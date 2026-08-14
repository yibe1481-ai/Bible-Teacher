<?php
/**
 * Text-to-speech service. Provides either a synthesized audio URL via Google
 * Cloud TTS (cached to the uploads dir) or instructs the Mini App to fall back
 * to the browser Web Speech API.
 *
 * @package Bible_Teacher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BE_TTS_Service
 */
class BE_TTS_Service {

	/**
	 * Voice per level.
	 *
	 * @param string $level Level.
	 * @return string
	 */
	protected function voice( $level ) {
		$voice = BE_Options::get( 'voice' );
		if ( 'beginner' === $level ) {
			return isset( $voice['voice_beginner'] ) ? $voice['voice_beginner'] : 'en-US-Standard-C';
		}
		return isset( $voice['voice_default'] ) ? $voice['voice_default'] : 'en-US-Standard-D';
	}

	/**
	 * TTS speed multiplier for a level.
	 *
	 * @param string $level Level.
	 * @return float
	 */
	public function speed( $level ) {
		$learning = BE_Options::get( 'learning' );
		$levels   = $learning['levels'] ?? array();
		if ( isset( $levels[ $level ]['tts_speed'] ) ) {
			return (float) $levels[ $level ]['tts_speed'];
		}
		return ( 'beginner' === $level ) ? 0.8 : 1.0;
	}

	/**
	 * Whether server-side TTS is configured (Google or ElevenLabs).
	 *
	 * @return bool
	 */
	public function server_tts_available() {
		$voice = BE_Options::get( 'voice' );
		$mode  = isset( $voice['tts_provider'] ) ? $voice['tts_provider'] : 'browser';

		if ( 'google' === $mode && ! empty( $voice['google_tts_key'] ) ) {
			return true;
		}
		if ( 'elevenlabs' === $mode && ! empty( $voice['elevenlabs_key'] ) && ! empty( $voice['elevenlabs_enabled'] ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Return synthesized audio payload for a verse.
	 *
	 * Returns an array with either a cached/local audio URL or a flag telling
	 * the frontend to use the browser Web Speech API.
	 *
	 * @param string $text  Verse text.
	 * @param string $level Level.
	 * @return array
	 */
	public function synthesize( $text, $level ) {
		if ( ! $this->server_tts_available() ) {
			return array(
				'mode'         => 'browser',
				'speed'        => $this->speed( $level ),
				'recognizable' => true,
			);
		}

		$voice = BE_Options::get( 'voice' );
		$mode  = $voice['tts_provider'] ?? 'browser';

		if ( 'google' === $mode ) {
			$audio = $this->synthesize_google( $text, $this->voice( $level ), $this->speed( $level ) );
			if ( $audio ) {
				return array(
					'mode'  => 'audio',
					'url'   => $audio,
					'speed' => $this->speed( $level ),
				);
			}
		}

		if ( 'elevenlabs' === $mode ) {
			$audio = $this->synthesize_elevenlabs( $text );
			if ( $audio ) {
				return array(
					'mode'  => 'audio',
					'url'   => $audio,
					'speed' => $this->speed( $level ),
				);
			}
		}

		return array(
			'mode'  => 'browser',
			'speed' => $this->speed( $level ),
		);
	}

	/**
	 * Google Cloud TTS synthesis, cached to the uploads dir.
	 *
	 * @param string $text     Text.
	 * @param string $voice    Voice name.
	 * @param float  $speed    Speaking rate.
	 * @return string|false Public URL to audio.
	 */
	protected function synthesize_google( $text, $voice, $speed ) {
		$config = BE_Options::get( 'voice' );
		$key    = isset( $config['google_tts_key'] ) ? $config['google_tts_key'] : '';

		if ( '' === $key ) {
			return false;
		}

		$cache_dir  = trailingslashit( wp_upload_dir()['basedir'] ) . 'bible-teacher/tts';
		$cache_url  = trailingslashit( wp_upload_dir()['baseurl'] ) . 'bible-teacher/tts';
		$cache_file = $cache_dir . '/' . md5( $text . $voice . $speed ) . '.mp3';

		if ( file_exists( $cache_file ) ) {
			return $cache_url . '/' . basename( $cache_file );
		}

		$body = wp_json_encode( array(
			'input'   => array( 'text' => $text ),
			'voice'   => array( 'languageCode' => 'en-US', 'name' => $voice ),
			'audioConfig' => array(
				'audioEncoding' => 'MP3',
				'speakingRate'  => $speed,
			),
		) );

		$response = wp_remote_post(
			'https://texttospeech.googleapis.com/v1/text:synthesize',
			array(
				'timeout' => 30,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'X-Goog-Api-Key'=> $key,
				),
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $data['audioContent'] ) ) {
			return false;
		}

		if ( ! is_dir( $cache_dir ) ) {
			wp_mkdir_p( $cache_dir );
		}

		$content = base64_decode( $data['audioContent'] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
		if ( file_put_contents( $cache_file, $content ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			return $cache_url . '/' . basename( $cache_file );
		}

		return false;
	}

	/**
	 * ElevenLabs TTS (optional premium fallback).
	 *
	 * @param string $text Text.
	 * @return string|false
	 */
	protected function synthesize_elevenlabs( $text ) {
		$config = BE_Options::get( 'voice' );
		$key    = isset( $config['elevenlabs_key'] ) ? $config['elevenlabs_key'] : '';
		$video  = isset( $config['elevenlabs_voice'] ) ? $config['elevenlabs_voice'] : '';

		if ( '' === $key || '' === $video ) {
			return false;
		}

		$response = wp_remote_post(
			'https://api.elevenlabs.io/v1/text-to-speech/' . rawurlencode( $video ),
			array(
				'timeout' => 30,
				'headers' => array(
					'xi-api-key' => $key,
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode( array( 'text' => $text ) ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		if ( '' === $body ) {
			return false;
		}

		$cache_dir  = trailingslashit( wp_upload_dir()['basedir'] ) . 'bible-teacher/tts';
		$cache_url  = trailingslashit( wp_upload_dir()['baseurl'] ) . 'bible-teacher/tts';
		$cache_file = $cache_dir . '/' . md5( $text . $video ) . '.mp3';

		if ( ! is_dir( $cache_dir ) ) {
			wp_mkdir_p( $cache_dir );
		}

		if ( file_put_contents( $cache_file, $body ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			return $cache_url . '/' . basename( $cache_file );
		}

		return false;
	}
}