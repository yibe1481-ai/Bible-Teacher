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
			// TEMP DIAGNOSTIC — try 4 algorithm combos to determine which one
			// Telegram's client actually uses. Remove when auth confirmed fixed.

			$raw_params = array();
			foreach ( explode( '&', $init_data ) as $pair ) {
				if ( '' === $pair ) { continue; }
				$parts = explode( '=', $pair, 2 );
				$raw_params[ $parts[0] ] = isset( $parts[1] ) ? $parts[1] : '';
			}

			$calc_hash = function ( $src, $skip ) use ( $token ) {
				$p = $src;
				foreach ( $skip as $k ) { unset( $p[ $k ] ); }
				ksort( $p );
				$lines = array();
				foreach ( $p as $k => $v ) { $lines[] = $k . '=' . $v; }
				$check  = implode( "\n", $lines );
				$secret = hash_hmac( 'sha256', 'WebAppData', $token, true );
				return array( bin2hex( hash_hmac( 'sha256', $check, $secret, true ) ), $check );
			};

			list( $h1, $ck1 ) = $calc_hash( $params, array( 'hash' ) );
			list( $h2, $ck2 ) = $calc_hash( $params, array( 'hash', 'signature' ) );
			list( $h3, $ck3 ) = $calc_hash( $raw_params, array( 'hash' ) );
			list( $h4, $ck4 ) = $calc_hash( $raw_params, array( 'hash', 'signature' ) );

			$diag = array(
				'when'     => gmdate( 'c' ),
				'received' => $received,
				'dec_all'  => $h1, 'dec_nosig' => $h2,
				'raw_all'  => $h3, 'raw_nosig' => $h4,
				'ck_dec_nosig' => substr( $ck2, 0, 400 ),
			);
			@file_put_contents( BIBLE_TEACHER_DIR . 'debug-auth.log', gmdate( 'c' ) . ' ' . wp_json_encode( $diag ) . "\n", FILE_APPEND | LOCK_EX ); // phpcs:ignore
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