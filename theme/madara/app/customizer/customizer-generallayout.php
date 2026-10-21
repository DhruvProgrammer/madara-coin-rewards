<?php

function madara_customize_register_generallayout( $wp_customize ) {
    $wp_customize->add_control(
        new WP_Customize_Control(
            $wp_customize,
            'body_schema',
            array(
                'label'          => esc_html__( 'Body Schema', 'madara' ),
                'section'        => 'theme_layout',
                'settings'       => 'body_schema',
                'type'           => 'select',
                'description' => esc_html__('Choose Body Color Schema', 'madara'),
                'choices' => array(
                    'light' => esc_html__( 'Light', 'madara' ),
                    'dark' => esc_html__( 'Dark', 'madara' )
				),
            )
        )
    );
    

    $wp_customize->add_control(
        new Skyrocket_Toggle_Switch_Custom_control(
            $wp_customize,
            'body_schema_toggle',
            array(
                'label'          => esc_html__( 'Body Schema Toggle', 'madara' ),
                'section'        => 'theme_layout',
                'settings'       => 'body_schema_toggle',
                'type'           => 'select',
                'description' => esc_html__('Show the Dark/Light Toggle button at bottom. This button will be hidden in Reading page as there is another default button in this page', 'madara'),
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
            'sidebar_size',
            array(
                'label'          => esc_html__( 'Sidebar Size', 'madara' ),
                'section'        => 'theme_layout',
                'settings'       => 'sidebar_size',
                'type'           => 'select',
                'description' => esc_html__('Set Sidebar Size', 'madara'),
                'choices' => array(
                    4 => esc_html__( '33.33% (4/12)', 'madara' ),
                    3 => esc_html__( '25% (3/12)', 'madara' ),
                    2 => esc_html__( '16.66% (2/12)', 'madara' ),
				),
            )
        )
    );

    $wp_customize->add_control(
        new Skyrocket_Image_Radio_Button_Custom_Control(
            $wp_customize,
            'main_top_sidebar_container',
            array(
                'label'          => esc_html__( 'Main Top Sidebar Container', 'madara' ),
                'section'        => 'theme_layout',
                'settings'       => 'main_top_sidebar_container',
                'description' => esc_html__('Set container for Main Top Sidebar. Custom width is 1760px', 'madara'),
                'choices' => array(
					'full_width' => array(
						'image' => get_parent_theme_file_uri( '/images/options/sidebar/sidebar-fullwidth.png' ),
						'name' => __( 'Full-Width', 'madara' )
					),
					'container' => array(
						'image' => get_parent_theme_file_uri( '/images/options/sidebar/sidebar-container.png' ),
						'name' => __( 'Container', 'madara' )
					),
					'custom_width' => array(
						'image' => get_parent_theme_file_uri( '/images/options/sidebar/sidebar-custom-width.png' ),
						'name' => __( 'Custom Width', 'madara' )
					)
				)
            )
        )
    );

    madara_customizer_register_background_controls($wp_customize, 'main_top_sidebar_background', 'Main Top Sidebar Background', 'theme_layout');

    $wp_customize->add_control(
        new WP_Customize_Control(
            $wp_customize,
            'main_top_sidebar_spacing',
            array(
                'label'          => esc_html__( 'Main Top Sidebar - Padding', 'madara' ),
                'section'        => 'theme_layout',
                'settings'       => 'main_top_sidebar_spacing',
                'type'           => 'text',
                'description' => esc_html__('Padding in Main Bottom Top. Default value is 50 0 20 0 & unit is px', 'madara')
            )
        )
    );

    $wp_customize->add_control(
        new Skyrocket_Image_Radio_Button_Custom_Control(
            $wp_customize,
            'main_top_second_sidebar_container',
            array(
                'label'          => esc_html__( 'Main Top Second Sidebar - Container', 'madara' ),
                'section'        => 'theme_layout',
                'settings'       => 'main_top_second_sidebar_container',
                'description' => esc_html__('Set container for Main Top Second Sidebar. Custom width is 1760px', 'madara'),
                'choices' => array(
					'full_width' => array(
						'image' => get_parent_theme_file_uri( '/images/options/sidebar/sidebar-fullwidth.png' ),
						'name' => __( 'Full-Width', 'madara' )
					),
					'container' => array(
						'image' => get_parent_theme_file_uri( '/images/options/sidebar/sidebar-container.png' ),
						'name' => __( 'Container', 'madara' )
					),
					'custom_width' => array(
						'image' => get_parent_theme_file_uri( '/images/options/sidebar/sidebar-custom-width.png' ),
						'name' => __( 'Custom Width', 'madara' )
					)
				)
            )
        )
    );
    
    madara_customizer_register_background_controls($wp_customize, 'main_top_second_sidebar_background', 'Main Top Second Sidebar Background', 'theme_layout');

    $wp_customize->add_control(
        new WP_Customize_Control(
            $wp_customize,
            'main_top_second_sidebar_spacing',
            array(
                'label'          => esc_html__( 'Main Top Second Sidebar - Padding', 'madara' ),
                'section'        => 'theme_layout',
                'settings'       => 'main_top_second_sidebar_spacing',
                'type'           => 'text',
                'description' => esc_html__('Padding in Main Top Second Sidebar. Default value is 50 0 20 0 & unit is px', 'madara')
            )
        )
    );

    $wp_customize->add_control(
        new Skyrocket_Image_Radio_Button_Custom_Control(
            $wp_customize,
            'main_bottom_sidebar_container',
            array(
                'label'          => esc_html__( 'Main Bottom Sidebar - Container', 'madara' ),
                'section'        => 'theme_layout',
                'settings'       => 'main_bottom_sidebar_container',
                'description' => esc_html__('Set container for Main Bottom Sidebar. Custom width is 1760px', 'madara'),
                'choices' => array(
					'full_width' => array(
						'image' => get_parent_theme_file_uri( '/images/options/sidebar/sidebar-fullwidth.png' ),
						'name' => __( 'Full-Width', 'madara' )
					),
					'container' => array(
						'image' => get_parent_theme_file_uri( '/images/options/sidebar/sidebar-container.png' ),
						'name' => __( 'Container', 'madara' )
					),
					'custom_width' => array(
						'image' => get_parent_theme_file_uri( '/images/options/sidebar/sidebar-custom-width.png' ),
						'name' => __( 'Custom Width', 'madara' )
					)
				)
            )
        )
    );

    madara_customizer_register_background_controls($wp_customize, 'main_bottom_sidebar_background', 'Main Bottom Sidebar Background', 'theme_layout');

    $wp_customize->add_control(
        new WP_Customize_Control(
            $wp_customize,
            'main_bottom_sidebar_spacing',
            array(
                'label'          => esc_html__( 'Main Bottom Sidebar - Padding', 'madara' ),
                'section'        => 'theme_layout',
                'settings'       => 'main_bottom_sidebar_spacing',
                'type'           => 'text',
                'description' => esc_html__('Padding in Main Bottom Sidebar. Default value is "50 0 20 0". The unit is pixel', 'madara')
            )
        )
    );

    madara_customizer_register_background_controls($wp_customize, 'login_popup_background', 'Login/Register Popup - Background', 'theme_layout');
}