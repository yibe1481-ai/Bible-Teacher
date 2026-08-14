<?php
/**
 * User data service: create/find users by Telegram id, update settings,
 * and fetch aggregate stats.
 *
 * @package Bible_Teacher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once BIBLE_TEACHER_DIR . 'includes/Database/class-repository.php';

/**
 * Class BE_User_Service
 */
class BE_User_Service extends BE_Repository {

	/**
	 * Find a user by Telegram user id.
	 *
	 * @param int $telegram_id Telegram id.
	 * @return array|null
	 */
	public function find_by_telegram_id( $telegram_id ) {
		global $wpdb;
		$table = $this->table( 'users' );

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE telegram_user_id = %d",
			$telegram_id
		), ARRAY_A );
	}

	/**
	 * Get a user by local id.
	 *
	 * @param int $user_id Local user id.
	 * @return array|null
	 */
	public function get( $user_id ) {
		global $wpdb;
		$table = $this->table( 'users' );

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE id = %d",
			$user_id
		), ARRAY_A );
	}

	/**
	 * Create a user record.
	 *
	 * @param array $data User fields.
	 * @return array|null Inserted row or null.
	 */
	public function create( $data ) {
		global $wpdb;
		$table = $this->table( 'users' );

		$now = current_time( 'mysql', true );

		$wpdb->insert(
			$table,
			array(
				'telegram_user_id'  => (int) $data['telegram_user_id'],
				'telegram_username' => isset( $data['telegram_username'] ) ? sanitize_text_field( $data['telegram_username'] ) : '',
				'first_name'        => sanitize_text_field( $data['first_name'] ),
				'last_name'         => isset( $data['last_name'] ) ? sanitize_text_field( $data['last_name'] ) : '',
				'language_code'     => isset( $data['language_code'] ) ? sanitize_text_field( $data['language_code'] ) : 'en',
				'timezone'          => isset( $data['timezone'] ) && $data['timezone'] ? sanitize_text_field( $data['timezone'] ) : 'UTC',
				'level'             => 'beginner',
				'placement_completed'=> 0,
				'notification_time' => BE_Options::get( 'notifications', 'default_time' ) ?: '07:00:00',
				'notifications_enabled' => 1,
				'status'            => 'active',
				'created_at'        => $now,
				'updated_at'        => $now,
				'last_active_at'    => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%s' )
		);

		return $this->get( (int) $wpdb->insert_id );
	}

	/**
	 * Touch a user's last_active_at timestamp.
	 *
	 * @param int $user_id User id.
	 * @return void
	 */
	public function touch( $user_id ) {
		global $wpdb;
		$wpdb->update(
			$this->table( 'users' ),
			array(
				'updated_at'     => current_time( 'mysql', true ),
				'last_active_at' => current_time( 'mysql', true ),
			),
			array( 'id' => $user_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Update user settings from a whitelist.
	 *
	 * @param int   $user_id User id.
	 * @param array $data    Whitelisted fields.
	 * @return bool
	 */
	public function update_settings( $user_id, $data ) {
		global $wpdb;
		$allowed = array(
			'timezone',
			'notification_time',
			'language_code',
			'notifications_enabled',
			'first_name',
			'telegram_username',
			'level',
		);

		$update = array( 'updated_at' => current_time( 'mysql', true ) );
		$format = array( '%s' );

		foreach ( $allowed as $key ) {
			if ( isset( $data[ $key ] ) ) {
				$update[ $key ] = sanitize_text_field( $data[ $key ] );
				$format[]       = '%s';
			}
		}

		return (bool) $wpdb->update(
			$this->table( 'users' ),
			$update,
			array( 'id' => $user_id ),
			$format,
			array( '%d' )
		);
	}

	/**
	 * Set a user's level (guarded — never trusts a client value directly).
	 *
	 * @param int    $user_id User id.
	 * @param string $level   Valid level.
	 * @return void
	 */
	public function set_level( $user_id, $level ) {
		$valid = array( 'beginner', 'intermediate', 'advanced' );
		if ( ! in_array( $level, $valid, true ) ) {
			return;
		}
		global $wpdb;
		$wpdb->update(
			$this->table( 'users' ),
			array( 'level' => $level, 'updated_at' => current_time( 'mysql', true ) ),
			array( 'id' => $user_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Aggregate stats for a user's profile.
	 *
	 * @param int $user_id User id.
	 * @return array
	 */
	public function stats( $user_id ) {
		$xp      = new BE_XP_Manager();
		$streak  = new BE_Streak( array( 'id' => $user_id ) );
		$badges  = new BE_Badge_Manager();

		return array(
			'weekly_xp'      => (int) $xp->totals( $user_id )['weekly_xp'],
			'lifetime_xp'    => (int) $xp->totals( $user_id )['lifetime_xp'],
			'current_streak' => $streak->current(),
			'longest_streak' => $streak->longest(),
			'badges'         => $badges->get_user_badges( $user_id ),
		);
	}

	/**
	 * Whether a user is banned.
	 *
	 * @param array $user User row.
	 * @return bool
	 */
	public function is_banned( $user ) {
		return isset( $user['status'] ) && 'banned' === $user['status'];
	}
}