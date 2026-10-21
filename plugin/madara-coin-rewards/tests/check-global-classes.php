<?php
/**
 * Global class resolution checker.
 *
 * Run with: php tests/check-global-classes.php
 *
 * Inside a PHP namespace, an unqualified class name resolves to
 * NAMESPACE\ClassName, not \ClassName. So `new WP_Error()` inside
 * `namespace MCR\Rewards;` tries to instantiate `MCR\Rewards\WP_Error` and
 * fatals at runtime - a bug that a syntax check cannot catch but that
 * breaks the site the moment the code path is reached.
 *
 * This tool finds every unqualified reference to a well-known WordPress or
 * PHP global class in a namespaced file that does not import it.
 *
 * @package MCR
 */

$root = dirname( __DIR__ );

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

/**
 * Global classes that must be imported (or prefixed with a backslash) when
 * used inside a namespace.
 */
$globals = array(
	'WP_Error',
	'WP_REST_Request',
	'WP_REST_Response',
	'WP_REST_Server',
	'WP_User',
	'WP_Post',
	'WP_List_Table',
	'WP_Query',
	'WP_User_Query',
	'WP_Term',
	'WP_Term_Query',
	'WP_Post_Type',
	'WP_Screen',
	'WP_Http',
	'WP_Customize_Manager',
	'WP_Customize_Setting',
	'WP_Customize_Control',
	'WP_Customize_Color_Control',
	'WP_Customize_Image_Control',
	'WP_Customize_Select_Control',
	'WP_Customize_Textarea_Control',
	'WP_Customize_Heading',
	'WP_Site_Health',
	'WP_Upgrader',
	'WP_Filesystem',
	'WP_Roles',
	'WP_Block_Type_Registry',
	'WP_Block',
	'WP_Block_Binding_Context',
	'WP_Screen',
	'WP_Nonce_Validator',
	'DateTime',
	'DateTimeImmutable',
	'DateTimeZone',
	'DateInterval',
	'DatePeriod',
	'Exception',
	'Error',
	'Throwable',
	'ArrayObject',
	'ArrayIterator',
	'Countable',
	'Iterator',
	'IteratorAggregate',
	'JsonException',
	'Closure',
	'InvalidArgumentException',
	'RuntimeException',
	'LogicException',
	'OutOfBoundsException',
	'UnexpectedValueException',
	'TypeError',
	'ValueError',
	'DivisionByZeroError',
	'Throwable',
);

$issues = array();

foreach ( $files as $path ) {
	$rel    = str_replace( '\\', '/', str_replace( $root . DIRECTORY_SEPARATOR, '', $path ) );
	$source = file_get_contents( $path );

	if ( false === $source ) {
		continue;
	}

	// Skip the test harness: it declares its own WP_Error on purpose.
	if ( 0 === strpos( $rel, 'tests/' ) ) {
		continue;
	}

	if ( ! preg_match( '/^\s*namespace\s+([^;{]+)[;{]/m', $source, $ns_match ) ) {
		continue; // Global namespace: nothing to resolve.
	}

	$namespace = trim( $ns_match[1] );

	// Collect imported class aliases.
	$imported = array();

	if ( preg_match_all( '/^\s*use\s+([^;]+);/m', $source, $use_matches ) ) {
		foreach ( $use_matches[1] as $import ) {
			$import = trim( $import );

			if ( false !== stripos( $import, ' as ' ) ) {
				list( $fq, $alias ) = preg_split( '/\s+as\s+/i', $import );
				$imported[ trim( $alias ) ] = trim( $fq );
				continue;
			}

			$parts = explode( '\\', $import );
			$imported[ end( $parts ) ] = $import;
		}
	}

	// Collect the class's own name (so self references are fine).
	$tokens = token_get_all( $source );
	$own    = '';

	for ( $i = 0; $i < count( $tokens ); $i++ ) {
		if ( is_array( $tokens[ $i ] ) && T_CLASS === $tokens[ $i ][0] ) {
			for ( $k = $i + 1; $k < count( $tokens ); $k++ ) {
				if ( is_array( $tokens[ $k ] ) && T_WHITESPACE === $tokens[ $k ][0] ) {
					continue;
				}

				if ( is_array( $tokens[ $k ] ) && T_STRING === $tokens[ $k ][0] ) {
					$own = $tokens[ $k ][1];
				}

				break;
			}

			break;
		}
	}

	// Strip comments so docblock mentions do not create false positives.
	// Whitespace is preserved so byte offsets stay aligned with $source.
	$code = '';
	$open = false;

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

	foreach ( $globals as $global ) {
		if ( isset( $imported[ $global ] ) ) {
			continue; // Properly imported.
		}

		if ( $global === $own ) {
			continue; // The class itself.
		}

		// Look for an unqualified use: new Global(, Global::, extends Global,
		// implements Global, or a type hint Global $var.
		$pattern = '/(?<![\\\\$>a-zA-Z0-9_])(' . preg_quote( $global, '/' ) . ')(?:\s*\(|\s*::|\s*\$|\\\\s*\{)/';

		if ( ! preg_match_all( $pattern, $code, $m, PREG_OFFSET_CAPTURE ) ) {
			continue;
		}

		foreach ( $m[1] as $hit ) {
			$offset = $hit[1];
			$line   = substr_count( substr( $source, 0, $offset ), "\n" ) + 1;

			$before = substr( $code, max( 0, $offset - 2 ), 2 );

			if ( '\\' === $before ) {
				continue; // Fully qualified.
			}

			// Make sure it is not part of a longer identifier.
			$prefix = substr( $code, 0, $offset );
			$tail   = substr( $prefix, -1 );

			if ( '' !== $tail && ( ctype_alnum( $tail ) || '_' === $tail ) ) {
				continue;
			}

			$issues[] = array(
				'file' => $rel,
				'line' => $line,
				'class' => $global,
				'namespace' => $namespace,
				'snippet' => trim( substr( $source, max( 0, $offset - 40 ), 90 ) ),
			);

			break; // One report per class per file is enough.
		}
	}
}

echo "MCR Global Class Resolution Check\n";
echo "=================================\n";
printf( "Scanned %d files.\n\n", count( $files ) );

if ( empty( $issues ) ) {
	echo "No unresolved global class references found.\n";
	exit( 0 );
}

printf( "Unresolved global class references (%d):\n", count( $issues ) );

foreach ( $issues as $issue ) {
	printf(
		"  %s:%d  %s -> would resolve to %s\\%s\n      %s\n",
		$issue['file'],
		$issue['line'],
		$issue['class'],
		$issue['namespace'],
		$issue['class'],
		preg_replace( '/\s+/', ' ', $issue['snippet'] )
	);
}

echo "\nFix each by adding 'use " . "{$issue['class']}" . ";' at the top of the file,\n";
echo "or by prefixing the class with a backslash.\n";

exit( 1 );
