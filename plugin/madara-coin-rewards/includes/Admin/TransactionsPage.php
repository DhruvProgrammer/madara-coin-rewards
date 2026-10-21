<?php
/**
 * Madara Coin & Rewards transactions page.
 *
 * Ledger browser with filters and CSV export.
 *
 * @package MCR
 */

namespace MCR\Admin;

defined( 'ABSPATH' ) || exit;

use MCR\Capabilities;
use MCR\Ledger;

/**
 * Browses the transaction ledger with filters and supports CSV export via
 * admin_post (capability + nonce).
 */
class TransactionsPage {

	/**
	 * Renders the transactions page.
	 *
	 * @return void
	 */
	public function render() {
		?>
		<div class="wrap mcr-admin">
			<h1><?php echo esc_html__( 'Transaction Ledger', 'madara-coin-rewards' ); ?></h1>
			<?php $this->render_notices(); ?>

			<form method="get" action="admin.php">
				<input type="hidden" name="page" value="mcr-transactions" />
				<div class="mcr-admin-filters">
					<label for="mcr_filter_user"><?php esc_html_e( 'User', 'madara-coin-rewards' ); ?></label>
					<input type="text" id="mcr_filter_user" name="user" value="<?php echo esc_attr( $_GET['user'] ?? '' ); ?>" class="regular-text" />

					<label for="mcr_filter_type"><?php esc_html_e( 'Type', 'madara-coin-rewards' ); ?></label>
					<select id="mcr_filter_type" name="type" class="regular-text">
						<option value=""><?php esc_html_e( 'All types', 'madara-coin-rewards' ); ?></option>
						<?php foreach ( Ledger::transaction_types() as $type ) : ?>
							<option value="<?php echo esc_attr( $type ); ?>"<?php selected( $_GET['type'] ?? '', $type ); ?>><?php echo esc_html( $type ); ?></option>
						<?php endforeach; ?>
					</select>

					<label for="mcr_filter_from"><?php esc_html_e( 'From', 'madara-coin-rewards' ); ?></label>
					<input type="date" id="mcr_filter_from" name="from" value="<?php echo esc_attr( $_GET['from'] ?? '' ); ?>" />

					<label for="mcr_filter_to"><?php esc_html_e( 'To', 'madara-coin-rewards' ); ?></label>
					<input type="date" id="mcr_filter_to" name="to" value="<?php echo esc_attr( $_GET['to'] ?? '' ); ?>" />

					<label for="mcr_filter_search"><?php esc_html_e( 'Search reason', 'madara-coin-rewards' ); ?></label>
					<input type="text" id="mcr_filter_search" name="search" value="<?php echo esc_attr( $_GET['search'] ?? '' ); ?>" class="regular-text" />

					<button type="submit" class="button"><?php esc_html_e( 'Filter', 'madara-coin-rewards' ); ?></button>
					<a href="admin.php?page=mcr-transactions" class="button button-secondary"><?php esc_html_e( 'Reset', 'madara-coin-rewards' ); ?></a>

				</div>
			</form>

			<?php if ( Capabilities::can_manage_coins() ) : ?>
				<?php
				/*
				 * The export form is a sibling of the filter form, not a
				 * child of it. HTML does not allow nested forms, so the
				 * browser dropped the inner one and the CSV button
				 * submitted the filter form instead.
				 */
				?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php
					wp_nonce_field( 'mcr_admin_action', '_wpnonce' );
					echo '<input type="hidden" name="action" value="mcr_export_transactions" />';
					// Pass through current filters as hidden fields.
					foreach ( array( 'user', 'type', 'from', 'to', 'search' ) as $k ) {
						if ( isset( $_GET[ $k ] ) ) {
							echo '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( wp_unslash( $_GET[ $k ] ) ) . '" />';
						}
					}
					?>
					<button type="submit" class="button button-secondary"><?php esc_html_e( 'Export CSV', 'madara-coin-rewards' ); ?></button>
				</form>
			<?php endif; ?>

			<?php $this->render_ledger_table(); ?>
		</div>
		<?php
	}

	/**
	 * Renders the ledger table with pagination.
	 *
	 * @return void
	 */
	public function render_ledger_table() {
		global $wpdb;

		$current_user_id = get_current_user_id();
		$can_view_users  = Capabilities::can_view_users();

		$where = array();
		$args  = array();

		// Privacy: non-admins only see their own transactions.
		if ( ! $can_view_users ) {
			$where[] = 't.user_id = %d';
			$args[]  = $current_user_id;
		}

		$filters = array(
			'user'   => isset( $_GET['user'] ) ? sanitize_text_field( wp_unslash( $_GET['user'] ) ) : '',
			'type'   => isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : '',
			'from'   => isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '',
			'to'     => isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '',
			'search' => isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '',
		);

		if ( $filters['user'] && $can_view_users ) {
			$uid = absint( $filters['user'] );
			if ( $uid ) {
				$where[] = 't.user_id = %d';
				$args[]  = $uid;
			}
		}

		if ( $filters['type'] ) {
			$where[] = 't.type = %s';
			$args[]  = $filters['type'];
		}

		if ( $filters['from'] ) {
			$where[] = 'DATE(t.created_at) >= %s';
			$args[]  = $filters['from'];
		}

		if ( $filters['to'] ) {
			$where[] = 'DATE(t.created_at) <= %s';
			$args[]  = $filters['to'];
		}

		if ( $filters['search'] ) {
			$where[] = 't.reason LIKE %s';
			$args[]  = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
		}

		$where_sql = '' === implode( ' ', $where ) ? '' : 'WHERE ' . implode( ' AND ', $where );

		$per_page = 25;
		$paged    = max( 1, absint( $_GET['paged'] ?? 1 ) );
		/*
		 * The offset is capped. MySQL has to walk the index entries it
		 * skips, so an unbounded page number turns into a very slow
		 * query.
		 */
		$offset   = min( ( $paged - 1 ) * $per_page, 100000 );

		$args_for_limit = $args;
		$args_for_limit[] = $per_page;
		$args_for_limit[] = $offset;

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT t.id, t.txn_uid, t.user_id, t.type, t.amount, t.balance_after, t.reason, t.source, t.created_at
			 FROM {$wpdb->prefix}mcr_transactions t
			 {$where_sql}
			 ORDER BY t.id DESC
			 LIMIT %d OFFSET %d",
			$args_for_limit
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
			echo '<p>' . esc_html__( 'No transactions found.', 'madara-coin-rewards' ) . '</p>';
			return;
		}

		$user_cache = array();

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
				<th><?php esc_html_e( 'Source', 'madara-coin-rewards' ); ?></th>
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
					<td><?php echo esc_html( date( 'Y-m-d H:i', strtotime( $row['created_at'] ) ) ); ?></td>
					<td><?php echo esc_html( $user_name ); ?> <small class="mcr-admin-meta">#<?php echo esc_html( $user_id ); ?></small></td>
					<td><?php echo esc_html( $row['type'] ); ?></td>
					<td class="mcr-admin-col-numeric <?php echo esc_attr( $amount_class ); ?>"><?php echo $amount_html; ?></td>
					<td class="mcr-admin-col-numeric"><?php echo esc_html( number_format_i18n( (int) $row['balance_after'] ) ); ?></td>
					<td><?php echo esc_html( $row['reason'] ); ?></td>
					<td><?php echo esc_html( $row['source'] ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<?php
		$total_pages = max( 1, ceil( $total / $per_page ) );
		$current_url = esc_url( admin_url( 'admin.php?page=mcr-transactions' ) . '&' . http_build_query( array_filter( $filters ) ) );
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
	 * Handles CSV export via admin_post.
	 *
	 * Verifies a nonce and a capability, then streams a CSV of the filtered
	 * ledger. Spreadsheet formula cells (starting with = + - @) are prefixed
	 * with a single quote to prevent formula injection.
	 *
	 * @return void
	 */
	public static function export_via_admin_post() {
		if ( ! Capabilities::can_manage_coins() ) {
			wp_die( __( 'Insufficient permissions.', 'madara-coin-rewards' ) );
		}

		check_admin_referer( 'mcr_admin_action' );

		$current_user_id = get_current_user_id();
		$can_view_users  = Capabilities::can_view_users();

		global $wpdb;

		$where = array();
		$args  = array();

		if ( ! $can_view_users ) {
			$where[] = 't.user_id = %d';
			$args[]  = $current_user_id;
		}

		$filters = array(
			'user'   => isset( $_POST['user'] ) ? sanitize_text_field( wp_unslash( $_POST['user'] ) ) : '',
			'type'   => isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : '',
			'from'   => isset( $_POST['from'] ) ? sanitize_text_field( wp_unslash( $_POST['from'] ) ) : '',
			'to'     => isset( $_POST['to'] ) ? sanitize_text_field( wp_unslash( $_POST['to'] ) ) : '',
			'search' => isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '',
		);

		if ( $filters['user'] && $can_view_users ) {
			$uid = absint( $filters['user'] );
			if ( $uid ) {
				$where[] = 't.user_id = %d';
				$args[]  = $uid;
			}
		}

		if ( $filters['type'] ) {
			$where[] = 't.type = %s';
			$args[]  = $filters['type'];
		}

		if ( $filters['from'] ) {
			$where[] = 'DATE(t.created_at) >= %s';
			$args[]  = $filters['from'];
		}

		if ( $filters['to'] ) {
			$where[] = 'DATE(t.created_at) <= %s';
			$args[]  = $filters['to'];
		}

		if ( $filters['search'] ) {
			$where[] = 't.reason LIKE %s';
			$args[]  = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
		}

		$where_sql = '' === implode( ' ', $where ) ? '' : 'WHERE ' . implode( ' AND ', $where );

		global $wpdb;

		/*
		 * The ledger grows without bound, so the export is streamed in
		 * chunks rather than loaded into memory in one go. A single
		 * SELECT over the whole ledger would exhaust the memory limit
		 * on a site with a long history.
		 */
		$chunk_size = 500;
		$max_rows   = 100000;

		$user_cache = array();

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="mcr_transactions_' . date( 'Ymd_His' ) . '.csv"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output = fopen( 'php://output', 'w' );

		$headers = array(
			__( 'Date', 'madara-coin-rewards' ),
			__( 'User ID', 'madara-coin-rewards' ),
			__( 'User', 'madara-coin-rewards' ),
			__( 'Type', 'madara-coin-rewards' ),
			__( 'Amount', 'madara-coin-rewards' ),
			__( 'Balance After', 'madara-coin-rewards' ),
			__( 'Reason', 'madara-coin-rewards' ),
			__( 'Source', 'madara-coin-rewards' ),
			__( 'Txn UID', 'madara-coin-rewards' ),
		);

		// 'self::method' as a string callable is deprecated since PHP 8.2.
		$headers = array_map( array( __CLASS__, 'escape_csv_cell' ), $headers );
		fputcsv( $output, $headers );

		$exported = 0;
		$offset   = 0;

		while ( $exported < $max_rows ) {
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT t.id, t.txn_uid, t.user_id, t.type, t.amount, t.balance_after, t.reason, t.source, t.created_at
				 FROM {$wpdb->prefix}mcr_transactions t
				 {$where_sql}
				 ORDER BY t.id DESC
				 LIMIT %d OFFSET %d",
				array_merge( $args, array( $chunk_size, $offset ) )
			), \ARRAY_A );

			if ( empty( $rows ) ) {
				break;
			}

			foreach ( $rows as $row ) {
				$user_id = absint( $row['user_id'] );
				if ( ! isset( $user_cache[ $user_id ] ) ) {
					$user = get_userdata( $user_id );
					$user_cache[ $user_id ] = $user ? $user->display_name : sprintf( __( 'User #%d', 'madara-coin-rewards' ), $user_id );
				}

				$row_out = array(
					date( 'Y-m-d H:i:s', strtotime( $row['created_at'] ) ),
					(int) $user_id,
					self::escape_csv_cell( $user_cache[ $user_id ] ),
					self::escape_csv_cell( $row['type'] ),
					(int) $row['amount'],
					(int) $row['balance_after'],
					self::escape_csv_cell( $row['reason'] ),
					self::escape_csv_cell( $row['source'] ),
					self::escape_csv_cell( $row['txn_uid'] ),
				);

				fputcsv( $output, $row_out );
			}

			$exported += count( $rows );
			$offset   += count( $rows );

			if ( count( $rows ) < $chunk_size ) {
				break;
			}
		}

		fclose( $output );
		exit;
	}

	/**
	 * Escapes a CSV cell to prevent spreadsheet formula injection.
	 *
	 * Cells starting with =, +, -, or @ are prefixed with a single quote so
	 * spreadsheet software treats them as literal text.
	 *
	 * @param string $value Cell value.
	 * @return string
	 */
	private static function escape_csv_cell( $value ) {
		$value = (string) $value;
		$first = substr( $value, 0, 1 );
		if ( in_array( $first, array( '=', '+', '-', '@' ), true ) ) {
			$value = "'" . $value;
		}
		return $value;
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
