<?php
/**
 * Notice banner.
 *
 * Roles are never hardcoded to alert: only errors interrupt.
 *
 * @package MCR
 *
 * @var array $args {
 *     @type string $type    info, success, warning or error.
 *     @type string $title   Heading.
 *     @type string $text    Body copy.
 *     @type array  $actions Action list: label, url, class.
 * }
 */

defined( 'ABSPATH' ) || exit;

$mcr_type = isset( $args['type'] ) ? sanitize_key( $args['type'] ) : 'info';

if ( ! in_array( $mcr_type, array( 'info', 'success', 'warning', 'error' ), true ) ) {
	$mcr_type = 'info';
}

$mcr_title   = isset( $args['title'] ) ? (string) $args['title'] : '';
$mcr_text    = isset( $args['text'] ) ? (string) $args['text'] : '';
$mcr_actions = isset( $args['actions'] ) && is_array( $args['actions'] ) ? $args['actions'] : array();

$mcr_icons = array(
	'info'    => 'i',
	'success' => '&#10003;',
	'warning' => '!',
	'error'   => '!',
);

$mcr_role = 'error' === $mcr_type ? 'alert' : 'status';
?>
<div class="mcr-alert mcr-alert--<?php echo esc_attr( $mcr_type ); ?>" role="<?php echo esc_attr( $mcr_role ); ?>">
	<span class="mcr-alert__icon" aria-hidden="true"><?php echo wp_kses_post( $mcr_icons[ $mcr_type ] ); ?></span>
	<div class="mcr-alert__body">
		<?php if ( '' !== $mcr_title ) : ?>
			<strong class="mcr-alert__title"><?php echo esc_html( $mcr_title ); ?></strong>
		<?php endif; ?>
		<?php if ( '' !== $mcr_text ) : ?>
			<p class="mcr-alert__text"><?php echo esc_html( $mcr_text ); ?></p>
		<?php endif; ?>
		<?php if ( ! empty( $mcr_actions ) ) : ?>
			<div class="mcr-alert__actions">
				<?php foreach ( $mcr_actions as $mcr_action ) : ?>
					<?php
					if ( ! is_array( $mcr_action ) || empty( $mcr_action['label'] ) ) {
						continue;
					}

					$mcr_class = isset( $mcr_action['class'] ) ? (string) $mcr_action['class'] : 'mcr-btn mcr-btn--sm mcr-btn--quiet';
					$mcr_url   = isset( $mcr_action['url'] ) ? (string) $mcr_action['url'] : '';
					?>
					<?php if ( '' !== $mcr_url ) : ?>
						<a class="<?php echo esc_attr( $mcr_class ); ?>" href="<?php echo esc_url( $mcr_url ); ?>"><?php echo esc_html( $mcr_action['label'] ); ?></a>
					<?php else : ?>
						<button type="button" class="<?php echo esc_attr( $mcr_class ); ?>"><?php echo esc_html( $mcr_action['label'] ); ?></button>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</div>
