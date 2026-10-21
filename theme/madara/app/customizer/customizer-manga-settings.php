<?php
add_action( 'customize_register', 'madara_customize_register_manga_settings' );
function madara_customize_register_manga_settings( $wp_customize ) {
    $section = 'manga_general';

    $wp_customize->add_section( $section , array(
        'title'      => esc_html__( 'Manga General Settings', 'madara' ),
        'priority'   => 16,
    ) );

    $arr_settings = array(
        'manga_adult_content' => 'off',
        'manga_hover_details' => 'off',
        'manga_new_chapter' => 'off',
        'manga_new_chapter_time_range' => 3,
        'manga_reader_setting' => 'on',
        'manga_bookmark_list_orderby' => '',
        'manga_bookmark_list_order' => 'oldest_first'
    );

    foreach($arr_settings as $key => $def_value){
        $wp_customize->add_setting( $key , array(
            'default'   => $def_value,
            'transport' => 'refresh',
        ) );
    };

    $wp_customize->add_control(
        new Skyrocket_Toggle_Switch_Custom_control(
            $wp_customize,
            'manga_adult_content',
            array(
                'label'          => esc_html__( 'Family Safe button', 'madara' ),
                'section'        => $section,
                'settings'       => 'manga_adult_content',
                'type'           => 'select',
                'description' => esc_html__( 'Show to "Family Safe" button. This will allow visitors to turn on/off Adult content on your site. If this button is ON, then by default, adult content will be filtered out', 'madara' ),
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
            'manga_hover_details',
            array(
                'label'          => esc_html__( 'Manga Hover Details', 'madara' ),
                'section'        => $section,
                'settings'       => 'manga_hover_details',
                'type'           => 'select',
                'description' => esc_html__( 'Show manga details when manga item in Manga Listing hoverd', 'madara' ),
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
            'manga_new_chapter',
            array(
                'label'          => esc_html__( 'Manga New Chapter Tag', 'madara' ),
                'section'        => $section,
                'settings'       => 'manga_new_chapter',
                'type'           => 'select',
                'description' => esc_html__( 'Display "New" tag for the new chapter', 'madara' ),
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
            'manga_new_chapter_time_range',
            array(
                'label'          => esc_html__( 'New Chapter - Time Range', 'madara' ),
                'section'        => $section,
                'settings'       => 'manga_new_chapter_time_range',
                'type'           => 'select',
                'description' => esc_html__( 'The time range for set "New" tag from the time the chapter is uploaded', 'madara' ),
                'choices' => array(
                    3 => esc_html__( '3 Days', 'madara' ),
                    7 => esc_html__( '7 Days', 'madara' ),
                    15 => esc_html__( '15 Days', 'madara' ),
                    30 => esc_html__( '30 Days', 'madara' ),
				),
            )
        )
    );

    $wp_customize->add_control(
        new Skyrocket_Toggle_Switch_Custom_control(
            $wp_customize,
            'manga_reader_setting',
            array(
                'label'          => esc_html__( 'Reader Settings', 'madara' ),
                'section'        => $section,
                'settings'       => 'manga_reader_setting',
                'type'           => 'select',
                'description' => esc_html__( 'Enable "Reading Settings" tab in User Settings dashboard', 'madara' ),
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
            'manga_bookmark_list_orderby',
            array(
                'label'          => esc_html__( 'Manga Bookmark List - Order By', 'madara' ),
                'section'        => $section,
                'settings'       => 'manga_bookmark_list_orderby',
                'type'           => 'select',
                'description' => esc_html__( 'By default, Bookmarked List items are ordered by the time an item is added to the list', 'madara' ),
                'choices' => array(
                    '' => esc_html__( 'Default (bookmarked time)', 'madara' ),
                    'update' => esc_html__( 'Manga Latest Update time', 'madara')
				),
            )
        )
    );

    $wp_customize->add_control(
        new WP_Customize_Control(
            $wp_customize,
            'manga_bookmark_list_order',
            array(
                'label'          => esc_html__( 'Manga Bookmark List - Order', 'madara' ),
                'section'        => $section,
                'settings'       => 'manga_bookmark_list_order',
                'type'           => 'select',
                'description' => esc_html__( 'Order of the items in the Bookmark List in User Settings page', 'madara' ),
                'choices' => array(
                    'oldest_first' => esc_html__( 'Oldest First', 'madara' ),
                    'newest_first' => esc_html__( 'Newest First', 'madara' )
				),
            )
        )
    );
}