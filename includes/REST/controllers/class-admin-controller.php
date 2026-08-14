<?php
/**
 * Admin controller (requires manage_options).
 *
 * Routes:
 *  GET  /be/v1/admin/stats/overview
 *  GET  /be/v1/admin/users
 *  GET  /be/v1/admin/ai/logs
 *  POST /be/v1/admin/ai/test
 *  POST /be/v1/admin/telegram/test
 *  POST /be/v1/admin/telegram/webhook/set
 *
 * @package Bible_Teacher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once BIBLE_TEACHER_DIR . 'includes/REST/class-rest-base.php';

/**
 * Class BE_Admin_Controller
 */
class BE_Admin_Controller extends BE_REST_Base {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$admin = array( 'permission_callback' => array( $this, 'authenticate_admin' ) );

		register_rest_route( $this->namespace, '/admin/stats/overview', array_merge( array(
			'methods'  => \WP_REST_Server::READABLE,
			'callback' => array( $this, 'overview' ),
		), $admin ) );

		register_rest_route( $this->namespace, '/admin/users', array_merge( array(
			'methods'  => \WP_REST_Server::READABLE,
			'callback' => array( $this, 'users' ),
		), $admin ) );

		register_rest_route( $this->namespace, '/admin/ai/logs', array_merge( array(
			'methods'  => \WP_REST_Server::READABLE,
			'callback' => array( $this, 'ai_logs' ),
		), $admin ) );

		register_rest_route( $this->namespace, '/admin/ai/test', array_merge( array(
			'methods'  => \WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'ai_test' ),
		), $admin ) );

		register_rest_route( $this->namespace, '/admin/telegram/test', array_merge( array(
			'methods'  => \WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'telegram_test' ),
		), $admin ) );

		register_rest_route( $this->namespace, '/admin/telegram/webhook/set', array_merge( array(
			'methods'  => \WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'webhook_set' ),
		), $admin ) );
	}

	/**
	 * Dashboard overview metrics.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function overview( $request ) {
		global $wpdb;
		$users  = $wpdb->prefix . BIBLE_TEACHER_DB_PREFIX . 'users';
		$streak = $wpdb->prefix . BIBLE_TEACHER_DB_PREFIX . 'streaks';

		$total_users = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$users}" );
		$active_today = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$users} WHERE last_active_at >= %s",
			gmdate( 'Y-m-d 00:00:00' )
		) );

		$breakdown = array();
		foreach ( array( 'beginner', 'intermediate', 'advanced' ) as $lvl ) {
			$breakdown[ $lvl ] = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$users} WHERE level = %s",
				$lvl
			) );
		}

		$active_streaks = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$streak} WHERE current_streak > 0" );

		// AI usage today per provider.
		$logs   = $wpdb->prefix . BIBLE_TEACHER_DB_PREFIX . 'ai_logs';
		$ai_usage = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$logs} WHERE created_at >= %s",
			gmdate( 'Y-m-d 00:00:00' )
		) );

		return $this->respond( array(
			'total_users'    => $total_users,
			'active_today'   => $active_today,
			'active_streaks' => $active_streaks,
			'level_breakdown'=> $breakdown,
			'ai_requests_today' => $ai_usage,
		) );
	}

	/**
	 * Paginated user list.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function users( $request ) {
		global $wpdb;
		$table  = $wpdb->prefix . BIBLE_TEACHER_DB_PREFIX . 'users';
		$praise = $wpdb->prefix . BIBLE_TEACHER_DB_PREFIX . 'streaks';
		$page   = max( 1, (int) ( $request->get_param( 'page' ) ?: 1 ) );
		$per    = 50;
		$offset = ( $page - 1 ) * $per;

		$sql = $wpdb->prepare(
			"SELECT u.id, u.telegram_user_id, u.first_name, u.last_name, u.telegram_username, u.level, u.status, u.created_at,
			        COALESCE((SELECT s.current_streak FROM {$praise} s WHERE s.user_id = u.id),0) AS streak
			 FROM {$table} u ORDER BY u.id DESC LIMIT %d OFFSET %d",
			$per,
			$offset
		);

		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL

		return $this->respond( array( 'users' => $rows, 'page' => $page ) );
	}

	/**
	 * AI logs with filters.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function ai_logs( $request ) {
		$filters = array(
			'feature' => $request->get_param( 'feature' ),
			'provider'=> $request->get_param( 'provider' ),
			'status'  => $request->get_param( 'status' ),
			'from'    => $request->get_param( 'from' ),
			'to'      => $request->get_param( 'to' ),
		);

		$rows = BE_AI_Logger::query( $filters, 50, max( 1, (int) ( $request->get_param( 'page' ) ?: 1 ) ) );
		return $this->respond( array( 'logs' => $rows ) );
	}

	/**
	 * Test an AI provider.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function ai_test( $request ) {
		$body = $request->get_json_params();
		$id   = isset( $body['provider_id'] ) ? sanitize_text_field( $body['provider_id'] ) : '';

		$ai     = new BE_AI_Manager();
		$config = $ai->provider_config( $id );
		if ( empty( $config ) ) {
			return $this->error_response( new WP_Error( 'be_no_provider', __( 'Provider not found.', 'bible-teacher' ) ) );
		}

		$adapter = new BE_OpenAI_Adapter( $config );
		$result  = $adapter->test_connection();

		return $this->respond( array(
			'ok'         => $result->ok,
			'message'    => $result->message,
			'latency_ms' => $result->latency_ms,
		) );
	}

	/**
	 * Test Telegram connectivity.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function telegram_test( $request ) {
		$bot = new BE_BotAPI();
		if ( ! $bot->is_configured() ) {
			return $this->error_response( new WP_Error( 'be_no_token', __( 'Bot token not configured.', 'bible-teacher' ) ) );
		}
		$info = $bot->webhook_info();
		if ( is_wp_error( $info ) ) {
			return $this->error_response( $info );
		}
		return $this->respond( array( 'ok' => true, 'info' => $info ) );
	}

	/**
	 * Set the Telegram webhook.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function webhook_set( $request ) {
		$body = $request->get_json_params();
		$url  = isset( $body['url'] ) ? esc_url_raw( $body['url'] ) : '';
		$secret = (string) get_option( 'bible_teacher_webhook_secret', '' );

		// Fall back to the configured URL if none given.
		if ( '' === $url ) {
			$url = BE_Options::get( 'telegram', 'webhook_url' );
		}
		if ( '' === $url ) {
			$url = home_url( '/wp-json/be/v1/telegram/webhook/' . $secret );
		}

		$bot = new BE_BotAPI();
		$result = $bot->set_webhook( $url, $secret );
		if ( is_wp_error( $result ) ) {
			return $this->error_response( $result );
		}

		// Persist the active webhook URL.
		$options = get_option( 'bible_teacher_options', array() );
		$options['telegram']['webhook_url'] = $url;
		update_option( 'bible_teacher_options', $options );
		BE_Options::reset();

		return $this->respond( array( 'ok' => true, 'result' => $result, 'url' => $url ) );
	}
}