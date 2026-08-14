<?php
/**
 * Generates comprehension quizzes for a verse at a given level.
 *
 * @package Bible_Teacher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once BIBLE_TEACHER_DIR . 'includes/AI/features/class-ai-feature.php';

/**
 * Class BE_Quiz_Generator
 * Feature key: quiz_generation
 */
class BE_Quiz_Generator extends BE_AI_Feature {

	/**
	 * Constructor.
	 *
	 * @param BE_AI_Manager $ai AI manager.
	 */
	public function __construct( $ai ) {
		$this->feature = 'quiz_generation';
		parent::__construct( $ai );
	}

	/**
	 * Number of quiz questions per level.
	 *
	 * @param string $level Level.
	 * @return int
	 */
	protected function question_count( $level ) {
		return ( 'advanced' === $level ) ? 4 : 3;
	}

	/**
	 * Number of answer options per level.
	 *
	 * @param string $level Level.
	 * @return int
	 */
	protected function option_count( $level ) {
		$learning = BE_Options::get( 'learning' );
		$levels   = isset( $learning['levels'] ) ? $learning['levels'] : array();
		if ( isset( $levels[ $level ]['quiz_options'] ) ) {
			return (int) $levels[ $level ]['quiz_options'];
		}
		return ( 'intermediate' === $level || 'advanced' === $level ) ? 4 : 3;
	}

	/**
	 * Generate quiz, preferring cache variants then AI then static.
	 *
	 * @param string $verse_text Verse text.
	 * @param string $level      Level.
	 * @param string $reference  Verse reference.
	 * @param int    $variant    Quiz variant index.
	 * @return array {questions: [...]}
	 */
	public function generate( $verse_text, $level, $reference, $variant = 0 ) {
		if ( ! $this->ai->enabled( $this->feature ) ) {
			return static::fallback( $verse_text, $level );
		}

		$options  = $this->option_count( $level );
		$count    = $this->question_count( $level );
		$advanced = ( 'advanced' === $level );

		$messages = array(
			array(
				'role'    => 'system',
				'content' => sprintf(
					'You are creating English comprehension exercises for %s Bible learners.',
					$level
				),
			),
			array(
				'role'    => 'user',
				'content' => sprintf(
					"Create %d fill-in-the-blank or comprehension questions from this verse for a %s learner.\n\nRules:\n- %s\n- Each question must have exactly one correct answer and %d plausible wrong answers.\n- Vary difficulty: include at least one easy and one medium question.\n%s\n\nVerse: \"%s\"",
					$count,
					$level,
					$this->level_rules( $level, $options, $advanced ),
					$options - 1,
					wp_json_encode( array(
						'questions' => array(
							array(
								'question'      => '',
								'options'       => array_fill( 0, $options, '' ),
								'correct_index' => 0,
								'explanation'   => '',
							),
						),
					) ),
					$verse_text
				),
			),
		);

		$response = $this->ai->chat(
			$this->feature,
			$messages,
			array(
				'level'   => $level,
				'verse'   => $reference,
				'variant' => $variant,
				'cache'   => true,
			)
		);

		$data = $this->extract_json( $response->content );
		if ( ! $data || empty( $data['questions'] ) ) {
			return static::fallback( $verse_text, $level );
		}

		return $data;
	}

	/**
	 * Level-specific quiz rules.
	 *
	 * @param string $level    Level.
	 * @param int    $options  Option count.
	 * @param bool   $advanced Advanced flag.
	 * @return string
	 */
	protected function level_rules( $level, $options, $advanced ) {
		if ( 'beginner' === $level ) {
			return 'BEGINNER: fill-in-the-blank only, one-word answers, ' . $options . ' options each.';
		}
		if ( $advanced ) {
			return 'ADVANCED: include one grammar analysis question and one cross-reference question, ' . $options . ' close options each.';
		}
		return 'INTERMEDIATE: mix of fill-in-the-blank and meaning questions, ' . $options . ' options each.';
	}

	/**
	 * Static canned quiz fallback.
	 *
	 * @param string $verse_text Verse text.
	 * @param string $level      Level.
	 * @return array
	 */
	public static function fallback( $verse_text, $level ) {
		$options_count = ( 'beginner' === $level ) ? 3 : 4;

		$template = array(
			'question'      => __( 'Which book is this verse from?', 'bible-teacher' ),
			'options'       => array( 'John', 'Psalms', 'Matthew' ),
			'correct_index' => 0,
			'explanation'   => __( 'Check the verse reference for today.', 'bible-teacher' ),
		);

		while ( count( $template['options'] ) < $options_count ) {
			$template['options'][] = 'Exodus';
		}

		return array(
			'questions' => array(
				$template,
				array(
					'question'      => __( 'Fill in the blank: "For God so loved the world, that he ___ his only begotten Son."', 'bible-teacher' ),
					'options'       => array( 'sent', 'smiled', 'dreamed' ),
					'correct_index' => 0,
					'explanation'   => __( 'John 3:16 uses the word "gave".', 'bible-teacher' ),
				),
				array(
					'question'      => __( 'Who loves the world in this verse?', 'bible-teacher' ),
					'options'       => array( 'God', 'The king', 'The people' ),
					'correct_index' => 0,
					'explanation'   => __( '"God so loved the world".', 'bible-teacher' ),
				),
			),
		);
	}
}