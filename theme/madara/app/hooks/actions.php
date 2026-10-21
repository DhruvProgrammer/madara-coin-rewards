<?php
use App\Madara;

add_action('madara_chapter_reading_actions_list_items', 'madara_chapter_reading_actions_add_darkmode_button');

function madara_chapter_reading_actions_add_darkmode_button(){
	?>
	<li><a href="#" class="wp-manga-action-button" data-action="toggle-contrast" title="<?php esc_html_e('Toggle Dark/Light Mode', 'madara');?>"><i class="icon ion-md-contrast"></i></a></li>
	<?php
}


add_filter( 'madara_meta_query_args', 'madara_adult_content_filter_metaquery');
	
function madara_adult_content_filter_metaquery($meta_query){
	if(Madara::getOption('manga_adult_content','off') == 'on'){
		if(isset($_COOKIE['wpmanga-adult']) && $_COOKIE['wpmanga-adult'] == '1') {
			// exclude adult content
			$meta_query[] = array(
				'key'     => 'manga_adult_content',
				'value'   => ''
			);
		} else {
			// I'm an adult, family-mode is off, then show all content as default
		}
	}
	return $meta_query;
}

add_filter('wp_manga_chapters_per_page', 'wp_manga_chapters_per_page');
function wp_manga_chapters_per_page($chapters_per_page){
	$val = Madara::getOption('manga_detail_chapters_per_page', 0);
	
	return $val;
}

/**
 * Reward Ads
 */
add_action('wp_manga_after_content_archive', 'wp_manga_after_content_archive_ads_link');
function wp_manga_after_content_archive_ads_link($manga_id){
	$cookie_ads_clicked = isset($_COOKIE[$manga_id . '-0-ads-clicked']) ? $_COOKIE[$manga_id . '-0-ads-clicked'] : '';

	if($cookie_ads_clicked == '1'){
		return;
	}

	$ads_clicked_expired = apply_filters('ads_clicked_expired_after_minutes', 5);
	
	$ads_link = get_post_meta($manga_id, 'manga_ads_link', true);
	if($ads_link){
		echo '<a data-expired="' . $ads_clicked_expired . '" data-url="' . get_permalink() . '" data-manga="' . $manga_id . '" data-chapter="0" class="reward_ads" href="' . esc_url($ads_link) . '">ADS</a>';
	}
	
}

add_filter('wp_manga_next_chapter_link_html', 'wp_manga_next_chapter_link_html', 10, 4);
add_filter('wp_manga_prev_chapter_link_html', 'wp_manga_prev_chapter_link_html', 10, 4);
function wp_manga_next_chapter_link_html($html, $next_link, $next_chap, $manga_id){
	
	$cookie_ads_clicked = isset($_COOKIE[$manga_id . '-' . $next_chap['chapter_id'] . '-ads-clicked']) ? $_COOKIE[$manga_id . '-' . $next_chap['chapter_id'] . '-ads-clicked'] : '';

	if($cookie_ads_clicked == '1'){
		return $html;
	}

	$chapter_metas = $next_chap['chapter_metas'] ? unserialize($next_chap['chapter_metas']) : array();

	$ads_link = isset($chapter_metas['ads_link']) ? $chapter_metas['ads_link'] : "";
	if($ads_link){
		return '<a data-expired="' . $ads_clicked_expired . '" data-url="' . $next_link . '" data-manga="' . $manga_id . '" data-chapter="' . $next_chap['chapter_id'] . '" class="btn next_page reward_ads" href="' . esc_url($ads_link) . '">' . esc_html__( 'Next', WP_MANGA_TEXTDOMAIN ) . '</a>';
	}

	return $html;
}

function wp_manga_prev_chapter_link_html($html, $prev_link, $prev_chap, $manga_id){
	
	$cookie_ads_clicked = isset($_COOKIE[$manga_id . '-' . $prev_chap['chapter_id'] . '-ads-clicked']) ? $_COOKIE[$manga_id . '-' . $prev_chap['chapter_id'] . '-ads-clicked'] : '';

	if($cookie_ads_clicked == '1'){
		return $html;
	}

	$chapter_metas = $prev_chap['chapter_metas'] ? unserialize($prev_chap['chapter_metas']) : array();

	$ads_link = isset($chapter_metas['ads_link']) ? $chapter_metas['ads_link'] : "";
	if($ads_link){
		return '<a data-expired="' . $ads_clicked_expired . '" data-url="' . $prev_link . '" data-manga="' . $manga_id . '" data-chapter="' . $prev_chap['chapter_id'] . '" class="btn prev_page reward_ads" href="' . esc_url($ads_link) . '">' . esc_html__( 'Prev', WP_MANGA_TEXTDOMAIN ) . '</a>';
	}

	return $html;
}

add_action('wp_manga_after_chapter_name', 'wp_manga_after_chapter_name_ads_link', 10, 2);
function wp_manga_after_chapter_name_ads_link($chapter, $manga_id){
	$cookie_ads_clicked = isset($_COOKIE[$manga_id . '-' . $chapter['chapter_id'] . '-ads-clicked']) ? $_COOKIE[$manga_id . '-' . $chapter['chapter_id'] . '-ads-clicked'] : '';

	if($cookie_ads_clicked == '1'){
		return;
	}

	$chapter_metas = $chapter['chapter_metas'] ? unserialize($chapter['chapter_metas']) : array();

	$ads_link = isset($chapter_metas['ads_link']) ? $chapter_metas['ads_link'] : "";
	if($ads_link){
		global $wp_manga_functions;
		$chapter_url = $wp_manga_functions->build_chapter_url( $manga_id, $chapter );

		$ads_clicked_expired = apply_filters('ads_clicked_expired_after_minutes', 5);
		
		echo '<a data-expired="' . $ads_clicked_expired . '" data-url="' . $chapter_url . '" data-manga="' . $manga_id . '" data-chapter="' . $chapter['chapter_id'] . '" class="reward_ads" href="' . esc_url($ads_link) . '">ADS</a>';
	}
}


// Admin configure ads link for each chapter
add_action('madara_chapter_modal_after_chapter_meta', 'wp_manga_chapter_edit_ads_link');
function wp_manga_chapter_edit_ads_link(){
	?>
	<div class="wp-manga-chapter-ads-link block">
		<label class="input-label"><strong><?php esc_html_e( 'Preload Ads Link', WP_MANGA_TEXTDOMAIN ); ?></strong></label>

		<input type="text" id="chapter-ads-link">
		<div class="desc"><?php echo esc_html__( 'Ads Link that will be opened when users go to this chapter', WP_MANGA_TEXTDOMAIN ); ?></div>
	</div>
	<?php
}

add_filter( 'wp_manga_save_chapter_args', 'wp_manga_admin_chapter_save_ads_link', 10, 1 );
function wp_manga_admin_chapter_save_ads_link($chapter_args){
	global $wp_manga_chapter;

	$chapter_metas = $wp_manga_chapter->get_chapter_meta($_POST['chapterID']);
	if(!$chapter_metas){
		$chapter_metas = array();
	}

	$chapterCreatingTriggerData = isset($_POST['chapterCreatingTriggerData']) && $_POST['chapterCreatingTriggerData'] != '' ? $_POST['chapterCreatingTriggerData'] : '';
	if ( $chapterCreatingTriggerData != '' ) {

		$chapterCreatingTriggerData = stripslashes( $chapterCreatingTriggerData );
		if ( ! is_object( $chapterCreatingTriggerData ) && ! is_array( $chapterCreatingTriggerData ) ) {
			$chapterCreatingTriggerData = json_decode( $chapterCreatingTriggerData, true );
		}
		if ( is_object( $chapterCreatingTriggerData ) && property_exists( $chapterCreatingTriggerData, 'ads_link' ) ) {
			$ads_link = $chapterCreatingTriggerData->ads_link;
			
		} else if ( isset( $chapterCreatingTriggerData['ads_link'] ) ) {
			$ads_link = $chapterCreatingTriggerData['ads_link'];
		}

		if ( ( $ads_link !== '' && $ads_link !== null ) ) {
			
			$chapter_metas['ads_link'] = $ads_link;
			$chapter_args['update']['chapter_metas'] = serialize($chapter_metas);
		}

	}

	return $chapter_args;
}

add_action( 'wp_manga_get_chapter', 'wp_manga_admin_chapter_get_ads_link', 10, 3 );

function wp_manga_admin_chapter_get_ads_link( $chapter_data, $chapter_id, $chapter_type ) {
	global $wp_manga_chapter;

	$chapter_metas = $wp_manga_chapter->get_chapter_meta($chapter_id);
	if(!$chapter_metas){
		$chapter_metas = array();
	}

	if(isset($chapter_metas['ads_link'])){
		$chapter_data['ads_link'] = $chapter_metas['ads_link'];
	}

	return $chapter_data;
}
