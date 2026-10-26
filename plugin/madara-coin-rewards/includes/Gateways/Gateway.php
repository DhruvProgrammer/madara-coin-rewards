<?php
/**
 * Abstract payment gateway.
 *
 * Every payment gateway in the plugin extends this class. Gateways never
 * instantiate themselves: they are created and looked up through
 * MCR\Gateways\Registry.
 *
 * MONEY REPRESENTATION (non-negotiable):
 *
 * All monetary arithmetic in this layer is done in **integer minor units**
 * (cents for USD/EUR). Prices sit in the `mcr_payments.amount` column as
 * DECIMAL(12,2) purely for storage and admin readability. The DECIMAL value
 * is converted exactly once at the storage boundary:
 *
 *     reading the DB:  $minor = Gateway::to_minor( $row['amount'] );
 *     writing the DB:  Gateway::money_db( $minor )
 *                      === PackageCalculator::fromMinorUnits( $minor )
 *
 * Comparisons ("did the customer actually pay the package price?") are
 * always made between two integers, never two floats, so binary floating
 * point drift (0.1 + 0.2 !== 0.3) can never turn a price mismatch into a
 * match — or hide a real match behind a false mismatch.
 *
 * IDEMPOTENCY MODEL:
 *
 * The `mcr_payments` table carries a UNIQUE key on (gateway, provider_tx_id).
 * Webhook receivers MUST:
 *   1. look up an existing row by provider_tx_id first (find_order_by_tx()),
 *   2. return a successful no-op when that row is already paid/credited,
 *      so the provider stops retrying,
 *   3. transition the row with a conditional UPDATE (old status in WHERE)
 *      so two concurrent webhook deliveries finalise exactly once,
 *   4. credit coins through the ledger with the idempotency key
 *      `payment:{gateway}:{provider_tx_id}` — a redelivered credit is a
 *      ledger-level no-op even if some other guard failed.
 *
 * @package MCR
 */

namespace MCR\Gateways;

use MCR\Ledger;
use MCR\Payments\PackageCalculator;
use MCR\Payments\Packages;
use MCR\Settings;
use MCR\Support\Clock;
use MCR\Support\WpClock;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Base class for all payment gateways.
 */
abstract class Gateway {

	/**
	 * Order status values (matches mcr_payments.status).
	 */
	const STATUS_PENDING  = 'pending';
	const STATUS_PAID     = 'paid';
	const STATUS_CREDITED = 'credited';
	const STATUS_FAILED   = 'failed';
	const STATUS_EXPIRED  = 'expired';
	const STATUS_REFUNDED = 'refunded';

	/**
	 * Columns the transition helper accepts for assignment.
	 *
	 * @var array
	 */
	const UPDATABLE_COLUMNS = array( 'provider_tx_id', 'paid_at', 'expires_at' );

	/**
	 * The setting keys that hold a secret, per gateway.
	 *
	 * The values of these keys are encrypted at rest before they are
	 * stored, and they are never rendered back into the settings form.
	 *
	 * @var array<string, string[]>
	 */
	const SECRET_FIELDS = array(
		'stripe' => array( 'secret_key', 'webhook_secret' ),
	);

	/**
	 * Per-gateway settings, as stored under `gateways.{id}` in Settings.
	 *
	 * @var array
	 */
	protected $settings;

	/**
	 * Injectable clock (site timezone, server authoritative).
	 *
	 * @var Clock
	 */
	private $clock;

	/**
	 * Constructor.
	 *
	 * @param array      $settings Gateway settings array.
	 * @param Clock|null $clock    Optional clock override (tests).
	 */
	public function __construct( array $settings = array(), ?Clock $clock = null ) {
		$this->settings = $settings;
		$this->clock    = $clock;
	}

	/**
	 * Unique gateway slug ([a-z0-9_-]); stored as mcr_payments.gateway.
	 *
	 * @return string
	 */
	abstract public function get_id();

	/**
	 * Human readable title shown to buyers and admins.
	 *
	 * @return string
	 */
	abstract public function get_title();

	/**
	 * Optional marketing/functional description.
	 *
	 * @return string
	 */
	public function get_description() {
		return '';
	}

	/**
	 * Whether the gateway can take orders right now. Must be cheap: no
	 * network calls. An unconfigured gateway is a first-class, safe state.
	 *
	 * @return bool
	 */
	abstract public function is_available();

	/**
	 * ISO-4217 currency this gateway collects in. Must match the package
	 * currency; there is no FX conversion in v1.
	 *
	 * @return string
	 */
	public function get_currency() {
		$currency = (string) $this->get_setting( 'currency', '' );

		if ( '' === $currency ) {
			$currency = (string) Settings::get( 'purchase.currency', 'USD' );
		}

		return strtoupper( substr( sanitize_text_field( $currency ), 0, 8 ) );
	}

	/**
	 * Creates a pending payment order and returns the redirect target.
	 *
	 * The implementation MUST persist a row in mcr_payments with status
	 * `pending` BEFORE returning any redirect target.
	 *
	 * @param array $order {
	 *     user_id: int, package_id: string, amount_minor: int, currency: string,
	 *     idempotency_key: string, return_url: string, cancel_url: string,
	 *     metadata: array.
	 * }
	 * @return array|WP_Error array{redirect_url:string,order_id:string}|WP_Error.
	 */
	abstract public function create_order( array $order );

	/**
	 * Verifies an inbound webhook/callback. MUST NOT trust the request body
	 * alone: re-fetch/re-derive the payment server-side where the provider
	 * allows it. Must be idempotent and must never credit without full
	 * amount/currency verification.
	 *
	 * @param mixed $request WP_REST_Request (or equivalent request object).
	 * @return PaymentResult|WP_Error
	 */
	abstract public function verify_webhook( $request );

	/**
	 * Admin settings field descriptors so the admin UI can render this
	 * gateway's settings generically.
	 *
	 * Each field: array( id, label, type, description, options?, default? ).
	 *
	 * @return array
	 */
	abstract public function get_settings_fields();

	/**
	 * Hook registration, called once by the Registry after instantiation.
	 *
	 * Webhook-capable gateways use this to queue their rest_api_init
	 * callback; the WooCommerce bridge uses it to queue WooCommerce hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {}

	/**
	 * REST route registration, executed on rest_api_init by the Registry.
	 *
	 * @return void
	 */
	public function register_rest_routes() {}

	/**
	 * Returns a setting with a default.
	 *
	 * @param string $key           Setting key.
	 * @param mixed  $default_value Default when unset.
	 * @return mixed
	 */
	protected function get_setting( $key, $default_value = null ) {
		return isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : $default_value;
	}

	/**
	 * Loads a gateway's stored settings: Settings key `gateways.{id}`.
	 *
	 * @param string $gateway_id Gateway slug.
	 * @return array
	 */
	public static function load_settings( $gateway_id ) {
		$settings = Settings::get( 'gateways.' . sanitize_key( (string) $gateway_id ), array() );

		return is_array( $settings ) ? $settings : array();
	}

	/**
	 * Persists a gateway's settings (strings are sanitized on the way in).
	 *
	 * @param string $gateway_id Gateway slug.
	 * @param array  $values     Raw settings to store.
	 * @return bool
	 */
	public static function save_settings( $gateway_id, array $values ) {
		$gateway_id = sanitize_key( (string) $gateway_id );
		$existing   = Settings::get( 'gateways.' . $gateway_id, array() );
		$existing   = is_array( $existing ) ? $existing : array();

		return Settings::update( array( 'gateways.' . $gateway_id => self::sanitize_settings( $gateway_id, $values, $existing ) ) );
	}

	/**
	 * Sanitizes a gateway's settings and encrypts its secret fields.
	 *
	 * An empty secret field means "keep the secret that is already
	 * stored", so the settings form never blanks a credential by accident.
	 *
	 * @param string $gateway_id Gateway slug.
	 * @param array  $values     Raw submitted values.
	 * @param array  $existing   The values that are already stored.
	 * @return array
	 */
	public static function sanitize_settings( $gateway_id, array $values, array $existing = array() ) {
		$gateway_id = sanitize_key( (string) $gateway_id );
		$secrets    = isset( self::SECRET_FIELDS[ $gateway_id ] ) ? self::SECRET_FIELDS[ $gateway_id ] : array();
		$clean      = $existing;

		foreach ( $values as $key => $value ) {
			$key = sanitize_key( (string) $key );

			if ( '' === $key ) {
				continue;
			}

			if ( is_array( $value ) ) {
				$clean[ $key ] = self::sanitize_setting_value( $value );
				continue;
			}

			$value = sanitize_text_field( (string) $value );

			if ( in_array( $key, $secrets, true ) ) {
				if ( '' === $value ) {
					// Keep whatever is already stored.
					continue;
				}

				$value = \MCR\Support\Secrets::encrypt( $value );
			}

			$clean[ $key ] = $value;
		}

		return $clean;
	}

	/**
	 * Recursively sanitizes one setting value.
	 *
	 * @param mixed $value Raw value.
	 * @return mixed
	 */
	private static function sanitize_setting_value( $value ) {
		if ( is_array( $value ) ) {
			return array_map( array( __CLASS__, 'sanitize_setting_value' ), $value );
		}

		return sanitize_text_field( (string) $value );
	}

	/**
	 * The site clock.
	 *
	 * @return Clock
	 */
	protected function get_clock() {
		if ( null === $this->clock ) {
			$this->clock = new WpClock();
		}

		return $this->clock;
	}

	/**
	 * Shared ledger instance.
	 *
	 * @return Ledger
	 */
	protected function ledger() {
		return Ledger::instance();
	}

	/**
	 * GMT MySQL datetime for now + offset.
	 *
	 * @param int $offset_seconds Added to the clock.
	 * @return string
	 */
	protected function gmt_time( $offset_seconds = 0 ) {
		$ts = $this->get_clock()->now()->getTimestamp() + (int) $offset_seconds;

		return gmdate( 'Y-m-d H:i:s', $ts );
	}

	/**
	 * The payments table name.
	 *
	 * @return string
	 */
	protected function table() {
		global $wpdb;

		return $wpdb->prefix . 'mcr_payments';
	}

	/**
	 * Finds a pending/paid order by internal order id.
	 *
	 * Scope guard: the row must belong to this gateway, otherwise it is
	 * treated as not found.
	 *
	 * @param string $order_id Internal MCR order id.
	 * @return array|null
	 */
	protected function find_order( $order_id ) {
		$row = $this->find_any_order( $order_id );

		if ( ! $row || (string) $row['gateway'] !== $this->get_id() ) {
			return null;
		}

		return $row;
	}

	/**
	 * Finds a payment row by order id regardless of gateway.
	 *
	 * @param string $order_id Internal MCR order id.
	 * @return array|null
	 */
	protected function find_any_order( $order_id ) {
		global $wpdb;

		$order_id = sanitize_text_field( (string) $order_id );

		if ( '' === $order_id ) {
			return null;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . $this->table() . ' WHERE order_id = %s LIMIT 1', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$order_id
			),
			\ARRAY_A
		);

		return $row ? (array) $row : null;
	}

	/**
	 * Finds a payment row of THIS gateway by its provider transaction id.
	 *
	 * The (gateway, provider_tx_id) pair is UNIQUE in mcr_payments, so this
	 * is the webhook idempotency lookup.
	 *
	 * @param string $provider_tx_id Provider transaction id.
	 * @return array|null
	 */
	protected function find_order_by_tx( $provider_tx_id ) {
		global $wpdb;

		$provider_tx_id = sanitize_text_field( (string) $provider_tx_id );

		if ( '' === $provider_tx_id ) {
			return null;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . $this->table() . ' WHERE gateway = %s AND provider_tx_id = %s LIMIT 1', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->get_id(),
				$provider_tx_id
			),
			\ARRAY_A
		);

		return $row ? (array) $row : null;
	}

	/**
	 * Finds a payment row by its database id, optionally scoped to gateway.
	 *
	 * @param int    $payment_id Row id.
	 * @param string $gateway_id Optional gateway scope.
	 * @return array|null
	 */
	public function find_order_by_id( $payment_id, $gateway_id = '' ) {
		global $wpdb;

		$payment_id = absint( $payment_id );

		if ( ! $payment_id ) {
			return null;
		}

		$sql = 'SELECT * FROM ' . $this->table() . ' WHERE id = %d'; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( '' !== (string) $gateway_id ) {
			$sql .= ' AND gateway = %s';
			$row  = $wpdb->get_row(
				$wpdb->prepare( $sql, $payment_id, sanitize_key( (string) $gateway_id ) ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				\ARRAY_A
			);
		} else {
			$row = $wpdb->get_row( $wpdb->prepare( $sql, $payment_id ), \ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		return $row ? (array) $row : null;
	}

	/**
	 * Validates and normalizes create_order() arguments.
	 *
	 * The coin amount and the price snapshot always come from the package
	 * row, never from anything the caller or browser sent: this closes the
	 * classic "pay less via a tampered amount" hole. A package snapshot is
	 * required: it supplies coins_total for the eventual credit and the
	 * authoritative amount_minor, so the caller-provided amount is only
	 * accepted when it matches the package.
	 *
	 * @param array $order Raw order args.
	 * @return array|WP_Error Normalized args or an error.
	 */
	protected function validate_order_args( array $order ) {
		$user_id = absint( $order['user_id'] ?? 0 );

		if ( ! $user_id ) {
			return new WP_Error( 'mcr_invalid_user', __( 'The buyer account could not be resolved.', 'madara-coin-rewards' ) );
		}

		$package_id = sanitize_key( (string) ( $order['package_id'] ?? '' ) );

		if ( '' === $package_id ) {
			return new WP_Error( 'mcr_invalid_package', __( 'The coin package could not be resolved.', 'madara-coin-rewards' ) );
		}

		$package = $this->package_snapshot( $package_id );

		if ( is_wp_error( $package ) ) {
			return $package;
		}

		// `amount_minor` arrives already in integer minor units (see the
		// create_order() contract): only an int-able value is accepted.
		$amount_minor = isset( $order['amount_minor'] ) && is_numeric( (string) $order['amount_minor'] )
			? absint( (int) round( (float) $order['amount_minor'] ) )
			: 0;

		if ( $amount_minor <= 0 ) {
			return new WP_Error( 'mcr_invalid_amount', __( 'The payment amount is invalid.', 'madara-coin-rewards' ) );
		}

		// Amount integrity: only the package price is accepted.
		if ( $amount_minor !== (int) $package['price_minor'] ) {
			$this->log_security_event(
				'payment_amount_mismatch',
				array(
					'gateway'  => $this->get_id(),
					'package'  => $package_id,
					'expected' => (int) $package['price_minor'],
					'given'    => $amount_minor,
				)
			);

			return new WP_Error( 'mcr_amount_mismatch', __( 'The payment amount does not match the package price.', 'madara-coin-rewards' ) );
		}

		$currency = strtoupper(
			sanitize_text_field( (string) ( $order['currency'] ?? $package['currency'] ) )
		);

		if ( ! preg_match( '/^[A-Z]{3,8}$/', $currency ) ) {
			return new WP_Error( 'mcr_invalid_currency', __( 'The payment currency is invalid.', 'madara-coin-rewards' ) );
		}

		if ( $currency !== (string) $package['currency'] ) {
			return new WP_Error( 'mcr_currency_mismatch', __( 'Currency mismatch between package and gateway.', 'madara-coin-rewards' ) );
		}

		$idempotency_key = sanitize_text_field( (string) ( $order['idempotency_key'] ?? '' ) );

		if ( '' === $idempotency_key ) {
			$idempotency_key = 'auto:' . $this->uuid();
		}

		$idempotency_key = substr( $idempotency_key, 0, 100 );

		return array(
			'user_id'         => $user_id,
			'package_id'      => $package_id,
			'package'         => $package,
			'amount_minor'    => $amount_minor,
			'currency'        => $currency,
			'coins_total'     => (int) $package['coins_total'],
			'idempotency_key' => $idempotency_key,
			'return_url'      => isset( $order['return_url'] ) ? esc_url_raw( (string) $order['return_url'] ) : '',
			'cancel_url'      => isset( $order['cancel_url'] ) ? esc_url_raw( (string) $order['cancel_url'] ) : '',
			'metadata'        => is_array( $order['metadata'] ?? null ) ? $order['metadata'] : array(),
		);
	}

	/**
	 * Loads a package snapshot (price, currency, coins_total) for an order.
	 *
	 * @param string $package_id Package id.
	 * @return array|WP_Error
	 */
	protected function package_snapshot( $package_id ) {
		$package = Packages::get( $package_id );

		if ( null === $package ) {
			return new WP_Error( 'mcr_unknown_package', __( 'This coin package is no longer available.', 'madara-coin-rewards' ) );
		}

		return $package;
	}

	/**
	 * Creates a pending order row (idempotent on the derived order id).
	 *
	 * The internal order id is derived from the gateway, the idempotency
	 * key and the buyer: the same idempotency key cannot create two rows.
	 * A UNIQUE provider_tx placeholder keeps (gateway, provider_tx_id)
	 * satisfied until the provider assigns its own reference.
	 *
	 * @param array $args { user_id, package_id, amount_minor, currency,
	 *                    coins_total, idempotency_key, expires_at,
	 *                    provider_tx_id }.
	 * @return array|WP_Error array{row:array,created:bool,duplicate:bool}|WP_Error.
	 */
	protected function create_order_row( array $args ) {
		global $wpdb;

		$idempotency_key = sanitize_text_field( (string) ( $args['idempotency_key'] ?? '' ) );

		if ( '' === $idempotency_key ) {
			$idempotency_key = 'auto:' . $this->uuid();
		}

		$order_id = isset( $args['order_id'] ) && '' !== (string) $args['order_id']
			? sanitize_text_field( (string) $args['order_id'] )
			: self::derive_order_id( $this->get_id(), $idempotency_key, absint( $args['user_id'] ?? 0 ) );

		// Fast path: the idempotent order already exists.
		$existing = $this->find_any_order( $order_id );

		if ( $existing ) {
			return array(
				'row'       => $existing,
				'created'   => false,
				'duplicate' => true,
			);
		}

		$amount    = sprintf( '%.2F', self::money_db( absint( $args['amount_minor'] ?? 0 ) ) );
		$now       = $this->gmt_time();
		$expires   = isset( $args['expires_at'] ) ? (string) $args['expires_at'] : $this->gmt_time( 30 * \MINUTE_IN_SECONDS );
		$provider  = isset( $args['provider_tx_id'] ) ? sanitize_text_field( (string) $args['provider_tx_id'] ) : 'pending_' . $order_id;
		$provider  = '' !== $provider ? $provider : 'pending_' . $order_id;
		$table     = $this->table();
		$coins     = absint( $args['coins_total'] ?? 0 );

		$ip   = isset( $args['user_ip'] ) ? substr( sanitize_text_field( (string) $args['user_ip'] ), 0, 45 ) : '';
		$ua   = isset( $args['user_agent'] ) ? substr( sanitize_text_field( (string) $args['user_agent'] ), 0, 255 ) : '';

		$inserted = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table}
					(user_id, package_id, gateway, order_id, provider_tx_id, amount, currency, coins_total, status, user_ip, user_agent, created_at, paid_at, expires_at)
				 VALUES (%d, %s, %s, %s, %s, %s, %s, %d, %s, %s, %s, %s, %s, %s)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				absint( $args['user_id'] ?? 0 ),
				sanitize_key( (string) ( $args['package_id'] ?? '' ) ),
				$this->get_id(),
				$order_id,
				$provider,
				$amount,
				strtoupper( sanitize_text_field( (string) ( $args['currency'] ?? '' ) ) ),
				$coins,
				self::STATUS_PENDING,
				$ip,
				$ua,
				$now,
				/*
				 * The column is NOT NULL, so the epoch the schema
				 * declares is used. The zero date is rejected by MySQL
				 * in strict mode, which made every purchase order
				 * fail to insert.
				 */
				'1970-01-01 00:00:00',
				$expires
			)
		);

		if ( false === $inserted ) {
			// Lost a race with a concurrent double-submit: re-read.
			$existing = $this->find_any_order( $order_id );

			if ( $existing ) {
				return array(
					'row'       => $existing,
					'created'   => false,
					'duplicate' => true,
				);
			}

			return new WP_Error( 'mcr_order_create_failed', __( 'The payment order could not be created.', 'madara-coin-rewards' ) );
		}

		$row = $this->find_order_by_id( (int) $wpdb->insert_id );

		if ( null === $row ) {
			return new WP_Error( 'mcr_order_create_failed', __( 'The payment order could not be created.', 'madara-coin-rewards' ) );
		}

		return array(
			'row'       => $row,
			'created'   => true,
			'duplicate' => false,
		);
	}

	/**
	 * Derives a stable internal order id from the idempotency key.
	 *
	 * Same key + same gateway + same buyer => same order id => the second
	 * submit reuses (never duplicates) the pending order.
	 *
	 * @param string $gateway_id      Gateway slug.
	 * @param string $idempotency_key Caller idempotency key.
	 * @param int    $user_id         Buyer id.
	 * @return string 64-character-safe id (<=64 chars).
	 */
	public static function derive_order_id( $gateway_id, $idempotency_key, $user_id ) {
		$base = 'mcr_' . sanitize_key( (string) $gateway_id )
			. '_' . substr( md5( $idempotency_key . '|' . absint( $user_id ) ), 0, 20 );

		return substr( $base, 0, 64 );
	}

	/**
	 * Assigns the real provider transaction id to a pending order row.
	 *
	 * @param int    $payment_id     Row id.
	 * @param string $provider_tx_id Provider reference.
	 * @return bool
	 */
	protected function update_provider_tx( $payment_id, $provider_tx_id ) {
		global $wpdb;

		$payment_id = absint( $payment_id );
		$provider_tx_id = sanitize_text_field( (string) $provider_tx_id );

		if ( ! $payment_id || '' === $provider_tx_id ) {
			return false;
		}

		$result = $wpdb->update(
			$this->table(),
			array( 'provider_tx_id' => $provider_tx_id ),
			array( 'id' => $payment_id ),
			array( '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Conditionally transitions an order between statuses.
	 *
	 * The old status is part of the WHERE clause, so two concurrent webhook
	 * deliveries race for exactly one transition. Additional columns from
	 * Gateway::UPDATABLE_COLUMNS may be written in the same statement.
	 *
	 * @param int    $payment_id Row id.
	 * @param string $from       Expected current status.
	 * @param string $to         New status.
	 * @param array  $extra      Optional column values.
	 * @return bool True when this call performed the transition.
	 */
	protected function transition_order( $payment_id, $from, $to, array $extra = array() ) {
		global $wpdb;

		$payment_id = absint( $payment_id );
		$from       = sanitize_key( (string) $from );
		$to         = sanitize_key( (string) $to );

		if ( ! $payment_id || '' === $from || '' === $to ) {
			return false;
		}

		$sets = array( 'status = %s' );
		$vals = array( $to );

		foreach ( self::UPDATABLE_COLUMNS as $column ) {
			if ( ! array_key_exists( $column, $extra ) ) {
				continue;
			}

			$sets[] = $column . ' = %s';
			$vals[] = (string) $extra[ $column ];
		}

		$vals[] = $payment_id;
		$vals[] = $from;

		$table = $this->table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only, all values prepared below.
		$sql = 'UPDATE ' . $table . ' SET ' . implode( ', ', $sets ) . ' WHERE id = %d AND status = %s';

		$affected = $wpdb->query( $wpdb->prepare( $sql, $vals ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return 1 === (int) $affected;
	}

	/**
	 * Marks a pending order failed. Never touches terminal rows.
	 *
	 * @param int    $payment_id Row id.
	 * @param string $reason     Short machine reason (logged, not stored: the
	 *                           table has no failure-reason column).
	 * @return bool
	 */
	protected function mark_order_failed( $payment_id, $reason = '' ) {
		$done = $this->transition_order( $payment_id, self::STATUS_PENDING, self::STATUS_FAILED );

		if ( '' !== (string) $reason ) {
			$this->log_security_event(
				'payment_marked_failed',
				array(
					'gateway' => $this->get_id(),
					'order'   => absint( $payment_id ),
					'reason'  => sanitize_text_field( (string) $reason ),
				)
			);
		}

		return $done;
	}

	/**
	 * Credits the coins of a paid order onto the buyer's ledger balance.
	 *
	 * Idempotent at both levels:
	 *  - the ledger key `payment:{gateway}:{provider_tx_id}` makes a repeated
	 *    credit a no-op that is reported as `duplicate => true`,
	 *  - the row transition pending→paid / paid→credited is conditional.
	 *
	 * Never call this until the payment amount and currency were verified
	 * against the stored snapshot. On success this fires the
	 * `mcr_payment_credited` action (order row, credit result).
	 *
	 * @param array  $order  The mcr_payments row (amount verified upstream).
	 * @param string $reason Ledger reason text.
	 * @return array|WP_Error array{credited:bool,duplicate:bool,transaction_id:int,balance_after:int}|WP_Error.
	 */
	protected function credit_order( array $order, $reason = '' ) {
		$payment_id = absint( $order['id'] ?? 0 );
		$coins      = absint( $order['coins_total'] ?? 0 );

		if ( ! $payment_id || self::STATUS_CREDITED === (string) ( $order['status'] ?? '' ) ) {
			return array(
				'credited'       => true,
				'duplicate'      => true,
				'transaction_id' => 0,
				'balance_after'  => 0,
			);
		}

		if ( self::STATUS_PAID !== (string) ( $order['status'] ?? '' ) && self::STATUS_PENDING !== (string) ( $order['status'] ?? '' ) ) {
			return new WP_Error( 'mcr_invalid_order_state', __( 'This payment cannot be credited in its current state.', 'madara-coin-rewards' ) );
		}

		if ( $coins <= 0 ) {
			return new WP_Error( 'mcr_invalid_coins', __( 'The coin amount of this payment is invalid.', 'madara-coin-rewards' ) );
		}

		if ( 'on' !== Settings::get( 'purchase.auto_credit', 'on' ) ) {
			return array(
				'credited'       => false,
				'duplicate'      => false,
				'transaction_id' => 0,
				'balance_after'  => 0,
			);
		}

		$tx = sanitize_text_field( (string) ( $order['provider_tx_id'] ?? '' ) );

		/*
		 * The idempotency key must be unique PER ORDER.
		 *
		 * `order_id` is a string (mcr_20260101_ab12cd), so it must NOT be
		 * passed through absint(): that would collapse every order that has
		 * no provider transaction id yet onto the same key, and the second
		 * such order would be rejected as a duplicate and never credited.
		 * The numeric primary key is used instead.
		 */
		$key = 'payment:' . $this->get_id() . ':' . ( '' !== $tx ? $tx : 'order_' . $payment_id );

		$reason = '' !== (string) $reason ? sanitize_text_field( (string) $reason ) : __( 'Coin package purchase', 'madara-coin-rewards' );

		try {
			$result = $this->ledger()->credit(
				absint( $order['user_id'] ?? 0 ),
				$coins,
				'purchase',
				$reason,
				array(
					'idempotency_key' => $key,
					'source'          => 'purchase',
					'ref_type'        => 'payment',
					'ref_id'          => $payment_id,
					// Marks the coins as bought rather than earned, so the
					// dashboard can report them separately.
					'purchased'       => true,
				)
			);
		} catch ( \Throwable $e ) {
			// A ledger fault must surface as a typed error, never a fatal:
			// the payment stays paid and crediting can be retried safely.
			$this->log_security_event(
				'ledger_credit_exception',
				array(
					'gateway' => $this->get_id(),
					'order'   => $payment_id,
				)
			);

			return new WP_Error( 'mcr_credit_failed', __( 'The coins could not be credited automatically. An administrator has been notified.', 'madara-coin-rewards' ) );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Move the row to credited. From paid first; pending→paid/credited
		// chains rely on the caller having established the paid state.
		if ( ! $this->transition_order( $payment_id, self::STATUS_PAID, self::STATUS_CREDITED ) ) {
			$this->transition_order( $payment_id, self::STATUS_PENDING, self::STATUS_PAID, array( 'paid_at' => $this->gmt_time() ) );
			$this->transition_order( $payment_id, self::STATUS_PAID, self::STATUS_CREDITED );
		}

		$fresh = $this->find_order_by_id( $payment_id );

		if ( $fresh ) {
			/**
			 * Fires after a payment has been credited.
			 *
			 * @param array $fresh  The mcr_payments row.
			 * @param array $result Ledger credit result.
			 */
			do_action( 'mcr_payment_credited', $fresh, $result );
		}

		return array(
			'credited'       => true,
			'duplicate'      => ! empty( $result['duplicate'] ),
			'transaction_id' => absint( $result['transaction_id'] ?? 0 ),
			'balance_after'  => absint( $result['balance_after'] ?? 0 ),
		);
	}

	/**
	 * Refunds a credited/paid order: reverses the ledger credit and marks
	 * the row refunded.
	 *
	 * The external refund already happened (the provider told us, or the
	 * admin confirmed), so the row always ends `refunded`; the coin clawback
	 * may fail when the user already spent the coins — that is logged, and
	 * documented as an admin follow-up, never silently re-credited.
	 *
	 * @param array  $order  The mcr_payments row (credited or paid).
	 * @param string $reason Refund reason (audit).
	 * @return array|WP_Error array{refunded:bool,reversed:bool,transaction_id:int}|WP_Error.
	 */
	protected function refund_order( array $order, $reason = '' ) {
		$payment_id = absint( $order['id'] ?? 0 );
		$coins      = absint( $order['coins_total'] ?? 0 );
		$tx         = sanitize_text_field( (string) ( $order['provider_tx_id'] ?? '' ) );

		if ( ! $payment_id ) {
			return new WP_Error( 'mcr_payment_unknown', __( 'This payment record was not found.', 'madara-coin-rewards' ) );
		}

		$reason = '' !== (string) $reason
			? sanitize_text_field( (string) $reason )
			: __( 'Payment refunded', 'madara-coin-rewards' );

		$reversed       = false;
		$reverse_result = null;

		if ( $coins > 0 && in_array( (string) ( $order['status'] ?? '' ), array( self::STATUS_CREDITED, self::STATUS_PAID ), true ) ) {
			try {
				$reverse_result = $this->ledger()->debit(
					absint( $order['user_id'] ?? 0 ),
					$coins,
					'refund',
					$reason,
					array(
						'idempotency_key' => 'refund:' . $this->get_id() . ':' . ( '' !== $tx ? $tx : 'order_' . $payment_id ),
						'source'          => 'refund',
						'ref_type'        => 'payment',
						'ref_id'          => $payment_id,
						/*
						 * A refund takes the coins back. It is NOT a
						 * spend, so it must not count towards
						 * lifetime_spent, and it must restore the
						 * lifetime_earned the purchase had added.
						 */
						'reversal'        => true,
					)
				);
			} catch ( \Throwable $e ) {
				$reverse_result = new WP_Error( 'mcr_refund_debit_failed', __( 'The coin reversal failed.', 'madara-coin-rewards' ) );
			}

			if ( is_wp_error( $reverse_result ) ) {
				// Coins already spent: the money refund is still final; the
				// clawback failure is logged for manual handling.
				$this->log_security_event(
					'payment_refund_clawback_failed',
					array(
						'gateway' => $this->get_id(),
						'order'   => $payment_id,
						'code'    => sanitize_key( $reverse_result->get_error_code() ),
					)
				);
			} else {
				$reversed = true;
			}
		}

		$done = false;

		foreach ( array( self::STATUS_CREDITED, self::STATUS_PAID, self::STATUS_PENDING ) as $from ) {
			if ( $this->transition_order( $payment_id, $from, self::STATUS_REFUNDED ) ) {
				$done = true;
			}
		}

		$fresh = $this->find_order_by_id( $payment_id );

		if ( $fresh ) {
			/**
			 * Fires after a payment has been refunded.
			 *
			 * @param array      $fresh    The mcr_payments row.
			 * @param array|null $reverse  Ledger debit result when attempted.
			 */
			do_action( 'mcr_payment_refunded', $fresh, $reverse_result );
		}

		return array(
			'refunded'       => true,
			'transitioned'   => $done,
			'reversed'       => $reversed,
			'transaction_id' => is_array( $reverse_result ) ? absint( $reverse_result['transaction_id'] ?? 0 ) : 0,
			'reason'         => $reason,
		);
	}

	/**
	 * Converts any numeric value to integer minor units (cents).
	 *
	 * The ONLY place floats touch this layer: the conversion happens once,
	 * through PackageCalculator, and everything downstream is int.
	 *
	 * @param mixed $value Decimal/major-unit value (e.g. "4.99").
	 * @return int Minor units.
	 */
	protected static function to_minor( $value ) {
		return (int) PackageCalculator::toMinorUnits( $value );
	}

	/**
	 * Converts integer minor units into the DECIMAL(12,2) value for storage.
	 *
	 * @param int $minor Minor units.
	 * @return float DECIMAL value (formatted with %.2F on insert).
	 */
	protected static function money_db( $minor ) {
		return PackageCalculator::fromMinorUnits( absint( $minor ) );
	}

	/**
	 * Formats minor units for display, e.g. 499 => "4.99 USD".
	 *
	 * Escape with esc_html() whenever printed to HTML.
	 *
	 * @param int    $minor    Minor units.
	 * @param string $currency Currency code.
	 * @return string
	 */
	public static function format_minor( $minor, $currency = '' ) {
		$value    = absint( $minor ) / 100;
		$currency = strtoupper( sanitize_text_field( (string) $currency ) );

		return number_format( $value, 2 ) . ( '' !== $currency ? ' ' . $currency : '' );
	}

	/**
	 * RFC 4122 v4 UUID (delegates to the shared helper).
	 *
	 * @return string
	 */
	protected function uuid() {
		return Ledger::uuid4();
	}

	/**
	 * Server-side security/fraud event sink.
	 *
	 * Written to the PHP error log and handed to the `mcr_security_event`
	 * action so a site owner can route events to their own logging.
	 * Response payloads to providers never include these details.
	 *
	 * @param string $event   Machine key (sanitized).
	 * @param array  $context Context data (sanitized keys, string values).
	 * @return void
	 */
	protected function log_security_event( $event, array $context = array() ) {
		$event = sanitize_key( (string) $event );

		$line = array( 'event' => $event );

		foreach ( $context as $key => $value ) {
			$key        = sanitize_key( (string) $key );
			$line[ $key ] = is_scalar( $value )
				? (string) ( is_bool( $value ) ? ( $value ? 'true' : 'false' ) : $value )
				: wp_json_encode( $value );
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[mcr-security] ' . wp_json_encode( $line ) );
		}

		/**
		 * Fires for every security-relevant event in the payment layer.
		 *
		 * @param string $event   Machine event key.
		 * @param array  $line    Sanitized context.
		 */
		do_action( 'mcr_security_event', $event, $line );
	}

	/**
	 * The client IP (best effort, for logs only — never authorization).
	 *
	 * @return string
	 */
	protected function request_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		return substr( $ip, 0, 45 );
	}
}
