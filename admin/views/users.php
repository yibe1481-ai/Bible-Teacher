<?php
/**
 * Users admin view.
 *
 * @package Bible_Teacher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$p  = $wpdb->prefix . BIBLE_TEACHER_DB_PREFIX;
$users = $wpdb->get_results(
	"SELECT u.*, COALESCE((SELECT s.current_streak FROM {$p}streaks s WHERE s.user_id = u.id),0) AS streak
	 FROM {$p}users u ORDER BY u.id DESC LIMIT 200",
	ARRAY_A
);
?>
<div class="wrap be-admin">
	<h1><?php esc_html_e( 'Users', 'bible-teacher' ); ?></h1>
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'ID', 'bible-teacher' ); ?></th>
				<th><?php esc_html_e( 'Name', 'bible-teacher' ); ?></th>
				<th><?php esc_html_e( 'Telegram', 'bible-teacher' ); ?></th>
				<th><?php esc_html_e( 'Level', 'bible-teacher' ); ?></th>
				<th><?php esc_html_e( 'Streak', 'bible-teacher' ); ?></th>
				<th><?php esc_html_e( 'Status', 'bible-teacher' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $users ) ) : ?>
				<tr><td colspan="6"><?php esc_html_e( 'No users yet.', 'bible-teacher' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $users as $user ) : ?>
					<tr>
						<td><?php echo (int) $user['id']; ?></td>
						<td><?php echo esc_html( trim( $user['first_name'] . ' ' . $user['last_name'] ) ); ?></td>
						<td><?php echo esc_html( '@' . $user['telegram_username'] ); ?></td>
						<td><?php echo esc_html( ucfirst( $user['level'] ) ); ?></td>
						<td><?php echo (int) $user['streak']; ?></td>
						<td><?php echo esc_html( $user['status'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>