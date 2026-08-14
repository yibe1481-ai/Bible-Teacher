<?php
/**
 * Placement quiz controller (spec §3.1).
 *
 * Routes:
 *  GET  /be/v1/placement/questions
 *  POST /be/v1/placement/submit   Body: { answers: [0,1,2], time_seconds: 24 }
 *
 * @package Bible_Teacher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once BIBLE_TEACHER_DIR . 'includes/REST/class-rest-base.php';

/**
 * Class BE_Placement_Controller
 */
class BE_Placement_Controller extends BE_REST_Base {

	/**
	 * Static placement questions.
	 *
	 * @var array
	 */
	private $questions = array(
		array(
			'id'       => 'q1_vocab',
			'type'     => 'vocabulary',
			'question' => "What does the word 'eternal' mean?",
			'options'  => array( 'Very old', 'Lasting forever', 'Very large', 'Far away' ),
			'correct'  => 1,
		),
		array(
			'id'       => 'q2_fill',
			'type'     => 'fill_blank',
			'question' => "For God so loved the world, that he ___ his only begotten Son.",
			'options'  => array( 'sends', 'gave', 'make', 'bring' ),
			'correct'  => 1,
		),
		array(
			'id'       => 'q3_comprehension',
			'type'     => 'comprehension',
			'question' => 'What does John 3:16 say is required to have everlasting life?',
			'options'  => array( 'Doing good works', 'Believing in Jesus', 'Going to church', 'Reading the Bible' ),
			'correct'  => 1,
		),
	);

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$auth = array( 'permission_callback' => array( $this, 'authenticate' ) );

		register_rest_route( $this->namespace, '/placement/questions', array_merge( array(
			'methods'  => \WP_REST_Server::READABLE,
			'callback' => array( $this, 'questions' ),
		), $auth ) );

		register_rest_route( $this->namespace, '/placement/submit', array_merge( array(
			'methods'  => \WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'submit' ),
		), $auth ) );
	}

	/**
	 * Return placement questions (without the correct indices).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function questions( $request ) {
		$stripped = array();
		foreach ( $this->questions as $q ) {
			$stripped[] = array(
				'id'       => $q['id'],
				'type'     => $q['type'],
				'question' => $q['question'],
				'options'  => $q['options'],
			);
		}
		return $this->respond( array( 'questions' => $stripped ) );
	}

	/**
	 * Score the placement quiz and assign a level (spec §3.1 scoring).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function submit( $request ) {
		$user = $request['current_user'];
		$body = $request->get_json_params();

		if ( ! isset( $body['answers'] ) || ! is_array( $body['answers'] ) ) {
			return $this->error_response( new WP_Error( 'be_invalid_answers', __( 'Answers are required.', 'bible-teacher' ) ) );
		}

		$time_seconds = isset( $body['time_seconds'] ) ? (int) $body['time_seconds'] : 0;

		$correct = 0;
		foreach ( $body['answers'] as $i => $answer ) {
			if ( isset( $this->questions[ $i ] ) && (int) $answer === (int) $this->questions[ $i ]['correct'] ) {
				$correct++;
			}
		}

		// §3.1: 0-1 → beginner; 2 → intermediate; 3 fast → advanced; 3 slow → intermediate.
		if ( $correct <= 1 ) {
			$level = 'beginner';
		} elseif ( $correct === 2 ) {
			$level = 'intermediate';
		} elseif ( $time_seconds > 0 && $time_seconds < 30 ) {
			$level = 'advanced';
		} else {
			$level = 'intermediate';
		}

		$service = new BE_User_Service();
		$service->set_level( $user['id'], $level );

		// Mark placement complete.
		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . BIBLE_TEACHER_DB_PREFIX . 'users',
			array(
				'placement_completed' => 1,
				'updated_at'          => current_time( 'mysql', true ),
			),
			array( 'id' => $user['id'] ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		// Never reveal the score to the user.
		return $this->respond( array(
			'level' => $level,
			'placement_completed' => true,
		) );
	}
}