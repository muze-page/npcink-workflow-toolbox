<?php
/**
 * Export generated AI summary candidates into a human review worksheet.
 *
 * This script is local/offline only. It reads generated eval JSON and writes
 * Markdown plus JSON review worksheets. It never calls Cloud or WordPress.
 *
 * @package Npcink_Toolbox
 */

$root        = dirname( __DIR__, 3 );
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

function npcink_summary_review_rows( array $items ): array {
	$rows = array(
		array(
			'样本ID',
			'标题',
			'候选ID',
			'字数',
			'AI摘要',
			'评分(1-5)',
			'采用决策',
			'问题类型',
			'人工修改版摘要',
			'备注',
		),
	);

	foreach ( $items as $item ) {
		$is_generation_error = 'generation_error' === (string) $item['failure_reason'];
		$rows[] = array(
			(string) $item['sample_id'],
			(string) $item['title'],
			(string) $item['candidate_id'],
			(int) $item['length'],
			'' !== $item['generation_error'] ? 'ERROR: ' . (string) $item['generation_error'] : (string) $item['summary'],
			(string) $item['quality_score'],
			$is_generation_error ? '不可用' : '',
			$is_generation_error ? '生成失败' : '',
			'',
			$is_generation_error ? '没有可用摘要候选；通常是 Cloud 返回结构异常、候选被质量门过滤，或该批次使用了旧规则。' : '',
		);
	}

	return $rows;
}

function npcink_summary_review_xml( string $value ): string {
	return htmlspecialchars( $value, ENT_XML1 | ENT_COMPAT, 'UTF-8' );
}

function npcink_summary_review_xlsx_col( int $index ): string {
	$letters = '';
	while ( $index > 0 ) {
		$index--;
		$letters = chr( 65 + ( $index % 26 ) ) . $letters;
		$index = (int) floor( $index / 26 );
	}

	return $letters;
}

function npcink_summary_review_xlsx_cell( int $column_index, int $row_index, $value ): string {
	$cell = npcink_summary_review_xlsx_col( $column_index ) . $row_index;
	if ( is_int( $value ) || is_float( $value ) ) {
		return '<c r="' . $cell . '"><v>' . $value . '</v></c>';
	}

	return '<c r="' . $cell . '" t="inlineStr"><is><t>' . npcink_summary_review_xml( (string) $value ) . '</t></is></c>';
}

function npcink_summary_review_write_xlsx( string $output_xlsx, array $rows ): void {
	if ( ! class_exists( 'ZipArchive' ) ) {
		npcink_summary_review_fail( 'ZipArchive is required to write XLSX worksheets.' );
	}

	$directory = dirname( $output_xlsx );
	if ( ! is_dir( $directory ) && ! mkdir( $directory, 0775, true ) && ! is_dir( $directory ) ) {
		npcink_summary_review_fail( 'Unable to create output directory: ' . $directory );
	}

	$sheet_rows = array();
	foreach ( $rows as $row_index => $row ) {
		$cells = array();
		foreach ( $row as $column_index => $value ) {
			$cells[] = npcink_summary_review_xlsx_cell( $column_index + 1, $row_index + 1, $value );
		}
		$sheet_rows[] = '<row r="' . ( $row_index + 1 ) . '">' . implode( '', $cells ) . '</row>';
	}

	$last_row = max( 2, count( $rows ) );
	$sheet_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
		. '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
		. '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
		. '<cols><col min="1" max="1" width="18" customWidth="1"/><col min="2" max="2" width="34" customWidth="1"/><col min="3" max="3" width="24" customWidth="1"/><col min="4" max="4" width="8" customWidth="1"/><col min="5" max="5" width="70" customWidth="1"/><col min="6" max="8" width="16" customWidth="1"/><col min="9" max="10" width="34" customWidth="1"/></cols>'
		. '<sheetData>' . implode( '', $sheet_rows ) . '</sheetData>'
		. '<dataValidations count="3">'
		. '<dataValidation type="list" allowBlank="1" sqref="F2:F' . $last_row . '"><formula1>"1,2,3,4,5"</formula1></dataValidation>'
		. '<dataValidation type="list" allowBlank="1" sqref="G2:G' . $last_row . '"><formula1>"直接可用,稍改可用,不可用"</formula1></dataValidation>'
		. '<dataValidation type="list" allowBlank="1" sqref="H2:H' . $last_row . '"><formula1>"生成失败,太泛,核心价值缺失,逻辑混乱,误导,语气不合适,像说明书,覆盖不足,太营销,太短,太长,无依据,其他"</formula1></dataValidation>'
		. '</dataValidations>'
		. '<pageMargins left="0.7" right="0.7" top="0.75" bottom="0.75" header="0.3" footer="0.3"/>'
		. '</worksheet>';

	$zip = new ZipArchive();
	if ( true !== $zip->open( $output_xlsx, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
		npcink_summary_review_fail( 'Unable to write XLSX worksheet: ' . $output_xlsx );
	}

	$zip->addFromString( '[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>' );
	$zip->addFromString( '_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>' );
	$zip->addFromString( 'xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="AI摘要评审" sheetId="1" r:id="rId1"/></sheets></workbook>' );
	$zip->addFromString( 'xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>' );
	$zip->addFromString( 'xl/worksheets/sheet1.xml', $sheet_xml );
	$zip->close();
}

$arg_map     = npcink_summary_review_arg_map( $script_args );
$input       = npcink_summary_review_path( (string) ( $arg_map['input'] ?? 'dev/evaluation/summary/generated/muze-candidates.json' ), $root );
$output_md   = npcink_summary_review_path( (string) ( $arg_map['output_md'] ?? 'dev/evaluation/summary/generated/summary-human-review.md' ), $root );
$output_json = npcink_summary_review_path( (string) ( $arg_map['output_json'] ?? 'dev/evaluation/summary/generated/summary-human-review.json' ), $root );
$output_csv  = npcink_summary_review_path( (string) ( $arg_map['output_csv'] ?? 'dev/evaluation/summary/generated/summary-human-review.csv' ), $root );
$output_xlsx = npcink_summary_review_path( (string) ( $arg_map['output_xlsx'] ?? 'dev/evaluation/summary/generated/summary-human-review.xlsx' ), $root );
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
			'quality_score'  => 1,
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
			'quality_score'  => '',
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
	'score_options'     => array( 1, 2, 3, 4, 5 ),
	'score_guide'       => array(
		'1' => '不可用：生成失败、事实错误、空泛或明显不适合发布',
		'2' => '较差：主题相关但需要大改',
		'3' => '可参考：方向基本可用但需要编辑重写',
		'4' => '较好：只需小改即可使用',
		'5' => '优秀：可直接作为当前文章摘要',
	),
	'decision_options'  => array( 'direct_use', 'minor_edit', 'reject' ),
	'failure_reasons'   => array(
		'generation_error',
		'too_generic',
		'missing_core_value',
		'logic_confusing',
		'misleading',
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

$rows = npcink_summary_review_rows( $items );

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
	$rows[0],
	',',
	'"',
	'\\'
);
foreach ( array_slice( $rows, 1 ) as $row ) {
	fputcsv(
		$csv_handle,
		$row,
		',',
		'"',
		'\\'
	);
}
fclose( $csv_handle );
npcink_summary_review_write_xlsx( $output_xlsx, $rows );

$lines   = array();
$lines[] = '# AI 摘要人工评审';
$lines[] = '';
$lines[] = '- Source: `' . $input . '`';
$lines[] = '- Created: `' . $worksheet['created_at'] . '`';
$lines[] = '- 评分: `1` 不可用, `2` 较差, `3` 可参考, `4` 较好, `5` 可直接使用';
$lines[] = '- 采用决策: `direct_use` 直接可用, `minor_edit` 稍改可用, `reject` 不可用';
$lines[] = '- 问题类型: `generation_error` 生成失败, `too_generic` 太泛, `missing_core_value` 核心价值缺失, `logic_confusing` 逻辑混乱, `misleading` 误导, `wrong_tone` 语气不合适, `instruction_like` 像说明书, `insufficient_coverage` 覆盖不足, `too_marketing` 太营销, `too_short` 太短, `too_long` 太长, `unsupported_claim` 无依据, `other` 其他';
$lines[] = '';
$lines[] = '| 样本ID | 标题 | 候选ID | 字数 | AI摘要 | 评分(1-5) | 采用决策 | 问题类型 | 人工修改版摘要 | 备注 |';
$lines[] = '| --- | --- | --- | ---: | --- | ---: | --- | --- | --- | --- |';

foreach ( $items as $item ) {
	$summary = '' !== $item['generation_error'] ? 'ERROR: ' . $item['generation_error'] : $item['summary'];
	$is_generation_error = 'generation_error' === (string) $item['failure_reason'];
	$score = $is_generation_error ? '1' : '';
	$decision = $is_generation_error ? '不可用' : '';
	$reason = $is_generation_error ? '生成失败' : '';
	$notes = $is_generation_error ? '没有可用摘要候选；通常是 Cloud 返回结构异常、候选被质量门过滤，或该批次使用了旧规则。' : '';
	$lines[] = sprintf(
		'| `%s` | %s | `%s` | %d | %s | %s | %s | %s |  | %s |',
		npcink_summary_review_text( (string) $item['sample_id'] ),
		npcink_summary_review_text( (string) $item['title'] ),
		npcink_summary_review_text( (string) $item['candidate_id'] ),
		(int) $item['length'],
		npcink_summary_review_text( (string) $summary ),
		npcink_summary_review_text( $score ),
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
echo 'Exported human review XLSX: ' . $output_xlsx . "\n";
