<?php
/**
 * MCR QA / adversarial STRESS suite.
 *
 * Run with: php tests/qa-stress.php
 *
 * Drives the REAL plugin classes (MCR\Ledger, MCR\Rewards\CheckIn,
 * MCR\Rewards\AdSessions, MCR\AdProviders\GenericS2S, MCR\Gateways\Manual,
 * MCR\Admin\DashboardPage) against an in-memory database that faithfully
 * implements the semantics the plugin depends on:
 *
 *  - the conditional UPDATE guard (WHERE balance >= N matches zero rows);
 *  - INSERT ... ON DUPLICATE KEY UPDATE;
 *  - the UNIQUE idempotency_key / user_day / session_token indexes, so a
 *    repeated grant really is a duplicate-key error;
 *  - transactions, so a ROLLBACK really undoes the write;
 *  - the schema column defaults, so a partially populated row behaves like
 *    the real MySQL table.
 *
 * The file EXITS 1 when a defect is proven.
 *
 * @package MCR
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/wp-stubs.php';

/* ==========================================================================
 * Extra WordPress stubs the plugin uses at runtime
 * ======================================================================== */

if ( ! function_exists( 'esc_url_raw' ) ) {
	/**
	 * Raw URL escaping stub.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	function esc_url_raw( $url ) {
		return filter_var( (string) $url, FILTER_SANITIZE_URL );
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
		return random_int( $min, $max );
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
		return sanitize_key( str_replace( ' ', '-', (string) $title ) );
	}
}

if ( ! function_exists( 'user_exists' ) ) {
	/**
	 * User existence stub.
	 *
	 * @param int $user_id User id.
	 * @return bool
	 */
	function user_exists( $user_id ) {
		return (int) $user_id > 0;
	}
}

if ( ! function_exists( 'rest_ensure_response' ) ) {
	/**
	 * Response stub.
	 *
	 * @param mixed $response Response.
	 * @return mixed
	 */
	function rest_ensure_response( $response ) {
		return $response;
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
		 * Constructor.
		 *
		 * @param string $body    Raw body.
		 * @param array  $headers Headers.
		 */
		public function __construct( $body = '', array $headers = array() ) {
			$this->body    = $body;
			$this->headers = $headers;

			foreach ( $headers as $key => $value ) {
				$this->headers[ strtolower( $key ) ] = $value;
			}
		}

		/**
		 * The raw body.
		 *
		 * @return string
		 */
		public function get_body() {
			return $this->body;
		}

		/**
		 * A header value.
		 *
		 * @param string $key Header name.
		 * @return string
		 */
		public function get_header( $key ) {
			$key = strtolower( (string) $key );

			return isset( $this->headers[ $key ] ) ? (string) $this->headers[ $key ] : '';
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
		 * The payload.
		 *
		 * @return mixed
		 */
		public function get_data() {
			return $this->data;
		}

		/**
		 * The HTTP status.
		 *
		 * @return int
		 */
		public function get_status() {
			return $this->status;
		}
	}
}

/* ==========================================================================
 * Load the real plugin classes
 * ======================================================================== */

$qa_base = dirname( __DIR__ ) . '/includes/';

require_once $qa_base . 'Support/Secrets.php';
require_once $qa_base . 'Support/RateLimiter.php';
require_once $qa_base . 'Support/Clock.php';
require_once $qa_base . 'Support/FixedClock.php';
require_once $qa_base . 'Support/WpClock.php';
require_once $qa_base . 'Ledger/BalanceMath.php';
require_once $qa_base . 'Ledger/LedgerInterface.php';
require_once $qa_base . 'Ledger.php';
require_once $qa_base . 'Settings.php';
require_once $qa_base . 'Installer.php';
require_once $qa_base . 'Container.php';
require_once $qa_base . 'Rewards/StreakCalculator.php';
require_once $qa_base . 'Rewards/CheckInPolicy.php';
require_once $qa_base . 'Rewards/AdRewardPolicy.php';
require_once $qa_base . 'Rewards/CheckIn.php';
require_once $qa_base . 'Rewards/AdSessions.php';
require_once $qa_base . 'AdProviders/Provider.php';
require_once $qa_base . 'AdProviders/GenericS2S.php';
require_once $qa_base . 'AdProviders/Registry.php';
require_once $qa_base . 'Payments/PackageCalculator.php';
require_once $qa_base . 'Payments/Packages.php';
require_once $qa_base . 'Payments/Orders.php';
require_once $qa_base . 'Gateways/Gateway.php';
require_once $qa_base . 'Gateways/Manual.php';
require_once $qa_base . 'Gateways/Registry.php';
require_once $qa_base . 'Capabilities.php';
require_once $qa_base . 'Admin/DashboardPage.php';

use MCR\Ledger;
use MCR\Payments\Packages;
use MCR\Rewards\AdSessions;
use MCR\Rewards\CheckIn;
use MCR\Settings;
use MCR\Support\FixedClock;

/* ==========================================================================
 * The in-memory database
 * ======================================================================== */

/**
 * A faithful in-memory wpdb for the tables the plugin owns.
 */
class QA_WPDB {

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
	 * Every query that was run.
	 *
	 * @var array
	 */
	public $queries = array();

	/**
	 * The in-memory tables.
	 *
	 * @var array
	 */
	public $tables = array(
		'balances'     => array(),
		'transactions' => array(),
		'checkins'     => array(),
		'ad_sessions'  => array(),
		'payments'     => array(),
	);

	/**
	 * Schema defaults per table.
	 *
	 * @var array
	 */
	public $defaults = array(
		'balances'     => array(
			'balance'         => 0,
			'lifetime_earned' => 0,
			'lifetime_spent'  => 0,
			'purchased_total' => 0,
			'updated_at'      => '1970-01-01 00:00:00',
		),
		'transactions' => array(
			'txn_uid'         => '',
			'type'            => '',
			'amount'          => 0,
			'balance_after'   => 0,
			'reason'          => '',
			'source'          => '',
			'ref_type'        => '',
			'ref_id'          => 0,
			'idempotency_key' => '',
			'ip'              => '',
			'user_agent'      => '',
			'created_at'      => '1970-01-01 00:00:00',
		),
		'checkins'     => array(
			'checkin_date'  => '1970-01-01',
			'streak_count'  => 1,
			'base_amount'   => 0,
			'bonus_amount'  => 0,
			'total_amount'  => 0,
			'transaction_id' => 0,
			'ip'            => '',
			'created_at'    => '1970-01-01 00:00:00',
		),
		'ad_sessions'  => array(
			'ad_id'           => '',
			'provider'        => '',
			'session_token'   => '',
			'completion_token' => '',
			'day_key'         => '1970-01-01',
			'status'          => 'pending',
			'coins'           => 0,
			'ip'              => '',
			'created_at'      => '1970-01-01 00:00:00',
			'expires_at'      => '1970-01-01 00:00:00',
			'verified_at'     => '1970-01-01 00:00:00',
		),
		'payments'     => array(
			'package_id'    => '',
			'gateway'       => '',
			'order_id'      => '',
			'provider_tx_id' => '',
			'amount'        => 0,
			'currency'      => '',
			'coins_total'   => 0,
			'status'        => 'pending',
			'user_ip'       => '',
			'user_agent'    => '',
			'created_at'    => '1970-01-01 00:00:00',
			'paid_at'       => '0000-00-00 00:00:00',
			'expires_at'    => '1970-01-01 00:00:00',
		),
	);

	/**
	 * Next auto increment id per table.
	 *
	 * @var array
	 */
	private $next_id = array(
		'balances'     => 1,
		'transactions' => 1,
		'checkins'     => 1,
		'ad_sessions'  => 1,
		'payments'     => 1,
	);

	/**
	 * Fault injection: fail the next transaction insert of this type.
	 *
	 * @var string
	 */
	public $fail_transaction_type = '';

	/**
	 * Fault injection: fail every transaction insert of this type.
	 *
	 * @var string
	 */
	public $fail_transaction_type_always = '';

	/**
	 * Whether a transaction is open.
	 *
	 * @var bool
	 */
	public $in_transaction = false;

	/**
	 * Snapshot taken at the last BEGIN.
	 *
	 * @var array
	 */
	private $snapshot = array();

	/**
	 * Number of transactions started.
	 *
	 * @var int
	 */
	public $transactions_started = 0;

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
		$this->queries[] = $sql;

		$sql = trim( preg_replace( '/\s+/', ' ', (string) $sql ) );

		if ( 'START TRANSACTION' === strtoupper( $sql ) ) {
			$this->in_transaction = true;
			++$this->transactions_started;
			$this->snapshot      = $this->tables;

			return 1;
		}

		if ( 'COMMIT' === strtoupper( $sql ) ) {
			$this->in_transaction = false;

			return 1;
		}

		if ( 'ROLLBACK' === strtoupper( $sql ) ) {
			if ( $this->in_transaction && ! empty( $this->snapshot ) ) {
				$this->tables = $this->snapshot;
			}

			$this->in_transaction = false;

			return 1;
		}

		$this->last_error = '';

		if ( 0 === strpos( strtoupper( $sql ), 'INSERT INTO ' ) ) {
			return $this->qa_insert( $sql );
		}

		if ( 0 === strpos( strtoupper( $sql ), 'UPDATE ' ) ) {
			return $this->qa_update( $sql );
		}

		if ( 0 === strpos( strtoupper( $sql ), 'DELETE FROM ' ) ) {
			return $this->qa_delete( $sql );
		}

		$this->rows_affected = 0;

		return 0;
	}

	/**
	 * Handles an INSERT.
	 *
	 * @param string $sql SQL.
	 * @return int|false
	 */
	private function qa_insert( $sql ) {
		$dup = '';

		if ( preg_match( '/^(.*?)\s*ON DUPLICATE KEY UPDATE\s*(.*)$/is', $sql, $m ) ) {
			$sql = $m[1];
			$dup = $m[2];
		}

		if ( ! preg_match( '/^INSERT INTO (\S+)\s*\(([^)]*)\)\s*VALUES\s*\((.*)\)$/is', $sql, $m ) ) {
			return false;
		}

		$table = $this->qa_table( $m[1] );
		$cols  = array_map( 'trim', explode( ',', $m[2] ) );
		$vals  = $this->qa_split( $m[3] );

		$row = array();

		foreach ( $cols as $i => $col ) {
			$row[ $col ] = array_key_exists( $i, $vals ) ? $this->qa_literal( $vals[ $i ] ) : null;
		}

		/*
		 * Fault injection: a transaction insert of a given type fails the
		 * way a real database failure does (false, not a duplicate error).
		 */
		if ( 'transactions' === $table ) {
			$type = (string) ( $row['type'] ?? '' );

			if ( $this->fail_transaction_type_always === $type
				|| ( $this->fail_transaction_type === $type && '' !== $this->fail_transaction_type ) ) {
				if ( '' === $this->fail_transaction_type_always ) {
					$this->fail_transaction_type = '';
				}

				$this->last_error    = 'Table \'wp_mcr_transactions\' is full';
				$this->rows_affected = 0;

				return false;
			}
		}

		$conflict = $this->qa_unique_conflict( $table, $row );

		if ( null !== $conflict ) {
			$this->last_error    = "Duplicate entry '" . (string) $row[ $conflict ] . "' for key '" . $conflict . "'";
			$this->rows_affected = 0;

			return false;
		}

		$id = $this->next_id[ $table ]++;

		$row = array_merge( $this->defaults[ $table ], $row );
		$row['id']      = $id;
		$row['user_id'] = isset( $row['user_id'] ) ? (int) $row['user_id'] : 0;

		if ( 'balances' === $table ) {
			$user_id = (int) $row['user_id'];

			if ( isset( $this->tables['balances'][ $user_id ] ) ) {
				if ( '' === $dup ) {
					$this->last_error = 'Duplicate entry';

					return false;
				}

				$existing = $this->tables['balances'][ $user_id ];

				foreach ( $this->qa_assignments( $dup ) as $col => $expr ) {
					$existing[ $col ] = $this->qa_eval( $expr, $existing );
				}

				$this->tables['balances'][ $user_id ] = $existing;
				$this->rows_affected                = 2;
				$this->insert_id                    = $id;

				return 2;
			}

			$this->tables['balances'][ $user_id ] = $row;
			$this->rows_affected                = 1;
			$this->insert_id                    = $id;

			return 1;
		}

		$this->tables[ $table ][] = $row;
		$this->rows_affected       = 1;
		$this->insert_id           = $id;

		return 1;
	}

	/**
	 * Handles an UPDATE.
	 *
	 * @param string $sql SQL.
	 * @return int
	 */
	private function qa_update( $sql ) {
		if ( ! preg_match( '/^UPDATE (\S+)\s+SET\s+(.*?)\s+WHERE\s+(.*)$/is', $sql, $m ) ) {
			return false;
		}

		$table = $this->qa_table( $m[1] );
		$set   = $this->qa_assignments( $m[2] );
		$conds = $this->qa_conditions( $m[3] );

		$affected = 0;

		foreach ( $this->tables[ $table ] as $index => $row ) {
			if ( ! $this->qa_matches( $row, $conds ) ) {
				continue;
			}

			foreach ( $set as $col => $expr ) {
				$row[ $col ] = $this->qa_eval( $expr, $row );
			}

			$this->tables[ $table ][ $index ] = $row;
			++$affected;
		}

		$this->rows_affected = $affected;

		return $affected;
	}

	/**
	 * Handles a DELETE.
	 *
	 * @param string $sql SQL.
	 * @return int
	 */
	private function qa_delete( $sql ) {
		if ( ! preg_match( '/^DELETE FROM (\S+)\s+WHERE\s+(.*)$/is', $sql, $m ) ) {
			return false;
		}

		$table = $this->qa_table( $m[1] );
		$conds = $this->qa_conditions( $m[2] );

		$kept = array();
		$gone = 0;

		foreach ( $this->tables[ $table ] as $row ) {
			if ( $this->qa_matches( $row, $conds ) ) {
				++$gone;
				continue;
			}

			$kept[] = $row;
		}

		$this->tables[ $table ] = $kept;
		$this->rows_affected    = $gone;

		return $gone;
	}

	/**
	 * Selects rows.
	 *
	 * @param string $sql  SQL.
	 * @param string $type Type.
	 * @return array
	 */
	public function get_results( $sql, $type = 'OBJECT' ) {
		$rows = $this->qa_select( $sql );

		if ( 'ARRAY_A' === $type ) {
			return $rows;
		}

		return array_map(
			static function ( $row ) {
				return (object) $row;
			},
			$rows
		);
	}

	/**
	 * Selects a single row.
	 *
	 * @param string $sql  SQL.
	 * @param string $type Type.
	 * @return mixed
	 */
	public function get_row( $sql, $type = 'OBJECT' ) {
		$rows = $this->qa_select( $sql );

		if ( empty( $rows ) ) {
			return null;
		}

		return 'ARRAY_A' === $type ? $rows[0] : (object) $rows[0];
	}

	/**
	 * Selects a single value.
	 *
	 * @param string $sql SQL.
	 * @return mixed
	 */
	public function get_var( $sql ) {
		$this->queries[] = $sql;

		$sql = trim( preg_replace( '/\s+/', ' ', (string) $sql ) );

		if ( preg_match( "/^SELECT GET_LOCK\(/i", $sql ) ) {
			return '1';
		}

		if ( preg_match( "/^SELECT RELEASE_LOCK\(/i", $sql ) ) {
			return '1';
		}

		$rows = $this->qa_select( $sql );

		if ( empty( $rows ) ) {
			return null;
		}

		$first = reset( $rows );

		return is_array( $first ) ? reset( $first ) : null;
	}

	/**
	 * Selects a column.
	 *
	 * @param string $sql SQL.
	 * @return array
	 */
	public function get_col( $sql ) {
		$rows = $this->qa_select( $sql );
		$out  = array();

		foreach ( $rows as $row ) {
			$out[] = is_array( $row ) ? reset( $row ) : null;
		}

		return $out;
	}

	/**
	 * Updates a table through the wpdb::update() API.
	 *
	 * @param string $table        Table.
	 * @param array  $data         Data.
	 * @param array  $where        Conditions.
	 * @param array  $format       Formats.
	 * @param array  $where_format Where formats.
	 * @return int
	 */
	public function update( $table, $data, $where, $format = null, $where_format = null ) {
		$key = $this->qa_table( $table );

		$set = array();

		foreach ( $data as $col => $value ) {
			$set[] = $col . ' = ' . $this->qa_quote( $value );
		}

		$conds = array();

		foreach ( $where as $col => $value ) {
			$conds[] = array( $col, '=', $value );
		}

		$sql = 'UPDATE ' . $table . ' SET ' . implode( ', ', $set ) . ' WHERE ' . $this->qa_condition_sql( $conds );

		return (int) $this->qa_update( $sql );
	}

	/**
	 * The in-memory table behind a SQL table name.
	 *
	 * @param string $name SQL table name.
	 * @return string
	 */
	private function qa_table( $name ) {
		$name = trim( (string) $name, '`' );

		if ( false !== strpos( $name, 'mcr_' ) ) {
			$name = substr( $name, strpos( $name, 'mcr_' ) + 4 );
		}

		return $name;
	}

	/**
	 * Splits a VALUES list.
	 *
	 * Quotes are KEPT so that a string literal stays distinguishable from a
	 * column reference (`status = 'verified'` vs `total = base_amount`).
	 *
	 * @param string $list Raw list.
	 * @return array
	 */
	private function qa_split( $list ) {
		$parts = array();
		$buf   = '';
		$quote = false;
		$depth = 0;

		for ( $i = 0; $i < strlen( $list ); $i++ ) {
			$char = $list[ $i ];

			if ( '\\' === $char && $quote ) {
				$buf .= $char;
				++$i;

				if ( $i < strlen( $list ) ) {
					$buf .= $list[ $i ];
				}

				continue;
			}

			if ( "'" === $char ) {
				$quote = ! $quote;
				$buf  .= $char;
				continue;
			}

			if ( ! $quote ) {
				if ( '(' === $char ) {
					++$depth;
				} elseif ( ')' === $char ) {
					--$depth;
				} elseif ( ',' === $char && 0 === $depth ) {
					$parts[] = trim( $buf );
					$buf     = '';
					continue;
				}
			}

			$buf .= $char;
		}

		if ( '' !== trim( $buf ) ) {
			$parts[] = trim( $buf );
		}

		return $parts;
	}

	/**
	 * Converts a raw SQL literal into a PHP value.
	 *
	 * @param string $raw Raw literal.
	 * @return mixed
	 */
	private function qa_literal( $raw ) {
		$raw = trim( (string) $raw );

		if ( 'NULL' === strtoupper( $raw ) ) {
			return null;
		}

		if ( '' !== $raw && ( "'" === $raw[0] ) ) {
			$raw = substr( $raw, 1, -1 );
			$raw = stripslashes( $raw );

			/*
			 * MySQL stores a VARCHAR up to the first NUL byte, so a key
			 * carrying a NUL is silently truncated in the database.
			 */
			$nul = strpos( $raw, "\0" );

			if ( false !== $nul ) {
				$raw = substr( $raw, 0, $nul );
			}

			return $raw;
		}

		if ( is_numeric( $raw ) ) {
			return $raw + 0;
		}

		return $raw;
	}

	/**
	 * Quotes a PHP value as a SQL literal.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private function qa_quote( $value ) {
		if ( null === $value ) {
			return 'NULL';
		}

		if ( is_int( $value ) || is_float( $value ) ) {
			return (string) $value;
		}

		return "'" . addslashes( (string) $value ) . "'";
	}

	/**
	 * Parses a SET clause into column => expression.
	 *
	 * @param string $set SET clause.
	 * @return array
	 */
	private function qa_assignments( $set ) {
		$out = array();

		foreach ( $this->qa_split( $set ) as $piece ) {
			if ( false === strpos( $piece, '=' ) ) {
				continue;
			}

			list( $col, $expr ) = explode( '=', $piece, 2 );

			$out[ trim( $col ) ] = trim( $expr );
		}

		return $out;
	}

	/**
	 * Evaluates a SET expression against a row.
	 *
	 * @param string $expr Expression.
	 * @param array  $row  Row.
	 * @return mixed
	 */
	private function qa_eval( $expr, array $row ) {
		$expr = trim( (string) $expr );

		/*
		 * A quoted SQL string literal evaluates to its (unquoted) value.
		 * This must be checked BEFORE stripping quotes, otherwise a string
		 * such as 'verified' would be mistaken for a column reference.
		 */
		if ( 1 === preg_match( "/^'(.*)'$/s", $expr, $m ) ) {
			return stripslashes( $m[1] );
		}

		if ( 1 === preg_match( '/^"(.*)"$/s', $expr, $m ) ) {
			return stripslashes( $m[1] );
		}

		/*
		 * The harness quotes the values it substitutes, so the quotes are
		 * removed before the expression is matched.
		 */
		$expr = str_replace( array( "'", '"' ), '', $expr );

		if ( preg_match( '/^GREATEST\(\s*(\w+)\s*-\s*(-?\d+)\s*,\s*0\s*\)$/', $expr, $m ) ) {
			return max( 0, (int) ( $row[ $m[1] ] ?? 0 ) - (int) $m[2] );
		}

		if ( preg_match( '/^(\w+)\s*\+\s*(-?\d+(?:\.\d+)?)$/', $expr, $m ) ) {
			return (int) ( $row[ $m[1] ] ?? 0 ) + (int) $m[2];
		}

		if ( preg_match( '/^(\w+)\s*-\s*(-?\d+(?:\.\d+)?)$/', $expr, $m ) ) {
			return (int) ( $row[ $m[1] ] ?? 0 ) - (int) $m[2];
		}

		if ( is_numeric( $expr ) ) {
			return $this->qa_literal( $expr );
		}

		if ( preg_match( '/^(\w+)$/', $expr, $m ) ) {
			return $row[ $m[1] ] ?? null;
		}

		return $this->qa_literal( $expr );
	}

	/**
	 * Parses a WHERE clause into conditions.
	 *
	 * @param string $where WHERE clause.
	 * @return array
	 */
	private function qa_conditions( $where ) {
		$conds = array();

		if ( preg_match_all( "/(\w+)\s*(>=|<=|<>|!=|=|>|<)\s*('(?:[^']*)'|-?\d+(?:\.\d+)?|NULL)/i", (string) $where, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $match ) {
				$conds[] = array( $match[1], $match[2], $this->qa_literal( $match[3] ) );
			}
		}

		return $conds;
	}

	/**
	 * Renders conditions back into SQL (for wpdb::update()).
	 *
	 * @param array $conds Conditions.
	 * @return string
	 */
	private function qa_condition_sql( array $conds ) {
		$parts = array();

		foreach ( $conds as $cond ) {
			$parts[] = $cond[0] . ' ' . $cond[1] . ' ' . $this->qa_quote( $cond[2] );
		}

		return implode( ' AND ', $parts );
	}

	/**
	 * Whether a row matches a condition list.
	 *
	 * @param array $row   Row.
	 * @param array $conds Conditions.
	 * @return bool
	 */
	private function qa_matches( array $row, array $conds ) {
		foreach ( $conds as $cond ) {
			$actual = array_key_exists( $cond[0], $row ) ? $row[ $cond[0] ] : null;

			if ( null === $actual || null === $cond[2] ) {
				return false;
			}

			$a = $actual;
			$b = $cond[2];

			if ( is_numeric( $a ) && is_numeric( $b ) ) {
				$a = (float) $a;
				$b = (float) $b;
			}

			switch ( $cond[1] ) {
				case '=':
					$ok = ( $a == $b ); // phpcs:ignore
					break;
				case '<>':
				case '!=':
					$ok = ( $a != $b ); // phpcs:ignore
					break;
				case '>':
					$ok = ( $a > $b );
					break;
				case '<':
					$ok = ( $a < $b );
					break;
				case '>=':
					$ok = ( $a >= $b );
					break;
				case '<=':
					$ok = ( $a <= $b );
					break;
				default:
					$ok = false;
			}

			if ( ! $ok ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Detects a UNIQUE index violation.
	 *
	 * @param string $table Table key.
	 * @param array  $row   Row.
	 * @return string|null The conflicting column, or null.
	 */
	private function qa_unique_conflict( $table, array $row ) {
		$indexes = array(
			'transactions' => array( array( 'idempotency_key' ), array( 'txn_uid' ) ),
			'checkins'     => array( array( 'user_id', 'checkin_date' ) ),
			'ad_sessions'  => array( array( 'session_token' ) ),
			'payments'     => array( array( 'order_id' ), array( 'gateway', 'provider_tx_id' ) ),
		);

		if ( ! isset( $indexes[ $table ] ) ) {
			return null;
		}

		foreach ( $indexes[ $table ] as $index ) {
			foreach ( $this->tables[ $table ] as $existing ) {
				$same = true;

				foreach ( $index as $col ) {
					if ( (string) ( $existing[ $col ] ?? '' ) !== (string) ( $row[ $col ] ?? '' ) ) {
						$same = false;
						break;
					}
				}

				if ( $same ) {
					return $index[0];
				}
			}
		}

		return null;
	}

	/**
	 * Runs a SELECT.
	 *
	 * @param string $sql SQL.
	 * @return array
	 */
	private function qa_select( $sql ) {
		$sql = trim( preg_replace( '/\s+/', ' ', (string) $sql ) );

		if ( preg_match( "/^SHOW TABLE STATUS LIKE '([^']+)'/i", $sql, $m ) ) {
			return array(
				array(
					'Name'   => $m[1],
					'Engine' => 'InnoDB',
				),
			);
		}

		if ( ! preg_match( '/^SELECT (.+?) FROM (\S+)(.*)$/is', $sql, $m ) ) {
			return array();
		}

		$list  = trim( $m[1] );
		$table = $this->qa_table( $m[2] );
		$rest  = $m[3];

		$where = '';
		$order = '';
		$limit = null;
		$off   = 0;

		if ( preg_match( '/ WHERE (.*?)(?= ORDER BY | LIMIT | OFFSET |$)/is', $rest, $m2 ) ) {
			$where = trim( $m2[1] );
		}

		if ( preg_match( '/ ORDER BY (.+?)(?= LIMIT | OFFSET |$)/is', $rest, $m3 ) ) {
			$order = trim( $m3[1] );
		}

		if ( preg_match( '/ LIMIT (\d+)/i', $rest, $m4 ) ) {
			$limit = (int) $m4[1];
		}

		if ( preg_match( '/ OFFSET (\d+)/i', $rest, $m5 ) ) {
			$off = (int) $m5[1];
		}

		$rows = isset( $this->tables[ $table ] ) ? array_values( $this->tables[ $table ] ) : array();
		$rows = $this->qa_filter( $rows, $this->qa_conditions( $where ) );

		if ( '' !== $order ) {
			$rows = $this->qa_sort( $rows, $order );
		}

		if ( null !== $limit ) {
			$rows = array_slice( $rows, $off, $limit );
		} elseif ( $off > 0 ) {
			$rows = array_slice( $rows, $off );
		}

		if ( preg_match( '/^COUNT\(\*\)$/i', $list ) ) {
			return array( array( 'COUNT(*)' => count( $rows ) ) );
		}

		if ( preg_match( '/^COALESCE\(SUM\((\w+)\),\s*0\)$/i', $list, $m6 ) ) {
			$sum = 0;

			foreach ( $rows as $row ) {
				$sum += (float) ( $row[ $m6[1] ] ?? 0 );
			}

			return array( array( $list => (int) $sum ) );
		}

		if ( preg_match( '/^COALESCE\(SUM\(CASE WHEN (.+?) THEN (.+?) ELSE 0 END\),\s*0\)$/is', $list, $mcase ) ) {
			$sum = 0;

			foreach ( $rows as $row ) {
				if ( $this->qa_eval_case_condition( $mcase[1], $row ) ) {
					$sum += $this->qa_eval_case_value( $mcase[2], $row );
				}
			}

			return array( array( $list => (int) $sum ) );
		}

		if ( preg_match( '/UNIX_TIMESTAMP\(MAX\((\w+)\)\)/i', $list, $m7 ) ) {
			$max = null;

			foreach ( $rows as $row ) {
				$value = $row[ $m7[1] ] ?? null;

				if ( null === $value || '' === $value ) {
					continue;
				}

				if ( null === $max || $value > $max ) {
					$max = $value;
				}
			}

			return array( array( $list => null === $max ? null : (int) strtotime( $max . ' UTC' ) ) );
		}

		if ( preg_match( '/\bCOUNT\s*\(\s*\*\s*\)|COALESCE\s*\(\s*SUM\s*\(/i', $list ) ) {
			$cols   = array_map( 'trim', $this->qa_split( $list ) );
			$result = array();

			foreach ( $cols as $col ) {
				$alias = $col;
				$expr  = $col;

				if ( preg_match( '/^(.+?)\s+(?:AS|as)\s+`?(\w+)`?$/i', $col, $ma ) ) {
					$expr  = trim( $ma[1] );
					$alias = $ma[2];
				}

				if ( preg_match( '/^COUNT\s*\(\s*\*\s*\)$/i', $expr ) ) {
					$result[ $alias ] = count( $rows );
				} elseif ( preg_match( '/^COALESCE\s*\(\s*SUM\s*\(\s*(.+?)\s*\)\s*,\s*0\s*\)$/is', $expr, $m ) ) {
					$inner = trim( $m[1] );

					if ( 0 === stripos( $inner, 'CASE' ) ) {
						if ( preg_match( '/^CASE WHEN (.+?) THEN (.+?) ELSE 0 END$/is', $inner, $mc ) ) {
							$sum = 0;

							foreach ( $rows as $row ) {
								if ( $this->qa_eval_case_condition( $mc[1], $row ) ) {
									$sum += $this->qa_eval_case_value( $mc[2], $row );
								}
							}

							$result[ $alias ] = (int) $sum;
						} else {
							$result[ $alias ] = 0;
						}
					} else {
						$sum = 0;

						foreach ( $rows as $row ) {
							$sum += (float) ( $row[ $inner ] ?? 0 );
						}

						$result[ $alias ] = (int) $sum;
					}
				} else {
					$result[ $alias ] = null;
				}
			}

			return array( $result );
		}

		if ( '*' === $list ) {
			return $rows;
		}

		$cols = array_map( 'trim', explode( ',', $list ) );
		$out  = array();

		foreach ( $rows as $row ) {
			$projected = array();

			foreach ( $cols as $col ) {
				$projected[ $col ] = array_key_exists( $col, $row ) ? $row[ $col ] : null;
			}

			$out[] = $projected;
		}

		return $out;
	}

	/**
	 * Evaluates a CASE condition for the reconcile aggregates.
	 *
	 * @param string $cond Condition text.
	 * @param array  $row  Row.
	 * @return bool
	 */
	private function qa_eval_case_condition( $cond, array $row ) {
		$cond = trim( (string) $cond );

		// Split on OR (top level; no nesting in these queries).
		$or_parts = preg_split( '/\s+OR\s+/i', $cond );

		foreach ( $or_parts as $or_part ) {
			$and_parts = preg_split( '/\s+AND\s+/i', trim( $or_part ) );
			$ok        = true;

			foreach ( $and_parts as $atom ) {
				$atom = trim( $atom );

				if ( preg_match( '/^amount\s*>\s*0$/i', $atom ) ) {
					$val = $row['amount'] ?? 0;
					$ok  = $ok && ( (float) $val > 0 );

					continue;
				}

				if ( preg_match( '/^amount\s*<\s*0$/i', $atom ) ) {
					$val = $row['amount'] ?? 0;
					$ok  = $ok && ( (float) $val < 0 );

					continue;
				}

				if ( preg_match( '/^type\s+IN\s*\((.*)\)$/i', $atom, $m ) ) {
					$list = $this->qa_parse_in_list( $m[1] );
					$ok   = $ok && in_array( (string) ( $row['type'] ?? '' ), $list, true );

					continue;
				}

				if ( preg_match( '/^type\s+NOT\s+IN\s*\((.*)\)$/i', $atom, $m ) ) {
					$list = $this->qa_parse_in_list( $m[1] );
					$ok   = $ok && ! in_array( (string) ( $row['type'] ?? '' ), $list, true );

					continue;
				}

				// Unknown atom: fail closed.
				$ok = false;
			}

			if ( $ok ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Parses a SQL IN list into PHP strings.
	 *
	 * @param string $list List text.
	 * @return array
	 */
	private function qa_parse_in_list( $list ) {
		$out = array();

		if ( preg_match_all( "/'([^']*)'/", (string) $list, $m ) ) {
			foreach ( $m[1] as $v ) {
				$out[] = stripslashes( $v );
			}
		}

		return $out;
	}

	/**
	 * Evaluates a CASE THEN expression.
	 *
	 * @param string $expr Expression.
	 * @param array  $row  Row.
	 * @return int|float
	 */
	private function qa_eval_case_value( $expr, array $row ) {
		$expr = trim( (string) $expr );

		if ( '-amount' === $expr || '- amount' === $expr ) {
			return 0 - (float) ( $row['amount'] ?? 0 );
		}

		if ( 'amount' === $expr ) {
			return (float) ( $row['amount'] ?? 0 );
		}

		return (float) $this->qa_literal( $expr );
	}

	/**
	 * Filters rows.
	 *
	 * @param array $rows  Rows.
	 * @param array $conds Conditions.
	 * @return array
	 */
	private function qa_filter( array $rows, array $conds ) {
		if ( empty( $conds ) ) {
			return $rows;
		}

		$kept = array();

		foreach ( $rows as $row ) {
			if ( $this->qa_matches( $row, $conds ) ) {
				$kept[] = $row;
			}
		}

		return $kept;
	}

	/**
	 * Sorts rows.
	 *
	 * @param array  $rows  Rows.
	 * @param string $order ORDER BY clause.
	 * @return array
	 */
	private function qa_sort( array $rows, $order ) {
		$desc = ( false !== stripos( $order, 'DESC' ) );
		$col  = preg_replace( '/\s+(ASC|DESC)$/i', '', trim( $order ) );

		usort(
			$rows,
			static function ( $a, $b ) use ( $col, $desc ) {
				$left  = $a[ $col ] ?? null;
				$right = $b[ $col ] ?? null;

				if ( $left == $right ) { // phpcs:ignore
					return 0;
				}

				$result = ( $left < $right ) ? -1 : 1; // phpcs:ignore

				return $desc ? -$result : $result;
			}
		);

		return $rows;
	}
}

/**
 * A controllable ad provider used as a control for the S2S path.
 */
class QA_Provider extends \MCR\AdProviders\Provider {

	/**
	 * What verify_completion() returns.
	 *
	 * @var bool
	 */
	public $verification = true;

	/**
	 * {@inheritDoc}
	 */
	public function get_id() {
		return 'qa_test';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title() {
		return 'QA test provider';
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_configured() {
		return true;
	}

	/**
	 * {@inheritDoc}
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
	 * {@inheritDoc}
	 */
	public function create_session( array $args ) {
		return array(
			'ad_unit'      => $this->get_ad_units()[0],
			'sdk_params'   => array(),
			'reward_coins' => absint( $args['reward_coins'] ?? 0 ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function verify_completion( array $payload ) {
		return $this->verification;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_settings_fields() {
		return array();
	}
}

/* ==========================================================================
 * Test helpers
 * ======================================================================== */

/**
 * Resets the in-memory world between test groups.
 *
 * @param QA_WPDB $wpdb Database.
 * @return void
 */
function qa_reset_world( QA_WPDB $wpdb ) {
	$wpdb->tables = array(
		'balances'     => array(),
		'transactions' => array(),
		'checkins'     => array(),
		'ad_sessions'  => array(),
		'payments'     => array(),
	);

	$wpdb->last_error               = '';
	$wpdb->insert_id                = 0;
	$wpdb->rows_affected            = 0;
	$wpdb->fail_transaction_type     = '';
	$wpdb->fail_transaction_type_always = '';
	$wpdb->queries                  = array();

	Settings::flush_cache();

	$GLOBALS['mcr_options']['mcr_settings'] = array();
	$GLOBALS['mcr_options']                = array( 'mcr_settings' => array() );

	Settings::flush_cache();

	if ( class_exists( 'MCR\\Ledger' ) ) {
		\MCR\Ledger::instance()->reset_balance_cache();
	}

	if ( class_exists( 'MCR\\Rewards\\CheckIn' ) ) {
		\MCR\Rewards\CheckIn::instance()->reset_status_cache();
	}
}

/**
 * Applies a settings patch.
 *
 * @param array $values Settings.
 * @return void
 */
function qa_set_settings( array $values ) {
	Settings::update( $values );
	Settings::flush_cache();
}

/**
 * Seeds a check-in row directly.
 *
 * @param QA_WPDB $wpdb   Database.
 * @param int     $user   User id.
 * @param string  $date   Day key.
 * @param int     $streak Streak.
 * @param int     $total  Total.
 * @return void
 */
function qa_seed_checkin( QA_WPDB $wpdb, $user, $date, $streak = 1, $total = 10 ) {
	$wpdb->tables['checkins'][] = array(
		'id'             => count( $wpdb->tables['checkins'] ) + 1,
		'user_id'        => (int) $user,
		'checkin_date'   => $date,
		'streak_count'   => (int) $streak,
		'base_amount'    => (int) $total,
		'bonus_amount'   => 0,
		'total_amount'   => (int) $total,
		'transaction_id' => 0,
		'ip'             => '',
		'created_at'     => '2026-01-01 00:00:00',
	);
}

/**
 * Finds a check-in row.
 *
 * @param QA_WPDB $wpdb Database.
 * @param int     $user User id.
 * @param string  $date Day key.
 * @return array|null
 */
function qa_find_checkin( QA_WPDB $wpdb, $user, $date ) {
	foreach ( $wpdb->tables['checkins'] as $row ) {
		if ( (int) $row['user_id'] === (int) $user && $row['checkin_date'] === $date ) {
			return $row;
		}
	}

	return null;
}

/**
 * Finds an ad session row.
 *
 * @param QA_WPDB $wpdb Database.
 * @param string  $token Session token.
 * @return array|null
 */
function qa_find_session( QA_WPDB $wpdb, $token ) {
	foreach ( $wpdb->tables['ad_sessions'] as $row ) {
		if ( $row['session_token'] === $token ) {
			return $row;
		}
	}

	return null;
}

/**
 * Returns the error code of a result, or a marker when it is not an error.
 *
 * @param mixed $thing Result.
 * @return string
 */
function qa_error_code( $thing ) {
	if ( is_wp_error( $thing ) ) {
		return $thing->get_error_code();
	}

	return '(no error)';
}

echo "MCR QA - adversarial stress / state machine\n";
echo "=========================================\n";

global $wpdb;

$wpdb = new QA_WPDB();

// The provider registry must be primed before anything asks for a provider.
add_filter(
	'mcr_register_ad_providers',
	static function ( $instances ) {
		$instances['qa_test'] = new QA_Provider();

		return $instances;
	}
);

$qa_clock = new FixedClock( '2026-01-15 10:00:00', 'UTC' );

\MCR\Container::instance()->set_clock( $qa_clock );
Ledger::instance()->set_clock( $qa_clock );

/* ==========================================================================
 * 1. The ad cap with the built-in S2S provider
 * ======================================================================== */

mcr_group( 'Ad cap: the S2S provider writes a status the cap never counts' );

qa_reset_world( $wpdb );

/*
 * The S2S postback compares the session expiry against the WALL CLOCK
 * (GenericS2S.php:473), while the session expiry is written from the
 * injected clock. Both are the same instant in production, so the group
 * runs on a clock pinned to the real moment to keep the flow alive.
 */
$qa_s2s_clock = new FixedClock( gmdate( 'Y-m-d H:i:s' ), 'UTC' );

\MCR\Container::instance()->set_clock( $qa_s2s_clock );
Ledger::instance()->set_clock( $qa_s2s_clock );

qa_set_settings(
	array(
		'ads.provider'                => 'generic_s2s',
		'ads.coins_per_ad'            => 5,
		'ads.daily_limit'             => 1,
		'ads.global_daily_limit'      => 0,
		'ads.cooldown_seconds'        => 300,
		'ads.session_ttl'             => 900,
		'ads.providers.generic_s2s'   => array(
			'postback_secret'    => 'qa-secret',
			'signature_header'   => 'X-MCR-Signature',
			'timestamp_header'   => 'X-MCR-Timestamp',
			'transaction_id_field' => 'transaction_id',
		),
		'security.log_ip'             => 'off',
	)
);

$qa_session = AdSessions::instance()->start( 7 );

assertFalse( is_wp_error( $qa_session ), 'an ad session starts for the S2S provider' );
assertSame( 64, strlen( $qa_session['session_token'] ), 'the session token is a 64 character hex string' );

$qa_raw = wp_json_encode(
	array(
		'session_token'  => $qa_session['session_token'],
		'transaction_id' => 'qa-tx-0001',
	)
);

$qa_request = new WP_REST_Request(
	$qa_raw,
	array(
		'x-mcr-signature' => hash_hmac( 'sha256', $qa_raw, 'qa-secret' ),
		'x-mcr-timestamp' => (string) time(),
	)
);

$qa_response = \MCR\AdProviders\Registry::get( 'generic_s2s' )->handle_postback( $qa_request );
$qa_data     = $qa_response->get_data();

assertSame( true, $qa_data['credited'], 'the signed postback credits the reward' );
assertSame( 5, Ledger::instance()->get_balance( 7 ), 'the coins landed in the ledger' );

$qa_row = qa_find_session( $wpdb, $qa_session['session_token'] );

assertSame( 'credited', $qa_row['status'], 'the S2S provider marks the session as credited' );

$qa_cap = AdSessions::instance()->cap_guard( 7, 0 );

assertTrue(
	is_wp_error( $qa_cap ),
	'the daily ad cap blocks a user who already watched the maximum number of ads today',
	'cap_guard() returned ' . qa_error_code( $qa_cap )
);

$qa_stats = AdSessions::instance()->stats( 7 );

assertSame( 1, $qa_stats['today'], 'the ad statistics count the rewarded view of today' );
assertSame( 0, $qa_stats['remaining'], 'the remaining ad allowance is zero after the cap was reached' );

$qa_again = AdSessions::instance()->start( 7 );

assertTrue(
	is_wp_error( $qa_again ),
	'a user at the daily ad cap cannot start another ad session',
	'start() returned ' . qa_error_code( $qa_again )
);

mcr_group( 'Ad cap: CONTROL - the client verification path counts correctly' );

qa_reset_world( $wpdb );

\MCR\Container::instance()->set_clock( $qa_clock );
Ledger::instance()->set_clock( $qa_clock );

qa_set_settings(
	array(
		'ads.provider'           => 'qa_test',
		'ads.coins_per_ad'       => 5,
		'ads.daily_limit'        => 1,
		'ads.global_daily_limit' => 0,
		'ads.cooldown_seconds'   => 0,
		'ads.session_ttl'        => 900,
		'security.log_ip'        => 'off',
	)
);

$qa_session = AdSessions::instance()->start( 7 );
assertFalse( is_wp_error( $qa_session ), 'an ad session starts for the control provider' );

$qa_verified = AdSessions::instance()->verify(
	7,
	array(
		'session_token'    => $qa_session['session_token'],
		'completion_token' => 'qa-proof-1',
	)
);

assertFalse( is_wp_error( $qa_verified ), 'the client verification path grants the reward' );
assertSame( 5, Ledger::instance()->get_balance( 7 ), 'the coins landed in the ledger' );

$qa_row = qa_find_session( $wpdb, $qa_session['session_token'] );
assertSame( 'verified', $qa_row['status'], 'the client path marks the session as verified' );

$qa_cap = AdSessions::instance()->cap_guard( 7, 0 );
assertTrue( is_wp_error( $qa_cap ), 'the daily ad cap blocks the second ad of the day' );
assertSame( 'mcr_daily_limit_reached', qa_error_code( $qa_cap ), 'the refusal names the daily limit' );

$qa_stats = AdSessions::instance()->stats( 7 );
assertSame( 1, $qa_stats['today'], 'the ad statistics count the rewarded view of today' );

$qa_again = AdSessions::instance()->start( 7 );
assertTrue( is_wp_error( $qa_again ), 'a user at the daily ad cap cannot start another ad session' );

/* ==========================================================================
 * 2. The check-in state machine
 * ======================================================================== */

mcr_group( 'Check-in: a failed bonus credit locks the user out of the day' );

qa_reset_world( $wpdb );

qa_set_settings(
	array(
		'checkin.base_amount' => 10,
		'checkin.bonuses'     => array( 3 => 5 ),
		'checkin.max_bonus'   => 100,
		'security.log_ip'     => 'off',
	)
);

// Two prior days make the streak 3, which reaches the 3 day bonus tier.
qa_seed_checkin( $wpdb, 7, '2026-01-13', 1, 10 );
qa_seed_checkin( $wpdb, 7, '2026-01-14', 2, 10 );

// The bonus credit fails at the database level (one transient error).
$wpdb->fail_transaction_type = 'checkin_bonus';

$qa_claim = CheckIn::instance()->claim( 7 );

assertTrue( is_wp_error( $qa_claim ), 'the claim fails when the bonus credit fails' );
assertSame( 0, Ledger::instance()->get_balance( 7 ), 'the base reward was reversed, so nothing was paid' );
assertSame( null, qa_find_checkin( $wpdb, 7, '2026-01-15' ), 'the claim row was removed so the day can be retried' );

$qa_retry = CheckIn::instance()->claim( 7 );

assertFalse(
	is_wp_error( $qa_retry ),
	'the user can retry the check-in after a failed bonus credit',
	'the retry was refused with ' . ( is_wp_error( $qa_retry ) ? $qa_retry->get_error_code() : 'nothing' )
);

assertSame( 10, Ledger::instance()->get_balance( 7 ), 'the retry pays the base reward' );

mcr_group( 'Check-in: a failed base credit keeps a claim row that was never paid' );

qa_reset_world( $wpdb );

qa_set_settings(
	array(
		'checkin.base_amount' => 10,
		'checkin.bonuses'     => array( 3 => 5 ),
		'checkin.max_bonus'   => 100,
		'security.log_ip'     => 'off',
	)
);

// The base credit fails at the database level.
$wpdb->fail_transaction_type = 'checkin';

$qa_claim = CheckIn::instance()->claim( 7 );

assertTrue( is_wp_error( $qa_claim ), 'the claim fails when the base credit fails' );
assertSame( 0, Ledger::instance()->get_balance( 7 ), 'no coins were paid' );
assertSame(
	null,
	qa_find_checkin( $wpdb, 7, '2026-01-15' ),
	'a claim row is not kept for a day that was never paid'
);

$qa_status = CheckIn::instance()->status( 7 );

assertFalse( $qa_status['claimed_today'], 'the status does not report the day as claimed' );
// The status reports the reward the user can claim today (potential), which
// is 10 coins here; it is not a statement that coins were already paid.
assertSame( 10, $qa_status['total_amount'], 'the status reports the claimable reward for the day' );

$wpdb->fail_transaction_type = '';

$qa_retry = CheckIn::instance()->claim( 7 );

assertFalse(
	is_wp_error( $qa_retry ),
	'the user can retry the check-in after a failed base credit',
	'the retry was refused with ' . ( is_wp_error( $qa_retry ) ? $qa_retry->get_error_code() : 'nothing' )
);

/* ==========================================================================
 * 3. Ledger invariants
 * ======================================================================== */

mcr_group( 'Ledger: the balance ceiling is enforced on the write path' );

qa_reset_world( $wpdb );

$qa_ceiling = \MCR\Ledger\BalanceMath::MAX_BALANCE;

Ledger::instance()->credit( 7, $qa_ceiling, 'admin_adjust', 'Flood', array( 'idempotency_key' => 'ceil-1' ) );
assertSame( $qa_ceiling, Ledger::instance()->get_balance( 7 ), 'a balance of exactly the ceiling is allowed' );

$qa_over = Ledger::instance()->credit( 7, 1, 'admin_adjust', 'One more', array( 'idempotency_key' => 'ceil-2' ) );

assertTrue(
	is_wp_error( $qa_over ),
	'a credit that would push the balance past the ledger ceiling is refused',
	'the credit returned ' . ( is_wp_error( $qa_over ) ? $qa_over->get_error_code() : 'success' )
);

assertSame(
	$qa_ceiling,
	Ledger::instance()->get_balance( 7 ),
	'the balance never exceeds the ledger ceiling'
);

mcr_group( 'Ledger: reconcile_user repairs the balance but not the lifetime counters' );

qa_reset_world( $wpdb );

Ledger::instance()->credit( 7, 100, 'checkin', 'Reward', array( 'idempotency_key' => 'rec-1' ) );
Ledger::instance()->debit( 7, 40, 'spend', 'Spend', array( 'idempotency_key' => 'rec-2' ) );

assertSame( 60, Ledger::instance()->get_balance( 7 ), 'the balance is 60' );
assertSame( 100, Ledger::instance()->get_stats( 7 )['lifetime_earned'], 'lifetime earned is 100' );
assertSame( 40, Ledger::instance()->get_stats( 7 )['lifetime_spent'], 'lifetime spent is 40' );

// Legacy corruption: the lifetime counter drifted.
$wpdb->tables['balances'][7]['lifetime_earned'] = 0;
$wpdb->tables['balances'][7]['lifetime_spent']  = 999;

$qa_repair = Ledger::instance()->reconcile_user( 7 );

assertFalse( is_wp_error( $qa_repair ), 'the repair tool runs' );
assertSame( 60, Ledger::instance()->get_balance( 7 ), 'the balance is still correct after the repair' );

$qa_stats = Ledger::instance()->get_stats( 7 );

assertSame( 100, $qa_stats['lifetime_earned'], 'the repair tool restores the lifetime earned counter' );
assertSame( 40, $qa_stats['lifetime_spent'], 'the repair tool restores the lifetime spent counter' );

qa_reset_world( $wpdb );

Ledger::instance()->credit( 7, 100, 'checkin', 'Reward', array( 'idempotency_key' => 'rec-3' ) );

// The balance row was lost entirely (a legacy bug or a manual delete).
unset( $wpdb->tables['balances'][7] );

$qa_repair = Ledger::instance()->reconcile_user( 7 );

assertFalse( is_wp_error( $qa_repair ), 'the repair tool runs on a missing balance row' );
assertSame( 100, Ledger::instance()->get_balance( 7 ), 'the balance is rebuilt from the ledger' );
assertSame(
	100,
	Ledger::instance()->get_stats( 7 )['lifetime_earned'],
	'the rebuilt balance row keeps the lifetime earned counter'
);

mcr_group( 'Ledger: type confusion in the user id' );

qa_reset_world( $wpdb );

$qa_array_user = Ledger::instance()->credit( array( 5 ), 100, 'admin_adjust', 'Array user', array( 'idempotency_key' => 'arr-1' ) );

assertTrue(
	is_wp_error( $qa_array_user ),
	'an array user id is refused instead of silently resolving to user 1',
	'the credit returned ' . ( is_wp_error( $qa_array_user ) ? $qa_array_user->get_error_code() : 'success' )
);

assertSame( 0, Ledger::instance()->get_balance( 1 ), 'no other user was credited by mistake' );

mcr_group( 'Ledger: idempotency key abuse' );

qa_reset_world( $wpdb );

$qa_first = Ledger::instance()->credit( 7, 10, 'admin_adjust', 'Zero key', array( 'idempotency_key' => '0' ) );
$qa_second = Ledger::instance()->credit( 7, 10, 'admin_adjust', 'Zero key', array( 'idempotency_key' => '0' ) );

assertFalse( is_wp_error( $qa_first ), 'the first credit with the key "0" succeeds' );
assertTrue(
	! empty( $qa_second['duplicate'] ),
	'the key "0" is a real idempotency key, so the second credit is a duplicate',
	'the second credit was not reported as a duplicate'
);
assertSame( 10, Ledger::instance()->get_balance( 7 ), 'the key "0" credits only once' );

qa_reset_world( $wpdb );

/*
 * Ledger::idempotency_key() truncates the key to 191 characters (the
 * column width). Two DIFFERENT 200 character keys that share their first
 * 191 characters therefore collapse onto one key, and the second grant is
 * silently refused as a duplicate.
 */
$qa_key_a = str_repeat( 'k', 199 ) . 'A';
$qa_key_b = str_repeat( 'k', 199 ) . 'B';

Ledger::instance()->credit( 7, 10, 'admin_adjust', 'Long key A', array( 'idempotency_key' => $qa_key_a ) );

$qa_second = Ledger::instance()->credit( 7, 10, 'admin_adjust', 'Long key B', array( 'idempotency_key' => $qa_key_b ) );

assertFalse(
	is_wp_error( $qa_second ),
	'two different 200 character idempotency keys are two different grants',
	'the second credit returned ' . qa_error_code( $qa_second )
);
assertSame( 20, Ledger::instance()->get_balance( 7 ), 'both long keys were honoured' );

qa_reset_world( $wpdb );

Ledger::instance()->credit( 7, 10, 'admin_adjust', 'Wildcard key', array( 'idempotency_key' => 'a%b_c' ) );
$qa_second = Ledger::instance()->credit( 7, 10, 'admin_adjust', 'Wildcard key', array( 'idempotency_key' => 'a%b_c' ) );

assertTrue( ! empty( $qa_second['duplicate'] ), 'a repeated wildcard key is a duplicate, not a new grant' );
assertSame( 10, Ledger::instance()->get_balance( 7 ), 'the wildcard key credited only once' );

qa_reset_world( $wpdb );

Ledger::instance()->credit( 7, 10, 'admin_adjust', 'Null byte A', array( 'idempotency_key' => "abc\0X" ) );
$qa_second = Ledger::instance()->credit( 7, 10, 'admin_adjust', 'Null byte B', array( 'idempotency_key' => "abc\0Y" ) );

assertFalse(
	! empty( $qa_second['duplicate'] ),
	'a null byte never splits an idempotency key into two different keys',
	'the second credit was reported as a duplicate'
);

qa_reset_world( $wpdb );

$qa_keyed = Ledger::instance()->credit( 7, 10, 'admin_adjust', 'Empty key', array( 'idempotency_key' => '' ) );
assertFalse( is_wp_error( $qa_keyed ), 'an empty idempotency key still credits once' );
assertSame( 10, Ledger::instance()->get_balance( 7 ), 'the empty key credited the amount once' );

mcr_group( 'Configuration abuse: a coin amount above the ledger ceiling' );

qa_reset_world( $wpdb );

/*
 * Settings::sanitizeValue() casts the numeric settings to int with no
 * ceiling, so an administrator can store a check-in reward that the ledger
 * will always refuse. The daily reward then fails for EVERY user, forever.
 */
qa_set_settings(
	array(
		'checkin.base_amount' => 2000000000000,
		'checkin.bonuses'     => array( 3 => 5 ),
		'checkin.max_bonus'   => 100,
		'security.log_ip'     => 'off',
	)
);

$qa_claim = CheckIn::instance()->claim( 7 );

// A reward above the ledger ceiling cannot be paid: the ledger refuses it,
// so the claim fails rather than silently paying an impossible amount.
assertTrue(
	is_wp_error( $qa_claim ),
	'a configured check-in reward above the ceiling is refused',
	'the claim returned ' . ( is_wp_error( $qa_claim ) ? qa_error_code( $qa_claim ) : 'success' )
);

assertSame( 0, Ledger::instance()->get_balance( 7 ), 'no coins were paid for an over-ceiling reward' );

qa_reset_world( $wpdb );

qa_set_settings(
	array(
		'ads.provider'           => 'qa_test',
		'ads.coins_per_ad'       => 2000000000000,
		'ads.daily_limit'        => 5,
		'ads.global_daily_limit' => 0,
		'ads.cooldown_seconds'   => 0,
		'ads.session_ttl'        => 900,
		'security.log_ip'        => 'off',
	)
);

$qa_ad = AdSessions::instance()->start( 7 );
assertFalse( is_wp_error( $qa_ad ), 'an ad session starts' );

$qa_ad_result = AdSessions::instance()->verify(
	7,
	array(
		'session_token'    => $qa_ad['session_token'],
		'completion_token' => 'proof-config',
	)
);

// A reward above the ledger ceiling cannot be paid: the ledger refuses it,
// so the verification fails rather than silently paying an impossible amount.
assertTrue(
	is_wp_error( $qa_ad_result ),
	'a configured ad reward above the ceiling is refused',
	'the verification returned ' . ( is_wp_error( $qa_ad_result ) ? qa_error_code( $qa_ad_result ) : 'success' )
);

assertSame( 0, Ledger::instance()->get_balance( 7 ), 'no coins were paid for an over-ceiling reward' );

mcr_group( 'Ledger: a corrupted balance row does not fatal' );

qa_reset_world( $wpdb );

$wpdb->tables['balances'][7] = array(
	'id'              => 1,
	'user_id'         => 7,
	'balance'         => null,
	'lifetime_earned' => null,
	'lifetime_spent'  => null,
	'purchased_total' => null,
	'updated_at'      => '1970-01-01 00:00:00',
);

$qa_diagnostics = array();

set_error_handler(
	static function ( $errno, $errstr ) use ( &$qa_diagnostics ) {
		$qa_diagnostics[] = $errno . ': ' . $errstr;

		return true;
	}
);

$qa_stats = Ledger::instance()->get_stats( 7 );
$qa_bal   = Ledger::instance()->get_balance( 7 );

restore_error_handler();

assertSame( array(), $qa_diagnostics, 'reading a row with NULL columns raises no PHP warning' );
assertSame( 0, $qa_stats['balance'], 'a NULL balance reads as zero' );
assertSame( 0, $qa_stats['lifetime_earned'], 'a NULL lifetime earned reads as zero' );
assertSame( 0, $qa_bal, 'a NULL balance reads as zero' );

qa_reset_world( $wpdb );

$wpdb->tables['balances'][7] = array(
	'id'              => 1,
	'user_id'         => 7,
	'balance'         => -50,
	'lifetime_earned' => 0,
	'lifetime_spent'  => 0,
	'purchased_total' => 0,
	'updated_at'      => '1970-01-01 00:00:00',
);

assertTrue(
	Ledger::instance()->get_balance( 7 ) >= 0,
	'a corrupted negative balance is never reported to the user as a negative balance',
	'the reported balance was ' . Ledger::instance()->get_balance( 7 )
);

/* ==========================================================================
 * 4. The refund path
 * ======================================================================== */

mcr_group( 'Refund: a refunded purchase must not be booked as a spend' );

qa_reset_world( $wpdb );

qa_set_settings(
	array(
		'purchase.gateway'   => 'manual',
		'purchase.currency'  => 'USD',
		'purchase.auto_credit' => 'on',
		'security.log_ip'    => 'off',
	)
);

$GLOBALS['mcr_caps']['mcr_manage_coins'] = true;

$qa_package = Packages::create(
	array(
		'id'            => 'qa_starter',
		'title'         => 'Starter',
		'coins'         => 100,
		'bonus_percent' => 0,
		'price'         => 4.99,
		'currency'      => 'USD',
		'active'        => 'on',
	)
);

assertFalse( is_wp_error( $qa_package ), 'a package can be created' );
assertSame( 100, $qa_package['coins_total'], 'the package grants 100 coins' );
assertSame( 499, $qa_package['price_minor'], 'the package price is 499 minor units' );

$qa_order = \MCR\Payments\Orders::instance()->create( 7, $qa_package );

assertFalse( is_wp_error( $qa_order ), 'an order can be created' );

$qa_payment = $wpdb->tables['payments'][0];

$_REQUEST['_mcr_nonce'] = \MCR\Gateways\Manual::create_action_nonce( (int) $qa_payment['id'] );

$qa_manual = new \MCR\Gateways\Manual( \MCR\Gateways\Gateway::load_settings( 'manual' ), $qa_clock );

$qa_paid = $qa_manual->admin_mark_paid( (int) $qa_payment['id'], 'Bank transfer received' );


assertFalse( is_wp_error( $qa_paid ), 'the administrator can approve the payment' );
assertSame( 100, Ledger::instance()->get_balance( 7 ), 'the purchase credited 100 coins' );

$qa_stats = Ledger::instance()->get_stats( 7 );
assertSame( 100, $qa_stats['lifetime_earned'], 'the purchase counts as earned' );
assertSame( 0, $qa_stats['lifetime_spent'], 'nothing was spent yet' );

$qa_refund = $qa_manual->admin_refund( (int) $qa_payment['id'], 'Customer refund' );

assertFalse( is_wp_error( $qa_refund ), 'the administrator can refund the payment' );
assertSame( 0, Ledger::instance()->get_balance( 7 ), 'the refund took the coins back' );

$qa_stats = Ledger::instance()->get_stats( 7 );

assertSame(
	0,
	$qa_stats['lifetime_earned'],
	'a refund gives the earned total back, exactly like the check-in reversal does',
	'lifetime_earned was ' . $qa_stats['lifetime_earned']
);
assertSame(
	0,
	$qa_stats['lifetime_spent'],
	'a refund is not a spend: the user never spent these coins',
	'lifetime_spent was ' . $qa_stats['lifetime_spent']
);

/* ==========================================================================
 * 5. The admin dashboard day bucket
 * ======================================================================== */

mcr_group( 'Admin dashboard: the day bucket follows the site timezone' );

qa_reset_world( $wpdb );

$qa_tz_clock = new FixedClock( '2026-01-15 10:00:00', 'Pacific/Kiritimati' );

\MCR\Container::instance()->set_clock( $qa_tz_clock );
Ledger::instance()->set_clock( $qa_tz_clock );

qa_seed_checkin( $wpdb, 7, '2026-01-15', 1, 10 );

$qa_dashboard = new \MCR\Admin\DashboardPage();

$qa_method = new ReflectionMethod( $qa_dashboard, 'compute_stats' );
$qa_method->setAccessible( true );
$qa_stats  = $qa_method->invoke( $qa_dashboard );

assertSame(
	1,
	(int) $qa_stats['checkins_today'],
	'the dashboard counts the check-ins of the current site-timezone day',
	'checkins_today was ' . var_export( $qa_stats['checkins_today'], true )
);

$qa_found = false;

foreach ( $wpdb->queries as $qa_sql ) {
	if ( false !== strpos( $qa_sql, 'checkin_date' ) && false !== strpos( $qa_sql, "'2026-01-15'" ) ) {
		$qa_found = true;
		break;
	}
}

assertTrue( $qa_found, 'the dashboard queries the day bucket of the injected clock' );

// Restore the UTC clock for the remaining groups.
$qa_clock = new FixedClock( '2026-01-15 10:00:00', 'UTC' );

\MCR\Container::instance()->set_clock( $qa_clock );
Ledger::instance()->set_clock( $qa_clock );

/* ==========================================================================
 * 6. Concurrency: no cross user contamination
 * ======================================================================== */

mcr_group( 'Concurrency: the ad cap is per user' );

qa_reset_world( $wpdb );

qa_set_settings(
	array(
		'ads.provider'           => 'qa_test',
		'ads.coins_per_ad'       => 5,
		'ads.daily_limit'        => 1,
		'ads.global_daily_limit' => 0,
		'ads.cooldown_seconds'   => 0,
		'ads.session_ttl'        => 900,
		'security.log_ip'        => 'off',
	)
);

$qa_a = AdSessions::instance()->start( 7 );
$qa_b = AdSessions::instance()->start( 8 );

assertFalse( is_wp_error( $qa_a ), 'user 7 starts an ad session' );
assertFalse( is_wp_error( $qa_b ), 'user 8 starts an ad session' );

AdSessions::instance()->verify(
	7,
	array(
		'session_token'    => $qa_a['session_token'],
		'completion_token' => 'proof-a',
	)
);

assertSame( 5, Ledger::instance()->get_balance( 7 ), 'user 7 earned the reward' );
assertSame( 0, Ledger::instance()->get_balance( 8 ), 'user 8 earned nothing yet' );

$qa_b_verified = AdSessions::instance()->verify(
	8,
	array(
		'session_token'    => $qa_b['session_token'],
		'completion_token' => 'proof-b',
	)
);

assertFalse( is_wp_error( $qa_b_verified ), 'user 8 is not blocked by user 7 reaching their own cap' );
assertSame( 5, Ledger::instance()->get_balance( 8 ), 'user 8 earned the reward' );

$qa_cross = AdSessions::instance()->verify(
	8,
	array(
		'session_token'    => $qa_a['session_token'],
		'completion_token' => 'proof-cross',
	)
);

assertTrue( is_wp_error( $qa_cross ), 'user 8 cannot complete user 7 ad session' );
assertSame( 5, Ledger::instance()->get_balance( 8 ), 'the cross user attempt moved no coins' );

mcr_group( 'Concurrency: two check-ins on the same day produce one reward' );

qa_reset_world( $wpdb );

qa_set_settings(
	array(
		'checkin.base_amount' => 10,
		'checkin.bonuses'     => array( 3 => 5 ),
		'checkin.max_bonus'   => 100,
		'security.log_ip'     => 'off',
	)
);

$qa_one = CheckIn::instance()->claim( 7 );
$qa_two = CheckIn::instance()->claim( 7 );

assertFalse( is_wp_error( $qa_one ), 'the first claim succeeds' );
assertTrue( is_wp_error( $qa_two ), 'the second claim of the same day is refused' );
assertSame( 10, Ledger::instance()->get_balance( 7 ), 'only one reward was paid' );
assertCount( 1, $wpdb->tables['checkins'], 'only one claim row exists' );

$qa_other = CheckIn::instance()->claim( 8 );

assertFalse( is_wp_error( $qa_other ), 'a different user can claim on the same day' );
assertSame( 10, Ledger::instance()->get_balance( 8 ), 'the second user got their own reward' );

/* ==========================================================================
 * 7. Full lifecycle round trip
 * ======================================================================== */

mcr_group( 'Lifecycle: check in, watch an ad, buy coins, adjust, refund' );

qa_reset_world( $wpdb );

qa_set_settings(
	array(
		'checkin.base_amount'    => 10,
		'checkin.bonuses'        => array( 3 => 5 ),
		'checkin.max_bonus'      => 100,
		'ads.provider'           => 'qa_test',
		'ads.coins_per_ad'       => 5,
		'ads.daily_limit'        => 5,
		'ads.global_daily_limit' => 0,
		'ads.cooldown_seconds'   => 0,
		'ads.session_ttl'        => 900,
		'purchase.gateway'       => 'manual',
		'purchase.currency'      => 'USD',
		'security.log_ip'        => 'off',
	)
);

$qa_claim = CheckIn::instance()->claim( 7 );
assertFalse( is_wp_error( $qa_claim ), 'the daily check-in succeeds' );
assertSame( 10, Ledger::instance()->get_balance( 7 ), 'the check-in paid the base reward' );

$qa_ad = AdSessions::instance()->start( 7 );
AdSessions::instance()->verify(
	7,
	array(
		'session_token'    => $qa_ad['session_token'],
		'completion_token' => 'proof-life',
	)
);
assertSame( 15, Ledger::instance()->get_balance( 7 ), 'the ad reward was paid' );

$qa_package = Packages::create(
	array(
		'id'            => 'life_starter',
		'title'         => 'Starter',
		'coins'         => 100,
		'bonus_percent' => 0,
		'price'         => 4.99,
		'currency'      => 'USD',
		'active'        => 'on',
	)
);

\MCR\Payments\Orders::instance()->create( 7, $qa_package );

$qa_payment = $wpdb->tables['payments'][0];

$_REQUEST['_mcr_nonce'] = \MCR\Gateways\Manual::create_action_nonce( (int) $qa_payment['id'] );

$qa_manual = new \MCR\Gateways\Manual( \MCR\Gateways\Gateway::load_settings( 'manual' ), $qa_clock );

$qa_manual->admin_mark_paid( (int) $qa_payment['id'], 'Bank transfer received' );
assertSame( 115, Ledger::instance()->get_balance( 7 ), 'the purchase was credited' );

$qa_rows = Ledger::instance()->list_for_user( 7, 50, 0 );
assertCount( 3, $qa_rows, 'the ledger lists the check-in, the ad reward and the purchase' );

Ledger::instance()->credit( 7, 50, 'admin_adjust', 'Goodwill', array( 'idempotency_key' => 'life-adj' ) );
assertSame( 165, Ledger::instance()->get_balance( 7 ), 'the admin adjustment was applied' );

$qa_manual->admin_refund( (int) $qa_payment['id'], 'Customer refund' );
assertSame( 65, Ledger::instance()->get_balance( 7 ), 'the refund took the purchase back' );

$qa_final = Ledger::instance()->get_stats( 7 );

assertTrue( $qa_final['balance'] >= 0, 'the balance never goes negative' );
assertTrue( $qa_final['lifetime_earned'] >= 0, 'the lifetime earned counter never goes negative' );
assertTrue( $qa_final['lifetime_spent'] >= 0, 'the lifetime spent counter never goes negative' );

/* ==========================================================================
 * Summary
 * ======================================================================== */

echo "\n=========================================\n";

$qa_total = $GLOBALS['mcr_tests']['passed'] + $GLOBALS['mcr_tests']['failed'];

printf(
	"Assertions: %d | Passed: %d | Failed: %d\n",
	$qa_total,
	$GLOBALS['mcr_tests']['passed'],
	$GLOBALS['mcr_tests']['failed']
);

if ( $GLOBALS['mcr_tests']['failed'] > 0 ) {
	echo "\nFailures:\n";

	foreach ( $GLOBALS['mcr_tests']['failures'] as $qa_failure ) {
		printf( "  - [%s] %s\n", $qa_failure['group'], $qa_failure['message'] );

		if ( '' !== $qa_failure['detail'] ) {
			printf( "      %s\n", $qa_failure['detail'] );
		}
	}

	exit( 1 );
}

echo "OK\n";
exit( 0 );
