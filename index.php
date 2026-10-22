<?php

class CarddavPlugin extends \RainLoop\Plugins\AbstractPlugin
{
	const
		NAME     = 'Mailbux CardDAV Auto',
		AUTHOR   = 'Mailwish / Mailbux — fork maintained by Convergent Cloud Computing',
		URL      = 'https://git.convergent.tn/fbennasr/SnappyMail-carddav',
		VERSION  = '1.9.1',
		RELEASE  = '2025-11-12',
		CATEGORY = 'Contacts',
		LICENSE  = 'No licence stated by the original author — see AUTHORS',
		DESCRIPTION = 'Auto-configures CardDAV sync - switches per account',
		REQUIRED = '2.0.0';
	
	private $lastConfiguredEmail = null;

	/**
	 * Un réglage d'administration, libellé et description dans la langue du
	 * navigateur de l'administrateur (`langs/admin/{en,fr}.json`, section
	 * `REGLAGES`).
	 *
	 * Le cœur affiche `label` et `desc` tels quels, sans passer par i18n : la
	 * traduction se fait donc ici. Elle vit hors de `langs/<langue>.json`, que
	 * `ReadLang()` verse dans le paquet de **chaque** utilisateur. La langue est
	 * lue dans `Accept-Language`, pas par `GetLanguage()` : `configMapping()` est
	 * appelé pendant la construction du gestionnaire de greffons, que
	 * `GetLanguage()` rappelle par son accroche `filter.language`.
	 */
	private static function reglage(string $sNom) : \RainLoop\Plugins\Property
	{
		static $aCache = array();
		$sLang = self::langueAdmin();
		foreach (\array_unique(array($sLang, 'en')) as $sEssai) {
			if (!isset($aCache[$sEssai])) {
				$sFichier = __DIR__ . '/langs/admin/' . $sEssai . '.json';
				$aData = \is_readable($sFichier) ? \json_decode((string) \file_get_contents($sFichier), true) : null;
				$aCache[$sEssai] = \is_array($aData['REGLAGES'] ?? null) ? $aData['REGLAGES'] : array();
			}
			if (isset($aCache[$sEssai][$sNom]['label'])) {
				return \RainLoop\Plugins\Property::NewInstance($sNom)
					->SetLabel((string) $aCache[$sEssai][$sNom]['label'])
					->SetDescription((string) ($aCache[$sEssai][$sNom]['desc'] ?? ''));
			}
		}
		return \RainLoop\Plugins\Property::NewInstance($sNom)->SetLabel($sNom);
	}

	/**
	 * La langue des réglages d'administration. Règle du propriétaire,
	 * 22 octobre 2026 : *proposer* la langue du navigateur, mais **garder le
	 * choix explicite** même s'il en diffère ; le français par défaut si le
	 * client ne choisit pas l'anglais.
	 *
	 * Le choix explicite est `admin_panel.language`, **seulement** si quelqu'un
	 * l'a posé dans le panneau (Général → langue) : le fichier fourni porte `en`
	 * sans que personne l'ait choisi, et la configuration seule ne distingue
	 * pas les deux. `NoteLangueAdmin()` pose donc une marque quand le panneau
	 * l'enregistre. Sans marque : `Accept-Language`, ordre des `q`, la première
	 * préférence `en` ou `fr` gagne, sinon le français. L'arabe n'est pas
	 * proposé : le panneau de SnappyMail 2.38.2 ne l'offre pas.
	 */
	protected static function langueAdmin() : string
	{
		if (\is_file(self::marqueLangueAdmin())) {
			try {
				$sChoix = (string) \RainLoop\Api::Config()->Get('admin_panel', 'language', '');
				return 0 === \strncasecmp($sChoix, 'en', 2) ? 'en' : 'fr';
			} catch (\Throwable $e) {
				// configuration illisible : on retombe sur le navigateur
			}
		}
		$sEntete = \strtolower(\substr((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''), 0, 512));
		$aPrefs = array();
		foreach (\explode(',', $sEntete) as $iRang => $sPart) {
			if (\preg_match('/^\s*([a-z]{1,8})(?:-[a-z0-9]{1,8})*\s*(?:;\s*q\s*=\s*([01](?:\.\d{0,3})?))?\s*$/D', $sPart, $m)) {
				$fQ = isset($m[2]) && '' !== $m[2] ? (float) $m[2] : 1.0;
				if (0 < $fQ) {
					$aPrefs[] = array($fQ, $iRang, $m[1]);
				}
			}
		}
		\usort($aPrefs, fn ($a, $b) => $b[0] <=> $a[0] ?: $a[1] <=> $b[1]);
		foreach ($aPrefs as $a) {
			if ('en' === $a[2] || 'fr' === $a[2]) {
				return $a[2];
			}
		}
		return 'fr';
	}

	/** Partagée par tous les greffons de la suite : le même nom, la même règle. */
	protected static function marqueLangueAdmin() : string
	{
		return (\defined('APP_PRIVATE_DATA') ? APP_PRIVATE_DATA : \sys_get_temp_dir() . '/') . 'convergent-langue-admin-choisie';
	}

	/**
	 * `json.after-AdminSettingsUpdate` : le panneau vient d'enregistrer ses
	 * réglages. Le cœur a déjà exigé la session d'administrateur
	 * (`IsAdminLoggined()` lève sinon, et cette accroche ne court pas) ; on
	 * exige en plus une réponse réussie et un `languageAdmin` envoyé — tout
	 * autre réglage enregistré ne vaut pas choix de langue.
	 */
	public function NoteLangueAdmin(&$aResponse) : void
	{
		if (!\is_array($aResponse) || true !== ($aResponse['Result'] ?? null)) {
			return;
		}
		$sLang = (string) $this->Manager()->Actions()->GetActionParam('languageAdmin', '');
		if ('' === $sLang) {
			return;
		}
		@\file_put_contents(self::marqueLangueAdmin(), \gmdate('c') . "\n", LOCK_EX);
	}

		protected function configMapping() : array
	{
		return array(
			self::reglage('carddav_url_template')
				->SetType(\RainLoop\Enumerations\PluginPropertyType::STRING)
				->SetDefaultValue(''),
			self::reglage('dav_default_domain')
				->SetType(\RainLoop\Enumerations\PluginPropertyType::STRING)
				->SetDefaultValue('')
		);
	}

	/**
	 * Expand the configured template for one account.
	 *
	 * With Cyrus "virtdomains: userid" a mailbox in the configured default
	 * domain is addressed by its local part, e.g.
	 * /dav/addressbooks/user/<login>/, while every other domain keeps the
	 * full address in the path.
	 */
	private function buildDavUrl(string $sEmail) : string
	{
		// No built-in default: the URL belongs to the deployment, so it comes
		// from the plugin's settings page and nowhere else. Empty means the
		// admin has not configured it yet, and we refuse to invent one.
		$sTemplate = \trim($this->Config()->Get('plugin', 'carddav_url_template', ''));
		if (!\strlen($sTemplate)) {
			return '';
		}
		$sDefaultDomain = \strtolower(\trim($this->Config()->Get('plugin', 'dav_default_domain', '')));

		$aParts = \explode('@', $sEmail, 2);
		$sLogin = $aParts[0];
		$sDomain = $aParts[1] ?? '';
		$sUser = ($sDefaultDomain && \strtolower($sDomain) === $sDefaultDomain) ? $sLogin : $sEmail;

		return \strtr($sTemplate, array(
			'{user}'   => $sUser,
			'{email}'  => $sEmail,
			'{login}'  => $sLogin,
			'{domain}' => $sDomain
		));
	}

	public function Init() : void
	{
		$this->addHook('json.after-AdminSettingsUpdate', 'NoteLangueAdmin');
		// Hook into login.success - runs for main login AND added accounts
		$this->addHook('login.success', 'AutoConfigureCardDAV');
		
		// Hook after AccountSwitch JSON action completes
		$this->addHook('json.after-AccountSwitch', 'OnAfterAccountSwitch');
	}
	
	/**
	 * Called after AccountSwitch action completes
	 */
	public function OnAfterAccountSwitch(array &$aResponse)
	{
		if (!empty($aResponse['Result'])) {
			// Account switch succeeded - clear last email to force update
			$this->lastConfiguredEmail = null;
			
			// Update CardDAV for switched account
			$oAccount = $this->Manager()->Actions()->getAccountFromToken();
			if ($oAccount) {
				$this->AutoConfigureCardDAV($oAccount);
			}
		}
	}

	/**
	 * Auto-configure CardDAV sync for current account
	 */
	public function AutoConfigureCardDAV(\RainLoop\Model\Account $oAccount)
	{
		if (!$oAccount || !$oAccount->Email()) {
			return;
		}

		$sEmail = $oAccount->Email();
		
		// Only update if email changed (avoid updating on every request)
		if ($this->lastConfiguredEmail === $sEmail) {
			return;
		}
		
		$this->lastConfiguredEmail = $sEmail;
		$oActions = $this->Manager()->Actions();
		
		try {
			// Get storage provider
			$oStorageProvider = $oActions->StorageProvider();
			if (!$oStorageProvider) {
				return;
			}

			// Always update CardDAV to match current account email.
			// Was hardcoded to another vendor's host (my.mailbux.com), which is
			// not this server — CardDAV never reached the local Cyrus DAV and
			// the caldav plugin, which derives its URL from this one, showed no
			// events either.
			$sCardDAVUrl = $this->buildDavUrl($sEmail);
			if (!\strlen($sCardDAVUrl)) {
				\SnappyMail\Log::notice('CardDAV',
					'carddav_url_template is not set; leaving contacts_sync untouched');
				return;
			}
			
			// Get account credentials
			$sPassword = null;
			$sPasswordHMAC = null;
			
			// Try to get password from additionalaccounts file
			$aAdditionalAccounts = $this->getAdditionalAccounts($oAccount, $oStorageProvider);
			
			if (isset($aAdditionalAccounts[$sEmail])) {
				// Found in additional accounts - convert password format
				try {
					// Get the main account (which has CryptKey method)
					$oMainAccount = $this->Manager()->Actions()->GetMainAccountFromToken();
					if (!$oMainAccount) {
						return;
					}
					
					$sCryptKey = $oMainAccount->CryptKey();
					
					// Decrypt using DecryptUrlSafe (dot-separated format)
					$sRawPassword = \SnappyMail\Crypt::DecryptUrlSafe($aAdditionalAccounts[$sEmail]['pass'], $sCryptKey);
					
					// Convert SensitiveString to string if needed
					if (is_object($sRawPassword) && method_exists($sRawPassword, '__toString')) {
						$sRawPassword = (string)$sRawPassword;
					}
					
					if (!$sRawPassword) {
						return;
					}
					
					// Re-encrypt in JSON array format for contacts_sync
					$sPassword = \SnappyMail\Crypt::EncryptToJSON($sRawPassword, $sCryptKey);
					$sPasswordHMAC = \hash_hmac('sha1', $sPassword, $sCryptKey);
				} catch (\Throwable $e) {
					return; // Skip update if password conversion fails
				}
			} else {
				// Primary account - encrypt password
				$sRawPassword = $oAccount->ImapPass();
				$sCryptKey = $oAccount->CryptKey();
				$sPassword = \SnappyMail\Crypt::EncryptToJSON($sRawPassword, $sCryptKey);
				$sPasswordHMAC = \hash_hmac('sha1', $sPassword, $sCryptKey);
			}
			
			// Keep sync switched off if it was deliberately disabled: this
			// plugin runs on every login and would otherwise silently re-arm a
			// two-way sync that an admin turned off.
			$iMode = 1;
			$mExisting = $oStorageProvider->Get($oAccount,
				\RainLoop\Providers\Storage\Enumerations\StorageType::CONFIG, 'contacts_sync');
			if ($mExisting && \is_string($mExisting)) {
				$aExisting = \json_decode($mExisting, true);
				if (\is_array($aExisting) && isset($aExisting['Mode']) && !$aExisting['Mode']) {
					$iMode = 0;
				}
			}

			// Prepare CardDAV data
			$aCardDAVData = [
				'Mode' => $iMode,
				'User' => $sEmail,
				'Password' => $sPassword,
				'PasswordHMAC' => $sPasswordHMAC,
				'Url' => $sCardDAVUrl
			];
			
			// Save CardDAV sync data
			$oStorageProvider->Put($oAccount,
				\RainLoop\Providers\Storage\Enumerations\StorageType::CONFIG,
				'contacts_sync',
				\json_encode($aCardDAVData)
			);
			
		} catch (\Exception $e) {
			// Silent fail
		}
	}
	
	/**
	 * Get additional accounts from storage
	 */
	private function getAdditionalAccounts(\RainLoop\Model\Account $oAccount, $oStorageProvider)
	{
		try {
			$mData = $oStorageProvider->Get($oAccount,
				\RainLoop\Providers\Storage\Enumerations\StorageType::CONFIG,
				'additionalaccounts'
			);
			
			if ($mData && \is_string($mData)) {
				$aData = \json_decode($mData, true);
				return \is_array($aData) ? $aData : [];
			}
		} catch (\Exception $e) {
			// Silent fail
		}
		
		return [];
	}
}
