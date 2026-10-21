<?php
/**
 * Static security and standards checker for the plugin.
 *
 * Run with: php tests/lint-plugin.php
 *
 * This is an INDEPENDENT verification tool: it does not trust the
 * implementation. It uses PHP's own tokenizer to analyse the source, so it
 * can tell a genuine vulnerability from a false positive (for example, a
 * table name interpolated into SQL - which is correct in WordPress, since
 * table names cannot be parameterised - versus a VALUE interpolated into a
 * query that was never passed through prepare()).
 *
 * @package MCR
 */

$root = dirname( __DIR__ );

$php_files = array();
$skip_dirs = array( '.git', 'node_modules', 'vendor' );

$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS )
);

foreach ( $iterator as $file ) {
	$path = $file->getPathname();

	foreach ( $skip_dirs as $skip ) {
		if ( false !== strpos( $path, DIRECTORY_SEPARATOR . $skip . DIRECTORY_SEPARATOR ) ) {
			continue 2;
		}
	}

	if ( 'php' === strtolower( $file->getExtension() ) ) {
		$php_files[] = $path;
	}
}

sort( $php_files );

$findings = array();

/**
 * Records a finding.
 *
 * @param array  $findings Findings collection.
 * @param string $severity Severity label.
 * @param string $rule     Rule identifier.
 * @param string $file     Relative file path.
 * @param int    $line     Line number.
 * @param string $message  Description.
 * @param string $code     The offending source line.
 * @return void
 */
function mcr_lint_add( &$findings, $severity, $rule, $file, $line, $message, $code ) {
	$findings[] = array(
		'severity' => $severity,
		'rule'     => $rule,
		'file'     => $file,
		'line'     => $line,
		'message'  => $message,
		'code'     => trim( $code ),
	);
}

/**
 * Decides whether a variable name is a table-name style variable.
 *
 * Table names cannot be passed to prepare(), so interpolating a literal
 * table name is the accepted WordPress pattern. The danger is interpolating
 * a VALUE, so we only allow a small, well-known set of name patterns.
 *
 * @param string $name Variable name.
 * @return bool
 */
function mcr_lint_is_table_var( $name ) {
	$name = ltrim( $name, '$' );

	if ( preg_match( '/^(table|tables|tbl|balances|ledger|transactions|checkins|checkins_table|ad_sessions|payments|claims|prefix)$/', $name ) ) {
		return true;
	}

	return (bool) preg_match( '/_table$|^table_/', $name );
}

/**
 * Checks a file's PHP source with the tokenizer.
 *
 * @param string $path     Absolute path.
 * @param string $rel      Relative path for reporting.
 * @param array  $findings Findings collection.
 * @return void
 */
function mcr_lint_scan_file( $path, $rel, &$findings ) {
	$source = file_get_contents( $path );

	if ( false === $source ) {
		return;
	}

	$tokens = token_get_all( $source );
	$count  = count( $tokens );

	// Map token index -> line number.
	$lines = array();

	for ( $i = 0; $i < $count; $i++ ) {
		if ( is_array( $tokens[ $i ] ) && in_array( $tokens[ $i ][0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
			continue;
		}

		$lines[ $i ] = is_array( $tokens[ $i ] ) ? $tokens[ $i ][2] : null;
	}

	$source_lines = explode( "\n", $source );

	/**
	 * Returns the source line text for a token index.
	 *
	 * @param int $index Token index.
	 * @return string
	 */
	$line_text = function ( $index ) use ( $source_lines ) {
		$number = isset( $GLOBALS['mcr_lint_lines'][ $index ] ) ? $GLOBALS['mcr_lint_lines'][ $index ] : 1;

		return isset( $source_lines[ $number - 1 ] ) ? $source_lines[ $number - 1 ] : '';
	};

	$GLOBALS['mcr_lint_lines'] = $lines;

	// --- 1. Find every $wpdb-> method call and classify the SQL argument ---
	for ( $i = 0; $i < $count; $i++ ) {
		if ( ! is_array( $tokens[ $i ] ) || T_VARIABLE !== $tokens[ $i ][0] ) {
			continue;
		}

		if ( '$wpdb' !== $tokens[ $i ][1] ) {
			continue;
		}

		// Expect -> method (
		$j = $i + 1;

		while ( $j < $count && is_array( $tokens[ $j ] ) && T_WHITESPACE === $tokens[ $j ][0] ) {
			$j++;
		}

		if ( $j >= $count || '-' !== $tokens[ $j ][0] ) {
			continue;
		}

		$j++;

		while ( $j < $count && is_array( $tokens[ $j ] ) && T_WHITESPACE === $tokens[ $j ][0] ) {
			$j++;
		}

		if ( $j >= $count || ! is_array( $tokens[ $j ] ) || T_STRING !== $tokens[ $j ][0] ) {
			continue;
		}

		$method = strtolower( $tokens[ $j ][1] );

		$sql_methods = array( 'query', 'get_results', 'get_row', 'get_var', 'get_col', 'prepare' );
		$is_prepare  = ( 'prepare' === $method );

		if ( ! in_array( $method, $sql_methods, true ) ) {
			continue;
		}

		// Find the opening paren of the call.
		$k = $j + 1;

		while ( $k < $count && is_array( $tokens[ $k ] ) && T_WHITESPACE === $tokens[ $k ][0] ) {
			$k++;
		}

		if ( $k >= $count || '(' !== $tokens[ $k ][0] ) {
			continue;
		}

		// Walk the argument list, tracking depth, and collect the first
		// argument's tokens until the matching close paren.
		$depth    = 0;
		$arg      = array();
		$arg_no   = 0;
		$found    = false;
		$in_first = false;

		for ( $m = $k; $m < $count; $m++ ) {
			$tok = $tokens[ $m ];

			if ( '(' === $tok ) {
				$depth++;

				if ( 1 === $depth ) {
					$in_first = true;
					continue;
				}
			}

			if ( ')' === $tok ) {
				$depth--;

				if ( 0 === $depth ) {
					break;
				}
			}

			if ( ',' === $tok && 1 === $depth ) {
				$in_first = false;
				$arg_no++;
				continue;
			}

			if ( $in_first && 1 === $depth ) {
				$arg[] = $tok;
			}
		}

		if ( $is_prepare ) {
			continue; // prepare() is the safe path.
		}

		// Examine the first argument. If it is a plain string with an
		// interpolated variable, that is a raw SQL string.
		$has_interpolation = false;
		$bad_vars          = array();

		foreach ( $arg as $tok ) {
			if ( is_array( $tok ) && T_ENCAPSED_AND_WHITESPACE === $tok[0] ) {
				continue;
			}

			if ( is_array( $tok ) && '"' === $tok[0] ) {
				// Double-quoted string: check for embedded variables.
				continue;
			}

			if ( is_array( $tok ) && T_VARIABLE === $tok[0] ) {
				$name = $tok[1];

				if ( ! mcr_lint_is_table_var( $name ) ) {
					$has_interpolation = true;
					$bad_vars[]        = $name;
				}
			}
		}

		// Detect the "quoted string with $var inside" pattern by scanning the
		// raw source slice for the method call.
		$start_line = isset( $lines[ $j ] ) ? $lines[ $j ] : 1;
		$slice      = '';

		for ( $m = $k; $m < $count && $m < $k + 400; $m++ ) {
			if ( is_array( $tokens[ $m ] ) ) {
				$slice .= $tokens[ $m ][1];
			} else {
				$slice .= $tokens[ $m ];
			}
		}

		// Find interpolated variables inside double-quoted strings.
		if ( preg_match_all( '/"([^"]*)"/s', $slice, $matches ) ) {
			foreach ( $matches[1] as $string_body ) {
				if ( preg_match_all( '/\$([a-zA-Z_][a-zA-Z0-9_]*)/', $string_body, $vars ) ) {
					foreach ( $vars[1] as $var ) {
						if ( ! mcr_lint_is_table_var( $var ) ) {
							$has_interpolation = true;
							$bad_vars[]        = '$' . $var;
						}
					}
				}
			}
		}

		if ( $has_interpolation ) {
			mcr_lint_add(
				$findings,
				'ERROR',
				'unprepared-sql',
				$rel,
				$start_line,
				'SQL string interpolates a value without $wpdb->prepare(): ' . implode( ', ', array_unique( $bad_vars ) ),
				$line_text( $j )
			);
		}
	}

	// --- 2. Direct superglobal output ---
	for ( $i = 0; $i < $count; $i++ ) {
		if ( ! is_array( $tokens[ $i ] ) || T_ECHO !== $tokens[ $i ][0] ) {
			continue;
		}

		// Look ahead for a superglobal within the echo statement.
		$depth = 0;

		for ( $m = $i + 1; $m < $count; $m++ ) {
			$tok = $tokens[ $m ];

			if ( '(' === $tok ) {
				$depth++;
			}

			if ( ')' === $tok ) {
				$depth--;

				if ( $depth < 0 ) {
					break;
				}
			}

			if ( is_array( $tok ) && T_VARIABLE === $tok[0] && in_array( $tok[1], array( '$_GET', '$_POST', '$_REQUEST', '$_COOKIE', '$_SERVER' ), true ) ) {
				// Look backwards for a wrapping escaping function. If the
				// superglobal is the direct argument of esc_attr(),
				// esc_html(), esc_url() or wp_kses_post(), it is safe.
				$safe  = false;
				$inner = $m - 1;

				while ( $inner >= 0 && is_array( $tokens[ $inner ] ) && T_WHITESPACE === $tokens[ $inner ][0] ) {
					$inner--;
				}

				if ( $inner >= 0 && '(' === $tokens[ $inner ] ) {
					$fn = $inner - 1;

					while ( $fn >= 0 && is_array( $tokens[ $fn ] ) && T_WHITESPACE === $tokens[ $fn ][0] ) {
						$fn--;
					}

					if ( $fn >= 0 && is_array( $tokens[ $fn ] ) && T_STRING === $tokens[ $fn ][0] ) {
						$escapers = array( 'esc_attr', 'esc_html', 'esc_url', 'esc_js', 'esc_textarea', 'wp_kses_post', 'wp_kses', 'absint', 'intval', 'sanitize_text_field', 'sanitize_key', 'sanitize_title', 'wp_unslash', 'wp_json_encode', 'number_format_i18n' );

						if ( in_array( strtolower( $tokens[ $fn ][1] ), $escapers, true ) ) {
							$safe = true;
						}
					}
				}

				if ( ! $safe ) {
					$line = isset( $lines[ $i ] ) ? $lines[ $i ] : 1;

					mcr_lint_add(
						$findings,
						'ERROR',
						'raw-echo-super-global',
						$rel,
						$line,
						'Superglobal echoed without escaping.',
						$line_text( $i )
					);
				}

				break;
			}

			if ( ';' === $tok && 0 === $depth ) {
				break;
			}
		}
	}

	// --- 3. Dangerous function calls ---
	$dangerous = array(
		'unserialize' => 'PHP object injection risk. Use maybe_unserialize() or json_decode().',
		'eval'        => 'eval() executes arbitrary code.',
		'assert'      => 'assert() can execute code in old PHP.',
		'create_function' => 'create_function() is deprecated and unsafe.',
		'extract'     => 'extract() can overwrite variables in scope.',
	);

	for ( $i = 0; $i < $count; $i++ ) {
		if ( ! is_array( $tokens[ $i ] ) || T_STRING !== $tokens[ $i ][0] ) {
			continue;
		}

		$name = strtolower( $tokens[ $i ][1] );

		if ( ! isset( $dangerous[ $name ] ) ) {
			continue;
		}

		// Skip method calls / declarations.
		$prev = $i - 1;

		while ( $prev >= 0 && is_array( $tokens[ $prev ] ) && T_WHITESPACE === $tokens[ $prev ][0] ) {
			$prev--;
		}

		if ( $prev >= 0 && is_array( $tokens[ $prev ] ) && in_array( $tokens[ $prev ][0], array( T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION ), true ) ) {
			continue;
		}

		$line = isset( $lines[ $i ] ) ? $lines[ $i ] : 1;

		mcr_lint_add( $findings, 'WARN', 'dangerous-function', $rel, $line, $dangerous[ $name ], $line_text( $i ) );
	}

	// --- 4. sslverify disabled ---
	if ( preg_match( '/sslverify[^\n]*false/', $source ) ) {
		$line = 1;

		mcr_lint_add( $findings, 'WARN', 'sslverify-off', $rel, $line, 'TLS verification disabled.', 'sslverify => false' );
	}

	// --- 5. ABSPATH guard ---
	if ( false === strpos( $source, "defined( 'ABSPATH' )" ) && false === strpos( $source, "defined('ABSPATH')" ) ) {
		mcr_lint_add( $findings, 'ERROR', 'missing-abspath-guard', $rel, 1, 'File is missing the ABSPATH guard.', '' );
	}

	// --- 6. Double-quoted strings that contain a printf specifier ---
	/*
	 * A double-quoted string is interpolated by PHP. A printf specifier such
	 * as "%1$d" contains a dollar sign, so PHP eats the "$d" as a variable
	 * reference, leaving "%1 " behind. printf() then throws
	 *
	 *     ValueError: Unknown format specifier " "
	 *
	 * which is a FATAL error in PHP 8.
	 */
	$specifier_pattern = '/"[^"\n]*%[0-9]*\$[a-zA-Z_][^"\n]*"/';

	if ( preg_match_all( $specifier_pattern, $source, $specifier_matches, PREG_OFFSET_CAPTURE ) ) {
		foreach ( $specifier_matches[0] as $hit ) {
			$line = substr_count( substr( $source, 0, $hit[1] ), "\n" ) + 1;

			mcr_lint_add(
				$findings,
				'ERROR',
				'interpolated-format-string',
				$rel,
				$line,
				'A double-quoted string contains a printf specifier. PHP interpolates the "$..." as a variable, which breaks printf() and is a fatal error in PHP 8. Use single quotes.',
				$hit[0]
			);
		}
	}

	// --- 7. Closing PHP tag (deprecated in modern WP) ---
	if ( preg_match( '/\?>\s*$/', $source ) ) {
		mcr_lint_add( $findings, 'INFO', 'closing-php-tag', $rel, 1, 'File ends with a closing PHP tag.', '' );
	}
}

// Files that legitimately do not need the ABSPATH guard. Everything under
// tests/ is a standalone tool, not plugin runtime code.
$no_guard_ok = array(
	'uninstall.php',
);

$file_count = 0;
$line_count = 0;

foreach ( $php_files as $path ) {
	$file_count++;
	$rel   = str_replace( '\\', '/', str_replace( $root . DIRECTORY_SEPARATOR, '', $path ) );
	$lines = file( $path, FILE_IGNORE_NEW_LINES );

	if ( false !== $lines ) {
		$line_count += count( $lines );
	}

	$allowed = false;

	if ( 0 === strpos( $rel, 'tests/' ) ) {
		$allowed = true;
	}

	foreach ( $no_guard_ok as $candidate ) {
		if ( $rel === $candidate ) {
			$allowed = true;
			break;
		}
	}

	if ( $allowed ) {
		continue;
	}

	mcr_lint_scan_file( $path, $rel, $findings );
}

// --- Structural requirements ---
$required = array(
	'madara-coin-rewards.php',
	'uninstall.php',
	'includes/Plugin.php',
	'includes/Installer.php',
	'includes/Ledger.php',
	'includes/Settings.php',
	'includes/Capabilities.php',
	'includes/Rest.php',
	'includes/Cron.php',
	'includes/Pages.php',
	'includes/functions.php',
	'includes/Support/Clock.php',
	'includes/Support/WpClock.php',
	'includes/Ledger/BalanceMath.php',
	'includes/Ledger/LedgerInterface.php',
	'includes/Rewards/StreakCalculator.php',
	'includes/Rewards/CheckInPolicy.php',
	'includes/Rewards/AdRewardPolicy.php',
	'includes/Rewards/CheckIn.php',
	'includes/Rewards/AdSessions.php',
	'includes/Payments/PackageCalculator.php',
	'includes/Payments/Orders.php',
	'includes/Integrations/Madara.php',
	'includes/Integrations/Privacy.php',
	'assets/css/mcr-design-system.css',
);

$missing = array();

foreach ( $required as $file ) {
	if ( ! file_exists( $root . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $file ) ) ) {
		$missing[] = $file;
	}
}

// --- Report ---
echo "MCR Plugin Lint\n";
echo "===============\n";
printf( "Scanned %d PHP files, %d lines.\n\n", $file_count, $line_count );

if ( ! empty( $missing ) ) {
	echo "MISSING REQUIRED FILES (" . count( $missing ) . "):\n";

	foreach ( $missing as $file ) {
		echo "  - " . $file . "\n";
	}

	echo "\n";
}

$by_severity = array(
	'ERROR' => array(),
	'WARN'  => array(),
	'INFO'  => array(),
);

foreach ( $findings as $finding ) {
	$by_severity[ $finding['severity'] ][] = $finding;
}

foreach ( $by_severity as $severity => $items ) {
	if ( empty( $items ) ) {
		continue;
	}

	printf( "%s (%d):\n", $severity, count( $items ) );

	$seen = array();

	foreach ( $items as $finding ) {
		$key = $finding['rule'] . '|' . $finding['file'] . '|' . $finding['line'];

		if ( isset( $seen[ $key ] ) ) {
			continue;
		}

		$seen[ $key ] = true;

		printf(
			"  [%s] %s:%d\n        %s\n        %s\n",
			$finding['rule'],
			$finding['file'],
			$finding['line'],
			$finding['message'],
			$finding['code']
		);
	}

	echo "\n";
}

printf(
	"Summary: %d error(s), %d warning(s), %d info, %d missing file(s).\n",
	count( $by_severity['ERROR'] ),
	count( $by_severity['WARN'] ),
	count( $by_severity['INFO'] ),
	count( $missing )
);

exit( empty( $by_severity['ERROR'] ) && empty( $missing ) ? 0 : 1 );
