<?php
/**
 * Madara Coin & Rewards admin settings page.
 *
 * Settings API screens for check-in, ads, purchases, templates, pages,
 * security, and notifications.
 *
 * @package MCR
 */

namespace MCR\Admin;

defined( 'ABSPATH' ) || exit;

use MCR\Settings;

/**
 * Renders the Madara Coins settings screen using the WordPress Settings API.
 *
 * All settings are stored in the single 'mcr_settings' option (an array of
 * dot-notation keys) and sanitized by MCR\Settings::sanitize().
 */
class SettingsPage {

	/**
	 * Option group name used by register_setting().
	 */
	const OPTION_GROUP = 'mcr_settings_group';

	/**
	 * Settings page slug.
	 */
	const PAGE_SLUG = 'mcr-settings';

	/**
	 * Registered sections and fields.
	 *
	 * @var array
	 */
	private $sections = array();

	/**
	 * Constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->sections = $this->default_sections();
	}

	/**
	 * Renders the settings page.
	 *
	 * @return void
	 */
	public function render() {
		?>
		<div class="wrap mcr-admin">
			<h1><?php echo esc_html__( 'Settings', 'madara-coin-rewards' ); ?></h1>
			<?php $this->render_notices(); ?>
			<form method="post" action="options.php">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Registers all settings sections and fields with WordPress.
	 *
	 * @return void
	 */
	public function register_sections_and_fields() {
		add_settings_section(
			'mcr_section_checkin',
			__( 'Daily Check-in', 'madara-coin-rewards' ),
			array( $this, 'render_section_checkin' ),
			self::PAGE_SLUG
		);

		add_settings_section(
			'mcr_section_ads',
			__( 'Watch Ads to Earn', 'madara-coin-rewards' ),
			array( $this, 'render_section_ads' ),
			self::PAGE_SLUG
		);

		add_settings_section(
			'mcr_section_purchase',
			__( 'Coin Purchases', 'madara-coin-rewards' ),
			array( $this, 'render_section_purchase' ),
			self::PAGE_SLUG
		);

		add_settings_section(
			'mcr_section_templates',
			__( 'Templates & Layout', 'madara-coin-rewards' ),
			array( $this, 'render_section_templates' ),
			self::PAGE_SLUG
		);

		add_settings_section(
			'mcr_section_pages',
			__( 'Page Assignments', 'madara-coin-rewards' ),
			array( $this, 'render_section_pages' ),
			self::PAGE_SLUG
		);

		add_settings_section(
			'mcr_section_security',
			__( 'Security', 'madara-coin-rewards' ),
			array( $this, 'render_section_security' ),
			self::PAGE_SLUG
		);

		add_settings_section(
			'mcr_section_notifications',
			__( 'Notifications', 'madara-coin-rewards' ),
			array( $this, 'render_section_notifications' ),
			self::PAGE_SLUG
		);

		// --- Daily Check-in fields ---
		add_settings_field(
			'mcr_field_features_checkin_enabled',
			__( 'Enable daily check-in', 'madara-coin-rewards' ),
			array( $this, 'render_field_checkbox' ),
			self::PAGE_SLUG,
			'mcr_section_checkin',
			array(
				'key'   => 'features.checkin_enabled',
				'label' => __( 'Allow users to check in once per day and earn coins.', 'madara-coin-rewards' ),
			)
		);

		add_settings_field(
			'mcr_field_checkin_base_amount',
			__( 'Base coins per check-in', 'madara-coin-rewards' ),
			array( $this, 'render_field_number' ),
			self::PAGE_SLUG,
			'mcr_section_checkin',
			array(
				'key'   => 'checkin.base_amount',
				'label' => __( 'Coins earned for a standard daily check-in.', 'madara-coin-rewards' ),
				'min'   => 0,
				'step'  => 1,
			)
		);

		add_settings_field(
			'mcr_field_checkin_bonuses',
			__( 'Streak bonuses', 'madara-coin-rewards' ),
			array( $this, 'render_field_textarea' ),
			self::PAGE_SLUG,
			'mcr_section_checkin',
			array(
				'key'      => 'checkin.bonuses',
				'label'    => __( 'Bonus coins per streak threshold (JSON map of day => amount).', 'madara-coin-rewards' ),
				'rows'     => 4,
				'placeholder' => wp_kses_post( __( '{"3": 5, "7": 15, "30": 50}', 'madara-coin-rewards' ) ),
			)
		);

		add_settings_field(
			'mcr_field_checkin_streak_enabled',
			__( 'Enable streak tracking', 'madara-coin-rewards' ),
			array( $this, 'render_field_checkbox' ),
			self::PAGE_SLUG,
			'mcr_section_checkin',
			array(
				'key'   => 'checkin.streak_enabled',
				'label' => __( 'Track consecutive-day streaks and award streak bonuses.', 'madara-coin-rewards' ),
			)
		);

		// --- Watch Ads fields ---
		add_settings_field(
			'mcr_field_features_ads_enabled',
			__( 'Enable ad rewards', 'madara-coin-rewards' ),
			array( $this, 'render_field_checkbox' ),
			self::PAGE_SLUG,
			'mcr_section_ads',
			array(
				'key'   => 'features.ads_enabled',
				'label' => __( 'Allow users to watch ads to earn coins.', 'madara-coin-rewards' ),
			)
		);

		add_settings_field(
			'mcr_field_ads_coins_per_ad',
			__( 'Coins per ad', 'madara-coin-rewards' ),
			array( $this, 'render_field_number' ),
			self::PAGE_SLUG,
			'mcr_section_ads',
			array(
				'key'   => 'ads.coins_per_ad',
				'label' => __( 'Coins awarded for each verified ad view.', 'madara-coin-rewards' ),
				'min'   => 0,
				'step'  => 1,
			)
		);

		add_settings_field(
			'mcr_field_ads_daily_limit',
			__( 'Daily ad limit per user', 'madara-coin-rewards' ),
			array( $this, 'render_field_number' ),
			self::PAGE_SLUG,
			'mcr_section_ads',
			array(
				'key'   => 'ads.daily_limit',
				'label' => __( 'Maximum number of ads a user can watch per day.', 'madara-coin-rewards' ),
				'min'   => 0,
				'step'  => 1,
			)
		);

		add_settings_field(
			'mcr_field_ads_cooldown_seconds',
			__( 'Cooldown between ads (seconds)', 'madara-coin-rewards' ),
			array( $this, 'render_field_number' ),
			self::PAGE_SLUG,
			'mcr_section_ads',
			array(
				'key'   => 'ads.cooldown_seconds',
				'label' => __( 'Minimum seconds between two ad reward grants.', 'madara-coin-rewards' ),
				'min'   => 0,
				'step'  => 1,
			)
		);

		// --- Coin Purchases fields ---
		add_settings_field(
			'mcr_field_features_purchase_enabled',
			__( 'Enable coin purchases', 'madara-coin-rewards' ),
			array( $this, 'render_field_checkbox' ),
			self::PAGE_SLUG,
			'mcr_section_purchase',
			array(
				'key'   => 'features.purchase_enabled',
				'label' => __( 'Allow users to buy coin packages.', 'madara-coin-rewards' ),
			)
		);

		add_settings_field(
			'mcr_field_purchase_currency',
			__( 'Currency', 'madara-coin-rewards' ),
			array( $this, 'render_field_text' ),
			self::PAGE_SLUG,
			'mcr_section_purchase',
			array(
				'key'   => 'purchase.currency',
				'label' => __( 'ISO 4217 currency code used for coin packages.', 'madara-coin-rewards' ),
			)
		);

		add_settings_field(
			'mcr_field_purchase_gateway',
			__( 'Payment gateway', 'madara-coin-rewards' ),
			array( $this, 'render_field_select' ),
			self::PAGE_SLUG,
			'mcr_section_purchase',
			array(
				'key'    => 'purchase.gateway',
				'label'  => __( 'Default gateway used when no gateway is selected at checkout.', 'madara-coin-rewards' ),
				'options' => array(
					'manual'       => __( 'Manual (admin-marked paid)', 'madara-coin-rewards' ),
					'stripe'       => __( 'Stripe', 'madara-coin-rewards' ),
					'woocommerce'  => __( 'WooCommerce', 'madara-coin-rewards' ),
				),
			)
		);

		add_settings_field(
			'mcr_field_purchase_auto_credit',
			__( 'Auto-credit on payment', 'madara-coin-rewards' ),
			array( $this, 'render_field_checkbox' ),
			self::PAGE_SLUG,
			'mcr_section_purchase',
			array(
				'key'   => 'purchase.auto_credit',
				'label' => __( 'Automatically credit coins when a payment webhook is verified.', 'madara-coin-rewards' ),
			)
		);

		// --- Templates & Layout fields ---
		$layouts = array(
			'default' => __( 'Default', 'madara-coin-rewards' ),
			'compact' => __( 'Compact', 'madara-coin-rewards' ),
			'wide'    => __( 'Wide', 'madara-coin-rewards' ),
		);

		foreach ( array( 'checkin', 'dashboard', 'ads', 'buy_coins', 'transactions', 'balance' ) as $page ) {
			add_settings_field(
				'mcr_field_template_layout_' . $page,
				__( ucfirst( str_replace( '_', ' ', $page ) ) . ' layout', 'madara-coin-rewards' ),
				array( $this, 'render_field_select' ),
				self::PAGE_SLUG,
				'mcr_section_templates',
				array(
					'key'     => 'template.layout_' . $page,
					'label'   => __( 'Template layout for the ' . ucfirst( str_replace( '_', ' ', $page ) ) . ' page.', 'madara-coin-rewards' ),
					'options' => $layouts,
				)
			);
		}

		add_settings_field(
			'mcr_field_template_columns',
			__( 'Grid columns', 'madara-coin-rewards' ),
			array( $this, 'render_field_number' ),
			self::PAGE_SLUG,
			'mcr_section_templates',
			array(
				'key'   => 'template.columns',
				'label' => __( 'Number of columns in grid-based layouts.', 'madara-coin-rewards' ),
				'min'   => 1,
				'max'   => 6,
				'step'  => 1,
			)
		);

		add_settings_field(
			'mcr_field_template_show_balance_summary',
			__( 'Show balance summary', 'madara-coin-rewards' ),
			array( $this, 'render_field_checkbox' ),
			self::PAGE_SLUG,
			'mcr_section_templates',
			array(
				'key'   => 'template.show_balance_summary',
				'label' => __( 'Display the user balance summary on frontend pages.', 'madara-coin-rewards' ),
			)
		);

		add_settings_field(
			'mcr_field_template_show_progress_bars',
			__( 'Show progress bars', 'madara-coin-rewards' ),
			array( $this, 'render_field_checkbox' ),
			self::PAGE_SLUG,
			'mcr_section_templates',
			array(
				'key'   => 'template.show_progress_bars',
				'label' => __( 'Display streak and progress bars on frontend pages.', 'madara-coin-rewards' ),
			)
		);

		add_settings_field(
			'mcr_field_template_show_streak_calendar',
			__( 'Show streak calendar', 'madara-coin-rewards' ),
			array( $this, 'render_field_checkbox' ),
			self::PAGE_SLUG,
			'mcr_section_templates',
			array(
				'key'   => 'template.show_streak_calendar',
				'label' => __( 'Display the check-in streak calendar on frontend pages.', 'madara-coin-rewards' ),
			)
		);

		add_settings_field(
			'mcr_field_template_show_sidebar',
			__( 'Show sidebar', 'madara-coin-rewards' ),
			array( $this, 'render_field_checkbox' ),
			self::PAGE_SLUG,
			'mcr_section_templates',
			array(
				'key'   => 'template.show_sidebar',
				'label' => __( 'Display the theme sidebar on coin-rewards pages.', 'madara-coin-rewards' ),
			)
		);

		add_settings_field(
			'mcr_field_template_show_breadcrumb',
			__( 'Show breadcrumb', 'madara-coin-rewards' ),
			array( $this, 'render_field_checkbox' ),
			self::PAGE_SLUG,
			'mcr_section_templates',
			array(
				'key'   => 'template.show_breadcrumb',
				'label' => __( 'Display the breadcrumb navigation on coin-rewards pages.', 'madara-coin-rewards' ),
			)
		);

		// --- Page Assignments fields ---
		foreach ( array( 'checkin', 'dashboard', 'ads', 'buy_coins', 'transactions' ) as $page ) {
			add_settings_field(
				'mcr_field_pages_' . $page,
				__( ucfirst( str_replace( '_', ' ', $page ) ) . ' page ID', 'madara-coin-rewards' ),
				array( $this, 'render_field_number' ),
				self::PAGE_SLUG,
				'mcr_section_pages',
				array(
					'key'   => 'pages.' . $page,
					'label' => __( 'ID of the WordPress page assigned to ' . ucfirst( str_replace( '_', ' ', $page ) ) . '. Enter 0 to use a shortcode.', 'madara-coin-rewards' ),
					'min'   => 0,
					'step'  => 1,
				)
			);
		}

		// --- Security fields ---
		add_settings_field(
			'mcr_field_security_log_ip',
			__( 'Log IP addresses', 'madara-coin-rewards' ),
			array( $this, 'render_field_checkbox' ),
			self::PAGE_SLUG,
			'mcr_section_security',
			array(
				'key'   => 'security.log_ip',
				'label' => __( 'Record IP addresses in the transaction ledger (audit only).', 'madara-coin-rewards' ),
			)
		);

		add_settings_field(
			'mcr_field_security_log_user_agent',
			__( 'Log user agents', 'madara-coin-rewards' ),
			array( $this, 'render_field_checkbox' ),
			self::PAGE_SLUG,
			'mcr_section_security',
			array(
				'key'   => 'security.log_user_agent',
				'label' => __( 'Record user agents in the transaction ledger (audit only).', 'madara-coin-rewards' ),
			)
		);

		add_settings_field(
			'mcr_field_security_rate_limit_checkin',
			__( 'Check-in rate limit', 'madara-coin-rewards' ),
			array( $this, 'render_field_number' ),
			self::PAGE_SLUG,
			'mcr_section_security',
			array(
				'key'   => 'security.rate_limit_checkin',
				'label' => __( 'Maximum check-in attempts per user per hour.', 'madara-coin-rewards' ),
				'min'   => 0,
				'step'  => 1,
			)
		);

		add_settings_field(
			'mcr_field_security_rate_limit_ads',
			__( 'Ad reward rate limit', 'madara-coin-rewards' ),
			array( $this, 'render_field_number' ),
			self::PAGE_SLUG,
			'mcr_section_security',
			array(
				'key'   => 'security.rate_limit_ads',
				'label' => __( 'Maximum ad attempts per user per hour.', 'madara-coin-rewards' ),
				'min'   => 0,
				'step'  => 1,
			)
		);

		// --- Notifications fields ---
		add_settings_field(
			'mcr_field_notify_toast_on_change',
			__( 'Toast notifications', 'madara-coin-rewards' ),
			array( $this, 'render_field_checkbox' ),
			self::PAGE_SLUG,
			'mcr_section_notifications',
			array(
				'key'   => 'notify.toast_on_change',
				'label' => __( 'Show a toast notification when the coin balance changes.', 'madara-coin-rewards' ),
			)
		);

		add_settings_field(
			'mcr_field_notify_show_login_prompt',
			__( 'Login prompt', 'madara-coin-rewards' ),
			array( $this, 'render_field_checkbox' ),
			self::PAGE_SLUG,
			'mcr_section_notifications',
			array(
				'key'   => 'notify.show_login_prompt',
				'label' => __( 'Show a login prompt to visitors on coin-rewards pages.', 'madara-coin-rewards' ),
			)
		);
	}

	/**
	 * Renders a section description.
	 *
	 * @param array $args Section arguments.
	 * @return void
	 */
	public function render_section_checkin() {
		echo '<p class="description">' . esc_html__( 'Configure the daily check-in reward and streak bonuses.', 'madara-coin-rewards' ) . '</p>';
	}

	public function render_section_ads() {
		echo '<p class="description">' . esc_html__( 'Configure rewarded ad watching and limits.', 'madara-coin-rewards' ) . '</p>';
	}

	public function render_section_purchase() {
		echo '<p class="description">' . esc_html__( 'Configure coin package purchases and payment gateways.', 'madara-coin-rewards' ) . '</p>';
	}

	public function render_section_templates() {
		echo '<p class="description">' . esc_html__( 'Choose frontend template layouts and appearance options.', 'madara-coin-rewards' ) . '</p>';
	}

	public function render_section_pages() {
		echo '<p class="description">' . esc_html__( 'Assign WordPress pages to each coin-rewards frontend page.', 'madara-coin-rewards' ) . '</p>';
	}

	public function render_section_security() {
		echo '<p class="description">' . esc_html__( 'Security, auditing, and rate-limiting options.', 'madara-coin-rewards' ) . '</p>';
	}

	public function render_section_notifications() {
		echo '<p class="description">' . esc_html__( 'User-facing notification preferences.', 'madara-coin-rewards' ) . '</p>';
	}

	// Field renderers -------------------------------------------------------

	/**
	 * Renders a single settings field based on its type.
	 *
	 * @param array $args Field arguments.
	 * @return void
	 */
	public function render_field( $args ) {
		$key   = isset( $args['key'] ) ? $args['key'] : '';
		$value = Settings::get( $key, isset( $args['default'] ) ? $args['default'] : '' );
		$type  = isset( $args['type'] ) ? $args['type'] : 'text';

		switch ( $type ) {
			case 'checkbox':
				$this->render_field_checkbox( $args );
				break;
			case 'select':
				$this->render_field_select( $args );
				break;
			case 'textarea':
				$this->render_field_textarea( $args );
				break;
			case 'json':
				$this->render_field_json( $args );
				break;
			case 'number':
				$this->render_field_number( $args );
				break;
			default:
				$this->render_field_text( $args );
				break;
		}

		if ( ! empty( $args['label'] ) ) {
			echo '<p class="description">' . esc_html( $args['label'] ) . '</p>';
		}
	}

	/**
	 * Renders a checkbox (on/off) field.
	 *
	 * @param array $args Field arguments.
	 * @return void
	 */
	public function render_field_checkbox( $args ) {
		$key   = isset( $args['key'] ) ? $args['key'] : '';
		$value = Settings::get( $key, 'off' );
		$checked = ( 'on' === $value || true === $value || 1 === $value || '1' === $value ) ? ' checked="checked"' : '';
		?>
		<input type="hidden" name="mcr_settings[<?php echo esc_attr( $key ); ?>]" value="off" />
		<input type="checkbox" name="mcr_settings[<?php echo esc_attr( $key ); ?>]" value="on" id="mcr_settings_<?php echo esc_attr( $key ); ?>"<?php echo $checked; ?> />
		<?php
	}

	/**
	 * Renders a text field.
	 *
	 * @param array $args Field arguments.
	 * @return void
	 */
	public function render_field_text( $args ) {
		$key   = isset( $args['key'] ) ? $args['key'] : '';
		$value = Settings::get( $key, '' );
		?>
		<input type="text" name="mcr_settings[<?php echo esc_attr( $key ); ?>]" id="mcr_settings_<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
		<?php
	}

	/**
	 * Renders a number field.
	 *
	 * @param array $args Field arguments.
	 * @return void
	 */
	public function render_field_number( $args ) {
		$key   = isset( $args['key'] ) ? $args['key'] : '';
		$value = Settings::get( $key, 0 );
		$min   = isset( $args['min'] ) ? ' min="' . esc_attr( $args['min'] ) . '"' : '';
		$max   = isset( $args['max'] ) ? ' max="' . esc_attr( $args['max'] ) . '"' : '';
		$step  = isset( $args['step'] ) ? ' step="' . esc_attr( $args['step'] ) . '"' : '';
		?>
		<input type="number" name="mcr_settings[<?php echo esc_attr( $key ); ?>]" id="mcr_settings_<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>"<?php echo $min . $max . $step; ?> class="small-text" />
		<?php
	}

	/**
	 * Renders a select field.
	 *
	 * @param array $args Field arguments.
	 * @return void
	 */
	public function render_field_select( $args ) {
		$key    = isset( $args['key'] ) ? $args['key'] : '';
		$value  = Settings::get( $key, '' );
		$options = isset( $args['options'] ) && is_array( $args['options'] ) ? $args['options'] : array();
		?>
		<select name="mcr_settings[<?php echo esc_attr( $key ); ?>]" id="mcr_settings_<?php echo esc_attr( $key ); ?>" class="regular-text">
			<?php foreach ( $options as $option_value => $option_label ) : ?>
				<option value="<?php echo esc_attr( $option_value ); ?>"<?php selected( $value, $option_value ); ?>><?php echo esc_html( $option_label ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Renders a textarea field.
	 *
	 * @param array $args Field arguments.
	 * @return void
	 */
	public function render_field_textarea( $args ) {
		$key   = isset( $args['key'] ) ? $args['key'] : '';
		$value = Settings::get( $key, '' );
		$rows  = isset( $args['rows'] ) ? absint( $args['rows'] ) : 5;

		/*
		 * A textarea always needs a scalar. Passing an array straight to
		 * esc_textarea() is a TypeError on PHP 8 - a hard fatal that took
		 * the whole Settings screen down - so structured values are rendered
		 * as JSON instead, matching the json field type.
		 */
		if ( is_array( $value ) || is_object( $value ) ) {
			$value = wp_json_encode( $value );
		}

		$value = is_scalar( $value ) ? (string) $value : '';
		?>
		<textarea name="mcr_settings[<?php echo esc_attr( $key ); ?>]" id="mcr_settings_<?php echo esc_attr( $key ); ?>" rows="<?php echo esc_attr( $rows ); ?>" class="large-text code"><?php echo esc_textarea( $value ); ?></textarea>
		<?php
	}

	/**
	 * Renders a JSON textarea field (used for bonuses/packages).
	 *
	 * @param array $args Field arguments.
	 * @return void
	 */
	public function render_field_json( $args ) {
		$key = isset( $args['key'] ) ? $args['key'] : '';
		$value = Settings::get( $key, array() );
		if ( is_array( $value ) ) {
			$value = wp_json_encode( $value );
		}
		?>
		<textarea name="mcr_settings[<?php echo esc_attr( $key ); ?>]" id="mcr_settings_<?php echo esc_attr( $key ); ?>" rows="6" class="large-text code"><?php echo esc_textarea( $value ); ?></textarea>
		<?php
	}

	/**
	 * Returns the default section definitions.
	 *
	 * @return array
	 */
	private function default_sections() {
		return array(
			'mcr_section_checkin'      => __( 'Daily Check-in', 'madara-coin-rewards' ),
			'mcr_section_ads'          => __( 'Watch Ads to Earn', 'madara-coin-rewards' ),
			'mcr_section_purchase'     => __( 'Coin Purchases', 'madara-coin-rewards' ),
			'mcr_section_templates'    => __( 'Templates & Layout', 'madara-coin-rewards' ),
			'mcr_section_pages'        => __( 'Page Assignments', 'madara-coin-rewards' ),
			'mcr_section_security'     => __( 'Security', 'madara-coin-rewards' ),
			'mcr_section_notifications'=> __( 'Notifications', 'madara-coin-rewards' ),
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
