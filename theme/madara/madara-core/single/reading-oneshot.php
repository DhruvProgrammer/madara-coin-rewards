<?php


	/**
	 * The Template for content of a Oneshot manga, in Manga Detail Page
	 *
	 * This template can be overridden by copying it to your-child-theme/madara-core/single/reading-oneshot.php
	 *
	 * HOWEVER, on occasion Madara will need to update template files and you
	 * (the theme developer) will need to copy the new files to your theme to
	 * maintain compatibility. We try to do this as little as possible, but it does
	 * happen. When this occurs the version of the template file will be bumped and
	 * we will list any important changes on our theme release logs.
	 * @package Madara
	 * @version 1.7.2.2
	 */
	 
	 
use App\Madara;

// get first chapter of this oneshot manga
global $wp_manga_functions, $post;

$post_id = get_the_ID();

if( !$post->post_password || ($post->post_password && !post_password_required()) ){    
    $chapters = $wp_manga_functions->get_latest_chapters($post_id, false, 1);

    if($chapters){
        $chapter = $chapters[0];
        
        // trick to tell others that this is a reading chapter
        set_query_var('chapter', $chapter['chapter_slug']);
        global $__CURRENT_CHAPTER;
        $__CURRENT_CHAPTER = $wp_manga_functions->get_chapter_by_slug( $post_id, $chapter['chapter_slug'] );
        
        // need a full data chapter object
        $chapter  = $wp_manga_functions->get_single_chapter( $post_id, $chapter['chapter_id'] );
        
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
        
        $is_lazy_load = Madara::getOption( 'lazyload', 'off' ) == 'on' ? true : false;
        if ( $is_lazy_load ) {
            $lazyload = 'wp-manga-chapter-img img-responsive lazyload effect-fade';
        } else {
            $lazyload = 'wp-manga-chapter-img';
        }
        
        $need_button_fullsize = false;
        
        $lazyload_dfimg = apply_filters( 'madara_image_placeholder_url', get_parent_theme_file_uri( '/images/dflazy.jpg' ), 0 );
    ?>
    <div id="oneshot-reader">
        <div class="row">        
            <?php
            
            /**
            * If alternative_content is empty, show default content
            **/
            $alternative_content = apply_filters('wp_manga_chapter_content_alternative', '');

            if(!$alternative_content){
                $i = 0;
                $total_page = count($chapter['storage'][ $in_use ]['page']);
                
                foreach ( $chapter['storage'][ $in_use ]['page'] as $page => $link ) {

                    $madara_reading_list_total_item = count( $chapter['storage'][ $in_use ]['page'] );

                    $host = $chapter['storage'][ $in_use ]['host'];
                    $src  = apply_filters('wp_manga_chapter_image_url', $host . $link['src'], $host, $link['src'], $post_id, $name);
                    
                    if($src != ''){
                        $i++;
                    
                        do_action( 'madara_before_chapter_image_wrapper', $page, $madara_reading_list_total_item ); ?>
                    <div class="col-lg-2 col-md-3 col-6">
                        <div class="image-item">

                            <?php do_action( 'madara_before_chapter_image', $page, $madara_reading_list_total_item );
                            ?>
                            <a href="<?php echo esc_url( $src ); ?>" data-lightbox="chapter-images">
                            <img id="image-<?php echo esc_attr( $page ); ?>" <?php if($is_lazy_load){ echo 'src="' . esc_url($lazyload_dfimg) . '" data-src="'; } else { echo 'src="';}?><?php echo esc_url( $src ); ?>" class="<?php echo esc_attr( $lazyload ); ?>">
                            </a>
                            <?php 
                            
                            do_action( 'madara_after_chapter_image', $page, $madara_reading_list_total_item ); ?>
                        </div>
                        <?php do_action( 'madara_after_chapter_image_wrapper', $page, $madara_reading_list_total_item ); ?>
                    </div>
                    <?php   
                        if($i%6 == 0 || $i == $total_page){
                            // end of 1 row
                            ?>
            </div>
                            <?php
                            if($i < $total_page){
                                // open new row
                                ?>
            <div class="row">
                                <?php
                            }
                        }
                        
                    }
                }
            } else {
                ?>
                <div class="col-12">
                    <?php echo madara_filter_content($alternative_content); ?>
                </div>
            </div> <!-- end row -->
                <?php
            }
        ?>
    </div>
        <?php
    }
} else {
    // need password
?>
    <div id="oneshot-reader">
        <div class="row">
            <div class="col-12">
                <?php the_content(); ?>
            </div>
        </div>
    </div>
<?php
}
?>