<?php

	/**
	 * Class ParseHead
	 *
	 * @since Madara Alpha 1.0
	 * @package madara
	 */

	namespace App\Views;

	use App\Madara;

	class ParseHead {
		public function __construct() {

		}

		/**
		 * Print page meta tags
		 */
		public static function meta_tags() {

			$meta_tags_html = '';

			$description = get_bloginfo( 'description' );
			$site_name = get_bloginfo( 'name' );
			$logo = \App\Madara::getOption( 'logo_image', get_template_directory_uri() . '/images/logo.png' );

			if ( is_single() ) {

				global $post;

				$post_format = get_post_format( $post->ID ) != '' && get_post_format( $post->ID ) == 'video' ? 'video.movie' : 'article';
				$url         = get_permalink( $post->ID );
				$title       = get_the_title( $post->ID );
				$post_title  = $title;

				$description = $post->post_excerpt;

				if ( $description == '' ) {
					$description = substr( strip_tags( $post->post_content ), 0, 165 );
				}

				$thumbnail_url = wp_get_attachment_url( get_post_thumbnail_id( $post->ID ) );
                $image_attributes = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), array(1200, 630) );
				$image = $image_attributes ? $image_attributes[0] : null;

				global $_wp_additional_image_sizes;

				$width  = 696;
				$height = 391;

				if ( isset( $_wp_additional_image_sizes['thumbnail'] ) ) {
					$width  = $_wp_additional_image_sizes['thumbnail']['width'];
					$height = $_wp_additional_image_sizes['thumbnail']['height'];
				}

				if( function_exists( 'madara_manga_meta_tags' ) ){
					// get manga meta tags
					$manga_meta_tags = madara_manga_meta_tags( $title, $description, $site_name );

					if( !empty( $manga_meta_tags['title'] ) ){
						$title = $manga_meta_tags['title'];
					}

					if( !empty( $manga_meta_tags['description'] ) ){
						$description = $manga_meta_tags['description'];
					}

					if( !empty( $manga_meta_tags['url'] ) ){
						$url = $manga_meta_tags['url'];
					}
				}

				?>
					<script type="application/ld+json">
						{
							"@context": "http://schema.org",
							"@type": "Article",
							"mainEntityOfPage": {
								"@type": "WebPage",
								"@id": "https://google.com/article"
							},
							"headline": "<?php echo esc_attr( get_the_title( $post->ID ) ); ?>",
							"image": {
								"@type": "ImageObject",
								"url": "<?php echo esc_attr( $thumbnail_url ); ?>",
								"height": <?php echo esc_attr( $height ); ?>,
								"width": <?php echo esc_attr( $width ); ?>
							},
							"datePublished": "<?php echo esc_js( $post->post_date_gmt ); ?>",
							"dateModified": "<?php echo esc_js( $post->post_modified_gmt ); ?>",
							"author": {
								"@type": "Person",
								"name": "<?php
								
								$meta_type = Madara::getOption('manga_single_meta_author', 'wp_author');
								
								if($meta_type == 'wp_author'){
									$author = get_user_by( 'id', $post->post_author );
									if ( $author ) {
										echo esc_attr( $author->display_name );
									};
								} else {
									global $wp_manga_functions;
									
									if(isset($wp_manga_functions)){
										$mangaAuthors = $wp_manga_functions->get_manga_authors($post->ID);
										echo esc_attr(strip_tags($mangaAuthors));
									}
								}
								
								?>"
							},
							"publisher": {
								"@type": "Organization",
								"name": "<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>",
								"logo": {
									"@type": "ImageObject",
									"url": "<?php if ( $logo != '' ) {
										echo esc_attr( $logo );
									} else {
										echo esc_attr( $thumbnail_url );
									} ?>"
								}
							},
							"description": "<?php echo esc_attr( strip_shortcodes( $description ) ); ?>"
						}
					</script>
				<?php

				$meta_tags_html .= '<meta property="og:type" content="' . esc_attr( $post_format ) . '"/>' . PHP_EOL;

			} elseif( is_tax() ){
				$term = get_queried_object();

				if( isset( $term->term_id ) ){
					$description = term_description( $term->term_id, $term->taxonomy );
					$description = substr( strip_tags( $description ), 0, 165 );
				}
			}

			if( empty( $image ) ){
				$image = $logo;
			}

			if( empty( $title ) ){
				$title = $site_name;
			}

			$meta_tags_html .= '<meta property="og:image" content="' . esc_attr( apply_filters( 'madara_meta_image', $image ) ) . '"/>' . PHP_EOL;
			$meta_tags_html .= '<meta property="og:site_name" content="' . esc_attr( $site_name ) . '"/>' . PHP_EOL;
			$meta_tags_html .= '<meta property="fb:app_id" content="' . \App\Madara::getOption( 'facebook_app_id' ) . '" />' . PHP_EOL;
			$meta_tags_html .= '<meta property="og:title" content="' . esc_attr( apply_filters( 'madara_meta_title', $title ) ) . '"/>' . PHP_EOL;

			if( !empty( $url ) ){
				$meta_tags_html .= '<meta property="og:url" content="' . esc_url( $url ) . '"/>' . PHP_EOL;
			}

			if( !empty( $description ) ){
				$meta_tags_html .= '<meta property="og:description" content="' . esc_attr( strip_shortcodes( $description ) ) . '"/>' . PHP_EOL;
			}

			if( !empty( $keywords ) ){
				$meta_tags_html .= '<meta name="keywords" content="' . $keywords . '"/>' . PHP_EOL;
			}

			// Meta for twitter
			$meta_tags_html .= '<meta name="twitter:card" content="summary" />' . PHP_EOL;
			$meta_tags_html .= '<meta name="twitter:site" content="@' . esc_attr( $site_name ) . '" />' . PHP_EOL;
			$meta_tags_html .= '<meta name="twitter:title" content="' . esc_attr( $title ) . '" />' . PHP_EOL;

			if( !empty( $description ) ){
				$meta_tags_html .= '<meta name="twitter:description" content="' . esc_attr( strip_shortcodes( $description ) ) . '" />' . PHP_EOL;
			}

			if( !empty( $url ) ){
				$meta_tags_html .= '<meta name="twitter:url" content="' . esc_url( $url ) . '" />' . PHP_EOL;
			}

			$meta_tags_html .= '<meta name="twitter:image" content="' . esc_attr( $image ) . '" />' . PHP_EOL;
			
			$meta_tags_html .= '<meta name="description" content="' . esc_attr( strip_shortcodes( $description ) ) . '" />';

			$meta_tags_html .= '<meta name="generator" content="' . esc_attr( esc_html__( 'Powered by Madara - A powerful manga, novel theme from Mangabooth.com', 'madara' ) ) . '" />' . PHP_EOL;

			echo apply_filters( 'madara-meta-tags', $meta_tags_html );

			do_action( 'madara-meta-tags' );
		}
	}
