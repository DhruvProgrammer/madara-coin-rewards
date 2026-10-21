<?php

function madara_customize_register_general( $wp_customize ) {
    $section = 'madara_general';

    $wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize, 'logo_image', array(
        'label'      => esc_html__( 'Logo Image', 'madara' ),
        'section'    => $section,
        'settings'   => 'logo_image',
        'description' => esc_html__('Upload your logo image', 'madara')
    ) ) );

    $wp_customize->add_control(
        new WP_Customize_Control(
            $wp_customize,
            'logo_image_size',
            array(
                'label'          => esc_html__( 'Logo Size (width x height)', 'madara' ),
                'section'        => $section,
                'settings'       => 'logo_image_size',
                'type'           => 'text',
                'description' => esc_html__('(optional) Specify your logo width & height. This may help to improve Google Pagespeed Insights value. For example 230x140', 'madara')
            )
        )
    );

    $wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize, 'retina_logo_image', array(
        'label'      => esc_html__( 'Retina Logo (optional)', 'madara' ),
        'section'    => $section,
        'settings'   => 'retina_logo_image',
        'description' => esc_html__('Retina logo should be two time bigger than the custom logo. Retina Logo is optional, use this setting if you want to strictly support retina devices.', 'madara')
    ) ) );

    $wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize, 'login_logo_image', array(
        'label'      => esc_html__( 'Logo Image', 'madara' ),
        'section'    => $section,
        'settings'   => 'login_logo_image',
        'description' => esc_html__('Upload your Admin Login logo image', 'madara')
    ) ) );
}