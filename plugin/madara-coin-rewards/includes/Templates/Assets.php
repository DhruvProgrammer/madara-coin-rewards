<?php
/**
 * Frontend asset loading.
 *
 * The design system and the frontend bundle are enqueued only on pages that
 * actually render a plugin shortcode or block, plus the pages assigned in the
 * settings. A shortcode that renders somewhere the detection cannot see (a
 * widget, a page builder) calls `need()` and wins anyway.
 *
 * @package MCR
 */

namespace MCR\Templates;

use MCR\Ledger;
use MCR\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Enqueues the plugin's stylesheet and script, and localizes their data.
 */
class Assets {

	/**
	 * Stylesheet handle.
	 */
	const STYLE = 'mcr-design-system';

	/**
	 * Storefront stylesheet handle.
	 */
	const BUY_STYLE = 'mcr-buy-coins';

	/**
	 * Frontend script handle.
	 */
	const SCRIPT = 'mcr-frontend';

	/**
	 * Singleton instance.
	 *
	 * @var Assets|null
	 */
	private static $instance = null;

	/**
	 * Whether the hooks were registered.
	 *
	 * @var bool
	 */
	private $registered = false;

	/**
	 * Page slug being rendered.
	 *
	 * @var string
	 */
	private $page = '';

	/**
	 * Layout being rendered.
	 *
	 * @var string
	 */
	private $layout = '';

	/**
	 * Whether mcrData was localized.
	 *
	 * @var bool
	 */
	private $localized = false;

	/**
	 * Returns the shared instance.
	 *
	 * @return Assets
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Registers the asset hooks.
	 *
	 * @return void
	 */
	public function register() {
		if ( $this->registered ) {
			return;
		}

		$this->registered = true;

		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue' ), 20 );

		/*
		 * The admin screens have their own stylesheet. It was never
		 * enqueued, so the plugin's admin pages were unstyled.
		 */
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ), 20 );
	}

	/**
	 * Enqueues the assets when the current request needs them.
	 *
	 * @return void
	 */
	public function maybe_enqueue() {
		if ( ! $this->should_load() ) {
			return;
		}

		$page = $this->detect_page();

		if ( '' !== $page ) {
			$this->page   = $page;
			$this->layout = Templates::layout( $page );
		}

		$this->enqueue();
	}

	/**
	 * Marks a page as needing the assets and enqueues them immediately.
	 *
	 * Safe to call from a shortcode or a block render callback, i.e. after
	 * `wp_enqueue_scripts` has already fired.
	 *
	 * @param string $slug   Page slug.
	 * @param string $layout Layout key.
	 * @return void
	 */
	public function need( $slug, $layout = '' ) {
		$slug = Templates::normalize_slug( $slug );

		if ( '' === $this->page && '' !== $slug ) {
			$this->page = $slug;
		}

		if ( '' === $this->layout ) {
			$this->layout = '' !== $layout ? sanitize_key( $layout ) : Templates::layout( $slug );
		}

		$this->enqueue_scripts();
	}

	/**
	 * Enqueues the stylesheet the admin screens need.
	 *
	 * @return void
	 */
	public function enqueue_admin_assets() {
		if ( ! defined( 'MCR_PLUGIN_URL' ) || ! defined( 'MCR_VERSION' ) ) {
			return;
		}

		if ( ! current_user_can( 'mcr_manage_settings' ) && ! current_user_can( 'mcr_manage_coins' ) ) {
			return;
		}

		if ( ! wp_style_is( 'mcr-admin', 'enqueued' ) && ! wp_style_is( 'mcr-admin', 'done' ) ) {
			wp_enqueue_style(
				'mcr-admin',
				MCR_PLUGIN_URL . 'assets/css/mcr-admin.css',
				array(),
				MCR_VERSION
			);
		}
	}

	/**
	 * Enqueues the stylesheet and the script.
	 *
	 * @return void
	 */
	private function enqueue() {
		if ( ! defined( 'MCR_PLUGIN_URL' ) || ! defined( 'MCR_VERSION' ) ) {
			return;
		}

		if ( ! wp_style_is( self::STYLE, 'enqueued' ) && ! wp_style_is( self::STYLE, 'done' ) ) {
			wp_enqueue_style(
				self::STYLE,
				MCR_PLUGIN_URL . 'assets/css/mcr-design-system.css',
				array(),
				MCR_VERSION
			);
		}

		/*
		 * The premium storefront stylesheet only loads on the Buy Coins
		 * page, so none of these rules can ever reach another page.
		 */
		if ( 'buy-coins' === $this->page
			&& ! wp_style_is( self::BUY_STYLE, 'enqueued' )
			&& ! wp_style_is( self::BUY_STYLE, 'done' ) ) {
			wp_enqueue_style(
				self::BUY_STYLE,
				MCR_PLUGIN_URL . 'assets/css/mcr-buy-coins.css',
				array( self::STYLE ),
				MCR_VERSION
			);
		}

		if ( ! wp_style_is( 'mcr-rewards', 'enqueued' ) && ! wp_style_is( 'mcr-rewards', 'done' ) ) {
			wp_enqueue_style(
				'mcr-rewards',
				MCR_PLUGIN_URL . 'assets/css/mcr-rewards.css',
				array( self::STYLE ),
				MCR_VERSION
			);
		}

		$this->enqueue_scripts();
	}

	/**
	 * Enqueues the frontend script and localizes data.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		if ( ! defined( 'MCR_PLUGIN_URL' ) || ! defined( 'MCR_VERSION' ) ) {
			return;
		}

		if ( ! wp_script_is( self::SCRIPT, 'enqueued' ) && ! wp_script_is( self::SCRIPT, 'done' ) ) {
			wp_enqueue_script(
				self::SCRIPT,
				MCR_PLUGIN_URL . 'assets/js/mcr-frontend.js',
				array(),
				MCR_VERSION,
				true
			);
		}

		$this->localize();
	}

	/**
	 * Passes data to the frontend bundle.
	 *
	 * @return void
	 */
	private function localize() {
		if ( $this->localized || ! wp_script_is( self::SCRIPT, 'registered' ) ) {
			return;
		}

		$this->localized = true;

		$urls = Templates::urls();

		unset( $urls['settings'] );

		$features = array(
			'checkin'   => Settings::featureEnabled( 'checkin' ),
			'ads'       => Settings::featureEnabled( 'ads' ),
			'purchases' => Settings::featureEnabled( 'purchase' ),
			'dashboard' => Settings::featureEnabled( 'dashboard' ),
		);

		$data = array(
			'restUrl'    => esc_url_raw( rest_url( 'mcr/v1/' ) ),
			'nonce'      => wp_create_nonce( 'wp_rest' ),
			'serverTime' => Templates::server_time(),
			'tz'         => function_exists( 'wp_timezone_string' ) ? wp_timezone_string() : 'UTC',
			'locale'     => str_replace( '_', '-', get_locale() ),
			'userId'     => get_current_user_id(),
			'page'       => $this->page,
			'layout'     => $this->layout,
			'features'   => $features,
			'urls'       => $urls,
			'routes'     => array(
				'balance'      => 'balance',
				'checkin'      => 'checkin',
				'adStart'      => 'ad/start',
				'adVerify'     => 'ad/verify',
				'packages'     => 'packages',
				'orders'       => 'orders',
				'transactions' => 'transactions',
				'dashboard'    => 'dashboard',
			),
			'perPage'    => 20,
			'types'      => $this->type_labels(),
			'format'     => array(
				'currency' => (string) Settings::get( 'purchase.currency', 'USD' ),
			),
			'polling'    => array(
				'adAttempts' => 6,
				'adDelays'   => array( 1000, 2000, 3000, 5000, 5000, 5000 ),
			),
			'i18n'       => $this->strings(),
		);

		// Both keys are provided: the spec uses `i18n`, the shortcode contract
		// calls it `strings`.
		$data['strings'] = $data['i18n'];

		/**
		 * Filters the data passed to the frontend bundle.
		 *
		 * @param array $data Localized data.
		 */
		$data = apply_filters( 'mcr_localized_data', $data );

		wp_localize_script( self::SCRIPT, 'mcrData', $data );
	}

	/**
	 * Decides whether the current request renders a plugin page.
	 *
	 * @return bool
	 */
	public function should_load() {
		if ( is_admin() || wp_doing_ajax() || is_feed() ) {
			return false;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}

		/*
		 * The balance widget can sit in the sidebar of ANY page, so it is
		 * checked first. It used to be checked only on non-singular
		 * requests, which left the widget unstyled on every chapter and
		 * every post.
		 */
		if ( $this->has_balance_widget() ) {
			return true;
		}

		if ( ! is_singular() ) {
			return false;
		}

		$post = get_post();

		if ( ! $post instanceof \WP_Post ) {
			return false;
		}

		$pages = (array) Settings::get( 'pages', array() );

		foreach ( $pages as $page_id ) {
			if ( absint( $page_id ) === (int) $post->ID ) {
				return true;
			}
		}

		foreach ( Templates::registry() as $slug => $definition ) {
			if ( ! empty( $definition['shortcode'] ) && has_shortcode( $post->post_content, $definition['shortcode'] ) ) {
				return true;
			}

			$block = ! empty( $definition['block'] ) ? $definition['block'] : $slug;

			if ( has_block( 'madara-coin-rewards/' . $block, $post ) ) {
				return true;
			}
		}

		return (bool) get_post_meta( $post->ID, '_mcr_appearance', true );
	}

	/**
	 * Detects which plugin page the current singular post renders.
	 *
	 * @return string
	 */
	private function detect_page() {
		$post = get_post();

		if ( ! $post instanceof \WP_Post ) {
			return '';
		}

		foreach ( Templates::registry() as $slug => $definition ) {
			$block = ! empty( $definition['block'] ) ? $definition['block'] : $slug;

			if ( has_block( 'madara-coin-rewards/' . $block, $post ) ) {
				return $slug;
			}
		}

		foreach ( Templates::registry() as $slug => $definition ) {
			if ( ! empty( $definition['shortcode'] ) && has_shortcode( $post->post_content, $definition['shortcode'] ) ) {
				return $slug;
			}
		}

		foreach ( (array) Settings::get( 'pages', array() ) as $key => $page_id ) {
			if ( absint( $page_id ) === (int) $post->ID ) {
				$slug = Templates::normalize_slug( $key );

				return Templates::definition( $slug ) ? $slug : '';
			}
		}

		return '';
	}

	/**
	 * Whether the balance widget is active.
	 *
	 * @return bool
	 */
	private function has_balance_widget() {
		if ( ! function_exists( 'is_active_widget' ) ) {
			return false;
		}

		return (bool) is_active_widget( false, false, 'mcr_balance', true );
	}

	/**
	 * Returns the transaction type label map for the client.
	 *
	 * @return array
	 */
	private function type_labels() {
		$types = array();

		if ( class_exists( '\MCR\Ledger' ) && method_exists( '\MCR\Ledger', 'transaction_types' ) ) {
			$types = (array) Ledger::transaction_types();
		}

		$types[] = 'reversal';

		$labels = array();

		foreach ( array_unique( $types ) as $type ) {
			$labels[ sanitize_key( $type ) ] = Templates::type_label( $type );
		}

		return $labels;
	}

	/**
	 * Returns the translated strings used by the frontend bundle.
	 *
	 * @return array
	 */
	private function strings() {
		return array(
			'claiming'        => __( 'Claiming...', 'madara-coin-rewards' ),
			'claimFailed'     => __( 'We could not save your check-in. Please try again.', 'madara-coin-rewards' ),
			'alreadyClaimed'  => __( 'You have already checked in today.', 'madara-coin-rewards' ),
			'sessionExpired'  => __( 'Your session expired. Reload the page and try again.', 'madara-coin-rewards' ),
			'tooMany'         => __( 'Too many attempts. Please wait a moment and try again.', 'madara-coin-rewards' ),
			'clockSkew'       => __( 'Your device clock is out of sync. Reload the page to see the correct time.', 'madara-coin-rewards' ),
			'genericError'    => __( 'Something went wrong. Please try again.', 'madara-coin-rewards' ),
			'retry'           => __( 'Try again', 'madara-coin-rewards' ),
			'reload'          => __( 'Reload page', 'madara-coin-rewards' ),
			'checkedIn'       => __( 'Checked in today', 'madara-coin-rewards' ),
			'watching'        => __( 'Watching ad...', 'madara-coin-rewards' ),
			'starting'        => __( 'Starting...', 'madara-coin-rewards' ),
			'verifying'       => __( 'Checking with the provider...', 'madara-coin-rewards' ),
			'adNotConfirmed'  => __( 'We could not confirm that ad finished, so no coins were added.', 'madara-coin-rewards' ),
			'adNotConfigured' => __( 'Rewarded ads are not configured on this site yet.', 'madara-coin-rewards' ),
			'adNotLoaded'     => __( 'The ad could not be loaded. No coins were added.', 'madara-coin-rewards' ),
			'adAlreadyCredited' => __( 'This ad reward was already added to your balance.', 'madara-coin-rewards' ),
			'adFailed'        => __( 'The ad provider did not respond. No coins were added.', 'madara-coin-rewards' ),
			'dailyLimitReached' => __( 'Daily limit reached', 'madara-coin-rewards' ),
			'nextAdInLabel'   => __( 'Next ad in', 'madara-coin-rewards' ),
			'nextCheckin'     => __( 'Next check-in in', 'madara-coin-rewards' ),
			'readyToClaim'    => __( 'Ready to claim.', 'madara-coin-rewards' ),
			'adReady'         => __( 'You can watch another ad now.', 'madara-coin-rewards' ),
			'announce60'      => __( '60 seconds left.', 'madara-coin-rewards' ),
			'announce10'      => __( '10 seconds left.', 'madara-coin-rewards' ),
			'added'           => __( '+%s coins added.', 'madara-coin-rewards' ),
			'balanceUpdated'  => __( 'Balance updated. %s coins.', 'madara-coin-rewards' ),
			'loadingMore'     => __( 'Loading...', 'madara-coin-rewards' ),
			'allShown'        => __( 'All %s transactions shown.', 'madara-coin-rewards' ),
			'showing'         => __( 'Showing %1$s of %2$s', 'madara-coin-rewards' ),
			'buyFailed'       => __( 'We could not start the payment.', 'madara-coin-rewards' ),
			'buyStarting'     => __( 'Starting checkout...', 'madara-coin-rewards' ),
			'orderCreated'    => __( 'Complete the payment with your selected method. Coins are added after confirmation.', 'madara-coin-rewards' ),
			'paymentCancelled' => __( 'Payment cancelled. You have not been charged.', 'madara-coin-rewards' ),
			'processingPayment' => __( 'Awaiting payment confirmation', 'madara-coin-rewards' ),
			'transactionsFailed' => __( 'We could not load your transactions. Please try again.', 'madara-coin-rewards' ),
			'typeLabel'       => __( 'Type', 'madara-coin-rewards' ),
			'descriptionLabel' => __( 'Description', 'madara-coin-rewards' ),
			'amountLabel'     => __( 'Amount', 'madara-coin-rewards' ),
			'balanceAfter'    => __( 'Balance after', 'madara-coin-rewards' ),
			'coins'           => __( 'coins', 'madara-coin-rewards' ),
			'coinsSpent'      => __( 'coins spent', 'madara-coin-rewards' ),
			'dismiss'         => __( 'Dismiss', 'madara-coin-rewards' ),
			'close'           => __( 'Close', 'madara-coin-rewards' ),
			'notifications'   => __( 'Notifications', 'madara-coin-rewards' ),
		);
	}
}
