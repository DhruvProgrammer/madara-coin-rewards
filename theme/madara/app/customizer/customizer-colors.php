<?php

function madara_customize_register_colors( $wp_customize ) {
    $section = 'custom_colors';

    $wp_customize->add_control(
        new Skyrocket_Toggle_Switch_Custom_control(
            $wp_customize,
            'site_custom_colors',
            array(
                'label'          => esc_html__( 'Custom Colors', 'madara' ),
                'section'        => $section,
                'settings'       => 'site_custom_colors',
                'type'           => 'select',
                'description' => esc_html__('Show Custom Colors settings', 'madara'),
                'choices' => array(
                    'off' => esc_html__( 'Off', 'madara' ),
                    'on' => esc_html__( 'On', 'madara' )
				),
            )
        )
    );

    $arr_controls = array(    
        'main_color' => ['label' => esc_html__( 'Primary Color (Gradient - Start Color)', 'madara' ), 'desc' => esc_html__( 'Choose Primary Color of the theme (Gradient - Start Color). Default is: #eb3349', 'madara' )],
        'main_color_end' => ['label' => esc_html__( 'Primary Color (Gradient - End Color)', 'madara' ), 'desc' => esc_html__( 'Choose Primary Color of the theme (Gradient - End Color)', 'madara' )],
        'link_color_hover' => ['label' => esc_html__( 'Link Hover Color', 'madara' ), 'desc' => esc_html__( 'Choose Link Hover Color of the theme. Default is Primary Color', 'madara' )],
        'star_color' => ['label' => esc_html__( 'Star Color', 'madara' ), 'desc' => esc_html__( 'Choose Star Color rating in Manga Listing. Default is: #ffd900', 'madara' )],
        'hot_badges_bg_color' => ['label' => esc_html__( 'HOT Badges background color', 'madara' ), 'desc' => esc_html__( 'Choose Background Color for HOT Badges in Manga Listing', 'madara' )],
        'new_badges_bg_color' => ['label' => esc_html__( 'NEW Badges backgroundcolor', 'madara' ), 'desc' => esc_html__( 'Choose Background Color for NEW Badges in Manga Listing', 'madara' )],
        'custom_badges_bg_color' => ['label' => esc_html__( 'CUSTOM Badges backgroundcolor', 'madara' ), 'desc' => esc_html__( 'Choose Background Color for Custom Badges in Manga Listing', 'madara' )],
        'btn_bg' => ['label' => esc_html__( 'Button Background', 'madara' ), 'desc' => esc_html__( 'Choose default Background Color for Buttons', 'madara' )],
        'btn_color' => ['label' => esc_html__( 'Button Text Color', 'madara' ), 'desc' => esc_html__( 'Choose default Text Color for Buttons', 'madara' )],
        'btn_hover_bg' => ['label' => esc_html__( 'Button Background Hover Color', 'madara' ), 'desc' => esc_html__( 'Choose default Background Hover Color for Buttons', 'madara' )],
        'btn_hover_color' => ['label' => esc_html__( 'Button Text Hover Color', 'madara' ), 'desc' => esc_html__( 'Choose default Text Hover Color for Buttons', 'madara' )]);
    foreach($arr_controls as $control => $settings){
        $wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, $control,
            array(
                'label' => $settings['label'],
                'section' => $section,
                'settings'   => $control,
                'description' => $settings['desc']
            )
        ) );
    }

    $wp_customize->add_control(
        new Skyrocket_Toggle_Switch_Custom_control(
            $wp_customize,
            'header_custom_colors',
            array(
                'label'          => esc_html__( 'Customize Header Colors', 'madara' ),
                'section'        => $section,
                'settings'       => 'header_custom_colors',
                'type'           => 'select',
                'description' => esc_html__( 'Change various color settings on Header', 'madara' ),
                'choices' => array(
                    'off' => esc_html__( 'Off', 'madara' ),
                    'on' => esc_html__( 'On', 'madara' )
				),
            )
        )
    );

    $arr_controls = array(
        'nav_item_color' => ['label' => esc_html__( 'Navigation - Item Color', 'madara' ), 'desc' => esc_html__( 'Choose color for menu items on Navigation', 'madara' )],
        'nav_item_hover_color' => ['label' => esc_html__( 'Navigation - Item Hover Color', 'madara' ), 'desc' => esc_html__( 'Choose hover color for menu items on Navigation', 'madara' )],
        'nav_sub_bg' => ['label' => esc_html__( 'Navigation - Background Color For Sub Menu', 'madara' ), 'desc' => esc_html__( 'Choose background color for sub menu of Navigation', 'madara' )],
        'nav_sub_bg_border_color' => ['label' => esc_html__( 'Navigation - Sub Menu Item Border Color', 'madara' ), 'desc' => esc_html__( 'Choose color for sub menu item border color', 'madara' )],
        'nav_sub_item_color' => ['label' => esc_html__( 'Navigation - Sub Menu Item Color', 'madara' ), 'desc' => esc_html__( 'Choose color for sub menu item of Navigation', 'madara' )],
        'nav_sub_item_hover_color' => ['label' => esc_html__( 'Navigation - Sub Menu Item Hover Color', 'madara' ), 'desc' => esc_html__( 'Choose hover color for sub menu item of Navigation', 'madara' )],
        'nav_sub_item_hover_bg' => ['label' => esc_html__( 'Navigation - Sub Menu Item Hover Background Color', 'madara' ), 'desc' => esc_html__( 'Choose hover background color for sub menu item of Navigation', 'madara' )]);
    foreach($arr_controls as $control => $settings){
        $wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, $control,
            array(
                'label' => $settings['label'],
                'section' => $section,
                'settings'   => $control,
                'description' => $settings['desc']
            )
        ) );
    }

    $wp_customize->add_control(
        new Skyrocket_Toggle_Switch_Custom_control(
            $wp_customize,
            'header_bottom_custom_colors',
            array(
                'label'          => esc_html__( 'Customize Header Bottom Colors', 'madara' ),
                'section'        => $section,
                'settings'       => 'header_bottom_custom_colors',
                'type'           => 'select',
                'description' => esc_html__( 'Change various color settings on Header Bottom', 'madara' ),
                'choices' => array(
                    'off' => esc_html__( 'Off', 'madara' ),
                    'on' => esc_html__( 'On', 'madara' )
				),
            )
        )
    );
    
    $arr_controls = array(
        'header_bottom_bg' => ['label' => esc_html__( 'Header Bottom Background', 'madara' ), 'desc' => esc_html__( 'Choose background color for the Header Bottom', 'madara' )],
        'bottom_nav_item_color' => ['label' => esc_html__( 'Second Navigation - Item Color', 'madara' ), 'desc' => esc_html__( 'Choose color for menu items on Navigation', 'madara' )],
        'bottom_nav_item_hover_color' => ['label' => esc_html__( 'Second Navigation - Item Hover Color', 'madara' ), 'desc' => esc_html__( 'Choose hover color for menu items on Second Navigation', 'madara' )],
        'bottom_nav_sub_bg' => ['label' => esc_html__( 'Second Navigation - Background Color For Sub Menu', 'madara' ), 'desc' => esc_html__( 'Choose background color for sub menu of Second Navigation', 'madara' )],
        'bottom_nav_sub_item_color' => ['label' => esc_html__( 'Second Navigation - Sub Menu Item Color', 'madara' ), 'desc' => esc_html__( 'Choose color for sub menu item of Second Navigation', 'madara' )],
        'bottom_nav_sub_item_hover_color' => ['label' => esc_html__( 'Second Navigation - Sub Menu Item Hover Color', 'madara' ), 'desc' => esc_html__( 'Choose hover color for sub menu item of Second Navigation', 'madara' )],
        'bottom_nav_sub_border_bottom' => ['label' => esc_html__( 'Second Navigation - Border Bottom Color For Sub Menu', 'madara' ), 'desc' => esc_html__( 'Choose border bottom color for sub menu of Second Navigation. Default is Primary Color', 'madara' )]);

    foreach($arr_controls as $control => $settings){
        $wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, $control,
            array(
                'label' => $settings['label'],
                'section' => $section,
                'settings'   => $control,
                'description' => $settings['desc']
            )
        ) );
    }

    $wp_customize->add_control(
        new Skyrocket_Toggle_Switch_Custom_control(
            $wp_customize,
            'mobile_menu_custom_color',
            array(
                'label'          => esc_html__( 'Mobile Menu Custom Color', 'madara' ),
                'section'        => $section,
                'settings'       => 'mobile_menu_custom_color',
                'type'           => 'select',
                'description' => esc_html__( 'Change various color settings on Mobile Menu', 'madara' ),
                'choices' => array(
                    'off' => esc_html__( 'Off', 'madara' ),
                    'on' => esc_html__( 'On', 'madara' )
                ),
            )
        )
    );
    
    $arr_controls = array(
        'mobile_browser_header_color' => ['label' => esc_html__( 'Mobile Browser Header Color', 'madara' ), 'desc' => esc_html__( 'Change header color on Mobile Browser Header', 'madara' )],
        'canvas_menu_background' => ['label' => esc_html__( 'Canvas Menu - Background', 'madara' ), 'desc' => esc_html__( 'Set Background Color of Canvas Menu', 'madara' )],
        'canvas_menu_color' => ['label' => esc_html__( 'Canvas Menu - Menu Item Color', 'madara' ), 'desc' => esc_html__( 'Set Color of Item of Canvas Menu', 'madara' )],
        'canvas_menu_hover' => ['label' => esc_html__( 'Canvas Menu - Menu Item Hover Color', 'madara' ), 'desc' => esc_html__( 'Set Hover Color of Item of Canvas Menu', 'madara' )]
    );

    foreach($arr_controls as $control => $settings){
        $wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, $control,
            array(
                'label' => $settings['label'],
                'section' => $section,
                'settings'   => $control,
                'description' => $settings['desc']
            )
        ) );
    }
}