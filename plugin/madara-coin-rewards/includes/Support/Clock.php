<?php
/**
 * Clock contract.
 *
 * Every time-dependent rule in the plugin (daily caps, streaks, cooldowns,
 * session expiry, order expiry) MUST go through this interface so behaviour
 * is testable without sleeping and without ever trusting the client clock.
 *
 * @package MCR
 */

namespace MCR\Support;

use DateTimeImmutable;
use DateTimeZone;

defined( 'ABSPATH' ) || exit;

/**
 * Injectable server-authoritative clock.
 */
interface Clock {

	/**
	 * Current moment in the site timezone.
	 *
	 * @return DateTimeImmutable
	 */
	public function now();

	/**
	 * The logical "day bucket" key (Y-m-d) in the site timezone.
	 *
	 * @return string
	 */
	public function today_key();

	/**
	 * Alias of today_key() using the camelCase naming used across the specs.
	 *
	 * @return string
	 */
	public function todayKey();

	/**
	 * The site timezone.
	 *
	 * @return DateTimeZone
	 */
	public function get_timezone();

	/**
	 * Alias of get_timezone() using the camelCase naming used across the specs.
	 *
	 * @return DateTimeZone
	 */
	public function getTimezone();

	/**
	 * Seconds remaining until the next day bucket begins.
	 *
	 * @return int Never negative.
	 */
	public function seconds_until_next_day();

	/**
	 * Current moment as a GMT MySQL datetime string.
	 *
	 * @return string
	 */
	public function mysql_gmt();
}
