<?php
/**
 * Daily check-in - hero layout.
 *
 * The status becomes a centred, full-width panel; the streak stays on the
 * surface below it and the bonus ladder is rendered as three cards.
 *
 * @package MCR
 *
 * @var array $args Template arguments.
 */

defined( 'ABSPATH' ) || exit;

$mcr_status      = isset( $args['status'] ) && is_array( $args['status'] ) ? $args['status'] : array();
$mcr_bonuses     = isset( $args['bonuses'] ) && is_array( $args['bonuses'] ) ? $args['bonuses'] : array();
$mcr_urls        = isset( $args['urls'] ) && is_array( $args['urls'] ) ? $args['urls'] : array();
$mcr_enabled     = ! isset( $args['enabled'] ) || $args['enabled'];
$mcr_claimed     = ! empty( $mcr_status['claimed_today'] );
$mcr_streak      = isset( $args['streak'] ) ? max( 0, (int) $args['streak'] ) : 0;
$mcr_total       = isset( $mcr_status['total_amount'] ) ? max( 0, (int) $mcr_status['total_amount'] ) : 0;
$mcr_bonus       = isset( $mcr_status['bonus_amount'] ) ? max( 0, (int) $mcr_status['bonus_amount'] ) : 0;
$mcr_today       = isset( $mcr_status['today_key'] ) ? (string) $mcr_status['today_key'] : gmdate( 'Y-m-d' );
$mcr_deadline    = isset( $args['deadline'] ) ? (string) $args['deadline'] : '';
$mcr_timezone    = isset( $args['timezone'] ) && '' !== (string) $args['timezone'] ? (string) $args['timezone'] : 'UTC';
$mcr_claimable   = ! empty( $args['claimable'] ) && $mcr_enabled && ! $mcr_claimed;
$mcr_show_progress = ! isset( $args['show_progress'] ) || $args['show_progress'];
$mcr_calendar    = isset( $mcr_status['calendar'] ) && is_array( $mcr_status['calendar'] ) ? $mcr_status['calendar'] : array();
$mcr_week        = \MCR\Templates\Templates::week( $mcr_calendar, $mcr_today );
$mcr_form_action = \MCR\Templates\Templates::checkin_form_action();
$mcr_dashboard   = isset( $mcr_urls['dashboard'] ) ? (string) $mcr_urls['dashboard'] : '';

ksort( $mcr_bonuses );

$mcr_claim_label = sprintf(
	/* translators: %s: coin amount */
	__( 'Claim %s coins', 'madara-coin-rewards' ),
	number_format_i18n( $mcr_total )
);
?>
<section class="mcr-section" id="mcr-checkin-status" aria-labelledby="mcr-checkin-status-title">
	<?php if ( ! $mcr_enabled ) : ?>
		<?php
		echo \MCR\Templates\Templates::part( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			'notice',
			array(
				'type'  => 'info',
				'title' => __( 'Daily check-in is currently disabled.', 'madara-coin-rewards' ),
			)
		);
		?>
	<?php endif; ?>

	<div class="mcr-card mcr-card--muted mcr-center">
		<div class="mcr-card__body">
			<span class="mcr-badge mcr-badge--accent">
				<?php
				if ( $mcr_streak > 0 ) {
					printf(
						/* translators: %d: streak length in days */
						esc_html__( 'Day %d', 'madara-coin-rewards' ),
						(int) $mcr_streak
					);
				} else {
					esc_html_e( 'New streak', 'madara-coin-rewards' );
				}
				?>
			</span>

			<h2 class="mcr-card__title" id="mcr-checkin-status-title"><?php esc_html_e( "Today's reward", 'madara-coin-rewards' ); ?></h2>

			<?php echo \MCR\Templates\Templates::amount( $mcr_total, 'xl' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

			<p class="mcr-muted">
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

			<?php if ( $mcr_claimed && $mcr_bonus > 0 ) : ?>
				<?php
				echo \MCR\Templates\Templates::part( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					'notice',
					array(
						'type'  => 'success',
						'title' => sprintf(
							/* translators: %s: bonus coin amount */
							__( 'Streak bonus unlocked: +%s coins.', 'madara-coin-rewards' ),
							number_format_i18n( $mcr_bonus )
						),
					)
				);
				?>
			<?php endif; ?>

			<div data-mcr-checkin-feedback></div>

			<div class="mcr-actions" style="justify-content:center">
				<?php if ( ! $mcr_enabled ) : ?>
					<span class="mcr-muted"><?php esc_html_e( 'Check-in rewards are paused.', 'madara-coin-rewards' ); ?></span>
				<?php elseif ( $mcr_claimed ) : ?>
					<span class="mcr-badge mcr-badge--success mcr-badge--dot"><?php esc_html_e( 'Checked in today', 'madara-coin-rewards' ); ?></span>
					<span class="mcr-muted"><?php esc_html_e( 'Next check-in in', 'madara-coin-rewards' ); ?></span>
					<?php echo \MCR\Templates\Templates::part( 'countdown', array( 'deadline' => $mcr_deadline ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php elseif ( $mcr_claimable ) : ?>
					<?php if ( '' !== $mcr_form_action ) : ?>
						<form method="post" action="<?php echo esc_url( $mcr_form_action ); ?>" data-mcr-checkin-form>
							<input type="hidden" name="action" value="mcr_checkin" />
							<?php wp_nonce_field( 'mcr_checkin', '_wpnonce' ); ?>
							<button type="submit" class="mcr-btn mcr-btn--lg mcr-btn--gold" data-mcr-checkin-claim data-mcr-amount="<?php echo esc_attr( $mcr_total ); ?>">
								<?php echo esc_html( $mcr_claim_label ); ?>
							</button>
						</form>
					<?php else : ?>
						<button type="button" class="mcr-btn mcr-btn--lg mcr-btn--gold" data-mcr-checkin-claim data-mcr-amount="<?php echo esc_attr( $mcr_total ); ?>">
							<?php echo esc_html( $mcr_claim_label ); ?>
						</button>
					<?php endif; ?>
				<?php else : ?>
					<span class="mcr-muted"><?php esc_html_e( 'Check-in is not available right now.', 'madara-coin-rewards' ); ?></span>
				<?php endif; ?>

				<a class="mcr-btn mcr-btn--quiet" href="<?php echo esc_url( $mcr_dashboard ); ?>"><?php esc_html_e( 'View rewards', 'madara-coin-rewards' ); ?></a>
			</div>
		</div>
	</div>
</section>

<?php if ( $mcr_show_progress && ! empty( $mcr_week ) ) : ?>
	<section class="mcr-section" id="mcr-checkin-streak" aria-labelledby="mcr-checkin-streak-title">
		<header class="mcr-section__head">
			<h2 class="mcr-section__title font-heading" id="mcr-checkin-streak-title"><?php esc_html_e( 'This week', 'madara-coin-rewards' ); ?></h2>
			<?php if ( ! empty( $args['next_bonus']['days'] ) ) : ?>
				<span class="mcr-muted">
					<?php
					printf(
						/* translators: 1: days remaining, 2: bonus amount */
						esc_html__( '%1$s more days to a +%2$s bonus', 'madara-coin-rewards' ),
						esc_html( number_format_i18n( (int) $args['next_bonus']['days'] ) ),
						esc_html( number_format_i18n( (int) $args['next_bonus']['amount'] ) )
					);
					?>
				</span>
			<?php endif; ?>
		</header>
		<?php echo \MCR\Templates\Templates::part( 'streak', array( 'days_data' => $mcr_week, 'bonuses' => $mcr_bonuses ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</section>
<?php endif; ?>

<section class="mcr-section" id="mcr-checkin-bonuses" aria-labelledby="mcr-checkin-bonuses-title">
	<header class="mcr-section__head">
		<h2 class="mcr-section__title font-heading" id="mcr-checkin-bonuses-title"><?php esc_html_e( 'Streak bonuses', 'madara-coin-rewards' ); ?></h2>
	</header>

	<?php if ( empty( $mcr_bonuses ) ) : ?>
		<div class="mcr-card mcr-card--muted">
			<p class="mcr-muted"><?php esc_html_e( 'No streak bonuses are configured.', 'madara-coin-rewards' ); ?></p>
		</div>
	<?php else : ?>
		<div class="mcr-grid mcr-grid--quick">
			<?php foreach ( $mcr_bonuses as $mcr_threshold => $mcr_amount ) : ?>
				<?php
				$mcr_threshold = (int) $mcr_threshold;
				$mcr_amount    = (int) $mcr_amount;

				if ( $mcr_threshold < 1 || $mcr_amount < 1 ) {
					continue;
				}

				$mcr_reached = $mcr_streak >= $mcr_threshold;
				?>
				<div class="mcr-card<?php echo esc_attr( $mcr_reached ? '' : ' mcr-card--quiet' ); ?>">
					<div class="mcr-card__header">
						<h3 class="mcr-card__title">
							<?php
							printf(
								/* translators: %d: streak length in days */
								esc_html__( 'Day %d', 'madara-coin-rewards' ),
								(int) $mcr_threshold
							);
							?>
						</h3>
						<?php if ( $mcr_reached ) : ?>
							<span class="mcr-badge mcr-badge--gold"><?php esc_html_e( 'Reached', 'madara-coin-rewards' ); ?></span>
						<?php endif; ?>
					</div>
					<div class="mcr-card__body">
						<?php echo \MCR\Templates\Templates::amount( $mcr_amount, 'lg' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<p class="mcr-card__meta">
							<?php
							if ( $mcr_reached ) {
								esc_html_e( 'Bonus claimed with your streak.', 'madara-coin-rewards' );
							} else {
								printf(
									/* translators: %d: days remaining */
									esc_html__( '%d more days to unlock.', 'madara-coin-rewards' ),
									max( 0, $mcr_threshold - $mcr_streak )
								);
							}
							?>
						</p>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</section>

<section class="mcr-section" id="mcr-checkin-rules" aria-labelledby="mcr-checkin-rules-title">
	<header class="mcr-section__head">
		<h2 class="mcr-section__title font-heading" id="mcr-checkin-rules-title"><?php esc_html_e( 'Rules', 'madara-coin-rewards' ); ?></h2>
	</header>
	<div class="mcr-card mcr-card--muted">
		<p class="mcr-muted">
			<?php
			printf(
				/* translators: %s: site timezone */
				esc_html__( 'One check-in per day. Rewards reset at midnight (%s). Streaks continue until a day is missed.', 'madara-coin-rewards' ),
				esc_html( $mcr_timezone )
			);
			?>
		</p>
	</div>
</section>
