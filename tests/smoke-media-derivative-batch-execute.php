<?php
/**
 * Local WordPress smoke for selected media derivative batch execution.
 *
 * Run with WP-CLI:
 * wp eval-file tests/smoke-media-derivative-batch-execute.php
 *
 * @package Npcink_Toolbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "FAIL: Run this script through WP-CLI eval-file so WordPress is loaded.\n" );
	exit( 1 );
}

$toolbox_media_batch_execute_attachment_ids = array();
$toolbox_media_batch_execute_paths          = array();
$toolbox_media_batch_execute_proposal_ids   = array();

function toolbox_media_batch_execute_pass( string $message ): void {
	echo "PASS: {$message}\n";
}

function toolbox_media_batch_execute_info( string $message ): void {
	echo "INFO: {$message}\n";
}

function toolbox_media_batch_execute_fail( string $message ): void {
	fwrite( STDERR, "FAIL: {$message}\n" );
	toolbox_media_batch_execute_cleanup();
	exit( 1 );
}

function toolbox_media_batch_execute_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		toolbox_media_batch_execute_fail( $message );
	}

	toolbox_media_batch_execute_pass( $message );
}

function toolbox_media_batch_execute_admin_user_id(): int {
	$admins = get_users(
		array(
			'role'   => 'administrator',
			'number' => 1,
			'fields' => 'ids',
		)
	);

	return absint( $admins[0] ?? 0 );
}

function toolbox_media_batch_execute_should_purge(): bool {
	$value = getenv( 'NPCINK_TOOLBOX_MEDIA_DERIVATIVE_BATCH_EXECUTE_SMOKE_PURGE' );
	if ( ! is_string( $value ) || '' === trim( $value ) ) {
		return true;
	}

	return in_array( strtolower( trim( $value ) ), array( '1', 'true', 'yes' ), true );
}

function toolbox_media_batch_execute_track_proposals( $data ): void {
	global $toolbox_media_batch_execute_proposal_ids;

	if ( ! is_array( $data ) ) {
		return;
	}

	foreach ( (array) ( $data['proposals'] ?? array() ) as $proposal ) {
		if ( ! is_array( $proposal ) ) {
			continue;
		}
		$proposal_id = sanitize_text_field( (string) ( $proposal['proposal_id'] ?? '' ) );
		if ( '' !== $proposal_id ) {
			$toolbox_media_batch_execute_proposal_ids[ $proposal_id ] = true;
		}
	}

	$proposal_id = sanitize_text_field( (string) ( $data['proposal_id'] ?? '' ) );
	if ( '' !== $proposal_id ) {
		$toolbox_media_batch_execute_proposal_ids[ $proposal_id ] = true;
	}
}

function toolbox_media_batch_execute_rest_raw( string $method, string $route, array $params = array() ): array {
	wp_set_current_user( toolbox_media_batch_execute_admin_user_id() );

	$request = new WP_REST_Request( $method, $route );
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}

	$response = rest_do_request( $request );
	$status   = (int) $response->get_status();
	$data     = $response->get_data();
	toolbox_media_batch_execute_track_proposals( $data );

	return array(
		'status' => $status,
		'data'   => is_array( $data ) ? $data : array(),
	);
}

function toolbox_media_batch_execute_rest( string $method, string $route, array $params = array() ): array {
	$response = toolbox_media_batch_execute_rest_raw( $method, $route, $params );
	$status   = (int) ( $response['status'] ?? 0 );
	$data     = is_array( $response['data'] ?? null ) ? (array) $response['data'] : array();

	toolbox_media_batch_execute_assert(
		$status >= 200 && $status < 300,
		$method . ' ' . $route . ' returned HTTP ' . $status
	);

	return $data;
}

function toolbox_media_batch_execute_create_attachment( string $label ): int {
	global $toolbox_media_batch_execute_attachment_ids, $toolbox_media_batch_execute_paths;

	toolbox_media_batch_execute_assert( function_exists( 'imagecreatetruecolor' ), 'GD image functions are available for the smoke fixture.' );

	$upload = wp_upload_dir();
	$dir    = trailingslashit( (string) ( $upload['path'] ?? '' ) );
	$url    = trailingslashit( (string) ( $upload['url'] ?? '' ) );
	toolbox_media_batch_execute_assert( '' !== $dir && wp_mkdir_p( $dir ), 'Upload directory is writable.' );

	$filename = sanitize_file_name( 'toolbox-media-batch-execute-' . sanitize_title( $label ) . '-' . gmdate( 'YmdHis' ) . '-' . substr( md5( (string) microtime( true ) ), 0, 8 ) . '.jpg' );
	$path     = $dir . $filename;
	$image    = imagecreatetruecolor( 960, 540 );
	toolbox_media_batch_execute_assert( false !== $image, 'Smoke fixture image canvas is created.' );

	$bg = imagecolorallocate( $image, 20, 96, 148 );
	$fg = imagecolorallocate( $image, 243, 248, 255 );
	imagefilledrectangle( $image, 0, 0, 960, 540, $bg );
	imagestring( $image, 5, 60, 100, 'Toolbox media batch execute smoke ' . $label, $fg );

	$written = imagejpeg( $image, $path, 88 );
	toolbox_media_batch_execute_assert( true === $written && is_readable( $path ), 'Smoke fixture image is written.' );
	$toolbox_media_batch_execute_paths[] = $path;

	if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}

	$attachment_id = wp_insert_attachment(
		array(
			'post_mime_type' => 'image/jpeg',
			'post_title'     => 'Toolbox media batch execute smoke ' . $label,
			'post_status'    => 'inherit',
			'guid'           => $url . $filename,
		),
		$path
	);
	toolbox_media_batch_execute_assert( ! is_wp_error( $attachment_id ) && $attachment_id > 0, 'Smoke attachment is inserted.' );

	$metadata = wp_generate_attachment_metadata( (int) $attachment_id, $path );
	if ( is_array( $metadata ) ) {
		wp_update_attachment_metadata( (int) $attachment_id, $metadata );
	}

	$toolbox_media_batch_execute_attachment_ids[] = (int) $attachment_id;
	return (int) $attachment_id;
}

function toolbox_media_batch_execute_derivative_from_result( array $result ): array {
	$cloud_result = is_array( $result['cloud_result'] ?? null ) ? $result['cloud_result'] : $result;
	return is_array( $cloud_result['artifact'] ?? null ) ? (array) $cloud_result['artifact'] : array();
}

function toolbox_media_batch_execute_wait_for_result( string $run_id ): array {
	for ( $attempt = 0; $attempt < 40; $attempt++ ) {
		usleep( 0 === $attempt ? 250000 : 750000 );
		$poll   = toolbox_media_batch_execute_rest_raw( 'GET', '/npcink-toolbox/v1/media-derivative-preview/' . rawurlencode( $run_id ) . '/result' );
		$result = is_array( $poll['data'] ?? null ) ? (array) $poll['data'] : array();
		$status = (string) ( $result['cloud_result']['status'] ?? $result['status'] ?? '' );
		if ( in_array( $status, array( 'succeeded', 'completed' ), true ) ) {
			return $result;
		}

		$http_status = (int) ( $poll['status'] ?? 0 );
		if ( 409 !== $http_status && ( $http_status < 200 || $http_status >= 300 ) ) {
			toolbox_media_batch_execute_fail( 'Cloud result polling returned HTTP ' . $http_status );
		}
	}

	toolbox_media_batch_execute_fail( 'Toolbox media derivative preview result did not become available.' );
}

function toolbox_media_batch_execute_latest_replacement_id( int $attachment_id ): string {
	$history = get_post_meta( $attachment_id, '_npcink_ai_media_file_replacement_history', true );
	$history = is_array( $history ) ? array_values( array_filter( $history, 'is_array' ) ) : array();
	$latest  = end( $history );

	return is_array( $latest ) ? sanitize_text_field( (string) ( $latest['replacement_id'] ?? '' ) ) : '';
}

function toolbox_media_batch_execute_response_value( array $payload, string $key ) {
	if ( array_key_exists( $key, $payload ) ) {
		return $payload[ $key ];
	}

	$execution = is_array( $payload['execution'] ?? null ) ? (array) $payload['execution'] : array();
	if ( array_key_exists( $key, $execution ) ) {
		return $execution[ $key ];
	}

	$record = is_array( $payload['execution_record'] ?? null ) ? (array) $payload['execution_record'] : array();
	if ( array_key_exists( $key, $record ) ) {
		return $record[ $key ];
	}

	return null;
}

function toolbox_media_batch_execute_cleanup(): void {
	global $wpdb, $toolbox_media_batch_execute_attachment_ids, $toolbox_media_batch_execute_paths, $toolbox_media_batch_execute_proposal_ids;

	foreach ( array_reverse( array_unique( array_filter( $toolbox_media_batch_execute_attachment_ids ) ) ) as $attachment_id ) {
		wp_delete_attachment( absint( $attachment_id ), true );
	}
	$toolbox_media_batch_execute_attachment_ids = array();

	foreach ( array_unique( array_filter( $toolbox_media_batch_execute_paths ) ) as $path ) {
		if ( is_string( $path ) && is_file( $path ) ) {
			@unlink( $path );
		}
	}
	$toolbox_media_batch_execute_paths = array();

	$upload  = wp_upload_dir();
	$basedir = trailingslashit( (string) ( $upload['basedir'] ?? '' ) );
	foreach ( (array) glob( $basedir . '20[0-9][0-9]/*/toolbox-media-batch-execute-*' ) as $path ) {
		if ( is_file( $path ) ) {
			@unlink( $path );
		}
	}
	foreach ( (array) glob( $basedir . 'npcink-abilities-toolkit-backups/20[0-9][0-9]/*/toolbox-media-batch-execute-*' ) as $path ) {
		if ( is_file( $path ) ) {
			@unlink( $path );
		}
	}

	if ( toolbox_media_batch_execute_should_purge() ) {
		$proposal_ids = array_keys( is_array( $toolbox_media_batch_execute_proposal_ids ) ? $toolbox_media_batch_execute_proposal_ids : array() );
		if ( ! empty( $proposal_ids ) ) {
			$audit_table    = $wpdb->prefix . 'npcink_governance_core_audit_log';
			$proposal_table = $wpdb->prefix . 'npcink_governance_core_proposals';
			foreach ( $proposal_ids as $proposal_id ) {
				$proposal_id = sanitize_text_field( $proposal_id );
				$wpdb->delete( $audit_table, array( 'proposal_id' => $proposal_id ), array( '%s' ) );
				$wpdb->delete( $proposal_table, array( 'proposal_id' => $proposal_id ), array( '%s' ) );
			}
			toolbox_media_batch_execute_info( 'Purged Core proposal fixtures: ' . count( $proposal_ids ) );
		}
	}
}

toolbox_media_batch_execute_assert( class_exists( 'WP_REST_Request' ) && function_exists( 'rest_do_request' ), 'WordPress REST dispatch is available.' );
toolbox_media_batch_execute_assert( toolbox_media_batch_execute_admin_user_id() > 0, 'A local administrator is available.' );
toolbox_media_batch_execute_assert( function_exists( 'npcink_abilities_toolkit_get_registered' ), 'Npcink Abilities Toolkit registry is available.' );

$attachment_ids = array(
	toolbox_media_batch_execute_create_attachment( 'A' ),
	toolbox_media_batch_execute_create_attachment( 'B' ),
);
$before_files   = array();
$before_urls    = array();
foreach ( $attachment_ids as $attachment_id ) {
	$before_files[ $attachment_id ] = (string) get_post_meta( $attachment_id, '_wp_attached_file', true );
	$before_urls[ $attachment_id ]  = (string) wp_get_attachment_url( $attachment_id );
	toolbox_media_batch_execute_assert( '' !== $before_files[ $attachment_id ] && '' !== $before_urls[ $attachment_id ], 'Attachment ' . (int) $attachment_id . ' has initial URL and file pointer.' );
}

$plan_envelope = toolbox_media_batch_execute_rest(
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
$plan          = is_array( $plan_envelope['result']['data'] ?? null ) ? (array) $plan_envelope['result']['data'] : ( is_array( $plan_envelope['data'] ?? null ) ? (array) $plan_envelope['data'] : array() );
$candidates    = is_array( $plan['candidates'] ?? null ) ? array_values( $plan['candidates'] ) : array();
$executed_rows = array();

toolbox_media_batch_execute_assert( true === (bool) ( $plan['readonly'] ?? false ), 'Adapter returns a read-only batch plan.' );
toolbox_media_batch_execute_assert( false === (bool) ( $plan['commit_execution'] ?? true ), 'Adapter batch plan does not execute commits.' );
toolbox_media_batch_execute_assert( 2 === count( $candidates ), 'Batch plan returns two selected preview candidates.' );

foreach ( $candidates as $index => $candidate ) {
	$candidate     = is_array( $candidate ) ? $candidate : array();
	$ability_input = is_array( $candidate['cloud_request_input'] ?? null ) ? (array) $candidate['cloud_request_input'] : array();
	$attachment_id = absint( $ability_input['attachment_id'] ?? 0 );
	toolbox_media_batch_execute_assert( in_array( $attachment_id, $attachment_ids, true ), 'Candidate ' . ( $index + 1 ) . ' maps to a selected attachment.' );

	$create = toolbox_media_batch_execute_rest(
		'POST',
		'/npcink-toolbox/v1/media-derivative-preview',
		array(
			'input'           => $ability_input,
			'idempotency_key' => 'toolbox-media-batch-execute-' . $attachment_id . '-' . time() . '-' . $index,
		)
	);
	$run_id = sanitize_text_field( (string) ( $create['run_id'] ?? $create['cloud_run']['run_id'] ?? '' ) );
	toolbox_media_batch_execute_assert( '' !== $run_id, 'Toolbox returns a Cloud run id for selected preview ' . ( $index + 1 ) . '.' );

	$result     = toolbox_media_batch_execute_wait_for_result( $run_id );
	$derivative = toolbox_media_batch_execute_derivative_from_result( $result );
	toolbox_media_batch_execute_assert( '' !== (string) ( $derivative['artifact_id'] ?? '' ), 'Selected preview ' . ( $index + 1 ) . ' returns derivative artifact evidence.' );
	toolbox_media_batch_execute_assert( 'image/webp' === (string) ( $derivative['mime_type'] ?? '' ), 'Selected preview ' . ( $index + 1 ) . ' derivative is WebP.' );

	$media_details_input = array(
		'title'       => 'Toolbox batch executed smoke image ' . ( $index + 1 ),
		'alt'         => 'Toolbox batch executed smoke image ' . ( $index + 1 ) . ' alt text.',
		'caption'     => 'Toolbox batch executed smoke image ' . ( $index + 1 ) . ' caption.',
		'description' => 'Toolbox batch executed smoke image ' . ( $index + 1 ) . ' description.',
		'source_type' => 'ai_generated',
	);
	$proposal_payload = toolbox_media_batch_execute_rest(
		'POST',
		'/npcink-toolbox/v1/media-derivative-optimization-payload',
		array(
			'ability_response'     => is_array( $create['ability_response'] ?? null ) ? (array) $create['ability_response'] : array(),
			'cloud_result'         => is_array( $result['cloud_result'] ?? null ) ? (array) $result['cloud_result'] : $result,
			'derivative_artifact'  => $derivative,
			'media_details_input'  => $media_details_input,
		)
	);
	$from_plan_request = is_array( $proposal_payload['from_plan_request'] ?? null ) ? (array) $proposal_payload['from_plan_request'] : array();
	toolbox_media_batch_execute_assert( ! empty( $proposal_payload['proposal_ready'] ), 'Toolbox projects a proposal-ready payload from Cloud Addon for selected preview ' . ( $index + 1 ) . '.' );

	$proposal_bridge = toolbox_media_batch_execute_rest( 'POST', '/npcink-openclaw-adapter/v1/proposals/from-plan', $from_plan_request );
	$proposal        = is_array( $proposal_bridge['proposals'][0] ?? null ) ? (array) $proposal_bridge['proposals'][0] : array();
	$proposal_id     = sanitize_text_field( (string) ( $proposal['proposal_id'] ?? '' ) );
	toolbox_media_batch_execute_assert( '' !== $proposal_id, 'Adapter creates one Core proposal for selected preview ' . ( $index + 1 ) . '.' );

	$execute = toolbox_media_batch_execute_rest( 'POST', '/npcink-openclaw-adapter/v1/proposals/' . rawurlencode( $proposal_id ) . '/approve-and-execute' );
	toolbox_media_batch_execute_assert( true === (bool) ( $execute['success'] ?? false ), 'Adapter approve-and-execute applies selected replacement ' . ( $index + 1 ) . '.' );
	$selected_action_count = (int) toolbox_media_batch_execute_response_value( $execute, 'selected_count' );
	$executed_action_count = (int) toolbox_media_batch_execute_response_value( $execute, 'executed_count' );
	toolbox_media_batch_execute_assert( $selected_action_count > 0, 'Execution response exposes selected_count for replacement ' . ( $index + 1 ) . '.' );
	toolbox_media_batch_execute_assert( $executed_action_count === $selected_action_count, 'Execution response exposes executed_count for all selected actions in replacement ' . ( $index + 1 ) . '.' );
	toolbox_media_batch_execute_assert( '' !== (string) toolbox_media_batch_execute_response_value( $execute, 'operator_next_action' ), 'Execution response exposes operator_next_action for replacement ' . ( $index + 1 ) . '.' );
	toolbox_media_batch_execute_assert( is_array( toolbox_media_batch_execute_response_value( $execute, 'core_preflight_evidence' ) ), 'Execution response exposes Core preflight evidence for replacement ' . ( $index + 1 ) . '.' );

	$after_file = (string) get_post_meta( $attachment_id, '_wp_attached_file', true );
	$after_url  = (string) wp_get_attachment_url( $attachment_id );
	toolbox_media_batch_execute_assert( $after_file !== $before_files[ $attachment_id ] && $after_url !== $before_urls[ $attachment_id ], 'Attachment ' . $attachment_id . ' URL and file pointer change after execution.' );
	toolbox_media_batch_execute_assert( 'image/webp' === (string) get_post_mime_type( $attachment_id ), 'Attachment ' . $attachment_id . ' mime type changes to WebP.' );
	toolbox_media_batch_execute_assert( $media_details_input['alt'] === get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ), 'Attachment ' . $attachment_id . ' reviewed ALT text is applied.' );

	$replacement_id = toolbox_media_batch_execute_latest_replacement_id( $attachment_id );
	toolbox_media_batch_execute_assert( '' !== $replacement_id, 'Attachment ' . $attachment_id . ' replacement history records a backup id.' );

	$executed_rows[] = array(
		'attachment_id'  => $attachment_id,
		'after_file'     => $after_file,
		'replacement_id' => $replacement_id,
	);
}

toolbox_media_batch_execute_assert( 2 === count( $executed_rows ), 'Batch smoke executes two selected replacement proposals.' );

foreach ( $executed_rows as $row ) {
	$attachment_id  = (int) $row['attachment_id'];
	$replacement_id = (string) $row['replacement_id'];
	$after_file     = (string) $row['after_file'];

	$restore_proposal = toolbox_media_batch_execute_rest(
		'POST',
		'/npcink-openclaw-adapter/v1/proposals',
		array(
			'ability_id' => 'npcink-abilities-toolkit/restore-media-backup',
			'title'      => 'Restore Toolbox media batch execute smoke backup',
			'summary'    => 'Smoke restore of the original image after a Toolbox batch media derivative proposal.',
			'input'      => array(
				'attachment_id'                  => $attachment_id,
				'backup_id'                      => $replacement_id,
				'expected_current_relative_file' => $after_file,
				'expected_current_mime_type'     => 'image/webp',
				'target_conflict_mode'           => 'overwrite',
				'dry_run'                        => true,
				'commit'                         => false,
				'idempotency_key'                => 'toolbox-media-batch-execute-restore-' . $replacement_id,
			),
			'preview'    => array(
				'source'    => array( 'type' => 'toolbox_media_batch_execute_smoke_restore' ),
				'backup_id' => $replacement_id,
			),
		)
	);
	$restore_proposal_id = sanitize_text_field( (string) ( $restore_proposal['proposal_id'] ?? '' ) );
	toolbox_media_batch_execute_assert( '' !== $restore_proposal_id, 'Adapter creates a Core restore proposal for attachment ' . $attachment_id . '.' );

	$restore_execute = toolbox_media_batch_execute_rest( 'POST', '/npcink-openclaw-adapter/v1/proposals/' . rawurlencode( $restore_proposal_id ) . '/approve-and-execute' );
	toolbox_media_batch_execute_assert( true === (bool) ( $restore_execute['success'] ?? false ), 'Adapter approve-and-execute restores attachment ' . $attachment_id . ' backup.' );
	toolbox_media_batch_execute_assert( $before_files[ $attachment_id ] === (string) get_post_meta( $attachment_id, '_wp_attached_file', true ), 'Restore returns attachment ' . $attachment_id . ' file pointer to the original file.' );
	toolbox_media_batch_execute_assert( 'image/jpeg' === (string) get_post_mime_type( $attachment_id ), 'Restore returns attachment ' . $attachment_id . ' mime type to JPEG.' );
}

toolbox_media_batch_execute_cleanup();
echo "Media derivative batch execution smoke passed.\n";
