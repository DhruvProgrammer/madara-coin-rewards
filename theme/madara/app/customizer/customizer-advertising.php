<?php

function madara_customize_register_advertising( $wp_customize ) {
    $section = 'advertising';

    $wp_customize->add_control(
        new WP_Customize_Control(
            $wp_customize,
            'adsense_id',
            array(
                'label'          => esc_html__( 'Google AdSense Publisher ID', 'madara' ),
                'section'        => $section,
                'settings'       => 'adsense_id',
                'type'           => 'text',
                'description' => esc_html__( 'Enter your Google AdSense Publisher ID', 'madara' ),
            )
        )
    );

    $ad_slots = array(
        'ads_before_content' => 'Before of content Ads',
        'ads_after_content'  => 'After of content Ads',
        'ads_footer'         => 'Footer Ads',
        'ads_wall_left'      => 'Wall Ads Left',
        'ads_wall_right'     => 'Wall Ads Right',
    );

    $ad_slots = apply_filters( 'madara_ad_slots', $ad_slots );

    foreach ( $ad_slots as $slot => $name ) {
        $wp_customize->add_control(
            new WP_Customize_Control(
                $wp_customize,
                'adsense_slot_' . $slot,
                array(
                    'label'          => sprintf( esc_html__( '%s - AdSense Ads Slot ID', 'madara' ), $name ),
                    'section'        => $section,
                    'settings'       => 'adsense_slot_' . $slot,
                    'type'           => 'text',
                    'description' => sprintf( esc_html__( 'If you want to display %s, enter Google AdSense Ad Slot ID here. If left empty, "%s - Custom Code" will be used', 'madara' ), $name, $name ),
                )
            )
        );

        $wp_customize->add_control(
            new WP_Customize_Control(
                $wp_customize,
                $slot,
                array(
                    'label'          => sprintf( esc_html__( '%s - Custom Code', 'madara' ), $name ),
                    'section'        => $section,
                    'settings'       => $slot,
                    'type'           => 'textarea',
                    'description' => sprintf( esc_html__( 'Enter custom code for %s position', 'madara' ), $name ),
                )
            )
        );
    }

    $wp_customize->add_control(
        new WP_Customize_Control(
            $wp_customize,
            'ads_wall_scrolltop',
            array(
                'label'          => esc_html__( 'Wall Ads Scroll Top', 'madara' ),
                'section'        => $section,
                'settings'       => 'ads_wall_scrolltop',
                'type'           => 'text',
                'description' => esc_html__( 'The space between the wall ads and the top window. This value can be overriden in each page or manga', 'madara' ),
            )
        )
    );

    $wp_customize->add_control(
        new WP_Customize_Control(
            $wp_customize,
            'ads_wall_maxtop',
            array(
                'label'          => esc_html__( 'Wall Ads Max Top', 'madara' ),
                'section'        => $section,
                'settings'       => 'ads_wall_maxtop',
                'type'           => 'text',
                'description' => esc_html__( 'When you scroll to the top of the window, the wall ads should not pass this horizontal line. This value can be overriden in each page or manga', 'madara' ),
            )
        )
    );
}