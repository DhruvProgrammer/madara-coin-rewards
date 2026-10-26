<?php
/**
 * Plugin settings.
 *
 * @package MCR
 */

namespace MCR;

defined( 'ABSPATH' ) || exit;

use MCR\Gateways\Gateway;
use MCR\AdProviders\Provider;

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

			// Uninstall.
			//
			// uninstall.php reads this key, so it has to exist as a typed
			// default here: otherwise the sanitize callback would have no
			// type inference for it and the purge flag could never be
			// stored through the Tools screen.
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
	 * Translates a default string.
	 *
	 * The defaults are built while the plugin boots, which is BEFORE the
	 * init action, so the text domain is not loaded yet. Calling a
	 * translation function at that point makes WordPress raise
	 * "_load_textdomain_just_in_time was called incorrectly" on every
	 * request, and the string is never translated.
	 *
	 * @param string $source The source string.
	 * @return string
	 */
	private static function text( $source ) {
		if ( function_exists( 'did_action' ) && did_action( 'init' ) ) {
			if ( 'Daily reward claimed! Come back tomorrow to keep your streak.' === $source ) {
				return __( 'Daily reward claimed! Come back tomorrow to keep your streak.', 'madara-coin-rewards' );
			}
			if ( 'Your coins have been added to your account.' === $source ) {
				return __( 'Your coins have been added to your account.', 'madara-coin-rewards' );
			}
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
	 * Normalizes a raw checkbox-style value to the on/off toggle convention.
	 *
	 * The explicit `'off'` string (and falsey values) mean off. Using
	 * `! empty()` here turned the string "off" into "on", because a
	 * non-empty string is truthy - so a package could never be switched off
	 * through update().
	 *
	 * @param mixed $value Raw value.
	 * @return string 'on' or 'off'.
	 */
	public static function to_toggle( $value ) {
		if ( true === $value || 1 === $value || '1' === $value || 'on' === $value || 'yes' === $value ) {
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
	 * THE STORED VALUE IS THE BASELINE. WordPress calls this callback with
	 * exactly the fields one settings screen submitted (packages screen,
	 * tools screen, gateway field...) and replaces the whole option with
	 * the return value. Starting from the submitted array instead would
	 * erase every key that form did not render, which is how a settings
	 * save silently deletes the coin packages, the page assignments and
	 * the gateway/provider secrets.
	 *
	 * The payload may mix both shapes:
	 *  - flat dotted keys ('checkin.base_amount' => 10) as posted by the
	 *    WP settings forms, and
	 *  - nested shapes ('gateways' => array( 'stripe' => array(...))) as
	 *    handed over by programmatic updates.
	 *
	 * Gateway (`gateways.{id}`) and provider (`ads.providers.{id}`)
	 * subtrees are delegated to their own sanitizers so secret values are
	 * always passed through Secrets::encrypt() before they reach storage.
	 *
	 * @param mixed  $input Raw submitted settings.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$defaults = self::defaults();
		$stored   = get_option( self::OPTION, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$clean = array();

		// 1. Keep every stored setting, re-sanitizing it against the type
		//    it is stored as. A partial form submission cannot remove it.
		foreach ( $stored as $key => $value ) {
			$key = (string) $key;

			if ( ! preg_match( self::KEY_PATTERN, $key ) ) {
				continue;
			}

			$clean[ $key ] = self::sanitizeStoredValue( $key, $value );
		}

		// 2. Apply the submitted values, flattened into dotted keys, over
		//    the stored baseline.
		foreach ( self::flatten_input( $input ) as $key => $value ) {
			$key = (string) $key;

			if ( ! preg_match( self::KEY_PATTERN, $key ) ) {
				continue;
			}

			$clean[ $key ] = self::sanitizeSubmittedValue( $key, $value, $clean, $defaults );
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
	 * The regex an accepted settings key must match.
	 *
	 * keep_note: sanitize_key() strips the dots a dotted key needs, so the
	 * keys are validated against this pattern instead.
	 */
	const KEY_PATTERN = '/^[a-z0-9_\-\.]+$/i';

	/**
	 * Re-sanitizes one already-stored setting.
	 *
	 * @param string $key   Dot-notation key.
	 * @param mixed  $value Stored value.
	 * @return mixed
	 */
	private static function sanitizeStoredValue( $key, $value ) {
		$defaults = self::defaults();

		if ( 0 === strpos( $key, 'gateways.' ) ) {
			$gateway_id = sanitize_key( substr( $key, 9 ) );

			if ( ! is_array( $value ) ) {
				return array();
			}

			return Gateway::sanitize_settings( $gateway_id, $value, $value );
		}

		if ( 0 === strpos( $key, 'ads.providers.' ) ) {
			$provider_id = sanitize_key( substr( $key, 14 ) );

			if ( ! is_array( $value ) ) {
				return array();
			}

			return Provider::sanitize_settings( $provider_id, $value, $value );
		}

		if ( array_key_exists( $key, $defaults ) ) {
			return self::sanitizeValue( $key, $value, $defaults[ $key ] );
		}

		// Unknown stored keys (set by integrations or earlier versions)
		// are preserved as they are.
		return $value;
	}

	/**
	 * Sanitizes one setting value that the current submit carried.
	 *
	 * @param string $key      Dot-notation key.
	 * @param mixed  $value    Raw submitted value.
	 * @param array  $clean    The sanitized array so far (stored baseline).
	 * @param array  $defaults Default settings for type inference.
	 * @return mixed
	 */
	private static function sanitizeSubmittedValue( $key, $value, array &$clean, array $defaults ) {
		if ( 0 === strpos( $key, 'gateways.' ) ) {
			$gateway_id = sanitize_key( substr( $key, 9 ) );

			if ( '' === $gateway_id || ! is_array( $value ) ) {
				// Malformed gateway payload: fall back to the stored value.
				return isset( $clean[ $key ] ) ? $clean[ $key ] : array();
			}

			$stored = isset( $clean[ $key ] ) && is_array( $clean[ $key ] ) ? $clean[ $key ] : array();

			return Gateway::sanitize_settings( $gateway_id, $value, $stored );
		}

		if ( 0 === strpos( $key, 'ads.providers.' ) ) {
			$provider_id = sanitize_key( substr( $key, 14 ) );

			if ( '' === $provider_id || ! is_array( $value ) ) {
				return isset( $clean[ $key ] ) ? $clean[ $key ] : array();
			}

			$stored = isset( $clean[ $key ] ) && is_array( $clean[ $key ] ) ? $clean[ $key ] : array();

			return Provider::sanitize_settings( $provider_id, $value, $stored );
		}

		if ( array_key_exists( $key, $defaults ) ) {
			return self::sanitizeValue( $key, $value, $defaults[ $key ] );
		}

		// An unknown key submitted out of nowhere: store nothing unsanized.
		return self::sanitize_setting_tree( $value );
	}

	/**
	 * Folds BOTH input shapes into dotted keys.
	 *
	 * Every form in this plugin posts flat dotted field names, so PHP
	 * already hands in 'gateways.stripe' => array(...). A wrapper that
	 * builds the settings programmatically may however hand in
	 * 'gateways' => array( 'stripe' => array(...) ). Only the gateway and
	 * ad-provider paths behave like containers; arrays everywhere else
	 * (packages, per-provider subtrees) are kept nested, because those
	 * sub-arrays ARE the value.
	 *
	 * @param array  $data  Raw input.
	 * @param string $prefix Internal prefix (do not pass).
	 * @return array<string,mixed>
	 */
	private static function flatten_input( array $data, $prefix = '' ) {
		$flat = array();

		foreach ( $data as $key => $value ) {
			$key       = (string) $key;
			$composite = '' === $prefix ? $key : $prefix . '.' . $key;

			if ( is_array( $value ) && self::is_container_path( $composite ) ) {
				$flat += self::flatten_input( $value, $composite );
				continue;
			}

			$flat[ $composite ] = $value;
		}

		return $flat;
	}

	/**
	 * Whether a composite input path keeps folding its children upward.
	 *
	 * @param string $composite The flattened key built so far.
	 * @return bool
	 */
	private static function is_container_path( $composite ) {
		return in_array( (string) $composite, array( 'gateways', 'ads', 'ads.providers' ), true );
	}

	/**
	 * Defensively sanitizes a value submitted under an unknown key.
	 *
	 * @param mixed $value Raw value.
	 * @return mixed
	 */
	private static function sanitize_setting_tree( $value ) {
		if ( is_array( $value ) ) {
			return array_map( array( __CLASS__, 'sanitize_setting_tree' ), $value );
		}

		return sanitize_text_field( (string) $value );
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
		 * The admin editor posts a JSON *string* (it is a <textarea>), so a
		 * raw string has to be decoded before the array walk below. Without
		 * this the editor silently saved an empty package list: WordPress
		 * reported "Settings saved" and then dropped every package.
		 */
		if ( is_string( $value ) ) {
			$trimmed = trim( $value );

			if ( '' === $trimmed ) {
				return array();
			}

			$decoded = json_decode( $trimmed, true );

			$value = ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) ? $decoded : array();
		}

		if ( ! is_array( $value ) ) {
			return array();
		}

		// Tolerate a list-style payload by keying each row on its own id.
		$keyed = array();

		foreach ( $value as $key => $package ) {
			if ( ! is_array( $package ) ) {
				continue;
			}

			$inner_id = isset( $package['id'] ) ? sanitize_key( (string) $package['id'] ) : '';

			if ( '' === $inner_id ) {
				$inner_id = sanitize_key( (string) $key );
			}

			if ( '' === $inner_id ) {
				continue;
			}

			$package['id'] = $inner_id;

			$keyed[ $inner_id ] = $package;
		}

		$value = $keyed;

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

			$clean[ $id ] = array(
				'id'            => $id,
				'title'         => isset( $package['title'] ) ? sanitize_text_field( $package['title'] ) : '',
				'coins'         => $coins,
				'bonus_percent' => isset( $package['bonus_percent'] ) ? max( 0, min( 100, (int) $package['bonus_percent'] ) ) : 0,
				'max_bonus'     => isset( $package['max_bonus'] ) ? max( 0, (int) $package['max_bonus'] ) : 0,
				'price'         => $price,
				'currency'      => isset( $package['currency'] ) ? strtoupper( substr( sanitize_text_field( $package['currency'] ), 0, 8 ) ) : Settings::get( 'purchase.currency', 'USD' ),
				'badge'         => isset( $package['badge'] ) ? sanitize_text_field( $package['badge'] ) : '',
				'active'        => self::to_toggle( isset( $package['active'] ) ? $package['active'] : 'off' ),
				'sort_order'    => isset( $package['sort_order'] ) ? (int) $package['sort_order'] : 0,
				'limit'         => isset( $package['limit'] ) ? max( 0, (int) $package['limit'] ) : 0,
				'purchased'     => isset( $package['purchased'] ) ? (int) $package['purchased'] : 0,
			);
		}

		return $clean;
	}
}
