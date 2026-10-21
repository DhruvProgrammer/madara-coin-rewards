<?php
/**
 * Default page creation and lookup.
 *
 * @package MCR
 */

namespace MCR;

defined( 'ABSPATH' ) || exit;

/**
 * Creates and resolves the plugin's pages.
 */
class Pages {

	/**
	 * The page slugs the plugin manages.
	 *
	 * @return array
	 */
	public static function slugs() {
		return array( 'checkin', 'dashboard', 'ads', 'buy_coins', 'transactions' );
	}

	/**
	 * The shortcode used by each page.
	 *
	 * @return array
	 */
	public static function shortcodes() {
		return array(
			'checkin'     => 'mcr_checkin',
			'dashboard'   => 'mcr_dashboard',
			'ads'         => 'mcr_ads',
			'buy_coins'   => 'mcr_buy_coins',
			'transactions' => 'mcr_transactions',
		);
	}

	/**
	 * The default page titles.
	 *
	 * @return array
	 */
	public static function titles() {
		return array(
			'checkin'     => __( 'Daily Rewards', 'madara-coin-rewards' ),
			'dashboard'   => __( 'My Coins', 'madara-coin-rewards' ),
			'ads'         => __( 'Watch Ads & Earn', 'madara-coin-rewards' ),
			'buy_coins'   => __( 'Buy Coins', 'madara-coin-rewards' ),
			'transactions' => __( 'Coin History', 'madara-coin-rewards' ),
		);
	}

	/**
	 * Creates the plugin pages on activation.
	 *
	 * Existing pages are reused: if a page already uses the plugin's
	 * shortcode, it is assigned rather than a duplicate being created.
	 *
	 * @return void
	 */
	public static function create_default_pages() {
		$shortcodes = self::shortcodes();
		$titles     = self::titles();
		$settings   = array();

		foreach ( self::slugs() as $slug ) {
			$existing = self::find_existing_page( $shortcodes[ $slug ] );

			if ( $existing ) {
				$settings[ 'pages.' . $slug ] = (int) $existing;
				continue;
			}

			$page_id = wp_insert_post(
				array(
					'post_title'     => $titles[ $slug ],
					'post_name'      => 'mcr-' . str_replace( '_', '-', $slug ),
					'post_content'   => '[' . $shortcodes[ $slug ] . ']',
					'post_status'    => 'publish',
					'post_type'      => 'page',
					'comment_status' => 'closed',
					'ping_status'    => 'closed',
				)
			);

			if ( $page_id && ! is_wp_error( $page_id ) ) {
				$settings[ 'pages.' . $slug ] = (int) $page_id;
			}
		}

		if ( ! empty( $settings ) ) {
			Settings::update( $settings );
			Settings::flush_cache();
		}
	}

	/**
	 * Finds an existing page that uses a given shortcode.
	 *
	 * @param string $shortcode Shortcode name.
	 * @return int Page id or 0.
	 */
	public static function find_existing_page( $shortcode ) {
		$pages = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				's'              => $shortcode,
			)
		);

		foreach ( (array) $pages as $page_id ) {
			$content = get_post_field( 'post_content', $page_id );

			if ( $content && false !== strpos( $content, '[' . $shortcode ) ) {
				return (int) $page_id;
			}
		}

		return 0;
	}

	/**
	 * Returns the URL of a plugin page.
	 *
	 * @param string $slug Page slug.
	 * @return string Empty string when the page does not exist.
	 */
	public static function url( $slug ) {
		$page_id = (int) Settings::get( 'pages.' . $slug, 0 );

		if ( ! $page_id ) {
			return '';
		}

		$url = get_permalink( $page_id );

		return $url ? $url : '';
	}
}
