<?php
/**
 * Export stratified WordPress article samples for recommendation evaluation.
 *
 * Run through WP-CLI:
 * wp --path=/path/to/wp eval-file tests/recommendation-eval/export-wp-samples.php -- output=tests/recommendation-eval/generated/samples.json limit=50
 *
 * @package Npcink_Toolbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "FAIL: Run this script through WP-CLI eval-file so WordPress is loaded.\n" );
	exit( 1 );
}

$script_args = isset( $args ) && is_array( $args ) ? $args : array_slice( $argv ?? array(), 1 );
$root        = dirname( __DIR__, 2 );

function npcink_rec_eval_export_arg_map( array $script_args ): array {
	$parsed = array();
	foreach ( $script_args as $arg ) {
		$arg = (string) $arg;
		if ( ! str_contains( $arg, '=' ) ) {
			continue;
		}
		list( $key, $value ) = explode( '=', $arg, 2 );
		$parsed[ trim( $key ) ] = trim( $value );
	}

	return $parsed;
}

function npcink_rec_eval_export_fail( string $message ): void {
	fwrite( STDERR, "FAIL: {$message}\n" );
	exit( 1 );
}

function npcink_rec_eval_export_path( string $path, string $root ): string {
	if ( str_starts_with( $path, '/' ) ) {
		return $path;
	}

	return $root . '/' . ltrim( $path, '/' );
}

function npcink_rec_eval_export_text( string $value, int $limit ): string {
	$text = html_entity_decode( wp_strip_all_tags( strip_shortcodes( $value ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$text = preg_replace( '/\s+/u', ' ', $text );
	$text = is_string( $text ) ? trim( $text ) : '';
	if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) && mb_strlen( $text, 'UTF-8' ) > $limit ) {
		return mb_substr( $text, 0, $limit, 'UTF-8' );
	}

	return strlen( $text ) > $limit ? substr( $text, 0, $limit ) : $text;
}

function npcink_rec_eval_export_post_row( WP_Post $post, string $bucket, int $content_chars ): array {
	$post_id = (int) $post->ID;
	$cats    = wp_get_post_terms( $post_id, 'category', array( 'fields' => 'all' ) );
	$tags    = wp_get_post_terms( $post_id, 'post_tag', array( 'fields' => 'all' ) );

	return array(
		'id'                    => 'wp-post-' . $post_id,
		'post_id'               => $post_id,
		'bucket'                => $bucket,
		'url'                   => get_permalink( $post_id ),
		'post_type'             => get_post_type( $post_id ),
		'post_status'           => get_post_status( $post_id ),
		'date_gmt'              => get_post_time( 'c', true, $post_id ),
		'title'                 => npcink_rec_eval_export_text( get_the_title( $post_id ), 300 ),
		'existing_excerpt'      => npcink_rec_eval_export_text( (string) $post->post_excerpt, 800 ),
		'content'               => npcink_rec_eval_export_text( (string) $post->post_content, $content_chars ),
		'content_chars'         => strlen( wp_strip_all_tags( (string) $post->post_content ) ),
		'current_categories'    => array_values(
			array_map(
				static fn( WP_Term $term ): array => array(
					'term_id' => (int) $term->term_id,
					'name'    => (string) $term->name,
					'slug'    => (string) $term->slug,
				),
				is_array( $cats ) ? $cats : array()
			)
		),
		'current_tags'          => array_values(
			array_map(
				static fn( WP_Term $term ): array => array(
					'term_id' => (int) $term->term_id,
					'name'    => (string) $term->name,
					'slug'    => (string) $term->slug,
				),
				is_array( $tags ) ? $tags : array()
			)
		),
		'has_featured_image'    => has_post_thumbnail( $post_id ),
		'first_round_tools'     => array( 'title', 'summary', 'category', 'tag' ),
		'write_posture'         => 'eval_only_no_wordpress_write',
	);
}

function npcink_rec_eval_export_query_posts( array $args ): array {
	$query = new WP_Query(
		array_merge(
			array(
				'post_type'              => 'post',
				'post_status'            => 'publish',
				'posts_per_page'         => 10,
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'suppress_filters'       => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => true,
			),
			$args
		)
	);

	return array_values( array_filter( $query->posts, static fn( $post ): bool => $post instanceof WP_Post ) );
}

function npcink_rec_eval_export_all_terms( string $taxonomy, int $limit ): array {
	$terms = get_terms(
		array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'number'     => $limit,
			'orderby'    => 'count',
			'order'      => 'DESC',
		)
	);
	if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
		return array();
	}

	return array_values(
		array_map(
			static fn( WP_Term $term ): array => array(
				'term_id' => (int) $term->term_id,
				'name'    => (string) $term->name,
				'slug'    => (string) $term->slug,
				'count'   => (int) $term->count,
			),
			$terms
		)
	);
}

$arg_map       = npcink_rec_eval_export_arg_map( $script_args );
$output        = npcink_rec_eval_export_path( (string) ( $arg_map['output'] ?? 'tests/recommendation-eval/generated/samples.json' ), $root );
$limit         = max( 5, min( 80, (int) ( $arg_map['limit'] ?? 50 ) ) );
$per_bucket    = max( 1, min( 20, (int) ( $arg_map['per_bucket'] ?? (int) ceil( $limit / 5 ) ) ) );
$content_chars = max( 1000, min( 30000, (int) ( $arg_map['content_chars'] ?? 8000 ) ) );
$post_type     = sanitize_key( (string) ( $arg_map['post_type'] ?? 'post' ) );
$status        = sanitize_key( (string) ( $arg_map['status'] ?? 'publish' ) );

$buckets = array(
	'recent' => npcink_rec_eval_export_query_posts(
		array(
			'post_type'      => $post_type,
			'post_status'    => $status,
			'posts_per_page' => $per_bucket,
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	),
	'old' => npcink_rec_eval_export_query_posts(
		array(
			'post_type'      => $post_type,
			'post_status'    => $status,
			'posts_per_page' => $per_bucket,
			'orderby'        => 'date',
			'order'          => 'ASC',
		)
	),
	'random' => npcink_rec_eval_export_query_posts(
		array(
			'post_type'      => $post_type,
			'post_status'    => $status,
			'posts_per_page' => $per_bucket,
			'orderby'        => 'rand',
		)
	),
);

$pool = npcink_rec_eval_export_query_posts(
	array(
		'post_type'      => $post_type,
		'post_status'    => $status,
		'posts_per_page' => 200,
		'orderby'        => 'date',
		'order'          => 'DESC',
	)
);

$buckets['metadata_gap'] = array_values(
	array_slice(
		array_filter(
			$pool,
			static function ( WP_Post $post ): bool {
				$post_id = (int) $post->ID;
				return '' === trim( (string) $post->post_excerpt )
					|| array() === wp_get_post_terms( $post_id, 'category', array( 'fields' => 'ids' ) )
					|| array() === wp_get_post_terms( $post_id, 'post_tag', array( 'fields' => 'ids' ) );
			}
		),
		0,
		$per_bucket
	)
);

usort(
	$pool,
	static fn( WP_Post $a, WP_Post $b ): int => strlen( wp_strip_all_tags( (string) $b->post_content ) ) <=> strlen( wp_strip_all_tags( (string) $a->post_content ) )
);
$buckets['long_form'] = array_slice( $pool, 0, $per_bucket );

$samples = array();
$seen    = array();
foreach ( $buckets as $bucket => $posts ) {
	foreach ( $posts as $post ) {
		$post_id = (int) $post->ID;
		if ( isset( $seen[ $post_id ] ) || count( $samples ) >= $limit ) {
			continue;
		}
		$seen[ $post_id ] = true;
		$samples[]        = npcink_rec_eval_export_post_row( $post, $bucket, $content_chars );
	}
}

if ( array() === $samples ) {
	npcink_rec_eval_export_fail( 'No posts were exported for recommendation eval.' );
}

$payload = array(
	'version'        => 1,
	'generated_at'   => gmdate( 'c' ),
	'source'         => array(
		'type'          => 'wordpress_stratified_sample',
		'site_url'      => home_url( '/' ),
		'post_type'     => $post_type,
		'post_status'   => $status,
		'limit'         => $limit,
		'per_bucket'    => $per_bucket,
		'content_chars' => $content_chars,
		'count'         => count( $samples ),
	),
	'available_terms' => array(
		'categories' => npcink_rec_eval_export_all_terms( 'category', 300 ),
		'tags'       => npcink_rec_eval_export_all_terms( 'post_tag', 500 ),
	),
	'notes'          => 'First-round recommendation eval samples only. Scripts must not write WordPress data.',
	'samples'        => $samples,
);

$directory = dirname( $output );
if ( ! is_dir( $directory ) && ! mkdir( $directory, 0775, true ) && ! is_dir( $directory ) ) {
	npcink_rec_eval_export_fail( 'Unable to create output directory: ' . $directory );
}

$encoded = wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
if ( ! is_string( $encoded ) || false === file_put_contents( $output, $encoded . "\n" ) ) {
	npcink_rec_eval_export_fail( 'Unable to write output: ' . $output );
}

echo 'Exported ' . count( $samples ) . ' recommendation eval sample(s) to ' . $output . "\n";
