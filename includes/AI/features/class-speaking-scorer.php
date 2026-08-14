<?php
/**
 * Scores a learner's spoken verse: transcribes audio, then compares the
 * transcription to the target verse via AI.
 *
 * @package Bible_Teacher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once BIBLE_TEACHER_DIR . 'includes/AI/features/class-ai-feature.php';

/**
 * Class BE_Speaking_Scorer
 * Feature key: speaking_score
 */
class BE_Speaking_Scorer extends BE_AI_Feature {

	/**
	 * Constructor.
	 *
	 * @param BE_AI_Manager $ai AI manager.
	 */
	public function __construct( $ai ) {
		$this->feature = 'speaking_score';
		parent::__construct( $ai );
	}

	/**
	 * Max recording length from settings (seconds).
	 *
	 * @return int
	 */
	public function max_recording_seconds() {
		return (int) BE_Options::get( 'learning', 'max_recording_seconds' ) ?: 30;
	}

	/**
	 * Passing threshold for a level.
	 *
	 * @param string $level Level.
	 * @return int
	 */
	protected function pass_threshold( $level ) {
		$learning = BE_Options::get( 'learning' );
		$levels   = $learning['levels'] ?? array();
		if ( isset( $levels[ $level ]['speak_pass'] ) ) {
			return (int) $levels[ $level ]['speak_pass'];
		}
		return 70;
	}

	/**
	 * Transcribe an audio file via the configured provider.
	 *
	 * @param string $audio_path Local path.
	 * @param int|null $user_id  User id.
	 * @return BE_TranscriptionResult
	 */
	public function transcribe( $audio_path, $user_id = null ) {
		return $this->ai->transcribe( $audio_path, $user_id );
	}

	/**
	 * Score a transcription against the verse.
	 *
	 * @param string $verse_text    Target verse.
	 * @param string $transcription Recognized text.
	 * @param string $level         Level.
	 * @param int|null $user_id     User id.
	 * @return array {score, correct_words, missed_words, tip, encouragement}
	 */
	public function score( $verse_text, $transcription, $level, $user_id = null ) {
		// No transcript — award a minimal, honest score.
		if ( trim( (string) $transcription ) === '' ) {
			return array(
				'score'          => 0,
				'correct_words'  => array(),
				'missed_words'   => array(),
				'tip'            => __( 'The audio could not be understood. Try recording in a quiet place.', 'bible-teacher' ),
				'encouragement'  => __( 'Thanks for trying! Practice makes progress.', 'bible-teacher' ),
			);
		}

		if ( ! $this->ai->enabled( $this->feature ) ) {
			return static::fallback( $verse_text, $transcription );
		}

		$messages = array(
			array(
				'role'    => 'system',
				'content' => sprintf(
					'You are scoring English pronunciation for a %s learner. Be encouraging and specific.',
					$level
				),
			),
			array(
				'role'    => 'user',
				'content' => sprintf(
					"The learner was asked to read this verse aloud:\n\"%s\"\n\nTheir speech was transcribed as:\n\"%s\"\n\nCompare the transcription to the original. Identify:\n1. Words they got right\n2. Words they missed or mispronounced\n3. Overall accuracy percentage (0-100)\n\nBe encouraging. If the score is low, note one specific word to practice.\n%s\n%s",
					$verse_text,
					$transcription,
					$this->json_instruction(),
					'{"score": 75, "correct_words": [], "missed_words": [], "tip": "", "encouragement": ""}'
				),
			),
		);

		$response = $this->ai->chat(
			$this->feature,
			$messages,
			array(
				'user_id' => $user_id,
				'cache'   => false,
			)
		);

		$data = $this->extract_json( $response->content );
		if ( ! $data || ! isset( $data['score'] ) ) {
			return static::fallback( $verse_text, $transcription );
		}

		$data['score'] = max( 0, min( 100, (int) $data['score'] ) );
		return $data;
	}

	/**
	 * Deterministic fallback scoring via word overlap.
	 *
	 * @param string $verse_text    Target verse.
	 * @param string $transcription Transcript.
	 * @return array
	 */
	public static function fallback( $verse_text, $transcription ) {
		$target = array_map( 'strtolower', preg_split( '/[^a-zA-Z\']+/', $verse_text, -1, PREG_SPLIT_NO_EMPTY ) );
		$spoken = array_map( 'strtolower', preg_split( '/[^a-zA-Z\']+/', $transcription, -1, PREG_SPLIT_NO_EMPTY ) );

		$target = array_values( array_unique( $target ) );

		$correct = array();
		$missed  = array();
		foreach ( $target as $word ) {
			if ( in_array( $word, $spoken, true ) ) {
				$correct[] = $word;
			} else {
				$missed[] = $word;
			}
		}

		$score = empty( $target ) ? 0 : (int) round( ( count( $correct ) / count( $target ) ) * 100 );

		return array(
			'score'         => $score,
			'correct_words' => array_slice( $correct, 0, 10 ),
			'missed_words'  => array_slice( $missed, 0, 10 ),
			'tip'           => $missed
				? sprintf( __( 'Practice saying "%s".', 'bible-teacher' ), reset( $missed ) )
				: __( 'Great pacing — read with natural rhythm next time.', 'bible-teacher' ),
			'encouragement' => __( 'Every attempt makes your English stronger!', 'bible-teacher' ),
		);
	}

	/**
	 * Whether a score passes the threshold for a level.
	 *
	 * @param int    $score Score.
	 * @param string $level Level.
	 * @return bool
	 */
	public function passes( $score, $level ) {
		return $score >= $this->pass_threshold( $level );
	}
}