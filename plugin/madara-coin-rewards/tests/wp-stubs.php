<?php
/**
 * A minimal WordPress for the boot smoke test.
 *
 * Only the functions the plugin actually calls during boot and
 * activation. Everything else the plugin uses at runtime is out of scope
 * for a boot test.
 *
 * @package MCR
 */

defined( 'ABSPATH' ) || exit;

$GLOBALS['mcr_actions']      = array();
$GLOBALS['mcr_activation']   = null;
$GLOBALS['mcr_deactivation'] = null;

/**
 * Shortcode registration.
 *
 * @param string   $tag      Shortcode tag.
 * @param callable $callback Callback.
 * @return void
 */
function add_shortcode( $tag, $callback ) {
	$GLOBALS['mcr_shortcodes'][ $tag ] = $callback;
}

/**
 * The widget base class.
 *
 * WordPress always loads wp-includes/class-wp-widget.php, so the plugin
 * can extend it unconditionally.
 */
class WP_Widget {

	/**
	 * Widget id.
	 *
	 * @var string
	 */
	public $id_base = '';

	/**
	 * Widget name.
	 *
	 * @var string
	 */
	public $name = '';

	/**
	 * Widget options.
	 *
	 * @var array
	 */
	public $widget_options = array();

	/**
	 * Control options.
	 *
	 * @var array
	 */
	public $control_options = array();

	/**
	 * Number of instances.
	 *
	 * @var int
	 */
	public $number = 1;

	/**
	 * Widget id.
	 *
	 * @var string
	 */
	public $id = '';

	/**
	 * Constructor.
	 *
	 * @param string $id_base Id base.
	 * @param string $name    Name.
	 * @param array  $options Options.
	 * @param array  $control_options Control options.
	 */
	public function __construct( $id_base, $name, $options = array(), $control_options = array() ) {
		$this->id_base         = $id_base;
		$this->name            = $name;
		$this->widget_options  = $options;
		$this->control_options = $control_options;
		$this->id              = $id_base . '-' . $this->number;
	}

	/**
	 * Renders the widget.
	 *
	 * @param array $args     Sidebar args.
	 * @param array $instance Instance.
	 * @return void
	 */
	public function widget( $args, $instance ) {}

	/**
	 * Renders the form.
	 *
	 * @param array $instance Instance.
	 * @return void
	 */
	public function form( $instance ) {}

	/**
	 * Updates the instance.
	 *
	 * @param array $new_instance New instance.
	 * @param array $old_instance Old instance.
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		return $new_instance;
	}

	/**
	 * Field id.
	 *
	 * @param string $field Field.
	 * @return string
	 */
	public function get_field_id( $field ) {
		return 'widget-' . $this->id_base . '-' . $this->number . '-' . $field;
	}

	/**
	 * Field name.
	 *
	 * @param string $field Field.
	 * @return string
	 */
	public function get_field_name( $field ) {
		return 'widget-' . $this->id_base . '[' . $this->number . '][' . $field . ']';
	}
}

/**
 * The WP_List_Table base class.
 */
class WP_List_Table {
	public $items = array();
	public $_pagination_args = array();

	public function __construct( $args = array() ) {}
	public function display() {}
	public function prepare_items() {}
	public function get_columns() { return array(); }
	public function get_sortable_columns() { return array(); }
	public function get_pagenum() { return 1; }
	public function set_pagination_args( $args ) { $this->_pagination_args = $args; }
	public function get_total_items() { return count( $this->items ); }
}

/**
 * Plugin directory helper.
 *
 * @param string $file File.
 * @return string
 */
function plugin_dir_path( $file ) {
	return rtrim( dirname( $file ), '/\\' ) . '/';
}

/**
 * Plugin URL helper.
 *
 * @param string $file File.
 * @return string
 */
function plugin_dir_url( $file ) {
	// A literal: the plugin assigns this to MCR_PLUGIN_URL, so returning
	// the constant here would be a self-reference.
	return 'https://example.test/wp-content/plugins/madara-coin-rewards/';
}

/**
 * Plugin basename helper.
 *
 * @param string $file File.
 * @return string
 */
function plugin_basename( $file ) {
	return 'madara-coin-rewards/' . basename( $file );
}

/**
 * Activation hook.
 *
 * @param string   $file     File.
 * @param callable $callback Callback.
 * @return void
 */
function register_activation_hook( $file, $callback ) {
	$GLOBALS['mcr_activation'] = $callback;
}

/**
 * Deactivation hook.
 *
 * @param string   $file     File.
 * @param callable $callback Callback.
 * @return void
 */
function register_deactivation_hook( $file, $callback ) {
	$GLOBALS['mcr_deactivation'] = $callback;
}

/**
 * Text domain loader.
 *
 * @param string $domain Domain.
 * @param bool   $abs    Absolute.
 * @param string $path   Path.
 * @return bool
 */
function load_plugin_textdomain( $domain, $abs = false, $path = '' ) {
	return true;
}

if ( ! function_exists( 'wp_date' ) ) {
	/**
	 * Formats a date/time using WordPress timezone.
	 *
	 * @param string        $format    Format.
	 * @param int|null      $timestamp Timestamp.
	 * @param DateTimeZone|null $timezone Timezone.
	 * @return string
	 */
	function wp_date( $format, $timestamp = null, $timezone = null ) {
		if ( null === $timestamp ) {
			$timestamp = time();
		}
		return date( $format, $timestamp );
	}
}

/**
 * Site timezone string.
 *
 * @return string
 */
function wp_timezone_string() {
	return 'UTC';
}

/**
 * Permalink.
 *
 * @param int $id Post id.
 * @return string
 */
function get_permalink( $id = 0 ) {
	return 'https://example.test/?p=' . (int) $id;
}

/**
 * Home URL.
 *
 * @param string $path Path.
 * @return string
 */
function home_url( $path = '' ) {
	return 'https://example.test' . $path;
}

/**
 * Admin URL.
 *
 * @param string $path Path.
 * @return string
 */
function admin_url( $path = '' ) {
	return 'https://example.test/wp-admin/' . $path;
}

/**
 * REST URL.
 *
 * @param string $path Path.
 * @return string
 */
function rest_url( $path = '' ) {
	return 'https://example.test/wp-json/' . $path;
}

/**
 * Nonce creation.
 *
 * @param string $action Action.
 * @return string
 */
function wp_create_nonce( $action = -1 ) {
	return substr( hash_hmac( 'md5', (string) $action, 'nonce_secret' ), -12, 10 );
}

/**
 * Nonce verification.
 *
 * @param string $nonce  Nonce.
 * @param string $action Action.
 * @return bool
 */
function wp_verify_nonce( $nonce, $action = -1 ) {
	return ! empty( $nonce ) && hash_equals( (string) wp_create_nonce( $action ), (string) $nonce );
}

/**
 * Cron scheduling.
 *
 * @param int    $timestamp Timestamp.
 * @param string $recurrence Recurrence.
 * @param string $hook      Hook.
 * @return bool
 */
function wp_schedule_event( $timestamp, $recurrence, $hook ) {
	return true;
}

/**
 * Cron clearing.
 *
 * @param string $hook Hook.
 * @return int
 */
function wp_clear_scheduled_hook( $hook ) {
	return 1;
}

/**
 * Next scheduled.
 *
 * @param string $hook Hook.
 * @return int
 */
function wp_next_scheduled( $hook ) {
	return 1;
}

/**
 * Role lookup.
 *
 * @param string $role Role name.
 * @return object|null
 */
function get_role( $role ) {
	return null;
}

/**
 * Post insert.
 *
 * @param array $args Post args.
 * @return int
 */
function wp_insert_post( $args ) {
	return 0;
}

/**
 * Post query.
 *
 * @param array $args Query args.
 * @return array
 */
function get_posts( $args = array() ) {
	return array();
}

/**
 * Post field.
 *
 * @param string $field Field.
 * @param int    $id    Post id.
 * @return string
 */
function get_post_field( $field, $id ) {
	return '';
}

/**
 * Rewrite rules flush.
 *
 * @return void
 */
function flush_rewrite_rules() {}

/**
 * Doing AJAX.
 *
 * @return bool
 */
function wp_doing_ajax() {
	return false;
}

/**
 * Is a feed.
 *
 * @return bool
 */
function is_feed() {
	return false;
}

/**
 * Is admin.
 *
 * @return bool
 */
function is_admin() {
	return ! empty( $GLOBALS['mcr_is_admin'] );
}

/**
 * Is singular.
 *
 * @return bool
 */
function is_singular() {
	return false;
}

/**
 * Current post.
 *
 * @return null
 */
function get_post() {
	return null;
}

/**
 * Shortcode detection.
 *
 * @param string $content   Content.
 * @param string $shortcode Shortcode.
 * @return bool
 */
function has_shortcode( $content, $shortcode ) {
	return false;
}

/**
 * Block detection.
 *
 * @param string $name Block name.
 * @param mixed  $post Post.
 * @return bool
 */
function has_block( $name, $post = null ) {
	return false;
}

/**
 * Post meta.
 *
 * @param int    $id     Post id.
 * @param string $key    Key.
 * @param bool   $single Single.
 * @return mixed
 */
function get_post_meta( $id, $key = '', $single = false ) {
	return $single ? '' : array();
}

if ( ! function_exists( 'get_user_meta' ) ) {
	/**
	 * User meta getter stub.
	 *
	 * @param int    $user_id User ID.
	 * @param string $key     Meta key.
	 * @param bool   $single  Whether to return a single value.
	 * @return mixed
	 */
	function get_user_meta( $user_id, $key = '', $single = false ) {
		if ( '' === $key ) {
			return $GLOBALS['mcr_user_meta'][ $user_id ] ?? array();
		}
		$val = $GLOBALS['mcr_user_meta'][ $user_id ][ $key ] ?? ( $single ? '' : array() );
		return $single ? $val : ( is_array( $val ) ? $val : array( $val ) );
	}
}

if ( ! function_exists( 'update_user_meta' ) ) {
	/**
	 * User meta updater stub.
	 *
	 * @param int    $user_id User ID.
	 * @param string $key     Meta key.
	 * @param mixed  $value   Meta value.
	 * @return bool
	 */
	function update_user_meta( $user_id, $key, $value ) {
		if ( ! isset( $GLOBALS['mcr_user_meta'] ) ) {
			$GLOBALS['mcr_user_meta'] = array();
		}
		if ( ! isset( $GLOBALS['mcr_user_meta'][ $user_id ] ) ) {
			$GLOBALS['mcr_user_meta'][ $user_id ] = array();
		}
		$GLOBALS['mcr_user_meta'][ $user_id ][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	/**
	 * Option delete.
	 *
	 * @param string $name Option name.
	 * @return bool
	 */
	function delete_option( $name ) {
		unset( $GLOBALS['mcr_options'][ $name ] );
		return true;
	}
}

if ( ! function_exists( 'add_option' ) ) {
	/**
	 * Option add.
	 *
	 * @param string $name  Option name.
	 * @param mixed  $value Value.
	 * @return bool
	 */
	function add_option( $name, $value = '', $deprecated = '', $autoload = 'yes' ) {
		if ( ! isset( $GLOBALS['mcr_options'][ $name ] ) ) {
			$GLOBALS['mcr_options'][ $name ] = $value;
			return true;
		}
		return false;
	}
}

/**
 * Site option read.
 *
 * @param string $option Option.
 * @param mixed  $default Default.
 * @return mixed
 */
function get_site_option( $option, $default = false ) {
	return $default;
}

/**
 * Site option write.
 *
 * @param string $option Option.
 * @param mixed  $value  Value.
 * @return bool
 */
function update_site_option( $option, $value ) {
	return true;
}

/**
 * Admin menu page.
 *
 * @return string
 */
function add_menu_page() {
	$GLOBALS['mcr_menu_pages'][] = func_get_args();
	return 'mcr-dashboard';
}

/**
 * Admin submenu page.
 *
 * @return string
 */
function add_submenu_page() {
	$GLOBALS['mcr_menu_pages'][] = func_get_args();
	return '';
}

/**
 * Current screen.
 *
 * @return null
 */
function get_current_screen() {
	if ( ! empty( $GLOBALS['mcr_screen_id'] ) ) {
		return (object) array( 'id' => $GLOBALS['mcr_screen_id'] );
	}
	return null;
}

function __checked_selected_helper( $helper, $current, $echo, $type ) {
	if ( (string) $helper === (string) $current ) {
		$result = " $type='$type'";
	} else {
		$result = '';
	}
	if ( $echo ) {
		echo $result;
	}
	return $result;
}

function checked( $checked, $current = true, $echo = true ) {
	return __checked_selected_helper( $checked, $current, $echo, 'checked' );
}

function selected( $selected, $current = true, $echo = true ) {
	return __checked_selected_helper( $selected, $current, $echo, 'selected' );
}

/**
 * dbDelta stub for database upgrades.
 *
 * @param string|array $queries Query or queries.
 * @return array
 */
function dbDelta( $queries ) {
	if ( ! isset( $GLOBALS['mcr_dbdelta'] ) ) {
		$GLOBALS['mcr_dbdelta'] = array();
	}
	foreach ( (array) $queries as $query ) {
		$GLOBALS['mcr_dbdelta'][] = $query;
	}
	return array();
}

/**
 * Register a setting.
 *
 * @param string $group Group.
 * @param string $name  Option name.
 * @param array  $args  Args.
 * @return void
 */
function register_setting( $group, $name, $args = array() ) {}

/**
 * Add a settings section.
 *
 * @return void
 */
function add_settings_section() {}

/**
 * Add a settings field.
 *
 * @return void
 */
function add_settings_field() {}

/**
 * Render settings fields.
 *
 * @return void
 */
function settings_fields() {}

/**
 * Render settings sections.
 *
 * @return void
 */
function do_settings_sections() {}

/**
 * Render settings fields.
 *
 * @return void
 */
function do_settings_fields() {}

/**
 * Register a block type.
 *
 * @param string $name Block name.
 * @param array  $args Args.
 * @return array
 */
function register_block_type( $name, $args = array() ) {
	return array();
}

/**
 * Register a script.
 *
 * @return bool
 */
function wp_register_script( $handle, $src = '', $deps = array(), $ver = false, $args = array() ) {
	return true;
}

/**
 * Is a script registered.
 *
 * @return bool
 */
function wp_script_is( $handle, $list = 'enqueued' ) {
	return false;
}

/**
 * Is a style registered or enqueued.
 *
 * @param string $handle Style handle.
 * @param string $list   List.
 * @return bool
 */
function wp_style_is( $handle, $list = 'enqueued' ) {
	return false;
}

/**
 * Localize a script.
 *
 * @return bool
 */
function wp_localize_script( $handle, $object_name, $l10n ) {
	return true;
}

/**
 * Enqueue a script.
 *
 * @return void
 */
function wp_enqueue_script() {}

/**
 * Enqueue a style.
 *
 * @return void
 */
function wp_enqueue_style() {}

/**
 * Register a widget.
 *
 * @param string $class Class name.
 * @return void
 */
function register_widget( $class ) {}

/**
 * Login URL.
 *
 * @param string $redirect Redirect.
 * @return string
 */
function wp_login_url( $redirect = '' ) {
	return 'https://example.test/wp-login.php';
}

/**
 * Register a REST route.
 *
 * @return void
 */
function register_rest_route() {}

if ( ! function_exists( 'did_action' ) ) {
	/**
	 * Did an action fire.
	 *
	 * @param string $tag Hook name.
	 * @return int
	 */
	function did_action( $tag ) {
		return 1;
	}
}

/**
 * Safe redirect.
 *
 * @param string $location Location.
 * @return bool
 */
function wp_safe_redirect( $location ) {
	return true;
}

if ( ! function_exists( 'wp_die' ) ) {
	/**
	 * Abort.
	 *
	 * @param string|WP_Error $message Message.
	 * @return void
	 */
	function wp_die( $message = '', $title = '', $args = array() ) {
		$text = is_wp_error( $message ) ? $message->get_error_message() : (string) $message;
		throw new \RuntimeException( 'wp_die: ' . $text );
	}
}

/**
 * Blog information.
 *
 * @param string $show Field.
 * @return string
 */
function get_bloginfo( $show = '' ) {
	return 'version' === $show ? '6.5' : 'Test Site';
}

/**
 * Locale.
 *
 * @return string
 */
function get_locale() {
	return 'en_US';
}

if ( ! function_exists( 'wp_get_current_user' ) ) {
	function wp_get_current_user() {
		$uid = function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0;
		if ( $uid > 0 && function_exists( 'get_userdata' ) ) {
			return get_userdata( $uid );
		}
		$user = new stdClass();
		$user->ID = $uid;
		$user->display_name = $uid > 0 ? 'User ' . $uid : '';
		return $user;
	}
}

/**
 * Option read.
 *
 * @param string $name    Option name.
 * @param mixed  $default Default.
 * @return mixed
 */
/**
 * A fake wpdb.
 */
class MCR_Fake_WPDB {

	/**
	 * Table prefix.
	 *
	 * @var string
	 */
	public $prefix = 'wp_';

	/**
	 * Insert id.
	 *
	 * @var int
	 */
	public $insert_id = 0;

	/**
	 * Rows affected.
	 *
	 * @var int
	 */
	public $rows_affected = 0;

	/**
	 * Last error.
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
	 * Prepare a query.
	 *
	 * @param string $sql  SQL.
	 * @param mixed  ...$args Arguments.
	 * @return string
	 */
	public function prepare( $sql, ...$args ) {
		if ( empty( $args ) ) {
			return $sql;
		}

		if ( isset( $args[0] ) && is_array( $args[0] ) && 1 === count( $args ) ) {
			$args = $args[0];
		}

		$sql     = str_replace( array( '%d', '%f' ), '%s', $sql );
		$values  = array();

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
	 * Escape for LIKE.
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
	 * Run a query.
	 *
	 * @param string $sql SQL.
	 * @return int
	 */
	public function query( $sql ) {
		$this->queries[] = $sql;

		return 1;
	}

	/**
	 * Fetch a value.
	 *
	 * @param string $sql SQL.
	 * @return null
	 */
	public function get_var( $sql ) {
		return null;
	}

	/**
	 * Fetch a row.
	 *
	 * @param string $sql  SQL.
	 * @param string $type Type.
	 * @return null
	 */
	public function get_row( $sql, $type = 'OBJECT' ) {
		return null;
	}

	/**
	 * Fetch rows.
	 *
	 * @param string $sql  SQL.
	 * @param string $type Type.
	 * @return array
	 */
	public function get_results( $sql, $type = 'OBJECT' ) {
		return array();
	}

	/**
	 * Fetch a column.
	 *
	 * @param string $sql SQL.
	 * @return array
	 */
	public function get_col( $sql ) {
		return array();
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	/**
	 * Retrieves a modified URL query string.
	 *
	 * @param mixed ...$args Query arguments.
	 * @return string
	 */
	function add_query_arg( ...$args ) {
		if ( empty( $args ) ) {
			return '';
		}

		if ( is_array( $args[0] ) ) {
			if ( count( $args ) < 2 || false === $args[1] ) {
				$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
			} else {
				$uri = (string) $args[1];
			}
		} else {
			if ( count( $args ) < 3 || false === $args[2] ) {
				$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
			} else {
				$uri = (string) $args[2];
			}
		}

		$frag = '';
		$pos  = strpos( $uri, '#' );
		if ( false !== $pos ) {
			$frag = substr( $uri, $pos );
			$uri  = substr( $uri, 0, $pos );
		}

		$parts = explode( '?', $uri, 2 );
		$base  = $parts[0];
		$query = array();
		if ( isset( $parts[1] ) ) {
			parse_str( $parts[1], $query );
		}

		if ( is_array( $args[0] ) ) {
			foreach ( $args[0] as $k => $v ) {
				$query[ $k ] = $v;
			}
		} else {
			$query[ $args[0] ] = $args[1];
		}

		$qs = http_build_query( $query );
		return $base . ( '' !== $qs ? '?' . $qs : '' ) . $frag;
	}
}

if ( ! function_exists( 'remove_query_arg' ) ) {
	function remove_query_arg( $keys, $query = false ) {
		if ( false === $query ) {
			$query = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		}
		$frag = '';
		$pos  = strpos( $query, '#' );
		if ( false !== $pos ) {
			$frag  = substr( $query, $pos );
			$query = substr( $query, 0, $pos );
		}
		$parts = explode( '?', $query, 2 );
		$base  = $parts[0];
		$args  = array();
		if ( isset( $parts[1] ) ) {
			parse_str( $parts[1], $args );
		}
		foreach ( (array) $keys as $k ) {
			unset( $args[ $k ] );
		}
		$qs = http_build_query( $args );
		return $base . ( '' !== $qs ? '?' . $qs : '' ) . $frag;
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) {
		return filter_var( (string) $url, FILTER_SANITIZE_URL );
	}
}

if ( ! function_exists( 'shortcode_atts' ) ) {
	/**
	 * Combine user attributes with known attributes.
	 *
	 * @param array  $pairs     Entire list of supported attributes and their defaults.
	 * @param array  $atts      User defined attributes in shortcode tag.
	 * @param string $shortcode Shortcode name.
	 * @return array
	 */
	function shortcode_atts( $pairs, $atts, $shortcode = '' ) {
		$atts = (array) $atts;
		$out  = array();
		foreach ( $pairs as $name => $default ) {
			if ( array_key_exists( $name, $atts ) ) {
				$out[ $name ] = $atts[ $name ];
			} else {
				$out[ $name ] = $default;
			}
		}
		return $out;
	}
}

if ( ! function_exists( 'do_shortcode' ) ) {
	/**
	 * Search content for shortcodes and filter it through their hooks.
	 *
	 * @param string $content     Content.
	 * @param bool   $ignore_html Ignore html.
	 * @return string
	 */
	function do_shortcode( $content, $ignore_html = false ) {
		if ( empty( $GLOBALS['mcr_shortcodes'] ) || ! is_array( $GLOBALS['mcr_shortcodes'] ) ) {
			return $content;
		}

		foreach ( $GLOBALS['mcr_shortcodes'] as $tag => $callback ) {
			$pattern = '/\[' . preg_quote( $tag, '/' ) . '(?:\s+([^\]]*))?\](?:(.+?)\[\/' . preg_quote( $tag, '/' ) . '\])?/s';
			$content = preg_replace_callback( $pattern, function( $m ) use ( $callback, $tag ) {
				$raw_atts = isset( $m[1] ) ? $m[1] : '';
				$atts     = array();
				if ( ! empty( $raw_atts ) ) {
					if ( preg_match_all( '/(\w+)\s*=\s*(?:["\']([^"\']*)["\']|(\S+))/', $raw_atts, $pairs, PREG_SET_ORDER ) ) {
						foreach ( $pairs as $p ) {
							$atts[ $p[1] ] = isset( $p[3] ) && '' !== $p[3] ? $p[3] : ( $p[2] ?? '' );
						}
					}
				}
				$inner = isset( $m[2] ) ? $m[2] : null;
				return (string) call_user_func( $callback, $atts, $inner, $tag );
			}, $content );
		}

		return $content;
	}
}

if ( ! function_exists( 'get_queried_object_id' ) ) {
	/**
	 * Retrieve current queried object id.
	 *
	 * @return int
	 */
	function get_queried_object_id() {
		return 0;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html_e' ) ) {
	function esc_html_e( $text, $domain = 'default' ) {
		echo htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr__' ) ) {
	function esc_attr__( $text, $domain = 'default' ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr_e' ) ) {
	function esc_attr_e( $text, $domain = 'default' ) {
		echo htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_textarea' ) ) {
	function esc_textarea( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( '_n' ) ) {
	function _n( $single, $plural, $number, $domain = 'default' ) {
		return 1 === (int) $number ? $single : $plural;
	}
}

if ( ! function_exists( 'get_user_by' ) ) {
	function get_user_by( $field, $value ) {
		return false;
	}
}

if ( ! function_exists( 'get_userdata' ) ) {
	function get_userdata( $user_id ) {
		return false;
	}
}
