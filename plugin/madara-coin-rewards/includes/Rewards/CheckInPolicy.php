<?php
/**
 * Daily check-in policy.
 *
 * Pure decision logic with an injected clock and ledger so it can be tested
 * without a database and without sleeping.
 *
 * The policy is deliberately split into three steps:
 *
 *   1. evaluate() - what is the claim worth? Pure, no side effects.
 *   2. (the caller records the claim) - the DB UNIQUE index is the gate.
 *   3. grant()    - move the coins.
 *
 * The caller MUST record the claim between 1 and 3, so a failed record
 * write can never leave coins paid for a day that was never recorded.
 *
 * @package MCR
 */

namespace MCR\Rewards;

use MCR\Ledger\LedgerInterface;
use MCR\Support\Clock;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Decides whether a daily check-in may proceed and what it is worth.
 */
class CheckInPolicy {

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
	 * Constructor.
	 *
	 * @param Clock           $clock  Clock instance.
	 * @param LedgerInterface $ledger Ledger instance.
	 */
	public function __construct( Clock $clock, LedgerInterface $ledger ) {
		$this->clock  = $clock;
		$this->ledger = $ledger;
	}

	/**
	 * Evaluates what a daily claim is worth, without touching the ledger.
	 *
	 * @param int   $user_id User id.
	 * @param array $options Options:
	 *                         - base_amount     int   Coins for the daily claim.
	 *                         - bonuses         array Threshold => bonus map.
	 *                         - max_bonus       int   Cap for the streak bonus.
	 *                         - previous_keys   array Previously claimed day keys.
	 *                         - already_claimed bool  Whether a claim exists today.
	 * @return array|WP_Error
	 */
	public function evaluate( $user_id, array $options = array() ) {
		$user_id = (int) $user_id;

		if ( $user_id <= 0 ) {
			return new WP_Error( 'mcr_invalid_user', __( 'Invalid user.', 'madara-coin-rewards' ) );
		}

		$options = wp_parse_args(
			$options,
			array(
				'base_amount'     => 0,
				'bonuses'         => array(),
				'max_bonus'       => 0,
				'previous_keys'   => array(),
				'already_claimed' => false,
			)
		);

		if ( $options['already_claimed'] ) {
			return new WP_Error(
				'mcr_already_claimed',
				__( 'You have already claimed your daily reward today.', 'madara-coin-rewards' ),
				array( 'status' => 409 )
			);
		}

		$today_key = $this->clock->today_key();

		$streak = StreakCalculator::calculateStreakDay( $today_key, $options['previous_keys'] );
		$bonus  = StreakCalculator::calculateBonus( $streak, $options['bonuses'], $options['max_bonus'] );

		$base = (int) $options['base_amount'];

		if ( $base < 0 ) {
			$base = 0;
		}

		return array(
			'user_id'  => $user_id,
			'date_key' => $today_key,
			'streak'   => $streak,
			'base'     => $base,
			'bonus'    => $bonus,
			'total'    => $base + $bonus,
		);
	}

	/**
	 * Grants an evaluated claim.
	 *
	 * The idempotency key is derived from the user and the day, so a
	 * repeated grant is a no-op at the database level.
	 *
	 * @param int   $user_id    User id.
	 * @param array $evaluation Result of evaluate().
	 * @param array $options    Options: reason, bonus_reason.
	 * @return array|WP_Error
	 */
	public function grant( $user_id, array $evaluation, array $options = array() ) {
		$options = wp_parse_args(
			$options,
			array(
				'reason'       => __( 'Daily check-in reward', 'madara-coin-rewards' ),
				'bonus_reason' => __( 'Check-in streak bonus', 'madara-coin-rewards' ),
			)
		);

		$user_id = (int) $user_id;
		$today   = isset( $evaluation['date_key'] ) ? (string) $evaluation['date_key'] : $this->clock->today_key();
		$base    = (int) ( $evaluation['base'] ?? 0 );
		$bonus   = (int) ( $evaluation['bonus'] ?? 0 );
		$txn_id  = 0;

		if ( $base > 0 ) {
			$result = $this->ledger->credit(
				$user_id,
				$base,
				'checkin',
				$options['reason'],
				array(
					'idempotency_key' => 'checkin:' . $user_id . ':' . $today,
					'source'          => 'checkin',
					'ref_type'        => 'checkin',
					'ref_id'          => 0,
				)
			);

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			if ( ! empty( $result['duplicate'] ) ) {
				return new WP_Error(
					'mcr_already_claimed',
					__( 'You have already claimed your daily reward today.', 'madara-coin-rewards' ),
					array( 'status' => 409 )
				);
			}

			$txn_id = isset( $result['transaction_id'] ) ? (int) $result['transaction_id'] : 0;
		}

		if ( $bonus > 0 ) {
			$bonus_result = $this->ledger->credit(
				$user_id,
				$bonus,
				'checkin_bonus',
				$options['bonus_reason'],
				array(
					'idempotency_key' => 'checkin_bonus:' . $user_id . ':' . $today,
					'source'          => 'checkin',
					'ref_type'        => 'checkin',
					'ref_id'          => 0,
				)
			);

			if ( is_wp_error( $bonus_result ) ) {
				/*
				 * The base reward is already committed. Reverse it so the
				 * day is not half-paid: the caller deletes the claim record
				 * and the user can retry cleanly.
				 *
				 * If the reversal itself fails (the user cannot cover it),
				 * the base stays granted. The error then says so, and the
				 * caller keeps the claim record so the day is not claimed
				 * twice.
				 */
				$reversed = false;

				if ( $base > 0 ) {
					$reversal = $this->ledger->debit(
						$user_id,
						$base,
						'reversal',
						__( 'Reversed daily check-in reward', 'madara-coin-rewards' ),
						array(
							'idempotency_key' => 'checkin_reversal:' . $user_id . ':' . $today,
							'source'          => 'checkin',
							'ref_type'        => 'checkin',
							'ref_id'          => 0,
							// Undoes the credit rather than counting as a spend.
							'reversal'        => true,
						)
					);

					$reversed = ! is_wp_error( $reversal );
				}

				$data = $bonus_result->get_error_data();

				if ( ! is_array( $data ) ) {
					$data = array();
				}

				$data['reversed'] = $reversed;
				$data['status']   = isset( $data['status'] ) ? (int) $data['status'] : 500;

				if ( ! $reversed ) {
					$data['status'] = 500;
				}

				$bonus_result->add_data( $data );

				return $bonus_result;
			}

			if ( 0 === $txn_id && isset( $bonus_result['transaction_id'] ) ) {
				$txn_id = (int) $bonus_result['transaction_id'];
			}
		}

		return array(
			'user_id'        => $user_id,
			'date_key'       => $today,
			'streak'         => (int) ( $evaluation['streak'] ?? 1 ),
			'base'           => $base,
			'bonus'          => $bonus,
			'total'          => $base + $bonus,
			'transaction_id' => $txn_id,
			'balance'        => $this->ledger->get_balance( $user_id ),
			'next_in'        => $this->clock->seconds_until_next_day(),
		);
	}

	/**
	 * Attempts to claim the daily reward.
	 *
	 * Convenience wrapper that evaluates and grants in one call. Callers
	 * that need to write their own claim record should use evaluate() and
	 * grant() instead, so that a failed record write cannot leave coins
	 * paid for an unrecorded day.
	 *
	 * @param int   $user_id User id.
	 * @param array $options Options, see evaluate().
	 * @return array|WP_Error
	 */
	public function attempt( $user_id, array $options = array() ) {
		$evaluation = $this->evaluate( $user_id, $options );

		if ( is_wp_error( $evaluation ) ) {
			return $evaluation;
		}

		return $this->grant( $user_id, $evaluation, $options );
	}
}
