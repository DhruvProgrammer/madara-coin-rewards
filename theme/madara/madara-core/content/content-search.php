<?php
	/**
	 * The Template for Manga Item layout, in a loop in Manga Search Results page
	 *
	 * This template can be overridden by copying it to your-child-theme/madara-core/content/content-search.php
	 *
	 * HOWEVER, on occasion Madara will need to update template files and you
	 * (the theme developer) will need to copy the new files to your theme to
	 * maintain compatibility. We try to do this as little as possible, but it does
	 * happen. When this occurs the version of the template file will be bumped and
	 * we will list any important changes on our theme release logs.
	 * @package Madara
	 * @version 1.7.3.9
	 */

	global $wp_query;
	$post_id = get_the_ID();
	$wp_manga           = madara_get_global_wp_manga();
	$wp_manga_functions = madara_get_global_wp_manga_functions();
	$wp_manga_settings  = madara_get_global_wp_manga_setting();

	$thumb_size = array( 193, 278 );

	$manga_reading_style = $wp_manga_functions->get_reading_style();
	$manga_alternative   = get_post_meta( $post_id, '_wp_manga_alternative', true );
	
	$manga_author        = get_the_terms( $post_id, 'wp-manga-author' );
	$manga_artist        = get_the_terms( $post_id, 'wp-manga-artist' );
	$manga_genre         = get_the_terms( $post_id, 'wp-manga-genre' );
	$manga_status        = get_post_meta( $post_id, '_wp_manga_status', true );
	$manga_release       = get_the_terms( $post_id, 'wp-manga-release' );
	
	$has_release = ! is_wp_error( $manga_release ) && ! empty( $manga_release );
	$has_status = ! is_wp_error( $manga_status ) && ! empty( $manga_status );
	$has_one_of_two = ($has_release || $has_status) && !($has_release && $has_status);
	
	$class_flag = '';
	if($has_one_of_two) $class_flag = 'nofloat';
?>
<div class="row c-tabs-item__content">
    <div class="col-4 col-md-2">
        <div class="tab-thumb c-image-hover">
			<?php
				if ( has_post_thumbnail() ) {
					?>
                    <a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>">
						<?php echo madara_thumbnail( $thumb_size ); ?>
                    </a>
					<?php
				}
			?>
        </div>
    </div>
    <div class="col-8 col-md-10">
        <div class="tab-summary">
            <div class="post-title">
                <h3 class="h4"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
            </div>
            <div class="post-content">
				<?php

					if ( ! empty( $manga_alternative ) ) {
						?>

                        <div class="post-content_item mg_alternative <?php echo esc_attr($class_flag);?>">
                            <div class="summary-heading">
                                <h5>
									<?php esc_html_e( 'Alternative', 'madara' ); ?>
                                </h5>
                            </div>
                            <div class="summary-content">
								<?php echo esc_html( $manga_alternative ); ?>
                            </div>
                        </div>

						<?php
					}
				?>
				<?php
					//var_dump( $manga_author );
					if ( ! is_wp_error( $manga_author ) && ! empty( $manga_author ) ) {
						?>
                        <div class="post-content_item mg_author <?php echo esc_attr($class_flag);?>">
                            <div class="summary-heading">
                                <h5>
									<?php esc_html_e( 'Authors', 'madara' ); ?>
                                </h5>
                            </div>
                            <div class="summary-content">
								<?php
									$authors = array();
									foreach ( $manga_author as $author ) {
										$authors[] = '<a href="' . get_term_link( $author ) . '">' . esc_html( $author->name ) . '</a>';
									}

									echo implode( ', ', $authors );
								?>
                            </div>
                        </div>
						<?php
					}

				?>

				<?php
					if ( ! is_wp_error( $manga_artist ) && ! empty( $manga_artist ) ) {
						?>
                        <div class="post-content_item mg_artists <?php echo esc_attr($class_flag);?>">
                            <div class="summary-heading">
                                <h5>
									<?php esc_html_e( 'Artists', 'madara' ); ?>
                                </h5>
                            </div>

                            <div class="summary-content">
								<?php
									$artists = array();
									foreach ( $manga_artist as $artist ) {
										$artists[] = '<a href="' . get_term_link( $artist ) . '">' . esc_html( $artist->name ) . '</a>';
									}

									echo implode( ', ', $artists );
								?>
                            </div>
                        </div>
						<?php
					}
				?>
				<?php
					if ( ! is_wp_error( $manga_genre ) && ! empty( $manga_genre ) ) {
						?>
                        <div class="post-content_item mg_genres <?php echo esc_attr($class_flag);?>">
                            <div class="summary-heading">
                                <h5>
									<?php esc_html_e( 'Genres', 'madara' ); ?>
                                </h5>
                            </div>
                            <div class="summary-content">
								<?php
									$genres = array();
									foreach ( $manga_genre as $genre ) {
										$genres[] = '<a href="' . get_term_link( $genre ) . '">' . esc_html( $genre->name ) . '</a>';
									}

									echo implode( ', ', $genres );
								?>
                            </div>
                        </div>
						<?php
					}
				?>
				<?php
					if ( ! empty( $manga_status ) ) {
						?>
                        <div class="post-content_item mg_status <?php echo esc_attr($class_flag);?>">
                            <div class="summary-heading">
                                <h5>
									<?php esc_html_e( 'Status', 'madara' ); ?>
                                </h5>
                            </div>
                            <div class="summary-content">
								<?php
									$status = $wp_manga_functions->get_manga_status( get_the_ID() );
									echo esc_html( $status ); ?>
                            </div>
                        </div>
						<?php
					}
				?>
				<?php
					if ( ! is_wp_error( $manga_release ) && ! empty( $manga_release ) ) {
						?>
                        <div class="post-content_item mg_release <?php echo esc_attr($class_flag);?>">
                            <div class="summary-heading">
                                <h5>
									<?php esc_html_e( 'Release', 'madara' ); ?>
                                </h5>
                            </div>
                            <div class="summary-content release-year">
								<?php
									$releases = array();
									foreach ( $manga_release as $release ) {
										$releases[] = '<a href="' . get_term_link( $release ) . '">' . esc_html( $release->name ) . '</a>';
									}

									echo implode( ', ', $releases );
								?>
                            </div>
                        </div>
						<?php
					}
				?>
				<?php do_action('madara_content_search_item_info', $post_id);?>
            </div>
        </div>
        <div class="tab-meta">
			<?php
				//Get latest chapter
				global $wp_manga_database;
				global $sort_setting;
				
				if(!isset($sort_setting)){
					$sort_setting = $wp_manga_database->get_sort_setting();
				}

				$sort_by    = $sort_setting['sortBy'];
				$sort_order = $sort_setting['sort'];
				
				$chapter = $wp_manga_functions->get_latest_chapters( get_the_ID(), null, 2, 0, $sort_by, $sort_order );
				if ( ! empty( $chapter ) ) {
					$latest_chapter     = $chapter[0];
					$latest_chapter_url = $wp_manga_functions->build_chapter_url( get_the_ID(), $latest_chapter['chapter_slug'] );
					?>
                    <div class="meta-item latest-chap">
						<?php if ( isset( $latest_chapter['chapter_name'] ) ) { ?>
                            <span class="font-meta"><?php echo esc_html__( 'Latest chapter', 'madara' ); ?> </span>
                            <span class="font-meta chapter"><a href="<?php echo esc_url( $latest_chapter_url ); ?>"><?php echo wp_kses_post( $latest_chapter['chapter_name'] ); ?></a></span>
						<?php } ?>
                    </div>
					<?php
					$update_time = ! empty( $latest_chapter['date'] ) ? $latest_chapter['date'] : '';

					$update_time = apply_filters( 'madara_archive_chapter_date', $update_time, $latest_chapter['chapter_id'], $latest_chapter['date'], $latest_chapter_url );

					if ( ! empty( $update_time ) ) {
						?>
                        <div class="meta-item post-on">
                            <span class="font-meta"><?php echo wp_kses_post( $update_time ); ?></span>
                        </div>
						<?php
					}
					?>
                    <div class="meta-item rating">
						<?php
							$wp_manga_functions->manga_rating_display( get_the_ID() );
						?>
                    </div>
					<?php
				}
			?>
        </div>
    </div>
</div>
