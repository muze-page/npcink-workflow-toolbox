<?php
/**
 * Local WordPress smoke for the Pro Nightly Inspection Cloud Batch E2E path.
 *
 * Requires a verified Npcink Cloud Addon connection and a running Cloud runtime worker.
 * Run with: wp eval-file tests/smoke-nightly-inspection-cloud-e2e.php
 *
 * @package Npcink_Toolbox
 */

$fail = static function ( string $message ): void {
	fwrite( STDERR, $message . "\n" );
	exit( 1 );
};

$pass = static function ( string $message ): void {
	echo '[ok] ' . $message . "\n";
};

$assert = static function ( bool $condition, string $message ) use ( $fail, $pass ): void {
	if ( ! $condition ) {
		$fail( $message );
	}
	$pass( $message );
};

$flag = static function ( array $payload, string $key, bool $default = false ): bool {
	if ( array_key_exists( $key, $payload ) ) {
		return (bool) $payload[ $key ];
	}
	if ( isset( $payload['safety'] ) && is_array( $payload['safety'] ) && array_key_exists( $key, $payload['safety'] ) ) {
		return (bool) $payload['safety'][ $key ];
	}

	return $default;
};

if ( ! class_exists( '\Npcink_Toolbox\Settings' ) || ! class_exists( '\Npcink_Toolbox\Provider_Client' ) ) {
	$fail( 'Toolbox Settings and Provider_Client classes must be loaded.' );
}
if ( ! class_exists( '\Npcink\LocalAutomationRuntime\NightlyInspection\Snapshot_Collector' ) || ! class_exists( '\Npcink\LocalAutomationRuntime\NightlyInspection\Morning_Brief_Builder' ) ) {
	$fail( 'Local Automation Runtime Nightly Inspection classes must be loaded.' );
}

$settings = new \Npcink_Toolbox\Settings();
$client   = new \Npcink_Toolbox\Provider_Client( $settings );

$entitlement = $client->get_nightly_inspection_cloud_runtime_entitlement();
if ( is_wp_error( $entitlement ) ) {
	$fail( 'Cloud runtime entitlement failed: ' . $entitlement->get_error_code() . ' ' . $entitlement->get_error_message() );
}

$runtime = is_array( $entitlement['pro_cloud_runtime'] ?? null ) ? $entitlement['pro_cloud_runtime'] : array();
$assert( 'nightly_site_inspection' === (string) ( $runtime['feature_id'] ?? '' ), 'Cloud entitlement exposes the Nightly Site Inspection runtime feature.' );
$assert( ! empty( $entitlement['submit_allowed'] ), 'Cloud entitlement allows a Pro Nightly Inspection batch submit.' );

$collector = new \Npcink\LocalAutomationRuntime\NightlyInspection\Snapshot_Collector();
$builder   = new \Npcink\LocalAutomationRuntime\NightlyInspection\Morning_Brief_Builder();
$snapshot  = $collector->collect( 3, 2 );
$brief     = $builder->build( $snapshot );

$submit = $client->submit_nightly_inspection_cloud_batch(
	$snapshot,
	array(
		'idempotency_key' => 'nightly-cloud-e2e-' . gmdate( 'YmdHis' ) . '-' . substr( md5( (string) microtime( true ) ), 0, 8 ),
		'payload_mode'    => 'metadata_only',
		'retention_ttl'   => 86400,
		'source'          => 'toolbox_smoke',
	)
);
if ( is_wp_error( $submit ) ) {
	$fail( 'Cloud batch submit failed: ' . $submit->get_error_code() . ' ' . $submit->get_error_message() );
}

$run_id = sanitize_text_field( (string) ( $submit['cloud_run']['run_id'] ?? $submit['run_id'] ?? '' ) );
$assert( '' !== $run_id, 'Cloud batch submit returns a run id.' );
$assert( false === $flag( $submit, 'direct_wordpress_write' ), 'Cloud batch submit does not grant direct WordPress writes.' );
$assert( false === $flag( $submit, 'cloud_scheduler_truth' ), 'Cloud batch submit does not make Cloud the local scheduler truth.' );

$status = array();
for ( $attempt = 1; $attempt <= 12; ++$attempt ) {
	$status = $client->get_nightly_inspection_cloud_batch_status( $run_id );
	if ( is_wp_error( $status ) ) {
		$fail( 'Cloud batch status failed: ' . $status->get_error_code() . ' ' . $status->get_error_message() );
	}

	if ( in_array( (string) ( $status['status'] ?? '' ), array( 'succeeded', 'failed', 'canceled' ), true ) ) {
		break;
	}

	sleep( 2 );
}

$assert( 'succeeded' === (string) ( $status['status'] ?? '' ), 'Cloud runtime worker processes the Nightly Inspection batch to succeeded.' );
$assert( false === $flag( $status, 'direct_wordpress_write' ), 'Cloud batch polling remains read-only.' );
$assert( false === $flag( $status, 'cloud_scheduler_truth' ), 'Cloud batch polling does not become scheduler truth.' );

$result = $client->get_nightly_inspection_cloud_batch_result( $run_id, $brief );
if ( is_wp_error( $result ) ) {
	$fail( 'Cloud batch result failed: ' . $result->get_error_code() . ' ' . $result->get_error_message() );
}

$patch  = is_array( $result['morning_brief_patch'] ?? null ) ? $result['morning_brief_patch'] : array();
$merged = is_array( $result['merged_morning_brief'] ?? null ) ? $result['merged_morning_brief'] : array();

$assert( 'cloud_batch_runtime_result.v1' === (string) ( $result['result']['contract_version'] ?? '' ), 'Cloud result exposes the Cloud Batch Runtime result contract.' );
$assert( 'nightly_site_inspection_cloud_batch_merge.v1' === (string) ( $patch['contract_version'] ?? '' ), 'Toolbox normalizes the Morning Brief Cloud merge patch.' );
$assert( 1 <= (int) ( $patch['action_count'] ?? 0 ), 'Cloud merge patch includes at least one review action.' );
$assert( ! empty( $merged ), 'Toolbox returns a merged Morning Brief preview.' );
$assert( true === (bool) ( $merged['safety']['cloud_called'] ?? false ), 'Merged Morning Brief records Cloud runtime detail usage.' );
$assert( false === (bool) ( $merged['safety']['direct_wordpress_write'] ?? true ), 'Merged Morning Brief does not grant direct WordPress writes.' );
$assert( false === (bool) ( $merged['safety']['cloud_scheduler_truth'] ?? true ), 'Merged Morning Brief does not make Cloud the scheduler truth.' );
$assert( true === (bool) ( $result['safety']['requires_local_review'] ?? false ), 'Cloud Batch result still requires local review.' );

echo 'Nightly inspection Cloud Batch E2E smoke passed: ' . $run_id . "\n";
