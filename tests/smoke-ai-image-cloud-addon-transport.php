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
	fwrite( STDERR, "FAIL: {$message}\n" );
	exit( 1 );
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

toolbox_ai_image_cloud_addon_smoke_assert(
	function_exists( 'npcink_cloud_addon_execute_toolbox_image_generation_runtime' ),
	'Cloud Addon exposes the Toolbox image generation transport helper.'
);

$option_name = class_exists( 'Npcink_Cloud_Addon_Settings' )
	? Npcink_Cloud_Addon_Settings::option_name()
	: 'npcink_cloud_addon_settings';
$had_original = false !== get_option( $option_name, false );
$original_settings = get_option( $option_name, false );
$captured_payload = array();
$captured_url = '';

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

			$captured_url = (string) $url;
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
				),
			);
		},
		10,
		3
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
	toolbox_ai_image_cloud_addon_smoke_assert( $response->get_status() >= 200 && $response->get_status() < 300, 'AI image generation REST dispatch succeeds through Cloud Addon transport.' );
	toolbox_ai_image_cloud_addon_smoke_assert( '' !== $captured_url, 'Cloud Addon runtime execute request was sent.' );
	toolbox_ai_image_cloud_addon_smoke_assert( 'toolbox_image_generation' === (string) ( $captured_payload['channel'] ?? '' ), 'Cloud runtime payload uses the Toolbox image generation channel.' );
	toolbox_ai_image_cloud_addon_smoke_assert( 'npcink-cloud/generate-image' === (string) ( $captured_payload['ability_name'] ?? '' ), 'Cloud runtime payload uses the Cloud image generation ability.' );
	toolbox_ai_image_cloud_addon_smoke_assert( 'toolbox_featured_image' === (string) ( $captured_payload['input']['source_surface'] ?? '' ), 'Cloud runtime payload marks the Toolbox featured-image source surface.' );
	toolbox_ai_image_cloud_addon_smoke_assert( 'result_only' === (string) ( $captured_payload['storage_mode'] ?? '' ), 'Cloud runtime payload stays result-only.' );
	toolbox_ai_image_cloud_addon_smoke_assert( false === (bool) ( $captured_payload['policy']['allow_fallback'] ?? true ), 'Cloud runtime payload does not allow provider fallback from Toolbox policy.' );

	$image = $data['images'][0] ?? array();
	toolbox_ai_image_cloud_addon_smoke_assert( is_array( $image ) && 'ai_generated' === (string) ( $image['source_type'] ?? '' ), 'Toolbox returns an AI-generated image candidate.' );
	toolbox_ai_image_cloud_addon_smoke_assert( 'cloud' === (string) ( $image['provider_origin'] ?? '' ), 'Toolbox candidate records Cloud as the provider origin.' );
	toolbox_ai_image_cloud_addon_smoke_assert( 'grok-imagine-image-quality' === (string) ( $image['hosted_profile'] ?? '' ), 'Toolbox candidate preserves the hosted profile.' );
	toolbox_ai_image_cloud_addon_smoke_assert( 'Tongyi-MAI/Z-Image-Turbo' === (string) ( $image['generation_model'] ?? '' ), 'Toolbox candidate preserves the generation model.' );
	toolbox_ai_image_cloud_addon_smoke_assert( 'reviewed_article_context' === (string) ( $image['seo_suggestions']['basis'] ?? '' ), 'Toolbox keeps media SEO suggestions based on reviewed article context.' );
} finally {
	if ( $had_original ) {
		update_option( $option_name, $original_settings, false );
	} else {
		delete_option( $option_name );
	}
}
