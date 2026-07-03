<?php
/**
 * Local WordPress smoke for Toolbox article audio through Cloud Addon.
 *
 * Run with WP-CLI:
 * wp eval-file tests/smoke-audio-cloud-addon-transport.php
 *
 * @package Npcink_Toolbox
 */

use Npcink_Toolbox\Provider_Client;
use Npcink_Toolbox\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "FAIL: Run this script through WP-CLI eval-file so WordPress is loaded.\n" );
	exit( 1 );
}

function toolbox_audio_cloud_addon_smoke_pass( string $message ): void {
	echo "PASS: {$message}\n";
}

function toolbox_audio_cloud_addon_smoke_fail( string $message ): void {
	fwrite( STDERR, "FAIL: {$message}\n" );
	exit( 1 );
}

function toolbox_audio_cloud_addon_smoke_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		toolbox_audio_cloud_addon_smoke_fail( $message );
	}

	toolbox_audio_cloud_addon_smoke_pass( $message );
}

toolbox_audio_cloud_addon_smoke_assert(
	function_exists( 'npcink_cloud_addon_execute_toolbox_audio_generation_runtime' ),
	'Cloud Addon exposes the Toolbox audio generation transport helper.'
);

$option_name       = class_exists( 'Npcink_Cloud_Addon_Settings' )
	? Npcink_Cloud_Addon_Settings::option_name()
	: 'npcink_cloud_addon_settings';
$had_original      = false !== get_option( $option_name, false );
$original_settings = get_option( $option_name, false );
$captured_payload  = array();
$captured_url      = '';

try {
	update_option(
		$option_name,
		array(
			'base_url'                       => 'http://127.0.0.1:8010',
			'site_id'                        => 'smoke-site',
			'key_id'                         => 'smoke-key',
			'secret'                         => 'smoke-secret',
			'timeout'                        => 8,
			'verified'                       => true,
			'verified_at'                    => gmdate( 'Y-m-d H:i:s' ) . ' UTC',
			'wordpress_ai_connector_enabled' => true,
		),
		false
	);

	add_filter(
		'pre_http_request',
		static function ( $preempt, $parsed_args, $url ) use ( &$captured_payload, &$captured_url ) {
			if ( false === strpos( (string) $url, '/v1/runtime/execute' ) ) {
				return $preempt;
			}

			$captured_url     = (string) $url;
			$captured_payload = json_decode( (string) ( $parsed_args['body'] ?? '' ), true );

			return array(
				'headers'  => array(),
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'body'     => wp_json_encode(
					array(
						'status' => 'ready',
						'run_id' => 'run_toolbox_audio_smoke',
						'data'   => array(
							'result' => array(
								'status'      => 'ready',
								'model_id'    => 'speech-2.8-turbo',
								'profile_id'  => 'audio.narration.default',
								'voice_id'    => 'voice_editorial',
								'audios'      => array(
									array(
										'id'               => 'cloud-addon-smoke-audio',
										'url'              => 'https://example.test/generated/cloud-addon-smoke.mp3',
										'format'           => 'mp3',
										'duration_seconds' => 42,
									),
								),
							),
						),
					)
				),
			);
		},
		10,
		3
	);

	$client = new Provider_Client( new Settings() );
	$result = $client->run_audio_generation(
		array(
			'intent'            => 'article_audio_summary',
			'summary_text'      => 'This is a reviewed spoken summary script for a WordPress article.',
			'script'            => 'This is a reviewed spoken summary script for a WordPress article.',
			'format'            => 'mp3',
			'voice_id'          => 'voice_editorial',
			'user_instruction'  => 'Use a calm editorial tone.',
			'audio_preferences' => array(
				'tone' => 'calm',
			),
		)
	);

	if ( is_wp_error( $result ) ) {
		toolbox_audio_cloud_addon_smoke_fail( 'Audio generation returned WP_Error: ' . $result->get_error_code() );
	}

	toolbox_audio_cloud_addon_smoke_assert( '' !== $captured_url, 'Cloud Addon runtime execute request was sent.' );
	toolbox_audio_cloud_addon_smoke_assert( 'toolbox_audio_generation' === (string) ( $captured_payload['channel'] ?? '' ), 'Cloud runtime payload uses the Toolbox audio generation channel.' );
	toolbox_audio_cloud_addon_smoke_assert( 'npcink-toolbox/generate-audio' === (string) ( $captured_payload['ability_name'] ?? '' ), 'Cloud runtime payload uses the Toolbox audio generation ability.' );
	toolbox_audio_cloud_addon_smoke_assert( 'toolbox_article_audio_candidates' === (string) ( $captured_payload['input']['source_surface'] ?? '' ), 'Cloud runtime payload marks the Toolbox article-audio source surface.' );
	toolbox_audio_cloud_addon_smoke_assert( 'result_only' === (string) ( $captured_payload['storage_mode'] ?? '' ), 'Cloud runtime payload stays result-only.' );
	toolbox_audio_cloud_addon_smoke_assert( false === (bool) ( $captured_payload['policy']['allow_fallback'] ?? true ), 'Cloud runtime payload does not allow provider fallback from Toolbox policy.' );
	toolbox_audio_cloud_addon_smoke_assert( false === (bool) ( $captured_payload['input']['review']['direct_wordpress_write'] ?? true ), 'Cloud runtime payload explicitly blocks direct WordPress writes.' );

	toolbox_audio_cloud_addon_smoke_assert( is_array( $result ) && 'audio_generation_candidates' === (string) ( $result['artifact_type'] ?? '' ), 'Toolbox returns an audio candidate artifact.' );
	toolbox_audio_cloud_addon_smoke_assert( 'suggestion_only' === (string) ( $result['write_posture'] ?? '' ), 'Toolbox audio result remains suggestion-only.' );
	toolbox_audio_cloud_addon_smoke_assert( false === (bool) ( $result['direct_wordpress_write'] ?? true ), 'Toolbox audio result does not authorize WordPress writes.' );
	toolbox_audio_cloud_addon_smoke_assert( ! empty( $result['audios'][0]['url'] ), 'Toolbox returns a playable audio candidate URL.' );
} finally {
	if ( $had_original ) {
		update_option( $option_name, $original_settings, false );
	} else {
		delete_option( $option_name );
	}
}
