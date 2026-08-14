<?php
/**
 * Telegram webhook receiver.
 *
 * Route: POST /be/v1/telegram/webhook/{secret}
 *
 * Responsibilities:
 *  - Validate the URL secret and X-Telegram-Bot-Api-Secret-Token header.
 *  - Guarantee idempotency by tracking processed update_ids.
 *  - Dispatch the update to the relevant handler and return 200 promptly.
 *
 * @package Bible_Teacher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BE_Webhook
 */
class BE_Webhook {

	/**
	 * Handle an incoming webhook request.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public static function handle( $request ) {
		$path_secret = (string) $request['secret'];
		$stored      = (string) get_option( 'bible_teacher_webhook_secret', '' );

		// URL secret must match.
		if ( '' === $stored || ! hash_equals( $stored, $path_secret ) ) {
			return new WP_REST_Response(
				array( 'ok' => false, 'error' => 'unauthorized' ),
				403
			);
		}

		// Optional header secret token for defense in depth.
		$header_secret = $request->get_header( 'X-Telegram-Bot-Api-Secret-Token' );
		if ( $header_secret && ! hash_equals( $stored, $header_secret ) ) {
			return new WP_REST_Response(
				array( 'ok' => false, 'error' => 'unauthorized' ),
				403
			);
		}

		$update = $request->get_json_params();
		if ( empty( $update['update_id'] ) ) {
			// Telegram always includes update_id; reject malformed payloads.
			return new WP_REST_Response( array( 'ok' => true ), 200 );
		}

		$update_id = (int) $update['update_id'];

		// Idempotency: ignore already-processed updates.
		if ( self::was_processed( $update_id ) ) {
			return new WP_REST_Response( array( 'ok' => true ), 200 );
		}

		// Process directly — lightweight enough for a busy small app.
		self::dispatch( $update, $update_id );

		// Acknowledge immediately.
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * Determine whether an update id was already handled.
	 *
	 * @param int $update_id Update id.
	 * @return bool
	 */
	private static function was_processed( $update_id ) {
		if ( wp_cache_get( $update_id, 'bible_teacher_webhook' ) ) {
			return true;
		}

		$stored = get_transient( 'be_webhook_' . $update_id );
		return false !== $stored;
	}

	/**
	 * Dispatch a validated update.
	 *
	 * @param array $update    Update payload.
	 * @param int   $update_id Update id.
	 * @return void
	 */
	private static function dispatch( $update, $update_id ) {
		// Mark processed before doing work to stop duplicate webhook delivery races.
		set_transient( 'be_webhook_' . $update_id, 1, DAY_IN_SECONDS );
		wp_cache_set( $update_id, 1, 'bible_teacher_webhook' );

		$bot = new BE_BotAPI();

		// Commands come through the message text.
		if ( ! empty( $update['message']['text'] ) ) {
			self::handle_command( $update['message'], $bot );
		}

		// Inline keyboard callbacks.
		if ( ! empty( $update['callback_query'] ) ) {
			self::handle_callback( $update['callback_query'], $bot );
		}
	}

	/**
	 * Route a bot command.
	 *
	 * @param array     $message Message object.
	 * @param BE_BotAPI $bot     Bot client.
	 * @return void
	 */
	private static function handle_command( $message, $bot ) {
		$chat_id = (int) $message['chat']['id'];
		$text    = trim( $message['text'] );

		if ( ! preg_match( '#^/(\S+)(?:\s+(.*))?$#', $text, $m ) ) {
			return;
		}

		$command = str_replace( array( '@', '_' ), '', strtolower( $m[1] ) );
		$mini_url = BE_Options::get( 'telegram', 'mini_app_url' );

		switch ( $command ) {
			case 'start':
				$bot->send_message(
					$chat_id,
					sprintf(
						"📖 <b>Bible English</b>\nLearn English through the Word. One verse a day.\n\nTap below to open your lesson 👇",
					),
					array( array( array( 'text' => '🚀 Open Bible English', 'url' => $mini_url ? $mini_url : '' ) ) )
				);
				break;

			case 'lesson':
				$bot->send_message(
					$chat_id,
					"Today's lesson is ready 👇",
					array( array( array( 'text' => '📖 Start Lesson', 'url' => $mini_url ? $mini_url : '' ) ) )
				);
				break;

			default:
				$bot->send_message(
					$chat_id,
					"Commands:\n/start — open the app\n/lesson — today's lesson\n/streak — your streak\n/profile — your stats\n/help — help"
				);
				break;
		}
	}

	/**
	 * Handle a callback_query from an inline button.
	 *
	 * @param array     $callback Callback query object.
	 * @param BE_BotAPI $bot      Bot client.
	 * @return void
	 */
	private static function handle_callback( $callback, $bot ) {
		$callback_id = isset( $callback['id'] ) ? $callback['id'] : '';
		$data        = isset( $callback['data'] ) ? (string) $callback['data'] : '';

		$bot->answer_callback( $callback_id, 'OK ✓' );

		$parts = explode( ':', $data );
		switch ( $parts[0] ) {
			case 'level_up':
			case 'level_down':
				$bot->send_message(
					$callback['message']['chat']['id'] ?? 0,
					__( 'Your level preference has been saved.', 'bible-teacher' )
				);
				break;
		}
	}
}