<?php
/**
 * Encryption for the secrets the plugin stores.
 *
 * @package MCR
 */

namespace MCR\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Encrypts and decrypts the secrets the plugin keeps in the options table.
 *
 * A secret is encrypted with a key derived from the site's auth salt, so
 * the stored value is useless on its own: reading wp_options no longer
 * reveals the Stripe key or the ad network's shared secret.
 *
 * sodium's secretbox is used when the extension is available. On a host
 * without it the value is stored as it is, and the marker prefix makes
 * that unambiguous - decrypt() returns a value that has no marker
 * unchanged, so the two states never collide.
 */
class Secrets {

	/**
	 * The marker that identifies an encrypted value.
	 */
	const PREFIX = 'mcrenc::';

	/**
	 * Encrypts a secret.
	 *
	 * A value that is already encrypted is returned unchanged, so saving
	 * the settings form twice cannot double-encrypt it.
	 *
	 * @param string $plain Raw secret.
	 * @return string
	 */
	public static function encrypt( $plain ) {
		$plain = (string) $plain;

		if ( '' === $plain || self::is_encrypted( $plain ) ) {
			return $plain;
		}

		if ( ! function_exists( 'sodium_crypto_secretbox' ) ) {
			return $plain;
		}

		try {
			$key    = hash( 'sha256', wp_salt( 'auth' ), true );
			$nonce  = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$cipher = sodium_crypto_secretbox( $plain, $nonce, $key );

			return self::PREFIX . base64_encode( $nonce . $cipher );
		} catch ( \Throwable $e ) {
			return $plain;
		}
	}

	/**
	 * Decrypts a stored secret.
	 *
	 * A value without the marker is returned as it is, which is what a
	 * store written before encryption was available needs.
	 *
	 * @param string $stored Stored value.
	 * @return string Decrypted secret, '' when it cannot be decrypted.
	 */
	public static function decrypt( $stored ) {
		$stored = trim( (string) $stored );

		if ( '' === $stored ) {
			return '';
		}

		if ( ! self::is_encrypted( $stored ) ) {
			return $stored;
		}

		if ( ! function_exists( 'sodium_crypto_secretbox_open' ) ) {
			return '';
		}

		$blob = base64_decode( substr( $stored, strlen( self::PREFIX ) ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		if ( false === $blob || strlen( $blob ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return '';
		}

		try {
			$nonce  = substr( $blob, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$cipher = substr( $blob, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$key    = hash( 'sha256', wp_salt( 'auth' ), true );

			$plain = sodium_crypto_secretbox_open( $cipher, $nonce, $key );

			return false === $plain ? '' : $plain;
		} catch ( \Throwable $e ) {
			return '';
		}
	}

	/**
	 * Whether a stored value is already encrypted.
	 *
	 * @param string $stored Stored value.
	 * @return bool
	 */
	public static function is_encrypted( $stored ) {
		return 0 === strpos( trim( (string) $stored ), self::PREFIX );
	}
}
