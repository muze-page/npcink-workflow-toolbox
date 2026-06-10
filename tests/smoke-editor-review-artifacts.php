<?php
/**
 * Local WordPress smoke for editor review artifacts.
 *
 * Run with WP-CLI:
 * wp eval-file tests/smoke-editor-review-artifacts.php -- [post_id]
 *
 * @package Npcink_Toolbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "FAIL: Run this script through WP-CLI eval-file so WordPress is loaded.\n" );
	exit( 1 );
}

$toolbox_editor_review_smoke_created_posts  = array();
$toolbox_editor_review_smoke_proposal_ids   = array();

function toolbox_editor_review_smoke_pass( string $message ): void {
	echo "PASS: {$message}\n";
}

function toolbox_editor_review_smoke_info( string $message ): void {
	echo "INFO: {$message}\n";
}

function toolbox_editor_review_smoke_fail( string $message ): void {
	fwrite( STDERR, "FAIL: {$message}\n" );
	toolbox_editor_review_smoke_cleanup();
	exit( 1 );
}

function toolbox_editor_review_smoke_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		toolbox_editor_review_smoke_fail( $message );
	}

	toolbox_editor_review_smoke_pass( $message );
}

function toolbox_editor_review_smoke_admin_user_id(): int {
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

function toolbox_editor_review_smoke_find_sample_post_id( array $script_args ): int {
	$requested = absint( $script_args[0] ?? 0 );
	if ( $requested > 0 && get_post( $requested ) ) {
		return $requested;
	}

	$posts = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => array( 'draft', 'publish', 'pending', 'future', 'private' ),
			'posts_per_page' => 1,
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'fields'         => 'ids',
		)
	);

	return absint( $posts[0] ?? 0 );
}

function toolbox_editor_review_smoke_public_target_post_id( int $sample_post_id ): int {
	global $toolbox_editor_review_smoke_created_posts;

	$posts = get_posts(
		array(
			'post_type'      => array( 'post', 'page' ),
			'post_status'    => 'publish',
			'post__not_in'   => array_filter( array( $sample_post_id ) ),
			'posts_per_page' => 1,
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'fields'         => 'ids',
		)
	);

	$post_id = absint( $posts[0] ?? 0 );
	if ( $post_id > 0 ) {
		return $post_id;
	}

	$post_id = wp_insert_post(
		array(
			'post_type'    => 'post',
			'post_status'  => 'publish',
			'post_title'   => 'Editor Review Smoke Public Target',
			'post_content' => 'A temporary public post used as Site Knowledge evidence for editor review artifact smoke tests.',
		),
		true
	);
	if ( is_wp_error( $post_id ) ) {
		toolbox_editor_review_smoke_fail( 'Unable to create a temporary public Site Knowledge target: ' . $post_id->get_error_code() );
	}

	$toolbox_editor_review_smoke_created_posts[] = absint( $post_id );
	return absint( $post_id );
}

function toolbox_editor_review_smoke_cleanup(): void {
	global $toolbox_editor_review_smoke_created_posts;

	toolbox_editor_review_smoke_purge_governance_records();

	$created_posts = is_array( $toolbox_editor_review_smoke_created_posts ) ? $toolbox_editor_review_smoke_created_posts : array();
	foreach ( array_unique( array_map( 'absint', $created_posts ) ) as $post_id ) {
		if ( $post_id > 0 && get_post( $post_id ) ) {
			wp_delete_post( $post_id, true );
		}
	}
}

function toolbox_editor_review_smoke_should_purge_governance_records(): bool {
	$value = getenv( 'NPCINK_TOOLBOX_EDITOR_REVIEW_SMOKE_PURGE' );
	if ( ! is_string( $value ) || '' === trim( $value ) ) {
		return true;
	}

	return in_array( strtolower( trim( $value ) ), array( '1', 'true', 'yes' ), true );
}

function toolbox_editor_review_smoke_extract_proposal_ids( $value, array &$proposal_ids, int $depth = 0 ): void {
	if ( $depth > 6 || ! is_array( $value ) ) {
		return;
	}

	foreach ( array( 'proposal_id', 'core_proposal_id', 'id' ) as $key ) {
		$proposal_id = trim( (string) ( $value[ $key ] ?? '' ) );
		if ( '' !== $proposal_id ) {
			$proposal_ids[ $proposal_id ] = true;
		}
	}

	foreach ( $value as $child ) {
		if ( is_array( $child ) ) {
			toolbox_editor_review_smoke_extract_proposal_ids( $child, $proposal_ids, $depth + 1 );
		}
	}
}

function toolbox_editor_review_smoke_first_proposal_id( $value ): string {
	$proposal_ids = array();
	toolbox_editor_review_smoke_extract_proposal_ids( $value, $proposal_ids );
	$proposal_ids = array_keys( $proposal_ids );

	return (string) ( $proposal_ids[0] ?? '' );
}

function toolbox_editor_review_smoke_track_rest_fixture( string $route, $data ): void {
	global $toolbox_editor_review_smoke_proposal_ids;

	if ( '/npcink-openclaw-adapter/v1/proposals' !== $route || ! is_array( $data ) ) {
		return;
	}

	if ( ! is_array( $toolbox_editor_review_smoke_proposal_ids ) ) {
		$toolbox_editor_review_smoke_proposal_ids = array();
	}

	toolbox_editor_review_smoke_extract_proposal_ids( $data, $toolbox_editor_review_smoke_proposal_ids );
}

function toolbox_editor_review_smoke_purge_governance_records(): void {
	global $wpdb, $toolbox_editor_review_smoke_proposal_ids;

	if ( ! toolbox_editor_review_smoke_should_purge_governance_records() ) {
		return;
	}

	$proposal_ids = array_keys( is_array( $toolbox_editor_review_smoke_proposal_ids ) ? $toolbox_editor_review_smoke_proposal_ids : array() );
	if ( empty( $proposal_ids ) ) {
		return;
	}

	$audit_table    = $wpdb->prefix . 'npcink_governance_core_audit_log';
	$proposal_table = $wpdb->prefix . 'npcink_governance_core_proposals';

	foreach ( $proposal_ids as $proposal_id ) {
		$proposal_id = sanitize_text_field( $proposal_id );
		$wpdb->delete( $audit_table, array( 'proposal_id' => $proposal_id ), array( '%s' ) );
		$wpdb->delete( $proposal_table, array( 'proposal_id' => $proposal_id ), array( '%s' ) );
	}

	toolbox_editor_review_smoke_info( 'Purged Core proposal fixtures: ' . count( $proposal_ids ) );
}

function toolbox_editor_review_smoke_post_snapshot( int $post_id ): array {
	$post = get_post( $post_id );
	if ( ! $post ) {
		return array();
	}

	return array(
		'post_title'    => (string) $post->post_title,
		'post_excerpt'  => (string) $post->post_excerpt,
		'post_content'  => (string) $post->post_content,
		'post_status'   => (string) $post->post_status,
		'post_modified' => (string) $post->post_modified,
	);
}

function toolbox_editor_review_smoke_post_count(): int {
	$counts = wp_count_posts( 'post' );
	$total  = 0;

	foreach ( get_object_vars( $counts ) as $count ) {
		$total += absint( $count );
	}

	return $total;
}

function toolbox_editor_review_smoke_rest_route( string $method, string $route, array $params ): array {
	wp_set_current_user( toolbox_editor_review_smoke_admin_user_id() );

	$request = new WP_REST_Request( strtoupper( $method ), $route );
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}

	$response = rest_do_request( $request );
	if ( is_wp_error( $response ) ) {
		toolbox_editor_review_smoke_fail( 'REST dispatch returned WP_Error: ' . $response->get_error_code() );
	}

	$data = $response->get_data();
	toolbox_editor_review_smoke_assert( $response->get_status() >= 200 && $response->get_status() < 300, 'REST dispatch succeeds for ' . $route . '.' );
	toolbox_editor_review_smoke_track_rest_fixture( $route, $data );

	return is_array( $data ) ? $data : array();
}

function toolbox_editor_review_smoke_rest( array $params ): array {
	return toolbox_editor_review_smoke_rest_route( 'POST', '/npcink-toolbox/v1/editor/content-support', $params );
}

$script_args = isset( $args ) && is_array( $args ) ? $args : array();

toolbox_editor_review_smoke_assert( class_exists( 'WP_REST_Request' ) && function_exists( 'rest_do_request' ), 'WordPress REST dispatch is available.' );
toolbox_editor_review_smoke_assert( toolbox_editor_review_smoke_admin_user_id() > 0, 'A local administrator is available for REST permission checks.' );

$sample_post_id = toolbox_editor_review_smoke_find_sample_post_id( $script_args );
toolbox_editor_review_smoke_assert( $sample_post_id > 0, 'A local post is available for editor review artifact smoke.' );
$target_post_id = toolbox_editor_review_smoke_public_target_post_id( $sample_post_id );
toolbox_editor_review_smoke_assert( $target_post_id > 0 && $target_post_id !== $sample_post_id, 'A public Site Knowledge target post is available.' );

$post         = get_post( $sample_post_id );
$before       = toolbox_editor_review_smoke_post_snapshot( $sample_post_id );
$post_count   = toolbox_editor_review_smoke_post_count();
$category_ids = wp_get_post_categories( $sample_post_id );
$tag_ids      = wp_get_post_tags( $sample_post_id, array( 'fields' => 'ids' ) );

add_filter(
	'npcink_toolbox_site_knowledge_cloud_request',
	static function ( $handled, array $runtime_payload ) use ( $target_post_id ) {
		$intent = sanitize_key( (string) ( $runtime_payload['input']['intent'] ?? '' ) );
		return array(
			'status' => 'ready',
			'data'   => array(
				'result' => array(
					'status'  => 'ready',
					'intent'  => $intent,
					'results' => array(
						array(
							'post_id'     => $target_post_id,
							'source_type' => 'post',
							'title'       => get_the_title( $target_post_id ),
							'url'         => get_permalink( $target_post_id ),
							'excerpt'     => 'Related public article returned by mocked Site Knowledge.',
							'score'       => 0.91,
							'reason'      => 'Mocked Site Knowledge related-content match for editor review smoke.',
						),
					),
					'coverage' => array(
						'indexed_public_posts' => 1,
					),
				),
			),
		);
	},
	10,
	2
);

$base_params = array(
	'post_id'        => $sample_post_id,
	'post_type'      => get_post_type( $sample_post_id ),
	'post_status'    => get_post_status( $sample_post_id ),
	'title'          => get_the_title( $sample_post_id ),
	'excerpt'        => wp_strip_all_tags( get_the_excerpt( $sample_post_id ) ),
	'content'        => wp_strip_all_tags( (string) $post->post_content ),
	'category_ids'   => implode( ',', array_map( 'absint', is_array( $category_ids ) ? $category_ids : array() ) ),
	'tag_ids'        => implode( ',', array_map( 'absint', is_array( $tag_ids ) ? $tag_ids : array() ) ),
	'featured_media' => absint( get_post_thumbnail_id( $sample_post_id ) ),
	'context_scope'  => 'full_article',
);

$internal_links_result = toolbox_editor_review_smoke_rest( array( 'intent' => 'internal_links' ) + $base_params );
$internal_links        = is_array( $internal_links_result['sections']['internal_links'] ?? null ) ? $internal_links_result['sections']['internal_links'] : array();
$internal_items        = is_array( $internal_links['items'] ?? null ) ? $internal_links['items'] : array();
$internal_handoff      = is_array( $internal_links['handoff'] ?? null ) ? $internal_links['handoff'] : array();

toolbox_editor_review_smoke_assert( 'editor_content_support_flow' === (string) ( $internal_links_result['artifact_type'] ?? '' ), 'Internal-links result declares editor_content_support_flow.' );
toolbox_editor_review_smoke_assert( 'internal_links' === (string) ( $internal_links_result['intent'] ?? '' ), 'Internal-links result preserves the fixed intent.' );
toolbox_editor_review_smoke_assert( false === (bool) ( $internal_links_result['direct_wordpress_write'] ?? true ), 'Internal-links flow disables direct WordPress writes.' );
toolbox_editor_review_smoke_assert( 'internal_link_candidates.v1' === (string) ( $internal_links['artifact_type'] ?? '' ), 'Internal-links section returns the structured candidate artifact.' );
toolbox_editor_review_smoke_assert( 'operator_review_only_no_insert' === (string) ( $internal_links['final_write_path'] ?? '' ), 'Internal-link candidates remain operator-review only without insertion.' );
toolbox_editor_review_smoke_assert( false === (bool) ( $internal_links['direct_wordpress_write'] ?? true ), 'Internal-link section disables direct WordPress writes.' );
toolbox_editor_review_smoke_assert( ! empty( $internal_items ), 'Internal-link section returns at least one candidate from mocked Site Knowledge.' );
toolbox_editor_review_smoke_assert( $target_post_id === absint( $internal_items[0]['target_post_id'] ?? 0 ), 'Internal-link candidate points to the public target post.' );
toolbox_editor_review_smoke_assert( 'review_only_candidate' === (string) ( $internal_items[0]['status'] ?? '' ), 'Internal-link candidate is review-only.' );
toolbox_editor_review_smoke_assert( in_array( 'no_link_insertion_in_toolbox', (array) ( $internal_handoff['blocked_actions'] ?? array() ), true ), 'Internal-link handoff blocks Toolbox link insertion.' );

$publish_result  = toolbox_editor_review_smoke_rest( array( 'intent' => 'publish_preflight' ) + $base_params );
$review          = is_array( $publish_result['sections']['pre_publish_review'] ?? null ) ? $publish_result['sections']['pre_publish_review'] : array();
$seo_handoff     = is_array( $publish_result['sections']['seo_handoff'] ?? null ) ? $publish_result['sections']['seo_handoff'] : array();
$duplicate_check = is_array( $publish_result['sections']['duplicate_check'] ?? null ) ? $publish_result['sections']['duplicate_check'] : array();
$review_items    = is_array( $review['items'] ?? null ) ? $review['items'] : array();
$review_names    = array_values( array_map( static fn( array $item ): string => (string) ( $item['name'] ?? '' ), $review_items ) );

toolbox_editor_review_smoke_assert( 'publish_preflight' === (string) ( $publish_result['intent'] ?? '' ), 'Publish preflight result preserves the fixed intent.' );
toolbox_editor_review_smoke_assert( false === (bool) ( $publish_result['direct_wordpress_write'] ?? true ), 'Publish preflight flow disables direct WordPress writes.' );
toolbox_editor_review_smoke_assert( 'pre_publish_review.v1' === (string) ( $review['artifact_type'] ?? '' ), 'Publish preflight returns the unified review artifact.' );
toolbox_editor_review_smoke_assert( false === (bool) ( $review['direct_wordpress_write'] ?? true ), 'Pre-publish review disables direct WordPress writes.' );
foreach ( array( 'summary', 'categories', 'tags', 'featured_image', 'internal_links', 'seo_meta', 'duplicate_risk' ) as $expected_item ) {
	toolbox_editor_review_smoke_assert( in_array( $expected_item, $review_names, true ), 'Pre-publish review includes ' . $expected_item . ' item.' );
}
toolbox_editor_review_smoke_assert( 'seo_meta_handoff_preview.v1' === (string) ( $seo_handoff['artifact_type'] ?? '' ), 'Publish preflight returns an SEO handoff preview artifact.' );
toolbox_editor_review_smoke_assert( 'npcink-abilities-toolkit/set-post-seo-meta' === (string) ( $seo_handoff['target_ability_id'] ?? '' ), 'SEO handoff targets the governed SEO metadata ability.' );
toolbox_editor_review_smoke_assert( false === (bool) ( $seo_handoff['direct_wordpress_write'] ?? true ), 'SEO handoff disables direct WordPress writes.' );
toolbox_editor_review_smoke_assert( true === (bool) ( $seo_handoff['proposal_ready'] ?? false ), 'SEO handoff is proposal-ready for the sampled post.' );
toolbox_editor_review_smoke_assert( in_array( 'no_seo_meta_write_in_toolbox', (array) ( $seo_handoff['blocked_actions'] ?? array() ), true ), 'SEO handoff blocks Toolbox SEO metadata writes.' );
toolbox_editor_review_smoke_assert( 'site_knowledge_results' === (string) ( $duplicate_check['artifact_type'] ?? '' ), 'Publish preflight keeps duplicate check evidence from Site Knowledge.' );

$seo_template = is_array( $seo_handoff['proposal_payload_template'] ?? null ) ? $seo_handoff['proposal_payload_template'] : array();
$seo_input    = is_array( $seo_template['input'] ?? null ) ? $seo_template['input'] : array();
$seo_preview  = is_array( $seo_template['preview'] ?? null ) ? $seo_template['preview'] : array();
$field_patch  = is_array( $seo_preview['field_patch'] ?? null ) ? $seo_preview['field_patch'] : array();

toolbox_editor_review_smoke_assert( true === (bool) ( $seo_input['dry_run'] ?? false ) && false === (bool) ( $seo_input['commit'] ?? true ), 'SEO proposal template remains dry-run and non-commit.' );
toolbox_editor_review_smoke_assert( false === (bool) ( $seo_preview['commit_execution'] ?? true ), 'SEO proposal preview disables commit execution.' );
toolbox_editor_review_smoke_assert( 2 === count( $field_patch ), 'SEO proposal preview includes field-level title and description patches.' );

$seo_proposal = toolbox_editor_review_smoke_rest_route(
	'POST',
	'/npcink-openclaw-adapter/v1/proposals',
	array(
		'ability_id' => 'npcink-abilities-toolkit/set-post-seo-meta',
		'title'      => (string) ( $seo_template['title'] ?? 'Review SEO meta for the current post' ),
		'summary'    => (string) ( $seo_template['summary'] ?? 'Single-post SEO title and description candidate prepared by Toolbox for Core-governed review.' ),
		'input'      => array(
			'post_id'         => $sample_post_id,
			'seo_title'       => (string) ( $seo_input['seo_title'] ?? '' ),
			'seo_description' => (string) ( $seo_input['seo_description'] ?? '' ),
			'dry_run'         => true,
			'commit'          => false,
		),
		'preview'    => $seo_preview,
		'caller'     => array(
			'surface'            => 'toolbox_editor_content_support',
			'external_thread_id' => 'toolbox-editor-review-artifacts-smoke',
			'source'             => 'tests/smoke-editor-review-artifacts.php',
		),
	)
);
$seo_proposal_id = toolbox_editor_review_smoke_first_proposal_id( $seo_proposal );
toolbox_editor_review_smoke_assert( '' !== $seo_proposal_id, 'Adapter creates one Core SEO metadata proposal from publish preflight.' );
toolbox_editor_review_smoke_assert( 'pending' === (string) ( $seo_proposal['status'] ?? '' ), 'SEO metadata proposal starts pending approval.' );
toolbox_editor_review_smoke_assert( 'npcink-abilities-toolkit/set-post-seo-meta' === (string) ( $seo_proposal['ability_id'] ?? '' ), 'SEO metadata proposal stores the governed ability id.' );

$core_proposal         = toolbox_editor_review_smoke_rest_route( 'GET', '/npcink-governance-core/v1/proposals/' . rawurlencode( $seo_proposal_id ), array() );
$core_proposal_preview = is_array( $core_proposal['preview'] ?? null ) ? $core_proposal['preview'] : array();
$core_field_patch      = is_array( $core_proposal_preview['field_patch'] ?? null ) ? $core_proposal_preview['field_patch'] : array();
$core_patch_fields     = array_values(
	array_filter(
		array_map(
			static fn( $patch ): string => is_array( $patch ) ? (string) ( $patch['field'] ?? '' ) : '',
			$core_field_patch
		)
	)
);
toolbox_editor_review_smoke_assert( 'pending' === (string) ( $core_proposal['status'] ?? '' ), 'Core proposal detail keeps the SEO handoff pending for human review.' );
toolbox_editor_review_smoke_assert( 'npcink-abilities-toolkit/set-post-seo-meta' === (string) ( $core_proposal['ability_id'] ?? '' ), 'Core proposal detail returns the governed SEO ability id.' );
toolbox_editor_review_smoke_assert( in_array( 'seo_title', $core_patch_fields, true ) && in_array( 'seo_description', $core_patch_fields, true ), 'Core proposal detail preserves reviewable SEO field patches.' );
toolbox_editor_review_smoke_assert( ! empty( $core_proposal['audit_timeline'] ) && is_array( $core_proposal['audit_timeline'] ), 'Core proposal detail returns an audit timeline for the SEO handoff.' );

$after = toolbox_editor_review_smoke_post_snapshot( $sample_post_id );
toolbox_editor_review_smoke_assert( $before === $after, 'Editor review artifact smoke does not mutate the sampled post.' );
toolbox_editor_review_smoke_assert( $post_count === toolbox_editor_review_smoke_post_count(), 'Editor review artifact smoke leaves the post count unchanged after cleanup accounting.' );

toolbox_editor_review_smoke_cleanup();
toolbox_editor_review_smoke_info( 'Editor review artifact smoke passed.' );
