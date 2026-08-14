<?php
/**
 * Notification scheduling and delivery: daily verse push at the user's
 * configured time, plus an evening reminder if the lesson isn't done.
 *
 * Uses WordPress' action scheduler through our wp-cron events (an Action
 * Scheduler plugin is recommended for production reliability, but the cron
 * hooks below are the via-point).
 *
 * @package Bible_Teacher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once BIBLE_TEACHER_DIR . 'includes/Database/class-repository.php';

/**
 * Class BE_Notification_Manager
 */
class BE_Notification_Manager extends BE_Repository {

	/**
	 * Queue a notification row for a user.
	 *
	 * @param int    $user_id User id.
	 * @param string $type    Type (daily|reminder|league|level_up).
	 * @param string $message Message text.
	 * @param string $scheduled_at UTC scheduled datetime.
	 * @return int|null
	 */
	public function queue( $user_id, $type, $message, $scheduled_at ) {
		global $wpdb;
		$table = $this->table( 'notifications' );

		$wpdb->insert(
			$table,
			array(
				'user_id'      => $user_id,
				'type'         => $type,
				'message'      => $message,
				'status'       => 'pending',
				'scheduled_at' => $scheduled_at,
				'created_at'   => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Queue today's daily-verse notifications for all enabled users who have
	 * not yet completed their lesson.
	 *
	 * @param string $date Date (Y-m-d).
	 * @return int Queued count.
	 */
	public function queue_daily( $date = null ) {
		$config = BE_Options::get( 'notifications' );
		if ( empty( $config['enabled'] ) ) {
			return 0;
		}

		$date    = $date ? $date : gmdate( 'Y-m-d' );
		$bot     = new BE_BotAPI();
		$bible   = new BE_Bible();
		$seq     = new BE_Verse_Sequencer();
		$users   = $this->active_users();

		$queued = 0;
		foreach ( $users as $user ) {
			$reference = $seq->verse_for( $user, $date );
			$verse     = $bible->fetch( $reference );
			if ( ! $verse ) {
				continue;
			}

			$preview = wp_trim_words( $verse['text'], 10, '…' );
			$template = $config['templates']['daily'] ?? "📖 Your daily verse is ready!\n\n{book} {chapter}:{verse}\n\"{verse_preview}...\"\n\nTap to start today's lesson 👇";

			$message = str_replace(
				array( '{book}', '{chapter}', '{verse}', '{verse_preview}' ),
				array( $verse['book'], $verse['chapter'], $verse['verse_number'], $preview ),
				$template
			);

			// Schedule at the user's local notification time converted to UTC.
			$scheduled = $this->utc_for_user_time( $user['notification_time'], $user['timezone'], $date );
			$this->queue( $user['id'], 'daily', $message, $scheduled );
			$queued++;

			// Inline button to open the mini app.
			$mini_url = BE_Options::get( 'telegram', 'mini_app_url' );
			if ( $mini_url ) {
				$this->schedule_send( $user['id'], $message, $scheduled, array(
					'sendMessage' => true,
					'keyboard'    => array( array( array( 'text' => '📖 Open Lesson', 'url' => $mini_url ) ) ),
				) );
			}
		}

		return $queued;
	}

	/**
	 * Queue reminder notifications for users who haven't completed today's lesson.
	 *
	 * @param string $date Date (Y-m-d).
	 * @return int Queued count.
	 */
	public function queue_reminders( $date = null ) {
		$config = BE_Options::get( 'notifications' );
		if ( empty( $config['reminder_enabled'] ) ) {
			return 0;
		}

		$date = $date ? $date : gmdate( 'Y-m-d' );
		$min_streak = (int) ( $config['reminder_min_streak'] ?? 3 );

		$incomplete = $this->users_without_completion( $date, $min_streak );
		$queued      = 0;

		foreach ( $incomplete as $user ) {
			$streak = ( new BE_Streak( $user ) )->current();
			$template = $config['templates']['reminder'] ?? "⏰ Don't break your {streak}-day streak!\n\nToday's lesson takes less than 5 minutes.";
			$message  = str_replace( '{streak}', $streak, $template );

			$scheduled = $this->utc_for_user_time( $config['reminder_time'], $user['timezone'], $date );
			$this->queue( $user['id'], 'reminder', $message, $scheduled );

			$mini_url = BE_Options::get( 'telegram', 'mini_app_url' );
			if ( $mini_url ) {
				$this->schedule_send( $user['id'], $message, $scheduled, array(
					'sendMessage' => true,
					'keyboard'    => array( array( array( 'text' => '📖 Open Lesson', 'url' => $mini_url ) ) ),
				) );
			}
			$queued++;
		}

		return $queued;
	}

	/**
	 * Deliver due pending notifications synchronously (called by cron).
	 *
	 * @param int $limit Max to send per pass.
	 * @return void
	 */
	public function process_due( $limit = 50 ) {
		global $wpdb;
		$table = $this->table( 'notifications' );
		$now   = current_time( 'mysql', true );

		$due = $wpdb->get_results( $wpdb->prepare(
			"SELECT n.*, u.telegram_user_id FROM {$table} n
			 INNER JOIN {$this->table('users')} u ON u.id = n.user_id
			 WHERE n.status = 'pending' AND n.scheduled_at <= %s
			 ORDER BY n.scheduled_at ASC LIMIT %d",
			$now,
			$limit
		), ARRAY_A );

		$bot = new BE_BotAPI();

		foreach ( $due as $item ) {
			$result = $bot->send_message( $item['telegram_user_id'], $item['message'] );

			$status = is_wp_error( $result ) ? 'failed' : 'sent';
			$msg_id = ( ! is_wp_error( $result ) && isset( $result['message_id'] ) ) ? (int) $result['message_id'] : null;

			$wpdb->update(
				$table,
				array(
					'status'              => $status,
					'telegram_message_id' => $msg_id,
					'sent_at'             => current_time( 'mysql', true ),
				),
				array( 'id' => $item['id'] ),
				array( '%s', '%d', '%s' ),
				array( '%d' )
			);
		}
	}

	/**
	 * Retry failed notifications older than an hour.
	 *
	 * @return void
	 */
	public function retry_failed() {
		global $wpdb;
		$table = $this->table( 'notifications' );
		$cutoff= gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS );

		$wpdb->query( $wpdb->prepare(
			"UPDATE {$table} SET status = 'pending' WHERE status = 'failed' AND created_at < %s",
			$cutoff
		) );
	}

	/**
	 * Active (non-banned, notifications enabled) users.
	 *
	 * @return array
	 */
	public function active_users() {
		global $wpdb;
		$table = $this->table( 'users' );

		return $wpdb->get_results(
			"SELECT * FROM {$table} WHERE status = 'active' AND notifications_enabled = 1",
			ARRAY_A
		);
	}

	/**
	 * Users with an active streak who haven't completed today's lesson.
	 *
	 * @param string $date  Date (Y-m-d).
	 * @param int    $min_streak Minimum streak to target.
	 * @return array
	 */
	protected function users_without_completion( $date, $min_streak ) {
		global $wpdb;
		$users     = $this->table( 'users' );
		$progress  = $this->table( 'progress' );
		$streaks   = $this->table( 'streaks' );

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT u.* FROM {$users} u
			 INNER JOIN {$streaks} s ON s.user_id = u.id
			 WHERE u.status = 'active' AND u.notifications_enabled = 1
			   AND s.current_streak >= %d
			   AND u.id NOT IN (
			     SELECT p.user_id FROM {$progress} p WHERE p.lesson_date = %s AND p.completed = 1
			   )",
			$min_streak,
			$date
		), ARRAY_A );
	}

	/**
	 * Convert a user's local HH:MM on a date to UTC, respecting their tz string.
	 *
	 * @param string $time  HH:MM.
	 * @param string $tz    Timezone string.
	 * @param string $date  Y-m-d local date.
	 * @return string MySQL datetime UTC.
	 */
	protected function utc_for_user_time( $time, $tz, $date ) {
		$time = $time && '00:00:00' !== $time ? $time : '07:00:00';
		$tz   = $tz ? $tz : 'UTC';
		$local = $date . ' ' . substr( $time, 0, 5 );

		try {
			$utc = new DateTime( $local, new DateTimeZone( $tz ) );
			$utc->setTimezone( new DateTimeZone( 'UTC' ) );
			return $utc->format( 'Y-m-d H:i:s' );
		} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis
			return $local . ':00';
		}
	}

	/**
	 * Send a Telegram message at a scheduled time via wp-cron.
	 *
	 * @param int    $user_id    User id.
	 * @param string $message    Message.
	 * @param string $scheduled  UTC datetime.
	 * @param array  $opts       Options (keyboard).
	 * @return void
	 */
	protected function schedule_send( $user_id, $message, $scheduled, $opts = array() ) {
		$timestamp = strtotime( $scheduled );
		if ( ! $timestamp || $timestamp <= time() ) {
			return;
		}

		wp_schedule_single_event(
			$timestamp,
			'bible_teacher_send_notification',
			array( 'user_id' => $user_id, 'message' => $message, 'opts' => $opts )
		);
	}
}