<?php
/**
 * Settings page renderer with section tabs.
 *
 * @package Bible_Teacher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap be-admin">
	<h1><?php esc_html_e( 'Bible English — Settings', 'bible-teacher' ); ?></h1>

	<form method="post" action="options.php">
		<?php settings_fields( 'bible_teacher_options' ); ?>

		<div class="be-settings-tabs">
			<?php
			$tabs = array(
				'general'       => __( 'General', 'bible-teacher' ),
				'telegram'      => __( 'Telegram', 'bible-teacher' ),
				'learning'      => __( 'Learning', 'bible-teacher' ),
				'competition'   => __( 'Competition', 'bible-teacher' ),
				'voice'         => __( 'Voice / TTS', 'bible-teacher' ),
				'bible'         => __( 'Bible Content', 'bible-teacher' ),
				'notifications' => __( 'Notifications', 'bible-teacher' ),
				'security'      => __( 'Security', 'bible-teacher' ),
			);

			$active = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification
			?>
			<ul class="be-tabs">
				<?php foreach ( $tabs as $slug => $label ) : ?>
					<li>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=bible-english-settings&tab=' . $slug ) ); ?>"
						   class="be-tab <?php echo $active === $slug ? 'is-active' : ''; ?>">
							<?php echo esc_html( $label ); ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>

		<?php foreach ( $tabs as $slug => $label ) : ?>
			<section class="be-section <?php echo $active === $slug ? '' : 'is-hidden'; ?>" id="be-section-<?php echo esc_attr( $slug ); ?>">
				<?php
				do_settings_sections( 'be_settings_' . $slug );
				?>
			</section>
		<?php endforeach; ?>

		<?php submit_button(); ?>
	</form>

	<hr />

	<h2><?php esc_html_e( 'AI Providers', 'bible-teacher' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Providers are managed via the dedicated Manager helper. They are stored encrypted and never logged.', 'bible-teacher' ); ?>
	</p>
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Name', 'bible-teacher' ); ?></th>
				<th><?php esc_html_e( 'Base URL', 'bible-teacher' ); ?></th>
				<th><?php esc_html_e( 'Model', 'bible-teacher' ); ?></th>
				<th><?php esc_html_e( 'Enabled', 'bible-teacher' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php
			$providers = get_option( 'bible_teacher_providers', array() );
			if ( empty( $providers ) ) :
				?>
				<tr><td colspan="4"><?php esc_html_e( 'No AI providers configured yet.', 'bible-teacher' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $providers as $provider ) : ?>
					<tr>
						<td><?php echo esc_html( $provider['name'] ?? '' ); ?></td>
						<td><?php echo esc_html( $provider['base_url'] ?? '' ); ?></td>
						<td><?php echo esc_html( $provider['model'] ?? '' ); ?></td>
						<td><?php echo esc_html( empty( $provider['enabled'] ) ? __( 'No', 'bible-teacher' ) : __( 'Yes', 'bible-teacher' ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>