<?php
	/**
	 * The Template for content of a manga chapter, paged layout (ie. 1 or few images in 1 page), in a Chapter Reading page
	 *
	 * This template can be overridden by copying it to your-child-theme/madara-core/reading-content/content-reading-paged.php
	 *
	 * HOWEVER, on occasion Madara will need to update template files and you
	 * (the theme developer) will need to copy the new files to your theme to
	 * maintain compatibility. We try to do this as little as possible, but it does
	 * happen. When this occurs the version of the template file will be bumped and
	 * we will list any important changes on our theme release logs.
	 * @package Madara
	 * @version 1.8
	 */

	use App\Madara;

	$wp_manga_functions = madara_get_global_wp_manga_functions();
	global $wp_manga;
	$post_id  = get_the_ID();
	
	$reading_chapter = function_exists('madara_permalink_reading_chapter') ? madara_permalink_reading_chapter() : false;
	
	if(!$reading_chapter){
		 // support Madara Core before 1.6
		 if($chapter_slug = get_query_var('chapter')){
			global $wp_manga_functions;
			$reading_chapter = $wp_manga_functions->get_chapter_by_slug( $post_id, $chapter_slug );
		 }
		 if(!$reading_chapter){
			return;
		 }
	}
	
	$name     = $reading_chapter['chapter_slug'];
	
	$paged    = get_query_var($wp_manga->manga_paged_var) ? get_query_var($wp_manga->manga_paged_var) : 1;
	
	$style    = isset( $_GET['style'] ) ? $_GET['style'] : 'paged';

	// For redirecting if page is invalid
	$is_valid_page = true;
	$url_redirect  = get_the_permalink();

	$manga_reading_style = Madara::getOption( 'manga_reading_style', 'paged' );
	$preload_images      = Madara::getOption( 'manga_reading_preload_images', 'on' );

	$manga_reading_navigation_by_pointer = Madara::getOption( 'manga_reading_navigation_by_pointer', 'on' );

	$is_lazy_load = Madara::getOption( 'lazyload', 'off' ) == 'on' ? true : false;
	
	if ( $is_lazy_load ) {
		$lazyload = 'wp-manga-chapter-img img-responsive lazyload effect-fade';
	} else {
		$lazyload = 'wp-manga-chapter-img';
	}
	$this_chapter = $reading_chapter;

	$chapter    = $wp_manga_functions->get_single_chapter( $post_id, $reading_chapter['chapter_id'] );
	
	if( empty( $chapter ) ){
		$is_valid_page = false;
	}else{
		$in_use     = $chapter['storage']['inUse'];

		// Check if page is valid
		$total_pages = madara_actual_total_pages( $chapter['total_page'], $post_id, $reading_chapter );

		if( $total_pages > 0 && $paged > $total_pages ){
			$is_valid_page = false;
			$url_redirect = $wp_manga_functions->build_chapter_url(  get_the_ID(), $name,
			'paged', null, 1 );
		}

		$alt_host = isset( $_GET['host'] ) ? $_GET['host'] : null;
		if ( $alt_host ) {
			$in_use = $alt_host;
		}
	}

	$img_per_page = intval( madara_get_img_per_page() );

	if ( $manga_reading_navigation_by_pointer == 'on' && ( $manga_reading_style == 'paged' || $style == 'paged' ) ) { ?>
		<a href="javascript:void(0)" class="page-link-hover page-prev-link"></a>
	<?php }

	if ( ! empty( $img_per_page ) && $img_per_page != '1' ) {

		$paged = $img_per_page * ( $paged - 1 );

		$need_button_fullsize = false;
		
		for ( $i = 1; $i <= $img_per_page; $i ++ ) {

			if ( ! isset( $chapter['storage'][ $in_use ]['page'][ $paged ] ) ) {
				break;
			}

			$host = $chapter['storage'][ $in_use ]['host'];
			$link = $chapter['storage'][ $in_use ]['page'][ $paged ]['src'];
			
			$src  = apply_filters('wp_manga_chapter_image_url', $host . $link, $host, $link, $post_id, $name);

			$madara_reading_list_total_item = count( $chapter['storage'][ $in_use ]['page'] );
			
			if($src != ''){

			?>

			<?php do_action( 'madara_before_chapter_image', $paged, $madara_reading_list_total_item ); ?>

            <img id="image-<?php echo esc_attr( $paged ); ?>" data-image-paged="<?php echo esc_attr( $paged ); ?>" <?php if($is_lazy_load){ echo 'data-src="'; } else { echo 'src="';}?><?php echo esc_url( $src ); ?>" class="<?php echo esc_attr( $lazyload ); ?>">
			
			<?php 
			//if(!$need_button_fullsize) {
				//list($width, $height, $type, $attr) = @getimagesize($src);
				
				//if(isset($width) && $width > 1140) {
					$need_button_fullsize = true;
				//}
			//}
			?>

			<?php do_action( 'madara_after_chapter_image', $paged, $madara_reading_list_total_item ); ?>

			<?php
			}
			$paged ++;
		}
		
		if($need_button_fullsize){ ?>
			<a href="javascript:void(0)" id="btn_view_full_image"><?php esc_html_e('View Full Size Image', 'madara');?></a>
		<?php
		}
	} else {
		if(isset($chapter['storage'][ $in_use ])){
			$host = $chapter['storage'][ $in_use ]['host'];
			
			$paged = $paged - 1; // index starts from 0
			
			if ( ! isset( $chapter['storage'][ $in_use ]['page'][ $paged ] ) ) {
				return;
			}

			$link = $chapter['storage'][ $in_use ]['page'][ $paged ]['src'];
			$src  = apply_filters('wp_manga_chapter_image_url', $host . $link, $host, $link, $post_id, $name);
			$madara_reading_list_total_item = count( $chapter['storage'][ $in_use ]['page'] );
			?>

			<?php do_action( 'madara_before_chapter_image', $paged, $madara_reading_list_total_item ); ?>

			<img id="image-<?php echo esc_attr( $paged ); ?>" data-image-paged="<?php echo esc_attr( $paged ); ?>" <?php if($is_lazy_load){ echo 'data-src="'; } else { echo 'src="';}?><?php echo esc_url( $src ); ?>" class="<?php echo esc_attr( $lazyload ); ?>">
			
			<?php
				//list($width, $height, $type, $attr) = @getimagesize($src);
				
				//if($width > 1140) {
					?>
					<a href="javascript:void(0)" id="btn_view_full_image"><?php esc_html_e('View Full Size Image', 'madara');?></a>
					<?php
				//}
				?>

			<?php do_action( 'madara_after_chapter_image', $paged, 0 ); ?>

			<?php
		}
	}

	if ( $manga_reading_navigation_by_pointer == 'on' && ( $manga_reading_style == 'paged' || $style == 'paged' ) ) { ?>
		<a href="javascript:void(0)" class="page-link-hover page-next-link"></a>
	<?php }
	if( $preload_images == 'on' && isset( $chapter['storage'][ $in_use ]['page'] ) && is_array( $chapter['storage'][ $in_use ]['page'] ) ){

		$output_images = array();

		foreach( $chapter['storage'][ $in_use ]['page'] as $index => $page ){
			$output_images[ $index ] = apply_filters('wp_manga_chapter_image_url', $chapter['storage'][ $in_use ]['host'] . $page['src'], $chapter['storage'][ $in_use ]['host'], $page['src'], $post_id, $name);
		}

		?>
		<script type="text/javascript" id="chapter_preloaded_images">
			var chapter_preloaded_images = <?php echo json_encode( $output_images ); ?>, chapter_images_per_page = <?php echo esc_js($img_per_page); ?>;
		</script>
		<?php
	}

	if( ! $is_valid_page ){
		?>
			<script type="text/javascript">
				window.location = "<?php echo esc_url( $url_redirect ); ?>";
			</script>
		<?php
	}
