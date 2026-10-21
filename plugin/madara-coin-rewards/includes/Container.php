<?php
/**
 * Service container.
 *
 * Provides the wiring for the pure policy classes so production code and
 * tests use the same construction path.
 *
 * @package MCR
 */

namespace MCR;

use MCR\Ledger\LedgerInterface;
use MCR\Rewards\AdRewardPolicy;
use MCR\Rewards\CheckInPolicy;
use MCR\Support\Clock;
use MCR\Support\WpClock;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the plugin's services.
 */
class Container {

	/**
	 * Singleton instance.
	 *
	 * @var Container|null
	 */
	private static $instance = null;

	/**
	 * Shared clock.
	 *
	 * @var Clock|null
	 */
	private $clock = null;

	/**
	 * Shared ledger.
	 *
	 * @var LedgerInterface|null
	 */
	private $ledger = null;

	/**
	 * Returns the shared instance.
	 *
	 * @return Container
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Returns the shared clock.
	 *
	 * @return Clock
	 */
	public function clock() {
		if ( null === $this->clock ) {
			$this->clock = new WpClock();
		}

		return $this->clock;
	}

	/**
	 * Overrides the shared clock. Intended for tests.
	 *
	 * @param Clock $clock Clock instance.
	 * @return void
	 */
	public function set_clock( Clock $clock ) {
		$this->clock = $clock;
	}

	/**
	 * Returns the shared ledger.
	 *
	 * @return LedgerInterface
	 */
	public function ledger() {
		if ( null === $this->ledger ) {
			$this->ledger = Ledger::instance();
		}

		return $this->ledger;
	}

	/**
	 * Overrides the shared ledger. Intended for tests.
	 *
	 * @param LedgerInterface $ledger Ledger instance.
	 * @return void
	 */
	public function set_ledger( LedgerInterface $ledger ) {
		$this->ledger = $ledger;
	}

	/**
	 * Builds a check-in policy.
	 *
	 * @param Clock           $clock  Clock instance.
	 * @param LedgerInterface $ledger Ledger instance.
	 * @return CheckInPolicy
	 */
	public static function makeCheckInPolicy( Clock $clock, LedgerInterface $ledger ) {
		return new CheckInPolicy( $clock, $ledger );
	}

	/**
	 * Builds an ad reward policy.
	 *
	 * @param Clock           $clock    Clock instance.
	 * @param LedgerInterface $ledger   Ledger instance.
	 * @param object|null     $provider Ad provider, when one is configured.
	 * @return AdRewardPolicy
	 */
	public static function makeAdRewardPolicy( Clock $clock, LedgerInterface $ledger, $provider = null ) {
		return new AdRewardPolicy( $clock, $ledger, $provider );
	}
}
