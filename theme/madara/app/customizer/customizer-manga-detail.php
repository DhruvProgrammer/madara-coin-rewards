<?php
add_action( 'customize_register', 'madara_customize_register_manga_detail' );
function madara_customize_register_manga_detail( $wp_customize ) {
    $section = 'manga_single';

    $wp_customize->add_section( $section , array(
        'title'      => esc_html__( 'Manga Detail Page', 'madara' ),
        'priority'   => 18,
    ) );

    $arr_settings = array(
        'manga_single_allow_thumb_gif' => 'off',

    );

    $arr_settings = array_merge($arr_settings, madara_background_control_properties('manga_profile_background'));

    $arr_settings = array_merge($arr_settings, array(
        
        'manga_profile_summary_layout' => 1,
        'manga_single_info_visibility' => 'off',
        'manga_single_info_chapters_count' => 'on',
        'manga_single_tags_post' => 'info',
        'manga_single_meta_author' => 'wp_author',
        'manga_single_breadcrumb' => 'on',
        'manga_single_summary' => 'on',
        'manga_single_chapters_list' => 'on',
        'init_links_enabled' => 'on',
        'manga_single_sidebar' => 'right',
        'manga_reading_oneshot' => 'manga',
        'manga_detail_lazy_chapters' => 'on',
        'manga_detail_chapters_per_page' => 0,
        'manga_volumes_order' => 'desc',
        'manga_chapters_order' => 'name_desc',
        'manga_single_chapters_list_cols' => '1',
        'manga_single_related_items_layout' => 1,
        'manga_single_related_items_count' => '4',
        'manga_single_related_item_mobile_width' => 100,
        'manga_single_related_item_show_datetime' => 'on',
        'manga_rank_views' => 'monthly',
        'seo_manga_title' => '',
        'seo_manga_desc' => '',
        'seo_chapter_title' => '',
        'seo_chapter_desc' => '',
        'manga_single_chapter_meta' => 'date',
        'manga_profile_forecolor' => ''
    ));

    foreach($arr_settings as $key => $def_value){
        $wp_customize->add_setting( $key , array(
            'default'   => $def_value,
            'transport' => 'refresh',
        ) );
    };

    $wp_customize->add_control(
        new Skyrocket_Toggle_Switch_Custom_control(
            $wp_customize,
            'manga_single_allow_thumb_gif',
            array(
                'label'          => esc_html__( 'Allow GIF for Featured Image', 'madara' ),
                'section'        => $section,
                'settings'       => 'manga_single_allow_thumb_gif',
                'type'           => 'select',
                'description' => esc_html__( 'Turn On/Off display GIF for Featured Image. Default Off.', 'madara' ),
                'choices' => array(
                    'off' => esc_html__( 'Off', 'madara' ),
                    'on' => esc_html__( 'On', 'madara' )
				),
            )
        )
    );

    madara_customizer_register_background_controls($wp_customize, 'manga_profile_background', esc_html__( 'Manga Single - Background', 'madara' ), $section);

    $wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, 'manga_profile_forecolor',
        array(
            'label' => esc_html__('Manga Single - ForeColor'),
            'section' => $section,
            'settings'   => 'manga_profile_forecolor',
            'description' => esc_html__('Text Color on Manga Single Detail Section')
        )
    ) );
        
        $wp_customize->add_control(
        new WP_Customize_Control(
            $wp_customize,
            'manga_profile_summary_layout',
            array(
                'label'          => esc_html__( 'Manga Single - Summary Layout', 'madara' ),
                'section'        => $section,
                'settings'       => 'manga_profile_summary_layout',
                'type'           => 'select',
                'description' => esc_html__( 'Layout of Manga Summary Info section', 'madara' ),
                'choices' => array(
                    1 => esc_html__( 'Layout 1 - Small Featured Image', 'madara' ),
                    2 => esc_html__( 'Layout 2 - Fullsize Featured Image', 'madara' ),
				),
            )
        )
    );

    $wp_customize->add_control(
        new Skyrocket_Toggle_Switch_Custom_control(
            $wp_customize,
            'manga_single_info_visibility',
            array(
                'label'          => esc_html__( 'Always Show Manga Info', 'madara' ),
                'section'        => $section,
                'settings'       => 'manga_single_info_visibility',
                'type'           => 'select',
                'description' => esc_html__( 'Always show manga info fields even if they are empty', 'madara' ),
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
            'manga_single_info_chapters_count',
            array(
                'label'          => esc_html__( 'Show Manga Chapters Count', 'madara' ),
                'section'        => $section,
                'settings'       => 'manga_single_info_chapters_count',
                'type'           => 'select',
                'description' => esc_html__( 'Show the number of chapters in the Manga Info section', 'madara' ),
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
            'manga_single_tags_post',
            array(
                'label'          => esc_html__( 'Show Manga Tags', 'madara' ),
                'section'        => $section,
                'settings'       => 'manga_single_tags_post',
                'type'           => 'select',
                'description' => esc_html__( 'Where to show the Manga Tags', 'madara' ),
                'choices' => array(
                    'both' => esc_html__( 'Both in Manga Info section and Page Bottom', 'madara' ),
                    'info' => esc_html__( 'In Manga Info section only', 'madara' ),
                    'summary' => esc_html__( 'Below the Manga Summary section', 'madara' ),
                    'bottom' => esc_html__( 'At Page Bottom only', 'madara' ),
				),
            )
        )
    );

    $wp_customize->add_control(
        new WP_Customize_Control(
            $wp_customize,
            'manga_single_meta_author',
            array(
                'label'          => esc_html__( 'Manga Single - Meta Tags for Authors', 'madara' ),
                'section'        => $section,
                'settings'       => 'manga_single_meta_author',
                'type'           => 'select',
                'description' => esc_html__( 'Use Post Author (default WordPress Author) or Manga Authors in the Meta Tags', 'madara' ),
                'choices' => array(
                    'wp_author' => esc_html__( 'WordPress Post Author', 'madara' ),
                    'manga_authors' => esc_html__( 'Manga Authors', 'madara' )
				),
            )
        )
    );

    $wp_customize->add_control(
        new Skyrocket_Toggle_Switch_Custom_control(
            $wp_customize,
            'manga_single_breadcrumb',
            array(
                'label'          => esc_html__( 'Manga Single - Breadcrumb', 'madara' ),
                'section'        => $section,
                'settings'       => 'manga_single_breadcrumb',
                'type'           => 'select',
                'description' => esc_html__( 'Enable Breadcrumb on Manga Single page', 'madara' ),
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
            'manga_single_breadcrumb',
            array(
                'label'          => esc_html__( 'Manga Single - Breadcrumbs', 'madara' ),
                'section'        => $section,
                'settings'       => 'manga_single_breadcrumb',
                'type'           => 'select',
                'description' => esc_html__( 'Enable Breadcrumbs on Manga Single page', 'madara' ),
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
            'manga_single_summary',
            array(
                'label'          => esc_html__( 'Manga Single - Show More Content', 'madara' ),
                'section'        => $section,
                'settings'       => 'manga_single_summary',
                'type'           => 'select',
                'description' => esc_html__( 'Enable Show More button in Manga Summary', 'madara' ),
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
            'manga_single_chapters_list',
            array(
                'label'          => esc_html__( 'Manga Single - Show More Chapter', 'madara' ),
                'section'        => $section,
                'settings'       => 'manga_single_chapters_list',
                'type'           => 'select',
                'description' => esc_html__( 'Enable Show More button in Manga Chapters List', 'madara' ),
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
            'init_links_enabled',
            array(
                'label'          => esc_html__( 'Show "Read First", "Read Last" button', 'madara' ),
                'section'        => $section,
                'settings'       => 'init_links_enabled',
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
        new Skyrocket_Image_Radio_Button_Custom_Control(
            $wp_customize,
            'manga_single_sidebar',
            array(
                'label'          => esc_html__( 'Manga Single Sidebar', 'madara' ),
                'section'        => $section,
                'settings'       => 'manga_single_sidebar',
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
						'image' => get_parent_theme_file_uri( '/images/options/sidebar/sidebar-hidden.png' ),
						'name' => __( 'Full', 'madara' )
					)
				)
            )
        )
    );

    $wp_customize->add_control(
        new WP_Customize_Control(
            $wp_customize,
            'manga_reading_oneshot',
            array(
                'label'          => esc_html__( 'Manga Single - Default Manga Style', 'madara' ),
                'section'        => $section,
                'settings'       => 'manga_reading_oneshot',
                'type'           => 'select',
                'description' => esc_html__( 'Set default style for Mangas. In each manga you can configure again to override this setting', 'madara' ),
                'choices' => array(
                    'manga' => esc_html__( 'Manga (with Chapters List)', 'madara' ),
                    'oneshot' => esc_html__( 'One Shot (display the first chapter only)', 'madara' )
				),
            )
        )
    );

    $wp_customize->add_control(
        new Skyrocket_Toggle_Switch_Custom_control(
            $wp_customize,
            'manga_detail_lazy_chapters',
            array(
                'label'          => esc_html__( 'Lazy-load chapters list', 'madara' ),
                'section'        => $section,
                'settings'       => 'manga_detail_lazy_chapters',
                'type'           => 'select',
                'description' => esc_html__('If you manga/novel has a lot of chapters, the chapters list will load too long. Lazy-load it will improve the performance. However, it will not be cached', 'madara'),
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
            'manga_detail_chapters_per_page',
            array(
                'label'          => esc_html__( 'Number of Chapters Per Page', 'madara' ),
                'section'        => $section,
                'settings'       => 'manga_detail_chapters_per_page',
                'type'           => 'text',
                'description' => esc_html__( 'Paginate the Chapters List. Only works if "Lazy-load chapters list" is ON. Leave it Empty or 0 to turn off pagination', 'madara' ),
            )
        )
    );
    

    $wp_customize->add_control(
        new WP_Customize_Control(
            $wp_customize,
            'manga_volumes_order',
            array(
                'label'          => esc_html__( 'Manga Single - Volumes Order', 'madara' ),
                'section'        => $section,
                'settings'       => 'manga_volumes_order',
                'type'           => 'select',
                'description' => esc_html__( 'Volumes order in the Chapter Navigation bar. In "Manga Edit" page, you can drag&drop the order of volumes to sort them', 'madara' ),
                'choices' => array(
                    'desc' => esc_html__( 'As in "Manga Edit" page', 'madara' ),
                    'asc' => esc_html__( 'Reverse order in "Manga Edit" page', 'madara' ),
				),
            )
        )
    );

    $wp_customize->add_control(
        new WP_Customize_Control(
            $wp_customize,
            'manga_chapters_order',
            array(
                'label'          => esc_html__( 'Manga Single - Chapters Order', 'madara' ),
                'section'        => $section,
                'settings'       => 'manga_chapters_order',
                'type'           => 'select',
                'description' => esc_html__( 'Set chapters order in Manga Single and other page where chapters are listed. Order By Name works, but low performance. Consider using Order by Custom Index', 'madara' ),
                'choices' => array(
                    'name_asc' => esc_html__( 'Oldest to latest by Name', 'madara' ),
                    'name_desc' => esc_html__( 'Latest to oldest by Name', 'madara' ),
                    'date_asc' => esc_html__( 'Oldest to latest by Time', 'madara' ),
                    'date_desc' => esc_html__( 'Latest to oldest by Time', 'madara' ),
                    'index_desc' => esc_html__( 'Custom Index Value - Bigger to Smaller', 'madara' ),
                    'index_asc' => esc_html__( 'Custom Index Value - Smaller to Bigger', 'madara' )
                )
            )
        )
    );

    $wp_customize->add_control(
        new Skyrocket_Slider_Custom_Control($wp_customize,
            'manga_single_chapters_list_cols',
            array(
                'label'          => esc_html__( 'Manga Single - Chapters List Columns', 'madara' ),
                'section'        => $section,
                'settings'       => 'manga_single_chapters_list_cols',
                'type'           => 'slider_control',
                'input_attrs' => array(
                    'min' => 1,
                    'max' => 4,
                    'step' => 1,
                ),
                'description' => esc_html__( 'Choose number of columns to list chapters', 'madara' ),
            )
    ));

    $wp_customize->add_control(
        new WP_Customize_Control(
            $wp_customize,
            'manga_single_chapter_meta',
            array(
                'label'          => esc_html__( 'Chapter Meta', 'madara' ),
                'section'        => $section,
                'settings'       => 'manga_single_chapter_meta',
                'type'           => 'select',
                'description' => esc_html__( 'Choose what type of info to display for the Latest Chapters of each Manga in the loop. To have "Chapter Views", you will need to enable this feature in Madara-Core plugin', 'madara' ),
                'choices' => array(
                    'hide' => esc_html__( 'Hide', 'madara' ),
                    'views' => esc_html__( 'Chapter Views', 'madara' ),
                    'date' => esc_html__( 'Published Date', 'madara' ),
                    'both' => esc_html__( 'Both Published Date & Chapter Views', 'madara' ),
				),
            )
        )
    );

    $wp_customize->add_control(
        new WP_Customize_Control(
            $wp_customize,
            'manga_single_related_items_layout',
            array(
                'label'          => esc_html__( 'Manga Single - Related Items Layout', 'madara' ),
                'section'        => $section,
                'settings'       => 'manga_single_related_items_layout',
                'type'           => 'select',
                'description' => esc_html__( 'Choose layout for Manga Related Items', 'madara' ),
                'choices' => array(
                    1 => esc_html__( 'Default (small thumbnail)', 'madara' ),
                    2 => esc_html__( 'Big Thumbnail', 'madara' )
                )
            )
        )
    );

    $wp_customize->add_control(
        new Skyrocket_Slider_Custom_Control(
            $wp_customize,
            'manga_single_related_items_count',
            array(
                'label'          => esc_html__( 'Manga Single - Number of Related Items', 'madara' ),
                'section'        => $section,
                'settings'       => 'manga_single_related_items_count',
                'type'           => 'slider_control',
                'description' => esc_html__( 'Choose number of related items to display', 'madara' ),
                'input_attrs' => array(
                    'min' => 1,
                    'max' => 100,
                    'step' => 1,
                ),
            )
        )
    );

    $wp_customize->add_control(
        new WP_Customize_Control(
            $wp_customize,
            'manga_single_related_item_mobile_width',
            array(
                'label'          => esc_html__( 'Item Width on Mobile Screen', 'madara' ),
                'section'        => $section,
                'settings'       => 'manga_single_related_item_mobile_width',
                'type'           => 'select',
                'description' => esc_html__( 'Set item width when viewing on mobile screens', 'madara' ),
                'choices' => array(
                    100 => esc_html__( '1/1 - 100% screen width', 'madara' ),
                    50 => esc_html__( '1/2 - 50% screen width', 'madara' )
                )
            )
        )
    );

    $wp_customize->add_control(
        new Skyrocket_Toggle_Switch_Custom_control(
            $wp_customize,
            'manga_single_related_item_show_datetime',
            array(
                'label'          => esc_html__( 'Related Manga - Show Datetime', 'madara' ),
                'section'        => $section,
                'settings'       => 'manga_single_related_item_show_datetime',
                'type'           => 'select',
                'description' => esc_html__( 'Turn On/Off datetime on Related Manga Item', 'madara' ),
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
            'manga_rank_views',
            array(
                'label'          => esc_html__( 'Manga Views Display', 'madara' ),
                'section'        => $section,
                'settings'       => 'manga_rank_views',
                'type'           => 'select',
                'description' => esc_html__( 'Display monthly views or all time views', 'madara' ),
                'choices' => array(
                    'monthly' => esc_html__( 'Monthly', 'madara' ),
                    'alltime' => esc_html__( 'All Time', 'madara' ),
                )
            )
        )
    );

    $wp_customize->add_control(
        new WP_Customize_Control(
            $wp_customize,
            'seo_manga_title',
            array(
                'label'          => esc_html__( 'SEO - Manga Title', 'madara' ),
                'section'        => $section,
                'settings'       => 'seo_manga_title',
                'type'           => 'text',
                'description' => esc_html__( 'Custom Title Meta for Single Manga page. Use tag %title% for current Manga Title. When using with Yoast SEO, this will override the meta title in Yoast', 'madara' ),
            )
        )
    );
    
    $wp_customize->add_control(
        new WP_Customize_Control(
            $wp_customize,
            'seo_manga_desc',
            array(
                'label'          => esc_html__( 'SEO - Manga Description', 'madara' ),
                'section'        => $section,
                'settings'       => 'seo_manga_desc',
                'type'           => 'text',
                'description' => esc_html__( 'Custom Description Meta for Single Manga page. Use tag %title% for current Manga Title. When using with Yoast SEO, this will override the meta description in Yoast', 'madara' ),
            )
        )
    );

    $wp_customize->add_control(
        new WP_Customize_Control(
            $wp_customize,
            'seo_chapter_title',
            array(
                'label'          => esc_html__( 'SEO - Manga Chapter Title', 'madara' ),
                'section'        => $section,
                'settings'       => 'seo_chapter_title',
                'type'           => 'text',
                'description' => esc_html__( 'Custom Title Meta for Single Manga Reading page. Use tag %title% for current Manga Title, %chapter% for current Manga Chapter, %chapter_index% for current Chapter Index. When using with Yoast SEO, this will override the meta title in Yoast', 'madara' ),
            )
        )
    );

    $wp_customize->add_control(
        new WP_Customize_Control(
            $wp_customize,
            'seo_chapter_desc',
            array(
                'label'          => esc_html__( 'SEO - Manga Chapter Description', 'madara' ),
                'section'        => $section,
                'settings'       => 'seo_chapter_desc',
                'type'           => 'text',
                'description' => esc_html__( 'Custom Description Meta for Single Manga Reading page. Use tag %title% for current Manga Title, %chapter% for current Manga Chapter, %chapter_index% for current Chapter Index, %summary% for Manga excerpt or first paragraph in a Novel chapter. When using with Yoast SEO, this will override the meta description in Yoast', 'madara' ),
            )
        )
    );

    
}