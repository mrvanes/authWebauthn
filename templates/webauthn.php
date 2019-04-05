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

$purpose = htmlspecialchars($this->data['purpose']);

$this->data['header'] = $this->t('{authWebauthn:webauthn:header_'.$purpose.'}');

$this->includeAtTemplateBase('includes/header.php');
$target = htmlspecialchars($this->data['target']);
$userid = htmlspecialchars($this->data['userid']);
$challenge = htmlspecialchars($this->data['challenge']);

?>
<h1><?php echo $this->data['header']; ?></h1>

    <p><?php echo $this->t('{authWebauthn:webauthn:insert_token}') . " <b>$userid</b>"; ?></p>

    <p><div class='cerror'></div></p>
    <form id='i<?php echo $purpose; ?>form' action='<?php echo $target; ?>' method='POST'>
<?php
// Embed hidden fields...
foreach ($this->data['params'] as $name => $value) {
    echo '<input type="hidden" name="'.htmlspecialchars($name).'" value="'.htmlspecialchars($value).'" />' . "\n";
}
?>
    <input type='hidden' id='ichallenge' name='ichallenge' value='<?php echo $challenge?>'>
    <input type='hidden' id='i<?php echo $purpose?>' name='i<?php echo $purpose?>' value='test'>
    </form>
    <div id='ido'>
    <p><?php echo $this->t('{authWebauthn:webauthn:take_action}')?></p>
    </div>

<script>

$(function(){
    purpose = "<?php echo $purpose?>";
    challenge = $('#ichallenge').val();

    if (purpose == 'register') {
        $('.cerror').empty().hide();

        /* activate the key and get the response */
        webauthnRegister(challenge, function(success, info) {
            if (success) {
                $('#iregister').val(info);
                $('#iregisterform').submit();
            } else {
                $('.cerror').text(info).show();
                $('#ido').hide();
            }
        });

    };

    if (purpose == 'validate') {
        $('.cerror').empty().hide();

        /* validate the key and get the response */
        webauthnValidate(challenge, function(success, info) {
            if (success) {
                $('#ivalidate').val(info);
                $('#ivalidateform').submit();
            } else {
                $('.cerror').text(info).show();
                $('#ido').hide();
            }
        });
    };
});
</script>

<?php
$this->includeAtTemplateBase('includes/footer.php');
