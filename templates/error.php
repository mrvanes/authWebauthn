<?php
/**
 * Template for Webauthn error page
 *
 * Parameters:
 * - 'userid': User ID
 * - 'params': Parameters which should be included in the request.
 *
 * @package SimpleSAMLphp
 */

$userid = htmlspecialchars($this->data['userid']);
$this->includeAtTemplateBase('includes/header.php');
?>
<h1>Error </h1>
<p><?php echo $this->t('{authwebauthn:error:no_registration}') . " <b>$userid</b>"?></p>


<?php
$this->includeAtTemplateBase('includes/footer.php');
