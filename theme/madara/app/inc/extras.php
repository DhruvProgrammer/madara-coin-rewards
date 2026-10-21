<?php

	/**
	 * helper functions
	 */

	use App\Madara;
	use App\Models\Database;

	/**
	 * Get Front-Page template query settings
	 *
	 * $manga_type = '{empty}|manga|text|video';
	 */
	function madara_get_front_page_query( $post_type = 'post', $page = 1, $manga_type = '' ) {
		$posts_per_page = Madara::getOption( 'page_post_count' ) ? Madara::getOption( 'page_post_count' ) : get_option( 'posts_per_page' );
		$cats           = Madara::getOption( 'page_post_cats' );
		$tags           = Madara::getOption( 'page_post_tags' );
		$ids            = Madara::getOption( 'page_post_ids' );
		$order          = Madara::getOption( 'page_post_order' );
		$orderby        = Madara::getOption( 'page_post_orderby' );

		if ( $orderby == 'name' ) {
			$order = 'ASC';
		}

		$args = array(
			'post_type'  => $post_type,
			'categories' => $cats,
			'tags'       => $tags,
			'ids'        => $ids
		);
		
		if($post_type == 'wp-manga'){
			if($manga_type != ''){
				$args['meta_query_value'] = $manga_type;
				$args['key'] = '_wp_manga_chapter_type';
			}
		}

		return Database::getPosts( $posts_per_page, $order, $page, $orderby, $args );
	}
	
	function madara_starts_with_posts_where( $where, $query ) {
		global $wpdb;

		$starts_with = get_query_var('starts_with') ? get_query_var('starts_with') : $query->get('starts_with');
		
		if ( $starts_with != '' ) {
			if($starts_with == '-0'){
				$where .= " AND $wpdb->posts.post_title LIKE '0%'";
			} else {
				$where .= " AND $wpdb->posts.post_title LIKE '$starts_with%'";
			}
		}

		return $where;
	}
	add_filter( 'posts_where', 'madara_starts_with_posts_where', 10, 2 );
	
	function manga_listing_alphabeta_bars( $baseurl ) {
		$start = '';
		if ( isset($_GET['start']) && $_GET['start'] != '' ) {
			$start = $_GET['start'];
		}
		?>
		<div id="manga-filte-alphabeta-bar">
			<a href="<?php echo remove_query_arg('start', $baseurl);?>"><?php echo esc_html__('All', 'madara');?></a>
		<?php
		$characters = str_split(apply_filters('madara_manga_title_characters', '0123456789abcdefghijklmnopqrstuvwxyz'));
		foreach($characters as $c){
			?>
			<a title="<?php echo sprintf(esc_html__('Manga starts with %s', 'madara'), $c);?>" class="<?php echo esc_html($c == $start ? 'active':'');?>" href="<?php echo esc_url(add_query_arg('start', $c, $baseurl));?>"><?php echo esc_html($c);?></a>
			<?php
		}
		?>
		</div>
		<?php
	}

	/**
	 * Adds custom classes to the array of body classes.
	 *
	 * @param array $classes Classes for the body element.
	 *
	 * @return array
	 */
	function madara_body_classes( $classes ) {
		$is_manga_reading_page = false;
		if( function_exists( 'is_manga_reading_page' ) && is_manga_reading_page() ) {
			$is_manga_reading_page = true;
		}
		
		$classes[] = 'page';

		$header_layout = Madara::getOption( 'header_style', 1 );
		$classes[]     = 'header-style-' . $header_layout;

		// if we are in Full Page template and Sectionized mode, sticky menu should be turned off
		if ( is_page() && basename( get_page_template() ) == 'fullpage.php' && get_post_meta( get_the_ID(), 'fullpage_autoscrolling', true ) == 'on' ) {
			// do nothing
			
		} else {
			
			$sticky_menu       = Madara::getOption( 'nav_sticky', 0);
			$sticky_reading_nav = Madara::getOption( 'manga_reading_sticky_navigation', 'on' );
			$sticky_reading_header = Madara::getOption( 'manga_reading_sticky_header', '' );
			if ( ($sticky_menu != 0 && !($is_manga_reading_page && $sticky_reading_header == 'off')) || ($is_manga_reading_page && ($sticky_reading_header == 'on')) ) {
				$classes[] = 'sticky-enabled';
				$classes[] = 'sticky-style-' . $sticky_menu;
			}
		}

		$sidebar = madara_get_theme_sidebar_setting();
		if ( $sidebar != 'full' && is_active_sidebar( 'main_sidebar' ) ) {
			$classes[] = 'is-sidebar';
		}

		$user_id = get_current_user_id();
		if($user_id){
			$body_schema = get_user_meta( $user_id, '_manga_user_site_schema', true);
		}
		
		$body_schema           = (isset($body_schema) && $body_schema != '') ? $body_schema : Madara::getOption( 'body_schema', 'light' );
		
		$overwrite_body_schema = isset( $_GET['body_schema'] ) && $_GET['body_schema'] != '' ? $_GET['body_schema'] : '';

		if ( $overwrite_body_schema != '' ) {
			if ( $overwrite_body_schema == 'dark' ) {
				$classes[] = 'text-ui-light';
			} else {
				$classes[] = 'text-ui-dark';
			}
		} else {
			if ( $body_schema == 'light' ) {
				$classes[] = 'text-ui-dark';
			} else {
				$classes[] = 'text-ui-light';
			}
		}
		
		global $wp_manga_setting;
			
		if(!isset($wp_manga_setting)){
			return $classes;
		}

		if ( is_manga_single() || is_manga_reading_page() ) {
			$manga_adult_content = get_post_meta( get_the_ID(), 'manga_adult_content', true );
			if ( ! empty( $manga_adult_content ) && $manga_adult_content[0] == 'yes' ) {
				$classes[] = 'adult-content censored';
			}
		}

		if ( $is_manga_reading_page ) {
			global $wp_manga_functions;
			
			$manga_reading_style = isset( $_GET['style'] ) ? $_GET['style'] : $wp_manga_functions->get_reading_style();
			
			$classes[] = 'manga-reading-' . $manga_reading_style . '-style';

			$floating_header = Madara::getOption('reading_page_floating_nav', 'off');
			if($floating_header == 'on'){
				$classes[] = 'floating-reading-tools';
			}
		}
		
		$manga_archives_item_type_icon = Madara::getOption('manga_archives_item_type_icon', 'off');
		if($manga_archives_item_type_icon == 'on'){
			$classes[] = 'manga-type-icon';
		}
		
		$minimal_reading_layout = Madara::getOption('minimal_reading_page', 'off');
		if($minimal_reading_layout == 'on'){
			$classes[] = 'minimal-reading-layout';
		}
		
		$sticky_for_mobile = Madara::getOption('manga_reading_sticky_navigation_mobile', 'off');
		if($sticky_for_mobile == 'on'){
			$classes[] = 'sticky-for-mobile';
		}

		return $classes;
	}

	add_filter( 'body_class', 'madara_body_classes' );

	add_filter( 'document_title_parts', 'madara_wp_title' );
	function madara_wp_title( $title ) {

		if ( is_404() ) {
			$title['title'] = Madara::getOption( 'page404_head_tag', $title['title'] );
		}

		return $title;
	}

	/**
	 * Use for global wp_query, get total number of posts in a query
	 */
	function madara_get_found_posts( $custom_query = null ) {
		if ( ! $custom_query ) {
			global $wp_query;
			$custom_query = $wp_query;
		}

		$found_posts = 0;
		if ( $custom_query ) {
			$found_posts = $custom_query->found_posts;
		}

		return $found_posts;
	}

	/**
	 * Use for global wp_query, get total number of posts in a query
	 */
	function madara_get_post_count( $custom_query = null ) {

		if ( ! $custom_query ) {
			global $wp_query;
			$custom_query = $wp_query;
		}

		$post_count = 0;
		if ( $custom_query ) {
			$post_count = $custom_query->post_count;
		}

		return $post_count;
	}

	/**
	 * Setup postdata for object $item
	 */
	function madara_setup_postdata( $item ) {
		global $post;
		$post = $item;
		setup_postdata( $post );
	}

	/**
	 * Set custom query to be Main Query, so we can use Template Tags like normal
	 *
	 * @return WP_Query main query to be returned later
	 */
	function madara_set_main_query( $custom_query ) {
		global $wp_query;

		$temp_query = $wp_query;

		$wp_query = $custom_query;

		return $temp_query;
	}
	
	add_filter('madara_ajax_query_arguments', 'madara_add_ajax_add_query_arguments');
	/**
	 * Add more arguments to the main query for ajax pagination
	 **/
	function madara_add_ajax_add_query_arguments( $args ){
		global $wp_manga_functions;
		
		if(isset($wp_manga_functions)){
			if((is_page() && is_page_template('page-templates/front-page.php')) || $wp_manga_functions->is_manga_posttype_archive()){
				$manga_archives_item_layout = Madara::getOption( 'manga_archives_item_layout', '' );
				
				$args['manga_archives_item_layout'] = $manga_archives_item_layout;
			}
		}
		
		return $args;
	}
	
	function madara_get_badge_choices(){
		return apply_filters('madara_manga_default_badges', array('hot' => esc_html__( 'Hot', 'madara' ), 'new' => esc_html__( 'New', 'madara' )));
	}