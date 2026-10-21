<?php
add_action( 'customize_register', 'madara_customize_register_manga_general' );
function madara_customize_register_manga_general( $wp_customize ) {
    $section = 'manga_general_layout';

    $wp_customize->add_section( $section , array(
        'title'      => esc_html__( 'Manga General Layout', 'madara' ),
        'priority'   => 17,
    ) );

    $arr_settings = array(
        'manga_main_top_sidebar_container' => 'container',
        'manga_main_top_sidebar_spacing' => '',
        'manga_main_top_second_sidebar_container' => 'container',
        'manga_main_top_second_sidebar_spacing' => '',
        'manga_main_bottom_sidebar_container' => 'container',
        'manga_main_bottom_sidebar_spacing' => ''
    );

    $arr_settings = array_merge($arr_settings, madara_background_control_properties('manga_main_top_sidebar_background'));
    $arr_settings = array_merge($arr_settings, madara_background_control_properties('manga_main_top_second_sidebar_background'));
    $arr_settings = array_merge($arr_settings, madara_background_control_properties('manga_main_bottom_sidebar_background'));
   
    foreach($arr_settings as $key => $def_value){
        $wp_customize->add_setting( $key , array(
            'default'   => $def_value,
            'transport' => 'refresh',
        ) );
    };

    $wp_customize->add_control(
        new Skyrocket_Image_Radio_Button_Custom_Control(
            $wp_customize,
            'manga_main_top_sidebar_container',
            array(
                'label'          => esc_html__( 'Manga Main Top Sidebar Container', 'madara' ),
                'section'        => $section,
                'settings'       => 'manga_main_top_sidebar_container',
                'type'           => 'select',
                'description' => esc_html__( 'Set container for Manga Main Top Sidebar. Custom width is 1760px', 'madara' ),
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
    
    madara_customizer_register_background_controls($wp_customize, 'manga_main_top_sidebar_background', esc_html__( 'Manga Main Top Sidebar Background', 'madara' ), $section);


    $wp_customize->add_control(
        new WP_Customize_Control(
            $wp_customize,
            'manga_main_top_sidebar_spacing',
            array(
                'label'          => esc_html__( 'Manga Main Top Sidebar - Padding', 'madara' ),
                'section'        => $section,
                'settings'       => 'manga_main_top_sidebar_spacing',
                'type'           => 'text',
                'description' => esc_html__( 'Padding in Manga Main Top Sidebar. Default value is 50 0 20 0 & unit is px', 'madara' ),
            )
        )
    );    

    $wp_customize->add_control(
        new Skyrocket_Image_Radio_Button_Custom_Control(
            $wp_customize,
            'manga_main_top_second_sidebar_container',
            array(
                'label'          => esc_html__( 'Manga Main Top Second Sidebar Container', 'madara' ),
                'section'        => $section,
                'settings'       => 'manga_main_top_second_sidebar_container',
                'type'           => 'select',
                'description' => esc_html__( 'Set container for Manga Main Top Second Sidebar. Custom width is 1760px', 'madara' ),
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

    madara_customizer_register_background_controls($wp_customize, 'manga_main_top_second_sidebar_background', esc_html__( 'Manga Main Top Second Sidebar Background', 'madara' ), $section);
    
    $wp_customize->add_control(
        new WP_Customize_Control(
            $wp_customize,
            'manga_main_top_second_sidebar_spacing',
            array(
                'label'          => esc_html__( 'Manga Main Top Second Sidebar - Padding', 'madara' ),
                'section'        => $section,
                'settings'       => 'manga_main_top_second_sidebar_spacing',
                'type'           => 'text',
                'description' => esc_html__( 'Padding in Manga Main Top Second Sidebar. Default value is 50 0 20 0 & unit is px', 'madara' ),
            )
        )
    );

    $wp_customize->add_control(
        new Skyrocket_Image_Radio_Button_Custom_Control(
            $wp_customize,
            'manga_main_bottom_sidebar_container',
            array(
                'label'          => esc_html__( 'Manga Main Bottom Sidebar Container', 'madara' ),
                'section'        => $section,
                'settings'       => 'manga_main_bottom_sidebar_container',
                'type'           => 'select',
                'description' => esc_html__( 'Set container for Manga Main Bottom Sidebar. Custom width is 1760px', 'madara' ),
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

    madara_customizer_register_background_controls($wp_customize, 'manga_main_bottom_sidebar_background', esc_html__( 'Manga Main Bottom Sidebar Background', 'madara' ), $section);


    $wp_customize->add_control(
        new WP_Customize_Control(
            $wp_customize,
            'manga_main_bottom_sidebar_spacing',
            array(
                'label'          => esc_html__( 'Manga Main Bottom Sidebar - Padding', 'madara' ),
                'section'        => $section,
                'settings'       => 'manga_main_bottom_sidebar_spacing',
                'type'           => 'text',
                'description' => esc_html__( 'Padding in Manga Main Bottom Sidebar. Default value is 50 0 20 0 & unit is px', 'madara' ),
            )
        )
    );

}