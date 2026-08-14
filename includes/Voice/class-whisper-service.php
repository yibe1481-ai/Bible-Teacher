<?php
/**
 * Speech-to-text service. Delegates transcription to the configured AI
 * provider's Whisper-compatible endpoint (Groq by default).
 *
 * @package Bible_Teacher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BE_Whisper
 */
class BE_Whisper {

	/**
	 * Transcribe an audio file.
	 *
	 * @param string   $audio_path Local path to the recording.
	 * @param int|null $user_id    Optional local user id.
	 * @return BE_TranscriptionResult
	 */
	public function transcribe( $audio_path, $user_id = null ) {
		$ai = new BE_AI_Manager();
		return $ai->transcribe( $audio_path, $user_id );
	}

	/**
	 * Allowed audio mime types from security config.
	 *
	 * @return array
	 */
	public function allowed_mimes() {
		$security = BE_Options::get( 'security' );
		return $security['allowed_audio_mimes'] ?? array( 'audio/webm', 'audio/mp4', 'audio/ogg', 'audio/wav' );
	}

	/**
	 * Max upload size in bytes (config in MB).
	 *
	 * @return int
	 */
	public function max_upload_bytes() {
		$mb = (int) ( BE_Options::get( 'security', 'max_upload_mb' ) ?: 10 );
		return $mb * MB_IN_BYTES;
	}
}