<?php
/**
 * Static boundary smoke for the WP-Cron + Cloud Batch Runtime orchestration split.
 *
 * @package Npcink_Toolbox
 */

$root = dirname( __DIR__ );

$fail = static function ( string $message ): void {
	fwrite( STDERR, $message . "\n" );
	exit( 1 );
};

$assert_contains = static function ( string $haystack, string $needle, string $message ) use ( $fail ): void {
	if ( false === strpos( $haystack, $needle ) ) {
		$fail( $message . ' Missing: ' . $needle );
	}
};

$assert_not_contains = static function ( string $haystack, string $needle, string $message ) use ( $fail ): void {
	if ( false !== strpos( $haystack, $needle ) ) {
		$fail( $message . ' Forbidden: ' . $needle );
	}
};

$adr = (string) file_get_contents( $root . '/docs/decisions/ADR-005-wp-cron-cloud-batch-orchestration.md' );
foreach (
	array(
		'Use WP-Cron Local Preview And Cloud Batch Runtime For Nightly Automation',
		'Basic/local fallback uses WP-Cron only',
		'Pro uses Cloud Batch Runtime',
		'Cloud must not become schedule truth',
		'must not create a server-side run-history table',
		'job table, lease store, retry processor, dead-letter processor',
		'Core remains the proposal, approval,',
		'final WordPress write path',
		'Plugin-side Action Scheduler',
		'Rejected for the current Basic and Pro path',
	) as $required_adr_text
) {
	$assert_contains( $adr, $required_adr_text, 'ADR-005 records the current orchestration decision.' );
}

$readme = (string) file_get_contents( $root . '/README.md' );
$assert_contains( $readme, 'ADR-005: Use WP-Cron Local Preview And Cloud Batch Runtime For Nightly Automation', 'README links ADR-005.' );
$assert_contains( $readme, 'WP-Cron is the local fallback preview', 'README records WP-Cron as local fallback/trigger only.' );
$assert_contains( $readme, 'Cloud Batch Runtime is the Pro execution', 'README records Cloud Batch Runtime as Pro execution path.' );

$basic_cron = (string) file_get_contents( $root . '/modules/local-automation-runtime/src/NightlyInspection/Basic_WP_Cron_Dry_Run.php' );
$assert_contains( $basic_cron, 'wp_schedule_event', 'Basic path may use WP-Cron for the local fallback preview.' );
$assert_contains( $basic_cron, 'LATEST_PREVIEW_OPTION', 'Basic path stores only the latest preview option.' );
foreach ( array( 'wp_remote_post', 'wp_remote_get', 'register_rest_route', 'as_enqueue_async_action', 'ActionScheduler', 'wp_insert_post', 'wp_update_post', 'approve-and-execute' ) as $forbidden_basic_text ) {
	$assert_not_contains( $basic_cron, $forbidden_basic_text, 'Basic WP-Cron preview must not become a local executor.' );
}

$cloud_merger = (string) file_get_contents( $root . '/modules/local-automation-runtime/src/NightlyInspection/Cloud_Batch_Result_Merger.php' );
foreach ( array( "'direct_wordpress_write'", "'final_write_path'", "'core_proposal_required'", "'cloud_scheduler_truth'", "'action_scheduler_used'" ) as $required_cloud_merger_text ) {
	$assert_contains( $cloud_merger, $required_cloud_merger_text, 'Cloud Batch result merge remains review-only.' );
}

$provider = (string) file_get_contents( $root . '/includes/Provider_Client.php' );
foreach ( array( "'cloud_role'            => 'runtime_detail'", "'cloud_scheduler_truth'        => false", "'core_proposal_created'        => false", "'direct_wordpress_write'       => false", 'get_run( $run_id', 'get_run_result( $run_id' ) as $required_provider_text ) {
	$assert_contains( $provider, $required_provider_text, 'Provider client keeps Cloud Batch as runtime/detail bridge.' );
}

$production_files = array( $root . '/npcink-workflow-toolbox.php' );
foreach ( array( 'includes', 'assets', 'modules/local-automation-runtime/src' ) as $relative_dir ) {
	$directory = new RecursiveDirectoryIterator( $root . '/' . $relative_dir, FilesystemIterator::SKIP_DOTS );
	$iterator  = new RecursiveIteratorIterator( $directory );
	foreach ( $iterator as $file ) {
		if ( ! $file instanceof SplFileInfo || ! $file->isFile() ) {
			continue;
		}
		if ( ! in_array( $file->getExtension(), array( 'php', 'js', 'css' ), true ) ) {
			continue;
		}
		$production_files[] = $file->getPathname();
	}
}

$forbidden_production_patterns = array(
	'as_enqueue_async_action',
	'as_schedule_single_action',
	'as_schedule_recurring_action',
	'ActionScheduler',
	'Action_Scheduler',
	'dbDelta(',
	'CREATE TABLE',
	'CREATE TABLE IF NOT EXISTS',
	'npcink_local_automation_runtime_jobs',
	'npcink_local_automation_runtime_runs',
	'npcink_local_automation_runtime_actions',
);

foreach ( $production_files as $path ) {
	$text = (string) file_get_contents( $path );
	foreach ( $forbidden_production_patterns as $pattern ) {
		$assert_not_contains( $text, $pattern, 'Production code must not introduce Action Scheduler or local runtime tables: ' . str_replace( $root . '/', '', $path ) );
	}
}

echo "Nightly inspection orchestration boundary: ok\n";
