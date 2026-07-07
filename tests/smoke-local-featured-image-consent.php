<?php
/**
 * Local WordPress smoke for Local Admin Consent featured image proof.
 *
 * Run with WP-CLI:
 * wp eval-file tests/smoke-local-featured-image-consent.php -- [post_id]
 *
 * @package Npcink_Toolbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "FAIL: Run this script through WP-CLI eval-file so WordPress is loaded.\n" );
	exit( 1 );
}

function toolbox_local_featured_smoke_pass( string $message ): void {
	echo "PASS: {$message}\n";
}

function toolbox_local_featured_smoke_fail( string $message ): void {
	fwrite( STDERR, "FAIL: {$message}\n" );
	exit( 1 );
}

function toolbox_local_featured_smoke_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		toolbox_local_featured_smoke_fail( $message );
	}

	toolbox_local_featured_smoke_pass( $message );
}

function toolbox_local_featured_smoke_admin_user_id(): int {
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

function toolbox_local_featured_smoke_find_post_id( array $script_args ): int {
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

function toolbox_local_featured_smoke_create_attachment(): int {
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$png = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=', true );
	if ( ! is_string( $png ) ) {
		toolbox_local_featured_smoke_fail( 'Could not decode smoke image.' );
	}

	$upload = wp_upload_bits( 'npcink-local-consent-featured-image.png', null, $png );
	if ( ! empty( $upload['error'] ) || empty( $upload['file'] ) ) {
		toolbox_local_featured_smoke_fail( 'Could not create smoke image upload.' );
	}

	$attachment_id = wp_insert_attachment(
		array(
			'post_mime_type' => 'image/png',
			'post_title'     => 'Npcink Local Consent Featured Image Smoke',
			'post_status'    => 'inherit',
		),
		(string) $upload['file']
	);
	$attachment_id = absint( $attachment_id );
	if ( $attachment_id <= 0 ) {
		toolbox_local_featured_smoke_fail( 'Could not create smoke image attachment.' );
	}

	$metadata = wp_generate_attachment_metadata( $attachment_id, (string) $upload['file'] );
	if ( is_array( $metadata ) ) {
		wp_update_attachment_metadata( $attachment_id, $metadata );
	}

	return $attachment_id;
}

$admin_id = toolbox_local_featured_smoke_admin_user_id();
toolbox_local_featured_smoke_assert( $admin_id > 0, 'A local administrator is available.' );
wp_set_current_user( $admin_id );

$post_id = toolbox_local_featured_smoke_find_post_id( $argv ?? array() );
toolbox_local_featured_smoke_assert( $post_id > 0, 'A local post is available.' );

$before_attachment_id = absint( get_post_thumbnail_id( $post_id ) );
$attachment_id        = toolbox_local_featured_smoke_create_attachment();

$request = new WP_REST_Request( 'POST', '/npcink-toolbox/v1/local-admin-consent/featured-image' );
$request->set_body_params(
	array(
		'post_id'       => $post_id,
		'attachment_id' => $attachment_id,
		'candidate'     => array(
			'title'  => 'Smoke selected image',
			'source' => 'media_library',
			'url'    => wp_get_attachment_url( $attachment_id ),
		),
	)
);

$response = rest_do_request( $request );
$status   = absint( $response->get_status() );
$data     = $response->get_data();

toolbox_local_featured_smoke_assert( 200 === $status, 'Local featured image consent REST dispatch succeeds.' );
toolbox_local_featured_smoke_assert( is_array( $data ) && 'local_admin_consent_featured_image_result' === (string) ( $data['artifact_type'] ?? '' ), 'REST result declares the local consent artifact.' );
toolbox_local_featured_smoke_assert( 'local_admin_consent' === (string) ( $data['classification']['classification'] ?? '' ), 'REST result records local_admin_consent classification.' );
toolbox_local_featured_smoke_assert( 'operation-classification-v1' === (string) ( $data['classification']['decision_envelope']['decision_version'] ?? '' ), 'REST result records the current classification decision envelope.' );
toolbox_local_featured_smoke_assert( 'set_featured_image' === (string) ( $data['classification']['decision_envelope']['operation_kind'] ?? '' ), 'REST result classification envelope records the featured-image operation kind.' );
toolbox_local_featured_smoke_assert( false === (bool) ( $data['proposal_created'] ?? true ), 'REST result does not create a Core proposal.' );
toolbox_local_featured_smoke_assert( 'npcink-governance-core' === (string) ( $data['audit_owner'] ?? '' ), 'REST result assigns audit ownership to Core.' );
toolbox_local_featured_smoke_assert( ! empty( $data['audit']['requested']['event_id'] ?? '' ) && ! empty( $data['audit']['completed']['event_id'] ?? '' ), 'REST result includes Core requested and completed audit events.' );
toolbox_local_featured_smoke_assert( $attachment_id === absint( get_post_thumbnail_id( $post_id ) ), 'Local consent sets the smoke attachment as the featured image.' );

if ( $before_attachment_id > 0 ) {
	set_post_thumbnail( $post_id, $before_attachment_id );
} else {
	delete_post_thumbnail( $post_id );
}
wp_delete_attachment( $attachment_id, true );

toolbox_local_featured_smoke_assert( $before_attachment_id === absint( get_post_thumbnail_id( $post_id ) ), 'Smoke restores the previous featured image state.' );
echo "Local Admin Consent featured image smoke passed.\n";
