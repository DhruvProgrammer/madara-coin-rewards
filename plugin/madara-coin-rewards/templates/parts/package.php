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

/*
 * Price per coin. Computed from the integer minor-unit price so the money
 * maths never runs through a float division, and shown only when it is
 * genuinely meaningful (a real price and a real coin amount).
 */
$mcr_minor = isset( $mcr_package['price_minor'] )
	? (int) $mcr_package['price_minor']
	: \MCR\Payments\Packages::price_minor( $mcr_package );

$mcr_unit_price = '';

if ( $mcr_available && $mcr_minor > 0 && $mcr_total > 0 ) {
	// Coins that 100 minor units (one currency unit) buys.
	$mcr_per_unit = ( $mcr_total * 100 ) / $mcr_minor;
	$mcr_decimals = ( fmod( (float) $mcr_per_unit, 1.0 ) > 0 ) ? 0 : 0;

	$mcr_unit_price = number_format_i18n( $mcr_per_unit );
	unset( $mcr_decimals );
}

$mcr_premium   = ! empty( $args['premium'] );
$mcr_login_url = isset( $args['login_url'] ) ? (string) $args['login_url'] : '';

if ( $mcr_premium ) {
	$mcr_classes .= ' mcr-package--premium';
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
			<?php if ( '' !== $mcr_unit_price ) : ?>
				<span class="mcr-package__unit">
					<?php
					printf(
						/* translators: %s: coins per currency unit */
						esc_html__( '%s coins per %s', 'madara-coin-rewards' ),
						esc_html( $mcr_unit_price ),
						esc_html( $mcr_currency )
					);
					?>
				</span>
			<?php endif; ?>
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
			<?php if ( '' !== $mcr_login_url ) : ?>
				<a class="mcr-btn mcr-btn--primary mcr-btn--block" href="<?php echo esc_url( $mcr_login_url ); ?>">
					<?php echo esc_html( $mcr_cta ); ?>
				</a>
			<?php else : ?>
				<button type="button" class="mcr-btn mcr-btn--block" disabled aria-disabled="true"
					<?php echo '' !== $mcr_described ? 'aria-describedby="' . esc_attr( $mcr_described ) . '"' : ''; ?>>
					<?php echo esc_html( $mcr_cta ); ?>
				</button>
			<?php endif; ?>
		<?php else : ?>
			<button type="button" class="mcr-btn mcr-btn--primary mcr-btn--block" data-mcr-buy
				data-mcr-package="<?php echo esc_attr( $mcr_id ); ?>">
				<?php echo esc_html( $mcr_cta ); ?>
			</button>
		<?php endif; ?>
	<?php endif; ?>
</article>
