<?php
/**
 * Real local WordPress smoke for Toolbox image-source candidates through Cloud Addon.
 *
 * Run with WP-CLI:
 * wp eval-file tests/smoke-image-source-cloud-addon-transport.php
 *
 * @package Npcink_Toolbox
 */

use Npcink_Toolbox\Provider_Client;
use Npcink_Toolbox\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "FAIL: Run this script through WP-CLI eval-file so WordPress is loaded.\n" );
	exit( 1 );
}

function toolbox_image_source_cloud_addon_real_smoke_pass( string $message ): void {
	echo "PASS: {$message}\n";
}

function toolbox_image_source_cloud_addon_real_smoke_fail( string $message ): void {
	fwrite( STDERR, "FAIL: {$message}\n" );
	exit( 1 );
}

function toolbox_image_source_cloud_addon_real_smoke_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		toolbox_image_source_cloud_addon_real_smoke_fail( $message );
	}

	toolbox_image_source_cloud_addon_real_smoke_pass( $message );
}

toolbox_image_source_cloud_addon_real_smoke_assert(
	function_exists( 'npcink_cloud_addon_execute_toolbox_image_source_runtime' ),
	'Cloud Addon exposes the Toolbox image-source transport helper.'
);
toolbox_image_source_cloud_addon_real_smoke_assert(
	class_exists( Provider_Client::class ) && class_exists( Settings::class ),
	'Toolbox provider client is available in the local WordPress site.'
);

$client = new Provider_Client( new Settings() );
$result = $client->image_candidates(
	'WordPress editorial workflow',
	array(
		'provider'     => 'auto',
		'per_page'     => 3,
		'latency_mode' => 'fast_first',
		'purpose'      => 'featured_image',
	)
);

if ( is_wp_error( $result ) ) {
	$code    = $result->get_error_code();
	$message = $result->get_error_message();
	if ( 'cloud_image_source_provider_not_configured' === $code ) {
		toolbox_image_source_cloud_addon_real_smoke_fail( "Cloud provider config is incomplete: {$code} - {$message}" );
	}

	toolbox_image_source_cloud_addon_real_smoke_fail( "Image-source search returned WP_Error: {$code} - {$message}" );
}

$images = (array) ( $result['images'] ?? array() );
$first  = is_array( $images[0] ?? null ) ? $images[0] : array();

toolbox_image_source_cloud_addon_real_smoke_assert( is_array( $result ), 'Image-source search returns a structured artifact.' );
toolbox_image_source_cloud_addon_real_smoke_assert( 'image_source_candidates' === (string) ( $result['artifact_type'] ?? '' ), 'Image-source artifact type is image_source_candidates.' );
toolbox_image_source_cloud_addon_real_smoke_assert( false === (bool) ( $result['direct_wordpress_write'] ?? true ), 'Image-source artifact does not authorize WordPress writes.' );
toolbox_image_source_cloud_addon_real_smoke_assert( false === (bool) ( $result['handoff']['direct_wordpress_write'] ?? true ), 'Image-source handoff blocks direct WordPress writes.' );
toolbox_image_source_cloud_addon_real_smoke_assert( count( $images ) > 0, 'Image-source search returns at least one real Cloud-managed candidate.' );
toolbox_image_source_cloud_addon_real_smoke_assert(
	'' !== (string) ( $first['regular_url'] ?? $first['url'] ?? $first['download_url'] ?? $first['thumbnail_url'] ?? '' ),
	'Image-source candidate preserves an inspectable image URL.'
);

