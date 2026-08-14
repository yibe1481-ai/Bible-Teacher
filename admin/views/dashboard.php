<?php
/**
 * Dashboard view (spec §13.12).
 *
 * @package Bible_Teacher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$users   = $wpdb->prefix . BIBLE_TEACHER_DB_PREFIX . 'users';
$streaks = $wpdb->prefix . BIBLE_TEACHER_DB_PREFIX . 'streaks';
$logs    = $wpdb->prefix . BIBLE_TEACHER_DB_PREFIX . 'ai_logs';

$total_users   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$users}" );
$active_today  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$users} WHERE last_active_at >= %s", gmdate( 'Y-m-d 00:00:00' ) ) );
$active_streaks = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$streaks} WHERE current_streak > 0" );
$ai_today       = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$logs} WHERE created_at >= %s", gmdate( 'Y-m-d 00:00:00' ) ) );
$level_counts   = array();
foreach ( array( 'beginner', 'intermediate', 'advanced' ) as $lvl ) {
	$level_counts[ $lvl ] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$users} WHERE level = %s", $lvl ) );
}
$levels_total = max( 1, array_sum( $level_counts ) );
?>
<div class="wrap be-admin">
	<h1><?php esc_html_e( 'Bible English — Dashboard', 'bible-teacher' ); ?></h1>

	<div class="be-cards">
		<div class="be-card"><span class="be-card-num"><?php echo esc_html( number_format_i18n( $total_users ) ); ?></span><span><?php esc_html_e( 'Total Users', 'bible-teacher' ); ?></span></div>
		<div class="be-card"><span class="be-card-num"><?php echo esc_html( number_format_i18n( $active_today ) ); ?></span><span><?php esc_html_e( 'Active Today', 'bible-teacher' ); ?></span></div>
		<div class="be-card"><span class="be-card-num"><?php echo esc_html( number_format_i18n( $active_streaks ) ); ?></span><span><?php esc_html_e( 'Active Streaks', 'bible-teacher' ); ?></span></div>
		<div class="be-card"><span class="be-card-num"><?php echo esc_html( number_format_i18n( $ai_today ) ); ?></span><span><?php esc_html_e( 'AI Requests Today', 'bible-teacher' ); ?></span></div>
	</div>

	<h2><?php esc_html_e( 'Level Breakdown', 'bible-teacher' ); ?></h2>
	<table class="widefat striped">
		<tbody>
			<?php foreach ( $level_counts as $lvl => $count ) : ?>
				<tr>
					<th><?php echo esc_html( ucfirst( $lvl ) ); ?></th>
					<td><?php echo esc_html( number_format_i18n( $count ) ); ?></td>
					<td><?php echo esc_html( sprintf( '%.0f%%', ( $count / $levels_total ) * 100 ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<h2><?php esc_html_e( 'Quick Status', 'bible-teacher' ); ?></h2>
	<p><?php esc_html_e( 'Use the System page for full health checks and scheduled jobs.', 'bible-teacher' ); ?></p>
</div>