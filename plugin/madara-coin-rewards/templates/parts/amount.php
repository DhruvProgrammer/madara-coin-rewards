<?php
/**
 * Formatted coin amount.
 *
 * Inline element: the markup stays on one line so whitespace between
 * the glyph and the value never shows in text flow.
 *
 * @package MCR
 *
 * @var array $args {
 *     @type int    $value  Amount.
 *     @type string $size   sm, lg or xl (already sanitised).
 *     @type string $suffix "--..." size class (already sanitised).
 *     @type string $extra  Prebuilt extra attributes (escaped in PHP).
 * }
 */

defined( 'ABSPATH' ) || exit;

$mcr_value  = isset( $args['value'] ) ? (int) $args['value'] : 0;
$mcr_size   = isset( $args['size'] ) ? (string) $args['size'] : '';
$mcr_suffix = isset( $args['suffix'] ) ? (string) $args['suffix'] : '';
$mcr_extra  = isset( $args['extra'] ) ? (string) $args['extra'] : '';

$mcr_coin = \MCR\Templates\Templates::part(
	'coin',
	array(
		'value' => $mcr_value,
		'size'  => in_array( $mcr_size, array( 'sm', 'lg', 'xl' ), true ) ? $mcr_size : '',
	)
);

echo '<span class="mcr-amount' . esc_attr( $mcr_suffix ) . '" data-mcr-amount="' . esc_attr( $mcr_value ) . '"' . $mcr_extra . '>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	. $mcr_coin // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	. '<span class="mcr-amount__value">' . esc_html( number_format_i18n( $mcr_value ) ) . '</span>'
	. '<span class="mcr-sr-only">' . esc_html__( 'coins', 'madara-coin-rewards' ) . '</span>'
	. '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
