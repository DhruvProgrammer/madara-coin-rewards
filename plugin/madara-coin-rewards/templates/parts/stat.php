<?php
/**
 * Stat tile.
 *
 * @package MCR
 *
 * @var array $args {
 *     @type string $label        Tile label.
 *     @type int    $value        Numeric value.
 *     @type string $meta         Optional meta line.
 *     @type string $tone         up or down (meta colour).
 *     @type bool   $accent       Accent variant.
 *     @type bool   $coin         Print the coin glyph next to the value.
 *     @type bool   $data_balance Mark the value as the live balance.
 * }
 */

defined( 'ABSPATH' ) || exit;

$mcr_label  = isset( $args['label'] ) ? (string) $args['label'] : '';
$mcr_value  = isset( $args['value'] ) ? (int) $args['value'] : 0;
$mcr_meta   = isset( $args['meta'] ) ? (string) $args['meta'] : '';
$mcr_tone   = isset( $args['tone'] ) ? sanitize_key( $args['tone'] ) : '';
$mcr_accent = ! empty( $args['accent'] );
$mcr_coin   = ! empty( $args['coin'] );
$mcr_live   = ! empty( $args['data_balance'] );

$mcr_classes = 'mcr-stat' . ( $mcr_accent ? ' mcr-stat--accent' : '' );
$mcr_meta_class = 'mcr-stat__meta';

if ( in_array( $mcr_tone, array( 'up', 'down' ), true ) ) {
	$mcr_meta_class .= ' mcr-stat__meta--' . $mcr_tone;
}
?>
<div class="<?php echo esc_attr( $mcr_classes ); ?>">
	<span class="mcr-stat__label"><?php echo esc_html( $mcr_label ); ?></span>
	<strong class="mcr-stat__value">
		<?php
		if ( $mcr_coin ) {
			$mcr_attrs = array();

			if ( $mcr_live ) {
				$mcr_attrs['data-mcr-balance'] = '1';
			}

			echo \MCR\Templates\Templates::amount( $mcr_value, '', $mcr_attrs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			echo esc_html( number_format_i18n( $mcr_value ) );
		}
		?>
	</strong>
	<?php if ( '' !== $mcr_meta ) : ?>
		<span class="<?php echo esc_attr( $mcr_meta_class ); ?>"><?php echo esc_html( $mcr_meta ); ?></span>
	<?php endif; ?>
</div>
