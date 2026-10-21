<?php

defined( 'ABSPATH' ) || exit;
/**
 * Inspects the plugin's database state on the live WordPress install.
 *
 * Run with: wp eval-file scripts/inspect-db.php --path=<wordpress-root>
 *
 * @package MCR
 */

global $wpdb;

$rows = $wpdb->get_col(
	"SELECT name FROM sqlite_master WHERE type = 'table' AND name LIKE '%mcr%' ORDER BY name"
);

echo "Tables created by the plugin:\n";

if ( empty( $rows ) ) {
	echo "  (none - the installer did not run)\n";
}

foreach ( (array) $rows as $name ) {
	$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$name}`" );

	echo "  {$name}  rows={$count}\n";
}

echo "\nOptions:\n";

foreach ( array( 'mcr_db_version', 'mcr_version', 'mcr_settings' ) as $option ) {
	$value = get_option( $option );

	if ( false === $value ) {
		echo "  {$option}: (not set)\n";
		continue;
	}

	if ( is_array( $value ) ) {
		echo "  {$option}: array with " . count( $value ) . " keys\n";
		continue;
	}

	echo "  {$option}: {$value}\n";
}

echo "\nCapabilities registered:\n";

$caps = get_option( 'mcr_capabilities', array() );

echo '  ' . ( empty( $caps ) ? '(none stored)' : implode( ', ', (array) $caps ) ) . "\n";

echo "\nPlugin status:\n";

$plugin = get_plugin_data( WP_PLUGIN_DIR . '/madara-coin-rewards/madara-coin-rewards.php', false, false );

echo '  Name:    ' . $plugin['Name'] . "\n";
echo '  Version: ' . $plugin['Version'] . "\n";
echo '  Domain:  ' . $plugin['TextDomain'] . "\n";

$active = get_option( 'active_plugins', array() );

echo '  Active:  ' . ( in_array( 'madara-coin-rewards/madara-coin-rewards.php', (array) $active, true ) ? 'yes' : 'NO' ) . "\n";

echo "\nScheduled cron events:\n";

$crons = _get_cron_array();

$found = false;

foreach ( (array) $crons as $timestamp => $hooks ) {
	foreach ( (array) $hooks as $hook => $events ) {
		if ( false !== strpos( (string) $hook, 'mcr' ) ) {
			echo "  {$hook}\n";
			$found = true;
		}
	}
}

if ( ! $found ) {
	echo "  (none)\n";
}
