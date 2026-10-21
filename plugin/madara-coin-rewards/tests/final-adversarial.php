<?php
/**
 * Final adversarial review.
 *
 * Run with: php tests/final-adversarial.php
 *
 * This suite attacks the fixes that were applied after the six earlier
 * reviews. Every assertion here either
 *
 *   - proves a defect that is STILL live (and therefore fails), or
 *   - documents a control that could NOT be broken (and therefore passes).
 *
 * A passing assertion is a result too: it says the fix holds.
 *
 * @package MCR
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/wp-stubs.php';

require_once dirname( __DIR__ ) . '/includes/Autoloader.php';

\MCR\Autoloader::register( dirname( __DIR__ ) . '/' );

if ( ! function_exists( 'esc_textarea' ) ) {
	/**
	 * Textarea escaping.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function esc_textarea( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES );
	}
}

if ( ! function_exists( 'sanitize_html_class' ) ) {
	/**
	 * HTML class sanitiser.
	 *
	 * @param string $classname Class name.
	 * @param string $fallback Fallback.
	 * @return string
	 */
	function sanitize_html_class( $classname, $fallback = '' ) {
		$sanitized = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $classname );

		return '' !== $sanitized ? $sanitized : $fallback;
	}
}

/* ==========================================================================
 * A faithful in-memory $wpdb
 * ======================================================================== */

/**
 * An in-memory wpdb that implements the semantics the plugin depends on:
 * the conditional balance guard, the UNIQUE idempotency_key index, the
 * UNIQUE (user_id, checkin_date) index, transactions, and the ad session
 * state machine.
 */
class MCR_Adv_WPDB {

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
	 * Every query that ran.
	 *
	 * @var array
	 */
	public $queries = array();

	/**
	 * Balance rows keyed by user id.
	 *
	 * @var array
	 */
	public $balances = array();

	/**
	 * Ledger rows keyed by id.
	 *
	 * @var array
	 */
	public $transactions = array();

	/**
	 * Ad session rows keyed by id.
	 *
	 * @var array
	 */
	public $sessions = array();

	/**
	 * Check-in rows keyed by id.
	 *
	 * @var array
	 */
	public $checkins = array();

	/**
	 * Next ledger id.
	 *
	 * @var int
	 */
	private $next_txn_id = 1;

	/**
	 * Next ad session id.
	 *
	 * @var int
	 */
	private $next_session_id = 1;

	/**
	 * Next check-in id.
	 *
	 * @var int
	 */
	private $next_checkin_id = 1;

	/**
	 * Whether a transaction is open.
	 *
	 * @var bool
	 */
	public $in_transaction = false;

	/**
	 * Snapshot taken at BEGIN.
	 *
	 * @var array|null
	 */
	private $snapshot = null;

	/**
	 * Prepares a query.
	 *
	 * @param string $sql  SQL.
	 * @param mixed  ...$args Arguments.
	 * @return string
	 */
	public function prepare( $sql, ...$args ) {
		if ( empty( $args ) ) {
			return $sql;
		}

		if ( 1 === count( $args ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}

		$sql = str_replace( array( '%d', '%f' ), '%s', $sql );

		$values = array();

		foreach ( $args as $arg ) {
			if ( is_int( $arg ) || is_float( $arg ) ) {
				$values[] = $arg;
			} elseif ( null === $arg ) {
				$values[] = 'NULL';
			} else {
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
	 * Runs a query.
	 *
	 * @param string $sql SQL.
	 * @return int|false
	 */
	public function query( $sql ) {
		$sql = trim( (string) $sql );

		$this->queries[] = $sql;

		$upper = strtoupper( $sql );

		if ( 'START TRANSACTION' === $upper ) {
			$this->in_transaction = true;
			$this->snapshot       = array(
				'balances'     => $this->balances,
				'transactions' => $this->transactions,
				'next_txn_id'  => $this->next_txn_id,
			);

			return 1;
		}

		if ( 'COMMIT' === $upper ) {
			$this->in_transaction = false;

			return 1;
		}

		if ( 'ROLLBACK' === $upper ) {
			if ( $this->in_transaction && ! empty( $this->snapshot ) ) {
				$this->balances     = $this->snapshot['balances'];
				$this->transactions = $this->snapshot['transactions'];
				$this->next_txn_id  = $this->snapshot['next_txn_id'];
			}

			$this->in_transaction = false;

			return 1;
		}

		$this->last_error = '';

		// INSERT INTO ... mcr_balances ... ON DUPLICATE KEY UPDATE.
		if ( preg_match( '/^INSERT INTO .*mcr_balances\s*\(([^)]*)\)\s*VALUES\s*\(([^)]*)\).*ON DUPLICATE KEY UPDATE/s', $sql, $m ) ) {
			return $this->upsert_balance( $m[1], $m[2] );
		}

		// INSERT INTO ... mcr_transactions ... VALUES (...).
		if ( preg_match( '/^INSERT INTO .*mcr_transactions.*VALUES \((.*)\)\s*$/s', $sql, $m ) ) {
			return $this->insert_transaction( $m[1] );
		}

		// INSERT INTO ... mcr_ad_sessions ... VALUES (...).
		if ( preg_match( '/^INSERT INTO .*mcr_ad_sessions.*VALUES \((.*)\)\s*$/s', $sql, $m ) ) {
			return $this->insert_session( $m[1] );
		}

		// INSERT INTO ... mcr_checkins ... VALUES (...).
		if ( preg_match( '/^INSERT INTO .*mcr_checkins.*VALUES \((.*)\)\s*$/s', $sql, $m ) ) {
			return $this->insert_checkin( $m[1] );
		}

		// UPDATE ... mcr_balances SET balance = balance - N ...
		if ( preg_match( '/^UPDATE .*mcr_balances.*SET balance\s*=\s*balance\s*-\s*(-?\d+)/s', $sql ) ) {
			return $this->update_balance( $sql );
		}

		// UPDATE ... mcr_transactions SET balance_after = N WHERE id = I.
		if ( preg_match( '/^UPDATE .*mcr_transactions SET balance_after\s*=\s*(\d+) WHERE id\s*=\s*(\d+)/s', $sql, $m ) ) {
			foreach ( $this->transactions as $row ) {
				if ( (int) $row['id'] === (int) $m[2] ) {
					$this->transactions[ (int) $m[2] ]['balance_after'] = (int) $m[1];
					$this->rows_affected                            = 1;

					return 1;
				}
			}

			$this->rows_affected = 0;

			return 0;
		}

		// UPDATE ... mcr_ad_sessions SET status = ...
		if ( preg_match( '/^UPDATE .*mcr_ad_sessions SET status = \'([a-z_]+)\'(.*)WHERE id\s*=\s*(\d+)(.*)$/s', $sql, $m ) ) {
			$id     = (int) $m[3];
			$status = $m[1];

			if ( ! isset( $this->sessions[ $id ] ) ) {
				$this->rows_affected = 0;

				return 0;
			}

			if ( false !== strpos( $m[4], "AND status = 'pending'" ) && 'pending' !== $this->sessions[ $id ]['status'] ) {
				$this->rows_affected = 0;

				return 0;
			}

			$this->sessions[ $id ]['status'] = $status;

			if ( preg_match( '/completion_token = \'([^\']*)\'/', $m[2], $cm ) ) {
				$this->sessions[ $id ]['completion_token'] = $cm[1];
			}

			if ( preg_match( '/verified_at = \'([^\']*)\'/', $m[2], $vm ) ) {
				$this->sessions[ $id ]['verified_at'] = $vm[1];
			}

			$this->rows_affected = 1;

			return 1;
		}

		// UPDATE ... mcr_checkins SET ...
		if ( preg_match( '/^UPDATE .*mcr_checkins SET (.*) WHERE id\s*=\s*(\d+)/s', $sql, $m ) ) {
			$id = (int) $m[2];

			if ( ! isset( $this->checkins[ $id ] ) ) {
				$this->rows_affected = 0;

				return 0;
			}

			if ( preg_match( '/transaction_id = (\d+)/', $m[1], $tm ) ) {
				$this->checkins[ $id ]['transaction_id'] = (int) $tm[1];
			}

			if ( preg_match( '/total_amount = (\d+)/', $m[1], $tm2 ) ) {
				$this->checkins[ $id ]['total_amount'] = (int) $tm2[1];
			}

			if ( preg_match( '/bonus_amount = (\d+)/', $m[1], $tm3 ) ) {
				$this->checkins[ $id ]['bonus_amount'] = (int) $tm3[1];
			}

			$this->rows_affected = 1;

			return 1;
		}

		// DELETE FROM ... mcr_transactions WHERE id = I.
		if ( preg_match( '/^DELETE FROM .*mcr_transactions WHERE id\s*=\s*(\d+)/s', $sql, $m ) ) {
			$id = (int) $m[1];
			unset( $this->transactions[ $id ] );

			$this->rows_affected = 1;

			return 1;
		}

		// DELETE FROM ... mcr_checkins WHERE id = I.
		if ( preg_match( '/^DELETE FROM .*mcr_checkins WHERE id\s*=\s*(\d+)/s', $sql, $m ) ) {
			$id = (int) $m[1];
			unset( $this->checkins[ $id ] );

			$this->rows_affected = 1;

			return 1;
		}

		$this->rows_affected = 0;

		return 0;
	}

	/**
	 * Upserts a balance row exactly like MySQL does.
	 *
	 * Two different statements write this table:
	 *
	 *  - the credit path, which ADDS to balance / lifetime_earned /
	 *    purchased_total, and
	 *  - reconcile_user(), which SETS balance / lifetime_earned /
	 *    lifetime_spent to recomputed values and leaves purchased_total
	 *    alone.
	 *
	 * @param string $columns Column list.
	 * @param string $values  Values list.
	 * @return int
	 */
	private function upsert_balance( $columns, $values ) {
		$parts = $this->split_values( $values );

		$user_id = (int) $parts[0];

		if ( ! isset( $this->balances[ $user_id ] ) ) {
			$this->balances[ $user_id ] = array(
				'balance'         => 0,
				'lifetime_earned' => 0,
				'lifetime_spent'  => 0,
				'purchased_total' => 0,
			);
		}

		if ( false !== strpos( $columns, 'lifetime_spent' ) ) {
			// reconcile_user(): absolute values.
			$this->balances[ $user_id ]['balance']         = (int) $parts[1];
			$this->balances[ $user_id ]['lifetime_earned'] = (int) $parts[2];
			$this->balances[ $user_id ]['lifetime_spent']  = (int) $parts[3];

			$this->rows_affected = 1;

			return 1;
		}

		// The credit path: relative values.
		$amount    = (int) $parts[1];
		$purchased = isset( $parts[3] ) ? (int) $parts[3] : 0;

		$this->balances[ $user_id ]['balance']         += $amount;
		$this->balances[ $user_id ]['lifetime_earned'] += $amount;
		$this->balances[ $user_id ]['purchased_total'] += $purchased;

		$this->rows_affected = 1;

		return 1;
	}

	/**
	 * Applies the conditional balance UPDATE.
	 *
	 * @param string $sql SQL.
	 * @return int
	 */
	private function update_balance( $sql ) {
		$amount = 0;

		if ( preg_match( '/balance\s*=\s*balance\s*-\s*(\d+)/', $sql, $m ) ) {
			$amount = (int) $m[1];
		}

		$restore_purchased = 0;

		if ( preg_match( '/purchased_total\s*=\s*purchased_total\s*-\s*(\d+)/', $sql, $m ) ) {
			$restore_purchased = (int) $m[1];
		}

		$is_reversal = ( false !== strpos( $sql, 'lifetime_earned = lifetime_earned -' ) );

		$user_id = 0;
		$guard   = 0;
		$earned  = 0;

		if ( preg_match( '/WHERE user_id\s*=\s*(\d+)\s*AND balance\s*>=\s*(\d+)/', $sql, $m ) ) {
			$user_id = (int) $m[1];
			$guard   = (int) $m[2];
		}

		if ( preg_match( '/AND lifetime_earned\s*>=\s*(\d+)/', $sql, $m ) ) {
			$earned = (int) $m[1];
		}

		if ( ! isset( $this->balances[ $user_id ] ) ) {
			$this->rows_affected = 0;

			return 0;
		}

		if ( $this->balances[ $user_id ]['balance'] < $guard ) {
			$this->rows_affected = 0;

			return 0;
		}

		if ( $is_reversal ) {
			if ( $this->balances[ $user_id ]['lifetime_earned'] < $earned ) {
				$this->rows_affected = 0;

				return 0;
			}

			$this->balances[ $user_id ]['balance']         -= $amount;
			$this->balances[ $user_id ]['lifetime_earned'] -= $amount;
			/*
			 * MySQL applies the SET list unconditionally once the WHERE
			 * clause matched. There is NO guard on purchased_total, so a
			 * reversal that restores more than was ever bought drives the
			 * counter negative.
			 */
			$this->balances[ $user_id ]['purchased_total'] -= $restore_purchased;
		} else {
			$this->balances[ $user_id ]['balance']        -= $amount;
			$this->balances[ $user_id ]['lifetime_spent'] += $amount;
		}

		$this->rows_affected = 1;

		return 1;
	}

	/**
	 * Inserts a ledger row, honouring the UNIQUE idempotency_key.
	 *
	 * @param string $values Values list.
	 * @return int|false
	 */
	private function insert_transaction( $values ) {
		$parts = $this->split_values( $values );

		$key = isset( $parts[9] ) ? trim( $parts[9], "'" ) : '';

		foreach ( $this->transactions as $row ) {
			if ( $row['idempotency_key'] === $key ) {
				$this->last_error = "Duplicate entry '" . $key . "' for key 'idempotency_key'";
				$this->rows_affected = 0;

				return false;
			}
		}

		$id = $this->next_txn_id++;

		$this->transactions[ $id ] = array(
			'id'              => $id,
			'user_id'         => (int) $parts[0],
			'txn_uid'         => trim( $parts[1], "'" ),
			'type'            => trim( $parts[2], "'" ),
			'amount'          => (int) $parts[3],
			'balance_after'   => 0,
			'reason'          => trim( $parts[4], "'" ),
			'source'          => trim( $parts[5], "'" ),
			'ref_type'        => trim( $parts[6], "'" ),
			'ref_id'          => (int) $parts[7],
			'idempotency_key' => $key,
			'ip'              => trim( $parts[10], "'" ),
			'user_agent'      => trim( $parts[11], "'" ),
			'created_at'      => trim( $parts[12], "'" ),
		);

		$this->insert_id     = $id;
		$this->rows_affected = 1;

		return 1;
	}

	/**
	 * Inserts an ad session row.
	 *
	 * @param string $values Values list.
	 * @return int
	 */
	private function insert_session( $values ) {
		$parts = $this->split_values( $values );

		$id = $this->next_session_id++;

		$this->sessions[ $id ] = array(
			'id'               => $id,
			'user_id'          => (int) $parts[0],
			'ad_id'            => trim( $parts[1], "'" ),
			'provider'         => trim( $parts[2], "'" ),
			'session_token'    => trim( $parts[3], "'" ),
			'completion_token' => trim( $parts[4], "'" ),
			'day_key'          => trim( $parts[5], "'" ),
			'status'           => trim( $parts[6], "'" ),
			'coins'            => (int) $parts[7],
			'ip'               => trim( $parts[8], "'" ),
			'created_at'       => trim( $parts[9], "'" ),
			'expires_at'       => trim( $parts[10], "'" ),
			'verified_at'      => trim( $parts[11], "'" ),
		);

		$this->insert_id     = $id;
		$this->rows_affected = 1;

		return 1;
	}

	/**
	 * Inserts a check-in row, honouring the UNIQUE (user_id, checkin_date).
	 *
	 * @param string $values Values list.
	 * @return int|false
	 */
	private function insert_checkin( $values ) {
		$parts = $this->split_values( $values );

		$user_id = (int) $parts[0];
		$date    = trim( $parts[1], "'" );

		foreach ( $this->checkins as $row ) {
			if ( (int) $row['user_id'] === $user_id && $row['checkin_date'] === $date ) {
				$this->last_error = "Duplicate entry '" . $user_id . '-' . $date . "' for key 'user_day'";
				$this->rows_affected = 0;

				return false;
			}
		}

		$id = $this->next_checkin_id++;

		$this->checkins[ $id ] = array(
			'id'             => $id,
			'user_id'        => $user_id,
			'checkin_date'   => $date,
			'streak_count'   => (int) $parts[2],
			'base_amount'    => (int) $parts[3],
			'bonus_amount'   => (int) $parts[4],
			'total_amount'   => (int) $parts[5],
			'transaction_id' => (int) $parts[6],
			'ip'             => trim( $parts[7], "'" ),
			'created_at'     => trim( $parts[8], "'" ),
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
		// SHOW TABLE STATUS.
		if ( preg_match( "/SHOW TABLE STATUS LIKE '([^']+)'/", $sql, $m ) ) {
			return 'InnoDB';
		}

		// SELECT balance FROM ... mcr_balances WHERE user_id = N.
		if ( preg_match( '/SELECT balance FROM .*mcr_balances WHERE user_id = (\d+)/', $sql, $m ) ) {
			$user_id = (int) $m[1];

			return isset( $this->balances[ $user_id ] ) ? $this->balances[ $user_id ]['balance'] : null;
		}

		// SELECT COALESCE(SUM(amount), 0) FROM ... mcr_transactions WHERE user_id = N.
		if ( preg_match( '/SUM\(amount\), 0\) FROM .*mcr_transactions WHERE user_id = (\d+)/', $sql, $m ) ) {
			$user_id = (int) $m[1];
			$sum     = 0;

			foreach ( $this->transactions as $row ) {
				if ( (int) $row['user_id'] === $user_id ) {
					$sum += (int) $row['amount'];
				}
			}

			return $sum;
		}

		// The lifetime_earned recomputation.
		if ( preg_match( '/SUM\(CASE WHEN amount > 0 OR type = \'reversal\' THEN amount ELSE 0 END\)/', $sql, $m ) ) {
			$user_id = (int) $this->user_id_from( $sql );
			$sum     = 0;

			foreach ( $this->transactions as $row ) {
				if ( (int) $row['user_id'] !== $user_id ) {
					continue;
				}

				$amount = (int) $row['amount'];

				if ( $amount > 0 || 'reversal' === $row['type'] ) {
					$sum += $amount;
				}
			}

			return $sum;
		}

		// The lifetime_spent recomputation.
		if ( preg_match( '/SUM\(CASE WHEN amount < 0 AND type <> \'reversal\' THEN -amount ELSE 0 END\)/', $sql, $m ) ) {
			$user_id = (int) $this->user_id_from( $sql );
			$sum     = 0;

			foreach ( $this->transactions as $row ) {
				if ( (int) $row['user_id'] !== $user_id ) {
					continue;
				}

				$amount = (int) $row['amount'];

				if ( $amount < 0 && 'reversal' !== $row['type'] ) {
					$sum += -$amount;
				}
			}

			return $sum;
		}

		// COUNT(*) over the ad sessions.
		if ( preg_match( '/SELECT COUNT\(\*\) FROM .*mcr_ad_sessions/i', $sql ) ) {
			return $this->count_sessions( $sql );
		}

		// UNIX_TIMESTAMP(MAX(verified_at)).
		if ( preg_match( '/UNIX_TIMESTAMP\(MAX\(verified_at\)\)/', $sql ) ) {
			$user_id = (int) $this->user_id_from( $sql );
			$max     = 0;

			foreach ( $this->sessions as $row ) {
				if ( (int) $row['user_id'] !== $user_id ) {
					continue;
				}

				if ( ! in_array( $row['status'], array( 'verified', 'credited' ), true ) ) {
					continue;
				}

				$ts = strtotime( $row['verified_at'] . ' UTC' );

				if ( $ts && $ts > $max ) {
					$max = $ts;
				}
			}

			return $max;
		}

		// The check-in pre-check.
		if ( preg_match( '/SELECT id FROM .*mcr_checkins WHERE user_id = (\d+) AND checkin_date = \'([^\']*)\'/', $sql, $m ) ) {
			foreach ( $this->checkins as $row ) {
				if ( (int) $row['user_id'] === (int) $m[1] && $row['checkin_date'] === $m[2] ) {
					return (int) $row['id'];
				}
			}

			return null;
		}

		// The postback replay check.
		if ( preg_match( '/SELECT id FROM .*mcr_ad_sessions WHERE completion_token = \'([^\']*)\' AND status = \'credited\'/', $sql, $m ) ) {
			foreach ( $this->sessions as $row ) {
				if ( $row['completion_token'] === $m[1] && 'credited' === $row['status'] ) {
					return (int) $row['id'];
				}
			}

			return null;
		}

		return null;
	}

	/**
	 * Extracts the user id from a WHERE clause.
	 *
	 * @param string $sql SQL.
	 * @return int
	 */
	private function user_id_from( $sql ) {
		if ( preg_match( '/user_id = (\d+)/', $sql, $m ) ) {
			return (int) $m[1];
		}

		return 0;
	}

	/**
	 * Counts the ad sessions matching a COUNT query.
	 *
	 * @param string $sql SQL.
	 * @return int
	 */
	private function count_sessions( $sql ) {
		$user_id = 0;

		if ( preg_match( '/user_id = (\d+)/', $sql, $m ) ) {
			$user_id = (int) $m[1];
		}

		$statuses = array( 'verified', 'credited' );

		if ( preg_match( '/status IN \( \'([^\']*)\'(?:, \'([^\']*)\')?/', $sql, $m ) ) {
			$statuses = array_filter( array( $m[1], isset( $m[2] ) ? $m[2] : '' ) );
		}

		$from = '';
		$to   = '';

		if ( preg_match( '/verified_at >= \'([^\']*)\'/', $sql, $m ) ) {
			$from = $m[1];
		}

		if ( preg_match( '/verified_at < \'([^\']*)\'/', $sql, $m ) ) {
			$to = $m[1];
		}

		if ( preg_match( '/verified_at > \'([^\']*)\'/', $sql, $m ) ) {
			$from = $m[1];
		}

		$exclude = 0;

		if ( preg_match( '/id <> (\d+)/', $sql, $m ) ) {
			$exclude = (int) $m[1];
		}

		$count = 0;

		foreach ( $this->sessions as $row ) {
			if ( $user_id && (int) $row['user_id'] !== $user_id ) {
				continue;
			}

			if ( ! in_array( $row['status'], $statuses, true ) ) {
				continue;
			}

			if ( (int) $row['id'] === $exclude ) {
				continue;
			}

			if ( '' !== $from && '' !== $to ) {
				$verified = strtotime( $row['verified_at'] . ' UTC' );
				$lo       = strtotime( $from . ' UTC' );
				$hi       = strtotime( $to . ' UTC' );

				if ( $verified < $lo || $verified >= $hi ) {
					continue;
				}
			} elseif ( '' !== $from ) {
				$verified = strtotime( $row['verified_at'] . ' UTC' );
				$lo       = strtotime( $from . ' UTC' );

				if ( $verified <= $lo ) {
					continue;
				}
			}

			++$count;
		}

		return $count;
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
		if ( preg_match( '/SHOW TABLE STATUS LIKE \'([^\']*)\'/', $sql, $m ) ) {
			return array(
				(object) array(
					'Name'   => $m[1],
					'Engine' => 'InnoDB',
				),
			);
		}

		if ( preg_match( '/SELECT balance, lifetime_earned, lifetime_spent, purchased_total FROM .*mcr_balances WHERE user_id = (\d+)/', $sql, $m ) ) {
			$user_id = (int) $m[1];

			if ( ! isset( $this->balances[ $user_id ] ) ) {
				return array();
			}

			$row = $this->balances[ $user_id ];

			return array(
				'OBJECT' === $type
					? (object) $row
					: $row,
			);
		}

		if ( preg_match( '/SELECT lifetime_earned, lifetime_spent FROM .*mcr_balances WHERE user_id = (\d+)/', $sql, $m ) ) {
			$user_id = (int) $m[1];

			if ( ! isset( $this->balances[ $user_id ] ) ) {
				return array();
			}

			$row = array(
				'lifetime_earned' => (int) $this->balances[ $user_id ]['lifetime_earned'],
				'lifetime_spent'  => (int) $this->balances[ $user_id ]['lifetime_spent'],
			);

			return array(
				'OBJECT' === $type
					? (object) $row
					: $row,
			);
		}

		if ( preg_match( '/SELECT \* FROM .*mcr_transactions WHERE idempotency_key = \'([^\']*)\'/', $sql, $m ) ) {
			foreach ( $this->transactions as $row ) {
				if ( $row['idempotency_key'] === $m[1] ) {
					return array(
						'OBJECT' === $type
							? (object) $row
							: $row,
					);
				}
			}

			return array();
		}

		if ( preg_match( '/SELECT \* FROM .*mcr_ad_sessions WHERE session_token = \'([^\']*)\'/', $sql, $m ) ) {
			foreach ( $this->sessions as $row ) {
				if ( $row['session_token'] === $m[1] ) {
					return array(
						'OBJECT' === $type
							? (object) $row
							: $row,
					);
				}
			}

			return array();
		}

		return array();
	}

	/**
	 * Selects a column.
	 *
	 * @param string $sql SQL.
	 * @return array
	 */
	public function get_col( $sql ) {
		if ( preg_match( '/SELECT checkin_date FROM .*mcr_checkins WHERE user_id = (\d+)/', $sql, $m ) ) {
			$user_id = (int) $m[1];
			$out     = array();

			foreach ( $this->checkins as $row ) {
				if ( (int) $row['user_id'] === $user_id ) {
					$out[] = $row['checkin_date'];
				}
			}

			rsort( $out );

			return $out;
		}

		return array();
	}
}

/* ==========================================================================
 * Helpers
 * ======================================================================== */

/**
 * Resets the whole harness state.
 *
 * @param array $settings Stored settings.
 * @return void
 */
function mcr_adv_reset( array $settings = array() ) {
	$GLOBALS['mcr_options'] = array(
		'mcr_settings' => $settings,
	);

	$GLOBALS['mcr_filters'] = array();
	$GLOBALS['mcr_current_user'] = 0;
	$GLOBALS['mcr_caps']       = array();

	\MCR\Settings::flush_cache();
	\MCR\Gateways\Registry::reset();
}

/**
 * Converts an HTML form field name into the PHP array shape PHP produces.
 *
 * 'mcr_settings[gateways.stripe][secret_key]' becomes
 * array( 'gateways.stripe' => array( 'secret_key' => $value ) ).
 *
 * @param string $name  Field name.
 * @param mixed  $value Value.
 * @return array
 */
function mcr_adv_post_shape( $name, $value ) {
	$parts = preg_split( '/\[/', $name );
	$root  = array_shift( $parts );

	$out = array();

	$cursor =& $out;
	$cursor[ $root ] = array();

	foreach ( $parts as $part ) {
		$key = rtrim( $part, ']' );

		if ( '' === $key ) {
			continue;
		}

		$cursor[ $key ] = array();
		$cursor         =& $cursor[ $key ];
	}

	// The deepest level holds the value.
	$ref = &$cursor;

	$ref = $value;

	unset( $ref );

	return $out;
}

/**
 * Extracts the value attribute of the first input with the given name.
 *
 * @param string $html HTML.
 * @param string $name Field name.
 * @return string|null
 */
function mcr_adv_input_value( $html, $name ) {
	$pattern = '/<input[^>]*name="' . preg_quote( $name, '/' ) . '"[^>]*value="([^"]*)"/';

	if ( preg_match( $pattern, $html, $m ) ) {
		return $m[1];
	}

	return null;
}

/**
 * Extracts every input name that starts with the given prefix.
 *
 * @param string $html   HTML.
 * @param string $prefix Prefix.
 * @return array
 */
function mcr_adv_input_names( $html, $prefix ) {
	$names = array();

	if ( preg_match_all( '/name="([^"]*)"/', $html, $m ) ) {
		foreach ( $m[1] as $name ) {
			if ( 0 === strpos( $name, $prefix ) ) {
				$names[] = $name;
			}
		}
	}

	return array_values( array_unique( $names ) );
}

/**
 * Runs a PHP snippet in a child process and returns its output.
 *
 * @param string $code PHP code.
 * @param array  $env  Extra environment.
 * @return array { code:int, output:string }.
 */
function mcr_adv_run_child( $code, array $env = array() ) {
	$dir = sys_get_temp_dir() . '/mcr-adv-' . getmypid();

	if ( ! is_dir( $dir ) ) {
		mkdir( $dir, 0777, true );
	}

	$file = $dir . '/child-' . md5( $code ) . '.php';

	file_put_contents( $file, $code );

	$descriptors = array(
		1 => array( 'pipe', 'w' ),
		2 => array( 'pipe', 'w' ),
	);

	$process = proc_open(
	escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $file ),
	$descriptors,
	$pipes,
		dirname( __DIR__ )
	);

	if ( ! is_resource( $process ) ) {
		return array(
			'code'   => -1,
			'output' => 'could not start the child process',
		);
	}

	$out = stream_get_contents( $pipes[1] );
	$err = stream_get_contents( $pipes[2] );

	fclose( $pipes[1] );
	fclose( $pipes[2] );

	$code = proc_close( $process );

	return array(
		'code'   => $code,
		'output' => $out . $err,
	);
}

/* ==========================================================================
 * Tests
 * ======================================================================== */

echo "MCR Final Adversarial Review\n";
echo "============================\n\n";

global $wpdb;

/* ==========================================================================
 * 1. The admin settings screen: the gateway / provider fields are dead
 * ======================================================================== */

mcr_group( '1. The admin screen cannot save a gateway or a provider setting' );

mcr_adv_reset();

$wpdb = new MCR_Adv_WPDB();
$GLOBALS['wpdb'] = $wpdb;

$settings_page = new \MCR\Admin\SettingsPage();

ob_start();
$settings_page->render_field_gateways();
$gateway_html = (string) ob_get_clean();

ob_start();
$settings_page->render_field_providers();
$provider_html = (string) ob_get_clean();

$gateway_names = mcr_adv_input_names( $gateway_html, 'mcr_settings[gateways' );
$provider_names = mcr_adv_input_names( $provider_html, 'mcr_settings[ads.providers' );

assertTrue(
	in_array( 'mcr_settings[gateways.stripe][secret_key]', $gateway_names, true ),
	'the Stripe secret key field is rendered by the settings screen'
);

assertTrue(
	in_array( 'mcr_settings[ads.providers.generic_s2s][postback_secret]', $provider_names, true ),
	'the ad postback secret field is rendered by the settings screen'
);

/*
 * The name attribute is 'mcr_settings[gateways.stripe][secret_key]', so PHP
 * hands the sanitizer $POST['mcr_settings']['gateways.stripe']['secret_key'] -
 * a FLAT dotted key, not a nested 'gateways' => array( 'stripe' => ... ).
 */
$posted = mcr_adv_post_shape( 'mcr_settings[gateways.stripe][secret_key]', 'sk_live_ATTACKER' );

assertTrue(
	isset( $posted['gateways.stripe']['secret_key'] ),
	'PHP parses the posted field name into the flat "gateways.stripe" key'
);

assertFalse(
	isset( $posted['gateways'] ),
	'the posted field name does NOT produce a nested "gateways" array'
);

mcr_adv_reset();

$clean = \MCR\Settings::sanitize(
	array(
		'features.checkin_enabled' => 'on',
		'gateways.stripe'          => array(
			'secret_key' => 'sk_live_ATTACKER',
		),
	)
);

assertTrue(
	isset( $clean['gateways.stripe']['secret_key'] ),
	'Settings::sanitize() keeps the Stripe secret key that the form submitted'
);

assertTrue(
	isset( $clean['gateways.stripe']['secret_key'] ) && 'sk_live_ATTACKER' !== $clean['gateways.stripe']['secret_key'],
	'the submitted Stripe secret key is stored encrypted at rest'
);

mcr_adv_reset();

$clean = \MCR\Settings::sanitize(
	array(
		'ads.providers.generic_s2s' => array(
			'postback_secret' => 'hmac-ATTACKER',
		),
	)
);

assertTrue(
	isset( $clean['ads.providers.generic_s2s']['postback_secret'] ),
	'Settings::sanitize() keeps the ad postback secret that the form submitted'
);

/*
 * Control: the sanitizer DOES understand the NESTED shape, which is a shape
 * no form in the plugin ever produces.
 */
mcr_adv_reset();

$clean = \MCR\Settings::sanitize(
	array(
		'gateways' => array(
			'stripe' => array(
				'secret_key' => 'sk_live_NESTED',
			),
		),
	)
);

assertTrue(
	isset( $clean['gateways.stripe']['secret_key'] ),
	'control: the sanitizer handles the nested "gateways" => array shape'
);

assertTrue(
	isset( $clean['gateways.stripe']['secret_key'] ) && 'sk_live_NESTED' !== $clean['gateways.stripe']['secret_key'],
	'control: the nested shape is encrypted at rest'
);

/* ==========================================================================
 * 2. The CSV export
 * ======================================================================== */

mcr_group( '2. The CSV export fatals when a reason filter is used' );

$child_code = <<<'PHP'
<?php
define( 'ABSPATH', '__MCR_ROOT__/' );
require_once '__MCR_ROOT__/tests/bootstrap.php';
require_once '__MCR_ROOT__/tests/wp-stubs.php';
require_once '__MCR_ROOT__/includes/Autoloader.php';
\MCR\Autoloader::register( '__MCR_ROOT__/' );

$GLOBALS['mcr_caps'] = array( 'mcr_manage_coins' => true );
$GLOBALS['wpdb']    = new MCR_Fake_WPDB();

if ( ! function_exists( 'check_admin_referer' ) ) {
	function check_admin_referer( $action = -1, $query_arg = '_wpnonce' ) {
		return true;
	}
}

$_POST['search'] = 'refund';

\MCR\Admin\TransactionsPage::export_via_admin_post();
PHP;

$child_code = str_replace( '__MCR_ROOT__', dirname( __DIR__ ), $child_code );

$child = mcr_adv_run_child( $child_code );

if ( 0 === $child['code'] ) {
	mcr_pass( 'exporting the ledger with a reason filter does not fatal' );
} else {
	mcr_fail(
		'exporting the ledger with a reason filter does not fatal',
		'child exit code ' . $child['code'] . "\n" . $child['output']
	);
}

if ( false === stripos( $child['output'], 'Fatal error' ) ) {
	mcr_pass( 'the export never fatals on the reason filter' );
} else {
	mcr_fail(
		'the export never fatals on the reason filter',
		$child['output']
	);
}

/* Control: the export without a reason filter works. */
$child_code_ok = str_replace( "\$_POST['search'] = 'refund';", '', $child_code );

$child_ok = mcr_adv_run_child( $child_code_ok );

if ( 0 === $child_ok['code'] ) {
	mcr_pass( 'control: exporting the ledger without a reason filter works' );
} else {
	mcr_fail(
		'control: exporting the ledger without a reason filter works',
		'child exit code ' . $child_ok['code'] . "\n" . $child_ok['output']
	);
}

/* ==========================================================================
 * 3. The reversal path
 * ======================================================================== */

mcr_group( '3. A reversal can drive purchased_total negative' );

mcr_adv_reset();

$wpdb = new MCR_Adv_WPDB();
$GLOBALS['wpdb'] = $wpdb;

$ledger = new \MCR\Ledger();

/*
 * The user earns 1000 coins from a check-in. Nothing was BOUGHT, so
 * purchased_total is 0.
 */
$ledger->credit( 42, 1000, 'checkin', 'Daily reward', array( 'idempotency_key' => 'adv-earn-1' ) );

assertSame( 0, (int) $wpdb->balances[42]['purchased_total'], 'the earned coins are not counted as purchased' );

/*
 * A refund-style reversal with restore_purchased=true. This is EXACTLY what
 * Gateway::refund_order() sends.
 */
$ledger->debit(
	42,
	1000,
	'refund',
	'Payment refunded',
	array(
		'idempotency_key'  => 'adv-refund-1',
		'reversal'         => true,
		'restore_purchased' => true,
	)
);

assertTrue(
	(int) $wpdb->balances[42]['purchased_total'] >= 0,
	'a reversal never drives purchased_total negative',
	'purchased_total is now ' . (int) $wpdb->balances[42]['purchased_total']
);

assertSame( 0, (int) $wpdb->balances[42]['balance'], 'the reversal took the coins back' );

/* ==========================================================================
 * 4. reconcile_user()
 * ======================================================================== */

mcr_group( '4. reconcile_user() corrupts the lifetime counters of a refunded user' );

mcr_adv_reset();

$wpdb = new MCR_Adv_WPDB();
$GLOBALS['wpdb'] = $wpdb;

$ledger = new \MCR\Ledger();

$ledger->credit(
	7,
	500,
	'purchase',
	'Coin package purchase',
	array(
		'idempotency_key' => 'adv-purchase-1',
		'purchased'       => true,
	)
);

$ledger->debit(
	7,
	500,
	'refund',
	'Payment refunded',
	array(
		'idempotency_key'  => 'adv-refund-2',
		'reversal'         => true,
		'restore_purchased' => true,
	)
);

assertSame( 0, (int) $wpdb->balances[7]['balance'], 'the refunded user has no balance left' );
assertSame( 0, (int) $wpdb->balances[7]['lifetime_earned'], 'the refunded user has earned nothing' );
assertSame( 0, (int) $wpdb->balances[7]['lifetime_spent'], 'a refund is not a spend' );

$reconciled = $ledger->reconcile_user( 7 );

assertSame(
	0,
	(int) $wpdb->balances[7]['lifetime_earned'],
	'reconcile_user() keeps lifetime_earned at 0 after a refund',
	'reconcile_user() reported ' . wp_json_encode( $reconciled )
);

assertSame(
	0,
	(int) $wpdb->balances[7]['lifetime_spent'],
	'reconcile_user() keeps lifetime_spent at 0 after a refund',
	'reconcile_user() reported ' . wp_json_encode( $reconciled )
);

assertSame(
	0,
	(int) $wpdb->balances[7]['purchased_total'],
	'reconcile_user() keeps purchased_total at 0 after a refund'
);

/* Control: reconcile_user does not lose purchased_total for a live row. */
mcr_adv_reset();

$wpdb = new MCR_Adv_WPDB();
$GLOBALS['wpdb'] = $wpdb;

$ledger = new \MCR\Ledger();

$ledger->credit( 8, 250, 'purchase', 'Coin package purchase', array( 'idempotency_key' => 'adv-p-2', 'purchased' => true ) );
$ledger->credit( 8, 10, 'checkin', 'Daily reward', array( 'idempotency_key' => 'adv-c-2' ) );

$ledger->reconcile_user( 8 );

assertSame(
	250,
	(int) $wpdb->balances[8]['purchased_total'],
	'control: reconcile_user() does not lose purchased_total for an existing balance row'
);

/* ==========================================================================
 * 5. The ad cap
 * ======================================================================== */

mcr_group( '5. The ad cap holds on BOTH the client and the server-to-server path' );

assertSame(
	'status IN ( \'verified\', \'credited\' )',
	\MCR\Rewards\AdSessions::counted_status_sql(),
	'the counted status vocabulary contains both terminal states'
);

mcr_adv_reset();

$wpdb = new MCR_Adv_WPDB();
$GLOBALS['wpdb'] = $wpdb;

$wpdb->sessions[1] = array(
	'id'               => 1,
	'user_id'          => 5,
	'ad_id'            => 'rewarded_video',
	'provider'         => 'generic_s2s',
	'session_token'    => str_repeat( 'a', 64 ),
	'completion_token' => 'tx-1',
	'day_key'          => gmdate( 'Y-m-d' ),
	'status'           => 'credited',
	'coins'            => 5,
	'ip'               => '',
	'created_at'       => gmdate( 'Y-m-d H:i:s' ),
	'expires_at'       => gmdate( 'Y-m-d H:i:s', time() + 900 ),
	'verified_at'      => gmdate( 'Y-m-d H:i:s' ),
);

$wpdb->sessions[2] = array(
	'id'               => 2,
	'user_id'          => 5,
	'ad_id'            => 'rewarded_video',
	'provider'         => 'generic_s2s',
	'session_token'    => str_repeat( 'b', 64 ),
	'completion_token' => 'tx-2',
	'day_key'          => gmdate( 'Y-m-d' ),
	'status'           => 'verified',
	'coins'            => 5,
	'ip'               => '',
	'created_at'       => gmdate( 'Y-m-d H:i:s' ),
	'expires_at'       => gmdate( 'Y-m-d H:i:s', time() + 900 ),
	'verified_at'      => gmdate( 'Y-m-d H:i:s' ),
);

$GLOBALS['mcr_options']['mcr_settings']['ads.daily_limit'] = 2;
\MCR\Settings::flush_cache();

$sessions = \MCR\Rewards\AdSessions::instance();

$cap = $sessions->cap_guard( 5, 99 );

assertTrue(
	is_wp_error( $cap ),
	'a user who already watched the daily maximum through both paths is blocked'
);

assertSame(
	'mcr_daily_limit_reached',
	is_wp_error( $cap ) ? $cap->get_error_code() : '',
	'the cap error is named mcr_daily_limit_reached'
);

/* Control: a user below the cap is allowed. */
mcr_adv_reset();

$wpdb = new MCR_Adv_WPDB();
$GLOBALS['wpdb'] = $wpdb;

$GLOBALS['mcr_options']['mcr_settings']['ads.daily_limit'] = 2;
\MCR\Settings::flush_cache();

$sessions = \MCR\Rewards\AdSessions::instance();

assertTrue(
	true === $sessions->cap_guard( 5, 0 ),
	'control: a user below the daily cap is allowed'
);

/* Control: the client path refuses a session the S2S path already credited. */
$policy = new \MCR\Rewards\AdRewardPolicy(
	new \MCR\Support\WpClock(),
	new \MCR\Ledger(),
	new class() {
		public function is_configured() {
			return true;
		}
	}
);

$credited_session = array(
	'id'          => 11,
	'user_id'     => 5,
	'status'      => 'credited',
	'coins'       => 5,
	'provider'    => 'generic_s2s',
	'ad_id'       => 'rewarded_video',
	'expires_at'  => gmdate( 'Y-m-d H:i:s', time() + 900 ),
);

$result = $policy->verify(
	5,
	array(
		'session_token'    => str_repeat( 'c', 64 ),
		'completion_token' => 'tx',
	),
	array(
		'session' => $credited_session,
		'coins'   => 5,
		'ref_id'  => 11,
	)
);

assertTrue(
	is_wp_error( $result ) && 'mcr_already_verified' === $result->get_error_code(),
	'control: the client path refuses a session the S2S path already credited'
);

/* Control: both paths derive the same ledger key, so a second credit is a no-op. */
$adreward = new \MCR\Rewards\AdRewardPolicy(
	new \MCR\Support\WpClock(),
	new \MCR\Ledger(),
	new class() {
		public function is_configured() {
			return true;
		}
		public function verify_completion( array $payload ) {
			return true;
		}
	}
);

mcr_adv_reset();

$wpdb = new MCR_Adv_WPDB();
$GLOBALS['wpdb'] = $wpdb;

$ledger = new \MCR\Ledger();

$first = $ledger->credit( 5, 5, 'ad_reward', 'Rewarded ad completion', array( 'idempotency_key' => 'ad_session:11' ) );
$second = $ledger->credit( 5, 5, 'ad_reward', 'Rewarded ad (network verified)', array( 'idempotency_key' => 'ad_session:11' ) );

assertFalse( ! empty( $first['duplicate'] ), 'control: the first ad credit moves money' );
assertTrue( ! empty( $second['duplicate'] ), 'control: the second ad credit on the same session is a duplicate no-op' );
assertSame( 5, (int) $wpdb->balances[5]['balance'], 'control: the user is paid for the ad exactly once' );

/* ==========================================================================
 * 6. The check-in attempt-scoped keys
 * ======================================================================== */

mcr_group( '6. The check-in claim row is the real gate' );

mcr_adv_reset();

$wpdb = new MCR_Adv_WPDB();
$GLOBALS['wpdb'] = $wpdb;

$GLOBALS['mcr_options']['mcr_settings']['checkin.base_amount'] = 10;
\MCR\Settings::flush_cache();

$checkin = \MCR\Rewards\CheckIn::instance();

$first = $checkin->claim( 3 );

assertFalse( is_wp_error( $first ), 'the first daily claim succeeds' );
assertSame( 10, (int) $wpdb->balances[3]['balance'], 'the first claim pays the base amount' );

$second = $checkin->claim( 3 );

assertTrue(
	is_wp_error( $second ) && 'mcr_already_claimed' === $second->get_error_code(),
	'the second claim on the same day is refused'
);

assertSame( 10, (int) $wpdb->balances[3]['balance'], 'the user is paid for the day exactly once' );

assertCount( 1, $wpdb->checkins, 'exactly one claim row exists for the day' );

/* Control: the idempotency key is scoped to the claim row. */
$policy = new \MCR\Rewards\CheckInPolicy(
	new \MCR\Support\WpClock(),
	new \MCR\Ledger()
);

$evaluation = array(
	'user_id'  => 3,
	'date_key' => '2026-10-21',
	'streak'   => 1,
	'base'     => 10,
	'bonus'    => 0,
	'total'    => 10,
);

mcr_adv_reset();

$wpdb = new MCR_Adv_WPDB();
$GLOBALS['wpdb'] = $wpdb;

$policy->grant( 3, $evaluation, array( 'attempt' => 77 ) );

$keys = array();

foreach ( $wpdb->transactions as $row ) {
	$keys[] = $row['idempotency_key'];
}

assertTrue(
	in_array( 'checkin:3:2026-10-21:77', $keys, true ),
	'control: the check-in idempotency key carries the claim row id'
);

mcr_adv_reset();

$wpdb = new MCR_Adv_WPDB();
$GLOBALS['wpdb'] = $wpdb;

$policy->grant( 3, $evaluation, array( 'attempt' => 78 ) );

$keys = array();

foreach ( $wpdb->transactions as $row ) {
	$keys[] = $row['idempotency_key'];
}

assertTrue(
	in_array( 'checkin:3:2026-10-21:78', $keys, true ),
	'control: a later attempt gets a different key, so a retry is not blocked'
);

/* ==========================================================================
 * 7. The rate limiter
 * ======================================================================== */

mcr_group( '7. The rate limiter window does not match the advertised setting' );

$limiter_source = (string) file_get_contents( dirname( __DIR__ ) . '/includes/Support/RateLimiter.php' );

assertTrue(
	false !== strpos( $limiter_source, 'const WINDOW = 3600;' ),
	'control: the rate limiter window is an hour, as the settings screen says'
);

$settings_page_source = (string) file_get_contents( dirname( __DIR__ ) . '/includes/Admin/SettingsPage.php' );

assertTrue(
	false !== strpos( $settings_page_source, 'per user per hour' ),
	'control: the admin screen advertises the limit as "per user per hour"'
);

assertTrue(
	\MCR\Support\RateLimiter::WINDOW >= 3600,
	'the rate limiter window matches the advertised "per hour" window'
);

/* Control: the bucket is per user. */
mcr_adv_reset();

$allowed_a = \MCR\Support\RateLimiter::allow( 'checkin', 1, 2 );
$allowed_b = \MCR\Support\RateLimiter::allow( 'checkin', 2, 2 );

assertTrue( $allowed_a, 'control: user 1 is inside the budget' );
assertTrue( $allowed_b, 'control: user 2 has its own budget' );

mcr_adv_reset();

\MCR\Support\RateLimiter::allow( 'checkin', 1, 1 );

assertFalse(
	\MCR\Support\RateLimiter::allow( 'checkin', 1, 1 ),
	'control: the bucket is exhausted after the limit'
);

/* ==========================================================================
 * 8. The secrets
 * ======================================================================== */

mcr_group( '8. The secret encryption round trip' );

$secret = 'sk_live_ABCDEF123456';

$encrypted = \MCR\Support\Secrets::encrypt( $secret );

assertTrue( 'sk_live_ABCDEF123456' !== $encrypted, 'the secret is not stored in the clear' );
assertSame( $secret, \MCR\Support\Secrets::decrypt( $encrypted ), 'the secret decrypts back to the original value' );
assertSame( $encrypted, \MCR\Support\Secrets::encrypt( $encrypted ), 'an already encrypted secret is not encrypted twice' );
assertSame( $secret, \MCR\Support\Secrets::decrypt( \MCR\Support\Secrets::encrypt( \MCR\Support\Secrets::encrypt( $secret ) ) ), 'a double submission still decrypts' );

mcr_adv_reset();

$stored = \MCR\Gateways\Gateway::sanitize_settings(
	'stripe',
	array(
		'secret_key' => $secret,
	),
	array()
);

assertTrue(
	isset( $stored['secret_key'] ) && 'sk_live_ABCDEF123456' !== $stored['secret_key'],
	'the gateway sanitizer encrypts the Stripe secret key'
);

$kept = \MCR\Gateways\Gateway::sanitize_settings(
	'stripe',
	array(
		'secret_key' => '',
	),
	$stored
);

assertSame(
	$stored['secret_key'],
	$kept['secret_key'],
	'control: an empty secret field keeps the stored secret'
);

assertSame(
	$secret,
	\MCR\Support\Secrets::decrypt( $kept['secret_key'] ),
	'control: the kept secret still decrypts'
);

/* ==========================================================================
 * 9. The re-validated credit amount
 * ======================================================================== */

mcr_group( '9. The re-validated credit amount' );

/**
 * Runs one filtered credit on a fresh database.
 *
 * @param mixed $filter_value What the filter returns.
 * @return array { result:mixed, balance:int }.
 */
function mcr_adv_filtered_credit( $filter_value ) {
	mcr_adv_reset();

	$wpdb = new MCR_Adv_WPDB();
	$GLOBALS['wpdb'] = $wpdb;

	$GLOBALS['mcr_filters']['mcr_credit_amount'] = array(
		function () use ( $filter_value ) {
			return $filter_value;
		},
	);

	$ledger = new \MCR\Ledger();

	$result = $ledger->credit( 4, 100, 'checkin', 'Daily reward', array( 'idempotency_key' => 'adv-' . md5( serialize( $filter_value ) ) ) );

	$GLOBALS['mcr_filters'] = array();

	return array(
		'result'  => $result,
		'balance' => isset( $wpdb->balances[4] ) ? (int) $wpdb->balances[4]['balance'] : 0,
	);
}

$negative = mcr_adv_filtered_credit( -100 );

assertTrue(
	is_wp_error( $negative['result'] ) && 'mcr_invalid_amount' === $negative['result']->get_error_code(),
	'a filter that returns a negative amount is rejected'
);

assertSame( 0, $negative['balance'], 'a rejected credit does not touch the balance' );

$huge = mcr_adv_filtered_credit( PHP_INT_MAX );

assertTrue(
	is_wp_error( $huge['result'] ) && 'mcr_invalid_amount' === $huge['result']->get_error_code(),
	'a filter that returns PHP_INT_MAX is rejected'
);

$exponent = mcr_adv_filtered_credit( '1e30' );

assertTrue(
	is_wp_error( $exponent['result'] ) && 'mcr_invalid_amount' === $exponent['result']->get_error_code(),
	'a filter that returns an out-of-range exponent is rejected'
);

/*
 * The (int) cast happens BEFORE the re-validation, so a value that only
 * LOOKS small survives the cast as a huge integer.
 */
$nine = mcr_adv_filtered_credit( '1e9' );

assertTrue(
	is_wp_error( $nine['result'] ),
	'a filter that returns the string "1e9" is rejected',
	'the credit was accepted and the balance became ' . $nine['balance']
);

assertTrue(
	$nine['balance'] <= 100,
	'a filter cannot inflate a 100 coin credit into a billion coins',
	'the balance became ' . $nine['balance']
);

$float = mcr_adv_filtered_credit( 1.5 );

assertTrue(
	is_wp_error( $float['result'] ),
	'a filter that returns a float is rejected instead of silently truncated',
	'the credit was accepted for ' . $float['balance'] . ' coins'
);

assertSame( 0, $float['balance'], 'a float filter value is never silently truncated into the ledger' );

mcr_adv_reset();

$wpdb = new MCR_Adv_WPDB();
$GLOBALS['wpdb'] = $wpdb;

$ledger = new \MCR\Ledger();

$result = $ledger->credit( 4, 100, 'checkin', 'Daily reward', array( 'idempotency_key' => 'adv-ok' ) );

assertFalse( is_wp_error( $result ), 'control: a normal credit still works' );
assertSame( 100, (int) $wpdb->balances[4]['balance'], 'control: the balance is 100' );

/* ==========================================================================
 * 10. Templates::suffix_ids()
 * ======================================================================== */

mcr_group( '10. suffix_ids() and the script that reads the suffixed ids' );

$reflection = new \ReflectionClass( \MCR\Templates\Templates::class );

$suffix = $reflection->getMethod( 'suffix_ids' );
$suffix->setAccessible( true );

$fragment = '<section class="mcr-section" id="mcr-tx" aria-labelledby="mcr-tx-title">'
	. '<h2 id="mcr-tx-title">T</h2>'
	. '<a href="#mcr-tx">go</a>'
	. '</section>';

$suffixed = $suffix->invoke( null, $fragment, '-2' );

assertTrue( false !== strpos( $suffixed, 'id="mcr-tx-2"' ), 'control: the id is suffixed' );
assertTrue( false !== strpos( $suffixed, 'aria-labelledby="mcr-tx-title-2"' ), 'control: the aria reference follows' );
assertTrue( false !== strpos( $suffixed, 'href="#mcr-tx-2"' ), 'control: the anchor follows' );

$transactions_template = (string) file_get_contents( dirname( __DIR__ ) . '/templates/transactions.php' );

assertTrue(
	false !== strpos( $transactions_template, 'id="mcr-txn-rows"' ),
	'control: the transactions template declares the id mcr-txn-rows'
);

$frontend_js = (string) file_get_contents( dirname( __DIR__ ) . '/assets/js/mcr-frontend.js' );

assertTrue(
	false !== strpos( $frontend_js, "getElementById( 'mcr-txn-rows' )" ),
	'control: the script looks the rows element up by its canonical id'
);

$rows_suffixed = $suffix->invoke( null, '<tbody id="mcr-txn-rows"></tbody>', '-2' );

assertTrue(
	false !== strpos( $rows_suffixed, 'id="mcr-txn-rows-2"' ),
	'a second render renames the rows element'
);

/*
 * The id of the rows element is suffixed in a second render, so the
 * script cannot reach it by that id. It finds it by the data attribute
 * instead, which is not suffixed.
 */
assertTrue(
	false !== strpos( $frontend_js, '[data-mcr-txn-rows]' ),
	'the script can still reach the rows element of a second render',
	'the second render emits ' . trim( $rows_suffixed ) . ' and the script finds the rows by their data attribute'
);

/* ==========================================================================
 * 11. purchase.auto_credit
 * ======================================================================== */

mcr_group( '11. The "auto credit on payment" switch is a dead setting' );

$auto_credit_readers = array();

$skip = array( 'Settings.php', 'SettingsPage.php' );

foreach ( array_merge(
	glob( dirname( __DIR__ ) . '/includes/*.php' ),
	glob( dirname( __DIR__ ) . '/includes/*/*.php' )
) as $file ) {
	if ( in_array( basename( $file ), $skip, true ) ) {
		continue;
	}

	$source = (string) file_get_contents( $file );

	if ( false !== strpos( $source, 'purchase.auto_credit' ) ) {
		$auto_credit_readers[] = basename( $file );
	}
}

assertTrue(
	! empty( $auto_credit_readers ),
	'something reads the purchase.auto_credit setting, so the switch works',
	'no file outside Settings.php / SettingsPage.php reads it'
);

/* ==========================================================================
 * 12. The admin UI escaping
 * ======================================================================== */

mcr_group( '12. The admin UI escapes gateway and provider values' );

mcr_adv_reset(
	array(
		'gateways.stripe' => array(
			'return_url' => '"><script>alert(1)</script>',
		),
	)
);

$settings_page = new \MCR\Admin\SettingsPage();

ob_start();
$settings_page->render_field_gateways();
$gateway_html = (string) ob_get_clean();

assertFalse(
	false !== strpos( $gateway_html, '<script>alert(1)</script>' ),
	'control: a gateway setting value cannot inject a script tag'
);

assertTrue(
	false !== strpos( $gateway_html, '&quot;&gt;&lt;script&gt;' ),
	'control: the gateway setting value is HTML escaped in the attribute'
);

/* ==========================================================================
 * 13. The CSV export controls
 * ======================================================================== */

mcr_group( '13. The CSV export controls' );

$transactions_reflection = new \ReflectionClass( \MCR\Admin\TransactionsPage::class );

$escape = $transactions_reflection->getMethod( 'escape_csv_cell' );
$escape->setAccessible( true );

assertSame(
	"'=cmd|'/C calc'!A0",
	$escape->invoke( null, '=cmd|\'/C calc\'!A0' ),
	'control: a cell that starts with = is neutralised'
);

assertSame(
	"'@SUM(A1:A9)",
	$escape->invoke( null, '@SUM(A1:A9)' ),
	'control: a cell that starts with @ is neutralised'
);

assertSame(
	'ordinary reason',
	$escape->invoke( null, 'ordinary reason' ),
	'control: an ordinary cell is left alone'
);

/*
 * A user who may manage coins but not view other users must only export
 * their own transactions.
 */
$child_scope = <<<'PHP'
<?php
define( 'ABSPATH', '__MCR_ROOT__/' );
require_once '__MCR_ROOT__/tests/bootstrap.php';
require_once '__MCR_ROOT__/tests/wp-stubs.php';
require_once '__MCR_ROOT__/includes/Autoloader.php';
\MCR\Autoloader::register( '__MCR_ROOT__/' );

class MCR_Adv_Spy_WPDB extends MCR_Fake_WPDB {

	public function get_results( $sql, $type = 'OBJECT' ) {
		echo "QUERY: " . $sql . "\n";

		return array();
	}

	public function get_var( $sql ) {
		echo "QUERY: " . $sql . "\n";

		return 0;
	}
}

$GLOBALS['mcr_caps']         = array( 'mcr_manage_coins' => true );
$GLOBALS['mcr_current_user'] = 99;
$GLOBALS['wpdb']             = new MCR_Adv_Spy_WPDB();

if ( ! function_exists( 'check_admin_referer' ) ) {
	function check_admin_referer( $action = -1, $query_arg = '_wpnonce' ) {
		return true;
	}
}

\MCR\Admin\TransactionsPage::export_via_admin_post();
PHP;

$child_scope = str_replace( '__MCR_ROOT__', dirname( __DIR__ ), $child_scope );

$scoped = mcr_adv_run_child( $child_scope );

assertTrue(
	false !== strpos( $scoped['output'], 't.user_id = 99' ),
	'control: a user without mcr_view_users only exports their own transactions',
	$scoped['output']
);

/* ==========================================================================
 * 14. A failed check-in grant does not lock the day
 * ======================================================================== */

mcr_group( '14. A failed check-in grant does not lock the day' );

mcr_adv_reset();

$wpdb = new MCR_Adv_WPDB();
$GLOBALS['wpdb'] = $wpdb;

$GLOBALS['mcr_options']['mcr_settings']['checkin.base_amount'] = 10;
\MCR\Settings::flush_cache();

$GLOBALS['mcr_filters']['mcr_credit_amount'] = array(
	function () {
		return -1;
	},
);

$checkin = \MCR\Rewards\CheckIn::instance();

$failed = $checkin->claim( 9 );

assertTrue( is_wp_error( $failed ), 'the failing claim reports an error' );
assertCount( 0, $wpdb->checkins, 'the claim row of a failed grant is removed' );

$GLOBALS['mcr_filters'] = array();

$retry = $checkin->claim( 9 );

assertFalse( is_wp_error( $retry ), 'the retry after a failed grant succeeds' );
assertSame( 10, (int) $wpdb->balances[9]['balance'], 'the retry pays the reward exactly once' );
assertCount( 1, $wpdb->checkins, 'exactly one claim row survives' );

/* ==========================================================================
 * Summary
 * ======================================================================== */

echo "\n============================\n";

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
