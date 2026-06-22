<?php
/**
 * Smoke test for the local Site Ops Insights builder.
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

function site_ops_smoke_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
	echo "PASS: {$message}\n";
}

$snapshot = array(
	'run_id'       => 'site-ops-fixture-001',
	'site_id'      => 'example-site',
	'generated_at' => '2026-06-22T00:00:00Z',
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
			'featured_image_present'   => false,
			'missing_alt_count'        => 2,
			'meta_description_present' => false,
			'excerpt_present'          => false,
			'approved_comment_count'   => 4,
		),
		array(
			'object_type'              => 'page',
			'object_id'                => 202,
			'title'                    => 'Reference page',
			'modified_at'              => '2026-05-01T00:00:00Z',
			'word_count'               => 900,
			'internal_link_count'      => 2,
			'categories'               => array( 'Docs' ),
			'tags'                     => array( 'reference' ),
			'featured_image_present'   => true,
			'missing_alt_count'        => 0,
			'meta_description_present' => true,
			'excerpt_present'          => true,
			'approved_comment_count'   => 0,
		),
	),
	'media'        => array(
		array(
			'object_type'      => 'attachment',
			'object_id'        => 301,
			'title'            => 'Diagram',
			'filename_present' => true,
			'alt_present'      => false,
			'caption_present'  => false,
		),
	),
	'comments'     => array(
		'approved_total'      => 12,
		'pending_total'       => 3,
		'recent_sample_count' => 8,
		'question_like_count' => 4,
		'long_comment_count'  => 2,
		'privacy'             => array(
			'comment_text_returned' => false,
			'author_email_returned' => false,
			'ip_address_returned'   => false,
			'user_agent_returned'   => false,
		),
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

$builder = new \Npcink_Toolbox\Site_Ops_Insight_Builder();
$pack    = $builder->build(
	$snapshot,
	array(
		'content_context_ready' => false,
		'cloud_ready'           => false,
	)
);

site_ops_smoke_assert( 'site_ops_insight_pack.v1' === ( $pack['contract_version'] ?? '' ), 'Site Ops builder returns the v1 insight pack contract.' );
site_ops_smoke_assert( 'suggestion_only' === ( $pack['write_posture'] ?? '' ), 'Site Ops insight pack stays suggestion-only.' );
site_ops_smoke_assert( false === ( $pack['direct_wordpress_write'] ?? true ), 'Site Ops insight pack disables direct WordPress writes.' );
site_ops_smoke_assert( false === ( $pack['cloud_required'] ?? true ), 'Site Ops P0 does not require Cloud.' );
site_ops_smoke_assert( false === ( $pack['core_proposal_created'] ?? true ), 'Site Ops P0 does not create Core proposals.' );
site_ops_smoke_assert( ! empty( $pack['top_findings'] ) && count( $pack['top_findings'] ) >= 4, 'Site Ops builder ranks multiple local operations findings.' );
site_ops_smoke_assert( false === ( $pack['safety']['comment_text_returned'] ?? true ), 'Site Ops insight pack omits full comment text.' );
site_ops_smoke_assert( false === ( $pack['safety']['comment_author_email_returned'] ?? true ), 'Site Ops insight pack omits comment author emails.' );
site_ops_smoke_assert( false === ( $pack['safety']['comment_ip_returned'] ?? true ), 'Site Ops insight pack omits comment IP addresses.' );

$encoded = json_encode( $pack );
site_ops_smoke_assert( false !== $encoded, 'Site Ops insight pack is JSON encodable.' );
foreach ( array( '"comment_content"', '"comment_author_email"', '"comment_author_IP"', '"comment_agent"', 'wp_insert_post', 'wp_update_post', 'update_post_meta' ) as $forbidden ) {
	site_ops_smoke_assert( false === strpos( (string) $encoded, $forbidden ), 'Site Ops insight pack omits forbidden field or write token: ' . $forbidden );
}
