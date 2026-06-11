<?php
/**
 * Export generated AI summary candidates into a human review worksheet.
 *
 * This script is local/offline only. It reads generated eval JSON and writes
 * Markdown plus JSON review worksheets. It never calls Cloud or WordPress.
 *
 * @package Npcink_Toolbox
 */

$root        = dirname( __DIR__, 2 );
$script_args = array_slice( $argv ?? array(), 1 );

function npcink_summary_review_arg_map( array $script_args ): array {
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

function npcink_summary_review_fail( string $message ): void {
	fwrite( STDERR, "FAIL: {$message}\n" );
	exit( 1 );
}

function npcink_summary_review_path( string $path, string $root ): string {
	if ( str_starts_with( $path, '/' ) ) {
		return $path;
	}

	return $root . '/' . ltrim( $path, '/' );
}

function npcink_summary_review_text( string $value ): string {
	$value = trim( preg_replace( '/\s+/u', ' ', $value ) ?? $value );

	return str_replace( array( '|', "\r", "\n" ), array( '\|', ' ', ' ' ), $value );
}

function npcink_summary_review_len( string $value ): int {
	return function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
}

function npcink_summary_review_slice( array $items, int $limit ): array {
	if ( $limit <= 0 ) {
		return $items;
	}

	return array_slice( $items, 0, $limit );
}

$arg_map     = npcink_summary_review_arg_map( $script_args );
$input       = npcink_summary_review_path( (string) ( $arg_map['input'] ?? 'tests/summary-eval/generated/muze-candidates.json' ), $root );
$output_md   = npcink_summary_review_path( (string) ( $arg_map['output_md'] ?? 'tests/summary-eval/generated/summary-human-review.md' ), $root );
$output_json = npcink_summary_review_path( (string) ( $arg_map['output_json'] ?? 'tests/summary-eval/generated/summary-human-review.json' ), $root );
$output_csv  = npcink_summary_review_path( (string) ( $arg_map['output_csv'] ?? 'tests/summary-eval/generated/summary-human-review.csv' ), $root );
$limit       = max( 0, min( 500, (int) ( $arg_map['limit'] ?? 50 ) ) );

if ( ! is_file( $input ) ) {
	npcink_summary_review_fail( 'Generated summary candidate file not found: ' . $input );
}

$decoded = json_decode( (string) file_get_contents( $input ), true );
if ( ! is_array( $decoded ) || ! is_array( $decoded['samples'] ?? null ) ) {
	npcink_summary_review_fail( 'Input JSON is invalid or missing samples: ' . $input );
}

$items = array();
foreach ( $decoded['samples'] as $sample ) {
	if ( ! is_array( $sample ) ) {
		continue;
	}

	$sample_id = (string) ( $sample['id'] ?? '' );
	$title     = (string) ( $sample['title'] ?? '' );
	$url       = (string) ( $sample['url'] ?? '' );

	if ( isset( $sample['generation_error'] ) ) {
		$items[] = array(
			'sample_id'      => $sample_id,
			'title'          => $title,
			'url'            => $url,
			'candidate_id'   => 'generation_error',
			'candidate_label' => 'Generation error',
			'length'         => 0,
			'summary'        => '',
			'generation_error' => (string) $sample['generation_error'],
			'decision'       => 'reject',
			'failure_reason' => 'generation_error',
			'edited_excerpt' => '',
			'notes'          => 'No usable summary candidate was returned for this sample.',
		);
		continue;
	}

	$candidates = is_array( $sample['candidates'] ?? null ) ? $sample['candidates'] : array();
	foreach ( $candidates as $candidate ) {
		if ( ! is_array( $candidate ) ) {
			continue;
		}

		$summary = trim( (string) ( $candidate['summary'] ?? '' ) );
		$items[] = array(
			'sample_id'      => $sample_id,
			'title'          => $title,
			'url'            => $url,
			'candidate_id'   => (string) ( $candidate['id'] ?? '' ),
			'candidate_label' => (string) ( $candidate['label'] ?? '' ),
			'length'         => npcink_summary_review_len( $summary ),
			'summary'        => $summary,
			'generation_error' => '',
			'decision'       => '',
			'failure_reason' => '',
			'edited_excerpt' => '',
			'notes'          => '',
		);
	}
}

$items = npcink_summary_review_slice( $items, $limit );

$worksheet = array(
	'version'           => 1,
	'created_at'        => gmdate( 'c' ),
	'source_file'       => $input,
	'candidate_count'   => count( $items ),
	'decision_options'  => array( 'direct_use', 'minor_edit', 'reject' ),
	'failure_reasons'   => array(
		'generation_error',
		'too_generic',
		'missing_core_value',
		'wrong_tone',
		'instruction_like',
		'insufficient_coverage',
		'too_marketing',
		'too_short',
		'too_long',
		'unsupported_claim',
		'other',
	),
	'review_items'      => $items,
);

foreach ( array( $output_md, $output_json ) as $output ) {
	$directory = dirname( $output );
	if ( ! is_dir( $directory ) && ! mkdir( $directory, 0775, true ) && ! is_dir( $directory ) ) {
		npcink_summary_review_fail( 'Unable to create output directory: ' . $directory );
	}
}
foreach ( array( $output_csv ) as $output ) {
	$directory = dirname( $output );
	if ( ! is_dir( $directory ) && ! mkdir( $directory, 0775, true ) && ! is_dir( $directory ) ) {
		npcink_summary_review_fail( 'Unable to create output directory: ' . $directory );
	}
}

$encoded = json_encode( $worksheet, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
if ( ! is_string( $encoded ) || false === file_put_contents( $output_json, $encoded . "\n" ) ) {
	npcink_summary_review_fail( 'Unable to write JSON worksheet: ' . $output_json );
}

$csv_handle = fopen( $output_csv, 'wb' );
if ( false === $csv_handle ) {
	npcink_summary_review_fail( 'Unable to write CSV worksheet: ' . $output_csv );
}
fwrite( $csv_handle, "\xEF\xBB\xBF" );
fputcsv(
	$csv_handle,
	array(
		'样本ID',
		'标题',
		'候选ID',
		'字数',
		'AI摘要',
		'评价',
		'问题类型',
		'人工修改版摘要',
		'备注',
	),
	',',
	'"',
	'\\'
);
foreach ( $items as $item ) {
	$is_generation_error = 'generation_error' === (string) $item['failure_reason'];
	fputcsv(
		$csv_handle,
		array(
			(string) $item['sample_id'],
			(string) $item['title'],
			(string) $item['candidate_id'],
			(int) $item['length'],
			'' !== $item['generation_error'] ? 'ERROR: ' . (string) $item['generation_error'] : (string) $item['summary'],
			$is_generation_error ? '不可用' : '',
			$is_generation_error ? '生成失败' : '',
			'',
			$is_generation_error ? '没有可用摘要候选；通常是 Cloud 返回结构异常、候选被质量门过滤，或该批次使用了旧规则。' : '',
		),
		',',
		'"',
		'\\'
	);
}
fclose( $csv_handle );

$lines   = array();
$lines[] = '# AI 摘要人工评审';
$lines[] = '';
$lines[] = '- Source: `' . $input . '`';
$lines[] = '- Created: `' . $worksheet['created_at'] . '`';
$lines[] = '- 评价选项: `direct_use` 直接可用, `minor_edit` 稍改可用, `reject` 不可用';
$lines[] = '- 问题类型: `generation_error` 生成失败, `too_generic` 太泛, `missing_core_value` 核心价值缺失, `wrong_tone` 语气不合适, `instruction_like` 像说明书, `insufficient_coverage` 覆盖不足, `too_marketing` 太营销, `too_short` 太短, `too_long` 太长, `unsupported_claim` 无依据, `other` 其他';
$lines[] = '';
$lines[] = '| 样本ID | 标题 | 候选ID | 字数 | AI摘要 | 评价 | 问题类型 | 人工修改版摘要 | 备注 |';
$lines[] = '| --- | --- | --- | ---: | --- | --- | --- | --- | --- |';

foreach ( $items as $item ) {
	$summary = '' !== $item['generation_error'] ? 'ERROR: ' . $item['generation_error'] : $item['summary'];
	$is_generation_error = 'generation_error' === (string) $item['failure_reason'];
	$decision = $is_generation_error ? '不可用' : '';
	$reason = $is_generation_error ? '生成失败' : '';
	$notes = $is_generation_error ? '没有可用摘要候选；通常是 Cloud 返回结构异常、候选被质量门过滤，或该批次使用了旧规则。' : '';
	$lines[] = sprintf(
		'| `%s` | %s | `%s` | %d | %s | %s | %s |  | %s |',
		npcink_summary_review_text( (string) $item['sample_id'] ),
		npcink_summary_review_text( (string) $item['title'] ),
		npcink_summary_review_text( (string) $item['candidate_id'] ),
		(int) $item['length'],
		npcink_summary_review_text( (string) $summary ),
		npcink_summary_review_text( $decision ),
		npcink_summary_review_text( $reason ),
		npcink_summary_review_text( $notes )
	);
}

if ( false === file_put_contents( $output_md, implode( "\n", $lines ) . "\n" ) ) {
	npcink_summary_review_fail( 'Unable to write Markdown worksheet: ' . $output_md );
}

echo 'Exported human review worksheet: ' . $output_md . "\n";
echo 'Exported human review JSON: ' . $output_json . "\n";
echo 'Exported human review CSV: ' . $output_csv . "\n";
