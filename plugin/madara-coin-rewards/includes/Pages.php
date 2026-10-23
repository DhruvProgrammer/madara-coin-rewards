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
	 * The default URL slugs for newly created pages.
	 *
	 * Every plugin page lives under a `premium-` namespace so the storefront
	 * URLs are consistent and do not advertise the plugin's internal prefix:
	 *
	 *   /premium              - Buy Coins
	 *   /premium-dashboard    - My Coins
	 *   /premium-checkin      - Daily Rewards
	 *   /premium-ads          - Watch Ads
	 *   /premium-transactions - Coin History
	 *
	 * The slug is only applied when a page has to be created; an existing
	 * installation is migrated by maybe_migrate_slugs().
	 *
	 * @return array
	 */
	public static function default_slugs() {
		$slugs = array(
			'checkin'      => 'premium-checkin',
			'dashboard'    => 'premium-dashboard',
			'ads'          => 'premium-ads',
			'buy_coins'    => 'premium',
			'transactions' => 'premium-transactions',
		);

		/**
		 * Filters the default URL slug used when the plugin creates a page.
		 *
		 * @param array $slugs Map of plugin page key => URL slug.
		 */
		$slugs = (array) apply_filters( 'mcr_page_slugs', $slugs );

		return $slugs;
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
		$defaults   = self::default_slugs();
		$settings   = array();

		foreach ( self::slugs() as $slug ) {
			$existing = self::find_existing_page( $shortcodes[ $slug ] );

			if ( $existing ) {
				$settings[ 'pages.' . $slug ] = (int) $existing;
				continue;
			}

			$post_name = isset( $defaults[ $slug ] ) ? (string) $defaults[ $slug ] : '';
			$post_name = \sanitize_title( $post_name );

			if ( '' === $post_name ) {
				$post_name = 'mcr-' . str_replace( '_', '-', $slug );
			}

			$page_id = wp_insert_post(
				array(
					'post_title'     => $titles[ $slug ],
					'post_name'      => $post_name,
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
	 * Migrates an existing Buy Coins page onto the configured slug.
	 *
	 * The storefront is served at `/premium`. An installation created
	 * before that slug existed still has `mcr-buy-coins`; this renames it
	 * in place (keeping the same page id, so every saved setting and link
	 * to it stays valid) and remembers the old slug so the old URL can be
	 * redirected rather than 404.
	 *
	 * Runs at most once per install, tracked by an option.
	 *
	 * @return bool Whether the page was renamed.
	 */
	public static function maybe_migrate_slugs() {
		$defaults = self::default_slugs();
		$redirects = array();

		foreach ( self::slugs() as $key ) {
			$page_id = \absint( \MCR\Settings::get( 'pages.' . $key, 0 ) );

			if ( ! $page_id || 'page' !== get_post_type( $page_id ) ) {
				continue;
			}

			$target  = isset( $defaults[ $key ] ) ? \sanitize_title( (string) $defaults[ $key ] ) : '';
			$current = (string) get_post_field( 'post_name', $page_id );

			if ( '' === $target || $current === $target ) {
				continue;
			}

			// Never steal a slug that already belongs to another post.
			$conflict = get_page_by_path( $target );

			if ( $conflict && (int) $conflict->ID !== $page_id ) {
				$target = $target . '-' . $page_id;
			}

			$result = wp_update_post(
				array(
					'ID'        => $page_id,
					'post_name' => $target,
				),
				true
			);

			if ( is_wp_error( $result ) ) {
				continue;
			}

			// Remember the previous slug so the old URL can redirect to the new one.
			$redirects[ $current ] = (int) $page_id;
		}

		if ( empty( $redirects ) ) {
			return false;
		}

		update_option( 'mcr_slug_redirects', $redirects );

		return true;
	}

	/**
	 * Registers the legacy slug redirects.
	 *
	 * Anyone who bookmarked or linked to an old URL is sent to the new one
	 * with a 301, so renaming costs no traffic and no SEO equity.
	 *
	 * @return void
	 */
	public static function register_slug_redirect() {
		$redirects = (array) \get_option( 'mcr_slug_redirects', array() );

		if ( empty( $redirects ) ) {
			return;
		}

		add_action( 'template_redirect', array( __CLASS__, 'maybe_redirect_slug' ) );
	}

	/**
	 * Performs a legacy-slug redirect.
	 *
	 * The old slug 404s by the time this runs, so the match is made on the
	 * requested path rather than on the resolved page.
	 *
	 * @return void
	 */
	public static function maybe_redirect_slug() {
		if ( is_admin() || wp_doing_ajax() ) {
			return;
		}

		$redirects = (array) \get_option( 'mcr_slug_redirects', array() );

		if ( empty( $redirects ) ) {
			return;
		}

		$request = isset( $_SERVER['REQUEST_URI'] ) ? \sanitize_text_field( \wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$request = trim( (string) \wp_parse_url( $request, PHP_URL_PATH ), '/' );

		if ( '' === $request ) {
			return;
		}

		// Match the path's last segment, ignoring any nested page structure.
		$segments = explode( '/', $request );
		$last     = \sanitize_title( (string) end( $segments ) );

		if ( ! isset( $redirects[ $last ] ) ) {
			return;
		}

		$page_id = (int) $redirects[ $last ];
		$target  = get_permalink( $page_id );

		if ( $target ) {
			wp_safe_redirect( $target, 301 );
			exit;
		}
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
