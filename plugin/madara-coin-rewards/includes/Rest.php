<?php
/**
 * REST API controller.
 *
 * @package MCR
 */

namespace MCR;

use MCR\Payments\Orders;
use MCR\Payments\Packages;
use MCR\Rewards\AdSessions;
use MCR\Rewards\CheckIn;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and serves the plugin's REST routes.
 */
class Rest {

	/**
	 * REST namespace.
	 */
	const NAMESPACE_V1 = 'mcr/v1';

	/**
	 * Singleton instance.
	 *
	 * @var Rest|null
	 */
	private static $instance = null;

	/**
	 * Returns the shared instance.
	 *
	 * @return Rest
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Registers every route.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/balance',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_balance' ),
				'permission_callback' => array( $this, 'permission_logged_in' ),
				'args'                => array(),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/checkin',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'post_checkin' ),
				'permission_callback' => array( $this, 'permission_logged_in' ),
				'args'                => array(),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/ad/start',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'post_ad_start' ),
				'permission_callback' => array( $this, 'permission_logged_in' ),
				'args'                => array(
					'ad_id' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/ad/verify',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'post_ad_verify' ),
				'permission_callback' => array( $this, 'permission_logged_in' ),
				'args'                => array(
					'session_token'    => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'completion_token' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/packages',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_packages' ),
				'permission_callback' => '__return_true',
				'args'                => array(),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/orders',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'post_order' ),
				'permission_callback' => array( $this, 'permission_logged_in' ),
				'args'                => array(
					'package_id' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/orders/(?P<order_id>[a-zA-Z0-9_\-]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_order' ),
				'permission_callback' => array( $this, 'permission_logged_in' ),
				'args'                => array(
					'order_id' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/transactions',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_transactions' ),
				'permission_callback' => array( $this, 'permission_logged_in' ),
				'args'                => array(
					'page'     => array(
						'required'          => false,
						'type'              => 'integer',
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page' => array(
						'required'          => false,
						'type'              => 'integer',
						'default'           => 20,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/dashboard',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_dashboard' ),
				'permission_callback' => array( $this, 'permission_logged_in' ),
				'args'                => array(),
			)
		);

		/**
		 * Fires after the plugin's REST routes are registered.
		 *
		 * @param Rest $controller The controller instance.
		 */
		do_action( 'mcr_register_rest_routes', $this );
	}

	/**
	 * Permission callback: the user must be logged in.
	 *
	 * @return true|\WP_Error
	 */
	public function permission_logged_in() {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error(
				'mcr_not_logged_in',
				__( 'You must be logged in to use this feature.', 'madara-coin-rewards' ),
				array( 'status' => 401 )
			);
		}

		return true;
	}

	/**
	 * Returns the current user's balance and stats.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_balance() {
		$user_id = get_current_user_id();
		$stats   = Ledger::instance()->get_stats( $user_id );

		return rest_ensure_response(
			array(
				'balance'         => (int) ( $stats['balance'] ?? 0 ),
				'stats'           => $stats,
				'checkin'         => CheckIn::instance()->status( $user_id ),
				'ads'             => AdSessions::instance()->stats( $user_id ),
				'ads_configured'  => AdSessions::instance()->isConfigured(),
			)
		);
	}

	/**
	 * Claims the daily reward.
	 *
	 * @return \WP_REST_Response
	 */
	public function post_checkin() {
		$user_id = get_current_user_id();

		$result = CheckIn::instance()->claim( $user_id );

		if ( is_wp_error( $result ) ) {
			$status = isset( $result->get_error_data()['status'] ) ? (int) $result->get_error_data()['status'] : 400;

			return new \WP_REST_Response(
				array(
					'code'    => $result->get_error_code(),
					'message' => $result->get_error_message(),
					'status'  => $status,
				),
				$status
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => $result['message'],
				'data'    => $result,
			)
		);
	}

	/**
	 * Starts an ad session.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function post_ad_start( \WP_REST_Request $request ) {
		$user_id = get_current_user_id();

		$result = AdSessions::instance()->start(
			$user_id,
			array( 'ad_id' => (string) $request->get_param( 'ad_id' ) )
		);

		if ( is_wp_error( $result ) ) {
			return $this->error_response( $result );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $result,
			)
		);
	}

	/**
	 * Verifies an ad completion.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function post_ad_verify( \WP_REST_Request $request ) {
		$user_id = get_current_user_id();

		$result = AdSessions::instance()->verify(
			$user_id,
			array(
				'session_token'    => (string) $request->get_param( 'session_token' ),
				'completion_token' => (string) $request->get_param( 'completion_token' ),
			)
		);

		if ( is_wp_error( $result ) ) {
			return $this->error_response( $result );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $result,
			)
		);
	}

	/**
	 * Returns the active coin packages.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_packages() {
		$packages = array();

		foreach ( Packages::get_active() as $package ) {
			$packages[] = array(
				'id'             => $package['id'],
				'title'          => $package['title'],
				'coins'          => (int) $package['coins'],
				'bonus_percent'  => (int) $package['bonus_percent'],
				'coins_total'    => (int) $package['coins_total'],
				'price'          => (float) $package['price'],
				'currency'       => $package['currency'],
				'badge'          => $package['badge'],
				'active'         => $package['active'],
			);
		}

		return rest_ensure_response(
			array(
				'packages' => $packages,
				'currency' => Settings::get( 'purchase.currency', 'USD' ),
			)
		);
	}

	/**
	 * Creates a pending purchase order.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function post_order( \WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		$package = Packages::get( (string) $request->get_param( 'package_id' ) );

		// A package must be active, priced and within its stock limit.
		if ( ! $package || ! Packages::is_purchasable( $package ) ) {
			return new \WP_REST_Response(
				array(
					'code'    => 'mcr_package_not_found',
					'message' => __( 'That coin package is not available.', 'madara-coin-rewards' ),
				),
				404
			);
		}

		$result = Orders::instance()->create( $user_id, $package );

		if ( is_wp_error( $result ) ) {
			return $this->error_response( $result );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $result,
			)
		);
	}

	/**
	 * Returns a single order belonging to the current user.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_order( \WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		$order   = Orders::instance()->get_for_user( (string) $request->get_param( 'order_id' ), $user_id );

		if ( ! $order ) {
			return new \WP_REST_Response(
				array(
					'code'    => 'mcr_order_not_found',
					'message' => __( 'Order not found.', 'madara-coin-rewards' ),
				),
				404
			);
		}

		return rest_ensure_response( array( 'data' => $order ) );
	}

	/**
	 * Returns the current user's transaction history.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_transactions( \WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		$page    = max( 1, absint( $request->get_param( 'page' ) ) );

		/*
		 * absint() turns a missing or malformed value into 0, so the clamp
		 * has to start at the default page size rather than 1 - otherwise a
		 * request that omits per_page silently returns ONE row per page and
		 * reports a page count of `total`.
		 */
		$per = $request->get_param( 'per_page' );
		$per = ( null === $per || '' === $per ) ? 20 : absint( $per );
		$per = min( 50, max( 1, $per ) );

		$transactions = Ledger::instance()->list_for_user( $user_id, $per, ( $page - 1 ) * $per );
		$total        = Ledger::instance()->count_for_user( $user_id );

		$rows = array();

		foreach ( (array) $transactions as $transaction ) {
			$rows[] = array(
				'id'             => (int) $transaction->id,
				'uid'            => $transaction->txn_uid,
				'type'           => $transaction->type,
				'amount'         => (int) $transaction->amount,
				'balance_after'  => (int) $transaction->balance_after,
				'reason'         => $transaction->reason,
				'source'         => $transaction->source,
				'created_at'     => $transaction->created_at,
				'created_display' => get_date_from_gmt( $transaction->created_at, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ),
			);
		}

		return rest_ensure_response(
			array(
				'transactions' => $rows,
				'total'        => $total,
				'page'         => $page,
				'per_page'     => $per,
				'pages'        => (int) ceil( $total / $per ),
			)
		);
	}

	/**
	 * Returns the rewards dashboard payload.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_dashboard() {
		$user_id = get_current_user_id();

		return rest_ensure_response(
			array(
				'balance'  => Ledger::instance()->get_stats( $user_id ),
				'checkin'  => CheckIn::instance()->status( $user_id ),
				'ads'      => AdSessions::instance()->stats( $user_id ),
				'packages' => Packages::get_active(),
			)
		);
	}

	/**
	 * Converts a WP_Error into a REST response.
	 *
	 * @param \WP_Error $error Error object.
	 * @return \WP_REST_Response
	 */
	private function error_response( \WP_Error $error ) {
		$data   = $error->get_error_data();
		$status = isset( $data['status'] ) ? (int) $data['status'] : 400;

		return new \WP_REST_Response(
			array(
				'code'    => $error->get_error_code(),
				'message' => $error->get_error_message(),
			),
			$status
		);
	}
}
