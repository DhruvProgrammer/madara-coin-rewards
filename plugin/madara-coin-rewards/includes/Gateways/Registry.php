<?php
/**
 * Gateway registry.
 *
 * Single access point for every payment gateway. Gateways are instantiated
 * lazily from their stored settings and can be extended by third parties
 * through the `mcr_register_gateways` filter.
 *
 * Call Registry::load() once early (plugins_loaded / init). The registry
 * schedules its rest_api_init hook on first load so webhook gateways get
 * their routes registered.
 *
 * @package MCR
 */

namespace MCR\Gateways;

defined( 'ABSPATH' ) || exit;

/**
 * Creates and keeps the gateway instances.
 */
final class Registry {

	/**
	 * @var array<string,Gateway>
	 */
	private static $gateways = array();

	/**
	 * @var bool
	 */
	private static $loaded = false;

	/**
	 * @var bool
	 */
	private static $hooks_scheduled = false;

	/**
	 * Builds all gateways. Safe to call more than once.
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

			// Built-in gateways, normalized to class-name strings first.
			'stripe'      => Stripe::class,
			'woocommerce' => WooCommerce::class,
			'manual'      => Manual::class,
		);

		/**
		 * Filters the registered payment gateways.
		 *
		 * Values may be Gateway instances or class-name strings.
		 *
		 * @param array $instances Current instances, keyed by gateway slug.
		 */
		$instances = apply_filters( 'mcr_register_gateways', $instances );

		foreach ( $instances as $id => $gateway ) {
			if ( is_string( $gateway ) && class_exists( $gateway ) ) {
				$settings = Gateway::load_settings( $id );
				$settings = is_array( $settings ) ? $settings : array();

				$gateway = new $gateway( $settings );
			}

			if ( $gateway instanceof Gateway ) {
				$gateway->register_hooks();
				self::$gateways[ self::sanitize_gateway_id( $gateway->get_id() ) ] = $gateway;
			}
		}
	}

	/**
	 * Schedules the REST route registration hook once.
	 *
	 * Returns early once done, so repeated load() calls are cheap.
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
	 * Asks every registered gateway to register its REST routes.
	 *
	 * @return void
	 */
	public static function register_rest_routes() {
		foreach ( self::all() as $gateway ) {
			if ( method_exists( $gateway, 'register_rest_routes' ) ) {
				$gateway->register_rest_routes();
			}
		}
	}

	/**
	 * Returns a gateway by slug, or null.
	 *
	 * @param string $id Gateway slug.
	 * @return Gateway|null
	 */
	public static function get( $id ) {
		self::load();

		$id = self::sanitize_gateway_id( $id );

		return isset( self::$gateways[ $id ] ) ? self::$gateways[ $id ] : null;
	}

	/**
	 * Returns every registered gateway.
	 *
	 * @return array<string,Gateway>
	 */
	public static function all() {
		self::load();

		return self::$gateways;
	}

	/**
	 * Returns only gateways whose is_available() is true.
	 *
	 * @return array<string,Gateway>
	 */
	public static function available() {
		return array_filter(
			self::all(),
			/**
			 * Evaluates gateway availability.
			 *
			 * @param Gateway $gateway The gateway.
			 * @return bool
			 */
			function ( Gateway $gateway ) {
				return $gateway->is_available();
			}
		);
	}

	/**
	 * The default gateway id: the configured default when available,
	 * otherwise the first available gateway.
	 *
	 * @return string Empty when no gateway is available.
	 */
	public static function default_id() {
		self::load();

		$default = \MCR\Settings::get( 'purchase.gateway', 'manual' );
		$default = sanitize_key( (string) $default );

		$available = self::available();

		if ( '' !== $default && isset( $available[ $default ] ) ) {
			return $default;
		}

		$ids = array_keys( $available );

		return ! empty( $ids ) ? (string) $ids[0] : '';
	}

	/**
	 * Forces a full rebuild (call after saving gateway settings).
	 *
	 * @return void
	 */
	public static function reset() {
		self::$loaded = false;
		self::$gateways = array();
	}

	/**
	 * Sanitizes a gateway slug.
	 *
	 * @param string $id Raw slug.
	 * @return string
	 */
	private static function sanitize_gateway_id( $id ) {
		return substr( strtolower( preg_replace( '/[^a-z0-9_-]/i', '', (string) $id ) ), 0, 32 );
	}
}
