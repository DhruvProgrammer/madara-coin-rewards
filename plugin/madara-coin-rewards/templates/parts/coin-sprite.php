<?php
/**
 * Coin sprite.
 *
 * Hidden SVG printed once per request; every coin glyph references a
 * symbol from here with an SVG <use>.
 *
 * @package MCR
 *
 * @var array $args {
 *     @type array $variants Amount bucket => drawing options.
 * }
 */

defined( 'ABSPATH' ) || exit;

$mcr_variants = isset( $args['variants'] ) && is_array( $args['variants'] ) ? $args['variants'] : array();

$mcr_svg  = '<svg class="mcr-coin-sprite" width="0" height="0" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg">';
$mcr_svg .= '<defs><linearGradient id="mcr-coin-gold" x1="0" y1="0" x2="0" y2="1">';
$mcr_svg .= '<stop offset="0" stop-color="#fff6bf"/><stop offset=".35" stop-color="#ffe45c"/>';
$mcr_svg .= '<stop offset=".62" stop-color="#ffd900"/><stop offset="1" stop-color="#f2b705"/>';
$mcr_svg .= '</linearGradient></defs>';

foreach ( $mcr_variants as $mcr_value => $mcr_variant ) {
	$mcr_svg .= '<symbol id="mcr-coin-symbol-' . esc_attr( $mcr_value ) . '" viewBox="0 0 32 32">';

	if ( ! empty( $mcr_variant['stack'] ) ) {
		$mcr_svg .= '<circle cx="19" cy="16" r="13" fill="#c98a04" opacity=".55"/>';
		$mcr_svg .= '<circle cx="17.5" cy="16" r="13" fill="#f2b705" opacity=".8"/>';
	}

	$mcr_svg .= '<circle cx="16" cy="16" r="15" fill="url(#mcr-coin-gold)"/>';
	$mcr_svg .= '<circle cx="16" cy="16" r="14.25" fill="none" stroke="#b8790a" stroke-width="1.5" opacity=".75"/>';

	if ( ! empty( $mcr_variant['ring'] ) ) {
		$mcr_svg .= '<circle cx="16" cy="16" r="11" fill="none" stroke="#c98a04" stroke-width="1" opacity=".55"/>';
	}

	if ( ! empty( $mcr_variant['ticks'] ) ) {
		$mcr_svg .= '<circle cx="16" cy="16" r="12.5" fill="none" stroke="#b8790a" stroke-width="2" stroke-linecap="round" stroke-dasharray=".6 4.3" opacity=".55"/>';
	}

	$mcr_svg .= '<ellipse cx="12" cy="9.5" rx="6.5" ry="3.2" fill="#ffffff" opacity=".38" transform="rotate(-20 12 9.5)"/>';
	$mcr_svg .= '<text x="16" y="16" text-anchor="middle" dominant-baseline="central" font-family="inherit" font-size="' . esc_attr( $mcr_variant['font'] ) . '" font-weight="700" fill="#6b4a00">' . esc_html( $mcr_value ) . '</text>';
	$mcr_svg .= '</symbol>';
}

$mcr_svg .= '</svg>';

echo $mcr_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
