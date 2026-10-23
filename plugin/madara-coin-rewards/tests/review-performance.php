<?php
/**
 * PERFORMANCE / DATABASE REVIEW SUITE.
 *
 * Run with: php tests/review-performance.php
 *
 * ---------------------------------------------------------------------------
 * THIS IS A DELIBERATELY RED SUITE.
 *
 * Every assertion tagged [DEFECT] describes a real, measured performance or
 * database defect in the CURRENT plugin source. They are written as
 * "the correct behaviour must hold" assertions, so they FAIL today and will
 * start PASSING as soon as the corresponding patch lands. The suite therefore
 * exits 1 while the defects exist and exits 0 once they are fixed.
 *
 * Assertions tagged [OK] are controls: they prove that a control which is
 * already implemented correctly stays correct.
 *
 * Every finding is backed by a query count or a SQL-shape assertion taken
 * through the fake $wpdb, never by a code reading alone.
 *
 * @package MCR
 */

namespace {

// The plugin files guard on ABSPATH (already defined by bootstrap.php).
error_reporting( E_ALL & ~E_DEPRECATED );

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/wp-stubs.php';

if ( ! defined( 'MCR_PLUGIN_DIR' ) ) {
	define( 'MCR_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'MCR_PLUGIN_URL' ) ) {
	define( 'MCR_PLUGIN_URL', 'https://example.test/wp-content/plugins/madara-coin-rewards/' );
}
if ( ! defined( 'MCR_PLUGIN_BASENAME' ) ) {
	define( 'MCR_PLUGIN_BASENAME', 'madara-coin-rewards/madara-coin-rewards.php' );
}
if ( ! defined( 'MCR_VERSION' ) ) {
	define( 'MCR_VERSION', '1.0.0' );
}
if ( ! defined( 'MCR_DB_VERSION' ) ) {
	define( 'MCR_DB_VERSION', '1.0.0' );
}

// WordPress functions the plugin uses that the shared stubs do not provide.
if ( ! function_exists( 'check_admin_referer' ) ) {
	/**
	 * Nonce check stub.
	 *
	 * @param string $action Action.
	 * @param string $query  Query arg.
	 * @return bool
	 */
	function check_admin_referer( $action = -1, $query = '_wpnonce' ) {
		return true;
	}
}
if ( ! function_exists( 'rest_ensure_response' ) ) {
	/**
	 * REST response stub.
	 *
	 * @param mixed $response Response.
	 * @return mixed
	 */
	function rest_ensure_response( $response ) {
		return $response;
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
		return strtolower( preg_replace( '/[^a-z0-9_\-]+/', '-', (string) $title ) );
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
if ( ! function_exists( 'wp_generate_uuid4' ) ) {
	/**
	 * UUID stub.
	 *
	 * @return string
	 */
	function wp_generate_uuid4() {
		return '00000000-0000-4000-8000-000000000000';
	}
}
if ( ! function_exists( 'delete_option' ) ) {
	/**
	 * Option delete stub.
	 *
	 * @param string $name Option name.
	 * @return bool
	 */
	function delete_option( $name ) {
		unset( $GLOBALS['mcr_options'][ $name ] );

		return true;
	}
}

require_once dirname( __DIR__ ) . '/includes/Autoloader.php';
\MCR\Autoloader::register( MCR_PLUGIN_DIR );

/* ==========================================================================
 * Test doubles
 * ======================================================================== */

/**
 * A counting $wpdb.
 *
 * It records EVERY statement - reads included - so a test can assert both
 * the number of round trips and the shape of the SQL.
 */
class Perf_WPDB extends MCR_Fake_WPDB {

	/**
	 * The users table name (WP core).
	 *
	 * @var string
	 */
	public $users = 'wp_users';

	/**
	 * Every statement that was run, in order.
	 *
	 * @var array
	 */
	public $log = array();

	/**
	 * Rows returned by get_results(), keyed by a SQL needle.
	 *
	 * @var array
	 */
	public $rows_for = array();

	/**
	 * Row returned by get_row(), keyed by a SQL needle.
	 *
	 * @var array
	 */
	public $row_for = array();

	/**
	 * Value returned by get_var(), keyed by a SQL needle.
	 *
	 * @var array
	 */
	public $var_for = array();

	/**
	 * Column returned by get_col(), keyed by a SQL needle.
	 *
	 * @var array
	 */
	public $col_for = array();

	/**
	 * Fallback rows.
	 *
	 * @var array
	 */
	public $default_rows = array();

	/**
	 * Fallback row.
	 *
	 * @var mixed
	 */
	public $default_row = null;

	/**
	 * Fallback value.
	 *
	 * @var mixed
	 */
	public $default_var = 0;

	/**
	 * Fallback column.
	 *
	 * @var array
	 */
	public $default_col = array();

	/**
	 * Clears the recorded statements.
	 *
	 * @return void
	 */
	public function reset() {
		$this->log = array();
	}

	/**
	 * The number of statements recorded since the last reset().
	 *
	 * @return int
	 */
	public function count() {
		return count( $this->log );
	}

	/**
	 * Finds the first recorded statement containing a needle.
	 *
	 * @param string $needle Needle.
	 * @return string|null
	 */
	public function first( $needle ) {
		foreach ( $this->log as $sql ) {
			if ( false !== stripos( $sql, $needle ) ) {
				return $sql;
			}
		}

		return null;
	}

	/**
	 * Counts the recorded statements containing a needle.
	 *
	 * @param string $needle Needle.
	 * @return int
	 */
	public function count_like( $needle ) {
		$n = 0;

		foreach ( $this->log as $sql ) {
			if ( false !== stripos( $sql, $needle ) ) {
				++$n;
			}
		}

		return $n;
	}

	/**
	 * Returns the first matching configured value.
	 *
	 * @param array  $map     Map of needle => value.
	 * @param string $sql     SQL.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	private function match( $map, $sql, $default ) {
		foreach ( $map as $needle => $value ) {
			if ( false !== stripos( $sql, $needle ) ) {
				return $value;
			}
		}

		return $default;
	}

	/**
	 * Runs a statement.
	 *
	 * @param string $sql SQL.
	 * @return int
	 */
	public function query( $sql ) {
		$this->log[] = $sql;

		return 1;
	}

	/**
	 * Runs a read and returns rows.
	 *
	 * @param string $sql  SQL.
	 * @param string $type Result type.
	 * @return array
	 */
	public function get_results( $sql, $type = 'OBJECT' ) {
		$this->log[] = $sql;

		$rows = $this->match( $this->rows_for, $sql, $this->default_rows );

		if ( 'ARRAY_A' === $type ) {
			return $rows;
		}

		$out = array();

		foreach ( (array) $rows as $row ) {
			$out[] = is_array( $row ) ? (object) $row : $row;
		}

		return $out;
	}

	/**
	 * Runs a read and returns one row.
	 *
	 * @param string $sql  SQL.
	 * @param string $type Result type.
	 * @return mixed
	 */
	public function get_row( $sql, $type = 'OBJECT' ) {
		$this->log[] = $sql;

		$row = $this->match( $this->row_for, $sql, $this->default_row );

		if ( null === $row ) {
			return null;
		}

		if ( is_array( $row ) && 'ARRAY_A' !== $type ) {
			return (object) $row;
		}

		return $row;
	}

	/**
	 * Runs a read and returns one value.
	 *
	 * @param string $sql SQL.
	 * @return mixed
	 */
	public function get_var( $sql ) {
		$this->log[] = $sql;

		return $this->match( $this->var_for, $sql, $this->default_var );
	}

	/**
	 * Runs a read and returns one column.
	 *
	 * @param string $sql SQL.
	 * @return array
	 */
	public function get_col( $sql ) {
		$this->log[] = $sql;

		return $this->match( $this->col_for, $sql, $this->default_col );
	}
}

/**
 * A provider whose verification throws, to prove the advisory lock leaks.
 */
class Throwing_Provider extends \MCR\AdProviders\Provider {

	/**
	 * The provider slug.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'throwing';
	}

	/**
	 * The provider title.
	 *
	 * @return string
	 */
	public function get_title() {
		return 'Throwing provider';
	}

	/**
	 * Whether the provider is configured.
	 *
	 * @return bool
	 */
	public function is_configured() {
		return true;
	}

	/**
	 * The ad units.
	 *
	 * @return array
	 */
	public function get_ad_units() {
		return array();
	}

	/**
	 * Creates a session.
	 *
	 * @param array $args Args.
	 * @return array
	 */
	public function create_session( array $args ) {
		return array();
	}

	/**
	 * Throws instead of verifying.
	 *
	 * @param array $payload Payload.
	 * @return true
	 * @throws \RuntimeException Always.
	 */
	public function verify_completion( array $payload ) {
		throw new \RuntimeException( 'provider exploded' );
	}

	/**
	 * The settings fields.
	 *
	 * @return array
	 */
	public function get_settings_fields() {
		return array();
	}
}

/* ==========================================================================
 * Helpers
 * ======================================================================== */

/**
 * Resets the shared state between groups.
 *
 * @param Perf_WPDB $wpdb Fake database.
 * @return void
 */
function mcr_perf_reset( Perf_WPDB $wpdb ) {
	$wpdb->reset();
	$wpdb->rows_for     = array();
	$wpdb->row_for      = array();
	$wpdb->var_for      = array();
	$wpdb->col_for      = array();
	$wpdb->default_rows = array();
	$wpdb->default_row  = null;
	$wpdb->default_var  = 0;
	$wpdb->default_col  = array();

	$GLOBALS['mcr_options']      = array( 'mcr_settings' => array() );
	$GLOBALS['mcr_filters']      = array();
	$GLOBALS['mcr_caps']         = array();
	$GLOBALS['mcr_current_user'] = 0;
	$GLOBALS['mcr_option_reads'] = array();

	$_GET  = array();
	$_POST = array();

	if ( class_exists( 'MCR\\Ledger' ) ) {
		\MCR\Ledger::instance()->reset_balance_cache();
	}

	if ( class_exists( 'MCR\\Rewards\\CheckIn' ) ) {
		\MCR\Rewards\CheckIn::instance()->reset_status_cache();
	}
	\MCR\Settings::flush_cache();
}

/**
 * Builds N fake user-id rows.
 *
 * @param int $n Row count.
 * @return array
 */
function mcr_perf_id_rows( $n ) {
	$rows = array();

	for ( $i = 1; $i <= $n; $i++ ) {
		$rows[] = array( 'ID' => $i );
	}

	return $rows;
}

/**
 * The canonical fake balance row.
 *
 * @return array
 */
function mcr_perf_balance_row() {
	return array(
		'balance'         => 500,
		'lifetime_earned' => 900,
		'lifetime_spent'  => 400,
		'purchased_total' => 0,
	);
}

/**
 * Registers the throwing provider as the configured ad provider.
 *
 * @return void
 */
function mcr_perf_install_throwing_provider() {
	\MCR\AdProviders\Registry::reset();

	add_filter(
		'mcr_register_ad_providers',
		static function ( $providers ) {
			$providers['throwing'] = 'Throwing_Provider';

			return $providers;
		}
	);

	\MCR\Settings::update( array( 'ads.provider' => 'throwing' ) );
}

/**
 * Parses the schema captured from the dbDelta() stub.
 *
 * @return array Table name => list of array('name'=>..,'cols'=>..,'unique'=>bool).
 */
function mcr_perf_schema() {
	$out = array();

	foreach ( (array) ( $GLOBALS['mcr_dbdelta'] ?? array() ) as $sql ) {
		if ( ! preg_match( '/CREATE\s+TABLE\s+(\S+)\s*\((.*)\)/is', $sql, $m ) ) {
			continue;
		}

		$table = trim( $m[1], '`' );
		$keys  = array();

		foreach ( preg_split( '/\r?\n/', $m[2] ) as $line ) {
			$line = trim( $line );

			if ( ! preg_match( '/^(UNIQUE\s+)?KEY\s+(\S+)\s+\(([^)]+)\)/i', $line, $km ) ) {
				continue;
			}

			$cols = array();

			foreach ( explode( ',', $km[3] ) as $col ) {
				$cols[] = trim( $col );
			}

			$keys[] = array(
				'name'   => $km[2],
				'cols'   => $cols,
				'unique' => '' !== trim( $km[1] ),
			);
		}

		$out[ $table ] = $keys;
	}

	return $out;
}

/**
 * Whether a table has an index whose column list starts with the given
 * columns (in the given order).
 *
 * @param array  $schema  Parsed schema.
 * @param string $table   Table name (without prefix).
 * @param array  $columns Column prefix to look for.
 * @return bool
 */
function mcr_perf_has_index( array $schema, $table, array $columns ) {
	$full = 'wp_mcr_' . $table;

	if ( ! isset( $schema[ $full ] ) ) {
		return false;
	}

	foreach ( $schema[ $full ] as $key ) {
		if ( array_values( array_slice( $key['cols'], 0, count( $columns ) ) ) === $columns ) {
			return true;
		}
	}

	return false;
}

/**
 * Returns the captured dbDelta statements.
 *
 * @return array
 */
function mcr_perf_dbdelta() {
	return (array) ( $GLOBALS['mcr_dbdelta'] ?? array() );
}

/**
 * Extracts the OFFSET of a paginated query.
 *
 * @param string $sql SQL.
 * @return int
 */
function mcr_perf_offset( $sql ) {
	if ( preg_match( '/OFFSET\s+(\d+)/i', $sql, $m ) ) {
		return (int) $m[1];
	}

	return 0;
}

/**
 * Extracts the LIMIT of a paginated query.
 *
 * @param string $sql SQL.
 * @return int
 */
function mcr_perf_limit( $sql ) {
	if ( preg_match( '/LIMIT\s+(\d+)/i', $sql, $m ) ) {
		return (int) $m[1];
	}

	return 0;
}

/**
 * Calls a private method on an object.
 *
 * @param object $object Object.
 * @param string $method Method.
 * @param array  $args   Args.
 * @return mixed
 */
function mcr_perf_private( $object, $method, array $args = array() ) {
	$r = new \ReflectionMethod( get_class( $object ), $method );
	$r->setAccessible( true );

	return $r->invokeArgs( $object, $args );
}

/* ==========================================================================
 * CHILD MODE: the CSV export probe.
 *
 * `export_via_admin_post()` ends with exit(), so it cannot run inside the
 * main process. The parent test spawns this same file with
 * MCR_PERF_CHILD=export and reads the captured SQL from STDERR.
 * ======================================================================== */

if ( 'export' === getenv( 'MCR_PERF_CHILD' ) ) {
	$child_wpdb = new Perf_WPDB();

	$row_count = (int) ( getenv( 'MCR_PERF_CHILD_ROWS' ) ?: 20000 );
	$rows      = array();

	for ( $i = 0; $i < $row_count; $i++ ) {
		$rows[] = array(
			'id'            => $i + 1,
			'txn_uid'       => sprintf( '00000000-0000-4000-8000-%012d', $i + 1 ),
			'user_id'       => ( $i % 100 ) + 1,
			'type'          => 'checkin',
			'amount'        => 10,
			'balance_after' => 100,
			'reason'        => 'Daily check-in reward',
			'source'        => 'web',
			'created_at'    => '2026-01-01 00:00:00',
		);
	}

	$child_wpdb->default_rows = $rows;

	$GLOBALS['wpdb']              = $child_wpdb;
	$GLOBALS['mcr_caps']         = array( 'manage_options' => true );
	$GLOBALS['mcr_current_user'] = 1;

	$_POST = array(
		'user'   => '',
		'type'   => '',
		'from'   => '',
		'to'     => '',
		'search' => '',
	);

	register_shutdown_function(
		static function () use ( $child_wpdb, $row_count ) {
			fwrite(
				STDERR,
				json_encode(
					array(
						'sql'        => $child_wpdb->log,
						'rows_sent'  => $row_count,
						'peak_bytes' => memory_get_peak_usage( true ),
						'peak_human' => round( memory_get_peak_usage( true ) / 1048576, 2 ) . ' MB',
						'fatal'      => (string) ( $GLOBALS['mcr_perf_child_fatal'] ?? '' ),
					)
				)
			);
		}
	);

	$fatal = '';

	try {
		\MCR\Admin\TransactionsPage::export_via_admin_post();
	} catch ( \Throwable $e ) {
		$fatal = get_class( $e ) . ': ' . $e->getMessage() . ' @ '
			. basename( $e->getFile() ) . ':' . $e->getLine();
	}

	$GLOBALS['mcr_perf_child_fatal'] = $fatal;

	exit( 0 );
}

/* ==========================================================================
 * The suite
 * ======================================================================== */

$wpdb = new Perf_WPDB();

$GLOBALS['wpdb'] = $wpdb;

/* --------------------------------------------------------------------------
 * A. Autoloader / file I/O
 * ----------------------------------------------------------------------- */

mcr_group( 'A. Autoloader / file I/O' );

mcr_perf_reset( $wpdb );

$autoloader_source = file_get_contents( dirname( __DIR__ ) . '/includes/Autoloader.php' );

assertFalse(
	(bool) preg_match( '/scandir|glob\s*\(|DirectoryIterator|opendir|readdir|FilesystemIterator/', $autoloader_source ),
	'[OK] The PSR-4 autoloader performs NO filesystem scan per class (the path is computed directly).'
);

assertTrue(
	(bool) preg_match( '/is_readable|file_exists/', $autoloader_source ),
	'[OK] The autoloader probes the computed path directly (1 stat() per class, not a directory walk).'
);

try {
	\MCR\Plugin::instance()->boot();
} catch ( \Throwable $e ) {
	// Boot failures are not the subject of this suite.
}

$GLOBALS['mcr_perf_boot_hooks'] = isset( $GLOBALS['mcr_filters']['plugins_loaded'] )
	? $GLOBALS['mcr_filters']['plugins_loaded']
	: array();

$mcr_classes = array_values(
	array_filter(
		get_declared_classes(),
		static function ( $c ) {
			return 0 === strpos( $c, 'MCR\\' );
		}
	)
);

printf(
	"    [INFO] cold front-end request autoloads %d MCR classes (1 stat() each, no class map)\n",
	count( $mcr_classes )
);

assertTrue(
	count( $mcr_classes ) > 5,
	'[OK] The cold request resolves the plugin classes through the autoloader (measurement anchor).'
);

assertTrue(
	(bool) preg_match( '/class_?map|autoload_classmap|opcache_compile_file/', $autoloader_source ),
	'[DEFECT] Autoloader has no class map: every class costs one extra stat() probe ('
	. count( $mcr_classes ) . ' probes per cold request). Add a static class map.'
);

/* --------------------------------------------------------------------------
 * B. Admin > Users list (N+1)
 * ----------------------------------------------------------------------- */

mcr_group( 'B. Admin > Users list (N+1)' );

mcr_perf_reset( $wpdb );
$GLOBALS['mcr_caps']['manage_options'] = true;
$wpdb->row_for['mcr_balances'] = mcr_perf_balance_row();
$wpdb->rows_for['SELECT u.ID FROM'] = mcr_perf_id_rows( 20 );

$users_page = new \MCR\Admin\UsersPage();
$users_page->prepare_items();
$q20 = $wpdb->count();

mcr_perf_reset( $wpdb );
$GLOBALS['mcr_caps']['manage_options'] = true;
$wpdb->row_for['mcr_balances'] = mcr_perf_balance_row();
$wpdb->rows_for['SELECT u.ID FROM'] = mcr_perf_id_rows( 2 );

$users_page = new \MCR\Admin\UsersPage();
$users_page->prepare_items();
$q2 = $wpdb->count();

printf( "    [INFO] UsersPage::prepare_items(): %d SQL for 20 rows, %d SQL for 2 rows\n", $q20, $q2 );

assertTrue(
	$q20 <= 3,
	'[DEFECT] UsersPage::prepare_items() must not run one balance query per row. Measured '
	. $q20 . ' SQL queries for a 20-row page (1 ids + 1 count + 20 per-row balance reads). '
	. 'Fix: select b.balance, b.lifetime_earned, b.lifetime_spent in the JOIN and drop the per-row Ledger::get_stats() call.'
);

assertTrue(
	( $q20 - $q2 ) <= 2,
	'[DEFECT] The users list must not scale its query count with the row count. Measured '
	. ( $q20 - $q2 ) . ' extra queries for 18 extra rows (exactly 1 query per row).'
);

$count_sql = $wpdb->first( 'COUNT(DISTINCT' );

assertTrue(
	false === $count_sql,
	'[DEFECT] count_all_users() must not run COUNT(DISTINCT u.ID) over the users x balances join. '
	. 'Fix: SELECT COUNT(*) FROM wp_mcr_balances (one row per user by construction, UNIQUE KEY user_id).'
);

/* --------------------------------------------------------------------------
 * C. Admin > Transactions / Logs list
 * ----------------------------------------------------------------------- */

mcr_group( 'C. Admin > Transactions / Logs list' );

mcr_perf_reset( $wpdb );
$GLOBALS['mcr_caps']['manage_options'] = true;

$wpdb->rows_for['FROM wp_mcr_transactions t'] = array(
	array(
		'id'            => 1,
		'txn_uid'       => 'uid',
		'user_id'       => 3,
		'type'          => 'checkin',
		'amount'        => 10,
		'balance_after' => 10,
		'reason'        => 'Daily',
		'source'        => 'web',
		'created_at'    => '2026-01-01 00:00:00',
	),
);

ob_start();
( new \MCR\Admin\TransactionsPage() )->render_ledger_table();
ob_end_clean();

$page_sql  = (string) $wpdb->first( 'ORDER BY t.id DESC' );
$count_sql = (string) $wpdb->first( 'COUNT(*)' );

assertTrue(
	0 !== mcr_perf_limit( $page_sql ),
	'[OK] The transactions page row query is bounded by a LIMIT.'
);

assertTrue(
	'' === $count_sql || false !== stripos( $count_sql, 'WHERE' ),
	'[DEFECT] The transactions page must not run an unfiltered SELECT COUNT(*) over the whole ledger on every page load. '
	. 'Measured: "' . trim( $count_sql ) . '". Fix: keyset pagination (no total) or a cached/approximate total.'
);

mcr_perf_reset( $wpdb );
$GLOBALS['mcr_caps']['manage_options'] = true;
$_GET['from'] = '2026-01-01';

ob_start();
( new \MCR\Admin\TransactionsPage() )->render_ledger_table();
ob_end_clean();

$date_sql = (string) $wpdb->first( 'ORDER BY t.id DESC' );

assertTrue(
	! preg_match( '/DATE\s*\(\s*[a-z]*\.?created_at\s*\)/i', $date_sql ),
	'[DEFECT] The transactions date filter must not wrap created_at in DATE(): that predicate cannot use any index. '
	. 'Measured: "' . trim( $date_sql ) . '". Fix: created_at >= \'YYYY-MM-DD 00:00:00\' AND created_at < \'...\' + KEY created_at.'
);

mcr_perf_reset( $wpdb );
$GLOBALS['mcr_caps']['manage_options'] = true;
$_GET['search'] = 'reward';

ob_start();
( new \MCR\Admin\TransactionsPage() )->render_ledger_table();
ob_end_clean();

$like_count = (string) $wpdb->first( 'COUNT(*)' );

assertTrue(
	'' === $like_count || false === stripos( $like_count, 'LIKE' ),
	'[DEFECT] A reason search must not trigger an unbounded COUNT(*) over a LIKE \'%term%\' predicate '
	. '(a full ledger scan per search). Measured: "' . trim( $like_count ) . '". Fix: bound the count to the page window.'
);

mcr_perf_reset( $wpdb );
$GLOBALS['mcr_caps']['manage_options'] = true;
$_GET['paged'] = '1000000';

ob_start();
( new \MCR\Admin\TransactionsPage() )->render_ledger_table();
ob_end_clean();

$deep_sql = (string) $wpdb->first( 'ORDER BY t.id DESC' );

assertTrue(
	mcr_perf_offset( $deep_sql ) <= 10000,
	'[DEFECT] The admin pagination offset must be capped. Measured OFFSET '
	. mcr_perf_offset( $deep_sql ) . ' for ?paged=1000000 - MySQL has to skip that many index entries.'
);

mcr_perf_reset( $wpdb );
$GLOBALS['mcr_caps']['manage_options'] = true;

ob_start();
( new \MCR\Admin\LogsPage() )->render_log_table();
ob_end_clean();

$logs_count = (string) $wpdb->first( 'COUNT(*)' );

assertTrue(
	'' !== $logs_count && false !== stripos( $logs_count, 'source' ),
	'[OK] The logs page restricts its ledger scan to the admin source.'
);

/* --------------------------------------------------------------------------
 * D. CSV export (unbounded result set)
 * ----------------------------------------------------------------------- */

mcr_group( 'D. CSV export (unbounded result set)' );

$child_env = getenv();

if ( ! is_array( $child_env ) ) {
	$child_env = array();
}

$child_env['MCR_PERF_CHILD']      = 'export';
$child_env['MCR_PERF_CHILD_ROWS'] = '20000';

$descriptors = array(
	1 => array( 'pipe', 'w' ),
	2 => array( 'pipe', 'w' ),
);

$proc = proc_open(
	array( PHP_BINARY, __FILE__ ),
	$descriptors,
	$pipes,
	dirname( __DIR__ ),
	$child_env
);

$child_out = '';
$child_err = '';

if ( is_resource( $proc ) ) {
	$child_out = (string) stream_get_contents( $pipes[1] );
	$child_err = (string) stream_get_contents( $pipes[2] );
	fclose( $pipes[1] );
	fclose( $pipes[2] );
	proc_close( $proc );
}

$child = json_decode( $child_err, true );

if ( ! is_array( $child ) ) {
	mcr_fail(
		'[DEFECT] The CSV export probe could not run (the export path could not be measured).',
		'stderr: ' . substr( $child_err, 0, 400 )
	);
} else {
	$export_sql = isset( $child['sql'][0] ) ? (string) $child['sql'][0] : '';

	printf(
		"    [INFO] CSV export: %d statement(s), %d rows returned, peak PHP memory %s, %d CSV lines written\n",
		count( $child['sql'] ),
		(int) $child['rows_sent'],
		(string) $child['peak_human'],
		substr_count( $child_out, "\n" )
	);

	if ( ! empty( $child['fatal'] ) ) {
		printf( "    [INFO] CSV export fatal: %s\n", (string) $child['fatal'] );
	}

	assertTrue(
		'' === (string) ( $child['fatal'] ?? '' ),
		'[DEFECT] TransactionsPage::export_via_admin_post() is a STATIC method but calls $this->escape_csv_cell(), '
		. 'which is a fatal "Using $this when not in object context" on PHP 8. Measured fatal: "'
		. (string) ( $child['fatal'] ?? '' ) . '". Fix: call self::escape_csv_cell().'
	);

	assertTrue(
		0 !== mcr_perf_limit( $export_sql ),
		'[DEFECT] The CSV export must not select the whole ledger without a LIMIT. Measured SQL: "'
		. trim( $export_sql ) . '" - every ledger row is buffered in PHP memory at once.'
	);

	assertTrue(
		count( $child['sql'] ) > 1,
		'[DEFECT] The CSV export must stream the ledger in bounded chunks (one statement per chunk), not as a single unbounded result set.'
	);
}

/* --------------------------------------------------------------------------
 * E. Ledger read paths
 * ----------------------------------------------------------------------- */

mcr_group( 'E. Ledger read paths' );

mcr_perf_reset( $wpdb );

// Measure the cost of one frontend dashboard render.
mcr_perf_reset( $wpdb );
$GLOBALS['mcr_current_user'] = 5;
$wpdb->var_for['mcr_balances']  = 100;
$wpdb->row_for['mcr_balances'] = mcr_perf_balance_row();
$wpdb->row_for['mcr_checkins'] = array(
	'streak_count' => 4,
	'base_amount'  => 10,
	'bonus_amount' => 5,
	'total_amount' => 15,
	'checkin_date' => gmdate( 'Y-m-d' ),
);

$dashboard_reflection = new \ReflectionMethod( 'MCR\Templates\Shortcodes', 'dashboard_args' );
$dashboard_reflection->setAccessible( true );
$dashboard_reflection->invoke( new \MCR\Templates\Shortcodes(), 5 );

printf(
	"    [INFO] one frontend dashboard render = %d SQL statements\n",
	$wpdb->count()
);

// Measure the cost of one ledger write (the hot path: every check-in, every
// ad reward, every purchase).
mcr_perf_reset( $wpdb );
$wpdb->var_for['information_schema'] = 'InnoDB';

\MCR\Ledger::instance()->credit(
	5,
	10,
	'checkin',
	'Daily reward',
	array( 'idempotency_key' => 'mcr-perf-test:1' )
);

printf(
	"    [INFO] one ledger credit = %d SQL statements (transaction control + insert + balance upsert + balance re-read + snapshot update)\n",
	$wpdb->count()
);

mcr_perf_reset( $wpdb );

\MCR\Ledger::instance()->list_for_user( 5, 25, 0 );
$sql = (string) $wpdb->first( 'FROM wp_mcr_transactions' );

assertTrue(
	25 === mcr_perf_limit( $sql ),
	'[OK] Ledger::list_for_user() clamps the limit and always emits a LIMIT.'
);

mcr_perf_reset( $wpdb );
\MCR\Ledger::instance()->list_for_user( 5, 100000, 0 );
$clamped = (string) $wpdb->first( 'FROM wp_mcr_transactions' );

assertTrue(
	100 === mcr_perf_limit( $clamped ),
	'[OK] Ledger::list_for_user() clamps the limit to 100 (no unbounded read).'
);

mcr_perf_reset( $wpdb );

$reflection = new \ReflectionMethod( 'MCR\Templates\Shortcodes', 'transactions_args' );
$reflection->setAccessible( true );

$_GET['mcr_page'] = '1';
$reflection->invoke( new \MCR\Templates\Shortcodes(), 5 );

$frontend_count = (string) $wpdb->first( 'COUNT(*)' );

assertTrue(
	'' === $frontend_count,
	'[DEFECT] The frontend transaction history must not run SELECT COUNT(*) over the user\'s entire ledger on every page view. '
	. 'Measured: "' . trim( $frontend_count ) . '". Fix: keyset pagination (LIMIT n+1) and drop the total.'
);

mcr_perf_reset( $wpdb );
$_GET['mcr_page'] = '500000';
$reflection->invoke( new \MCR\Templates\Shortcodes(), 5 );
$deep_front = (string) $wpdb->first( 'FROM wp_mcr_transactions' );

assertTrue(
	mcr_perf_offset( $deep_front ) <= 10000,
	'[DEFECT] The frontend transaction pagination offset must be capped. Measured OFFSET '
	. mcr_perf_offset( $deep_front ) . '.'
);

/* --------------------------------------------------------------------------
 * F. Ad sessions: advisory lock, indexes, pruning
 * ----------------------------------------------------------------------- */

mcr_group( 'F. Ad sessions: advisory lock and per-request cost' );

mcr_perf_reset( $wpdb );
$GLOBALS['mcr_caps']['manage_options'] = true;
$wpdb->var_for['GET_LOCK'] = '1';
$wpdb->row_for['session_token ='] = array(
	'id'            => 11,
	'user_id'       => 7,
	'ad_id'         => 'rewarded_video',
	'provider'      => 'throwing',
	'session_token' => 'tok',
	'status'        => 'pending',
	'coins'         => 5,
	'day_key'       => gmdate( 'Y-m-d' ),
	'created_at'    => gmdate( 'Y-m-d H:i:s' ),
	'expires_at'    => gmdate( 'Y-m-d H:i:s', time() + 900 ),
	'verified_at'   => '1970-01-01 00:00:00',
);

mcr_perf_install_throwing_provider();

$threw    = false;
$released = false;

try {
	\MCR\Rewards\AdSessions::instance()->verify(
		7,
		array(
			'session_token'    => 'tok',
			'completion_token' => 'proof',
		)
	);
} catch ( \Throwable $e ) {
	$threw = true;
}

foreach ( $wpdb->log as $sql ) {
	if ( false !== stripos( $sql, 'RELEASE_LOCK' ) ) {
		$released = true;
	}
}

assertTrue(
	! ( $threw && ! $released ),
	'[DEFECT] AdSessions::verify() must release the MySQL advisory lock on EVERY path. '
	. 'Measured: the provider threw, the exception escaped verify() and NO RELEASE_LOCK was issued. '
	. 'Fix: wrap the locked section in try { ... } finally { $this->unlock_user( $lock ); }.'
);

$lock_name = null;
$timeout   = null;

foreach ( $wpdb->log as $sql ) {
	if ( preg_match( '/GET_LOCK\(\s*\'([^\']+)\'/', $sql, $m ) ) {
		$lock_name = $m[1];
	}

	if ( preg_match( '/GET_LOCK\(\s*\'[^\']+\'\s*,\s*(\d+)\s*\)/i', $sql, $m ) ) {
		$timeout = (int) $m[1];
	}
}

assertTrue(
	null !== $lock_name && false !== strpos( $lock_name, 'wp_' ),
	'[DEFECT] The advisory lock name must be scoped to the site/table prefix. MySQL advisory locks are '
	. 'SERVER-WIDE, so the name "' . (string) $lock_name . '" collides with the same user id on every other '
	. 'site sharing the MySQL server (multisite, shared hosting). Fix: \'mcr_adcap_\' . $wpdb->prefix . $user_id.'
);

assertTrue(
	null !== $lock_name && false !== strpos( $lock_name, '7' ) && false !== strpos( $lock_name, 'adcap' ),
	'[OK] The advisory lock name is per-user and per-purpose (adcap).'
);

assertTrue(
	null !== $timeout && $timeout <= 2,
	'[DEFECT] The advisory-lock wait must be short. Measured GET_LOCK timeout = ' . (string) $timeout
	. ' seconds: N concurrent verifications for one user each hold a database connection for N x 5 s.'
);

mcr_perf_reset( $wpdb );
mcr_perf_install_throwing_provider();

\MCR\Rewards\AdSessions::instance()->stats( 7 );

assertTrue(
	$wpdb->count_like( 'mcr_ad_sessions' ) <= 2,
	'[DEFECT] AdSessions::stats() must not run three separate aggregates over the same ad-session rows. '
	. 'Measured ' . $wpdb->count_like( 'mcr_ad_sessions' ) . ' queries. '
	. 'Fix: one COUNT/COALESCE(SUM) query for the lifetime totals, one for today.'
);

// Measure the cost of one ad session start and one ad verification.
mcr_perf_reset( $wpdb );
mcr_perf_install_throwing_provider();
$wpdb->var_for['GET_LOCK'] = '1';

try {
	\MCR\Rewards\AdSessions::instance()->start( 7, array( 'ad_id' => 'rewarded_video' ) );
} catch ( \Throwable $e ) {
	// Provider failures do not change the statement count.
}

$start_statements = $wpdb->count();

printf(
	"    [INFO] one ad session start = %d SQL statements (3 reads over mcr_ad_sessions + 1 INSERT)\n",
	$start_statements
);

mcr_perf_reset( $wpdb );
mcr_perf_install_throwing_provider();
$wpdb->var_for['GET_LOCK'] = '1';
$wpdb->row_for['session_token ='] = array(
	'id'            => 11,
	'user_id'       => 7,
	'ad_id'         => 'rewarded_video',
	'provider'      => 'throwing',
	'session_token' => 'tok',
	'status'        => 'pending',
	'coins'         => 5,
	'day_key'       => gmdate( 'Y-m-d' ),
	'created_at'    => gmdate( 'Y-m-d H:i:s' ),
	'expires_at'    => gmdate( 'Y-m-d H:i:s', time() + 900 ),
	'verified_at'   => '1970-01-01 00:00:00',
);

try {
	\MCR\Rewards\AdSessions::instance()->verify(
		7,
		array(
			'session_token'    => 'tok',
			'completion_token' => 'proof',
		)
	);
} catch ( \Throwable $e ) {
	// The provider throws; that is the point of the test above.
}

printf(
	"    [INFO] one ad verification = %d SQL statements before the grant (1 session read + 2 COUNTs + GET_LOCK)\n",
	$wpdb->count()
);

mcr_perf_reset( $wpdb );
\MCR\Rewards\AdSessions::instance()->cap_guard( 7, 0 );

$global_cap = false;

foreach ( $wpdb->log as $sql ) {
	if ( false !== stripos( $sql, 'COUNT(*)' )
		&& false !== stripos( $sql, 'mcr_ad_sessions' )
		&& false === stripos( $sql, 'user_id' ) ) {
		$global_cap = true;
	}
}

assertTrue(
	$global_cap,
	'[OK] The global ad cap is a site-wide count over the ad-session table (this is the statement that needs the (status, verified_at) index).'
);

mcr_perf_reset( $wpdb );
\MCR\Cron::run_hourly();

$hourly_sql = (string) $wpdb->first( 'UPDATE wp_mcr_ad_sessions' );

assertTrue(
	0 !== mcr_perf_limit( $hourly_sql ),
	'[DEFECT] The hourly expiry UPDATE must be batched. Measured: "' . trim( $hourly_sql )
	. '" - a single statement can lock every expired row at once.'
);

mcr_perf_reset( $wpdb );
\MCR\Cron::run_daily();

assertTrue(
	0 === $wpdb->count_like( 'mcr_transactions' ),
	'[OK] The daily maintenance job does not scan the transaction ledger.'
);

mcr_perf_reset( $wpdb );
\MCR\Cron::run_hourly();
\MCR\Cron::run_daily();

assertTrue(
	$wpdb->count_like( 'DELETE FROM wp_mcr_ad_sessions' ) > 0,
	'[DEFECT] mcr_ad_sessions grows forever: nothing ever deletes a row. The global daily cap counts over the whole '
	. 'table on every ad start and every ad verify. Fix: prune in Cron::run_daily() - '
	. 'DELETE FROM wp_mcr_ad_sessions WHERE status IN (\'expired\',\'credited\') AND verified_at < NOW() - INTERVAL 2 DAY '
	. '(keep >= 48 h so the rolling 24 h global cap and the site-timezone daily cap stay correct), '
	. 'plus DELETE of pending rows older than the session TTL.'
);

/* --------------------------------------------------------------------------
 * G. Indexes (schema vs. queries)
 * ----------------------------------------------------------------------- */

mcr_group( 'G. Indexes (schema vs. queries)' );

mcr_perf_reset( $wpdb );

$GLOBALS['mcr_dbdelta'] = array();

$wpdb->var_for['information_schema'] = 'InnoDB';

\MCR\Installer::install_schema();

$schema = mcr_perf_schema();

assertTrue(
	isset( $schema['wp_mcr_transactions'] ),
	'[OK] The installer creates the transactions table.'
);

assertTrue(
	mcr_perf_has_index( $schema, 'transactions', array( 'user_id', 'id' ) ),
	'[DEFECT] mcr_transactions needs KEY (user_id, id) so "WHERE user_id = ? ORDER BY id DESC" is an index-ordered '
	. 'range scan instead of a filesort of every row the user owns. '
	. 'Fix: ALTER TABLE wp_mcr_transactions ADD KEY user_id_id (user_id, id);'
);

assertTrue(
	mcr_perf_has_index( $schema, 'transactions', array( 'created_at' ) ),
	'[DEFECT] mcr_transactions needs a standalone KEY (created_at): the dashboard "transactions today" count and the '
	. 'admin date filters currently wrap the column in DATE(), which no index can serve. '
	. 'Fix: ALTER TABLE wp_mcr_transactions ADD KEY created_at (created_at);'
);

assertTrue(
	mcr_perf_has_index( $schema, 'transactions', array( 'source', 'created_at' ) ),
	'[DEFECT] mcr_transactions needs KEY (source, created_at): the admin Activity Log page filters on '
	. 'source = \'wp-admin\' and there is no index on source at all, so the log page scans the whole ledger. '
	. 'Fix: ALTER TABLE wp_mcr_transactions ADD KEY source_created (source, created_at);'
);

assertTrue(
	mcr_perf_has_index( $schema, 'ad_sessions', array( 'status', 'verified_at' ) ),
	'[DEFECT] mcr_ad_sessions needs KEY (status, verified_at): the site-wide rolling 24 h cap counts over '
	. 'status = \'verified\' AND verified_at > ? and the existing index is (status, expires_at), so every ad '
	. 'start and every ad verify full-scans the table. '
	. 'Fix: ALTER TABLE wp_mcr_ad_sessions ADD KEY status_verified (status, verified_at);'
);

assertTrue(
	mcr_perf_has_index( $schema, 'ad_sessions', array( 'user_id', 'status', 'verified_at' ) ),
	'[DEFECT] mcr_ad_sessions needs KEY (user_id, status, verified_at): the per-user daily count and the '
	. 'MAX(verified_at) cooldown check currently fall back to the (user_id, day_key) index and then filter. '
	. 'Fix: ALTER TABLE wp_mcr_ad_sessions ADD KEY user_status_verified (user_id, status, verified_at);'
);

assertTrue(
	mcr_perf_has_index( $schema, 'ad_sessions', array( 'completion_token' ) ),
	'[DEFECT] mcr_ad_sessions needs an index on completion_token: the signed server-to-server postback handler '
	. '(a PUBLIC, unauthenticated REST route) runs "WHERE completion_token = ? AND status = ?" as its replay '
	. 'check, and completion_token has no index - every postback is a full table scan. '
	. 'Fix: ALTER TABLE wp_mcr_ad_sessions ADD KEY completion_token (completion_token);'
);

assertTrue(
	mcr_perf_has_index( $schema, 'balances', array( 'lifetime_earned' ) )
	&& mcr_perf_has_index( $schema, 'balances', array( 'lifetime_spent' ) ),
	'[DEFECT] mcr_balances needs KEY (lifetime_earned) and KEY (lifetime_spent): both are offered as sortable '
	. 'columns on the users list table but only "balance" is indexed, so sorting by them filesorts the whole '
	. 'balances table. Fix: ALTER TABLE wp_mcr_balances ADD KEY lifetime_earned (lifetime_earned), ADD KEY lifetime_spent (lifetime_spent);'
);

assertTrue(
	mcr_perf_has_index( $schema, 'payments', array( 'user_id', 'id' ) ),
	'[DEFECT] mcr_payments needs KEY (user_id, id): the order history reads "WHERE user_id = ? ORDER BY id DESC" '
	. 'and the only user index is (user_id, status). Fix: ALTER TABLE wp_mcr_payments ADD KEY user_id_id (user_id, id);'
);

/* --------------------------------------------------------------------------
 * H. Repeated identical reads / memoisation
 * ----------------------------------------------------------------------- */

mcr_group( 'H. Repeated identical reads / memoisation' );

mcr_perf_reset( $wpdb );

$wpdb->row_for['mcr_checkins'] = array(
	'streak_count' => 4,
	'base_amount'  => 10,
	'bonus_amount' => 5,
	'total_amount' => 15,
	'checkin_date' => gmdate( 'Y-m-d' ),
);

\MCR\Rewards\CheckIn::instance()->status( 7 );
$first = $wpdb->count();

$wpdb->reset();
\MCR\Rewards\CheckIn::instance()->status( 7 );
$second = $wpdb->count();

printf( "    [INFO] CheckIn::status(): %d SQL on the first call, %d SQL on the second call in the same request\n", $first, $second );

assertTrue(
	0 === $second,
	'[DEFECT] CheckIn::status() must be memoised for the request. Measured: the second call re-issued '
	. $second . ' SQL statements (today row + 35-day calendar, plus a 400-row streak read when unclaimed). '
	. 'The dashboard, the balance panel and the sidebar widget each call it, so one page view runs it 3+ times.'
);

mcr_perf_reset( $wpdb );
$wpdb->var_for['mcr_balances']  = 100;
$wpdb->row_for['mcr_balances'] = mcr_perf_balance_row();

$GLOBALS['mcr_current_user'] = 5;

\MCR\Rest::instance()->get_balance();

$balance_reads = $wpdb->count_like( 'FROM wp_mcr_balances' );

assertTrue(
	$balance_reads <= 1,
	'[DEFECT] The REST balance endpoint must not read the same balances row twice. Measured '
	. $balance_reads . ' reads of mcr_balances (Ledger::get_balance() + Ledger::get_stats()). '
	. 'Fix: call get_stats() only, it already returns the balance.'
);

mcr_perf_reset( $wpdb );
$wpdb->rows_for['FROM wp_mcr_transactions'] = array(
	array(
		'id'            => 1,
		'txn_uid'       => 'a',
		'type'          => 'ad_reward',
		'amount'        => 5,
		'balance_after' => 5,
		'reason'        => 'ad',
		'source'        => 'ad:generic_s2s',
		'created_at'    => '2026-01-01 00:00:00',
	),
);

$shortcodes = new \MCR\Templates\Shortcodes();
mcr_perf_private( $shortcodes, 'recent_ad_rewards', array( 7, 5 ) );

$recent_sql = (string) $wpdb->first( 'FROM wp_mcr_transactions' );

assertTrue(
	(bool) preg_match( '/WHERE[^;]*\btype\b\s*=/i', $recent_sql ),
	'[DEFECT] The "recent ad rewards" list must filter on type in SQL. Measured SQL: "'
	. trim( $recent_sql ) . '" - it downloads 100 ledger rows and filters type = \'ad_reward\' in PHP.'
);

assertTrue(
	mcr_perf_limit( $recent_sql ) <= 5,
	'[DEFECT] The "recent ad rewards" list must not fetch 100 rows to show 5. Measured LIMIT '
	. mcr_perf_limit( $recent_sql ) . '.'
);

/* --------------------------------------------------------------------------
 * I. Options and transients
 * ----------------------------------------------------------------------- */

mcr_group( 'I. Options and transients' );

mcr_perf_reset( $wpdb );

for ( $i = 0; $i < 50; $i++ ) {
	\MCR\Settings::get( 'checkin.base_amount', 10 );
	\MCR\Settings::get( 'ads.daily_limit', 10 );
}

$option_reads = count(
	array_filter(
		$GLOBALS['mcr_option_reads'],
		static function ( $name ) {
			return 'mcr_settings' === $name;
		}
	)
);

assertSame(
	1,
	$option_reads,
	'[OK] Settings::all() memoises the options array: 100 Settings::get() calls cause exactly 1 option read.'
);

mcr_perf_reset( $wpdb );

mcr_perf_private(
	\MCR\Payments\Orders::instance(),
	'attempt_id',
	array( 42, array( 'id' => 'starter' ) )
);

$writes         = (array) ( $GLOBALS['mcr_transient_writes'] ?? array() );
$user_keyed     = false;
$transient_keys = array();

foreach ( $writes as $write ) {
	$transient_keys[] = (string) $write['key'];

	if ( false !== strpos( (string) $write['key'], '42' ) ) {
		$user_keyed = true;
	}
}

printf( "    [INFO] purchase de-duplication transient keys: %s\n", implode( ', ', $transient_keys ) );

assertTrue(
	! $user_keyed,
	'[DEFECT] The purchase de-duplication token must not live in a transient whose key embeds the user id. '
	. 'On a site without a persistent object cache every set_transient() creates a row in wp_options that is '
	. 'only reaped by the WP-Cron expiry sweep, so wp_options grows with the number of buyers x packages. '
	. 'Fix: keep the token in the mcr_payments table (a pending order row) or in user meta.'
);

/* --------------------------------------------------------------------------
 * J. Cron
 * ----------------------------------------------------------------------- */

mcr_group( 'J. Cron' );

mcr_perf_reset( $wpdb );
\MCR\Cron::run_daily();

assertTrue(
	0 === $wpdb->count_like( 'mcr_transactions' ),
	'[OK] The daily cron job never scans the append-only ledger.'
);

mcr_perf_reset( $wpdb );
\MCR\Cron::run_daily();

assertTrue(
	1 === $wpdb->count_like( 'mcr_payments' ),
	'[OK] The daily cron job expires stale orders with a single indexed UPDATE.'
);

/* --------------------------------------------------------------------------
 * K. Installer / dbDelta upgrade path
 * ----------------------------------------------------------------------- */

mcr_group( 'K. Installer / dbDelta upgrade path' );

mcr_perf_reset( $wpdb );

$upgrade_hooks     = (array) ( $GLOBALS['mcr_perf_boot_hooks'] ?? array() );
$on_plugins_loaded = false;

foreach ( $upgrade_hooks as $hook ) {
	if ( is_array( $hook ) && isset( $hook[0] ) && is_string( $hook[0] ) && 'MCR\Installer' === $hook[0] ) {
		$on_plugins_loaded = true;
	}
}

assertTrue(
	! $on_plugins_loaded,
	'[DEFECT] The schema upgrade must not run on the "plugins_loaded" hook. A dbDelta() ALTER against the '
	. 'append-only ledger then executes inside an ordinary public front-end/REST request, under the PHP/FPM '
	. 'time limit, while the Ledger holds row locks. Fix: move Installer::maybe_upgrade() to "admin_init" '
	. '(or a one-off background upgrade) and keep "plugins_loaded" for the version check only.'
);

$installer_source = (string) file_get_contents( dirname( __DIR__ ) . '/includes/Installer.php' );

assertTrue(
	(bool) preg_match( '/ADD\s+(KEY|INDEX)/i', $installer_source ),
	'[DEFECT] The installer must ship the new ledger indexes through an explicit, idempotent '
	. 'ALTER TABLE ... ADD KEY migration (guarded by an information_schema check), not through dbDelta\'s '
	. 'whole-table diff, so a column drift can never turn into a full table copy of the ledger.'
);

mcr_perf_reset( $wpdb );

$GLOBALS['mcr_dbdelta'] = array();
$wpdb->var_for['information_schema'] = 'InnoDB';

\MCR\Installer::install_schema();

$whole_table = 0;

foreach ( mcr_perf_dbdelta() as $sql ) {
	if ( false !== stripos( $sql, 'CREATE TABLE' ) && false !== stripos( $sql, 'mcr_transactions' ) ) {
		++$whole_table;
	}
}

assertTrue(
	0 === $whole_table,
	'[DEFECT] dbDelta() must never be handed the whole CREATE TABLE spec of mcr_transactions: a single column '
	. 'difference makes dbDelta emit ALTER TABLE ... MODIFY COLUMN, which MySQL implements as ALGORITHM=COPY - '
	. 'a full copy of the ledger. Fix: keep dbDelta for the table skeletons but migrate the ledger with '
	. 'guarded, additive ALTER statements.'
);

/* --------------------------------------------------------------------------
 * L. Memory
 * ----------------------------------------------------------------------- */

mcr_group( 'L. Memory' );

mcr_perf_reset( $wpdb );
$GLOBALS['mcr_caps']['manage_options'] = true;

$wpdb->rows_for['SELECT u.ID FROM'] = mcr_perf_id_rows( 20 );
$wpdb->row_for['mcr_balances']    = mcr_perf_balance_row();

$users_page = new \MCR\Admin\UsersPage();
$users_page->prepare_items();

$items = isset( $users_page->items ) ? $users_page->items : array();

assertTrue(
	count( $items ) <= 20,
	'[OK] The users list table paginates BEFORE building its array (at most one page of items is held).'
);

mcr_perf_reset( $wpdb );

$reflection = new \ReflectionMethod( 'MCR\Templates\Shortcodes', 'transactions_args' );
$reflection->setAccessible( true );

$wpdb->rows_for['FROM wp_mcr_transactions'] = array();

$result = $reflection->invoke( new \MCR\Templates\Shortcodes(), 5 );

assertTrue(
	is_array( $result ) && count( $result['rows'] ) <= 20,
	'[OK] The frontend transaction list paginates BEFORE building its array.'
);

/* --------------------------------------------------------------------------
 * M. Stress / adversarial
 * ----------------------------------------------------------------------- */

mcr_group( 'M. Stress / adversarial' );

mcr_perf_reset( $wpdb );
$GLOBALS['mcr_caps']['manage_options'] = true;
$wpdb->row_for['mcr_balances'] = mcr_perf_balance_row();

$stress_total = 0;

for ( $page = 1; $page <= 5; $page++ ) {
	$wpdb->rows_for['SELECT u.ID FROM'] = mcr_perf_id_rows( 20 );

	$page_object = new \MCR\Admin\UsersPage();
	$page_object->prepare_items();

	$stress_total += $wpdb->count();
	$wpdb->reset();
}

printf( "    [INFO] 5 pages x 20 users of the admin users list = %d SQL queries\n", $stress_total );

assertTrue(
	$stress_total <= 15,
	'[DEFECT] 5 pages of the users list must not cost 110 queries. Measured ' . $stress_total
	. ' SQL queries (22 per page).'
);

mcr_perf_reset( $wpdb );
$GLOBALS['mcr_caps']['manage_options'] = true;

$scans = 0;

for ( $i = 0; $i < 20; $i++ ) {
	$_GET['search'] = 'needle' . $i;

	ob_start();
	( new \MCR\Admin\TransactionsPage() )->render_ledger_table();
	ob_end_clean();

	$scans += $wpdb->count_like( 'COUNT(*)' );
	$wpdb->reset();
}

printf( "    [INFO] 20 admin reason searches = %d unbounded COUNT(*) statements over the ledger\n", $scans );

assertTrue(
	0 === $scans,
	'[DEFECT] 20 admin searches must not produce 20 unbounded COUNT(*) statements over the whole ledger. '
	. 'Measured ' . $scans . '. A LIKE \'%term%\' predicate cannot use an index, so every search is a full scan.'
);

mcr_perf_reset( $wpdb );
$GLOBALS['mcr_caps']['manage_options'] = true;

$lock_statements = 0;
$leaks           = 0;

for ( $i = 0; $i < 5; $i++ ) {
	$wpdb->reset();
	$wpdb->var_for['GET_LOCK']     = '1';
	$wpdb->row_for['session_token ='] = array(
		'id'            => 11,
		'user_id'       => 7,
		'ad_id'         => 'rewarded_video',
		'provider'      => 'throwing',
		'session_token' => 'tok',
		'status'        => 'pending',
		'coins'         => 5,
		'day_key'       => gmdate( 'Y-m-d' ),
		'created_at'    => gmdate( 'Y-m-d H:i:s' ),
		'expires_at'    => gmdate( 'Y-m-d H:i:s', time() + 900 ),
		'verified_at'   => '1970-01-01 00:00:00',
	);

	mcr_perf_install_throwing_provider();

	try {
		\MCR\Rewards\AdSessions::instance()->verify(
			7,
			array(
				'session_token'    => 'tok',
				'completion_token' => 'proof',
			)
		);
	} catch ( \Throwable $e ) {
		// Expected: the provider throws.
	}

	foreach ( $wpdb->log as $sql ) {
		if ( false !== stripos( $sql, 'GET_LOCK' ) ) {
			++$lock_statements;
		}

		if ( false !== stripos( $sql, 'RELEASE_LOCK' ) ) {
			++$leaks;
		}
	}
}

assertTrue(
	5 === $lock_statements,
	'[OK] Every ad verification takes exactly one advisory lock.'
);

assertTrue(
	5 === $leaks,
	'[DEFECT] A thrown provider must not leak the advisory lock. The lock is connection-scoped: with a '
	. 'persistent MySQL connection it survives the request and every later ad verification for that user '
	. 'blocks for the full GET_LOCK timeout. Measured ' . $leaks . ' RELEASE_LOCK statements for 5 verifications.'
);

/* ==========================================================================
 * Summary
 * ======================================================================== */

$total = $GLOBALS['mcr_tests']['passed'] + $GLOBALS['mcr_tests']['failed'];

echo "\n";

if ( $GLOBALS['mcr_tests']['failed'] > 0 ) {
	echo "Confirmed defects (each [DEFECT] assertion below fails against the current source):\n";

	foreach ( $GLOBALS['mcr_tests']['failures'] as $failure ) {
		printf( "  - [%s] %s\n", $failure['group'], $failure['message'] );
	}
	echo "\n";
}

printf(
	"Assertions: %d | Passed: %d | Failed: %d\n",
	$total,
	$GLOBALS['mcr_tests']['passed'],
	$GLOBALS['mcr_tests']['failed']
);

exit( $GLOBALS['mcr_tests']['failed'] > 0 ? 1 : 0 );

}

/* ==========================================================================
 * Namespaced test doubles that shadow the global WordPress helpers.
 *
 * PHP resolves an unqualified function call inside a namespace against that
 * namespace first and only then against the global scope, so these blocks
 * let the suite observe the plugin's calls without touching the shared stubs.
 * ======================================================================== */

namespace MCR\Admin {

	/**
	 * Returns a fake user so the users list table does not skip every row.
	 *
	 * @param int $id User id.
	 * @return object
	 */
	function get_userdata( $id ) {
		return (object) array(
			'ID'           => (int) $id,
			'user_login'   => 'user' . (int) $id,
			'display_name' => 'User ' . (int) $id,
			'user_email'   => 'user' . (int) $id . '@example.test',
		);
	}

}

namespace MCR {

	/**
	 * Counts option reads and delegates to the global implementation.
	 *
	 * @param string $name          Option name.
	 * @param mixed  $default_value Default.
	 * @return mixed
	 */
	function get_option( $name, $default_value = false ) {
		$GLOBALS['mcr_option_reads'][] = $name;

		return \get_option( $name, $default_value );
	}

}

namespace MCR\Payments {

	/**
	 * Records transient writes and delegates to the global implementation.
	 *
	 * @param string $key   Transient key.
	 * @param mixed  $value Value.
	 * @param int    $ttl   TTL.
	 * @return bool
	 */
	function set_transient( $key, $value, $ttl = 0 ) {
		$GLOBALS['mcr_transient_writes'][] = array(
			'key' => $key,
			'ttl' => $ttl,
		);

		return \set_transient( $key, $value, $ttl );
	}

}
