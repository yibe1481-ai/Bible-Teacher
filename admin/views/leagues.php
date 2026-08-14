<?php
/**
 * Leagues admin view.
 *
 * @package Bible_Teacher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$p      = $wpdb->prefix . BIBLE_TEACHER_DB_PREFIX;
$leagues = $wpdb->get_results(
	"SELECT l.*, (SELECT COUNT(*) FROM {$p}league_members m WHERE m.league_id = l.id) AS members
	 FROM {$p}leagues l ORDER BY l.week_start DESC LIMIT 100",
	ARRAY_A
);
?>
<div class="wrap be-admin">
	<h1><?php esc_html_e( 'Leagues', 'bible-teacher' ); ?></h1>
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Name', 'bible-teacher' ); ?></th>
				<th><?php esc_html_e( 'Division', 'bible-teacher' ); ?></th>
				<th><?php esc_html_e( 'Level', 'bible-teacher' ); ?></th>
				<th><?php esc_html_e( 'Week', 'bible-teacher' ); ?></th>
				<th><?php esc_html_e( 'Members', 'bible-teacher' ); ?></th>
				<th><?php esc_html_e( 'Status', 'bible-teacher' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $leagues ) ) : ?>
				<tr><td colspan="6"><?php esc_html_e( 'No leagues yet.', 'bible-teacher' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $leagues as $league ) : ?>
					<tr>
						<td><?php echo esc_html( $league['name'] ); ?></td>
						<td><?php echo esc_html( ucfirst( $league['division'] ) ); ?></td>
						<td><?php echo esc_html( ucfirst( $league['level'] ) ); ?></td>
						<td><?php echo esc_html( $league['week_start'] . ' → ' . $league['week_end'] ); ?></td>
						<td><?php echo (int) $league['members']; ?></td>
						<td><?php echo esc_html( $league['status'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>