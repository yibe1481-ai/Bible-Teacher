<?php
/**
 * Evaluates an Advanced-level paraphrase of a verse.
 *
 * @package Bible_Teacher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once BIBLE_TEACHER_DIR . 'includes/AI/features/class-ai-feature.php';

/**
 * Class BE_Writing_Scorer
 * Feature key: writing_score
 */
class BE_Writing_Scorer extends BE_AI_Feature {

	/**
	 * Constructor.
	 *
	 * @param BE_AI_Manager $ai AI manager.
	 */
	public function __construct( $ai ) {
		$this->feature = 'writing_score';
		parent::__construct( $ai );
	}

	/**
	 * Score a learner's paraphrase.
	 *
	 * @param string $verse_text  Original verse.
	 * @param string $user_text   Learner's paraphrase.
	 * @param int|null $user_id   User id.
	 * @return array {score, meaning_score, grammar_score, originality_score, feedback, corrections}
	 */
	public function score( $verse_text, $user_text, $user_id = null ) {
		$user_text = trim( (string) $user_text );
		if ( '' === $user_text ) {
			return static::fallback( 0, $verse_text, $user_text );
		}

		if ( ! $this->ai->enabled( $this->feature ) ) {
			return static::fallback( $this->overlap_score( $verse_text, $user_text ), $verse_text, $user_text );
		}

		$messages = array(
			array(
				'role'    => 'system',
				'content' => __( 'You are evaluating an English paraphrase written by an Advanced Bible English learner.', 'bible-teacher' ),
			),
			array(
				'role'    => 'user',
				'content' => sprintf(
					"Original KJV verse: \"%s\"\n\nLearner's paraphrase: \"%s\"\n\nEvaluate:\n- Does it capture the core meaning? (0-40 points)\n- Is the English grammatically correct? (0-30 points)\n- Is it in their own words (not just copied)? (0-30 points)\n%s\n%s",
					$verse_text,
					$user_text,
					$this->json_instruction(),
					'{"score": 85, "meaning_score": 35, "grammar_score": 28, "originality_score": 22, "feedback": "", "corrections": []}'
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
			return static::fallback( $this->overlap_score( $verse_text, $user_text ), $verse_text, $user_text );
		}

		$data['score']            = max( 0, min( 100, (int) $data['score'] ) );
		$data['meaning_score']    = isset( $data['meaning_score'] ) ? (int) $data['meaning_score'] : 0;
		$data['grammar_score']    = isset( $data['grammar_score'] ) ? (int) $data['grammar_score'] : 0;
		$data['originality_score']= isset( $data['originality_score'] ) ? (int) $data['originality_score'] : 0;
		if ( ! isset( $data['corrections'] ) || ! is_array( $data['corrections'] ) ) {
			$data['corrections'] = array();
		}
		return $data;
	}

	/**
	 * Rough static score based on length coverage of the verse.
	 *
	 * @param string $verse_text Verse.
	 * @param string $user_text  Paraphrase.
	 * @return int 0-100
	 */
	protected function overlap_score( $verse_text, $user_text ) {
		$target_words = preg_split( '/\s+/', trim( $verse_text ) );
		$count        = count( $target_words );
		if ( 0 === $count ) {
			return 0;
		}
		// A modest length relative to the original signals effort; cap at 100.
		$user_words = preg_split( '/\s+/', trim( $user_text ) );
		$ratio      = count( $user_words ) / $count;
		return (int) round( min( 100, $ratio * 80 + 10 ) );
	}

	/**
	 * Deterministic fallback result.
	 *
	 * @param int    $base_score Base numeric score.
	 * @param string $verse_text Verse.
	 * @param string $user_text  Paraphrase.
	 * @return array
	 */
	public static function fallback( $base_score, $verse_text, $user_text ) {
		return array(
			'score'             => $base_score,
			'meaning_score'     => (int) round( $base_score * 0.4 ),
			'grammar_score'     => (int) round( $base_score * 0.3 ),
			'originality_score' => (int) round( $base_score * 0.3 ),
			'feedback'          => ( $base_score >= 80 )
				? __( 'Excellent paraphrase — you captured the meaning well!', 'bible-teacher' )
				: __( 'Good effort! Try expressing the main idea more in your own words next time.', 'bible-teacher' ),
			'corrections'       => array(),
		);
	}
}