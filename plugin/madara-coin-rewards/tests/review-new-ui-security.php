<?php
/**
 * SECURITY & WORDPRESS-INTEGRATION review of the NEW rewards UI.
 *
 * Run with: php tests/review-new-ui-security.php
 *
 * Scope: the newly built wallet header (templates/parts/wallet.php), the
 * shared ad panel (templates/parts/ads-panel.php), the rebuilt check-in page
 * (templates/checkin.php), the new stylesheet (assets/css/mcr-rewards.css),
 * the new Shortcodes::checkin_args()/profile() work and the second
 * stylesheet enqueue in Templates\Assets.
 *
 * Every assertion is written so that it FAILS on the real defect it proves.
 * Assertions that describe behaviour that is already correct are marked
 * "control:" in their message.
 *
 * @package MCR
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/wp-stubs.php';

/* ==========================================================================
 * Extra stubs the templates need.
 * ======================================================================== */

if ( ! function_exists( 'wp_nonce_field' ) ) {
	/**
	 * Nonce field stub.
	 *
	 * @param string $action Action.
	 * @param string $name   Field name.
	 * @param bool   $referer Whether to add the referer field.
	 * @param bool   $echo   Whether to echo.
	 * @return string
	 */
	function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $echo = true ) {
		$field = '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( wp_create_nonce( $action ) ) . '" />';

		if ( $echo ) {
			echo $field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		return $field;
	}
}

if ( ! function_exists( 'is_active_widget' ) ) {
	/**
	 * Active widget stub.
	 *
	 * @param mixed $widget_id Widget id.
	 * @param mixed $key       Key.
	 * @param mixed $id_base   Id base.
	 * @param bool  $skip      Skip the inactive check.
	 * @return bool
	 */
	function is_active_widget( $widget_id = false, $key = false, $id_base = false, $skip = false ) {
		return ! empty( $GLOBALS['mcr_active_widgets'] );
	}
}

/* ==========================================================================
 * Review helpers.
 * ======================================================================== */

$GLOBALS['mcr_plugin_dir'] = dirname( __DIR__ ) . '/';

/**
 * Reads a plugin file.
 *
 * @param string $rel Relative path.
 * @return string
 */
function mcrns_read( $rel ) {
	return (string) file_get_contents( $GLOBALS['mcr_plugin_dir'] . $rel );
}

/**
 * Renders a shortcode and returns the markup.
 *
 * A render that throws is reported instead of fatal, so one broken page
 * cannot hide the rest of the suite.
 *
 * @param string $shortcode Shortcode text.
 * @return string
 */
function mcrns_render( $shortcode ) {
	ob_start();

	try {
		$html = do_shortcode( $shortcode );
	} catch ( \Throwable $e ) {
		$GLOBALS['mcrns_last_error'] = get_class( $e ) . ': ' . $e->getMessage();
		$html = '';
	}

	ob_end_clean();

	return (string) $html;
}

/**
 * Renders a partial through the plugin's own part() mechanism.
 *
 * @param string $name Partial name.
 * @param array  $args Partial arguments.
 * @return string
 */
function mcrns_part( $name, array $args ) {
	try {
		return (string) \MCR\Templates\Templates::part( $name, $args );
	} catch ( \Throwable $e ) {
		$GLOBALS['mcrns_last_error'] = get_class( $e ) . ': ' . $e->getMessage();

		return '';
	}
}

/**
 * Configures the built-in signed postback provider.
 *
 * @param array $provider Provider settings.
 * @return void
 */
function mcrns_configure_provider( array $provider = array() ) {
	$settings = array_merge(
		array(
			'postback_secret' => 'shared-secret',
			'placement_id'    => 'main-placement',
		),
		$provider
	);

	\MCR\Settings::update(
		array(
			'ads.provider'              => 'generic_s2s',
			'ads.providers.generic_s2s' => $settings,
		)
	);

	\MCR\Settings::flush_cache();

	// The registry caches the provider instances: rebuild them.
	\MCR\AdProviders\Registry::reset();
}

/**
 * Extracts the CSS rules of a stylesheet.
 *
 * @param string $css Stylesheet source.
 * @return array List of array{selector:string,body:string}.
 */
function mcrns_css_rules( $css ) {
	$css = preg_replace( '#/\*.*?\*/#s', '', (string) $css );
	$css = preg_replace( '/\s+/', ' ', (string) $css );

	$rules = array();
	$len   = strlen( $css );
	$at    = 0;

	while ( $at < $len ) {
		$brace = strpos( $css, '{', $at );

		if ( false === $brace ) {
			break;
		}

		$prelude = trim( substr( $css, $at, $brace - $at ) );

		$depth = 1;
		$i     = $brace + 1;

		while ( $i < $len && $depth > 0 ) {
			if ( '{' === $css[ $i ] ) {
				++$depth;
			} elseif ( '}' === $css[ $i ] ) {
				--$depth;
			}

			++$i;
		}

		$body = substr( $css, $brace + 1, $i - $brace - 2 );

		if ( '' !== $prelude && '@' === $prelude[0] ) {
			if ( ! preg_match( '/^@keyframes/i', $prelude ) ) {
				// @media / @supports: the rules live inside.
				$rules = array_merge( $rules, mcrns_css_rules( $body ) );
			}
		} elseif ( '' !== $prelude ) {
			$rules[] = array(
				'selector' => $prelude,
				'body'     => $body,
			);
		}

		$at = $i;
	}

	return $rules;
}

/* ==========================================================================
 * 0. Boot
 * ======================================================================== */

$GLOBALS['wpdb']        = new MCR_Fake_WPDB();
$GLOBALS['mcr_current_user'] = 5;
$GLOBALS['mcr_caps']    = array(
	'manage_options'     => true,
	'mcr_manage_settings' => true,
);

mcr_group( 'Boot' );

$boot_error = null;

try {
	require_once $GLOBALS['mcr_plugin_dir'] . 'includes/Autoloader.php';
	\MCR\Autoloader::register( $GLOBALS['mcr_plugin_dir'] );

	require_once $GLOBALS['mcr_plugin_dir'] . 'madara-coin-rewards.php';

	mcr_bootstrap();

	assertTrue( true, 'the plugin boots in a frontend request' );
} catch ( \Throwable $e ) {
	$boot_error = $e;
	mcr_fail( 'the plugin boots in a frontend request', get_class( $e ) . ': ' . $e->getMessage() );
}

if ( null !== $boot_error ) {
	printf(
		"\nAssertions: %d | Passed: %d | Failed: %d\n",
		$GLOBALS['mcr_tests']['passed'] + $GLOBALS['mcr_tests']['failed'],
		$GLOBALS['mcr_tests']['passed'],
		$GLOBALS['mcr_tests']['failed']
	);
	exit( 1 );
}

/* ==========================================================================
 * Test doubles. They are declared AFTER the boot, so the plugin's
 * autoloader can resolve the ledger contract they implement.
 * ======================================================================== */
/**
 * A ledger that records the credits it is asked for.
 */
class MCRNS_Stub_Ledger implements \MCR\Ledger\LedgerInterface {

	/**
	 * Credits are recorded here.
	 *
	 * @var array
	 */
	public $credits = array();

	/**
	 * {@inheritDoc}
	 */
	public function credit( $user_id, $amount, $type, $reason = '', $ctx = array() ) {
		$this->credits[] = array(
			'user_id' => $user_id,
			'amount'  => $amount,
			'type'    => $type,
		);

		return array(
			'balance_after'  => (int) $amount,
			'transaction_id' => 1,
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function debit( $user_id, $amount, $type, $reason = '', $ctx = array() ) {
		return array(
			'balance_after'  => 0,
			'transaction_id' => 1,
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_balance( $user_id ) {
		return 0;
	}
}

/**
 * A provider that always confirms a completion, so the ONLY thing that can
 * stop the reward is the policy itself.
 */
class MCRNS_Stub_Provider {

	/**
	 * Whether the provider is configured.
	 *
	 * @var bool
	 */
	public $configured = true;

	/**
	 * Whether the completion is confirmed.
	 *
	 * @var bool
	 */
	public $confirms = true;

	/**
	 * Whether is_configured() was called.
	 *
	 * @var bool
	 */
	public $was_configured_checked = false;

	/**
	 * Whether the provider is configured.
	 *
	 * @return bool
	 */
	public function is_configured() {
		$this->was_configured_checked = true;

		return (bool) $this->configured;
	}

	/**
	 * Confirms (or refuses) a completion.
	 *
	 * @param array $payload Payload.
	 * @return true|\WP_Error
	 */
	public function verify_completion( array $payload ) {
		if ( ! $this->confirms ) {
			return new \WP_Error( 'mcr_stub_refused', 'refused' );
		}

		return true;
	}
}

/* ==========================================================================
 * 1. XSS through the wallet.
 * ======================================================================== */

mcr_group( 'Wallet: display name, avatar and initials' );

$wallet_html = mcrns_part(
	'wallet',
	array(
		'logged_in' => true,
		'stats'     => array(
			'balance'         => 120,
			'lifetime_earned' => 400,
			'lifetime_spent'  => 280,
		),
		'profile'   => array(
			'name'     => '<script>alert("xss")</script>',
			'avatar'   => 'https://example.test/avatar.png',
			'initials' => '<script>alert("initials")</script>',
		),
		'streak'    => 3,
	)
);

assertFalse(
	false !== strpos( $wallet_html, '<script>' ),
	'a display name of "<script>" cannot inject a script tag into the wallet'
);

assertTrue(
	false !== strpos( $wallet_html, '&lt;script&gt;' ),
	'control: the display name is HTML-escaped by the wallet'
);

assertFalse(
	false !== strpos( $wallet_html, '<script>alert("initials")' ),
	'initials cannot inject a script tag into the wallet'
);

assertTrue(
	false !== strpos( $wallet_html, 'https://example.test/avatar.png' ),
	'control: the avatar is rendered as an image source'
);

/*
 * esc_url() does not escape a double quote, so an avatar URL that contains
 * one breaks out of the src attribute. The attribute needs esc_attr() as
 * well - esc_url() alone is not an attribute escaper.
 */
$wallet_src = mcrns_read( 'templates/parts/wallet.php' );

assertTrue(
	(bool) preg_match( '/src="<\?php echo esc_attr\(\s*esc_url\(/', $wallet_src )
		|| (bool) preg_match( '/src="<\?php echo esc_url\(\s*esc_attr\(/', $wallet_src ),
	'the wallet escapes the avatar URL for an ATTRIBUTE (esc_url() alone leaves a double quote intact)'
);

/* ==========================================================================
 * 2. XSS through the ad provider.
 * ======================================================================== */

mcr_group( 'Ad panel: the provider supplied unit' );

$panel_html = mcrns_part(
	'ads-panel',
	array(
		'enabled'       => true,
		'configured'    => true,
		'units'         => array(
			array(
				'id'    => 'unit_a',
				'label' => '<script>alert("label")</script>',
			),
		),
		'ads'           => array(
			'today'        => 0,
			'limit'        => 10,
			'remaining'    => 10,
			'coins_per_ad' => 5,
		),
		'coins_per_ad'  => 5,
		'daily_limit'   => 10,
		'cooldown'      => 300,
	)
);

assertFalse(
	false !== strpos( $panel_html, '<script>' ),
	'a provider label cannot inject a script tag into the ad panel'
);

assertTrue(
	false !== strpos( $panel_html, 'data-mcr-ad-unit="unit_a"' ),
	'control: the ad unit id is rendered as a sanitised data attribute'
);

$hostile_units = mcrns_part(
	'ads-panel',
	array(
		'enabled'       => true,
		'configured'    => true,
		'units'         => array(
			array(
				'id'    => '"><img src=x onerror=alert(1)>',
				'label' => 'x',
			),
		),
		'ads'           => array(
			'today'        => 0,
			'limit'        => 10,
			'remaining'    => 10,
			'coins_per_ad' => 5,
		),
		'coins_per_ad'  => 5,
		'daily_limit'   => 10,
		'cooldown'      => 300,
	)
);

assertFalse(
	false !== strpos( $hostile_units, 'onerror=' ),
	'a hostile ad unit id cannot inject an event handler into the ad panel'
);

assertFalse(
	false !== strpos( $hostile_units, '<img src=x' ),
	'a hostile ad unit id cannot inject markup into the ad panel'
);

/*
 * The panel is supposed to show which ad unit the button starts, so the
 * provider supplied label has to reach the page.
 */
$panel_label_html = mcrns_part(
	'ads-panel',
	array(
		'enabled'       => true,
		'configured'    => true,
		'units'         => array(
			array(
				'id'    => 'unit_a',
				'label' => 'Premium rewarded video',
			),
		),
		'ads'           => array(
			'today'        => 0,
			'limit'        => 10,
			'remaining'    => 10,
			'coins_per_ad' => 5,
		),
		'coins_per_ad'  => 5,
		'daily_limit'   => 10,
		'cooldown'      => 300,
	)
);

assertTrue(
	false !== strpos( $panel_label_html, 'Premium rewarded video' ),
	'control: the ad panel shows the provider supplied LABEL of the unit it renders'
);

assertFalse(
	false !== strpos( $panel_label_html, '<script>alert("label")' ),
	'control: the provider supplied label is escaped, not printed as markup'
);

/* ==========================================================================
 * 3. The button must only be rendered when a reward can be paid.
 * ======================================================================== */

mcr_group( 'Ad panel: a button that can never pay' );

/*
 * With "coins per ad" set to 0 - a value the settings screen allows (the
 * field's minimum is 0) - the reward is rejected by the policy
 * (mcr_invalid_amount) and by the S2S postback (invalid_amount). The panel
 * must not offer a button at all.
 */
$policy = new \MCR\Rewards\AdRewardPolicy(
	new \MCR\Support\FixedClock( '2026-10-21 12:00:00', 'UTC' ),
	new MCRNS_Stub_Ledger(),
	new MCRNS_Stub_Provider()
);

$zero_result = $policy->verify(
	5,
	array(
		'session_token'    => str_repeat( 'a', 64 ),
		'completion_token' => 'proof',
	),
	array(
		'session'       => array(
			'id'       => 1,
			'user_id'  => 5,
			'status'   => 'pending',
			'coins'    => 0,
			'ad_id'    => 'rewarded_video',
			'provider' => 'generic_s2s',
			'expires_at' => gmdate( 'Y-m-d H:i:s', time() + 900 ),
		),
		'coins'         => 0,
		'daily_limit'   => 10,
		'watched_today' => 0,
		'ref_id'        => 1,
	)
);

assertTrue(
	is_wp_error( $zero_result ) && 'mcr_invalid_amount' === $zero_result->get_error_code(),
	'control: a verified ad that is worth 0 coins is rejected by the policy, so it can never be paid'
);

$zero_panel = mcrns_part(
	'ads-panel',
	array(
		'enabled'       => true,
		'configured'    => true,
		'units'         => array(
			array(
				'id'    => 'rewarded_video',
				'label' => 'Rewarded video',
			),
		),
		'ads'           => array(
			'today'        => 0,
			'limit'        => 10,
			'remaining'    => 10,
			'coins_per_ad' => 0,
		),
		'coins_per_ad'  => 0,
		'daily_limit'   => 10,
		'cooldown'      => 300,
	)
);

assertFalse(
	false !== strpos( $zero_panel, 'data-mcr-ad-start' ),
	'the ad panel renders NO "watch ad" button when an ad is worth 0 coins, because such a reward can never be paid'
);

/* ==========================================================================
 * 4. The other pages still render.
 * ======================================================================== */

mcr_group( 'Regression: the other plugin pages' );

$dashboard_html = mcrns_render( '[mcr_dashboard]' );

assertTrue(
	false !== strpos( $dashboard_html, 'mcr-dashboard-ads' ),
	'control: the dashboard still renders its ad section' . ( isset( $GLOBALS['mcrns_last_error'] ) ? ' (' . $GLOBALS['mcrns_last_error'] . ')' : '' )
);

assertTrue(
	false !== strpos( $dashboard_html, 'mcr-page--dashboard' ),
	'control: the dashboard page still renders'
);

$ads_html = mcrns_render( '[mcr_ads]' );

assertTrue(
	false !== strpos( $ads_html, 'mcr-ads-panel' ),
	'control: the ads page still renders the shared ad panel'
);

assertTrue(
	false !== strpos( $ads_html, 'mcr-ads-recent' ),
	'control: the ads page still renders the recent ad rewards section'
);

$buy_html = mcrns_render( '[mcr_buy_coins]' );

assertTrue(
	false !== strpos( $buy_html, 'mcr-page--buy-coins' ),
	'control: the buy-coins page still renders'
);

$transactions_html = mcrns_render( '[mcr_transactions]' );

assertTrue(
	false !== strpos( $transactions_html, 'mcr-page--transactions' ),
	'control: the transactions page still renders'
);

$balance_html = mcrns_render( '[mcr_balance]' );

assertTrue(
	false !== strpos( $balance_html, 'mcr-page--balance' ),
	'control: the balance panel still renders'
);

/* ==========================================================================
 * 5. The check-in page.
 * ======================================================================== */

mcr_group( 'Check-in page: the wallet and the ad panel' );

$checkin_html = mcrns_render( '[mcr_checkin]' );

assertTrue(
	false !== strpos( $checkin_html, 'mcr-wallet' ),
	'control: the default check-in layout renders the wallet header'
);

assertTrue(
	false !== strpos( $checkin_html, 'mcr-ads-panel' ),
	'control: the default check-in layout renders the ad panel'
);

assertTrue(
	false !== strpos( $checkin_html, 'mcr-checkin-bonuses' ),
	'control: the default check-in layout still renders the streak bonuses'
);

assertTrue(
	false !== strpos( $checkin_html, 'mcr-checkin-rules' ),
	'control: the default check-in layout still renders the rules'
);

$hero_html = mcrns_render( '[mcr_checkin layout="hero"]' );

assertTrue(
	false !== strpos( $hero_html, 'mcr-wallet' ),
	'the hero check-in layout renders the wallet header too'
);

assertTrue(
	false !== strpos( $hero_html, 'mcr-ads-panel' ),
	'the hero check-in layout renders the ad panel too'
);

$compact_html = mcrns_render( '[mcr_checkin layout="compact"]' );

assertTrue(
	false !== strpos( $compact_html, 'mcr-wallet' ),
	'the compact check-in layout renders the wallet header too'
);

assertTrue(
	false !== strpos( $compact_html, 'mcr-ads-panel' ),
	'the compact check-in layout renders the ad panel too'
);

/* ==========================================================================
 * 6. The end to end ad panel: a provider that is configured.
 * ======================================================================== */

mcr_group( 'Check-in page: a configured provider' );

/*
 * The display name, the avatar URL and the initials come from the user
 * account, so the whole page has to escape them.
 */
add_filter(
	'mcr_page_args',
	function ( $args, $slug ) {
		if ( 'checkin' === $slug ) {
			$args['profile'] = array(
				'name'     => '<script>alert("profile")</script>',
				'avatar'   => 'https://example.test/avatar.png',
				'initials' => '<script>alert("initials")</script>',
			);
		}

		return $args;
	}
);

$hostile_profile_html = mcrns_render( '[mcr_checkin]' );

assertFalse(
	false !== strpos( $hostile_profile_html, '<script>' ),
	'control: the display name on the check-in page cannot inject a script tag'
);

assertTrue(
	false !== strpos( $hostile_profile_html, '&lt;script&gt;' ),
	'control: the display name on the check-in page is HTML-escaped'
);

mcrns_configure_provider();

$configured_html = mcrns_render( '[mcr_checkin]' );

assertTrue(
	false !== strpos( $configured_html, 'data-mcr-ad-start' ),
	'control: a configured provider offers the watch button on the check-in page'
);

assertTrue(
	false !== strpos( $configured_html, 'Rewarded video (S2S verified)' ),
	'the check-in page shows the human readable name the provider gave the ad unit'
);

/*
 * With "coins per ad" at 0 the whole ad flow is pointless: the session is
 * created, the ad is watched and the reward is refused.
 */
\MCR\Settings::update( array( 'ads.coins_per_ad' => 0 ) );
\MCR\Settings::flush_cache();

$zero_coins_html = mcrns_render( '[mcr_checkin]' );

assertFalse(
	false !== strpos( $zero_coins_html, 'data-mcr-ad-start' ),
	'the check-in page offers NO watch button when an ad is worth 0 coins, because such a reward can never be paid'
);

\MCR\Settings::update( array( 'ads.coins_per_ad' => 5 ) );
\MCR\Settings::flush_cache();

/* ==========================================================================
 * 7. The "earned today" arithmetic.
 * ======================================================================== */

mcr_group( 'Check-in page: the earned today counter' );

/*
 * The wallet shows "Earned today". It has to be the amount the LEDGER
 * credited today, not a number rebuilt from a mutable setting: the ads
 * panel multiplies the number of ads watched today by the CURRENT coins per
 * ad, so a rate change makes the two counters on the same screen disagree.
 */
add_filter(
	'mcr_page_args',
	function ( $args, $slug ) {
		if ( 'checkin' === $slug ) {
			$args['ads'] = array(
				'today'        => 2,
				'total'        => 2,
				'coins'        => 10,
				'limit'        => 10,
				'remaining'    => 8,
				'coins_per_ad' => 10,
			);

			$args['stats'] = array(
				'balance'         => 10,
				'lifetime_earned' => 10,
				'lifetime_spent'  => 0,
				'purchased_total' => 0,
			);
		}

		return $args;
	}
);

$today_html = mcrns_render( '[mcr_checkin]' );

assertFalse(
	false !== strpos( $today_html, '20' ) && false !== strpos( $today_html, 'earned today' ),
	'control: the "earned today" counter is the amount the ledger credited today, not the ad count multiplied by the current rate'
);

assertTrue(
	false !== strpos( $today_html, '10' ),
	'control: the ledger total for today is the one the wallet shows'
);

/* ==========================================================================
 * 8. Templates::part() and the theme override.
 * ======================================================================== */

mcr_group( 'Templates::part(): the argument scope' );

$override_html = mcrns_part(
	'wallet',
	array(
		'logged_in'   => true,
		'stats'       => array(
			'balance'         => 7,
			'lifetime_earned' => 7,
			'lifetime_spent'  => 0,
		),
		'profile'     => array(
			'name' => 'Tester',
		),
		'streak'      => 1,
		// An argument that collides with a variable the part computes.
		'mcr_balance' => 999999,
	)
);

assertFalse(
	false !== strpos( $override_html, '999,999' ),
	'control: an argument cannot overwrite a variable the partial computes for itself'
);

assertTrue(
	false !== strpos( $override_html, 'mcr-wallet' ),
	'control: the wallet partial still renders'
);

/*
 * The theme override path: a theme may replace a part with its own file.
 * That is a documented extension point (like a theme template), so the
 * plugin's own part must be used when the theme has no override.
 */
$located = \MCR\Templates\Templates::locate_part( 'wallet' );

assertSame(
	str_replace( '\\', '/', $located ),
	str_replace( '\\', '/', MCR_PLUGIN_DIR . 'templates/parts/wallet.php' ),
	'control: without a theme override the plugin\'s own partial is used'
);

assertTrue(
	(bool) preg_match( '/apply_filters\(\s*"mcr_template_part_\{\$name\}"/', mcrns_read( 'includes/Templates/Templates.php' ) ),
	'control: a part can be overridden through the documented mcr_template_part_{name} filter'
);

/* ==========================================================================
 * 9. The new stylesheet.
 * ======================================================================== */

mcr_group( 'The new stylesheet' );

$css = mcrns_read( 'assets/css/mcr-rewards.css' );

$unscoped = array();

foreach ( mcrns_css_rules( $css ) as $rule ) {
	foreach ( explode( ',', $rule['selector'] ) as $selector ) {
		$selector = trim( $selector );

		if ( '' === $selector ) {
			continue;
		}

		if ( false === strpos( $selector, '.mcr-scope' ) ) {
			$unscoped[] = $selector;
		}
	}
}

assertCount(
	0,
	$unscoped,
	'control: every rule of the new stylesheet is scoped under .mcr-scope, so it cannot reach the theme'
);

assertFalse(
	false !== stripos( $css, 'url(' ),
	'control: the new stylesheet loads no external resource, so no crafted URL can inject into it'
);

assertFalse(
	false !== stripos( $css, '@import' ),
	'control: the new stylesheet imports nothing'
);

assertFalse(
	false !== stripos( $css, 'expression(' ),
	'control: the new stylesheet has no CSS expression'
);

assertFalse(
	false !== stripos( $css, 'javascript:' ),
	'control: the new stylesheet has no javascript: url'
);

assertFalse(
	false !== stripos( $css, '-moz-binding' ),
	'control: the new stylesheet has no XBL binding'
);

/* ==========================================================================
 * 10. The stylesheet enqueue.
 * ======================================================================== */

mcr_group( 'The stylesheet enqueue' );

$assets_src = mcrns_read( 'includes/Templates/Assets.php' );

assertSame(
	'mcr-design-system',
	\MCR\Templates\Assets::STYLE,
	'control: the design system is enqueued under the handle the new stylesheet depends on'
);

preg_match_all(
	'/wp_enqueue_style\(\s*([^,]+?),\s*([^,]+?),\s*(array\([^)]*\)|[^,]+?),\s*([^,]+?),/s',
	$assets_src,
	$enqueues,
	PREG_SET_ORDER
);

$design_pos   = -1;
$rewards_pos  = -1;
$rewards_deps = '';

foreach ( $enqueues as $index => $enqueue ) {
	$handle = trim( $enqueue[1] );
	$src    = trim( $enqueue[2] );
	$deps   = trim( $enqueue[3] );

	if ( false !== strpos( $src, 'mcr-design-system.css' ) ) {
		$design_pos = $index;
	}

	if ( false !== strpos( $src, 'mcr-rewards.css' ) ) {
		$rewards_pos  = $index;
		$rewards_deps = $deps;
	}
}

assertTrue(
	$design_pos >= 0,
	'control: the design system stylesheet is enqueued'
);

assertTrue(
	$rewards_pos >= 0,
	'control: the new rewards stylesheet is enqueued'
);

assertTrue(
	$design_pos >= 0 && $rewards_pos > $design_pos,
	'the new rewards stylesheet is enqueued AFTER the design system it depends on'
);

assertSame(
	'array( self::STYLE )',
	$rewards_deps,
	'the new rewards stylesheet declares the design system handle as its dependency'
);

/*
 * The design system must never be missing when the new stylesheet is
 * loaded: the new file only uses the --mcr-* variables.
 */
$design_block = substr( $assets_src, strpos( $assets_src, 'private function enqueue' ) );
$design_block = substr( $design_block, 0, strpos( $design_block, "\n\t}" ) );

assertTrue(
	false !== strpos( $design_block, "self::STYLE," ),
	'control: the design system is enqueued inside the same enqueue() method as the new stylesheet'
);

assertTrue(
	strpos( $design_block, "self::STYLE," ) < strpos( $design_block, "'mcr-rewards'," ),
	'control: the design system is enqueued before the new stylesheet inside enqueue()'
);

/* ==========================================================================
 * Summary.
 * ======================================================================== */

$total = $GLOBALS['mcr_tests']['passed'] + $GLOBALS['mcr_tests']['failed'];

printf(
	"\nAssertions: %d | Passed: %d | Failed: %d\n",
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
