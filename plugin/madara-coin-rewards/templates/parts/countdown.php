<?php
/**
 * Countdown clock.
 *
 * The deadline is an absolute server-issued timestamp. The client only
 * formats the remaining delta; it never decides eligibility.
 *
 * @package MCR
 *
 * @var array $args {
 *     @type string $deadline ISO-8601 deadline.
 *     @type bool   $small    Compact variant.
 *     @type bool   $live     Include the polite live region (default true).
 * }
 */

defined( 'ABSPATH' ) || exit;

$mcr_deadline = isset( $args['deadline'] ) ? (string) $args['deadline'] : '';
$mcr_timestamp = '' !== $mcr_deadline ? strtotime( $mcr_deadline ) : 0;
$mcr_left      = $mcr_timestamp ? max( 0, $mcr_timestamp - time() ) : 0;

$mcr_hours   = (int) floor( $mcr_left / 3600 );
$mcr_minutes = (int) floor( ( $mcr_left % 3600 ) / 60 );
$mcr_seconds = (int) ( $mcr_left % 60 );

$mcr_small = ! empty( $args['small'] );
$mcr_live  = ! isset( $args['live'] ) || $args['live'];

$mcr_classes = 'mcr-countdown' . ( $mcr_small ? ' mcr-countdown--sm' : '' ) . ( $mcr_left > 0 ? '' : ' is-expired' );
?>
<div class="<?php echo esc_attr( $mcr_classes ); ?>"
	<?php echo '' !== $mcr_deadline ? 'data-mcr-deadline="' . esc_attr( $mcr_deadline ) . '"' : ''; ?>
	<?php echo $mcr_left > 0 ? 'data-mcr-seconds="' . esc_attr( (string) $mcr_left ) . '"' : ''; ?>>
	<span class="mcr-countdown__unit">
		<span class="mcr-countdown__num" data-mcr-unit="h"><?php echo esc_html( str_pad( (string) $mcr_hours, 2, '0', STR_PAD_LEFT ) ); ?></span>
		<span class="mcr-countdown__label"><?php esc_html_e( 'h', 'madara-coin-rewards' ); ?></span>
	</span>
	<span class="mcr-countdown__sep" aria-hidden="true">:</span>
	<span class="mcr-countdown__unit">
		<span class="mcr-countdown__num" data-mcr-unit="m"><?php echo esc_html( str_pad( (string) $mcr_minutes, 2, '0', STR_PAD_LEFT ) ); ?></span>
		<span class="mcr-countdown__label"><?php esc_html_e( 'm', 'madara-coin-rewards' ); ?></span>
	</span>
	<span class="mcr-countdown__sep" aria-hidden="true">:</span>
	<span class="mcr-countdown__unit">
		<span class="mcr-countdown__num" data-mcr-unit="s"><?php echo esc_html( str_pad( (string) $mcr_seconds, 2, '0', STR_PAD_LEFT ) ); ?></span>
		<span class="mcr-countdown__label"><?php esc_html_e( 's', 'madara-coin-rewards' ); ?></span>
	</span>
	<?php if ( $mcr_live ) : ?>
		<span class="mcr-sr-only" data-mcr-live aria-live="polite" aria-atomic="true"></span>
	<?php endif; ?>
</div>
