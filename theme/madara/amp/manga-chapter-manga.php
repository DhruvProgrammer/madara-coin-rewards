<?php
global $wp_manga_functions, $wp_manga;
$post_id = get_the_ID();

/**
 * If alternative_content is empty, show default content
 **/
$alternative_content = apply_filters('wp_manga_chapter_content_alternative', '');
	
if(!$alternative_content){
			
	$reading_chapter = $this->get('chapter');
	$name = $reading_chapter['chapter_slug'];
	$chapter  = $wp_manga_functions->get_single_chapter( $post_id, $reading_chapter['chapter_id'] );
	$in_use   = $chapter['storage']['inUse'];
	$alt_host = isset( $_GET['host'] ) ? $_GET['host'] : null;
	if ( $alt_host ) {
		$in_use = $alt_host;
	}
	
	$storage = $chapter['storage'];
	if ( ! isset( $storage[ $in_use ] ) || ! is_array( $storage[ $in_use ]['page'] ) ) {
		return;
	}

	$madara_reading_list_total_item = 0;
	
	$need_button_fullsize = false;
	
	$amp_height = function_exists('ot_get_option') ? ot_get_option('amp_image_height', 400) : 400;

	$reading_style = function_exists('ot_get_option') ? ot_get_option('amp_manga_reading_style', 'list') : 'list';
	
	if($reading_style == 'slides') {
		global $wp_manga_chapter;
		$chapter_amp_height = $wp_manga_chapter->get_chapter_meta($reading_chapter, 'AMP_Height');
		if($chapter_amp_height){
			$amp_height = $chapter_amp_height;
		}
				
		?>
		<amp-carousel height="<?php echo esc_attr($amp_height);?>" layout="fixed-height" type="slides">
		<?php
	}

	foreach ( $chapter['storage'][ $in_use ]['page'] as $page => $link ) {

		$madara_reading_list_total_item = count( $chapter['storage'][ $in_use ]['page'] );

		$host = $chapter['storage'][ $in_use ]['host'];
		$src  = apply_filters('wp_manga_chapter_image_url', $host . $link['src'], $host, $link['src'], $post_id, $name);
		
		if($src != ''){

		?>

        <div class="fixed-container">
            <amp-img id="image-<?php echo esc_attr( $page ); ?>" class="contain" src="<?php echo esc_url( $src ); ?>" layout="fill"></amp-img>
        </div>

		<?php 
		
		}
	}
	
	if($reading_style == 'slides') {
	?>
	</amp-carousel>
	<?php
	}
} else {
	echo madara_filter_content($alternative_content);
}
	
	global $is_amp_required;
	$is_amp_required = true;
	$wp_manga->manga_nav( 'footer' );
	$is_amp_required = false;