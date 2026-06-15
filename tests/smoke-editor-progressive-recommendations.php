<?php
/**
 * Local WordPress smoke for editor progressive recommendations.
 *
 * Run with WP-CLI:
 * wp eval-file tests/smoke-editor-progressive-recommendations.php -- [post_id]
 *
 * @package Npcink_Toolbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "FAIL: Run this script through WP-CLI eval-file so WordPress is loaded.\n" );
	exit( 1 );
}

function toolbox_editor_progressive_smoke_pass( string $message ): void {
	echo "PASS: {$message}\n";
}

function toolbox_editor_progressive_smoke_fail( string $message ): void {
	fwrite( STDERR, "FAIL: {$message}\n" );
	exit( 1 );
}

function toolbox_editor_progressive_smoke_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		toolbox_editor_progressive_smoke_fail( $message );
	}

	toolbox_editor_progressive_smoke_pass( $message );
}

function toolbox_editor_progressive_smoke_admin_user_id(): int {
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

function toolbox_editor_progressive_smoke_sample_post_id( array $script_args ): int {
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

function toolbox_editor_progressive_smoke_post_payload( int $post_id ): array {
	$post = get_post( $post_id );
	if ( ! $post ) {
		toolbox_editor_progressive_smoke_fail( 'Sample post does not exist.' );
	}

	return array(
		'intent'         => 'progressive_recommendations',
		'post_id'        => $post_id,
		'post_type'      => (string) $post->post_type,
		'post_status'    => (string) $post->post_status,
		'title'          => get_the_title( $post ),
		'excerpt'        => has_excerpt( $post ) ? get_the_excerpt( $post ) : '',
		'content'        => wp_strip_all_tags( (string) $post->post_content ),
		'category_ids'   => implode( ',', array_map( 'absint', wp_get_post_categories( $post_id ) ) ),
		'tag_ids'        => implode( ',', array_map( 'absint', wp_get_post_tags( $post_id, array( 'fields' => 'ids' ) ) ) ),
		'featured_media' => (string) get_post_thumbnail_id( $post_id ),
	);
}

function toolbox_editor_progressive_smoke_rest_request( array $payload ): array {
	$request = new WP_REST_Request( 'POST', '/npcink-toolbox/v1/editor/content-support' );
	$request->set_body_params( $payload );
	$response = rest_do_request( $request );
	if ( is_wp_error( $response ) ) {
		toolbox_editor_progressive_smoke_fail( 'REST request failed: ' . $response->get_error_code() );
	}

	$data = rest_get_server()->response_to_data( $response, false );
	if ( ! is_array( $data ) ) {
		toolbox_editor_progressive_smoke_fail( 'REST response is not an array.' );
	}

	return $data;
}

function toolbox_editor_progressive_smoke_find_text( $value, string $needle ): bool {
	if ( is_string( $value ) ) {
		return false !== strpos( $value, $needle );
	}
	if ( ! is_array( $value ) ) {
		return false;
	}
	foreach ( $value as $child ) {
		if ( toolbox_editor_progressive_smoke_find_text( $child, $needle ) ) {
			return true;
		}
	}
	return false;
}

$admin_user_id = toolbox_editor_progressive_smoke_admin_user_id();
toolbox_editor_progressive_smoke_assert( $admin_user_id > 0, 'Found an administrator user for the smoke request.' );
wp_set_current_user( $admin_user_id );

$post_id = toolbox_editor_progressive_smoke_sample_post_id( isset( $args ) && is_array( $args ) ? $args : array() );
toolbox_editor_progressive_smoke_assert( $post_id > 0, 'Found a sample post for progressive recommendations.' );

$before = get_post( $post_id, ARRAY_A );
$start  = microtime( true );
$data   = toolbox_editor_progressive_smoke_rest_request( toolbox_editor_progressive_smoke_post_payload( $post_id ) );
$elapsed_ms = (int) round( ( microtime( true ) - $start ) * 1000 );

$section = is_array( $data['sections']['progressive_recommendations'] ?? null ) ? $data['sections']['progressive_recommendations'] : array();
$set     = is_array( $data['recommendation_set'] ?? null ) ? $data['recommendation_set'] : array();
$candidates = is_array( $section['recommendation_candidates'] ?? null ) ? $section['recommendation_candidates'] : array();

toolbox_editor_progressive_smoke_assert( $elapsed_ms < 2500, 'Progressive request completed within the 2.5 second UX budget.' );
toolbox_editor_progressive_smoke_assert( 'editor_progressive_recommendations.v1' === ( $section['artifact_type'] ?? '' ), 'Progressive section returns the v1 local artifact.' );
toolbox_editor_progressive_smoke_assert( false === ( $section['remote_execution_policy']['cloud_calls'] ?? true ), 'Progressive section does not call Cloud.' );
toolbox_editor_progressive_smoke_assert( 'editor_recommendation_set.v1' === ( $set['contract_version'] ?? '' ), 'Progressive response includes the recommendation set wrapper.' );
toolbox_editor_progressive_smoke_assert( ! empty( $set['content_fingerprint'] ), 'Progressive response includes a content fingerprint for UI cache stability.' );
toolbox_editor_progressive_smoke_assert( count( $candidates ) <= 8, 'Progressive response keeps the default review list to eight or fewer candidates.' );
toolbox_editor_progressive_smoke_assert( ! toolbox_editor_progressive_smoke_find_text( $candidates, 'Matched tokens: .' ), 'Progressive candidates do not contain empty matched-token copy.' );
toolbox_editor_progressive_smoke_assert( ! toolbox_editor_progressive_smoke_find_text( $candidates, 'Matched tokens: this' ), 'Progressive candidates do not rank English stopwords as evidence.' );
toolbox_editor_progressive_smoke_assert( ! toolbox_editor_progressive_smoke_find_text( $candidates, 'confirm_token' ) && ! toolbox_editor_progressive_smoke_find_text( $candidates, 'write_confirmed' ), 'Progressive candidates do not expose legacy write confirmation contracts.' );
toolbox_editor_progressive_smoke_assert( false === ( $set['governance']['direct_wordpress_write'] ?? true ), 'Progressive recommendation set keeps direct WordPress writes disabled.' );

$after = get_post( $post_id, ARRAY_A );
toolbox_editor_progressive_smoke_assert(
	is_array( $before ) && is_array( $after )
	&& ( $before['post_title'] ?? '' ) === ( $after['post_title'] ?? '' )
	&& ( $before['post_excerpt'] ?? '' ) === ( $after['post_excerpt'] ?? '' )
	&& ( $before['post_content'] ?? '' ) === ( $after['post_content'] ?? '' )
	&& ( $before['post_status'] ?? '' ) === ( $after['post_status'] ?? '' ),
	'Progressive smoke leaves the sampled post unchanged.'
);

echo 'INFO: Progressive candidates=' . count( $candidates ) . ' elapsed_ms=' . $elapsed_ms . PHP_EOL;
