<?php
/**
 * Madara Coin & Rewards admin dashboard page.
 *
 * Overview and statistics.
 *
 * @package MCR
 */

namespace MCR\Admin;

defined( 'ABSPATH' ) || exit;

use MCR\Capabilities;
use MCR\Container;
use MCR\Ledger;
use MCR\Settings;

/**
 * Renders the admin dashboard with overview statistics.
 *
 * Expensive aggregates are cached in a short-lived transient. All SQL uses
 * $wpdb->prepare() and there is no N+1 querying.
 */
class DashboardPage {

	/**
	 * Transient key for cached stats.
	 */
	const STATS_TRANSIENT = 'mcr_dashboard_stats';

	/**
	 * Transient TTL in seconds (5 minutes).
	 */
	const STATS_TTL = 300;

	/**
	 * Renders the dashboard page.
	 *
	 * @return void
	 */
	public function render() {
		?>
		<div class="wrap mcr-admin">
			<h1><?php echo esc_html__( 'Dashboard', 'madara-coin-rewards' ); ?></h1>
			<?php $this->render_notices(); ?>

			<?php $this->render_stat_cards(); ?>

			<h2><?php echo esc_html__( 'Feature Status', 'madara-coin-rewards' ); ?></h2>
			<table class="form-table mcr-admin-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Daily Check-in', 'madara-coin-rewards' ); ?></th>
					<td><?php echo Settings::featureEnabled( 'checkin' ) ? '<mark class="yes">' . esc_html__( 'Enabled', 'madara-coin-rewards' ) . '</mark>' : '<mark class="error">' . esc_html__( 'Disabled', 'madara-coin-rewards' ) . '</mark>'; ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Watch Ads', 'madara-coin-rewards' ); ?></th>
					<td><?php echo Settings::featureEnabled( 'ads' ) ? '<mark class="yes">' . esc_html__( 'Enabled', 'madara-coin-rewards' ) . '</mark>' : '<mark class="error">' . esc_html__( 'Disabled', 'madara-coin-rewards' ) . '</mark>'; ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Coin Purchases', 'madara-coin-rewards' ); ?></th>
					<td><?php echo Settings::featureEnabled( 'purchase' ) ? '<mark class="yes">' . esc_html__( 'Enabled', 'madara-coin-rewards' ) . '</mark>' : '<mark class="error">' . esc_html__( 'Disabled', 'madara-coin-rewards' ) . '</mark>'; ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Dashboard', 'madara-coin-rewards' ); ?></th>
					<td><?php echo Settings::featureEnabled( 'dashboard' ) ? '<mark class="yes">' . esc_html__( 'Enabled', 'madara-coin-rewards' ) . '</mark>' : '<mark class="error">' . esc_html__( 'Disabled', 'madara-coin-rewards' ) . '</mark>'; ?></td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Configuration', 'madara-coin-rewards' ); ?></h2>
			<table class="form-table mcr-admin-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Base check-in reward', 'madara-coin-rewards' ); ?></th>
					<td><?php echo esc_html( number_format_i18n( (int) Settings::get( 'checkin.base_amount', 0 ) ) ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Coins per ad', 'madara-coin-rewards' ); ?></th>
					<td><?php echo esc_html( number_format_i18n( (int) Settings::get( 'ads.coins_per_ad', 0 ) ) ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Daily ad limit', 'madara-coin-rewards' ); ?></th>
					<td><?php echo esc_html( number_format_i18n( (int) Settings::get( 'ads.daily_limit', 0 ) ) ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Purchase currency', 'madara-coin-rewards' ); ?></th>
					<td><?php echo esc_html( strtoupper( substr( (string) Settings::get( 'purchase.currency', 'USD' ), 0, 3 ) ) ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Configured packages', 'madara-coin-rewards' ); ?></th>
					<td><?php echo esc_html( number_format_i18n( count( (array) Settings::get( 'packages', array() ) ) ) ); ?></td>
				</tr>
			</table>
		</div>
		<?php
	}

	/**
	 * Renders stat cards with cached aggregates.
	 *
	 * @return void
	 */
	private function render_stat_cards() {
		$stats = get_transient( self::STATS_TRANSIENT );

		if ( false === $stats ) {
			$stats = $this->compute_stats();
			set_transient( self::STATS_TRANSIENT, $stats, self::STATS_TTL );
		}

		$cards = array(
			array(
				'label' => __( 'Total Users', 'madara-coin-rewards' ),
				'value' => number_format_i18n( $stats['total_users'] ),
				'color' => '#eb3349',
			),
			array(
				'label' => __( 'Coins in Circulation', 'madara-coin-rewards' ),
				'value' => number_format_i18n( $stats['total_balance'] ),
				'color' => '#75b22d',
			),
			array(
				'label' => __( 'Lifetime Earned', 'madara-coin-rewards' ),
				'value' => number_format_i18n( $stats['lifetime_earned'] ),
				'color' => '#0f0f0f',
			),
			array(
				'label' => __( 'Lifetime Spent', 'madara-coin-rewards' ),
				'value' => number_format_i18n( $stats['lifetime_spent'] ),
				'color' => '#0f0f0f',
			),
			array(
				'label' => __( 'Transactions Today', 'madara-coin-rewards' ),
				'value' => number_format_i18n( $stats['transactions_today'] ),
				'color' => '#5bc0de',
			),
			array(
				'label' => __( 'Check-ins Today', 'madara-coin-rewards' ),
				'value' => number_format_i18n( $stats['checkins_today'] ),
				'color' => '#5cb85c',
			),
		);

		echo '<div class="mcr-admin-stat-cards">';
		foreach ( $cards as $card ) {
			printf(
				'<div class="mcr-admin-stat-card" style="--mcr-stat-color: %s;">
					<span class="mcr-admin-stat-label">%s</span>
					<span class="mcr-admin-stat-value">%s</span>
				</div>',
				esc_attr( $card['color'] ),
				esc_html( $card['label'] ),
				esc_html( $card['value'] )
			);
		}
		echo '</div>';
	}

	/**
	 * Computes dashboard statistics using exact SQL with no N+1 queries.
	 *
	 * @return array
	 */
	private function compute_stats() {
		global $wpdb;

		$balances = $wpdb->prefix . 'mcr_balances';
		$ledger   = $wpdb->prefix . 'mcr_transactions';
		$checkins = $wpdb->prefix . 'mcr_checkins';

		// All day buckets come from the injectable site timezone clock.
		$clock = Container::instance()->clock();
		$today = $clock->today_key();

		$total_users = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$balances}"
		);

		$row = $wpdb->get_row(
			"SELECT
				COALESCE(SUM(balance), 0) AS total_balance,
				COALESCE(SUM(lifetime_earned), 0) AS lifetime_earned,
				COALESCE(SUM(lifetime_spent), 0) AS lifetime_spent
			FROM {$balances}",
			\ARRAY_A
		);

		// created_at is stored as GMT, so bound the GMT day from the clock
		// with a sargable range instead of DATE(created_at).
		$gmt_today = substr( $clock->mysql_gmt(), 0, 10 );
		$day_start = $gmt_today . ' 00:00:00';
		$parts     = array_map( 'intval', explode( '-', $gmt_today ) );
		$day_end   = gmdate( 'Y-m-d 00:00:00', gmmktime( 0, 0, 0, $parts[1], $parts[2] + 1, $parts[0] ) );

		$transactions_today = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$ledger} WHERE created_at >= %s AND created_at < %s",
			$day_start,
			$day_end
		) );

		$checkins_today = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$checkins} WHERE checkin_date = %s",
			$today
		) );

		return array(
			'total_users'      => $total_users,
			'total_balance'    => (int) ( $row['total_balance'] ?? 0 ),
			'lifetime_earned'  => (int) ( $row['lifetime_earned'] ?? 0 ),
			'lifetime_spent'   => (int) ( $row['lifetime_spent'] ?? 0 ),
			'transactions_today'=> $transactions_today,
			'checkins_today'   => $checkins_today,
		);
	}

	/**
	 * Renders admin notices stored in a transient.
	 *
	 * @return void
	 */
	private function render_notices() {
		$notice = get_transient( 'mcr_admin_notice' );
		if ( $notice ) {
			delete_transient( 'mcr_admin_notice' );
			$class = isset( $notice['class'] ) ? esc_attr( $notice['class'] ) : 'notice-info';
			printf(
				'<div class="notice %s is-dismissible"><p>%s</p></div>',
				$class,
				esc_html( $notice['message'] )
			);
		}
	}
}
