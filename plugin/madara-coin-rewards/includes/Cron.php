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
	/**
	 * Removes the ad sessions that can no longer matter.
	 *
	 * The table grows forever otherwise, and the site-wide cap counts
	 * over it on every ad start. Two days are kept so the rolling
	 * 24 hour cap and the site-timezone daily cap stay correct.
	 *
	 * @return void
	 */
	public static function prune_ad_sessions() {
		global $wpdb;

		$table = $wpdb->prefix . 'mcr_ad_sessions';

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table}
				 WHERE verified_at < %s
				   AND status IN ( 'expired', 'credited', 'verified' )", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				gmdate( 'Y-m-d H:i:s', time() - ( 2 * \DAY_IN_SECONDS ) )
			)
		);

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table}
				 WHERE status = 'pending'
				   AND expires_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				gmdate( 'Y-m-d H:i:s', time() - \DAY_IN_SECONDS )
			)
		);
	}

	/**
	 * Runs the daily maintenance.
	 *
	 * @return void
	 */
	public static function run_daily() {
		$expired = Payments\Orders::instance()->expireStale();

		/*
		 * The ad sessions are pruned here, so the table cannot grow
		 * without bound and the site-wide cap keeps counting over a
		 * bounded set of rows.
		 */
		self::prune_ad_sessions();

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
				"UPDATE {$table} SET status = 'expired' WHERE status = 'pending' AND expires_at < %s LIMIT 100", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				gmdate( 'Y-m-d H:i:s' )
			)
		);

		/**
		 * Fires during the plugin's hourly maintenance.
		 */
		do_action( 'mcr_hourly_maintenance' );
	}
}
