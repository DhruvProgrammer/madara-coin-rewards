<?php

	// Prevent direct access to this file
	defined( 'ABSPATH' ) || die( 'Direct access to this file is not allowed.' );

	/**
	 * Custom settings array that will eventually be
	 * passes to the OptionTree Settings API Class.
	 */

	if ( class_exists( 'WP_MANGA' ) ) {
		// wp-manga plugin is active, add some options to Theme Options

		$madara_theme_options = array(
			'sections' => array(
				array(
					'id'    => 'manga_general_layout',
					'title' => '<i class="fas fa-bolt"><!-- --></i>' . esc_html__( 'Manga General Layout', 'madara' ),
				),
				array(
					'id'    => 'manga_general',
					'title' => '<i class="fas fa-bolt"><!-- --></i>' . esc_html__( 'Manga General Settings', 'madara' ),
				),
				array(
					'id'    => 'manga_archives',
					'title' => '<i class="fas fa-bolt"><!-- --></i>' . esc_html__( 'Manga Archives Page', 'madara' ),
				),
				array(
					'id'    => 'manga_single',
					'title' => '<i class="fas fa-bolt"><!-- --></i>' . esc_html__( 'Manga Detail Page', 'madara' ),
				),
				array(
					'id'    => 'manga_reading',
					'title' => '<i class="fas fa-bolt"><!-- --></i>' . esc_html__( 'Manga Reading Page', 'madara' ),
				),
			),
			'settings' => array(


				/*
				* Manga Theme Options
				* */
				array(
					'id'      => 'manga_adult_content',
					'label'   => esc_html__( 'Family Safe button', 'madara' ),
					'desc'    => esc_html__( 'Show to "Family Safe" button. This will allow visitors to turn on/off Adult content on your site. If this button is ON, then by default, adult content will be filtered out', 'madara' ),
					'std'     => 'off',
					'type'    => 'on-off',
					'section' => 'manga_general'
				),
				
				array(
					'id'      => 'manga_hover_details',
					'label'   => esc_html__( 'Manga Hover Details', 'madara' ),
					'desc'    => esc_html__( 'Show manga details when manga item in Manga Listing hoverd', 'madara' ),
					'std'     => 'off',
					'type'    => 'on-off',
					'section' => 'manga_general'
				),

				array(
					'id'      => 'manga_new_chapter',
					'label'   => esc_html__( 'Manga New Chapter Tag', 'madara' ),
					'desc'    => esc_html__( 'Display "New" tag for the new chapter', 'madara' ),
					'std'     => 'off',
					'type'    => 'on-off',
					'section' => 'manga_general'
				),

				array(
					'id'        => 'manga_new_chapter_time_range',
					'label'     => esc_html__( 'New Chapter - Time Range', 'madara' ),
					'desc'      => esc_html__( 'The time range for set "New" tag from the time the chapter is uploaded', 'madara' ),
					'std'       => 3,
					'type'      => 'select',
					'section'   => 'manga_general',
					'choices'   => array(
						array(
							'value' => 3,
							'label' => esc_html__( '3 Days', 'madara' ),
						),
						array(
							'value' => 7,
							'label' => esc_html__( '7 Days', 'madara' ),
						),
						array(
							'value' => 15,
							'label' => esc_html__( '15 Days', 'madara' ),
						),
						array(
							'value' => 30,
							'label' => esc_html__( '30 Days', 'madara' ),
						),
					),
					'condition' => 'manga_new_chapter:is(on)',
				),
				
				array(
					'id'      => 'manga_reader_setting',
					'label'   => esc_html__( 'Reader Settings', 'madara' ),
					'desc'    => esc_html__( 'Enable "Reader Settings" tab in User Settings dashboard', 'madara' ),
					'std'     => 'on',
					'type'    => 'on-off',
					'section' => 'manga_general'
				),
                
                array(
					'id'      => 'manga_bookmark_list_orderby',
					'label'   => esc_html__( 'Manga Bookmark List - Order By', 'madara' ),
					'desc'    => esc_html__( 'By default, Bookmarked List items are ordered by the time an item is added to the list', 'madara' ),
					'std'     => '',
					'type'    => 'select',
					'choices' => array(
						array(
							'value' => '',
							'label' => esc_html__( 'Default (bookmarked time)', 'madara' )
						),
						array(
							'value' => 'update',
							'label' => esc_html__( 'Manga Latest Update time', 'madara' )
						)
					),
					'section' => 'manga_general',
				),
				
				array(
					'id'      => 'manga_bookmark_list_order',
					'label'   => esc_html__( 'Manga Bookmark List - Order', 'madara' ),
					'desc'    => esc_html__( 'Order of the items in the Bookmark List in User Settings page', 'madara' ),
					'std'     => 'oldest_firt',
					'type'    => 'select',
					'choices' => array(
						array(
							'value' => 'oldest_first',
							'label' => esc_html__( 'Oldest First', 'madara' )
						),
						array(
							'value' => 'newest_first',
							'label' => esc_html__( 'Newest First', 'madara' )
						)
					),
					'section' => 'manga_general',
				),

				array(
					'id'      => 'manga_main_top_sidebar_container',
					'label'   => esc_html__( 'Manga Main Top Sidebar Container', 'madara' ),
					'desc'    => esc_html__( 'Set container for Manga Main Top Sidebar. Custom width is 1760px', 'madara' ),
					'std'     => 'container',
					'type'    => 'radio-image',
					'class'   => '',
					'choices' => array(
						array(
							'value' => 'full_width',
							'label' => esc_html__( 'Full-Width', 'madara' ),
							'src'   => get_parent_theme_file_uri( '/images/options/sidebar/sidebar-fullwidth.png' ),
						),
						array(
							'value' => 'container',
							'label' => esc_html__( 'Container', 'madara' ),
							'src'   => get_parent_theme_file_uri( '/images/options/sidebar/sidebar-container.png' ),
						),
						array(
							'value' => 'custom_width',
							'label' => esc_html__( 'Custom Width', 'madara' ),
							'src'   => get_parent_theme_file_uri( '/images/options/sidebar/sidebar-custom-width.png' ),
						)
					),
					'section' => 'manga_general_layout',
				),

				array(
					'id'      => 'manga_main_top_sidebar_background',
					'label'   => esc_html__( 'Manga Main Top Sidebar Background', 'madara' ),
					'desc'    => esc_html__( 'Upload background image for Manga Main Top Sidebar', 'madara' ),
					'std'     => '',
					'type'    => 'background',
					'section' => 'manga_general_layout',
				),

				array(
					'id'      => 'manga_main_top_sidebar_spacing',
					'label'   => esc_html__( 'Manga Main Top Sidebar - Padding', 'madara' ),
					'desc'    => esc_html__( 'Padding in Manga Main Top Sidebar. Default value is 50 0 20 0 & unit is px', 'madara' ),
					'std'     => '',
					'type'    => 'spacing',
					'section' => 'manga_general_layout',
				),

				array(
					'id'      => 'manga_main_top_second_sidebar_container',
					'label'   => esc_html__( 'Manga Main Top Second Sidebar Container', 'madara' ),
					'desc'    => esc_html__( 'Set container for Manga Main Top Second Sidebar. Custom width is 1760px', 'madara' ),
					'std'     => 'container',
					'type'    => 'radio-image',
					'class'   => '',
					'choices' => array(
						array(
							'value' => 'full_width',
							'label' => esc_html__( 'Full-Width', 'madara' ),
							'src'   => get_parent_theme_file_uri( '/images/options/sidebar/sidebar-fullwidth.png' ),
						),
						array(
							'value' => 'container',
							'label' => esc_html__( 'Container', 'madara' ),
							'src'   => get_parent_theme_file_uri( '/images/options/sidebar/sidebar-container.png' ),
						),
						array(
							'value' => 'custom_width',
							'label' => esc_html__( 'Custom Width', 'madara' ),
							'src'   => get_parent_theme_file_uri( '/images/options/sidebar/sidebar-custom-width.png' ),
						)
					),
					'section' => 'manga_general_layout',
				),

				array(
					'id'      => 'manga_main_top_second_sidebar_background',
					'label'   => esc_html__( 'Manga Main Top Second Sidebar Background', 'madara' ),
					'desc'    => esc_html__( 'Upload background image for Manga Main Top Second Sidebar', 'madara' ),
					'std'     => '',
					'type'    => 'background',
					'section' => 'manga_general_layout',
				),

				array(
					'id'      => 'manga_main_top_second_sidebar_spacing',
					'label'   => esc_html__( 'Manga Main Top Second Sidebar - Padding', 'madara' ),
					'desc'    => esc_html__( 'Padding in Manga Main Top Second Sidebar. Default value is 50 0 20 0 & unit is px', 'madara' ),
					'std'     => '',
					'type'    => 'spacing',
					'section' => 'manga_general_layout',
				),

				array(
					'id'      => 'manga_main_bottom_sidebar_container',
					'label'   => esc_html__( 'Manga Main Bottom Sidebar Container', 'madara' ),
					'desc'    => esc_html__( 'Set container for Manga Main Bottom Sidebar. Custom width is 1760px', 'madara' ),
					'std'     => 'container',
					'type'    => 'radio-image',
					'class'   => '',
					'choices' => array(
						array(
							'value' => 'full_width',
							'label' => esc_html__( 'Full-Width', 'madara' ),
							'src'   => get_parent_theme_file_uri( '/images/options/sidebar/sidebar-fullwidth.png' ),
						),
						array(
							'value' => 'container',
							'label' => esc_html__( 'Container', 'madara' ),
							'src'   => get_parent_theme_file_uri( '/images/options/sidebar/sidebar-container.png' ),
						),
						array(
							'value' => 'custom_width',
							'label' => esc_html__( 'Custom Width', 'madara' ),
							'src'   => get_parent_theme_file_uri( '/images/options/sidebar/sidebar-custom-width.png' ),
						)
					),
					'section' => 'manga_general_layout',
				),

				array(
					'id'      => 'manga_main_bottom_sidebar_background',
					'label'   => esc_html__( 'Manga Main Bottom Sidebar Background', 'madara' ),
					'desc'    => esc_html__( 'Upload background image for Manga Main Bottom Sidebar', 'madara' ),
					'std'     => '',
					'type'    => 'background',
					'section' => 'manga_general_layout',
				),

				array(
					'id'      => 'manga_main_bottom_sidebar_spacing',
					'label'   => esc_html__( 'Manga Main Bottom Sidebar - Padding', 'madara' ),
					'desc'    => esc_html__( 'Padding in Manga Main Bottom Sidebar. Default value is 50 0 20 0 & unit is px', 'madara' ),
					'std'     => '',
					'type'    => 'spacing',
					'section' => 'manga_general_layout',
				),
                
				array(
					'id'           => 'manga_archive_breadcrumb',
					'label'        => esc_html__( 'Manga Archives Header', 'madara' ),
					'desc'         => esc_html__( 'Enable Header section on Manga Archives page', 'madara' ),
					'std'          => 'on',
					'type'         => 'on-off',
					'section'      => 'manga_archives',
					'min_max_step' => '',
				),

				array(
					'id'      => 'manga_archive_breadcrumb_background',
					'label'   => esc_html__( 'Manga Archive Header Background', 'madara' ),
					'desc'    => esc_html__( 'Upload background image for Manga Archive Header', 'madara' ),
					'std'     => '',
					'type'    => 'background',
					'section' => 'manga_archives',
				),
                
                array(
					'id'      => 'manga_archive_heading',
					'label'   => esc_html__( 'Manga Archive Heading Text', 'madara' ),
					'desc'    => esc_html__( 'set Heading Text for Manga Archives page. Default is "All Mangas"', 'madara' ),
					'std'     => '',
					'type'    => 'text',
					'section' => 'manga_archives',
				),

				array(
					'id'      => 'manga_archive_genres',
					'label'   => esc_html__( 'Genres on Manga Archive Page', 'madara' ),
					'desc'    => esc_html__( 'Enable Genres block on Manga Archive Page Breadcrumb', 'madara' ),
					'std'     => 'on',
					'type'    => 'on-off',
					'section' => 'manga_archives',
				),

				array(
					'id'        => 'manga_archive_genres_collapse',
					'label'     => esc_html__( 'Default Genres List appearance', 'madara' ),
					'desc'      => esc_html__( 'Show or hide Genres list by default. Choose "On" to open the Genres List', 'madara' ),
					'std'       => 'on',
					'type'      => 'on-off',
					'section'   => 'manga_archives',
					'condition' => 'manga_archive_genres:is(on)',
				),

				array(
					'id'        => 'manga_archive_genres_title',
					'label'     => esc_html__( 'Genres Block Title', 'madara' ),
					'desc'      => esc_html__( 'Genres Block Title. Default is "GENRES"', 'madara' ),
					'type'      => 'text',
					'section'   => 'manga_archives',
					'condition' => 'manga_archive_genres:is(on)',
				),

				array(
					'id'      => 'manga_archive_sidebar',
					'label'   => esc_html__( 'Manga Archives Sidebar', 'madara' ),
					'desc'    => '',
					'std'     => 'right',
					'type'    => 'radio-image',
					'section' => 'manga_archives',
					'choices' => array(
						array(
							'value' => 'left',
							'label' => esc_html__( 'Left', 'madara' ),
							'src'   => get_parent_theme_file_uri( '/images/options/sidebar/sidebar-left.png' ),
						),
						array(
							'value' => 'right',
							'label' => esc_html__( 'Right', 'madara' ),
							'src'   => get_parent_theme_file_uri( '/images/options/sidebar/sidebar-right.png' ),
						),
						array(
							'value' => 'full',
							'label' => esc_html__( 'Hidden', 'madara' ),
							'src'   => get_parent_theme_file_uri( '/images/options/sidebar/sidebar-hidden.png' ),
						)
					),
				),
				
				array(
					'id'      => 'manga_archives_item_layout',
					'label'   => esc_html__( 'Item Layout', 'madara' ),
					'desc'    => esc_html__( 'Select layout for Manga Item in the list. Remember to regenerate thumbnails for existing mangas', 'madara' ),
					'std'     => 'default',
					'type'    => 'select',
					'section' => 'manga_archives',
					'choices' => array(
						array(
							'value' => 'default',
							'label' => esc_html__( 'Default (Small Thumbnail)', 'madara' ),
						),
						array(
							'value' => 'big_thumbnail',
							'label' => esc_html__( 'Big Thumbnail', 'madara' ),
						),
						array(
							'value' => 'simple',
							'label' => esc_html__( 'Simple List', 'madara' ),
						)
					),
				),
                
                array(
					'id'      => 'manga_archives_item_mobile_width',
					'label'   => esc_html__( 'Item Width on Mobile Screen', 'madara' ),
					'desc'    => esc_html__( 'Set item width when viewing on mobile screens', 'madara' ),
					'std'     => '50',
					'type'    => 'select',
					'section' => 'manga_archives',
					'choices' => array(
						array(
							'value' => '50',
							'label' => esc_html__( '1/2 - 50% screen width', 'madara' ),
						),
						array(
							'value' => '100',
							'label' => esc_html__( '1/1 - 100% screen width', 'madara' ),
						)
					),
                    'condition' => 'manga_archives_item_layout:is(big_thumbnail)'
				),
                
                array(
					'id'      => 'manga_single_allow_thumb_gif',
					'label'   => esc_html__( 'Allow GIF for Featured Image', 'madara' ),
					'desc'    => esc_html__( 'Turn On/Off display GIF for Featured Image. Default Off.', 'madara' ),
					'std'     => 'off',
					'type'    => 'on-off',
					'section' => 'manga_single',
				),
                
                array(
					'id'        => 'manga_archive_latest_chapter_on_thumbnail',
					'label'     => esc_html__( 'Link to the Latest Chapter on thumbnail', 'madara' ),
					'desc'      => esc_html__( 'Item thumbnail will link to the Latest Chapter in this series. A "Chapter Tag" will also appear on top of the thumbnail', 'madara' ),
					'type'      => 'on-off',
                    'std'          => 'off',
					'section'   => 'manga_archives',
				),
				
				array(
					'id'      => 'manga_archives_item_type_icon',
					'label'   => esc_html__( 'Manga Type Icon', 'madara' ),
					'desc'    => esc_html__( 'If your site has different types of manga (Comic, Novel, Drama) and you need an icon to differentiate those, turn this on', 'madara' ),
					'std'     => 'off',
					'type'    => 'on-off',
					'section' => 'manga_archives'
				),
                
                array(
					'id'      => 'manga_archives_item_type_text',
					'label'   => esc_html__( 'Manga Type Text', 'madara' ),
					'desc'    => esc_html__( 'Showing Manga Type value or not', 'madara' ),
					'std'     => 'off',
					'type'    => 'on-off',
					'section' => 'manga_archives'
				),
				
				array(
					'id'      => 'manga_badge_position',
					'label'   => esc_html__( 'Badge Position', 'madara' ),
					'desc'    => esc_html__( 'Choose where to show the badge', 'madara' ),
					'std'     => '1',
					'type'    => 'select',
					'section' => 'manga_archives',
					'choices' => array(
						array(
							'value' => 1,
							'label' => esc_html__( 'Before title', 'madara' ),
						),
						array(
							'value' => 2,
							'label' => esc_html__( 'Before thumbnail', 'madara' ),
						)
					)
				),
                
                array(
					'id'        => 'manga_archive_limit_visible_lines',
					'label'     => esc_html__( 'Limit Visible Titles', 'madara' ),
					'desc'      => esc_html__( 'Limit number of text lines so the long title will not break the layout', 'madara' ),
					'type'      => 'numeric-slider',
                    'std'          => '2',
                    'min_max_step' => '1,5,1',
					'section'   => 'manga_archives',
				),
                
                array(
					'id'        => 'manga_archive_latest_chapters_visible',
					'label'     => esc_html__( 'Number of visible latest chapters', 'madara' ),
					'desc'      => esc_html__( 'Choose number of visible latest chapters for each item in the list', 'madara' ),
					'type'      => 'numeric-slider',
                    'std'          => '2',
                    'min_max_step' => '0,4,1',
					'section'   => 'manga_archives',
				),
				
				array(
					'id'      => 'manga_archives_item_volume',
					'label'   => esc_html__( 'Show Chapter Volume', 'madara' ),
					'desc'    => esc_html__( 'Show Chapter Volume information', 'madara' ),
					'std'     => 'on',
					'type'    => 'on-off',
					'section' => 'manga_archives',
				),

				array(
					'id'      => 'manga_profile_background',
					'label'   => esc_html__( 'Manga Single - Background', 'madara' ),
					'desc'    => esc_html__( 'Upload background image used in Manga detail page', 'madara' ),
					'std'     => '',
					'type'    => 'background',
					'section' => 'manga_single',
				),
                
                array(
					'id'      => 'manga_profile_summary_layout',
					'label'   => esc_html__( 'Manga Single - Summary Layout', 'madara' ),
					'desc'    => esc_html__( 'Layout of Manga Summary Info section', 'madara' ),
					'std'     => 1,
					'type'    => 'select',
					'section' => 'manga_single',
                    'choices' => array(
						array(
							'value' => 1,
							'label' => esc_html__( 'Layout 1 - Small Featured Image', 'madara' ),
						),
						array(
							'value' => 2,
							'label' => esc_html__( 'Layout 2 - Fullsize Featured Image', 'madara' ),
						)
					),
				),
                
                array(
					'id'      => 'manga_single_info_visibility',
					'label'   => esc_html__( 'Always Show Manga Info', 'madara' ),
					'desc'    => esc_html__( 'Always show manga info fields even if they are empty', 'madara' ),
					'std'     => 'off',
					'type'    => 'on-off',
					'section' => 'manga_single',
				),
                
                array(
					'id'      => 'manga_single_tags_post',
					'label'   => esc_html__( 'Show Manga Tags', 'madara' ),
					'desc'    => esc_html__( 'Where to show the Manga Tags', 'madara' ),
					'std'     => 'info',
					'type'    => 'select',
                    'choices' => array(
						array(
							'value' => 'both',
							'label' => esc_html__( 'Both in Manga Info section and Page Bottom', 'madara' ),
						),
						array(
							'value' => 'info',
							'label' => esc_html__( 'In Manga Info section only', 'madara' ),
						),
						array(
							'value' => 'bottom',
							'label' => esc_html__( 'At Page Bottom only', 'madara' ),
						)
						),
					'section' => 'manga_single',
				),
				
				array(
					'id'      => 'manga_single_meta_author',
					'label'   => esc_html__( 'Manga Single - Meta Tags for Authors', 'madara' ),
					'desc'    => esc_html__( 'Use Post Author (default WordPress Author) or Manga Authors in the Meta Tags', 'madara' ),
					'std'     => 'wp_author',
					'type'    => 'select',
					'choices' => array(
						array(
							'value' => 'wp_author',
							'label' => esc_html__( 'WordPress Post Author', 'madara' ),
						),
						array(
							'value' => 'manga_authors',
							'label' => esc_html__( 'Manga Authors', 'madara' ),
						)
						),
					'section' => 'manga_single',
				),

				array(
					'id'      => 'manga_single_breadcrumb',
					'label'   => esc_html__( 'Manga Single - Breadcrumb', 'madara' ),
					'desc'    => esc_html__( 'Enable Breadcrumb on Manga Single page', 'madara' ),
					'std'     => 'on',
					'type'    => 'on-off',
					'section' => 'manga_single',
				),

				array(
					'id'      => 'manga_single_summary',
					'label'   => esc_html__( 'Manga Single - Show More Content', 'madara' ),
					'desc'    => esc_html__( 'Enable Show More button in Manga Summary', 'madara' ),
					'std'     => 'on',
					'type'    => 'on-off',
					'section' => 'manga_single',
				),

				array(
					'id'      => 'manga_single_chapters_list',
					'label'   => esc_html__( 'Manga Single - Show More Chapter', 'madara' ),
					'desc'    => esc_html__( 'Enable Show More button in Manga Chapters List', 'madara' ),
					'std'     => 'on',
					'type'    => 'on-off',
					'section' => 'manga_single',
				),
				
				array(
					'id'      => 'init_links_enabled',
					'label'   => esc_html__( 'Show "Read First", "Read Last" button', 'madara' ),
					'std'     => 'on',
					'type'    => 'on-off',
					'section' => 'manga_single',
				),

				array(
					'id'      => 'manga_single_sidebar',
					'label'   => esc_html__( 'Manga Single Sidebar', 'madara' ),
					'desc'    => '',
					'std'     => 'right',
					'type'    => 'radio-image',
					'section' => 'manga_single',
					'choices' => array(
						array(
							'value' => 'left',
							'label' => esc_html__( 'Left', 'madara' ),
							'src'   => get_parent_theme_file_uri( '/images/options/sidebar/sidebar-left.png' ),
						),
						array(
							'value' => 'right',
							'label' => esc_html__( 'Right', 'madara' ),
							'src'   => get_parent_theme_file_uri( '/images/options/sidebar/sidebar-right.png' ),
						),
						array(
							'value' => 'full',
							'label' => esc_html__( 'Hidden', 'madara' ),
							'src'   => get_parent_theme_file_uri( '/images/options/sidebar/sidebar-hidden.png' ),
						)
					),
				),
                
                array(
					'id'      => 'manga_reading_oneshot',
					'label'   => esc_html__( 'Manga Single - Default Manga Style', 'madara' ),
					'desc'    => esc_html__( 'Set default style for Mangas. In each manga you can configure again to override this setting', 'madara' ),
					'std'     => 'manga',
					'type'    => 'select',
					'section' => 'manga_single',
					'choices' => array(
						array(
							'value' => 'manga',
							'label' => esc_html__( 'Manga (with Chapters List)', 'madara' ),
						),
						array(
							'value' => 'oneshot',
							'label' => esc_html__( 'One Shot (display the first chapter only)', 'madara' ),
						)
					),
				),
				
				array(
					'id'      => 'manga_detail_lazy_chapters',
					'label'   => esc_html__( 'Lazy-load chapters list', 'madara' ),
					'std'     => 'on',
					'type'    => 'on-off',
					'section' => 'manga_single',
					'desc'    => esc_html__('If you manga/novel has a lot of chapters, the chapters list will load too long. Lazy-load it will improve the performance. However, it will not be cached', 'madara')
				),
                
                array(
					'id'      => 'manga_volumes_order',
					'label'   => esc_html__( 'Manga Single - Volumes Order', 'madara' ),
					'desc'    => esc_html__( 'Volumes order in the Chapter Navigation bar. In "Manga Edit" page, you can drag&drop the order of volumes to sort them', 'madara' ),
					'std'     => 'desc',
					'type'    => 'select',
					'section' => 'manga_single',
					'choices' => array(
						array(
							'value' => 'desc',
							'label' => esc_html__( 'As in "Manga Edit" page', 'madara' ),
						),
						array(
							'value' => 'asc',
							'label' => esc_html__( 'Reverse order in "Manga Edit" page', 'madara' ),
						)
					),
				),

				array(
					'id'      => 'manga_chapters_order',
					'label'   => esc_html__( 'Manga Single - Chapters Order', 'madara' ),
					'desc'    => esc_html__( 'Set chapters order in Manga Single and other page where chapters are listed. Order By Name works, but low performance. Consider using Order by Custom Index', 'madara' ),
					'std'     => 'name_desc',
					'type'    => 'select',
					'section' => 'manga_single',
					'choices' => array(
						array(
							'value' => 'name_asc',
							'label' => esc_html__( 'Oldest to latest by Name', 'madara' ),
						),
						array(
							'value' => 'name_desc',
							'label' => esc_html__( 'Latest to oldest by Name', 'madara' ),
						),
						array(
							'value' => 'date_asc',
							'label' => esc_html__( 'Oldest to latest by Time', 'madara' ),
						),
						array(
							'value' => 'date_desc',
							'label' => esc_html__( 'Latest to oldest by Time', 'madara' ),
						),
						array(
							'value' => 'index_desc',
							'label' => esc_html__( 'Custom Index Value - Bigger to Smaller', 'madara' ),
						),
						array(
							'value' => 'index_asc',
							'label' => esc_html__( 'Custom Index Value - Smaller to Bigger', 'madara' ),
						),
					),
				),
                 array(
					'id'      => 'manga_single_chapters_list_cols',
					'label'   => esc_html__( 'Manga Single - Chapters List Columns', 'madara' ),
					'desc'    => esc_html__( 'Choose number of columns to list chapters', 'madara' ),
					'type'      => 'numeric-slider',
                    'std'          => '1',
                    'min_max_step' => '1,4,1',
					'section' => 'manga_single'
				),
                
				array(
					'id'      => 'manga_single_related_items_layout',
					'label'   => esc_html__( 'Manga Single - Related Items Layout', 'madara' ),
					'desc'    => esc_html__( 'Choose layout for Manga Related Items', 'madara' ),
					'std'     => 1,
					'type'    => 'select',
					'section' => 'manga_single',
					'choices' => array(
						array(
							'value' => 1,
							'label' => esc_html__( 'Default (small thumbnail)', 'madara' ),
						),
						array(
							'value' => 2,
							'label' => esc_html__( 'Big Thumbnail', 'madara' ),
						)
					),
				),                
                
                array(
					'id'      => 'manga_single_related_items_count',
					'label'   => esc_html__( 'Manga Single - Number of Related Items', 'madara' ),
					'desc'    => esc_html__( 'Choose number of related items to display', 'madara' ),
					'type'      => 'select',
                    'std'          => '4',
                    'choices' => array(
						array(
							'value' => 3,
							'label' => 3,
						),
						array(
							'value' => 4,
							'label' => 4,
						),
						array(
							'value' => 6,
							'label' => 6,
						)
					),
					'section' => 'manga_single'
				),
                
                array(
					'id'      => 'manga_single_related_item_mobile_width',
					'label'   => esc_html__( 'Item Width on Mobile Screen', 'madara' ),
					'desc'    => esc_html__( 'Set item width when viewing on mobile screens', 'madara' ),
					'std'     => 100,
					'type'    => 'select',
					'section' => 'manga_single',
					'choices' => array(
						array(
							'value' => 100,
							'label' => esc_html__( '1/1 - 100% screen width', 'madara' ),
						),
						array(
							'value' => 50,
							'label' => esc_html__( '1/2 - 50% screen width', 'madara' ),
						)
					)
				),
                
				array(
					'id'      => 'manga_rank_views',
					'label'   => esc_html__( 'Manga Views Display', 'madara' ),
					'desc'    => esc_html__( 'Display monthly views or all time views', 'madara' ),
					'std'     => 'monthly',
					'type'    => 'select',
					'section' => 'manga_single',
					'choices' => array(
						array(
							'value' => 'monthly',
							'label' => esc_html__( 'Monthly', 'madara' ),
						),
						array(
							'value' => 'alltime',
							'label' => esc_html__( 'All Time', 'madara' ),
						)
					),
				),

				array(
					'id'        => 'seo_manga_title',
					'label'     => esc_html__( 'SEO - Manga Title', 'madara' ),
					'desc'      => esc_html__( 'Custom Title Meta for Single Manga page. Use tag %title% for current Manga Title. When using with Yoast SEO, this will override the meta title in Yoast', 'madara' ),
					'std'       => '',
					'type'      => 'text',
					'section'   => 'manga_single',
				),

				array(
					'id'        => 'seo_manga_desc',
					'label'     => esc_html__( 'SEO - Manga Description', 'madara' ),
					'desc'      => esc_html__( 'Custom Description Meta for Single Manga page. Use tag %title% for current Manga Title. When using with Yoast SEO, this will override the meta description in Yoast', 'madara' ),
					'std'       => '',
					'type'      => 'text',
					'section'   => 'manga_single',
				),

				array(
					'id'        => 'seo_chapter_title',
					'label'     => esc_html__( 'SEO - Manga Chapter Title', 'madara' ),
					'desc'      => esc_html__( 'Custom Title Meta for Single Manga Reading page. Use tag %title% for current Manga Title, %chapter% for current Manga Chapter, %chapter_index% for current Chapter Index, %vol% for volumn name. When using with Yoast SEO, this will override the meta title in Yoast', 'madara' ),
					'std'       => '',
					'type'      => 'text',
					'section'   => 'manga_single',
				),

				array(
					'id'        => 'seo_chapter_desc',
					'label'     => esc_html__( 'SEO - Manga Chapter Description', 'madara' ),
					'desc'      => esc_html__( 'Custom Description Meta for Single Manga Reading page. Use tag %title% for current Manga Title, %chapter% for current Manga Chapter, %chapter_index% for current Chapter Index, %summary% for Manga excerpt or first paragraph in a Novel chapter. When using with Yoast SEO, this will override the meta description in Yoast', 'madara' ),
					'std'       => '',
					'type'      => 'text',
					'section'   => 'manga_single',
				),
				
				array(
					'id'      => 'manga_reading_discussion',
					'label'   => esc_html__( 'Enable Reading Discussion', 'madara' ),
					'desc'    => esc_html__( 'Turn On/Off Reading Discussion for Manga Reading Page. Default Off.', 'madara' ),
					'std'     => 'on',
					'type'    => 'on-off',
					'section' => 'manga_reading',
				),
				
				array(
					'id'      => 'manga_reading_discussion_heading',
					'label'   => esc_html__( 'Enable Reading Discussion Heading', 'madara' ),
					'desc'    => esc_html__( 'Show heading for the Comments Form', 'madara' ),
					'std'     => 'on',
					'type'    => 'on-off',
					'section' => 'manga_reading',
					'condition' => 'manga_reading_discussion:is(on)',
				),
				
				array(
					'id'        => 'manga_reading_page_sidebar',
					'label'     => esc_html__( 'Manga Reading Page Sidebar', 'madara' ),
					'desc'      => '',
					'std'       => 'right',
					'type'      => 'radio-image',
					'section'   => 'manga_reading',
					'choices'   => array(
						array(
							'value' => 'left',
							'label' => esc_html__( 'Left', 'madara' ),
							'src'   => get_parent_theme_file_uri( '/images/options/sidebar/sidebar-left.png' ),
						),
						array(
							'value' => 'right',
							'label' => esc_html__( 'Right', 'madara' ),
							'src'   => get_parent_theme_file_uri( '/images/options/sidebar/sidebar-right.png' ),
						),
						array(
							'value' => 'full',
							'label' => esc_html__( 'Hidden', 'madara' ),
							'src'   => get_parent_theme_file_uri( '/images/options/sidebar/sidebar-hidden.png' ),
						)
					),
					'condition' => 'manga_reading_discussion:is(on)',
				),
				
				array(
					'id'        => 'manga_reading_text_sidebar',
					'label'     => esc_html__( 'Manga Text Chapter - Side Column', 'madara' ),
					'desc'      => esc_html__('In Text Chapter reading page, move sidebar & discussion to the side column, instead of at bottom of content', 'madara'),
					'std'       => 'off',
					'type'      => 'on-off',
					'section'   => 'manga_reading'
				),
				
				array(
					'id'        => 'chapter_heading',
					'label'     => esc_html__( 'Chapter Heading', 'madara' ),
					'desc'      => esc_html__('Show Chapter Heading', 'madara'),
					'std'       => 'on',
					'type'      => 'on-off',
					'section'   => 'manga_reading'
				),
				
				array(
					'id'        => 'minimal_reading_page',
					'label'     => esc_html__( 'Minimal Reading Layout', 'madara' ),
					'desc'      => esc_html__('Hide header and other parts to focus in reading content', 'madara'),
					'std'       => 'off',
					'type'      => 'on-off',
					'section'   => 'manga_reading'
				),
				
				array(
					'id'        => 'manga_reading_text_fontsize',
					'label'     => esc_html__( 'Manga Text Chapter - Font Size', 'madara' ),
					'desc'      => esc_html__('Set font size (in pixels) for text. By default, it takes global font-size', 'madara'),
					'std'       => '',
					'type'      => 'text',
					'section'   => 'manga_reading'
				),

				array(
					'id'      => 'manga_reading_style',
					'label'   => esc_html__( 'Manga Image Chapter - Reading Style', 'madara' ),
					'desc'    => esc_html__( 'Choose reading style for Image Chapter', 'madara' ),
					'std'     => 'paged',
					'type'    => 'select',
					'section' => 'manga_reading',
					'choices' => array(
						array(
							'value' => 'paged',
							'label' => esc_html__( 'Paged', 'madara' ),
						),
						array(
							'value' => 'list',
							'label' => esc_html__( 'List', 'madara' ),
						),
					),
				),
				
				array(
					'id'      => 'manga_chapters_select_order',
					'label'   => esc_html__( 'Chapters Order in Reading Navigation', 'madara' ),
					'desc'    => esc_html__( 'Should we keep the order in detail page, or reverse it?', 'madara' ),
					'std'     => 'default',
					'type'    => 'select',
					'section' => 'manga_reading',
					'choices' => array(
						array(
							'value' => 'default',
							'label' => esc_html__( 'Use Chapters Order in Detail page', 'madara' ),
						),
						array(
							'value' => 'reverse',
							'label' => esc_html__( 'Reverse', 'madara' ),
						),
					),
				),

				array(
					'id'        => 'manga_reading_content_gaps',
					'label'     => esc_html__( 'Enable Gaps', 'madara' ),
					'desc'      => esc_html__( 'Enable Gaps between the images in Reading List Style', 'madara' ),
					'std'       => 'on',
					'type'      => 'on-off',
					'section'   => 'manga_reading',
					'condition' => 'manga_reading_style:is(list)',
				),

				array(
					'id'        => 'manga_reading_images_per_page',
					'label'     => esc_html__( 'Images Per Page', 'madara' ),
					'desc'      => '',
					'std'       => '1',
					'type'      => 'select',
					'section'   => 'manga_reading',
					'choices'   => array(
						array(
							'value' => '1',
							'label' => esc_html__( '1 image', 'madara' ),
						),
						array(
							'value' => '3',
							'label' => esc_html__( '3 images', 'madara' ),
						),
						array(
							'value' => '6',
							'label' => esc_html__( '6 images', 'madara' ),
						),
						array(
							'value' => '10',
							'label' => esc_html__( '10 images', 'madara' ),
						),
					),
					'condition' => 'manga_reading_style:is(paged)',
				),
				
				array(
					'id'        => 'manga_reading_full_width',
					'label'     => esc_html__( 'Full Width (No Left/Right Padding)', 'madara' ),
					'desc'      => esc_html__( 'Disable Left/Right padding when reading chapter', 'madara' ),
					'std'       => 'on',
					'type'      => 'on-off',
					'section'   => 'manga_reading'
				),

				array(
					'id'      => 'manga_reading_related',
					'label'   => esc_html__( 'Enable Related Manga', 'madara' ),
					'desc'    => esc_html__( 'Turn On/Off Related Manga in Reading Page.', 'madara' ),
					'std'     => 'on',
					'type'    => 'on-off',
					'section' => 'manga_reading',
				),

				array(
					'id'        => 'manga_page_reading_ajax',
					'label'     => esc_html__( 'Page Reading Ajax', 'madara' ),
					'desc'      => '',
					'std'       => 'on',
					'type'      => 'on-off',
					'section'   => 'manga_reading',
					'condition' => 'manga_reading_style:not(list)',
					'desc'      => esc_html__( 'Use Ajax instead of redirecting URL when go to next page on chapter', 'madara' )
				),

				array(
					'id'      => 'manga_reading_preload_images',
					'label'   => esc_html__( 'Preload Images', 'madara' ),
					'desc'    => '',
					'std'     => 'on',
					'type'    => 'on-off',
					'section' => 'manga_reading',
					'desc'    => esc_html__( 'Use preloaded images for chapter without reloading or using ajax to get next/prev image', 'madara' )
				),
				
				array(
					'id'      => 'manga_reading_sticky_header',
					'label'   => esc_html__( 'Sticky Header', 'madara' ),
					'desc'    => '',
					'std'     => '',
					'type'    => 'select',
					'section' => 'manga_reading',
					'choices'   => array(
						array(
							'value' => '',
							'label' => esc_html__( 'Default (use setting in Theme Options > Header > Sticky Menu', 'madara' ),
						),
						array(
							'value' => 'on',
							'label' => esc_html__( 'Yes', 'madara' ),
						),
						array(
							'value' => 'off',
							'label' => esc_html__( 'No', 'madara' ),
						)
					),
				),

				array(
					'id'      => 'manga_reading_sticky_navigation',
					'label'   => esc_html__( 'Sticky Chapter Navigation (Wide Screens)', 'madara' ),
					'desc'    => '',
					'std'     => 'on',
					'type'    => 'on-off',
					'section' => 'manga_reading',
					'condition' => 'manga_reading_sticky_header:not(off)'
				),
				
				array(
					'id'      => 'manga_reading_sticky_navigation_mobile',
					'label'   => esc_html__( 'Enable Sticky Chapter Navigation for mobile screens ( < 768px)', 'madara' ),
					'desc'    => '',
					'std'     => 'off',
					'type'    => 'on-off',
					'section' => 'manga_reading',
					'condition' => 'manga_reading_sticky_header:not(off)'
				),

				array(
					'id'        => 'manga_reading_navigation_by_pointer',
					'label'     => esc_html__( 'Next & Prev page by Pointer position', 'madara' ),
					'desc'      => '',
					'std'       => 'on',
					'type'      => 'on-off',
					'section'   => 'manga_reading',
					'condition' => 'manga_reading_style:is(paged)',
				),

				array(
					'id'      => 'manga_reading_social_share',
					'label'   => esc_html__( 'Social Sharing', 'madara' ),
					'desc'    => esc_html__( 'Enable Social Sharing. Required plugin: ', 'madara' ) . '<a href="https://wordpress.org/plugins/accesspress-social-share/" target="_blank">AccessPress Social Share</a>',
					'std'     => 'off',
					'type'    => 'on-off',
					'section' => 'manga_reading',
				),

				array(
					'id'      => 'madara_disable_imagetoolbar',
					'label'   => esc_html__( 'Disable Image "Save image as"', 'madara' ),
					'desc'    => esc_html__( 'This setting will remove "Save image as" from mouse right click menu on Manga Reading Page', 'madara' ),
					'std'     => 'off',
					'type'    => 'on-off',
					'section' => 'manga_reading',
				),

				array(
					'id'      => 'madara_reading_history',
					'label'   => esc_html__( 'Manga Reading History', 'madara' ),
					'desc'    => esc_html__( 'Save Manga to user reading history when user\'s reading a chapter.', 'madara' ),
					'std'     => 'on',
					'type'    => 'on-off',
					'section' => 'manga_reading',
				),

				array(
					'id'      => 'madara_reading_history_delay',
					'label'   => esc_html__( 'Manga Reading History Delay', 'madara' ),
					'desc'    => esc_html__( 'how many seconds should we wait user to read the chapter before saving chapter into reading history', 'madara' ),
					'std'     => '5',
					'type'    => 'text',
					'section' => 'manga_reading',
					'condition' => 'madara_reading_history:is(on)',
				),

				array(
					'id'      => 'madara_reading_history_items',
					'label'   => esc_html__( 'Manga Reading History Items', 'madara' ),
					'desc'    => esc_html__( 'Number of Manga Items at most to be saved in Manga Reading History. If you want to store unlimited number of items, enter -1. Please note that you have a lot of mangas, it would effect performance', 'madara' ),
					'std'     => '12',
					'type'    => 'text',
					'section' => 'manga_reading',
					'condition' => 'madara_reading_history:is(on)',
				),
				array(
					'id'      => 'manga_search_exclude_tags',
					'label'   => esc_html__( 'Manga Search - Exclude Tags', 'madara' ),
					'desc'    => esc_html__( 'Exclude mangas from Search Results if they have these tags. Enter a list of tag slug, separated by comma', 'madara' ),
					'type'    => 'text',
					'section' => 'search',
				),
				
				array(
					'id'      => 'manga_search_exclude_genres',
					'label'   => esc_html__( 'Manga Search - Exclude Genres', 'madara' ),
					'desc'    => esc_html__( 'Exclude mangas from Search Results if they have these genres. Enter a list of genre slug, separated by comma', 'madara' ),
					'type'    => 'text',
					'section' => 'search',
				),
				
				array(
					'id'      => 'manga_search_exclude_authors',
					'label'   => esc_html__( 'Manga Search - Exclude Authors', 'madara' ),
					'desc'    => esc_html__( 'Exclude mangas from Search Results if they belong to these authors. Enter a list of author slug, separated by comma', 'madara' ),
					'type'    => 'text',
					'section' => 'search',
				),
                
                array(
					'id'      => 'madara_ajax_search',
					'label'   => esc_html__( 'Ajax Search', 'madara' ),
					'desc'    => esc_html__( 'Enable or Disable Ajax Search for Manga', 'madara' ),
					'std'     => 'on',
					'type'    => 'on-off',
					'section' => 'search',
				),
			)
		);
        
        
    
        /* Support polylang */
        if(function_exists('PLL')){
            $custom_settings['settings'][] = array(
                'id'      => 'polylang_footer',
                'label'   => esc_html__('Show Polylang Languages Switcher in Footer','madara'),
                'desc'    => '',
                'std'     => 'on',
                'type'    => 'on-off',
                'section' => 'misc',
            );
        }
        
        $custom_settings['sections'][] = array(
                'id'    => 'user_settings',
                'title' => '<i class="fas fa-user-cog"></i>' . esc_html__( 'User Settings', 'madara' ),
            );
        
        $custom_settings['settings'][] = array(
            'id'      => 'user_settings_weak_password',
            'label'   => esc_html__('Require strong Password','madara'),
            'desc'    => esc_html__('Force user to use strong password','madara'),
            'std'     => 'on',
            'type'    => 'on-off',
            'section' => 'user_settings',
        );
        
        /** Support Speaker plugin */
        if( class_exists( '\Merkulove\Speaker\SpeakerCaster' ) ){
            $custom_settings['sections'][] = array(
                'id'    => 'manga_speaker',
                'title' => '<i class="fas fa-file-audio"><!-- --></i>' . esc_html__( 'Speaker', 'madara' ),
            );
            
            $custom_settings['settings'][] = array(
                'id'      => 'speaker_sized',
                'label'   => esc_html__('Player Size','madara'),
                'desc'    => esc_html__('Size of the audio player','madara'),
                'std'     => '',
                'type'    => 'select',
				'choices'   => array(
						array(
							'value' => '',
							'label' => esc_html__( 'Full width', 'madara' ),
						),
						array(
							'value' => 'sized',
							'label' => esc_html__( 'Small Player', 'madara' ),
						)
					),
                'section' => 'manga_speaker',
            );
            
            $custom_settings['settings'][] = array(
                'id'      => 'speaker_position',
                'label'   => esc_html__('Player Position','madara'),
                'desc'    => '',
                'std'     => '',
                'type'    => 'select',
				'choices'   => array(
						array(
							'value' => 'floating',
							'label' => esc_html__( 'Floating (fixed position at bottom)', 'madara' ),
						),
						array(
							'value' => '',
							'label' => esc_html__( 'Before chapter content', 'madara' ),
						)
					),
                'section' => 'manga_speaker',
            );
        }
	}
