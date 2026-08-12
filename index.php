<?php

class CarddavPlugin extends \RainLoop\Plugins\AbstractPlugin
{
	const
		NAME     = 'Mailbux CardDAV Auto',
		VERSION  = '1.7',
		RELEASE  = '2025-11-12',
		CATEGORY = 'Contacts',
		DESCRIPTION = 'Auto-configures CardDAV sync - switches per account',
		REQUIRED = '2.0.0';
	
	private $lastConfiguredEmail = null;

	protected function configMapping() : array
	{
		return array(
			\RainLoop\Plugins\Property::NewInstance('carddav_url_template')
				->SetLabel('CardDAV URL template')
				->SetType(\RainLoop\Enumerations\PluginPropertyType::STRING)
				->SetDescription('Addressbook URL for this server, e.g.'
					. ' https://dav.example.com/dav/addressbooks/user/{user}/Default'
					. ' - {user} = mailbox name as the DAV server knows it, {email} = full address,'
					. ' {login} = local part, {domain} = domain part. Leave empty to disable.')
				->SetDefaultValue(''),
			\RainLoop\Plugins\Property::NewInstance('dav_default_domain')
				->SetLabel('DAV default domain')
				->SetType(\RainLoop\Enumerations\PluginPropertyType::STRING)
				->SetDescription('Addresses in this domain are addressed by local part only,'
					. ' everything else by full address. On Cyrus with "virtdomains: userid" this'
					. ' is imapd.conf defaultdomain. Leave empty to always use the full address.')
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
