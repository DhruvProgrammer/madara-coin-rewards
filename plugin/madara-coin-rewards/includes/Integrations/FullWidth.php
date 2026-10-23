<?php
/**
 * The full-width layout for the plugin's own pages.
 *
 * The rewards pages are the plugin's own content. A theme sidebar full of
 * recent comments and trending posts is noise there, and it steals the
 * width that the wallet, the reward calendar and the ad panel need.
 *
 * The sidebar is removed the WordPress way: the widgets are taken out of
 * the registered sidebars FOR THIS REQUEST ONLY. The theme then finds no
 * active sidebar, so it drops the sidebar column and gives the content the
 * full width by itself. No theme file is touched, and no other page of
 * the site is affected.
 *
 * @package MCR
 */

namespace MCR\Integrations;

use MCR\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Removes the theme sidebar from the plugin's pages.
 */
class FullWidth {

	/**
	 * The plugin's page slugs.
	 *
	 * @var array
	 */
	const SLUGS = array( 'checkin', 'dashboard', 'ads', 'buy_coins', 'transactions' );

	/**
	 * Registers the hooks.
	 *
	 * @return void
	 */
	public static function register() {
		/*
		 * Late, so another plugin that fills a sidebar on purpose has
		 * the last word.
		 */
		add_filter( 'sidebars_widgets', array( __CLASS__, 'remove_sidebar_widgets' ), 99 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );

		/*
		 * A safety net for themes whose page template does not drop the
		 * sidebar column just because the sidebar is empty: the column is
		 * hidden and the content takes the full width anyway.
		 */
		add_action( 'wp_head', array( __CLASS__, 'print_fallback_css' ), 20 );
	}

	/**
	 * Prints a tiny fallback stylesheet for themes that keep the sidebar
	 * column in the DOM even when the sidebar is empty.
	 *
	 * @return void
	 */
	public static function print_fallback_css() {
		if ( ! self::is_reward_page() ) {
			return;
		}

		echo '<style id="mcr-full-width">' . "\n";
		echo '.mcr-full-width .sidebar-col, .mcr-full-width .sidebar { display: none !important; }' . "\n";
		echo '.mcr-full-width .main-col { flex: 0 0 100%; max-width: 100%; }' . "\n";
		echo '</style>' . "\n";
	}

	/**
	 * Empties the registered sidebars on the plugin's pages.
	 *
	 * The option that stores the widgets is never touched: the change
	 * lives in the request only, so the site's widgets are exactly as
	 * they were the next time the widgets screen is opened.
	 *
	 * @param array $sidebars The registered sidebars.
	 * @return array
	 */
	public static function remove_sidebar_widgets( $sidebars ) {
		if ( ! is_array( $sidebars ) || ! self::is_reward_page() ) {
			return $sidebars;
		}

		foreach ( $sidebars as $id => $widgets ) {
			/*
			 * The numeric "array_version" key and the inactive-widget
			 * list are not sidebars. The inactive list is where
			 * WordPress keeps the widgets the reader has taken out of
			 * a sidebar, and it must never be emptied.
			 */
			if ( ! is_string( $id ) || 'wp_inactive_widgets' === $id ) {
				continue;
			}

			/*
			 * Only the sidebars that carry the theme's main content
			 * widgets are cleared. Footer and bottom widget areas are
			 * left alone, so a save handler triggered during the request
			 * can never wipe them.
			 */
			$is_main = ( 'main_sidebar' === $id || 'sidebar-1' === $id || 'primary' === $id || 'sidebar' === $id )
				|| ( false !== stripos( $id, 'main' ) && false !== stripos( $id, 'sidebar' ) );

			if ( ! $is_main ) {
				continue;
			}

			$sidebars[ $id ] = array();
		}

		return $sidebars;
	}

	/**
	 * Adds a body class to the plugin's pages.
	 *
	 * @param array $classes The body classes.
	 * @return array
	 */
	public static function body_class( $classes ) {
		if ( ! is_array( $classes ) ) {
			return $classes;
		}

		if ( self::is_reward_page() ) {
			$classes[] = 'mcr-full-width';
		}

		return $classes;
	}

	/**
	 * Whether the request is for one of the plugin's pages.
	 *
	 * A page counts when it is one of the pages the plugin created, or
	 * when it uses one of the plugin's shortcodes - a site that puts the
	 * check-in panel on its own landing page gets the same treatment.
	 *
	 * @return bool
	 */
	public static function is_reward_page() {
		/*
		 * The widgets screen and the customizer both read the sidebar
		 * list to SAVE it. The change is never applied there.
		 */
		if ( is_admin() || ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) ) {
			return false;
		}

		if ( function_exists( 'is_customize_preview' ) && is_customize_preview() ) {
			return false;
		}

		if ( ! function_exists( 'is_singular' ) || ! is_singular() ) {
			return false;
		}

		$post_id = (int) get_queried_object_id();

		if ( ! $post_id ) {
			return false;
		}

		foreach ( self::SLUGS as $slug ) {
			if ( (int) Settings::get( 'pages.' . $slug, 0 ) === $post_id ) {
				return true;
			}
		}

		$post = get_post( $post_id );

		if ( ! $post || ! isset( $post->post_content ) ) {
			return false;
		}

		foreach ( self::SLUGS as $slug ) {
			if ( has_shortcode( $post->post_content, 'mcr_' . $slug ) ) {
				return true;
			}
		}

		return false;
	}
}
