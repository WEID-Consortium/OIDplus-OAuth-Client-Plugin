<?php

/*
 * OIDplus 2.0
 * Copyright 2019 - 2021 Daniel Marschall, ViaThinkSoft
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Frdlweb\OIDplus\Plugins\PublicPages\LoginWebfan;

use ViaThinkSoft\OIDplus\Core\OIDplus;
use ViaThinkSoft\OIDplus\Core\OIDplusPagePluginPublic;
use ViaThinkSoft\OIDplus\Core\OIDplusException;
use ViaThinkSoft\OIDplus\Plugins\PublicPages\Login\INTF_OID_1_3_6_1_4_1_37476_2_5_2_3_5;

use Wehowski\Helpers\ArrayHelper;


use Frdlweb\SmokeShare;


class OIDplusPagePublicLoginWebfan extends OIDplusPagePluginPublic implements INTF_OID_1_3_6_1_4_1_37476_2_5_2_3_5 {

	protected $db_table_exists = false;

	
  
    public static function removeAccountsLink(int $id) : void {
        OIDplus::db()->query("DELETE FROM ###oidplus_oauth_users WHERE id = ?", [$id]);
    }

    public static function addAccountsLink(int $ra_id, string|int $external_id, string $provider, ?string $ip): void {
        if (is_null($ip)) $ip = OIDplus::getClientIpAddress();
        OIDplus::db()->query(
            "INSERT INTO ###oidplus_oauth_users (ra_id, external_id, provider, ip) VALUES (?, ?, ?, ?)",
            [$ra_id, (string)$external_id, $provider, $ip]
        );
    }

    public static function getAccount(string|int $external_id, string $provider): bool|array {
        $res = OIDplus::db()->query(
            "SELECT * FROM ###oidplus_oauth_users WHERE external_id = ? AND provider = ?",
            [(string)$external_id, $provider]
        );
        if (!$res->any()) return false;
        return $res->fetch_array();
    }

    public static function getRaById(int $id): bool|array {
        $res = OIDplus::db()->query("SELECT * FROM ###ra WHERE ra_id = ?", [$id]);
        if (!$res->any()) return false;
        return $res->fetch_array();
    }

    public static function getRaByEmail(string $email): bool|array {
        $res = OIDplus::db()->query("SELECT * FROM ###ra WHERE email = ?", [$email]);
        if (!$res->any()) return false;
        return $res->fetch_array();
    }
	
    
	
   	
	public function action(string $actionID, array $params): array {
		throw new OIDplusException(_L('Unknown action ID'));
	}

  public function init($html = true): void {
        // Tabellen ggf. anlegen (MySQL)
        if (!OIDplus::db()->tableExists("###oidplus_oauth_users") || !OIDplus::db()->tableExists("###oidplus_oauth_providers")) {
            if (OIDplus::db()->getSlang()->id() == 'mysql') {
                OIDplus::db()->query("
CREATE TABLE IF NOT EXISTS ###oidplus_oauth_users (
  `id` INT NOT NULL AUTO_INCREMENT,
  `ra_id` INT UNSIGNED DEFAULT NULL,
  `external_id` VARCHAR(255) NOT NULL,
  `provider` VARCHAR(255) NOT NULL,
  `ip` VARCHAR(40) NOT NULL,
  UNIQUE KEY `account` (`external_id`,`provider`,`ra_id`),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                OIDplus::db()->query("
CREATE TABLE IF NOT EXISTS ###oidplus_oauth_providers (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(64) NOT NULL,
  `homepage` VARCHAR(512) NOT NULL,
  `apiBaseUrl` VARCHAR(512) NOT NULL,
  `authorizeUrl` VARCHAR(512) NOT NULL,
  `requestTokenUrl` VARCHAR(512) NOT NULL,
  `accessTokenUrl` VARCHAR(512) NOT NULL,
  `client_identifier` VARCHAR(512) NOT NULL,
  `client_secret` VARCHAR(512) NOT NULL,
  `provider_model` VARCHAR(512) DEFAULT 'oauth.php',
  UNIQUE KEY `provider` (`name`),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                $this->db_table_exists = true;
            } else {
                $this->db_table_exists = false; // TODO: andere DBMS
            }
        } else {
            $this->db_table_exists = true;
        }
    }


	
	
	
	
	
	public function gui($id, &$out, &$handled): void {

		 if ($id === 'oidplus:weid_info') {
			 	$handled = true;
			$target = 'https://weid.info';
			$out['text'] = '<p>'._L('Please wait...').'</p><p><a href="'.$target.'">Goto '.$target.'...</a></p><script>window.location.href = '.js_escape($target).';</script>';
		 }elseif($id === 'oidplus:webfan_goto_frdlweb') {
		// 'oidplus:webfan_goto_frdl_home'
					$handled = true;
			$target = 'https://frdlweb.de';
			$out['text'] = '<p>'._L('Please wait...').'</p><p><a href="'.$target.'">Goto '.$target.'...</a></p><script>window.location.href = '.js_escape($target).';</script>';
		}elseif($id === 'oidplus:webfan_goto_webfan') {
		// 'oidplus:webfan_goto_frdl_home'
					$handled = true;
			$target = 'https://webfan.de';
			$out['text'] = '<p>'._L('Please wait...').'</p><p><a href="'.$target.'">Goto '.$target.'...</a></p><script>window.location.href = '.js_escape($target).';</script>';
		}elseif($id === 'oidplus:webfan_goto_webfan_home') {
		// 'oidplus:webfan_goto_frdl_home'
					$handled = true;
			$target = '?goto=oid%3A1.3.6.1.4.1.37553.8.1.8';
			$out['text'] = '<p>'._L('Please wait...').'</p><p><a href="'.$target.'">Goto '.$target.'...</a></p><script>window.location.href = '.js_escape($target).';</script>';
		}elseif ($id === 'oidplus:login_webfan') {
            $handled = true;
            $out['title'] = _L('Login using Webfan/GitHub/Google');
            $out['icon']  = OIDplus::baseConfig()->getValue('Webfan_OAUTH2_ICON_URL', 'https://webfan.de/favicon.ico');

            if (!OIDplus::baseConfig()->getValue('Webfan_OAUTH2_ENABLED', false)) {
                $out['icon'] = 'img/error_big.png';
                $out['text'] = _L('Webfan OAuth authentication is disabled on this system.');
                return;
            }

            // Wir leiten auf unsere oauth.php um, die den Flow startet (inkl. State/Scopes)
            $baseCb = OIDplus::webpath(__DIR__, OIDplus::PATH_ABSOLUTE).'oauth.php';
            $providers = [
                'webfan' => 'Webfan',
                'github' => 'GitHub',
                'google' => 'Google'
            ];
            $links = [];
            foreach ($providers as $key => $label) {
				$p = static::oidplus_oauth_load_provider_config($key);
				if(empty($p['clientId']))continue;
                $links[] = '<p><a class="btn btn-primary" href="'.htmlentities($baseCb.'?provider='.$key).'" onclick="location.href=this.href;">'.
                           _L('Login using %1', $label).'</a></p>';
            }

            $out['text'] = '<h3>'._L('Choose a provider').'</h3>'.implode('', $links);
        }elseif($id === 'oidplus:login_with_webfan') {
		// 'oidplus:webfan_goto_frdl_home'
					$handled = true;
			$target = 'https://'.$_SERVER['SERVER_NAME'].'/plugins/frdl/publicPages/801_login_webfan/oauth.php?provider=webfan';
			$out['text'] = '<p>'._L('Please wait...').'</p><p><a href="'.$target.'">Goto '.$target.'...</a></p><script>window.location.href = '.js_escape($target).';</script>';
		}
	}

	public function publicSitemap(&$out): void {
		$out[] = 'oidplus:login_webfan';
	}

	public function tree(array &$json, ?string $ra_email = null, bool $nonjs = false, string $req_goto = ''): bool {
		$tree_icon =OIDplus::baseConfig()->getValue('Webfan_OAUTH2_ICON_URL', 'https://webfan.de/favicon.ico'); // default icon (folder)
		$weid_icon ='https://weid.info/favicon.ico'; 
			
 
		
				  $isCentral = OIDplus::baseConfig()->getValue('TENANT_APP_ID_OID') === '1.3.6.1.4.1.37476.30.9.1494410075'  
			   && OIDplus::baseConfig()->getValue('TENANT_OBJECT_ID_OID' ) === '1.3.6.1.4.1.37553';
		
	if($isCentral){	
		
	
		$item= [
			'id' => 'oidplus:resources$Tools/Whois.html',
			'icon' => 'plugins/viathinksoft/publicPages/300_search/img/main_icon16.png',
			'text' =>  'Whois Lookup',
		];
		array_unshift($json, $item);	
	 }	
		 
			 
		if (OIDplus::baseConfig()->getValue('Webfan_OAUTH2_ENABLED', false) 
		    && !count(OIDplus::authUtils()->loggedInRaList())) {
			$item = array(
				'id'=>'oidplus:login_with_webfan',
				'text' => str_replace('Google', 'Webfan', _L('Login using Google')),
				//OIDplus::webpath(__DIR__).'treeicon.png'
				'icon' => OIDplus::baseConfig()->getValue('Webfan_OAUTH2_ICON_URL', 'https://webfan.de/favicon.ico')
			);			
			array_unshift($json, $item);
		}
				
   		 
		
	  return true;
	}
	public function tree_search($request) {
		return false;
	}

 
	public function alternativeLoginMethods() :array {
		$logins = array();
		
			  $isCentral = OIDplus::baseConfig()->getValue('TENANT_APP_ID_OID') === '1.3.6.1.4.1.37476.30.9.1494410075'  
			   && OIDplus::baseConfig()->getValue('TENANT_OBJECT_ID_OID' ) === '1.3.6.1.4.1.37553';
		
	//if($isCentral){		
		if (OIDplus::baseConfig()->getValue('Webfan_OAUTH2_ENABLED', false)) {
			$logins[] = array(
				'oidplus:login_webfan',
				str_replace('Google', 'Webfan', _L('Login using Google')),
				//OIDplus::webpath(__DIR__).'treeicon.png'
				OIDplus::baseConfig()->getValue('Webfan_OAUTH2_ICON_URL', 'https://webfan.de/favicon.ico')
			);			
		}
	//}//if(!OIDplus::baseConfig()->getValue('TENANT_IS_TENANT'))	
		return $logins;
	}
	
	public static function oidplus_oauth_load_provider_config(string $providerName): array {
    $providerName = strtolower($providerName);

    // Versuche Provider aus DB zu laden
    $res = OIDplus::db()->query("SELECT * FROM ###oidplus_oauth_providers WHERE name = ?", [$providerName]);
    if ($res->any()) {
        $row = $res->fetch_array();
        return [
            'name' => $providerName,
            'clientId' => $row['client_identifier'] ?? '',
            'clientSecret' => $row['client_secret'] ?? '',
            'urlAuthorize' => $row['authorizeUrl'] ?? '',
            'urlAccessToken' => $row['accessTokenUrl'] ?? '',
            'urlResourceOwnerDetails' => $row['apiBaseUrl'] ?? '',
            'scope' => OIDplus::baseConfig()->getValue('scope_'.$providerName, '')
        ];
    }

    // Wenn nicht in DB vorhanden -> vernünftige Defaults ermitteln und in DB anlegen
    $baseConfig = function($key, $def='') { return OIDplus::baseConfig()->getValue($key, $def); };

    switch ($providerName) {
        case 'webfan':
        case 'nextcloud':
            $baseUrl = rtrim($baseConfig('Webfan_Nextcloud_Url', 'https://webfan.de'), '/');
            $authorizeUrl = $baseUrl . '/apps/oauth2/authorize';
            $accessTokenUrl = $baseUrl . '/apps/oauth2/api/v1/token';
            // resource owner (Nextcloud OCS user endpoint)
            $resourceOwnerUrl = $baseUrl . '/ocs/v2.php/cloud/user?format=json';
            $clientId = $baseConfig('Webfan_OAUTH2_CLIENT_ID', '');
            $clientSecret = $baseConfig('Webfan_OAUTH2_CLIENT_SECRET', '');
            $homepage = $baseUrl;
            $scope = $baseConfig('scope_webfan', 'email');
            break;

        case 'google':
            $authorizeUrl = 'https://accounts.google.com/o/oauth2/v2/auth';
            $accessTokenUrl = 'https://oauth2.googleapis.com/token';
            $resourceOwnerUrl = 'https://www.googleapis.com/oauth2/v1/userinfo?alt=json';
            $clientId = $baseConfig('Google_OAUTH2_CLIENT_ID', '');
            $clientSecret = $baseConfig('Google_OAUTH2_CLIENT_SECRET', '');
            $homepage = 'https://accounts.google.com';
            $scope = $baseConfig('scope_google', 'openid email profile');
            break;

        case 'github':
            $authorizeUrl = 'https://github.com/login/oauth/authorize';
            $accessTokenUrl = 'https://github.com/login/oauth/access_token';
            $resourceOwnerUrl = 'https://api.github.com/user';
            $clientId = $baseConfig('Github_OAUTH2_CLIENT_ID', '');
            $clientSecret = $baseConfig('Github_OAUTH2_CLIENT_SECRET', '');
            $homepage = 'https://github.com';
            $scope = $baseConfig('scope_github', 'user:email');
            break;

        default:
            // generische Fallback-URLs (leere Tokens/URLs, so dass Admin die Werte prüfen kann)
            $authorizeUrl = $baseConfig(strtoupper($providerName).'_AUTHORIZE', '');
            $accessTokenUrl = $baseConfig(strtoupper($providerName).'_TOKEN', '');
            $resourceOwnerUrl = $baseConfig(strtoupper($providerName).'_API', '');
            $clientId = $baseConfig(strtoupper($providerName).'_CLIENT_ID', '');
            $clientSecret = $baseConfig(strtoupper($providerName).'_CLIENT_SECRET', '');
            $homepage = $baseConfig($providerName.'_homepage', '');
            $scope = $baseConfig('scope_'.$providerName, '');
            break;
    }

    // Insert in DB (parameterisiert)
    OIDplus::db()->query(
        "INSERT INTO ###oidplus_oauth_providers
           (name, homepage, apiBaseUrl, authorizeUrl, requestTokenUrl, accessTokenUrl, client_identifier, client_secret, provider_model)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [
            $providerName,
            $homepage,
            $resourceOwnerUrl,
            $authorizeUrl,
            '', // requestTokenUrl (OAuth2 meist leer)
            $accessTokenUrl,
            $clientId,
            $clientSecret,
            'oauth.php'
        ]
    );

    // neu laden und zurückgeben (falls Insert fehl schlägt, geben wir die berechneten Defaults zurück)
    $res2 = OIDplus::db()->query("SELECT * FROM ###oidplus_oauth_providers WHERE name = ?", [$providerName]);
    if ($res2->any()) {
        $row = $res2->fetch_array();
        return [
            'name' => $providerName,
            'clientId' => $row['client_identifier'] ?? $clientId,
            'clientSecret' => $row['client_secret'] ?? $clientSecret,
            'urlAuthorize' => $row['authorizeUrl'] ?? $authorizeUrl,
            'urlAccessToken' => $row['accessTokenUrl'] ?? $accessTokenUrl,
            'urlResourceOwnerDetails' => $row['apiBaseUrl'] ?? $resourceOwnerUrl,
            'scope' => $scope
        ];
    }

    // Fallback: berechnete Werte (DB-Insert evtl. fehlgeschlagen - trotzdem weiterarbeiten)
    return [
        'name' => $providerName,
        'clientId' => $clientId,
        'clientSecret' => $clientSecret,
        'urlAuthorize' => $authorizeUrl,
        'urlAccessToken' => $accessTokenUrl,
        'urlResourceOwnerDetails' => $resourceOwnerUrl,
        'scope' => $scope
    ];
}

}
