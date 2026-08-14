<?php
/**
 * Default plugin configuration values.
 *
 * These mirror the admin Settings sections. They are stored once under
 * `bible_teacher_options` at activation and merged with any saved user
 * overrides at runtime.
 *
 * @package Bible_Teacher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return the full default options array.
 *
 * @return array
 */
function bible_teacher_default_options() {
	return array(
		// General.
		'general'              => array(
			'tagline'           => 'Learn English through the Word. One verse a day.',
			'default_language'  => 'en',
			'default_timezone'  => 'UTC',
			'daily_lesson_time' => '07:00',
			'reminder_time'     => '20:00',
			'max_users_per_league' => 30,
			'min_users_per_league' => 10,
			'plugin_enabled'    => 1,
			'maintenance_mode'  => 0,
		),
		// Telegram.
		'telegram'             => array(
			'bot_token'        => '',
			'bot_username'     => '',
			'webhook_url'      => '',
			'mini_app_url'     => '',
			'admin_ids'        => array(),
		),
		// AI — providers live in their own option (bible_teacher_providers).
		'ai'                   => array(
			'global_enabled' => 1,
		),
		// Learning.
		'learning'             => array(
			'vocab_words_per_lesson'    => 5,
			'max_recording_seconds'     => 30,
			'min_recording_seconds'     => 3,
			'quiz_time_limit'           => 0,
			'show_answer_after_wrong'   => 1,
			'allow_lesson_replay'       => 1,
			'streak_grace_hours'        => 2,
			'levels'                    => array(
				'beginner'     => array(
					'xp_multiplier' => 1.2,
					'quiz_options'  => 3,
					'tts_speed'     => 0.8,
					'speak_pass'    => 60,
					'max_words'     => 15,
				),
				'intermediate' => array(
					'xp_multiplier' => 1.0,
					'quiz_options'  => 4,
					'tts_speed'     => 1.0,
					'speak_pass'    => 70,
					'min_words'     => 10,
					'max_words'     => 30,
				),
				'advanced'     => array(
					'xp_multiplier' => 0.9,
					'quiz_options'  => 4,
					'tts_speed'     => 1.0,
					'speak_pass'    => 85,
					'min_words'     => 20,
					'grammar_q'     => 1,
					'crossref_q'    => 1,
				),
			),
			'level_up'                  => array(
				'quiz_accuracy'  => 85,
				'quiz_days'      => 7,
				'speak_score'    => 80,
				'speak_of_last'  => 5,
				'speak_last_n'   => 7,
				'avg_time_min'   => 3,
				'avg_time_days'  => 5,
				'level_down_acc' => 50,
				'level_down_days'=> 5,
			),
		),
		// Competition.
		'competition'          => array(
			'leagues_enabled'      => 1,
			'users_per_league'     => 30,
			'promotion_spots'      => 5,
			'relegation_spots'     => 5,
			'divisions'            => array(
				'genesis',
				'psalms',
				'proverbs',
				'john',
				'romans',
				'revelation',
			),
			'championships_enabled' => 1,
			'grand_championship'    => 1,
			'qualifiers_per_div'    => 3,
			// XP rewards.
			'xp'                     => array(
				'verse'           => 10,
				'vocab'           => 10,
				'listening'       => 10,
				'quiz'            => 15,
				'quiz_perfect'    => 10,
				'speaking_attempt'=> 20,
				'speaking_high'   => 15,
				'writing'         => 15,
				'writing_high'    => 10,
				'streak_per_day'  => 5,
				'streak_max'      => 50,
				'early_bird'      => 5,
				'share'           => 10,
				'referral'        => 50,
			),
		),
		// Voice / TTS.
		'voice'                => array(
			'tts_enabled'        => 1,
			'tts_provider'       => 'browser', // google | elevenlabs | browser.
			'google_tts_key'     => '',
			'voice_beginner'     => 'en-US-Standard-C',
			'voice_default'      => 'en-US-Standard-D',
			'elevenlabs_key'     => '',
			'elevenlabs_voice'   => '',
			'elevenlabs_enabled' => 0,
			'max_audio_mb'       => 5,
			'allowed_mimes'      => array( 'audio/webm', 'audio/mp4', 'audio/ogg', 'audio/wav' ),
		),
		// Bible content.
		'bible'                => array(
			'api_base'          => 'https://bible-api.com/',
			'translation'       => 'kjv',
			'cache_enabled'     => 1,
			'cache_duration'    => 365,
			'beginner_book'     => 'john',
			'intermediate_book' => 'psalms',
			'advanced_book'     => 'romans',
			'delivery_order'    => 'sequential', // sequential | random | curated.
			'auto_tag'          => 1,
			'beginner_max_words'=> 15,
			'advanced_min_words'=> 20,
		),
		// Notifications.
		'notifications'        => array(
			'enabled'          => 1,
			'default_time'     => '07:00',
			'reminder_enabled' => 1,
			'reminder_time'    => '20:00',
			'reminder_min_streak' => 3,
			'weekend_same'     => 1,
			'templates'        => array(
				'daily'     => "📖 Your daily verse is ready!\n\n{book} {chapter}:{verse}\n\"{verse_preview}...\"\n\nTap to start today's lesson 👇",
				'reminder'  => "⏰ Don't break your {streak}-day streak!\n\nToday's lesson takes less than 5 minutes.",
				'league'    => "📊 Your {division} League results:\n\nYou finished #{rank} with {xp} XP.\nNew league starts Monday. Come back strong! 💪",
				'level_up'  => "🎉 You've been crushing your lessons this week!\n\nReady for {next_level} level? Want to move up?",
			),
		),
		// Security.
		'security'             => array(
			'jwt_expiry_minutes' => 60,
			'rate_limit_per_min' => 60,
			'max_upload_mb'      => 10,
			'allowed_audio_mimes'=> array( 'audio/webm', 'audio/mp4', 'audio/ogg', 'audio/wav' ),
			'audit_logging'      => 1,
			'ban_on_abuse'       => 1,
			'abuse_threshold'    => 10,
		),
		// Admin convenience.
		'admin'                => array(
			'uninstall_cleanup' => 1,
		),
	);
}
