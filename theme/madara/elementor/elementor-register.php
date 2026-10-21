<?php
/**
 * Plugin Name: Elementor List Widget
 * Description: List widget for Elementor.
 * Plugin URI:  https://elementor.com/
 * Version:     1.0.0
 * Author:      Elementor Developer
 * Author URI:  https://developers.elementor.com/
 * Text Domain: elementor-list-widget
 *
 * Requires Plugins: elementor
 * Elementor tested up to: 3.21.0
 * Elementor Pro tested up to: 3.21.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Register List Widget.
 *
 * Include widget file and register widget class.
 *
 * @since 1.0.0
 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
 * @return void
 */
function register_madaraelementor_widget( $widgets_manager ) {

	require_once( __DIR__ . '/widgets/manga-slider.php' );
	require_once( __DIR__ . '/widgets/popular-slider.php' );
	require_once( __DIR__ . '/widgets/manga-search.php' );
	require_once( __DIR__ . '/widgets/manga-listing.php' );
	require_once( __DIR__ . '/widgets/manga-authors.php' );
	require_once( __DIR__ . '/widgets/posts.php' );
	require_once( __DIR__ . '/widgets/manga-history.php' );
	require_once( __DIR__ . '/widgets/manga-bookmark.php' );
	require_once( __DIR__ . '/widgets/manga-releases.php' );
	require_once( __DIR__ . '/widgets/manga-taxonomy.php' );
	require_once( __DIR__ . '/widgets/user-section.php' );
	require_once( __DIR__ . '/widgets/manga-recent.php' );
	require_once( __DIR__ . '/widgets/madara-logo.php' );
	require_once( __DIR__ . '/widgets/madara-familysafe.php' );
	require_once( __DIR__ . '/widgets/manga/madara-properties.php' );
	require_once( __DIR__ . '/widgets/manga/madara-chapters.php' );
	require_once( __DIR__ . '/widgets/manga/madara-related.php' );
	require_once( __DIR__ . '/widgets/manga/madara-reading.php' );
	require_once( __DIR__ . '/widgets/manga/madara-title.php' );
	require_once( __DIR__ . '/widgets/manga/madara-summary.php' );
	require_once( __DIR__ . '/widgets/manga/madara-thumb.php' );
	require_once( __DIR__ . '/widgets/manga/madara-comments.php' );

	$widgets_manager->register( new \Elementor_Manga_Slider() );
	$widgets_manager->register( new \Elementor_Manga_Popular_Slider() );
	$widgets_manager->register( new \Elementor_Manga_Search() );
	$widgets_manager->register( new \Elementor_Manga_Authors() );
	$widgets_manager->register( new \Elementor_WP_Posts() );
	$widgets_manager->register( new \Elementor_Manga_History() );
	$widgets_manager->register( new \Elementor_Manga_Bookmark() );
	$widgets_manager->register( new \Elementor_Manga_Releases() );
	$widgets_manager->register( new \Elementor_Madara_UserSection() );
	$widgets_manager->register( new \Elementor_Manga_Recent() );
	$widgets_manager->register( new \Elementor_Madara_SiteLogo() );
	$widgets_manager->register( new \Elementor_Madara_FamilySafe() );
	$widgets_manager->register( new \Elementor_Manga_Taxonomies() );
	$widgets_manager->register( new \Elementor_Manga_Listing() );
	
	$widgets_manager->register( new \Elementor_Madara_Properties() );
	$widgets_manager->register( new \Elementor_Madara_Chapters() );
	$widgets_manager->register( new \Elementor_Madara_Related() );
	$widgets_manager->register( new \Elementor_Madara_Reading() );
	$widgets_manager->register( new \Elementor_Madara_Title() );
	$widgets_manager->register( new \Elementor_Madara_Summary() );
	$widgets_manager->register( new \Elementor_Madara_Thumb() );
	$widgets_manager->register( new \Elementor_Madara_Comments() );
}

add_action( 'elementor/widgets/register', 'register_madaraelementor_widget' );

function madara_add_elementor_widget_categories( $elements_manager ) {

	$elements_manager->add_category(
		'madara',
		[
			'title' => esc_html__( 'Madara General Widgets', 'madara' ),
			'icon' => 'fa fa-plug',
		]
	);

	$elements_manager->add_category(
		'manga',
		[
			'title' => esc_html__( 'Madara Manga Elements', 'madara' ),
			'icon' => 'fa fa-plug',
		]
	);

}
add_action( 'elementor/elements/categories_registered', 'madara_add_elementor_widget_categories' );