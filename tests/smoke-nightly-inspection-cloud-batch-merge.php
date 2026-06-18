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
			'action_id'      => 'action_001',
			'object_type'    => 'post',
			'object_id'      => 101,
			'score'          => 62,
			'quality_score'  => 62,
			'severity'       => 'warning',
			'reason_codes'   => array( 'needs_refresh', 'weak_internal_links' ),
			'priority_reason' => 'Lower score with stale content and weak internal links.',
			'recommended_next_action' => 'review_content',
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
	'core_intake_package' => array(
		'contract_version'                 => 'nightly_site_inspection_core_intake_package.v1',
		'selected_review_item_ids'         => array( 'action_001' ),
		'selected_review_items'            => array(
			array(
				'action_id'               => 'action_001',
				'title'                   => 'Stale article',
				'object_type'             => 'post',
				'object_id'               => 101,
				'score'                   => 62,
				'severity'                => 'warning',
				'reason_codes'            => array( 'needs_refresh', 'weak_internal_links' ),
				'evidence_summary'        => 'The post is stale and has weak internal links.',
				'recommended_next_action' => 'review_content',
				'direct_wordpress_write'  => true,
			),
		),
		'target_route'                     => 'core:/proposals/from-plan',
		'target_plan_ability_id'           => 'npcink-toolbox/build-nightly-inspection-review-plan',
		'target_plan_contract'             => 'nightly_site_inspection_core_review_plan.v1',
		'core_review_plan_idempotency_key' => 'nightly-inspection-review-abc123',
		'proposal_created'                 => true,
		'proposal_state_owner'             => 'magick-ai-core',
		'approval_truth'                   => 'wordpress_local',
		'final_write_truth'                => 'wordpress_local',
		'cloud_role'                       => 'runtime_detail',
		'cloud_scheduler_truth'            => true,
		'direct_wordpress_write'           => true,
		'receipt_expectation'              => array(
			'expected_local_receipt' => 'core_proposal_id',
			'receipt_owner'          => 'wordpress_toolbox_local',
			'cloud_receipt_storage'  => 'not_canonical',
		),
	),
	'morning_brief'       => array(
		'priority_queue' => array(
			array(
				'action_id'               => 'action_001',
				'object_type'             => 'post',
				'object_id'               => 101,
				'score'                   => 62,
				'severity'                => 'warning',
				'priority_reason'         => 'Lower score with stale content and weak internal links.',
				'reason_codes'            => array( 'needs_refresh', 'weak_internal_links' ),
				'group_ids'               => array( 'content_quality' ),
				'evidence_summary'        => 'The post is stale and has weak internal links.',
				'recommended_next_action' => 'review_content',
				'direct_wordpress_write'  => false,
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
if ( 1 !== ( $patch['priority_queue_count'] ?? 0 ) ) {
	$fail( 'Cloud Batch patch should expose one priority queue item.' );
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
if ( true !== ( $patch['core_intake_package_available'] ?? false ) ) {
	$fail( 'Cloud Batch patch should expose Core intake package availability.' );
}
if ( 'nightly_site_inspection_core_intake_package.v1' !== ( $patch['core_intake_package']['contract_version'] ?? '' ) ) {
	$fail( 'Cloud Batch patch should preserve the Core intake package contract.' );
}
if ( 'core:/proposals/from-plan' !== ( $patch['core_intake_package']['target_route'] ?? '' ) ) {
	$fail( 'Cloud Batch patch should preserve the Core intake target route.' );
}
if ( false !== ( $patch['core_intake_package']['proposal_created'] ?? true ) ) {
	$fail( 'Cloud Batch patch must not treat Cloud as proposal creator.' );
}
if ( false !== ( $patch['core_intake_package']['direct_wordpress_write'] ?? true ) ) {
	$fail( 'Cloud Batch patch must force Core intake package to review-only.' );
}
if ( false !== ( $patch['core_intake_package']['selected_review_items'][0]['direct_wordpress_write'] ?? true ) ) {
	$fail( 'Cloud Batch patch must force selected Core intake items to review-only.' );
}
if ( false !== ( $patch['core_intake_package']['cloud_scheduler_truth'] ?? true ) ) {
	$fail( 'Cloud Batch patch must not treat Cloud as local scheduler truth.' );
}
if ( 'not_canonical' !== ( $patch['core_intake_package']['receipt_expectation']['cloud_receipt_storage'] ?? '' ) ) {
	$fail( 'Cloud Batch patch should keep Cloud receipt storage non-canonical.' );
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
if ( 1 !== (int) ( $merged['cloud_runtime']['priority_queue_count'] ?? 0 ) ) {
	$fail( 'Merged Morning Brief should count Cloud priority queue items.' );
}
if ( true !== ( $merged['cloud_runtime']['core_intake_package_available'] ?? false ) ) {
	$fail( 'Merged Morning Brief should expose Core intake package availability.' );
}
if ( 'nightly_site_inspection_core_intake_package.v1' !== ( $merged['cloud_runtime']['core_intake_package_contract'] ?? '' ) ) {
	$fail( 'Merged Morning Brief should preserve the Core intake package contract summary.' );
}
if ( 'wordpress_toolbox_local' !== ( $merged['cloud_runtime']['core_intake_receipt_owner'] ?? '' ) ) {
	$fail( 'Merged Morning Brief should preserve the local receipt owner.' );
}
if ( 'action_001' !== ( $merged['priority_queue'][0]['action_id'] ?? '' ) ) {
	$fail( 'Merged Morning Brief should expose the Cloud priority queue.' );
}
if ( false !== ( $merged['priority_queue'][0]['direct_wordpress_write'] ?? true ) ) {
	$fail( 'Merged priority queue items must remain review-only.' );
}
if ( 'cloud_batch_runtime' !== ( $merged['writing_preparation'][0]['source'] ?? '' ) ) {
	$fail( 'Merged Morning Brief should add Cloud writing preparation evidence.' );
}

echo "Nightly inspection Cloud Batch merge: ok\n";
