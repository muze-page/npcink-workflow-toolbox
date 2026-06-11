<?php
/**
 * Export generated AI summary candidates into Promptfoo AI-judge CSV cases.
 *
 * This script is local/offline only. It reads generated eval JSON and prepares
 * model-graded cases. It never calls Cloud or WordPress.
 *
 * @package Npcink_Toolbox
 */

$root        = dirname( __DIR__, 2 );
$script_args = array_slice( $argv ?? array(), 1 );

function npcink_summary_judge_arg_map( array $script_args ): array {
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

function npcink_summary_judge_fail( string $message ): void {
	fwrite( STDERR, "FAIL: {$message}\n" );
	exit( 1 );
}

function npcink_summary_judge_path( string $path, string $root ): string {
	if ( str_starts_with( $path, '/' ) ) {
		return $path;
	}

	return $root . '/' . ltrim( $path, '/' );
}

function npcink_summary_judge_text( string $value, int $max_chars = 3200 ): string {
	$value = trim( preg_replace( '/\s+/u', ' ', $value ) ?? $value );
	if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
		return mb_strlen( $value, 'UTF-8' ) > $max_chars ? mb_substr( $value, 0, $max_chars, 'UTF-8' ) : $value;
	}

	return strlen( $value ) > $max_chars ? substr( $value, 0, $max_chars ) : $value;
}

$arg_map = npcink_summary_judge_arg_map( $script_args );
$input   = npcink_summary_judge_path( (string) ( $arg_map['input'] ?? 'tests/summary-eval/generated/muze-candidates.json' ), $root );
$output  = npcink_summary_judge_path( (string) ( $arg_map['output'] ?? 'tests/summary-eval/generated/promptfoo-judge-cases.csv' ), $root );
$limit   = max( 0, min( 500, (int) ( $arg_map['limit'] ?? 20 ) ) );

if ( ! is_file( $input ) ) {
	npcink_summary_judge_fail( 'Generated summary candidate file not found: ' . $input );
}

$decoded = json_decode( (string) file_get_contents( $input ), true );
if ( ! is_array( $decoded ) || ! is_array( $decoded['samples'] ?? null ) ) {
	npcink_summary_judge_fail( 'Input JSON is invalid or missing samples: ' . $input );
}

$directory = dirname( $output );
if ( ! is_dir( $directory ) && ! mkdir( $directory, 0775, true ) && ! is_dir( $directory ) ) {
	npcink_summary_judge_fail( 'Unable to create output directory: ' . $directory );
}

$handle = fopen( $output, 'wb' );
if ( false === $handle ) {
	npcink_summary_judge_fail( 'Unable to write Promptfoo judge cases: ' . $output );
}

fputcsv(
	$handle,
	array(
		'sample_id',
		'title',
		'candidate_id',
		'summary',
		'article_content',
		'existing_excerpt',
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

	$sample_id        = (string) ( $sample['id'] ?? '' );
	$title            = npcink_summary_judge_text( (string) ( $sample['title'] ?? '' ), 300 );
	$article_content  = npcink_summary_judge_text( (string) ( $sample['content'] ?? '' ) );
	$existing_excerpt = npcink_summary_judge_text( (string) ( $sample['existing_excerpt'] ?? '' ), 500 );

	if ( isset( $sample['generation_error'] ) ) {
		fputcsv(
			$handle,
			array(
				$sample_id,
				$title,
				'generation_error',
				'ERROR: ' . (string) $sample['generation_error'],
				$article_content,
				$existing_excerpt,
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
				npcink_summary_judge_text( (string) ( $candidate['summary'] ?? '' ), 500 ),
				$article_content,
				$existing_excerpt,
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

echo 'Exported Promptfoo AI judge cases: ' . $output . "\n";
echo 'Cases: ' . $written . "\n";
