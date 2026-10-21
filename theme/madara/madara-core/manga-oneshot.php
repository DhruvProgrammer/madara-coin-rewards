<?php

	/**
	 * The Template for Manga Detail page in Oneshot layout
	 *
	 * This template can be overridden by copying it to your-child-theme/madara-core/manga-oneshot.php
	 *
	 * HOWEVER, on occasion Madara will need to update template files and you
	 * (the theme developer) will need to copy the new files to your theme to
	 * maintain compatibility. We try to do this as little as possible, but it does
	 * happen. When this occurs the version of the template file will be bumped and
	 * we will list any important changes on our theme release logs.
	 * @package Madara
	 * @version 1.7.2.2
	 */

	get_header();

	use App\Madara;

	$wp_manga           = madara_get_global_wp_manga();
	$wp_manga_functions = madara_get_global_wp_manga_functions();
    
	$post_id            = get_the_ID();
    
    $madara_single_sidebar      = madara_get_theme_sidebar_setting();
	$madara_breadcrumb          = Madara::getOption( 'manga_single_breadcrumb', 'on' );
	$manga_profile_background   = madara_output_background_options( 'manga_profile_background' );
	$manga_single_summary       = Madara::getOption( 'manga_single_summary', 'on' );

	$wp_manga_settings = get_option( 'wp_manga_settings' );
	$related_manga     = isset( $wp_manga_settings['related_manga'] ) ? $wp_manga_settings['related_manga'] : null;
    $info_summary_layout = Madara::getOption('manga_profile_summary_layout', 1);

	$madara_sidebar_size = Madara::getOption( 'sidebar_size', 4 );
	$madara_sidebar_size_class = 'col-md-' . $madara_sidebar_size . ' col-sm-' . $madara_sidebar_size;
	$madara_main_col_class = 'col-md-' . (12 - $madara_sidebar_size) . ' col-sm-' . (12 - $madara_sidebar_size);
?>


<?php do_action( 'before_manga_single' ); ?>
<div <?php post_class();?>>
<div class="profile-manga summary-layout-<?php echo esc_attr($info_summary_layout);?>" style="<?php echo esc_attr( $manga_profile_background != '' ? $manga_profile_background : 'background-image: url(' . madara_get_default_background() . ');' ); ?>">
    <div class="container">
        <div class="row">
            <div class="col-12 col-sm-12 col-md-12">
				<?php
					if ( $madara_breadcrumb == 'on' ) {
						get_template_part( 'madara-core/manga', 'breadcrumb' );
					}
				?>
                <div class="post-title">
                    <?php madara_manga_title_badges_html( $post_id, 1 ); ?>
                    
                    <h1>
						<?php echo esc_html( get_the_title() ); ?>
                    </h1>
                </div>
                <div class="tab-summary <?php echo has_post_thumbnail() ? '' : esc_attr( 'no-thumb' ); ?>">
                    <?php 
                    
                    $thumb_size         = array( 193, 278 );
                    set_query_var('thumb_size', $thumb_size);
                    set_query_var('post_id', $post_id);
                    get_template_part( '/madara-core/single/info-summary', $info_summary_layout); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="c-page-content style-1">
    <div class="content-area">
        <div class="container">
            <div class="row <?php echo esc_attr( $madara_single_sidebar == 'left' ? 'sidebar-left' : '' ) ?>">
                <div class="main-col <?php echo esc_attr( $madara_single_sidebar !== 'full' && ( is_active_sidebar( 'manga_single_sidebar' ) || is_active_sidebar( 'main_sidebar' ) ) ? ' ' . $madara_main_col_class : 'col-md-12 col-sm-12 sidebar-hidden' ) ?>">
                    <!-- container & no-sidebar-->
                    <div class="main-col-inner">
                        <div class="c-page">
                            <!-- <div class="c-page__inner"> -->
                            <div class="c-page__content">
								
								<?php get_template_part( '/madara-core/single/reading-oneshot'); ?>
                            </div>
                            <!-- </div> -->
                        </div>
						<?php edit_post_link(esc_html__('Edit This Manga', 'madara'));?>
						
                        <!-- comments-area -->
						<?php 
						
						do_action( 'wp_manga_discussion' ); ?>
                        <!-- END comments-area -->

						<?php

							if ( $related_manga == 1 ) {
								get_template_part( '/madara-core/manga', 'related' );
							}

							if ( class_exists( 'WP_Manga' ) ) {
                                $setting = Madara::getOption('manga_single_tags_post', 'info');
                                if($setting == 'both' || $setting == 'bottom'){
                                    $GLOBALS['wp_manga']->wp_manga_get_tags();
                                }
							}
						?>

                    </div>
                </div>

				<?php
					if ( $madara_single_sidebar != 'full' && ( is_active_sidebar( 'main_sidebar' ) || is_active_sidebar( 'manga_single_sidebar' ) ) ) {
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

<?php do_action( 'after_manga_single' ); ?>
</div>
<?php get_footer();