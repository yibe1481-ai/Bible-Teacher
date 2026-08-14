<?php
/**
 * Generates a short "tomorrow" teaser after a lesson.
 *
 * @package Bible_Teacher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once BIBLE_TEACHER_DIR . 'includes/AI/features/class-ai-feature.php';

/**
 * Class BE_Preview_Generator
 * Feature key: tomorrow_preview
 */
class BE_Preview_Generator extends BE_AI_Feature {

	/**
	 * Constructor.
	 *
	 * @param BE_AI_Manager $ai AI manager.
	 */
	public function __construct( $ai ) {
		$this->feature = 'tomorrow_preview';
		parent::__construct( $ai );
	}

	/**
	 * Generate a teaser for tomorrow's verse.
	 *
	 * @param array $meta      {book, chapter, verse_number}.
	 * @param string $reference Verse reference (e.g. "John 3:16").
	 * @param string $verse_text Verse text.
	 * @param int   $streak     Current streak.
	 * @return string
	 */
	public function generate( $meta, $reference, $verse_text, $streak = 0 ) {
		if ( ! $this->ai->enabled( $this->feature ) ) {
			return static::fallback( $reference );
		}

		$messages = array(
			array(
				'role'    => 'system',
				'content' => __( 'You write brief, motivating previews for a Bible English learning app. Under 25 words.', 'bible-teacher' ),
			),
			array(
				'role'    => 'user',
				'content' => sprintf(
					"Tomorrow's verse is %s — \"%s\"\nToday's user streak: %d days.\nWrite a teaser that makes them want to come back tomorrow. Reference one interesting word from the verse.",
					$reference,
					$verse_text,
					$streak
				),
			),
		);

		$response = $this->ai->chat(
			$this->feature,
			$messages,
			array(
				'verse'  => $reference,
				'level'  => 'intermediate',
				'variant'=> 0,
				'cache'  => true,
			)
		);

		$text = trim( $response->content );
		return ( '' !== $text ) ? $text : static::fallback( $reference );
	}

	/**
	 * Deterministic fallback preview.
	 *
	 * @param string $reference Verse reference.
	 * @return string
	 */
	public static function fallback( $reference ) {
		return sprintf(
			__( 'See you at the usual time — %s is up next 🔥', 'bible-teacher' ),
			$reference
		);
	}
}