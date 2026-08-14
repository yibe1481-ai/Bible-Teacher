<?php
/**
 * Lessons admin view: recent lesson progress.
 *
 * @package Bible_Teacher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$p    = $wpdb->prefix . BIBLE_TEACHER_DB_PREFIX;
$rows = $wpdb->get_results(
	"SELECT p.*, u.first_name, u.telegram_username
	 FROM {$p}progress p
	 INNER JOIN {$p}users u ON u.id = p.user_id
	 ORDER BY p.lesson_date DESC, p.id DESC LIMIT 200",
	ARRAY_A
);
?>
<div class="wrap be-admin">
	<h1><?php esc_html_e( 'Lessons', 'bible-teacher' ); ?></h1>
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'User', 'bible-teacher' ); ?></th>
				<th><?php esc_html_e( 'Verse', 'bible-teacher' ); ?></th>
				<th><?php esc_html_e( 'Date', 'bible-teacher' ); ?></th>
				<th><?php esc_html_e( 'Quiz', 'bible-teacher' ); ?></th>
				<th><?php esc_html_e( 'Speaking', 'bible-teacher' ); ?></th>
				<th><?php esc_html_e( 'XP', 'bible-teacher' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $rows ) ) : ?>
				<tr><td colspan="6"><?php esc_html_e( 'No lessons yet.', 'bible-teacher' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row['first_name'] . ' (@' . $row['telegram_username'] . ')' ); ?></td>
						<td><?php echo esc_html( $row['verse_reference'] ); ?></td>
						<td><?php echo esc_html( $row['lesson_date'] ); ?></td>
						<td><?php echo esc_html( $row['quiz_score'] . '/' . $row['quiz_total'] ); ?></td>
						<td><?php echo esc_html( $row['speaking_score'] . '%' ); ?></td>
						<td><?php echo (int) $row['xp_earned']; ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>