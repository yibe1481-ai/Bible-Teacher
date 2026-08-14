<?php
/**
 * Generates level-aware vocabulary breakdowns for a verse.
 *
 * @package Bible_Teacher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once BIBLE_TEACHER_DIR . 'includes/AI/features/class-ai-feature.php';

/**
 * Class BE_Vocabulary_Generator
 * Feature key: vocabulary_generation
 */
class BE_Vocabulary_Generator extends BE_AI_Feature {

	/**
	 * Constructor.
	 *
	 * @param BE_AI_Manager $ai AI manager.
	 */
	public function __construct( $ai ) {
		$this->feature = 'vocabulary_generation';
		parent::__construct( $ai );
	}

	/**
	 * Count of words requested per level.
	 *
	 * @param string $level Level.
	 * @return int
	 */
	protected function words_per_level( $level ) {
		if ( 'advanced' === $level ) {
			return 5;
		}
		return (int) BE_Options::get( 'learning', 'vocab_words_per_lesson' ) ?: 5;
	}

	/**
	 * Generate vocabulary, preferring cache then AI then static fallback.
	 *
	 * @param string $verse_text Verse text.
	 * @param array  $meta       book/chapter/verse_number.
	 * @param string $level      Level.
	 * @param string $reference  Cache reference.
	 * @return array {vocabulary: [...]}
	 */
	public function generate( $verse_text, $meta, $level, $reference ) {
		if ( ! $this->ai->enabled( $this->feature ) ) {
			return static::fallback( $verse_text, $level );
		}

		$messages = array(
			array(
				'role'    => 'system',
				'content' => sprintf(
					'You are an English language tutor helping %1$s learners understand the Bible in English. Be encouraging, clear, and simple.',
					$level
				),
			),
			array(
				'role'    => 'user',
				'content' => sprintf(
					"From this KJV Bible verse, identify the %d most important words for a %s English learner to understand.\n\nFor each word provide:\n- word: the word exactly as it appears\n- simple_definition: %s\n- example_sentence: a modern example sentence using the word\n- pronunciation_hint: how to say it (e.g. \"sounds like 'ee-ter-nal'\")\n%s\n\nVerse: \"%s\"\nBook: %s Chapter: %d Verse: %d",
					$this->words_per_level( $level ),
					$level,
					$this->definition_instruction( $level ),
					'{"vocabulary": [{"word": "", "simple_definition": "", "example_sentence": "", "pronunciation_hint": ""}]}',
					$verse_text,
					isset( $meta['book'] ) ? $meta['book'] : '',
					isset( $meta['chapter'] ) ? (int) $meta['chapter'] : 0,
					isset( $meta['verse_number'] ) ? (int) $meta['verse_number'] : 0
				),
			),
		);

		$response = $this->ai->chat(
			$this->feature,
			$messages,
			array(
				'level'  => $level,
				'verse'  => $reference,
				'variant'=> 0,
				'cache'  => true,
			)
		);

		$data = $this->extract_json( $response->content );
		if ( ! $data || empty( $data['vocabulary'] ) ) {
			return static::fallback( $verse_text, $level );
		}

		return $data;
	}

	/**
	 * Definition depth instruction per level.
	 *
	 * @param string $level Level.
	 * @return string
	 */
	protected function definition_instruction( $level ) {
		switch ( $level ) {
			case 'advanced':
				return 'one sentence with definition + etymology + grammar note';
			case 'intermediate':
				return 'one sentence with an example in context';
			default:
				return 'one short sentence using the simplest words possible';
		}
	}

	/**
	 * Static fallback used when AI is disabled or fails.
	 *
	 * @param string $verse_text Verse text.
	 * @param string $level      Level.
	 * @return array
	 */
	public static function fallback( $verse_text, $level ) {
		// Extract 5 unique alpha words, longer words to give useful terms.
		$words = preg_split( '/[^a-zA-Z\']+/', $verse_text, -1, PREG_SPLIT_NO_EMPTY );
		$words = array_values( array_unique( array_filter( $words, function ( $w ) {
			return mb_strlen( $w ) >= 4;
		} ) ) );
		$words = array_slice( $words, 0, 5 );

		$vocab = array();
		foreach ( $words as $i => $word ) {
			$vocab[] = array(
				'word'               => mb_strtolower( $word ),
				'simple_definition'  => sprintf( 'An important word in this verse. Check the verse again to understand its meaning.' ),
				'example_sentence'   => sprintf( 'You used the word "%s" while reading today\'s verse.', $word ),
				'pronunciation_hint' => 'Listen to the verse audio to hear this word.',
			);
		}

		// Guarantee at least 1 word.
		if ( empty( $vocab ) ) {
			$vocab[] = array(
				'word'               => 'love',
				'simple_definition'  => 'To care deeply about someone or something.',
				'example_sentence'   => 'We love our family and our friends.',
				'pronunciation_hint' => "sounds like 'luhv'",
			);
		}

		return array( 'vocabulary' => $vocab );
	}
}