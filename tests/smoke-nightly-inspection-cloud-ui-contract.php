<?php
/**
 * Static UI contract smoke for Nightly Inspection Cloud Runtime compatibility.
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

$admin_js   = (string) file_get_contents( $root . '/assets/admin.js' );
$admin_page = (string) file_get_contents( $root . '/includes/Admin_Page.php' );

foreach (
	array(
		'function autoPollNightlyCloudBatch',
		'Automatic status check',
		'Cloud remains the run-state owner',
		'function autoReadNightlyCloudBatchResult',
		'automatically merged into the local review-only scheduled review preview',
		'Automatic checks ended before Cloud reached a terminal state',
		'Cloud accepted the run, but the automatic status/result follow-up did not complete',
		'Cloud result is not ready yet',
		'function renderNightlyCloudRecentRun',
		'function renderNightlyCloudRecentRuns',
		'nightlyCloudPayloadFromRecentCard',
		'NIGHTLY_CLOUD_RECENT_KEY',
		'Use run',
		'Refresh status',
		'Load result',
		'Retry run',
		'Partial success',
		'Cloud returned partial success',
		'Retry guidance remains Cloud-owned',
		'function retryNightlyCloudBatch',
		"postJson(config.restUrl, 'nightly-inspection/cloud-batch/' + encodeURIComponent(runId) + '/retry'",
		"getJson(config.restUrl, 'nightly-inspection/cloud-batch/recent?limit=5')",
		'Cloud run detail',
		'Core handoff',
		'Core intake package',
		'Scheduled review queue',
		'Score breakdown',
		'Scheduled review feedback',
		'data-toolbox-nightly-agent-feedback',
		'wrong_priority',
		'already_handled',
		'source_reason_codes',
		'score_breakdown',
		'priority_queue',
		'issue_groups',
		'Inspection',
		'Top review',
		'Review in Core',
		'Toolbox prepares review context only; it does not create proposals or write content',
		'nightlyCloudCoreIntakePackage',
		'core_intake_package',
		'New runs are disabled until Cloud reports remaining quota',
		'Advanced details: Cloud inspection payload',
		'nightlyCloudTerminal',
		'nightlyCloudSucceeded',
	) as $required_js_text
) {
	$assert_contains( $admin_js, $required_js_text, 'Nightly Inspection Cloud Runtime compatibility code keeps the submit/status/result flow observable for existing callers.' );
}

foreach (
	array(
		'cloud_addon_runtime_runs_url',
		"'tab'  => 'runtime_runs'",
		'Cloud run status and recovery',
		'Recent runs, status reads, result reads, and Cloud-owned retry requests now live in Cloud Addon.',
		'Toolbox keeps only the scheduled review preview and local fallback settings here.',
		'Open Cloud run recovery',
		'Low-frequency inspection preview stays here; Cloud run status and recovery live in Cloud Addon.',
		'Inspect recent runs, read results, and request Cloud-owned retry in Cloud Addon.',
	) as $required_admin_text
) {
	$assert_contains( $admin_page, $required_admin_text, 'Scheduled Review admin panel routes Cloud run detail and recovery to Cloud Addon Runtime Runs.' );
}

foreach (
	array(
		'data-toolbox-nightly-cloud-batch',
		'Run Cloud inspection',
		'data-toolbox-nightly-cloud-recent',
		'Load Cloud recent',
		'data-toolbox-nightly-cloud-advanced',
		'data-toolbox-nightly-cloud-retry',
		'data-toolbox-nightly-cloud-recent-run',
		'data-toolbox-nightly-cloud-run-summary',
		'Cloud owns entitlement, usage, queue, retry, and retention detail',
		'no local job queue or write path is created',
	) as $forbidden_admin_text
) {
	$assert_not_contains( $admin_page, $forbidden_admin_text, 'Scheduled Review admin panel must not expose local Cloud run submit/recent/retry controls.' );
}

foreach (
	array(
		'as_enqueue_async_action',
		'ActionScheduler',
		'wp_schedule_event(',
		'wp_insert_post(',
		'wp_update_post(',
	) as $forbidden_js_text
) {
	$assert_not_contains( $admin_js, $forbidden_js_text, 'Nightly Inspection Cloud Runtime compatibility code must not create local execution or write ownership.' );
}

echo "Nightly inspection Cloud Runtime compatibility contract: ok\n";
