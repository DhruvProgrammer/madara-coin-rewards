<?php
/**
 * Daily check-in - default (calendar) layout.
 *
 * Mobile first: a single column on phones, two columns on tablet and up.
 * The wallet (balance) sits at the top, the check-in card is the primary
 * action, the reward calendar and the milestones follow, and the ad panel
 * and the recent activity sit in the side column on wider screens.
 *
 * The page is rendered inside the theme's own content container, so the
 * Madara header, navigation and footer are the theme's. This template only
 * fills the content area. The theme's sidebar is removed for this page by
 * MCR\Integrations\FullWidth, so the content gets the full width.
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
$mcr_stats       = isset( $args['stats'] ) && is_array( $args['stats'] ) ? $args['stats'] : array();
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
$mcr_logged_in   = ! empty( $args['user_id'] );
$mcr_has_any     = false;
$mcr_recent      = isset( $args['recent'] ) && is_array( $args['recent'] ) ? $args['recent'] : array();

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

$mcr_next_bonus = isset( $args['next_bonus'] ) && is_array( $args['next_bonus'] ) ? $args['next_bonus'] : array();

if ( '+00:00' === $mcr_timezone || 'UTC' === strtoupper( $mcr_timezone ) ) {
	$mcr_timezone = __( 'UTC', 'madara-coin-rewards' );
}
?>
<?php
/*
 * The balance (wallet) card comes first. It is the same partial the rest
 * of the plugin uses, so it stays in step.
 */
echo \MCR\Templates\Templates::part( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	'wallet-block',
	array( 'page_args' => $args )
);
?>

<div class="mcr-rewards__lead">
	<p><?php esc_html_e( 'Check in every day, watch ads and turn your activity into coins you can spend.', 'madara-coin-rewards' ); ?></p>
	<?php if ( $mcr_streak > 0 || ( isset( $args['today_earned'] ) && (int) $args['today_earned'] > 0 ) ) : ?>
		<div class="mcr-rewards__chips">
			<?php if ( $mcr_streak > 0 ) : ?>
				<span class="mcr-chip mcr-chip--streak">
					<?php
					printf(
						/* translators: %d: streak length in days */
						esc_html__( '%d day streak', 'madara-coin-rewards' ),
						(int) $mcr_streak
					);
					?>
				</span>
			<?php endif; ?>
			<?php if ( isset( $args['today_earned'] ) && (int) $args['today_earned'] > 0 ) : ?>
				<span class="mcr-chip mcr-chip--earned">
					<?php
					printf(
						/* translators: %s: coin amount */
						esc_html__( '+%s coins earned today', 'madara-coin-rewards' ),
						esc_html( number_format_i18n( (int) $args['today_earned'] ) )
					);
					?>
				</span>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>

<div class="mcr-rewards__grid">
	<div class="mcr-rewards__main">
		<section class="mcr-section" id="mcr-checkin-status" aria-labelledby="mcr-checkin-status-title">
			<header class="mcr-section__head">
				<h2 class="mcr-section__title" id="mcr-checkin-status-title"><?php esc_html_e( 'Daily check-in', 'madara-coin-rewards' ); ?></h2>
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

			<div class="mcr-card mcr-card--claim<?php echo esc_attr( $mcr_streak < 1 ? ' mcr-card--quiet' : '' ); ?>">
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
		</section>

		<?php if ( $mcr_has_any || $mcr_claimable ) : ?>
			<section class="mcr-section" id="mcr-checkin-calendar" aria-labelledby="mcr-checkin-calendar-title">
				<header class="mcr-section__head">
					<h2 class="mcr-section__title" id="mcr-checkin-calendar-title"><?php esc_html_e( 'Reward calendar', 'madara-coin-rewards' ); ?></h2>
					<?php if ( $mcr_claimable ) : ?>
						<span class="mcr-badge mcr-badge--accent"><?php esc_html_e( 'Available now', 'madara-coin-rewards' ); ?></span>
					<?php endif; ?>
				</header>
				<div class="mcr-card mcr-card--calendar">
					<p class="mcr-card__meta"><?php esc_html_e( 'Claimed, available and upcoming daily rewards.', 'madara-coin-rewards' ); ?></p>
					<?php
					echo \MCR\Templates\Templates::part( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						'streak',
						array(
							'days_data' => $mcr_week,
							'bonuses'   => $mcr_bonuses,
							'claimable' => $mcr_claimable,
						)
					);
					?>
					<ul class="mcr-legend">
						<li class="mcr-legend__item"><span class="mcr-legend__dot is-done" aria-hidden="true"></span><?php esc_html_e( 'Claimed', 'madara-coin-rewards' ); ?></li>
						<li class="mcr-legend__item"><span class="mcr-legend__dot is-available" aria-hidden="true"></span><?php esc_html_e( 'Available', 'madara-coin-rewards' ); ?></li>
						<li class="mcr-legend__item"><span class="mcr-legend__dot is-locked" aria-hidden="true"></span><?php esc_html_e( 'Upcoming', 'madara-coin-rewards' ); ?></li>
						<li class="mcr-legend__item"><span class="mcr-legend__dot is-missed" aria-hidden="true"></span><?php esc_html_e( 'Missed', 'madara-coin-rewards' ); ?></li>
					</ul>
				</div>
			</section>
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

		<?php if ( $mcr_show_progress && ! empty( $mcr_week ) ) : ?>
			<section class="mcr-section" id="mcr-checkin-streak" aria-labelledby="mcr-checkin-streak-title">
				<header class="mcr-section__head">
					<h2 class="mcr-section__title" id="mcr-checkin-streak-title"><?php esc_html_e( 'Your streak', 'madara-coin-rewards' ); ?></h2>
					<?php if ( ! empty( $mcr_next_bonus['days'] ) ) : ?>
						<span class="mcr-badge mcr-badge--gold">
							<?php
							printf(
								/* translators: 1: days remaining, 2: bonus amount */
								esc_html__( '%1$s more days to a +%2$s bonus', 'madara-coin-rewards' ),
								esc_html( number_format_i18n( (int) $mcr_next_bonus['days'] ) ),
								esc_html( number_format_i18n( (int) $mcr_next_bonus['amount'] ) )
							);
							?>
						</span>
					<?php endif; ?>
				</header>
				<div class="mcr-card mcr-card--muted">
					<p class="mcr-muted">
						<?php if ( $mcr_streak > 0 ) : ?>
							<?php
							printf(
								/* translators: %d: streak length in days */
								esc_html__( 'You have checked in %d days in a row. Miss a day and the streak starts again from one.', 'madara-coin-rewards' ),
								(int) $mcr_streak
							);
							?>
						<?php else : ?>
							<?php esc_html_e( 'Check in every day to build a streak. The longer the streak, the bigger the milestone bonuses.', 'madara-coin-rewards' ); ?>
						<?php endif; ?>
					</p>
				</div>
			</section>
		<?php endif; ?>

		<?php if ( ! empty( $mcr_bonuses ) ) : ?>
			<section class="mcr-section" id="mcr-checkin-bonuses" aria-labelledby="mcr-checkin-bonuses-title">
				<header class="mcr-section__head">
					<h2 class="mcr-section__title" id="mcr-checkin-bonuses-title"><?php esc_html_e( 'Streak bonuses', 'madara-coin-rewards' ); ?></h2>
					<span class="mcr-muted"><?php esc_html_e( 'Bonus coins are added automatically with the check-in.', 'madara-coin-rewards' ); ?></span>
				</header>
				<div class="mcr-card mcr-card--muted">
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
				</div>
			</section>
		<?php endif; ?>

		<?php if ( ! empty( $mcr_recent ) ) : ?>
			<section class="mcr-section" id="mcr-checkin-activity" aria-labelledby="mcr-checkin-activity-title">
				<header class="mcr-section__head">
					<h2 class="mcr-section__title" id="mcr-checkin-activity-title"><?php esc_html_e( 'Recent activity', 'madara-coin-rewards' ); ?></h2>
					<?php if ( '' !== $mcr_dashboard ) : ?>
						<a class="mcr-btn mcr-btn--sm mcr-btn--quiet" href="<?php echo esc_url( $mcr_dashboard ); ?>"><?php esc_html_e( 'View all', 'madara-coin-rewards' ); ?></a>
					<?php endif; ?>
				</header>
				<div class="mcr-card mcr-card--muted">
					<ul class="mcr-txn-list">
						<?php foreach ( $mcr_recent as $mcr_txn ) : ?>
							<?php
							echo \MCR\Templates\Templates::part( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								'txn',
								array(
									'txn'          => $mcr_txn,
									'show_balance' => false,
								)
							);
							?>
						<?php endforeach; ?>
					</ul>
				</div>
			</section>
		<?php endif; ?>

		<section class="mcr-section" id="mcr-checkin-rules" aria-labelledby="mcr-checkin-rules-title">
			<header class="mcr-section__head">
				<h2 class="mcr-section__title" id="mcr-checkin-rules-title"><?php esc_html_e( 'Rules', 'madara-coin-rewards' ); ?></h2>
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
	</div>

	<div class="mcr-rewards__aside">
		<?php
		echo \MCR\Templates\Templates::part( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			'ads-block',
			array( 'page_args' => $args )
		);
		?>
	</div>
</div>
