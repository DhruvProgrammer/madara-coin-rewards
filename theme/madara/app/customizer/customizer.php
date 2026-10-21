<?php

require dirname(__FILE__) . '/custom-controls.php';
require dirname(__FILE__) . '/customizer-general.php';
require dirname(__FILE__) . '/customizer-generallayout.php';
require dirname(__FILE__) . '/customizer-colors.php';
require dirname(__FILE__) . '/customizer-fonts.php';
require dirname(__FILE__) . '/customizer-header.php';
require dirname(__FILE__) . '/customizer-blog.php';
require dirname(__FILE__) . '/customizer-single-post.php';
require dirname(__FILE__) . '/customizer-single-page.php';
require dirname(__FILE__) . '/customizer-search.php';
require dirname(__FILE__) . '/customizer-404.php';
require dirname(__FILE__) . '/customizer-advertising.php';
require dirname(__FILE__) . '/customizer-socials.php';
require dirname(__FILE__) . '/customizer-misc.php';
require dirname(__FILE__) . '/customizer-amp.php';
require dirname(__FILE__) . '/customizer-users.php';

function madara_customizer_register_background_controls($wp_customize, $name, $label, $section){
    $wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, $name . '_color',
			array(
				'label' => sprintf(esc_html__( '%s - Background Color', 'madara' ), $label),
				'section' => $section,
                'settings'   => $name . '_color',
                'description' => sprintf(esc_html__('Upload background image for %s', 'madara'), $label)
			)
		) );

    $wp_customize->add_control( new WP_Customize_Control(
        $wp_customize,
        $name . '_repeat',
        array(
            'label'          => sprintf(esc_html__( '%s - Background Repeat', 'madara' ), $label),
            'section'        => $section,
            'settings'       => $name . '_repeat',
            'type'           => 'select',
            'choices' => array(
                '' => esc_html__( 'background-repeat', 'madara' ),
                'no-repeat' => esc_html__( 'No Repeat', 'madara' ),
                'repeat-all' => esc_html__( 'Repeat All', 'madara' ),
                'repeat-x' => esc_html__( 'Repeat Horizontally', 'madara' ),
                'repeat-y' => esc_html__( 'Repeat Vertically', 'madara' ),
                'inherit' => esc_html__( 'inherit', 'madara' )
            ),
        )
    ) );

    $wp_customize->add_control( new WP_Customize_Control(
        $wp_customize,
        $name . '_attachment',
        array(
            'label'          => sprintf(esc_html__( '%s - Background Attachment', 'madara' ), $label),
            'section'        => $section,
            'settings'       => $name . '_attachment',
            'type'           => 'select',
            'choices' => array(
                '' => esc_html__( 'background-attachment', 'madara' ),
                'fixed' => esc_html__( 'Fixed', 'madara' ),
                'scroll' => esc_html__( 'Scroll', 'madara' ),
                'inherit' => esc_html__( 'inherit', 'madara' )
            ),
        )
    ) );

    $wp_customize->add_control( new WP_Customize_Control(
        $wp_customize,
        $name . '_position',
        array(
            'label'          => sprintf(esc_html__( '%s - Background Position', 'madara' ), $label),
            'section'        => $section,
            'settings'       => $name . '_repeat',
            'type'           => 'select',
            'choices' => array(
                '' => esc_html__( 'background-position', 'madara' ),
                'left-top' => esc_html__( 'Left Top', 'madara' ),
                'left-center' => esc_html__( 'Left Center', 'madara' ),
                'left-bottom' => esc_html__( 'Left Bottom', 'madara' ),
                'center-top' => esc_html__( 'Center Top', 'madara' ),
                'center-center' => esc_html__( 'Center Center', 'madara' ),
                'center-bottom' => esc_html__( 'Center Bottom', 'madara' ),
                'right-top' => esc_html__( 'Right Top', 'madara' ),
                'right-center' => esc_html__( 'Right Center', 'madara' ),
                'right-bottom' => esc_html__( 'Right Bottom', 'madara' ),
            ),
        )
    ) );

    $wp_customize->add_control(
        new WP_Customize_Control(
            $wp_customize,
            $name . '_size',
            array(
                'label'          => sprintf(esc_html__( '%s - Background Size', 'madara' ), $label),
                'section'        => $section,
                'settings'       => $name . '_size',
                'type'           => 'text',
                'description' => esc_html__('See "https://developer.mozilla.org/en-US/docs/Web/CSS/background-size"', 'madara')
            )
        )
    );

    $wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize, $name . '_image', array(
        'label'      => sprintf(esc_html__( '%s - Background Image', 'madara' ), $label),
        'section'    => $section,
        'settings'   => $name . '_image'
    ) ) );
}

function madara_background_control_properties($control_name){
    return array(
        $control_name . '_color' => '',
        $control_name . '_repeat' => '',
        $control_name . '_attachment' => '',
        $control_name . '_position' => '',
        $control_name . '_size' => '',
        $control_name . '_image' => '');
}

function madara_customize_register( $wp_customize ) {
    $wp_customize->add_section( 'madara_general' , array(
        'title'      => esc_html__( 'Branding', 'madara' ),
        'priority'   => 1,
    ) );

    $wp_customize->add_section( 'theme_layout' , array(
        'title'      => esc_html__( 'General Layout', 'madara' ),
        'priority'   => 2,
    ) );

    $wp_customize->add_section( 'custom_colors' , array(
        'title'      => esc_html__( 'Custom Colors', 'madara' ),
        'priority'   => 3,
    ) );

    $wp_customize->add_section( 'custom_fonts' , array(
        'title'      => esc_html__( 'Custom Fonts', 'madara' ),
        'priority'   => 4,
    ) );

    $wp_customize->add_section( 'header' , array(
        'title'      => esc_html__( 'Header', 'madara' ),
        'priority'   => 5,
    ) );

    $wp_customize->add_section( 'archives' , array(
        'title'      => esc_html__( 'Blog', 'madara' ),
        'priority'   => 6,
    ) );

    $wp_customize->add_section( 'single_post' , array(
        'title'      => esc_html__( 'Single Post', 'madara' ),
        'priority'   => 7,
    ) );

    $wp_customize->add_section( 'single_page' , array(
        'title'      => esc_html__( 'Single Page', 'madara' ),
        'priority'   => 8,
    ) );

    $wp_customize->add_section( 'search' , array(
        'title'      => esc_html__( 'Search', 'madara' ),
        'priority'   => 9,
    ) );
    $wp_customize->add_section( '404' , array(
        'title'      => esc_html__( '404 - Page Not Found', 'madara' ),
        'priority'   => 10,
    ) );
    $wp_customize->add_section( 'advertising' , array(
        'title'      => esc_html__( 'Advertising', 'madara' ),
        'priority'   => 11,
    ) );
    $wp_customize->add_section( 'socials' , array(
        'title'      => esc_html__( 'Social Accounts', 'madara' ),
        'priority'   => 12,
    ) );

    $wp_customize->add_section( 'misc' , array(
        'title'      => esc_html__( 'Misc', 'madara' ),
        'priority'   => 13,
    ) );

    $wp_customize->add_section( 'amp' , array(
        'title'      => esc_html__( 'AMP', 'madara' ),
        'priority'   => 14,
    ) );

    $wp_customize->add_section( 'user_settings' , array(
        'title'      => esc_html__( 'User Settings', 'madara' ),
        'priority'   => 15,
    ) );

    $arr_settings = array(
        'logo_image' => '',
        'logo_image_size' => '',
        'retina_logo_image' => '',
        'login_logo_image' => '',
        // GENERAL LAYOUT SETTINGS
        'body_schema' => 'light',
        'body_schema_toggle' => 'off',
        'sidebar_size' => 4,
        'main_top_sidebar_container' => 'container',
        'main_top_sidebar_spacing' => '',
        'main_top_second_sidebar_container' => 'container',
        'main_top_second_sidebar_spacing' => '',
        'main_bottom_sidebar_container' => 'container',
        'main_bottom_sidebar_spacing' => ''
    );

    $arr_settings = array_merge($arr_settings, madara_background_control_properties('main_top_sidebar_background'));
    
    $arr_settings = array_merge($arr_settings, madara_background_control_properties('main_top_second_sidebar_background'));

    $arr_settings = array_merge($arr_settings, madara_background_control_properties('main_bottom_sidebar_background'));

    $arr_settings = array_merge($arr_settings, madara_background_control_properties('login_popup_background'));

    $arr_settings = array_merge($arr_settings,
            array(
                'site_custom_colors' => 'off',
                'main_color' => '',
                'main_color_end' => '',
                'link_color_hover' => '',
                'star_color' => '',
                'hot_badges_bg_color' => '',
                'new_badges_bg_color' => '',
                'custom_badges_bg_color' => '',
                'btn_bg' => '',
                'btn_color' => '',
                'btn_hover_bg' => '',
                'btn_hover_color' => '',
                'header_custom_colors' => 'off',
                'nav_item_color' => '',
                'nav_item_hover_color' => '',
                'nav_sub_bg' => '',
                'nav_sub_bg_border_color' => '',
                'nav_sub_item_color' => '',
                'nav_sub_item_hover_color' => '',
                'nav_sub_item_hover_bg' => '',
                'header_bottom_custom_colors' => 'off',
                'header_bottom_bg' => '',
                'bottom_nav_item_color' => '',
                'bottom_nav_item_hover_color' => '',
                'bottom_nav_sub_bg' => '',
                'bottom_nav_sub_item_color' => '',
                'bottom_nav_sub_item_hover_color' => '',
                'bottom_nav_sub_border_bottom' => '',
                'mobile_menu_custom_color' => 'off',
                'mobile_browser_header_color' => '',
                'canvas_menu_background' => '',
                'canvas_menu_color' => '',
                'canvas_menu_hover' => ''
            )
        );
    
    // custom fonts
    $arr_settings = array_merge($arr_settings, array(
        'font_using_custom' => 'off',
        'main_font_on_google' => 'on',
        'main_font_google_family' => '',
        'main_font_family' => '',
        'main_font_size' => '14',
        'main_font_weight' => '',
        'main_font_line_height' => '1.5',
        'heading_font_on_google' => 'on',
        'heading_font_google_family' => '',
        'heading_font_family' => '',
        'heading_font_size_h1' => '34',
        'h1_line_height' => '1.2',
        'h1_font_weight' => '600',
        'heading_font_size_h2' => '30',
        'h2_line_height' => '1.2',
        'h2_font_weight' => '600',
        'heading_font_size_h3' => '24',
        'h3_line_height' => '1.4',
        'h3_font_weight' => '600',
        'heading_font_size_h4' => '18',
        'h4_line_height' => '1.2',
        'h4_font_weight' => '600',
        'heading_font_size_h5' => '16',
        'h5_line_height' => '1.2',
        'h5_font_weight' => '600',
        'heading_font_size_h6' => '14',
        'h6_line_height' => '1.2',
        'h6_font_weight' => '500',
        'navigation_font_on_google' => 'on',
        'navigation_font_google_family' => '',
        'navigation_font_family' => '',
        'navigation_font_size' => '14',
        'navigation_font_weight' => '400',
        'meta_font_on_google' => 'on',
        'meta_font_google_family' => '',
        'meta_font_family' => '',
        'custom_font_1' => '',
        'custom_font_2' => '',
        'custom_font_3' => '',
    ));

    // Header
    $arr_settings = array_merge($arr_settings, array(
        'header_style' => 1,
        'nav_sticky' => 1,
        'header_bottom_border' => 'on',
        'header_disable_login_buttons' => 'on'
    ));

    // Blog
    $arr_settings = array_merge($arr_settings, array(
        'archive_sidebar' => 'right',
        'archive_heading_text' => '',
        'archive_heading_icon' => '',
        'archive_margin_top' => '',
        'archive_content_columns' => '',
        'archive_navigation' => 'default',
        'archive_breadcrumbs' => 'on',
        'archive_navigation_same_term' => 'off',
        'archive_navigation_term_taxonomy' => '',
        'archive_post_excerpt' => 'on'
    ));

    // Post
    $arr_settings = array_merge($arr_settings, array(
        'single_sidebar' => 'right',
        'single_excerpt' => '',
        'single_featured_image' => 'on',
        'single_tags' => 'on',
        'post_meta_tags' => 'on',
        'single_category' => 'on',
        'enable_comment' => 'on',
        'single_reverse_nav' => 'off'
    ));

    // 404
    $arr_settings = array_merge($arr_settings, array(
        'page404_head_tag' => '',
        'page404_featured_image' => '',
        'page404_title' => '',
        'page404_content' => ''
    ));

    // Page
    $arr_settings = array_merge($arr_settings, array(
        'page_sidebar' => 'right',
        'page_meta_tags' => 'on',
        'page_comments' => 'on'
    ));

    // Search
    $arr_settings = array_merge($arr_settings, madara_background_control_properties('search_header_background'));
    $arr_settings = array_merge($arr_settings, array(
        'madara_search_use_archivelayout' => 'no',
        'manga_search_exclude_tags' => '',
        'manga_search_exclude_genres' => '',
        'manga_search_exclude_authors' => '',
        'madara_ajax_search' => ''
    ));

    // Advertising
    $ad_slots = array(
        'ads_before_content' => esc_html__('Before of content Ads', 'madara'),
        'ads_after_content'  => esc_html__('After of content Ads', 'madara'),
        'ads_footer'         => esc_html__('Footer Ads', 'madara'),
        'ads_wall_left'      => esc_html__('Wall Ads Left', 'madara'),
        'ads_wall_right'     => esc_html__('Wall Ads Right', 'madara')
    );

    $ad_slots = apply_filters( 'madara_ad_slots', $ad_slots );
    foreach($ad_slots as $slot => $name){
        $arr_settings = array_merge($arr_settings, array(
            'adsense_slot_' . $slot => '',
            $slot => '',
        ));
    }

    $arr_settings = array_merge($arr_settings, array(
        'ads_wall_scrolltop' => '210',
        'ads_wall_maxtop' => '510'
    ));

    // Add our Sortable Repeater setting and Custom Control for Social media URLs
    $wp_customize->add_setting( 'custom_social_account',
        array(
            'default' => '',
            'transport' => 'postMessage',
        )
    );

    $wp_customize->add_setting( 'open_social_link_new_tab');

    // Social Accounts
    $socials = array(
        'facebook' => esc_html__( 'Facebook', 'madara' ),
        'twitter'  => esc_html__( 'Twitter', 'madara' ),
        'linkedin'         => esc_html__( 'LinkedIn', 'madara' ),
        'tumblr'      => esc_html__( 'Tumblr', 'madara' ),
        'google-plus'     => esc_html__( 'Google Plus', 'madara' ),
        'pinterest' => esc_html__( 'Pinterest', 'madara' ),
        'youtube' => esc_html__( 'Youtube', 'madara' ),
        'flickr' => esc_html__( 'Flickr', 'madara' ),
        'dribbble' => esc_html__( 'Dribbble', 'madara' ),
        'behance' => esc_html__( 'Behance', 'madara' ),
        'envelop' => esc_html__( 'Email', 'madara' ),
        'rss' => esc_html__( 'RSS', 'madara' ),
    );

    $socials = apply_filters( 'madara_social_accounts', $socials );

    foreach ( $socials as $key => $name ) {
        $arr_settings = array_merge($arr_settings, array(
            $key => '',
        ));
    }
    
    // Mísc
    $arr_settings = array_merge($arr_settings, array(
        'polylang_footer' => '',
        'copyright' => '',
        'echo_meta_tags' => 'on',
        'lazyload' => 'off',
        'scroll_effect' => 'off',
        'go_to_top' => 'off',
        'loading_fontawesome' => 'on',
        'loading_ionicons' => 'on',
        'loading_ct_icons' => 'on',
        'facebook_app_id' => '',
        'default_heading_style' => 2,
        'static_icon' => '',
        'pre_loading' => '-1',
        'pre_loading_bg_color' => '',
        'pre_loading_icon_color' => '',
        'ajax_loading_effect' => '',
        'poppins_font' => 'on'
    ));

    $thumb_sizes = App\config\ThemeConfig::getAllThumbSizes();
	if ( is_array( $thumb_sizes ) ) {
		foreach ( $thumb_sizes as $size => $config ) {
            $arr_settings = array_merge($arr_settings, array($size => 'on'));
        }
    }
    
    $arr_settings = array_merge($arr_settings, array(
        'amp' => '',
        'amp_fontawesome_key' => '',
        'amp_image_height' => '400',
        'amp_manga_reading_style' => 'list'
    ));   

    $arr_settings = array_merge($arr_settings, array(
        'user_settings_weak_password' => ''
    )); 
    
    foreach($arr_settings as $key => $def_value){
        $wp_customize->add_setting( $key , array(
            'default'   => $def_value,
            'transport' => 'refresh',
        ) );
    };    

    madara_customize_register_general($wp_customize);
    madara_customize_register_generallayout($wp_customize);
    madara_customize_register_colors($wp_customize);
    madara_customize_register_fonts($wp_customize);
    madara_customize_register_header($wp_customize);
    madara_customize_register_blog($wp_customize);
    madara_customize_register_post($wp_customize);
    madara_customize_register_page($wp_customize);
    madara_customize_register_search($wp_customize);
    madara_customize_register_404($wp_customize);
    madara_customize_register_advertising($wp_customize);
    madara_customize_register_socials($wp_customize);
    madara_customize_register_misc($wp_customize);
    madara_customize_register_amp($wp_customize);
    madara_customize_register_users($wp_customize);

    /** Support Speaker plugin */
    if( class_exists( '\Merkulove\Speaker\SpeakerCaster' ) ){
        $wp_customize->add_section( 'manga_speaker' , array(
            'title'      => esc_html__( 'Novel Audio', 'madara' )
        ) );

        $wp_customize->add_setting( 'speaker_sized' , array(
            'default'   => ''
        ) );

        $wp_customize->add_setting( 'speaker_position' , array(
            'default'   => 'floating'
        ) );

        $wp_customize->add_control(
            new WP_Customize_Control(
                $wp_customize,
                'speaker_sized',
                array(
                    'label'          => esc_html__('Player Size','madara'),
                    'section'        => 'manga_speaker',
                    'settings'       => 'speaker_sized',
                    'type'           => 'select',
                    'description' => esc_html__('Size of the audio player','madara'),
                    'choices' => array(
                        '' => esc_html__( 'Full width', 'madara' ),
                        'sized' => esc_html__( 'Small Player', 'madara' )
                    ),
                )
            )
        );

        $wp_customize->add_control(
            new WP_Customize_Control(
                $wp_customize,
                'speaker_position',
                array(
                    'label'          => esc_html__('Player Position','madara'),
                    'section'        => 'manga_speaker',
                    'settings'       => 'speaker_position',
                    'type'           => 'select',
                    'description' => '',
                    'choices' => array(
                        'floating' => esc_html__( 'Floating (fixed position at bottom)', 'madara' ),
                        '' => esc_html__( 'Before chapter content', 'madara' ),
                    ),
                )
            )
        );
    }
 }

 add_action( 'customize_register', 'madara_customize_register' );

 require dirname(__FILE__) . '/customizer-manga-general.php';
 require dirname(__FILE__) . '/customizer-manga-settings.php';
 require dirname(__FILE__) . '/customizer-manga-archives.php';
 require dirname(__FILE__) . '/customizer-manga-detail.php';
 require dirname(__FILE__) . '/customizer-manga-reading.php';