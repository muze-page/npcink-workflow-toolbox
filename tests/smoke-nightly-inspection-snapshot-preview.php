<?php
/**
 * Validates the local snapshot collector and manual Morning Brief preview chain.
 *
 * @package Npcink_Toolbox
 */

$root = dirname( __DIR__ );

if ( ! class_exists( 'WP_Post' ) ) {
	class WP_Post {
		public int $ID;
		public string $post_type;
		public string $post_status;
		public string $post_title;
		public string $post_content;
		public string $post_modified_gmt;

		public function __construct( int $id, string $post_type, string $title, string $content, string $modified_gmt = '2026-06-16 00:00:00' ) {
			$this->ID                = $id;
			$this->post_type         = $post_type;
			$this->post_status       = 'publish';
			$this->post_title        = $title;
			$this->post_content      = $content;
			$this->post_modified_gmt = $modified_gmt;
		}
	}
}

function current_time( string $type, bool $gmt = false ): int {
	return 1781575200;
}

function home_url( string $path = '/' ): string {
	return 'https://example.test' . $path;
}

function wp_parse_url( string $url, int $component = -1 ) {
	return parse_url( $url, $component );
}

function is_wp_error( $value ): bool {
	return false;
}

function get_posts( array $args ): array {
	if ( 'attachment' === ( $args['post_type'] ?? '' ) ) {
		return array(
			new WP_Post( 201, 'attachment', 'Hero image', '', '2026-06-15 00:00:00' ),
		);
	}

	return array(
		new WP_Post( 101, 'post', '2024 WordPress Optimization Guide', '<p>Short intro with <a href="/internal">internal</a>.</p><img src="/missing.jpg" />', '2024-01-10 08:00:00' ),
		new WP_Post( 102, 'page', 'Useful Editorial Maintenance Checklist', '<h2>Overview</h2><p>Structured content with <a href="https://example.test/related">related</a>.</p>', '2026-05-10 08:00:00' ),
	);
}

function get_post_meta( int $post_id, string $key, bool $single = false ): string {
	$values = array(
		'102:_yoast_wpseo_metadesc' => 'A practical maintenance checklist for reviewing content quality, structure, internal links, media evidence, and editorial readiness.',
		'201:_wp_attachment_image_alt' => '',
	);

	return $values[ $post_id . ':' . $key ] ?? '';
}

function wp_get_post_terms( int $post_id, string $taxonomy, array $args = array() ): array {
	if ( 102 === $post_id && 'category' === $taxonomy ) {
		return array( 'Editorial' );
	}
	if ( 102 === $post_id && 'post_tag' === $taxonomy ) {
		return array( 'maintenance' );
	}

	return array();
}

function get_post_thumbnail_id( int $post_id ): int {
	return 0;
}

function get_attached_file( int $attachment_id ): string {
	return '/tmp/hero-image.jpg';
}

require_once $root . '/modules/local-automation-runtime/src/Contract/Replay_Validator.php';
require_once $root . '/modules/local-automation-runtime/src/NightlyInspection/Rule_Scorer.php';
require_once $root . '/modules/local-automation-runtime/src/NightlyInspection/Morning_Brief_Builder.php';
require_once $root . '/modules/local-automation-runtime/src/NightlyInspection/Manual_Dry_Run_Planner.php';
require_once $root . '/modules/local-automation-runtime/src/NightlyInspection/Snapshot_Collector.php';

$fail = static function ( string $message ): void {
	fwrite( STDERR, $message . "\n" );
	exit( 1 );
};

$collector = new \Npcink\LocalAutomationRuntime\NightlyInspection\Snapshot_Collector();
$snapshot  = $collector->collect();

if ( 'example.test' !== ( $snapshot['site_id'] ?? '' ) ) {
	$fail( 'Snapshot collector should identify the local site host.' );
}
if ( 2 !== count( $snapshot['posts'] ?? array() ) ) {
	$fail( 'Snapshot collector should include bounded public posts and pages.' );
}
if ( 1 !== count( $snapshot['media'] ?? array() ) ) {
	$fail( 'Snapshot collector should include bounded image attachments.' );
}
if ( 1 !== ( $snapshot['posts'][0]['internal_link_count'] ?? 0 ) ) {
	$fail( 'Snapshot collector should count relative internal links.' );
}
if ( 1 !== ( $snapshot['posts'][0]['missing_alt_count'] ?? 0 ) ) {
	$fail( 'Snapshot collector should count missing inline image alt text.' );
}

$planner = new \Npcink\LocalAutomationRuntime\NightlyInspection\Manual_Dry_Run_Planner();
$replay  = $planner->plan( $snapshot );

$validator = new \Npcink\LocalAutomationRuntime\Contract\Replay_Validator();
$result    = $validator->validate( $replay );
if ( true !== ( $result['valid'] ?? false ) ) {
	$fail( 'Snapshot preview replay failed validation: ' . implode( ', ', $result['errors'] ?? array() ) );
}
if ( false !== ( $replay['core_runtime_execution'] ?? true ) || false !== ( $replay['background_execution'] ?? true ) ) {
	$fail( 'Snapshot preview must not execute Core runtime or background work.' );
}
if ( false !== ( $replay['preview']['morning_brief']['safety']['direct_wordpress_write'] ?? true ) ) {
	$fail( 'Snapshot preview must keep direct WordPress writes false.' );
}

echo "Nightly inspection snapshot preview: ok\n";
