<?php
/**
 * WooCommerce bridge gateway.
 *
 * Sells coin packages through the buyer's existing WooCommerce checkout.
 * Each package is mapped to a simple WooCommerce product (virtual, price
 * === package price). The MCR payment row keeps the authoritative snapshot;
 * WooCommerce only moves the money.
 *
 * ANTI-TAMPERING (price integrity):
 *
 * A coin is only credited when the completed WC order's LINE TOTAL — re-read
 * from the order object, not from anything the browser sent — equals the
 * package's price in integer minor units, and the order currency equals the
 * package currency. A client (or a tampering plugin) that discounts the
 * cart therefore produces an order that is refused credit with an audit
 * note on the WC order and a security event.
 *
 * IDEMPOTENCY: on the WooCommerce order id. The MCR row uses
 * provider_tx_id = 'wc_{wc_order_id}' (UNIQUE with the gateway), and the
 * ledger key 'payment:woocommerce:{wc_order_id}' no-ops any repeat.
 *
 * @package MCR
 */

namespace MCR\Gateways;

use MCR\Payments\PaymentResult;
use MCR\Payments\Packages;
use WP_Error;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce checkout bridge.
 */
class WooCommerce extends Gateway {

	/**
	 * Whether the WooCommerce action hooks were queued.
	 *
	 * @var bool
	 */
	private static $hooks_registered = false;

	/**
	 * @return string
	 */
	public function get_id() {
		return 'woocommerce';
	}

	/**
	 * @return string
	 */
	public function get_title() {
		return __( 'WooCommerce checkout', 'madara-coin-rewards' );
	}

	/**
	 * @return string
	 */
	public function get_description() {
		return __( 'Adds a coin package as a WooCommerce product and processes it through the shop checkout. Requires each package to be mapped to a product priced exactly like the package.', 'madara-coin-rewards' );
	}

	/**
	 * Available only when WooCommerce is installed and the bridge is on.
	 *
	 * @return bool
	 */
	public function is_available() {
		if ( 'off' === (string) $this->get_setting( 'enabled', 'on' ) ) {
			return false;
		}

		return class_exists( 'WooCommerce' )
			&& function_exists( 'wc_get_order' )
			&& function_exists( 'wc_get_product' )
			&& function_exists( 'wc_create_order' )
			&& class_exists( 'WC_Order' );
	}

	/**
	 * Queues the WooCommerce action hooks once.
	 *
	 * @return void
	 */
	public function register_hooks() {
		if ( self::$hooks_registered ) {
			return;
		}

		self::$hooks_registered = true;

		add_action( 'woocommerce_order_status_changed', array( $this, 'on_order_status_changed' ), 10, 4 );
		add_action( 'woocommerce_payment_complete', array( $this, 'on_payment_complete' ), 10, 1 );
		add_action( 'woocommerce_order_status_refunded', array( $this, 'on_order_refunded' ), 10, 1 );
	}

	/**
	 * The package => WC product id map (setting product_map).
	 *
	 * @return array<string,int>
	 */
	public function get_product_map() {
		$map = $this->get_setting( 'product_map', array() );

		if ( ! is_array( $map ) ) {
			return array();
		}

		$clean = array();

		foreach ( $map as $package_id => $product_id ) {
			$package_id = sanitize_key( (string) $package_id );
			$product_id = absint( $product_id );

			if ( '' === $package_id || ! $product_id ) {
				continue;
			}

			$clean[ $package_id ] = $product_id;
		}

		return $clean;
	}

	/**
	 * Creates a dedicated WooCommerce order carrying the MCR pending row.
	 *
	 * A direct WC order (instead of the cart flow) binds the package and the
	 * internal MCR order id to the WC order as metadata immediately, so the
	 * credit path is deterministic no matter which WC gateway completes it.
	 *
	 * @param array $order Order args (see Gateway::create_order()).
	 * @return array|WP_Error array{redirect_url:string,order_id:string}|WP_Error.
	 */
	public function create_order( array $order ) {
		if ( ! $this->is_available() ) {
			return new WP_Error( 'mcr_wc_missing', __( 'WooCommerce is required for this payment method.', 'madara-coin-rewards' ) );
		}

		$validated = $this->validate_order_args( $order );

		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$map     = $this->get_product_map();
		$product_id = absint( $map[ $validated['package_id'] ] ?? 0 );

		if ( ! $product_id ) {
			return new WP_Error( 'mcr_wc_no_product', __( 'No WooCommerce product is mapped to this coin package. Map it in the settings first.', 'madara-coin-rewards' ) );
		}

		$product = wc_get_product( $product_id );

		if ( ! $product || ! $product instanceof \WC_Product ) {
			return new WP_Error( 'mcr_wc_no_product', __( 'The mapped WooCommerce product no longer exists.', 'madara-coin-rewards' ) );
		}

		// Currency must match: no FX conversion.
		$wc_currency = function_exists( 'get_woocommerce_currency' ) ? strtoupper( (string) get_woocommerce_currency() ) : '';

		if ( '' !== $wc_currency && $wc_currency !== strtoupper( (string) $validated['currency'] ) ) {
			return new WP_Error( 'mcr_currency_mismatch', __( 'Currency mismatch between the coin package and the shop currency.', 'madara-coin-rewards' ) );
		}

		// The product price must equal the package price in minor units —
		// caught at order creation instead of at credit time where possible.
		$price_raw = $product->get_price( 'edit' );

		if ( ! is_string( $price_raw ) && ! is_numeric( $price_raw ) ) {
			$price_raw = $product->get_price();
		}

		if ( '' === (string) $price_raw || null === $price_raw ) {
			return new WP_Error( 'mcr_wc_no_price', __( 'The mapped WooCommerce product has no price set.', 'madara-coin-rewards' ) );
		}

		$price_minor = self::to_minor( $price_raw );

		if ( $price_minor !== (int) $validated['amount_minor'] ) {
			return new WP_Error( 'mcr_wc_price_mismatch', __( 'The WooCommerce product price must exactly match the package price. Update the product or the package.', 'madara-coin-rewards' ) );
		}

		// Persist the MCR pending order first — it is the snapshot the
		// credit path verifies against.
		$created = $this->create_order_row(
			array(
				'user_id'         => $validated['user_id'],
				'package_id'      => $validated['package_id'],
				'amount_minor'    => $validated['amount_minor'],
				'currency'        => $validated['currency'],
				'coins_total'     => $validated['coins_total'],
				'idempotency_key' => $validated['idempotency_key'],
				'expires_at'      => $this->gmt_time( \DAY_IN_SECONDS ),
			)
		);

		if ( is_wp_error( $created ) ) {
			return $created;
		}

		$row = $created['row'];

		if ( self::STATUS_PENDING !== (string) $row['status'] ) {
			$tx = (string) $row['provider_tx_id'];

			if ( 0 === strpos( $tx, 'wc_' ) ) {
				$existing = absint( substr( $tx, 3 ) );

				if ( $existing && function_exists( 'wc_get_order' ) && wc_get_order( $existing ) ) {
					$existing_order = wc_get_order( $existing );

					return array(
						'redirect_url' => esc_url_raw( (string) $existing_order->get_checkout_payment_url() ),
						'order_id'     => (string) $row['order_id'],
						'duplicate'    => true,
					);
				}
			}

			return new WP_Error( 'mcr_order_exists', __( 'This purchase is already in progress. Reload the buy page to retry.', 'madara-coin-rewards' ) );
		}

		$wc_order = wc_create_order(
			array(
				'customer_id' => absint( $validated['user_id'] ),
				'created_via' => 'mcr_buy_coins',
			)
		);

		if ( is_wp_error( $wc_order ) || ! $wc_order instanceof \WC_Order ) {
			$this->mark_order_failed( (int) $row['id'], 'wc_create_failed' );

			return new WP_Error( 'mcr_wc_create_failed', __( 'The WooCommerce order could not be created.', 'madara-coin-rewards' ) );
		}

		$item_id = $wc_order->add_product( $product, 1 );

		if ( false === $item_id || is_wp_error( $item_id ) ) {
			$wc_order->delete( true );
			$this->mark_order_failed( (int) $row['id'], 'wc_add_product_failed' );

			return new WP_Error( 'mcr_wc_add_item_failed', __( 'The coin package product could not be added to the order.', 'madara-coin-rewards' ) );
		}

		$wc_order->update_meta_data( '_mcr_package_id', (string) $validated['package_id'] );
		$wc_order->update_meta_data( '_mcr_order_uuid', (string) $row['order_id'] );
		$wc_order->update_meta_data( '_mcr_coins_total', (int) $validated['coins_total'] );
		$wc_order->calculate_totals();
		$wc_order->add_order_note(
			sprintf(
				/* translators: %s: coin amount */
				__( 'Madara Coin & Rewards: coin package order (%s coins) created.', 'madara-coin-rewards' ),
				number_format_i18n( absint( $validated['coins_total'] ) )
			)
		);
		$wc_order->save();

		$this->update_provider_tx( (int) $row['id'], 'wc_' . absint( $wc_order->get_id() ) );

		return array(
			'redirect_url' => esc_url_raw( (string) $wc_order->get_checkout_payment_url() ),
			'order_id'     => (string) $row['order_id'],
		);
	}

	/**
	 * There is no plugin webhook: WooCommerce delivers its own events via
	 * the registered order-status hooks.
	 *
	 * @param mixed $request Ignored.
	 * @return WP_Error Always an error.
	 */
	public function verify_webhook( $request ) {
		return new WP_Error(
			'mcr_wc_no_webhook',
			__( 'The WooCommerce bridge listens to internal WooCommerce order hooks, not webhooks.', 'madara-coin-rewards' ),
			array( 'status' => 400 )
		);
	}

	/**
	 * woocommerce_order_status_changed handler.
	 *
	 * Credits only on processing/completed transitions.
	 *
	 * @param int    $order_id   WC order id.
	 * @param string $old_status Previous status.
	 * @param string $new_status New status.
	 * @param mixed  $order      WC_Order (or fetched from the id).
	 * @return void
	 */
	public function on_order_status_changed( $order_id, $old_status, $new_status, $order = null ) {
		if ( ! in_array( (string) $new_status, array( 'processing', 'completed' ), true ) ) {
			return;
		}

		$this->try_credit( $order_id );
	}

	/**
	 * woocommerce_payment_complete handler for gateways that never switch
	 * order status themselves.
	 *
	 * @param int $order_id WC order id.
	 * @return void
	 */
	public function on_payment_complete( $order_id ) {
		$this->try_credit( $order_id );
	}

	/**
	 * The verification + credit pipeline for one WC order. Idempotent.
	 *
	 * @param int $order_id WC order id.
	 * @return array|WP_Error Credit summary or an error.
	 */
	public function try_credit( $order_id ) {
		$order_id = absint( $order_id );

		if ( ! $order_id ) {
			return new WP_Error( 'mcr_wc_order_unknown', __( 'The WooCommerce order was not found.', 'madara-coin-rewards' ), array( 'status' => 404 ) );
		}

		if ( ! $this->is_available() ) {
			return new WP_Error( 'mcr_wc_missing', __( 'WooCommerce is required for this payment method.', 'madara-coin-rewards' ) );
		}

		$order = wc_get_order( $order_id );

		if ( ! $order || ! $order instanceof \WC_Order ) {
			return new WP_Error( 'mcr_wc_order_unknown', __( 'The WooCommerce order was not found.', 'madara-coin-rewards' ), array( 'status' => 404 ) );
		}

		// Non-MCR orders (a shop is usually selling other things too) are
		// outside this bridge's concern unless a mapped product is inside.
		$package = $this->resolve_package( $order );

		if ( null === $package ) {
			return new WP_Error( 'mcr_wc_no_package', __( 'The order does not contain a mapped coin package.', 'madara-coin-rewards' ) );
		}

		// Idempotency: the row, keyed on the WC order id.
		$row = $this->find_order_by_tx( 'wc_' . $order_id );

		if ( null === $row ) {
			$mcr_order_id = sanitize_text_field( (string) $order->get_meta( '_mcr_order_uuid', true ) );

			if ( '' !== $mcr_order_id ) {
				$row = $this->find_order( $mcr_order_id );
			}
		}

		if ( null === $row ) {
			// Self-healing path for orders created outside create_order()
			// (e.g. the admin built one manually from a mapped product).
			$created = $this->create_order_row(
				array(
					'user_id'         => absint( $order->get_user_id() ),
					'package_id'      => (string) $package['id'],
					'amount_minor'    => (int) $package['price_minor'],
					'currency'        => (string) $package['currency'],
					'coins_total'     => (int) $package['coins_total'],
					'idempotency_key' => 'wc_order_' . $order_id,
					'provider_tx_id'  => 'wc_' . $order_id,
					'expires_at'      => $this->gmt_time( \DAY_IN_SECONDS ),
				)
			);

			if ( is_wp_error( $created ) ) {
				return $created;
			}

			$row = $created['row'];
		}

		if ( in_array( (string) $row['status'], array( self::STATUS_PAID, self::STATUS_CREDITED ), true ) ) {
			return array(
				'credited'  => true,
				'duplicate' => true,
				'order_id'  => (string) $row['order_id'],
			);
		}

		if ( self::STATUS_REFUNDED === (string) $row['status'] ) {
			return new WP_Error( 'mcr_invalid_order_state', __( 'This payment was refunded.', 'madara-coin-rewards' ), array( 'status' => 409 ) );
		}

		if ( self::STATUS_PENDING !== (string) $row['status'] ) {
			return new WP_Error( 'mcr_invalid_order_state', __( 'This payment is not awaiting credit.', 'madara-coin-rewards' ), array( 'status' => 409 ) );
		}

		//
		// PRICE INTEGRITY — integers only, re-read from the order object.
		//
		$expected = (int) $package['price_minor'];
		$actual   = 0;
		$matched  = false;

		foreach ( $order->get_items() as $item ) {
			$item_product_id = absint( $item->get_product_id() );
			$item_package    = $this->item_package_id( $order, $item, $item_product_id );

			if ( (string) $item_package !== (string) $package['id'] ) {
				continue;
			}

			$matched  = true;
			// WC line totals are decimal strings in the order currency.
			// Rounded into minor units once, then compared as integers.
			$actual  += (int) round( ( (float) $item->get_total( 'edit' ) ) * 100 );
		}

		if ( ! $matched || $expected !== $actual ) {
			$order->add_order_note(
				sprintf(
					/* translators: 1: expected minor units, 2: actual minor units */
					__( 'Madara Coin & Rewards: price mismatch (expected %1$s, found %2$s minor units). Coins NOT credited.', 'madara-coin-rewards' ),
					number_format_i18n( $expected ),
					number_format_i18n( $actual )
				)
			);
			$order->save();

			$this->mark_order_failed( (int) $row['id'], 'wc_price_mismatch' );

			$this->log_security_event(
				'wc_price_mismatch',
				array(
					'order'    => $order_id,
					'mcr'      => (string) $row['order_id'],
					'expected' => $expected,
					'given'    => $actual,
				)
			);

			return new WP_Error( 'mcr_wc_price_mismatch', __( 'The order total does not match the package price. Coins were not credited.', 'madara-coin-rewards' ), array( 'status' => 422 ) );
		}

		$currency = strtoupper( sanitize_text_field( (string) $order->get_currency() ) );

		if ( '' !== $currency && $currency !== strtoupper( (string) $package['currency'] ) ) {
			$order->add_order_note(
				__( 'Madara Coin & Rewards: currency mismatch. Coins NOT credited.', 'madara-coin-rewards' )
			);
			$order->save();

			$this->mark_order_failed( (int) $row['id'], 'wc_currency_mismatch' );
			$this->log_security_event(
				'wc_currency_mismatch',
				array(
					'order'    => $order_id,
					'mcr'      => (string) $row['order_id'],
					'expected' => (string) $package['currency'],
					'given'    => $currency,
				)
			);

			return new WP_Error( 'mcr_wc_currency_mismatch', __( 'The order currency does not match the package currency. Coins were not credited.', 'madara-coin-rewards' ), array( 'status' => 422 ) );
		}

		// Verify the buyer owns the order before attaching it to their ledger.
		$user_id = absint( $order->get_user_id() );

		if ( $user_id !== absint( $row['user_id'] ) ) {
			$order->add_order_note( __( 'Madara Coin & Rewards: order buyer mismatch. Coins NOT credited.', 'madara-coin-rewards' ) );
			$order->save();

			$this->log_security_event(
				'wc_buyer_mismatch',
				array(
					'order' => $order_id,
					'mcr'   => (string) $row['order_id'],
				)
			);

			return new WP_Error( 'mcr_wc_buyer_mismatch', __( 'The order does not belong to the buyer of record.', 'madara-coin-rewards' ), array( 'status' => 403 ) );
		}

		$paid = $this->transition_order(
			(int) $row['id'],
			self::STATUS_PENDING,
			self::STATUS_PAID,
			array( 'paid_at' => $this->gmt_time() )
		);

		if ( ! $paid ) {
			$fresh = $this->find_order_by_id( (int) $row['id'] );
			$state = $fresh ? (string) $fresh['status'] : '';

			if ( self::STATUS_CREDITED === $state ) {
				return array(
					'credited'  => true,
					'duplicate' => true,
					'order_id'  => (string) $row['order_id'],
				);
			}

			if ( self::STATUS_PAID === $state ) {
				/*
				 * Paid but NOT credited: a previous attempt failed. Retry
				 * the credit rather than stranding the coins. The ledger's
				 * idempotency key makes the retry safe.
				 */
				$row = $fresh;
			} else {
				return new WP_Error( 'mcr_order_transition_failed', __( 'The order state changed while saving.', 'madara-coin-rewards' ), array( 'status' => 409 ) );
			}
		} else {
			$row['provider_tx_id'] = 'wc_' . $order_id;
			$row['status']         = self::STATUS_PAID;
		}

		$credit = $this->credit_order( $row, __( 'Coin package purchase (WooCommerce)', 'madara-coin-rewards' ) );

		if ( is_wp_error( $credit ) ) {
			return $credit;
		}

		$order->add_order_note(
			! empty( $credit['duplicate'] )
				? __( 'Madara Coin & Rewards: coins for this order were already credited.', 'madara-coin-rewards' )
				: sprintf(
					/* translators: %s: coin amount */
					__( 'Madara Coin & Rewards: %s coins credited.', 'madara-coin-rewards' ),
					number_format_i18n( absint( $package['coins_total'] ) )
				)
		);
		$order->save();

		return array_merge(
			$credit,
			array(
				'order_id'    => (string) $row['order_id'],
				'wc_order_id' => $order_id,
			)
		);
	}

	/**
	 * woocommerce_order_status_refunded handler: claw the coins back.
	 *
	 * @param int $order_id WC order id.
	 * @return void
	 */
	public function on_order_refunded( $order_id ) {
		$order_id = absint( $order_id );

		if ( ! $order_id ) {
			return;
		}

		$row = $this->find_order_by_tx( 'wc_' . $order_id );

		if ( null === $row ) {
			$order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;

			if ( $order && $order instanceof \WC_Order ) {
				$mcr_order_id = sanitize_text_field( (string) $order->get_meta( '_mcr_order_uuid', true ) );

				if ( '' !== $mcr_order_id ) {
					$row = $this->find_order( $mcr_order_id );
				}
			}
		}

		if ( null === $row || self::STATUS_REFUNDED === (string) $row['status'] ) {
			return;
		}

		if ( ! in_array( (string) $row['status'], array( self::STATUS_PAID, self::STATUS_CREDITED, self::STATUS_PENDING ), true ) ) {
			return;
		}

		$result = $this->refund_order( $row, __( 'WooCommerce order refunded', 'madara-coin-rewards' ) );

		if ( is_wp_error( $result ) ) {
			$this->log_security_event(
				'wc_refund_failed',
				array(
					'order' => $order_id,
					'code'  => sanitize_key( $result->get_error_code() ),
				)
			);
		}
	}

	/**
	 * Resolves the coin package carried by a WC order.
	 *
	 * @param \WC_Order $order The WC order.
	 * @return array|null Normalized package, or null when none applies.
	 */
	private function resolve_package( $order ) {
		$package_id = sanitize_key( (string) $order->get_meta( '_mcr_package_id', true ) );

		if ( '' === $package_id ) {
			$map = $this->get_product_map();
			$reverse = array_flip( $map );

			foreach ( $order->get_items() as $item ) {
				$item_meta_package = sanitize_key( (string) $item->get_meta( '_mcr_package_id', true ) );

				if ( '' !== $item_meta_package ) {
					$package_id = $item_meta_package;
					break;
				}

				$item_product_id = absint( $item->get_product_id() );

				if ( isset( $reverse[ $item_product_id ] ) ) {
					$package_id = sanitize_key( (string) $reverse[ $item_product_id ] );
					break;
				}
			}
		}

		if ( '' === $package_id ) {
			return null;
		}

		return Packages::get( $package_id );
	}

	/**
	 * The package id a line item belongs to.
	 *
	 * @param \WC_Order        $order            Parent order.
	 * @param \WC_Order_Item   $item             The line item.
	 * @param int              $item_product_id  The item's product id.
	 * @return string Package id or ''.
	 */
	private function item_package_id( $order, $item, $item_product_id ) {
		$meta = sanitize_key( (string) $item->get_meta( '_mcr_package_id', true ) );

		if ( '' !== $meta ) {
			return $meta;
		}

		$map     = $this->get_product_map();
		$reverse = array_flip( $map );

		if ( isset( $reverse[ $item_product_id ] ) ) {
			return sanitize_key( (string) $reverse[ $item_product_id ] );
		}

		return '';
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
				'label'       => __( 'Enable WooCommerce bridge', 'madara-coin-rewards' ),
				'type'        => 'toggle',
				'description' => __( 'Sells coin packages through the shop checkout. WooCommerce must be active.', 'madara-coin-rewards' ),
				'default'     => 'on',
			),
			array(
				'id'          => 'product_map',
				'label'       => __( 'Package → product mapping', 'madara-coin-rewards' ),
				'type'        => 'textarea',
				'description' => __( 'One mapping per line, format: package_id=product_id (e.g. starter=1042). Each product must be a simple, virtual product priced exactly like its package; a product price that differs makes the package unsellable by design.', 'madara-coin-rewards' ),
			),
		);
	}
}
