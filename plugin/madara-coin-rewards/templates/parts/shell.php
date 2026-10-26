<?php
/**
 * Shared page shell.
 *
 * Wraps every rendered rewards page. The balance live region is
 * announced by id, so a page that renders the plugin twice must not
 * emit the same id twice — the first render keeps the canonical id the
 * script looks for, later ones get a suffixed id.
 *
 * @package MCR
 *
 * @var array $args {
 *     @type string $slug    Page slug.
 *     @type string $layout  Layout key.
 *     @type string $classes Space-separated wrapper classes.
 *     @type string $sprite  SVG sprite markup (empty after first print).
 *     @type string $header  Page header markup.
 *     @type string $content Rendered page body.
 *     @type string $live_id Unique live-region id.
 * }
 */

defined( 'ABSPATH' ) || exit;

$mcr_slug    = isset( $args['slug'] ) ? (string) $args['slug'] : '';
$mcr_layout  = isset( $args['layout'] ) ? (string) $args['layout'] : '';
$mcr_classes = isset( $args['classes'] ) ? (string) $args['classes'] : 'mcr-scope mcr-page';
$mcr_sprite  = isset( $args['sprite'] ) ? (string) $args['sprite'] : '';
$mcr_header  = isset( $args['header'] ) ? (string) $args['header'] : '';
$mcr_content = isset( $args['content'] ) ? (string) $args['content'] : '';
$mcr_live_id = isset( $args['live_id'] ) ? (string) $args['live_id'] : 'mcr-live-balance';
?>
<div class="<?php echo esc_attr( $mcr_classes ); ?>" data-mcr-page="<?php echo esc_attr( $mcr_slug ); ?>" data-mcr-layout="<?php echo esc_attr( $mcr_layout ); ?>">
<?php echo $mcr_sprite; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<?php echo $mcr_header; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<div class="mcr-page__body">
<?php echo $mcr_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</div>
<div class="mcr-sr-only" data-mcr-live="balance" id="<?php echo esc_attr( $mcr_live_id ); ?>" aria-live="polite" aria-atomic="true"></div>
<div class="mcr-portal mcr-toasts" role="region" aria-label="<?php echo esc_attr__( 'Notifications', 'madara-coin-rewards' ); ?>"></div>
</div>
