<?php

function madara_customize_register_post( $wp_customize ) {
    $section = 'single_post';

    $wp_customize->add_control(
        new Skyrocket_Image_Radio_Button_Custom_Control(
            $wp_customize,
            'single_sidebar',
            array(
                'label'          => esc_html__( 'Single Post Sidebar', 'madara' ),
                'section'        => $section,
                'settings'       => 'single_sidebar',
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
            'single_excerpt',
            array(
                'label'          => esc_html__( 'Post Excerpt', 'madara' ),
                'section'        => $section,
                'settings'       => 'single_excerpt',
                'type'           => 'select',
                'description' => esc_html__( 'Show Post Excerpt', 'madara' ),
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
            'single_featured_image',
            array(
                'label'          => esc_html__( 'Featured Image', 'madara' ),
                'section'        => $section,
                'settings'       => 'single_featured_image',
                'type'           => 'select',
                'description' => esc_html__( 'Show (fullsize) Featured Image', 'madara' ),
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
            'single_tags',
            array(
                'label'          => esc_html__( 'Tags', 'madara' ),
                'section'        => $section,
                'settings'       => 'single_tags',
                'type'           => 'select',
                'description' => esc_html__( 'Show Tags list', 'madara' ),
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
            'post_meta_tags',
            array(
                'label'          => esc_html__( 'Enable Post Meta', 'madara' ),
                'section'        => $section,
                'settings'       => 'post_meta_tags',
                'type'           => 'select',
                'description' => esc_html__( 'Show Post "Posted-On Date" and "Post Categories"', 'madara' ),
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
            'single_category',
            array(
                'label'          => esc_html__( 'Post Category', 'madara' ),
                'section'        => $section,
                'settings'       => 'single_category',
                'type'           => 'select',
                'description' => esc_html__( 'Show Category list', 'madara' ),
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
            'enable_comment',
            array(
                'label'          => esc_html__( 'Enable Comments', 'madara' ),
                'section'        => $section,
                'settings'       => 'enable_comment',
                'type'           => 'select',
                'description' => esc_html__( 'You can disable Comments Form in single post only', 'madara' ),
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
            'single_reverse_nav',
            array(
                'label'          => esc_html__( 'Reverse Navigation Links', 'madara' ),
                'section'        => $section,
                'settings'       => 'single_reverse_nav',
                'type'           => 'select',
                'description' => esc_html__( 'By default, in LTR language, Next Button is on the left, while Previous Button is on the right. If this option is turned on, then Next Button will be on the right, while the Previous Button is on the left', 'madara' ),
                'choices' => array(
                    'off' => esc_html__( 'Off', 'madara' ),
                    'on' => esc_html__( 'On', 'madara' )
				),
            )
        )
    );
}