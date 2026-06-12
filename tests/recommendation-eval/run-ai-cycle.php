<?php
/**
 * Run a local recommendation generation -> review -> repair evaluation cycle.
 *
 * This script is local/offline except for explicit provider calls. It reads
 * API credentials from environment variables and never writes WordPress data.
 *
 * @package Npcink_Toolbox
 */

$root        = dirname( __DIR__, 2 );
$script_args = array_slice( $argv ?? array(), 1 );

function npcink_rec_eval_cycle_arg_map( array $script_args ): array {
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

function npcink_rec_eval_cycle_fail( string $message ): void {
	fwrite( STDERR, "FAIL: {$message}\n" );
	exit( 1 );
}

function npcink_rec_eval_cycle_path( string $path, string $root ): string {
	if ( str_starts_with( $path, '/' ) ) {
		return $path;
	}

	return $root . '/' . ltrim( $path, '/' );
}

function npcink_rec_eval_cycle_read_json( string $path ): array {
	if ( ! is_file( $path ) ) {
		npcink_rec_eval_cycle_fail( 'Input JSON file not found: ' . $path );
	}

	$decoded = json_decode( (string) file_get_contents( $path ), true );
	if ( ! is_array( $decoded ) ) {
		npcink_rec_eval_cycle_fail( 'Input JSON is invalid: ' . $path );
	}

	return $decoded;
}

function npcink_rec_eval_cycle_write_json( string $path, array $payload ): void {
	$directory = dirname( $path );
	if ( ! is_dir( $directory ) && ! mkdir( $directory, 0775, true ) && ! is_dir( $directory ) ) {
		npcink_rec_eval_cycle_fail( 'Unable to create output directory: ' . $directory );
	}

	$encoded = json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	if ( ! is_string( $encoded ) || false === file_put_contents( $path, $encoded . "\n" ) ) {
		npcink_rec_eval_cycle_fail( 'Unable to write output JSON: ' . $path );
	}
}

function npcink_rec_eval_cycle_bool_arg( array $arg_map, string $key, bool $default ): bool {
	if ( ! array_key_exists( $key, $arg_map ) ) {
		return $default;
	}

	return in_array( strtolower( (string) $arg_map[ $key ] ), array( '1', 'true', 'yes', 'on' ), true );
}

function npcink_rec_eval_cycle_profiles(): array {
	return array(
		'gpt55' => array(
			'label'        => 'gpt55',
			'api_key_env'  => 'REC_EVAL_GPT55_API_KEY',
			'base_url_env' => 'REC_EVAL_GPT55_BASE_URL',
			'model_env'    => 'REC_EVAL_GPT55_MODEL',
			'base_url'     => '',
			'model'        => 'gpt-5.5',
		),
		'grok43' => array(
			'label'        => 'grok43',
			'api_key_env'  => 'REC_EVAL_GROK43_API_KEY',
			'base_url_env' => 'REC_EVAL_GROK43_BASE_URL',
			'model_env'    => 'REC_EVAL_GROK43_MODEL',
			'base_url'     => '',
			'model'        => 'grok-4.3',
		),
		'deepseek' => array(
			'label'        => 'deepseek',
			'api_key_env'  => 'REC_EVAL_DEEPSEEK_API_KEY',
			'base_url_env' => 'REC_EVAL_DEEPSEEK_BASE_URL',
			'model_env'    => 'REC_EVAL_DEEPSEEK_MODEL',
			'base_url'     => 'https://api.deepseek.com',
			'model'        => 'deepseek-v4-pro',
		),
	);
}

function npcink_rec_eval_cycle_profile( string $name, bool $dry_run ): array {
	$profiles = npcink_rec_eval_cycle_profiles();
	if ( ! isset( $profiles[ $name ] ) ) {
		npcink_rec_eval_cycle_fail( 'Unknown provider profile: ' . $name );
	}

	$profile              = $profiles[ $name ];
	$profile['api_key']   = (string) getenv( $profile['api_key_env'] );
	$profile['base_url']  = (string) ( getenv( $profile['base_url_env'] ) ?: $profile['base_url'] );
	$profile['model']     = (string) ( getenv( $profile['model_env'] ) ?: $profile['model'] );
	$profile['endpoint']  = rtrim( $profile['base_url'], '/' );
	$profile['endpoint'] .= str_ends_with( $profile['endpoint'], '/v1' ) ? '/chat/completions' : '/v1/chat/completions';

	if ( '' === $profile['api_key'] && ! $dry_run ) {
		npcink_rec_eval_cycle_fail( 'Set ' . $profile['api_key_env'] . ' before running profile ' . $name . '.' );
	}
	if ( '' === $profile['base_url'] && ! $dry_run ) {
		npcink_rec_eval_cycle_fail( 'Set ' . $profile['base_url_env'] . ' before running profile ' . $name . '.' );
	}
	unset( $profile['api_key'] );

	return $profile;
}

function npcink_rec_eval_cycle_request_profile( array $profile ): array {
	return array(
		'label'        => (string) $profile['label'],
		'api_key_env'  => (string) $profile['api_key_env'],
		'base_url_env' => (string) $profile['base_url_env'],
		'model_env'    => (string) $profile['model_env'],
		'base_url'     => (string) $profile['base_url'],
		'model'        => (string) $profile['model'],
	);
}

function npcink_rec_eval_cycle_terms( array $terms, int $limit ): array {
	return array_values(
		array_slice(
			array_filter(
				$terms,
				static fn( $term ): bool => is_array( $term ) && '' !== (string) ( $term['name'] ?? '' )
			),
			0,
			$limit
		)
	);
}

function npcink_rec_eval_cycle_sample_context( array $sample, array $source, int $term_limit ): array {
	$available_terms = is_array( $source['available_terms'] ?? null ) ? $source['available_terms'] : array();

	return array(
		'article'         => array(
			'id'                 => (string) ( $sample['id'] ?? '' ),
			'post_id'            => (int) ( $sample['post_id'] ?? 0 ),
			'bucket'             => (string) ( $sample['bucket'] ?? '' ),
			'url'                => (string) ( $sample['url'] ?? '' ),
			'title'              => (string) ( $sample['title'] ?? '' ),
			'existing_excerpt'   => (string) ( $sample['existing_excerpt'] ?? '' ),
			'content'            => (string) ( $sample['content'] ?? '' ),
			'current_categories' => is_array( $sample['current_categories'] ?? null ) ? $sample['current_categories'] : array(),
			'current_tags'       => is_array( $sample['current_tags'] ?? null ) ? $sample['current_tags'] : array(),
			'has_featured_image' => (bool) ( $sample['has_featured_image'] ?? false ),
		),
		'available_terms' => array(
			'categories' => npcink_rec_eval_cycle_terms( is_array( $available_terms['categories'] ?? null ) ? $available_terms['categories'] : array(), $term_limit ),
			'tags'       => npcink_rec_eval_cycle_terms( is_array( $available_terms['tags'] ?? null ) ? $available_terms['tags'] : array(), $term_limit ),
		),
		'tool_scope'      => array( 'title', 'summary', 'category', 'tag' ),
		'write_posture'   => 'eval_only_no_wordpress_write',
	);
}

function npcink_rec_eval_cycle_generator_messages( array $context, string $operator_note ): array {
	$schema = array(
		'title_candidates'    => array(
			array(
				'value'         => '标题候选，2-3个',
				'reason'        => '为什么适合当前文章',
				'quality_notes' => array( '具体、不夸大、不标题党' ),
			),
		),
		'summary_candidates'  => array(
			array(
				'value'         => '摘要候选，2-3个，适合作为 WordPress excerpt',
				'reason'        => '覆盖的核心信息',
				'quality_notes' => array( '忠于原文、可读、利于检索' ),
			),
		),
		'category_candidates' => array(
			array(
				'term_id'    => 0,
				'name'       => '必须优先从已有分类中选择',
				'reason'     => '分类依据',
				'confidence' => 0.8,
			),
		),
		'tag_candidates'      => array(
			array(
				'term_id'    => 0,
				'name'       => '必须优先从已有标签中选择',
				'reason'     => '标签依据',
				'confidence' => 0.8,
			),
		),
		'new_tag_gaps'        => array(
			array(
				'name'            => '仅当已有标签明显缺口时提出',
				'reason'          => '为什么需要人工确认新标签',
				'review_required' => true,
			),
		),
	);

	return array(
		array(
			'role'    => 'system',
			'content' => '你是 WordPress 编辑推荐质量评测助手。只输出严格 JSON。你的所有输出都是候选建议，不得要求写入 WordPress，不得虚构文章没有支持的信息。',
		),
		array(
			'role'    => 'user',
			'content' => "请基于当前文章生成第一阶段推荐候选：标题、摘要、已有分类、已有标签。\n\n作者补充需求：{$operator_note}\n\n硬性规则：\n- 分类和标签优先使用 available_terms 中已有 term_id/name。\n- 新标签只能放进 new_tag_gaps，且必须 review_required=true。\n- 不要生成内链或图片建议，本轮只评测 title/summary/category/tag。\n- 不要返回 Markdown，不要解释 JSON 之外的文字。\n\n期望 JSON 结构：\n" . json_encode( $schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n\n上下文：\n" . json_encode( $context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
		),
	);
}

function npcink_rec_eval_cycle_reviewer_messages( array $context, array $generated ): array {
	$schema = array(
		'overall_score'     => 4,
		'needs_repair'      => true,
		'tool_scores'       => array(
			'title'    => 4,
			'summary'  => 4,
			'category' => 4,
			'tag'      => 4,
		),
		'issues'            => array(
			array(
				'tool'     => 'summary',
				'severity' => 'medium',
				'reason'   => '问题说明',
			),
		),
		'repair_brief'      => '给修正模型的简短改进要求',
		'boundary_verdict'  => 'no_write_boundary_ok',
		'acceptance_notes'  => array( '可以保留的优点' ),
		'reject_if_present' => array( 'unsupported_claim', 'wrong_existing_term', 'direct_write_language' ),
	);

	return array(
		array(
			'role'    => 'system',
			'content' => '你是第二个 AI 审核员，负责挑错而不是迎合。只输出严格 JSON。重点检查事实贴合、具体性、已有分类/标签复用、边界合规。',
		),
		array(
			'role'    => 'user',
			'content' => "请审核候选结果。评分 1-5，低于 4 的工具必须给出问题和修正建议。\n\n审核维度：factual_fit、specificity、title_repetition、unsupported_claim、existing_term_reuse、category_fit、tag_fit、no_write_boundary。\n\n期望 JSON 结构：\n" . json_encode( $schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n\n文章上下文：\n" . json_encode( $context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n\n待审核候选：\n" . json_encode( $generated, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
		),
	);
}

function npcink_rec_eval_cycle_repair_messages( array $context, array $generated, array $review ): array {
	return array(
		array(
			'role'    => 'system',
			'content' => '你是推荐候选修正助手。只输出严格 JSON，结构必须与原候选一致。修正必须忠于文章和已有分类/标签，不得写入 WordPress。',
		),
		array(
			'role'    => 'user',
			'content' => "请根据审核意见修正候选。保留可用候选，替换低质量候选。分类/标签仍然优先使用已有 term_id/name，新标签只放 new_tag_gaps。\n\n文章上下文：\n" . json_encode( $context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n\n原候选：\n" . json_encode( $generated, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n\n审核意见：\n" . json_encode( $review, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
		),
	);
}

function npcink_rec_eval_cycle_env_value( string $key ): string {
	$value = getenv( $key );

	return is_string( $value ) ? $value : '';
}

function npcink_rec_eval_cycle_parse_json_content( string $content ): array {
	$content = trim( $content );
	$content = preg_replace( '/^```(?:json)?\s*/i', '', $content ) ?? $content;
	$content = preg_replace( '/\s*```$/', '', $content ) ?? $content;
	$start   = strpos( $content, '{' );
	$end     = strrpos( $content, '}' );
	if ( false !== $start && false !== $end && $end >= $start ) {
		$content = substr( $content, $start, $end - $start + 1 );
	}

	$decoded = json_decode( $content, true );
	if ( ! is_array( $decoded ) ) {
		throw new RuntimeException( 'Model response was not valid JSON.' );
	}

	return $decoded;
}

function npcink_rec_eval_cycle_call_model( string $profile_name, array $messages, bool $use_json_mode, float $temperature, int $max_tokens ): array {
	$profiles = npcink_rec_eval_cycle_profiles();
	$profile  = $profiles[ $profile_name ];
	$api_key  = npcink_rec_eval_cycle_env_value( $profile['api_key_env'] );
	$base_url = (string) ( getenv( $profile['base_url_env'] ) ?: $profile['base_url'] );
	$model    = (string) ( getenv( $profile['model_env'] ) ?: $profile['model'] );
	if ( '' === $base_url ) {
		throw new RuntimeException( 'Missing base URL environment variable: ' . $profile['base_url_env'] );
	}
	$endpoint = rtrim( $base_url, '/' );
	$endpoint .= str_ends_with( $endpoint, '/v1' ) ? '/chat/completions' : '/v1/chat/completions';

	if ( '' === $api_key ) {
		throw new RuntimeException( 'Missing API key environment variable: ' . $profile['api_key_env'] );
	}
	if ( ! function_exists( 'curl_init' ) ) {
		throw new RuntimeException( 'PHP cURL extension is required for provider calls.' );
	}

	$body = array(
		'model'       => $model,
		'messages'    => $messages,
		'temperature' => $temperature,
		'max_tokens'  => $max_tokens,
	);
	if ( $use_json_mode ) {
		$body['response_format'] = array( 'type' => 'json_object' );
	}

	$handle = curl_init( $endpoint );
	curl_setopt_array(
		$handle,
		array(
			CURLOPT_POST           => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT        => 120,
			CURLOPT_HTTPHEADER     => array(
				'Authorization: Bearer ' . $api_key,
				'Content-Type: application/json',
			),
			CURLOPT_POSTFIELDS     => json_encode( $body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
		)
	);

	$response = curl_exec( $handle );
	$status   = (int) curl_getinfo( $handle, CURLINFO_RESPONSE_CODE );
	$error    = curl_error( $handle );
	curl_close( $handle );

	if ( ! is_string( $response ) || '' !== $error ) {
		throw new RuntimeException( 'Provider request failed: ' . $error );
	}
	if ( $status < 200 || $status >= 300 ) {
		throw new RuntimeException( 'Provider returned HTTP ' . $status . '. Body length: ' . strlen( $response ) );
	}

	$decoded = json_decode( $response, true );
	if ( ! is_array( $decoded ) ) {
		throw new RuntimeException( 'Provider response JSON was invalid.' );
	}

	$content = $decoded['choices'][0]['message']['content'] ?? '';
	if ( is_array( $content ) ) {
		$content = implode( '', array_map( static fn( $part ): string => is_array( $part ) ? (string) ( $part['text'] ?? '' ) : (string) $part, $content ) );
	}
	if ( ! is_string( $content ) || '' === trim( $content ) ) {
		throw new RuntimeException( 'Provider response did not include message content.' );
	}

	return npcink_rec_eval_cycle_parse_json_content( $content );
}

function npcink_rec_eval_cycle_dry_generated( array $context ): array {
	$article = is_array( $context['article'] ?? null ) ? $context['article'] : array();
	$title   = trim( (string) ( $article['title'] ?? '' ) );
	$summary = trim( (string) ( $article['existing_excerpt'] ?? '' ) );
	if ( '' === $summary ) {
		$content = trim( (string) ( $article['content'] ?? '' ) );
		$summary = function_exists( 'mb_substr' ) ? mb_substr( $content, 0, 120, 'UTF-8' ) : substr( $content, 0, 120 );
	}
	$cats = is_array( $context['available_terms']['categories'] ?? null ) ? $context['available_terms']['categories'] : array();
	$tags = is_array( $context['available_terms']['tags'] ?? null ) ? $context['available_terms']['tags'] : array();

	return array(
		'title_candidates'    => array(
			array(
				'value'         => '' !== $title ? $title : 'Dry run title candidate',
				'reason'        => 'dry_run placeholder based on current article title',
				'quality_notes' => array( 'dry_run_only' ),
			),
		),
		'summary_candidates'  => array(
			array(
				'value'         => '' !== $summary ? $summary : 'Dry run summary candidate.',
				'reason'        => 'dry_run placeholder based on existing excerpt or content prefix',
				'quality_notes' => array( 'dry_run_only' ),
			),
		),
		'category_candidates' => array(
			array(
				'term_id'    => (int) ( $cats[0]['term_id'] ?? 0 ),
				'name'       => (string) ( $cats[0]['name'] ?? 'Dry run category' ),
				'reason'     => 'dry_run placeholder using the first exported existing category',
				'confidence' => 0.5,
			),
		),
		'tag_candidates'      => array(
			array(
				'term_id'    => (int) ( $tags[0]['term_id'] ?? 0 ),
				'name'       => (string) ( $tags[0]['name'] ?? 'Dry run tag' ),
				'reason'     => 'dry_run placeholder using the first exported existing tag',
				'confidence' => 0.5,
			),
		),
		'new_tag_gaps'        => array(),
	);
}

function npcink_rec_eval_cycle_dry_review(): array {
	return array(
		'overall_score'     => 3,
		'needs_repair'      => false,
		'tool_scores'       => array(
			'title'    => 3,
			'summary'  => 3,
			'category' => 3,
			'tag'      => 3,
		),
		'issues'            => array(),
		'repair_brief'      => 'dry_run placeholder review; run with provider env vars for real judging.',
		'boundary_verdict'  => 'no_write_boundary_ok',
		'acceptance_notes'  => array( 'dry_run_only' ),
		'reject_if_present' => array(),
	);
}

$arg_map          = npcink_rec_eval_cycle_arg_map( $script_args );
$input            = npcink_rec_eval_cycle_path( (string) ( $arg_map['input'] ?? 'tests/recommendation-eval/generated/samples.json' ), $root );
$output           = npcink_rec_eval_cycle_path( (string) ( $arg_map['output'] ?? 'tests/recommendation-eval/generated/ai-cycle.json' ), $root );
$limit            = max( 1, min( 80, (int) ( $arg_map['limit'] ?? 20 ) ) );
$term_limit       = max( 10, min( 300, (int) ( $arg_map['term_limit'] ?? 120 ) ) );
$sample_sleep     = max( 0, min( 30, (int) ( $arg_map['sample_sleep'] ?? 1 ) ) );
$generator_name   = (string) ( $arg_map['generator_profile'] ?? 'gpt55' );
$reviewer_name    = (string) ( $arg_map['reviewer_profile'] ?? 'deepseek' );
$repair_name      = (string) ( $arg_map['repair_profile'] ?? $generator_name );
$operator_note    = (string) ( $arg_map['operator_note'] ?? '保持客观、具体，优先复用已有分类和标签。' );
$dry_run          = npcink_rec_eval_cycle_bool_arg( $arg_map, 'dry_run', false );
$use_json_mode    = npcink_rec_eval_cycle_bool_arg( $arg_map, 'json_mode', true );
$source           = npcink_rec_eval_cycle_read_json( $input );
$samples          = is_array( $source['samples'] ?? null ) ? array_slice( $source['samples'], 0, $limit ) : array();

if ( array() === $samples ) {
	npcink_rec_eval_cycle_fail( 'Input JSON is missing samples: ' . $input );
}

$generator_profile = npcink_rec_eval_cycle_profile( $generator_name, $dry_run );
$reviewer_profile  = npcink_rec_eval_cycle_profile( $reviewer_name, $dry_run );
$repair_profile    = npcink_rec_eval_cycle_profile( $repair_name, $dry_run );

$output_payload = array(
	'version'      => 1,
	'created_at'   => gmdate( 'c' ),
	'source_file'  => $input,
	'dry_run'      => $dry_run,
	'write_posture' => 'eval_only_no_wordpress_write',
	'profiles'     => array(
		'generator' => npcink_rec_eval_cycle_request_profile( $generator_profile ),
		'reviewer'  => npcink_rec_eval_cycle_request_profile( $reviewer_profile ),
		'repairer'  => npcink_rec_eval_cycle_request_profile( $repair_profile ),
	),
	'operator_note' => $operator_note,
	'samples'       => array(),
);

foreach ( $samples as $index => $sample ) {
	if ( ! is_array( $sample ) ) {
		continue;
	}

	$context = npcink_rec_eval_cycle_sample_context( $sample, $source, $term_limit );
	$cycle   = array(
		'generated' => null,
		'review'    => null,
		'repaired'  => null,
		'errors'    => array(),
	);

	try {
		if ( $dry_run ) {
			$cycle['generated'] = npcink_rec_eval_cycle_dry_generated( $context );
			$cycle['review']    = npcink_rec_eval_cycle_dry_review();
			$cycle['repaired']  = $cycle['generated'];
		} else {
			$cycle['generated'] = npcink_rec_eval_cycle_call_model( $generator_name, npcink_rec_eval_cycle_generator_messages( $context, $operator_note ), $use_json_mode, 0.3, 2200 );
			$cycle['review']    = npcink_rec_eval_cycle_call_model( $reviewer_name, npcink_rec_eval_cycle_reviewer_messages( $context, $cycle['generated'] ), $use_json_mode, 0.1, 1600 );
			$cycle['repaired']  = npcink_rec_eval_cycle_call_model( $repair_name, npcink_rec_eval_cycle_repair_messages( $context, $cycle['generated'], $cycle['review'] ), $use_json_mode, 0.2, 2200 );
		}
	} catch ( Throwable $throwable ) {
		$cycle['errors'][] = array(
			'message' => $throwable->getMessage(),
			'stage'   => 'ai_cycle',
		);
	}

	$output_payload['samples'][] = array(
		'id'             => (string) ( $sample['id'] ?? '' ),
		'post_id'        => (int) ( $sample['post_id'] ?? 0 ),
		'bucket'         => (string) ( $sample['bucket'] ?? '' ),
		'url'            => (string) ( $sample['url'] ?? '' ),
		'title'          => (string) ( $sample['title'] ?? '' ),
		'current_categories' => is_array( $sample['current_categories'] ?? null ) ? $sample['current_categories'] : array(),
		'current_tags'   => is_array( $sample['current_tags'] ?? null ) ? $sample['current_tags'] : array(),
		'ai_cycle'       => $cycle,
	);

	npcink_rec_eval_cycle_write_json( $output, $output_payload );
	echo 'Processed recommendation eval sample ' . ( $index + 1 ) . '/' . count( $samples ) . ': ' . (string) ( $sample['id'] ?? '' ) . "\n";

	if ( ! $dry_run && $sample_sleep > 0 ) {
		sleep( $sample_sleep );
	}
}

echo 'Wrote recommendation AI cycle results to ' . $output . "\n";
