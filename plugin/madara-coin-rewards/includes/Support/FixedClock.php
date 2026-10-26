<?php
/**
 * Fixed clock for deterministic tests.
 *
 * @package MCR
 */

namespace MCR\Support;

use DateTimeImmutable;
use DateTimeZone;

defined( 'ABSPATH' ) || exit;

/**
 * A clock frozen at a given moment.
 */
class FixedClock implements Clock {

	/**
	 * The frozen moment.
	 *
	 * @var DateTimeImmutable
	 */
	private $fixed_now;

	/**
	 * The timezone used for day buckets.
	 *
	 * @var DateTimeZone
	 */
	private $timezone;

	/**
	 * Constructor.
	 *
	 * A string is interpreted as a WALL CLOCK time in the given timezone,
	 * which is what a test author means by "2026-03-08 01:59 in New York".
	 * A DateTimeImmutable is treated as an absolute instant and converted.
	 *
	 * @param DateTimeImmutable|string $fixed_now Moment to freeze at.
	 * @param DateTimeZone|string      $timezone  Timezone for day buckets.
	 */
	public function __construct( $fixed_now, $timezone = 'UTC' ) {
		$this->timezone = $timezone instanceof DateTimeZone ? $timezone : new DateTimeZone( (string) $timezone );

		if ( $fixed_now instanceof DateTimeImmutable ) {
			$this->fixed_now = $fixed_now->setTimezone( $this->timezone );
		} else {
			$this->fixed_now = new DateTimeImmutable( (string) $fixed_now, $this->timezone );
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function now() {
		return $this->fixed_now;
	}

	/**
	 * {@inheritDoc}
	 */
	public function today_key() {
		return $this->fixed_now->format( 'Y-m-d' );
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
		return $this->timezone;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getTimezone() {
		return $this->timezone;
	}

	/**
	 * {@inheritDoc}
	 */
	public function seconds_until_next_day() {
		$next    = $this->fixed_now->modify( '+1 day' )->setTime( 0, 0, 0 );
		$seconds = $next->getTimestamp() - $this->fixed_now->getTimestamp();

		return $seconds > 0 ? (int) $seconds : 0;
	}

	/**
	 * {@inheritDoc}
	 */
	public function mysql_gmt() {
		return gmdate( 'Y-m-d H:i:s', $this->fixed_now->getTimestamp() );
	}
}
