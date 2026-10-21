<?php
/**
 * Loading skeleton.
 *
 * Reserved heights come from the design system (`--stat` 96px, `--row` 61px,
 * `--card` 340px), so the skeleton never causes a layout shift. The wrapper is
 * `aria-busy="true"` and carries a single screen-reader label; every visual
 * skeleton is `aria-hidden="true"`.
 *
 * @package MCR
 *
 * @var array $args {
 *     @type string $variant One of stat, rows, card, checkin, packages, table.
 *     @type int    $count   Number of placeholders.
 *     @type string $label   Screen-reader loading label.
 * }
 */

defined( 'ABSPATH' ) || exit;

$mcr_variant = isset( $args['variant'] ) ? sanitize_key( $args['variant'] ) : 'rows';
$mcr_label   = isset( $args['label'] ) && '' !== (string) $args['label']
	? (string) $args['label']
	: __( 'Loading...', 'madara-coin-rewards' );

$mcr_defaults = array(
	'stat'     => 4,
	'rows'     => 5,
	'card'     => 1,
	'checkin'  => 1,
	'packages' => 3,
	'table'    => 8,
);

$mcr_count = isset( $args['count'] ) ? absint( $args['count'] ) : 0;

if ( $mcr_count < 1 ) {
	$mcr_count = isset( $mcr_defaults[ $mcr_variant ] ) ? $mcr_defaults[ $mcr_variant ] : 5;
}

$mcr_count = min( 20, $mcr_count );
?>
<?php if ( 'stat' === $mcr_variant ) : ?>
	<div class="mcr-grid mcr-grid--stats" aria-busy="true">
		<span class="mcr-sr-only"><?php echo esc_html( $mcr_label ); ?></span>
		<?php for ( $mcr_i = 0; $mcr_i < $mcr_count; $mcr_i++ ) : ?>
			<div class="mcr-skeleton mcr-skeleton--stat" aria-hidden="true"></div>
		<?php endfor; ?>
	</div>
<?php elseif ( 'card' === $mcr_variant || 'packages' === $mcr_variant ) : ?>
	<div class="mcr-grid mcr-grid--packages" aria-busy="true">
		<span class="mcr-sr-only"><?php echo esc_html( $mcr_label ); ?></span>
		<?php for ( $mcr_i = 0; $mcr_i < $mcr_count; $mcr_i++ ) : ?>
			<div class="mcr-skeleton mcr-skeleton--card" aria-hidden="true"></div>
		<?php endfor; ?>
	</div>
<?php elseif ( 'checkin' === $mcr_variant ) : ?>
	<div class="mcr-card" aria-busy="true">
		<span class="mcr-sr-only"><?php echo esc_html( $mcr_label ); ?></span>
		<div class="mcr-skeleton mcr-skeleton--title" aria-hidden="true"></div>
		<div class="mcr-skeleton mcr-skeleton--text" aria-hidden="true"></div>
		<div class="mcr-skeleton mcr-skeleton--text" style="width:60%" aria-hidden="true"></div>
		<div class="mcr-skeleton" style="height:44px;margin-block-start:14px" aria-hidden="true"></div>
	</div>
	<ol class="mcr-streak" aria-hidden="true">
		<?php for ( $mcr_i = 0; $mcr_i < 7; $mcr_i++ ) : ?>
			<li class="mcr-streak__day"><span class="mcr-skeleton mcr-skeleton--circle"></span></li>
		<?php endfor; ?>
	</ol>
<?php elseif ( 'table' === $mcr_variant ) : ?>
	<div class="mcr-table__wrap" aria-busy="true">
		<span class="mcr-sr-only"><?php echo esc_html( $mcr_label ); ?></span>
		<div class="mcr-skeleton__group mcr-skeleton__group--rows" aria-hidden="true">
			<?php for ( $mcr_i = 0; $mcr_i < $mcr_count; $mcr_i++ ) : ?>
				<div class="mcr-skeleton mcr-skeleton--row"></div>
			<?php endfor; ?>
		</div>
	</div>
<?php else : ?>
	<div class="mcr-skeleton__group mcr-skeleton__group--rows" aria-busy="true">
		<span class="mcr-sr-only"><?php echo esc_html( $mcr_label ); ?></span>
		<?php for ( $mcr_i = 0; $mcr_i < $mcr_count; $mcr_i++ ) : ?>
			<div class="mcr-skeleton mcr-skeleton--row" aria-hidden="true"></div>
		<?php endfor; ?>
	</div>
<?php endif;
