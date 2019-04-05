<?php

/**
 * Show a warning to an user about the SP requesting SSO a short time after
 * doing it previously.
 *
 * @package SimpleSAMLphp
 */


if (!array_key_exists('StateId', $_REQUEST)) {
    throw new \SimpleSAML\Error\BadRequest('Missing required StateId query parameter.');
}
$stateid = $_REQUEST['StateId'];
$state = \SimpleSAML\Auth\State::loadState($stateid, 'authWebauthn:webauthn');

require_once dirname(dirname(__FILE__)).'/lib/webauthn.php';
$session = \SimpleSAML\Session::getSessionFromRequest();
$webauthn = new \SimpleSAML\Module\authWebauthn\Auth\Source\WebAuthn($_SERVER['HTTP_HOST']);

/* In this example, the user database is simply a directory of json files
  named by their username (urlencoded so there are no weird characters
  in the file names). For simplicity, it's in the HTML tree so someone
  could look at it - you really, really don't want to do this for a
  live system */
define('USER_DATABASE', $_SERVER['DOCUMENT_ROOT'].'/users');
if (! file_exists(USER_DATABASE)) {
  if (! @mkdir(USER_DATABASE)) {
    \SimpleSAML\Logger::info(sprintf('Cannot create user database directory - is the html directory writable by the web server? If not: "mkdir %s; chmod 777 %s"', USER_DATABASE, USER_DATABASE));
    throw new \Exception(sprintf("cannot create %s - see error log", USER_DATABASE));
  }
}

function userpath($username) {
  $username = str_replace('.', '%2E', $username);
  return sprintf('%s/%s.json', USER_DATABASE, urlencode($username));
}

function getuser($username) {
  $user = @file_get_contents(userpath($username));
  if (empty($user)) return false;
  $user = json_decode($user);
  if (empty($user)) return false;
  return $user;
}

function createuser($username, $key) {
    if (!file_exists(userpath($username))) {
        return file_put_contents(userpath($username), $key);
    }
    return true;
}

function putuser($username, $key) {
    return file_put_contents(userpath($username), $key);
}

$purpose = $state['Purpose'];
$userID = $state['userID'];
$challenge = '';

// Handle POST
if (!empty($_POST)) {
    // This is the registration answer
    if ($purpose == 'register') {
        $response = $_POST['iregister'];
        $keys = getuser($userID);
        $keys->webauthnkeys = $webauthn->register($response, $keys->webauthnkeys);
        if (putuser($userID, json_encode($keys))) {
            \SimpleSAML\Module\authWebauthn\Auth\Process\WebAuthn::success($state);
        }

    } elseif ($purpose == 'validate') {
        $response = $_POST['ivalidate'];
        $keys = getuser($userID);
        if ($webauthn->authenticate($response, $keys->webauthnkeys)) {
            \SimpleSAML\Module\authWebauthn\Auth\Process\WebAuthn::success($state);
        } else {
            \SimpleSAML\Module\authWebauthn\Auth\Process\WebAuthn::fail($state);
        }
    }
}

// Prepare Challenge
if ($purpose == 'register') {
    $id = md5(time() . '-'. rand(1,1000000000));
    $key = ['name'=> $userID,
            'id'=> $id,
            'webauthnkeys' => $webauthn->cancel()];
    if (createuser($userID, json_encode($key))) {
        $challenge = $webauthn->prepare_challenge_for_registration($userID, $id);
    }
} elseif ($purpose == 'validate') {
    $user = getuser($userID);
    if ($user) {
        $challenge = $webauthn->prepare_for_login($user->webauthnkeys);
    } else {
        $url = \SimpleSAML\Module::getModuleURL('authWebauthn/error.php');
        \SimpleSAML\Utils\HTTP::submitPOSTData($url, ['StateId' => $stateid]);
    }
}

$globalConfig = \SimpleSAML\Configuration::getInstance();
$t = new \SimpleSAML\XHTML\Template($globalConfig, 'authWebauthn:webauthn.php');
$t->data['target'] = \SimpleSAML\Module::getModuleURL('authWebauthn/webauthn.php');
$t->data['pageid'] = 'WebAuthn';
$t->data['params'] = ['StateId' => $stateid];
$t->data['purpose'] = $purpose;
$t->data['userid'] = $userID;
$t->data['challenge'] = $challenge;
$t->show();
