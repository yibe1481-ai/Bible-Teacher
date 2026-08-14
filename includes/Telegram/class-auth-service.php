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

		$secret_key = hash( 'sha256', $token, true );

		$params = array();
		parse_str( $init_data, $params );
		if ( empty( $params['hash'] ) || empty( $params['user'] ) ) {
			return null;
		}

		$received = $params['hash'];
		unset( $params['hash'] );

		// Build the data_check_string in sorted order, with % digits unescaped.
		ksort( $params );
		$check_string = '';
		foreach ( $params as $key => $value ) {
			if ( is_array( $value ) ) {
				continue;
			}
			$check_string .= $key . '=' . rawurldecode( $value ) . "\n";
		}
		$check_string = rtrim( $check_string, "\n" );

		$calculated = bin2hex( hash_hmac( 'sha256', $check_string, $secret_key, true ) );

		if ( ! hash_equals( $calculated, $received ) ) {
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