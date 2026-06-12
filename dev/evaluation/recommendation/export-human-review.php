<?php
/**
 * Export recommendation AI-cycle results into human review worksheets.
 *
 * This script is local/offline only. It never calls providers or WordPress.
 *
 * @package Npcink_Toolbox
 */

$root        = dirname( __DIR__, 3 );
$script_args = array_slice( $argv ?? array(), 1 );

function npcink_rec_review_arg_map( array $script_args ): array {
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

function npcink_rec_review_fail( string $message ): void {
	fwrite( STDERR, "FAIL: {$message}\n" );
	exit( 1 );
}

function npcink_rec_review_path( string $path, string $root ): string {
	if ( str_starts_with( $path, '/' ) ) {
		return $path;
	}

	return $root . '/' . ltrim( $path, '/' );
}

function npcink_rec_review_read_json( string $path ): array {
	if ( ! is_file( $path ) ) {
		npcink_rec_review_fail( 'Input JSON file not found: ' . $path );
	}

	$decoded = json_decode( (string) file_get_contents( $path ), true );
	if ( ! is_array( $decoded ) ) {
		npcink_rec_review_fail( 'Input JSON is invalid: ' . $path );
	}

	return $decoded;
}

function npcink_rec_review_write_json( string $path, array $payload ): void {
	$directory = dirname( $path );
	if ( ! is_dir( $directory ) && ! mkdir( $directory, 0775, true ) && ! is_dir( $directory ) ) {
		npcink_rec_review_fail( 'Unable to create output directory: ' . $directory );
	}

	$encoded = json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	if ( ! is_string( $encoded ) || false === file_put_contents( $path, $encoded . "\n" ) ) {
		npcink_rec_review_fail( 'Unable to write JSON: ' . $path );
	}
}

function npcink_rec_review_text( string $value ): string {
	$value = trim( preg_replace( '/\s+/u', ' ', $value ) ?? $value );

	return str_replace( array( '|', "\r", "\n" ), array( '\|', ' ', ' ' ), $value );
}

function npcink_rec_review_list( $value ): string {
	if ( ! is_array( $value ) ) {
		return is_scalar( $value ) ? (string) $value : '';
	}

	return implode(
		'; ',
		array_values(
			array_filter(
				array_map(
					static function ( $item ): string {
						if ( is_array( $item ) ) {
							return (string) ( $item['reason'] ?? $item['value'] ?? $item['name'] ?? json_encode( $item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
						}

						return (string) $item;
					},
					$value
				),
				static fn( string $item ): bool => '' !== trim( $item )
			)
		)
	);
}

function npcink_rec_review_score_for_tool( array $review, string $tool ): string {
	$scores = is_array( $review['tool_scores'] ?? null ) ? $review['tool_scores'] : array();

	return isset( $scores[ $tool ] ) ? (string) $scores[ $tool ] : (string) ( $review['overall_score'] ?? '' );
}

function npcink_rec_review_issues_for_tool( array $review, string $tool ): string {
	$issues = is_array( $review['issues'] ?? null ) ? $review['issues'] : array();
	$matched = array();
	foreach ( $issues as $issue ) {
		if ( ! is_array( $issue ) ) {
			continue;
		}
		$issue_tool = (string) ( $issue['tool'] ?? '' );
		if ( '' !== $issue_tool && $tool !== $issue_tool ) {
			continue;
		}
		$matched[] = trim( (string) ( $issue['severity'] ?? '' ) . ': ' . (string) ( $issue['reason'] ?? '' ), ': ' );
	}

	return implode( '; ', array_filter( $matched ) );
}

function npcink_rec_review_candidate_value( string $tool, array $candidate ): string {
	if ( 'category' === $tool || 'tag' === $tool || 'new_tag_gap' === $tool ) {
		$term_id = (int) ( $candidate['term_id'] ?? 0 );
		$name    = (string) ( $candidate['name'] ?? '' );

		return $term_id > 0 ? $name . ' (#' . $term_id . ')' : $name;
	}

	return (string) ( $candidate['value'] ?? '' );
}

function npcink_rec_review_add_rows( array &$items, array $sample, string $tool, array $candidates, array $review ): void {
	$index = 0;
	foreach ( $candidates as $candidate ) {
		if ( ! is_array( $candidate ) ) {
			continue;
		}
		$index++;
		$items[] = array(
			'sample_id'       => (string) ( $sample['id'] ?? '' ),
			'post_id'         => (int) ( $sample['post_id'] ?? 0 ),
			'bucket'          => (string) ( $sample['bucket'] ?? '' ),
			'url'             => (string) ( $sample['url'] ?? '' ),
			'article_title'   => (string) ( $sample['title'] ?? '' ),
			'tool'            => $tool,
			'candidate_id'    => $tool . '-' . $index,
			'candidate_value' => npcink_rec_review_candidate_value( $tool, $candidate ),
			'candidate_reason' => (string) ( $candidate['reason'] ?? '' ),
			'ai_score'        => npcink_rec_review_score_for_tool( $review, 'new_tag_gap' === $tool ? 'tag' : $tool ),
			'ai_issues'       => npcink_rec_review_issues_for_tool( $review, 'new_tag_gap' === $tool ? 'tag' : $tool ),
			'repair_brief'    => (string) ( $review['repair_brief'] ?? '' ),
			'human_decision'  => '',
			'problem_type'    => '',
			'human_revision'  => '',
			'notes'           => '',
		);
	}
}

$arg_map     = npcink_rec_review_arg_map( $script_args );
$input       = npcink_rec_review_path( (string) ( $arg_map['input'] ?? 'dev/evaluation/recommendation/generated/ai-cycle.json' ), $root );
$output_md   = npcink_rec_review_path( (string) ( $arg_map['output_md'] ?? 'dev/evaluation/recommendation/generated/human-review.md' ), $root );
$output_json = npcink_rec_review_path( (string) ( $arg_map['output_json'] ?? 'dev/evaluation/recommendation/generated/human-review.json' ), $root );
$output_csv  = npcink_rec_review_path( (string) ( $arg_map['output_csv'] ?? 'dev/evaluation/recommendation/generated/human-review.csv' ), $root );
$limit       = max( 0, min( 1000, (int) ( $arg_map['limit'] ?? 200 ) ) );
$decoded     = npcink_rec_review_read_json( $input );
$samples     = is_array( $decoded['samples'] ?? null ) ? $decoded['samples'] : array();

$items = array();
foreach ( $samples as $sample ) {
	if ( ! is_array( $sample ) ) {
		continue;
	}

	$cycle     = is_array( $sample['ai_cycle'] ?? null ) ? $sample['ai_cycle'] : array();
	$review    = is_array( $cycle['review'] ?? null ) ? $cycle['review'] : array();
	$repaired  = is_array( $cycle['repaired'] ?? null ) ? $cycle['repaired'] : array();
	$generated = is_array( $cycle['generated'] ?? null ) ? $cycle['generated'] : array();
	$source    = array() !== $repaired ? $repaired : $generated;

	npcink_rec_review_add_rows( $items, $sample, 'title', is_array( $source['title_candidates'] ?? null ) ? $source['title_candidates'] : array(), $review );
	npcink_rec_review_add_rows( $items, $sample, 'summary', is_array( $source['summary_candidates'] ?? null ) ? $source['summary_candidates'] : array(), $review );
	npcink_rec_review_add_rows( $items, $sample, 'category', is_array( $source['category_candidates'] ?? null ) ? $source['category_candidates'] : array(), $review );
	npcink_rec_review_add_rows( $items, $sample, 'tag', is_array( $source['tag_candidates'] ?? null ) ? $source['tag_candidates'] : array(), $review );
	npcink_rec_review_add_rows( $items, $sample, 'new_tag_gap', is_array( $source['new_tag_gaps'] ?? null ) ? $source['new_tag_gaps'] : array(), $review );
}

if ( $limit > 0 ) {
	$items = array_slice( $items, 0, $limit );
}

$worksheet = array(
	'version'          => 1,
	'created_at'       => gmdate( 'c' ),
	'source_file'      => $input,
	'write_posture'    => 'eval_only_no_wordpress_write',
	'candidate_count'  => count( $items ),
	'decision_options' => array( 'direct_use', 'minor_edit', 'reject' ),
	'problem_types'    => array(
		'not_factual',
		'too_generic',
		'wrong_term',
		'missing_existing_term',
		'unsupported_claim',
		'wrong_tone',
		'too_long',
		'too_short',
		'boundary_issue',
		'other',
	),
	'items'            => $items,
);

npcink_rec_review_write_json( $output_json, $worksheet );

foreach ( array( $output_md, $output_csv ) as $output ) {
	$directory = dirname( $output );
	if ( ! is_dir( $directory ) && ! mkdir( $directory, 0775, true ) && ! is_dir( $directory ) ) {
		npcink_rec_review_fail( 'Unable to create output directory: ' . $directory );
	}
}

$md = array(
	'# Recommendation Human Review',
	'',
	'- Source: `' . $input . '`',
	'- Candidates: ' . count( $items ),
	'- Write posture: `eval_only_no_wordpress_write`',
	'',
	'| 样本ID | 分层 | 原标题 | 工具 | 候选ID | 候选值 | AI评分 | AI问题 | 人工决策 | 问题类型 | 人工修改 | 备注 |',
	'| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |',
);
foreach ( $items as $item ) {
	$md[] = '| ' . implode(
		' | ',
		array(
			npcink_rec_review_text( (string) $item['sample_id'] ),
			npcink_rec_review_text( (string) $item['bucket'] ),
			npcink_rec_review_text( (string) $item['article_title'] ),
			npcink_rec_review_text( (string) $item['tool'] ),
			npcink_rec_review_text( (string) $item['candidate_id'] ),
			npcink_rec_review_text( (string) $item['candidate_value'] ),
			npcink_rec_review_text( (string) $item['ai_score'] ),
			npcink_rec_review_text( (string) $item['ai_issues'] ),
			'',
			'',
			'',
			''
		)
	) . ' |';
}

if ( false === file_put_contents( $output_md, implode( "\n", $md ) . "\n" ) ) {
	npcink_rec_review_fail( 'Unable to write Markdown review file: ' . $output_md );
}

$handle = fopen( $output_csv, 'wb' );
if ( false === $handle ) {
	npcink_rec_review_fail( 'Unable to write CSV review file: ' . $output_csv );
}
fwrite( $handle, "\xEF\xBB\xBF" );
fputcsv(
	$handle,
	array(
		'样本ID',
		'Post ID',
		'分层',
		'URL',
		'原标题',
		'工具',
		'候选ID',
		'候选值',
		'候选理由',
		'AI评分',
		'AI问题',
		'修正说明',
		'人工决策',
		'问题类型',
		'人工修改',
		'备注',
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
			$item['post_id'],
			$item['bucket'],
			$item['url'],
			$item['article_title'],
			$item['tool'],
			$item['candidate_id'],
			$item['candidate_value'],
			$item['candidate_reason'],
			$item['ai_score'],
			$item['ai_issues'],
			$item['repair_brief'],
			$item['human_decision'],
			$item['problem_type'],
			$item['human_revision'],
			$item['notes'],
		),
		',',
		'"',
		'\\'
	);
}
fclose( $handle );

echo 'Exported recommendation human review worksheet: ' . count( $items ) . " candidate(s)\n";
echo 'Markdown: ' . $output_md . "\n";
echo 'JSON: ' . $output_json . "\n";
echo 'CSV: ' . $output_csv . "\n";
