<?php
/**
 * Generic OpenAI-compatible provider adapter.
 *
 * Serves Groq, OpenRouter, Google Gemini (OpenAI-compatible endpoint), and
 * arbitrary custom endpoints via the /chat/completions and
 * /audio/transcriptions APIs. Configuration is read from the provider list
 * stored by BE_AI_Manager.
 *
 * @package Bible_Teacher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once BIBLE_TEACHER_DIR . 'includes/AI/interface-ai-provider.php';
require_once BIBLE_TEACHER_DIR . 'includes/AI/class-ai-response.php';

/**
 * Class BE_OpenAI_Adapter
 */
class BE_OpenAI_Adapter implements BE_AI_Provider_Interface {

	/**
	 * Provider config array.
	 *
	 * @var array
	 */
	protected $config;

	/**
	 * Constructor.
	 *
	 * @param array $config Provider configuration.
	 */
	public function __construct( $config ) {
		$this->config = $config;
	}

	/**
	 * Provider id.
	 *
	 * @return string
	 */
	public function get_id() {
		return isset( $this->config['id'] ) ? $this->config['id'] : 'provider';
	}

	/**
	 * Configured models.
	 *
	 * @return array
	 */
	public function get_models() {
		$models = isset( $this->config['models'] ) && is_array( $this->config['models'] )
			? $this->config['models']
			: array();
		if ( ! empty( $this->config['model'] ) ) {
			$models[] = $this->config['model'];
		}
		return array_values( array_unique( $models ) );
	}

	/**
	 * API key.
	 *
	 * @return string
	 */
	protected function api_key() {
		return isset( $this->config['api_key'] ) ? (string) $this->config['api_key'] : '';
	}

	/**
	 * Base URL for chat completions.
	 *
	 * @return string
	 */
	protected function chat_url() {
		$base = isset( $this->config['base_url'] ) ? untrailingslashit( $this->config['base_url'] ) : '';
		if ( substr( $base, -18 ) === '/chat/completions' || substr( $base, -1 ) === '/' ) {
			return $base . ( substr( $base, -1 ) === '/' ? '' : '' );
		}
		return $base . ( substr( $base, -8 ) === '/v1' ? '' : '/v1' ) . '/chat/completions';
	}

	/**
	 * Send a chat completion.
	 *
	 * @param array $messages Messages.
	 * @param array $options  Overrides.
	 * @return BE_AIResponse
	 */
	public function chat( array $messages, array $options = array() ) {
		$start = microtime( true );
		$body  = array(
			'model'       => isset( $options['model'] ) ? $options['model'] : ( $this->config['model'] ?? 'gpt-3.5-turbo' ),
			'messages'    => array_values( $messages ),
			'temperature' => isset( $options['temperature'] ) ? (float) $options['temperature'] : (float) ( $this->config['temperature'] ?? 0.3 ),
			'max_tokens'  => isset( $options['max_tokens'] ) ? (int) $options['max_tokens'] : (int) ( $this->config['max_tokens'] ?? 1000 ),
		);

		$timeout = isset( $options['timeout'] ) ? (int) $options['timeout'] : (int) ( $this->config['timeout'] ?? 30 );

		$response = wp_remote_post(
			$this->chat_url(),
			array(
				'timeout' => $timeout,
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key(),
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		$result = new BE_AIResponse( '' );
		$result->provider = $this->get_id();
		$result->model    = $body['model'];

		if ( is_wp_error( $response ) ) {
			$result->latency_ms = (int) ( ( microtime( true ) - $start ) * 1000 );
			return $result;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		// On non-2xx the body may be an error object.
		if ( $code < 200 || $code >= 300 ) {
			$error_msg = isset( $data['error']['message'] ) ? $data['error']['message'] : '';
			// Reuse WP_Error-style signal by leaving content empty and
			// storing the message so the manager can log a failure.
			$result->latency_ms = (int) ( ( microtime( true ) - $start ) * 1000 );
			$result->content    = ''; // Feature layers treat empty/failed via JSON null.
			$result->error      = $error_msg; // phpcs:ignore Generic.CodeAnalysis -- dynamic property for error surface.
			return $result;
		}

		$content = '';
		if ( isset( $data['choices'][0]['message']['content'] ) ) {
			$content = (string) $data['choices'][0]['message']['content'];
		}

		$result->content       = $content;
		$result->latency_ms    = (int) ( ( microtime( true ) - $start ) * 1000 );
		$result->input_tokens  = isset( $data['usage']['prompt_tokens'] ) ? (int) $data['usage']['prompt_tokens'] : null;
		$result->output_tokens = isset( $data['usage']['completion_tokens'] ) ? (int) $data['usage']['completion_tokens'] : null;

		return $result;
	}

	/**
	 * Transcribe audio via OpenAI-compatible /audio/transcriptions.
	 *
	 * @param string $audio_path Local file path.
	 * @param array  $options    Overrides (model).
	 * @return BE_TranscriptionResult
	 */
	public function transcribe( $audio_path, array $options = array() ) {
		if ( ! file_exists( $audio_path ) ) {
			return new BE_TranscriptionResult( '', false, 'audio file not found' );
		}

		$base = isset( $this->config['base_url'] ) ? untrailingslashit( $this->config['base_url'] ) : '';
		if ( substr( $base, -8 ) !== '/v1' ) {
			$base .= '/v1';
		}
		$url = $base . '/audio/transcriptions';

		$multipart = array(
			'model'    => isset( $options['model'] ) ? $options['model'] : ( $this->config['whisper_model'] ?? 'whisper-large-v3-turbo' ),
			'file'     => new CURLFile( $audio_path ),
		);

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 60,
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key(),
				),
				'body'    => $multipart,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new BE_TranscriptionResult( '', false, $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 200 && $code < 300 && isset( $data['text'] ) ) {
			return new BE_TranscriptionResult( (string) $data['text'], true );
		}

		return new BE_TranscriptionResult( '', false, isset( $data['error'] ) ? wp_json_encode( $data['error'] ) : 'transcription failed' );
	}

	/**
	 * Test connectivity with a minimal chat request.
	 *
	 * @return BE_AIConnectionResult
	 */
	public function test_connection() {
		$start  = microtime( true );
		$result = $this->chat(
			array(
				array(
					'role'    => 'system',
					'content' => 'You are a connectivity test. Reply with exactly: OK',
				),
			),
			array( 'max_tokens' => 5, 'temperature' => 0 )
		);

		$latency = (int) ( ( microtime( true ) - $start ) * 1000 );

		if ( '' !== $result->content ) {
			return new BE_AIConnectionResult( true, 'connected (' . $latency . 'ms)', $latency );
		}
		return new BE_AIConnectionResult( false, 'request failed', $latency );
	}
}