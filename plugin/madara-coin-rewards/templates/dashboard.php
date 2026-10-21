<?php
/**
 * Rewards dashboard - default (grid) layout.
 *
 * Every section is server-rendered and independent: a failed ad query never
 * blanks the balance or the ledger, because the controller already defaulted
 * each block separately.
 *
 * @package MCR
 *
 * @var array $args Template arguments.
 */

defined( 'ABSPATH' ) || exit;

$mcr_stats       = isset( $args['stats'] ) && is_array( $args['stats'] ) ? $args['stats'] : array();
$mcr_checkin     = isset( $args['checkin'] ) && is_array( $args['checkin'] ) ? $args['checkin'] : array();
$mcr_bonuses     = isset( $args['bonuses'] ) && is_array( $args['bonuses'] ) ? $args['bonuses'] : array();
$mcr_recent      = isset( $args['recent'] ) && is_array( $args['recent'] ) ? $args['recent'] : array();
$mcr_ads         = isset( $args['ads'] ) && is_array( $args['ads'] ) ? $args['ads'] : array();
$mcr_urls        = isset( $args['urls'] ) && is_array( $args['urls'] ) ? $args['urls'] : array();
$mcr_balance     = isset( $mcr_stats['balance'] ) ? (int) $mcr_stats['balance'] : 0;
$mcr_streak      = isset( $mcr_checkin['streak'] ) ? max( 0, (int) $mcr_checkin['streak'] ) : 0;
$mcr_today       = isset( $mcr_checkin['today_key'] ) ? (string) $mcr_checkin['today_key'] : gmdate( 'Y-m-d' );
$mcr_week        = \MCR\Templates\Templates::week(
	isset( $mcr_checkin['calendar'] ) && is_array( $mcr_checkin['calendar'] ) ? $mcr_checkin['calendar'] : array(),
	$mcr_today
);
$mcr_show_balance  = ! isset( $args['show_balance'] ) || $args['show_balance'];
$mcr_show_progress = ! isset( $args['show_progress'] ) || $args['show_progress'];
$mcr_checkin_on  = ! empty( $args['checkin_enabled'] );
$mcr_ads_on      = ! empty( $args['ads_enabled'] );
$mcr_purchase_on = ! empty( $args['purchase_enabled'] );
$mcr_ads_ready   = ! empty( $args['ads_configured'] );
$mcr_claimable   = ! empty( $args['claimable'] );
$mcr_next_bonus  = isset( $args['next_bonus'] ) && is_array( $args['next_bonus'] ) ? $args['next_bonus'] : array();

$mcr_checkin_url = isset( $mcr_urls['checkin'] ) ? (string) $mcr_urls['checkin'] : '';
$mcr_ads_url     = isset( $mcr_urls['ads'] ) ? (string) $mcr_urls['ads'] : '';
$mcr_buy_url     = isset( $mcr_urls['buy'] ) ? (string) $mcr_urls['buy'] : '';
$mcr_txn_url     = isset( $mcr_urls['transactions'] ) ? (string) $mcr_urls['transactions'] : '';

$mcr_coins_per_ad = isset( $mcr_ads['coins_per_ad'] ) ? max( 0, (int) $mcr_ads['coins_per_ad'] ) : 0;
$mcr_ads_today    = isset( $mcr_ads['today'] ) ? max( 0, (int) $mcr_ads['today'] ) : 0;
$mcr_ads_limit    = isset( $mcr_ads['limit'] ) ? max( 0, (int) $mcr_ads['limit'] ) : 0;
$mcr_ads_left     = isset( $mcr_ads['remaining'] ) ? max( 0, (int) $mcr_ads['remaining'] ) : max( 0, $mcr_ads_limit - $mcr_ads_today );
$mcr_claim_total  = isset( $mcr_checkin['total_amount'] ) ? max( 0, (int) $mcr_checkin['total_amount'] ) : 0;

$mcr_target = isset( $mcr_next_bonus['threshold'] ) ? (int) $mcr_next_bonus['threshold'] : 30;
$mcr_target = $mcr_target > 0 ? $mcr_target : 30;

$mcr_primary_url   = $mcr_checkin_url;
$mcr_primary_label = __( 'Earn coins', 'madara-coin-rewards' );

if ( $mcr_claimable && $mcr_checkin_on ) {
	$mcr_primary_label = sprintf(
		/* translators: %s: coin amount */
		__( 'Claim %s coins', 'madara-coin-rewards' ),
		number_format_i18n( $mcr_claim_total )
	);
} elseif ( $mcr_ads_on && $mcr_ads_ready && $mcr_ads_left > 0 ) {
	$mcr_primary_url   = $mcr_ads_url;
	$mcr_primary_label = sprintf(
		/* translators: %s: coin amount */
		__( 'Watch an ad (+%s)', 'madara-coin-rewards' ),
		number_format_i18n( $mcr_coins_per_ad )
	);
} elseif ( $mcr_purchase_on ) {
	$mcr_primary_url   = $mcr_buy_url;
	$mcr_primary_label = __( 'Buy coins', 'madara-coin-rewards' );
}
?>
<?php if ( $mcr_show_balance ) : ?>
	<section class="mcr-section" id="mcr-dashboard-balance" aria-labelledby="mcr-dashboard-balance-title">
		<div class="mcr-card">
			<div class="mcr-card__header">
				<div>
					<h2 class="mcr-card__title" id="mcr-dashboard-balance-title"><?php esc_html_e( 'Your balance', 'madara-coin-rewards' ); ?></h2>
					<p class="mcr-card__meta"><?php esc_html_e( 'Available balance', 'madara-coin-rewards' ); ?></p>
				</div>
				<?php if ( $mcr_streak > 0 ) : ?>
					<span class="mcr-badge mcr-badge--gold">
						<?php
						printf(
							/* translators: %d: streak length in days */
							esc_html__( '%d day streak', 'madara-coin-rewards' ),
							(int) $mcr_streak
						);
						?>
					</span>
				<?php endif; ?>
			</div>

			<div class="mcr-card__body">
				<?php echo \MCR\Templates\Templates::amount( $mcr_balance, 'xl', array( 'data-mcr-balance' => '1' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

				<?php if ( $mcr_show_progress ) : ?>
					<?php
					echo \MCR\Templates\Templates::part( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						'progress',
						array(
							'value'     => min( $mcr_streak, $mcr_target ),
							'max'       => $mcr_target,
							'left'      => sprintf(
								/* translators: %d: streak length in days */
								__( 'Day %d', 'madara-coin-rewards' ),
								(int) $mcr_streak
							),
							'right'     => sprintf(
								/* translators: %d: streak length in days */
								__( 'Day %d', 'madara-coin-rewards' ),
								(int) $mcr_target
							),
							'aria_text' => sprintf(
								/* translators: 1: streak length, 2: target length */
								__( '%1$d of %2$d days', 'madara-coin-rewards' ),
								(int) min( $mcr_streak, $mcr_target ),
								(int) $mcr_target
							),
						)
					);
					?>
					<?php if ( ! empty( $mcr_next_bonus['days'] ) ) : ?>
						<p class="mcr-card__meta">
							<?php
							printf(
								/* translators: 1: days remaining, 2: bonus amount */
								esc_html__( '%1$s more days to a +%2$s bonus', 'madara-coin-rewards' ),
								esc_html( number_format_i18n( (int) $mcr_next_bonus['days'] ) ),
								esc_html( number_format_i18n( (int) $mcr_next_bonus['amount'] ) )
							);
							?>
						</p>
					<?php endif; ?>
				<?php endif; ?>

				<?php if ( 0 === $mcr_balance ) : ?>
					<p class="mcr-card__meta"><?php esc_html_e( 'Earn your first coins with a daily check-in.', 'madara-coin-rewards' ); ?></p>
				<?php endif; ?>
			</div>

			<div class="mcr-card__footer">
				<a class="mcr-btn" href="<?php echo esc_url( $mcr_primary_url ); ?>"><?php echo esc_html( $mcr_primary_label ); ?></a>
				<a class="mcr-btn mcr-btn--quiet" href="<?php echo esc_url( $mcr_txn_url ); ?>"><?php esc_html_e( 'View all', 'madara-coin-rewards' ); ?></a>
			</div>
		</div>
	</section>
<?php endif; ?>

<section class="mcr-section" id="mcr-dashboard-stats" aria-labelledby="mcr-dashboard-stats-title">
	<header class="mcr-section__head">
		<h2 class="mcr-section__title font-heading" id="mcr-dashboard-stats-title"><?php esc_html_e( 'Summary', 'madara-coin-rewards' ); ?></h2>
	</header>
	<div class="mcr-grid mcr-grid--stats">
		<?php
		echo \MCR\Templates\Templates::part( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			'stat',
			array(
				'label'        => __( 'Available balance', 'madara-coin-rewards' ),
				'value'        => $mcr_balance,
				'coin'         => true,
				'data_balance' => true,
				'accent'       => true,
			)
		);

		echo \MCR\Templates\Templates::part( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			'stat',
			array(
				'label' => __( 'Lifetime earned', 'madara-coin-rewards' ),
				'value' => isset( $mcr_stats['lifetime_earned'] ) ? (int) $mcr_stats['lifetime_earned'] : 0,
				'coin'  => true,
			)
		);

		echo \MCR\Templates\Templates::part( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			'stat',
			array(
				'label' => __( 'Lifetime spent', 'madara-coin-rewards' ),
				'value' => isset( $mcr_stats['lifetime_spent'] ) ? (int) $mcr_stats['lifetime_spent'] : 0,
				'coin'  => true,
			)
		);

		echo \MCR\Templates\Templates::part( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			'stat',
			array(
				'label' => __( 'Purchased coins', 'madara-coin-rewards' ),
				'value' => isset( $mcr_stats['purchased_total'] ) ? (int) $mcr_stats['purchased_total'] : 0,
				'coin'  => true,
			)
		);
		?>
	</div>
</section>

<?php if ( $mcr_checkin_on || $mcr_ads_on || $mcr_purchase_on ) : ?>
	<section class="mcr-section" id="mcr-dashboard-quick" aria-labelledby="mcr-dashboard-quick-title">
		<header class="mcr-section__head">
			<h2 class="mcr-section__title font-heading" id="mcr-dashboard-quick-title"><?php esc_html_e( 'Quick actions', 'madara-coin-rewards' ); ?></h2>
		</header>
		<div class="mcr-grid mcr-grid--quick">
			<?php if ( $mcr_checkin_on ) : ?>
				<div class="mcr-card mcr-card--interactive">
					<div class="mcr-card__header">
						<h3 class="mcr-card__title"><?php esc_html_e( 'Daily check-in', 'madara-coin-rewards' ); ?></h3>
						<?php if ( $mcr_claimable ) : ?>
							<span class="mcr-badge mcr-badge--success mcr-badge--dot"><?php esc_html_e( 'Available', 'madara-coin-rewards' ); ?></span>
						<?php elseif ( ! empty( $mcr_checkin['claimed_today'] ) ) : ?>
							<span class="mcr-badge mcr-badge--success mcr-badge--dot"><?php esc_html_e( 'Checked in today', 'madara-coin-rewards' ); ?></span>
						<?php endif; ?>
					</div>
					<div class="mcr-card__body">
						<p class="mcr-card__meta"><?php esc_html_e( 'Claim once a day and keep your streak alive.', 'madara-coin-rewards' ); ?></p>
					</div>
					<div class="mcr-card__footer">
						<a class="mcr-btn mcr-btn--sm" href="<?php echo esc_url( $mcr_checkin_url ); ?>"><?php esc_html_e( 'Check in', 'madara-coin-rewards' ); ?></a>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( $mcr_ads_on ) : ?>
				<div class="mcr-card mcr-card--interactive">
					<div class="mcr-card__header">
						<h3 class="mcr-card__title"><?php esc_html_e( 'Watch ads', 'madara-coin-rewards' ); ?></h3>
						<?php if ( $mcr_ads_ready ) : ?>
							<span class="mcr-badge mcr-badge--gold">
								<?php
								printf(
									/* translators: %s: coin amount */
									esc_html__( '+%s coins', 'madara-coin-rewards' ),
									esc_html( number_format_i18n( $mcr_coins_per_ad ) )
								);
								?>
							</span>
						<?php endif; ?>
					</div>
					<div class="mcr-card__body">
						<p class="mcr-card__meta">
							<?php
							printf(
								/* translators: %s: coin amount */
								esc_html__( 'Watch a short ad to earn %s coins.', 'madara-coin-rewards' ),
								esc_html( number_format_i18n( $mcr_coins_per_ad ) )
							);
							?>
						</p>
					</div>
					<div class="mcr-card__footer">
						<a class="mcr-btn mcr-btn--sm" href="<?php echo esc_url( $mcr_ads_url ); ?>"><?php esc_html_e( 'Watch ads', 'madara-coin-rewards' ); ?></a>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( $mcr_purchase_on ) : ?>
				<div class="mcr-card mcr-card--interactive">
					<div class="mcr-card__header">
						<h3 class="mcr-card__title"><?php esc_html_e( 'Buy coins', 'madara-coin-rewards' ); ?></h3>
					</div>
					<div class="mcr-card__body">
						<p class="mcr-card__meta"><?php esc_html_e( 'Top up your balance. Coins are added after payment confirmation.', 'madara-coin-rewards' ); ?></p>
					</div>
					<div class="mcr-card__footer">
						<a class="mcr-btn mcr-btn--sm" href="<?php echo esc_url( $mcr_buy_url ); ?>"><?php esc_html_e( 'Buy coins', 'madara-coin-rewards' ); ?></a>
					</div>
				</div>
			<?php endif; ?>
		</div>
	</section>
<?php endif; ?>

<section class="mcr-section" id="mcr-dashboard-recent" aria-labelledby="mcr-dashboard-recent-title">
	<header class="mcr-section__head">
		<h2 class="mcr-section__title font-heading" id="mcr-dashboard-recent-title"><?php esc_html_e( 'Recent activity', 'madara-coin-rewards' ); ?></h2>
		<a class="mcr-section__action" href="<?php echo esc_url( $mcr_txn_url ); ?>"><?php esc_html_e( 'View all', 'madara-coin-rewards' ); ?></a>
	</header>

	<div class="mcr-card">
		<?php if ( empty( $mcr_recent ) ) : ?>
			<?php
			echo \MCR\Templates\Templates::part( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				'empty',
				array(
					'title'   => __( 'No transactions yet', 'madara-coin-rewards' ),
					'text'    => __( 'Your coin activity will appear here as soon as you earn or spend coins.', 'madara-coin-rewards' ),
					'bare'    => true,
					'actions' => array(
						array(
							'label' => __( 'Earn coins', 'madara-coin-rewards' ),
							'url'   => $mcr_checkin_url,
						),
					),
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

<div class="mcr-grid mcr-grid--split">
	<?php if ( $mcr_show_progress && ! empty( $mcr_week ) ) : ?>
		<section class="mcr-section" id="mcr-dashboard-streak" aria-labelledby="mcr-dashboard-streak-title">
			<header class="mcr-section__head">
				<h2 class="mcr-section__title font-heading" id="mcr-dashboard-streak-title"><?php esc_html_e( 'Streak', 'madara-coin-rewards' ); ?></h2>
				<a class="mcr-section__action" href="<?php echo esc_url( $mcr_checkin_url ); ?>"><?php esc_html_e( 'Check in', 'madara-coin-rewards' ); ?></a>
			</header>
			<div class="mcr-card">
				<div class="mcr-card__body">
					<?php echo \MCR\Templates\Templates::part( 'streak', array( 'days_data' => $mcr_week, 'bonuses' => $mcr_bonuses ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php if ( ! empty( $mcr_next_bonus['days'] ) ) : ?>
						<p class="mcr-card__meta">
							<?php
							printf(
								/* translators: 1: days remaining, 2: bonus amount */
								esc_html__( '%1$s more days to a +%2$s bonus', 'madara-coin-rewards' ),
								esc_html( number_format_i18n( (int) $mcr_next_bonus['days'] ) ),
								esc_html( number_format_i18n( (int) $mcr_next_bonus['amount'] ) )
							);
							?>
						</p>
					<?php endif; ?>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( $mcr_ads_on ) : ?>
		<section class="mcr-section" id="mcr-dashboard-ads" aria-labelledby="mcr-dashboard-ads-title">
			<header class="mcr-section__head">
				<h2 class="mcr-section__title font-heading" id="mcr-dashboard-ads-title"><?php esc_html_e( 'Ad rewards', 'madara-coin-rewards' ); ?></h2>
				<a class="mcr-section__action" href="<?php echo esc_url( $mcr_ads_url ); ?>"><?php esc_html_e( 'View ads', 'madara-coin-rewards' ); ?></a>
			</header>
			<div class="mcr-card">
				<div class="mcr-card__body">
					<p class="mcr-strong">
						<?php
						printf(
							/* translators: 1: ads watched today, 2: daily cap */
							esc_html__( 'Today\'s ads: %1$d of %2$d', 'madara-coin-rewards' ),
							(int) $mcr_ads_today,
							(int) $mcr_ads_limit
						);
						?>
					</p>
					<?php if ( $mcr_show_progress && $mcr_ads_limit > 0 ) : ?>
						<?php
						echo \MCR\Templates\Templates::part( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							'progress',
							array(
								'value'  => min( $mcr_ads_today, $mcr_ads_limit ),
								'max'    => $mcr_ads_limit,
								'variant' => 'gold',
								'left'   => sprintf(
									/* translators: %d: ads watched */
									__( '%d watched', 'madara-coin-rewards' ),
									(int) $mcr_ads_today
								),
								'right'  => sprintf(
									/* translators: %d: ads remaining */
									__( '%d left', 'madara-coin-rewards' ),
									(int) $mcr_ads_left
								),
							)
						);
						?>
					<?php endif; ?>

					<?php if ( $mcr_ads_ready ) : ?>
						<p class="mcr-card__meta">
							<?php
							printf(
								/* translators: %s: coin amount */
								esc_html__( 'Each verified ad adds %s coins.', 'madara-coin-rewards' ),
								esc_html( number_format_i18n( $mcr_coins_per_ad ) )
							);
							?>
						</p>
					<?php else : ?>
						<?php
						echo \MCR\Templates\Templates::part( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							'notice',
							array(
								'type'  => 'info',
								'title' => __( 'Rewarded ads are not configured on this site yet.', 'madara-coin-rewards' ),
							)
						);
						?>
					<?php endif; ?>
				</div>
			</div>
		</section>
	<?php endif; ?>
</div>
