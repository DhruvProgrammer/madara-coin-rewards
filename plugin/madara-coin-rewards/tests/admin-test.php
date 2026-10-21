<?php
/**
 * Admin screen smoke test.
 *
 * Run with: php tests/admin-test.php
 *
 * Renders every admin screen the plugin registers, the way WordPress does,
 * and asserts that none of them fatals. The admin screens are the largest
 * single surface in the plugin and the one a user reaches first, so a
 * missing method or a bad call is a fatal error on the very first click.
 *
 * @package MCR
 */

if ( ! defined( 'ABSPATH' ) ) {
	/*
	 * A miniature WordPress root so that the plugin's
	 * `require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php'`
	 * resolves. The class itself is provided by the stubs.
	 */
	$mcr_wp_root = sys_get_temp_dir() . '/mcr-wp-harness';

	if ( ! is_dir( $mcr_wp_root . '/wp-admin/includes' ) ) {
		wp_mkdir( $mcr_wp_root . '/wp-admin/includes' );
	}

	$list_table = $mcr_wp_root . '/wp-admin/includes/class-wp-list-table.php';

	if ( ! file_exists( $list_table ) ) {
		file_put_contents( $list_table, "<?php\n// The WP_List_Table class is provided by tests/wp-stubs.php.\n" );
	}

	define( 'ABSPATH', $mcr_wp_root . '/' );
}

/**
 * Creates a directory.
 *
 * @param string $path Path.
 * @return void
 */
function wp_mkdir( $path ) {
	if ( ! is_dir( $path ) ) {
		mkdir( $path, 0777, true );
	}
}

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/wp-stubs.php';

// The admin screens only run for an administrator.
$GLOBALS['mcr_is_admin'] = true;
$GLOBALS['mcr_caps'] = array(
	'mcr_manage_settings' => true,
	'mcr_manage_coins'    => true,
	'mcr_view_users'       => true,
	'manage_options'      => true,
	'edit_posts'          => true,
	'manage_woocommerce'  => true,
);

$GLOBALS['wpdb'] = new MCR_Fake_WPDB();

/**
 * Admin-only stubs.
 */

/**
 * Whether the request is an admin request.
 *
 * @return bool
 */

/**
 * Admin menu registration.
 *
 * @return string
 */

/**
 * Submenu registration.
 *
 * @return string
 */

/**
 * Submit button.
 *
 * @return void
 */
function submit_button( $text = '', $type = 'primary', $name = 'submit' ) {
	echo '<button type="submit" name="' . esc_attr( $name ) . '">' . esc_html( $text ) . '</button>';
}

/**
 * Nonce field.
 *
 * @return void
 */
function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $echo = true ) {
	$field = '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( wp_create_nonce( $action ) ) . '" />';

	if ( $echo ) {
		echo $field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	return $field;
}

/**
 * Referer check.
 *
 * @param string $action Action.
 * @return bool
 */
function check_admin_referer( $action = -1, $query_arg = '_wpnonce' ) {
	return true;
}

/**
 * Screen.
 *
 * @return object|null
 */

/**
 * Admin URL.
 *
 * @param string $path Path.
 * @return string
 */

/**
 * Referer.
 *
 * @return string
 */
function wp_get_referer() {
	return 'https://example.test/wp-admin/plugins.php';
}

/**
 * Safe redirect.
 *
 * @param string $location Location.
 * @param int    $status   Status.
 * @return bool
 */

/**
 * Die.
 *
 * @param string $message Message.
 * @return void
 */

/**
 * Page title.
 *
 * @return string
 */
function get_admin_page_title() {
	return 'Madara Coins';
}

/**
 * Settings errors.
 *
 * @return void
 */
function settings_errors() {}

/**
 * User list.
 *
 * @param array $args Args.
 * @return array
 */
function get_users( $args = array() ) {
	return array();
}

/**
 * User count.
 *
 * @return int
 */
function count_users() {
	return array( 'total_users' => 0 );
}

/**
 * Transients.
 *
 * @param string $key Key.
 * @return mixed
 */
function get_site_transient( $key ) {
	return get_transient( $key );
}

/**
 * Delete a transient.
 *
 * @param string $key Key.
 * @return bool
 */
function delete_site_transient( $key ) {
	return delete_transient( $key );
}

/**
 * Plugin action links.
 *
 * @param array $links Links.
 * @return array
 */
function plugin_action_links( $links ) {
	return $links;
}

echo "MCR Admin Smoke Test\n";
echo "=====================\n\n";

mcr_group( 'Admin registration' );

$thrown = null;

try {
	require_once dirname( __DIR__ ) . '/includes/Autoloader.php';
	MCR\Autoloader::register( dirname( __DIR__ ) . '/' );

	require_once dirname( __DIR__ ) . '/madara-coin-rewards.php';

	// WordPress includes the plugin file, then fires plugins_loaded.
	mcr_bootstrap();

	assertTrue( true, 'the plugin booted in an admin request' );
} catch ( \Throwable $e ) {
	$thrown = $e;
	mcr_fail( 'the plugin booted in an admin request', get_class( $e ) . ': ' . $e->getMessage() );
}

if ( null === $thrown ) {
	mcr_group( 'Admin menu' );

	$GLOBALS['mcr_menu_pages'] = array();

	try {
		do_action( 'admin_menu' );

		assertTrue( count( $GLOBALS['mcr_menu_pages'] ) > 1, 'the admin menu registered its pages (' . count( $GLOBALS['mcr_menu_pages'] ) . ')' );
	} catch ( \Throwable $e ) {
		mcr_fail( 'the admin menu registered without a fatal error', get_class( $e ) . ': ' . $e->getMessage() );
	}

	mcr_group( 'Admin screens render' );

	/*
	 * Each registered page points at a render callback. Rendering every one
	 * of them is what a user does when they click through the menu, and it
	 * is where a missing method or a bad call shows up.
	 */
	$rendered = 0;

	foreach ( (array) ( $GLOBALS['mcr_menu_pages'] ?? array() ) as $page ) {
		/*
		 * add_menu_page() takes the callback at index 4 and
		 * add_submenu_page() at index 5, so the callback is located by
		 * looking for the first callable argument.
		 */
		$callback = null;

		foreach ( (array) $page as $argument ) {
			if ( is_callable( $argument ) ) {
				$callback = $argument;
				break;
			}
		}

		if ( null === $callback ) {
			continue;
		}

		// The menu slug, so a failure names the screen.
		// add_menu_page() puts it at index 3, add_submenu_page() at index 4.
		if ( count( $page ) >= 7 && isset( $page[3] ) ) {
			$slug = (string) $page[3];
		} elseif ( isset( $page[4] ) && is_string( $page[4] ) ) {
			$slug = (string) $page[4];
		} else {
			$slug = 'unknown';
		}

		try {
			ob_start();
			call_user_func( $callback );
			$html = ob_get_clean();

			++$rendered;

			assertTrue(
				is_string( $html ),
				'the ' . $slug . ' screen rendered output'
			);
		} catch ( \Throwable $e ) {
			if ( ob_get_level() > 0 ) {
				ob_end_clean();
			}

			mcr_fail(
				'the ' . $slug . ' screen rendered without a fatal error',
				get_class( $e ) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine()
			);
		}
	}

	assertTrue( $rendered >= 5, 'at least five admin screens rendered (' . $rendered . ')' );
}

mcr_group( 'The admin screens are not hidden by the stylesheet' );

/*
 * The stylesheet used to end with a rule that hid every table, card and
 * filter on the plugin's own screens whenever the <body> did not carry
 * the "mcr-admin" class. The class is added by the admin_body_class
 * filter, and while that filter was missing the dashboard showed its
 * headings and nothing else, and the packages screen could not be
 * edited at all.
 */
$admin_css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/mcr-admin.css' );

assertTrue(
	false === strpos( $admin_css, 'body:not(.mcr-admin)' ),
	'the admin stylesheet does not hide the plugin\'s own elements'
);

assertTrue(
	isset( $GLOBALS['mcr_filters']['admin_body_class'] ),
	'the admin_body_class filter is hooked'
);

$GLOBALS['mcr_screen_id'] = 'mcr-rewards_page_mcr-dashboard';

assertTrue(
	MCR\Admin\Admin::is_plugin_screen(),
	'the plugin recognises its own dashboard screen'
);

$admin_object = new MCR\Admin\Admin();

assertTrue(
	false !== strpos( (string) $admin_object->admin_body_class( 'wp-admin' ), 'mcr-admin' ),
	'the <body> carries the mcr-admin class on the plugin\'s screens'
);

$GLOBALS['mcr_screen_id'] = 'edit-post';

assertFalse(
	MCR\Admin\Admin::is_plugin_screen(),
	'the plugin does not claim a screen that is not its own'
);

$GLOBALS['mcr_screen_id'] = '';

echo "\n=====================\n";

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
