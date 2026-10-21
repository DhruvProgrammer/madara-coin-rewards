<?php
/**
 * WordPress-integration review harness.
 *
 * Adversarial review of the plugin's behaviour inside a real WordPress
 * install: hook wiring, load order, activation/deactivation/uninstall,
 * the Settings API, the admin menu, the REST API, cron, transients,
 * multisite, i18n, capabilities, theme integration and the asset pipeline.
 *
 * Every assertion is written so that it FAILS for the defect it proves.
 * Assertions that describe a CONTROL that is already correct are marked
 * "(control)" and are expected to pass.
 *
 * Run with:
 *   php tests/review-wp-integration.php
 *
 * @package MCR
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/wp-stubs.php';

/* -------------------------------------------------------------------------
 * Local stubs for WordPress functions the shared harness does not provide.
 *
 * They are faithful to WordPress core semantics; in particular
 * esc_textarea() calls htmlspecialchars() WITHOUT casting, exactly like
 * wp-includes/formatting.php, so an array argument raises the same
 * TypeError it raises in production.
 * ---------------------------------------------------------------------- */

if ( ! function_exists( 'check_admin_referer' ) ) {
	/**
	 * Nonce verification stub (as in wp-admin/includes/plugin.php).
	 *
	 * @param string $action    Action.
	 * @param string $query_arg Query arg.
	 * @return bool
	 */
	function check_admin_referer( $action = -1, $query_arg = '_wpnonce' ) {
		return true;
	}
}

if ( ! function_exists( 'esc_textarea' ) ) {
	/**
	 * Textarea escaping stub (as in wp-includes/formatting.php).
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function esc_textarea( $text ) {
		// NOTE: no (string) cast - this is what WordPress core does.
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'wp_get_referer' ) ) {
	/**
	 * Referer stub (as in wp-includes/functions.php).
	 *
	 * @return false
	 */
	function wp_get_referer() {
		return false;
	}
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
	/**
	 * Minimal REST response stand-in.
	 */
	class WP_REST_Response {

		/**
		 * Response payload.
		 *
		 * @var mixed
		 */
		public $data;

		/**
		 * HTTP status.
		 *
		 * @var int
		 */
		public $status;

		/**
		 * Constructor.
		 *
		 * @param mixed $data   Payload.
		 * @param int   $status Status.
		 */
		public function __construct( $data = null, $status = 200 ) {
			$this->data   = $data;
			$this->status = $status;
		}

		/**
		 * Returns the payload.
		 *
		 * @return mixed
		 */
		public function get_data() {
			return $this->data;
		}
	}
}

if ( ! function_exists( 'rest_ensure_response' ) ) {
	/**
	 * REST response normaliser stub.
	 *
	 * @param mixed $response Response.
	 * @return WP_REST_Response
	 */
	function rest_ensure_response( $response ) {
		if ( $response instanceof WP_REST_Response ) {
			return $response;
		}

		return new WP_REST_Response( $response, 200 );
	}
}

/**
 * A wpdb double that can return canned rows.
 */
class MCR_Review_WPDB extends MCR_Fake_WPDB {

	/**
	 * Rows returned by get_results().
	 *
	 * @var array
	 */
	public $canned_rows = array();

	/**
	 * Returns canned rows when present.
	 *
	 * @param string $sql  SQL.
	 * @param string $type Type.
	 * @return array
	 */
	public function get_results( $sql, $type = 'OBJECT' ) {
		if ( ! empty( $this->canned_rows ) ) {
			return $this->canned_rows;
		}

		return parent::get_results( $sql, $type );
	}
}

/* -------------------------------------------------------------------------
 * Helpers
 * ---------------------------------------------------------------------- */

/**
 * Reads a plugin source file (line endings normalised).
 *
 * @param string $rel Path relative to the plugin root.
 * @return string
 */
function mcr_src( $rel ) {
	static $cache = array();

	$rel = ltrim( str_replace( '\\', '/', $rel ), '/' );

	if ( ! isset( $cache[ $rel ] ) ) {
		$path = dirname( __DIR__ ) . '/' . $rel;
		$cache[ $rel ] = is_readable( $path ) ? str_replace( "\r\n", "\n", (string) file_get_contents( $path ) ) : '';
	}

	return $cache[ $rel ];
}

/**
 * Extracts the body of a method from a plugin source file.
 *
 * @param string $file  Source file path relative to the plugin root.
 * @param string $class Class name.
 * @param string $method Method name.
 * @return string
 */
function mcr_method_body( $file, $class, $method ) {
	$reflect = new ReflectionMethod( $class, $method );
	$lines   = file( dirname( __DIR__ ) . '/' . $file, FILE_IGNORE_NEW_LINES );
	$body    = array_slice( $lines, $reflect->getStartLine() - 1, $reflect->getEndLine() - $reflect->getStartLine() + 1 );

	return str_replace( "\r\n", "\n", implode( "\n", $body ) );
}

/**
 * Emulates WordPress' update_option() for a sanitised option.
 *
 * update_option() -> sanitize_option() -> apply_filters( "sanitize_option_{$option}" ).
 * register_setting() wires the plugin's sanitize_callback to exactly that
 * filter, so the stored value is the callback's return value.
 *
 * @param string $option Option name.
 * @param mixed  $value  Raw value.
 * @return mixed Stored value.
 */
function mcr_wp_update_option( $option, $value ) {
	$stored = apply_filters( 'sanitize_option_' . $option, $value, $option, $value );

	$GLOBALS['mcr_options'][ $option ] = $stored;

	return $stored;
}

/**
 * Extracts the dot-notation setting keys rendered by the Settings form.
 *
 * Keys built by concatenation ('template.layout_' . $page) are skipped.
 *
 * @return array
 */
function mcr_settings_form_keys() {
	$src = mcr_src( 'includes/Admin/SettingsPage.php' );

	preg_match_all( "/'key'\s*=>\s*'([a-z0-9_.]+)'(?!\s*\.)/", $src, $matches );

	return array_values( array_unique( $matches[1] ) );
}

/**
 * Extracts the admin action names dispatched by Admin::maybe_handle_actions().
 *
 * @return array
 */
function mcr_handled_admin_actions() {
	$src = mcr_src( 'includes/Admin/Admin.php' );

	if ( ! preg_match( '/private function maybe_handle_actions\(\).*?\n\t\}/s', $src, $block ) ) {
		return array();
	}

	preg_match_all( "/case '([^']+)':/", $block[0], $matches );

	return $matches[1];
}

/**
 * Extracts the admin action names posted by the Tools screen.
 *
 * @return array
 */
function mcr_tools_posted_actions() {
	$src = mcr_src( 'includes/Admin/ToolsPage.php' );

	preg_match_all( "/name=\"mcr_action\" value=\"([^\"]+)\"/", $src, $matches );

	return $matches[1];
}

/**
 * Whether a callback was registered on a hook.
 *
 * @param string   $hook     Hook name.
 * @param callable $callback Callback.
 * @return bool
 */
function mcr_hook_registered( $hook, $callback ) {
	foreach ( array( 'mcr_actions', 'mcr_filters' ) as $bucket ) {
		$registered = isset( $GLOBALS[ $bucket ][ $hook ] ) ? $GLOBALS[ $bucket ][ $hook ] : array();

		foreach ( $registered as $registered_callback ) {
			if ( ! is_array( $registered_callback ) || 2 !== count( $registered_callback ) ) {
				continue;
			}

			$registered_class = is_object( $registered_callback[0] ) ? get_class( $registered_callback[0] ) : (string) $registered_callback[0];
			$expected_class   = is_object( $callback[0] ) ? get_class( $callback[0] ) : (string) $callback[0];

			if ( ltrim( $registered_class, '\\' ) === ltrim( $expected_class, '\\' )
				&& $registered_callback[1] === $callback[1]
				&& is_callable( $registered_callback ) ) {
				return true;
			}
		}
	}

	return false;
}

/**
 * Counts the commas that are not inside parentheses or quotes.
 *
 * @param string $line Line.
 * @return int
 */
function mcr_top_level_commas( $line ) {
	$depth = 0;
	$count = 0;
	$len   = strlen( $line );

	for ( $i = 0; $i < $len; $i++ ) {
		$char = $line[ $i ];

		if ( "'" === $char ) {
			$i++;

			while ( $i < $len && "'" !== $line[ $i ] ) {
				$i++;
			}

			continue;
		}

		if ( '(' === $char ) {
			$depth++;
			continue;
		}

		if ( ')' === $char ) {
			$depth--;
			continue;
		}

		if ( ',' === $char && 0 === $depth ) {
			$count++;
		}
	}

	return $count;
}

/* -------------------------------------------------------------------------
 * The plugin main file is included here: it registers the autoloader at
 * include time, exactly as WordPress does when it loads the plugin.
 * ---------------------------------------------------------------------- */

require_once dirname( __DIR__ ) . '/madara-coin-rewards.php';

/* -------------------------------------------------------------------------
 * Child-process scenarios.
 *
 * mcr-child-export : proves the static-method $this defect at runtime.
 * mcr-child-admin  : proves that an unhandled admin action is swallowed.
 *
 * Run as: php review-wp-integration.php mcr-child-export
 * ---------------------------------------------------------------------- */

if ( isset( $argv[1] ) && 'mcr-child-export' === $argv[1] ) {
	$GLOBALS['mcr_caps']['manage_options'] = true;

	$wpdb = new MCR_Review_WPDB();
	$wpdb->canned_rows = array(
		array(
			'id'            => 1,
			'txn_uid'       => 'abc',
			'user_id'       => 7,
			'type'          => 'checkin',
			'amount'        => 10,
			'balance_after' => 10,
			'reason'        => 'Daily reward',
			'source'        => 'web',
			'created_at'    => '2026-01-01 00:00:00',
		),
	);
	$GLOBALS['wpdb'] = $wpdb;

	$_POST = array(
		'_wpnonce' => 'x',
		'user'     => '',
	);

	ob_start();

	try {
		\MCR\Admin\TransactionsPage::export_via_admin_post();
	} catch ( \Throwable $e ) {
		ob_end_clean();
		echo 'MCR_CHILD_ERROR: ' . get_class( $e ) . ': ' . $e->getMessage();
		exit( 0 );
	}

	ob_end_clean();
	echo 'MCR_CHILD_OK';
	exit( 0 );
}

if ( isset( $argv[1] ) && 'mcr-child-admin' === $argv[1] ) {
	$GLOBALS['mcr_caps']['manage_options'] = true;

	$_POST = array(
		'mcr_action' => isset( $argv[2] ) ? $argv[2] : '',
		'_wpnonce'   => 'x',
		'user_id'    => 0,
		'reason'     => 'review harness',
		'confirm'    => 'yes-delete-everything',
	);

	register_shutdown_function(
		function () {
			echo 'MCR_CHILD_NOTICE=' . ( get_transient( 'mcr_admin_notice' ) ? 'yes' : 'no' );
		}
	);

	$dispatch = new ReflectionMethod( '\MCR\Admin\Admin', 'maybe_handle_actions' );
	$dispatch->setAccessible( true );

	try {
		$dispatch->invoke( new \MCR\Admin\Admin() );
	} catch ( \Throwable $e ) {
		echo 'MCR_CHILD_ERROR: ' . get_class( $e ) . ': ' . $e->getMessage();
	}

	echo 'MCR_CHILD_END';
	exit( 0 );
}

/* -------------------------------------------------------------------------
 * Test suite
 * ---------------------------------------------------------------------- */

$GLOBALS['wpdb'] = new MCR_Review_WPDB();

/**
 * Resets the plugin option store to a known state.
 *
 * @return void
 */
function mcr_reset_options() {
	$GLOBALS['mcr_options'] = array(
		'mcr_settings' => array(),
	);
	$GLOBALS['mcr_caps'] = array();

	if ( class_exists( '\MCR\Settings' ) ) {
		\MCR\Settings::flush_cache();
	}
}

// ==========================================================================
mcr_group( '1. Load order: autoloader, plugin file, activation hooks' );
// ==========================================================================

// CONTROL: the autoloader is registered at plugin-file include time, so the
// classes are resolvable in an activation request (plugins_loaded already
// fired there). A plugin that registered the autoloader inside
// plugins_loaded would not satisfy this.
assertTrue( class_exists( '\MCR\Installer', true ), 'control: the autoloader is registered at plugin-file include time (MCR\\Installer resolves without plugins_loaded)' );
assertTrue( class_exists( '\MCR\Autoloader', true ), 'control: MCR\\Autoloader is resolvable at include time' );

// CONTROL: the activation / deactivation hooks are procedural callbacks.
assertTrue( is_callable( 'mcr_activate' ), 'control: mcr_activate() is a callable procedural activation callback' );
assertTrue( is_callable( 'mcr_deactivate' ), 'control: mcr_deactivate() is a callable procedural deactivation callback' );
assertSame( 'mcr_activate', $GLOBALS['mcr_activation'], 'control: register_activation_hook() points at the procedural mcr_activate()' );
assertSame( 'mcr_deactivate', $GLOBALS['mcr_deactivation'], 'control: register_deactivation_hook() points at the procedural mcr_deactivate()' );

// CONTROL: the activation callback requires the Installer explicitly so it
// never depends on hook ordering.
assertTrue( false !== strpos( mcr_src( 'madara-coin-rewards.php' ), "require_once MCR_PLUGIN_DIR . 'includes/Installer.php';" ), 'control: mcr_activate() requires includes/Installer.php directly' );

// DEFECT: the activation hook ignores WordPress' $network_wide argument.
$reflect = new ReflectionFunction( 'mcr_activate' );
assertTrue( $reflect->getNumberOfParameters() >= 1, 'DEFECT: mcr_activate() must accept the $network_wide argument WordPress passes to the activation hook' );

// DEFECT: there is no multisite handling anywhere in the plugin.
$plugin_php = '';

foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( dirname( __DIR__ ) . '/includes' ) ) as $file ) {
	if ( 'php' === strtolower( $file->getExtension() ) ) {
		$plugin_php .= (string) file_get_contents( $file->getPathname() );
	}
}

$plugin_php .= mcr_src( 'madara-coin-rewards.php' );
$plugin_php .= mcr_src( 'uninstall.php' );

assertTrue( false !== strpos( $plugin_php, 'is_multisite' ), 'DEFECT: the plugin contains no is_multisite() handling, so a network activation only provisions the one site it was activated from' );
assertTrue( false !== strpos( $plugin_php, 'switch_to_blog' ), 'DEFECT: the plugin never switches sites, so per-site tables / cron / capabilities are never created on the other sites of a network' );

// ==========================================================================
mcr_group( '2. Boot: hooks are registered on real WordPress hooks' );
// ==========================================================================

mcr_reset_options();

\MCR\Plugin::instance()->boot();

$expected_hooks = array(
	'init'                               => array( '\MCR\Plugin', 'load_textdomain' ),
	'plugins_loaded'                     => array( '\MCR\Installer', 'maybe_upgrade' ),
	'widgets_init'                       => array( '\MCR\Plugin', 'register_widgets' ),
	'wp_head'                            => array( '\MCR\Integrations\Madara', 'print_theme_bridge' ),
	'show_user_profile'                  => array( '\MCR\Integrations\Profile', 'render_profile_panel' ),
	'edit_user_profile'                  => array( '\MCR\Integrations\Profile', 'render_profile_panel' ),
	'personal_options_update'            => array( '\MCR\Integrations\Profile', 'save_profile_panel' ),
	'edit_user_profile_update'           => array( '\MCR\Integrations\Profile', 'save_profile_panel' ),
	'wp_privacy_personal_data_exporters' => array( '\MCR\Plugin', 'register_privacy_exporter' ),
	'wp_privacy_personal_data_erasers'   => array( '\MCR\Plugin', 'register_privacy_eraser' ),
	'rest_api_init'                      => array( '\MCR\Rest', 'register_routes' ),
	'mcr_daily_maintenance'              => array( '\MCR\Cron', 'run_daily' ),
	'mcr_hourly_maintenance'             => array( '\MCR\Cron', 'run_hourly' ),
);

foreach ( $expected_hooks as $hook => $callback ) {
	assertTrue( mcr_hook_registered( $hook, $callback ), 'control: the "' . $hook . '" hook is registered with a callable callback' );
}

// CONTROL: the profile hooks carry the argument signature WordPress passes.
assertSame( 1, ( new ReflectionMethod( '\MCR\Integrations\Profile', 'render_profile_panel' ) )->getNumberOfParameters(), 'control: render_profile_panel() takes the $user argument show_user_profile/edit_user_profile pass' );
assertSame( 1, ( new ReflectionMethod( '\MCR\Integrations\Profile', 'save_profile_panel' ) )->getNumberOfParameters(), 'control: save_profile_panel() takes the $user_id argument personal_options_update/edit_user_profile_update pass' );

// CONTROL: the six shortcodes are registered with the names the created
// pages use.
$mcr_shortcodes = isset( $GLOBALS['mcr_shortcodes'] ) ? $GLOBALS['mcr_shortcodes'] : array();

foreach ( \MCR\Pages::shortcodes() as $shortcode ) {
	assertTrue( isset( $mcr_shortcodes[ $shortcode ] ), 'control: the "' . $shortcode . '" shortcode is registered' );
}

assertCount( 6, $mcr_shortcodes, 'control: exactly the six plugin shortcodes are registered' );

// ==========================================================================
mcr_group( '3. Admin menu registration' );
// ==========================================================================

mcr_reset_options();
$GLOBALS['mcr_caps']['mcr_manage_settings'] = true;

$GLOBALS['mcr_menu_pages'] = array();
$admin = new \MCR\Admin\Admin();
$admin->admin_menu();

$menu = isset( $GLOBALS['mcr_menu_pages'] ) ? $GLOBALS['mcr_menu_pages'] : array();

assertCount( 8, $menu, 'control: the admin registers one top-level page and seven submenu pages' );

// add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $callback, $icon, $position )
$top = $menu[0];
assertSame( 'Madara Coins', $top[0], 'control: add_menu_page() receives the page title first' );
assertSame( 'Madara Coins', $top[1], 'control: add_menu_page() receives the menu title second' );
assertSame( \MCR\Capabilities::MANAGE_SETTINGS, $top[2], 'control: add_menu_page() uses the plugin capability' );
assertSame( \MCR\Admin\Admin::MENU_SLUG, $top[3], 'control: add_menu_page() uses the top-level slug' );
assertTrue( is_callable( $top[4] ), 'control: the top-level menu render callback is callable' );
assertSame( 'dashicons-chart-line', $top[5], 'control: the menu icon is a dashicon' );
assertSame( 56, $top[6], 'control: the menu position is 56' );

foreach ( array_slice( $menu, 1 ) as $submenu ) {
	// add_submenu_page( $parent, $page_title, $menu_title, $capability, $menu_slug, $callback )
	assertTrue( is_callable( $submenu[5] ), 'control: the submenu render callback "' . $submenu[4] . '" is callable' );

	$reflect = new ReflectionMethod( $submenu[5][0], $submenu[5][1] );
	assertTrue( $reflect->isPublic(), 'control: the submenu render callback "' . $submenu[4] . '" is public' );
}

// CONTROL: every admin page class is reachable through a public render method.
foreach ( array( 'render_dashboard', 'render_settings', 'render_packages', 'render_users', 'render_transactions', 'render_logs', 'render_tools' ) as $method ) {
	$reflect = new ReflectionMethod( '\MCR\Admin\Admin', $method );
	assertTrue( $reflect->isPublic() && ! $reflect->isStatic(), 'control: Admin::' . $method . '() is a public instance method' );
}

// CONTROL: the export handler is a public static method (a static callback
// registered with add_action() must be callable statically).
$export = new ReflectionMethod( '\MCR\Admin\TransactionsPage', 'export_via_admin_post' );
assertTrue( $export->isPublic() && $export->isStatic(), 'control: TransactionsPage::export_via_admin_post() is a public static method' );

// DEFECT: the entire menu is gated on can_manage_settings(), so a user who
// holds only mcr_manage_coins can never reach the submenu pages that are
// themselves registered with mcr_manage_coins.
mcr_reset_options();
$GLOBALS['mcr_caps']['mcr_manage_coins'] = true;

$GLOBALS['mcr_menu_pages'] = array();
$coin_manager = new \MCR\Admin\Admin();
$coin_manager->admin_menu();

assertTrue(
	count( isset( $GLOBALS['mcr_menu_pages'] ) ? $GLOBALS['mcr_menu_pages'] : array() ) > 0,
	'DEFECT: a user holding only mcr_manage_coins gets no admin menu at all, so the Coins submenu pages registered with mcr_manage_coins are unreachable'
);

// ==========================================================================
mcr_group( '4. The Settings API' );
// ==========================================================================

mcr_reset_options();

// CONTROL: register_setting() supplies a sanitize callback.
assertTrue(
	false !== strpos( mcr_src( 'includes/Admin/Admin.php' ), "'sanitize_callback' => array( 'MCR\\Settings', 'sanitize' )" ),
	'control: register_setting() registers a sanitize_callback'
);

// CONTROL: every key the Settings form renders exists in Settings::defaults().
$defaults = \MCR\Settings::defaults();

foreach ( mcr_settings_form_keys() as $key ) {
	assertTrue( array_key_exists( $key, $defaults ), 'control: the rendered setting "' . $key . '" has a default' );
}

// CONTROL: the section/field page slug matches the submenu slug and the
// do_settings_sections() call.
assertSame( 'mcr-settings', \MCR\Admin\SettingsPage::PAGE_SLUG, 'control: the settings page slug is mcr-settings' );
assertTrue(
	false !== strpos( mcr_src( 'includes/Admin/SettingsPage.php' ), 'do_settings_sections( self::PAGE_SLUG )' ),
	'control: do_settings_sections() is called with the page the sections were registered on'
);
assertSame( 'mcr_settings_group', \MCR\Admin\SettingsPage::OPTION_GROUP, 'control: the option group matches settings_fields()' );

// DEFECT: Settings::sanitize() returns only the keys that were submitted.
// register_setting() wires it to the sanitize_option_mcr_settings filter, so
// update_option() REPLACES the whole mcr_settings option with that subset.
mcr_reset_options();
$GLOBALS['mcr_options']['mcr_settings'] = array(
	'packages'            => array(
		'starter' => array( 'id' => 'starter', 'title' => 'Starter', 'coins' => 100, 'price' => 1.0, 'active' => 'on' ),
	),
	'checkin.base_amount' => 25,
	'ads.coins_per_ad'    => 12,
);

$clean = \MCR\Settings::sanitize( array( 'checkin.base_amount' => '30' ) );

assertTrue( array_key_exists( 'packages', $clean ), 'DEFECT: Settings::sanitize() drops the "packages" key, so saving the Settings screen deletes every configured coin package' );
assertTrue( array_key_exists( 'ads.coins_per_ad', $clean ), 'DEFECT: Settings::sanitize() drops every key the form does not submit, so all other settings are reset to their defaults' );

// Emulate WordPress' update_option() for the Settings screen submit.
mcr_reset_options();
$GLOBALS['mcr_options']['mcr_settings'] = array(
	'packages'            => array(
		'starter' => array( 'id' => 'starter', 'title' => 'Starter', 'coins' => 100, 'price' => 1.0, 'active' => 'on' ),
	),
	'checkin.base_amount' => 25,
	'ads.coins_per_ad'    => 12,
	'pages.checkin'       => 42,
);

add_filter( 'sanitize_option_mcr_settings', array( '\MCR\Settings', 'sanitize' ) );

$stored = mcr_wp_update_option( 'mcr_settings', array( 'checkin.base_amount' => '30' ) );

assertTrue( array_key_exists( 'packages', $stored ), 'DEFECT: submitting the Settings screen replaces the whole mcr_settings option and destroys the coin packages' );
assertTrue( array_key_exists( 'pages.checkin', $stored ), 'DEFECT: submitting the Settings screen wipes the page assignments (pages.*) as well' );

// DEFECT: the Packages screen posts only mcr_settings[packages]; every other
// setting falls back to its default.
mcr_reset_options();
$GLOBALS['mcr_options']['mcr_settings'] = array(
	'packages'            => array(
		'starter' => array( 'id' => 'starter', 'title' => 'Starter', 'coins' => 100, 'price' => 1.0, 'active' => 'on' ),
	),
	'checkin.base_amount' => 25,
	'pages.checkin'       => 42,
);

$stored = mcr_wp_update_option( 'mcr_settings', array( 'packages' => '{"starter":{"id":"starter"}}' ) );

assertTrue( array_key_exists( 'checkin.base_amount', $stored ), 'DEFECT: submitting the Packages screen resets every other plugin setting back to its default value' );
assertTrue( array_key_exists( 'pages.checkin', $stored ), 'DEFECT: submitting the Packages screen resets the page assignments back to 0' );

// DEFECT: the Packages editor posts a JSON string but sanitizePackages()
// only accepts an array, so a package can never be stored.
$packages = \MCR\Settings::sanitizePackages( '{"starter":{"id":"starter","title":"Starter","coins":100,"price":1.0,"active":"on"}}' );

assertTrue( count( $packages ) > 0, 'DEFECT: Settings::sanitizePackages() rejects the JSON string the Packages textarea posts, so the Packages screen can never save a package' );

// DEFECT: the streak bonus map is submitted as JSON text and is discarded.
$bonuses = \MCR\Settings::sanitizeBonuses( '{"3": 5, "7": 15, "30": 50}' );

assertTrue( count( $bonuses ) > 0, 'DEFECT: Settings::sanitizeBonuses() rejects the JSON string the Streak bonuses textarea posts, so the bonus map is wiped on every settings save' );

// DEFECT: checkin.bonuses defaults to an array and the textarea renders it
// through esc_textarea(), which is htmlspecialchars() without a cast: on
// PHP 8 that is a TypeError and the whole Settings screen 500s.
$thrown = null;

ob_start();

try {
	( new \MCR\Admin\SettingsPage() )->render_field_textarea( array( 'key' => 'checkin.bonuses' ) );
} catch ( \Throwable $e ) {
	$thrown = $e;
}

ob_end_clean();

assertFalse( $thrown instanceof TypeError, 'DEFECT: rendering the Streak bonuses textarea passes an array to esc_textarea() and fatals on PHP 8 (TypeError)' );

if ( $thrown instanceof Throwable ) {
	assertFalse(
		false !== strpos( $thrown->getMessage(), 'htmlspecialchars' ),
		'DEFECT: the Settings screen fatal comes from htmlspecialchars() receiving the array default of checkin.bonuses'
	);
}

// DEFECT: uninstall.php keys off mcr_settings["uninstall.purge_data"], a
// key that is not a default and is not rendered by any form, so it can
// never be stored - the uninstall purge branch is dead code.
assertTrue(
	array_key_exists( 'uninstall.purge_data', \MCR\Settings::defaults() ),
	'DEFECT: uninstall.php reads mcr_settings["uninstall.purge_data"], but that key is not a Settings default and is rendered by no form, so the uninstall purge branch can never run'
);

$purge_stored = \MCR\Settings::sanitize( array( 'uninstall.purge_data' => 'on' ) );
assertTrue( array_key_exists( 'uninstall.purge_data', $purge_stored ), 'DEFECT: the uninstall purge flag cannot survive the sanitize callback, so uninstall.php always skips the purge' );

// DEFECT: Settings::update() cannot persist any key that is not a default,
// which silently drops the gateway and ad-provider settings.
mcr_reset_options();
$GLOBALS['mcr_options']['mcr_settings'] = \MCR\Settings::defaults();

$clean = \MCR\Settings::sanitize( array( 'gateways.stripe' => array( 'secret_key' => 'sk_test' ) ) );
assertTrue( array_key_exists( 'gateways.stripe', $clean ), 'DEFECT: Settings::sanitize() drops the "gateways.*" keys, so Gateway::save_settings() can never store the Stripe configuration' );

$clean = \MCR\Settings::sanitize( array( 'ads.providers.generic_s2s' => array( 'secret' => 'x' ) ) );
assertTrue( array_key_exists( 'ads.providers.generic_s2s', $clean ), 'DEFECT: Settings::sanitize() drops the "ads.providers.*" keys, so Provider::save_settings() can never store the ad provider configuration' );

// DEFECT: the settings option group has no capability and the plugin never
// registers the option_page_capability filter, so options.php demands
// manage_options while the menu uses the plugin's own capability.
$admin_src = mcr_src( 'includes/Admin/Admin.php' );
assertTrue( false !== strpos( $admin_src, "'capability'" ), 'DEFECT: register_setting() declares no capability for the mcr_settings_group option group' );
assertTrue( false !== strpos( $plugin_php, 'option_page_capability_' ), 'DEFECT: the plugin never registers the option_page_capability_mcr_settings_group filter, so only manage_options users can save the settings form' );

// ==========================================================================
mcr_group( '5. Admin action dispatch (Tools screen)' );
// ==========================================================================

$handled = mcr_handled_admin_actions();
$posted  = mcr_tools_posted_actions();

assertTrue( in_array( 'mcr_reset_user', $handled, true ), 'control: the Tools "Reset a User Balance" action is dispatched' );
assertTrue( in_array( 'mcr_uninstall', $handled, true ), 'control: the Tools "Clear Settings" action is dispatched' );

foreach ( $posted as $action ) {
	assertTrue(
		in_array( $action, $handled, true ),
		'DEFECT: the Tools screen posts the "' . $action . '" action but Admin::maybe_handle_actions() has no case for it, so the button silently does nothing'
	);
}

// Behavioural proof in a child process: a dispatched action produces an
// admin notice, an unhandled one does not.
$child_handled = @shell_exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' mcr-child-admin mcr_reset_user 2>&1' );

if ( is_string( $child_handled ) && false !== strpos( $child_handled, 'MCR_CHILD_NOTICE=yes' ) ) {
	assertTrue( true, 'control: a dispatched admin action (mcr_reset_user) produces an admin notice' );
} else {
	mcr_fail( 'control: a dispatched admin action (mcr_reset_user) produces an admin notice', 'child output: ' . trim( (string) $child_handled ) );
}

$child_unhandled = @shell_exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' mcr-child-admin mcr_reconcile_all 2>&1' );

if ( is_string( $child_unhandled ) && false !== strpos( $child_unhandled, 'MCR_CHILD_NOTICE=yes' ) ) {
	assertTrue( true, 'DEFECT: the mcr_reconcile_all action is silently swallowed - the dispatcher has no case for it and no feedback is shown to the administrator' );
} else {
	mcr_fail( 'DEFECT: the mcr_reconcile_all action is silently swallowed - the dispatcher has no case for it and no feedback is shown to the administrator', 'child output: ' . trim( (string) $child_unhandled ) );
}

// ==========================================================================
mcr_group( '6. REST API' );
// ==========================================================================

$rest = new \MCR\Rest();
$rest->register_routes();

assertTrue(
	isset( $GLOBALS['mcr_actions']['rest_api_init'] ),
	'control: the REST routes are registered on rest_api_init'
);

mcr_reset_options();
$GLOBALS['mcr_current_user'] = 0;

$denied = $rest->permission_logged_in();
assertTrue( is_wp_error( $denied ), 'control: permission_logged_in() rejects anonymous requests' );

if ( is_wp_error( $denied ) ) {
	assertSame( 'mcr_not_logged_in', $denied->get_error_code(), 'control: the anonymous error code is mcr_not_logged_in' );
	assertSame( 401, (int) $denied->get_error_data()['status'], 'control: the anonymous error carries HTTP status 401' );
}

$GLOBALS['mcr_current_user'] = 5;
assertTrue( true === $rest->permission_logged_in(), 'control: permission_logged_in() allows a logged-in user' );
$GLOBALS['mcr_current_user'] = 0;

// CONTROL: no mutating route in the plugin's own controller is public.
$rest_src = mcr_src( 'includes/Rest.php' );
preg_match_all( "/register_rest_route\((.*?)\n\t\t\);/s", $rest_src, $route_blocks );

$mutating_public = 0;

foreach ( $route_blocks[1] as $block ) {
	if ( preg_match( "/'methods'\s*=>\s*'(POST|PUT|PATCH|DELETE)'/", $block, $m ) ) {
		if ( preg_match( "/'permission_callback'\s*=>\s*'__return_true'/", $block ) ) {
			$mutating_public++;
		}
	}
}

assertSame( 0, $mutating_public, 'control: no mutating route in includes/Rest.php is registered with __return_true' );

// CONTROL: the response keys the JS client reads exist in the PHP responses.
$frontend_js = mcr_src( 'assets/js/mcr-frontend.js' );

assertTrue( false !== strpos( $frontend_js, 'payload.transactions' ), 'control: the frontend bundle reads payload.transactions' );
assertTrue( false !== strpos( $rest_src, "'transactions' => \$rows" ), 'control: the REST transactions response exposes a "transactions" key' );
assertTrue( false !== strpos( $rest_src, "'success' => true" ), 'control: the REST mutation responses expose a "success" key' );
assertTrue( false !== strpos( $frontend_js, 'response.data' ), 'control: the frontend bundle reads response.data' );
assertTrue( false !== strpos( $rest_src, "'data'    => \$result" ), 'control: the REST mutation responses expose a "data" key' );

// DEFECT: none of the routes declare an "args" schema, so no request
// parameter is validated or sanitised by the REST API itself.
preg_match_all( "/register_rest_route\(/", $rest_src, $route_calls );
$args_declarations = preg_match_all( "/'args'\s*=>\s*array\(/", $rest_src );

assertSame(
	count( $route_calls[0] ),
	$args_declarations,
	'DEFECT: none of the REST routes in includes/Rest.php declares an "args" schema, so no request parameter is validated or sanitised by register_rest_route()'
);

// ==========================================================================
mcr_group( '7. Cron' );
// ==========================================================================

$cron_src = mcr_src( 'includes/Cron.php' );

assertTrue( false !== strpos( $cron_src, "wp_schedule_event( time() + \\HOUR_IN_SECONDS, 'daily', self::HOOK_DAILY )" ), 'control: the daily event uses the core "daily" schedule' );
assertTrue( false !== strpos( $cron_src, "wp_schedule_event( time() + ( 30 * \\MINUTE_IN_SECONDS ), 'hourly', self::HOOK_HOURLY )" ), 'control: the hourly event uses the core "hourly" schedule' );
assertTrue( false !== strpos( mcr_src( 'includes/Installer.php' ), 'Cron::schedule_events();' ), 'control: activation schedules the cron events' );
assertTrue( false !== strpos( mcr_src( 'includes/Installer.php' ), 'Cron::unschedule_events();' ), 'control: deactivation unschedules the cron events' );

// CONTROL: the maintenance callbacks are idempotent - they only UPDATE.
assertFalse( false !== stripos( $cron_src, 'INSERT INTO' ), 'control: the cron callbacks never insert a row, so a duplicated or missed run cannot double-credit' );
assertTrue( false !== strpos( $cron_src, "SET status = 'expired' WHERE status = 'pending'" ), 'control: the hourly callback only expires pending rows, so re-running it is a no-op' );

// ==========================================================================
mcr_group( '8. Transients and caching' );
// ==========================================================================

$transient_sources = array(
	'includes/Admin/Admin.php',
	'includes/Admin/DashboardPage.php',
	'includes/Payments/Orders.php',
	'includes/Installer.php',
);

$bad_ttl = array();

foreach ( $transient_sources as $file ) {
	$src = mcr_src( $file );

	if ( preg_match_all( "/set_transient\(\s*'[^']+'\s*,\s*[^,]+,\s*([^)]+)\)/", $src, $m ) ) {
		foreach ( $m[1] as $ttl ) {
			if ( preg_match( '/^\s*0\s*$/', $ttl ) ) {
				$bad_ttl[] = $file . ': ' . $ttl;
			}
		}
	}
}

assertCount( 0, $bad_ttl, 'control: every set_transient() call passes a non-zero TTL' );

// DEFECT: the dashboard stats cache is never invalidated when a balance
// changes, so the Dashboard shows stale numbers for up to five minutes.
$invalidation_sources = array(
	'includes/Ledger.php',
	'includes/Rewards/CheckIn.php',
	'includes/Rewards/AdSessions.php',
	'includes/Gateways/Gateway.php',
);

$invalidated = false;

foreach ( $invalidation_sources as $file ) {
	if ( false !== strpos( mcr_src( $file ), 'mcr_dashboard_stats' ) ) {
		$invalidated = true;
	}
}

assertTrue( $invalidated, 'DEFECT: the mcr_dashboard_stats transient is never invalidated when a balance changes, so the admin Dashboard shows stale totals for up to 300 seconds' );

// DEFECT: uninstall.php deletes the dashboard/activation keys as OPTIONS
// although they are TRANSIENTS.
$uninstall_src = mcr_src( 'uninstall.php' );
assertTrue( false !== strpos( $uninstall_src, "delete_transient( 'mcr_dashboard_stats' )" ), 'DEFECT: uninstall.php deletes mcr_dashboard_stats with delete_option(), but it is stored as a transient by DashboardPage' );
assertTrue( false !== strpos( $uninstall_src, "delete_transient( 'mcr_activation_redirect' )" ), 'DEFECT: uninstall.php deletes mcr_activation_redirect with delete_option(), but Installer::activate() sets it with set_transient()' );

// ==========================================================================
mcr_group( '9. Internationalisation' );
// ==========================================================================

$plugin_header = mcr_src( 'madara-coin-rewards.php' );

assertTrue( false !== strpos( $plugin_header, 'Text Domain:       madara-coin-rewards' ), 'control: the plugin header declares the madara-coin-rewards text domain' );
assertTrue( false !== strpos( mcr_src( 'includes/Plugin.php' ), "'madara-coin-rewards',\n\t\t\tfalse," ), 'control: load_plugin_textdomain() uses the madara-coin-rewards domain' );

$pot = mcr_src( 'languages/madara-coin-rewards.pot' );
assertTrue( '' !== $pot, 'control: languages/madara-coin-rewards.pot exists' );
assertTrue( false !== strpos( $pot, 'X-Domain: madara-coin-rewards' ), 'control: the POT file declares the madara-coin-rewards text domain' );

// DEFECT: string concatenation inside a translation function produces a
// msgid that no translation can ever match.
$concatenated = array();

foreach ( array( 'includes/Admin/SettingsPage.php', 'includes/AdProviders/GenericS2S.php', 'includes/Gateways/Stripe.php' ) as $file ) {
	$src = mcr_src( $file );

	if ( preg_match_all( "/(?:__|_e|esc_html__|esc_attr__|esc_html_e|esc_attr_e)\(\s*'[^']*'\s*\.\s*/", $src, $m ) ) {
		foreach ( $m[0] as $hit ) {
			$concatenated[] = $file;
		}
	}
}

assertCount(
	0,
	$concatenated,
	'DEFECT: ' . count( $concatenated ) . ' translation calls concatenate a string inside __(), producing msgids that can never be translated (' . implode( ', ', array_unique( $concatenated ) ) . ')'
);

// ==========================================================================
mcr_group( '10. Capabilities' );
// ==========================================================================

$caps = \MCR\Capabilities::all();

assertCount( 3, $caps, 'control: the plugin defines three custom capabilities' );
assertTrue( in_array( 'mcr_manage_settings', $caps, true ), 'control: mcr_manage_settings is one of the plugin capabilities' );
assertTrue( in_array( 'mcr_manage_coins', $caps, true ), 'control: mcr_manage_coins is one of the plugin capabilities' );
assertTrue( in_array( 'mcr_view_users', $caps, true ), 'control: mcr_view_users is one of the plugin capabilities' );

assertTrue( false !== strpos( mcr_src( 'includes/Installer.php' ), 'Capabilities::add_to_roles();' ), 'control: activation grants the custom capabilities' );
assertTrue( false !== strpos( $uninstall_src, "'mcr_manage_settings', 'mcr_manage_coins', 'mcr_view_users'" ), 'control: uninstall.php revokes the custom capabilities' );

mcr_reset_options();
$GLOBALS['mcr_caps']['manage_options'] = true;

assertTrue( \MCR\Capabilities::can_manage_settings(), 'control: can_manage_settings() accepts manage_options' );
assertTrue( \MCR\Capabilities::can_manage_coins(), 'control: can_manage_coins() accepts manage_options' );
assertTrue( \MCR\Capabilities::can_view_users(), 'control: can_view_users() accepts manage_options' );

// ==========================================================================
mcr_group( '11. Madara theme integration' );
// ==========================================================================

assertFalse( class_exists( '\App\Madara' ), 'control: the Madara theme class is not loaded in this test' );
assertSame( '', \MCR\Integrations\Madara::accent(), 'control: Madara::accent() returns an empty string when the theme is absent' );
assertSame( '', \MCR\Integrations\Madara::accent_secondary(), 'control: Madara::accent_secondary() returns an empty string when the theme is absent' );
assertSame( '', \MCR\Integrations\Madara::color_scheme(), 'control: Madara::color_scheme() returns an empty string when the theme is absent' );

ob_start();
do_action( 'wp_head' );
$head = ob_get_clean();

assertTrue( is_string( $head ), 'control: the wp_head theme bridge does not fatal when the Madara theme is absent' );

// Every \App\Madara:: call must be guarded by a class_exists() check.
$madara_src   = mcr_src( 'includes/Integrations/Madara.php' );
$madara_calls = preg_match_all( '/\\\\App\\\\Madara::/', $madara_src );
$madara_guards = preg_match_all( "/class_exists\( '\\\\App\\\\Madara' \)/", $madara_src );

assertSame( $madara_calls, $madara_guards, 'control: every call into the Madara theme class is guarded by class_exists()' );

// ==========================================================================
mcr_group( '12. Asset pipeline' );
// ==========================================================================

$assets_src = mcr_src( 'includes/Templates/Assets.php' );

assertTrue( false !== strpos( $assets_src, "MCR_PLUGIN_URL . 'assets/css/mcr-design-system.css'" ), 'control: the design-system stylesheet is enqueued from the plugin URL' );
assertTrue( false !== strpos( $assets_src, "MCR_PLUGIN_URL . 'assets/js/mcr-frontend.js'" ), 'control: the frontend bundle is enqueued from the plugin URL' );
assertTrue( false !== strpos( $assets_src, 'MCR_VERSION' ), 'control: the assets are versioned with MCR_VERSION' );
assertTrue( false !== strpos( $assets_src, "wp_localize_script( self::SCRIPT, 'mcrData', \$data )" ), 'control: the frontend bundle receives its data through wp_localize_script()' );
assertTrue( false !== strpos( $assets_src, "unset( \$urls['settings'] )" ), 'control: the admin URL is removed from the localized data before it is sent to the browser' );

// CONTROL: the assets are enqueued from the wp_enqueue_scripts hook when
// the page can be detected in advance.
assertTrue(
	false !== strpos( $assets_src, "add_action( 'wp_enqueue_scripts', array( \$this, 'maybe_enqueue' ), 20 )" ),
	'control: the assets are enqueued from the wp_enqueue_scripts hook when the page can be detected in advance'
);

// DEFECT: Assets::need() runs while the_content is rendered, i.e. AFTER
// wp_head has already printed the styles. Enqueuing a stylesheet at that
// point is too late - WordPress never prints it, so the page renders
// unstyled whenever the plugin page was not detected in advance (a
// shortcode inside a widget, a page builder, a reusable block, ...).
$need_body    = mcr_method_body( 'includes/Templates/Assets.php', '\MCR\Templates\Assets', 'need' );
$enqueue_body = mcr_method_body( 'includes/Templates/Assets.php', '\MCR\Templates\Assets', 'enqueue' );

$style_reachable_from_render_path = ( false !== strpos( $need_body, '$this->enqueue()' ) && false !== strpos( $enqueue_body, 'wp_enqueue_style(' ) );

assertFalse(
	$style_reachable_from_render_path,
	'DEFECT: Assets::need() reaches wp_enqueue_style() from the shortcode/block render path, which runs after wp_head, so the CSS is never printed when the plugin page was not detected in advance'
);

// ==========================================================================
mcr_group( '13. Database schema (dbDelta compatibility)' );
// ==========================================================================

mcr_reset_options();
$GLOBALS['wpdb'] = new MCR_Review_WPDB();
$GLOBALS['mcr_dbdelta'] = array();

\MCR\Installer::install_schema();

$statements = isset( $GLOBALS['mcr_dbdelta'] ) ? $GLOBALS['mcr_dbdelta'] : array();

assertCount( 6, $statements, 'control: dbDelta() is called once per plugin table' );

$schema_problems = array();

foreach ( $statements as $sql ) {
	// Two spaces after PRIMARY KEY.
	if ( ! preg_match( '/^\s*PRIMARY KEY {2}\(/m', $sql ) ) {
		$schema_problems[] = 'PRIMARY KEY needs two spaces';
	}

	// One space after KEY / UNIQUE KEY.
	if ( preg_match( '/^\s*UNIQUE KEY {2,}/m', $sql ) ) {
		$schema_problems[] = 'UNIQUE KEY needs one space';
	}

	if ( preg_match( '/^\s*KEY {2,}/m', $sql ) ) {
		$schema_problems[] = 'KEY needs one space';
	}

	// One column definition per line (dbDelta splits the body on newlines).
	if ( preg_match( '|\((.*)\)|s', $sql, $body ) ) {
		foreach ( explode( "\n", $body[1] ) as $line ) {
			if ( mcr_top_level_commas( $line ) > 1 ) {
				$schema_problems[] = 'two column definitions share a line';
				break;
			}
		}
	}

	// The table must end with the charset/collate and the engine.
	if ( ! preg_match( '/\)\s+.+ENGINE=InnoDB;\s*$/', $sql ) ) {
		$schema_problems[] = 'the table is not closed with the charset/collate and ENGINE=InnoDB';
	}
}

assertCount( 0, $schema_problems, 'control: the dbDelta schema strings follow dbDelta\'s spacing, one-column-per-line and table-terminator rules (' . implode( '; ', array_unique( $schema_problems ) ) . ')' );

// DEFECT: a zero date is written into a DATETIME column. MySQL 5.7 and 8.0
// run with NO_ZERO_DATE + STRICT_TRANS_TABLES by default and reject it.
$gateway_src = mcr_src( 'includes/Gateways/Gateway.php' );
assertTrue(
	false === strpos( $gateway_src, "'0000-00-00 00:00:00'" ),
	'DEFECT: Gateway::create_order_row() inserts the zero date 0000-00-00 into mcr_payments.paid_at, which MySQL 5.7/8.0 rejects in strict mode (error 1292), so every purchase order fails to be created'
);

$installer_src = mcr_src( 'includes/Installer.php' );
assertTrue(
	false !== strpos( $installer_src, "paid_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00'" ),
	'control: the schema itself uses 1970-01-01 as the zero-date replacement for paid_at, so only the INSERT in Gateway::create_order_row() is wrong'
);

// DEFECT: integer display widths. MySQL 8.0.19+ dropped them, so DESCRIBE
// reports "int unsigned" where the schema says "int(10) unsigned" and
// dbDelta issues a pointless ALTER TABLE on every schema check.
$display_widths = array();

foreach ( $statements as $sql ) {
	if ( preg_match_all( '/\b(?:bigint|int|tinyint|smallint)\(\d+\)/i', $sql, $m ) ) {
		foreach ( $m[0] as $hit ) {
			$display_widths[] = $hit;
		}
	}
}

assertCount(
	0,
	$display_widths,
	'DEFECT: the schema uses integer display widths (' . implode( ', ', array_unique( $display_widths ) ) . '); MySQL 8.0.19+ drops them, so dbDelta ALTERs the tables on every schema check'
);

// ==========================================================================
mcr_group( '14. CSV export (static method using $this)' );
// ==========================================================================

$export_body = mcr_method_body( 'includes/Admin/TransactionsPage.php', '\MCR\Admin\TransactionsPage', 'export_via_admin_post' );

assertTrue( ( new ReflectionMethod( '\MCR\Admin\TransactionsPage', 'export_via_admin_post' ) )->isStatic(), 'control: export_via_admin_post() is a static method' );
assertTrue(
	false === strpos( $export_body, '$this->' ),
	'DEFECT: the static method TransactionsPage::export_via_admin_post() uses $this->escape_csv_cell(), which is a fatal "Using $this when not in object context" error on PHP 7/8'
);

$child = @shell_exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' mcr-child-export 2>&1' );

if ( is_string( $child ) && false === strpos( $child, 'not in object context' ) ) {
	assertTrue( true, 'control: the CSV export runs without a fatal "Using $this when not in object context" error' );
} else {
	mcr_fail( 'DEFECT: calling the CSV export at runtime raises "Using $this when not in object context", so exporting the transaction CSV returns a 500 as soon as there is one transaction row', 'child output: ' . trim( (string) $child ) );
}

// DEFECT: "self::method" used as a string callable is deprecated since
// PHP 8.2 and emits a deprecation notice on every call.
$self_callables = array();

foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( dirname( __DIR__ ) . '/includes' ) ) as $file ) {
	if ( 'php' !== strtolower( $file->getExtension() ) ) {
		continue;
	}

	if ( preg_match_all( "/'self::[a-z_]+'/i", (string) file_get_contents( $file->getPathname() ), $m ) ) {
		foreach ( $m[0] as $hit ) {
			$self_callables[] = str_replace( dirname( __DIR__ ) . '/', '', $file->getPathname() ) . ': ' . $hit;
		}
	}
}

assertCount(
	0,
	$self_callables,
	'DEFECT: ' . count( $self_callables ) . ' string callable(s) use "self::method", which is deprecated since PHP 8.2 and emits a deprecation notice on every call (' . implode( ', ', $self_callables ) . ')'
);

/* ==========================================================================
 * Summary
 * ======================================================================== */

$passed = (int) $GLOBALS['mcr_tests']['passed'];
$failed = (int) $GLOBALS['mcr_tests']['failed'];
$total  = $passed + $failed;

echo "\n";
echo 'Assertions: ' . $total . ' | Passed: ' . $passed . ' | Failed: ' . $failed . "\n";

if ( $failed > 0 ) {
	echo "\nFailures:\n";

	foreach ( $GLOBALS['mcr_tests']['failures'] as $failure ) {
		echo '  - [' . $failure['group'] . '] ' . $failure['message'] . "\n";

		if ( '' !== $failure['detail'] ) {
			echo '      ' . $failure['detail'] . "\n";
		}
	}

	exit( 1 );
}

exit( 0 );
