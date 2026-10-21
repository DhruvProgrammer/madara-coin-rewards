<?php
/**
 * Daily check-in - compact layout.
 *
 * One card: status line, amount, a single claim control and the current week.
 * The calendar and the bonus ladder are intentionally omitted; the rules move
 * into a native <details> so the block stays short.
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
$mcr_calendar    = isset( $mcr_status['calendar'] ) && is_array( $mcr_status['calendar'] ) ? $mcr_status['calendar'] : array();
$mcr_week        = \MCR\Templates\Templates::week( $mcr_calendar, $mcr_today );
$mcr_form_action = \MCR\Templates\Templates::checkin_form_action();
$mcr_dashboard   = isset( $mcr_urls['dashboard'] ) ? (string) $mcr_urls['dashboard'] : '';

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

	<div class="mcr-card">
		<div class="mcr-card__header">
			<div>
				<h2 class="mcr-card__title" id="mcr-checkin-status-title"><?php esc_html_e( 'Daily check-in', 'madara-coin-rewards' ); ?></h2>
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
			</div>
			<?php if ( $mcr_claimed ) : ?>
				<span class="mcr-badge mcr-badge--success mcr-badge--dot"><?php esc_html_e( 'Checked in today', 'madara-coin-rewards' ); ?></span>
			<?php else : ?>
				<span class="mcr-badge mcr-badge--gold">
					<?php
					printf(
						/* translators: %s: coin amount */
						esc_html__( '+%s coins', 'madara-coin-rewards' ),
						esc_html( number_format_i18n( $mcr_total ) )
					);
					?>
				</span>
			<?php endif; ?>
		</div>

		<div class="mcr-card__body">
			<div class="mcr-row mcr-row--between">
				<div>
					<?php echo \MCR\Templates\Templates::amount( $mcr_total, 'lg' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<p class="mcr-card__meta">
						<?php
						printf(
							/* translators: %s: coin amount */
							esc_html__( "Today's reward: %s coins", 'madara-coin-rewards' ),
							esc_html( number_format_i18n( $mcr_total ) )
						);
						?>
					</p>
				</div>

				<div class="mcr-actions">
					<?php if ( ! $mcr_enabled ) : ?>
						<span class="mcr-muted"><?php esc_html_e( 'Check-in rewards are paused.', 'madara-coin-rewards' ); ?></span>
					<?php elseif ( $mcr_claimed ) : ?>
						<span class="mcr-muted"><?php esc_html_e( 'Next check-in in', 'madara-coin-rewards' ); ?></span>
						<?php echo \MCR\Templates\Templates::part( 'countdown', array( 'deadline' => $mcr_deadline, 'small' => true ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php elseif ( $mcr_claimable ) : ?>
						<?php if ( '' !== $mcr_form_action ) : ?>
							<form method="post" action="<?php echo esc_url( $mcr_form_action ); ?>" data-mcr-checkin-form>
								<input type="hidden" name="action" value="mcr_checkin" />
								<?php wp_nonce_field( 'mcr_checkin', '_wpnonce' ); ?>
								<button type="submit" class="mcr-btn mcr-btn--lg" data-mcr-checkin-claim data-mcr-amount="<?php echo esc_attr( $mcr_total ); ?>">
									<?php echo esc_html( $mcr_claim_label ); ?>
								</button>
							</form>
						<?php else : ?>
							<button type="button" class="mcr-btn mcr-btn--lg" data-mcr-checkin-claim data-mcr-amount="<?php echo esc_attr( $mcr_total ); ?>">
								<?php echo esc_html( $mcr_claim_label ); ?>
							</button>
						<?php endif; ?>
					<?php else : ?>
						<span class="mcr-muted"><?php esc_html_e( 'Check-in is not available right now.', 'madara-coin-rewards' ); ?></span>
					<?php endif; ?>
				</div>
			</div>

			<div data-mcr-checkin-feedback></div>

			<?php if ( ! empty( $mcr_week ) ) : ?>
				<?php echo \MCR\Templates\Templates::part( 'streak', array( 'days_data' => $mcr_week, 'bonuses' => $mcr_bonuses ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endif; ?>
		</div>

		<div class="mcr-card__footer">
			<a class="mcr-btn mcr-btn--sm mcr-btn--quiet" href="<?php echo esc_url( $mcr_dashboard ); ?>"><?php esc_html_e( 'View rewards', 'madara-coin-rewards' ); ?></a>
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
		</div>
	</div>

	<details class="mcr-card mcr-card--muted">
		<summary class="mcr-strong"><?php esc_html_e( 'Rules', 'madara-coin-rewards' ); ?></summary>
		<p class="mcr-muted">
			<?php
			printf(
				/* translators: %s: site timezone */
				esc_html__( 'One check-in per day. Rewards reset at midnight (%s). Streaks continue until a day is missed.', 'madara-coin-rewards' ),
				esc_html( $mcr_timezone )
			);
			?>
		</p>
	</details>
</section>
