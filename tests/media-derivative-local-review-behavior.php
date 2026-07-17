<?php
/**
 * Focused behavior checks for the media derivative local review boundary.
 *
 * @package Npcink_Toolbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/wp-stub/' );
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private string $code;

		public function __construct( string $code ) {
			$this->code = $code;
		}

		public function get_error_code(): string {
			return $this->code;
		}
	}
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
	class WP_REST_Request {
		/** @var array<string,mixed> */
		private array $path_params;

		/** @var array<string,mixed> */
		private array $json_params;

		/** @var array<string,mixed> */
		private array $query_params;

		/**
		 * @param array<string,mixed> $path_params Path parameters.
		 * @param array<string,mixed> $json_params JSON body parameters.
		 * @param array<string,mixed> $query_params Query parameters.
		 */
		public function __construct( array $path_params, array $json_params = array(), array $query_params = array() ) {
			$this->path_params  = $path_params;
			$this->json_params  = $json_params;
			$this->query_params = $query_params;
		}

		/** @return array<string,mixed> */
		public function get_params(): array {
			return array_merge( $this->path_params, $this->query_params, $this->json_params );
		}

		/** @return array<string,mixed> */
		public function get_json_params(): array {
			return $this->json_params;
		}

		/** @return array<string,mixed> */
		public function get_query_params(): array {
			return $this->query_params;
		}

		public function get_param( string $key ) {
			return $this->path_params[ $key ] ?? $this->json_params[ $key ] ?? $this->query_params[ $key ] ?? null;
		}
	}
}

function __( string $message ): string {
	return $message;
}

function sanitize_text_field( string $value ): string {
	return trim( $value );
}

function sanitize_file_name( string $value ): string {
	return preg_replace( '/[^A-Za-z0-9._-]/', '', $value ) ?? '';
}

function sanitize_key( string $value ): string {
	return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $value ) ) ?? '';
}

function absint( $value ): int {
	return abs( (int) $value );
}

function is_wp_error( $value ): bool {
	return $value instanceof WP_Error;
}

function rest_url( string $path ): string {
	return 'https://example.test/wp-json/' . ltrim( $path, '/' );
}

$npcink_toolbox_local_review_can_manage        = false;
$npcink_toolbox_local_review_receive_calls     = 0;
$npcink_toolbox_local_review_received_artifact = array();

function current_user_can( string $capability ): bool {
	global $npcink_toolbox_local_review_can_manage;
	return 'manage_options' === $capability && true === $npcink_toolbox_local_review_can_manage;
}

function npcink_cloud_addon_receive_media_derivative_artifact( array $artifact ) {
	global $npcink_toolbox_local_review_receive_calls, $npcink_toolbox_local_review_received_artifact;
	++$npcink_toolbox_local_review_receive_calls;
	$npcink_toolbox_local_review_received_artifact = $artifact;
	return new WP_Error( 'behavior_receive_sentinel' );
}

function npcink_toolbox_local_review_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
	echo "PASS: {$message}\n";
}

eval( 'namespace Npcink_Toolbox; class Plugin { public const REST_NAMESPACE = "npcink-toolbox/v1"; }' );
require_once dirname( __DIR__ ) . '/includes/Rest_Controller.php';

$controller  = ( new ReflectionClass( \Npcink_Toolbox\Rest_Controller::class ) )->newInstanceWithoutConstructor();
$artifact_id = 'art_' . str_repeat( 'a', 32 );
$base_artifact = array(
	'artifact_id'         => $artifact_id,
	'expires_at'          => gmdate( 'Y-m-d\TH:i:s\Z', time() + 900 ),
	'mime_type'           => 'image/webp',
	'format'              => 'webp',
	'width'               => 320,
	'height'              => 180,
	'filesize_bytes'      => 1024,
	'sha256'              => str_repeat( 'b', 64 ),
	'suggested_filename'  => 'optimized-bbbbbbbb.webp',
	'filename_basis'      => array(
		'owner'                          => 'wordpress_write_ability_final',
		'strategy'                       => 'format_checksum',
		'final_sanitize_unique_required' => true,
	),
	'processing_warnings' => array(),
);

$request_for = static function ( array $artifact, array $query = array(), ?string $path_artifact_id = null, ?array $json = null ) use ( $artifact_id ): WP_REST_Request {
	return new WP_REST_Request(
		array( 'artifact_id' => $path_artifact_id ?? $artifact_id ),
		$json ?? array( 'artifact' => $artifact ),
		$query
	);
};

npcink_toolbox_local_review_assert( false === $controller->permission_media_derivative_local_review(), 'Local review denies users without manage_options.' );
$npcink_toolbox_local_review_can_manage = true;
npcink_toolbox_local_review_assert( true === $controller->permission_media_derivative_local_review(), 'Local review accepts an administrator after WordPress REST cookie authentication.' );

$route_args_method = new ReflectionMethod( \Npcink_Toolbox\Rest_Controller::class, 'media_derivative_local_review_route_args' );
$route_args_method->setAccessible( true );
$route_args       = $route_args_method->invoke( $controller );
$artifact_schema  = is_array( $route_args['artifact'] ?? null ) ? $route_args['artifact'] : array();
$artifact_properties = is_array( $artifact_schema['properties'] ?? null ) ? $artifact_schema['properties'] : array();
npcink_toolbox_local_review_assert( true === ( $artifact_schema['required'] ?? null ), 'WordPress route marks the whole artifact argument required.' );
npcink_toolbox_local_review_assert( 'rest_validate_request_arg' === ( $artifact_schema['validate_callback'] ?? null ), 'WordPress Core validates the registered artifact object schema before the callback.' );
npcink_toolbox_local_review_assert( array_keys( $base_artifact ) === array_keys( $artifact_properties ), 'Route schema declares exactly the local11 artifact properties.' );
npcink_toolbox_local_review_assert( array() === array_filter( $artifact_properties, static fn( array $property ): bool => true !== ( $property['required'] ?? null ) ), 'Schema v3 marks every local11 property required.' );
npcink_toolbox_local_review_assert(
	array( 'owner', 'strategy', 'final_sanitize_unique_required' ) === ( $artifact_properties['filename_basis']['anyOf'][0]['required'] ?? null ),
	'Nested filename_basis keeps its exact required-property list.'
);

$assert_args_rejected = static function ( WP_REST_Request $request, string $message ) use ( $controller ): void {
	global $npcink_toolbox_local_review_receive_calls;
	$before = $npcink_toolbox_local_review_receive_calls;
	$result = $controller->serve_media_derivative_local_review( $request );
	npcink_toolbox_local_review_assert( $result instanceof WP_Error && 'npcink_toolbox_media_derivative_local_review_args_invalid' === $result->get_error_code(), $message );
	npcink_toolbox_local_review_assert( $before === $npcink_toolbox_local_review_receive_calls, $message . ' Rejection occurs before Cloud Addon.' );
};

$assert_args_rejected( $request_for( $base_artifact, array( '_wpnonce' => 'old-query-nonce' ) ), 'A legacy query nonce is rejected.' );
$assert_args_rejected( $request_for( $base_artifact, array( 'sha256' => str_repeat( 'b', 64 ) ) ), 'Every artifact query field is rejected.' );
$assert_args_rejected( $request_for( $base_artifact, array( 'processing_warnings' => '[]' ) ), 'Serialized warnings in the query are rejected.' );
$assert_args_rejected( $request_for( $base_artifact, array(), null, array() ), 'A missing whole artifact body is rejected.' );
$assert_args_rejected( $request_for( $base_artifact, array(), null, array( 'artifact' => $base_artifact, 'extra' => true ) ), 'An extra top-level JSON field is rejected.' );

$assert_descriptor_rejected = static function ( array $artifact, string $message, ?string $path_artifact_id = null ) use ( $controller, $request_for ): void {
	global $npcink_toolbox_local_review_receive_calls;
	$before = $npcink_toolbox_local_review_receive_calls;
	$result = $controller->serve_media_derivative_local_review( $request_for( $artifact, array(), $path_artifact_id ) );
	npcink_toolbox_local_review_assert( $result instanceof WP_Error && 'npcink_toolbox_media_derivative_local_review_descriptor_invalid' === $result->get_error_code(), $message );
	npcink_toolbox_local_review_assert( $before === $npcink_toolbox_local_review_receive_calls, $message . ' Rejection occurs before Cloud Addon.' );
};

$assert_descriptor_forwarded = static function ( array $artifact, string $message ) use ( $controller, $request_for ): void {
	global $npcink_toolbox_local_review_receive_calls;
	$before = $npcink_toolbox_local_review_receive_calls;
	$result = $controller->serve_media_derivative_local_review( $request_for( $artifact ) );
	npcink_toolbox_local_review_assert( $result instanceof WP_Error && 'behavior_receive_sentinel' === $result->get_error_code(), $message );
	npcink_toolbox_local_review_assert( $before + 1 === $npcink_toolbox_local_review_receive_calls, $message . ' Cloud Addon is called exactly once.' );
};

$artifact_with_checksum             = $base_artifact;
$artifact_with_checksum['checksum'] = 'sha256:' . str_repeat( 'b', 64 );
$assert_descriptor_rejected( $artifact_with_checksum, 'Extra checksum body field is rejected.' );
$artifact_with_reference                       = $base_artifact;
$artifact_with_reference['artifact_reference'] = array( 'artifact_id' => $artifact_id );
$assert_descriptor_rejected( $artifact_with_reference, 'Extra artifact_reference body field is rejected.' );
$assert_descriptor_rejected( $base_artifact, 'Path and body artifact ids must match.', 'art_' . str_repeat( 'c', 32 ) );

$valid_result = $controller->serve_media_derivative_local_review( $request_for( $base_artifact ) );
npcink_toolbox_local_review_assert( $valid_result instanceof WP_Error && 'behavior_receive_sentinel' === $valid_result->get_error_code(), 'Canonical Z UTC JSON artifact reaches the Addon receiver.' );
npcink_toolbox_local_review_assert( 1 === $npcink_toolbox_local_review_receive_calls, 'Addon receiver is called exactly once for the canonical Z descriptor.' );
npcink_toolbox_local_review_assert( array_keys( $base_artifact ) === array_keys( $npcink_toolbox_local_review_received_artifact ), 'Addon receiver receives the exact canonical local11 artifact contract.' );
npcink_toolbox_local_review_assert( $base_artifact['sha256'] === $npcink_toolbox_local_review_received_artifact['sha256'], 'Addon receiver gets the bare sha256 value.' );
npcink_toolbox_local_review_assert( ! isset( $npcink_toolbox_local_review_received_artifact['checksum'], $npcink_toolbox_local_review_received_artifact['artifact_reference'] ), 'Addon receiver gets no Cloud-only checksum or artifact_reference fields.' );

$reordered_artifact = array_reverse( $base_artifact, true );
$reordered_artifact['filename_basis'] = array_reverse( $base_artifact['filename_basis'], true );
$assert_descriptor_forwarded( $reordered_artifact, 'Reordered JSON object keys remain a valid exact local11 body.' );
$assert_descriptor_rejected( array_merge( $base_artifact, array( 'expires_at' => '2030-02-31T00:00:00Z' ) ), 'Impossible UTC calendar dates are rejected.' );
$assert_descriptor_rejected( array_merge( $base_artifact, array( 'expires_at' => '2030-01-01T08:00:00+08:00' ) ), 'Non-UTC RFC3339 offsets are rejected.' );
$assert_descriptor_forwarded( array_merge( $base_artifact, array( 'expires_at' => gmdate( 'Y-m-d\TH:i:s', time() + 900 ) . '+00:00' ) ), 'Canonical +00:00 UTC RFC3339 reaches Cloud Addon.' );
$assert_descriptor_forwarded( array_merge( $base_artifact, array( 'width' => 8192, 'height' => 2048 ) ), 'Exact 8192-axis and 16777216-area boundary reaches Cloud Addon.' );
$assert_descriptor_rejected( array_merge( $base_artifact, array( 'width' => '8192', 'height' => 2048 ) ), 'A numeric string is rejected instead of coerced.' );
$assert_descriptor_rejected( array_merge( $base_artifact, array( 'width' => 8193 ) ), 'An axis above 8192 is rejected.' );
$assert_descriptor_rejected( array_merge( $base_artifact, array( 'width' => 4097, 'height' => 4096 ) ), 'An image area above 16777216 is rejected.' );
$assert_descriptor_forwarded( array_merge( $base_artifact, array( 'suggested_filename' => str_repeat( 'a', 115 ) . '.webp' ) ), 'A canonical 120-byte suggested filename reaches Cloud Addon.' );
$assert_descriptor_rejected( array_merge( $base_artifact, array( 'suggested_filename' => str_repeat( 'a', 116 ) . '.webp' ) ), 'A 121-byte suggested filename is rejected.' );
$assert_descriptor_forwarded( array_merge( $base_artifact, array( 'processing_warnings' => array( str_repeat( 'w', 200 ) ) ) ), 'A 200-byte processing warning reaches Cloud Addon.' );
$assert_descriptor_rejected( array_merge( $base_artifact, array( 'processing_warnings' => array( str_repeat( 'w', 201 ) ) ) ), 'A 201-byte processing warning is rejected.' );
$assert_descriptor_rejected( array_merge( $base_artifact, array( 'mime_type' => 'image/png' ) ), 'MIME and format mismatch is rejected by callback-level cross-field validation.' );
$assert_descriptor_rejected( array_merge( $base_artifact, array( 'processing_warnings' => array( 'not-a-list' => 'warning' ) ) ), 'Associative warnings are rejected by callback-level exact-list validation.' );

$cloud_artifact = array(
	'artifact_id'         => $artifact_id,
	'artifact_reference'  => array( 'artifact_id' => $artifact_id ),
	'expires_at'          => $base_artifact['expires_at'],
	'suggested_filename'  => $base_artifact['suggested_filename'],
	'filename_basis'      => array_reverse( $base_artifact['filename_basis'], true ),
	'mime_type'           => $base_artifact['mime_type'],
	'format'              => $base_artifact['format'],
	'width'               => $base_artifact['width'],
	'height'              => $base_artifact['height'],
	'filesize_bytes'      => $base_artifact['filesize_bytes'],
	'checksum'            => 'sha256:' . $base_artifact['sha256'],
	'processing_warnings' => $base_artifact['processing_warnings'],
);
$projection_method = new ReflectionMethod( \Npcink_Toolbox\Rest_Controller::class, 'media_derivative_local_review_projection' );
$projection_method->setAccessible( true );
$local_review = $projection_method->invoke( $controller, array( 'artifact' => array_reverse( $cloud_artifact, true ) ) );
npcink_toolbox_local_review_assert( array( 'endpoint', 'method', 'artifact' ) === array_keys( $local_review ), 'Projection emits only endpoint, method, and artifact.' );
npcink_toolbox_local_review_assert( 'POST' === ( $local_review['method'] ?? null ), 'Projection requires POST.' );
npcink_toolbox_local_review_assert( false === strpos( (string) ( $local_review['endpoint'] ?? '' ), '?' ), 'Projection endpoint is queryless.' );
npcink_toolbox_local_review_assert( array_keys( $base_artifact ) === array_keys( (array) ( $local_review['artifact'] ?? array() ) ), 'Projection emits the exact canonical local11 JSON artifact.' );
npcink_toolbox_local_review_assert( ! isset( $local_review['artifact']['checksum'], $local_review['artifact']['artifact_reference'] ), 'Projection strips Cloud-only checksum and artifact_reference fields.' );

echo "Media derivative local review behavior checks passed.\n";
