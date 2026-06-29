<?php
/**
 * Local WordPress smoke for the Operations Insights Cloud analysis E2E path.
 *
 * Requires a verified Npcink Cloud Addon connection and a running Cloud runtime.
 * Run with: wp eval-file tests/smoke-site-ops-cloud-e2e.php
 *
 * @package Npcink_Toolbox
 */

$fail = static function ( string $message ): void {
	fwrite( STDERR, $message . "\n" );
	exit( 1 );
};

$pass = static function ( string $message ): void {
	echo '[ok] ' . $message . "\n";
};

$assert = static function ( bool $condition, string $message ) use ( $fail, $pass ): void {
	if ( ! $condition ) {
		$fail( $message );
	}
	$pass( $message );
};

$flag = static function ( array $payload, string $key, bool $default = false ): bool {
	if ( array_key_exists( $key, $payload ) ) {
		return (bool) $payload[ $key ];
	}
	if ( isset( $payload['safety'] ) && is_array( $payload['safety'] ) && array_key_exists( $key, $payload['safety'] ) ) {
		return (bool) $payload['safety'][ $key ];
	}

	return $default;
};

if (
	! class_exists( '\Npcink_Toolbox\Settings' ) ||
	! class_exists( '\Npcink_Toolbox\Provider_Client' ) ||
	! class_exists( '\Npcink_Toolbox\Site_Ops_Snapshot_Collector' ) ||
	! class_exists( '\Npcink_Toolbox\Site_Ops_Insight_Builder' ) ||
	! class_exists( '\Npcink_Toolbox\Site_Ops_Cloud_Request_Builder' )
) {
	$fail( 'Toolbox Operations Insights classes must be loaded.' );
}

if ( ! function_exists( 'npcink_cloud_addon_runtime_client' ) ) {
	$fail( 'Cloud Addon runtime client seam must be available.' );
}

$runtime_client = npcink_cloud_addon_runtime_client();
$assert( is_object( $runtime_client ) && method_exists( $runtime_client, 'execute_runtime' ), 'Cloud Addon exposes execute_runtime().' );

$settings        = new \Npcink_Toolbox\Settings();
$client          = new \Npcink_Toolbox\Provider_Client( $settings );
$collector       = new \Npcink_Toolbox\Site_Ops_Snapshot_Collector();
$builder         = new \Npcink_Toolbox\Site_Ops_Insight_Builder();
$request_builder = new \Npcink_Toolbox\Site_Ops_Cloud_Request_Builder();

$runtime_context = array(
	'content_context_ready' => true,
	'cloud_ready'           => true,
);

$snapshot = $collector->collect();
$pack     = $builder->build( $snapshot, $runtime_context );
$request  = $request_builder->build( $snapshot, $pack, $runtime_context );

$assert( 'site_ops_cloud_analysis_request.v1' === (string) ( $request['contract_version'] ?? '' ), 'Local request uses the Site Ops Cloud analysis request contract.' );
$assert( 'site_ops_cloud_analysis_result.v1' === (string) ( $request['expected_result_contract'] ?? '' ), 'Local request expects the Site Ops Cloud analysis result contract.' );
$assert( 'runtime_detail' === (string) ( $request['cloud_role'] ?? '' ), 'Local request keeps Cloud in runtime/detail role.' );
$assert( 'whole_run_offload' === (string) ( $request['execution_pattern'] ?? '' ), 'Local request uses whole-run offload.' );
$assert( false === $flag( $request, 'cloud_called' ), 'Local request builder does not call Cloud.' );
$assert( false === $flag( $request, 'direct_wordpress_write' ), 'Local request builder does not grant direct WordPress writes.' );

$result = $client->run_site_ops_cloud_analysis( $request );
if ( is_wp_error( $result ) ) {
	$fail( 'Cloud analysis failed: ' . $result->get_error_code() . ' ' . $result->get_error_message() );
}

$assert( 'site_ops_cloud_analysis_result.v1' === (string) ( $result['contract_version'] ?? '' ), 'Cloud result uses the Site Ops Cloud analysis result contract.' );
$assert( 'npcink-ai-cloud' === (string) ( $result['runtime_owner'] ?? '' ), 'Cloud result declares npcink-ai-cloud runtime ownership.' );
$assert( 'runtime_detail' === (string) ( $result['cloud_role'] ?? '' ), 'Cloud result stays runtime/detail.' );
$assert( 'succeeded' === (string) ( $result['status'] ?? '' ), 'Cloud runtime returns a succeeded Site Ops analysis.' );
$assert( false === $flag( $result, 'direct_wordpress_write', true ), 'Cloud analysis does not grant direct WordPress writes.' );
$assert( false === $flag( $result, 'cloud_scheduler_truth', true ), 'Cloud analysis does not become scheduler truth.' );
$assert( false === $flag( $result, 'core_proposal_created', true ), 'Cloud analysis does not create Core proposals.' );
$assert( true === $flag( $result, 'requires_local_review' ), 'Cloud analysis still requires local review.' );

$cloud_run = is_array( $result['cloud_run'] ?? null ) ? $result['cloud_run'] : array();
$analysis  = is_array( $result['result'] ?? null ) ? $result['result'] : array();

$run_id = sanitize_text_field( (string) ( $cloud_run['run_id'] ?? '' ) );
$assert( '' !== $run_id, 'Cloud analysis returns a run id.' );
$assert( 1 <= count( is_array( $analysis['priority_queue'] ?? null ) ? $analysis['priority_queue'] : array() ), 'Cloud analysis returns priority recommendations.' );
$assert( 1 <= count( is_array( $analysis['trend_notes'] ?? null ) ? $analysis['trend_notes'] : array() ), 'Cloud analysis returns trend notes.' );
$assert( is_array( $analysis['executive_summary'] ?? null ) && '' !== (string) ( $analysis['executive_summary']['headline'] ?? '' ), 'Cloud analysis returns an executive summary headline.' );
$assert( 1 <= count( is_array( $analysis['dimension_summaries'] ?? null ) ? $analysis['dimension_summaries'] : array() ), 'Cloud analysis returns dimension summaries.' );
$assert( 1 <= count( is_array( $analysis['semantic_ranked_findings'] ?? null ) ? $analysis['semantic_ranked_findings'] : array() ), 'Cloud analysis returns semantic ranked findings.' );
$assert( 1 <= count( is_array( $analysis['trend_explanations'] ?? null ) ? $analysis['trend_explanations'] : array() ), 'Cloud analysis returns trend explanations.' );
$assert( is_array( $analysis['analysis_closure'] ?? null ) && '' !== (string) ( $analysis['analysis_closure']['loop_status'] ?? '' ), 'Cloud analysis returns analysis closure state.' );
$assert( 1 <= (int) ( $result['cloud_request_summary']['finding_count'] ?? 0 ), 'Cloud analysis echoes a bounded finding count.' );

if ( defined( 'NPCINK_TOOLBOX_DIR' ) && function_exists( 'load_textdomain' ) ) {
	if ( function_exists( 'unload_textdomain' ) ) {
		unload_textdomain( 'npcink-workflow-toolbox', true );
	}
	load_textdomain( 'npcink-workflow-toolbox', NPCINK_TOOLBOX_DIR . 'languages/npcink-workflow-toolbox-zh_CN.mo' );
}

$admin_page    = new \Npcink_Toolbox\Admin_Page( $settings );
$render_method = new ReflectionMethod( $admin_page, 'render_site_ops_cloud_analysis_result' );

ob_start();
$render_method->invoke( $admin_page, $result );
$rendered_html = (string) ob_get_clean();

$assert( false !== strpos( $rendered_html, 'Cloud 执行摘要' ), 'Rendered Cloud result exposes the Chinese executive summary label.' );
$assert( false !== strpos( $rendered_html, 'Cloud 维度摘要' ), 'Rendered Cloud result exposes the Chinese dimension summary label.' );
$assert( false !== strpos( $rendered_html, '语义排序详情' ), 'Rendered Cloud result exposes the Chinese semantic ranking detail label.' );
$assert( false !== strpos( $rendered_html, '趋势解释' ), 'Rendered Cloud result exposes the Chinese trend explanation label.' );
$assert( false !== strpos( $rendered_html, '分析闭环' ), 'Rendered Cloud result exposes the Chinese analysis closure label.' );
$assert( false !== strpos( $rendered_html, '仅 Cloud 详情；Core 与 WordPress 写入仍由本地治理。' ), 'Rendered Cloud result keeps the Chinese local-governed boundary copy.' );

echo 'Site Ops Cloud analysis E2E smoke passed: ' . $run_id . "\n";
