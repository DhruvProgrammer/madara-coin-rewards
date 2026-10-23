<?php
/**
 * Madara Coin & Rewards users page.
 *
 * User balances list table plus grant/deduct actions.
 *
 * @package MCR
 */

namespace MCR\Admin;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

use MCR\Capabilities;
use MCR\Ledger;

/**
 * Lists user balances and supports grant/deduct adjustments through the
 * auditable ledger. Non-admins see only their own balance.
 */
class UsersPage extends \WP_List_Table {

	/**
	 * Whether the current user may view other users' data.
	 *
	 * @var bool
	 */
	private $can_view_users = false;

	/**
	 * Constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		parent::__construct( array(
			'plural' => 'users',
			'singular' => 'user',
			'ajax'   => false,
		) );

		$this->can_view_users = Capabilities::can_view_users();
	}

	/**
	 * Renders the users page.
	 *
	 * @return void
	 */
	public function render() {
		?>
		<div class="wrap mcr-admin">
			<h1><?php echo esc_html__( 'User Balances', 'madara-coin-rewards' ); ?></h1>
			<?php $this->render_grant_deduct_form(); ?>
			<?php $this->render_notices(); ?>
			<form method="post">
				<?php
				wp_nonce_field( 'mcr_admin_action', '_wpnonce' );
				echo '<input type="hidden" name="mcr_action" value="mcr_grant_deduct" />';
				$this->display();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Prepares the list of users to display.
	 *
	 * @return void
	 */
	public function prepare_items() {
		global $wpdb;

		$columns = $this->get_columns();
		$hidden  = array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		// Pagination.
		$per_page = 20;
		$paged    = max( 1, absint( $_GET['paged'] ?? 1 ) );
		$offset   = ( $paged - 1 ) * $per_page;

		$orderby = 'balance';
		$order   = 'DESC';
		if ( isset( $_GET['orderby'] ) && in_array( $_GET['orderby'], array( 'balance', 'lifetime_earned', 'lifetime_spent', 'user_login', 'user_email' ), true ) ) {
			$orderby = sanitize_key( wp_unslash( $_GET['orderby'] ) );
		}
		if ( isset( $_GET['order'] ) && in_array( strtoupper( sanitize_key( wp_unslash( $_GET['order'] ) ) ), array( 'ASC', 'DESC' ), true ) ) {
			$order = strtoupper( sanitize_key( wp_unslash( $_GET['order'] ) ));
		}

		// Build the user query. Admins see all; others see only themselves.
		$current_user_id = get_current_user_id();

		$user_ids = $this->can_view_users
			? $this->get_all_user_ids( $per_page, $offset, $orderby, $order )
			: array( $current_user_id );

		$total = $this->can_view_users
			? $this->count_all_users()
			: 1;

		$this->items = array();

		foreach ( $user_ids as $user_id ) {
			$user = get_userdata( $user_id );
			if ( ! $user ) {
				continue;
			}

			$stats = Ledger::instance()->get_stats( $user_id );

			$this->items[ $user_id ] = array(
				'user_id'        => $user_id,
				'user_login'     => $user->user_login,
				'display_name'   => $user->display_name,
				'user_email'     => $user->user_email,
				'balance'        => $stats['balance'],
				'lifetime_earned'=> $stats['lifetime_earned'],
				'lifetime_spent' => $stats['lifetime_spent'],
			);
		}

		$this->set_pagination_args( array(
			'total_items' => $total,
			'per_page'    => $per_page,
		) );
	}

	/**
	 * Returns the list of columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'cb'               => '<input type="checkbox" />',
			'user'             => __( 'User', 'madara-coin-rewards' ),
			'balance'          => __( 'Balance', 'madara-coin-rewards' ),
			'lifetime_earned'  => __( 'Lifetime Earned', 'madara-coin-rewards' ),
			'lifetime_spent'   => __( 'Lifetime Spent', 'madara-coin-rewards' ),
			'actions'          => __( 'Actions', 'madara-coin-rewards' ),
		);
	}

	/**
	 * Returns sortable columns.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return array(
			'balance'          => array( 'balance', false ),
			'lifetime_earned'  => array( 'lifetime_earned', false ),
			'lifetime_spent'   => array( 'lifetime_spent', false ),
			'user_login'       => array( 'user_login', false ),
		);
	}

	/**
	 * Returns bulk actions.
	 *
	 * @return array
	 */
	public function get_bulk_actions() {
		return array();
	}

	/**
	 * Default column rendering.
	 *
	 * @param array $item Item.
	 * @param string $column Column name.
	 * @return string
	 */
	public function column_default( $item, $column ) {
		switch ( $column ) {
			case 'user':
				return $this->column_user( $item );
			case 'balance':
				return $this->column_balance( $item );
			case 'lifetime_earned':
				return $this->column_lifetime_earned( $item );
			case 'lifetime_spent':
				return $this->column_lifetime_spent( $item );
			case 'actions':
				return $this->column_actions( $item );
			default:
				return esc_html( $item[ $column ] ?? '' );
		}
	}

	/**
	 * Renders the user column.
	 *
	 * @param array $item Item.
	 * @return string
	 */
	public function column_user( $item ) {
		$name  = ! empty( $item['display_name'] ) ? $item['display_name'] : $item['user_login'];
		$login = esc_html( $item['user_login'] );
		$email = esc_html( $item['user_email'] );
		$url   = esc_url( admin_url( 'user-edit.php?user_id=' . absint( $item['user_id'] ) ) );
		$out  = '<strong><a href="' . $url . '">' . esc_html( $name ) . '</a></strong>';
		$out .= '<br /><small class="mcr-admin-meta">' . esc_html( $login ) . '</small>';
		$out .= '<br /><small class="mcr-admin-meta">' . $email . '</small>';
		return $out;
	}

	/**
	 * Renders the balance column.
	 *
	 * @param array $item Item.
	 * @return string
	 */
	public function column_balance( $item ) {
		return '<strong>' . esc_html( number_format_i18n( (int) $item['balance'] ) ) . '</strong>';
	}

	/**
	 * Renders the lifetime earned column.
	 *
	 * @param array $item Item.
	 * @return string
	 */
	public function column_lifetime_earned( $item ) {
		return esc_html( number_format_i18n( (int) $item['lifetime_earned'] ) );
	}

	/**
	 * Renders the lifetime spent column.
	 *
	 * @param array $item Item.
	 * @return string
	 */
	public function column_lifetime_spent( $item ) {
		return esc_html( number_format_i18n( (int) $item['lifetime_spent'] ) );
	}

	/**
	 * Renders the actions column.
	 *
	 * @param array $item Item.
	 * @return string
	 */
	public function column_actions( $item ) {
		$user_id = absint( $item['user_id'] );
		$url     = esc_url( admin_url( 'user-edit.php?user_id=' . $user_id ) );

		return sprintf(
			'<a class="button mcr-admin-button-small" href="%s">%s</a>',
			$url,
			esc_html__( 'Edit User', 'madara-coin-rewards' )
		);
	}

	/**
	 * Returns the cb column (row checkbox).
	 *
	 * @param array $item Item.
	 * @return string
	 */
	public function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="user[]" value="%d" />',
			absint( $item['user_id'] )
		);
	}

	/**
	 * Returns all user IDs ordered by balance, paginated.
	 *
	 * @param int $per_page Rows per page.
	 * @param int $offset   Offset.
	 * @param string $orderby Order column.
	 * @param string $order Order direction.
	 * @return array
	 */
	private function get_all_user_ids( $per_page, $offset, $orderby, $order ) {
		global $wpdb;

		$orderby_sql = ( 'user_login' === $orderby || 'user_email' === $orderby )
			? $wpdb->users . '.' . $orderby
			: $wpdb->prefix . 'mcr_balances.' . $orderby;

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT u.ID FROM {$wpdb->users} u
			 JOIN {$wpdb->prefix}mcr_balances b ON b.user_id = u.ID
			 ORDER BY {$orderby_sql} {$order}
			 LIMIT %d OFFSET %d",
			$per_page,
			$offset
		) );

		$ids = array();
		foreach ( $rows as $row ) {
			$ids[] = (int) $row->ID;
		}

		return $ids;
	}

	/**
	 * Counts all users with a balance row.
	 *
	 * @return int
	 */
	private function count_all_users() {
		global $wpdb;

		// No WHERE clause, so there is nothing to prepare.
		return (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT u.ID) FROM {$wpdb->users} u
			 JOIN {$wpdb->prefix}mcr_balances b ON b.user_id = u.ID"
		);
	}

	/**
	 * Renders the grant/deduct form.
	 *
	 * @return void
	 */
	private function render_grant_deduct_form() {
		if ( ! Capabilities::can_manage_coins() ) {
			return;
		}
		?>
		<div class="mcr-admin-card">
			<h2><?php echo esc_html__( 'Grant / Deduct Coins', 'madara-coin-rewards' ); ?></h2>
			<form method="post" action="admin.php?page=mcr-users">
				<?php
				wp_nonce_field( 'mcr_admin_action' );
				echo '<input type="hidden" name="mcr_action" value="mcr_grant_deduct" />';
				?>
				<?php
				/*
				 * Every label carries a "for" attribute that points at the id
				 * of the control it names. A label without a control is
				 * invisible to a screen reader and to a click target.
				 */
				?>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="mcr_grant_user_id"><?php esc_html_e( 'User ID', 'madara-coin-rewards' ); ?></label></th>
						<td><input type="number" name="user_id" id="mcr_grant_user_id" min="1" class="small-text" required="required" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="mcr_grant_amount"><?php esc_html_e( 'Amount', 'madara-coin-rewards' ); ?></label></th>
						<td><input type="number" name="amount" id="mcr_grant_amount" min="1" class="small-text" required="required" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="mcr_grant_operation_grant"><?php esc_html_e( 'Operation', 'madara-coin-rewards' ); ?></label></th>
						<td>
							<label for="mcr_grant_operation_grant"><input type="radio" id="mcr_grant_operation_grant" name="operation" value="grant" checked="checked" /> <?php esc_html_e( 'Grant (credit)', 'madara-coin-rewards' ); ?></label>
							&nbsp;
							<label for="mcr_grant_operation_deduct"><input type="radio" id="mcr_grant_operation_deduct" name="operation" value="deduct" /> <?php esc_html_e( 'Deduct (debit)', 'madara-coin-rewards' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="mcr_grant_reason"><?php esc_html_e( 'Reason', 'madara-coin-rewards' ); ?> <span class="mcr-admin-required">*</span></label></th>
						<td><input type="text" name="reason" id="mcr_grant_reason" class="regular-text" required="required" /></td>
					</tr>
				</table>
				<?php submit_button( __( 'Adjust Balance', 'madara-coin-rewards' ), 'button-primary', 'mcr_adjust_submit' ); ?>
			</form>
		</div>
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
