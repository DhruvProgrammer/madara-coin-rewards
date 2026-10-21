<?php
/**
 * Coin package card.
 *
 * Prices are display-only: the amount is snapshotted on the server when the
 * order is created, so editing the DOM cannot change what is charged.
 *
 * @package MCR
 *
 * @var array $args {
 *     @type array  $package     Normalized package (id, title, coins, bonus_percent,
 *                               coins_total, price, currency, badge).
 *     @type bool   $featured    Featured card (accent border + ribbon).
 *     @type bool   $available   Whether a price is configured.
 *     @type array  $features    Feature strings (optional).
 *     @type string $cta_label   Call-to-action label.
 *     @type bool   $disabled    Disable the CTA (payment gateway unavailable).
 *     @type string $describedby Id of the alert that explains the disabled state.
 * }
 */

defined( 'ABSPATH' ) || exit;

$mcr_package = isset( $args['package'] ) && is_array( $args['package'] ) ? $args['package'] : array();

if ( empty( $mcr_package['id'] ) ) {
	return;
}

$mcr_id        = sanitize_key( (string) $mcr_package['id'] );
$mcr_title     = isset( $mcr_package['title'] ) && '' !== (string) $mcr_package['title']
	? (string) $mcr_package['title']
	: __( 'Coin package', 'madara-coin-rewards' );
$mcr_coins     = isset( $mcr_package['coins'] ) ? max( 0, (int) $mcr_package['coins'] ) : 0;
$mcr_total     = isset( $mcr_package['coins_total'] ) ? max( 0, (int) $mcr_package['coins_total'] ) : $mcr_coins;
$mcr_bonus     = max( 0, $mcr_total - $mcr_coins );
$mcr_price     = isset( $mcr_package['price'] ) ? (float) $mcr_package['price'] : 0.0;
$mcr_currency  = isset( $mcr_package['currency'] ) && '' !== (string) $mcr_package['currency']
	? strtoupper( (string) $mcr_package['currency'] )
	: 'USD';
$mcr_featured  = ! empty( $args['featured'] );
$mcr_available = ! isset( $args['available'] ) || $args['available'];
$mcr_available = $mcr_available && $mcr_price > 0;
$mcr_disabled  = ! empty( $args['disabled'] );
$mcr_described = isset( $args['describedby'] ) ? (string) $args['describedby'] : '';
$mcr_features  = isset( $args['features'] ) && is_array( $args['features'] ) ? $args['features'] : array();

if ( empty( $mcr_features ) ) {
	$mcr_features = array(
		__( 'One-time purchase', 'madara-coin-rewards' ),
		__( 'Coins are added after payment confirmation', 'madara-coin-rewards' ),
	);
}

$mcr_ribbon = isset( $mcr_package['badge'] ) ? trim( (string) $mcr_package['badge'] ) : '';

if ( '' === $mcr_ribbon && $mcr_featured ) {
	$mcr_ribbon = __( 'Most popular', 'madara-coin-rewards' );
}

$mcr_cta = isset( $args['cta_label'] ) && '' !== (string) $args['cta_label']
	? (string) $args['cta_label']
	: sprintf(
		/* translators: %s: coin amount */
		__( 'Buy %s coins', 'madara-coin-rewards' ),
		number_format_i18n( $mcr_total )
	);

$mcr_classes = 'mcr-package';

if ( $mcr_featured ) {
	$mcr_classes .= ' is-featured';
}

if ( ! $mcr_available ) {
	$mcr_classes .= ' is-unavailable';
}

$mcr_decimals = ( $mcr_price === floor( $mcr_price ) ) ? 0 : 2;
?>
<article class="<?php echo esc_attr( $mcr_classes ); ?>" aria-labelledby="mcr-package-<?php echo esc_attr( $mcr_id ); ?>-name">
	<?php if ( '' !== $mcr_ribbon ) : ?>
		<span class="mcr-package__ribbon"><?php echo esc_html( $mcr_ribbon ); ?></span>
	<?php endif; ?>

	<h3 class="mcr-package__name" id="mcr-package-<?php echo esc_attr( $mcr_id ); ?>-name"><?php echo esc_html( $mcr_title ); ?></h3>

	<p class="mcr-package__coins">
		<?php echo \MCR\Templates\Templates::amount( $mcr_total, 'lg' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php if ( $mcr_bonus > 0 ) : ?>
			<span class="mcr-package__bonus">
				<?php
				printf(
					/* translators: %s: bonus coin amount */
					esc_html__( '+%s bonus coins', 'madara-coin-rewards' ),
					esc_html( number_format_i18n( $mcr_bonus ) )
				);
				?>
			</span>
		<?php endif; ?>
	</p>

	<p class="mcr-package__price">
		<?php if ( $mcr_available ) : ?>
			<span class="mcr-package__price-amount"><?php echo esc_html( number_format_i18n( $mcr_price, $mcr_decimals ) ); ?></span>
			<span class="mcr-package__price-currency"><?php echo esc_html( $mcr_currency ); ?></span>
		<?php else : ?>
			<span class="mcr-muted"><?php esc_html_e( 'Unavailable', 'madara-coin-rewards' ); ?></span>
		<?php endif; ?>
	</p>

	<ul class="mcr-package__features">
		<?php foreach ( $mcr_features as $mcr_feature ) : ?>
			<?php if ( '' === (string) $mcr_feature ) : ?>
				<?php continue; ?>
			<?php endif; ?>
			<li><?php echo esc_html( $mcr_feature ); ?></li>
		<?php endforeach; ?>
	</ul>

	<?php if ( $mcr_available ) : ?>
		<?php if ( $mcr_disabled ) : ?>
			<button type="button" class="mcr-btn mcr-btn--block" disabled aria-disabled="true"
				<?php echo '' !== $mcr_described ? 'aria-describedby="' . esc_attr( $mcr_described ) . '"' : ''; ?>>
				<?php echo esc_html( $mcr_cta ); ?>
			</button>
		<?php else : ?>
			<button type="button" class="mcr-btn mcr-btn--block" data-mcr-buy
				data-mcr-package="<?php echo esc_attr( $mcr_id ); ?>">
				<?php echo esc_html( $mcr_cta ); ?>
			</button>
		<?php endif; ?>
	<?php endif; ?>
</article>
