<?php
/**
 * PSR-4 style autoloader for the Bible Teacher plugin.
 *
 * Maps class names (BE_Prefix) to include/ paths. Several classes live in
 * well-known WordPress conventions (class-{name}.php), so we generate candidate
 * files from the class name rather than requiring a rigid namespace map.
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
		if ( 0 !== strpos( $class, 'BE_' ) ) {
			return;
		}

		$relative = self::class_to_path( $class );
		if ( ! $relative ) {
			return;
		}

		$base = BIBLE_TEACHER_DIR . 'includes/';
		$file = $base . $relative;

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}

	/**
	 * Convert a BE_ class name to a relative file path.
	 *
	 * @param string $class Class name.
	 * @return string|false
	 */
	private static function class_to_path( $class ) {
		// Strip the BE_ prefix.
		$name = substr( $class, 3 );

		// Aliases where the class name does not match its folder name, or
		// is a single segment that the underscore splitter cannot place.
		static $folders = array(
			'AI_Manager'        => 'AI/class-ai-manager',
			'AI_Cache'          => 'AI/class-ai-cache',
			'AI_Logger'         => 'AI/class-ai-logger',
			'OpenAI_Adapter'    => 'AI/adapters/class-openai-compatible-adapter',
			'Options'           => 'Core/class-options',
			'AI_Feature'        => 'AI/features/class-ai-feature',
			'Vocabulary_Generator' => 'AI/features/class-vocabulary-generator',
			'Quiz_Generator'    => 'AI/features/class-quiz-generator',
			'Speaking_Scorer'   => 'AI/features/class-speaking-scorer',
			'Writing_Scorer'    => 'AI/features/class-writing-scorer',
			'Feedback_Generator'=> 'AI/features/class-feedback-generator',
			'Preview_Generator' => 'AI/features/class-preview-generator',
		);

		if ( isset( $folders[ $name ] ) ) {
			return $folders[ $name ] . '.php';
		}

		// Split on underscores to find the first-level directory.
		$parts = explode( '_', $name );
		if ( count( $parts ) < 2 ) {
			return false;
		}

		$top    = array_shift( $parts );
		$folder = self::dir_for_top( $top );

		if ( ! $folder ) {
			return false;
		}

		$file_base = implode( '-', $parts );
		$file_base = strtolower( $file_base );

		return $folder . '/class-' . $file_base . '.php';
	}

	/**
	 * Map the first segment of the class name to a base folder under includes/.
	 *
	 * @param string $top First segment (before first underscore).
	 * @return string|false
	 */
	private static function dir_for_top( $top ) {
		$dirs = array(
			'Plugin'       => 'Core',
			'Loader'       => 'Core',
			'Activator'    => 'Core',
			'Deactivator'  => 'Core',
			'Migrator'     => 'Database',
			'Repository'   => 'Database',
			'REST'         => 'REST',
			'User'         => 'Users',
			'Lesson'       => 'Lessons',
			'Streak'       => 'Lessons',
			'Bible'        => 'Bible',
			'TTSService'   => 'Voice',
			'Whisper'      => 'Voice',
			'XP'           => 'Competition',
			'League'       => 'Competition',
			'Championship' => 'Competition',
			'Badge'        => 'Badges',
			'Group'        => 'Groups',
			'Notification' => 'Notifications',
			'BotAPI'       => 'Telegram',
			'Webhook'      => 'Telegram',
			'Auth'         => 'Telegram',
			'JWT'          => 'Telegram',
			'MiniApp'      => 'Telegram',
			'Security'     => 'Security',
			'Cron'         => 'Cron',
			'AI'           => 'AI',
		);

		if ( isset( $dirs[ $top ] ) ) {
			return $dirs[ $top ];
		}

		return false;
	}
}

BE_Loader::register();
