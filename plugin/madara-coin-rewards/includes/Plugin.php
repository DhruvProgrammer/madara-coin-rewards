<?php
/**
 * Plugin bootstrap.
 *
 * @package MCR
 */

namespace MCR;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the plugin together.
 */
class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Whether boot() has run.
	 *
	 * @var bool
	 */
	private $booted = false;

	/**
	 * Returns the shared instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Registers every hook the plugin needs.
	 *
	 * No plugin table is queried here. Admin-only classes are only
	 * instantiated when the request is actually an admin request.
	 *
	 * @return void
	 */
	public function boot() {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		require_once MCR_PLUGIN_DIR . 'includes/functions.php';

		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'plugins_loaded', array( Installer::class, 'maybe_upgrade' ), 20 );

		// The REST API is the data layer for the frontend.
		Rest::instance()->register();

		// Payment gateways and ad providers register their own REST routes
		// when they are loaded, so they must be loaded before rest_api_init.
		Gateways\Registry::load();
		AdProviders\Registry::load();

		// Template rendering and shortcodes.
		Templates\Shortcodes::instance()->register();
		Templates\Blocks::instance()->register();

		// The coin balance widget.
		add_action( 'widgets_init', array( $this, 'register_widgets' ) );

		// Cron maintenance.
		Cron::register();

		// The theme bridge lets the plugin adopt the site's accent colour.
		add_action( 'wp_head', array( Integrations\Madara::class, 'print_theme_bridge' ), 20 );

		// User profile panel.
		add_action( 'show_user_profile', array( Integrations\Profile::class, 'render_profile_panel' ) );
		add_action( 'edit_user_profile', array( Integrations\Profile::class, 'render_profile_panel' ) );
		add_action( 'personal_options_update', array( Integrations\Profile::class, 'save_profile_panel' ) );
		add_action( 'edit_user_profile_update', array( Integrations\Profile::class, 'save_profile_panel' ) );

		// Privacy exports and erasures.
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_privacy_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_privacy_eraser' ) );

		// Admin screens.
		if ( is_admin() ) {
			Admin\Admin::init();
		}

		/**
		 * Fires once the plugin has booted.
		 *
		 * @param Plugin $plugin The plugin instance.
		 */
		do_action( 'mcr_booted', $this );
	}

	/**
	 * Registers the plugin's widgets.
	 *
	 * @return void
	 */
	public function register_widgets() {
		require_once ABSPATH . 'wp-admin/includes/widgets.php';

		register_widget( '\MCR\Widgets\BalanceWidget' );
	}

	/**
	 * Loads the plugin translations.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'madara-coin-rewards',
			false,
			dirname( MCR_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Registers the personal data exporter.
	 *
	 * @param array $exporters Registered exporters.
	 * @return array
	 */
	public function register_privacy_exporter( $exporters ) {
		$exporters['madara-coin-rewards'] = array(
			'exporter_friendly_name' => __( 'Madara Coins', 'madara-coin-rewards' ),
			'callback'               => array( Integrations\Privacy::class, 'export' ),
		);

		return $exporters;
	}

	/**
	 * Registers the personal data eraser.
	 *
	 * @param array $erasers Registered erasers.
	 * @return array
	 */
	public function register_privacy_eraser( $erasers ) {
		$erasers['madara-coin-rewards'] = array(
			'eraser_friendly_name' => __( 'Madara Coins', 'madara-coin-rewards' ),
			'callback'             => array( Integrations\Privacy::class, 'erase' ),
		);

		return $erasers;
	}
}
