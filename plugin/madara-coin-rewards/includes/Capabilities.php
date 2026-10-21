<?php
/**
 * Custom capabilities.
 *
 * @package MCR
 */

namespace MCR;

defined( 'ABSPATH' ) || exit;

/**
 * Manages the plugin's custom capabilities.
 */
class Capabilities {

	/**
	 * Manage the plugin settings.
	 */
	const MANAGE_SETTINGS = 'mcr_manage_settings';

	/**
	 * View and adjust user balances.
	 */
	const MANAGE_COINS = 'mcr_manage_coins';

	/**
	 * View other users' reward data.
	 */
	const VIEW_USERS = 'mcr_view_users';

	/**
	 * All capabilities the plugin defines.
	 *
	 * @return array
	 */
	public static function all() {
		return array(
			self::MANAGE_SETTINGS,
			self::MANAGE_COINS,
			self::VIEW_USERS,
		);
	}

	/**
	 * Adds the capabilities to the administrator role.
	 *
	 * @return void
	 */
	public static function add_to_roles() {
		$admin = get_role( 'administrator' );

		if ( ! $admin ) {
			return;
		}

		foreach ( self::all() as $cap ) {
			if ( ! $admin->has_cap( $cap ) ) {
				$admin->add_cap( $cap );
			}
		}
	}

	/**
	 * Removes the capabilities from the administrator role.
	 *
	 * @return void
	 */
	public static function remove_from_roles() {
		$admin = get_role( 'administrator' );

		if ( ! $admin ) {
			return;
		}

		foreach ( self::all() as $cap ) {
			$admin->remove_cap( $cap );
		}
	}

	/**
	 * Whether the current user may manage plugin settings.
	 *
	 * @return bool
	 */
	public static function can_manage_settings() {
		return current_user_can( self::MANAGE_SETTINGS ) || current_user_can( 'manage_options' );
	}

	/**
	 * Whether the current user may adjust balances.
	 *
	 * @return bool
	 */
	public static function can_manage_coins() {
		return current_user_can( self::MANAGE_COINS ) || current_user_can( 'manage_options' );
	}

	/**
	 * Whether the current user may view other users' reward data.
	 *
	 * @return bool
	 */
	public static function can_view_users() {
		return current_user_can( self::VIEW_USERS ) || current_user_can( 'manage_options' );
	}
}
