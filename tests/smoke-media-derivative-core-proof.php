<?php
/**
 * Local WordPress smoke for the Toolbox media derivative Core proposal path.
 *
 * Run with WP-CLI:
 * wp eval-file tests/smoke-media-derivative-core-proof.php
 *
 * @package Npcink_Toolbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "FAIL: Run this script through WP-CLI eval-file so WordPress is loaded.\n" );
	exit( 1 );
}

$toolbox_media_derivative_smoke_proposal_ids  = array();
$toolbox_media_derivative_smoke_attachment_id = 0;
$toolbox_media_derivative_smoke_paths         = array();

function toolbox_media_derivative_smoke_pass( string $message ): void {
	echo "PASS: {$message}\n";
}

function toolbox_media_derivative_smoke_info( string $message ): void {
	echo "INFO: {$message}\n";
}

function toolbox_media_derivative_smoke_fail( string $message ): void {
	fwrite( STDERR, "FAIL: {$message}\n" );
	toolbox_media_derivative_smoke_cleanup();
	exit( 1 );
}

function toolbox_media_derivative_smoke_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		toolbox_media_derivative_smoke_fail( $message );
	}

	toolbox_media_derivative_smoke_pass( $message );
}

function toolbox_media_derivative_smoke_admin_user_id(): int {
	$admins = get_users(
		array(
			'role'   => 'administrator',
			'number' => 1,
			'fields' => 'ids',
		)
	);

	return absint( $admins[0] ?? 0 );
}

function toolbox_media_derivative_smoke_should_purge(): bool {
	$value = getenv( 'NPCINK_TOOLBOX_MEDIA_DERIVATIVE_SMOKE_PURGE' );
	if ( ! is_string( $value ) || '' === trim( $value ) ) {
		return true;
	}

	return in_array( strtolower( trim( $value ) ), array( '1', 'true', 'yes' ), true );
}

function toolbox_media_derivative_smoke_track_proposals( $data ): void {
	global $toolbox_media_derivative_smoke_proposal_ids;

	if ( ! is_array( $data ) ) {
		return;
	}

	foreach ( (array) ( $data['proposals'] ?? array() ) as $proposal ) {
		if ( ! is_array( $proposal ) ) {
			continue;
		}

		$proposal_id = sanitize_text_field( (string) ( $proposal['proposal_id'] ?? '' ) );
		if ( '' !== $proposal_id ) {
			$toolbox_media_derivative_smoke_proposal_ids[ $proposal_id ] = true;
		}
	}

	$proposal_id = sanitize_text_field( (string) ( $data['proposal_id'] ?? '' ) );
	if ( '' !== $proposal_id ) {
		$toolbox_media_derivative_smoke_proposal_ids[ $proposal_id ] = true;
	}
}

function toolbox_media_derivative_smoke_rest( string $method, string $route, array $params = array() ): array {
	wp_set_current_user( toolbox_media_derivative_smoke_admin_user_id() );

	$request = new WP_REST_Request( $method, $route );
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}

	$response = rest_do_request( $request );
	$status   = (int) $response->get_status();
	$data     = $response->get_data();
	toolbox_media_derivative_smoke_track_proposals( $data );

	toolbox_media_derivative_smoke_assert(
		$status >= 200 && $status < 300,
		$method . ' ' . $route . ' returned HTTP ' . $status
	);

	return is_array( $data ) ? $data : array();
}

function toolbox_media_derivative_smoke_create_attachment(): int {
	global $toolbox_media_derivative_smoke_attachment_id, $toolbox_media_derivative_smoke_paths;

	toolbox_media_derivative_smoke_assert( function_exists( 'imagecreatetruecolor' ), 'GD image functions are available for the smoke fixture.' );

	$upload = wp_upload_dir();
	$dir    = trailingslashit( (string) ( $upload['path'] ?? '' ) );
	$url    = trailingslashit( (string) ( $upload['url'] ?? '' ) );
	toolbox_media_derivative_smoke_assert( '' !== $dir && wp_mkdir_p( $dir ), 'Upload directory is writable.' );

	$stamp    = gmdate( 'YmdHis' );
	$filename = sanitize_file_name( 'toolbox-media-derivative-core-proof-' . $stamp . '-' . substr( md5( (string) microtime( true ) ), 0, 8 ) . '.png' );
	$path     = $dir . $filename;

	$image = imagecreatetruecolor( 1280, 720 );
	toolbox_media_derivative_smoke_assert( false !== $image, 'Smoke fixture image canvas is created.' );
	$bg = imagecolorallocate( $image, 22, 96, 148 );
	$fg = imagecolorallocate( $image, 243, 248, 255 );
	imagefilledrectangle( $image, 0, 0, 1280, 720, $bg );
	imagestring( $image, 5, 80, 120, 'Toolbox media derivative smoke', $fg );
	$written = imagepng( $image, $path );
	toolbox_media_derivative_smoke_assert( true === $written && is_readable( $path ), 'Smoke fixture image is written.' );
	$toolbox_media_derivative_smoke_paths[] = $path;

	if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}

	$attachment_id = wp_insert_attachment(
		array(
			'post_mime_type' => 'image/png',
			'post_title'     => 'Toolbox media derivative smoke',
			'post_status'    => 'inherit',
			'guid'           => $url . $filename,
		),
		$path
	);
	toolbox_media_derivative_smoke_assert( ! is_wp_error( $attachment_id ) && $attachment_id > 0, 'Smoke attachment is inserted.' );

	$metadata = wp_generate_attachment_metadata( $attachment_id, $path );
	if ( is_array( $metadata ) ) {
		wp_update_attachment_metadata( $attachment_id, $metadata );
	}

	$toolbox_media_derivative_smoke_attachment_id = (int) $attachment_id;
	return (int) $attachment_id;
}

function toolbox_media_derivative_smoke_derivative_from_result( array $result ): array {
	$cloud_result = is_array( $result['cloud_result'] ?? null ) ? $result['cloud_result'] : $result;
	foreach ( array( 'derivative_artifact', 'artifact', 'derivative' ) as $key ) {
		if ( is_array( $cloud_result[ $key ] ?? null ) ) {
			return (array) $cloud_result[ $key ];
		}
	}

	return array();
}

function toolbox_media_derivative_smoke_latest_replacement_id( int $attachment_id ): string {
	$history = get_post_meta( $attachment_id, '_npcink_ai_media_file_replacement_history', true );
	$history = is_array( $history ) ? array_values( array_filter( $history, 'is_array' ) ) : array();
	$latest  = end( $history );

	return is_array( $latest ) ? sanitize_text_field( (string) ( $latest['replacement_id'] ?? '' ) ) : '';
}

function toolbox_media_derivative_smoke_cleanup(): void {
	global $wpdb, $toolbox_media_derivative_smoke_attachment_id, $toolbox_media_derivative_smoke_paths, $toolbox_media_derivative_smoke_proposal_ids;

	if ( $toolbox_media_derivative_smoke_attachment_id > 0 ) {
		wp_delete_attachment( $toolbox_media_derivative_smoke_attachment_id, true );
		$toolbox_media_derivative_smoke_attachment_id = 0;
	}

	foreach ( array_unique( array_filter( $toolbox_media_derivative_smoke_paths ) ) as $path ) {
		if ( is_string( $path ) && is_file( $path ) ) {
			@unlink( $path );
		}
	}

	$upload  = wp_upload_dir();
	$basedir = trailingslashit( (string) ( $upload['basedir'] ?? '' ) );
	foreach ( (array) glob( $basedir . '20[0-9][0-9]/*/toolbox-media-derivative-core-proof-*' ) as $path ) {
		if ( is_file( $path ) ) {
			@unlink( $path );
		}
	}
	foreach ( (array) glob( $basedir . 'npcink-abilities-toolkit-backups/20[0-9][0-9]/*/toolbox-media-derivative-core-proof-*' ) as $path ) {
		if ( is_file( $path ) ) {
			@unlink( $path );
		}
	}

	if ( toolbox_media_derivative_smoke_should_purge() ) {
		$proposal_ids = array_keys( is_array( $toolbox_media_derivative_smoke_proposal_ids ) ? $toolbox_media_derivative_smoke_proposal_ids : array() );
		if ( ! empty( $proposal_ids ) ) {
			$audit_table    = $wpdb->prefix . 'npcink_governance_core_audit_log';
			$proposal_table = $wpdb->prefix . 'npcink_governance_core_proposals';
			foreach ( $proposal_ids as $proposal_id ) {
				$proposal_id = sanitize_text_field( $proposal_id );
				$wpdb->delete( $audit_table, array( 'proposal_id' => $proposal_id ), array( '%s' ) );
				$wpdb->delete( $proposal_table, array( 'proposal_id' => $proposal_id ), array( '%s' ) );
			}
			toolbox_media_derivative_smoke_info( 'Purged Core proposal fixtures: ' . count( $proposal_ids ) );
		}
	}
}

toolbox_media_derivative_smoke_assert( class_exists( 'WP_REST_Request' ) && function_exists( 'rest_do_request' ), 'WordPress REST dispatch is available.' );
toolbox_media_derivative_smoke_assert( toolbox_media_derivative_smoke_admin_user_id() > 0, 'A local administrator is available.' );
toolbox_media_derivative_smoke_assert( class_exists( '\Npcink_Toolbox\Provider_Client' ) && class_exists( '\Npcink_Toolbox\Settings' ), 'Toolbox provider client classes are loaded.' );

$attachment_id = toolbox_media_derivative_smoke_create_attachment();
$before_url    = wp_get_attachment_url( $attachment_id );
$before_file   = (string) get_post_meta( $attachment_id, '_wp_attached_file', true );
toolbox_media_derivative_smoke_assert( '' !== (string) $before_url && '' !== $before_file, 'Smoke attachment has a public URL and attached file.' );

$handoff = toolbox_media_derivative_smoke_rest(
	'POST',
	'/npcink-toolbox/v1/media-derivative-handoff',
	array(
		'attachment_id'   => $attachment_id,
		'target_format'   => 'webp',
		'max_width'       => 320,
		'quality'         => 82,
		'watermark_mode'  => 'off',
	)
);
toolbox_media_derivative_smoke_assert( 'media_derivative_handoff' === (string) ( $handoff['artifact_type'] ?? '' ), 'Toolbox returns a media derivative handoff artifact.' );
toolbox_media_derivative_smoke_assert( false === (bool) ( $handoff['direct_wordpress_write'] ?? true ), 'Toolbox handoff does not write WordPress.' );
toolbox_media_derivative_smoke_assert( 'core_proposal_required' === (string) ( $handoff['handoff']['final_write_path'] ?? '' ), 'Toolbox handoff points final writes to Core proposal review.' );

$ability_input = is_array( $handoff['ability_input'] ?? null ) ? (array) $handoff['ability_input'] : array();
toolbox_media_derivative_smoke_assert( (int) ( $ability_input['attachment_id'] ?? 0 ) === $attachment_id, 'Toolbox handoff carries the selected attachment id.' );
toolbox_media_derivative_smoke_assert( 'webp' === (string) ( $ability_input['preferred_format'] ?? '' ), 'Toolbox handoff maps format override to preferred_format.' );
toolbox_media_derivative_smoke_assert( 320 === (int) ( $ability_input['target_max_width'] ?? 0 ), 'Toolbox handoff maps width override to target_max_width.' );
toolbox_media_derivative_smoke_assert( ! isset( $ability_input['watermark'] ), 'Toolbox handoff can omit watermark for one disabled-watermark run.' );

$create = toolbox_media_derivative_smoke_rest(
	'POST',
	'/npcink-openclaw-adapter/v1/media-derivative-runs',
	array(
		'input'           => $ability_input,
		'idempotency_key' => 'toolbox-media-derivative-core-proof-' . $attachment_id . '-' . time(),
	)
);
$run_id = sanitize_text_field( (string) ( $create['run_id'] ?? $create['cloud_run']['run_id'] ?? '' ) );
toolbox_media_derivative_smoke_assert( '' !== $run_id, 'Adapter returns a Cloud media derivative run id.' );

$result = array();
for ( $attempt = 0; $attempt < 40; $attempt++ ) {
	usleep( 0 === $attempt ? 250000 : 750000 );
	$result = toolbox_media_derivative_smoke_rest( 'GET', '/npcink-openclaw-adapter/v1/media-derivative-runs/' . rawurlencode( $run_id ) . '/result' );
	$status = (string) ( $result['cloud_result']['status'] ?? $result['status'] ?? '' );
	if ( in_array( $status, array( 'succeeded', 'completed' ), true ) ) {
		break;
	}
}

$derivative = toolbox_media_derivative_smoke_derivative_from_result( $result );
toolbox_media_derivative_smoke_assert( '' !== (string) ( $derivative['artifact_id'] ?? '' ), 'Cloud result includes a derivative artifact id.' );
toolbox_media_derivative_smoke_assert( 'image/webp' === (string) ( $derivative['mime_type'] ?? '' ), 'Cloud derivative is WebP.' );

$preflight = toolbox_media_derivative_smoke_rest(
	'POST',
	'/npcink-openclaw-adapter/v1/run-read-ability',
	array(
		'ability_id' => 'npcink-abilities-toolkit/build-media-adoption-preflight-summary',
		'input'      => array(
			'attachment_id'       => $attachment_id,
			'derivative_artifact' => $derivative,
		),
	)
);
$preflight_data = is_array( $preflight['result']['data'] ?? null ) ? (array) $preflight['result']['data'] : ( is_array( $preflight['data'] ?? null ) ? (array) $preflight['data'] : array() );
toolbox_media_derivative_smoke_assert( 'media_adoption_preflight_summary' === (string) ( $preflight_data['artifact_type'] ?? '' ), 'Adapter can run the media adoption preflight summary ability.' );
toolbox_media_derivative_smoke_assert( false === (bool) ( $preflight_data['direct_wordpress_write'] ?? true ), 'Media adoption preflight summary declares no direct WordPress write.' );
toolbox_media_derivative_smoke_assert( false === (bool) ( $preflight_data['proposal_created'] ?? true ), 'Media adoption preflight summary does not create a Core proposal.' );
toolbox_media_derivative_smoke_assert( true === (bool) ( $preflight_data['readiness']['can_submit_core_proposal'] ?? false ), 'Media adoption preflight summary marks the reviewed artifact as Core-proposal ready.' );

$media_details_input = array(
	'title'       => 'Toolbox optimized smoke image',
	'alt'         => 'Toolbox optimized smoke image alt text.',
	'caption'     => 'Toolbox optimized smoke image caption.',
	'description' => 'Toolbox optimized smoke image description.',
	'source_type' => 'ai_generated',
);
$proposal_payload = toolbox_media_derivative_smoke_rest(
	'POST',
	'/npcink-openclaw-adapter/v1/media-derivative-proposal-payload',
	array(
		'ability_response'     => is_array( $create['ability_response'] ?? null ) ? (array) $create['ability_response'] : array(),
		'cloud_result'         => is_array( $result['cloud_result'] ?? null ) ? (array) $result['cloud_result'] : $result,
		'derivative_artifact'  => $derivative,
		'media_details_input'  => $media_details_input,
	)
);
toolbox_media_derivative_smoke_assert( ! empty( $proposal_payload['proposal_ready'] ), 'Adapter builds a proposal-ready media optimization payload.' );
$from_plan_request = is_array( $proposal_payload['from_plan_request'] ?? null ) ? (array) $proposal_payload['from_plan_request'] : array();
toolbox_media_derivative_smoke_assert( 'npcink-abilities-toolkit/build-media-optimization-plan' === (string) ( $from_plan_request['plan_ability_id'] ?? '' ), 'Proposal payload targets the media optimization plan ability.' );

$proposal_bridge = toolbox_media_derivative_smoke_rest( 'POST', '/npcink-openclaw-adapter/v1/proposals/from-plan', $from_plan_request );
$proposal        = is_array( $proposal_bridge['proposals'][0] ?? null ) ? (array) $proposal_bridge['proposals'][0] : array();
$proposal_id     = sanitize_text_field( (string) ( $proposal['proposal_id'] ?? '' ) );
toolbox_media_derivative_smoke_assert( '' !== $proposal_id, 'Adapter creates one Core media optimization proposal.' );

$execute = toolbox_media_derivative_smoke_rest( 'POST', '/npcink-openclaw-adapter/v1/proposals/' . rawurlencode( $proposal_id ) . '/approve-and-execute' );
toolbox_media_derivative_smoke_assert( true === (bool) ( $execute['success'] ?? false ), 'Adapter approve-and-execute applies the Core media optimization proposal.' );

$after_url  = wp_get_attachment_url( $attachment_id );
$after_file = (string) get_post_meta( $attachment_id, '_wp_attached_file', true );
toolbox_media_derivative_smoke_assert( $after_url !== $before_url && $after_file !== $before_file, 'Attachment URL and file pointer change after proposal execution.' );
toolbox_media_derivative_smoke_assert( 'image/webp' === (string) get_post_mime_type( $attachment_id ), 'Attachment mime type changes to WebP.' );
toolbox_media_derivative_smoke_assert( $media_details_input['alt'] === get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ), 'Reviewed ALT text is applied by the Core-approved proposal.' );

$replacement_id = toolbox_media_derivative_smoke_latest_replacement_id( $attachment_id );
toolbox_media_derivative_smoke_assert( '' !== $replacement_id, 'Replacement history records a backup id for restore.' );

$restore_proposal = toolbox_media_derivative_smoke_rest(
	'POST',
	'/npcink-openclaw-adapter/v1/proposals',
	array(
		'ability_id' => 'npcink-abilities-toolkit/restore-media-backup',
		'title'      => 'Restore Toolbox media derivative smoke backup',
		'summary'    => 'Smoke restore of the original image after a Toolbox media derivative proposal.',
		'input'      => array(
			'attachment_id'                 => $attachment_id,
			'backup_id'                     => $replacement_id,
			'expected_current_relative_file' => $after_file,
			'expected_current_mime_type'    => 'image/webp',
			'target_conflict_mode'          => 'overwrite',
			'dry_run'                       => true,
			'commit'                        => false,
			'idempotency_key'               => 'toolbox-media-derivative-restore-' . $replacement_id,
		),
		'preview'    => array(
			'source'    => array( 'type' => 'toolbox_media_derivative_core_smoke_restore' ),
			'backup_id' => $replacement_id,
		),
	)
);
$restore_proposal_id = sanitize_text_field( (string) ( $restore_proposal['proposal_id'] ?? '' ) );
toolbox_media_derivative_smoke_assert( '' !== $restore_proposal_id, 'Adapter creates a Core restore proposal for cleanup.' );

$restore_execute = toolbox_media_derivative_smoke_rest( 'POST', '/npcink-openclaw-adapter/v1/proposals/' . rawurlencode( $restore_proposal_id ) . '/approve-and-execute' );
toolbox_media_derivative_smoke_assert( true === (bool) ( $restore_execute['success'] ?? false ), 'Adapter approve-and-execute restores the original media backup.' );
toolbox_media_derivative_smoke_assert( $before_file === (string) get_post_meta( $attachment_id, '_wp_attached_file', true ), 'Restore returns the attachment file pointer to the original file.' );
toolbox_media_derivative_smoke_assert( 'image/png' === (string) get_post_mime_type( $attachment_id ), 'Restore returns the attachment mime type to PNG.' );

toolbox_media_derivative_smoke_cleanup();
echo "Toolbox media derivative Core proposal smoke passed.\n";
