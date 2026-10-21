<?php
	/**
	 * @package madara
	 */

	$madara_postMeta       = new App\Views\ParseMeta();
	$madara_showtags       = \App\Madara::getOption( 'single_tags', 'on' );
	$madara_page_meta_tags = \App\Madara::getOption( 'page_meta_tags', 'on' );
	$madara_page_title = \App\Madara::getOption( 'page_title', 'on' );
	$thumb_size            = 'full';
?>


<div id="post-<?php the_ID(); ?>" <?php post_class( 'c-blog-post' ); ?>>
	<?php if($madara_page_title == 'on' || $madara_page_meta_tags == 'on'){?>
    <div class="entry-header">
        <div class="entry-header_wrap">
			<?php if($madara_page_title == 'on'){?>
            <div class="entry-title">
                <h1 class="item-title h2"><?php the_title(); ?></h1>
            </div>
			<?php } ?>

			<?php if ( $madara_page_meta_tags == 'on' ) {
				$madara_postMeta->renderPostMeta();
			} ?>
        </div>
    </div>
	<?php } ?>

	<?php if ( has_excerpt() ) { ?>
        <div class="c-blog__excerpt">
			<?php the_excerpt(); ?>
        </div>
	<?php } ?>

	<?php if ( has_post_thumbnail() ) { ?>
        <div class="c-blog__thumbnail">
            <a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>">
				<?php echo madara_thumbnail( $thumb_size ); ?>
            </a>
        </div>
	<?php } ?>

    <div class="entry-content">
        <div class="entry-content_wrap">
			<?php the_content(); ?>
        </div>
    </div>

	<?php if ( $madara_showtags == 'on' && has_tag() ): ?>
        <div class="item-tags">
			<?php the_tags( '<ul class="list-inline">
                <li>
                    <h4 class="heading">' . esc_html__( 'Tags: ', 'madara' ) . '</h4>
                </li><li>', '</li> <li>', '</li></ul>' );
			?>
        </div>
	<?php endif; ?>

	<?php
		wp_link_pages( array(
			'before' => '<div class="page-links">' . esc_html__( 'Pages:', 'madara' ),
			'after'  => '</div>',
		) );
	?>

	<?php 
    
    if(current_user_can('manage_options')){
        edit_post_link( esc_html__( 'Edit', 'madara' ), '<span class="edit-link">', '</span>' );
    }    ?>


</div>