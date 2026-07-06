<?php
/**
 * Local WordPress operator trial export for Content Metadata Delta.
 *
 * Run with WP-CLI:
 * wp eval-file tests/smoke-content-metadata-operator-trial.php
 *
 * @package Npcink_Toolbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "FAIL: Run this script through WP-CLI eval-file so WordPress is loaded.\n" );
	exit( 1 );
}

function toolbox_metadata_operator_trial_pass( string $message ): void {
	echo "PASS: {$message}\n";
}

function toolbox_metadata_operator_trial_fail( string $message ): void {
	fwrite( STDERR, "FAIL: {$message}\n" );
	exit( 1 );
}

function toolbox_metadata_operator_trial_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		toolbox_metadata_operator_trial_fail( $message );
	}

	toolbox_metadata_operator_trial_pass( $message );
}

function toolbox_metadata_operator_trial_arg_map( array $script_args ): array {
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

function toolbox_metadata_operator_trial_admin_user_id(): int {
	$users = get_users(
		array(
			'role'    => 'administrator',
			'number'  => 1,
			'orderby' => 'ID',
			'order'   => 'ASC',
			'fields'  => 'ID',
		)
	);

	return absint( $users[0] ?? 0 );
}

function toolbox_metadata_operator_trial_explicit_post_ids( string $raw ): array {
	if ( '' === trim( $raw ) ) {
		return array();
	}

	$ids = array();
	foreach ( explode( ',', $raw ) as $value ) {
		$id = absint( trim( $value ) );
		if ( $id > 0 && get_post( $id ) ) {
			$ids[] = $id;
		}
	}

	return array_values( array_unique( $ids ) );
}

function toolbox_metadata_operator_trial_post_ids( int $limit, array $explicit_ids ): array {
	if ( ! empty( $explicit_ids ) ) {
		return array_slice( $explicit_ids, 0, $limit );
	}

	$ids = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => array( 'draft', 'publish', 'pending', 'future', 'private' ),
			'posts_per_page' => max( 1, min( 5, $limit ) ),
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'fields'         => 'ids',
		)
	);

	return array_values( array_map( 'absint', is_array( $ids ) ? $ids : array() ) );
}

function toolbox_metadata_operator_trial_post_snapshot( int $post_id ): array {
	$post = get_post( $post_id );
	if ( ! $post ) {
		return array();
	}

	return array(
		'post_title'    => (string) $post->post_title,
		'post_excerpt'  => (string) $post->post_excerpt,
		'post_content'  => (string) $post->post_content,
		'post_status'   => (string) $post->post_status,
		'post_modified' => (string) $post->post_modified,
		'category_ids'  => array_values( array_map( 'absint', wp_get_post_categories( $post_id ) ) ),
		'tag_ids'       => array_values( array_map( 'absint', wp_get_post_tags( $post_id, array( 'fields' => 'ids' ) ) ) ),
	);
}

function toolbox_metadata_operator_trial_rest( string $route, array $params ): array {
	wp_set_current_user( toolbox_metadata_operator_trial_admin_user_id() );

	$request = new WP_REST_Request( 'POST', $route );
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}

	$response = rest_do_request( $request );
	if ( is_wp_error( $response ) ) {
		toolbox_metadata_operator_trial_fail( 'REST dispatch returned WP_Error: ' . $response->get_error_code() );
	}

	$data = $response->get_data();
	if ( $response->get_status() < 200 || $response->get_status() >= 300 ) {
		$error = is_array( $data )
			? (string) ( $data['code'] ?? '' ) . ' ' . (string) ( $data['message'] ?? '' )
			: '';
		toolbox_metadata_operator_trial_fail( trim( 'REST dispatch failed for ' . $route . ' with HTTP ' . $response->get_status() . ' ' . $error ) );
	}

	return is_array( $data ) ? $data : array();
}

function toolbox_metadata_operator_trial_term_ids( array $items, string $taxonomy, int $limit ): array {
	$ids = array();
	foreach ( $items as $item ) {
		if ( ! is_array( $item ) || $taxonomy !== (string) ( $item['taxonomy'] ?? '' ) ) {
			continue;
		}
		$id = absint( $item['term_id'] ?? 0 );
		if ( $id > 0 ) {
			$ids[] = $id;
		}
	}

	return array_slice( array_values( array_unique( $ids ) ), 0, $limit );
}

function toolbox_metadata_operator_trial_markdown_text( string $value ): string {
	$value = preg_replace( '/\s+/u', ' ', $value ) ?? $value;
	return str_replace( array( '|', "\r", "\n" ), array( '\|', ' ', ' ' ), trim( $value ) );
}

function toolbox_metadata_operator_trial_write_json( string $path, array $payload ): void {
	if ( '' === $path ) {
		return;
	}

	$directory = dirname( $path );
	if ( ! is_dir( $directory ) && ! wp_mkdir_p( $directory ) ) {
		toolbox_metadata_operator_trial_fail( 'Unable to create output directory: ' . $directory );
	}

	$encoded = wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	if ( ! is_string( $encoded ) || false === file_put_contents( $path, $encoded . "\n" ) ) {
		toolbox_metadata_operator_trial_fail( 'Unable to write JSON output: ' . $path );
	}

	toolbox_metadata_operator_trial_pass( 'Wrote Content Metadata Delta trial JSON output.' );
}

function toolbox_metadata_operator_trial_write_markdown( string $path, array $payload ): void {
	if ( '' === $path ) {
		return;
	}

	$directory = dirname( $path );
	if ( ! is_dir( $directory ) && ! wp_mkdir_p( $directory ) ) {
		toolbox_metadata_operator_trial_fail( 'Unable to create output directory: ' . $directory );
	}

	$summary = is_array( $payload['summary'] ?? null ) ? $payload['summary'] : array();
	$lines   = array(
		'# Content Metadata Delta Operator Trial Cases',
		'',
		'- Contract: `' . (string) ( $payload['contract'] ?? '' ) . '`',
		'- Created: `' . (string) ( $payload['created_at'] ?? '' ) . '`',
		'- Write posture: `' . (string) ( $payload['write_posture'] ?? '' ) . '`',
		'- Cases: `' . (string) ( $summary['case_count'] ?? 0 ) . '`, target range: `' . (string) ( $summary['target_case_range'] ?? '' ) . '`, status: `' . (string) ( $summary['trial_status'] ?? '' ) . '`',
		'',
		'| Post | Status | Excerpt candidate | Category ids | Tag ids | Apply plan | Human outcome |',
		'| ---: | --- | --- | --- | --- | --- | --- |',
	);

	foreach ( (array) ( $payload['cases'] ?? array() ) as $case ) {
		if ( ! is_array( $case ) ) {
			continue;
		}
		$target = is_array( $case['target_post'] ?? null ) ? $case['target_post'] : array();
		$review = is_array( $case['review_decision'] ?? null ) ? $case['review_decision'] : array();
		$apply = is_array( $case['governance_evidence']['apply_plan'] ?? null ) ? $case['governance_evidence']['apply_plan'] : array();
		$lines[] = '| ' . (string) ( $target['id'] ?? '' )
			. ' | ' . toolbox_metadata_operator_trial_markdown_text( (string) ( $target['status'] ?? '' ) )
			. ' | ' . toolbox_metadata_operator_trial_markdown_text( (string) ( $review['excerpt']['candidate'] ?? '' ) )
			. ' | ' . toolbox_metadata_operator_trial_markdown_text( implode( ',', array_map( 'strval', (array) ( $review['categories']['accepted_ids'] ?? array() ) ) ) )
			. ' | ' . toolbox_metadata_operator_trial_markdown_text( implode( ',', array_map( 'strval', (array) ( $review['tags']['accepted_ids'] ?? array() ) ) ) )
			. ' | ' . toolbox_metadata_operator_trial_markdown_text( (string) ( $apply['artifact_type'] ?? '' ) )
			. ' | pending |';
	}

	$lines[] = '';
	$lines[] = 'Human outcome values should be accepted, edited, rejected, or not_applicable. This worksheet is local review evidence only; it is not a WordPress write authorization.';
	$lines[] = 'Accepted changes must remain Core proposal handoffs until a separate governance decision changes that boundary.';

	if ( false === file_put_contents( $path, implode( "\n", $lines ) . "\n" ) ) {
		toolbox_metadata_operator_trial_fail( 'Unable to write Markdown output: ' . $path );
	}

	toolbox_metadata_operator_trial_pass( 'Wrote Content Metadata Delta trial Markdown output.' );
}

function toolbox_metadata_operator_trial_case( int $post_id ): array {
	$post = get_post( $post_id );
	toolbox_metadata_operator_trial_assert( $post instanceof WP_Post, 'Found real post ' . $post_id . ' for metadata operator trial.' );

	$before       = toolbox_metadata_operator_trial_post_snapshot( $post_id );
	$category_ids = wp_get_post_categories( $post_id );
	$tag_ids      = wp_get_post_tags( $post_id, array( 'fields' => 'ids' ) );

	$result = toolbox_metadata_operator_trial_rest(
		'/npcink-toolbox/v1/editor/content-support',
		array(
			'intent'        => 'summary_terms_optimization',
			'post_id'       => $post_id,
			'post_type'     => get_post_type( $post_id ),
			'post_status'   => get_post_status( $post_id ),
			'title'         => get_the_title( $post_id ),
			'excerpt'       => wp_strip_all_tags( get_the_excerpt( $post_id ) ),
			'content'       => wp_strip_all_tags( (string) $post->post_content ),
			'category_ids'  => implode( ',', array_map( 'absint', is_array( $category_ids ) ? $category_ids : array() ) ),
			'tag_ids'       => implode( ',', array_map( 'absint', is_array( $tag_ids ) ? $tag_ids : array() ) ),
			'context_scope' => 'full_article',
		)
	);

	$section = is_array( $result['sections']['summary_terms_optimization'] ?? null ) ? $result['sections']['summary_terms_optimization'] : array();
	$delta   = is_array( $section['content_metadata_delta'] ?? null ) ? $section['content_metadata_delta'] : array();
	toolbox_metadata_operator_trial_assert( 'content_metadata_delta' === (string) ( $delta['artifact_type'] ?? '' ), 'Post ' . $post_id . ' returns a Content Metadata Delta artifact.' );
	toolbox_metadata_operator_trial_assert( 'suggestion_only' === (string) ( $delta['write_posture'] ?? '' ), 'Post ' . $post_id . ' metadata delta remains suggestion-only.' );
	toolbox_metadata_operator_trial_assert( false === (bool) ( $delta['direct_wordpress_write'] ?? true ), 'Post ' . $post_id . ' metadata delta disables direct WordPress writes.' );

	$delta_body    = is_array( $delta['delta'] ?? null ) ? $delta['delta'] : array();
	$excerpt_delta = is_array( $delta_body['excerpt'] ?? null ) ? $delta_body['excerpt'] : array();
	$category_pick = toolbox_metadata_operator_trial_term_ids( is_array( $delta_body['categories'] ?? null ) ? $delta_body['categories'] : array(), 'category', 2 );
	$tag_pick      = toolbox_metadata_operator_trial_term_ids( is_array( $delta_body['tags'] ?? null ) ? $delta_body['tags'] : array(), 'post_tag', 5 );
	$excerpt       = sanitize_text_field( (string) ( $excerpt_delta['recommended'] ?? '' ) );
	if ( '' === $excerpt ) {
		$excerpt = sanitize_text_field( wp_trim_words( wp_strip_all_tags( get_the_title( $post_id ) . ' ' . (string) $post->post_content ), 28, '' ) );
	}

	$apply_plan = toolbox_metadata_operator_trial_rest(
		'/npcink-toolbox/v1/flows/content-metadata-apply-plan',
		array(
			'post_id'                => $post_id,
			'excerpt'                => $excerpt,
			'category_ids'           => $category_pick,
			'tag_ids'                => $tag_pick,
			'content_metadata_delta' => $delta,
			'evidence_refs'          => array(
				array(
					'id'      => 'metadata-operator-trial-post-' . $post_id,
					'type'    => 'target_post',
					'post_id' => $post_id,
				),
			),
			'new_term_candidates'    => array(),
		)
	);
	toolbox_metadata_operator_trial_assert( 'content_metadata_apply_plan' === (string) ( $apply_plan['artifact_type'] ?? '' ), 'Post ' . $post_id . ' builds a governed content metadata apply plan.' );
	toolbox_metadata_operator_trial_assert( false === (bool) ( $apply_plan['direct_wordpress_write'] ?? true ), 'Post ' . $post_id . ' apply plan disables direct WordPress writes.' );
	toolbox_metadata_operator_trial_assert( true === (bool) ( $apply_plan['dry_run'] ?? false ) && false === (bool) ( $apply_plan['commit_execution'] ?? true ), 'Post ' . $post_id . ' apply plan stays dry-run and non-commit.' );
	toolbox_metadata_operator_trial_assert( 'core_proposal_required' === (string) ( $apply_plan['authorization']['classification'] ?? '' ), 'Post ' . $post_id . ' apply plan requires Core proposal review.' );

	$after = toolbox_metadata_operator_trial_post_snapshot( $post_id );
	toolbox_metadata_operator_trial_assert( $before === $after, 'Post ' . $post_id . ' metadata snapshot is unchanged.' );

	return array(
		'case_id'             => 'post:' . $post_id,
		'target_post'         => array(
			'id'     => $post_id,
			'type'   => sanitize_key( (string) get_post_type( $post_id ) ),
			'status' => sanitize_key( (string) get_post_status( $post_id ) ),
			'title'  => sanitize_text_field( get_the_title( $post_id ) ),
		),
		'issue_record'        => is_array( $delta['issue_record'] ?? null ) ? $delta['issue_record'] : array(),
		'diagnosis'           => is_array( $delta['diagnosis'] ?? null ) ? $delta['diagnosis'] : array(),
		'delta'               => $delta_body,
		'review_decision'     => array(
			'excerpt'    => array(
				'outcome'   => '' !== $excerpt ? 'pending_operator_review' : 'not_applicable',
				'candidate' => $excerpt,
				'notes'     => '',
			),
			'categories' => array(
				'outcome'      => ! empty( $category_pick ) ? 'pending_operator_review' : 'not_applicable',
				'accepted_ids' => $category_pick,
				'notes'        => 'Existing categories only; no taxonomy creation.',
			),
			'tags'       => array(
				'outcome'      => ! empty( $tag_pick ) ? 'pending_operator_review' : 'not_applicable',
				'accepted_ids' => $tag_pick,
				'notes'        => 'Existing tags only; no taxonomy creation.',
			),
		),
		'governance_evidence' => array(
			'write_posture'       => 'eval_only_no_wordpress_write',
			'final_write_path'    => 'core_proposal_required',
			'proposal_created'    => false,
			'execution_created'   => false,
			'readback_status'     => 'unchanged_snapshot_verified',
			'apply_plan'          => array(
				'artifact_type'      => (string) ( $apply_plan['artifact_type'] ?? '' ),
				'plan_ability_id'    => (string) ( $apply_plan['handoff']['plan_ability_id'] ?? '' ),
				'classification'     => (string) ( $apply_plan['authorization']['classification'] ?? '' ),
				'write_action_count' => count( (array) ( $apply_plan['write_actions'] ?? array() ) ),
				'dry_run'            => (bool) ( $apply_plan['dry_run'] ?? false ),
				'commit_execution'   => (bool) ( $apply_plan['commit_execution'] ?? true ),
			),
		),
		'outcome_contract'    => is_array( $delta['outcome_contract'] ?? null ) ? $delta['outcome_contract'] : array(),
		'learning_entry'      => array(
			'status'              => 'pending_human_outcome',
			'learning_candidates' => array_values( array_map( 'sanitize_key', (array) ( $delta['learning_candidates'] ?? array() ) ) ),
			'feedback_fields'     => array( 'excerpt_outcome', 'category_outcome', 'tag_outcome', 'operator_notes' ),
		),
	);
}

$arg_map       = toolbox_metadata_operator_trial_arg_map( isset( $args ) && is_array( $args ) ? $args : array() );
$admin_user_id = toolbox_metadata_operator_trial_admin_user_id();
toolbox_metadata_operator_trial_assert( $admin_user_id > 0, 'Found an administrator user for the Content Metadata Delta operator trial.' );
wp_set_current_user( $admin_user_id );

$limit        = max( 1, min( 5, absint( $arg_map['max_posts'] ?? ( getenv( 'NPCINK_TOOLBOX_METADATA_TRIAL_MAX_POSTS' ) ?: 3 ) ) ) );
$explicit_ids = toolbox_metadata_operator_trial_explicit_post_ids( (string) ( $arg_map['post_ids'] ?? ( getenv( 'CONTENT_METADATA_TRIAL_POST_IDS' ) ?: '' ) ) );
$post_ids     = toolbox_metadata_operator_trial_post_ids( $limit, $explicit_ids );
toolbox_metadata_operator_trial_assert( ! empty( $post_ids ), 'Found real posts for the Content Metadata Delta operator trial.' );

$cases = array();
foreach ( $post_ids as $post_id ) {
	$cases[] = toolbox_metadata_operator_trial_case( absint( $post_id ) );
}

$case_count   = count( $cases );
$trial_status = $case_count >= 3 ? 'ready_for_3_to_5_case_review' : 'sample_below_target_case_count';
$payload      = array(
	'version'       => 1,
	'type'          => 'content_metadata_delta_operator_trial',
	'contract'      => 'content_metadata_delta_operator_trial.v1',
	'created_at'    => gmdate( 'c' ),
	'write_posture' => 'eval_only_no_wordpress_write',
	'scope'         => 'single_post_cases_exported_for_operator_review',
	'summary'       => array(
		'case_count'        => $case_count,
		'target_case_range' => '3-5',
		'trial_status'      => $trial_status,
		'post_ids'          => array_values( array_map( 'absint', $post_ids ) ),
		'core_handoff_path' => 'npcink-abilities-toolkit/build-content-metadata-apply-plan -> Core proposals/from-plan',
		'eval_lab_role'     => 'optional_ai_assisted_review_evidence_only',
	),
	'safety'        => array(
		'direct_wordpress_write' => false,
		'proposal_created'       => false,
		'execution_created'      => false,
		'batch_execution'        => false,
		'new_term_creation'      => false,
		'automatic_publish'      => false,
	),
	'cases'         => $cases,
);

$output_json = (string) ( $arg_map['output_json'] ?? ( getenv( 'NPCINK_TOOLBOX_METADATA_TRIAL_OUTPUT_JSON' ) ?: '' ) );
$output_md   = (string) ( $arg_map['output_md'] ?? ( getenv( 'NPCINK_TOOLBOX_METADATA_TRIAL_OUTPUT_MD' ) ?: '' ) );
toolbox_metadata_operator_trial_write_json( $output_json, $payload );
toolbox_metadata_operator_trial_write_markdown( $output_md, $payload );

echo 'INFO: Content Metadata Delta operator trial summary=' . wp_json_encode( $payload['summary'] ) . PHP_EOL;
echo "Content Metadata Delta operator trial passed.\n";
