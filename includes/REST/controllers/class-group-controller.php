<?php
/**
 * Group / Church mode controller.
 *
 * Routes:
 *  GET  /be/v1/groups/mine
 *  POST /be/v1/groups/create          {name, description, verse_focus_book}
 *  POST /be/v1/groups/join            {invite_code}
 *  GET  /be/v1/groups/{id}/leaderboard
 *
 * @package Bible_Teacher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once BIBLE_TEACHER_DIR . 'includes/REST/class-rest-base.php';

/**
 * Class BE_Group_Controller
 */
class BE_Group_Controller extends BE_REST_Base {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$auth = array( 'permission_callback' => array( $this, 'authenticate' ) );

		register_rest_route( $this->namespace, '/groups/mine', array_merge( array(
			'methods'  => \WP_REST_Server::READABLE,
			'callback' => array( $this, 'mine' ),
		), $auth ) );

		register_rest_route( $this->namespace, '/groups/create', array_merge( array(
			'methods'  => \WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'create' ),
		), $auth ) );

		register_rest_route( $this->namespace, '/groups/join', array_merge( array(
			'methods'  => \WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'join' ),
		), $auth ) );

		register_rest_route(
			$this->namespace,
			'/groups/(?P<id>\d+)/leaderboard',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'leaderboard' ),
				'permission_callback' => array( $this, 'authenticate' ),
			)
		);
	}

	/**
	 * My groups.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function mine( $request ) {
		$user   = $request['current_user'];
		$groups = ( new BE_Group_Manager() )->mine( $user['id'] );
		return $this->respond( array( 'groups' => $groups ) );
	}

	/**
	 * Create a group.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create( $request ) {
		$user  = $request['current_user'];
		$body  = $request->get_json_params();
		$group = ( new BE_Group_Manager() )->create( $user, $body );

		if ( is_wp_error( $group ) ) {
			return $this->error_response( $group );
		}
		return $this->respond( array( 'group' => $group ), 201 );
	}

	/**
	 * Join a group by code.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function join( $request ) {
		$user  = $request['current_user'];
		$body  = $request->get_json_params();
		$code  = isset( $body['invite_code'] ) ? $body['invite_code'] : '';

		$group = ( new BE_Group_Manager() )->join( $user, $code );
		if ( is_wp_error( $group ) ) {
			return $this->error_response( $group );
		}
		return $this->respond( array( 'group' => $group ) );
	}

	/**
	 * Group leaderboard.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function leaderboard( $request ) {
		$user  = $request['current_user'];
		$group = ( new BE_Group_Manager() )->get( (int) $request['id'] );

		if ( ! $group || ! ( new BE_Group_Manager() )->is_member( $group['id'], $user['id'] ) ) {
			return $this->error_response( new WP_Error( 'be_group_forbidden', __( 'Not a member of this group.', 'bible-teacher' ), array( 'status' => 403 ) ) );
		}

		$board = ( new BE_Group_Manager() )->leaderboard( $group['id'] );
		return $this->respond( array( 'group' => $group, 'leaderboard' => $board ) );
	}
}