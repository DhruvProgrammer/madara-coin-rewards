<?php
/**
 * Empty state.
 *
 * @package MCR
 *
 * @var array $args {
 *     @type string $icon    Glyph (HTML entity or plain character).
 *     @type string $title   Heading.
 *     @type string $text    Body copy.
 *     @type array  $actions Action list: label, url (link), class, attributes.
 *     @type bool   $bare    Bare variant without border/background.
 * }
 */

defined( 'ABSPATH' ) || exit;

$mcr_icon    = isset( $args['icon'] ) ? (string) $args['icon'] : '';
$mcr_title   = isset( $args['title'] ) ? (string) $args['title'] : '';
$mcr_text    = isset( $args['text'] ) ? (string) $args['text'] : '';
$mcr_actions = isset( $args['actions'] ) && is_array( $args['actions'] ) ? $args['actions'] : array();
$mcr_bare    = ! empty( $args['bare'] );

$mcr_classes = 'mcr-empty' . ( $mcr_bare ? ' mcr-empty--bare' : '' );
?>
<div class="<?php echo esc_attr( $mcr_classes ); ?>">
	<?php if ( '' !== $mcr_icon ) : ?>
		<div class="mcr-empty__icon" aria-hidden="true"><?php echo wp_kses_post( $mcr_icon ); ?></div>
	<?php endif; ?>
	<?php if ( '' !== $mcr_title ) : ?>
		<h3 class="mcr-empty__title"><?php echo esc_html( $mcr_title ); ?></h3>
	<?php endif; ?>
	<?php if ( '' !== $mcr_text ) : ?>
		<p class="mcr-empty__text"><?php echo esc_html( $mcr_text ); ?></p>
	<?php endif; ?>
	<?php if ( ! empty( $mcr_actions ) ) : ?>
		<div class="mcr-actions">
			<?php foreach ( $mcr_actions as $mcr_action ) : ?>
				<?php
				if ( ! is_array( $mcr_action ) ) {
					continue;
				}

				$mcr_label = isset( $mcr_action['label'] ) ? (string) $mcr_action['label'] : '';
				$mcr_url   = isset( $mcr_action['url'] ) ? (string) $mcr_action['url'] : '';
				$mcr_class = isset( $mcr_action['class'] ) ? (string) $mcr_action['class'] : 'mcr-btn';
				$mcr_attrs = isset( $mcr_action['attributes'] ) && is_array( $mcr_action['attributes'] ) ? $mcr_action['attributes'] : array();

				if ( '' === $mcr_label ) {
					continue;
				}

				$mcr_attr_html = '';

				foreach ( $mcr_attrs as $mcr_attr => $mcr_value ) {
					$mcr_attr = preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $mcr_attr ) );

					if ( '' === $mcr_attr ) {
						continue;
					}

					$mcr_attr_html .= ' ' . $mcr_attr . '="' . esc_attr( $mcr_value ) . '"';
				}

				if ( '' !== $mcr_url ) {
					?>
					<a class="<?php echo esc_attr( $mcr_class ); ?>" href="<?php echo esc_url( $mcr_url ); ?>"<?php echo $mcr_attr_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html( $mcr_label ); ?></a>
					<?php
				} else {
					?>
					<button type="button" class="<?php echo esc_attr( $mcr_class ); ?>"<?php echo $mcr_attr_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html( $mcr_label ); ?></button>
					<?php
				}
				?>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
