<?php

	/**
	 * The Template for printing out a manga property (Summary/Synopsis) in Manga Detail page
	 *
	 * This template can be overridden by copying it to your-child-theme/madara-core/single/info-summary.php
	 *
	 * HOWEVER, on occasion Madara will need to update template files and you
	 * (the theme developer) will need to copy the new files to your theme to
	 * maintain compatibility. We try to do this as little as possible, but it does
	 * happen. When this occurs the version of the template file will be bumped and
	 * we will list any important changes on our theme release logs.
	 * @package Madara
	 * @version 1.7.2.2
	 */
	 
	 ?>
<?php if ( has_post_thumbnail() ) { ?>
    <div class="summary_image">
        <a href="<?php echo get_the_permalink(); ?>">
            <?php echo madara_thumbnail( $thumb_size ); ?>
        </a>
    </div>
<?php } ?>
<div class="summary_content_wrap">
    <div class="summary_content">
        <div class="post-content">
            <?php get_template_part( 'html/ajax-loading/ball-pulse' ); ?>
            
            <?php do_action('wp-manga-manga-properties', $post_id);?>
            
            <?php do_action('wp-manga-after-manga-properties', $post_id);?>
            
        </div>
        <div class="post-status">
        
            <?php do_action('wp-manga-manga-status', $post_id);?>

        </div>
        
        <?php
        
        $is_oneshot = is_manga_oneshot($post_id);
    
        if(!$is_oneshot){                            
            set_query_var( 'manga_id', $post_id );
            get_template_part('madara-core/single/quick-buttons');
        }
        
        ?>
    </div>
</div>