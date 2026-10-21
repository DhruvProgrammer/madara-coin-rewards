<?php
/**
 * MCR test suite.
 *
 * Run with: php tests/run-tests.php
 *
 * Self-contained: no PHPUnit, no Composer, no WordPress install required.
 * The pure logic classes of the plugin are loaded from their REAL source
 * files, so this suite tests the shipping code rather than a copy.
 *
 * @package MCR
 */

// The plugin files guard on ABSPATH.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

if ( ! defined( 'MCR_TEST_MODE' ) ) {
	define( 'MCR_TEST_MODE', 'REAL_PLUGIN_SOURCE' );
}

// The assertion framework and the WordPress stubs live in a shared file so
// this suite and the ledger suite behave identically.
require_once __DIR__ . '/bootstrap.php';

/* ==========================================================================
 * 3. Load the real plugin classes under test
 * ======================================================================== */

$base = dirname( __DIR__ ) . '/includes/';

require_once $base . 'Support/Clock.php';
require_once $base . 'Support/WpClock.php';
require_once $base . 'Support/FixedClock.php';
require_once $base . 'Ledger/BalanceMath.php';
require_once $base . 'Ledger/LedgerInterface.php';
require_once $base . 'Rewards/StreakCalculator.php';
require_once $base . 'Rewards/CheckInPolicy.php';
require_once $base . 'Rewards/AdRewardPolicy.php';
require_once $base . 'Payments/PackageCalculator.php';

use MCR\Ledger\BalanceMath;
use MCR\Payments\PackageCalculator;
use MCR\Rewards\AdRewardPolicy;
use MCR\Rewards\CheckInPolicy;
use MCR\Rewards\StreakCalculator;
use MCR\Support\FixedClock;

/* ==========================================================================
 * 4. Test doubles
 * ======================================================================== */

/**
 * In-memory ledger used to exercise the policy classes without a database.
 */
class Fake_Ledger implements \MCR\Ledger\LedgerInterface {

	/**
	 * Balance per user.
	 *
	 * @var array
	 */
	public $balances = array();

	/**
	 * Recorded credits.
	 *
	 * @var array
	 */
	public $credits = array();

	/**
	 * Recorded debits.
	 *
	 * @var array
	 */
	public $debits = array();

	/**
	 * {@inheritDoc}
	 */
	public function credit( $user_id, $amount, $type, $reason = '', $ctx = array() ) {
		$this->credits[] = array(
			'user_id' => $user_id,
			'amount'  => $amount,
			'type'    => $type,
			'reason'  => $reason,
			'ctx'     => $ctx,
		);

		$this->balances[ $user_id ] = ( $this->balances[ $user_id ] ?? 0 ) + $amount;

		return array(
			'transaction_id' => count( $this->credits ),
			'balance_after'  => $this->balances[ $user_id ],
			'duplicate'      => false,
			'amount'         => $amount,
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function debit( $user_id, $amount, $type, $reason = '', $ctx = array() ) {
		$this->debits[] = array(
			'user_id' => $user_id,
			'amount'  => $amount,
			'type'    => $type,
		);

		$this->balances[ $user_id ] = ( $this->balances[ $user_id ] ?? 0 ) - $amount;

		return array(
			'transaction_id' => 0,
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
}

/**
 * Stub ad provider whose verification result is controlled by the test.
 */
class Fake_Ad_Provider {

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
	 * Last payload passed to verify_completion().
	 *
	 * @var array
	 */
	public $last_payload = array();

	/**
	 * Whether is_configured().
	 *
	 * @return bool
	 */
	public function is_configured() {
		return $this->configured;
	}

	/**
	 * Verifies a completion.
	 *
	 * @param array $payload Payload.
	 * @return bool
	 */
	public function verify_completion( $payload ) {
		$this->last_payload = $payload;

		return $this->verification;
	}
}

/* ==========================================================================
 * 5. The tests
 * ======================================================================== */

echo "MCR Test Suite â€” Mode: " . MCR_TEST_MODE . "\n";
echo "====================================================\n";

/* --- StreakCalculator --------------------------------------------------- */

mcr_group( 'StreakCalculator::calculateStreakDay' );

assertSame(
	1,
	StreakCalculator::calculateStreakDay( '2026-01-15', array() ),
	'no history starts a streak at day 1'
);

assertSame(
	2,
	StreakCalculator::calculateStreakDay( '2026-01-15', array( '2026-01-14' ) ),
	'consecutive days extend the streak'
);

assertSame(
	4,
	StreakCalculator::calculateStreakDay( '2026-01-15', array( '2026-01-14', '2026-01-13', '2026-01-12' ) ),
	'three consecutive prior days make day 4'
);

assertSame(
	1,
	StreakCalculator::calculateStreakDay( '2026-01-15', array( '2026-01-13' ) ),
	'a broken streak resets to day 1'
);

assertSame(
	1,
	StreakCalculator::calculateStreakDay( '2026-01-15', array( '2026-01-01', '2026-01-02' ) ),
	'older history does not extend the streak'
);

assertSame(
	3,
	StreakCalculator::calculateStreakDay( '2028-03-01', array( '2028-02-28', '2028-02-29' ) ),
	'leap year Feb 28 -> Feb 29 -> Mar 1 counts consecutively'
);

assertSame(
	2,
	StreakCalculator::calculateStreakDay( '2026-03-01', array( '2026-02-28', '2026-02-29' ) ),
	'an impossible 29 February in a non-leap year is discarded, not counted as a gap'
);

assertSame(
	2,
	StreakCalculator::calculateStreakDay( '2026-01-01', array( '2025-12-31' ) ),
	'year boundary Dec 31 -> Jan 1 counts consecutively'
);

assertSame(
	1,
	StreakCalculator::calculateStreakDay( '', array( '2026-01-14' ) ),
	'an invalid today key falls back to day 1'
);

assertSame(
	2,
	StreakCalculator::calculateStreakDay( '2026-01-15', array( '2026-01-14', '2026-01-16' ) ),
	'a future date does not break the streak'
);

mcr_group( 'StreakCalculator::calculateBonus' );

assertSame(
	0,
	StreakCalculator::calculateBonus( 1, array( 3 => 5 ) ),
	'no bonus below the first threshold'
);

assertSame(
	5,
	StreakCalculator::calculateBonus( 3, array( 3 => 5 ) ),
	'bonus is granted exactly at the threshold'
);

assertSame(
	5,
	StreakCalculator::calculateBonus( 6, array( 3 => 5, 7 => 15 ) ),
	'the highest reached tier wins'
);

assertSame(
	15,
	StreakCalculator::calculateBonus( 7, array( 3 => 5, 7 => 15 ) ),
	'tier 7 grants its bonus'
);

assertSame(
	50,
	StreakCalculator::calculateBonus( 30, array( 3 => 5, 7 => 15, 30 => 50 ) ),
	'tier 30 grants its bonus'
);

assertSame(
	100,
	StreakCalculator::calculateBonus( 365, array( 30 => 500 ), 100 ),
	'the bonus is capped at max_bonus'
);

assertSame(
	50,
	StreakCalculator::calculateBonus( 365, array( 30 => 50 ), 100 ),
	'a bonus below the cap is granted in full'
);

assertSame(
	0,
	StreakCalculator::calculateBonus( 10, array() ),
	'an empty bonus map grants nothing'
);

assertSame(
	0,
	StreakCalculator::calculateBonus( 10, array( 'x' => 'y' ) ),
	'a malformed bonus map grants nothing'
);

assertSame(
	0,
	StreakCalculator::calculateBonus( 0, array( 1 => 100 ) ),
	'streak day 0 grants nothing'
);

mcr_group( 'StreakCalculator::normalizeKey' );

assertSame( '2026-01-15', StreakCalculator::normalizeKey( '2026-01-15' ), 'a valid key is preserved' );
assertSame( '', StreakCalculator::normalizeKey( '15-01-2026' ), 'a wrong-format key is rejected' );
assertSame( '', StreakCalculator::normalizeKey( '2026-02-30' ), 'an impossible date is rejected' );
assertSame( '', StreakCalculator::normalizeKey( 'not-a-date' ), 'garbage is rejected' );
assertSame(
	array( '2026-01-13', '2026-01-14' ),
	StreakCalculator::normalizeKeys( array( '2026-01-14', '2026-01-13', '2026-01-14', 'bad' ) ),
	'keys are de-duplicated and sorted, invalid ones dropped'
);

/* --- Clock -------------------------------------------------------------- */

mcr_group( 'Clock / day buckets' );

$clock = new FixedClock( '2026-01-15 23:59:59', 'UTC' );
assertSame( '2026-01-15', $clock->today_key(), 'the day key is the local calendar date' );
assertSame( 1, $clock->seconds_until_next_day(), 'one second remains before the next day' );

$clock = new FixedClock( '2026-01-15 00:00:00', 'UTC' );
assertSame( 86400, $clock->seconds_until_next_day(), 'a full day remains at midnight' );

$clock = new FixedClock( '2026-03-08 01:59:00', 'America/New_York' );
assertSame( '2026-03-08', $clock->today_key(), 'before the DST spring-forward jump' );
$clock = new FixedClock( '2026-03-08 03:01:00', 'America/New_York' );
assertSame( '2026-03-08', $clock->today_key(), 'after the DST spring-forward jump the day has not turned' );

$clock = new FixedClock( '2026-10-25 01:30:00', 'Europe/London' );
assertSame( '2026-10-25', $clock->today_key(), 'before the DST fall-back jump' );
$clock = new FixedClock( '2026-10-26 01:30:00', 'Europe/London' );
assertSame( '2026-10-26', $clock->today_key(), 'after the DST fall-back jump the day has turned' );

$clock = new FixedClock( '2026-01-15 12:00:00', 'Asia/Tokyo' );
assertSame( '2026-01-15', $clock->today_key(), 'the day bucket follows the site timezone, not UTC' );

/* --- CheckInPolicy ------------------------------------------------------ */

mcr_group( 'CheckInPolicy::attempt' );

$clock = new FixedClock( '2026-01-15 10:00:00', 'UTC' );
$ledger = new Fake_Ledger();

$policy = new CheckInPolicy( $clock, $ledger );

$result = $policy->attempt( 7, array( 'base_amount' => 10 ) );
assertFalse( is_wp_error( $result ), 'the first claim of the day succeeds' );
assertSame( 10, $result['total'], 'the base amount is granted' );
assertSame( 1, $result['streak'], 'the streak starts at 1' );
assertSame( '2026-01-15', $result['date_key'], 'the day bucket is recorded' );
assertSame( 1, count( $ledger->credits ), 'exactly one ledger row is written' );

$result = $policy->attempt( 7, array( 'base_amount' => 10, 'already_claimed' => true ) );
assertTrue( is_wp_error( $result ), 'a second claim the same day is refused' );
assertSame( 'mcr_already_claimed', $result->get_error_code(), 'the refusal reports already_claimed' );
assertSame( 1, count( $ledger->credits ), 'the refused claim writes no extra ledger row' );

$credits_before = count( $ledger->credits );

$result = $policy->attempt(
	7,
	array(
		'base_amount'   => 10,
		'bonuses'       => array( 3 => 5, 7 => 15 ),
		'previous_keys' => array( '2026-01-13', '2026-01-14' ),
	)
);
assertSame( 3, $result['streak'], 'the streak counts the previous consecutive days' );
assertSame( 5, $result['bonus'], 'the threshold bonus is applied' );
assertSame( 15, $result['total'], 'the total is base plus bonus' );
assertSame( 2, count( $ledger->credits ) - $credits_before, 'the base and the bonus are separate ledger rows' );

$result = $policy->attempt( 0, array() );
assertTrue( is_wp_error( $result ), 'an invalid user is refused' );

/* --- AdRewardPolicy ----------------------------------------------------- */

mcr_group( 'AdRewardPolicy::requestSession' );

$clock = new FixedClock( '2026-01-15 10:00:00', 'UTC' );
$ledger = new Fake_Ledger();

$policy = new AdRewardPolicy( $clock, $ledger, null );
assertFalse( $policy->providerConfigured(), 'no provider means not configured' );

$result = $policy->requestSession( 7, array( 'coins' => 5 ) );
assertTrue( is_wp_error( $result ), 'a session cannot start without a provider' );
assertSame( 'mcr_provider_not_configured', $result->get_error_code(), 'the refusal names the missing provider' );

$provider = new Fake_Ad_Provider();
$policy = new AdRewardPolicy( $clock, $ledger, $provider );
assertTrue( $policy->providerConfigured(), 'a configured provider is detected' );

$result = $policy->requestSession( 7, array( 'coins' => 5, 'daily_limit' => 10, 'watched_today' => 0 ) );
assertFalse( is_wp_error( $result ), 'a session starts when the limits allow it' );
assertSame( 64, strlen( $result['session_token'] ), 'the session token is 32 random bytes, hex encoded' );
assertSame( 5, $result['coins'], 'the session carries the coin value' );

$result = $policy->requestSession( 7, array( 'coins' => 5, 'daily_limit' => 10, 'watched_today' => 10 ) );
assertTrue( is_wp_error( $result ), 'the daily cap blocks a new session' );
assertSame( 'mcr_daily_limit_reached', $result->get_error_code(), 'the refusal names the daily limit' );

$policy = new AdRewardPolicy( $clock, $ledger, $provider );
$result = $policy->requestSession(
	7,
	array(
		'coins'            => 5,
		'daily_limit'      => 10,
		'watched_today'    => 1,
		'cooldown_seconds' => 300,
		'last_watch_at'    => $clock->now()->getTimestamp() - 60,
	)
);
assertTrue( is_wp_error( $result ), 'the cooldown blocks a new session' );
assertSame( 'mcr_cooldown_active', $result->get_error_code(), 'the refusal names the cooldown' );

$result = $policy->requestSession(
	7,
	array(
		'coins'            => 5,
		'daily_limit'      => 10,
		'watched_today'    => 1,
		'cooldown_seconds' => 300,
		'last_watch_at'    => $clock->now()->getTimestamp() - 400,
	)
);
assertFalse( is_wp_error( $result ), 'the session starts once the cooldown has expired' );

mcr_group( 'AdRewardPolicy::verify' );

$clock = new FixedClock( '2026-01-15 10:00:00', 'UTC' );
$ledger = new Fake_Ledger();
$provider = new Fake_Ad_Provider();
$policy = new AdRewardPolicy( $clock, $ledger, $provider );

$session = array(
	'id'         => 42,
	'user_id'    => 7,
	'ad_id'      => 'rewarded_video',
	'provider'   => 'generic-s2s',
	'status'     => 'pending',
	'coins'      => 5,
	'expires_at' => '2026-01-15 10:15:00',
);

$result = $policy->verify(
	7,
	array( 'session_token' => 'abc', 'completion_token' => 'proof' ),
	array( 'session' => $session, 'coins' => 5, 'daily_limit' => 10, 'watched_today' => 0 )
);
assertFalse( is_wp_error( $result ), 'a verified completion grants the reward' );
assertSame( 5, $result['coins'], 'the reward amount is granted' );
assertSame( 1, count( $ledger->credits ), 'exactly one ledger row is written' );
assertSame( 'ad_session:42', $ledger->credits[0]['ctx']['idempotency_key'], 'the credit is idempotent on the session' );

$result = $policy->verify(
	8,
	array( 'session_token' => 'abc', 'completion_token' => 'proof' ),
	array( 'session' => $session, 'coins' => 5 )
);
assertTrue( is_wp_error( $result ), 'a session belonging to another user is refused' );
assertSame( 'mcr_session_mismatch', $result->get_error_code(), 'the refusal names the ownership mismatch' );

$verified = $session;
$verified['status'] = 'verified';

$result = $policy->verify(
	7,
	array( 'session_token' => 'abc', 'completion_token' => 'proof' ),
	array( 'session' => $verified, 'coins' => 5 )
);
assertTrue( is_wp_error( $result ), 'an already verified session is refused' );
assertSame( 'mcr_already_verified', $result->get_error_code(), 'the refusal names the replay' );

$expired = $session;
$expired['expires_at'] = '2026-01-15 09:00:00';

$result = $policy->verify(
	7,
	array( 'session_token' => 'abc', 'completion_token' => 'proof' ),
	array( 'session' => $expired, 'coins' => 5 )
);
assertTrue( is_wp_error( $result ), 'an expired session is refused' );
assertSame( 'mcr_session_expired', $result->get_error_code(), 'the refusal names the expiry' );

$provider->verification = false;
$ledger->credits = array();

$result = $policy->verify(
	7,
	array( 'session_token' => 'abc', 'completion_token' => 'proof' ),
	array( 'session' => $session, 'coins' => 5 )
);
assertTrue( is_wp_error( $result ), 'a completion the provider rejects grants nothing' );
assertSame( 0, count( $ledger->credits ), 'no ledger row is written for a rejected completion' );

$provider->verification = true;
$policy = new AdRewardPolicy( $clock, $ledger, null );
$result = $policy->verify(
	7,
	array( 'session_token' => 'abc', 'completion_token' => 'proof' ),
	array( 'session' => $session, 'coins' => 5 )
);
assertTrue( is_wp_error( $result ), 'verification without a provider grants nothing' );
assertSame( 'mcr_provider_not_configured', $result->get_error_code(), 'the refusal names the missing provider' );

/* --- PackageCalculator -------------------------------------------------- */

mcr_group( 'PackageCalculator::calculateTotal' );

assertSame( 100, PackageCalculator::calculateTotal( 100 ), 'a package with no bonus returns the base' );
assertSame( 110, PackageCalculator::calculateTotal( 100, 10 ), 'a 10 percent bonus is added' );
assertSame( 200, PackageCalculator::calculateTotal( 100, 100 ), 'a 100 percent bonus doubles the amount' );
assertSame( 120, PackageCalculator::calculateTotal( 100, 50, 20 ), 'the bonus is capped' );
assertSame( 100, PackageCalculator::calculateTotal( 100, 0 ), 'a zero bonus changes nothing' );
assertSame( 0, PackageCalculator::calculateTotal( -50 ), 'a negative base is clamped to zero' );
assertSame( 200, PackageCalculator::calculateTotal( 100, 500 ), 'a bonus over 100 percent is clamped to 100 percent' );

mcr_group( 'PackageCalculator money conversion' );

assertSame( 1999, PackageCalculator::toMinorUnits( 19.99 ), 'a price converts to minor units' );
assertSame( 1000, PackageCalculator::toMinorUnits( 10 ), 'a whole price converts to minor units' );
assertSame( 19.99, PackageCalculator::fromMinorUnits( 1999 ), 'minor units convert back to a price' );
assertSame( 0, PackageCalculator::toMinorUnits( 'abc' ), 'a non-numeric price converts to zero' );

/* --- BalanceMath -------------------------------------------------------- */

mcr_group( 'BalanceMath::validate' );

assertSame( 10, BalanceMath::validate( 10 ), 'a positive integer is valid' );
assertSame( 10, BalanceMath::validate( '10' ), 'a numeric string is valid' );
assertFalse( BalanceMath::validate( 0 ), 'zero is rejected' );
assertFalse( BalanceMath::validate( -5 ), 'a negative amount is rejected' );
assertFalse( BalanceMath::validate( 5.0 ), 'a float is rejected even when it is whole' );
assertFalse( BalanceMath::validate( 5.5 ), 'a fractional float is rejected' );
assertFalse( BalanceMath::validate( true ), 'a boolean is rejected' );
assertFalse( BalanceMath::validate( null ), 'null is rejected' );
assertFalse( BalanceMath::validate( array( 5 ) ), 'an array is rejected' );
assertFalse( BalanceMath::validate( 'abc' ), 'a non-numeric string is rejected' );
assertFalse( BalanceMath::validate( '5abc' ), 'a partially numeric string is rejected' );
assertFalse( BalanceMath::validate( BalanceMath::MAX_BALANCE + 1 ), 'an amount above the ceiling is rejected' );

mcr_group( 'BalanceMath::credit and debit' );

assertSame( 110, BalanceMath::credit( 100, 10 ), 'a credit increases the balance' );
assertSame( 90, BalanceMath::debit( 100, 10 ), 'a debit decreases the balance' );
assertFalse( BalanceMath::debit( 100, 101 ), 'an overdraft is rejected' );
assertFalse( BalanceMath::debit( 100, 0 ), 'a zero debit is rejected' );
assertFalse( BalanceMath::credit( 100, 0 ), 'a zero credit is rejected' );
assertFalse( BalanceMath::credit( 100, -5 ), 'a negative credit is rejected' );
assertFalse( BalanceMath::credit( BalanceMath::MAX_BALANCE, 10 ), 'a credit that would overflow is rejected' );
assertTrue( BalanceMath::canAfford( 100, 100 ), 'an exact balance is affordable' );
assertFalse( BalanceMath::canAfford( 99, 100 ), 'one coin short is not affordable' );

mcr_group( 'BalanceMath drift over many transactions' );

$balance = 0;

for ( $i = 0; $i < 10000; $i++ ) {
	$balance = BalanceMath::credit( $balance, 3 );
	$balance = BalanceMath::debit( $balance, 1 );
}

assertSame( 20000, $balance, 'ten thousand credit/debit pairs leave no drift' );

/* --- Summary ------------------------------------------------------------ */

echo "\n";
echo "====================================================\n";

$total = $GLOBALS['mcr_tests']['passed'] + $GLOBALS['mcr_tests']['failed'];

printf(
	"Assertions: %d | Passed: %d | Failed: %d\n",
	$total,
	$GLOBALS['mcr_tests']['passed'],
	$GLOBALS['mcr_tests']['failed']
);

if ( $GLOBALS['mcr_tests']['failed'] > 0 ) {
	echo "\nFailures:\n";

	foreach ( $GLOBALS['mcr_tests']['failures'] as $failure ) {
		printf( "  - [%s] %s\n", $failure['group'], $failure['message'] );

		if ( '' !== $failure['detail'] ) {
			printf( "      %s\n", $failure['detail'] );
		}
	}

	exit( 1 );
}

echo "OK\n";
exit( 0 );
