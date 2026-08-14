<?php
/**
 * Shared REST controller base: route registration, JWT auth, rate limiting,
 * and current-user resolution.
 *
 * All authenticated endpoints under /be/v1/ extend this base so that
 * identity is always resolved server-side from the verified JWT.
 *
 * @package Bible_Teacher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BE_REST_Base
 */
class BE_REST_Base {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'be/v1';

	/**
	 * Register routes (override in subclasses).
	 *
	 * @return void
	 */
	public function register_routes() {
		// Implemented by subclasses.
	}

	/**
	 * Permission callback for authenticated endpoints.
	 *
	 * Validates the Bearer JWT and returns the resolved current user on success.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array|false
	 */
	public function authenticate( $request ) {
		// Rate limit by IP.
		$ip = BE_Security::client_ip();
		if ( BE_Security::is_banned( $ip ) ) {
			return false;
		}
		if ( ! BE_Security::rate_limit_ok( 'rest:' . $ip . ':' . $request->get_route() ) ) {
			return new WP_Error( 'be_rate_limited', __( 'Too many requests, please slow down.', 'bible-teacher' ), array( 'status' => 429 ) );
		}

		$header = $request->get_header( 'Authorization' );
		$token  = BE_Auth::bearer_token( $header );
		if ( '' === $token ) {
			return new WP_Error( 'be_auth_required', __( 'Missing bearer token.', 'bible-teacher' ), array( 'status' => 401 ) );
		}

		$claims = BE_JWT::decode( $token );
		if ( ! $claims || empty( $claims['uid'] ) ) {
			return new WP_Error( 'be_auth_invalid', __( 'Invalid or expired token.', 'bible-teacher' ), array( 'status' => 401 ) );
		}

		$user = ( new BE_User_Service() )->get( (int) $claims['uid'] );
		if ( ! $user || ( new BE_User_Service() )->is_banned( $user ) ) {
			return new WP_Error( 'be_auth_invalid', __( 'Invalid account.', 'bible-teacher' ), array( 'status' => 403 ) );
		}
		if ( empty( BE_Options::get( 'general', 'plugin_enabled' ) ) ) {
			return new WP_Error( 'be_maintenance', __( 'The app is in maintenance mode.', 'bible-teacher' ), array( 'status' => 503 ) );
		}

		$request['current_user'] = $user;
		return true;
	}

	/**
	 * Permission callback for admin-only endpoints.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public function authenticate_admin( $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'be_forbidden', __( 'Admin access required.', 'bible-teacher' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * Helper: wrap a result as a REST response with a status code.
	 *
	 * @param mixed $data   Response data.
	 * @param int   $status HTTP status.
	 * @return \WP_REST_Response
	 */
	protected function respond( $data, $status = 200 ) {
		return new WP_REST_Response( $data, $status );
	}

	/**
	 * Convert a WP_Error to a REST response.
	 *
	 * @param \WP_Error $error Error object.
	 * @return \WP_REST_Response
	 */
	protected function error_response( $error ) {
		$code   = $error->get_error_code();
		$status = 400;
		$data   = $error->get_error_data();
		if ( is_array( $data ) && isset( $data['status'] ) ) {
			$status = (int) $data['status'];
		}
		return new WP_REST_Response( array(
			'error'   => $code,
			'message' => $error->get_error_message(),
		), $status );
	}
}