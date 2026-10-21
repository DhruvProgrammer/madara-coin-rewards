<?php

function madara_customize_register_fonts( $wp_customize ) {
    $section = 'custom_fonts';

    $wp_customize->add_control(
        new Skyrocket_Toggle_Switch_Custom_control(
            $wp_customize,
            'font_using_custom',
            array(
                'label'          => esc_html__( 'Custom Font Settings', 'madara' ),
                'section'        => $section,
                'settings'       => 'font_using_custom',
                'type'           => 'select',
                'description' => esc_html__( 'Customize default Font Settings', 'madara' ),
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
            'main_font_on_google',
            array(
                'label'          => esc_html__( 'Use Google Font for Main Font', 'madara' ),
                'section'        => $section,
                'settings'       => 'main_font_on_google',
                'type'           => 'select',
                'description' => esc_html__( 'If you use Google Font for Main Font Family, turn this on', 'madara' ),
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
        'main_font_google_family',
        array(
            'label'          => esc_html__( 'Main Font - Google Font', 'madara' ),
            'section'        => $section,
            'settings'       => 'main_font_google_family',
            'type'           => 'text',
            'description' => esc_html__( 'Enter Google Fonts for Main Font', 'madara' ),
        )
    ));


    $wp_customize->add_control(
    new WP_Customize_Control(
        $wp_customize,
        'main_font_family',
        array(
            'label'          => esc_html__( 'Main Font - Custom Font Family', 'madara' ),
            'section'        => $section,
            'settings'       => 'main_font_family',
            'type'           => 'text',
            'description' => esc_html__( 'Enter name of your custom font family here', 'madara' )
        )
    ));

    $wp_customize->add_control(
    new Skyrocket_Slider_Custom_Control($wp_customize,
        'main_font_size',
        array(
            'label'          => esc_html__( 'Main Font Size', 'madara' ),
            'section'        => $section,
            'settings'       => 'main_font_size',
            'type'           => 'slider_control',
            'input_attrs' => array(
                'min' => 10,
                'max' => 20,
                'step' => 1,
            ),
            'description' => esc_html__( 'Choose Font Size. Default is 14px', 'madara' ),
        )
    ));

        $wp_customize->add_control(
            new WP_Customize_Control(
                $wp_customize,
                'main_font_weight',
                array(
                    'label'          => esc_html__( 'Main Font Weight', 'madara' ),
                    'section'        => $section,
                    'settings'       => 'main_font_weight',
                    'type'           => 'select',
                    'description' => esc_html__( 'Choose Font Weight.', 'madara' ),
                    'choices' => array(
                        'normal' => esc_html__( 'Normal', 'madara' ),
                        'bold' => esc_html__( 'Bold', 'madara' ),
                        'bold' => esc_html__( 'Bold', 'madara' ),
                        'bolder' => esc_html__( 'Bolder', 'madara' ),
                        'bold' => esc_html__( 'Bold', 'madara' ),
                        'initial' => esc_html__( 'Initial', 'madara' ),
                        'lighter' => esc_html__( 'Lighter', 'madara' ),
                        '100' => esc_html__( '100', 'madara' ),
                        '200' => esc_html__( '200', 'madara' ),
                        '300' => esc_html__( '300', 'madara' ),
                        '400' => esc_html__( '400', 'madara' ),
                        '500' => esc_html__( '500', 'madara' ),
                        '600' => esc_html__( '600', 'madara' ),
                        '700' => esc_html__( '700', 'madara' ),
                        '800' => esc_html__( '800', 'madara' ),
                        '900' => esc_html__( '900', 'madara' ),
                    ),
                )
            )
        );
    
    $wp_customize->add_control(
        new Skyrocket_Slider_Custom_Control($wp_customize,
            'main_font_line_height',
            array(
                'label'          => esc_html__( 'Main Font Line Height', 'madara' ),
                'section'        => $section,
                'settings'       => 'main_font_line_height',
                'type'           => 'slider_control',
                'input_attrs' => array(
                    'min' => 1,
                    'max' => 3,
                    'step' => 0.1,
                ),
                'description' => esc_html__( 'Choose Font Line Height. Default is 1.5', 'madara' ),
            )
    ));

    $wp_customize->add_control(
        new Skyrocket_Toggle_Switch_Custom_control(
            $wp_customize,
            'heading_font_on_google',
            array(
                'label'          => esc_html__( 'Use Google Font for Heading Font', 'madara' ),
                'section'        => $section,
                'settings'       => 'heading_font_on_google',
                'type'           => 'select',
                'description' => esc_html__( 'If you use Google Font for Heading Font Family, turn this on', 'madara' ),
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
            'heading_font_google_family',
            array(
                'label'          => esc_html__( 'Heading Font - Google Font', 'madara' ),
                'section'        => $section,
                'settings'       => 'heading_font_google_family',
                'type'           => 'text',
                'description' => esc_html__( 'Heading Font is used for all heading tags (ie. H1, H2, H3, H4, H5, H6)', 'madara' ),
            )
    ));

    $wp_customize->add_control(
        new WP_Customize_Control(
            $wp_customize,
            'heading_font_family',
            array(
                'label'          => esc_html__( 'Heading Font - Custom Font Family', 'madara' ),
                'section'        => $section,
                'settings'       => 'heading_font_family',
                'type'           => 'text',
                'description' => esc_html__( 'Heading Font is used for all heading tags (ie. H1, H2, H3, H4, H5, H6). Enter name of font family here', 'madara' ),
            )
    ));

    for($i = 1; $i <= 6; $i++){
        $wp_customize->add_control(
            new Skyrocket_Slider_Custom_Control($wp_customize,
                'heading_font_size_h'.$i,
                array(
                    'label'          => esc_html__( 'H'.$i.' - Font Size', 'madara' ),
                    'section'        => $section,
                    'settings'       => 'heading_font_size_h'.$i,
                    'type'           => 'slider_control',
                    'input_attrs' => array(
                        'min' => 8,
                        'max' => 80,
                        'step' => 1,
                    ),
                    'description' => esc_html__( 'Choose font size for H'.$i.'. Default is 34px', 'madara' ),
                )
        ));
    
        $wp_customize->add_control(
            new Skyrocket_Slider_Custom_Control($wp_customize,
                'h'.$i.'_line_height',
                array(
                    'label'          => esc_html__( 'H'.$i.' - Line Height', 'madara' ),
                    'section'        => $section,
                    'settings'       => 'h'.$i.'_line_height',
                    'type'           => 'slider_control',
                    'input_attrs' => array(
                        'min' => 1,
                        'max' => 3,
                        'step' => 0.1,
                    ),
                    'description' => esc_html__( 'Choose H'.$i.' Line Height.  Default is 1.2em', 'madara' ),
                )
        ));
    
        $wp_customize->add_control(
            new Skyrocket_Slider_Custom_Control($wp_customize,
                'h'.$i.'_font_weight',
                array(
                    'label'          => esc_html__( 'H'.$i.' - Font Weight', 'madara' ),
                    'section'        => $section,
                    'settings'       => 'h'.$i.'_font_weight',
                    'type'           => 'slider_control',
                    'input_attrs' => array(
                        'min' => 100,
                        'max' => 900,
                        'step' => 100,
                    ),
                    'description' => esc_html__( 'Choose H'.$i.' Font Weight', 'madara' ),
                )
        ));
    }

    $wp_customize->add_control(
        new Skyrocket_Toggle_Switch_Custom_control(
            $wp_customize,
            'navigation_font_on_google',
            array(
                'label'          => esc_html__( 'Use Google Font for Navigation', 'madara' ),
                'section'        => $section,
                'settings'       => 'navigation_font_on_google',
                'type'           => 'select',
                'description' => esc_html__( 'If you use Google Font for Navigation Items, turn this on', 'madara' ),
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
            'navigation_font_google_family',
            array(
                'label'          => esc_html__( 'Navigation - Google Font', 'madara' ),
                'section'        => $section,
                'settings'       => 'navigation_font_google_family',
                'type'           => 'text',
                'description' => esc_html__( 'Choose font to be used for Navigation Items', 'madara' ),
            )
    ));

    $wp_customize->add_control(
        new WP_Customize_Control(
            $wp_customize,
            'navigation_font_family',
            array(
                'label'          => esc_html__( 'Navigation - Custom Font Family', 'madara' ),
                'section'        => $section,
                'settings'       => 'navigation_font_family',
                'type'           => 'text',
                'description' => esc_html__( 'Enter name of font family to be used for Navigation Items', 'madara' ),
            )
    ));

    $wp_customize->add_control(
        new Skyrocket_Slider_Custom_Control($wp_customize,
            'navigation_font_size',
            array(
                'label'          => esc_html__( 'Navigation - Font Size', 'madara' ),
                'section'        => $section,
                'settings'       => 'navigation_font_size',
                'type'           => 'slider_control',
                'input_attrs' => array(
                    'min' => 10,
                    'max' => 26,
                    'step' => 1,
                ),
                'description' => esc_html__( 'Choose font size for Navigation Items. Default is 14px', 'madara' ),
            )
    ));

    $wp_customize->add_control(
        new Skyrocket_Slider_Custom_Control($wp_customize,
            'navigation_font_weight',
            array(
                'label'          => esc_html__( 'Navigation - Font Weight', 'madara' ),
                'section'        => $section,
                'settings'       => 'navigation_font_weight',
                'type'           => 'slider_control',
                'input_attrs' => array(
                    'min' => 100,
                    'max' => 900,
                    'step' => 100,
                ),
                'description' => esc_html__( 'Choose Font Weight', 'madara' ),
            )
    ));

    $wp_customize->add_control(
        new Skyrocket_Toggle_Switch_Custom_control(
            $wp_customize,
            'meta_font_on_google',
            array(
                'label'          => esc_html__( 'Use Google Font for Meta Font', 'madara' ),
                'section'        => $section,
                'settings'       => 'meta_font_on_google',
                'type'           => 'select',
                'description' => esc_html__( 'If you use Google Font for Meta Font Family, turn this on', 'madara' ),
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
            'meta_font_google_family',
            array(
                'label'          => esc_html__( 'Meta Font Family', 'madara' ),
                'section'        => $section,
                'settings'       => 'meta_font_google_family',
                'type'           => 'text',
                'description' => esc_html__( 'Meta Font is used for all meta tags', 'madara' ),
            )
    ));

    $wp_customize->add_control(
        new WP_Customize_Control(
            $wp_customize,
            'meta_font_family',
            array(
                'label'          => esc_html__( 'Meta Font Family', 'madara' ),
                'section'        => $section,
                'settings'       => 'meta_font_family',
                'type'           => 'text',
                'description' => esc_html__( 'Meta Font is used for all meta tags. Enter name of font family here', 'madara' ),
            )
    ));

    for($i = 1; $i <= 3; $i++){
        $wp_customize->add_control(
            new WP_Customize_Control(
                $wp_customize,
                'custom_font_' . $i,
                array(
                    'label'          => esc_html__( 'Custom Font ' . $i, 'madara' ),
                    'section'        => $section,
                    'settings'       => 'custom_font_' . $i,
                    'type'           => 'text',
                    'description' => esc_html__( 'Upload your own font and enter name "custom_font_' . $i . '" in "Main Font Family or Special Font Family" setting above', 'madara' ),
                )
        ));
    }
}