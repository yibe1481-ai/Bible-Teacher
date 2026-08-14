<?php
/**
 * Registers scheduled background jobs (spec §13.15).
 *
 * wp-cron events are registered here. For production we recommend pairing
 * with the Action Scheduler plugin; the handlers below remain the same entry
 * points either way.
 *
 * @package Bible_Teacher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BE_Cron
 */
class BE_Cron {

	/**
	 * List of events with their intervals.
	 *
	 * @var array
	 */
	protected $events = array(
		'bible_teacher_daily_verse_fetch'      => 'daily',
		'bible_teacher_ai_pre_generate'        => 'daily',
		'bible_teacher_streak_expiry'          => 'daily',
		'bible_teacher_level_progress'         => 'daily',
		'bible_teacher_badge_award'            => 'daily',
		'bible_teacher_ai_cache_cleanup'       => 'daily',
		'bible_teacher_weekly_league_rotation' => 'weekly',
		'bible_teacher_xp_weekly_reset'        => 'weekly',
		'bible_teacher_notification_retry'     => 'be_15_minutes',
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->register_interval();
		$this->schedule();
		$this->bind_handlers();
	}

	/**
	 * Add the custom 15-minute interval.
	 *
	 * @return void
	 */
	protected function register_interval() {
		add_filter(
			'cron_schedules',
			function ( $schedules ) {
				$schedules['be_15_minutes'] = array(
					'interval' => 15 * MINUTE_IN_SECONDS,
					'display'  => __( 'Every 15 minutes', 'bible-teacher' ),
				);
				$schedules['be_weekly'] = array(
					'interval' => WEEK_IN_SECONDS,
					'display'  => __( 'Once weekly', 'bible-teacher' ),
				);
				return $schedules;
			}
		);
	}

	/**
	 * Ensure all events are scheduled once.
	 *
	 * @return void
	 */
	protected function schedule() {
		foreach ( $this->events as $hook => $interval ) {
			if ( ! wp_next_scheduled( $hook ) ) {
				wp_schedule_event( time(), $interval, $hook );
			}
		}
	}

	/**
	 * Bind each event to its handler.
	 *
	 * @return void
	 */
	protected function bind_handlers() {
		add_action( 'bible_teacher_verse_fetch', array( $this, 'fetch_and_cache_verses' ) );
		add_action( 'bible_teacher_daily_verse_fetch', array( $this, 'fetch_and_cache_verses' ) );
		add_action( 'bible_teacher_ai_pre_generate', array( $this, 'ai_pre_generate' ) );
		add_action( 'bible_teacher_streak_expiry', array( $this, 'streak_expiry' ) );
		add_action( 'bible_teacher_level_progress', array( $this, 'level_progress' ) );
		add_action( 'bible_teacher_badge_award', array( $this, 'badge_award' ) );
		add_action( 'bible_teacher_ai_cache_cleanup', array( $this, 'ai_cache_cleanup' ) );
		add_action( 'bible_teacher_weekly_league_rotation', array( $this, 'league_rotation' ) );
		add_action( 'bible_teacher_xp_weekly_reset', array( $this, 'xp_weekly_reset' ) );
		add_action( 'bible_teacher_notification_retry', array( $this, 'notification_retry' ) );
		add_action( 'bible_teacher_championship_process', array( $this, 'championship_process' ) );
		add_action( 'bible_teacher_send_notification', array( $this, 'send_single_notification' ), 10, 3 );
	}

	/**
	 * Pre-fetch the next day's verse into local cache.
	 *
	 * @return void
	 */
	public function fetch_and_cache_verses() {
		$bible        = new BE_Bible();
		$users        = ( new BE_Notification_Manager() )->active_users();
		$sequencer    = new BE_Verse_Sequencer();
		$date         = gmdate( 'Y-m-d', strtotime( '+1 day' ) );

		$refs = array();
		foreach ( $users as $user ) {
			$refs[] = $sequencer->verse_for( $user, $date );
		}
		$refs = array_values( array_unique( $refs ) );

		foreach ( $refs as $ref ) {
			$bible->pre_cache( $ref );
		}
	}

	/**
	 * Pre-generate AI content for tomorrow's verses (cache warm).
	 *
	 * @return void
	 */
	public function ai_pre_generate() {
		$bible    = new BE_Bible();
		$ai       = new BE_AI_Manager();
		$date     = gmdate( 'Y-m-d', strtotime( '+1 day' ) );
		$levels   = array( 'beginner', 'intermediate', 'advanced' );
		$users    = ( new BE_Notification_Manager() )->active_users();
		$sequencer = new BE_Verse_Sequencer();

		$touched = array();
		foreach ( $users as $user ) {
			$key = $user['level'] . ':' . $sequencer->verse_for( $user, $date );
			if ( in_array( $key, $touched, true ) ) {
				continue;
			}
			$touched[] = $key;

			$reference = explode( ':', $key )[1];
			$verse     = $bible->fetch( $reference );
			if ( ! $verse ) {
				continue;
			}

			if ( $ai->enabled( 'vocabulary_generation' ) ) {
				( new BE_Vocabulary_Generator( $ai ) )->generate( $verse['text'], $verse, $user['level'], $reference );
			}
			if ( $ai->enabled( 'quiz_generation' ) ) {
				$variants = max( 1, (int) $ai->feature_config( 'quiz_generation' )['cache_variants'] );
				for ( $i = 0; $i < $variants; $i++ ) {
					( new BE_Quiz_Generator( $ai ) )->generate( $verse['text'], $user['level'], $reference, $i );
				}
			}
		}
	}

	/**
	 * Zero out streaks that lapsed beyond the grace window.
	 *
	 * @return void
	 */
	public function streak_expiry() {
		global $wpdb;
		$table  = $wpdb->prefix . BIBLE_TEACHER_DB_PREFIX . 'streaks';
		$grace  = (int) BE_Options::get( 'learning', 'streak_grace_hours' ) ?: 2;
		$cutoff = gmdate( 'Y-m-d', time() - ( $grace + 24 ) * HOUR_IN_SECONDS );

		$wpdb->query( $wpdb->prepare(
			"UPDATE {$table} SET current_streak = 0 WHERE last_lesson_date IS NOT NULL AND last_lesson_date < %s",
			$cutoff
		) );
	}

	/**
	 * Evaluate automatic level progression signals.
	 *
	 * @return void
	 */
	public function level_progress() {
		// MVP: no automatic forced changes. Level changes are user-consented
		// per spec §3.3. This is a hook point for future scheduling.
	}

	/**
	 * Evaluate automatic badge awards for active users.
	 *
	 * @return void
	 */
	public function badge_award() {
		$users = ( new BE_Notification_Manager() )->active_users();
		$badges= new BE_Badge_Manager();
		foreach ( $users as $user ) {
			$badges->evaluate( $user );
		}
	}

	/**
	 * Delete expired AI content cache rows.
	 *
	 * @return void
	 */
	public function ai_cache_cleanup() {
		global $wpdb;
		$table = $wpdb->prefix . BIBLE_TEACHER_DB_PREFIX . 'ai_content_cache';
		$wpdb->query( $wpdb->prepare(
			"DELETE FROM {$table} WHERE expires_at <= %s",
			current_time( 'mysql', true )
		) );
	}

	/**
	 * Finalize completed leagues and roll new weekly leagues.
	 *
	 * @return void
	 */
	public function league_rotation() {
		$leagues  = new BE_League_Manager();

		// Finalize all active leagues from the previous week.
		$prev_week = $leagues->week_start( gmdate( 'Y-m-d' ) );
		global $wpdb;
		$table = $wpdb->prefix . BIBLE_TEACHER_DB_PREFIX . 'leagues';

		$active = $wpdb->get_results(
			"SELECT id FROM {$table} WHERE status = 'active'",
			ARRAY_A
		);
		foreach ( $active as $row ) {
			$leagues->finalize( $row['id'] );
		}

		// New leagues form lazily on next leaderboard access.
		return count( $active );
	}

	/**
	 * Reset weekly XP counters (Monday midnight).
	 *
	 * @return void
	 */
	public function xp_weekly_reset() {
		( new BE_XP_Manager() )->reset_weekly();
	}

	/**
	 * Retry failed notifications.
	 *
	 * @return void
	 */
	public function notification_retry() {
		( new BE_Notification_Manager() )->retry_failed();
	}

	/**
	 * Process monthly championships: qualify + resolve winners.
	 *
	 * @return void
	 */
	public function championship_process() {
		$manager = new BE_Championship_Manager();
		if ( ! $manager->enabled() ) {
			return;
		}

		// Run for the previous month at start-of-month.
		$now       = new DateTimeImmutable();
		$prev      = $now->modify( 'first day of last month' );
		$month     = (int) $prev->format( 'n' );
		$year      = (int) $prev->format( 'Y' );

		foreach ( array( 'beginner', 'intermediate', 'advanced' ) as $level ) {
			$qualifiers = $manager->qualifiers( $month, $year, $level );
			$winner     = $manager->resolve( $qualifiers, $month, $year, $level );
			if ( $winner ) {
				$manager->set_winner( $winner, $month, $year, $level );
			}
		}
	}

	/**
	 * Send a single queued notification (scheduled single event).
	 *
	 * @param int    $user_id User id.
	 * @param string $message Message.
	 * @param array  $opts    Options.
	 * @return void
	 */
	public function send_single_notification( $user_id, $message, $opts = array() ) {
		$user = ( new BE_User_Service() )->get( (int) $user_id );
		if ( ! $user ) {
			return;
		}
		$bot = new BE_BotAPI();
		$keyboard = isset( $opts['keyboard'] ) ? $opts['keyboard'] : array();
		$bot->send_message( $user['telegram_user_id'], $message, $keyboard );
	}
}