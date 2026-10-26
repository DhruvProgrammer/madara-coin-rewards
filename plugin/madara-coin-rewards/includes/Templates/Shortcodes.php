<?php
/**
 * Shortcode handlers.
 *
 * Each shortcode is a thin controller: it gathers the data a page needs from
 * the plugin services, then hands that data to the template renderer. Nothing
 * is queried inside a template.
 *
 * @package MCR
 */

namespace MCR\Templates;

use MCR\Ledger;
use MCR\Payments\PackageCalculator;
use MCR\Rewards\AdSessions;
use MCR\Rewards\CheckIn;
use MCR\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and renders the plugin's shortcodes.
 */
class Shortcodes {

	/**
	 * Singleton instance.
	 *
	 * @var Shortcodes|null
	 */
	private static $instance = null;

	/**
	 * Whether the shortcodes were registered.
	 *
	 * @var bool
	 */
	private $registered = false;

	/**
	 * Returns the shared instance.
	 *
	 * @return Shortcodes
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Registers the shortcodes and the assets/blocks they depend on.
	 *
	 * @return void
	 */
	public function register() {
		if ( $this->registered ) {
			return;
		}

		$this->registered = true;

		Assets::instance()->register();
		Blocks::instance()->register();

		foreach ( Templates::registry() as $slug => $definition ) {
			if ( empty( $definition['shortcode'] ) ) {
				continue;
			}

			add_shortcode(
				$definition['shortcode'],
				function ( $atts = array() ) use ( $slug ) {
					return $this->render( $slug, is_array( $atts ) ? $atts : array() );
				}
			);
		}
	}

	/**
	 * Renders a page for a shortcode or a block.
	 *
	 * @param string $slug Page slug.
	 * @param array  $atts Shortcode attributes or block attributes.
	 * @return string
	 */
	public function render( $slug, array $atts = array() ) {
		$slug = Templates::normalize_slug( $slug );

		if ( ! Templates::definition( $slug ) ) {
			return '';
		}

		$atts = shortcode_atts(
			array(
				'layout'  => '',
				'columns' => '',
			),
			$atts,
			'mcr_' . str_replace( '-', '_', $slug )
		);

		$layout = Templates::layout( $slug, isset( $atts['layout'] ) ? $atts['layout'] : '' );

		Assets::instance()->need( $slug, $layout );

		$args         = $this->build_args( $slug, $atts );
		$args['layout'] = $layout;

		// A columns value of 0 (or an absent one) means "use the site
		// default", so it must not be coerced to 1.
		$requested = (string) $atts['columns'];

		if ( '' !== $requested && '0' !== $requested ) {
			$args['columns'] = max( 1, min( 4, absint( $requested ) ) );
		} else {
			$args['columns'] = max( 1, min( 4, absint( Settings::get( 'template.columns', 3 ) ) ) );
		}

		return Templates::render( $slug, $args );
	}

	/**
	 * Gathers the template arguments for a page.
	 *
	 * @param string $slug Page slug.
	 * @param array  $atts Sanitized shortcode attributes.
	 * @return array
	 */
	private function build_args( $slug, array $atts ) {
		$user_id = get_current_user_id();

		$args = array(
			'user_id' => $user_id,
			'atts'    => $atts,
			'urls'    => Templates::urls(),
			'types'   => $this->type_labels(),
		);

		switch ( $slug ) {
			case 'checkin':
				$args = array_merge( $args, $this->checkin_args( $user_id ) );
				break;

			case 'dashboard':
				$args = array_merge( $args, $this->dashboard_args( $user_id ) );
				break;

			case 'ads':
				$args = array_merge( $args, $this->ads_args( $user_id ) );
				break;

			case 'buy-coins':
				$args = array_merge( $args, $this->buy_args( $user_id ) );
				break;

			case 'transactions':
				$args = array_merge( $args, $this->transactions_args( $user_id ) );
				break;

			case 'balance':
				$args = array_merge( $args, $this->balance_args( $user_id ) );
				break;
		}

		/**
		 * Filters the data passed to a page template.
		 *
		 * @param array  $args    Template arguments.
		 * @param string $slug    Page slug.
		 * @param int    $user_id Current user id.
		 */
		return apply_filters( 'mcr_page_args', $args, $slug, $user_id );
	}

	/**
	 * Arguments for the daily check-in page.
	 *
	 * @param int $user_id User id.
	 * @return array
	 */
	private function checkin_args( $user_id ) {
		$status   = $this->checkin_status( $user_id );
		$bonuses  = Settings::get( 'checkin.bonuses', array() );
		$bonuses  = is_array( $bonuses ) ? $bonuses : array();
		ksort( $bonuses );
		$streak   = isset( $status['streak'] ) ? (int) $status['streak'] : 0;
		$ads_info = $this->ads_stats( $user_id, true );
		$units    = $this->ad_units();

		return array(
			'status'           => $status,
			'bonuses'          => $bonuses,
			'enabled'          => Settings::featureEnabled( 'checkin' ) && ! empty( $status['enabled'] ),
			'deadline'         => Templates::deadline( isset( $status['next_in'] ) ? (int) $status['next_in'] : 0 ),
			'streak'           => $streak,
			'next_bonus'       => $this->next_bonus( $streak, $bonuses ),
			'timezone'         => function_exists( 'wp_timezone_string' ) ? wp_timezone_string() : 'UTC',
			'claimable'        => empty( $status['claimed_today'] ) && ! empty( $status['base_claimable'] ),
			'stats'            => $this->ledger_stats( $user_id ),
			'profile'          => $this->profile( $user_id ),
			'today_earned'     => $this->today_earned( $user_id ),
			'ads'              => $ads_info,
			'ads_enabled'      => Settings::featureEnabled( 'ads' ),
			'ads_configured'   => $this->ads_configured(),
			'ad_units'         => $units,
			'units'            => $units,
			'coins_per_ad'     => isset( $ads_info['coins_per_ad'] ) ? (int) $ads_info['coins_per_ad'] : (int) Settings::get( 'ads.coins_per_ad', 5 ),
			'daily_limit'      => isset( $ads_info['limit'] ) ? (int) $ads_info['limit'] : (int) Settings::get( 'ads.daily_limit', 10 ),
			'ads_cooldown'     => (int) Settings::get( 'ads.cooldown_seconds', 300 ),
			'cooldown'         => (int) Settings::get( 'ads.cooldown_seconds', 300 ),
			'ads_reset'        => Templates::deadline( $this->seconds_until_next_day() ),
			'reset_deadline'   => Templates::deadline( $this->seconds_until_next_day() ),
			'logged_in'        => $user_id > 0,
			'login_url'        => function_exists( 'wp_login_url' ) ? wp_login_url( Templates::current_url() ) : '',
		);
	}

	/**
	 * Arguments for the dashboard page.
	 *
	 * @param int $user_id User id.
	 * @return array
	 */
	private function dashboard_args( $user_id ) {
		$checkin = $this->checkin_status( $user_id );
		$bonuses = Settings::get( 'checkin.bonuses', array() );
		$bonuses = is_array( $bonuses ) ? $bonuses : array();

		$streak = isset( $checkin['streak'] ) ? (int) $checkin['streak'] : 0;

		return array(
			'stats'          => $this->ledger_stats( $user_id ),
			'checkin'        => $checkin,
			'bonuses'        => $bonuses,
			'next_bonus'     => $this->next_bonus( $streak, $bonuses ),
			'recent'         => $this->recent_transactions( $user_id, 5 ),
			'ads'            => $this->ads_stats( $user_id ),
			'ads_configured' => $this->ads_configured(),
			'deadline'       => Templates::deadline( isset( $checkin['next_in'] ) ? (int) $checkin['next_in'] : 0 ),
			'checkin_enabled' => Settings::featureEnabled( 'checkin' ),
			'ads_enabled'    => Settings::featureEnabled( 'ads' ),
			'purchase_enabled' => Settings::featureEnabled( 'purchase' ),
			'claimable'      => empty( $checkin['claimed_today'] ) && ! empty( $checkin['base_claimable'] ),
			'timezone'       => function_exists( 'wp_timezone_string' ) ? wp_timezone_string() : 'UTC',
		);
	}

	/**
	 * Arguments for the ads page.
	 *
	 * @param int $user_id User id.
	 * @return array
	 */
	private function ads_args( $user_id ) {
		$stats = $this->ads_stats( $user_id );

		return array(
			'enabled'       => Settings::featureEnabled( 'ads' ),
			'configured'    => $this->ads_configured(),
			'ad_units'      => $this->ad_units(),
			'ads'           => $stats,
			'recent'        => $this->recent_ad_rewards( $user_id, 5 ),
			'coins_per_ad'  => isset( $stats['coins_per_ad'] ) ? (int) $stats['coins_per_ad'] : (int) Settings::get( 'ads.coins_per_ad', 5 ),
			'daily_limit'   => isset( $stats['limit'] ) ? (int) $stats['limit'] : (int) Settings::get( 'ads.daily_limit', 10 ),
			'cooldown'      => (int) Settings::get( 'ads.cooldown_seconds', 300 ),
			'reset_deadline' => Templates::deadline( $this->seconds_until_next_day() ),
			'urls'          => Templates::urls(),
		);
	}

	/**
	 * Arguments for the buy-coins page.
	 *
	 * @param int $user_id User id.
	 * @return array
	 */
	private function buy_args( $user_id ) {
		$orders = array();
		$notice = array();

		if ( class_exists( '\MCR\Payments\Orders' ) ) {
			$orders = \MCR\Payments\Orders::instance()->list_for_user( $user_id, 5 );
		}

		$payment_state = isset( $_GET['mcr_payment'] ) ? sanitize_key( wp_unslash( $_GET['mcr_payment'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'cancelled' === $payment_state ) {
			$notice = array(
				'type'  => 'info',
				'title' => __( 'Payment cancelled', 'madara-coin-rewards' ),
				'text'  => __( 'Payment cancelled. You have not been charged.', 'madara-coin-rewards' ),
			);
		} elseif ( 'failed' === $payment_state ) {
			$notice = array(
				'type'  => 'error',
				'title' => __( 'Payment failed', 'madara-coin-rewards' ),
				'text'  => __( 'You have not been charged. Try another payment method.', 'madara-coin-rewards' ),
			);
		} elseif ( 'success' === $payment_state ) {
			$notice = array(
				'type'  => 'success',
				'title' => __( 'Payment received', 'madara-coin-rewards' ),
				'text'  => __( 'We will add your coins as soon as the payment is confirmed.', 'madara-coin-rewards' ),
			);
		}

		$pending = false;

		foreach ( $orders as $order ) {
			if ( isset( $order['status'] ) && 'pending' === $order['status'] ) {
				$pending = true;
			}
		}

		return array(
			'packages'         => $this->packages(),
			'stats'            => $this->ledger_stats( $user_id ),
			'gateway_available' => $this->gateway_available(),
			'gateways'         => $this->gateway_details(),
			'support_url'      => $this->support_url(),
			'currency'         => (string) Settings::get( 'purchase.currency', 'USD' ),
			'orders'           => $orders,
			'pending_order'    => $pending,
			'notice'           => $notice,
			'enabled'          => Settings::featureEnabled( 'purchase' ),
			'urls'             => Templates::urls(),
		);
	}

	/**
	 * Returns the configured gateway's own title and description.
	 *
	 * The storefront must only describe payment methods the site really has
	 * enabled, so this reads the live gateway object rather than a static
	 * list of everything the plugin ships with. Returns an empty array when
	 * no gateway is configured or available.
	 *
	 * @return array List of [ 'id', 'title', 'description' ].
	 */
	private function gateway_details() {
		if ( ! Settings::featureEnabled( 'purchase' ) ) {
			return array();
		}

		$gateway_id = (string) Settings::get( 'purchase.gateway', '' );

		if ( '' === $gateway_id || ! class_exists( '\MCR\Gateways\Registry' ) ) {
			return array();
		}

		try {
			$gateway = \MCR\Gateways\Registry::get( $gateway_id );
		} catch ( \Throwable $e ) {
			return array();
		}

		if ( ! is_object( $gateway ) ) {
			return array();
		}

		if ( method_exists( $gateway, 'is_available' ) && ! $gateway->is_available() ) {
			return array();
		}

		return array(
			array(
				'id'          => $gateway_id,
				'title'       => method_exists( $gateway, 'get_title' ) ? (string) $gateway->get_title() : '',
				'description' => method_exists( $gateway, 'get_description' ) ? (string) $gateway->get_description() : '',
			),
		);
	}

	/**
	 * Returns the site's support URL, if one is configured.
	 *
	 * Respects the site's own support configuration when the theme provides
	 * one. An empty string means the storefront omits the help block rather
	 * than inventing a contact address.
	 *
	 * @return string
	 */
	private function support_url() {
		$configured = (string) Settings::get( 'purchase.support_url', '' );

		if ( '' !== $configured ) {
			return $configured;
		}

		// Madara exposes its own support page option.
		if ( class_exists( '\App\Madara' ) ) {
			$page = \App\Madara::getOption( 'support_page', '' );

			if ( is_numeric( $page ) && (int) $page > 0 ) {
				$link = get_permalink( (int) $page );

				if ( is_string( $link ) && '' !== $link ) {
					return $link;
				}
			}
		}

		return '';
	}

	/**
	 * Arguments for the transaction history page.
	 *
	 * @param int $user_id User id.
	 * @return array
	 */
	private function transactions_args( $user_id ) {
		$per_page = 20;
		$page     = isset( $_GET['mcr_page'] ) ? max( 1, absint( wp_unslash( $_GET['mcr_page'] ) ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$offset   = min( ( $page - 1 ) * $per_page, 10000 );

		$probe_rows = $this->ledger_rows( $user_id, $per_page + 1, $offset );
		$has_more   = count( $probe_rows ) > $per_page;
		$rows       = array_slice( $probe_rows, 0, $per_page );
		$total      = $has_more ? $offset + $per_page + 1 : $offset + count( $rows );
		$shown      = $offset + count( $rows );

		$next_page = $has_more ? $page + 1 : 0;

		$base_url = Templates::current_url();

		return array(
			'rows'      => $rows,
			'total'     => $total,
			'shown'     => min( $shown, $total ),
			'page'      => $page,
			'per_page'  => $per_page,
			'has_more'  => $next_page > 0,
			'next_page' => $next_page,
			'next_url'  => $next_page > 0 ? add_query_arg( 'mcr_page', $next_page, $base_url ) : '',
			'prev_url'  => $page > 1 ? add_query_arg( 'mcr_page', $page - 1, $base_url ) : '',
			'types'     => $this->type_labels(),
		);
	}

	/**
	 * Arguments for the balance panel.
	 *
	 * @param int $user_id User id.
	 * @return array
	 */
	private function balance_args( $user_id ) {
		$status = $this->checkin_status( $user_id );
		$bonus  = $this->next_bonus( isset( $status['streak'] ) ? (int) $status['streak'] : 0, Settings::get( 'checkin.bonuses', array() ) );

		return array(
			'stats'      => $this->ledger_stats( $user_id ),
			'checkin'    => $status,
			'next_bonus' => $bonus,
			'urls'       => Templates::urls(),
		);
	}

	/**
	 * Returns the check-in status, defensively.
	 *
	 * @param int $user_id User id.
	 * @return array
	 */
	private function checkin_status( $user_id ) {
		$defaults = array(
			'user_id'       => (int) $user_id,
			'claimed'       => false,
			'claimed_today' => false,
			'streak'        => 0,
			'base_amount'   => (int) Settings::get( 'checkin.base_amount', 10 ),
			'bonus_amount'  => 0,
			'total_amount'  => (int) Settings::get( 'checkin.base_amount', 10 ),
			'next_in'       => $this->seconds_until_next_day(),
			'today_key'     => class_exists( '\MCR\Container' ) ? \MCR\Container::instance()->clock()->today_key() : '',
			'calendar'      => array(),
			'enabled'       => Settings::featureEnabled( 'checkin' ),
			'base_claimable' => true,
		);

		if ( ! $user_id || ! class_exists( '\MCR\Rewards\CheckIn' ) ) {
			return $defaults;
		}

		$status = CheckIn::instance()->status( $user_id );

		return is_array( $status ) ? array_merge( $defaults, $status ) : $defaults;
	}

	/**
	 * Returns the ledger statistics, defensively.
	 *
	 * @param int $user_id User id.
	 * @return array
	 */
	private function ledger_stats( $user_id ) {
		$defaults = array(
			'balance'         => 0,
			'lifetime_earned' => 0,
			'lifetime_spent'  => 0,
			'purchased_total' => 0,
		);

		if ( ! $user_id || ! class_exists( '\MCR\Ledger' ) ) {
			return $defaults;
		}

		$stats = Ledger::instance()->get_stats( $user_id );

		return is_array( $stats ) ? array_merge( $defaults, $stats ) : $defaults;
	}

	/**
	 * Returns the profile summary for the wallet header.
	 *
	 * @param int $user_id User id.
	 * @return array
	 */
	private function profile( $user_id ) {
		if ( ! $user_id || ! function_exists( 'get_userdata' ) ) {
			return array(
				'name'     => '',
				'avatar'   => '',
				'initials' => '',
			);
		}

		$user = get_userdata( $user_id );
		if ( ! $user || ! is_object( $user ) ) {
			return array(
				'name'     => '',
				'avatar'   => '',
				'initials' => '',
			);
		}

		$name = isset( $user->display_name ) && '' !== trim( (string) $user->display_name )
			? trim( (string) $user->display_name )
			: ( isset( $user->user_login ) ? (string) $user->user_login : '' );

		$words    = preg_split( '/\s+/u', $name );
		$initials = '';
		if ( ! empty( $words ) ) {
			if ( count( $words ) >= 2 ) {
				$initials = mb_substr( $words[0], 0, 1, 'UTF-8' ) . mb_substr( $words[1], 0, 1, 'UTF-8' );
			} else {
				$initials = mb_substr( $words[0], 0, 1, 'UTF-8' );
			}
		}

		$avatar = function_exists( 'get_avatar_url' )
			? (string) get_avatar_url( $user_id, 96 )
			: '';

		return array(
			'name'     => $name,
			'avatar'   => $avatar,
			'initials' => $initials,
		);
	}

	/**
	 * Calculates the coins earned today by the user.
	 *
	 * @param int $user_id User id.
	 * @return int
	 */
	private function today_earned( $user_id ) {
		if ( ! $user_id ) {
			return 0;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'mcr_transactions';
		$clock = \MCR\Container::instance()->clock();

		$day_start = $clock->now()->setTime( 0, 0, 0 )->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
		$day_end   = $clock->now()->setTime( 0, 0, 0 )->modify( '+1 day' )->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );

		$earned = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(amount), 0) FROM {$table}
				 WHERE user_id = %d
				   AND created_at >= %s
				   AND created_at < %s
				   AND ( amount >= 0 OR type IN ( 'reversal', 'refund' ) )",
				$user_id,
				$day_start,
				$day_end
			)
		);

		return max( 0, $earned );
	}

	/**
	 * Returns the ad statistics, defensively.
	 *
	 * @param int $user_id User id.
	 * @return array
	 */
	private function ads_stats( $user_id, $today_only = false ) {
		$limit = (int) Settings::get( 'ads.daily_limit', 10 );

		$defaults = array(
			'today'        => 0,
			'total'        => 0,
			'coins'        => 0,
			'limit'        => $limit,
			'remaining'    => $limit,
			'coins_per_ad' => (int) Settings::get( 'ads.coins_per_ad', 5 ),
		);

		if ( ! $user_id || ! class_exists( '\MCR\Rewards\AdSessions' ) ) {
			return $defaults;
		}

		$stats = AdSessions::instance()->stats( $user_id, $today_only );

		return is_array( $stats ) ? array_merge( $defaults, $stats ) : $defaults;
	}

	/**
	 * Whether a rewarded-ad provider is configured.
	 *
	 * Fails closed: without a provider registry nothing can verify a
	 * completion, so ads must be reported as unavailable.
	 *
	 * @return bool
	 */
	private function ads_configured() {
		if ( ! Settings::featureEnabled( 'ads' ) || ! class_exists( '\MCR\Rewards\AdSessions' ) ) {
			return false;
		}

		if ( ! class_exists( '\MCR\AdProviders\Registry' ) ) {
			return false;
		}

		try {
			return (bool) AdSessions::instance()->isConfigured();
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * Returns the configured ad units.
	 *
	 * @return array
	 */
	private function ad_units() {
		if ( ! class_exists( '\MCR\Rewards\AdSessions' ) || ! class_exists( '\MCR\AdProviders\Registry' ) ) {
			return array();
		}

		try {
			$units = AdSessions::instance()->adUnits();
		} catch ( \Throwable $e ) {
			return array();
		}

		if ( ! is_array( $units ) ) {
			return array();
		}

		$normalized = array();

		foreach ( $units as $key => $unit ) {
			if ( is_string( $unit ) ) {
				$unit = array( 'id' => $unit, 'label' => $unit );
			}

			if ( ! is_array( $unit ) ) {
				continue;
			}

			$id = isset( $unit['id'] ) ? sanitize_key( (string) $unit['id'] ) : sanitize_key( (string) $key );

			if ( '' === $id ) {
				continue;
			}

			$normalized[] = array(
				'id'      => $id,
				'label'   => isset( $unit['label'] ) && '' !== (string) $unit['label']
					? (string) $unit['label']
					: ( isset( $unit['name'] ) ? (string) $unit['name'] : $id ),
				'placement' => isset( $unit['placement'] ) ? sanitize_key( (string) $unit['placement'] ) : '',
			);
		}

		return $normalized;
	}

	/**
	 * Whether a payment gateway is available.
	 *
	 * @return bool
	 */
	private function gateway_available() {
		if ( ! Settings::featureEnabled( 'purchase' ) ) {
			return false;
		}

		$gateway_id = (string) Settings::get( 'purchase.gateway', '' );

		if ( '' === $gateway_id || ! class_exists( '\MCR\Gateways\Registry' ) ) {
			return false;
		}

		try {
			if ( ! method_exists( '\MCR\Gateways\Registry', 'get' ) ) {
				return false;
			}

			$gateway = \MCR\Gateways\Registry::get( $gateway_id );
		} catch ( \Throwable $e ) {
			return false;
		}

		if ( ! is_object( $gateway ) ) {
			return false;
		}

		if ( method_exists( $gateway, 'is_available' ) ) {
			return (bool) $gateway->is_available();
		}

		return true;
	}

	/**
	 * Returns the active coin packages.
	 *
	 * @return array
	 */
	private function packages() {
		/*
		 * Packages::get_active() already returns fully normalized packages:
		 * coins_total, price_minor, max_bonus, active, limit and purchased are
		 * all present. Re-normalizing here used to rebuild the array from a
		 * fixed key list that omitted price_minor/limit/purchased, so
		 * Packages::is_purchasable() then always saw a zero price and the
		 * storefront could never offer a package for sale.
		 *
		 * We therefore pass the normalized rows straight through and only
		 * apply the display defaults the templates still expect.
		 */
		if ( ! class_exists( '\MCR\Payments\Packages' ) || ! method_exists( '\MCR\Payments\Packages', 'get_active' ) ) {
			return array();
		}

		$active = \MCR\Payments\Packages::get_active();

		if ( ! is_array( $active ) ) {
			return array();
		}

		$default_currency = (string) Settings::get( 'purchase.currency', 'USD' );
		$normalized       = array();

		foreach ( $active as $package ) {
			if ( ! is_array( $package ) ) {
				continue;
			}

			$id = isset( $package['id'] ) ? sanitize_key( (string) $package['id'] ) : '';

			if ( '' === $id ) {
				continue;
			}

			// Display defaults only; every derived value is preserved.
			$package['id']       = $id;
			$package['title']    = isset( $package['title'] ) && '' !== (string) $package['title']
				? (string) $package['title']
				: __( 'Coin package', 'madara-coin-rewards' );
			$package['currency'] = isset( $package['currency'] ) && '' !== (string) $package['currency']
				? strtoupper( (string) $package['currency'] )
				: $default_currency;

			$normalized[] = $package;
		}

		return $normalized;
	}

	/**
	 * Returns recent ledger rows formatted for the templates.
	 *
	 * @param int $user_id User id.
	 * @param int $limit   Row count.
	 * @return array
	 */
	private function recent_transactions( $user_id, $limit = 5 ) {
		return $this->ledger_rows( $user_id, $limit, 0 );
	}

	/**
	 * Returns formatted ledger rows.
	 *
	 * @param int $user_id User id.
	 * @param int $limit   Row count.
	 * @param int $offset  Offset.
	 * @return array
	 */
	private function ledger_rows( $user_id, $limit, $offset = 0 ) {
		if ( ! $user_id || ! class_exists( '\MCR\Ledger' ) ) {
			return array();
		}

		$rows = Ledger::instance()->list_for_user( $user_id, $limit, $offset );

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$out = array();

		foreach ( $rows as $row ) {
			$out[] = $this->format_transaction( $row );
		}

		return $out;
	}

	/**
	 * Returns recent verified ad rewards.
	 *
	 * @param int $user_id User id.
	 * @param int $limit   Row count.
	 * @return array
	 */
	private function recent_ad_rewards( $user_id, $limit = 5 ) {
		if ( ! $user_id || ! class_exists( '\MCR\Ledger' ) ) {
			return array();
		}

		$rows = Ledger::instance()->list_for_user( $user_id, $limit, 0, 'ad_reward' );

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$out = array();

		foreach ( $rows as $row ) {
			$out[] = $this->format_transaction( $row );
		}

		return $out;
	}

	/**
	 * Counts a user's ledger rows.
	 *
	 * @param int $user_id User id.
	 * @return int
	 */
	private function ledger_total( $user_id ) {
		if ( ! $user_id || ! class_exists( '\MCR\Ledger' ) ) {
			return 0;
		}

		return (int) Ledger::instance()->count_for_user( $user_id );
	}

	/**
	 * Normalizes a ledger row for templates and the REST append path.
	 *
	 * @param object $row Ledger row.
	 * @return array
	 */
	private function format_transaction( $row ) {
		$created = isset( $row->created_at ) ? (string) $row->created_at : '';
		$display = $created;
		$iso     = $created;

		if ( '' !== $created ) {
			$display = get_date_from_gmt( $created, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
			$iso     = get_date_from_gmt( $created, 'c' );
		}

		$type = isset( $row->type ) ? sanitize_key( (string) $row->type ) : '';

		return array(
			'id'            => isset( $row->id ) ? (int) $row->id : 0,
			'uid'           => isset( $row->txn_uid ) ? (string) $row->txn_uid : '',
			'type'          => $type,
			'type_label'    => Templates::type_label( $type ),
			'amount'        => isset( $row->amount ) ? (int) $row->amount : 0,
			'balance_after' => isset( $row->balance_after ) ? (int) $row->balance_after : 0,
			'reason'        => isset( $row->reason ) && '' !== (string) $row->reason
				? (string) $row->reason
				: Templates::type_label( $type ),
			'created_at'    => $created,
			'created_iso'   => $iso,
			'created_display' => $display,
		);
	}

	/**
	 * Returns the transaction type label map.
	 *
	 * @return array
	 */
	private function type_labels() {
		$types = array(
			'checkin',
			'checkin_bonus',
			'ad_reward',
			'purchase',
			'purchase_bonus',
			'spend',
			'admin_adjust',
			'refund',
			'referral',
			'reversal',
		);

		if ( class_exists( '\MCR\Ledger' ) && method_exists( '\MCR\Ledger', 'transaction_types' ) ) {
			$registered = Ledger::transaction_types();

			if ( is_array( $registered ) ) {
				$types = array_values( array_unique( array_merge( $types, $registered ) ) );
			}
		}

		$labels = array();

		foreach ( $types as $type ) {
			$labels[ $type ] = Templates::type_label( $type );
		}

		return $labels;
	}

	/**
	 * Finds the next streak bonus above the current streak.
	 *
	 * @param int   $streak  Current streak.
	 * @param array $bonuses Threshold => amount map.
	 * @return array ['days' => int, 'amount' => int, 'threshold' => int]
	 */
	private function next_bonus( $streak, $bonuses ) {
		$streak  = (int) $streak;
		$bonuses = is_array( $bonuses ) ? $bonuses : array();

		ksort( $bonuses );

		foreach ( $bonuses as $threshold => $amount ) {
			$threshold = (int) $threshold;
			$amount    = (int) $amount;

			if ( $threshold > $streak && $amount > 0 ) {
				return array(
					'days'      => $threshold - $streak,
					'threshold' => $threshold,
					'amount'    => $amount,
				);
			}
		}

		return array(
			'days'      => 0,
			'threshold' => 0,
			'amount'    => 0,
		);
	}

	/**
	 * Seconds until the next day bucket starts.
	 *
	 * @return int
	 */
	private function seconds_until_next_day() {
		if ( class_exists( '\MCR\Container' ) ) {
			return (int) \MCR\Container::instance()->clock()->seconds_until_next_day();
		}

		return 0;
	}
}
