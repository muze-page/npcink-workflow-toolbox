<?php
/**
 * Smoke test for the future Site Ops Cloud analysis request contract.
 *
 * @package Npcink_Toolbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = null ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.stringFound
		return $text;
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $key ) );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return trim( wp_strip_all_tags( (string) $value ) );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $value ) {
		return strip_tags( (string) $value );
	}
}

require_once dirname( __DIR__ ) . '/includes/Site_Ops_Insight_Builder.php';
require_once dirname( __DIR__ ) . '/includes/Site_Ops_Cloud_Request_Builder.php';

function site_ops_cloud_request_smoke_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
	echo "PASS: {$message}\n";
}

$snapshot = array(
	'run_id'       => 'site-ops-fixture-002',
	'site_id'      => 'example-site',
	'generated_at' => '2026-06-22T00:00:00Z',
	'site'         => array(
		'name'        => 'Example Site',
		'description' => 'Public site summary',
		'url_host'    => 'example.test',
	),
	'posts'        => array(
		array(
			'object_type'              => 'post',
			'object_id'                => 101,
			'title'                    => 'Old active tutorial',
			'modified_at'              => '2025-01-01T00:00:00Z',
			'word_count'               => 220,
			'internal_link_count'      => 0,
			'categories'               => array(),
			'tags'                     => array(),
			'missing_alt_count'        => 2,
			'meta_description_present' => false,
			'excerpt_present'          => false,
			'approved_comment_count'   => 4,
		),
	),
	'media'        => array(
		array(
			'object_type'     => 'attachment',
			'object_id'       => 301,
			'title'           => 'Diagram',
			'alt_present'     => false,
			'caption_present' => false,
		),
	),
	'comments'     => array(
		'approved_total'      => 12,
		'pending_total'       => 3,
		'recent_sample_count' => 8,
		'question_like_count' => 4,
		'long_comment_count'  => 2,
		'active_post_count'   => 5,
	),
	'taxonomies'   => array(
		'category' => array(
			'total'       => 5,
			'empty_count' => 1,
			'low_count'   => 2,
		),
		'post_tag' => array(
			'total'       => 9,
			'empty_count' => 2,
			'low_count'   => 3,
		),
	),
);

$context = array(
	'content_context_ready' => true,
	'cloud_ready'           => true,
);

$insight_builder = new \Npcink_Toolbox\Site_Ops_Insight_Builder();
$request_builder = new \Npcink_Toolbox\Site_Ops_Cloud_Request_Builder();
$pack            = $insight_builder->build( $snapshot, $context );
$request         = $request_builder->build( $snapshot, $pack, $context );

site_ops_cloud_request_smoke_assert( 'site_ops_cloud_analysis_request.v1' === ( $request['contract_version'] ?? '' ), 'Site Ops Cloud request exposes the v1 request contract.' );
site_ops_cloud_request_smoke_assert( 'site_ops_cloud_analysis_result.v1' === ( $request['expected_result_contract'] ?? '' ), 'Site Ops Cloud request declares the expected result contract.' );
site_ops_cloud_request_smoke_assert( 'runtime_detail' === ( $request['cloud_role'] ?? '' ), 'Site Ops Cloud request keeps Cloud as runtime detail.' );
site_ops_cloud_request_smoke_assert( 'whole_run_offload' === ( $request['execution_pattern'] ?? '' ), 'Site Ops Cloud request routes complex analysis to whole-run offload.' );
site_ops_cloud_request_smoke_assert( 'suggestion_only' === ( $request['write_posture'] ?? '' ), 'Site Ops Cloud request stays suggestion-only.' );
site_ops_cloud_request_smoke_assert( false === ( $request['direct_wordpress_write'] ?? true ), 'Site Ops Cloud request disables direct WordPress writes.' );
site_ops_cloud_request_smoke_assert( false === ( $request['core_proposal_created'] ?? true ), 'Site Ops Cloud request does not create Core proposals.' );
site_ops_cloud_request_smoke_assert( false === ( $request['local_runtime_created'] ?? true ), 'Site Ops Cloud request does not create a local runtime.' );
site_ops_cloud_request_smoke_assert( false === ( $request['safety']['cloud_called'] ?? true ), 'Site Ops Cloud request builder does not call Cloud.' );
site_ops_cloud_request_smoke_assert( true === ( $request['safety']['cloud_is_runtime_detail_only'] ?? false ), 'Site Ops Cloud request marks Cloud as runtime/detail only.' );
site_ops_cloud_request_smoke_assert( false === ( $request['safety']['comment_text_returned'] ?? true ), 'Site Ops Cloud request omits comment text.' );
site_ops_cloud_request_smoke_assert( false === ( $request['safety']['comment_author_email_returned'] ?? true ), 'Site Ops Cloud request omits comment author emails.' );
site_ops_cloud_request_smoke_assert( false === ( $request['safety']['comment_ip_returned'] ?? true ), 'Site Ops Cloud request omits comment IP addresses.' );
site_ops_cloud_request_smoke_assert( in_array( 'prepare_core_handoff_candidates_without_creating_proposals', $request['input']['analysis_tasks'] ?? array(), true ), 'Site Ops Cloud request can ask Cloud to prepare handoff candidates without creating proposals.' );

$encoded = json_encode( $request );
site_ops_cloud_request_smoke_assert( false !== $encoded, 'Site Ops Cloud request is JSON encodable.' );
foreach ( array( '"comment_content"', '"comment_author_email"', '"comment_author_IP"', '"comment_agent"', 'wp_insert_post', 'wp_update_post', 'update_post_meta', 'wp_remote_post', 'register_rest_route', 'wp_schedule_event', 'as_enqueue_async_action' ) as $forbidden ) {
	site_ops_cloud_request_smoke_assert( false === strpos( (string) $encoded, $forbidden ), 'Site Ops Cloud request omits forbidden field or runtime token: ' . $forbidden );
}
