<?php
	/**
	 * Template Name: Front-Page
	 *
	 * @package madara
	 */

	use App\Madara;

	$page_content = Madara::getOption( 'page_content' );

	if ( $page_content == 'page_content' ) {
		get_template_part( 'page' );
		exit;
	}

	if ( ! class_exists( 'WP_MANGA' ) && $page_content == 'manga' ) {
		get_template_part( 'page' );
		exit;
	}
    
    $page_id = get_the_ID();

	$madara_archive_heading_text = Madara::getOption( 'archive_heading_text', '' );
	$madara_archive_heading_icon = Madara::getOption( 'archive_heading_icon', '' );
	$archive_content_columns     = Madara::getOption( 'archive_content_columns', 3 );
	$archive_margin_top          = get_post_meta( $page_id, 'archive_margin_top', true );
	$archive_margin_top          = isset( $archive_margin_top ) && $archive_margin_top != '' ? $archive_margin_top : '';
	$page_custom_css = Madara::getOption('page_custom_css', '');
	
	$page_custom_css = apply_filters('madara_page_custom_css_class', $page_custom_css);
	
	$madara_sidebar              = madara_get_theme_sidebar_setting();

	$nav_type                    = \App\Madara::getOption( 'archive_navigation', 'default' );

	$template = 'html/loop/content';
	
	$manga_type = '';
	
	if ( $page_content == 'manga' ) {
		$post_type = 'wp-manga';
		$manga_type = get_post_meta($page_id, 'manga_type', true);
		$class_1   = 'c-page';
		$class_2   = 'c-page__content manga_content';
		$template  = 'madara-core/content/content-archive';
	} else {
		$post_type = 'post';
		$class_1   = '';
		$class_2   = '';
	}

	if ( ! empty( get_query_var( 'paged' ) ) ) {
		$paged = get_query_var( 'paged' );
	} elseif ( ! empty( get_query_var( 'page' ) ) ) {
		$paged = get_query_var( 'page' );
	} else {
		$paged = 1;
	}
	
	if ( isset($_GET['start']) && $_GET['start'] != '' ) {
		$start = $_GET['start'];
		
		set_query_var( 'starts_with', $start == '0' ? '-0' : $start);
		
		add_filter('madara_ajax_query_arguments', function($args){
					$args['starts_with'] = ($_GET['start'] == '0' ? '-0' : $_GET['start']);
					
					return $args;
		});
	}
	
	$orderby = Madara::getOption( 'page_post_orderby' );
	if($orderby == 'name'){
		$show_alphabeta_filters = Madara::getOption( 'manga_filter_by_characters', 'on' ) == 'on' ? true : false;
	} else {
		$show_alphabeta_filters = false;
	}

	if ( $page_content == 'manga' ) {
        $timerange = '';

		$show_filter_bar = Madara::getOption('filter_bar', 'no');
        if($show_filter_bar == 'yes'){
			$orderby = isset( $_GET['m_orderby'] ) ? $_GET['m_orderby'] : $orderby;
		}

		$orderby2 = $orderby;

		switch($orderby){
			case 'name':
				$orderby2 = 'alphabet';
				break;
			case 'modified':
				$orderby2 = 'latest';
				break;
			case 'latest':
				$orderby2 = 'new-manga';
				break;
			case 'rand':
				$orderby2 = 'random';
				break;
            case 'trending':
                $timerange = Madara::getOption('frontpage_orderby_trending_timerange', 'week' );
                break;
			default:
				$orderby2 = $orderby;
				break;
		}

		$args = array(
			'orderby' => $orderby2, 
			'paged' => $paged, 
			'timerange' => $timerange
		);
		
		$meta_query = array('relation' => 'AND');
		if($manga_type != ''){
			$meta_query[] = array(
				'key' => '_wp_manga_chapter_type',
				'value' => $manga_type
			);
		}
		
		$posts_per_page = Madara::getOption('page_post_count');
		if($posts_per_page) $args['posts_per_page'] = $posts_per_page;
		
		$args['tax_query'] = array();
		$args['tax_query']['relation'] = 'OR';
		
		$manga_tags = get_post_meta($page_id, 'manga_tags', true);
		if($manga_tags != ''){
			
			$manga_tags_array = explode( ',', $manga_tags );
			foreach ( $manga_tags_array as $g ) {
				$args['tax_query'][] = array(
					'taxonomy' => 'wp-manga-tag',
					'terms'    => sanitize_title(trim($g)),
					'field'    => 'slug',
				);
			}
		}
		
		$manga_genres = get_post_meta($page_id, 'manga_genres', true);
		if($manga_genres != ''){	
			$manga_genres_array = explode( ',', $manga_genres );
			foreach ( $manga_genres_array as $g ) {
				$args['tax_query'][] = array(
					'taxonomy' => 'wp-manga-genre',
					'terms'    => sanitize_title(trim($g)),
					'field'    => 'slug',
				);
			}
		}
		
		$manga_status = get_post_meta($page_id, 'manga_status', true);
		if($manga_status){
			$meta_query[] = array('key' => '_wp_manga_status',
									'value' => $manga_status
									);
		}
		
		$args['meta_query'] = $meta_query;
        
        $ignore_ids = Madara::getOption('ignore_ids');
        if($ignore_ids){
            $args['post__not_in'] = explode(",", $ignore_ids);
        }

		$ignore_genres = Madara::getOption('ignore_genres');
		if($ignore_genres){
			$args['tax_query']['relation'] = 'AND';
			$args['tax_query'][] = array(
				'taxonomy' => 'wp-manga-genre',
				'terms' => explode(",", $ignore_genres),
				'field' => 'slug',
				'operator' => 'NOT IN',
			);
		}

		$ignore_tags = Madara::getOption('ignore_tags');
		if($ignore_tags){
			$args['tax_query']['relation'] = 'AND';
			$args['tax_query'][] = array(
				'taxonomy' => 'wp-manga-tag',
				'terms' => explode(",", $ignore_tags),
				'field' => 'slug',
				'operator' => 'NOT IN',
			);
		}

		if ( is_home() || is_front_page() || is_manga_posttype_archive() ) {
			
			$manga_query = madara_manga_query( $args );
		} else {
			$manga_query = madara_manga_query( $wp_query->query_vars );
		}

		$madara_custom_query = madara_manga_query( $args );

		set_query_var( 'sidebar', $madara_sidebar );

		$set_query_var = array(
			'sidebar' => $madara_sidebar,
		);
	} else {
		$madara_custom_query = madara_get_front_page_query( $post_type, $paged, $manga_type );
		set_query_var( 'archive_content_columns', $archive_content_columns );
		$set_query_var = array(
			'archive_content_columns' => $archive_content_columns,
		);
	}

	$madara_custom_query->query = array_merge( $madara_custom_query->query, $set_query_var );

	$madara_post_count = $madara_custom_query->post_count;
	
	$manga_archives_item_layout = Madara::getOption( 'manga_archives_item_layout', 'default' );

	$bigthumbnail_layout2 = '';
	
	if($manga_archives_item_layout == 'big_thumbnail_2'){
		$manga_archives_item_layout = 'big_thumbnail';
		$bigthumbnail_layout2 = 'overlay';
	}
	$manga_archives_item_columns = Madara::getOption( 'manga_archives_item_columns', 1 );

	if($manga_archives_item_layout == 'simple'){
		$manga_archives_item_columns = 1;
	}

	$manga_page_layout = Madara::getOption( 'manga_page_layout', '' );

	$madara_sidebar_size = Madara::getOption( 'sidebar_size', 4 );
	$madara_sidebar_size_class = 'col-md-' . $madara_sidebar_size . ' col-sm-' . $madara_sidebar_size;
	$madara_main_col_class = 'col-md-' . (12 - $madara_sidebar_size) . ' col-sm-' . (12 - $madara_sidebar_size);

	get_header();

?>

    <div class="c-page-content style-1">
        <div class="content-area <?php echo esc_attr($page_custom_css);?>" style="<?php echo esc_attr( $archive_margin_top != '' ? 'margin-top: ' . $archive_margin_top . 'px' : ''); ?>">
            <div class="container<?php echo $manga_page_layout;?>">
                <div class="row <?php echo esc_attr( $madara_sidebar == 'left' ? 'sidebar-left' : '') ?>">
                    <?php do_action('madara_before_page_content'); ?>
                    <div class="main-col <?php echo esc_attr( $madara_sidebar != 'full' && ( is_active_sidebar( 'manga_archive_sidebar' ) || is_active_sidebar( 'main_sidebar' ) ) ? $madara_main_col_class : 'sidebar-hidden col-md-12 col-sm-12'); ?>">

						<?php get_template_part( 'html/main-bodytop' ); ?>

                        <div class="main-col-inner <?php echo esc_attr( $class_1 ); ?>">

							<?php if ( $madara_archive_heading_text != '' ) { ?>
                                <div class="<?php echo madara_get_default_heading_style();?> font-heading <?php echo esc_attr( $madara_archive_heading_icon == '' ? 'no-icon' : '' ); ?>">

                                    <h1 class="h4">

										<?php if ( $madara_archive_heading_icon != '' ) { ?>
                                            <i class="<?php echo esc_attr( $madara_archive_heading_icon ); ?>"></i>
										<?php } ?>

										<?php echo esc_html( $madara_archive_heading_text ); ?>

                                    </h1>
                                </div>
							<?php } ?>

							<?php the_content();?>
											
							<?php if($show_filter_bar == 'yes'){ ?>
							<div class="tab-wrap filter-bar">
								<div class="<?php echo madara_get_default_heading_style();?> font-heading fullsize">
									<div class="h4">
										<?php if($madara_archive_heading_text == ''){?>
										<i class="<?php madara_default_heading_icon(); ?>"></i>
										<?php }?>
										<?php echo sprintf( _n( '%s result', '%s results', $madara_custom_query->post_count, 'madara' ), $madara_custom_query->found_posts ); ?>
									</div>
									<?php get_template_part( 'madara-core/manga-filter' ); ?>
								</div>
							</div>
							<?php } ?>
                            <div class="c-blog-listing <?php echo esc_attr( $class_2 ); ?>">
                                <div class="c-blog__inner">
                                    <div class="c-blog__content">
											<?php 
											if($show_alphabeta_filters){
												global $wp;
												manga_listing_alphabeta_bars($wp->request ? home_url('/') . $wp->request : home_url('/'));
											}
											?>
										<?php if ( $madara_custom_query->have_posts() ) : 
											?>
                                            <div id="loop-content" class="page-content-listing <?php echo esc_attr('item-' . $manga_archives_item_layout);?> <?php echo $manga_archives_item_columns == -1 ? 'auto-cols' : '';?>">
												<?php
													$index = 1;
													set_query_var( 'madara_post_count', $madara_post_count );
													set_query_var('manga_archives_item_layout', $manga_archives_item_layout);
													set_query_var('manga_archives_item_columns', $manga_archives_item_columns);

													set_query_var('manga_archives_item_bigthumbnail', $bigthumbnail_layout2);
												?>

												<?php while ( $madara_custom_query->have_posts() ) : $madara_custom_query->the_post(); ?>

													<?php
													set_query_var( 'madara_loop_index', $index );

													if ( $page_content == 'manga' ) {
														if($manga_archives_item_columns != 1){
															if($manga_archives_item_columns == -1){
																// just synchronize the value
																$manga_archives_item_columns = 0;
															}

															set_query_var('manga_archives_item_columns', $manga_archives_item_columns);
														}

														get_template_part( 'madara-core/content/content-archive' );
														
													} else {
														get_template_part( 'html/loop/content' );
													}

													$index ++;
													
													do_action('madara-manga-archive-loop', $index);

													?>

												<?php endwhile;
													wp_reset_postdata(); ?>

                                            </div>

										<?php else : ?>

											<?php get_template_part( 'html/loop/content', 'none' ); ?>

										<?php endif; ?>

										<?php
											//Get Pagination
											$madara_pagination = new App\Views\ParsePagination();
											$madara_pagination->renderPageNavigation( '#loop-content', $template, $madara_custom_query, $nav_type );
										?>

										<script type="text/javascript">
											if(typeof __madara_query_vars !== 'undefined'){
												__madara_query_vars.manga_archives_item_columns = <?php echo $manga_archives_item_columns;?>;
											}
											
                                        </script>

                                    </div>
                                </div>
                            </div>

                        </div>


						<?php get_template_part( 'html/main-bodybottom' ); ?>

                    </div>


					<?php
						if ( $madara_sidebar != 'full' && is_active_sidebar( 'main_sidebar' ) ) {
							?>
                            <div class="sidebar-col <?php echo esc_html($madara_sidebar_size_class); ?>">
								<?php get_sidebar(); ?>
                            </div>
						<?php }
					?>
                    <?php do_action('madara_after_body_content'); ?>
                </div>
            </div>
        </div>
    </div>


<?php

	get_footer();
