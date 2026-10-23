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
 * Registers the autoloader so the MCR namespace resolves.
 *
 * This MUST run at file scope rather than inside the plugins_loaded
 * callback: WordPress includes a plugin file during activation and
 * deactivation *after* plugins_loaded has already fired, so anything
 * deferred to that hook would leave the activation/deactivation
 * callbacks (MCR\Installer) unresolvable and fatal with a TypeError.
 *
 * @return void
 */
function mcr_register_autoloader() {
	require_once MCR_PLUGIN_DIR . 'includes/Autoloader.php';
	MCR\Autoloader::register( MCR_PLUGIN_DIR );
}
mcr_register_autoloader();

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
add_action( 'plugins_loaded', 'mcr_bootstrap', 5 );

register_activation_hook( __FILE__, array( 'MCR\Installer', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'MCR\Installer', 'deactivate' ) );

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
