<?php
/**
 * Finds the WordPress functions the plugin calls that the test harness does
 * not provide.
 *
 * Run with: php tests/find-missing-stubs.php
 *
 * A WordPress function called from inside a namespace falls back to the
 * global scope, so these are not plugin bugs - but the harness must provide
 * them, or a test fails for the wrong reason.
 *
 * @package MCR
 */

$root = dirname( __DIR__ );

$provided = array();

foreach ( array( 'tests/bootstrap.php', 'tests/wp-stubs.php', 'tests/admin-test.php', 'tests/frontend-test.php' ) as $file ) {
	$path = $root . '/' . $file;

	if ( ! file_exists( $path ) ) {
		continue;
	}

	$source = file_get_contents( $path );

	if ( preg_match_all( '/^function\s+(\w+)\s*\(/m', $source, $m ) ) {
		foreach ( $m[1] as $name ) {
			$provided[ $name ] = true;
		}
	}
}

// PHP built-ins that WordPress code commonly uses.
$builtin = array(
	'array', 'array_merge', 'array_filter', 'array_map', 'array_keys', 'array_values', 'array_slice',
	'array_combine', 'array_column', 'array_unique', 'array_search', 'array_key_exists', 'array_fill',
	'count', 'strlen', 'strtolower', 'strtoupper', 'trim', 'rtrim', 'ltrim', 'substr', 'strpos',
	'stripos', 'str_replace', 'sprintf', 'printf', 'implode', 'explode', 'in_array', 'is_array',
	'is_string', 'is_int', 'is_float', 'is_bool', 'is_null', 'is_object', 'is_numeric', 'isset', 'empty',
	'abs', 'ceil', 'floor', 'round', 'max', 'min', 'intval', 'floatval', 'strval', 'boolval',
	'json_encode', 'json_decode', 'md5', 'sha1', 'hash', 'hash_hmac', 'hash_equals', 'random_bytes',
	'bin2hex', 'hex2bin', 'base64_encode', 'base64_decode', 'preg_match', 'preg_match_all',
	'preg_replace', 'preg_replace_callback', 'preg_split', 'preg_quote', 'preg_escape',
	'str_split', 'str_pad', 'str_repeat', 'ucfirst', 'ucwords', 'nl2br', 'strip_tags',
	'date', 'gmdate', 'time', 'strtotime', 'mktime', 'checkdate', 'date_default_timezone_set',
	'wp_die', 'call_user_func', 'call_user_func_array', 'func_get_args', 'func_num_args',
	'is_callable', 'method_exists', 'class_exists', 'interface_exists', 'function_exists',
	'get_class', 'get_parent_class', 'get_object_vars', 'gettype', 'settype', 'serialize',
	'unserialize', 'var_dump', 'print_r', 'iterator_to_array', 'usort', 'uasort', 'uksort',
	'ksort', 'krsort', 'asort', 'arsort', 'sort', 'rsort', 'array_walk', 'array_map',
	'defined', 'define', 'constant', 'trigger_error', 'error_log', 'exit', 'die', 'sleep',
	'number_format', 'str_word_count', 'wordwrap', 'chunk_split', 'similar_text',
	'array_diff', 'array_intersect', 'array_flip', 'compact', 'extract', 'gettype',
	'htmlspecialchars', 'htmlentities', 'stripcslashes', 'addslashes', 'addcslashes',
	'sprintf', 'vsprintf', 'vprintf', 'fopen', 'fclose', 'fwrite', 'fread', 'file_get_contents',
	'file_exists', 'is_readable', 'is_writable', 'is_dir', 'mkdir', 'unlink', 'copy', 'rename',
	'scandir', 'glob', 'opendir', 'readdir', 'closedir', 'tempnam', 'sys_get_temp_dir',
	'getenv', 'putenv', 'phpversion', 'php_uname', 'extension_loaded', 'function_exists',
	'wp_parse_args', 'array_walk_recursive', 'usort', 'str_word_count',
);

$used = array();

/*
 * The names of every method the plugin declares, so that a method CALL is
 * not mistaken for a missing WordPress function.
 */
$plugin_methods = array();

$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $root . '/includes', FilesystemIterator::SKIP_DOTS )
);

foreach ( $iterator as $file ) {
	if ( 'php' !== strtolower( $file->getExtension() ) ) {
		continue;
	}

	$source = file_get_contents( $file->getPathname() );

	if ( preg_match_all( '/function\s+(\w+)\s*\(/', $source, $m ) ) {
		foreach ( $m[1] as $name ) {
			$plugin_methods[ strtolower( $name ) ] = true;
		}
	}
}

$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $root . '/includes', FilesystemIterator::SKIP_DOTS )
);

foreach ( $iterator as $file ) {
	if ( 'php' !== strtolower( $file->getExtension() ) ) {
		continue;
	}

	$source = file_get_contents( $file->getPathname() );

	if ( ! preg_match_all( '/(?<![\\\\$>\w\'"])([a-z_][a-z0-9_]*)\s*\(/i', $source, $m ) ) {
		continue;
	}

	foreach ( $m[1] as $name ) {
		$lower = strtolower( $name );

		// Skip method calls and language constructs.
		if ( in_array( $lower, array( 'if', 'elseif', 'else', 'foreach', 'for', 'while', 'switch', 'match', 'array', 'list', 'isset', 'unset', 'empty', 'echo', 'print', 'return', 'new', 'clone', 'include', 'require', 'function', 'static', 'public', 'private', 'protected', 'try', 'catch', 'finally', 'throw', 'exit', 'die', 'and', 'or', 'xor', 'use', 'namespace', 'declare', 'endif', 'endforeach', 'as', 'fn', 'yield', 'instanceof', 'global', 'const' ), true ) ) {
			continue;
		}

		// A method the plugin itself declares is not a WordPress function.
		if ( isset( $plugin_methods[ $lower ] ) ) {
			continue;
		}

		$used[ $lower ] = true;
	}
}

$missing = array();

foreach ( array_keys( $used ) as $name ) {
	if ( isset( $provided[ $name ] ) || in_array( $name, $builtin, true ) ) {
		continue;
	}

	$missing[] = $name;
}

sort( $missing );

echo "Functions the plugin calls that the harness does not provide:\n";

if ( empty( $missing ) ) {
	echo "  (none)\n";
	exit( 0 );
}

foreach ( $missing as $name ) {
	echo "  - " . $name . "\n";
}

echo "\n" . count( $missing ) . " missing.\n";
