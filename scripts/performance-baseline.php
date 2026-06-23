<?php
/**
 * Captures a small REST latency baseline for Npcink Toolbox routes.
 *
 * Required:
 * - NPCINK_TOOLBOX_BASE_URL, for example https://npcink.local
 *
 * Optional authentication:
 * - NPCINK_TOOLBOX_AUTH_COOKIE, copied from a logged-in admin browser session
 * - NPCINK_TOOLBOX_NONCE, a wp_rest nonce for X-WP-Nonce
 *
 * Optional behavior:
 * - NPCINK_TOOLBOX_PERF_INCLUDE_CLOUD=1 to include Cloud-backed POST probes
 * - NPCINK_TOOLBOX_PERF_ALLOW_ERROR_STATUS=1 to measure known failure paths
 * - NPCINK_TOOLBOX_PERF_INSECURE_TLS=1 for local self-signed HTTPS only
 * - NPCINK_TOOLBOX_PERF_OUTPUT=/path/to/baseline.jsonl to save JSONL
 *
 * @package Npcink_Toolbox
 */

$base_url = rtrim( (string) getenv( 'NPCINK_TOOLBOX_BASE_URL' ), '/' );
if ( '' === $base_url ) {
	fwrite( STDERR, "Set NPCINK_TOOLBOX_BASE_URL to a local or staging WordPress origin.\n" );
	exit( 2 );
}

$include_cloud = '1' === (string) getenv( 'NPCINK_TOOLBOX_PERF_INCLUDE_CLOUD' );
$allow_errors  = '1' === (string) getenv( 'NPCINK_TOOLBOX_PERF_ALLOW_ERROR_STATUS' );
$insecure_tls  = '1' === (string) getenv( 'NPCINK_TOOLBOX_PERF_INSECURE_TLS' );
$output_path   = (string) getenv( 'NPCINK_TOOLBOX_PERF_OUTPUT' );
$cookie        = (string) getenv( 'NPCINK_TOOLBOX_AUTH_COOKIE' );
$nonce         = (string) getenv( 'NPCINK_TOOLBOX_NONCE' );
$namespace     = '/wp-json/npcink-toolbox/v1';

$probes = array(
	array(
		'name'   => 'status',
		'method' => 'GET',
		'path'   => '/status',
	),
	array(
		'name'   => 'site_knowledge_status',
		'method' => 'GET',
		'path'   => '/site-knowledge/status',
	),
);

if ( $include_cloud ) {
	$probes[] = array(
		'name'   => 'site_knowledge_search',
		'method' => 'POST',
		'path'   => '/site-knowledge/search',
		'body'   => array(
			'query'       => 'performance baseline',
			'max_results' => 1,
		),
	);
	$probes[] = array(
		'name'   => 'ai_content_support_summary',
		'method' => 'POST',
		'path'   => '/ai/content-support',
		'body'   => array(
			'intent'  => 'summary_suggestions',
			'title'   => 'Performance baseline',
			'excerpt' => 'Small authenticated smoke for timeout and unavailable-path behavior.',
			'content' => 'This local performance baseline checks the authenticated Toolbox REST path without creating WordPress content or changing site state.',
		),
	);
	$probes[] = array(
		'name'   => 'image_candidates_fast_first',
		'method' => 'POST',
		'path'   => '/image-candidates',
		'body'   => array(
			'query'        => 'performance baseline',
			'provider'     => 'cloud',
			'per_page'     => 1,
			'latency_mode' => 'fast_first',
		),
	);
}

$records = array();
foreach ( $probes as $probe ) {
	$records[] = npcink_toolbox_perf_probe( $base_url . $namespace . $probe['path'], $probe, $cookie, $nonce, $insecure_tls );
}

$jsonl = '';
foreach ( $records as $record ) {
	$jsonl .= json_encode( $record, JSON_UNESCAPED_SLASHES ) . "\n";
}

if ( '' !== $output_path ) {
	$directory = dirname( $output_path );
	if ( ! is_dir( $directory ) && ! mkdir( $directory, 0755, true ) && ! is_dir( $directory ) ) {
		fwrite( STDERR, "Could not create output directory: {$directory}\n" );
		exit( 1 );
	}
	file_put_contents( $output_path, $jsonl );
}

echo $jsonl;

$failed = array_filter(
	$records,
	static fn( array $record ): bool => 0 === (int) $record['status'] || ( ! $allow_errors && (int) $record['status'] >= 400 )
);
if ( array() !== $failed ) {
	fwrite( STDERR, "One or more Toolbox REST probes did not return a successful HTTP status. Check the URL, TLS trust, authentication, payloads, or set NPCINK_TOOLBOX_PERF_ALLOW_ERROR_STATUS=1 for known failure-path baselines.\n" );
	exit( 1 );
}

$slow = array_filter(
	$records,
	static fn( array $record ): bool => (float) $record['duration_ms'] > 2500
);
if ( array() !== $slow ) {
	fwrite( STDERR, "One or more Toolbox REST probes exceeded 2500ms. Inspect the JSONL output before release.\n" );
	exit( 1 );
}

/**
 * Runs one REST probe.
 *
 * @param string $url URL.
 * @param array<string,mixed> $probe Probe definition.
 * @param string $cookie Cookie header.
 * @param string $nonce REST nonce.
 * @param bool   $insecure_tls Whether to skip TLS verification for local self-signed certs.
 * @return array<string,mixed>
 */
function npcink_toolbox_perf_probe( string $url, array $probe, string $cookie, string $nonce, bool $insecure_tls ): array {
	$headers = array(
		'Accept: application/json',
		'User-Agent: npcink-toolbox-performance-baseline/1.0',
	);
	$body = '';
	if ( 'POST' === $probe['method'] ) {
		$body      = json_encode( $probe['body'] ?? array() );
		$headers[] = 'Content-Type: application/json';
	}
	if ( '' !== $cookie ) {
		$headers[] = 'Cookie: ' . $cookie;
	}
	if ( '' !== $nonce ) {
		$headers[] = 'X-WP-Nonce: ' . $nonce;
	}

	$options = array(
		'http' => array(
			'method'        => (string) $probe['method'],
			'header'        => implode( "\r\n", $headers ),
			'content'       => $body,
			'timeout'       => 12,
			'ignore_errors' => true,
		),
	);
	if ( $insecure_tls ) {
		$options['ssl'] = array(
			'verify_peer'      => false,
			'verify_peer_name' => false,
		);
	}

	$started  = microtime( true );
	$response = @file_get_contents( $url, false, stream_context_create( $options ) );
	$duration = round( ( microtime( true ) - $started ) * 1000, 1 );
	$status   = npcink_toolbox_perf_status_code( $http_response_header ?? array() );

	return array(
		'generated_at' => gmdate( 'c' ),
		'name'         => (string) $probe['name'],
		'method'       => (string) $probe['method'],
		'url'          => $url,
		'status'       => $status,
		'duration_ms'  => $duration,
		'bytes'        => is_string( $response ) ? strlen( $response ) : 0,
		'cloud_probe'  => ! in_array( (string) $probe['name'], array( 'status', 'site_knowledge_status' ), true ),
	);
}

/**
 * Extracts the HTTP status code from response headers.
 *
 * @param array<int,string> $headers Headers.
 * @return int
 */
function npcink_toolbox_perf_status_code( array $headers ): int {
	foreach ( $headers as $header ) {
		if ( preg_match( '#^HTTP/\S+\s+(\d{3})#', $header, $matches ) ) {
			return (int) $matches[1];
		}
	}

	return 0;
}
