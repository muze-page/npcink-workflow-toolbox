<?php
/**
 * Local WordPress smoke for the Toolbox article_draft_v1 to Core proposal handoff.
 *
 * Run with WP-CLI:
 * wp eval-file tests/smoke-article-draft-core-proof.php
 *
 * @package Npcink_Toolbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "FAIL: Run this script through WP-CLI eval-file so WordPress is loaded.\n" );
	exit( 1 );
}

$GLOBALS['toolbox_article_core_smoke_proposal_ids'] = array();
$GLOBALS['toolbox_article_core_smoke_post_title']   = '';

function toolbox_article_core_smoke_pass( string $message ): void {
	echo "PASS: {$message}\n";
}

function toolbox_article_core_smoke_info( string $message ): void {
	echo "INFO: {$message}\n";
}

function toolbox_article_core_smoke_fail( string $message ): void {
	fwrite( STDERR, "FAIL: {$message}\n" );
	toolbox_article_core_smoke_purge_post_fixture();
	toolbox_article_core_smoke_purge_governance_records();
	exit( 1 );
}

function toolbox_article_core_smoke_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		toolbox_article_core_smoke_fail( $message );
	}

	toolbox_article_core_smoke_pass( $message );
}

function toolbox_article_core_smoke_admin_user_id(): int {
	$admins = get_users(
		array(
			'role'   => 'administrator',
			'number' => 1,
			'fields' => 'ids',
		)
	);

	return absint( $admins[0] ?? 1 );
}

function toolbox_article_core_smoke_track_rest_fixture( string $method, string $route, $data ): void {
	if ( 'POST' !== strtoupper( $method ) || ! in_array( $route, array( '/npcink-governance-core/v1/proposals/from-plan', '/npcink-openclaw-adapter/v1/proposals/from-plan' ), true ) || ! is_array( $data ) ) {
		return;
	}

	foreach ( (array) ( $data['proposals'] ?? array() ) as $proposal ) {
		if ( ! is_array( $proposal ) ) {
			continue;
		}

		$proposal_id = trim( (string) ( $proposal['proposal_id'] ?? '' ) );
		if ( '' !== $proposal_id ) {
			$GLOBALS['toolbox_article_core_smoke_proposal_ids'][ $proposal_id ] = true;
		}
	}
}

function toolbox_article_core_smoke_should_execute(): bool {
	$value = getenv( 'NPCINK_TOOLBOX_ARTICLE_CORE_SMOKE_EXECUTE' );

	return is_string( $value ) && in_array( strtolower( trim( $value ) ), array( '1', 'true', 'yes' ), true );
}

function toolbox_article_core_smoke_purge_post_fixture(): void {
	$title = (string) ( $GLOBALS['toolbox_article_core_smoke_post_title'] ?? '' );
	if ( '' === $title ) {
		return;
	}

	$post_ids = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => 'any',
			'title'          => $title,
			'fields'         => 'ids',
			'posts_per_page' => 5,
		)
	);
	foreach ( $post_ids as $post_id ) {
		wp_delete_post( absint( $post_id ), true );
	}
}

function toolbox_article_core_smoke_should_purge_governance_records(): bool {
	$value = getenv( 'NPCINK_TOOLBOX_ARTICLE_CORE_SMOKE_PURGE' );
	if ( ! is_string( $value ) || '' === trim( $value ) ) {
		return true;
	}

	return in_array( strtolower( trim( $value ) ), array( '1', 'true', 'yes' ), true );
}

function toolbox_article_core_smoke_purge_governance_records(): void {
	global $wpdb;

	if ( ! toolbox_article_core_smoke_should_purge_governance_records() ) {
		return;
	}

	$proposal_ids = array_keys( (array) ( $GLOBALS['toolbox_article_core_smoke_proposal_ids'] ?? array() ) );
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

	toolbox_article_core_smoke_info( 'Purged Core proposal fixtures: ' . count( $proposal_ids ) );
}

function toolbox_article_core_smoke_rest_result( string $method, string $route, array $params = array() ): array {
	wp_set_current_user( toolbox_article_core_smoke_admin_user_id() );

	$request = new WP_REST_Request( $method, $route );
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}

	$response = rest_do_request( $request );
	$status   = (int) $response->get_status();
	$data     = $response->get_data();
	toolbox_article_core_smoke_track_rest_fixture( $method, $route, $data );

	return array(
		'status' => $status,
		'data'   => $data,
	);
}

function toolbox_article_core_smoke_rest( string $method, string $route, array $params = array() ): array {
	$result = toolbox_article_core_smoke_rest_result( $method, $route, $params );

	toolbox_article_core_smoke_assert(
		$result['status'] >= 200 && $result['status'] < 300,
		$method . ' ' . $route . ' returned HTTP ' . $result['status']
	);

	return is_array( $result['data'] ) ? $result['data'] : array();
}

function toolbox_article_core_smoke_post_title_count( string $title ): int {
	global $wpdb;

	return (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_title = %s",
			$title
		)
	);
}

toolbox_article_core_smoke_assert( class_exists( 'WP_REST_Request' ) && function_exists( 'rest_do_request' ), 'WordPress REST dispatch is available.' );
toolbox_article_core_smoke_assert( class_exists( 'WP_Abilities_Registry' ), 'WordPress Abilities registry is available.' );

$registry = WP_Abilities_Registry::get_instance();
foreach ( array( 'npcink-toolbox/build-article-write-plan', 'npcink-abilities-toolkit/create-draft' ) as $ability_id ) {
	toolbox_article_core_smoke_assert( null !== $registry->get_registered( $ability_id ), "{$ability_id} is registered." );
}

$run_id   = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'article_core_', true );
$title    = 'Toolbox Core Article Draft Smoke ' . substr( md5( $run_id ), 0, 8 );
$GLOBALS['toolbox_article_core_smoke_post_title'] = $title;
$content  = "## Governed draft smoke\n\nThis reviewed draft is used only for the local Toolbox/Core smoke test.\n\nIt should become a Core pending proposal for a draft post, not a direct WordPress post.";
$plan_input = array(
	'title'            => $title,
	'topic'            => 'Toolbox Core article draft handoff smoke',
	'content_markdown' => $content,
	'excerpt'          => 'Local smoke proof for the Toolbox article_draft_v1 Core handoff.',
	'risk_level'       => 'low',
);

$before_count = toolbox_article_core_smoke_post_title_count( $title );

$plan = toolbox_article_core_smoke_rest( 'POST', '/npcink-toolbox/v1/flows/article-plan', $plan_input );
toolbox_article_core_smoke_assert( 'article_write_plan' === (string) ( $plan['artifact_type'] ?? '' ), 'Toolbox returns an article_write_plan artifact.' );
toolbox_article_core_smoke_assert( 'article_draft_v1' === (string) ( $plan['source_recipe_id'] ?? '' ), 'Toolbox plan uses article_draft_v1.' );
toolbox_article_core_smoke_assert( false === (bool) ( $plan['direct_wordpress_write'] ?? true ), 'Toolbox plan disables direct WordPress writes.' );
toolbox_article_core_smoke_assert( false === (bool) ( $plan['commit_execution'] ?? true ), 'Toolbox plan does not claim commit execution.' );
toolbox_article_core_smoke_assert( true === (bool) ( $plan['requires_approval'] ?? false ), 'Toolbox plan requires approval.' );
toolbox_article_core_smoke_assert( '/wp-json/npcink-governance-core/v1/proposals/from-plan' === (string) ( $plan['handoff']['core_route'] ?? '' ), 'Toolbox plan points to Core from-plan intake.' );

$write_actions = is_array( $plan['write_actions'] ?? null ) ? $plan['write_actions'] : array();
toolbox_article_core_smoke_assert( 1 === count( $write_actions ), 'Toolbox plan contains one write action.' );
$action = is_array( $write_actions[0] ?? null ) ? $write_actions[0] : array();
$input  = is_array( $action['input'] ?? null ) ? $action['input'] : array();
toolbox_article_core_smoke_assert( 'npcink-abilities-toolkit/create-draft' === (string) ( $action['target_ability_id'] ?? '' ), 'Toolbox write action targets the governed create-draft ability.' );
toolbox_article_core_smoke_assert( 'host_governed_create_draft' === (string) ( $action['recipe_step'] ?? '' ), 'Toolbox write action marks the host-governed recipe step.' );
toolbox_article_core_smoke_assert( 'draft' === (string) ( $input['status'] ?? '' ), 'Toolbox write action is draft-only.' );
toolbox_article_core_smoke_assert( 'markdown' === (string) ( $input['content_format'] ?? '' ), 'Toolbox declares the reviewed section payload as markdown for safe WordPress conversion.' );
toolbox_article_core_smoke_assert( true === (bool) ( $input['dry_run'] ?? false ) && false === (bool) ( $input['commit'] ?? true ), 'Toolbox write action input is dry-run and non-commit.' );
toolbox_article_core_smoke_assert( true === (bool) ( $action['proposal_ready'] ?? false ), 'Toolbox write action is proposal-ready for the reviewed draft.' );

$created = toolbox_article_core_smoke_rest(
	'POST',
	'/npcink-openclaw-adapter/v1/proposals/from-plan',
	array(
		'plan_ability_id' => 'npcink-toolbox/build-article-write-plan',
		'plan'            => $plan,
		'plan_input'      => $plan_input,
	)
);

toolbox_article_core_smoke_assert( 'npcink-toolbox/build-article-write-plan' === (string) ( $created['plan_ability_id'] ?? '' ), 'Adapter/Core response records the Toolbox planning ability.' );
toolbox_article_core_smoke_assert( 1 === (int) ( $created['proposal_count'] ?? 0 ), 'Core creates one proposal from the article plan.' );
toolbox_article_core_smoke_assert( false === (bool) ( $created['commit_execution'] ?? true ), 'Core from-plan intake remains non-commit.' );

$proposals = is_array( $created['proposals'] ?? null ) ? $created['proposals'] : array();
$proposal  = is_array( $proposals[0] ?? null ) ? $proposals[0] : array();
$preview   = is_array( $proposal['preview'] ?? null ) ? $proposal['preview'] : array();
$proposal_input = is_array( $proposal['input'] ?? null ) ? $proposal['input'] : array();

toolbox_article_core_smoke_assert( 'pending' === (string) ( $proposal['status'] ?? '' ), 'Core proposal starts in pending status.' );
toolbox_article_core_smoke_assert( 'npcink-abilities-toolkit/create-draft' === (string) ( $proposal['ability_id'] ?? '' ), 'Core proposal stores the real create-draft ability id.' );
toolbox_article_core_smoke_assert( 'npcink-toolbox/build-article-write-plan' === (string) ( $proposal['caller']['plan_ability_id'] ?? '' ), 'Core proposal caller records the plan ability id.' );
toolbox_article_core_smoke_assert( 'npcink-abilities-toolkit/create-draft' === (string) ( $preview['target_ability_id'] ?? '' ), 'Core preview preserves the target ability id.' );
toolbox_article_core_smoke_assert( true === (bool) ( $preview['dry_run'] ?? false ) && false === (bool) ( $preview['commit'] ?? true ), 'Core preview remains dry-run and non-commit.' );
toolbox_article_core_smoke_assert( false === (bool) ( $preview['commit_execution'] ?? true ), 'Core preview does not execute final writes.' );
toolbox_article_core_smoke_assert( is_array( $preview['article_workflow'] ?? null ), 'Core preview includes the article workflow summary.' );
toolbox_article_core_smoke_assert( 'draft' === (string) ( $proposal_input['status'] ?? '' ), 'Core proposal input remains draft-only.' );
toolbox_article_core_smoke_assert( true === (bool) ( $proposal_input['dry_run'] ?? false ) && false === (bool) ( $proposal_input['commit'] ?? true ), 'Core proposal input remains dry-run and non-commit.' );

$after_count = toolbox_article_core_smoke_post_title_count( $title );
toolbox_article_core_smoke_assert( $before_count === $after_count, 'No WordPress post is created during Toolbox/Core proposal intake.' );

if ( toolbox_article_core_smoke_should_execute() ) {
	$proposal_id = sanitize_text_field( (string) ( $proposal['proposal_id'] ?? '' ) );
	toolbox_article_core_smoke_assert( '' !== $proposal_id, 'Core returns a proposal id for explicit Adapter execution.' );
	$executed = toolbox_article_core_smoke_rest(
		'POST',
		'/npcink-openclaw-adapter/v1/proposals/' . rawurlencode( $proposal_id ) . '/approve-and-execute',
		array(
			'intent' => 'commit',
			'note'   => 'Local Toolbox article draft smoke approval.',
		)
	);
	$execution_record = is_array( $executed['execution_record'] ?? null ) ? $executed['execution_record'] : ( is_array( $executed['execution'] ?? null ) ? $executed['execution'] : array() );
	toolbox_article_core_smoke_assert( 'succeeded' === (string) ( $execution_record['status'] ?? '' ), 'Adapter approve-and-execute succeeds after Core approval and preflight.' );

	$post_ids = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => 'any',
			'title'          => $title,
			'fields'         => 'ids',
			'posts_per_page' => 5,
		)
	);
	toolbox_article_core_smoke_assert( 1 === count( $post_ids ), 'Approved governed execution creates exactly one WordPress post.' );
	$created_post = get_post( absint( $post_ids[0] ?? 0 ) );
	toolbox_article_core_smoke_assert( $created_post instanceof WP_Post && 'draft' === $created_post->post_status, 'Governed execution creates status draft, never publish.' );
	toolbox_article_core_smoke_assert( false !== strpos( (string) $created_post->post_content, '<h2>Governed draft smoke</h2>' ), 'Markdown sections are converted to WordPress-safe HTML.' );

	toolbox_article_core_smoke_purge_post_fixture();
	toolbox_article_core_smoke_assert( $before_count === toolbox_article_core_smoke_post_title_count( $title ), 'Created draft fixture is removed after readback.' );
} else {
	toolbox_article_core_smoke_info( 'Set NPCINK_TOOLBOX_ARTICLE_CORE_SMOKE_EXECUTE=1 to approve, execute, verify draft status, and clean up the post fixture.' );
}

toolbox_article_core_smoke_purge_governance_records();
echo "Toolbox article draft Core handoff smoke passed.\n";
