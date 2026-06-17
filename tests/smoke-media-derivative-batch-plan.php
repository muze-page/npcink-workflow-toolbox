<?php
/**
 * Local WordPress smoke for the media derivative batch review-set plan.
 *
 * Run with WP-CLI:
 * wp eval-file tests/smoke-media-derivative-batch-plan.php
 *
 * @package Npcink_Toolbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "FAIL: Run this script through WP-CLI eval-file so WordPress is loaded.\n" );
	exit( 1 );
}

$toolbox_media_batch_smoke_root = dirname( __DIR__ );
require_once $toolbox_media_batch_smoke_root . '/modules/local-automation-runtime/src/Contract/Media_Conversion_Review_Set_Normalizer.php';
require_once $toolbox_media_batch_smoke_root . '/modules/local-automation-runtime/src/Contract/Media_Conversion_Review_Set_Validator.php';

$toolbox_media_batch_smoke_attachment_ids = array();
$toolbox_media_batch_smoke_paths          = array();

function toolbox_media_batch_smoke_pass( string $message ): void {
	echo "PASS: {$message}\n";
}

function toolbox_media_batch_smoke_fail( string $message ): void {
	fwrite( STDERR, "FAIL: {$message}\n" );
	toolbox_media_batch_smoke_cleanup();
	exit( 1 );
}

function toolbox_media_batch_smoke_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		toolbox_media_batch_smoke_fail( $message );
	}

	toolbox_media_batch_smoke_pass( $message );
}

function toolbox_media_batch_smoke_admin_user_id(): int {
	$admins = get_users(
		array(
			'role'   => 'administrator',
			'number' => 1,
			'fields' => 'ids',
		)
	);

	return absint( $admins[0] ?? 0 );
}

function toolbox_media_batch_smoke_call_ability( array $definitions, string $ability_id, array $input = array() ) {
	$definition = is_array( $definitions[ $ability_id ] ?? null ) ? $definitions[ $ability_id ] : array();
	$callback   = $definition['execute_callback'] ?? null;
	if ( ! is_callable( $callback ) ) {
		toolbox_media_batch_smoke_fail( "{$ability_id} does not expose a callable execute callback." );
	}

	return call_user_func( $callback, $input );
}

function toolbox_media_batch_smoke_create_attachment( string $format ): int {
	global $toolbox_media_batch_smoke_attachment_ids, $toolbox_media_batch_smoke_paths;

	toolbox_media_batch_smoke_assert( function_exists( 'imagecreatetruecolor' ), 'GD image functions are available for the smoke fixture.' );

	$upload = wp_upload_dir();
	$dir    = trailingslashit( (string) ( $upload['path'] ?? '' ) );
	$url    = trailingslashit( (string) ( $upload['url'] ?? '' ) );
	toolbox_media_batch_smoke_assert( '' !== $dir && wp_mkdir_p( $dir ), 'Upload directory is writable.' );

	$format   = 'png' === sanitize_key( $format ) ? 'png' : 'jpg';
	$filename = sanitize_file_name( 'toolbox-media-batch-plan-' . $format . '-' . gmdate( 'YmdHis' ) . '-' . substr( md5( (string) microtime( true ) ), 0, 8 ) . '.' . $format );
	$path     = $dir . $filename;
	$image    = imagecreatetruecolor( 960, 540 );
	toolbox_media_batch_smoke_assert( false !== $image, 'Smoke fixture image canvas is created.' );

	$bg = imagecolorallocate( $image, 'png' === $format ? 42 : 20, 'png' === $format ? 112 : 96, 'png' === $format ? 72 : 148 );
	$fg = imagecolorallocate( $image, 243, 248, 255 );
	imagefilledrectangle( $image, 0, 0, 960, 540, $bg );
	imagestring( $image, 5, 60, 100, 'Toolbox media batch plan smoke', $fg );

	$written = 'png' === $format ? imagepng( $image, $path ) : imagejpeg( $image, $path, 88 );
	toolbox_media_batch_smoke_assert( true === $written && is_readable( $path ), 'Smoke fixture image is written.' );
	$toolbox_media_batch_smoke_paths[] = $path;

	if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}

	$attachment_id = wp_insert_attachment(
		array(
			'post_mime_type' => 'png' === $format ? 'image/png' : 'image/jpeg',
			'post_title'     => 'Toolbox media batch plan smoke ' . strtoupper( $format ),
			'post_status'    => 'inherit',
			'guid'           => $url . $filename,
		),
		$path
	);
	toolbox_media_batch_smoke_assert( ! is_wp_error( $attachment_id ) && $attachment_id > 0, 'Smoke attachment is inserted.' );

	$metadata = wp_generate_attachment_metadata( (int) $attachment_id, $path );
	if ( is_array( $metadata ) ) {
		wp_update_attachment_metadata( (int) $attachment_id, $metadata );
	}

	$toolbox_media_batch_smoke_attachment_ids[] = (int) $attachment_id;
	return (int) $attachment_id;
}

function toolbox_media_batch_smoke_cleanup(): void {
	global $toolbox_media_batch_smoke_attachment_ids, $toolbox_media_batch_smoke_paths;

	foreach ( array_reverse( array_unique( array_filter( $toolbox_media_batch_smoke_attachment_ids ) ) ) as $attachment_id ) {
		wp_delete_attachment( absint( $attachment_id ), true );
	}
	$toolbox_media_batch_smoke_attachment_ids = array();

	foreach ( array_unique( array_filter( $toolbox_media_batch_smoke_paths ) ) as $path ) {
		if ( is_string( $path ) && is_file( $path ) ) {
			@unlink( $path );
		}
	}
	$toolbox_media_batch_smoke_paths = array();
}

toolbox_media_batch_smoke_assert( class_exists( 'WP_REST_Request' ) && function_exists( 'rest_do_request' ), 'WordPress REST dispatch is available.' );
toolbox_media_batch_smoke_assert( toolbox_media_batch_smoke_admin_user_id() > 0, 'A local administrator is available.' );
toolbox_media_batch_smoke_assert( function_exists( 'npcink_abilities_toolkit_get_registered' ), 'Npcink Abilities Toolkit registry is available.' );

wp_set_current_user( toolbox_media_batch_smoke_admin_user_id() );

$jpeg_id = toolbox_media_batch_smoke_create_attachment( 'jpg' );
$png_id  = toolbox_media_batch_smoke_create_attachment( 'png' );

$before_jpeg_file = (string) get_post_meta( $jpeg_id, '_wp_attached_file', true );
$before_png_file  = (string) get_post_meta( $png_id, '_wp_attached_file', true );

$definitions = npcink_abilities_toolkit_get_registered();
$definitions = is_array( $definitions ) ? $definitions : array();
$plan        = toolbox_media_batch_smoke_call_ability(
	$definitions,
	'npcink-abilities-toolkit/build-media-derivative-batch-plan',
	array(
		'attachment_ids' => array( $jpeg_id, $png_id ),
		'target_format'  => 'png',
		'max_items'      => 10,
		'quality'        => 82,
	)
);

if ( is_wp_error( $plan ) ) {
	toolbox_media_batch_smoke_fail( 'Batch plan returned WP_Error: ' . $plan->get_error_code() );
}

$data = is_array( $plan['data'] ?? null ) ? $plan['data'] : array();
toolbox_media_batch_smoke_assert( true === (bool) ( $plan['success'] ?? false ), 'Batch plan returns a success envelope.' );
toolbox_media_batch_smoke_assert( true === (bool) ( $data['readonly'] ?? false ), 'Batch plan is read-only.' );
toolbox_media_batch_smoke_assert( false === (bool) ( $data['commit_execution'] ?? true ), 'Batch plan does not execute commits.' );
toolbox_media_batch_smoke_assert( 1 === (int) ( $data['eligibility_summary']['eligible_count'] ?? 0 ), 'Batch plan reports one eligible candidate.' );
toolbox_media_batch_smoke_assert( 1 === (int) ( $data['eligibility_summary']['blocked_count'] ?? 0 ), 'Batch plan reports one blocked item.' );
toolbox_media_batch_smoke_assert( $jpeg_id === (int) ( $data['candidates'][0]['attachment_id'] ?? 0 ), 'JPEG fixture is the eligible conversion candidate.' );
toolbox_media_batch_smoke_assert( 'eligible' === (string) ( $data['candidates'][0]['status'] ?? '' ), 'Candidate carries eligible status.' );
toolbox_media_batch_smoke_assert( 'attachment:' . $jpeg_id === (string) ( $data['candidates'][0]['result_ref'] ?? '' ), 'Candidate carries stable result reference.' );
toolbox_media_batch_smoke_assert( 'png' === (string) ( $data['candidates'][0]['cloud_request_input']['preferred_format'] ?? '' ), 'Candidate carries PNG cloud request format.' );
toolbox_media_batch_smoke_assert( $png_id === (int) ( $data['blocked_items'][0]['attachment_id'] ?? 0 ), 'PNG fixture is blocked as already target format.' );
toolbox_media_batch_smoke_assert( 'already_target_format' === (string) ( $data['blocked_items'][0]['blocked_reason'] ?? '' ), 'Blocked item records already-target-format reason.' );
toolbox_media_batch_smoke_assert( true === (bool) ( $data['retryable'] ?? false ), 'Batch plan is marked retryable as a rebuildable review set.' );
toolbox_media_batch_smoke_assert( is_string( $data['operator_next_action'] ?? null ) && '' !== $data['operator_next_action'], 'Batch plan includes operator next action.' );
toolbox_media_batch_smoke_assert( ! isset( $data['write_actions'], $data['wordpress_write_decision'], $data['approval_decision'], $data['commit'] ), 'Batch plan omits write and approval execution fields.' );

$review_set = ( new \Npcink\LocalAutomationRuntime\Contract\Media_Conversion_Review_Set_Normalizer() )->from_media_derivative_batch_plan( $plan );
$validation = ( new \Npcink\LocalAutomationRuntime\Contract\Media_Conversion_Review_Set_Validator() )->validate( $review_set );
toolbox_media_batch_smoke_assert( true === (bool) ( $validation['valid'] ?? false ), 'Batch plan normalizes to the local automation media conversion review-set contract.' );
toolbox_media_batch_smoke_assert( 'npcink_local_automation_media_conversion_review_set.v1' === (string) ( $review_set['contract_version'] ?? '' ), 'Normalized review set declares the local automation media conversion contract.' );
toolbox_media_batch_smoke_assert( 1 === (int) ( $review_set['eligibility_summary']['selected_count'] ?? 0 ), 'Normalized review set preserves selected candidate count.' );
toolbox_media_batch_smoke_assert( 1 === (int) ( $review_set['eligibility_summary']['blocked_count'] ?? 0 ), 'Normalized review set preserves blocked item count.' );
toolbox_media_batch_smoke_assert( 'npcink-abilities-toolkit/build-media-derivative-cloud-request' === (string) ( $review_set['selected_items'][0]['target_ability_id'] ?? '' ), 'Normalized review set preserves the selected media derivative target ability.' );
toolbox_media_batch_smoke_assert( true === (bool) ( $review_set['retryable'] ?? false ) && true === (bool) ( $review_set['retry_guidance']['retryable'] ?? false ), 'Normalized review set preserves rebuildable retry guidance without owning execution retries.' );
toolbox_media_batch_smoke_assert( false === (bool) ( $review_set['safety']['local_queue_created'] ?? true ), 'Normalized review set does not create a local queue.' );
toolbox_media_batch_smoke_assert( false === (bool) ( $review_set['safety']['direct_wordpress_write'] ?? true ), 'Normalized review set does not authorize direct WordPress writes.' );

toolbox_media_batch_smoke_assert( $before_jpeg_file === (string) get_post_meta( $jpeg_id, '_wp_attached_file', true ), 'Eligible fixture attached file is unchanged by planning.' );
toolbox_media_batch_smoke_assert( $before_png_file === (string) get_post_meta( $png_id, '_wp_attached_file', true ), 'Blocked fixture attached file is unchanged by planning.' );

toolbox_media_batch_smoke_cleanup();
echo "Media derivative batch plan smoke passed.\n";
