<?php

function madara_customize_register_header( $wp_customize ) {
    $section = 'header';

    $wp_customize->add_control(
        new Skyrocket_Image_Radio_Button_Custom_Control(
            $wp_customize,
            'header_style',
            array(
                'label'          => esc_html__( 'Header Style', 'madara' ),
                'section'        => $section,
                'settings'       => 'header_style',
                'description' => esc_html__( 'Choose Header style. Custom width is 1760px', 'madara' ),
                'choices' => array(
					'1' => array(
						'image' => get_parent_theme_file_uri( '/images/options/header/header-container.png' ),
						'name' => __( 'Container', 'madara' )
					),
					'2' => array(
						'image' => get_parent_theme_file_uri( '/images/options/header/header-custom-width.png' ),
						'name' => __( 'Custom Width', 'madara' )
                    ),
                    '3' => array(
						'image' => get_parent_theme_file_uri( '/images/options/header/header-custom-width.png' ),
						'name' => __( 'Full Width', 'madara' )
					)
				)
            )
        )
    );

    $wp_customize->add_control(
        new WP_Customize_Control(
            $wp_customize,
            'nav_sticky',
            array(
                'label'          => esc_html__( 'Sticky Menu', 'madara' ),
                'section'        => $section,
                'settings'       => 'nav_sticky',
                'type'           => 'select',
                'description' => esc_html__( 'Enable/ Disable the Sticky Menu', 'madara' ),
                'choices' => array(
                    0 => esc_html__( 'Disable', 'madara' ),
                    1 => esc_html__( 'Always sticky', 'madara' ),
                    2 => esc_html__( 'When page is scrolled up', 'madara' )
				),
            )
        )
    );

    $wp_customize->add_control(
        new Skyrocket_Toggle_Switch_Custom_control(
            $wp_customize,
            'header_bottom_border',
            array(
                'label'          => esc_html__( 'Header Bottom - Border Bottom', 'madara' ),
                'section'        => $section,
                'settings'       => 'header_bottom_border',
                'type'           => 'select',
                'description' => esc_html__( 'Enable border bottom of the Header Bottom', 'madara' ),
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
            'header_disable_login_buttons',
            array(
                'label'          => esc_html__( 'Default Login Buttons', 'madara' ),
                'section'        => $section,
                'settings'       => 'header_disable_login_buttons',
                'type'           => 'select',
                'description' => esc_html__( 'In case you plan to use a custom Login/Register buttons somewhere else, you can hide the default button on header', 'madara' ),
                'choices' => array(
                    'off' => esc_html__( 'Off', 'madara' ),
                    'on' => esc_html__( 'On', 'madara' )
				),
            )
        )
    );
}