<?php
/**
 * Focused behavior checks for the repeatable performance baseline.
 *
 * @package Npcink_Toolbox
 */

require_once dirname( __DIR__ ) . '/scripts/performance-baseline.php';

/**
 * Fails the focused test process when a condition is false.
 *
 * @param bool   $condition Condition.
 * @param string $message Message.
 * @return void
 */
function npcink_toolbox_perf_test_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, 'FAIL: ' . $message . "\n" );
		exit( 1 );
	}
	echo 'PASS: ' . $message . "\n";
}

/**
 * Asserts that a callable throws an expected exception.
 *
 * @param callable $callback Callback.
 * @param string   $message Message.
 * @return void
 */
function npcink_toolbox_perf_test_throws( callable $callback, string $message ): void {
	try {
		$callback();
	} catch ( InvalidArgumentException | RuntimeException $exception ) {
		echo 'PASS: ' . $message . "\n";
		return;
	}

	fwrite( STDERR, 'FAIL: ' . $message . "\n" );
	exit( 1 );
}

/**
 * Builds a summarized local or Cloud record.
 *
 * @param string     $name Probe name.
 * @param array<int> $statuses Measured statuses.
 * @param bool       $json_valid Whether responses were valid JSON.
 * @return array<string,mixed>
 */
function npcink_toolbox_perf_test_record( string $name = 'status', array $statuses = array( 200, 200, 200 ), bool $json_valid = true ): array {
	$probe = array(
		'name'   => $name,
		'method' => 'status' === $name ? 'GET' : 'POST',
		'path'   => 'status' === $name ? '/status' : '/site-knowledge/search',
		'body'   => 'status' === $name ? array() : array( 'query' => 'performance baseline' ),
	);
	$samples = array();
	foreach ( $statuses as $index => $status ) {
		$samples[] = array(
			'status'      => $status,
			'duration_ms' => ( $index + 1 ) * 10,
			'bytes'       => 100 + $index,
			'json_valid'  => $json_valid,
		);
	}

	return npcink_toolbox_perf_summarize(
		'https://npcink.local/wp-json/npcink-toolbox/v1' . $probe['path'],
		$probe,
		array(),
		$samples
	);
}

npcink_toolbox_perf_test_assert( 3.0 === npcink_toolbox_perf_median( array( 5, 1, 3 ) ), 'Median handles odd sample counts.' );
npcink_toolbox_perf_test_assert( 4.0 === npcink_toolbox_perf_median( array( 7, 1, 5, 3 ) ), 'Median averages the middle values for even sample counts.' );
npcink_toolbox_perf_test_assert( 10.0 === npcink_toolbox_perf_percentile( range( 1, 10 ), 0.95 ), 'P95 uses the deterministic nearest-rank method.' );

putenv( 'NPCINK_TOOLBOX_PERF_SAMPLES' );
npcink_toolbox_perf_test_assert( 10 === npcink_toolbox_perf_env_int( 'NPCINK_TOOLBOX_PERF_SAMPLES', 10, 3, 50 ), 'The default measured sample count is ten.' );
putenv( 'NPCINK_TOOLBOX_PERF_SAMPLES=2' );
npcink_toolbox_perf_test_throws(
	static fn(): int => npcink_toolbox_perf_env_int( 'NPCINK_TOOLBOX_PERF_SAMPLES', 10, 3, 50 ),
	'Sample counts below the repeatability floor are rejected.'
);
putenv( 'NPCINK_TOOLBOX_PERF_SAMPLES' );

$local_probes = npcink_toolbox_perf_probes( false );
$cloud_probes = npcink_toolbox_perf_probes( true );
npcink_toolbox_perf_test_assert( array( 'status' ) === array_column( $local_probes, 'name' ), 'The default probe set is local-only.' );
npcink_toolbox_perf_test_assert( 5 === count( $cloud_probes ) && in_array( 'site_knowledge_status', array_column( $cloud_probes, 'name' ), true ), 'Cloud-backed status and action probes require explicit opt-in.' );
$ordered_signature = npcink_toolbox_perf_probe_signature( array( 'name' => 'cloud', 'method' => 'POST', 'body' => array( 'query' => 'x', 'limit' => 1 ) ), '/probe' );
$reordered_signature = npcink_toolbox_perf_probe_signature( array( 'name' => 'cloud', 'method' => 'POST', 'body' => array( 'limit' => 1, 'query' => 'x' ) ), '/probe' );
npcink_toolbox_perf_test_assert( $ordered_signature === $reordered_signature, 'Probe signatures are stable across associative payload key order.' );

$record = npcink_toolbox_perf_test_record();
npcink_toolbox_perf_test_assert( 20.0 === $record['median_ms'] && 30.0 === $record['p95_ms'] && true === $record['response_json_valid'], 'A record reports median, P95, and JSON validity from measured samples.' );
npcink_toolbox_perf_test_assert( 200 === $record['status'] && true === $record['status_consistent'], 'A stable HTTP status is recorded as a deterministic invariant.' );

$mixed_record = npcink_toolbox_perf_test_record( 'status', array( 200, 503, 200 ) );
$mixed_failures = npcink_toolbox_perf_failures( $mixed_record, true, false );
npcink_toolbox_perf_test_assert( 0 < count( $mixed_failures ) && false !== strpos( implode( ' ', $mixed_failures ), 'inconsistent' ), 'Mixed HTTP statuses fail even when failure-path measurement is enabled.' );

$no_status_failures = npcink_toolbox_perf_failures( npcink_toolbox_perf_test_record( 'status', array( 0, 0, 0 ), false ), false, false );
npcink_toolbox_perf_test_assert( false !== strpos( implode( ' ', $no_status_failures ), 'no HTTP status' ), 'Missing HTTP status and invalid JSON fail the hard gate.' );

$redirect_failures = npcink_toolbox_perf_failures( npcink_toolbox_perf_test_record( 'status', array( 302, 302, 302 ) ), true, false );
npcink_toolbox_perf_test_assert( false !== strpos( implode( ' ', $redirect_failures ), 'not an accepted response' ), 'Redirect responses never pass the baseline gate.' );
npcink_toolbox_perf_test_assert( 200 === npcink_toolbox_perf_status_code( array( 'HTTP/1.1 301 Moved', 'HTTP/2 200 OK' ) ), 'Status parsing uses the final response status.' );

$local_error_failures = npcink_toolbox_perf_failures( npcink_toolbox_perf_test_record( 'status', array( 500, 500, 500 ) ), true, false );
npcink_toolbox_perf_test_assert( false !== strpos( implode( ' ', $local_error_failures ), 'not an accepted response' ), 'The local status probe always requires a successful 2xx response.' );
$cloud_error_failures = npcink_toolbox_perf_failures( npcink_toolbox_perf_test_record( 'site_knowledge_search', array( 503, 503, 503 ) ), true, false );
npcink_toolbox_perf_test_assert( array() === $cloud_error_failures, 'An explicitly enabled Cloud probe may measure one stable known 4xx/5xx path.' );

$reference              = $record;
$reference['median_ms'] = 100.0;
$small_reference              = $record;
$small_reference['median_ms'] = 50.0;
$percent_only           = $record;
$percent_only['median_ms'] = 69.0;
$milliseconds_only      = $record;
$milliseconds_only['median_ms'] = 121.0;
$exact_percent          = $record;
$exact_percent['median_ms'] = 130.0;
$exact_milliseconds     = $record;
$exact_milliseconds['median_ms'] = 120.0;
$both_thresholds        = $record;
$both_thresholds['median_ms'] = 131.0;

$percent_only = npcink_toolbox_perf_compare( $percent_only, true, $small_reference, 30.0, 20.0, false );
$milliseconds_only = npcink_toolbox_perf_compare( $milliseconds_only, true, $reference, 30.0, 20.0, false );
$exact_percent = npcink_toolbox_perf_compare( $exact_percent, true, $reference, 30.0, 20.0, false );
$exact_milliseconds = npcink_toolbox_perf_compare( $exact_milliseconds, true, $reference, 30.0, 20.0, false );
$observed_regression = npcink_toolbox_perf_compare( $both_thresholds, true, $reference, 30.0, 20.0, false );
$enforced_regression = npcink_toolbox_perf_compare( $both_thresholds, true, $reference, 30.0, 20.0, true );

npcink_toolbox_perf_test_assert( empty( $percent_only['comparison']['candidate_regression'] ), 'A relative-only increase does not trigger the two-part threshold.' );
npcink_toolbox_perf_test_assert( empty( $milliseconds_only['comparison']['candidate_regression'] ), 'An absolute-only increase does not trigger the two-part threshold.' );
npcink_toolbox_perf_test_assert( empty( $exact_percent['comparison']['candidate_regression'] ), 'Exactly 30 percent does not exceed the strict relative threshold.' );
npcink_toolbox_perf_test_assert( empty( $exact_milliseconds['comparison']['candidate_regression'] ), 'Exactly 20 milliseconds does not exceed the strict absolute threshold.' );
npcink_toolbox_perf_test_assert( ! empty( $observed_regression['comparison']['candidate_regression'] ) && array() === npcink_toolbox_perf_failures( $observed_regression, false, false ), 'Observation mode reports a candidate regression without failing.' );
npcink_toolbox_perf_test_assert( 0 < count( npcink_toolbox_perf_failures( $enforced_regression, false, true ) ), 'Explicit enforcement turns the same candidate regression into a failure.' );

$mismatched_reference           = $reference;
$mismatched_reference['origin'] = 'https://different.local';
npcink_toolbox_perf_test_throws(
	static fn(): array => npcink_toolbox_perf_compare( $record, true, $mismatched_reference, 30.0, 20.0, false ),
	'Cross-origin reference comparisons are rejected.'
);
foreach ( array( 'probe_signature' => 'different', 'sample_count' => 4, 'status' => 201 ) as $field => $value ) {
	$identity_mismatch           = $reference;
	$identity_mismatch[ $field ] = $value;
	npcink_toolbox_perf_test_throws(
		static fn(): array => npcink_toolbox_perf_compare( $record, true, $identity_mismatch, 30.0, 20.0, false ),
		'Reference identity mismatch is rejected for ' . $field . '.'
	);
}

$reference_path = tempnam( sys_get_temp_dir(), 'npcink-perf-reference-' );
npcink_toolbox_perf_test_assert( false !== $reference_path, 'A temporary reference path is available.' );
file_put_contents( $reference_path, npcink_toolbox_perf_jsonl( array( $record ) ) );
$loaded_references = npcink_toolbox_perf_load_references( $reference_path );
npcink_toolbox_perf_test_assert( isset( $loaded_references['status'] ), 'A valid v2 JSONL reference is loaded by probe name.' );
npcink_toolbox_perf_validate_reference_set( $loaded_references, $local_probes );

$legacy_path = tempnam( sys_get_temp_dir(), 'npcink-perf-legacy-' );
npcink_toolbox_perf_test_assert( false !== $legacy_path, 'A temporary legacy path is available.' );
file_put_contents( $legacy_path, json_encode( array( 'name' => 'status', 'duration_ms' => 10 ) ) . "\n" );
npcink_toolbox_perf_test_throws(
	static fn(): array => npcink_toolbox_perf_load_references( $legacy_path ),
	'Legacy single-sample references are rejected instead of silently compared.'
);

$invalid_json_path = tempnam( sys_get_temp_dir(), 'npcink-perf-invalid-json-' );
npcink_toolbox_perf_test_assert( false !== $invalid_json_path, 'A temporary invalid JSON path is available.' );
file_put_contents( $invalid_json_path, "not-json\n" );
npcink_toolbox_perf_test_throws(
	static fn(): array => npcink_toolbox_perf_load_references( $invalid_json_path ),
	'Invalid reference JSONL is rejected.'
);

$duplicate_path = tempnam( sys_get_temp_dir(), 'npcink-perf-duplicate-' );
npcink_toolbox_perf_test_assert( false !== $duplicate_path, 'A temporary duplicate path is available.' );
file_put_contents( $duplicate_path, npcink_toolbox_perf_jsonl( array( $record, $record ) ) );
npcink_toolbox_perf_test_throws(
	static fn(): array => npcink_toolbox_perf_load_references( $duplicate_path ),
	'Duplicate probe references are rejected.'
);
npcink_toolbox_perf_test_throws(
	static fn() => npcink_toolbox_perf_validate_reference_set( $loaded_references, $cloud_probes ),
	'A baseline with a different probe set is rejected.'
);

$sensitive_probe = array(
	'name'   => 'site_knowledge_search',
	'method' => 'POST',
	'path'   => '/site-knowledge/search',
	'body'   => array( 'query' => 'private-payload-value' ),
);
$sensitive_record = npcink_toolbox_perf_summarize(
	'https://npcink.local/wp-json/npcink-toolbox/v1/site-knowledge/search',
	$sensitive_probe,
	array(),
	array(
		array( 'status' => 200, 'duration_ms' => 10, 'bytes' => 100, 'json_valid' => true ),
		array( 'status' => 200, 'duration_ms' => 11, 'bytes' => 100, 'json_valid' => true ),
		array( 'status' => 200, 'duration_ms' => 12, 'bytes' => 100, 'json_valid' => true ),
	)
);
$serialized_record = npcink_toolbox_perf_jsonl( array( $sensitive_record ) );
npcink_toolbox_perf_test_assert( false === strpos( $serialized_record, 'private-payload-value' ), 'Output retains only a request signature, not the request body.' );
npcink_toolbox_perf_test_assert( false === strpos( $serialized_record, 'Cookie' ) && false === strpos( $serialized_record, 'Nonce' ), 'Output does not retain authentication headers.' );

npcink_toolbox_perf_test_throws(
	static fn(): string => npcink_toolbox_perf_base_url( 'https://user:secret@npcink.local/?token=secret' ),
	'Performance target URLs cannot contain credentials, query strings, or fragments.'
);

@unlink( $reference_path );
@unlink( $legacy_path );
@unlink( $invalid_json_path );
@unlink( $duplicate_path );

echo "Performance baseline behavior checks passed.\n";
