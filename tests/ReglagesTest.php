<?php
/**
 * CardDAV 1.9.1 — les réglages d'administration dans la langue du navigateur,
 * le français par défaut (décision du propriétaire, 22 octobre 2026).
 *
 * Le cœur affiche le libellé et la description d'une `Property` tels quels :
 * la traduction se fait dans `configMapping()`, depuis `langs/admin/*.json`.
 *
 * @license AGPL-3.0-or-later
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/stubs.php';
require_once __DIR__ . '/../index.php';

final class ReglagesTest extends TestCase
{
	private const LANGUES = array('en', 'fr', 'ar');

	private function catalogue(string $sLangue) : array
	{
		$sChemin = __DIR__ . '/../langs/admin/' . $sLangue . '.json';
		$aData = \json_decode((string) \file_get_contents($sChemin), true);
		$this->assertTrue(\is_array($aData['REGLAGES'] ?? null), "{$sLangue} : JSON invalide ou section REGLAGES absente");
		return $aData['REGLAGES'];
	}

	/** Un navigateur, et ce que `configMapping()` en rend : nom => [libellé, description]. */
	private static function marque() : string
	{
		return (\defined('APP_PRIVATE_DATA') ? APP_PRIVATE_DATA : \sys_get_temp_dir() . '/') . 'convergent-langue-admin-choisie';
	}

	private function reglages(?string $sAcceptLanguage) : array
	{
		if (null === $sAcceptLanguage) {
			unset($_SERVER['HTTP_ACCEPT_LANGUAGE']);
		} else {
			$_SERVER['HTTP_ACCEPT_LANGUAGE'] = $sAcceptLanguage;
		}
		$g = new class extends CarddavPlugin {
			public function carte() : array { return $this->configMapping(); }
		};
		$a = array();
		foreach ($g->carte() as $p) {
			$a[$p->name] = array($p->label, $p->desc);
		}
		unset($_SERVER['HTTP_ACCEPT_LANGUAGE']);
		return $a;
	}

	public function testChaqueReglageATroisLibelles() : void
	{
		$aNoms = \array_keys($this->reglages('en'));
		$this->assertSame(2, \count($aNoms));
		foreach (self::LANGUES as $l) {
			$c = $this->catalogue($l);
			$this->assertSame(array(), \array_values(\array_diff($aNoms, \array_keys($c))), "{$l} : réglage sans libellé");
			$this->assertSame(array(), \array_values(\array_diff(\array_keys($c), $aNoms)), "{$l} : libellé d'un réglage disparu");
			foreach ($c as $n => $v) {
				$this->assertTrue('' !== \trim((string) ($v['label'] ?? '')), "{$l}/{$n} : libellé vide");
				$this->assertSame('' === $this->catalogue('en')[$n]['desc'], '' === $v['desc'], "{$l}/{$n} : description présente dans une langue et pas dans l'autre");
			}
		}
	}

	public function testLeNavigateurChoisitEtLeFrancaisEstLeDefaut() : void
	{
		$sEn = 'DAV default domain';
		$sFr = 'Domaine DAV par défaut';
		foreach (array(
			'en-US,en;q=0.9'              => $sEn,
			'en'                          => $sEn,
			'de-DE,de;q=0.9,en;q=0.8'     => $sEn,
			'fr-FR,fr;q=0.9,en;q=0.8'     => $sFr,
			'en;q=0.5,fr;q=0.8'           => $sFr,
			'fr;q=0.5,en;q=0.8'           => $sEn,
			'ar-TN,ar;q=0.9'              => $sFr,
			'de'                          => $sFr,
			''                            => $sFr,
			'en;q=0'                      => $sFr,
			'*'                           => $sFr,
			"en\r\nX-Injecte: 1"          => $sFr,
			\str_repeat('xx,', 300) . 'en' => $sFr,
		) as $sEntete => $sAttendu) {
			$this->assertSame($sAttendu, $this->reglages($sEntete)['dav_default_domain'][0], "Accept-Language « {$sEntete} »");
		}
		$this->assertSame($sFr, $this->reglages(null)['dav_default_domain'][0], 'sans en-tête : le français');
	}

	/** Le choix explicite, posé dans le panneau, l'emporte sur le navigateur. */
	public function testLeChoixExpliciteLEmporteSurLeNavigateur() : void
	{
		$sMarque = self::marque();
		@\unlink($sMarque);
		\RainLoop\Api::$langueAdmin = 'en';
		$this->assertSame('Domaine DAV par défaut', $this->reglages('fr-FR')['dav_default_domain'][0], 'en non choisi : le navigateur décide');
		try {
			\file_put_contents($sMarque, "x\n");
			$this->assertSame('DAV default domain', $this->reglages('fr-FR')['dav_default_domain'][0], 'en choisi : gardé contre un navigateur français');
			\RainLoop\Api::$langueAdmin = 'fr';
			$this->assertSame('Domaine DAV par défaut', $this->reglages('en-US')['dav_default_domain'][0], 'fr choisi : gardé contre un navigateur anglais');
			\RainLoop\Api::$langueAdmin = 'de';
			$this->assertSame('Domaine DAV par défaut', $this->reglages('en-US')['dav_default_domain'][0], 'autre langue choisie : le français');
		} finally {
			@\unlink($sMarque);
			\RainLoop\Api::$langueAdmin = 'en';
		}
	}

	/** La marque ne se pose que pour un enregistrement réussi qui porte `languageAdmin`. */
	public function testLaMarqueNeSePoseQueSurUnChoixDeLangueReussi() : void
	{
		$sMarque = self::marque();
		@\unlink($sMarque);
		$g = new CarddavPlugin();
		$g->Init();
		$this->assertSame('NoteLangueAdmin', $g->hooks['json.after-AdminSettingsUpdate'] ?? null);
		$g->actionsStub = new \RainLoop\Actions();
		try {
			$r = array('Result' => false);
			$g->actionsStub->params = array('languageAdmin' => 'en');
			$g->NoteLangueAdmin($r);
			$this->assertFalse(\is_file($sMarque), 'refus du cœur : pas de marque');
			$r = array('Result' => true);
			$g->actionsStub->params = array('Theme' => 'Convergent');
			$g->NoteLangueAdmin($r);
			$this->assertFalse(\is_file($sMarque), 'un autre réglage ne vaut pas choix de langue');
			$g->actionsStub->params = array('languageAdmin' => 'en');
			$g->NoteLangueAdmin($r);
			$this->assertTrue(\is_file($sMarque), 'choix enregistré : marque posée');
		} finally {
			@\unlink($sMarque);
		}
	}

	/** Le défaut de la 1.16.0 : un même panneau mêlait l'anglais et le français. */
	public function testUnPanneauAnglaisNePorteAucunFrancais() : void
	{
		foreach ($this->reglages('en-US') as $n => $a) {
			$this->assertSame(0, \preg_match('/[éèêàçù]|\b(?:les?|des|une?|par|pour)\b/u', \implode(' ', $a)), "{$n} : du français dans le panneau anglais");
		}
		foreach ($this->catalogue('ar') as $n => $a) {
			$this->assertSame(1, \preg_match('/\p{Arabic}/u', $a['label']), "{$n} : libellé arabe sans arabe");
		}
		foreach ($this->reglages(null) as $n => $a) {
			$this->assertSame($this->catalogue('fr')[$n]['label'], $a[0], "{$n} : le défaut n'est pas le français");
		}
	}
}
