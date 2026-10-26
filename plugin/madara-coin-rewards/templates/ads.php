<?php
/**
 * Watch ads page.
 *
 * A reward button is only rendered when a provider is configured: without a
 * provider nothing can verify that an ad finished, so the page explains the
 * state instead of offering a button that cannot pay.
 *
 * @package MCR
 *
 * @var array $args Template arguments.
 */

defined( 'ABSPATH' ) || exit;

$mcr_enabled     = ! empty( $args['enabled'] );
$mcr_configured  = ! empty( $args['configured'] );
$mcr_units       = isset( $args['ad_units'] ) && is_array( $args['ad_units'] ) ? $args['ad_units'] : array();
$mcr_ads         = isset( $args['ads'] ) && is_array( $args['ads'] ) ? $args['ads'] : array();
$mcr_recent      = isset( $args['recent'] ) && is_array( $args['recent'] ) ? $args['recent'] : array();
$mcr_urls        = isset( $args['urls'] ) && is_array( $args['urls'] ) ? $args['urls'] : array();
$mcr_coins       = isset( $args['coins_per_ad'] ) ? max( 0, (int) $args['coins_per_ad'] ) : 0;
$mcr_limit       = isset( $args['daily_limit'] ) ? max( 0, (int) $args['daily_limit'] ) : 0;
$mcr_cooldown    = isset( $args['cooldown'] ) ? max( 0, (int) $args['cooldown'] ) : 0;
$mcr_today       = isset( $mcr_ads['today'] ) ? max( 0, (int) $mcr_ads['today'] ) : 0;
$mcr_remaining   = isset( $mcr_ads['remaining'] ) ? max( 0, (int) $mcr_ads['remaining'] ) : max( 0, $mcr_limit - $mcr_today );
$mcr_reset       = isset( $args['reset_deadline'] ) ? (string) $args['reset_deadline'] : '';
$mcr_txn_url     = isset( $mcr_urls['transactions'] ) ? (string) $mcr_urls['transactions'] : '';
$mcr_capped      = $mcr_limit > 0 && $mcr_remaining < 1;

if ( $mcr_enabled && $mcr_configured && empty( $mcr_units ) ) {
	$mcr_units = array(
		array(
			'id'    => 'rewarded_video',
			'label' => __( 'Rewarded video', 'madara-coin-rewards' ),
		),
	);
}
?>
<?php echo \MCR\Templates\Templates::part( 'ads-panel', $args ); ?>

<section class="mcr-section" id="mcr-ads-recent" aria-labelledby="mcr-ads-recent-title">
	<header class="mcr-section__head">
		<h2 class="mcr-section__title font-heading" id="mcr-ads-recent-title"><?php esc_html_e( 'Recent ad rewards', 'madara-coin-rewards' ); ?></h2>
		<a class="mcr-section__action" href="<?php echo esc_url( $mcr_txn_url ); ?>"><?php esc_html_e( 'View all', 'madara-coin-rewards' ); ?></a>
	</header>

	<div class="mcr-card">
		<?php if ( empty( $mcr_recent ) ) : ?>
			<?php
			echo \MCR\Templates\Templates::part( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				'empty',
				array(
					'icon'  => '&#9654;',
					'title' => __( 'No ad rewards yet', 'madara-coin-rewards' ),
					'text'  => __( 'Rewards from ads will be listed here.', 'madara-coin-rewards' ),
					'bare'  => true,
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
