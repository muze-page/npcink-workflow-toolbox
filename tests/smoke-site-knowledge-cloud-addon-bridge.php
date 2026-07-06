<?php
/**
 * Local WordPress smoke for Cloud Addon-owned Site Knowledge change delivery.
 *
 * This script is read-only. The wrapper activates Cloud Addon before Toolbox in
 * a separate WP-CLI request, then restores the original activation state.
 *
 * @package Npcink_Toolbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "FAIL: Run this script through WP-CLI eval-file so WordPress is loaded.\n" );
	exit( 1 );
}

/**
 * Emits a passing assertion.
 *
 * @param string $message Message.
 * @return void
 */
function npcink_toolbox_sk_cloud_bridge_pass( string $message ): void {
	echo '[ok] ' . $message . "\n";
}

/**
 * Emits a failing assertion and exits.
 *
 * @param string $message Message.
 * @return void
 */
function npcink_toolbox_sk_cloud_bridge_fail( string $message ): void {
	fwrite( STDERR, '[fail] ' . $message . "\n" );
	exit( 1 );
}

/**
 * Assertion helper.
 *
 * @param bool   $condition Condition.
 * @param string $message Message.
 * @return void
 */
function npcink_toolbox_sk_cloud_bridge_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		npcink_toolbox_sk_cloud_bridge_fail( $message );
	}

	npcink_toolbox_sk_cloud_bridge_pass( $message );
}

npcink_toolbox_sk_cloud_bridge_assert(
	function_exists( 'npcink_cloud_addon_site_knowledge_change_bridge_health' ),
	'Cloud Addon exposes the Site Knowledge change bridge health seam.'
);

npcink_toolbox_sk_cloud_bridge_assert(
	class_exists( 'Npcink_Cloud_Site_Knowledge_Change_Bridge' ),
	'Cloud Addon Site Knowledge bridge class is loaded.'
);

npcink_toolbox_sk_cloud_bridge_assert(
	class_exists( '\Npcink_Toolbox\Site_Knowledge_Auto_Sync' ),
	'Toolbox Site Knowledge legacy fallback class is loaded.'
);

$health = \Npcink_Toolbox\Site_Knowledge_Auto_Sync::health_snapshot();
npcink_toolbox_sk_cloud_bridge_assert( is_array( $health ), 'Toolbox returns a Site Knowledge change bridge health array.' );
npcink_toolbox_sk_cloud_bridge_assert( 'cloud_addon' === (string) ( $health['owner'] ?? '' ), 'Toolbox health reports Cloud Addon as Site Knowledge change delivery owner.' );
npcink_toolbox_sk_cloud_bridge_assert( 'site_knowledge_change_bridge' === (string) ( $health['mode'] ?? '' ), 'Toolbox health reports the Cloud Addon bridge mode.' );
npcink_toolbox_sk_cloud_bridge_assert( false === (bool) ( $health['legacy_toolbox_fallback'] ?? true ), 'Toolbox legacy fallback is disabled while Cloud Addon bridge is present.' );
npcink_toolbox_sk_cloud_bridge_assert( array_key_exists( 'buffer_count', $health ), 'Toolbox exposes the Cloud Addon bridge buffer count without owning a legacy queue.' );
npcink_toolbox_sk_cloud_bridge_assert( array_key_exists( 'queue_count', $health ) && in_array( 'queue_count', (array) ( $health['compatibility_aliases'] ?? array() ), true ), 'Toolbox keeps queue_count only as a compatibility alias for older callers.' );
npcink_toolbox_sk_cloud_bridge_assert( 'local_wordpress_host' === (string) ( $health['ownership']['source_content_owner'] ?? '' ), 'Toolbox preserves local WordPress as Site Knowledge source content owner.' );
npcink_toolbox_sk_cloud_bridge_assert( 'cloud_addon' === (string) ( $health['ownership']['delivery_bridge_owner'] ?? '' ), 'Toolbox preserves Cloud Addon as Site Knowledge delivery bridge owner.' );
npcink_toolbox_sk_cloud_bridge_assert( 'cloud_service' === (string) ( $health['ownership']['vector_storage_owner'] ?? '' ), 'Toolbox preserves Cloud service as Site Knowledge vector storage owner.' );
npcink_toolbox_sk_cloud_bridge_assert( true === (bool) ( $health['truth_boundaries']['cloud_is_index_truth'] ?? false ), 'Toolbox reports Cloud as Site Knowledge index truth.' );
npcink_toolbox_sk_cloud_bridge_assert( false === (bool) ( $health['truth_boundaries']['cloud_is_wordpress_control_plane'] ?? true ), 'Toolbox reports Cloud is not the WordPress control plane.' );
npcink_toolbox_sk_cloud_bridge_assert( false === (bool) ( $health['truth_boundaries']['cloud_creates_wordpress_writes'] ?? true ), 'Toolbox reports Cloud does not create WordPress writes.' );
npcink_toolbox_sk_cloud_bridge_assert( 'local_wordpress_host' === (string) ( $health['site_knowledge_cloud_boundary']['ownership']['final_write_owner'] ?? '' ), 'Toolbox exposes Site Knowledge final write owner through the read-only boundary projection.' );

$cloud_post_hook = has_action(
	'transition_post_status',
	array( 'Npcink_Cloud_Site_Knowledge_Change_Bridge', 'handle_post_status_transition' )
);
npcink_toolbox_sk_cloud_bridge_assert( false !== $cloud_post_hook, 'Cloud Addon bridge owns the public post status hook.' );
npcink_toolbox_sk_cloud_bridge_assert( false === has_action( 'npcink_toolbox_process_site_knowledge_auto_sync' ), 'Toolbox legacy auto-sync cron hook is not registered.' );

echo "Site Knowledge Cloud Addon bridge smoke: ok\n";
