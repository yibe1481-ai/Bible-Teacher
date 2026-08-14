<?php
/**
 * User controller: profile, settings, badges, stats.
 *
 * Routes:
 *  GET /be/v1/me
 *  PUT /be/v1/me/settings
 *  GET /be/v1/me/badges
 *  GET /be/v1/me/stats
 *
 * @package Bible_Teacher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once BIBLE_TEACHER_DIR . 'includes/REST/class-rest-base.php';

/**
 * Class BE_User_Controller
 */
class BE_User_Controller extends BE_REST_Base {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$auth = array( 'permission_callback' => array( $this, 'authenticate' ) );

		register_rest_route( $this->namespace, '/me', array_merge( array(
			'methods'  => \WP_REST_Server::READABLE,
			'callback' => array( $this, 'me' ),
		), $auth ) );

		register_rest_route( $this->namespace, '/me/settings', array_merge( array(
			'methods'  => \WP_REST_Server::EDITABLE,
			'callback' => array( $this, 'update_settings' ),
		), $auth ) );

		register_rest_route( $this->namespace, '/me/badges', array_merge( array(
			'methods'  => \WP_REST_Server::READABLE,
			'callback' => array( $this, 'badges' ),
		), $auth ) );

		register_rest_route( $this->namespace, '/me/stats', array_merge( array(
			'methods'  => \WP_REST_Server::READABLE,
			'callback' => array( $this, 'stats' ),
		), $auth ) );
	}

	/**
	 * Current user profile.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function me( $request ) {
		$user = $request['current_user'];
		$streak = new BE_Streak( $user );

		return $this->respond( array(
			'id'          => (int) $user['id'],
			'first_name'  => $user['first_name'],
			'last_name'   => $user['last_name'],
			'username'    => $user['telegram_username'],
			'language'    => $user['language_code'],
			'timezone'    => $user['timezone'],
			'level'       => $user['level'],
			'placement_completed' => (bool) $user['placement_completed'],
			'notification_time'   => $user['notification_time'],
			'notifications_enabled' => (bool) $user['notifications_enabled'],
			'current_streak' => $streak->current(),
			'longest_streak' => $streak->longest(),
		) );
	}

	/**
	 * Update current user settings (whitelisted).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_settings( $request ) {
		$user = $request['current_user'];
		$service = new BE_User_Service();

		$data = array();
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			return $this->error_response( new WP_Error( 'be_invalid_body', __( 'Invalid request body.', 'bible-teacher' ) ) );
		}

		if ( isset( $body['level'] ) ) {
			$service->set_level( $user['id'], sanitize_text_field( $body['level'] ) );
		}
		$service->update_settings( $user['id'], $body );

		$fresh = $service->get( $user['id'] );
		return $this->respond( $fresh );
	}

	/**
	 * User badges.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function badges( $request ) {
		$user = $request['current_user'];
		$badges = new BE_Badge_Manager();
		return $this->respond( array( 'badges' => $badges->get_user_badges( $user['id'] ) ) );
	}

	/**
	 * User aggregate stats.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function stats( $request ) {
		$user = $request['current_user'];
		$service = new BE_User_Service();
		return $this->respond( $service->stats( $user['id'] ) );
	}
}