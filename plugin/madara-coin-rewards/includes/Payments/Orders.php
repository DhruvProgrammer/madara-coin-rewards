<?php
/**
 * Coin purchase orders.
 *
 * @package MCR
 */

namespace MCR\Payments;

use MCR\Container;
use MCR\Gateways\Registry;
use MCR\Ledger;
use MCR\Settings;
use MCR\Support\Clock;

defined( 'ABSPATH' ) || exit;

/**
 * Creates and settles coin purchase orders.
 */
class Orders {

	/**
	 * Singleton instance.
	 *
	 * @var Orders|null
	 */
	private static $instance = null;

	/**
	 * Returns the shared instance.
	 *
	 * @return Orders
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Creates a pending purchase and returns the checkout details.
	 *
	 * The gateway owns the order row: it performs the amount verification
	 * against the package snapshot, persists the pending order and returns
	 * the provider redirect. This class deliberately does not write to
	 * mcr_payments itself, so there is exactly one implementation of the
	 * order lifecycle and no chance of a duplicate row.
	 *
	 * @param int   $user_id User id.
	 * @param array $package Package definition (as returned by Packages::get()).
	 * @return array|WP_Error
	 */
	public function create( $user_id, array $package ) {
		$user_id = absint( $user_id );

		if ( ! $user_id ) {
			return new \WP_Error( 'mcr_not_logged_in', __( 'Please log in to buy coins.', 'madara-coin-rewards' ) );
		}

		if ( ! Settings::featureEnabled( 'purchase' ) ) {
			return new \WP_Error( 'mcr_feature_disabled', __( 'Coin purchases are currently disabled.', 'madara-coin-rewards' ) );
		}

		$gateway_id = (string) Settings::get( 'purchase.gateway', 'manual' );
		$gateway    = Registry::get( $gateway_id );

		if ( ! $gateway ) {
			return new \WP_Error(
				'mcr_gateway_unavailable',
				__( 'No payment gateway is available. Please contact the site administrator.', 'madara-coin-rewards' )
			);
		}

		if ( ! $gateway->is_available() ) {
			return new \WP_Error(
				'mcr_gateway_unavailable',
				__( 'This payment method is not available right now.', 'madara-coin-rewards' )
			);
		}

		$clock  = Container::instance()->clock();
		$expiry = $clock->now()->getTimestamp() + ( max( 5, (int) Settings::get( 'purchase.order_expiry_minutes', 60 ) ) * 60 );

		/*
		 * The gateway re-reads the package and refuses any amount that does
		 * not match the package price, so the value sent here is only the
		 * expected value, never the authority.
		 */
		$order = array(
			'user_id'         => $user_id,
			'package_id'      => (string) $package['id'],
			'amount_minor'    => (int) $package['price_minor'],
			'currency'        => strtoupper( (string) $package['currency'] ),
			'coins_total'     => (int) $package['coins_total'],
			'idempotency_key' => 'purchase:' . $user_id . ':' . $package['id'] . ':' . $this->attempt_id( $user_id, $package ),
			'expires_at'      => gmdate( 'Y-m-d H:i:s', $expiry ),
		);

		/**
		 * Fires before a gateway creates its checkout session.
		 *
		 * @param array  $order   The order snapshot.
		 * @param object $gateway The gateway instance.
		 */
		do_action( 'mcr_before_create_order', $order, $gateway );

		$result = $gateway->create_order( $order );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$result = (array) $result;

		return array_merge(
			array(
				'order_id' => (string) ( $result['order_id'] ?? '' ),
				'amount'   => PackageCalculator::fromMinorUnits( (int) $package['price_minor'] ),
				'currency' => $order['currency'],
				'coins'    => $order['coins_total'],
				'status'   => 'pending',
			),
			$result
		);
	}

	/**
	 * Returns a de-duplication token for a purchase attempt.
	 *
	 * A double-clicked Buy button must not create two orders (and two
	 * charges), but the user must still be able to buy the same package
	 * again later. The token is therefore reused for a short window and
	 * then replaced.
	 *
	 * @param int   $user_id User id.
	 * @param array $package Package definition.
	 * @return string
	 */
	private function attempt_id( $user_id, array $package ) {
		$clock    = Container::instance()->clock();
		$now      = $clock->now()->getTimestamp();
		$meta_key = '_mcr_attempt_' . $package['id'];

		if ( function_exists( 'get_user_meta' ) ) {
			$meta = get_user_meta( $user_id, $meta_key, true );

			if ( is_array( $meta ) && isset( $meta['token'], $meta['expires'] ) && $now < (int) $meta['expires'] ) {
				return (string) $meta['token'];
			}

			$token = wp_generate_uuid4();
			update_user_meta(
				$user_id,
				$meta_key,
				array(
					'token'   => $token,
					'expires' => $now + 120,
				)
			);

			return $token;
		}

		static $tokens = array();
		$key = $user_id . ':' . $package['id'];
		if ( isset( $tokens[ $key ] ) && $now < $tokens[ $key ]['expires'] ) {
			return $tokens[ $key ]['token'];
		}

		$token = wp_generate_uuid4();
		$tokens[ $key ] = array(
			'token'   => $token,
			'expires' => $now + 120,
		);

		return $token;
	}

	/**
	 * Expires stale pending orders.
	 *
	 * @return int Number of orders expired.
	 */
	public function expireStale() {
		global $wpdb;

		$clock = Container::instance()->clock();
		$table = $wpdb->prefix . 'mcr_payments';

		return (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET status = 'expired'
				 WHERE status = 'pending' AND expires_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$clock->mysql_gmt()
			)
		);
	}

	/**
	 * Fetches an order by its order id.
	 *
	 * @param string $order_id Order id.
	 * @return array|null
	 */
	public function get_by_order_id( $order_id ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . $wpdb->prefix . 'mcr_payments WHERE order_id = %s LIMIT 1', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				(string) $order_id
			),
			\ARRAY_A
		);

		return $row ? $row : null;
	}

	/**
	 * Fetches an order belonging to a specific user.
	 *
	 * This is the IDOR guard: an order is only ever returned when the
	 * requested user matches the order's owner.
	 *
	 * @param string $order_id Order id.
	 * @param int    $user_id  User id.
	 * @return array|null
	 */
	public function get_for_user( $order_id, $user_id ) {
		$order = $this->get_by_order_id( $order_id );

		if ( ! $order ) {
			return null;
		}

		if ( (int) $order['user_id'] !== absint( $user_id ) ) {
			return null;
		}

		return $order;
	}

	/**
	 * Lists a user's orders.
	 *
	 * @param int $user_id User id.
	 * @param int $limit   Limit.
	 * @return array
	 */
	public function list_for_user( $user_id, $limit = 20 ) {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . $wpdb->prefix . 'mcr_payments WHERE user_id = %d ORDER BY id DESC LIMIT %d', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				absint( $user_id ),
				max( 1, min( 100, absint( $limit ) ) )
			),
			\ARRAY_A
		);

		return (array) $rows;
	}

	/**
	 * Generates a unique, unguessable order id.
	 *
	 * @return string
	 */
	private function generate_order_id() {
		return 'mcr_' . gmdate( 'YmdHis' ) . '_' . strtolower( bin2hex( random_bytes( 8 ) ) );
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

	/**
	 * Returns the client user agent.
	 *
	 * @return string
	 */
	private function client_agent() {
		$agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		return substr( $agent, 0, 255 );
	}
}
