<?php
/**
 * Bible verse provider backed by bible-api.com with a local database cache.
 *
 * Verse responses are cached in `wp_be_verse_cache` so live API calls never
 * happen during a user lesson — they are pre-fetched by the daily cron job.
 *
 * @package Bible_Teacher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once BIBLE_TEACHER_DIR . 'includes/Database/class-repository.php';

/**
 * Class BE_Bible
 */
class BE_Bible extends BE_Repository {

	/**
	 * Fetch a verse by reference, serving from cache first.
	 *
	 * @param string $reference Verse reference (e.g. "John 3:16").
	 * @param bool   $force     Bypass cache and hit the API.
	 * @return array|null Verse array or null.
	 */
	public function fetch( $reference, $force = false ) {
		$reference = trim( $reference );

		if ( ! $force ) {
			$cached = $this->get_cached( $reference );
			if ( $cached ) {
				return $cached;
			}
		}

		$compiled = $this->compile( $this->request( $reference ) );

		if ( $compiled ) {
			$this->store( $compiled );
		}

		return $compiled;
	}

	/**
	 * Fetch a range like "John 3:16-18".
	 *
	 * @param string $reference Range reference.
	 * @return array|null
	 */
	public function fetch_range( $reference ) {
		return $this->fetch( $reference );
	}

	/**
	 * Ensure a reference is cached (called by cron for the next day's verse).
	 *
	 * @param string $reference Reference.
	 * @return bool Whether it succeeded.
	 */
	public function pre_cache( $reference ) {
		$verse = $this->fetch( $reference, true );
		return (bool) $verse;
	}

	/**
	 * Read from the local cache.
	 *
	 * @param string $reference Verse reference.
	 * @return array|null
	 */
	protected function get_cached( $reference ) {
		global $wpdb;
		$table = $this->table( 'verse_cache' );

		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE reference = %s LIMIT 1",
			$reference
		), ARRAY_A );

		if ( ! $row ) {
			return null;
		}

		return $this->row_to_verse( $row );
	}

	/**
	 * Normalize a cached DB row into a verse array.
	 *
	 * @param array $row DB row.
	 * @return array
	 */
	protected function row_to_verse( $row ) {
		return array(
			'id'           => (int) $row['id'],
			'reference'    => $row['reference'],
			'book'         => $row['book'],
			'chapter'      => (int) $row['chapter'],
			'verse_number' => (int) $row['verse_number'],
			'text'         => $row['text'],
			'word_count'   => (int) $row['word_count'],
			'difficulty'   => $row['difficulty_tag'],
			'override'     => (bool) $row['difficulty_override'],
			'cached_at'    => $row['cached_at'],
		);
	}

	/**
	 * Persist a compiled verse into the cache table.
	 *
	 * @param array $verse Verse array.
	 * @return void
	 */
	protected function store( $verse ) {
		global $wpdb;
		$table = $this->table( 'verse_cache' );

		$data = array(
			'reference'         => $verse['reference'],
			'book'              => $verse['book'],
			'chapter'           => (int) $verse['chapter'],
			'verse_number'      => (int) $verse['verse_number'],
			'text'              => $verse['text'],
			'word_count'        => (int) $verse['word_count'],
			'difficulty_tag'    => $verse['difficulty'],
			'difficulty_override'=> $verse['override'] ? 1 : 0,
			'cached_at'         => current_time( 'mysql', true ),
		);

		$args = array( '%s', '%s', '%d', '%d', '%s', '%d', '%s', '%d', '%s' );

		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table} WHERE reference = %s",
			$verse['reference']
		) );

		if ( $existing ) {
			$wpdb->update( $table, $data, array( 'id' => $existing ), $args, array( '%d' ) );
		} else {
			$wpdb->insert( $table, $data, $args );
		}
	}

	/**
	 * Compile a bible-api.com response into our normalized verse shape.
	 *
	 * @param array|false $response Raw API response.
	 * @return array|null
	 */
	protected function compile( $response ) {
		if ( ! is_array( $response ) || empty( $response['text'] ) ) {
			return null;
		}

		if ( isset( $response['verses'] ) && is_array( $response['verses'] ) ) {
			$ref      = isset( $response['reference'] ) ? $response['reference'] : '';
			$book_raw = isset( $response['verses'][0]['book_name'] ) ? $response['verses'][0]['book_name'] : '';
			$chapter  = isset( $response['verses'][0]['chapter'] ) ? (int) $response['verses'][0]['chapter'] : 0;
			$verse_no = isset( $response['verses'][0]['verse'] ) ? (int) $response['verses'][0]['verse'] : 0;
			$text     = $response['text'];
		} else {
			$ref      = isset( $response['reference'] ) ? $response['reference'] : '';
			$book_raw = isset( $response['book_name'] ) ? $response['book_name'] : '';
			$chapter  = isset( $response['chapter'] ) ? (int) $response['chapter'] : 0;
			$verse_no = isset( $response['verse'] ) ? (int) $response['verse'] : 0;
			$text     = $response['text'];
		}

		return array(
			'reference'    => $ref,
			'book'         => $book_raw,
			'chapter'      => $chapter,
			'verse_number' => $verse_no,
			'text'         => wp_strip_all_tags( $text ),
			'word_count'   => str_word_count( wp_strip_all_tags( $text ) ),
			'difficulty'   => $this->classify( wp_strip_all_tags( $text ) ),
			'override'     => false,
		);
	}

	/**
	 * Classify a verse's difficulty by word count (spec §8.3).
	 *
	 * @param string $text Verse text.
	 * @return string beginner|intermediate|advanced
	 */
	public function classify( $text ) {
		$bible = BE_Options::get( 'bible' );
		$beginner_max = (int) ( $bible['beginner_max_words'] ?? 15 );
		$advanced_min = (int) ( $bible['advanced_min_words'] ?? 20 );

		$count = str_word_count( $text );

		if ( $count <= $beginner_max ) {
			return 'beginner';
		}
		if ( $count >= $advanced_min || strpos( $text, ';' ) !== false ) {
			return 'advanced';
		}
		return 'intermediate';
	}

	/**
	 * Perform the HTTP request to bible-api.com.
	 *
	 * @param string $reference Verse reference.
	 * @return array|false
	 */
	protected function request( $reference ) {
		$bible = BE_Options::get( 'bible' );
		$base  = isset( $bible['api_base'] ) ? untrailingslashit( $bible['api_base'] ) : 'https://bible-api.com';
		$translation = isset( $bible['translation'] ) ? $bible['translation'] : 'kjv';

		$glue  = ( 'kjv' === strtolower( $translation ) ) ? '' : '&translation=' . rawurlencode( $translation );
		$url   = $base . '/' . rawurlencode( $reference ) . '?translation=kjv' . $glue;

		$response = wp_remote_get( $url, array( 'timeout' => 20 ) );
		if ( is_wp_error( $response ) ) {
			return false;
		}

		return json_decode( wp_remote_retrieve_body( $response ), true );
	}
}