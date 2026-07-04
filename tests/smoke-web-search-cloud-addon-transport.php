<?php
/**
 * Real local WordPress smoke for Toolbox web search through Cloud Addon.
 *
 * Run with WP-CLI:
 * wp eval-file tests/smoke-web-search-cloud-addon-transport.php
 *
 * @package Npcink_Toolbox
 */

use Npcink_Toolbox\Provider_Client;
use Npcink_Toolbox\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "FAIL: Run this script through WP-CLI eval-file so WordPress is loaded.\n" );
	exit( 1 );
}

function toolbox_web_search_cloud_addon_real_smoke_pass( string $message ): void {
	echo "PASS: {$message}\n";
}

function toolbox_web_search_cloud_addon_real_smoke_fail( string $message ): void {
	fwrite( STDERR, "FAIL: {$message}\n" );
	exit( 1 );
}

function toolbox_web_search_cloud_addon_real_smoke_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		toolbox_web_search_cloud_addon_real_smoke_fail( $message );
	}

	toolbox_web_search_cloud_addon_real_smoke_pass( $message );
}

toolbox_web_search_cloud_addon_real_smoke_assert(
	function_exists( 'npcink_cloud_addon_execute_toolbox_web_search_runtime' ),
	'Cloud Addon exposes the Toolbox web search transport helper.'
);
toolbox_web_search_cloud_addon_real_smoke_assert(
	class_exists( Provider_Client::class ) && class_exists( Settings::class ),
	'Toolbox provider client is available in the local WordPress site.'
);

$client = new Provider_Client( new Settings() );
$result = $client->test_cloud_web_search(
	array(
		'query'        => 'WordPress editorial workflow AI research',
		'intent'       => 'writing_context',
		'max_results'  => 3,
		'recency_days' => 30,
	)
);

if ( is_wp_error( $result ) ) {
	$code    = $result->get_error_code();
	$message = $result->get_error_message();
	if ( 'cloud_web_search_zhihu_access_secret_missing' === $code ) {
		toolbox_web_search_cloud_addon_real_smoke_fail( "Cloud provider config is incomplete: {$code} - {$message}" );
	}

	toolbox_web_search_cloud_addon_real_smoke_fail( "Web search returned WP_Error: {$code} - {$message}" );
}

toolbox_web_search_cloud_addon_real_smoke_assert( is_array( $result ), 'Web search returns a structured artifact.' );
toolbox_web_search_cloud_addon_real_smoke_assert( 'web_search_results' === (string) ( $result['artifact_type'] ?? '' ), 'Web search artifact type is web_search_results.' );
toolbox_web_search_cloud_addon_real_smoke_assert( 'web_search.v1' === (string) ( $result['contract_version'] ?? '' ), 'Web search keeps the web_search.v1 contract.' );
toolbox_web_search_cloud_addon_real_smoke_assert( false === (bool) ( $result['direct_wordpress_write'] ?? true ), 'Web search artifact does not authorize WordPress writes.' );
toolbox_web_search_cloud_addon_real_smoke_assert( false === (bool) ( $result['handoff']['direct_wordpress_write'] ?? true ), 'Web search handoff blocks direct WordPress writes.' );
toolbox_web_search_cloud_addon_real_smoke_assert( count( (array) ( $result['results'] ?? array() ) ) > 0, 'Web search returns at least one real Cloud-managed result.' );

