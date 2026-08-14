<?php
/**
 * Content cache for deterministic AI generations keyed by
 * feature + level + verse_reference + variant. Speaking/writing feedback is
 * never cached (unique per user session).
 *
 * @package Bible_Teacher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BE_AI_Cache
 */
class BE_AI_Cache {

	/**
	 * Features allowed to use the cache.
	 *
	 * @var array
	 */
	private static $cacheable = array(
		'vocabulary_generation',
		'quiz_generation',
		'tomorrow_preview',
	);

	/**
	 * Build a cache key.
	 *
	 * @param string $feature Feature key.
	 * @param string $level   Level.
	 * @param string $verse   Verse reference.
	 * @param int    $variant Variant index.
	 * @return string
	 */
	public static function key( $feature, $level, $verse, $variant = 0 ) {
		return md5( implode( '|', array( $feature, $level, strtolower( $verse ), (int) $variant ) ) );
	}

	/**
	 * Whether a feature is cached at all.
	 *
	 * @param string $feature Feature key.
	 * @return bool
	 */
	public static function supports( $feature ) {
		return in_array( $feature, self::$cacheable, true );
	}

	/**
	 * Get cached content, or null when expired/missing.
	 *
	 * @param string $key Cache key.
	 * @return array|null {content, provider, model}
	 */
	public static function get( $key ) {
		global $wpdb;
		$table = $wpdb->prefix . BIBLE_TEACHER_DB_PREFIX . 'ai_content_cache';

		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT content, provider, model FROM {$table} WHERE cache_key = %s LIMIT 1",
			$key
		), ARRAY_A );

		if ( ! $row ) {
			return null;
		}

		return array(
			'content'  => $row['content'],
			'provider' => $row['provider'],
			'model'    => $row['model'],
		);
	}

	/**
	 * Store generated content.
	 *
	 * @param string $key       Cache key.
	 * @param string $feature   Feature key.
	 * @param string $level     Level.
	 * @param string $verse     Verse reference.
	 * @param int    $variant   Variant.
	 * @param string $content   Raw content.
	 * @param string $provider  Provider id.
	 * @param string $model     Model.
	 * @param int    $ttl_days  Cache TTL in days.
	 * @return void
	 */
	public static function set( $key, $feature, $level, $verse, $variant, $content, $provider, $model, $ttl_days = 30 ) {
		global $wpdb;
		$table = $wpdb->prefix . BIBLE_TEACHER_DB_PREFIX . 'ai_content_cache';

		// Upsert: delete then insert to keep unique cache_key intact.
		$wpdb->delete( $table, array( 'cache_key' => $key ), array( '%s' ) );

		$wpdb->insert(
			$table,
			array(
				'cache_key'       => $key,
				'feature'         => $feature,
				'level'           => $level,
				'verse_reference' => $verse,
				'variant'         => (int) $variant,
				'content'         => $content,
				'provider'        => $provider,
				'model'           => $model,
				'created_at'      => current_time( 'mysql', true ),
				'expires_at'      => gmdate( 'Y-m-d H:i:s', time() + $ttl_days * DAY_IN_SECONDS ),
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
		);
	}
}