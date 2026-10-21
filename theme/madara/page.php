<?php

	/**
	 * The Template for displaying all single page.
	 *
	 * @package madara
	 */

	get_header();

	$madara_page_sidebar = madara_get_theme_sidebar_setting();
	$page_custom_css = \App\Madara::getOption('page_custom_css', '');
	$container = \App\Madara::getOption('page_container', '');
	
	$page_custom_css = apply_filters('madara_page_custom_css_class', $page_custom_css);	

	$madara_sidebar_size = \App\Madara::getOption( 'sidebar_size', 4 );
	$madara_sidebar_size_class = 'col-md-' . $madara_sidebar_size . ' col-sm-' . $madara_sidebar_size;
	$madara_main_col_class = 'col-md-' . (12 - $madara_sidebar_size) . ' col-sm-' . (12 - $madara_sidebar_size);
?>

    <div class="c-page-content style-2">
        <div class="content-area <?php echo esc_attr($page_custom_css);?>">
            <div class="container<?php echo $container;?>">

                <div class="row <?php echo esc_attr( $madara_page_sidebar == 'left' ? 'sidebar-left' : ''); ?>">

                    <div class="<?php echo esc_attr( $madara_page_sidebar !== 'full' && is_active_sidebar( 'main_sidebar' ) ? 'main-col ' . $madara_main_col_class : 'col-md-12 col-sm-12'); ?>">

						<?php get_template_part( 'html/main-bodytop' ); ?>

                        <div class="main-col-inner">

							<?php while ( have_posts() ) : the_post(); ?>


								<?php get_template_part( 'html/single/content', 'page' ); ?>

								<?php
								// If comments are open or we have at least one comment, load up the comment template
								$madara_pagecomments = \App\Madara::getOption( 'page_comments', 'on' );

								if ( $madara_pagecomments == 'on' && ( comments_open() || '0' != get_comments_number() ) ) :
									comments_template();
								endif;
								?><?php endwhile; // end of the loop. ?>

                        </div>

						<?php get_template_part( 'html/main-bodybottom' ); ?>

                    </div>

					<?php
						if ( $madara_page_sidebar != 'full' && is_active_sidebar( 'main_sidebar' ) ) {
							?>
                            <div class="sidebar-col <?php echo esc_html($madara_sidebar_size_class); ?>">
								<?php get_sidebar(); ?>
                            </div>
						<?php }
					?>


                </div>
            </div>
        </div>
    </div>


<?php

	get_footer();
