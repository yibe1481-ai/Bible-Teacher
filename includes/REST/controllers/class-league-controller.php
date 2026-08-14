<?php
/**
 * League & leaderboard controller.
 *
 * Routes:
 *  GET /be/v1/league/current
 *  GET /be/v1/league/history
 *  GET /be/v1/leaderboard/global?level=beginner&limit=20
 *
 * @package Bible_Teacher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once BIBLE_TEACHER_DIR . 'includes/REST/class-rest-base.php';

/**
 * Class BE_League_Controller
 */
class BE_League_Controller extends BE_REST_Base {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$auth = array( 'permission_callback' => array( $this, 'authenticate' ) );

		register_rest_route( $this->namespace, '/league/current', array_merge( array(
			'methods'  => \WP_REST_Server::READABLE,
			'callback' => array( $this, 'current' ),
		), $auth ) );

		register_rest_route( $this->namespace, '/league/history', array_merge( array(
			'methods'  => \WP_REST_Server::READABLE,
			'callback' => array( $this, 'history' ),
		), $auth ) );

		register_rest_route( $this->namespace, '/leaderboard/global', array_merge( array(
			'methods'  => \WP_REST_Server::READABLE,
			'callback' => array( $this, 'global_leaderboard' ),
		), $auth ) );
	}

	/**
	 * Current league and user's position.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function current( $request ) {
		$user   = $request['current_user'];
		$leagues= new BE_League_Manager();
		$league = $leagues->get_active_league( $user );

		$board = $leagues->leaderboard( $league['id'] );
		$rank  = 0;
		foreach ( $board as $i => $entry ) {
			if ( (int) $entry['user_id'] === (int) $user['id'] ) {
				$rank = $i + 1;
				break;
			}
		}

		return $this->respond( array(
			'league' => array(
				'id'        => (int) $league['id'],
				'name'      => $league['name'],
				'division'  => $league['division'],
				'level'     => $league['level'],
				'week_start'=> $league['week_start'],
				'week_end'  => $league['week_end'],
			),
			'my_rank'  => $rank,
			'leaderboard' => $board,
		) );
	}

	/**
	 * Leaderboard history for a user.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function history( $request ) {
		global $wpdb;
		$user    = $request['current_user'];
		$leagues = $wpdb->prefix . BIBLE_TEACHER_DB_PREFIX . 'leagues';
		$members = $wpdb->prefix . BIBLE_TEACHER_DB_PREFIX . 'league_members';

		$history = $wpdb->get_results( $wpdb->prepare(
			"SELECT l.name, l.division, l.week_start, m.rank, m.final_xp, m.outcome
			 FROM {$members} m
			 INNER JOIN {$leagues} l ON l.id = m.league_id
			 WHERE m.user_id = %d AND l.status = 'completed'
			 ORDER BY l.week_start DESC LIMIT 20",
			$user['id']
		), ARRAY_A );

		return $this->respond( array( 'history' => $history ) );
	}

	/**
	 * Global leaderboard filtered by level.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function global_leaderboard( $request ) {
		global $wpdb;
		$xptable = $wpdb->prefix . BIBLE_TEACHER_DB_PREFIX . 'xp';
		$users   = $wpdb->prefix . BIBLE_TEACHER_DB_PREFIX . 'users';

		$level  = sanitize_text_field( $request->get_param( 'level' ) ?: 'beginner' );
		$limit  = (int) ( $request->get_param( 'limit' ) ?: 20 );
		$limit  = max( 1, min( 100, $limit ) );

		$key = 'be_lb_' . $level . '_' . (int) ( gmdate( 'YW' ) );
		$rows = get_transient( $key );

		if ( false === $rows ) {
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT u.id, u.first_name, u.telegram_username, x.weekly_xp
				 FROM {$xptable} x
				 INNER JOIN {$users} u ON u.id = x.user_id
				 WHERE u.level = %s AND u.status = 'active'
				 ORDER BY x.weekly_xp DESC LIMIT %d",
				$level,
				$limit
			), ARRAY_A );
			set_transient( $key, $rows, HOUR_IN_SECONDS );
		}

		return $this->respond( array( 'level' => $level, 'leaderboard' => $rows ) );
	}
}