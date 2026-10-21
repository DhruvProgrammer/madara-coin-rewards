<?php
/**
 * GDPR export and erasure.
 *
 * @package MCR
 */

namespace MCR\Integrations;

use MCR\Ledger;

defined( 'ABSPATH' ) || exit;

/**
 * Handles personal data export and erasure requests.
 */
class Privacy {

	/**
	 * Exports a user's coin data.
	 *
	 * @param string $email_address Email address of the requester.
	 * @param int    $page          Page number.
	 * @return array
	 */
	public static function export( $email_address, $page = 1 ) {
		$user = get_user_by( 'email', $email_address );

		if ( ! $user ) {
			return array( 'data' => array(), 'done' => true );
		}

		$per_page = 50;
		$offset   = max( 0, ( (int) $page - 1 ) * $per_page );

		$transactions = Ledger::instance()->list_for_user( (int) $user->ID, $per_page, $offset );
		$total        = Ledger::instance()->count_for_user( (int) $user->ID );

		$data = array();

		foreach ( (array) $transactions as $transaction ) {
			$data[] = array(
				'group_id'    => 'madara-coin-rewards',
				'group_label' => __( 'Coin transactions', 'madara-coin-rewards' ),
				'item_id'     => 'mcr-txn-' . (int) $transaction->id,
				'data'        => array(
					array(
						'name'  => __( 'Transaction ID', 'madara-coin-rewards' ),
						'value' => $transaction->txn_uid,
					),
					array(
						'name'  => __( 'Type', 'madara-coin-rewards' ),
						'value' => $transaction->type,
					),
					array(
						'name'  => __( 'Amount', 'madara-coin-rewards' ),
						'value' => (int) $transaction->amount,
					),
					array(
						'name'  => __( 'Balance after', 'madara-coin-rewards' ),
						'value' => (int) $transaction->balance_after,
					),
					array(
						'name'  => __( 'Reason', 'madara-coin-rewards' ),
						'value' => $transaction->reason,
					),
					array(
						'name'  => __( 'Date', 'madara-coin-rewards' ),
						'value' => $transaction->created_at,
					),
				),
			);
		}

		$done = ( $offset + count( $data ) ) >= $total;

		return array(
			'data' => $data,
			'done' => $done,
		);
	}

	/**
	 * Erases a user's coin data.
	 *
	 * The ledger is financial-style audit data, so it is anonymised rather
	 * than deleted: the rows remain (to preserve the audit trail) but the
	 * owning user id is cleared and the reasons are scrubbed.
	 *
	 * @param string $email_address Email address of the requester.
	 * @param int    $page          Page number.
	 * @return array
	 */
	public static function erase( $email_address, $page = 1 ) {
		global $wpdb;

		$user = get_user_by( 'email', $email_address );

		if ( ! $user ) {
			return array(
				'items_removed'  => false,
				'items_retained' => false,
				'messages'       => array(),
				'done'           => true,
			);
		}

		$user_id = (int) $user->ID;

		$removed  = false;
		$retained = false;

		$ledger    = $wpdb->prefix . 'mcr_transactions';
		$balances  = $wpdb->prefix . 'mcr_balances';
		$checkins  = $wpdb->prefix . 'mcr_checkins';
		$ad_sess   = $wpdb->prefix . 'mcr_ad_sessions';
		$payments  = $wpdb->prefix . 'mcr_payments';

		// Remove data that carries no audit value.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$checkins} WHERE user_id = %d", $user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$ad_sess} WHERE user_id = %d", $user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$retained = (bool) $wpdb->query( $wpdb->prepare( "DELETE FROM {$payments} WHERE user_id = %d", $user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$removed  = true;

		// Anonymise the ledger: keep the rows for audit, drop the identity.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$ledger} SET user_id = 0, reason = %s, ip = '', user_agent = '' WHERE user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				__( 'Erased', 'madara-coin-rewards' ),
				$user_id
			)
		);

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$balances} WHERE user_id = %d", $user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return array(
			'items_removed'  => $removed,
			'items_retained' => $retained,
			'messages'       => array(
				sprintf(
					/* translators: %d: number of transactions retained */
					__( 'The coin transaction ledger was anonymised. %d transaction rows were retained for audit purposes.', 'madara-coin-rewards' ),
					(int) $wpdb->get_var( "SELECT COUNT(*) FROM {$ledger} WHERE user_id = 0" ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				),
			),
			'done'           => true,
		);
	}
}
