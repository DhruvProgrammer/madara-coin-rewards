<?php
/**
 * Coin package CRUD.
 *
 * Packages live in the plugin settings (Settings::OPTION, key `packages`)
 * as a keyed map. Every read path normalizes a package and decorates it with
 * derived values:
 *
 * - `coins_total`: coins + bonus, computed once via
 *   PackageCalculator::calculateTotal() so the bonus maths exists in exactly
 *   one place.
 * - `price_minor`: the price as an integer in minor units (cents).
 *
 * MONEY REPRESENTATION: prices are stored as floats/DECIMAL in settings for
 * admin editing, but every comparison and any hand-off to a payment gateway
 * uses `price_minor` (int). Cookies, forms and APIs never see floats on a
 * comparison path — integer minor units only (see Gateway::to_minor()).
 *
 * @package MCR
 */

namespace MCR\Payments;

use MCR\Settings;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Reads, validates and persists coin packages.
 */
class Packages {

	/**
	 * The allowlist of editable package fields.
	 *
	 * @return array
	 */
	public static function fields() {
		return array( 'title', 'coins', 'bonus_percent', 'max_bonus', 'price', 'currency', 'badge', 'active', 'sort_order', 'limit', 'purchased' );
	}

	/**
	 * Returns every package, decorated and sorted by sort_order.
	 *
	 * Sorting ties (equal sort_order) fall back to the package id so the
	 * order is deterministic on every PHP version.
	 *
	 * @return array<int,array> List of normalized package arrays.
	 */
	public static function get_all() {
		$raw      = Settings::get( 'packages', array() );
		$packages = array();

		if ( ! is_array( $raw ) ) {
			return array();
		}

		foreach ( $raw as $id => $package ) {
			$clean = self::normalize( $id, $package );

			if ( null === $clean ) {
				continue;
			}

			$packages[] = $clean;
		}

		usort(
			$packages,
			/**
			 * Sorts packages by sort_order, then id.
			 *
			 * @param array $a First package.
			 * @param array $b Second package.
			 * @return int
			 */
			function ( $a, $b ) {
				$by_order = (int) $a['sort_order'] <=> (int) $b['sort_order'];

				if ( 0 !== $by_order ) {
					return $by_order;
				}

				return strcmp( (string) $a['id'], (string) $b['id'] );
			}
		);

		return array_values( $packages );
	}

	/**
	 * Returns a single package by id, or null.
	 *
	 * @param string|int $id Package id.
	 * @return array|null
	 */
	public static function get( $id ) {
		$id = sanitize_key( (string) $id );

		if ( '' === $id ) {
			return null;
		}

		$raw = Settings::get( 'packages', array() );

		if ( ! is_array( $raw ) || ! isset( $raw[ $id ] ) || ! is_array( $raw[ $id ] ) ) {
			return null;
		}

		return self::normalize( $id, $raw[ $id ] );
	}

	/**
	 * Returns packages whose `active` toggle is `on`, sorted like get_all().
	 *
	 * @return array
	 */
	public static function get_active() {
		return array_values(
			array_filter(
				self::get_all(),
				array( __CLASS__, 'is_active' )
			)
		);
	}

	/**
	 * Whether a normalized package is active.
	 *
	 * @param array $package Normalized package.
	 * @return bool
	 */
	public static function is_active( $package ) {
		return is_array( $package ) && 'on' === ( isset( $package['active'] ) ? $package['active'] : 'off' );
	}

	/**
	 * Whether the package may actually be sold right now.
	 *
	 * Active, in stock (limit 0 = unlimited), with a positive coin amount
	 * and a positive price.
	 *
	 * @param array $package Normalized package.
	 * @return bool
	 */
	public static function is_purchasable( $package ) {
		if ( ! self::is_active( $package ) ) {
			return false;
		}

		$coins       = (int) ( $package['coins'] ?? 0 );
		$bonus       = (int) ( $package['bonus_percent'] ?? 0 );
		$max_bonus   = (int) ( $package['max_bonus'] ?? 0 );
		$limit       = (int) ( $package['limit'] ?? 0 );
		$purchased   = (int) ( $package['purchased'] ?? 0 );
		$coins_total = self::coins_total( $package );

		if ( $coins_total <= 0 && ( $coins > 0 || $bonus > 0 || $max_bonus > 0 ) ) {
			// Normalized package with a broken coin total.
			return false;
		}

		if ( $coins <= 0 ) {
			return false;
		}

		if ( self::price_minor( $package ) <= 0 ) {
			return false;
		}

		if ( $limit > 0 && $purchased >= $limit ) {
			return false;
		}

		return true;
	}

	/**
	 * Total coins granted by a package (base + bonus, capped).
	 *
	 * All bonus maths lives in PackageCalculator::calculateTotal(); this is
	 * the single wrapper the rest of the plugin calls.
	 *
	 * @param array $package Normalized package (or one carrying the raw fields).
	 * @return int
	 */
	public static function coins_total( array $package ) {
		if ( isset( $package['coins_total'] ) ) {
			return (int) $package['coins_total'];
		}

		return PackageCalculator::calculateTotal(
			(int) ( $package['coins'] ?? 0 ),
			(int) ( $package['bonus_percent'] ?? 0 ),
			(int) ( $package['max_bonus'] ?? 0 )
		);
	}

	/**
	 * Package price as an integer in minor units (cents).
	 *
	 * Never compare in floats: convert once here, compare ints downstream.
	 *
	 * @param array $package Normalized package.
	 * @return int Minor units; 0 when the price is missing or invalid.
	 */
	public static function price_minor( array $package ) {
		return PackageCalculator::toMinorUnits( (float) ( $package['price'] ?? 0 ) );
	}

	/**
	 * Human readable price, e.g. "4.99 USD". Escape with esc_html() on echo.
	 *
	 * @param array $package  Normalized package.
	 * @param int   $decimals Decimal places.
	 * @return string
	 */
	public static function format_price( array $package, $decimals = 2 ) {
		$decimals  = max( 0, min( 6, (int) $decimals ) );
		$price     = (float) ( $package['price'] ?? 0 );
		$currency  = strtoupper( sanitize_text_field( (string) ( $package['currency'] ?? '' ) ) );

		return number_format( $price, $decimals ) . ( '' !== $currency ? ' ' . $currency : '' );
	}

	/**
	 * Creates a package.
	 *
	 * @param array $args Package fields (title, coins, bonus_percent,
	 *                    max_bonus, price, currency, badge, active,
	 *                    sort_order, limit, purchased). `id` optional.
	 * @return array|WP_Error The normalized package, or an error.
	 */
	public static function create( array $args ) {
		$id = isset( $args['id'] ) ? sanitize_key( (string) $args['id'] ) : '';

		if ( '' === $id ) {
			$title = sanitize_title( (string) ( $args['title'] ?? '' ) );
			$id    = '' !== $title ? $title : 'package_' . strtolower( substr( md5( uniqid( '', true ) ), 0, 6 ) );
		}

		if ( ! self::validate_amounts( $args ) ) {
			return new WP_Error( 'mcr_package_invalid', __( 'A package needs a positive coin amount and a positive price.', 'madara-coin-rewards' ) );
		}

		$packages = self::raw_packages();

		if ( isset( $packages[ $id ] ) ) {
			return new WP_Error(
				'mcr_package_exists',
				__( 'A package with this id already exists.', 'madara-coin-rewards' )
			);
		}

		$new = self::build_settings_value( $args, isset( $packages[ $id ] ) ? $packages[ $id ] : array(), true, $id );

		if ( is_wp_error( $new ) ) {
			return $new;
		}

		$packages[ $id ] = $new;

		if ( ! self::persist( $packages ) ) {
			return new WP_Error( 'mcr_package_save_failed', __( 'The package could not be saved.', 'madara-coin-rewards' ) );
		}

		return self::get( $id );
	}

	/**
	 * Updates an existing package. Only the given fields change.
	 *
	 * @param string|int $id   Package id.
	 * @param array      $args Fields to change (same allowlist as create()).
	 * @return array|WP_Error The normalized package, or an error.
	 */
	public static function update( $id, array $args ) {
		$id       = sanitize_key( (string) $id );
		$packages = self::raw_packages();

		if ( '' === $id || ! isset( $packages[ $id ] ) ) {
			return new WP_Error( 'mcr_package_unknown', __( 'This coin package does not exist.', 'madara-coin-rewards' ) );
		}

		$built = self::build_settings_value( $args, $packages[ $id ], false, $id );

		// A build failure must never be written into storage: assigning the
		// WP_Error into the map persisted a non-array and left the package
		// unusable, and the caller only saw a generic "could not be saved".
		if ( is_wp_error( $built ) ) {
			return $built;
		}

		$packages[ $id ] = $built;

		if ( ! self::persist( $packages ) ) {
			return new WP_Error( 'mcr_package_save_failed', __( 'The package could not be saved.', 'madara-coin-rewards' ) );
		}

		return self::get( $id );
	}

	/**
	 * Deletes a package.
	 *
	 * @param string|int $id Package id.
	 * @return bool True when the package existed and was removed.
	 */
	public static function delete( $id ) {
		$id       = sanitize_key( (string) $id );
		$packages = self::raw_packages();

		if ( '' === $id || ! isset( $packages[ $id ] ) ) {
			return false;
		}

		unset( $packages[ $id ] );

		return self::persist( $packages );
	}

	/**
	 * The raw settings map (id => package array).
	 *
	 * @return array
	 */
	private static function raw_packages() {
		$packages = Settings::get( 'packages', array() );

		return is_array( $packages ) ? $packages : array();
	}

	/**
	 * Persists the package map through Settings::update().
	 *
	 * @param array $packages Keyed map of raw settings packages.
	 * @return bool
	 */
	private static function persist( array $packages ) {
		return Settings::update( array( 'packages' => $packages ) );
	}

	/**
	 * Validates that required amounts are present and positive.
	 *
	 * @param array $args Raw package args.
	 * @return bool
	 */
	private static function validate_amounts( array $args ) {
		$coins = (int) ( $args['coins'] ?? 0 );
		$price = (float) ( $args['price'] ?? 0 );

		if ( $coins <= 0 || $coins > 100000000 ) {
			return false;
		}

		if ( ! is_numeric( (string) ( $args['price'] ?? '' ) ) || $price <= 0 || $price > 1000000 ) {
			return false;
		}

		return true;
	}

	/**
	 * Builds the settings-array value of one package from raw args.
	 *
	 * Existing values win for fields the caller does not supply (update);
	 * on create, defaults fill the gaps. The result is sanitized through
	 * Settings::sanitizePackages() so no key outside the allowlist ever
	 * reaches storage (mass-assignment defence).
	 *
	 * @param array  $args     Raw fields.
	 * @param array  $existing Existing raw value (may be empty).
	 * @param bool   $is_new   Whether this is a create (defaults differ).
	 * @param string $id       Package id; stored as the map key and the `id` field.
	 * @return array|WP_Error Sanitized settings value, or an error.
	 */
	private static function build_settings_value( array $args, array $existing, $is_new, $id = '' ) {
		$base = array();

		foreach ( self::fields() as $field ) {
			if ( array_key_exists( $field, $args ) ) {
				$base[ $field ] = $args[ $field ];
			} elseif ( isset( $existing[ $field ] ) ) {
				$base[ $field ] = $existing[ $field ];
			}
		}

		// On create, fill the defaults the sanitize routine relies on.
		$base = wp_parse_args(
			$base,
			array(
				'title'         => '',
				'coins'         => 0,
				'bonus_percent' => 0,
				'max_bonus'     => 0,
				'price'         => 0,
				'currency'      => Settings::get( 'purchase.currency', 'USD' ),
				'badge'         => '',
				'active'        => $is_new ? 'on' : 'off',
				'sort_order'    => self::next_sort_order(),
				'limit'         => 0,
				'purchased'     => 0,
			)
		);

		// `active` arrives as arbitrary truthy input; normalize to on/off.
		$base['active'] = ( ! empty( $base['active'] ) && 'off' !== $base['active'] ) ? 'on' : 'off';

		// Sanitize through the shared allowlist: unknown keys are dropped.
		// The package id is passed under its REAL key: sanitizePackages()
		// reads the id from the inner field or, failing that, from the key.
		// Passing a literal 'id' key made every stored package claim to be
		// called "id".
		$id = sanitize_key( (string) $id );
		unset( $base['id'] );

		if ( '' === $id ) {
			return new WP_Error( 'mcr_package_invalid', __( 'The package fields are invalid.', 'madara-coin-rewards' ) );
		}

		$clean = Settings::sanitizePackages( array( $id => $base ) );

		if ( empty( $clean ) ) {
			return new WP_Error( 'mcr_package_invalid', __( 'The package fields are invalid.', 'madara-coin-rewards' ) );
		}

		$package = reset( $clean );

		if ( ! is_array( $package ) || '' === (string) ( $package['id'] ?? '' ) ) {
			return new WP_Error( 'mcr_package_invalid', __( 'The package fields are invalid.', 'madara-coin-rewards' ) );
		}

		// sanitizePackages() drops `max_bonus` (it only knows the base schema).
		// Keep it so the per-package bonus cap survives storage round-trips;
		// Packages::normalize() re-tcasts and clamps it on every read.
		$package['max_bonus'] = max( 0, (int) ( $base['max_bonus'] ?? 0 ) );

		return $package;
	}

	/**
	 * Records a completed sale against the package stock limit.
	 *
	 * @param string $package_id Package id.
	 * @return void
	 */
	public static function record_sale( $package_id ) {
		$package_id = sanitize_key( (string) $package_id );

		if ( '' === $package_id ) {
			return;
		}

		$packages = self::raw_packages();

		if ( ! isset( $packages[ $package_id ] ) || ! is_array( $packages[ $package_id ] ) ) {
			return;
		}

		$package = $packages[ $package_id ];

		$package['purchased'] = (int) ( $package['purchased'] ?? 0 ) + 1;

		$packages[ $package_id ] = $package;

		self::persist( $packages );
	}

	/**
	 * Restores the package stock counter after a refund.
	 *
	 * @param string $package_id Package id.
	 * @return void
	 */
	public static function record_refund( $package_id ) {
		$package_id = sanitize_key( (string) $package_id );

		if ( '' === $package_id ) {
			return;
		}

		$packages = self::raw_packages();

		if ( ! isset( $packages[ $package_id ] ) || ! is_array( $packages[ $package_id ] ) ) {
			return;
		}

		$package = $packages[ $package_id ];

		$package['purchased'] = max( 0, (int) ( $package['purchased'] ?? 0 ) - 1 );

		$packages[ $package_id ] = $package;

		self::persist( $packages );
	}

	/**
	 * The next free sort_order (max + 1).
	 *
	 * @return int
	 */
	private static function next_sort_order() {
		$max = 0;

		foreach ( self::raw_packages() as $package ) {
			$sort = is_array( $package ) && isset( $package['sort_order'] ) ? (int) $package['sort_order'] : 0;
			$max  = max( $max, $sort );
		}

		return $max + 1;
	}

	/**
	 * Normalizes one raw package and decorates it with derived values.
	 *
	 * @param string|int $id      Raw key.
	 * @param mixed      $package Raw package value.
	 * @return array|null Null when the value is unusable.
	 */
	private static function normalize( $id, $package ) {
		if ( ! is_array( $package ) ) {
			return null;
		}

		$id = sanitize_key( (string) $id );

		if ( '' === $id ) {
			return null;
		}

		$bonus = (int) ( $package['bonus_percent'] ?? 0 );
		$bonus = max( 0, min( 100, $bonus ) );

		$price = isset( $package['price'] ) && is_numeric( (string) $package['price'] ) ? (float) $package['price'] : 0.0;
		$price = max( 0, min( 1000000, $price ) );

		$coins = (int) ( $package['coins'] ?? 0 );

		if ( $coins < 0 || $coins > 100000000 ) {
			return null;
		}

		$currency = strtoupper(
			substr(
				sanitize_text_field( (string) ( $package['currency'] ?? Settings::get( 'purchase.currency', 'USD' ) ) ),
				0,
				8
			)
		);

		$package_out = array(
			'id'            => $id,
			'title'         => sanitize_text_field( (string) ( $package['title'] ?? '' ) ),
			'coins'         => $coins,
			'bonus_percent' => $bonus,
			'max_bonus'     => max( 0, (int) ( $package['max_bonus'] ?? 0 ) ),
			'price'         => round( $price, 2 ),
			'currency'      => '' !== $currency ? $currency : Settings::get( 'purchase.currency', 'USD' ),
			'badge'         => sanitize_text_field( (string) ( $package['badge'] ?? '' ) ),
			'active'        => ! empty( $package['active'] ) && 'off' !== $package['active'] ? 'on' : 'off',
			'sort_order'    => (int) ( $package['sort_order'] ?? 0 ),
			'limit'         => max( 0, (int) ( $package['limit'] ?? 0 ) ),
			'purchased'     => max( 0, (int) ( $package['purchased'] ?? 0 ) ),
		);

		// Derived values are computed here so consumers never recompute.
		$package_out['coins_total'] = PackageCalculator::calculateTotal(
			$package_out['coins'],
			$package_out['bonus_percent'],
			$package_out['max_bonus']
		);

		$package_out['price_minor'] = PackageCalculator::toMinorUnits( $package_out['price'] );

		return $package_out;
	}
}

if ( function_exists( 'add_action' ) ) {
	add_action(
		'mcr_payment_credited',
		static function ( $order, $result = array() ) {
			if ( is_array( $result ) && ! empty( $result['duplicate'] ) ) {
				return;
			}

			if ( is_array( $order ) && isset( $order['package_id'] ) ) {
				\MCR\Payments\Packages::record_sale( (string) $order['package_id'] );
			}
		},
		10,
		2
	);

	add_action(
		'mcr_payment_refunded',
		static function ( $order ) {
			if ( is_array( $order ) && isset( $order['package_id'] ) ) {
				\MCR\Payments\Packages::record_refund( (string) $order['package_id'] );
			}
		},
		10,
		1
	);
}
