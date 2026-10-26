<?php
/**
 * Shared test bootstrap: the assertion framework and the WordPress stubs.
 *
 * Both run-tests.php and run-ledger-tests.php include this file so the two
 * suites share one definition of WordPress behaviour.
 *
 * @package MCR
 */

// The plugin files guard on ABSPATH.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

// WordPress defines these in wp-includes/load.php. The plugin references
// them fully qualified, so the harness must define them too.
if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

if ( ! defined( 'ARRAY_N' ) ) {
	define( 'ARRAY_N', 'ARRAY_N' );
}

if ( ! defined( 'OBJECT' ) ) {
	define( 'OBJECT', 'OBJECT' );
}

if ( ! defined( 'OBJECT_K' ) ) {
	define( 'OBJECT_K', 'OBJECT_K' );
}

if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

if ( ! defined( 'WEEK_IN_SECONDS' ) ) {
	define( 'WEEK_IN_SECONDS', 604800 );
}

if ( ! defined( 'MCR_TEST_MODE' ) ) {
	define( 'MCR_TEST_MODE', 'REAL_PLUGIN_SOURCE' );
}

if ( ! defined( 'SODIUM_CRYPTO_SECRETBOX_NONCEBYTES' ) ) {
	define( 'SODIUM_CRYPTO_SECRETBOX_NONCEBYTES', 24 );
}

if ( ! function_exists( 'sodium_crypto_secretbox' ) ) {
	function sodium_crypto_secretbox( $plain, $nonce, $key ) {
		$pad   = '';
		$block = 0;
		while ( strlen( $pad ) < strlen( $plain ) ) {
			$pad .= hash( 'sha256', $key . $nonce . ( $block++ ), true );
		}
		return $plain ^ substr( $pad, 0, strlen( $plain ) );
	}
}

if ( ! function_exists( 'sodium_crypto_secretbox_open' ) ) {
	function sodium_crypto_secretbox_open( $cipher, $nonce, $key ) {
		return sodium_crypto_secretbox( $cipher, $nonce, $key );
	}
}

if ( ! function_exists( 'mb_strlen' ) ) {
	function mb_strlen( $string, $encoding = null ) {
		if ( function_exists( 'iconv_strlen' ) ) {
			$res = @iconv_strlen( (string) $string, $encoding ?: 'UTF-8' );
			if ( false !== $res ) {
				return $res;
			}
		}
		return strlen( (string) $string );
	}
}

if ( ! function_exists( 'mb_substr' ) ) {
	function mb_substr( $string, $start, $length = null, $encoding = null ) {
		if ( function_exists( 'iconv_substr' ) ) {
			$res = @iconv_substr( (string) $string, $start, $length ?? 2147483647, $encoding ?: 'UTF-8' );
			if ( false !== $res ) {
				return $res;
			}
		}
		return null === $length ? substr( (string) $string, $start ) : substr( (string) $string, $start, $length );
	}
}

if ( ! function_exists( 'mb_strtoupper' ) ) {
	function mb_strtoupper( $string, $encoding = null ) {
		return strtoupper( (string) $string );
	}
}

/* ==========================================================================
 * 1. Tiny assertion framework
 * ======================================================================== */

$GLOBALS['mcr_tests'] = array(
	'passed'  => 0,
	'failed'  => 0,
	'group'   => '',
	'failures' => array(),
);

/**
 * Starts a new test group.
 *
 * @param string $name Group name.
 * @return void
 */
function mcr_group( $name ) {
	$GLOBALS['mcr_tests']['group'] = $name;
	echo "\n  " . $name . "\n";

	if ( class_exists( '\MCR\Templates\Templates' ) ) {
		\MCR\Templates\Templates::reset_render_count();
	}
}

/**
 * Records a passing assertion.
 *
 * @param string $message Description.
 * @return void
 */
function mcr_pass( $message ) {
	++$GLOBALS['mcr_tests']['passed'];
	echo "    [PASS] " . $message . "\n";
}

/**
 * Records a failing assertion.
 *
 * @param string $message Description.
 * @param string $detail  Extra detail.
 * @return void
 */
function mcr_fail( $message, $detail = '' ) {
	++$GLOBALS['mcr_tests']['failed'];

	$GLOBALS['mcr_tests']['failures'][] = array(
		'group'   => $GLOBALS['mcr_tests']['group'],
		'message' => $message,
		'detail'  => $detail,
	);

	echo "    [FAIL] " . $message . "\n";

	if ( '' !== $detail ) {
		echo "           " . $detail . "\n";
	}
}

/**
 * Asserts strict equality.
 *
 * @param mixed  $expected Expected value.
 * @param mixed  $actual   Actual value.
 * @param string $message  Description.
 * @return void
 */
function assertSame( $expected, $actual, $message ) {
	if ( $expected === $actual ) {
		mcr_pass( $message );

		return;
	}

	mcr_fail(
		$message,
		'expected ' . mcr_export( $expected ) . ', got ' . mcr_export( $actual )
	);
}

/**
 * Asserts loose numeric/string equality.
 *
 * @param mixed  $expected Expected value.
 * @param mixed  $actual   Actual value.
 * @param string $message  Description.
 * @return void
 */
function assertEquals( $expected, $actual, $message ) {
	if ( $expected == $actual ) { // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
		mcr_pass( $message );

		return;
	}

	mcr_fail(
		$message,
		'expected ' . mcr_export( $expected ) . ', got ' . mcr_export( $actual )
	);
}

/**
 * Asserts a value is true.
 *
 * @param mixed  $actual  Value.
 * @param string $message Description.
 * @return void
 */
function assertTrue( $actual, $message ) {
	if ( true === $actual ) {
		mcr_pass( $message );

		return;
	}

	mcr_fail( $message, 'got ' . mcr_export( $actual ) );
}

/**
 * Asserts a value is false.
 *
 * @param mixed  $actual  Value.
 * @param string $message Description.
 * @return void
 */
function assertFalse( $actual, $message ) {
	if ( false === $actual ) {
		mcr_pass( $message );

		return;
	}

	mcr_fail( $message, 'got ' . mcr_export( $actual ) );
}

/**
 * Asserts two values are not identical.
 *
 * @param mixed  $expected Value that must differ.
 * @param mixed  $actual   Value.
 * @param string $message  Description.
 * @return void
 */
function assertNotSame( $expected, $actual, $message ) {
	if ( $expected !== $actual ) {
		mcr_pass( $message );

		return;
	}

	mcr_fail( $message, 'both values are ' . mcr_export( $actual ) );
}

/**
 * Asserts a count.
 *
 * @param int    $expected Expected count.
 * @param array  $actual   Array.
 * @param string $message  Description.
 * @return void
 */
function assertCount( $expected, $actual, $message ) {
	$count = is_array( $actual ) || $actual instanceof Countable ? count( $actual ) : -1;

	if ( $expected === $count ) {
		mcr_pass( $message );

		return;
	}

	mcr_fail( $message, 'expected count ' . $expected . ', got ' . $count );
}

/**
 * Asserts a callable throws.
 *
 * @param callable $callback Callback.
 * @param string   $message  Description.
 * @return void
 */
function assertThrows( $callback, $message ) {
	try {
		call_user_func( $callback );
	} catch ( \Throwable $e ) {
		mcr_pass( $message . ' (' . get_class( $e ) . ')' );

		return;
	}

	mcr_fail( $message, 'no exception was thrown' );
}

/**
 * Asserts a callable throws a WP_Error with a specific code.
 *
 * @param callable $callback Callback.
 * @param string   $code     Expected error code.
 * @param string   $message  Description.
 * @return void
 */
function assertThrowsWith( $callback, $code, $message ) {
	try {
		call_user_func( $callback );
	} catch ( \Throwable $e ) {
		if ( is_wp_error( $e ) && $e->get_error_code() === $code ) {
			mcr_pass( $message );

			return;
		}

		mcr_fail(
			$message,
			'expected WP_Error ' . $code . ', got ' . ( is_wp_error( $e ) ? $e->get_error_code() : get_class( $e ) )
		);

		return;
	}

	mcr_fail( $message, 'no exception was thrown' );
}

/**
 * Exports a value for a failure message.
 *
 * @param mixed $value Value.
 * @return string
 */
function mcr_export( $value ) {
	if ( is_bool( $value ) ) {
		return $value ? 'true' : 'false';
	}

	if ( null === $value ) {
		return 'null';
	}

	if ( is_scalar( $value ) ) {
		return var_export( $value, true );
	}

	if ( is_array( $value ) ) {
		return 'array(' . count( $value ) . ')' . json_encode( $value );
	}

	return get_class( $value );
}

/* ==========================================================================
 * 2. WordPress stubs
 * ======================================================================== */

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Minimal WP_Error stand-in.
	 */
	class WP_Error {

		/**
		 * Error code.
		 *
		 * @var string
		 */
		public $code;

		/**
		 * Error message.
		 *
		 * @var string
		 */
		public $message;

		/**
		 * Error data.
		 *
		 * @var mixed
		 */
		public $data;

		/**
		 * Constructor.
		 *
		 * @param string $code    Error code.
		 * @param string $message Error message.
		 * @param mixed  $data    Error data.
		 */
		public function __construct( $code = '', $message = '', $data = '' ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		/**
		 * Returns the error code.
		 *
		 * @return string
		 */
		public function get_error_code() {
			return $this->code;
		}

		/**
		 * Returns the error message.
		 *
		 * @return string
		 */
		public function get_error_message() {
			return $this->message;
		}

		/**
		 * Returns the error data.
		 *
		 * @return mixed
		 */
		public function get_error_data() {
			return $this->data;
		}

		/**
		 * Adds or replaces the error data.
		 *
		 * @param mixed $data Data.
		 * @return void
		 */
		public function add_data( $data ) {
			$this->data = $data;
		}
	}
}

$GLOBALS['mcr_filters'] = array();
$GLOBALS['mcr_options'] = array(
	'mcr_settings' => array(),
);
$GLOBALS['mcr_current_user'] = 0;
$GLOBALS['mcr_caps']       = array();

/**
 * Translation stub.
 *
 * @param string $text Text.
 * @return string
 */
function __( $text ) { // phpcs:ignore
	return $text;
}

/**
 * Translation with context stub.
 *
 * @param string $text    Text.
 * @param string $context Context.
 * @return string
 */
function _x( $text, $context ) { // phpcs:ignore
	return $text;
}

/**
 * Escaping stubs.
 *
 * @param string $text Text.
 * @return string
 */
function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES );
}

/**
 * Attribute escaping stub.
 *
 * @param string $text Text.
 * @return string
 */
function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES );
}

/**
 * URL escaping stub.
 *
 * @param string $url URL.
 * @return string
 */
function esc_url( $url ) {
	return filter_var( (string) $url, FILTER_SANITIZE_URL );
}

/**
 * KSES stub.
 *
 * @param string $text Text.
 * @return string
 */
function wp_kses_post( $text ) {
	return (string) $text;
}

/**
 * Text sanitising stub.
 *
 * @param string $text Text.
 * @return string
 */
function sanitize_text_field( $text ) {
	return trim( preg_replace( '/[\r\n\t]+/', ' ', wp_strip_all_tags( (string) $text ) ) );
}

/**
 * Slug sanitising stub.
 *
 * Mirrors core behaviour closely enough for the page-slug code: lowercase,
 * non-alphanumerics collapsed to a single dash, trimmed.
 *
 * @param string $title Raw title or slug.
 * @return string
 */
function sanitize_title( $title ) {
	$title = strtolower( (string) $title );
	$title = preg_replace( '/[^a-z0-9\-_]+/', '-', $title );

	return trim( (string) $title, '-' );
}

/**
 * Key sanitising stub.
 *
 * @param string $key Key.
 * @return string
 */
function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}

/**
 * Tag stripping stub.
 *
 * @param string $text Text.
 * @return string
 */
function wp_strip_all_tags( $text ) {
	return strip_tags( (string) $text );
}

/**
 * Absolute integer stub.
 *
 * @param mixed $value Value.
 * @return int
 */
function absint( $value ) {
	return abs( (int) $value );
}

/**
 * Argument merging stub.
 *
 * WordPress semantics: wp_parse_args( $args, $defaults ) merges $defaults
 * FIRST and lets $args override them.
 *
 * @param array $args     Overrides.
 * @param array $defaults Defaults.
 * @return array
 */
function wp_parse_args( $args, $defaults = array() ) { // phpcs:ignore
	if ( is_object( $args ) ) {
		$args = get_object_vars( $args );
	}

	if ( ! is_array( $args ) ) {
		$args = array();
	}

	return array_merge( $defaults, $args );
}

/**
 * Filter application stub.
 *
 * @param string $tag   Filter name.
 * @param mixed  $value Value.
 * @param mixed  ...$args Extra arguments.
 * @return mixed
 */
function apply_filters( $tag, $value ) {
	$args = func_get_args();

	if ( empty( $GLOBALS['mcr_filters'][ $tag ] ) ) {
		return $value;
	}

	foreach ( $GLOBALS['mcr_filters'][ $tag ] as $callback ) {
		$value = call_user_func_array( $callback, array_slice( $args, 1 ) );
	}

	return $value;
}

/**
 * Filter registration stub.
 *
 * @param string   $tag      Filter name.
 * @param callable $callback Callback.
 * @return void
 */
function add_filter( $tag, $callback ) {
	$GLOBALS['mcr_filters'][ $tag ][] = $callback;
}

/**
 * Action registration stub.
 *
 * @param string   $tag      Action name.
 * @param callable $callback Callback.
 * @return void
 */
function add_action( $tag, $callback ) {
	$GLOBALS['mcr_filters'][ $tag ][] = $callback;

	// Actions are recorded separately so a test can assert that a hook was
	// wired to something.
	if ( ! isset( $GLOBALS['mcr_actions'][ $tag ] ) ) {
		$GLOBALS['mcr_actions'][ $tag ] = array();
	}

	$GLOBALS['mcr_actions'][ $tag ][] = $callback;
}

/**
 * Action execution stub.
 *
 * @param string $tag Action name.
 * @param mixed  ...$args Arguments.
 * @return void
 */
function do_action( $tag ) {
	$args = func_get_args();

	if ( empty( $GLOBALS['mcr_filters'][ $tag ] ) ) {
		return;
	}

	foreach ( $GLOBALS['mcr_filters'][ $tag ] as $callback ) {
		call_user_func_array( $callback, array_slice( $args, 1 ) );
	}
}

/**
 * Salt stub.
 *
 * @param string $scheme Scheme.
 * @return string
 */
function wp_salt( $scheme = 'auth' ) {
	return 'mcr-test-salt-' . $scheme;
}

/**
 * Timezone stub.
 *
 * @return DateTimeZone
 */
function wp_timezone() {
	$tz = get_option( 'timezone_string' );

	if ( ! empty( $tz ) ) {
		return new DateTimeZone( $tz );
	}

	return new DateTimeZone( 'UTC' );
}

if ( ! function_exists( 'wp_date' ) ) {
	function wp_date( $format, $timestamp = null, $timezone = null ) {
		if ( null === $timestamp ) {
			$timestamp = time();
		}
		return date( $format, $timestamp );
	}
}

/**
 * Current time stub.
 *
 * @param string $type Type.
 * @param bool   $gmt  Whether GMT.
 * @return int
 */
function current_time( $type = 'mysql', $gmt = false ) { // phpcs:ignore
	if ( 'timestamp' === $type ) {
		return time();
	}

	return gmdate( 'Y-m-d H:i:s' );
}

/**
 * JSON encoding stub.
 *
 * @param mixed $data Data.
 * @param int   $flags Flags.
 * @return string
 */
function wp_json_encode( $data, $flags = 0 ) {
	return json_encode( $data, $flags );
}

/**
 * Password generator stub.
 *
 * @param int $length Length.
 * @return string
 */
function wp_generate_password( $length = 12 ) {
	return substr( str_repeat( 'abcdef0123456789', 4 ), 0, $length );
}

/**
 * Hash stub.
 *
 * @param string $data Data.
 * @return string
 */
function wp_hash( $data ) {
	return md5( (string) $data );
}

/**
 * WP_Error check stub.
 *
 * @param mixed $thing Thing.
 * @return bool
 */
function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

/**
 * Unslash stub.
 *
 * @param mixed $value Value.
 * @return mixed
 */
function wp_unslash( $value ) {
	return $value;
}

/**
 * Option getter stub.
 *
 * @param string $name    Option name.
 * @param mixed  $default Default.
 * @return mixed
 */
function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['mcr_options'] ) ? $GLOBALS['mcr_options'][ $name ] : $default;
}

/**
 * Option setter stub.
 *
 * @param string $name  Option name.
 * @param mixed  $value Value.
 * @return bool
 */
function update_option( $name, $value ) {
	$GLOBALS['mcr_options'][ $name ] = $value;

	return true;
}

/**
 * Option adder stub.
 *
 * @param string $name  Option name.
 * @param mixed  $value Value.
 * @return bool
 */
function add_option( $name, $value ) {
	if ( array_key_exists( $name, $GLOBALS['mcr_options'] ) ) {
		return false;
	}

	$GLOBALS['mcr_options'][ $name ] = $value;

	return true;
}

/**
 * Current user id stub.
 *
 * @return int
 */
function get_current_user_id() {
	return (int) $GLOBALS['mcr_current_user'];
}

/**
 * Logged-in stub.
 *
 * @return bool
 */
function is_user_logged_in() {
	return get_current_user_id() > 0;
}

/**
 * Capability stub.
 *
 * @param string $cap Capability.
 * @return bool
 */
function current_user_can( $cap ) {
	return ! empty( $GLOBALS['mcr_caps'][ $cap ] );
}

if ( ! function_exists( 'did_action' ) ) {
	function did_action( $tag ) {
		return 0;
	}
}

/**
 * Transient getter stub.
 *
 * @param string $key Key.
 * @return mixed
 */
function get_transient( $key ) {
	return $GLOBALS['mcr_options'][ 'transient_' . $key ] ?? false;
}

/**
 * Transient setter stub.
 *
 * @param string $key   Key.
 * @param mixed  $value Value.
 * @param int    $ttl   TTL.
 * @return bool
 */
function set_transient( $key, $value, $ttl = 0 ) {
	$GLOBALS['mcr_options'][ 'transient_' . $key ] = $value;

	return true;
}

/**
 * Transient deleter stub.
 *
 * @param string $key Key.
 * @return bool
 */
function delete_transient( $key ) {
	unset( $GLOBALS['mcr_options'][ 'transient_' . $key ] );

	return true;
}

/**
 * Number formatting stub.
 *
 * @param float $number Number.
 * @return string
 */
function number_format_i18n( $number ) {
	return number_format( (float) $number );
}

/**
 * Date formatting stub.
 *
 * @param string $date   Date.
 * @param string $format Format.
 * @return string
 */
function get_date_from_gmt( $date, $format = 'Y-m-d' ) {
	return gmdate( $format, strtotime( $date . ' UTC' ) );
}

/**
 * Site time stub.
 *
 * @return int
 */
function current_datetime_gmt() {
	return time();
}

