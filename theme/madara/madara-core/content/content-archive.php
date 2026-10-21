<?php

	/**
	 * The Template for Manga Item layout, in a loop in Manga Archives page
	 *
	 * This template can be overridden by copying it to your-child-theme/madara-core/content/content-archive.php
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

	$wp_query           = madara_get_global_wp_query();
	$wp_manga           = madara_get_global_wp_manga();
	$wp_manga_setting   = madara_get_global_wp_manga_setting();
	$wp_manga_functions = madara_get_global_wp_manga_functions();
    
	$thumb_size          = apply_filters('manga_archive_thumb_size', 'madara_manga_big_thumb');
	$madara_loop_index   = get_query_var( 'madara_loop_index' );
	
	$madara_total_posts  = get_query_var( 'madara_post_count' );
	$madara_page_sidebar = get_query_var( 'sidebar' );

	$manga_hover_details     = Madara::getOption( 'manga_hover_details', 'off' );
	$manga_archives_item_mobile_width = Madara::getOption( 'manga_archives_item_mobile_width', '50' ) == 50 ? false : true;

	$manga_id = get_the_ID();
 
	$alternative             = $wp_manga_functions->get_manga_alternative( $manga_id );

	$authors                 = $wp_manga_functions->get_manga_authors( $manga_id );
	$chapter_type = get_post_meta( $manga_id, '_wp_manga_chapter_type', true );

	$show_rating = get_query_var('manga_archive_show_rating');
	
	if($show_rating == '') {
		$show_rating = Madara::getOption('manga_archive_show_rating', 'on');
	}

	$force_show_latest_chapters = get_query_var('manga_archive_latest_chapters_count'); 
	$latest_chapters_count = intval($force_show_latest_chapters);

	if($force_show_latest_chapters === ""){
		// in case this template not called from a shortcode / elementor element
		$latest_chapters_count = intval(Madara::getOption('manga_archive_latest_chapters_visible', 2));
	}

	$manga_archives_item_layout = get_query_var('manga_archives_item_layout');

	$bigthumbnail_layout2 = get_query_var('manga_archives_item_bigthumbnail');

	$item_columns = 3;
	$main_col_class = '';	

	if ( $madara_page_sidebar == 'full' ) {
		if($manga_archives_item_layout == 'default' || $manga_archives_item_layout == 'small_thumbnail'){
			$main_col_class = 'col-12 col-md-4';
		} elseif($manga_archives_item_layout == 'big_thumbnail'){
			// big thumbnail layout
			$thumb_size              = ($manga_archives_item_mobile_width ? 'madara_manga_big_thumb_full' : 'madara_manga_big_thumb');
			$main_col_class = 'col-'. ($manga_archives_item_mobile_width ? '12' : '6') .' col-md-2';
			$item_columns = 6;
		} elseif($manga_archives_item_layout == 'simple') {
			$main_col_class = 'col-12';
			$item_columns = 12;
		}
	} else {
		if($manga_archives_item_layout == 'default' || $manga_archives_item_layout == 'small_thumbnail'){
			$main_col_class = 'col-12 col-md-6';
			$item_columns = 2;
		} elseif($manga_archives_item_layout == 'big_thumbnail') {
			// big thumbnail layout
			$thumb_size              = ($manga_archives_item_mobile_width ? 'madara_manga_big_thumb_full' : 'madara_manga_big_thumb');
			$main_col_class = 'col-'. ($manga_archives_item_mobile_width ? '12' : '6') .' col-md-3';
			$item_columns = 4;
		} elseif($manga_archives_item_layout == 'simple') {
			$main_col_class = 'col-12';
			$item_columns = 12;
		}
	}
	
	// in case there is "forced" setting for $item_columns
	$manga_archives_item_columns = get_query_var('manga_archives_item_columns');

	if($manga_archives_item_columns == 2){
		$main_col_class = 'col-12 col-md-6';
		$item_columns = 2;
	} else if($manga_archives_item_columns == 3){
		// big thumbnail layout
		$thumb_size              = 'madara_manga_big_thumb_full';
		$main_col_class = 'col-12 col-md-4';
		$item_columns = 3;
	} else if($manga_archives_item_columns == 4){
		// big thumbnail layout
		$thumb_size              = ($manga_archives_item_mobile_width ? 'madara_manga_big_thumb_full' : 'madara_manga_big_thumb');
		$main_col_class = 'col-'. ($manga_archives_item_mobile_width ? '12' : '6') .' col-md-3';
		$item_columns = 4;
	} else if($manga_archives_item_columns == 6){
		// big thumbnail layout
		$thumb_size              = ($manga_archives_item_mobile_width ? 'madara_manga_big_thumb_full' : 'madara_manga_big_thumb');
		$main_col_class = 'col-'. ($manga_archives_item_mobile_width ? '12' : '6') .' col-md-2';
		$item_columns = 6;
	} else if($manga_archives_item_columns === 0){
		$main_col_class = 'auto-col';
		$item_columns = 0;
	}

	if($bigthumbnail_layout2){
		$main_col_class .= ' bigthumbnail2';
	}

    $thumbnail_link = Madara::getOption('manga_archive_latest_chapter_on_thumbnail', 'off'); // default, ie. link to manga detail
	
	$title_badge_pos = Madara::getOption('manga_badge_position', 1); // 1: before title, 2: before thumbnail
	$archive_show_author = Madara::getOption('manga_archive_show_author', 'on');

	if ( $item_columns) {
		if($madara_loop_index % $item_columns == 1) {
?>
<div class="page-listing-item">
    <div class="row row-eq-height">
		<?php
		}
	} else {
?>
<div class="page-listing-item">
<?php	
			}
		?>

        <div class="<?php echo esc_attr( $main_col_class ); ?> <?php echo 'badge-pos-' . esc_attr($title_badge_pos);?>">
            <div class="page-item-detail <?php echo esc_attr($chapter_type);?> <?php echo esc_attr($manga_archives_item_mobile_width ? 'fullwidth' : '');?> ">
				<?php 
				do_action('wp_manga_before_content_archive', $manga_id);

				if($manga_archives_item_layout == 'simple'){
					get_template_part( 'madara-core/content/content-archive-simple' );
				} else { ?>
                <div id="manga-item-<?php echo esc_attr( $manga_id ); ?>" class="item-thumb <?php echo esc_attr($manga_hover_details == 'off' ? '' : 'hover-details'); ?> c-image-hover" data-post-id="<?php echo esc_attr($manga_id); ?>">
					<?php
					
						if ( has_post_thumbnail() ) {
                            $link = ($thumbnail_link == 'off' ? get_permalink() : madara_get_latest_chapter_link($manga_id));
							?>
                            <a href="<?php echo esc_url($link); ?>" title="<?php the_title_attribute(); ?>">
								<?php 
                                echo madara_thumbnail( $thumb_size );
                                
                                if($title_badge_pos == 2){
                                        madara_manga_title_badges_html( $manga_id, 1 );
                                }
                                
                                if($thumbnail_link == 'on'){
                                    $total_chapters = get_post_meta($manga_id, 'manga_expected_total', true);
                                    $current_chapters = $wp_manga_functions->get_chapters_count($manga_id);
                                    
                                    if($total_chapters){
										$text = esc_html__('Chapter %s', 'madara');
                                        $text = sprintf($text, $current_chapters . '/' . $total_chapters);
                                    } else {
										$text = esc_html__('%s chapters', 'madara');
                                        $text = sprintf($text, $current_chapters);
                                    }
                                    ?>
                                <span class="quick-chapter-link"><?php echo esc_html($text);?></span>
                                <?php }
                                                                
                                if(Madara::getOption('manga_archives_item_type_text', 'off') == 'on'){?>
                                <span class="manga-type"><?php echo get_post_meta($manga_id, '_wp_manga_type', true);?></span>
                                <?php }?>
                            </a>
							<?php if($bigthumbnail_layout2 == 'overlay'){?>
								<div class="overlay-content">
									<div class="post-title font-title">
										<h3 class="h5">
											<?php if($title_badge_pos == 1){?>
												<?php madara_manga_title_badges_html( $manga_id, 1 ); ?>
											<?php }?>
											<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
										</h3>
									</div>
									<?php if($archive_show_author == 'on'){?>
									<div class="author meta">
										<?php echo $wp_manga_functions->get_manga_authors($manga_id);?>
									</div>
									<?php }?>
								</div>
							<?php } ?>
							<?php
						}
					?>
                </div>
				<?php if($bigthumbnail_layout2 == '' || ($archive_show_author == 'on' || $latest_chapters_count)) {?>
                <div class="item-summary">
					<?php
					if($bigthumbnail_layout2 == ''){?>
						<div class="post-title font-title">
							<h3 class="h5">
								<?php if($title_badge_pos == 1){?>
									<?php madara_manga_title_badges_html( $manga_id, 1 ); ?>
								<?php }?>
								<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
							</h3>
						</div>
						<?php if($archive_show_author == 'on'){?>
							<div class="author meta">
								<?php echo $wp_manga_functions->get_manga_authors($manga_id);?>
							</div>
						<?php } ?>
					<?php } ?>
					<?php if($show_rating == 'on'){?>
                    <div class="meta-item rating">
						<?php
							$wp_manga_functions->manga_rating_display( $manga_id );
						?>
                    </div>
					<?php }?>
					<?php if($latest_chapters_count > 0){ ?>
                    <div class="list-chapter">
						<?php
							$all_ids = $wp_query->get( 'madara_ids');
							if($all_ids){
								$wp_manga_functions->manga_meta_cached( $all_ids, $manga_id, 0, $latest_chapters_count, $force_show_latest_chapters !== "" );
							} else {
								$wp_manga_functions->manga_meta( $manga_id, 0, $latest_chapters_count, $force_show_latest_chapters !== "" );
							}
						?>
                    </div>
					<?php }?>
                </div>
				<?php } ?>
				<?php } 
				do_action('wp_manga_after_content_archive', $manga_id);	
			?>

            </div>

        </div>
		<?php
			if ( $item_columns ) {
				if(($madara_loop_index % $item_columns == 0 ) || ( $madara_loop_index == $madara_total_posts )) {
		?>
    </div>
</div>
<?php
				}
	} else {
		?>
		</div>
		<?php
	}