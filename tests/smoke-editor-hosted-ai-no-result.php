<?php
/**
 * Local WordPress smoke for hosted AI no-result editor diagnostics.
 *
 * Run with WP-CLI:
 * wp eval-file tests/smoke-editor-hosted-ai-no-result.php
 *
 * @package Npcink_Toolbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "FAIL: Run this script through WP-CLI eval-file so WordPress is loaded.\n" );
	exit( 1 );
}

function toolbox_editor_hosted_no_result_pass( string $message ): void {
	echo "PASS: {$message}\n";
}

function toolbox_editor_hosted_no_result_fail( string $message ): void {
	fwrite( STDERR, "FAIL: {$message}\n" );
	exit( 1 );
}

function toolbox_editor_hosted_no_result_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		toolbox_editor_hosted_no_result_fail( $message );
	}

	toolbox_editor_hosted_no_result_pass( $message );
}

function toolbox_editor_hosted_no_result_admin_user_id(): int {
	$users = get_users(
		array(
			'role'    => 'administrator',
			'number'  => 1,
			'orderby' => 'ID',
			'order'   => 'ASC',
			'fields'  => 'ID',
		)
	);

	return absint( $users[0] ?? 0 );
}

function toolbox_editor_hosted_no_result_rest( array $params ): array {
	wp_set_current_user( toolbox_editor_hosted_no_result_admin_user_id() );

	$request = new WP_REST_Request( 'POST', '/npcink-toolbox/v1/editor/content-support' );
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}

	$response = rest_do_request( $request );
	if ( is_wp_error( $response ) ) {
		toolbox_editor_hosted_no_result_fail( 'REST dispatch returned WP_Error: ' . $response->get_error_code() );
	}

	$data = $response->get_data();
	toolbox_editor_hosted_no_result_assert( $response->get_status() >= 200 && $response->get_status() < 300, 'REST paragraph check dispatch succeeds.' );

	return is_array( $data ) ? $data : array();
}

function toolbox_editor_hosted_no_result_cloud_response( string $scenario ): array {
	$base = array(
		'run_id' => 'hosted-no-result-' . $scenario,
		'data'   => array(
			'run_id'            => 'hosted-no-result-' . $scenario,
			'result'            => array(
				'status'      => 'ready',
				'output_text' => '',
			),
			'execution_context' => array(
				'storage_mode'        => 'no_store',
				'data_classification' => 'public_site_content',
			),
		),
	);

	if ( 'omitted' === $scenario ) {
		$base['status']                         = 'omitted';
		$base['data']['status']                 = 'omitted';
		$base['data']['provider_call_count']    = 1;
		$base['data']['idempotent_replay']      = false;
		$base['data']['result']['status']       = 'omitted';
		return $base;
	}

	if ( 'zero_provider_call' === $scenario ) {
		$base['status']                      = 'ready';
		$base['data']['status']              = 'ready';
		$base['data']['provider_call_count'] = 0;
		return $base;
	}

	if ( 'ready_with_output' === $scenario ) {
		$base['status']                                = 'ready';
		$base['data']['status']                        = 'ready';
		$base['data']['provider_call_count']           = 1;
		$base['data']['result']['status']              = 'ready';
		$base['data']['result']['output_json']         = array(
			'clarity_check'       => '托管 AI 返回：这段文字整体可读，但需要人工确认结构。',
			'fact_gaps'           => '托管 AI 返回：未发现需要新增事实。',
			'tone_consistency'    => '托管 AI 返回：语气中性。',
			'editing_suggestions' => '托管 AI 返回：只做审阅，不替换正文。',
		);
		$base['data']['result']['output_text']         = wp_json_encode( $base['data']['result']['output_json'] );
		return $base;
	}

	$base['status']                      = 'ready';
	$base['data']['status']              = 'ready';
	$base['data']['provider_call_count'] = 1;
	$base['data']['idempotent_replay']   = true;
	return $base;
}

toolbox_editor_hosted_no_result_assert( class_exists( 'WP_REST_Request' ) && function_exists( 'rest_do_request' ), 'WordPress REST dispatch is available.' );
toolbox_editor_hosted_no_result_assert( toolbox_editor_hosted_no_result_admin_user_id() > 0, 'A local administrator is available for REST permission checks.' );

$scenario = '';
add_filter(
	'npcink_toolbox_hosted_ai_cloud_request',
	static function ( $handled, array $runtime_payload, array $input ) use ( &$scenario ) {
		unset( $handled, $runtime_payload, $input );
		return toolbox_editor_hosted_no_result_cloud_response( $scenario );
	},
	10,
	3
);

$base_params = array(
	'post_id'             => 0,
	'post_type'           => 'post',
	'post_status'         => 'draft',
	'title'               => 'Hosted AI no-result smoke',
	'excerpt'             => '',
	'content'             => '经测试，在同等服务器条件下，文章数量1万篇时，读取文章列表时间比WordPress快一倍。保存文章时与WordPress耗时相当，因此非常适合小型数据场景适用。',
	'selected_text'       => '经测试，在同等服务器条件下，文章数量1万篇时，读取文章列表时间比WordPress快一倍。保存文章时与WordPress耗时相当，因此非常适合小型数据场景适用。',
	'selected_block_text' => '经测试，在同等服务器条件下，文章数量1万篇时，读取文章列表时间比WordPress快一倍。保存文章时与WordPress耗时相当，因此非常适合小型数据场景适用。',
	'intent'              => 'polish_notes',
	'force_regenerate'    => true,
);

foreach ( array( 'omitted', 'zero_provider_call', 'replay_empty' ) as $scenario_name ) {
	$scenario = $scenario_name;
	$result   = toolbox_editor_hosted_no_result_rest(
		array_merge(
			$base_params,
			array(
				'generation_variant' => 'hosted-no-result-' . $scenario_name . '-' . wp_generate_uuid4(),
			)
		)
	);
	$section  = is_array( $result['sections']['polish_notes'] ?? null ) ? $result['sections']['polish_notes'] : array();
	$items    = is_array( $section['items'] ?? null ) ? $section['items'] : array();

	toolbox_editor_hosted_no_result_assert( 'hosted_ai_with_local_empty_fallback' === (string) ( $section['provider_execution'] ?? '' ), "Paragraph check uses local fallback for {$scenario_name} hosted no-result." );
	toolbox_editor_hosted_no_result_assert( 'local_paragraph_check_after_hosted_ai_empty' === (string) ( $section['fallback_reason'] ?? '' ), "Paragraph check records fallback reason for {$scenario_name}." );
	toolbox_editor_hosted_no_result_assert( count( $items ) >= 4, "Paragraph check returns local review items for {$scenario_name}." );
	toolbox_editor_hosted_no_result_assert( 'no_store' === (string) ( $section['cloud_storage_mode'] ?? '' ), "Paragraph check preserves Cloud storage mode for {$scenario_name}." );
	toolbox_editor_hosted_no_result_assert( array_key_exists( 'cloud_provider_call_count', $section ), "Paragraph check preserves provider call count for {$scenario_name}." );

	if ( 'omitted' === $scenario_name ) {
		toolbox_editor_hosted_no_result_assert( 'omitted' === (string) ( $section['cloud_status'] ?? '' ), 'Paragraph check preserves omitted Cloud status.' );
	}
	if ( 'zero_provider_call' === $scenario_name ) {
		toolbox_editor_hosted_no_result_assert( 0 === absint( $section['cloud_provider_call_count'] ?? -1 ), 'Paragraph check preserves zero provider-call diagnostic.' );
	}
	if ( 'replay_empty' === $scenario_name ) {
		toolbox_editor_hosted_no_result_assert( ! empty( $section['cloud_idempotent_replay'] ), 'Paragraph check preserves idempotent replay diagnostic.' );
	}
}

$scenario       = 'omitted';
$generic_result = toolbox_editor_hosted_no_result_rest(
	array_merge(
		$base_params,
		array(
			'content'             => '安装后直接运行，适用于个人博客、作品集和网站内容展示等小型数据使用场景。',
			'selected_text'       => '安装后直接运行，适用于个人博客、作品集和网站内容展示等小型数据使用场景。',
			'selected_block_text' => '安装后直接运行，适用于个人博客、作品集和网站内容展示等小型数据使用场景。',
			'generation_variant'  => 'hosted-no-result-generic-' . wp_generate_uuid4(),
		)
	)
);
$generic_section = is_array( $generic_result['sections']['polish_notes'] ?? null ) ? $generic_result['sections']['polish_notes'] : array();
$generic_output  = is_array( $generic_section['output_json'] ?? null ) ? $generic_section['output_json'] : array();
$generic_details = implode(
	' ',
	array_map(
		static function ( $item ): string {
			return is_array( $item ) ? (string) ( $item['detail'] ?? '' ) : '';
		},
		is_array( $generic_section['items'] ?? null ) ? $generic_section['items'] : array()
	)
);
$generic_profile = is_array( $generic_section['fallback_signal_profile'] ?? null ) ? $generic_section['fallback_signal_profile'] : array();

toolbox_editor_hosted_no_result_assert( empty( $generic_profile['has_metric_claim'] ), 'Paragraph fallback signal profile detects no metric/performance claim for generic selected text.' );
toolbox_editor_hosted_no_result_assert( empty( $generic_profile['long_or_dense'] ), 'Paragraph fallback does not treat duplicate selected text and selected block text as a dense paragraph.' );
toolbox_editor_hosted_no_result_assert( 1 !== preg_match( '/性能|耗时|保存耗时/u', $generic_details ), 'Generic paragraph fallback does not project performance or save-time wording onto selected text.' );
toolbox_editor_hosted_no_result_assert( false !== strpos( (string) ( $generic_output['editing_suggestions'] ?? '' ), '不要直接替换正文' ), 'Generic paragraph fallback keeps the no-replacement editing posture.' );

$media_binding_text   = '这段说明执行后 core/image attachment id 8053 应保留，并要求评价标准先交代清楚。';
$media_binding_result = toolbox_editor_hosted_no_result_rest(
	array_merge(
		$base_params,
		array(
			'content'             => $media_binding_text,
			'selected_text'       => $media_binding_text,
			'selected_block_text' => $media_binding_text,
			'generation_variant'  => 'hosted-no-result-media-binding-' . wp_generate_uuid4(),
		)
	)
);
$media_binding_section = is_array( $media_binding_result['sections']['polish_notes'] ?? null ) ? $media_binding_result['sections']['polish_notes'] : array();
$media_binding_output  = is_array( $media_binding_section['output_json'] ?? null ) ? $media_binding_section['output_json'] : array();
$media_binding_profile = is_array( $media_binding_section['fallback_signal_profile'] ?? null ) ? $media_binding_section['fallback_signal_profile'] : array();
$media_binding_details = implode(
	' ',
	array_map(
		static function ( $item ): string {
			return is_array( $item ) ? (string) ( $item['detail'] ?? '' ) : '';
		},
		is_array( $media_binding_section['items'] ?? null ) ? $media_binding_section['items'] : array()
	)
);

toolbox_editor_hosted_no_result_assert( ! empty( $media_binding_profile['has_metric_claim'] ), 'Paragraph fallback detects numeric or ID claims in media-binding selected text.' );
toolbox_editor_hosted_no_result_assert( empty( $media_binding_profile['has_performance_claim'] ), 'Paragraph fallback does not treat attachment IDs as performance claims.' );
toolbox_editor_hosted_no_result_assert( false !== strpos( (string) ( $media_binding_output['fact_gaps'] ?? '' ), '数字、ID、数量或范围' ), 'Paragraph fallback gives numeric/ID fact-boundary guidance for media-binding selected text.' );
toolbox_editor_hosted_no_result_assert( 1 !== preg_match( '/速度|耗时|性能口径/u', $media_binding_details ), 'Media-binding paragraph fallback does not project speed or timing wording onto attachment ID text.' );

$late_glue_text   = '这段先说明媒体绑定、编辑器兼容性、移动端堆叠和治理审批上下文，前半段刻意较长以覆盖截断风险。图片来自已审核 WordPress 媒体库，执行后 attachment id 8053 应保留。核心要点文章计划先生成可审查 Gutenberg 结构。评估维度对比文章应先交代评价标准。';
$late_glue_result = toolbox_editor_hosted_no_result_rest(
	array_merge(
		$base_params,
		array(
			'content'             => $late_glue_text,
			'selected_text'       => $late_glue_text,
			'selected_block_text' => $late_glue_text,
			'generation_variant'  => 'hosted-no-result-late-glue-' . wp_generate_uuid4(),
		)
	)
);
$late_glue_section = is_array( $late_glue_result['sections']['polish_notes'] ?? null ) ? $late_glue_result['sections']['polish_notes'] : array();
$late_glue_output  = is_array( $late_glue_section['output_json'] ?? null ) ? $late_glue_section['output_json'] : array();
$late_glue_profile = is_array( $late_glue_section['fallback_signal_profile'] ?? null ) ? $late_glue_section['fallback_signal_profile'] : array();

toolbox_editor_hosted_no_result_assert( ! empty( $late_glue_profile['has_structural_glue'] ), 'Paragraph fallback sees structural glue that appears after the old short selected-text trim window.' );
toolbox_editor_hosted_no_result_assert( false !== strpos( (string) ( $late_glue_output['clarity_check'] ?? '' ), '黏连' ), 'Paragraph fallback prioritizes late structural glue in selected text.' );

$glue_text   = '方案 A适合追求最小改动和快速上线的场景。常见问题文章会直接发布吗？不会。';
$glue_result = toolbox_editor_hosted_no_result_rest(
	array_merge(
		$base_params,
		array(
			'content'             => $glue_text,
			'selected_text'       => $glue_text,
			'selected_block_text' => $glue_text,
			'generation_variant'  => 'hosted-no-result-glue-' . wp_generate_uuid4(),
		)
	)
);
$glue_section = is_array( $glue_result['sections']['polish_notes'] ?? null ) ? $glue_result['sections']['polish_notes'] : array();
$glue_output  = is_array( $glue_section['output_json'] ?? null ) ? $glue_section['output_json'] : array();
$glue_profile = is_array( $glue_section['fallback_signal_profile'] ?? null ) ? $glue_section['fallback_signal_profile'] : array();

toolbox_editor_hosted_no_result_assert( ! empty( $glue_profile['has_structural_glue'] ), 'Paragraph fallback signal profile detects heading or option-label glue.' );
toolbox_editor_hosted_no_result_assert( false !== strpos( (string) ( $glue_output['clarity_check'] ?? '' ), '黏连' ), 'Paragraph fallback explains structural glue instead of returning only generic fact checks.' );

$scenario      = 'ready_with_output';
$overlay_result = toolbox_editor_hosted_no_result_rest(
	array_merge(
		$base_params,
		array(
			'content'             => $glue_text,
			'selected_text'       => $glue_text,
			'selected_block_text' => $glue_text,
			'generation_variant'  => 'hosted-output-overlay-' . wp_generate_uuid4(),
		)
	)
);
$overlay_section = is_array( $overlay_result['sections']['polish_notes'] ?? null ) ? $overlay_result['sections']['polish_notes'] : array();
$overlay_output  = is_array( $overlay_section['output_json'] ?? null ) ? $overlay_section['output_json'] : array();
$overlay          = is_array( $overlay_section['local_review_overlay'] ?? null ) ? $overlay_section['local_review_overlay'] : array();
$overlay_profile  = is_array( $overlay['signal_profile'] ?? null ) ? $overlay['signal_profile'] : array();
$overlay_items    = is_array( $overlay['items'] ?? null ) ? $overlay['items'] : array();
$overlay_details  = implode(
	' ',
	array_map(
		static function ( $item ): string {
			return is_array( $item ) ? (string) ( $item['detail'] ?? '' ) : '';
		},
		$overlay_items
	)
);

toolbox_editor_hosted_no_result_assert( 'hosted_ai' === (string) ( $overlay_section['provider_execution'] ?? '' ), 'Paragraph overlay preserves hosted AI execution when AI returns text.' );
toolbox_editor_hosted_no_result_assert( empty( $overlay_section['fallback_reason'] ), 'Paragraph overlay does not relabel a valid hosted AI result as fallback.' );
toolbox_editor_hosted_no_result_assert( false !== strpos( (string) ( $overlay_output['clarity_check'] ?? '' ), '托管 AI 返回' ), 'Paragraph overlay preserves the hosted AI structured output.' );
toolbox_editor_hosted_no_result_assert( 'paragraph_local_review_overlay.v1' === (string) ( $overlay['artifact_type'] ?? '' ), 'Paragraph overlay returns a local review overlay artifact.' );
toolbox_editor_hosted_no_result_assert( ! empty( $overlay_profile['has_structural_glue'] ), 'Paragraph overlay runs local structural-glue detection even when hosted AI returns output.' );
toolbox_editor_hosted_no_result_assert( false !== strpos( $overlay_details, '黏连' ), 'Paragraph overlay adds local structural-glue review details beside hosted AI output.' );

$article_result = toolbox_editor_hosted_no_result_rest(
	array(
		'post_id'     => 0,
		'post_type'   => 'post',
		'post_status' => 'draft',
		'title'       => 'Article checkup glue smoke',
		'excerpt'     => '',
		'content'     => '核心要点文章计划先生成可审查 Gutenberg 结构。可维护性编辑体验响应式表现治理边界主要差异把差异写进段落和对比卡片。方案 A适合追求最小改动和快速上线的场景。操作顺序 1. 先确认边界 2. 再整理证据 3. 最后人工发布。AEO 关注回答型体验。读者或搜索系统提出一个明确问题时，文章不能先给直接答案，再补充条件、步骤和限制。',
		'intent'      => 'article_checkup',
	)
);
$article_checkup = is_array( $article_result['sections']['article_checkup'] ?? null ) ? $article_result['sections']['article_checkup'] : array();
$article_items   = is_array( $article_checkup['items'] ?? null ) ? $article_checkup['items'] : array();
$format_consistency = is_array( $article_checkup['format_consistency'] ?? null ) ? $article_checkup['format_consistency'] : array();
$semantic_consistency = is_array( $article_checkup['semantic_consistency'] ?? null ) ? $article_checkup['semantic_consistency'] : array();
$article_ids     = array_map(
	static function ( $item ): string {
		return is_array( $item ) ? (string) ( $item['id'] ?? '' ) : '';
	},
	$article_items
);

toolbox_editor_hosted_no_result_assert( in_array( 'structural_glue_1', $article_ids, true ), 'Article checkup detects glued heading labels, phrase groups, or option labels as one scannable issue.' );
toolbox_editor_hosted_no_result_assert( 'format_consistency.v1' === (string) ( $format_consistency['artifact_type'] ?? '' ), 'Article checkup returns a bounded format consistency sub-artifact.' );
toolbox_editor_hosted_no_result_assert( in_array( 'format_inline_list_1', $article_ids, true ), 'Article checkup flags inline numbered lists as layout guidance instead of rewriting them.' );
toolbox_editor_hosted_no_result_assert( 'semantic_consistency.v1' === (string) ( $semantic_consistency['artifact_type'] ?? '' ), 'Article checkup returns a bounded semantic consistency sub-artifact.' );
toolbox_editor_hosted_no_result_assert( in_array( 'semantic_aeo_answer_order', $article_ids, true ), 'Article checkup flags reversed AEO answer-order wording for manual review.' );
toolbox_editor_hosted_no_result_assert( ! empty( $format_consistency['no_rewrite'] ) && false === (bool) ( $format_consistency['direct_wordpress_write'] ?? true ), 'Format consistency check stays no-rewrite and no-write.' );
toolbox_editor_hosted_no_result_assert( ! empty( $semantic_consistency['no_rewrite'] ) && false === (bool) ( $semantic_consistency['direct_wordpress_write'] ?? true ), 'Semantic consistency check stays no-rewrite and no-write.' );
