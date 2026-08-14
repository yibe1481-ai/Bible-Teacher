<?php
/**
 * Auth controller: validates Telegram initData and issues a session JWT.
 *
 * Route: POST /be/v1/auth/telegram
 * Body:  { initData: string }
 *
 * @package Bible_Teacher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once BIBLE_TEACHER_DIR . 'includes/REST/class-rest-base.php';

/**
 * Class BE_Auth_Controller
 */
class BE_Auth_Controller extends BE_REST_Base {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/auth/telegram',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'telegram_auth' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$this->namespace,
			'/telegram/webhook/(?P<secret>[a-zA-Z0-9]+)',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( 'BE_Webhook', 'handle' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Handle Telegram auth and issue a token.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function telegram_auth( $request ) {
		$ip = BE_Security::client_ip();
		if ( BE_Security::is_banned( $ip ) ) {
			return $this->error_response( new WP_Error( 'be_banned', __( 'Access temporarily blocked.', 'bible-teacher' ), array( 'status' => 403 ) ) );
		}
		if ( ! BE_Security::rate_limit_ok( 'auth:' . $ip, 30 ) ) {
			return $this->error_response( new WP_Error( 'be_rate_limited', __( 'Too many attempts.', 'bible-teacher' ), array( 'status' => 429 ) ) );
		}

		$init_data = $request->get_param( 'initData' );
		$telegram_user = BE_Auth::verify_init_data( $init_data );

		if ( ! $telegram_user ) {
			BE_Security::audit( 'auth', 'invalid_init_data', array( 'ip' => $ip ) );
			return $this->error_response( new WP_Error( 'be_auth_failed', __( 'Telegram authentication failed.', 'bible-teacher' ), array( 'status' => 401 ) ) );
		}

		$user = BE_Auth::resolve_user( $telegram_user );
		if ( is_wp_error( $user ) ) {
			return $this->error_response( $user );
		}
		if ( ! $user || ( new BE_User_Service() )->is_banned( $user ) ) {
			return $this->error_response( new WP_Error( 'be_banned', __( 'Cannot sign in.', 'bible-teacher' ), array( 'status' => 403 ) ) );
		}

		$token = BE_Auth::issue_token( $user['id'] );
		$streak = new BE_Streak( $user );

		return $this->respond( array(
			'token' => $token,
			'user'  => array(
				'id'          => (int) $user['id'],
				'level'       => $user['level'],
				'placement_completed' => (bool) $user['placement_completed'],
				'first_name'  => $user['first_name'],
				'username'    => $user['telegram_username'],
				'current_streak' => $streak->current(),
			),
		) );
	}
}