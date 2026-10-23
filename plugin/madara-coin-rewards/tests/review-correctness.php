<?php
/**
 * Correctness & business-logic review.
 *
 * Run with: php tests/review-correctness.php
 *
 * This suite exercises the REAL plugin sources (MCR\Ledger, MCR\Rewards\*,
 * MCR\Payments\*, MCR\Gateways\*, MCR\Settings) against an in-memory $wpdb
 * that faithfully implements the semantics those classes depend on:
 *
 *  - the UNIQUE idempotency_key / (user_id,checkin_date) / session_token /
 *    (gateway,provider_tx_id) indexes,
 *  - the conditional UPDATE guard (WHERE balance >= N),
 *  - INSERT ... ON DUPLICATE KEY UPDATE,
 *  - real transactions (and a non-transactional engine mode),
 *  - a tiny but real SQL parser for the SELECT/UPDATE/INSERT shapes the
 *    plugin issues.
 *
 * Every finding in docs/12-correctness.md is proven here by an assertion
 * that FAILS against the current source.
 *
 * @package MCR
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/wp-stubs.php';

/* -------------------------------------------------------------------------
 * WordPress functions the shared stubs do not provide but the plugin calls.
 * ---------------------------------------------------------------------- */

if ( ! function_exists( 'esc_url_raw' ) ) {
	/**
	 * Raw URL escaping stub.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	function esc_url_raw( $url ) {
		return esc_url( $url );
	}
}

if ( ! function_exists( 'esc_textarea' ) ) {
	/**
	 * Textarea escaping stub.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function esc_textarea( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES );
	}
}

if ( ! function_exists( 'sanitize_title' ) ) {
	/**
	 * Title sanitising stub.
	 *
	 * @param string $title Title.
	 * @return string
	 */
	function sanitize_title( $title ) {
		return trim( preg_replace( '/[^a-z0-9_\-]+/', '-', strtolower( (string) $title ) ), '-' );
	}
}

if ( ! function_exists( 'wp_generate_uuid4' ) ) {
	/**
	 * UUID stub.
	 *
	 * @return string
	 */
	function wp_generate_uuid4() {
		return sprintf(
			'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0x0fff ) | 0x4000,
			wp_rand( 0, 0x3fff ) | 0x8000,
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0xffff )
		);
	}
}

if ( ! function_exists( 'wp_rand' ) ) {
	/**
	 * Random integer stub.
	 *
	 * @param int $min Minimum.
	 * @param int $max Maximum.
	 * @return int
	 */
	function wp_rand( $min = 0, $max = 0 ) {
		return random_int( (int) $min, (int) $max );
	}
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
	/**
	 * Minimal REST request stand-in.
	 */
	class WP_REST_Request {

		/**
		 * Raw body.
		 *
		 * @var string
		 */
		private $body;

		/**
		 * Headers.
		 *
		 * @var array
		 */
		private $headers;

		/**
		 * Parameters.
		 *
		 * @var array
		 */
		private $params;

		/**
		 * Constructor.
		 *
		 * @param string $body    Raw body.
		 * @param array  $headers Headers.
		 * @param array  $params  Parameters.
		 */
		public function __construct( $body = '', $headers = array(), $params = array() ) {
			$this->body    = (string) $body;
			$this->headers = is_array( $headers ) ? $headers : array();
			$this->params  = is_array( $params ) ? $params : array();
		}

		/**
		 * Raw body.
		 *
		 * @return string
		 */
		public function get_body() {
			return $this->body;
		}

		/**
		 * A header (case insensitive).
		 *
		 * @param string $name Header name.
		 * @return string
		 */
		public function get_header( $name ) {
			$name = strtolower( (string) $name );

			foreach ( $this->headers as $key => $value ) {
				if ( strtolower( (string) $key ) === $name ) {
					return (string) $value;
				}
			}

			return '';
		}

		/**
		 * A parameter.
		 *
		 * @param string $name Parameter name.
		 * @return mixed
		 */
		public function get_param( $name ) {
			return isset( $this->params[ $name ] ) ? $this->params[ $name ] : null;
		}
	}
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
	/**
	 * Minimal REST response stand-in.
	 */
	class WP_REST_Response {

		/**
		 * Data.
		 *
		 * @var mixed
		 */
		private $data;

		/**
		 * Status code.
		 *
		 * @var int
		 */
		private $status;

		/**
		 * Constructor.
		 *
		 * @param mixed $data   Data.
		 * @param int   $status Status code.
		 */
		public function __construct( $data = null, $status = 200 ) {
			$this->data   = $data;
			$this->status = (int) $status;
		}

		/**
		 * Data.
		 *
		 * @return mixed
		 */
		public function get_data() {
			return $this->data;
		}

		/**
		 * Status code.
		 *
		 * @return int
		 */
		public function get_status() {
			return $this->status;
		}
	}
}

/* -------------------------------------------------------------------------
 * Plugin autoloader.
 * ---------------------------------------------------------------------- */

require_once dirname( __DIR__ ) . '/includes/Autoloader.php';

\MCR\Autoloader::register( dirname( __DIR__ ) );

/* =========================================================================
 * 1. An in-memory $wpdb
 * ======================================================================== */

/**
 * A small but faithful in-memory wpdb.
 *
 * It parses the SQL the plugin actually issues instead of pattern matching
 * whole statements, so the behaviour under test is the real behaviour.
 */
class Review_WPDB {

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
	 * Last error.
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
	 * Storage engine reported by SHOW TABLE STATUS.
	 *
	 * @var string
	 */
	public $engine = 'InnoDB';

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
	 * Check-in rows.
	 *
	 * @var array
	 */
	public $checkins = array();

	/**
	 * Ad session rows.
	 *
	 * @var array
	 */
	public $ad_sessions = array();

	/**
	 * Payment rows.
	 *
	 * @var array
	 */
	public $payments = array();

	/**
	 * Next auto increment id per table.
	 *
	 * @var array
	 */
	private $next_id = array();

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
	 * How many transactions were started.
	 *
	 * @var int
	 */
	public $transactions_started = 0;

	/**
	 * Test switch: make the ledger INSERT fail.
	 *
	 * @var bool
	 */
	public $fail_transaction_insert = false;

	/**
	 * Test switch: make the check-in INSERT fail.
	 *
	 * @var bool
	 */
	public $fail_checkin_insert = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->next_id = array(
			'mcr_balances'    => 1,
			'mcr_transactions'=> 1,
			'mcr_checkins'    => 1,
			'mcr_ad_sessions' => 1,
			'mcr_payments'    => 1,
		);
	}

	/**
	 * Current GMT datetime.
	 *
	 * @return string
	 */
	public function now_gmt() {
		return gmdate( 'Y-m-d H:i:s' );
	}

	/**
	 * Prepares a query.
	 *
	 * @param string $sql  SQL.
	 * @param mixed  ...$args Arguments.
	 * @return string
	 */
	public function prepare( $sql, ...$args ) {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}

		if ( empty( $args ) ) {
			return $sql;
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
			++$this->transactions_started;
			$this->snapshot = array(
				'balances'     => $this->balances,
				'transactions' => $this->transactions,
				'checkins'     => $this->checkins,
				'ad_sessions'  => $this->ad_sessions,
				'payments'     => $this->payments,
				'next_id'      => $this->next_id,
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
				$this->checkins     = $this->snapshot['checkins'];
				$this->ad_sessions  = $this->snapshot['ad_sessions'];
				$this->payments     = $this->snapshot['payments'];
				$this->next_id      = $this->snapshot['next_id'];
			}

			$this->in_transaction = false;

			return 1;
		}

		$this->last_error = '';

		$insert = $this->parse_insert( $sql );

		if ( null !== $insert ) {
			return $this->handle_insert( $insert['table'], $insert['columns'], $insert['values'], $insert['tail'] );
		}

		if ( preg_match( '/^UPDATE\s+(\S+)\s+SET\s+(.+?)\s+WHERE\s+(.+)$/is', $sql, $m ) ) {
			return $this->handle_update( trim( $m[1], '`' ), $m[2], $m[3] );
		}

		if ( preg_match( '/^DELETE\s+FROM\s+(\S+)\s+WHERE\s+(.+)$/is', $sql, $m ) ) {
			return $this->handle_delete( trim( $m[1], '`' ), $m[2] );
		}

		$this->rows_affected = 0;

		return 0;
	}

	/**
	 * Runs a SELECT and returns the first value.
	 *
	 * @param string $sql SQL.
	 * @return mixed
	 */
	public function get_var( $sql ) {
		$rows = $this->select_rows( $sql );

		if ( empty( $rows ) ) {
			return null;
		}

		$values = array_values( (array) $rows[0] );

		return count( $values ) ? $values[0] : null;
	}

	/**
	 * Runs a SELECT and returns the first row.
	 *
	 * @param string $sql  SQL.
	 * @param string $type Return type.
	 * @return mixed
	 */
	public function get_row( $sql, $type = 'OBJECT' ) {
		$rows = $this->select_rows( $sql );

		if ( empty( $rows ) ) {
			return null;
		}

		return 'ARRAY_A' === $type ? $rows[0] : (object) $rows[0];
	}

	/**
	 * Runs a SELECT and returns every row.
	 *
	 * @param string $sql  SQL.
	 * @param string $type Return type.
	 * @return array
	 */
	public function get_results( $sql, $type = 'OBJECT' ) {
		$rows = $this->select_rows( $sql );

		if ( 'ARRAY_A' === $type ) {
			return $rows;
		}

		$out = array();

		foreach ( $rows as $row ) {
			$out[] = (object) $row;
		}

		return $out;
	}

	/**
	 * Runs a SELECT and returns one column.
	 *
	 * @param string $sql SQL.
	 * @return array
	 */
	public function get_col( $sql ) {
		$rows = $this->select_rows( $sql );
		$out  = array();

		foreach ( $rows as $row ) {
			$values = array_values( $row );

			$out[] = count( $values ) ? $values[0] : null;
		}

		return $out;
	}

	/**
	 * wpdb::insert().
	 *
	 * @param string $table  Table.
	 * @param array  $data   Data.
	 * @param mixed  $format Formats.
	 * @return int|false
	 */
	public function insert( $table, $data, $format = null ) {
		$key  = $this->table_key( $table );
		$rows = &$this->rows( $key );

		$id = $this->next_id[ $key ]++;

		$row = array( 'id' => $id );

		foreach ( $data as $col => $value ) {
			$row[ $col ] = $value;
		}

		$rows[ $id ] = $row;

		$this->insert_id     = $id;
		$this->rows_affected = 1;

		return $id;
	}

	/**
	 * wpdb::update().
	 *
	 * @param string $table        Table.
	 * @param array  $data         Data.
	 * @param array  $where        Where.
	 * @param mixed  $format       Formats.
	 * @param mixed  $where_format Where formats.
	 * @return int|false
	 */
	public function update( $table, $data, $where, $format = null, $where_format = null ) {
		$key  = $this->table_key( $table );
		$rows = &$this->rows( $key );

		$affected = 0;

		foreach ( $rows as $id => $row ) {
			$match = true;

			foreach ( $where as $col => $value ) {
				if ( (string) ( $row[ $col ] ?? '' ) !== (string) $value ) {
					$match = false;
					break;
				}
			}

			if ( ! $match ) {
				continue;
			}

			foreach ( $data as $col => $value ) {
				$rows[ $id ][ $col ] = $value;
			}

			++$affected;
		}

		$this->rows_affected = $affected;

		return $affected;
	}

	/* ---------------------------------------------------------------------
	 * Internals.
	 * ------------------------------------------------------------------ */

	/**
	 * Maps a table name to a storage key.
	 *
	 * @param string $table Table name.
	 * @return string
	 */
	private function table_key( $table ) {
		$table = trim( (string) $table, '`' );

		foreach ( array( 'mcr_balances', 'mcr_transactions', 'mcr_checkins', 'mcr_ad_sessions', 'mcr_payments' ) as $key ) {
			if ( substr( $table, -strlen( $key ) ) === $key ) {
				return $key;
			}
		}

		return $table;
	}

	/**
	 * The rows of one table (by reference).
	 *
	 * @param string $key Storage key.
	 * @return array
	 */
	private function &rows( $key ) {
		switch ( $key ) {
			case 'mcr_balances':
				return $this->balances;
			case 'mcr_transactions':
				return $this->transactions;
			case 'mcr_checkins':
				return $this->checkins;
			case 'mcr_ad_sessions':
				return $this->ad_sessions;
			case 'mcr_payments':
				return $this->payments;
		}

		$empty = array();

		return $empty;
	}

	/**
	 * Finds the index of the parenthesis matching the one at $open.
	 *
	 * @param string $sql  SQL.
	 * @param int    $open Index of the opening parenthesis.
	 * @return int
	 */
	private function match_paren( $sql, $open ) {
		$depth = 0;
		$in_q  = false;
		$len   = strlen( $sql );

		for ( $i = $open; $i < $len; $i++ ) {
			$char = $sql[ $i ];

			if ( '\\' === $char && $in_q ) {
				++$i;
				continue;
			}

			if ( "'" === $char ) {
				$in_q = ! $in_q;
				continue;
			}

			if ( $in_q ) {
				continue;
			}

			if ( '(' === $char ) {
				++$depth;
			} elseif ( ')' === $char ) {
				--$depth;

				if ( 0 === $depth ) {
					return $i;
				}
			}
		}

		return -1;
	}

	/**
	 * Splits a comma separated list on top level commas.
	 *
	 * @param string $list Raw list.
	 * @return array
	 */
	private function split_top( $list ) {
		$parts = array();
		$buf   = '';
		$in_q  = false;
		$len   = strlen( $list );

		for ( $i = 0; $i < $len; $i++ ) {
			$char = $list[ $i ];

			if ( '\\' === $char && $in_q ) {
				$buf .= $char . ( isset( $list[ $i + 1 ] ) ? $list[ $i + 1 ] : '' );
				++$i;
				continue;
			}

			if ( "'" === $char ) {
				$in_q = ! $in_q;
				$buf .= $char;
				continue;
			}

			if ( ',' === $char && ! $in_q ) {
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
	 * Converts a SQL literal into a PHP value.
	 *
	 * @param string $value Raw literal.
	 * @return mixed
	 */
	private function literal( $value ) {
		$value = trim( (string) $value );

		if ( '' === $value ) {
			return '';
		}

		if ( 'NULL' === strtoupper( $value ) ) {
			return null;
		}

		if ( "'" === $value[0] && "'" === substr( $value, -1 ) ) {
			$inner = substr( $value, 1, -1 );

			return str_replace( array( "\\'", '\\\\' ), array( "'", '\\' ), $inner );
		}

		if ( preg_match( '/^-?\d+$/', $value ) ) {
			return (int) $value;
		}

		if ( preg_match( '/^-?\d+\.\d+$/', $value ) ) {
			return (float) $value;
		}

		return $value;
	}

	/**
	 * Parses an INSERT statement.
	 *
	 * @param string $sql SQL.
	 * @return array|null
	 */
	private function parse_insert( $sql ) {
		if ( ! preg_match( '/^INSERT\s+INTO\s+(`?[A-Za-z0-9_]+`?)\s*\(/i', $sql, $m ) ) {
			return null;
		}

		$table   = trim( $m[1], '`' );
		$col_open = strpos( $sql, '(', strlen( $m[0] ) - 1 );
		$col_end  = $this->match_paren( $sql, $col_open );

		if ( $col_end < 0 ) {
			return null;
		}

		$columns = $this->split_top( substr( $sql, $col_open + 1, $col_end - $col_open - 1 ) );

		$rest = trim( substr( $sql, $col_end + 1 ) );

		if ( ! preg_match( '/^VALUES\s*\(/i', $rest, $vm ) ) {
			return null;
		}

		$val_open = strpos( $rest, '(', strlen( $vm[0] ) - 1 );
		$val_end  = $this->match_paren( $rest, $val_open );

		if ( $val_end < 0 ) {
			return null;
		}

		$values = $this->split_top( substr( $rest, $val_open + 1, $val_end - $val_open - 1 ) );
		$tail   = trim( substr( $rest, $val_end + 1 ) );

		return array(
			'table'   => $table,
			'columns' => $columns,
			'values'  => $values,
			'tail'    => $tail,
		);
	}

	/**
	 * Parses one SET expression.
	 *
	 * @param string $expr Expression.
	 * @return array|null
	 */
	private function parse_set( $expr ) {
		if ( ! preg_match( '/^`?([A-Za-z0-9_]+)`?\s*=\s*(.+)$/s', trim( $expr ), $m ) ) {
			return null;
		}

		$col = $m[1];
		$rhs = trim( $m[2] );

		if ( preg_match( '/^`?([A-Za-z0-9_]+)`?\s*\+\s*(\d+)$/', $rhs, $rm ) ) {
			return array( $col, '+', (int) $rm[2] );
		}

		if ( preg_match( '/^`?([A-Za-z0-9_]+)`?\s*-\s*(\d+)$/', $rhs, $rm ) ) {
			return array( $col, '-', (int) $rm[2] );
		}

		return array( $col, '=', $this->literal( $rhs ) );
	}

	/**
	 * Applies SET expressions to a row.
	 *
	 * @param array $row  Row (by reference).
	 * @param array $sets Set expressions.
	 * @return void
	 */
	private function apply_sets( &$row, array $sets ) {
		foreach ( $sets as $set ) {
			list( $col, $op, $value ) = $set;

			if ( '+' === $op ) {
				$row[ $col ] = (int) ( $row[ $col ] ?? 0 ) + (int) $value;
			} elseif ( '-' === $op ) {
				$row[ $col ] = (int) ( $row[ $col ] ?? 0 ) - (int) $value;
			} else {
				$row[ $col ] = $value;
			}
		}
	}

	/**
	 * Evaluates a WHERE clause against a row.
	 *
	 * @param array  $row  Row.
	 * @param string $where WHERE clause.
	 * @return bool
	 */
	private function where_matches( array $row, $where ) {
		$where = trim( (string) $where );

		if ( '' === $where ) {
			return true;
		}

		foreach ( preg_split( '/\s+AND\s+/i', $where ) as $cond ) {
			$cond = trim( $cond );

			if ( '' === $cond ) {
				continue;
			}

			if ( preg_match( '/^`?([A-Za-z0-9_]+)`?\s+IN\s*\(([^)]*)\)$/i', $cond, $im ) ) {
				$col    = $im[1];
				$actual = (string) ( $row[ $col ] ?? '' );
				$found  = false;

				foreach ( explode( ',', $im[2] ) as $candidate ) {
					if ( $actual === (string) $this->literal( trim( $candidate ) ) ) {
						$found = true;
						break;
					}
				}

				if ( ! $found ) {
					return false;
				}

				continue;
			}

			if ( ! preg_match( '/^`?([A-Za-z0-9_]+)`?\s*(<=|>=|<>|!=|=|<|>)\s*(.+)$/is', $cond, $cm ) ) {
				return false;
			}

			$col    = $cm[1];
			$op     = $cm[2];
			$actual = $row[ $col ] ?? null;
			$want   = $this->literal( $cm[3] );

			if ( is_numeric( $actual ) && is_numeric( $want ) ) {
				$actual = (float) $actual;
				$want   = (float) $want;
			} else {
				$actual = (string) $actual;
				$want   = (string) $want;
			}

			switch ( $op ) {
				case '=':
					if ( $actual != $want ) { // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
						return false;
					}
					break;
				case '>=':
					if ( $actual < $want ) {
						return false;
					}
					break;
				case '<=':
					if ( $actual > $want ) {
						return false;
					}
					break;
				case '<>':
				case '!=':
					if ( $actual == $want ) { // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
						return false;
					}
					break;
				case '>':
					if ( $actual <= $want ) {
						return false;
					}
					break;
				case '<':
					if ( $actual >= $want ) {
						return false;
					}
					break;
			}
		}

		return true;
	}

	/**
	 * Handles an INSERT.
	 *
	 * @param string $table   Table.
	 * @param array  $columns Columns.
	 * @param array  $values  Values.
	 * @param string $tail    Tail (ON DUPLICATE KEY UPDATE ...).
	 * @return int|false
	 */
	private function handle_insert( $table, $columns, $values, $tail ) {
		$key = $this->table_key( $table );

		$row = array();

		foreach ( $columns as $index => $column ) {
			$column = trim( $column, " `\t\n\r" );

			$row[ $column ] = array_key_exists( $index, $values ) ? $this->literal( $values[ $index ] ) : null;
		}

		if ( 'mcr_transactions' === $key ) {
			if ( $this->fail_transaction_insert ) {
				$this->last_error = 'Table \'wp_mcr_transactions\' is locked';

				return false;
			}

			foreach ( $this->transactions as $existing ) {
				if ( (string) $existing['idempotency_key'] === (string) $row['idempotency_key'] ) {
					$this->last_error = "Duplicate entry '" . $row['idempotency_key'] . "' for key 'idempotency_key'";

					return false;
				}
			}

			$id                   = $this->next_id[ $key ]++;
			$row['id']            = $id;
			$row['balance_after'] = (int) ( $row['balance_after'] ?? 0 );
			$row['amount']        = (int) ( $row['amount'] ?? 0 );
			$row['ref_id']        = (int) ( $row['ref_id'] ?? 0 );

			$this->transactions[ $id ] = $row;

			$this->insert_id     = $id;
			$this->rows_affected = 1;

			return 1;
		}

		if ( 'mcr_checkins' === $key ) {
			if ( $this->fail_checkin_insert ) {
				$this->last_error = 'Table \'wp_mcr_checkins\' is locked';

				return false;
			}

			foreach ( $this->checkins as $existing ) {
				if ( (int) $existing['user_id'] === (int) $row['user_id'] && (string) $existing['checkin_date'] === (string) $row['checkin_date'] ) {
					$this->last_error = "Duplicate entry '" . $row['user_id'] . '-' . $row['checkin_date'] . "' for key 'user_day'";

					return false;
				}
			}

			$id         = $this->next_id[ $key ]++;
			$row['id']  = $id;

			$this->checkins[ $id ] = $row;

			$this->insert_id     = $id;
			$this->rows_affected = 1;

			return 1;
		}

		if ( 'mcr_ad_sessions' === $key ) {
			foreach ( $this->ad_sessions as $existing ) {
				if ( (string) $existing['session_token'] === (string) $row['session_token'] ) {
					$this->last_error = "Duplicate entry '" . $row['session_token'] . "' for key 'session_token'";

					return false;
				}
			}

			$id        = $this->next_id[ $key ]++;
			$row['id'] = $id;
			$row['coins'] = (int) ( $row['coins'] ?? 0 );

			$this->ad_sessions[ $id ] = $row;

			$this->insert_id     = $id;
			$this->rows_affected = 1;

			return 1;
		}

		if ( 'mcr_payments' === $key ) {
			foreach ( $this->payments as $existing ) {
				if ( (string) $existing['order_id'] === (string) $row['order_id'] ) {
					$this->last_error = "Duplicate entry '" . $row['order_id'] . "' for key 'order_id'";

					return false;
				}

				if ( (string) $existing['gateway'] === (string) $row['gateway'] && (string) $existing['provider_tx_id'] === (string) $row['provider_tx_id'] ) {
					$this->last_error = "Duplicate entry '" . $row['provider_tx_id'] . "' for key 'gateway_provider_tx'";

					return false;
				}
			}

			$id              = $this->next_id[ $key ]++;
			$row['id']       = $id;
			$row['coins_total'] = (int) ( $row['coins_total'] ?? 0 );

			$this->payments[ $id ] = $row;

			$this->insert_id     = $id;
			$this->rows_affected = 1;

			return 1;
		}

		// mcr_balances.
		$user_id = (int) $row['user_id'];

		if ( isset( $this->balances[ $user_id ] ) ) {
			if ( false !== stripos( $tail, 'ON DUPLICATE KEY UPDATE' ) ) {
				$sets = array();

				$set_part = substr( $tail, stripos( $tail, 'ON DUPLICATE KEY UPDATE' ) + strlen( 'ON DUPLICATE KEY UPDATE' ) );

				foreach ( $this->split_top( $set_part ) as $expr ) {
					$set = $this->parse_set( $expr );

					if ( null !== $set ) {
						$sets[] = $set;
					}
				}

				$this->apply_sets( $this->balances[ $user_id ], $sets );

				$this->rows_affected = 1;

				return 1;
			}

			$this->last_error = "Duplicate entry '" . $user_id . "' for key 'user_id'";

			return false;
		}

		$id = $this->next_id[ $key ]++;

		$this->balances[ $user_id ] = array(
			'id'              => $id,
			'user_id'         => $user_id,
			'balance'         => (int) ( $row['balance'] ?? 0 ),
			'lifetime_earned' => (int) ( $row['lifetime_earned'] ?? 0 ),
			'lifetime_spent'  => (int) ( $row['lifetime_spent'] ?? 0 ),
			'purchased_total' => (int) ( $row['purchased_total'] ?? 0 ),
			'updated_at'      => (string) ( $row['updated_at'] ?? '' ),
		);

		$this->insert_id     = $id;
		$this->rows_affected = 1;

		return 1;
	}

	/**
	 * Handles an UPDATE.
	 *
	 * @param string $table Table.
	 * @param string $set   SET clause.
	 * @param string $where WHERE clause.
	 * @return int
	 */
	private function handle_update( $table, $set, $where ) {
		$key  = $this->table_key( $table );
		$rows = &$this->rows( $key );

		$sets = array();

		foreach ( $this->split_top( $set ) as $expr ) {
			$parsed = $this->parse_set( $expr );

			if ( null !== $parsed ) {
				$sets[] = $parsed;
			}
		}

		$affected = 0;

		foreach ( $rows as $id => $row ) {
			if ( ! $this->where_matches( $row, $where ) ) {
				continue;
			}

			$this->apply_sets( $rows[ $id ], $sets );

			++$affected;
		}

		$this->rows_affected = $affected;

		return $affected;
	}

	/**
	 * Handles a DELETE.
	 *
	 * @param string $table Table.
	 * @param string $where WHERE clause.
	 * @return int
	 */
	private function handle_delete( $table, $where ) {
		$key  = $this->table_key( $table );
		$rows = &$this->rows( $key );

		$affected = 0;

		foreach ( $rows as $id => $row ) {
			if ( ! $this->where_matches( $row, $where ) ) {
				continue;
			}

			unset( $rows[ $id ] );

			++$affected;
		}

		$this->rows_affected = $affected;

		return $affected;
	}

	/**
	 * Runs a SELECT.
	 *
	 * @param string $sql SQL.
	 * @return array
	 */
	private function select_rows( $sql ) {
		$sql = preg_replace( '/\s+/', ' ', trim( (string) $sql ) );

		$this->queries[] = $sql;

		if ( preg_match( "/^SHOW TABLE STATUS LIKE '([^']*)'/i", $sql, $m ) ) {
			return array(
				array(
					'Name'   => $m[1],
					'Engine' => $this->engine,
				),
			);
		}

		if ( preg_match( '/^SELECT GET_LOCK\(/i', $sql ) ) {
			return array( array( 'lock' => '1' ) );
		}

		if ( preg_match( '/^SELECT RELEASE_LOCK\(/i', $sql ) ) {
			return array( array( 'lock' => '1' ) );
		}

		if ( ! preg_match( '/^SELECT (.+?) FROM (\S+)(.*)$/is', $sql, $m ) ) {
			return array();
		}

		$cols  = trim( $m[1] );
		$table = trim( $m[2], '`' );
		$rest  = trim( $m[3] );

		$order  = '';
		$limit  = null;
		$offset = 0;

		if ( preg_match( '/\s+ORDER BY\s+/i', $rest, $p, PREG_OFFSET_CAPTURE ) ) {
			$part  = $p[0][0];
			$pos   = $p[0][1];
			$order = trim( substr( $rest, $pos + strlen( $part ) ) );
			$rest  = substr( $rest, 0, $pos );
		}

		if ( preg_match( '/\s+LIMIT\s+(\d+)/i', $rest, $p, PREG_OFFSET_CAPTURE ) ) {
			$part  = $p[0][0];
			$pos   = $p[0][1];
			$limit = (int) $p[1][0];
			$rest  = substr( $rest, 0, $pos );
		}

		if ( preg_match( '/\s+OFFSET\s+(\d+)/i', $rest, $p, PREG_OFFSET_CAPTURE ) ) {
			$part   = $p[0][0];
			$pos    = $p[0][1];
			$offset = (int) $p[1][0];
			$rest   = substr( $rest, 0, $pos );
		}

		$rest = trim( $rest );

		if ( 0 === stripos( $rest, 'WHERE ' ) ) {
			$rest = trim( substr( $rest, 6 ) );
		}

		$key  = $this->table_key( $table );
		$rows = &$this->rows( $key );

		$matched = array();

		foreach ( $rows as $id => $row ) {
			if ( $this->where_matches( $row, $rest ) ) {
				$matched[ $id ] = $row;
			}
		}

		if ( '' !== $order ) {
			$desc    = ( false !== stripos( $order, ' DESC' ) );
			$columns = array_map( 'trim', explode( ',', $order ) );

			usort(
				$matched,
				function ( $a, $b ) use ( $columns, $desc ) {
					foreach ( $columns as $column ) {
						$column = preg_replace( '/\s+(ASC|DESC)$/i', '', $column );

						if ( $a[ $column ] == $b[ $column ] ) { // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
							continue;
						}

						return ( $a[ $column ] < $b[ $column ] ) ? -1 : 1;
					}

					return 0;
				}
			);

			if ( $desc ) {
				$matched = array_reverse( $matched );
			}
		}

		$matched = array_values( $matched );

		if ( null !== $limit ) {
			$matched = array_slice( $matched, $offset, $limit );
		} elseif ( $offset > 0 ) {
			$matched = array_slice( $matched, $offset );
		}

		return $this->project( $cols, $matched );
	}

	/**
	 * Applies the select list to the matched rows.
	 *
	 * @param string $cols    Select list.
	 * @param array  $matched Matched rows.
	 * @return array
	 */
	private function project( $cols, array $matched ) {
		if ( '*' === $cols ) {
			return $matched;
		}

		if ( preg_match( '/^COUNT\(\s*\*\s*\)$/i', $cols ) ) {
			return array( array( 'count' => count( $matched ) ) );
		}

		/*
		 * A conditional aggregate: COALESCE( SUM( CASE WHEN <cond> THEN <expr>
		 * ELSE <expr> END ), 0 ).
		 *
		 * The repair tool recomputes the lifetime counters with exactly
		 * this shape, so the harness has to understand it - otherwise the
		 * recomputed value silently reads as 0 and the repair looks broken
		 * when it is not.
		 */
		if ( preg_match( '/^COALESCE\(\s*SUM\(\s*CASE\s+WHEN\s+(.+?)\s+THEN\s+(.+?)\s+ELSE\s+(.+?)\s+END\s*\)\s*,\s*0\s*\)$/is', $cols, $m ) ) {
			$sum = 0;

			foreach ( $matched as $row ) {
				if ( ! $this->case_when_matches( $row, $m[1] ) ) {
					continue;
				}

				$sum += (float) $this->case_value( $row, $m[2] );
			}

			return array( array( 'sum' => $sum ) );
		}

		if ( preg_match( '/COALESCE\(\s*SUM\(\s*([A-Za-z0-9_]+)\s*\)\s*,\s*0\s*\)/i', $cols, $m ) ) {
			$sum = 0;

			foreach ( $matched as $row ) {
				$sum += (float) ( $row[ $m[1] ] ?? 0 );
			}

			return array( array( 'sum' => $sum ) );
		}

		if ( preg_match( '/UNIX_TIMESTAMP\(\s*MAX\(\s*([A-Za-z0-9_]+)\s*\)\s*\)/i', $cols, $m ) ) {
			$max = null;

			foreach ( $matched as $row ) {
				$value = $row[ $m[1] ] ?? null;

				if ( null === $value || '' === $value ) {
					continue;
				}

				if ( null === $max || $value > $max ) {
					$max = $value;
				}
			}

			if ( null === $max ) {
				return array( array( 'max' => null ) );
			}

			return array( array( 'max' => (int) strtotime( $max . ' UTC' ) ) );
		}

		$list = array_map( 'trim', explode( ',', $cols ) );
		$out  = array();

		foreach ( $matched as $row ) {
			$new = array();

			foreach ( $list as $column ) {
				$new[ $column ] = $row[ $column ] ?? null;
			}

			$out[] = $new;
		}

		return $out;
	}

	/**
	 * Evaluates a CASE WHEN condition against a row.
	 *
	 * The supported grammar is the one the plugin actually uses:
	 * comparisons ( =, !=, <>, <, <=, >, >= ), IN and NOT IN, combined
	 * with AND and OR. AND binds tighter than OR, as in SQL.
	 *
	 * @param array  $row       Row.
	 * @param string $condition Condition.
	 * @return bool
	 */
	private function case_when_matches( array $row, $condition ) {
		$condition = trim( (string) $condition );

		if ( '' === $condition ) {
			return true;
		}

		foreach ( $this->split_logical( $condition, 'OR' ) as $or_part ) {
			$matched = true;

			foreach ( $this->split_logical( $or_part, 'AND' ) as $and_part ) {
				if ( ! $this->case_condition_matches( $row, trim( $and_part ) ) ) {
					$matched = false;
					break;
				}
			}

			if ( $matched ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Evaluates one comparison of a CASE WHEN condition.
	 *
	 * @param array  $row       Row.
	 * @param string $condition Condition.
	 * @return bool
	 */
	private function case_condition_matches( array $row, $condition ) {
		if ( '' === $condition ) {
			return true;
		}

		if ( preg_match( '/^`?([A-Za-z0-9_]+)`?\s+(NOT\s+)?IN\s*\((.*)\)$/is', $condition, $m ) ) {
			$col     = $m[1];
			$negated = '' !== trim( $m[2] );
			$actual  = (string) ( $row[ $col ] ?? '' );
			$found   = false;

			foreach ( $this->split_top( $m[3] ) as $candidate ) {
				if ( $actual === (string) $this->literal( trim( $candidate ) ) ) {
					$found = true;
					break;
				}
			}

			return $negated ? ! $found : $found;
		}

		if ( ! preg_match( '/^`?([A-Za-z0-9_]+)`?\s*(<=|>=|<>|!=|=|<|>)\s*(.+)$/is', $condition, $m ) ) {
			return false;
		}

		$col    = $m[1];
		$op     = $m[2];
		$actual = $row[ $col ] ?? null;
		$want   = $this->literal( $m[3] );

		if ( is_numeric( $actual ) && is_numeric( $want ) ) {
			$actual = (float) $actual;
			$want   = (float) $want;
		} else {
			$actual = (string) $actual;
			$want   = (string) $want;
		}

		switch ( $op ) {
			case '=':
				return $actual == $want; // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
			case '!=':
			case '<>':
				return $actual != $want; // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
			case '>=':
				return $actual >= $want;
			case '<=':
				return $actual <= $want;
			case '>':
				return $actual > $want;
			case '<':
				return $actual < $want;
		}

		return false;
	}

	/**
	 * Evaluates the THEN branch of a CASE WHEN expression.
	 *
	 * @param array  $row  Row.
	 * @param string $expr Expression.
	 * @return float
	 */
	private function case_value( array $row, $expr ) {
		$expr = trim( (string) $expr );

		if ( preg_match( '/^-([A-Za-z0-9_]+)$/', $expr, $m ) ) {
			return -1 * (float) ( $row[ $m[1] ] ?? 0 );
		}

		if ( preg_match( '/^([A-Za-z0-9_]+)$/', $expr, $m ) ) {
			return (float) ( $row[ $m[1] ] ?? 0 );
		}

		return (float) $this->literal( $expr );
	}

	/**
	 * Splits a SQL expression on a top level AND or OR.
	 *
	 * The keyword is only a separator when it stands on its own, outside
	 * quotes and outside parentheses.
	 *
	 * @param string $sql     SQL.
	 * @param string $keyword AND or OR.
	 * @return array
	 */
	private function split_logical( $sql, $keyword ) {
		$parts = array();
		$buf   = '';
		$depth = 0;
		$in_q  = false;
		$len   = strlen( $sql );
		$i     = 0;

		while ( $i < $len ) {
			$char = $sql[ $i ];

			if ( "'" === $char ) {
				$in_q = ! $in_q;
				$buf .= $char;
				++$i;
				continue;
			}

			if ( ! $in_q ) {
				if ( '(' === $char ) {
					++$depth;
				} elseif ( ')' === $char ) {
					--$depth;
				}
			}

			if ( ! $in_q && 0 === $depth && 0 === substr_compare( $sql, $keyword, $i, strlen( $keyword ) ) ) {
				$before = ( $i > 0 ) ? $sql[ $i - 1 ] : ' ';
				$after  = ( $i + strlen( $keyword ) < $len ) ? $sql[ $i + strlen( $keyword ) ] : ' ';

				if ( ! preg_match( '/[A-Za-z0-9_]/', $before ) && ! preg_match( '/[A-Za-z0-9_]/', $after ) ) {
					$parts[] = trim( $buf );
					$buf     = '';
					$i      += strlen( $keyword );
					continue;
				}
			}

			$buf .= $char;
			++$i;
		}

		if ( '' !== trim( $buf ) ) {
			$parts[] = trim( $buf );
		}

		return $parts;
	}

	/* ---------------------------------------------------------------------
	 * Test helpers.
	 * ------------------------------------------------------------------ */

	/**
	 * Seeds a payment row.
	 *
	 * @param array $row Row data.
	 * @return int The row id.
	 */
	public function seed_payment( array $row ) {
		$id = $this->next_id['mcr_payments']++;

		$row['id'] = $id;

		$this->payments[ $id ] = $row;

		return $id;
	}

	/**
	 * Seeds an ad session row.
	 *
	 * @param array $row Row data.
	 * @return int The row id.
	 */
	public function seed_ad_session( array $row ) {
		$id = $this->next_id['mcr_ad_sessions']++;

		$row['id'] = $id;

		$this->ad_sessions[ $id ] = $row;

		return $id;
	}

	/**
	 * Seeds a check-in row.
	 *
	 * @param array $row Row data.
	 * @return int The row id.
	 */
	public function seed_checkin( array $row ) {
		$id = $this->next_id['mcr_checkins']++;

		$row['id'] = $id;

		$this->checkins[ $id ] = $row;

		return $id;
	}

	/**
	 * The ledger row with the given idempotency key.
	 *
	 * @param string $key Key.
	 * @return array|null
	 */
	public function transaction_by_key( $key ) {
		foreach ( $this->transactions as $row ) {
			if ( (string) $row['idempotency_key'] === (string) $key ) {
				return $row;
			}
		}

		return null;
	}

	/**
	 * Whether any recorded query contains the needle.
	 *
	 * @param string $needle Needle.
	 * @return bool
	 */
	public function ran( $needle ) {
		foreach ( $this->queries as $sql ) {
			if ( false !== strpos( $sql, $needle ) ) {
				return true;
			}
		}

		return false;
	}
}

/* =========================================================================
 * 2. Shared fixtures
 * ======================================================================== */

global $wpdb;

$wpdb = new Review_WPDB();

/*
 * The container clock is frozen at the real current moment so that the
 * plugin's own `current_time()` based writes (the S2S postback) land in
 * the same day bucket as the clock-driven queries.
 */
$clock = new \MCR\Support\FixedClock( gmdate( 'Y-m-d H:i:s' ), 'UTC' );

\MCR\Container::instance()->set_clock( $clock );

/**
 * Exposes the protected gateway internals to the test.
 */
class Review_Manual_Gateway extends \MCR\Gateways\Manual {

	/**
	 * Calls the protected refund helper.
	 *
	 * @param array  $order  Order row.
	 * @param string $reason Reason.
	 * @return array|\WP_Error
	 */
	public function call_refund_order( array $order, $reason = '' ) {
		return $this->refund_order( $order, $reason );
	}

	/**
	 * Calls the protected credit helper.
	 *
	 * @param array  $order  Order row.
	 * @param string $reason Reason.
	 * @return array|\WP_Error
	 */
	public function call_credit_order( array $order, $reason = '' ) {
		return $this->credit_order( $order, $reason );
	}
}

/**
 * A provider that confirms every completion server-side.
 *
 * It stands in for a client-SDK provider: the only difference to the
 * bundled GenericS2S provider is that it confirms the completion itself
 * instead of waiting for a network postback. It is used to prove that a
 * genuinely UNVERIFIED session can still be verified - the "already
 * verified" answer must only ever apply to a session that already
 * reached a terminal state.
 */
class Review_Confirming_Provider extends \MCR\AdProviders\Provider {

	/**
	 * Provider slug.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'review_confirming';
	}

	/**
	 * Provider title.
	 *
	 * @return string
	 */
	public function get_title() {
		return 'Review confirming provider';
	}

	/**
	 * Always configured.
	 *
	 * @return bool
	 */
	public function is_configured() {
		return true;
	}

	/**
	 * One rewarded unit.
	 *
	 * @return array
	 */
	public function get_ad_units() {
		return array(
			array(
				'id'     => 'rewarded_video',
				'title'  => 'Rewarded video',
				'type'   => 'rewarded_video',
				'params' => array(),
			),
		);
	}

	/**
	 * Issues the SDK data for one watch attempt.
	 *
	 * @param array $args Session args.
	 * @return array
	 */
	public function create_session( array $args ) {
		return array(
			'ad_unit'          => $this->get_ad_units()[0],
			'sdk_params'       => array(),
			'reward_coins'     => isset( $args['reward_coins'] ) ? (int) $args['reward_coins'] : 0,
			'signature_header' => 'X-MCR-Signature',
			'timestamp_header' => 'X-MCR-Timestamp',
		);
	}

	/**
	 * Confirms every completion.
	 *
	 * @param array $payload Completion payload.
	 * @return true
	 */
	public function verify_completion( array $payload ) {
		return true;
	}

	/**
	 * No settings.
	 *
	 * @return array
	 */
	public function get_settings_fields() {
		return array();
	}
}

echo "MCR Correctness & Business-Logic Review\n";
echo "=========================================\n\n";

/* =========================================================================
 * 3. Ledger: credit, debit, the SQL-side guard and idempotency
 * ======================================================================== */

mcr_group( 'Ledger: the debit guard is enforced in SQL, not in PHP' );

$ledger = new \MCR\Ledger();

$ledger->credit( 10, 100, 'checkin', 'Daily reward', array( 'idempotency_key' => 'k-1' ) );

assertSame( 100, $ledger->get_balance( 10 ), 'a credit moves the balance' );
assertSame( 100, $ledger->get_stats( 10 )['lifetime_earned'], 'a credit counts as earned' );

$wpdb->queries = array();

$refused = $ledger->debit( 10, 500, 'spend', 'Too much', array( 'idempotency_key' => 'k-2' ) );

assertTrue( is_wp_error( $refused ), 'an over-budget debit is refused' );
assertSame( 'mcr_insufficient_funds', $refused->get_error_code(), 'the refusal names insufficient funds' );
assertSame( 100, $ledger->get_balance( 10 ), 'the refused debit leaves the balance alone' );
assertSame( 1, count( $wpdb->transactions ), 'the refused debit leaves no ledger row' );

$guard_sql = '';

foreach ( $wpdb->queries as $sql ) {
	if ( false !== strpos( $sql, 'UPDATE wp_mcr_balances' ) ) {
		$guard_sql = $sql;
	}
}

assertTrue( false !== strpos( $guard_sql, 'balance >=' ), 'the balance guard lives in the SQL WHERE clause' );
assertTrue( false !== strpos( $guard_sql, 'lifetime_spent' ), 'a plain debit increments lifetime_spent' );
assertTrue( false === strpos( $guard_sql, 'lifetime_earned' ), 'a plain debit does not touch lifetime_earned' );

mcr_group( 'Ledger: idempotency and balance_after' );

$again = $ledger->credit( 10, 100, 'checkin', 'Daily reward', array( 'idempotency_key' => 'k-1' ) );

assertTrue( ! empty( $again['duplicate'] ), 'the same idempotency key is a duplicate' );
assertSame( 100, $ledger->get_balance( 10 ), 'a duplicate credit does not move the balance' );
assertSame( 1, count( $wpdb->transactions ), 'a duplicate credit writes no second row' );

$txn = $wpdb->transaction_by_key( 'k-1' );

assertSame( 100, (int) $txn['balance_after'], 'balance_after equals the resulting balance' );

$debit = $ledger->debit( 10, 40, 'spend', 'Spend', array( 'idempotency_key' => 'k-3' ) );

assertSame( 60, $debit['balance_after'], 'the balance after the debit is exact' );

$txn = $wpdb->transaction_by_key( 'k-3' );

assertSame( 60, (int) $txn['balance_after'], 'the debit row snapshots the resulting balance' );

/* =========================================================================
 * 4. Ledger: reversal semantics (must NOT be a spend)
 * ======================================================================== */

mcr_group( 'Ledger: a reversal is not a spend and restores the earned total' );

$ledger->credit( 11, 40, 'checkin', 'Daily reward', array( 'idempotency_key' => 'rev-1' ) );

$wpdb->queries = array();

$ledger->debit( 11, 40, 'reversal', 'Reversal', array( 'idempotency_key' => 'rev-2', 'reversal' => true ) );

$stats = $ledger->get_stats( 11 );

assertSame( 0, $stats['balance'], 'the reversal removed the coins' );
assertSame( 0, $stats['lifetime_earned'], 'the reversal gave the earned total back' );
assertSame( 0, $stats['lifetime_spent'], 'the reversal is NOT counted as a spend' );

$reversal_sql = '';

foreach ( $wpdb->queries as $sql ) {
	if ( false !== strpos( $sql, 'UPDATE wp_mcr_balances' ) ) {
		$reversal_sql = $sql;
	}
}

assertFalse( false !== strpos( $reversal_sql, 'lifetime_spent' ), 'the reversal SQL never touches lifetime_spent' );

/* =========================================================================
 * 5. FINDING: a refund is booked as a spend
 * ======================================================================== */

mcr_group( 'FINDING: a refund must not be booked as a spend' );

$payment_id = $wpdb->seed_payment(
	array(
		'user_id'       => 12,
		'package_id'    => 'starter',
		'gateway'       => 'manual',
		'order_id'      => 'mcr_refund_order_1',
		'provider_tx_id'=> 'tx_refund_1',
		'amount'        => '100.00',
		'currency'      => 'USD',
		'coins_total'   => 100,
		'status'        => 'pending',
		'created_at'    => $wpdb->now_gmt(),
		'paid_at'       => '0000-00-00 00:00:00',
		'expires_at'    => gmdate( 'Y-m-d H:i:s', time() + 3600 ),
	)
);

$gateway = new Review_Manual_Gateway( array() );

$row = $wpdb->payments[ $payment_id ];

$credit = $gateway->call_credit_order( $row, 'Coin package purchase' );

assertFalse( is_wp_error( $credit ), 'the purchase is credited' );
assertSame( 100, $ledger->get_balance( 12 ), 'the purchased coins are in the balance' );

$stats = $ledger->get_stats( 12 );

assertSame( 100, $stats['lifetime_earned'], 'a purchase counts as earned' );
assertSame( 100, $stats['purchased_total'], 'a purchase counts as purchased' );
assertSame( 0, $stats['lifetime_spent'], 'a purchase is not a spend' );

$row            = $wpdb->payments[ $payment_id ];
$row['status']  = 'credited';

$refund = $gateway->call_refund_order( $row, 'Payment refunded' );

assertFalse( is_wp_error( $refund ), 'the refund is applied' );
assertSame( 0, $ledger->get_balance( 12 ), 'the refund removed the coins' );

$stats = $ledger->get_stats( 12 );

assertSame( 0, $stats['lifetime_spent'], 'a refund is NOT a spend (lifetime_spent must stay 0)' );
assertSame( 0, $stats['lifetime_earned'], 'a refund gives the earned total back' );
assertSame( 0, $stats['purchased_total'], 'a refund removes the purchased total' );

/* =========================================================================
 * 6. FINDING: a rejected debit leaves a phantom ledger row without a
 *    transactional engine
 * ======================================================================== */

mcr_group( 'FINDING: a rejected debit must not leave a ledger row (no transactions)' );

$wpdb->engine = 'MyISAM';

$myisam = new \MCR\Ledger();

$myisam->credit( 13, 100, 'admin_adjust', 'Seed', array( 'idempotency_key' => 'my-1' ) );

$before = count( $wpdb->transactions );

$refused = $myisam->debit( 13, 500, 'spend', 'Too much', array( 'idempotency_key' => 'my-2' ) );

assertTrue( is_wp_error( $refused ), 'the over-budget debit is refused' );
assertSame( 100, $myisam->get_balance( 13 ), 'the balance is untouched' );
assertSame( $before, count( $wpdb->transactions ), 'the refused debit leaves NO ledger row behind' );

$myisam->reconcile_user( 13 );

assertSame( 100, $myisam->get_balance( 13 ), 'the repair tool keeps the balance correct' );

$wpdb->engine = 'InnoDB';

/* =========================================================================
 * 7. FINDING: a filtered credit amount can drive the balance negative
 * ======================================================================== */

mcr_group( 'FINDING: the balance can never go negative' );

$GLOBALS['mcr_filters']['mcr_credit_amount'] = array(
	function () {
		return -50;
	},
);

$ledger->credit( 14, 100, 'checkin', 'Filtered', array( 'idempotency_key' => 'neg-1' ) );

$GLOBALS['mcr_filters']['mcr_credit_amount'] = array();

assertTrue( $ledger->get_balance( 14 ) >= 0, 'a filtered credit amount can never make the balance negative' );

/* =========================================================================
 * 8. FINDING: reconcile_user only repairs the balance
 * ======================================================================== */

mcr_group( 'FINDING: the repair tool also repairs the lifetime counters' );

$ledger->credit( 15, 100, 'checkin', 'Seed', array( 'idempotency_key' => 'rec-1' ) );

$wpdb->balances[15]['lifetime_earned'] = 5;

$ledger->reconcile_user( 15 );

$stats = $ledger->get_stats( 15 );

assertSame( 100, $stats['balance'], 'the balance is repaired' );
assertSame( 100, $stats['lifetime_earned'], 'the earned counter is repaired too' );

/* =========================================================================
 * 9. FINDING: a failed check-in grant must not block the day
 * ======================================================================== */

mcr_group( 'FINDING: a failed check-in must not permanently block the user' );

$user_id   = 20;
$today     = $clock->today_key();

$wpdb->fail_transaction_insert = true;

$claim = \MCR\Rewards\CheckIn::instance()->claim( $user_id );

$wpdb->fail_transaction_insert = false;

assertTrue( is_wp_error( $claim ), 'the failed grant is reported as an error' );

$rows = 0;

foreach ( $wpdb->checkins as $row ) {
	if ( (int) $row['user_id'] === $user_id && (string) $row['checkin_date'] === $today ) {
		++$rows;
	}
}

assertSame( 0, $rows, 'no claim row survives a failed grant: the user can retry today' );

$claim2 = \MCR\Rewards\CheckIn::instance()->claim( $user_id );

assertFalse(
	is_wp_error( $claim2 ) && 'mcr_already_claimed' === $claim2->get_error_code(),
	'a failed grant does not turn into "already claimed"'
);

/* =========================================================================
 * 10. FINDING: the advertised streak bonus differs from the granted bonus
 * ======================================================================== */

mcr_group( 'FINDING: the advertised check-in bonus equals the granted bonus' );

$bonus_user = 21;
$yesterday  = $clock->now()->modify( '-1 day' )->format( 'Y-m-d' );

$wpdb->seed_checkin(
	array(
		'user_id'      => $bonus_user,
		'checkin_date' => $yesterday,
		'streak_count' => 1,
		'base_amount'  => 10,
		'bonus_amount' => 0,
		'total_amount' => 10,
		'transaction_id'=> 0,
		'ip'           => '',
		'created_at'   => $wpdb->now_gmt(),
	)
);

$status = \MCR\Rewards\CheckIn::instance()->status( $bonus_user );

$policy = new \MCR\Rewards\CheckInPolicy(
	$clock,
	$ledger
);

$evaluation = $policy->evaluate(
	$bonus_user,
	array(
		'base_amount'   => (int) \MCR\Settings::get( 'checkin.base_amount', 10 ),
		'bonuses'       => \MCR\Settings::get( 'checkin.bonuses', array() ),
		'max_bonus'     => (int) \MCR\Settings::get( 'checkin.max_bonus', 0 ),
		'previous_keys' => array( $yesterday ),
	)
);

assertSame( (int) $evaluation['streak'], (int) $status['streak'], 'the status and the policy agree on the streak' );
assertSame(
	(int) $evaluation['bonus'],
	(int) $status['bonus_amount'],
	'the bonus the UI advertises is the bonus the claim pays'
);
assertSame(
	(int) $evaluation['total'],
	(int) $status['total_amount'],
	'the total the UI advertises is the total the claim pays'
);

/* =========================================================================
 * 10b. Adversarial: a double click and a replayed key
 * ======================================================================== */

mcr_group( 'Adversarial: a double click and a replayed key never pay twice' );

$double_user = 22;

$first_claim = \MCR\Rewards\CheckIn::instance()->claim( $double_user );

assertFalse( is_wp_error( $first_claim ), 'the first check-in succeeds' );

$balance_after_first = $ledger->get_balance( $double_user );

assertTrue( $balance_after_first > 0, 'the first check-in paid coins' );

$second_claim = \MCR\Rewards\CheckIn::instance()->claim( $double_user );

assertTrue(
	is_wp_error( $second_claim ) && 'mcr_already_claimed' === $second_claim->get_error_code(),
	'the second click is refused as "already claimed"'
);
assertSame( $balance_after_first, $ledger->get_balance( $double_user ), 'the second click pays nothing' );

$replay = $ledger->credit( 50, 10, 'checkin', 'Reward', array( 'idempotency_key' => 'replay-1' ) );

assertFalse( is_wp_error( $replay ), 'the first replayed credit succeeds' );

$replay2 = $ledger->credit( 50, 999, 'checkin', 'Reward', array( 'idempotency_key' => 'replay-1' ) );

assertTrue( ! empty( $replay2['duplicate'] ), 'the replayed key is a duplicate' );
assertSame( 10, $ledger->get_balance( 50 ), 'a replayed key with a different amount credits nothing' );
assertSame( 10, (int) $replay2['amount'], 'the duplicate reports the amount of the original transaction' );
assertSame( 1, count( $wpdb->transaction_by_key( 'replay-1' ) ? array( 1 ) : array() ), 'exactly one ledger row exists for the replayed key' );

/* =========================================================================
 * 11. Streak logic: continuity, resets, timezones, DST, leap day
 * ======================================================================== */
mcr_group( 'Streak: continuity, resets and edge cases' );

assertSame( 1, \MCR\Rewards\StreakCalculator::calculateStreakDay( '2026-03-05', array() ), 'a first claim is streak day 1' );
assertSame( 2, \MCR\Rewards\StreakCalculator::calculateStreakDay( '2026-03-05', array( '2026-03-04' ) ), 'yesterday continues the streak' );
assertSame(
	4,
	\MCR\Rewards\StreakCalculator::calculateStreakDay( '2026-03-05', array( '2026-03-04', '2026-03-03', '2026-03-02' ) ),
	'three consecutive days make streak day 4'
);
assertSame(
	4,
	\MCR\Rewards\StreakCalculator::calculateStreakDay( '2026-03-05', array( '2026-03-02', '2026-03-04', '2026-03-03' ) ),
	'the streak does not depend on the order of the keys'
);
assertSame( 1, \MCR\Rewards\StreakCalculator::calculateStreakDay( '2026-03-05', array( '2026-03-03' ) ), 'a missed day resets the streak' );
assertSame( 1, \MCR\Rewards\StreakCalculator::calculateStreakDay( '2026-03-05', array( '2026-02-01' ) ), 'an old claim does not extend the streak' );
assertSame(
	2,
	\MCR\Rewards\StreakCalculator::calculateStreakDay( '2026-03-05', array( '2026-03-04', '2026-03-04' ) ),
	'a duplicated day key is counted once'
);
assertSame(
	2,
	\MCR\Rewards\StreakCalculator::calculateStreakDay( '2026-03-05', array( '2026-03-06', '2026-03-04' ) ),
	'a day key in the future is ignored'
);
assertSame(
	2,
	\MCR\Rewards\StreakCalculator::calculateStreakDay( '2026-03-05', array( '2026-03-04', 'not-a-date', '2026-02-30' ) ),
	'invalid day keys are ignored'
);
assertSame( 2, \MCR\Rewards\StreakCalculator::calculateStreakDay( '2024-03-01', array( '2024-02-29' ) ), 'a leap day continues the streak' );
assertSame( 2, \MCR\Rewards\StreakCalculator::calculateStreakDay( '2024-02-29', array( '2024-02-28' ) ), 'the day before a leap day continues the streak' );
assertSame( 2, \MCR\Rewards\StreakCalculator::calculateStreakDay( '2026-03-01', array( '2026-02-28' ) ), 'a month boundary continues the streak' );
assertSame( 1, \MCR\Rewards\StreakCalculator::calculateStreakDay( '', array( '2026-03-04' ) ), 'an empty day key is streak day 1' );

mcr_group( 'Streak: the day bucket is the SITE timezone, not UTC' );

$new_york = new \MCR\Support\FixedClock( '2026-03-05 20:00:00', 'America/New_York' );
$utc       = new \MCR\Support\FixedClock( '2026-03-05 20:00:00', 'UTC' );

assertSame( '2026-03-05', $new_york->today_key(), 'the site timezone decides the day bucket' );
assertSame( '2026-03-05', $utc->today_key(), 'UTC agrees for the same wall clock time' );

$tokyo = new \MCR\Support\FixedClock( '2026-03-05 20:00:00', 'Asia/Tokyo' );

assertSame( '2026-03-05', $tokyo->today_key(), 'a far-away timezone still uses its own day bucket' );

$edge = new \MCR\Support\FixedClock( '2026-03-05 23:59:59', 'America/New_York' );

assertSame( '2026-03-05', $edge->today_key(), 'the last second of the site day is still that day' );
assertSame( 1, $edge->seconds_until_next_day(), 'one second is left in the day' );

$dst = new \MCR\Support\FixedClock( '2026-03-08 12:00:00', 'America/New_York' );

assertSame( '2026-03-08', $dst->today_key(), 'the DST switch day is a normal day' );
assertSame(
	2,
	\MCR\Rewards\StreakCalculator::calculateStreakDay( '2026-03-09', array( '2026-03-08' ) ),
	'a streak continues across a DST transition'
);

mcr_group( 'Streak: bonus escalation and its cap' );

$bonuses = array( 3 => 5, 7 => 15, 30 => 50 );

assertSame( 0, \MCR\Rewards\StreakCalculator::calculateBonus( 1, $bonuses, 100 ), 'no bonus before the first threshold' );
assertSame( 5, \MCR\Rewards\StreakCalculator::calculateBonus( 3, $bonuses, 100 ), 'the 3-day tier pays 5' );
assertSame( 15, \MCR\Rewards\StreakCalculator::calculateBonus( 7, $bonuses, 100 ), 'the 7-day tier pays 15' );
assertSame( 50, \MCR\Rewards\StreakCalculator::calculateBonus( 30, $bonuses, 100 ), 'the 30-day tier pays 50' );
assertSame( 20, \MCR\Rewards\StreakCalculator::calculateBonus( 30, $bonuses, 20 ), 'the bonus cap is applied' );
assertSame( 0, \MCR\Rewards\StreakCalculator::calculateBonus( 0, $bonuses, 100 ), 'streak day 0 pays nothing' );
assertSame( 0, \MCR\Rewards\StreakCalculator::calculateBonus( 5, array(), 100 ), 'no bonuses configured pays nothing' );

/* =========================================================================
 * 12. Ad cap
 * ======================================================================== */

mcr_group( 'Ad cap: the cap is counted on verified_at, per user per day' );

\MCR\Settings::update( array( 'ads.daily_limit' => 2 ) );
\MCR\Settings::flush_cache();

$now_gmt = $wpdb->now_gmt();

$wpdb->seed_ad_session(
	array(
		'user_id'         => 30,
		'ad_id'           => 'rewarded_video',
		'provider'        => 'generic_s2s',
		'session_token'   => str_repeat( 'a', 64 ),
		'completion_token'=> 'tx-a',
		'day_key'         => $clock->now()->modify( '-1 day' )->format( 'Y-m-d' ),
		'status'          => 'verified',
		'coins'           => 5,
		'ip'              => '',
		'created_at'      => $now_gmt,
		'expires_at'      => gmdate( 'Y-m-d H:i:s', time() + 3600 ),
		'verified_at'     => $now_gmt,
	)
);

$cap = \MCR\Rewards\AdSessions::instance()->cap_guard( 30 );

assertFalse( is_wp_error( $cap ), 'one verified ad is below the cap of 2' );

$wpdb->seed_ad_session(
	array(
		'user_id'         => 30,
		'ad_id'           => 'rewarded_video',
		'provider'        => 'generic_s2s',
		'session_token'   => str_repeat( 'b', 64 ),
		'completion_token'=> 'tx-b',
		// The session was STARTED yesterday but VERIFIED today: it must count.
		'day_key'         => $clock->now()->modify( '-1 day' )->format( 'Y-m-d' ),
		'status'          => 'verified',
		'coins'           => 5,
		'ip'              => '',
		'created_at'      => $now_gmt,
		'expires_at'      => gmdate( 'Y-m-d H:i:s', time() + 3600 ),
		'verified_at'     => $now_gmt,
	)
);

$cap = \MCR\Rewards\AdSessions::instance()->cap_guard( 30 );

assertTrue( is_wp_error( $cap ), 'the cap counts on verified_at, not on the session day bucket' );
assertSame( 'mcr_daily_limit_reached', $cap->get_error_code(), 'the cap error is named' );

$wpdb->seed_ad_session(
	array(
		'user_id'         => 31,
		'ad_id'           => 'rewarded_video',
		'provider'        => 'generic_s2s',
		'session_token'   => str_repeat( 'c', 64 ),
		'completion_token'=> 'tx-c',
		// STARTED today but VERIFIED yesterday: it must NOT count today.
		'day_key'         => $clock->today_key(),
		'status'          => 'verified',
		'coins'           => 5,
		'ip'              => '',
		'created_at'      => $now_gmt,
		'expires_at'      => gmdate( 'Y-m-d H:i:s', time() + 3600 ),
		'verified_at'     => gmdate( 'Y-m-d H:i:s', time() - 86400 ),
	)
);

$cap = \MCR\Rewards\AdSessions::instance()->cap_guard( 31 );

assertFalse( is_wp_error( $cap ), 'an ad verified yesterday does not count towards today' );

$stats = \MCR\Rewards\AdSessions::instance()->stats( 31 );

assertSame( 0, $stats['today'], 'the ad stats count today only' );
assertTrue( $stats['remaining'] >= 0, 'the remaining ad allowance is never negative' );

mcr_group( 'Ad cap: the S2S postback path counts towards the daily cap (regression)' );

\MCR\Settings::update(
	array(
		'ads.daily_limit'        => 1,
		'ads.provider'            => 'generic_s2s',
		'ads.providers.generic_s2s' => array( 'postback_secret' => 'review-secret' ),
	)
);
\MCR\Settings::flush_cache();

\MCR\AdProviders\Registry::reset();

$postback_user   = 32;
$session_token   = str_repeat( 'd', 64 );
$raw             = wp_json_encode(
	array(
		'session_token' => $session_token,
		'transaction_id'=> 'tx-postback-1',
	)
);
$signature       = hash_hmac( 'sha256', $raw, 'review-secret' );

$wpdb->seed_ad_session(
	array(
		'user_id'         => $postback_user,
		'ad_id'           => 'rewarded_video',
		'provider'        => 'generic_s2s',
		'session_token'   => $session_token,
		'completion_token'=> '',
		'day_key'         => $clock->today_key(),
		'status'          => 'pending',
		'coins'           => 5,
		'ip'              => '',
		'created_at'      => $now_gmt,
		'expires_at'      => gmdate( 'Y-m-d H:i:s', time() + 3600 ),
		'verified_at'     => '1970-01-01 00:00:00',
	)
);

$provider = new \MCR\AdProviders\GenericS2S( array( 'postback_secret' => 'review-secret' ) );

$response = $provider->handle_postback(
	new WP_REST_Request(
		$raw,
		array(
			'x-mcr-signature' => $signature,
			'x-mcr-timestamp' => (string) time(),
		)
	)
);

assertSame( 200, $response->get_status(), 'the signed postback is accepted' );
assertSame( 5, $ledger->get_balance( $postback_user ), 'the postback credited the coins' );

$session_row = null;

foreach ( $wpdb->ad_sessions as $row ) {
	if ( (string) $row['session_token'] === $session_token ) {
		$session_row = $row;
	}
}

assertSame( 'credited', (string) $session_row['status'], 'the postback marked the session as credited' );

$cap = \MCR\Rewards\AdSessions::instance()->cap_guard( $postback_user );

assertTrue(
	is_wp_error( $cap ),
	'a rewarded ad credited through the S2S postback counts towards the daily cap'
);

$stats = \MCR\Rewards\AdSessions::instance()->stats( $postback_user );

assertSame( 1, $stats['today'], 'the ad stats count the credited S2S ad' );
assertSame( 0, $stats['remaining'], 'the remaining ad allowance drops to zero' );

/*
 * The client side "my ad is done" call arrives AFTER the network has
 * already confirmed the ad and the coins are in the account.
 *
 * "verified" (client path) and "credited" (server-to-server path) are
 * BOTH terminal states: the ad was watched and it was paid for. So the
 * client call reports "already verified" - it must never pay the reward a
 * second time, and it must not pretend to have granted anything either.
 */
\MCR\Settings::update( array( 'ads.daily_limit' => 5 ) );
\MCR\Settings::flush_cache();

$verify = \MCR\Rewards\AdSessions::instance()->verify(
	$postback_user,
	array(
		'session_token'    => $session_token,
		'completion_token' => 'tx-postback-1',
	)
);

assertSame(
	'mcr_already_verified',
	is_wp_error( $verify ) ? $verify->get_error_code() : '',
	'the client verification reports "already verified" once the network confirmed the ad (the status vocabulary must match)'
);

assertSame(
	5,
	$ledger->get_balance( $postback_user ),
	'the client re-verification never pays the reward a second time'
);

/*
 * The mirror image of the assertion above: a session that has NOT reached
 * a terminal state yet must still be verifiable, otherwise the client
 * path would be dead and every rewarded ad would be lost.
 */
mcr_group( 'Ad rewards: a session that is not terminal yet can still be verified' );

$GLOBALS['mcr_filters']['mcr_register_ad_providers'] = array(
	function ( $instances ) {
		$instances['review_confirming'] = new Review_Confirming_Provider( array() );

		return $instances;
	},
);

\MCR\AdProviders\Registry::reset();

\MCR\Settings::update(
	array(
		'ads.provider'       => 'review_confirming',
		'ads.coins_per_ad'   => 5,
		'ads.daily_limit'    => 5,
		'ads.global_daily_limit' => 0,
		'ads.cooldown_seconds'   => 0,
	)
);

\MCR\Settings::flush_cache();

$client_user = 33;

$client_session = \MCR\Rewards\AdSessions::instance()->start( $client_user, array( 'ad_id' => 'rewarded_video' ) );

assertFalse(
	is_wp_error( $client_session ),
	'the ad session starts with a provider that confirms completions'
);

$client_verify = \MCR\Rewards\AdSessions::instance()->verify(
	$client_user,
	array(
		'session_token'    => $client_session['session_token'],
		'completion_token' => 'completion-1',
	)
);

assertFalse(
	is_wp_error( $client_verify ),
	'a genuinely unverified session is verified and paid out'
);

assertSame(
	5,
	$ledger->get_balance( $client_user ),
	'the verified ad pays the reward exactly once'
);

$client_status = '';

foreach ( $wpdb->ad_sessions as $row ) {
	if ( (string) $row['session_token'] === (string) $client_session['session_token'] ) {
		$client_status = (string) $row['status'];
	}
}

assertSame( 'verified', $client_status, 'the verified session reaches the "verified" terminal state' );

$client_reverify = \MCR\Rewards\AdSessions::instance()->verify(
	$client_user,
	array(
		'session_token'    => $client_session['session_token'],
		'completion_token' => 'completion-1',
	)
);

assertSame(
	'mcr_already_verified',
	is_wp_error( $client_reverify ) ? $client_reverify->get_error_code() : '',
	'a second verification of the same session reports "already verified"'
);

assertSame(
	5,
	$ledger->get_balance( $client_user ),
	'the second verification never pays the reward a second time'
);

/*
 * Restore the bundled provider for the sections that follow.
 */
$GLOBALS['mcr_filters']['mcr_register_ad_providers'] = array();

\MCR\AdProviders\Registry::reset();

\MCR\Settings::update( array( 'ads.provider' => 'generic_s2s' ) );

\MCR\Settings::flush_cache();

/* =========================================================================
 * 13. Package maths
 * ======================================================================== */

mcr_group( 'Package maths: the coin total is exact' );

assertSame( 110, \MCR\Payments\PackageCalculator::calculateTotal( 100, 10, 0 ), 'a 10% bonus adds a tenth' );
assertSame( 105, \MCR\Payments\PackageCalculator::calculateTotal( 100, 10, 5 ), 'the bonus cap is applied to the bonus' );
assertSame( 116, \MCR\Payments\PackageCalculator::calculateTotal( 101, 15, 0 ), 'a fractional bonus never creates value' );
assertSame( 105, \MCR\Payments\PackageCalculator::calculateTotal( 100, 15, 5 ), 'the bonus cap applies to the bonus, not to the total' );
assertSame( 0, \MCR\Payments\PackageCalculator::calculateTotal( 0, 50, 0 ), 'a package without coins pays nothing' );
assertSame( 200, \MCR\Payments\PackageCalculator::calculateTotal( 100, 150, 0 ), 'a bonus above 100% is clamped' );
assertSame( 100, \MCR\Payments\PackageCalculator::calculateTotal( 100, -10, 0 ), 'a negative bonus is ignored' );
assertSame( 0, \MCR\Payments\PackageCalculator::calculateTotal( -5, 10, 0 ), 'a negative coin amount is ignored' );
assertSame( 499, \MCR\Payments\PackageCalculator::toMinorUnits( '4.99' ), 'a price becomes minor units' );
assertSame( 30, \MCR\Payments\PackageCalculator::toMinorUnits( 0.1 + 0.2 ), 'float drift does not change the minor units' );
assertSame( 0, \MCR\Payments\PackageCalculator::toMinorUnits( 'not a price' ), 'an invalid price is zero' );

/* =========================================================================
 * 14. FINDING: the settings pages destroy the configuration
 * ======================================================================== */

mcr_group( 'FINDING: saving a settings page keeps the rest of the configuration' );

\MCR\Settings::update(
	array(
		'packages'          => array(
			'starter' => array(
				'id'            => 'starter',
				'title'         => 'Starter',
				'coins'         => 100,
				'price'         => 4.99,
				'currency'      => 'USD',
				'active'        => 'on',
				'bonus_percent' => 0,
				'max_bonus'     => 0,
				'limit'         => 0,
				'purchased'     => 0,
				'sort_order'    => 1,
			),
		),
		'ads.provider'      => 'generic_s2s',
		'ads.global_daily_limit' => 50,
		'checkin.max_bonus' => 100,
		'purchase.min_amount' => 1,
	)
);
\MCR\Settings::flush_cache();

/*
 * Exactly the keys the Settings screen renders (see Admin\SettingsPage).
 */
$settings_form_post = array(
	'features.checkin_enabled'    => 'on',
	'checkin.base_amount'          => '10',
	'checkin.bonuses'              => '{"3":5,"7":15,"30":50}',
	'checkin.streak_enabled'       => 'on',
	'features.ads_enabled'         => 'on',
	'ads.coins_per_ad'             => '5',
	'ads.daily_limit'              => '10',
	'ads.cooldown_seconds'         => '300',
	'features.purchase_enabled'    => 'on',
	'purchase.currency'            => 'USD',
	'purchase.gateway'             => 'manual',
	'purchase.auto_credit'         => 'on',
	'template.layout_checkin'      => 'default',
	'template.layout_dashboard'    => 'default',
	'template.layout_ads'          => 'default',
	'template.layout_buy_coins'    => 'default',
	'template.layout_transactions' => 'default',
	'template.layout_balance'      => 'default',
	'template.columns'             => '3',
	'template.show_balance_summary'=> 'on',
	'template.show_progress_bars'  => 'on',
	'template.show_streak_calendar'=> 'on',
	'template.show_sidebar'        => 'on',
	'template.show_breadcrumb'     => 'on',
	'pages.checkin'                => '0',
	'pages.dashboard'              => '0',
	'pages.ads'                    => '0',
	'pages.buy_coins'              => '0',
	'pages.transactions'           => '0',
	'security.log_ip'              => 'on',
	'security.log_user_agent'      => 'off',
	'security.rate_limit_checkin'  => '10',
	'security.rate_limit_ads'      => '20',
	'notify.toast_on_change'       => 'on',
	'notify.show_login_prompt'     => 'on',
);

$clean = \MCR\Settings::sanitize( $settings_form_post );

assertTrue( isset( $clean['packages'] ) && ! empty( $clean['packages'] ), 'the coin packages survive a settings save' );
assertSame( 'generic_s2s', isset( $clean['ads.provider'] ) ? $clean['ads.provider'] : null, 'the ad provider survives a settings save' );
assertSame( 50, isset( $clean['ads.global_daily_limit'] ) ? $clean['ads.global_daily_limit'] : null, 'the global ad limit survives a settings save' );
assertSame( 100, isset( $clean['checkin.max_bonus'] ) ? $clean['checkin.max_bonus'] : null, 'the streak bonus cap survives a settings save' );
assertSame( 1, isset( $clean['purchase.min_amount'] ) ? $clean['purchase.min_amount'] : null, 'the minimum purchase amount survives a settings save' );

/*
 * End to end: this is exactly what wp-admin/options.php does with the
 * registered sanitize callback of the 'mcr_settings' option.
 */
update_option( 'mcr_settings', $clean );
\MCR\Settings::flush_cache();

assertCount( 1, \MCR\Payments\Packages::get_all(), 'the packages are still there after a settings save' );
assertSame( 'generic_s2s', \MCR\Settings::get( 'ads.provider', '' ), 'the ad provider is still configured after a settings save' );

mcr_group( 'FINDING: the packages page can actually save a package' );

$packages_form_post = array(
	'packages' => wp_json_encode(
		array(
			'starter' => array(
				'id'            => 'starter',
				'title'         => 'Starter',
				'coins'         => 100,
				'price'         => 4.99,
				'currency'      => 'USD',
				'active'        => 'on',
				'bonus_percent' => 50,
				'max_bonus'     => 10,
				'limit'         => 0,
				'purchased'     => 0,
				'sort_order'    => 1,
			),
		)
	),
);

$clean = \MCR\Settings::sanitize( $packages_form_post );

assertTrue( ! empty( $clean['packages'] ), 'the JSON posted by the packages page is decoded' );
assertSame( 100, isset( $clean['packages']['starter']['coins'] ) ? $clean['packages']['starter']['coins'] : null, 'the package coins survive a packages save' );
assertSame(
	10,
	isset( $clean['packages']['starter']['max_bonus'] ) ? $clean['packages']['starter']['max_bonus'] : null,
	'the per-package bonus cap survives a packages save'
);

$clean = \MCR\Settings::sanitize( array( 'checkin.bonuses' => '{"3":5,"7":15,"30":50}' ) );

assertSame( 5, isset( $clean['checkin.bonuses'][3] ) ? $clean['checkin.bonuses'][3] : null, 'the JSON streak bonuses posted by the settings page are decoded' );

update_option( 'mcr_settings', $clean );
\MCR\Settings::flush_cache();

assertCount( 1, \MCR\Payments\Packages::get_all(), 'the package is saved by the packages page' );

/* =========================================================================
 * 15. FINDING: the package purchase limit is never enforced
 * ======================================================================== */

mcr_group( 'FINDING: a package with a stock limit stops selling after the limit' );

\MCR\Settings::update(
	array(
		'packages' => array(
			'limited' => array(
				'id'            => 'limited',
				'title'         => 'Limited',
				'coins'         => 100,
				'price'         => 4.99,
				'currency'      => 'USD',
				'active'        => 'on',
				'bonus_percent' => 0,
				'max_bonus'     => 0,
				'limit'         => 1,
				'purchased'     => 0,
				'sort_order'    => 1,
			),
		),
		'purchase.gateway' => 'manual',
	)
);
\MCR\Settings::flush_cache();

$package = \MCR\Payments\Packages::get( 'limited' );

assertFalse( null === $package, 'the package exists' );
assertSame( 100, (int) $package['coins_total'], 'the package delivers 100 coins' );
assertSame( 499, (int) $package['price_minor'], 'the package costs 4.99' );
assertTrue( \MCR\Payments\Packages::is_purchasable( $package ), 'the package is purchasable before the first sale' );

$order = \MCR\Payments\Orders::instance()->create( 40, $package );

assertFalse( is_wp_error( $order ), 'the order is created' );

$order_row = $wpdb->get_row( "SELECT * FROM wp_mcr_payments WHERE order_id = '" . $order['order_id'] . "' LIMIT 1", 'ARRAY_A' );

assertFalse( null === $order_row, 'the payment row exists' );

$GLOBALS['mcr_caps']['mcr_manage_coins'] = true;

$_REQUEST['_mcr_nonce'] = wp_create_nonce( \MCR\Gateways\Manual::nonce_action( (int) $order_row['id'] ) );

$manual = new \MCR\Gateways\Manual( array() );

$paid = $manual->admin_mark_paid( (int) $order_row['id'], 'Bank transfer received' );

assertFalse( is_wp_error( $paid ), 'the admin approves the payment' );
assertSame( 100, $ledger->get_balance( 40 ), 'the buyer received exactly the package coins' );

$sold = \MCR\Payments\Packages::get( 'limited' );

assertSame( 1, (int) $sold['purchased'], 'the package counts the sale' );
assertFalse( \MCR\Payments\Packages::is_purchasable( $sold ), 'the package is sold out after the limit is reached' );

/* =========================================================================
 * 16. Payments: marked paid exactly once, stable idempotency key
 * ======================================================================== */

mcr_group( 'Payments: an order is marked paid exactly once' );

$payment_id = $wpdb->seed_payment(
	array(
		'user_id'        => 41,
		'package_id'     => 'limited',
		'gateway'        => 'manual',
		'order_id'       => 'mcr_pay_once_1',
		'provider_tx_id' => 'tx_pay_once_1',
		'amount'         => '4.99',
		'currency'       => 'USD',
		'coins_total'    => 100,
		'status'         => 'pending',
		'created_at'     => $wpdb->now_gmt(),
		'paid_at'        => '0000-00-00 00:00:00',
		'expires_at'     => gmdate( 'Y-m-d H:i:s', time() + 3600 ),
	)
);

$_REQUEST['_mcr_nonce'] = wp_create_nonce( \MCR\Gateways\Manual::nonce_action( $payment_id ) );

$first = $manual->admin_mark_paid( $payment_id, 'Bank transfer' );

assertFalse( is_wp_error( $first ), 'the first approval succeeds' );
assertSame( 100, $ledger->get_balance( 41 ), 'the coins were credited once' );

$second = $manual->admin_mark_paid( $payment_id, 'Bank transfer' );

assertFalse( is_wp_error( $second ), 'the second approval is not an error' );
assertTrue( ! empty( $second['duplicate'] ), 'the second approval is reported as a duplicate' );
assertSame( 100, $ledger->get_balance( 41 ), 'the second approval does not pay twice' );

assertTrue(
	$wpdb->ran( 'payment:manual:tx_pay_once_1' ),
	'the ledger idempotency key is derived from the provider transaction id'
);

mcr_group( 'Payments: a manual grant is capability checked, nonce checked and records a reason' );

$GLOBALS['mcr_caps']['mcr_manage_coins'] = false;

$denied = $manual->admin_mark_paid( $payment_id, 'Nope' );

assertTrue( is_wp_error( $denied ), 'a user without the capability cannot approve a payment' );
assertSame( 'mcr_forbidden', $denied->get_error_code(), 'the refusal is a capability error' );

$GLOBALS['mcr_caps']['mcr_manage_coins'] = true;

$_REQUEST['_mcr_nonce'] = 'forged-nonce';

$denied = $manual->admin_mark_paid( $payment_id, 'Nope' );

assertTrue( is_wp_error( $denied ), 'a forged nonce cannot approve a payment' );
assertSame( 'mcr_bad_nonce', $denied->get_error_code(), 'the refusal is a nonce error' );

$payment_id2 = $wpdb->seed_payment(
	array(
		'user_id'        => 42,
		'package_id'     => 'limited',
		'gateway'        => 'manual',
		'order_id'       => 'mcr_pay_reason_1',
		'provider_tx_id' => 'tx_pay_reason_1',
		'amount'         => '4.99',
		'currency'       => 'USD',
		'coins_total'    => 100,
		'status'         => 'pending',
		'created_at'     => $wpdb->now_gmt(),
		'paid_at'        => '0000-00-00 00:00:00',
		'expires_at'     => gmdate( 'Y-m-d H:i:s', time() + 3600 ),
	)
);

$_REQUEST['_mcr_nonce'] = wp_create_nonce( \MCR\Gateways\Manual::nonce_action( $payment_id2 ) );

$approved = $manual->admin_mark_paid( $payment_id2, '' );

assertFalse( is_wp_error( $approved ), 'the approval without a reason still succeeds' );

$reasoned = $wpdb->transaction_by_key( 'payment:manual:tx_pay_reason_1' );

assertFalse( null === $reasoned, 'the credit wrote a ledger row' );
assertTrue( '' !== trim( (string) $reasoned['reason'] ), 'the ledger row records a reason' );

/* =========================================================================
 * 17. Summary
 * ======================================================================== */

echo "\n=========================================\n";

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
