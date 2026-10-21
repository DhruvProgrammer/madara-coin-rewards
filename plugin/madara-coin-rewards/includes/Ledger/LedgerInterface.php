<?php
/**
 * Ledger contract.
 *
 * @package MCR
 */

namespace MCR\Ledger;

defined( 'ABSPATH' ) || exit;

/**
 * Minimal ledger contract used by the pure policy classes.
 */
interface LedgerInterface {

	/**
	 * Credits coins to a user.
	 *
	 * @param int    $user_id User id.
	 * @param int    $amount  Positive coin amount.
	 * @param string $type    Transaction type.
	 * @param string $reason  Human readable reason.
	 * @param array  $ctx     Context (idempotency_key, source, ref_type, ref_id).
	 * @return array|\WP_Error
	 */
	public function credit( $user_id, $amount, $type, $reason = '', $ctx = array() );

	/**
	 * Debits coins from a user.
	 *
	 * @param int    $user_id User id.
	 * @param int    $amount  Positive coin amount to remove.
	 * @param string $type    Transaction type.
	 * @param string $reason  Human readable reason.
	 * @param array  $ctx     Context.
	 * @return array|\WP_Error
	 */
	public function debit( $user_id, $amount, $type, $reason = '', $ctx = array() );

	/**
	 * Returns a user's current balance.
	 *
	 * @param int $user_id User id.
	 * @return int
	 */
	public function get_balance( $user_id );
}
