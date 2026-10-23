<?php
/**
 * Madara theme integration.
 *
 * Lets the plugin adopt the site's accent colour and dark-mode scheme
 * without touching the theme's own styles.
 *
 * @package MCR
 */

namespace MCR\Integrations;

use MCR\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Reads theme options and exposes them to the plugin's CSS.
 */
class Madara {

	/**
	 * Whether the theme bridge has already printed.
	 *
	 * @var bool
	 */
	private static $printed = false;

	/**
	 * Prints the theme bridge styles.
	 *
	 * @return void
	 */
	public static function print_theme_bridge() {
		if ( self::$printed ) {
			return;
		}

		self::$printed = true;

		$accent  = self::accent();
		$accent2 = self::accent_secondary();

		if ( '' === $accent && '' === $accent2 ) {
			return;
		}

		$css = '';

		if ( '' !== $accent ) {
			$css .= ':root{--mcr-accent:' . $accent . ';}';
		}

		if ( '' !== $accent2 ) {
			$css .= ':root{--mcr-accent-2:' . $accent2 . ';}';
		}

		if ( '' === $css ) {
			return;
		}

		// The CSS is built from theme options and a strict hex whitelist.
		echo '<style id="mcr-theme-bridge">' . wp_strip_all_tags( $css ) . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Returns the site's accent colour as a sanitised hex value.
	 *
	 * @return string Empty string when unavailable.
	 */
	public static function accent() {
		if ( ! class_exists( '\App\Madara' ) ) {
			return '';
		}

		$value = \App\Madara::getOption( 'main_color', '' );

		return self::sanitize_hex( $value );
	}

	/**
	 * Returns the site's secondary accent colour as a sanitised hex value.
	 *
	 * @return string Empty string when unavailable.
	 */
	public static function accent_secondary() {
		if ( ! class_exists( '\App\Madara' ) ) {
			return '';
		}

		$value = \App\Madara::getOption( 'main_color_end', '' );

		return self::sanitize_hex( $value );
	}

	/**
	 * Whether the site is using the theme's dark colour scheme.
	 *
	 * Madara's class names are inverted relative to their meaning:
	 * body_schema 'dark' produces the `text-ui-light` body class.
	 *
	 * @return string 'dark', 'light' or an empty string when unknown.
	 */
	public static function color_scheme() {
		if ( ! class_exists( '\App\Madara' ) ) {
			return '';
		}

		$schema = \App\Madara::getOption( 'body_schema', 'light' );

		return 'dark' === $schema ? 'dark' : 'light';
	}

	/**
	 * Returns a sanitized hex colour.
	 *
	 * @param mixed $value Raw value.
	 * @return string Hex colour or an empty string.
	 */
	public static function sanitize_hex( $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$value = trim( $value );

		if ( '' === $value ) {
			return '';
		}

		if ( ! preg_match( '/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $value ) ) {
			return '';
		}

		return strtolower( $value );
	}

	/**
	 * Registers the page-shell rules for a plugin storefront page.
	 *
	 * Only the pages the plugin was explicitly asked to run full width are
	 * affected. The sidebar is removed through the standard WordPress
	 * sidebar filter so the theme stops rendering it, and the content is
	 * allowed to use the full width via the theme's own no-sidebar class.
	 *
	 * @return void
	 */
	public static function register_layout_hooks() {
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
		add_filter( 'is_active_sidebar', array( __CLASS__, 'filter_sidebar' ), 10, 2 );
	}

	/**
	 * Whether the current request is a plugin page that runs full width.
	 *
	 * @return bool
	 */
	public static function is_full_width_page() {
		if ( is_admin() || ! function_exists( 'is_page' ) || ! is_page() ) {
			return false;
		}

		$page_id = get_queried_object_id();
		$target  = absint( Settings::get( 'pages.' . self::full_width_slug(), 0 ) );

		return $target > 0 && $page_id === $target;
	}

	/**
	 * Which plugin page opts into the full-width, no-sidebar layout.
	 *
	 * Only the Buy Coins storefront does. Every other plugin page keeps the
	 * theme's normal layout so nothing else on the site changes.
	 *
	 * @return string Setting slug, e.g. `buy_coins`.
	 */
	private static function full_width_slug() {
		/**
		 * Filters which plugin page renders full width with no sidebar.
		 *
		 * @param string $slug Page settings key. Default: buy_coins.
		 */
		return (string) apply_filters( 'mcr_full_width_page', 'buy_coins' );
	}

	/**
	 * Adds the theme's no-sidebar body classes on the storefront page.
	 *
	 * @param array $classes Existing body classes.
	 * @return array
	 */
	public static function body_class( $classes ) {
		if ( ! self::is_full_width_page() ) {
			return $classes;
		}

		$classes = is_array( $classes ) ? $classes : array();

		// Madara / most e-commerce themes key off these; adding several
		// keeps the full-width behaviour working across theme versions.
		$classes[] = 'mcr-no-sidebar';
		$classes[] = 'no-sidebar';
		$classes[] = 'page-template-no-sidebar';

		return array_values( array_unique( $classes ) );
	}

	/**
	 * Reports the sidebar as inactive so the theme stops rendering it.
	 *
	 * This only fires on the storefront page, so widgets elsewhere on the
	 * site are untouched.
	 *
	 * @param bool   $is_active Whether the sidebar has widgets.
	 * @param string $sidebar_id Sidebar id.
	 * @return bool
	 */
	public static function filter_sidebar( $is_active, $sidebar_id ) {
		if ( self::is_full_width_page() ) {
			return false;
		}

		return $is_active;
	}
}
