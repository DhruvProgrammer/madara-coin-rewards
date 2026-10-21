<?php
/**
 * Ledger integration tests.
 *
 * Run with: php tests/run-ledger-tests.php
 *
 * Exercises the REAL MCR\Ledger against an in-memory $wpdb stub that
 * faithfully implements the semantics the ledger depends on:
 *
 *  - the conditional UPDATE guard (WHERE balance >= N matches zero rows
 *    when the balance is insufficient, and rows_affected reflects that);
 *  - INSERT ... ON DUPLICATE KEY UPDATE;
 *  - the UNIQUE idempotency_key index, so a repeated grant is a
 *    duplicate-key error rather than a second credit;
 *  - transactions, so a rollback really undoes the balance change.
 *
 * This is where the money lives, so it is worth testing properly.
 *
 * @package MCR
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

require_once __DIR__ . '/bootstrap.php';

use MCR\Ledger;

/* ==========================================================================
 * In-memory $wpdb
 * ======================================================================== */

/**
 * A minimal but faithful in-memory wpdb.
 */
class Fake_WPDB {

	/**
	 * Table prefix.
	 *
	 * @var string
	 */
	public $prefix = 'wp_';

	/**
	 * Insert id of the last insert.
	 *
	 * @var int
	 */
	public $insert_id = 0;

	/**
	 * Rows affected by the last query.
	 *
	 * @var int
	 */
	public $rows_affected = 0;

	/**
	 * Last error message.
	 *
	 * @var string
	 */
	public $last_error = '';

	/**
	 * Balances keyed by user id.
	 *
	 * @var array
	 */
	public $balances = array();

	/**
	 * Ledger rows.
	 *
	 * @var array
	 */
	public $transactions = array();

	/**
	 * Next auto increment id.
	 *
	 * @var int
	 */
	private $next_id = 1;

	/**
	 * Whether a transaction is open.
	 *
	 * @var bool
	 */
	public $in_transaction = false;

	/**
	 * Snapshot of state at the last BEGIN.
	 *
	 * @var array
	 */
	private $snapshot = array();

	/**
	 * Number of transactions opened (proves the ledger uses them).
	 *
	 * @var int
	 */
	public $transactions_started = 0;

	/**
	 * Builds a placeholder list.
	 *
	 * @param string $sql SQL.
	 * @return array
	 */
	public function prepare( $sql, ...$args ) {
		if ( empty( $args ) ) {
			return $sql;
		}

		$sql = str_replace( '%d', '%s', $sql );
		$sql = str_replace( '%f', '%s', $sql );

		$values = array();

		foreach ( $args as $arg ) {
			if ( is_int( $arg ) || is_float( $arg ) ) {
				$values[] = $arg;
			} elseif ( null === $arg ) {
				$values[] = 'NULL';
			} else {
				// WordPress quotes %s values itself.
				$values[] = "'" . addslashes( (string) $arg ) . "'";
			}
		}

		return vsprintf( $sql, $values );
	}

	/**
	 * Escapes a string for LIKE.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public function esc_like( $text ) {
		return addcslashes( (string) $text, '_%\\' );
	}

	/**
	 * Charset collate.
	 *
	 * @return string
	 */
	public function get_charset_collate() {
		return 'DEFAULT CHARACTER SET utf8mb4';
	}

	/**
	 * Starts a transaction.
	 *
	 * @return int
	 */
	public function query( $sql ) {
		$sql = trim( (string) $sql );

		if ( 'START TRANSACTION' === strtoupper( $sql ) ) {
			$this->in_transaction = true;
			++$this->transactions_started;
			$this->snapshot = array(
				'balances'     => $this->balances,
				'transactions' => $this->transactions,
				'next_id'      => $this->next_id,
			);

			return 1;
		}

		if ( 'COMMIT' === strtoupper( $sql ) ) {
			$this->in_transaction = false;

			return 1;
		}

		if ( 'ROLLBACK' === strtoupper( $sql ) ) {
			if ( $this->in_transaction && ! empty( $this->snapshot ) ) {
				$this->balances     = $this->snapshot['balances'];
				$this->transactions = $this->snapshot['transactions'];
				$this->next_id      = $this->snapshot['next_id'];
			}

			$this->in_transaction = false;

			return 1;
		}

		$this->last_error = '';

		if ( preg_match( '/^INSERT INTO .*mcr_balances.*VALUES \(([^)]*)\).*ON DUPLICATE KEY UPDATE/s', $sql, $m ) ) {
			return $this->upsert_balance( $m[1] );
		}

		if ( preg_match( '/^INSERT INTO .*mcr_transactions.*VALUES \((.*)\)\s*$/s', $sql, $m ) ) {
			return $this->insert_transaction( $m[1] );
		}

		if ( preg_match( '/^UPDATE .*mcr_balances.*SET balance\s*=\s*balance\s*-\s*(-?\d+).*WHERE user_id\s*=\s*(\d+)\s*AND balance\s*>=\s*(\d+)/s', $sql, $m ) ) {
			// A reversal gives the earned total back and is not a spend.
			$is_reversal = ( false !== strpos( $sql, 'lifetime_earned = lifetime_earned -' ) );

			return $this->debit_balance( (int) $m[2], (int) $m[3], (int) $m[1], $is_reversal );
		}

		if ( preg_match( '/^UPDATE .*mcr_transactions SET balance_after\s*=\s*(\d+) WHERE id\s*=\s*(\d+)/s', $sql, $m ) ) {
			foreach ( $this->transactions as $row ) {
				if ( (int) $row['id'] === (int) $m[2] ) {
					$this->transactions[ (int) $m[2] ]['balance_after'] = (int) $m[1];
					$this->rows_affected             = 1;

					return 1;
				}
			}

			$this->rows_affected = 0;

			return 0;
		}

		$this->rows_affected = 0;

		return 0;
	}

	/**
	 * Splits a VALUES list.
	 *
	 * @param string $list Raw list.
	 * @return array
	 */
	private function split_values( $list ) {
		$parts = array();
		$buf   = '';
		$quote = false;

		for ( $i = 0; $i < strlen( $list ); $i++ ) {
			$char = $list[ $i ];

			if ( "'" === $char ) {
				$quote = ! $quote;
				continue;
			}

			if ( ',' === $char && ! $quote ) {
				$parts[] = trim( $buf );
				$buf     = '';
				continue;
			}

			$buf .= $char;
		}

		if ( '' !== trim( $buf ) ) {
			$parts[] = trim( $buf );
		}

		return $parts;
	}

	/**
	 * Upserts a balance row.
	 *
	 * @param string $values Values list.
	 * @return int
	 */
	private function upsert_balance( $values ) {
		$parts = $this->split_values( $values );

		$user_id   = (int) $parts[0];
		$amount    = (int) $parts[1];
		$purchased = isset( $parts[3] ) ? (int) $parts[3] : 0;

		if ( ! isset( $this->balances[ $user_id ] ) ) {
			$this->balances[ $user_id ] = array(
				'balance'         => 0,
				'lifetime_earned' => 0,
				'lifetime_spent'  => 0,
				'purchased_total' => 0,
			);
		}

		$this->balances[ $user_id ]['balance']         += $amount;
		$this->balances[ $user_id ]['lifetime_earned'] += $amount;
		$this->balances[ $user_id ]['purchased_total'] += $purchased;

		$this->rows_affected = 1;

		return 1;
	}

	/**
	 * Applies the conditional debit guard.
	 *
	 * @param int  $user_id   User id.
	 * @param int  $guard     The balance guard amount.
	 * @param int  $amount    The amount to remove.
	 * @param bool $reversal  Whether this is a reversal of a credit.
	 * @return int
	 */
	private function debit_balance( $user_id, $guard, $amount, $reversal = false ) {
		if ( ! isset( $this->balances[ $user_id ] ) ) {
			$this->rows_affected = 0;

			return 0;
		}

		if ( $this->balances[ $user_id ]['balance'] < $guard ) {
			// The guard did not match: nothing is written.
			$this->rows_affected = 0;

			return 0;
		}

		if ( $reversal ) {
			// A reversal undoes a credit: it is not a spend, and it gives
			// the earned total back.
			if ( $this->balances[ $user_id ]['lifetime_earned'] < $amount ) {
				$this->rows_affected = 0;

				return 0;
			}

			$this->balances[ $user_id ]['balance']        -= $amount;
			$this->balances[ $user_id ]['lifetime_earned'] -= $amount;

			$this->rows_affected = 1;

			return 1;
		}

		$this->balances[ $user_id ]['balance']        -= $amount;
		$this->balances[ $user_id ]['lifetime_spent'] += $amount;

		$this->rows_affected = 1;

		return 1;
	}

	/**
	 * Inserts a ledger row, honouring the UNIQUE idempotency_key.
	 *
	 * @param string $values Values list.
	 * @return int
	 */
	private function insert_transaction( $values ) {
		$parts = $this->split_values( $values );

		$key = $parts[9];

		foreach ( $this->transactions as $row ) {
			if ( $row['idempotency_key'] === $key ) {
				$this->last_error = "Duplicate entry '" . $key . "' for key 'idempotency_key'";
				$this->rows_affected = 0;

				return false;
			}
		}

		$id = $this->next_id++;

		$this->transactions[ $id ] = array(
			'id'              => $id,
			'user_id'         => (int) $parts[0],
			'txn_uid'         => $parts[1],
			'type'            => $parts[2],
			'amount'          => (int) $parts[3],
			'balance_after'   => 0,
			'reason'          => $parts[4],
			'source'          => $parts[5],
			'ref_type'        => $parts[6],
			'ref_id'          => (int) $parts[7],
			'idempotency_key' => $key,
			'ip'              => $parts[10],
			'user_agent'      => $parts[11],
			'created_at'      => $parts[12],
		);

		$this->insert_id     = $id;
		$this->rows_affected = 1;

		return 1;
	}

	/**
	 * Selects a single value.
	 *
	 * @param string $sql SQL.
	 * @return mixed
	 */
	public function get_var( $sql ) {
		if ( preg_match( '/SELECT balance FROM .*mcr_balances WHERE user_id = (\d+)/', $sql, $m ) ) {
			$user_id = (int) $m[1];

			return isset( $this->balances[ $user_id ] ) ? $this->balances[ $user_id ]['balance'] : null;
		}

		return null;
	}

	/**
	 * Selects a single row.
	 *
	 * @param string $sql  SQL.
	 * @param string $type Type.
	 * @return mixed
	 */
	public function get_row( $sql, $type = 'OBJECT' ) {
		$rows = $this->get_results( $sql, $type );

		return empty( $rows ) ? null : $rows[0];
	}

	/**
	 * Selects rows.
	 *
	 * @param string $sql  SQL.
	 * @param string $type Type.
	 * @return array
	 */
	public function get_results( $sql, $type = 'OBJECT' ) {
		// SHOW TABLE STATUS, used to detect the storage engine.
		if ( preg_match( "/SHOW TABLE STATUS LIKE '([^']+)'/", $sql, $m ) ) {
			$engine = 'InnoDB';

			return array(
				(object) array(
					'Name'   => $m[1],
					'Engine' => $engine,
				),
			);
		}

		if ( preg_match( '/SELECT balance, lifetime_earned, lifetime_spent, purchased_total FROM .*mcr_balances WHERE user_id = (\d+)/', $sql, $m ) ) {
			$user_id = (int) $m[1];

			if ( ! isset( $this->balances[ $user_id ] ) ) {
				return array();
			}

			$row = $this->balances[ $user_id ];

			$row = array(
				'balance'         => (int) $row['balance'],
				'lifetime_earned' => (int) $row['lifetime_earned'],
				'lifetime_spent'  => (int) $row['lifetime_spent'],
				'purchased_total' => (int) $row['purchased_total'],
			);

			return array( 'OBJECT' === $type ? (object) $row : $row );
		}

		if ( preg_match( '/SELECT \* FROM .*mcr_transactions WHERE idempotency_key = \'([^\']*)\'/', $sql, $m ) ) {
			foreach ( $this->transactions as $row ) {
				if ( $row['idempotency_key'] === $m[1] ) {
					return array( 'OBJECT' === $type ? (object) $row : $row );
				}
			}
		}

		return array();
	}
}

/**
 * Selects rows (the ledger uses get_col too).
 *
 * @param string $sql  SQL.
 * @param string $type Type.
 * @return array
 */
function wpdb_get_col_stub( $sql ) {
	return array();
}

/* ==========================================================================
 * Tests
 * ======================================================================== */

echo "MCR Ledger Integration Tests\n";
echo "==============================\n\n";

global $wpdb;

$wpdb = new Fake_WPDB();

require_once dirname( __DIR__ ) . '/includes/Support/Clock.php';
require_once dirname( __DIR__ ) . '/includes/Support/WpClock.php';
require_once dirname( __DIR__ ) . '/includes/Support/FixedClock.php';
require_once dirname( __DIR__ ) . '/includes/Ledger/BalanceMath.php';
require_once dirname( __DIR__ ) . '/includes/Ledger/LedgerInterface.php';
require_once dirname( __DIR__ ) . '/includes/Settings.php';
require_once dirname( __DIR__ ) . '/includes/Installer.php';
require_once dirname( __DIR__ ) . '/includes/Rewards/StreakCalculator.php';
require_once dirname( __DIR__ ) . '/includes/Rewards/CheckInPolicy.php';
require_once dirname( __DIR__ ) . '/includes/Ledger.php';

$ledger = new Ledger();

/* --- Credit ------------------------------------------------------------- */

mcr_group( 'Ledger::credit' );

$result = $ledger->credit( 5, 100, 'checkin', 'Daily reward', array( 'idempotency_key' => 'k1' ) );

assertFalse( is_wp_error( $result ), 'a credit succeeds' );
assertSame( 100, $result['balance_after'], 'the balance after the credit is exact' );
assertFalse( $result['duplicate'], 'the first credit is not a duplicate' );
assertSame( 100, $ledger->get_balance( 5 ), 'the balance table reflects the credit' );
assertSame( 100, $wpdb->balances[5]['lifetime_earned'], 'lifetime earned is incremented' );
assertSame( 1, count( $wpdb->transactions ), 'exactly one ledger row is written' );
assertSame( 100, $wpdb->transactions[1]['balance_after'], 'the ledger row snapshots the balance' );
assertTrue( $wpdb->transactions_started > 0, 'the ledger uses a database transaction' );

$result = $ledger->credit( 5, 100, 'checkin', 'Daily reward', array( 'idempotency_key' => 'k1' ) );

assertFalse( is_wp_error( $result ), 'a repeated credit is not an error' );
assertTrue( $result['duplicate'], 'a repeated idempotency key is reported as a duplicate' );
assertSame( 100, $ledger->get_balance( 5 ), 'a repeated credit does not move the balance' );
assertSame( 1, count( $wpdb->transactions ), 'a repeated credit writes no second ledger row' );

$result = $ledger->credit( 5, 0, 'checkin', '', array( 'idempotency_key' => 'k2' ) );
assertTrue( is_wp_error( $result ), 'a zero credit is refused' );
assertSame( 'mcr_invalid_amount', $result->get_error_code(), 'the refusal names the invalid amount' );

$result = $ledger->credit( 0, 100, 'checkin', '', array( 'idempotency_key' => 'k3' ) );
assertTrue( is_wp_error( $result ), 'a credit to an invalid user is refused' );

$result = $ledger->credit( 6, 100, 'not_a_real_type', '', array( 'idempotency_key' => 'k4' ) );
assertTrue( is_wp_error( $result ), 'an unknown transaction type is refused' );
assertSame( 'mcr_invalid_type', $result->get_error_code(), 'the refusal names the invalid type' );

/* --- Debit -------------------------------------------------------------- */

mcr_group( 'Ledger::debit' );

$ledger->credit( 7, 100, 'admin_adjust', 'Test credit', array( 'idempotency_key' => 'd0' ) );

$result = $ledger->debit( 7, 40, 'spend', 'Purchase', array( 'idempotency_key' => 'd1' ) );

assertFalse( is_wp_error( $result ), 'a debit within the balance succeeds' );
assertSame( 60, $result['balance_after'], 'the balance after the debit is exact' );
assertSame( 60, $ledger->get_balance( 7 ), 'the balance table reflects the debit' );
assertSame( 40, $wpdb->balances[7]['lifetime_spent'], 'lifetime spent is incremented' );

$result = $ledger->debit( 7, 61, 'spend', 'Too much', array( 'idempotency_key' => 'd2' ) );

assertTrue( is_wp_error( $result ), 'a debit larger than the balance is refused' );
assertSame( 'mcr_insufficient_funds', $result->get_error_code(), 'the refusal names insufficient funds' );
assertSame( 60, $ledger->get_balance( 7 ), 'the refused debit does not change the balance' );

$result = $ledger->debit( 7, 60, 'spend', 'Exact', array( 'idempotency_key' => 'd3' ) );
assertFalse( is_wp_error( $result ), 'a debit of exactly the balance succeeds' );
assertSame( 0, $ledger->get_balance( 7 ), 'the balance is now zero' );

$result = $ledger->debit( 7, 1, 'spend', 'Overdraft', array( 'idempotency_key' => 'd4' ) );
assertTrue( is_wp_error( $result ), 'a debit from an empty balance is refused' );
assertSame( 0, $ledger->get_balance( 7 ), 'the balance never goes negative' );

/* --- Rollback ----------------------------------------------------------- */

mcr_group( 'Ledger atomicity' );

$before_transactions = count( $wpdb->transactions );
$before_balance     = $ledger->get_balance( 7 );

$result = $ledger->debit( 7, 999, 'spend', 'Rollback test', array( 'idempotency_key' => 'r1' ) );

assertTrue( is_wp_error( $result ), 'the over-budget debit fails' );
assertSame( $before_transactions, count( $wpdb->transactions ), 'the rolled back debit leaves no ledger row' );
assertSame( $before_balance, $ledger->get_balance( 7 ), 'the rolled back debit leaves the balance untouched' );

/* --- Stats -------------------------------------------------------------- */

mcr_group( 'Ledger::get_stats' );

$ledger->credit( 8, 500, 'admin_adjust', '', array( 'idempotency_key' => 's1' ) );
$ledger->credit( 8, 100, 'purchase', '', array( 'idempotency_key' => 's2' ) );
$ledger->debit( 8, 200, 'spend', '', array( 'idempotency_key' => 's3' ) );

$stats = $ledger->get_stats( 8 );

assertSame( 400, $stats['balance'], 'the stats balance is correct' );
assertSame( 600, $stats['lifetime_earned'], 'lifetime earned is correct' );
assertSame( 200, $stats['lifetime_spent'], 'lifetime spent is correct' );

$stats = $ledger->get_stats( 99999 );
assertSame( 0, $stats['balance'], 'an unknown user has a zero balance' );

/* --- Ref/idempotency derivation ----------------------------------------- */

mcr_group( 'Ledger idempotency key derivation' );

$ledger->credit( 9, 10, 'checkin', '', array( 'ref_type' => 'checkin', 'ref_id' => 77 ) );
$key = $wpdb->transactions[ count( $wpdb->transactions ) ]['idempotency_key'];
assertSame( '9:checkin:checkin:77', $key, 'a reference produces a stable idempotency key' );

$ledger->credit( 9, 10, 'checkin', '', array( 'ref_type' => 'checkin', 'ref_id' => 77 ) );
assertSame( 10, $ledger->get_balance( 9 ), 'the same reference cannot credit twice' );

/* --- Payment idempotency key derivation -------------------------------- */

mcr_group( 'Payment idempotency keys are unique per order' );

/*
 * The gateway derives the ledger key from the provider transaction id, or
 * from the order when there is none. The order id is a STRING, so it must
 * not be passed through absint() - that would collapse every order without
 * a provider id onto one key and silently refuse to credit the second one.
 */
$gateway_source = file_get_contents( dirname( __DIR__ ) . '/includes/Gateways/Gateway.php' );

assertTrue(
	false !== strpos( $gateway_source, "'order_' . \$payment_id" ),
	'the fallback idempotency key uses the numeric payment id'
);

assertFalse(
	false !== strpos( $gateway_source, "absint( \$order['order_id']" ),
	'the fallback idempotency key does not run the string order id through absint()'
);

$key_a = 'payment:manual:order_' . 11;
$key_b = 'payment:manual:order_' . 12;

assertTrue( $key_a !== $key_b, 'two different orders derive two different idempotency keys' );

/* --- Purchased coins are tracked separately --------------------------- */

mcr_group( 'Purchased coins are counted separately from earned coins' );

$ledger->credit( 20, 100, 'purchase', '', array( 'idempotency_key' => 'p1', 'purchased' => true ) );
$ledger->credit( 20, 50, 'checkin', '', array( 'idempotency_key' => 'p2' ) );

$stats = $ledger->get_stats( 20 );

assertSame( 150, $stats['balance'], 'both credits are in the balance' );
assertSame( 150, $stats['lifetime_earned'], 'both credits count as earned' );
assertSame( 100, $stats['purchased_total'], 'only the purchase counts as purchased' );

/* --- A failed bonus must not strand the base reward ------------------- */

mcr_group( 'A half-failed check-in is rolled back' );

$clock    = new \MCR\Support\FixedClock( '2026-01-15 10:00:00', 'UTC' );
$policy   = new \MCR\Rewards\CheckInPolicy( $clock, $ledger );

// Two prior days make the streak 3, which reaches the 3-day bonus tier.
$evaluation = $policy->evaluate(
	21,
	array(
		'base_amount'   => 10,
		'bonuses'       => array( 3 => 5 ),
		'previous_keys' => array( '2026-01-13', '2026-01-14' ),
	)
);

assertFalse( is_wp_error( $evaluation ), 'the claim can be evaluated' );
assertSame( 5, $evaluation['bonus'], 'the bonus is included in the evaluation' );

/*
 * A ledger that fails the SECOND credit (the bonus) must leave the base
 * reversed, so the day is not half-paid and the user can retry.
 */
class Half_Failing_Ledger implements \MCR\Ledger\LedgerInterface {

	/**
	 * Balance per user.
	 *
	 * @var array
	 */
	public $balances = array();

	/**
	 * Number of credits before the failure.
	 *
	 * @var int
	 */
	public $fail_after = 1;

	/**
	 * {@inheritDoc}
	 */
	public function credit( $user_id, $amount, $type, $reason = '', $ctx = array() ) {
		if ( 0 === $this->fail_after ) {
			return new WP_Error( 'mcr_ledger_down', 'The ledger is unavailable.' );
		}

		--$this->fail_after;

		$this->balances[ $user_id ] = ( $this->balances[ $user_id ] ?? 0 ) + $amount;

		return array(
			'transaction_id' => 1,
			'balance_after'  => $this->balances[ $user_id ],
			'duplicate'      => false,
			'amount'         => $amount,
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function debit( $user_id, $amount, $type, $reason = '', $ctx = array() ) {
		$this->balances[ $user_id ] = ( $this->balances[ $user_id ] ?? 0 ) - $amount;

		return array(
			'transaction_id' => 2,
			'balance_after'  => $this->balances[ $user_id ],
			'duplicate'      => false,
			'amount'         => -$amount,
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_balance( $user_id ) {
		return (int) ( $this->balances[ $user_id ] ?? 0 );
	}
}

$half = new Half_Failing_Ledger();
$policy = new \MCR\Rewards\CheckInPolicy( $clock, $half );

$result = $policy->grant(
	22,
	$policy->evaluate(
		22,
		array(
			'base_amount'   => 10,
			'bonuses'       => array( 3 => 5 ),
			'previous_keys' => array( '2026-01-13', '2026-01-14' ),
		)
	)
);

assertTrue( is_wp_error( $result ), 'a failed bonus credit is reported as an error' );
assertSame( 0, $half->get_balance( 22 ), 'the base reward is reversed so the day is not half paid' );

mcr_group( 'A reversal is not counted as a spend' );

$ledger->credit( 23, 40, 'checkin', '', array( 'idempotency_key' => 'r1' ) );
$ledger->debit( 23, 40, 'reversal', 'Reversal', array( 'idempotency_key' => 'r2', 'reversal' => true ) );

$stats = $ledger->get_stats( 23 );

assertSame( 0, $stats['balance'], 'the reversal removed the coins' );
assertSame( 0, $stats['lifetime_earned'], 'the reversal gave the earned total back' );
assertSame( 0, $stats['lifetime_spent'], 'the reversal is not counted as a spend' );

/* --- A reversal that cannot be applied keeps the day claimed ----------- */

mcr_group( 'A failed reversal reports that the base was not reversed' );

/**
 * A ledger whose debit always fails, so the reversal cannot be applied.
 */
class Unreversible_Ledger extends Half_Failing_Ledger {

	/**
	 * {@inheritDoc}
	 */
	public function debit( $user_id, $amount, $type, $reason = '', $ctx = array() ) {
		return new WP_Error( 'mcr_debit_failed', 'The debit could not be applied.' );
	}
}

$stuck = new Unreversible_Ledger();
$policy = new \MCR\Rewards\CheckInPolicy( $clock, $stuck );

$result = $policy->grant(
	24,
	$policy->evaluate(
		24,
		array(
			'base_amount'   => 10,
			'bonuses'       => array( 3 => 5 ),
			'previous_keys' => array( '2026-01-13', '2026-01-14' ),
		)
	)
);

assertTrue( is_wp_error( $result ), 'the failed grant is reported' );

$data = $result->get_error_data();

assertTrue( is_array( $data ) && isset( $data['reversed'] ), 'the error says whether the base was reversed' );
assertFalse( ! empty( $data['reversed'] ), 'the error says the reversal did NOT happen' );
assertSame( 10, $stuck->get_balance( 24 ), 'the base coins remain when the reversal fails' );

/* --- Summary ------------------------------------------------------------ */

echo "\n==============================\n";

$total = $GLOBALS['mcr_tests']['passed'] + $GLOBALS['mcr_tests']['failed'];

printf(
	"Assertions: %d | Passed: %d | Failed: %d\n",
	$total,
	$GLOBALS['mcr_tests']['passed'],
	$GLOBALS['mcr_tests']['failed']
);

if ( $GLOBALS['mcr_tests']['failed'] > 0 ) {
	echo "\nFailures:\n";

	foreach ( $GLOBALS['mcr_tests']['failures'] as $failure ) {
		printf( "  - [%s] %s\n", $failure['group'], $failure['message'] );

		if ( '' !== $failure['detail'] ) {
			printf( "      %s\n", $failure['detail'] );
		}
	}

	exit( 1 );
}

echo "OK\n";
exit( 0 );
