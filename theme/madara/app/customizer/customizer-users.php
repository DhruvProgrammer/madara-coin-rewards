<?php

function madara_customize_register_users( $wp_customize ) {
    $section = 'user_settings';

    $wp_customize->add_control(
        new Skyrocket_Toggle_Switch_Custom_control(
            $wp_customize,
            'user_settings_weak_password',
            array(
                'label'          => esc_html__('Require strong Password','madara'),
                'section'        => $section,
                'settings'       => 'user_settings_weak_password',
                'type'           => 'select',
                'description' => esc_html__('Force user to use strong password','madara'),
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
            'amp_fontawesome_key',
            array(
                'label'          => esc_html__( 'FontAwesome Key', 'madara' ),
                'section'        => $section,
                'settings'       => 'amp_fontawesome_key',
                'type'           => 'text',
                'description' => esc_html__( 'In an AMP link, local lib for Font Icons cannot be loaded. Thus, we need to load it from FontAwesome CDN. Register your email here: https://fontawesome.com/start and get the Key', 'madara' ),
            )
        )
    );

    $wp_customize->add_control(
        new WP_Customize_Control(
            $wp_customize,
            'amp_image_height',
            array(
                'label'          => esc_html__( 'Image Height (in px)', 'madara' ),
                'section'        => $section,
                'settings'       => 'amp_image_height',
                'type'           => 'text',
                'description' => esc_html__( 'In an AMP link, images of a chapter should have same height. You can specify the height of images here for better display. You can set this value in each Manga and Chapter as well', 'madara' ),
            )
        )
    );

    $wp_customize->add_control(
        new WP_Customize_Control(
            $wp_customize,
            'amp_manga_reading_style',
            array(
                'label'          => esc_html__( 'Chapter Reading in List or Slides mode', 'madara' ),
                'section'        => $section,
                'settings'       => 'amp_manga_reading_style',
                'type'           => 'select',
                'description' => esc_html__( 'For Manga Chapter (Images) Reading page, use Images Listing or Slides mode', 'madara' ),
                'choices' => array(
                    'list' => esc_html__( 'List', 'madara' ),
                    'slides' => esc_html__( 'Slides', 'madara' )
				),
            )
        )
    );
}