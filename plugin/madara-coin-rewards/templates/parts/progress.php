<?php
/**
 * Progress bar.
 *
 * Width is pure CSS (`--mcr-progress-value`), so there is no JS dependency.
 *
 * @package MCR
 *
 * @var array $args {
 *     @type int    $value     Current value.
 *     @type int    $max       Maximum value.
 *     @type string $variant   gold for reward caps.
 *     @type string $size      lg for a taller bar.
 *     @type string $left      Left meta text.
 *     @type string $right     Right meta text.
 *     @type string $aria_text Accessible value text.
 * }
 */

defined( 'ABSPATH' ) || exit;

$mcr_value = isset( $args['value'] ) ? max( 0, (int) $args['value'] ) : 0;
$mcr_max   = isset( $args['max'] ) ? max( 0, (int) $args['max'] ) : 0;
$mcr_left  = isset( $args['left'] ) ? (string) $args['left'] : '';
$mcr_right = isset( $args['right'] ) ? (string) $args['right'] : '';
$mcr_aria  = isset( $args['aria_text'] ) ? (string) $args['aria_text'] : '';
$mcr_variant = isset( $args['variant'] ) ? sanitize_key( $args['variant'] ) : '';
$mcr_size    = isset( $args['size'] ) ? sanitize_key( $args['size'] ) : '';

$mcr_percent = $mcr_max > 0 ? min( 100, ( $mcr_value / $mcr_max ) * 100 ) : 0;

if ( '' === $mcr_aria ) {
	$mcr_aria = sprintf(
		/* translators: 1: current value, 2: maximum value */
		__( '%1$s of %2$s', 'madara-coin-rewards' ),
		number_format_i18n( $mcr_value ),
		number_format_i18n( $mcr_max )
	);
}

$mcr_classes = 'mcr-progress';

if ( 'gold' === $mcr_variant ) {
	$mcr_classes .= ' mcr-progress--gold';
}

if ( 'lg' === $mcr_size ) {
	$mcr_classes .= ' mcr-progress--lg';
}
?>
<div class="<?php echo esc_attr( $mcr_classes ); ?>" role="progressbar" aria-valuemin="0"
	aria-valuemax="<?php echo esc_attr( $mcr_max ); ?>" aria-valuenow="<?php echo esc_attr( min( $mcr_value, $mcr_max ) ); ?>"
	aria-valuetext="<?php echo esc_attr( $mcr_aria ); ?>"
	style="--mcr-progress-value:<?php echo esc_attr( round( $mcr_percent, 2 ) ); ?>%">
	<span class="mcr-progress__bar"></span>
</div>
<?php if ( '' !== $mcr_left || '' !== $mcr_right ) : ?>
	<div class="mcr-progress__meta">
		<span><?php echo esc_html( $mcr_left ); ?></span>
		<span><?php echo esc_html( $mcr_right ); ?></span>
	</div>
<?php endif;
