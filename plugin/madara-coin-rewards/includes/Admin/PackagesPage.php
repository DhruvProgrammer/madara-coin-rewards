<?php
/**
 * Madara Coin & Rewards coin package editor.
 *
 * @package MCR
 */

namespace MCR\Admin;

defined( 'ABSPATH' ) || exit;

use MCR\Capabilities;
use MCR\Settings;

/**
 * Renders the coin package editor: a list of configured packages plus an
 * add/edit form. Packages are stored in the 'packages' key of mcr_settings.
 */
class PackagesPage {

	/**
	 * @var array List of sanitized packages keyed by package id.
	 */
	private $packages = array();

	/**
	 * Constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->packages = (array) Settings::get( 'packages', array() );
		uksort( $this->packages, function ( $a, $b ) {
			$sa = isset( $this->packages[$a]['sort_order'] ) ? (int) $this->packages[$a]['sort_order'] : 0;
			$sb = isset( $this->packages[$b]['sort_order'] ) ? (int) $this->packages[$b]['sort_order'] : 0;
			if ( $sa === $sb ) {
				return strcmp( $a, $b );
			}
			return $sa < $sb ? -1 : 1;
		} );

		$this->register_package_fields();
	}

	/**
	 * Renders the packages page.
	 *
	 * @return void
	 */
	public function render() {
		?>
		<div class="wrap mcr-admin">
			<h1><?php echo esc_html__( 'Coin Packages', 'madara-coin-rewards' ); ?></h1>
			<?php $this->render_notices(); ?>

			<div class="mcr-admin-card">
				<h2><?php echo esc_html__( 'Add / Edit Package', 'madara-coin-rewards' ); ?></h2>
				<form method="post" action="options.php">
					<?php
					settings_fields( 'mcr_settings_group' );
					do_settings_fields( 'mcr-packages', 'mcr_section_packages' );
					/*
					 * Without this the form has no submit control, so the
					 * textarea could never be posted and packages could never
					 * be saved from this screen.
					 */
					submit_button( __( 'Save Packages', 'madara-coin-rewards' ) );
					?>
				</form>
			</div>

			<h2><?php echo esc_html__( 'Configured Packages', 'madara-coin-rewards' ); ?></h2>
			<?php $this->render_packages_table(); ?>
		</div>
		<?php
	}

	/**
	 * Registers the package editor fields with the Settings API.
	 *
	 * @return void
	 */
	public function register_package_fields() {
		add_settings_section(
			'mcr_section_packages',
			__( 'Coin Package Editor', 'madara-coin-rewards' ),
			array( $this, 'render_section_packages' ),
			'mcr-packages'
		);

		add_settings_field(
			'mcr_field_packages',
			__( 'Packages', 'madara-coin-rewards' ),
			array( $this, 'render_field_packages' ),
			'mcr-packages',
			'mcr_section_packages',
			array()
		);
	}

	/**
	 * Section description.
	 *
	 * @return void
	 */
	public function render_section_packages() {
		echo '<p class="description">' . esc_html__( 'Define coin packages users can purchase. Each package maps a price to a coin amount with an optional bonus percent.', 'madara-coin-rewards' ) . '</p>';
	}

	/**
	 * Renders the packages JSON editor field.
	 *
	 * @return void
	 */
	public function render_field_packages() {
		$value = wp_json_encode( $this->packages, JSON_PRETTY_PRINT );
		?>
		<textarea name="mcr_settings[packages]" id="mcr_settings_packages" rows="10" class="large-text code"><?php echo esc_textarea( $value ); ?></textarea>
		<p class="description"><?php echo esc_html__( 'Edit the packages as a JSON array. Each item: id, title, coins, price, currency, badge, active, sort_order, bonus_percent, limit.', 'madara-coin-rewards' ) . '</p>';
	}

	/**
	 * Renders the configured packages table.
	 *
	 * @return void
	 */
	public function render_packages_table() {
		if ( empty( $this->packages ) ) {
			echo '<p>' . esc_html__( 'No packages configured yet.', 'madara-coin-rewards' ) . '</p>';
			return;
		}
		?>
		<table class="widefat mcr-admin-table" cellspacing="0">
			<thead>
			<tr>
				<th><?php esc_html_e( 'ID', 'madara-coin-rewards' ); ?></th>
				<th><?php esc_html_e( 'Title', 'madara-coin-rewards' ); ?></th>
				<th class="mcr-admin-col-numeric"><?php esc_html_e( 'Coins', 'madara-coin-rewards' ); ?></th>
				<th class="mcr-admin-col-numeric"><?php esc_html_e( 'Price', 'madara-coin-rewards' ); ?></th>
				<th class="mcr-admin-col-numeric"><?php esc_html_e( 'Bonus %', 'madara-coin-rewards' ); ?></th>
				<th class="mcr-admin-col-numeric"><?php esc_html_e( 'Purchased', 'madara-coin-rewards' ); ?></th>
				<th><?php esc_html_e( 'Active', 'madara-coin-rewards' ); ?></th>
			</tr>
			</thead>
			<tbody>
			<?php foreach ( $this->packages as $id => $pkg ) : ?>
				<tr>
					<td><?php echo esc_html( $id ); ?></td>
					<td><?php echo esc_html( $pkg['title'] ?? '' ); ?></td>
					<td class="mcr-admin-col-numeric"><?php echo esc_html( number_format_i18n( (int) ( $pkg['coins'] ?? 0 ) ) ); ?></td>
					<td class="mcr-admin-col-numeric"><?php echo esc_html( number_format_i18n( (float) ( $pkg['price'] ?? 0 ), 2 ) ); ?> <?php echo esc_html( strtoupper( substr( (string) ( $pkg['currency'] ?? 'USD' ), 0, 3 ) ) ); ?></td>
					<td class="mcr-admin-col-numeric"><?php echo esc_html( (int) ( $pkg['bonus_percent'] ?? 0 ) ); ?>%</td>
					<td class="mcr-admin-col-numeric"><?php echo esc_html( number_format_i18n( (int) ( $pkg['purchased'] ?? 0 ) ) ); ?></td>
					<td><?php echo ( 'on' === ( $pkg['active'] ?? 'off' ) ) ? '<mark class="yes">' . esc_html__( 'Active', 'madara-coin-rewards' ) . '</mark>' : '<mark class="error">' . esc_html__( 'Inactive', 'madara-coin-rewards' ) . '</mark>'; ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
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
