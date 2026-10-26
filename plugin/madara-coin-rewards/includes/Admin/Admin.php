<?php
/**
 * Madara Coin & Rewards admin panel.
 *
 * Menu wiring, capability checks, and page routing.
 *
 * @package MCR
 */

namespace MCR\Admin;

defined( 'ABSPATH' ) || exit;

use MCR\Capabilities;
use MCR\Ledger;
use MCR\Settings;

/**
 * Registers the Madara Coins admin menu, guards every request with capability
 * checks, registers the WordPress settings, and routes requests to the page
 * classes.
 *
 * Every admin action verifies a nonce and a capability before proceeding.
 */
class Admin {

	/**
	 * Top-level menu slug.
	 */
	const MENU_SLUG = 'mcr-rewards';

	/**
	 * Page classes (lazy instantiated).
	 *
	 * @var object|null
	 */
	private $settings_page = null;
	private $packages_page = null;
	private $users_page = null;
	private $transactions_page = null;
	private $dashboard_page = null;
	private $logs_page = null;
	private $tools_page = null;

	/**
	 * Registers admin hooks.
	 *
	 * @return void
	 */
	public static function init() {
		$admin = new self();

		add_action( 'admin_menu', array( $admin, 'admin_menu' ) );
		add_action( 'admin_init', array( $admin, 'admin_init' ) );
		add_action( 'admin_notices', array( $admin, 'admin_notices' ) );

		/*
		 * The <body> carries the "mcr-admin" class on the plugin's own
		 * screens, so the stylesheet can tell them apart from the rest
		 * of the admin.
		 */
		add_filter( 'admin_body_class', array( $admin, 'admin_body_class' ) );
	}

	/**
	 * Whether the request is one of the plugin's own admin screens.
	 *
	 * The top-level screen is "toplevel_page_mcr-rewards" and the
	 * submenu screens are "mcr-rewards_page_mcr-dashboard" and so on.
	 *
	 * @return bool
	 */
	public static function is_plugin_screen() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();

		if ( ! is_object( $screen ) || empty( $screen->id ) ) {
			return false;
		}

		$id = (string) $screen->id;

		if ( 'toplevel_page_' . self::MENU_SLUG === $id ) {
			return true;
		}

		return 0 === strpos( $id, self::MENU_SLUG . '_page_mcr-' );
	}

	/**
	 * Adds the "mcr-admin" class to the <body> on the plugin's screens.
	 *
	 * @param string $classes The body classes.
	 * @return string
	 */
	public function admin_body_class( $classes ) {
		if ( ! self::is_plugin_screen() ) {
			return $classes;
		}

		return trim( (string) $classes . ' mcr-admin' );
	}

	/**
	 * Adds the top-level menu and all submenu pages.
	 *
	 * @return void
	 */
	public function admin_menu() {
		/*
		 * A user who may manage the coins has to be able to REACH the
		 * screens. The menu used to require the settings capability, so
		 * a coin manager saw no menu at all.
		 */
		if ( ! Capabilities::can_manage_settings() && ! Capabilities::can_manage_coins() ) {
			return;
		}

		add_menu_page(
			__( 'Madara Coins', 'madara-coin-rewards' ),
			__( 'Madara Coins', 'madara-coin-rewards' ),
			Capabilities::MANAGE_COINS,
			self::MENU_SLUG,
			array( $this, 'render_dashboard' ),
			'dashicons-chart-line',
			56
		);

		$parent = self::MENU_SLUG;

		add_submenu_page(
			$parent,
			__( 'Dashboard', 'madara-coin-rewards' ),
			__( 'Dashboard', 'madara-coin-rewards' ),
			Capabilities::MANAGE_SETTINGS,
			'mcr-dashboard',
			array( $this, 'render_dashboard' )
		);

		add_submenu_page(
			$parent,
			__( 'Settings', 'madara-coin-rewards' ),
			__( 'Settings', 'madara-coin-rewards' ),
			Capabilities::MANAGE_SETTINGS,
			'mcr-settings',
			array( $this, 'render_settings' )
		);

		add_submenu_page(
			$parent,
			__( 'Packages', 'madara-coin-rewards' ),
			__( 'Packages', 'madara-coin-rewards' ),
			Capabilities::MANAGE_COINS,
			'mcr-packages',
			array( $this, 'render_packages' )
		);

		add_submenu_page(
			$parent,
			__( 'Users', 'madara-coin-rewards' ),
			__( 'Users', 'madara-coin-rewards' ),
			Capabilities::VIEW_USERS,
			'mcr-users',
			array( $this, 'render_users' )
		);

		add_submenu_page(
			$parent,
			__( 'Transactions', 'madara-coin-rewards' ),
			__( 'Transactions', 'madara-coin-rewards' ),
			Capabilities::MANAGE_COINS,
			'mcr-transactions',
			array( $this, 'render_transactions' )
		);

		add_submenu_page(
			$parent,
			__( 'Logs', 'madara-coin-rewards' ),
			__( 'Logs', 'madara-coin-rewards' ),
			Capabilities::MANAGE_COINS,
			'mcr-logs',
			array( $this, 'render_logs' )
		);

		add_submenu_page(
			$parent,
			__( 'Tools', 'madara-coin-rewards' ),
			__( 'Tools', 'madara-coin-rewards' ),
			Capabilities::MANAGE_COINS,
			'mcr-tools',
			array( $this, 'render_tools' )
		);
	}

	/**
	 * Registers the settings and dispatches incoming admin POST actions.
	 *
	 * @return void
	 */
	public function admin_init() {
		if ( ! Capabilities::can_manage_settings() ) {
			return;
		}

		register_setting(
			'mcr_settings_group',
			'mcr_settings',
			array(
				'sanitize_callback' => array( 'MCR\Settings', 'sanitize' ),
				/*
				 * The group is saved by the Settings API, which checks
				 * this capability. Without it only manage_options users
				 * could save the form, and the plugin's own capability
				 * was ignored.
				 */
				'capability'        => \MCR\Capabilities::MANAGE_SETTINGS,
			)
		);

		add_filter(
			'option_page_capability_mcr_settings_group',
			array( $this, 'filter_settings_capability' )
		);

		add_action(
			'admin_post_mcr_export_transactions',
			array( \MCR\Admin\TransactionsPage::class, 'export_via_admin_post' )
		);

		$this->register_settings_sections();

		$this->maybe_handle_actions();
	}

	/**
	 * Registers the settings sections and fields for the Settings API.
	 *
	 * @return void
	 */
	private function register_settings_sections() {
		$settings_page = $this->settings_page();
		$settings_page->register_sections_and_fields();
	}

	/**
	 * Returns the capability required to save the settings form.
	 *
	 * @return string
	 */
	public function filter_settings_capability() {
		return \MCR\Capabilities::MANAGE_SETTINGS;
	}

	/**
	 * Outputs admin notices (engine warning and transient action results).
	 *
	 * @return void
	 */
	public function admin_notices() {
		$warning = get_option( 'mcr_engine_warning' );
		if ( $warning ) {
			printf(
				'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
				esc_html( $warning )
			);
		}

		$notice = get_transient( 'mcr_admin_notice' );
		if ( $notice ) {
			delete_transient( 'mcr_admin_notice' );
			$class = isset( $notice['class'] ) ? esc_attr( $notice['class'] ) : 'notice-info';
			printf(
				'<div class="notice %s is-dismissible"><p>%s</p></div>',
				$class,
				esc_html( $notice['message'] )
			);
		}
	}

	/**
	 * Dispatches POSTed admin actions.
	 *
	 * Every action verifies a nonce and a capability before proceeding.
	 *
	 * @return void
	 */
	private function maybe_handle_actions() {
		if ( empty( $_POST['mcr_action'] ) || ! isset( $_POST['_wpnonce'] ) ) {
			return;
		}

		$action = sanitize_key( wp_unslash( $_POST['mcr_action'] ) );

		check_admin_referer( 'mcr_admin_action' );

		switch ( $action ) {
			case 'mcr_grant_deduct':
				$this->handle_grant_deduct();
				break;
			case 'mcr_reconcile':
				$this->handle_reconcile();
				break;
			case 'mcr_reconcile_all':
				$this->handle_reconcile_all();
				break;
			case 'mcr_export_settings':
				$this->handle_export_settings();
				break;
			case 'mcr_reset_user':
				$this->handle_reset_user();
				break;
			case 'mcr_uninstall':
				$this->handle_uninstall();
				break;
		}
	}

	/**
	 * Stores a transient admin notice shown after the next redirect.
	 *
	 * @param string $message Notice message.
	 * @param string $class   Notice CSS class.
	 * @return void
	 */
	private function set_notice( $message, $class = 'notice-info' ) {
		set_transient( 'mcr_admin_notice', array( 'message' => $message, 'class' => $class ), 30 );
	}

	/**
	 * Redirects back to the referring admin page.
	 *
	 * @return void
	 */
	private function redirect_back() {
		$url = wp_get_referer();
		if ( ! $url ) {
			$url = admin_url( 'admin.php?page=' . self::MENU_SLUG );
		}
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Handles grant/deduct of coins to/from a user.
	 *
	 * Writes through MCR\Ledger::credit()/debit() with type 'admin_adjust' and
	 * a required reason so the ledger stays auditable.
	 *
	 * @return void
	 */
	private function handle_grant_deduct() {
		if ( ! Capabilities::can_manage_coins() ) {
			wp_die( __( 'Insufficient permissions.', 'madara-coin-rewards' ) );
		}

		$user_id   = absint( $_POST['user_id'] ?? 0 );
		$amount    = absint( $_POST['amount'] ?? 0 );
		$operation = sanitize_key( wp_unslash( $_POST['operation'] ?? 'grant' ) );
		$reason    = sanitize_text_field( wp_unslash( $_POST['reason'] ?? '' ) );

		if ( ! $user_id || ! $amount || empty( $reason ) ) {
			$this->set_notice( __( 'User ID, amount, and reason are required.', 'madara-coin-rewards' ), 'notice-error' );
			$this->redirect_back();
		}

		if ( ! user_exists( $user_id ) ) {
			$this->set_notice( __( 'User not found.', 'madara-coin-rewards' ), 'notice-error' );
			$this->redirect_back();
		}

		if ( ! in_array( $operation, array( 'grant', 'deduct' ), true ) ) {
			$this->set_notice( __( 'Invalid operation.', 'madara-coin-rewards' ), 'notice-error' );
			$this->redirect_back();
		}

		$ledger = Ledger::instance();
		$ctx    = array(
			'source'           => 'wp-admin',
			'ref_type'         => 'admin_adjust',
			'ref_id'           => $user_id,
			'idempotency_key'  => 'admin_adjust:' . $user_id . ':' . current_time( 'mysql', true ) . ':' . uniqid( '', true ),
		);

		if ( 'deduct' === $operation ) {
			$result = $ledger->debit( $user_id, $amount, 'admin_adjust', $reason, $ctx );
		} else {
			$result = $ledger->credit( $user_id, $amount, 'admin_adjust', $reason, $ctx );
		}

		if ( is_wp_error( $result ) ) {
			$this->set_notice( $result->get_error_message(), 'notice-error' );
		} else {
			$verb = ( 'deduct' === $operation ) ? __( 'deducted from', 'madara-coin-rewards' ) : __( 'credited to', 'madara-coin-rewards' );
			$this->set_notice(
				sprintf(
					/* translators: %d: coins, %s: verb, %d: user id */
					__( '%d coins %s user %d.', 'madara-coin-rewards' ),
					$amount,
					$verb,
					$user_id
				)
			);
		}

		$this->redirect_back();
	}

	/**
	 * Recomputes a user's balance from the ledger (admin repair tool).
	 *
	 * @return void
	 */
	private function handle_reconcile() {
		if ( ! Capabilities::can_manage_coins() ) {
			wp_die( __( 'Insufficient permissions.', 'madara-coin-rewards' ) );
		}

		$user_id = absint( $_POST['user_id'] ?? 0 );
		if ( ! $user_id || ! user_exists( $user_id ) ) {
			$this->set_notice( __( 'User not found.', 'madara-coin-rewards' ), 'notice-error' );
			$this->redirect_back();
		}

		$result = Ledger::instance()->reconcile_user( $user_id );

		if ( $result['fixed'] ) {
			$this->set_notice(
				sprintf(
					/* translators: 1: previous balance, 2: recomputed balance */
					__( 'Balance reconciled: %1$d corrected to %2$d.', 'madara-coin-rewards' ),
					$result['previous'],
					$result['recomputed']
				)
			);
		} else {
			$this->set_notice( __( 'Balance already matches the ledger. No changes needed.', 'madara-coin-rewards' ) );
		}

		$this->redirect_back();
	}

	/**
	 * Recomputes all user balances from the ledger (admin repair tool).
	 *
	 * @return void
	 */
	private function handle_reconcile_all() {
		if ( ! Capabilities::can_manage_coins() ) {
			wp_die( __( 'Insufficient permissions.', 'madara-coin-rewards' ) );
		}

		global $wpdb;
		$user_ids = $wpdb->get_col(
			'SELECT DISTINCT user_id FROM ' . $wpdb->prefix . 'mcr_balances UNION SELECT DISTINCT user_id FROM ' . $wpdb->prefix . 'mcr_transactions'
		);

		$fixed_count = 0;
		if ( ! empty( $user_ids ) ) {
			$ledger = Ledger::instance();
			foreach ( $user_ids as $uid ) {
				$res = $ledger->reconcile_user( absint( $uid ) );
				if ( ! is_wp_error( $res ) && ! empty( $res['fixed'] ) ) {
					$fixed_count++;
				}
			}
		}

		$this->set_notice(
			sprintf(
				/* translators: 1: fixed count, 2: total users */
				__( 'Reconciliation complete. Fixed %1$d of %2$d user balances.', 'madara-coin-rewards' ),
				$fixed_count,
				count( (array) $user_ids )
			)
		);

		$this->redirect_back();
	}

	/**
	 * Exports plugin settings as a JSON file.
	 *
	 * @return void
	 */
	private function handle_export_settings() {
		if ( ! Capabilities::can_manage_settings() ) {
			wp_die( __( 'Insufficient permissions.', 'madara-coin-rewards' ) );
		}

		$settings = Settings::all();
		$json     = wp_json_encode( $settings, \JSON_PRETTY_PRINT );

		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="mcr-settings-' . gmdate( 'Y-m-d' ) . '.json"' );
		echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Resets a user's balance to zero with a required reason.
	 *
	 * @return void
	 */
	private function handle_reset_user() {
		if ( ! Capabilities::can_manage_coins() ) {
			wp_die( __( 'Insufficient permissions.', 'madara-coin-rewards' ) );
		}

		$user_id = absint( $_POST['user_id'] ?? 0 );
		if ( ! $user_id || ! user_exists( $user_id ) ) {
			$this->set_notice( __( 'User not found.', 'madara-coin-rewards' ), 'notice-error' );
			$this->redirect_back();
		}

		$reason = sanitize_text_field( wp_unslash( $_POST['reason'] ?? '' ) );
		if ( empty( $reason ) ) {
			$this->set_notice( __( 'A reason is required to reset a balance.', 'madara-coin-rewards' ), 'notice-error' );
			$this->redirect_back();
		}

		$balance = Ledger::instance()->get_balance( $user_id );
		if ( $balance > 0 ) {
			Ledger::instance()->debit(
				$user_id,
				$balance,
				'admin_adjust',
				__( 'Reset to zero', 'madara-coin-rewards' ) . ': ' . $reason,
				array(
					'source'           => 'wp-admin',
					'ref_type'         => 'admin_adjust',
					'ref_id'           => $user_id,
					'idempotency_key'  => 'admin_reset:' . $user_id . ':' . current_time( 'mysql', true ) . ':' . uniqid( '', true ),
				)
			);
		}

		$this->set_notice( __( 'User balance reset to zero.', 'madara-coin-rewards' ) );
		$this->redirect_back();
	}

	/**
	 * Clears plugin settings and transients (uninstall helper).
	 *
	 * @return void
	 */
	private function handle_uninstall() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Insufficient permissions.', 'madara-coin-rewards' ) );
		}

		$confirm = sanitize_text_field( wp_unslash( $_POST['confirm'] ?? '' ) );
		if ( 'yes-delete-everything' !== $confirm ) {
			$this->set_notice( __( 'Uninstall confirmation was missing.', 'madara-coin-rewards' ), 'notice-error' );
			$this->redirect_back();
		}

		delete_option( 'mcr_settings' );
		delete_option( 'mcr_db_version' );
		delete_option( 'mcr_data_version' );
		delete_option( 'mcr_engine_warning' );
		delete_transient( 'mcr_admin_notice' );
		delete_transient( 'mcr_dashboard_stats' );

		Capabilities::remove_from_roles();

		$this->set_notice(
			__( 'Plugin settings cleared. Database tables are preserved unless you remove them via uninstall.php.', 'madara-coin-rewards' )
		);
		$this->redirect_back();
	}

	/**
	 * @return SettingsPage
	 */
	private function settings_page() {
		if ( null === $this->settings_page ) {
			$this->settings_page = new SettingsPage();
		}

		return $this->settings_page;
	}

	/**
	 * @return PackagesPage
	 */
	private function packages_page() {
		if ( null === $this->packages_page ) {
			$this->packages_page = new PackagesPage();
		}

		return $this->packages_page;
	}

	/**
	 * @return UsersPage
	 */
	private function users_page() {
		if ( null === $this->users_page ) {
			$this->users_page = new UsersPage();
		}

		return $this->users_page;
	}

	/**
	 * @return TransactionsPage
	 */
	private function transactions_page() {
		if ( null === $this->transactions_page ) {
			$this->transactions_page = new TransactionsPage();
		}

		return $this->transactions_page;
	}

	/**
	 * @return DashboardPage
	 */
	private function dashboard_page() {
		if ( null === $this->dashboard_page ) {
			$this->dashboard_page = new DashboardPage();
		}

		return $this->dashboard_page;
	}

	/**
	 * @return LogsPage
	 */
	private function logs_page() {
		if ( null === $this->logs_page ) {
			$this->logs_page = new LogsPage();
		}

		return $this->logs_page;
	}

	/**
	 * @return ToolsPage
	 */
	private function tools_page() {
		if ( null === $this->tools_page ) {
			$this->tools_page = new ToolsPage();
		}

		return $this->tools_page;
	}

	// Page rendering --------------------------------------------------------

	public function render_dashboard() {
		$this->dashboard_page()->render();
	}

	public function render_settings() {
		$this->settings_page()->render();
	}

	public function render_packages() {
		$this->packages_page()->render();
	}

	public function render_users() {
		$this->users_page()->render();
	}

	public function render_transactions() {
		$this->transactions_page()->render();
	}

	public function render_logs() {
		$this->logs_page()->render();
	}

	public function render_tools() {
		$this->tools_page()->render();
	}
}
