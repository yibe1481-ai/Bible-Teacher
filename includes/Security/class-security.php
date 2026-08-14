<?php
/**
 * Security helpers: in-memory rate limiting, audit logging, and simple
 * abuse banning. Uses a transient-backed sliding bucket per key.
 *
 * @package Bible_Teacher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BE_Security
 */
class BE_Security {

	/**
	 * Get settings.
	 *
	 * @return array
	 */
	private static function settings() {
		return BE_Options::get( 'security' );
	}

	/**
	 * Per-minute rate limit. Returns true when the action is allowed.
	 *
	 * @param string $key    Unique rate-limit key (e.g. IP + endpoint).
	 * @param int    $limit  Max requests per minute (0 = use configured).
	 * @return bool
	 */
	public static function rate_limit_ok( $key, $limit = 0 ) {
		if ( $limit <= 0 ) {
			$config = self::settings();
			$limit  = (int) ( $config['rate_limit_per_min'] ?? 60 );
		}

		$transient = 'be_rl_' . md5( $key );
		$window    = get_transient( $transient );

		if ( false === $window ) {
			set_transient( $transient, 1, MINUTE_IN_SECONDS );
			return true;
		}

		if ( (int) $window >= $limit ) {
			self::record_abuse( $key );
			return false;
		}

		set_transient( $transient, (int) $window + 1, MINUTE_IN_SECONDS );
		return true;
	}

	/**
	 * Record a potential abuse event for a key.
	 *
	 * @param string $key Abuse key.
	 * @return void
	 */
	private static function record_abuse( $key ) {
		$config = self::settings();
		if ( empty( $config['ban_on_abuse'] ) ) {
			return;
		}

		$transient = 'be_abuse_' . md5( $key );
		$count     = (int) get_transient( $transient );
		$count++;
		set_transient( $transient, $count, HOUR_IN_SECONDS );

		$threshold = (int) ( $config['abuse_threshold'] ?? 10 );
		if ( $count >= $threshold ) {
			set_transient( 'be_banned_' . md5( $key ), 1, HOUR_IN_SECONDS );
			self::audit( 'auth', 'abuse_ban', array( 'key' => $key, 'count' => $count ) );
		}
	}

	/**
	 * Whether a key is currently banned.
	 *
	 * @param string $key Key to check.
	 * @return bool
	 */
	public static function is_banned( $key ) {
		return (bool) get_transient( 'be_banned_' . md5( $key ) );
	}

	/**
	 * Client IP (respects reverse proxies conservatively).
	 *
	 * @return string
	 */
	public static function client_ip() {
		$ip = self::server( 'REMOTE_ADDR' );
		return $ip ? $ip : 'unknown';
	}

	/**
	 * Read a $_SERVER value safely.
	 *
	 * @param string $key Server key.
	 * @return string
	 */
	private static function server( $key ) {
		return isset( $_SERVER[ $key ] ) ? sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ) : '';
	}

	/**
	 * Write an audit-log entry if auditing is enabled.
	 *
	 * @param string $category Category.
	 * @param string $action   Action key.
	 * @param array  $data     Context data (never contains secrets).
	 * @return void
	 */
	public static function audit( $category, $action, $data = array() ) {
		$config = self::settings();
		if ( empty( $config['audit_logging'] ) ) {
			return;
		}

		error_log( sprintf(
			'[BibleTeacher] %s:%s %s',
			$category,
			$action,
			wp_json_encode( (array) $data )
		) );
	}
}
