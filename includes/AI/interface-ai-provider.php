<?php
/**
 * Contract for AI providers (chat completion + audio transcription).
 *
 * Each provider adapter implements this interface. Feature logic depends only
 * on this interface, so swapping providers or adding new ones never touches
 * the feature classes.
 *
 * @package Bible_Teacher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface BE_AI_Provider_Interface
 */
interface BE_AI_Provider_Interface {

	/**
	 * Send a chat completion request.
	 *
	 * @param array $messages  OpenAI-style messages [['role','content'],...].
	 * @param array $options   Overrides for temperature, max_tokens, timeout, model.
	 * @return BE_AIResponse
	 */
	public function chat( array $messages, array $options = array() );

	/**
	 * Transcribe an audio file.
	 *
	 * @param string $audio_path Local path to audio file.
	 * @param array  $options    Overrides (model).
	 * @return BE_TranscriptionResult
	 */
	public function transcribe( $audio_path, array $options = array() );

	/**
	 * Test connectivity and model availability.
	 *
	 * @return BE_AIConnectionResult
	 */
	public function test_connection();

	/**
	 * Get the provider id / label.
	 *
	 * @return string
	 */
	public function get_id();

	/**
	 * Get configured model ids.
	 *
	 * @return array
	 */
	public function get_models();
}