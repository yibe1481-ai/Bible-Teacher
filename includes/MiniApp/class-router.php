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

	/** @var string Cache-busting suffix appended to asset URLs. */
	private $asset_ver;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->base_dir = BIBLE_TEACHER_DIR . 'assets/mini-app/';
		$this->base_url = BIBLE_TEACHER_URL . 'assets/mini-app/';
		// Bump to force browsers/WebViews to re-fetch assets after updates.
		// Use the newest mtime across all app source files so any edit (config,
		// app, screens, styles) invalidates the whole bundle's cache.
		$newest  = 0;
		$checked = array( 'index.html', 'config.js', 'app.js', 'styles.css' );
		$screens = glob( $this->base_dir . 'screens/*.js' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( is_array( $screens ) ) {
			$checked = array_merge( $checked, $screens );
		}
		foreach ( $checked as $file ) {
			$path = $file;
			if ( false === strpos( $file, '/' ) ) {
				$path = $this->base_dir . $file;
			}
			$m = filemtime( $path );
			if ( is_int( $m ) && $m > $newest ) {
				$newest = $m;
			}
		}
		$this->asset_ver = BIBLE_TEACHER_VERSION . '-' . substr( (string) $newest, -6 );
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
	 * Process raw HTML, absolutising relative asset URLs and injecting runtime
	 * config.
	 *
	 * @param string $html Raw HTML.
	 * @return string
	 */
	private function process_html( $html ) {
		$base = rtrim( $this->base_url, '/' );

		// Absolutise only relative src/href values (paths like styles.css,
		// screens/home.js) and append a cache-busting version so updates are
		// picked up immediately. Already-absolute URLs (http://, //, data:,
		// etc.) are left untouched.
		$html = preg_replace_callback(
			'/(<(?:link|script)\s[^>]*?\b(?:href|src)\s*=\s*["\'])([^"\']+)(["\'])/i',
			function ( $m ) use ( $base ) {
				$url = $m[2];
				if ( preg_match( '/^(https?:\/\/|\/\/|data:|#|mailto:|tel:)/i', $url ) ) {
					return $m[0];
				}
				return $m[1] . $base . '/' . ltrim( $url, '/' ) . '?v=' . $this->asset_ver . $m[3];
			},
			$html
		);

		// Inject runtime config before config.js loads (match the versioned URL).
		// Use a root-relative API base so it always shares the page's origin &
		// scheme — avoids cross-origin CORS preflight dropping the Authorization
		// header when the app is served over http but rest_url() returns https.
		$config_json = wp_json_encode( array(
			'API_BASE'    => '/wp-json/be/v1',
		) );
		$config_script = '<script src="' . esc_url( $this->base_url . 'config.js' ) . '?v=' . $this->asset_ver . '"></script>';
		$html = preg_replace(
			'/<script src="[^"]*\/config\.js(?:\?[^"]*)?"><\/script>/',
			'<script>window.BE_CONFIG=' . $config_json . ';</script>' . $config_script,
			$html,
			1
		);

		return $html;
	}

	/**
	 * Intercept the request when our query var is set and serve the app shell.
	 *
	 * Also honours ?be_mini_app=1 via GET for sites using plain permalinks
	 * (no .htaccess rewrite support).
	 *
	 * @return void
	 */
	public function serve() {
		if ( ! get_query_var( 'be_mini_app' ) && ! isset( $_GET['be_mini_app'] ) ) {
			return;
		}

		if ( ! headers_sent() ) {
			nocache_headers();
			header( 'Content-Type: text/html; charset=UTF-8' );
			header( 'X-Robots-Tag: noindex' );
		}

		$html = file_get_contents( $this->base_dir . 'index.html' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( false === $html ) {
			status_header( 500 );
			exit( 'Mini App not found.' );
		}

		echo $this->process_html( $html ); // phpcs:ignore WordPress.Security.EscapeOutput
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