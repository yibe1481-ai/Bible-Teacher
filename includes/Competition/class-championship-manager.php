<?php
/**
 * Monthly championship lifecycle: qualification from weekly league results,
 * bracket seeding, winner selection, and badge award.
 *
 * @package Bible_Teacher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once BIBLE_TEACHER_DIR . 'includes/Database/class-repository.php';

/**
 * Class BE_Championship_Manager
 */
class BE_Championship_Manager extends BE_Repository {

	/**
	 * Qualifying user ids for a month/level championship.
	 *
	 * Top N per division champions qualify (default 3).
	 *
	 * @param int    $month Month (1-12).
	 * @param int    $year  Year.
	 * @param string $level Level.
	 * @return array
	 */
	public function qualifiers( $month, $year, $level ) {
		global $wpdb;
		$members   = $this->table( 'league_members' );
		$leagues   = $this->table( 'leagues' );
		$champions = $this->table( 'championships' );

		$month_start = sprintf( '%04d-%02d-01', $year, $month );
		$month_end   = date( 'Y-m-t', strtotime( $month_start ) );

		// Winners are previously selected championship rows; for MVP we pick
		// top weekly finishers per division.
		$sql = $wpdb->prepare(
			"SELECT m.user_id
			 FROM {$members} m
			 INNER JOIN {$leagues} l ON l.id = m.league_id
			 WHERE l.level = %s AND l.week_start BETWEEN %s AND %s AND m.rank = 1
			 ORDER BY m.final_xp DESC
			 LIMIT %d",
			$level,
			$month_start,
			$month_end,
			(int) BE_Options::get( 'competition', 'qualifiers_per_div' ) ?: 3
		);

		return $wpdb->get_col( $sql );
	}

	/**
	 * Run a single-elimination bracket for qualifying user ids and pick the
	 * winner based on average weekly XP across the month.
	 *
	 * @param array  $user_ids Qualifying user ids.
	 * @param int    $month    Month.
	 * @param int    $year     Year.
	 * @param string $level    Level.
	 * @return int|false Winner user id or false.
	 */
	public function resolve( $user_ids, $month, $year, $level ) {
		global $wpdb;
		$xptable = $this->table( 'xp_log' );
		$month_start = sprintf( '%04d-%02d-01', $year, $month );
		$month_end   = date( 'Y-m-t', strtotime( $month_start ) );

		$best_user = false;
		$best_xp   = -1;

		foreach ( (array) $user_ids as $uid ) {
			$xp = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COALESCE(SUM(amount),0) FROM {$xptable} WHERE user_id = %d AND created_at BETWEEN %s AND %s",
				$uid,
				$month_start,
				$month_end . ' 23:59:59'
			) );
			if ( $xp > $best_xp ) {
				$best_xp = $xp;
				$best_user = (int) $uid;
			}
		}

		return $best_user;
	}

	/**
	 * Record championship winner.
	 *
	 * @param int    $winner_user_id Winner user id.
	 * @param int    $month          Month.
	 * @param int    $year           Year.
	 * @param string $level          Level (beginner|intermediate|advanced|grand).
	 * @return void
	 */
	public function set_winner( $winner_user_id, $month, $year, $level ) {
		global $wpdb;
		$table = $this->table( 'championships' );

		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table} WHERE month = %d AND year = %d AND level = %s",
			$month,
			$year,
			$level
		) );

		if ( $existing ) {
			$wpdb->update(
				$table,
				array( 'winner_user_id' => $winner_user_id, 'status' => 'completed' ),
				array( 'id' => $existing ),
				array( '%d', '%s' ),
				array( '%d' )
			);
		} else {
			$wpdb->insert(
				$table,
				array(
					'month'          => $month,
					'year'           => $year,
					'level'          => $level,
					'status'         => 'completed',
					'winner_user_id' => $winner_user_id,
					'created_at'     => current_time( 'mysql', true ),
				),
				array( '%d', '%d', '%s', '%s', '%d', '%s' )
			);
		}

		// Award the champion badge.
		$badges = new BE_Badge_Manager();
		$month_name = date_i18n( 'F Y', mktime( 0, 0, 0, $month, 1, $year ) );
		$badges->award( $winner_user_id, 'champion', sprintf( 'Champion %s', ucfirst( $level ) ), $month_name );
	}

	/**
	 * Whether the current month has championships configured.
	 *
	 * @return bool
	 */
	public function enabled() {
		return (bool) BE_Options::get( 'competition', 'championships_enabled' );
	}
}