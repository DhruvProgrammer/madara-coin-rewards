<?php
	/** Manga Reading Content - Text type **/

	$wp_manga     = madara_get_global_wp_manga();
	$post_id      = get_the_ID();
	
	$this_chapter = $this->get('chapter');
	
	if(!$this_chapter){
		 // support Madara Core before 1.6
		 if($chapter_slug = get_query_var('chapter')){
			global $wp_manga_functions;
			$this_chapter = $wp_manga_functions->get_chapter_by_slug( $post_id, $chapter_slug );
		 }
		 if(!$this_chapter){
			return;
		 }
	}
	
	$chapter_type = get_post_meta( $post_id, '_wp_manga_chapter_type', true );
	
	$name = $this_chapter['chapter_slug'];
	
	$chapter_content = new WP_Query( array(
		'post_parent' => $this_chapter['chapter_id'],
		'post_type'   => 'chapter_text_content'
	) );
	
	$server = isset($_GET['host']) ? $_GET['host'] : '';
	
	if ( $chapter_content->have_posts() ) {
		$posts = $chapter_content->posts;

		$post = $posts[0];
		
		/**
		 * If alternative_content is empty, show default content
		 **/
		$alternative_content = apply_filters('wp_manga_chapter_content_alternative', '');
		
		if(!$alternative_content){
			
		if ( $chapter_type == 'text' ) { ?>

			<?php do_action( 'madara_before_text_chapter_content' ); ?>

            <div class="text-left">
				<?php echo apply_filters('the_content', $post->post_content); ?>
            </div>
			<div id="text-chapter-toolbar">
				<a href="#"><i class="icon ion-md-settings"></i></a>
			</div>

			<?php do_action( 'madara_after_text_chapter_content' ); ?>

		<?php } elseif ( $chapter_type == 'video' ) { ?>

			<?php do_action( 'madara_before_video_chapter_content' ); ?>

            <div class="chapter-video-frame">
				<?php $wp_manga->chapter_video_content($post, $server); ?>
            </div>

			<?php do_action( 'madara_after_video_chapter_content' ); ?>

		<?php }
		
		} else {
			echo madara_filter_content($alternative_content);
		}

	}

	wp_reset_postdata();


global $is_amp_required;
$is_amp_required = true;
$wp_manga->manga_nav( 'footer' );
$is_amp_required = false;