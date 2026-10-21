<?php

	/**
	 * hooks to change template HTML
	 * @package madara
	 *
	 */


	use App\Madara;
	use App\Views\MadaraView;


	add_action( 'madara_before_body', 'madara_output_before_body', 10 );
	function madara_output_before_body() {
		// print out pre-loading effect

		if ( Madara::getOption( 'pre_loading', - 1 ) == 1 || ( Madara::getOption( 'pre_loading', - 1 ) == 2 && ( is_front_page() ) ) ) {
			$ajax_loading_template = \App\Madara::getOption( 'ajax_loading_effect', 'ball-grid-pulse' );

			$madara_logo = \App\Madara::getOption( 'pre_loading_logo', '' );
			if ( $madara_logo == '' ) {
				$madara_logo = madara_get_logo( false, true );
			} else {
				$madara_logo = '<a class="logo" href="' . esc_url( home_url( '/' ) ) . '" title="' . esc_attr( get_bloginfo( 'name' ) ) . '">
			    <img class="for-original" src="' . esc_url( $madara_logo ) . '" alt="' . esc_attr( get_bloginfo( 'name' ) ) . '"/></a>';
			}

			$html = '<div id="pageloader" class="spinners">
			<div class="spinner"><div class="c-pre-loading-logo">' . $madara_logo . '</div>';

			ob_start();
			get_template_part( 'html/ajax-loading/' . $ajax_loading_template );
			$html .= ob_get_contents();
			ob_end_clean();

			$html .= '
            </div>
        </div>';

			$html = apply_filters( 'madara_pre_loading_html', $html );

			echo wp_kses_post( $html );
		}
	}

	add_action( 'before_page_content', 'madara_blog_breadcrumbs', 100 );
	function madara_blog_breadcrumbs() {
		if ( is_post_type_archive( 'post' ) || is_single() ) {
			if ( Madara::getOption( 'archive_breadcrumbs', 'on' ) == 'on' ) {
				?>
                <div id="c-blog_breadcrumbs">
					<?php MadaraView::renderBreadcrumbs(); ?>
                </div>
			<?php }
		}
	}


	add_filter( 'excerpt_length', 'madara_custom_excerpt_length', 999 );
	function madara_custom_excerpt_length( $length ) {
		return Madara::getOption( 'custom_excerpt_length', $length );
	}

	/**
	 * Filter the excerpt "read more" string.
	 *
	 * @param string $more "Read more" excerpt string.
	 *
	 * @return string (Maybe) modified "read more" excerpt string.
	 */
	function madara_excerpt_more( $more ) {
		$html = '...';
		$html .= '<div class="c-read-more"><a class="c-read-more-link" href="' . get_the_permalink( get_the_ID() ) . '">' . esc_html__( 'Continue Reading', 'madara' ) . '</a> ' . esc_html( '&rarr;' ) . '</div>';

		return apply_filters( 'madara_excerpt_more_content', $html );
	}

	add_filter( 'excerpt_more', 'madara_excerpt_more' );

	add_filter( 'madara_dashboard_heading', 'madara_welcome_text' );
	function madara_welcome_text( $text ) {
		return esc_html__( 'Madara Dashboard', 'madara' );
	}

	add_filter( 'madara_theme_document_url', 'madara_online_document' );
	function madara_online_document( $url ) {
		return 'https://live.mangabooth.com/doc';
	}

	add_filter( 'madara_theme_support_url', 'madara_online_support' );
	function madara_online_support( $url ) {
		return 'https://mangabooth.ticksy.com';
	}

	add_action( 'wp_footer', 'madara_go_to_top' );
	function madara_go_to_top() {
		$is_gototop = Madara::getOption( 'go_to_top', 'off' );

		if ( $is_gototop != 'off' ) {
			?>
            <div class="go-to-top active">
                <i class="icon ion-md-arrow-up"></i>
            </div>
			<?php
		}
	}

	add_filter( 'wp_manga_sidebar_before_widget', 'madara_sidebar_filter_before_widget', 999 );
	function madara_sidebar_filter_before_widget() {
		$before_widget = '<div class="row"><div id="%1$s" class="widget %2$s"><div class="widget__inner %2$s__inner c-widget-wrap">';

		return $before_widget;
	}

	add_filter( 'wp_manga_sidebar_after_widget', 'madara_sidebar_filter_after_widget', 999 );
	function madara_sidebar_filter_after_widget() {
		$after_widget = '</div></div></div>';

		return $after_widget;
	}

	add_filter( 'wp_manga_sidebar_before_title', 'madara_sidebar_before_title', 999 );
	function madara_sidebar_before_title() {
		$before_title = '<div class="widget-title"><h4 class="heading">';

		return $before_title;
	}

	add_filter( 'wp_manga_sidebar_after_title', 'madara_sidebar_after_title', 999 );
	function madara_sidebar_after_title() {
		$after_title = '</h4></div>';

		return $after_title;
	}

	if ( class_exists( 'WP_MANGA' ) ) {

		/*
		 * check plugin wp-manga active or not.
		 * */

		add_action( 'madara_add_manga_sidebar', 'madara_add_manga_sidebar' );

		function madara_add_manga_sidebar() {

			register_sidebar( array(
				'name'          => esc_html__( 'WP Manga - Main Top Sidebar', 'madara' ),
				'id'            => 'manga_main_top_sidebar',
				'description'   => esc_html__( 'Appear before main content in Manga Pages', 'madara' ),
				'before_widget' => '<div id="%1$s" class="widget %2$s"><div class="widget__inner %2$s__inner c-widget-wrap">',
				'after_widget'  => '</div></div>',
				'before_title'  => '<div class="widget-title"><div class="c-blog__heading style-2 font-heading"><h4>',
				'after_title'   => '</h4></div></div>',
			) );

			register_sidebar( array(
				'name'          => esc_html__( 'WP Manga - Main Top Second Sidebar', 'madara' ),
				'id'            => 'manga_main_top_second_sidebar',
				'description'   => esc_html__( 'Appear before main content in Manga Pages', 'madara' ),
				'before_widget' => '<div id="%1$s" class="widget %2$s"><div class="widget__inner %2$s__inner c-widget-wrap">',
				'after_widget'  => '</div></div>',
				'before_title'  => '<div class="widget-title"><div class="c-blog__heading style-2 font-heading"><h4>',
				'after_title'   => '</h4></div></div>',
			) );

			register_sidebar( array(
				'name'          => esc_html__( 'WP Manga - Main Bottom Sidebar', 'madara' ),
				'id'            => 'manga_main_bottom_sidebar',
				'description'   => esc_html__( 'Appear after main content in Manga Pages', 'madara' ),
				'before_widget' => '<div id="%1$s" class="widget %2$s"><div class="widget__inner %2$s__inner c-widget-wrap">',
				'after_widget'  => '</div></div>',
				'before_title'  => '<div class="widget-title"><div class="c-blog__heading style-2 font-heading"><h4>',
				'after_title'   => '</h4></div></div>',
			) );

		}

	}

	/**
	 * Get background option in "style=''" format
	 *
	 * @param $option string - Option name
	 * @param $echo - Echo the value or not
	 * @param $allow_singular_override - If the option is called from a singular page of which there are same options, we allow the options of that page to override global settings
	 *
	 * @return mixed - Value of option
	 */
	function madara_output_background_options( $option = '', $echo = 0, $allow_singular_override = true ) {

		$bg_style = '';

		if ( $option != '' ) {

			$bg_options = Madara::getOption( $option, null, $allow_singular_override );
			
			if ( is_array( $bg_options ) ) {
				$bg_options_repeat     = isset( $bg_options['background-repeat'] ) ? $bg_options['background-repeat'] : '';
				$bg_options_attachment = isset( $bg_options['background-attachment'] ) ? $bg_options['background-attachment'] : '';
				$bg_options_position   = isset( $bg_options['background-position'] ) ? $bg_options['background-position'] : '';
				$bg_options_size       = isset( $bg_options['background-size'] ) ? $bg_options['background-size'] : '';
				$bg_options_image      = isset( $bg_options['background-image'] ) ? $bg_options['background-image'] : '';
				$bg_options_color      = isset( $bg_options['background-color'] ) ? $bg_options['background-color'] : '';

				if ( ! empty( $bg_options ) ) {

					if ( $bg_options_color != '' ) {
						$bg_style .= 'background-color:' . $bg_options_color . '; ';
					}

					if ( $bg_options_image != '' ) {
						$bg_style .= 'background-image:url(' . esc_url( $bg_options_image ) . '); ';

						if ( $bg_options_repeat != '' ) {
							$bg_style .= 'background-repeat:' . $bg_options_repeat . '; ';
						}

						if ( $bg_options_attachment != '' ) {
							$bg_style .= 'background-attachment:' . $bg_options_attachment . '; ';
						}

						if ( $bg_options_size != '' ) {
							$bg_style .= 'background-size:' . $bg_options_size . '; ';
						}

						if ( $bg_options_position != '' ) {
							$bg_style .= 'background-position:' . $bg_options_position . '; ';
						}
					}
				}
			}

		}

		if ( $echo == 1 ) {
			echo esc_html( $bg_style );
		} else {
			return $bg_style;
		}
	}

	function madara_output_sidebar_container_classes( $option = '', $default_value = '', $echo = 0, $allow_singular_override = true ) {
		$container_class = '';

		if ( $option != '' ) {

			$option = Madara::getOption( $option, $default_value, $allow_singular_override );

			if ( $option == 'container' ) {
				$container_class = 'container c-container';
			} else if ( $option == 'full_width' ) {
				$container_class = 'container-fluid c-container-fluid';
			} else {
				$container_class = 'container custom-width c-container';
			}
		}

		if ( $echo == 1 ) {
			echo esc_attr( $container_class );
		} else {
			return $container_class;
		}

	}

	function madara_output_spacing_options( $option = '', $default_value = '', $echo = 0, $allow_singular_override = true ) {

		$spacing = '';

		if ( $option != '' ) {
			$value = Madara::getOption( $option, $default_value, $allow_singular_override );
			
			if ( is_array( $value ) ) {

				$unit   = isset( $value['unit'] ) ? $value['unit'] : 'px';
				$top    = isset( $value['top'] ) ? $value['top'] . $unit . ' ' : '';
				$right  = isset( $value['right'] ) ? $value['right'] . $unit . ' ' : '';
				$bottom = isset( $value['bottom'] ) ? $value['bottom'] . $unit . ' ' : '';
				$left   = isset( $value['left'] ) ? $value['left'] . $unit . ' ' : '';

				if ( $top != '' || $right != '' || $bottom != '' || $left != '' ) {
					$spacing .= ' padding:' . $top . $right . $bottom . $left . '; ';
				}
			} else {
				$spacing = $value ? ' padding:' . $value : '';
			}
		}

		if ( $echo == 1 ) {
			echo esc_html( $spacing );
		} else {
			return $spacing;
		}
	}

	add_filter( 'upload_mimes', 'madara_add_custom_upload_mimes' );
	function madara_add_custom_upload_mimes( $existing_mimes ) {

		$existing_mimes['otf']  = 'application/x-font-otf';
		$existing_mimes['woff'] = 'application/x-font-woff';
		$existing_mimes['ttf']  = 'application/x-font-ttf';
		$existing_mimes['svg']  = 'image/svg+xml';
		$existing_mimes['eot']  = 'application/vnd.ms-fontobject';

		return $existing_mimes;
	}

	add_action( 'wp_footer', 'manga_adult_content' );
	function manga_adult_content() {
		global $wp_manga_setting;
			
		if(!isset($wp_manga_setting)){
			return;
		}
		
		if ( is_manga_single() ||  is_manga_reading_page() ) {
			$manga_adult_content = get_post_meta( get_the_ID(), 'manga_adult_content', true );
			$message_html        = '<p class="manga-adult-title">' . get_the_title() . '</p>';
			$message_html        .= '<p class="adult-message">' . esc_html__( 'contains themes or scenes that may not be suitable for very young readers thus is blocked for their protection.', 'madara' ) . '</p>';
			$message_html        .= '<p class="adult-confirm">' . esc_html__( 'Are you over 18?', 'madara' ) . '</p>';

			$adult_content = apply_filters( 'madara_adult_content_message', $message_html );
			$direct_url    = apply_filters( 'madara_adult_popup_direct_url', home_url( '/' ) );

			if ( ! empty( $manga_adult_content ) && $manga_adult_content[0] == 'yes' ) { ?>

                <!-- Modal -->
                <div id="adult_modal" class="modal fade custom-modal" role="dialog" data-site-url="<?php echo esc_url( $direct_url ); ?>">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title"><?php esc_html_e( 'Caution to under-aged viewers', 'madara' ) ?></h5>
                            </div>
                            <div class="modal-body">
								<?php echo wp_kses_post( $adult_content ); ?>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary btn-not-adult"><?php esc_html_e( 'No', 'madara' ) ?></button>
                                <button type="button" class="btn btn-primary btn-adult-confirm" data-dismiss="modal"><?php esc_html_e( 'Yes, I am', 'madara' ); ?></button>
                            </div>
                        </div>
                    </div>
                </div>
			<?php }
		}
	}

	add_action('wp_footer', 'madara_darklight_toggle');
	function madara_darklight_toggle(){
		$toggle_enable = Madara::getOption( 'body_schema_toggle', 'off' );
		if($toggle_enable == 'on'){
			$schema_cookie = isset($_COOKIE['wpmanga-body-contrast']) ? $_COOKIE['wpmanga-body-contrast'] : '';
			if(!$schema_cookie){
				// get default value
				$schema_cookie = Madara::getOption( 'body_schema', 'light' );
			}
			
			?>
			<div id="darklight_toggle">
				<div class="toggle-switch">
					<label>
						<input type = 'checkbox' <?php echo ($schema_cookie == 'dark' ? 'checked="checked"' : '');?>>
						<span class = 'slider'></span>
					</label>
				</div>
			</div>
			<?php	
		}
	}
