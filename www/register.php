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

$webauthn = new \SimpleSAML\Module\authwebauthn\WebAuthn($_SERVER['HTTP_HOST']);

$purpose = $state['Purpose'];
$userID = $state['userID'];
$validated = (isset($state['Validated']) && $state['Validated'])?true:false;
$challenge = '';

if ($validated) {
    $state['Purpose'] = 'register';
    $purpose = 'register';
}

global $db;
$db = new \SQLite3($state['db']);
if (!$db) {
    \SimpleSAML\Logger::info("no sqlite db: " . $state['db']);
}

function randstring($length){
    $token = "";
    $codeAlphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    $codeAlphabet.= "abcdefghijklmnopqrstuvwxyz";
    $codeAlphabet.= "0123456789";
    for($i=0;$i<$length;$i++){
        $token .= $codeAlphabet[rand(0,strlen($codeAlphabet))];
    }
    return $token;
}

function getuser($username) {
    global $db;
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

function getkeys($username) {
    global $db;
    $keys = [];
    $query = "SELECT id from keys where user=:username";
    $st = $db->prepare($query);
    $st->bindValue(':username', $username);
    $r = $st->execute();
    while ($row = $r->fetchArray(SQLITE3_ASSOC)) {
        $keys[] = $row['id'];
    }
    return $keys;
}

function delkey($username, $id) {
    global $db;
    $query  = "DELETE FROM keys WHERE user=:username AND id=:id";
    $st = $db->prepare($query);
    $st->bindValue(':username', $username);
    $st->bindValue(':id', $id);
    return $st->execute();
}

function putuser($username, $id, $key) {
    global $db;
    $query  = "INSERT OR REPLACE INTO keys (user, id, key) VALUES (:username, :id, :key)";
    $st = $db->prepare($query);
    $st->bindValue(':username', $username);
    $st->bindValue(':id', $id);
    $st->bindValue(':key', json_encode($key));
    return $st->execute();
}

// Handle POST
if (!empty($_POST)) {
    // This is the validation response
    if ($purpose == 'validate') {
        $response = $_POST['ivalidate'];
        $keys = getuser($userID);
        if ($response != 'empty' && $webauthn->authenticate($response, $keys)) {
            $state['Validated'] = true;
            $state['Purpose'] = 'register';
            $purpose = 'register';
        }
    } else {
        // This is the registration response
        $response = $_POST['iregister'];
        $id = $_POST['id'];
        $key = $webauthn->register($response);
        if ($id && !putuser($userID, $id, $key)) {
            \SimpleSAML\Module\authwebauthn\Auth\Process\WebAuthn::fail($state);
        }
    }
}

// Handle GET
if ($validated && !empty($_GET)) {
    if (isset($_GET['d'])) {
        $id = $_GET['d'];
        delkey($userID, $id);
    }
}

// Prepare Challenge
$keys = getuser($userID);
if ($purpose == 'validate') {
    $challenge = $webauthn->prepare_for_login($keys);
} else {
    $id = $userID; // $userID is displayName, $id is unique ID
    $challenge = $webauthn->prepare_challenge_for_registration($userID, $id, $keys);
}

\SimpleSAML\Auth\State::saveState($state, 'authwebauthn:webauthn');

$globalConfig = \SimpleSAML\Configuration::getInstance();
$t = new \SimpleSAML\XHTML\Template($globalConfig, 'authwebauthn:register.php');
$t->data['target'] = \SimpleSAML\Module::getModuleURL('authwebauthn/register.php');
$t->data['pageid'] = 'WebAuthn';
$t->data['params'] = ['StateId' => $stateid];
$t->data['purpose'] = $purpose;
$t->data['userid'] = $userID;
$t->data['challenge'] = $challenge;
$t->data['ids'] = getkeys($userID);
$t->show();
