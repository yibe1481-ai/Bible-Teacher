<?php
/**
 * Chooses which verse a user sees today based on their level and the
 * configured delivery order (book-sequential by default).
 *
 * @package Bible_Teacher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once BIBLE_TEACHER_DIR . 'includes/Database/class-repository.php';

/**
 * Class BE_Verse_Sequencer
 */
class BE_Verse_Sequencer extends BE_Repository {

	/**
	 * Beginner starter verses (spec §8.2).
	 *
	 * @var array
	 */
	private $beginner_starter = array(
		'John 3:16',
		'John 1:1',
		'John 14:6',
		'Psalm 23:1',
		'John 11:35',
		'Proverbs 3:5-6',
		'Romans 8:28',
	);

	/**
	 * Starting book for each level.
	 *
	 * @return array
	 */
	private function start_books() {
		$bible = BE_Options::get( 'bible' );
		return array(
			'beginner'     => 'john',
			'intermediate' => isset( $bible['intermediate_book'] ) ? $bible['intermediate_book'] : 'psalms',
			'advanced'     => isset( $bible['advanced_book'] ) ? $bible['advanced_book'] : 'romans',
		);
	}

	/**
	 * Get the verse reference for a user on a given date.
	 *
	 * @param array  $user   User row.
	 * @param string $date   Y-m-d date.
	 * @return string Verse reference.
	 */
	public function verse_for( $user, $date ) {
		$level = $user['level'];

		// Beginners use a curated starter pool during their first week.
		if ( 'beginner' === $level ) {
			$done = $this->verses_seen( $user, $date );
			foreach ( $this->beginner_starter as $ref ) {
				if ( ! in_array( $ref, $done, true ) ) {
					return $ref;
				}
			}
		}

		$book   = $this->start_books()[ $level ];
		$order  = BE_Options::get( 'bible', 'delivery_order' );
		$config = isset( $order ) ? $order : 'sequential';

		if ( 'random' === $config ) {
			return $this->random_verse( $book, $level );
		}

		return $this->sequential_verse( $user, $book, $level, $date );
	}

	/**
	 * References already seen by the user recently.
	 *
	 * @param array  $user User row.
	 * @param string $date Today (Y-m-d).
	 * @return array
	 */
	protected function verses_seen( $user, $date ) {
		global $wpdb;
		$table = $this->table( 'progress' );

		$rows = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT verse_reference FROM {$table} WHERE user_id = %d AND lesson_date > DATE_SUB(%s, INTERVAL 14 DAY)",
			$user['id'],
			$date
		) );

		return $rows ? $rows : array();
	}

	/**
	 * Deterministic next verse advancing chapter/verse counters.
	 *
	 * @param array  $user User row.
	 * @param string $book Book name.
	 * @param string $level Level.
	 * @param string $date Date.
	 * @return string
	 */
	protected function sequential_verse( $user, $book, $level, $date ) {
		global $wpdb;
		$table = $this->table( 'progress' );

		// Find the last completed verse for this user in the book.
		$last = $wpdb->get_row( $wpdb->prepare(
			"SELECT chapter, verse_number FROM {$table} WHERE user_id = %d AND book = %s ORDER BY lesson_date DESC LIMIT 1",
			$user['id'],
			ucfirst( $book )
		), ARRAY_A );

		if ( ! $last ) {
			// First verse in the book, chapter 1.
			return $this->format_reference( $book, 1, 1 );
		}

		$chapter = (int) $last['chapter'];
		$verse   = (int) $last['verse_number'] + 1;

		return $this->format_reference( $book, $chapter, $verse );
	}

	/**
	 * Pick a random cached verse at an appropriate difficulty.
	 *
	 * @param string $book  Book.
	 * @param string $level Level.
	 * @return string
	 */
	protected function random_verse( $book, $level ) {
		global $wpdb;
		$table = $this->table( 'verse_cache' );

		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT reference FROM {$table} WHERE book = %s AND (difficulty_tag = %s OR difficulty_override = 1) ORDER BY RAND() LIMIT 1",
			ucfirst( $book ),
			$level
		), ARRAY_A );

		return $row ? $row['reference'] : $this->format_reference( $book, 1, 1 );
	}

	/**
	 * Build a canonical reference string.
	 *
	 * @param string $book        Book.
	 * @param int    $chapter     Chapter.
	 * @param int    $verse       Verse.
	 * @param int|null $end_verse End verse for ranges.
	 * @return string
	 */
	public function format_reference( $book, $chapter, $verse, $end_verse = null ) {
		$book_title = ucfirst( strtolower( $book ) );
		if ( null !== $end_verse && $end_verse > $verse ) {
			return sprintf( '%s %d:%d-%d', $book_title, $chapter, $verse, $end_verse );
		}
		return sprintf( '%s %d:%d', $book_title, $chapter, $verse );
	}
}