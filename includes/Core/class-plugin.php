<?php
/**
 * Main plugin container. Wires up activation, autoloader, admin and REST.
 *
 * @package Bible_Teacher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BE_Plugin
 */
class BE_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var BE_Plugin|null
	 */
	protected static $instance = null;

	/**
	 * Set up wp hooks.
	 *
	 * @return void
	 */
	public function run() {
		register_activation_hook( BIBLE_TEACHER_FILE, array( 'BE_Activator', 'activate' ) );
		register_deactivation_hook( BIBLE_TEACHER_FILE, array( 'BE_Deactivator', 'deactivate' ) );

		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'register_mini_app' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'plugins_loaded', array( $this, 'init_services' ) );
		add_action( 'template_redirect', array( $this, 'serve_mini_app' ) );

		if ( is_admin() ) {
			add_action( 'init', array( $this, 'init_admin' ) );
		}
	}

	/**
	 * Load the plugin textdomain.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'bible-teacher', false, dirname( BIBLE_TEACHER_BASENAME ) . '/languages' );
	}

	/**
	 * Register the Mini App rewrite rule.
	 *
	 * @return void
	 */
	public function register_mini_app() {
		( new BE_MiniApp() )->register_rewrite();
	}

	/**
	 * Serve the Mini App when its query var is present.
	 *
	 * @return void
	 */
	public function serve_mini_app() {
		( new BE_MiniApp() )->serve();
	}

	/**
	 * Instantiate background cron manager.
	 *
	 * @return void
	 */
	public function init_services() {
		new BE_Cron();
	}

	/**
	 * Boot admin panel.
	 *
	 * @return void
	 */
	public function init_admin() {
		new BE_Admin();
	}

	/**
	 * Register REST route controllers.
	 *
	 * @return void
	 */
	public function register_rest_routes() {
		$controllers = array(
			'BE_Auth_Controller',
			'BE_User_Controller',
			'BE_Placement_Controller',
			'BE_Lesson_Controller',
			'BE_League_Controller',
			'BE_Group_Controller',
			'BE_Admin_Controller',
		);

		foreach ( $controllers as $controller_class ) {
			if ( class_exists( $controller_class ) ) {
				$controller = new $controller_class();
				$controller->register_routes();
			}
		}
	}
}
