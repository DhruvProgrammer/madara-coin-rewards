<?php

	/**
	 * The Template for printing out the Quick Buttons (Read First, Read Last, Continue) in Manga Detail page
	 *
	 * This template can be overridden by copying it to your-child-theme/madara-core/single/quikc-buttons.php
	 *
	 * HOWEVER, on occasion Madara will need to update template files and you
	 * (the theme developer) will need to copy the new files to your theme to
	 * maintain compatibility. We try to do this as little as possible, but it does
	 * happen. When this occurs the version of the template file will be bumped and
	 * we will list any important changes on our theme release logs.
	 * @package Madara
	 * @version 2.0.1
	 */

use App\Madara;
global $wp_manga_functions, $wp_manga_chapter;

$manga_id = get_query_var('manga_id');
			
$current_read_chapter = 0;
if ( is_user_logged_in() ) {
	$user_id = get_current_user_id();
	$history = madara_get_current_reading_chapter($user_id, $manga_id);
	if($history){
		$current_read_chapter = $history['c'];
	}
}

$init_links_enabled = Madara::getOption('init_links_enabled', 'on') == 'on' ? true : false;
$chapters_count = $wp_manga_chapter->get_chapters_count($manga_id);
if($init_links_enabled && $chapters_count > 0){ ?>

<div id="init-links" class="nav-links">
	<?php 
	$has_current_reading = false;
	$reading_style     = $wp_manga_functions->get_reading_style();
	if($current_read_chapter) {
		
		global $wp_manga_chapter;
		
		$current_chapter = $wp_manga_chapter->get_chapter_by_id( $manga_id, $current_read_chapter );
		if($current_chapter){
			// this check to ensure a reading chapter is still there (ie. not deleted)
			$current_chapter_link = $wp_manga_functions->build_chapter_url( $manga_id, $current_chapter, $reading_style );
		?>
		<a href="<?php echo esc_url($current_chapter_link);?>" class="c-btn c-btn_style-1" title="<?php echo esc_attr($current_chapter['chapter_name']);?>"><?php esc_html_e('Continue reading', 'madara');?></a>
		<?php
			$has_current_reading = true;
		}
	}

	if(!$has_current_reading){
		global $wp_manga_database;
		global $sort_setting;
		
		if(!isset($sort_setting)){
			$sort_setting = $wp_manga_database->get_sort_setting();
		}

		$sort_order = $sort_setting['sort'];
		$manga = $wp_manga_functions->get_all_chapters( $manga_id, $sort_order );
		$first_chap = false;
		$last_chap = false;
		foreach($manga as $vol_key => $vol){
			if(!$first_chap){
				$first_chap = $vol['chapters'][0];
			}
			
			$last_chap = end($vol['chapters']);
		}

		$first_link      = $wp_manga_functions->build_chapter_url( $manga_id, $first_chap, $reading_style );
		$last_link      = $wp_manga_functions->build_chapter_url( $manga_id, $last_chap, $reading_style );

		$btn_first_class = apply_filters('wp_manga_chapter_item_class','', $first_chap, $manga_id);
		$btn_last_class = apply_filters('wp_manga_chapter_item_class','', $last_chap, $manga_id);
		
		if($sort_order == 'asc'){
			?>
			<span class="<?php echo $btn_first_class;?>"><a href="<?php echo $first_link;?>" id="btn-read-first" class="c-btn c-btn_style-1">
			<?php esc_html_e('Read First', 'madara');?></a></spa>
			<span class="<?php echo $btn_last_class;?>"><a href="<?php echo $last_link;?>" id="btn-read-last" class="c-btn c-btn_style-1"><?php esc_html_e('Read Last', 'madara');?></a></span>
			<?php
		} else {
			?>
			<span class="<?php echo $btn_last_class;?>"><a href="<?php echo $last_link;?>" id="btn-read-last" class="c-btn c-btn_style-1">
			<?php esc_html_e('Read First', 'madara');?></a></span>
			<span class="<?php echo $btn_first_class;?>"><a href="<?php echo $first_link;?>" id="btn-read-first" class="c-btn c-btn_style-1"><?php esc_html_e('Read Last', 'madara');?></a></span>
			<?php
		}
	}?>
</div>

<?php }?>