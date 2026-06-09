<?php
/**
 * Local WordPress smoke for the editor Content Metadata Delta artifact.
 *
 * Run with WP-CLI:
 * wp eval-file tests/smoke-content-metadata-delta.php -- [post_id]
 *
 * @package Npcink_Toolbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "FAIL: Run this script through WP-CLI eval-file so WordPress is loaded.\n" );
	exit( 1 );
}

$toolbox_metadata_delta_smoke_proposal_ids = array();

function toolbox_metadata_delta_smoke_pass( string $message ): void {
	echo "PASS: {$message}\n";
}

function toolbox_metadata_delta_smoke_info( string $message ): void {
	echo "INFO: {$message}\n";
}

function toolbox_metadata_delta_smoke_fail( string $message ): void {
	fwrite( STDERR, "FAIL: {$message}\n" );
	toolbox_metadata_delta_smoke_purge_governance_records();
	exit( 1 );
}

function toolbox_metadata_delta_smoke_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		toolbox_metadata_delta_smoke_fail( $message );
	}

	toolbox_metadata_delta_smoke_pass( $message );
}

function toolbox_metadata_delta_smoke_admin_user_id(): int {
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

function toolbox_metadata_delta_smoke_track_rest_fixture( string $route, $data ): void {
	global $toolbox_metadata_delta_smoke_proposal_ids;

	if ( '/npcink-governance-core/v1/proposals/from-plan' !== $route || ! is_array( $data ) ) {
		return;
	}

	foreach ( (array) ( $data['proposals'] ?? array() ) as $proposal ) {
		if ( ! is_array( $proposal ) ) {
			continue;
		}

		$proposal_id = trim( (string) ( $proposal['proposal_id'] ?? '' ) );
		if ( '' !== $proposal_id ) {
			$toolbox_metadata_delta_smoke_proposal_ids[ $proposal_id ] = true;
		}
	}
}

function toolbox_metadata_delta_smoke_should_purge_governance_records(): bool {
	$value = getenv( 'NPCINK_TOOLBOX_METADATA_DELTA_SMOKE_PURGE' );
	if ( ! is_string( $value ) || '' === trim( $value ) ) {
		return true;
	}

	return in_array( strtolower( trim( $value ) ), array( '1', 'true', 'yes' ), true );
}

function toolbox_metadata_delta_smoke_purge_governance_records(): void {
	global $wpdb, $toolbox_metadata_delta_smoke_proposal_ids;

	if ( ! toolbox_metadata_delta_smoke_should_purge_governance_records() ) {
		return;
	}

	$proposal_ids = array_keys( is_array( $toolbox_metadata_delta_smoke_proposal_ids ) ? $toolbox_metadata_delta_smoke_proposal_ids : array() );
	if ( empty( $proposal_ids ) ) {
		return;
	}

	$audit_table    = $wpdb->prefix . 'npcink_governance_core_audit_log';
	$proposal_table = $wpdb->prefix . 'npcink_governance_core_proposals';

	foreach ( $proposal_ids as $proposal_id ) {
		$proposal_id = sanitize_text_field( $proposal_id );
		$wpdb->delete( $audit_table, array( 'proposal_id' => $proposal_id ), array( '%s' ) );
		$wpdb->delete( $proposal_table, array( 'proposal_id' => $proposal_id ), array( '%s' ) );
	}

	toolbox_metadata_delta_smoke_info( 'Purged Core proposal fixtures: ' . count( $proposal_ids ) );
}

function toolbox_metadata_delta_smoke_find_sample_post_id( array $script_args ): int {
	$requested = absint( $script_args[0] ?? 0 );
	if ( $requested > 0 && get_post( $requested ) ) {
		return $requested;
	}

	$posts = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => array( 'draft', 'publish', 'pending', 'future', 'private' ),
			'posts_per_page' => 1,
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'fields'         => 'ids',
		)
	);

	return absint( $posts[0] ?? 0 );
}

function toolbox_metadata_delta_smoke_post_count(): int {
	$counts = wp_count_posts( 'post' );
	$total  = 0;

	foreach ( get_object_vars( $counts ) as $count ) {
		$total += absint( $count );
	}

	return $total;
}

function toolbox_metadata_delta_smoke_post_snapshot( int $post_id ): array {
	$post = get_post( $post_id );
	if ( ! $post ) {
		return array();
	}

	return array(
		'post_title'   => (string) $post->post_title,
		'post_excerpt' => (string) $post->post_excerpt,
		'post_content' => (string) $post->post_content,
		'post_status'  => (string) $post->post_status,
		'post_modified' => (string) $post->post_modified,
	);
}

function toolbox_metadata_delta_smoke_rest( string $route, array $params ): array {
	wp_set_current_user( toolbox_metadata_delta_smoke_admin_user_id() );

	$request = new WP_REST_Request( 'POST', $route );
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}

	$response = rest_do_request( $request );
	if ( is_wp_error( $response ) ) {
		toolbox_metadata_delta_smoke_fail( 'REST dispatch returned WP_Error: ' . $response->get_error_code() );
	}

	$data = $response->get_data();
	toolbox_metadata_delta_smoke_track_rest_fixture( $route, $data );
	toolbox_metadata_delta_smoke_assert( $response->get_status() >= 200 && $response->get_status() < 300, 'REST dispatch succeeds for ' . $route . '.' );

	return is_array( $data ) ? $data : array();
}

$script_args = isset( $args ) && is_array( $args ) ? $args : array();

toolbox_metadata_delta_smoke_assert( class_exists( 'WP_REST_Request' ) && function_exists( 'rest_do_request' ), 'WordPress REST dispatch is available.' );
toolbox_metadata_delta_smoke_assert( toolbox_metadata_delta_smoke_admin_user_id() > 0, 'A local administrator is available for REST permission checks.' );

$sample_post_id = toolbox_metadata_delta_smoke_find_sample_post_id( $script_args );
toolbox_metadata_delta_smoke_assert( $sample_post_id > 0, 'A local post is available for the metadata delta smoke.' );

$post        = get_post( $sample_post_id );
$before      = toolbox_metadata_delta_smoke_post_snapshot( $sample_post_id );
$post_count  = toolbox_metadata_delta_smoke_post_count();
$category_ids = wp_get_post_categories( $sample_post_id );
$tag_ids      = wp_get_post_tags( $sample_post_id, array( 'fields' => 'ids' ) );

$result = toolbox_metadata_delta_smoke_rest(
	'/npcink-toolbox/v1/editor/content-support',
	array(
		'intent'        => 'summary_terms_optimization',
		'post_id'       => $sample_post_id,
		'post_type'     => get_post_type( $sample_post_id ),
		'post_status'   => get_post_status( $sample_post_id ),
		'title'         => get_the_title( $sample_post_id ),
		'excerpt'       => wp_strip_all_tags( get_the_excerpt( $sample_post_id ) ),
		'content'       => wp_strip_all_tags( (string) $post->post_content ),
		'category_ids'  => implode( ',', array_map( 'absint', is_array( $category_ids ) ? $category_ids : array() ) ),
		'tag_ids'       => implode( ',', array_map( 'absint', is_array( $tag_ids ) ? $tag_ids : array() ) ),
		'context_scope' => 'full_article',
	)
);

$section = is_array( $result['sections']['summary_terms_optimization'] ?? null )
	? $result['sections']['summary_terms_optimization']
	: array();
$delta   = is_array( $section['content_metadata_delta'] ?? null )
	? $section['content_metadata_delta']
	: array();
$auth    = is_array( $delta['authorization'] ?? null ) ? $delta['authorization'] : array();
$checks  = is_array( $delta['outcome_contract']['checks'] ?? null ) ? $delta['outcome_contract']['checks'] : array();
$taxonomy_ranking = is_array( $section['taxonomy_terms']['ranking_context'] ?? null ) ? $section['taxonomy_terms']['ranking_context'] : array();
$summary_context  = is_array( $section['summary_layers']['related_context_summary'] ?? null ) ? $section['summary_layers']['related_context_summary'] : array();

toolbox_metadata_delta_smoke_assert( 'editor_content_support_flow' === (string) ( $result['artifact_type'] ?? '' ), 'REST result declares editor_content_support_flow.' );
toolbox_metadata_delta_smoke_assert( 'summary_terms_optimization' === (string) ( $result['intent'] ?? '' ), 'REST result preserves the summary_terms_optimization intent.' );
toolbox_metadata_delta_smoke_assert( false === (bool) ( $result['direct_wordpress_write'] ?? true ), 'Editor content-support flow disables direct WordPress writes.' );
toolbox_metadata_delta_smoke_assert( 'article_discoverability_optimization.v1' === (string) ( $section['artifact_type'] ?? '' ), 'Summary terms section declares the optimization artifact.' );
toolbox_metadata_delta_smoke_assert( false === (bool) ( $section['direct_wordpress_write'] ?? true ), 'Summary terms section disables direct WordPress writes.' );
toolbox_metadata_delta_smoke_assert( 'content_metadata_delta' === (string) ( $delta['artifact_type'] ?? '' ), 'Content Metadata Delta artifact is returned.' );
toolbox_metadata_delta_smoke_assert( $sample_post_id === absint( $delta['target_post_id'] ?? 0 ), 'Content Metadata Delta targets the current post.' );
toolbox_metadata_delta_smoke_assert( 'suggestion_only' === (string) ( $delta['write_posture'] ?? '' ), 'Content Metadata Delta is suggestion-only.' );
toolbox_metadata_delta_smoke_assert( 'core_proposal_required' === (string) ( $delta['final_write_path'] ?? '' ), 'Content Metadata Delta points final writes to Core proposals.' );
toolbox_metadata_delta_smoke_assert( false === (bool) ( $delta['direct_wordpress_write'] ?? true ), 'Content Metadata Delta disables direct WordPress writes.' );
toolbox_metadata_delta_smoke_assert( is_array( $delta['issue_record'] ?? null ) && is_array( $delta['diagnosis'] ?? null ) && is_array( $delta['delta'] ?? null ), 'Content Metadata Delta includes issue, diagnosis, and delta sections.' );
toolbox_metadata_delta_smoke_assert( 'ranking_evidence_only_no_term_creation_or_assignment' === (string) ( $taxonomy_ranking['related_term_policy'] ?? '' ), 'Taxonomy ranking treats related-content terms as ranking evidence only.' );
toolbox_metadata_delta_smoke_assert( 'related_context_checks_duplicate_coverage_and_term_fit_without_adding_new_facts' === (string) ( $summary_context['policy'] ?? '' ), 'Summary layers keep related context as duplicate/term-fit evidence without adding facts.' );
toolbox_metadata_delta_smoke_assert( 'suggestion_only' === (string) ( $auth['classification'] ?? '' ), 'Authorization classification is suggestion_only.' );
toolbox_metadata_delta_smoke_assert( 'operation-classification-v1' === (string) ( $auth['policy_version'] ?? '' ), 'Authorization records the operation classification policy version.' );
toolbox_metadata_delta_smoke_assert( in_array( 'core_proposal_required_for_external_batch_new_term_or_incomplete_preview', (array) ( $auth['required_evidence'] ?? array() ), true ), 'Authorization documents the stricter Core proposal path.' );
toolbox_metadata_delta_smoke_assert( in_array( 'no_toolbox_direct_wordpress_write', $checks, true ), 'Outcome contract forbids Toolbox direct writes.' );
toolbox_metadata_delta_smoke_assert( in_array( 'related_content_terms_used_for_ranking_only', $checks, true ), 'Outcome contract keeps related terms as ranking evidence only.' );
toolbox_metadata_delta_smoke_assert( in_array( 'accepted_write_like_changes_route_through_core_or_future_classified_local_consent', $checks, true ), 'Outcome contract keeps accepted write-like changes on governed paths.' );

$reviewed_excerpt = wp_trim_words( wp_strip_all_tags( get_the_title( $sample_post_id ) . ' metadata smoke reviewed excerpt' ), 28, '' );
$apply_plan = toolbox_metadata_delta_smoke_rest(
	'/npcink-toolbox/v1/flows/content-metadata-apply-plan',
	array(
		'post_id'                => $sample_post_id,
		'excerpt'                => $reviewed_excerpt,
		'category_ids'           => is_array( $category_ids ) ? $category_ids : array(),
		'tag_ids'                => is_array( $tag_ids ) ? $tag_ids : array(),
		'content_metadata_delta' => $delta,
		'evidence_refs'          => array(
			array(
				'id'     => 'metadata-smoke-target-post',
				'type'   => 'target_post',
				'post_id' => $sample_post_id,
			),
		),
		'new_term_candidates'    => array(
			array(
				'name'   => 'Metadata Smoke New Term',
				'status' => 'review_only_vocabulary_gap',
			),
		),
	)
);
$apply_actions = is_array( $apply_plan['write_actions'] ?? null ) ? array_values( $apply_plan['write_actions'] ) : array();
toolbox_metadata_delta_smoke_assert( 'content_metadata_apply_plan' === (string) ( $apply_plan['artifact_type'] ?? '' ), 'Content metadata apply plan artifact is returned.' );
toolbox_metadata_delta_smoke_assert( false === (bool) ( $apply_plan['direct_wordpress_write'] ?? true ), 'Content metadata apply plan disables direct WordPress writes.' );
toolbox_metadata_delta_smoke_assert( true === (bool) ( $apply_plan['dry_run'] ?? false ) && false === (bool) ( $apply_plan['commit_execution'] ?? true ), 'Content metadata apply plan remains dry-run without commit execution.' );
toolbox_metadata_delta_smoke_assert( 'batch' === (string) ( $apply_plan['proposal_mode'] ?? '' ) && true === (bool) ( $apply_plan['batch_approval'] ?? false ), 'Content metadata apply plan requests one Core batch approval.' );
toolbox_metadata_delta_smoke_assert( ! empty( $apply_actions ), 'Content metadata apply plan contains reviewed write actions.' );
toolbox_metadata_delta_smoke_assert( 'npcink-toolbox/build-content-metadata-apply-plan' === (string) ( $apply_plan['handoff']['plan_ability_id'] ?? '' ), 'Content metadata apply plan points to its Core handoff ability.' );
toolbox_metadata_delta_smoke_assert( ! empty( $apply_plan['manual_review'] ?? array() ), 'Content metadata apply plan keeps new terms as manual-review notes.' );

$has_excerpt_action = false;
foreach ( $apply_actions as $action ) {
	$target = (string) ( is_array( $action ) ? ( $action['target_ability_id'] ?? '' ) : '' );
	$input  = is_array( $action['input'] ?? null ) ? $action['input'] : array();
	toolbox_metadata_delta_smoke_assert( true === (bool) ( $input['dry_run'] ?? false ) && false === (bool) ( $input['commit'] ?? true ), 'Every content metadata apply action is dry-run and non-commit.' );
	toolbox_metadata_delta_smoke_assert( false === strpos( $target, 'create-term' ), 'Content metadata apply plan does not include term creation actions.' );
	if ( 'npcink-abilities-toolkit/update-post' === $target ) {
		$has_excerpt_action = ! empty( $input['excerpt'] ?? '' );
	}
	if ( 'npcink-abilities-toolkit/set-post-terms' === $target ) {
		toolbox_metadata_delta_smoke_assert( false === (bool) ( $input['create_missing'] ?? true ), 'Term assignment actions reject create_missing.' );
		toolbox_metadata_delta_smoke_assert( in_array( (string) ( $input['taxonomy'] ?? '' ), array( 'category', 'post_tag' ), true ), 'Term assignment actions target only categories or post tags.' );
	}
}
toolbox_metadata_delta_smoke_assert( $has_excerpt_action, 'Content metadata apply plan can route a reviewed excerpt through update-post.' );

$created = toolbox_metadata_delta_smoke_rest(
	'/npcink-governance-core/v1/proposals/from-plan',
	array(
		'plan_ability_id' => 'npcink-toolbox/build-content-metadata-apply-plan',
		'plan'            => $apply_plan,
		'plan_input'      => array(
			'post_id'      => $sample_post_id,
			'excerpt'      => $reviewed_excerpt,
			'category_ids' => is_array( $category_ids ) ? $category_ids : array(),
			'tag_ids'      => is_array( $tag_ids ) ? $tag_ids : array(),
		),
		'caller'          => array(
			'source' => 'tests/smoke-content-metadata-delta.php',
		),
	)
);

toolbox_metadata_delta_smoke_assert( 'npcink-toolbox/build-content-metadata-apply-plan' === (string) ( $created['plan_ability_id'] ?? '' ), 'Core response records the metadata planning ability.' );
toolbox_metadata_delta_smoke_assert( 1 === (int) ( $created['proposal_count'] ?? 0 ), 'Core creates one batch proposal from the metadata apply plan.' );
toolbox_metadata_delta_smoke_assert( count( $apply_actions ) === (int) ( $created['action_count'] ?? 0 ), 'Core response preserves the metadata action count.' );
toolbox_metadata_delta_smoke_assert( false === (bool) ( $created['commit_execution'] ?? true ), 'Core metadata from-plan intake remains non-commit.' );

$proposals      = is_array( $created['proposals'] ?? null ) ? $created['proposals'] : array();
$proposal       = is_array( $proposals[0] ?? null ) ? $proposals[0] : array();
$proposal_input = is_array( $proposal['input'] ?? null ) ? $proposal['input'] : array();
$proposal_preview = is_array( $proposal['preview'] ?? null ) ? $proposal['preview'] : array();

toolbox_metadata_delta_smoke_assert( 'pending' === (string) ( $proposal['status'] ?? '' ), 'Core metadata proposal starts pending approval.' );
toolbox_metadata_delta_smoke_assert( 'plan_to_proposal_batch' === (string) ( $proposal_preview['source']['type'] ?? '' ), 'Core metadata proposal stores a batch source preview.' );
toolbox_metadata_delta_smoke_assert( isset( $proposal_preview['content_metadata_apply'] ), 'Core metadata proposal preserves content_metadata_apply review evidence.' );
toolbox_metadata_delta_smoke_assert( count( $apply_actions ) === count( (array) ( $proposal_input['write_actions'] ?? array() ) ), 'Core metadata proposal input stores all reviewed write actions.' );
toolbox_metadata_delta_smoke_assert( true === (bool) ( $proposal_input['dry_run'] ?? false ) && false === (bool) ( $proposal_input['commit'] ?? true ), 'Core metadata proposal input remains dry-run and non-commit.' );
toolbox_metadata_delta_smoke_assert( false === (bool) ( $proposal_preview['commit_execution'] ?? true ), 'Core metadata proposal preview disables Core execution.' );

$after = toolbox_metadata_delta_smoke_post_snapshot( $sample_post_id );
toolbox_metadata_delta_smoke_assert( $post_count === toolbox_metadata_delta_smoke_post_count(), 'Metadata delta smoke does not create or delete posts.' );
toolbox_metadata_delta_smoke_assert( $before === $after, 'Metadata delta smoke does not mutate the sampled post.' );

toolbox_metadata_delta_smoke_purge_governance_records();
echo "Content Metadata Delta smoke passed.\n";
