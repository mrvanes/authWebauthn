<?php

/**
 * Show a warning to an user about the SP requesting SSO a short time after
 * doing it previously.
 *
 * @package SimpleSAMLphp
 */

require_once dirname(dirname(__FILE__)).'/lib/webauthn.php';

if (!array_key_exists('StateId', $_REQUEST)) {
    throw new \SimpleSAML\Error\BadRequest('Missing required StateId query parameter.');
}

$stateid = $_REQUEST['StateId'];
$state = \SimpleSAML\Auth\State::loadState($stateid, 'authwebauthn:webauthn');

// $session = \SimpleSAML\Session::getSessionFromRequest();
$webauthn = new \SimpleSAML\Module\authwebauthn\WebAuthn($_SERVER['HTTP_HOST']);

$purpose = $state['Purpose'];
$userID = $state['userID'];
$challenge = '';

global $db;
$db = new \SQLite3($state['db']);
if (!$db) {
    \SimpleSAML\Logger::info("no sqlite db: " . $state['db']);
}

function getuser($username) {
    global $db;
    $query = "SELECT webauthnkeys from keys where user=:username";
    $st = $db->prepare($query);
    $st->bindValue(':username', $username);
    $row = $st->execute()->fetchArray();
    $keys = json_decode($row[0]);
    if (!$keys) return false;
    return $keys;
}

function putuser($username, $keys) {
    global $db;
    $query  = "INSERT OR REPLACE INTO keys (user, webauthnkeys) VALUES (:username, :keys)";
    $st = $db->prepare($query);
    $st->bindValue(':username', $username);
    $st->bindValue(':keys', $keys);
    return $st->execute();
}

// Handle POST
if (!empty($_POST)) {
    // This is the registration response
    if ($purpose == 'register') {
        $response = $_POST['iregister'];
        $keys = getuser($userID);
        if (!$keys) $keys = $webauthn->cancel();
        $keys = $webauthn->register($response, $keys);
        if (putuser($userID, json_encode($keys))) {
            \SimpleSAML\Module\authwebauthn\Auth\Process\WebAuthn::success($state);
        }

    // This is the validation response
    } elseif ($purpose == 'validate') {
        $response = $_POST['ivalidate'];
        $keys = getuser($userID);
        if ($webauthn->authenticate($response, $keys)) {
            \SimpleSAML\Module\authwebauthn\Auth\Process\WebAuthn::success($state);
        } else {
            \SimpleSAML\Module\authwebauthn\Auth\Process\WebAuthn::fail($state);
        }
    }
}

// Prepare Challenge
if ($purpose == 'register') {
    $id = $userID; // $userID is displayName, $id is unique ID
    $challenge = $webauthn->prepare_challenge_for_registration($userID, $id);
} else {
    // Look for registered user keys
    $keys = getuser($userID);
    if (!$keys) {
        if ($purpose == 'validate') {
            // Validation without key is impossible
            $url = \SimpleSAML\Module::getModuleURL('authwebauthn/error.php');
            \SimpleSAML\Utils\HTTP::submitPOSTData($url, ['StateId' => $stateid]);
        } else {
            // This is !register & !keys & !validate (must be fallback, so register)
            $state['Purpose'] = 'register';
            $id = \SimpleSAML\Auth\State::saveState($state, 'authwebauthn:webauthn');
            $url = \SimpleSAML\Module::getModuleURL('authwebauthn/webauthn.php');
            \SimpleSAML\Utils\HTTP::redirectTrustedURL($url, ['StateId' => $id]);
        }
    }
    // We have keys, and want to validate or fallback to validate
    $challenge = $webauthn->prepare_for_login($keys);
    $purpose = 'validate';
    $state['Purpose'] = 'validate';
    $stateid = \SimpleSAML\Auth\State::saveState($state, 'authwebauthn:webauthn');
}

$globalConfig = \SimpleSAML\Configuration::getInstance();
$t = new \SimpleSAML\XHTML\Template($globalConfig, 'authwebauthn:webauthn.php');
$t->data['target'] = \SimpleSAML\Module::getModuleURL('authwebauthn/webauthn.php');
$t->data['pageid'] = 'WebAuthn';
$t->data['params'] = ['StateId' => $stateid];
$t->data['purpose'] = $purpose;
$t->data['userid'] = $userID;
$t->data['challenge'] = $challenge;
$t->show();
