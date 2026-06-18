<?php
/**
 * Static UI contract smoke for the Pro Nightly Inspection Cloud Runtime panel.
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
		'automatically merged into the local review-only Morning Brief preview',
		'Automatic checks ended before Cloud reached a terminal state',
		'Cloud accepted the run, but the automatic status/result follow-up did not complete',
		'Cloud result is not ready yet',
		'function renderNightlyCloudRecentRun',
		'NIGHTLY_CLOUD_RECENT_KEY',
		'Use run',
		'Refresh status',
		'Load result',
		'Cloud run detail',
		'Core handoff',
		'Core intake package',
		'Morning Brief review queue',
		'Score breakdown',
		'Morning Brief feedback',
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
	$assert_contains( $admin_js, $required_js_text, 'Pro Cloud Runtime UI keeps the submit/status/result flow observable.' );
}

foreach (
	array(
		'data-toolbox-nightly-cloud-batch',
		'Run Cloud inspection',
		'data-toolbox-nightly-cloud-advanced',
		'Advanced details',
		'data-toolbox-nightly-cloud-recent-run',
		'data-toolbox-nightly-cloud-run-summary',
		'data-toolbox-nightly-local-brief',
		'Cloud owns entitlement, usage, queue, retry, and retention detail',
		'Cloud remains the run-state owner',
		'no local job queue or write path is created',
	) as $required_admin_text
) {
	$assert_contains( $admin_page, $required_admin_text, 'Pro Cloud Runtime admin panel keeps review-only boundary copy and data hooks.' );
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
	$assert_not_contains( $admin_js, $forbidden_js_text, 'Pro Cloud Runtime UI must not create local execution or write ownership.' );
}

echo "Nightly inspection Cloud Runtime UI contract: ok\n";
