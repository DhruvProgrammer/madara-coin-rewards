<?php
/**
 * Generic signed server-to-server (S2S) ad postback provider.
 *
 * The recommended production path for rewarded ads. The ad network calls
 * the plugin's REST route when a rewarded ad genuinely completes:
 *
 *     POST /wp-json/mcr/v1/ads/postback
 *     Content-Type: application/json
 *     X-MCR-Signature: <hex HMAC-SHA256 of the raw body>
 *
 * Site owner configuration:
 *  - postback_secret: shared HMAC secret (generated here and pasted into
 *    the ad network's S2S config, or taken from their dashboard).
 *  - signature_header: defaults to `X-MCR-Signature`.
 *  - timestamp_header: defaults to `X-MCR-Timestamp` (replay tolerance 300s).
 *  - transaction_id_field: the JSON field carrying the network's unique
 *    transaction id (defaults to `transaction_id`).
 *  - ip_allowlist (optional, best effort): REMOTE_ADDR allowlist.
 *
 * The postback URL is plugin-generated and exposed through get_ad_units()
 * params / the settings screen; the network's dashboard needs it:
 *     {site}/wp-json/mcr/v1/ads/postback
 *
 * TRUST: HIGH only when the network genuinely performs S2S callbacks and
 * the signature verifies. A reward amount reported by the network is never
 * trusted: coins come from the server-side session row only.
 *
 * @package MCR
 */

namespace MCR\AdProviders;

use MCR\Ledger;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * HMAC-signed postback ad provider.
 */
class GenericS2S extends Provider {

	/**
	 * REST namespace route consumed by the ad network.
	 */
	const POSTBACK_ROUTE = '/mcr/v1/ads/postback';

	/**
	 * Default signature replay tolerance (seconds).
	 */
	const TIMESTAMP_TOLERANCE = 300;

	/**
	 * Default signature header name.
	 */
	const DEFAULT_SIGNATURE_HEADER = 'X-MCR-Signature';

	/**
	 * Default timestamp header name.
	 */
	const DEFAULT_TIMESTAMP_HEADER = 'X-MCR-Timestamp';

	/**
	 * Whether the postback route was already registered.
	 *
	 * @var bool
	 */
	private static $routes_registered = false;

	/**
	 * @return string
	 */
	public function get_id() {
		return 'generic_s2s';
	}

	/**
	 * @return string
	 */
	public function get_title() {
		return __( 'Verified postback (S2S)', 'madara-coin-rewards' );
	}

	/**
	 * @return string
	 */
	public function get_description() {
		return __( 'High-trust rewarded ads: the ad network itself calls your site after a real ad completion and the callback is HMAC-signed. Requires a network that supports server-to-server callbacks.', 'madara-coin-rewards' );
	}

	/**
	 * Configured when the shared secret is present. The postback URL is
	 * plugin-generated (rest_url), so no external URL is needed.
	 *
	 * @return bool
	 */
	public function is_configured() {
		return '' !== trim( (string) $this->get_setting( 'postback_secret', '' ) );
	}

	/**
	 * The plugin-generated postback URL the admin pastes into the network.
	 *
	 * @return string
	 */
	public function get_postback_url() {
		if ( ! function_exists( 'rest_url' ) ) {
			return home_url( '/wp-json' . self::POSTBACK_ROUTE );
		}

		return rest_url( self::POSTBACK_ROUTE );
	}

	/**
	 * The shared transaction secret (alias kept for readability).
	 *
	 * @return string
	 */
	private function postback_secret() {
		return $this->secret();
	}

	/**
	 * Single rewarded ad unit built from the configured placement.
	 *
	 * @return array Empty when not configured.
	 */
	public function get_ad_units() {
		if ( ! $this->is_configured() ) {
			return array();
		}

		return array(
			array(
				'id'     => sanitize_key( (string) $this->get_setting( 'placement_id', '' ) ),
				'title'  => __( 'Rewarded video (S2S verified)', 'madara-coin-rewards' ),
				'type'   => 'rewarded_video',
				'params' => array(
					'placement_id' => sanitize_key( (string) $this->get_setting( 'placement_id', '' ) ),
					'postback_url' => $this->get_postback_url(),
				),
			),
		);
	}

	/**
	 * Validates one session start against the configured unit.
	 *
	 * @param array $args { user_id, ad_unit_id, reward_coins }.
	 * @return array|WP_Error array{ad_unit:array,sdk_params:array,expires:int}|WP_Error.
	 */
	public function create_session( array $args ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error(
				'mcr_s2s_not_configured',
				__( 'Ad rewards are not configured on this site. No coins can be earned from ads right now.', 'madara-coin-rewards' ),
				array( 'status' => 503 )
			);
		}

		$units = $this->get_ad_units();

		if ( empty( $units ) ) {
			return new WP_Error(
				'mcr_s2s_no_units',
				__( 'No ad units are configured for this provider.', 'madara-coin-rewards' ),
				array( 'status' => 503 )
			);
		}

		$requested = sanitize_key( (string) ( $args['ad_unit_id'] ?? '' ) );

		if ( '' === $requested ) {
			return new WP_Error(
				'mcr_s2s_unknown_unit',
				__( 'This ad unit is not available.', 'madara-coin-rewards' ),
				array( 'status' => 404 )
			);
		}

		$unit = null;

		foreach ( $units as $candidate ) {
			if ( (string) $candidate['id'] === $requested ) {
				$unit = $candidate;
				break;
			}
		}

		if ( null === $unit ) {
			return new WP_Error(
				'mcr_s2s_unknown_unit',
				__( 'This ad unit is not available.', 'madara-coin-rewards' ),
				array( 'status' => 404 )
			);
		}

		$user_id = absint( $args['user_id'] ?? 0 );

		if ( ! $user_id ) {
			return new WP_Error(
				'mcr_invalid_user',
				__( 'Invalid user.', 'madara-coin-rewards' ),
				array( 'status' => 400 )
			);
		}

		return array(
			'ad_unit'          => $unit,
			'sdk_params'       => array(
				'placement_id' => (string) $unit['params']['placement_id'],
				// The network's server posts here on completion.
				'postback_url' => (string) $unit['params']['postback_url'],
			),
			// The reward is echo'd for display only; the authoritative amount
			// is re-read from the session row at credit time.
			'reward_coins'     => absint( $args['reward_coins'] ?? 0 ),
			'signature_header' => $this->signature_header(),
			'timestamp_header' => $this->timestamp_header(),
		);
	}

	/**
	 * Client-side completion check for the policy pipeline.
	 *
	 * An S2S network does not hand the browser a verifiable completion
	 * token: proof only arrives via the signed postback on the server.
	 * verify_completion() therefore accepts a client completion ONLY when
	 * the signed postback for that exact transaction has already landed
	 * (row credited + token match). Otherwise it fails closed — awaiting
	 * the network is not a success, and "almost verified" grants nothing.
	 *
	 * @param array $payload { session_token, completion_token, user_id, ad_id }.
	 * @return true|WP_Error
	 */
	public function verify_completion( array $payload ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error(
				'mcr_s2s_not_configured',
				__( 'Ad rewards are not configured on this site. No coins can be earned from ads right now.', 'madara-coin-rewards' ),
				array( 'status' => 503 )
			);
		}

		global $wpdb;

		$session_token = sanitize_text_field( (string) ( $payload['session_token'] ?? '' ) );
		$completion    = sanitize_text_field( (string) ( $payload['completion_token'] ?? '' ) );
		$user_id       = absint( $payload['user_id'] ?? 0 );

		if ( '' === $session_token || ! preg_match( '/^[a-f0-9]{64}$/i', $session_token ) ) {
			return new WP_Error(
				'mcr_s2s_invalid_session',
				__( 'The ad session could not be verified.', 'madara-coin-rewards' ),
				array( 'status' => 400 )
			);
		}

		$table = $wpdb->prefix . 'mcr_ad_sessions';
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . $table . ' WHERE session_token = %s LIMIT 1', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$session_token
			),
			\ARRAY_A
		);

		if ( ! $row ) {
			return new WP_Error(
				'mcr_s2s_unknown_session',
				__( 'The ad session could not be verified.', 'madara-coin-rewards' ),
				array( 'status' => 404 )
			);
		}

		if ( absint( $row['user_id'] ) !== $user_id ) {
			return new WP_Error(
				'mcr_s2s_session_owner',
				__( 'The ad session does not belong to this account.', 'madara-coin-rewards' ),
				array( 'status' => 403 )
			);
		}

		$status  = (string) $row['status'];
		$proof   = sanitize_text_field( (string) $row['completion_token'] );

		if ( 'credited' === $status && '' !== $proof ) {
			// The signed postback already landed for this transaction.
			if ( '' === $completion || hash_equals( $proof, $completion ) ) {
				return true;
			}

			return new WP_Error(
				'mcr_s2s_proof_mismatch',
				__( 'The completion token does not match the network callback.', 'madara-coin-rewards' ),
				array( 'status' => 403 )
			);
		}

		return new WP_Error(
			'mcr_s2s_pending',
			__( 'The ad network has not confirmed this session yet. If the ad was really completed, the reward will appear automatically in a few seconds.', 'madara-coin-rewards' ),
			array( 'status' => 409 )
		);
	}

	/**
	 * Queues the postback route registration.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Registers POST /mcr/v1/ads/postback (permission: none — signature only;
	 * networks cannot send WordPress nonces).
	 *
	 * @return void
	 */
	public function register_rest_routes() {
		if ( self::$routes_registered || ! function_exists( 'register_rest_route' ) ) {
			return;
		}

		self::$routes_registered = true;

		register_rest_route(
			'mcr/v1',
			'/ads/postback',
			array(
				'methods'             => 'POST',
				'callback'            => function ( WP_REST_Request $request ) {
					return $this->handle_postback( $request );
				},
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * The signed postback receiver: the ONLY path that credits S2S rewards.
	 *
	 * Steps: signature (+ timestamp replay window) → payload shape →
	 * replay check on the transaction id → session lookup → owner/expiry/
	 * state checks → reward sanity → atomic single-use transition → ledger
	 * credit idempotent on `ad_session:{row_id}`.
	 *
	 * @param WP_REST_Request $request The network's request.
	 * @return WP_REST_Response
	 */
	public function handle_postback( WP_REST_Request $request ) {
		if ( ! $this->is_configured() ) {
			return new WP_REST_Response( array( 'error' => 'not_configured' ), 503 );
		}

		$raw = (string) $request->get_body();
		$sig = (string) $request->get_header( strtolower( $this->signature_header() ) );

		if ( '' === $raw || '' === $sig ) {
			return new WP_REST_Response( array( 'error' => 'missing_credentials' ), 400 );
		}

		// Optional replay window via the timestamp header.
		$ts_header = $this->timestamp_header();

		if ( '' !== $ts_header ) {
			$ts = (int) $request->get_header( strtolower( $ts_header ) );

			if ( $ts <= 0 ) {
				return new WP_REST_Response( array( 'error' => 'missing_timestamp' ), 400 );
			}

			if ( abs( time() - $ts ) > $this->tolerance() ) {
				return new WP_REST_Response( array( 'error' => 'stale_timestamp' ), 400 );
			}
		}

		// IP allowlist (best effort; REMOTE_ADDR is transport-truth only).
		if ( ! $this->ip_allowed() ) {
			$this->log_security_event(
				'ad_postback_ip_rejected',
				array( 'ip' => $this->request_ip() )
			);

			return new WP_REST_Response( array( 'error' => 'ip_not_allowed' ), 403 );
		}

		// Signature: HMAC-SHA256 over the exact raw bytes.
		$expected = hash_hmac( 'sha256', $raw, $this->secret() );

		if ( ! hash_equals( $expected, self::strip_signature_prefix( $sig ) ) ) {
			$this->log_security_event(
				'ad_postback_bad_signature',
				array( 'ip' => $this->request_ip() )
			);

			return new WP_REST_Response( array( 'error' => 'bad_signature' ), 403 );
		}

		$data = json_decode( $raw, true );

		if ( ! is_array( $data ) ) {
			return new WP_REST_Response( array( 'error' => 'bad_payload' ), 400 );
		}

		$session_token = sanitize_text_field( (string) ( $data['session_token'] ?? '' ) );
		$tx_field      = $this->transaction_id_field();
		$transaction   = isset( $data[ $tx_field ] ) ? sanitize_text_field( (string) $data[ $tx_field ] ) : '';

		if ( '' === $session_token || '' === $transaction ) {
			return new WP_REST_Response( array( 'error' => 'bad_payload' ), 400 );
		}

		if ( ! preg_match( '/^[a-f0-9]{64}$/i', $session_token ) || strlen( $transaction ) > 128 ) {
			return new WP_REST_Response( array( 'error' => 'bad_payload' ), 400 );
		}

		global $wpdb;

		$table = $wpdb->prefix . 'mcr_ad_sessions';

		// Provider-side replay: a transaction id may be used once, ever.
		$replayed = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM ' . $table . ' WHERE completion_token = %s AND status = %s LIMIT 1', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$transaction,
				'credited'
			)
		);

		if ( $replayed ) {
			// Already processed: 200 so the network stops retrying.
			return new WP_REST_Response( array( 'received' => true, 'duplicate' => true ), 200 );
		}

		$session = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . $table . ' WHERE session_token = %s LIMIT 1', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$session_token
			),
			\ARRAY_A
		);

		if ( ! $session ) {
			return new WP_REST_Response( array( 'error' => 'unknown_session' ), 404 );
		}

		if ( (string) $session['provider'] !== $this->get_id() ) {
			return new WP_REST_Response( array( 'error' => 'wrong_provider' ), 409 );
		}

		// Bind to a user when the network reports one (it should not invent it).
		if ( isset( $data['user_id'] ) && absint( $data['user_id'] ) !== absint( $session['user_id'] ) ) {
			$this->log_security_event(
				'ad_postback_user_mismatch',
				array(
					'ip'      => $this->request_ip(),
					'session' => absint( $session['id'] ),
				)
			);

			return new WP_REST_Response( array( 'error' => 'user_mismatch' ), 403 );
		}

		// Expiry: WordPress UTC/MySQL datetime strings, GMT arithmetic.
		$expires = strtotime( (string) $session['expires_at'] );

		if ( $expires && $expires < time() ) {
			return new WP_REST_Response( array( 'error' => 'expired' ), 410 );
		}

		// Reward sanity: the network may confirm, never raise, the amount.
		$coins = absint( $session['coins'] );

		if ( isset( $data['reward_amount'] ) && ! is_array( $data['reward_amount'] ) && absint( $data['reward_amount'] ) !== $coins ) {
			$this->log_security_event(
				'ad_postback_amount_mismatch',
				array(
					'ip'       => $this->request_ip(),
					'session'  => absint( $session['id'] ),
					'expected' => $coins,
					'given'    => absint( $data['reward_amount'] ),
				)
			);

			return new WP_REST_Response( array( 'error' => 'amount_mismatch' ), 422 );
		}

		if ( $coins <= 0 ) {
			return new WP_REST_Response( array( 'error' => 'invalid_amount' ), 422 );
		}

		//
		// The daily cap is enforced BEFORE the state transition, so a
		// concurrent postback cannot leave a 'credited' row with no coin.
		// A per-user advisory lock is held across the check and the
		// transition, so two simultaneous completions cannot both read
		// the same count and both pass it.
		//
		$sessions = \MCR\Rewards\AdSessions::instance();
		$lock     = $sessions->lock_user( absint( $session['user_id'] ) );

		$cap = $sessions->cap_guard( absint( $session['user_id'] ), absint( $session['id'] ) );

		if ( is_wp_error( $cap ) ) {
			$sessions->unlock_user( $lock );

			// The reward is not granted. The session is expired rather than
			// left pending, so the network does not retry forever.
			$wpdb->query(
				$wpdb->prepare(
				"UPDATE {$table}
					SET status = 'expired', verified_at = %s
					WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					current_time( 'mysql', true ),
					absint( $session['id'] )
				)
			);

			$this->log_security_event(
				'ad_postback_daily_limit_reached',
				array(
					'session' => absint( $session['id'] ),
					'user_id'  => absint( $session['user_id'] ),
				)
			);

			return new \WP_REST_Response( array( 'received' => true, 'credited' => false, 'reason' => 'daily_limit' ), 200 );
		}

		// Atomic single-use transition: only 'pending' sessions may become
		// 'credited', and only this UPDATE wins the race.
		//
		$affected = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
					SET status = 'credited', completion_token = %s, verified_at = %s
				 WHERE session_token = %s AND status = 'pending'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$transaction,
				current_time( 'mysql', true ),
				$session_token
			)
		);

		if ( 1 !== (int) $affected ) {
			$sessions->unlock_user( $lock );

			// Lost the race or an invalid state: duplicates answer 200.
			return new WP_REST_Response( array( 'received' => true, 'duplicate' => true ), 200 );
		}

		// Credit with the policy-exact key so a double grant is impossible
		// no matter which pipeline got there first.
		try {
			$credit = Ledger::instance()->credit(
				absint( $session['user_id'] ),
				$coins,
				'ad_reward',
				__( 'Rewarded ad (network verified)', 'madara-coin-rewards' ),
				array(
					'idempotency_key' => 'ad_session:' . absint( $session['id'] ),
					'source'          => 'ad:' . $this->get_id(),
					'ref_type'        => 'ad_session',
					'ref_id'          => absint( $session['id'] ),
				)
			);
		} catch ( \Throwable $e ) {
			$sessions->unlock_user( $lock );

			// Roll the session back to pending so the network retry can
			// redo the grant (the SAME idempotency key guarantees no double credit).
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table}
						SET status = 'pending', verified_at = '0000-00-00 00:00:00'
					 WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					absint( $session['id'] )
				)
			);

			$this->log_security_event(
				'ad_postback_credit_failed',
				array( 'session' => absint( $session['id'] ) )
			);

			return new WP_REST_Response( array( 'received' => true, 'credited' => false ), 500 );
		}

		$sessions->unlock_user( $lock );

		if ( is_wp_error( $credit ) ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table}
						SET status = 'pending', verified_at = '0000-00-00 00:00:00'
					 WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					absint( $session['id'] )
				)
			);

			$this->log_security_event(
				'ad_postback_credit_failed',
				array(
					'session' => absint( $session['id'] ),
					'code'    => sanitize_key( $credit->get_error_code() ),
				)
			);

			return new WP_REST_Response( array( 'received' => true, 'credited' => false ), 500 );
		}

		return new WP_REST_Response(
			array(
				'received'  => true,
				'credited'  => true,
				'amount'    => $coins,
				'duplicate' => ! empty( $credit['duplicate'] ),
			),
			200
		);
	}

	/**
	 * The configured signature header name.
	 *
	 * @return string
	 */
	public function signature_header() {
		$header = sanitize_text_field( (string) $this->get_setting( 'signature_header', self::DEFAULT_SIGNATURE_HEADER ) );

		return '' !== $header ? $header : self::DEFAULT_SIGNATURE_HEADER;
	}

	/**
	 * The configured timestamp header name ('' disables the replay window).
	 *
	 * @return string
	 */
	public function timestamp_header() {
		$header = sanitize_text_field( (string) $this->get_setting( 'timestamp_header', self::DEFAULT_TIMESTAMP_HEADER ) );

		return $header;
	}

	/**
	 * The configured timestamp tolerance in seconds.
	 *
	 * @return int
	 */
	public function tolerance() {
		$tolerance = absint( $this->get_setting( 'timestamp_tolerance', self::TIMESTAMP_TOLERANCE ) );

		return $tolerance > 0 ? $tolerance : self::TIMESTAMP_TOLERANCE;
	}

	/**
	 * The JSON field carrying the network's transaction id.
	 *
	 * @return string
	 */
	public function transaction_id_field() {
		$field = sanitize_key( (string) $this->get_setting( 'transaction_id_field', 'transaction_id' ) );

		return '' !== $field ? $field : 'transaction_id';
	}

	/**
	 * The shared secret.
	 *
	 * @return string
	 */
	private function secret() {
		return trim( (string) $this->get_setting( 'postback_secret', '' ) );
	}

	/**
	 * Whether the request IP is on the optional allowlist.
	 *
	 * @return bool True when no allowlist is configured.
	 */
	private function ip_allowed() {
		$raw = (string) $this->get_setting( 'ip_allowlist', '' );

		if ( '' === trim( $raw ) ) {
			return true;
		}

		$allowed = array();

		foreach ( preg_split( '/[\s,]+/', $raw ) as $entry ) {
			$entry = trim( (string) $entry );

			if ( '' !== $entry && false !== filter_var( $entry, FILTER_VALIDATE_IP ) ) {
				$allowed[] = $entry;
			}
		}

		if ( empty( $allowed ) ) {
			return true;
		}

		return in_array( $this->request_ip(), $allowed, true );
	}

	/**
	 * Strips an optional "sha256=" (or any algo=) prefix from a signature.
	 *
	 * @param string $sig   Signature value.
	 * @param bool   $plain When true no validation happens (identity).
	 * @return string
	 */
	private static function strip_signature_prefix( $sig, $plain = false ) {
		$sig = trim( (string) $sig );

		if ( $plain ) {
			return $sig;
		}

		return (string) preg_replace( '/^[A-Za-z0-9]+=/i', '', $sig );
	}

	/**
	 * Admin settings fields (generic render contract).
	 *
	 * @return array
	 */
	public function get_settings_fields() {
		return array(
			array(
				'id'          => 'postback_secret',
				'label'       => __( 'Shared secret', 'madara-coin-rewards' ),
				'type'        => 'password',
				'description' => __( 'The HMAC secret shared with the ad network: paste the same value into the network server-server (S2S) callback settings. Without it no coin is ever granted.', 'madara-coin-rewards' ),
			),
			array(
				'id'          => 'placement_id',
				'label'       => __( 'Placement ID', 'madara-coin-rewards' ),
				'type'        => 'text',
				'description' => __( 'The rewarded placement id as configured in the ad network.', 'madara-coin-rewards' ),
			),
			array(
				'id'          => 'signature_header',
				'label'       => __( 'Signature header', 'madara-coin-rewards' ),
				'type'        => 'text',
				'description' => __( 'Header carrying the HMAC. Default: ' . self::DEFAULT_SIGNATURE_HEADER . '.', 'madara-coin-rewards' ),
				'default'     => self::DEFAULT_SIGNATURE_HEADER,
			),
			array(
				'id'          => 'timestamp_header',
				'label'       => __( 'Timestamp header', 'madara-coin-rewards' ),
				'type'        => 'text',
				'description' => __( 'Header carrying a unix timestamp for the 300 second replay window; leave empty to disable that check. Default: ' . self::DEFAULT_TIMESTAMP_HEADER . '.', 'madara-coin-rewards' ),
				'default'     => self::DEFAULT_TIMESTAMP_HEADER,
			),
			array(
				'id'          => 'timestamp_tolerance',
				'label'       => __( 'Timestamp tolerance (seconds)', 'madara-coin-rewards' ),
				'type'        => 'number',
				'description' => __( 'How old a signed callback may be. Default: 300 (Stripe-style ±5 minutes).', 'madara-coin-rewards' ),
				'default'     => self::TIMESTAMP_TOLERANCE,
			),
			array(
				'id'          => 'transaction_id_field',
				'label'       => __( 'Transaction id field', 'madara-coin-rewards' ),
				'type'        => 'text',
				'description' => __( 'JSON field of the callback carrying the network transaction id. Default: transaction_id.', 'madara-coin-rewards' ),
				'default'     => 'transaction_id',
			),
			array(
				'id'          => 'ip_allowlist',
				'label'       => __( 'Callback IP allowlist (optional)', 'madara-coin-rewards' ),
				'type'        => 'textarea',
				'description' => __( 'Comma or line separated IP addresses the network calls from. Best effort behind proxies — signature verification remains the real gate. Leave empty to allow all.', 'madara-coin-rewards' ),
			),
			array(
				'id'          => 'postback_url',
				'label'       => __( 'Postback URL for the network', 'madara-coin-rewards' ),
				'type'        => 'text',
				'description' => $this->get_postback_url(),
				'default'     => '',
			),
		);
	}
}
