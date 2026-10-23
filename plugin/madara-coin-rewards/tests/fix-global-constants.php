<?php
/**
 * Prefixes unqualified WordPress constants inside namespaced files.
 *
 * Run with: php tests/fix-global-constants.php
 *
 * In a namespace, an unqualified constant is first looked up as
 * NAMESPACE\CONSTANT and only then in the global namespace. The global
 * fallback works, but WordPress Coding Standards require the explicit
 * leading backslash, and the fallback is not available if the constant
 * has not been defined yet (WordPress defines ARRAY_A and the time
 * constants in wp-includes/load.php).
 *
 * The rewrite is done with the tokenizer, so occurrences inside strings
 * and comments are left alone.
 *
 * @package MCR
 */

$root = dirname( __DIR__ );

$constants = array(
	'ARRAY_A',
	'ARRAY_N',
	'OBJECT',
	'OBJECT_K',
	'MINUTE_IN_SECONDS',
	'HOUR_IN_SECONDS',
	'DAY_IN_SECONDS',
	'WEEK_IN_SECONDS',
	'MONTH_IN_SECONDS',
	'YEAR_IN_SECONDS',
);

$files = array();
$skip  = array( '.git', 'node_modules', 'vendor' );

$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS )
);

foreach ( $iterator as $file ) {
	$path = $file->getPathname();

	foreach ( $skip as $dir ) {
		if ( false !== strpos( $path, DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR ) ) {
			continue 2;
		}
	}

	if ( 'php' === strtolower( $file->getExtension() ) ) {
		$files[] = $path;
	}
}

sort( $files );

$changed_files = 0;
$changed_total = 0;

foreach ( $files as $path ) {
	$rel    = str_replace( $root . DIRECTORY_SEPARATOR, '', $path );
	$source = file_get_contents( $path );

	if ( false === $source ) {
		continue;
	}

	// Only namespaced files are affected.
	if ( ! preg_match( '/^\s*namespace\s+([^;{]+)[;{]/m', $source, $ns_match ) ) {
		continue;
	}

	$tokens = token_get_all( $source );
	$out    = '';
	$count  = 0;
	$len    = count( $tokens );

	for ( $i = 0; $i < $len; $i++ ) {
		$token = $tokens[ $i ];

		if ( ! is_array( $token ) ) {
			$out .= $token;
			continue;
		}

		if ( T_CONSTANT_ENCAPSED_STRING === $token[0] || T_COMMENT === $token[0] || T_DOC_COMMENT === $token[0] ) {
			$out .= $token[1];
			continue;
		}

		if ( T_STRING === $token[0] && in_array( $token[1], $constants, true ) ) {
			// Skip when already fully qualified.
			$prev = $i - 1;

			while ( $prev >= 0 && is_array( $tokens[ $prev ] ) && T_WHITESPACE === $tokens[ $prev ][0] ) {
				$prev--;
			}

			$qualified = false;

			if ( $prev >= 0 ) {
				if ( is_array( $tokens[ $prev ] ) && T_NS_SEPARATOR === $tokens[ $prev ][0] ) {
					$qualified = true;
				} elseif ( is_array( $tokens[ $prev ] ) && in_array( $tokens[ $prev ][0], array( T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION ), true ) ) {
					$qualified = true;
				} elseif ( is_array( $tokens[ $prev ] ) && T_CONST === $tokens[ $prev ][0] ) {
					$qualified = true;
				}
			}

			// Skip when it is part of a class constant or a namespaced name.
			$next = $i + 1;

			while ( $next < $len && is_array( $tokens[ $next ] ) && T_WHITESPACE === $tokens[ $next ][0] ) {
				$next++;
			}

			if ( $next < $len && is_array( $tokens[ $next ] ) && T_DOUBLE_COLON === $tokens[ $next ][0] ) {
				$qualified = true;
			}

			if ( ! $qualified ) {
				$out   .= '\\' . $token[1];
				$count++;
				continue;
			}
		}

		$out .= $token[1];
	}

	if ( $count > 0 ) {
		file_put_contents( $path, $out );
		++$changed_files;
		$changed_total += $count;
		printf( "  %-58s %d constant(s) prefixed\n", $rel, $count );
	}
}

printf( "\nPrefixed %d constant(s) across %d file(s).\n", $changed_total, $changed_files );
