<?php
/**
 * Badge definitions and award logic (spec §6). Badges are permanent and
 * stored once per (user, slug).
 *
 * @package Bible_Teacher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once BIBLE_TEACHER_DIR . 'includes/Database/class-repository.php';

/**
 * Class BE_Badge_Manager
 */
class BE_Badge_Manager extends BE_Repository {

	/**
	 * Static badge registry with emoji + eligibility check key.
	 *
	 * @return array
	 */
	public function registry() {
		return array(
			'week_warrior'       => array( '🔥', __( 'Week Warrior', 'bible-teacher' ), 'streak_7' ),
			'monthly_faithful'   => array( '📅', __( 'Monthly Faithful', 'bible-teacher' ), 'streak_30' ),
			'century'            => array( '💯', __( 'Century', 'bible-teacher' ), 'streak_100' ),
			'year_of_word'       => array( '📖', __( 'Year of the Word', 'bible-teacher' ), 'streak_365' ),
			'gospel_of_john'     => array( '✅', __( 'Gospel of John', 'bible-teacher' ), 'complete_book' ),
			'psalms_complete'    => array( '🎵', __( 'Psalms Complete', 'bible-teacher' ), 'complete_book' ),
			'proverbs_master'    => array( '🧠', __( 'Proverbs Master', 'bible-teacher' ), 'complete_book' ),
			'perfect_week'       => array( '⭐', __( 'Perfect Week', 'bible-teacher' ), 'perfect_week' ),
			'early_bird'         => array( '🌅', __( 'Early Bird', 'bible-teacher' ), 'early_bird' ),
			'speaker'            => array( '🗣️', __( 'Speaker', 'bible-teacher' ), 'speaking_50' ),
			'writer'             => array( '✍️', __( 'Writer', 'bible-teacher' ), 'writing_50' ),
			'sharpshooter'       => array( '🎯', __( 'Sharpshooter', 'bible-teacher' ), 'perfect_streak' ),
			'champion'           => array( '🏆', __( 'Champion', 'bible-teacher' ), 'manual' ),
			'grand_champion'     => array( '👑', __( 'Grand Champion', 'bible-teacher' ), 'manual' ),
		);
	}

	/**
	 * Award a badge to a user if not already held.
	 *
	 * @param int    $user_id User id.
	 * @param string $slug    Badge slug.
	 * @param string $name    Display name.
	 * @param string $detail  Optional month/level suffix.
	 * @return bool Whether newly awarded.
	 */
	public function award( $user_id, $slug, $name, $detail = '' ) {
		if ( $this->has( $user_id, $slug ) ) {
			return false;
		}

		global $wpdb;
		$table = $this->table( 'badges' );

		$full_name = $name . ( $detail ? ' ' . $detail : '' );

		$wpdb->insert(
			$table,
			array(
				'user_id'    => $user_id,
				'badge_slug' => $slug,
				'badge_name' => $full_name,
				'awarded_at' => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%s' )
		);

		return true;
	}

	/**
	 * Whether a user already holds a badge.
	 *
	 * @param int    $user_id User id.
	 * @param string $slug    Badge slug.
	 * @return bool
	 */
	public function has( $user_id, $slug ) {
		global $wpdb;
		$table = $this->table( 'badges' );
		return (bool) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND badge_slug = %s",
			$user_id,
			$slug
		) );
	}

	/**
	 * All badges for a user.
	 *
	 * @param int $user_id User id.
	 * @return array
	 */
	public function get_user_badges( $user_id ) {
		global $wpdb;
		$table = $this->table( 'badges' );

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT badge_slug, badge_name, awarded_at FROM {$table} WHERE user_id = %d ORDER BY awarded_at DESC",
			$user_id
		), ARRAY_A );
	}

	/**
	 * Run automatic badge eligibility checks for a user after a lesson.
	 *
	 * @param array $user User row.
	 * @return array Newly awarded badges.
	 */
	public function evaluate( $user ) {
		$awarded = array();

		$streak = new BE_Streak( $user );
		$current = $streak->current();

		foreach ( $this->registry() as $slug => $def ) {
			// Skip manual badges here.
			if ( 'manual' === $def[2] ) {
				continue;
			}
			if ( $this->passes_streak_check( $slug, $current ) && ! $this->has( $user['id'], $slug ) ) {
				if ( $this->award( $user['id'], $slug, $def[1] ) ) {
					$awarded[] = $slug;
				}
			}
		}

		return $awarded;
	}

	/**
	 * Simple streak-threshold checks for the automatic pass.
	 *
	 * @param string $slug    Badge slug.
	 * @param int    $current Current streak length.
	 * @return bool
	 */
	protected function passes_streak_check( $slug, $current ) {
		switch ( $slug ) {
			case 'week_warrior':
				return $current >= 7;
			case 'monthly_faithful':
				return $current >= 30;
			case 'century':
				return $current >= 100;
			case 'year_of_word':
				return $current >= 365;
		}
		return false;
	}
}