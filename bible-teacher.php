<?php
/**
 * Plugin Name:       Bible Teacher
 * Plugin URI:        https://github.com/yibe1481-ai/Bible-Teacher
 * Description:       Learn English through the Bible — a gamified Telegram Mini App teaching vocabulary, listening, speaking, reading and writing through daily Bible verses, powered by free AI models.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            Yibe1481
 * Author URI:        https://github.com/yibe1481-ai
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bible-teacher
 * Domain Path:       /languages
 *
 * @package Bible_Teacher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Direct access not permitted.
}

define( 'BIBLE_TEACHER_VERSION', '1.0.0' );
define( 'BIBLE_TEACHER_FILE', __FILE__ );
define( 'BIBLE_TEACHER_DIR', plugin_dir_path( __FILE__ ) );
define( 'BIBLE_TEACHER_URL', plugin_dir_url( __FILE__ ) );
define( 'BIBLE_TEACHER_BASENAME', plugin_basename( __FILE__ ) );
define( 'BIBLE_TEACHER_PLUGIN_SLUG', 'bible-teacher' );
define( 'BIBLE_TEACHER_TABLE_VERSION', '1.0.0' );
define( 'BIBLE_TEACHER_DB_PREFIX', 'be_' );

require_once BIBLE_TEACHER_DIR . 'includes/Core/class-loader.php';

/**
 * Boot the plugin.
 *
 * @return void
 */
function bible_teacher_run() {
	$plugin = new BE_Plugin();
	$plugin->run();
}

bible_teacher_run();
