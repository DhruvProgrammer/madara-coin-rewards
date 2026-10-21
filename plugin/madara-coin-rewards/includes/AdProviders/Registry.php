<?php
/**
 * Ad provider registry.
 *
 * Single access point for every rewarded-ad provider. Providers come built
 * in (generic signed server-to-server postback) or via the
 * `mcr_register_ad_providers` filter.
 *
 * Call Registry::load() once early (plugins_loaded / init); the registry
 * schedules its rest_api_init hook on first load so providers with REST
 * endpoints register them in time.
 *
 * @package MCR
 */

namespace MCR\AdProviders;

use MCR\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Creates and keeps the ad provider instances.
 */
final class Registry {

	/**
	 * @var array<string,Provider>
	 */
	private static $providers = array();

	/**
	 * @var bool
	 */
	private static $loaded = false;

	/**
	 * @var bool
	 */
	private static $hooks_scheduled = false;

	/**
	 * Builds all providers. Safe to call more than once.
	 *
	 * @return void
	 */
	public static function load() {
		self::schedule_hooks();

		if ( self::$loaded ) {
			return;
		}

		self::$loaded = true;

		$instances = array(
			'generic_s2s' => GenericS2S::class,
		);

		/**
		 * Filters the registered ad providers.
		 *
		 * Values may be Provider instances or class-name strings.
		 *
		 * @param array $instances Current instances, keyed by provider slug.
		 */
		$instances = apply_filters( 'mcr_register_ad_providers', $instances );

		foreach ( $instances as $id => $provider ) {
			if ( is_string( $provider ) && class_exists( $provider ) ) {
				$settings = Provider::load_settings( $id );
				$settings = is_array( $settings ) ? $settings : array();

				$provider = new $provider( $settings );
			}

			if ( $provider instanceof Provider ) {
				$provider->register_hooks();
				self::$providers[ self::sanitize_provider_id( $provider->get_id() ) ] = $provider;
			}
		}
	}

	/**
	 * Schedules the REST route registration hook once.
	 *
	 * @return void
	 */
	private static function schedule_hooks() {
		if ( self::$hooks_scheduled ) {
			return;
		}

		self::$hooks_scheduled = true;

		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
	}

	/**
	 * Asks every registered provider to register its REST routes.
	 *
	 * @return void
	 */
	public static function register_rest_routes() {
		foreach ( self::all() as $provider ) {
			if ( method_exists( $provider, 'register_rest_routes' ) ) {
				$provider->register_rest_routes();
			}
		}
	}

	/**
	 * Returns a provider by slug, or null.
	 *
	 * @param string $id Provider slug.
	 * @return Provider|null
	 */
	public static function get( $id ) {
		self::load();

		$id = self::sanitize_provider_id( $id );

		return isset( self::$providers[ $id ] ) ? self::$providers[ $id ] : null;
	}

	/**
	 * Returns every registered provider.
	 *
	 * @return array<string,Provider>
	 */
	public static function all() {
		self::load();

		return self::$providers;
	}

	/**
	 * Returns only providers whose is_configured() is true. An unconfigured
	 * provider is a normal state — the feature simply refuses to grant.
	 *
	 * @return array<string,Provider>
	 */
	public static function available() {
		return array_filter(
			self::all(),
			/**
			 * Evaluates provider configured-ness.
			 *
			 * @param Provider $provider The provider.
			 * @return bool
			 */
			function ( Provider $provider ) {
				return $provider->is_configured();
			}
		);
	}

	/**
	 * The configured provider to use: the admin-selected one when valid and
	 * configured, else the first configured provider, else null.
	 *
	 * @return Provider|null
	 */
	public static function configured() {
		self::load();

		$selected = sanitize_key( (string) Settings::get( 'ads.provider', '' ) );

		if ( '' !== $selected ) {
			$provider = isset( self::$providers[ $selected ] ) ? self::$providers[ $selected ] : null;

			if ( $provider && $provider->is_configured() ) {
				return $provider;
			}
		}

		foreach ( self::available() as $provider ) {
			return $provider;
		}

		return null;
	}

	/**
	 * Forces a full rebuild (call after saving provider settings).
	 *
	 * @return void
	 */
	public static function reset() {
		self::$loaded    = false;
		self::$providers = array();
	}

	/**
	 * Sanitizes a provider slug.
	 *
	 * @param string $id Raw slug.
	 * @return string
	 */
	private static function sanitize_provider_id( $id ) {
		return substr( strtolower( preg_replace( '/[^a-z0-9_-]/i', '', (string) $id ) ), 0, 32 );
	}
}
