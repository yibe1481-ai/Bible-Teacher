<?php
/**
 * Value objects returned by AI provider adapters.
 *
 * @package Bible_Teacher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Result of a chat completion call.
 */
class BE_AIResponse {

	/**
	 * Raw completion text.
	 *
	 * @var string
	 */
	public $content;

	/**
	 * Provider id that served the request.
	 *
	 * @var string
	 */
	public $provider;

	/**
	 * Model used.
	 *
	 * @var string
	 */
	public $model;

	/**
	 * Latency in milliseconds.
	 *
	 * @var int
	 */
	public $latency_ms;

	/**
	 * Input token usage if reported.
	 *
	 * @var int|null
	 */
	public $input_tokens;

	/**
	 * Output token usage if reported.
	 *
	 * @var int|null
	 */
	public $output_tokens;

	/**
	 * Error message when the request failed (empty on success).
	 *
	 * @var string
	 */
	public $error = '';

	/**
	 * Constructor.
	 *
	 * @param string $content Completion text.
	 */
	public function __construct( $content ) {
		$this->content     = (string) $content;
		$this->latency_ms  = 0;
		$this->input_tokens = null;
		$this->output_tokens = null;
	}

	/**
	 * Try to decode the content as JSON (for feature outputs).
	 *
	 * @return array|null
	 */
	public function json() {
		$decoded = json_decode( $this->content, true );
		return is_array( $decoded ) ? $decoded : null;
	}
}

/**
 * Result of a speech transcription call.
 */
class BE_TranscriptionResult {

	/**
	 * Transcribed text.
	 *
	 * @var string
	 */
	public $text;

	/**
	 * Whether transcription succeeded.
	 *
	 * @var bool
	 */
	public $success;

	/**
	 * Either transcription error.
	 *
	 * @var string
	 */
	public $error;

	/**
	 * Constructor.
	 *
	 * @param string $text    Transcript text.
	 * @param bool   $success Whether success.
	 * @param string $error   Error message.
	 */
	public function __construct( $text = '', $success = true, $error = '' ) {
		$this->text    = $text;
		$this->success = $success;
		$this->error   = $error;
	}
}

/**
 * Result of a provider test_connection() call.
 */
class BE_AIConnectionResult {

	/**
	 * Whether the provider responded.
	 *
	 * @var bool
	 */
	public $ok;

	/**
	 * Human-readable message.
	 *
	 * @var string
	 */
	public $message;

	/**
	 * Latency in ms.
	 *
	 * @var int
	 */
	public $latency_ms;

	/**
	 * Constructor.
	 *
	 * @param bool   $ok        Success flag.
	 * @param string $message   Message.
	 * @param int    $latency_ms Latency.
	 */
	public function __construct( $ok = false, $message = '', $latency_ms = 0 ) {
		$this->ok        = (bool) $ok;
		$this->message   = $message;
		$this->latency_ms = (int) $latency_ms;
	}
}