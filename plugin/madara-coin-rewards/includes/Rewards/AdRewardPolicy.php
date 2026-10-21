<?php
/**
 * Ad reward policy.
 *
 * The rules that decide whether an ad view may become coins. All checks are
 * server-side; the client is never trusted to assert that an ad was watched.
 *
 * @package MCR
 */

namespace MCR\Rewards;

use MCR\Ledger\LedgerInterface;
use MCR\Support\Clock;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Decides ad reward eligibility and grants verified rewards.
 */
class AdRewardPolicy {

	/**
	 * Clock instance.
	 *
	 * @var Clock
	 */
	private $clock;

	/**
	 * Ledger instance.
	 *
	 * @var LedgerInterface
	 */
	private $ledger;

	/**
	 * Configured ad provider, when one exists.
	 *
	 * @var object|null
	 */
	private $provider;

	/**
	 * Constructor.
	 *
	 * @param Clock           $clock    Clock instance.
	 * @param LedgerInterface $ledger   Ledger instance.
	 * @param object|null     $provider Ad provider implementing the provider contract.
	 */
	public function __construct( Clock $clock, LedgerInterface $ledger, $provider = null ) {
		$this->clock    = $clock;
		$this->ledger   = $ledger;
		$this->provider = $provider;
	}

	/**
	 * Whether an ad provider is configured and ready.
	 *
	 * @return bool
	 */
	public function providerConfigured() {
		if ( ! is_object( $this->provider ) ) {
			return false;
		}

		if ( method_exists( $this->provider, 'is_configured' ) ) {
			return (bool) $this->provider->is_configured();
		}

		return true;
	}

	/**
	 * Evaluates whether a new ad session may start.
	 *
	 * @param int   $user_id User id.
	 * @param array $options Options:
	 *                       - daily_limit       int  Maximum views per day.
	 *                       - global_daily_limit int Maximum views per day across all ads.
	 *                       - cooldown_seconds  int  Minimum gap between views.
	 *                       - watched_today     int  Views already completed today.
	 *                       - watched_global    int  Views completed today across all ads.
	 *                       - last_watch_at     int  Timestamp of the last completed view.
	 * @return array|WP_Error
	 */
	public function requestSession( $user_id, array $options = array() ) {
		$user_id = (int) $user_id;

		if ( $user_id <= 0 ) {
			return new \WP_Error( 'mcr_invalid_user', __( 'Invalid user.', 'madara-coin-rewards' ) );
		}

		if ( ! $this->providerConfigured() ) {
			return new \WP_Error(
				'mcr_provider_not_configured',
				__( 'Rewarded ads are not configured on this site yet.', 'madara-coin-rewards' ),
				array( 'status' => 503 )
			);
		}

		$options = wp_parse_args(
			$options,
			array(
				'daily_limit'        => 0,
				'global_daily_limit' => 0,
				'cooldown_seconds'   => 0,
				'watched_today'      => 0,
				'watched_global'     => 0,
				'last_watch_at'      => 0,
				'ad_id'              => '',
				'coins'              => 0,
				'session_ttl'        => 900,
			)
		);

		if ( $options['daily_limit'] > 0 && $options['watched_today'] >= $options['daily_limit'] ) {
			return new \WP_Error(
				'mcr_daily_limit_reached',
				__( 'You have reached the daily ad reward limit.', 'madara-coin-rewards' ),
				array( 'status' => 429 )
			);
		}

		if ( $options['global_daily_limit'] > 0 && $options['watched_global'] >= $options['global_daily_limit'] ) {
			return new \WP_Error(
				'mcr_daily_limit_reached',
				__( 'You have reached the daily ad reward limit.', 'madara-coin-rewards' ),
				array( 'status' => 429 )
			);
		}

		$cooldown = (int) $options['cooldown_seconds'];

		if ( $cooldown > 0 && $options['last_watch_at'] > 0 ) {
			$elapsed = $this->clock->now()->getTimestamp() - (int) $options['last_watch_at'];

			if ( $elapsed < $cooldown ) {
				return new \WP_Error(
					'mcr_cooldown_active',
					sprintf(
						/* translators: %d: number of seconds remaining */
						__( 'Please wait %d seconds before watching another ad.', 'madara-coin-rewards' ),
						$cooldown - $elapsed
					),
					array(
						'status'  => 429,
						'retry_after' => $cooldown - $elapsed,
					)
				);
			}
		}

		$session_token = $this->generate_session_token();

		$payload = $this->sign_payload(
			array(
				'user_id'      => $user_id,
				'session'      => $session_token,
				'coins'        => (int) $options['coins'],
				'expires'      => $this->clock->now()->getTimestamp() + (int) $options['session_ttl'],
				'nonce'        => $this->clock->now()->getTimestamp(),
				'ad_id'        => (string) $options['ad_id'],
				'day_key'      => $this->clock->today_key(),
			)
		);

		return array(
			'session_token' => $session_token,
			'signature'     => $payload['signature'],
			'coins'         => (int) $options['coins'],
			'expires_at'    => $payload['expires'],
			'ad_id'         => (string) $options['ad_id'],
			'day_key'       => $this->clock->today_key(),
		);
	}

	/**
	 * Verifies an ad completion and grants the reward.
	 *
	 * The provider's own verification runs server-to-server. When no provider
	 * is configured, or the provider cannot confirm the completion, no coins
	 * are granted.
	 *
	 * @param int   $user_id User id.
	 * @param array $payload Completion payload: session_token, completion_token, signature.
	 * @param array $options Options: session (row), coins, session_ttl, daily_limit, watched_today.
	 * @return array|WP_Error
	 */
	public function verify( $user_id, array $payload, array $options = array() ) {
		$user_id = (int) $user_id;

		if ( $user_id <= 0 ) {
			return new \WP_Error( 'mcr_invalid_user', __( 'Invalid user.', 'madara-coin-rewards' ) );
		}

		if ( ! $this->providerConfigured() ) {
			return new \WP_Error(
				'mcr_provider_not_configured',
				__( 'Rewarded ads are not configured on this site yet.', 'madara-coin-rewards' ),
				array( 'status' => 503 )
			);
		}

		$session_token = isset( $payload['session_token'] ) ? sanitize_text_field( (string) $payload['session_token'] ) : '';
		$completion    = isset( $payload['completion_token'] ) ? sanitize_text_field( (string) $payload['completion_token'] ) : '';

		if ( '' === $session_token || '' === $completion ) {
			return new \WP_Error(
				'mcr_invalid_completion',
				__( 'The ad completion could not be verified.', 'madara-coin-rewards' ),
				array( 'status' => 400 )
			);
		}

		$options = wp_parse_args(
			$options,
			array(
				'session'      => null,
				'coins'        => 0,
				'daily_limit'  => 0,
				'watched_today' => 0,
				'ref_id'       => 0,
			)
		);

		$session = $options['session'];

		if ( ! is_array( $session ) ) {
			return new \WP_Error(
				'mcr_session_not_found',
				__( 'The ad session was not found or has expired.', 'madara-coin-rewards' ),
				array( 'status' => 404 )
			);
		}

		if ( (int) $session['user_id'] !== $user_id ) {
			return new \WP_Error(
				'mcr_session_mismatch',
				__( 'The ad session does not belong to this account.', 'madara-coin-rewards' ),
				array( 'status' => 403 )
			);
		}

		/*
		 * Both terminal states mean the ad was watched and paid for:
		 * "verified" is the client-verification path and "credited" is
		 * the server-to-server path. Either of them means the reward has
		 * already been claimed.
		 */
		if ( in_array( $session['status'], array( 'verified', 'credited' ), true ) ) {
			return new \WP_Error(
				'mcr_already_verified',
				__( 'This ad reward has already been claimed.', 'madara-coin-rewards' ),
				array( 'status' => 409 )
			);
		}

		if ( 'pending' !== $session['status'] ) {
			return new WP_Error(
				'mcr_session_invalid',
				__( 'The ad session is no longer valid.', 'madara-coin-rewards' ),
				array( 'status' => 409 )
			);
		}

		$expires = strtotime( $session['expires_at'] . ' UTC' );

		if ( $expires && $expires < $this->clock->now()->getTimestamp() ) {
			return new WP_Error(
				'mcr_session_expired',
				__( 'The ad session has expired. Please watch the ad again.', 'madara-coin-rewards' ),
				array( 'status' => 410 )
			);
		}

		if ( $options['daily_limit'] > 0 && $options['watched_today'] >= $options['daily_limit'] ) {
			return new WP_Error(
				'mcr_daily_limit_reached',
				__( 'You have reached the daily ad reward limit.', 'madara-coin-rewards' ),
				array( 'status' => 429 )
			);
		}

		// The provider decides whether the ad really was completed. This call
		// is server-to-server; the client cannot influence its outcome.
		$verification = $this->provider->verify_completion(
			array(
				'completion_token' => $completion,
				'session_token'    => $session_token,
				'user_id'          => $user_id,
				'ad_id'            => (string) $session['ad_id'],
			)
		);

		if ( is_wp_error( $verification ) ) {
			return $verification;
		}

		if ( ! $verification ) {
			return new WP_Error(
				'mcr_verification_failed',
				__( 'The ad completion could not be verified.', 'madara-coin-rewards' ),
				array( 'status' => 403 )
			);
		}

		$coins = (int) $options['coins'];

		if ( $coins <= 0 ) {
			return new WP_Error(
				'mcr_invalid_amount',
				__( 'Invalid coin amount.', 'madara-coin-rewards' ),
				array( 'status' => 400 )
			);
		}

		$ref_id = (int) $options['ref_id'] ? (int) $options['ref_id'] : (int) $session['id'];

		$result = $this->ledger->credit(
			$user_id,
			$coins,
			'ad_reward',
			__( 'Rewarded ad completion', 'madara-coin-rewards' ),
			array(
				'idempotency_key' => 'ad_session:' . $ref_id,
				'source'          => 'ad:' . sanitize_key( (string) $session['provider'] ),
				'ref_type'        => 'ad_session',
				'ref_id'          => $ref_id,
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'coins'         => $coins,
			'balance'       => $result['balance_after'],
			'duplicate'     => ! empty( $result['duplicate'] ),
			'session_id'    => $ref_id,
			'transaction_id' => $result['transaction_id'],
		);
	}

	/**
	 * Generates a cryptographically random session token.
	 *
	 * @return string
	 */
	public function generate_session_token() {
		return bin2hex( random_bytes( 32 ) );
	}

	/**
	 * Signs a payload with the site's auth salt.
	 *
	 * @param array $payload Payload data.
	 * @return array Payload plus the signature and the raw string.
	 */
	public function sign_payload( array $payload ) {
		$payload['expires'] = isset( $payload['expires'] ) ? (int) $payload['expires'] : 0;

		$string = $this->payload_string( $payload );

		$signature = hash_hmac( 'sha256', $string, wp_salt( 'auth' ) );

		$payload['signature'] = $signature;

		return $payload;
	}

	/**
	 * Verifies a signed payload.
	 *
	 * @param array  $payload Payload including the signature.
	 * @param string $key     The key to compare against.
	 * @return bool
	 */
	public function verify_signature( array $payload, $key = 'signature' ) {
		$signature = isset( $payload[ $key ] ) ? (string) $payload[ $key ] : '';

		if ( '' === $signature ) {
			return false;
		}

		$copy = $payload;
		unset( $copy[ $key ] );

		$expected = hash_hmac( 'sha256', $this->payload_string( $copy ), wp_salt( 'auth' ) );

		return hash_equals( $expected, $signature );
	}

	/**
	 * Builds the canonical string that gets signed.
	 *
	 * @param array $payload Payload data.
	 * @return string
	 */
	private function payload_string( array $payload ) {
		$parts = array();

		foreach ( $payload as $key => $value ) {
			$parts[] = $key . '=' . (string) $value;
		}

		sort( $parts );

		return implode( '|', $parts );
	}
}
