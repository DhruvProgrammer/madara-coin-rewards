<?php
/**
 * Error banner.
 *
 * @package MCR
 *
 * @var array $args {
 *     @type string $title       Heading (default: Something went wrong.).
 *     @type string $text        Body copy.
 *     @type bool   $retry       Show a retry action.
 *     @type string $retry_label Retry label.
 * }
 */

defined( 'ABSPATH' ) || exit;

$mcr_title       = isset( $args['title'] ) && '' !== (string) $args['title']
	? (string) $args['title']
	: __( 'Something went wrong. Please try again.', 'madara-coin-rewards' );
$mcr_text        = isset( $args['text'] ) ? (string) $args['text'] : '';
$mcr_retry       = ! isset( $args['retry'] ) || $args['retry'];
$mcr_retry_label = isset( $args['retry_label'] ) && '' !== (string) $args['retry_label']
	? (string) $args['retry_label']
	: __( 'Try again', 'madara-coin-rewards' );
?>
<div class="mcr-alert mcr-alert--error" role="alert">
	<span class="mcr-alert__icon" aria-hidden="true">!</span>
	<div class="mcr-alert__body">
		<strong class="mcr-alert__title"><?php echo esc_html( $mcr_title ); ?></strong>
		<?php if ( '' !== $mcr_text ) : ?>
			<p class="mcr-alert__text"><?php echo esc_html( $mcr_text ); ?></p>
		<?php endif; ?>
		<?php if ( $mcr_retry ) : ?>
			<div class="mcr-alert__actions">
				<button type="button" class="mcr-btn mcr-btn--sm mcr-btn--quiet" data-mcr-retry><?php echo esc_html( $mcr_retry_label ); ?></button>
			</div>
		<?php endif; ?>
	</div>
</div>
