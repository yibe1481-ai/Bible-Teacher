<?php
/**
 * Centralized settings reader. Loads defaults and merges saved overrides.
 *
 * All plugin settings live under a single option key (`bible_teacher_options`)
 * as a nested array mirroring the admin Settings sections. Providers are kept
 * in a separate option key because they are an unbounded list.
 *
 * @package Bible_Teacher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BE_Options
 */
class BE_Options {

	/**
	 * Cached merged options.
	 *
	 * @var array|null
	 */
	protected static $cache = null;

	/**
	 * Get the full merged options array.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		require_once BIBLE_TEACHER_DIR . 'config/defaults.php';
		$defaults = bible_teacher_default_options();

		$saved = get_option( 'bible_teacher_options', array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		self::$cache = array_replace_recursive( $defaults, $saved );
		return self::$cache;
	}

	/**
	 * Get a section (or nested value) of the settings.
	 *
	 * @param string|null $section      Top-level section key, or null for all.
	 * @param string|null $key          Optional nested key within the section.
	 * @return mixed
	 */
	public static function get( $section = null, $key = null ) {
		$all = self::all();

		if ( null === $section ) {
			return $all;
		}

		if ( ! isset( $all[ $section ] ) ) {
			return null;
		}

		$val = $all[ $section ];

		if ( null !== $key ) {
			return isset( $val[ $key ] ) ? $val[ $key ] : null;
		}

		return $val;
	}

	/**
	 * Invalidate the cache (e.g. after saving settings).
	 *
	 * @return void
	 */
	public static function reset() {
		self::$cache = null;
	}
}
