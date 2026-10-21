<?php
/**
 * Madara Coin & Rewards admin tools page.
 *
 * Uninstall, export, recalculate balances, reset a user.
 *
 * @package MCR
 */

namespace MCR\Admin;

defined( 'ABSPATH' ) || exit;

use MCR\Capabilities;
use MCR\Ledger;
use MCR\Settings;

/**
 * Renders the admin tools: balance recalculation, user reset, settings
 * export, and uninstall helper.
 */
class ToolsPage {

	/**
	 * Renders the tools page.
	 *
	 * @return void
	 */
	public function render() {
		?>
		<div class="wrap mcr-admin">
			<h1><?php echo esc_html__( 'Tools', 'madara-coin-rewards' ); ?></h1>
			<?php $this->render_notices(); ?>

			<h2><?php esc_html_e( 'Recalculate Balances', 'madara-coin-rewards' ); ?></h2>
			<p class="description"><?php echo esc_html__( 'Recomputes every user balance from the append-only ledger and fixes any divergence.', 'madara-coin-rewards' ); ?></p>
			<form method="post" action="admin.php?page=mcr-tools">
				<?php
				wp_nonce_field( 'mcr_admin_action' );
				echo '<input type="hidden" name="mcr_action" value="mcr_reconcile_all" />';
				?>
				<?php submit_button( __( 'Recalculate All Balances', 'madara-coin-rewards' ), 'button-primary', 'mcr_reconcile_submit' ); ?>
			</form>

			<h2><?php esc_html_e( 'Reset a User Balance', 'madara-coin-rewards' ); ?></h2>
			<p class="description"><?php echo esc_html__( 'Sets a user balance to zero by recording an admin_adjust debit. A reason is required for auditability.', 'madara-coin-rewards' ); ?></p>
			<form method="post" action="admin.php?page=mcr-tools">
				<?php
				wp_nonce_field( 'mcr_admin_action' );
				echo '<input type="hidden" name="mcr_action" value="mcr_reset_user" />';
				?>
				<table class="form-table">
					<tr>
						<th scope="row"><label><?php esc_html_e( 'User ID', 'madara-coin-rewards' ); ?></label></th>
						<td><input type="number" name="user_id" min="1" class="small-text" required="required" /></td>
					</tr>
					<tr>
						<th scope="row"><label><?php esc_html_e( 'Reason', 'madara-coin-rewards' ); ?> <span class="mcr-admin-required">*</span></label></th>
						<td><input type="text" name="reason" class="regular-text" required="required" /></td>
					</tr>
				</table>
				<?php submit_button( __( 'Reset Balance to Zero', 'madara-coin-rewards' ), 'button-primary', 'mcr_reset_submit' ); ?>
			</form>

			<h2><?php esc_html_e( 'Export Data', 'madara-coin-rewards' ); ?></h2>
			<p class="description"><?php echo esc_html__( 'Export plugin data for backup or migration.', 'madara-coin-rewards' ); ?></p>
			<form method="post" action="admin.php?page=mcr-tools">
				<?php
				wp_nonce_field( 'mcr_admin_action' );
				echo '<input type="hidden" name="mcr_action" value="mcr_export_settings" />';
				?>
				<?php submit_button( __( 'Export Settings', 'madara-coin-rewards' ), 'button-secondary', 'mcr_export_settings_submit' ); ?>
			</form>

			<h2><?php esc_html_e( 'Uninstall Helper', 'madara-coin-rewards' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Clears plugin settings and transients. Database tables are preserved unless you remove them via uninstall.php.', 'madara-coin-rewards' ); ?></p>
			<form method="post" action="admin.php?page=mcr-tools">
				<?php
				wp_nonce_field( 'mcr_admin_action' );
				echo '<input type="hidden" name="mcr_action" value="mcr_uninstall" />';
				?>
				<table class="form-table">
					<tr>
						<th scope="row"><label><?php esc_html_e( 'Confirm', 'madara-coin-rewards' ); ?> <span class="mcr-admin-required">*</span></label></th>
						<td>
							<input type="text" name="confirm" class="regular-text" />
							<p class="description"><?php esc_html_e( 'Type exactly: yes-delete-everything', 'madara-coin-rewards' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Clear Settings', 'madara-coin-rewards' ), 'button-secondary', 'mcr_uninstall_submit' ); ?>
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
