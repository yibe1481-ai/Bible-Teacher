<?php
/**
 * System view: health checks, AI logs, scheduled jobs (spec §13.13–13.15).
 *
 * @package Bible_Teacher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$check = array(
	__( 'WordPress', 'bible-teacher' )       => '✅',
	__( 'Database', 'bible-teacher' )        => $wpdb->get_var( 'SELECT 1' ) ? '✅' : '❌',
	__( 'REST API', 'bible-teacher' )        => rest_url( 'be/v1' ) ? '✅' : '❌',
	__( 'PHP Version', 'bible-teacher' )     => '✅ ' . PHP_VERSION,
	__( 'DB Schema', 'bible-teacher' )       => '✅ ' . ( get_option( 'bible_teacher_db_version', '0' ) ),
);
?>
<div class="wrap be-admin">
	<h1><?php esc_html_e( 'System', 'bible-teacher' ); ?></h1>

	<h2><?php esc_html_e( 'System Health', 'bible-teacher' ); ?></h2>
	<table class="widefat fixed">
		<tbody>
			<?php foreach ( $check as $label => $status ) : ?>
				<tr><th><?php echo esc_html( $label ); ?></th><td><?php echo wp_kses_post( $status ); ?></td></tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<h2><?php esc_html_e( 'AI Logs (recent)', 'bible-teacher' ); ?></h2>
	<?php
	$logs = BE_AI_Logger::query( array(), 25, 1 );
	if ( empty( $logs ) ) {
		echo '<p>' . esc_html__( 'No AI requests logged yet.', 'bible-teacher' ) . '</p>';
	} else {
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Time', 'bible-teacher' ); ?></th>
					<th><?php esc_html_e( 'Feature', 'bible-teacher' ); ?></th>
					<th><?php esc_html_e( 'Provider', 'bible-teacher' ); ?></th>
					<th><?php esc_html_e( 'Model', 'bible-teacher' ); ?></th>
					<th><?php esc_html_e( 'Status', 'bible-teacher' ); ?></th>
					<th><?php esc_html_e( 'Latency', 'bible-teacher' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $logs as $log ) : ?>
					<tr>
						<td><?php echo esc_html( $log['created_at'] ); ?></td>
						<td><?php echo esc_html( $log['feature'] ); ?></td>
						<td><?php echo esc_html( $log['provider'] ); ?></td>
						<td><?php echo esc_html( $log['model'] ); ?></td>
						<td><?php echo esc_html( $log['status'] ); ?></td>
						<td><?php echo esc_html( $log['latency_ms'] . 'ms' ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
	?>

	<h2><?php esc_html_e( 'Scheduled Jobs', 'bible-teacher' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Scheduled events are registered and managed by the cron manager. Check the WordPress cron system for next run times.', 'bible-teacher' ); ?></p>
	<table class="widefat striped">
		<thead><tr><th><?php esc_html_e( 'Event', 'bible-teacher' ); ?></th><th><?php esc_html_e( 'Scheduled', 'bible-teacher' ); ?></th></tr></thead>
		<tbody>
			<?php
			$events = array(
				'bible_teacher_daily_verse_fetch',
				'bible_teacher_ai_pre_generate',
				'bible_teacher_streak_expiry',
				'bible_teacher_level_progress',
				'bible_teacher_badge_award',
				'bible_teacher_weekly_league_rotation',
				'bible_teacher_xp_weekly_reset',
				'bible_teacher_notification_retry',
			);
			foreach ( $events as $event ) {
				$next = wp_next_scheduled( $event );
				echo '<tr><td>' . esc_html( $event ) . '</td><td>' . ( $next ? esc_html( gmdate( 'Y-m-d H:i:s', $next ) ) : esc_html__( 'Not scheduled', 'bible-teacher' ) ) . '</td></tr>';
			}
			?>
		</tbody>
	</table>
</div>