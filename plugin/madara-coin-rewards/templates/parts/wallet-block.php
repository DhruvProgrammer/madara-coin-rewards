<?php
/**
 * The wallet header, rendered from the page arguments.
 *
 * Every check-in layout uses this block, so the wallet looks the same and
 * behaves the same whichever layout the site has chosen.
 *
 * @package MCR
 *
 * @var array $args Partial arguments.
 * @var array $args['page_args'] The page arguments.
 */

defined( 'ABSPATH' ) || exit;

$mcr_page = isset( $args['page_args'] ) && is_array( $args['page_args'] ) ? $args['page_args'] : array();

echo \MCR\Templates\Templates::part( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	'wallet',
	array(
		'logged_in'         => ! empty( $mcr_page['user_id'] ),
		'stats'             => isset( $mcr_page['stats'] ) && is_array( $mcr_page['stats'] ) ? $mcr_page['stats'] : array(),
		'profile'           => isset( $mcr_page['profile'] ) && is_array( $mcr_page['profile'] ) ? $mcr_page['profile'] : array(),
		'streak'            => isset( $mcr_page['streak'] ) ? (int) $mcr_page['streak'] : 0,
		'today_earned'      => isset( $mcr_page['today_earned'] ) ? (int) $mcr_page['today_earned'] : 0,
		'next_bonus_days'   => isset( $mcr_page['next_bonus']['days'] ) ? (int) $mcr_page['next_bonus']['days'] : 0,
		'next_bonus_amount' => isset( $mcr_page['next_bonus']['amount'] ) ? (int) $mcr_page['next_bonus']['amount'] : 0,
		'login_url'         => isset( $mcr_page['urls']['login'] ) ? (string) $mcr_page['urls']['login'] : '',
	)
);
