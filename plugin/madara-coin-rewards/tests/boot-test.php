<?php
/**
 * Boot smoke test.
 *
 * Run with: php tests/boot-test.php
 *
 * Loads the plugin the way WordPress would - the main file, the
 * plugins_loaded bootstrap, the activation hook - against a stubbed
 * WordPress, and asserts that the whole thing comes up without a fatal
 * error and that the public API works.
 *
 * This catches the class of bug a syntax check cannot: a missing import,
 * a method that does not exist, a hook that points at nothing, a class
 * registered under the wrong name.
 *
 * @package MCR
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/wp-stubs.php';

echo "MCR Boot Smoke Test\n";
echo "===================\n\n";

mcr_group( 'Plugin bootstrap' );

$thrown = null;

try {
	/*
	 * Load the plugin exactly as WordPress does: the main file defines the
	 * constants and registers the bootstrap on plugins_loaded. The hook
	 * does not fire in this harness, so the bootstrap is called directly.
	 */
	require_once dirname( __DIR__ ) . '/madara-coin-rewards.php';

	assertTrue( function_exists( 'mcr_bootstrap' ), 'the plugin registers its bootstrap' );
	assertTrue( defined( 'MCR_PLUGIN_DIR' ), 'the plugin defines its constants' );

	mcr_bootstrap();

	assertTrue( class_exists( 'MCR\Plugin' ), 'the plugin class is autoloadable' );
	assertTrue( true, 'boot() completed without a fatal error' );
} catch ( \Throwable $e ) {
	$thrown = $e;
	mcr_fail( 'boot() completed without a fatal error', get_class( $e ) . ': ' . $e->getMessage() );
}

if ( null === $thrown ) {
	mcr_group( 'Public API' );

	// WordPress always provides the global $wpdb.
	$GLOBALS['wpdb'] = new MCR_Fake_WPDB();

	assertSame( 0, mcr_get_balance( 1 ), 'mcr_get_balance() returns a balance' );
	assertTrue( is_array( mcr_get_stats( 1 ) ), 'mcr_get_stats() returns an array' );
	assertTrue( is_array( mcr_get_transaction_types() ), 'mcr_get_transaction_types() returns the allowlist' );
	assertTrue( mcr_is_enabled( 'checkin' ), 'a feature toggle can be read' );
	assertTrue( is_array( mcr_get_checkin_status( 1 ) ), 'mcr_get_checkin_status() returns an array' );

	mcr_group( 'Registered hooks' );

	assertTrue( isset( $GLOBALS['mcr_actions']['widgets_init'] ), 'the widget hook is registered' );
	assertTrue( isset( $GLOBALS['mcr_actions']['rest_api_init'] ), 'the REST hook is registered' );
	assertTrue( isset( $GLOBALS['mcr_actions']['init'] ), 'the textdomain hook is registered' );
	assertTrue( isset( $GLOBALS['mcr_actions']['show_user_profile'] ), 'the profile panel hook is registered' );

	mcr_group( 'Widget registration' );

	// The widget class name in register_widget() must match the class.
	$plugin_source = file_get_contents( dirname( __DIR__ ) . '/includes/Plugin.php' );

	preg_match( "/register_widget\(\s*'([^']+)'/", $plugin_source, $widget_match );
	$registered = isset( $widget_match[1] ) ? ltrim( $widget_match[1], '\\' ) : '';

	assertTrue( '' !== $registered, 'a widget class is registered' );
	assertTrue( class_exists( $registered ), 'the registered widget class exists (' . $registered . ')' );

	mcr_group( 'Activation' );

	if ( isset( $GLOBALS['mcr_activation'] ) ) {
		try {
			call_user_func( $GLOBALS['mcr_activation'] );
			mcr_pass( 'the activation hook runs without a fatal error' );
		} catch ( \Throwable $e ) {
			mcr_fail( 'the activation hook runs without a fatal error', get_class( $e ) . ': ' . $e->getMessage() );
		}
	} else {
		mcr_fail( 'the activation hook is registered', 'no activation callback was captured' );
	}
}

echo "\n===================\n";

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
