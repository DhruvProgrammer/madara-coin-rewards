(function ($) {

	"use strict";

	jQuery(window).on('load', function (e) {
    });
    
    var madara_checkPasswordStrength = function(){
        var new_pass = $('#new-password-input').val();
        var new_pass_retype = $('#comfirm-password-input').val();

        // Get the password strength
        var strength = wp.passwordStrength.meter( new_pass, wp.passwordStrength.userInputDisallowedList(), new_pass_retype );
        
        var strength_result = $('#password-strength');
        
        // Add the strength meter results
        switch ( strength ) {
            case 2:
                strength_result.addClass( 'bad' ).html( pwsL10n.bad );
                break;

            case 3:
                strength_result.addClass( 'good' ).html( pwsL10n.good );
                break;

            case 4:
                strength_result.addClass( 'strong' ).html( pwsL10n.strong );
                break;

            case 5:
                strength_result.addClass( 'short' ).html( pwsL10n.mismatch );
                break;

            default:
                strength_result.addClass( 'short' ).html( pwsL10n.short );
        }
        
        return strength;
    }
    
    jQuery(document).ready(function () {
        $( 'body' ).on( 'keyup', '#new-password-input, #comfirm-password-input',
            function( event ) {
                madara_checkPasswordStrength();
            }
        );
        
        $('#form-account-settings').on('submit', function(evt){
            evt.preventDefault(); //this will prevent the default submit
            var pass_strength = false;
            var new_pass = $('#new-password-input').val();
            if(new_pass != ''){
                var strength = madara_checkPasswordStrength();
                
                if($(this).data('force-strong-password')){
                    if(strength >= 4){
                        pass_strength = true;
                    } else {
                        return false;
                    }
                } else {
                    if(strength < 4){
                        $('#checkbox-weak-password').show();
                        
                        if($('#agree-weak-password:checked').length > 0){
                            pass_strength = true;
                        }
                    }
                }
            }
            
            if($('input[name=user-new-name]').val() != '' || $('input[name=user-new-email]').val() != '' || pass_strength){
                $(this).unbind('submit').submit();
            }
        });
        
    });
    
})(jQuery);
    