<?php
/**
 * The "Watch ads & earn coins" panel.
 *
 * Rendered by the daily check-in page and by the standalone ads page, so
 * there is one source of truth for the ad UI.
 *
 * The panel never offers a button that cannot pay. Without a configured
 * provider nothing can verify that an ad finished, so the unavailable
 * state is explained instead of showing a button that would fail.
 *
 * @package MCR
 *
 * @var array $args Partial arguments:
 * @var bool  $args['enabled']       Whether ad rewards are switched on.
 * @var bool  $args['configured']    Whether a provider is able to verify.
 * @var array $args['units']         The ad units the provider offers.
 * @var array $args['ads']           The ad statistics.
 * @var int   $args['coins_per_ad']  Coins awarded per verified ad.
 * @var int   $args['daily_limit']   The daily cap.
 * @var int   $args['cooldown']      Seconds between two ads.
 * @var string $args['reset_deadline'] When the daily cap resets.
 */

defined( 'ABSPATH' ) || exit;

$mcr_enabled     = ! empty( $args['enabled'] );
$mcr_configured  = ! empty( $args['configured'] );
$mcr_units       = isset( $args['units'] ) && is_array( $args['units'] ) ? $args['units'] : array();
$mcr_ads         = isset( $args['ads'] ) && is_array( $args['ads'] ) ? $args['ads'] : array();
$mcr_urls        = isset( $args['urls'] ) && is_array( $args['urls'] ) ? $args['urls'] : array();
$mcr_coins       = isset( $args['coins_per_ad'] ) ? max( 0, (int) $args['coins_per_ad'] ) : 0;
$mcr_limit       = isset( $args['daily_limit'] ) ? max( 0, (int) $args['daily_limit'] ) : 0;
$mcr_cooldown    = isset( $args['cooldown'] ) ? max( 0, (int) $args['cooldown'] ) : 0;
$mcr_reset       = isset( $args['reset_deadline'] ) ? (string) $args['reset_deadline'] : '';
$mcr_today       = isset( $mcr_ads['today'] ) ? max( 0, (int) $mcr_ads['today'] ) : 0;
$mcr_remaining   = isset( $mcr_ads['remaining'] ) ? max( 0, (int) $mcr_ads['remaining'] ) : max( 0, $mcr_limit - $mcr_today );
$mcr_capped      = $mcr_limit > 0 && $mcr_remaining < 1;

/*
 * A reward of zero can never be paid: the ledger rejects a zero
 * amount. A button that always fails is worse than no button, so the
 * panel explains the state instead of offering one.
 */
$mcr_no_reward = $mcr_coins <= 0;

/*
 * A daily limit of zero means UNLIMITED, so the panel says so instead
 * of "0 of 0 ads left today".
 */
$mcr_unlimited = $mcr_limit <= 0;

/*
 * The coins the ad sessions really paid today, not the count times the
 * current rate: the rate can be changed while the day is running.
 */
$mcr_earned = isset( $mcr_ads['coins_today'] )
	? max( 0, (int) $mcr_ads['coins_today'] )
	: $mcr_today * $mcr_coins;

/*
 * A broken provider must not be able to flood the page with buttons, so
 * the number of units is bounded.
 */
$mcr_units = array_slice( $mcr_units, 0, 20 );

$mcr_unit_label = '';

foreach ( $mcr_units as $mcr_unit ) {
	if ( is_array( $mcr_unit ) && ! empty( $mcr_unit['label'] ) ) {
		$mcr_unit_label = (string) $mcr_unit['label'];
		break;
	}
}
$mcr_txn_url     = isset( $mcr_urls['transactions'] ) ? (string) $mcr_urls['transactions'] : '';

/*
 * A provider that offers no unit is still usable: the default rewarded
 * video is offered instead, so the panel never renders empty.
 */
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
	<section class="mcr-section mcr-section--earn" id="mcr-ads-panel" aria-labelledby="mcr-ads-panel-title">
		<header class="mcr-section__head">
			<h2 class="mcr-section__title" id="mcr-ads-panel-title"><?php esc_html_e( 'Watch ads &amp; earn coins', 'madara-coin-rewards' ); ?></h2>
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
	<section class="mcr-section mcr-section--earn" id="mcr-ads-panel" aria-labelledby="mcr-ads-panel-title">
		<header class="mcr-section__head">
			<h2 class="mcr-section__title" id="mcr-ads-panel-title"><?php esc_html_e( 'Watch ads &amp; earn coins', 'madara-coin-rewards' ); ?></h2>
		</header>
		<div id="mcr-ads-not-configured">
			<?php
			echo \MCR\Templates\Templates::part( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				'notice',
				array(
					'type'  => 'info',
					'title' => __( 'Rewarded ads are not available yet', 'madara-coin-rewards' ),
					'text'  => __( 'This site has not connected an ad network yet, so no ad can be verified and no coins can be awarded here. Check back later, or earn coins with a daily check-in.', 'madara-coin-rewards' ),
				)
			);
			?>
		</div>
	</section>
<?php else : ?>
	<section class="mcr-section mcr-section--earn" id="mcr-ads-panel" aria-labelledby="mcr-ads-panel-title">
		<header class="mcr-section__head">
			<h2 class="mcr-section__title" id="mcr-ads-panel-title"><?php esc_html_e( 'Watch ads &amp; earn coins', 'madara-coin-rewards' ); ?></h2>
			<?php if ( $mcr_capped ) : ?>
				<span class="mcr-badge mcr-badge--warning"><?php esc_html_e( 'Daily limit reached', 'madara-coin-rewards' ); ?></span>
			<?php else : ?>
				<span class="mcr-badge mcr-badge--gold">
					<?php
					if ( $mcr_no_reward ) {
						esc_html_e( 'Not available', 'madara-coin-rewards' );
					} else {
						printf(
							/* translators: %s: coin amount */
							esc_html__( '+%s coins per ad', 'madara-coin-rewards' ),
							esc_html( number_format_i18n( $mcr_coins ) )
						);
					}
					?>
				</span>
			<?php endif; ?>
		</header>

		<div class="mcr-grid mcr-grid--split">
			<div class="mcr-card mcr-card--earn">
				<div class="mcr-card__header">
					<div>
						<h3 class="mcr-card__title">
							<?php
							echo esc_html(
								'' !== $mcr_unit_label
									? $mcr_unit_label
									: __( 'Earn by watching', 'madara-coin-rewards' )
							);
							?>
						</h3>
						<p class="mcr-card__meta">
							<?php
							if ( $mcr_unlimited ) {
								esc_html_e( 'Unlimited ads today', 'madara-coin-rewards' );
							} else {
								printf(
									/* translators: 1: ads remaining, 2: daily cap */
									esc_html__( '%1$s of %2$s ads left today', 'madara-coin-rewards' ),
									esc_html( number_format_i18n( $mcr_remaining ) ),
									esc_html( number_format_i18n( $mcr_limit ) )
								);
							}
							?>
						</p>
					</div>
				</div>

				<div class="mcr-card__body">
					<?php echo \MCR\Templates\Templates::amount( $mcr_coins, 'lg' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

					<?php if ( $mcr_limit > 0 ) : ?>
						<?php
						echo \MCR\Templates\Templates::part( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							'progress',
							array(
								'value'     => min( $mcr_today, $mcr_limit ),
								'max'       => $mcr_limit,
								'variant'   => 'gold',
								'aria_text' => sprintf(
									/* translators: 1: ads watched, 2: daily cap */
									__( '%1$s of %2$s ads watched today', 'madara-coin-rewards' ),
									number_format_i18n( $mcr_today ),
									number_format_i18n( $mcr_limit )
								),
								'left'      => sprintf(
									/* translators: %d: ads watched today */
									__( '%d watched today', 'madara-coin-rewards' ),
									(int) $mcr_today
								),
								'right'     => sprintf(
									/* translators: %d: ads remaining */
									__( '%d left', 'madara-coin-rewards' ),
									(int) $mcr_remaining
								),
							)
						);
						?>
					<?php endif; ?>

					<div class="mcr-row">
						<span class="mcr-muted"><?php esc_html_e( 'Earned from ads today', 'madara-coin-rewards' ); ?></span>
						<?php echo \MCR\Templates\Templates::amount( $mcr_earned, 'sm' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>

					<div class="mcr-row">
						<span class="mcr-muted"><?php esc_html_e( 'Daily limit resets in', 'madara-coin-rewards' ); ?></span>
						<?php echo \MCR\Templates\Templates::part( 'countdown', array( 'deadline' => $mcr_reset, 'small' => true ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>

					<div data-mcr-ad-feedback></div>
				</div>

				<div class="mcr-card__footer">
					<?php if ( $mcr_capped ) : ?>
						<button type="button" class="mcr-btn mcr-btn--block mcr-btn--quiet" disabled aria-disabled="true">
							<?php esc_html_e( 'Daily limit reached', 'madara-coin-rewards' ); ?>
						</button>
					<?php elseif ( $mcr_no_reward ) : ?>
						<p class="mcr-card__meta">
							<?php esc_html_e( 'Ad rewards are switched off: an ad is worth no coins, so no button is offered.', 'madara-coin-rewards' ); ?>
						</p>
					<?php else : ?>
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
							<button type="button" class="mcr-btn mcr-btn--block mcr-btn--gold" data-mcr-ad-start
								data-mcr-ad-unit="<?php echo esc_attr( $mcr_unit_id ); ?>"
								data-mcr-ad-coins="<?php echo esc_attr( $mcr_coins ); ?>"
								data-mcr-cooldown="<?php echo esc_attr( $mcr_cooldown ); ?>"
								title="<?php echo esc_attr( $mcr_unit_label ); ?>">
								<?php
								printf(
									/* translators: 1: ad unit label, 2: coin amount */
									esc_html__( 'Watch %1$s &amp; earn %2$s coins', 'madara-coin-rewards' ),
									esc_html( '' !== $mcr_unit_label ? $mcr_unit_label : __( 'ad', 'madara-coin-rewards' ) ),
									esc_html( number_format_i18n( $mcr_coins ) )
								);
								?>
							</button>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			</div>

			<div class="mcr-card mcr-card--muted">
				<div class="mcr-card__header">
					<h3 class="mcr-card__title"><?php esc_html_e( 'How ad rewards work', 'madara-coin-rewards' ); ?></h3>
				</div>
				<div class="mcr-card__body">
					<ul class="mcr-package__features">
						<li>
							<?php
							printf(
								/* translators: %s: coin amount */
								esc_html__( 'Every verified ad adds %s coins to your balance.', 'madara-coin-rewards' ),
								esc_html( number_format_i18n( $mcr_coins ) )
							);
							?>
						</li>
						<li>
							<?php
							if ( $mcr_unlimited ) {
								esc_html_e( 'There is no limit on the ads you can watch each day.', 'madara-coin-rewards' );
							} else {
								printf(
									/* translators: %s: daily cap */
									esc_html__( 'You can watch up to %s ads per day.', 'madara-coin-rewards' ),
									esc_html( number_format_i18n( $mcr_limit ) )
								);
							}
							?>
						</li>
						<li>
							<?php
							printf(
								/* translators: %s: cooldown seconds */
								esc_html__( 'Wait %s seconds between two ads.', 'madara-coin-rewards' ),
								esc_html( number_format_i18n( $mcr_cooldown ) )
							);
							?>
						</li>
						<li><?php esc_html_e( 'An ad has to be watched to the end. The ad network confirms it before any coins are added.', 'madara-coin-rewards' ); ?></li>
					</ul>

					<?php if ( '' !== $mcr_txn_url ) : ?>
						<a class="mcr-btn mcr-btn--sm mcr-btn--quiet" href="<?php echo esc_url( $mcr_txn_url ); ?>"><?php esc_html_e( 'View my ad rewards', 'madara-coin-rewards' ); ?></a>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</section>
<?php endif;
