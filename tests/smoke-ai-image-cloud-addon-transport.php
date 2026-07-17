<?php
/**
 * Local WordPress smoke for Toolbox AI image generation through Cloud Addon.
 *
 * Run with WP-CLI:
 * wp eval-file tests/smoke-ai-image-cloud-addon-transport.php
 *
 * @package Npcink_Toolbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "FAIL: Run this script through WP-CLI eval-file so WordPress is loaded.\n" );
	exit( 1 );
}

function toolbox_ai_image_cloud_addon_smoke_pass( string $message ): void {
	echo "PASS: {$message}\n";
}

function toolbox_ai_image_cloud_addon_smoke_fail( string $message ): void {
	throw new RuntimeException( $message );
}

function toolbox_ai_image_cloud_addon_smoke_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		toolbox_ai_image_cloud_addon_smoke_fail( $message );
	}

	toolbox_ai_image_cloud_addon_smoke_pass( $message );
}

function toolbox_ai_image_cloud_addon_smoke_admin_user_id(): int {
	$users = get_users(
		array(
			'role'    => 'administrator',
			'number'  => 1,
			'orderby' => 'ID',
			'order'   => 'ASC',
			'fields'  => 'ID',
		)
	);

	return absint( $users[0] ?? 0 );
}

/**
 * Reads one option directly from storage so process-local pre_option filters do
 * not hide accidental persistence.
 *
 * @return mixed
 */
function toolbox_ai_image_cloud_addon_smoke_raw_option( string $option_name ) {
	global $wpdb;

	$value = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
			$option_name
		)
	);

	return null === $value ? false : maybe_unserialize( $value );
}

$option_name = class_exists( 'Npcink_Cloud_Addon_Settings' )
	? Npcink_Cloud_Addon_Settings::option_name()
	: 'npcink_cloud_addon_settings';
$had_original = false !== get_option( $option_name, false );
$original_settings = get_option( $option_name, false );
$captured_payload = array();
$captured_url = '';
$unexpected_urls = array();
$expected_url = 'http://127.0.0.1:8010/v1/runtime/execute';
$http_filter = null;
$settings_filter = null;
$failure_message = '';

try {
	toolbox_ai_image_cloud_addon_smoke_assert(
		function_exists( 'npcink_cloud_addon_execute_toolbox_image_generation_runtime' ),
		'Cloud Addon exposes the Toolbox image generation transport helper.'
	);
	toolbox_ai_image_cloud_addon_smoke_assert(
		class_exists( 'Npcink_Cloud_Addon_Settings' ),
		'Cloud Addon settings API is available.'
	);
	toolbox_ai_image_cloud_addon_smoke_assert(
		class_exists( 'Npcink_Cloud_Credential_Store' ),
		'Cloud Addon authenticated credential envelope API is available.'
	);
	$temporary_envelope = Npcink_Cloud_Credential_Store::encrypt(
		array(
			'site_id' => 'smoke-site',
			'key_id'  => 'smoke-key',
			'secret'  => 'smoke-secret',
		)
	);
	toolbox_ai_image_cloud_addon_smoke_assert( ! is_wp_error( $temporary_envelope ), 'Cloud Addon creates an authenticated in-memory credential envelope.' );
	$temporary_settings = is_array( $original_settings ) ? $original_settings : array();
	$temporary_settings = array_merge(
		$temporary_settings,
		array(
			'base_url'                       => 'http://127.0.0.1:8010',
			'timeout'                        => 8,
			'verified'                       => true,
			'verified_at'                    => gmdate( 'Y-m-d H:i:s' ) . ' UTC',
			'monitoring_enabled'             => false,
			'site_knowledge_delivery_enabled' => false,
			'wordpress_ai_connector_enabled' => true,
			'credential_envelope'             => $temporary_envelope,
		)
	);
	$settings_filter = static function () use ( $temporary_settings ) {
		return $temporary_settings;
	};
	add_filter( 'pre_option_' . $option_name, $settings_filter, PHP_INT_MAX, 3 );
	toolbox_ai_image_cloud_addon_smoke_assert(
		Npcink_Cloud_Addon_Settings::is_verified(),
		'Cloud Addon reads the authenticated temporary credentials without persisting them.'
	);

	$http_filter =
		static function ( $preempt, $parsed_args, $url ) use ( &$captured_payload, &$captured_url, &$unexpected_urls, $expected_url ) {
			if ( $expected_url !== (string) $url ) {
				$unexpected_urls[] = (string) $url;
				return new WP_Error( 'toolbox_ai_image_cloud_addon_smoke_unexpected_http', 'Unexpected outbound HTTP request during no-credit transport smoke.' );
			}

			$captured_url = (string) $url;
			$captured_payload = json_decode( (string) ( $parsed_args['body'] ?? '' ), true );

			$mock_body = wp_json_encode(
				array(
					'status' => 'ready',
					'run_id' => 'run_toolbox_ai_image_smoke',
					'data'   => array(
						'result' => array(
							'status'     => 'ready',
							'model_id'   => 'Tongyi-MAI/Z-Image-Turbo',
							'profile_id' => 'grok-imagine-image-quality',
							'images'     => array(
								array(
									'id'          => 'cloud-addon-smoke-image',
									'regular_url' => 'https://example.test/generated/cloud-addon-smoke.jpg',
									'title'       => 'Prompt-like generated title that should be replaced',
									'alt'         => 'Prompt-like generated alt that should be replaced',
									'prompt'      => 'Create a featured image for a WordPress article.',
								),
							),
						),
					),
				)
			);
			$mock_body = is_string( $mock_body ) ? $mock_body : '{}';

			return array(
				'headers'  => array(
					'content-type'   => 'application/json; charset=utf-8',
					'content-length' => (string) strlen( $mock_body ),
				),
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'body'     => $mock_body,
			);
		};
	add_filter(
		'pre_http_request',
		$http_filter,
		PHP_INT_MAX,
		3
	);
	register_shutdown_function(
		static function () use ( &$unexpected_urls ): void {
			if ( empty( $unexpected_urls ) ) {
				return;
			}

			fwrite( STDERR, 'FAIL: Unexpected outbound HTTP was attempted before shutdown: ' . implode( ', ', array_unique( $unexpected_urls ) ) . "\n" );
			exit( 1 );
		}
	);

	wp_set_current_user( toolbox_ai_image_cloud_addon_smoke_admin_user_id() );

	$request = new WP_REST_Request( 'POST', '/npcink-toolbox/v1/ai/image-generation' );
	$request->set_param( 'prompt', 'Create a featured image for a WordPress article.' );
	$request->set_param( 'aspect_ratio', '16:9' );
	$request->set_param( 'response_format', 'url' );
	$request->set_param( 'n', 1 );
	$request->set_param(
		'media_context',
		array(
			'title'       => 'Cloud Addon transport smoke title',
			'alt'         => '',
			'description' => '',
		)
	);

	$response = rest_do_request( $request );
	if ( is_wp_error( $response ) ) {
		toolbox_ai_image_cloud_addon_smoke_fail( 'REST dispatch returned WP_Error: ' . $response->get_error_code() );
	}

	$data = $response->get_data();
	if ( $response->get_status() < 200 || $response->get_status() >= 300 ) {
		$error_data = is_array( $data ) ? $data : array();
		toolbox_ai_image_cloud_addon_smoke_fail(
			sprintf(
				'AI image generation REST dispatch failed with HTTP %d (%s): %s',
				(int) $response->get_status(),
				sanitize_key( (string) ( $error_data['code'] ?? 'unknown_error' ) ),
				sanitize_text_field( (string) ( $error_data['message'] ?? 'No error message.' ) )
			)
		);
	}
	toolbox_ai_image_cloud_addon_smoke_pass( 'AI image generation REST dispatch succeeds through Cloud Addon transport.' );
	toolbox_ai_image_cloud_addon_smoke_assert( $expected_url === $captured_url, 'Cloud Addon runtime execute request targets the exact local smoke endpoint.' );
	toolbox_ai_image_cloud_addon_smoke_assert( 'toolbox_image_generation' === (string) ( $captured_payload['channel'] ?? '' ), 'Cloud runtime payload uses the Toolbox image generation channel.' );
	toolbox_ai_image_cloud_addon_smoke_assert( 'npcink-cloud/generate-image' === (string) ( $captured_payload['ability_name'] ?? '' ), 'Cloud runtime payload uses the Cloud image generation ability.' );
	toolbox_ai_image_cloud_addon_smoke_assert( 'toolbox_featured_image' === (string) ( $captured_payload['input']['source_surface'] ?? '' ), 'Cloud runtime payload marks the Toolbox featured-image source surface.' );
	toolbox_ai_image_cloud_addon_smoke_assert( 'result_only' === (string) ( $captured_payload['storage_mode'] ?? '' ), 'Cloud runtime payload stays result-only.' );
	toolbox_ai_image_cloud_addon_smoke_assert( false === (bool) ( $captured_payload['policy']['allow_fallback'] ?? true ), 'Cloud runtime payload does not allow provider fallback from Toolbox policy.' );
	toolbox_ai_image_cloud_addon_smoke_assert( array() === $unexpected_urls, 'No unexpected or real outbound HTTP request is attempted.' );

	$image = $data['images'][0] ?? array();
	toolbox_ai_image_cloud_addon_smoke_assert( is_array( $image ) && 'ai_generated' === (string) ( $image['source_type'] ?? '' ), 'Toolbox returns an AI-generated image candidate.' );
	toolbox_ai_image_cloud_addon_smoke_assert( 'cloud' === (string) ( $image['provider_origin'] ?? '' ), 'Toolbox candidate records Cloud as the provider origin.' );
	toolbox_ai_image_cloud_addon_smoke_assert( 'grok-imagine-image-quality' === (string) ( $image['hosted_profile'] ?? '' ), 'Toolbox candidate preserves the hosted profile.' );
	toolbox_ai_image_cloud_addon_smoke_assert( 'Tongyi-MAI/Z-Image-Turbo' === (string) ( $image['generation_model'] ?? '' ), 'Toolbox candidate preserves the generation model.' );
	toolbox_ai_image_cloud_addon_smoke_assert( 'reviewed_article_context' === (string) ( $image['seo_suggestions']['basis'] ?? '' ), 'Toolbox keeps media SEO suggestions based on reviewed article context.' );
} catch ( Throwable $error ) {
	$failure_message = $error->getMessage();
}

$settings_restored = $had_original
	? $original_settings === toolbox_ai_image_cloud_addon_smoke_raw_option( $option_name )
	: false === toolbox_ai_image_cloud_addon_smoke_raw_option( $option_name );
if ( ! $settings_restored ) {
	$failure_message = '' === $failure_message
		? 'Cloud Addon settings changed during the no-credit transport smoke.'
		: $failure_message . ' Cloud Addon settings also changed unexpectedly.';
}
if ( $settings_restored ) {
	toolbox_ai_image_cloud_addon_smoke_pass( 'Cloud Addon stored settings remain byte-for-byte unchanged.' );
}

if ( '' !== $failure_message ) {
	fwrite( STDERR, "FAIL: {$failure_message}\n" );
	exit( 1 );
}

echo "Toolbox AI image Cloud Addon no-credit transport smoke passed.\n";
