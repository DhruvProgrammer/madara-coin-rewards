<?php
/**
 * A simple rate limiter for the reward endpoints.
 *
 * @package MCR
 */

namespace MCR\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Limits how often an action may be performed.
 *
 * The counter lives in a transient, so it is per site and expires on its
 * own. A limit of zero means "no limit".
 */
class RateLimiter {

	/**
	 * The window the counter covers, in seconds.
	 *
	 * The settings screen describes the limits as "per user per hour",
	 * so the window is an hour.
	 */
	const WINDOW = 3600;

	/**
	 * Consumes one unit of the budget for a bucket.
	 *
	 * @param string $bucket The bucket, e.g. "ads" or "checkin".
	 * @param int    $key    The subject of the limit, e.g. a user id.
	 * @param int    $limit  How many attempts are allowed. 0 disables it.
	 * @return bool True when the action may proceed.
	 */
	public static function allow( $bucket, $key, $limit ) {
		$limit = (int) $limit;

		if ( $limit <= 0 ) {
			return true;
		}

		$name  = 'mcr_rl_' . md5( sanitize_key( (string) $bucket ) . '|' . (int) $key );
		$count = (int) get_transient( $name );

		if ( $count >= $limit ) {
			return false;
		}

		set_transient( $name, $count + 1, self::WINDOW );

		return true;
	}
}
