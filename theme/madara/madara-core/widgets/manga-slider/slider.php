<?php

	/**
	 * The Template for WP Manga Slider widget
	 *
	 * This template can be overridden by copying it to your-child-theme/madara-core/widgets/manga-slider/slider.php
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
	$thumb_size         = array( 642, 320 );
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

	$slider_image = get_post_meta( $post_id, 'manga_banner', true );
?>

<div class="slider__item <?php echo has_post_thumbnail() ? '' : 'no-thumb'; ?>">

	<?php if ( has_post_thumbnail() ) { ?>
        <div class="slider__thumb">
            <div class="slider__thumb_item">
				<?php madara_manga_title_badges_html($post_id, true);?>
                <a href="<?php echo get_the_permalink() ?>">
					<?php 
					
					if($slider_image != ''){
						echo '<img src="' . esc_url($slider_image) . '"/>';
					} else {
						if ( $allow_thumb_gif == 'off' ) {
							echo madara_thumbnail( $thumb_size );
						} else {
							echo get_the_post_thumbnail( $post_id, $thumb_size );
						}
					}?>
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
        </div>
    </div>
</div>