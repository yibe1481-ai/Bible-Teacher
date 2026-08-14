<?php
/**
 * Shared helper for AI feature generators: level-aware prompt templates,
 * JSON extraction (strip markdown fences), and static fallbacks.
 *
 * @package Bible_Teacher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BE_AI_Feature
 */
abstract class BE_AI_Feature {

	/**
	 * Feature key.
	 *
	 * @var string
	 */
	protected $feature = '';

	/**
	 * AI manager instance.
	 *
	 * @var BE_AI_Manager
	 */
	protected $ai;

	/**
	 * Constructor.
	 *
	 * @param BE_AI_Manager $ai AI manager.
	 */
	public function __construct( $ai ) {
		$this->ai = $ai;
	}

	/**
	 * Extract the first top-level JSON object/array from model text, tolerating
	 * ```json fences and leading prose.
	 *
	 * @param string $text Model output.
	 * @return array|null
	 */
	protected function extract_json( $text ) {
		if ( '' === $text ) {
			return null;
		}

		$text = trim( $text );

		// Strip ```json ... ``` fences if present.
		if ( 0 === strpos( $text, '```' ) ) {
			$text = preg_replace( '/^```[a-z]*\s*/i', '', $text );
			$text = preg_replace( '/```\s*$/', '', $text );
		}

		$decoded = json_decode( $text, true );
		if ( is_array( $decoded ) ) {
			return $decoded;
		}

		// Fall back to slicing from the first { or [ to the last ] or }.
		$start = strpos( $text, '{' );
		$alt   = strpos( $text, '[' );
		if ( false !== $alt && ( false === $start || $alt < $start ) ) {
			$start = $alt;
		}
		if ( false !== $start ) {
			$end   = strrpos( $text, '}' );
			$alt_e = strrpos( $text, ']' );
			if ( false !== $alt_e && ( false === $end || $alt_e > $end ) ) {
				$end = $alt_e;
			}
			if ( false !== $end ) {
				$sub = trim( substr( $text, $start, $end - $start + 1 ) );
				if ( $sub ) {
					$decoded = json_decode( $sub, true );
					if ( is_array( $decoded ) ) {
						return $decoded;
					}
				}
			}
		}

		return null;
	}

	/**
	 * Build the "return only JSON" suffix used in every generation prompt.
	 *
	 * @return string
	 */
	protected function json_instruction() {
		return "\n\nReturn ONLY valid JSON. No markdown, no code fences, no explanation.";
	}
}