<?php
/**
 * Real-network smoke for URL writing packs and confirmed draft previews.
 *
 * Run through WP-CLI only. The script reads one existing post as editor context,
 * calls the three public URL fixtures, and proves that no WordPress state changes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run this script through WP-CLI eval-file.\n" );
	exit( 1 );
}

function npcink_toolbox_writing_pack_smoke_fail( string $message ): void {
	throw new RuntimeException( $message );
}

function npcink_toolbox_writing_pack_smoke_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		npcink_toolbox_writing_pack_smoke_fail( $message );
	}
}

function npcink_toolbox_writing_pack_smoke_has_value( $value ): bool {
	if ( is_scalar( $value ) ) {
		return '' !== trim( (string) $value );
	}
	if ( ! is_array( $value ) ) {
		return false;
	}
	foreach ( $value as $item ) {
		if ( npcink_toolbox_writing_pack_smoke_has_value( $item ) ) {
			return true;
		}
	}
	return false;
}

function npcink_toolbox_writing_pack_smoke_admin_id(): int {
	$users = get_users(
		array(
			'role'   => 'administrator',
			'number' => 1,
			'fields' => 'ID',
		)
	);
	return absint( $users[0] ?? 0 );
}

function npcink_toolbox_writing_pack_smoke_context_post(): WP_Post {
	$posts = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => array( 'publish', 'draft', 'private', 'pending', 'future' ),
			'posts_per_page' => 1,
			'orderby'        => 'modified',
			'order'          => 'DESC',
		)
	);
	if ( empty( $posts[0] ) || ! $posts[0] instanceof WP_Post ) {
		npcink_toolbox_writing_pack_smoke_fail( 'A local post is required as read-only editor context.' );
	}
	return $posts[0];
}

function npcink_toolbox_writing_pack_smoke_counts(): array {
	$post_counts       = wp_count_posts( 'post' );
	$attachment_counts = wp_count_posts( 'attachment' );
	return array(
		'posts'       => get_object_vars( $post_counts ),
		'attachments' => get_object_vars( $attachment_counts ),
	);
}

function npcink_toolbox_writing_pack_smoke_snapshot( int $post_id ): array {
	$post = get_post( $post_id );
	if ( ! $post ) {
		npcink_toolbox_writing_pack_smoke_fail( 'The read-only context post disappeared.' );
	}
	$terms = wp_get_object_terms( $post_id, get_object_taxonomies( $post->post_type ), array( 'fields' => 'tt_ids' ) );
	if ( is_wp_error( $terms ) ) {
		npcink_toolbox_writing_pack_smoke_fail( 'Could not snapshot context-post terms.' );
	}
	sort( $terms );
	return array(
		'post_title'     => (string) $post->post_title,
		'post_excerpt'   => (string) $post->post_excerpt,
		'post_content'   => (string) $post->post_content,
		'post_status'    => (string) $post->post_status,
		'post_modified'  => (string) $post->post_modified,
		'featured_media' => (int) get_post_thumbnail_id( $post_id ),
		'term_ids'       => array_map( 'intval', $terms ),
		'object_counts'  => npcink_toolbox_writing_pack_smoke_counts(),
	);
}

function npcink_toolbox_writing_pack_smoke_rest( array $params ): array {
	$request = new WP_REST_Request( 'POST', '/npcink-toolbox/v1/editor/content-support' );
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}
	$started  = microtime( true );
	$response = rest_do_request( $request );
	if ( is_wp_error( $response ) ) {
		npcink_toolbox_writing_pack_smoke_fail( 'REST dispatch returned WP_Error: ' . $response->get_error_code() );
	}
	$data = $response->get_data();
	return array(
		'status'     => (int) $response->get_status(),
		'data'       => is_array( $data ) ? $data : array(),
		'duration_s' => round( microtime( true ) - $started, 3 ),
	);
}

function npcink_toolbox_writing_pack_smoke_base_params( WP_Post $post ): array {
	return array(
		'intent'         => 'source_adaptation_review',
		'input_mode'     => 'url_reference',
		'post_id'        => (int) $post->ID,
		'post_type'      => (string) $post->post_type,
		'post_status'    => (string) $post->post_status,
		'title'          => (string) $post->post_title,
		'excerpt'        => (string) $post->post_excerpt,
		'content'        => (string) $post->post_content,
		'featured_media' => (int) get_post_thumbnail_id( $post->ID ),
	);
}

function npcink_toolbox_writing_pack_smoke_assert_no_write( int $post_id, array $before, string $stage ): void {
	clean_post_cache( $post_id );
	$after = npcink_toolbox_writing_pack_smoke_snapshot( $post_id );
	npcink_toolbox_writing_pack_smoke_assert( $before === $after, $stage . ' mutated WordPress post, media, terms, or object counts.' );
}

$fixture_path = __DIR__ . '/fixtures/source-adaptation-real-url-trial.json';
$fixture      = json_decode( (string) file_get_contents( $fixture_path ), true );
npcink_toolbox_writing_pack_smoke_assert( is_array( $fixture ), 'Real-URL fixture must be valid JSON.' );
npcink_toolbox_writing_pack_smoke_assert( 'source_adaptation_real_url_trial.v1' === ( $fixture['contract_version'] ?? '' ), 'Real-URL fixture contract is current.' );
npcink_toolbox_writing_pack_smoke_assert( 3 === count( $fixture['cases'] ?? array() ), 'Exactly three public WordPress URL cases are required.' );
npcink_toolbox_writing_pack_smoke_assert( class_exists( 'WP_REST_Request' ) && function_exists( 'rest_do_request' ), 'WordPress REST dispatch is available.' );

$admin_id = npcink_toolbox_writing_pack_smoke_admin_id();
npcink_toolbox_writing_pack_smoke_assert( $admin_id > 0, 'A local administrator is required for REST permission checks.' );
wp_set_current_user( $admin_id );

$post    = npcink_toolbox_writing_pack_smoke_context_post();
$before  = npcink_toolbox_writing_pack_smoke_snapshot( (int) $post->ID );
$base    = npcink_toolbox_writing_pack_smoke_base_params( $post );
$summary      = array();
$review_cases = array();
$case_limit  = absint( getenv( 'NPCINK_TOOLBOX_WRITING_PACK_CASE_LIMIT' ) );
$trial_cases = $case_limit > 0 ? array_slice( $fixture['cases'], 0, min( 3, $case_limit ) ) : $fixture['cases'];

try {
	foreach ( $trial_cases as $case ) {
		$case_id = sanitize_key( (string) ( $case['id'] ?? '' ) );
		$url     = esc_url_raw( (string) ( $case['url'] ?? '' ) );
		npcink_toolbox_writing_pack_smoke_assert( '' !== $case_id && '' !== $url, 'Every real-URL case needs an id and URL.' );

		$extract = npcink_toolbox_writing_pack_smoke_rest(
			array_merge(
				$base,
				array(
					'source_stage' => 'extract',
					'source_url'   => $url,
				)
			)
		);
		$extract_data = $extract['data'];
		$source       = $extract_data['sections']['source_article'] ?? array();
		npcink_toolbox_writing_pack_smoke_assert( 200 === $extract['status'], $case_id . ': extraction request failed.' );
		npcink_toolbox_writing_pack_smoke_assert( 'source_extraction_preview.v1' === ( $extract_data['artifact_type'] ?? '' ), $case_id . ': extraction artifact contract mismatch.' );
		npcink_toolbox_writing_pack_smoke_assert( 'ready' === ( $source['status'] ?? '' ), $case_id . ': exact extraction is not ready.' );
		npcink_toolbox_writing_pack_smoke_assert( 'matched' === ( $source['url_match'] ?? '' ), $case_id . ': exact URL did not match.' );
		npcink_toolbox_writing_pack_smoke_assert( npcink_toolbox_writing_pack_smoke_has_value( $source['content_hash'] ?? '' ), $case_id . ': extraction content hash is missing.' );
		npcink_toolbox_writing_pack_smoke_assert_no_write( (int) $post->ID, $before, $case_id . ' extraction' );

		$research = npcink_toolbox_writing_pack_smoke_rest(
			array_merge(
				$base,
				array(
					'source_stage'      => 'research_plan',
					'source_url'        => $url,
					'force_regenerate'  => true,
					'generation_variant' => 'real-url-pack-' . $case_id . '-' . wp_generate_uuid4(),
				)
			)
		);
		$research_data = $research['data'];
		$pack          = $research_data['sections']['article_writing_pack'] ?? array();
		$facts         = $pack['research_basis']['fact_ledger'] ?? array();
		$review              = $research_data['sections']['source_adaptation_review'] ?? array();
		$research_diagnostic = wp_json_encode(
			array(
				'review_status'    => (string) ( $review['status'] ?? '' ),
				'review_code'      => (string) ( $review['code'] ?? $review['error_code'] ?? '' ),
				'review_message'   => (string) ( $review['message'] ?? '' ),
				'blocking_reasons' => $pack['generation_admission']['blocking_reasons'] ?? array(),
			),
			JSON_UNESCAPED_SLASHES
		);
		npcink_toolbox_writing_pack_smoke_assert( 200 === $research['status'], $case_id . ': writing-pack request failed.' );
		npcink_toolbox_writing_pack_smoke_assert( 'article_writing_pack.v1' === ( $research_data['artifact_type'] ?? '' ), $case_id . ': writing-pack response contract mismatch.' );
		npcink_toolbox_writing_pack_smoke_assert( 'article_writing_pack.v1' === ( $pack['artifact_type'] ?? '' ), $case_id . ': nested writing pack is missing.' );
		npcink_toolbox_writing_pack_smoke_assert( ! empty( $facts ), $case_id . ': fact ledger is empty. ' . $research_diagnostic );
		foreach ( $facts as $fact_index => $fact ) {
			npcink_toolbox_writing_pack_smoke_assert( npcink_toolbox_writing_pack_smoke_has_value( $fact['claim'] ?? '' ), $case_id . ': fact ' . $fact_index . ' has no claim.' );
			npcink_toolbox_writing_pack_smoke_assert( npcink_toolbox_writing_pack_smoke_has_value( $fact['evidence_basis'] ?? '' ), $case_id . ': fact ' . $fact_index . ' has no evidence basis.' );
			npcink_toolbox_writing_pack_smoke_assert( npcink_toolbox_writing_pack_smoke_has_value( $fact['verification_status'] ?? '' ), $case_id . ': fact ' . $fact_index . ' has no verification status.' );
		}
		npcink_toolbox_writing_pack_smoke_assert( false === ( $pack['generation_admission']['article_generation_allowed'] ?? true ), $case_id . ': unconfirmed pack incorrectly admits generation.' );
		npcink_toolbox_writing_pack_smoke_assert( false === ( $pack['direct_wordpress_write'] ?? true ), $case_id . ': writing pack claims direct write capability.' );
		npcink_toolbox_writing_pack_smoke_assert_no_write( (int) $post->ID, $before, $case_id . ' research' );

		$unconfirmed = npcink_toolbox_writing_pack_smoke_rest(
			array_merge(
				$base,
				array(
					'source_stage'          => 'draft',
					'reviewed_writing_pack' => $pack,
					'writing_pack_confirmation' => array(
						'status'                   => 'needs_review',
						'confirmed'                => false,
						'base_content_fingerprint' => (string) ( $pack['content_fingerprint'] ?? '' ),
					),
				)
			)
		);
		npcink_toolbox_writing_pack_smoke_assert( 400 === $unconfirmed['status'], $case_id . ': unconfirmed draft was not rejected.' );
		npcink_toolbox_writing_pack_smoke_assert( 'npcink_toolbox_writing_pack_review_confirmation_required' === ( $unconfirmed['data']['code'] ?? '' ), $case_id . ': unconfirmed draft returned the wrong error.' );
		npcink_toolbox_writing_pack_smoke_assert_no_write( (int) $post->ID, $before, $case_id . ' unconfirmed draft' );

		$draft = npcink_toolbox_writing_pack_smoke_rest(
			array_merge(
				$base,
				array(
					'source_stage'          => 'draft',
					'reviewed_writing_pack' => $pack,
					'writing_pack_confirmation' => array(
						'status'                   => 'confirmed_by_operator',
						'confirmed'                => true,
						'base_content_fingerprint' => (string) ( $pack['content_fingerprint'] ?? '' ),
					),
					'force_regenerate'      => true,
					'generation_variant'    => 'real-url-draft-' . $case_id . '-' . wp_generate_uuid4(),
				)
			)
		);
		$draft_data    = $draft['data'];
		$draft_preview = $draft_data['sections']['article_draft_preview'] ?? array();
		npcink_toolbox_writing_pack_smoke_assert( 200 === $draft['status'], $case_id . ': confirmed draft request failed.' );
		npcink_toolbox_writing_pack_smoke_assert( 'article_draft_preview.v1' === ( $draft_data['artifact_type'] ?? '' ), $case_id . ': draft response contract mismatch.' );
		npcink_toolbox_writing_pack_smoke_assert( 'ready' === ( $draft_preview['status'] ?? '' ) && ! empty( $draft_preview['sections'] ), $case_id . ': structured draft preview is not ready.' );
		npcink_toolbox_writing_pack_smoke_assert( false === ( $draft_preview['direct_wordpress_write'] ?? true ), $case_id . ': draft preview claims direct write capability.' );
		npcink_toolbox_writing_pack_smoke_assert( false === ( $draft_preview['body_insertion'] ?? true ), $case_id . ': draft preview claims body insertion capability.' );
		npcink_toolbox_writing_pack_smoke_assert_no_write( (int) $post->ID, $before, $case_id . ' confirmed draft' );

		$summary[] = array(
			'case'                => $case_id,
			'extract_seconds'     => $extract['duration_s'],
			'research_seconds'    => $research['duration_s'],
			'draft_seconds'       => $draft['duration_s'],
			'fact_count'          => count( $facts ),
			'draft_section_count' => count( $draft_preview['sections'] ),
			'wordpress_mutated'   => false,
		);
		$review_cases[] = array(
			'case'          => $case_id,
			'source_url'    => $url,
			'writing_pack'  => $pack,
			'draft_preview' => $draft_preview,
			'human_review'  => array(
				'status'      => 'pending',
				'issue_codes' => array(),
				'notes'       => '',
			),
		);
	}
} catch ( Throwable $error ) {
	npcink_toolbox_writing_pack_smoke_assert_no_write( (int) $post->ID, $before, 'failed real-URL smoke' );
	WP_CLI::error( $error->getMessage() );
}

if ( '1' === getenv( 'NPCINK_TOOLBOX_WRITING_PACK_REVIEW_EXPORT' ) ) {
	$review_dir  = dirname( __DIR__ ) . '/build/eval';
	$review_path = $review_dir . '/article-writing-pack-real-url-review.json';
	npcink_toolbox_writing_pack_smoke_assert( wp_mkdir_p( $review_dir ), 'Could not create the local writing-pack review directory.' );
	$written = file_put_contents(
		$review_path,
		wp_json_encode(
			array(
				'artifact_type'          => 'article_writing_pack_operator_trial.v1',
				'generated_at'           => gmdate( 'c' ),
				'write_posture'          => 'local_eval_only',
				'direct_wordpress_write' => false,
				'cases'                  => $review_cases,
			),
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		) . "\n"
	);
	npcink_toolbox_writing_pack_smoke_assert( false !== $written, 'Could not write the local writing-pack review export.' );
	WP_CLI::log( 'Local writing-pack review export: ' . $review_path );
}

WP_CLI::log( wp_json_encode( $summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
WP_CLI::success( sprintf( '%d real URL writing-pack case(s) passed extraction, fact-ledger, confirmation, draft-preview, and no-write gates.', count( $summary ) ) );
