<?php

	/**
	 * 1.0
	 * @package    Madara
	 * @author     WPStylish <wpstylish@gmail.com>
	 * @copyright  Copyright (C) 2018 mangabooth.com. All Rights Reserved
	 *
	 * Websites: https://mangabooth.com/
	 */

	namespace App;

	// Prevent direct access to this file
	defined( 'ABSPATH' ) || die( 'Direct access to this file is not allowed.' );

	require( get_template_directory() . '/app/core.php' );

	require( 'lib/walker_mobile_menu.class.php' );

	if ( class_exists( 'WP_MANGA' ) ) {
		/*
		 * check plugin wp-manga active or not.
		 * */
		require( get_template_directory() . '/manga-functions.php' );
	}

	/**
	 * Core class.
	 *
	 * @package  Madara
	 * @since    1.0
	 */
	class MadaraStarter extends Madara {

		private static $instance;

		public static function getInstance() {
			if ( null == self::$instance ) {
				self::$instance = new MadaraStarter();
			}

			return self::$instance;
		}

		/**
		 * Initialize Madara Core.
		 *
		 * @return  void
		 */
		public function initialize() {
			add_action( 'template_redirect', array( $this, 'set_content_width' ), 0 );

			parent::initialize();

			if ( class_exists( 'woocommerce' ) ) {
				Plugins\madara_WooCommerce\WooCommerce::initialize();
			}

			/**
			 * Custom template tags and functions for this theme.
			 */
			require( get_template_directory() . '/inc/template-tags.php' );
			require( get_template_directory() . '/inc/extras.php' );
			require( get_template_directory() . '/inc/hooks.php' );

			if(!class_exists('OT_Loader')){
				add_filter( 'ot_theme_mode', '__return_true' );
				add_filter('ot_show_options_ui', '__return_false');
				add_filter('ot_show_settings_import', '__return_false');
				add_filter('ot_show_settings_export', '__return_false');
				add_filter('ot_show_new_layout', '__return_false');
				add_filter('ot_show_docs', '__return_false');
				add_filter('ot_use_theme_options', '__return_false');
				add_filter('ot_show_pages', '__return_false');

				require( get_template_directory() . '/app/plugins/option-tree/ot-loader.php' );
			}			
			
			require( get_template_directory() . '/app/customizer/customizer.php' );

			add_action( 'after_setup_theme', array( $this, 'addThemeSupport' ) );
			add_action( 'widgets_init', array( $this, 'registerSidebar' ) );
			add_action( 'after_setup_theme', array( $this, 'registerNavMenus' ) );
			add_action('init', array($this, 'init'));
			add_action('admin_init', array($this, 'admin_init'));

			add_action( 'wp_enqueue_scripts', array( $this, 'enqueueScripts' ) );

			add_action( 'madara_release_logs', array( $this, 'release_logs' ) );
			add_filter( 'theme_page_templates', array( $this, 'makewp_exclude_page_templates' ) );

			if(file_exists(get_template_directory() . '/sample-data/sample_data.php')){
				require(get_template_directory() . '/sample-data/sample_data.php');
				$installer = new \madara_sampledata_installer();
			}

			require ( get_template_directory() . '/elementor/elementor-register.php');
		}

		function admin_init(){
			if (!current_user_can('manage_options')) {
				return;
			}

			add_action('wp_ajax_customizer_get_css', array($this, 'customizer_get_css'));

			global $pagenow;
			if($pagenow == 'edit.php' && isset($_GET['page']) && $_GET['page'] == 'wp-manga-settings'){
				if(isset($_GET['action']) && $_GET['action'] == 'importot'){
					
					if(class_exists( 'OT_Loader' ) && defined( 'ABSPATH' )){
						$optiontree_data = get_option('option_tree', array());
						
						foreach($optiontree_data as $option => $val){
							if($val == 'on'){
								$val = 1;
							} else if($val == 'off'){
								$val = 0;
							}
							set_theme_mod($option, $val);
						}
					}
					
					echo '<div class="notice notice-warning settings-error is-dismissible"><p><strong>Imported. You can safely remove OptionTree plugin. Go to <a href="' . admin_url('customize.php') . '">Theme Customizer</a> now</strong></p></div>';
				}
			}
		}

		/**
		 * Called by the customizer when a color changes, it updates the custom CSS on page
		 */
		function customizer_get_css(){
			$madara             = new App\Madara();

			//require( get_template_directory() . '/css/custom.css.php' );
			//$custom_css = madara_custom_CSS();
			$main_color          = $madara->getOption('main_color', '');
			wp_send_json_success($main_color);
		}

		function init(){
			if($this->getOption('amp', 'off') == 'on'){
				require( get_template_directory() . '/inc/amp.php' );
			}
		}

		/**
		 * Set the content width in pixels, based on the theme's design and stylesheet.
		 *
		 * Priority 0 to make it available to lower priority callbacks.
		 *
		 * @global int $content_width
		 */
		function set_content_width() {

			$content_width = 980;

			$GLOBALS['content_width'] = apply_filters( 'madara_content_width', $content_width );
		}

		/**
		 * Hides the custom post template for pages on WordPress 4.6 and older
		 *
		 * @param array $post_templates Array of page templates. Keys are filenames, values are translated names.
		 *
		 * @return array Filtered array of page templates.
		 */
		function makewp_exclude_page_templates( $post_templates ) {
			if ( version_compare( $GLOBALS['wp_version'], '4.7', '<' ) ) {
				// unset( $post_templates['page-templates/my-full-width-post-template.php'] );
			}

			return $post_templates;
		}

		/**
		 * Add Theme Support
		 *
		 * @return void
		 */
		function addThemeSupport() {

			load_theme_textdomain( 'madara', get_template_directory() . '/languages' );

			add_theme_support( 'automatic-feed-links' );

			add_theme_support( "title-tag" );

			add_theme_support( 'post-thumbnails' );

			add_theme_support( 'html5', array(
				'comment-form',
				'comment-list',
				'search-form',
				'gallery',
				'caption',
			) );

			add_theme_support( 'wp-block-styles' );
			add_theme_support( 'responsive-embeds' );
			add_theme_support( 'align-wide' );
			add_theme_support( 'align-full' );

			// register thumb sizes
			do_action( 'madara_reg_thumbnail' );

			remove_theme_support( 'widgets-block-editor' );
		}

		/**
		 * Madara Sidebar Init
		 *
		 * @since Madara Alpha 1.0
		 */
		function registerSidebar() {
			/*
			 * register WP Manga Main Top Sidebar & WP Manga Main Top Second Sidebar when plugin wp-manga activated.
			 * */
			do_action( 'madara_add_manga_sidebar' );

			$main_sidebar_before_widget = apply_filters( 'madara_main_sidebar_before_widget', '<div class="row"><div id="%1$s" class="widget %2$s"><div class="widget__inner %2$s__inner c-widget-wrap">' );
			$main_sidebar_after_widget  = apply_filters( 'madara_main_sidebar_after_widget', '</div></div></div>' );

			$before_widget = apply_filters( 'madara_sidebar_before_widget', '<div id="%1$s" class="widget %2$s"><div class="widget__inner %2$s__inner c-widget-wrap">' );
			$after_widget  = apply_filters( 'madara_sidebar_after_widget', '</div></div>' );

			$before_title = '<div class="widget-heading font-nav"><h5 class="heading">';
			$after_title  = '</h5></div>';

			register_sidebar( array(
				'name'          => esc_html__( 'Main Sidebar', 'madara' ),
				'id'            => 'main_sidebar',
				'description'   => esc_html__( 'Main Sidebar used by all pages', 'madara' ),
				'before_widget' => $main_sidebar_before_widget,
				'after_widget'  => $main_sidebar_after_widget,
				'before_title'  => $before_title,
				'after_title'   => $after_title,
			) );

			register_sidebar( array(
				'name'          => esc_html__( 'Single Post Sidebar', 'madara' ),
				'id'            => 'single_post_sidebar',
				'description'   => esc_html__( 'Appear in Single Post', 'madara' ),
				'before_widget' => $main_sidebar_before_widget,
				'after_widget'  => $main_sidebar_after_widget,
				'before_title'  => $before_title,
				'after_title'   => $after_title,
			) );

			register_sidebar( array(
				'name'          => esc_html__( 'Search Sidebar', 'madara' ),
				'id'            => 'search_sidebar',
				'description'   => esc_html__( 'Search Sidebar in header', 'madara' ),
				'before_widget' => $before_widget,
				'after_widget'  => $after_widget,
				'before_title'  => $before_title,
				'after_title'   => $after_title,
			) );

			register_sidebar( array(
				'name'          => esc_html__( 'Main Top Sidebar', 'madara' ),
				'id'            => 'top_sidebar',
				'description'   => esc_html__( 'Appear before main content', 'madara' ),
				'before_widget' => $before_widget,
				'after_widget'  => $after_widget,
				'before_title'  => $before_title,
				'after_title'   => $after_title,
			) );

			register_sidebar( array(
				'name'          => esc_html__( 'Main Top Second Sidebar', 'madara' ),
				'id'            => 'top_second_sidebar',
				'description'   => esc_html__( 'Appear before main content', 'madara' ),
				'before_widget' => $before_widget,
				'after_widget'  => $after_widget,
				'before_title'  => $before_title,
				'after_title'   => $after_title,
			) );

			register_sidebar( array(
				'name'          => esc_html__( 'Body Top Sidebar', 'madara' ),
				'id'            => 'body_top_sidebar',
				'description'   => esc_html__( 'Appear before body content', 'madara' ),
				'before_widget' => $before_widget,
				'after_widget'  => $after_widget,
				'before_title'  => $before_title,
				'after_title'   => $after_title,
			) );

			register_sidebar( array(
				'name'          => esc_html__( 'Body Bottom Sidebar', 'madara' ),
				'id'            => 'body_bottom_sidebar',
				'description'   => esc_html__( 'Appear after body content', 'madara' ),
				'before_widget' => '<div id="%1$s" class="widget %2$s"><div class="widget__inner %2$s__inner c-widget-wrap">',
				'after_widget'  => '</div></div>',
				'before_title'  => '<div class="widget-title"><div class="c-blog__heading style-2 font-heading"><h4>',
				'after_title'   => '</h4></div></div>',
			) );

			register_sidebar( array(
				'name'          => esc_html__( 'Main Bottom Sidebar', 'madara' ),
				'id'            => 'bottom_sidebar',
				'description'   => esc_html__( 'Appear after main content', 'madara' ),
				'before_widget' => $before_widget,
				'after_widget'  => $after_widget,
				'before_title'  => $before_title,
				'after_title'   => $after_title,
			) );

			register_sidebar( array(
				'name'          => esc_html__( 'Footer Sidebar', 'madara' ),
				'id'            => 'footer_sidebar',
				'description'   => esc_html__( 'Appear in Footer', 'madara' ),
				'before_widget' => $before_widget,
				'after_widget'  => $after_widget,
				'before_title'  => $before_title,
				'after_title'   => $after_title,
			) );
		}

		/**
		 * Register Menu Location
		 *
		 * @since Madara Alpha 1.0
		 */
		function registerNavMenus() {
			register_nav_menus( array(
				'primary_menu'   => esc_html__( 'Primary Menu', 'madara' ),
				'secondary_menu' => esc_html__( 'Secondary Menu', 'madara' ),
				'mobile_menu'    => esc_html__( 'Mobile Menu', 'madara' ),
				'user_menu'      => esc_html__( 'User Menu', 'madara' ),
				'footer_menu'    => esc_html__( 'Footer Menu', 'madara' ),
			) );
		}

		/**
		 * Enqueue needed scripts
		 */
		function enqueueScripts() {
			if ( $this->getOption( 'loading_fontawesome', 'on' ) == 'on' ) {
				wp_enqueue_style( 'fontawesome', get_parent_theme_file_uri( '/app/lib/fontawesome/web-fonts-with-css/css/all.min.css' ), array(), '5.15.3' );
			}
			if ( $this->getOption( 'loading_ionicons', 'on' ) == 'on' ) {
				wp_enqueue_style( 'ionicons', get_parent_theme_file_uri( '/css/fonts/ionicons/css/ionicons.min.css' ), array(), '4.5.10' );
			}
			if ( $this->getOption( 'loading_ct_icons', 'on' ) == 'on' ) {
				wp_enqueue_style( 'madara-icons', get_parent_theme_file_uri( '/css/fonts/ct-icon/ct-icon.css' ) );
			}

			wp_enqueue_style( 'bootstrap', get_parent_theme_file_uri( '/css/bootstrap.min.css' ), array(), '4.3.1' );
			wp_enqueue_style( 'slick', get_parent_theme_file_uri( '/js/slick/slick.css' ), array(), '1.9.0' );
			wp_enqueue_style( 'slick-theme', get_parent_theme_file_uri( '/js/slick/slick-theme.css' ) );

            // currently on Oneshot Reading page is needed for lightbox
            $is_manga_oneshot = (defined('WP_MANGA_VER') && WP_MANGA_VER >= 1.66) ? is_manga_oneshot() : 0;

            if($is_manga_oneshot){
                wp_enqueue_style( 'lightbox', get_parent_theme_file_uri( '/css/lightbox.min.css' ), array(), '2.11.2' );
            }
			//Temporary
			wp_enqueue_style( 'loaders', get_parent_theme_file_uri( '/css/loaders.min.css' ) );

			wp_enqueue_style( 'madara-css', get_stylesheet_uri(), array(), '2.2.3' );

			if(class_exists('Elementor\Plugin')){
				wp_enqueue_style( 'madara-elementor', get_parent_theme_file_uri( '/elementor/elementor.css' ) );
			}
			
			wp_enqueue_script( 'imagesloaded' );
			wp_enqueue_script( 'slick', get_parent_theme_file_uri( '/js/slick/slick.min.js' ), array( 'jquery' ), '1.9.0', true );
			wp_enqueue_script( 'aos', get_parent_theme_file_uri( '/js/aos.js' ), array(), '', true );

            wp_enqueue_script( 'madara-js', get_parent_theme_file_uri( '/js/template.js' ), array(
				'jquery',
				'bootstrap',
				'shuffle'
			), '2.2.5.3', true );

            if($is_manga_oneshot){
                wp_enqueue_script( 'lightbox', get_parent_theme_file_uri( '/js/lightbox.min.js' ), array( 'jquery' ), '2.11.2', true );
            }

            global $wp_manga_functions;

            if($wp_manga_functions && $wp_manga_functions->is_user_settings_page() && isset($_GET['tab']) && $_GET['tab'] == 'account-settings') {
                wp_enqueue_script( 'password-strength-meter' );
                wp_enqueue_script( 'madara-js-user-settings', get_parent_theme_file_uri( '/js/template-user-settings.js' ), array(
				'madara-js'), '1.7.1.1', true );
            }

			wp_enqueue_script( 'madara-ajax', get_parent_theme_file_uri( '/js/ajax.js' ), array( 'jquery' ), '', true );

			$js_params = array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) );

			global $wp_query, $wp;

			$js_params['query_vars']  = $wp_query->query_vars;
			$js_params['current_url'] = home_url( $wp->request );

			wp_localize_script( 'madara-js', 'madara', apply_filters( 'madara_js_params', $js_params ) );

			/**
			 * Add Custom CSS
			 */
			require( get_template_directory() . '/css/custom.css.php' );
			$custom_css = madara_custom_CSS();
			wp_add_inline_style( 'madara-css', $custom_css );
		}

		public function release_logs() {
			?>
            <ul>
				<li>Version 2.2.7 - 2025.12.08<br/>
					<ul>
						<li>#Add: experimental feature to encrypt Novel Chapter content (Customizer > Manga Reading Page), protect chapter content from being copied</li>
						<li>#Update: Front-Page template with new parameters (ignore tags & genres)</li>
						<li>#Update: Preload Ads Link for Next/Prev buttons</li>
						<li>#Update: WP Manga Core plugin with updated settings page</li>
						<li>#Update: new WP Manga Settings to set default Chapter Type (no need to select type when creating new Manga)</li>
						<li>#Update: option to show Manga Chapters Count in Manga Info section (Customizer > Manga Detail Page)</li>
						<li>#Update: option to show Manga Tags in Manga Summary section (Customizer > Manga Detail Page)</li>
						<li>#Fix: Front-Page template sort by Latest Manga issue</li>
					</ul>
				</li>
				<li>Version 2.2.6.1 - 2025.11.24<br/>
					<ul>
						<li>#Fix: warning log bugs</li>
						<li>#Fix: history manga issue</li>
						<li>#Fix: trending manga issue</li>
					</ul>
				</li>
				<li>Version 2.2.6 - 2025.11.09<br/>
					<ul>
						<li>#Update: update Sort by Name feature (WP Manga Settings > Legacy Settings)</li>
						<li>#Update: improve performance by using cache and combined queries</li>
						<li>#Fix: fix Adult Filter compatibility with Litespeed</li>
						<li>#Update: improve RTL</li>
						<li>#Fix: fix minor issues</li>
					</ul>
				</li>
				<li>Version 2.2.5.3 - 2025.10.08<br/>
					<ul>
						<li>#Update: Madara Shortcodes plugin security enhancements</li>
						<li>#Update: remove found_posts filter</li>
						<li>#Update: improve gphotos storage</li>
						<li>#Fix: fix user login off canvas issue</li>
						<li>#Fix: purge cache when bookmarking</li>
						<li>#Fix: fix Recent Comments widget (show avatar , show datetime options do not work)</li>
						<li>#Fix: fix caching issue</li>
						<li>#Fix: fix font family issue</li>
						<li>#Fix: fix missing Show Rating option in Customizer</li>
						<li>#Fix: fix minor bugs</li>
					</ul>
				</li>
				<li>Version 2.2.5.2 - 2025.08.08<br/>
					<ul>
						<li>#Fix: minor warning bugs</li>
					</ul>
				</li>
				<li>Version 2.2.5.1 - 2025.08.07<br/>
					<ul>
						<li>#Update: improve Caching (refresh cache when update Manga)</li>
					</ul>
				</li>	
				<li>Version 2.2.5 - 2025.07.29<br/>
					<ul>
						<li>#Add: option to hide Action Buttons (Bookmark, Toogle Schema) in Chapter Reading page</li>
						<li>#Add: option to make Navigation Buttons floating in Chapter Reading page</li>
						<li>#Update: improve Caching</li>
						<li>#Update: new "num_chapters" property in [manga_listing] shortcode</li>	
						<li>#Update: option to add more images to chapter using Google Photos Album import</li>					
						<li>#Fix: Google Photos storage issues</li>
						<li>#Fix: various bugs</li>
					</ul>
				</li>
				<li>Version 2.2.4 - 2025.07.07<br/>
					<ul>
						<li>#Update: allow to create Google Photos Album directly before uploading</li>
						<li>#Fix: security vulnarability</li>
						<li>#Fix: latest OneSignal compatibility (v3)</li>						
						<li>#Fix: minor issues</li>
					</ul>
				</li>
				<li>Version 2.2.3 - 2025.06.25<br/>
					<ul>
						<li>#Update: Google Photos API change</li>
						<li>#Update: improve loading default Poppins Google Font, option to disable this font</li>
						<li>#Update: allow downloading a single text chapter</li>						
						<li>#Fix: Preloading Ads does not work in Ajax pagination</li>
						<li>#Fix: Ajax search for Manga does not search in Alternative Name</li>
						<li>#Fix: minor bugs</li>
					</ul>
				</li>
				<li>Version 2.2.2.3 - 2025.06.14<br/>
					<ul>
						<li>#Update: add Limit Chapter Name visible lines & Show Chapter Release Date setting in Theme Customize > Manga Archives</li>
						<li>#Update: improve PageSpeed Insight score</li>
						<li>#Update: add volume name on Page Title and %vol% for placeholder in SEO setting</li>
						<li>#Update: WooCommerce template file version</li>
						<li>#Update: enable Show More manga summary content in Single Header layout 2</li>
						<li>#Fix: minor bugs</li>
					</ul>
				</li>
				<li>Version 2.2.2.2 - 2025.05.03<br/>
					<ul>
						<li>#Update: allow translating default Manga Badge</li>
						<li>#Fix: support caching for WP Manga Users Upload PRO and WP Manga Chapters Permission</li>
						<li>#Fix: minor bugs</li>
					</ul>
				</li>
				<li>Version 2.2.2 - 2025.05.03<br/>
					<ul>
						<li>#Add: Pre-load Ads Link for Manga and Chapter</li>
						<li>#Update: add Manga Archives Item Columns setting in Theme Customize</li>
						<li>#Fix: minor bugs</li>
					</ul>
				</li>
				<li>Version 2.2.1 - 2025.04.20<br/>
					<ul>
						<li>#Update: Widget Recent Comments / add options to show Comment Date & User Avatar</li>
						<li>#Update: some CSS improvements</li>
						<li>#Fix: cannot change Custom Fonts in Theme Customize </li>
						<li>#Fix: [manga_listing] shortcode / property "chapter_type" does not work</li>
					</ul>
				</li>
				<li>Version 2.2 - 2025.04.07<br/>
					<ul>
						<li>#Update: Improve Security & Performance</li>
						<li>#Fix: Front-Page Settings section is hidden sometimes</li>
						<li>#Fix: minor bugs and improvements</li>
					</ul>
				</li>
			<li>Version 2.1.2 - 2025.03.19<br/>
				<ul>
					<li>#Hot Fix: built-in Sitemap is disabled by default to increase performance. To enable Sitemap, use this filter "wp_manga_sitemap_enabled". For example<br/>
						<code>add_filter("wp_manga_sitemap_enabled", "my_site_wp_manga_sitemap_enabled");<br/>
						function my_site_wp_manga_sitemap_enabled($enabled){<br/>
							return true;<br/>
						}<br/>
					</code>
					</li>
				</ul>
			</li>
			<li>Version 2.1.1 - 2025.03.13<br/>
					<ul>
						<li>#Fix: bugs with Front-Page ajax pagination</li>
						<li>#Fix: minor bugs and improvements</li>
					</ul>
				</li>
				<li>Version 2.1 - 2025.03.02<br/>
					<ul>
						<li>#Add: WP Manga - Recent Comments widget</li>
						<li>#Update: suport WP Discuz</li>
						<li>#Update: new conditional tags (is_manga_genre, is_manga_tag, is_adult_manga)</li>
						<li>#Fix: Ajax pagination in Archives page</li>
						<li>#Fix: minor bugs and improvements</li>
					</ul>
				</li>
				<li>Version 2.0.1 - 2025.02.26<br/>
					<ul>
						<li>#Fix: mobile menu with Header 2 & 3 layout</li>
						<li>#Update: new settings for Elementor Manga Listing widget</li>
						<li>#Fix: many bugs and improvements</li>
					</ul>
				</li>
				<li>Version 2.0 - 2025.02.01<br/>
					<ul>
						<li>#Add: Support Elementor and new Elementor widgets</li>
						<li>#Add: Chapter Views feature</li>
						<li>#Add: new Item Layout (Big Thumbnail 2) & Badge Style (Style 2)</li>
						<li>#Add: shortcode [advancesearchform] to build a custom search page</li>
						<li>#Add: shortcode [madara_allterms] to build a custom Taxonomy (Genres, Tags...) page</li>
						<li>#Add: new Demo Import feature to build a manga, novel or web drama sites</li>
						<li>#Update: improve Manga Listing shortcode with new parameters</li>
						<li>#Update: improve Front-Page template with new parameters</li>
						<li>#Update: improve Search Results page with new paramters</li>
						<li>#Update: natively support Sitemap for Chapters</li>
						<li>#Update: pagination for Chapters List</li>
						<li>#Update: Header Style 3 (full-width)</li>
						<li>#Update: Multiple columns layout for dropdown main menu</li>
						<li>#Fix: many bugs and improvements</li>
					</ul>
				</li>
				<li>Full release logs: <a href="https://mangabooth.com/product/wp-manga-theme-madara/" target="_blank">Madara WordPress Theme</a></li>
            </ul>
			<?php
		}
	}


	$madara = MadaraStarter::getInstance();
	$madara->initialize();
