<?php
/**
 * Persists AI request results to wp_be_ai_logs for the System > AI Logs page.
 * Logs usage metadata only — never request bodies, API keys, or user audio.
 *
 * @package Bible_Teacher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BE_AI_Logger
 */
class BE_AI_Logger {

	/**
	 * Insert an AI log row.
	 *
	 * @param string $feature Feature key.
	 * @param string $provider Provider id.
	 * @param string $model    Model.
	 * @param int|null $user_id Local user id (optional).
	 * @param string $status   success|failure|fallback.
	 * @param array  $usage    {input_tokens, output_tokens, latency_ms, error_code}.
	 * @return void
	 */
	public static function log( $feature, $provider, $model, $user_id, $status, $usage = array() ) {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . BIBLE_TEACHER_DB_PREFIX . 'ai_logs',
			array(
				'feature'       => $feature,
				'provider'      => $provider,
				'model'         => $model,
				'user_id'       => $user_id,
				'status'        => $status,
				'input_tokens'  => isset( $usage['input_tokens'] ) ? (int) $usage['input_tokens'] : null,
				'output_tokens' => isset( $usage['output_tokens'] ) ? (int) $usage['output_tokens'] : null,
				'latency_ms'    => isset( $usage['latency_ms'] ) ? (int) $usage['latency_ms'] : null,
				'error_code'    => isset( $usage['error_code'] ) ? substr( $usage['error_code'], 0, 100 ) : null,
				'created_at'    => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%d', '%s', '%s' )
		);
	}

	/**
	 * Query logs with filters for the admin page.
	 *
	 * @param array $filters Feature/provider/status/from/to.
	 * @param int   $limit   Page size.
	 * @param int   $page    Page number (1-indexed).
	 * @return array Rows.
	 */
	public static function query( $filters = array(), $limit = 50, $page = 1 ) {
		global $wpdb;

		$table = $wpdb->prefix . BIBLE_TEACHER_DB_PREFIX . 'ai_logs';
		$where = array( '1=1' );
		$args  = array();

		if ( ! empty( $filters['feature'] ) ) {
			$where[] = 'feature = %s';
			$args[]  = $filters['feature'];
		}
		if ( ! empty( $filters['provider'] ) ) {
			$where[] = 'provider = %s';
			$args[]  = $filters['provider'];
		}
		if ( ! empty( $filters['status'] ) ) {
			$where[] = 'status = %s';
			$args[]  = $filters['status'];
		}
		if ( ! empty( $filters['from'] ) ) {
			$where[] = 'created_at >= %s';
			$args[]  = $filters['from'];
		}
		if ( ! empty( $filters['to'] ) ) {
			$where[] = 'created_at <= %s';
			$args[]  = $filters['to'];
		}

		$offset = ( max( 1, $page ) - 1 ) * $limit;
		$sql    = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY id DESC LIMIT %d OFFSET %d';
		$args[] = $limit;
		$args[] = $offset;

		return $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
	}
}