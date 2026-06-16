<?php
/**
 * Local WordPress smoke matrix for editor progressive recommendations.
 *
 * Run with WP-CLI:
 * wp eval-file tests/smoke-editor-progressive-local-matrix.php -- [post_id]
 *
 * @package Npcink_Toolbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "FAIL: Run this script through WP-CLI eval-file so WordPress is loaded.\n" );
	exit( 1 );
}

function toolbox_editor_progressive_matrix_pass( string $message ): void {
	echo "PASS: {$message}\n";
}

function toolbox_editor_progressive_matrix_fail( string $message ): void {
	fwrite( STDERR, "FAIL: {$message}\n" );
	exit( 1 );
}

function toolbox_editor_progressive_matrix_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		toolbox_editor_progressive_matrix_fail( $message );
	}

	toolbox_editor_progressive_matrix_pass( $message );
}

function toolbox_editor_progressive_matrix_admin_user_id(): int {
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

function toolbox_editor_progressive_matrix_sample_post_id( array $script_args ): int {
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

function toolbox_editor_progressive_matrix_post_payload( int $post_id ): array {
	$post = get_post( $post_id );
	if ( ! $post ) {
		toolbox_editor_progressive_matrix_fail( 'Sample post does not exist.' );
	}

	return array(
		'intent'              => 'progressive_recommendations',
		'post_id'             => $post_id,
		'post_type'           => (string) $post->post_type,
		'post_status'         => (string) $post->post_status,
		'title'               => get_the_title( $post ),
		'excerpt'             => has_excerpt( $post ) ? get_the_excerpt( $post ) : '',
		'content'             => wp_strip_all_tags( (string) $post->post_content ),
		'selected_block_text' => '',
		'category_ids'        => implode( ',', array_map( 'absint', wp_get_post_categories( $post_id ) ) ),
		'tag_ids'             => implode( ',', array_map( 'absint', wp_get_post_tags( $post_id, array( 'fields' => 'ids' ) ) ) ),
		'featured_media'      => (string) get_post_thumbnail_id( $post_id ),
	);
}

function toolbox_editor_progressive_matrix_rest_request( array $payload ): array {
	$request = new WP_REST_Request( 'POST', '/npcink-toolbox/v1/editor/content-support' );
	$request->set_body_params( $payload );
	$response = rest_do_request( $request );
	if ( is_wp_error( $response ) ) {
		toolbox_editor_progressive_matrix_fail( 'REST request failed: ' . $response->get_error_code() );
	}

	$data = rest_get_server()->response_to_data( $response, false );
	if ( ! is_array( $data ) ) {
		toolbox_editor_progressive_matrix_fail( 'REST response is not an array.' );
	}

	return $data;
}

function toolbox_editor_progressive_matrix_find_text( $value, string $needle ): bool {
	if ( is_string( $value ) ) {
		return false !== strpos( $value, $needle );
	}
	if ( ! is_array( $value ) ) {
		return false;
	}
	foreach ( $value as $child ) {
		if ( toolbox_editor_progressive_matrix_find_text( $child, $needle ) ) {
			return true;
		}
	}
	return false;
}

function toolbox_editor_progressive_matrix_fingerprint( array $data ): string {
	return (string) ( $data['recommendation_set']['content_fingerprint'] ?? '' );
}

function toolbox_editor_progressive_matrix_has_core_routes(): bool {
	$routes = rest_get_server()->get_routes();
	foreach ( array_keys( $routes ) as $route ) {
		if ( false !== strpos( (string) $route, '/npcink-governance-core/' ) ) {
			return true;
		}
	}
	return false;
}

$admin_user_id = toolbox_editor_progressive_matrix_admin_user_id();
toolbox_editor_progressive_matrix_assert( $admin_user_id > 0, 'Found an administrator user for local smoke requests.' );
wp_set_current_user( $admin_user_id );

if ( ! function_exists( 'is_plugin_active' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}
$plugin_file = defined( 'NPCINK_TOOLBOX_FILE' ) ? plugin_basename( NPCINK_TOOLBOX_FILE ) : '';
toolbox_editor_progressive_matrix_assert( '' !== $plugin_file && is_plugin_active( $plugin_file ), 'Toolbox plugin is active without activation fatal errors.' );

$route_data = toolbox_editor_progressive_matrix_rest_request( array( 'intent' => 'progressive_recommendations' ) );
toolbox_editor_progressive_matrix_assert( 'editor_content_support_flow' === ( $route_data['artifact_type'] ?? '' ), 'Editor content-support REST route is available after activation.' );

$fixture_post_id = wp_insert_post(
	array(
		'post_type'    => 'post',
		'post_status'  => 'draft',
		'post_title'   => 'Progressive recommendation local smoke fixture',
		'post_excerpt' => 'Local no-cloud smoke for taxonomy, media, and preflight suggestions.',
		'post_content' => 'This fixture checks the local editor recommendation set without calling Cloud or creating proposals.',
	),
	true
);
toolbox_editor_progressive_matrix_assert( ! is_wp_error( $fixture_post_id ) && absint( $fixture_post_id ) > 0, 'Created a temporary draft post for the new-article smoke.' );

try {
	$new_payload = toolbox_editor_progressive_matrix_post_payload( absint( $fixture_post_id ) );
	$new_data    = toolbox_editor_progressive_matrix_rest_request( $new_payload );
	$new_section = is_array( $new_data['sections']['progressive_recommendations'] ?? null ) ? $new_data['sections']['progressive_recommendations'] : array();
	$new_set     = is_array( $new_data['recommendation_set'] ?? null ) ? $new_data['recommendation_set'] : array();

	toolbox_editor_progressive_matrix_assert( 'editor_progressive_recommendations.v1' === ( $new_section['artifact_type'] ?? '' ), 'New draft smoke returns the local progressive artifact.' );
	toolbox_editor_progressive_matrix_assert( false === ( $new_section['remote_execution_policy']['cloud_calls'] ?? true ), 'New draft automatic path remains no-Cloud.' );
	toolbox_editor_progressive_matrix_assert( true === ( $new_set['no_write'] ?? false ) && false === ( $new_set['governance']['direct_wordpress_write'] ?? true ), 'New draft recommendation set is explicitly no-write.' );
	toolbox_editor_progressive_matrix_assert( ! empty( $new_set['content_fingerprint'] ), 'New draft recommendation set includes a stable content fingerprint.' );
	toolbox_editor_progressive_matrix_assert( ! toolbox_editor_progressive_matrix_find_text( $new_set, 'submitted_proposal_id' ), 'New draft recommendation set does not create a Core proposal.' );

	$post_id = toolbox_editor_progressive_matrix_sample_post_id( isset( $args ) && is_array( $args ) ? $args : array() );
	toolbox_editor_progressive_matrix_assert( $post_id > 0, 'Found an existing post for the edit-context smoke.' );
	$before = get_post( $post_id, ARRAY_A );
	$base_payload = toolbox_editor_progressive_matrix_post_payload( $post_id );
	$base_data    = toolbox_editor_progressive_matrix_rest_request( $base_payload );
	$base_fp      = toolbox_editor_progressive_matrix_fingerprint( $base_data );

	foreach (
		array(
			'title'               => 'Progressive recommendation changed title smoke',
			'content'             => $base_payload['content'] . ' Extra body signal for local progressive smoke.',
			'excerpt'             => 'Changed excerpt signal for local recommendations.',
			'selected_block_text' => 'Selected block signal for local recommendations.',
		) as $field => $value
	) {
		$variant          = $base_payload;
		$variant[ $field ] = $value;
		$variant_data     = toolbox_editor_progressive_matrix_rest_request( $variant );
		toolbox_editor_progressive_matrix_assert( $base_fp !== toolbox_editor_progressive_matrix_fingerprint( $variant_data ), "Changing {$field} changes the recommendation fingerprint." );
	}

	$after = get_post( $post_id, ARRAY_A );
	toolbox_editor_progressive_matrix_assert(
		is_array( $before ) && is_array( $after )
		&& ( $before['post_title'] ?? '' ) === ( $after['post_title'] ?? '' )
		&& ( $before['post_excerpt'] ?? '' ) === ( $after['post_excerpt'] ?? '' )
		&& ( $before['post_content'] ?? '' ) === ( $after['post_content'] ?? '' )
		&& ( $before['post_status'] ?? '' ) === ( $after['post_status'] ?? '' ),
		'Edit-context smoke leaves the sampled existing post unchanged.'
	);

	$base_section = is_array( $base_data['sections']['progressive_recommendations'] ?? null ) ? $base_data['sections']['progressive_recommendations'] : array();
	$base_set     = is_array( $base_data['recommendation_set'] ?? null ) ? $base_data['recommendation_set'] : array();
	toolbox_editor_progressive_matrix_assert( false === ( $base_section['remote_execution_policy']['cloud_calls'] ?? true ), 'No-Cloud configuration still returns local recommendations.' );
	toolbox_editor_progressive_matrix_assert( 'local' === ( $base_set['source_layer'] ?? '' ), 'Progressive recommendation set reports the local source layer.' );
	toolbox_editor_progressive_matrix_assert( ! toolbox_editor_progressive_matrix_find_text( $base_data, 'provider_execution' ), 'Automatic progressive smoke does not include Cloud provider execution.' );

	$core_available = toolbox_editor_progressive_matrix_has_core_routes();
	$proposal_targets = is_array( $base_set['proposal_targets'] ?? null ) ? $base_set['proposal_targets'] : array();
	foreach ( $proposal_targets as $target ) {
		toolbox_editor_progressive_matrix_assert( 'definition_only_user_trigger_required' === ( $target['handoff_status'] ?? '' ), 'Core handoff target is definition-only until the operator acts.' );
		toolbox_editor_progressive_matrix_assert( false === ( $target['direct_wordpress_write'] ?? true ), 'Core handoff target does not authorize a Toolbox write.' );
		toolbox_editor_progressive_matrix_assert( ! isset( $target['core_route'] ) && ! isset( $target['rest_route'] ) && ! isset( $target['execution_status'] ), 'Core handoff target does not expose raw REST routes or execution state.' );
	}
	toolbox_editor_progressive_matrix_assert( ! toolbox_editor_progressive_matrix_find_text( $base_set, 'submitted_proposal_id' ), $core_available ? 'Core-present smoke does not auto-create a proposal.' : 'Core-absent smoke does not expose an unavailable proposal action.' );

	toolbox_editor_progressive_matrix_assert( 2500 === absint( $base_section['remote_execution_policy']['fallback_for_timeout_ms'] ?? 0 ), 'Progressive REST artifact declares the 2.5 second editor fallback budget.' );
} finally {
	wp_delete_post( absint( $fixture_post_id ), true );
}

toolbox_editor_progressive_matrix_pass( 'Removed the temporary draft fixture.' );
