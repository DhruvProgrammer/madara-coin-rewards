<?php
/**
 * The watch-ads panel, rendered from the page arguments.
 *
 * Every check-in layout uses this block, so the ad entry point is always
 * on the check-in page and always looks the same.
 *
 * @package MCR
 *
 * @var array $args Partial arguments.
 * @var array $args['page_args'] The page arguments.
 */

defined( 'ABSPATH' ) || exit;

$mcr_page = isset( $args['page_args'] ) && is_array( $args['page_args'] ) ? $args['page_args'] : array();

echo \MCR\Templates\Templates::part( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	'ads-panel',
	array(
		'enabled'        => ! empty( $mcr_page['ads_enabled'] ),
		'configured'     => ! empty( $mcr_page['ads_configured'] ),
		'units'          => isset( $mcr_page['ad_units'] ) && is_array( $mcr_page['ad_units'] ) ? $mcr_page['ad_units'] : array(),
		'ads'            => isset( $mcr_page['ads'] ) && is_array( $mcr_page['ads'] ) ? $mcr_page['ads'] : array(),
		'urls'           => isset( $mcr_page['urls'] ) && is_array( $mcr_page['urls'] ) ? $mcr_page['urls'] : array(),
		'coins_per_ad'   => isset( $mcr_page['ads']['coins_per_ad'] ) ? (int) $mcr_page['ads']['coins_per_ad'] : 0,
		'daily_limit'    => isset( $mcr_page['ads']['limit'] ) ? (int) $mcr_page['ads']['limit'] : 0,
		'cooldown'       => isset( $mcr_page['ads_cooldown'] ) ? (int) $mcr_page['ads_cooldown'] : 0,
		'reset_deadline' => isset( $mcr_page['ads_reset'] ) ? (string) $mcr_page['ads_reset'] : '',
	)
);
