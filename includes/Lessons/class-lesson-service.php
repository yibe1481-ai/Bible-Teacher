<?php
/**
 * Lesson orchestration: assembles the daily lesson, records step completions,
 * awards XP, and returns feedback + tomorrow's preview.
 *
 * @package Bible_Teacher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once BIBLE_TEACHER_DIR . 'includes/Database/class-repository.php';

/**
 * Class BE_Lesson_Service
 */
class BE_Lesson_Service extends BE_Repository {

	/**
	 * AI manager instance.
	 *
	 * @var BE_AI_Manager
	 */
	protected $ai;

	/**
	 * Verse sequencer.
	 *
	 * @var BE_Verse_Sequencer
	 */
	protected $sequencer;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->ai        = new BE_AI_Manager();
		$this->sequencer = new BE_Verse_Sequencer();
	}

	/**
	 * Get today's lesson for a user.
	 *
	 * @param array $user User row.
	 * @return array Lesson payload.
	 */
	public function today( $user ) {
		$date  = gmdate( 'Y-m-d' );
		$reference = $this->sequencer->verse_for( $user, $date );

		$bible = new BE_Bible();
		$verse = $bible->fetch( $reference );

		if ( ! $verse ) {
			return new WP_Error( 'be_verse_unavailable', __( 'Today\'s verse is unavailable, please try again shortly.', 'bible-teacher' ) );
		}

		// Ensure a progress row exists.
		$this->ensure_progress( $user['id'], $verse, $date );

		return array(
			'verse'      => $verse,
			'reference'  => $verse['reference'],
			'level'      => $user['level'],
			'date'       => $date,
			'vocab'      => $this->vocabulary( $verse, $user ),
			'listening'  => $this->listening( $verse, $user ),
			'quiz'       => $this->quiz( $verse, $user, 0 ),
			'writing'    => array(
				'mode' => $this->writing_mode( $user['level'] ),
			),
			'streak'     => ( new BE_Streak( $user ) )->current(),
			'weekly_xp'  => (int) ( new BE_XP_Manager() )->totals( $user['id'] )['weekly_xp'],
		);
	}

	/**
	 * Generate vocabulary (AI, cached per verse+level).
	 *
	 * @param array $verse Verse array.
	 * @param array $user  User row.
	 * @return array
	 */
	protected function vocabulary( $verse, $user ) {
		$vocab = new BE_Vocabulary_Generator( $this->ai );
		return $vocab->generate( $verse['text'], $verse, $user['level'], $verse['reference'] );
	}

	/**
	 * Listening payload.
	 *
	 * @param array $verse Verse array.
	 * @param array $user  User row.
	 * @return array
	 */
	protected function listening( $verse, $user ) {
		$tts = new BE_TTS_Service();
		return $tts->synthesize( $verse['text'], $user['level'] );
	}

	/**
	 * Generate quiz (AI, cached per verse+level+variant).
	 *
	 * @param array $verse   Verse array.
	 * @param array $user    User row.
	 * @param int   $variant Variant index.
	 * @return array
	 */
	protected function quiz( $verse, $user, $variant ) {
		$quiz = new BE_Quiz_Generator( $this->ai );
		return $quiz->generate( $verse['text'], $user['level'], $verse['reference'], $variant );
	}

	/**
	 * Writing mode per level.
	 *
	 * @param string $level Level.
	 * @return string copy|blanks|paraphrase
	 */
	protected function writing_mode( $level ) {
		if ( 'advanced' === $level ) {
			return 'paraphrase';
		}
		if ( 'intermediate' === $level ) {
			return 'blanks';
		}
		return 'copy';
	}

	/**
	 * Ensure a progress row for the user+date (upsert).
	 *
	 * @param int    $user_id User id.
	 * @param array  $verse   Verse array.
	 * @param string $date    Lesson date.
	 * @return void
	 */
	protected function ensure_progress( $user_id, $verse, $date ) {
		global $wpdb;
		$table = $this->table( 'progress' );

		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table} WHERE user_id = %d AND lesson_date = %s",
			$user_id,
			$date
		) );

		if ( $existing ) {
			return;
		}

		$wpdb->insert(
			$table,
			array(
				'user_id'         => $user_id,
				'verse_reference' => $verse['reference'],
				'book'            => $verse['book'],
				'chapter'         => $verse['chapter'],
				'verse_number'    => $verse['verse_number'],
				'lesson_date'     => $date,
				'completed'       => 0,
				'created_at'      => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%d', '%d', '%s', '%d', '%s' )
		);
	}

	/**
	 * Mark a step complete and award XP.
	 *
	 * @param array  $user User row.
	 * @param array  $verse Verse array.
	 * @param string $step vocab|listening|quiz|speaking|writing.
	 * @param array  $result Optional step result (score etc).
	 * @return array {progress, xp_awarded}
	 */
	public function complete_step( $user, $verse, $step, $result = array() ) {
		$date  = gmdate( 'Y-m-d' );
		$xp    = new BE_XP_Manager();
		$config= BE_Options::get( 'competition', 'xp' );

		global $wpdb;
		$table = $this->table( 'progress' );

		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE user_id = %d AND lesson_date = %s",
			$user['id'],
			$date
		), ARRAY_A );

		$base_xp    = 0;
		$field      = '';
		$update     = array();
		$bonus_xp   = 0;

		switch ( $step ) {
			case 'vocab':
				$base_xp = (int) $config['vocab'];
				$field   = 'vocab_completed';
				$update[ $field ] = 1;
				break;
			case 'listening':
				$base_xp = (int) $config['listening'];
				$field   = 'listening_completed';
				$update[ $field ] = 1;
				break;
			case 'quiz':
				$base_xp   = (int) $config['quiz'];
				$field     = 'quiz_completed';
				$score     = (int) ( $result['score'] ?? 0 );
				$total     = (int) ( $result['total'] ?? 1 );
				$update[ $field ]  = 1;
				$update['quiz_score'] = $score;
				$update['quiz_total'] = $total;
				if ( $total > 0 && $score === $total ) {
					$bonus_xp = (int) $config['quiz_perfect'];
				}
				break;
			case 'speaking':
				$base_xp   = (int) $config['speaking_attempt'];
				$field     = 'speaking_completed';
				$score     = (int) ( $result['score'] ?? 0 );
				$update[ $field ]  = 1;
				$update['speaking_score'] = $score;
				if ( $score >= 80 ) {
					$bonus_xp = (int) $config['speaking_high'];
				}
				break;
			case 'writing':
				$base_xp   = (int) $config['writing'];
				$field     = 'writing_completed';
				$score     = (int) ( $result['score'] ?? 0 );
				$update[ $field ]  = 1;
				$update['writing_score'] = $score;
				if ( $score >= 80 ) {
					$bonus_xp = (int) $config['writing_high'];
				}
				break;
		}

		$total_xp = $base_xp + $bonus_xp;

		// Award XP (skips if already completed this step — simple idempotency).
		$already_done = $row && ! empty( $row[ $field ] );
		$awarded = 0;
		if ( ! empty( $field ) && ! $already_done && $total_xp > 0 ) {
			$awarded = $xp->award( $user, $total_xp, 'lesson_' . $step, 'lesson', $row['id'] ?? null );
		}

		// Detect full lesson completion.
		$all_done   = ! empty( $update['vocab_completed'] ) && ! empty( $update['listening_completed'] )
			&& ! empty( $update['quiz_completed'] ) && ! empty( $update['speaking_completed'] )
			&& ! empty( $update['writing_completed'] );
		$prev_done  = $row ? (int) $row['completed'] : 0;
		$now_done   = $all_done ? 1 : $prev_done;

		$update['completed']     = $now_done;
		$update['xp_earned']     = ( $row['xp_earned'] ?? 0 ) + $awarded;
		$update['completed_at']  = $now_done ? current_time( 'mysql', true ) : ( $row['completed_at'] ?? null );
		$update['updated_at']    = current_time( 'mysql', true );

		$wpdb->update(
			$table,
			$update,
			array( 'user_id' => $user['id'], 'lesson_date' => $date ),
			$this->format_map( $update ),
			array( '%d', '%s' )
		);

		// Streak updates only on full completion.
		$streak_update = array( 'current' => 0, 'longest' => 0, 'incremented' => false );
		if ( $now_done && ! $prev_done ) {
			$streak_update = ( new BE_Streak( $user ) )->record_completion();
		}

		// Early bird bonus for completing before 9am.
		if ( gmdate( 'G' ) < 9 && ! $already_done ) {
			$xp->award( $user, (int) $config['early_bird'], 'early_bird', 'lesson', $row['id'] ?? null );
		}

		return array(
			'awarded'      => $awarded,
			'completed'    => $now_done,
			'streak'       => $streak_update,
			'progress'     => $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM {$this->table('progress')} WHERE user_id = %d AND lesson_date = %s",
				$user['id'],
				$date
			), ARRAY_A ),
		);
	}

	/**
	 * Build the format map for an update array.
	 *
	 * @param array $update Update fields.
	 * @return array
	 */
	protected function format_map( $update ) {
		$ints = array_flip( array(
			'vocab_completed', 'listening_completed', 'quiz_completed',
			'quiz_score', 'quiz_total', 'speaking_completed', 'speaking_score',
			'writing_completed', 'writing_score', 'completed', 'xp_earned',
		) );

		$format = array();
		foreach ( array_keys( $update ) as $key ) {
			$format[] = isset( $ints[ $key ] ) ? '%d' : '%s';
		}
		return $format;
	}

	/**
	 * Get personalized feedback + tomorrow's preview after a completed lesson.
	 *
	 * @param array $user User row.
	 * @return array
	 */
	public function results( $user ) {
		$date  = gmdate( 'Y-m-d' );

		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$this->table('progress')} WHERE user_id = %d AND lesson_date = %s",
			$user['id'],
			$date
		), ARRAY_A );

		$stats = array(
			'quiz_score'     => $row ? (int) $row['quiz_score'] : 0,
			'quiz_total'     => $row ? (int) $row['quiz_total'] : 0,
			'speaking_score' => $row ? (int) $row['speaking_score'] : 0,
			'writing_score'  => $row ? (int) $row['writing_score'] : 0,
			'streak'         => ( new BE_Streak( $user ) )->current(),
			'xp_today'       => ( new BE_XP_Manager() )->today_xp( $user['id'] ),
		);

		$verse = null;
		if ( $row ) {
			// fetch() serves from the local cache first, so this is fast.
			$verse = ( new BE_Bible() )->fetch( $row['verse_reference'] );
		}

		$feedback = new BE_Feedback_Generator( $this->ai );
		$feedback_text = $feedback->generate( $stats, $user['level'], $verse['text'] ?? '', $user['id'] );

		$tomorrow  = new BE_Preview_Generator( $this->ai );
		$preview   = $tomorrow->generate(
			array(),
			$this->tomorrow_reference( $user ),
			$this->tomorrow_text( $user ),
			$stats['streak']
		);

		return array(
			'stats'    => $stats,
			'feedback' => $feedback_text,
			'preview'  => $preview,
		);
	}

	/**
	 * Next verse reference from the sequencer (cached lightly).
	 *
	 * @param array $user User row.
	 * @return string
	 */
	protected function tomorrow_reference( $user ) {
		return $this->sequencer->verse_for( $user, gmdate( 'Y-m-d', strtotime( '+1 day' ) ) );
	}

	/**
	 * Attempt to fetch tomorrow's verse text for the preview prompt.
	 *
	 * @param array $user User row.
	 * @return string
	 */
	protected function tomorrow_text( $user ) {
		$ref = $this->tomorrow_reference( $user );
		$verse = ( new BE_Bible() )->fetch( $ref );
		return $verse['text'] ?? '';
	}
}