<?php
/**
 * Scheduled maintenance.
 *
 * @package MCR
 */

namespace MCR;

defined( 'ABSPATH' ) || exit;

/**
 * Cron jobs for order expiry and session cleanup.
 */
class Cron {

	/**
	 * Daily maintenance hook.
	 */
	const HOOK_DAILY = 'mcr_daily_maintenance';

	/**
	 * Hourly maintenance hook.
	 */
	const HOOK_HOURLY = 'mcr_hourly_maintenance';

	/**
	 * Registers the cron hooks.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( self::HOOK_DAILY, array( __CLASS__, 'run_daily' ) );
		add_action( self::HOOK_HOURLY, array( __CLASS__, 'run_hourly' ) );
	}

	/**
	 * Schedules the cron events.
	 *
	 * @return void
	 */
	public static function schedule_events() {
		if ( ! wp_next_scheduled( self::HOOK_DAILY ) ) {
			wp_schedule_event( time() + \HOUR_IN_SECONDS, 'daily', self::HOOK_DAILY );
		}

		if ( ! wp_next_scheduled( self::HOOK_HOURLY ) ) {
			wp_schedule_event( time() + ( 30 * \MINUTE_IN_SECONDS ), 'hourly', self::HOOK_HOURLY );
		}
	}

	/**
	 * Removes the cron events.
	 *
	 * @return void
	 */
	public static function unschedule_events() {
		wp_clear_scheduled_hook( self::HOOK_DAILY );
		wp_clear_scheduled_hook( self::HOOK_HOURLY );
	}

	/**
	 * Runs the daily maintenance.
	 *
	 * @return void
	 */
	public static function run_daily() {
		global $wpdb;

		$expired = Payments\Orders::instance()->expireStale();

		$table = $wpdb->prefix . 'mcr_ad_sessions';
		$wpdb->query(
			"DELETE FROM {$table} WHERE ( status IN ('expired', 'credited') AND verified_at < NOW() - INTERVAL 2 DAY ) OR ( status = 'pending' AND expires_at < NOW() - INTERVAL 1 DAY )" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		/**
		 * Fires during the plugin's daily maintenance.
		 *
		 * @param int $expired Number of expired orders.
		 */
		do_action( 'mcr_daily_maintenance', $expired );
	}

	/**
	 * Runs the hourly maintenance.
	 *
	 * @return void
	 */
	public static function run_hourly() {
		global $wpdb;

		$table = $wpdb->prefix . 'mcr_ad_sessions';

		// Mark stale pending sessions as expired so a client cannot complete
		// a session hours later.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET status = 'expired' WHERE status = 'pending' AND expires_at < %s LIMIT 500", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				gmdate( 'Y-m-d H:i:s' )
			)
		);

		/**
		 * Fires during the plugin's hourly maintenance.
		 */
		do_action( 'mcr_hourly_maintenance' );
	}
}
