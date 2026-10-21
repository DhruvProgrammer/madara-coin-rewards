<?php
/**
 * Watch ads page.
 *
 * The earning interface is a shared partial, so this page and the daily
 * check-in page can never drift apart.
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

$mcr_enabled  = ! empty( $args['enabled'] );
$mcr_configured = ! empty( $args['configured'] );
$mcr_recent   = isset( $args['recent'] ) && is_array( $args['recent'] ) ? $args['recent'] : array();
$mcr_urls     = isset( $args['urls'] ) && is_array( $args['urls'] ) ? $args['urls'] : array();
$mcr_txn_url  = isset( $mcr_urls['transactions'] ) ? (string) $mcr_urls['transactions'] : '';
?>
<?php
echo \MCR\Templates\Templates::part( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	'ads-panel',
	array(
		'enabled'        => $mcr_enabled,
		'configured'     => $mcr_configured,
		'units'          => isset( $args['ad_units'] ) && is_array( $args['ad_units'] ) ? $args['ad_units'] : array(),
		'ads'            => isset( $args['ads'] ) && is_array( $args['ads'] ) ? $args['ads'] : array(),
		'urls'           => $mcr_urls,
		'coins_per_ad'   => isset( $args['coins_per_ad'] ) ? (int) $args['coins_per_ad'] : 0,
		'daily_limit'    => isset( $args['daily_limit'] ) ? (int) $args['daily_limit'] : 0,
		'cooldown'       => isset( $args['cooldown'] ) ? (int) $args['cooldown'] : 0,
		'reset_deadline' => isset( $args['reset_deadline'] ) ? (string) $args['reset_deadline'] : '',
	)
);
?>

<section class="mcr-section" id="mcr-ads-recent" aria-labelledby="mcr-ads-recent-title">
	<header class="mcr-section__head">
		<h2 class="mcr-section__title c-blog__heading" id="mcr-ads-recent-title"><?php esc_html_e( 'Recent ad rewards', 'madara-coin-rewards' ); ?></h2>
		<?php if ( '' !== $mcr_txn_url ) : ?>
			<a class="mcr-section__action" href="<?php echo esc_url( $mcr_txn_url ); ?>"><?php esc_html_e( 'View all', 'madara-coin-rewards' ); ?></a>
		<?php endif; ?>
	</header>

	<div class="mcr-card">
		<?php if ( empty( $mcr_recent ) ) : ?>
			<?php
			echo \MCR\Templates\Templates::part( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				'empty',
				array(
					'icon'  => '&#9654;',
					'title' => __( 'No ad rewards yet', 'madara-coin-rewards' ),
					'text'  => __( 'Rewards from ads will be listed here once you watch your first ad.', 'madara-coin-rewards' ),
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
