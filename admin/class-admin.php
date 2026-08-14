<?php
/**
 * WordPress admin panel bootstrap: registers the menu, settings API, and
 * renders views. Kept in the plugin root's `admin/` folder per spec §15.
 *
 * @package Bible_Teacher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BE_Admin
 */
class BE_Admin {

	/**
	 * Option key used by the Settings API.
	 *
	 * @var string
	 */
	protected $option_group = 'bible_teacher_options';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register the top-level menu and submenus.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_menu_page(
			__( 'Bible English', 'bible-teacher' ),
			__( 'Bible English', 'bible-teacher' ),
			'manage_options',
			'bible-english',
			array( $this, 'render_dashboard' ),
			'dashicons-book-alt',
			80
		);

		$submenus = array(
			'dashboard'  => array( __( 'Dashboard', 'bible-teacher' ), array( $this, 'render_dashboard' ) ),
			'users'      => array( __( 'Users', 'bible-teacher' ), array( $this, 'render_users' ) ),
			'lessons'    => array( __( 'Lessons', 'bible-teacher' ), array( $this, 'render_lessons' ) ),
			'leagues'    => array( __( 'Leagues', 'bible-teacher' ), array( $this, 'render_leagues' ) ),
			'settings'   => array( __( 'Settings', 'bible-teacher' ), array( $this, 'render_settings' ) ),
			'system'     => array( __( 'System', 'bible-teacher' ), array( $this, 'render_system' ) ),
		);

		foreach ( $submenus as $slug => $def ) {
			add_submenu_page(
				'bible-english',
				$def[0],
				$def[0],
				'manage_options',
				'bible-english-' . $slug,
				$def[1]
			);
		}
	}

	/**
	 * Register settings sections/fields via the Settings API.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			$this->option_group,
			$this->option_group,
			array( 'sanitize_callback' => array( $this, 'sanitize_options' ) )
		);

		$general = BE_Options::get( 'general' );
		require_once BIBLE_TEACHER_DIR . 'admin/views/class-settings-fields.php';
		$fields = new BE_Settings_Fields( $this->option_group );
		$fields->register_all();
	}

	/**
	 * Sanitize nested options on save.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public function sanitize_options( $input ) {
		if ( ! is_array( $input ) ) {
			return array();
		}
		return BE_Options::sanitize( $input );
	}

	/**
	 * Enqueue admin CSS/JS.
	 *
	 * @param string $hook Current admin page.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( false === strpos( $hook, 'bible-english' ) ) {
			return;
		}
		wp_enqueue_style( 'bible-teacher-admin', BIBLE_TEACHER_URL . 'assets/css/admin.css', array(), BIBLE_TEACHER_VERSION );
	}

	/**
	 * Render the dashboard view.
	 *
	 * @return void
	 */
	public function render_dashboard() {
		require BIBLE_TEACHER_DIR . 'admin/views/dashboard.php';
	}

	/**
	 * Render users view.
	 *
	 * @return void
	 */
	public function render_users() {
		require BIBLE_TEACHER_DIR . 'admin/views/users.php';
	}

	/**
	 * Render lessons view.
	 *
	 * @return void
	 */
	public function render_lessons() {
		require BIBLE_TEACHER_DIR . 'admin/views/lessons.php';
	}

	/**
	 * Render leagues view.
	 *
	 * @return void
	 */
	public function render_leagues() {
		require BIBLE_TEACHER_DIR . 'admin/views/leagues.php';
	}

	/**
	 * Render settings view.
	 *
	 * @return void
	 */
	public function render_settings() {
		require BIBLE_TEACHER_DIR . 'admin/views/settings.php';
	}

	/**
	 * Render system view.
	 *
	 * @return void
	 */
	public function render_system() {
		require BIBLE_TEACHER_DIR . 'admin/views/system.php';
	}
}