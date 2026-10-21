<?php
	/**
	 * The Template for content of a text/video chapter, in a Chapter Reading page
	 *
	 * This template can be overridden by copying it to your-child-theme/madara-core/reading-content/content-reading-content.php
	 *
	 * HOWEVER, on occasion Madara will need to update template files and you
	 * (the theme developer) will need to copy the new files to your theme to
	 * maintain compatibility. We try to do this as little as possible, but it does
	 * happen. When this occurs the version of the template file will be bumped and
	 * we will list any important changes on our theme release logs.
	 * @package Madara
	 * @version 1.7.2.2
	 */

	$wp_manga     = madara_get_global_wp_manga();
	$post_id      = get_the_ID();

	$this_chapter = function_exists('madara_permalink_reading_chapter') ? madara_permalink_reading_chapter() : false;

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
		?>
		<?php if ( $chapter_type == 'text' ) { ?>

			<?php do_action( 'madara_before_text_chapter_content' ); 
			
			do_action('madara_text_chapter_content', $post);
			?>
			
			<div id="text-chapter-toolbar">
				<a href="#"><i class="icon ion-md-settings"></i></a>
			</div>

			<?php do_action( 'madara_after_text_chapter_content' ); ?>

		<?php } elseif ( $chapter_type == 'video' ) { ?>

			<?php do_action( 'madara_before_video_chapter_content' ); ?>

            <div class="chapter-video-frame" id="chapter-video-frame">
				<?php $wp_manga->chapter_video_content($post, $server); ?>
            </div>

			<?php do_action( 'madara_after_video_chapter_content' ); ?>

		<?php } ?>

		<?php

	}

	wp_reset_postdata();
