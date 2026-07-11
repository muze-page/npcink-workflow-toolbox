<?php
/**
 * Read-only platform contract convergence checks.
 *
 * @package Npcink_Toolbox
 */

$root        = dirname( __DIR__ );
$family_root = dirname( $root );
$failures    = array();
$passes      = 0;
$skips       = 0;

function npcink_contract_check( bool $condition, string $message ): void {
	global $failures, $passes;

	if ( $condition ) {
		++$passes;
		echo "PASS: {$message}\n";
		return;
	}

	$failures[] = $message;
	fwrite( STDERR, "FAIL: {$message}\n" );
}

function npcink_contract_file_contains( string $path, array $needles, string $label ): void {
	$content = is_file( $path ) ? file_get_contents( $path ) : false;
	npcink_contract_check( false !== $content, "{$label} exists" );
	if ( false === $content ) {
		return;
	}

	foreach ( $needles as $needle ) {
		npcink_contract_check( false !== strpos( $content, $needle ), "{$label} preserves {$needle}" );
	}
}

npcink_contract_file_contains(
	$root . '/docs/decisions/ADR-006-native-editor-commit-and-governed-batch-handoff.md',
	array( 'native_editor_commit', 'Plugin-admin batch execution surfaces', 'generic external AI-client contract', 'canonical owner of reusable, versioned' ),
	'ADR-006'
);
npcink_contract_file_contains(
	$root . '/docs/editor-native-commit-migration-spec.md',
	array( 'Eligibility Test', 'Removed Legacy Components', 'governance URL', 'Do not run old and new commit mechanisms' ),
	'Editor migration specification'
);
npcink_contract_file_contains(
	$root . '/docs/workflow-and-ai-client-projection-contract.md',
	array( 'Projection Parity', 'Generic Adapter Baseline', 'Separate Adapter Threshold', 'version mismatch fails closed' ),
	'Workflow and Adapter projection contract'
);

$quality_matrix = file_get_contents( $root . '/scripts/cross-repo-quality-matrix.php' );
npcink_contract_check( false !== $quality_matrix && false === strpos( $quality_matrix, 'wp-magick-toolbox' ), 'Npcink quality matrix excludes wp-magick-toolbox' );
foreach ( array( 'npcink-governance-core', 'npcink-abilities-toolkit', 'npcink-ai-client-adapter', 'npcink-workflow-toolbox', 'npcink-cloud-addon', 'npcink-ai-cloud' ) as $repo_name ) {
	npcink_contract_check( false !== $quality_matrix && false !== strpos( $quality_matrix, "'name'       => '{$repo_name}'" ), "Npcink quality matrix includes {$repo_name}" );
}

$legacy_markers = array(
	'reviewed-action-intents',
	'approve-and-execute',
	'queuePublishExecutionIntent',
	'executeReviewedProposalOnPublish',
	'executePendingPublishIntents',
);
foreach ( array( 'assets', 'includes' ) as $source_dir ) {
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/' . $source_dir, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $iterator as $file ) {
		if ( ! $file->isFile() || ! in_array( $file->getExtension(), array( 'php', 'js' ), true ) ) {
			continue;
		}
		$content = file_get_contents( $file->getPathname() );
		foreach ( $legacy_markers as $marker ) {
			if ( false === strpos( $content, $marker ) ) {
				continue;
			}
			$relative = substr( $file->getPathname(), strlen( $root ) + 1 );
			npcink_contract_check( false, "Legacy editor execution marker {$marker} is absent from {$relative}" );
		}
	}
}
npcink_contract_check( true, 'Production source contains no legacy editor post-save execution markers' );

$sibling_contracts = array(
	array(
		'path'    => $family_root . '/npcink-governance-core/docs/decisions/ADR-005-keep-core-independent-and-standardize-channel-adapters.md',
		'needles' => array( 'standardize channel adapters', 'OpenClaw Adapter as the first channel adapter' ),
		'label'   => 'Core channel adapter decision',
	),
	array(
		'path'    => $family_root . '/npcink-abilities-toolkit/docs/workflow-definition-contract.md',
		'needles' => array( 'canonical', 'owner of reusable workflow definitions', 'not a workflow runtime' ),
		'label'   => 'Toolkit workflow definition contract',
	),
	array(
		'path'    => $family_root . '/npcink-ai-client-adapter/README.md',
		'needles' => array( 'thin AI client channel plugin', 'similar AI clients' ),
		'label'   => 'Adapter generic client baseline',
	),
	array(
		'path'    => $family_root . '/npcink-cloud-addon/AGENTS.md',
		'needles' => array( 'Cloud connector only', 'WordPress write ownership' ),
		'label'   => 'Cloud Addon transport boundary',
	),
	array(
		'path'    => $family_root . '/npcink-ai-cloud/AGENTS.md',
		'needles' => array( 'second WordPress control plane', 'second workflow registry' ),
		'label'   => 'Cloud runtime boundary',
	),
);

foreach ( $sibling_contracts as $contract ) {
	if ( ! is_file( $contract['path'] ) ) {
		++$skips;
		echo 'SKIP: ' . $contract['label'] . " sibling checkout is unavailable\n";
		continue;
	}
	npcink_contract_file_contains( $contract['path'], $contract['needles'], $contract['label'] );
}

$toolkit_fixture_path = $family_root . '/npcink-abilities-toolkit/tests/fixtures/agent-workflow-replay.json';
$toolkit_fixture_json = is_file( $toolkit_fixture_path ) ? file_get_contents( $toolkit_fixture_path ) : false;
$toolkit_fixture      = false !== $toolkit_fixture_json ? json_decode( $toolkit_fixture_json, true ) : null;
$media_workflow       = is_array( $toolkit_fixture ) && is_array( $toolkit_fixture['cases']['media_optimization'] ?? null )
	? $toolkit_fixture['cases']['media_optimization']
	: array();

npcink_contract_check( false !== $toolkit_fixture_json && is_array( $toolkit_fixture ), 'Toolkit workflow replay fixture is readable JSON' );
npcink_contract_check( ! empty( $media_workflow ), 'Toolkit owns the media optimization workflow definition' );

$expected_media_workflow = array(
	'recipe_id'                    => 'npcink-abilities-toolkit/recipes/media-optimization',
	'contract_version'             => 'v1',
	'entrypoint_ability_id'        => 'npcink-abilities-toolkit/build-media-optimization-plan',
	'required_scope'               => 'media.read',
	'failure_policy'               => 'fail_closed',
	'host_governed_write_boundary' => true,
);
foreach ( $expected_media_workflow as $field => $expected ) {
	npcink_contract_check( $expected === ( $media_workflow[ $field ] ?? null ), "Toolkit media optimization definition preserves {$field}" );
}
npcink_contract_check( array( 'media_optimization_v1' ) === ( $media_workflow['recipe_aliases'] ?? array() ), 'Toolkit media optimization definition preserves compatibility alias' );
npcink_contract_check( array( 'attachment_id', 'media_details_input', 'derivative_artifact' ) === ( $media_workflow['required_inputs'] ?? array() ), 'Toolkit media optimization definition preserves required inputs' );
npcink_contract_check( 'approval_request' === ( $media_workflow['handoff']['kind'] ?? '' ), 'Toolkit media optimization definition preserves Core approval handoff' );

$toolbox_projection_source = file_get_contents( $root . '/includes/Provider_Client.php' );
npcink_contract_check( false !== $toolbox_projection_source && false !== strpos( $toolbox_projection_source, "'definition_owner'            => 'npcink-abilities-toolkit'" ), 'Toolbox media optimization is a Toolkit-owned fixed-button projection' );
foreach ( array( 'recipe_id', 'contract_version', 'entrypoint_ability_id', 'required_scope', 'failure_policy' ) as $field ) {
	$value = (string) ( $media_workflow[ $field ] ?? '' );
	npcink_contract_check( '' !== $value && false !== strpos( $toolbox_projection_source, "'{$field}'" ) && false !== strpos( $toolbox_projection_source, "'{$value}'" ), "Toolbox projection preserves Toolkit {$field}" );
}
foreach ( (array) ( $media_workflow['required_inputs'] ?? array() ) as $required_input ) {
	npcink_contract_check( false !== strpos( $toolbox_projection_source, "'{$required_input}'" ), "Toolbox projection preserves required input {$required_input}" );
}
npcink_contract_check( false !== strpos( $toolbox_projection_source, "'handoff_kind'                 => 'approval_request'" ), 'Toolbox projection preserves governed handoff kind' );
npcink_contract_check( false !== strpos( $toolbox_projection_source, "'host_governed_write_boundary' => true" ), 'Toolbox projection preserves host-governed write boundary' );
npcink_contract_check( false !== strpos( $toolbox_projection_source, "'canonical_definition_storage' => false" ), 'Toolbox projection does not store a canonical workflow definition' );

$adapter_controller = file_get_contents( $family_root . '/npcink-ai-client-adapter/includes/Rest/Controller.php' );
foreach ( array( "const ADAPTER_CONTRACT_VERSION    = '4'", "'client_contract'                      => 'generic_ai_client'", "'priority_channel'                     => 'openclaw'", 'npcink_ai_client_workflow_projection.v1', "'definition_owner'              => 'npcink-abilities-toolkit'", "'version_mismatch_policy'       => 'fail_closed'", "'canonical_definition_storage'  => false", "'runtime_state_storage'         => false" ) as $adapter_marker ) {
	npcink_contract_check( false !== $adapter_controller && false !== strpos( $adapter_controller, $adapter_marker ), "Adapter workflow projection preserves {$adapter_marker}" );
}
foreach ( array( 'recipe_id', 'contract_version', 'entrypoint_ability_id', 'required_scope', 'required_inputs', 'handoff', 'failure_policy', 'host_governed_write_boundary' ) as $parity_field ) {
	npcink_contract_check( false !== $adapter_controller && false !== strpos( $adapter_controller, "'{$parity_field}'" ), "Adapter workflow projection requires parity field {$parity_field}" );
}

$core_contract_controller = file_get_contents( $family_root . '/npcink-governance-core/includes/Rest/Contract_Controller.php' );
npcink_contract_check( false !== $core_contract_controller && false !== strpos( $core_contract_controller, "'pre_classification_exclusions'" ), 'Core exposes pre-classification exclusions to consumers' );
npcink_contract_check( false !== $core_contract_controller && false !== strpos( $core_contract_controller, "'native_editor_commit_is_core_classification' => false" ), 'Core does not make native editor commit a fifth classification' );
npcink_contract_check( false !== $core_contract_controller && false !== strpos( $core_contract_controller, "'native_editor_commit_core_record_required' => false" ), 'Core requires no record for native editor commit' );

foreach ( array( $root . '/includes', $family_root . '/npcink-ai-client-adapter/includes' ) as $consumer_source_dir ) {
	$consumer_source = '';
	$iterator        = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $consumer_source_dir, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $iterator as $file ) {
		if ( $file->isFile() && 'php' === $file->getExtension() ) {
			$consumer_source .= (string) file_get_contents( $file->getPathname() );
		}
	}
	npcink_contract_check( false === strpos( $consumer_source, 'class Workflow_Definition_Provider' ), basename( dirname( $consumer_source_dir ) ) . ' does not implement a second workflow definition provider' );
}

if ( $failures ) {
	fwrite( STDERR, sprintf( "Platform contract convergence failed: %d failure(s), %d pass(es), %d skip(s).\n", count( $failures ), $passes, $skips ) );
	exit( 1 );
}

echo sprintf( "Platform contract convergence passed: %d pass(es), %d skip(s).\n", $passes, $skips );
