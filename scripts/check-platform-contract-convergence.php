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

if ( $failures ) {
	fwrite( STDERR, sprintf( "Platform contract convergence failed: %d failure(s), %d pass(es), %d skip(s).\n", count( $failures ), $passes, $skips ) );
	exit( 1 );
}

echo sprintf( "Platform contract convergence passed: %d pass(es), %d skip(s).\n", $passes, $skips );
