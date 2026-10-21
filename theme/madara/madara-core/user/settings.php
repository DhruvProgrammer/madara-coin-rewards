<?php

	/**
	 * The Template for User Settings page
	 *
	 * This template can be overridden by copying it to your-child-theme/madara-core/user/settings.php
	 *
	 * HOWEVER, on occasion Madara will need to update template files and you
	 * (the theme developer) will need to copy the new files to your theme to
	 * maintain compatibility. We try to do this as little as possible, but it does
	 * happen. When this occurs the version of the template file will be bumped and
	 * we will list any important changes on our theme release logs.
	 * @package Madara
	 * @version 1.7.2.2
	 */
	 

	if ( ! is_user_logged_in() ) {
		return;
	}

	$wp_manga_template = madara_get_global_wp_manga_template();

	$array_compare = madara_get_user_settings_tabs();
	
	$tab_pane = isset( $_GET['tab'] ) ? $_GET['tab'] : array_keys($array_compare)[0];
	
	$account = wp_get_current_user();
?>

<div class="row settings-page">
    <div class="col-md-3 col-sm-3">
        <div class="nav-tabs-wrap">
            <ul class="nav nav-tabs">
				<?php foreach($array_compare as $tab => $settings){?>
				
                <li class="<?php echo esc_attr( $tab_pane == $tab ? 'active' : ''); ?>">
                    <a href="<?php echo esc_url( $settings['url'] ); ?>"><i class="<?php echo esc_attr($settings['icon']);?>"></i><?php echo esc_html($settings['label']); ?>
                    </a>
                </li>
                
				<?php } ?>
				
				<?php do_action( 'madara_user_nav_tabs', $tab_pane, $account ); ?>
            </ul>
        </div>
    </div>
    <div class="col-md-9 col-sm-9">
        <div class="tabs-content-wrap">
            <div class="tab-content">
				<?php if ( in_array( $tab_pane, array_keys($array_compare) ) ) { ?>
                    <div class="tab-pane active">
						<?php $wp_manga_template->load_template( "user/page/$tab_pane" ); ?>
                    </div>
				<?php } ?>

				<?php do_action( 'madara_user_nav_contents', $tab_pane, $account ); ?>
            </div>
        </div>
    </div>
</div>
