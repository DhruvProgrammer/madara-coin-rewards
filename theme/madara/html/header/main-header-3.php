<?php

    use App\Madara;
    global $wp_manga_user_actions;

	do_action( 'madara_main_header_before', 3 );

    $header_login_buttons = Madara::getOption('header_disable_login_buttons', 'on');
    $user_enabled = ($header_login_buttons == 'on') && ! is_user_logged_in() && get_option( 'users_can_register' );
    $user_manga_logged = is_user_logged_in() && class_exists( 'WP_MANGA' );

?>
    <div class="search-navigation search-sidebar">

		<?php if ( is_active_sidebar( 'search_sidebar' ) ) { ?>
			<?php dynamic_sidebar( 'search_sidebar' ); ?>
		<?php } else { ?>

            <div class="search-navigation__wrap">
                <ul class="main-menu-search nav-menu">
                    <li class="menu-search">
                        <a href="javascript:;" class="open-search-main-menu"> <i class="icon ion-ios-search"></i>
                            <i class="icon ion-android-close"></i> </a>

                    </li>
                </ul>
            </div>
		<?php } ?>		
    </div>
    <?php if ( $user_enabled ) { ?>
        <div class="user-section c-modal_item">
            <!-- Button trigger modal -->
            <a href="javascript:void(0)" data-toggle="modal" data-target="#form-login" class="btn-active-modal"><?php echo esc_html__( 'Sign in', 'madara' ); ?></a>
            <a href="javascript:void(0)" data-toggle="modal" data-target="#form-sign-up" class="btn-active-modal"><?php echo esc_html__( 'Sign up', 'madara' ); ?></a>
        </div>
    <?php } elseif ( $user_manga_logged ) { ?>
        <div class="user-section c-modal_item">
            <?php
                $wp_manga_user_actions->get_user_section( 50, true);
            ?>
        </div>
    <?php } ?>
    <div class="c-togle__menu">
        <button aria-label="open" type="button" class="menu_icon__open">
            <span></span> <span></span> <span></span>
        </button>
    </div>
<?php

	/**
	 * Hook to wrap Main Header div
	 */
	do_action( 'madara_main_header_after', 3 );
