<?php
/**
 * Export local WordPress posts into summary-eval source samples.
 *
 * Run through WP-CLI:
 * wp --path=/path/to/wp eval-file tests/summary-eval/export-wp-samples.php -- author=Muze limit=50 output=tests/summary-eval/generated/muze-source-samples.json
 *
 * @package Npcink_Toolbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "FAIL: Run this script through WP-CLI eval-file so WordPress is loaded.\n" );
	exit( 1 );
}

$script_args = isset( $args ) && is_array( $args ) ? $args : array_slice( $argv ?? array(), 1 );
$root        = dirname( __DIR__, 2 );

function npcink_summary_export_arg_map( array $script_args ): array {
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

function npcink_summary_export_fail( string $message ): void {
	fwrite( STDERR, "FAIL: {$message}\n" );
	exit( 1 );
}

function npcink_summary_export_post_text( string $value, int $limit ): string {
	$text = html_entity_decode( wp_strip_all_tags( strip_shortcodes( $value ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$text = preg_replace( '/\s+/u', ' ', $text );
	$text = is_string( $text ) ? trim( $text ) : '';
	if ( function_exists( 'mb_substr' ) && function_exists( 'mb_strlen' ) && mb_strlen( $text, 'UTF-8' ) > $limit ) {
		return mb_substr( $text, 0, $limit, 'UTF-8' );
	}

	return strlen( $text ) > $limit ? substr( $text, 0, $limit ) : $text;
}

function npcink_summary_export_find_author_ids( string $author ): array {
	if ( '' === trim( $author ) ) {
		return array();
	}

	$author_lower = strtolower( $author );
	$ids          = array();
	foreach ( get_users( array( 'number' => 1000 ) ) as $user ) {
		$values = array(
			(string) ( $user->user_login ?? '' ),
			(string) ( $user->user_nicename ?? '' ),
			(string) ( $user->display_name ?? '' ),
			(string) ( $user->user_email ?? '' ),
		);
		foreach ( $values as $value ) {
			if ( strtolower( $value ) === $author_lower ) {
				$ids[] = (int) $user->ID;
				break;
			}
		}
	}

	return array_values( array_unique( array_filter( $ids ) ) );
}

$arg_map     = npcink_summary_export_arg_map( $script_args );
$author      = (string) ( $arg_map['author'] ?? 'Muze' );
$limit       = max( 1, min( 200, (int) ( $arg_map['limit'] ?? 50 ) ) );
$status      = sanitize_key( (string) ( $arg_map['status'] ?? 'publish' ) );
$post_type   = sanitize_key( (string) ( $arg_map['post_type'] ?? 'post' ) );
$output      = (string) ( $arg_map['output'] ?? $root . '/tests/summary-eval/generated/muze-source-samples.json' );
$content_max = max( 500, min( 20000, (int) ( $arg_map['content_chars'] ?? 6000 ) ) );
$include_excerpt_candidate = in_array( strtolower( (string) ( $arg_map['include_excerpt_candidate'] ?? '0' ) ), array( '1', 'true', 'yes' ), true );

if ( ! str_starts_with( $output, '/' ) ) {
	$output = $root . '/' . ltrim( $output, '/' );
}

$author_ids = npcink_summary_export_find_author_ids( $author );
if ( array() === $author_ids ) {
	npcink_summary_export_fail( 'No WordPress author matched: ' . $author );
}

$query = new WP_Query(
	array(
		'author__in'       => $author_ids,
		'post_type'        => $post_type,
		'post_status'      => $status,
		'posts_per_page'   => $limit,
		'orderby'          => 'date',
		'order'            => 'DESC',
		'no_found_rows'    => true,
		'suppress_filters' => false,
	)
);

$samples = array();
foreach ( $query->posts as $post ) {
	$post_id  = (int) $post->ID;
	$excerpt  = trim( npcink_summary_export_post_text( (string) $post->post_excerpt, 800 ) );
	$sample   = array(
		'id'                  => 'wp-post-' . $post_id,
		'post_id'             => $post_id,
		'url'                 => get_permalink( $post_id ),
		'author'              => get_the_author_meta( 'display_name', (int) $post->post_author ),
		'post_status'         => get_post_status( $post_id ),
		'post_type'           => get_post_type( $post_id ),
		'title'               => npcink_summary_export_post_text( get_the_title( $post_id ), 300 ),
		'content'             => npcink_summary_export_post_text( (string) $post->post_content, $content_max ),
		'existing_excerpt'    => $excerpt,
		'has_existing_excerpt' => '' !== $excerpt,
		'content_type'        => 'site_export',
		'categories'          => wp_get_post_terms( $post_id, 'category', array( 'fields' => 'names' ) ),
		'tags'                => wp_get_post_terms( $post_id, 'post_tag', array( 'fields' => 'names' ) ),
		'length'              => array(
			'min' => 60,
			'max' => 160,
		),
		'must_not_contain'    => array(
			'本文',
			'本文说明',
			'本文介绍',
			'这篇文章',
			'该文章',
			'这篇草稿',
			'草稿主张',
		),
	);

	if ( $include_excerpt_candidate && '' !== $excerpt ) {
		$sample['candidates'] = array(
			array(
				'id'            => 'existing_excerpt',
				'summary'       => $excerpt,
				'expected_pass' => true,
			),
		);
	}

	$samples[] = $sample;
}

if ( array() === $samples ) {
	npcink_summary_export_fail( 'No posts matched author=' . $author . ' status=' . $status . ' post_type=' . $post_type );
}

$payload = array(
	'version'     => 1,
	'generated_at' => gmdate( 'c' ),
	'source'      => array(
		'type'       => 'wordpress',
		'site_url'   => home_url( '/' ),
		'author'     => $author,
		'author_ids' => $author_ids,
		'post_status' => $status,
		'post_type'  => $post_type,
		'limit'      => $limit,
		'count'      => count( $samples ),
	),
	'notes'       => 'Source samples only. Add generated candidates before using this as a model-quality regression file.',
	'samples'     => $samples,
);

$directory = dirname( $output );
if ( ! is_dir( $directory ) && ! mkdir( $directory, 0775, true ) && ! is_dir( $directory ) ) {
	npcink_summary_export_fail( 'Unable to create output directory: ' . $directory );
}

$encoded = wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
if ( ! is_string( $encoded ) || false === file_put_contents( $output, $encoded . "\n" ) ) {
	npcink_summary_export_fail( 'Unable to write output: ' . $output );
}

echo 'Exported ' . count( $samples ) . ' summary source sample(s) to ' . $output . "\n";
