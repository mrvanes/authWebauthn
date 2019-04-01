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
$uid = htmlspecialchars($this->data['uid']);
$contButton = htmlspecialchars($this->t('{authWebauthn:webauthn:continue}'));

?>
<style>
.cerror,.cdone {
/*     border: 1px solid black; */
}
#idokey {
    display: none;
}
</style>
<h1><?php echo $this->data['header']; ?></h1>

    <p><?php echo $this->t('{authWebauthn:webauthn:insert_token}') . " $uid"; ?></p>

    <p><div class='cerror'></div><div class='cdone'></div></p>
    <form id='i<?php echo $purpose; ?>form' action='<?php echo $target; ?>' method='POST'>
<?php
// Embed hidden fields...
foreach ($this->data['params'] as $name => $value) {
    echo '<input type="hidden" name="'.htmlspecialchars($name).'" value="'.htmlspecialchars($value).'" />';
}
?>
    <input type='hidden' name='<?php echo $purpose?>username' value='<?php echo $uid?>' readonly>
    <input type='submit' value='<?php echo $purpose?>'>
    </form>
    <div id='idokey'>
    Do your thing: press button on key, swipe fingerprint or whatever
    </div>

    <form id='icontinueform' method='POST'>
    <input type="hidden" name="continue" id="icontinue" value="false">
    </form>

<script>

  $(function(){

    $('#iregisterform').submit(function(ev){
        var self = $(this);
        ev.preventDefault();
        $('.cerror').empty().hide();

        $.ajax({method: 'POST',
                data: {registerusername: self.find('[name=registerusername]').val()},
                dataType: 'json',
                success: function(j){
                    $('#iregisterform,#idokey').toggle();
                    /* activate the key and get the response */
                    webauthnRegister(j.challenge, function(success, info){
                        if (success) {
                            $.ajax({method: 'POST',
                                    data: {register: info},
                                    dataType: 'json',
                                    success: function(j){
                                        $('#iregisterform,#idokey').toggle();
                                        $('.cdone').text("registration completed successfully").show();
//                                         setTimeout(function(){ $('.cdone').hide(300); }, 2000);
                                        $('#icontinue').val('true');
                                        $('#icontinueform').submit();
                                    },
                                    error: function(xhr, status, error){
                                        $('.cerror').text("registration failed: "+error+": "+xhr.responseText).show();
                                    }
                                    });
                        } else {
                            $('#iregisterform').show();
                            $('#idokey').hide();
                            $('.cerror').text(info).show();
                        }
                    });
                },

                error: function(xhr, status, error){
                    $('#iregisterform').show();
                    $('#idokey').hide();
                    $('.cerror').text("couldn't initiate registration: "+error+": "+xhr.responseText).show();
                }
                });
    });

    $('#ivalidateform').submit(function(ev){
        var self = $(this);
        ev.preventDefault();
        $('.cerror').empty().hide();

        $.ajax({method: 'POST',
                data: {loginusername: self.find('[name=validateusername]').val()},
                dataType: 'json',
                success: function(j){
                    $('#ivalidateform,#idokey').toggle();
                    /* activate the key and get the response */
                    webauthnAuthenticate(j.challenge, function(success, info){
                        if (success) {
                            $.ajax({method: 'POST',
                                    data: {login: info},
                                    dataType: 'json',
                                    success: function(j){
                                        $('#ivalidateform,#idokey').toggle();
                                        $('.cdone').text("login completed successfully").show();
//                                         setTimeout(function(){ $('.cdone').hide(300); }, 2000);
                                        $('#continue').val('true');
                                        $('#icontinueform').submit();

                                    },
                                    error: function(xhr, status, error){
                                        $('.cerror').text("login failed: "+error+": "+xhr.responseText).show();
                                    }
                                    });
                        } else {
                            $('#ivalidateform').show();
                            $('#idokey').hide();
                            $('.cerror').text(info).show();
                            $('#icontinueform').submit();
                        }
                    });
                },

                error: function(xhr, status, error){
                    $('#ivalidateform').show();
                    $('#idokey').hide();
                    $('.cerror').text("couldn't initiate login: "+error+": "+xhr.responseText).show();
                }
                });
    });

});
</script>

<?php
$this->includeAtTemplateBase('includes/footer.php');
