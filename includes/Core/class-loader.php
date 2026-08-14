<?php
/**
 * Autoloader for the Bible Teacher plugin.
 *
 * Maps BE_ class names to include/ paths via an explicit table. An explicit
 * map is used (rather than an underscore-splitting heuristic) because many
 * classes share suffixes (e.g. Manager, Service) and live in distinct
 * folders; heuristics would collide.
 *
 * @package Bible_Teacher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BE_Loader
 */
class BE_Loader {

	/**
	 * Explicit class-name → relative-path map.
	 *
	 * @var array
	 */
	private static $map = array(
		// Core.
		'BE_Plugin'          => 'Core/class-plugin.php',
		'BE_Loader'          => 'Core/class-loader.php',
		'BE_Activator'       => 'Core/class-activator.php',
		'BE_Deactivator'     => 'Core/class-deactivator.php',
		'BE_Options'         => 'Core/class-options.php',
		// Database.
		'BE_Migrator'        => 'Database/class-migrator.php',
		'BE_Repository'      => 'Database/class-repository.php',
		// Security.
		'BE_Security'        => 'Security/class-security.php',
		// Telegram.
		'BE_JWT'             => 'Telegram/class-jwt.php',
		'BE_BotAPI'          => 'Telegram/class-bot-api.php',
		'BE_Auth'            => 'Telegram/class-auth-service.php',
		'BE_Webhook'         => 'Telegram/class-webhook-handler.php',
		// AI.
		'BE_AI_Manager'      => 'AI/class-ai-manager.php',
		'BE_AI_Cache'        => 'AI/class-ai-cache.php',
		'BE_AI_Logger'       => 'AI/class-ai-logger.php',
		'BE_AI_Feature'      => 'AI/features/class-ai-feature.php',
		'BE_Vocabulary_Generator' => 'AI/features/class-vocabulary-generator.php',
		'BE_Quiz_Generator'  => 'AI/features/class-quiz-generator.php',
		'BE_Speaking_Scorer' => 'AI/features/class-speaking-scorer.php',
		'BE_Writing_Scorer'  => 'AI/features/class-writing-scorer.php',
		'BE_Feedback_Generator' => 'AI/features/class-feedback-generator.php',
		'BE_Preview_Generator'=> 'AI/features/class-preview-generator.php',
		'BE_OpenAI_Adapter'  => 'AI/adapters/class-openai-compatible-adapter.php',
		// Bible.
		'BE_Bible'           => 'Bible/class-bible-api.php',
		'BE_Verse_Sequencer' => 'Bible/class-verse-sequencer.php',
		// Voice.
		'BE_TTS_Service'     => 'Voice/class-tts-service.php',
		'BE_Whisper'         => 'Voice/class-whisper-service.php',
		// Competition.
		'BE_XP_Manager'      => 'Competition/class-xp-manager.php',
		'BE_League_Manager'  => 'Competition/class-league-manager.php',
		'BE_Championship_Manager' => 'Competition/class-championship-manager.php',
		// Badges.
		'BE_Badge_Manager'   => 'Badges/class-badge-manager.php',
		// Groups.
		'BE_Group_Manager'   => 'Groups/class-group-manager.php',
		// Notifications.
		'BE_Notification_Manager' => 'Notifications/class-notification-manager.php',
		// Users & Lessons.
		'BE_User_Service'    => 'Users/class-user-service.php',
		'BE_Lesson_Service'  => 'Lessons/class-lesson-service.php',
		'BE_Streak'          => 'Lessons/class-streak-service.php',
		// Cron.
		'BE_Cron'            => 'Cron/class-cron.php',
		'BE_MiniApp'         => 'MiniApp/class-router.php',
		// Admin (lives outside includes/).
		'BE_Admin'           => '../admin/class-admin.php',
		// REST controllers (see includes/REST/controllers).
		'BE_REST_Base'       => 'REST/class-rest-base.php',
		'BE_Auth_Controller' => 'REST/controllers/class-auth-controller.php',
		'BE_User_Controller' => 'REST/controllers/class-user-controller.php',
		'BE_Placement_Controller' => 'REST/controllers/class-placement-controller.php',
		'BE_Lesson_Controller' => 'REST/controllers/class-lesson-controller.php',
		'BE_League_Controller'=> 'REST/controllers/class-league-controller.php',
		'BE_Group_Controller'=> 'REST/controllers/class-group-controller.php',
		'BE_Admin_Controller'=> 'REST/controllers/class-admin-controller.php',
	);

	/**
	 * Register the autoloader with SPL.
	 *
	 * @return void
	 */
	public static function register() {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Autoload a BE_ class.
	 *
	 * @param string $class Fully qualified class name.
	 * @return void
	 */
	public static function autoload( $class ) {
		if ( ! isset( self::$map[ $class ] ) ) {
			return;
		}

		$file = BIBLE_TEACHER_DIR . 'includes/' . self::$map[ $class ];

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}

BE_Loader::register();