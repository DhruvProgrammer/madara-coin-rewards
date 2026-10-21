<?php
/**
 * Rewards dashboard - list layout.
 *
 * One column, no cards except the sticky balance bar. Recent activity becomes
 * a real table so the page reads like an account statement.
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
$mcr_checkin_on  = ! empty( $args['checkin_enabled'] );
$mcr_ads_on      = ! empty( $args['ads_enabled'] );
$mcr_purchase_on = ! empty( $args['purchase_enabled'] );
$mcr_ads_ready   = ! empty( $args['ads_configured'] );
$mcr_claimable   = ! empty( $args['claimable'] );
$mcr_ads_left    = isset( $mcr_ads['remaining'] ) ? max( 0, (int) $mcr_ads['remaining'] ) : 0;
$mcr_coins_per_ad = isset( $mcr_ads['coins_per_ad'] ) ? max( 0, (int) $mcr_ads['coins_per_ad'] ) : 0;
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
	<div class="mcr-sticky-bar">
		<div>
			<span class="mcr-stat__label" id="mcr-dashboard-balance-title"><?php esc_html_e( 'Available balance', 'madara-coin-rewards' ); ?></span>
			<?php echo \MCR\Templates\Templates::amount( $mcr_balance, 'lg', array( 'data-mcr-balance' => '1' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
		<div class="mcr-actions">
			<a class="mcr-btn mcr-btn--sm" href="<?php echo esc_url( $mcr_primary_url ); ?>"><?php echo esc_html( $mcr_primary_label ); ?></a>
			<a class="mcr-btn mcr-btn--sm mcr-btn--quiet" href="<?php echo esc_url( $mcr_txn_url ); ?>"><?php esc_html_e( 'History', 'madara-coin-rewards' ); ?></a>
		</div>
	</div>
</section>

<section class="mcr-section" id="mcr-dashboard-stats" aria-labelledby="mcr-dashboard-stats-title">
	<header class="mcr-section__head">
		<h2 class="mcr-section__title font-heading" id="mcr-dashboard-stats-title"><?php esc_html_e( 'Summary', 'madara-coin-rewards' ); ?></h2>
	</header>
	<div class="mcr-grid mcr-grid--cols-2">
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
		<div class="mcr-actions">
			<?php if ( $mcr_checkin_on ) : ?>
				<a class="mcr-btn mcr-btn--block" href="<?php echo esc_url( $mcr_checkin_url ); ?>"><?php esc_html_e( 'Daily check-in', 'madara-coin-rewards' ); ?></a>
			<?php endif; ?>
			<?php if ( $mcr_ads_on ) : ?>
				<a class="mcr-btn mcr-btn--block" href="<?php echo esc_url( $mcr_ads_url ); ?>"><?php esc_html_e( 'Watch ads', 'madara-coin-rewards' ); ?></a>
			<?php endif; ?>
			<?php if ( $mcr_purchase_on ) : ?>
				<a class="mcr-btn mcr-btn--block mcr-btn--quiet" href="<?php echo esc_url( $mcr_buy_url ); ?>"><?php esc_html_e( 'Buy coins', 'madara-coin-rewards' ); ?></a>
			<?php endif; ?>
		</div>
	</section>
<?php endif; ?>

<section class="mcr-section" id="mcr-dashboard-recent" aria-labelledby="mcr-dashboard-recent-title">
	<header class="mcr-section__head">
		<h2 class="mcr-section__title font-heading" id="mcr-dashboard-recent-title"><?php esc_html_e( 'Recent activity', 'madara-coin-rewards' ); ?></h2>
		<a class="mcr-section__action" href="<?php echo esc_url( $mcr_txn_url ); ?>"><?php esc_html_e( 'View all', 'madara-coin-rewards' ); ?></a>
	</header>

	<?php if ( empty( $mcr_recent ) ) : ?>
		<?php
		echo \MCR\Templates\Templates::part( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			'empty',
			array(
				'title'   => __( 'No transactions yet', 'madara-coin-rewards' ),
				'text'    => __( 'Your coin activity will appear here as soon as you earn or spend coins.', 'madara-coin-rewards' ),
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
		<div class="mcr-table__wrap">
			<table class="mcr-table mcr-table--stack">
				<caption class="mcr-sr-only"><?php esc_html_e( 'Recent activity', 'madara-coin-rewards' ); ?></caption>
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Date', 'madara-coin-rewards' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Type', 'madara-coin-rewards' ); ?></th>
						<th scope="col" class="mcr-table__num"><?php esc_html_e( 'Amount', 'madara-coin-rewards' ); ?></th>
						<th scope="col" class="mcr-table__num"><?php esc_html_e( 'Balance after', 'madara-coin-rewards' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $mcr_recent as $mcr_txn ) : ?>
						<?php
						$mcr_amount = isset( $mcr_txn['amount'] ) ? (int) $mcr_txn['amount'] : 0;
						$mcr_credit = $mcr_amount >= 0;
						?>
						<tr>
							<th scope="row" data-label="<?php esc_attr_e( 'Date', 'madara-coin-rewards' ); ?>">
								<time datetime="<?php echo esc_attr( isset( $mcr_txn['created_iso'] ) ? $mcr_txn['created_iso'] : '' ); ?>">
									<?php echo esc_html( isset( $mcr_txn['created_display'] ) ? $mcr_txn['created_display'] : '' ); ?>
								</time>
							</th>
							<td data-label="<?php esc_attr_e( 'Type', 'madara-coin-rewards' ); ?>">
								<?php echo esc_html( isset( $mcr_txn['type_label'] ) ? $mcr_txn['type_label'] : '' ); ?>
							</td>
							<td data-label="<?php esc_attr_e( 'Amount', 'madara-coin-rewards' ); ?>" class="mcr-table__num <?php echo esc_attr( $mcr_credit ? 'mcr-table__credit' : 'mcr-table__debit' ); ?>">
								<?php echo esc_html( \MCR\Templates\Templates::amount_text( $mcr_amount ) ); ?>
							</td>
							<td data-label="<?php esc_attr_e( 'Balance after', 'madara-coin-rewards' ); ?>" class="mcr-table__num">
								<?php echo esc_html( number_format_i18n( isset( $mcr_txn['balance_after'] ) ? (int) $mcr_txn['balance_after'] : 0 ) ); ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</section>

<section class="mcr-section" id="mcr-dashboard-streak" aria-labelledby="mcr-dashboard-streak-title">
	<header class="mcr-section__head">
		<h2 class="mcr-section__title font-heading" id="mcr-dashboard-streak-title"><?php esc_html_e( 'Streak', 'madara-coin-rewards' ); ?></h2>
		<?php if ( $mcr_streak > 0 ) : ?>
			<span class="mcr-badge mcr-badge--gold">
				<?php
				printf(
					/* translators: %d: streak length in days */
					esc_html__( '%d days', 'madara-coin-rewards' ),
					(int) $mcr_streak
				);
				?>
			</span>
		<?php endif; ?>
	</header>
	<?php if ( ! empty( $mcr_week ) ) : ?>
		<?php echo \MCR\Templates\Templates::part( 'streak', array( 'days_data' => $mcr_week, 'bonuses' => isset( $args['bonuses'] ) ? $args['bonuses'] : array() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php endif; ?>
	<?php if ( ! empty( $args['next_bonus']['days'] ) ) : ?>
		<p class="mcr-muted">
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
</section>
