<?php
/**
 * Handles plugin activation: runs migrations and schedules background jobs.
 *
 * @package Bible_Teacher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BE_Activator
 */
class BE_Activator {

	/**
	 * Perform activation tasks.
	 *
	 * @return void
	 */
	public static function activate() {
		BE_Migrator::maybe_upgrade();

		// Default settings on first install.
		self::install_defaults();

		// Create upload directory for speaking recordings.
		self::ensure_upload_dir();

		// Seed initial verse cache asynchronously via wp-cron.
		if ( ! wp_next_scheduled( 'bible_teacher_verse_fetch' ) ) {
			wp_schedule_event( time(), 'daily', 'bible_teacher_verse_fetch' );
		}

		// Register + flush the Mini App rewrite rule.
		$mini = new BE_MiniApp();
		$mini->register_rewrite();

		// Flush rewrite rules so REST is registered cleanly.
		flush_rewrite_rules();
	}

	/**
	 * Store default option values if not already present.
	 *
	 * @return void
	 */
	private static function install_defaults() {
		require_once BIBLE_TEACHER_DIR . 'config/defaults.php';

		$installed_options = get_option( 'bible_teacher_options', null );
		if ( null === $installed_options ) {
			update_option( 'bible_teacher_options', bible_teacher_default_options() );
		}

		if ( ! get_option( 'bible_teacher_db_version' ) ) {
			update_option( 'bible_teacher_db_version', BIBLE_TEACHER_TABLE_VERSION );
		}

		if ( ! get_option( 'bible_teacher_webhook_secret' ) ) {
			update_option( 'bible_teacher_webhook_secret', wp_generate_password( 32, false ) );
		}

		if ( ! get_option( 'bible_teacher_jwt_secret' ) ) {
			update_option( 'bible_teacher_jwt_secret', wp_generate_password( 48, true, false ) );
		}
	}

	/**
	 * Create the upload subdirectory used for speaking recordings.
	 *
	 * @return void
	 */
	private static function ensure_upload_dir() {
		$upload_dir = wp_upload_dir();
		$target     = trailingslashit( $upload_dir['basedir'] ) . 'bible-teacher';
		if ( ! is_dir( $target ) ) {
			wp_mkdir_p( $target );
		}

		// Guard against direct listing.
		$htaccess = trailingslashit( $target ) . '.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			file_put_contents( $htaccess, "Options -Indexes\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
	}
}
