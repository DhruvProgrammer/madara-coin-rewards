<?php

/**
	 * The Template for content of a manga chapter, paged layout (ie. 1 or few images in 1 page), in a Chapter Reading page
	 *
	 * This template can be overridden by copying it to your-child-theme/madara-core/reading-content/content-reading-paged.php
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
$manga_id = get_the_ID();

global $wp_manga_functions, $wp_manga_database;
			
$sort_setting = $wp_manga_database->get_sort_setting();

$sort_by    = $sort_setting['sortBy'];
$sort_order = $sort_setting['sort'];

$user_id = null;
if ( is_user_logged_in() ) {
	$user_id = get_current_user_id();
}

$manga_reading_style = $wp_manga_functions->get_reading_style( $user_id, $manga_id );

$list_chapters = $wp_manga_functions->get_latest_chapters( $manga_id, null, 1, false, $sort_by, $sort_order );
$latest_chapter = false;
if ( ! empty( $list_chapters ) ) {
	$latest_chapter = $list_chapters[0];
	$c_url = $wp_manga_functions->build_chapter_url( $manga_id, $latest_chapter, $manga_reading_style );
}
?>
<tr>
	<td class="genre"><?php
			$genres = $wp_manga_functions->get_manga_genres( $manga_id );
			$items = explode(",",$genres);
			if(count($items) > 3) 
				echo implode(",", array_slice($items,0, 3)) . "...";
			else 
				echo $genres;
		?>
	</td>
	<td class="title"><?php echo '<a href="' . get_permalink() . '" title="' . get_the_title() . '">' . get_the_title() . '</a>';?></td>
	<td class="release <?php echo apply_filters('wp_manga_chapter_item_class','', $latest_chapter, $manga_id);?>"><?php
	
			if($latest_chapter){
				do_action('wp_manga_before_chapter_name',$latest_chapter, $manga_id);
				
				echo '<a href="' . esc_url($c_url) . '" title="' . get_the_title() . ' - '. esc_attr($latest_chapter['chapter_name']) . '">' . wp_kses_post($latest_chapter['chapter_name']) . '</a>';
				
				do_action('wp_manga_after_chapter_name',$latest_chapter, $manga_id);
			}
		?></td>
	<td class="author"><?php
			$authors = $wp_manga_functions->get_manga_authors( $manga_id );
			echo trim($authors);
		?></td>
	<td class="time"><?php if($latest_chapter){
		if ( ! empty( $latest_chapter['date'] ) ) {
			$time_diff = $wp_manga_functions->get_time_diff( $latest_chapter['date'] );

			if ( $time_diff ) {

				$time_diff = apply_filters( 'madara_archive_chapter_date', $time_diff, $latest_chapter['chapter_id'], $latest_chapter['date'], $c_url );

				?>
				<span class="post-on font-meta">
					<?php echo wp_kses_post( $time_diff ); ?>
				</span>
				<?php
			}
		}
	}?></td>
</tr>