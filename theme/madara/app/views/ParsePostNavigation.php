<?php

	/**
	 * ParsePostNavigation Class
	 */

	namespace App\Views;
    
    use App\Madara;

	class ParsePostNavigation {
		public function __construct() {

		}

		public function render( $echo = 1 ) {
            $nav_same_term = Madara::getOption('archive_navigation_same_term', 'off');
            
			$previous = ( is_attachment() ) ? get_post( get_post()->post_parent ) : get_adjacent_post( ($nav_same_term == 'off' ? false : true), '', true, ($nav_same_term == 'on' ? Madara::getOption('archive_navigation_term_taxonomy', 'category') : 'category') );

			$next = get_adjacent_post( ($nav_same_term == 'off' ? false : true), '', false, ($nav_same_term == 'on' ? Madara::getOption('archive_navigation_term_taxonomy', 'category') : 'category') );

			if ( ! $next && ! $previous ) {
				return;
			}

			$get_previous_post = $previous;
			$get_next_post     = $next;
			$thumb_size        = array( 254, 140 );
            
            $reverse = Madara::getOption('single_reverse_nav', 'off');

			?>

            <nav class="navigation paging-navigation <?php echo (esc_html($reverse) == 'on' ? 'reverse' : ''); ?>">
                <div class="nav-links">
					<?php if ( is_object( $get_next_post ) ) { ?>
                        <div class="nav-next nav-button">
                            <a class="link" href="<?php echo get_permalink( $get_next_post->ID ) ?>"><?php echo esc_html__( 'NEXT POST', 'madara' ); ?></a>
                            <div class="c-blog__thumbnail c-image-hover">
                                <a href="<?php echo get_permalink( $get_next_post->ID ) ?>"> <?php echo get_the_post_thumbnail( $get_next_post->ID, $thumb_size, array( 'class' => 'img-responsive' ) ) ?> </a>
                            </div>
                            <div class="c-blog__summary">
                                <div class="post-title font-title">
                                    <h6>
                                        <a href="<?php echo get_permalink( $get_next_post->ID ) ?>"><?php echo get_the_title( $get_next_post->ID ); ?></a>
                                    </h6>
                                </div>
                            </div>
                        </div>
					<?php } ?>

					<?php if ( is_object( $get_previous_post ) ) { ?>
                        <div class="nav-previous nav-button">
                            <a class="link" href="<?php echo get_permalink( $get_previous_post->ID ) ?>"><?php echo esc_html__( 'PREV POST', 'madara' ); ?></a>
                            <div class="c-blog__thumbnail c-image-hover">
                                <a href="<?php echo get_permalink( $get_previous_post->ID ) ?>"> <?php echo get_the_post_thumbnail( $get_previous_post->ID, $thumb_size, array( 'class' => 'img-responsive' ) ) ?> </a>
                            </div>
                            <div class="c-blog__summary">
                                <div class="post-title font-title">
                                    <h6>
                                        <a href="<?php echo get_permalink( $get_previous_post->ID ) ?>"><?php echo get_the_title( $get_previous_post->ID ); ?></a>
                                    </h6>
                                </div>
                            </div>
                        </div>
					<?php } ?>
                </div>
                <!-- .nav-links -->
            </nav>

			<?php
		}
	}