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

