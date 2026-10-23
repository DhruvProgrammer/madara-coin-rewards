<?php
/**
 * Pure balance arithmetic.
 *
 * No WordPress or database dependency, so it can be unit tested directly.
 * All coin amounts are integers; PHP's int is 64-bit on every platform
 * WordPress supports.
 *
 * @package MCR
 */

namespace MCR\Ledger;

defined( 'ABSPATH' ) || exit;

/**
 * Pure balance maths.
 */
class BalanceMath {

	/**
	 * Largest coin amount the plugin will ever store.
	 *
	 * PHP_INT_MAX is ~9.2e18. We cap well below that so summing lifetime
	 * counters can never overflow a signed 64-bit integer.
	 */
	const MAX_BALANCE = 1000000000000; // One trillion coins.

	/**
	 * Validates a coin amount for a credit.
	 *
	 * @param mixed $amount Raw amount.
	 * @return int|false The amount, or false when invalid.
	 */
	public static function validateCredit( $amount ) {
		return self::validate( $amount );
	}

	/**
	 * Validates a coin amount for a debit.
	 *
	 * @param mixed $amount Raw amount.
	 * @return int|false The amount, or false when invalid.
	 */
	public static function validateDebit( $amount ) {
		return self::validate( $amount );
	}

	/**
	 * Validates a positive integer coin amount.
	 *
	 * Rejects non-numeric input, floats, zero, negatives, booleans, and
	 * anything above the configured ceiling. Floats are rejected even when
	 * they hold a whole number, so a float can never silently truncate a
	 * value on its way into the ledger.
	 *
	 * @param mixed $amount Raw amount.
	 * @return int|false The amount, or false when invalid.
	 */
	public static function validate( $amount ) {
		if ( is_bool( $amount ) || is_array( $amount ) || is_object( $amount ) || null === $amount ) {
			return false;
		}

		if ( is_float( $amount ) ) {
			return false;
		}

		if ( is_string( $amount ) ) {
			$amount = trim( $amount );

			if ( '' === $amount || ! preg_match( '/^-?\d+$/', $amount ) ) {
				return false;
			}

			/*
			 * A clean integer string is unambiguous, and the database
			 * returns numeric columns as strings, so accept it and cast.
			 */
			$amount = (int) $amount;
		}

		if ( ! is_int( $amount ) ) {
			return false;
		}

		if ( $amount <= 0 ) {
			return false;
		}

		if ( $amount > self::MAX_BALANCE ) {
			return false;
		}

		return $amount;
	}

	/**
	 * Computes a new balance after a credit.
	 *
	 * @param int $balance Current balance.
	 * @param int $amount  Amount to add.
	 * @return int|false New balance, or false when it would overflow.
	 */
	public static function credit( $balance, $amount ) {
		$balance = (int) $balance;
		$amount  = self::validateCredit( $amount );

		if ( false === $amount ) {
			return false;
		}

		$new = $balance + $amount;

		/*
		 * A negative balance is a corruption state the plugin must
		 * survive: the balance can never be reported as negative, so a
		 * credit that still leaves the balance below zero is clamped to
		 * zero instead of handing a negative value back to the caller.
		 */
		if ( $new < 0 ) {
			return 0;
		}

		if ( $new > self::MAX_BALANCE ) {
			return false;
		}

		return $new;
	}

	/**
	 * Computes a new balance after a debit.
	 *
	 * @param int $balance Current balance.
	 * @param int $amount  Amount to subtract.
	 * @return int|false New balance, or false when funds are insufficient.
	 */
	public static function debit( $balance, $amount ) {
		$balance = (int) $balance;
		$amount  = self::validateDebit( $amount );

		if ( false === $amount ) {
			return false;
		}

		if ( $balance < $amount ) {
			return false;
		}

		return $balance - $amount;
	}

	/**
	 * Whether a balance can cover a debit.
	 *
	 * @param int $balance Current balance.
	 * @param int $amount  Amount to check.
	 * @return bool
	 */
	public static function canAfford( $balance, $amount ) {
		$amount = self::validateDebit( $amount );

		if ( false === $amount ) {
			return false;
		}

		return (int) $balance >= $amount;
	}
}
