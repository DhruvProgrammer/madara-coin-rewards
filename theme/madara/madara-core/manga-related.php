<?php
    /**
	 * The Template for Manga Related section in Manga Detail page
	 *
	 * This template can be overridden by copying it to your-child-theme/madara-core/manga-related.php
	 *
	 * HOWEVER, on occasion Madara will need to update template files and you
	 * (the theme developer) will need to copy the new files to your theme to
	 * maintain compatibility. We try to do this as little as possible, but it does
	 * happen. When this occurs the version of the template file will be bumped and
	 * we will list any important changes on our theme release logs.
	 * @package Madara
	 * @version 1.7.2.2
	 */
	 
	 use App\Madara;

	$wp_manga_settings = get_option( 'wp_manga_settings' );
	$related_by        = $wp_manga_settings['related_by'];

    $manga_related_layout = Madara::getOption('manga_single_related_items_layout', 1);
    
    $mobile_col = (Madara::getOption('manga_single_related_item_mobile_width', '100') == 50 ? '6' : '12');
    
	$thumb_size        = array( 75, 106 ); // default
    if( $manga_related_layout == 2 ){
        $thumb_size         = 'madara_manga_big_thumb'; // big thumbnail
		if($mobile_col == 12){
			$thumb_size = 'madara_manga_big_thumb_full';
		}
    }
    
    $related_items_count = Madara::getOption('manga_single_related_items_count', 4);
	
	if ( $related_by ) {
		$post_id = get_the_ID();

		$related_args = array(
			'post_type'      => 'wp-manga',
			'post_status'    => 'publish',
			'orderby' => 'rand',
			'posts_per_page' => $related_items_count,
			'post__not_in'   => array( $post_id ),
		);

		switch ( $related_by ) {
			case 'related_author':
				$author_terms = wp_get_post_terms( $post_id, 'wp-manga-author' );

				$manga_author = array();

				if ( ! empty( $author_terms ) ) {
					foreach ( $author_terms as $author ) {
						$manga_author[] = $author->term_id;
					}
				}

				$related_args['tax_query'] = array(
					array(
						'taxonomy' => 'wp-manga-author',
						'field'    => 'term_id',
						'terms'    => $manga_author
					)
				);
				break;
			case 'related_year':
				$year_terms = wp_get_post_terms( $post_id, 'wp-manga-release' );

				$manga_year = array();

				if ( ! empty( $year_terms ) ) {
					foreach ( $year_terms as $year ) {
						$manga_year[] = $year->term_id;
					}
				}

				$related_args['tax_query'] = array(
					array(
						'taxonomy' => 'wp-manga-release',
						'field'    => 'term_id',
						'terms'    => $manga_year
					)
				);
				break;
			case 'related_artist' :
				$artists_terms = wp_get_post_terms( $post_id, 'wp-manga-artist' );

				$manga_artists = array();

				if ( ! empty( $artists_terms ) ) {
					foreach ( $artists_terms as $artists ) {
						$manga_artists[] = $artists->term_id;
					}
				}

				$related_args['tax_query'] = array(
					array(
						'taxonomy' => 'wp-manga-artist',
						'field'    => 'term_id',
						'terms'    => $manga_artists
					)
				);
				break;
			case 'related_genre' :

				$genre_terms = wp_get_post_terms( $post_id, 'wp-manga-genre' );
				
				$genre_id = array();

				if ( ! empty( $genre_terms ) ) {
					foreach ( $genre_terms as $term ) {
						$genre_id[] = $term->term_id;
					}
				}

				$related_args['tax_query'] = array(
					array(
						'taxonomy' => 'wp-manga-genre',
						'field'    => 'term_id',
						'terms'    => $genre_id
					)
				);
				break;
		}

		$related_query = new WP_Query( $related_args );
		
		if ( $related_query->have_posts() ) {
			?>

            <div class="row c-row related-manga">
                <div class="col-12 col-sm-12 col-md-12 col-lg-12">
                    <div class="<?php echo madara_get_default_heading_style();?> font-heading">
                        <h4 class="h4">
                            <i class="<?php madara_default_heading_icon(); ?>"></i>
							<?php esc_html_e( 'YOU MAY ALSO LIKE', 'madara' ) ?></h4>
                    </div>
                </div>


				<?php
                    $col_width = 12 / $related_items_count;

					$show_datetime = Madara::getOption('manga_single_related_item_show_datetime', 'on');
                    
					while ( $related_query->have_posts() ) {

						$related_query->the_post();

						$date = $show_datetime == 'on' ? get_post_meta( get_the_ID(), '_latest_update', true ) : '';

						?>

                        <div class="col-<?php echo esc_attr($mobile_col);?> col-sm-6 col-md-<?php echo esc_attr($col_width);?>">
                            <div class="related-reading-wrap related-style-<?php echo esc_attr($manga_related_layout);?>">
                                <div class="related-reading-img widget-thumbnail c-image-hover">
                                    <a title="<?php echo esc_attr(get_the_title()); ?>" href="<?php echo get_the_permalink(); ?>">
										<?php
											if ( has_post_thumbnail( get_the_ID() ) ) {
												echo madara_thumbnail( $thumb_size );
											}
										?>
                                    </a>
                                </div>
                                <div class="related-reading-content">
                                    <h5 class="widget-title">
                                        <a href="<?php echo esc_url(get_the_permalink()); ?>" title="<?php echo get_the_title(); ?>">
											<?php echo get_the_title(); ?>
                                        </a>
                                    </h5>
                                </div>
								<?php if ( $date && '' != $date ) { ?>
                                    <div class="post-on font-meta">
                                        <span>
                                            <?php echo date_i18n( get_option( 'date_format' ), $date ); ?>
                                        </span>
                                    </div>
								<?php } ?>
                            </div>
                        </div>
						<?php
					}

				?>
            </div>

			<?php
		}

		wp_reset_postdata();
	}
