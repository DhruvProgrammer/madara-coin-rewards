<?php
/**
 * The coin ledger.
 *
 * Every balance change in the plugin goes through this class. The ledger is
 * append-only: rows are never mutated except for the `balance_after`
 * snapshot written inside the same transaction that created them.
 *
 * @package MCR
 */

namespace MCR;

use MCR\Ledger\BalanceMath;
use MCR\Ledger\LedgerInterface;
use MCR\Support\Clock;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Atomic, idempotent, auditable coin ledger.
 */
class Ledger implements LedgerInterface {

	/**
	 * Singleton instance.
	 *
	 * @var Ledger|null
	 */
	private static $instance = null;

	/**
	 * Clock instance.
	 *
	 * @var Clock|null
	 */
	private $clock = null;

	/**
	 * Whether a transaction is currently open.
	 *
	 * @var bool
	 */
	private $in_transaction = false;

	/**
	 * Whether the storage engine supports real transactions.
	 *
	 * @var bool|null
	 */
	private $supports_transactions = null;

	/**
	 * Returns the shared instance.
	 *
	 * @return Ledger
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @param Clock|null $clock Optional clock override.
	 */
	public function __construct( ?Clock $clock = null ) {
		$this->clock = $clock;
	}

	/**
	 * Sets the clock used for timestamps.
	 *
	 * @param Clock $clock Clock instance.
	 * @return void
	 */
	public function set_clock( Clock $clock ) {
		$this->clock = $clock;
	}

	/**
	 * Returns the clock in use.
	 *
	 * @return Clock
	 */
	public function get_clock() {
		if ( null === $this->clock ) {
			$this->clock = new Support\WpClock();
		}

		return $this->clock;
	}

	/**
	 * Credits coins to a user.
	 *
	 * Idempotent: supplying the same `idempotency_key` twice returns the
	 * original transaction with `duplicate => true` and does not move money.
	 *
	 * @param int    $user_id User receiving the coins.
	 * @param int    $amount  Positive number of coins.
	 * @param string $type    Transaction type (see self::TRANSACTION_TYPES).
	 * @param string $reason  Human readable, translatable reason.
	 * @param array  $ctx     Optional context: source, ref_type, ref_id,
	 *                         idempotency_key, ip, user_agent.
	 * @return array|WP_Error ['transaction_id','balance_after','duplicate','amount'].
	 */
	public function credit( $user_id, $amount, $type, $reason = '', $ctx = array() ) {
		$user_id = absint( $user_id );

		if ( ! $user_id ) {
			return new WP_Error( 'mcr_invalid_user', __( 'Invalid user.', 'madara-coin-rewards' ) );
		}

		$amount = BalanceMath::validateCredit( $amount );

		if ( false === $amount ) {
			return new WP_Error( 'mcr_invalid_amount', __( 'Invalid coin amount.', 'madara-coin-rewards' ) );
		}

		$type = self::sanitize_type( $type );

		if ( '' === $type ) {
			return new WP_Error( 'mcr_invalid_type', __( 'Invalid transaction type.', 'madara-coin-rewards' ) );
		}

		/**
		 * Filters the amount of a credit before it is applied.
		 *
		 * @param int    $amount  Coin amount.
		 * @param string $type    Transaction type.
		 * @param int    $user_id User id.
		 * @param array  $ctx     Context.
		 */
		$amount = (int) apply_filters( 'mcr_credit_amount', $amount, $type, $user_id, $ctx );

		/**
		 * Filters whether a credit may proceed.
		 *
		 * Returning false blocks the credit entirely.
		 *
		 * @param bool   $allowed Whether the credit is allowed.
		 * @param int    $user_id User id.
		 * @param int    $amount  Coin amount.
		 * @param string $type    Transaction type.
		 * @param array  $ctx     Context.
		 */
		if ( ! apply_filters( 'mcr_before_credit', true, $user_id, $amount, $type, $ctx ) ) {
			return new WP_Error( 'mcr_credit_blocked', __( 'This reward was blocked.', 'madara-coin-rewards' ) );
		}

		$key = $this->idempotency_key( $ctx, $type, $user_id );

		return $this->apply( $user_id, $amount, $type, $reason, $ctx, $key, true );
	}

	/**
	 * Debits coins from a user.
	 *
	 * The balance guard is enforced in SQL (`WHERE balance >= amount`), never
	 * in PHP, so a concurrent spender cannot overdraw the balance.
	 *
	 * @param int    $user_id User losing the coins.
	 * @param int    $amount  Positive number of coins to remove.
	 * @param string $type    Transaction type.
	 * @param string $reason  Human readable reason.
	 * @param array  $ctx     Optional context.
	 * @return array|WP_Error ['transaction_id','balance_after','duplicate','amount'].
	 */
	public function debit( $user_id, $amount, $type, $reason = '', $ctx = array() ) {
		$user_id = absint( $user_id );

		if ( ! $user_id ) {
			return new WP_Error( 'mcr_invalid_user', __( 'Invalid user.', 'madara-coin-rewards' ) );
		}

		$amount = BalanceMath::validateDebit( $amount );

		if ( false === $amount ) {
			return new WP_Error( 'mcr_invalid_amount', __( 'Invalid coin amount.', 'madara-coin-rewards' ) );
		}

		$type = self::sanitize_type( $type );

		if ( '' === $type ) {
			return new WP_Error( 'mcr_invalid_type', __( 'Invalid transaction type.', 'madara-coin-rewards' ) );
		}

		/**
		 * Filters whether a debit may proceed.
		 *
		 * @param bool   $allowed Whether the debit is allowed.
		 * @param int    $user_id User id.
		 * @param int    $amount  Coin amount.
		 * @param string $type    Transaction type.
		 * @param array  $ctx     Context.
		 */
		if ( ! apply_filters( 'mcr_before_debit', true, $user_id, $amount, $type, $ctx ) ) {
			return new WP_Error( 'mcr_debit_blocked', __( 'This spend was blocked.', 'madara-coin-rewards' ) );
		}

		$key = $this->idempotency_key( $ctx, $type, $user_id );

		return $this->apply( $user_id, -$amount, $type, $reason, $ctx, $key, false );
	}

	/**
	 * Applies a signed amount to a balance and records the ledger row.
	 *
	 * @param int    $user_id    User id.
	 * @param int    $signed     Signed amount (positive = credit).
	 * @param string $type       Transaction type.
	 * @param string $reason     Reason.
	 * @param array  $ctx        Context.
	 * @param string $key        Idempotency key.
	 * @param bool   $is_credit  Whether this is a credit.
	 * @return array|WP_Error
	 */
	private function apply( $user_id, $signed, $type, $reason, $ctx, $key, $is_credit ) {
		global $wpdb;

		$balances = $wpdb->prefix . 'mcr_balances';
		$ledger   = $wpdb->prefix . 'mcr_transactions';
		$now      = $this->get_clock()->mysql_gmt();

		$source   = isset( $ctx['source'] ) ? sanitize_key( $ctx['source'] ) : 'web';
		$ref_type = isset( $ctx['ref_type'] ) ? sanitize_key( $ctx['ref_type'] ) : '';
		$ref_id   = isset( $ctx['ref_id'] ) ? absint( $ctx['ref_id'] ) : 0;
		$ip       = $this->request_ip( $ctx );
		$agent    = $this->request_agent( $ctx );

		$this->begin();

		// The UNIQUE idempotency_key index is the idempotency gate: a repeated
		// grant fails the insert and is reported as a duplicate no-op.
		$inserted = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$ledger}
					(user_id, txn_uid, type, amount, balance_after, reason, source, ref_type, ref_id, idempotency_key, ip, user_agent, created_at)
				 VALUES (%d, %s, %s, %d, 0, %s, %s, %s, %d, %s, %s, %s, %s)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id,
				self::uuid4(),
				$type,
				$signed,
				sanitize_text_field( $reason ),
				$source,
				$ref_type,
				$ref_id,
				$key,
				$ip,
				$agent,
				$now
			)
		);

		if ( false === $inserted ) {
			if ( self::is_duplicate_key_error() ) {
				$this->rollback();

				$existing = $this->get_by_idempotency_key( $key );

				if ( $existing ) {
					return array(
						'transaction_id' => (int) $existing->id,
						'balance_after'  => (int) $existing->balance_after,
						'duplicate'      => true,
						'amount'         => (int) $existing->amount,
					);
				}
			}

			$this->rollback();

			return new WP_Error( 'mcr_ledger_insert_failed', __( 'Could not record the transaction.', 'madara-coin-rewards' ) );
		}

		$txn_id = (int) $wpdb->insert_id;

		if ( $is_credit ) {
			/*
			 * Purchased coins are tracked separately from earned coins so
			 * the dashboard can show what was bought rather than won.
			 */
			$purchased = ! empty( $ctx['purchased'] ) ? $signed : 0;

			$updated = $wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$balances} (user_id, balance, lifetime_earned, purchased_total, updated_at)
					 VALUES (%d, %d, %d, %d, %s)
					 ON DUPLICATE KEY UPDATE
						balance         = balance + %d,
						lifetime_earned = lifetime_earned + %d,
						purchased_total = purchased_total + %d,
						updated_at      = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$user_id,
					$signed,
					$signed,
					$purchased,
					$now,
					$signed,
					$signed,
					$purchased,
					$now
				)
			);
		} else {
			$amount_abs = abs( $signed );

			/*
			 * A reversal undoes an earlier credit, so it must not count as
			 * the user SPENDING coins, and it gives the earned total back.
			 */
			$is_reversal = ! empty( $ctx['reversal'] );

			if ( $is_reversal ) {
				$updated = $wpdb->query(
					$wpdb->prepare(
						"UPDATE {$balances}
							SET balance         = balance - %d,
								lifetime_earned = lifetime_earned - %d,
								updated_at       = %s
						 WHERE user_id = %d
						   AND balance >= %d
						   AND lifetime_earned >= %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$amount_abs,
						$amount_abs,
						$now,
						$user_id,
						$amount_abs,
						$amount_abs
					)
				);
			} else {
				// The balance guard lives in SQL. If the user cannot afford it,
				// zero rows match and nothing is written.
				$updated = $wpdb->query(
					$wpdb->prepare(
						"UPDATE {$balances}
							SET balance         = balance - %d,
								lifetime_spent   = lifetime_spent + %d,
								updated_at       = %s
						 WHERE user_id = %d
						   AND balance >= %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$amount_abs,
						$amount_abs,
						$now,
						$user_id,
						$amount_abs
					)
				);
			}
		}

		if ( false === $updated ) {
			$this->rollback();

			return new WP_Error( 'mcr_balance_update_failed', __( 'Could not update the balance.', 'madara-coin-rewards' ) );
		}

		if ( ! $is_credit && 0 === (int) $updated ) {
			// No row matched: either the user has no balance row yet, or the
			// balance is insufficient. Both mean the debit cannot proceed.
			$this->rollback();

			return new WP_Error( 'mcr_insufficient_funds', __( 'You do not have enough coins for this.', 'madara-coin-rewards' ) );
		}

		// Our own write holds the row lock until COMMIT, and InnoDB guarantees
		// we read our own uncommitted value, so this snapshot is exact.
		$balance_after = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT balance FROM {$balances} WHERE user_id = %d", $user_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$ledger} SET balance_after = %d WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$balance_after,
				$txn_id
			)
		);

		if ( ! $this->commit() ) {
			return new WP_Error( 'mcr_commit_failed', __( 'Could not commit the transaction.', 'madara-coin-rewards' ) );
		}

		/**
		 * Fires after coins have been credited and committed.
		 *
		 * @param int    $user_id      User id.
		 * @param int    $amount       Amount credited.
		 * @param string $type         Transaction type.
		 * @param int    $balance_after Balance after the change.
		 * @param array  $ctx          Context.
		 */
		do_action( 'mcr_after_credit', $user_id, $is_credit ? $signed : 0, $type, $balance_after, $ctx );

		if ( ! $is_credit ) {
			do_action( 'mcr_after_debit', $user_id, abs( $signed ), $type, $balance_after, $ctx );
		}

		return array(
			'transaction_id' => $txn_id,
			'balance_after'  => $balance_after,
			'duplicate'      => false,
			'amount'         => $signed,
		);
	}

	/**
	 * Returns a user's current balance.
	 *
	 * @param int $user_id User id.
	 * @return int
	 */
	public function get_balance( $user_id ) {
		global $wpdb;

		$user_id = absint( $user_id );

		if ( ! $user_id ) {
			return 0;
		}

		$balance = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT balance FROM ' . $wpdb->prefix . 'mcr_balances WHERE user_id = %d', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id
			)
		);

		return null === $balance ? 0 : (int) $balance;
	}

	/**
	 * Returns a user's balance statistics.
	 *
	 * @param int $user_id User id.
	 * @return array
	 */
	public function get_stats( $user_id ) {
		global $wpdb;

		$user_id = absint( $user_id );

		$defaults = array(
			'balance'         => 0,
			'lifetime_earned' => 0,
			'lifetime_spent'  => 0,
			'purchased_total' => 0,
		);

		if ( ! $user_id ) {
			return $defaults;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT balance, lifetime_earned, lifetime_spent, purchased_total FROM ' . $wpdb->prefix . 'mcr_balances WHERE user_id = %d', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id
			),
			\ARRAY_A
		);

		if ( ! $row ) {
			return $defaults;
		}

		return array_merge( $defaults, array_map( 'intval', $row ) );
	}

	/**
	 * Finds a ledger row by its idempotency key.
	 *
	 * @param string $key Idempotency key.
	 * @return object|null
	 */
	public function get_by_idempotency_key( $key ) {
		global $wpdb;

		if ( '' === (string) $key ) {
			return null;
		}

		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . $wpdb->prefix . 'mcr_transactions WHERE idempotency_key = %s LIMIT 1', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$key
			)
		);
	}

	/**
	 * Lists a user's transactions, newest first.
	 *
	 * @param int $user_id User id.
	 * @param int $limit   Rows per page.
	 * @param int $offset  Offset.
	 * @return array
	 */
	public function list_for_user( $user_id, $limit = 20, $offset = 0 ) {
		global $wpdb;

		$user_id = absint( $user_id );
		$limit   = max( 1, min( 100, absint( $limit ) ) );
		$offset  = max( 0, absint( $offset ) );

		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, txn_uid, type, amount, balance_after, reason, source, ref_type, ref_id, created_at
				 FROM ' . $wpdb->prefix . 'mcr_transactions
				 WHERE user_id = %d
				 ORDER BY id DESC
				 LIMIT %d OFFSET %d', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id,
				$limit,
				$offset
			)
		);
	}

	/**
	 * Counts a user's transactions.
	 *
	 * @param int $user_id User id.
	 * @return int
	 */
	public function count_for_user( $user_id ) {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'mcr_transactions WHERE user_id = %d', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				absint( $user_id )
			)
		);
	}

	/**
	 * Recomputes a user's balance from the ledger.
	 *
	 * Used by the admin repair tool to detect and fix divergence.
	 *
	 * @param int $user_id User id.
	 * @return array|WP_Error ['previous','recomputed','fixed'].
	 */
	public function reconcile_user( $user_id ) {
		global $wpdb;

		$user_id = absint( $user_id );

		if ( ! $user_id ) {
			return new WP_Error( 'mcr_invalid_user', __( 'Invalid user.', 'madara-coin-rewards' ) );
		}

		$sum = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COALESCE(SUM(amount), 0) FROM ' . $wpdb->prefix . 'mcr_transactions WHERE user_id = %d', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id
			)
		);

		$previous = $this->get_balance( $user_id );
		$fixed    = false;

		if ( $previous !== $sum ) {
			$wpdb->query(
				$wpdb->prepare(
					'INSERT INTO ' . $wpdb->prefix . 'mcr_balances (user_id, balance, updated_at) VALUES (%d, %d, %s)
					 ON DUPLICATE KEY UPDATE balance = %d, updated_at = %s', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$user_id,
					$sum,
					$this->get_clock()->mysql_gmt(),
					$sum,
					$this->get_clock()->mysql_gmt()
				)
			);

			$fixed = true;
		}

		return array(
			'previous'   => $previous,
			'recomputed' => $sum,
			'fixed'      => $fixed,
		);
	}

	/**
	 * Builds an idempotency key.
	 *
	 * @param array  $ctx     Context.
	 * @param string $type    Transaction type.
	 * @param int    $user_id User id.
	 * @return string
	 */
	private function idempotency_key( $ctx, $type, $user_id ) {
		if ( ! empty( $ctx['idempotency_key'] ) ) {
			return substr( sanitize_text_field( (string) $ctx['idempotency_key'] ), 0, 191 );
		}

		$ref = '';

		if ( ! empty( $ctx['ref_type'] ) && ! empty( $ctx['ref_id'] ) ) {
			$ref = $ctx['ref_type'] . ':' . absint( $ctx['ref_id'] );
		}

		if ( '' === $ref ) {
			// Without a reference we cannot derive a stable key, so we
			// generate a random one: the caller did not supply idempotency
			// semantics and the grant is inherently one-shot.
			return 'auto:' . $user_id . ':' . $type . ':' . self::uuid4();
		}

		return substr( $user_id . ':' . $type . ':' . $ref, 0, 191 );
	}

	/**
	 * Sanitizes a transaction type against the allowlist.
	 *
	 * @param string $type Raw type.
	 * @return string Empty string when invalid.
	 */
	public static function sanitize_type( $type ) {
		$type = sanitize_key( (string) $type );

		return in_array( $type, self::transaction_types(), true ) ? $type : '';
	}

	/**
	 * The allowlist of transaction types.
	 *
	 * @return array
	 */
	public static function transaction_types() {
		return apply_filters(
			'mcr_transaction_types',
			array(
				'checkin',
				'checkin_bonus',
				'ad_reward',
				'purchase',
				'purchase_bonus',
				'admin_adjust',
				'spend',
				'reversal',
				'refund',
				'referral',
			)
		);
	}

	/**
	 * Generates a RFC 4122 version 4 UUID.
	 *
	 * @return string
	 */
	public static function uuid4() {
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			return wp_generate_uuid4();
		}

		$data    = random_bytes( 16 );
		$data[6] = chr( ord( $data[6] ) & 0x0f | 0x40 );
		$data[8] = chr( ord( $data[8] ) & 0x3f | 0x80 );

		return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $data ), 4 ) );
	}

	/**
	 * Determines whether the last database error was a duplicate key.
	 *
	 * @return bool
	 */
	public static function is_duplicate_key_error() {
		global $wpdb;

		$error = (string) $wpdb->last_error;

		if ( '' === $error ) {
			return false;
		}

		return false !== stripos( $error, 'duplicate entry' )
			|| false !== stripos( $error, '1062' )
			|| false !== stripos( $error, 'unique constraint' );
	}

	/**
	 * Returns the client IP, honouring the privacy settings.
	 *
	 * @param array $ctx Context which may carry an explicit ip.
	 * @return string
	 */
	private function request_ip( $ctx ) {
		if ( ! empty( $ctx['ip'] ) ) {
			return substr( sanitize_text_field( (string) $ctx['ip'] ), 0, 45 );
		}

		if ( ! Settings::get( 'security.log_ip', 'on' ) ) {
			return '';
		}

		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		return substr( $ip, 0, 45 );
	}

	/**
	 * Returns the user agent, honouring the privacy settings.
	 *
	 * @param array $ctx Context which may carry an explicit user agent.
	 * @return string
	 */
	private function request_agent( $ctx ) {
		if ( ! empty( $ctx['user_agent'] ) ) {
			return substr( sanitize_text_field( (string) $ctx['user_agent'] ), 0, 255 );
		}

		if ( ! Settings::get( 'security.log_user_agent', 'off' ) ) {
			return '';
		}

		$agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		return substr( $agent, 0, 255 );
	}

	/**
	 * Begins a database transaction when the engine supports it.
	 *
	 * @return void
	 */
	private function begin() {
		global $wpdb;

		if ( $this->in_transaction ) {
			return;
		}

		if ( $this->supports_transactions() ) {
			$wpdb->query( 'START TRANSACTION' );
			$this->in_transaction = true;
		}
	}

	/**
	 * Commits the open transaction.
	 *
	 * @return bool
	 */
	private function commit() {
		global $wpdb;

		if ( ! $this->in_transaction ) {
			return true;
		}

		$this->in_transaction = false;

		return false !== $wpdb->query( 'COMMIT' );
	}

	/**
	 * Rolls back the open transaction.
	 *
	 * @return void
	 */
	private function rollback() {
		global $wpdb;

		if ( ! $this->in_transaction ) {
			return;
		}

		$this->in_transaction = false;

		$wpdb->query( 'ROLLBACK' );
	}

	/**
	 * Whether the balances table supports real transactions.
	 *
	 * @return bool
	 */
	private function supports_transactions() {
		global $wpdb;

		if ( null !== $this->supports_transactions ) {
			return $this->supports_transactions;
		}

		$table  = $wpdb->prefix . 'mcr_balances';
		$engine = Installer::table_engine( $table );

		$this->supports_transactions = ( 'INNODB' === $engine );

		return $this->supports_transactions;
	}
}
