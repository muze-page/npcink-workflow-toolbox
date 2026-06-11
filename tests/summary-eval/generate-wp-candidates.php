<?php
/**
 * Generate AI summary candidates for exported WordPress summary-eval samples.
 *
 * This script calls the existing editor content-support REST route. It stores
 * candidates in a local eval JSON file only; it never writes post excerpts.
 *
 * Run through WP-CLI:
 * wp --path=/path/to/wp eval-file tests/summary-eval/generate-wp-candidates.php -- input=tests/summary-eval/generated/muze-source-samples.json output=tests/summary-eval/generated/muze-candidates.json limit=10
 *
 * @package Npcink_Toolbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "FAIL: Run this script through WP-CLI eval-file so WordPress is loaded.\n" );
	exit( 1 );
}

$script_args = isset( $args ) && is_array( $args ) ? $args : array_slice( $argv ?? array(), 1 );
$root        = dirname( __DIR__, 2 );

function npcink_summary_generate_arg_map( array $script_args ): array {
	$parsed = array();
	foreach ( $script_args as $arg ) {
		$arg = (string) $arg;
		if ( ! str_contains( $arg, '=' ) ) {
			continue;
		}
		list( $key, $value ) = explode( '=', $arg, 2 );
		$parsed[ trim( $key ) ] = trim( $value );
	}

	return $parsed;
}

function npcink_summary_generate_fail( string $message ): void {
	fwrite( STDERR, "FAIL: {$message}\n" );
	exit( 1 );
}

function npcink_summary_generate_path( string $path, string $root ): string {
	if ( str_starts_with( $path, '/' ) ) {
		return $path;
	}

	return $root . '/' . ltrim( $path, '/' );
}

function npcink_summary_generate_admin_user_id(): int {
	$users = get_users(
		array(
			'role'   => 'administrator',
			'number' => 1,
			'fields' => 'ID',
		)
	);

	return isset( $users[0] ) ? (int) $users[0] : 0;
}

function npcink_summary_generate_excerpt_items( array $data ): array {
	$section = is_array( $data['sections']['summary_terms_optimization'] ?? null )
		? $data['sections']['summary_terms_optimization']
		: array();
	$layers  = is_array( $section['summary_layers'] ?? null ) ? $section['summary_layers'] : array();
	$items   = is_array( $layers['items'] ?? null ) ? $layers['items'] : array();

	$candidates = array();
	foreach ( $items as $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}
		$value = trim( (string) ( $item['value'] ?? '' ) );
		if ( '' === $value ) {
			continue;
		}
		$candidates[] = array(
			'id'      => sanitize_key( (string) ( $item['id'] ?? 'ai_summary' ) ),
			'summary' => $value,
			'label'   => sanitize_text_field( (string) ( $item['label'] ?? '' ) ),
			'reason'  => sanitize_text_field( (string) ( $item['reason'] ?? '' ) ),
		);
	}

	return $candidates;
}

function npcink_summary_generate_diagnostics( array $data ): array {
	$section = is_array( $data['sections']['summary_terms_optimization'] ?? null )
		? $data['sections']['summary_terms_optimization']
		: array();
	$summary = is_array( $section['summary_candidates'] ?? null ) ? $section['summary_candidates'] : array();

	$output_text = (string) ( $summary['output_text'] ?? '' );
	if ( function_exists( 'mb_substr' ) ) {
		$output_text = mb_substr( $output_text, 0, 500, 'UTF-8' );
	} else {
		$output_text = substr( $output_text, 0, 500 );
	}

	return array(
		'artifact_type'       => sanitize_text_field( (string) ( $data['artifact_type'] ?? '' ) ),
		'intent'              => sanitize_key( (string) ( $data['intent'] ?? '' ) ),
		'section_keys'        => array_values( array_map( 'sanitize_key', array_keys( $section ) ) ),
		'summary_status'      => sanitize_key( (string) ( $summary['status'] ?? '' ) ),
		'summary_message'     => sanitize_text_field( (string) ( $summary['message'] ?? '' ) ),
		'summary_output_text' => sanitize_text_field( $output_text ),
		'provider_execution'  => sanitize_key( (string) ( $section['provider_execution'] ?? '' ) ),
		'generation_mode'     => sanitize_key( (string) ( $section['generation_mode'] ?? '' ) ),
	);
}

function npcink_summary_generate_rest( array $sample ): array {
	$request = new WP_REST_Request( 'POST', '/npcink-toolbox/v1/editor/content-support' );
	$params  = array(
		'intent'             => 'summary_suggestions',
		'post_id'            => absint( $sample['post_id'] ?? 0 ),
		'post_type'          => sanitize_key( (string) ( $sample['post_type'] ?? 'post' ) ),
		'post_status'        => sanitize_key( (string) ( $sample['post_status'] ?? '' ) ),
		'title'              => (string) ( $sample['title'] ?? '' ),
		'excerpt'            => (string) ( $sample['existing_excerpt'] ?? '' ),
		'content'            => (string) ( $sample['content'] ?? '' ),
		'query'              => 'Generate a reader-facing WordPress excerpt for this published article preview.',
		'generation_variant' => 'summary-eval-' . (string) ( $sample['id'] ?? wp_generate_uuid4() ) . '-' . time(),
	);
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}

	$response = rest_do_request( $request );
	if ( is_wp_error( $response ) ) {
		return array(
			'ok'    => false,
			'error' => $response->get_error_code() . ': ' . $response->get_error_message(),
		);
	}

	$data = $response->get_data();
	if ( $response->get_status() < 200 || $response->get_status() >= 300 || ! is_array( $data ) ) {
		return array(
			'ok'    => false,
			'error' => 'REST status ' . $response->get_status(),
			'data'  => is_array( $data ) ? $data : array(),
		);
	}

	$candidates = npcink_summary_generate_excerpt_items( $data );
	if ( array() === $candidates ) {
		return array(
			'ok'          => false,
			'error'       => 'No summary candidate returned.',
			'diagnostics' => npcink_summary_generate_diagnostics( $data ),
		);
	}

	return array(
		'ok'         => true,
		'candidates' => $candidates,
		'run_id'     => sanitize_text_field( (string) ( $data['run_id'] ?? '' ) ),
	);
}

function npcink_summary_generate_retryable_error( array $result ): bool {
	$message = strtolower(
		(string) ( $result['error'] ?? '' ) . ' ' .
		(string) ( $result['diagnostics']['summary_message'] ?? '' )
	);

	return str_contains( $message, 'max active cloud runs' )
		|| str_contains( $message, 'timeout' )
		|| str_contains( $message, 'temporarily' );
}

$arg_map = npcink_summary_generate_arg_map( $script_args );
$input   = npcink_summary_generate_path( (string) ( $arg_map['input'] ?? 'tests/summary-eval/generated/muze-source-samples.json' ), $root );
$output  = npcink_summary_generate_path( (string) ( $arg_map['output'] ?? 'tests/summary-eval/generated/muze-candidates.json' ), $root );
$limit   = max( 1, min( 200, (int) ( $arg_map['limit'] ?? 10 ) ) );
$offset  = max( 0, (int) ( $arg_map['offset'] ?? 0 ) );
$retries = max( 0, min( 10, (int) ( $arg_map['retries'] ?? 3 ) ) );
$retry_sleep = max( 0, min( 60, (int) ( $arg_map['retry_sleep'] ?? 8 ) ) );
$sample_sleep = max( 0, min( 60, (int) ( $arg_map['sample_sleep'] ?? 2 ) ) );

if ( ! is_file( $input ) ) {
	npcink_summary_generate_fail( 'Input summary source samples not found: ' . $input );
}

$decoded = json_decode( (string) file_get_contents( $input ), true );
if ( ! is_array( $decoded ) || ! is_array( $decoded['samples'] ?? null ) ) {
	npcink_summary_generate_fail( 'Input summary source samples JSON is invalid: ' . $input );
}

$admin_user_id = npcink_summary_generate_admin_user_id();
if ( 0 >= $admin_user_id ) {
	npcink_summary_generate_fail( 'No local administrator is available for REST permission checks.' );
}
wp_set_current_user( $admin_user_id );

$source_samples = array_slice( $decoded['samples'], $offset, $limit );
if ( array() === $source_samples ) {
	npcink_summary_generate_fail( 'No source samples selected.' );
}

$generated = $decoded;
$generated['generated_at'] = gmdate( 'c' );
$generated['generation']   = array(
	'type'       => 'editor_summary_suggestions_rest',
	'route'      => '/npcink-toolbox/v1/editor/content-support',
	'limit'      => $limit,
	'offset'     => $offset,
	'retries'    => $retries,
	'retry_sleep' => $retry_sleep,
	'sample_sleep' => $sample_sleep,
	'admin_user' => $admin_user_id,
);
$generated['samples']      = array();

$errors = 0;
foreach ( $source_samples as $sample ) {
	if ( ! is_array( $sample ) ) {
		$errors++;
		continue;
	}

	$result = array();
	for ( $attempt = 0; $attempt <= $retries; $attempt++ ) {
		$result = npcink_summary_generate_rest( $sample );
		if ( (bool) ( $result['ok'] ?? false ) || ! npcink_summary_generate_retryable_error( $result ) || $attempt >= $retries ) {
			break;
		}

		echo 'WAIT: sample=' . (string) ( $sample['id'] ?? 'unknown' ) . ' retry=' . ( $attempt + 1 ) . ' sleep=' . $retry_sleep . "s\n";
		if ( 0 < $retry_sleep ) {
			sleep( $retry_sleep );
		}
	}
	if ( ! (bool) ( $result['ok'] ?? false ) ) {
		$errors++;
		$sample['generation_error']       = (string) ( $result['error'] ?? 'Unknown generation error.' );
		$sample['generation_diagnostics'] = is_array( $result['diagnostics'] ?? null ) ? $result['diagnostics'] : array();
		echo 'FAIL: sample=' . (string) ( $sample['id'] ?? 'unknown' ) . ' error=' . $sample['generation_error'] . "\n";
		$generated['samples'][] = $sample;
		continue;
	}

	if ( 'site_export' === (string) ( $sample['content_type'] ?? '' ) ) {
		$length_min = (int) ( $sample['length']['min'] ?? 60 );
		if ( ! is_array( $sample['length'] ?? null ) ) {
			$sample['length'] = array( 'min' => 60, 'max' => 160 );
		} elseif ( $length_min > 60 ) {
			$sample['length']['min'] = 60;
		}
	}

	$sample['candidates'] = array_values( $result['candidates'] );
	$sample['generation_run_id'] = (string) ( $result['run_id'] ?? '' );
	$generated['samples'][] = $sample;
	echo 'PASS: sample=' . (string) ( $sample['id'] ?? 'unknown' ) . ' candidates=' . count( $sample['candidates'] ) . "\n";
	if ( 0 < $sample_sleep ) {
		sleep( $sample_sleep );
	}
}

$directory = dirname( $output );
if ( ! is_dir( $directory ) && ! mkdir( $directory, 0775, true ) && ! is_dir( $directory ) ) {
	npcink_summary_generate_fail( 'Unable to create output directory: ' . $directory );
}

$encoded = wp_json_encode( $generated, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
if ( ! is_string( $encoded ) || false === file_put_contents( $output, $encoded . "\n" ) ) {
	npcink_summary_generate_fail( 'Unable to write output: ' . $output );
}

if ( 0 < $errors ) {
	npcink_summary_generate_fail( "{$errors} summary generation sample(s) failed. Output written for inspection: " . $output );
}

echo 'Generated summary candidates for ' . count( $generated['samples'] ) . ' sample(s): ' . $output . "\n";
