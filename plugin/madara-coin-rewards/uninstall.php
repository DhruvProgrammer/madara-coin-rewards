<?php
/**
 * Uninstall handler.
 *
 * Runs when the plugin is deleted from the WordPress plugins screen.
 * Data is only removed when the administrator explicitly opted in via the
 * Tools screen, so an accidental delete never destroys a coin economy.
 *
 * @package MCR
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$mcr_option = get_option( 'mcr_settings', array() );

$mcr_purge = is_array( $mcr_option ) && isset( $mcr_option['uninstall.purge_data'] ) && 'on' === $mcr_option['uninstall.purge_data'];

if ( $mcr_purge ) {
	global $wpdb;

	$mcr_tables = array( 'balances', 'transactions', 'checkins', 'ad_sessions', 'payments', 'claims' );

	foreach ( $mcr_tables as $mcr_table ) {
		$mcr_name = $wpdb->prefix . 'mcr_' . $mcr_table;
		$wpdb->query( "DROP TABLE IF EXISTS `{$mcr_name}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
	}

	delete_option( 'mcr_settings' );
	delete_option( 'mcr_db_version' );
	delete_option( 'mcr_data_version' );
	delete_option( 'mcr_engine_warning' );
	delete_option( 'mcr_activation_redirect' );
	delete_option( 'mcr_dashboard_stats' );

	$mcr_roles = array( 'administrator' );

	foreach ( $mcr_roles as $mcr_role_name ) {
		$mcr_role = get_role( $mcr_role_name );

		if ( ! $mcr_role ) {
			continue;
		}

		foreach ( array( 'mcr_manage_settings', 'mcr_manage_coins', 'mcr_view_users' ) as $mcr_cap ) {
			$mcr_role->remove_cap( $mcr_cap );
		}
	}

	wp_clear_scheduled_hook( 'mcr_daily_maintenance' );
	wp_clear_scheduled_hook( 'mcr_hourly_maintenance' );
}
