<?php
/**
 * Generates a short personalized tutor message after a lesson.
 *
 * @package Bible_Teacher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once BIBLE_TEACHER_DIR . 'includes/AI/features/class-ai-feature.php';

/**
 * Class BE_Feedback_Generator
 * Feature key: personalized_feedback
 */
class BE_Feedback_Generator extends BE_AI_Feature {

	/**
	 * Constructor.
	 *
	 * @param BE_AI_Manager $ai AI manager.
	 */
	public function __construct( $ai ) {
		$this->feature = 'personalized_feedback';
		parent::__construct( $ai );
	}

	/**
	 * Generate feedback from lesson stats.
	 *
	 * @param array  $stats  quiz_score, quiz_total, speaking_score, writing_score, streak, xp_today.
	 * @param string $level  Level.
	 * @param string $verse  Verse text for reference.
	 * @param int|null $user_id User id.
	 * @return string
	 */
	public function generate( $stats, $level, $verse, $user_id = null ) {
		if ( ! $this->ai->enabled( $this->feature ) ) {
			return static::fallback( $stats );
		}

		$messages = array(
			array(
				'role'    => 'system',
				'content' => __( 'You are an encouraging English tutor and Bible study guide. Keep responses under 40 words. Be warm, specific, and motivating.', 'bible-teacher' ),
			),
			array(
				'role'    => 'user',
				'content' => sprintf(
					"Student level: %s\nToday's verse: \"%s\"\nQuiz score: %d/%d\nSpeaking score: %s\nWriting score: %s\nCurrent streak: %d days\nXP earned today: %d\n\nGive one encouraging sentence about their performance and one specific tip for tomorrow. Reference the verse content naturally.",
					$level,
					$verse,
					isset( $stats['quiz_score'] ) ? (int) $stats['quiz_score'] : 0,
					isset( $stats['quiz_total'] ) ? (int) $stats['quiz_total'] : 1,
					isset( $stats['speaking_score'] ) ? (int) $stats['speaking_score'] . '%' : 'not attempted',
					isset( $stats['writing_score'] ) ? (int) $stats['writing_score'] . '%' : 'not attempted',
					isset( $stats['streak'] ) ? (int) $stats['streak'] : 0,
					isset( $stats['xp_today'] ) ? (int) $stats['xp_today'] : 0
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

		$text = trim( $response->content );
		return ( '' !== $text ) ? $text : static::fallback( $stats );
	}

	/**
	 * Deterministic fallback feedback.
	 *
	 * @param array $stats Lesson stats.
	 * @return string
	 */
	public static function fallback( $stats ) {
		$quiz_ratio = isset( $stats['quiz_total'] ) && $stats['quiz_total'] > 0
			? ( (int) $stats['quiz_score'] / (int) $stats['quiz_total'] )
			: 0;

		if ( $quiz_ratio >= 0.9 ) {
			return __( 'Outstanding work today! You really know this verse well. Tomorrow, try reading it aloud at a natural speed to polish your rhythm.', 'bible-teacher' );
		}
		if ( $quiz_ratio >= 0.5 ) {
			return __( 'Nice progress! You understood the main idea of today\'s verse. Focus on the words you missed during the quiz tomorrow.', 'bible-teacher' );
		}
		return __( 'Good job showing up today! Even a small step builds your streak. Re-read the verse once more before tomorrow\'s lesson.', 'bible-teacher' );
	}
}