<?php
/**
 * Rewards dashboard - hero layout.
 *
 * The balance becomes a centred hero panel. The former tab panels are stacked
 * as ordinary sections: native tabs need JavaScript, and a tab set that does
 * not work is worse than three visible sections.
 *
 * @package MCR
 *
 * @var array $args Template arguments.
 */

defined( 'ABSPATH' ) || exit;

$mcr_stats       = isset( $args['stats'] ) && is_array( $args['stats'] ) ? $args['stats'] : array();
$mcr_checkin     = isset( $args['checkin'] ) && is_array( $args['checkin'] ) ? $args['checkin'] : array();
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
$mcr_claimed     = ! empty( $mcr_checkin['claimed_today'] );
$mcr_claimable   = ! empty( $args['claimable'] );
$mcr_checkin_on  = ! empty( $args['checkin_enabled'] );
$mcr_ads_on      = ! empty( $args['ads_enabled'] );
$mcr_ads_ready   = ! empty( $args['ads_configured'] );
$mcr_purchase_on = ! empty( $args['purchase_enabled'] );
$mcr_show_progress = ! isset( $args['show_progress'] ) || $args['show_progress'];
$mcr_coins_per_ad = isset( $mcr_ads['coins_per_ad'] ) ? max( 0, (int) $mcr_ads['coins_per_ad'] ) : 0;
$mcr_ads_today   = isset( $mcr_ads['today'] ) ? max( 0, (int) $mcr_ads['today'] ) : 0;
$mcr_ads_limit   = isset( $mcr_ads['limit'] ) ? max( 0, (int) $mcr_ads['limit'] ) : 0;
$mcr_ads_left    = isset( $mcr_ads['remaining'] ) ? max( 0, (int) $mcr_ads['remaining'] ) : max( 0, $mcr_ads_limit - $mcr_ads_today );
$mcr_claim_total = isset( $mcr_checkin['total_amount'] ) ? max( 0, (int) $mcr_checkin['total_amount'] ) : 0;

$mcr_checkin_url = isset( $mcr_urls['checkin'] ) ? (string) $mcr_urls['checkin'] : '';
$mcr_ads_url     = isset( $mcr_urls['ads'] ) ? (string) $mcr_urls['ads'] : '';
$mcr_buy_url     = isset( $mcr_urls['buy'] ) ? (string) $mcr_urls['buy'] : '';
$mcr_txn_url     = isset( $mcr_urls['transactions'] ) ? (string) $mcr_urls['transactions'] : '';

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
<section class="mcr-section" id="mcr-dashboard-balance" aria-labelledby="mcr-dashboard-balance-title">
	<div class="mcr-card mcr-card--muted mcr-center">
		<div class="mcr-card__body">
			<h2 class="mcr-sr-only" id="mcr-dashboard-balance-title"><?php esc_html_e( 'Your balance', 'madara-coin-rewards' ); ?></h2>
			<?php echo \MCR\Templates\Templates::amount( $mcr_balance, 'xl', array( 'data-mcr-balance' => '1' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<p class="mcr-amount__label"><?php esc_html_e( 'available coins', 'madara-coin-rewards' ); ?></p>

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
			<?php else : ?>
				<p class="mcr-card__meta"><?php esc_html_e( 'Earn your first coins with a daily check-in.', 'madara-coin-rewards' ); ?></p>
			<?php endif; ?>

			<div class="mcr-actions" style="justify-content:center">
				<a class="mcr-btn mcr-btn--lg" href="<?php echo esc_url( $mcr_primary_url ); ?>"><?php echo esc_html( $mcr_primary_label ); ?></a>
				<a class="mcr-btn mcr-btn--quiet" href="<?php echo esc_url( $mcr_txn_url ); ?>"><?php esc_html_e( 'History', 'madara-coin-rewards' ); ?></a>
			</div>
		</div>
	</div>
</section>

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

<?php if ( $mcr_checkin_on ) : ?>
	<section class="mcr-section" id="mcr-dashboard-checkin" aria-labelledby="mcr-dashboard-checkin-title">
		<header class="mcr-section__head">
			<h2 class="mcr-section__title font-heading" id="mcr-dashboard-checkin-title"><?php esc_html_e( 'Daily check-in', 'madara-coin-rewards' ); ?></h2>
			<a class="mcr-section__action" href="<?php echo esc_url( $mcr_checkin_url ); ?>"><?php esc_html_e( 'Open check-in', 'madara-coin-rewards' ); ?></a>
		</header>
		<div class="mcr-grid mcr-grid--split">
			<div class="mcr-card">
				<div class="mcr-card__header">
					<h3 class="mcr-card__title"><?php esc_html_e( "Today's reward", 'madara-coin-rewards' ); ?></h3>
					<?php if ( $mcr_claimed ) : ?>
						<span class="mcr-badge mcr-badge--success mcr-badge--dot"><?php esc_html_e( 'Checked in today', 'madara-coin-rewards' ); ?></span>
					<?php endif; ?>
				</div>
				<div class="mcr-card__body">
					<?php echo \MCR\Templates\Templates::amount( $mcr_claim_total, 'lg' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<p class="mcr-card__meta">
						<?php if ( $mcr_streak > 0 ) : ?>
							<?php
							printf(
								/* translators: %d: streak length in days */
								esc_html__( 'Day %d of your streak', 'madara-coin-rewards' ),
								(int) $mcr_streak
							);
							?>
						<?php else : ?>
							<?php esc_html_e( 'Start your streak today.', 'madara-coin-rewards' ); ?>
						<?php endif; ?>
					</p>
					<?php if ( $mcr_claimed ) : ?>
						<div class="mcr-row">
							<span class="mcr-muted"><?php esc_html_e( 'Next check-in in', 'madara-coin-rewards' ); ?></span>
							<?php echo \MCR\Templates\Templates::part( 'countdown', array( 'deadline' => isset( $args['deadline'] ) ? $args['deadline'] : '', 'small' => true ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</div>
					<?php else : ?>
						<a class="mcr-btn" href="<?php echo esc_url( $mcr_checkin_url ); ?>"><?php esc_html_e( 'Claim on the check-in page', 'madara-coin-rewards' ); ?></a>
					<?php endif; ?>
				</div>
			</div>

			<div class="mcr-card">
				<div class="mcr-card__header">
					<h3 class="mcr-card__title"><?php esc_html_e( 'This week', 'madara-coin-rewards' ); ?></h3>
				</div>
				<div class="mcr-card__body">
					<?php if ( ! empty( $mcr_week ) ) : ?>
						<?php echo \MCR\Templates\Templates::part( 'streak', array( 'days_data' => $mcr_week, 'bonuses' => isset( $args['bonuses'] ) ? $args['bonuses'] : array() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php endif; ?>
					<?php if ( ! empty( $args['next_bonus']['days'] ) ) : ?>
						<p class="mcr-card__meta">
							<?php
							printf(
								/* translators: 1: days remaining, 2: bonus amount */
								esc_html__( '%1$s more days to a +%2$s bonus', 'madara-coin-rewards' ),
								esc_html( number_format_i18n( (int) $args['next_bonus']['days'] ) ),
								esc_html( number_format_i18n( (int) $args['next_bonus']['amount'] ) )
							);
							?>
						</p>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</section>
<?php endif; ?>

<?php if ( $mcr_ads_on ) : ?>
	<section class="mcr-section" id="mcr-dashboard-ads" aria-labelledby="mcr-dashboard-ads-title">
		<header class="mcr-section__head">
			<h2 class="mcr-section__title font-heading" id="mcr-dashboard-ads-title"><?php esc_html_e( 'Ad rewards', 'madara-coin-rewards' ); ?></h2>
			<a class="mcr-section__action" href="<?php echo esc_url( $mcr_ads_url ); ?>"><?php esc_html_e( 'Watch ads', 'madara-coin-rewards' ); ?></a>
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
							'value'   => min( $mcr_ads_today, $mcr_ads_limit ),
							'max'     => $mcr_ads_limit,
							'variant' => 'gold',
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
<?php endif;
