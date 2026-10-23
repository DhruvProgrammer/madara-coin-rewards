<?php
/**
 * Abstract ad provider.
 *
 * An ad provider is the source of truth for "was this rewarded ad really
 * completed?". Clients (and the network's client SDK) can never decide
 * that themselves: value is granted only through server verification.
 *
 * HONESTY CONTRACT: an unconfigured or untrusted provider is allowed to
 * exist, but it must say so — is_configured() returns false,
 * verify_completion() returns WP_Error and no coin ever moves. A "verified"
 * result that the backend cannot actually verify is a lie, and no provider
 * here tells lies.
 *
 * @package MCR
 */

namespace MCR\AdProviders;

use MCR\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Base class for rewarded-ad providers.
 */
abstract class Provider {

	/**
	 * Per-provider settings, stored under `ads.providers.{id}` in Settings.
	 *
	 * @var array
	 */
	protected $settings;

	/**
	 * Constructor.
	 *
	 * @param array $settings Provider settings array.
	 */
	public function __construct( array $settings = array() ) {
		$this->settings = $settings;
	}

	/**
	 * Unique provider slug ([a-z0-9_-]).
	 *
	 * @return string
	 */
	abstract public function get_id();

	/**
	 * Human readable provider name.
	 *
	 * @return string
	 */
	abstract public function get_title();

	/**
	 * Optional description of what the provider actually verifies
	 * (trust level documentation belongs here too).
	 *
	 * @return string
	 */
	public function get_description() {
		return '';
	}

	/**
	 * Whether the provider's credentials are present. Cheap, no network.
	 * When false, the whole ads feature must refuse to grant anything.
	 *
	 * @return bool
	 */
	abstract public function is_configured();

	/**
	 * The ad units the provider can serve.
	 *
	 * @return array<int,array{id:string,title:string,type:string,params:array}>
	 *               type: 'rewarded_video'|'interstitial'|'display'.
	 */
	abstract public function get_ad_units();

	/**
	 * Issues the SDK/data for one ad watch attempt. The server-side
	 * session token / signing is the controller's job; the provider only
	 * validates the unit and returns the SDK payload.
	 *
	 * @param array $args { user_id: int, ad_unit_id: string, reward_coins: int }.
	 * @return array|WP_Error array{ad_unit:array,sdk_params:array}|WP_Error.
	 */
	abstract public function create_session( array $args );

	/**
	 * Verifies the completion proof for one watch attempt.
	 *
	 * @param array $payload { session_token: string, completion_token: string,
	 *                        user_id: int, ad_id: string }.
	 * @return true|WP_Error True when the completion is verified, a WP_Error
	 *                       (with a `status` datum for HTTP responses) when
	 *                       it may not be trusted.
	 */
	abstract public function verify_completion( array $payload );

	/**
	 * Admin settings field descriptors for generic rendering.
	 *
	 * Each field: array( id, label, type, description, options?, default? ).
	 *
	 * @return array
	 */
	abstract public function get_settings_fields();

	/**
	 * Hook registration, invoked by the Registry after instantiation.
	 *
	 * @return void
	 */
	public function register_hooks() {}

	/**
	 * REST route registration, invoked on rest_api_init by the Registry.
	 *
	 * @return void
	 */
	public function register_rest_routes() {}

	/**
	 * Returns a provider setting.
	 *
	 * @param string $key           Setting key.
	 * @param mixed  $default_value Default when unset.
	 * @return mixed
	 */
	protected function get_setting( $key, $default_value = null ) {
		return isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : $default_value;
	}

	/**
	 * Loads a provider's stored settings (Settings key `ads.providers.{id}`).
	 *
	 * @param string $provider_id Provider slug.
	 * @return array
	 */
	public static function load_settings( $provider_id ) {
		$settings = Settings::get( 'ads.providers.' . sanitize_key( (string) $provider_id ), array() );

		return is_array( $settings ) ? $settings : array();
	}

	/**
	 * Persists a provider's settings (scalars sanitized on the way in).
	 *
	 * @param string $provider_id Provider slug.
	 * @param array  $values      Raw settings.
	 * @return bool
	 */
	public static function save_settings( $provider_id, array $values ) {
		$clean = array();

		foreach ( $values as $key => $value ) {
			$key = sanitize_key( (string) $key );

			if ( '' === $key ) {
				continue;
			}

			$clean[ $key ] = self::sanitize_setting_value( $value );
		}

		return Settings::update( array( 'ads.providers.' . sanitize_key( (string) $provider_id ) => $clean ) );
	}

	/**
	 * Recursively sanitizes one setting value.
	 *
	 * @param mixed $value Raw value.
	 * @return mixed
	 */
	private static function sanitize_setting_value( $value ) {
		if ( is_array( $value ) ) {
			return array_map( array( __CLASS__, 'sanitize_setting_value' ), $value );
		}

		return sanitize_text_field( (string) $value );
	}

	/**
	 * Server-side security/fraud event sink. Never leaks details to any
	 * HTTP response.
	 *
	 * @param string $event   Machine key.
	 * @param array  $context Context data.
	 * @return void
	 */
	protected function log_security_event( $event, array $context = array() ) {
		$event = sanitize_key( (string) $event );

		$line = array( 'event' => $event );

		foreach ( $context as $key => $value ) {
			$key = sanitize_key( (string) $key );

			$line[ $key ] = is_scalar( $value )
				? (string) ( is_bool( $value ) ? ( $value ? 'true' : 'false' ) : $value )
				: wp_json_encode( $value );
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[mcr-security] ' . wp_json_encode( $line ) );
		}

		/**
		 * Fires for every security-relevant event in the ad layer.
		 *
		 * @param string $event Machine event key.
		 * @param array  $line  Sanitized context.
		 */
		do_action( 'mcr_security_event', $event, $line );
	}

	/**
	 * The client IP (best effort, logging only — never authorization).
	 *
	 * @return string
	 */
	protected function request_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		return substr( $ip, 0, 45 );
	}
}
