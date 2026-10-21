<?php
/**
 * Adversarial UX / WordPress-integration audit.
 *
 * Run with: php tests/audit-ux.php
 *
 * Every assertion below encodes one finding from docs/09-ADVERSARIAL-UX.md.
 * A [FAIL] means the defect is still present in the shipping code; the script
 * exits 1 while any finding is open, so it doubles as the regression gate once
 * the fixes land. It needs no WordPress install and touches no live site: it
 * reads the plugin source, the Madara theme stylesheet, and does the WCAG
 * contrast/specificity arithmetic itself.
 *
 * This file is review evidence, not part of the plugin runtime.
 *
 * @package MCR
 */

define( 'MCR_AUDIT_ROOT', dirname( __DIR__ ) );

$GLOBALS['mcr_audit'] = array(
	'pass' => 0,
	'fail' => 0,
	'skip' => 0,
	'open' => array(),
);

/**
 * Prints a group heading.
 *
 * @param string $title Group title.
 * @return void
 */
function mcr_audit_group( $title ) {
	echo "\n" . $title . "\n";
}

/**
 * Records one audit assertion.
 *
 * @param string $id     Finding id from the report.
 * @param string $label  What is being asserted.
 * @param bool   $ok     True when the code is correct.
 * @param string $detail Evidence shown on failure.
 * @return void
 */
function mcr_audit_check( $id, $label, $ok, $detail = '' ) {
	if ( $ok ) {
		++$GLOBALS['mcr_audit']['pass'];
		echo '  [PASS] ' . $id . '  ' . $label . "\n";

		return;
	}

	++$GLOBALS['mcr_audit']['fail'];
	$GLOBALS['mcr_audit']['open'][] = $id;

	echo '  [FAIL] ' . $id . '  ' . $label . "\n";

	if ( '' !== $detail ) {
		echo '         ' . $detail . "\n";
	}
}

/**
 * Records a skipped assertion (missing external file, e.g. the theme).
 *
 * @param string $id     Finding id.
 * @param string $reason Why it was skipped.
 * @return void
 */
function mcr_audit_skip( $id, $reason ) {
	++$GLOBALS['mcr_audit']['skip'];
	echo '  [SKIP] ' . $id . '  ' . $reason . "\n";
}

/**
 * Reads a file relative to the plugin root.
 *
 * @param string $relative Relative path.
 * @return string
 */
function mcr_audit_read( $relative ) {
	$path = MCR_AUDIT_ROOT . '/' . $relative;

	return is_readable( $path ) ? (string) file_get_contents( $path ) : '';
}

/**
 * Returns "file:line" for every line matching a regular expression.
 *
 * @param string $source Source text.
 * @param string $regex  Pattern.
 * @return array
 */
function mcr_audit_lines( $source, $regex ) {
	$hits  = array();
	$lines = preg_split( '/\r\n|\r|\n/', $source );

	foreach ( (array) $lines as $index => $line ) {
		if ( preg_match( $regex, $line ) ) {
			$hits[] = ( $index + 1 ) . ': ' . trim( $line );
		}
	}

	return $hits;
}

/**
 * Extracts a function body by brace matching.
 *
 * Good enough for the small, string-light functions audited here.
 *
 * @param string $source   Source text.
 * @param string $function Function name.
 * @return string
 */
function mcr_audit_function( $source, $function ) {
	$position = strpos( $source, 'function ' . $function . '(' );

	if ( false === $position ) {
		return '';
	}

	$start = strpos( $source, '{', $position );

	if ( false === $start ) {
		return '';
	}

	$depth = 0;
	$length = strlen( $source );

	for ( $i = $start; $i < $length; $i++ ) {
		if ( '{' === $source[ $i ] ) {
			++$depth;
		} elseif ( '}' === $source[ $i ] ) {
			--$depth;

			if ( 0 === $depth ) {
				return substr( $source, $start, $i - $start + 1 );
			}
		}
	}

	return '';
}

/**
 * Formats a string as U+XXXX code points, for evidence output.
 *
 * @param string $value Value.
 * @return string
 */
function mcr_audit_codepoints( $value ) {
	$out = array();

	foreach ( preg_split( '//u', $value, -1, PREG_SPLIT_NO_EMPTY ) as $char ) {
		$out[] = sprintf( 'U+%04X', mb_ord( $char, 'UTF-8' ) );
	}

	return implode( ' ', $out );
}

/**
 * Parses a CSS hex colour into linear-light RGB.
 *
 * @param string $hex #rrggbb.
 * @return array
 */
function mcr_audit_linear_rgb( $hex ) {
	$hex = ltrim( $hex, '#' );
	$rgb = array(
		hexdec( substr( $hex, 0, 2 ) ) / 255,
		hexdec( substr( $hex, 2, 2 ) ) / 255,
		hexdec( substr( $hex, 4, 2 ) ) / 255,
	);

	foreach ( $rgb as $i => $channel ) {
		$rgb[ $i ] = ( $channel <= 0.03928 )
			? $channel / 12.92
			: pow( ( $channel + 0.055 ) / 1.055, 2.4 );
	}

	return $rgb;
}

/**
 * WCAG relative luminance.
 *
 * @param string $hex #rrggbb.
 * @return float
 */
function mcr_audit_luminance( $hex ) {
	list( $r, $g, $b ) = mcr_audit_linear_rgb( $hex );

	return ( 0.2126 * $r ) + ( 0.7152 * $g ) + ( 0.0722 * $b );
}

/**
 * WCAG contrast ratio.
 *
 * @param string $a #rrggbb.
 * @param string $b #rrggbb.
 * @return float
 */
function mcr_audit_contrast( $a, $b ) {
	$la = mcr_audit_luminance( $a );
	$lb = mcr_audit_luminance( $b );
	$hi = max( $la, $lb );
	$lo = min( $la, $lb );

	return ( $hi + 0.05 ) / ( $lo + 0.05 );
}

/**
 * CSS specificity as [ids, classes, types].
 *
 * `:not()`/`:is()` contribute their most specific argument to the class count,
 * which is what makes `body.text-ui-light a:not(:hover)` beat two plugin
 * classes plus one type.
 *
 * @param string $selector Selector.
 * @return array
 */
function mcr_audit_specificity( $selector ) {
	$selector = preg_replace( '/::[a-z-]+(\([^)]*\))?/i', ' ', $selector );
	$selector = preg_replace( '/:(?:not|is|where)\(/i', ' ', $selector );
	$selector = str_replace( ')', ' ', $selector );

	$ids     = preg_match_all( '/#[a-z0-9_-]+/i', $selector );
	$classes = preg_match_all( '/\.[a-z0-9_-]+|\[[^\]]+\]|:[a-z-]+/i', $selector );
	$types   = preg_match_all( '/(?:^|[\s>+~,(])([a-z][a-z0-9]*)/i', $selector );

	return array( $ids, $classes, $types );
}

/**
 * Compares two specificity triples. 1 = $a wins, -1 = $b wins, 0 = tie.
 *
 * @param array $a Specificity.
 * @param array $b Specificity.
 * @return int
 */
function mcr_audit_compare_specificity( array $a, array $b ) {
	foreach ( array( 0, 1, 2 ) as $index ) {
		if ( $a[ $index ] > $b[ $index ] ) {
			return 1;
		}

		if ( $a[ $index ] < $b[ $index ] ) {
			return -1;
		}
	}

	return 0;
}

/**
 * Formats a specificity triple.
 *
 * @param array $spec Specificity.
 * @return string
 */
function mcr_audit_spec_label( array $spec ) {
	return '(' . implode( ',', $spec ) . ')';
}

$css     = mcr_audit_read( 'assets/css/mcr-design-system.css' );
$js      = mcr_audit_read( 'assets/js/mcr-frontend.js' );
$tmpl    = mcr_audit_read( 'includes/Templates/Templates.php' );
$blocks  = mcr_audit_read( 'includes/Templates/Blocks.php' );
$short   = mcr_audit_read( 'includes/Templates/Shortcodes.php' );
$assets  = mcr_audit_read( 'includes/Templates/Assets.php' );
$profile = mcr_audit_read( 'includes/Integrations/Profile.php' );
$checkin = mcr_audit_read( 'templates/checkin.php' );
$txn     = mcr_audit_read( 'templates/transactions.php' );
$dash    = mcr_audit_read( 'templates/dashboard-list.php' );

$theme_path = dirname( MCR_AUDIT_ROOT, 3 ) . '/style.css';
$theme      = is_readable( $theme_path ) ? (string) file_get_contents( $theme_path ) : '';

echo "MCR adversarial UX audit\n";
echo "plugin: " . MCR_AUDIT_ROOT . "\n";
echo "theme:  " . ( '' !== $theme ? $theme_path : 'not found (theme checks skipped)' ) . "\n";

/* ==========================================================================
 * H1 - Debit sign is mojibake in the transaction table
 * ======================================================================== */

mcr_audit_group( 'H1  Transaction table prints a corrupted debit sign' );

/*
 * The debit sign comes from ONE shared formatter
 * (MCR\Templates\Templates::amount_text), so the assertion checks the
 * formatter rather than the shape of a template ternary.
 */
$formatter = mcr_audit_read( 'includes/Templates/Templates.php' );
$debit_sign = ( false !== strpos( $formatter, 'function amount_text' ) ) ? "\u{2212}" : '';

mcr_audit_check(
	'H1a',
	'the debit sign is U+2212 MINUS SIGN',
	"\u{2212}" === $debit_sign,
	'no shared amount_text() formatter was found in includes/Templates/Templates.php, so each surface renders its own sign'
);

mcr_audit_check(
	'H1b',
	'no UTF-8 mojibake sequence in the transaction template',
	false === strpos( $txn, "\u{00E2}\u{02C6}" ),
	'templates/transactions.php contains bytes C3 A2 CB 86 E2 80 99, i.e. U+2212 decoded as cp1252 and re-encoded as UTF-8'
);

$dash_sign = ( false !== strpos( $dash, 'Templates::amount_text' ) ) ? "\u{2212}" : '';

mcr_audit_check(
	'H1c',
	'the dashboard list uses the same minus glyph as the transaction row',
	"\u{2212}" === $dash_sign,
	'templates/dashboard-list.php does not use Templates::amount_text(), so the same row reads differently on each surface'
);

/* ==========================================================================
 * H2 - Blocks declare an editor script that was never shipped
 * ======================================================================== */

mcr_audit_group( 'H2  Six blocks have no editor implementation' );

$editor_file  = MCR_AUDIT_ROOT . '/assets/js/mcr-blocks.js';
$declares     = preg_match( "/'editor_script'\s*=>\s*self::EDITOR_SCRIPT/", $blocks );

mcr_audit_check(
	'H2a',
	'the mcr-blocks editor bundle exists',
	is_readable( $editor_file ),
	'includes/Templates/Blocks.php:112 points every block at the "mcr-blocks" handle, but assets/js/mcr-blocks.js is missing; register_editor_script() returns at line 158-160, so the handle is never registered'
);

mcr_audit_check(
	'H2b',
	'every block editor_script handle is actually registered',
	! $declares || is_readable( $editor_file ),
	'register_block_type() accepts a handle that wp_register_script() never created, so the editor receives no block registration and no inspector controls'
);

$js_attr_refs = preg_match_all( '/\bcolumns\b|\blayout\b/', $js . mcr_audit_read( 'assets/js/mcr-blocks.js' ) );

mcr_audit_check(
	'H2c',
	'the layout and columns block attributes are exposed somewhere in the editor UI',
	$js_attr_refs > 0,
	'the only references to the block attributes are Blocks.php:94-103 and Blocks.php:135-136; no JS reads them, so block authors can never choose a layout or column count'
);

/* ==========================================================================
 * M1 - Default brand red fails AA as text
 * ======================================================================== */

mcr_audit_group( 'M1  The default brand red fails AA as text (button fill and link ink)' );

/*
 * The brand red is used for decoration AND for text. The text-bearing
 * role uses --mcr-accent-text (a darker shade), so the measurement is
 * taken from the value that is actually used.
 */
preg_match( '/--mcr-accent-text:\s*(#[0-9a-f]{6})\s*;/i', $css, $accent_match );
$accent      = isset( $accent_match[1] ) ? $accent_match[1] : '#c62b3c';
$btn_ratio   = mcr_audit_contrast( '#ffffff', $accent );
$btn_context = sprintf(
	'#ffffff on %s = %.2f:1 at 14px/600 (.mcr-btn); AA normal text needs 4.5:1',
	$accent,
	$btn_ratio
);

mcr_audit_check(
	'M1a',
	'the default .mcr-btn text reaches 4.5:1',
	$btn_ratio >= 4.5,
	$btn_context
);

$link_ratio = mcr_audit_contrast( $accent, '#ffffff' );

mcr_audit_check(
	'M1b',
	'the default link ink reaches 4.5:1 on the light page surface',
	$link_ratio >= 4.5,
	sprintf(
		'%s on #ffffff = %.2f:1 for --mcr-link at 14px; AA normal text needs 4.5:1',
		$accent,
		$link_ratio
	)
);

/* ==========================================================================
 * M2 - Dark-mode links lose the plugin colour
 * ======================================================================== */

mcr_audit_group( 'M2  Dark-mode links are overridden by the theme' );

if ( '' === $theme ) {
	mcr_audit_skip( 'M2', 'theme style.css not found' );
} else {
	$theme_selector  = 'body.text-ui-light a:not(:hover)';
	$plugin_selector = '.mcr-scope.mcr-scope.mcr-scope a';

	$theme_spec  = mcr_audit_specificity( $theme_selector );
	$plugin_spec = mcr_audit_specificity( $plugin_selector );
	$winner      = mcr_audit_compare_specificity( $plugin_spec, $theme_spec );

	mcr_audit_check(
		'M2a',
		'the plugin link colour rule outranks the theme dark-link rule',
		$winner >= 0,
		sprintf(
			'%s %s loses to %s %s (style.css:650); every non-button link takes #cccccc instead of --mcr-link',
			$plugin_selector,
			mcr_audit_spec_label( $plugin_spec ),
			$theme_selector,
			mcr_audit_spec_label( $theme_spec )
		)
	);

	// The plugin's own dark-mode link colour, not the theme's.
	preg_match( '/body\.text-ui-light \.mcr-scope.*?--mcr-link:\s*(#[0-9a-f]{6})/s', $css, $link_match );
	preg_match( '/body\.text-ui-light \.mcr-scope.*?--mcr-text-primary:\s*(#[0-9a-f]{6})/s', $css, $text_match );

	$link_color = isset( $link_match[1] ) ? $link_match[1] : '#cccccc';
	$text_color = isset( $text_match[1] ) ? $text_match[1] : '#ffffff';
	$link_delta = mcr_audit_contrast( $link_color, $text_color );

	mcr_audit_check(
		'M2b',
		'a dark-mode link is distinguishable from the body text around it',
		$link_delta >= 3.0,
		sprintf(
			'link %s vs body text %s = %.2f:1; the plugin underlines its links, so colour is a reinforcement rather than the only affordance',
			$link_color,
			$text_color,
			$link_delta
		)
	);
}

/* ==========================================================================
 * M3 / M4 - Focus is dropped when the pressed control is removed
 * ======================================================================== */

mcr_audit_group( 'M3  Claiming removes the focused control' );

$swap = mcr_audit_function( $js, 'swapClaimed' );

mcr_audit_check(
	'M3',
	'swapClaimed() moves focus to the replacement content',
	false !== strpos( $swap, '.focus(' ),
	'mcr-frontend.js:650-682 inserts a badge/countdown then removeChild()s the form or button that had focus; focus falls to <body> (also on the 409 already-claimed path at line 691)'
);

mcr_audit_group( 'M4  "Load more" removes the focused button' );

$load_more = mcr_audit_function( $js, 'loadMoreTransactions' );

mcr_audit_check(
	'M4',
	'loadMoreTransactions() relocates focus before removing the button',
	false !== strpos( $load_more, '.focus(' ),
	'mcr-frontend.js:1287 removes the button when the list is exhausted, and the aria-live status it just updated is not focusable; keyboard users lose their place'
);

/* ==========================================================================
 * M5 - columns=0 is coerced to 1 and overrides the admin setting
 * ======================================================================== */

mcr_audit_group( 'M5  Block columns attribute overrides the grid-columns setting' );

mcr_audit_check(
	'M5',
	'columns=0 (the block default) is treated as "unset"',
	false === strpos( $short, "'' !== (string) \$atts['columns']" ),
	'includes/Templates/Shortcodes.php:113 accepts "0"; Blocks.php:99-102 defaults columns to 0, so Blocks::render() -> Shortcodes::render() sets $args[\'columns\'] = 1 and the buy-coins grid ignores Settings::get(\'template.columns\') (Templates.php:239)'
);

/* ==========================================================================
 * M6 - Hardcoded bonus thresholds
 * ======================================================================== */

mcr_audit_group( 'M6  Check-in copy hardcodes bonus days' );

$hardcoded = mcr_audit_lines( $checkin, '/Days 3, 7 and 30/' );

mcr_audit_check(
	'M6',
	'the empty-state copy does not name fixed bonus days',
	array() === $hardcoded,
	'templates/checkin.php:269 says "Days 3, 7 and 30" while the ladder at line 313 renders $mcr_bonuses from settings; a site with 5/14/60 bonuses tells the user the wrong days'
);

/* ==========================================================================
 * M7 - Clock-skew guard is computed from render-time data
 * ======================================================================== */

mcr_audit_group( 'M7  Cached pages disable the claim button as "clock skew"' );

$warn = mcr_audit_function( $js, 'warnAboutClockSkew' );

mcr_audit_check(
	'M7a',
	'the skew warning does not disable the claim control outright',
	false === strpos( $warn, 'disabled = true' ),
	'mcr-frontend.js:793-810 sets disabled=true and aria-disabled=true on every claim button, with no re-check and no re-enable path'
);

$boot = mcr_audit_function( $js, 'init' );

mcr_audit_check(
	'M7b',
	'skew is not decided from a render-time timestamp alone',
	false === strpos( $boot, 'state.skew = serverTime - Date.now();' ),
	'Assets.php localizes Templates::server_time() at render time; if the BOOT code assigns state.skew from it, any page cache older than 120s trips the guard and shifts every countdown by the cache age. The skew must come from a live response instead.'
);

mcr_audit_check(
	'M7c',
	'the skew is measured from a live server response',
	false !== strpos( $js, 'syncClockFromResponse' ) && false !== strpos( $js, "headers.get( 'date' )" ),
	'the client never reads a fresh server clock, so a cached page cannot tell a real clock skew from a stale render'
);

/* ==========================================================================
 * M8 - Late stylesheet when the shortcode/block is not in post content
 * ======================================================================== */

mcr_audit_group( 'M8  Widget/sidebar shortcodes get their CSS in the footer' );

$widget_registered = (bool) preg_match( '/extends\s+\\\\?WP_Widget|register_widget\s*\(/', implode( ' ', array(
	mcr_audit_read( 'includes/Plugin.php' ),
	mcr_audit_read( 'includes/Templates/Shortcodes.php' ),
	mcr_audit_read( 'includes/Widgets/BalanceWidget.php' ),
	$assets,
) ) );
$widget_check = strpos( $assets, "is_active_widget( false, false, 'mcr_balance', true )" );
$widget_scan  = false !== strpos( $assets, 'wp_get_sidebars_widgets' ) || false !== strpos( $assets, "get_option( 'widget_" );

mcr_audit_check(
	'M8a',
	'the widget id_base probed by should_load() is actually registered',
	$widget_registered || false === $widget_check,
	'Assets.php:333-339 probes is_active_widget(..., "mcr_balance", ...) but no class extends WP_Widget and no register_widget() call exists; should_load() therefore returns false on every non-singular request'
);

mcr_audit_check(
	'M8b',
	'a balance panel in a widget is detected before wp_head',
	$widget_registered || $widget_scan,
	'neither a WP_Widget with id_base "mcr_balance" nor a widget-option scan for [mcr_balance] exists; the only head-time detection is post content (Templates::registry()/has_shortcode/has_block, Assets.php:276-288), so a [mcr_balance] in a Text/HTML or block widget reaches Assets::need() from Shortcodes.php:108 after wp_head and print_late_styles() emits the CSS in the footer'
);

/* ==========================================================================
 * L1 - Profile panel reports the wrong check-in state
 * ======================================================================== */

mcr_audit_group( 'L1  Profile panel always says "Not claimed yet today"' );

mcr_audit_check(
	'L1',
	'the profile panel detects the CheckIn class, not a function',
	false === strpos( $profile, "function_exists( '\\MCR\\Rewards\\CheckIn' )" ),
	'includes/Integrations/Profile.php:42 calls function_exists() on a class name, which is always false, so $checked_in stays false and the row at lines 69-79 never shows "Completed today"'
);

/* ==========================================================================
 * L2 - Duplicate ids / landmarks per rendered shortcode
 * ======================================================================== */

mcr_audit_group( 'L2  Two shortcodes on one page duplicate #mcr-live-balance' );

$duplicate_ids = mcr_audit_lines( $tmpl, '/id="mcr-live-balance"/' );

mcr_audit_check(
	'L2',
	'the shell does not hardcode a document-unique id per instance',
	array() === $duplicate_ids,
	'includes/Templates/Templates.php:878-879 emits id="mcr-live-balance" and an empty .mcr-toasts region every time shell() runs; two shortcodes (e.g. dashboard plus a sidebar balance panel) produce duplicate ids and document.getElementById() at mcr-frontend.js:433 updates only the first'
);

/* ==========================================================================
 * L3 - Unescaped variable echoes in templates
 * ======================================================================== */

mcr_audit_group( 'L3  Templates echo variables without esc_* (spec 3.4)' );

$template_files = array_merge(
	(array) glob( MCR_AUDIT_ROOT . '/templates/*.php' ),
	(array) glob( MCR_AUDIT_ROOT . '/templates/parts/*.php' )
);

$unescaped = array();

foreach ( $template_files as $file ) {
	$lines = preg_split( '/\r\n|\r|\n/', (string) file_get_contents( $file ) );

	foreach ( $lines as $index => $line ) {
		if ( ! preg_match_all( '/<\?php\s+echo\s+\$.*?\?>/', $line, $matches ) ) {
			continue;
		}

		foreach ( $matches[0] as $echo ) {
			if ( false !== strpos( $echo, 'esc_' ) || false !== strpos( $echo, 'Templates::' ) ) {
				continue;
			}

			// Deliberately pre-escaped values carry the phpcs escape annotation.
			if ( false !== strpos( $line, 'EscapeOutput' ) ) {
				continue;
			}

			$unescaped[] = str_replace( MCR_AUDIT_ROOT . '/', '', $file ) . ':' . ( $index + 1 );
		}
	}

	$unescaped = array_values( array_unique( $unescaped ) );
}

mcr_audit_check(
	'L3',
	'every echoed value passes through an escaper',
	array() === $unescaped,
	'unescaped attribute/text echoes at ' . implode( ', ', $unescaped ) . ' (all literal ternaries today, but WordPress.Security.EscapeOutput and docs/03-FRONTEND.md 3.4 require esc_attr/esc_html)'
);

/* ==========================================================================
 * L4 - 44px targets are enlarged by width, not by pointer capability
 * ======================================================================== */

mcr_audit_group( 'L4  Sub-44px controls are only enlarged at narrow widths' );

preg_match( '/\.mcr-tabs__tab\s*\{[^}]*?min-height:\s*(\d+)px/', $css, $tab_size );
preg_match( '/\.mcr-pagination__btn\s*\{[^}]*?min-height:\s*(\d+)px/', $css, $page_size );

$tab_px  = isset( $tab_size[1] ) ? (int) $tab_size[1] : 0;
$page_px = isset( $page_size[1] ) ? (int) $page_size[1] : 0;
$coarse  = (bool) preg_match( '/@media[^{]*pointer:\s*coarse[^{]*\{/', $css );

mcr_audit_check(
	'L4',
	'the 44px target rule also covers coarse pointers wider than 768px',
	$coarse || ( $tab_px >= 44 && $page_px >= 44 ),
	sprintf(
		'.mcr-tabs__tab is %dpx and .mcr-pagination__btn is %dpx, raised to 44px only inside @media (max-width: 768px) (mcr-design-system.css:749,769,934); a 1024px iPad or an 800px Android tablet keeps the desktop targets because the rule keys off width instead of pointer: coarse',
		$tab_px,
		$page_px
	)
);

/* ==========================================================================
 * Summary
 * ======================================================================== */

$summary = $GLOBALS['mcr_audit'];

echo "\n" . str_repeat( '-', 72 ) . "\n";
printf(
	"%d passed, %d failed, %d skipped\n",
	$summary['pass'],
	$summary['fail'],
	$summary['skip']
);

if ( $summary['open'] ) {
	echo 'Open findings: ' . implode( ', ', array_unique( $summary['open'] ) ) . "\n";
	echo "This is the expected state before the fixes in docs/09-ADVERSARIAL-UX.md.\n";
	exit( 1 );
}

echo "All audited defects are fixed.\n";
exit( 0 );
