<?php
/**
 * Plugin settings.
 *
 * @package MCR
 */

namespace MCR;

defined( 'ABSPATH' ) || exit;

/**
 * Typed access to the plugin's settings array.
 */
class Settings {

	/**
	 * Option name holding the settings array.
	 */
	const OPTION = 'mcr_settings';

	/**
	 * Cached settings array.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Returns the default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		$defaults = array(
			// Feature switches.
			'features.checkin_enabled'         => 'on',
			'features.ads_enabled'             => 'on',
			'features.purchase_enabled'        => 'on',
			'features.dashboard_enabled'       => 'on',

			// Daily check-in.
			'checkin.base_amount'              => 10,
			'checkin.bonuses'                  => array(
				3  => 5,
				7  => 15,
				30 => 50,
			),
			'checkin.max_bonus'                => 100,
			'checkin.streak_enabled'           => 'on',
			'checkin.timezone_source'          => 'site',
			'checkin.require_login'            => 'on',
			'checkin.success_message'          => self::text( 'Daily reward claimed! Come back tomorrow to keep your streak.' ),

			// Ad rewards.
			'ads.coins_per_ad'                 => 5,
			'ads.daily_limit'                  => 10,
			'ads.cooldown_seconds'             => 300,
			'ads.session_ttl'                  => 900,
			'ads.provider'                     => '',
			'ads.global_daily_limit'           => 50,
			'ads.placements'                   => array( 'rewarded_video' ),

			// Purchases.
			'purchase.currency'                => 'USD',
			'purchase.min_amount'              => 1,
			'purchase.max_amount'              => 10000,
			'purchase.order_expiry_minutes'    => 60,
			'purchase.gateway'                 => 'manual',
			'purchase.auto_credit'             => 'on',
			'purchase.success_message'         => self::text( 'Your coins have been added to your account.' ),

			// Packages.
			'packages'                         => array(),

			// Templates and pages.
			'template.layout_checkin'          => 'default',
			'template.layout_dashboard'        => 'default',
			'template.layout_ads'              => 'default',
			'template.layout_buy_coins'        => 'default',
			'template.layout_transactions'     => 'default',
			'template.layout_balance'          => 'default',
			'template.columns'                 => 3,
			'template.show_balance_summary'    => 'on',
			'template.show_progress_bars'      => 'on',
			'template.show_streak_calendar'    => 'on',
			'template.show_sidebar'            => 'on',
			'template.show_breadcrumb'         => 'on',
			'pages.checkin'                    => 0,
			'pages.dashboard'                  => 0,
			'pages.ads'                        => 0,
			'pages.buy_coins'                  => 0,
			'pages.transactions'               => 0,

			// Security.
			'security.log_ip'                  => 'on',
			'security.log_user_agent'          => 'off',
			'security.rate_limit_checkin'      => 10,
			'security.rate_limit_ads'          => 20,
			'security.maintenance_mode'        => 'off',

			// Notifications.
			'notify.toast_on_change'           => 'on',
			'notify.show_login_prompt'         => 'on',

			// Uninstall. The opt-in has to be a KNOWN setting, or the
			// sanitiser drops it and the uninstall handler can never
			// purge anything.
			'uninstall.purge_data'             => 'off',
		);

		/**
		 * Filters the plugin's default settings.
		 *
		 * @param array $defaults Default settings.
		 */
		return apply_filters( 'mcr_default_settings', $defaults );
	}

	/**
	 * The catalogue of strings the settings registry ships with.
	 *
	 * The defaults are built while the plugin boots, which is BEFORE the
	 * init action, so the text domain is not loaded yet. Calling a
	 * translation function at that point makes WordPress raise
	 * "_load_textdomain_just_in_time was called incorrectly" on every
	 * request, and the string is never translated.
	 *
	 * Every entry carries a literal message id, so a translator is able to
	 * match it: a computed message id can never be translated.
	 *
	 * @return array Map of source string to translated string.
	 */
	private static function strings() {
		return array(
			'Daily reward claimed! Come back tomorrow to keep your streak.' => __( 'Daily reward claimed! Come back tomorrow to keep your streak.', 'madara-coin-rewards' ),
			'Your coins have been added to your account.'                   => __( 'Your coins have been added to your account.', 'madara-coin-rewards' ),
		);
	}

	/**
	 * Translates a default string.
	 *
	 * The defaults are built while the plugin boots, which is BEFORE the
	 * init action, so the text domain is not loaded yet. Calling a
	 * translation function at that point makes WordPress raise
	 * "_load_textdomain_just_in_time was called incorrectly" on every
	 * request, and the string is never translated.
	 *
	 * @param string $source The source string.
	 * @return string The translated string, or the source when unknown.
	 */
	private static function text( $source ) {
		if ( function_exists( 'did_action' ) && did_action( 'init' ) ) {
			$strings = self::strings();

			return isset( $strings[ $source ] ) ? $strings[ $source ] : $source;
		}

		return $source;
	}

	/**
	 * Returns all settings merged over the defaults.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$stored = get_option( self::OPTION, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		self::$cache = array_merge( self::defaults(), $stored );

		return self::$cache;
	}

	/**
	 * Returns a single setting.
	 *
	 * @param string $key           Dot-notation key.
	 * @param mixed  $default_value Value when unset.
	 * @return mixed
	 */
	public static function get( $key, $default_value = null ) {
		$all = self::all();

		if ( array_key_exists( $key, $all ) ) {
			return $all[ $key ];
		}

		return $default_value;
	}

	/**
	 * Returns a setting as an 'on'/'off' string.
	 *
	 * @param string $key Dot-notation key.
	 * @return string
	 */
	public static function toggle( $key ) {
		$value = self::get( $key, 'off' );

		if ( true === $value || 'on' === $value || 1 === $value || '1' === $value ) {
			return 'on';
		}

		return 'off';
	}

	/**
	 * Whether a feature is enabled.
	 *
	 * @param string $feature Feature key, e.g. 'checkin'.
	 * @return bool
	 */
	public static function featureEnabled( $feature ) {
		return 'on' === self::toggle( 'features.' . $feature . '_enabled' );
	}

	/**
	 * Updates settings.
	 *
	 * @param array $values Partial settings to merge.
	 * @return bool
	 */
	public static function update( array $values ) {
		$current = get_option( self::OPTION, array() );

		if ( ! is_array( $current ) ) {
			$current = array();
		}

		$merged = array_merge( $current, $values );

		self::$cache = null;

		return update_option( self::OPTION, $merged );
	}

	/**
	 * Clears the in-request cache.
	 *
	 * @return void
	 */
	public static function flush_cache() {
		self::$cache = null;
	}

	/**
	 * Installs the default settings on activation.
	 *
	 * @return void
	 */
	public static function install_defaults() {
		$stored = get_option( self::OPTION, null );

		if ( null === $stored ) {
			add_option( self::OPTION, self::defaults() );
			self::flush_cache();

			return;
		}

		if ( ! is_array( $stored ) ) {
			update_option( self::OPTION, self::defaults() );
			self::flush_cache();
		}
	}

	/**
	 * Sanitizes the whole settings array.
	 *
	 * @param mixed $input Raw submitted settings.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$defaults = self::defaults();

		/*
		 * The array starts from the STORED value, not from the defaults.
		 *
		 * The gateway and the ad provider credentials live under keys
		 * that are not part of the defaults, because they only exist once
		 * something has been configured. Building the sanitised array from
		 * the defaults alone silently deleted every one of them the first
		 * time the settings form was saved.
		 */
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();

		$clean = $stored;

		foreach ( $defaults as $key => $default ) {
			if ( ! array_key_exists( $key, $input ) ) {
				continue;
			}

			$clean[ $key ] = self::sanitizeValue( $key, $input[ $key ], $default );
		}

		/*
		 * The gateway and the provider settings arrive in one of two
		 * shapes, and BOTH are accepted:
		 *
		 *   the flat dotted key, which is what the field name
		 *   "mcr_settings[gateways.stripe][secret_key]" produces:
		 *
		 *     array( 'gateways.stripe' => array( 'secret_key' => $value ) )
		 *
		 *   and the nested shape:
		 *
		 *     array( 'gateways' => array( 'stripe' => array( ... ) ) )
		 *
		 * Accepting only one of them meant that whichever shape the
		 * form did not produce was silently discarded.
		 */
		$gateways = array();

		if ( isset( $input['gateways'] ) && is_array( $input['gateways'] ) ) {
			foreach ( $input['gateways'] as $gateway_id => $values ) {
				if ( is_array( $values ) ) {
					$gateways[ (string) $gateway_id ] = $values;
				}
			}
		}

		foreach ( $input as $key => $values ) {
			if ( ! is_string( $key ) || 0 !== strpos( $key, 'gateways.' ) || ! is_array( $values ) ) {
				continue;
			}

			$gateway_id = substr( $key, strlen( 'gateways.' ) );

			if ( ! isset( $gateways[ $gateway_id ] ) ) {
				$gateways[ $gateway_id ] = $values;
			}
		}

		foreach ( $gateways as $gateway_id => $values ) {
			$gateway_id = sanitize_key( (string) $gateway_id );

			if ( '' === $gateway_id ) {
				continue;
			}

			$key = 'gateways.' . $gateway_id;

			$existing = isset( $clean[ $key ] ) && is_array( $clean[ $key ] ) ? $clean[ $key ] : array();

			$clean[ $key ] = \MCR\Gateways\Gateway::sanitize_settings( $gateway_id, $values, $existing );
		}

		$providers = array();

		if ( isset( $input['ads.providers'] ) && is_array( $input['ads.providers'] ) ) {
			foreach ( $input['ads.providers'] as $provider_id => $values ) {
				if ( is_array( $values ) ) {
					$providers[ (string) $provider_id ] = $values;
				}
			}
		}

		foreach ( $input as $key => $values ) {
			if ( ! is_string( $key ) || 0 !== strpos( $key, 'ads.providers.' ) || ! is_array( $values ) ) {
				continue;
			}

			$provider_id = substr( $key, strlen( 'ads.providers.' ) );

			if ( ! isset( $providers[ $provider_id ] ) ) {
				$providers[ $provider_id ] = $values;
			}
		}

		foreach ( $providers as $provider_id => $values ) {
			$provider_id = sanitize_key( (string) $provider_id );

			if ( '' === $provider_id ) {
				continue;
			}

			$key = 'ads.providers.' . $provider_id;

			$existing = isset( $clean[ $key ] ) && is_array( $clean[ $key ] ) ? $clean[ $key ] : array();

			$clean[ $key ] = \MCR\AdProviders\Provider::sanitize_settings( $provider_id, $values, $existing );
		}

		/**
		 * Filters the sanitized settings before they are saved.
		 *
		 * @param array $clean    Sanitized settings.
		 * @param array $input    Raw submitted settings.
		 */
		return apply_filters( 'mcr_sanitize_settings', $clean, $input );
	}

	/**
	 * Sanitizes a single setting value.
	 *
	 * @param string $key     Dot-notation key.
	 * @param mixed  $value   Raw value.
	 * @param mixed  $default Default value (used to infer the type).
	 * @return mixed
	 */
	private static function sanitizeValue( $key, $value, $default ) {
		/*
		 * A setting whose value is an array is rendered as a JSON
		 * textarea, so what arrives from the form is a JSON STRING. It
		 * has to be decoded before the sanitizer can work with it -
		 * otherwise the streak bonus map and the coin packages could
		 * never be saved.
		 */
		if ( is_array( $default ) && is_string( $value ) ) {
			$decoded = json_decode( $value, true );

			if ( is_array( $decoded ) ) {
				$value = $decoded;
			} else {
				return is_array( $default ) ? array() : $default;
			}
		}

		if ( is_array( $default ) && 'packages' === substr( $key, 0, 8 ) ) {
			return self::sanitizePackages( $value );
		}

		if ( is_array( $default ) && 'checkin.bonuses' === $key ) {
			return self::sanitizeBonuses( $value );
		}

		if ( is_array( $default ) ) {
			return is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : array();
		}

		if ( 'on' === $default || 'off' === $default ) {
			return in_array( $value, array( 'on', 'off' ), true ) ? $value : $default;
		}

		if ( is_int( $default ) ) {
			return (int) $value;
		}

		if ( is_float( $default ) ) {
			return (float) $value;
		}

		if ( is_string( $default ) ) {
			return sanitize_text_field( (string) $value );
		}

		return sanitize_text_field( (string) $value );
	}

	/**
	 * Sanitizes the streak bonus map.
	 *
	 * @param mixed $value Raw bonuses.
	 * @return array Threshold => amount.
	 */
	public static function sanitizeBonuses( $value ) {
		/*
		 * The bonuses are edited in a JSON textarea, so what arrives from
		 * the form is a JSON string. It is decoded here, so the map can
		 * actually be saved.
		 */
		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			$value   = is_array( $decoded ) ? $decoded : array();
		}

		if ( ! is_array( $value ) ) {
			return array();
		}

		$clean = array();

		foreach ( $value as $threshold => $amount ) {
			$threshold = (int) $threshold;
			$amount    = (int) $amount;

			if ( $threshold < 1 || $threshold > 3650 ) {
				continue;
			}

			if ( $amount < 0 || $amount > 1000000 ) {
				continue;
			}

			$clean[ $threshold ] = $amount;
		}

		ksort( $clean );

		return $clean;
	}

	/**
	 * Sanitizes the coin packages array.
	 *
	 * @param mixed $value Raw packages.
	 * @return array
	 */
	public static function sanitizePackages( $value ) {
		/*
		 * The packages are edited in a JSON textarea, so what arrives
		 * from the form is a JSON string. It is decoded here, so a
		 * package can actually be saved.
		 */
		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			$value   = is_array( $decoded ) ? $decoded : array();
		}

		if ( ! is_array( $value ) ) {
			return array();
		}

		$clean = array();

		foreach ( $value as $key => $package ) {
			if ( ! is_array( $package ) ) {
				continue;
			}

			$id = isset( $package['id'] ) ? sanitize_key( $package['id'] ) : sanitize_key( (string) $key );

			if ( '' === $id ) {
				continue;
			}

			$coins = isset( $package['coins'] ) ? (int) $package['coins'] : 0;

			if ( $coins < 0 || $coins > 100000000 ) {
				continue;
			}

			$price = isset( $package['price'] ) ? (float) $package['price'] : 0.0;

			if ( $price < 0 || $price > 1000000 ) {
				continue;
			}

			/*
			 * The keys written here have to match the editable field list
			 * of the packages page (Payments\Packages::fields()) exactly.
			 *
			 * The list used to be missing "max_bonus", so the per-package
			 * bonus cap was silently dropped every time the packages form
			 * was saved: the package round trip lost a field the read path
			 * (Packages::normalize()) still expects.
			 */
			$clean[ $id ] = array(
				'id'            => $id,
				'title'         => isset( $package['title'] ) ? sanitize_text_field( $package['title'] ) : '',
				'coins'         => $coins,
				'bonus_percent' => isset( $package['bonus_percent'] ) ? max( 0, min( 100, (int) $package['bonus_percent'] ) ) : 0,
				'max_bonus'     => isset( $package['max_bonus'] ) ? max( 0, (int) $package['max_bonus'] ) : 0,
				'price'         => $price,
				'currency'      => isset( $package['currency'] ) ? strtoupper( substr( sanitize_text_field( $package['currency'] ), 0, 8 ) ) : Settings::get( 'purchase.currency', 'USD' ),
				'badge'         => isset( $package['badge'] ) ? sanitize_text_field( $package['badge'] ) : '',
				'active'        => ! empty( $package['active'] ) ? 'on' : 'off',
				'sort_order'    => isset( $package['sort_order'] ) ? (int) $package['sort_order'] : 0,
				'limit'         => isset( $package['limit'] ) ? max( 0, (int) $package['limit'] ) : 0,
				'purchased'     => isset( $package['purchased'] ) ? (int) $package['purchased'] : 0,
			);
		}

		return $clean;
	}
}
