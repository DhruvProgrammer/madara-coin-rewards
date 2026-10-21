<?php
/**
 * Streak maths.
 *
 * Pure logic: no WordPress, no database, no direct time calls. The caller
 * supplies the day keys, so the same code is used by the live check-in flow
 * and by the test harness.
 *
 * @package MCR
 */

namespace MCR\Rewards;

defined( 'ABSPATH' ) || exit;

/**
 * Calculates consecutive-day streaks and streak bonuses.
 */
class StreakCalculator {

	/**
	 * Calculates the streak day number for a claim.
	 *
	 * A streak continues when the previous claim was on the immediately
	 * preceding calendar day (in the site timezone). Any longer gap breaks
	 * the streak and the new streak starts at day 1.
	 *
	 * @param string       $today_key       The day key being claimed (Y-m-d).
	 * @param array|string $previous_keys   Previously claimed day keys, any order.
	 * @return int The streak day number (1-based).
	 */
	public static function calculateStreakDay( $today_key, $previous_keys = array() ) {
		$today_key = self::normalizeKey( $today_key );

		if ( '' === $today_key ) {
			return 1;
		}

		$previous_keys = self::normalizeKeys( $previous_keys );

		if ( empty( $previous_keys ) ) {
			return 1;
		}

		/*
		 * The walk below expects the most recent day first, so sort
		 * descending here rather than relying on the caller. This makes the
		 * function order-independent, which is what the policy classes and
		 * the database query both need.
		 */
		rsort( $previous_keys );

		/*
		 * A day key in the future is anomalous (the server generates these,
		 * so it should not happen) but it must not break a legitimate
		 * streak. Discard anything on or after today before walking.
		 */
		$previous_keys = array_values(
			array_filter(
				$previous_keys,
				static function ( $key ) use ( $today_key ) {
					return $key < $today_key;
				}
			)
		);

		if ( empty( $previous_keys ) ) {
			return 1;
		}

		// Only the days immediately before today can extend a streak.
		$cursor = self::makeDate( $today_key );

		$streak = 1;

		foreach ( $previous_keys as $key ) {
			$expected = $cursor->modify( '-1 day' )->format( 'Y-m-d' );

			if ( $key === $expected ) {
				++$streak;
				$cursor = self::makeDate( $expected );
				continue;
			}

			// A gap means the streak is broken. Older keys are irrelevant.
			break;
		}

		return $streak;
	}

	/**
	 * Builds a date-only value for calendar arithmetic.
	 *
	 * The arithmetic is done in UTC so that a DST transition in the server
	 * or site timezone can never shift a "yesterday" computation onto the
	 * wrong calendar day. These are date-only values, so UTC is the correct
	 * frame for the maths.
	 *
	 * @param string $key Day key (Y-m-d).
	 * @return \DateTimeImmutable
	 */
	private static function makeDate( $key ) {
		return new \DateTimeImmutable( $key . ' 00:00:00', new \DateTimeZone( 'UTC' ) );
	}

	/**
	 * Calculates the streak bonus for a given streak day.
	 *
	 * Bonuses are configured as a map of day threshold => coin amount. The
	 * highest threshold that the streak has reached wins, and the result is
	 * capped by `max_bonus`.
	 *
	 * @param int   $streak_day The streak day number.
	 * @param array $bonuses    Map of threshold => bonus amount.
	 * @param int   $max_bonus  Maximum bonus that may be granted (0 = no cap).
	 * @return int Bonus amount in coins.
	 */
	public static function calculateBonus( $streak_day, $bonuses = array(), $max_bonus = 0 ) {
		$streak_day = (int) $streak_day;

		if ( $streak_day < 1 || ! is_array( $bonuses ) || empty( $bonuses ) ) {
			return 0;
		}

		$best = 0;

		foreach ( $bonuses as $threshold => $amount ) {
			$threshold = (int) $threshold;
			$amount    = (int) $amount;

			if ( $threshold < 1 || $amount < 1 ) {
				continue;
			}

			if ( $streak_day >= $threshold && $amount > $best ) {
				$best = $amount;
			}
		}

		$max_bonus = (int) $max_bonus;

		if ( $max_bonus > 0 && $best > $max_bonus ) {
			$best = $max_bonus;
		}

		return $best;
	}

	/**
	 * Normalizes a list of day keys.
	 *
	 * @param array|string $keys Raw keys.
	 * @return array Sorted, unique, valid Y-m-d keys.
	 */
	public static function normalizeKeys( $keys ) {
		if ( is_string( $keys ) ) {
			$keys = ( '' === trim( $keys ) ) ? array() : explode( ',', $keys );
		}

		if ( ! is_array( $keys ) ) {
			return array();
		}

		$clean = array();

		foreach ( $keys as $key ) {
			$key = self::normalizeKey( $key );

			if ( '' !== $key ) {
				$clean[] = $key;
			}
		}

		$clean = array_values( array_unique( $clean ) );

		sort( $clean );

		return $clean;
	}

	/**
	 * Validates and normalizes a single day key.
	 *
	 * @param mixed $key Raw key.
	 * @return string Y-m-d or an empty string when invalid.
	 */
	public static function normalizeKey( $key ) {
		if ( ! is_string( $key ) ) {
			return '';
		}

		$key = trim( $key );

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $key ) ) {
			return '';
		}

		list( $year, $month, $day ) = array_map( 'intval', explode( '-', $key ) );

		if ( ! checkdate( $month, $day, $year ) ) {
			return '';
		}

		return $key;
	}
}
