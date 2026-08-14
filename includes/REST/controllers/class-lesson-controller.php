<?php
/**
 * Lesson controller: today's lesson, step completions, and results.
 *
 * Routes:
 *  GET  /be/v1/lesson/today
 *  POST /be/v1/lesson/vocab/complete
 *  POST /be/v1/lesson/listening/complete
 *  POST /be/v1/lesson/quiz/submit      Body: { answers: [0,2,1] }
 *  POST /be/v1/lesson/speaking/submit  Body: multipart audio
 *  POST /be/v1/lesson/writing/submit   Body: { text: string }
 *  GET  /be/v1/lesson/feedback
 *
 * @package Bible_Teacher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once BIBLE_TEACHER_DIR . 'includes/REST/class-rest-base.php';

/**
 * Class BE_Lesson_Controller
 */
class BE_Lesson_Controller extends BE_REST_Base {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$routes = array(
			'/lesson/today'            => array( \WP_REST_Server::READABLE, 'today' ),
			'/lesson/vocab/complete'   => array( \WP_REST_Server::CREATABLE, 'complete_vocab' ),
			'/lesson/listening/complete' => array( \WP_REST_Server::CREATABLE, 'complete_listening' ),
			'/lesson/quiz/submit'      => array( \WP_REST_Server::CREATABLE, 'submit_quiz' ),
			'/lesson/speaking/submit'  => array( \WP_REST_Server::CREATABLE, 'submit_speaking' ),
			'/lesson/writing/submit'   => array( \WP_REST_Server::CREATABLE, 'submit_writing' ),
			'/lesson/feedback'         => array( \WP_REST_Server::READABLE, 'feedback' ),
		);

		foreach ( $routes as $route => $def ) {
			register_rest_route( $this->namespace, $route, array(
				'methods'             => $def[0],
				'callback'            => array( $this, $def[1] ),
				'permission_callback' => array( $this, 'authenticate' ),
			) );
		}
	}

	/**
	 * Today's lesson.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function today( $request ) {
		$service = new BE_Lesson_Service();
		$lesson  = $service->today( $request['current_user'] );

		if ( is_wp_error( $lesson ) ) {
			return $this->error_response( $lesson );
		}
		return $this->respond( $lesson );
	}

	/**
	 * Complete vocab step.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function complete_vocab( $request ) {
		return $this->complete_step( $request, 'vocab' );
	}

	/**
	 * Complete listening step.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function complete_listening( $request ) {
		return $this->complete_step( $request, 'listening' );
	}

	/**
	 * Shared step completion using today's verse.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @param string           $step    Step key.
	 * @return \WP_REST_Response
	 */
	protected function complete_step( $request, $step ) {
		$user  = $request['current_user'];
		$lesson = new BE_Lesson_Service();
		$today = $lesson->today( $user );

		if ( is_wp_error( $today ) ) {
			return $this->error_response( $today );
		}

		$result = $lesson->complete_step( $user, array(
			'reference' => $today['reference'],
		), $step, array() );

		return $this->respond( array(
			'awarded'   => $result['awarded'],
			'completed' => $result['completed'],
			'streak'    => $result['streak'],
		) );
	}

	/**
	 * Submit quiz answers and score.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function submit_quiz( $request ) {
		$user = $request['current_user'];
		$body = $request->get_json_params();
		$lesson = new BE_Lesson_Service();
		$today  = $lesson->today( $user );

		if ( is_wp_error( $today ) ) {
			return $this->error_response( $today );
		}

		$answers = isset( $body['answers'] ) && is_array( $body['answers'] ) ? $body['answers'] : array();
		$questions = isset( $today['quiz']['questions'] ) ? $today['quiz']['questions'] : array();

		$score = 0;
		foreach ( $answers as $i => $answer ) {
			if ( isset( $questions[ $i ]['correct_index'] ) && (int) $answer === (int) $questions[ $i ]['correct_index'] ) {
				$score++;
			}
		}
		$total = count( $questions );

		$result = $lesson->complete_step( $user, $today, 'quiz', array(
			'score' => $score,
			'total' => $total,
		) );

		return $this->respond( array(
			'score'    => $score,
			'total'    => $total,
			'awarded'  => $result['awarded'],
			'perfect'  => $total > 0 && $score === $total,
			'completed'=> $result['completed'],
		) );
	}

	/**
	 * Submit a speaking recording (multipart audio), transcribe, and score.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function submit_speaking( $request ) {
		$user  = $request['current_user'];

		// Maximum upload size check.
		$whisper = new BE_Whisper();
		$max_bytes = $whisper->max_upload_bytes();

		$files = $request->get_file_params();
		if ( empty( $files['audio'] ) ) {
			return $this->error_response( new WP_Error( 'be_no_audio', __( 'No audio file provided.', 'bible-teacher' ) ) );
		}

		$file = $files['audio'];
		if ( is_array( $file ) ) {
			$tmp_name = isset( $file['tmp_name'] ) ? $file['tmp_name'] : '';
			$size     = isset( $file['size'] ) ? (int) $file['size'] : 0;
		} else {
			return $this->error_response( new WP_Error( 'be_bad_upload', __( 'Unexpected upload format.', 'bible-teacher' ) ) );
		}

		if ( $size <= 0 || $size > $max_bytes ) {
			return $this->error_response( new WP_Error( 'be_audio_too_large', __( 'Audio file is too large.', 'bible-teacher' ) ) );
		}

		// Move into our managed upload dir.
		$dir  = trailingslashit( wp_upload_dir()['basedir'] ) . 'bible-teacher';
		$name = $user['id'] . '-' . gmdate( 'ymdHis' ) . '-speaking.webm';
		$dest = $dir . '/' . $name;

		if ( ! move_uploaded_file( $tmp_name, $dest ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			return $this->error_response( new WP_Error( 'be_upload_failed', __( 'Could not store the recording.', 'bible-teacher' ) ) );
		}

		// Fetch today's verse for scoring reference.
		$lesson = new BE_Lesson_Service();
		$today  = $lesson->today( $user );
		if ( is_wp_error( $today ) ) {
			return $this->error_response( $today );
		}
		$verse_text = $today['verse']['text'];

		// Transcribe.
		$transcription = $whisper->transcribe( $dest, $user['id'] );

		if ( ! $transcription->success ) {
			// Partial XP for a genuine attempt (spec §9.2).
			$xp = new BE_XP_Manager();
			$config = BE_Options::get( 'competition', 'xp' );
			$partial = (int) ( $config['speaking_attempt'] ?? 20 );
			$xp->award( $user, $partial, 'speaking_attempt_partial', 'lesson' );

			return $this->respond( array(
				'transcription_available' => false,
				'partial_xp'              => $partial,
				'message'                 => __( 'Transcription unavailable, but you earned XP for the attempt!', 'bible-teacher' ),
			) );
		}

		// Score via AI.
		$scorer = new BE_Speaking_Scorer( new BE_AI_Manager() );
		$result = $scorer->score( $verse_text, $transcription->text, $user['level'], $user['id'] );

		$lesson_result = $lesson->complete_step( $user, $today, 'speaking', array(
			'score' => $result['score'],
		) );

		// Cleanup audio file after processing.
		wp_delete_file( $dest );

		return $this->respond( array(
			'transcription_available' => true,
			'transcription'           => $transcription->text,
			'result'                  => $result,
			'awarded'                 => $lesson_result['awarded'],
			'pass'                    => $scorer->passes( $result['score'], $user['level'] ),
			'completed'               => $lesson_result['completed'],
		) );
	}

	/**
	 * Submit writing exercise.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function submit_writing( $request ) {
		$user = $request['current_user'];
		$body = $request->get_json_params();
		$text = isset( $body['text'] ) ? sanitize_textarea_field( $body['text'] ) : '';

		$lesson = new BE_Lesson_Service();
		$today  = $lesson->today( $user );
		if ( is_wp_error( $today ) ) {
			return $this->error_response( $today );
		}

		$verse_text = $today['verse']['text'];
		$level      = $user['level'];

		// Non-advanced uses string accuracy; advanced uses AI.
		$scorer = null;
		$result = null;

		if ( 'advanced' === $level ) {
			$scorer = new BE_Writing_Scorer( new BE_AI_Manager() );
			$result = $scorer->score( $verse_text, $text, $user['id'] );
		} else {
			$mode = $lesson->writing_mode( $level );
			// copy/blanks: score by string overlap against the verse.
			$target_words = array_map( 'strtolower', str_word_count( wp_strip_all_tags( $verse_text ), 1 ) );
			$user_words   = array_map( 'strtolower', str_word_count( $text, 1 ) );
			$matched = count( array_intersect( $target_words, $user_words ) );
			$denom   = max( 1, count( $target_words ) );
			$score   = (int) round( ( $matched / $denom ) * 100 );
			$result  = array( 'score' => $score );
		}

		$lesson_result = $lesson->complete_step( $user, $today, 'writing', array(
			'score' => $result['score'],
		) );

		return $this->respond( array(
			'result'    => $result,
			'awarded'   => $lesson_result['awarded'],
			'completed' => $lesson_result['completed'],
		) );
	}

	/**
	 * Feedback + tomorrow preview.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function feedback( $request ) {
		$lesson = new BE_Lesson_Service();
		$results = $lesson->results( $request['current_user'] );
		return $this->respond( $results );
	}
}