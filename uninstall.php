<?php
/**
 * Uninstall handler — removes custom tables and options.
 *
 * This runs only when the plugin is deleted from WordPress. All data is
 * destroyed intentionally, so we gate on the standard WP_UNINSTALL_PLUGIN
 * constant as WordPress requires.
 *
 * @package Bible_Teacher
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Only remove data if the option permitting it is enabled (defaults to on for full uninstall).
$cleanup = get_option( 'bible_teacher_uninstall_cleanup', 1 );
if ( ! $cleanup ) {
	return;
}

$tables = array(
	'be_ai_content_cache',
	'be_ai_logs',
	'be_badges',
	'be_championships',
	'be_group_members',
	'be_groups',
	'be_league_members',
	'be_leagues',
	'be_notifications',
	'be_progress',
	'be_streaks',
	'be_users',
	'be_verse_cache',
	'be_xp_log',
	'be_xp',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL
}

$options = array(
	'bible_teacher_options',
	'bible_teacher_db_version',
	'bible_teacher_webhook_secret',
	'bible_teacher_jwt_secret',
	'bible_teacher_providers',
	'bible_teacher_providers_backup',
	'bible_teacher_ai_usage',
	'bible_teacher_uninstall_cleanup',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

// Remove the upload directory.
$upload_dir = wp_upload_dir();
$target     = trailingslashit( $upload_dir['basedir'] ) . 'bible-teacher';
if ( is_dir( $target ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
	$filesystem = new WP_Filesystem_Direct( null );
	$filesystem->rmdir( $target, true );
}
