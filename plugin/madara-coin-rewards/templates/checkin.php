<?php
/**
 * Daily check-in - default (calendar) layout.
 *
 * The template only formats data prepared by MCR\Templates\Shortcodes. The
 * claim button is a real form when the controller registered the admin-post
 * fallback; JavaScript upgrades it to the REST endpoint.
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
$mcr_has_any     = false;

foreach ( $mcr_calendar as $mcr_day ) {
	if ( ! empty( $mcr_day['claimed'] ) ) {
		$mcr_has_any = true;
		break;
	}
}

ksort( $mcr_bonuses );

/*
 * The empty-state copy names the configured bonus days, so a site with a
 * 5/14/60 ladder is not told about the wrong bonus days.
 */
$mcr_bonus_days = array_keys( $mcr_bonuses );
sort( $mcr_bonus_days );

if ( empty( $mcr_bonus_days ) ) {
	$mcr_empty_text = __( 'Claim your first daily reward to start a streak.', 'madara-coin-rewards' );
} elseif ( 1 === count( $mcr_bonus_days ) ) {
	$mcr_empty_text = sprintf(
		/* translators: %s: list of streak days, e.g. "3" */
		_n(
			'Claim your first daily reward to start a streak. Day %s unlocks bonus coins.',
			'Claim your first daily reward to start a streak. Days %s unlock bonus coins.',
			count( $mcr_bonus_days ),
			'madara-coin-rewards'
		),
		implode( ', ', $mcr_bonus_days )
	);
} else {
	$mcr_empty_text = sprintf(
		/* translators: %s: list of streak days, e.g. "3, 7 and 30" */
		__( 'Claim your first daily reward to start a streak. Days %s unlock bonus coins.', 'madara-coin-rewards' ),
		implode( ', ', array_slice( $mcr_bonus_days, 0, -1 ) ) . ' and ' . end( $mcr_bonus_days )
	);
}

$mcr_claim_label = sprintf(
	/* translators: %s: coin amount */
	__( 'Claim %s coins', 'madara-coin-rewards' ),
	number_format_i18n( $mcr_total )
);
?>
<?php echo \MCR\Templates\Templates::part( 'wallet', $args ); ?>
<section class="mcr-section" id="mcr-checkin-status" aria-labelledby="mcr-checkin-status-title">
	<header class="mcr-section__head">
		<h2 class="mcr-section__title font-heading" id="mcr-checkin-status-title"><?php esc_html_e( 'Daily check-in', 'madara-coin-rewards' ); ?></h2>
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
	</header>

	<?php if ( ! $mcr_enabled ) : ?>
		<?php
		echo \MCR\Templates\Templates::part( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			'notice',
			array(
				'type'  => 'info',
				'title' => __( 'Daily check-in is currently disabled.', 'madara-coin-rewards' ),
				'text'  => __( 'You can still earn coins from ads or by purchasing a package.', 'madara-coin-rewards' ),
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

	<div class="mcr-grid mcr-grid--split">
		<div class="mcr-card<?php echo esc_attr( $mcr_streak < 1 ? ' mcr-card--quiet' : '' ); ?>">
			<div class="mcr-card__header">
				<div>
					<h3 class="mcr-card__title"><?php esc_html_e( "Today's reward", 'madara-coin-rewards' ); ?></h3>
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
				<?php if ( $mcr_bonus > 0 && ! $mcr_claimed ) : ?>
					<span class="mcr-badge mcr-badge--gold">
						<?php
						printf(
							/* translators: %s: bonus coin amount */
							esc_html__( '+%s bonus', 'madara-coin-rewards' ),
							esc_html( number_format_i18n( $mcr_bonus ) )
						);
						?>
					</span>
				<?php endif; ?>
			</div>

			<div class="mcr-card__body">
				<?php echo \MCR\Templates\Templates::amount( $mcr_total, 'xl' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php if ( $mcr_total > 0 ) : ?>
					<p class="mcr-card__meta">
						<?php
						printf(
							/* translators: %s: coin amount */
							esc_html__( 'Today\'s reward: %s coins', 'madara-coin-rewards' ),
							esc_html( number_format_i18n( $mcr_total ) )
						);
						?>
					</p>
				<?php endif; ?>

				<div data-mcr-checkin-feedback></div>

				<?php if ( ! $mcr_enabled ) : ?>
					<p class="mcr-muted"><?php esc_html_e( 'Check-in rewards are paused. Please check back later.', 'madara-coin-rewards' ); ?></p>
				<?php elseif ( $mcr_claimed ) : ?>
					<div class="mcr-row">
						<span class="mcr-badge mcr-badge--success mcr-badge--dot"><?php esc_html_e( 'Checked in today', 'madara-coin-rewards' ); ?></span>
					</div>
					<div class="mcr-row">
						<span class="mcr-muted"><?php esc_html_e( 'Next check-in in', 'madara-coin-rewards' ); ?></span>
						<?php echo \MCR\Templates\Templates::part( 'countdown', array( 'deadline' => $mcr_deadline, 'small' => true ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
				<?php elseif ( $mcr_claimable ) : ?>
					<?php if ( '' !== $mcr_form_action ) : ?>
						<form method="post" action="<?php echo esc_url( $mcr_form_action ); ?>" class="mcr-full" data-mcr-checkin-form>
							<input type="hidden" name="action" value="mcr_checkin" />
							<?php wp_nonce_field( 'mcr_checkin', '_wpnonce' ); ?>
							<button type="submit" class="mcr-btn mcr-btn--lg mcr-btn--block" data-mcr-checkin-claim data-mcr-amount="<?php echo esc_attr( $mcr_total ); ?>">
								<?php echo esc_html( $mcr_claim_label ); ?>
							</button>
						</form>
					<?php else : ?>
						<button type="button" class="mcr-btn mcr-btn--lg mcr-btn--block" data-mcr-checkin-claim data-mcr-amount="<?php echo esc_attr( $mcr_total ); ?>">
							<?php echo esc_html( $mcr_claim_label ); ?>
						</button>
					<?php endif; ?>
				<?php else : ?>
					<?php
					echo \MCR\Templates\Templates::part( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						'notice',
						array(
							'type'  => 'info',
							'title' => __( 'Check-in is not available right now.', 'madara-coin-rewards' ),
							'text'  => __( 'No reward is configured for today. Please check back later.', 'madara-coin-rewards' ),
						)
					);
					?>
				<?php endif; ?>
			</div>

			<?php if ( $mcr_enabled ) : ?>
				<div class="mcr-card__footer">
					<a class="mcr-btn mcr-btn--quiet" href="<?php echo esc_url( $mcr_dashboard ); ?>"><?php esc_html_e( 'View rewards', 'madara-coin-rewards' ); ?></a>
				</div>
			<?php endif; ?>
		</div>

		<div class="mcr-card">
			<div class="mcr-card__header">
				<h3 class="mcr-card__title"><?php esc_html_e( 'Recent check-ins', 'madara-coin-rewards' ); ?></h3>
			</div>
			<div class="mcr-card__body">
				<?php if ( $mcr_has_any ) : ?>
					<ol class="mcr-streak">
						<?php foreach ( $mcr_calendar as $mcr_day ) : ?>
							<?php
							if ( ! is_array( $mcr_day ) || empty( $mcr_day['date'] ) ) {
								continue;
							}

							$mcr_date     = (string) $mcr_day['date'];
							$mcr_claimed_day = ! empty( $mcr_day['claimed'] );
							$mcr_is_today = ( $mcr_date === $mcr_today );
							$mcr_day_streak = isset( $mcr_day['streak'] ) ? (int) $mcr_day['streak'] : 0;
							$mcr_day_amount = isset( $mcr_day['amount'] ) ? (int) $mcr_day['amount'] : 0;
							$mcr_day_classes = 'mcr-streak__day';

							if ( $mcr_claimed_day ) {
								$mcr_day_classes .= ' is-done';
							} elseif ( $mcr_date > $mcr_today ) {
								$mcr_day_classes .= ' is-locked';
							} elseif ( $mcr_is_today ) {
								$mcr_day_classes .= ' is-available';
							} else {
								$mcr_day_classes .= ' is-missed';
							}

							if ( $mcr_is_today ) {
								$mcr_day_classes .= ' is-today';
							}

							if ( $mcr_claimed_day && $mcr_day_streak > 0 && isset( $mcr_bonuses[ $mcr_day_streak ] ) ) {
								$mcr_day_classes .= ' is-bonus';
							}
							?>
							<li class="<?php echo esc_attr( $mcr_day_classes ); ?>"<?php echo $mcr_is_today ? ' aria-current="date"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
								<span class="mcr-streak__label" aria-hidden="true"><?php echo esc_html( \MCR\Templates\Templates::format_date( $mcr_date, 'j' ) ); ?></span>
								<span class="mcr-streak__dot" aria-hidden="true"><?php echo esc_html( $mcr_claimed_day ? '✓' : '·' ); ?></span>
								<span class="mcr-sr-only">
									<?php
									if ( $mcr_claimed_day ) {
										printf(
											/* translators: 1: date, 2: coin amount */
											esc_html__( '%1$s: claimed, %2$s coins.', 'madara-coin-rewards' ),
											esc_html( \MCR\Templates\Templates::format_date( $mcr_date ) ),
											esc_html( number_format_i18n( $mcr_day_amount ) )
										);
									} elseif ( $mcr_is_today ) {
										printf(
											/* translators: %s: date */
											esc_html__( '%s: available, ready to claim.', 'madara-coin-rewards' ),
											esc_html( \MCR\Templates\Templates::format_date( $mcr_date ) )
										);
									} elseif ( $mcr_date > $mcr_today ) {
										printf(
											/* translators: %s: date */
											esc_html__( '%s: upcoming.', 'madara-coin-rewards' ),
											esc_html( \MCR\Templates\Templates::format_date( $mcr_date ) )
										);
									} else {
										printf(
											/* translators: %s: date */
											esc_html__( '%s: not claimed.', 'madara-coin-rewards' ),
											esc_html( \MCR\Templates\Templates::format_date( $mcr_date ) )
										);
									}
									?>
								</span>
							</li>
						<?php endforeach; ?>
					</ol>
					<ul class="mcr-legend">
						<li class="mcr-legend__item">
							<span class="mcr-legend__dot is-done" aria-hidden="true"></span>
							<span><?php esc_html_e( 'Claimed', 'madara-coin-rewards' ); ?></span>
						</li>
						<li class="mcr-legend__item">
							<span class="mcr-legend__dot is-available" aria-hidden="true"></span>
							<span><?php esc_html_e( 'Available', 'madara-coin-rewards' ); ?></span>
						</li>
						<li class="mcr-legend__item">
							<span class="mcr-legend__dot is-locked" aria-hidden="true"></span>
							<span><?php esc_html_e( 'Upcoming', 'madara-coin-rewards' ); ?></span>
						</li>
						<li class="mcr-legend__item">
							<span class="mcr-legend__dot is-missed" aria-hidden="true"></span>
							<span><?php esc_html_e( 'Missed', 'madara-coin-rewards' ); ?></span>
						</li>
					</ul>
				<?php else : ?>
					<?php
					$mcr_empty_actions = array();

					if ( $mcr_enabled && $mcr_claimable ) {
						$mcr_empty_actions[] = array(
							'label'      => sprintf(
								/* translators: %s: coin amount */
								__( 'Claim today\'s reward (+%s)', 'madara-coin-rewards' ),
								number_format_i18n( $mcr_total )
							),
							'class'      => 'mcr-btn',
							'attributes' => array( 'data-mcr-checkin-claim' => '1' ),
						);
					}

					echo \MCR\Templates\Templates::part( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						'empty',
						array(
							'icon'    => '&#9737;',
							'title'   => __( 'No check-ins yet', 'madara-coin-rewards' ),
							'text'    => $mcr_empty_text,
							'actions' => $mcr_empty_actions,
							'bare'    => true,
						)
					);
					?>
				<?php endif; ?>
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
		<span class="mcr-muted"><?php esc_html_e( 'Bonus coins are added automatically with the check-in.', 'madara-coin-rewards' ); ?></span>
	</header>

	<div class="mcr-card<?php echo esc_attr( $mcr_streak < 1 ? ' mcr-card--quiet' : '' ); ?>">
		<?php if ( empty( $mcr_bonuses ) ) : ?>
			<p class="mcr-muted"><?php esc_html_e( 'No streak bonuses are configured.', 'madara-coin-rewards' ); ?></p>
		<?php else : ?>
			<ul class="mcr-txn-list">
				<?php foreach ( $mcr_bonuses as $mcr_threshold => $mcr_amount ) : ?>
					<?php
					$mcr_threshold = (int) $mcr_threshold;
					$mcr_amount    = (int) $mcr_amount;

					if ( $mcr_threshold < 1 || $mcr_amount < 1 ) {
						continue;
					}

					$mcr_reached = $mcr_streak >= $mcr_threshold;
					$mcr_left    = max( 0, $mcr_threshold - $mcr_streak );
					?>
					<li class="mcr-txn">
						<span class="mcr-txn__icon mcr-txn__icon--credit" aria-hidden="true">&#43;</span>
						<div class="mcr-txn__main">
							<span class="mcr-txn__reason">
								<?php
								printf(
									/* translators: %d: streak length in days */
									esc_html__( 'Day %d', 'madara-coin-rewards' ),
									(int) $mcr_threshold
								);
								?>
								<?php if ( $mcr_reached ) : ?>
									<span class="mcr-badge mcr-badge--gold"><?php esc_html_e( 'Reached', 'madara-coin-rewards' ); ?></span>
								<?php endif; ?>
							</span>
							<span class="mcr-txn__meta">
								<?php
								if ( $mcr_reached ) {
									esc_html_e( 'Bonus claimed with your streak.', 'madara-coin-rewards' );
								} else {
									printf(
										/* translators: %d: days remaining */
										esc_html__( '%d more days to unlock.', 'madara-coin-rewards' ),
										(int) $mcr_left
									);
								}
								?>
							</span>
						</div>
						<div class="mcr-txn__amount mcr-txn__amount--credit">
							<?php echo esc_html( '+' . number_format_i18n( $mcr_amount ) ); ?>
							<span class="mcr-sr-only"><?php esc_html_e( 'coins', 'madara-coin-rewards' ); ?></span>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
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

<?php echo \MCR\Templates\Templates::part( 'ads-panel', $args ); ?>
