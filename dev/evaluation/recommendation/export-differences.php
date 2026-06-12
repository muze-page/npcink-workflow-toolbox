<?php
/**
 * Export only cross-model recommendation differences for human review.
 *
 * This script is local/offline only. It reads AI-cycle JSON files and writes
 * a compact Markdown/JSON/CSV report containing disagreements, low scores, and
 * errors.
 *
 * @package Npcink_Toolbox
 */

$root        = dirname( __DIR__, 3 );
$script_args = array_slice( $argv ?? array(), 1 );

function npcink_rec_diff_arg_map( array $script_args ): array {
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

function npcink_rec_diff_fail( string $message ): void {
	fwrite( STDERR, "FAIL: {$message}\n" );
	exit( 1 );
}

function npcink_rec_diff_path( string $path, string $root ): string {
	if ( str_starts_with( $path, '/' ) ) {
		return $path;
	}

	return $root . '/' . ltrim( $path, '/' );
}

function npcink_rec_diff_read_json( string $path ): array {
	if ( ! is_file( $path ) ) {
		npcink_rec_diff_fail( 'Input JSON file not found: ' . $path );
	}

	$decoded = json_decode( (string) file_get_contents( $path ), true );
	if ( ! is_array( $decoded ) ) {
		npcink_rec_diff_fail( 'Input JSON is invalid: ' . $path );
	}

	return $decoded;
}

function npcink_rec_diff_write_json( string $path, array $payload ): void {
	$directory = dirname( $path );
	if ( ! is_dir( $directory ) && ! mkdir( $directory, 0775, true ) && ! is_dir( $directory ) ) {
		npcink_rec_diff_fail( 'Unable to create output directory: ' . $directory );
	}

	$encoded = json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	if ( ! is_string( $encoded ) || false === file_put_contents( $path, $encoded . "\n" ) ) {
		npcink_rec_diff_fail( 'Unable to write JSON: ' . $path );
	}
}

function npcink_rec_diff_text( string $value ): string {
	$value = trim( preg_replace( '/\s+/u', ' ', $value ) ?? $value );

	return str_replace( array( '|', "\r", "\n" ), array( '\|', ' ', ' ' ), $value );
}

function npcink_rec_diff_normalize_value( string $value ): string {
	$value = strtolower( trim( preg_replace( '/\s+/u', ' ', $value ) ?? $value ) );
	$value = preg_replace( '/[[:punct:]\s]+/u', '', $value ) ?? $value;

	return $value;
}

function npcink_rec_diff_tool_candidates( array $candidate_set, string $tool ): array {
	$key = array(
		'title'       => 'title_candidates',
		'summary'     => 'summary_candidates',
		'category'    => 'category_candidates',
		'tag'         => 'tag_candidates',
		'new_tag_gap' => 'new_tag_gaps',
	)[ $tool ] ?? '';

	$candidates = is_array( $candidate_set[ $key ] ?? null ) ? $candidate_set[ $key ] : array();
	$values     = array();
	foreach ( $candidates as $candidate ) {
		if ( ! is_array( $candidate ) ) {
			continue;
		}
		if ( 'category' === $tool || 'tag' === $tool || 'new_tag_gap' === $tool ) {
			$name    = trim( (string) ( $candidate['name'] ?? '' ) );
			$term_id = (int) ( $candidate['term_id'] ?? 0 );
			if ( '' !== $name ) {
				$values[] = $term_id > 0 ? $name . ' (#' . $term_id . ')' : $name;
			}
			continue;
		}

		$value = trim( (string) ( $candidate['value'] ?? '' ) );
		if ( '' !== $value ) {
			$values[] = $value;
		}
	}

	return array_values( array_unique( $values ) );
}

function npcink_rec_diff_score_for_tool( array $review, string $tool ): int {
	$scores = is_array( $review['tool_scores'] ?? null ) ? $review['tool_scores'] : array();
	$value  = $scores[ 'new_tag_gap' === $tool ? 'tag' : $tool ] ?? ( $review['overall_score'] ?? 0 );

	return is_numeric( $value ) ? (int) $value : 0;
}

function npcink_rec_diff_issues_for_tool( array $review, string $tool ): array {
	$issues  = is_array( $review['issues'] ?? null ) ? $review['issues'] : array();
	$matched = array();
	foreach ( $issues as $issue ) {
		if ( ! is_array( $issue ) ) {
			continue;
		}
		$issue_tool = (string) ( $issue['tool'] ?? '' );
		$target     = 'new_tag_gap' === $tool ? 'tag' : $tool;
		if ( '' !== $issue_tool && $issue_tool !== $target ) {
			continue;
		}
		$matched[] = trim( (string) ( $issue['severity'] ?? '' ) . ': ' . (string) ( $issue['reason'] ?? '' ), ': ' );
	}

	return array_values( array_filter( $matched ) );
}

function npcink_rec_diff_error_messages( array $cycle ): array {
	$errors = is_array( $cycle['errors'] ?? null ) ? $cycle['errors'] : array();
	$items  = array();
	foreach ( $errors as $error ) {
		if ( is_array( $error ) ) {
			$items[] = (string) ( $error['message'] ?? json_encode( $error, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
		} elseif ( is_scalar( $error ) ) {
			$items[] = (string) $error;
		}
	}

	return array_values( array_filter( $items ) );
}

function npcink_rec_diff_sample_map( array $payload ): array {
	$samples = is_array( $payload['samples'] ?? null ) ? $payload['samples'] : array();
	$map     = array();
	foreach ( $samples as $sample ) {
		if ( ! is_array( $sample ) ) {
			continue;
		}
		$id = (string) ( $sample['id'] ?? '' );
		if ( '' !== $id ) {
			$map[ $id ] = $sample;
		}
	}

	return $map;
}

function npcink_rec_diff_changed( array $values_by_label ): bool {
	$normalized_sets = array();
	foreach ( $values_by_label as $values ) {
		$normalized = array_values( array_unique( array_map( 'npcink_rec_diff_normalize_value', $values ) ) );
		sort( $normalized );
		$normalized_sets[] = implode( "\n", $normalized );
	}

	return count( array_unique( $normalized_sets ) ) > 1;
}

function npcink_rec_diff_reason( array $values_by_label, array $scores_by_label, array $issues_by_label, array $errors_by_label ): array {
	$reasons = array();
	if ( npcink_rec_diff_changed( $values_by_label ) ) {
		$reasons[] = 'candidate_disagreement';
	}
	foreach ( $scores_by_label as $label => $score ) {
		if ( $score > 0 && $score <= 3 ) {
			$reasons[] = $label . '_low_score_' . $score;
		}
	}
	foreach ( $issues_by_label as $label => $issues ) {
		if ( array() !== $issues ) {
			$reasons[] = $label . '_issues';
		}
	}
	foreach ( $errors_by_label as $label => $errors ) {
		if ( array() !== $errors ) {
			$reasons[] = $label . '_errors';
		}
	}

	return array_values( array_unique( $reasons ) );
}

$arg_map     = npcink_rec_diff_arg_map( $script_args );
$input_paths = array_map(
	'trim',
	explode(
		',',
		(string) ( $arg_map['inputs'] ?? 'dev/evaluation/recommendation/generated/ai-cycle-gpt55.json,dev/evaluation/recommendation/generated/ai-cycle-grok43.json,dev/evaluation/recommendation/generated/ai-cycle-deepseek.json' )
	)
);
$labels      = array_map( 'trim', explode( ',', (string) ( $arg_map['labels'] ?? 'gpt55,grok43,deepseek' ) ) );
$output_md   = npcink_rec_diff_path( (string) ( $arg_map['output_md'] ?? 'dev/evaluation/recommendation/generated/differences.md' ), $root );
$output_json = npcink_rec_diff_path( (string) ( $arg_map['output_json'] ?? 'dev/evaluation/recommendation/generated/differences.json' ), $root );
$output_csv  = npcink_rec_diff_path( (string) ( $arg_map['output_csv'] ?? 'dev/evaluation/recommendation/generated/differences.csv' ), $root );
$tools       = array( 'title', 'summary', 'category', 'tag', 'new_tag_gap' );

if ( count( $input_paths ) !== count( $labels ) ) {
	npcink_rec_diff_fail( 'inputs and labels must have the same item count.' );
}

$runs = array();
foreach ( $input_paths as $index => $path ) {
	$label          = $labels[ $index ];
	$absolute       = npcink_rec_diff_path( $path, $root );
	$payload        = npcink_rec_diff_read_json( $absolute );
	$runs[ $label ] = array(
		'path'    => $absolute,
		'payload' => $payload,
		'samples' => npcink_rec_diff_sample_map( $payload ),
	);
}

$sample_ids = array();
foreach ( $runs as $run ) {
	$sample_ids = array_merge( $sample_ids, array_keys( $run['samples'] ) );
}
$sample_ids = array_values( array_unique( $sample_ids ) );
sort( $sample_ids );

$items = array();
foreach ( $sample_ids as $sample_id ) {
	$base_sample = null;
	foreach ( $runs as $run ) {
		if ( isset( $run['samples'][ $sample_id ] ) ) {
			$base_sample = $run['samples'][ $sample_id ];
			break;
		}
	}
	if ( ! is_array( $base_sample ) ) {
		continue;
	}

	foreach ( $tools as $tool ) {
		$values_by_label = array();
		$scores_by_label = array();
		$issues_by_label = array();
		$errors_by_label = array();
		foreach ( $runs as $label => $run ) {
			$sample = is_array( $run['samples'][ $sample_id ] ?? null ) ? $run['samples'][ $sample_id ] : array();
			$cycle  = is_array( $sample['ai_cycle'] ?? null ) ? $sample['ai_cycle'] : array();
			$source = is_array( $cycle['repaired'] ?? null ) ? $cycle['repaired'] : ( is_array( $cycle['generated'] ?? null ) ? $cycle['generated'] : array() );
			$review = is_array( $cycle['review'] ?? null ) ? $cycle['review'] : array();

			$values_by_label[ $label ] = npcink_rec_diff_tool_candidates( $source, $tool );
			$scores_by_label[ $label ] = npcink_rec_diff_score_for_tool( $review, $tool );
			$issues_by_label[ $label ] = npcink_rec_diff_issues_for_tool( $review, $tool );
			$errors_by_label[ $label ] = npcink_rec_diff_error_messages( $cycle );
		}

		$reasons = npcink_rec_diff_reason( $values_by_label, $scores_by_label, $issues_by_label, $errors_by_label );
		if ( array() === $reasons ) {
			continue;
		}

		$items[] = array(
			'sample_id'       => $sample_id,
			'post_id'         => (int) ( $base_sample['post_id'] ?? 0 ),
			'bucket'          => (string) ( $base_sample['bucket'] ?? '' ),
			'url'             => (string) ( $base_sample['url'] ?? '' ),
			'article_title'   => (string) ( $base_sample['title'] ?? '' ),
			'tool'            => $tool,
			'reasons'         => $reasons,
			'values_by_label' => $values_by_label,
			'scores_by_label' => $scores_by_label,
			'issues_by_label' => $issues_by_label,
			'errors_by_label' => $errors_by_label,
			'human_decision'  => '',
			'notes'           => '',
		);
	}
}

$report = array(
	'version'          => 1,
	'created_at'       => gmdate( 'c' ),
	'write_posture'    => 'eval_only_no_wordpress_write',
	'inputs'           => array_combine( $labels, array_map( static fn( $run ): string => (string) $run['path'], $runs ) ),
	'labels'           => $labels,
	'sample_count'     => count( $sample_ids ),
	'difference_count' => count( $items ),
	'items'            => $items,
);

npcink_rec_diff_write_json( $output_json, $report );

foreach ( array( $output_md, $output_csv ) as $output ) {
	$directory = dirname( $output );
	if ( ! is_dir( $directory ) && ! mkdir( $directory, 0775, true ) && ! is_dir( $directory ) ) {
		npcink_rec_diff_fail( 'Unable to create output directory: ' . $directory );
	}
}

$md = array(
	'# Recommendation Differences',
	'',
	'- Samples: ' . count( $sample_ids ),
	'- Difference rows: ' . count( $items ),
	'- Write posture: `eval_only_no_wordpress_write`',
	'',
	'| 样本ID | 分层 | 原标题 | 工具 | 差异原因 | 模型输出 | 人工结论 | 备注 |',
	'| --- | --- | --- | --- | --- | --- | --- | --- |',
);
foreach ( $items as $item ) {
	$model_output = array();
	foreach ( $labels as $label ) {
		$values = $item['values_by_label'][ $label ] ?? array();
		$score  = (string) ( $item['scores_by_label'][ $label ] ?? '' );
		$model_output[] = $label . '[' . $score . ']: ' . ( array() === $values ? '(empty)' : implode( ' / ', $values ) );
	}
	$md[] = '| ' . implode(
		' | ',
		array(
			npcink_rec_diff_text( (string) $item['sample_id'] ),
			npcink_rec_diff_text( (string) $item['bucket'] ),
			npcink_rec_diff_text( (string) $item['article_title'] ),
			npcink_rec_diff_text( (string) $item['tool'] ),
			npcink_rec_diff_text( implode( ';', $item['reasons'] ) ),
			npcink_rec_diff_text( implode( ' || ', $model_output ) ),
			'',
			''
		)
	) . ' |';
}
if ( false === file_put_contents( $output_md, implode( "\n", $md ) . "\n" ) ) {
	npcink_rec_diff_fail( 'Unable to write Markdown report: ' . $output_md );
}

$handle = fopen( $output_csv, 'wb' );
if ( false === $handle ) {
	npcink_rec_diff_fail( 'Unable to write CSV report: ' . $output_csv );
}
fwrite( $handle, "\xEF\xBB\xBF" );
$header = array( '样本ID', 'Post ID', '分层', 'URL', '原标题', '工具', '差异原因' );
foreach ( $labels as $label ) {
	$header[] = $label . '评分';
	$header[] = $label . '输出';
	$header[] = $label . '问题';
	$header[] = $label . '错误';
}
$header[] = '人工结论';
$header[] = '备注';
fputcsv( $handle, $header, ',', '"', '\\' );
foreach ( $items as $item ) {
	$row = array(
		$item['sample_id'],
		$item['post_id'],
		$item['bucket'],
		$item['url'],
		$item['article_title'],
		$item['tool'],
		implode( ';', $item['reasons'] ),
	);
	foreach ( $labels as $label ) {
		$row[] = $item['scores_by_label'][ $label ] ?? '';
		$row[] = implode( ' / ', $item['values_by_label'][ $label ] ?? array() );
		$row[] = implode( '; ', $item['issues_by_label'][ $label ] ?? array() );
		$row[] = implode( '; ', $item['errors_by_label'][ $label ] ?? array() );
	}
	$row[] = $item['human_decision'];
	$row[] = $item['notes'];
	fputcsv( $handle, $row, ',', '"', '\\' );
}
fclose( $handle );

echo 'Exported recommendation differences: ' . count( $items ) . " row(s)\n";
echo 'Markdown: ' . $output_md . "\n";
echo 'JSON: ' . $output_json . "\n";
echo 'CSV: ' . $output_csv . "\n";
