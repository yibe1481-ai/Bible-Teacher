<?php
/**
 * Base repository providing table-name helpers and shared $wpdb access.
 *
 * Every data-access class extends this so that all queries go through
 * $wpdb with prepared statements, never raw concatenation.
 *
 * @package Bible_Teacher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BE_Repository
 */
class BE_Repository {

	/**
	 * Return a fully-qualified table name with prefixes applied.
	 *
	 * @param string $name Table name without prefixes (e.g. "users").
	 * @return string
	 */
	protected function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . BIBLE_TEACHER_DB_PREFIX . $name;
	}

	/**
	 * Current UTC datetime for inserts.
	 *
	 * @return string
	 */
	protected function now() {
		return current_time( 'mysql', true );
	}
}
