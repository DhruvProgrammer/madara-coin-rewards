<?php

function madara_customize_register_page( $wp_customize ) {
    $section = 'single_page';

    $wp_customize->add_control(
        new Skyrocket_Image_Radio_Button_Custom_Control(
            $wp_customize,
            'page_sidebar',
            array(
                'label'          => esc_html__( 'Single Page Sidebar', 'madara' ),
                'section'        => $section,
                'settings'       => 'page_sidebar',
                'description' => '',
                'choices' => array(
					'left' => array(
						'image' => get_parent_theme_file_uri( '/images/options/sidebar/sidebar-left.png' ),
						'name' => __( 'Left', 'madara' )
					),
					'right' => array(
						'image' => get_parent_theme_file_uri( '/images/options/sidebar/sidebar-right.png' ),
						'name' => __( 'Right', 'madara' )
                    ),
                    'full' => array(
						'image' => get_parent_theme_file_uri( '/images/options/sidebar/sidebar-fullwidth.png' ),
						'name' => __( 'Full', 'madara' )
					)
				)
            )
        )
    );

    $wp_customize->add_control(
        new Skyrocket_Toggle_Switch_Custom_control(
            $wp_customize,
            'page_meta_tags',
            array(
                'label'          => esc_html__( 'Enable Page Meta Tags', 'madara' ),
                'section'        => $section,
                'settings'       => 'page_meta_tags',
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
            'page_comments',
            array(
                'label'          => esc_html__( 'Enable Comments', 'madara' ),
                'section'        => $section,
                'settings'       => 'page_comments',
                'type'           => 'select',
                'description' => '',
                'choices' => array(
                    'off' => esc_html__( 'Off', 'madara' ),
                    'on' => esc_html__( 'On', 'madara' )
				),
            )
        )
    );
}