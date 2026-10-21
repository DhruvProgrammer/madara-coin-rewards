<?php
/**
 * Coin glyph.
 *
 * The glyph is decoration: the real number always lives in the adjacent
 * `.mcr-amount__value`. The sprite is printed once per request by
 * MCR\Templates\Templates::render().
 *
 * @package MCR
 *
 * @var array $args {
 *     @type int    $value Amount, used to pick the nearest symbol variant.
 *     @type string $size  sm, lg or xl.
 * }
 */

defined( 'ABSPATH' ) || exit;

$mcr_value  = isset( $args['value'] ) ? absint( $args['value'] ) : 5;
$mcr_bucket = 1;

foreach ( array( 5, 10, 50, 100 ) as $mcr_step ) {
	if ( $mcr_value >= $mcr_step ) {
		$mcr_bucket = $mcr_step;
	}
}

$mcr_size_key = isset( $args['size'] ) ? sanitize_key( $args['size'] ) : '';
$mcr_size     = in_array( $mcr_size_key, array( 'sm', 'lg', 'xl' ), true ) ? ' mcr-coin--' . $mcr_size_key : '';
?>
<span class="mcr-coin<?php echo esc_attr( $mcr_size ); ?>">
	<svg class="mcr-coin__svg" viewBox="0 0 32 32" aria-hidden="true" focusable="false">
		<use href="#mcr-coin-symbol-<?php echo esc_attr( $mcr_bucket ); ?>"></use>
	</svg>
</span>
