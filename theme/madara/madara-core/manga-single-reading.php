<?php
	/**
	 * The Template for Manga Chapter Reading page
	 *
	 * This template can be overridden by copying it to your-child-theme/madara-core/manga-single-reading.php
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
	
	$manga_id  = get_the_ID();
	$reading_chapter = function_exists('madara_permalink_reading_chapter') ? madara_permalink_reading_chapter() : false;
	
	if(!$reading_chapter){
		 // support Madara Core before 1.6
		 if($chapter_slug = get_query_var('chapter')){
			global $wp_manga_functions;
			$reading_chapter = $wp_manga_functions->get_chapter_by_slug( $manga_id, $chapter_slug );
		 }
		 
		 if(!$reading_chapter){
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			get_template_part( 404 ); exit();
		 }
	}

	$cur_chap = $reading_chapter['chapter_slug'];

	$wp_manga           = madara_get_global_wp_manga();
	$wp_manga_functions = madara_get_global_wp_manga_functions();
	

	$style    = isset( $_GET['style'] ) ? $_GET['style'] : $wp_manga_functions->get_reading_style();

	$wp_manga_settings = get_option( 'wp_manga_settings' );
	$related_manga     = isset( $wp_manga_settings['related_manga'] ) ? $wp_manga_settings['related_manga'] : null;
	if($related_manga == 1){
		$related_manga = Madara::getOption( 'manga_reading_related', 'on' ) == 'on' ? 1 : 0;
	}
	$madara_single_sidebar      = madara_get_theme_sidebar_setting();
	$madara_breadcrumb          = Madara::getOption( 'manga_single_breadcrumb', 'on' );
	$manga_reading_discussion   = Madara::getOption( 'manga_reading_discussion', 'on' );
	$manga_reading_social_share = Madara::getOption( 'manga_reading_social_share', 'off' );
	
	$chapter_type = get_post_meta( $manga_id, '_wp_manga_chapter_type', true );
	$is_text_chapter_right_sidebar = ($madara_single_sidebar != 'full' && $chapter_type == 'text' && Madara::getOption( 'manga_reading_text_sidebar', 'on' ) == 'on') ? true : false;
	
	$madara_sidebar_size = Madara::getOption( 'sidebar_size', 4 );
	$madara_sidebar_size_class = 'col-sm-' . $madara_sidebar_size;
	$madara_main_col_class = 'col-sm-' . (12 - $madara_sidebar_size);

	if ( $madara_single_sidebar == 'full' || $is_text_chapter_right_sidebar ) {
		$main_col_class = 'sidebar-hidden col-12';
	} else {
		$main_col_class = 'main-col col-12 ' . $madara_main_col_class;
	}
	
	get_header();

?>
    <div class="c-page-content style-1 reading-content-wrap chapter-type-<?php echo esc_attr($chapter_type == '' ? 'manga' : $chapter_type);?>" data-site-url="<?php echo home_url( '/' ); ?>">
        <div class="content-area">
            <div class="container">
                <div class="row">
                    <div class="main-col <?php echo esc_attr($is_text_chapter_right_sidebar ? $madara_main_col_class : "col-md-12");?> col-12 sidebar-hidden">
						<?php 
						
						$madara_show_chapter_heading = Madara::getOption( 'chapter_heading', 'full' );
						
						if($madara_show_chapter_heading != 'off'){?>
						<h1 id="chapter-heading">
							<?php
							
							if($madara_show_chapter_heading == 'full'){
								$vol_name = '';
								if($reading_chapter['volume_id']){
									global $wp_manga_volume;
									$chapter_vol = $wp_manga_volume->get_volume_by_id( $manga_id, $reading_chapter['volume_id'] );
									$vol_name = $chapter_vol['volume_name'];
								}
								
								$manga = get_post($manga_id); 
								
								if($vol_name == ''){
									$text_format = esc_html_x("%s - %s", "Chapter Heading Format [Manga Title] - [Chapter Title]", "madara");
									echo sprintf($text_format, esc_html($manga->post_title), esc_html($reading_chapter['chapter_name']));
								} else {
									$text_format = esc_html_x("%s - %s - %s", "Chapter Heading Format [Manga Title] - [Vol Name] - [Chapter Title]", "madara");
									echo sprintf($text_format, esc_html($manga->post_title), $vol_name, esc_html($reading_chapter['chapter_name']));
								}		
							} else {
								echo esc_html($reading_chapter['chapter_name']);
							}
							
							?>
						</h1>
						<?php }  ?>
						
                        <!-- container & no-sidebar-->
                        <div class="main-col-inner">
                            <div class="c-blog-post">
                                <div class="entry-header header" id="manga-reading-nav-head" data-position="header" data-chapter="<?php echo esc_attr($cur_chap);?>" data-id="<?php echo esc_attr(get_the_ID());?>"><?php $wp_manga->manga_nav( 'header' ); ?></div>
                                <div class="entry-content">
                                    <div class="entry-content_wrap">

                                        <div class="read-container">

											<?php echo apply_filters( 'madara_ads_before_content', madara_ads_position( 'ads_before_content', 'body-top-ads' ) ); ?>
											
                                            <div class="reading-content">
												<input type="hidden" id="wp-manga-current-chap" data-id="<?php echo esc_attr($reading_chapter['chapter_id']);?>" value="<?php echo esc_attr($cur_chap);?>"/>
												<?php 
                                                
                                                global $post;
                                                
                                                if( !$post->post_password || ($post->post_password && !post_password_required()) ){
												
                                                    /**
                                                     * If alternative_content is empty, show default content
                                                     **/
                                                    $alternative_content = apply_filters('wp_manga_chapter_content_alternative', '');
                                                    
                                                    if(!$alternative_content){
                                                        do_action('wp_manga_before_chapter_content', $cur_chap, $manga_id);
                                                        
                                                        do_action('wp_manga_chapter_content', $cur_chap, $manga_id);
                                                        
                                                        do_action('wp_manga_after_chapter_content', $cur_chap, $manga_id);
                                                    } else {
                                                        echo madara_filter_content($alternative_content);
                                                    }
                                                
                                                } else {
                                                    // show the password form
                                                    the_content();
                                                }
												
												?>

                                            </div>
										

											<?php echo apply_filters( 'madara_ads_after_content', madara_ads_position( 'ads_after_content', 'body-bottom-ads' ) ); ?>

                                        </div>


                                    </div>
                                </div>
								<div class="entry-header footer" id="manga-reading-nav-foot" data-position="footer" data-id="<?php echo esc_attr(get_the_ID());?>"><?php $wp_manga->manga_nav( 'footer' ); ?></div>
                            </div>

							<?php if ( class_exists( 'APSS_Class' ) && $manga_reading_social_share == 'on' ) {

								$madara_sharing_text     = apply_filters( 'manga_reading_sharing_text', esc_html__( 'SHARE THIS MANGA', 'madara' ) );
								$madara_sharing_networks = 'facebook, twitter, google-plus, pinterest, linkedin, digg';
								$madara_sharing_networks = apply_filters( 'manga_reading_sharing_networkds', $madara_sharing_networks );
								echo do_shortcode( "[apss_share share_text='$madara_sharing_text' networks='$madara_sharing_networks' counter='1' total_counter='1' http_count='1']" );

							} ?>

							<?php if ( $manga_reading_discussion == 'on' && !$is_text_chapter_right_sidebar ) { ?>
                                <div class="row <?php echo esc_attr( $madara_single_sidebar == 'left' ? 'sidebar-left' : ''); ?>">
                                    <div class="<?php echo esc_attr( $main_col_class ); ?>">
                                        <!-- comments-area -->
										<?php do_action( 'wp_manga_discussion' ); ?>
										<!-- END comments-area -->
                                    </div>

									<?php
										if ( $madara_single_sidebar != 'full' ) {
											?>
                                            <div class="sidebar-col <?php echo esc_html($madara_sidebar_size_class); ?>">
												<?php get_sidebar(); ?>
                                            </div>
										<?php }
									?>

                                </div>
							<?php } ?>

							<?php
								$minimal_reading_page = Madara::getOption( 'minimal_reading_page', 'off' );
								
								if ( $related_manga == 1 && $minimal_reading_page == 'off' ) {
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
					if ( $madara_single_sidebar != 'full' && $is_text_chapter_right_sidebar ) {
						?>
						<div class="sidebar-col text-sidebar <?php echo esc_html($madara_sidebar_size_class); ?> col-12">
							<?php get_sidebar(); ?>
							
							<!-- comments-area -->
							<?php 
							if($manga_reading_discussion == 'on') 
								do_action( 'wp_manga_discussion' ); ?>
							<!-- END comments-area -->
						</div>
					<?php }
					?>
                </div>
            </div>
        </div>
    </div>
<?php do_action( 'after_manga_single' ); ?>
<?php

	get_footer();
