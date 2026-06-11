<?php
/**
 * Export generated AI summary candidates into Promptfoo CSV test cases.
 *
 * This script is local/offline only. It never calls Cloud or WordPress.
 *
 * @package Npcink_Toolbox
 */

$root        = dirname( __DIR__, 2 );
$script_args = array_slice( $argv ?? array(), 1 );

function npcink_summary_promptfoo_arg_map( array $script_args ): array {
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

function npcink_summary_promptfoo_fail( string $message ): void {
	fwrite( STDERR, "FAIL: {$message}\n" );
	exit( 1 );
}

function npcink_summary_promptfoo_path( string $path, string $root ): string {
	if ( str_starts_with( $path, '/' ) ) {
		return $path;
	}

	return $root . '/' . ltrim( $path, '/' );
}

$arg_map = npcink_summary_promptfoo_arg_map( $script_args );
$input   = npcink_summary_promptfoo_path( (string) ( $arg_map['input'] ?? 'tests/summary-eval/generated/muze-candidates.json' ), $root );
$output  = npcink_summary_promptfoo_path( (string) ( $arg_map['output'] ?? 'tests/summary-eval/generated/promptfoo-cases.csv' ), $root );
$limit   = max( 0, min( 500, (int) ( $arg_map['limit'] ?? 50 ) ) );

if ( ! is_file( $input ) ) {
	npcink_summary_promptfoo_fail( 'Generated summary candidate file not found: ' . $input );
}

$decoded = json_decode( (string) file_get_contents( $input ), true );
if ( ! is_array( $decoded ) || ! is_array( $decoded['samples'] ?? null ) ) {
	npcink_summary_promptfoo_fail( 'Input JSON is invalid or missing samples: ' . $input );
}

$directory = dirname( $output );
if ( ! is_dir( $directory ) && ! mkdir( $directory, 0775, true ) && ! is_dir( $directory ) ) {
	npcink_summary_promptfoo_fail( 'Unable to create output directory: ' . $directory );
}

$handle = fopen( $output, 'wb' );
if ( false === $handle ) {
	npcink_summary_promptfoo_fail( 'Unable to write Promptfoo cases: ' . $output );
}

fputcsv(
	$handle,
	array(
		'sample_id',
		'title',
		'candidate_id',
		'summary',
		'generation_error',
	),
	',',
	'"',
	'\\'
);

$written = 0;
foreach ( $decoded['samples'] as $sample ) {
	if ( ! is_array( $sample ) ) {
		continue;
	}
	if ( $limit > 0 && $written >= $limit ) {
		break;
	}

	$sample_id = (string) ( $sample['id'] ?? '' );
	$title     = (string) ( $sample['title'] ?? '' );

	if ( isset( $sample['generation_error'] ) ) {
		fputcsv(
			$handle,
			array(
				$sample_id,
				$title,
				'generation_error',
				'ERROR: ' . (string) $sample['generation_error'],
				(string) $sample['generation_error'],
			),
			',',
			'"',
			'\\'
		);
		$written++;
		continue;
	}

	$candidates = is_array( $sample['candidates'] ?? null ) ? $sample['candidates'] : array();
	foreach ( $candidates as $candidate ) {
		if ( ! is_array( $candidate ) ) {
			continue;
		}
		if ( $limit > 0 && $written >= $limit ) {
			break 2;
		}

		fputcsv(
			$handle,
			array(
				$sample_id,
				$title,
				(string) ( $candidate['id'] ?? '' ),
				trim( (string) ( $candidate['summary'] ?? '' ) ),
				'',
			),
			',',
			'"',
			'\\'
		);
		$written++;
	}
}

fclose( $handle );

echo 'Exported Promptfoo cases: ' . $output . "\n";
echo 'Cases: ' . $written . "\n";
