<?php
    /**
	 * The Template for Manga Search results page
	 *
	 * This template can be overridden by copying it to your-child-theme/madara-core/manga-search.php
	 *
	 * HOWEVER, on occasion Madara will need to update template files and you
	 * (the theme developer) will need to copy the new files to your theme to
	 * maintain compatibility. We try to do this as little as possible, but it does
	 * happen. When this occurs the version of the template file will be bumped and
	 * we will list any important changes on our theme release logs.
	 * @package Madara
	 * @version 2.2.5
	 */
	 
	 use App\Madara;
    
	/**
	 * @package madara
	 */

	get_header();

	/**
	 * madara_before_main_page hook
	 *
	 * @hooked madara_output_before_main_page - 10
	 * @hooked madara_output_top_sidebar - 89
	 *
	 * @author
	 * @since 1.0
	 * @code     Madara
	 */

	do_action( 'madara_before_main_page' );

	echo do_shortcode('[madara_advance_searchform container=1]');

	global $s_query;
	$s = esc_html(isset( $_GET['s'] ) ? $_GET['s'] : '');

	$madara_search_use_archivelayout = Madara::getOption('madara_search_use_archivelayout', 'no');
	
	$container = '';
	if($madara_search_use_archivelayout == 'yes'){
		$manga_archives_item_layout = Madara::getOption('manga_archives_item_layout','default');
		
		$container = Madara::getOption('manga_archives_page_container','');

		global $wp_query;
		
		$wp_query->set('madara_post_count', madara_get_post_count( $s_query ) );
		$wp_query->set('manga_archives_item_layout', $manga_archives_item_layout);
		$wp_query->set('sidebar', 'full');

		$manga_archives_item_columns = 0;
		$wp_query->set('manga_archives_item_columns', $manga_archives_item_columns);
	}
	
	?>
    <div class="c-page-content">
        <div class="content-area">
            <div class="container<?php echo $container;?>">
                <div class="row">
                    <div class="main-col col-md-12 sidebar-hidden">

						<?php get_template_part( 'html/main-bodytop' ); ?>

                        <!-- container & no-sidebar-->
                        <div class="main-col-inner">
							<?php
								if ( $s_query->have_posts() ) {
									?>
                                    <div class="search-wrap">
                                        <div class="tab-wrap">
                                            <div class="<?php echo madara_get_default_heading_style();?> font-heading fullsize">
												
                                                <h1 class="h4">
                                                    <i class="<?php madara_default_heading_icon(); ?>"></i> 
													<?php if($s){?>
													<?php echo sprintf( _n( '%s result for "%s"', '%s results for "%s"', $s_query->found_posts, 'madara' ), $s_query->found_posts, apply_filters("madara_search_results_title_conditions", $s) ); ?>
													<?php } else {
														esc_html_e("All Mangas", "madara");
													}?>
                                                </h1>
												
												<?php get_template_part( 'madara-core/manga-filter' ); ?>
                                            </div>
                                        </div>
										<div class="c-page__content">
											<div class="tab-content-wrap">
												<div role="tabpanel" class="c-tabs-item">
													<div id="loop-content" class="page-content-listing <?php echo $madara_search_use_archivelayout == 'yes' ? esc_attr('item-' . $manga_archives_item_layout) . " " . ($manga_archives_item_columns === 0 ? 'auto-cols':'') : "";?>">
														<?php
															
															$index = 1;
															while ( $s_query->have_posts() ) {
																$wp_query->set( 'madara_loop_index', $index );
																$index ++;
																$s_query->the_post();
																get_template_part( 'madara-core/content/content', $madara_search_use_archivelayout == 'yes' ? 'archive' : 'search' );

															}														
															
															wp_reset_postdata();
														?>
													</div>
												</div>
												<?php
												$madara_pagination = new App\Views\ParsePagination();
												$madara_pagination->renderPageNavigation( '#loop-content', 'madara-core/content/content-' . ($madara_search_use_archivelayout == 'yes' ? 'archive' : 'search'), $s_query );
												?>
											</div>
										</div>
                                    </div>
									<?php
								} else {
									get_template_part( 'madara-core/content/content', 'none' );
								}
							?>
                        </div>

						<?php get_template_part( 'html/main-bodybottom' ); ?>

                    </div>
                </div>
            </div>
        </div>
    </div>
<?php

	/**
	 * madara_after_main_page hook
	 *
	 * @hooked madara_output_after_main_page - 90
	 * @hooked madara_output_bottom_sidebar - 91
	 *
	 * @author
	 * @since 1.0
	 * @code     Madara
	 */
	do_action( 'madara_after_main_page' );

	get_footer();
