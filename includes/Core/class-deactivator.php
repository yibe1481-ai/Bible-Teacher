<?php
/**
 * Handles plugin deactivation: unschedules background jobs.
 *
 * @package Bible_Teacher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BE_Deactivator
 */
class BE_Deactivator {

	/**
	 * Remove scheduled cron events on deactivation.
	 *
	 * @return void
	 */
	public static function deactivate() {
		$events = array(
			'bible_teacher_daily_verse_fetch',
			'bible_teacher_ai_pre_generate',
			'bible_teacher_streak_expiry',
			'bible_teacher_weekly_league_rotation',
			'bible_teacher_xp_weekly_reset',
			'bible_teacher_level_progress',
			'bible_teacher_badge_award',
			'bible_teacher_championship_process',
			'bible_teacher_notification_retry',
			'bible_teacher_ai_cache_cleanup',
			'bible_teacher_verse_fetch',
		);

		foreach ( $events as $event ) {
			$timestamp = wp_next_scheduled( $event );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, $event );
			}
		}
	}
}
