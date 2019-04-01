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
$id = $_REQUEST['StateId'];
$state = \SimpleSAML\Auth\State::loadState($id, 'authWebauthn:webauthn');

if (array_key_exists('continue', $_REQUEST)) {
    if ($_REQUEST['continue'] == 'true') {
        \SimpleSAML\Module\authWebauthn\Auth\Process\WebAuthn::success($state);
    } else {
         $state['Attributes'] = [];
        \SimpleSAML\Module\authWebauthn\Auth\Process\WebAuthn::fail($state);
    }
}

require_once dirname(dirname(__FILE__)).'/lib/webauthn.php';
$session = \SimpleSAML\Session::getSessionFromRequest();

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

function oops($s){
  http_response_code(400);
  echo "{$s}\n";
  exit;
}

function userpath($username){
  $username = str_replace('.', '%2E', $username);
  return sprintf('%s/%s.json', USER_DATABASE, urlencode($username));
}

function getuser($username){
  $user = @file_get_contents(userpath($username));
  if (empty($user)) { oops('user not found'); }
  $user = json_decode($user);
  if (empty($user)) { oops('user not json decoded'); }
  return $user;
}

/* A post is an ajax request, otherwise display the page */
if (! empty($_POST)) {

  try {

    $webauthn = new \SimpleSAML\Module\authWebauthn\Auth\Source\WebAuthn($_SERVER['HTTP_HOST']);

    switch(TRUE){

    case isset($_POST['registerusername']):
      /* initiate the registration */
      $username = $_POST['registerusername'];

      $userid = md5(time() . '-'. rand(1,1000000000));

      if (!file_exists(userpath($username))) {
//         oops("user '{$username}' already exists");
      /* Create a new user in the database. In principle, you can store more
         than one key in the user's webauthnkeys,
         but you'd probably do that from a user profile page rather than initial
         registration. The procedure is the same, just don't cancel existing
         keys like this.*/
        file_put_contents(userpath($username), json_encode(['name'=> $username,
                                                          'id'=> $userid,
                                                          'webauthnkeys' => $webauthn->cancel()]));
      }

      $_SESSION['username'] = $username;
      $j = ['challenge' => $webauthn->prepare_challenge_for_registration($username, $userid)];
      break;

    case isset($_POST['register']):
      /* complete the registration */
      if (empty($_SESSION['username'])) { oops('username not set'); }
      $user = getuser($_SESSION['username']);

      /* The heart of the matter */
      $user->webauthnkeys = $webauthn->register($_POST['register'], $user->webauthnkeys);

      /* Save the result to enable a challenge to be raised agains this
         newly created key in order to log in */
      file_put_contents(userpath($user->name), json_encode($user));
      $j = 'ok';

      break;

    case isset($_POST['loginusername']):
      /* initiate the login */
      $username = $_POST['loginusername'];
      $user = getuser($username);
      $_SESSION['loginname'] = $user->name;

      /* note: that will emit an error if username does not exist. That's not
         good practice for a live system, as you don't want to have a way for
         people to interrogate your user database for existence */

      $j['challenge'] = $webauthn->prepare_for_login($user->webauthnkeys);
      break;

    case isset($_POST['login']):
      /* authenticate the login */
      if (empty($_SESSION['loginname'])) { oops('username not set'); }
      $user = getuser($_SESSION['loginname']);

      if (! $webauthn->authenticate($_POST['login'], $user->webauthnkeys)) {
        http_response_code(401);
        echo 'failed to authenticate with that key';
        exit;
      }
      $j = 'ok';

      break;

    default:
      http_response_code(400);
      echo "unrecognized POST\n";
      break;
    }

  } catch(Exception $ex) {
    oops($ex->getMessage());
  }

  header('Content-type: application/json');
  echo json_encode($j);
  exit;
}









$globalConfig = \SimpleSAML\Configuration::getInstance();
$t = new \SimpleSAML\XHTML\Template($globalConfig, 'authWebauthn:webauthn.php');
$t->data['target'] = \SimpleSAML\Module::getModuleURL('authWebauthn/webauthn.php');
$t->data['params'] = ['StateId' => $id];
$t->data['purpose'] = $state['Purpose'];
$t->data['uid'] = $state['userID'];
$t->data['pageid'] = 'WebAuthn';
$t->show();
