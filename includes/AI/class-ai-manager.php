<?php
/**
 * AI orchestration: provider registry, per-feature dispatch, fallback chain,
 * daily usage limits, and logging.
 *
 * Providers are stored as a list in the `bible_teacher_providers` option.
 * Feature → provider/model mapping lives in `bible_teacher_ai_features`.
 *
 * @package Bible_Teacher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once BIBLE_TEACHER_DIR . 'includes/AI/interface-ai-provider.php';
require_once BIBLE_TEACHER_DIR . 'includes/AI/class-ai-response.php';
require_once BIBLE_TEACHER_DIR . 'includes/AI/adapters/class-openai-compatible-adapter.php';

/**
 * Class BE_AI_Manager
 */
class BE_AI_Manager {

	/**
	 * Cached resolved provider adapters keyed by provider id.
	 *
	 * @var array
	 */
	protected $adapters = array();

	/**
	 * Get all stored provider configs.
	 *
	 * @return array
	 */
	public function providers() {
		$providers = get_option( 'bible_teacher_providers', array() );
		return is_array( $providers ) ? $providers : array();
	}

	/**
	 * Get an adapter for a provider id, caching constructed instances.
	 *
	 * @param string $id Provider id.
	 * @return BE_OpenAI_Adapter|null
	 */
	public function adapter( $id ) {
		if ( isset( $this->adapters[ $id ] ) ) {
			return $this->adapters[ $id ];
		}

		foreach ( $this->providers() as $provider ) {
			if ( ( $provider['id'] ?? '' ) === $id && ! empty( $provider['enabled'] ) ) {
				$adapter                  = new BE_OpenAI_Adapter( $provider );
				$this->adapters[ $id ]    = $adapter;
				return $adapter;
			}
		}

		return null;
	}

	/**
	 * Per-feature routing configuration.
	 *
	 * @param string $feature Feature key.
	 * @return array Bailout defaults when unconfigured.
	 */
	public function feature_config( $feature ) {
		$features = get_option( 'bible_teacher_ai_features', array() );
		$feature  = ( is_array( $features ) && isset( $features[ $feature ] ) ) ? $features[ $feature ] : array();

		return wp_parse_args( $feature, array(
			'enabled'             => 1,
			'primary_provider'    => '',
			'primary_model'       => '',
			'temperature'         => 0.3,
			'max_tokens'          => 800,
			'timeout'             => 25,
			'fallback_provider'   => '',
			'fallback_model'      => '',
			'cache_duration'      => 30,
			'cache_variants'      => 5,
		) );
	}

	/**
	 * Whether AI is globally and per-feature enabled.
	 *
	 * @param string $feature Feature key.
	 * @return bool
	 */
	public function enabled( $feature ) {
		$global = (int) BE_Options::get( 'ai', 'global_enabled' );
		$config = $this->feature_config( $feature );
		return $global && (int) $config['enabled'];
	}

	/**
	 * Daily count for an (provider, feature) pair, used for free-tier limits.
	 *
	 * @param string $provider Provider id.
	 * @param string $feature  Feature key.
	 * @return int
	 */
	public function usage_today( $provider, $feature ) {
		$usage = get_transient( 'be_ai_usage_' . md5( $provider . $feature . gmdate( 'Y-m-d' ) ) );
		return $usage ? (int) $usage : 0;
	}

	/**
	 * Increment usage for a provider/feature.
	 *
	 * @param string $provider Provider id.
	 * @param string $feature  Feature key.
	 * @return void
	 */
	protected function bump_usage( $provider, $feature ) {
		$key = 'be_ai_usage_' . md5( $provider . $feature . gmdate( 'Y-m-d' ) );
		set_transient( $key, $this->usage_today( $provider, $feature ) + 1, DAY_IN_SECONDS );
	}

	/**
	 * Send a chat request for a feature with fallback + caching.
	 *
	 * @param string $feature   Feature key.
	 * @param array  $messages  Messages.
	 * @param array  $opts      Additional: level, verse, variant, user_id, cache.
	 * @return BE_AIResponse
	 */
	public function chat( $feature, array $messages, array $opts = array() ) {
		$config   = $this->feature_config( $feature );
		$level    = isset( $opts['level'] ) ? $opts['level'] : 'beginner';
		$verse    = isset( $opts['verse'] ) ? $opts['verse'] : '';
		$variant  = isset( $opts['variant'] ) ? (int) $opts['variant'] : 0;
		$user_id  = isset( $opts['user_id'] ) ? (int) $opts['user_id'] : null;

		// Cache lookup.
		if ( isset( $opts['cache'] ) && $opts['cache'] && BE_AI_Cache::supports( $feature ) ) {
			$key = BE_AI_Cache::key( $feature, $level, $verse, $variant );
			$hit = BE_AI_Cache::get( $key );
			if ( $hit ) {
				$response = new BE_AIResponse( $hit['content'] );
				$response->provider = $hit['provider'];
				$response->model    = $hit['model'];

				// Log cache hit as success without an API call.
				BE_AI_Logger::log( $feature, $hit['provider'], $hit['model'], $user_id, 'success', array(
					'input_tokens' => 0, 'output_tokens' => 0, 'latency_ms' => 0,
				) );
				return $response;
			}
		}

		$attempts = array();
		if ( $config['primary_provider'] ) {
			$attempts[] = array( 'provider' => $config['primary_provider'], 'model' => $config['primary_model'] );
		}
		if ( $config['fallback_provider'] && $config['fallback_provider'] !== $config['primary_provider'] ) {
			$attempts[] = array( 'provider' => $config['fallback_provider'], 'model' => $config['fallback_model'] );
		}

		// If no providers configured, degrade to empty response.
		if ( empty( $attempts ) ) {
			BE_AI_Logger::log( $feature, 'none', '', $user_id, 'failure', array( 'error_code' => 'no_provider' ) );
			return new BE_AIResponse( '' );
		}

		foreach ( $attempts as $attempt ) {
			$adapter = $this->adapter( $attempt['provider'] );
			if ( ! $adapter ) {
				continue;
			}

			// Respect per-provider daily limit.
			$provider_config = $this->provider_config( $attempt['provider'] );
			$daily_limit     = (int) ( $provider_config['daily_limit'] ?? 1400 );
			if ( $daily_limit > 0 && $this->usage_today( $attempt['provider'], $feature ) >= (int) ( $daily_limit * 0.8 ) ) {
				BE_AI_Logger::log( $feature, $attempt['provider'], $attempt['model'], $user_id, 'fallback', array( 'error_code' => 'near_limit' ) );
				continue;
			}

			$response = $adapter->chat( $messages, array(
				'model'       => $attempt['model'] ? $attempt['model'] : $provider_config['model'] ?? '',
				'temperature' => $config['temperature'],
				'max_tokens'  => $config['max_tokens'],
				'timeout'     => $config['timeout'],
			) );

			$this->bump_usage( $attempt['provider'], $feature );

			if ( '' !== $response->content ) {
				$status = ( 0 === array_search( $attempt, $attempts, true ) ) ? 'success' : 'fallback';
				BE_AI_Logger::log( $feature, $attempt['provider'], $response->model, $user_id, $status, array(
					'input_tokens'  => $response->input_tokens,
					'output_tokens' => $response->output_tokens,
					'latency_ms'    => $response->latency_ms,
				) );

				if ( isset( $opts['cache'] ) && $opts['cache'] && BE_AI_Cache::supports( $feature ) ) {
					BE_AI_Cache::set(
						BE_AI_Cache::key( $feature, $level, $verse, $variant ),
						$feature, $level, $verse, $variant,
						$response->content, $response->provider, $response->model,
						(int) $config['cache_duration']
					);
				}
				return $response;
			}

			BE_AI_Logger::log( $feature, $attempt['provider'], $response->model, $user_id, 'failure', array(
				'error_code' => $response->error ?? 'empty_response',
			) );
		}

		return new BE_AIResponse( '' );
	}

	/**
	 * Transcribe audio (used for speaking exercises).
	 *
	 * @param string $audio_path Local audio path.
	 * @param int|null $user_id User id.
	 * @return BE_TranscriptionResult
	 */
	public function transcribe( $audio_path, $user_id = null ) {
		$config = $this->feature_config( 'speaking_score' );

		$attempts = array();
		if ( $config['primary_provider'] ) {
			$attempts[] = array( 'provider' => $config['primary_provider'], 'model' => $config['primary_model'] );
		}
		if ( $config['fallback_provider'] && $config['fallback_provider'] !== $config['primary_provider'] ) {
			$attempts[] = array( 'provider' => $config['fallback_provider'], 'model' => $config['fallback_model'] );
		}

		// Default to whisper on the primary provider.
		foreach ( $attempts as $attempt ) {
			$adapter = $this->adapter( $attempt['provider'] );
			if ( ! $adapter ) {
				continue;
			}
			$result = $adapter->transcribe( $audio_path, array(
				'model' => $attempt['model'] ? $attempt['model'] : 'whisper-large-v3-turbo',
			) );
			if ( $result->success ) {
				return $result;
			}
		}

		return new BE_TranscriptionResult( '', false, 'transcription unavailable' );
	}

	/**
	 * Raw provider config for a specific id.
	 *
	 * @param string $id Provider id.
	 * @return array
	 */
	public function provider_config( $id ) {
		foreach ( $this->providers() as $provider ) {
			if ( ( $provider['id'] ?? '' ) === $id ) {
				return $provider;
			}
		}
		return array();
	}
}