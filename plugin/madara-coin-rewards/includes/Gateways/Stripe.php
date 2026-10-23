<?php
/**
 * Stripe Checkout gateway (no SDK dependency).
 *
 * Creates real Stripe Checkout Sessions through the Stripe HTTP API and
 * verifies inbound webhooks against the Stripe-Signature header.
 *
 * SETUP (site owner, all obtained from https://dashboard.stripe.com):
 *  - publishable_key: Developers → API keys → Publishable key (pk_...).
 *  - secret_key:      Developers → API keys → Secret key (sk_...). Stored
 *                     encrypted at rest when sodium is available.
 *  - webhook_secret:  Create a webhook endpoint pointing at
 *                     {site}/wp-json/mcr/v1/stripe/webhook, subscribe to
 *                     `checkout.session.completed`,
 *                     `checkout.session.expired`, `charge.refunded`, and
 *                     copy the endpoint's Signing secret (whsec_...).
 *
 * All money is compared in INTEGER MINOR UNITS. The order row stores a
 * DECIMAL(12,2) amount; the Stripe `amount_total` (int cents) is compared
 * against PackageCalculator::toMinorUnits() of that decimal — never against
 * a float, so 0.1 + 0.2 drift can never fake a match.
 *
 * @package MCR
 */

namespace MCR\Gateways;

use MCR\Payments\PaymentResult;
use MCR\Settings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Stripe Checkout via the HTTP API.
 */
class Stripe extends Gateway {

	/**
	 * Stripe REST API base.
	 */
	const API_BASE = 'https://api.stripe.com/v1';

	/**
	 * Webhook signature tolerance (Stripe's default, seconds).
	 */
	const SIGNATURE_TOLERANCE = 300;

	/**
	 * Marker prefix of encrypted-at-rest secrets.
	 */
	const SECRET_PREFIX = 'mcrenc::';

	/**
	 * Whether the webhook route was already registered.
	 *
	 * @var bool
	 */
	private static $routes_registered = false;

	/**
	 * Whether hooks were queued.
	 *
	 * @var bool
	 */
	private static $hooks_registered = false;

	/**
	 * @return string
	 */
	public function get_id() {
		return 'stripe';
	}

	/**
	 * @return string
	 */
	public function get_title() {
		return __( 'Stripe Checkout', 'madara-coin-rewards' );
	}

	/**
	 * @return string
	 */
	public function get_description() {
		return __( 'Redirects the buyer to a Stripe-hosted checkout and credits coins only after Stripe confirms the payment via a signed webhook.', 'madara-coin-rewards' );
	}

	/**
	 * Available only with all three credentials present. No network calls.
	 *
	 * @return bool
	 */
	public function is_available() {
		if ( 'off' === (string) $this->get_setting( 'enabled', 'on' ) ) {
			return false;
		}

		return '' !== $this->publishable_key()
			&& '' !== $this->secret_key()
			&& '' !== $this->webhook_secret();
	}

	/**
	 * The decryptable secret key (never echoed).
	 *
	 * @return string
	 */
	private function secret_key() {
		return self::decrypt_secret( (string) $this->get_setting( 'secret_key', '' ) );
	}

	/**
	 * The publishable key.
	 *
	 * @return string
	 */
	private function publishable_key() {
		return sanitize_text_field( (string) $this->get_setting( 'publishable_key', '' ) );
	}

	/**
	 * The webhook signing secret.
	 *
	 * @return string
	 */
	private function webhook_secret() {
		return self::decrypt_secret( (string) $this->get_setting( 'webhook_secret', '' ) );
	}

	/**
	 * Queues the REST route registration on rest_api_init.
	 *
	 * @return void
	 */
	public function register_hooks() {
		if ( self::$hooks_registered ) {
			return;
		}

		self::$hooks_registered = true;

		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Registers POST /mcr/v1/stripe/webhook (permission: none — signature only).
	 *
	 * @return void
	 */
	public function register_rest_routes() {
		if ( self::$routes_registered || ! function_exists( 'register_rest_route' ) ) {
			return;
		}

		self::$routes_registered = true;

		register_rest_route(
			'mcr/v1',
			'/stripe/webhook',
			array(
				'methods'             => 'POST',
				'callback'            => function ( WP_REST_Request $request ) {
					return $this->handle_webhook( $request );
				},
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Creates a Stripe Checkout Session and persists the MCR pending order.
	 *
	 * @param array $order Order args (see Gateway::create_order()).
	 * @return array|WP_Error array{redirect_url:string,order_id:string}|WP_Error.
	 */
	public function create_order( array $order ) {
		$secret = $this->secret_key();

		if ( '' === $secret ) {
			return new WP_Error( 'mcr_stripe_not_configured', __( 'Stripe is not configured.', 'madara-coin-rewards' ) );
		}

		$validated = $this->validate_order_args( $order );

		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$currency = strtolower( $this->get_currency() );

		if ( '' !== $currency && strtolower( (string) $validated['currency'] ) !== $currency ) {
			return new WP_Error( 'mcr_currency_mismatch', __( 'Currency mismatch between package and gateway.', 'madara-coin-rewards' ) );
		}

		// Persist the pending order first: if the Stripe call fails the
		// order remains as the idempotent record of the intent.
		$created = $this->create_order_row(
			array(
				'user_id'         => $validated['user_id'],
				'package_id'      => $validated['package_id'],
				'amount_minor'    => $validated['amount_minor'],
				'currency'        => $validated['currency'],
				'coins_total'     => $validated['coins_total'],
				'idempotency_key' => $validated['idempotency_key'],
				'expires_at'      => $this->gmt_time( 30 * \MINUTE_IN_SECONDS ),
			)
		);

		if ( is_wp_error( $created ) ) {
			return $created;
		}

		$row = $created['row'];

		if ( self::STATUS_PENDING !== (string) $row['status'] ) {
			return new WP_Error( 'mcr_order_exists', __( 'This purchase is already in progress. Reload the buy page to retry.', 'madara-coin-rewards' ), array( 'order_id' => (string) $row['order_id'] ) );
		}

		$package_title = '' !== (string) $validated['package']['title']
			? (string) $validated['package']['title']
			: __( 'Coin package', 'madara-coin-rewards' );

		$params = array(
			'mode'        => 'payment',
			'success_url' => add_query_arg(
				array(
					'mcr_order'  => (string) $row['order_id'],
					'mcr_status' => 'success',
				),
				'' !== $validated['return_url'] ? $validated['return_url'] : home_url( '/' )
			),
			'cancel_url'  => add_query_arg(
				array(
					'mcr_order'  => (string) $row['order_id'],
					'mcr_status' => 'cancel',
				),
				'' !== $validated['cancel_url'] ? $validated['cancel_url'] : home_url( '/' )
			),
			'line_items'  => array(
				array(
					'quantity'   => 1,
					'price_data' => array(
						'currency'     => $currency,
						'unit_amount'  => (int) $validated['amount_minor'],
						'product_data' => array( 'name' => $package_title ),
					),
				),
			),
			'metadata'            => array(
				'order_id'    => (string) $row['order_id'],
				'package_id'  => (string) $validated['package_id'],
				'user_id'     => (string) $validated['user_id'],
				'coins_total' => (string) $validated['coins_total'],
			),
			'payment_intent_data' => array(
				'metadata' => array(
					'order_id'   => (string) $row['order_id'],
					'package_id' => (string) $validated['package_id'],
					'user_id'    => (string) $validated['user_id'],
				),
			),
		);

		$response = wp_remote_post(
			self::API_BASE . '/checkout/sessions',
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization'    => 'Bearer ' . $secret,
					'Content-Type'     => 'application/x-www-form-urlencoded',
					// Stripe-side idempotency: retries with the same key
					// return the same session instead of double-charging.
					'Idempotency-Key'  => (string) $row['order_id'],
				),
				'body'    => http_build_query( self::flatten( $params ) ),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->mark_order_failed( (int) $row['id'], 'stripe_request_failed' );

			return new WP_Error( 'mcr_stripe_request_failed', __( 'Stripe could not be reached. Please try again.', 'madara-coin-rewards' ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $body ) || empty( $body['id'] ) || empty( $body['url'] ) ) {
			$reason = 'stripe_http_' . sanitize_key( (string) wp_remote_retrieve_response_code( $response ) );

			if ( ! empty( $body['error']['message'] ) ) {
				$reason = 'stripe_api';
			}

			$this->mark_order_failed( (int) $row['id'], $reason );

			return new WP_Error(
				'mcr_stripe_create_failed',
				__( 'Stripe did not accept the payment session. Please try again.', 'madara-coin-rewards' )
			);
		}

		$this->update_provider_tx( (int) $row['id'], sanitize_text_field( (string) $body['id'] ) );

		return array(
			'redirect_url' => esc_url_raw( (string) $body['url'] ),
			'order_id'     => (string) $row['order_id'],
		);
	}

	/**
	 * Webhook entrypoint returning the HTTP response Stripe expects.
	 *
	 * Verified but unhandled events, duplicates and "already credited"
	 * deliveries all answer 200 so Stripe stops retrying; only signature
	 * and parse failures answer 4xx.
	 *
	 * @param WP_REST_Request $request The inbound request.
	 * @return WP_REST_Response
	 */
	public function handle_webhook( WP_REST_Request $request ) {
		$payload = (string) $request->get_body();
		$secret  = $this->webhook_secret();

		if ( '' === $secret ) {
			return new WP_REST_Response( array( 'error' => 'not_configured' ), 503 );
		}

		$sig_header = (string) $request->get_header( 'stripe-signature' );

		if ( ! self::verify_signature( $payload, $sig_header, $secret, self::SIGNATURE_TOLERANCE ) ) {
			$this->log_security_event(
				'stripe_webhook_bad_signature',
				array(
					'ip' => $this->request_ip(),
				)
			);

			return new WP_REST_Response( array( 'error' => 'invalid_signature' ), 400 );
		}

		$event = json_decode( $payload, true );

		if ( ! is_array( $event ) || empty( $event['type'] ) || ! isset( $event['data']['object'] ) || ! is_array( $event['data']['object'] ) ) {
			return new WP_REST_Response( array( 'error' => 'bad_payload' ), 400 );
		}

		$object = $event['data']['object'];

		switch ( sanitize_key( (string) $event['type'] ) ) {
			case 'checkout.session.completed':
				$result = $this->process_session_completed( $object );
				break;

			case 'checkout.session.expired':
				$result = $this->process_session_expired( $object );
				break;

			case 'charge.refunded':
				$result = $this->process_charge_refunded( $object );
				break;

			default:
				// Authorized but not subscribed to: ack and stop retries.
				return new WP_REST_Response( array( 'received' => true ), 200 );
		}

		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();
			$data = is_array( $data ) ? $data : array();
			$code = (int) ( $data['status'] ?? 400 );

			return new WP_REST_Response(
				array( 'error' => sanitize_key( $result->get_error_code() ) ),
				$code
			);
		}

		return new WP_REST_Response( array( 'received' => true, 'status' => $result->get_status() ), 200 );
	}

	/**
	 * verify_webhook(): the typed result of a verified Stripe delivery.
	 *
	 * @param mixed $request The inbound request (WP_REST_Request).
	 * @return PaymentResult|WP_Error
	 */
	public function verify_webhook( $request ) {
		if ( $request instanceof WP_REST_Request ) {
			$result = $this->handle_webhook( $request );

			if ( $result instanceof WP_REST_Response ) {
				$status = (int) $result->get_status();

				if ( $status >= 400 ) {
					$data = (array) $result->get_data();

					return new WP_Error(
						sanitize_key( (string) ( $data['error'] ?? 'mcr_webhook_rejected' ) ),
						__( 'The webhook delivery could not be verified.', 'madara-coin-rewards' ),
						array( 'status' => $status )
					);
				}

				$data = (array) $result->get_data();
				$text = isset( $data['status'] ) ? (string) $data['status'] : 'pending';

				$args = array(
					'gateway_id' => $this->get_id(),
					'raw'        => $data,
				);

				switch ( $text ) {
					case PaymentResult::STATUS_PAID:
						return PaymentResult::paid( $args );
					case PaymentResult::STATUS_EXPIRED:
						return PaymentResult::expired( $args );
					case PaymentResult::STATUS_REFUNDED:
						return PaymentResult::refunded( $args );
					case PaymentResult::STATUS_FAILED:
						return PaymentResult::failed(
							'mcr_payment_failed',
							__( 'The payment failed.', 'madara-coin-rewards' ),
							$args
						);
				}

				return PaymentResult::pending( $args );
			}

			return new WP_Error( 'mcr_webhook_invalid', __( 'Unexpected webhook response.', 'madara-coin-rewards' ) );
		}

		return new WP_Error( 'mcr_webhook_invalid', __( 'Invalid webhook request.', 'madara-coin-rewards' ) );
	}

	/**
	 * Handles checkout.session.completed.
	 *
	 * Does NOT trust the event body: re-retrieves the session server-side,
	 * confirms payment_status was 'paid', then compares amount_total and
	 * currency against the stored snapshot in integer minor units before
	 * any credit. On mismatch the order is marked failed and NOTHING is
	 * credited.
	 *
	 * @param array $session The event's data.object.
	 * @return PaymentResult|WP_Error
	 */
	private function process_session_completed( array $session ) {
		$session_id = sanitize_text_field( (string) ( $session['id'] ?? '' ) );

		if ( ! preg_match( '/^cs_[A-Za-z0-9_-]+$/', $session_id ) ) {
			return new WP_Error( 'mcr_stripe_bad_payload', '', array( 'status' => 400 ) );
		}

		// Idempotency step 1: the delivery may already be final. Look the
		// row up by the UNIQUE (gateway, provider_tx_id) first.
		$row = $this->find_order_by_tx( $session_id );

		if ( null === $row ) {
			// Fall back to our metadata.order_id; accepted only after the
			// server-side session fetch re-attaches the real tx id.
			$order_id = sanitize_text_field( (string) ( $session['metadata']['order_id'] ?? '' ) );
			$row      = $this->find_order( $order_id );

			if ( null === $row ) {
				return new WP_Error( 'mcr_order_unknown', '', array( 'status' => 404 ) );
			}
		}

		if ( in_array( (string) $row['status'], array( self::STATUS_PAID, self::STATUS_CREDITED ), true ) ) {
			return $this->duplicate_result( $row, PaymentResult::STATUS_PAID );
		}

		if ( self::STATUS_REFUNDED === (string) $row['status'] ) {
			return $this->duplicate_result( $row, PaymentResult::STATUS_REFUNDED );
		}

		if ( self::STATUS_PENDING !== (string) $row['status'] ) {
			return new WP_Error( 'mcr_order_invalid_state', '', array( 'status' => 409 ) );
		}

		// Server-side fetch. The event payload is not evidence of payment.
		$fetched = $this->retrieve_session( $session_id );

		if ( is_wp_error( $fetched ) ) {
			return $fetched;
		}

		if ( 'paid' !== (string) ( $fetched['payment_status'] ?? '' ) ) {
			return new WP_Error( 'mcr_stripe_not_paid', '', array( 'status' => 422 ) );
		}

		// The session's internal metadata must point back to this exact row.
		$claimed_order = sanitize_text_field( (string) ( $fetched['metadata']['order_id'] ?? '' ) );

		if ( (string) $row['order_id'] !== $claimed_order ) {
			$this->log_security_event(
				'stripe_metadata_mismatch',
				array(
					'ip'      => $this->request_ip(),
					'session' => $session_id,
					'status'  => (string) $row['status'],
					'order'   => (string) $row['order_id'],
				)
			);

			return new WP_Error( 'mcr_stripe_metadata_mismatch', '', array( 'status' => 403 ) );
		}

		//
		// Amount & currency checks — integers only, never floats.
		//
		$expected_minor = self::to_minor( $row['amount'] );
		$actual_minor   = isset( $fetched['amount_total'] ) ? absint( $fetched['amount_total'] ) : -1;

		if ( $expected_minor !== $actual_minor ) {
			$this->mark_order_failed( (int) $row['id'], 'stripe_amount_mismatch' );

			$this->log_security_event(
				'stripe_amount_mismatch',
				array(
					'ip'       => $this->request_ip(),
					'session'  => $session_id,
					'order'    => (string) $row['order_id'],
					'expected' => $expected_minor,
					'given'    => $actual_minor,
				)
			);

			// Refuse to credit. 422 tells Stripe a genuine delivery problem
			// exists; the failed row means later retries are no-ops.
			return new WP_Error( 'mcr_stripe_amount_mismatch', '', array( 'status' => 422 ) );
		}

		$currency = strtoupper( sanitize_text_field( (string) ( $fetched['currency'] ?? '' ) ) );

		if ( '' !== $currency && $currency !== strtoupper( (string) $row['currency'] ) ) {
			$this->mark_order_failed( (int) $row['id'], 'stripe_currency_mismatch' );
			$this->log_security_event(
				'stripe_currency_mismatch',
				array(
					'ip'        => $this->request_ip(),
					'session'   => $session_id,
					'order'     => (string) $row['order_id'],
					'expected'  => (string) $row['currency'],
					'given'     => $currency,
				)
			);

			return new WP_Error( 'mcr_stripe_currency_mismatch', '', array( 'status' => 422 ) );
		}

		// Idempotency step 2: conditional transition. Two concurrent
		// deliveries race; exactly one wins this UPDATE.
		$paid = $this->transition_order(
			(int) $row['id'],
			self::STATUS_PENDING,
			self::STATUS_PAID,
			array(
				'provider_tx_id' => $session_id,
				'paid_at'        => $this->gmt_time(),
			)
		);

		if ( ! $paid ) {
			$fresh = $this->find_order_by_id( (int) $row['id'] );
			$state = $fresh ? (string) $fresh['status'] : '';

			if ( self::STATUS_CREDITED === $state ) {
				// Already credited: this is a duplicate delivery.
				return $this->duplicate_result( $row, PaymentResult::STATUS_PAID );
			}

			if ( self::STATUS_PAID === $state ) {
				/*
				 * Paid but NOT credited: a previous attempt failed (for
				 * example a ledger outage). The credit must be retried
				 * here, otherwise the coins are stranded until an
				 * administrator intervenes. The ledger's idempotency key
				 * makes the retry safe.
				 */
				$row = $fresh;
			} elseif ( self::STATUS_REFUNDED === $state ) {
				return $this->duplicate_result( $row, PaymentResult::STATUS_PAID );
			} else {
				return new WP_Error( 'mcr_order_transition_failed', '', array( 'status' => 409 ) );
			}
		} else {
			$row['provider_tx_id'] = $session_id;
			$row['status']         = self::STATUS_PAID;
		}

		$credit = $this->credit_order( $row, __( 'Coin package purchase (Stripe)', 'madara-coin-rewards' ) );

		if ( is_wp_error( $credit ) ) {
			// The row stays paid, so a later delivery retries the credit.
			// The ledger's idempotency key makes that retry safe.
			return new WP_Error( 'mcr_credit_failed', '', array( 'status' => 500 ) );
		}

		return PaymentResult::paid(
			array(
				'gateway_id'     => $this->get_id(),
				'order_id'       => (string) $row['order_id'],
				'provider_tx_id' => $session_id,
				'amount_minor'   => $expected_minor,
				'currency'       => (string) $row['currency'],
				'metadata'       => is_array( $fetched['metadata'] ?? null ) ? $fetched['metadata'] : array(),
				'raw'            => array( 'session_id' => $session_id ),
			)
		);
	}

	/**
	 * Handles checkout.session.expired.
	 *
	 * @param array $session The event's data.object.
	 * @return PaymentResult|WP_Error
	 */
	private function process_session_expired( array $session ) {
		$session_id = sanitize_text_field( (string) ( $session['id'] ?? '' ) );

		if ( ! preg_match( '/^cs_[A-Za-z0-9_-]+$/', $session_id ) ) {
			return new WP_Error( 'mcr_stripe_bad_payload', '', array( 'status' => 400 ) );
		}

		$row = $this->find_order_by_tx( $session_id );

		if ( null === $row ) {
			$order_id = sanitize_text_field( (string) ( $session['metadata']['order_id'] ?? '' ) );
			$row      = $this->find_order( $order_id );

			if ( null === $row ) {
				return new WP_Error( 'mcr_order_unknown', '', array( 'status' => 404 ) );
			}
		}

		// A paid/credited payment always wins over expiry.
		if ( in_array( (string) $row['status'], array( self::STATUS_PAID, self::STATUS_CREDITED ), true ) ) {
			return $this->duplicate_result( $row, PaymentResult::STATUS_PAID );
		}

		$this->transition_order( (int) $row['id'], self::STATUS_PENDING, self::STATUS_EXPIRED );

		return PaymentResult::expired(
			array(
				'gateway_id'     => $this->get_id(),
				'order_id'       => (string) $row['order_id'],
				'provider_tx_id' => ( '' !== (string) $row['provider_tx_id'] ? (string) $row['provider_tx_id'] : $session_id ),
				'currency'       => (string) $row['currency'],
			)
		);
	}

	/**
	 * Handles charge.refunded: reverses the credited coins.
	 *
	 * The charge references a payment intent, not the session, so the
	 * intent is retrieved server-side and mapped back through its
	 * metadata.order_id.
	 *
	 * @param array $charge The event's data.object.
	 * @return PaymentResult|WP_Error
	 */
	private function process_charge_refunded( array $charge ) {
		$intent_id = sanitize_text_field( (string) ( $charge['payment_intent'] ?? '' ) );

		if ( ! preg_match( '/^pi_[A-Za-z0-9_-]+$/', $intent_id ) ) {
			return new WP_Error( 'mcr_stripe_no_payment_intent', '', array( 'status' => 200 ) );
		}

		$intent = $this->stripe_get( self::API_BASE . '/payment_intents/' . rawurlencode( $intent_id ) );

		if ( is_wp_error( $intent ) ) {
			return $intent;
		}

		$order_id = sanitize_text_field( (string) ( $intent['metadata']['order_id'] ?? '' ) );
		$row      = $this->find_order( $order_id );

		if ( null === $row ) {
			return new WP_Error( 'mcr_order_unknown', '', array( 'status' => 404 ) );
		}

		if ( self::STATUS_REFUNDED === (string) $row['status'] ) {
			return $this->duplicate_result( $row, PaymentResult::STATUS_REFUNDED );
		}

		if ( self::STATUS_PENDING === (string) $row['status'] ) {
			// Never charged (or no completed payment): nothing to reverse.
			$this->transition_order( (int) $row['id'], self::STATUS_PENDING, self::STATUS_EXPIRED );

			return new WP_Error( 'mcr_stripe_refund_without_payment', '', array( 'status' => 200 ) );
		}

		$refund = $this->refund_order(
			$row,
			sprintf(
				/* translators: %s: Stripe refund reason */
				__( 'Stripe refund (%s)', 'madara-coin-rewards' ),
				sanitize_key( (string) ( $charge['reason'] ?? 'unspecified' ) )
			)
		);

		if ( is_wp_error( $refund ) ) {
			return new WP_Error( 'mcr_stripe_refund_failed', '', array( 'status' => 500 ) );
		}

		return PaymentResult::refunded(
			array(
				'gateway_id'     => $this->get_id(),
				'order_id'       => (string) $row['order_id'],
				'provider_tx_id' => (string) $row['provider_tx_id'],
				'amount_minor'   => self::to_minor( $row['amount'] ),
				'currency'       => (string) $row['currency'],
			)
		);
	}

	/**
	 * Builds a no-op/duplicate PaymentResult from an already-final row.
	 *
	 * @param array  $row    The mcr_payments row.
	 * @param string $status Status the callers should see.
	 * @return PaymentResult
	 */
	private function duplicate_result( array $row, $status ) {
		$args = array(
			'gateway_id'     => $this->get_id(),
			'order_id'       => (string) $row['order_id'],
			'provider_tx_id' => (string) $row['provider_tx_id'],
			'amount_minor'   => self::to_minor( $row['amount'] ),
			'currency'       => (string) $row['currency'],
			'metadata'       => array( 'duplicate' => true ),
		);

		switch ( (string) $status ) {
			case PaymentResult::STATUS_REFUNDED:
				return PaymentResult::refunded( $args );
			case PaymentResult::STATUS_EXPIRED:
				return PaymentResult::expired( $args );
			case PaymentResult::STATUS_FAILED:
				return PaymentResult::failed( 'mcr_duplicate_delivery', __( 'Duplicate webhook delivery.', 'madara-coin-rewards' ), $args );
		}

		return PaymentResult::paid( $args );
	}

	/**
	 * Retrieves a Checkout Session from Stripe's API (server-to-server).
	 *
	 * @param string $session_id Session id (cs_...), pre-validated.
	 * @return array|\WP_Error Parsed session object or an error with a
	 *                         mapped HTTP status for the webhook response.
	 */
	private function retrieve_session( $session_id ) {
		$result = $this->stripe_get( self::API_BASE . '/checkout/sessions/' . rawurlencode( $session_id ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( ! is_array( $result ) || empty( $result['id'] ) ) {
			return new WP_Error( 'mcr_stripe_fetch_failed', '', array( 'status' => 422 ) );
		}

		return $result;
	}

	/**
	 * Performs a JSON GET against the Stripe API with the secret key.
	 *
	 * @param string $url Fully built API URL.
	 * @return array|WP_Error
	 */
	private function stripe_get( $url ) {
		$secret = $this->secret_key();

		if ( '' === $secret ) {
			return new WP_Error( 'mcr_stripe_not_configured', '', array( 'status' => 503 ) );
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 30,
				'redirection' => 2,
				'headers'    => array(
					'Authorization' => 'Bearer ' . $secret,
					'Accept'        => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'mcr_stripe_request_failed', '', array( 'status' => 502 ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $body ) ) {
			return new WP_Error( 'mcr_stripe_bad_json', '', array( 'status' => 502 ) );
		}

		if ( ! empty( $body['error'] ) ) {
			// Stripe said no (e.g. wrong key, unknown object).
			return new WP_Error( 'mcr_stripe_api_error', '', array( 'status' => 422 ) );
		}

		return $body;
	}

	/**
	 * Verifies the Stripe-Signature header against the raw payload.
	 *
	 * Header format: `t=<unix_ts>,v1=<hmac>,v1=<hmac2>` (multiple v1 values
	 * are allowed for key rotation). The HMAC is SHA-256 of
	 * `{timestamp}.{raw_body}` keyed by the webhook secret. Timestamps
	 * outside ±tolerance are rejected (replay window).
	 *
	 * @param string $payload     Raw request body.
	 * @param string $sig_header  The Stripe-Signature header value.
	 * @param string $secret      The webhook signing secret.
	 * @param int    $tolerance   Maximum age delta in seconds. Default 300.
	 * @return bool
	 */
	public static function verify_signature( $payload, $sig_header, $secret, $tolerance = self::SIGNATURE_TOLERANCE ) {
		$payload    = (string) $payload;
		$sig_header = (string) $sig_header;
		$secret     = trim( self::decrypt_secret( (string) $secret ) );
		$tolerance  = absint( $tolerance );

		if ( '' === $sig_header || '' === $secret || $tolerance <= 0 ) {
			return false;
		}

		$timestamp = 0;
		$sigs      = array();

		foreach ( explode( ',', $sig_header ) as $part ) {
			$kv = explode( '=', trim( $part ), 2 );

			if ( count( $kv ) !== 2 ) {
				continue;
			}

			if ( 't' === $kv[0] ) {
				$timestamp = isset( $kv[1] ) ? absint( $kv[1] ) : 0;
			}

			if ( 'v1' === $kv[0] && isset( $kv[1] ) ) {
				$sigs[] = trim( $kv[1] );
			}
		}

		if ( $timestamp <= 0 || empty( $sigs ) ) {
			return false;
		}

		// Replay window.
		if ( abs( time() - $timestamp ) > $tolerance ) {
			return false;
		}

		$expected = hash_hmac( 'sha256', $timestamp . '.' . $payload, $secret );

		foreach ( $sigs as $sig ) {
			if ( is_string( $sig ) && hash_equals( $expected, $sig ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Flattens a nested array into the form Stripe accepts:
	 * array( 'a' => array( 'b' => 'c' ) ) => array( 'a[b]' => 'c' ).
	 *
	 * @param array  $params Source array.
	 * @param string $prefix Internal key prefix (do not pass).
	 * @return array
	 */
	public static function flatten( array $params, $prefix = '' ) {
		$out = array();

		foreach ( $params as $key => $value ) {
			$full = '' === $prefix ? (string) $key : $prefix . '[' . $key . ']';

			if ( is_array( $value ) ) {
				$out = array_merge( $out, self::flatten( $value, $full ) );
				continue;
			}

			$out[ $full ] = (string) $value;
		}

		return $out;
	}

	/**
	 * Encrypts a secret at rest when sodium is available (wp_salt derived
	 * key). Plaintext falls through unchanged for hosts without sodium.
	 *
	 * @param string $plain Raw secret.
	 * @return string
	 */
	public static function encrypt_secret( $plain ) {
		$plain = (string) $plain;

		if ( '' === $plain || ! function_exists( 'sodium_crypto_secretbox' ) ) {
			return $plain;
		}

		try {
			$key    = hash( 'sha256', wp_salt( 'auth' ), true );
			$nonce  = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$cipher = sodium_crypto_secretbox( $plain, $nonce, $key );

			return self::SECRET_PREFIX . base64_encode( $nonce . $cipher );
		} catch ( \Throwable $e ) {
			return $plain;
		}
	}

	/**
	 * Decrypts a stored secret. Values without the encryption marker are
	 * returned as-is (plaintext, for stores saved before encryption was
	 * available).
	 *
	 * @param string $stored Stored value.
	 * @return string Decrypted secret, '' when undecryptable.
	 */
	public static function decrypt_secret( $stored ) {
		$stored = trim( (string) $stored );

		if ( '' === $stored ) {
			return '';
		}

		if ( 0 !== strpos( $stored, self::SECRET_PREFIX ) ) {
			return $stored;
		}

		if ( ! function_exists( 'sodium_crypto_secretbox_open' ) ) {
			return '';
		}

		$blob = base64_decode( substr( $stored, strlen( self::SECRET_PREFIX ) ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		if ( false === $blob || strlen( $blob ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return '';
		}

		try {
			$nonce   = substr( $blob, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$cipher  = substr( $blob, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$key     = hash( 'sha256', wp_salt( 'auth' ), true );
			$plain   = sodium_crypto_secretbox_open( $cipher, $nonce, $key );

			return false === $plain ? '' : (string) $plain;
		} catch ( \Throwable $e ) {
			return '';
		}
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
				'label'       => __( 'Enable Stripe', 'madara-coin-rewards' ),
				'type'        => 'toggle',
				'description' => __( 'When disabled the checkout refuses to use Stripe even if keys are present.', 'madara-coin-rewards' ),
				'default'     => 'on',
			),
			array(
				'id'          => 'publishable_key',
				'label'       => __( 'Publishable key', 'madara-coin-rewards' ),
				'type'        => 'text',
				'description' => __( 'Stripe Dashboard → Developers → API keys → Publishable key (pk_live_... or pk_test_...).', 'madara-coin-rewards' ),
			),
			array(
				'id'          => 'secret_key',
				'label'       => __( 'Secret key', 'madara-coin-rewards' ),
				'type'        => 'password',
				'description' => __( 'Stripe Dashboard → Developers → API keys → Secret key (sk_live_... or sk_test_...). Stored encrypted at rest when sodium is available; never shown back.', 'madara-coin-rewards' ),
			),
			array(
				'id'          => 'webhook_secret',
				'label'       => __( 'Webhook signing secret', 'madara-coin-rewards' ),
				'type'        => 'password',
				'description' => __( 'Create a webhook endpoint in Stripe pointing to ' . '/wp-json/mcr/v1/stripe/webhook' . ' with events checkout.session.completed, checkout.session.expired and charge.refunded, then paste its Signing secret (whsec_...).', 'madara-coin-rewards' ),
			),
			array(
				'id'          => 'currency',
				'label'       => __( 'Currency', 'madara-coin-rewards' ),
				'type'        => 'select',
				'options'     => array( 'USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY' ),
				'description' => __( 'Must match the currency of the coin package being sold. Clear to use the site currency.', 'madara-coin-rewards' ),
			),
			array(
				'id'          => 'return_url',
				'label'       => __( 'Return URL', 'madara-coin-rewards' ),
				'type'        => 'url',
				'description' => __( 'Where the buyer lands after paying. Defaults to the site home page.', 'madara-coin-rewards' ),
			),
		);
	}
}
