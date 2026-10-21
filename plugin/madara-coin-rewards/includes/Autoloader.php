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
