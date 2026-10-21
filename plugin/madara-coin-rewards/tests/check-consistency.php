<?php
/**
 * Cross-module consistency checker.
 *
 * Run with: php tests/check-consistency.php
 *
 * The plugin was assembled from several independently written modules. This
 * tool verifies that they actually fit together: that every referenced class
 * exists, that method calls match their declarations, that every registered
 * hook points at a real callable, and that the database schema agrees with
 * the SQL in the code.
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

$issues = array();

/**
 * Records an issue.
 *
 * @param array  $issues  Issues collection.
 * @param string $severity Severity.
 * @param string $kind     Issue kind.
 * @param string $file     File.
 * @param int    $line     Line.
 * @param string $message  Message.
 * @return void
 */
function mcr_cc_add( &$issues, $severity, $kind, $file, $line, $message ) {
	$issues[] = array(
		'severity' => $severity,
		'kind'     => $kind,
		'file'     => $file,
		'line'     => $line,
		'message'  => $message,
	);
}

/* -------------------------------------------------------------------------
 * 0. Helpers
 * ---------------------------------------------------------------------- */

/**
 * Resolves a parent class reference to a fully qualified name.
 *
 * PHP resolves an unqualified parent name against the CURRENT namespace
 * first and only then the global one, so `extends Foo` in `MCR\Admin`
 * means `MCR\Admin\Foo` when that class exists, and the global `Foo`
 * otherwise.
 *
 * @param string $parent    Raw parent reference.
 * @param string $namespace The child's namespace.
 * @param array  $aliases   Import aliases in the child's file.
 * @param array  $classes   The class map, for the same-namespace check.
 * @return string
 */
function mcr_cc_resolve_parent( $parent, $namespace, $aliases, $classes = array() ) {
	if ( '' === $parent ) {
		return '';
	}

	// A leading backslash means fully qualified: \WP_List_Table.
	if ( 0 === strpos( $parent, '\\' ) ) {
		return ltrim( $parent, '\\' );
	}

	if ( isset( $aliases[ $parent ] ) ) {
		return $aliases[ $parent ];
	}

	if ( false !== strpos( $parent, '\\' ) ) {
		if ( 0 === strpos( $parent, 'MCR\\' ) ) {
			return $parent;
		}

		return ( '' !== $namespace ? $namespace . '\\' : '' ) . $parent;
	}

	// Unqualified: the current namespace wins if such a class exists.
	if ( '' !== $namespace ) {
		$candidate = $namespace . '\\' . $parent;

		if ( isset( $classes[ $candidate ] ) ) {
			return $candidate;
		}
	}

	// Otherwise it is a global class.
	return $parent;
}

/**
 * Whether a class (or one of its ancestors) declares a method.
 *
 * @param string $fqcn   Fully qualified class name.
 * @param string $method Lowercase method name.
 * @param array  $classes The class map.
 * @param int    $depth  Recursion guard.
 * @return bool
 */
function mcr_cc_has_method( $fqcn, $method, $classes, $depth = 0 ) {
	if ( $depth > 8 || ! isset( $classes[ $fqcn ] ) ) {
		return false;
	}

	if ( isset( $classes[ $fqcn ]['methods'][ $method ] ) ) {
		return true;
	}

	$parent = isset( $classes[ $fqcn ]['parent'] ) ? $classes[ $fqcn ]['parent'] : '';

	if ( '' === $parent ) {
		return false;
	}

	return mcr_cc_has_method( $parent, $method, $classes, $depth + 1 );
}

/**
 * Blanks out the string literals and the comments in a source file.
 *
 * The character offsets are preserved, so a line number computed from the
 * result is still the line number of the original source. A class
 * reference inside a string is text, not a call.
 *
 * @param string $source Source code.
 * @return string
 */
function mcr_cc_strip_literals( $source ) {
	$out = '';
	$len = strlen( $source );
	$i   = 0;

	while ( $i < $len ) {
		$ch = $source[ $i ];

		// A line comment.
		if ( '/' === $ch && $i + 1 < $len && '/' === $source[ $i + 1 ] ) {
			while ( $i < $len && "\n" !== $source[ $i ] ) {
				$out .= ' ';
				++$i;
			}

			continue;
		}

		// A block comment.
		if ( '/' === $ch && $i + 1 < $len && '*' === $source[ $i + 1 ] ) {
			while ( $i < $len ) {
				if ( '*' === $source[ $i ] && $i + 1 < $len && '/' === $source[ $i + 1 ] ) {
					$out .= '  ';
					$i += 2;
					break;
				}

				$out .= ( "\n" === $source[ $i ] ) ? "\n" : ' ';
				++$i;
			}

			continue;
		}

		// A string literal.
		if ( "'" === $ch || '"' === $ch ) {
			$quote = $ch;
			$out  .= ' ';
			++$i;

			while ( $i < $len ) {
				if ( '\\' === $source[ $i ] ) {
					$out .= ( "\n" === $source[ $i ] ) ? "\n" : ' ';
					++$i;

					if ( $i < $len ) {
						$out .= ( "\n" === $source[ $i ] ) ? "\n" : ' ';
						++$i;
					}

					continue;
				}

				if ( $source[ $i ] === $quote ) {
					$out .= ' ';
					++$i;
					break;
				}

				$out .= ( "\n" === $source[ $i ] ) ? "\n" : ' ';
				++$i;
			}

			continue;
		}

		$out .= $ch;
		++$i;
	}

	return $out;
}

/* -------------------------------------------------------------------------
 * 1. Collect every declared class, its methods and its parent.
 * ---------------------------------------------------------------------- */
$classes = array();   // FQCN => array( 'file' => ..., 'methods' => array() ).
$uses    = array();   // FQCN => file that declares it.

foreach ( $php_files as $path ) {
	$source = file_get_contents( $path );

	if ( false === $source ) {
		continue;
	}

	$rel     = str_replace( '\\', '/', str_replace( '\\', '/', str_replace( $root . DIRECTORY_SEPARATOR, '', $path ) ) );
	$tokens  = token_get_all( $source );
	$count   = count( $tokens );

	$namespace = '';
	$in_use    = false;
	$use_alias = array();

	for ( $i = 0; $i < $count; $i++ ) {
		// A `use` statement ends at a semicolon, which is a raw (non-array)
		// token, so the block must be closed before the is_array() guard.
		if ( $in_use ) {
			if ( ';' === $tokens[ $i ] ) {
				$in_use = false;
			}

			continue;
		}

		$tok = $tokens[ $i ];

		if ( ! is_array( $tok ) ) {
			continue;
		}

		if ( T_NAMESPACE === $tok[0] ) {
			$namespace = '';

			for ( $k = $i + 1; $k < $count; $k++ ) {
				if ( is_array( $tokens[ $k ] ) && in_array( $tokens[ $k ][0], array( T_STRING, T_NAME_QUALIFIED, T_NS_SEPARATOR ), true ) ) {
					$namespace .= $tokens[ $k ][1];
				} elseif ( is_array( $tokens[ $k ] ) && T_WHITESPACE === $tokens[ $k ][0] ) {
					continue;
				} else {
					break;
				}
			}

			$namespace = trim( $namespace, '\\' );
			continue;
		}

		if ( T_USE === $tok[0] ) {
			$in_use    = true;
			$use_alias = array();
			continue;
		}

		if ( T_CLASS === $tok[0] || T_INTERFACE === $tok[0] || T_TRAIT === $tok[0] ) {
			// Skip ::class and anonymous classes.
			$prev = $i - 1;

			while ( $prev >= 0 && is_array( $tokens[ $prev ] ) && T_WHITESPACE === $tokens[ $prev ][0] ) {
				$prev--;
			}

			if ( $prev >= 0 && is_array( $tokens[ $prev ] ) && T_DOUBLE_COLON === $tokens[ $prev ][0] ) {
				continue;
			}

			$name = '';

			for ( $k = $i + 1; $k < $count; $k++ ) {
				if ( is_array( $tokens[ $k ] ) && T_WHITESPACE === $tokens[ $k ][0] ) {
					continue;
				}

				if ( is_array( $tokens[ $k ] ) && T_STRING === $tokens[ $k ][0] ) {
					$name = $tokens[ $k ][1];
				}

				break;
			}

			if ( '' === $name ) {
				continue;
			}

			$fqcn = ( '' !== $namespace ) ? $namespace . '\\' . $name : $name;

			// The parent class, for method resolution.
			$parent = '';

			for ( $k = $i + 1; $k < $count; $k++ ) {
				$scan = $tokens[ $k ];

				if ( ! is_array( $scan ) ) {
					continue;
				}

				if ( T_WHITESPACE === $scan[0] || T_COMMENT === $scan[0] || T_DOC_COMMENT === $scan[0] ) {
					continue;
				}

				if ( T_EXTENDS === $scan[0] ) {
					for ( $m = $k + 1; $m < $count; $m++ ) {
						$name_token = $tokens[ $m ];

						if ( is_array( $name_token ) && T_STRING === $name_token[0] ) {
							$parent = $name_token[1];
						} elseif ( is_array( $name_token ) && T_NAME_QUALIFIED === $name_token[0] ) {
							$parent = $name_token[1];
						} elseif ( is_array( $name_token ) && T_NAME_FULLY_QUALIFIED === $name_token[0] ) {
							// A leading backslash: \WP_List_Table. The
							// backslash is kept so the resolver knows it
							// is fully qualified.
							$parent = $name_token[1];
						}

						if ( '' !== $parent ) {
							break;
						}
					}
				}

				if ( T_CLASS === $scan[0] || T_FUNCTION === $scan[0] || T_ABSTRACT === $scan[0] || T_FINAL === $scan[0] ) {
					break;
				}
			}

			// Collect method names declared in this class body.
			$methods = array();
			$depth   = 0;
			$started = false;
			$end_line = $tok[2];

			for ( $k = $i + 1; $k < $count; $k++ ) {
				$t = $tokens[ $k ];

				// String interpolation such as "{$table}" tokenises as
				// T_CURLY_OPEN followed by a raw '}'. That opening brace
				// must count, and the raw '}' that closes it decrements in
				// the normal way, so the depth stays balanced.
				if ( is_array( $t ) && in_array( $t[0], array( T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES ), true ) ) {
					$depth++;
					continue;
				}

				if ( '{' === $t ) {
					$depth++;
					$started = true;
					continue;
				}

				if ( '}' === $t ) {
					$depth--;

					if ( $started && $depth <= 0 ) {
						$end_line = is_array( $tokens[ $k ] ) ? $tokens[ $k ][2] : $end_line;
						break;
					}

					continue;
				}

				if ( $started && is_array( $t ) && T_FUNCTION === $t[0] ) {
					for ( $m = $k + 1; $m < $count; $m++ ) {
						if ( is_array( $tokens[ $m ] ) && T_WHITESPACE === $tokens[ $m ][0] ) {
							continue;
						}

						if ( is_array( $tokens[ $m ] ) && T_STRING === $tokens[ $m ][0] ) {
							$methods[ strtolower( $tokens[ $m ][1] ) ] = true;
						}

						break;
					}
				}
			}

			if ( ! isset( $classes[ $fqcn ] ) ) {
				$classes[ $fqcn ] = array(
					'file'    => $rel,
					'methods' => $methods,
					'line'    => $tok[2],
					'parent'  => $parent,
					'start'   => $tok[2],
					'end'     => $end_line,
				);
			}
		}
	}
}

/* -------------------------------------------------------------------------
 * 1b. Resolve the parent of every class to a fully qualified name.
 * ---------------------------------------------------------------------- */

foreach ( $classes as $fqcn => $info ) {
	if ( '' === $info['parent'] ) {
		continue;
	}

	// The aliases and namespace of the file that declares the class.
	$file_source  = (string) file_get_contents( $root . DIRECTORY_SEPARATOR . $info['file'] );
	$file_aliases = array();

	if ( preg_match_all( '/^\s*use\s+([^;]+);/m', $file_source, $file_use_matches ) ) {
		foreach ( $file_use_matches[1] as $import ) {
			$import = trim( $import );

			if ( false !== stripos( $import, ' as ' ) ) {
				list( $fq, $alias ) = preg_split( '/\s+as\s+/i', $import );
				$file_aliases[ trim( $alias ) ] = trim( $fq );
			} else {
				$parts = explode( '\\', $import );
				$file_aliases[ end( $parts ) ] = $import;
			}
		}
	}

	$namespace = '';

	if ( preg_match( '/^\s*namespace\s+([^;{]+)[;{]/m', $file_source, $ns_match ) ) {
		$namespace = trim( $ns_match[1] );
	}

	$classes[ $fqcn ]['parent'] = mcr_cc_resolve_parent( $info['parent'], $namespace, $file_aliases, $classes );
}

/* -------------------------------------------------------------------------
 * 2. Verify every MCR\ class resolves to a file the autoloader can find.
 * ---------------------------------------------------------------------- */

$autoloadable = array(
	'MCR\Plugin',
	'MCR\Installer',
	'MCR\Ledger',
	'MCR\Settings',
	'MCR\Capabilities',
	'MCR\Container',
	'MCR\Cron',
	'MCR\Pages',
	'MCR\Rest',
	'MCR\Support\Clock',
	'MCR\Support\WpClock',
	'MCR\Support\FixedClock',
	'MCR\Ledger\BalanceMath',
	'MCR\Ledger\LedgerInterface',
	'MCR\Rewards\StreakCalculator',
	'MCR\Rewards\CheckInPolicy',
	'MCR\Rewards\AdRewardPolicy',
	'MCR\Rewards\CheckIn',
	'MCR\Rewards\AdSessions',
	'MCR\Payments\PackageCalculator',
	'MCR\Payments\Packages',
	'MCR\Payments\Orders',
	'MCR\Payments\PaymentResult',
	'MCR\Gateways\Gateway',
	'MCR\Gateways\Registry',
	'MCR\Gateways\Stripe',
	'MCR\Gateways\Manual',
	'MCR\Gateways\WooCommerce',
	'MCR\AdProviders\Provider',
	'MCR\AdProviders\Registry',
	'MCR\AdProviders\GenericS2S',
	'MCR\Templates\Templates',
	'MCR\Templates\Shortcodes',
	'MCR\Templates\Blocks',
	'MCR\Templates\Assets',
	'MCR\Admin\Admin',
	'MCR\Admin\SettingsPage',
	'MCR\Admin\PackagesPage',
	'MCR\Admin\UsersPage',
	'MCR\Admin\TransactionsPage',
	'MCR\Admin\DashboardPage',
	'MCR\Admin\LogsPage',
	'MCR\Admin\ToolsPage',
	'MCR\Integrations\Madara',
	'MCR\Integrations\Profile',
	'MCR\Integrations\Privacy',
);

foreach ( $autoloadable as $fqcn ) {
	if ( ! isset( $classes[ $fqcn ] ) ) {
		mcr_cc_add( $issues, 'ERROR', 'missing-class', '-', 0, 'Class ' . $fqcn . ' is not declared anywhere.' );
		continue;
	}

	// The autoloader maps MCR\Foo\Bar -> includes/Foo/Bar.php.
	$relative = str_replace( '\\', '/', substr( $fqcn, 4 ) );
	$expected = 'includes/' . $relative . '.php';

	if ( $classes[ $fqcn ]['file'] !== $expected ) {
		mcr_cc_add(
			$issues,
			'ERROR',
			'autoload-mismatch',
			$classes[ $fqcn ]['file'],
			$classes[ $fqcn ]['line'],
			'Class ' . $fqcn . ' lives in ' . $classes[ $fqcn ]['file'] . ' but the autoloader expects ' . $expected
		);
	}
}

/* -------------------------------------------------------------------------
 * 3. Verify static calls to MCR classes exist.
 * ---------------------------------------------------------------------- */

foreach ( $php_files as $path ) {
	$source = file_get_contents( $path );

	if ( false === $source ) {
		continue;
	}

	$rel = str_replace( '\\', '/', str_replace( $root . DIRECTORY_SEPARATOR, '', $path ) );

	// Namespace + import aliases for this file.
	$namespace = '';
	$aliases   = array();

	if ( preg_match( '/^\s*namespace\s+([^;]+);/m', $source, $ns_match ) ) {
		$namespace = trim( $ns_match[1] );
	}

	if ( preg_match_all( '/^\s*use\s+([^;]+);/m', $source, $use_matches ) ) {
		foreach ( $use_matches[1] as $import ) {
			$import = trim( $import );

			if ( false !== stripos( $import, ' as ' ) ) {
				list( $fq, $alias ) = preg_split( '/\s+as\s+/i', $import );
				$aliases[ trim( $alias ) ] = trim( $fq );
			} else {
				$parts = explode( '\\', $import );
				$aliases[ end( $parts ) ] = $import;
			}
		}
	}

	// Strip comments so a docblock mention is not reported as a call.
	// Whitespace is preserved so byte offsets stay aligned with $source.
	$code = '';

	foreach ( token_get_all( $source ) as $token ) {
		if ( is_string( $token ) ) {
			$code .= $token;
			continue;
		}

		if ( T_COMMENT === $token[0] || T_DOC_COMMENT === $token[0] ) {
			$code .= str_repeat( ' ', strlen( $token[1] ) );
			continue;
		}

		$code .= $token[1];
	}

	/*
	 * Only real code is scanned: a class reference inside a string
	 * literal is text, not a call. The stripping happens AFTER the
	 * tokenizer has built the code, because that is what fills it.
	 */
	$code = mcr_cc_strip_literals( $code );

	/*
	 * Static calls. The class reference may be a single segment
	 * (`Shortcodes::register()`), a relative multi-segment path
	 * (`Admin\Admin::register()`) or a fully qualified one.
	 *
	 * The relative multi-segment form is the one that used to slip
	 * through: only the FIRST segment was resolved, so `Admin\Admin::` was
	 * checked against `MCR\Admin` (a namespace, not a class) and skipped -
	 * even though `MCR\Admin\Admin` had no such method.
	 */
	$call_pattern = '/(?<![\\\\$a-zA-Z0-9_>])((?:[A-Z][A-Za-z0-9_]*\\\\)*[A-Z][A-Za-z0-9_]*)::([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/';

	if ( preg_match_all( $call_pattern, $code, $calls, PREG_SET_ORDER ) ) {
		foreach ( $calls as $call ) {
			$reference = $call[1];
			$method    = strtolower( $call[2] );
			$segments  = explode( '\\', $reference );
			$first     = $segments[0];

			// An imported alias wins.
			if ( count( $segments ) === 1 && isset( $aliases[ $first ] ) ) {
				$fqcn = $aliases[ $first ];
			} elseif ( count( $segments ) === 1 ) {
				// A single segment is either the current namespace's class or
				// a global class; both are handled elsewhere.
				continue;
			} else {
				// Relative to the current namespace.
				$fqcn = ( '' !== $namespace ? $namespace . '\\' : '' ) . $reference;
			}

			if ( 0 !== strpos( $fqcn, 'MCR\\' ) ) {
				continue;
			}

			if ( ! isset( $classes[ $fqcn ] ) ) {
				mcr_cc_add( $issues, 'ERROR', 'unknown-class', $rel, 0, 'Call to ' . $fqcn . '::' . $call[2] . '() but the class does not exist.' );
				continue;
			}

			if ( ! mcr_cc_has_method( $fqcn, $method, $classes ) ) {
				mcr_cc_add(
					$issues,
					'ERROR',
					'unknown-method',
					$rel,
					0,
					'Call to ' . $fqcn . '::' . $call[2] . '() but that method is not declared in ' . $classes[ $fqcn ]['file'] . ' or its ancestors.'
				);
			}
		}
	}

	/*
	 * Instance calls on a variable whose class is unambiguous:
	 *   $x = new Foo();   $x->bar();
	 *
	 * Only the `new` form is inferred. A factory such as
	 * `Container::instance()->clock()` returns a DIFFERENT type from the
	 * class that provides it, so inferring from the factory's class name
	 * would report false positives.
	 */
	$known = array();

	if ( preg_match_all( '/\$([a-zA-Z_][a-zA-Z0-9_]*)\s*=\s*new\s+([\\\\A-Za-z_][\\\\A-Za-z0-9_]*)\s*\(/', $code, $news, PREG_SET_ORDER ) ) {
		foreach ( $news as $hit ) {
			$var = $hit[1];

			// A variable reassigned to a different class is ambiguous, so
			// it is dropped rather than guessed at. array_key_exists is
			// used because isset() is false for a null flag.
			if ( array_key_exists( $var, $known ) && $known[ $var ] !== $hit[2] ) {
				$known[ $var ] = null;
				continue;
			}

			$known[ $var ] = $hit[2];
		}
	}

	foreach ( $known as $var => $class ) {
		if ( null === $class ) {
			unset( $known[ $var ] );
		}
	}

	if ( ! empty( $known ) && preg_match_all( '/\$([a-zA-Z_][a-zA-Z0-9_]*)->([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $code, $calls4, PREG_SET_ORDER ) ) {
		foreach ( $calls4 as $call ) {
			$var = $call[1];

			if ( ! isset( $known[ $var ] ) ) {
				continue;
			}

			$reference = $known[ $var ];
			$segments  = explode( '\\', $reference );
			$first     = $segments[0];

			if ( count( $segments ) === 1 && isset( $aliases[ $first ] ) ) {
				$fqcn = $aliases[ $first ];
			} elseif ( count( $segments ) === 1 ) {
				$fqcn = ( '' !== $namespace ? $namespace . '\\' : '' ) . $first;
			} else {
				$fqcn = ( '' !== $namespace ? $namespace . '\\' : '' ) . $reference;
			}

			if ( 0 !== strpos( $fqcn, 'MCR\\' ) || ! isset( $classes[ $fqcn ] ) ) {
				continue;
			}

			if ( ! mcr_cc_has_method( $fqcn, strtolower( $call[2] ), $classes ) ) {
				mcr_cc_add(
					$issues,
					'ERROR',
					'unknown-method',
					$rel,
					0,
					'Call to $' . $var . '->' . $call[2] . '() but ' . $fqcn . ' does not declare that method.'
				);
			}
		}
	}

	/*
	 * `$this->method()` inside a class must exist on that class. This is
	 * the other half of the "undefined method" family of fatals.
	 */
	if ( preg_match_all( '/\$this->([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $code, $self_calls, PREG_SET_ORDER ) ) {
		foreach ( $self_calls as $call ) {
			$line = substr_count( substr( $code, 0, strpos( $code, $call[0] ) ), "\n" ) + 1;

			// The owner is the class whose body contains the call, which is
			// not necessarily the first class in the file.
			$owner = '';

			foreach ( $classes as $fqcn => $info ) {
				if ( $info['file'] !== $rel ) {
					continue;
				}

				if ( $line >= $info['start'] && $line <= $info['end'] ) {
					$owner = $fqcn;
					break;
				}
			}

			if ( ! $owner ) {
				continue;
			}

			/*
			 * If the class extends something outside the plugin (a
			 * WordPress base class such as WP_List_Table), the method may
			 * legitimately come from there, so the check is skipped.
			 */
			$owner_parent = isset( $classes[ $owner ]['parent'] ) ? $classes[ $owner ]['parent'] : '';

			if ( '' !== $owner_parent && 0 !== strpos( $owner_parent, 'MCR\\' ) ) {
				continue;
			}

			if ( ! mcr_cc_has_method( $owner, strtolower( $call[1] ), $classes ) ) {
				mcr_cc_add(
					$issues,
					'ERROR',
					'unknown-method',
					$rel,
					$line,
					'Call to $this->' . $call[1] . '() but ' . $owner . ' does not declare that method.'
				);
			}
		}
	}

	// A class in a sub-namespace (MCR\Templates, MCR\Admin, ...) that calls
	// another MCR class without importing it resolves to the WRONG class at
	// runtime - a fatal error, not a warning.
	$current_ns = '';

	if ( preg_match( '/^\s*namespace\s+([^;{]+)[;{]/m', $source, $ns_match ) ) {
		$current_ns = trim( $ns_match[1] );
	}

	if ( '' !== $current_ns && 'MCR' !== $current_ns && preg_match_all( '/(?<![\\\\$a-zA-Z0-9_>])(([A-Z][A-Za-z0-9_]*))(?:::)([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $source, $calls2, PREG_SET_ORDER ) ) {
		foreach ( $calls2 as $call ) {
			$short = $call[1];

			// Skip anything already imported, and the current namespace.
			if ( isset( $aliases[ $short ] ) ) {
				continue;
			}

			$candidate = $current_ns . '\\' . $short;

			if ( ! isset( $classes[ $candidate ] ) ) {
				continue;
			}

			// The class lives in this namespace but was not referenced
			// through a leading backslash, so it would resolve to the
			// current namespace - which is actually correct here.
			continue;
		}
	}

	// The dangerous case is a MCR class in a DIFFERENT sub-namespace being
	// called unqualified. Detect that by looking for a call whose short name
	// matches a known MCR class in another namespace and which is not
	// imported.
	if ( '' !== $current_ns && preg_match_all( '/(?<![\\\\$a-zA-Z0-9_>\\w])([A-Z][A-Za-z0-9_]*)::([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $code, $calls3, PREG_SET_ORDER ) ) {
		foreach ( $calls3 as $call ) {
			$short = $call[1];
			$method = strtolower( $call[2] );

			if ( isset( $aliases[ $short ] ) ) {
				continue; // Imported: fine.
			}

			// Is there a class with this short name in another namespace?
			$matches = array();

			foreach ( $classes as $fqcn => $info ) {
				$parts = explode( '\\', $fqcn );

				if ( end( $parts ) === $short ) {
					$matches[ $fqcn ] = $info;
				}
			}

			if ( empty( $matches ) ) {
				continue;
			}

			// If the ONLY match is in the current namespace, the call is
			// correct as written.
			if ( 1 === count( $matches ) && isset( $matches[ $current_ns . '\\' . $short ] ) ) {
				continue;
			}

			// If the only match is the current namespace's class, fine.
			$current_match = isset( $matches[ $current_ns . '\\' . $short ] );

			if ( $current_match && 1 === count( $matches ) ) {
				continue;
			}

			// Otherwise an unqualified call would resolve to
			// MCR\Sub\Short which does not exist.
			if ( ! $current_match ) {
				$targets = implode( ', ', array_keys( $matches ) );

				mcr_cc_add(
					$issues,
					'ERROR',
					'unqualified-mcr-class',
					$rel,
					0,
					'Call to ' . $short . '::' . $call[2] . '() from namespace ' . $current_ns . ' is not imported. It would resolve to ' . $current_ns . '\\' . $short . '. Candidate classes: ' . $targets
				);
			}
		}
	}

	// Instance calls on a known variable are harder to resolve; check the
	// most important ones explicitly below.
}

/* -------------------------------------------------------------------------
 * 4. Verify schema: every table used in SQL exists in the installer.
 * ---------------------------------------------------------------------- */

$installer = file_get_contents( $root . '/includes/Installer.php' );

$schema_tables = array();

if ( preg_match_all( '/CREATE TABLE\s+\{\$prefix\}([a-z_]+)/', $installer, $m ) ) {
	$schema_tables = $m[1];
}

$used_tables = array();

foreach ( $php_files as $path ) {
	$source = file_get_contents( $path );

	if ( false === $source ) {
		continue;
	}

	$rel = str_replace( '\\', '/', str_replace( $root . DIRECTORY_SEPARATOR, '', $path ) );

	if ( preg_match_all( '/mcr_(balances|transactions|checkins|ad_sessions|payments|claims|ledger|orders|user_coins|user_balances|ad_stats)\b/', $source, $m ) ) {
		foreach ( $m[1] as $table ) {
			$used_tables[ $table ] = true;
		}
	}
}

foreach ( array_keys( $used_tables ) as $table ) {
	if ( ! in_array( $table, $schema_tables, true ) ) {
		mcr_cc_add( $issues, 'ERROR', 'unknown-table', '-', 0, 'Table mcr_' . $table . ' is used in code but not created by the installer.' );
	}
}

/* -------------------------------------------------------------------------
 * 5. Verify hook callbacks point at real methods.
 * ---------------------------------------------------------------------- */

foreach ( $php_files as $path ) {
	$source = file_get_contents( $path );

	if ( false === $source ) {
		continue;
	}

	$rel = str_replace( '\\', '/', str_replace( $root . DIRECTORY_SEPARATOR, '', $path ) );

	// array( $this, 'method' ) style callbacks.
	if ( preg_match_all( '/array\(\s*\$this,\s*[\'"]([a-zA-Z_][a-zA-Z0-9_]*)[\'"]\s*\)/', $source, $m, PREG_OFFSET_CAPTURE ) ) {
		foreach ( $m[1] as $hit ) {
			$method = $hit[0];
			$line   = substr_count( substr( $source, 0, $hit[1] ), "\n" ) + 1;

			// Find the class this file declares.
			$owner = null;

			foreach ( $classes as $fqcn => $info ) {
				if ( $info['file'] === $rel ) {
					$owner = $fqcn;
					break;
				}
			}

			if ( $owner && ! isset( $classes[ $owner ]['methods'][ strtolower( $method ) ] ) ) {
				mcr_cc_add( $issues, 'ERROR', 'missing-callback', $rel, $line, 'Hook callback ' . $owner . '::' . $method . '() does not exist.' );
			}
		}
	}

	// array( 'MCR\Foo\Bar', 'method' ) style callbacks.
	if ( preg_match_all( '/array\(\s*[\'"](MCR\\\\?[A-Za-z0-9_\\\\]+)[\'"]\s*,\s*[\'"]([a-zA-Z_][a-zA-Z0-9_]*)[\'"]\s*\)/', $source, $m, PREG_OFFSET_CAPTURE ) ) {
		foreach ( $m[1] as $index => $hit ) {
			$fqcn   = str_replace( '\\\\', '\\', $hit[0] );
			$method = $m[2][ $index ][0];
			$line   = substr_count( substr( $source, 0, $hit[1] ), "\n" ) + 1;

			if ( 0 === strpos( $rel, 'tests/' ) ) {
				continue; // The checker's own example strings.
			}

			if ( ! isset( $classes[ $fqcn ] ) ) {
				mcr_cc_add( $issues, 'ERROR', 'missing-callback-class', $rel, $line, 'Hook callback class ' . $fqcn . ' does not exist.' );
				continue;
			}

			if ( ! isset( $classes[ $fqcn ]['methods'][ strtolower( $method ) ] ) ) {
				mcr_cc_add( $issues, 'ERROR', 'missing-callback-method', $rel, $line, 'Hook callback ' . $fqcn . '::' . $method . '() does not exist.' );
			}
		}
	}
}

/* -------------------------------------------------------------------------
 * 6. Verify the REST routes the JS calls exist in the controller.
 * ---------------------------------------------------------------------- */

$rest_source = '';

if ( file_exists( $root . '/includes/Rest.php' ) ) {
	$rest_source = file_get_contents( $root . '/includes/Rest.php' );
}

$js_source = '';

if ( file_exists( $root . '/assets/js/mcr-frontend.js' ) ) {
	$js_source = file_get_contents( $root . '/assets/js/mcr-frontend.js' );
}

if ( '' !== $rest_source && '' !== $js_source ) {
	$rest_routes = array();

	if ( preg_match_all( "/register_rest_route\(\s*self::NAMESPACE_V1,\s*'([^']+)'/s", $rest_source, $m ) ) {
		$rest_routes = $m[1];
	}

	// The frontend JS keeps a route map. Each value must be a registered
	// REST route, otherwise the button silently 404s.
	$js_routes = array();

	if ( preg_match_all( "/(\w+)\s*:\s*'([a-z0-9\/_-]+)'/i", $js_source, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $hit ) {
			if ( in_array( $hit[1], array( 'balance', 'checkin', 'adStart', 'adVerify', 'orders', 'transactions', 'dashboard', 'packages' ), true ) ) {
				$js_routes[ $hit[1] ] = '/' . trim( $hit[2], '/' );
			}
		}
	}

	foreach ( $js_routes as $name => $path ) {
		$found = false;

		foreach ( $rest_routes as $registered ) {
			$pattern = '/' . trim( $registered, '/' );

			if ( $pattern === $path ) {
				$found = true;
				break;
			}
		}

		if ( ! $found ) {
			mcr_cc_add(
				$issues,
				'ERROR',
				'unknown-rest-route',
				'assets/js/mcr-frontend.js',
				0,
				'JS route "' . $name . '" maps to ' . $path . ' but no matching REST route is registered.'
			);
		}
	}
}

/* -------------------------------------------------------------------------
 * 7. Verify shortcodes declared in code are registered.
 * ---------------------------------------------------------------------- */

$shortcode_source = '';

foreach ( array( '/includes/Templates/Shortcodes.php', '/includes/Templates/Blocks.php' ) as $candidate ) {
	if ( file_exists( $root . $candidate ) ) {
		$shortcode_source .= file_get_contents( $root . $candidate );
	}
}

if ( preg_match_all( "/add_shortcode\(\s*'([^']+)'/", $shortcode_source, $m ) ) {
	$registered = $m[1];

	foreach ( $registered as $shortcode ) {
		if ( ! preg_match( '/^[a-z0-9_\-]+$/', $shortcode ) ) {
			mcr_cc_add( $issues, 'ERROR', 'bad-shortcode', 'includes/Templates/Shortcodes.php', 0, 'Shortcode name "' . $shortcode . '" is not a valid slug.' );
		}
	}
}

/* -------------------------------------------------------------------------
 * Report.
 * ---------------------------------------------------------------------- */

echo "MCR Consistency Check\n";
echo "====================\n";
printf( "Classes found: %d | PHP files: %d\n\n", count( $classes ), count( $php_files ) );

$by_severity = array(
	'ERROR' => array(),
	'WARN'  => array(),
	'INFO'  => array(),
);

foreach ( $issues as $issue ) {
	$by_severity[ $issue['severity'] ][] = $issue;
}

foreach ( $by_severity as $severity => $items ) {
	if ( empty( $items ) ) {
		continue;
	}

	printf( "%s (%d):\n", $severity, count( $items ) );

	foreach ( $items as $issue ) {
		printf( "  [%s] %s:%d\n        %s\n", $issue['kind'], $issue['file'], $issue['line'], $issue['message'] );
	}

	echo "\n";
}

printf(
	"Summary: %d error(s), %d warning(s), %d info.\n",
	count( $by_severity['ERROR'] ),
	count( $by_severity['WARN'] ),
	count( $by_severity['INFO'] )
);

exit( empty( $by_severity['ERROR'] ) ? 0 : 1 );
