<?php
/**
 * MCR QA / adversarial EDGE-CASE suite.
 *
 * Run with: php tests/qa-edge-cases.php
 *
 * Hostile-input and state-machine abuse against the pure, dependency-free
 * classes of the plugin:
 *
 *   MCR\Ledger\BalanceMath
 *   MCR\Rewards\StreakCalculator
 *   MCR\Rewards\CheckInPolicy
 *   MCR\Rewards\AdRewardPolicy
 *   MCR\Payments\PackageCalculator
 *   MCR\Support\FixedClock
 *
 * No database, no WordPress runtime: only the shared bootstrap and the
 * WordPress stubs are loaded, so every assertion here is deterministic and
 * reproducible.
 *
 * The file EXITS 1 when a defect is proven. That is the point of the suite:
 * a green run means the plugin survived the abuse.
 *
 * @package MCR
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/wp-stubs.php';

$qa_base = dirname( __DIR__ ) . '/includes/';

require_once $qa_base . 'Support/Clock.php';
require_once $qa_base . 'Support/FixedClock.php';
require_once $qa_base . 'Support/WpClock.php';
require_once $qa_base . 'Ledger/BalanceMath.php';
require_once $qa_base . 'Ledger/LedgerInterface.php';
require_once $qa_base . 'Rewards/StreakCalculator.php';
require_once $qa_base . 'Rewards/CheckInPolicy.php';
require_once $qa_base . 'Rewards/AdRewardPolicy.php';
require_once $qa_base . 'Payments/PackageCalculator.php';

use MCR\Ledger\BalanceMath;
use MCR\Payments\PackageCalculator;
use MCR\Rewards\AdRewardPolicy;
use MCR\Rewards\CheckInPolicy;
use MCR\Rewards\StreakCalculator;
use MCR\Support\FixedClock;

/* ==========================================================================
 * Test doubles
 * ======================================================================== */

/**
 * A recording ledger that can be told to fail a specific credit or debit.
 *
 * It records every call, so the tests can assert the exact sequence of
 * ledger operations a policy performs (grant -> bonus -> reversal).
 */
class QA_Ledger implements \MCR\Ledger\LedgerInterface {

	/**
	 * Balance per user.
	 *
	 * @var array
	 */
	public $balances = array();

	/**
	 * Every ledger call, in order.
	 *
	 * @var array
	 */
	public $calls = array();

	/**
	 * 1-based index of the credit call that must fail. 0 = never fail.
	 *
	 * @var int
	 */
	public $fail_credit_at = 0;

	/**
	 * Number of credit calls made so far.
	 *
	 * @var int
	 */
	public $credit_count = 0;

	/**
	 * Whether every debit fails.
	 *
	 * @var bool
	 */
	public $fail_debit = false;

	/**
	 * {@inheritDoc}
	 */
	public function credit( $user_id, $amount, $type, $reason = '', $ctx = array() ) {
		++$this->credit_count;

		$this->calls[] = array(
			'op'      => 'credit',
			'user_id' => $user_id,
			'amount'  => $amount,
			'type'    => $type,
			'ctx'     => $ctx,
		);

		if ( $this->fail_credit_at === $this->credit_count ) {
			return new WP_Error( 'mcr_ledger_down', 'The ledger is unavailable.' );
		}

		$this->balances[ $user_id ] = ( $this->balances[ $user_id ] ?? 0 ) + $amount;

		return array(
			'transaction_id' => count( $this->calls ),
			'balance_after'  => $this->balances[ $user_id ],
			'duplicate'      => false,
			'amount'         => $amount,
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function debit( $user_id, $amount, $type, $reason = '', $ctx = array() ) {
		$this->calls[] = array(
			'op'      => 'debit',
			'user_id' => $user_id,
			'amount'  => $amount,
			'type'    => $type,
			'ctx'     => $ctx,
		);

		if ( $this->fail_debit ) {
			return new WP_Error( 'mcr_debit_failed', 'The debit could not be applied.' );
		}

		$this->balances[ $user_id ] = ( $this->balances[ $user_id ] ?? 0 ) - $amount;

		return array(
			'transaction_id' => count( $this->calls ),
			'balance_after'  => $this->balances[ $user_id ],
			'duplicate'      => false,
			'amount'         => -$amount,
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_balance( $user_id ) {
		return (int) ( $this->balances[ $user_id ] ?? 0 );
	}

	/**
	 * The idempotency keys used so far.
	 *
	 * @return array
	 */
	public function keys() {
		$keys = array();

		foreach ( $this->calls as $call ) {
			if ( isset( $call['ctx']['idempotency_key'] ) ) {
				$keys[] = $call['ctx']['idempotency_key'];
			}
		}

		return $keys;
	}
}

/**
 * A stub ad provider whose verification result the test controls.
 */
class QA_Provider {

	/**
	 * Whether the provider reports itself as configured.
	 *
	 * @var bool
	 */
	public $configured = true;

	/**
	 * What verify_completion() returns.
	 *
	 * @var bool
	 */
	public $verification = true;

	/**
	 * The payload of the last verify_completion() call.
	 *
	 * @var array
	 */
	public $last_payload = array();

	/**
	 * {@inheritDoc}
	 */
	public function is_configured() {
		return $this->configured;
	}

	/**
	 * {@inheritDoc}
	 */
	public function verify_completion( $payload ) {
		$this->last_payload = $payload;

		return $this->verification;
	}
}

/**
 * Runs a callback while collecting every PHP diagnostic it raises.
 *
 * @param callable $callback Callback.
 * @return array List of "level: message" strings.
 */
function qa_collect_diagnostics( callable $callback ) {
	$found = array();

	set_error_handler(
		static function ( $errno, $errstr, $errfile = '', $errline = 0 ) use ( &$found ) {
			$found[] = $errno . ': ' . $errstr;

			return true;
		}
	);

	try {
		$callback();
	} finally {
		restore_error_handler();
	}

	return $found;
}

echo "MCR QA - adversarial edge cases\n";
echo "==============================\n";

/* ==========================================================================
 * 1. BalanceMath - numeric abuse
 * ======================================================================== */

mcr_group( 'BalanceMath::validate - hostile numeric input' );

assertSame( 10, BalanceMath::validate( 10 ), 'a positive integer is accepted' );
assertSame( 10, BalanceMath::validate( '10' ), 'a clean numeric string is accepted' );
assertSame( 10, BalanceMath::validate( ' 10 ' ), 'a padded numeric string is trimmed and accepted' );
assertFalse( BalanceMath::validate( 0 ), 'zero is rejected' );
assertFalse( BalanceMath::validate( '0' ), 'the string zero is rejected' );
assertFalse( BalanceMath::validate( -1 ), 'a negative integer is rejected' );
assertFalse( BalanceMath::validate( '-1' ), 'a negative numeric string is rejected' );
assertFalse( BalanceMath::validate( PHP_INT_MAX ), 'PHP_INT_MAX is rejected' );
assertFalse( BalanceMath::validate( (string) PHP_INT_MAX ), 'PHP_INT_MAX as a string is rejected' );
assertFalse( BalanceMath::validate( '9223372036854775808' ), 'a value past PHP_INT_MAX is rejected' );
assertFalse( BalanceMath::validate( '1e10' ), 'exponent notation is rejected' );
assertFalse( BalanceMath::validate( '0x10' ), 'hex notation is rejected' );
assertSame( 10, BalanceMath::validate( '010' ), 'a leading zero is a plain decimal, not octal' );
assertFalse( BalanceMath::validate( '1 000' ), 'a grouped number is rejected' );
assertFalse( BalanceMath::validate( "\u{0661}\u{0662}\u{0663}" ), 'unicode digits are rejected' );
assertFalse( BalanceMath::validate( '1.0' ), 'a decimal string is rejected' );
assertFalse( BalanceMath::validate( INF ), 'INF is rejected' );
assertFalse( BalanceMath::validate( NAN ), 'NAN is rejected' );
assertFalse( BalanceMath::validate( true ), 'a boolean is rejected' );
assertFalse( BalanceMath::validate( null ), 'null is rejected' );
assertFalse( BalanceMath::validate( array( 5 ) ), 'an array is rejected' );
assertFalse( BalanceMath::validate( new stdClass() ), 'an object is rejected' );
assertFalse( BalanceMath::validate( BalanceMath::MAX_BALANCE + 1 ), 'one coin above the ceiling is rejected' );
assertSame( BalanceMath::MAX_BALANCE, BalanceMath::validate( BalanceMath::MAX_BALANCE ), 'exactly the ceiling is accepted' );

mcr_group( 'BalanceMath::credit / debit - overflow and negativity' );

assertSame( 110, BalanceMath::credit( 100, 10 ), 'a credit adds to the balance' );
assertSame( 90, BalanceMath::debit( 100, 10 ), 'a debit subtracts from the balance' );
assertFalse( BalanceMath::credit( BalanceMath::MAX_BALANCE, 1 ), 'a credit that would breach the ceiling is rejected' );
assertFalse( BalanceMath::credit( PHP_INT_MAX, 1 ), 'a credit on a PHP_INT_MAX balance does not overflow' );
assertFalse( BalanceMath::debit( 100, 101 ), 'an overdraft is rejected' );
assertFalse( BalanceMath::debit( -50, 1 ), 'a debit from a negative balance is rejected' );
assertFalse( BalanceMath::credit( 100, 0 ), 'a zero credit is rejected' );
assertFalse( BalanceMath::credit( 100, -5 ), 'a negative credit is rejected' );
assertFalse( BalanceMath::credit( 100, INF ), 'an INF credit is rejected' );
assertFalse( BalanceMath::credit( 100, NAN ), 'a NAN credit is rejected' );
assertFalse( BalanceMath::credit( 100, '1e10' ), 'an exponent credit is rejected' );
assertFalse( BalanceMath::canAfford( 100, '1e10' ), 'an exponent cost is never affordable' );

/*
 * A negative balance is a corruption state the plugin must survive. The
 * plugin documents "the balance never goes negative" (run-ledger-tests.php
 * asserts it), so credit() must not hand a still-negative balance back.
 */
assertSame(
	0,
	BalanceMath::credit( -50, 10 ),
	'a credit on a corrupted negative balance clamps it to zero instead of keeping it negative'
);

mcr_group( 'BalanceMath - no overflow over many operations' );

$qa_balance = 0;

for ( $qa_i = 0; $qa_i < 50000; $qa_i++ ) {
	$qa_balance = BalanceMath::credit( $qa_balance, 7 );
	$qa_balance = BalanceMath::debit( $qa_balance, 3 );
}

assertSame( 200000, $qa_balance, 'fifty thousand credit/debit pairs leave no drift' );
assertTrue( is_int( $qa_balance ), 'the balance stays an integer' );

/* ==========================================================================
 * 2. PackageCalculator - numeric abuse
 * ======================================================================== */

mcr_group( 'PackageCalculator::calculateTotal - overflow' );

assertSame( 100, PackageCalculator::calculateTotal( 100 ), 'a package with no bonus returns the base' );
assertSame( 110, PackageCalculator::calculateTotal( 100, 10 ), 'a 10 percent bonus is added' );
assertSame( 0, PackageCalculator::calculateTotal( -50 ), 'a negative base is clamped to zero' );
assertSame( 100, PackageCalculator::calculateTotal( 100, 0 ), 'a zero bonus changes nothing' );
assertSame( 200, PackageCalculator::calculateTotal( 100, 500 ), 'a bonus over 100 percent is clamped' );
assertSame( 0, PackageCalculator::calculateTotal( 'abc', 10 ), 'a non numeric base is zero' );
assertSame( 0, PackageCalculator::calculateTotal( null, 10 ), 'a null base is zero' );
assertSame( 0, PackageCalculator::calculateTotal( array(), 10 ), 'an array base is zero' );

/*
 * The coin maths is the single source of truth for every package total
 * (Packages::coins_total() and Packages::normalize() both call it). It must
 * never return a negative number: a negative coin total is a silent money
 * bug for every caller.
 */
$qa_total = PackageCalculator::calculateTotal( PHP_INT_MAX, 100 );

assertTrue(
	$qa_total >= 0,
	'calculateTotal(PHP_INT_MAX, 100) does not overflow into a negative coin total',
	'expected a non-negative total, got ' . var_export( $qa_total, true )
);

assertTrue(
	is_int( $qa_total ),
	'calculateTotal(PHP_INT_MAX, 100) returns an integer, not a float',
	'expected int, got ' . gettype( $qa_total ) . ' ' . var_export( $qa_total, true )
);

$qa_total2 = PackageCalculator::calculateTotal( PHP_INT_MAX, 100, 0 );

assertTrue(
	$qa_total2 >= 0,
	'calculateTotal(PHP_INT_MAX, 100, 0) does not overflow into a negative coin total',
	'expected a non-negative total, got ' . var_export( $qa_total2, true )
);

mcr_group( 'PackageCalculator - money conversion abuse' );

assertSame( 1999, PackageCalculator::toMinorUnits( 19.99 ), 'a price converts to minor units' );
assertSame( 1000, PackageCalculator::toMinorUnits( 10 ), 'a whole price converts to minor units' );
assertSame( 19.99, PackageCalculator::fromMinorUnits( 1999 ), 'minor units convert back to a price' );
assertSame( 0, PackageCalculator::toMinorUnits( 'abc' ), 'a non numeric price converts to zero' );
assertSame( 0, PackageCalculator::toMinorUnits( INF ), 'an INF price converts to zero' );
assertSame( 0, PackageCalculator::toMinorUnits( NAN ), 'a NAN price converts to zero' );
assertSame( 0, PackageCalculator::toMinorUnits( null ), 'a null price converts to zero' );
assertSame( 0, PackageCalculator::toMinorUnits( array() ), 'an array price converts to zero' );
assertSame( 1000000000000, PackageCalculator::toMinorUnits( '1e10' ), 'exponent notation converts to minor units' );

$qa_minor = PackageCalculator::toMinorUnits( '1e30' );

assertTrue(
	$qa_minor >= 0,
	'toMinorUnits("1e30") never returns a negative money amount',
	'expected a non-negative amount, got ' . var_export( $qa_minor, true )
);

assertTrue(
	PackageCalculator::fromMinorUnits( $qa_minor ) >= 0,
	'fromMinorUnits() never turns a converted amount into a negative price'
);

/* ==========================================================================
 * 3. StreakCalculator - calendar abuse
 * ======================================================================== */

mcr_group( 'StreakCalculator - leap days and year boundaries' );

assertSame(
	2,
	StreakCalculator::calculateStreakDay( '2024-03-01', array( '2024-02-29' ) ),
	'a leap day extends the streak'
);
assertSame(
	3,
	StreakCalculator::calculateStreakDay( '2024-03-01', array( '2024-02-28', '2024-02-29' ) ),
	'28 Feb -> 29 Feb -> 1 Mar is three consecutive days in a leap year'
);
assertSame(
	2,
	StreakCalculator::calculateStreakDay( '2025-03-01', array( '2025-02-28' ) ),
	'28 Feb -> 1 Mar is consecutive in a non leap year'
);
assertSame(
	1,
	StreakCalculator::calculateStreakDay( '2025-03-01', array( '2025-02-27' ) ),
	'a gap before a non leap February still breaks the streak'
);
assertSame(
	2,
	StreakCalculator::calculateStreakDay( '2026-01-01', array( '2025-12-31' ) ),
	'the year boundary is consecutive'
);
assertSame(
	3,
	StreakCalculator::calculateStreakDay( '2026-01-01', array( '2025-12-31', '2025-12-30' ) ),
	'the year boundary is consecutive for a multi day streak'
);
assertSame( '', StreakCalculator::normalizeKey( '2025-02-29' ), '29 February in a non leap year is rejected' );
assertSame( '2024-02-29', StreakCalculator::normalizeKey( '2024-02-29' ), '29 February in a leap year is accepted' );
assertSame(
	3,
	StreakCalculator::calculateStreakDay( '2024-02-29', array( '2024-02-28', '2024-02-27' ) ),
	'a streak can be built up to the leap day itself'
);

mcr_group( 'StreakCalculator - hostile key lists' );

assertSame( 1, StreakCalculator::calculateStreakDay( '2026-01-15', 'not-an-array-or-date' ), 'a garbage string key list is ignored' );
assertSame( 1, StreakCalculator::calculateStreakDay( '2026-01-15', 123 ), 'an integer key list is ignored' );
assertSame( 1, StreakCalculator::calculateStreakDay( '2026-01-15', null ), 'a null key list is ignored' );
assertSame( 1, StreakCalculator::calculateStreakDay( '2026-01-15', array( null, false, 0, '' ) ), 'junk keys are ignored' );
assertSame(
	2,
	StreakCalculator::calculateStreakDay( '2026-01-15', array( '2026-01-14', '2026-01-14' ) ),
	'duplicate keys do not double count the streak'
);
assertSame(
	3,
	StreakCalculator::calculateStreakDay( '2026-01-15', '2026-01-14,2026-01-13' ),
	'a comma separated key list is accepted'
);
assertSame(
	2,
	StreakCalculator::calculateStreakDay( '2026-01-15', array( '2026-01-14', '2026-01-16' ) ),
	'a future key does not break the streak'
);
assertSame(
	1,
	StreakCalculator::calculateStreakDay( '2026-01-15', array( '2026-01-13' ) ),
	'a missing day breaks the streak'
);

mcr_group( 'StreakCalculator::calculateBonus - hostile bonus maps' );

assertSame( 0, StreakCalculator::calculateBonus( 3, array() ), 'an empty bonus map grants nothing' );
assertSame( 0, StreakCalculator::calculateBonus( 3, null ), 'a null bonus map grants nothing' );
assertSame( 0, StreakCalculator::calculateBonus( 3, 'not-an-array' ), 'a string bonus map grants nothing' );
assertSame( 0, StreakCalculator::calculateBonus( 3, array( 'x' => 'y' ) ), 'a malformed bonus map grants nothing' );
assertSame( 0, StreakCalculator::calculateBonus( 0, array( 1 => 100 ) ), 'streak day zero grants nothing' );
assertSame( 0, StreakCalculator::calculateBonus( -5, array( 1 => 100 ) ), 'a negative streak day grants nothing' );
assertSame( 0, StreakCalculator::calculateBonus( 10, array( 0 => 100 ) ), 'a zero threshold grants nothing' );
assertSame( 0, StreakCalculator::calculateBonus( 10, array( 3 => 0 ) ), 'a zero bonus amount grants nothing' );
assertSame( 0, StreakCalculator::calculateBonus( 10, array( 3 => -5 ) ), 'a negative bonus amount grants nothing' );
assertSame( 5, StreakCalculator::calculateBonus( 3, array( 3 => 5 ) ), 'the threshold bonus is granted at the threshold' );
assertSame( 15, StreakCalculator::calculateBonus( 7, array( 3 => 5, 7 => 15 ) ), 'the highest reached tier wins' );

$qa_bonus = StreakCalculator::calculateBonus( 30, array( 30 => 500 ), 100 );

assertSame( 100, $qa_bonus, 'the max_bonus cap is applied to a large bonus' );
assertSame( 0, StreakCalculator::calculateBonus( 30, array( 30 => 0 ), 0 ), 'a zero bonus grants nothing' );

/* ==========================================================================
 * 4. Clock - day buckets, DST, timezones
 * ======================================================================== */

mcr_group( 'Clock - day bucket boundaries' );

$qa_clock = new FixedClock( '2026-01-15 23:59:59', 'UTC' );
assertSame( '2026-01-15', $qa_clock->today_key(), 'the last second of the day is still today' );
assertSame( 1, $qa_clock->seconds_until_next_day(), 'one second remains before the next day' );

$qa_clock = new FixedClock( '2026-01-16 00:00:00', 'UTC' );
assertSame( '2026-01-16', $qa_clock->today_key(), 'midnight starts the next day' );
assertSame( 86400, $qa_clock->seconds_until_next_day(), 'a full day remains at midnight' );

mcr_group( 'Clock - DST transitions (23 hour and 25 hour days)' );

// 2026-03-08 is the second Sunday of March 2026: the US spring forward date.
$qa_clock = new FixedClock( '2026-03-08 00:00:00', 'America/New_York' );
assertSame( '2026-03-08', $qa_clock->today_key(), 'the spring forward day starts on the right date' );
assertSame(
	82800,
	$qa_clock->seconds_until_next_day(),
	'a 23 hour DST day counts 23 hours, not 24'
);

// 2026-11-01 is the first Sunday of November 2026: the US fall back date.
$qa_clock = new FixedClock( '2026-11-01 00:00:00', 'America/New_York' );
assertSame( '2026-11-01', $qa_clock->today_key(), 'the fall back day starts on the right date' );
assertSame(
	90000,
	$qa_clock->seconds_until_next_day(),
	'a 25 hour DST day counts 25 hours, not 24'
);

$qa_clock = new FixedClock( '2026-03-08 01:59:00', 'America/New_York' );
assertSame( '2026-03-08', $qa_clock->today_key(), 'the day has not turned before the spring forward jump' );
assertSame( '2026-03-08', ( new FixedClock( '2026-03-08 03:01:00', 'America/New_York' ) )->today_key(), 'the day has not turned after the spring forward jump' );

mcr_group( 'Clock - extreme site timezones' );

// UTC+14 (Pacific/Kiritimati) and UTC-11 (Pacific/Midway).
$qa_clock = new FixedClock( '2026-01-15 23:30:00', 'Pacific/Kiritimati' );
assertSame( '2026-01-15', $qa_clock->today_key(), 'a UTC+14 site still uses its own local date' );
assertSame( 1800, $qa_clock->seconds_until_next_day(), 'a UTC+14 site rolls over at its local midnight' );

$qa_clock = new FixedClock( '2026-01-14 12:00:00', 'Pacific/Midway' );
assertSame( '2026-01-14', $qa_clock->today_key(), 'a UTC-11 site uses its own local date' );
assertSame( 43200, $qa_clock->seconds_until_next_day(), 'a UTC-11 site rolls over at its local midnight' );

/*
 * On a DST start day whose local midnight does not exist (Chile jumps from
 * 23:59:59 to 01:00:00), PHP normalises 00:00:00 to 01:00:00. The day
 * boundary must still be the local midnight of the NEXT day, so the
 * countdown must not be an hour long.
 */
$qa_clock = new FixedClock( '2026-09-06 12:00:00', 'America/Santiago' );
assertSame( '2026-09-06', $qa_clock->today_key(), 'the day key is the local date even when local midnight does not exist' );
assertSame(
	43200,
	$qa_clock->seconds_until_next_day(),
	'the countdown reaches the next local midnight even when local midnight does not exist on this day'
);

mcr_group( 'Clock - backwards jump (NTP correction)' );

$qa_clock = new FixedClock( '2026-01-15 10:00:00', 'UTC' );
assertSame( '2026-01-15', $qa_clock->today_key(), 'the clock reports its own day' );

$qa_back = new FixedClock( '2026-01-15 10:00:00', 'UTC' );
assertSame( $qa_clock->today_key(), $qa_back->today_key(), 'two clocks at the same instant agree' );

$qa_clock = new FixedClock( '2026-01-15 00:00:00', 'UTC' );
assertSame( 86400, $qa_clock->seconds_until_next_day(), 'the countdown never goes negative' );

/* ==========================================================================
 * 5. CheckInPolicy - the check-in state machine
 * ======================================================================== */

mcr_group( 'CheckInPolicy - interleaving: base ok, bonus ok' );

$qa_clock   = new FixedClock( '2026-01-15 10:00:00', 'UTC' );
$qa_ledger  = new QA_Ledger();
$qa_policy  = new CheckInPolicy( $qa_clock, $qa_ledger );

$qa_eval = $qa_policy->evaluate(
	7,
	array(
		'base_amount'   => 10,
		'bonuses'       => array( 3 => 5 ),
		'previous_keys' => array( '2026-01-13', '2026-01-14' ),
	)
);

assertFalse( is_wp_error( $qa_eval ), 'the claim evaluates' );
assertSame( 3, $qa_eval['streak'], 'the streak counts the previous consecutive days' );
assertSame( 5, $qa_eval['bonus'], 'the threshold bonus is applied' );
assertSame( 15, $qa_eval['total'], 'the total is base plus bonus' );
assertSame( '2026-01-15', $qa_eval['date_key'], 'the day bucket is the clock day' );

$qa_grant = $qa_policy->grant( 7, $qa_eval );

assertFalse( is_wp_error( $qa_grant ), 'the grant succeeds' );
assertSame( 15, $qa_ledger->get_balance( 7 ), 'the balance is base plus bonus' );
assertSame( 2, count( $qa_ledger->calls ), 'two ledger rows are written' );
assertSame( 'checkin:7:2026-01-15', $qa_ledger->keys()[0], 'the base credit is keyed on the user and the day' );
assertSame( 'checkin_bonus:7:2026-01-15', $qa_ledger->keys()[1], 'the bonus credit is keyed separately' );

mcr_group( 'CheckInPolicy - interleaving: base FAILS' );

$qa_ledger = new QA_Ledger();
$qa_ledger->fail_credit_at = 1;
$qa_policy = new CheckInPolicy( $qa_clock, $qa_ledger );

$qa_eval = $qa_policy->evaluate(
	7,
	array(
		'base_amount'   => 10,
		'bonuses'       => array( 3 => 5 ),
		'previous_keys' => array( '2026-01-13', '2026-01-14' ),
	)
);

$qa_grant = $qa_policy->grant( 7, $qa_eval );

assertTrue( is_wp_error( $qa_grant ), 'the failed base credit is reported as an error' );
assertSame( 0, $qa_ledger->get_balance( 7 ), 'no coins were paid' );
assertSame( 1, count( $qa_ledger->calls ), 'no reversal was attempted for a base credit that never landed' );

/*
 * The caller (CheckIn::claim) keeps the claim row when the error does not
 * carry `reversed`. For a base credit that never landed nothing was paid,
 * so the day was NOT claimed and the row MUST be removed - otherwise the
 * user is locked out of today with a row that claims a total they never
 * received. The policy must therefore say so.
 */
$qa_data = $qa_grant->get_error_data();
$qa_data = is_array( $qa_data ) ? $qa_data : array();

$qa_says_not_claimed = ( isset( $qa_data['reversed'] ) && true === $qa_data['reversed'] )
	|| ( isset( $qa_data['granted'] ) && false === $qa_data['granted'] );

assertTrue(
	$qa_says_not_claimed,
	'a failed base credit tells the caller that nothing was granted, so the claim row is removed',
	'error data was ' . var_export( $qa_data, true )
);

mcr_group( 'CheckInPolicy - interleaving: base ok, bonus FAILS, reversal ok' );

$qa_ledger = new QA_Ledger();
$qa_ledger->fail_credit_at = 2; // The bonus credit fails.
$qa_policy = new CheckInPolicy( $qa_clock, $qa_ledger );

$qa_eval = $qa_policy->evaluate(
	8,
	array(
		'base_amount'   => 10,
		'bonuses'       => array( 3 => 5 ),
		'previous_keys' => array( '2026-01-13', '2026-01-14' ),
	)
);

$qa_grant = $qa_policy->grant( 8, $qa_eval );

assertTrue( is_wp_error( $qa_grant ), 'the failed bonus credit is reported' );
assertSame( 0, $qa_ledger->get_balance( 8 ), 'the base reward is reversed so the day is not half paid' );
assertSame( 3, count( $qa_ledger->calls ), 'credit, failed credit, reversal' );
assertSame( 'debit', $qa_ledger->calls[2]['op'], 'the third call is the reversal' );
assertSame( 'reversal', $qa_ledger->calls[2]['type'], 'the reversal is booked as a reversal' );

$qa_data = $qa_grant->get_error_data();
$qa_data = is_array( $qa_data ) ? $qa_data : array();

assertTrue( ! empty( $qa_data['reversed'] ), 'the error says the base WAS reversed' );

mcr_group( 'CheckInPolicy - interleaving: base ok, bonus FAILS, reversal FAILS' );

$qa_ledger = new QA_Ledger();
$qa_ledger->fail_credit_at = 2;
$qa_ledger->fail_debit     = true;
$qa_policy = new CheckInPolicy( $qa_clock, $qa_ledger );

$qa_eval = $qa_policy->evaluate(
	9,
	array(
		'base_amount'   => 10,
		'bonuses'       => array( 3 => 5 ),
		'previous_keys' => array( '2026-01-13', '2026-01-14' ),
	)
);

$qa_grant = $qa_policy->grant( 9, $qa_eval );

assertTrue( is_wp_error( $qa_grant ), 'the failed grant is reported' );
assertSame( 10, $qa_ledger->get_balance( 9 ), 'the base coins remain when the reversal fails' );

$qa_data = $qa_grant->get_error_data();
$qa_data = is_array( $qa_data ) ? $qa_data : array();

assertFalse( ! empty( $qa_data['reversed'] ), 'the error says the reversal did NOT happen' );

mcr_group( 'CheckInPolicy - interleaving: base zero, bonus FAILS' );

$qa_ledger = new QA_Ledger();
$qa_ledger->fail_credit_at = 1;
$qa_policy = new CheckInPolicy( $qa_clock, $qa_ledger );

$qa_eval = $qa_policy->evaluate(
	10,
	array(
		'base_amount'   => 0,
		'bonuses'       => array( 3 => 5 ),
		'previous_keys' => array( '2026-01-13', '2026-01-14' ),
	)
);

$qa_grant = $qa_policy->grant( 10, $qa_eval );

assertTrue( is_wp_error( $qa_grant ), 'the failed bonus credit is reported' );
assertSame( 0, $qa_ledger->get_balance( 10 ), 'nothing was paid' );

mcr_group( 'CheckInPolicy - refusing and hostile arguments' );

$qa_ledger = new QA_Ledger();
$qa_policy = new CheckInPolicy( $qa_clock, $qa_ledger );

assertTrue( is_wp_error( $qa_policy->evaluate( 0 ) ), 'user zero is refused' );
assertTrue( is_wp_error( $qa_policy->evaluate( -1 ) ), 'a negative user id is refused' );
assertTrue( is_wp_error( $qa_policy->evaluate( 'abc' ) ), 'a non numeric user id is refused' );
assertTrue( is_wp_error( $qa_policy->evaluate( null ) ), 'a null user id is refused' );
assertTrue( is_wp_error( $qa_policy->evaluate( array() ) ), 'an empty array user id is refused' );

$qa_refused = $qa_policy->evaluate( 7, array( 'already_claimed' => true ) );
assertTrue( is_wp_error( $qa_refused ), 'an already claimed day is refused' );
assertSame( 'mcr_already_claimed', $qa_refused->get_error_code(), 'the refusal names the duplicate claim' );

$qa_eval = $qa_policy->evaluate( 7, array( 'base_amount' => -100 ) );
assertSame( 0, $qa_eval['base'], 'a negative base amount is clamped to zero' );

$qa_eval = $qa_policy->evaluate( 7, array( 'base_amount' => null ) );
assertSame( 0, $qa_eval['base'], 'a null base amount is zero' );

/*
 * The evaluation total is written straight into the mcr_checkins row with
 * a %d placeholder. A total that overflows an int becomes a float and the
 * %d cast produces garbage, so the total must stay an integer.
 */
$qa_eval = $qa_policy->evaluate( 7, array( 'base_amount' => PHP_INT_MAX, 'bonuses' => array( 3 => 5 ) ) );

assertTrue(
	is_int( $qa_eval['total'] ),
	'the evaluated total stays an integer even for an absurd base amount',
	'expected int, got ' . gettype( $qa_eval['total'] ) . ' ' . var_export( $qa_eval['total'], true )
);

mcr_group( 'CheckInPolicy - the daily grant is not repeatable' );

$qa_ledger = new QA_Ledger();
$qa_policy = new CheckInPolicy( $qa_clock, $qa_ledger );

$qa_first = $qa_policy->attempt( 11, array( 'base_amount' => 10 ) );
assertFalse( is_wp_error( $qa_first ), 'the first claim of the day succeeds' );
assertSame( 10, $qa_ledger->get_balance( 11 ), 'the base amount is granted' );

$qa_second = $qa_policy->attempt( 11, array( 'base_amount' => 10, 'already_claimed' => true ) );
assertTrue( is_wp_error( $qa_second ), 'a second claim the same day is refused' );
assertSame( 'mcr_already_claimed', $qa_second->get_error_code(), 'the refusal names already_claimed' );
assertSame( 1, count( $qa_ledger->calls ), 'the refused claim writes no extra ledger row' );
assertSame( 10, $qa_ledger->get_balance( 11 ), 'the refused claim does not move the balance' );

/* ==========================================================================
 * 6. AdRewardPolicy - the ad reward state machine
 * ======================================================================== */

mcr_group( 'AdRewardPolicy::requestSession - limits' );

$qa_clock   = new FixedClock( '2026-01-15 10:00:00', 'UTC' );
$qa_ledger  = new QA_Ledger();
$qa_provider = new QA_Provider();
$qa_policy  = new AdRewardPolicy( $qa_clock, $qa_ledger, $qa_provider );

$qa_session = $qa_policy->requestSession(
	7,
	array( 'coins' => 5, 'daily_limit' => 10, 'watched_today' => 9 )
);
assertFalse( is_wp_error( $qa_session ), 'a session starts one below the cap' );
assertSame( 64, strlen( $qa_session['session_token'] ), 'the session token is 32 random bytes, hex encoded' );
assertSame( '2026-01-15', $qa_session['day_key'], 'the session carries the day bucket' );

$qa_capped = $qa_policy->requestSession(
	7,
	array( 'coins' => 5, 'daily_limit' => 10, 'watched_today' => 10 )
);
assertTrue( is_wp_error( $qa_capped ), 'the daily cap blocks a new session' );
assertSame( 'mcr_daily_limit_reached', $qa_capped->get_error_code(), 'the refusal names the daily limit' );

$qa_capped = $qa_policy->requestSession(
	7,
	array( 'coins' => 5, 'daily_limit' => 0, 'global_daily_limit' => 3, 'watched_global' => 3 )
);
assertTrue( is_wp_error( $qa_capped ), 'the global cap blocks a new session' );
assertSame( 'mcr_daily_limit_reached', $qa_capped->get_error_code(), 'the refusal names the daily limit' );

$qa_capped = $qa_policy->requestSession(
	7,
	array( 'coins' => 5, 'daily_limit' => 10, 'watched_today' => 1, 'cooldown_seconds' => 300, 'last_watch_at' => $qa_clock->now()->getTimestamp() - 60 )
);
assertTrue( is_wp_error( $qa_capped ), 'the cooldown blocks a new session' );
assertSame( 'mcr_cooldown_active', $qa_capped->get_error_code(), 'the refusal names the cooldown' );

$qa_ok = $qa_policy->requestSession(
	7,
	array( 'coins' => 5, 'daily_limit' => 10, 'watched_today' => 1, 'cooldown_seconds' => 300, 'last_watch_at' => $qa_clock->now()->getTimestamp() - 400 )
);
assertFalse( is_wp_error( $qa_ok ), 'the session starts once the cooldown has expired' );

$qa_invalid = $qa_policy->requestSession( 0, array( 'coins' => 5 ) );
assertTrue( is_wp_error( $qa_invalid ), 'an invalid user cannot start a session' );

$qa_noprovider = new AdRewardPolicy( $qa_clock, $qa_ledger, null );
$qa_invalid = $qa_noprovider->requestSession( 7, array( 'coins' => 5 ) );
assertTrue( is_wp_error( $qa_invalid ), 'no provider means no session' );
assertSame( 'mcr_provider_not_configured', $qa_invalid->get_error_code(), 'the refusal names the missing provider' );

mcr_group( 'AdRewardPolicy::verify - replay, ownership, expiry, cap' );

$qa_session_row = array(
	'id'         => 42,
	'user_id'    => 7,
	'ad_id'      => 'rewarded_video',
	'provider'   => 'generic_s2s',
	'status'     => 'pending',
	'coins'      => 5,
	'expires_at' => '2026-01-15 10:15:00',
);

$qa_result = $qa_policy->verify(
	7,
	array( 'session_token' => 'abc', 'completion_token' => 'proof' ),
	array( 'session' => $qa_session_row, 'coins' => 5, 'daily_limit' => 10, 'watched_today' => 0 )
);
assertFalse( is_wp_error( $qa_result ), 'a verified completion grants the reward' );
assertSame( 5, $qa_result['coins'], 'the reward amount is granted' );
assertSame( 1, count( $qa_ledger->calls ), 'exactly one ledger row is written' );
assertSame( 'ad_session:42', $qa_ledger->calls[0]['ctx']['idempotency_key'], 'the credit is idempotent on the session' );

$qa_result = $qa_policy->verify(
	8,
	array( 'session_token' => 'abc', 'completion_token' => 'proof' ),
	array( 'session' => $qa_session_row, 'coins' => 5 )
);
assertTrue( is_wp_error( $qa_result ), 'a session belonging to another user is refused' );
assertSame( 'mcr_session_mismatch', $qa_result->get_error_code(), 'the refusal names the ownership mismatch' );

$qa_verified = $qa_session_row;
$qa_verified['status'] = 'verified';

$qa_result = $qa_policy->verify(
	7,
	array( 'session_token' => 'abc', 'completion_token' => 'proof' ),
	array( 'session' => $qa_verified, 'coins' => 5 )
);
assertTrue( is_wp_error( $qa_result ), 'an already verified session is refused' );
assertSame( 'mcr_already_verified', $qa_result->get_error_code(), 'the refusal names the replay' );

$qa_expired = $qa_session_row;
$qa_expired['expires_at'] = '2026-01-15 09:00:00';

$qa_result = $qa_policy->verify(
	7,
	array( 'session_token' => 'abc', 'completion_token' => 'proof' ),
	array( 'session' => $qa_expired, 'coins' => 5 )
);
assertTrue( is_wp_error( $qa_result ), 'an expired session is refused' );
assertSame( 'mcr_session_expired', $qa_result->get_error_code(), 'the refusal names the expiry' );

$qa_result = $qa_policy->verify(
	7,
	array( 'session_token' => 'abc', 'completion_token' => 'proof' ),
	array( 'session' => $qa_session_row, 'coins' => 5, 'daily_limit' => 10, 'watched_today' => 10 )
);
assertTrue( is_wp_error( $qa_result ), 'a completion after the cap is reached is refused' );
assertSame( 'mcr_daily_limit_reached', $qa_result->get_error_code(), 'the refusal names the daily limit' );

$qa_result = $qa_policy->verify(
	7,
	array( 'session_token' => 'abc', 'completion_token' => 'proof' ),
	array( 'session' => null, 'coins' => 5 )
);
assertTrue( is_wp_error( $qa_result ), 'a session that was never requested is refused' );
assertSame( 'mcr_session_not_found', $qa_result->get_error_code(), 'the refusal names the missing session' );

$qa_result = $qa_policy->verify(
	7,
	array( 'session_token' => '', 'completion_token' => 'proof' ),
	array( 'session' => $qa_session_row, 'coins' => 5 )
);
assertTrue( is_wp_error( $qa_result ), 'an empty session token is refused' );
assertSame( 'mcr_invalid_completion', $qa_result->get_error_code(), 'the refusal names the invalid completion' );

$qa_result = $qa_policy->verify(
	7,
	array( 'session_token' => 'abc', 'completion_token' => 'proof' ),
	array( 'session' => $qa_session_row, 'coins' => 0 )
);
assertTrue( is_wp_error( $qa_result ), 'a zero reward is refused' );
assertSame( 'mcr_invalid_amount', $qa_result->get_error_code(), 'the refusal names the invalid amount' );

mcr_group( 'AdRewardPolicy::verify - expiry with a corrupt expires_at' );

/*
 * mcr_ad_sessions.expires_at is `datetime NOT NULL DEFAULT '1970-01-01
 * 00:00:00'`. strtotime() turns that default into the integer 0, which is
 * FALSY, so the expiry guard `if ( $expires && ... )` is skipped and a
 * session whose expiry did not survive is treated as live forever.
 */
$qa_zero_epoch = $qa_session_row;
$qa_zero_epoch['expires_at'] = '1970-01-01 00:00:00';

$qa_result = $qa_policy->verify(
	7,
	array( 'session_token' => 'abc', 'completion_token' => 'proof' ),
	array( 'session' => $qa_zero_epoch, 'coins' => 5 )
);

assertTrue(
	is_wp_error( $qa_result ),
	'a session whose expires_at is the zero epoch is refused as expired',
	'expected an error, got ' . ( is_array( $qa_result ) ? 'a granted reward' : 'nothing' )
);

$qa_garbage = $qa_session_row;
$qa_garbage['expires_at'] = 'not-a-date';

$qa_result = $qa_policy->verify(
	7,
	array( 'session_token' => 'abc', 'completion_token' => 'proof' ),
	array( 'session' => $qa_garbage, 'coins' => 5 )
);

assertTrue(
	is_wp_error( $qa_result ),
	'a session whose expires_at cannot be parsed is refused instead of living forever',
	'expected an error, got ' . ( is_array( $qa_result ) ? 'a granted reward' : 'nothing' )
);

mcr_group( 'AdRewardPolicy::verify - partially populated session row' );

$qa_partial = $qa_session_row;
unset( $qa_partial['status'] );

$qa_diagnostics = qa_collect_diagnostics(
	static function () use ( $qa_policy, $qa_partial ) {
		$qa_policy->verify(
			7,
			array( 'session_token' => 'abc', 'completion_token' => 'proof' ),
			array( 'session' => $qa_partial, 'coins' => 5 )
		);
	}
);

assertSame(
	array(),
	$qa_diagnostics,
	'a session row without a status column does not raise a PHP warning',
	'diagnostics: ' . implode( ' | ', $qa_diagnostics )
);

$qa_partial2 = $qa_session_row;
unset( $qa_partial2['user_id'] );

$qa_diagnostics = qa_collect_diagnostics(
	static function () use ( $qa_policy, $qa_partial2 ) {
		$qa_policy->verify(
			7,
			array( 'session_token' => 'abc', 'completion_token' => 'proof' ),
			array( 'session' => $qa_partial2, 'coins' => 5 )
		);
	}
);

assertSame(
	array(),
	$qa_diagnostics,
	'a session row without a user_id column does not raise a PHP warning',
	'diagnostics: ' . implode( ' | ', $qa_diagnostics )
);

mcr_group( 'AdRewardPolicy - signature helpers' );

$qa_signed = $qa_policy->sign_payload( array( 'user_id' => 7, 'session' => 'abc', 'coins' => 5, 'expires' => 100 ) );
assertTrue( $qa_policy->verify_signature( $qa_signed ), 'a freshly signed payload verifies' );

$qa_tampered = $qa_signed;
$qa_tampered['coins'] = 500;
assertFalse( $qa_policy->verify_signature( $qa_tampered ), 'a tampered payload does not verify' );

$qa_unsigned = $qa_signed;
unset( $qa_unsigned['signature'] );
assertFalse( $qa_policy->verify_signature( $qa_unsigned ), 'an unsigned payload does not verify' );

/* ==========================================================================
 * 7. Idempotency key abuse
 * ======================================================================== */

mcr_group( 'Idempotency keys built by the policies' );

$qa_ledger = new QA_Ledger();
$qa_policy = new CheckInPolicy( $qa_clock, $qa_ledger );

$qa_policy->attempt( 12, array( 'base_amount' => 10 ) );
$qa_policy->attempt( 12, array( 'base_amount' => 10, 'bonuses' => array( 3 => 5 ), 'previous_keys' => array( '2026-01-13', '2026-01-14' ) ) );

$qa_keys = $qa_ledger->keys();

assertSame( 'checkin:12:2026-01-15', $qa_keys[0], 'the base key is the user and the day' );
assertSame( 'checkin_bonus:12:2026-01-15', $qa_keys[2], 'the bonus key is the user and the day' );
assertTrue( strlen( $qa_keys[0] ) <= 191, 'the check-in key fits the idempotency_key column' );

/*
 * The day bucket must be part of the key, otherwise yesterday's grant and
 * today's grant collide and the second day is silently refused.
 */
$qa_yesterday = new FixedClock( '2026-01-14 10:00:00', 'UTC' );
$qa_ledger2   = new QA_Ledger();
$qa_policy2   = new CheckInPolicy( $qa_yesterday, $qa_ledger2 );
$qa_policy2->attempt( 12, array( 'base_amount' => 10 ) );

assertNotSame( $qa_keys[0], $qa_ledger2->keys()[0], 'the key changes with the day bucket' );

/* ==========================================================================
 * 8. Determinism audit - direct time calls in includes/
 * ======================================================================== */

mcr_group( 'Determinism - every time dependent rule goes through the clock' );

/**
 * Contexts where a direct time call is legitimate.
 *
 * - includes/Support/: the clock implementations themselves.
 * - Admin list screens: formatting an already stored timestamp.
 * - Templates date helpers: calendar maths on a date-only value, done in
 *   UTC on purpose (see Templates::format_date()).
 * - The gateway helper that formats a clock timestamp.
 * - The order id, which is a random identifier, not a rule.
 */
$qa_allowed_time_contexts = array(
	'#includes/Support/(Clock|WpClock|FixedClock)\.php#',
	'#includes/Admin/TransactionsPage\.php#',
	'#includes/Admin/LogsPage\.php#',
	'#includes/Admin/Admin\.php#',
	'#includes/Admin/DashboardPage\.php#',
	'#includes/AdProviders/GenericS2S\.php#',
	'#includes/Cron\.php#',
	'#includes/Gateways/Gateway\.php#',
	'#includes/Gateways/Stripe\.php.*time\(\)#',
	// The order id is a random identifier, not a rule.
	'#includes/Payments/Orders\.php.*gmdate\( \'YmdHis\'#',
	// A clock-derived timestamp formatted for the database.
	'#includes/Payments/Orders\.php.*gmdate\( \'Y-m-d H:i:s\', \$expiry#',
	'#includes/Rewards/AdSessions\.php.*gmdate\( \'Y-m-d H:i:s\',#',
	// Parsing a stored value is not a clock read (the comparison uses the clock).
	'#includes/Rewards/AdRewardPolicy\.php.*strtotime\( \$session\[#',
	'#includes/AdProviders/GenericS2S\.php.*strtotime\( \(string\) \$session\[#',
	'#includes/Templates/Templates\.php.*strtotime\( \$date#',
	'#includes/Templates/Templates\.php.*strtotime\( \$today_key#',
	'#includes/Templates/Templates\.php.*gmdate\( \$format, \$stamp#',
	'#includes/Templates/Templates\.php.*gmdate\( \'w\', \$stamp#',
	'#includes/Templates/Templates\.php.*gmdate\( \'Y-m-d\', \$stamp#',
	// Fallbacks that only run when the clock service is unavailable.
	'#includes/Templates/Templates\.php.*gmdate\( \'c\', time\(\) \+ \$seconds \)#',
	'#includes/Templates/Templates\.php.*gmdate\( \'c\' \)#',
	'#includes/Templates/Templates\.php.*gmdate\( \'Y-m-d\' \)#',
);

$qa_time_pattern = '/\b(time|current_time|gmdate|strtotime|microtime|hrtime|mktime)\s*\(/';

$qa_violations = array();
$qa_files      = array();

$qa_includes = dirname( __DIR__ ) . '/includes';
$qa_iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $qa_includes, FilesystemIterator::SKIP_DOTS ) );

foreach ( $qa_iterator as $qa_file ) {
	if ( ! $qa_file->isFile() || 'php' !== strtolower( $qa_file->getExtension() ) ) {
		continue;
	}

	$qa_path   = str_replace( '\\', '/', $qa_file->getPathname() );
	$qa_lines  = file( $qa_path, FILE_IGNORE_NEW_LINES );
	$qa_rel    = substr( $qa_path, strlen( dirname( __DIR__ ) ) + 1 );

	$qa_files[ $qa_rel ] = true;

	for ( $qa_i = 0; $qa_i < count( $qa_lines ); $qa_i++ ) {
		$qa_line = $qa_lines[ $qa_i ];

		if ( ! preg_match( $qa_time_pattern, $qa_line ) ) {
			continue;
		}

		// A DateTime construction is not a clock read: it is pure maths.
		if ( preg_match( '/new\s+\\\\?DateTime/', $qa_line ) ) {
			continue;
		}

		$qa_context = $qa_rel . ':' . ( $qa_i + 1 ) . ' ' . trim( $qa_line );

		$qa_ok = false;

		foreach ( $qa_allowed_time_contexts as $qa_pattern ) {
			if ( preg_match( $qa_pattern, $qa_context ) ) {
				$qa_ok = true;
				break;
			}
		}

		if ( ! $qa_ok ) {
			$qa_violations[] = $qa_context;
		}
	}
}

assertTrue(
	count( $qa_files ) > 30,
	'the determinism audit scanned the whole includes/ tree',
	'scanned ' . count( $qa_files ) . ' files'
);

assertSame(
	array(),
	$qa_violations,
	'no time dependent rule reads the wall clock directly; every decision goes through Support\\Clock',
	implode( "\n           ", $qa_violations )
);

/* ==========================================================================
 * Summary
 * ======================================================================== */

echo "\n==============================\n";

$qa_total = $GLOBALS['mcr_tests']['passed'] + $GLOBALS['mcr_tests']['failed'];

printf(
	"Assertions: %d | Passed: %d | Failed: %d\n",
	$qa_total,
	$GLOBALS['mcr_tests']['passed'],
	$GLOBALS['mcr_tests']['failed']
);

if ( $GLOBALS['mcr_tests']['failed'] > 0 ) {
	echo "\nFailures:\n";

	foreach ( $GLOBALS['mcr_tests']['failures'] as $qa_failure ) {
		printf( "  - [%s] %s\n", $qa_failure['group'], $qa_failure['message'] );

		if ( '' !== $qa_failure['detail'] ) {
			printf( "      %s\n", $qa_failure['detail'] );
		}
	}

	exit( 1 );
}

echo "OK\n";
exit( 0 );
