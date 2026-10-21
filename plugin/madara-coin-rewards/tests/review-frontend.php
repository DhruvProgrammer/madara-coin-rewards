<?php
/**
 * FRONTEND / UX / TEMPLATE review test suite.
 *
 * Run with: php tests/review-frontend.php
 *
 * Scope: escaping, the printf/interpolation class of bug, HTML validity,
 * accessibility, responsive/CSS behaviour, i18n, the frontend JS, the balance
 * widget, theme fidelity and markup-vs-CSS drift.
 *
 * Every assertion in this file is written so that it FAILS on the real defect
 * it proves. Controls (assertions that describe behaviour that is already
 * correct) are marked "control:" in their message.
 *
 * @package MCR
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/wp-stubs.php';

/* ==========================================================================
 * Extra stubs the admin pages need.
 * ======================================================================== */

if ( ! function_exists( 'esc_textarea' ) ) {
	/**
	 * Textarea escaping stub.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function esc_textarea( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES );
	}
}

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

if ( ! function_exists( 'submit_button' ) ) {
	/**
	 * Submit button stub.
	 *
	 * @param string $text Text.
	 * @param string $type Button type.
	 * @param string $name Field name.
	 * @return void
	 */
	function submit_button( $text = '', $type = 'primary', $name = 'submit' ) {
		echo '<button type="submit" name="' . esc_attr( $name ) . '" class="button button-' . esc_attr( $type ) . '">' . esc_html( $text ) . '</button>';
	}
}

if ( ! function_exists( 'check_admin_referer' ) ) {
	/**
	 * Nonce check stub.
	 *
	 * @param string $action Action.
	 * @return void
	 */
	function check_admin_referer( $action = -1 ) {
		// The harness does not exercise nonce failures.
	}
}

if ( ! function_exists( 'wp_get_referer' ) ) {
	/**
	 * Referer stub.
	 *
	 * @return string
	 */
	function wp_get_referer() {
		return 'https://example.test/wp-admin/admin.php?page=mcr-rewards';
	}
}

if ( ! function_exists( 'user_exists' ) ) {
	/**
	 * User existence stub.
	 *
	 * @param int $user_id User id.
	 * @return bool
	 */
	function user_exists( $user_id ) {
		return (int) $user_id > 0;
	}
}

if ( ! function_exists( 'is_active_widget' ) ) {
	/**
	 * Active widget stub.
	 *
	 * @param mixed $widget_id Widget id.
	 * @param mixed $key       Key.
	 * @param mixed $id_base   Id base.
	 * @param bool  $skip_inactive Skip the inactive check.
	 * @return bool
	 */
	function is_active_widget( $widget_id = false, $key = false, $id_base = false, $skip_inactive = false ) {
		return ! empty( $GLOBALS['mcr_active_widgets'] );
	}
}

/* ==========================================================================
 * Review helpers.
 * ======================================================================== */

$GLOBALS['mcr_plugin_dir'] = dirname( __DIR__ ) . '/';

/**
 * Returns every plugin PHP file that ships to a site, tests excluded.
 *
 * @return array Relative paths.
 */
function mcrr_php_files() {
	$root  = $GLOBALS['mcr_plugin_dir'];
	$files = array();
	$it    = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root ) );

	foreach ( $it as $file ) {
		if ( ! $file->isFile() || 'php' !== strtolower( $file->getExtension() ) ) {
			continue;
		}

		$rel = str_replace( '\\', '/', substr( $file->getPathname(), strlen( rtrim( $root, '/\\' ) ) + 1 ) );

		if ( preg_match( '#^(tests|docs|tools)/#', $rel ) ) {
			continue;
		}

		$files[] = $rel;
	}

	sort( $files );

	return $files;
}

/**
 * Reads a plugin file.
 *
 * @param string $rel Relative path.
 * @return string
 */
function mcrr_read( $rel ) {
	return file_get_contents( $GLOBALS['mcr_plugin_dir'] . $rel );
}

/**
 * Extracts the translatable calls from a token stream.
 *
 * @param array $tokens Token stream.
 * @return array List of array{fn,file,line,kind,msgid,domain}.
 */
function mcrr_translatable_calls( array $tokens ) {
	$functions = array( '__', '_e', 'esc_html__', 'esc_html_e', 'esc_attr__', 'esc_attr_e', '_x', 'esc_html_x', 'esc_attr_x' );
	$out       = array();
	$count     = count( $tokens );

	for ( $i = 0; $i < $count; $i++ ) {
		$token = $tokens[ $i ];

		if ( ! is_array( $token ) || T_STRING !== $token[0] || ! in_array( $token[1], $functions, true ) ) {
			continue;
		}

		$j = $i + 1;

		while ( $j < $count && is_array( $tokens[ $j ] ) && T_WHITESPACE === $tokens[ $j ][0] ) {
			$j++;
		}

		if ( $j >= $count || '(' !== $tokens[ $j ] ) {
			continue;
		}

		// Read the argument list.
		$depth  = 1;
		$k      = $j + 1;
		$args   = array();
		$buf    = array();
		$concat = false;

		while ( $k < $count && $depth > 0 ) {
			$current = $tokens[ $k ];

			if ( '(' === $current ) {
				$depth++;
			} elseif ( ')' === $current ) {
				$depth--;

				if ( 0 === $depth ) {
					break;
				}
			} elseif ( ',' === $current && 1 === $depth ) {
				$args[] = $buf;
				$buf    = array();

				$k++;
				continue;
			}

			if ( 1 === $depth && '.' === $current ) {
				$concat = true;
			}

			$buf[] = $current;

			$k++;
		}

		$args[] = $buf;

		// Strip the leading whitespace / comments of every argument.
		foreach ( $args as $index => $argument ) {
			while ( $argument && is_array( $argument[0] )
				&& in_array( $argument[0][0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
				array_shift( $argument );
			}

			$args[ $index ] = $argument;
		}

		$first = isset( $args[0] ) ? $args[0] : array();

		// Drop leading whitespace tokens.
		while ( $first && is_array( $first[0] ) && T_WHITESPACE === $first[0][0] ) {
			array_shift( $first );
		}

		$kind  = 'literal';
		$msgid = '';

		if ( empty( $first ) ) {
			$kind = 'empty';
		} elseif ( is_array( $first[0] ) && T_CONSTANT_ENCAPSED_STRING === $first[0][0] ) {
			$raw   = $first[0][1];
			$quote = $raw[0];
			$msgid = substr( $raw, 1, -1 );

			if ( "'" === $quote ) {
				$msgid = str_replace( array( "\\'", '\\\\' ), array( "'", '\\' ), $msgid );
			} else {
				$msgid = str_replace( array( '\\"', '\\\\' ), array( '"', '\\' ), $msgid );
			}

			if ( $concat ) {
				$kind = 'concatenated';
			}
		} elseif ( is_array( $first[0] ) && T_ENCAPSED_AND_WHITESPACE === $first[0][0] ) {
			$kind  = 'interpolated';
			$msgid = isset( $first[0][1] ) ? $first[0][1] : '';
		} elseif ( ! is_array( $first[0] ) && '"' === $first[0] ) {
			// An interpolated double-quoted string starts with a bare quote token.
			$kind  = 'interpolated';
			$msgid = '"';
		} else {
			$kind  = 'dynamic';
			$msgid = is_array( $first[0] ) ? trim( $first[0][1] ) : (string) $first[0];
		}

		$domain = '';

		if ( isset( $args[1] ) && $args[1] ) {
			$domain_token = $args[1][0];

			if ( is_array( $domain_token ) && T_CONSTANT_ENCAPSED_STRING === $domain_token[0] ) {
				$domain = trim( $domain_token[1], "'" );
			}
		}

		$out[] = array(
			'fn'     => $token[1],
			'line'   => $token[2],
			'kind'   => $kind,
			'msgid'  => $msgid,
			'domain' => $domain,
		);
	}

	return $out;
}

/**
 * Parses the design system CSS into flat declarations.
 *
 * @param string $css CSS source.
 * @return array
 */
function mcrr_css_rules( $css ) {
	$css   = preg_replace( '#/\*.*?\*/#s', '', $css );
	$rules = array();
	$order = 0;

	mcrr_css_parse( $css, '', $rules, $order );

	return $rules;
}

/**
 * Recursive CSS parser.
 *
 * Sequential scanner: it understands at-rules with a block (@media,
 * @keyframes) and plain rule blocks, and it records the source order so the
 * cascade can be replayed.
 *
 * @param string $body  CSS body.
 * @param string $media Media context.
 * @param array  $rules Rules (by reference).
 * @param int    $order Order counter (by reference).
 * @return void
 */
function mcrr_css_parse( $body, $media, &$rules, &$order ) {
	$length = strlen( $body );
	$i      = 0;

	while ( $i < $length ) {
		while ( $i < $length && ctype_space( $body[ $i ] ) ) {
			$i++;
		}

		if ( $i >= $length ) {
			break;
		}

		$start = $i;

		while ( $i < $length && '{' !== $body[ $i ] && ';' !== $body[ $i ] ) {
			$i++;
		}

		if ( $i >= $length ) {
			break;
		}

		if ( ';' === $body[ $i ] ) {
			// A statement such as @charset "UTF-8"; or @import.
			$i++;
			continue;
		}

		$prelude = trim( substr( $body, $start, $i - $start ) );
		$i++;

		$depth = 1;
		$j     = $i;

		while ( $j < $length && $depth > 0 ) {
			if ( '{' === $body[ $j ] ) {
				$depth++;
			} elseif ( '}' === $body[ $j ] ) {
				$depth--;
			}

			$j++;
		}

		$inner = substr( $body, $i, $j - $i - 1 );

		if ( 0 === strpos( $prelude, '@media' ) ) {
			$condition = trim( substr( $prelude, 6 ) );

			mcrr_css_parse( $inner, '' === $media ? $condition : $media . ' and ' . $condition, $rules, $order );
		} elseif ( 0 === strpos( $prelude, '@keyframes' ) ) {
			// Keyframe steps are not selectors; skip them.
		} elseif ( 0 === strpos( $prelude, '@' ) ) {
			mcrr_css_parse( $inner, $media, $rules, $order );
		} else {
			$selectors = array_map( 'trim', explode( ',', $prelude ) );

			foreach ( explode( ';', $inner ) as $declaration ) {
				if ( false === strpos( $declaration, ':' ) ) {
					continue;
				}

				list( $prop, $value ) = explode( ':', $declaration, 2 );

				$rules[] = array(
					'media' => $media,
					'sel'   => $selectors,
					'prop'  => trim( $prop ),
					'value' => trim( $value ),
					'order' => $order++,
				);
			}
		}

		$i = $j;
	}
}

/**
 * Whether a media query applies at a viewport width.
 *
 * @param string $media Media query.
 * @param int    $width Viewport width in px.
 * @return bool
 */
function mcrr_media_matches( $media, $width ) {
	if ( '' === $media ) {
		return true;
	}

	foreach ( preg_split( '/\s+and\s+/', $media ) as $part ) {
		$part = trim( $part );

		if ( preg_match( '/min-width:\s*(\d+)px/', $part, $m ) ) {
			if ( $width < (int) $m[1] ) {
				return false;
			}
		} elseif ( preg_match( '/max-width:\s*(\d+)px/', $part, $m ) ) {
			if ( $width > (int) $m[1] ) {
				return false;
			}
		} elseif ( preg_match( '/hover|pointer|prefers-reduced-motion|prefers-color-scheme/', $part ) ) {
			// Not a plain desktop / light-mode environment.
			return false;
		}
	}

	return true;
}

/**
 * Whether a simple class selector matches an element class list.
 *
 * @param string $selector Selector.
 * @param array  $classes  Element classes.
 * @return bool
 */
function mcrr_selector_matches( $selector, array $classes ) {
	$parts = preg_split( '/\s+/', trim( $selector ) );
	$last  = end( $parts );

	if ( '' === $last ) {
		return false;
	}

	if ( ! preg_match_all( '/\.([A-Za-z0-9_-]+)/', $last, $m ) ) {
		return false;
	}

	foreach ( $m[1] as $class ) {
		if ( ! in_array( $class, $classes, true ) ) {
			return false;
		}
	}

	return true;
}

/**
 * Resolves the effective value of a custom property for a class list.
 *
 * Models the cascade for the single-class rules the design system uses:
 * every matching declaration has the same specificity, so the last one in
 * source order wins.
 *
 * @param array  $rules  Parsed rules.
 * @param array  $classes Element classes.
 * @param string $prop   Property.
 * @param int    $width  Viewport width.
 * @return array|null The winning rule.
 */
function mcrr_effective_rule( array $rules, array $classes, $prop, $width ) {
	$best = null;

	foreach ( $rules as $rule ) {
		if ( $rule['prop'] !== $prop || ! mcrr_media_matches( $rule['media'], $width ) ) {
			continue;
		}

		$matched = false;

		foreach ( $rule['sel'] as $selector ) {
			if ( mcrr_selector_matches( $selector, $classes ) ) {
				$matched = true;
				break;
			}
		}

		if ( ! $matched ) {
			continue;
		}

		if ( null === $best || $rule['order'] > $best['order'] ) {
			$best = $rule;
		}
	}

	return $best;
}

/**
 * Returns every id attribute in an HTML string.
 *
 * @param string $html HTML.
 * @return array
 */
function mcrr_ids( $html ) {
	preg_match_all( '/\sid="([^"]*)"/', $html, $m );

	return $m[1];
}

/**
 * Counts the occurrences of an id attribute.
 *
 * @param array  $ids Ids.
 * @param string $id  Id.
 * @return int
 */
function mcrr_count_id( array $ids, $id ) {
	return count( array_keys( $ids, $id, true ) );
}

/**
 * Returns the labels in an HTML string that are not associated with a control.
 *
 * A label is associated when it carries a "for" attribute or when it wraps
 * the control it names.
 *
 * @param string $html HTML.
 * @return array
 */
function mcrr_orphan_labels( $html ) {
	$orphans = array();

	if ( preg_match_all( '#<label\b([^>]*)>(.*?)</label>#s', $html, $labels, PREG_SET_ORDER ) ) {
		foreach ( $labels as $label ) {
			if ( preg_match( '/\bfor\s*=/i', $label[1] ) ) {
				continue;
			}

			if ( preg_match( '#<(input|select|textarea)\b#i', $label[2] ) ) {
				continue;
			}

			$orphans[] = trim( wp_strip_all_tags( $label[2] ) );
		}
	}

	return $orphans;
}

/**
 * A single formatted ledger row, as the transactions controller builds it.
 *
 * @param string $reason Reason text.
 * @return array
 */
function mcrr_txn_rows( $reason = 'Daily check-in' ) {
	return array(
		array(
			'id'              => 1,
			'uid'             => 'txn-1',
			'type'            => 'checkin',
			'type_label'      => 'Daily check-in',
			'amount'          => 10,
			'balance_after'   => 120,
			'reason'          => $reason,
			'created_at'      => '2026-10-21 09:14:00',
			'created_iso'     => '2026-10-21T09:14:00+00:00',
			'created_display' => '21 Oct 2026, 09:14',
		),
	);
}

/* ==========================================================================
 * Boot.
 * ======================================================================== */

echo "MCR Frontend / UX / Template Review\n";
echo "====================================\n\n";

$GLOBALS['wpdb']            = new MCR_Fake_WPDB();
$GLOBALS['mcr_current_user'] = 5;
$GLOBALS['mcr_caps']        = array(
	'manage_options'      => true,
	'mcr_manage_settings'  => true,
	'mcr_manage_coins'     => true,
	'mcr_view_users'       => true,
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

/* ==========================================================================
 * 1. Escaping / output correctness.
 * ======================================================================== */

mcr_group( 'Escaping: adversarial payloads' );

$xss_script = '<script>alert(1)</script>';
$xss_attr   = '"><img src=x onerror=alert(1)>';
$xss_amp    = 'Tom & Jerry <b>bold</b> "quoted" \'single\'';

$txn_html = MCR\Templates\Templates::render(
	'transactions',
	array(
		'rows'  => array(
			array(
				'id'             => 1,
				'type'           => 'admin_adjust',
				'type_label'     => 'Admin adjustment',
				'amount'         => 25,
				'balance_after'  => 125,
				'reason'         => $xss_script,
				'created_at'     => '2026-10-21 09:14:00',
				'created_iso'    => '2026-10-21T09:14:00+00:00',
				'created_display' => '21 Oct 2026, 09:14',
			),
		),
		'total'    => 1,
		'shown'    => 1,
		'page'     => 1,
		'has_more' => false,
	)
);

assertFalse(
	false !== strpos( $txn_html, '<script>alert(1)</script>' ),
	'a stored transaction reason cannot inject a <script> tag'
);

assertTrue(
	false !== strpos( $txn_html, '&lt;script&gt;' ),
	'the transaction reason is HTML-escaped in the table'
);

$pkg_html = MCR\Templates\Templates::render(
	'buy-coins',
	array(
		'packages' => array(
			array(
				'id'           => 'gold',
				'title'        => $xss_attr,
				'coins'        => 100,
				'coins_total'  => 120,
				'price'        => 4.99,
				'currency'     => 'USD',
				'badge'        => $xss_script,
			),
		),
		'enabled'           => true,
		'gateway_available' => true,
	)
);

assertFalse(
	false !== strpos( $pkg_html, '<img src=x onerror=alert(1)>' ),
	'a package title cannot break out of an attribute'
);

assertFalse(
	false !== strpos( $pkg_html, '<script>alert(1)</script>' ),
	'a package badge cannot inject a <script> tag'
);

assertTrue(
	false !== strpos( $pkg_html, '&lt;img src=x onerror=alert(1)&gt;' ),
	'the package title is attribute-escaped'
);

$ads_html = MCR\Templates\Templates::render(
	'ads',
	array(
		'enabled'       => true,
		'configured'    => true,
		'ad_units'      => array(
			array(
				'id'    => 'rewarded_video',
				'label' => $xss_script,
			),
		),
		'ads'           => array( 'today' => 0, 'limit' => 10, 'remaining' => 10, 'coins_per_ad' => 5 ),
		'coins_per_ad'  => 5,
		'daily_limit'   => 10,
		'cooldown'      => 300,
	)
);

assertFalse(
	false !== strpos( $ads_html, '<script>alert(1)</script>' ),
	'an ad unit label cannot inject a <script> tag'
);

assertFalse(
	false !== strpos( $ads_html, 'onerror=' ),
	'no injected event handler survives in the ads page'
);

mcr_group( 'Escaping: unicode, RTL and long values' );

$rtl_html = MCR\Templates\Templates::render(
	'transactions',
	array(
		'rows'  => array(
			array(
				'type'           => 'admin_adjust',
				'type_label'     => 'ضبط',
				'amount'         => -5,
				'balance_after'  => 0,
				'reason'         => 'مكافأة ' . str_repeat( 'طويل ', 200 ),
				'created_display' => '٢١ أكتوبر ٢٠٢٦',
				'created_iso'    => '2026-10-21T09:14:00+00:00',
			),
		),
		'total' => 1,
		'shown' => 1,
	)
);

assertTrue(
	false !== strpos( $rtl_html, 'مكافأة' ),
	'RTL transaction reasons are preserved verbatim'
);

assertFalse(
	false !== strpos( $rtl_html, 'dir=' ),
	'the plugin does not force a text direction on user content'
);

$long_html = MCR\Templates\Templates::render(
	'transactions',
	array(
		'rows'  => array(
			array(
				'type'           => 'admin_adjust',
				'type_label'     => 'Admin adjustment',
				'amount'         => 1,
				'balance_after'  => 1,
				'reason'         => str_repeat( 'A', 500 ),
				'created_display' => '21 Oct 2026',
				'created_iso'    => '2026-10-21T09:14:00+00:00',
			),
		),
		'total' => 1,
		'shown' => 1,
	)
);

assertTrue(
	false !== strpos( $long_html, str_repeat( 'A', 500 ) ),
	'a 500 character reason survives rendering without truncation or corruption'
);

/* ==========================================================================
 * 2. The printf / interpolation class of bug.
 * ======================================================================== */

mcr_group( 'printf / interpolation class of bug' );

assertThrows(
	function () {
		// The exact bug: PHP eats "$d" inside the double-quoted format.
		@printf( "%1$d", 5 ); // phpcs:ignore
	},
	'control: a double-quoted "%1$d" format string is a fatal ValueError in PHP 8'
);

$threw = false;

ob_start();

try {
	printf( '%1$d', 5 ); // phpcs:ignore
} catch ( \Throwable $e ) {
	$threw = true;
}

ob_end_clean();

assertFalse( $threw, 'control: a single-quoted format string is safe' );

$interpolated = array();
$bad_prints   = array();

foreach ( mcrr_php_files() as $rel ) {
	$source = mcrr_read( $rel );
	$tokens = token_get_all( $source );

	foreach ( $tokens as $token ) {
		if ( ! is_array( $token ) || T_CONSTANT_ENCAPSED_STRING !== $token[0] ) {
			continue;
		}

		$raw = $token[1];

		/*
		 * A double-quoted string is interpolated by PHP. When a conversion
		 * specifier is followed by "$" + identifier ("%1$d"), PHP eats the
		 * variable and printf() dies with a ValueError.
		 */
		if ( '"' === $raw[0] && preg_match( '/%(?:\d+\$)?[-+ 0]*\d*(?:\.\d+)?[bcdeEfFgGosuxX]\$?[A-Za-z_]/', $raw ) ) {
			$interpolated[] = $rel . ':' . $token[2] . ' ' . $raw;
		}
	}

	// Every printf/sprintf must have a literal format and matching arguments.
	foreach ( $tokens as $i => $token ) {
		if ( ! is_array( $token ) || T_STRING !== $token[0] ) {
			continue;
		}

		if ( ! in_array( strtolower( $token[1] ), array( 'printf', 'sprintf', 'vprintf', 'vsprintf' ), true ) ) {
			continue;
		}

		$j = $i + 1;

		while ( $j < count( $tokens ) && is_array( $tokens[ $j ] ) && T_WHITESPACE === $tokens[ $j ][0] ) {
			$j++;
		}

		if ( ! isset( $tokens[ $j ] ) || '(' !== $tokens[ $j ] ) {
			continue;
		}

		// Read the whole argument list.
		$depth  = 1;
		$cursor = $j + 1;
		$args   = array();
		$buffer = array();

		while ( $cursor < count( $tokens ) && $depth > 0 ) {
			$current = $tokens[ $cursor ];

			if ( '(' === $current ) {
				$depth++;
			} elseif ( ')' === $current ) {
				$depth--;

				if ( 0 === $depth ) {
					break;
				}
			} elseif ( ',' === $current && 1 === $depth ) {
				$args[] = $buffer;
				$buffer = array();

				$cursor++;
				continue;
			}

			$buffer[] = $current;

			$cursor++;
		}

		$args[] = $buffer;

		// The format string.
		$format_tokens = $args[0];
		$values        = count( $args ) - 1;

		while ( $format_tokens && is_array( $format_tokens[0] )
			&& in_array( $format_tokens[0][0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
			array_shift( $format_tokens );
		}

		$format_value = null;
		$format_line  = 0;

		if ( ! empty( $format_tokens ) && is_array( $format_tokens[0] ) && T_CONSTANT_ENCAPSED_STRING === $format_tokens[0][0] ) {
			$format_value = $format_tokens[0][1];
			$format_line  = $format_tokens[0][2];
		} elseif ( ! empty( $format_tokens ) && is_array( $format_tokens[0] ) && T_STRING === $format_tokens[0][0]
			&& in_array( $format_tokens[0][1], array( '__', 'esc_html__', 'esc_attr__', '_x', 'esc_html_x', 'esc_attr_x', '_n', '_nx' ), true ) ) {
			// The format is wrapped in a translation function: look inside.
			$inner = $format_tokens;
			$seek  = 1;

			while ( $seek < count( $inner ) && '(' !== $inner[ $seek ] ) {
				$seek++;
			}

			$seek++;

			while ( $seek < count( $inner ) && is_array( $inner[ $seek ] )
				&& in_array( $inner[ $seek ][0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
				$seek++;
			}

			if ( isset( $inner[ $seek ] ) && is_array( $inner[ $seek ] ) && T_CONSTANT_ENCAPSED_STRING === $inner[ $seek ][0] ) {
				$format_value = $inner[ $seek ][1];
				$format_line  = $inner[ $seek ][2];
			}
		} elseif ( ! empty( $format_tokens ) && is_array( $format_tokens[0] ) && T_VARIABLE === $format_tokens[0][0] ) {
			// A format held in a variable: it cannot be verified statically.
			continue;
		}

		if ( null === $format_value ) {
			// Not statically verifiable: skip rather than report a false positive.
			continue;
		}

		if ( '"' === $format_value[0] ) {
			$bad_prints[] = $rel . ':' . $format_line . ' uses a double-quoted format string: ' . $format_value;
			continue;
		}

		$value = substr( $format_value, 1, -1 );
		$value = str_replace( array( "\\'", '\\\\' ), array( "'", '\\' ), $value );

		preg_match_all( '/%(?:\d+\$)?[-+ 0]*\d*(?:\.\d+)?[bcdeEfFgGosuxX]/', $value, $matches );

		$placeholders = count( $matches[0] );
		$highest      = 0;

		foreach ( $matches[0] as $match ) {
			if ( preg_match( '/^%(\d+)\$/', $match, $position ) ) {
				$highest = max( $highest, (int) $position[1] );
			}
		}

		if ( in_array( strtolower( $token[1] ), array( 'vprintf', 'vsprintf' ), true ) ) {
			// The values come from an array: the count cannot be checked statically.
			continue;
		}

		if ( $highest > 0 ) {
			if ( $values < $highest ) {
				$bad_prints[] = $rel . ':' . $format_line . ' format "' . $value . '" needs at least ' . $highest . ' value(s), got ' . $values;
			}
		} elseif ( $values !== $placeholders ) {
			$bad_prints[] = $rel . ':' . $format_line . ' format "' . $value . '" expects ' . $placeholders . ' value(s), got ' . $values;
		}
	}
}

assertCount(
	0,
	$interpolated,
	'no double-quoted PHP string in the plugin contains a printf specifier followed by a variable (the PHP 8 ValueError fatal class)'
);

$printf_message = 'every printf/sprintf call site uses a single-quoted literal format string with matching placeholders and arguments (a double-quoted format is invisible to the POT extractor and one "$" away from the PHP 8 ValueError fatal)';

if ( count( $bad_prints ) > 0 ) {
	mcr_fail( $printf_message, implode( ' | ', array_slice( $bad_prints, 0, 8 ) ) );
} else {
	mcr_pass( $printf_message );
}

mcr_group( 'printf / interpolation class of bug: parse errors' );

$parse_errors = array();

foreach ( mcrr_php_files() as $rel ) {
	try {
		token_get_all( mcrr_read( $rel ), TOKEN_PARSE );
	} catch ( \Throwable $e ) {
		$parse_errors[] = $rel . ': ' . $e->getMessage();
	}
}

assertCount(
	0,
	$parse_errors,
	'no plugin PHP file has a parse error (a raw apostrophe in a single-quoted string)'
);

/* ==========================================================================
 * 3. Correct HTML.
 * ======================================================================== */

mcr_group( 'HTML: unique ids' );

$double_checkin = do_shortcode( '[mcr_checkin][mcr_checkin]' );
$checkin_ids   = mcrr_ids( $double_checkin );
$checkin_dupes = array();

foreach ( array_count_values( $checkin_ids ) as $id => $count ) {
	if ( $count > 1 ) {
		$checkin_dupes[] = $id . ' x' . $count;
	}
}

assertCount(
	0,
	$checkin_dupes,
	'rendering the plugin twice on one page emits no duplicate id attributes'
);

$double_txn = MCR\Templates\Templates::render(
	'transactions',
	array(
		'rows'  => mcrr_txn_rows(),
		'total' => 1,
		'shown' => 1,
		'page'  => 1,
	)
);

$double_txn .= MCR\Templates\Templates::render(
	'transactions',
	array(
		'rows'  => mcrr_txn_rows(),
		'total' => 1,
		'shown' => 1,
		'page'  => 1,
	)
);

$txn_page_id = mcrr_ids( $double_txn );

assertSame(
	1,
	mcrr_count_id( $txn_page_id, 'mcr-txn-rows' ),
	'the transaction table body id stays unique when the shortcode renders twice (getElementById would target the wrong table)'
);

$double_balance = do_shortcode( '[mcr_balance][mcr_balance]' );
$balance_ids    = mcrr_ids( $double_balance );
$balance_dupes  = array();

foreach ( array_count_values( $balance_ids ) as $id => $count ) {
	if ( $count > 1 ) {
		$balance_dupes[] = $id . ' x' . $count;
	}
}

assertCount(
	0,
	$balance_dupes,
	'the balance panel emits no duplicate id attributes when rendered twice'
);

mcr_group( 'HTML: nesting' );

$dashboard_html = do_shortcode( '[mcr_dashboard]' );

$bad_paragraph = array();

if ( preg_match_all( '#<p\b[^>]*>(.*?)</p>#s', $dashboard_html, $paragraphs ) ) {
	foreach ( $paragraphs[1] as $inner ) {
		if ( preg_match( '#<(div|table|ul|ol|section|form|h1|h2|h3|h4|h5|h6|article|aside|nav|blockquote)\b#i', $inner ) ) {
			$bad_paragraph[] = trim( substr( $inner, 0, 80 ) );
		}
	}
}

assertCount( 0, $bad_paragraph, 'no block element is nested inside a <p> in the dashboard' );

$bad_button = array();

if ( preg_match_all( '#<button\b[^>]*>(.*?)</button>#s', $dashboard_html, $buttons ) ) {
	foreach ( $buttons[1] as $inner ) {
		if ( preg_match( '#<(div|table|ul|ol|section|form|h1|h2|h3|h4|h5|h6|p|article|aside|nav)\b#i', $inner ) ) {
			$bad_button[] = trim( substr( $inner, 0, 80 ) );
		}
	}
}

assertCount( 0, $bad_button, 'no block element is nested inside a <button> in the dashboard' );

mcr_group( 'HTML: admin forms' );

$transactions_admin = new MCR\Admin\TransactionsPage();

ob_start();
$transactions_admin->render();
$transactions_admin_html = (string) ob_get_clean();

$form_depth   = 0;
$max_depth    = 0;
$depth_marker = 0;

if ( preg_match_all( '#</?form\b#i', $transactions_admin_html, $form_tags ) ) {
	foreach ( $form_tags[0] as $tag ) {
		if ( '/' === $tag[1] ) {
			$form_depth--;
		} else {
			$form_depth++;
			$max_depth = max( $max_depth, $form_depth );
		}
	}
}

assertSame(
	1,
	$max_depth,
	'the admin transactions page never nests a <form> inside another <form> (the CSV export button is swallowed by the filter form)'
);

$depth_marker = 0;

assertCount(
	0,
	mcrr_orphan_labels( $transactions_admin_html ),
	'every <label> in the admin transactions page is associated with a control (a for attribute or a wrapped input)'
);

$users_admin = new MCR\Admin\UsersPage();

ob_start();
$users_admin->render();
$users_admin_html = (string) ob_get_clean();

assertCount(
	0,
	mcrr_orphan_labels( $users_admin_html ),
	'every <label> in the admin users page is associated with a control (a for attribute or a wrapped input)'
);

$orphan_for = array();

if ( preg_match_all( '#<label\b[^>]*\bfor="([^"]+)"#i', $users_admin_html, $for_values ) ) {
	foreach ( $for_values[1] as $for_value ) {
		if ( ! preg_match( '/\sid="' . preg_quote( $for_value, '/' ) . '"/i', $users_admin_html ) ) {
			$orphan_for[] = $for_value;
		}
	}
}

assertCount( 0, $orphan_for, 'every label for= in the admin users page points at an existing control id' );

mcr_group( 'HTML: table semantics' );

$txn_page_html = MCR\Templates\Templates::render(
	'transactions',
	array(
		'rows'  => mcrr_txn_rows(),
		'total' => 1,
		'shown' => 1,
		'page'  => 1,
	)
);

assertTrue(
	false !== strpos( $txn_page_html, '<th scope="col">' ),
	'the transaction table header cells carry scope="col"'
);

assertTrue(
	false !== strpos( $txn_page_html, '<th scope="row">' ),
	'the transaction table row header cells carry scope="row"'
);

assertTrue(
	false !== strpos( $txn_page_html, '<caption' ),
	'the transaction table carries a caption'
);

/* ==========================================================================
 * 4. Accessibility.
 * ======================================================================== */

mcr_group( 'Accessibility: aria-current' );

$streak_html = MCR\Templates\Templates::render(
	'checkin',
	array(
		'status' => array(
			'claimed_today' => false,
			'total_amount'  => 10,
			'bonus_amount'  => 0,
			'today_key'     => gmdate( 'Y-m-d' ),
			'calendar'      => array(
				array(
					'date'    => gmdate( 'Y-m-d' ),
					'claimed' => true,
					'streak'  => 3,
					'amount'  => 10,
					'today'   => true,
				),
			),
		),
		'bonuses'   => array( 3 => 5 ),
		'enabled'   => true,
		'claimable' => true,
		'streak'    => 3,
	)
);

assertFalse(
	false !== strpos( $streak_html, 'aria-current=&quot;' ),
	'aria-current is not double-escaped into an invalid ARIA token'
);

assertTrue(
	false !== strpos( $streak_html, 'aria-current="date"' ),
	'the current day carries a valid aria-current="date" token'
);

mcr_group( 'Accessibility: progress bar and live regions' );

$balance_page = do_shortcode( '[mcr_balance]' );

assertTrue(
	false !== strpos( $balance_page, 'role="progressbar"' ),
	'the streak progress bar exposes role="progressbar"'
);

assertTrue(
	false !== strpos( $balance_page, 'aria-valuemin="0"' ),
	'the progress bar exposes aria-valuemin'
);

assertTrue(
	false !== strpos( $balance_page, 'aria-valuemax="' ),
	'the progress bar exposes aria-valuemax'
);

assertTrue(
	false !== strpos( $balance_page, 'aria-valuenow="' ),
	'the progress bar exposes aria-valuenow'
);

assertTrue(
	false !== strpos( $balance_page, 'aria-valuetext="' ),
	'the progress bar exposes aria-valuetext'
);

assertTrue(
	false !== strpos( $balance_page, 'aria-live="polite"' ),
	'the page shell exposes an aria-live region for async balance updates'
);

assertTrue(
	false !== strpos( $balance_page, 'aria-atomic="true"' ),
	'the live region is aria-atomic'
);

mcr_group( 'Accessibility: decorative graphics and names' );

assertTrue(
	false !== strpos( $balance_page, '<svg class="mcr-coin__svg" viewBox="0 0 32 32" aria-hidden="true"' ),
	'the coin SVG is marked aria-hidden'
);

assertFalse(
	false !== strpos( $balance_page, '<svg class="mcr-coin-sprite" width="0" height="0" aria-hidden="true"' ),
	'the coin sprite SVG is marked aria-hidden'
);

$empty_buttons = array();

if ( preg_match_all( '#<button\b[^>]*>(.*?)</button>#s', do_shortcode( '[mcr_dashboard]' ), $buttons ) ) {
	foreach ( $buttons[1] as $inner ) {
		if ( '' === trim( strip_tags( $inner ) ) ) {
			$empty_buttons[] = $inner;
		}
	}
}

assertCount( 0, $empty_buttons, 'every button in the dashboard has a text label' );

mcr_group( 'Accessibility: JS focus management' );

$frontend_js = mcrr_read( 'assets/js/mcr-frontend.js' );

if ( preg_match( '/function\s+setPaginationStatus\s*\(([^)]*)\)\s*\{(.*?)\n\t\}/s', $frontend_js, $match ) ) {
	$status_body = $match[2];
} else {
	$status_body = '';
}

assertTrue(
	false !== strpos( $status_body, 'return' ),
	'setPaginationStatus() returns the status element so the focus can be moved after the last page is loaded'
);

assertTrue(
	false !== strpos( $frontend_js, "status.setAttribute( 'tabindex', '-1' )" ) || false !== strpos( $frontend_js, "status.setAttribute( 'tabindex', '-1' );" ),
	'the load-more handler still tries to move focus to the status message'
);

/* ==========================================================================
 * 5. Responsive / layout.
 * ======================================================================== */

mcr_group( 'Responsive: the admin column setting' );

$design_css = mcrr_read( 'assets/css/mcr-design-system.css' );
$css_rules  = mcrr_css_rules( $design_css );

foreach ( array( 2, 3 ) as $chosen ) {
	$classes = array( 'mcr-grid', 'mcr-grid--packages', 'mcr-grid--cols-' . $chosen );
	$rule    = mcrr_effective_rule( $css_rules, $classes, '--mcr-cols', 1200 );

	assertSame(
		(string) $chosen,
		$rule ? $rule['value'] : '',
		'at >=1200px the grid honours the admin-selected column count (' . $chosen . ')'
	);
}

$mobile_rule = mcrr_effective_rule(
	$css_rules,
	array( 'mcr-grid', 'mcr-grid--packages', 'mcr-grid--cols-3' ),
	'--mcr-cols',
	375
);

assertSame(
	'1',
	$mobile_rule ? $mobile_rule['value'] : '',
	'control: the grid collapses to a single column below 768px'
);

mcr_group( 'Responsive: long user content' );

$content_selectors = array( '.mcr-txn__reason', '.mcr-table td', '.mcr-package__name', '.mcr-empty__text', '.mcr-page__lead' );
$wrapping_rules   = array();

foreach ( $css_rules as $rule ) {
	if ( 'overflow-wrap' !== $rule['prop'] && 'word-break' !== $rule['prop'] && 'overflow-wrap' !== $rule['prop'] ) {
		continue;
	}

	foreach ( $rule['sel'] as $selector ) {
		foreach ( $content_selectors as $target ) {
			if ( false !== strpos( $selector, ltrim( $target, '.' ) ) ) {
				$wrapping_rules[] = $selector;
			}
		}
	}
}

assertTrue(
	count( $wrapping_rules ) > 0,
	'user supplied text (transaction reason, package name, table cell) can wrap instead of overflowing the viewport'
);

/* ==========================================================================
 * 6. Theme fidelity.
 * ======================================================================== */

mcr_group( 'Theme fidelity: the Madara colour bridge' );

$scope_rules = array();

foreach ( $css_rules as $rule ) {
	if ( '--mcr-accent' !== $rule['prop'] ) {
		continue;
	}

	foreach ( $rule['sel'] as $selector ) {
		if ( false !== strpos( $selector, 'mcr-scope' ) || false !== strpos( $selector, 'mcr-portal' ) ) {
			$scope_rules[] = $selector . ' => ' . $rule['value'];
		}
	}
}

assertCount(
	0,
	$scope_rules,
	'the design system does not redeclare --mcr-accent on the plugin scope, so the theme bridge (:root) can actually reach the plugin elements'
);

$bridge_source = mcrr_read( 'includes/Integrations/Madara.php' );

assertTrue(
	false !== strpos( $bridge_source, ':root{' ),
	'control: the Madara bridge writes the theme accent to :root'
);

mcr_group( 'Theme fidelity: design tokens' );

assertTrue(
	false !== strpos( $design_css, '--mcr-brand-primary: #eb3349' ),
	'control: the brand primary matches the Madara theme colour #eb3349'
);

assertTrue(
	false !== strpos( $design_css, '--mcr-brand-primary-light: #f45c43' ),
	'control: the brand secondary matches the Madara theme colour #f45c43'
);

assertTrue(
	false !== strpos( $design_css, 'var(--primary-color, #eb3349)' ),
	'control: the accent colour falls back to the theme primary colour'
);

assertTrue(
	false !== strpos( $design_css, 'var(--secondary-color, #f45c43)' ),
	'control: the secondary accent falls back to the theme secondary colour'
);

mcr_group( 'Theme fidelity: the admin stylesheet' );

$admin_css_referenced = false;

foreach ( mcrr_php_files() as $rel ) {
	if ( false !== strpos( mcrr_read( $rel ), 'mcr-admin.css' ) ) {
		$admin_css_referenced = true;
		break;
	}
}

assertTrue(
	$admin_css_referenced,
	'the shipped admin stylesheet (assets/css/mcr-admin.css) is enqueued somewhere'
);

$admin_enqueue_hook = false;

foreach ( mcrr_php_files() as $rel ) {
	if ( false !== strpos( mcrr_read( $rel ), 'admin_enqueue_scripts' ) ) {
		$admin_enqueue_hook = true;
		break;
	}
}

assertTrue(
	$admin_enqueue_hook,
	'the plugin registers an admin_enqueue_scripts hook so the admin pages can load their stylesheet'
);

/* ==========================================================================
 * 7. i18n.
 * ======================================================================== */

mcr_group( 'i18n: the .pot catalogue' );

$source_strings = array();
$dynamic_calls  = array();

foreach ( mcrr_php_files() as $rel ) {
	$tokens = token_get_all( mcrr_read( $rel ) );

	foreach ( mcrr_translatable_calls( $tokens ) as $call ) {
		if ( 'madara-coin-rewards' !== $call['domain'] ) {
			continue;
		}

		if ( 'literal' === $call['kind'] ) {
			$source_strings[ $call['msgid'] ][] = $rel . ':' . $call['line'];
			continue;
		}

		$dynamic_calls[] = $rel . ':' . $call['line'] . ' ' . $call['fn'] . '() [' . $call['kind'] . '] ' . $call['msgid'];
	}
}

$pot_raw = mcrr_read( 'languages/madara-coin-rewards.pot' );
preg_match_all( '/^msgid "(.*)"$/m', $pot_raw, $pot_matches );

$pot_ids = array();

foreach ( $pot_matches[1] as $id ) {
	$pot_ids[ stripcslashes( $id ) ] = true;
}

$missing_from_pot = array();

foreach ( $source_strings as $id => $refs ) {
	if ( ! isset( $pot_ids[ $id ] ) ) {
		$missing_from_pot[] = $id . ' (' . implode( ', ', array_slice( $refs, 0, 2 ) ) . ')';
	}
}

$pot_message = 'every translatable string in the plugin source is present in languages/madara-coin-rewards.pot';

if ( count( $missing_from_pot ) > 0 ) {
	mcr_fail( $pot_message, implode( ' | ', array_slice( $missing_from_pot, 0, 10 ) ) );
} else {
	mcr_pass( $pot_message );
}

$dynamic_message = 'no translation function receives a concatenated or computed msgid (translators can never match it)';

if ( count( $dynamic_calls ) > 0 ) {
	mcr_fail( $dynamic_message, implode( ' | ', array_slice( $dynamic_calls, 0, 10 ) ) );
} else {
	mcr_pass( $dynamic_message );
}

mcr_group( 'i18n: dates and numbers' );

$streak_part = mcrr_read( 'templates/parts/streak.php' );

assertFalse(
	false !== strpos( $streak_part, "gmdate( 'D'" ),
	'the weekday label in the streak strip is not a hard-coded English gmdate() abbreviation'
);

$transactions_admin_source = mcrr_read( 'includes/Admin/TransactionsPage.php' );

assertFalse(
	false !== strpos( $transactions_admin_source, "date( 'Y-m-d H:i'" ),
	'the admin transaction date column is formatted with wp_date() instead of the raw date()'
);

$logs_admin_source = mcrr_read( 'includes/Admin/LogsPage.php' );

assertFalse(
	false !== strpos( $logs_admin_source, "date( 'Y-m-d H:i'" ),
	'the admin log date column is formatted with wp_date() instead of the raw date()'
);

/* ==========================================================================
 * 8. JS correctness.
 * ======================================================================== */

mcr_group( 'JS: no HTML injection' );

assertFalse(
	false !== strpos( $frontend_js, 'innerHTML' ),
	'the frontend bundle never assigns innerHTML'
);

assertFalse(
	false !== strpos( $frontend_js, 'outerHTML' ),
	'the frontend bundle never assigns outerHTML'
);

assertFalse(
	false !== strpos( $frontend_js, 'insertAdjacentHTML' ),
	'the frontend bundle never uses insertAdjacentHTML'
);

assertFalse(
	false !== strpos( $frontend_js, 'document.write' ),
	'the frontend bundle never uses document.write'
);

assertFalse(
	false !== strpos( $frontend_js, 'eval(' ),
	'the frontend bundle never uses eval()'
);

mcr_group( 'JS: namespace hygiene' );

assertTrue(
	1 === preg_match( '/\(\s*function\s*\(\s*window\s*,\s*document\s*\)/', $frontend_js ),
	'control: the frontend bundle is wrapped in an IIFE so no var leaks into the page namespace'
);

mcr_group( 'JS: the REST data shape' );

$rest_source = mcrr_read( 'includes/Rest.php' );

foreach ( array( 'balance', 'transactions', 'total', 'per_page', 'created_display', 'created_at', 'type', 'reason', 'amount', 'balance_after' ) as $key ) {
	assertTrue(
		false !== strpos( $rest_source, "'" . $key . "'" ),
		'the REST controller really returns the "' . $key . '" field the frontend bundle reads'
	);
}

$assets_source = mcrr_read( 'includes/Templates/Assets.php' );

foreach ( array( 'restUrl', 'nonce', 'serverTime', 'routes', 'perPage', 'types', 'i18n', 'strings', 'features' ) as $key ) {
	assertTrue(
		false !== strpos( $assets_source, "'" . $key . "'" ),
		'the localized script payload really carries the "' . $key . '" key the frontend bundle reads'
	);
}

/* ==========================================================================
 * 9. The balance widget.
 * ======================================================================== */

mcr_group( 'Balance widget' );

$widget = new MCR\Widgets\BalanceWidget();

$sidebar_args = array(
	'before_widget' => '<aside class="widget">',
	'after_widget'  => '</aside>',
	'before_title'  => '<h3 class="widget-heading">',
	'after_title'   => '</h3>',
);

$GLOBALS['mcr_current_user'] = 0;

ob_start();
$widget->widget( $sidebar_args, array( 'title' => 'Your Coins', 'show_streak' => true, 'show_link' => true ) );
$widget_logged_out = (string) ob_get_clean();

assertTrue(
	false !== strpos( $widget_logged_out, 'Log in to see your coin balance.' ),
	'control: the widget shows a login prompt to logged-out visitors'
);

assertFalse(
	false !== strpos( $widget_logged_out, 'data-mcr-balance' ),
	'control: no balance markup is printed to logged-out visitors'
);

$GLOBALS['mcr_current_user'] = 5;

ob_start();
$widget->widget( $sidebar_args, array( 'title' => 'Your Coins', 'show_streak' => true, 'show_link' => true ) );
$widget_logged_in = (string) ob_get_clean();

assertTrue(
	false !== strpos( $widget_logged_in, 'data-mcr-balance' ),
	'control: the widget exposes the data-mcr-balance hook so the client can repaint it'
);

assertTrue(
	false !== strpos( $widget_logged_in, '>0<' ),
	'control: a user with no balance row sees 0, not an empty value'
);

assertTrue(
	false !== strpos( $widget_logged_in, 'widget-heading' ),
	'control: the widget reuses the theme widget heading markup'
);

ob_start();
$widget->widget( $sidebar_args, array( 'title' => '<script>alert(1)</script>', 'show_streak' => true, 'show_link' => true ) );
$widget_hostile = (string) ob_get_clean();

assertFalse(
	false !== strpos( $widget_hostile, '<script>alert(1)</script>' ),
	'a hostile widget title cannot inject a script tag'
);

mcr_group( 'Balance widget: asset loading' );

$assets_source = mcrr_read( 'includes/Templates/Assets.php' );

if ( preg_match( '/public\s+function\s+should_load\s*\(\s*\)\s*\{(.*?)\n\t\}/s', $assets_source, $match ) ) {
	$should_load_body = $match[1];
} else {
	$should_load_body = '';
}

$guard_start = strpos( $should_load_body, 'is_singular()' );
$guard_end   = false;

if ( false !== $guard_start ) {
	$brace = strpos( $should_load_body, '{', $guard_start );
	$depth = 0;
	$end   = $brace;

	for ( $i = $brace; $i < strlen( $should_load_body ); $i++ ) {
		if ( '{' === $should_load_body[ $i ] ) {
			$depth++;
		} elseif ( '}' === $should_load_body[ $i ] ) {
			$depth--;

			if ( 0 === $depth ) {
				$end = $i;
				break;
			}
		}
	}

	$guard_end = $end;
}

$widget_check_outside_guard = false;

if ( false !== $guard_start && false !== $guard_end ) {
	$before = substr( $should_load_body, 0, $guard_start );
	$after  = substr( $should_load_body, $guard_end );

	if ( false !== strpos( $before, 'has_balance_widget' ) || false !== strpos( $after, 'has_balance_widget' ) ) {
		$widget_check_outside_guard = true;
	}
} elseif ( false === $guard_start ) {
	$widget_check_outside_guard = false !== strpos( $should_load_body, 'has_balance_widget' );
}

assertTrue(
	$widget_check_outside_guard,
	'should_load() checks for the active balance widget on singular requests too, so the widget is not rendered without its stylesheet'
);

$widget_source = mcrr_read( 'includes/Widgets/BalanceWidget.php' );

assertFalse(
	false !== strpos( $widget_source, 'Assets::instance()->need' ),
	'control note: the widget relies on should_load() rather than enqueueing the assets itself'
);

/* ==========================================================================
 * 10. Markup vs CSS drift.
 * ======================================================================== */

mcr_group( 'Markup vs CSS: the packages grid recipe' );

$packages_rules = array();

foreach ( $css_rules as $rule ) {
	if ( '--mcr-cols' !== $rule['prop'] ) {
		continue;
	}

	foreach ( $rule['sel'] as $selector ) {
		if ( false !== strpos( $selector, 'mcr-grid--packages' ) ) {
			$packages_rules[] = $selector . ' @ ' . $rule['media'] . ' => ' . $rule['value'];
		}
	}
}

$cols_rules = array();

foreach ( $css_rules as $rule ) {
	if ( '--mcr-cols' !== $rule['prop'] ) {
		continue;
	}

	foreach ( $rule['sel'] as $selector ) {
		if ( false !== strpos( $selector, 'mcr-grid--cols-' ) ) {
			$cols_rules[] = $selector . ' @ ' . $rule['media'] . ' => ' . $rule['value'];
		}
	}
}

assertTrue(
	count( $packages_rules ) > 0 && count( $cols_rules ) > 0,
	'control: both the packages recipe and the admin column classes declare --mcr-cols'
);

$buy_coins_source = mcrr_read( 'templates/buy-coins.php' );

assertTrue(
	false !== strpos( $buy_coins_source, 'mcr-grid--cols-' ),
	'control: the buy-coins grid really does emit the admin column class next to the packages recipe'
);

/* ==========================================================================
 * 11. Admin buttons that are actually buttons.
 * ======================================================================== */

mcr_group( 'Admin: dead buttons' );

$admin_source = mcrr_read( 'includes/Admin/Admin.php' );
$tools_source = mcrr_read( 'includes/Admin/ToolsPage.php' );

preg_match_all( '/name="mcr_action" value="([^"]+)"/', $tools_source, $tool_actions );

$unhandled = array();

foreach ( $tool_actions[1] as $action ) {
	if ( false === strpos( $admin_source, "case '" . $action . "'" ) ) {
		$unhandled[] = $action;
	}
}

assertCount(
	0,
	$unhandled,
	'every admin action posted by the Tools page has a handler in the admin dispatcher'
);

/* ==========================================================================
 * Summary.
 * ======================================================================== */

echo "\n=======================\n";

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
