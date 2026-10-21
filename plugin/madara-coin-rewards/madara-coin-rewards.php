<?php
/**
 * Plugin Name:       Madara Coin & Rewards
 * Plugin URI:        https://example.com/madara-coin-rewards
 * Description:       Server-authoritative coin economy for Madara (and any WordPress theme): daily check-in rewards, watch-ads-to-earn, coin purchases, and a fully auditable transaction ledger.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Madara Coin & Rewards
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       madara-coin-rewards
 * Domain Path:       /languages
 *
 * @package MCR
 */

defined( 'ABSPATH' ) || exit;

define( 'MCR_VERSION', '1.0.0' );
define( 'MCR_PLUGIN_FILE', __FILE__ );
define( 'MCR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MCR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MCR_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'MCR_DB_VERSION', '1.0.0' );

/**
 * Minimum WordPress and PHP versions the plugin supports.
 */
define( 'MCR_MIN_WP', '6.0' );
define( 'MCR_MIN_PHP', '7.4' );

/**
 * Bootstrap the plugin.
 *
 * @return void
 */
function mcr_bootstrap() {
	if ( version_compare( PHP_VERSION, MCR_MIN_PHP, '<' ) ) {
		add_action( 'admin_notices', 'mcr_php_version_notice' );
		return;
	}

	if ( version_compare( get_bloginfo( 'version' ), MCR_MIN_WP, '<' ) ) {
		add_action( 'admin_notices', 'mcr_wp_version_notice' );
		return;
	}

	require_once MCR_PLUGIN_DIR . 'includes/Plugin.php';

	MCR\Plugin::instance()->boot();
}
/*
 * The autoloader is registered as soon as this file is included, NOT on
 * `plugins_loaded`.
 *
 * WordPress includes a plugin's main file DURING activation, at which
 * point `plugins_loaded` has already been dispatched. Anything
 * registered on that hook therefore never runs in the activation request,
 * so a class that is only autoloaded from `plugins_loaded` cannot be
 * resolved when the activation hook fires - which is a fatal error:
 *
 *     Error: Class "MCR\Installer" not found
 *     Plugin could not be activated because it triggered a fatal error.
 *
 * Registering here makes the classes resolvable in every request,
 * including the activation request.
 */
require_once MCR_PLUGIN_DIR . 'includes/Autoloader.php';
MCR\Autoloader::register( MCR_PLUGIN_DIR );

add_action( 'plugins_loaded', 'mcr_bootstrap', 5 );

/**
 * Runs when the plugin is activated.
 *
 * The class is required explicitly rather than relying on the
 * autoloader, so activation never depends on hook ordering.
 *
 * @return void
 */
function mcr_activate( $network_wide = false ) {
	require_once MCR_PLUGIN_DIR . 'includes/Installer.php';

	/*
	 * WordPress passes true when the plugin is activated for the whole
	 * network. The tables, the pages and the capabilities then have to be
	 * provisioned on EVERY site of the network, not just the one the
	 * activation was requested from.
	 */
	MCR\Installer::activate( (bool) $network_wide );
}
register_activation_hook( __FILE__, 'mcr_activate' );

/**
 * Runs when the plugin is deactivated.
 *
 * @return void
 */
function mcr_deactivate() {
	require_once MCR_PLUGIN_DIR . 'includes/Installer.php';

	MCR\Installer::deactivate();
}
register_deactivation_hook( __FILE__, 'mcr_deactivate' );

/**
 * Renders an admin notice when PHP is too old.
 *
 * @return void
 */
function mcr_php_version_notice() {
	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		esc_html(
			sprintf(
				/* translators: %s: required PHP version */
				__( 'Madara Coin & Rewards requires PHP %s or newer. The plugin is inactive.', 'madara-coin-rewards' ),
				MCR_MIN_PHP
			)
		)
	);
}

/**
 * Renders an admin notice when WordPress is too old.
 *
 * @return void
 */
function mcr_wp_version_notice() {
	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		esc_html(
			sprintf(
				/* translators: %s: required WordPress version */
				__( 'Madara Coin & Rewards requires WordPress %s or newer. The plugin is inactive.', 'madara-coin-rewards' ),
				MCR_MIN_WP
			)
		)
	);
}
