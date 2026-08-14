<?php
/**
 * XP economy: awards XP for actions, applies level multipliers, tracks
 * weekly + lifetime totals, and writes an append-only ledger.
 *
 * Weekly XP resets every Monday (spec §4). Lifetime XP never resets.
 *
 * @package Bible_Teacher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once BIBLE_TEACHER_DIR . 'includes/Database/class-repository.php';

/**
 * Class BE_XP_Manager
 */
class BE_XP_Manager extends BE_Repository {

	/**
	 * Award XP to a user for a reason.
	 *
	 * @param array  $user          User row.
	 * @param int    $amount        Base XP amount before multiplier.
	 * @param string $reason        Ledger reason.
	 * @param string $reference_type Optional reference type.
	 * @param int    $reference_id   Optional reference id.
	 * @return int Awarded XP after multiplier.
	 */
	public function award( $user, $amount, $reason, $reference_type = null, $reference_id = null ) {
		if ( (int) $amount <= 0 ) {
			return 0;
		}

		$multiplier  = $this->level_multiplier( $user['level'] );
		$final       = (int) round( $amount * $multiplier );

		$now  = current_time( 'mysql', true );
		$week = $this->week_start();

		$this->add_row( $user['id'], $final, $reason, $reference_type, $reference_id, $now );

		// Update running totals (upsert).
		global $wpdb;
		$table = $this->table( 'xp' );

		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT id, weekly_xp, lifetime_xp FROM {$table} WHERE user_id = %d",
			$user['id']
		), ARRAY_A );

		if ( ! $row ) {
			$wpdb->insert(
				$table,
				array(
					'user_id'         => $user['id'],
					'weekly_xp'       => $final,
					'lifetime_xp'     => $final,
					'week_start_date' => $week,
					'updated_at'      => $now,
				),
				array( '%d', '%d', '%d', '%s', '%s' )
			);
		} else {
			$weekly     = (int) $row['weekly_xp'] + $final;
			$lifetime   = (int) $row['lifetime_xp'] + $final;
			$wpdb->update(
				$table,
				array(
					'weekly_xp'   => $weekly,
					'lifetime_xp' => $lifetime,
					'updated_at'  => $now,
				),
				array( 'id' => $row['id'] ),
				array( '%d', '%d', '%s' ),
				array( '%d' )
			);
		}

		return $final;
	}

	/**
	 * Append a ledger entry.
	 *
	 * @param int    $user_id User id.
	 * @param int    $amount  XP amount (may be negative).
	 * @param string $reason  Reason.
	 * @param string|null $reference_type Reference type.
	 * @param int|null $reference_id Reference id.
	 * @param string $now     Timestamp.
	 * @return void
	 */
	protected function add_row( $user_id, $amount, $reason, $reference_type, $reference_id, $now ) {
		global $wpdb;
		$table = $this->table( 'xp_log' );

		$wpdb->insert(
			$table,
			array(
				'user_id'        => $user_id,
				'amount'         => $amount,
				'reason'         => $reason,
				'reference_type' => $reference_type,
				'reference_id'   => $reference_id,
				'created_at'     => $now,
			),
			array( '%d', '%d', '%s', '%s', '%d', '%s' )
		);
	}

	/**
	 * Level XP multiplier from settings.
	 *
	 * @param string $level Level.
	 * @return float
	 */
	protected function level_multiplier( $level ) {
		$learning = BE_Options::get( 'learning' );
		$levels   = $learning['levels'] ?? array();
		if ( isset( $levels[ $level ]['xp_multiplier'] ) ) {
			return (float) $levels[ $level ]['xp_multiplier'];
		}
		return 1.0;
	}

	/**
	 * Current day's earned XP from the ledger.
	 *
	 * @param int $user_id User id.
	 * @return int
	 */
	public function today_xp( $user_id ) {
		global $wpdb;
		$table = $this->table( 'xp_log' );

		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(amount),0) FROM {$table} WHERE user_id = %d AND created_at >= %s",
			$user_id,
			gmdate( 'Y-m-d 00:00:00' )
		) );
	}

	/**
	 * Weekly and lifetime totals for a user.
	 *
	 * @param int $user_id User id.
	 * @return array
	 */
	public function totals( $user_id ) {
		global $wpdb;
		$table = $this->table( 'xp' );

		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT weekly_xp, lifetime_xp, week_start_date FROM {$table} WHERE user_id = %d",
			$user_id
		), ARRAY_A );

		if ( ! $row ) {
			return array( 'weekly_xp' => 0, 'lifetime_xp' => 0, 'week_start_date' => $this->week_start() );
		}
		return $row;
	}

	/**
	 * Reset all weekly XP counters (weekly league cycle).
	 *
	 * @return void
	 */
	public function reset_weekly() {
		global $wpdb;
		$table = $this->table( 'xp' );

		$wpdb->query( $wpdb->prepare(
			"UPDATE {$table} SET weekly_xp = 0, week_start_date = %s, updated_at = %s",
			$this->week_start(),
			current_time( 'mysql', true )
		) );
	}

	/**
	 * Monday of the current week (configurable reset day).
	 *
	 * @param string $date Date reference (default today).
	 * @return string Y-m-d
	 */
	public function week_start( $date = null ) {
		$date = $date ? strtotime( $date ) : time();
		return date( 'Y-m-d', strtotime( 'monday this week', $date ) ); // phpcs:ignore WordPress.DateTime
	}
}