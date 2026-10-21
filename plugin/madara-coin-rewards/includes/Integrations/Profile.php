<?php
/**
 * User profile integration.
 *
 * @package MCR
 */

namespace MCR\Integrations;

use MCR\Ledger;
use MCR\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Adds a coin balance panel to the WordPress user profile.
 */
class Profile {

	/**
	 * Renders the coin balance panel on the profile screen.
	 *
	 * @param \WP_User $user The user being displayed.
	 * @return void
	 */
	public static function render_profile_panel( $user ) {
		if ( ! $user instanceof \WP_User ) {
			return;
		}

		// A user may only see their own balance unless they can manage coins.
		$can_view_all = current_user_can( 'mcr_manage_coins' ) || current_user_can( 'manage_options' );

		if ( ! $can_view_all && get_current_user_id() !== (int) $user->ID ) {
			return;
		}

		$stats = Ledger::instance()->get_stats( (int) $user->ID );

		$checked_in = false;

		if ( class_exists( '\MCR\Rewards\CheckIn' ) ) {
			$status     = \MCR\Rewards\CheckIn::instance()->status( (int) $user->ID );
			$checked_in = ! empty( $status['claimed_today'] );
		}
		?>
		<h2 id="mcr-coins"><?php esc_html_e( 'Madara Coins', 'madara-coin-rewards' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><?php esc_html_e( 'Coin balance', 'madara-coin-rewards' ); ?></th>
				<td>
					<strong><?php echo esc_html( number_format_i18n( $stats['balance'] ) ); ?></strong>
					<?php esc_html_e( 'coins', 'madara-coin-rewards' ); ?>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Lifetime earned', 'madara-coin-rewards' ); ?></th>
				<td><?php echo esc_html( number_format_i18n( $stats['lifetime_earned'] ) ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Lifetime spent', 'madara-coin-rewards' ); ?></th>
				<td><?php echo esc_html( number_format_i18n( $stats['lifetime_spent'] ) ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Purchased coins', 'madara-coin-rewards' ); ?></th>
				<td><?php echo esc_html( number_format_i18n( $stats['purchased_total'] ) ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Daily check-in', 'madara-coin-rewards' ); ?></th>
				<td>
					<?php
					if ( $checked_in ) {
						esc_html_e( 'Completed today', 'madara-coin-rewards' );
					} else {
						esc_html_e( 'Not claimed yet today', 'madara-coin-rewards' );
					}
					?>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Handles the profile panel save.
	 *
	 * The panel is read-only, so nothing is written. The hook exists so
	 * that future editable fields have a safe place to live.
	 *
	 * @param int $user_id User id.
	 * @return void
	 */
	public static function save_profile_panel( $user_id ) {
		unset( $user_id );
	}
}
