<?php
/**
 * Group / Church mode: create groups, join by invite code, membership
 * management, and internal leaderboards.
 *
 * @package Bible_Teacher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once BIBLE_TEACHER_DIR . 'includes/Database/class-repository.php';

/**
 * Class BE_Group_Manager
 */
class BE_Group_Manager extends BE_Repository {

	/**
	 * Create a group owned by a user.
	 *
	 * @param array  $user User row.
	 * @param array  $data {name, description, verse_focus_book}.
	 * @return array|WP_Error
	 */
	public function create( $user, $data ) {
		$name = trim( sanitize_text_field( $data['name'] ?? '' ) );
		if ( '' === $name ) {
			return new WP_Error( 'be_group_name', __( 'Group name is required.', 'bible-teacher' ) );
		}

		global $wpdb;
		$table = $this->table( 'groups' );

		$invite = $this->unique_invite_code();

		$wpdb->insert(
			$table,
			array(
				'name'              => $name,
				'description'       => sanitize_textarea_field( $data['description'] ?? '' ),
				'admin_user_id'     => $user['id'],
				'invite_code'       => $invite,
				'verse_focus_book'  => sanitize_text_field( $data['verse_focus_book'] ?? '' ),
				'member_count'      => 1,
				'status'            => 'active',
				'created_at'        => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s' )
		);

		$group_id = (int) $wpdb->insert_id;

		$wpdb->insert(
			$this->table( 'group_members' ),
			array(
				'group_id'  => $group_id,
				'user_id'   => $user['id'],
				'role'      => 'admin',
				'joined_at' => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%s' )
		);

		return $this->get( $group_id );
	}

	/**
	 * Join a group by invite code.
	 *
	 * @param array  $user User row.
	 * @param string $code Invite code.
	 * @return array|WP_Error
	 */
	public function join( $user, $code ) {
		$code = strtoupper( trim( sanitize_text_field( $code ) ) );

		global $wpdb;
		$groups = $this->table( 'groups' );

		$group = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$groups} WHERE invite_code = %s AND status = 'active' LIMIT 1",
			$code
		), ARRAY_A );

		if ( ! $group ) {
			return new WP_Error( 'be_group_invalid', __( 'Invalid invite code.', 'bible-teacher' ) );
		}

		if ( $this->is_member( $group['id'], $user['id'] ) ) {
			return $group;
		}

		$wpdb->insert(
			$this->table( 'group_members' ),
			array(
				'group_id'  => $group['id'],
				'user_id'   => $user['id'],
				'role'      => 'member',
				'joined_at' => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%s' )
		);

		$wpdb->update(
			$groups,
			array( 'member_count' => (int) $group['member_count'] + 1 ),
			array( 'id' => $group['id'] ),
			array( '%d' ),
			array( '%d' )
		);

		return $group;
	}

	/**
	 * Fetch a group by id.
	 *
	 * @param int $group_id Group id.
	 * @return array|null
	 */
	public function get( $group_id ) {
		global $wpdb;
		$table = $this->table( 'groups' );

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE id = %d",
			$group_id
		), ARRAY_A );
	}

	/**
	 * Groups a user belongs to.
	 *
	 * @param int $user_id User id.
	 * @return array
	 */
	public function mine( $user_id ) {
		global $wpdb;
		$members = $this->table( 'group_members' );
		$groups  = $this->table( 'groups' );

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT g.*, gm.role AS my_role
			 FROM {$groups} g
			 INNER JOIN {$members} gm ON gm.group_id = g.id
			 WHERE gm.user_id = %d AND g.status = 'active'
			 ORDER BY g.created_at DESC",
			$user_id
		), ARRAY_A );
	}

	/**
	 * Internal leaderboard for a group (weekly XP).
	 *
	 * @param int $group_id Group id.
	 * @return array
	 */
	public function leaderboard( $group_id ) {
		global $wpdb;
		$members = $this->table( 'group_members' );
		$xptable = $this->table( 'xp' );

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT gm.user_id, gm.role,
			        COALESCE((SELECT weekly_xp FROM {$xptable} x WHERE x.user_id = gm.user_id),0) AS weekly_xp
			 FROM {$members} gm
			 WHERE gm.group_id = %d
			 ORDER BY weekly_xp DESC
			 LIMIT 50",
			$group_id
		), ARRAY_A );
	}

	/**
	 * Whether a user is a member of a group.
	 *
	 * @param int $group_id Group id.
	 * @param int $user_id  User id.
	 * @return bool
	 */
	public function is_member( $group_id, $user_id ) {
		global $wpdb;
		$table = $this->table( 'group_members' );
		return (bool) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE group_id = %d AND user_id = %d",
			$group_id,
			$user_id
		) );
	}

	/**
	 * Generate a short, unique invite code.
	 *
	 * @return string
	 */
	protected function unique_invite_code() {
		do {
			$code = strtoupper( wp_generate_password( 8, false, false ) );
			$exists = $this->get_by_code( $code );
		} while ( $exists );

		return $code;
	}

	/**
	 * Look up a group by invite code.
	 *
	 * @param string $code Invite code.
	 * @return array|null
	 */
	protected function get_by_code( $code ) {
		global $wpdb;
		$table = $this->table( 'groups' );
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT id FROM {$table} WHERE invite_code = %s",
			$code
		), ARRAY_A );
	}
}