<?php
/**
 * Minimal dependency-free HMAC-SHA256 JWT for API session tokens.
 *
 * Kept intentionally small: we only need HS256 signing/verification for
 * server-issued, short-lived session tokens. No third-party libraries are
 * used, so the plugin has no Composer runtime dependency.
 *
 * @package Bible_Teacher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BE_JWT
 */
class BE_JWT {

	/**
	 * Sign a payload into a JWT.
	 *
	 * @param array $payload      Claims.
	 * @param int   $expire_minutes Expiry in minutes.
	 * @return string
	 */
	public static function encode( $payload, $expire_minutes = 60 ) {
		$secret = self::secret();

		$header    = self::base64url( wp_json_encode( array( 'alg' => 'HS256', 'typ' => 'JWT' ) ) );
		$now       = time();
		$claims    = wp_parse_args(
			$payload,
			array(
				'iat' => $now,
				'nbf' => $now,
				'exp' => $now + ( $expire_minutes * MINUTE_IN_SECONDS ),
			)
		);
		$payload   = self::base64url( wp_json_encode( $claims ) );
		$signature = self::sign( $header . '.' . $payload, $secret );

		return $header . '.' . $payload . '.' . $signature;
	}

	/**
	 * Verify and decode a JWT. Returns claims or null when invalid/expired.
	 *
	 * @param string $token JWT string.
	 * @return array|null
	 */
	public static function decode( $token ) {
		if ( ! is_string( $token ) || substr_count( $token, '.' ) !== 2 ) {
			return null;
		}

		$secret = self::secret();
		$parts  = explode( '.', $token );

		$expected = self::sign( $parts[0] . '.' . $parts[1], $secret );
		if ( ! hash_equals( $expected, $parts[2] ) ) {
			return null;
		}

		$payload = json_decode( self::base64url_decode( $parts[1] ), true );
		if ( ! is_array( $payload ) ) {
			return null;
		}

		$now = time();
		if ( isset( $payload['exp'] ) && $payload['exp'] < $now ) {
			return null;
		}
		if ( isset( $payload['nbf'] ) && $payload['nbf'] > $now ) {
			return null;
		}

		return $payload;
	}

	/**
	 * HMAC-SHA256 base64url signature.
	 *
	 * @param string $data   Data to sign.
	 * @param string $secret Secret key.
	 * @return string
	 */
	private static function sign( $data, $secret ) {
		return self::base64url( hash_hmac( 'sha256', $data, $secret, true ) );
	}

	/**
	 * Retrieve the JWT signing secret.
	 *
	 * @return string
	 */
	private static function secret() {
		$secret = get_option( 'bible_teacher_jwt_secret', '' );
		if ( empty( $secret ) ) {
			$secret = wp_generate_password( 48, true, false );
			update_option( 'bible_teacher_jwt_secret', $secret );
		}
		return $secret;
	}

	/**
	 * Base64 URL-safe encode.
	 *
	 * @param string $data Raw data.
	 * @return string
	 */
	private static function base64url( $data ) {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	/**
	 * Base64 URL-safe decode.
	 *
	 * @param string $data Encoded data.
	 * @return string
	 */
	private static function base64url_decode( $data ) {
		return base64_decode( strtr( $data, '-_', '+/' ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
	}
}