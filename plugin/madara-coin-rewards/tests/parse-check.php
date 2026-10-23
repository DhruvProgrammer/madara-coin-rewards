<?php
/**
 * Syntax check every PHP file in the plugin.
 *
 * Run with: php tests/parse-check.php
 *
 * Equivalent to running `php -l` on each file, but in one pass and with a
 * summary. token_get_all() with the TOKEN_PARSE flag performs a full parse
 * and raises a ParseError on invalid syntax, so this needs no shell and no
 * native PHP binary.
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

$failures = 0;
$checked  = 0;
$bytes    = 0;

foreach ( $files as $path ) {
	$rel    = str_replace( $root . DIRECTORY_SEPARATOR, '', $path );
	$source = file_get_contents( $path );

	if ( false === $source ) {
		printf( "FAIL %s (unreadable)\n", $rel );
		$failures++;
		continue;
	}

	$checked++;
	$bytes += strlen( $source );

	/*
	 * A UTF-8 BOM before "<?php" is a fatal error: "Namespace declaration
	 * statement has to be the very first statement". Editors add it silently,
	 * so it is checked explicitly.
	 */
	if ( 0 === strncmp( $source, "\xEF\xBB\xBF", 3 ) ) {
		$failures++;
		printf( "FAIL %s\n     A UTF-8 BOM precedes the opening tag. PHP cannot parse this file.\n", $rel );
	}

	try {
		token_get_all( $source, TOKEN_PARSE );
	} catch ( \ParseError $e ) {
		$failures++;
		printf( "FAIL %s\n     %s (line %d)\n", $rel, $e->getMessage(), $e->getLine() );
	} catch ( \Throwable $e ) {
		$failures++;
		printf( "FAIL %s\n     %s\n", $rel, $e->getMessage() );
	}
}

printf( "Parsed %d PHP files (%s KB), %d failed.\n", $checked, number_format( $bytes / 1024, 1 ), $failures );

exit( $failures > 0 ? 1 : 0 );
