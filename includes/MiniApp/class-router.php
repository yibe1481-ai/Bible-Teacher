<?php
/**
 * Mini App router — serves the static Telegram Mini App from a clean URL.
 *
 * Registers a rewrite rule so the single-page app is available at
 *   {home_url}/bible-teacher-app/
 * (configurable via BE_Options telegram.mini_app_url for external hosts).
 * Serving through WordPress keeps the app and its REST API on the same origin,
 * which is required for Telegram initData validation and same-origin cookies.
 *
 * @package Bible_Teacher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BE_MiniApp
 */
class BE_MiniApp {

	/** @var string Base directory that holds the static app. */
	private $base_dir;

	/** @var string Public URL base. */
	private $base_url;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->base_dir = BIBLE_TEACHER_DIR . 'assets/mini-app/';
		$this->base_url = BIBLE_TEACHER_URL . 'assets/mini-app/';
	}

	/**
	 * Register the rewrite rule.
	 *
	 * @return void
	 */
	public function register_rewrite() {
		add_rewrite_rule( '^bible-teacher-app/?$', 'index.php?be_mini_app=1', 'top' );
		add_rewrite_tag( '%be_mini_app%', '1' );
	}

	/**
	 * Intercept the request when our query var is set and serve the app shell.
	 *
	 * @return void
	 */
	public function serve() {
		if ( ! get_query_var( 'be_mini_app' ) ) {
			return;
		}

		if ( ! headers_sent() ) {
			nocache_headers();
			header( 'Content-Type: text/html; charset=UTF-8' );
			header( 'X-Robots-Tag: noindex' );
		}

		// Serve index.html, injecting runtime config for the asset base so the
		// JS resource URLs are correct even if the app is mounted at a sub-path.
		$html = file_get_contents( $this->base_dir . 'index.html' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( false === $html ) {
			status_header( 500 );
			exit( 'Mini App not found.' );
		}

		// Shield the external Telegram CDN (must stay absolute), then absolutize the
		// plugin asset references so relative URLs resolve correctly when mounted
		// at the rewrite path.
		$telegram_src = 'https://telegram.org/js/telegram-web-app.js';
		$html = str_replace( 'src="' . $telegram_src . '"', 'src="__TG_SDK__"', $html );
		$html = str_replace( 'href="styles.css"', 'href="' . esc_url( $this->base_url . 'styles.css' ) . '"', $html );
		$html = str_replace( array( 'href="', 'src="' ), array( 'href="' . esc_url( $this->base_url ), 'src="' . esc_url( $this->base_url ) ), $html );
		$html = str_replace( 'src="__TG_SDK__"', 'src="' . $telegram_src . '"', $html );

		// Feed the runtime config (absolute API base) into config.js immediately.
		$config_json = wp_json_encode( array(
			'API_BASE' => rest_url( 'be/v1' ),
		) );
		$html = str_replace(
			'<script src="' . esc_url( $this->base_url . 'config.js' ) . '"></script>',
			'<script>window.BE_CONFIG=' . $config_json . ';</script><script src="' . esc_url( $this->base_url . 'config.js' ) . '"></script>',
			$html
		);

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- contains HTML shell.
		exit;
	}

	/**
	 * Flush rewrite rules on activation.
	 *
	 * @return void
	 */
	public function flush() {
		$this->register_rewrite();
		flush_rewrite_rules();
	}
}