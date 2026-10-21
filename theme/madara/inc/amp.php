<?php

// AMP support functions
add_filter( 'amp_post_template_data', 'madara_amp_post_template_data', 10, 2);
function madara_amp_post_template_data($data, $post){
    
    $var = get_query_var( 'chapter' );
	
	$manga_id = get_the_ID();
	
    $reading_chapter = false;
	if($var != '' && $manga_id){
		global $wp_manga_functions;
		$reading_chapter = $wp_manga_functions->get_chapter_by_slug( $manga_id, $var );
	}
    
    $data['chapter'] = $reading_chapter;
    
    return $data;
}

add_filter('amp_post_article_header_meta', 'madara_amp_post_article_header_meta');
function madara_amp_post_article_header_meta( $array = [] ){
	if(get_post_type(get_the_ID()) == 'wp-manga'){
		$array = [];
		
		$var = get_query_var( 'chapter' );
	
        $manga_id = get_the_ID();
        
        $reading_chapter = false;
        if($var != '' && $manga_id){
            global $wp_manga_functions;
            $reading_chapter = $wp_manga_functions->get_chapter_by_slug( $manga_id, $var );
        }
	
		if($reading_chapter){
			$cur_chap = $reading_chapter['chapter_slug'];
		} else {
			$array[] = 'featured-image';
			$array[] = 'meta-manga';
		}
	}
	
	return $array;
}

add_filter('amp_post_article_footer_meta', 'madara_amp_post_article_footer_meta');
function madara_amp_post_article_footer_meta( $array = [] ){
	if(get_post_type(get_the_ID()) == 'wp-manga'){
		$array = [];
        
        $var = get_query_var( 'chapter' );
	
        $manga_id = get_the_ID();
        
        $reading_chapter = false;
        if($var != '' && $manga_id){
            global $wp_manga_functions;
            $reading_chapter = $wp_manga_functions->get_chapter_by_slug( $manga_id, $var );
        }
	
		if($reading_chapter){
			$cur_chap = $reading_chapter['chapter_slug'];
		} else {
			$array[] = 'manga-chapters';
		}
	}
	
	return $array;
}

add_action( 'wp_enqueue_scripts', function() {
    if ( function_exists( 'is_amp_endpoint' ) && is_amp_endpoint() ) {
        wp_register_style( 'amp-style',
    		get_parent_theme_file_uri('/css/amp-style.css'),
    		array(),
    		'20191111',
    		'all' );
		wp_enqueue_style( 'amp-style' );
    }
} );

add_action('amp_post_template_head', 'madara_amp_head');
function madara_amp_head( $amp ){
	?>
	<script async custom-element="amp-selector" src="https://cdn.ampproject.org/v0/amp-selector-0.1.js"></script>
	<script async custom-element="amp-bind" src="https://cdn.ampproject.org/v0/amp-bind-0.1.js"></script>
	<script async custom-element="amp-carousel" src="https://cdn.ampproject.org/v0/amp-carousel-0.1.js"></script>
	<?php

}

add_filter( 'madara_new_chapter_tag', 'madara_amp_new_tag', 10, 6);
function madara_amp_new_tag($html, $date, $chapter_id, $chapter_date, $chapter_link, $time_diff){
	if ( function_exists( 'is_amp_endpoint' ) && is_amp_endpoint() ) {
	return '<span class="c-new-tag"><a href="' . esc_url( $chapter_link ) . '" title="' . esc_attr( $time_diff ) . '"><amp-img src="' . esc_url( get_parent_theme_file_uri() ) . '/images/new.gif' . '" alt="' . esc_attr( $time_diff ) . '"></a></span>';
	}
	
	return $html;
}