<?php
/**
 * Buy coins page.
 *
 * Prices are display-only. The order is created server-side, which snapshots
 * the amount, and the coins are credited by the payment webhook only. Every
 * call to action is therefore either a real checkout start or a disabled
 * control explained by an alert - never a button that pretends to pay.
 *
 * @package MCR
 *
 * @var array $args Template arguments.
 */

defined( 'ABSPATH' ) || exit;

$mcr_packages  = isset( $args['packages'] ) && is_array( $args['packages'] ) ? $args['packages'] : array();
$mcr_stats     = isset( $args['stats'] ) && is_array( $args['stats'] ) ? $args['stats'] : array();
$mcr_urls      = isset( $args['urls'] ) && is_array( $args['urls'] ) ? $args['urls'] : array();
$mcr_notice    = isset( $args['notice'] ) && is_array( $args['notice'] ) ? $args['notice'] : array();
$mcr_gateway   = ! empty( $args['gateway_available'] );
$mcr_pending   = ! empty( $args['pending_order'] );
$mcr_enabled   = ! isset( $args['enabled'] ) || $args['enabled'];
$mcr_balance   = isset( $mcr_stats['balance'] ) ? (int) $mcr_stats['balance'] : 0;
$mcr_columns   = isset( $args['columns'] ) ? max( 1, min( 4, (int) $args['columns'] ) ) : 3;
$mcr_show_balance = ! isset( $args['show_balance'] ) || $args['show_balance'];
$mcr_txn_url   = isset( $mcr_urls['transactions'] ) ? (string) $mcr_urls['transactions'] : '';
$mcr_toggle    = ! $mcr_enabled ? 'disabled' : ( $mcr_gateway ? '' : 'disabled' );
$mcr_described = ! $mcr_enabled ? 'mcr-purchase-disabled' : ( $mcr_gateway ? '' : 'mcr-payments-alert' );

$mcr_grid_classes = 'mcr-grid mcr-grid--packages';

if ( $mcr_columns > 1 ) {
	$mcr_grid_classes .= ' mcr-grid--cols-' . $mcr_columns;
}
?>
<?php if ( ! empty( $mcr_notice['title'] ) || ! empty( $mcr_notice['text'] ) ) : ?>
	<section class="mcr-section" aria-label="<?php esc_attr_e( 'Payment status', 'madara-coin-rewards' ); ?>">
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

<?php if ( $mcr_show_balance ) : ?>
	<section class="mcr-section" id="mcr-buy-balance" aria-labelledby="mcr-buy-balance-title">
		<div class="mcr-card">
			<div class="mcr-card__header">
				<h2 class="mcr-card__title" id="mcr-buy-balance-title"><?php esc_html_e( 'Your balance', 'madara-coin-rewards' ); ?></h2>
				<a class="mcr-section__action" href="<?php echo esc_url( $mcr_txn_url ); ?>"><?php esc_html_e( 'History', 'madara-coin-rewards' ); ?></a>
			</div>
			<div class="mcr-card__body">
				<?php echo \MCR\Templates\Templates::amount( $mcr_balance, 'lg', array( 'data-mcr-balance' => '1' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<p class="mcr-card__meta"><?php esc_html_e( 'Coins are added as soon as payment is confirmed.', 'madara-coin-rewards' ); ?></p>
			</div>
		</div>
	</section>
<?php endif; ?>

<?php if ( $mcr_pending ) : ?>
	<section class="mcr-section" aria-label="<?php esc_attr_e( 'Pending payment', 'madara-coin-rewards' ); ?>">
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

<section class="mcr-section" id="mcr-buy-packages" aria-labelledby="mcr-buy-packages-title">
	<header class="mcr-section__head">
		<h2 class="mcr-section__title font-heading" id="mcr-buy-packages-title"><?php esc_html_e( 'Coin packages', 'madara-coin-rewards' ); ?></h2>
		<?php if ( ! empty( $mcr_packages ) ) : ?>
			<span class="mcr-muted"><?php esc_html_e( 'One-time purchase. No subscription.', 'madara-coin-rewards' ); ?></span>
		<?php endif; ?>
	</header>

	<?php if ( ! $mcr_enabled ) : ?>
		<div id="mcr-purchase-disabled">
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
		<div id="mcr-payments-alert">
			<?php
			echo \MCR\Templates\Templates::part( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				'notice',
				array(
					'type'  => 'warning',
					'title' => __( 'Payments are not configured', 'madara-coin-rewards' ),
					'text'  => __( 'No payment method is available right now, so coins cannot be purchased.', 'madara-coin-rewards' ),
				)
			);
			?>
		</div>
	<?php endif; ?>

	<?php if ( empty( $mcr_packages ) ) : ?>
		<?php
		$mcr_empty_actions = array();

		if ( current_user_can( 'manage_options' ) && ! empty( $mcr_urls['settings'] ) ) {
			$mcr_empty_actions[] = array(
				'label' => __( 'Add a package', 'madara-coin-rewards' ),
				'url'   => (string) $mcr_urls['settings'],
				'class' => 'mcr-btn mcr-btn--quiet',
			);
		}

		echo \MCR\Templates\Templates::part( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			'empty',
			array(
				'icon'    => '&#9679;',
				'title'   => __( 'No coin packages available', 'madara-coin-rewards' ),
				'text'    => __( 'Coin packages will appear here once the site owner adds them.', 'madara-coin-rewards' ),
				'actions' => $mcr_empty_actions,
			)
		);
		?>
	<?php else : ?>
		<div class="<?php echo esc_attr( $mcr_grid_classes ); ?>">
			<?php foreach ( $mcr_packages as $mcr_package ) : ?>
				<?php
				if ( ! is_array( $mcr_package ) || empty( $mcr_package['id'] ) ) {
					continue;
				}

				$mcr_featured = ! empty( $mcr_package['featured'] ) || ! empty( $mcr_package['popular'] );

				echo \MCR\Templates\Templates::part( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					'package',
					array(
						'package'     => $mcr_package,
						'featured'    => $mcr_featured,
						'disabled'    => '' !== $mcr_toggle,
						'describedby' => $mcr_described,
					)
				);
				?>
			<?php endforeach; ?>
		</div>

		<div data-mcr-buy-feedback></div>
	<?php endif; ?>
</section>

<section class="mcr-section" id="mcr-buy-gateways" aria-labelledby="mcr-buy-gateways-title">
	<header class="mcr-section__head">
		<h2 class="mcr-section__title font-heading" id="mcr-buy-gateways-title"><?php esc_html_e( 'Payment', 'madara-coin-rewards' ); ?></h2>
	</header>

	<div class="mcr-card mcr-card--muted">
		<div class="mcr-card__body">
			<?php if ( $mcr_enabled && $mcr_gateway ) : ?>
				<p class="mcr-muted"><?php esc_html_e( 'Payments are processed by our payment provider. You will be redirected to complete the payment.', 'madara-coin-rewards' ); ?></p>
				<p class="mcr-card__meta"><?php esc_html_e( 'Your coins are credited only after the payment is confirmed. If you cancel, you are not charged.', 'madara-coin-rewards' ); ?></p>
			<?php elseif ( ! $mcr_enabled ) : ?>
				<p class="mcr-muted"><?php esc_html_e( 'Purchases are paused, so no payment can be started right now.', 'madara-coin-rewards' ); ?></p>
			<?php else : ?>
				<p class="mcr-muted"><?php esc_html_e( 'A payment gateway must be enabled before coins can be purchased.', 'madara-coin-rewards' ); ?></p>
			<?php endif; ?>
		</div>
	</div>
</section>

<section class="mcr-section" id="mcr-buy-faq" aria-labelledby="mcr-buy-faq-title">
	<header class="mcr-section__head">
		<h2 class="mcr-section__title font-heading" id="mcr-buy-faq-title"><?php esc_html_e( 'Questions', 'madara-coin-rewards' ); ?></h2>
	</header>

	<div class="mcr-card mcr-card--muted">
		<details>
			<summary class="mcr-strong"><?php esc_html_e( 'When do I get my coins?', 'madara-coin-rewards' ); ?></summary>
			<p class="mcr-muted"><?php esc_html_e( 'Coins are added as soon as the payment provider confirms the payment. This usually takes a few seconds.', 'madara-coin-rewards' ); ?></p>
		</details>
		<details>
			<summary class="mcr-strong"><?php esc_html_e( 'Can I get a refund?', 'madara-coin-rewards' ); ?></summary>
			<p class="mcr-muted"><?php esc_html_e( 'Refunds follow the policy of the payment provider. Contact the site owner if a payment was taken but your coins were not added.', 'madara-coin-rewards' ); ?></p>
		</details>
		<details>
			<summary class="mcr-strong"><?php esc_html_e( 'Do coins expire?', 'madara-coin-rewards' ); ?></summary>
			<p class="mcr-muted"><?php esc_html_e( 'Coins stay on your account until you spend them. There is no expiry date.', 'madara-coin-rewards' ); ?></p>
		</details>
	</div>
</section>
