<?php
/**
 * Payment result value object.
 *
 * An immutable result shared between gateway webhook receivers, manual admin
 * actions and the purchase controller. Instances are only created through
 * the named constructors; every getter is a pure read.
 *
 * MONEY REPRESENTATION (non-negotiable):
 *
 * All monetary amounts in the payment layer are **integers in minor units**
 * (cents for USD/EUR, e.g. 499 === 4.99). Prices live in the database as
 * DECIMAL(12,2) purely for storage and admin readability. Comparisons are
 * always made between two integers, never between floats, so binary
 * floating point drift (0.1 + 0.2 !== 0.3) can never make a wrong price look
 * correct. Decimal strings read from the database are converted exactly once
 * at the storage boundary with PackageCalculator::toMinorUnits().
 *
 * @package MCR
 */

namespace MCR\Payments;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable payment result.
 */
final class PaymentResult {

	/**
	 * Payment confirmed by the provider.
	 */
	const STATUS_PAID = 'paid';

	/**
	 * Created, awaiting the provider.
	 */
	const STATUS_PENDING = 'pending';

	/**
	 * Provider rejected, or the payment did not go through.
	 */
	const STATUS_FAILED = 'failed';

	/**
	 * Checkout closed without a payment (timeout or cancel).
	 */
	const STATUS_EXPIRED = 'expired';

	/**
	 * Money returned to the buyer.
	 */
	const STATUS_REFUNDED = 'refunded';

	/**
	 * Gateway slug the result belongs to.
	 *
	 * @var string
	 */
	private $gateway_id = '';

	/**
	 * Internal MCR order id (not the provider reference).
	 *
	 * @var string
	 */
	private $order_id = '';

	/**
	 * The provider's unique transaction id. This is the idempotency anchor.
	 *
	 * @var string
	 */
	private $provider_tx_id = '';

	/**
	 * Payment status; one of the STATUS_* constants.
	 *
	 * @var string
	 */
	private $status = '';

	/**
	 * Amount in integer minor units. Never a float.
	 *
	 * @var int
	 */
	private $amount_minor = 0;

	/**
	 * ISO-4217 currency code, uppercase.
	 *
	 * @var string
	 */
	private $currency = '';

	/**
	 * Free-form metadata from the provider (sanitized).
	 *
	 * @var array
	 */
	private $metadata = array();

	/**
	 * Raw provider response, kept for audits only. Never trusted by callers.
	 *
	 * @var array
	 */
	private $raw = array();

	/**
	 * Error details: array( 'code' => string, 'message' => string ) or null.
	 *
	 * @var array|null
	 */
	private $error = null;

	/**
	 * Private: use the named constructors.
	 *
	 * @param array $args Result data.
	 */
	private function __construct( array $args ) {
		$this->gateway_id     = isset( $args['gateway_id'] ) ? sanitize_key( (string) $args['gateway_id'] ) : '';
		$this->order_id       = isset( $args['order_id'] ) ? sanitize_text_field( (string) $args['order_id'] ) : '';
		$this->provider_tx_id = isset( $args['provider_tx_id'] ) ? sanitize_text_field( (string) $args['provider_tx_id'] ) : '';
		$this->amount_minor   = isset( $args['amount_minor'] ) ? absint( $args['amount_minor'] ) : 0;
		$this->metadata       = isset( $args['metadata'] ) && is_array( $args['metadata'] ) ? $args['metadata'] : array();
		$this->raw            = isset( $args['raw'] ) && is_array( $args['raw'] ) ? $args['raw'] : array();

		$currency = isset( $args['currency'] ) ? sanitize_text_field( (string) $args['currency'] ) : '';
		$this->currency = strtoupper( substr( $currency, 0, 8 ) );

		$status = isset( $args['status'] ) ? (string) $args['status'] : self::STATUS_PENDING;
		$this->status = in_array( $status, self::statuses(), true ) ? $status : self::STATUS_PENDING;

		if ( isset( $args['error'] ) ) {
			$error = $args['error'];

			if ( is_array( $error ) && isset( $error['code'] ) ) {
				$this->error = array(
					'code'    => sanitize_key( (string) $error['code'] ),
					'message' => isset( $error['message'] ) ? (string) $error['message'] : '',
				);
			} elseif ( $error instanceof WP_Error ) {
				$this->error = array(
					'code'    => sanitize_key( $error->get_error_code() ),
					'message' => (string) $error->get_error_message(),
				);
			}
		}
	}

	/**
	 * Returns the list of valid statuses.
	 *
	 * @return array
	 */
	public static function statuses() {
		return array(
			self::STATUS_PAID,
			self::STATUS_PENDING,
			self::STATUS_FAILED,
			self::STATUS_EXPIRED,
			self::STATUS_REFUNDED,
		);
	}

	/**
	 * A confirmed, successful payment.
	 *
	 * @param array $args { gateway_id, order_id, provider_tx_id, amount_minor, currency, metadata, raw }.
	 * @return self
	 */
	public static function paid( array $args = array() ) {
		return new self( array_merge( $args, array( 'status' => self::STATUS_PAID ) ) );
	}

	/**
	 * A payment the provider rejected or that could not be verified.
	 *
	 * @param string $error_code    Machine readable error code.
	 * @param string $error_message Optional human readable message.
	 * @param array  $args          Additional result data.
	 * @return self
	 */
	public static function failed( $error_code = 'unknown', $error_message = '', array $args = array() ) {
		return new self(
			array_merge(
				$args,
				array(
					'status' => self::STATUS_FAILED,
					'error'  => array(
						'code'    => (string) $error_code,
						'message' => (string) $error_message,
					),
				)
			)
		);
	}

	/**
	 * An order created but not yet confirmed by the provider.
	 *
	 * @param array $args Additional result data.
	 * @return self
	 */
	public static function pending( array $args = array() ) {
		return new self( array_merge( $args, array( 'status' => self::STATUS_PENDING ) ) );
	}

	/**
	 * A checkout that expired or was cancelled without a payment.
	 *
	 * @param array $args Additional result data.
	 * @return self
	 */
	public static function expired( array $args = array() ) {
		return new self( array_merge( $args, array( 'status' => self::STATUS_EXPIRED ) ) );
	}

	/**
	 * A payment returned to the buyer.
	 *
	 * @param array $args Additional result data.
	 * @return self
	 */
	public static function refunded( array $args = array() ) {
		return new self( array_merge( $args, array( 'status' => self::STATUS_REFUNDED ) ) );
	}

	/**
	 * The payment status; one of the STATUS_* constants.
	 *
	 * @return string
	 */
	public function get_status() {
		return $this->status;
	}

	/**
	 * Whether coins may be credited for this result.
	 *
	 * @return bool
	 */
	public function is_paid() {
		return self::STATUS_PAID === $this->status;
	}

	/**
	 * @return bool
	 */
	public function is_pending() {
		return self::STATUS_PENDING === $this->status;
	}

	/**
	 * @return bool
	 */
	public function is_failed() {
		return self::STATUS_FAILED === $this->status;
	}

	/**
	 * @return bool
	 */
	public function is_expired() {
		return self::STATUS_EXPIRED === $this->status;
	}

	/**
	 * @return bool
	 */
	public function is_refunded() {
		return self::STATUS_REFUNDED === $this->status;
	}

	/**
	 * @return string
	 */
	public function get_gateway_id() {
		return $this->gateway_id;
	}

	/**
	 * @return string
	 */
	public function get_order_id() {
		return $this->order_id;
	}

	/**
	 * @return string
	 */
	public function get_provider_tx_id() {
		return $this->provider_tx_id;
	}

	/**
	 * Amount as an integer in minor units (cents). Integers only, never a
	 * float, so price comparisons never drift.
	 *
	 * @return int
	 */
	public function get_amount() {
		return $this->amount_minor;
	}

	/**
	 * Alias of get_amount() making the unit explicit.
	 *
	 * @return int Minor units.
	 */
	public function get_amount_minor() {
		return $this->amount_minor;
	}

	/**
	 * ISO-4217 currency code, uppercase.
	 *
	 * @return string
	 */
	public function get_currency() {
		return $this->currency;
	}

	/**
	 * @return array
	 */
	public function get_metadata() {
		return $this->metadata;
	}

	/**
	 * Raw provider payload (audit use only).
	 *
	 * @return array
	 */
	public function get_raw() {
		return $this->raw;
	}

	/**
	 * Error details when the payment failed, null otherwise.
	 *
	 * @return array|null { code: string, message: string }|null
	 */
	public function get_error() {
		return $this->error;
	}

	/**
	 * @return bool
	 */
	public function has_error() {
		return null !== $this->error;
	}
}
