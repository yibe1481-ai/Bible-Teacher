<?php
/**
 * Registers Settings API sections and fields for the Bible English options.
 *
 * @package Bible_Teacher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BE_Settings_Fields
 */
class BE_Settings_Fields {

	/**
	 * Option group (key to store under).
	 *
	 * @var string
	 */
	protected $option;

	/**
	 * Constructor.
	 *
	 * @param string $option Option key.
	 */
	public function __construct( $option ) {
		$this->option = $option;
	}

	/**
	 * Register all sections and fields.
	 *
	 * @return void
	 */
	public function register_all() {
		$this->general();
		$this->telegram();
		$this->learning();
		$this->competition();
		$this->voice();
		$this->bible();
		$this->notifications();
		$this->security();
	}

	/**
	 * General settings section.
	 *
	 * @return void
	 */
	protected function general() {
		add_settings_section( 'be_general', __( 'General', 'bible-teacher' ), null, 'be_settings_general' );

		$fields = array(
			'tagline'      => __( 'Tagline', 'bible-teacher' ),
			'default_timezone' => __( 'Default Timezone', 'bible-teacher' ),
			'daily_lesson_time' => __( 'Daily Lesson Time', 'bible-teacher' ),
			'reminder_time' => __( 'Reminder Time', 'bible-teacher' ),
			'max_users_per_league' => __( 'Max Users per League', 'bible-teacher' ),
			'min_users_per_league' => __( 'Min Users per League', 'bible-teacher' ),
		);

		foreach ( $fields as $key => $label ) {
			add_settings_field(
				'be_general_' . $key,
				$label,
				array( $this, 'text_field' ),
				'be_settings_general',
				'be_general',
				array( 'section' => 'general', 'key' => $key )
			);
		}
	}

	/**
	 * Telegram settings section.
	 *
	 * @return void
	 */
	protected function telegram() {
		add_settings_section( 'be_telegram', __( 'Telegram', 'bible-teacher' ), null, 'be_settings_telegram' );

		$fields = array(
			'bot_token'    => __( 'Bot Token', 'bible-teacher' ),
			'bot_username' => __( 'Bot Username', 'bible-teacher' ),
			'webhook_url'  => __( 'Webhook URL', 'bible-teacher' ),
			'mini_app_url' => __( 'Mini App URL', 'bible-teacher' ),
		);

		foreach ( $fields as $key => $label ) {
			add_settings_field(
				'be_telegram_' . $key,
				$label,
				array( $this, 'text_field' ),
				'be_settings_telegram',
				'be_telegram',
				array( 'section' => 'telegram', 'key' => $key, 'password' => 'bot_token' === $key )
			);
		}
	}

	/**
	 * Learning settings section.
	 *
	 * @return void
	 */
	protected function learning() {
		add_settings_section( 'be_learning', __( 'Learning', 'bible-teacher' ), null, 'be_settings_learning' );

		$fields = array(
			'vocab_words_per_lesson'  => __( 'Vocabulary Words per Lesson', 'bible-teacher' ),
			'max_recording_seconds'   => __( 'Max Recording Length (s)', 'bible-teacher' ),
			'quiz_time_limit'         => __( 'Quiz Time Limit (s, 0=none)', 'bible-teacher' ),
			'show_answer_after_wrong' => __( 'Show Answer After Wrong', 'bible-teacher' ),
			'streak_grace_hours'      => __( 'Streak Grace Hours', 'bible-teacher' ),
		);

		foreach ( $fields as $key => $label ) {
			add_settings_field(
				'be_learning_' . $key,
				$label,
				array( $this, 'text_field' ),
				'be_settings_learning',
				'be_learning',
				array( 'section' => 'learning', 'key' => $key )
			);
		}
	}

	/**
	 * Competition settings section.
	 *
	 * @return void
	 */
	protected function competition() {
		add_settings_section( 'be_competition', __( 'Competition', 'bible-teacher' ), null, 'be_settings_competition' );

		$fields = array(
			'users_per_league' => __( 'Users per League', 'bible-teacher' ),
			'promotion_spots'  => __( 'Promotion Spots', 'bible-teacher' ),
			'relegation_spots' => __( 'Relegation Spots', 'bible-teacher' ),
			'leagues_enabled'  => __( 'Leagues Enabled', 'bible-teacher' ),
		);

		foreach ( $fields as $key => $label ) {
			add_settings_field(
				'be_competition_' . $key,
				$label,
				array( $this, 'text_field' ),
				'be_settings_competition',
				'be_competition',
				array( 'section' => 'competition', 'key' => $key )
			);
		}
	}

	/**
	 * Voice settings section.
	 *
	 * @return void
	 */
	protected function voice() {
		add_settings_section( 'be_voice', __( 'Voice / TTS', 'bible-teacher' ), null, 'be_settings_voice' );

		$fields = array(
			'tts_enabled'   => __( 'TTS Enabled', 'bible-teacher' ),
			'tts_provider'  => __( 'TTS Provider (google|elevenlabs|browser)', 'bible-teacher' ),
			'google_tts_key'=> __( 'Google Cloud TTS Key', 'bible-teacher' ),
			'max_audio_mb'  => __( 'Max Audio Upload (MB)', 'bible-teacher' ),
		);

		foreach ( $fields as $key => $label ) {
			add_settings_field(
				'be_voice_' . $key,
				$label,
				array( $this, 'text_field' ),
				'be_settings_voice',
				'be_voice',
				array(
					'section'  => 'voice',
					'key'      => $key,
					'password' => 'google_tts_key' === $key,
				)
			);
		}
	}

	/**
	 * Bible settings section.
	 *
	 * @return void
	 */
	protected function bible() {
		add_settings_section( 'be_bible', __( 'Bible Content', 'bible-teacher' ), null, 'be_settings_bible' );

		$fields = array(
			'api_base'            => __( 'Bible API Base URL', 'bible-teacher' ),
			'translation'         => __( 'Translation', 'bible-teacher' ),
			'beginner_book'       => __( 'Beginner Starting Book', 'bible-teacher' ),
			'intermediate_book'   => __( 'Intermediate Starting Book', 'bible-teacher' ),
			'advanced_book'       => __( 'Advanced Starting Book', 'bible-teacher' ),
			'delivery_order'      => __( 'Delivery Order (sequential|random)', 'bible-teacher' ),
		);

		foreach ( $fields as $key => $label ) {
			add_settings_field(
				'be_bible_' . $key,
				$label,
				array( $this, 'text_field' ),
				'be_settings_bible',
				'be_bible',
				array( 'section' => 'bible', 'key' => $key )
			);
		}
	}

	/**
	 * Notification settings section.
	 *
	 * @return void
	 */
	protected function notifications() {
		add_settings_section( 'be_notifications', __( 'Notifications', 'bible-teacher' ), null, 'be_settings_notifications' );

		$fields = array(
			'enabled'           => __( 'Notifications Enabled', 'bible-teacher' ),
			'default_time'      => __( 'Default Lesson Time', 'bible-teacher' ),
			'reminder_enabled'  => __( 'Reminder Enabled', 'bible-teacher' ),
			'reminder_time'     => __( 'Reminder Time', 'bible-teacher' ),
			'reminder_min_streak' => __( 'Reminder Min Streak', 'bible-teacher' ),
		);

		foreach ( $fields as $key => $label ) {
			add_settings_field(
				'be_notifications_' . $key,
				$label,
				array( $this, 'text_field' ),
				'be_settings_notifications',
				'be_notifications',
				array( 'section' => 'notifications', 'key' => $key )
			);
		}
	}

	/**
	 * Security settings section.
	 *
	 * @return void
	 */
	protected function security() {
		add_settings_section( 'be_security', __( 'Security', 'bible-teacher' ), null, 'be_settings_security' );

		$fields = array(
			'jwt_expiry_minutes' => __( 'JWT Expiry (minutes)', 'bible-teacher' ),
			'rate_limit_per_min' => __( 'REST Rate Limit (req/min)', 'bible-teacher' ),
			'max_upload_mb'      => __( 'Max Upload Size (MB)', 'bible-teacher' ),
			'audit_logging'      => __( 'Audit Logging Enabled', 'bible-teacher' ),
			'ban_on_abuse'       => __( 'Ban on Abuse', 'bible-teacher' ),
			'abuse_threshold'    => __( 'Abuse Threshold (per hour)', 'bible-teacher' ),
		);

		foreach ( $fields as $key => $label ) {
			add_settings_field(
				'be_security_' . $key,
				$label,
				array( $this, 'text_field' ),
				'be_settings_security',
				'be_security',
				array( 'section' => 'security', 'key' => $key )
			);
		}
	}

	/**
	 * Render a simple text field bound to the options array.
	 *
	 * @param array $args Field args.
	 * @return void
	 */
	public function text_field( $args ) {
		$section = $args['section'];
		$key     = $args['key'];
		$value   = BE_Options::get( $section, $key );

		if ( is_array( $value ) ) {
			$value = implode( ', ', $value );
		}

		$type = ! empty( $args['password'] ) ? 'password' : 'text';

		printf(
			'<input type="%1$s" name="%2$s[%3$s][%4$s]" value="%5$s" class="regular-text" />',
			esc_attr( $type ),
			esc_attr( $this->option ),
			esc_attr( $section ),
			esc_attr( $key ),
			esc_attr( $value )
		);
	}
}