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
 * Whether an action has already fired.
 *
 * @param string $tag Hook name.
 * @return int
 */
function did_action( $tag ) {
	return 1;
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
 * Plural translation stub.
 *
 * @param string $single Singular.
 * @param string $plural Plural.
 * @param int    $number Number.
 * @param string $domain Text domain.
 * @return string
 */
function _n( $single, $plural, $number, $domain = 'default' ) { // phpcs:ignore
	return 1 === (int) $number ? $single : $plural;
}

/**
 * Plural translation with context stub.
 *
 * @param string $single  Singular.
 * @param string $plural  Plural.
 * @param int    $number  Number.
 * @param string $context Context.
 * @param string $domain  Text domain.
 * @return string
 */
function _nx( $single, $plural, $number, $context, $domain = 'default' ) { // phpcs:ignore
	return 1 === (int) $number ? $single : $plural;
}

/**
 * Translation echo stub.
 *
 * @param string $text   Text.
 * @param string $domain Text domain.
 * @return void
 */
function _e( $text, $domain = 'default' ) { // phpcs:ignore
	echo $text; // phpcs:ignore
}

/**
 * Escaped translation stub.
 *
 * @param string $text   Text.
 * @param string $domain Text domain.
 * @return string
 */
function esc_html__( $text, $domain = 'default' ) { // phpcs:ignore
	return esc_html( $text );
}

/**
 * Escaped translation echo stub.
 *
 * @param string $text   Text.
 * @param string $domain Text domain.
 * @return void
 */
function esc_html_e( $text, $domain = 'default' ) { // phpcs:ignore
	echo esc_html( $text );
}

/**
 * Escaped attribute translation stub.
 *
 * @param string $text   Text.
 * @param string $domain Text domain.
 * @return string
 */
function esc_attr__( $text, $domain = 'default' ) { // phpcs:ignore
	return esc_attr( $text );
}

/**
 * Escaped attribute translation echo stub.
 *
 * @param string $text   Text.
 * @param string $domain Text domain.
 * @return void
 */
function esc_attr_e( $text, $domain = 'default' ) { // phpcs:ignore
	echo esc_attr( $text );
}

/**
 * Escaped translation with context stub.
 *
 * @param string $text    Text.
 * @param string $context Context.
 * @param string $domain  Text domain.
 * @return string
 */
function esc_html_x( $text, $context, $domain = 'default' ) { // phpcs:ignore
	return esc_html( $text );
}

/**
 * Escaped attribute translation with context stub.
 *
 * @param string $text    Text.
 * @param string $context Context.
 * @param string $domain  Text domain.
 * @return string
 */
function esc_attr_x( $text, $context, $domain = 'default' ) { // phpcs:ignore
	return esc_attr( $text );
}

/**
 * Nonce creation.
 *
 * @param string $action Action.
 * @return string
 */
function wp_create_nonce( $action = -1 ) {
	return 'nonce-' . md5( (string) $action );
}

/**
 * Nonce verification.
 *
 * @param string $nonce  Nonce.
 * @param string $action Action.
 * @return bool
 */
function wp_verify_nonce( $nonce, $action = -1 ) {
	return 'nonce-' . md5( (string) $action ) === $nonce;
}

/**
 * Abort execution.
 *
 * @param string $message Message.
 * @return void
 */
function wp_die( $message = '' ) {
	throw new RuntimeException( 'wp_die: ' . (string) $message );
}

/**
 * JSON success response.
 *
 * @param mixed $data    Data.
 * @param int   $status  Status.
 * @return void
 */
function wp_send_json_success( $data = null, $status = 200 ) {
	echo wp_json_encode(
		array(
			'success' => true,
			'data'    => $data,
		)
	);
}

/**
 * JSON error response.
 *
 * @param mixed $data   Data.
 * @param int   $status Status.
 * @return void
 */
function wp_send_json_error( $data = null, $status = 400 ) {
	echo wp_json_encode(
		array(
			'success' => false,
			'data'    => $data,
		)
	);
}

/**
 * KSES stub.
 *
 * @param string $string HTML.
 * @param array  $allowed Allowed tags.
 * @return string
 */
function wp_kses( $string, $allowed = array() ) {
	return (string) $string;
}

/**
 * Checkbox checked attribute.
 *
 * @param mixed $checked Current value.
 * @param mixed $current Value to compare.
 * @param bool  $echo    Whether to echo.
 * @return string
 */
function checked( $checked, $current = true, $echo = true ) {
	$result = (string) $checked === (string) $current ? ' checked="checked"' : '';

	if ( $echo ) {
		echo $result; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	return $result;
}

/**
 * Selected attribute.
 *
 * @param mixed $selected Current value.
 * @param mixed $current  Value to compare.
 * @param bool  $echo     Whether to echo.
 * @return string
 */
function selected( $selected, $current = true, $echo = true ) {
	$result = (string) $selected === (string) $current ? ' selected="selected"' : '';

	if ( $echo ) {
		echo $result; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	return $result;
}

/**
 * Disabled attribute.
 *
 * @param mixed $disabled Current value.
 * @param mixed $current  Value to compare.
 * @param bool  $echo     Whether to echo.
 * @return string
 */
function disabled( $disabled, $current = true, $echo = true ) {
	$result = (string) $disabled === (string) $current ? ' disabled="disabled"' : '';

	if ( $echo ) {
		echo $result; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	return $result;
}

/**
 * Shortcode attribute normalisation.
 *
 * Mirrors WordPress: lower-cased keys, values trimmed, defaults applied.
 *
 * @param array  $pairs    Attribute pairs.
 * @param array  $atts     Attributes to merge into.
 * @param string $shortcode Shortcode tag.
 * @return array
 */
function shortcode_atts( $pairs, $atts, $shortcode = '' ) {
	$atts = (array) $atts;
	$out  = array();

	foreach ( (array) $pairs as $name => $default ) {
		$key = strtolower( $name );

		if ( array_key_exists( $key, $atts ) ) {
			$out[ $key ] = $atts[ $key ];
		} else {
			$out[ $key ] = $default;
		}
	}

	if ( $shortcode ) {
		$out = apply_filters( "shortcode_atts_{$shortcode}", $out, $pairs, $atts, $shortcode );
	}

	return $out;
}

/**
 * Builds a URL with query arguments.
 *
 * @param array|string $args Arguments or a URL.
 * @param string       $url  URL.
 * @return string
 */
function add_query_arg( $args, $url = '' ) {
	if ( ! is_array( $args ) ) {
		$args = array( $args => $url );
		$url  = '';
	}

	$query = '';

	foreach ( $args as $key => $value ) {
		$query .= ( '' === $query ? '?' : '&' ) . rawurlencode( (string) $key ) . '=' . rawurlencode( (string) $value );
	}

	return (string) $url . $query;
}

/**
 * Whether a script is registered or enqueued.
 *
 * @param string $handle Handle.
 * @param string $list  List.
 * @return bool
 */
function wp_script_is( $handle, $list = 'enqueued' ) {
	return false;
}

/**
 * Whether a style is registered or enqueued.
 *
 * @param string $handle Handle.
 * @param string $list  List.
 * @return bool
 */
function wp_style_is( $handle, $list = 'enqueued' ) {
	return false;
}

/**
 * Enqueue a style.
 *
 * @param string $handle Handle.
 * @return void
 */
function wp_enqueue_style( $handle = '', $src = '', $deps = array(), $ver = false, $media = 'all' ) {}

/**
 * Enqueue a script.
 *
 * @param string $handle Handle.
 * @return void
 */
function wp_enqueue_script( $handle = '', $src = '', $deps = array(), $ver = false, $args = array() ) {}

/**
 * Register a style.
 *
 * @param string $handle Handle.
 * @return bool
 */
function wp_register_style( $handle, $src = '', $deps = array(), $ver = false, $media = 'all' ) {
	return true;
}

/**
 * Localize a script.
 *
 * @param string $handle      Handle.
 * @param string $object_name Object name.
 * @param array  $l10n        Data.
 * @return bool
 */
function wp_localize_script( $handle, $object_name, $l10n ) {
	return true;
}

/**
 * The template directory.
 *
 * @return string
 */
function get_template_directory() {
	return dirname( __DIR__ ) . '/';
}

/**
 * The stylesheet directory.
 *
 * @return string
 */
function get_stylesheet_directory() {
	return dirname( __DIR__ ) . '/';
}

/**
 * The template directory URI.
 *
 * @return string


/**
 * A formatted date.
 *
 * @param string $format Format.
 * @param int    $timestamp Timestamp.
 * @return string
 */
function wp_date( $format, $timestamp = null ) {
	return gmdate( $format, null === $timestamp ? time() : (int) $timestamp );
}

/**
 * Normalise a filesystem path.
 *
 * @param string $path Path.
 * @return string
 */
function wp_normalize_path( $path ) {
	return str_replace( '\\', '/', (string) $path );
}

/**
 * The registration URL.
 *
 * @return string
 */
function wp_registration_url() {
	return 'https://example.test/register';
}

/**
 * The queried object id.
 *
 * @return int
 */
function get_queried_object_id() {
	return 0;
}

/**
 * Whether a hook has a callback.
 *
 * @param string $hook     Hook.
 * @param mixed  $callback Callback.
 * @return bool
 */
function has_action( $hook, $callback = false ) {
	return false;
}

/**
 * Whether a filter has a callback.
 *
 * @param string $hook     Hook.
 * @param mixed  $callback Callback.
 * @return bool
 */
function has_filter( $hook, $callback = false ) {
	return false;
}

/**
 * The login URL.
 *
 * @param string $redirect Redirect.
 * @return string
 */
function wp_login_url( $redirect = '' ) {
	return 'https://example.test/wp-login.php';
}

/**
 * The stylesheet directory URI.
 *
 * @return string
 */
function get_stylesheet_directory_uri() {
	return 'https://example.test/wp-content/themes/madara/';
}

/**
 * The template directory URI.
 *
 * @return string
 */
function get_template_directory_uri() {
	return 'https://example.test/wp-content/themes/madara/';
}

/**
 * A localised date.
 *
 * The weekday abbreviation follows the site's language, which is why
 * gmdate() is not used for it.
 *
 * @param string   $format    Date format.
 * @param int|null $timestamp Timestamp.
 * @return string
 */
function date_i18n( $format, $timestamp = null ) {
	return gmdate( $format, null === $timestamp ? time() : (int) $timestamp );
}

/**
 * The current user.
 *
 * @return object|null
 */
function wp_get_current_user() {
	return null;
}

/**
 * A user by id.
 *
 * @param int $id User id.
 * @return object|null
 */
function get_userdata( $id ) {
	return null;
}

/**
 * A user by field.
 *
 * @param string $field Field.
 * @param mixed  $value Value.
 * @return object|null
 */
function get_user_by( $field, $value ) {
	return null;
}

/**
 * The avatar URL.
 *
 * @param mixed  $id_or_email User.
 * @param int    $size        Size.
 * @param string $default     Default.
 * @return string
 */
function get_avatar_url( $id_or_email, $size = 96, $default = '' ) {
	return 'https://example.test/avatar.png';
}

/**
 * The site icon.
 *
 * @return string
 */
function get_site_icon_url( $size = 512, $url = '', $blog_id = 0 ) {
	return 'https://example.test/icon.png';
}

/**
 * The site name.
 *
 * @return string
 */
function get_blogname() {
	return 'Test Site';
}

/**
 * The site description.
 *
 * @return string
 */
function get_blogdescription() {
	return 'Just another site';
}

/**
 * The date format option.
 *
 * @param string $format Option name.
 * @param mixed  $default Default.
 * @return string

/**
 * The option value.
 *
 * @param string $name    Option name.
 * @param mixed  $default Default.
 * @return mixed
 */
function get_theme_mod( $name, $default = false ) {
	return $default;
}

/**
 * The option value.
 *
 * @param string $name    Option name.
 * @param mixed  $default Default.
 * @return mixed
 */
function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['mcr_options'] ) ? $GLOBALS['mcr_options'][ $name ] : $default;
}

/**
 * Removes a query argument from a URL.
 *
 * @param string|array $key Argument key.
 * @param string       $url URL.
 * @return string
 */
function remove_query_arg( $key, $url = '' ) {
	$keys = (array) $key;

	foreach ( $keys as $name ) {
		$url = preg_replace( '/[?&]' . preg_quote( (string) $name, '/' ) . '=[^&]*(&|$)/', '$1', (string) $url );
		$url = rtrim( (string) $url, '?&' );
	}

	return (string) $url;
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
 * Shortcode expansion.
 *
 * @param string $content Content.
 * @return string
 */
function do_shortcode( $content ) {
	$content = (string) $content;

	if ( empty( $GLOBALS['mcr_shortcodes'] ) ) {
		return $content;
	}

	foreach ( $GLOBALS['mcr_shortcodes'] as $tag => $callback ) {
		$pattern = '/\[' . preg_quote( $tag, '/' ) . '(?:\s+[^\]]*)?\]/';

		$content = preg_replace_callback(
			$pattern,
			static function ( $matches ) use ( $callback, $tag ) {
				$atts = array();

				if ( preg_match_all( '/(\w+)\s*=\s*"([^"]*)"/', $matches[0], $pairs, PREG_SET_ORDER ) ) {
					foreach ( $pairs as $pair ) {
						$atts[ $pair[1] ] = $pair[2];
					}
				}

				return (string) call_user_func( $callback, $atts, '', $tag );
			},
			$content
		);
	}

	return $content;
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

