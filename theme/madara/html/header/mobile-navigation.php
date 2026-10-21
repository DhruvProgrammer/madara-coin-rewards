<?php
	/**
	 * Mobile Navigation Template
	 * @package madara
	 */
    use App\Madara;
    $header_login_buttons = Madara::getOption('header_disable_login_buttons', 'on');
    $user_enabled = ($header_login_buttons == 'on') && ! is_user_logged_in() && get_option( 'users_can_register' );
?>

<div class="mobile-menu menu-collapse off-canvas">
    <div class="close-nav">
        <button aria-label="close" class="menu_icon__close">
            <span></span> <span></span>
        </button>
    </div>

	<?php if ( is_user_logged_in() ) {
		$current_user = wp_get_current_user();
		?>
        <div class="c-user_item">
            <div class="c-user_avatar" >
                <div class="c-user_avatar-image">
					<?php echo get_avatar( $current_user->ID, 80 ); ?>
                </div>
            </div>
            <span class="c-user_name"><?php echo esc_html( $current_user->display_name ); ?></span>
        </div>

	<?php } elseif( $user_enabled ) { ?>

        <div class="user-section c-modal_item">
            <!-- Button trigger modal -->
            <span class="c-modal_sign-in">
                <a href="#" data-toggle="modal" data-target="#form-login" class="btn-active-modal"><?php echo esc_html__( 'Sign in', 'madara' ); ?></a>
            </span>

            <span class="c-modal_sign-up">
                <a href="javascript:void(0)" data-toggle="modal" data-target="#form-sign-up" class="btn-active-modal"><?php echo esc_html__( 'Sign up', 'madara' ); ?></a>
            </span>

        </div>

	<?php } ?>

    <nav class="off-menu">
		<?php
			if ( has_nav_menu( 'mobile_menu' ) ) {

				wp_nav_menu( array(
					'theme_location' => 'mobile_menu',
					'container'      => false,
					'menu_class'     => 'nav navbar-nav main-navbar',
					'walker'         => new App\Plugins\Walker_Nav_Menu\Custom_Walker_Nav_Menu()
				) );

			} else {

				wp_nav_menu( array(
					'theme_location' => 'primary_menu',
					'container'      => false,
					'menu_class'     => 'nav navbar-nav main-navbar',
					'walker'         => new App\Plugins\Walker_Nav_Menu\Custom_Walker_Nav_Menu()
				) );

			}
		?>
    </nav>
	
	<div class="center"><?php madara_adult_filter_button() ;?></div>
</div>