<?php
/**
 * Local WordPress smoke for editor SEO apply through Adapter/Core.
 *
 * Run with WP-CLI:
 * wp eval-file tests/smoke-editor-seo-apply.php
 *
 * @package Npcink_Toolbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "FAIL: Run this script through WP-CLI eval-file so WordPress is loaded.\n" );
	exit( 1 );
}

$toolbox_editor_seo_apply_post_id      = 0;
$toolbox_editor_seo_apply_proposal_ids = array();

function toolbox_editor_seo_apply_pass( string $message ): void {
	echo "PASS: {$message}\n";
}

function toolbox_editor_seo_apply_info( string $message ): void {
	echo "INFO: {$message}\n";
}

function toolbox_editor_seo_apply_fail( string $message ): void {
	fwrite( STDERR, "FAIL: {$message}\n" );
	toolbox_editor_seo_apply_cleanup();
	exit( 1 );
}

function toolbox_editor_seo_apply_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		toolbox_editor_seo_apply_fail( $message );
	}

	toolbox_editor_seo_apply_pass( $message );
}

function toolbox_editor_seo_apply_admin_user_id(): int {
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

function toolbox_editor_seo_apply_extract_proposal_ids( $value, array &$proposal_ids, int $depth = 0 ): void {
	if ( $depth > 6 || ! is_array( $value ) ) {
		return;
	}

	foreach ( array( 'proposal_id', 'core_proposal_id', 'id' ) as $key ) {
		$proposal_id = sanitize_text_field( (string) ( $value[ $key ] ?? '' ) );
		if ( '' !== $proposal_id ) {
			$proposal_ids[ $proposal_id ] = true;
		}
	}

	foreach ( $value as $child ) {
		if ( is_array( $child ) ) {
			toolbox_editor_seo_apply_extract_proposal_ids( $child, $proposal_ids, $depth + 1 );
		}
	}
}

function toolbox_editor_seo_apply_first_proposal_id( $value ): string {
	$proposal_ids = array();
	toolbox_editor_seo_apply_extract_proposal_ids( $value, $proposal_ids );
	$proposal_ids = array_keys( $proposal_ids );

	return (string) ( $proposal_ids[0] ?? '' );
}

function toolbox_editor_seo_apply_track_fixture( $data ): void {
	global $toolbox_editor_seo_apply_proposal_ids;

	if ( ! is_array( $data ) ) {
		return;
	}
	if ( ! is_array( $toolbox_editor_seo_apply_proposal_ids ) ) {
		$toolbox_editor_seo_apply_proposal_ids = array();
	}

	toolbox_editor_seo_apply_extract_proposal_ids( $data, $toolbox_editor_seo_apply_proposal_ids );
}

function toolbox_editor_seo_apply_should_purge(): bool {
	$value = getenv( 'NPCINK_TOOLBOX_EDITOR_SEO_APPLY_SMOKE_PURGE' );
	if ( ! is_string( $value ) || '' === trim( $value ) ) {
		return true;
	}

	return in_array( strtolower( trim( $value ) ), array( '1', 'true', 'yes' ), true );
}

function toolbox_editor_seo_apply_cleanup(): void {
	global $wpdb, $toolbox_editor_seo_apply_post_id, $toolbox_editor_seo_apply_proposal_ids;

	if ( $toolbox_editor_seo_apply_post_id > 0 && get_post( $toolbox_editor_seo_apply_post_id ) ) {
		wp_delete_post( $toolbox_editor_seo_apply_post_id, true );
		$toolbox_editor_seo_apply_post_id = 0;
	}

	if ( ! toolbox_editor_seo_apply_should_purge() ) {
		return;
	}

	if ( ! is_array( $toolbox_editor_seo_apply_proposal_ids ) ) {
		$toolbox_editor_seo_apply_proposal_ids = array();
	}

	$proposal_ids = array_keys( $toolbox_editor_seo_apply_proposal_ids );
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

	toolbox_editor_seo_apply_info( 'Purged Core proposal fixtures: ' . count( $proposal_ids ) );
}

function toolbox_editor_seo_apply_rest_raw( string $method, string $route, array $params = array() ): array {
	wp_set_current_user( toolbox_editor_seo_apply_admin_user_id() );

	$request = new WP_REST_Request( strtoupper( $method ), $route );
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}

	$response = rest_do_request( $request );
	if ( is_wp_error( $response ) ) {
		toolbox_editor_seo_apply_fail( 'REST dispatch returned WP_Error: ' . $response->get_error_code() );
	}

	$data = $response->get_data();
	toolbox_editor_seo_apply_track_fixture( is_array( $data ) ? $data : array() );

	return array(
		'status' => (int) $response->get_status(),
		'data'   => is_array( $data ) ? $data : array(),
	);
}

function toolbox_editor_seo_apply_rest( string $method, string $route, array $params = array() ): array {
	$response = toolbox_editor_seo_apply_rest_raw( $method, $route, $params );
	toolbox_editor_seo_apply_assert( $response['status'] >= 200 && $response['status'] < 300, 'REST dispatch succeeds for ' . $route . '.' );

	return is_array( $response['data'] ) ? $response['data'] : array();
}

function toolbox_editor_seo_apply_create_post(): int {
	global $toolbox_editor_seo_apply_post_id;

	$post_id = wp_insert_post(
		array(
			'post_type'    => 'post',
			'post_status'  => 'draft',
			'post_title'   => 'Toolbox SEO Apply Smoke Article',
			'post_excerpt' => 'A concise smoke-test excerpt for governed SEO application.',
			'post_content' => 'This temporary article verifies that editor discoverability SEO suggestions can move through Adapter and Core approval before SEO metadata is written.',
		),
		true
	);
	if ( is_wp_error( $post_id ) ) {
		toolbox_editor_seo_apply_fail( 'Unable to create temporary post: ' . $post_id->get_error_code() );
	}

	$toolbox_editor_seo_apply_post_id = absint( $post_id );
	return $toolbox_editor_seo_apply_post_id;
}

function toolbox_editor_seo_apply_meta_snapshot( int $post_id ): array {
	return array(
		'title'       => (string) get_post_meta( $post_id, '_yoast_wpseo_title', true ),
		'description' => (string) get_post_meta( $post_id, '_yoast_wpseo_metadesc', true ),
	);
}

toolbox_editor_seo_apply_assert( class_exists( 'WP_REST_Request' ) && function_exists( 'rest_do_request' ), 'WordPress REST dispatch is available.' );
toolbox_editor_seo_apply_assert( toolbox_editor_seo_apply_admin_user_id() > 0, 'A local administrator is available for REST permission checks.' );

$post_id = toolbox_editor_seo_apply_create_post();
$post    = get_post( $post_id );
toolbox_editor_seo_apply_assert( $post instanceof WP_Post, 'Temporary editor SEO smoke post exists.' );
$before = toolbox_editor_seo_apply_meta_snapshot( $post_id );

$content_support = toolbox_editor_seo_apply_rest(
	'POST',
	'/npcink-toolbox/v1/editor/content-support',
	array(
		'intent'      => 'discoverability',
		'post_id'     => $post_id,
		'post_type'   => 'post',
		'post_status' => (string) $post->post_status,
		'title'       => (string) $post->post_title,
		'excerpt'     => (string) $post->post_excerpt,
		'content'     => (string) $post->post_content,
	)
);

$seo_handoff = is_array( $content_support['sections']['seo_handoff'] ?? null ) ? $content_support['sections']['seo_handoff'] : array();
$template    = is_array( $seo_handoff['proposal_payload_template'] ?? null ) ? $seo_handoff['proposal_payload_template'] : array();
$input       = is_array( $template['input'] ?? null ) ? $template['input'] : array();
$preview     = is_array( $template['preview'] ?? null ) ? $template['preview'] : array();

toolbox_editor_seo_apply_assert( 'seo_meta_handoff_preview.v1' === (string) ( $seo_handoff['artifact_type'] ?? '' ), 'Discoverability returns an SEO handoff preview.' );
toolbox_editor_seo_apply_assert( true === (bool) ( $seo_handoff['proposal_ready'] ?? false ), 'SEO handoff is ready for the temporary post.' );
toolbox_editor_seo_apply_assert( '' !== (string) ( $input['seo_title'] ?? '' ) && '' !== (string) ( $input['seo_description'] ?? '' ), 'SEO handoff includes title and description candidates.' );

$proposal = toolbox_editor_seo_apply_rest(
	'POST',
	'/npcink-openclaw-adapter/v1/proposals',
	array(
		'ability_id' => 'npcink-abilities-toolkit/set-post-seo-meta',
		'title'      => (string) ( $template['title'] ?? 'Review SEO meta for the current post' ),
		'summary'    => (string) ( $template['summary'] ?? 'Single-post SEO title and description candidate prepared by Toolbox for Core-governed review.' ),
		'input'      => array(
			'post_id'         => $post_id,
			'seo_title'       => (string) $input['seo_title'],
			'seo_description' => (string) $input['seo_description'],
			'dry_run'         => false,
			'commit'          => true,
			'idempotency_key' => 'toolbox-editor-seo-apply-smoke-' . $post_id . '-' . time(),
		),
		'preview'    => array_merge(
			$preview,
			array(
				'dry_run'          => false,
				'commit_execution' => true,
			)
		),
		'caller'     => array(
			'surface'            => 'toolbox_editor_content_support',
			'external_thread_id' => 'toolbox-editor-seo-apply-smoke',
			'source'             => 'tests/smoke-editor-seo-apply.php',
		),
	)
);

$proposal_id = toolbox_editor_seo_apply_first_proposal_id( $proposal );
toolbox_editor_seo_apply_assert( '' !== $proposal_id, 'Adapter creates an executable SEO Core proposal.' );

$execute = toolbox_editor_seo_apply_rest_raw( 'POST', '/npcink-openclaw-adapter/v1/proposals/' . rawurlencode( $proposal_id ) . '/approve-and-execute' );
$after   = toolbox_editor_seo_apply_meta_snapshot( $post_id );

if ( $execute['status'] >= 200 && $execute['status'] < 300 && true === (bool) ( $execute['data']['success'] ?? false ) ) {
	toolbox_editor_seo_apply_assert( (string) $input['seo_title'] === $after['title'], 'Approved SEO title is written by the Core-approved ability.' );
	toolbox_editor_seo_apply_assert( (string) $input['seo_description'] === $after['description'], 'Approved SEO description is written by the Core-approved ability.' );
	toolbox_editor_seo_apply_info( 'Editor SEO apply smoke covered approve-and-execute success.' );
} else {
	$core_proposal = toolbox_editor_seo_apply_rest( 'GET', '/npcink-governance-core/v1/proposals/' . rawurlencode( $proposal_id ), array() );
	toolbox_editor_seo_apply_assert( '' !== (string) ( $core_proposal['proposal_id'] ?? $core_proposal['id'] ?? '' ), 'Blocked automatic execution leaves a Core proposal for review.' );
	toolbox_editor_seo_apply_assert( $before === $after, 'Blocked automatic execution leaves SEO metadata unchanged.' );
	toolbox_editor_seo_apply_info( 'Editor SEO apply smoke covered approve-and-execute blocked fallback.' );
}

toolbox_editor_seo_apply_cleanup();
toolbox_editor_seo_apply_info( 'Editor SEO apply smoke passed.' );
