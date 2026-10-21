<?php
/**
 * Frontend render smoke test.
 *
 * Run with: php tests/frontend-test.php
 *
 * Renders every shortcode, the way a page would, and asserts that the
 * markup is produced without a fatal error. The templates are the largest
 * single surface in the plugin and the first thing a visitor sees.
 *
 * @package MCR
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/wp-stubs.php';

$GLOBALS['wpdb'] = new MCR_Fake_WPDB();
$GLOBALS['mcr_current_user'] = 5;
$GLOBALS['mcr_caps'] = array(
	'mcr_manage_settings' => true,
	'mcr_manage_coins'    => true,
	'mcr_view_users'       => true,
	'manage_options'      => true,
	'edit_posts'          => true,
);

/**
 * Whether a shortcode is present in the content.
 *
 * @param string $content   Content.
 * @param string $shortcode Shortcode.
 * @return bool
 */

/**
 * Whether a block is present.
 *
 * @param string $name Block name.
 * @param mixed  $post Post.
 * @return bool
 */

/**
 * The site timezone.
 *
 * @return string
 */

/**
 * The permalink.
 *
 * @param int $id Post id.
 * @return string
 */

/**
 * The home URL.
 *
 * @param string $path Path.
 * @return string
 */

/**
 * The admin URL.
 *
 * @param string $path Path.
 * @return string
 */

/**
 * The login URL.
 *
 * @param string $redirect Redirect.
 * @return string
 */

/**
 * The blog name.
 *
 * @param string $show Field.
 * @return string
 */

/**
 * The date.
 *
 * @param string $format Format.
 * @param mixed  $date   Date.
 * @return string
 */

/**
 * The site language.
 *
 * @return string
 */

/**
 * The date format.
 *
 * @param string $format Format.
 * @return string
 */
function get_option_format( $format = 'date_format' ) {
	return 'Y-m-d';
}

/**
 * Whether a sidebar is active.
 *
 * @param string $id Sidebar id.
 * @return bool
 */
function is_active_sidebar( $id ) {
	return false;
}

/**
 * The current URL.
 *
 * @return string
 */
function home_url_current() {
	return 'https://example.test/';
}

echo "MCR Frontend Smoke Test\n";
echo "=======================\n\n";

mcr_group( 'Frontend registration' );

$thrown = null;

try {
	require_once dirname( __DIR__ ) . '/includes/Autoloader.php';
	MCR\Autoloader::register( dirname( __DIR__ ) . '/' );

	require_once dirname( __DIR__ ) . '/madara-coin-rewards.php';

	mcr_bootstrap();

	assertTrue( true, 'the plugin booted in a frontend request' );
} catch ( \Throwable $e ) {
	$thrown = $e;
	mcr_fail( 'the plugin booted in a frontend request', get_class( $e ) . ': ' . $e->getMessage() );
}

if ( null === $thrown ) {
	mcr_group( 'Shortcodes render' );

	$shortcodes = array(
		'mcr_checkin',
		'mcr_dashboard',
		'mcr_ads',
		'mcr_buy_coins',
		'mcr_transactions',
		'mcr_balance',
	);

	foreach ( $shortcodes as $shortcode ) {
		try {
			// A shortcode may return its markup or echo it; capture both.
			ob_start();
			$returned = do_shortcode( '[' . $shortcode . ']' );
			$echoed   = ob_get_clean();

			$html = trim( (string) $returned . (string) $echoed );

			assertTrue( '' !== $html, 'the ' . $shortcode . ' shortcode produced markup' );
			assertTrue(
				false !== strpos( $html, 'mcr-scope' ),
				'the ' . $shortcode . ' shortcode is wrapped in the plugin scope'
			);
		} catch ( \Throwable $e ) {
			if ( ob_get_level() > 0 ) {
				ob_end_clean();
			}

			mcr_fail(
				'the ' . $shortcode . ' shortcode rendered without a fatal error',
				get_class( $e ) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine()
			);
		}
	}
}

echo "\n=======================\n";

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
