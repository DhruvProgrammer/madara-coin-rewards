<?php
/**
 * Activation smoke test.
 *
 * Run with: php tests/activation-test.php
 *
 * Reproduces the EXACT order WordPress uses when a plugin is activated from
 * the plugins screen:
 *
 *   1. wp-settings.php dispatches `plugins_loaded`  (BEFORE our file exists)
 *   2. wp-admin/includes/plugin.php includes the plugin file
 *   3. ... then fires `activate_{plugin_basename}`
 *
 * This is the order that matters, and it is NOT the order a normal request
 * uses. A plugin whose activation hook needs a class that is only autoloaded
 * on `plugins_loaded` fatals here, because that hook has already been
 * dispatched by the time the plugin file is included.
 *
 * The test asserts the activation completes, creates the tables, and does
 * not fatal.
 *
 * @package MCR
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/wp-stubs.php';

echo "MCR Activation Smoke Test\n";
echo "=========================\n\n";

mcr_group( 'WordPress activation order' );

/*
 * Step 1: `plugins_loaded` has ALREADY been dispatched. Nothing in this
 * request will run a plugins_loaded callback that was registered later.
 */
$GLOBALS['mcr_plugins_loaded_dispatched'] = true;

// WordPress always provides the global $wpdb.
$GLOBALS['wpdb'] = new MCR_Fake_WPDB();

$thrown = null;

try {
	/*
	 * Step 2: WordPress includes the plugin file. This is the first time
	 * the file is seen in this request.
	 */
	require_once dirname( __DIR__ ) . '/madara-coin-rewards.php';

	assertTrue( defined( 'MCR_PLUGIN_DIR' ), 'the plugin file defined its constants' );
	assertTrue( function_exists( 'mcr_bootstrap' ), 'the plugin registered its bootstrap' );

	/*
	 * Step 3: WordPress fires the activation hook. The callback is the one
	 * that was passed to register_activation_hook(), and WordPress calls it
	 * with call_user_func() - so a class that cannot be resolved is a FATAL,
	 * not a graceful failure.
	 */
	$callback = isset( $GLOBALS['mcr_activation'] ) ? $GLOBALS['mcr_activation'] : null;

	assertTrue( is_string( $callback ) || is_array( $callback ), 'an activation callback was registered' );
	assertTrue(
		is_callable( $callback ),
		'the activation callback is callable (its class must be autoloadable at this point)'
	);

	if ( is_string( $callback ) || is_array( $callback ) ) {
		// Exactly what WordPress does.
		call_user_func( $callback );
	}

	assertTrue( true, 'the activation hook completed without a fatal error' );
} catch ( \Throwable $e ) {
	$thrown = $e;
	mcr_fail(
		'the activation hook completed without a fatal error',
		get_class( $e ) . ': ' . $e->getMessage()
	);
}

if ( null === $thrown ) {
	mcr_group( 'Activation side effects' );

	global $wpdb;

	$created = false;

	foreach ( (array) ( $GLOBALS['mcr_dbdelta'] ?? array() ) as $query ) {
		if ( false !== stripos( $query, 'CREATE TABLE' ) && false !== stripos( $query, 'mcr_' ) ) {
			$created = true;
			break;
		}
	}

	assertTrue( $created, 'the activation created the plugin tables' );
	assertTrue( false !== get_option( 'mcr_settings', false ), 'the default settings were stored' );
	assertTrue( false !== get_option( 'mcr_db_version', false ), 'the schema version was stored' );
}

mcr_group( 'Deactivation' );

try {
	$callback = isset( $GLOBALS['mcr_deactivation'] ) ? $GLOBALS['mcr_deactivation'] : null;

	assertTrue( is_string( $callback ) || is_array( $callback ), 'a deactivation callback was registered' );
	assertTrue(
		is_callable( $callback ),
		'the deactivation callback is callable (its class must be autoloadable at this point)'
	);

	if ( is_string( $callback ) || is_array( $callback ) ) {
		call_user_func( $callback );
	}

	assertTrue( true, 'the deactivation hook completed without a fatal error' );
} catch ( \Throwable $e ) {
	mcr_fail(
		'the deactivation hook completed without a fatal error',
		get_class( $e ) . ': ' . $e->getMessage()
	);
}

echo "\n=========================\n";

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
