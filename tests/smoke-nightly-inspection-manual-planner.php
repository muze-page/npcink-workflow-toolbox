<?php
/**
 * Validates the manual dry-run planner for Nightly Site Inspection.
 *
 * @package Npcink_Toolbox
 */

$root = dirname( __DIR__ );

require_once $root . '/modules/local-automation-runtime/src/Contract/Replay_Validator.php';
require_once $root . '/modules/local-automation-runtime/src/NightlyInspection/Rule_Scorer.php';
require_once $root . '/modules/local-automation-runtime/src/NightlyInspection/Morning_Brief_Builder.php';
require_once $root . '/modules/local-automation-runtime/src/NightlyInspection/Manual_Dry_Run_Planner.php';

$fixture_path = $root . '/modules/local-automation-runtime/tests/fixtures/nightly-inspection-snapshot.json';
$snapshot     = json_decode( (string) file_get_contents( $fixture_path ), true );

if ( ! is_array( $snapshot ) ) {
	fwrite( STDERR, "Nightly inspection snapshot fixture is not valid JSON.\n" );
	exit( 1 );
}

$planner = new \Npcink\LocalAutomationRuntime\NightlyInspection\Manual_Dry_Run_Planner();
$replay  = $planner->plan( $snapshot );

$validator = new \Npcink\LocalAutomationRuntime\Contract\Replay_Validator();
$result    = $validator->validate( $replay );

$fail = static function ( string $message ): void {
	fwrite( STDERR, $message . "\n" );
	exit( 1 );
};

if ( true !== ( $result['valid'] ?? false ) ) {
	$fail( 'Manual dry-run replay failed validation: ' . implode( ', ', $result['errors'] ?? array() ) );
}
if ( 'nightly_site_inspection_morning_brief' !== ( $replay['task_profile'] ?? '' ) ) {
	$fail( 'Manual dry-run replay has the wrong task profile.' );
}
if ( 'dry_run_replay' !== ( $replay['mode'] ?? '' ) ) {
	$fail( 'Manual dry-run replay must stay dry-run only.' );
}
if ( false !== ( $replay['core_runtime_execution'] ?? true ) || false !== ( $replay['background_execution'] ?? true ) ) {
	$fail( 'Manual dry-run replay must not execute Core runtime or background work.' );
}
foreach ( array( 'scheduler_created', 'worker_created', 'core_tables_created', 'lease_store_created', 'dead_letter_processor_created' ) as $false_flag ) {
	if ( false !== ( $replay['acceptance'][ $false_flag ] ?? true ) ) {
		$fail( 'Manual dry-run replay acceptance flag must be false: ' . $false_flag );
	}
}

$brief = $replay['preview']['morning_brief'] ?? array();
if ( ! is_array( $brief ) || 'nightly_site_inspection_result.v1' !== ( $brief['contract_version'] ?? '' ) ) {
	$fail( 'Manual dry-run replay must include a Morning Brief preview.' );
}
foreach ( array( 'direct_wordpress_write', 'cloud_scheduler_truth', 'cloud_called', 'cron_registered', 'action_scheduler_used', 'custom_tables_created' ) as $false_flag ) {
	if ( false !== ( $brief['safety'][ $false_flag ] ?? true ) ) {
		$fail( 'Morning Brief safety flag must be false: ' . $false_flag );
	}
}

$actions = $replay['job']['actions'] ?? array();
if ( ! is_array( $actions ) || count( $actions ) < 2 ) {
	$fail( 'Manual dry-run replay should include ready preview actions.' );
}
foreach ( $actions as $action ) {
	if ( ! is_array( $action ) || 'ready' !== ( $action['status'] ?? '' ) ) {
		$fail( 'Manual dry-run replay actions must stay ready preview items.' );
	}
	if ( 'manual_dry_run_preview_only' !== ( $action['execution_profile'] ?? '' ) ) {
		$fail( 'Manual dry-run replay actions must use the preview-only execution profile.' );
	}
}

echo "Nightly inspection manual planner: ok\n";
