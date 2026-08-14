<?php
/**
 * Weekly league lifecycle: division sizing, member placement, rank finalizing,
 * promotion/relegation, and per-league leaderboards.
 *
 * @package Bible_Teacher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once BIBLE_TEACHER_DIR . 'includes/Database/class-repository.php';

/**
 * Class BE_League_Manager
 */
class BE_League_Manager extends BE_Repository {

	/**
	 * Get or create the active league for a user's level this week.
	 *
	 * @param array  $user User row.
	 * @param string $date Week start date.
	 * @return array League row.
	 */
	public function get_active_league( $user, $date = null ) {
		$date    = $date ? $date : $this->week_start();
		$week_end= date( 'Y-m-d', strtotime( $date . ' +6 days' ) );

		global $wpdb;
		$leagues = $this->table( 'leagues' );

		// Try to reuse a not-yet-full league for this level/week.
		$existing = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$leagues} WHERE level = %s AND status = 'active' AND week_start = %s AND division <> '' LIMIT 1",
			$user['level'],
			$date
		), ARRAY_A );

		if ( $existing && $this->league_size( $existing['id'] ) < $this->max_per_league() ) {
			$this->ensure_member( $existing, $user );
			return $existing;
		}

		// Otherwise create a fresh league.
		$division = $this->division_for( $user );
		$name     = ucfirst( $division ) . ' League';

		$wpdb->insert(
			$leagues,
			array(
				'name'       => $name,
				'division'   => $division,
				'level'      => $user['level'],
				'week_start' => $date,
				'week_end'   => $week_end,
				'status'     => 'active',
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		$league_id = (int) $wpdb->insert_id;
		$league    = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$leagues} WHERE id = %d",
			$league_id
		), ARRAY_A );

		$this->ensure_member( $league, $user );
		return $league;
	}

	/**
	 * Place a user into a league's member table with starting XP.
	 *
	 * @param array $league League row.
	 * @param array $user   User row.
	 * @return void
	 */
	protected function ensure_member( $league, $user ) {
		global $wpdb;
		$members = $this->table( 'league_members' );
		$xp      = new BE_XP_Manager();
		$totals  = $xp->totals( $user['id'] );

		$wpdb->insert(
			$members,
			array(
				'league_id'   => $league['id'],
				'user_id'     => $user['id'],
				'starting_xp' => (int) $totals['lifetime_xp'],
			),
			array( '%d', '%d', '%d' )
		);
	}

	/**
	 * Size of a league.
	 *
	 * @param int $league_id League id.
	 * @return int
	 */
	protected function league_size( $league_id ) {
		global $wpdb;
		$members = $this->table( 'league_members' );
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$members} WHERE league_id = %d",
			$league_id
		) );
	}

	/**
	 * Max users per league from competition settings.
	 *
	 * @return int
	 */
	protected function max_per_league() {
		return (int) BE_Options::get( 'competition', 'users_per_league' ) ?: 30;
	}

	/**
	 * Choose a division for a user based on lifetime XP buckets.
	 *
	 * @param array $user User row.
	 * @return string
	 */
	protected function division_for( $user ) {
		$divisions = BE_Options::get( 'competition', 'divisions' );
		if ( ! is_array( $divisions ) || count( $divisions ) < 6 ) {
			return 'genesis';
		}

		$xp      = new BE_XP_Manager();
		$totals  = $xp->totals( $user['id'] );
		$lifetime= (int) $totals['lifetime_xp'];

		// New users start at Genesis; higher lifetime XP places them higher.
		if ( $lifetime < 200 ) {
			return $divisions[0];
		}
		if ( $lifetime < 800 ) {
			return $divisions[1];
		}
		if ( $lifetime < 2000 ) {
			return $divisions[2];
		}
		if ( $lifetime < 5000 ) {
			return $divisions[3];
		}
		if ( $lifetime < 10000 ) {
			return $divisions[4];
		}
		return $divisions[5];
	}

	/**
	 * Leaderboard rows for a league, ordered by weekly XP within the window.
	 *
	 * @param int $league_id League id.
	 * @return array
	 */
	public function leaderboard( $league_id ) {
		global $wpdb;
		$members = $this->table( 'league_members' );
		$leagues = $this->table( 'leagues' );

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT m.user_id, m.starting_xp, l.name AS league_name,
			        COALESCE((SELECT weekly_xp FROM {$this->table('xp')} x WHERE x.user_id = m.user_id),0) AS weekly_xp
			 FROM {$members} m
			 INNER JOIN {$leagues} l ON l.id = m.league_id
			 WHERE m.league_id = %d
			 ORDER BY weekly_xp DESC, m.starting_xp DESC
			 LIMIT 30",
			$league_id
		), ARRAY_A );
	}

	/**
	 * Finalize a league: compute final ranks/outcomes and close it.
	 *
	 * @param int $league_id League id.
	 * @return void
	 */
	public function finalize( $league_id ) {
		global $wpdb;
		$members = $this->table( 'league_members' );

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT m.user_id, m.league_id,
			        COALESCE((SELECT weekly_xp FROM {$this->table('xp')} x WHERE x.user_id = m.user_id),0) AS weekly_xp
			 FROM {$members} m WHERE m.league_id = %d ORDER BY weekly_xp DESC",
			$league_id
		), ARRAY_A );

		$promotion = (int) BE_Options::get( 'competition', 'promotion_spots' ) ?: 5;
		$relegation= (int) BE_Options::get( 'competition', 'relegation_spots' ) ?: 5;

		$total = count( $rows );
		foreach ( $rows as $i => $row ) {
			$rank    = $i + 1;
			$outcome = 'stayed';
			if ( $rank <= $promotion ) {
				$outcome = 'promoted';
			} elseif ( $rank > $total - $relegation ) {
				$outcome = 'relegated';
			}

			$wpdb->update(
				$members,
				array(
					'final_xp' => (int) $row['weekly_xp'],
					'rank'     => $rank,
					'outcome'  => $outcome,
				),
				array( 'league_id' => $league_id, 'user_id' => $row['user_id'] ),
				array( '%d', '%d', '%s' ),
				array( '%d', '%d' )
			);
		}

		$wpdb->update(
			$this->table( 'leagues' ),
			array( 'status' => 'completed' ),
			array( 'id' => $league_id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Monday of current week.
	 *
	 * @param string $date Optional reference date.
	 * @return string
	 */
	public function week_start( $date = null ) {
		$xp = new BE_XP_Manager();
		return $xp->week_start( $date );
	}
}