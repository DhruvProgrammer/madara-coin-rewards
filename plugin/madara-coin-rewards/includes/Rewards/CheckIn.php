<?php
/**
 * Daily check-in service.
 *
 * @package MCR
 */

namespace MCR\Rewards;

use MCR\Container;
use MCR\Ledger;
use MCR\Settings;
use MCR\Support\Clock;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the daily check-in flow end to end.
 */
class CheckIn {

	/**
	 * Singleton instance.
	 *
	 * @var CheckIn|null
	 */
	private static $instance = null;

	/**
	 * Per-request memoisation of status() results.
	 *
	 * @var array
	 */
	private $status_cache = array();

	/**
	 * Returns the shared instance.
	 *
	 * @return CheckIn
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Returns the current status of the daily check-in for a user.
	 *
	 * @param int $user_id User id.
	 * @return array
	 */
	public function status( $user_id ) {
		global $wpdb;

		$user_id = absint( $user_id );

		if ( isset( $this->status_cache[ $user_id ] ) ) {
			return $this->status_cache[ $user_id ];
		}

		$clock   = Container::instance()->clock();
		$today   = $clock->today_key();

		$defaults = array(
			'user_id'         => $user_id,
			'claimed'         => false,
			'claimed_today'   => false,
			'streak'          => 0,
			'base_amount'     => (int) Settings::get( 'checkin.base_amount', 10 ),
			'bonus_amount'    => 0,
			'total_amount'    => 0,
			'next_in'         => $clock->seconds_until_next_day(),
			'today_key'       => $today,
			'calendar'        => array(),
			'enabled'         => Settings::featureEnabled( 'checkin' ),
			'base_claimable'  => false,
		);

		if ( ! $user_id ) {
			$this->status_cache[ $user_id ] = $defaults;

			return $defaults;
		}

		$table = $wpdb->prefix . 'mcr_checkins';

		$today_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d AND checkin_date = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id,
				$today
			)
		);

		if ( $today_row ) {
			$defaults['claimed']       = true;
			$defaults['claimed_today'] = true;
			$defaults['streak']        = (int) $today_row->streak_count;
			$defaults['base_amount']   = (int) $today_row->base_amount;
			$defaults['bonus_amount']  = (int) $today_row->bonus_amount;
			$defaults['total_amount']  = (int) $today_row->total_amount;
		} else {
			$defaults['streak']         = $this->current_streak( $user_id );
			/*
			 * current_streak() already counts today as the first day of
			 * the streak the user would have, so the bonus is computed
			 * from it directly. Adding one here advertised the next
			 * tier's bonus and promised more than the claim pays.
			 */
			$defaults['bonus_amount']   = StreakCalculator::calculateBonus(
				$defaults['streak'],
				Settings::get( 'checkin.bonuses', array() ),
				(int) Settings::get( 'checkin.max_bonus', 0 )
			);
			$defaults['total_amount']   = $defaults['base_amount'] + $defaults['bonus_amount'];
		}

		$defaults['base_claimable'] = $defaults['total_amount'] > 0;
		$defaults['calendar']       = $this->calendar( $user_id );

		$this->status_cache[ $user_id ] = $defaults;

		return $defaults;
	}

	/**
	 * Clears the per-request status cache.
	 *
	 * @return void
	 */
	public function reset_status_cache() {
		$this->status_cache = array();
	}

	/**
	 * Computes the streak a user would have if they claimed now.
	 *
	 * @param int $user_id User id.
	 * @return int
	 */
	public function current_streak( $user_id ) {
		global $wpdb;

		$user_id = absint( $user_id );

		$clock   = Container::instance()->clock();

		$table = $wpdb->prefix . 'mcr_checkins';

		$keys = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT checkin_date FROM {$table} WHERE user_id = %d ORDER BY checkin_date DESC LIMIT 400", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id
			)
		);

		return StreakCalculator::calculateStreakDay( $clock->today_key(), $keys );
	}

	/**
	 * Builds the check-in calendar for the trailing days.
	 *
	 * @param int $user_id User id.
	 * @param int $days    Number of days to include.
	 * @return array
	 */
	public function calendar( $user_id, $days = 35 ) {
		global $wpdb;

		$user_id = absint( $user_id );

		$days    = max( 7, min( 120, absint( $days ) ) );
		$clock   = Container::instance()->clock();

		$table = $wpdb->prefix . 'mcr_checkins';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT checkin_date, streak_count, total_amount FROM {$table}
				 WHERE user_id = %d AND checkin_date >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id,
				$clock->now()->modify( '-' . $days . ' days' )->format( 'Y-m-d' )
			)
		);

		$by_date = array();

		foreach ( (array) $rows as $row ) {
			$by_date[ $row->checkin_date ] = $row;
		}

		$calendar = array();

		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$date     = $clock->now()->modify( '-' . $i . ' days' )->format( 'Y-m-d' );
			$calendar[] = array(
				'date'   => $date,
				'claimed' => isset( $by_date[ $date ] ),
				'streak' => isset( $by_date[ $date ] ) ? (int) $by_date[ $date ]->streak_count : 0,
				'amount' => isset( $by_date[ $date ] ) ? (int) $by_date[ $date ]->total_amount : 0,
				'today'  => $date === $clock->today_key(),
			);
		}

		return $calendar;
	}

	/**
	 * Claims the daily reward.
	 *
	 * The database UNIQUE index on (user_id, checkin_date) is the real
	 * guarantee: if two requests race, the second insert fails and the user
	 * keeps a single reward.
	 *
	 * @param int $user_id User id.
	 * @return array|WP_Error
	 */
	public function claim( $user_id ) {
		global $wpdb;

		$user_id = absint( $user_id );

		/*
		 * The rate limit the settings screen offers is applied here. It
		 * used to be stored and displayed but never read, so a client
		 * could hammer the endpoint.
		 */
		if ( ! \MCR\Support\RateLimiter::allow(
			'checkin',
			$user_id,
			(int) Settings::get( 'security.rate_limit_checkin', 10 )
		) ) {
			return new \WP_Error(
				'mcr_rate_limited',
				__( 'You are checking in too quickly. Please wait a moment and try again.', 'madara-coin-rewards' ),
				array( 'status' => 429 )
			);
		}


		if ( ! $user_id ) {
			return new \WP_Error( 'mcr_not_logged_in', __( 'Please log in to claim your daily reward.', 'madara-coin-rewards' ) );
		}

		if ( ! Settings::featureEnabled( 'checkin' ) ) {
			return new \WP_Error( 'mcr_feature_disabled', __( 'Daily check-in is currently disabled.', 'madara-coin-rewards' ) );
		}

		$clock = Container::instance()->clock();
		$today = $clock->today_key();

		$table = $wpdb->prefix . 'mcr_checkins';

		// Pre-check for a friendly message. The DB constraint remains the
		// authority, this is only to avoid a noisy duplicate-key error.
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE user_id = %d AND checkin_date = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id,
				$today
			)
		);

		if ( $existing ) {
			return new \WP_Error(
				'mcr_already_claimed',
				__( 'You have already claimed your daily reward today.', 'madara-coin-rewards' ),
				array( 'status' => 409 )
			);
		}

		$previous_keys = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT checkin_date FROM {$table} WHERE user_id = %d ORDER BY checkin_date DESC LIMIT 400", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id
			)
		);

		$policy = Container::makeCheckInPolicy(
			$clock,
			Ledger::instance()
		);

		/*
		 * Step 1: work out what the claim is worth. Pure, no side effects.
		 */
		$evaluation = $policy->evaluate(
			$user_id,
			array(
				'base_amount'     => (int) Settings::get( 'checkin.base_amount', 10 ),
				'bonuses'         => Settings::get( 'checkin.bonuses', array() ),
				'max_bonus'       => (int) Settings::get( 'checkin.max_bonus', 0 ),
				'previous_keys'   => $previous_keys,
				'already_claimed' => (bool) $existing,
			)
		);

		if ( is_wp_error( $evaluation ) ) {
			return $evaluation;
		}

		/*
		 * Step 2: record the claim. The UNIQUE index on (user_id,
		 * checkin_date) makes this the atomic gate - a concurrent second
		 * request fails here, BEFORE any coins move.
		 */
		$inserted = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (user_id, checkin_date, streak_count, base_amount, bonus_amount, total_amount, transaction_id, ip, created_at)
				 VALUES (%d, %s, %d, %d, %d, %d, %d, %s, %s)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id,
				$today,
				$evaluation['streak'],
				$evaluation['base'],
				$evaluation['bonus'],
				$evaluation['total'],
				0,
				Settings::get( 'security.log_ip', 'on' ) ? $this->client_ip() : '',
				$clock->mysql_gmt()
			)
		);

		if ( false === $inserted ) {
			if ( Ledger::is_duplicate_key_error() ) {
				return new \WP_Error(
					'mcr_already_claimed',
					__( 'You have already claimed your daily reward today.', 'madara-coin-rewards' ),
					array( 'status' => 409 )
				);
			}

			return new \WP_Error( 'mcr_checkin_failed', __( 'Could not record your check-in. Please try again.', 'madara-coin-rewards' ) );
		}

		$checkin_id = (int) $wpdb->insert_id;

		/*
		 * Step 3: move the coins. If the ledger fails, the claim record is
		 * removed again so the user can retry - the alternative is coins
		 * paid for a day that was never recorded.
		 */
		$result = $policy->grant(
			$user_id,
			$evaluation,
			array(
				// The claim record's id scopes the idempotency keys to this
				// attempt, so a retry after a failed grant is not blocked
				// by the failed attempt's key.
				'attempt' => $checkin_id,
			)
		);

		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();
			$data = is_array( $data ) ? $data : array();

			/*
			 * The record is removed whenever NOTHING was paid, so the day
			 * is not locked and the user can try again. That covers a
			 * failed base credit and a bonus whose reversal worked.
			 *
			 * When the reversal itself failed the base coins are still in
			 * the account: the record is kept, because the day WAS paid
			 * for, and letting the user claim it again would pay twice.
			 */
			$granted = isset( $data['granted'] ) ? (int) $data['granted'] : ( ! empty( $data['reversed'] ) ? 0 : 0 );

			if ( $granted <= 0 ) {
				$wpdb->query(
					$wpdb->prepare(
						"DELETE FROM {$table} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$checkin_id
					)
				);
			} else {
				$wpdb->query(
					$wpdb->prepare(
						"UPDATE {$table} SET bonus_amount = 0, total_amount = %d WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$granted,
						$checkin_id
					)
				);
			}

			return $result;
		}

		if ( ! empty( $result['transaction_id'] ) ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table} SET transaction_id = %d WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					(int) $result['transaction_id'],
					$checkin_id
				)
			);
		}

		/**
		 * Fires after a successful daily check-in.
		 *
		 * @param int   $user_id User id.
		 * @param array $result  Claim result.
		 */
		do_action( 'mcr_after_checkin', $user_id, $result );

		$result['checkin_id'] = $checkin_id;
		$result['message']    = Settings::get( 'checkin.success_message', __( 'Daily reward claimed! Come back tomorrow to keep your streak.', 'madara-coin-rewards' ) );

		return $result;
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
