<?php
/**
 * Telegram Bot API client.
 *
 * Thin wrapper around the Bot API with a consistent response shape, timeout
 * handling, and a helper for the webhook configuration. Never logs the bot
 * token.
 *
 * @package Bible_Teacher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BE_BotAPI
 */
class BE_BotAPI {

	/**
	 * Base Bot API URL.
	 */
	const API_BASE = 'https://api.telegram.org/bot';

	/**
	 * Get the configured bot token.
	 *
	 * @return string
	 */
	public function token() {
		$config = BE_Options::get( 'telegram' );
		return isset( $config['bot_token'] ) ? (string) $config['bot_token'] : '';
	}

	/**
	 * Whether a bot token is configured.
	 *
	 * @return bool
	 */
	public function is_configured() {
		return '' !== $this->token();
	}

	/**
	 * Call a Bot API method.
	 *
	 * @param string $method Method name (e.g. "sendMessage").
	 * @param array  $params Parameters.
	 * @return array|WP_Error Parsed response or WP_Error.
	 */
	public function call( $method, $params = array() ) {
		$token = $this->token();
		if ( '' === $token ) {
			return new WP_Error( 'be_no_token', __( 'Telegram bot token is not configured.', 'bible-teacher' ) );
		}

		$url  = self::API_BASE . $token . '/' . $method;
		$args = array(
			'timeout' => 30,
			'body'    => $params,
		);

		$response = wp_remote_post( $url, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code || empty( $body['ok'] ) ) {
			$desc = isset( $body['description'] ) ? $body['description'] : 'unknown error';
			return new WP_Error( 'be_telegram_' . $code, $desc );
		}

		return isset( $body['result'] ) ? $body['result'] : $body;
	}

	/**
	 * Send a text message with inline keyboard.
	 *
	 * @param int   $chat_id Telegram chat id.
	 * @param string $text   Message text.
	 * @param array $keyboard Optional inline keyboard ([[{text,url|callback_data},...]]).
	 * @return array|WP_Error
	 */
	public function send_message( $chat_id, $text, $keyboard = array() ) {
		$params = array(
			'chat_id'    => $chat_id,
			'text'       => $text,
			'parse_mode' => 'HTML',
		);

		if ( ! empty( $keyboard ) ) {
			$params['reply_markup'] = wp_json_encode( array( 'inline_keyboard' => $keyboard ) );
		}

		return $this->call( 'sendMessage', $params );
	}

	/**
	 * Send an audio file.
	 *
	 * @param int    $chat_id Telegram chat id.
	 * @param string $audio   Upload path to audio file.
	 * @param string $caption Caption.
	 * @return array|WP_Error
	 */
	public function send_audio( $chat_id, $audio, $caption = '' ) {
		$params = array(
			'chat_id'  => $chat_id,
			'audio'    => new CURLFile( $audio ),
			'caption'  => $caption,
			'parse_mode' => 'HTML',
		);

		return $this->call( 'sendAudio', $params );
	}

	/**
	 * Set the webhook for the bot.
	 *
	 * @param string $url       Webhook URL.
	 * @param string $secret    Secret token.
	 * @param string $ip        Optional fixed IP.
	 * @return array|WP_Error
	 */
	public function set_webhook( $url, $secret, $ip = '' ) {
		$params = array(
			'url'         => $url,
			'secret_token'=> $secret,
			'drop_pending_updates' => 'true',
		);
		if ( $ip ) {
			$params['ip_address'] = $ip;
		}
		return $this->call( 'setWebhook', $params );
	}

	/**
	 * Delete the webhook.
	 *
	 * @return array|WP_Error
	 */
	public function delete_webhook() {
		return $this->call( 'deleteWebhook', array( 'drop_pending_updates' => 'true' ) );
	}

	/**
	 * Fetch current webhook info.
	 *
	 * @return array|WP_Error
	 */
	public function webhook_info() {
		return $this->call( 'getWebhookInfo', array() );
	}

	/**
	 * Send a test message to an admin.
	 *
	 * @param int $chat_id Chat id.
	 * @return array|WP_Error
	 */
	public function send_test( $chat_id ) {
		return $this->send_message( $chat_id, sprintf( '✅ <b>Bible Teacher</b> test message. Bot is online!' ) );
	}

	/**
	 * Answer a callback query (for inline buttons).
	 *
	 * @param string $callback_query_id Query id.
	 * @param string $text               Notification text.
	 * @return array|WP_Error
	 */
	public function answer_callback( $callback_query_id, $text = '' ) {
		return $this->call( 'answerCallbackQuery', array(
			'callback_query_id' => $callback_query_id,
			'text'              => $text,
		) );
	}
}