<?php
/**
 * CardDAV — the address-book URL.
 *
 * The template decides which address book the webmail binds to. There is no
 * built-in default on purpose: inventing a URL would point the plugin at
 * somebody else's server, or at nothing, and both fail in ways that look like
 * a broken account rather than a missing setting.
 *
 * @license AGPL-3.0-or-later
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/stubs.php';
require_once __DIR__ . '/../index.php';

final class DavUrlTest extends TestCase
{
    private function plugin(array $config = []): CarddavPlugin
    {
        $actions             = new \RainLoop\Actions();
        $actions->account    = new \RainLoop\Account('paul@convergent.tn');
        $plugin              = new CarddavPlugin();
        $plugin->actionsStub = $actions;
        $plugin->config      = $config;
        return $plugin;
    }

    private function url(array $config, string $email): string
    {
        $plugin = $this->plugin($config);
        return (new ReflectionMethod($plugin, 'buildDavUrl'))->invoke($plugin, $email);
    }

    /** Nothing configured means nothing returned — never a guessed URL. */
    public function testAnUnconfiguredTemplateYieldsNothing(): void
    {
        self::assertSame('', $this->url([], 'paul@convergent.tn'));
        self::assertSame('', $this->url(['carddav_url_template' => '   '], 'paul@convergent.tn'));
    }

    /** Each placeholder is replaced by the part of the address it names. */
    public function testEveryPlaceholderIsSubstituted(): void
    {
        self::assertSame(
            'paul@example.org|paul@example.org|paul|example.org',
            $this->url(['carddav_url_template' => '{user}|{email}|{login}|{domain}'], 'paul@example.org')
        );
    }

    /** On the local domain the server wants a bare login. */
    public function testTheDefaultDomainIsStrippedFromTheUserPlaceholder(): void
    {
        $config = [
            'carddav_url_template' => 'https://dav.convergent.tn/dav/addressbooks/{user}/Default/',
            'dav_default_domain'   => 'convergent.tn',
        ];

        self::assertSame('https://dav.convergent.tn/dav/addressbooks/paul/Default/',
            $this->url($config, 'paul@convergent.tn'));

        self::assertSame('https://dav.convergent.tn/dav/addressbooks/paul@elsewhere.org/Default/',
            $this->url($config, 'paul@elsewhere.org'),
            'an address outside the domain keeps its domain');
    }

    /** The domain comparison ignores case, as DNS does. */
    public function testTheDomainComparisonIsCaseInsensitive(): void
    {
        $config = ['carddav_url_template' => '{user}', 'dav_default_domain' => 'Convergent.TN'];

        foreach (['paul@convergent.tn', 'paul@CONVERGENT.TN'] as $email) {
            self::assertSame('paul', $this->url($config, $email), $email);
        }
    }

    /** Without a default domain the full address is always used. */
    public function testWithoutADefaultDomainTheFullAddressIsUsed(): void
    {
        self::assertSame('paul@convergent.tn',
            $this->url(['carddav_url_template' => '{user}'], 'paul@convergent.tn'));
    }

    /** A near-miss domain is not stripped: a suffix match is not a match. */
    public function testASubdomainIsNotMistakenForTheDefaultDomain(): void
    {
        $config = ['carddav_url_template' => '{user}', 'dav_default_domain' => 'convergent.tn'];

        self::assertSame('paul@mail.convergent.tn', $this->url($config, 'paul@mail.convergent.tn'));
    }

    /** An address with no domain does not produce a stray separator. */
    public function testAnAddressWithoutADomainIsHandled(): void
    {
        self::assertSame('paul/', $this->url(['carddav_url_template' => '{login}/{domain}'], 'paul'));
    }

    /** A placeholder appearing twice is replaced both times. */
    public function testARepeatedPlaceholderIsReplacedEverywhere(): void
    {
        self::assertSame('paul/paul',
            $this->url(['carddav_url_template' => '{login}/{login}'], 'paul@x.tn'));
    }

    /** A template with no placeholder at all is returned unchanged. */
    public function testAFixedTemplateIsLeftAlone(): void
    {
        self::assertSame('https://dav.example.com/shared/',
            $this->url(['carddav_url_template' => 'https://dav.example.com/shared/'], 'paul@x.tn'));
    }
}
