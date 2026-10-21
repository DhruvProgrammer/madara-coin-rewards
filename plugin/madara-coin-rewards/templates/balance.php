<?php
/**
 * Balance panel.
 *
 * Three layouts share one rule: the number comes from the server and is only
 * ever repainted from a server response. `show_balance` is ignored here - the
 * panel *is* the balance - but `show_progress` removes the streak bar.
 *
 * @package MCR
 *
 * @var array $args Template arguments.
 */

defined( 'ABSPATH' ) || exit;

$mcr_stats      = isset( $args['stats'] ) && is_array( $args['stats'] ) ? $args['stats'] : array();
$mcr_checkin    = isset( $args['checkin'] ) && is_array( $args['checkin'] ) ? $args['checkin'] : array();
$mcr_urls       = isset( $args['urls'] ) && is_array( $args['urls'] ) ? $args['urls'] : array();
$mcr_next_bonus = isset( $args['next_bonus'] ) && is_array( $args['next_bonus'] ) ? $args['next_bonus'] : array();
$mcr_balance    = isset( $mcr_stats['balance'] ) ? (int) $mcr_stats['balance'] : 0;
$mcr_streak     = isset( $mcr_checkin['streak'] ) ? max( 0, (int) $mcr_checkin['streak'] ) : 0;
$mcr_show_progress = ! isset( $args['show_progress'] ) || $args['show_progress'];
$mcr_layout     = isset( $args['layout'] ) ? sanitize_key( (string) $args['layout'] ) : 'default';

$mcr_checkin_url = isset( $mcr_urls['checkin'] ) ? (string) $mcr_urls['checkin'] : '';
$mcr_txn_url     = isset( $mcr_urls['transactions'] ) ? (string) $mcr_urls['transactions'] : '';
$mcr_rewards_url = isset( $mcr_urls['dashboard'] ) ? (string) $mcr_urls['dashboard'] : $mcr_checkin_url;

$mcr_target = isset( $mcr_next_bonus['threshold'] ) ? (int) $mcr_next_bonus['threshold'] : 30;
$mcr_target = $mcr_target > 0 ? $mcr_target : 30;
?>

<?php if ( 'inline' === $mcr_layout ) : ?>
	<section class="mcr-section" aria-label="<?php esc_attr_e( 'Your balance', 'madara-coin-rewards' ); ?>">
		<div class="mcr-row mcr-row--between">
			<?php echo \MCR\Templates\Templates::amount( $mcr_balance, 'sm', array( 'data-mcr-balance' => '1' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<a class="mcr-section__action" href="<?php echo esc_url( $mcr_rewards_url ); ?>"><?php esc_html_e( 'Rewards', 'madara-coin-rewards' ); ?></a>
		</div>
	</section>
<?php elseif ( 'hero' === $mcr_layout ) : ?>
	<section class="mcr-section mcr-center" aria-label="<?php esc_attr_e( 'Your balance', 'madara-coin-rewards' ); ?>">
		<?php echo \MCR\Templates\Templates::amount( $mcr_balance, 'xl', array( 'data-mcr-balance' => '1' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<p class="mcr-amount__label"><?php esc_html_e( 'available coins', 'madara-coin-rewards' ); ?></p>

		<?php if ( $mcr_show_progress && $mcr_streak > 0 ) : ?>
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
		<?php endif; ?>

		<div class="mcr-actions" style="justify-content:center">
			<a class="mcr-btn mcr-btn--lg" href="<?php echo esc_url( $mcr_checkin_url ); ?>"><?php esc_html_e( 'Earn coins', 'madara-coin-rewards' ); ?></a>
		</div>
	</section>
<?php else : ?>
	<section class="mcr-section" aria-label="<?php esc_attr_e( 'Your balance', 'madara-coin-rewards' ); ?>">
		<div class="mcr-card">
			<div class="mcr-card__header">
				<h2 class="mcr-card__title"><?php esc_html_e( 'Your balance', 'madara-coin-rewards' ); ?></h2>
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
				<?php echo \MCR\Templates\Templates::amount( $mcr_balance, 'lg', array( 'data-mcr-balance' => '1' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<p class="mcr-amount__label"><?php esc_html_e( 'available coins', 'madara-coin-rewards' ); ?></p>

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
				<?php endif; ?>
			</div>

			<div class="mcr-card__footer">
				<a class="mcr-btn" href="<?php echo esc_url( $mcr_checkin_url ); ?>"><?php esc_html_e( 'Earn coins', 'madara-coin-rewards' ); ?></a>
				<a class="mcr-btn mcr-btn--quiet" href="<?php echo esc_url( $mcr_txn_url ); ?>"><?php esc_html_e( 'History', 'madara-coin-rewards' ); ?></a>
			</div>
		</div>
	</section>
<?php endif;
