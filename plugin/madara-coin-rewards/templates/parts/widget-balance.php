<?php
/**
 * Coin balance widget body.
 *
 * Rendered between the widget's before/after wrappers by
 * MCR\Widgets\BalanceWidget.
 *
 * @package MCR
 *
 * @var array $args {
 *     @type bool  $logged_in  Whether the visitor has an account.
 *     @type array $stats      Ledger stats for the current user.
 *     @type int   $streak     Current check-in streak (0 for guests).
 *     @type bool  $show_streak Whether the streak line was enabled.
 *     @type string $url       Rewards dashboard URL ('' disables the link).
 * }
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $args['logged_in'] ) ) {
	?><p class="mcr-muted"><?php echo esc_html__( 'Log in to see your coin balance.', 'madara-coin-rewards' ); ?></p><?php
	return;
}

$mcr_stats      = isset( $args['stats'] ) && is_array( $args['stats'] ) ? $args['stats'] : array( 'balance' => 0 );
$mcr_streak     = isset( $args['streak'] ) ? (int) $args['streak'] : 0;
$mcr_show_streak = empty( $args['show_streak'] ) ? false : true;
$mcr_url        = isset( $args['url'] ) ? (string) $args['url'] : '';
?>
<div class="mcr-widget-balance mcr-scope">
	<div class="mcr-amount mcr-amount--lg" data-mcr-balance>
		<span class="mcr-amount__value"><?php echo esc_html( number_format_i18n( (int) $mcr_stats['balance'] ) ); ?></span>
		<span class="mcr-sr-only"><?php echo esc_html__( 'coins', 'madara-coin-rewards' ); ?></span>
	</div>
<?php if ( $mcr_show_streak ) : ?>
	<p class="mcr-muted"><?php echo esc_html( sprintf( _n( 'Day %d of your streak', 'Day %d of your streak', $mcr_streak, 'madara-coin-rewards' ), $mcr_streak ) ); ?></p>
<?php endif; ?>
<?php if ( '' !== $mcr_url ) : ?>
	<a class="mcr-btn mcr-btn--sm" href="<?php echo esc_url( $mcr_url ); ?>"><?php echo esc_html__( 'View rewards', 'madara-coin-rewards' ); ?></a>
<?php endif; ?>
</div>
