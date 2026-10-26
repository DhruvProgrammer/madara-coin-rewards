<?php
/**
 * PSR-4 style autoloader for the MCR namespace.
 *
 * @package MCR
 */

namespace MCR;

defined( 'ABSPATH' ) || exit;

/**
 * Maps the `MCR\` namespace onto the plugin's `includes/` directory.
 */
class Autoloader {

	/**
	 * Registered namespace prefix.
	 *
	 * @var string
	 */
	const PREFIX = 'MCR\\';

	/**
	 * Base directory for the namespace.
	 *
	 * @var string
	 */
	private static $base_dir = '';

	/**
	 * Static class map for fast resolution without stat() probes on cold requests.
	 *
	 * @var array<string, string>
	 */
	private static $class_map = array(
		'MCR\\Plugin'                      => 'includes/Plugin.php',
		'MCR\\Installer'                   => 'includes/Installer.php',
		'MCR\\Capabilities'                => 'includes/Capabilities.php',
		'MCR\\Settings'                    => 'includes/Settings.php',
		'MCR\\Ledger'                      => 'includes/Ledger.php',
		'MCR\\Pages'                       => 'includes/Pages.php',
		'MCR\\Cron'                        => 'includes/Cron.php',
		'MCR\\Rest'                        => 'includes/Rest.php',
		'MCR\\Container'                   => 'includes/Container.php',
		'MCR\\Admin\\Admin'                => 'includes/Admin/Admin.php',
		'MCR\\Admin\\DashboardPage'        => 'includes/Admin/DashboardPage.php',
		'MCR\\Admin\\LogsPage'             => 'includes/Admin/LogsPage.php',
		'MCR\\Admin\\PackagesPage'         => 'includes/Admin/PackagesPage.php',
		'MCR\\Admin\\SettingsPage'         => 'includes/Admin/SettingsPage.php',
		'MCR\\Admin\\ToolsPage'            => 'includes/Admin/ToolsPage.php',
		'MCR\\Admin\\TransactionsPage'     => 'includes/Admin/TransactionsPage.php',
		'MCR\\Admin\\UsersPage'            => 'includes/Admin/UsersPage.php',
		'MCR\\AdProviders\\GenericS2S'     => 'includes/AdProviders/GenericS2S.php',
		'MCR\\AdProviders\\Provider'       => 'includes/AdProviders/Provider.php',
		'MCR\\AdProviders\\Registry'       => 'includes/AdProviders/Registry.php',
		'MCR\\Gateways\\Gateway'           => 'includes/Gateways/Gateway.php',
		'MCR\\Gateways\\Manual'            => 'includes/Gateways/Manual.php',
		'MCR\\Gateways\\Registry'          => 'includes/Gateways/Registry.php',
		'MCR\\Gateways\\Stripe'            => 'includes/Gateways/Stripe.php',
		'MCR\\Gateways\\WooCommerce'       => 'includes/Gateways/WooCommerce.php',
		'MCR\\Integrations\\FullWidth'     => 'includes/Integrations/FullWidth.php',
		'MCR\\Integrations\\Madara'        => 'includes/Integrations/Madara.php',
		'MCR\\Integrations\\Privacy'       => 'includes/Integrations/Privacy.php',
		'MCR\\Integrations\\Profile'       => 'includes/Integrations/Profile.php',
		'MCR\\Ledger\\BalanceMath'         => 'includes/Ledger/BalanceMath.php',
		'MCR\\Ledger\\LedgerInterface'     => 'includes/Ledger/LedgerInterface.php',
		'MCR\\Payments\\Orders'            => 'includes/Payments/Orders.php',
		'MCR\\Payments\\PackageCalculator' => 'includes/Payments/PackageCalculator.php',
		'MCR\\Payments\\Packages'          => 'includes/Payments/Packages.php',
		'MCR\\Payments\\PaymentResult'     => 'includes/Payments/PaymentResult.php',
		'MCR\\Rewards\\AdRewardPolicy'     => 'includes/Rewards/AdRewardPolicy.php',
		'MCR\\Rewards\\AdSessions'         => 'includes/Rewards/AdSessions.php',
		'MCR\\Rewards\\CheckIn'            => 'includes/Rewards/CheckIn.php',
		'MCR\\Rewards\\CheckInPolicy'      => 'includes/Rewards/CheckInPolicy.php',
		'MCR\\Rewards\\StreakCalculator'   => 'includes/Rewards/StreakCalculator.php',
		'MCR\\Support\\Clock'              => 'includes/Support/Clock.php',
		'MCR\\Support\\FixedClock'         => 'includes/Support/FixedClock.php',
		'MCR\\Support\\RateLimiter'        => 'includes/Support/RateLimiter.php',
		'MCR\\Support\\Secrets'            => 'includes/Support/Secrets.php',
		'MCR\\Support\\WpClock'            => 'includes/Support/WpClock.php',
		'MCR\\Templates\\Assets'           => 'includes/Templates/Assets.php',
		'MCR\\Templates\\Blocks'           => 'includes/Templates/Blocks.php',
		'MCR\\Templates\\Shortcodes'       => 'includes/Templates/Shortcodes.php',
		'MCR\\Templates\\Templates'        => 'includes/Templates/Templates.php',
		'MCR\\Widgets\\BalanceWidget'      => 'includes/Widgets/BalanceWidget.php',
	);

	/**
	 * Whether the autoloader has been registered.
	 *
	 * @var bool
	 */
	private static $registered = false;

	/**
	 * Registers the autoloader.
	 *
	 * @param string $base_dir Absolute path to the plugin root.
	 * @return void
	 */
	public static function register( $base_dir ) {
		self::$base_dir = rtrim( $base_dir, '/\\' ) . '/';

		if ( self::$registered ) {
			return;
		}

		spl_autoload_register( array( __CLASS__, 'load' ) );
		self::$registered = true;
	}

	/**
	 * Loads a class file for the MCR namespace.
	 *
	 * @param string $class_name Fully qualified class name.
	 * @return void
	 */
	public static function load( $class_name ) {
		if ( 0 !== strpos( $class_name, self::PREFIX ) ) {
			return;
		}

		if ( isset( self::$class_map[ $class_name ] ) ) {
			$file = self::$base_dir . self::$class_map[ $class_name ];
			require_once $file;
			return;
		}

		$relative = substr( $class_name, strlen( self::PREFIX ) );
		$relative = str_replace( '\\', '/', $relative );

		// Guard against traversal attempts inside the namespace.
		if ( false !== strpos( $relative, '..' ) ) {
			return;
		}

		$file = self::$base_dir . 'includes/' . $relative . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}
