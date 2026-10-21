<?php

	use App\Madara;

	/**
	 * Get sidebar setting for a particular page
	 */

	function madara_get_theme_sidebar_setting() {
		$sidebar = '';
		if ( is_404() ) {
			$sidebar = 'hidden';
		} elseif ( class_exists( 'WP_MANGA' ) && is_manga_archive() ) {
			$sidebar = Madara::getOption( 'manga_archive_sidebar', 'right' );
		} elseif ( class_exists( 'WP_MANGA' ) && is_manga_single() ) {
			$sidebar = Madara::getOption( 'manga_single_sidebar', 'right' );
		} elseif ( class_exists( 'WP_MANGA' ) && is_manga_reading_page() ) {
			$sidebar = Madara::getOption( 'manga_reading_page_sidebar', 'right' );
		} elseif ( is_page() ) {
			$sidebar = Madara::getOption( 'page_sidebar', 'right' );
		} elseif ( class_exists( 'WP_MANGA' ) && ! is_manga() && is_archive() || ( class_exists( 'WP_MANGA' ) && class_exists( 'WP_MANGA' ) && ! is_manga() && is_front_page() && is_home() || ( class_exists( 'WP_MANGA' ) && ! is_manga() && is_home() ) ) ) {
			$sidebar = Madara::getOption( 'archive_sidebar', 'right' );
		} else {
			$sidebar = Madara::getOption( 'single_sidebar', 'right' );
		}

		return apply_filters( 'madara_sidebar_setting', $sidebar );
	}

	/**
	 * Get page title of all pages
	 */
	function madara_get_page_title() {

		$page_title = '';
		if ( is_home() ) {
			$page_title = Madara::getOption( 'blog_heading', '' );
			$page_title = $page_title ? $page_title : get_bloginfo( 'name' );
		} elseif ( is_search() ) {
			$page_title = esc_html__( 'Search Results', 'madara' );
		} elseif ( is_singular() ) {
			$page_title = get_the_title();
		} elseif ( is_archive() ) {
			$page_title = '';

			if ( is_category() ) :
				$page_title = single_cat_title( '', false );

			elseif ( is_tag() ) :
				$page_title = single_tag_title( '', false );

			elseif ( is_author() ) :
				$page_title = sprintf( esc_html__( 'Author: %s', 'madara' ), '<span class="vcard">' . get_the_author() . '</span>' );

			elseif ( is_day() ) :
				$page_title = sprintf( esc_html__( 'Day: %s', 'madara' ), '<span>' . get_the_date() . '</span>' );

			elseif ( is_month() ) :
				$page_title = sprintf( esc_html__( 'Month: %s', 'madara' ), '<span>' . get_the_date( _x( 'F Y', 'monthly archives date format', 'madara' ) ) . '</span>' );

			elseif ( is_year() ) :
				$page_title = sprintf( esc_html__( 'Year: %s', 'madara' ), '<span>' . get_the_date( _x( 'Y', 'yearly archives date format', 'madara' ) ) . '</span>' );

			elseif ( is_tax( 'post_format', 'post-format-aside' ) ) :
				$page_title = esc_html__( 'Asides', 'madara' );

			elseif ( is_tax( 'post_format', 'post-format-gallery' ) ) :
				$page_title = esc_html__( 'Galleries', 'madara' );

			elseif ( is_tax( 'post_format', 'post-format-image' ) ) :
				$page_title = esc_html__( 'Images', 'madara' );

			elseif ( is_tax( 'post_format', 'post-format-video' ) ) :
				$page_title = esc_html__( 'Videos', 'madara' );

			elseif ( is_tax( 'post_format', 'post-format-quote' ) ) :
				$page_title = esc_html__( 'Quotes', 'madara' );

			elseif ( is_tax( 'post_format', 'post-format-link' ) ) :
				$page_title = esc_html__( 'Links', 'madara' );

			elseif ( is_tax( 'post_format', 'post-format-status' ) ) :
				$page_title = esc_html__( 'Statuses', 'madara' );

			elseif ( is_tax( 'post_format', 'post-format-audio' ) ) :
				$page_title = esc_html__( 'Audios', 'madara' );

			elseif ( is_tax( 'post_format', 'post-format-chat' ) ) :
				$page_title = esc_html__( 'Chats', 'madara' );

			elseif ( is_tax( 'ct_portfolio_cat' ) ) :

				$term = get_queried_object();

				if ( $term ) {
					$page_title = sprintf( esc_html__( 'Projects in %s category', 'madara' ), $term->name );
				} else {
					$page_title = esc_html__( 'Archives', 'madara' );
				}

			elseif ( is_post_type_archive( 'ct_portfolio' ) ) :

				$text = ct_portfolio_get_filter_condition_in_words();

				if ( $text != '' ) {
					$page_title = $text;
				} else {

					$page_title = esc_html__( 'All Projects', 'madara' );
				}

			elseif ( is_post_type_archive( 'ct_office' ) ) :
				$page_title = esc_html__( 'Location', 'madara' );
			elseif ( is_post_type_archive( 'ct_service' ) ) :
				$page_title = esc_html__( 'All Services', 'madara' );
			else:
				$page_title = esc_html__( 'Archives', 'madara' );
			endif;

			$page_title = apply_filters( 'madara_archive_title', $page_title );
		}

		return $page_title;
	}

	function localize_show_more_text(){

		if( function_exists( 'is_manga_single' ) && is_manga_single() ){
			wp_localize_script( 'madara-js', 'single_manga_show_more', array(
				'show_more' => __( 'Show more  ', 'madara' ),
				'show_less' => __( 'Show less  ', 'madara' )
			) );
		}

	}
	add_action( 'wp_enqueue_scripts', 'localize_show_more_text', 30 );
	
	// do later
	function madara_filter_content($content){
		return $content;
	}
	
	function madara_get_user_settings_tabs(){
		global $wp_manga_user_actions;
		$default_tabs = [
			'bookmark' => array('url' => $wp_manga_user_actions->get_user_tab_url( 'bookmark' ), 'icon' => 'icon ion-ios-bookmark', 'label' => esc_html__( 'Bookmarks', 'madara' )),
			'history' => array('url' => $wp_manga_user_actions->get_user_tab_url( 'history' ), 'icon' => 'icon ion-md-alarm', 'label' => esc_html__( 'History', 'madara' )),
			'reader-settings' => array('url' => $wp_manga_user_actions->get_user_tab_url( 'reader-settings' ), 'icon' => 'icon ion-md-cog', 'label' => esc_html__( 'Reader Settings', 'madara' )),
			'account-settings' => array('url' => $wp_manga_user_actions->get_user_tab_url( 'account-settings' ), 'icon' => 'icon ion-md-person', 'label' => esc_html__( 'Account Settings', 'madara' )),
		];

		if(current_user_can('edit_posts')){
			$default_tabs['my-mangas'] = array('url' => $wp_manga_user_actions->get_user_tab_url( 'my-mangas' ), 'icon' => 'icon ion-md-folder-open', 'label' => esc_html__( 'My Uploaded Mangas', 'madara' ));
		}


		$reader_settings_tab = Madara::getOption( 'manga_reader_setting', 'on' );
		if($reader_settings_tab == 'off'){
			unset($default_tabs['reader-settings']);
		}
		
		if(Madara::getOption( 'madara_reading_history', 'on' ) == 'off'){
			unset($default_tabs['history']);
		}
				
		return apply_filters( 'madara_user_settings_tab_array_compare', $default_tabs);
	}
	
	/**
	 * Get search query args, used in Advanced Search Form
	 **/
	function madara_get_search_args(){
		$s         = sanitize_text_field(isset( $_GET['s'] ) ? $_GET['s'] : '');
		$s_genre   = isset( $_GET['genre'] ) ? $_GET['genre'] : array();
		$s_tags  = sanitize_text_field(isset( $_GET['tags'] ) ? $_GET['tags'] : '');
		$s_author  = sanitize_text_field(isset( $_GET['author'] ) ? $_GET['author'] : '');
		$s_artist  = sanitize_text_field(isset( $_GET['artist'] ) ? $_GET['artist'] : '');
		$s_release = sanitize_text_field(isset( $_GET['release'] ) ? $_GET['release'] : '');
		$s_status  = isset( $_GET['status'] ) ? $_GET['status'] : array();

		$s_orderby = sanitize_text_field(isset( $_GET['m_orderby'] ) ? $_GET['m_orderby'] : '');
		$s_paged   = sanitize_text_field(get_query_var( 'paged' ) ? get_query_var( 'paged' ) : 1);
		
		$s_args = array(
			's'        => $s,
			'orderby'  => $s_orderby,
			'paged'    => $s_paged,
			'template' => 'search'
		);
		
		$meta_query = array('relation' => 'AND');
		if ( ! empty( $s_status ) ) {
			$meta_query = array_merge($meta_query, array(
				array(
					'key'     => '_wp_manga_status',
					'value'   => $s_status,
					'compare' => 'IN'
				),
			));
		}
		
		$s_adult = isset( $_GET['adult'] ) ? $_GET['adult'] : '';
		if($s_adult != ''){
			if(!$s_adult){
				$meta_query = array_merge($meta_query, array(
                    array(
                        'key'     => 'manga_adult_content',
                        'value'   => ''
                    )
				));

			} else {
				$meta_query = array_merge($meta_query, array(
					array(
						'key'     => 'manga_adult_content',
						'value'   => serialize(array('yes'))
					),
				));
			}
		}
		
		$s_args['meta_query'] = $meta_query;

		$tax_query = array();

		if ( ! empty( $s_genre ) ) {
			$tax_args = array(
				'taxonomy' => 'wp-manga-genre',
				'slug'     => $s_genre
			);

			$queried_genre = new WP_Term_Query( $tax_args );
			$genres        = array();

			if ( ! empty( $queried_genre->get_terms() ) ) {
				foreach ( $queried_genre->get_terms() as $genre ) {
					$genres[] = $genre->term_id;
				}
			}

			if ( ! empty( $genres ) ) {
				$s_genre_condition = isset( $_GET['op'] ) ? $_GET['op'] : '';
				$or_genres = $s_genre_condition == '' ? true : false;
				
				if(!$or_genres){
					$genre_args = array();
					
					foreach($genres as $genre){
						$genre_args[] = array(
								'taxonomy' => 'wp-manga-genre',
								'field'    => 'term_id',
								'terms'    => array($genre),
								'operator' => 'IN'
							);
					}
					
					$tax_query[] = array(array_merge(array(
							'relation' => 'AND'
						), $genre_args));
						
				} else {
					$tax_query[] = array(
						'taxonomy' => 'wp-manga-genre',
						'field'    => 'term_id',
						'terms'    => $genres
					);
				}
			}
		}
		
		if(!empty($s_tags)){
			$tax_query[] = array(
						'taxonomy' => 'wp-manga-tag',
						'field'    => 'slug',
						'terms'    => explode(',', $s_tags)
					);
		}

		if ( ! empty( $s_author ) ) {
			$tax_args = array(
				'taxonomy' => 'wp-manga-author',
				'search'   => $s_author
			);

			$queried_author = new WP_Term_Query( $tax_args );
			$authors        = array();

			if ( ! empty( $queried_author->get_terms() ) ) {
				foreach ( $queried_author->get_terms() as $author_term ) {
					$authors[] = $author_term->term_id;
				}
			}

			if ( ! empty( $s_author ) ) {
				$tax_query[] = array(
					'taxonomy' => 'wp-manga-author',
					'field'    => 'term_id',
					'terms'    => $authors
				);
			}
		}

		if ( ! empty( $s_artist ) ) {
			$tax_args = array(
				'taxonomy' => 'wp-manga-artist',
				'search'   => $s_artist
			);

			$queried_artist = new WP_Term_Query( $tax_args );
			$artists        = array();

			if ( ! empty( $queried_artist->get_terms() ) ) {
				foreach ( $queried_artist->get_terms() as $artist ) {
					$artists[] = $artist->term_id;
				}
			}

			if ( ! empty( $s_artist ) ) {
				$tax_query[] = array(
					'taxonomy' => 'wp-manga-artist',
					'field'    => 'term_id',
					'terms'    => $artists
				);
			}
		}

		if ( ! empty( $s_release ) ) {
			$tax_args = array(
				'taxonomy' => 'wp-manga-release',
				'search'   => $s_release,
			);

			$queried_release = new WP_Term_Query( $tax_args );
			$releases        = array();

			if ( ! empty( $queried_release->get_terms() ) ) {
				foreach ( $queried_release->get_terms() as $release ) {
					$releases[] = $release->term_id;
				}
			}

			if ( ! empty( $s_release ) ) {
				$tax_query[] = array(
					'taxonomy' => 'wp-manga-release',
					'field'    => 'term_id',
					'terms'    => $releases
				);
			}
		}

		// exclude args
		$exclude_tags = Madara::getOption('manga_search_exclude_tags', '');
		$exclude_genres = Madara::getOption('manga_search_exclude_genres', '');
		$exclude_authors = Madara::getOption('manga_search_exclude_authors', '');
		
		if($exclude_tags != '' || $exclude_genres != '' || $exclude_authors != ''){
			$exclude_args = array();
			
			if($exclude_tags != ''){
				$exclude_args[] = array(
							'taxonomy' => 'wp-manga-tag',
							'field'    => 'slug',
							'terms'    => explode(',', $exclude_tags),
							'operator' => 'NOT IN'
						);
			}
			
			if($exclude_genres != ''){
				$tax_args = array(
					'taxonomy' => 'wp-manga-genre',
					'slug'     => $exclude_genres
				);

				$queried_genre = new WP_Term_Query( $tax_args );
				$genres        = array();

				if ( ! empty( $queried_genre->get_terms() ) ) {
					foreach ( $queried_genre->get_terms() as $genre ) {
						$genres[] = $genre->term_id;
					}
				}
				
				$exclude_args[] = array(
							'taxonomy' => 'wp-manga-genre',
							'field'    => 'term_id',
							'terms'    => $genres,
							'operator' => 'NOT IN'
						);
			}
			
			if($exclude_authors != ''){
				$tax_args = array(
					'taxonomy' => 'wp-manga-author',
					'search'   => $exclude_authors
				);

				$queried_author = new WP_Term_Query( $tax_args );
				$authors        = array();

				if ( ! empty( $queried_author->get_terms() ) ) {
					foreach ( $queried_author->get_terms() as $author_term ) {
						$authors[] = $author_term->term_id;
					}
				}
				
				$exclude_args[] = array(
							'taxonomy' => 'wp-manga-author',
							'field'    => 'term_id',
							'terms'    => $authors,
							'operator' => 'NOT IN'
						);
			}
			
			if ( ! empty( $tax_query ) ) {
				$s_args['tax_query'] = array_merge(array(
					'relation' => 'AND'
				), $exclude_args);
			} else {
				$s_args['tax_query'] = array_merge(array(
					'relation' => 'AND'
				), $exclude_args);
			}
		} else {
			if ( ! empty( $tax_query ) ) {
				$s_args['tax_query'] = array_merge(array(
					'relation' => 'OR'
				), $tax_query);
			}
		}
		
		return apply_filters('madara_search_args', $s_args);
	}

	/**
	 * Escape value in in property. Does not support HTML tags
	 */
	function esc_deep_object($obj){
		foreach($obj as $key => $value){
			$val = is_string($value) ? esc_html($value) : $value;
			$obj[$key] = $val;
		}

		return $obj;
	}

	function madara_get_default_background($schema = ''){
		if(!$schema){
			$schema = \App\Madara::getOption('body_schema', 'light');
		}

		return get_parent_theme_file_uri( '/images/bg-search' . ($schema == 'light' ? '' : '-dark') . '.jpg' );
	}

	function madara_get_default_heading_style(){
		return 'c-blog__heading style-' . \App\Madara::getOption('default_heading_style', 2);
	}