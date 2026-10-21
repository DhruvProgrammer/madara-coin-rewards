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
 * dbDelta stub: records the schema statements.
 *
 * @param array|string $queries Queries.
 * @return array
 */
function dbDelta( $queries ) {
$queries = (array) $queries;

foreach ( $queries as $query ) {
if ( is_string( $query ) && false !== stripos( $query, 'CREATE TABLE' ) ) {
$GLOBALS['mcr_dbdelta'][] = $query;
}
}

return array();
}
/**
 * Render the settings fields for a page.
 *
 * @return void
 */
function do_settings_fields( $page = '' ) {}

/**
 * The WordPress list table base class.
 *
 * Only the members the plugin's table uses are implemented.
 */
class WP_List_Table {

	/**
	 * Column headers.
	 *
	 * @var array
	 */
	public $_column_headers = array();

	/**
	 * Items.
	 *
	 * @var array
	 */
	public $_items = array();

	/**
	 * Total items.
	 *
	 * @var int
	 */
	protected $_total_items = 0;

	/**
	 * Per page.
	 *
	 * @var int
	 */
	protected $_per_page = 20;

	/**
	 * Constructor.
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = array() ) {
		if ( isset( $args['singular'] ) ) {
			$this->singular = $args['singular'];
		}

		if ( isset( $args['plural'] ) ) {
			$this->plural = $args['plural'];
		}

		if ( isset( $args['ajax'] ) ) {
			$this->ajax = (bool) $args['ajax'];
		}
	}

	/**
	 * The singular label.
	 *
	 * @var string
	 */
	public $singular = '';

	/**
	 * The plural label.
	 *
	 * @var string
	 */
	public $plural = '';

	/**
	 * Whether the table uses AJAX.
	 *
	 * @var bool
	 */
	public $ajax = false;

	/**
	 * Sets the pagination arguments.
	 *
	 * @param array $args Arguments.
	 * @return void
	 */
	public function set_pagination_args( $args ) {
		if ( isset( $args['total_items'] ) ) {
			$this->_total_items = (int) $args['total_items'];
		}

		if ( isset( $args['per_page'] ) ) {
			$this->_per_page = (int) $args['per_page'];
		}
	}

	/**
	 * The current page number.
	 *
	 * @return int
	 */
	public function get_pagenum() {
		$paged = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;

		return max( 1, $paged );
	}

	/**
	 * The total number of items.
	 *
	 * @return int
	 */
	public function get_total_items() {
		return $this->_total_items;
	}

	/**
	 * The columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array();
	}

	/**
	 * The sortable columns.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return array();
	}

	/**
	 * Prepares the items.
	 *
	 * @return void
	 */
	public function prepare_items() {}

	/**
	 * The default column renderer.
	 *
	 * @param array  $item    Item.
	 * @param string $column_name Column.
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '';
	}

	/**
	 * Renders the table.
	 *
	 * @return void
	 */
	public function display() {
		echo '<table class="wp-list-table widefat fixed striped"><tbody>';

		foreach ( (array) $this->_items as $item ) {
			$this->single_row( $item );
		}

		echo '</tbody></table>';
	}

	/**
	 * Renders one row.
	 *
	 * @param array $item Item.
	 * @return void
	 */
	public function single_row( $item ) {
		echo '<tr><td>' . $this->column_default( $item, 'id' ) . '</td></tr>';
	}

	/**
	 * The "no items" message.
	 *
	 * @return void
	 */
	public function no_items() {
		echo '<tr><td colspan="10">No items found.</td></tr>';
	}
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

/**
 * Nonce verification.
 *
 * @param string $nonce  Nonce.
 * @param string $action Action.
 * @return bool
 */

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
	// A test can flip this to exercise the admin-only code paths.
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

/**
 * Option delete.
 *
 * @param string $name Option name.
 * @return bool
 */

/**
 * Option add.
 *
 * @param string $name  Option name.
 * @param mixed  $value Value.
 * @return bool
 */

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
	/*
	 * A test can set the current screen's id to exercise the code that
	 * has to know which screen it is on.
	 */
	if ( empty( $GLOBALS['mcr_screen_id'] ) ) {
		return null;
	}

	return (object) array(
		'id' => (string) $GLOBALS['mcr_screen_id'],
	);
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

/**
 * Localize a script.
 *
 * @return bool

/**
 * Enqueue a script.
 *
 * @return void

/**
 * Register a REST route.
 *
 * @return void
 */
function register_rest_route() {}

/**
 * Safe redirect.
 *
 * @param string $location Location.
 * @return bool
 */
function wp_safe_redirect( $location ) {
	return true;
}

/**
 * Abort.
 *
 * @param string $message Message.
 * @return void
 */

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
		/*
		 * WordPress accepts EITHER variadic arguments or a single array of
		 * arguments. The plugin uses the array form, so it must be supported
		 * exactly as WordPress does.
		 */
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
