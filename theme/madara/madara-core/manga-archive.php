<?php
	/**
	 * The Template for Manga Archives page
	 *
	 * This template can be overridden by copying it to your-child-theme/madara-core/manga-archive.php
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

	get_header();

	$wp_query = madara_get_global_wp_query();

	$madara_page_sidebar   = madara_get_manga_archive_sidebar();

	$madara_breadcrumb     = Madara::getOption( 'manga_archive_breadcrumb', 'on' );

	$manga_archive_heading = Madara::getOption( 'manga_archive_heading', esc_html__('All Mangas', 'madara') );
	$manga_archive_heading = apply_filters( 'madara_archive_heading', $manga_archive_heading );
	
	$manga_archives_item_layout = Madara::getOption( 'manga_archives_item_layout', 'default' );
	$bigthumbnail_layout2 = '';

	if($manga_archives_item_layout == 'big_thumbnail_2'){
		$manga_archives_item_layout = 'big_thumbnail';
		$bigthumbnail_layout2 = 'overlay';
	}
	
	//set args
	if ( ! empty( get_query_var( 'paged' ) ) ) {
		$paged = get_query_var( 'paged' );
	} elseif ( ! empty( get_query_var( 'page' ) ) ) {
		$paged = get_query_var( 'page' );
	} else {
		$paged = 1;
	}

	$orderby = isset( $_GET['m_orderby'] ) ? $_GET['m_orderby'] : 'latest';

	$manga_args = array(
		'paged'    => $paged,
		'orderby'  => $orderby,
		'template' => 'archive',
		'sidebar'  => $madara_page_sidebar,
	);

	foreach ( $manga_args as $key => $value ) {
		$wp_query->set( $key, $value );
	}

	
	if ( is_home() || is_front_page() || is_manga_posttype_archive() ) {	
		$actual_query_vars = $manga_args;
		//$manga_query = madara_manga_query( $manga_args );
	} else {
		$actual_query_vars = $wp_query->query_vars;
		//$manga_query = madara_manga_query( $wp_query->query_vars );
	}

	if(class_exists('WP_MANGA_CACHE_SYSTEM')){
		$results = WP_MANGA_CACHE_SYSTEM::get_cached_or_query($actual_query_vars);
		$posts_count = WP_MANGA_CACHE_SYSTEM::get_cached_or_count($actual_query_vars);
	} else {
		$results = madara_manga_query($actual_query_vars)->get_posts();
		$posts_count = madara_manga_query($actual_query_vars)->found_posts;
	}
	
	$container = Madara::getOption('manga_archives_page_container', '');//'-fluid';

	$page_custom_css = '';
	$page_custom_css = apply_filters('madara_page_custom_css_class', $page_custom_css);
	$manga_archives_item_columns = Madara::getOption( 'manga_archives_item_columns', -1 );

	$madara_sidebar_size = Madara::getOption( 'sidebar_size', 4 );
	$madara_sidebar_size_class = 'col-md-' . $madara_sidebar_size . ' col-sm-' . $madara_sidebar_size;
	$madara_main_col_class = 'col-md-' . (12 - $madara_sidebar_size) . ' col-sm-' . (12 - $madara_sidebar_size);
?>
<script type="text/javascript">
	var manga_args = <?php echo str_replace( '\/', '/', json_encode( $actual_query_vars ) ); ?>;
</script>
<?php
	if ( $madara_breadcrumb == 'on' ) {
		get_template_part( 'madara-core/manga', 'breadcrumb' );
	}
?>
<div class="c-page-content style-1">
    <div class="content-area <?php echo esc_attr($page_custom_css);?>">
        <div class="container<?php echo $container;?>">
            <div class="row <?php echo esc_attr( $madara_page_sidebar == 'left' ? 'sidebar-left' : ''); ?>">
                <?php do_action('madara_before_page_content'); ?>
                <div class="main-col <?php echo esc_attr( $madara_page_sidebar != 'full' && ( is_active_sidebar( 'manga_archive_sidebar' ) || is_active_sidebar( 'main_sidebar' ) ) ? 'main-col ' . $madara_main_col_class : 'sidebar-hidden col-md-12 col-sm-12'); ?>">

					<?php get_template_part( 'html/main-bodytop' ); ?>

                    <!-- container & no-sidebar-->
                    <div class="main-col-inner">
                        <div class="c-page">
							<?php if ( is_tax() ) { 
									$tax = get_queried_object();
								?>
                                <div class="entry-header">
                                    <div class="entry-header_wrap">
                                        <div class="entry-title">
                                            <h1 class="item-title h4"><?php echo apply_filters( 'madara_archive_taxonomy_heading', isset( $tax->name ) ? $tax->name : '' ); ?></h1>
											<?php
											if($tax->description != '') {?>
											<p class="item-description"><?php echo wp_kses_post($tax->description);?></p>
											<?php }?>
                                        </div>
                                    </div>
                                </div>
							<?php } else if ( is_manga_archive() && $manga_archive_heading != '' ) { ?>

                                <div class="entry-header">
                                    <div class="entry-header_wrap">
                                        <div class="entry-title">
                                            <h1 class="item-title h4"><?php echo esc_html( $manga_archive_heading ) ?></h1>
                                        </div>
                                    </div>
                                </div>

							<?php } ?> 
                            <!-- <div class="c-page__inner"> -->
                            <div class="c-page__content">
                                <div class="tab-wrap">
                                    <div class="<?php echo madara_get_default_heading_style();?> font-heading fullsize">
                                        <div class="h4">
                                            <i class="<?php madara_default_heading_icon(); ?>"></i>
											<?php echo sprintf( _n( '%s result', '%s results', $posts_count, 'madara' ), $posts_count ); ?>
                                        </div>
										<?php get_template_part( 'madara-core/manga-filter' ); ?>
                                    </div>
                                </div>
                                <!-- Tab panes -->
                                <div class="tab-content-wrap">
                                    <div role="tabpanel" class="c-tabs-item">
									<div id="loop-content" class="page-content-listing <?php echo esc_attr('item-' . $manga_archives_item_layout);?>  <?php echo $manga_archives_item_columns == -1 ? 'auto-cols' : '';?>">
											<?php
											
												if ( $results && count($results) ) {

													$index = 1;
													$wp_query->set( 'madara_post_count', $posts_count );
													$wp_query->set('manga_archives_item_layout', $manga_archives_item_layout);
													$wp_query->set('manga_archives_item_bigthumbnail', $bigthumbnail_layout2);
													if($manga_archives_item_columns != 1){
														if($manga_archives_item_columns == -1){
															// just synchronize the value
															$manga_archives_item_columns = 0;
														}

														set_query_var('manga_archives_item_columns', $manga_archives_item_columns);
													}

													/**
													 * For bulk query
													 */
													$all_manga_ids = array_map(function($item){ return $item->ID;}, $results);
													global $wp_manga_chapter;													
													$wp_query->set( 'madara_ids', $all_manga_ids );

													foreach($results as $result){
														global $post;
														$post = $result;
														setup_postdata($post);

														$wp_query->set( 'madara_loop_index', $index );
														$index ++;
														
														get_template_part( 'madara-core/content/content', 'archive' );
														
														do_action('madara-manga-archive-loop', $index);
													}

													wp_reset_postdata();

													/* while ( $manga_query->have_posts() ) {

														$wp_query->set( 'madara_loop_index', $index );
														$index ++;

														$manga_query->the_post();
														get_template_part( 'madara-core/content/content', 'archive' );
														
														do_action('madara-manga-archive-loop', $index);
													} */

												} else {
													get_template_part( 'madara-core/content/content-none' );
												}
											?>
                                        </div>
										<?php
											$madara_pagination = new App\Views\ParsePagination();
											$madara_pagination->renderPageNavigation( '.c-tabs-item .page-content-listing', 'madara-core/content/content-archive', $actual_query_vars );
										?>
										<script type="text/javascript">
											if(typeof __madara_query_vars !== 'undefined'){
												__madara_query_vars.manga_archives_item_columns = <?php echo $manga_archives_item_columns;?>;
											}
											
                                        </script>
                                    </div>
                                </div>
                            </div>
                            <!-- </div> -->
                        </div>
                        <!-- paging -->
                    </div>

					<?php get_template_part( 'html/main-bodybottom' ); ?>

                </div>
				<?php
					if ( $madara_page_sidebar != 'full' && ( is_active_sidebar( 'manga_archive_sidebar' ) || is_active_sidebar( 'main_sidebar' ) ) ) {
						?>
                        <div class="sidebar-col <?php echo esc_html($madara_sidebar_size_class); ?>">
							<?php get_sidebar(); ?>
                        </div>
						<?php
					}
				?>
                <?php do_action('madara_after_body_content'); ?>
            </div>
        </div>
    </div>
</div>
<?php get_footer(); ?>
