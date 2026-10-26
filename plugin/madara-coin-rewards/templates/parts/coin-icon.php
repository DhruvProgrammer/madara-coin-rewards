<?php
/**
 * Coin icon with an explicit pixel size.
 *
 * Inline element: the markup stays on one line so whitespace between
 * the glyph and an adjacent value never shows in text flow.
 *
 * @package MCR
 *
 * @var array $args {
 *     @type int    $size   Icon edge length in px.
 *     @type string $class  Extra classes (already sanitised).
 *     @type string $symbol Sprite symbol to draw (1, 5, 10, 50, 100).
 * }
 */

defined( 'ABSPATH' ) || exit;

$mcr_size   = isset( $args['size'] ) ? (int) $args['size'] : 32;
$mcr_class  = isset( $args['class'] ) ? (string) $args['class'] : '';
$mcr_symbol = isset( $args['symbol'] ) ? (string) $args['symbol'] : '10';

echo '<span class="mcr-coin ' . esc_attr( $mcr_class ) . '" style="--mcr-coin-size:' . esc_attr( $mcr_size ) . 'px" aria-hidden="true" focusable="false">'
	. '<svg class="mcr-coin__svg" viewBox="0 0 32 32" focusable="false" aria-hidden="true">'
	. '<use href="#mcr-coin-symbol-' . esc_attr( $mcr_symbol ) . '"></use>'
	. '</svg></span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
