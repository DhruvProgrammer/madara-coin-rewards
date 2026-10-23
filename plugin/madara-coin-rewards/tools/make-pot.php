<?php
/**
 * Generates languages/madara-coin-rewards.pot from the plugin source.
 *
 * Run with:
 *
 *     php tools/make-pot.php
 *
 * The extractor uses PHP's own tokenizer, so it sees exactly the same string
 * literals that WordPress sees at runtime. Only calls whose message id is a
 * plain string literal are collected: a concatenated or computed msgid can
 * never be matched by a translator, so it does not belong in the catalogue.
 *
 * @package MCR
 */

/*
 * The ABSPATH guard keeps the file from being requested directly over
 * HTTP. The generator runs from the command line, where WordPress is not
 * loaded, so the constant is defined for the CLI instead.
 */
if ( ! defined( 'ABSPATH' ) ) {
	if ( 'cli' !== PHP_SAPI ) {
		exit;
	}

	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "This script must be run from the command line.\n" );
	exit( 1 );
}

const MCR_POT_DOMAIN = 'madara-coin-rewards';

/**
 * The i18n functions the extractor understands.
 *
 * Each entry maps the function name to the position of its arguments:
 * "text" is the singular message id, "plural" the plural message id,
 * "context" the disambiguation context and "domain" the text domain.
 */
function mcr_pot_functions() {
	return array(
		'__'         => array(
			'text'   => 0,
			'domain' => 1,
		),
		'_e'         => array(
			'text'   => 0,
			'domain' => 1,
		),
		'esc_html__' => array(
			'text'   => 0,
			'domain' => 1,
		),
		'esc_html_e' => array(
			'text'   => 0,
			'domain' => 1,
		),
		'esc_attr__' => array(
			'text'   => 0,
			'domain' => 1,
		),
		'esc_attr_e' => array(
			'text'   => 0,
			'domain' => 1,
		),
		'_x'         => array(
			'text'   => 0,
			'context' => 1,
			'domain' => 2,
		),
		'_ex'        => array(
			'text'   => 0,
			'context' => 1,
			'domain' => 2,
		),
		'esc_html_x' => array(
			'text'   => 0,
			'context' => 1,
			'domain' => 2,
		),
		'esc_attr_x' => array(
			'text'   => 0,
			'context' => 1,
			'domain' => 2,
		),
		'_n'         => array(
			'text'   => 0,
			'plural' => 1,
			'domain' => 3,
		),
		'_nx'        => array(
			'text'   => 0,
			'plural' => 1,
			'context' => 3,
			'domain' => 4,
		),
	);
}

/**
 * Returns the plugin root directory.
 *
 * @return string
 */
function mcr_pot_root() {
	return dirname( __DIR__ );
}

/**
 * Returns every plugin PHP file that ships to a site.
 *
 * The test and the documentation directories are not part of the plugin.
 *
 * @param string $root Plugin root.
 * @return array List of relative paths.
 */
function mcr_pot_files( $root ) {
	$skip = array( 'tests', 'docs', 'tools', 'node_modules', 'vendor', '.git' );
	$files = array();

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS )
	);

	foreach ( $iterator as $file ) {
		if ( ! $file->isFile() || 'php' !== strtolower( $file->getExtension() ) ) {
			continue;
		}

		$relative = str_replace( '\\', '/', substr( $file->getPathname(), strlen( rtrim( $root, '/\\' ) ) + 1 ) );

		if ( preg_match( '#^(' . implode( '|', array_map( 'preg_quote', $skip ) ) . ')/#', $relative ) ) {
			continue;
		}

		$files[] = $relative;
	}

	sort( $files );

	return $files;
}

/**
 * Returns the literal string of a token, or null when it is not a literal.
 *
 * @param array $tokens Argument token list.
 * @param int   $index  Argument index.
 * @return string|null
 */
function mcr_pot_argument_string( array $tokens, $index ) {
	if ( ! isset( $tokens[ $index ] ) || ! $tokens[ $index ] ) {
		return null;
	}

	$first = $tokens[ $index ][0];

	if ( ! is_array( $first ) || T_CONSTANT_ENCAPSED_STRING !== $first[0] ) {
		return null;
	}

	$raw   = $first[1];
	$quote = $raw[0];
	$value = substr( $raw, 1, -1 );

	if ( "'" === $quote ) {
		$value = str_replace( array( "\\'", '\\\\' ), array( "'", '\\' ), $value );
	} else {
		$value = str_replace( array( '\\"', '\\\\' ), array( '"', '\\' ), $value );
	}

	return $value;
}

/**
 * Extracts every translatable call from a token stream.
 *
 * @param array $tokens Token stream.
 * @return array List of call descriptors.
 */
function mcr_pot_calls( array $tokens ) {
	$functions = mcr_pot_functions();
	$calls     = array();
	$count     = count( $tokens );

	// Index of the last "translators:" comment seen, if any.
	$comment_index = null;
	$comment_text  = '';

	for ( $i = 0; $i < $count; $i++ ) {
		$token = $tokens[ $i ];

		if ( is_array( $token ) && in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
			if ( preg_match( '#/\*\s*translators:\s*(.*?)\s*\*/#is', $token[1], $match ) ) {
				$comment_index = $i;
				$comment_text  = trim( preg_replace( '#\s+#', ' ', $match[1] ) );
			} elseif ( preg_match( '#^\s*//\s*translators:\s*(.*)$#i', $token[1], $match ) ) {
				$comment_index = $i;
				$comment_text  = trim( preg_replace( '#\s+#', ' ', $match[1] ) );
			}

			continue;
		}

		if ( ! is_array( $token ) || T_STRING !== $token[0] || ! isset( $functions[ $token[1] ] ) ) {
			continue;
		}

		$spec = $functions[ $token[1] ];

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
		$buffer = array();
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
				$args[] = $buffer;
				$buffer = array();

				$k++;
				continue;
			}

			if ( 1 === $depth && '.' === $current ) {
				$concat = true;
			}

			$buffer[] = $current;

			$k++;
		}

		$args[] = $buffer;

		// Strip the leading whitespace and comments of every argument.
		foreach ( $args as $index => $argument ) {
			while ( $argument && is_array( $argument[0] )
				&& in_array( $argument[0][0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
				array_shift( $argument );
			}

			$args[ $index ] = $argument;
		}

		$first = isset( $args[0] ) ? $args[0] : array();

		while ( $first && is_array( $first[0] ) && T_WHITESPACE === $first[0][0] ) {
			array_shift( $first );
		}

		$kind = 'literal';

		if ( empty( $first ) ) {
			$kind = 'empty';
		} elseif ( ! is_array( $first[0] ) || T_CONSTANT_ENCAPSED_STRING !== $first[0][0] ) {
			$kind = 'dynamic';
		} elseif ( $concat ) {
			$kind = 'concatenated';
		}

		$domain = mcr_pot_argument_string( $args, $spec['domain'] );

		if ( MCR_POT_DOMAIN !== $domain ) {
			$comment_index = null;
			$comment_text  = '';

			continue;
		}

		if ( 'literal' !== $kind ) {
			$comment_index = null;
			$comment_text  = '';

			continue;
		}

		$context = isset( $spec['context'] ) ? mcr_pot_argument_string( $args, $spec['context'] ) : null;
		$plural  = isset( $spec['plural'] ) ? mcr_pot_argument_string( $args, $spec['plural'] ) : null;

		// The reference points at the string literal, not at the call.
		$text_line  = $token[2];
		$plural_line = 0;

		if ( isset( $args[ $spec['text'] ][0] ) && is_array( $args[ $spec['text'] ][0] ) ) {
			$text_line = $args[ $spec['text'] ][0][2];
		}

		if ( isset( $spec['plural'] ) && isset( $args[ $spec['plural'] ][0] ) && is_array( $args[ $spec['plural'] ][0] ) ) {
			$plural_line = $args[ $spec['plural'] ][0][2];
		}

		$call = array(
			'fn'         => $token[1],
			'line'       => $text_line,
			'plural_line' => $plural_line,
			'msgid'      => mcr_pot_argument_string( $args, $spec['text'] ),
			'plural'     => $plural,
			'context'    => $context,
			'comment'    => '',
		);

		if ( null !== $comment_index ) {
			// Only attach the comment when nothing but whitespace separates it.
			$attached = true;

			for ( $c = $comment_index + 1; $c < $i; $c++ ) {
				if ( is_array( $tokens[ $c ] ) && T_WHITESPACE === $tokens[ $c ][0] ) {
					continue;
				}

				$attached = false;
				break;
			}

			if ( $attached ) {
				$call['comment'] = $comment_text;
			}
		}

		$calls[] = $call;

		$comment_index = null;
		$comment_text  = '';
	}

	return $calls;
}

/**
 * Escapes a string for a gettext catalogue.
 *
 * @param string $text Raw text.
 * @return string
 */
function mcr_pot_escape( $text ) {
	return str_replace(
		array( '\\', '"', "\n", "\r", "\t" ),
		array( '\\\\', '\\"', '\\n', '\\r', '\\t' ),
		$text
	);
}

/**
 * Renders the catalogue header.
 *
 * @return string
 */
function mcr_pot_header() {
	$header = array(
		'Project-Id-Version: Madara Coin & Rewards 1.0.0',
		'Report-Msgid-Bugs-To: https://example.com/madara-coin-rewards',
		'POT-Creation-Date: ' . gmdate( 'Y-m-d\TH:i:s+00:00' ),
		'MIME-Version: 1.0',
		'Content-Type: text/plain; charset=UTF-8',
		'Content-Transfer-Encoding: 8bit',
		'X-Generator: MCR tools/make-pot.php',
		'X-Domain: ' . MCR_POT_DOMAIN,
		'Language-Team: LANGUAGE <LL@li.org>',
		'Plural-Forms: nplurals=2; plural=(n != 1);',
	);

	$out  = "# Copyright (C) 2026 Madara Coin & Rewards\n";
	$out .= "# This file is distributed under the GPL-2.0-or-later license.\n";
	$out .= "msgid \"\"\n";
	$out .= "msgstr \"\"\n";

	foreach ( $header as $line ) {
		$out .= '"' . $line . "\\n\"\n";
	}

	return $out;
}

$root = mcr_pot_root();
$files = mcr_pot_files( $root );

$entries = array();

foreach ( $files as $relative ) {
	$source = file_get_contents( $root . '/' . $relative );
	$tokens = token_get_all( $source );

	foreach ( mcr_pot_calls( $tokens ) as $call ) {
		$key = ( null === $call['context'] ? '' : $call['context'] ) . "\x04" . $call['msgid'] . "\x04" . ( null === $call['plural'] ? '' : $call['plural'] );

		if ( ! isset( $entries[ $key ] ) ) {
			$entries[ $key ] = array(
				'msgid'   => $call['msgid'],
				'plural'  => $call['plural'],
				'context' => $call['context'],
				'comment' => $call['comment'],
				'refs'    => array(),
			);
		}

		if ( '' === $entries[ $key ]['comment'] && '' !== $call['comment'] ) {
			$entries[ $key ]['comment'] = $call['comment'];
		}

		$entries[ $key ]['refs'][] = $relative . ':' . $call['line'];

		if ( $call['plural_line'] > 0 && $call['plural_line'] !== $call['line'] ) {
			$entries[ $key ]['refs'][] = $relative . ':' . $call['plural_line'];
		}
	}
}

// Order the catalogue by the first reference, the way makepot does.
$ordered = array_values( $entries );

usort(
	$ordered,
	function ( $a, $b ) {
		$first_a = explode( ':', $a['refs'][0] );
		$first_b = explode( ':', $b['refs'][0] );

		$file_a = $first_a[0];
		$file_b = $first_b[0];

		if ( $file_a !== $file_b ) {
			return strcmp( $file_a, $file_b );
		}

		return (int) $first_a[1] - (int) $first_b[1];
	}
);

$out = mcr_pot_header();

foreach ( $ordered as $entry ) {
	$out .= "\n";

	if ( '' !== $entry['comment'] ) {
		$out .= '#. translators: ' . $entry['comment'] . "\n";
	}

	foreach ( $entry['refs'] as $reference ) {
		$out .= '#: ' . $reference . "\n";
	}

	if ( null !== $entry['context'] && '' !== $entry['context'] ) {
		$out .= 'msgctxt "' . mcr_pot_escape( $entry['context'] ) . "\"\n";
	}

	$out .= 'msgid "' . mcr_pot_escape( $entry['msgid'] ) . "\"\n";

	if ( null !== $entry['plural'] && '' !== $entry['plural'] ) {
		$out .= 'msgid_plural "' . mcr_pot_escape( $entry['plural'] ) . "\"\n";
		$out .= "msgstr[0] \"\"\n";
		$out .= "msgstr[1] \"\"\n";
	} else {
		$out .= "msgstr \"\"\n";
	}
}

$target = $root . '/languages/madara-coin-rewards.pot';

if ( ! is_dir( dirname( $target ) ) ) {
	mkdir( dirname( $target ), 0755, true );
}

file_put_contents( $target, $out );

printf(
	"Wrote %s with %d strings from %d files.\n",
	'languages/madara-coin-rewards.pot',
	count( $ordered ),
	count( $files )
);
