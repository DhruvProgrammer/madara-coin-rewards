<?php
/**
 * Public procedural API.
 *
 * These are the functions other plugins and themes are expected to use.
 * Everything here is a thin, filter-friendly wrapper around the classes.
 *
 * @package MCR
 */

use MCR\Ledger;
use MCR\Rewards\CheckIn;
use MCR\Settings;

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'mcr_get_balance' ) ) {
	/**
	 * Returns a user's coin balance.
	 *
	 * @param int $user_id User id. Defaults to the current user.
	 * @return int
	 */
	function mcr_get_balance( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();

		return Ledger::instance()->get_balance( $user_id );
	}
}

if ( ! function_exists( 'mcr_get_stats' ) ) {
	/**
	 * Returns a user's coin statistics.
	 *
	 * @param int $user_id User id. Defaults to the current user.
	 * @return array
	 */
	function mcr_get_stats( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();

		return Ledger::instance()->get_stats( $user_id );
	}
}

if ( ! function_exists( 'mcr_credit' ) ) {
	/**
	 * Credits coins to a user.
	 *
	 * Always supply an idempotency key in $ctx so a repeated call cannot
	 * grant the same reward twice.
	 *
	 * @param int    $user_id User id.
	 * @param int    $amount  Coin amount.
	 * @param string $type    Transaction type.
	 * @param string $reason  Human readable reason.
	 * @param array  $ctx     Context: idempotency_key, source, ref_type, ref_id.
	 * @return array|WP_Error
	 */
	function mcr_credit( $user_id, $amount, $type = 'admin_adjust', $reason = '', $ctx = array() ) {
		return Ledger::instance()->credit( $user_id, $amount, $type, $reason, $ctx );
	}
}

if ( ! function_exists( 'mcr_debit' ) ) {
	/**
	 * Debits coins from a user.
	 *
	 * @param int    $user_id User id.
	 * @param int    $amount  Coin amount.
	 * @param string $type    Transaction type.
	 * @param string $reason  Human readable reason.
	 * @param array  $ctx     Context.
	 * @return array|WP_Error
	 */
	function mcr_debit( $user_id, $amount, $type = 'spend', $reason = '', $ctx = array() ) {
		return Ledger::instance()->debit( $user_id, $amount, $type, $reason, $ctx );
	}
}

if ( ! function_exists( 'mcr_can_afford' ) ) {
	/**
	 * Whether a user can spend a given number of coins.
	 *
	 * @param int $user_id User id.
	 * @param int $amount  Coin amount.
	 * @return bool
	 */
	function mcr_can_afford( $user_id, $amount ) {
		return mcr_get_balance( $user_id ) >= (int) $amount;
	}
}

if ( ! function_exists( 'mcr_is_enabled' ) ) {
	/**
	 * Whether a plugin feature is enabled.
	 *
	 * @param string $feature Feature key: checkin, ads, purchase, dashboard.
	 * @return bool
	 */
	function mcr_is_enabled( $feature ) {
		return Settings::featureEnabled( $feature );
	}
}

if ( ! function_exists( 'mcr_get_checkin_status' ) ) {
	/**
	 * Returns the daily check-in status for a user.
	 *
	 * @param int $user_id User id. Defaults to the current user.
	 * @return array
	 */
	function mcr_get_checkin_status( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();

		return CheckIn::instance()->status( $user_id );
	}
}

if ( ! function_exists( 'mcr_get_transaction_types' ) ) {
	/**
	 * Returns the allowlist of transaction types.
	 *
	 * @return array
	 */
	function mcr_get_transaction_types() {
		return Ledger::transaction_types();
	}
}

if ( ! function_exists( 'mcr_get_page_url' ) ) {
	/**
	 * Returns the URL of one of the plugin's pages.
	 *
	 * @param string $slug Page slug.
	 * @return string
	 */
	function mcr_get_page_url( $slug ) {
		return MCR\Pages::url( $slug );
	}
}
