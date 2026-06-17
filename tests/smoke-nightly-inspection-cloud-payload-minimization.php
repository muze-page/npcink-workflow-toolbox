<?php
/**
 * Validates Nightly Inspection Cloud Batch payload minimization.
 *
 * @package Npcink_Toolbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		$key = strtolower( (string) $key );
		return preg_replace( '/[^a-z0-9_\-]/', '', $key );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return trim( strip_tags( (string) $value ) );
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $value ) {
		return trim( strip_tags( (string) $value ) );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $value ) {
		return strip_tags( (string) $value );
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}

$root = dirname( __DIR__ );
require_once $root . '/includes/Provider_Client.php';

$fail = static function ( string $message ): void {
	fwrite( STDERR, $message . "\n" );
	exit( 1 );
};

$assert = static function ( bool $condition, string $message ) use ( $fail ): void {
	if ( ! $condition ) {
		$fail( $message );
	}
};

$client = ( new ReflectionClass( \Npcink_Toolbox\Provider_Client::class ) )->newInstanceWithoutConstructor();
$method = new ReflectionMethod( \Npcink_Toolbox\Provider_Client::class, 'nightly_inspection_cloud_batch_items' );
$method->setAccessible( true );

$snapshot = array(
	'posts' => array(
		array(
			'object_type'         => 'post',
			'object_id'           => 101,
			'title'               => 'Contact alice@example.com for the source interview',
			'meta_description'    => 'Reference customer phone 15551234567 in local-only notes.',
			'content'             => 'Draft context with token abcdef should stay local.',
			'internal_link_count' => 2,
			'missing_alt_count'   => 0,
			'modified_at'         => '2026-06-15 00:00:00',
		),
		array(
			'object_type'         => 'post',
			'object_id'           => 102,
			'title'               => 'Complete evergreen article for public review',
			'meta_description'    => 'Public metadata without private identifiers.',
			'content'             => 'Safe public excerpt.',
			'internal_link_count' => 1,
			'missing_alt_count'   => 0,
			'modified_at'         => '2026-06-15 00:00:00',
		),
	),
	'media' => array(
		array(
			'object_id' => 201,
			'title'     => 'IMG_15551234567',
			'filename'  => 'IMG_15551234567.jpg',
			'alt'       => '',
		),
	),
);

$report = array();
$args   = array( $snapshot, 'excerpt', &$report );
$items  = $method->invokeArgs( $client, $args );
$json   = json_encode( $items );

$assert( is_array( $items ) && 3 === count( $items ), 'Cloud batch payload includes the expected bounded items.' );
$assert( 'content item metadata' === (string) $items[0]['title'], 'Sensitive post title is minimized.' );
$assert( '' === (string) $items[0]['meta_description'], 'Sensitive post meta description is minimized to empty fallback.' );
$assert( 'Complete evergreen article for public review' === (string) $items[1]['title'], 'Safe post title is preserved.' );
$assert( 'media attachment metadata' === (string) $items[2]['title'], 'Attachment title is generalized.' );
$assert( 'media attachment metadata' === (string) $items[2]['excerpt'], 'Attachment filename excerpt is generalized.' );
$assert( false === strpos( (string) $json, 'alice@example.com' ), 'Payload does not include raw email text.' );
$assert( false === strpos( (string) $json, '15551234567' ), 'Payload does not include raw long numeric text.' );
$assert( true === (bool) ( $report['applied'] ?? false ), 'Payload minimization report records applied minimization.' );
$assert( 3 <= (int) ( $report['modified_field_count'] ?? 0 ), 'Payload minimization report records modified fields.' );
$assert( false === (bool) ( $report['raw_values_included'] ?? true ), 'Payload minimization report excludes raw values.' );
$assert( false === (bool) ( $report['direct_wordpress_write'] ?? true ), 'Payload minimization report remains read-only.' );

echo "Nightly Inspection Cloud payload minimization smoke passed.\n";
