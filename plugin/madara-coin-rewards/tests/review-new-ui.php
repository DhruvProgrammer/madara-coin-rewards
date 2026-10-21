<?php
/**
 * VISUAL DESIGN & UX review of the newly built rewards interface.
 *
 * Run with: php tests/review-new-ui.php
 *
 * Scope: the wallet header, the check-in page, the ad panel, the reward
 * calendar, the seven day streak strip and assets/css/mcr-rewards.css.
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
			echo $field; // phpcs:ignore
		}

		return $field;
	}
}

$GLOBALS['mcr_plugin_dir'] = dirname( __DIR__ ) . '/';

/**
 * The WordPress root of this checkout (the theme lives at its root).
 */
define( 'MCRNU_WP_ROOT', dirname( $GLOBALS['mcr_plugin_dir'], 3 ) );

/* ==========================================================================
 * Helpers
 * ======================================================================== */

/**
 * Reads a plugin file.
 *
 * @param string $rel Relative path.
 * @return string
 */
function mcrnu_read( $rel ) {
	return (string) file_get_contents( $GLOBALS['mcr_plugin_dir'] . $rel );
}

/**
 * Normalises markup the way production WordPress does.
 *
 * WordPress' esc_html() does not double-encode an existing NAMED entity
 * (_wp_specialchars() runs wp_kses_normalize_entities() first). The test
 * stub's htmlspecialchars() does. Normalising keeps every text assertion
 * about PRODUCTION output rather than about the stub.
 *
 * Numeric entities are NOT in WordPress' named whitelist, so they are left
 * exactly as the stub produced them - that difference is a real defect and
 * the assertions below rely on it.
 *
 * @param string $html Markup.
 * @return string
 */
function mcrnu_wp_normalise( $html ) {
	return str_replace( '&amp;amp;', '&amp;', (string) $html );
}

/**
 * Parses a stylesheet into flat rules.
 *
 * @param string $css Stylesheet source.
 * @return array List of array{selector:string, body:string}.
 */
function mcrnu_css_rules( $css ) {
	$css = preg_replace( '#/\*.*?\*/#s', '', (string) $css );

	// @charset / @import are statements, not rules: they would otherwise be
	// swallowed into the selector of the first real rule.
	$css = preg_replace( '/@(?:charset|import|namespace)[^;]*;/i', '', $css );

	$rules = array();

	if ( ! preg_match_all( '/([^{}]+)\{([^{}]*)\}/', $css, $matches, PREG_SET_ORDER ) ) {
		return $rules;
	}

	foreach ( $matches as $match ) {
		$selector = trim( $match[1] );
		$body     = trim( $match[2] );

		if ( '' === $selector || 0 === strpos( $selector, '@' ) ) {
			continue;
		}

		foreach ( explode( ',', $selector ) as $one ) {
			$one = trim( $one );

			if ( '' === $one ) {
				continue;
			}

			$rules[] = array(
				'selector' => $one,
				'body'     => $body,
			);
		}
	}

	return $rules;
}

/**
 * Returns the body of the rule whose selector matches exactly.
 *
 * @param string $css      Stylesheet source.
 * @param string $selector Exact selector.
 * @return string
 */
function mcrnu_rule_body( $css, $selector ) {
	foreach ( mcrnu_css_rules( $css ) as $rule ) {
		if ( $rule['selector'] === $selector ) {
			return $rule['body'];
		}
	}

	return '';
}

/**
 * Reads a custom property out of a rule body.
 *
 * @param string $body     Rule body.
 * @param string $property Property name.
 * @return string
 */
function mcrnu_body_var( $body, $property ) {
	if ( preg_match( '/(?:^|;)\s*' . preg_quote( $property, '/' ) . '\s*:\s*([^;]+)/', $body, $match ) ) {
		return trim( $match[1] );
	}

	return '';
}

/**
 * Converts a hex colour to a 0-255 RGB triple.
 *
 * @param string $hex Hex colour.
 * @return array
 */
function mcrnu_rgb( $hex ) {
	$hex = ltrim( trim( (string) $hex ), '#' );

	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}

	return array(
		hexdec( substr( $hex, 0, 2 ) ),
		hexdec( substr( $hex, 2, 2 ) ),
		hexdec( substr( $hex, 4, 2 ) ),
	);
}

/**
 * The WCAG 2.x relative luminance of a colour.
 *
 * @param string $hex Hex colour.
 * @return float
 */
function mcrnu_luminance( $hex ) {
	list( $r, $g, $b ) = mcrnu_rgb( $hex );

	$channels = array();

	foreach ( array( $r, $g, $b ) as $value ) {
		$value = $value / 255;

		$channels[] = ( $value <= 0.03928 ) ? ( $value / 12.92 ) : pow( ( $value + 0.055 ) / 1.055, 2.4 );
	}

	return ( 0.2126 * $channels[0] ) + ( 0.7152 * $channels[1] ) + ( 0.0722 * $channels[2] );
}

/**
 * The WCAG 2.x contrast ratio between two colours.
 *
 * @param string $a First colour.
 * @param string $b Second colour.
 * @return float
 */
function mcrnu_contrast( $a, $b ) {
	$la = mcrnu_luminance( $a );
	$lb = mcrnu_luminance( $b );

	if ( $la < $lb ) {
		$tmp = $la;
		$la  = $lb;
		$lb  = $tmp;
	}

	return ( $la + 0.05 ) / ( $lb + 0.05 );
}

/**
 * The CSS specificity of a selector as an (ids, classes, types) triple.
 *
 * @param string $selector Selector.
 * @return array
 */
function mcrnu_specificity( $selector ) {
	$selector = trim( (string) $selector );

	$ids = preg_match_all( '/#[A-Za-z0-9_-]+/', $selector );
	$rest = preg_replace( '/#[A-Za-z0-9_-]+/', ' ', $selector );

	$types = preg_match_all( '/::[A-Za-z-]+/', $rest );
	$rest = preg_replace( '/::[A-Za-z-]+/', ' ', $rest );

	$classes  = preg_match_all( '/(?<!:):[A-Za-z-]+/', $rest );
	$rest     = preg_replace( '/(?<!:):[A-Za-z-]+/', ' ', $rest );
	$classes += preg_match_all( '/\[[^\]]*\]/', $rest );
	$rest     = preg_replace( '/\[[^\]]*\]/', ' ', $rest );
	$classes += preg_match_all( '/\.[A-Za-z0-9_-]+/', $rest );
	$rest     = preg_replace( '/\.[A-Za-z0-9_-]+/', ' ', $rest );

	$types += preg_match_all( '/(?:^|[\s>+~])([A-Za-z][A-Za-z0-9-]*)/', $rest );

	return array( (int) $ids, (int) $classes, (int) $types );
}

/**
 * Compares two specificity triples.
 *
 * @param array $a First triple.
 * @param array $b Second triple.
 * @return int -1 when a loses, 0 on a tie, 1 when a wins.
 */
function mcrnu_compare_specificity( $a, $b ) {
	for ( $i = 0; $i < 3; $i++ ) {
		if ( $a[ $i ] === $b[ $i ] ) {
			continue;
		}

		return ( $a[ $i ] > $b[ $i ] ) ? 1 : -1;
	}

	return 0;
}

/**
 * Renders the check-in page with overridden controller arguments.
 *
 * @param array $override Arguments to merge over the controller's own.
 * @return string
 */
function mcrnu_render_checkin( array $override ) {
	$filter = static function ( $args ) use ( $override ) {
		return array_merge( $args, $override );
	};

	add_filter( 'mcr_page_args', $filter );

	ob_start();
	$html = do_shortcode( '[mcr_checkin]' );
	ob_end_clean();

	$GLOBALS['mcr_filters']['mcr_page_args'] = array();

	return mcrnu_wp_normalise( $html );
}

/**
 * Builds a seven day calendar for the tests.
 *
 * @param int  $claimed_days  How many past days are claimed.
 * @param bool $today_claimed Whether today is claimed.
 * @param string $today        Today in Y-m-d.
 * @return array
 */
function mcrnu_calendar( $claimed_days, $today_claimed, $today = '2026-10-21' ) {
	$rows = array();
	$base = strtotime( $today . ' 00:00:00' );

	for ( $i = 6; $i >= 0; $i-- ) {
		$date    = gmdate( 'Y-m-d', $base - ( $i * DAY_IN_SECONDS ) );
		$claimed = ( $i >= 7 - $claimed_days ) ? ( 0 === $i ? $today_claimed : true ) : false;

		$rows[] = array(
			'date'    => $date,
			'claimed' => $claimed,
			'streak'  => max( 0, 7 - $i ),
			'amount'  => $claimed ? 10 : 0,
		);
	}

	return $rows;
}

/* ==========================================================================
 * Boot
 * ======================================================================== */

echo "MCR new UI - visual design & UX review\n";
echo "=======================================\n\n";

$GLOBALS['wpdb']              = new MCR_Fake_WPDB();
$GLOBALS['mcr_current_user'] = 5;
$GLOBALS['mcr_caps']         = array(
	'manage_options'     => true,
	'mcr_manage_settings' => true,
);

mcr_group( 'Boot' );

$boot_error = null;

try {
	require_once $GLOBALS['mcr_plugin_dir'] . 'includes/Autoloader.php';
	MCR\Autoloader::register( $GLOBALS['mcr_plugin_dir'] );

	require_once $GLOBALS['mcr_plugin_dir'] . 'madara-coin-rewards.php';

	mcr_bootstrap();

	assertTrue( true, 'the plugin boots in a frontend request' );
} catch ( \Throwable $e ) {
	$boot_error = $e;
	mcr_fail( 'the plugin boots in a frontend request', get_class( $e ) . ': ' . $e->getMessage() );
}

if ( null !== $boot_error ) {
	echo "\nAssertions: 1 | Passed: 0 | Failed: 1\n";
	exit( 1 );
}

// A realistic date format so the calendar's screen reader text is complete.
$GLOBALS['mcr_options']['date_format'] = 'F j, Y';

$rewards_css = mcrnu_read( 'assets/css/mcr-rewards.css' );
$system_css  = mcrnu_read( 'assets/css/mcr-design-system.css' );
$frontend_js = mcrnu_read( 'assets/js/mcr-frontend.js' );
$theme_css   = file_exists( MCRNU_WP_ROOT . '/style.css' ) ? (string) file_get_contents( MCRNU_WP_ROOT . '/style.css' ) : '';

$checkin_html = mcrnu_render_checkin(
	array(
		'status' => array(
			'claimed_today' => false,
			'streak'        => 1,
			'total_amount'  => 10,
			'bonus_amount'  => 0,
			'today_key'     => '2026-10-21',
			'calendar'      => mcrnu_calendar( 0, false ),
		),
		'streak'       => 1,
		'claimable'    => true,
		'stats'        => array(
			'balance'         => 1250,
			'lifetime_earned' => 4300,
			'lifetime_spent'  => 3050,
		),
		'profile'      => array(
			'name'     => 'Aiko',
			'avatar'   => '',
			'initials' => 'AI',
		),
		'today_earned' => 0,
	)
);

/* ==========================================================================
 * 1. The reward calendar: the visible text
 * ======================================================================== */

mcr_group( 'Reward calendar: the day markers' );

$dot_glyphs = array();

if ( preg_match_all( '/<span class="mcr-streak__dot"[^>]*>(.*?)<\/span>/s', $checkin_html, $dot_matches ) ) {
	foreach ( $dot_matches[1] as $dot ) {
		$dot_glyphs[] = trim( $dot );
	}
}

$entity_leaks = array();

foreach ( $dot_glyphs as $dot ) {
	$decoded = html_entity_decode( $dot, ENT_QUOTES, 'UTF-8' );

	if ( false !== strpos( $decoded, '&#' ) ) {
		$entity_leaks[] = $dot;
	}
}

assertCount(
	0,
	$entity_leaks,
	'the calendar day markers must render as glyphs, not as the literal text "&#183;" / "&#10003;" (the user currently reads the raw HTML entity inside every day cell)'
);

assertTrue(
	count( $dot_glyphs ) >= 7,
	'control: the reward calendar renders seven day markers'
);

/* ==========================================================================
 * 2. The wallet: live balance
 * ======================================================================== */

mcr_group( 'Wallet: the live balance' );

$wallet_balance = '';

if ( preg_match( '/<div class="mcr-wallet__balance">(.*?)<\/div>/s', $checkin_html, $wallet_match ) ) {
	$wallet_balance = $wallet_match[1];
}

assertTrue(
	false !== strpos( $wallet_balance, 'data-mcr-balance' ),
	'control: the wallet balance carries data-mcr-balance, so the script repaints it in place after a claim'
);

assertTrue(
	false !== strpos( $frontend_js, "querySelectorAll( '[data-mcr-balance]' )" ),
	'control: the frontend bundle repaints every [data-mcr-balance] element'
);

assertTrue(
	false !== strpos( $checkin_html, 'aria-live="polite"' ),
	'control: the page ships a polite live region, so a balance change is announced'
);

/* ==========================================================================
 * 3. Madara fidelity: the theme heading class
 * ======================================================================== */

mcr_group( 'Madara fidelity: the theme heading class' );

assertTrue(
	(bool) preg_match( '/\.mcr-section__title\.c-blog__heading\s*\{[^}]*width:\s*100%/s', $system_css ),
	'control: the design system makes .mcr-section__title.c-blog__heading full width, which is why a trailing badge drops onto its own line'
);

if ( '' !== $theme_css ) {
	assertTrue(
		(bool) preg_match( '/\.c-blog__heading\s*\{\s*width:\s*100%;\s*\}/', $theme_css ),
		'control: the theme\'s bare .c-blog__heading carries none of the theme heading style (no .style-1/2/3), it only sets width:100%'
	);
}

assertFalse(
	(bool) preg_match( '/class="mcr-section__title[^"]*\bc-blog__heading\b/', $checkin_html ),
	'the section titles must not carry c-blog__heading: the class is full width, so the header badge is pushed ~40px below the title instead of sitting beside it'
);

assertFalse(
	(bool) preg_match( '/class="mcr-section__title[^"]*\bc-blog__heading\b/', \MCR\Templates\Templates::part( 'ads-panel', array( 'enabled' => true, 'configured' => true ) ) ),
	'the ad panel title must not carry c-blog__heading either'
);

/* ==========================================================================
 * 4. The ad panel: the states
 * ======================================================================== */

mcr_group( 'Ad panel: the states' );

$not_configured = mcrnu_wp_normalise(
	\MCR\Templates\Templates::part(
		'ads-panel',
		array(
			'enabled'    => true,
			'configured' => false,
		)
	)
);

assertTrue(
	false !== strpos( $not_configured, 'not available yet' ),
	'control: with no provider configured the panel says the rewarded ads are unavailable'
);

assertFalse(
	false !== strpos( $not_configured, 'data-mcr-ad-start' ),
	'control: with no provider configured the panel renders no ad button at all'
);

assertFalse(
	(bool) preg_match( '/mcr-alert--success/', $not_configured ),
	'control: with no provider configured the panel never shows a success state'
);

$ads_disabled = mcrnu_wp_normalise(
	\MCR\Templates\Templates::part(
		'ads-panel',
		array(
			'enabled'    => false,
			'configured' => true,
		)
	)
);

assertTrue(
	false !== strpos( $ads_disabled, 'currently disabled' ),
	'control: with ad rewards switched off the panel says so'
);

assertFalse(
	false !== strpos( $ads_disabled, 'data-mcr-ad-start' ),
	'control: with ad rewards switched off the panel renders no ad button'
);

$capped = mcrnu_wp_normalise(
	\MCR\Templates\Templates::part(
		'ads-panel',
		array(
			'enabled'    => true,
			'configured' => true,
			'units'      => array( array( 'id' => 'rewarded_video', 'label' => 'Rewarded video' ) ),
			'ads'        => array(
				'today'     => 10,
				'remaining' => 0,
				'limit'     => 10,
			),
			'coins_per_ad' => 5,
			'daily_limit'  => 10,
			'cooldown'     => 300,
		)
	)
);

assertTrue(
	false !== strpos( $capped, 'Daily limit reached' ),
	'control: when the daily cap is reached the panel says the cap is reached'
);

assertTrue(
	(bool) preg_match( '/<button[^>]*\bdisabled\b/', $capped ),
	'control: when the daily cap is reached the ad button is disabled'
);

$unlimited = mcrnu_wp_normalise(
	\MCR\Templates\Templates::part(
		'ads-panel',
		array(
			'enabled'    => true,
			'configured' => true,
			'units'      => array( array( 'id' => 'rewarded_video', 'label' => 'Rewarded video' ) ),
			'ads'        => array(
				'today'     => 0,
				'remaining' => 0,
				'limit'     => 0,
			),
			'coins_per_ad' => 5,
			'daily_limit'  => 0,
			'cooldown'     => 300,
		)
	)
);

assertFalse(
	false !== strpos( $unlimited, '0 ads per day' ),
	'a daily limit of 0 means unlimited (AdRewardPolicy only caps when the limit is above 0), so the panel must not claim the reader may watch 0 ads per day'
);

assertFalse(
	false !== strpos( $unlimited, '0 of 0 ads left' ),
	'a daily limit of 0 means unlimited, so the panel must not claim 0 of 0 ads are left'
);

$two_units = mcrnu_wp_normalise(
	\MCR\Templates\Templates::part(
		'ads-panel',
		array(
			'enabled'    => true,
			'configured' => true,
			'units'      => array(
				array( 'id' => 'alpha', 'label' => 'Alpha placement' ),
				array( 'id' => 'beta', 'label' => 'Beta placement' ),
			),
			'ads'        => array(
				'today'     => 0,
				'remaining' => 10,
				'limit'     => 10,
			),
			'coins_per_ad' => 5,
			'daily_limit'  => 10,
			'cooldown'     => 300,
		)
	)
);

preg_match_all( '/<button[^>]*data-mcr-ad-start[^>]*>(.*?)<\/button>/s', $two_units, $unit_buttons );

assertSame(
	2,
	count( $unit_buttons[1] ),
	'control: every configured ad unit gets its own button'
);

$unit_labels = array_map( 'trim', $unit_buttons[1] );

assertSame(
	count( $unit_labels ),
	count( array_unique( $unit_labels ) ),
	'control: every ad unit gets a distinct button label, so two placements never render two identical buttons'
);

/* ==========================================================================
 * 5. The calendar states and the legend
 * ======================================================================== */

mcr_group( 'Reward calendar: the states and the legend' );

$calendar_html = mcrnu_render_checkin(
	array(
		'status' => array(
			'claimed_today' => false,
			'streak'        => 4,
			'total_amount'  => 10,
			'bonus_amount'  => 0,
			'today_key'     => '2026-10-21',
			'calendar'      => mcrnu_calendar( 3, false ),
		),
		'streak'    => 4,
		'claimable' => true,
	)
);

$strip_states = array();

if ( preg_match_all( '/<li class="mcr-streak__day([^"]*)"/', $calendar_html, $day_matches ) ) {
	foreach ( $day_matches[1] as $classes ) {
		foreach ( explode( ' ', $classes ) as $class ) {
			$class = trim( $class );

			if ( 0 === strpos( $class, 'is-' ) ) {
				$strip_states[ $class ] = true;
			}
		}
	}
}

$legend_states = array();

if ( preg_match_all( '/mcr-legend__dot (is-[a-z]+)/', $calendar_html, $legend_matches ) ) {
	foreach ( $legend_matches[1] as $class ) {
		$legend_states[ $class ] = true;
	}
}

foreach ( array( 'is-done', 'is-missed', 'is-locked', 'is-available' ) as $state ) {
	if ( empty( $strip_states[ $state ] ) ) {
		continue;
	}

	assertTrue(
		isset( $legend_states[ $state ] ),
		sprintf( 'the calendar renders a "%s" day, so the legend must explain it: the strip shows four states while the legend only documents three', $state )
	);
}

assertTrue(
	false !== strpos( $calendar_html, 'available, ready to claim' ),
	'control: the available day is announced in words, not only by colour'
);

assertTrue(
	(bool) preg_match( '/<li class="mcr-streak__day[^"]*is-today[^"]*" aria-current="date"/', $calendar_html ),
	'control: today carries aria-current="date"'
);

assertTrue(
	false !== strpos( $calendar_html, 'not claimed' ),
	'control: a missed day is announced in words'
);

assertTrue(
	false !== strpos( $calendar_html, 'upcoming' ),
	'control: an upcoming day is announced in words'
);

/* ==========================================================================
 * 6. Contrast, in both of the theme's schemes
 * ======================================================================== */

mcr_group( 'Contrast in the theme light scheme (body.text-ui-dark)' );

$light_tokens = mcrnu_rule_body( $system_css, '.mcr-scope' );
$dark_tokens  = mcrnu_rule_body( $system_css, 'body.text-ui-light .mcr-scope' );

assertTrue(
	'' !== $light_tokens,
	'control: the design system declares the light scheme tokens on .mcr-scope'
);

assertTrue(
	'' !== $dark_tokens,
	'control: the design system declares the dark scheme tokens on body.text-ui-light .mcr-scope (the theme inverts the schema: body_schema dark => body.text-ui-light)'
);

$button_rule = mcrnu_rule_body( $system_css, '.mcr-btn' );

assertTrue(
	(bool) preg_match( '/color:\s*#fff/i', $button_rule ),
	'control: the plugin button paints its label white'
);

$light_button = mcrnu_body_var( $light_tokens, '--mcr-accent-text' );
$dark_button  = mcrnu_body_var( $dark_tokens, '--mcr-accent-text' );

assertTrue(
	mcrnu_contrast( $light_button, '#ffffff' ) >= 4.5,
	sprintf( 'control: the primary button label passes AA in the theme light scheme (%s on #ffffff = %.2f:1)', $light_button, mcrnu_contrast( $light_button, '#ffffff' ) )
);

mcr_group( 'Contrast in the theme dark scheme (body.text-ui-light)' );

assertTrue(
	mcrnu_contrast( $dark_button, '#ffffff' ) >= 4.5,
	sprintf( 'the primary "Check In" button label must pass AA in the theme dark scheme (%s on #ffffff = %.2f:1, the AA threshold for 14px text is 4.5:1)', $dark_button, mcrnu_contrast( $dark_button, '#ffffff' ) )
);

mcr_group( 'Contrast of the gold call to action' );

$coin_300  = mcrnu_body_var( $light_tokens, '--mcr-coin-300' );
$coin_400  = mcrnu_body_var( $light_tokens, '--mcr-coin-400' );
$coin_ink  = mcrnu_body_var( $light_tokens, '--mcr-coin-ink' );

$gold_rule = mcrnu_rule_body( $rewards_css, '.mcr-scope .mcr-btn--gold' );

assertTrue(
	'' !== $gold_rule,
	'control: the rewards stylesheet restyles the gold call to action'
);

preg_match_all( '/var\((--mcr-coin-[0-9]+)\)/', $gold_rule, $gold_vars );

$gold_stops = array();

foreach ( $gold_vars[1] as $token ) {
	$value = mcrnu_body_var( $light_tokens, $token );

	if ( '' !== $value ) {
		$gold_stops[ $token ] = $value;
	}
}

assertTrue(
	count( $gold_stops ) >= 2,
	'control: the gold button gradient is built from the coin tokens'
);

$gold_worst = 99.0;

foreach ( $gold_stops as $token => $value ) {
	$ratio = mcrnu_contrast( $coin_ink, $value );

	if ( $ratio < $gold_worst ) {
		$gold_worst = $ratio;
	}
}

assertTrue(
	$gold_worst >= 4.5,
	sprintf( 'the gold call to action label must pass AA at every stop of its gradient (worst stop %.2f:1 against %s, the AA threshold for 14px text is 4.5:1)', $gold_worst, $coin_ink )
);

/* ==========================================================================
 * 7. The ad call to action at 360px
 * ======================================================================== */

mcr_group( 'Ad panel: the gold call to action on a narrow phone' );

$button_rule_body = mcrnu_rule_body( $system_css, '.mcr-btn' );

assertTrue(
	(bool) preg_match( '/white-space:\s*nowrap/', $button_rule_body ),
	'control: the plugin button is nowrap, so a long label overflows the button instead of wrapping'
);

assertTrue(
	(bool) preg_match( '/\.mcr-scope [^{}]*\.mcr-btn--(?:gold|block)[^{}]*\{[^}]*white-space:\s*normal/s', $rewards_css ),
	'the gold ad call to action must be allowed to wrap: its label carries the ad unit name, and on a 360px phone the nowrap label spills out of the button onto the card'
);

/* ==========================================================================
 * 8. The cascade between the two stylesheets
 * ======================================================================== */
mcr_group( 'The cascade between the two stylesheets' );

$system_gold_hover = '';
$rewards_gold_hover = '';

foreach ( mcrnu_css_rules( $system_css ) as $rule ) {
	if ( false !== strpos( $rule['selector'], '.mcr-btn--gold:hover' ) && false !== strpos( $rule['selector'], '.mcr-scope.mcr-scope' ) ) {
		$system_gold_hover = $rule['selector'];
		break;
	}
}

foreach ( mcrnu_css_rules( $rewards_css ) as $rule ) {
	if ( false !== strpos( $rule['selector'], '.mcr-btn--gold:hover' ) && false !== strpos( $rule['selector'], '.mcr-scope' ) ) {
		$rewards_gold_hover = $rule['selector'];
		break;
	}
}

assertTrue(
	'' !== $system_gold_hover && '' !== $rewards_gold_hover,
	'control: both stylesheets declare a gold button hover rule'
);

assertTrue(
	mcrnu_compare_specificity(
		mcrnu_specificity( $rewards_gold_hover ),
		mcrnu_specificity( $system_gold_hover )
	) > 0,
	sprintf(
		'the rewards stylesheet must win the gold button hover rule ("%s" is %s, the design system\'s "%s" is %s, so the rewards rule never applies)',
		$rewards_gold_hover,
		implode( ',', mcrnu_specificity( $rewards_gold_hover ) ),
		$system_gold_hover,
		implode( ',', mcrnu_specificity( $system_gold_hover ) )
	)
);

/* ==========================================================================
 * 8. The wallet's display name
 * ======================================================================== */

mcr_group( 'Wallet: the display name' );

$long_name_html = mcrnu_render_checkin(
	array(
		'status' => array(
			'claimed_today' => false,
			'streak'        => 9,
			'total_amount'  => 10,
			'bonus_amount'  => 0,
			'today_key'     => '2026-10-21',
			'calendar'      => mcrnu_calendar( 6, false ),
		),
		'streak'    => 9,
		'claimable' => true,
		'profile'   => array(
			'name'     => 'Maximilian Alexander Featherstonehaugh',
			'avatar'   => '',
			'initials' => 'MA',
		),
	)
);

$name_is_clipped = (bool) preg_match( '/\.mcr-scope \.mcr-wallet__name\s*\{[^}]*text-overflow:\s*ellipsis/s', $rewards_css );
$name_has_title  = (bool) preg_match( '/<span class="mcr-wallet__name"[^>]*\btitle=/', $long_name_html );

assertFalse(
	$name_is_clipped && ! $name_has_title,
	'a long display name must stay readable: the wallet clips it with an ellipsis and never exposes the full name (no title attribute)'
);

assertTrue(
	false !== strpos( $long_name_html, 'Maximilian Alexander Featherstonehaugh' ),
	'control: the full display name is in the markup, so a screen reader still reads it'
);

/* ==========================================================================
 * 9. The CSS scoping
 * ======================================================================== */

mcr_group( 'CSS scoping: no rule can reach outside the wrapper' );

$unscoped = array();

foreach ( mcrnu_css_rules( $rewards_css ) as $rule ) {
	$selector = $rule['selector'];

	// Keyframe stops (0%, 50%, 100%) are not selectors.
	if ( preg_match( '/^\d+(\.\d+)?%$/', $selector ) ) {
		continue;
	}

	if ( false === strpos( $selector, '.mcr-scope' ) ) {
		$unscoped[] = $selector;
	}
}

assertCount(
	0,
	$unscoped,
	'every rule in assets/css/mcr-rewards.css is scoped under .mcr-scope, so it can never reach the Madara theme or another plugin'
);

/* ==========================================================================
 * 10. The responsive rules and reduced motion
 * ======================================================================== */

mcr_group( 'Responsive rules' );

foreach ( array( 'min-width: 768px', 'min-width: 992px', 'max-width: 767px', 'max-width: 480px' ) as $query ) {
	assertTrue(
		false !== strpos( $rewards_css, $query ),
		sprintf( 'control: the rewards stylesheet carries the %s breakpoint (the theme uses 480/768/992/1200)', $query )
	);
}

assertTrue(
	(bool) preg_match( '/@media \(max-width: 480px\)\s*\{[^@]*\.mcr-wallet__stats\s*\{[^}]*grid-template-columns:\s*1fr/s', $rewards_css ),
	'control: below 480px the three wallet stat pills stack instead of collapsing into unreadable columns'
);

assertTrue(
	(bool) preg_match( '/@media \(max-width: 480px\)\s*\{[^@]*\.mcr-streak\s*\{[^}]*overflow-x:\s*auto/s', $system_css ),
	'control: below 480px the seven day strip becomes a scroll rail with 44px cells (design system)'
);

assertTrue(
	(bool) preg_match( '/@media \(hover: none\), \(pointer: coarse\)\s*\{[^@]*\.mcr-btn--sm\s*\{[^}]*min-height:\s*var\(--mcr-tap\)/s', $system_css ),
	'control: on a touch device every button, including the small one, is at least 44px tall (design system)'
);

mcr_group( 'Reduced motion' );

assertTrue(
	(bool) preg_match( '/@media \(prefers-reduced-motion: reduce\)\s*\{[^@]*\.mcr-streak__day\.is-available\s*\{[^}]*animation:\s*none/s', $rewards_css ),
	'the attention pulse on the available day is switched off for readers who ask for reduced motion'
);

/* ==========================================================================
 * 10. Madara fidelity: the brand colour must be themeable
 * ======================================================================== */

mcr_group( 'Madara fidelity: the accent colour' );

$literal_brand = preg_match_all( '/rgba\(\s*235\s*,\s*51\s*,\s*73\s*,/i', $rewards_css )
	+ preg_match_all( '/rgba\(\s*117\s*,\s*178\s*,\s*45\s*,/i', $rewards_css );

$fixed_brand_tokens = preg_match_all( '/var\(\s*--mcr-brand-(?:primary|primary-light|secondary)\b/', $rewards_css );

assertSame(
	0,
	$literal_brand,
	'the new stylesheet must not hard-code the brand colours as rgba(): Madara lets a site change its main colour, and the plugin has to follow it'
);

assertSame(
	0,
	$fixed_brand_tokens,
	'the new stylesheet must not use the fixed --mcr-brand-* tokens: the themeable --mcr-accent token has to drive them, otherwise the plugin keeps the stock red on a rebranded site'
);

/* ==========================================================================
 * 11. The weekday label in the streak strip
 * ======================================================================== */

mcr_group( 'The weekday label in the streak strip' );

$streak_php = mcrnu_read( 'templates/parts/streak.php' );

assertFalse(
	(bool) preg_match( '/gmdate\(\s*[\'"]D[\'"]/', $streak_php ),
	'the weekday label in the streak strip must be localised: gmdate(\'D\') always prints the English abbreviation, so a German site reads "Thu" and "Sat" instead of "Do" and "Sa"'
);

/* ==========================================================================
 * Summary
 * ======================================================================== */

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
		echo '  - [' . $failure['group'] . '] ' . $failure['message'] . "\n";

		if ( '' !== $failure['detail'] ) {
			echo '      ' . $failure['detail'] . "\n";
		}
	}

	exit( 1 );
}

exit( 0 );
