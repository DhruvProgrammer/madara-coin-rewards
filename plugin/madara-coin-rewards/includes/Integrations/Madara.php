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
	 * Sanitizes a colour value to a strict hex triplet.
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
}
