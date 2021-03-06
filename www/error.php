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
$state = \SimpleSAML\Auth\State::loadState($stateid, 'authwebauthn:webauthn');

$globalConfig = \SimpleSAML\Configuration::getInstance();
$t = new \SimpleSAML\XHTML\Template($globalConfig, 'authwebauthn:error.php');
$t->data['userid'] = $state['userID'];
$t->show();
