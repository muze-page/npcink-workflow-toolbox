<?php
/** Verifies the frozen default fixed-button ownership and coverage table. */

$root    = dirname( __DIR__ );
$path    = $root . '/docs/fixed-button-contract-table.json';
$decoded = is_file( $path ) ? json_decode( (string) file_get_contents( $path ), true ) : null;
$errors  = array();
$passes  = 0;

function npcink_fixed_button_check( bool $condition, string $message ): void {
	global $errors, $passes;
	if ( $condition ) {
		++$passes;
		echo "PASS: {$message}\n";
		return;
	}
	$errors[] = $message;
	fwrite( STDERR, "FAIL: {$message}\n" );
}

npcink_fixed_button_check( is_array( $decoded ), 'Fixed-button contract table is readable JSON' );
npcink_fixed_button_check( 'npcink_fixed_button_contract_table.v1' === (string) ( $decoded['schema_version'] ?? '' ), 'Fixed-button table uses the v1 schema' );
npcink_fixed_button_check( 'npcink-abilities-toolkit' === (string) ( $decoded['canonical_workflow_owner'] ?? '' ), 'Toolkit remains the canonical workflow owner' );
npcink_fixed_button_check( 'npcink-governance-core' === (string) ( $decoded['governance_owner'] ?? '' ), 'Core remains the governance owner' );
npcink_fixed_button_check( 'npcink-ai-client-adapter' === (string) ( $decoded['external_client_owner'] ?? '' ), 'Adapter remains the external-client owner' );

$buttons      = is_array( $decoded['buttons'] ?? null ) ? $decoded['buttons'] : array();
$expected_ids = array( 'site_check', 'publish_preflight', 'internal_link_candidates', 'article_image_alt', 'image_candidates', 'article_narration', 'article_audio_summary', 'batch_image_optimization', 'batch_alt_review' );
$actual_ids   = array();
$allowed_lanes = array( 'review_only', 'native_editor_commit', 'core_proposal_required', 'mixed_review_and_core_handoff' );
$allowed_parity = array( 'workflow_projection_proven', 'ability_parity_ready', 'ability_parity_ready_editor_commit_excluded', 'partial_contract_reuse' );

foreach ( $buttons as $button ) {
	$button_id = strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) ( $button['button_id'] ?? '' ) ) );
	$actual_ids[] = $button_id;
	npcink_fixed_button_check( '' !== $button_id, 'Every fixed button has an id' );
	npcink_fixed_button_check( '' !== (string) ( $button['surface'] ?? '' ), "{$button_id} declares an owning surface" );
	npcink_fixed_button_check( ! empty( $button['source_contracts'] ) && is_array( $button['source_contracts'] ), "{$button_id} declares source contracts" );
	npcink_fixed_button_check( '' !== (string) ( $button['runtime_owner'] ?? '' ), "{$button_id} declares a runtime owner" );
	npcink_fixed_button_check( in_array( (string) ( $button['write_lane'] ?? '' ), $allowed_lanes, true ), "{$button_id} uses an allowed write lane" );
	npcink_fixed_button_check( '' !== (string) ( $button['handoff_owner'] ?? '' ), "{$button_id} declares a handoff owner" );
	npcink_fixed_button_check( in_array( (string) ( $button['adapter_parity_status'] ?? '' ), $allowed_parity, true ), "{$button_id} reports an honest Adapter parity status" );
	npcink_fixed_button_check( false === (bool) ( $button['direct_wordpress_write'] ?? true ), "{$button_id} exposes no direct Toolbox WordPress write" );

	$source_file = $root . '/' . ltrim( (string) ( $button['source_file'] ?? '' ), '/' );
	$source      = is_file( $source_file ) ? file_get_contents( $source_file ) : false;
	npcink_fixed_button_check( false !== $source && false !== strpos( $source, (string) ( $button['source_marker'] ?? '' ) ), "{$button_id} source marker exists in production" );
}

sort( $actual_ids );
sort( $expected_ids );
npcink_fixed_button_check( $expected_ids === $actual_ids, 'The table covers exactly the nine committed default buttons' );
npcink_fixed_button_check( 1 === count( array_filter( $buttons, static fn( array $button ): bool => 'workflow_projection_proven' === ( $button['adapter_parity_status'] ?? '' ) ) ), 'Only the currently proven media workflow claims full projection parity' );

$editor_source = file_get_contents( $root . '/assets/editor-content-support.js' );
$editor_block  = false !== $editor_source && preg_match( '/const flows = \[(.*?)\n\t\];\n\n\tconst flowGroups/s', $editor_source, $editor_match ) ? $editor_match[1] : '';
preg_match_all( "/\n\s*intent:\s*'([^']+)'/", $editor_block, $editor_intents );
$editor_table_count = count( array_filter( $buttons, static fn( array $button ): bool => 'editor_content_support' === ( $button['surface'] ?? '' ) ) );
npcink_fixed_button_check( $editor_table_count === count( array_unique( $editor_intents[1] ?? array() ) ), 'Every committed editor flow has one fixed-button contract row' );

$admin_source = file_get_contents( $root . '/includes/Admin_Page.php' );
$admin_block  = false !== $admin_source && preg_match( '/private function render_tool_cards.*?\$tools = array\((.*?)\n\t\t\);/s', $admin_source, $admin_match ) ? $admin_match[1] : '';
preg_match_all( "/'id'\s*=>\s*'([^']+)'/", $admin_block, $admin_ids );
$admin_table_count = count( array_filter( $buttons, static fn( array $button ): bool => 'admin_image_handling' === ( $button['surface'] ?? '' ) ) );
npcink_fixed_button_check( $admin_table_count === count( array_unique( $admin_ids[1] ?? array() ) ), 'Every committed Image Handling tool has one fixed-button contract row' );

$adr = file_get_contents( $root . '/docs/decisions/ADR-008-freeze-fixed-button-and-generic-client-boundary.md' );
foreach ( array( 'No further broad ownership migration', 'External clients do not inherit this exception', 'consumer-conformance test', 'must not add a channel' ) as $marker ) {
	npcink_fixed_button_check( false !== $adr && false !== strpos( $adr, $marker ), "ADR-008 freezes boundary marker: {$marker}" );
}

if ( $errors ) {
	fwrite( STDERR, sprintf( "Fixed-button contracts failed: %d failure(s), %d pass(es).\n", count( $errors ), $passes ) );
	exit( 1 );
}

echo sprintf( "Fixed-button contracts passed: %d pass(es).\n", $passes );
