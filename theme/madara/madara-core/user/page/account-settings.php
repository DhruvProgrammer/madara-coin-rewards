<?php
    /**
	 * The Template for Account Settings section in User Settings page
	 *
	 * This template can be overridden by copying it to your-child-theme/madara-core/user/account-settings.php
	 *
	 * HOWEVER, on occasion Madara will need to update template files and you
	 * (the theme developer) will need to copy the new files to your theme to
	 * maintain compatibility. We try to do this as little as possible, but it does
	 * happen. When this occurs the version of the template file will be bumped and
	 * we will list any important changes on our theme release logs.
	 * @package Madara
	 * @version 1.7.2.2
	 */
	 
	 use App\Madara;
    
	if ( ! is_user_logged_in() ) {
		return;
	}
    
	
	$user    = wp_get_current_user();
	if($user && current_user_can('edit_user', $user->ID)) {
        $user_id = $user->ID;
    } else {
        return;
    }

    $account_resp = madara_update_user_settings();
    // get data again
    $user    = wp_get_current_user();
    
    $user_settings_weak_password = Madara::getOption('user_settings_weak_password', 'on') == 'on' ? true : false;

?>
<form method="post" id="form-account-settings" data-force-strong-password="<?php echo esc_attr($user_settings_weak_password ? 1 : 0);?>">

	<?php if( $account_resp !== null ){ ?>
		<?php if ( ! is_wp_error( $account_resp ) ) { ?>
	        <div class="alert alert-success alert-dismissable">
	            <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
	            <strong><?php esc_html_e( 'Succeeded!', 'madara' ); ?></strong> <?php esc_html_e( 'Update successfully', 'madara' ); ?>
	        </div>
		<?php } else { ?>
	        <div class="alert alert-danger alert-dismissable">
	            <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
	            <strong><?php esc_html_e( 'Failed!', 'madara' ); ?></strong> <?php echo esc_html( $account_resp->get_error_message() ); ?>
	        </div>
		<?php } ?>
	<?php } ?>

	<?php wp_nonce_field( '_wp_manga_save_user_settings' ); ?>
    <div class="tab-group-item">
        <div class="tab-item">
            <div class="choose-avatar">
				<div class="loading-overlay">
					<div class="loading-icon">
						<i class="fas fa-spinner fa-spin"></i>
					</div>
				</div>
				<div class="c-user-avatar">
					<?php echo get_avatar( $user_id, 195 ); ?>
				</div>
            </div>
			<?php 
			global $wp_manga_setting;
			
			$user_can_upload_avatar = $wp_manga_setting->get_manga_option('user_can_upload_avatar', '1');
			if($user_can_upload_avatar == '1'){?>
            <div class="form form-choose-avatar">
                <div class="select-flie">
                    <!--Update Avatar -->
                    <form action="#">
						<?php esc_html_e( 'Only for .jpg .png or .gif file', 'madara' ); ?>
                        <label class="select-avata">
							<input type="file" name="wp-manga-user-avatar">
							<span class="file-name"></span>
						</label>
							
                        <input type="submit" value="<?php esc_html_e( 'Upload', 'madara' ); ?>" name="wp-manga-upload-avatar" id="wp-manga-upload-avatar">
                    </form>

                </div>
            </div>
			<?php } ?>
        </div>
        <div class="tab-item">

            <div class="settings-title">
                <h3>
					<?php esc_html_e( 'Change Your Display name', 'madara' ); ?>
                </h3>
            </div>
            <div class="form-group row">
                <label for="name-input" class="col-md-3"><?php esc_html_e( 'Current Display name', 'madara' ); ?></label>
                <div class="col-md-9">
					<?php if ( isset( $user->data->display_name ) ) { ?>
                        <span class="show"><?php echo esc_html( $user->data->display_name ); ?></span>
					<?php } ?>
                </div>
            </div>
            <div class="form-group row">
                <label for="name-input" class="col-md-3"><?php esc_html_e( 'New Display name', 'madara' ); ?></label>
                <div class="col-md-9">
                    <input class="form-control" type="text" value="" name="user-new-name">
                </div>
            </div>
            <div class="form-group row">
                <label for="name-input-submit" class="col-md-3"></label>
                <div class="col-md-9">
                    <input class="form-control" type="submit" value="<?php esc_html_e( 'Submit', 'madara' ); ?>" id="name-input-submit" name="account-form-submit">
                </div>
            </div>

        </div>
        <div class="tab-item">
            <div class="settings-title">
                <h3>
					<?php esc_html_e( 'Change Your email address', 'madara' ); ?>
                </h3>
            </div>
            <div class="form-group row">
                <label for="email-input" class="col-md-3"><?php esc_html_e( 'Your email', 'madara' ); ?></label>
                <div class="col-md-9">
					<?php if ( isset( $user->data->user_email ) ) { ?>
                        <span class="show"><?php echo esc_html( $user->data->user_email ); ?></span>
					<?php } ?>
                </div>
            </div>
            <div class="form-group row">
                <label for="email-input" class="col-md-3"><?php esc_html_e( 'New email address', 'madara' ); ?></label>
                <div class="col-md-9">
                    <input class="form-control" type="email" value="" id="email-input" name="user-new-email">
                </div>
            </div>
            <div class="form-group row">
                <label for="email-input-submit" class="col-md-3"></label>
                <div class="col-md-9">
                    <input class="form-control" type="submit" value="<?php esc_html_e( 'Submit', 'madara' ); ?>" id="email-input-submit" name="account-form-submit">
                </div>
            </div>
        </div>
        <div class="tab-item">
            <div class="settings-title">
                <h3>
					<?php esc_html_e( 'Change Your Password', 'madara' ); ?>
                </h3>
            </div>

            <div class="form-group row">
                <label for="currrent-password-input" class="col-md-3"><?php esc_html_e( 'Current Password', 'madara' ); ?></label>
                <div class="col-md-9">
                    <input class="form-control" type="password" value="" id="currrent-password-input" name="user-current-password">
                </div>
            </div>
            <div class="form-group row">
                <label for="new-password-input" class="col-md-3"><?php esc_html_e( 'New Password', 'madara' ); ?></label>
                <div class="col-md-9">
                    <input class="form-control" type="password" value="" id="new-password-input" name="user-new-password">
                </div>
            </div>
            <div class="form-group row">
                <label for="comfirm-password-input" class="col-md-3"><?php esc_html_e( 'Comfirm Password', 'madara' ); ?></label>
                <div class="col-md-9">
                    <input class="form-control" type="password" value="" id="comfirm-password-input" name="user-new-password-confirm">
                    <span id="password-strength"></span>
                </div>
            </div>
            <div class="form-group row">
                <label for="password-input-submit" class="col-md-3"></label>
                <div class="col-md-9">
                    <label id="checkbox-weak-password" style="display:none"><input type="checkbox" id="agree-weak-password" /><?php esc_html_e('I agree to use this weak password', 'madara');?></label>
                    <input class="form-control" type="submit" value="<?php esc_html_e( 'Submit', 'madara' ); ?>" id="password-input-submit" name="account-form-submit">
                </div>
            </div>
        </div>
    </div>
</form>
