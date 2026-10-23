<?php
/**
 * Watch ads page.
 *
 * A reward button is only rendered when a provider is configured: without a
 * provider nothing can verify that an ad finished, so the page explains the
 * state instead of offering a button that cannot pay.
 *
 * @package MCR
 *
 * @var array $args Template arguments.
 */

defined( 'ABSPATH' ) || exit;

$mcr_enabled     = ! empty( $args['enabled'] );
$mcr_configured  = ! empty( $args['configured'] );
$mcr_units       = isset( $args['ad_units'] ) && is_array( $args['ad_units'] ) ? $args['ad_units'] : array();
$mcr_ads         = isset( $args['ads'] ) && is_array( $args['ads'] ) ? $args['ads'] : array();
$mcr_recent      = isset( $args['recent'] ) && is_array( $args['recent'] ) ? $args['recent'] : array();
$mcr_urls        = isset( $args['urls'] ) && is_array( $args['urls'] ) ? $args['urls'] : array();
$mcr_coins       = isset( $args['coins_per_ad'] ) ? max( 0, (int) $args['coins_per_ad'] ) : 0;
$mcr_limit       = isset( $args['daily_limit'] ) ? max( 0, (int) $args['daily_limit'] ) : 0;
$mcr_cooldown    = isset( $args['cooldown'] ) ? max( 0, (int) $args['cooldown'] ) : 0;
$mcr_today       = isset( $mcr_ads['today'] ) ? max( 0, (int) $mcr_ads['today'] ) : 0;
$mcr_remaining   = isset( $mcr_ads['remaining'] ) ? max( 0, (int) $mcr_ads['remaining'] ) : max( 0, $mcr_limit - $mcr_today );
$mcr_reset       = isset( $args['reset_deadline'] ) ? (string) $args['reset_deadline'] : '';
$mcr_txn_url     = isset( $mcr_urls['transactions'] ) ? (string) $mcr_urls['transactions'] : '';
$mcr_capped      = $mcr_limit > 0 && $mcr_remaining < 1;

if ( $mcr_enabled && $mcr_configured && empty( $mcr_units ) ) {
	$mcr_units = array(
		array(
			'id'    => 'rewarded_video',
			'label' => __( 'Rewarded video', 'madara-coin-rewards' ),
		),
	);
}
?>
<?php if ( ! $mcr_enabled ) : ?>
	<section class="mcr-section" id="mcr-ads-status" aria-labelledby="mcr-ads-status-title">
		<header class="mcr-section__head">
			<h2 class="mcr-section__title font-heading" id="mcr-ads-status-title"><?php esc_html_e( 'Watch ads', 'madara-coin-rewards' ); ?></h2>
		</header>
		<?php
		echo \MCR\Templates\Templates::part( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			'notice',
			array(
				'type'  => 'info',
				'title' => __( 'Ad rewards are currently disabled.', 'madara-coin-rewards' ),
				'text'  => __( 'You can still earn coins with a daily check-in or by purchasing a package.', 'madara-coin-rewards' ),
			)
		);
		?>
	</section>
<?php elseif ( ! $mcr_configured ) : ?>
	<section class="mcr-section" id="mcr-ads-status" aria-labelledby="mcr-ads-status-title">
		<header class="mcr-section__head">
			<h2 class="mcr-section__title font-heading" id="mcr-ads-status-title"><?php esc_html_e( 'Watch ads', 'madara-coin-rewards' ); ?></h2>
		</header>
		<div id="mcr-ads-not-configured">
			<?php
			echo \MCR\Templates\Templates::part( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				'notice',
				array(
					'type'  => 'info',
					'title' => __( 'Ad rewards are not available', 'madara-coin-rewards' ),
					'text'  => __( 'Rewarded ads are not configured on this site yet.', 'madara-coin-rewards' ),
				)
			);
			?>
		</div>
	</section>
<?php else : ?>
	<section class="mcr-section" id="mcr-ads-status" aria-labelledby="mcr-ads-status-title">
		<header class="mcr-section__head">
			<h2 class="mcr-section__title font-heading" id="mcr-ads-status-title"><?php esc_html_e( 'Watch ads', 'madara-coin-rewards' ); ?></h2>
			<?php if ( $mcr_capped ) : ?>
				<span class="mcr-badge mcr-badge--warning"><?php esc_html_e( 'Daily limit reached', 'madara-coin-rewards' ); ?></span>
			<?php endif; ?>
		</header>

		<div class="mcr-card">
			<div class="mcr-card__body">
				<p class="mcr-strong">
					<?php
					printf(
						/* translators: 1: ads watched today, 2: daily cap */
						esc_html__( 'Today\'s ads: %1$d of %2$d', 'madara-coin-rewards' ),
						(int) $mcr_today,
						(int) $mcr_limit
					);
					?>
				</p>

				<?php if ( $mcr_limit > 0 ) : ?>
					<?php
					echo \MCR\Templates\Templates::part( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						'progress',
						array(
							'value'   => min( $mcr_today, $mcr_limit ),
							'max'     => $mcr_limit,
							'variant' => 'gold',
							'left'    => sprintf(
								/* translators: %d: ads watched */
								__( '%d watched', 'madara-coin-rewards' ),
								(int) $mcr_today
							),
							'right'   => sprintf(
								/* translators: %d: ads remaining */
								__( '%d left', 'madara-coin-rewards' ),
								(int) $mcr_remaining
							),
						)
					);
					?>
				<?php endif; ?>

				<div class="mcr-row">
					<span class="mcr-muted"><?php esc_html_e( 'Resets in', 'madara-coin-rewards' ); ?></span>
					<?php echo \MCR\Templates\Templates::part( 'countdown', array( 'deadline' => $mcr_reset, 'small' => true ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>

				<?php if ( $mcr_capped ) : ?>
					<p class="mcr-card__meta"><?php esc_html_e( 'Come back tomorrow.', 'madara-coin-rewards' ); ?></p>
				<?php else : ?>
					<p class="mcr-card__meta">
						<?php
						printf(
							/* translators: 1: coin amount, 2: cooldown seconds */
							esc_html__( 'Each verified ad adds %1$s coins. Wait %2$s seconds between ads.', 'madara-coin-rewards' ),
							esc_html( number_format_i18n( $mcr_coins ) ),
							esc_html( number_format_i18n( $mcr_cooldown ) )
						);
						?>
					</p>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<section class="mcr-section" id="mcr-ads-providers" aria-labelledby="mcr-ads-providers-title">
		<header class="mcr-section__head">
			<h2 class="mcr-section__title font-heading" id="mcr-ads-providers-title"><?php esc_html_e( 'Available ads', 'madara-coin-rewards' ); ?></h2>
		</header>

		<div class="mcr-grid mcr-grid--cards">
			<?php foreach ( $mcr_units as $mcr_unit ) : ?>
				<?php
				if ( ! is_array( $mcr_unit ) || empty( $mcr_unit['id'] ) ) {
					continue;
				}

				$mcr_unit_id    = sanitize_key( (string) $mcr_unit['id'] );
				$mcr_unit_label = isset( $mcr_unit['label'] ) && '' !== (string) $mcr_unit['label']
					? (string) $mcr_unit['label']
					: __( 'Rewarded video', 'madara-coin-rewards' );
				?>
				<article class="mcr-card" aria-labelledby="mcr-ad-<?php echo esc_attr( $mcr_unit_id ); ?>-title">
					<div class="mcr-card__header">
						<h3 class="mcr-card__title" id="mcr-ad-<?php echo esc_attr( $mcr_unit_id ); ?>-title"><?php echo esc_html( $mcr_unit_label ); ?></h3>
						<?php if ( $mcr_capped ) : ?>
							<span class="mcr-badge mcr-badge--warning"><?php esc_html_e( 'Daily limit reached', 'madara-coin-rewards' ); ?></span>
						<?php else : ?>
							<span class="mcr-badge mcr-badge--gold">
								<?php
								printf(
									/* translators: %s: coin amount */
									esc_html__( '+%s coins', 'madara-coin-rewards' ),
									esc_html( number_format_i18n( $mcr_coins ) )
								);
								?>
							</span>
						<?php endif; ?>
					</div>

					<div class="mcr-card__body">
						<?php echo \MCR\Templates\Templates::amount( $mcr_coins, 'lg' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<p class="mcr-card__meta">
							<?php
							printf(
								/* translators: 1: ads remaining, 2: daily cap */
								esc_html__( '%1$s of %2$s ads remaining today', 'madara-coin-rewards' ),
								esc_html( number_format_i18n( $mcr_remaining ) ),
								esc_html( number_format_i18n( $mcr_limit ) )
							);
							?>
						</p>
						<p class="mcr-card__meta">
							<?php
							printf(
								/* translators: %s: cooldown seconds */
								esc_html__( 'Next ad in %s seconds after a reward.', 'madara-coin-rewards' ),
								esc_html( number_format_i18n( $mcr_cooldown ) )
							);
							?>
						</p>
						<div data-mcr-ad-feedback></div>
					</div>

					<div class="mcr-card__footer">
						<?php if ( $mcr_capped ) : ?>
							<button type="button" class="mcr-btn mcr-btn--block" disabled aria-disabled="true"><?php esc_html_e( 'Daily limit reached', 'madara-coin-rewards' ); ?></button>
						<?php else : ?>
							<button type="button" class="mcr-btn mcr-btn--block" data-mcr-ad-start
								data-mcr-ad-unit="<?php echo esc_attr( $mcr_unit_id ); ?>"
								data-mcr-ad-coins="<?php echo esc_attr( $mcr_coins ); ?>"
								data-mcr-cooldown="<?php echo esc_attr( $mcr_cooldown ); ?>">
								<?php
								printf(
									/* translators: %s: coin amount */
									esc_html__( 'Watch ad (+%s)', 'madara-coin-rewards' ),
									esc_html( number_format_i18n( $mcr_coins ) )
								);
								?>
							</button>
						<?php endif; ?>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	</section>

	<section class="mcr-section" id="mcr-ads-limits" aria-labelledby="mcr-ads-limits-title">
		<header class="mcr-section__head">
			<h2 class="mcr-section__title font-heading" id="mcr-ads-limits-title"><?php esc_html_e( 'Limits', 'madara-coin-rewards' ); ?></h2>
		</header>
		<div class="mcr-card mcr-card--muted">
			<ul class="mcr-package__features">
				<li>
					<?php
					printf(
						/* translators: %s: daily cap */
						esc_html__( 'Daily cap: %s ads.', 'madara-coin-rewards' ),
						esc_html( number_format_i18n( $mcr_limit ) )
					);
					?>
				</li>
				<li>
					<?php
					printf(
						/* translators: %s: cooldown seconds */
						esc_html__( '%s seconds between ads.', 'madara-coin-rewards' ),
						esc_html( number_format_i18n( $mcr_cooldown ) )
					);
					?>
				</li>
				<li><?php esc_html_e( 'Ads must be watched to the end. Rewards are confirmed by the ad provider before coins are added.', 'madara-coin-rewards' ); ?></li>
			</ul>
		</div>
	</section>
<?php endif; ?>

<section class="mcr-section" id="mcr-ads-recent" aria-labelledby="mcr-ads-recent-title">
	<header class="mcr-section__head">
		<h2 class="mcr-section__title font-heading" id="mcr-ads-recent-title"><?php esc_html_e( 'Recent ad rewards', 'madara-coin-rewards' ); ?></h2>
		<a class="mcr-section__action" href="<?php echo esc_url( $mcr_txn_url ); ?>"><?php esc_html_e( 'View all', 'madara-coin-rewards' ); ?></a>
	</header>

	<div class="mcr-card">
		<?php if ( empty( $mcr_recent ) ) : ?>
			<?php
			echo \MCR\Templates\Templates::part( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				'empty',
				array(
					'icon'  => '&#9654;',
					'title' => __( 'No ad rewards yet', 'madara-coin-rewards' ),
					'text'  => __( 'Rewards from ads will be listed here.', 'madara-coin-rewards' ),
					'bare'  => true,
				)
			);
			?>
		<?php else : ?>
			<ul class="mcr-txn-list">
				<?php foreach ( $mcr_recent as $mcr_txn ) : ?>
					<?php echo \MCR\Templates\Templates::part( 'txn', array( 'txn' => $mcr_txn ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
</section>
