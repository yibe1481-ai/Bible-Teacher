<?php
/**
 * Streak tracking per user with a grace window for lessons completed just
 * past midnight (spec "streak grace period").
 *
 * @package Bible_Teacher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once BIBLE_TEACHER_DIR . 'includes/Database/class-repository.php';

/**
 * Class BE_Streak
 */
class BE_Streak extends BE_Repository {

	/**
	 * User id.
	 *
	 * @var int
	 */
	protected $user_id;

	/**
	 * Constructor.
	 *
	 * @param array $user User row (must contain id).
	 */
	public function __construct( $user ) {
		$this->user_id = (int) ( is_array( $user ) ? $user['id'] : $user );
	}

	/**
	 * Current streak length.
	 *
	 * @return int
	 */
	public function current() {
		$row = $this->row();
		return $row ? (int) $row['current_streak'] : 0;
	}

	/**
	 * Longest streak length.
	 *
	 * @return int
	 */
	public function longest() {
		$row = $this->row();
		return $row ? (int) $row['longest_streak'] : 0;
	}

	/**
	 * Record a lesson completion, updating the streak with grace handling.
	 *
	 * @param string $completed_at UTC datetime of completion.
	 * @return array {current, longest, incremented}
	 */
	public function record_completion( $completed_at = null ) {
		global $wpdb;
		$table = $this->table( 'streaks' );

		$completed_at = $completed_at ? $completed_at : current_time( 'mysql', true );
		$day          = gmdate( 'Y-m-d', strtotime( $completed_at ) );

		$row = $this->row();

		if ( ! $row ) {
			$wpdb->insert(
				$table,
				array(
					'user_id'           => $this->user_id,
					'current_streak'    => 1,
					'longest_streak'    => 1,
					'last_lesson_date'  => $day,
					'streak_started_at' => $day,
					'updated_at'        => $completed_at,
				),
				array( '%d', '%d', '%d', '%s', '%s', '%s' )
			);
			return array( 'current' => 1, 'longest' => 1, 'incremented' => true );
		}

		$grace_hours = (int) BE_Options::get( 'learning', 'streak_grace_hours' ) ?: 2;
		$last_date   = $row['last_lesson_date'];

		// Already completed today — no change (unless same-day replay is on).
		if ( $last_date === $day ) {
			return array(
				'current'     => (int) $row['current_streak'],
				'longest'     => (int) $row['longest_streak'],
				'incremented' => false,
			);
		}

		// Grace: treat "yesterday late" as today when within window.
		$effective_day = $day;
		if ( gmdate( 'G:i', strtotime( $completed_at ) ) < sprintf( '%02d:00', $grace_hours ) ) {
			// Completed within grace hours after midnight: may count toward the
			// previous calendar day if the last lesson was the day before.
			$effective_day = gmdate( 'Y-m-d', strtotime( $day . ' -1 day' ) );
		}

		$expected = gmdate( 'Y-m-d', strtotime( $last_date . ' +1 day' ) );
		$current  = (int) $row['current_streak'];

		if ( $effective_day === $expected || $effective_day === $last_date ) {
			$current++;
			$last_date = $day;
		} elseif ( $last_date === $day ) {
			// no-op
		} else {
			// Streak broken.
			$current    = 1;
			$last_date  = $day;
		}

		$longest = max( (int) $row['longest_streak'], $current );

		$wpdb->update(
			$table,
			array(
				'current_streak'   => $current,
				'longest_streak'   => $longest,
				'last_lesson_date' => $last_date,
				'updated_at'       => $completed_at,
			),
			array( 'user_id' => $this->user_id ),
			array( '%d', '%d', '%s', '%s' ),
			array( '%d' )
		);

		return array(
			'current'     => $current,
			'longest'     => $longest,
			'incremented' => ( $current === (int) $row['current_streak'] + 1 ),
		);
	}

	/**
	 * Fetch the streak row.
	 *
	 * @return array|null
	 */
	protected function row() {
		global $wpdb;
		$table = $this->table( 'streaks' );

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE user_id = %d",
			$this->user_id
		), ARRAY_A );
	}
}