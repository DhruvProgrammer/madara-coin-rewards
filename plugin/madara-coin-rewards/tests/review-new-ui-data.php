<?php
/**
 * DATA-WIRING & CORRECTNESS review: the NEW daily check-in page UI.
 *
 * Run with: php tests/review-new-ui-data.php
 *
 * Scope: the data the check-in page prepares (the wallet header and the ad
 * panel), the "earned today" arithmetic, the profile helper, the ad panel's
 * fail-closed behaviour, the ad unit normalisation, the query budget and
 * the promise the wallet markup makes to the frontend script.
 *
 * The suite asserts the CONTRACT, not an implementation: every number the
 * page shows must be the number the ledger holds, the page must never
 * offer a button that cannot pay, and the page must not spend queries on
 * figures it does not show.
 *
 * Assertions that describe behaviour that is already correct are marked
 * "control:" in their message.
 *
 * @package MCR
 */

namespace MCR\Templates {

	/**
	 * Test double for get_userdata() as the MCR\Templates code sees it.
	 *
	 * An unqualified call inside a namespace resolves to the namespaced
	 * function first, so this override is what Shortcodes::profile()
	 * actually calls. The global stub always answers null, which would make
	 * every profile case untestable.
	 *
	 * @param int $id User id.
	 * @return object|false
	 */
	function get_userdata( $id ) {
		$users = isset( $GLOBALS['mcrn_users'] ) ? $GLOBALS['mcrn_users'] : array();
		$id    = (int) $id;

		if ( ! isset( $users[ $id ] ) ) {
			return false;
		}

		return $users[ $id ];
	}

	/**
	 * Test double for get_avatar_url().
	 *
	 * @param mixed  $id_or_email User.
	 * @param int    $size        Size.
	 * @param string $default     Default.
	 * @return string
	 */
	function get_avatar_url( $id_or_email, $size = 96, $default = '' ) {
		$users = isset( $GLOBALS['mcrn_users'] ) ? $GLOBALS['mcrn_users'] : array();
		$id    = (int) $id_or_email;

		if ( isset( $users[ $id ] ) && ! empty( $users[ $id ]->avatar ) ) {
			return (string) $users[ $id ]->avatar;
		}

		return '';
	}
}

namespace {

	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', dirname( __DIR__ ) . '/' );
	}

	require_once __DIR__ . '/bootstrap.php';
	require_once __DIR__ . '/wp-stubs.php';

	/* ==========================================================================
	 * 1. Boot
	 * ======================================================================== */

	$plugin_dir = dirname( __DIR__ ) . '/';

	require_once $plugin_dir . 'includes/Autoloader.php';

	\MCR\Autoloader::register( $plugin_dir );

	require_once $plugin_dir . 'madara-coin-rewards.php';

	mcr_bootstrap();

	/* ==========================================================================
	 * 2. A programmable $wpdb
	 *
	 * MCR_Fake_WPDB only records write statements. The check-in page reads
	 * everything, so this subclass records the reads too and answers them
	 * from a programmable map.
	 * ======================================================================== */

	/**
	 * A programmable fake database.
	 */
	class MCRN_WPDB extends MCR_Fake_WPDB {

		/**
		 * Every statement, reads included.
		 *
		 * @var array
		 */
		public $log = array();

		/**
		 * Scalar results, keyed by a '|'-separated needle list.
		 *
		 * @var array
		 */
		public $vars = array();

		/**
		 * Single row results.
		 *
		 * @var array
		 */
		public $rows = array();

		/**
		 * Row list results.
		 *
		 * @var array
		 */
		public $results = array();

		/**
		 * Column results.
		 *
		 * @var array
		 */
		public $cols = array();

		/**
		 * The ledger rows the earned-today query reads.
		 *
		 * @var array
		 */
		public $transactions = array();

		/**
		 * Clears the log and every programmed result.
		 *
		 * @return void
		 */
		public function reset() {
			$this->log         = array();
			$this->vars        = array();
			$this->rows        = array();
			$this->results     = array();
			$this->cols        = array();
			$this->transactions = array();
		}

		/**
		 * Sums the ledger rows the way the query in front of us asks for.
		 *
		 * The two shapes the plugin can use are both implemented, so the
		 * test can tell them apart:
		 *
		 *  - `amount > 0` ignores the rows that take coins back;
		 *  - the reconcile formula (`type IN ( 'reversal', 'refund' )`)
		 *    nets them off.
		 *
		 * @param string $sql SQL.
		 * @return int
		 */
		private function ledger_sum( $sql ) {
			$sum = 0;

			foreach ( $this->transactions as $row ) {
				$amount = (int) $row['amount'];
				$type   = (string) $row['type'];

				if ( false !== stripos( $sql, 'amount > 0' ) ) {
					if ( $amount <= 0 ) {
						continue;
					}

					$sum += $amount;

					continue;
				}

				if ( $amount < 0 && ! in_array( $type, array( 'reversal', 'refund' ), true ) ) {
					// A spend is not an earning.
					continue;
				}

				$sum += $amount;
			}

			return $sum;
		}

		/**
		 * Finds the first programmed result whose needles all match.
		 *
		 * @param array  $map     Needle map.
		 * @param string $sql     SQL.
		 * @param mixed  $default Default value.
		 * @return mixed
		 */
		private function pick( $map, $sql, $default ) {
			foreach ( $map as $needle => $value ) {
				$ok = true;

				foreach ( explode( '|', (string) $needle ) as $part ) {
					if ( false === stripos( $sql, $part ) ) {
						$ok = false;
						break;
					}
				}

				if ( $ok ) {
					return $value;
				}
			}

			return $default;
		}

		/**
		 * Records a write.
		 *
		 * @param string $sql SQL.
		 * @return int
		 */
		public function query( $sql ) {
			$this->log[] = $sql;

			return 1;
		}

		/**
		 * Records a scalar read.
		 *
		 * @param string $sql SQL.
		 * @return mixed
		 */
		public function get_var( $sql ) {
			$this->log[] = $sql;

			if ( false !== stripos( $sql, 'COALESCE(SUM(amount), 0)' ) && false !== stripos( $sql, 'mcr_transactions' ) ) {
				if ( ! empty( $this->transactions ) ) {
					return $this->ledger_sum( $sql );
				}
			}

			return $this->pick( $this->vars, $sql, null );
		}

		/**
		 * Records a single row read.
		 *
		 * @param string $sql  SQL.
		 * @param string $type Result type.
		 * @return mixed
		 */
		public function get_row( $sql, $type = 'OBJECT' ) {
			$this->log[] = $sql;

			$row = $this->pick( $this->rows, $sql, null );

			if ( null === $row ) {
				return null;
			}

			if ( is_array( $row ) && 'ARRAY_A' !== $type ) {
				return (object) $row;
			}

			return $row;
		}

		/**
		 * Records a row list read.
		 *
		 * @param string $sql  SQL.
		 * @param string $type Result type.
		 * @return array
		 */
		public function get_results( $sql, $type = 'OBJECT' ) {
			$this->log[] = $sql;

			$rows = $this->pick( $this->results, $sql, array() );

			if ( 'ARRAY_A' === $type ) {
				return $rows;
			}

			$out = array();

			foreach ( (array) $rows as $row ) {
				$out[] = is_array( $row ) ? (object) $row : $row;
			}

			return $out;
		}

		/**
		 * Records a column read.
		 *
		 * @param string $sql SQL.
		 * @return array
		 */
		public function get_col( $sql ) {
			$this->log[] = $sql;

			return $this->pick( $this->cols, $sql, array() );
		}
	}

	/* ==========================================================================
	 * 3. Test providers
	 * ======================================================================== */

	/**
	 * A configurable ad provider.
	 */
	class MCRN_Provider extends \MCR\AdProviders\Provider {

		/**
		 * The provider slug.
		 *
		 * @return string
		 */
		public function get_id() {
			return 'mcrn_fake';
		}

		/**
		 * The provider title.
		 *
		 * @return string
		 */
		public function get_title() {
			return 'MCRN fake provider';
		}

		/**
		 * Whether the provider is configured.
		 *
		 * @return bool
		 */
		public function is_configured() {
			return ! empty( $GLOBALS['mcrn_provider_configured'] );
		}

		/**
		 * The ad units.
		 *
		 * @return array
		 */
		public function get_ad_units() {
			return isset( $GLOBALS['mcrn_provider_units'] ) ? $GLOBALS['mcrn_provider_units'] : array();
		}

		/**
		 * Creates a session.
		 *
		 * @param array $args Arguments.
		 * @return array
		 */
		public function create_session( array $args ) {
			return array();
		}

		/**
		 * Verifies a completion.
		 *
		 * @param array $payload Payload.
		 * @return true
		 */
		public function verify_completion( array $payload ) {
			return true;
		}

		/**
		 * The settings fields.
		 *
		 * @return array
		 */
		public function get_settings_fields() {
			return array();
		}
	}

	/**
	 * A provider whose configuration check explodes.
	 */
	class MCRN_Throwing_Provider extends \MCR\AdProviders\Provider {

		/**
		 * The provider slug.
		 *
		 * @return string
		 */
		public function get_id() {
			return 'mcrn_throwing';
		}

		/**
		 * The provider title.
		 *
		 * @return string
		 */
		public function get_title() {
			return 'MCRN throwing provider';
		}

		/**
		 * Explodes instead of answering.
		 *
		 * @return bool
		 * @throws \RuntimeException Always.
		 */
		public function is_configured() {
			throw new \RuntimeException( 'provider exploded' );
		}

		/**
		 * The ad units.
		 *
		 * @return array
		 */
		public function get_ad_units() {
			return array();
		}

		/**
		 * Creates a session.
		 *
		 * @param array $args Arguments.
		 * @return array
		 */
		public function create_session( array $args ) {
			return array();
		}

		/**
		 * Verifies a completion.
		 *
		 * @param array $payload Payload.
		 * @return true
		 */
		public function verify_completion( array $payload ) {
			return true;
		}

		/**
		 * The settings fields.
		 *
		 * @return array
		 */
		public function get_settings_fields() {
			return array();
		}
	}

	/* ==========================================================================
	 * 4. Harness
	 * ======================================================================== */

	global $wpdb;

	$wpdb = new MCRN_WPDB();

	$GLOBALS['mcrn_users']                = array();
	$GLOBALS['mcrn_provider_configured'] = false;
	$GLOBALS['mcrn_provider_units']       = array();
	$GLOBALS['mcrn_captured']             = array();
	$GLOBALS['mcr_current_user']          = 0;

	/*
	 * The clock is frozen so the day bucket is deterministic.
	 */
	$clock = new \MCR\Support\FixedClock( '2026-10-21 12:00:00', 'UTC' );

	\MCR\Container::instance()->set_clock( $clock );

	$today_key = $clock->today_key();

	/*
	 * The fake provider is registered through the documented filter.
	 */
	add_filter(
		'mcr_register_ad_providers',
		function ( $instances ) {
			$instances['mcrn_fake']     = new MCRN_Provider();
			$instances['mcrn_throwing'] = new MCRN_Throwing_Provider();

			return $instances;
		}
	);

	\MCR\AdProviders\Registry::reset();

	/*
	 * The page arguments are captured so the tests can assert on the data
	 * the controller prepared rather than on the markup.
	 */
	add_filter(
		'mcr_page_args',
		function ( $args, $slug = '', $user_id = 0 ) {
			$GLOBALS['mcrn_captured'][ $slug ] = $args;

			return $args;
		}
	);

	/**
	 * Seeds the fake database with a check-in / ad scenario.
	 *
	 * @param array $opts Scenario:
	 *                    - claimed       bool   The user claimed today.
	 *                    - total         int    The check-in row's total_amount.
	 *                    - bonus         int    The check-in row's bonus_amount.
	 *                    - ads_today     int    Counted ad sessions today.
	 *                    - ads_total     int    Counted ad sessions overall.
	 *                    - ads_coins     int    SUM(coins) over all sessions.
	 *                    - ads_today_sum int    SUM(coins) over today's sessions.
	 *                    - ledger_today  int    The ledger's credits for today.
	 *                    - txns          array  The ledger rows of today.
	 *                    - balance       int    The balance row's balance.
	 *                    - lifetime      int    The balance row's lifetime_earned.
	 * @return void
	 */
	function mcrn_seed( array $opts = array() ) {
		global $wpdb;

		$opts = array_merge(
			array(
				'claimed'       => true,
				'total'         => 60,
				'bonus'         => 50,
				'ads_today'     => 0,
				'ads_total'     => 0,
				'ads_coins'     => 0,
				'ads_today_sum' => 0,
				'ledger_today'  => 0,
				'txns'          => array(),
				'balance'       => 0,
				'lifetime'      => 0,
				'spent'         => 0,
				'purchased'     => 0,
			),
			$opts
		);

		$wpdb->reset();

		if ( $opts['claimed'] ) {
			$wpdb->rows['SELECT * FROM wp_mcr_checkins'] = array(
				'id'             => 1,
				'user_id'        => 5,
				'checkin_date'   => $GLOBALS['mcrn_today'],
				'streak_count'   => 3,
				'base_amount'    => 10,
				'bonus_amount'   => $opts['bonus'],
				'total_amount'   => $opts['total'],
				'transaction_id' => 1,
				'ip'             => '',
				'created_at'     => '2026-10-21 09:00:00',
			);
		}

		$wpdb->cols['SELECT checkin_date FROM wp_mcr_checkins'] = array( '2026-10-20', '2026-10-19' );

		$wpdb->results['SELECT checkin_date, streak_count, total_amount'] = array(
			array(
				'checkin_date' => $GLOBALS['mcrn_today'],
				'streak_count' => 3,
				'total_amount' => $opts['total'],
			),
		);

		$wpdb->vars['COUNT(*)|mcr_ad_sessions|verified_at >='] = (int) $opts['ads_today'];
		$wpdb->vars['COUNT(*)|mcr_ad_sessions']              = (int) $opts['ads_total'];
		$wpdb->vars['COALESCE(SUM(coins), 0)|verified_at >='] = (int) $opts['ads_today_sum'];
		$wpdb->vars['COALESCE(SUM(coins), 0)']                = (int) $opts['ads_coins'];

		/*
		 * The coins the ledger credited today.
		 */
		$wpdb->vars['COALESCE(SUM(amount), 0)|mcr_transactions'] = (int) $opts['ledger_today'];

		$wpdb->rows['FROM wp_mcr_balances'] = array(
			'balance'         => (int) $opts['balance'],
			'lifetime_earned' => (int) $opts['lifetime'],
			'lifetime_spent'  => (int) $opts['spent'],
			'purchased_total' => (int) $opts['purchased'],
		);

		$wpdb->transactions = (array) $opts['txns'];
	}

	/**
	 * Renders a plugin page and returns its markup and prepared arguments.
	 *
	 * A page that fatals is reported, not thrown: the harness has to keep
	 * running so the failure can be described.
	 *
	 * @param string $slug    Page slug.
	 * @param int    $user_id The current user.
	 * @return array {html:string,args:array,error:string}
	 */
	function mcrn_render( $slug = 'checkin', $user_id = 5 ) {
		global $wpdb;

		$GLOBALS['mcr_current_user'] = (int) $user_id;
		$GLOBALS['mcrn_captured']    = array();

		$wpdb->log = array();

		$html  = '';
		$error = '';

		try {
			$html = \MCR\Templates\Shortcodes::instance()->render( $slug, array() );
		} catch ( \Throwable $e ) {
			$error = get_class( $e ) . ': ' . $e->getMessage();
		}

		return array(
			'html'  => (string) $html,
			'args'  => isset( $GLOBALS['mcrn_captured'][ $slug ] ) ? $GLOBALS['mcrn_captured'][ $slug ] : array(),
			'error' => $error,
		);
	}

	/**
	 * Returns the first data-mcr-amount value that follows a marker.
	 *
	 * @param string $html   Markup.
	 * @param string $needle Marker text.
	 * @return int|null
	 */
	function mcrn_amount_after( $html, $needle ) {
		$pos = strpos( $html, $needle );

		if ( false === $pos ) {
			return null;
		}

		$tail = substr( $html, $pos, 600 );

		if ( preg_match( '/data-mcr-amount="(-?\d+)"/', $tail, $m ) ) {
			return (int) $m[1];
		}

		return null;
	}

	/**
	 * Counts the occurrences of a substring.
	 *
	 * @param string $haystack Haystack.
	 * @param string $needle   Needle.
	 * @return int
	 */
	function mcrn_count( $haystack, $needle ) {
		return substr_count( (string) $haystack, (string) $needle );
	}

	$GLOBALS['mcrn_today'] = $today_key;

	/*
	 * A clean starting point for the settings.
	 */
	\MCR\Settings::update(
		array(
			'features.ads_enabled'     => 'on',
			'features.checkin_enabled' => 'on',
			'ads.provider'             => '',
			'ads.coins_per_ad'         => 5,
			'ads.daily_limit'          => 10,
			'ads.cooldown_seconds'     => 300,
			'checkin.base_amount'      => 10,
			'checkin.bonuses'          => array( 3 => 5, 7 => 15, 30 => 50 ),
			'checkin.max_bonus'        => 100,
		)
	);

	\MCR\Settings::flush_cache();

	echo "MCR New-UI Data Wiring & Correctness Review\n";
	echo "===========================================\n\n";

	/* ==========================================================================
	 * 5. The page has to render at all
	 * ======================================================================== */

	mcr_group( 'CRITICAL: the check-in page renders' );

	mcrn_seed(
		array(
			'claimed'       => true,
			'total'         => 60,
			'ads_today'     => 2,
			'ads_total'     => 7,
			'ads_coins'     => 35,
			'ads_today_sum' => 10,
			'ledger_today'  => 70,
			'balance'       => 70,
			'lifetime'      => 70,
		)
	);

	$render = mcrn_render( 'checkin', 5 );

	assertSame(
		'',
		$render['error'],
		'the check-in page renders without a fatal error'
	);

	if ( '' !== $render['error'] ) {
		echo "\n  (the check-in page did not render; the data assertions were skipped)\n\n";
	} else {
		/* ======================================================================
		 * 6. The prepared data
		 * ==================================================================== */

		mcr_group( 'Check-in page: the wallet and ad data are prepared' );

		$args = $render['args'];

		assertTrue( isset( $args['stats'] ) && is_array( $args['stats'] ), 'the wallet statistics are prepared' );
		assertTrue( isset( $args['profile'] ) && is_array( $args['profile'] ), 'the profile summary is prepared' );
		assertTrue( array_key_exists( 'today_earned', $args ), 'the earned-today figure is prepared' );
		assertTrue( isset( $args['ads'] ) && is_array( $args['ads'] ), 'the ad statistics are prepared' );
		assertTrue( array_key_exists( 'ads_enabled', $args ), 'the ads feature switch is prepared' );
		assertTrue( array_key_exists( 'ads_configured', $args ), 'the ads provider state is prepared' );
		assertTrue( isset( $args['ad_units'] ) && is_array( $args['ad_units'] ), 'the ad units are prepared' );
		assertTrue( array_key_exists( 'ads_cooldown', $args ), 'the ad cooldown is prepared' );
		assertTrue( array_key_exists( 'ads_reset', $args ), 'the ad cap reset deadline is prepared' );

		/* ======================================================================
		 * 7. "Earned today" against the ledger
		 * ==================================================================== */

		mcr_group( 'Earned today: the normal case matches the ledger' );

		/*
		 * The user claimed today (10 base + 50 bonus) and watched two ads
		 * that paid 5 coins each. The ledger holds 60 + 10 = 70 coins for
		 * today.
		 */
		mcrn_seed(
			array(
				'claimed'       => true,
				'total'         => 60,
				'bonus'         => 50,
				'ads_today'     => 2,
				'ads_total'     => 7,
				'ads_coins'     => 35,
				'ads_today_sum' => 10,
				'ledger_today'  => 70,
				'balance'       => 70,
				'lifetime'      => 70,
			)
		);

		$render = mcrn_render( 'checkin', 5 );

		assertSame( 70, (int) $render['args']['today_earned'], 'control: earned today equals the ledger total in the normal case' );
		assertSame( 70, (int) $render['args']['stats']['balance'], 'control: the balance comes from the ledger' );

		mcr_group( 'Earned today: the rate cannot change while the day runs' );

		/*
		 * The site owner raised the ad rate from 5 to 20 after the first ad
		 * of the day. The sessions the user watched paid the 5 coins that
		 * were frozen on the session rows (and that the ledger credited);
		 * the current setting says 20.
		 */
		\MCR\Settings::update(
			array(
				'ads.coins_per_ad' => 20,
				'ads.provider'     => 'mcrn_fake',
			)
		);
		\MCR\Settings::flush_cache();

		$GLOBALS['mcrn_provider_configured'] = true;
		$GLOBALS['mcrn_provider_units']       = array(
			array(
				'id'    => 'unit_a',
				'label' => 'Unit A',
			),
		);

		mcrn_seed(
			array(
				'claimed'       => true,
				'total'         => 60,
				'bonus'         => 50,
				'ads_today'     => 2,
				'ads_total'     => 7,
				'ads_coins'     => 35,
				'ads_today_sum' => 10,
				'ledger_today'  => 70,
				'balance'       => 70,
				'lifetime'      => 70,
			)
		);

		$render = mcrn_render( 'checkin', 5 );

		assertSame(
			70,
			(int) $render['args']['today_earned'],
			'the earned-today figure equals the coins the ledger actually credited today'
		);

		assertSame(
			10,
			mcrn_amount_after( $render['html'], 'Earned from ads today' ),
			'the ad panel states the coins the ad sessions really paid today'
		);

		\MCR\Settings::update( array( 'ads.coins_per_ad' => 5 ) );
		\MCR\Settings::flush_cache();

		mcr_group( 'Earned today: every credit the ledger recorded counts' );

		/*
		 * The user claimed today (60) and watched two ads (10). The admin
		 * then granted 100 coins with the coin tool, which the ledger
		 * records as an admin_adjust credit of +100 today.
		 */
		mcrn_seed(
			array(
				'claimed'       => true,
				'total'         => 60,
				'bonus'         => 50,
				'ads_today'     => 2,
				'ads_total'     => 7,
				'ads_coins'     => 35,
				'ads_today_sum' => 10,
				'ledger_today'  => 170,
				'balance'       => 170,
				'lifetime'      => 170,
			)
		);

		$render = mcrn_render( 'checkin', 5 );

		assertSame(
			170,
			(int) $render['args']['today_earned'],
			'the earned-today figure is the ledger credit total for today'
		);

		assertSame(
			170,
			(int) $render['args']['stats']['lifetime_earned'],
			'control: the lifetime figure the ledger keeps is the one the wallet shows'
		);

		mcr_group( 'Earned today: a reward that was taken back is not an earning' );

		/*
		 * The check-in bonus failed after the base was paid, so the base was
		 * reversed (CheckInPolicy.php:224-238) and the claim record was
		 * removed. The user claimed again and was paid the base once more.
		 * The ledger therefore holds +10, -10 (reversal) and +10 today, and
		 * the balance row says lifetime_earned 10.
		 */
		mcrn_seed(
			array(
				'claimed'   => false,
				'total'     => 0,
				'ads_today' => 0,
				'txns'      => array(
					array(
						'user_id'    => 5,
						'type'       => 'checkin',
						'amount'     => 10,
						'created_at' => '2026-10-21 09:00:00',
					),
					array(
						'user_id'    => 5,
						'type'       => 'reversal',
						'amount'     => -10,
						'created_at' => '2026-10-21 09:00:01',
					),
					array(
						'user_id'    => 5,
						'type'       => 'checkin',
						'amount'     => 10,
						'created_at' => '2026-10-21 09:05:00',
					),
				),
				'balance'  => 10,
				'lifetime' => 10,
			)
		);

		$render = mcrn_render( 'checkin', 5 );

		assertSame(
			10,
			(int) $render['args']['today_earned'],
			'the earned-today figure is the net of the credits the ledger recorded today'
		);

		assertTrue(
			(int) $render['args']['today_earned'] <= (int) $render['args']['stats']['lifetime_earned'],
			'today\'s earnings never exceed the lifetime earnings'
		);

		mcr_group( 'Earned today: a user who has not claimed yet' );

		mcrn_seed(
			array(
				'claimed'       => false,
				'total'         => 0,
				'ads_today'     => 0,
				'ads_total'     => 3,
				'ads_coins'     => 15,
				'ads_today_sum' => 0,
				'ledger_today'  => 0,
				'balance'       => 15,
				'lifetime'      => 15,
			)
		);

		$render = mcrn_render( 'checkin', 5 );

		assertSame( 0, (int) $render['args']['today_earned'], 'control: nothing claimed and no ad watched means nothing earned today' );
		assertSame( 15, (int) $render['args']['stats']['lifetime_earned'], 'control: the lifetime total is untouched' );

		mcr_group( 'Earned today: the ad coins are not counted twice' );

		mcrn_seed(
			array(
				'claimed'       => true,
				'total'         => 60,
				'bonus'         => 50,
				'ads_today'     => 3,
				'ads_total'     => 9,
				'ads_coins'     => 45,
				'ads_today_sum' => 15,
				'ledger_today'  => 75,
				'balance'       => 75,
				'lifetime'      => 75,
			)
		);

		$render = mcrn_render( 'checkin', 5 );

		assertSame( 75, (int) $render['args']['today_earned'], 'control: the ad coins are added once, not twice' );

		/* ======================================================================
		 * 8. The profile helper
		 * ==================================================================== */

		mcr_group( 'Profile: a deleted user' );

		$GLOBALS['mcrn_users'] = array();

		mcrn_seed( array( 'claimed' => true, 'total' => 60 ) );

		$render = mcrn_render( 'checkin', 5 );

		assertSame( '', (string) $render['args']['profile']['name'], 'control: a deleted user has no display name' );
		assertSame( '', (string) $render['args']['profile']['initials'], 'control: a deleted user has no initials' );
		assertTrue(
			false !== strpos( $render['html'], 'Your coin wallet' ),
			'control: the wallet falls back to a generic label'
		);

		mcr_group( 'Profile: the initials are two characters, not two bytes' );

		$GLOBALS['mcrn_users'] = array(
			5 => (object) array(
				'display_name' => 'Ödipus Rex',
				'user_login'   => 'odipus',
			),
		);

		mcrn_seed( array( 'claimed' => true, 'total' => 60 ) );

		$render = mcrn_render( 'checkin', 5 );

		assertSame(
			2,
			mb_strlen( (string) $render['args']['profile']['initials'], 'UTF-8' ),
			'the initials are two characters long'
		);

		mcr_group( 'Profile: the initials never run past the second word' );

		$GLOBALS['mcrn_users'] = array(
			5 => (object) array(
				'display_name' => '张 伟 三',
				'user_login'   => 'zhang',
			),
		);

		mcrn_seed( array( 'claimed' => true, 'total' => 60 ) );

		$render = mcrn_render( 'checkin', 5 );

		assertSame(
			2,
			mb_strlen( (string) $render['args']['profile']['initials'], 'UTF-8' ),
			'the initials never exceed two characters'
		);

		mcr_group( 'Profile: the ordinary cases' );

		$GLOBALS['mcrn_users'] = array(
			5 => (object) array(
				'display_name' => 'Ada Lovelace',
				'user_login'   => 'ada',
			),
		);

		mcrn_seed( array( 'claimed' => true, 'total' => 60 ) );

		$render = mcrn_render( 'checkin', 5 );

		assertSame( 'AL', (string) $render['args']['profile']['initials'], 'control: two words give two initials' );
		assertSame( 'Ada Lovelace', (string) $render['args']['profile']['name'], 'control: the display name is used' );

		$GLOBALS['mcrn_users'] = array(
			5 => (object) array(
				'display_name' => 'Cher',
				'user_login'   => 'cher',
			),
		);

		$render = mcrn_render( 'checkin', 5 );

		assertTrue(
			'' !== (string) $render['args']['profile']['initials'],
			'control: one word still gives an initial'
		);
		assertTrue(
			mb_strlen( (string) $render['args']['profile']['initials'], 'UTF-8' ) <= 2,
			'control: one word never gives more than two initials'
		);

		$GLOBALS['mcrn_users'] = array(
			5 => (object) array(
				'display_name' => 'Jean-Luc Picard',
				'user_login'   => 'jl',
			),
		);

		$render = mcrn_render( 'checkin', 5 );

		assertSame( 'JP', (string) $render['args']['profile']['initials'], 'control: a hyphenated first name still gives one initial' );

		$GLOBALS['mcrn_users'] = array(
			5 => (object) array(
				'display_name' => "O'Brien",
				'user_login'   => 'obrien',
			),
		);

		$render = mcrn_render( 'checkin', 5 );

		assertTrue(
			false !== strpos( $render['html'], 'O&#039;Brien' ) || false !== strpos( $render['html'], 'O&#39;Brien' ),
			'control: an apostrophe in the display name is escaped'
		);
		assertFalse(
			false !== strpos( $render['html'], "O'Brien" ),
			'control: the raw apostrophe never reaches the markup'
		);

		mcr_group( 'Profile: a name that is only whitespace' );

		$GLOBALS['mcrn_users'] = array(
			5 => (object) array(
				'display_name' => '   ',
				'user_login'   => 'spaced',
			),
		);

		mcrn_seed( array( 'claimed' => true, 'total' => 60 ) );

		$render = mcrn_render( 'checkin', 5 );

		assertTrue(
			'' !== trim( (string) $render['args']['profile']['name'] ),
			'a whitespace-only display name is not used as the wallet name'
		);

		mcr_group( 'Profile: a user without a display name' );

		$GLOBALS['mcrn_users'] = array(
			5 => (object) array(
				'display_name' => '',
				'user_login'   => 'noname',
			),
		);

		mcrn_seed( array( 'claimed' => true, 'total' => 60 ) );

		$render = mcrn_render( 'checkin', 5 );

		assertTrue(
			'' !== trim( (string) $render['args']['profile']['name'] ),
			'a user without a display name falls back to the login name'
		);

		mcr_group( 'Profile: the right-to-left and emoji names' );

		$GLOBALS['mcrn_users'] = array(
			5 => (object) array(
				'display_name' => 'מיכאל',
				'user_login'   => 'michael',
			),
		);

		mcrn_seed( array( 'claimed' => true, 'total' => 60 ) );

		$render = mcrn_render( 'checkin', 5 );

		assertTrue(
			'' !== (string) $render['args']['profile']['initials'],
			'control: a right-to-left name still produces an initial'
		);

		$GLOBALS['mcrn_users'] = array(
			5 => (object) array(
				'display_name' => '😀',
				'user_login'   => 'emoji',
			),
		);

		mcrn_seed( array( 'claimed' => true, 'total' => 60 ) );

		$render = mcrn_render( 'checkin', 5 );

		assertTrue(
			'' !== (string) $render['args']['profile']['initials'],
			'control: an emoji name still produces an avatar fallback'
		);

		/* ======================================================================
		 * 9. The ad panel fails closed
		 * ==================================================================== */

		mcr_group( 'Ad panel: it never offers a button that cannot pay' );

		$GLOBALS['mcrn_provider_configured'] = true;
		$GLOBALS['mcrn_provider_units']       = array(
			array(
				'id'    => 'unit_a',
				'label' => 'Unit A',
			),
		);

		// 9a. No provider selected at all.
		\MCR\Settings::update( array( 'ads.provider' => '' ) );
		\MCR\Settings::flush_cache();
		\MCR\AdProviders\Registry::reset();

		mcrn_seed( array( 'claimed' => true, 'total' => 60 ) );

		$render = mcrn_render( 'checkin', 5 );

		assertFalse( (bool) $render['args']['ads_configured'], 'control: an empty provider registry is not configured' );
		assertSame( 0, mcrn_count( $render['html'], 'data-mcr-ad-start' ), 'control: no provider means no ad button' );

		// 9b. A provider that is not configured.
		\MCR\Settings::update( array( 'ads.provider' => 'mcrn_fake' ) );
		\MCR\Settings::flush_cache();

		$GLOBALS['mcrn_provider_configured'] = false;

		$render = mcrn_render( 'checkin', 5 );

		assertFalse( (bool) $render['args']['ads_configured'], 'control: an unconfigured provider is not configured' );
		assertSame( 0, mcrn_count( $render['html'], 'data-mcr-ad-start' ), 'control: an unconfigured provider means no ad button' );

		// 9c. The provider class does not exist.
		\MCR\Settings::update( array( 'ads.provider' => 'mcrn_missing' ) );
		\MCR\Settings::flush_cache();

		$GLOBALS['mcrn_provider_configured'] = true;

		$render = mcrn_render( 'checkin', 5 );

		assertFalse( (bool) $render['args']['ads_configured'], 'control: a provider class that does not exist is not configured' );
		assertSame( 0, mcrn_count( $render['html'], 'data-mcr-ad-start' ), 'control: a missing provider class means no ad button' );

		// 9d. The provider throws while it is asked.
		\MCR\Settings::update( array( 'ads.provider' => 'mcrn_throwing' ) );
		\MCR\Settings::flush_cache();

		$render = mcrn_render( 'checkin', 5 );

		assertFalse( (bool) $render['args']['ads_configured'], 'control: a throwing provider is not configured' );
		assertSame( 0, mcrn_count( $render['html'], 'data-mcr-ad-start' ), 'control: a throwing provider means no ad button' );

		// 9e. The built-in S2S provider without a secret.
		\MCR\Settings::update( array( 'ads.provider' => 'generic_s2s' ) );
		\MCR\Settings::flush_cache();

		$render = mcrn_render( 'checkin', 5 );

		assertFalse( (bool) $render['args']['ads_configured'], 'control: an empty shared secret is not configured' );
		assertSame( 0, mcrn_count( $render['html'], 'data-mcr-ad-start' ), 'control: an empty secret means no ad button' );

		// 9f. The ads feature is switched off.
		\MCR\Settings::update(
			array(
				'ads.provider'         => 'mcrn_fake',
				'features.ads_enabled' => 'off',
			)
		);
		\MCR\Settings::flush_cache();

		$render = mcrn_render( 'checkin', 5 );

		assertFalse( (bool) $render['args']['ads_enabled'], 'control: the ads feature switch is off' );
		assertFalse( (bool) $render['args']['ads_configured'], 'control: a switched-off feature is not configured' );
		assertSame( 0, mcrn_count( $render['html'], 'data-mcr-ad-start' ), 'control: a switched-off feature means no ad button' );

		// 9g. A configured provider: the button appears.
		\MCR\Settings::update(
			array(
				'ads.provider'         => 'mcrn_fake',
				'features.ads_enabled' => 'on',
			)
		);
		\MCR\Settings::flush_cache();

		$render = mcrn_render( 'checkin', 5 );

		assertTrue( (bool) $render['args']['ads_configured'], 'control: a configured provider is configured' );
		assertSame( 1, mcrn_count( $render['html'], 'data-mcr-ad-start' ), 'control: a configured provider renders the ad button' );

		/* ======================================================================
		 * 10. The ad unit list
		 * ==================================================================== */

		mcr_group( 'Ad units: a hostile provider cannot break the attribute' );

		$GLOBALS['mcrn_provider_units'] = array(
			array(
				'id'    => 'evil" onmouseover="alert(1)',
				'label' => '<script>alert(1)</script>',
			),
		);

		mcrn_seed( array( 'claimed' => true, 'total' => 60 ) );

		$render = mcrn_render( 'checkin', 5 );

		assertFalse(
			false !== strpos( $render['html'], 'data-mcr-ad-unit="evil" onmouseover' ),
			'a hostile unit id cannot break out of the data-mcr-ad-unit attribute'
		);
		assertFalse(
			false !== strpos( $render['html'], '<script>alert(1)</script>' ),
			'a hostile unit label cannot inject a script tag'
		);
		assertTrue(
			false !== strpos( $render['html'], 'data-mcr-ad-unit="evilonmouseoveralert1"' ),
			'control: the unit id is sanitised before it reaches the attribute'
		);

		mcr_group( 'Ad units: the label reaches the page' );

		$GLOBALS['mcrn_provider_units'] = array(
			array(
				'id'    => 'unit_a',
				'label' => 'MCRN Unit Label',
			),
		);

		$render = mcrn_render( 'checkin', 5 );

		assertTrue(
			false !== strpos( $render['html'], 'MCRN Unit Label' ),
			'the ad unit label the provider supplied is shown to the reader'
		);

		mcr_group( 'Ad units: the number of buttons is bounded' );

		$many = array();

		for ( $i = 0; $i < 500; $i++ ) {
			$many[] = array(
				'id'    => 'unit_' . $i,
				'label' => 'Unit ' . $i,
			);
		}

		$GLOBALS['mcrn_provider_units'] = $many;

		$render = mcrn_render( 'checkin', 5 );

		assertTrue(
			mcrn_count( $render['html'], 'data-mcr-ad-start' ) <= 20,
			'a broken provider cannot make the panel render an unbounded number of buttons'
		);

		$GLOBALS['mcrn_provider_units'] = array(
			array(
				'id'    => 'unit_a',
				'label' => 'Unit A',
			),
		);

		/* ======================================================================
		 * 11. The logged-out visitor
		 * ==================================================================== */

		mcr_group( 'Logged-out visitor: no queries, no numbers' );

		mcrn_seed(
			array(
				'claimed'       => true,
				'total'         => 60,
				'ads_today'     => 4,
				'ads_total'     => 9,
				'ads_coins'     => 40,
				'ads_today_sum' => 20,
				'ledger_today'  => 60,
				'balance'       => 100,
				'lifetime'      => 100,
			)
		);

		global $wpdb;

		$render = mcrn_render( 'checkin', 0 );

		assertSame( 0, count( $wpdb->log ), 'control: the check-in page runs no query for a logged-out visitor' );
		assertSame( 0, (int) $render['args']['stats']['balance'], 'control: a logged-out visitor has a zero balance' );
		assertSame( 0, (int) $render['args']['stats']['lifetime_earned'], 'control: a logged-out visitor earned nothing' );
		assertSame( 0, (int) $render['args']['today_earned'], 'control: a logged-out visitor earned nothing today' );
		assertSame( 0, (int) $render['args']['ads']['today'], 'control: a logged-out visitor watched no ads' );
		assertSame( 0, mcrn_count( $render['html'], 'data-mcr-ad-start' ), 'control: a logged-out visitor gets no ad button' );

		/* ======================================================================
		 * 12. The query budget
		 * ==================================================================== */

		mcr_group( 'Query budget: the check-in page' );

		mcrn_seed(
			array(
				'claimed'       => true,
				'total'         => 60,
				'ads_today'     => 2,
				'ads_total'     => 7,
				'ads_coins'     => 35,
				'ads_today_sum' => 10,
				'ledger_today'  => 70,
				'balance'       => 70,
				'lifetime'      => 70,
			)
		);

		$render = mcrn_render( 'checkin', 5 );

		assertTrue(
			count( $wpdb->log ) <= 7,
			'the check-in page stays inside a seven query budget'
		);

		$unscoped = 0;

		foreach ( $wpdb->log as $sql ) {
			if ( false !== stripos( $sql, 'mcr_ad_sessions' ) && false === stripos( $sql, 'verified_at >=' ) ) {
				++$unscoped;
			}
		}

		assertSame(
			0,
			$unscoped,
			'the check-in page runs no lifetime-scoped ad query whose result it never shows'
		);

		mcr_group( 'Query budget: the other pages are untouched' );

		foreach ( array( 'dashboard', 'ads', 'buy-coins', 'transactions', 'balance' ) as $slug ) {
			mcrn_seed(
				array(
					'claimed'       => true,
					'total'         => 60,
					'ads_today'     => 2,
					'ads_total'     => 7,
					'ads_coins'     => 35,
					'ads_today_sum' => 10,
					'ledger_today'  => 70,
					'balance'       => 70,
					'lifetime'      => 70,
				)
			);

			mcrn_render( $slug, 5 );

			assertTrue(
				count( $wpdb->log ) <= 12,
				sprintf( 'control: the %s page stays inside its query budget', $slug )
			);
		}

		/* ======================================================================
		 * 13. The ad panel numbers
		 * ==================================================================== */

		mcr_group( 'Ad panel: the numbers it shows' );

		\MCR\Settings::update(
			array(
				'ads.provider'         => 'mcrn_fake',
				'features.ads_enabled' => 'on',
				'ads.coins_per_ad'     => 5,
				'ads.daily_limit'      => 10,
				'ads.cooldown_seconds' => 300,
			)
		);
		\MCR\Settings::flush_cache();

		$GLOBALS['mcrn_provider_configured'] = true;
		$GLOBALS['mcrn_provider_units']       = array(
			array(
				'id'    => 'unit_a',
				'label' => 'Unit A',
			),
		);

		mcrn_seed(
			array(
				'claimed'       => true,
				'total'         => 60,
				'ads_today'     => 3,
				'ads_total'     => 9,
				'ads_coins'     => 45,
				'ads_today_sum' => 15,
				'ledger_today'  => 75,
				'balance'       => 75,
				'lifetime'      => 75,
			)
		);

		$render = mcrn_render( 'checkin', 5 );

		assertSame( 300, (int) $render['args']['ads_cooldown'], 'control: the cooldown comes from the setting' );
		assertSame(
			'2026-10-22T00:00:00+00:00',
			(string) $render['args']['ads_reset'],
			'control: the cap reset deadline is the next midnight in the site timezone'
		);
		assertTrue(
			false !== strpos( $render['html'], 'data-mcr-ad-coins="5"' ),
			'control: the ad button carries the coins per ad'
		);
		assertTrue(
			false !== strpos( $render['html'], 'data-mcr-cooldown="300"' ),
			'control: the ad button carries the cooldown'
		);
		assertTrue(
			false !== strpos( $render['html'], '7 of 10 ads left today' ),
			'control: the panel shows the ads left today'
		);

		mcr_group( 'Ad panel: the daily cap reached' );

		mcrn_seed(
			array(
				'claimed'       => true,
				'total'         => 60,
				'ads_today'     => 10,
				'ads_total'     => 20,
				'ads_coins'     => 100,
				'ads_today_sum' => 50,
				'ledger_today'  => 110,
				'balance'       => 110,
				'lifetime'      => 110,
			)
		);

		$render = mcrn_render( 'checkin', 5 );

		assertSame( 0, (int) $render['args']['ads']['remaining'], 'control: no ad is left today' );
		assertSame( 0, mcrn_count( $render['html'], 'data-mcr-ad-start' ), 'control: the cap reached removes the ad button' );
		assertTrue(
			false !== strpos( $render['html'], 'Daily limit reached' ),
			'control: the panel explains that the cap is reached'
		);

		/* ======================================================================
		 * 14. The wallet and the frontend script
		 * ==================================================================== */

		mcr_group( 'The wallet balance carries data-mcr-balance' );

		$GLOBALS['mcrn_users'] = array(
			5 => (object) array(
				'display_name' => 'Ada Lovelace',
				'user_login'   => 'ada',
			),
		);

		mcrn_seed(
			array(
				'claimed'       => true,
				'total'         => 60,
				'ads_today'     => 2,
				'ads_total'     => 7,
				'ads_coins'     => 35,
				'ads_today_sum' => 10,
				'ledger_today'  => 70,
				'balance'       => 70,
				'lifetime'      => 70,
			)
		);

		$render = mcrn_render( 'checkin', 5 );

		assertTrue(
			false !== strpos( $render['html'], 'data-mcr-balance' ),
			'the wallet balance carries the data-mcr-balance hook the script repaints'
		);

		mcr_group( 'Control: the other pages keep their balance hook' );

		foreach ( array( 'dashboard', 'buy-coins', 'balance' ) as $slug ) {
			$render = mcrn_render( $slug, 5 );

			assertTrue(
				false !== strpos( $render['html'], 'data-mcr-balance' ),
				sprintf( 'control: the %s page keeps the data-mcr-balance hook', $slug )
			);
		}

		/* ======================================================================
		 * 15. The new keys do not leak into the other pages
		 * ==================================================================== */

		mcr_group( 'Control: the new arguments only exist on the check-in page' );

		foreach ( array( 'dashboard', 'ads', 'buy-coins', 'transactions', 'balance' ) as $slug ) {
			$render = mcrn_render( $slug, 5 );

			assertFalse(
				array_key_exists( 'profile', $render['args'] ),
				sprintf( 'control: the %s page does not gain the wallet profile', $slug )
			);
			assertFalse(
				array_key_exists( 'today_earned', $render['args'] ),
				sprintf( 'control: the %s page does not gain the earned-today figure', $slug )
			);
			assertFalse(
				array_key_exists( 'ads_reset', $render['args'] ),
				sprintf( 'control: the %s page does not gain the ad reset deadline', $slug )
			);
		}

		/* ======================================================================
		 * 16. The other pages still render
		 * ==================================================================== */

		mcr_group( 'Control: every page still renders' );

		foreach ( array( 'checkin', 'dashboard', 'ads', 'buy-coins', 'transactions', 'balance' ) as $slug ) {
			$render = mcrn_render( $slug, 5 );

			assertSame( '', $render['error'], sprintf( 'control: the %s page renders without a fatal error', $slug ) );
			assertTrue(
				'' !== trim( $render['html'] ),
				sprintf( 'control: the %s page renders', $slug )
			);
			assertFalse(
				false !== strpos( $render['html'], 'Template not found' ),
				sprintf( 'control: the %s page finds its template', $slug )
			);
		}

		mcr_group( 'Control: the balance widget is unchanged' );

		$widget = new \MCR\Widgets\BalanceWidget();

		ob_start();
		$widget->widget(
			array(
				'before_widget' => '',
				'after_widget'  => '',
				'before_title'  => '',
				'after_title'   => '',
			),
			array(
				'title'       => 'Your Coins',
				'show_streak' => true,
				'show_link'   => true,
			)
		);
		$widget_html = (string) ob_get_clean();

		assertTrue(
			false !== strpos( $widget_html, 'data-mcr-balance' ),
			'control: the balance widget still carries the data-mcr-balance hook'
		);
	}

	/* ==========================================================================
	 * 17. Summary
	 * ======================================================================== */

	$failures = $GLOBALS['mcr_tests']['failures'];

	echo "\n";

	if ( ! empty( $failures ) ) {
		echo "Failures:\n";

		foreach ( $failures as $index => $failure ) {
			echo '  ' . ( $index + 1 ) . '. [' . $failure['group'] . '] ' . $failure['message'] . "\n";

			if ( '' !== $failure['detail'] ) {
				echo '     ' . $failure['detail'] . "\n";
			}
		}
	}

	printf(
		"\nAssertions: %d | Passed: %d | Failed: %d\n",
		$GLOBALS['mcr_tests']['passed'] + $GLOBALS['mcr_tests']['failed'],
		$GLOBALS['mcr_tests']['passed'],
		$GLOBALS['mcr_tests']['failed']
	);

	exit( empty( $failures ) ? 0 : 1 );
}
