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
    $keys = [];
    $query = "SELECT key from keys where user=:username";
    $st = $db->prepare($query);
    $st->bindValue(':username', $username);
    $r = $st->execute();
    while ($row = $r->fetchArray(SQLITE3_ASSOC)) {
        $keys[] = json_decode($row['key']);
    }
    if (!$keys) return false;
    return json_encode($keys);
}

// Look for registered user keys
$keys = getuser($userID);

// Handle POST
if (!empty($_POST)) {
    // This is the validation response
    if ($purpose == 'validate') {
        $response = $_POST['ivalidate'];
        $keys = getuser($userID);
        if ($response != 'empty' && $webauthn->authenticate($response, $keys)) {
            \SimpleSAML\Module\authwebauthn\Auth\Process\WebAuthn::success($state);
        } else {
            if ($state['flow'] == 'fallback') {
                if ($keys) {
                    $state['Purpose'] = 'validate';
                } else {
                    $state['Purpose'] = 'register';
                }
                $id = \SimpleSAML\Auth\State::saveState($state, 'authwebauthn:webauthn');
                $url = \SimpleSAML\Module::getModuleURL('authwebauthn/register.php');
                \SimpleSAML\Utils\HTTP::redirectTrustedURL($url, ['StateId' => $id]);
            } else {
                \SimpleSAML\Module\authwebauthn\Auth\Process\WebAuthn::fail($state);
            }
        }
    }
}

if (!$keys) {
    if ($purpose == 'validate') {
        // Validation without key is impossible
        \SimpleSAML\Module\authwebauthn\Auth\Process\WebAuthn::fail($state);
    } else {
        // This is !register & !keys & !validate (must be fallback, so register)
        $state['Purpose'] = 'register';
        $id = \SimpleSAML\Auth\State::saveState($state, 'authwebauthn:webauthn');
        $url = \SimpleSAML\Module::getModuleURL('authwebauthn/register.php');
        \SimpleSAML\Utils\HTTP::redirectTrustedURL($url, ['StateId' => $id]);
    }
}
// We have keys, and want to validate or fallback to validate
$challenge = $webauthn->prepare_for_login($keys);
$state['flow'] = $purpose;
$state['Purpose'] = 'validate';
$purpose = 'validate';
$stateid = \SimpleSAML\Auth\State::saveState($state, 'authwebauthn:webauthn');

$globalConfig = \SimpleSAML\Configuration::getInstance();
$t = new \SimpleSAML\XHTML\Template($globalConfig, 'authwebauthn:validate.php');
$t->data['target'] = \SimpleSAML\Module::getModuleURL('authwebauthn/validate.php');
$t->data['pageid'] = 'WebAuthn';
$t->data['params'] = ['StateId' => $stateid];
$t->data['purpose'] = $purpose;
$t->data['userid'] = $userID;
$t->data['challenge'] = $challenge;
$t->show();
