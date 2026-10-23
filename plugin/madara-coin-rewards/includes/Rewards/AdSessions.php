<?php
/**
 * Ad session service.
 *
 * @package MCR
 */

namespace MCR\Rewards;

use MCR\AdProviders\Registry;
use MCR\Container;
use MCR\Ledger;
use MCR\Settings;
use MCR\Support\Clock;

defined( 'ABSPATH' ) || exit;

/**
 * Creates, verifies and expires rewarded-ad sessions.
 */
class AdSessions {

	/**
	 * Singleton instance.
	 *
	 * @var AdSessions|null
	 */
	private static $instance = null;

	/**
	 * Returns the shared instance.
	 *
	 * @return AdSessions
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Returns the configured ad provider.
	 *
	 * @return object|null
	 */
	/**
	 * The session states that count towards the ad reward caps.
	 *
	 * The client-verification path leaves a session in "verified" and the
	 * server-to-server path in "credited". Both mean the ad was watched
	 * and paid for, so both have to be counted. Keeping them in one list
	 * is what stops the two pipelines drifting apart - when they did, the
	 * daily cap silently stopped applying to the server-to-server path.
	 *
	 * @var string[]
	 */
	const COUNTED_STATUSES = array( 'verified', 'credited' );

	/**
	 * The SQL fragment that matches the counted session states.
	 *
	 * The states are emitted as placeholders and passed to prepare() as
	 * arguments, so the fragment is never interpolated into the query.
	 *
	 * @return string
	 */
	public static function counted_status_sql() {
		$quoted = array();

		foreach ( self::COUNTED_STATUSES as $status ) {
			// The states are a compile-time constant, so there is no
			// user input here and nothing to escape.
			$quoted[] = "'" . $status . "'";
		}

		return 'status IN ( ' . implode( ', ', $quoted ) . ' )';
	}

	public function provider() {
		$provider_id = (string) Settings::get( 'ads.provider', '' );

		if ( '' === $provider_id ) {
			return null;
		}

		return Registry::get( $provider_id );
	}

	/**
	 * Whether a provider is configured.
	 *
	 * @return bool
	 */
	public function isConfigured() {
		$provider = $this->provider();

		if ( ! is_object( $provider ) ) {
			return false;
		}

		if ( method_exists( $provider, 'is_configured' ) ) {
			return (bool) $provider->is_configured();
		}

		return true;
	}

	/**
	 * Starts a new ad session.
	 *
	 * @param int   $user_id User id.
	 * @param array $args    Optional ad_id.
	 * @return array|WP_Error
	 */
	public function start( $user_id, $args = array() ) {
		global $wpdb;

		$user_id = absint( $user_id );

		if ( ! $user_id ) {
			return new \WP_Error( 'mcr_not_logged_in', __( 'Please log in to watch ads and earn coins.', 'madara-coin-rewards' ) );
		}

		if ( ! Settings::featureEnabled( 'ads' ) ) {
			return new \WP_Error( 'mcr_feature_disabled', __( 'Ad rewards are currently disabled.', 'madara-coin-rewards' ) );
		}

		$clock = Container::instance()->clock();
		$table = $wpdb->prefix . 'mcr_ad_sessions';

		$ad_id  = isset( $args['ad_id'] ) ? sanitize_key( (string) $args['ad_id'] ) : 'rewarded_video';
		/*
		 * The rate limit the settings screen offers is applied here. It
		 * used to be stored and displayed but never read, so a session
		 * could be opened in an endless loop.
		 */
		if ( ! \MCR\Support\RateLimiter::allow(
			'ads',
			$user_id,
			(int) Settings::get( 'security.rate_limit_ads', 20 )
		) ) {
			return new \WP_Error(
				'mcr_rate_limited',
				__( 'You are requesting ad sessions too quickly. Please wait a moment and try again.', 'madara-coin-rewards' ),
				array( 'status' => 429 )
			);
		}

		$coins = (int) Settings::get( 'ads.coins_per_ad', 5 );
		$ttl   = max( 60, (int) Settings::get( 'ads.session_ttl', 900 ) );

		$policy = new AdRewardPolicy(
			$clock,
			Ledger::instance(),
			$this->provider()
		);

		$watched_today = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table}
				 WHERE user_id = %d
				   AND " . self::counted_status_sql() . "
				   AND verified_at >= %s
				   AND verified_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id,
				$clock->now()->setTime( 0, 0, 0 )->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ),
				$clock->now()->setTime( 0, 0, 0 )->modify( '+1 day' )->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' )
			)
		);

		$last_watch_at = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT UNIX_TIMESTAMP(MAX(verified_at)) FROM {$table} WHERE user_id = %d AND " . self::counted_status_sql() . "", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id
			)
		);

		/*
		 * The site-wide counter: every verified view by every user in the
		 * last 24 hours. It backs the global daily limit.
		 */
		$watched_global = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table}
				 WHERE " . self::counted_status_sql() . "
				   AND verified_at > %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				gmdate( 'Y-m-d H:i:s', $clock->now()->getTimestamp() - \DAY_IN_SECONDS )
			)
		);

		$session = $policy->requestSession(
			$user_id,
			array(
				'daily_limit'        => (int) Settings::get( 'ads.daily_limit', 10 ),
				'global_daily_limit' => (int) Settings::get( 'ads.global_daily_limit', 50 ),
				'cooldown_seconds'   => (int) Settings::get( 'ads.cooldown_seconds', 300 ),
				'watched_today'      => $watched_today,
				'watched_global'     => $watched_global,
				'last_watch_at'      => $last_watch_at,
				'ad_id'              => $ad_id,
				'coins'              => $coins,
				'session_ttl'        => $ttl,
			)
		);

		if ( is_wp_error( $session ) ) {
			return $session;
		}

		$inserted = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (user_id, ad_id, provider, session_token, completion_token, day_key, status, coins, ip, created_at, expires_at, verified_at)
				 VALUES (%d, %s, %s, %s, '', %s, 'pending', %d, %s, %s, %s, '1970-01-01 00:00:00')", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id,
				$ad_id,
				(string) Settings::get( 'ads.provider', '' ),
				$session['session_token'],
				$clock->today_key(),
				$coins,
				Settings::get( 'security.log_ip', 'on' ) ? $this->client_ip() : '',
				$clock->mysql_gmt(),
				gmdate( 'Y-m-d H:i:s', $session['expires_at'] )
			)
		);

		if ( false === $inserted ) {
			return new \WP_Error( 'mcr_session_failed', __( 'Could not start the ad session. Please try again.', 'madara-coin-rewards' ) );
		}

		$session['session_id'] = (int) $wpdb->insert_id;

		/**
		 * Fires after an ad session is created.
		 *
		 * @param int   $user_id User id.
		 * @param array $session Session data.
		 */
		do_action( 'mcr_after_ad_session_start', $user_id, $session );

		return $session;
	}

	/**
	 * Checks the daily ad reward cap immediately before a reward is granted.
	 *
	 * The cap is enforced at CREDIT time, not only when a session starts,
	 * so every grant path is covered. The count is taken from the
	 * verification timestamp rather than the session's day bucket, so a
	 * session started just before midnight and verified just after cannot
	 * be used to exceed the cap.
	 *
	 * @param int $user_id            User id.
	 * @param int $exclude_session_id Session to ignore (the one being granted).
	 * @return true|WP_Error
	 */
	public function cap_guard( $user_id, $exclude_session_id = 0 ) {
		global $wpdb;

		$user_id = absint( $user_id );

		if ( ! $user_id ) {
			return new \WP_Error( 'mcr_invalid_user', __( 'Invalid user.', 'madara-coin-rewards' ) );
		}

		$clock = Container::instance()->clock();
		$table = $wpdb->prefix . 'mcr_ad_sessions';

		$exclude      = absint( $exclude_session_id );
		$daily_limit  = (int) Settings::get( 'ads.daily_limit', 10 );
		$global_limit = (int) Settings::get( 'ads.global_daily_limit', 50 );

		/*
		 * The site-timezone day boundaries, expressed in GMT, because
		 * verified_at is stored in GMT.
		 */
		$day_start = $clock->now()->setTime( 0, 0, 0 );
		$day_end   = $day_start->modify( '+1 day' );

		if ( $daily_limit > 0 ) {
			$verified_today = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table}
					 WHERE user_id = %d
					   AND " . self::counted_status_sql() . "
					   AND verified_at >= %s
					   AND verified_at < %s
					   AND id <> %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$user_id,
					$day_start->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ),
					$day_end->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ),
					$exclude
				)
			);

			if ( $verified_today >= $daily_limit ) {
				return new \WP_Error(
					'mcr_daily_limit_reached',
					__( 'You have reached the daily ad reward limit.', 'madara-coin-rewards' ),
					array( 'status' => 429 )
				);
			}
		}

		if ( $global_limit > 0 ) {
			/*
			 * The GLOBAL limit is site-wide: it counts every verified view
			 * by every user, not just this one. It is the site's ceiling on
			 * ad rewards.
			 */
			$rolling = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table}
					 WHERE " . self::counted_status_sql() . "
					   AND verified_at > %s
					   AND id <> %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					gmdate( 'Y-m-d H:i:s', $clock->now()->getTimestamp() - \DAY_IN_SECONDS ),
					$exclude
				)
			);

			if ( $rolling >= $global_limit ) {
				return new \WP_Error(
					'mcr_daily_limit_reached',
					__( 'The daily ad reward limit has been reached for the whole site.', 'madara-coin-rewards' ),
					array( 'status' => 429 )
				);
			}
		}

		return true;
	}

	/**
	 * Acquires a per-user advisory lock for the reward-granting section.
	 *
	 * The daily cap is a COUNT over rows other requests are also writing.
	 * Without a lock, two simultaneous completions can both read the same
	 * count and both pass. MySQL's GET_LOCK serialises them on one
	 * connection. The lock is always released, and if the server does not
	 * support it the caller proceeds unlocked - the cap then degrades to a
	 * best-effort check rather than failing closed.
	 *
	 * @param int $user_id User id.
	 * @return string|false The lock name on success, false when unavailable.
	 */
	public function lock_user( $user_id ) {
		global $wpdb;

		/*
		 * A MySQL advisory lock is SERVER wide, so the name has to carry
		 * the table prefix: two sites sharing a MySQL server would
		 * otherwise contend for the same lock.
		 *
		 * The wait is short on purpose. A long wait holds a database
		 * connection for every concurrent attempt.
		 */
		$name = 'mcr_adcap_' . $wpdb->prefix . (int) $user_id;

		$got = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $name, 2 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		if ( '1' === (string) $got ) {
			return $name;
		}

		return false;
	}

	/**
	 * Releases a per-user advisory lock.
	 *
	 * @param string|false $name Lock name from lock_user().
	 * @return void
	 */
	public function unlock_user( $name ) {
		global $wpdb;

		if ( ! is_string( $name ) || '' === $name ) {
			return;
		}

		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Verifies a completion and grants the reward.
	 *
	 * @param int   $user_id User id.
	 * @param array $payload Payload with session_token and completion_token.
	 * @return array|WP_Error
	 */
	public function verify( $user_id, array $payload ) {
		global $wpdb;

		$user_id = absint( $user_id );

		if ( ! $user_id ) {
			return new \WP_Error( 'mcr_not_logged_in', __( 'Please log in to claim rewards.', 'madara-coin-rewards' ) );
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

		$clock = Container::instance()->clock();
		$table = $wpdb->prefix . 'mcr_ad_sessions';

		$session = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE session_token = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$session_token
			),
			\ARRAY_A
		);

		if ( ! $session ) {
			return new \WP_Error(
				'mcr_session_not_found',
				__( 'The ad session was not found or has expired.', 'madara-coin-rewards' ),
				array( 'status' => 404 )
			);
		}

		$watched_today = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table}
				 WHERE user_id = %d
				   AND " . self::counted_status_sql() . "
				   AND verified_at >= %s
				   AND verified_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id,
				$clock->now()->setTime( 0, 0, 0 )->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ),
				$clock->now()->setTime( 0, 0, 0 )->modify( '+1 day' )->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' )
			)
		);

		$policy = new AdRewardPolicy(
			$clock,
			Ledger::instance(),
			$this->provider()
		);

		/*
		 * The cap is checked against the verification time, immediately
		 * before the reward is granted, so a session opened before midnight
		 * cannot be used to exceed today's limit.
		 *
		 * A per-user advisory lock is held across the check and the grant,
		 * so two simultaneous completions cannot both read the same count
		 * and both pass it.
		 */
		$lock = $this->lock_user( $user_id );

		/*
		 * The advisory lock is held across the cap check AND the grant, so
		 * two simultaneous completions cannot both read the same count and
		 * both pass it.
		 *
		 * try/finally guarantees the lock is released even when the
		 * provider, the ledger or a hooked action throws. A leaked
		 * GET_LOCK is connection scoped, so on a persistent connection it
		 * would deadlock the user for the life of that connection.
		 */
		try {
			$cap = $this->cap_guard( $user_id, (int) $session['id'] );

			if ( is_wp_error( $cap ) ) {
				return $cap;
			}

			$result = $policy->verify(
				$user_id,
				$payload,
				array(
					'session'       => $session,
					'coins'         => (int) $session['coins'],
					'daily_limit'   => (int) Settings::get( 'ads.daily_limit', 10 ),
					'watched_today' => $watched_today,
					'ref_id'        => (int) $session['id'],
				)
			);

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			// Mark the session verified. The status column is the single-use gate:
			// only a 'pending' row can transition to 'verified'.
			$updated = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table} SET status = 'verified', completion_token = %s, verified_at = %s
					 WHERE id = %d AND status = 'pending'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$completion,
					$clock->mysql_gmt(),
					(int) $session['id']
				)
			);

			if ( false === $updated || 0 === (int) $updated ) {
				// Somebody else completed the same session first. The ledger's
				// idempotency key means the coins were only granted once.
				return new \WP_Error(
					'mcr_already_verified',
					__( 'This ad reward has already been claimed.', 'madara-coin-rewards' ),
					array( 'status' => 409 )
				);
			}

			/**
			 * Fires after an ad reward is granted.
			 *
			 * @param int   $user_id User id.
			 * @param array $result  Verification result.
			 */
			do_action( 'mcr_after_ad_reward', $user_id, $result );

			return $result;
		} finally {
			$this->unlock_user( $lock );
		}
	}

	/**
	 * Returns the ad units available for display.
	 *
	 * @return array
	 */
	public function adUnits() {
		$provider = $this->provider();

		if ( ! is_object( $provider ) || ! method_exists( $provider, 'get_ad_units' ) ) {
			return array();
		}

		$units = $provider->get_ad_units();

		return is_array( $units ) ? $units : array();
	}

	/**
	 * Returns the ad reward statistics for a user.
	 *
	 * @param int $user_id User id.
	 * @return array
	 */
	public function stats( $user_id, $include_lifetime = true ) {
		global $wpdb;

		$user_id = absint( $user_id );
		$table   = $wpdb->prefix . 'mcr_ad_sessions';
		$clock   = Container::instance()->clock();

		$today_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS cnt, COALESCE(SUM(coins), 0) AS coins FROM {$table}
				 WHERE user_id = %d
				   AND " . self::counted_status_sql() . "
				   AND verified_at >= %s
				   AND verified_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id,
				$clock->now()->setTime( 0, 0, 0 )->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ),
				$clock->now()->setTime( 0, 0, 0 )->modify( '+1 day' )->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' )
			),
			\ARRAY_A
		);

		$today       = (int) ( is_array( $today_row ) ? ( $today_row['cnt'] ?? 0 ) : 0 );
		$coins_today = (int) ( is_array( $today_row ) ? ( $today_row['coins'] ?? 0 ) : 0 );

		$stats = array(
			'today'      => $today,
			'coins_today' => $coins_today,
			'limit'      => (int) Settings::get( 'ads.daily_limit', 10 ),
			'remaining'  => max( 0, (int) Settings::get( 'ads.daily_limit', 10 ) - $today ),
		);

		/*
		 * The lifetime aggregates are only computed when the caller
		 * shows them. The check-in page does not, and a table that grows
		 * for ever is not scanned on a page that does not need it.
		 */
		if ( $include_lifetime ) {
			$lifetime_row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT COUNT(*) AS cnt, COALESCE(SUM(coins), 0) AS coins FROM {$table} WHERE user_id = %d AND " . self::counted_status_sql() . "", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$user_id
				),
				\ARRAY_A
			);

			$stats['total'] = (int) ( is_array( $lifetime_row ) ? ( $lifetime_row['cnt'] ?? 0 ) : 0 );
			$stats['coins'] = (int) ( is_array( $lifetime_row ) ? ( $lifetime_row['coins'] ?? 0 ) : 0 );
		}

		$stats['coins_per_ad'] = (int) Settings::get( 'ads.coins_per_ad', 5 );

		return $stats;
	}

	/**
	 * Returns the client IP address.
	 *
	 * @return string
	 */
	private function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		return substr( $ip, 0, 45 );
	}
}
