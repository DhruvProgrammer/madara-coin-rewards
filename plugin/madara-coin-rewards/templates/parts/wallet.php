<?php
/**
 * The wallet header: who the reader is and what they are holding.
 *
 * The balance element carries the "data-mcr-balance" attribute, so the
 * script repaints it the moment a reward is credited, without a page
 * reload.
 *
 * @package MCR
 *
 * @var array $args Partial arguments:
 * @var bool  $args['logged_in']    Whether somebody is signed in.
 * @var array $args['stats']        The ledger statistics.
 * @var array $args['profile']      The display name and the avatar URL.
 * @var int   $args['streak']       The current streak, in days.
 * @var int   $args['today_earned'] The coins earned today.
 * @var int   $args['next_bonus_days'] Days until the next bonus.
 * @var int   $args['next_bonus_amount'] The next bonus amount.
 * @var string $args['login_url']   Where to sign in.
 */

defined( 'ABSPATH' ) || exit;

$mcr_logged_in  = ! empty( $args['logged_in'] );
$mcr_stats      = isset( $args['stats'] ) && is_array( $args['stats'] ) ? $args['stats'] : array();
$mcr_profile    = isset( $args['profile'] ) && is_array( $args['profile'] ) ? $args['profile'] : array();
$mcr_streak     = isset( $args['streak'] ) ? max( 0, (int) $args['streak'] ) : 0;
$mcr_today      = isset( $args['today_earned'] ) ? max( 0, (int) $args['today_earned'] ) : 0;
$mcr_bonus_days = isset( $args['next_bonus_days'] ) ? max( 0, (int) $args['next_bonus_days'] ) : 0;
$mcr_bonus_amt  = isset( $args['next_bonus_amount'] ) ? max( 0, (int) $args['next_bonus_amount'] ) : 0;
$mcr_login_url  = isset( $args['login_url'] ) ? (string) $args['login_url'] : '';

$mcr_balance   = isset( $mcr_stats['balance'] ) ? max( 0, (int) $mcr_stats['balance'] ) : 0;
$mcr_earned    = isset( $mcr_stats['lifetime_earned'] ) ? max( 0, (int) $mcr_stats['lifetime_earned'] ) : 0;
$mcr_spent     = isset( $mcr_stats['lifetime_spent'] ) ? max( 0, (int) $mcr_stats['lifetime_spent'] ) : 0;
$mcr_name      = isset( $mcr_profile['name'] ) ? (string) $mcr_profile['name'] : '';
$mcr_avatar    = isset( $mcr_profile['avatar'] ) ? (string) $mcr_profile['avatar'] : '';
$mcr_initials  = isset( $mcr_profile['initials'] ) ? (string) $mcr_profile['initials'] : '';
?>
<div class="mcr-wallet">
	<div class="mcr-wallet__profile">
		<span class="mcr-wallet__avatar" aria-hidden="true">
			<?php if ( '' !== $mcr_avatar ) : ?>
				<img src="<?php echo esc_attr( esc_url( $mcr_avatar ) ); ?>" alt="" width="56" height="56" loading="lazy" decoding="async" />
			<?php elseif ( '' !== $mcr_initials ) : ?>
				<span class="mcr-wallet__initials"><?php echo esc_html( $mcr_initials ); ?></span>
			<?php else : ?>
				<span class="mcr-wallet__initials">&#9734;</span>
			<?php endif; ?>
		</span>
		<div class="mcr-wallet__identity">
			<span class="mcr-wallet__name" title="<?php echo esc_attr( $mcr_name ); ?>">
				<?php
				if ( '' !== $mcr_name ) {
					echo esc_html( $mcr_name );
				} else {
					esc_html_e( 'Your coin wallet', 'madara-coin-rewards' );
				}
				?>
			</span>
			<span class="mcr-wallet__meta">
				<?php if ( $mcr_streak > 0 ) : ?>
					<?php
					printf(
						/* translators: %d: streak length in days */
						esc_html__( '%d day streak', 'madara-coin-rewards' ),
						(int) $mcr_streak
					);
					?>
				<?php else : ?>
					<?php esc_html_e( 'Check in daily to start a streak', 'madara-coin-rewards' ); ?>
				<?php endif; ?>
			</span>
		</div>
	</div>

	<div class="mcr-wallet__balance">
		<span class="mcr-wallet__label"><?php esc_html_e( 'Your balance', 'madara-coin-rewards' ); ?></span>
		<?php if ( $mcr_logged_in ) : ?>
			<?php
			/*
			 * The hook the script repaints: the balance changes in place
			 * the moment a reward is credited, without a page reload.
			 */
			echo \MCR\Templates\Templates::amount( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				$mcr_balance,
				'xl',
				array( 'data-mcr-balance' => '' )
			);
			?>
			<span class="mcr-wallet__meta">
				<?php if ( $mcr_today > 0 ) : ?>
					<?php
					printf(
						/* translators: %s: coin amount */
						esc_html__( '+%s coins earned today', 'madara-coin-rewards' ),
						esc_html( number_format_i18n( $mcr_today ) )
					);
					?>
				<?php elseif ( $mcr_bonus_days > 0 ) : ?>
					<?php
					printf(
						/* translators: 1: days remaining, 2: bonus amount */
						esc_html__( '%1$s more days to a +%2$s bonus', 'madara-coin-rewards' ),
						esc_html( number_format_i18n( $mcr_bonus_days ) ),
						esc_html( number_format_i18n( $mcr_bonus_amt ) )
					);
					?>
				<?php else : ?>
					<?php esc_html_e( 'Check in now to start earning', 'madara-coin-rewards' ); ?>
				<?php endif; ?>
			</span>
		<?php else : ?>
			<span class="mcr-wallet__guest"><?php esc_html_e( 'Sign in to see your balance and start earning coins.', 'madara-coin-rewards' ); ?></span>
			<?php if ( '' !== $mcr_login_url ) : ?>
				<a class="mcr-btn mcr-btn--lg mcr-btn--theme" href="<?php echo esc_url( $mcr_login_url ); ?>"><?php esc_html_e( 'Sign in to earn coins', 'madara-coin-rewards' ); ?></a>
			<?php endif; ?>
		<?php endif; ?>
	</div>

	<?php if ( $mcr_logged_in ) : ?>
		<div class="mcr-wallet__stats">
			<div class="mcr-wallet__stat">
				<span class="mcr-wallet__stat-label"><?php esc_html_e( 'Earned today', 'madara-coin-rewards' ); ?></span>
				<span class="mcr-wallet__stat-value"><?php echo esc_html( number_format_i18n( $mcr_today ) ); ?></span>
			</div>
			<div class="mcr-wallet__stat">
				<span class="mcr-wallet__stat-label"><?php esc_html_e( 'Lifetime earned', 'madara-coin-rewards' ); ?></span>
				<span class="mcr-wallet__stat-value"><?php echo esc_html( number_format_i18n( $mcr_earned ) ); ?></span>
			</div>
			<div class="mcr-wallet__stat">
				<span class="mcr-wallet__stat-label"><?php esc_html_e( 'Lifetime spent', 'madara-coin-rewards' ); ?></span>
				<span class="mcr-wallet__stat-value"><?php echo esc_html( number_format_i18n( $mcr_spent ) ); ?></span>
			</div>
		</div>
	<?php endif; ?>
</div>
