<?php
/**
 * Local WordPress smoke for selected media derivative batch previews to Core proposals.
 *
 * Run with WP-CLI:
 * wp eval-file tests/smoke-media-derivative-batch-core-proposals.php
 *
 * @package Npcink_Toolbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "FAIL: Run this script through WP-CLI eval-file so WordPress is loaded.\n" );
	exit( 1 );
}

$toolbox_media_batch_core_smoke_attachment_ids = array();
$toolbox_media_batch_core_smoke_paths          = array();
$toolbox_media_batch_core_smoke_proposal_ids   = array();

function toolbox_media_batch_core_smoke_pass( string $message ): void {
	echo "PASS: {$message}\n";
}

function toolbox_media_batch_core_smoke_info( string $message ): void {
	echo "INFO: {$message}\n";
}

function toolbox_media_batch_core_smoke_fail( string $message ): void {
	fwrite( STDERR, "FAIL: {$message}\n" );
	toolbox_media_batch_core_smoke_cleanup();
	exit( 1 );
}

function toolbox_media_batch_core_smoke_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		toolbox_media_batch_core_smoke_fail( $message );
	}

	toolbox_media_batch_core_smoke_pass( $message );
}

function toolbox_media_batch_core_smoke_admin_user_id(): int {
	$admins = get_users(
		array(
			'role'   => 'administrator',
			'number' => 1,
			'fields' => 'ids',
		)
	);

	return absint( $admins[0] ?? 0 );
}

function toolbox_media_batch_core_smoke_track_proposals( $data ): void {
	global $toolbox_media_batch_core_smoke_proposal_ids;

	if ( ! is_array( $data ) ) {
		return;
	}

	foreach ( (array) ( $data['proposals'] ?? array() ) as $proposal ) {
		if ( ! is_array( $proposal ) ) {
			continue;
		}
		$proposal_id = sanitize_text_field( (string) ( $proposal['proposal_id'] ?? '' ) );
		if ( '' !== $proposal_id ) {
			$toolbox_media_batch_core_smoke_proposal_ids[ $proposal_id ] = true;
		}
	}

	$proposal_id = sanitize_text_field( (string) ( $data['proposal_id'] ?? '' ) );
	if ( '' !== $proposal_id ) {
		$toolbox_media_batch_core_smoke_proposal_ids[ $proposal_id ] = true;
	}
}

function toolbox_media_batch_core_smoke_should_purge(): bool {
	$value = getenv( 'NPCINK_TOOLBOX_MEDIA_DERIVATIVE_BATCH_CORE_SMOKE_PURGE' );
	if ( ! is_string( $value ) || '' === trim( $value ) ) {
		return true;
	}

	return in_array( strtolower( trim( $value ) ), array( '1', 'true', 'yes' ), true );
}

function toolbox_media_batch_core_smoke_rest_raw( string $method, string $route, array $params = array() ): array {
	wp_set_current_user( toolbox_media_batch_core_smoke_admin_user_id() );

	$request = new WP_REST_Request( $method, $route );
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}

	$response = rest_do_request( $request );
	$status   = (int) $response->get_status();
	$data     = $response->get_data();
	toolbox_media_batch_core_smoke_track_proposals( $data );

	return array(
		'status' => $status,
		'data'   => is_array( $data ) ? $data : array(),
	);
}

function toolbox_media_batch_core_smoke_rest( string $method, string $route, array $params = array() ): array {
	$response = toolbox_media_batch_core_smoke_rest_raw( $method, $route, $params );
	$status   = (int) ( $response['status'] ?? 0 );
	$data     = is_array( $response['data'] ?? null ) ? (array) $response['data'] : array();

	toolbox_media_batch_core_smoke_assert(
		$status >= 200 && $status < 300,
		$method . ' ' . $route . ' returned HTTP ' . $status
	);

	return $data;
}

function toolbox_media_batch_core_smoke_create_attachment( string $label ): int {
	global $toolbox_media_batch_core_smoke_attachment_ids, $toolbox_media_batch_core_smoke_paths;

	toolbox_media_batch_core_smoke_assert( function_exists( 'imagecreatetruecolor' ), 'GD image functions are available for the smoke fixture.' );

	$upload = wp_upload_dir();
	$dir    = trailingslashit( (string) ( $upload['path'] ?? '' ) );
	$url    = trailingslashit( (string) ( $upload['url'] ?? '' ) );
	toolbox_media_batch_core_smoke_assert( '' !== $dir && wp_mkdir_p( $dir ), 'Upload directory is writable.' );

	$filename = sanitize_file_name( 'toolbox-media-batch-core-' . sanitize_title( $label ) . '-' . gmdate( 'YmdHis' ) . '-' . substr( md5( (string) microtime( true ) ), 0, 8 ) . '.jpg' );
	$path     = $dir . $filename;
	$image    = imagecreatetruecolor( 960, 540 );
	toolbox_media_batch_core_smoke_assert( false !== $image, 'Smoke fixture image canvas is created.' );

	$bg = imagecolorallocate( $image, 20, 96, 148 );
	$fg = imagecolorallocate( $image, 243, 248, 255 );
	imagefilledrectangle( $image, 0, 0, 960, 540, $bg );
	imagestring( $image, 5, 60, 100, 'Toolbox media batch Core smoke ' . $label, $fg );

	$written = imagejpeg( $image, $path, 88 );
	toolbox_media_batch_core_smoke_assert( true === $written && is_readable( $path ), 'Smoke fixture image is written.' );
	$toolbox_media_batch_core_smoke_paths[] = $path;

	if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}

	$attachment_id = wp_insert_attachment(
		array(
			'post_mime_type' => 'image/jpeg',
			'post_title'     => 'Toolbox media batch Core smoke ' . $label,
			'post_status'    => 'inherit',
			'guid'           => $url . $filename,
		),
		$path
	);
	toolbox_media_batch_core_smoke_assert( ! is_wp_error( $attachment_id ) && $attachment_id > 0, 'Smoke attachment is inserted.' );

	$metadata = wp_generate_attachment_metadata( (int) $attachment_id, $path );
	if ( is_array( $metadata ) ) {
		wp_update_attachment_metadata( (int) $attachment_id, $metadata );
	}

	$toolbox_media_batch_core_smoke_attachment_ids[] = (int) $attachment_id;
	return (int) $attachment_id;
}

function toolbox_media_batch_core_smoke_cleanup(): void {
	global $wpdb, $toolbox_media_batch_core_smoke_attachment_ids, $toolbox_media_batch_core_smoke_paths, $toolbox_media_batch_core_smoke_proposal_ids;

	foreach ( array_reverse( array_unique( array_filter( $toolbox_media_batch_core_smoke_attachment_ids ) ) ) as $attachment_id ) {
		wp_delete_attachment( absint( $attachment_id ), true );
	}
	$toolbox_media_batch_core_smoke_attachment_ids = array();

	foreach ( array_unique( array_filter( $toolbox_media_batch_core_smoke_paths ) ) as $path ) {
		if ( is_string( $path ) && is_file( $path ) ) {
			@unlink( $path );
		}
	}
	$toolbox_media_batch_core_smoke_paths = array();

	if ( toolbox_media_batch_core_smoke_should_purge() ) {
		$proposal_ids = array_keys( is_array( $toolbox_media_batch_core_smoke_proposal_ids ) ? $toolbox_media_batch_core_smoke_proposal_ids : array() );
		if ( ! empty( $proposal_ids ) ) {
			$audit_table    = $wpdb->prefix . 'npcink_governance_core_audit_log';
			$proposal_table = $wpdb->prefix . 'npcink_governance_core_proposals';
			foreach ( $proposal_ids as $proposal_id ) {
				$proposal_id = sanitize_text_field( $proposal_id );
				$wpdb->delete( $audit_table, array( 'proposal_id' => $proposal_id ), array( '%s' ) );
				$wpdb->delete( $proposal_table, array( 'proposal_id' => $proposal_id ), array( '%s' ) );
			}
			toolbox_media_batch_core_smoke_info( 'Purged Core proposal fixtures: ' . count( $proposal_ids ) );
		}
	}
}

toolbox_media_batch_core_smoke_assert( class_exists( 'WP_REST_Request' ) && function_exists( 'rest_do_request' ), 'WordPress REST dispatch is available.' );
toolbox_media_batch_core_smoke_assert( toolbox_media_batch_core_smoke_admin_user_id() > 0, 'A local administrator is available.' );
toolbox_media_batch_core_smoke_assert( function_exists( 'npcink_abilities_toolkit_get_registered' ), 'Npcink Abilities Toolkit registry is available.' );

$attachment_ids = array(
	toolbox_media_batch_core_smoke_create_attachment( 'A' ),
	toolbox_media_batch_core_smoke_create_attachment( 'B' ),
);
$before_files = array();
foreach ( $attachment_ids as $attachment_id ) {
	$before_files[ $attachment_id ] = (string) get_post_meta( $attachment_id, '_wp_attached_file', true );
}

$plan_envelope = toolbox_media_batch_core_smoke_rest(
	'POST',
	'/npcink-openclaw-adapter/v1/run-read-ability',
	array(
		'ability_id' => 'npcink-abilities-toolkit/build-media-derivative-batch-plan',
		'input'      => array(
			'attachment_ids'    => $attachment_ids,
			'target_format'     => 'webp',
			'target_max_width'  => 320,
			'quality'           => 82,
			'max_items'         => 10,
		),
	)
);
$plan = is_array( $plan_envelope['result']['data'] ?? null ) ? (array) $plan_envelope['result']['data'] : ( is_array( $plan_envelope['data'] ?? null ) ? (array) $plan_envelope['data'] : array() );
toolbox_media_batch_core_smoke_assert( true === (bool) ( $plan['readonly'] ?? false ), 'Adapter returns a read-only batch plan.' );
toolbox_media_batch_core_smoke_assert( false === (bool) ( $plan['commit_execution'] ?? true ), 'Adapter batch plan does not execute commits.' );
toolbox_media_batch_core_smoke_assert( 2 === (int) ( $plan['eligibility_summary']['eligible_count'] ?? 0 ), 'Adapter batch plan reports two eligible candidates.' );

$candidates = is_array( $plan['candidates'] ?? null ) ? array_values( $plan['candidates'] ) : array();
toolbox_media_batch_core_smoke_assert( 2 === count( $candidates ), 'Batch plan returns two selected preview candidates.' );

$proposal_ids = array();
foreach ( $candidates as $index => $candidate ) {
	$candidate = is_array( $candidate ) ? $candidate : array();
	$input     = is_array( $candidate['cloud_request_input'] ?? null ) ? (array) $candidate['cloud_request_input'] : array();
	toolbox_media_batch_core_smoke_assert( ! empty( $input['attachment_id'] ), 'Candidate ' . ( $index + 1 ) . ' carries an attachment id.' );

	$derivative = array(
		'artifact_id'        => 'toolbox_media_projection_' . (int) $input['attachment_id'] . '_' . $index,
		'expires_at'         => gmdate( 'c', time() + 600 ),
		'mime_type'         => 'image/webp',
		'format'             => 'webp',
		'width'              => 320,
		'height'             => 180,
		'filesize_bytes'     => 16384,
		'suggested_filename' => 'toolbox-media-projection-' . (int) $input['attachment_id'] . '.webp',
	);
	$media_details_input = array(
		'title'       => 'Toolbox batch optimized smoke image ' . ( $index + 1 ),
		'alt'         => 'Toolbox batch optimized smoke image ' . ( $index + 1 ) . ' alt text.',
		'caption'     => 'Toolbox batch optimized smoke image ' . ( $index + 1 ) . ' caption.',
		'description' => 'Toolbox batch optimized smoke image ' . ( $index + 1 ) . ' description.',
		'source_type' => 'ai_generated',
	);
	$plan_input = array(
		'attachment_id'                 => (int) $input['attachment_id'],
		'media_details_input'           => $media_details_input,
		'derivative_artifact'           => $derivative,
		'file_name'                     => 'toolbox-media-projection-' . (int) $input['attachment_id'] . '.webp',
		'expected_current_mime_type'    => 'image/jpeg',
		'expected_derivative_mime_type' => 'image/webp',
	);
	$plan_envelope = toolbox_media_batch_core_smoke_rest(
		'POST',
		'/npcink-openclaw-adapter/v1/run-read-ability',
		array(
			'ability_id' => 'npcink-abilities-toolkit/build-media-optimization-plan',
			'input'      => $plan_input,
		)
	);
	$optimization_plan = is_array( $plan_envelope['result']['data'] ?? null ) ? (array) $plan_envelope['result']['data'] : array();
	toolbox_media_batch_core_smoke_assert( 'media_optimization_plan' === (string) ( $optimization_plan['artifact_type'] ?? '' ), 'Adapter returns the canonical media optimization plan for selected preview ' . ( $index + 1 ) . '.' );
	toolbox_media_batch_core_smoke_assert( false === (bool) ( $optimization_plan['commit_execution'] ?? true ), 'Media optimization plan remains non-commit for selected preview ' . ( $index + 1 ) . '.' );
	$from_plan_request = array(
		'plan_ability_id' => 'npcink-abilities-toolkit/build-media-optimization-plan',
		'plan'            => $optimization_plan,
		'plan_input'      => $plan_input,
		'caller'          => array(
			'surface' => 'toolbox_media_optimization_projection_smoke',
			'source'  => 'tests/smoke-media-derivative-batch-core-proposals.php',
		),
	);

	$proposal_bridge = toolbox_media_batch_core_smoke_rest( 'POST', '/npcink-openclaw-adapter/v1/proposals/from-plan', $from_plan_request );
	$proposal        = is_array( $proposal_bridge['proposals'][0] ?? null ) ? (array) $proposal_bridge['proposals'][0] : array();
	$proposal_id     = sanitize_text_field( (string) ( $proposal['proposal_id'] ?? '' ) );
	toolbox_media_batch_core_smoke_assert( '' !== $proposal_id, 'Adapter creates one Core proposal for selected preview ' . ( $index + 1 ) . '.' );
	$proposal_ids[] = $proposal_id;
}

toolbox_media_batch_core_smoke_assert( 2 === count( array_unique( $proposal_ids ) ), 'Batch smoke creates two selected Core review proposals.' );
foreach ( $before_files as $attachment_id => $before_file ) {
	toolbox_media_batch_core_smoke_assert( $before_file === (string) get_post_meta( (int) $attachment_id, '_wp_attached_file', true ), 'Attachment ' . (int) $attachment_id . ' file pointer is unchanged because proposals were not executed.' );
	toolbox_media_batch_core_smoke_assert( 'image/jpeg' === (string) get_post_mime_type( (int) $attachment_id ), 'Attachment ' . (int) $attachment_id . ' mime type remains JPEG because proposals were not executed.' );
}

toolbox_media_batch_core_smoke_cleanup();
echo "Media derivative batch Core proposal smoke passed.\n";
