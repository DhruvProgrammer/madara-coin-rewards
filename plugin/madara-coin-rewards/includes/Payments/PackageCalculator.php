<?php
/**
 * Coin package maths.
 *
 * Pure logic. Prices are handled in integer minor units by the payment
 * layer; this class only computes coin totals.
 *
 * @package MCR
 */

namespace MCR\Payments;

defined( 'ABSPATH' ) || exit;

/**
 * Computes package coin totals.
 */
class PackageCalculator {

	/**
	 * Calculates the total coins a package grants.
	 *
	 * A package has a base coin amount and an optional percentage bonus.
	 * The bonus is applied to the base amount and the result is capped by
	 * `max_bonus_coins` when one is configured.
	 *
	 * @param int   $base_coins     Base coin amount.
	 * @param int   $bonus_percent  Percentage bonus (0-100).
	 * @param int   $max_bonus_coins Maximum bonus coins (0 = no cap).
	 * @return int Total coins.
	 */
	public static function calculateTotal( $base_coins, $bonus_percent = 0, $max_bonus_coins = 0 ) {
		$base_coins = (int) $base_coins;

		if ( $base_coins < 0 ) {
			$base_coins = 0;
		}

		$bonus_percent = (int) $bonus_percent;

		if ( $bonus_percent < 0 ) {
			$bonus_percent = 0;
		}

		if ( $bonus_percent > 100 ) {
			$bonus_percent = 100;
		}

		$bonus_raw = floor( ( (float) $base_coins ) * ( $bonus_percent / 100.0 ) );

		$max_bonus_coins = (int) $max_bonus_coins;

		if ( $max_bonus_coins > 0 && $bonus_raw > $max_bonus_coins ) {
			$bonus_raw = (float) $max_bonus_coins;
		}

		$total_float = ( (float) $base_coins ) + $bonus_raw;

		if ( $total_float >= (float) PHP_INT_MAX ) {
			return PHP_INT_MAX;
		}

		$total = (int) $total_float;

		return $total >= 0 ? $total : 0;
	}

	/**
	 * Converts a decimal price into integer minor units.
	 *
	 * Floating point money is never stored; the gateway layer works in minor
	 * units (cents, etc.) to avoid rounding drift.
	 *
	 * @param float|string $price   Decimal price.
	 * @param int          $decimals Number of decimal places for the currency.
	 * @return int Minor units, or 0 when the price is invalid.
	 */
	public static function toMinorUnits( $price, $decimals = 2 ) {
		$decimals = max( 0, min( 6, (int) $decimals ) );

		if ( ! is_numeric( $price ) ) {
			return 0;
		}

		$price_float = (float) $price;

		if ( ! is_finite( $price_float ) || $price_float <= 0 ) {
			return 0;
		}

		$factor = pow( 10, $decimals );
		$val    = round( $price_float * $factor );

		if ( $val >= (float) PHP_INT_MAX ) {
			return PHP_INT_MAX;
		}

		$minor = (int) $val;

		return $minor >= 0 ? $minor : 0;
	}

	/**
	 * Converts integer minor units back into a decimal price.
	 *
	 * @param int $minor    Minor units.
	 * @param int $decimals Number of decimal places for the currency.
	 * @return float
	 */
	public static function fromMinorUnits( $minor, $decimals = 2 ) {
		$decimals = max( 0, min( 6, (int) $decimals ) );
		$factor   = pow( 10, $decimals );
		$minor    = (int) $minor;

		if ( $minor < 0 ) {
			$minor = 0;
		}

		return round( $minor / $factor, $decimals );
	}
}
