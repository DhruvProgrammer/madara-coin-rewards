<?php
/**
 * Buy coins page - premium storefront.
 *
 * Everything shown here is read from the plugin's own configuration:
 * packages come from Packages::get_active(), the balance from the ledger,
 * the payment state from real orders, and the gateway from the configured
 * gateway's own availability check. Nothing is hardcoded, and no control
 * ever pretends to take a payment.
 *
 * @package MCR
 *
 * @var array $args Template arguments.
 */

defined( 'ABSPATH' ) || exit;

use MCR\Payments\Packages;

$mcr_packages = isset( $args['packages'] ) && is_array( $args['packages'] ) ? $args['packages'] : array();
$mcr_stats    = isset( $args['stats'] ) && is_array( $args['stats'] ) ? $args['stats'] : array();
$mcr_urls     = isset( $args['urls'] ) && is_array( $args['urls'] ) ? $args['urls'] : array();
$mcr_notice   = isset( $args['notice'] ) && is_array( $args['notice'] ) ? $args['notice'] : array();
$mcr_recent_orders   = isset( $args['orders'] ) && is_array( $args['orders'] ) ? $args['orders'] : array();
$mcr_gateways = isset( $args['gateways'] ) && is_array( $args['gateways'] ) ? $args['gateways'] : array();

$mcr_gateway    = ! empty( $args['gateway_available'] );
$mcr_pending    = ! empty( $args['pending_order'] );
$mcr_enabled    = ! isset( $args['enabled'] ) || $args['enabled'];
$mcr_balance    = isset( $mcr_stats['balance'] ) ? (int) $mcr_stats['balance'] : 0;
$mcr_earned     = isset( $mcr_stats['lifetime_earned'] ) ? (int) $mcr_stats['lifetime_earned'] : 0;
$mcr_spent      = isset( $mcr_stats['lifetime_spent'] ) ? (int) $mcr_stats['lifetime_spent'] : 0;
$mcr_columns    = isset( $args['columns'] ) ? max( 1, min( 4, (int) $args['columns'] ) ) : 3;
$mcr_txn_url    = isset( $mcr_urls['transactions'] ) ? (string) $mcr_urls['transactions'] : '';
$mcr_login_url  = isset( $mcr_urls['login'] ) ? (string) $mcr_urls['login'] : '';
$mcr_checkin_url = isset( $mcr_urls['checkin'] ) ? (string) $mcr_urls['checkin'] : '';
$mcr_is_logged_in = is_user_logged_in();

/*
 * Only sell what the backend will actually accept. A package that is
 * inactive, out of stock, or unpriced is shown as unavailable rather than
 * hidden, so the storefront never advertises something checkout refuses.
 */
$mcr_sellable = array();

foreach ( $mcr_packages as $mcr_candidate ) {
	if ( ! is_array( $mcr_candidate ) || empty( $mcr_candidate['id'] ) ) {
		continue;
	}

	if ( Packages::is_purchasable( $mcr_candidate ) ) {
		$mcr_sellable[] = $mcr_candidate;
	}
}

/*
 * Value ranking: coins per currency unit, computed from the normalized
 * price_minor so the comparison never runs through a float division on a
 * money value. The cheapest coin wins "Best value" unless the admin has
 * already pinned a badge, which always wins.
 */
$mcr_best_value_id = '';

foreach ( $mcr_sellable as $mcr_i => $mcr_candidate ) {
	$mcr_minor = (int) ( $mcr_candidate['price_minor'] ?? 0 );
	$mcr_total = (int) ( $mcr_candidate['coins_total'] ?? 0 );

	if ( $mcr_minor <= 0 || $mcr_total <= 0 ) {
		continue;
	}

	// Compare coins per 100 minor units to stay in integers.
	$mcr_rate = (int) floor( ( $mcr_total * 100 ) / $mcr_minor );

	if ( '' === $mcr_best_value_id || $mcr_rate > $mcr_best_value_rate ) {
		$mcr_best_value_rate = $mcr_rate;
		$mcr_best_value_id   = (string) $mcr_candidate['id'];
	}
}

$mcr_has_any_package = ! empty( $mcr_packages );
$mcr_can_checkout    = $mcr_enabled && $mcr_gateway && ! empty( $mcr_sellable );

$mcr_grid_classes = 'mcr-grid mcr-grid--packages mcr-buy__grid';

if ( $mcr_columns > 1 ) {
	$mcr_grid_classes .= ' mcr-grid--cols-' . $mcr_columns;
}
?>

<?php /* ============ 1. PREMIUM HERO ============ */ ?>
<section class="mcr-buy__hero" aria-labelledby="mcr-buy-hero-title">
	<div class="mcr-buy__hero-glow" aria-hidden="true"></div>

	<div class="mcr-buy__hero-inner">
		<div class="mcr-buy__hero-copy">
			<p class="mcr-buy__eyebrow">
				<span class="mcr-buy__eyebrow-dot" aria-hidden="true"></span>
				<?php esc_html_e( 'Coin store', 'madara-coin-rewards' ); ?>
			</p>

			<h1 class="mcr-buy__hero-title" id="mcr-buy-hero-title">
				<?php esc_html_e( 'Get More Coins', 'madara-coin-rewards' ); ?>
			</h1>

			<p class="mcr-buy__hero-sub">
				<?php esc_html_e( 'Purchase coins to unlock the features your account has access to. Coins never expire and are added as soon as your payment is confirmed.', 'madara-coin-rewards' ); ?>
			</p>

			<?php if ( $mcr_is_logged_in ) : ?>
				<div class="mcr-buy__balance">
					<span class="mcr-buy__balance-label"><?php esc_html_e( 'Your balance', 'madara-coin-rewards' ); ?></span>
					<?php echo \MCR\Templates\Templates::amount( $mcr_balance, 'lg', array( 'data-mcr-balance' => '1' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>

				<div class="mcr-buy__hero-actions">
					<?php if ( $mcr_can_checkout && $mcr_has_any_package ) : ?>
						<a class="mcr-btn mcr-btn--primary mcr-btn--lg" href="#mcr-buy-packages">
							<?php esc_html_e( 'See coin packages', 'madara-coin-rewards' ); ?>
						</a>
					<?php else : ?>
						<a class="mcr-btn mcr-btn--primary mcr-btn--lg" href="<?php echo esc_url( $mcr_checkin_url ); ?>">
							<?php esc_html_e( 'Earn free coins', 'madara-coin-rewards' ); ?>
						</a>
					<?php endif; ?>
					<?php if ( $mcr_txn_url ) : ?>
						<a class="mcr-btn mcr-btn--ghost mcr-btn--lg" href="<?php echo esc_url( $mcr_txn_url ); ?>">
							<?php esc_html_e( 'Purchase history', 'madara-coin-rewards' ); ?>
						</a>
					<?php endif; ?>
				</div>
			<?php else : ?>
				<div class="mcr-buy__hero-actions">
					<a class="mcr-btn mcr-btn--primary mcr-btn--lg" href="<?php echo esc_url( $mcr_login_url ); ?>">
						<?php esc_html_e( 'Log in to buy coins', 'madara-coin-rewards' ); ?>
					</a>
				</div>
				<p class="mcr-buy__hero-note">
					<?php esc_html_e( 'You need an account to purchase coins and keep your purchase history.', 'madara-coin-rewards' ); ?>
				</p>
			<?php endif; ?>
		</div>

		<div class="mcr-buy__hero-art" aria-hidden="true">
			<div class="mcr-buy__coin-stack">
				<?php echo \MCR\Templates\Templates::coin_icon( 220, 'mcr-buy__coin mcr-buy__coin--back' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php echo \MCR\Templates\Templates::coin_icon( 180, 'mcr-buy__coin mcr-buy__coin--mid' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php echo \MCR\Templates\Templates::coin_icon( 140, 'mcr-buy__coin mcr-buy__coin--front' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</div>
	</div>
</section>

<?php /* ============ 2. PAYMENT STATE BANNER ============ */ ?>
<?php if ( ! empty( $mcr_notice['title'] ) || ! empty( $mcr_notice['text'] ) ) : ?>
	<section class="mcr-section mcr-section--flush" aria-label="<?php esc_attr_e( 'Payment status', 'madara-coin-rewards' ); ?>">
		<?php
		echo \MCR\Templates\Templates::part( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			'notice',
			array(
				'type'  => isset( $mcr_notice['type'] ) ? (string) $mcr_notice['type'] : 'info',
				'title' => isset( $mcr_notice['title'] ) ? (string) $mcr_notice['title'] : '',
				'text'  => isset( $mcr_notice['text'] ) ? (string) $mcr_notice['text'] : '',
			)
		);
		?>
	</section>
<?php endif; ?>

<?php if ( $mcr_pending ) : ?>
	<section class="mcr-section mcr-section--flush" aria-label="<?php esc_attr_e( 'Pending payment', 'madara-coin-rewards' ); ?>">
		<?php
		echo \MCR\Templates\Templates::part( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			'notice',
			array(
				'type'  => 'info',
				'title' => __( 'Awaiting payment confirmation', 'madara-coin-rewards' ),
				'text'  => __( 'We will add your coins as soon as the payment is confirmed.', 'madara-coin-rewards' ),
			)
		);
		?>
	</section>
<?php endif; ?>

<?php /* ============ 3. PACKAGES ============ */ ?>
<section class="mcr-section" id="mcr-buy-packages" aria-labelledby="mcr-buy-packages-title">
	<header class="mcr-section__head">
		<h2 class="mcr-section__title font-heading" id="mcr-buy-packages-title">
			<?php esc_html_e( 'Choose your package', 'madara-coin-rewards' ); ?>
		</h2>
		<?php if ( $mcr_sellable ) : ?>
			<span class="mcr-muted"><?php esc_html_e( 'One-time purchase. No subscription.', 'madara-coin-rewards' ); ?></span>
		<?php endif; ?>
	</header>

	<?php if ( ! $mcr_enabled ) : ?>
		<div id="mcr-purchase-disabled" class="mcr-buy__state">
			<?php
			echo \MCR\Templates\Templates::part( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				'notice',
				array(
					'type'  => 'info',
					'title' => __( 'Coin purchases are currently disabled.', 'madara-coin-rewards' ),
					'text'  => __( 'You can still earn coins with a daily check-in or by watching ads.', 'madara-coin-rewards' ),
				)
			);
			?>
		</div>
	<?php elseif ( ! $mcr_gateway ) : ?>
		<div id="mcr-payments-alert" class="mcr-buy__state">
			<?php
			echo \MCR\Templates\Templates::part( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				'notice',
				array(
					'type'  => 'warning',
					'title' => __( 'Checkout is unavailable', 'madara-coin-rewards' ),
					'text'  => __( 'No payment method is configured right now, so coins cannot be purchased. Your balance and history are unaffected.', 'madara-coin-rewards' ),
				)
			);
			?>
		</div>
	<?php endif; ?>

	<?php if ( ! $mcr_has_any_package ) : ?>
		<?php
		$mcr_empty_actions = array();

		$mcr_packages_url = ! empty( $mcr_urls['packages'] )
			? (string) $mcr_urls['packages']
			: (string) $mcr_urls['settings'];

		if ( current_user_can( 'manage_options' ) && '' !== $mcr_packages_url ) {
			$mcr_empty_actions[] = array(
				'label' => __( 'Add a package', 'madara-coin-rewards' ),
				'url'   => $mcr_packages_url,
				'class' => 'mcr-btn mcr-btn--primary',
			);
		}

		if ( $mcr_checkin_url ) {
			$mcr_empty_actions[] = array(
				'label' => __( 'Earn free coins instead', 'madara-coin-rewards' ),
				'url'   => $mcr_checkin_url,
				'class' => 'mcr-btn mcr-btn--ghost',
			);
		}

		echo \MCR\Templates\Templates::part( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			'empty',
			array(
				'icon'    => '&#9679;',
				'title'   => __( 'No coin packages available yet', 'madara-coin-rewards' ),
				'text'    => __( 'Packages are set up by the site owner. As soon as they are available they will appear here, ready to buy.', 'madara-coin-rewards' ),
				'actions' => $mcr_empty_actions,
			)
		);
		?>
	<?php elseif ( empty( $mcr_sellable ) ) : ?>
		<?php
		echo \MCR\Templates\Templates::part( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			'notice',
			array(
				'type'  => 'warning',
				'title' => __( 'Packages are currently unavailable', 'madara-coin-rewards' ),
				'text'  => __( 'The packages below cannot be purchased at the moment. Please check back later.', 'madara-coin-rewards' ),
			)
		);
		?>
		<div class="<?php echo esc_attr( $mcr_grid_classes ); ?>" aria-disabled="true">
			<?php foreach ( $mcr_packages as $mcr_package ) : ?>
				<?php
				if ( ! is_array( $mcr_package ) || empty( $mcr_package['id'] ) ) {
					continue;
				}

				echo \MCR\Templates\Templates::part( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					'package',
					array(
						'package'   => $mcr_package,
						'featured'  => false,
						'available' => false,
						'disabled'  => true,
						'premium'   => true,
					)
				);
				?>
			<?php endforeach; ?>
		</div>
	<?php else : ?>
		<div class="<?php echo esc_attr( $mcr_grid_classes ); ?>">
			<?php foreach ( $mcr_packages as $mcr_package ) : ?>
				<?php
				if ( ! is_array( $mcr_package ) || empty( $mcr_package['id'] ) ) {
					continue;
				}

				$mcr_pkg_id     = (string) $mcr_package['id'];
				$mcr_pkg_sell   = Packages::is_purchasable( $mcr_package );
				$mcr_has_badge  = '' !== trim( (string) ( $mcr_package['badge'] ?? '' ) );
				$mcr_is_featured = $mcr_pkg_sell && ( $mcr_has_badge || $mcr_pkg_id === $mcr_best_value_id );
				$mcr_cta        = '';

				/*
				 * One clear label per state, derived from the backend:
				 * checkout ready, sold out / unavailable, or disabled.
				 */
				if ( ! $mcr_pkg_sell ) {
					$mcr_cta = __( 'Unavailable', 'madara-coin-rewards' );
				} elseif ( ! $mcr_is_logged_in ) {
					$mcr_cta = __( 'Log in to buy', 'madara-coin-rewards' );
				} else {
					$mcr_cta = sprintf(
						/* translators: %s: coin amount */
						__( 'Buy %s coins', 'madara-coin-rewards' ),
						number_format_i18n( (int) ( $mcr_package['coins_total'] ?? 0 ) )
					);
				}

				echo \MCR\Templates\Templates::part( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					'package',
					array(
						'package'      => $mcr_package,
						'featured'     => $mcr_is_featured,
						'available'    => $mcr_pkg_sell,
						'disabled'     => ! $mcr_pkg_sell || ! $mcr_can_checkout,
						'premium'      => true,
						'best_value'   => ( $mcr_pkg_sell && $mcr_pkg_id === $mcr_best_value_id ),
						'cta_label'    => $mcr_cta,
						'login_url'    => $mcr_login_url,
						'describedby'  => ! $mcr_pkg_sell ? 'mcr-purchase-unavailable' : ( $mcr_enabled && $mcr_gateway ? '' : 'mcr-payments-alert' ),
					)
				);
				?>
			<?php endforeach; ?>
		</div>

		<div data-mcr-buy-feedback></div>
	<?php endif; ?>
</section>

<?php /* ============ 4. WHY BUY COINS ============ */ ?>
<?php if ( $mcr_has_any_package ) : ?>
<section class="mcr-section" id="mcr-buy-why" aria-labelledby="mcr-buy-why-title">
	<header class="mcr-section__head">
		<h2 class="mcr-section__title font-heading" id="mcr-buy-why-title">
			<?php esc_html_e( 'Why buy coins?', 'madara-coin-rewards' ); ?>
		</h2>
	</header>

	<div class="mcr-buy__features">
		<div class="mcr-buy__feature">
			<span class="mcr-buy__feature-icon" aria-hidden="true">
				<?php echo \MCR\Templates\Templates::coin_icon( 34, 'mcr-coin mcr-coin--lg' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</span>
			<h3 class="mcr-buy__feature-title"><?php esc_html_e( 'They never expire', 'madara-coin-rewards' ); ?></h3>
			<p class="mcr-buy__feature-text">
				<?php esc_html_e( 'Coins stay on your account until you spend them. There is no expiry date and no subscription to remember.', 'madara-coin-rewards' ); ?>
			</p>
		</div>

		<div class="mcr-buy__feature">
			<span class="mcr-buy__feature-icon" aria-hidden="true">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" focusable="false">
					<path d="M12 2 4 6v6c0 5 3.4 8.7 8 10 4.6-1.3 8-5 8-10V6l-8-4Z"/>
					<path d="m9 12 2 2 4-4"/>
				</svg>
			</span>
			<h3 class="mcr-buy__feature-title"><?php esc_html_e( 'Paid for, kept forever', 'madara-coin-rewards' ); ?></h3>
			<p class="mcr-buy__feature-text">
				<?php esc_html_e( 'Purchased coins are recorded separately from coins you earn, so you can always see what you bought and what you won.', 'madara-coin-rewards' ); ?>
			</p>
		</div>

		<div class="mcr-buy__feature">
			<span class="mcr-buy__feature-icon" aria-hidden="true">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" focusable="false">
					<rect x="2" y="5" width="20" height="14" rx="2.5"/>
					<path d="M2 10h20M6 15h4"/>
				</svg>
			</span>
			<h3 class="mcr-buy__feature-title"><?php esc_html_e( 'One account, full history', 'madara-coin-rewards' ); ?></h3>
			<p class="mcr-buy__feature-text">
				<?php esc_html_e( 'Every purchase and every coin you earn is written to a permanent transaction history you can review at any time.', 'madara-coin-rewards' ); ?>
			</p>
		</div>
	</div>
</section>

<?php /* ============ 5. HOW IT WORKS ============ */ ?>
<section class="mcr-section" id="mcr-buy-steps" aria-labelledby="mcr-buy-steps-title">
	<header class="mcr-section__head">
		<h2 class="mcr-section__title font-heading" id="mcr-buy-steps-title">
			<?php esc_html_e( 'How it works', 'madara-coin-rewards' ); ?>
		</h2>
	</header>

	<ol class="mcr-buy__steps">
		<li class="mcr-buy__step">
			<span class="mcr-buy__step-num" aria-hidden="true">1</span>
			<div>
				<h3 class="mcr-buy__step-title"><?php esc_html_e( 'Choose a package', 'madara-coin-rewards' ); ?></h3>
				<p class="mcr-buy__step-text">
					<?php esc_html_e( 'Pick the coin amount that suits you. Each card shows the exact price and what you receive.', 'madara-coin-rewards' ); ?>
				</p>
			</div>
		</li>
		<li class="mcr-buy__step">
			<span class="mcr-buy__step-num" aria-hidden="true">2</span>
			<div>
				<h3 class="mcr-buy__step-title"><?php esc_html_e( 'Complete the payment', 'madara-coin-rewards' ); ?></h3>
				<p class="mcr-buy__step-text">
					<?php esc_html_e( 'You are taken to a secure checkout to pay. If you cancel, you are not charged.', 'madara-coin-rewards' ); ?>
				</p>
			</div>
		</li>
		<li class="mcr-buy__step">
			<span class="mcr-buy__step-num" aria-hidden="true">3</span>
			<div>
				<h3 class="mcr-buy__step-title"><?php esc_html_e( 'Coins are added', 'madara-coin-rewards' ); ?></h3>
				<p class="mcr-buy__step-text">
					<?php esc_html_e( 'Your coins are credited only after the payment provider confirms the payment to us - never before.', 'madara-coin-rewards' ); ?>
				</p>
			</div>
		</li>
	</ol>
</section>
<?php endif; ?>

<?php /* ============ 6. SECURE PAYMENTS (only what is really enabled) ============ */ ?>
<?php if ( $mcr_gateways ) : ?>
<section class="mcr-section" id="mcr-buy-gateways" aria-labelledby="mcr-buy-gateways-title">
	<header class="mcr-section__head">
		<h2 class="mcr-section__title font-heading" id="mcr-buy-gateways-title">
			<?php esc_html_e( 'Secure payments', 'madara-coin-rewards' ); ?>
		</h2>
	</header>

	<div class="mcr-card mcr-card--muted">
		<div class="mcr-card__body">
			<div class="mcr-buy__gateways">
				<?php foreach ( $mcr_gateways as $mcr_gateway_item ) : ?>
					<div class="mcr-buy__gateway">
						<span class="mcr-buy__gateway-icon" aria-hidden="true">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" focusable="false">
								<rect x="4" y="10" width="16" height="10" rx="2"/>
								<path d="M8 10V7a4 4 0 0 1 8 0v3"/>
							</svg>
						</span>
						<div>
							<h3 class="mcr-buy__gateway-title">
								<?php echo esc_html( (string) ( $mcr_gateway_item['title'] ?? '' ) ); ?>
							</h3>
							<?php if ( ! empty( $mcr_gateway_item['description'] ) ) : ?>
								<p class="mcr-buy__gateway-text"><?php echo esc_html( (string) $mcr_gateway_item['description'] ); ?></p>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<p class="mcr-muted mcr-buy__secure-note">
				<?php esc_html_e( 'Payment details are entered on the payment provider\'s own secure page. This site never sees or stores your card number.', 'madara-coin-rewards' ); ?>
			</p>
		</div>
	</div>
</section>
<?php endif; ?>

<?php /* ============ 7. PURCHASE HISTORY (real orders only) ============ */ ?>
<?php if ( $mcr_is_logged_in && ! empty( $mcr_recent_orders ) ) : ?>
<section class="mcr-section" id="mcr-buy-history" aria-labelledby="mcr-buy-history-title">
	<header class="mcr-section__head">
		<h2 class="mcr-section__title font-heading" id="mcr-buy-history-title">
			<?php esc_html_e( 'Your recent purchases', 'madara-coin-rewards' ); ?>
		</h2>
		<?php if ( $mcr_txn_url ) : ?>
			<a class="mcr-section__action" href="<?php echo esc_url( $mcr_txn_url ); ?>">
				<?php esc_html_e( 'View all', 'madara-coin-rewards' ); ?>
			</a>
		<?php endif; ?>
	</header>

	<div class="mcr-card">
		<div class="mcr-card__body">
			<ul class="mcr-buy__orders">
				<?php foreach ( array_slice( $mcr_recent_orders, 0, 5 ) as $mcr_order ) : ?>
					<?php
					$mcr_o_status = isset( $mcr_order['status'] ) ? (string) $mcr_order['status'] : '';
					$mcr_o_class  = 'mcr-buy__order mcr-buy__order--' . sanitize_html_class( $mcr_o_status );

					/*
					 * `credited` is the terminal success state the gateway
					 * writes once the coins have actually been granted, so a
					 * finished purchase must never read as "Pending".
					 */
					$mcr_o_done = in_array( $mcr_o_status, array( 'paid', 'completed', 'credited' ), true );

					if ( $mcr_o_done ) {
						$mcr_o_label = __( 'Completed', 'madara-coin-rewards' );
					} elseif ( 'refunded' === $mcr_o_status ) {
						$mcr_o_label = __( 'Refunded', 'madara-coin-rewards' );
					} elseif ( 'failed' === $mcr_o_status ) {
						$mcr_o_label = __( 'Failed', 'madara-coin-rewards' );
					} elseif ( 'expired' === $mcr_o_status ) {
						$mcr_o_label = __( 'Expired', 'madara-coin-rewards' );
					} elseif ( 'cancelled' === $mcr_o_status ) {
						$mcr_o_label = __( 'Cancelled', 'madara-coin-rewards' );
					} else {
						$mcr_o_label = __( 'Pending', 'madara-coin-rewards' );
					}

					$mcr_o_coins = isset( $mcr_order['coins_total'] ) ? (int) $mcr_order['coins_total'] : 0;
					?>
					<li class="<?php echo esc_attr( $mcr_o_class ); ?>">
						<div class="mcr-buy__order-main">
							<span class="mcr-buy__order-title">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %s: coin amount */
										__( '%s coins', 'madara-coin-rewards' ),
										number_format_i18n( $mcr_o_coins )
									)
								);
								?>
							</span>
							<span class="mcr-buy__order-date">
								<?php
								$mcr_o_date = isset( $mcr_order['created_at'] ) ? (string) $mcr_order['created_at'] : '';

								echo esc_html(
									$mcr_o_date
										? mysql2date( get_option( 'date_format' ), $mcr_o_date )
										: ''
								);
								?>
							</span>
						</div>
						<span class="mcr-badge mcr-badge--<?php echo esc_attr( $mcr_o_done ? 'success' : ( in_array( $mcr_o_status, array( 'failed', 'refunded' ), true ) ? 'danger' : 'muted' ) ); ?>">
							<?php echo esc_html( $mcr_o_label ); ?>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>
</section>
<?php endif; ?>

<?php /* ============ 8. FAQ ============ */ ?>
<section class="mcr-section" id="mcr-buy-faq" aria-labelledby="mcr-buy-faq-title">
	<header class="mcr-section__head">
		<h2 class="mcr-section__title font-heading" id="mcr-buy-faq-title">
			<?php esc_html_e( 'Questions', 'madara-coin-rewards' ); ?>
		</h2>
	</header>

	<div class="mcr-card mcr-card--muted mcr-buy__faq">
		<details class="mcr-buy__faq-item">
			<summary class="mcr-buy__faq-q">
				<?php esc_html_e( 'When do I get my coins?', 'madara-coin-rewards' ); ?>
				<span class="mcr-buy__faq-icon" aria-hidden="true"></span>
			</summary>
			<div class="mcr-buy__faq-a">
				<p class="mcr-muted">
					<?php esc_html_e( 'Coins are added only after the payment provider confirms the payment to us. That usually takes a few seconds. You can always check the Coin History page for the latest entries.', 'madara-coin-rewards' ); ?>
				</p>
			</div>
		</details>

		<details class="mcr-buy__faq-item">
			<summary class="mcr-buy__faq-q">
				<?php esc_html_e( 'What happens if I cancel or the payment fails?', 'madara-coin-rewards' ); ?>
				<span class="mcr-buy__faq-icon" aria-hidden="true"></span>
			</summary>
			<div class="mcr-buy__faq-a">
				<p class="mcr-muted">
					<?php esc_html_e( 'You are not charged and no coins are added. You can safely start the purchase again with the same or a different package.', 'madara-coin-rewards' ); ?>
				</p>
			</div>
		</details>

		<details class="mcr-buy__faq-item">
			<summary class="mcr-buy__faq-q">
				<?php esc_html_e( 'Do coins expire?', 'madara-coin-rewards' ); ?>
				<span class="mcr-buy__faq-icon" aria-hidden="true"></span>
			</summary>
			<div class="mcr-buy__faq-a">
				<p class="mcr-muted">
					<?php esc_html_e( 'Coins stay on your account until you spend them. There is no expiry date.', 'madara-coin-rewards' ); ?>
				</p>
			</div>
		</details>

		<details class="mcr-buy__faq-item">
			<summary class="mcr-buy__faq-q">
				<?php esc_html_e( 'Can I get a refund?', 'madara-coin-rewards' ); ?>
				<span class="mcr-buy__faq-icon" aria-hidden="true"></span>
			</summary>
			<div class="mcr-buy__faq-a">
				<p class="mcr-muted">
					<?php esc_html_e( 'Refunds follow the policy of the payment provider. If a payment was taken but your coins were not added, contact the site owner and we will investigate.', 'madara-coin-rewards' ); ?>
				</p>
			</div>
		</details>

		<details class="mcr-buy__faq-item">
			<summary class="mcr-buy__faq-q">
				<?php esc_html_e( 'Where can I see my transactions?', 'madara-coin-rewards' ); ?>
				<span class="mcr-buy__faq-icon" aria-hidden="true"></span>
			</summary>
			<div class="mcr-buy__faq-a">
				<p class="mcr-muted">
					<?php esc_html_e( 'Every coin earned or spent is recorded in your Coin History, including the running balance after each transaction.', 'madara-coin-rewards' ); ?>
					<?php if ( $mcr_txn_url ) : ?>
						<br />
						<a class="mcr-link" href="<?php echo esc_url( $mcr_txn_url ); ?>">
							<?php esc_html_e( 'Open Coin History', 'madara-coin-rewards' ); ?>
						</a>
					<?php endif; ?>
				</p>
			</div>
		</details>
	</div>
</section>

<?php /* ============ 9. HELP ============ */ ?>
<?php
$mcr_help_url = isset( $args['support_url'] ) ? esc_url_raw( (string) $args['support_url'] ) : '';
?>
<?php if ( '' !== $mcr_help_url ) : ?>
<section class="mcr-section" id="mcr-buy-help" aria-labelledby="mcr-buy-help-title">
	<div class="mcr-buy__help">
		<div>
			<h2 class="mcr-buy__help-title" id="mcr-buy-help-title">
				<?php esc_html_e( 'Need help?', 'madara-coin-rewards' ); ?>
			</h2>
			<p class="mcr-muted">
				<?php esc_html_e( 'Something not working as expected? Get in touch and we will take a look.', 'madara-coin-rewards' ); ?>
			</p>
		</div>
		<a class="mcr-btn mcr-btn--ghost mcr-btn--lg" href="<?php echo esc_url( $mcr_help_url ); ?>">
			<?php esc_html_e( 'Contact support', 'madara-coin-rewards' ); ?>
		</a>
	</div>
</section>
<?php endif; ?>