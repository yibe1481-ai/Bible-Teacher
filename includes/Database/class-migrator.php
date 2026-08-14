<?php
/**
 * Database schema installer and version tracker.
 *
 * All custom tables are created here via dbDelta, keyed by the WordPress
 * table prefix. Schema version is tracked in the `bible_teacher_db_version`
 * option so future upgrades can be applied incrementally.
 *
 * NOTE: table names carry the WP prefix plus the `be_` plugin prefix, e.g.
 * `wp_be_users`. The uninstaller drops the same set.
 *
 * @package Bible_Teacher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BE_Migrator
 */
class BE_Migrator {

	/**
	 * Number of tables managed by the plugin.
	 */
	const TABLE_COUNT = 14;

	/**
	 * Run migrations if the stored version is out of date.
	 *
	 * @return bool
	 */
	public static function maybe_upgrade() {
		$current = get_option( 'bible_teacher_db_version', '0.0.0' );

		if ( version_compare( $current, BIBLE_TEACHER_TABLE_VERSION, '>=' ) ) {
			return false;
		}

		self::install();

		update_option( 'bible_teacher_db_version', BIBLE_TEACHER_TABLE_VERSION );
		return true;
	}

	/**
	 * Create (or update) all plugin tables.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$p               = $wpdb->prefix . 'be_';

		$tables = array(
			// Users.
			"{$p}users" => "CREATE TABLE {$p}users (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				telegram_user_id BIGINT UNSIGNED NOT NULL,
				telegram_username VARCHAR(255) NULL,
				first_name VARCHAR(255) NOT NULL,
				last_name VARCHAR(255) NULL,
				language_code VARCHAR(10) DEFAULT 'en',
				timezone VARCHAR(60) DEFAULT 'UTC',
				level ENUM('beginner','intermediate','advanced') DEFAULT 'beginner',
				placement_completed TINYINT(1) DEFAULT 0,
				notification_time TIME DEFAULT '07:00:00',
				notifications_enabled TINYINT(1) DEFAULT 1,
				status ENUM('active','inactive','banned') DEFAULT 'active',
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				last_active_at DATETIME NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY telegram_user_id (telegram_user_id),
				KEY status (status)
			) $charset_collate;",

			// Progress.
			"{$p}progress" => "CREATE TABLE {$p}progress (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				user_id BIGINT UNSIGNED NOT NULL,
				verse_reference VARCHAR(50) NOT NULL,
				book VARCHAR(50) NOT NULL,
				chapter TINYINT UNSIGNED NOT NULL,
				verse_number TINYINT UNSIGNED NOT NULL,
				lesson_date DATE NOT NULL,
				completed TINYINT(1) DEFAULT 0,
				vocab_completed TINYINT(1) DEFAULT 0,
				listening_completed TINYINT(1) DEFAULT 0,
				quiz_completed TINYINT(1) DEFAULT 0,
				quiz_score TINYINT UNSIGNED DEFAULT 0,
				quiz_total TINYINT UNSIGNED DEFAULT 0,
				speaking_completed TINYINT(1) DEFAULT 0,
				speaking_score TINYINT UNSIGNED DEFAULT 0,
				writing_completed TINYINT(1) DEFAULT 0,
				writing_score TINYINT UNSIGNED DEFAULT 0,
				xp_earned SMALLINT UNSIGNED DEFAULT 0,
				completed_at DATETIME NULL,
				created_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY user_date (user_id, lesson_date),
				KEY user_id (user_id)
			) $charset_collate;",

			// Streaks.
			"{$p}streaks" => "CREATE TABLE {$p}streaks (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				user_id BIGINT UNSIGNED NOT NULL,
				current_streak INT UNSIGNED DEFAULT 0,
				longest_streak INT UNSIGNED DEFAULT 0,
				last_lesson_date DATE NULL,
				streak_started_at DATE NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY user_id (user_id)
			) $charset_collate;",

			// XP summary.
			"{$p}xp" => "CREATE TABLE {$p}xp (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				user_id BIGINT UNSIGNED NOT NULL,
				weekly_xp INT UNSIGNED DEFAULT 0,
				lifetime_xp INT UNSIGNED DEFAULT 0,
				week_start_date DATE NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY user_id (user_id),
				KEY week_start_date (week_start_date)
			) $charset_collate;",

			// XP log.
			"{$p}xp_log" => "CREATE TABLE {$p}xp_log (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				user_id BIGINT UNSIGNED NOT NULL,
				amount SMALLINT NOT NULL,
				reason VARCHAR(100) NOT NULL,
				reference_type VARCHAR(50) NULL,
				reference_id BIGINT UNSIGNED NULL,
				created_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				KEY user_id (user_id),
				KEY created_at (created_at)
			) $charset_collate;",

			// Leagues.
			"{$p}leagues" => "CREATE TABLE {$p}leagues (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				name VARCHAR(100) NOT NULL,
				division ENUM('genesis','psalms','proverbs','john','romans','revelation') NOT NULL,
				level ENUM('beginner','intermediate','advanced') NOT NULL,
				week_start DATE NOT NULL,
				week_end DATE NOT NULL,
				status ENUM('active','completed') DEFAULT 'active',
				created_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				KEY week_start (week_start),
				KEY status (status)
			) $charset_collate;",

			// League members.
			"{$p}league_members" => "CREATE TABLE {$p}league_members (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				league_id BIGINT UNSIGNED NOT NULL,
				user_id BIGINT UNSIGNED NOT NULL,
				starting_xp INT UNSIGNED DEFAULT 0,
				final_xp INT UNSIGNED DEFAULT 0,
				rank TINYINT UNSIGNED NULL,
				outcome ENUM('promoted','stayed','relegated') NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY league_user (league_id, user_id),
				KEY user_id (user_id)
			) $charset_collate;",

			// Championships.
			"{$p}championships" => "CREATE TABLE {$p}championships (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				month TINYINT UNSIGNED NOT NULL,
				year SMALLINT UNSIGNED NOT NULL,
				level ENUM('beginner','intermediate','advanced','grand') NOT NULL,
				status ENUM('upcoming','active','completed') DEFAULT 'upcoming',
				winner_user_id BIGINT UNSIGNED NULL,
				created_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				KEY month_year (month, year)
			) $charset_collate;",

			// Badges.
			"{$p}badges" => "CREATE TABLE {$p}badges (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				user_id BIGINT UNSIGNED NOT NULL,
				badge_slug VARCHAR(100) NOT NULL,
				badge_name VARCHAR(200) NOT NULL,
				awarded_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY user_badge (user_id, badge_slug),
				KEY badge_slug (badge_slug)
			) $charset_collate;",

			// Groups.
			"{$p}groups" => "CREATE TABLE {$p}groups (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				name VARCHAR(255) NOT NULL,
				description TEXT NULL,
				admin_user_id BIGINT UNSIGNED NOT NULL,
				invite_code VARCHAR(20) UNIQUE NOT NULL,
				verse_focus_book VARCHAR(50) NULL,
				member_count INT UNSIGNED DEFAULT 0,
				status ENUM('active','inactive') DEFAULT 'active',
				created_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY invite_code (invite_code),
				KEY admin_user_id (admin_user_id)
			) $charset_collate;",

			// Group members.
			"{$p}group_members" => "CREATE TABLE {$p}group_members (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				group_id BIGINT UNSIGNED NOT NULL,
				user_id BIGINT UNSIGNED NOT NULL,
				role ENUM('admin','member') DEFAULT 'member',
				joined_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY group_user (group_id, user_id),
				KEY user_id (user_id)
			) $charset_collate;",

			// Verse cache.
			"{$p}verse_cache" => "CREATE TABLE {$p}verse_cache (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				reference VARCHAR(50) NOT NULL,
				book VARCHAR(50) NOT NULL,
				chapter TINYINT UNSIGNED NOT NULL,
				verse_number TINYINT UNSIGNED NOT NULL,
				text TEXT NOT NULL,
				word_count TINYINT UNSIGNED NOT NULL,
				difficulty_tag ENUM('beginner','intermediate','advanced') NOT NULL,
				difficulty_override TINYINT(1) DEFAULT 0,
				cached_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY reference (reference),
				KEY difficulty_tag (difficulty_tag)
			) $charset_collate;",

			// AI content cache.
			"{$p}ai_content_cache" => "CREATE TABLE {$p}ai_content_cache (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				cache_key VARCHAR(200) NOT NULL,
				feature VARCHAR(100) NOT NULL,
				level ENUM('beginner','intermediate','advanced') NOT NULL,
				verse_reference VARCHAR(50) NOT NULL,
				variant TINYINT UNSIGNED DEFAULT 0,
				content LONGTEXT NOT NULL,
				provider VARCHAR(100) NOT NULL,
				model VARCHAR(200) NOT NULL,
				created_at DATETIME NOT NULL,
				expires_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY cache_key (cache_key),
				KEY feature_level_verse (feature, level, verse_reference)
			) $charset_collate;",

			// AI logs.
			"{$p}ai_logs" => "CREATE TABLE {$p}ai_logs (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				feature VARCHAR(100) NOT NULL,
				provider VARCHAR(100) NOT NULL,
				model VARCHAR(200) NOT NULL,
				user_id BIGINT UNSIGNED NULL,
				status ENUM('success','failure','fallback') NOT NULL,
				input_tokens INT UNSIGNED NULL,
				output_tokens INT UNSIGNED NULL,
				latency_ms INT UNSIGNED NULL,
				error_code VARCHAR(100) NULL,
				created_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				KEY created_at (created_at),
				KEY feature (feature),
				KEY status (status)
			) $charset_collate;",

			// Notifications.
			"{$p}notifications" => "CREATE TABLE {$p}notifications (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				user_id BIGINT UNSIGNED NOT NULL,
				type VARCHAR(100) NOT NULL,
				message TEXT NOT NULL,
				telegram_message_id BIGINT UNSIGNED NULL,
				status ENUM('pending','sent','failed') DEFAULT 'pending',
				scheduled_at DATETIME NOT NULL,
				sent_at DATETIME NULL,
				created_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				KEY user_id (user_id),
				KEY status (status),
				KEY scheduled_at (scheduled_at)
			) $charset_collate;",
		);

		foreach ( $tables as $sql ) {
			dbDelta( $sql );
		}
	}
}
