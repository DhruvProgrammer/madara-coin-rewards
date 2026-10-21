<?php
/**
 * Adversarial security review tests for the Madara Coin & Rewards plugin.
 *
 * Run with: php tests/review-security.php
 *
 * The suite drives the REAL plugin classes against an in-memory $wpdb that
 * implements the semantics the plugin depends on (UNIQUE idempotency_key,
 * conditional UPDATE guards, INSERT ... ON DUPLICATE KEY UPDATE, and the
 * ad-session status column).
 *
 * Every finding in docs/12-security.md has at least one assertion here that
 * FAILS against the current source. Assertions that PASS document a control
 * that was checked and found correct.
 *
 * @package MCR
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/wp-stubs.php';

if ( ! defined( 'MCR_PLUGIN_DIR' ) ) {
	define( 'MCR_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
}

if ( ! defined( 'MCR_PLUGIN_URL' ) ) {
	define( 'MCR_PLUGIN_URL', 'https://example.test/wp-content/plugins/madara-coin-rewards/' );
}

if ( ! defined( 'MCR_VERSION' ) ) {
	define( 'MCR_VERSION', '1.0.0' );
}

require_once dirname( __DIR__ ) . '/includes/Autoloader.php';
MCR\Autoloader::register( dirname( __DIR__ ) . '/' );

/* ==========================================================================
 * Extra WordPress stubs the shared harness does not provide
 * ======================================================================== */

if ( ! function_exists( 'check_admin_referer' ) ) {
	/**
	 * Nonce + referer check (nonce only, which is the security-relevant half).
	 *
	 * @param string|int $action    Action name.
	 * @param string     $query_arg Request field carrying the nonce.
	 * @return int
	 */
	function check_admin_referer( $action = -1, $query_arg = '_wpnonce' ) {
		$nonce = isset( $_REQUEST[ $query_arg ] ) ? (string) $_REQUEST[ $query_arg ] : '';

		if ( '' === $nonce || ! wp_verify_nonce( $nonce, $action ) ) {
			throw new RuntimeException( 'check_admin_referer failed for action ' . $action );
		}

		return 1;
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	/**
	 * Raw URL escaping.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	function esc_url_raw( $url ) {
		return filter_var( (string) $url, FILTER_SANITIZE_URL );
	}
}

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
		public function __construct( $body = '', array $headers = array(), array $params = array() ) {
			$this->body    = (string) $body;
			$this->headers = array();
			$this->params  = $params;

			foreach ( $headers as $key => $value ) {
				$this->headers[ strtolower( str_replace( '_', '-', (string) $key ) ) ] = (string) $value;
			}
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
		 * Header value.
		 *
		 * @param string $key Header name.
		 * @return string
		 */
		public function get_header( $key ) {
			$key = strtolower( str_replace( '_', '-', (string) $key ) );

			return isset( $this->headers[ $key ] ) ? (string) $this->headers[ $key ] : '';
		}

		/**
		 * Parameter value.
		 *
		 * @param string $key Parameter name.
		 * @return mixed
		 */
		public function get_param( $key ) {
			return isset( $this->params[ $key ] ) ? $this->params[ $key ] : null;
		}
	}
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
	/**
	 * Minimal REST response stand-in.
	 */
	class WP_REST_Response {

		/**
		 * Payload.
		 *
		 * @var mixed
		 */
		private $data;

		/**
		 * HTTP status.
		 *
		 * @var int
		 */
		private $status;

		/**
		 * Constructor.
		 *
		 * @param mixed $data   Payload.
		 * @param int   $status HTTP status.
		 */
		public function __construct( $data = null, $status = 200 ) {
			$this->data   = $data;
			$this->status = (int) $status;
		}

		/**
		 * Payload.
		 *
		 * @return mixed
		 */
		public function get_data() {
			return $this->data;
		}

		/**
		 * HTTP status.
		 *
		 * @return int
		 */
		public function get_status() {
			return $this->status;
		}
	}
}

/* ==========================================================================
 * In-memory $wpdb
 * ======================================================================== */

/**
 * A $wpdb that understands the queries the plugin actually issues.
 */
class MCR_Sec_WPDB extends MCR_Fake_WPDB {

	/**
	 * Table data.
	 *
	 * @var array
	 */
	public $tables = array();

	/**
	 * Every statement, in order.
	 *
	 * @var array
	 */
	public $statements = array();

	/**
	 * Next auto-increment id per table.
	 *
	 * @var array
	 */
	private $next_id = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->prefix = 'wp_';

		foreach ( array( 'balances', 'transactions', 'checkins', 'ad_sessions', 'payments' ) as $name ) {
			$this->tables[ 'wp_mcr_' . $name ] = array();
			$this->next_id[ 'wp_mcr_' . $name ] = 1;
		}
	}

	/**
	 * Blanks out quoted string contents while preserving offsets.
	 *
	 * @param string $sql SQL.
	 * @return string
	 */
	private function blank_strings( $sql ) {
		$out = '';
		$in  = false;
		$len = strlen( $sql );

		for ( $i = 0; $i < $len; $i++ ) {
			$c = $sql[ $i ];

			if ( "'" === $c ) {
				$in = ! $in;
				$out .= ' ';
				continue;
			}

			$out .= $in ? ' ' : $c;
		}

		return $out;
	}

	/**
	 * The table a statement touches.
	 *
	 * @param string $sql SQL.
	 * @return string
	 */
	private function table_of( $sql ) {
		if ( preg_match( '/(?:FROM|INTO|UPDATE)\s+`?([A-Za-z0-9_]+)`?/i', $this->blank_strings( $sql ), $m ) ) {
			return $m[1];
		}

		return '';
	}

	/**
	 * The WHERE clause of a statement.
	 *
	 * @param string $sql SQL.
	 * @return string
	 */
	private function where_clause( $sql ) {
		$blanked = $this->blank_strings( $sql );

		if ( preg_match( '/\bWHERE\b/i', $blanked, $m, PREG_OFFSET_CAPTURE ) ) {
			$pos   = $m[0][1];
			$where = substr( $sql, $pos + strlen( $m[0][0] ) );
			$where = preg_replace( '/\bLIMIT\b.*$/is', '', $where );
			$where = preg_replace( '/\bORDER BY\b.*$/is', '', $where );

			return trim( $where );
		}

		return '';
	}

	/**
	 * Splits a clause on top-level commas.
	 *
	 * @param string $list Clause.
	 * @return array
	 */
	private function split_list( $list ) {
		$parts = array();
		$cur   = '';
		$in    = false;
		$len   = strlen( $list );

		for ( $i = 0; $i < $len; $i++ ) {
			$c = $list[ $i ];

			if ( "'" === $c ) {
				$in   = ! $in;
				$cur .= $c;
				continue;
			}

			if ( ',' === $c && ! $in ) {
				$parts[] = trim( $cur );
				$cur     = '';
				continue;
			}

			$cur .= $c;
		}

		if ( '' !== trim( $cur ) ) {
			$parts[] = trim( $cur );
		}

		return $parts;
	}

	/**
	 * Splits a WHERE clause into AND-ed conditions.
	 *
	 * @param string $where WHERE clause.
	 * @return array
	 */
	private function split_conditions( $where ) {
		if ( '' === trim( $where ) ) {
			return array();
		}

		/*
		 * The AND keyword is located in the BLANKED copy (so an "AND" inside
		 * a string literal is never treated as a separator), but the slice is
		 * taken from the ORIGINAL string so each condition keeps its value.
		 */
		$blanked = $this->blank_strings( $where );
		$parts   = array();
		$offset  = 0;
		$len     = strlen( $blanked );
		$i       = 0;

		while ( $i < $len ) {
			if ( preg_match( '/\bAND\b/i', $blanked, $m, PREG_OFFSET_CAPTURE, $i ) ) {
				$pos = $m[0][1];

				$parts[] = trim( substr( $where, $offset, $pos - $offset ) );
				$offset  = $pos + strlen( $m[0][0] );
				$i       = $offset;
				continue;
			}

			break;
		}

		$parts[] = trim( substr( $where, $offset ) );

		return array_values( array_filter( $parts, 'strlen' ) );
	}

	/**
	 * Evaluates one condition against a row.
	 *
	 * @param array  $row       Row.
	 * @param string $condition Condition.
	 * @return bool
	 */
	private function match_row( $row, $condition ) {
		$condition = trim( $condition );

		if ( '' === $condition ) {
			return true;
		}

		if ( ! preg_match( '/^([A-Za-z0-9_.]+)\s*(>=|<=|<>|!=|=|<|>)\s*(.+)$/', $condition, $m ) ) {
			return true;
		}

		$col = $m[1];
		$op  = $m[2];
		$val = trim( $m[3] );

		if ( false !== strpos( $col, '.' ) ) {
			$col = substr( $col, strrpos( $col, '.' ) + 1 );
		}

		if ( ! array_key_exists( $col, $row ) ) {
			return true;
		}

		$actual = $row[ $col ];

		if ( preg_match( "/^'(.*)'$/s", $val, $vm ) ) {
			$want = stripslashes( $vm[1] );
		} else {
			$want = $val;
		}

		if ( is_numeric( $want ) && is_numeric( $actual ) ) {
			$want   = $want + 0;
			$actual = $actual + 0;
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

		return true;
	}

	/**
	 * The rows a SELECT matches.
	 *
	 * @param string $sql SQL.
	 * @return array
	 */
	private function rows_for( $sql ) {
		$table = $this->table_of( $sql );

		if ( '' === $table || ! isset( $this->tables[ $table ] ) ) {
			return array();
		}

		$conds = $this->split_conditions( $this->where_clause( $sql ) );
		$out   = array();

		foreach ( $this->tables[ $table ] as $row ) {
			$ok = true;

			foreach ( $conds as $cond ) {
				if ( ! $this->match_row( $row, $cond ) ) {
					$ok = false;
					break;
				}
			}

			if ( $ok ) {
				$out[] = $row;
			}
		}

		return $out;
	}

	/**
	 * The columns a SELECT projects.
	 *
	 * @param string $sql SQL.
	 * @return array
	 */
	private function select_columns( $sql ) {
		if ( ! preg_match( '/^\s*SELECT\s+(.*?)\s+FROM\s/i', $this->blank_strings( $sql ), $m ) ) {
			return array();
		}

		$list = trim( $m[1] );

		if ( false !== strpos( $list, '*' ) ) {
			return array();
		}

		return $this->split_list( $list );
	}

	/**
	 * Converts a raw SQL literal into a PHP value.
	 *
	 * @param string $raw Literal.
	 * @return mixed
	 */
	private function sql_value( $raw ) {
		$raw = trim( $raw );

		if ( preg_match( "/^'(.*)'$/s", $raw, $m ) ) {
			return stripslashes( $m[1] );
		}

		if ( 'NULL' === strtoupper( $raw ) ) {
			return null;
		}

		if ( preg_match( '/^-?\d+$/', $raw ) ) {
			return (int) $raw;
		}

		if ( is_numeric( $raw ) ) {
			return (float) $raw;
		}

		return $raw;
	}

	/**
	 * Applies one SET assignment to a row.
	 *
	 * @param array  $row        Row (by reference).
	 * @param string $assignment Assignment.
	 * @return void
	 */
	private function apply_assignment( &$row, $assignment ) {
		$assignment = trim( $assignment );

		if ( ! preg_match( '/^([A-Za-z0-9_]+)\s*=\s*(.+)$/', $assignment, $m ) ) {
			return;
		}

		$col = $m[1];
		$val = trim( $m[2] );

		if ( preg_match( '/^([A-Za-z0-9_]+)\s*([+-])\s*(-?\d+)$/', $val, $mm ) ) {
			$row[ $col ] = (int) $row[ $mm[1] ] + ( '+' === $mm[2] ? (int) $mm[3] : - ( (int) $mm[3] ) );
			return;
		}

		$row[ $col ] = $this->sql_value( $val );
	}

	/**
	 * Runs an INSERT.
	 *
	 * @param string $sql SQL.
	 * @return int|false
	 */
	private function do_insert( $sql ) {
		$table = $this->table_of( $sql );

		if ( ! isset( $this->tables[ $table ] ) ) {
			return false;
		}

		if ( ! preg_match( '/\(([^)]*)\)\s*VALUES/i', $sql, $m ) ) {
			return false;
		}

		$cols = $this->split_list( $m[1] );

		if ( ! preg_match( '/VALUES\s*\((.*)\)/is', $sql, $m ) ) {
			return false;
		}

		$vals = $this->split_list( $m[1] );
		$row  = array();

		foreach ( $cols as $i => $col ) {
			$row[ $col ] = $this->sql_value( isset( $vals[ $i ] ) ? $vals[ $i ] : 'NULL' );
		}

		if ( preg_match( '/ON DUPLICATE KEY UPDATE\s+(.*)$/is', $sql, $m ) ) {
			return $this->upsert( $table, $row, $this->split_list( $m[1] ) );
		}

		$duplicate = $this->duplicate_reason( $table, $row );

		if ( null !== $duplicate ) {
			$this->last_error = $duplicate;
			return false;
		}

		$id              = $this->next_id[ $table ]++;
		$row['id']       = $id;
		$this->tables[ $table ][] = $row;
		$this->insert_id = $id;

		return 1;
	}

	/**
	 * The duplicate-key reason for a row, or null.
	 *
	 * @param string $table Table.
	 * @param array  $row   Row.
	 * @return string|null
	 */
	private function duplicate_reason( $table, $row ) {
		foreach ( $this->tables[ $table ] as $existing ) {
			if ( 'wp_mcr_transactions' === $table
				&& (string) $existing['idempotency_key'] === (string) $row['idempotency_key'] ) {
				return "Duplicate entry '{$row['idempotency_key']}' for key 'idempotency_key'";
			}

			if ( 'wp_mcr_payments' === $table
				&& (string) $existing['order_id'] === (string) $row['order_id'] ) {
				return "Duplicate entry '{$row['order_id']}' for key 'order_id'";
			}

			if ( 'wp_mcr_payments' === $table
				&& (string) $existing['gateway'] === (string) $row['gateway']
				&& (string) $existing['provider_tx_id'] === (string) $row['provider_tx_id'] ) {
				return 'Duplicate entry for key gateway_provider_tx';
			}

			if ( 'wp_mcr_balances' === $table
				&& (string) $existing['user_id'] === (string) $row['user_id'] ) {
				return 'Duplicate entry for key user_id';
			}

			if ( 'wp_mcr_checkins' === $table
				&& (string) $existing['user_id'] === (string) $row['user_id']
				&& (string) $existing['checkin_date'] === (string) $row['checkin_date'] ) {
				return 'Duplicate entry for key user_day';
			}
		}

		return null;
	}

	/**
	 * Runs an INSERT ... ON DUPLICATE KEY UPDATE.
	 *
	 * @param string $table       Table.
	 * @param array  $row         Incoming row.
	 * @param array  $assignments SET assignments.
	 * @return int
	 */
	private function upsert( $table, $row, $assignments ) {
		if ( isset( $row['user_id'] ) ) {
			foreach ( $this->tables[ $table ] as $i => $existing ) {
				if ( (string) $existing['user_id'] === (string) $row['user_id'] ) {
					foreach ( $assignments as $assignment ) {
						$this->apply_assignment( $existing, $assignment );
					}

					$this->tables[ $table ][ $i ] = $existing;

					return 2;
				}
			}
		}

		$id              = $this->next_id[ $table ]++;
		$row['id']       = $id;
		$this->tables[ $table ][] = $row;
		$this->insert_id = $id;

		return 1;
	}

	/**
	 * Runs an UPDATE.
	 *
	 * @param string $sql SQL.
	 * @return int|false
	 */
	private function do_update( $sql ) {
		$table = $this->table_of( $sql );

		if ( ! isset( $this->tables[ $table ] ) ) {
			return false;
		}

		if ( ! preg_match( '/^UPDATE\s+[A-Za-z0-9_]+\s+SET\s+(.*?)\s+WHERE\s+(.*)$/is', $sql, $m ) ) {
			return false;
		}

		$assignments = $this->split_list( $m[1] );
		$conds       = $this->split_conditions( $m[2] );
		$affected    = 0;

		foreach ( $this->tables[ $table ] as $i => $row ) {
			$ok = true;

			foreach ( $conds as $cond ) {
				if ( ! $this->match_row( $row, $cond ) ) {
					$ok = false;
					break;
				}
			}

			if ( ! $ok ) {
				continue;
			}

			foreach ( $assignments as $assignment ) {
				$this->apply_assignment( $row, $assignment );
			}

			$this->tables[ $table ][ $i ] = $row;
			$affected++;
		}

		return $affected;
	}

	/**
	 * Runs a DELETE.
	 *
	 * @param string $sql SQL.
	 * @return int
	 */
	private function do_delete( $sql ) {
		$table = $this->table_of( $sql );

		if ( ! isset( $this->tables[ $table ] ) ) {
			return 0;
		}

		$conds   = $this->split_conditions( $this->where_clause( $sql ) );
		$keep    = array();
		$removed = 0;

		foreach ( $this->tables[ $table ] as $row ) {
			$ok = true;

			foreach ( $conds as $cond ) {
				if ( ! $this->match_row( $row, $cond ) ) {
					$ok = false;
					break;
				}
			}

			if ( $ok ) {
				$removed++;
			} else {
				$keep[] = $row;
			}
		}

		$this->tables[ $table ] = $keep;

		return $removed;
	}

	/**
	 * Runs a statement.
	 *
	 * @param string $sql SQL.
	 * @return int|false
	 */
	public function query( $sql ) {
		$this->queries[]    = $sql;
		$this->statements[] = $sql;

		$trimmed = trim( $sql );

		if ( preg_match( '/^START TRANSACTION/i', $trimmed ) ) {
			return 1;
		}

		if ( preg_match( '/^COMMIT/i', $trimmed ) ) {
			return 1;
		}

		if ( preg_match( '/^ROLLBACK/i', $trimmed ) ) {
			return 1;
		}

		if ( preg_match( '/^INSERT\s+INTO/i', $trimmed ) ) {
			return $this->do_insert( $trimmed );
		}

		if ( preg_match( '/^UPDATE/i', $trimmed ) ) {
			return $this->do_update( $trimmed );
		}

		if ( preg_match( '/^DELETE/i', $trimmed ) ) {
			return $this->do_delete( $trimmed );
		}

		return 1;
	}

	/**
	 * Fetches a single value.
	 *
	 * @param string $sql SQL.
	 * @return mixed
	 */
	public function get_var( $sql ) {
		$this->statements[] = $sql;
		$this->queries[]   = $sql;

		if ( preg_match( '/GET_LOCK/i', $sql ) ) {
			return '1';
		}

		if ( preg_match( '/RELEASE_LOCK/i', $sql ) ) {
			return '1';
		}

		if ( preg_match( '/information_schema/i', $sql ) ) {
			return 'InnoDB';
		}

		$rows = $this->rows_for( $sql );

		if ( preg_match( '/SELECT\s+COUNT\(\s*\*\s*\)/i', $sql ) ) {
			return count( $rows );
		}

		if ( preg_match( '/COALESCE\(\s*SUM\(\s*([A-Za-z0-9_]+)\s*\)/i', $sql, $m ) ) {
			$sum = 0;

			foreach ( $rows as $row ) {
				$sum += (int) $row[ $m[1] ];
			}

			return $sum;
		}

		if ( preg_match( '/UNIX_TIMESTAMP\(\s*MAX\(\s*([A-Za-z0-9_]+)\s*\)\s*\)/i', $sql, $m ) ) {
			$max = 0;

			foreach ( $rows as $row ) {
				$value = strtotime( (string) $row[ $m[1] ] );

				if ( $value && $value > $max ) {
					$max = $value;
				}
			}

			return $max;
		}

		if ( empty( $rows ) ) {
			return null;
		}

		$cols = $this->select_columns( $sql );

		if ( empty( $cols ) ) {
			return null;
		}

		$key = $cols[0];

		if ( false !== strpos( $key, '.' ) ) {
			$key = substr( $key, strrpos( $key, '.' ) + 1 );
		}

		return array_key_exists( $key, $rows[0] ) ? $rows[0][ $key ] : null;
	}

	/**
	 * Fetches a single row.
	 *
	 * @param string $sql  SQL.
	 * @param string $type Result type.
	 * @return array|object|null
	 */
	public function get_row( $sql, $type = 'OBJECT' ) {
		$this->statements[] = $sql;
		$this->queries[]   = $sql;

		if ( preg_match( '/information_schema/i', $sql ) ) {
			return null;
		}

		$rows = $this->rows_for( $sql );

		if ( empty( $rows ) ) {
			return null;
		}

		if ( 'ARRAY_A' === $type ) {
			return $rows[0];
		}

		return (object) $rows[0];
	}

	/**
	 * Fetches rows.
	 *
	 * @param string $sql  SQL.
	 * @param string $type Result type.
	 * @return array
	 */
	public function get_results( $sql, $type = 'OBJECT' ) {
		$this->statements[] = $sql;
		$this->queries[]   = $sql;

		if ( preg_match( '/SHOW TABLE STATUS/i', $sql ) ) {
			return array( (object) array( 'Engine' => 'InnoDB' ) );
		}

		$rows = $this->rows_for( $sql );

		if ( 'ARRAY_A' === $type ) {
			return $rows;
		}

		return array_map(
			function ( $row ) {
				return (object) $row;
			},
			$rows
		);
	}

	/**
	 * Fetches a column.
	 *
	 * @param string $sql SQL.
	 * @return array
	 */
	public function get_col( $sql ) {
		$this->statements[] = $sql;
		$this->queries[]   = $sql;

		$rows = $this->rows_for( $sql );
		$cols = $this->select_columns( $sql );
		$out  = array();

		foreach ( $rows as $row ) {
			if ( empty( $cols ) ) {
				$out[] = reset( $row );
				continue;
			}

			$key = $cols[0];

			if ( false !== strpos( $key, '.' ) ) {
				$key = substr( $key, strrpos( $key, '.' ) + 1 );
			}

			$out[] = array_key_exists( $key, $row ) ? $row[ $key ] : null;
		}

		return $out;
	}

	/**
	 * The stored balance of a user.
	 *
	 * @param int $user_id User id.
	 * @return int
	 */
	public function balance_of( $user_id ) {
		foreach ( $this->tables['wp_mcr_balances'] as $row ) {
			if ( (int) $row['user_id'] === (int) $user_id ) {
				return (int) $row['balance'];
			}
		}

		return 0;
	}
}

/* ==========================================================================
 * Helpers
 * ======================================================================== */

/**
 * Resets the global state and installs a fresh $wpdb.
 *
 * @param array $settings Settings to store.
 * @return MCR_Sec_WPDB
 */
function mcr_sec_reset( array $settings = array() ) {
	$GLOBALS['mcr_options']    = array( 'mcr_settings' => $settings );
	$GLOBALS['mcr_filters']    = array();
	$GLOBALS['mcr_current_user'] = 0;
	$GLOBALS['mcr_caps']       = array();

	$_GET     = array();
	$_POST    = array();
	$_REQUEST = array();

	MCR\Settings::flush_cache();
	MCR\AdProviders\Registry::reset();

	$GLOBALS['wpdb'] = new MCR_Sec_WPDB();

	return $GLOBALS['wpdb'];
}

/**
 * The settings a site running the GenericS2S provider would have.
 *
 * @param array $overrides Overrides.
 * @return array
 */
function mcr_sec_s2s_settings( array $overrides = array() ) {
	return array_merge(
		array(
			'ads.provider'                   => 'generic_s2s',
			'ads.providers.generic_s2s'      => array( 'postback_secret' => 'unit-test-secret' ),
			'ads.coins_per_ad'               => 5,
			'ads.daily_limit'                => 10,
			'ads.global_daily_limit'         => 50,
			'ads.cooldown_seconds'           => 300,
			'ads.session_ttl'                => 900,
			'features.ads_enabled'           => 'on',
			'security.log_ip'                => 'on',
			'security.log_user_agent'        => 'off',
			'security.rate_limit_checkin'    => 10,
			'security.rate_limit_ads'        => 20,
		),
		$overrides
	);
}

/**
 * Builds a signed ad-network postback request.
 *
 * @param array  $payload Payload.
 * @param string $secret  HMAC secret.
 * @param int    $offset  Timestamp offset in seconds.
 * @return WP_REST_Request
 */
function mcr_sec_postback( array $payload, $secret = 'unit-test-secret', $offset = 0 ) {
	$body    = wp_json_encode( $payload );
	$stamp   = time() + $offset;
	$headers = array(
		'X-MCR-Signature' => hash_hmac( 'sha256', $body, $secret ),
		'X-MCR-Timestamp' => (string) $stamp,
	);

	return new WP_REST_Request( $body, $headers );
}

/**
 * The rows of a table.
 *
 * @param MCR_Sec_WPDB $wpdb  Fake wpdb.
 * @param string       $table Table.
 * @return array
 */
function mcr_sec_rows( MCR_Sec_WPDB $wpdb, $table ) {
	return isset( $wpdb->tables[ $table ] ) ? $wpdb->tables[ $table ] : array();
}

/**
 * Reads every plugin source file.
 *
 * @param string $sub Directory relative to includes/.
 * @return array
 */
function mcr_sec_sources( $sub = '' ) {
	$base  = dirname( __DIR__ ) . '/includes' . ( '' !== $sub ? '/' . $sub : '' );
	$files = array();

	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $base, FilesystemIterator::SKIP_DOTS ) );

	foreach ( $it as $file ) {
		if ( 'php' === strtolower( $file->getExtension() ) ) {
			$files[] = $file->getPathname();
		}
	}

	sort( $files );

	return $files;
}

/* ==========================================================================
 * 1. Ad postback signature verification (control verified)
 * ======================================================================== */

mcr_group( '1. GenericS2S postback: signature, timestamp and payload validation' );

$wpdb = mcr_sec_reset( mcr_sec_s2s_settings() );
$provider = MCR\AdProviders\Registry::get( 'generic_s2s' );

assertTrue( is_object( $provider ), 'the GenericS2S provider is registered' );

$session = MCR\Rewards\AdSessions::instance()->start( 7, array( 'ad_id' => 'rewarded_video' ) );
assertTrue( is_array( $session ) && ! is_wp_error( $session ), 'an ad session starts for a logged-in user' );

$token = isset( $session['session_token'] ) ? $session['session_token'] : '';

// 1.1 Missing signature.
$request = new WP_REST_Request( wp_json_encode( array( 'session_token' => $token, 'transaction_id' => 'tx-1' ) ), array( 'X-MCR-Timestamp' => (string) time() ) );
$response = $provider->handle_postback( $request );
assertSame( 400, $response->get_status(), 'a postback without a signature is rejected' );
assertSame( 'missing_credentials', $response->get_data()['error'], 'a postback without a signature reports missing_credentials' );

// 1.2 Wrong signature.
$request = new WP_REST_Request(
	wp_json_encode( array( 'session_token' => $token, 'transaction_id' => 'tx-1' ) ),
	array(
		'X-MCR-Signature' => hash_hmac( 'sha256', 'anything', 'wrong-secret' ),
		'X-MCR-Timestamp' => (string) time(),
	)
);
$response = $provider->handle_postback( $request );
assertSame( 403, $response->get_status(), 'a postback with a wrong signature is rejected' );
assertSame( 'bad_signature', $response->get_data()['error'], 'a postback with a wrong signature reports bad_signature' );

// 1.3 Signature over a DIFFERENT body (tamper).
$signed_body = wp_json_encode( array( 'session_token' => $token, 'transaction_id' => 'tx-1' ) );
$tampered    = wp_json_encode( array( 'session_token' => $token, 'transaction_id' => 'tx-1', 'reward_amount' => 999999 ) );
$request     = new WP_REST_Request(
	$tampered,
	array(
		'X-MCR-Signature' => hash_hmac( 'sha256', $signed_body, 'unit-test-secret' ),
		'X-MCR-Timestamp' => (string) time(),
	)
);
$response = $provider->handle_postback( $request );
assertSame( 403, $response->get_status(), 'a signature computed over a different body is rejected' );

// 1.4 Missing timestamp.
$request = new WP_REST_Request(
	wp_json_encode( array( 'session_token' => $token, 'transaction_id' => 'tx-1' ) ),
	array( 'X-MCR-Signature' => hash_hmac( 'sha256', wp_json_encode( array( 'session_token' => $token, 'transaction_id' => 'tx-1' ) ), 'unit-test-secret' ) )
);
$response = $provider->handle_postback( $request );
assertSame( 400, $response->get_status(), 'a postback without a timestamp is rejected' );
assertSame( 'missing_timestamp', $response->get_data()['error'], 'a postback without a timestamp reports missing_timestamp' );

// 1.5 Stale timestamp.
$body    = wp_json_encode( array( 'session_token' => $token, 'transaction_id' => 'tx-1' ) );
$request = new WP_REST_Request(
	$body,
	array(
		'X-MCR-Signature' => hash_hmac( 'sha256', $body, 'unit-test-secret' ),
		'X-MCR-Timestamp' => (string) ( time() - 4000 ),
	)
);
$response = $provider->handle_postback( $request );
assertSame( 400, $response->get_status(), 'a postback with a stale timestamp is rejected' );
assertSame( 'stale_timestamp', $response->get_data()['error'], 'a postback with a stale timestamp reports stale_timestamp' );

// 1.6 Signature with an empty secret.
$request = new WP_REST_Request(
	$body,
	array(
		'X-MCR-Signature' => hash_hmac( 'sha256', $body, '' ),
		'X-MCR-Timestamp' => (string) time(),
	)
);
$response = $provider->handle_postback( $request );
assertSame( 403, $response->get_status(), 'a signature made with an empty secret is rejected' );

// 1.7 Valid signature but an unknown session.
$body    = wp_json_encode( array( 'session_token' => str_repeat( 'a', 64 ), 'transaction_id' => 'tx-1' ) );
$request = new WP_REST_Request(
	$body,
	array(
		'X-MCR-Signature' => hash_hmac( 'sha256', $body, 'unit-test-secret' ),
		'X-MCR-Timestamp' => (string) time(),
	)
);
$response = $provider->handle_postback( $request );
assertSame( 404, $response->get_status(), 'a correctly signed postback for an unknown session is refused' );
assertSame( 'unknown_session', $response->get_data()['error'], 'an unknown session reports unknown_session' );

// 1.8 The comparison is constant time.
$stripe_src = file_get_contents( dirname( __DIR__ ) . '/includes/Gateways/Stripe.php' );
$s2s_src    = file_get_contents( dirname( __DIR__ ) . '/includes/AdProviders/GenericS2S.php' );
assertTrue( false !== strpos( $s2s_src, 'hash_equals( $expected' ), 'the ad postback compares signatures with hash_equals' );
assertTrue( false !== strpos( $stripe_src, 'hash_equals( $expected, $sig )' ), 'the Stripe webhook compares signatures with hash_equals' );

/* ==========================================================================
 * 2. Ad postback: transaction id is single use (control verified)
 * ======================================================================== */

mcr_group( '2. GenericS2S postback: transaction id replay protection' );

$wpdb   = mcr_sec_reset( mcr_sec_s2s_settings() );
$provider = MCR\AdProviders\Registry::get( 'generic_s2s' );

$session_a = MCR\Rewards\AdSessions::instance()->start( 7, array( 'ad_id' => 'rewarded_video' ) );
$session_b = MCR\Rewards\AdSessions::instance()->start( 7, array( 'ad_id' => 'rewarded_video' ) );

$body_a = wp_json_encode( array( 'session_token' => $session_a['session_token'], 'transaction_id' => 'tx-A' ) );
$req_a  = new WP_REST_Request(
	$body_a,
	array(
		'X-MCR-Signature' => hash_hmac( 'sha256', $body_a, 'unit-test-secret' ),
		'X-MCR-Timestamp' => (string) time(),
	)
);
$response = $provider->handle_postback( $req_a );
assertSame( 200, $response->get_status(), 'a valid postback is accepted' );
assertTrue( ! empty( $response->get_data()['credited'] ), 'a valid postback credits the reward' );
assertSame( 5, $wpdb->balance_of( 7 ), 'the reward lands on the balance' );

// Replay the exact same signed request.
$response = $provider->handle_postback( $req_a );
assertSame( 200, $response->get_status(), 'a replayed postback is acknowledged' );
assertTrue( ! empty( $response->get_data()['duplicate'] ), 'a replayed postback is reported as a duplicate' );
assertSame( 5, $wpdb->balance_of( 7 ), 'a replayed postback does not credit a second time' );

// Re-use the same transaction id on a DIFFERENT session.
$body_b = wp_json_encode( array( 'session_token' => $session_b['session_token'], 'transaction_id' => 'tx-A' ) );
$req_b  = new WP_REST_Request(
	$body_b,
	array(
		'X-MCR-Signature' => hash_hmac( 'sha256', $body_b, 'unit-test-secret' ),
		'X-MCR-Timestamp' => (string) time(),
	)
);
$response = $provider->handle_postback( $req_b );
assertTrue( ! empty( $response->get_data()['duplicate'] ), 'a transaction id cannot be re-used on another session' );
assertSame( 5, $wpdb->balance_of( 7 ), 're-using a transaction id on another session credits nothing' );

/* ==========================================================================
 * 3. DEFECT: the ad reward cap is dead for the built-in S2S provider
 * ======================================================================== */

mcr_group( '3. DEFECT: ads.daily_limit / ads.global_daily_limit are not enforced (status verified vs credited)' );

$wpdb = mcr_sec_reset( mcr_sec_s2s_settings( array( 'ads.daily_limit' => 1, 'ads.global_daily_limit' => 1 ) ) );
$provider = MCR\AdProviders\Registry::get( 'generic_s2s' );

$tokens = array();

for ( $i = 0; $i < 4; $i++ ) {
	$session = MCR\Rewards\AdSessions::instance()->start( 7, array( 'ad_id' => 'rewarded_video' ) );
	$tokens[] = $session['session_token'];
}

assertCount( 1, $tokens, 'at most ads.daily_limit (1) ad session can be opened per day' );

$statuses = array();

foreach ( $tokens as $index => $session_token ) {
	$body = wp_json_encode( array( 'session_token' => $session_token, 'transaction_id' => 'tx-' . $index ) );
	$req  = new WP_REST_Request(
		$body,
		array(
			'X-MCR-Signature' => hash_hmac( 'sha256', $body, 'unit-test-secret' ),
			'X-MCR-Timestamp' => (string) time(),
		)
	);

	$response = $provider->handle_postback( $req );
	$data     = $response->get_data();

	$statuses[] = isset( $data['reason'] ) ? $data['reason'] : '';

	if ( $index > 0 ) {
		assertFalse(
			! empty( $data['credited'] ),
			'postback #' . ( $index + 1 ) . ' is refused because the daily ad limit (ads.daily_limit = 1) is reached'
		);
	}
}

assertSame( 5, $wpdb->balance_of( 7 ), 'the balance is capped at ads.daily_limit x ads.coins_per_ad' );
assertSame( 'daily_limit', $statuses[1], 'the second postback is answered with reason=daily_limit' );

// The S2S path never produces a 'verified' row, so every counter is blind.
$verified_writes = 0;

foreach ( $wpdb->statements as $statement ) {
	if ( preg_match( "/SET status = 'verified'/i", $statement ) ) {
		$verified_writes++;
	}
}

assertSame( 0, $verified_writes, 'the S2S postback path never writes status=verified, so no cap counter can ever match' );

$credited_rows = 0;

foreach ( mcr_sec_rows( $wpdb, 'wp_mcr_ad_sessions' ) as $row ) {
	if ( 'credited' === $row['status'] ) {
		$credited_rows++;
	}
}

assertSame( 4, $credited_rows, 'all four postbacks left a credited ad-session row' );

// cap_guard() itself must refuse a user who already earned more than the cap.
$guard = MCR\Rewards\AdSessions::instance()->cap_guard( 7, 0 );
assertTrue( is_wp_error( $guard ), 'cap_guard() refuses a user who already earned more ads today than ads.daily_limit' );

// The cooldown is dead for the same reason.
mcr_sec_reset( mcr_sec_s2s_settings( array( 'ads.cooldown_seconds' => 300, 'ads.daily_limit' => 0, 'ads.global_daily_limit' => 0 ) ) );
$provider = MCR\AdProviders\Registry::get( 'generic_s2s' );

$session = MCR\Rewards\AdSessions::instance()->start( 7, array( 'ad_id' => 'rewarded_video' ) );
$body    = wp_json_encode( array( 'session_token' => $session['session_token'], 'transaction_id' => 'tx-c' ) );
$req     = new WP_REST_Request(
	$body,
	array(
		'X-MCR-Signature' => hash_hmac( 'sha256', $body, 'unit-test-secret' ),
		'X-MCR-Timestamp' => (string) time(),
	)
);
$provider->handle_postback( $req );

$next = MCR\Rewards\AdSessions::instance()->start( 7, array( 'ad_id' => 'rewarded_video' ) );
assertTrue(
	is_wp_error( $next ) && 'mcr_cooldown_active' === $next->get_error_code(),
	'the ad cooldown (ads.cooldown_seconds = 300) blocks a second session right after a rewarded view'
);

// The user-facing ad statistics are blind to credited sessions too.
$stats = MCR\Rewards\AdSessions::instance()->stats( 7 );
assertSame( 1, (int) $stats['today'], 'the ad statistics report the rewarded view the user just earned' );
assertSame( 5, (int) $stats['coins'], 'the ad statistics report the coins the user just earned' );

/* ==========================================================================
 * 4. DEFECT: the advertised rate limits are never enforced
 * ======================================================================== */

mcr_group( '4. DEFECT: security.rate_limit_ads / security.rate_limit_checkin are never enforced' );

$wpdb = mcr_sec_reset( mcr_sec_s2s_settings( array( 'security.rate_limit_ads' => 1, 'ads.daily_limit' => 0, 'ads.global_daily_limit' => 0, 'ads.cooldown_seconds' => 0 ) ) );

$starts = 0;

for ( $i = 0; $i < 5; $i++ ) {
	$result = MCR\Rewards\AdSessions::instance()->start( 7, array( 'ad_id' => 'rewarded_video' ) );

	if ( ! is_wp_error( $result ) ) {
		$starts++;
	}
}

assertSame( 1, $starts, 'only one ad session per rate-limit window is allowed (security.rate_limit_ads = 1)' );

$readers = array();

foreach ( mcr_sec_sources() as $file ) {
	if ( in_array( basename( $file ), array( 'Settings.php', 'SettingsPage.php' ), true ) ) {
		// These two only DEFINE and RENDER the setting; they never enforce it.
		continue;
	}

	$source = (string) file_get_contents( $file );

	if ( false !== strpos( $source, "security.rate_limit_ads" ) || false !== strpos( $source, "security.rate_limit_checkin" ) ) {
		$readers[] = basename( $file );
	}
}

assertCount( 0, $readers, 'no plugin source reads the rate-limit settings (they are dead settings)' );

/* ==========================================================================
 * 5. DEFECT: secrets are stored in plaintext although the UI claims otherwise
 * ======================================================================== */

mcr_group( '5. DEFECT: gateway and provider secrets are stored in plaintext' );

mcr_sec_reset();

MCR\Gateways\Gateway::save_settings(
	'stripe',
	array(
		'secret_key'     => 'sk_live_TESTPLAINTEXT',
		'webhook_secret' => 'whsec_TESTPLAINTEXT',
		'publishable_key' => 'pk_live_TEST',
	)
);

$stored = isset( $GLOBALS['mcr_options']['mcr_settings']['gateways.stripe'] )
	? $GLOBALS['mcr_options']['mcr_settings']['gateways.stripe']
	: array();

assertTrue( isset( $stored['secret_key'] ), 'the Stripe secret key is persisted' );
assertFalse(
	'sk_live_TESTPLAINTEXT' === (string) $stored['secret_key'],
	'the Stripe secret key is stored encrypted at rest (as the settings screen claims)'
);
assertSame(
	'sk_live_TESTPLAINTEXT',
	MCR\Gateways\Stripe::decrypt_secret( (string) $stored['secret_key'] ),
	'the stored Stripe secret key still decrypts to the original value'
);

MCR\AdProviders\Provider::save_settings( 'generic_s2s', array( 'postback_secret' => 'hmac-PLAINTEXT' ) );

$provider_stored = isset( $GLOBALS['mcr_options']['mcr_settings']['ads.providers.generic_s2s'] )
	? $GLOBALS['mcr_options']['mcr_settings']['ads.providers.generic_s2s']
	: array();

assertTrue( isset( $provider_stored['postback_secret'] ), 'the ad postback secret is persisted' );
assertFalse(
	'hmac-PLAINTEXT' === (string) $provider_stored['postback_secret'],
	'the ad postback HMAC secret is stored encrypted at rest'
);

/* ==========================================================================
 * 6. DEFECT: saving the settings form silently deletes the secrets
 * ======================================================================== */

mcr_group( '6. DEFECT: Settings::sanitize() drops the gateway and provider secret settings' );

mcr_sec_reset(
	array(
		'gateways.stripe'          => array( 'secret_key' => 'sk_live_KEEPME' ),
		'ads.providers.generic_s2s' => array( 'postback_secret' => 'hmac-KEEPME' ),
	)
);

$clean = MCR\Settings::sanitize( array( 'features.checkin_enabled' => 'on' ) );

assertTrue(
	isset( $clean['gateways.stripe'] ),
	'saving the settings form keeps the Stripe credentials'
);
assertTrue(
	isset( $clean['ads.providers.generic_s2s'] ),
	'saving the settings form keeps the ad postback secret'
);

/* ==========================================================================
 * 7. DEFECT: the package stock limit never decrements
 * ======================================================================== */

mcr_group( '7. DEFECT: a package purchase never increments the package purchase counter' );

$wpdb = mcr_sec_reset(
	array(
		'packages'            => array(
			'starter' => array(
				'id'            => 'starter',
				'title'         => 'Starter',
				'coins'         => 100,
				'bonus_percent' => 0,
				'price'         => 5.0,
				'currency'      => 'USD',
				'badge'         => '',
				'active'        => 'on',
				'sort_order'    => 1,
				'limit'         => 1,
				'purchased'     => 0,
			),
		),
		'purchase.currency' => 'USD',
		'features.purchase_enabled' => 'on',
	)
);

$GLOBALS['mcr_caps']       = array( 'mcr_manage_coins' => true );
$GLOBALS['mcr_current_user'] = 1;

$package = MCR\Payments\Packages::get( 'starter' );
assertTrue( MCR\Payments\Packages::is_purchasable( $package ), 'the package is purchasable before the limit is reached' );

$gateway = new MCR\Gateways\Manual( array() );

$order = $gateway->create_order(
	array(
		'user_id'         => 7,
		'package_id'      => 'starter',
		'amount_minor'    => 500,
		'currency'        => 'USD',
		'coins_total'     => 100,
		'idempotency_key' => 'mcr-sec-order-1',
		'expires_at'      => gmdate( 'Y-m-d H:i:s', time() + 3600 ),
	)
);

assertTrue( ! is_wp_error( $order ), 'the manual gateway creates a pending order' );

$payment_id = 0;

foreach ( mcr_sec_rows( $wpdb, 'wp_mcr_payments' ) as $row ) {
	$payment_id = (int) $row['id'];
}

$_REQUEST['_mcr_nonce'] = wp_create_nonce( MCR\Gateways\Manual::nonce_action( $payment_id ) );

$paid = $gateway->admin_mark_paid( $payment_id, 'Bank transfer received' );

assertTrue( ! is_wp_error( $paid ), 'the administrator approves the payment' );
assertSame( 100, $wpdb->balance_of( 7 ), 'the buyer receives the package coins' );

$after = MCR\Payments\Packages::get( 'starter' );
assertSame(
	1,
	(int) $after['purchased'],
	'the package purchase counter is incremented by a completed purchase so the stock limit is enforced'
);

/* ==========================================================================
 * 8. Authorisation controls (control verified)
 * ======================================================================== */

mcr_group( '8. Authorisation: admin coin grants and manual payments are capability gated' );

mcr_sec_reset();
$GLOBALS['mcr_caps'] = array();

$_POST['mcr_action']  = 'mcr_grant_deduct';
$_POST['_wpnonce']    = wp_create_nonce( 'mcr_admin_action' );
$_REQUEST['_wpnonce'] = $_POST['_wpnonce'];
$_POST['user_id']     = 7;
$_POST['amount']      = 1000000;
$_POST['operation']   = 'grant';
$_POST['reason']      = 'pwn';

$admin  = new MCR\Admin\Admin();
$method = new ReflectionMethod( 'MCR\Admin\Admin', 'maybe_handle_actions' );
$method->setAccessible( true );

assertThrows(
	function () use ( $method, $admin ) {
		$method->invoke( $admin );
	},
	'a subscriber cannot grant coins through the admin grant/deduct action'
);

$wpdb = mcr_sec_reset();
$GLOBALS['mcr_caps'] = array();

$_REQUEST['_mcr_nonce'] = wp_create_nonce( 'mcr_manual_action_999' );
$gateway               = new MCR\Gateways\Manual( array() );

$denied = $gateway->admin_mark_paid( 1, 'nope' );
assertTrue( is_wp_error( $denied ) && 'mcr_forbidden' === $denied->get_error_code(), 'a subscriber cannot approve a manual payment' );

$GLOBALS['mcr_caps'] = array( 'mcr_manage_coins' => true );
$_REQUEST['_mcr_nonce'] = 'nonce-not-for-this-payment';
$denied = $gateway->admin_mark_paid( 1, 'nope' );
assertTrue( is_wp_error( $denied ) && 'mcr_bad_nonce' === $denied->get_error_code(), 'a payment nonce bound to another payment id is rejected' );

/* ==========================================================================
 * 9. IDOR guards (control verified)
 * ======================================================================== */

mcr_group( '9. IDOR: orders and balances are bound to their owner' );

mcr_sec_reset();

$order_row = array(
	'id'          => 1,
	'user_id'     => 7,
	'package_id'  => 'starter',
	'gateway'     => 'manual',
	'order_id'    => 'mcr_20260101_abcdef',
	'provider_tx_id' => 'pending_mcr_20260101_abcdef',
	'amount'      => '5.00',
	'currency'    => 'USD',
	'coins_total' => 100,
	'status'      => 'pending',
	'user_ip'     => '',
	'user_agent'  => '',
	'created_at'  => gmdate( 'Y-m-d H:i:s' ),
	'paid_at'     => '0000-00-00 00:00:00',
	'expires_at'  => gmdate( 'Y-m-d H:i:s', time() + 3600 ),
);

$GLOBALS['wpdb']->tables['wp_mcr_payments'] = array( $order_row );

assertTrue( null !== MCR\Payments\Orders::instance()->get_for_user( 'mcr_20260101_abcdef', 7 ), 'the buyer can read their own order' );
assertSame( null, MCR\Payments\Orders::instance()->get_for_user( 'mcr_20260101_abcdef', 8 ), 'another user cannot read the order' );

/* ==========================================================================
 * 10. SQL injection (control verified)
 * ======================================================================== */

mcr_group( '10. SQL injection: hostile ledger filters are parameterised' );

mcr_sec_reset();
$GLOBALS['mcr_caps'] = array();

$hostile = "x'UNION SELECT user_id,balance FROM wp_mcr_balances--";

$_GET['search'] = $hostile;
$_GET['type']   = 'checkin';

$page    = new MCR\Admin\TransactionsPage();
$method  = new ReflectionMethod( 'MCR\Admin\TransactionsPage', 'render_ledger_table' );
$method->setAccessible( true );

ob_start();
$method->invoke( $page );
ob_end_clean();

$queries = $GLOBALS['wpdb']->queries;
$found  = false;
$sample = '';

foreach ( $queries as $query ) {
	if ( false !== strpos( $query, 'mcr_transactions' ) && false !== strpos( $query, 'reason LIKE' ) ) {
		$found = true;
		$sample = $query;
		break;
	}
}

assertTrue( $found, 'the ledger query was actually executed' );

$expected = "'" . addslashes( '%' . addcslashes( $hostile, '_%\\' ) . '%' ) . "'";

assertFalse(
	false !== strpos( $sample, $hostile ),
	'a hostile ledger filter is not interpolated raw into the SQL'
);
assertTrue(
	false !== strpos( $sample, $expected ),
	'a hostile ledger filter is bound as an escaped placeholder value'
);

$injectable = array();

foreach ( mcr_sec_sources() as $file ) {
	$source = (string) file_get_contents( $file );

	if ( preg_match( '/(WHERE|AND|OR)\s+[A-Za-z0-9_.]*\s*=\s*\$_(GET|POST|REQUEST)/', $source ) ) {
		$injectable[] = basename( $file );
	}
}

assertCount( 0, $injectable, 'no plugin source interpolates a raw request superglobal into SQL' );

/* ==========================================================================
 * 11. XSS (control verified)
 * ======================================================================== */

mcr_group( '11. XSS: stored reasons and package titles are escaped on output' );

mcr_sec_reset();

$html = MCR\Templates\Templates::part(
	'txn',
	array(
		'txn' => array(
			'amount'         => 5,
			'reason'         => '<script>alert(1)</script>',
			'type_label'     => 'Ad reward',
			'created_display' => 'today',
			'balance_after'  => 5,
		),
	)
);

assertFalse( false !== strpos( $html, '<script>' ), 'a stored transaction reason is escaped in the frontend template' );
assertTrue( false !== strpos( $html, '&lt;script&gt;' ), 'the escaped transaction reason is still readable' );

$html = MCR\Templates\Templates::part(
	'package',
	array(
		'package' => array(
			'id'    => 'starter',
			'title' => '"><img src=x onerror=alert(1)>',
			'coins' => 100,
			'coins_total' => 100,
			'price' => 5.0,
			'currency' => 'USD',
		),
	)
);

assertFalse( false !== strpos( $html, '<img src=x' ), 'a package title is escaped in the frontend template' );
assertFalse( false !== strpos( $html, 'onerror=alert(1)>' ), 'a package title cannot break out of an attribute' );

/* ==========================================================================
 * 12. Stripe webhook signature (control verified)
 * ======================================================================== */

mcr_group( '12. Stripe webhook: signature is verified over the raw body' );

$secret = 'whsec_test';
$body   = '{"type":"checkout.session.completed","data":{"object":{"id":"cs_1"}}}';
$stamp  = time();
$hmac   = hash_hmac( 'sha256', $stamp . '.' . $body, $secret );

assertTrue(
	MCR\Gateways\Stripe::verify_signature( $body, 't=' . $stamp . ',v1=' . $hmac, $secret, 300 ),
	'a correctly signed Stripe webhook verifies'
);
assertFalse(
	MCR\Gateways\Stripe::verify_signature( $body . ' ', 't=' . $stamp . ',v1=' . $hmac, $secret, 300 ),
	'a Stripe signature over a different body is rejected'
);
assertFalse(
	MCR\Gateways\Stripe::verify_signature( $body, 't=' . ( $stamp - 4000 ) . ',v1=' . $hmac, $secret, 300 ),
	'a Stripe webhook with a stale timestamp is rejected'
);
assertFalse(
	MCR\Gateways\Stripe::verify_signature( $body, 't=' . $stamp . ',v1=' . $hmac, 'whsec_other', 300 ),
	'a Stripe webhook signed with a different secret is rejected'
);
assertFalse(
	MCR\Gateways\Stripe::verify_signature( $body, 't=' . $stamp, $secret, 300 ),
	'a Stripe webhook without a v1 signature is rejected'
);
assertFalse(
	MCR\Gateways\Stripe::verify_signature( $body, 'v1=' . $hmac, $secret, 300 ),
	'a Stripe webhook without a timestamp is rejected'
);
assertTrue(
	MCR\Gateways\Stripe::verify_signature( $body, 't=' . $stamp . ',v1=deadbeef,v1=' . $hmac, $secret, 300 ),
	'a Stripe webhook with a rotated signing key still verifies'
);

assertTrue(
	false !== strpos( $stripe_src, '$request->get_body()' ),
	'the Stripe webhook reads the RAW request body'
);
assertTrue(
	false !== strpos( $stripe_src, 'retrieve_session' ),
	'the Stripe webhook re-fetches the session server-side instead of trusting the event body'
);

/* ==========================================================================
 * 13. Object injection, SSRF and LFI (control verified)
 * ======================================================================== */

mcr_group( '13. Object injection, SSRF and local file inclusion' );

$dangerous = array();

foreach ( mcr_sec_sources() as $file ) {
	$source = (string) file_get_contents( $file );

	if ( preg_match( '/\b(maybe_unserialize|unserialize)\s*\(/', $source ) ) {
		$dangerous[] = basename( $file ) . ': unserialize';
	}
}

assertCount( 0, $dangerous, 'no plugin source deserialises user controlled data' );

$ssrf = array();

foreach ( mcr_sec_sources() as $file ) {
	$source = (string) file_get_contents( $file );

	// An outbound request whose URL is built from a raw request superglobal.
	if ( preg_match( '/wp_remote_(get|post)\([^)]*\$_(GET|POST|REQUEST|SERVER)/', $source ) ) {
		$ssrf[] = basename( $file );
	}
}

assertCount( 0, $ssrf, 'no outbound HTTP request is built from a request superglobal' );

$urls = array();

foreach ( mcr_sec_sources( 'Gateways' ) as $file ) {
	$source = (string) file_get_contents( $file );

	if ( preg_match_all( '/\$this->stripe_get\(\s*([^)]*)\)/', $source, $matches ) ) {
		foreach ( $matches[1] as $url ) {
			$urls[] = trim( $url );
		}
	}
}

$unsafe_urls = array();

foreach ( $urls as $url ) {
	if ( false === strpos( $url, 'self::API_BASE' ) ) {
		$unsafe_urls[] = $url;
	}
}

assertCount( 0, $unsafe_urls, 'every outbound Stripe API call is built from the constant API base URL' );
assertTrue( false !== strpos( $stripe_src, 'rawurlencode( $session_id )' ), 'the Stripe session id is URL encoded before it reaches the API' );

assertSame( '', MCR\Templates\Templates::locate( '../../../../etc/passwd', 'default' ), 'a traversal slug resolves to no template' );
assertSame( '', MCR\Templates\Templates::locate( 'checkin/../../evil', 'default' ), 'a traversal layout resolves to no template' );
assertSame( 'checkin', MCR\Templates\Templates::normalize_slug( '../../Checkin' ), 'slugs are normalised to a safe alphabet' );

/* ==========================================================================
 * 14. Privacy (control verified)
 * ======================================================================== */

mcr_group( '14. Privacy: the exporter and the eraser only touch the requested user' );

mcr_sec_reset();

$wpdb = $GLOBALS['wpdb'];

$wpdb->tables['wp_mcr_transactions'] = array(
	array(
		'id'             => 1,
		'user_id'        => 7,
		'txn_uid'        => 'uuid-1',
		'type'           => 'ad_reward',
		'amount'         => 5,
		'balance_after'  => 5,
		'reason'         => 'Rewarded ad',
		'source'         => 'ad',
		'ref_type'       => 'ad_session',
		'ref_id'         => 1,
		'idempotency_key' => 'ad_session:1',
		'ip'             => '',
		'user_agent'     => '',
		'created_at'     => gmdate( 'Y-m-d H:i:s' ),
	),
	array(
		'id'             => 2,
		'user_id'        => 8,
		'txn_uid'        => 'uuid-2',
		'type'           => 'ad_reward',
		'amount'         => 9,
		'balance_after'  => 9,
		'reason'         => 'Rewarded ad',
		'source'         => 'ad',
		'ref_type'       => 'ad_session',
		'ref_id'         => 2,
		'idempotency_key' => 'ad_session:2',
		'ip'             => '',
		'user_agent'     => '',
		'created_at'     => gmdate( 'Y-m-d H:i:s' ),
	),
);

$export = MCR\Integrations\Privacy::export( 'nobody@example.test' );
assertCount( 0, $export['data'], 'the exporter returns nothing for an unknown address' );

/* ==========================================================================
 * Summary
 * ======================================================================== */

echo "\n";
echo "====================================================\n";

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
