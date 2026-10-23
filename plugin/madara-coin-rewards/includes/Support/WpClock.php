<?php
/**
 * Production clock implementation backed by WordPress time functions.
 *
 * @package MCR
 */

namespace MCR\Support;

use DateTimeImmutable;
use DateTimeZone;

defined( 'ABSPATH' ) || exit;

/**
 * Server-authoritative clock using the WordPress site timezone.
 */
class WpClock implements Clock {

	/**
	 * Optional timestamp override, used by the plugin's own test harness.
	 *
	 * @var int|null
	 */
	private $now_override = null;

	/**
	 * Optional timezone override, used by the plugin's own test harness.
	 *
	 * @var DateTimeZone|null
	 */
	private $timezone_override = null;

	/**
	 * {@inheritDoc}
	 */
	public function now() {
		return ( new DateTimeImmutable( '@' . $this->timestamp() ) )->setTimezone( $this->getTimezone() );
	}

	/**
	 * {@inheritDoc}
	 */
	public function today_key() {
		return $this->now()->format( 'Y-m-d' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function todayKey() {
		return $this->today_key();
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_timezone() {
		if ( $this->timezone_override instanceof DateTimeZone ) {
			return $this->timezone_override;
		}

		$tz = function_exists( 'wp_timezone' ) ? wp_timezone() : null;

		if ( $tz instanceof DateTimeZone ) {
			return $tz;
		}

		return new DateTimeZone( 'UTC' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function getTimezone() {
		return $this->get_timezone();
	}

	/**
	 * {@inheritDoc}
	 */
	public function seconds_until_next_day() {
		$moment = $this->now();
		$next   = $moment->modify( '+1 day' )->setTime( 0, 0, 0 );

		$seconds = $next->getTimestamp() - $moment->getTimestamp();

		return $seconds > 0 ? (int) $seconds : 0;
	}

	/**
	 * {@inheritDoc}
	 */
	public function mysql_gmt() {
		return gmdate( 'Y-m-d H:i:s', $this->timestamp() );
	}

	/**
	 * Current GMT timestamp.
	 *
	 * @return int
	 */
	public function timestamp() {
		if ( null !== $this->now_override ) {
			return $this->now_override;
		}

		return time();
	}

	/**
	 * Overrides the current time. Intended for tests.
	 *
	 * @param int $timestamp Unix timestamp.
	 * @return void
	 */
	public function set_timestamp( $timestamp ) {
		$this->now_override = (int) $timestamp;
	}

	/**
	 * Overrides the timezone. Intended for tests.
	 *
	 * @param DateTimeZone|string $timezone Timezone.
	 * @return void
	 */
	public function set_timezone( $timezone ) {
		$this->timezone_override = $timezone instanceof DateTimeZone ? $timezone : new DateTimeZone( (string) $timezone );
	}
}
