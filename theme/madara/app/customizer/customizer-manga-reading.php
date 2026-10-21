<?php
add_action( 'customize_register', 'madara_customize_register_manga_reading' );
function madara_customize_register_manga_reading( $wp_customize ) {
    $section = 'manga_reading';

    $wp_customize->add_section( $section , array(
        'title'      => esc_html__( 'Manga Reading Page', 'madara' ),
        'priority'   => 19,
    ) );

    $arr_settings = array(
        'manga_reading_discussion' => '',
        'manga_reading_discussion_heading' => '',
        'manga_reading_page_sidebar' => '',
        'manga_reading_text_sidebar' => '',
        'chapter_heading' => 'full',
        'minimal_reading_page' => 'on',
        'reading_page_breadcrumbs' => 'on',
        'reading_page_floating_nav' => 'off',
        'reading_page_action_icons' => 'on',
        'manga_reading_text_fontsize' => '',
        'manga_reading_style' => '',
        'manga_chapters_select_order' => '',
        'manga_reading_content_gaps' => '',
        'manga_reading_images_per_page' => '',
        'manga_reading_full_width' => '',
        'manga_reading_related' => '',
        'manga_page_reading_ajax' => '',
        'manga_reading_preload_images' => '',
        'manga_reading_sticky_header' => '',
        'manga_reading_sticky_navigation' => '',
        'manga_reading_sticky_navigation_mobile' => '',
        'manga_reading_navigation_by_pointer' => '',
        'madara_disable_imagetoolbar' => '',
        'madara_reading_history' => '',
        'madara_reading_history_items' => '',
        'madara_reading_history_delay' => 5,
        'madara_enable_chapter_protection' => 'off'
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
            'manga_reading_discussion',
            array(
                'label'          => esc_html__( 'Enable Reading Discussion', 'madara' ),
                'section'        => $section,
                'settings'       => 'manga_reading_discussion',
                'type'           => 'select',
                'description' => esc_html__( 'Turn On/Off Reading Discussion for Manga Reading Page. Default Off.', 'madara' ),
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
            'manga_reading_discussion_heading',
            array(
                'label'          => esc_html__( 'Enable Reading Discussion Heading', 'madara' ),
                'section'        => $section,
                'settings'       => 'manga_reading_discussion_heading',
                'type'           => 'select',
                'description' => esc_html__( 'Show heading for the Comments Form', 'madara' ),
                'choices' => array(
                    'off' => esc_html__( 'Off', 'madara' ),
                    'on' => esc_html__( 'On', 'madara' )
				),
            )
        )
    );

    $wp_customize->add_control(
        new Skyrocket_Image_Radio_Button_Custom_Control(
            $wp_customize,
            'manga_reading_page_sidebar',
            array(
                'label'          => esc_html__( 'Manga Reading Page Sidebar', 'madara' ),
                'section'        => $section,
                'settings'       => 'manga_reading_page_sidebar',
                'type'           => 'select',
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
            'manga_reading_text_sidebar',
            array(
                'label'          => esc_html__( 'Manga Text Chapter - Side Column', 'madara' ),
                'section'        => $section,
                'settings'       => 'manga_reading_text_sidebar',
                'type'           => 'select',
                'description' => esc_html__('In Text Chapter reading page, move sidebar & discussion to the side column, instead of at bottom of content', 'madara'),
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
            'chapter_heading',
            array(
                'label'          => esc_html__( 'Chapter Heading', 'madara' ),
                'section'        => $section,
                'settings'       => 'chapter_heading',
                'type'           => 'select',
                'description' => esc_html__('Show Chapter Heading', 'madara'),
                'choices' => array(
                    'off' => esc_html__( 'Off', 'madara' ),
                    'full' => esc_html__( 'Manga Title - Volume Name - Chapter Name format', 'madara' ),
                    'short' => esc_html__( 'Only Chapter Name', 'madara' ),
				),
            )
        )
    );

    $wp_customize->add_control(
        new Skyrocket_Toggle_Switch_Custom_control(
            $wp_customize,
            'minimal_reading_page',
            array(
                'label'          => esc_html__( 'Minimal Reading Layout', 'madara' ),
                'section'        => $section,
                'settings'       => 'minimal_reading_page',
                'type'           => 'select',
                'description' => esc_html__('Hide header and other parts to focus in reading content', 'madara'),
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
            'reading_page_breadcrumbs',
            array(
                'label'          => esc_html__( 'Breadcrumbs', 'madara' ),
                'section'        => $section,
                'settings'       => 'reading_page_breadcrumbs',
                'type'           => 'select',
                'description' => esc_html__('Show or hide breacrumbs in Chapter Reading Page', 'madara'),
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
            'reading_page_action_icons',
            array(
                'label'          => esc_html__( 'Reading Actions', 'madara' ),
                'section'        => $section,
                'settings'       => 'reading_page_action_icons',
                'type'           => 'select',
                'description' => esc_html__('Show or hide action buttons such as Bookmark or Toggle Contrast', 'madara'),
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
            'reading_page_floating_nav',
            array(
                'label'          => esc_html__( 'Floating Navigation Buttons', 'madara' ),
                'section'        => $section,
                'settings'       => 'reading_page_floating_nav',
                'type'           => 'select',
                'description' => esc_html__('Make the Navigation Buttons floating so that readers can focus in chapter content', 'madara'),
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
            'manga_reading_text_fontsize',
            array(
                'label'          => esc_html__( 'Manga Text Chapter - Font Size', 'madara' ),
                'section'        => $section,
                'settings'       => 'manga_reading_text_fontsize',
                'type'           => 'text',
                'description' => esc_html__('Set font size (in pixels) for text. By default, it takes global font-size', 'madara'),
            )
        )
    );

    
    $wp_customize->add_control(
        new WP_Customize_Control(
            $wp_customize,
            'manga_reading_style',
            array(
                'label'          => esc_html__( 'Manga Image Chapter - Reading Style', 'madara' ),
                'section'        => $section,
                'settings'       => 'manga_reading_style',
                'type'           => 'select',
                'description' => esc_html__( 'Choose reading style for Image Chapter', 'madara' ),
                'choices' => array(
                    'paged' => esc_html__( 'Paged', 'madara' ),
                    'list' => esc_html__( 'List', 'madara' ),
				),
            )
        )
    );

    $wp_customize->add_control(
        new WP_Customize_Control(
            $wp_customize,
            'manga_chapters_select_order',
            array(
                'label'          => esc_html__( 'Chapters Order in Reading Navigation', 'madara' ),
                'section'        => $section,
                'settings'       => 'manga_chapters_select_order',
                'type'           => 'select',
                'description' => esc_html__( 'Should we keep the order in detail page, or reverse it?', 'madara' ),
                'choices' => array(
                    'default' => esc_html__( 'Use Chapters Order in Detail page', 'madara' ),
                    'reverse' => esc_html__( 'Reverse', 'madara' ),
				),
            )
        )
    );

    $wp_customize->add_control(
        new Skyrocket_Toggle_Switch_Custom_control(
            $wp_customize,
            'manga_reading_content_gaps',
            array(
                'label'          => esc_html__( 'Enable Gaps', 'madara' ),
                'section'        => $section,
                'settings'       => 'manga_reading_content_gaps',
                'type'           => 'select',
                'description' => esc_html__( 'Enable Gaps between the images in Reading List Style', 'madara' ),
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
            'manga_reading_images_per_page',
            array(
                'label'          => esc_html__( 'Images Per Page', 'madara' ),
                'section'        => $section,
                'settings'       => 'manga_reading_images_per_page',
                'type'           => 'select',
                'description' => '',
                'choices' => array(
                    '1' => esc_html__( '1 image', 'madara' ),
                    '3' => esc_html__( '3 images', 'madara' ),
                    '6' => esc_html__( '6 images', 'madara' ),
                    '10' => esc_html__( '10 images', 'madara' ),
				),
            )
        )
    );

    $wp_customize->add_control(
        new Skyrocket_Toggle_Switch_Custom_control(
            $wp_customize,
            'manga_reading_full_width',
            array(
                'label'          => esc_html__( 'Full Width (No Left/Right Padding)', 'madara' ),
                'section'        => $section,
                'settings'       => 'manga_reading_full_width',
                'type'           => 'select',
                'description' => esc_html__( 'Disable Left/Right padding when reading chapter', 'madara' ),
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
            'manga_reading_related',
            array(
                'label'          => esc_html__( 'Enable Related Manga', 'madara' ),
                'section'        => $section,
                'settings'       => 'manga_reading_related',
                'type'           => 'select',
                'description' => esc_html__( 'Turn On/Off Related Manga in Reading Page.', 'madara' ),
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
            'manga_page_reading_ajax',
            array(
                'label'          => esc_html__( 'Page Reading Ajax', 'madara' ),
                'section'        => $section,
                'settings'       => 'manga_page_reading_ajax',
                'type'           => 'select',
                'description' => esc_html__( 'Use Ajax instead of redirecting URL when go to next page on chapter', 'madara' ),
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
            'manga_reading_preload_images',
            array(
                'label'          => esc_html__( 'Preload Images', 'madara' ),
                'section'        => $section,
                'settings'       => 'manga_reading_preload_images',
                'type'           => 'select',
                'description' => esc_html__( 'Use preloaded images for chapter without reloading or using ajax to get next/prev image', 'madara' ),
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
            'manga_reading_sticky_header',
            array(
                'label'          => esc_html__( 'Sticky Header', 'madara' ),
                'section'        => $section,
                'settings'       => 'manga_reading_sticky_header',
                'type'           => 'select',
                'description' => '',
                'choices' => array(
                    '' => esc_html__( 'Default (use setting in Theme Options > Header > Sticky Menu', 'madara' ),
                    'on' => esc_html__( 'Yes', 'madara' ),
                    'off' => esc_html__( 'No', 'madara' )
				),
            )
        )
    );

    $wp_customize->add_control(
        new Skyrocket_Toggle_Switch_Custom_control(
            $wp_customize,
            'manga_reading_sticky_navigation',
            array(
                'label'          => esc_html__( 'Sticky Chapter Navigation (Wide Screens)', 'madara' ),
                'section'        => $section,
                'settings'       => 'manga_reading_sticky_navigation',
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
            'manga_reading_sticky_navigation_mobile',
            array(
                'label'          => esc_html__( 'Enable Mobile Sticky Chapter Navigation', 'madara' ),
                'section'        => $section,
                'settings'       => 'manga_reading_sticky_navigation_mobile',
                'type'           => 'select',
                'description' => esc_html__( 'Enable Sticky Chapter Navigation for mobile screens ( < 768px)', 'madara' ),
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
            'manga_reading_navigation_by_pointer',
            array(
                'label'          => esc_html__( 'Next & Prev page by Pointer position', 'madara' ),
                'section'        => $section,
                'settings'       => 'manga_reading_navigation_by_pointer',
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
            'madara_disable_imagetoolbar',
            array(
                'label'          => esc_html__( 'Disable Image "Save image as"', 'madara' ),
                'section'        => $section,
                'settings'       => 'madara_disable_imagetoolbar',
                'type'           => 'select',
                'description' => esc_html__( 'This setting will remove "Save image as" from mouse right click menu on Manga Reading Page', 'madara' ),
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
            'madara_reading_history',
            array(
                'label'          => esc_html__( 'Manga Reading History', 'madara' ),
                'section'        => $section,
                'settings'       => 'madara_reading_history',
                'type'           => 'select',
                'description' => esc_html__( 'Save Manga to user reading history when user\'s reading a chapter.', 'madara' ),
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
            'madara_reading_history_delay',
            array(
                'label'          => esc_html__( 'Manga Reading History Delay', 'madara' ),
                'section'        => $section,
                'settings'       => 'madara_reading_history_delay',
                'type'           => 'text',
                'description' => esc_html__( 'how many seconds should we wait user to read the chapter before saving chapter into reading history', 'madara' ),
            )
        )
    );

    $wp_customize->add_control(
        new WP_Customize_Control(
            $wp_customize,
            'madara_reading_history_items',
            array(
                'label'          => esc_html__( 'Manga Reading History Items', 'madara' ),
                'section'        => $section,
                'settings'       => 'madara_reading_history_items',
                'type'           => 'text',
                'description' => esc_html__( 'Number of Manga Items at most to be saved in Manga Reading History. If you want to store unlimited number of items, enter -1. Please note that you have a lot of mangas, it would effect performance', 'madara' ),
                'std'     => '12',
            )
        )
    );

    $wp_customize->add_control(
        new Skyrocket_Toggle_Switch_Custom_control(
            $wp_customize,
            'madara_enable_chapter_protection',
            array(
                'label'          => esc_html__( 'Enable Chapter Protection', 'madara' ),
                'section'        => $section,
                'settings'       => 'madara_enable_chapter_protection',
                'type'           => 'select',
                'description' => esc_html__( 'Experimental feature to protect novel chapter content from being copied or scraped', 'madara' ),
                'choices' => array(
                    'off' => esc_html__( 'Off', 'madara' ),
                    'on' => esc_html__( 'On', 'madara' )
				),
            )
        )
    );
}