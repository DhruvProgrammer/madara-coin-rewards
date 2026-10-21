<?php

CONST MADARA_SAMPLE_DATA_URL = 'https://live.mangabooth.com/sampledata';

if(!class_exists('madara_UNYSON_BACKUP')){
	class madara_UNYSON_BACKUP
	{
		function __construct(){
			// empty class to trick the sample data tab
		}
	}
	
}

if(!class_exists('madara_sampledata_installer')){
	class madara_sampledata_installer{
		function __construct(){
			add_action( 'wp_ajax_madara_install_data', array($this, 'ajax_install_data' ));
			add_action('madara_welcome_importdata_tab_content', array($this, '_sample_data_page'));
		}
		
		function ajax_install_data(){
			// create home page	
			$args = array(
						'post_content' => '',
						'post_type' => 'page',
						'post_title' => 'Home Page',
						'post_status' => 'publish'
					);
			$frontpage_id = wp_insert_post($args);
			
			update_post_meta( $frontpage_id, 'custom_sidebar_settings', 'on' );
			update_post_meta( $frontpage_id, 'page_content', 'manga' );
			update_post_meta( $frontpage_id, 'page_sidebar', 'right' );
			update_post_meta( $frontpage_id, 'main_top_sidebar_background', array('background-color' => '#000') );
			update_post_meta( $frontpage_id, 'main_top_sidebar_container', 'full_width' );
			update_post_meta( $frontpage_id, 'main_top_sidebar_spacing', '15 0 1 0' );
			update_post_meta( $frontpage_id, 'main_top_second_sidebar_spacing', '1 0 0 0' );
			update_post_meta( $frontpage_id, 'main_top_second_sidebar_background', array('background-color' => '#000') );
			update_post_meta( $frontpage_id, 'page_title', 'off' );
			update_post_meta( $frontpage_id, 'page_meta_tags', 'off' );
			update_post_meta( $frontpage_id, '_wp_page_template', 'page-templates/front-page.php' );
		
			$args = array(
				'post_content' => '-- Manga Listing --',
				'post_type' => 'page',
				'post_title' => 'New',
				'post_status' => 'publish'
			);
			$newpage_id = wp_insert_post($args);
		
			update_post_meta( $newpage_id, 'page_content', 'manga' );
			update_post_meta( $newpage_id, 'page_sidebar', 'full' );
			update_post_meta( $newpage_id, 'manga_archives_item_layout', 'small_thumbnail' );
			update_post_meta( $newpage_id, 'archive_heading_text', 'Latest Updates' );
			update_post_meta( $newpage_id, '_wp_page_template', 'page-templates/front-page.php' );
			update_post_meta( $newpage_id, 'page_post_orderby', 'modified' );
			
			$args = array(
				'post_content' => '-- Manga Listing --',
				'post_type' => 'page',
				'post_title' => 'Ranking',
				'post_status' => 'publish'
			);
			$rankingpage_id = wp_insert_post($args);
		
			update_post_meta( $rankingpage_id, 'page_content', 'manga' );
			update_post_meta( $rankingpage_id, 'page_sidebar', 'full' );
			update_post_meta( $rankingpage_id, 'manga_archives_item_layout', 'big_thumbnail' );
			update_post_meta( $rankingpage_id, 'archive_heading_text', 'Top Views' );    
			update_post_meta( $rankingpage_id, '_wp_page_template', 'page-templates/front-page.php' );
			update_post_meta( $rankingpage_id, 'page_post_orderby', 'views' );
			update_post_meta( $rankingpage_id, 'page_custom_css', 'ranking' );
			
			$args = array(
				'post_content' => '-- Blog Page --',
				'post_type' => 'page',
				'post_title' => 'Blog',
				'post_status' => 'publish'
			);
			$blog_pageid = wp_insert_post($args);
		
			update_post_meta( $blog_pageid, 'page_sidebar', 'right' );

			$args = array(
				'post_content' => '-- About Us --',
				'post_type' => 'page',
				'post_title' => 'About Us',
				'post_status' => 'publish'
			);
			$aboutus_pageid = wp_insert_post($args);
			
			$settings = get_option( 'wp_manga_settings' , array() );
			$settings['manga_archive_page'] = $newpage_id;
			$resp = update_option( 'wp_manga_settings', $settings );
		
			// Widgets settings
			$widgets = '{"manga_archive_sidebar":{"manga-history-id-2":{"title":"MY READING HISTORY","number_of_posts":"3","widget_logic":"!is_page(\'contact\') && !is_page(\'about-us\')"}},"manga_single_sidebar":{"manga-recent-3":{"title":"POPULAR MANGA","number_of_post":"6","genre":"","author":"","artist":"","release":"","order_by":"latest","time_range":"all","order":"desc","style":"style-1","button":"Here for more Popular Manga","url":"\/manga\/?m_orderby=trending","widget_logic":"!is_page(\'contact\') && !is_page(\'about-us\')"}},"manga_reading_sidebar":{"custom_html-2":{"title":"Madara Info","content":"<p>\r\nMadara stands as a beacon for those desiring to craft a captivating online comic and manga reading platform on WordPress\r\n<\/p>\r\n<p>\r\n\tFor custom work request, please send email to wpstylish(at)gmail(dot)com\r\n<\/p>","widget_logic":""}},"main_sidebar":{"manga-search-3":{"title":"","search_advanced":"Advanced","widget_logic":""},"manga-recent-4":{"title":"Editor choices","number_of_post":"5","genre":"","author":"","artist":"","release":"","order_by":"random","time_range":"all","order":"desc","style":"style-1","button":"View All","url":"https:\/\/live.mangabooth.com\/manga\/","show_volume":"yes"},"wp_manga_release_id-2":{"title":"Titles by years","exclude":"","number":"20","go_release":"true","widget_logic":""}},"search_sidebar":{"manga-search-4":{"title":"","search_advanced":"Advanced"}},"top_sidebar":{"manga-slider-2":{"title":"","number_of_post":"7","number_to_show":"3","genre":"","manga_tags":"","author":"","artist":"","release":"","order_by":"latest","manga_type":"","order":"desc","style":"style-2","autoplay":"1","timerange":"all","extended_widget_opts-manga-slider-6":{"id_base":"manga-slider-6","visibility":{"main":"","options":"hide","selected":"0","misc":{"home":"1"},"pages":["12","337","498","849"],"tax_terms_page":{"category":"1"}},"devices":{"options":"hide"},"alignment":{"desktop":"default"},"roles":{"state":""},"styling":{"bg_image":""},"class":{"selected":"0","id":"","classes":"","logic":""},"tabselect":"0"}}},"top_second_sidebar":{"manga-popular-slider-2":{"title":"Popular Series","number_of_post":"5","number_to_show":"4","genre":"","author":"","artist":"","release":"","order_by":"latest","order":"desc","style":"style-1","manga_type":"","manga_tags":"","autoplay":"1","timerange":"all","extended_widget_opts-manga-popular-slider-6":{"id_base":"manga-popular-slider-6","visibility":{"main":"","options":"hide","selected":"0","pages":["498","849"],"tax_terms_page":{"category":"1"}},"devices":{"options":"hide"},"alignment":{"desktop":"default"},"roles":{"state":""},"styling":{"bg_image":""},"class":{"selected":"0","id":"","classes":"","logic":""},"tabselect":"0"}}},"footer_sidebar":{"manga-genres-id-2":{"title":"All Genres","exclude_genre":"","show_manga_counts":"true","layout":"layout-2"}}}';
		
			$widgets_json = json_decode($widgets);   // Decode file contents to JSON data.
			$wie_import_results = $this->wie_import_data($widgets_json);
		
			// Theme Options settings
			$to_settings = 'YToyNTc6e3M6MTA6ImxvZ29faW1hZ2UiO3M6MDoiIjtzOjE1OiJsb2dvX2ltYWdlX3NpemUiO3M6MDoiIjtzOjE3OiJyZXRpbmFfbG9nb19pbWFnZSI7czowOiIiO3M6MTY6ImxvZ2luX2xvZ29faW1hZ2UiO3M6MDoiIjtzOjExOiJib2R5X3NjaGVtYSI7czo0OiJkYXJrIjtzOjI2OiJtYWluX3RvcF9zaWRlYmFyX2NvbnRhaW5lciI7czo5OiJjb250YWluZXIiO3M6Mjc6Im1haW5fdG9wX3NpZGViYXJfYmFja2dyb3VuZCI7YTo2OntzOjE2OiJiYWNrZ3JvdW5kLWNvbG9yIjtzOjA6IiI7czoxNzoiYmFja2dyb3VuZC1yZXBlYXQiO3M6MDoiIjtzOjIxOiJiYWNrZ3JvdW5kLWF0dGFjaG1lbnQiO3M6MDoiIjtzOjE5OiJiYWNrZ3JvdW5kLXBvc2l0aW9uIjtzOjA6IiI7czoxNToiYmFja2dyb3VuZC1zaXplIjtzOjA6IiI7czoxNjoiYmFja2dyb3VuZC1pbWFnZSI7czowOiIiO31zOjI0OiJtYWluX3RvcF9zaWRlYmFyX3NwYWNpbmciO3M6MDoiIjtzOjMzOiJtYWluX3RvcF9zZWNvbmRfc2lkZWJhcl9jb250YWluZXIiO3M6OToiY29udGFpbmVyIjtzOjM0OiJtYWluX3RvcF9zZWNvbmRfc2lkZWJhcl9iYWNrZ3JvdW5kIjthOjY6e3M6MTY6ImJhY2tncm91bmQtY29sb3IiO3M6MDoiIjtzOjE3OiJiYWNrZ3JvdW5kLXJlcGVhdCI7czowOiIiO3M6MjE6ImJhY2tncm91bmQtYXR0YWNobWVudCI7czowOiIiO3M6MTk6ImJhY2tncm91bmQtcG9zaXRpb24iO3M6MDoiIjtzOjE1OiJiYWNrZ3JvdW5kLXNpemUiO3M6MDoiIjtzOjE2OiJiYWNrZ3JvdW5kLWltYWdlIjtzOjA6IiI7fXM6MzE6Im1haW5fdG9wX3NlY29uZF9zaWRlYmFyX3NwYWNpbmciO3M6MDoiIjtzOjI5OiJtYWluX2JvdHRvbV9zaWRlYmFyX2NvbnRhaW5lciI7czo5OiJjb250YWluZXIiO3M6MzA6Im1haW5fYm90dG9tX3NpZGViYXJfYmFja2dyb3VuZCI7YTo2OntzOjE2OiJiYWNrZ3JvdW5kLWNvbG9yIjtzOjA6IiI7czoxNzoiYmFja2dyb3VuZC1yZXBlYXQiO3M6MDoiIjtzOjIxOiJiYWNrZ3JvdW5kLWF0dGFjaG1lbnQiO3M6MDoiIjtzOjE5OiJiYWNrZ3JvdW5kLXBvc2l0aW9uIjtzOjA6IiI7czoxNToiYmFja2dyb3VuZC1zaXplIjtzOjA6IiI7czoxNjoiYmFja2dyb3VuZC1pbWFnZSI7czowOiIiO31zOjI3OiJtYWluX2JvdHRvbV9zaWRlYmFyX3NwYWNpbmciO3M6MDoiIjtzOjIyOiJsb2dpbl9wb3B1cF9iYWNrZ3JvdW5kIjthOjY6e3M6MTY6ImJhY2tncm91bmQtY29sb3IiO3M6MDoiIjtzOjE3OiJiYWNrZ3JvdW5kLXJlcGVhdCI7czowOiIiO3M6MjE6ImJhY2tncm91bmQtYXR0YWNobWVudCI7czowOiIiO3M6MTk6ImJhY2tncm91bmQtcG9zaXRpb24iO3M6MDoiIjtzOjE1OiJiYWNrZ3JvdW5kLXNpemUiO3M6MDoiIjtzOjE2OiJiYWNrZ3JvdW5kLWltYWdlIjtzOjA6IiI7fXM6MTg6InNpdGVfY3VzdG9tX2NvbG9ycyI7czozOiJvZmYiO3M6MTA6Im1haW5fY29sb3IiO3M6MDoiIjtzOjE0OiJtYWluX2NvbG9yX2VuZCI7czowOiIiO3M6MTY6ImxpbmtfY29sb3JfaG92ZXIiO3M6MDoiIjtzOjEwOiJzdGFyX2NvbG9yIjtzOjA6IiI7czoxOToiaG90X2JhZGdlc19iZ19jb2xvciI7czowOiIiO3M6MTk6Im5ld19iYWRnZXNfYmdfY29sb3IiO3M6MDoiIjtzOjIyOiJjdXN0b21fYmFkZ2VzX2JnX2NvbG9yIjtzOjA6IiI7czo2OiJidG5fYmciO3M6MDoiIjtzOjk6ImJ0bl9jb2xvciI7czowOiIiO3M6MTI6ImJ0bl9ob3Zlcl9iZyI7czowOiIiO3M6MTU6ImJ0bl9ob3Zlcl9jb2xvciI7czowOiIiO3M6MjA6ImhlYWRlcl9jdXN0b21fY29sb3JzIjtzOjM6Im9mZiI7czoxNDoibmF2X2l0ZW1fY29sb3IiO3M6MDoiIjtzOjIwOiJuYXZfaXRlbV9ob3Zlcl9jb2xvciI7czowOiIiO3M6MTA6Im5hdl9zdWJfYmciO3M6MDoiIjtzOjIzOiJuYXZfc3ViX2JnX2JvcmRlcl9jb2xvciI7czowOiIiO3M6MTg6Im5hdl9zdWJfaXRlbV9jb2xvciI7czowOiIiO3M6MjQ6Im5hdl9zdWJfaXRlbV9ob3Zlcl9jb2xvciI7czowOiIiO3M6MjE6Im5hdl9zdWJfaXRlbV9ob3Zlcl9iZyI7czowOiIiO3M6Mjc6ImhlYWRlcl9ib3R0b21fY3VzdG9tX2NvbG9ycyI7czozOiJvZmYiO3M6MTY6ImhlYWRlcl9ib3R0b21fYmciO3M6MDoiIjtzOjIxOiJib3R0b21fbmF2X2l0ZW1fY29sb3IiO3M6MDoiIjtzOjI3OiJib3R0b21fbmF2X2l0ZW1faG92ZXJfY29sb3IiO3M6MDoiIjtzOjE3OiJib3R0b21fbmF2X3N1Yl9iZyI7czowOiIiO3M6MjU6ImJvdHRvbV9uYXZfc3ViX2l0ZW1fY29sb3IiO3M6MDoiIjtzOjMxOiJib3R0b21fbmF2X3N1Yl9pdGVtX2hvdmVyX2NvbG9yIjtzOjA6IiI7czoyODoiYm90dG9tX25hdl9zdWJfYm9yZGVyX2JvdHRvbSI7czowOiIiO3M6MjQ6Im1vYmlsZV9tZW51X2N1c3RvbV9jb2xvciI7czozOiJvZmYiO3M6Mjc6Im1vYmlsZV9icm93c2VyX2hlYWRlcl9jb2xvciI7czowOiIiO3M6MjI6ImNhbnZhc19tZW51X2JhY2tncm91bmQiO3M6MDoiIjtzOjE3OiJjYW52YXNfbWVudV9jb2xvciI7czowOiIiO3M6MTc6ImNhbnZhc19tZW51X2hvdmVyIjtzOjA6IiI7czoxOToiZ29vZ2xlX2ZvbnRfYXBpX2tleSI7czowOiIiO3M6MTc6ImZvbnRfdXNpbmdfY3VzdG9tIjtzOjM6Im9mZiI7czoxOToibWFpbl9mb250X29uX2dvb2dsZSI7czoyOiJvbiI7czoyMzoibWFpbl9mb250X2dvb2dsZV9mYW1pbHkiO3M6MDoiIjtzOjE2OiJtYWluX2ZvbnRfZmFtaWx5IjtzOjA6IiI7czoxNDoibWFpbl9mb250X3NpemUiO3M6MjoiMTQiO3M6MTY6Im1haW5fZm9udF93ZWlnaHQiO3M6Njoibm9ybWFsIjtzOjIxOiJtYWluX2ZvbnRfbGluZV9oZWlnaHQiO3M6MzoiMS41IjtzOjIyOiJoZWFkaW5nX2ZvbnRfb25fZ29vZ2xlIjtzOjI6Im9uIjtzOjI2OiJoZWFkaW5nX2ZvbnRfZ29vZ2xlX2ZhbWlseSI7czowOiIiO3M6MTk6ImhlYWRpbmdfZm9udF9mYW1pbHkiO3M6MDoiIjtzOjIwOiJoZWFkaW5nX2ZvbnRfc2l6ZV9oMSI7czoyOiIzNCI7czoxNDoiaDFfbGluZV9oZWlnaHQiO3M6MzoiMS4yIjtzOjE0OiJoMV9mb250X3dlaWdodCI7czozOiI2MDAiO3M6MjA6ImhlYWRpbmdfZm9udF9zaXplX2gyIjtzOjI6IjMwIjtzOjE0OiJoMl9saW5lX2hlaWdodCI7czozOiIxLjIiO3M6MTQ6ImgyX2ZvbnRfd2VpZ2h0IjtzOjM6IjYwMCI7czoyMDoiaGVhZGluZ19mb250X3NpemVfaDMiO3M6MjoiMjQiO3M6MTQ6ImgzX2xpbmVfaGVpZ2h0IjtzOjM6IjEuNCI7czoxNDoiaDNfZm9udF93ZWlnaHQiO3M6MzoiNjAwIjtzOjIwOiJoZWFkaW5nX2ZvbnRfc2l6ZV9oNCI7czoyOiIxOCI7czoxNDoiaDRfbGluZV9oZWlnaHQiO3M6MzoiMS4yIjtzOjE0OiJoNF9mb250X3dlaWdodCI7czozOiI2MDAiO3M6MjA6ImhlYWRpbmdfZm9udF9zaXplX2g1IjtzOjI6IjE2IjtzOjE0OiJoNV9saW5lX2hlaWdodCI7czozOiIxLjIiO3M6MTQ6Img1X2ZvbnRfd2VpZ2h0IjtzOjM6IjYwMCI7czoyMDoiaGVhZGluZ19mb250X3NpemVfaDYiO3M6MjoiMTQiO3M6MTQ6Img2X2xpbmVfaGVpZ2h0IjtzOjM6IjEuMiI7czoxNDoiaDZfZm9udF93ZWlnaHQiO3M6MzoiNTAwIjtzOjI1OiJuYXZpZ2F0aW9uX2ZvbnRfb25fZ29vZ2xlIjtzOjI6Im9uIjtzOjI5OiJuYXZpZ2F0aW9uX2ZvbnRfZ29vZ2xlX2ZhbWlseSI7czowOiIiO3M6MjI6Im5hdmlnYXRpb25fZm9udF9mYW1pbHkiO3M6MDoiIjtzOjIwOiJuYXZpZ2F0aW9uX2ZvbnRfc2l6ZSI7czoyOiIxNCI7czoyMjoibmF2aWdhdGlvbl9mb250X3dlaWdodCI7czozOiI0MDAiO3M6MTk6Im1ldGFfZm9udF9vbl9nb29nbGUiO3M6Mjoib24iO3M6MjM6Im1ldGFfZm9udF9nb29nbGVfZmFtaWx5IjtzOjA6IiI7czoxNjoibWV0YV9mb250X2ZhbWlseSI7czowOiIiO3M6MTM6ImN1c3RvbV9mb250XzEiO3M6MDoiIjtzOjEzOiJjdXN0b21fZm9udF8yIjtzOjA6IiI7czoxMzoiY3VzdG9tX2ZvbnRfMyI7czowOiIiO3M6MTI6ImhlYWRlcl9zdHlsZSI7czoxOiIxIjtzOjEwOiJuYXZfc3RpY2t5IjtzOjE6IjEiO3M6MjA6ImhlYWRlcl9ib3R0b21fYm9yZGVyIjtzOjI6Im9uIjtzOjI4OiJoZWFkZXJfZGlzYWJsZV9sb2dpbl9idXR0b25zIjtzOjI6Im9uIjtzOjE1OiJhcmNoaXZlX3NpZGViYXIiO3M6NToicmlnaHQiO3M6MjA6ImFyY2hpdmVfaGVhZGluZ190ZXh0IjtzOjA6IiI7czoyMDoiYXJjaGl2ZV9oZWFkaW5nX2ljb24iO3M6MDoiIjtzOjE4OiJhcmNoaXZlX21hcmdpbl90b3AiO3M6MDoiIjtzOjIzOiJhcmNoaXZlX2NvbnRlbnRfY29sdW1ucyI7czoxOiIzIjtzOjE4OiJhcmNoaXZlX25hdmlnYXRpb24iO3M6NzoiZGVmYXVsdCI7czoxOToiYXJjaGl2ZV9icmVhZGNydW1icyI7czoyOiJvbiI7czoyODoiYXJjaGl2ZV9uYXZpZ2F0aW9uX3NhbWVfdGVybSI7czozOiJvZmYiO3M6MzI6ImFyY2hpdmVfbmF2aWdhdGlvbl90ZXJtX3RheG9ub215IjtzOjA6IiI7czoyMDoiYXJjaGl2ZV9wb3N0X2V4Y2VycHQiO3M6Mjoib24iO3M6MTQ6InNpbmdsZV9zaWRlYmFyIjtzOjU6InJpZ2h0IjtzOjE0OiJzaW5nbGVfZXhjZXJwdCI7czoyOiJvbiI7czoyMToic2luZ2xlX2ZlYXR1cmVkX2ltYWdlIjtzOjI6Im9uIjtzOjExOiJzaW5nbGVfdGFncyI7czoyOiJvbiI7czoxNDoicG9zdF9tZXRhX3RhZ3MiO3M6Mjoib24iO3M6MTU6InNpbmdsZV9jYXRlZ29yeSI7czoyOiJvbiI7czoxNDoiZW5hYmxlX2NvbW1lbnQiO3M6Mjoib24iO3M6MTg6InNpbmdsZV9yZXZlcnNlX25hdiI7czozOiJvZmYiO3M6MTI6InBhZ2Vfc2lkZWJhciI7czo1OiJyaWdodCI7czoxNDoicGFnZV9tZXRhX3RhZ3MiO3M6Mjoib24iO3M6MTM6InBhZ2VfY29tbWVudHMiO3M6Mjoib24iO3M6MjQ6InNlYXJjaF9oZWFkZXJfYmFja2dyb3VuZCI7YTo2OntzOjE2OiJiYWNrZ3JvdW5kLWNvbG9yIjtzOjc6IiMwYTBhMGEiO3M6MTc6ImJhY2tncm91bmQtcmVwZWF0IjtzOjA6IiI7czoyMToiYmFja2dyb3VuZC1hdHRhY2htZW50IjtzOjA6IiI7czoxOToiYmFja2dyb3VuZC1wb3NpdGlvbiI7czowOiIiO3M6MTU6ImJhY2tncm91bmQtc2l6ZSI7czowOiIiO3M6MTY6ImJhY2tncm91bmQtaW1hZ2UiO3M6MDoiIjt9czoyNToibWFuZ2Ffc2VhcmNoX2V4Y2x1ZGVfdGFncyI7czowOiIiO3M6Mjc6Im1hbmdhX3NlYXJjaF9leGNsdWRlX2dlbnJlcyI7czowOiIiO3M6Mjg6Im1hbmdhX3NlYXJjaF9leGNsdWRlX2F1dGhvcnMiO3M6MDoiIjtzOjE4OiJtYWRhcmFfYWpheF9zZWFyY2giO3M6Mjoib24iO3M6MTY6InBhZ2U0MDRfaGVhZF90YWciO3M6MDoiIjtzOjIyOiJwYWdlNDA0X2ZlYXR1cmVkX2ltYWdlIjtzOjA6IiI7czoxMzoicGFnZTQwNF90aXRsZSI7czowOiIiO3M6MTU6InBhZ2U0MDRfY29udGVudCI7czowOiIiO3M6ODoiZmFjZWJvb2siO3M6MDoiIjtzOjc6InR3aXR0ZXIiO3M6MDoiIjtzOjg6ImxpbmtlZGluIjtzOjA6IiI7czo2OiJ0dW1ibHIiO3M6MDoiIjtzOjExOiJnb29nbGUtcGx1cyI7czowOiIiO3M6OToicGludGVyZXN0IjtzOjA6IiI7czo3OiJ5b3V0dWJlIjtzOjA6IiI7czo2OiJmbGlja3IiO3M6MDoiIjtzOjg6ImRyaWJiYmxlIjtzOjA6IiI7czo3OiJiZWhhbmNlIjtzOjA6IiI7czo4OiJlbnZlbG9wZSI7czowOiIiO3M6MzoicnNzIjtzOjA6IiI7czoyNDoib3Blbl9zb2NpYWxfbGlua19uZXdfdGFiIjtzOjI6Im9uIjtzOjEwOiJhZHNlbnNlX2lkIjtzOjA6IiI7czozMToiYWRzZW5zZV9zbG90X2Fkc19iZWZvcmVfY29udGVudCI7czowOiIiO3M6MTg6ImFkc19iZWZvcmVfY29udGVudCI7czowOiIiO3M6MzA6ImFkc2Vuc2Vfc2xvdF9hZHNfYWZ0ZXJfY29udGVudCI7czowOiIiO3M6MTc6ImFkc19hZnRlcl9jb250ZW50IjtzOjA6IiI7czoyMzoiYWRzZW5zZV9zbG90X2Fkc19mb290ZXIiO3M6MDoiIjtzOjEwOiJhZHNfZm9vdGVyIjtzOjA6IiI7czoyNjoiYWRzZW5zZV9zbG90X2Fkc193YWxsX2xlZnQiO3M6MDoiIjtzOjEzOiJhZHNfd2FsbF9sZWZ0IjtzOjA6IiI7czoyNzoiYWRzZW5zZV9zbG90X2Fkc193YWxsX3JpZ2h0IjtzOjA6IiI7czoxNDoiYWRzX3dhbGxfcmlnaHQiO3M6MDoiIjtzOjI1OiJhZHNlbnNlX3Nsb3RfYWRzX3RvcF9wYWdlIjtzOjA6IiI7czoxMjoiYWRzX3RvcF9wYWdlIjtzOjEwNDoiPGEgaHJlZj0iIyI+PGltZyBzcmM9Imh0dHBzOi8vbGl2ZS5tYW5nYWJvb3RoLmNvbS90cC93cC1jb250ZW50L3VwbG9hZHMvMjAyMy8wMi90b3AtYmFubmVyLTEucG5nIiAvPjwvYT4iO3M6OToiY29weXJpZ2h0IjtzOjQwOiJNYWRhcmEgV29yZFByZXNzIFRoZW1lIGJ5IE1hbmdhYm9vdGguY29tIjtzOjE0OiJlY2hvX21ldGFfdGFncyI7czoyOiJvbiI7czo4OiJsYXp5bG9hZCI7czoyOiJvbiI7czoxMzoic2Nyb2xsX2VmZmVjdCI7czoyOiJvbiI7czo5OiJnb190b190b3AiO3M6Mjoib24iO3M6MTk6ImxvYWRpbmdfZm9udGF3ZXNvbWUiO3M6Mjoib24iO3M6MTY6ImxvYWRpbmdfaW9uaWNvbnMiO3M6Mjoib24iO3M6MTY6ImxvYWRpbmdfY3RfaWNvbnMiO3M6Mjoib24iO3M6MTA6ImN1c3RvbV9jc3MiO3M6MDoiIjtzOjE1OiJmYWNlYm9va19hcHBfaWQiO3M6MDoiIjtzOjExOiJzdGF0aWNfaWNvbiI7czowOiIiO3M6MTE6InByZV9sb2FkaW5nIjtzOjE6IjIiO3M6MTY6InByZV9sb2FkaW5nX2xvZ28iO3M6MDoiIjtzOjIwOiJwcmVfbG9hZGluZ19iZ19jb2xvciI7czowOiIiO3M6MjI6InByZV9sb2FkaW5nX2ljb25fY29sb3IiO3M6MDoiIjtzOjE5OiJhamF4X2xvYWRpbmdfZWZmZWN0IjtzOjE1OiJiYWxsLWdyaWQtcHVsc2UiO3M6MTk6Im1hZGFyYV9taXNjX3RodW1iXzMiO3M6Mjoib24iO3M6MjI6Im1hZGFyYV9tYW5nYV9iaWdfdGh1bWIiO3M6Mjoib24iO3M6MTk6Im1hZGFyYV9taXNjX3RodW1iXzEiO3M6Mjoib24iO3M6Mjc6Im1hZGFyYV9tYW5nYV9iaWdfdGh1bWJfZnVsbCI7czoyOiJvbiI7czoxOToibWFkYXJhX21pc2NfdGh1bWJfMiI7czoyOiJvbiI7czoyOToibWFkYXJhX21pc2NfdGh1bWJfcG9zdF9zbGlkZXIiO3M6Mjoib24iO3M6MTk6Im1hZGFyYV9taXNjX3RodW1iXzQiO3M6Mjoib24iO3M6MTQ6InRwX3NsaWRlcl9pdGVtIjtzOjI6Im9uIjtzOjM6ImFtcCI7czozOiJvZmYiO3M6MTk6ImFtcF9mb250YXdlc29tZV9rZXkiO3M6MDoiIjtzOjE2OiJhbXBfaW1hZ2VfaGVpZ2h0IjtzOjM6IjQwMCI7czoyMzoiYW1wX21hbmdhX3JlYWRpbmdfc3R5bGUiO3M6NDoibGlzdCI7czoyNzoidXNlcl9zZXR0aW5nc193ZWFrX3Bhc3N3b3JkIjtzOjI6Im9uIjtzOjMyOiJtYW5nYV9tYWluX3RvcF9zaWRlYmFyX2NvbnRhaW5lciI7czo5OiJjb250YWluZXIiO3M6MzM6Im1hbmdhX21haW5fdG9wX3NpZGViYXJfYmFja2dyb3VuZCI7YTo2OntzOjE2OiJiYWNrZ3JvdW5kLWNvbG9yIjtzOjA6IiI7czoxNzoiYmFja2dyb3VuZC1yZXBlYXQiO3M6MDoiIjtzOjIxOiJiYWNrZ3JvdW5kLWF0dGFjaG1lbnQiO3M6MDoiIjtzOjE5OiJiYWNrZ3JvdW5kLXBvc2l0aW9uIjtzOjA6IiI7czoxNToiYmFja2dyb3VuZC1zaXplIjtzOjA6IiI7czoxNjoiYmFja2dyb3VuZC1pbWFnZSI7czowOiIiO31zOjMwOiJtYW5nYV9tYWluX3RvcF9zaWRlYmFyX3NwYWNpbmciO3M6MDoiIjtzOjM5OiJtYW5nYV9tYWluX3RvcF9zZWNvbmRfc2lkZWJhcl9jb250YWluZXIiO3M6OToiY29udGFpbmVyIjtzOjQwOiJtYW5nYV9tYWluX3RvcF9zZWNvbmRfc2lkZWJhcl9iYWNrZ3JvdW5kIjthOjY6e3M6MTY6ImJhY2tncm91bmQtY29sb3IiO3M6MDoiIjtzOjE3OiJiYWNrZ3JvdW5kLXJlcGVhdCI7czowOiIiO3M6MjE6ImJhY2tncm91bmQtYXR0YWNobWVudCI7czowOiIiO3M6MTk6ImJhY2tncm91bmQtcG9zaXRpb24iO3M6MDoiIjtzOjE1OiJiYWNrZ3JvdW5kLXNpemUiO3M6MDoiIjtzOjE2OiJiYWNrZ3JvdW5kLWltYWdlIjtzOjA6IiI7fXM6Mzc6Im1hbmdhX21haW5fdG9wX3NlY29uZF9zaWRlYmFyX3NwYWNpbmciO3M6MDoiIjtzOjM1OiJtYW5nYV9tYWluX2JvdHRvbV9zaWRlYmFyX2NvbnRhaW5lciI7czo5OiJjb250YWluZXIiO3M6MzY6Im1hbmdhX21haW5fYm90dG9tX3NpZGViYXJfYmFja2dyb3VuZCI7YTo2OntzOjE2OiJiYWNrZ3JvdW5kLWNvbG9yIjtzOjA6IiI7czoxNzoiYmFja2dyb3VuZC1yZXBlYXQiO3M6MDoiIjtzOjIxOiJiYWNrZ3JvdW5kLWF0dGFjaG1lbnQiO3M6MDoiIjtzOjE5OiJiYWNrZ3JvdW5kLXBvc2l0aW9uIjtzOjA6IiI7czoxNToiYmFja2dyb3VuZC1zaXplIjtzOjA6IiI7czoxNjoiYmFja2dyb3VuZC1pbWFnZSI7czowOiIiO31zOjMzOiJtYW5nYV9tYWluX2JvdHRvbV9zaWRlYmFyX3NwYWNpbmciO3M6MDoiIjtzOjE5OiJtYW5nYV9hZHVsdF9jb250ZW50IjtzOjI6Im9uIjtzOjE5OiJtYW5nYV9ob3Zlcl9kZXRhaWxzIjtzOjM6Im9mZiI7czoxNzoibWFuZ2FfbmV3X2NoYXB0ZXIiO3M6Mjoib24iO3M6Mjg6Im1hbmdhX25ld19jaGFwdGVyX3RpbWVfcmFuZ2UiO3M6MToiMyI7czoyMDoibWFuZ2FfcmVhZGVyX3NldHRpbmciO3M6Mjoib24iO3M6Mjc6Im1hbmdhX2Jvb2ttYXJrX2xpc3Rfb3JkZXJieSI7czowOiIiO3M6MjU6Im1hbmdhX2Jvb2ttYXJrX2xpc3Rfb3JkZXIiO3M6MTI6Im9sZGVzdF9maXJzdCI7czoyNDoibWFuZ2FfYXJjaGl2ZV9icmVhZGNydW1iIjtzOjI6Im9uIjtzOjI3OiJtYW5nYV9hcmNoaXZlX2JyZWFkY3J1bWJfYmciO2E6Njp7czoxNjoiYmFja2dyb3VuZC1jb2xvciI7czo3OiIjMGEwYTBhIjtzOjE3OiJiYWNrZ3JvdW5kLXJlcGVhdCI7czowOiIiO3M6MjE6ImJhY2tncm91bmQtYXR0YWNobWVudCI7czowOiIiO3M6MTk6ImJhY2tncm91bmQtcG9zaXRpb24iO3M6MDoiIjtzOjE1OiJiYWNrZ3JvdW5kLXNpemUiO3M6MDoiIjtzOjE2OiJiYWNrZ3JvdW5kLWltYWdlIjtzOjA6IiI7fXM6MjE6Im1hbmdhX2FyY2hpdmVfaGVhZGluZyI7czowOiIiO3M6MjA6Im1hbmdhX2FyY2hpdmVfZ2VucmVzIjtzOjI6Im9uIjtzOjI5OiJtYW5nYV9hcmNoaXZlX2dlbnJlc19jb2xsYXBzZSI7czoyOiJvbiI7czoyNjoibWFuZ2FfYXJjaGl2ZV9nZW5yZXNfdGl0bGUiO3M6MDoiIjtzOjIxOiJtYW5nYV9hcmNoaXZlX3NpZGViYXIiO3M6NToicmlnaHQiO3M6MjY6Im1hbmdhX2FyY2hpdmVzX2l0ZW1fbGF5b3V0IjtzOjc6ImRlZmF1bHQiO3M6MzI6Im1hbmdhX2FyY2hpdmVzX2l0ZW1fbW9iaWxlX3dpZHRoIjtzOjI6IjUwIjtzOjQxOiJtYW5nYV9hcmNoaXZlX2xhdGVzdF9jaGFwdGVyX29uX3RodW1ibmFpbCI7czozOiJvZmYiO3M6Mjk6Im1hbmdhX2FyY2hpdmVzX2l0ZW1fdHlwZV9pY29uIjtzOjM6Im9mZiI7czoyOToibWFuZ2FfYXJjaGl2ZXNfaXRlbV90eXBlX3RleHQiO3M6Mzoib2ZmIjtzOjIwOiJtYW5nYV9iYWRnZV9wb3NpdGlvbiI7czoxOiIxIjtzOjMzOiJtYW5nYV9hcmNoaXZlX2xpbWl0X3Zpc2libGVfbGluZXMiO3M6MToiMiI7czozNzoibWFuZ2FfYXJjaGl2ZV9sYXRlc3RfY2hhcHRlcnNfdmlzaWJsZSI7czoxOiIyIjtzOjI2OiJtYW5nYV9hcmNoaXZlc19pdGVtX3ZvbHVtZSI7czoyOiJvbiI7czoyODoibWFuZ2Ffc2luZ2xlX2FsbG93X3RodW1iX2dpZiI7czozOiJvZmYiO3M6MjQ6Im1hbmdhX3Byb2ZpbGVfYmFja2dyb3VuZCI7YTo2OntzOjE2OiJiYWNrZ3JvdW5kLWNvbG9yIjtzOjA6IiI7czoxNzoiYmFja2dyb3VuZC1yZXBlYXQiO3M6MDoiIjtzOjIxOiJiYWNrZ3JvdW5kLWF0dGFjaG1lbnQiO3M6MDoiIjtzOjE5OiJiYWNrZ3JvdW5kLXBvc2l0aW9uIjtzOjA6IiI7czoxNToiYmFja2dyb3VuZC1zaXplIjtzOjA6IiI7czoxNjoiYmFja2dyb3VuZC1pbWFnZSI7czowOiIiO31zOjI4OiJtYW5nYV9wcm9maWxlX3N1bW1hcnlfbGF5b3V0IjtzOjE6IjEiO3M6Mjg6Im1hbmdhX3NpbmdsZV9pbmZvX3Zpc2liaWxpdHkiO3M6Mzoib2ZmIjtzOjIyOiJtYW5nYV9zaW5nbGVfdGFnc19wb3N0IjtzOjQ6ImluZm8iO3M6MjQ6Im1hbmdhX3NpbmdsZV9tZXRhX2F1dGhvciI7czo5OiJ3cF9hdXRob3IiO3M6MjM6Im1hbmdhX3NpbmdsZV9icmVhZGNydW1iIjtzOjI6Im9uIjtzOjIwOiJtYW5nYV9zaW5nbGVfc3VtbWFyeSI7czoyOiJvbiI7czoyNjoibWFuZ2Ffc2luZ2xlX2NoYXB0ZXJzX2xpc3QiO3M6Mjoib24iO3M6MTg6ImluaXRfbGlua3NfZW5hYmxlZCI7czoyOiJvbiI7czoyMDoibWFuZ2Ffc2luZ2xlX3NpZGViYXIiO3M6NToicmlnaHQiO3M6MjE6Im1hbmdhX3JlYWRpbmdfb25lc2hvdCI7czo1OiJtYW5nYSI7czoyNjoibWFuZ2FfZGV0YWlsX2xhenlfY2hhcHRlcnMiO3M6Mjoib24iO3M6MTk6Im1hbmdhX3ZvbHVtZXNfb3JkZXIiO3M6NDoiZGVzYyI7czoyMDoibWFuZ2FfY2hhcHRlcnNfb3JkZXIiO3M6OToibmFtZV9kZXNjIjtzOjMxOiJtYW5nYV9zaW5nbGVfY2hhcHRlcnNfbGlzdF9jb2xzIjtzOjE6IjEiO3M6MzM6Im1hbmdhX3NpbmdsZV9yZWxhdGVkX2l0ZW1zX2xheW91dCI7czoxOiIxIjtzOjMyOiJtYW5nYV9zaW5nbGVfcmVsYXRlZF9pdGVtc19jb3VudCI7czoxOiI0IjtzOjM4OiJtYW5nYV9zaW5nbGVfcmVsYXRlZF9pdGVtX21vYmlsZV93aWR0aCI7czozOiIxMDAiO3M6MTY6Im1hbmdhX3Jhbmtfdmlld3MiO3M6NzoibW9udGhseSI7czoxNToic2VvX21hbmdhX3RpdGxlIjtzOjA6IiI7czoxNDoic2VvX21hbmdhX2Rlc2MiO3M6MDoiIjtzOjE3OiJzZW9fY2hhcHRlcl90aXRsZSI7czowOiIiO3M6MTY6InNlb19jaGFwdGVyX2Rlc2MiO3M6MDoiIjtzOjI0OiJtYW5nYV9yZWFkaW5nX2Rpc2N1c3Npb24iO3M6Mjoib24iO3M6MzI6Im1hbmdhX3JlYWRpbmdfZGlzY3Vzc2lvbl9oZWFkaW5nIjtzOjI6Im9uIjtzOjI2OiJtYW5nYV9yZWFkaW5nX3BhZ2Vfc2lkZWJhciI7czo1OiJyaWdodCI7czoyNjoibWFuZ2FfcmVhZGluZ190ZXh0X3NpZGViYXIiO3M6Mzoib2ZmIjtzOjE1OiJjaGFwdGVyX2hlYWRpbmciO3M6Mjoib24iO3M6MjA6Im1pbmltYWxfcmVhZGluZ19wYWdlIjtzOjI6Im9uIjtzOjI3OiJtYW5nYV9yZWFkaW5nX3RleHRfZm9udHNpemUiO3M6MDoiIjtzOjE5OiJtYW5nYV9yZWFkaW5nX3N0eWxlIjtzOjQ6Imxpc3QiO3M6Mjc6Im1hbmdhX2NoYXB0ZXJzX3NlbGVjdF9vcmRlciI7czo3OiJkZWZhdWx0IjtzOjI2OiJtYW5nYV9yZWFkaW5nX2NvbnRlbnRfZ2FwcyI7czoyOiJvbiI7czoyOToibWFuZ2FfcmVhZGluZ19pbWFnZXNfcGVyX3BhZ2UiO3M6MToiMSI7czoyNDoibWFuZ2FfcmVhZGluZ19mdWxsX3dpZHRoIjtzOjI6Im9uIjtzOjIxOiJtYW5nYV9yZWFkaW5nX3JlbGF0ZWQiO3M6Mjoib24iO3M6MjM6Im1hbmdhX3BhZ2VfcmVhZGluZ19hamF4IjtzOjI6Im9uIjtzOjI4OiJtYW5nYV9yZWFkaW5nX3ByZWxvYWRfaW1hZ2VzIjtzOjI6Im9uIjtzOjI3OiJtYW5nYV9yZWFkaW5nX3N0aWNreV9oZWFkZXIiO3M6MDoiIjtzOjMxOiJtYW5nYV9yZWFkaW5nX3N0aWNreV9uYXZpZ2F0aW9uIjtzOjI6Im9uIjtzOjM4OiJtYW5nYV9yZWFkaW5nX3N0aWNreV9uYXZpZ2F0aW9uX21vYmlsZSI7czozOiJvZmYiO3M6MzU6Im1hbmdhX3JlYWRpbmdfbmF2aWdhdGlvbl9ieV9wb2ludGVyIjtzOjI6Im9uIjtzOjI2OiJtYW5nYV9yZWFkaW5nX3NvY2lhbF9zaGFyZSI7czozOiJvZmYiO3M6Mjc6Im1hZGFyYV9kaXNhYmxlX2ltYWdldG9vbGJhciI7czozOiJvZmYiO3M6MjI6Im1hZGFyYV9yZWFkaW5nX2hpc3RvcnkiO3M6Mjoib24iO3M6Mjg6Im1hZGFyYV9yZWFkaW5nX2hpc3RvcnlfZGVsYXkiO3M6MToiNSI7czoyODoibWFkYXJhX3JlYWRpbmdfaGlzdG9yeV9pdGVtcyI7czoyOiIxMiI7fQ==';

			$message = $this->_import_thememod($to_settings);
		
			// create menu
			$main_menu_id = wp_create_nav_menu('Primary Menu ' . rand(0,1000));
			if($main_menu_id){
				wp_update_nav_menu_item($main_menu_id, 0, array(
					'menu-item-title' =>  __('Home'),
					'menu-item-object-id' => $frontpage_id,
					'menu-item-object' => 'page',
					'menu-item-type' => 'post_type',
					'menu-item-status' => 'publish'));
			
				wp_update_nav_menu_item($main_menu_id, 0, array(
					'menu-item-title' =>  __('All Series'),
					'menu-item-object-id' => $newpage_id,
					'menu-item-object' => 'page',
					'menu-item-type' => 'post_type',
					'menu-item-status' => 'publish'));
			
				wp_update_nav_menu_item($main_menu_id, 0, array(
					'menu-item-title' =>  __('Blog'),
					'menu-item-object-id' => $blog_pageid	,
					'menu-item-object' => 'page',
					'menu-item-type' => 'post_type',
					'menu-item-status' => 'publish'));
				
				wp_update_nav_menu_item($main_menu_id, 0, array(
					'menu-item-title' =>  __('About Us'),
					'menu-item-object-id' => $aboutus_pageid,
					'menu-item-object' => 'page',
					'menu-item-type' => 'post_type',
					'menu-item-status' => 'publish'));
			}
		
			$second_menu_id = wp_create_nav_menu('Secondary Menu ' . rand(0,1000));
			if($second_menu_id){
				wp_update_nav_menu_item($second_menu_id, 0, array(
					'menu-item-title' =>  'ROMANCE',
					'menu-item-classes' => '',
					'menu-item-url' => '#', 
					'menu-item-status' => 'publish'));
			
				wp_update_nav_menu_item($second_menu_id, 0, array(
					'menu-item-title' =>  'COMEDY',
					'menu-item-classes' => '',
					'menu-item-url' => '#', 
					'menu-item-status' => 'publish'));
			}    
			
			$locations = get_theme_mod('nav_menu_locations');
			$locations['primary_menu'] = $main_menu_id;
			$locations['secondary_menu'] = $second_menu_id;
			set_theme_mod( 'nav_menu_locations', $locations );
		
			update_option( 'page_on_front', $frontpage_id );
			update_option('show_on_front', 'page');
			update_option('posts_per_page', 16);
			update_option('page_for_posts', $blog_pageid);
			// permalink
			update_option('permalink_structure', '/%postname%/');
			update_option('users_can_register', 1);
		
			// insert manga post
			for($id = 1; $id <= 20; $id++){
				$args = array(
					'post_content' => 'Lorem ipsum',
					'post_type' => 'wp-manga',
					'post_title' => 'Manga ' . $id,
					'post_status' => 'publish'
				);
			
				$manga_id = wp_insert_post($args);

				$thumb_idx = rand(1,5);
				$banner_idx = rand(1,5);
			
				$thumb_id = $this->_upload_thumb( get_template_directory() . '/sample-data/thumb-' . $thumb_idx . '.jpg', $manga_id );
				$banner_id = $this->_upload_thumb( get_template_directory() . '/sample-data/horiimage-' . $banner_idx . '.jpg', $manga_id );
			
				$meta_data = array(
					'_thumbnail_id'          => $thumb_id,
					'_wp_manga_alternative'  => 'Alternative Name',
					'_wp_manga_chapter_type' => 'manga',
				);
			
				foreach( $meta_data as $key => $value ){
					if( !empty( $value ) ){
						update_post_meta( $manga_id, $key, $value );
					}
				}
			
				update_post_meta($manga_id, 'manga_banner', wp_get_attachment_url($banner_id));
			
				$manga_terms = array(
					'wp-manga-release' => '2023',
					'wp-manga-author'      => 'The Author',
					'wp-manga-artist'      => 'Artist',
					'wp-manga-genre'       => 'action,horrow,fun,drama,ecchi,fighting,girl,boys,adventure,manhwa,chinese',
					'wp-manga-tag'         => 'tag-1,tag-2,tag-3',
				);
			
				foreach( $manga_terms as $tax => $term ){
					$resp = $this->_add_manga_terms( $manga_id, $term, $tax );
				}
		
				// insert chapters
				$this->_upload_single_chapter(array('name' => 'Chapter 1', 'extend_name' => 'Other Name 1'), $manga_id);
				$this->_upload_single_chapter(array('name' => 'Chapter 2', 'extend_name' => 'Other Name 2'), $manga_id);
				$this->_upload_single_chapter(array('name' => 'Chapter 3', 'extend_name' => 'Other Name 3'), $manga_id);
				$this->_upload_single_chapter(array('name' => 'Chapter 4', 'extend_name' => 'Other Name 4'), $manga_id);
			}
		
			$this->_update_post_views( $manga_id, 1000 );
			$this->_update_ratings( $manga_id, array('avg' => 5, 'numbers' => 1000) );
		
			wp_send_json_success( ['message' => 'Sample data installed. Please remove "/sample-data" folder in your theme', 'data' => $message] );
		}
		
		function _upload_single_chapter( $chapter, $post_id ){		
			// Prepare
			global $wp_manga, $wp_manga_storage;
			$uniqid = $wp_manga->get_uniqid( $post_id );
			
			$slugified_name = $wp_manga_storage->slugify( $chapter['name'] );
			
			// Download images
			$extract = WP_MANGA_DATA_DIR . $uniqid . '/' . $slugified_name;
		
			if( ! file_exists( $extract ) ){
				if( ! wp_mkdir_p( $extract ) ){
					error_log_die([
						'function' => __FUNCTION__,
						'message'  => "Cannot make dir $extract",
						'cancel'   => true,
					]);
				}
			}
		
			$extract_uri = WP_MANGA_DATA_URL;
		
			$this->_folder_copy(dirname(__FILE__) . '/chapter-images', $extract);
		
			// Create Chapter
			$chapter_args = array(
				'post_id'             => $post_id,
				'volume_id'           => 0,
				'chapter_name'        => $chapter['name'],
				'chapter_name_extend' => $chapter['extend_name'],
				'chapter_slug'        => $slugified_name,
			);
		
			$storage = 'local';
			
			//upload chapter to cloud in case of crawl single
			$result = $wp_manga_storage->wp_manga_upload_single_chapter( $chapter_args, $extract, $extract_uri, $storage );
			
			return $result;
		
		}

		function _upload_single_content_chapter($chapter, $post_id){
			if (!class_exists('WP_MANGA_TEXT_CHAPTER')) {
				return;
			}
		
			global $wp_manga_text_type;
		
			$insert_chapter_args = [
				'post_id' => $post_id,
				'volume_id' => 0,
				'chapter_name' => $chapter['chapter_name'],
				'chapter_name_extend' => '',
				'chapter_slug' => sanitize_title($chapter['chapter_name']),
				'chapter_content' => $chapter['chapter_content']
			];

			$insert_chapter = $wp_manga_text_type->insert_chapter($insert_chapter_args);
	
		}
		
		function _folder_copy($src, $dst) { 
		  
			// open the source directory
			$dir = opendir($src); 
		  
			// Make the destination directory if not exist
			@mkdir($dst); 
		  
			// Loop through the files in source directory
			while( $file = readdir($dir) ) { 
		  
				if (( $file != '.' ) && ( $file != '..' )) { 
					if ( is_dir($src . '/' . $file) ) 
					{ 
		  
						// Recursively calling custom copy function
						// for sub directory 
						$this->_folder_copy($src . '/' . $file, $dst . '/' . $file); 
		  
					} 
					else { 
						copy($src . '/' . $file, $dst . '/' . $file); 
					} 
				} 
			} 
		  
			closedir($dir);
		}
		
		function _add_manga_terms( $post_id, $terms, $taxonomy ){
		
		
		
			$terms = explode(',', $terms);
		
			if( empty( $terms ) ){
				return false;
			}
		
			$taxonomy_obj = get_taxonomy( $taxonomy );
		
			if( $taxonomy_obj->hierarchical ){
		
				$output_terms = array();
		
				foreach( $terms as $current_term ){
		
					if( empty( $current_term ) ){
						continue;
					}
		
					//check if term is exist
					$term = term_exists( $current_term, $taxonomy );
		
					//then add if it isn't
					if( ! $term || is_wp_error( $term ) ){
						$term = wp_insert_term( $current_term, $taxonomy );
						if( !is_wp_error( $term ) && isset( $term['term_id'] ) ){
							$term = intval( $term['term_id'] );
		
						}else{
							continue;
						}
					}else{
						$term = intval( $term['term_id'] );
					}
		
					$output_terms[] = $term;
				}
		
				$terms = $output_terms;
			}
		
			$resp = wp_set_post_terms( $post_id, $terms, $taxonomy );
		
			return $resp;
		
		}
		
		function _update_post_views( $post_id, $views ){
		
			$month = date('m');
		
			update_post_meta( $post_id, '_wp_manga_month_views', array(
				'date' => $month,
				'views' => $views
			) );
			
			update_post_meta( $post_id, '_wp_manga_views', $views );
			
			$new_year_views = array( 'views' => $views, 'date' => date('y') );
			update_post_meta( $post_id, '_wp_manga_year_views', $new_year_views );
			update_post_meta( $post_id, '_wp_manga_year_views_value', $views ); // clone to sort by value
		
		}
		
		function _update_ratings( $post_id, $ratings = array() ){
		
			if( empty( $ratings ) || !isset( $ratings['avg'] ) || !isset( $ratings['numbers'] ) ){
				return false;
			}
		
			extract( $ratings );
		
			$totals = intval( (float)trim($avg) * (float)$numbers );
			$int_avg_totals = intval( $avg ) * $numbers;
		
			$above_avg_numbers = $totals - $int_avg_totals;
			$int_avg_numbers = $numbers - $above_avg_numbers;
		
			$rates = array();
		
			for( $i = 1; $i <= $above_avg_numbers; $i++ ){
				$rates[] = intval( $avg + 1 );
			}
		
			for( $i = 1; $i <= $int_avg_numbers; $i++ ){
				$rates[] = intval( $avg );
			}
		
			update_post_meta( $post_id, '_manga_avarage_reviews', $avg );
			update_post_meta( $post_id, '_manga_reviews', $rates );
		
			return true;
		}
		
		function _upload_thumb($url, $post_id = 0){
			include_once( ABSPATH . 'wp-admin/includes/image.php' );
			$content = file_get_contents( $url );
		
			$pathinfo = pathinfo( $url );
		
			if( ! $content ){
				return false;
			}
		
			$upload_dir = wp_upload_dir();
		
			if( ! file_exists( $upload_dir['basedir'] . '/images' ) ){
				if( ! wp_mkdir_p( $upload_dir['basedir'] . '/images' ) ){
					error_log_die([
						'function' => __FUNCTION__,
						'message'  => "Cannot make dir $extract",
						'cancel'   => true,
					]);
				}
			}
		
			$file_tmp_path = $upload_dir['basedir'] . '/images/' . $pathinfo['filename'] . '-' . $post_id . '.' . explode('?',$pathinfo['extension'])[0];
		
			file_put_contents( $file_tmp_path, $content );
		
			$wp_filetype = wp_check_filetype(basename($file_tmp_path), null );
		
			$attachment = array(
				'post_mime_type' => $wp_filetype['type'],
				'post_title' => $post_id,
				'post_content' => '',
				'post_status' => 'inherit'
			);
		
			$attach_id = wp_insert_attachment( $attachment, $file_tmp_path );
		
			$imagenew = get_post( $attach_id );
			$fullsizepath = get_attached_file( $imagenew->ID );
			$attach_data = wp_generate_attachment_metadata( $attach_id, $fullsizepath );
			wp_update_attachment_metadata( $attach_id, $attach_data );
		
			return $attach_id;
		}
		
		function _import_thememod($options){
			// Default message.
			$message = 'failed';
		
			$decoded  = base64_decode( $options ); // phpcs:ignore
			
			// Convert the options to an array.
			$options = maybe_unserialize( $decoded );
			
			if ( $options ) {
				$options = (array)$options;
				/*
				$options['copyright'] = 'Madara WordPress Theme by Mangabooth.com';
				$options = serialize($options);
				$encoded = base64_encode($options);
				echo $encoded;exit;*/
		
				$options_safe = array();
		
				// Get settings array.
				$settings = get_option( apply_filters( 'ot_options_id', 'option_tree' ) );
		
				// Has options.
				if ( is_array( $options ) ) {
					foreach($options as $option => $val){
						if($val == 'on'){
							$val = 1;
						} else if($val == 'off'){
							$val = 0;
						}
						set_theme_mod($option, $val);
					}
		
					$message = 'success';
				}
			}
		
			return $message;
		}
		
		/**
		 * Import widget JSON data
		 *
		 * @since 0.4
		 * @global array $wp_registered_sidebars
		 * @param object $data JSON widget data from .wie file.
		 * @return array Results array
		 */
		function wie_import_data($data)
		{
			global $wp_registered_sidebars;
	
			// Have valid data?
			// If no data or could not decode.
			if (empty($data) || ! is_object($data)) {
				$message = esc_html__('Import data is invalid.', 'madara');
				wp_send_json_error( ['message' => $message, 'data' => $message] );
			}
	
			// Hook before import.
			do_action('wie_before_import');
			$data = apply_filters('wie_import_data', $data);
	
			// Get all available widgets site supports.
			$available_widgets = $this->wie_available_widgets();
	
			// Get all existing widget instances.
			$widget_instances = array();
			foreach ($available_widgets as $widget_data) {
				$widget_instances[$widget_data['id_base']] = get_option('widget_' . $widget_data['id_base']);
			}
	
			// clean existing widgets
			$sidebars_widgets = get_option('sidebars_widgets');
			foreach($sidebars_widgets as $id => $widgets){
				$sidebars_widgets[$id] = array();
			}
			update_option('sidebars_widgets', $sidebars_widgets);
	
			// Begin results.
			$results = array();
	
			// Loop import data's sidebars.
			foreach ($data as $sidebar_id => $widgets) {
				// Skip inactive widgets (should not be in export file).
				if ('wp_inactive_widgets' === $sidebar_id) {
					continue;
				}
	
				// Check if sidebar is available on this site.
				// Otherwise add widgets to inactive, and say so.
				if (isset($wp_registered_sidebars[$sidebar_id])) {
					$sidebar_available    = true;
					$use_sidebar_id       = $sidebar_id;
					$sidebar_message_type = 'success';
					$sidebar_message      = '';
				} else {
					$sidebar_available    = false;
					$use_sidebar_id       = 'wp_inactive_widgets'; // Add to inactive if sidebar does not exist in theme.
					$sidebar_message_type = 'error';
					$sidebar_message      = esc_html__('Widget area does not exist in theme (using Inactive)', 'widget-importer-exporter');
				}
	
				// Result for sidebar
				// Sidebar name if theme supports it; otherwise ID.
				$results[$sidebar_id]['name']         = ! empty($wp_registered_sidebars[$sidebar_id]['name']) ? $wp_registered_sidebars[$sidebar_id]['name'] : $sidebar_id;
				$results[$sidebar_id]['message_type'] = $sidebar_message_type;
				$results[$sidebar_id]['message']      = $sidebar_message;
				$results[$sidebar_id]['widgets']      = array();
	
				// Loop widgets.
				foreach ($widgets as $widget_instance_id => $widget) {
					$fail = false;
	
					// Get id_base (remove -# from end) and instance ID number.
					$id_base            = preg_replace('/-[0-9]+$/', '', $widget_instance_id);
					$instance_id_number = str_replace($id_base . '-', '', $widget_instance_id);
	
					// Does site support this widget?
					if (! $fail && ! isset($available_widgets[$id_base])) {
						$fail                = true;
						$widget_message_type = 'error';
						$widget_message      = esc_html__('Site does not support widget', 'widget-importer-exporter'); // Explain why widget not imported.
					}
	
					// Filter to modify settings object before conversion to array and import
					// Leave this filter here for backwards compatibility with manipulating objects (before conversion to array below)
					// Ideally the newer wie_widget_settings_array below will be used instead of this.
					$widget = apply_filters('wie_widget_settings', $widget);
	
					// Convert multidimensional objects to multidimensional arrays
					// Some plugins like Jetpack Widget Visibility store settings as multidimensional arrays
					// Without this, they are imported as objects and cause fatal error on Widgets page
					// If this creates problems for plugins that do actually intend settings in objects then may need to consider other approach: https://wordpress.org/support/topic/problem-with-array-of-arrays
					// It is probably much more likely that arrays are used than objects, however.
					$widget = json_decode(wp_json_encode($widget), true);
	
					// Filter to modify settings array
					// This is preferred over the older wie_widget_settings filter above
					// Do before identical check because changes may make it identical to end result (such as URL replacements).
					$widget = apply_filters('wie_widget_settings_array', $widget);
					
					// No failure.
					if (! $fail) {
						// Add widget instance
						$single_widget_instances   = get_option('widget_' . $id_base); // All instances for that widget ID base, get fresh every time.
						$single_widget_instances   = ! empty($single_widget_instances) ? $single_widget_instances : array(
							'_multiwidget' => 1,   // Start fresh if have to.
						);
						$single_widget_instances[] = $widget; // Add it.
	
						// Get the key it was given.
						end($single_widget_instances);
						$new_instance_id_number = key($single_widget_instances);
	
						// If key is 0, make it 1
						// When 0, an issue can occur where adding a widget causes data from other widget to load,
						// and the widget doesn't stick (reload wipes it).
						if ('0' === strval($new_instance_id_number)) {
							$new_instance_id_number = 1;
							$single_widget_instances[$new_instance_id_number] = $single_widget_instances[0];
							unset($single_widget_instances[0]);
						}
	
						// Move _multiwidget to end of array for uniformity.
						if (isset($single_widget_instances['_multiwidget'])) {
							$multiwidget = $single_widget_instances['_multiwidget'];
							unset($single_widget_instances['_multiwidget']);
							$single_widget_instances['_multiwidget'] = $multiwidget;
						}
	
						// Update option with new widget.
						update_option('widget_' . $id_base, $single_widget_instances);
	
						// Assign widget instance to sidebar.
						// Which sidebars have which widgets, get fresh every time.
						$sidebars_widgets = get_option('sidebars_widgets');
	
						// Avoid rarely fatal error when the option is an empty string
						// https://github.com/churchthemes/widget-importer-exporter/pull/11.
						if (! $sidebars_widgets) {
							$sidebars_widgets = array();
						}
	
						// Use ID number from new widget instance.
						$new_instance_id = $id_base . '-' . $new_instance_id_number;
	
						// Add new instance to sidebar.
						$sidebars_widgets[$use_sidebar_id][] = $new_instance_id;
	
						// Save the amended data.
						update_option('sidebars_widgets', $sidebars_widgets);
	
						// After widget import action.
						$after_widget_import = array(
							'sidebar'           => $use_sidebar_id,
							'sidebar_old'       => $sidebar_id,
							'widget'            => $widget,
							'widget_type'       => $id_base,
							'widget_id'         => $new_instance_id,
							'widget_id_old'     => $widget_instance_id,
							'widget_id_num'     => $new_instance_id_number,
							'widget_id_num_old' => $instance_id_number,
						);
						do_action('wie_after_widget_import', $after_widget_import);
	
						// Success message.
						if ($sidebar_available) {
							$widget_message_type = 'success';
							$widget_message      = esc_html__('Imported', 'widget-importer-exporter');
						} else {
							$widget_message_type = 'warning';
							$widget_message      = esc_html__('Imported to Inactive', 'widget-importer-exporter');
						}
					}
	
					// Result for widget instance
					$results[$sidebar_id]['widgets'][$widget_instance_id]['name']         = isset($available_widgets[$id_base]['name']) ? $available_widgets[$id_base]['name'] : $id_base;      // Widget name or ID if name not available (not supported by site).
					$results[$sidebar_id]['widgets'][$widget_instance_id]['title']        = ! empty($widget['title']) ? $widget['title'] : esc_html__('No Title', 'widget-importer-exporter');  // Show "No Title" if widget instance is untitled.
					$results[$sidebar_id]['widgets'][$widget_instance_id]['message_type'] = $widget_message_type;
					$results[$sidebar_id]['widgets'][$widget_instance_id]['message']      = $widget_message;
				}
			}
	
			// Hook after import.
			do_action('wie_after_import');
	
			// Return results.
			return apply_filters('wie_import_results', $results);
		}
		
		/**
		 * Available widgets
		 *
		 * Gather site's widgets into array with ID base, name, etc.
		 * Used by export and import functions.
		 *
		 * @since 0.4
		 * @global array $wp_registered_widget_updates
		 * @return array Widget information
		 */
		function wie_available_widgets()
		{
			global $wp_registered_widget_controls;
	
			$widget_controls = $wp_registered_widget_controls;
	
			$available_widgets = array();
	
			foreach ($widget_controls as $widget) {
				// No duplicates.
				if (! empty( $widget['id_base'] ) && ! isset( $available_widgets[ $widget['id_base'] ] )) {
					$available_widgets[ $widget['id_base'] ]['id_base'] = $widget['id_base'];
					$available_widgets[ $widget['id_base'] ]['name']    = $widget['name'];
				}
			}
	
			return apply_filters( 'wie_available_widgets', $available_widgets );
		}
		
		function _sample_data_page(){
			?>
			<style type="text/css">
				#page-sample-data, #page-sample-data p{font-size:14px}
				#page-sample-data .btn{display:inline-block;padding:3px 10px;background:#007cba;color:#FFF;font-size:14px}
				#page-sample-data .btn i{display: none}
				#page-sample-data .btn:hover{background:#006ba1; color:#FFF}
				#page-sample-data .btn.loading{background:#999;border:none}
				#page-sample-data .btn.loading i{display: inline-block;}
				#page-sample-data ul{list-style-type: disc;padding-left:15px}
			</style>
			<div id="page-sample-data">
				<h3 style="margin: 40px 0">Install sample data for Madara</h3>
				<p>Demo Page: <a href="https://live.mangabooth.com/" target="_blank">Madara Demo Page</a></p>
				<p><a href="javascript:void(0)" class="btn" onclick="madara_install_sampledata(this)"><i class="fas fa-spinner fa-spin"></i> Click here</a></p>
				
				<p><strong>Notes:</strong>
					<ul>
						<li>New data will be imported each time running the Sample Data installation</li>
						<li>Make sure to run only on a fresh site because existing settings will be replaced</li>
					</ul>
				</p>
			</div>
			<script type="text/javascript">
				function madara_install_sampledata(obj){
					if(!jQuery(obj).hasClass('loading')){
						jQuery(obj).addClass('loading');
						jQuery.ajax({
							method : 'POST',
							url : ajaxurl,
							data : {
								action: 'madara_install_data'
							},
							success : function( response ){
								var data = response.data;
								console.log(data.data);
								alert(data.message);
								jQuery('#page-sample-data .btn').after("<p>View <a href='/'>your site</a> now</p>")
							},
							error: function(err){
								console.log(err);
								alert("error");
							},
							complete: function(){
								jQuery(obj).removeClass('loading');
							}
						});
					}
					
					return false;
				}
			</script>
			<?php
		}
	}
}

function madara_demo_import_lists($demo_lists){
	$sample_data_url = MADARA_SAMPLE_DATA_URL;
	$demo_mangaplus = array(
		'title' => __( 'Manga & Webtoon', 'madara' ),/*Title*/
		'is_pro' => false,/*Is Premium*/
		'type' => 'elementor',
		'author' => __( 'Mangabooth', 'madara' ),/*Author Name*/
		'keywords' => array( 'manga' ),/*Search keyword*/
		'categories' => array( 'manga' ),/*Categories*/
		'template_url' => array(
			'content' => $sample_data_url . '/manga/content.json',
			'options' => $sample_data_url . '/manga/options.json',
			'widgets' => $sample_data_url . '/manga/widgets.json',
		),
		'screenshot_url' => $sample_data_url . '/images/manga-preview.png',/*Full URL Path to demo screenshot image*/
		'demo_url' => 'https://live.mangabooth.com/20-manga/',/*Full URL Path to Live Demo*/
		'plugins' => array(
			array(
				'name'      => __( 'Elementor', 'madara' ),
				'slug'      => 'elementor',
			),
		),

	);

	$demo_lists['mangaplus'] = $demo_mangaplus;

	$demo_webnovel = array(
		'title' => __( 'Web Novel', 'madara' ),/*Title*/
		'is_pro' => false,/*Is Premium*/
		'type' => 'elementor',
		'author' => __( 'Mangabooth', 'madara' ),/*Author Name*/
		'keywords' => array( 'novel' ),/*Search keyword*/
		'categories' => array( 'novel' ),/*Categories*/
		'template_url' => array(
			'content' => $sample_data_url . '/webnovel/content.json',
			'options' => $sample_data_url . '/webnovel/options.json',
			'widgets' => $sample_data_url . '/webnovel/widgets.json',
		),
		'screenshot_url' => $sample_data_url . '/images/webnovel-preview.png',/*Full URL Path to demo screenshot image*/
		'demo_url' => 'https://live.mangabooth.com/20-novel/',/*Full URL Path to Live Demo*/
		'plugins' => array(
			array(
				'name'      => __( 'Elementor', 'madara' ),
				'slug'      => 'elementor',
			),
		),

	);

	$demo_lists['webnovel'] = $demo_webnovel;

	$demo_anime = array(
		'title' => __( 'Anime', 'madara' ),/*Title*/
		'is_pro' => false,/*Is Premium*/
		'type' => 'elementor',
		'author' => __( 'Mangabooth', 'madara' ),/*Author Name*/
		'keywords' => array( 'anime' ),/*Search keyword*/
		'categories' => array( 'anime' ),/*Categories*/
		'template_url' => array(
			'content' => $sample_data_url . '/anime/content.json',
			'options' => $sample_data_url . '/anime/options.json',
			'widgets' => $sample_data_url . '/anime/widgets.json',
		),
		'screenshot_url' => $sample_data_url . '/images/anime-preview.png',/*Full URL Path to demo screenshot image*/
		'demo_url' => 'https://live.mangabooth.com/20-webdrama/',/*Full URL Path to Live Demo*/
		'plugins' => array(
			array(
				'name'      => __( 'Elementor', 'madara' ),
				'slug'      => 'elementor',
			),
		),

	);

	$demo_lists['anime'] = $demo_anime;

	return $demo_lists;
}
add_filter('advanced_import_demo_lists','madara_demo_import_lists');

add_action('advanced_import_before_demo_import_screen', 'madara_before_demo_import_screen');
function madara_before_demo_import_screen(){
	echo '<div class="ai-body">';

	echo '<div class="ai-header">';
	echo '<h2 style="color:#FF0000">Warning</h2>';
	echo '<p style="color:#FF0000">It is recommended to install the sample data on a new site. It may cause conflict with your existing data. If you need help, contact us at our <a href="https://mangabooth.ticksy.com" target="_blank">Support System</a></p>';
	echo '</div>';
	echo '</div>';/*ai-body*/
}

add_action('advanced_import_before_complete_screen', 'madara_save_wp_manga_settings');
function madara_save_wp_manga_settings(){
	$options = get_option( 'wp_manga_settings');
	$options['enable_comment'] = 1;
	$options['default_comment'] = 'wp';

	update_option('wp_manga_settings', $options);
}

add_action('advanced_import_before_complete_screen', 'madara_add_dummy_chapters');

function madara_add_dummy_chapters() {

	$madara_sampledata_installer = new madara_sampledata_installer();
    global $wp_manga_text_type;

	//get all manga
	$manga_args = array(
		'post_type' => 'wp-manga',
		'posts_per_page' => 20,
		'post_status' => 'publish',
		'order' => 'desc'
	);

	$manga_query = new WP_Query($manga_args);
	if ($manga_query -> have_posts()) {
		while ($manga_query -> have_posts()) {
			$manga_query -> the_post();
			$manga_id = get_the_ID();
			$manga_slug = get_post_field('post_name', $manga_id);
			error_log($manga_id . " - " . $manga_slug);
			$chapter_type = get_post_meta($manga_id, '_wp_manga_chapter_type', true);

			madara_set_manga_images($manga_id, $manga_slug);

			if ($chapter_type == 'text') {
				for ($i = 1; $i <= 5; $i++) {
				$madara_sampledata_installer->_upload_single_content_chapter(array(
					'chapter_name' => 'Chapter ' . $i,
					'chapter_content' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Etiam egestas est tortor, in pulvinar ex lobortis a. Donec et mollis mi. Integer lacinia elementum neque sit amet fermentum. Morbi condimentum ipsum quis risus bibendum, pulvinar semper felis tempor. Integer neque sapien, lacinia quis elit sit amet, dignissim efficitur mi. Sed at sem nec nulla viverra pellentesque. Suspendisse potenti. Praesent nec sapien ligula. Vestibulum efficitur tellus non consectetur elementum. Donec urna risus, tempus id ornare sed, tincidunt imperdiet est. Ut nunc urna, ultrices ac odio sit amet, finibus tincidunt lorem. Fusce eget dui tempor mi cursus commodo id vulputate urna. Nulla urna purus, iaculis non ornare ut, scelerisque eu dui. 
					Aliquam commodo ultricies nibh, et mollis nisl accumsan sit amet. Proin placerat dui a nibh blandit maximus. Quisque leo nibh, molestie eu lobortis a, gravida ut libero. Ut rutrum ante ac nunc venenatis, et laoreet risus pellentesque. Donec nec tristique metus. Mauris porttitor sodales feugiat. Vestibulum nec consectetur nisl, non pulvinar dolor. Aenean id nunc at velit venenatis mollis. Ut sagittis ultrices est at blandit. Vestibulum vitae velit porttitor, tincidunt sapien vel, porttitor erat. In blandit sagittis eros, a molestie nisl.')
					, $manga_id);
				}
			} elseif($chapter_type == 'video') {
				for ($i = 1; $i <= 5; $i++) {
					$madara_sampledata_installer->_upload_single_content_chapter(array(
						'chapter_name' => 'Chapter ' . $i,
						'chapter_content' => '<div><iframe width="560" height="315" src="https://www.youtube.com/embed/XtxvJAerXG0" title="⁴ᴷ New York Comic Con 2021 - Sunday (October 10, 2021)" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe></div>'
					), $manga_id);
				}
			} else {
				$chapters = array(
					array('name' => 'Chapter 1', 'extend_name' => 'Other Name 1'),
					array('name' => 'Chapter 2', 'extend_name' => 'Other Name 2'),
					array('name' => 'Chapter 3', 'extend_name' => 'Other Name 3'),
					array('name' => 'Chapter 4', 'extend_name' => 'Other Name 4'),
				);

				foreach ($chapters as $chapter) {
					$madara_sampledata_installer->_upload_single_chapter($chapter, $manga_id);
				}
			}
		}
	}
}

function madara_set_manga_images($manga_id, $image_slug) {
    $post_id = $manga_id;
    $thumbnail_path = MADARA_SAMPLE_DATA_URL . '/images/' . $image_slug . '.jpg';
    $banner_path = MADARA_SAMPLE_DATA_URL . '/images/' . $image_slug . '-banner.jpg';
    // Check if the thumbnail exists
	$image_data = wp_remote_get($thumbnail_path);
	
	if(!is_wp_error($image_data) && 200 === wp_remote_retrieve_response_code( $image_data ) ){
        $upload = wp_upload_bits(basename($thumbnail_path), null, $image_data['body']);

        if (!$upload['error']) {
            $attachment = array(
                'post_mime_type' => 'image/jpeg',
                'post_title'     => basename($thumbnail_path),
                'post_content'   => '',
                'post_status'    => 'inherit',
            );

            $attachment_id = wp_insert_attachment($attachment, $upload['file'], $post_id);
            $attachment_metadata = wp_generate_attachment_metadata($attachment_id, $upload['file']);
            wp_update_attachment_metadata($attachment_id, $attachment_metadata);
			error_log("Set thumbnail: " . $attachment_id);
            set_post_thumbnail($post_id, $attachment_id);
        }
    } else {
		error_log("File not found or cannot download: " . $thumbnail_path);
	}

    // Check if the banner exists
	$image_data = wp_remote_get($banner_path);
	
	if(!is_wp_error($image_data) && 200 === wp_remote_retrieve_response_code( $image_data )){
        $upload = wp_upload_bits(basename($banner_path), null, $image_data['body']);

        if (!$upload['error']) {
            $attachment = array(
                'post_mime_type' => 'image/jpeg',
                'post_title'     => basename($banner_path),
                'post_content'   => '',
                'post_status'    => 'inherit',
            );

            $attachment_id = wp_insert_attachment($attachment, $upload['file'], $post_id);
            $attachment_metadata = wp_generate_attachment_metadata($attachment_id, $upload['file']);
            wp_update_attachment_metadata($attachment_id, $attachment_metadata);

            $attachment_url = wp_get_attachment_url($attachment_id);
			error_log("Update banner: " . $attachment_url);
            update_post_meta($post_id, 'manga_banner', $attachment_url);
        }
    } else {
		error_log("File not found or cannot download: " . $banner_path);
	}
}

function external_file_exists($url) {
	$headers = @get_headers($url);
	return $headers && strpos($headers[0], '200') !== false;
}

add_filter ('madara_required_plugins', 'madara_sampledata_required_plugins');
function madara_sampledata_required_plugins($madara_required_plugins) {
	$madara_required_plugins[] = array(
		'name' => 'Advanced Import : One Click Import for WordPress or Theme Demo Data',
		'slug' => 'advanced-import',
		'required' => false,
	);

	return $madara_required_plugins;
}

if(!function_exists('error_log_die')){
	function error_log_die($args){
		/**
		 * 'function' => __FUNCTION__,
						'message'  => "Cannot make dir $extract",
						'cancel'   => true,
		 */
		error_log($args['function']);
		error_log($args['message']);

		if(isset($args['cancel']) && $args['cancel']){
			wp_die();
		}
	}
}