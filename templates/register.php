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

$this->data['header'] = $this->t('{authwebauthn:webauthn:header_'.$purpose.'}');

$this->includeAtTemplateBase('includes/header.php');
$target = htmlspecialchars($this->data['target']);
$userid = htmlspecialchars($this->data['userid']);
$challenge = htmlspecialchars($this->data['challenge']);
$ids = $this->data['ids'];
$stateid = $this->data['params']['StateId'];
$validate = \SimpleSAML\Module::getModuleURL('authwebauthn/validate.php', ['StateId' => $stateid]);

?>
<h1><?php echo "Register - " . $this->data['header']; ?></h1>

    <p><?php echo $this->t('{authwebauthn:webauthn:insert_token}') . " <b>$userid</b>"; ?></p>

    <form id='i<?php echo $purpose; ?>form' action='<?php echo $target; ?>' method='POST'>
    <input type='hidden' id='ichallenge' name='ichallenge' value='<?php echo $challenge?>'>
    <input type='hidden' id='i<?php echo $purpose?>' name='i<?php echo $purpose?>' value='empty'>
<?php
    // Embed hidden fields...
    foreach ($this->data['params'] as $name => $value) {
        echo '<input type="hidden" name="'.htmlspecialchars($name).'" value="'.htmlspecialchars($value).'" />' . "\n";
    }
?>
    <table>
<?php
    // Show previous keys
    $delete = "<img height='16em' src='".  \SimpleSAML\Module::getModuleURL('authwebauthn/resources/delete.png') . "'>";
    foreach ($ids as $key_id) {
        $del_url = \SimpleSAML\Module::getModuleURL('authwebauthn/register.php', ['StateId' => $stateid, 'd' => $key_id]);
        echo "<tr><td>" . htmlspecialchars($key_id) . "</td><td><a href='$del_url'>$delete</td></tr>\n";
    }
?>
    <tr id='submit'><td><input type='text' id='id' name='id' placeholder='device ID'></td>
    <td><input type='submit' value='register'></td></tr>
    </table>
    </form>

    <p>
    <div class='cerror'></div>
    <a href='#' id='register'>register new device</a>
    <div id='ido'><?php echo "<a href='$validate'>" . $this->t('{authwebauthn:webauthn:validate}') . "</a>"?></div>
    </p>
<script>

$(function(){
    purpose = "<?php echo $purpose?>";
    challenge = $('#ichallenge').val();
    $('#submit').hide();

    $('#register').click(function() {
        $('.cerror').empty().hide();
        $('#ido').hide();

        /* activate the key and get the response */
        webauthnRegister(challenge, function(success, info) {
            if (success) {
                $('#iregister').val(info);
                $('#submit').show();
//                 $('#iregisterform').submit();
            } else {
                $('.cerror').text(info).show();
                $('#submit').hide();
                $('#ido').show();
            }
        });
        return false;
    });

    if (purpose == 'validate') {
        $('.cerror').empty().hide();
        $('#register').hide();

        /* validate the key and get the response */
        webauthnValidate(challenge, function(success, info) {
            if (success) {
                $('#ivalidate').val(info);
                $('#ivalidateform').submit();
            } else {
                $('.cerror').text(info).show();
//                 $('#ido').hide();
            }
        });
    };
});


</script>

<?php
$this->includeAtTemplateBase('includes/footer.php');
