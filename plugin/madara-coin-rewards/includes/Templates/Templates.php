<?php
/**
 * Frontend template registry, resolver and renderer.
 *
 * A page is identified by a slug (`checkin`, `dashboard`, `ads`, `buy-coins`,
 * `transactions`, `balance`). The renderer resolves a template file for that
 * slug and layout with the following precedence:
 *
 * 1. `get_stylesheet_directory() . '/madara-coin-rewards/'` (child theme)
 * 2. `get_template_directory() . '/madara-coin-rewards/'`   (parent theme)
 * 3. `MCR_PLUGIN_DIR . 'templates/'`                        (plugin)
 *
 * Layout files are flat: `checkin.php` is the default layout, `checkin-hero.php`
 * and `checkin-compact.php` are the alternatives. When a layout file is
 * missing the default file for the slug is used.
 *
 * Templates receive `$args` only: no database access, no queries, no business
 * logic. Escaping happens on output.
 *
 * @package MCR
 */

namespace MCR\Templates;

use MCR\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the plugin's frontend pages and partials.
 */
class Templates {

	/**
	 * Theme override directory, relative to the theme root.
	 */
	const THEME_DIR = 'madara-coin-rewards';

	/**
	 * Registered page definitions.
	 *
	 * @var array
	 */
	private static $pages = array();

	/**
	 * Definitions added at runtime through register().
	 *
	 * @var array
	 */
	private static $custom = array();

	/**
	 * Resolved path cache, keyed by "slug|layout".
	 *
	 * @var array
	 */
	private static $paths = array();

	/**
	 * Whether the coin sprite was already printed in this request.
	 *
	 * @var bool
	 */
	private static $sprite_done = false;

	/**
	 * How many times the page shell has been rendered in this request.
	 *
	 * Used to give the live region a document-unique id when a page
	 * renders the plugin more than once.
	 *
	 * @var int
	 */
	private static $render_count = 0;

	/**
	 * Registers a page definition, merging with the built-in one.
	 *
	 * @param string $slug       Page slug.
	 * @param array  $definition Definition: label, shortcode, default_layout,
	 *                           layouts, sections, lead.
	 * @return void
	 */
	public static function register( $slug, array $definition ) {
		$slug = self::normalize_slug( $slug );

		if ( '' === $slug ) {
			return;
		}

		$existing = self::built_in( $slug );

		if ( $existing ) {
			$definition = array_merge( $existing, $definition );
		}

		self::$custom[ $slug ] = $definition;
	}

	/**
	 * Returns the page registry.
	 *
	 * @return array
	 */
	public static function registry() {
		$registry = array();

		foreach ( self::built_in() as $slug => $definition ) {
			$registry[ $slug ] = isset( self::$custom[ $slug ] )
				? array_merge( $definition, self::$custom[ $slug ] )
				: $definition;
		}

		foreach ( self::$custom as $slug => $definition ) {
			if ( ! isset( $registry[ $slug ] ) ) {
				$registry[ $slug ] = $definition;
			}
		}

		/**
		 * Filters the registered frontend templates.
		 *
		 * @param array $registry Slug => definition map.
		 */
		return apply_filters( 'mcr_templates', $registry );
	}

	/**
	 * Returns one page definition.
	 *
	 * @param string $slug Page slug.
	 * @return array|null
	 */
	public static function definition( $slug ) {
		$registry = self::registry();
		$slug     = self::normalize_slug( $slug );

		return isset( $registry[ $slug ] ) ? $registry[ $slug ] : null;
	}

	/**
	 * Returns the layouts available for a page.
	 *
	 * @param string $slug Page slug.
	 * @return array Layout key => label.
	 */
	public static function layouts( $slug ) {
		$definition = self::definition( $slug );

		if ( ! $definition ) {
			return array( 'default' => __( 'Default', 'madara-coin-rewards' ) );
		}

		$layouts = isset( $definition['layouts'] ) && is_array( $definition['layouts'] )
			? $definition['layouts']
			: array();

		$layouts['default'] = isset( $layouts['default'] )
			? $layouts['default']
			: __( 'Default', 'madara-coin-rewards' );

		return $layouts;
	}

	/**
	 * Returns the default layout for a page.
	 *
	 * @param string $slug Page slug.
	 * @return string
	 */
	public static function default_layout( $slug ) {
		$definition = self::definition( $slug );
		$layout     = isset( $definition['default_layout'] ) ? sanitize_key( $definition['default_layout'] ) : 'default';
		$layouts    = self::layouts( $slug );

		return isset( $layouts[ $layout ] ) ? $layout : 'default';
	}

	/**
	 * Resolves the layout for a page.
	 *
	 * Precedence: explicit argument, then the stored setting, then the page
	 * default. Unknown layouts fall back to the default layout.
	 *
	 * @param string $slug      Page slug.
	 * @param string $requested Requested layout (may be empty).
	 * @return string
	 */
	public static function layout( $slug, $requested = '' ) {
		$slug    = self::normalize_slug( $slug );
		$layouts = self::layouts( $slug );
		$key     = str_replace( '-', '_', $slug );

		$chosen = '' !== (string) $requested
			? sanitize_key( (string) $requested )
			: sanitize_key( (string) Settings::get( 'template.layout_' . $key, '' ) );

		if ( '' === $chosen || ! isset( $layouts[ $chosen ] ) ) {
			$chosen = self::default_layout( $slug );
		}

		if ( ! isset( $layouts[ $chosen ] ) ) {
			$chosen = 'default';
		}

		return $chosen;
	}

	/**
	 * Normalizes a page slug (`buy_coins` and `buy-coins` are equivalent).
	 *
	 * @param string $slug Raw slug.
	 * @return string
	 */
	public static function normalize_slug( $slug ) {
		$slug = str_replace( '_', '-', strtolower( (string) $slug ) );
		$slug = preg_replace( '/[^a-z0-9\-]/', '', $slug );

		return is_string( $slug ) ? $slug : '';
	}

	/**
	 * Renders a page.
	 *
	 * @param string $slug Page slug.
	 * @param array  $args Template arguments.
	 * @return string
	 */
	public static function render( $slug, array $args = array() ) {
		$slug       = self::normalize_slug( $slug );
		$definition = self::definition( $slug );

		if ( ! $definition ) {
			return self::render_missing();
		}

		$layout = self::layout( $slug, isset( $args['layout'] ) ? $args['layout'] : '' );

		$args = wp_parse_args(
			$args,
			array(
				'slug'         => $slug,
				'layout'       => $layout,
				'lead'         => isset( $definition['lead'] ) ? $definition['lead'] : '',
				'show_header'  => true,
				'show_balance' => 'on' === Settings::toggle( 'template.show_balance_summary' ),
				'show_progress' => 'on' === Settings::toggle( 'template.show_progress_bars' ),
				'columns'      => max( 1, min( 4, absint( Settings::get( 'template.columns', 3 ) ) ) ),
				'user_id'      => get_current_user_id(),
				'urls'         => array(),
				'server_time'  => self::server_time(),
				'timezone'     => function_exists( 'wp_timezone_string' ) ? wp_timezone_string() : 'UTC',
			)
		);

		$args['slug']   = $slug;
		$args['layout'] = $layout;
		$args['urls']   = wp_parse_args( (array) $args['urls'], self::urls() );

		/**
		 * Filters the template arguments before rendering.
		 *
		 * @param array  $args Template arguments.
		 * @param string $slug Page slug.
		 */
		$args = apply_filters( 'mcr_get_template_args', $args, $slug );

		// A balance widget that says "0 coins / Sign in" is noise.
		if ( 'balance' === $slug && ! is_user_logged_in() ) {
			return '';
		}

		if ( ! is_user_logged_in() ) {
			$content = self::auth_empty();
		} else {
			$content = self::render_body( $slug, $layout, $args );
		}

		return self::shell( $slug, $layout, $args, $content );
	}

	/**
	 * Locates a template file for a slug and layout.
	 *
	 * @param string $slug   Page slug.
	 * @param string $layout Layout key.
	 * @param array  $args   Template arguments passed to the path filter.
	 * @return string Absolute path or an empty string.
	 */
	public static function locate( $slug, $layout, array $args = array() ) {
		$slug   = self::normalize_slug( $slug );
		$layout = sanitize_key( (string) $layout );
		$key    = $slug . '|' . $layout;

		if ( isset( self::$paths[ $key ] ) ) {
			return self::$paths[ $key ];
		}

		/**
		 * Filters the absolute template path for a page.
		 *
		 * @param string $path   Absolute path.
		 * @param array  $args   Template arguments.
		 * @param string $layout Layout key.
		 */
		$override = apply_filters( "mcr_template_{$slug}", '', $args, $layout );

		if ( is_string( $override ) && '' !== $override && is_readable( $override ) ) {
			self::$paths[ $key ] = $override;

			return $override;
		}

		$files = array( $slug . '.php' );

		if ( '' !== $layout && 'default' !== $layout ) {
			array_unshift( $files, $slug . '-' . $layout . '.php' );
		}

		$roots = array();

		if ( function_exists( 'get_stylesheet_directory' ) ) {
			$roots[] = get_stylesheet_directory();
		}

		if ( function_exists( 'get_template_directory' ) ) {
			$roots[] = get_template_directory();
		}

		$roots = array_unique( array_filter( $roots ) );

		foreach ( $roots as $root ) {
			foreach ( $files as $file ) {
				$path = self::normalize_path( $root . '/' . self::THEME_DIR . '/' . $file );

				if ( is_readable( $path ) ) {
					self::$paths[ $key ] = $path;

					return $path;
				}
			}
		}

		foreach ( $files as $file ) {
			$path = self::normalize_path( MCR_PLUGIN_DIR . 'templates/' . $file );

			if ( is_readable( $path ) ) {
				self::$paths[ $key ] = $path;

				return $path;
			}
		}

		self::$paths[ $key ] = '';

		return '';
	}

	/**
	 * Renders a partial from `templates/parts/{name}.php`.
	 *
	 * Partial arguments are extracted into scope; `$args` also stays
	 * available. A missing partial is never fatal.
	 *
	 * @param string $name Partial name.
	 * @param array  $args Partial arguments.
	 * @return string
	 */
	public static function part( $name, array $args = array() ) {
		$name = strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $name ) );
		$path = self::locate_part( $name, $args );

		if ( '' === $path ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				trigger_error( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
					esc_html( sprintf( 'Madara Coin & Rewards: template part "%s" was not found.', $name ) )
				);
			}

			return '';
		}

		$mcr_part_args = $args;

		ob_start();
		extract( $args, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		$args = $mcr_part_args;
		include $path;

		return (string) ob_get_clean();
	}

	/**
	 * Locates a partial file.
	 *
	 * @param string $name Partial name.
	 * @param array  $args Arguments passed to the path filter.
	 * @return string
	 */
	public static function locate_part( $name, array $args = array() ) {
		/**
		 * Filters the absolute path of a partial.
		 *
		 * @param string $path Absolute path.
		 * @param array  $args Partial arguments.
		 */
		$override = apply_filters( "mcr_template_part_{$name}", '', $args );

		if ( is_string( $override ) && '' !== $override && is_readable( $override ) ) {
			return $override;
		}

		$relative = self::THEME_DIR . '/parts/' . $name . '.php';
		$roots    = array();

		if ( function_exists( 'get_stylesheet_directory' ) ) {
			$roots[] = get_stylesheet_directory();
		}

		if ( function_exists( 'get_template_directory' ) ) {
			$roots[] = get_template_directory();
		}

		foreach ( array_unique( array_filter( $roots ) ) as $root ) {
			$path = self::normalize_path( $root . '/' . $relative );

			if ( is_readable( $path ) ) {
				return $path;
			}
		}

		$path = self::normalize_path( MCR_PLUGIN_DIR . 'templates/parts/' . $name . '.php' );

		return is_readable( $path ) ? $path : '';
	}

	/**
	 * Returns the shared page URLs.
	 *
	 * @return array
	 */
	public static function urls() {
		$pages = array(
			'checkin'      => 'pages.checkin',
			'dashboard'    => 'pages.dashboard',
			'ads'          => 'pages.ads',
			'buy_coins'    => 'pages.buy_coins',
			'transactions' => 'pages.transactions',
		);

		$urls = array();

		foreach ( $pages as $key => $setting ) {
			$page_id = absint( Settings::get( $setting, 0 ) );
			$url     = $page_id ? get_permalink( $page_id ) : '';

			$urls[ $key ] = $url ? $url : home_url( '/' );
		}

		$urls['buy']   = $urls['buy_coins'];
		$urls['login'] = wp_login_url( self::current_url() );
		if ( current_user_can( 'manage_options' ) ) {
			$urls['settings']    = admin_url( 'admin.php?page=mcr-settings' );
			// Packages are edited on their own screen, not under Settings.
			$urls['packages']    = admin_url( 'admin.php?page=mcr-packages' );
		} else {
			$urls['settings'] = '';
			$urls['packages'] = '';
		}

		return $urls;
	}

	/**
	 * Returns the current request URL.
	 *
	 * @return string
	 */
	public static function current_url() {
		$post_id = get_queried_object_id();
		$url     = $post_id ? get_permalink( $post_id ) : '';

		if ( ! $url ) {
			$url = home_url( '/' );
		}

		return $url;
	}

	/**
	 * Formats a `Y-m-d` calendar date without timezone drift.
	 *
	 * Calendar dates are not instants: they are formatted in UTC so a site in
	 * UTC-10 and a site in UTC+14 both see the day the row belongs to.
	 *
	 * @param string $date   Date in Y-m-d.
	 * @param string $format Optional date format.
	 * @return string
	 */
	public static function format_date( $date, $format = '' ) {
		$date = (string) $date;

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return $date;
		}

		$format = '' !== (string) $format ? (string) $format : get_option( 'date_format' );
		$stamp  = strtotime( $date . ' 00:00:00' );

		if ( ! $stamp ) {
			return $date;
		}

		if ( function_exists( 'wp_date' ) ) {
			return wp_date( $format, $stamp, new \DateTimeZone( 'UTC' ) );
		}

		return gmdate( $format, $stamp );
	}

	/**
	 * Returns a human label for a ledger transaction type.
	 *
	 * @param string $type Raw type.
	 * @return string
	 */
	public static function type_label( $type ) {
		$type = sanitize_key( (string) $type );

		$labels = array(
			'checkin'        => __( 'Daily check-in', 'madara-coin-rewards' ),
			'checkin_bonus'  => __( 'Streak bonus', 'madara-coin-rewards' ),
			'ad_reward'      => __( 'Ad reward', 'madara-coin-rewards' ),
			'purchase'       => __( 'Coin purchase', 'madara-coin-rewards' ),
			'purchase_bonus' => __( 'Purchase bonus', 'madara-coin-rewards' ),
			'spend'          => __( 'Spent', 'madara-coin-rewards' ),
			'admin_adjust'   => __( 'Admin adjustment', 'madara-coin-rewards' ),
			'refund'         => __( 'Refund', 'madara-coin-rewards' ),
			'referral'       => __( 'Referral', 'madara-coin-rewards' ),
			'reversal'       => __( 'Reversal', 'madara-coin-rewards' ),
		);

		/**
		 * Filters the human readable transaction type labels.
		 *
		 * @param array  $labels Type => label map.
		 * @param string $type   Requested type.
		 */
		$labels = apply_filters( 'mcr_transaction_type_labels', $labels, $type );

		if ( isset( $labels[ $type ] ) ) {
			return $labels[ $type ];
		}

		return ucwords( str_replace( '_', ' ', $type ) );
	}

	/**
	 * Renders a coin amount.
	 *
	 * @param int    $value Amount.
	 * @param string $size  Size: sm, lg or xl.
	 * @param array  $attrs Extra attributes (already sanitized keys).
	 * @return string
	 */
	/**
	 * Formats a signed coin amount for display.
	 *
	 * One formatter for every surface, so the same transaction reads
	 * identically in the table, the dashboard card and the feed. A debit
	 * uses the true minus sign (U+2212) rather than a hyphen, so the sign is
	 * unambiguous next to a number.
	 *
	 * @param int $amount Signed coin amount.
	 * @return string
	 */
	public static function amount_text( $amount ) {
		$amount = (int) $amount;

		$sign = $amount >= 0 ? '+' : "\xE2\x88\x92"; // U+2212 MINUS SIGN.

		return $sign . number_format_i18n( abs( $amount ) );
	}

	/**
	 * Returns a decorative coin icon.
	 *
	 * Wraps the same sprite symbol the amount renderer uses, but without a
	 * numeric label, so a hero or a feature card can show a coin at any size
	 * without the value baked into the artwork.
	 *
	 * @param int    $size   Pixel size for the coin (used as the CSS box size).
	 * @param string $class  Extra classes for the wrapper element.
	 * @param string $symbol Sprite symbol to draw (1, 5, 10, 50, 100).
	 * @return string Escaped-safe markup. The sprite itself is printed once
	 *                per request by the page shell.
	 */
	public static function coin_icon( $size = 32, $class = '', $symbol = '10' ) {
		$size  = max( 8, min( 512, (int) $size ) );
		$class = preg_replace( '/[^a-z0-9_\- ]/', '', strtolower( (string) $class ) );

		$symbols = array( '1', '5', '10', '50', '100' );
		$symbol  = in_array( (string) $symbol, $symbols, true ) ? (string) $symbol : '10';

		return '<span class="mcr-coin ' . esc_attr( $class ) . '" style="--mcr-coin-size:' . esc_attr( $size ) . 'px" aria-hidden="true" focusable="false">'
			. '<svg class="mcr-coin__svg" viewBox="0 0 32 32" focusable="false" aria-hidden="true">'
			. '<use href="#mcr-coin-symbol-' . esc_attr( $symbol ) . '"></use>'
			. '</svg></span>';
	}

	public static function amount( $value, $size = '', array $attrs = array() ) {
		$value  = (int) $value;
		$size   = sanitize_key( (string) $size );
		$suffix = in_array( $size, array( 'sm', 'lg', 'xl' ), true ) ? ' mcr-amount--' . $size : '';

		$extra = '';

		foreach ( $attrs as $attr => $attr_value ) {
			$attr = preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $attr ) );

			if ( '' === $attr || null === $attr_value || false === $attr_value ) {
				continue;
			}

			$extra .= ' ' . $attr . '="' . esc_attr( $attr_value ) . '"';
		}

		$coin = self::part(
			'coin',
			array(
				'value' => $value,
				'size'  => in_array( $size, array( 'sm', 'lg', 'xl' ), true ) ? $size : '',
			)
		);

		return '<span class="mcr-amount' . esc_attr( $suffix ) . '" data-mcr-amount="' . esc_attr( $value ) . '"' . $extra . '>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			. $coin // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			. '<span class="mcr-amount__value">' . esc_html( number_format_i18n( $value ) ) . '</span>'
			. '<span class="mcr-sr-only">' . esc_html__( 'coins', 'madara-coin-rewards' ) . '</span>'
			. '</span>';
	}

	/**
	 * Returns the ISO-8601 deadline N seconds from now, in the site timezone.
	 *
	 * @param int $seconds Seconds from now.
	 * @return string
	 */
	public static function deadline( $seconds ) {
		$seconds = max( 0, (int) $seconds );

		if ( class_exists( '\MCR\Container' ) ) {
			$clock = \MCR\Container::instance()->clock();

			return $clock->now()->modify( '+' . $seconds . ' seconds' )->format( 'c' );
		}

		return gmdate( 'c', time() + $seconds );
	}

	/**
	 * Returns the current server time as ISO-8601.
	 *
	 * @return string
	 */
	public static function server_time() {
		if ( class_exists( '\MCR\Container' ) ) {
			return \MCR\Container::instance()->clock()->now()->format( 'c' );
		}

		return gmdate( 'c' );
	}

	/**
	 * Builds the current week (Monday-first by site setting) from a calendar.
	 *
	 * The calendar is a flat list of trailing days; the strip needs the current
	 * week in reading order, including days that have not happened yet.
	 *
	 * @param array  $calendar  Calendar rows (date, claimed, streak, amount).
	 * @param string $today_key Today in Y-m-d.
	 * @return array Seven day arrays with `today` and `future` flags.
	 */
	public static function week( array $calendar, $today_key = '' ) {
		$map = array();

		foreach ( $calendar as $day ) {
			if ( is_array( $day ) && isset( $day['date'] ) ) {
				$map[ (string) $day['date'] ] = $day;
			}
		}

		$today_key = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $today_key ) ? (string) $today_key : gmdate( 'Y-m-d' );
		$stamp     = strtotime( $today_key . ' 00:00:00' );

		if ( ! $stamp ) {
			return array();
		}

		$start_of_week = function_exists( 'get_option' ) ? (int) get_option( 'start_of_week', 1 ) : 1;
		$start_of_week = max( 0, min( 6, $start_of_week ) );
		$offset        = ( (int) gmdate( 'w', $stamp ) - $start_of_week + 7 ) % 7;

		$week = array();

		for ( $i = 0; $i < 7; $i++ ) {
			$date  = gmdate( 'Y-m-d', $stamp - ( $offset * \DAY_IN_SECONDS ) + ( $i * \DAY_IN_SECONDS ) );
			$entry = isset( $map[ $date ] )
				? $map[ $date ]
				: array(
					'date'    => $date,
					'claimed' => false,
					'streak'  => 0,
					'amount'  => 0,
				);

			$entry['date']   = $date;
			$entry['today']  = ( $date === $today_key );
			$entry['future'] = ( $date > $today_key );

			$week[] = $entry;
		}

		return $week;
	}

	/**
	 * Returns the admin-post URL when a no-JS check-in handler exists.
	 *
	 * Rendering a form without a handler would send the visitor to a blank
	 * `admin-post.php` screen, so the fallback is only printed when the
	 * controller actually registered the action.
	 *
	 * @return string
	 */
	public static function checkin_form_action() {
		if ( function_exists( 'has_action' ) && false !== has_action( 'admin_post_mcr_checkin' ) ) {
			return admin_url( 'admin-post.php' );
		}

		return '';
	}


	/**
	 * Renders the shared signed-out empty state.
	 *
	 * @return string
	 */
	public static function auth_empty() {
		$actions = array(
			array(
				'label' => __( 'Sign in', 'madara-coin-rewards' ),
				'url'   => wp_login_url( self::current_url() ),
				'class' => 'mcr-btn',
			),
		);

		if ( get_option( 'users_can_register' ) ) {
			$actions[] = array(
				'label' => __( 'Create an account', 'madara-coin-rewards' ),
				'url'   => wp_registration_url(),
				'class' => 'mcr-btn mcr-btn--quiet',
			);
		}

		return self::part(
			'empty',
			array(
				'icon'    => '&#9679;',
				'title'   => __( 'Sign in to see your coins', 'madara-coin-rewards' ),
				'text'    => __( 'Your balance, streak and transaction history are private to your account.', 'madara-coin-rewards' ),
				'actions' => $actions,
			)
		);
	}

	/**
	 * Returns the built-in page definitions.
	 *
	 * @param string $slug Optional single slug.
	 * @return array
	 */
	private static function built_in( $slug = '' ) {
		if ( empty( self::$pages ) ) {
			self::$pages = array(
				'checkin'      => array(
					'label'          => __( 'Daily check-in', 'madara-coin-rewards' ),
					'shortcode'      => 'mcr_checkin',
					'block'          => 'checkin',
					'default_layout' => 'default',
					'layouts'        => array(
						'default' => __( 'Default', 'madara-coin-rewards' ),
						'hero'    => __( 'Hero', 'madara-coin-rewards' ),
						'compact' => __( 'Compact', 'madara-coin-rewards' ),
					),
					'sections'       => array( 'status', 'streak', 'calendar', 'bonuses', 'rules' ),
					'lead'           => __( 'Claim your coins once a day. Come back tomorrow to keep your streak alive.', 'madara-coin-rewards' ),
				),
				'dashboard'    => array(
					'label'          => __( 'Rewards dashboard', 'madara-coin-rewards' ),
					'shortcode'      => 'mcr_dashboard',
					'block'          => 'dashboard',
					'default_layout' => 'default',
					'layouts'        => array(
						'default' => __( 'Grid', 'madara-coin-rewards' ),
						'list'    => __( 'List', 'madara-coin-rewards' ),
						'hero'    => __( 'Hero', 'madara-coin-rewards' ),
					),
					'sections'       => array( 'balance', 'stats', 'quick', 'recent', 'streak', 'ads' ),
					'lead'           => __( 'Balance, streaks and everything you have earned so far.', 'madara-coin-rewards' ),
				),
				'ads'          => array(
					'label'          => __( 'Watch ads', 'madara-coin-rewards' ),
					'shortcode'      => 'mcr_ads',
					'block'          => 'ads',
					'default_layout' => 'default',
					'layouts'        => array(
						'default' => __( 'Cards', 'madara-coin-rewards' ),
					),
					'sections'       => array( 'status', 'providers', 'limits', 'recent' ),
					'lead'           => __( 'Watch a short ad to earn coins. Limits reset daily.', 'madara-coin-rewards' ),
				),
				'buy-coins'    => array(
					'label'          => __( 'Buy coins', 'madara-coin-rewards' ),
					'shortcode'      => 'mcr_buy_coins',
					'block'          => 'buy-coins',
					'default_layout' => 'default',
					'layouts'        => array(
						'default' => __( 'Grid', 'madara-coin-rewards' ),
					),
					'sections'       => array( 'balance', 'packages', 'gateways', 'faq' ),
					'lead'           => __( 'Top up your balance. Coins are added as soon as payment is confirmed.', 'madara-coin-rewards' ),
				),
				'transactions' => array(
					'label'          => __( 'Transaction history', 'madara-coin-rewards' ),
					'shortcode'      => 'mcr_transactions',
					'block'          => 'transactions',
					'default_layout' => 'default',
					'layouts'        => array(
						'default' => __( 'Table', 'madara-coin-rewards' ),
					),
					'sections'       => array( 'summary', 'list', 'pagination' ),
					'lead'           => __( 'Every coin you have earned or spent, newest first.', 'madara-coin-rewards' ),
				),
				'balance'      => array(
					'label'          => __( 'Balance panel', 'madara-coin-rewards' ),
					'shortcode'      => 'mcr_balance',
					'block'          => 'balance',
					'default_layout' => 'default',
					'layouts'        => array(
						'default' => __( 'Card', 'madara-coin-rewards' ),
						'inline'  => __( 'Inline', 'madara-coin-rewards' ),
						'hero'    => __( 'Hero', 'madara-coin-rewards' ),
					),
					'sections'       => array( 'balance', 'tier', 'actions' ),
					'lead'           => __( 'Your coin balance at a glance.', 'madara-coin-rewards' ),
				),
			);
		}

		if ( '' !== $slug ) {
			return isset( self::$pages[ $slug ] ) ? self::$pages[ $slug ] : array();
		}

		return self::$pages;
	}

	/**
	 * Renders the template body for a resolved slug and layout.
	 *
	 * @param string $slug   Page slug.
	 * @param string $layout Layout key.
	 * @param array  $args   Template arguments.
	 * @return string
	 */
	private static function render_body( $slug, $layout, array $args ) {
		$file = self::locate( $slug, $layout, $args );

		if ( '' === $file ) {
			return self::part(
				'error',
				array(
					'title' => __( 'Template not found.', 'madara-coin-rewards' ),
					'text'  => __( 'This rewards page cannot be displayed right now.', 'madara-coin-rewards' ),
				)
			);
		}

		/**
		 * Filters the resolved template path after lookup.
		 *
		 * @param string $file   Absolute path.
		 * @param string $slug   Page slug.
		 * @param string $layout Layout key.
		 */
		$file = apply_filters( 'mcr_template_path', $file, $slug, $layout );

		if ( ! is_string( $file ) || ! is_readable( $file ) ) {
			return '';
		}

		ob_start();
		include $file;

		return (string) ob_get_clean();
	}

	/**
	 * Wraps rendered content in the shared page shell.
	 *
	 * @param string $slug    Page slug.
	 * @param string $layout  Layout key.
	 * @param array  $args    Template arguments.
	 * @param string $content Rendered sections.
	 * @return string
	 */
	private static function shell( $slug, $layout, array $args, $content ) {
		$header = '';

		if ( ! empty( $args['show_header'] ) ) {
			$header = '<div class="mcr-page__header">';

			if ( ! empty( $args['lead'] ) ) {
				$header .= '<p class="mcr-page__lead">' . esc_html( $args['lead'] ) . '</p>';
			}

			$header .= '</div>';
		}

		$classes = 'mcr-scope mcr-page mcr-page--' . $slug;

		$html  = '<div class="' . esc_attr( $classes ) . '" data-mcr-page="' . esc_attr( $slug ) . '" data-mcr-layout="' . esc_attr( $layout ) . '">';
		$html .= self::coin_sprite();
		$html .= $header;
		$html .= '<div class="mcr-page__body">' . $content . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		/*
		 * The live region is announced by id, so a page that renders the
		 * plugin twice must not emit the same id twice. The first render
		 * keeps the canonical id the script looks for; later ones get a
		 * suffixed id.
		 */
		++self::$render_count;

		$live_id = 1 === self::$render_count ? 'mcr-live-balance' : 'mcr-live-balance-' . self::$render_count;

		$html .= '<div class="mcr-sr-only" data-mcr-live="balance" id="' . esc_attr( $live_id ) . '" aria-live="polite" aria-atomic="true"></div>';
		$html .= '<div class="mcr-portal mcr-toasts" role="region" aria-label="' . esc_attr__( 'Notifications', 'madara-coin-rewards' ) . '"></div>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Prints the coin sprite once per request.
	 *
	 * @return string
	 */
	private static function coin_sprite() {
		if ( self::$sprite_done ) {
			return '';
		}

		self::$sprite_done = true;

		$variants = array(
			1   => array( 'font' => 13, 'ring' => false, 'ticks' => false, 'stack' => false ),
			5   => array( 'font' => 12, 'ring' => true, 'ticks' => false, 'stack' => false ),
			10  => array( 'font' => 10, 'ring' => true, 'ticks' => true, 'stack' => false ),
			50  => array( 'font' => 9.5, 'ring' => true, 'ticks' => true, 'stack' => false ),
			100 => array( 'font' => 8, 'ring' => true, 'ticks' => false, 'stack' => true ),
		);

		$svg  = '<svg class="mcr-coin-sprite" width="0" height="0" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg">';
		$svg .= '<defs><linearGradient id="mcr-coin-gold" x1="0" y1="0" x2="0" y2="1">';
		$svg .= '<stop offset="0" stop-color="#fff6bf"/><stop offset=".35" stop-color="#ffe45c"/>';
		$svg .= '<stop offset=".62" stop-color="#ffd900"/><stop offset="1" stop-color="#f2b705"/>';
		$svg .= '</linearGradient></defs>';

		foreach ( $variants as $value => $variant ) {
			$svg .= '<symbol id="mcr-coin-symbol-' . esc_attr( $value ) . '" viewBox="0 0 32 32">';

			if ( $variant['stack'] ) {
				$svg .= '<circle cx="19" cy="16" r="13" fill="#c98a04" opacity=".55"/>';
				$svg .= '<circle cx="17.5" cy="16" r="13" fill="#f2b705" opacity=".8"/>';
			}

			$svg .= '<circle cx="16" cy="16" r="15" fill="url(#mcr-coin-gold)"/>';
			$svg .= '<circle cx="16" cy="16" r="14.25" fill="none" stroke="#b8790a" stroke-width="1.5" opacity=".75"/>';

			if ( $variant['ring'] ) {
				$svg .= '<circle cx="16" cy="16" r="11" fill="none" stroke="#c98a04" stroke-width="1" opacity=".55"/>';
			}

			if ( $variant['ticks'] ) {
				$svg .= '<circle cx="16" cy="16" r="12.5" fill="none" stroke="#b8790a" stroke-width="2" stroke-linecap="round" stroke-dasharray=".6 4.3" opacity=".55"/>';
			}

			$svg .= '<ellipse cx="12" cy="9.5" rx="6.5" ry="3.2" fill="#ffffff" opacity=".38" transform="rotate(-20 12 9.5)"/>';
			$svg .= '<text x="16" y="16" text-anchor="middle" dominant-baseline="central" font-family="inherit" font-size="' . esc_attr( $variant['font'] ) . '" font-weight="700" fill="#6b4a00">' . esc_html( $value ) . '</text>';
			$svg .= '</symbol>';
		}

		$svg .= '</svg>';

		return $svg;
	}

	/**
	 * Renders the "template not found" error.
	 *
	 * @return string
	 */
	private static function render_missing() {
		return self::part(
			'error',
			array(
				'title' => __( 'Template not found.', 'madara-coin-rewards' ),
				'text'  => __( 'This rewards page cannot be displayed right now.', 'madara-coin-rewards' ),
			)
		);
	}

	/**
	 * Normalizes a filesystem path.
	 *
	 * @param string $path Raw path.
	 * @return string
	 */
	private static function normalize_path( $path ) {
		if ( function_exists( 'wp_normalize_path' ) ) {
			return wp_normalize_path( $path );
		}

		return str_replace( '\\', '/', $path );
	}
}
