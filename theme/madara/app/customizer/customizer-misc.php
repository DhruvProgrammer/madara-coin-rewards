<?php

function madara_customize_register_misc( $wp_customize ) {
    $section = 'misc';

    $wp_customize->add_control(
        new Skyrocket_Toggle_Switch_Custom_control(
            $wp_customize,
            'polylang_footer',
            array(
                'label'          => esc_html__('Show Polylang Languages Switcher in Footer','madara'),
                'section'        => $section,
                'settings'       => 'polylang_footer',
                'type'           => 'select',
                'description' => '',
                'choices' => array(
                    'off' => esc_html__( 'Off', 'madara' ),
                    'on' => esc_html__( 'On', 'madara' )
				),
            )
        )
    );

    $wp_customize->add_control(
        new WP_Customize_Control(
            $wp_customize,
            'copyright',
            array(
                'label'          => esc_html__( 'Copyright Text', 'madara' ),
                'section'        => $section,
                'settings'       => 'copyright',
                'type'           => 'text',
                'description' => esc_html__( 'Appear in Footer', 'madara' ),
            )
        )
    );

    $wp_customize->add_control(
        new Skyrocket_Toggle_Switch_Custom_control(
            $wp_customize,
            'echo_meta_tags',
            array(
                'label'          => esc_html__( 'SEO - Echo Meta Tags', 'madara' ),
                'section'        => $section,
                'settings'       => 'echo_meta_tags',
                'type'           => 'select',
                'description' => esc_html__( 'By default, Madara generates its own SEO meta tags (for example: Facebook Meta Tags). If you are using another SEO plugin like YOAST or a Facebook plugin, you can turn off this option', 'madara' ),
                'choices' => array(
                    'off' => esc_html__( 'Off', 'madara' ),
                    'on' => esc_html__( 'On', 'madara' )
				),
            )
        )
    );

    $wp_customize->add_control(
        new Skyrocket_Toggle_Switch_Custom_control(
            $wp_customize,
            'lazyload',
            array(
                'label'          => esc_html__( 'Lazyload', 'madara' ),
                'section'        => $section,
                'settings'       => 'lazyload',
                'type'           => 'select',
                'description' => esc_html__( 'Enable to use Image Lazyload.', 'madara' ),
                'choices' => array(
                    'off' => esc_html__( 'Off', 'madara' ),
                    'on' => esc_html__( 'On', 'madara' )
				),
            )
        )
    );

    $wp_customize->add_control(
        new Skyrocket_Toggle_Switch_Custom_control(
            $wp_customize,
            'scroll_effect',
            array(
                'label'          => esc_html__( 'Enable Smooth Scroll Effect', 'madara' ),
                'section'        => $section,
                'settings'       => 'scroll_effect',
                'type'           => 'select',
                'description' => '',
                'choices' => array(
                    'off' => esc_html__( 'Off', 'madara' ),
                    'on' => esc_html__( 'On', 'madara' )
				),
            )
        )
    );

    $wp_customize->add_control(
        new Skyrocket_Toggle_Switch_Custom_control(
            $wp_customize,
            'go_to_top',
            array(
                'label'          => esc_html__( 'Enable Go To Top button', 'madara' ),
                'section'        => $section,
                'settings'       => 'go_to_top',
                'type'           => 'select',
                'description' => '',
                'choices' => array(
                    'off' => esc_html__( 'Off', 'madara' ),
                    'on' => esc_html__( 'On', 'madara' )
				),
            )
        )
    );

    $wp_customize->add_control(
        new Skyrocket_Toggle_Switch_Custom_control(
            $wp_customize,
            'loading_fontawesome',
            array(
                'label'          => esc_html__( 'Turn On/Off loading FontAwesome', 'madara' ),
                'section'        => $section,
                'settings'       => 'loading_fontawesome',
                'type'           => 'select',
                'description' => esc_html__( 'If you don\'t use FontAwesome (a Font Icons library), you can turn it off to save bandwidth', 'madara' ),
                'choices' => array(
                    'off' => esc_html__( 'Off', 'madara' ),
                    'on' => esc_html__( 'On', 'madara' )
				),
            )
        )
    );

    $wp_customize->add_control(
        new Skyrocket_Toggle_Switch_Custom_control(
            $wp_customize,
            'loading_ct_icons',
            array(
                'label'          => esc_html__( 'Turn On/Off loading CT-Icons', 'madara' ),
                'section'        => $section,
                'settings'       => 'loading_ct_icons',
                'type'           => 'select',
                'description' => esc_html__( 'If you don\'t use CT-Icons (a Font Icons library), you can turn it off to save bandwidth', 'madara' ),
                'choices' => array(
                    'off' => esc_html__( 'Off', 'madara' ),
                    'on' => esc_html__( 'On', 'madara' )
				),
            )
        )
    );

    /*
    $wp_customize->add_control(
        new WP_Customize_Control(
            $wp_customize,
            'custom_css_2',
            array(
                'label'          => esc_html__( 'Custom CSS', 'madara' ),
                'section'        => $section,
                'settings'       => 'custom_css_2',
                'type'           => 'textarea',
                'description' => esc_html__( 'Enter custom CSS. Ex: <i>.class{ font-size: 13px; }</i>', 'madara' ),
            )
        )
    );*/

    $wp_customize->add_control(
        new WP_Customize_Control(
            $wp_customize,
            'facebook_app_id',
            array(
                'label'          => esc_html__( 'Facebook App ID', 'madara' ),
                'section'        => $section,
                'settings'       => 'facebook_app_id',
                'type'           => 'text',
                'description' => esc_html__( '(Optional) Enter your Facebook App ID. It is useful when you share your post on Facebook', 'madara' ),
            )
        )
    );

    $wp_customize->add_control(
        new WP_Customize_Control(
            $wp_customize,
            'default_heading_style',
            array(
                'label'          => esc_html__( 'Default Heading Style', 'madara' ),
                'section'        => $section,
                'settings'       => 'default_heading_style',
                'type'           => 'select',
                'description' => esc_html__( 'Default Heading Style', 'madara' ),
                'choices' => array(
                    '1' => esc_html__( 'Style 1', 'madara' ),
                    '2' => esc_html__( 'Style 2', 'madara' ),
                    '3' => esc_html__( 'Style 3', 'madara' )
				),
            )
        )
    );

    $wp_customize->add_control(
        new WP_Customize_Control(
            $wp_customize,
            'static_icon',
            array(
                'label'          => esc_html__( 'Default Heading Icon', 'madara' ),
                'section'        => $section,
                'settings'       => 'static_icon',
                'type'           => 'text',
                'description' => esc_html__( 'Default Heading Icon used in Heading Style 2. Default is "ion-ios-star"', 'madara' ) . '<br/><a href="http://ionicons.com/" target="_blank">' . esc_html__( 'IonIcons', 'madara' ) . '</a><br/><a href="http://fontawesome.io/icons/" target="_blank">' . esc_html__( 'FontAwesome', 'madara' ) . '</a>',
            )
        )
    );

    $wp_customize->add_control(
        new WP_Customize_Control(
            $wp_customize,
            'pre_loading',
            array(
                'label'          => esc_html__( 'Pre-loading Effect', 'madara' ),
                'section'        => $section,
                'settings'       => 'pre_loading',
                'type'           => 'select',
                'description' => esc_html__( 'Enable Pre-loading Effect', 'madara' ),
                'choices' => array(
                    '-1' => esc_html__( 'Disable All', 'madara' ),
                    '1' => esc_html__( 'Enable All', 'madara' ),
                    '2' => esc_html__( 'Front-page Only', 'madara' )
				),
            )
        )
    );

    $wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize, 'pre_loading_logo', array(
        'label'      => esc_html__( 'Pre-loading Logo', 'madara' ),
        'section'    => $section,
        'settings'   => 'pre_loading_logo',
        'description' => esc_html__( 'Preloading Logo. If not selected, Logo Image at Theme Options > General > Logo Image will be used', 'madara' ),
    ) ) );

    $wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, 'pre_loading_bg_color',
            array(
                'label' => esc_html__( 'Pre-loading Background Color', 'madara' ),
                'section' => $section,
                'settings'   => 'pre_loading_bg_color',
                'description' => esc_html__( 'Default is #eb3349', 'madara' ),
            )
    ) );
    
    $wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, 'pre_loading_icon_color',
        array(
            'label' => esc_html__( 'Pre-loading Icon Color', 'madara' ),
            'section' => $section,
            'settings'   => 'pre_loading_icon_color',
            'description' => esc_html__( 'Default is #ffffff', 'madara' ),
        )
    ) );

    $loading_effect_choices = array(
        array(
            'value' => 'ball-pulse',
            'label' => esc_html__( 'Ball Pulse', 'madara' ),
            'src'   => get_parent_theme_file_uri( '/images/options/ajax-loading/ball-pulse.gif' ),
        ),
        array(
            'value' => 'ball-pulse-sync',
            'label' => esc_html__( 'Ball Pulse Sync', 'madara' ),
            'src'   => get_parent_theme_file_uri( '/images/options/ajax-loading/ball-pulse-sync.gif' ),
        ),
        array(
            'value' => 'ball-beat',
            'label' => esc_html__( 'Ball Beat', 'madara' ),
            'src'   => get_parent_theme_file_uri( '/images/options/ajax-loading/ball-beat.gif' ),
        ),
        array(
            'value' => 'ball-rotate',
            'label' => esc_html__( 'Ball Rotate', 'madara' ),
            'src'   => get_parent_theme_file_uri( '/images/options/ajax-loading/ball-rotate.gif' ),
        ),
        array(
            'value' => 'ball-grid-pulse',
            'label' => esc_html__( 'Ball Grid Pulse', 'madara' ),
            'src'   => get_parent_theme_file_uri( '/images/options/ajax-loading/ball-grid-pulse.gif' ),
        ),
        array(
            'value' => 'ball-grid-beat',
            'label' => esc_html__( 'Ball Grid Beat', 'madara' ),
            'src'   => get_parent_theme_file_uri( '/images/options/ajax-loading/ball-grid-beat.gif' ),
        ),
        array(
            'value' => 'ball-clip-rotate',
            'label' => esc_html__( 'Ball Clip Rotate', 'madara' ),
            'src'   => get_parent_theme_file_uri( '/images/options/ajax-loading/ball-clip-rotate.gif' ),
        ),
        array(
            'value' => 'ball-clip-rotate-pulse',
            'label' => esc_html__( 'Ball Clip Rotate Pulse', 'madara' ),
            'src'   => get_parent_theme_file_uri( '/images/options/ajax-loading/ball-clip-rotate-pulse.gif' ),
        ),
        array(
            'value' => 'ball-clip-rotate-multiple',
            'label' => esc_html__( 'Ball Clip Rotate Multiple', 'madara' ),
            'src'   => get_parent_theme_file_uri( '/images/options/ajax-loading/ball-clip-rotate-multiple.gif' ),
        ),
        array(
            'value' => 'ball-pulse-rise',
            'label' => esc_html__( 'Ball Pulse Rise', 'madara' ),
            'src'   => get_parent_theme_file_uri( '/images/options/ajax-loading/ball-pulse-rise.gif' ),
        ),
        array(
            'value' => 'cube-transition',
            'label' => esc_html__( 'Cube Transition', 'madara' ),
            'src'   => get_parent_theme_file_uri( '/images/options/ajax-loading/cube-transition.gif' ),
        ),
        array(
            'value' => 'ball-zig-zag',
            'label' => esc_html__( 'Ball Zig Zag', 'madara' ),
            'src'   => get_parent_theme_file_uri( '/images/options/ajax-loading/ball-zig-zag.gif' ),
        ),
        array(
            'value' => 'ball-zig-zag-deflect',
            'label' => esc_html__( 'Ball Zig Zag Deflect', 'madara' ),
            'src'   => get_parent_theme_file_uri( '/images/options/ajax-loading/ball-zig-zag-deflect.gif' ),
        ),
        array(
            'value' => 'ball-triangle-path',
            'label' => esc_html__( 'Ball Triangle Path', 'madara' ),
            'src'   => get_parent_theme_file_uri( '/images/options/ajax-loading/ball-triangle-path.gif' ),
        ),
        array(
            'value' => 'line-scale',
            'label' => esc_html__( 'Line Scale', 'madara' ),
            'src'   => get_parent_theme_file_uri( '/images/options/ajax-loading/line-scale.gif' ),
        ),
        array(
            'value' => 'line-scale-party',
            'label' => esc_html__( 'Line Scale Party', 'madara' ),
            'src'   => get_parent_theme_file_uri( '/images/options/ajax-loading/line-scale-party.gif' ),
        ),
        array(
            'value' => 'line-scale-pulse-out',
            'label' => esc_html__( 'Line Scale Pulse Out', 'madara' ),
            'src'   => get_parent_theme_file_uri( '/images/options/ajax-loading/line-scale-pulse-out.gif' ),
        ),
        array(
            'value' => 'line-scale-pulse-out-rapid',
            'label' => esc_html__( 'Line Scale Pulse Put Rapid', 'madara' ),
            'src'   => get_parent_theme_file_uri( '/images/options/ajax-loading/line-scale-pulse-out-rapid.gif' ),
        ),
        array(
            'value' => 'ball-scale',
            'label' => esc_html__( 'Ball Scale', 'madara' ),
            'src'   => get_parent_theme_file_uri( '/images/options/ajax-loading/ball-scale.gif' ),
        ),
        array(
            'value' => 'ball-scale-multiple',
            'label' => esc_html__( 'Ball Scale Multiple', 'madara' ),
            'src'   => get_parent_theme_file_uri( '/images/options/ajax-loading/ball-scale-multiple.gif' ),
        ),
        array(
            'value' => 'ball-scale-ripple',
            'label' => esc_html__( 'Ball Scale Ripple', 'madara' ),
            'src'   => get_parent_theme_file_uri( '/images/options/ajax-loading/ball-scale-ripple.gif' ),
        ),
        array(
            'value' => 'ball-scale-ripple-multiple',
            'label' => esc_html__( 'Ball Scale Ripple Multiple', 'madara' ),
            'src'   => get_parent_theme_file_uri( '/images/options/ajax-loading/ball-scale-ripple-multiple.gif' ),
        ),
        array(
            'value' => 'ball-spin-fade-loader',
            'label' => esc_html__( 'Ball Spin Fade Loader', 'madara' ),
            'src'   => get_parent_theme_file_uri( '/images/options/ajax-loading/ball-spin-fade-loader.gif' ),
        ),
        array(
            'value' => 'line-spin-fade-loader',
            'label' => esc_html__( 'Line Spin Fade Loader', 'madara' ),
            'src'   => get_parent_theme_file_uri( '/images/options/ajax-loading/line-spin-fade-loader.gif' ),
        ),
        array(
            'value' => 'triangle-skew-spin',
            'label' => esc_html__( 'Triangle Skew Spin', 'madara' ),
            'src'   => get_parent_theme_file_uri( '/images/options/ajax-loading/triangle-skew-spin.gif' ),
        ),
        array(
            'value' => 'semi-circle-spin',
            'label' => esc_html__( 'Semi Circle Spin', 'madara' ),
            'src'   => get_parent_theme_file_uri( '/images/options/ajax-loading/semi-circle-spin.gif' ),
        ),
        array(
            'value' => 'square-spin',
            'label' => esc_html__( 'Square Spin', 'madara' ),
            'src'   => get_parent_theme_file_uri( '/images/options/ajax-loading/square-spin.gif' ),
        ),
    );

    $loading_effect_choices_converted = [];
    foreach($loading_effect_choices as $item){
        $loading_effect_choices_converted[$item['value']] = array('name' => $item['label'], 'image' => $item['src']);
    }

    
    $wp_customize->add_control(
        new Skyrocket_Image_Radio_Button_Custom_Control(
            $wp_customize,
            'ajax_loading_effect',
            array(
                'label'          => esc_html__( 'Preloading Icon', 'madara' ),
                'section'        => $section,
                'settings'       => 'ajax_loading_effect',
                'description' => '',
                'choices' => $loading_effect_choices_converted
            )
        )
    );

    $wp_customize->add_control(
        new Skyrocket_Toggle_Switch_Custom_control(
            $wp_customize,
            'poppins_font',
            array(
                'label'          => esc_html__('Disable Default Poppins Font','madara'),
                'section'        => $section,
                'settings'       => 'poppins_font',
                'type'           => 'select',
                'description' => esc_html__('If you do not use default Poppins Font, you can disable loading it from Google Fonts here', 'madara'),
                'choices' => array(
                    'off' => esc_html__( 'Off', 'madara' ),
                    'on' => esc_html__( 'On', 'madara' )
				),
            )
        )
    );

    $thumb_sizes = App\config\ThemeConfig::getAllThumbSizes();
    
	if ( is_array( $thumb_sizes ) ) {
		foreach ( $thumb_sizes as $size => $config ) {
            $wp_customize->add_control(
                new Skyrocket_Toggle_Switch_Custom_control(
                    $wp_customize,
                    $size,
                    array(
                        'label'          => $config[3],
                        'section'        => $section,
                        'settings'       => $size,
                        'type'           => 'select',
                        'description' => $config[4],
                        'choices' => array(
                            'off' => esc_html__( 'Off', 'madara' ),
                            'on' => esc_html__( 'On', 'madara' )
                        ),
                    )
                )
            );
		}
	}
}