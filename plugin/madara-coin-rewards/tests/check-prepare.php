<?php
/**
 * Finds the wpdb::prepare() calls whose query has no placeholder.
 *
 * Run with: php tests/check-prepare.php
 *
 * WordPress raises a "_doing_it_wrong" notice when prepare() is handed a
 * query that has no placeholder in it:
 *
 *   Function wpdb::prepare was called incorrectly. The query argument of
 *   wpdb::prepare() must have a placeholder.
 *
 * The query is still run, but the notice is written to the debug log on
 * every request. A query with no placeholder does not need prepare() at
 * all: the placeholder is what makes the arguments safe, and there are
 * no arguments.
 *
 * @package MCR
 */

$root = dirname( __DIR__ );

$files = array();

$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $root . '/includes', FilesystemIterator::SKIP_DOTS )
);

foreach ( $iterator as $file ) {
	if ( 'php' === strtolower( $file->getExtension() ) ) {
		$files[] = $file->getPathname();
	}
}

sort( $files );

$issues = array();

/**
 * Reports an issue.
 *
 * @param array  $issues The issue list.
 * @param string $file   The file.
 * @param int    $line   The line.
 * @param string $detail The detail.
 */
function mcr_cp_add( &$issues, $file, $line, $detail ) {
	$issues[] = array(
		'file'   => $file,
		'line'   => $line,
		'detail' => $detail,
	);
}

foreach ( $files as $path ) {
	$source = file_get_contents( $path );

	if ( false === $source ) {
		continue;
	}

	$rel = str_replace( '\\', '/', $path );
	$rel = str_replace( str_replace( '\\', '/', $root ) . '/', '', $rel );

	$tokens = token_get_all( $source );
	$count  = count( $tokens );

	for ( $i = 0; $i < $count; $i++ ) {
		$token = $tokens[ $i ];

		// Look for the "prepare" method call.
		if ( ! is_array( $token ) || T_STRING !== $token[0] || 'prepare' !== strtolower( $token[1] ) ) {
			continue;
		}

		/*
		 * It has to be a method call: the token before "prepare" is the
		 * object operator or the scope operator, and the token after it
		 * is the "(" that opens the call. Both are ARRAY tokens, not
		 * plain strings.
		 */
		$j = $i - 1;

		while ( $j >= 0 && is_array( $tokens[ $j ] ) && T_WHITESPACE === $tokens[ $j ][0] ) {
			--$j;
		}

		$is_call = false;

		if ( $j >= 0 && is_array( $tokens[ $j ] ) ) {
			if ( T_OBJECT_OPERATOR === $tokens[ $j ][0] || T_DOUBLE_COLON === $tokens[ $j ][0] ) {
				$is_call = true;
			}
		}

		if ( ! $is_call ) {
			continue;
		}

		// The first argument: a string literal, or a concatenation of them.
		$k = $i + 1;

		while ( $k < $count && is_array( $tokens[ $k ] ) && T_WHITESPACE === $tokens[ $k ][0] ) {
			++$k;
		}

		if ( $k >= $count || '(' !== $tokens[ $k ] ) {
			continue;
		}

		++$k;

		$query       = '';
		$depth       = 1;
		$arg_count   = 0;
		$is_variable = false;
		$is_plain    = true;

		// Is the first argument a plain string, or a variable?
		{
			$look = $k;

			while ( $look < $count && is_array( $tokens[ $look ] ) && T_WHITESPACE === $tokens[ $look ][0] ) {
				++$look;
			}

			if ( $look < $count && is_array( $tokens[ $look ] ) && T_VARIABLE === $tokens[ $look ][0] ) {
				$is_variable = true;
			}
		}

		while ( $k < $count && $depth > 0 ) {
			$piece = $tokens[ $k ];

			if ( '(' === $piece ) {
				++$depth;
			} elseif ( ')' === $piece ) {
				--$depth;

				if ( 0 === $depth ) {
					break;
				}
			} elseif ( ',' === $piece && 1 === $depth ) {
				++$arg_count;
			} elseif ( is_array( $piece ) && T_CONSTANT_ENCAPSED_STRING === $piece[0] ) {
				$query .= $piece[1];
			} elseif ( is_array( $piece ) && T_ENCAPSED_AND_WHITESPACE === $piece[0] ) {
				// The literal part of an interpolated string.
				$query .= $piece[1];
				$is_plain = false;
			} elseif ( is_array( $piece ) && T_VARIABLE === $piece[0] && 1 === $depth ) {
				$is_plain = false;
			}

			++$k;
		}

		$line = is_array( $token ) ? $token[2] : 0;

		/*
		 * A query that is built into a variable cannot be judged here:
		 * the placeholder may be inside the variable. Those are left
		 * alone.
		 */
		if ( $is_variable ) {
			continue;
		}

		if ( false !== strpos( $query, '%' ) ) {
			continue;
		}

		/*
		 * A plain string with no placeholder is always wrong. An
		 * interpolated string with no placeholder is only wrong when
		 * nothing is passed to substitute, because the variable may
		 * carry the placeholder.
		 */
		if ( ! $is_plain && 0 !== $arg_count ) {
			continue;
		}

		mcr_cp_add(
			$issues,
			$rel,
			$line,
			'prepare() is called with a query that has no placeholder. WordPress raises a "called incorrectly" notice for it on every request, and the query does not need prepare() at all.'
		);
	}
}

echo "MCR Prepare Check\n";
echo "==================\n\n";

if ( empty( $issues ) ) {
	echo "Scanned " . count( $files ) . " files. Every prepare() call has a placeholder.\n";
	exit( 0 );
}

echo 'Scanned ' . count( $files ) . " files. Found " . count( $issues ) . " problem(s):\n\n";

foreach ( $issues as $issue ) {
	printf( "  %s:%d\n    %s\n\n", $issue['file'], $issue['line'], $issue['detail'] );
}

exit( 1 );
