<?php
/**
 * Manual / administrator gateway.
 *
 * The buyer creates a pending order; a site administrator confirms it.
 * There is no external provider, so there is no webhook: the admin actions
 * are the only way a payment becomes coins, and every action requires:
 *
 *  - current_user_can( 'mcr_manage_coins' ) || current_user_can( 'manage_options' )
 *  - a per-payment nonce (bound to the payment row id)
 *  - reason text recorded for the audit trail
 *
 * @package MCR
 */

namespace MCR\Gateways;

use MCR\Capabilities;
use MCR\Payments\PaymentResult;
use MCR\Settings;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Admin-confirmed payment gateway.
 */
class Manual extends Gateway {

	/**
	 * The request field carrying the action nonce.
	 */
	const NONCE_FIELD = '_mcr_nonce';

	/**
	 * @return string
	 */
	public function get_id() {
		return 'manual';
	}

	/**
	 * @return string
	 */
	public function get_title() {
		return __( 'Manual confirmation', 'madara-coin-rewards' );
	}

	/**
	 * @return string
	 */
	public function get_description() {
		return __( 'Creates a pending order and grants coins only after a site administrator confirms the payment received by bank transfer, cash or any off-site provider.', 'madara-coin-rewards' );
	}

	/**
	 * Admin-controlled: on by default, environment-independent.
	 *
	 * @return bool
	 */
	public function is_available() {
		return 'off' !== (string) $this->get_setting( 'enabled', 'on' );
	}

	/**
	 * Creates a pending order the buyer waits on (7 day expiry).
	 *
	 * @param array $order Order args (see Gateway::create_order()).
	 * @return array|WP_Error array{redirect_url:string,order_id:string}|WP_Error.
	 */
	public function create_order( array $order ) {
		$validated = $this->validate_order_args( $order );

		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$created = $this->create_order_row(
			array(
				'user_id'         => $validated['user_id'],
				'package_id'      => $validated['package_id'],
				'amount_minor'    => $validated['amount_minor'],
				'currency'        => $validated['currency'],
				'coins_total'     => $validated['coins_total'],
				'idempotency_key' => $validated['idempotency_key'],
				// Manual confirmation is a human process: 7 days.
				'expires_at'      => $this->gmt_time( 7 * \DAY_IN_SECONDS ),
			)
		);

		if ( is_wp_error( $created ) ) {
			return $created;
		}

		$row = $created['row'];

		if ( self::STATUS_PENDING !== (string) $row['status'] ) {
			// Duplicate idempotency key: report the existing order.
			return array(
				'redirect_url' => $this->order_status_url( $row ),
				'order_id'     => (string) $row['order_id'],
				'duplicate'    => true,
			);
		}

		return array(
			'redirect_url' => $this->order_status_url( $row ),
			'order_id'     => (string) $row['order_id'],
		);
	}

	/**
	 * The buyer-facing status URL for a manual order.
	 *
	 * @param array $row The mcr_payments row.
	 * @return string
	 */
	private function order_status_url( array $row ) {
		$page_id = absint( Settings::get( 'pages.buy_coins', 0 ) );
		$link    = $page_id ? get_permalink( $page_id ) : '';

		if ( ! is_string( $link ) || '' === $link || is_wp_error( $link ) ) {
			$link = home_url( '/' );
		}

		$url = add_query_arg(
			array(
				'mcr_order'  => (string) $row['order_id'],
				'mcr_status' => 'pending_manual',
			),
			$link
		);

		return esc_url_raw( $url );
	}

	/**
	 * There is no webhook: manual payments are confirmed by admins.
	 *
	 * @param mixed $request Ignored.
	 * @return WP_Error Always an error.
	 */
	public function verify_webhook( $request ) {
		return new WP_Error(
			'mcr_manual_no_webhook',
			__( 'The manual gateway has no webhook. Payments are confirmed in the admin panel.', 'madara-coin-rewards' ),
			array( 'status' => 400 )
		);
	}

	/**
	 * Lists pending manual orders (admin queue).
	 *
	 * @param int $limit Rows to return (max 100).
	 * @return array
	 */
	public function get_pending_orders( $limit = 50 ) {
		global $wpdb;

		$limit = max( 1, min( 100, absint( $limit ) ) );
		$table = $this->table();

		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				 WHERE gateway = %s AND status = %s
				 ORDER BY created_at DESC
				 LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->get_id(),
				self::STATUS_PENDING,
				$limit
			),
			\ARRAY_A
		);
	}

	/**
	 * The nonce action name for an admin action on a payment row.
	 *
	 * Nonces are bound to the payment id: an intercepted token cannot be
	 * replayed against another order.
	 *
	 * @param int $payment_id Row id.
	 * @return string
	 */
	public static function nonce_action( $payment_id ) {
		return 'mcr_manual_action_' . absint( $payment_id );
	}

	/**
	 * Creates the nonce for an admin action (used by the admin UI/form).
	 *
	 * @param int $payment_id Row id.
	 * @return string
	 */
	public static function create_action_nonce( $payment_id ) {
		return wp_create_nonce( self::nonce_action( $payment_id ) );
	}

	/**
	 * Capability + nonce gate for all admin actions.
	 *
	 * @param int $payment_id Row id.
	 * @return bool|WP_Error True when authorized.
	 */
	private function authorize( $payment_id ) {
		if ( ! Capabilities::can_manage_coins() ) {
			return new WP_Error( 'mcr_forbidden', __( 'You are not allowed to manage coin payments.', 'madara-coin-rewards' ), array( 'status' => 403 ) );
		}

		$nonce = isset( $_REQUEST[ self::NONCE_FIELD ] )
			? sanitize_text_field( wp_unslash( (string) $_REQUEST[ self::NONCE_FIELD ] ) )
			: '';

		if ( '' === $nonce || ! wp_verify_nonce( $nonce, self::nonce_action( $payment_id ) ) ) {
			return new WP_Error( 'mcr_bad_nonce', __( 'Invalid security token. Reload the page and try again.', 'madara-coin-rewards' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Admin action: mark a pending manual order as paid and credit coins.
	 *
	 * Idempotent: on a second call with the same row the ledger
	 * idempotency key makes the credit a no-op that reports as duplicate.
	 *
	 * @param int    $payment_id Row id.
	 * @param string $reason     Audit reason (what payment was received).
	 * @return array|WP_Error array{credited,duplicate,transaction_id,balance_after,order_id}|WP_Error.
	 */
	public function admin_mark_paid( $payment_id, $reason = '' ) {
		$payment_id = absint( $payment_id );

		if ( ! $payment_id ) {
			return new WP_Error( 'mcr_payment_unknown', __( 'This payment record was not found.', 'madara-coin-rewards' ), array( 'status' => 404 ) );
		}

		$authorized = $this->authorize( $payment_id );

		if ( is_wp_error( $authorized ) ) {
			return $authorized;
		}

		$reason = sanitize_text_field( (string) $reason );

		if ( '' === $reason ) {
			/* translators: %s: admin display name */
			$reason_format = __( 'Approved by %s via the manual gateway', 'madara-coin-rewards' );
			$admin_name    = '';

			$current_user = wp_get_current_user();

			if ( $current_user instanceof \WP_User && '' !== (string) $current_user->display_name ) {
				$admin_name = (string) $current_user->display_name;
			} elseif ( 0 !== get_current_user_id() ) {
				$admin_name = '#' . absint( get_current_user_id() );
			}

			$reason = sprintf( $reason_format, '' !== $admin_name ? $admin_name : 'administrator' );
		}

		$row = $this->find_order_by_id( $payment_id, $this->get_id() );

		if ( null === $row ) {
			return new WP_Error( 'mcr_payment_unknown', __( 'This payment record was not found.', 'madara-coin-rewards' ), array( 'status' => 404 ) );
		}

		$status = (string) $row['status'];

		if ( self::STATUS_PENDING === $status ) {
			$paid = $this->transition_order(
				$payment_id,
				self::STATUS_PENDING,
				self::STATUS_PAID,
				array( 'paid_at' => $this->gmt_time() )
			);

			if ( ! $paid ) {
				return new WP_Error( 'mcr_order_transition_failed', __( 'The order state changed while saving. Reload and check its status.', 'madara-coin-rewards' ), array( 'status' => 409 ) );
			}

			$row['status'] = self::STATUS_PAID;
		} elseif ( ! in_array( $status, array( self::STATUS_PAID, self::STATUS_CREDITED ), true ) ) {
			// Only pending→paid and retry-after-paid-failure are legal.
			return new WP_Error( 'mcr_invalid_order_state', __( 'This payment cannot be approved in its current state.', 'madara-coin-rewards' ), array( 'status' => 409 ) );
		}

		$credit = $this->credit_order( $row, $reason );

		if ( is_wp_error( $credit ) ) {
			return $credit;
		}

		/**
		 * Fires after an administrator manually approved a payment.
		 *
		 * @param array  $row        The mcr_payments row.
		 * @param int    $admin_id   Approving admin user id.
		 * @param string $reason     Audit reason.
		 * @param array  $credit     Ledger result.
		 */
		do_action( 'mcr_manual_payment_approved', $row, get_current_user_id(), $reason, $credit );

		return array_merge(
			$credit,
			array(
				'order_id' => (string) $row['order_id'],
				'reason'   => $reason,
			)
		);
	}

	/**
	 * Admin action: reject (fail) a pending manual order. No coins move.
	 *
	 * @param int    $payment_id Row id.
	 * @param string $reason     Audit reason.
	 * @return array|WP_Error
	 */
	public function admin_reject( $payment_id, $reason = '' ) {
		$payment_id = absint( $payment_id );

		if ( ! $payment_id ) {
			return new WP_Error( 'mcr_payment_unknown', __( 'This payment record was not found.', 'madara-coin-rewards' ), array( 'status' => 404 ) );
		}

		$authorized = $this->authorize( $payment_id );

		if ( is_wp_error( $authorized ) ) {
			return $authorized;
		}

		$reason = sanitize_text_field( (string) $reason );

		if ( '' === $reason ) {
			$reason = __( 'Rejected by administrator', 'madara-coin-rewards' );
		}

		$row = $this->find_order_by_id( $payment_id, $this->get_id() );

		if ( null === $row ) {
			return new WP_Error( 'mcr_payment_unknown', __( 'This payment record was not found.', 'madara-coin-rewards' ), array( 'status' => 404 ) );
		}

		if ( self::STATUS_PENDING !== (string) $row['status'] ) {
			return new WP_Error( 'mcr_invalid_order_state', __( 'Only pending payments can be rejected.', 'madara-coin-rewards' ), array( 'status' => 409 ) );
		}

		if ( ! $this->transition_order( $payment_id, self::STATUS_PENDING, self::STATUS_FAILED ) ) {
			return new WP_Error( 'mcr_order_transition_failed', __( 'The order state changed while saving. Reload and check its status.', 'madara-coin-rewards' ), array( 'status' => 409 ) );
		}

		/**
		 * Fires after an administrator rejected a payment. The order row is
		 * immutable (terminal) and no coins existed to reverse, so the
		 * reason is delivered to this action for the site's audit trail.
		 *
		 * @param array  $row      The mcr_payments row (failed).
		 * @param int    $admin_id Rejecting admin user id.
		 * @param string $reason   Audit reason.
		 */
		do_action( 'mcr_manual_payment_rejected', $row, get_current_user_id(), $reason );

		return array(
			'rejected' => true,
			'order_id' => (string) $row['order_id'],
			'reason'   => $reason,
		);
	}

	/**
	 * Admin action: refund an approved payment and reverse the coins.
	 *
	 * @param int    $payment_id Row id.
	 * @param string $reason     Audit reason.
	 * @return array|WP_Error
	 */
	public function admin_refund( $payment_id, $reason = '' ) {
		$payment_id = absint( $payment_id );

		if ( ! $payment_id ) {
			return new WP_Error( 'mcr_payment_unknown', __( 'This payment record was not found.', 'madara-coin-rewards' ), array( 'status' => 404 ) );
		}

		$authorized = $this->authorize( $payment_id );

		if ( is_wp_error( $authorized ) ) {
			return $authorized;
		}

		$row = $this->find_order_by_id( $payment_id, $this->get_id() );

		if ( null === $row ) {
			return new WP_Error( 'mcr_payment_unknown', __( 'This payment record was not found.', 'madara-coin-rewards' ), array( 'status' => 404 ) );
		}

		if ( ! in_array( (string) $row['status'], array( self::STATUS_PAID, self::STATUS_CREDITED ), true ) ) {
			return new WP_Error( 'mcr_invalid_order_state', __( 'Only paid payments can be refunded.', 'madara-coin-rewards' ), array( 'status' => 409 ) );
		}

		$result = $this->refund_order( $row, $reason );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		/**
		 * Fires after an administrator refunded a manual payment.
		 *
		 * @param array  $row    The refunded mcr_payments row.
		 * @param int    $admin_id Refunding admin user id.
		 * @param array  $result Refund result (whether coins were reversed).
		 */
		do_action( 'mcr_manual_payment_refunded', $row, get_current_user_id(), $result );

		return array_merge(
			$result,
			array( 'order_id' => (string) $row['order_id'] )
		);
	}

	/**
	 * Admin settings fields (generic render contract).
	 *
	 * @return array
	 */
	public function get_settings_fields() {
		return array(
			array(
				'id'          => 'enabled',
				'label'       => __( 'Enable manual purchases', 'madara-coin-rewards' ),
				'type'        => 'toggle',
				'description' => __( 'Creates pending orders for buyers; an administrator confirms each payment in the Payments screen before coins are granted.', 'madara-coin-rewards' ),
				'default'     => 'on',
			),
			array(
				'id'          => 'currency',
				'label'       => __( 'Currency', 'madara-coin-rewards' ),
				'type'        => 'select',
				'options'     => array( 'USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY' ),
				'description' => __( 'Must match the currency of the coin package being sold. Clear to use the site currency.', 'madara-coin-rewards' ),
			),
		);
	}
}
