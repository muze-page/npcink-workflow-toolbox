<?php
/** Real-host consumer probe for the Adapter generic AI-client contract. */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "FAIL: Run this script through WP-CLI eval-file.\n" );
	exit( 1 );
}

function npcink_generic_client_probe_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
	echo "PASS: {$message}\n";
}

function npcink_generic_client_probe_request( string $method, string $route, array $params = array() ): array {
	$request = new WP_REST_Request( $method, $route );
	$request->set_header( 'user-agent', 'npcink-generic-contract-probe/1.0' );
	$request->set_header( 'x-npcink-client-profile', 'generic-contract-probe' );
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}
	$response = rest_do_request( $request );
	npcink_generic_client_probe_assert( $response->get_status() >= 200 && $response->get_status() < 300, "{$method} {$route} succeeds" );
	$data = $response->get_data();
	return is_array( $data ) ? $data : array();
}

$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ids' ) );
npcink_generic_client_probe_assert( ! empty( $admins ), 'A local administrator is available for the authenticated consumer probe' );
wp_set_current_user( absint( $admins[0] ) );

$health   = npcink_generic_client_probe_request( 'GET', '/npcink-openclaw-adapter/v1/health' );
$contract = is_array( $health['contract'] ?? null ) ? $health['contract'] : array();
$workflow = is_array( $contract['workflow_projection'] ?? null ) ? $contract['workflow_projection'] : array();
npcink_generic_client_probe_assert( 'generic_ai_client' === (string) ( $contract['client_contract'] ?? '' ), 'Adapter advertises the generic AI-client contract' );
npcink_generic_client_probe_assert( 'openclaw' === (string) ( $contract['priority_channel'] ?? '' ), 'OpenClaw remains the priority implementation' );
npcink_generic_client_probe_assert( array( 'openclaw' ) === (array) ( $workflow['supported_channels'] ?? array() ), 'The probe does not invent a second supported channel' );
npcink_generic_client_probe_assert( 'npcink-abilities-toolkit' === (string) ( $workflow['definition_owner'] ?? '' ), 'Toolkit remains the workflow-definition owner' );
npcink_generic_client_probe_assert( false === (bool) ( $workflow['canonical_definition_storage'] ?? true ), 'Adapter does not store canonical workflow definitions' );
npcink_generic_client_probe_assert( false === (bool) ( $workflow['runtime_state_storage'] ?? true ), 'Adapter does not store workflow runtime state' );
npcink_generic_client_probe_assert( 'fail_closed' === (string) ( $workflow['version_mismatch_policy'] ?? '' ), 'Generic workflow projection fails closed on version mismatch' );

$manifest = npcink_generic_client_probe_request( 'GET', '/npcink-openclaw-adapter/v1/connection/manifest' );
npcink_generic_client_probe_assert( 'npcink.ai/wordpress-adapter-connection' === (string) ( $manifest['kind'] ?? '' ), 'Generic consumer can discover the connection manifest' );
npcink_generic_client_probe_assert( 'proposal_only' === (string) ( $manifest['capabilities']['write']['mode'] ?? '' ), 'Connection manifest keeps writes proposal-only' );
npcink_generic_client_probe_assert( false === (bool) ( $manifest['capabilities']['write']['direct_wordpress_write_allowed'] ?? true ), 'Connection manifest forbids direct WordPress writes' );

$recipe = npcink_generic_client_probe_request(
	'POST',
	'/npcink-openclaw-adapter/v1/run-read-ability',
	array(
		'ability_id' => 'npcink-abilities-toolkit/get-workflow-recipe',
		'input'      => array( 'recipe_id' => 'media_optimization_v1' ),
		'caller'     => array(
			'client_type'        => 'generic_contract_probe',
			'client_name'        => 'toolbox-non-openclaw-probe',
			'external_thread_id' => 'toolbox-generic-contract-probe',
		),
	)
);
$definition = is_array( $recipe['result'] ?? null ) ? $recipe['result'] : array();
npcink_generic_client_probe_assert( 'direct_read' === (string) ( $recipe['governance_mode'] ?? '' ), 'Generic consumer receives the governed read envelope' );
npcink_generic_client_probe_assert( 'npcink-abilities-toolkit/recipes/media-optimization' === (string) ( $definition['recipe_id'] ?? '' ), 'Generic consumer resolves the canonical media workflow definition' );
npcink_generic_client_probe_assert( 'npcink-abilities-toolkit/build-media-optimization-plan' === (string) ( $definition['entrypoint_ability_id'] ?? '' ), 'Generic consumer receives the canonical entrypoint ability' );
npcink_generic_client_probe_assert( true === (bool) ( $definition['host_governed_write_boundary'] ?? false ), 'Generic consumer preserves the host-governed write boundary' );
npcink_generic_client_probe_assert( 'approval_request' === (string) ( $definition['handoff']['kind'] ?? '' ), 'Generic consumer receives the Core approval handoff posture' );

echo "Generic non-OpenClaw AI-client contract probe passed without declaring channel support.\n";
