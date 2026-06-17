<?php
/**
 * Validates review-only Cloud Batch Runtime merge behavior.
 *
 * @package Npcink_Toolbox
 */

$root = dirname( __DIR__ );

require_once $root . '/modules/local-automation-runtime/src/NightlyInspection/Cloud_Batch_Result_Merger.php';

$fail = static function ( string $message ): void {
	fwrite( STDERR, $message . "\n" );
	exit( 1 );
};

$morning_brief = array(
	'contract_version'    => 'nightly_site_inspection_result.v1',
	'run_id'              => 'local-nightly-preview-001',
	'priorities'          => array(
		array(
			'object_type'             => 'post',
			'object_id'               => 101,
			'title'                   => 'Stale article',
			'score'                   => 55,
			'reason_codes'            => array( 'stale_content' ),
			'recommended_next_action' => 'review_content',
		),
		array(
			'object_type'             => 'attachment',
			'object_id'               => 501,
			'title'                   => 'Hero image',
			'score'                   => 85,
			'reason_codes'            => array( 'missing_image_alt' ),
			'recommended_next_action' => 'review_media_alt',
		),
	),
	'writing_preparation' => array(),
	'safety'              => array(
		'direct_wordpress_write' => false,
		'requires_local_review'  => true,
		'cloud_scheduler_truth'  => false,
		'cloud_called'           => false,
		'action_scheduler_used'  => false,
		'custom_tables_created'  => false,
	),
);

$cloud_result = array(
	'run_id'              => 'run_cloud_123',
	'status'              => 'succeeded',
	'worker_phase'        => 'result_ready',
	'execution_kind'      => 'nightly_site_inspection',
	'eligibility_summary' => array(
		'items_total'      => 2,
		'eligible_count'  => 1,
		'blocked_count'   => 1,
		'reviewable_count' => 1,
		'selected_count'  => 1,
	),
	'blocked_items'       => array(
		array(
			'object_type'          => 'page',
			'object_id'            => 202,
			'blocked_reason'       => 'insufficient_public_evidence',
			'operator_next_action' => 'review_local_snapshot',
		),
	),
	'operator_next_action' => 'review_cloud_batch_result',
	'retryable'           => false,
	'retry_guidance'      => array(
		'retryable'            => false,
		'reason'               => 'terminal_result_available',
		'operator_next_action' => 'review_cloud_batch_result',
	),
	'actions'             => array(
		array(
			'object_type'    => 'post',
			'object_id'      => 101,
			'quality_score'  => 62,
			'severity'       => 'warning',
			'reason_codes'   => array( 'needs_refresh', 'weak_internal_links' ),
			'recommendation' => 'Refresh the stale article before drafting new follow-up content.',
			'evidence_refs'  => array(
				array(
					'id'     => 'post:101',
					'label'  => 'Local post metadata',
					'source' => 'local_snapshot',
				),
			),
		),
	),
);

$merger = new \Npcink\LocalAutomationRuntime\NightlyInspection\Cloud_Batch_Result_Merger();
$patch  = $merger->patch( $cloud_result );
$merged = $merger->merge( $morning_brief, $cloud_result );

if ( 'nightly_site_inspection_cloud_batch_merge.v1' !== ( $patch['contract_version'] ?? '' ) ) {
	$fail( 'Cloud Batch patch contract version mismatch.' );
}
if ( 1 !== ( $patch['action_count'] ?? 0 ) ) {
	$fail( 'Cloud Batch patch should expose one action.' );
}
if ( false !== ( $patch['direct_wordpress_write'] ?? true ) ) {
	$fail( 'Cloud Batch patch must not grant direct writes.' );
}
if ( 'result_ready' !== ( $patch['worker_phase'] ?? '' ) ) {
	$fail( 'Cloud Batch patch should preserve worker phase.' );
}
if ( 'nightly_site_inspection' !== ( $patch['execution_kind'] ?? '' ) ) {
	$fail( 'Cloud Batch patch should preserve execution kind.' );
}
if ( 1 !== (int) ( $patch['eligibility_summary']['blocked_count'] ?? 0 ) ) {
	$fail( 'Cloud Batch patch should preserve blocked count.' );
}
if ( 'insufficient_public_evidence' !== ( $patch['blocked_items'][0]['blocked_reason'] ?? '' ) ) {
	$fail( 'Cloud Batch patch should preserve blocked item reason.' );
}
if ( 'review_cloud_batch_result' !== ( $patch['operator_next_action'] ?? '' ) ) {
	$fail( 'Cloud Batch patch should preserve operator next action.' );
}
if ( false !== ( $patch['retryable'] ?? true ) ) {
	$fail( 'Cloud Batch patch should keep terminal retryability false.' );
}
if ( 'terminal_result_available' !== ( $patch['retry_guidance']['reason'] ?? '' ) ) {
	$fail( 'Cloud Batch patch should preserve retry guidance reason.' );
}
if ( 'core_proposal_required' !== ( $patch['final_write_path'] ?? '' ) ) {
	$fail( 'Cloud Batch patch must keep Core proposal handoff.' );
}
if ( true !== ( $merged['safety']['cloud_called'] ?? false ) ) {
	$fail( 'Merged Morning Brief should record that Cloud detail was used.' );
}
if ( false !== ( $merged['safety']['direct_wordpress_write'] ?? true ) ) {
	$fail( 'Merged Morning Brief must not grant direct writes.' );
}
if ( false !== ( $merged['safety']['cloud_scheduler_truth'] ?? true ) ) {
	$fail( 'Merged Morning Brief must not treat Cloud as scheduler truth.' );
}
if ( false !== ( $merged['safety']['action_scheduler_used'] ?? true ) ) {
	$fail( 'Merged Morning Brief must not mark Action Scheduler as used.' );
}
if ( 'result_ready' !== ( $merged['cloud_runtime']['worker_phase'] ?? '' ) ) {
	$fail( 'Merged Morning Brief should preserve Cloud worker phase.' );
}
if ( 1 !== (int) ( $merged['cloud_runtime']['eligibility_summary']['reviewable_count'] ?? 0 ) ) {
	$fail( 'Merged Morning Brief should preserve Cloud reviewable count.' );
}
if ( 'needs_refresh' !== ( $merged['priorities'][0]['cloud_runtime']['reason_codes'][0] ?? '' ) ) {
	$fail( 'Merged Morning Brief should attach Cloud runtime detail to the matching priority.' );
}
if ( 'cloud_batch_runtime' !== ( $merged['writing_preparation'][0]['source'] ?? '' ) ) {
	$fail( 'Merged Morning Brief should add Cloud writing preparation evidence.' );
}

echo "Nightly inspection Cloud Batch merge: ok\n";
