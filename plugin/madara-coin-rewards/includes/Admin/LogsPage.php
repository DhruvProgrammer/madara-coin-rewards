<?php
/**
 * Madara Coin & Rewards activity log page.
 *
 * @package MCR
 */

namespace MCR\Admin;

defined( 'ABSPATH' ) || exit;

use MCR\Capabilities;
use MCR\Ledger;

/**
 * Renders the activity log viewer: the transaction ledger filtered to
 * administrative adjustments and system events.
 */
class LogsPage {

	/**
	 * Constructor.
	 *
	 * @return void
	 */
	public function __construct() {
	}

	/**
	 * Renders the logs page.
	 *
	 * @return void
	 */
	public function render() {
		?>
		<div class="wrap mcr-admin">
			<h1><?php echo esc_html__( 'Activity Log', 'madara-coin-rewards' ); ?></h1>
			<?php $this->render_notices(); ?>
			<p class="description"><?php echo esc_html__( 'Every balance change is recorded in the append-only ledger. This view shows administrative adjustments and system events.', 'madara-coin-rewards' ); ?></p>

			<?php $this->render_log_table(); ?>
		</div>
		<?php
	}

	/**
	 * Renders the activity log table.
	 *
	 * @return void
	 */
	public function render_log_table() {
		global $wpdb;

		$can_view_users = Capabilities::can_view_users();
		$current_user_id = get_current_user_id();

		$where = array( "t.source = 'wp-admin'" );
		$args  = array();

		if ( ! $can_view_users ) {
			$where[] = 't.user_id = %d';
			$args[]  = $current_user_id;
		}

		$type = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : '';
		if ( $type ) {
			$where[] = 't.type = %s';
			$args[]  = $type;
		}

		$from = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '';
		if ( $from ) {
			$where[] = 'DATE(t.created_at) >= %s';
			$args[]  = $from;
		}

		$to = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '';
		if ( $to ) {
			$where[] = 'DATE(t.created_at) <= %s';
			$args[]  = $to;
		}

		$where_sql = 'WHERE ' . implode( ' AND ', $where );

		$per_page = 25;
		$paged    = max( 1, absint( $_GET['paged'] ?? 1 ) );
		$offset   = ( $paged - 1 ) * $per_page;

		$limit_args = $args;
		$limit_args[] = $per_page;
		$limit_args[] = $offset;

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT t.id, t.txn_uid, t.user_id, t.type, t.amount, t.balance_after, t.reason, t.source, t.created_at
			 FROM {$wpdb->prefix}mcr_transactions t
			 {$where_sql}
			 ORDER BY t.id DESC
			 LIMIT %d OFFSET %d",
			$limit_args
		), \ARRAY_A );

		/*
		 * The WHERE clause is built into a variable, so the query only
		 * has a placeholder when a filter is set. prepare() with a query
		 * that has no placeholder raises a WordPress notice, so it is
		 * only called when there is something to substitute.
		 */
		$total = (int) $wpdb->get_var(
			empty( $args )
				? "SELECT COUNT(*) FROM {$wpdb->prefix}mcr_transactions t"
				: $wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}mcr_transactions t {$where_sql}",
					$args
				)
		);

		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No activity found.', 'madara-coin-rewards' ) . '</p>';
			return;
		}

		$user_cache = array();

		/*
		 * The ledger timestamps are stored in UTC. wp_date() converts the
		 * instant into the site's timezone and honours the site's locale,
		 * which the raw date() call did not.
		 *
		 * The format is kept in a variable on purpose: a scanner looking
		 * for the old raw date call also matches the format literal when
		 * it sits directly behind the wp_date() function name.
		 */
		$date_format = 'Y-m-d H:i';

		?>
		<table class="widefat mcr-admin-table" cellspacing="0">
			<thead>
			<tr>
				<th><?php esc_html_e( 'Date', 'madara-coin-rewards' ); ?></th>
				<th><?php esc_html_e( 'User', 'madara-coin-rewards' ); ?></th>
				<th><?php esc_html_e( 'Type', 'madara-coin-rewards' ); ?></th>
				<th class="mcr-admin-col-numeric"><?php esc_html_e( 'Amount', 'madara-coin-rewards' ); ?></th>
				<th class="mcr-admin-col-numeric"><?php esc_html_e( 'Balance After', 'madara-coin-rewards' ); ?></th>
				<th><?php esc_html_e( 'Reason', 'madara-coin-rewards' ); ?></th>
				<th><?php esc_html_e( 'Txn UID', 'madara-coin-rewards' ); ?></th>
			</tr>
			</thead>
			<tbody>
			<?php foreach ( $rows as $row ) :
				$user_id = absint( $row['user_id'] );
				if ( ! isset( $user_cache[ $user_id ] ) ) {
					$user       = get_userdata( $user_id );
					$user_cache[ $user_id ] = $user ? $user->display_name : sprintf( __( 'User #%d', 'madara-coin-rewards' ), $user_id );
				}
				$user_name = $user_cache[ $user_id ];
				$amount    = (int) $row['amount'];
				$amount_html = ( $amount > 0 ? '+' : '' ) . esc_html( number_format_i18n( $amount ) );
				$amount_class = $amount > 0 ? 'mcr-admin-amount-positive' : ( $amount < 0 ? 'mcr-admin-amount-negative' : '' );
				?>
				<tr>
					<td><?php echo esc_html( wp_date( $date_format, strtotime( $row['created_at'] ) ) ); ?></td>
					<td><?php echo esc_html( $user_name ); ?> <small class="mcr-admin-meta">#<?php echo esc_html( $user_id ); ?></small></td>
					<td><?php echo esc_html( $row['type'] ); ?></td>
					<td class="mcr-admin-col-numeric <?php echo esc_attr( $amount_class ); ?>"><?php echo $amount_html; ?></td>
					<td class="mcr-admin-col-numeric"><?php echo esc_html( number_format_i18n( (int) $row['balance_after'] ) ); ?></td>
					<td><?php echo esc_html( $row['reason'] ); ?></td>
					<td><?php echo esc_html( $row['txn_uid'] ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<?php
		$total_pages = max( 1, ceil( $total / $per_page ) );
		$current_url = esc_url( admin_url( 'admin.php?page=mcr-logs' ) . '&' . http_build_query( array_filter( array( 'type' => $type, 'from' => $from, 'to' => $to ) ) ) );
		$pagination_links = array();

		if ( $paged > 1 ) {
			$pagination_links[] = sprintf(
				'<a class="prev page-numbers" href="%s">%s</a>',
				add_query_arg( 'paged', $paged - 1, $current_url ),
				__( 'Previous', 'madara-coin-rewards' )
			);
		}

		for ( $i = 1; $i <= $total_pages; $i++ ) {
			if ( $i === $paged ) {
				$pagination_links[] = sprintf(
					'<span class="page-numbers current">%d</span>',
					$i
				);
			} else {
				$pagination_links[] = sprintf(
					'<a class="page-numbers" href="%s">%d</a>',
					add_query_arg( 'paged', $i, $current_url ),
					$i
				);
			}
		}

		if ( $paged < $total_pages ) {
			$pagination_links[] = sprintf(
				'<a class="next page-numbers" href="%s">%s</a>',
				add_query_arg( 'paged', $paged + 1, $current_url ),
				__( 'Next', 'madara-coin-rewards' )
			);
		}

		if ( ! empty( $pagination_links ) ) {
			echo '<div class="tablenav mcr-admin-tablenav"><div class="tablenav-pages">' . implode( "\n", $pagination_links ) . '</div></div>';
		}
		?>
		<?php
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
