<?php
declare(strict_types=1);

namespace Frdlweb\OIDplus\Plugins\PublicPages\LoginWebfan;

use ViaThinkSoft\OIDplus\Core\OIDplus;
use ViaThinkSoft\OIDplus\Core\OIDplusGui;
use ViaThinkSoft\OIDplus\Core\OIDplusException;
use ViaThinkSoft\OIDplus\Core\OIDplusRA;
ob_start();
// ---- OIDplus Bootstrapping ----
require_once __DIR__ . '/../../../../includes/oidplus.inc.php';
OIDplus::init(true);
set_exception_handler([OIDplusGui::class, 'html_exception_handler']);
set_time_limit(55);


// ---- Config-Check ----
if (!OIDplus::baseConfig()->getValue('Webfan_OAUTH2_ENABLED', false)) {
    throw new OIDplusException(_L('Webfan OAuth authentication is disabled on this system.'));
}



spl_autoload_register(function (string $class_name): void {
    // Map the namespace to the corresponding folder
    $namespace_mapping = [
        'Webfan\\OAuth\\' => 'oauth',
        'Psr\\Http\\Message\\' => 'src-psr-messages',
    ];
 
    foreach ($namespace_mapping as $namespace => $directory) {
        if (
            strpos($class_name, $namespace = trim($namespace, '\\')) !== 0
            || (!$directory = realpath(__DIR__ . DIRECTORY_SEPARATOR . trim($directory, DIRECTORY_SEPARATOR)))
        ) {
            continue; // Class name doesn't match or the directory doesn't exist
        }
 
        // Require the file
        $class_file = $directory . str_replace([$namespace, '\\'], ['', DIRECTORY_SEPARATOR], $class_name) . '.php';
        if (file_exists($class_file)) {
            require_once $class_file;
        }
    }
});


 
function oidplus_oauth_load_provider_config(string $providerName): array {
  return OIDplusPagePublicLoginWebfan::oidplus_oauth_load_provider_config($providerName);
}


/**
 * Erzeuge Redirect-URI
 */
function oidplus_oauth_redirect_uri(string $provider): string {
    // Standard: diese Datei ist die Callback-URL (inkl. provider=...)
    // Passe die Domain ggf. an deine Instanz an.
    $self = OIDplus::webpath(__DIR__, \ViaThinkSoft\OIDplus\Core\OIDplus::PATH_ABSOLUTE).'oauth.php';
    $delim = (strpos($self, '?') !== false) ? '&' : '?';
    return $self . $delim . 'provider=' . rawurlencode($provider);
}

/**
 * Hole einen konfigurierten Provider-Client aus deinen Webfan\OAuth Klassen.
 * Erwartet, dass \Webfan\OAuth\Provider\* Klassen vorhanden sind.
 */
function oidplus_make_provider(string $providerName) {
    $cfg = oidplus_oauth_load_provider_config($providerName);
    $redirectUri = oidplus_oauth_redirect_uri($providerName);
 
    switch ($cfg['name']) {
        case 'webfan':
            return new \Webfan\OAuth\Provider\GenericProvider([
                'clientId'                => $cfg['clientId'],
                'clientSecret'            => $cfg['clientSecret'],
                'redirectUri'             => $redirectUri,
                'urlAuthorize'            => $cfg['urlAuthorize'],
                'urlAccessToken'          => $cfg['urlAccessToken'],
                'urlResourceOwnerDetails' => $cfg['urlResourceOwnerDetails'],
                'scopes'                  => array_filter(array_map('trim', explode(' ', (string)$cfg['scope']))),
                'scopeSeparator'          => ' ',
                'headers'                 => ['OCS-APIRequest' => 'true'] // Nextcloud/OCS
            ]);
        case 'google':
            return new \Webfan\OAuth\Provider\Google([
                'clientId'     => $cfg['clientId'],
                'clientSecret' => $cfg['clientSecret'],
                'redirectUri'  => $redirectUri,
                'scopes'       => array_filter(array_map('trim', explode(' ', (string)$cfg['scope']))),
            ]);
        case 'github':
            return new \Webfan\OAuth\Provider\Github([
                'clientId'     => $cfg['clientId'],
                'clientSecret' => $cfg['clientSecret'],
                'redirectUri'  => $redirectUri,
                'scopes'       => array_filter(array_map('trim', explode(' ', (string)$cfg['scope']))),
            ]);
        default:
            throw new OIDplusException('Unknown provider for client creation: '.$cfg['name']);
    }
}

/**
 * OAuth Flow: starte Authorization
 */
function oidplus_oauth_begin($client, string $provider): void {
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    $stateKey = 'oauth2state_'.$provider;
    $authUrl = $client->getAuthorizationUrl();
    $_SESSION[$stateKey] = $client->getState();

    header('Location: '.$authUrl);
    //exit;
	die('<a href="'.$authUrl.'">Got to '.$authUrl.'</a>');
}

/**
 * ID-basierte Verknüpfung + Login (provider/external_id → RA)
 */
function oidplus_oauth_link_and_login(string $provider, string $externalId, ?string $email, ?string $name): void {
    // 1) Mapping per provider+external_id?
    $res = OIDplus::db()->query(
        "SELECT * FROM ###oidplus_oauth_users WHERE provider = ? AND external_id = ?",
        [$provider, $externalId]
    );
    if ($res->any()) {
        $row = $res->fetch_array();
        $ra_id = (int)($row['ra_id'] ?? 0);

        if ($ra_id > 0) {
            // RA aus ra_id holen
            $raRes = OIDplus::db()->query("SELECT * FROM ###ra WHERE ra_id = ?", [$ra_id]);
            if (!$raRes->any()) {
                throw new OIDplusException('Linked RA not found (database inconsistency).');
            }
            $ra = $raRes->fetch_array();
            $raEmail = $ra['email'];
            OIDplus::authUtils()->raLoginEx($raEmail, 'OAuth:'.$provider);
            OIDplus::db()->query("UPDATE ###ra SET last_login = ".OIDplus::db()->sqlDate()." WHERE email = ?", [$raEmail]);
            OIDplus::logger()->log("V2:[OK]RA(%1)", "RA '%1' logged in via ".$provider." OAuth2 (by link)", $raEmail);
            OIDplus::invoke_shutdown();	
			$stateKey = 'oauth2state_'.$provider;	
			unset($_SESSION[$stateKey]);		
			$target = OIDplus::webpath(null, OIDplus::PATH_ABSOLUTE);//.'?goto=oidplus:system';
            header('Location:'.$target );
            die('<a href="'.$target.'">Got to '.$target.'</a>');
        }
        // mapping existiert, aber ra_id NULL → wir versuchen über email zu binden
    }

    // 2) Kein (vollständiges) Mapping: wir brauchen eine RA (email-basiert in OIDplus)
    if (empty($email)) {
        throw new OIDplusException('Email address missing from provider; cannot create or locate RA.');
    }

    $ra = new OIDplusRA($email);
    if (!$ra->existing()) {
        $ra->register_ra(null);
        // Namen setzen (optional)
        $personal_name = $name ?: $email;
        $ra_name = $name ?: ($provider.' user');
        OIDplus::db()->query("UPDATE ###ra SET ra_name = ?, personal_name = ? WHERE email = ?", [$ra_name, $personal_name, $email]);
        OIDplus::logger()->log("V2:[INFO]RA(%1)", "RA '%1' was created because of successful ".$provider." OAuth2 login", $email);
    }

    // 3) Mapping speichern/aktualisieren
    $raRow = OIDplus::db()->query("SELECT ra_id FROM ###ra WHERE email = ?", [$email])->fetch_array();
    $ra_id = (int)$raRow['ra_id'];
    // UPSERT
    $ip = OIDplus::getClientIpAddress();
    // Versuche Insert, bei Unique-Kollision (external_id+provider+ra_id) passiert nichts
    OIDplus::db()->query(
        "INSERT INTO ###oidplus_oauth_users (ra_id, external_id, provider, ip) VALUES (?, ?, ?, ?)",
        [$ra_id, $externalId, $provider, $ip]
    );

    // 4) Login
    OIDplus::authUtils()->raLoginEx($email, 'OAuth:'.$provider);
    OIDplus::db()->query("UPDATE ###ra SET last_login = ".OIDplus::db()->sqlDate()." WHERE email = ?", [$email]);
    OIDplus::logger()->log("V2:[OK]RA(%1)", "RA '%1' logged in via ".$provider." OAuth2", $email);
    OIDplus::invoke_shutdown();	 
	$stateKey = 'oauth2state_'.$provider;	 
	unset($_SESSION[$stateKey]);
	$target = OIDplus::webpath(null, OIDplus::PATH_ABSOLUTE);//.'?goto=oidplus:system';
    header('Location:'.$target);
    die('<a href="'.$target.'">Got to '.$target.'</a>');
}

// ---- Controller ----

$provider = isset($_GET['provider']) ? strtolower(trim($_GET['provider'])) : 'webfan';
$client   = oidplus_make_provider($provider);

if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
$stateKey = 'oauth2state_'.$provider;

if (!isset($_GET['code'])) {
    // Flow starten
    oidplus_oauth_begin($client, $provider);
	return;
}

// CSRF State prüfen
if (empty($_GET['state']) || empty($_SESSION[$stateKey]) || $_GET['state'] !== $_SESSION[$stateKey]) {
    unset($_SESSION[$stateKey]);
    throw new OIDplusException('Invalid state');
}

// Token holen + Resource Owner laden
try {
    $accessToken = $client->getAccessToken('authorization_code', ['code' => $_GET['code']]);
    $owner       = $client->getResourceOwner($accessToken);
} catch (\Throwable $e) {
    throw new OIDplusException('OAuth error: '.$e->getMessage());
}

// Normalisieren: externalId, name, email extrahieren
$externalId = null;
$name = null;
$email = null;

switch ($provider) {
    case 'webfan':
        $arr = method_exists($owner, 'toArray') ? $owner->toArray() : (array)$owner;
        // Nextcloud OCS format
        $data = $arr['ocs']['data'] ?? [];
        $externalId = (string)($data['id'] ?? '');
        $name = (string)($data['display-name'] ?? '');
        $email = (string)($data['email'] ?? '');
        break;

    case 'google':
        $externalId = (string)$owner->getId();
        $name = (string)($owner->getName() ?? '');
        $email = (string)($owner->getEmail() ?? '');
        break;

    case 'github':
        $externalId = (string)$owner->getId();
        $name = (string)($owner->getNickname() ?? '');
        $email = (string)($owner->getEmail() ?? '');
        break;

    default:
        throw new OIDplusException('Unknown provider: '.$provider);
}

if ($externalId === '') {
    throw new OIDplusException('Provider did not return an external ID.');
}

// ID-basiertes Mapping + Login
oidplus_oauth_link_and_login($provider, $externalId, $email, $name);
return;