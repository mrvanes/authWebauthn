<?php
/**
 * Template for Webauthn token registration and validation
 *
 * Parameters:
 * - 'target': Target URL.
 * - 'params': Parameters which should be included in the request.
 *
 * @package SimpleSAMLphp
 */
$userid = $this-data['userid'];
$this->includeAtTemplateBase('includes/header.php');
?>
<h1>Error </h1>
<p><?php echo $this->t('{authWebauthn:error:no_registration}') . " <b>" . $this->data['userid']; ?></b></p>


<?php
$this->includeAtTemplateBase('includes/footer.php');
