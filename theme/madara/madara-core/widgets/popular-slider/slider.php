<?php

	/**
	 * The Template for WP Manga Popular Slider widget
	 *
	 * This template can be overridden by copying it to your-child-theme/madara-core/widgets/popular-slider/slider.php
	 *
	 * HOWEVER, on occasion Madara will need to update template files and you
	 * (the theme developer) will need to copy the new files to your theme to
	 * maintain compatibility. We try to do this as little as possible, but it does
	 * happen. When this occurs the version of the template file will be bumped and
	 * we will list any important changes on our theme release logs.
	 * @package Madara
	 * @version 2.0
	 */

	use App\Madara;

	$wp_manga_functions = madara_get_global_wp_manga_functions();
	$thumb_size         = array( 125, 180 );
	$allow_thumb_gif    = Madara::getOption( 'manga_single_allow_thumb_gif', 'off' );
	$post_id = get_the_ID();

	$thumb_url = get_the_post_thumbnail_url( $post_id );

	$thumb_type = 'gif';
	if ( $thumb_url != '' ) {
		$type = substr( $thumb_url, - 3 );
	}

	if ( $allow_thumb_gif == 'on' && $thumb_type == $type ) {
		$thumb_size = 'full';
	}
	
	

?>
<div class="slider__item">
    <div class="item__wrap <?php echo has_post_thumbnail() ? '' : 'no-thumb'; ?>">

		<?php if ( has_post_thumbnail() ) { ?>
            <div class="slider__thumb">
                <div class="slider__thumb_item c-image-hover">
					<?php madara_manga_title_badges_html($post_id, true);?>
                    <a href="<?php echo get_the_permalink() ?>">
						<?php if ( $allow_thumb_gif == 'off' ) {
							echo madara_thumbnail( $thumb_size );
						} else {
							echo get_the_post_thumbnail( $post_id, $thumb_size );
						} ?>
                        <div class="slider-overlay"></div>
                    </a>
                </div>
            </div>
		<?php } ?>

        <div class="slider__content">
            <div class="slider__content_item">
                <div class="post-title font-title">
                    <h4>
                        <a href="<?php echo get_the_permalink() ?>"><?php echo get_the_title() ?></a>
                    </h4>
                </div>
				<?php $date = get_post_meta( $post_id, '_latest_update', true ) ?>
				<?php if ( $date && '' != $date ): ?>
                    <div class="post-on font-meta">
                        <span>
                            <?php echo date_i18n( get_option( 'date_format' ), $date ); ?>
                        </span>
                    </div>
				<?php endif ?>

                <div class="chapter-item ">
					<?php 
					
						global $wp_manga_database;
			
						$sort_setting = $wp_manga_database->get_sort_setting();

						$sort_by    = $sort_setting['sortBy'];
						$sort_order = $sort_setting['sort'];
						
						$chapters = $wp_manga_functions->get_latest_chapters( $post_id, null, 2, 0, $sort_by, $sort_order );
						if ( $chapters ) {
							foreach ( $chapters as $chapter ) {

								$style = $wp_manga_functions->get_reading_style();

								$manga_link = $wp_manga_functions->build_chapter_url( $post_id, $chapter['chapter_slug'], $style );

								?>

								<?php if ( ! empty( $chapter['chapter_name'] ) ) { ?>
                                    <span class="chapter <?php echo apply_filters('wp_manga_chapter_item_class', '', $chapter, $post_id);?>">
                                        <a href="<?php echo esc_url( $manga_link ); ?>"><?php echo wp_kses_post( $chapter['chapter_name'] ) ?></a>
                                    </span>
								<?php } ?>

							<?php }
						}
					?>
                </div>
            </div>
        </div>

    </div>
</div>
