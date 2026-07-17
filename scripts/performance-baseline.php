<?php
/**
 * Captures a repeatable REST latency baseline for Npcink Toolbox routes.
 *
 * Required:
 * - NPCINK_TOOLBOX_BASE_URL, for example https://npcink.local
 *
 * Optional authentication:
 * - NPCINK_TOOLBOX_AUTH_COOKIE, copied from a logged-in admin browser session
 * - NPCINK_TOOLBOX_NONCE, a wp_rest nonce for X-WP-Nonce
 *
 * Optional behavior:
 * - NPCINK_TOOLBOX_PERF_SAMPLES=10 measured requests per probe
 * - NPCINK_TOOLBOX_PERF_WARMUPS=1 unmeasured request per probe
 * - NPCINK_TOOLBOX_PERF_INCLUDE_CLOUD=1 to include Cloud-backed probes
 * - NPCINK_TOOLBOX_PERF_ALLOW_ERROR_STATUS=1 to measure known failure paths
 * - NPCINK_TOOLBOX_PERF_INSECURE_TLS=1 for local self-signed HTTPS only
 * - NPCINK_TOOLBOX_PERF_OUTPUT=/path/to/current.jsonl to save JSONL
 * - NPCINK_TOOLBOX_PERF_BASELINE=/path/to/reference.jsonl to compare medians
 * - NPCINK_TOOLBOX_PERF_ENFORCE_REGRESSION=1 to fail candidate regressions
 * - NPCINK_TOOLBOX_PERF_REGRESSION_PERCENT=30 relative regression threshold
 * - NPCINK_TOOLBOX_PERF_REGRESSION_MIN_MS=20 minimum absolute regression
 *
 * @package Npcink_Toolbox
 */

/**
 * Runs the performance baseline command.
 *
 * @return int Process exit code.
 */
function npcink_toolbox_perf_main(): int {
	try {
		$base_url = npcink_toolbox_perf_base_url( (string) getenv( 'NPCINK_TOOLBOX_BASE_URL' ) );
		if ( '' === $base_url ) {
			throw new InvalidArgumentException( 'Set NPCINK_TOOLBOX_BASE_URL to a local or staging WordPress origin.' );
		}

		$include_cloud       = npcink_toolbox_perf_env_flag( 'NPCINK_TOOLBOX_PERF_INCLUDE_CLOUD' );
		$allow_errors        = npcink_toolbox_perf_env_flag( 'NPCINK_TOOLBOX_PERF_ALLOW_ERROR_STATUS' );
		$insecure_tls        = npcink_toolbox_perf_env_flag( 'NPCINK_TOOLBOX_PERF_INSECURE_TLS' );
		$enforce_regression  = npcink_toolbox_perf_env_flag( 'NPCINK_TOOLBOX_PERF_ENFORCE_REGRESSION' );
		$output_path         = (string) getenv( 'NPCINK_TOOLBOX_PERF_OUTPUT' );
		$baseline_path       = (string) getenv( 'NPCINK_TOOLBOX_PERF_BASELINE' );
		$cookie              = (string) getenv( 'NPCINK_TOOLBOX_AUTH_COOKIE' );
		$nonce               = (string) getenv( 'NPCINK_TOOLBOX_NONCE' );
		$sample_count        = npcink_toolbox_perf_env_int( 'NPCINK_TOOLBOX_PERF_SAMPLES', 10, 3, 50 );
		$warmup_count        = npcink_toolbox_perf_env_int( 'NPCINK_TOOLBOX_PERF_WARMUPS', 1, 0, 10 );
		$regression_percent  = npcink_toolbox_perf_env_float( 'NPCINK_TOOLBOX_PERF_REGRESSION_PERCENT', 30.0, 0.0, 1000.0 );
		$regression_min_ms   = npcink_toolbox_perf_env_float( 'NPCINK_TOOLBOX_PERF_REGRESSION_MIN_MS', 20.0, 0.0, 60000.0 );

		if ( $enforce_regression && '' === $baseline_path ) {
			throw new InvalidArgumentException( 'NPCINK_TOOLBOX_PERF_ENFORCE_REGRESSION=1 requires NPCINK_TOOLBOX_PERF_BASELINE.' );
		}

		$references = '' === $baseline_path ? array() : npcink_toolbox_perf_load_references( $baseline_path );
		$probes     = npcink_toolbox_perf_probes( $include_cloud );
		if ( '' !== $baseline_path ) {
			npcink_toolbox_perf_validate_reference_set( $references, $probes );
		}
		$namespace  = '/wp-json/npcink-toolbox/v1';
		$records    = array();
		if ( $include_cloud ) {
			$cloud_probe_count = count( $probes ) - 1;
			fwrite(
				STDERR,
				'NOTICE: Cloud probes enabled; ' . $cloud_probe_count . ' Cloud-backed routes will each receive ' . $warmup_count . ' warmup(s) and ' . $sample_count . " measured request(s). This may consume provider quota.\n"
			);
		}

		foreach ( $probes as $probe ) {
			$warmups = array();
			for ( $index = 0; $index < $warmup_count; $index++ ) {
				$warmups[] = npcink_toolbox_perf_probe( $base_url . $namespace . $probe['path'], $probe, $cookie, $nonce, $insecure_tls );
			}

			$samples = array();
			for ( $index = 0; $index < $sample_count; $index++ ) {
				$samples[] = npcink_toolbox_perf_probe( $base_url . $namespace . $probe['path'], $probe, $cookie, $nonce, $insecure_tls );
			}

			$record = npcink_toolbox_perf_summarize( $base_url . $namespace . $probe['path'], $probe, $warmups, $samples );
			$record = npcink_toolbox_perf_compare(
				$record,
				'' !== $baseline_path,
				$references[ (string) $probe['name'] ] ?? null,
				$regression_percent,
				$regression_min_ms,
				$enforce_regression
			);
			$records[] = $record;
		}

		$jsonl = npcink_toolbox_perf_jsonl( $records );
		if ( '' !== $output_path ) {
			npcink_toolbox_perf_write_output( $output_path, $jsonl );
		}
		echo $jsonl;

		$failures = array();
		foreach ( $records as $record ) {
			foreach ( npcink_toolbox_perf_failures( $record, $allow_errors, $enforce_regression ) as $failure ) {
				$failures[] = $record['name'] . ': ' . $failure;
			}
		}

		if ( array() !== $failures ) {
			fwrite( STDERR, "Performance baseline failed:\n- " . implode( "\n- ", $failures ) . "\n" );
			return 1;
		}

		return 0;
	} catch ( InvalidArgumentException | RuntimeException $exception ) {
		fwrite( STDERR, $exception->getMessage() . "\n" );
		return 2;
	}
}

/**
 * Returns the fixed probe set.
 *
 * @param bool $include_cloud Whether to include explicit Cloud probes.
 * @return array<int,array<string,mixed>>
 */
function npcink_toolbox_perf_probes( bool $include_cloud ): array {
	$probes = array(
		array(
			'name'   => 'status',
			'method' => 'GET',
			'path'   => '/status',
		),
	);

	if ( ! $include_cloud ) {
		return $probes;
	}

	$probes[] = array(
		'name'   => 'site_knowledge_status',
		'method' => 'GET',
		'path'   => '/site-knowledge/status',
	);
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

	return $probes;
}

/**
 * Runs one REST probe.
 *
 * @param string              $url URL.
 * @param array<string,mixed> $probe Probe definition.
 * @param string              $cookie Cookie header.
 * @param string              $nonce REST nonce.
 * @param bool                $insecure_tls Whether to skip TLS verification for local self-signed certs.
 * @return array{status:int,duration_ms:float,bytes:int,json_valid:bool}
 */
function npcink_toolbox_perf_probe( string $url, array $probe, string $cookie, string $nonce, bool $insecure_tls ): array {
	$headers = array(
		'Accept: application/json',
		'User-Agent: npcink-toolbox-performance-baseline/2.0',
	);
	$body = '';
	if ( 'POST' === $probe['method'] ) {
		$body      = (string) json_encode( $probe['body'] ?? array() );
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
			'follow_location' => 0,
			'max_redirects' => 0,
		),
	);
	if ( $insecure_tls ) {
		$options['ssl'] = array(
			'verify_peer'      => false,
			'verify_peer_name' => false,
		);
	}

	$started  = hrtime( true );
	$response = @file_get_contents( $url, false, stream_context_create( $options ) );
	$duration = round( ( hrtime( true ) - $started ) / 1000000, 1 );
	$status   = npcink_toolbox_perf_status_code( $http_response_header ?? array() );
	$json_valid = false;
	if ( is_string( $response ) ) {
		json_decode( $response, true );
		$json_valid = JSON_ERROR_NONE === json_last_error();
	}

	return array(
		'status'      => $status,
		'duration_ms' => $duration,
		'bytes'       => is_string( $response ) ? strlen( $response ) : 0,
		'json_valid'  => $json_valid,
	);
}

/**
 * Summarizes warmups and measured samples.
 *
 * @param string                               $url Probe URL.
 * @param array<string,mixed>                  $probe Probe definition.
 * @param array<int,array<string,int|float>>   $warmups Warmup results.
 * @param array<int,array<string,int|float>>   $samples Measured results.
 * @return array<string,mixed>
 */
function npcink_toolbox_perf_summarize( string $url, array $probe, array $warmups, array $samples ): array {
	$all_results = array_merge( $warmups, $samples );
	$statuses    = array_values(
		array_unique(
			array_map(
				static fn( array $sample ): int => (int) $sample['status'],
				$all_results
			)
		)
	);
	sort( $statuses, SORT_NUMERIC );

	$durations = array_map(
		static fn( array $sample ): float => (float) $sample['duration_ms'],
		$samples
	);
	$bytes = array_map(
		static fn( array $sample ): float => (float) $sample['bytes'],
		$samples
	);
	$json_valid = ! in_array(
		false,
		array_map(
			static fn( array $sample ): bool => ! empty( $sample['json_valid'] ),
			$all_results
		),
		true
	);
	$request_path = (string) parse_url( $url, PHP_URL_PATH );

	return array(
		'schema_version'    => 'npcink_toolbox_perf.v2',
		'generated_at'      => gmdate( 'c' ),
		'name'              => (string) $probe['name'],
		'method'            => (string) $probe['method'],
		'origin'            => npcink_toolbox_perf_origin( $url ),
		'path'              => $request_path,
		'cloud_probe'       => 'status' !== (string) $probe['name'],
		'probe_signature'   => npcink_toolbox_perf_probe_signature( $probe, $request_path ),
		'warmup_count'      => count( $warmups ),
		'sample_count'      => count( $samples ),
		'status'            => 1 === count( $statuses ) ? $statuses[0] : null,
		'status_codes'      => $statuses,
		'status_consistent' => 1 === count( $statuses ),
		'response_json_valid' => $json_valid,
		'median_ms'         => round( npcink_toolbox_perf_median( $durations ), 1 ),
		'p95_ms'            => round( npcink_toolbox_perf_percentile( $durations, 0.95 ), 1 ),
		'min_ms'            => round( min( $durations ), 1 ),
		'max_ms'            => round( max( $durations ), 1 ),
		'samples_ms'        => $durations,
		'median_bytes'      => round( npcink_toolbox_perf_median( $bytes ), 1 ),
	);
}

/**
 * Adds an optional reference comparison to a record.
 *
 * @param array<string,mixed> $record Current record.
 * @param bool                $reference_requested Whether a reference file was requested.
 * @param array<string,mixed>|null $reference Reference record.
 * @param float               $threshold_percent Relative threshold.
 * @param float               $threshold_min_ms Absolute threshold.
 * @param bool                $enforced Whether candidate regressions fail the command.
 * @return array<string,mixed>
 */
function npcink_toolbox_perf_compare( array $record, bool $reference_requested, ?array $reference, float $threshold_percent, float $threshold_min_ms, bool $enforced ): array {
	$comparison = array(
		'result'                => 'not_compared',
		'reference_median_ms'   => null,
		'delta_ms'              => null,
		'delta_percent'         => null,
		'threshold_percent'     => $threshold_percent,
		'threshold_min_ms'      => $threshold_min_ms,
		'candidate_regression'  => false,
		'enforced'              => $enforced,
	);

	if ( ! $reference_requested ) {
		$record['comparison'] = $comparison;
		return $record;
	}

	if ( null === $reference ) {
		$comparison['result'] = 'missing_reference';
		$record['comparison'] = $comparison;
		return $record;
	}

	if ( empty( $record['status_consistent'] ) || empty( $record['response_json_valid'] ) || ! is_int( $record['status'] ?? null ) ) {
		$comparison['result'] = 'current_sample_invalid';
		$record['comparison'] = $comparison;
		return $record;
	}

	$identity_fields = array( 'schema_version', 'origin', 'method', 'path', 'cloud_probe', 'probe_signature', 'sample_count', 'warmup_count', 'status' );
	foreach ( $identity_fields as $field ) {
		if ( ! array_key_exists( $field, $reference ) || $record[ $field ] !== $reference[ $field ] ) {
			throw new InvalidArgumentException( 'Performance baseline reference mismatch for ' . $record['name'] . ': ' . $field . '.' );
		}
	}
	if ( empty( $reference['response_json_valid'] ) ) {
		throw new InvalidArgumentException( 'Performance baseline reference for ' . $record['name'] . ' did not record valid JSON responses.' );
	}
	if ( ! is_numeric( $reference['median_ms'] ?? null ) || (float) $reference['median_ms'] <= 0.0 ) {
		throw new InvalidArgumentException( 'Performance baseline reference for ' . $record['name'] . ' has no positive median_ms.' );
	}

	$reference_median                  = (float) $reference['median_ms'];
	$current_median                    = (float) $record['median_ms'];
	$delta_ms                          = round( $current_median - $reference_median, 1 );
	$delta_percent                     = $reference_median > 0.0 ? round( ( $delta_ms / $reference_median ) * 100, 1 ) : null;
	$candidate                         = null !== $delta_percent && $delta_ms > $threshold_min_ms && $delta_percent > $threshold_percent;
	$comparison['result']              = $candidate ? 'candidate_regression' : 'pass';
	$comparison['reference_median_ms'] = $reference_median;
	$comparison['delta_ms']            = $delta_ms;
	$comparison['delta_percent']       = $delta_percent;
	$comparison['candidate_regression'] = $candidate;
	$record['comparison']              = $comparison;

	return $record;
}

/**
 * Returns hard gate failures for a summarized record.
 *
 * @param array<string,mixed> $record Record.
 * @param bool                $allow_errors Whether known HTTP error responses are allowed.
 * @param bool                $enforce_regression Whether relative regression candidates fail.
 * @return array<int,string>
 */
function npcink_toolbox_perf_failures( array $record, bool $allow_errors, bool $enforce_regression ): array {
	$failures = array();
	$statuses = is_array( $record['status_codes'] ?? null ) ? $record['status_codes'] : array();

	if ( in_array( 0, $statuses, true ) || array() === $statuses ) {
		$failures[] = 'one or more warmup/sample requests returned no HTTP status';
	}
	if ( empty( $record['status_consistent'] ) ) {
		$failures[] = 'warmup/sample HTTP statuses were inconsistent (' . implode( ', ', array_map( 'strval', $statuses ) ) . ')';
	}
	if ( empty( $record['response_json_valid'] ) ) {
		$failures[] = 'one or more warmup/sample responses were not valid JSON';
	}
	foreach ( $statuses as $status ) {
		$status = (int) $status;
		if ( $status >= 200 && $status < 300 ) {
			continue;
		}
		if ( $status >= 400 && $status < 600 && ! empty( $record['cloud_probe'] ) && $allow_errors ) {
			continue;
		}
		if ( $status >= 400 && $status < 600 && ! empty( $record['cloud_probe'] ) ) {
			$failures[] = 'Cloud HTTP ' . $status . ' requires NPCINK_TOOLBOX_PERF_ALLOW_ERROR_STATUS=1 for an intentional failure-path baseline';
		} else {
			$failures[] = 'HTTP ' . $status . ' is not an accepted response for this probe';
		}
		break;
	}

	$comparison = is_array( $record['comparison'] ?? null ) ? $record['comparison'] : array();
	if ( $enforce_regression && 'missing_reference' === ( $comparison['result'] ?? '' ) ) {
		$failures[] = 'the enforced baseline file has no matching reference record';
	}
	if ( $enforce_regression && ! empty( $comparison['candidate_regression'] ) ) {
		$failures[] = 'median regression exceeded both the relative and absolute thresholds';
	}

	return $failures;
}

/**
 * Loads per-probe reference records from JSONL.
 *
 * @param string $path Baseline JSONL path.
 * @return array<string,array<string,mixed>>
 */
function npcink_toolbox_perf_load_references( string $path ): array {
	if ( ! is_file( $path ) || ! is_readable( $path ) ) {
		throw new InvalidArgumentException( 'Performance baseline reference is not readable: ' . $path );
	}

	$lines      = file( $path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
	$references = array();
	if ( false === $lines ) {
		throw new RuntimeException( 'Could not read performance baseline reference: ' . $path );
	}

	foreach ( $lines as $line_number => $line ) {
		$record = json_decode( $line, true );
		if ( ! is_array( $record ) || ! isset( $record['name'] ) ) {
			throw new InvalidArgumentException( 'Invalid performance baseline JSONL record at line ' . ( $line_number + 1 ) . '.' );
		}

		if ( 'npcink_toolbox_perf.v2' !== ( $record['schema_version'] ?? null ) ) {
			throw new InvalidArgumentException( 'Performance baseline record ' . $record['name'] . ' does not use schema npcink_toolbox_perf.v2.' );
		}
		$required_fields = array( 'origin', 'method', 'path', 'cloud_probe', 'probe_signature', 'sample_count', 'warmup_count', 'status', 'status_codes', 'status_consistent', 'response_json_valid', 'median_ms', 'samples_ms' );
		foreach ( $required_fields as $field ) {
			if ( ! array_key_exists( $field, $record ) ) {
				throw new InvalidArgumentException( 'Performance baseline record ' . $record['name'] . ' is missing ' . $field . '.' );
			}
		}
		if ( ! is_string( $record['origin'] ) || '' === $record['origin'] || ! is_string( $record['probe_signature'] ) || '' === $record['probe_signature'] ) {
			throw new InvalidArgumentException( 'Performance baseline record ' . $record['name'] . ' has an invalid origin or probe signature.' );
		}
		if ( ! is_string( $record['method'] ) || '' === $record['method'] || ! is_string( $record['path'] ) || '' === $record['path'] || ! is_bool( $record['cloud_probe'] ) ) {
			throw new InvalidArgumentException( 'Performance baseline record ' . $record['name'] . ' has invalid method, path, or Cloud identity metadata.' );
		}
		if ( ! is_int( $record['sample_count'] ) || $record['sample_count'] < 3 || ! is_int( $record['warmup_count'] ) || $record['warmup_count'] < 0 || ! is_int( $record['status'] ) ) {
			throw new InvalidArgumentException( 'Performance baseline record ' . $record['name'] . ' has invalid sample, warmup, or HTTP status metadata.' );
		}
		if ( true !== $record['status_consistent'] || array( $record['status'] ) !== $record['status_codes'] ) {
			throw new InvalidArgumentException( 'Performance baseline record ' . $record['name'] . ' did not record one stable HTTP status.' );
		}
		if ( ! is_array( $record['samples_ms'] ) || count( $record['samples_ms'] ) !== $record['sample_count'] || count( array_filter( $record['samples_ms'], 'is_numeric' ) ) !== $record['sample_count'] ) {
			throw new InvalidArgumentException( 'Performance baseline record ' . $record['name'] . ' does not contain the declared measured samples.' );
		}
		if ( true !== $record['response_json_valid'] ) {
			throw new InvalidArgumentException( 'Performance baseline record ' . $record['name'] . ' did not record valid JSON responses.' );
		}
		if ( ! is_numeric( $record['median_ms'] ) || (float) $record['median_ms'] <= 0.0 ) {
			throw new InvalidArgumentException( 'Performance baseline record ' . $record['name'] . ' has no positive median_ms.' );
		}
		if ( isset( $references[ (string) $record['name'] ] ) ) {
			throw new InvalidArgumentException( 'Performance baseline contains duplicate probe name: ' . $record['name'] );
		}
		$references[ (string) $record['name'] ] = $record;
	}

	return $references;
}

/**
 * Ensures a reference contains exactly the requested probe set.
 *
 * @param array<string,array<string,mixed>> $references Reference records.
 * @param array<int,array<string,mixed>>     $probes Requested probes.
 * @return void
 */
function npcink_toolbox_perf_validate_reference_set( array $references, array $probes ): void {
	$reference_names = array_keys( $references );
	$probe_names     = array_map(
		static fn( array $probe ): string => (string) $probe['name'],
		$probes
	);
	sort( $reference_names, SORT_STRING );
	sort( $probe_names, SORT_STRING );
	if ( $reference_names !== $probe_names ) {
		throw new InvalidArgumentException( 'Performance baseline probe set does not match the current probe set.' );
	}
}

/**
 * Encodes records as JSONL.
 *
 * @param array<int,array<string,mixed>> $records Records.
 * @return string
 */
function npcink_toolbox_perf_jsonl( array $records ): string {
	$jsonl = '';
	foreach ( $records as $record ) {
		$encoded = json_encode( $record, JSON_UNESCAPED_SLASHES );
		if ( false === $encoded ) {
			throw new RuntimeException( 'Could not encode performance baseline record.' );
		}
		$jsonl .= $encoded . "\n";
	}

	return $jsonl;
}

/**
 * Writes output to disk.
 *
 * @param string $path Output path.
 * @param string $jsonl JSONL payload.
 * @return void
 */
function npcink_toolbox_perf_write_output( string $path, string $jsonl ): void {
	$directory = dirname( $path );
	if ( ! is_dir( $directory ) && ! mkdir( $directory, 0755, true ) && ! is_dir( $directory ) ) {
		throw new RuntimeException( 'Could not create performance output directory: ' . $directory );
	}
	if ( false === file_put_contents( $path, $jsonl, LOCK_EX ) ) {
		throw new RuntimeException( 'Could not write performance output: ' . $path );
	}
}

/**
 * Computes a median.
 *
 * @param array<int,int|float> $values Values.
 * @return float
 */
function npcink_toolbox_perf_median( array $values ): float {
	if ( array() === $values ) {
		throw new InvalidArgumentException( 'Cannot calculate a median from an empty sample.' );
	}
	sort( $values, SORT_NUMERIC );
	$count  = count( $values );
	$middle = intdiv( $count, 2 );
	if ( 1 === $count % 2 ) {
		return (float) $values[ $middle ];
	}

	return ( (float) $values[ $middle - 1 ] + (float) $values[ $middle ] ) / 2;
}

/**
 * Computes a nearest-rank percentile.
 *
 * @param array<int,int|float> $values Values.
 * @param float                $percentile Percentile from zero to one.
 * @return float
 */
function npcink_toolbox_perf_percentile( array $values, float $percentile ): float {
	if ( array() === $values ) {
		throw new InvalidArgumentException( 'Cannot calculate a percentile from an empty sample.' );
	}
	if ( $percentile <= 0.0 || $percentile > 1.0 ) {
		throw new InvalidArgumentException( 'Percentile must be greater than zero and no more than one.' );
	}
	sort( $values, SORT_NUMERIC );
	$index = max( 0, (int) ceil( $percentile * count( $values ) ) - 1 );

	return (float) $values[ $index ];
}

/**
 * Extracts the HTTP status code from response headers.
 *
 * @param array<int,string> $headers Headers.
 * @return int
 */
function npcink_toolbox_perf_status_code( array $headers ): int {
	$status = 0;
	foreach ( $headers as $header ) {
		if ( preg_match( '#^HTTP/\S+\s+(\d{3})#', $header, $matches ) ) {
			$status = (int) $matches[1];
		}
	}

	return $status;
}

/**
 * Validates and normalizes a WordPress base URL.
 *
 * @param string $url Candidate URL.
 * @return string
 */
function npcink_toolbox_perf_base_url( string $url ): string {
	$url = rtrim( trim( $url ), '/' );
	if ( '' === $url ) {
		return '';
	}
	$parts = parse_url( $url );
	if (
		! is_array( $parts ) ||
		! in_array( strtolower( (string) ( $parts['scheme'] ?? '' ) ), array( 'http', 'https' ), true ) ||
		'' === (string) ( $parts['host'] ?? '' ) ||
		isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['query'] ) || isset( $parts['fragment'] )
	) {
		throw new InvalidArgumentException( 'NPCINK_TOOLBOX_BASE_URL must be an HTTP(S) origin or WordPress base URL without credentials, query, or fragment.' );
	}

	return $url;
}

/**
 * Returns a canonical HTTP origin.
 *
 * @param string $url URL.
 * @return string
 */
function npcink_toolbox_perf_origin( string $url ): string {
	$parts  = parse_url( $url );
	$scheme = strtolower( (string) ( $parts['scheme'] ?? '' ) );
	$host   = strtolower( (string) ( $parts['host'] ?? '' ) );
	$port   = isset( $parts['port'] ) ? (int) $parts['port'] : null;
	if ( '' === $scheme || '' === $host ) {
		throw new InvalidArgumentException( 'Could not derive a performance target origin.' );
	}
	if ( ( 'http' === $scheme && 80 === $port ) || ( 'https' === $scheme && 443 === $port ) ) {
		$port = null;
	}

	return $scheme . '://' . $host . ( null === $port ? '' : ':' . $port );
}

/**
 * Hashes the request identity without retaining its body.
 *
 * @param array<string,mixed> $probe Probe definition.
 * @param string              $request_path Full request path.
 * @return string
 */
function npcink_toolbox_perf_probe_signature( array $probe, string $request_path ): string {
	$identity = array(
		'method'      => strtoupper( (string) $probe['method'] ),
		'path'        => $request_path,
		'body'        => npcink_toolbox_perf_canonicalize( $probe['body'] ?? array() ),
		'cloud_probe' => 'status' !== (string) $probe['name'],
	);
	$encoded = json_encode( $identity, JSON_UNESCAPED_SLASHES );
	if ( false === $encoded ) {
		throw new RuntimeException( 'Could not encode a performance probe signature.' );
	}

	return hash( 'sha256', $encoded );
}

/**
 * Sorts associative payloads recursively for stable signatures.
 *
 * @param mixed $value Value.
 * @return mixed
 */
function npcink_toolbox_perf_canonicalize( $value ) {
	if ( ! is_array( $value ) ) {
		return $value;
	}
	$is_list = array() === $value || array_keys( $value ) === range( 0, count( $value ) - 1 );
	if ( ! $is_list ) {
		ksort( $value, SORT_STRING );
	}
	foreach ( $value as $key => $item ) {
		$value[ $key ] = npcink_toolbox_perf_canonicalize( $item );
	}

	return $value;
}

/**
 * Reads a strict 0/1 environment flag.
 *
 * @param string $name Environment variable.
 * @return bool
 */
function npcink_toolbox_perf_env_flag( string $name ): bool {
	$value = (string) getenv( $name );
	if ( '' === $value || '0' === $value ) {
		return false;
	}
	if ( '1' === $value ) {
		return true;
	}

	throw new InvalidArgumentException( $name . ' must be 0 or 1.' );
}

/**
 * Reads a bounded integer environment value.
 *
 * @param string $name Environment variable.
 * @param int    $default Default value.
 * @param int    $minimum Minimum value.
 * @param int    $maximum Maximum value.
 * @return int
 */
function npcink_toolbox_perf_env_int( string $name, int $default, int $minimum, int $maximum ): int {
	$value = (string) getenv( $name );
	if ( '' === $value ) {
		return $default;
	}
	if ( ! preg_match( '/^-?\d+$/', $value ) ) {
		throw new InvalidArgumentException( $name . ' must be an integer.' );
	}
	$number = (int) $value;
	if ( $number < $minimum || $number > $maximum ) {
		throw new InvalidArgumentException( $name . ' must be between ' . $minimum . ' and ' . $maximum . '.' );
	}

	return $number;
}

/**
 * Reads a bounded float environment value.
 *
 * @param string $name Environment variable.
 * @param float  $default Default value.
 * @param float  $minimum Minimum value.
 * @param float  $maximum Maximum value.
 * @return float
 */
function npcink_toolbox_perf_env_float( string $name, float $default, float $minimum, float $maximum ): float {
	$value = (string) getenv( $name );
	if ( '' === $value ) {
		return $default;
	}
	if ( ! is_numeric( $value ) ) {
		throw new InvalidArgumentException( $name . ' must be numeric.' );
	}
	$number = (float) $value;
	if ( $number < $minimum || $number > $maximum ) {
		throw new InvalidArgumentException( $name . ' must be between ' . $minimum . ' and ' . $maximum . '.' );
	}

	return $number;
}

if ( isset( $_SERVER['SCRIPT_FILENAME'] ) && realpath( (string) $_SERVER['SCRIPT_FILENAME'] ) === __FILE__ ) {
	exit( npcink_toolbox_perf_main() );
}
