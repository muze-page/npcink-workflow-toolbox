<?php
/**
 * Compare two Promptfoo AI-judge result JSON files.
 *
 * This script is local/offline only. It never calls Cloud or WordPress.
 *
 * @package Npcink_Toolbox
 */

$root        = dirname( __DIR__, 3 );
$script_args = array_slice( $argv ?? array(), 1 );

function npcink_summary_judge_compare_arg_map( array $script_args ): array {
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

function npcink_summary_judge_compare_fail( string $message ): void {
	fwrite( STDERR, "FAIL: {$message}\n" );
	exit( 1 );
}

function npcink_summary_judge_compare_path( string $path, string $root ): string {
	if ( str_starts_with( $path, '/' ) ) {
		return $path;
	}

	return $root . '/' . ltrim( $path, '/' );
}

function npcink_summary_judge_compare_score_label( ?float $score ): int {
	if ( null === $score ) {
		return 0;
	}
	if ( $score < 0.2 ) {
		return 1;
	}
	if ( $score < 0.4 ) {
		return 2;
	}
	if ( $score < 0.6 ) {
		return 3;
	}
	if ( $score < 0.8 ) {
		return 4;
	}

	return 5;
}

function npcink_summary_judge_compare_reason( array $result ): string {
	foreach ( array(
		$result['gradingResult']['reason'] ?? null,
		$result['gradingResult']['comment'] ?? null,
		$result['reason'] ?? null,
		$result['error'] ?? null,
	) as $value ) {
		if ( is_string( $value ) && '' !== trim( $value ) ) {
			return trim( preg_replace( '/\s+/u', ' ', $value ) ?? $value );
		}
	}

	return '';
}

function npcink_summary_judge_compare_result_rows( string $path, string $root ): array {
	$absolute = npcink_summary_judge_compare_path( $path, $root );
	if ( ! is_file( $absolute ) ) {
		npcink_summary_judge_compare_fail( 'Judge result file not found: ' . $absolute );
	}

	$decoded = json_decode( (string) file_get_contents( $absolute ), true );
	if ( ! is_array( $decoded ) ) {
		npcink_summary_judge_compare_fail( 'Judge result JSON is invalid: ' . $absolute );
	}

	$results = is_array( $decoded['results']['results'] ?? null ) ? $decoded['results']['results'] : array();
	if ( array() === $results && is_array( $decoded['results'] ?? null ) ) {
		$results = $decoded['results'];
	}

	$rows = array();
	foreach ( $results as $result ) {
		if ( ! is_array( $result ) ) {
			continue;
		}

		$vars = is_array( $result['vars'] ?? null ) ? $result['vars'] : array();
		if ( array() === $vars && is_array( $result['testCase']['vars'] ?? null ) ) {
			$vars = $result['testCase']['vars'];
		}

		$sample_id    = (string) ( $vars['sample_id'] ?? '' );
		$candidate_id = (string) ( $vars['candidate_id'] ?? '' );
		if ( '' === $sample_id || '' === $candidate_id ) {
			continue;
		}

		$score = isset( $result['score'] ) && is_numeric( $result['score'] ) ? (float) $result['score'] : null;
		$key   = $sample_id . '|' . $candidate_id;
		$rows[ $key ] = array(
			'sample_id'    => $sample_id,
			'title'        => (string) ( $vars['title'] ?? '' ),
			'candidate_id' => $candidate_id,
			'summary'      => (string) ( $vars['summary'] ?? '' ),
			'score'        => $score,
			'score_1_5'    => npcink_summary_judge_compare_score_label( $score ),
			'success'      => (bool) ( $result['success'] ?? false ),
			'reason'       => npcink_summary_judge_compare_reason( $result ),
		);
	}

	return $rows;
}

function npcink_summary_judge_compare_risky_reason( string $reason ): bool {
	foreach ( array( '事实错误', '误导', '逻辑混乱', '不忠于', 'unsupported', 'misleading', 'confusing' ) as $needle ) {
		if ( false !== mb_stripos( $reason, $needle, 0, 'UTF-8' ) ) {
			return true;
		}
	}

	return false;
}

$arg_map         = npcink_summary_judge_compare_arg_map( $script_args );
$primary_path    = (string) ( $arg_map['primary'] ?? 'dev/evaluation/summary/generated/promptfoo-judge-gpt55.json' );
$secondary_path  = (string) ( $arg_map['secondary'] ?? 'dev/evaluation/summary/generated/promptfoo-judge-deepseek.json' );
$output_json     = npcink_summary_judge_compare_path( (string) ( $arg_map['output_json'] ?? 'dev/evaluation/summary/generated/promptfoo-judge-cross.json' ), $root );
$output_csv      = npcink_summary_judge_compare_path( (string) ( $arg_map['output_csv'] ?? 'dev/evaluation/summary/generated/promptfoo-judge-cross.csv' ), $root );
$primary_label   = (string) ( $arg_map['primary_label'] ?? 'gpt55' );
$secondary_label = (string) ( $arg_map['secondary_label'] ?? 'deepseek' );

$primary_rows   = npcink_summary_judge_compare_result_rows( $primary_path, $root );
$secondary_rows = npcink_summary_judge_compare_result_rows( $secondary_path, $root );
$keys           = array_values( array_unique( array_merge( array_keys( $primary_rows ), array_keys( $secondary_rows ) ) ) );
sort( $keys );

$items = array();
foreach ( $keys as $key ) {
	$primary   = $primary_rows[ $key ] ?? null;
	$secondary = $secondary_rows[ $key ] ?? null;
	$base      = $primary ?? $secondary ?? array();

	$primary_score   = is_array( $primary ) ? (int) $primary['score_1_5'] : 0;
	$secondary_score = is_array( $secondary ) ? (int) $secondary['score_1_5'] : 0;
	$score_gap       = min( $primary_score, $secondary_score ) > 0 ? abs( $primary_score - $secondary_score ) : null;

	$review_reasons = array();
	if ( ! is_array( $primary ) || ! is_array( $secondary ) ) {
		$review_reasons[] = 'missing_judge_result';
	}
	if ( is_array( $primary ) && ! $primary['success'] ) {
		$review_reasons[] = $primary_label . '_failed';
	}
	if ( is_array( $secondary ) && ! $secondary['success'] ) {
		$review_reasons[] = $secondary_label . '_failed';
	}
	if ( $primary_score > 0 && $primary_score <= 2 ) {
		$review_reasons[] = $primary_label . '_low_score';
	}
	if ( $secondary_score > 0 && $secondary_score <= 2 ) {
		$review_reasons[] = $secondary_label . '_low_score';
	}
	if ( null !== $score_gap && $score_gap >= 2 ) {
		$review_reasons[] = 'score_gap_' . $score_gap;
	}
	if ( is_array( $primary ) && npcink_summary_judge_compare_risky_reason( $primary['reason'] ) ) {
		$review_reasons[] = $primary_label . '_risky_reason';
	}
	if ( is_array( $secondary ) && npcink_summary_judge_compare_risky_reason( $secondary['reason'] ) ) {
		$review_reasons[] = $secondary_label . '_risky_reason';
	}

	$items[] = array(
		'sample_id'          => (string) ( $base['sample_id'] ?? '' ),
		'title'              => (string) ( $base['title'] ?? '' ),
		'candidate_id'       => (string) ( $base['candidate_id'] ?? '' ),
		'summary'            => (string) ( $base['summary'] ?? '' ),
		$primary_label . '_score' => $primary_score,
		$secondary_label . '_score' => $secondary_score,
		'score_gap'          => $score_gap,
		'needs_human_review' => array() !== $review_reasons,
		'review_reasons'     => array_values( array_unique( $review_reasons ) ),
		$primary_label . '_reason' => is_array( $primary ) ? (string) $primary['reason'] : '',
		$secondary_label . '_reason' => is_array( $secondary ) ? (string) $secondary['reason'] : '',
	);
}

$summary = array(
	'version'                  => 1,
	'created_at'               => gmdate( 'c' ),
	'primary_label'            => $primary_label,
	'secondary_label'          => $secondary_label,
	'candidate_count'          => count( $items ),
	'needs_human_review_count' => count( array_filter( $items, static fn( $item ) => (bool) $item['needs_human_review'] ) ),
	'items'                    => $items,
);

foreach ( array( $output_json, $output_csv ) as $output ) {
	$directory = dirname( $output );
	if ( ! is_dir( $directory ) && ! mkdir( $directory, 0775, true ) && ! is_dir( $directory ) ) {
		npcink_summary_judge_compare_fail( 'Unable to create output directory: ' . $directory );
	}
}

$encoded = json_encode( $summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
if ( ! is_string( $encoded ) || false === file_put_contents( $output_json, $encoded . "\n" ) ) {
	npcink_summary_judge_compare_fail( 'Unable to write cross-judge JSON: ' . $output_json );
}

$handle = fopen( $output_csv, 'wb' );
if ( false === $handle ) {
	npcink_summary_judge_compare_fail( 'Unable to write cross-judge CSV: ' . $output_csv );
}
fwrite( $handle, "\xEF\xBB\xBF" );
fputcsv(
	$handle,
	array(
		'样本ID',
		'标题',
		'候选ID',
		'摘要',
		$primary_label . '评分',
		$secondary_label . '评分',
		'分差',
		'需人工复核',
		'复核原因',
		$primary_label . '原因',
		$secondary_label . '原因',
	),
	',',
	'"',
	'\\'
);
foreach ( $items as $item ) {
	fputcsv(
		$handle,
		array(
			$item['sample_id'],
			$item['title'],
			$item['candidate_id'],
			$item['summary'],
			$item[ $primary_label . '_score' ],
			$item[ $secondary_label . '_score' ],
			$item['score_gap'],
			$item['needs_human_review'] ? '是' : '否',
			implode( ';', $item['review_reasons'] ),
			$item[ $primary_label . '_reason' ],
			$item[ $secondary_label . '_reason' ],
		),
		',',
		'"',
		'\\'
	);
}
fclose( $handle );

echo 'Compared AI judge results: ' . count( $items ) . " candidate(s)\n";
echo 'Needs human review: ' . $summary['needs_human_review_count'] . "\n";
echo 'Cross-judge JSON: ' . $output_json . "\n";
echo 'Cross-judge CSV: ' . $output_csv . "\n";
