<?php
/**
 * Telegram WebApp initData validation and session issuance.
 *
 * Verifies the HMAC-SHA256 signature of Telegram.WebApp.initData against the
 * bot token, extracts the verified user identity, and never trusts any
 * client-supplied identity/level/XP fields.
 *
 * @package Bible_Teacher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BE_Auth
 */
class BE_Auth {

	/**
	 * Validate raw initData and return the verified user object.
	 *
	 * @param string $init_data Raw initData string from the client.
	 * @return array|null Verified user {id, first_name, username, ...} or null.
	 */
	public static function verify_init_data( $init_data ) {
		if ( ! is_string( $init_data ) || '' === $init_data ) {
			return null;
		}

		$config = BE_Options::get( 'telegram' );
		$token  = isset( $config['bot_token'] ) ? (string) $config['bot_token'] : '';
		if ( '' === $token ) {
			return null;
		}

		// Split into key=>value pairs, URL-decoding each key & value once
		// (mirrors Telegram's documented algorithm; do not decode a second time).
		$params = array();
		foreach ( explode( '&', $init_data ) as $pair ) {
			if ( '' === $pair ) {
				continue;
			}
			$parts          = explode( '=', $pair, 2 );
			$key            = urldecode( $parts[0] );
			$params[ $key ] = isset( $parts[1] ) ? urldecode( $parts[1] ) : '';
		}
		if ( empty( $params['hash'] ) || empty( $params['user'] ) ) {
			@file_put_contents( BIBLE_TEACHER_DIR . 'debug-auth.log', gmdate( 'c' ) . ' FAIL missing_fields hash=' . ( empty( $params['hash'] ) ? 'empty' : 'ok' ) . ' user=' . ( empty( $params['user'] ) ? 'empty' : 'ok' ) . ' keys=' . ( wp_json_encode( array_keys( $params ) ) ) . "\n", FILE_APPEND | LOCK_EX ); // phpcs:ignore
			return null;
		}

		$received = $params['hash'];
		unset( $params['hash'] );

		// Build the data_check_string: remaining fields sorted by key, formatted
		// as "key=value" lines joined by newlines.
		$sorted = $params;
		ksort( $sorted );
		$lines = array();
		foreach ( $sorted as $key => $value ) {
			$lines[] = $key . '=' . $value;
		}
		$check_string = implode( "\n", $lines );

		// Mini App initData is signed with the "WebAppData" secret key — an HMAC
		// of the bot token — NOT the SHA256(bot_token) used by the classic
		// Telegram Login Widget.
		$secret_key = hash_hmac( 'sha256', 'WebAppData', $token, true );
		$calculated = bin2hex( hash_hmac( 'sha256', $check_string, $secret_key, true ) );

		if ( ! hash_equals( $calculated, $received ) ) {
				// TEMP DIAGNOSTIC — try both approaches and log everything so we
				// can reverse-engineer which algorithm Telegram actually uses.
				// Remove after the auth issue is confirmed fixed.

				// Build the raw (undecoded) check string alongside the decoded one.
				$raw_params = array();
				foreach ( explode( '&', $init_data ) as $pair ) {
					if ( '' === $pair ) { continue; }
					$parts = explode( '=', $pair, 2 );
					$key   = $parts[0]; // keep raw — no urldecode
					$raw_params[ $key ] = isset( $parts[1] ) ? $parts[1] : '';
				}
				if ( isset( $raw_params['hash'] ) ) {
					unset( $raw_params['hash'] );
				}
				ksort( $raw_params );
				$raw_lines = array();
				foreach ( $raw_params as $k => $v ) {
					$raw_lines[] = $k . '=' . $v;
				}
				$check_raw = implode( "\n", $raw_lines );

				$hash_decoded = $calculated;
				$hash_raw     = bin2hex( hash_hmac( 'sha256', $check_raw, $secret_key, true ) );

				$diag = array(
					'when'         => gmdate( 'c' ),
					'keys'         => array_keys( $sorted ),
					'check_decoded' => substr( $check_string, 0, 600 ),
					'check_raw'    => substr( $check_raw, 0, 600 ),
					'received'     => $received,
					'hash_decoded' => $hash_decoded,
					'hash_raw'     => $hash_raw,
				);
				@file_put_contents( BIBLE_TEACHER_DIR . 'debug-auth.log', gmdate( 'c' ) . ' ' . wp_json_encode( $diag ) . "\n", FILE_APPEND | LOCK_EX ); // phpcs:ignore
				return null;
			}

		// Auth date must be recent (within 24h) to avoid replay abuse.
		if ( ! empty( $params['auth_date'] ) ) {
			$auth_date = (int) $params['auth_date'];
			if ( time() - $auth_date > DAY_IN_SECONDS ) {
				return null;
			}
		}

		$user = json_decode( wp_unslash( $params['user'] ), true );
		if ( ! is_array( $user ) || empty( $user['id'] ) ) {
			return null;
		}

		return $user;
	}

	/**
	 * Resolve a verified Telegram user to a local user row, creating it if
	 * necessary.
	 *
	 * @param array $telegram_user Verified user from initData.
	 * @return array|WP_Error Local user row.
	 */
	public static function resolve_user( $telegram_user ) {
		$repo = new BE_User_Service();
		$user = $repo->find_by_telegram_id( $telegram_user['id'] );

		if ( ! $user ) {
			$user = $repo->create(
				array(
					'telegram_user_id' => (int) $telegram_user['id'],
					'telegram_username'=> isset( $telegram_user['username'] ) ? $telegram_user['username'] : '',
					'first_name'       => isset( $telegram_user['first_name'] ) ? $telegram_user['first_name'] : 'User',
					'last_name'        => isset( $telegram_user['last_name'] ) ? $telegram_user['last_name'] : '',
					'language_code'    => isset( $telegram_user['language_code'] ) ? $telegram_user['language_code'] : 'en',
				)
			);
		} else {
			$repo->touch( $user['id'] );
		}

		return $user;
	}

	/**
	 * Issue a session JWT for a local user.
	 *
	 * @param int $user_id Local user id.
	 * @return string
	 */
	public static function issue_token( $user_id ) {
		$config = BE_Options::get( 'security' );
		$expiry = (int) ( $config['jwt_expiry_minutes'] ?? 60 );

		return BE_JWT::encode(
			array(
				'sub' => 'telegram',
				'uid' => (int) $user_id,
			),
			$expiry
		);
	}

	/**
	 * Parse the Bearer token from a request.
	 *
	 * @param string $header Raw Authorization header.
	 * @return string
	 */
	public static function bearer_token( $header ) {
		if ( preg_match( '/^Bearer\s+(.+)$/i', $header, $m ) ) {
			return trim( $m[1] );
		}
		return '';
	}
}