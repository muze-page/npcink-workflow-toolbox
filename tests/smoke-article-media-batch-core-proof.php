<?php
/**
 * Local WordPress smoke for the high-risk article/media batch Core proposal path.
 *
 * Run with WP-CLI:
 * wp eval-file tests/smoke-article-media-batch-core-proof.php
 *
 * @package Npcink_Toolbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "FAIL: Run this script through WP-CLI eval-file so WordPress is loaded.\n" );
	exit( 1 );
}

$toolbox_article_media_batch_smoke_proposal_ids = array();

function toolbox_article_media_batch_smoke_pass( string $message ): void {
	echo "PASS: {$message}\n";
}

function toolbox_article_media_batch_smoke_info( string $message ): void {
	echo "INFO: {$message}\n";
}

function toolbox_article_media_batch_smoke_fail( string $message ): void {
	fwrite( STDERR, "FAIL: {$message}\n" );
	toolbox_article_media_batch_smoke_purge_governance_records();
	exit( 1 );
}

function toolbox_article_media_batch_smoke_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		toolbox_article_media_batch_smoke_fail( $message );
	}

	toolbox_article_media_batch_smoke_pass( $message );
}

function toolbox_article_media_batch_smoke_admin_user_id(): int {
	$admins = get_users(
		array(
			'role'   => 'administrator',
			'number' => 1,
			'fields' => 'ids',
		)
	);

	return absint( $admins[0] ?? 0 );
}

function toolbox_article_media_batch_smoke_post_title_count( array $titles ): int {
	global $wpdb;

	if ( empty( $titles ) ) {
		return 0;
	}

	$placeholders = implode( ',', array_fill( 0, count( $titles ), '%s' ) );

	return (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_title IN ({$placeholders})",
			$titles
		)
	);
}

function toolbox_article_media_batch_smoke_attachment_count(): int {
	$counts = wp_count_posts( 'attachment' );
	$total  = 0;

	foreach ( get_object_vars( $counts ) as $count ) {
		$total += absint( $count );
	}

	return $total;
}

function toolbox_article_media_batch_smoke_local_consent_audit_count(): int {
	global $wpdb;

	$table = $wpdb->prefix . 'npcink_governance_core_audit_log';

	return (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE event_name IN (%s, %s, %s)",
			'local_admin_consent.requested',
			'local_admin_consent.completed',
			'local_admin_consent.failed'
		)
	);
}

function toolbox_article_media_batch_smoke_track_rest_fixture( string $method, string $route, $data ): void {
	global $toolbox_article_media_batch_smoke_proposal_ids;

	if ( 'POST' !== strtoupper( $method ) || '/npcink-governance-core/v1/proposals/from-plan' !== $route || ! is_array( $data ) ) {
		return;
	}

	foreach ( (array) ( $data['proposals'] ?? array() ) as $proposal ) {
		if ( ! is_array( $proposal ) ) {
			continue;
		}

		$proposal_id = trim( (string) ( $proposal['proposal_id'] ?? '' ) );
		if ( '' !== $proposal_id ) {
			$toolbox_article_media_batch_smoke_proposal_ids[ $proposal_id ] = true;
		}
	}
}

function toolbox_article_media_batch_smoke_should_purge_governance_records(): bool {
	$value = getenv( 'NPCINK_TOOLBOX_ARTICLE_MEDIA_BATCH_SMOKE_PURGE' );
	if ( ! is_string( $value ) || '' === trim( $value ) ) {
		return true;
	}

	return in_array( strtolower( trim( $value ) ), array( '1', 'true', 'yes' ), true );
}

function toolbox_article_media_batch_smoke_purge_governance_records(): void {
	global $wpdb, $toolbox_article_media_batch_smoke_proposal_ids;

	if ( ! toolbox_article_media_batch_smoke_should_purge_governance_records() ) {
		return;
	}

	$proposal_ids = array_keys( is_array( $toolbox_article_media_batch_smoke_proposal_ids ) ? $toolbox_article_media_batch_smoke_proposal_ids : array() );
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

	toolbox_article_media_batch_smoke_info( 'Purged Core proposal fixtures: ' . count( $proposal_ids ) );
}

function toolbox_article_media_batch_smoke_rest( string $method, string $route, array $params = array() ): array {
	wp_set_current_user( toolbox_article_media_batch_smoke_admin_user_id() );

	$request = new WP_REST_Request( $method, $route );
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}

	$response = rest_do_request( $request );
	$status   = (int) $response->get_status();
	$data     = $response->get_data();
	toolbox_article_media_batch_smoke_track_rest_fixture( $method, $route, $data );

	toolbox_article_media_batch_smoke_assert(
		$status >= 200 && $status < 300,
		$method . ' ' . $route . ' returned HTTP ' . $status
	);

	return is_array( $data ) ? $data : array();
}

toolbox_article_media_batch_smoke_assert( class_exists( 'WP_REST_Request' ) && function_exists( 'rest_do_request' ), 'WordPress REST dispatch is available.' );
toolbox_article_media_batch_smoke_assert( toolbox_article_media_batch_smoke_admin_user_id() > 0, 'A local administrator is available.' );
toolbox_article_media_batch_smoke_assert( class_exists( '\Npcink_Toolbox\Provider_Client' ) && class_exists( '\Npcink_Toolbox\Settings' ), 'Toolbox provider client classes are loaded.' );
toolbox_article_media_batch_smoke_assert( function_exists( 'npcink_abilities_toolkit_get_registered' ), 'Npcink Abilities Toolkit registry is available.' );

$definitions = npcink_abilities_toolkit_get_registered();
$definitions = is_array( $definitions ) ? $definitions : array();
foreach (
	array(
		'npcink-toolbox/build-article-media-batch-write-plan',
		'npcink-abilities-toolkit/create-draft',
		'npcink-abilities-toolkit/upload-media-from-url',
		'npcink-abilities-toolkit/update-media-details',
		'npcink-abilities-toolkit/set-post-featured-image',
	) as $ability_id
) {
	toolbox_article_media_batch_smoke_assert( isset( $definitions[ $ability_id ] ) && is_array( $definitions[ $ability_id ] ), "{$ability_id} is registered." );
}

$run_id = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'article_media_batch_', true );
$suffix = substr( md5( $run_id ), 0, 8 );
$titles = array(
	'Toolbox Article Media Batch Core Proof A ' . $suffix,
	'Toolbox Article Media Batch Core Proof B ' . $suffix,
);

$plan_input = array(
	'topic'    => 'Toolbox high-risk article plus image batch Core proposal proof ' . $suffix,
	'articles' => array(
		array(
			'title'            => $titles[0],
			'content_markdown' => "Reviewed batch article A for Core proposal proof.\n\nThis is dry-run content only.",
			'excerpt'          => 'Reviewed batch article A excerpt.',
				'image_candidate'  => array(
					'url'              => 'https://example.com/npcink-batch-proof-a.jpg',
					'regular_url'      => 'https://example.com/npcink-batch-proof-a.jpg',
					'download_url'     => 'https://example.com/npcink-batch-proof-a.jpg',
					'thumbnail_url'    => 'https://example.com/npcink-batch-proof-a-thumb.jpg',
				'source_url'       => 'https://example.com/source-a',
				'source_type'      => 'external',
				'provider'         => 'manual',
				'title'            => 'Batch proof image A',
				'description'      => 'Reviewed external image candidate A.',
				'alt_description'  => 'Reviewed external image candidate A.',
				'attribution_text' => 'Example source A',
			),
		),
		array(
			'title'            => $titles[1],
			'content_markdown' => "Reviewed batch article B for Core proposal proof.\n\nThis is dry-run content only.",
			'excerpt'          => 'Reviewed batch article B excerpt.',
				'image_candidate'  => array(
					'url'              => 'https://example.com/npcink-batch-proof-b.jpg',
					'regular_url'      => 'https://example.com/npcink-batch-proof-b.jpg',
					'download_url'     => 'https://example.com/npcink-batch-proof-b.jpg',
					'thumbnail_url'    => 'https://example.com/npcink-batch-proof-b-thumb.jpg',
				'source_url'       => 'https://example.com/source-b',
				'source_type'      => 'external',
				'provider'         => 'manual',
				'title'            => 'Batch proof image B',
				'description'      => 'Reviewed external image candidate B.',
				'alt_description'  => 'Reviewed external image candidate B.',
				'attribution_text' => 'Example source B',
			),
		),
	),
);

$client = new \Npcink_Toolbox\Provider_Client( new \Npcink_Toolbox\Settings() );
$plan   = $client->build_article_media_batch_write_plan( $plan_input );
if ( is_wp_error( $plan ) ) {
	toolbox_article_media_batch_smoke_fail( 'Toolbox could not build the article/media batch plan: ' . $plan->get_error_code() );
}
toolbox_article_media_batch_smoke_assert( ! is_wp_error( $plan ) && is_array( $plan ), 'Toolbox builds a high-risk article/media batch plan.' );

$before_title_count         = toolbox_article_media_batch_smoke_post_title_count( $titles );
$before_attachment_count    = toolbox_article_media_batch_smoke_attachment_count();
$before_local_consent_count = toolbox_article_media_batch_smoke_local_consent_audit_count();

toolbox_article_media_batch_smoke_assert( 'article_media_batch_write_plan' === (string) ( $plan['artifact_type'] ?? '' ), 'Toolbox returns an article_media_batch_write_plan artifact.' );
toolbox_article_media_batch_smoke_assert( 'core_article_media_batch_write_plan' === (string) ( $plan['composition_role'] ?? '' ), 'Toolbox plan declares the Core batch handoff role.' );
toolbox_article_media_batch_smoke_assert( 'core_proposal_handoff' === (string) ( $plan['write_posture'] ?? '' ), 'Toolbox marks the batch as a Core proposal handoff.' );
toolbox_article_media_batch_smoke_assert( false === (bool) ( $plan['direct_wordpress_write'] ?? true ), 'Toolbox batch plan disables direct WordPress writes.' );
toolbox_article_media_batch_smoke_assert( true === (bool) ( $plan['dry_run'] ?? false ) && false === (bool) ( $plan['commit_execution'] ?? true ), 'Toolbox batch plan remains dry-run without commit execution.' );
toolbox_article_media_batch_smoke_assert( 'batch' === (string) ( $plan['proposal_mode'] ?? '' ) && true === (bool) ( $plan['batch_approval'] ?? false ), 'Toolbox batch plan requests one Core batch approval.' );
toolbox_article_media_batch_smoke_assert( 'core_proposal_required' === (string) ( $plan['handoff']['final_write_path'] ?? '' ), 'Toolbox batch plan declares core_proposal_required.' );

$write_actions = is_array( $plan['write_actions'] ?? null ) ? array_values( $plan['write_actions'] ) : array();
toolbox_article_media_batch_smoke_assert( 8 === count( $write_actions ), 'Toolbox batch plan contains draft, upload, metadata, and featured-image actions for both articles.' );

$target_counts = array();
foreach ( $write_actions as $action ) {
	$target = (string) ( is_array( $action ) ? ( $action['target_ability_id'] ?? '' ) : '' );
	$input  = is_array( $action['input'] ?? null ) ? $action['input'] : array();
	$target_counts[ $target ] = ( $target_counts[ $target ] ?? 0 ) + 1;
	toolbox_article_media_batch_smoke_assert( true === (bool) ( $input['dry_run'] ?? false ) && false === (bool) ( $input['commit'] ?? true ), 'Every batch write action is dry-run and non-commit.' );
	toolbox_article_media_batch_smoke_assert( true === (bool) ( $action['requires_approval'] ?? false ), 'Every batch write action requires approval.' );
	toolbox_article_media_batch_smoke_assert( false === (bool) ( $action['commit_execution'] ?? true ), 'Every batch write action disables commit execution.' );
}
toolbox_article_media_batch_smoke_assert( 2 === (int) ( $target_counts['npcink-abilities-toolkit/create-draft'] ?? 0 ), 'Batch includes two draft creation actions.' );
toolbox_article_media_batch_smoke_assert( 2 === (int) ( $target_counts['npcink-abilities-toolkit/upload-media-from-url'] ?? 0 ), 'Batch includes two media upload actions.' );
toolbox_article_media_batch_smoke_assert( 2 === (int) ( $target_counts['npcink-abilities-toolkit/update-media-details'] ?? 0 ), 'Batch includes two media metadata actions.' );
toolbox_article_media_batch_smoke_assert( 2 === (int) ( $target_counts['npcink-abilities-toolkit/set-post-featured-image'] ?? 0 ), 'Batch includes two featured-image actions.' );

$created = toolbox_article_media_batch_smoke_rest(
	'POST',
	'/npcink-governance-core/v1/proposals/from-plan',
	array(
		'plan_ability_id' => 'npcink-toolbox/build-article-media-batch-write-plan',
		'plan'            => $plan,
		'plan_input'      => $plan_input,
		'caller'          => array(
			'source' => 'tests/smoke-article-media-batch-core-proof.php:' . $run_id,
		),
	)
);

toolbox_article_media_batch_smoke_assert( 'npcink-toolbox/build-article-media-batch-write-plan' === (string) ( $created['plan_ability_id'] ?? '' ), 'Core response records the article/media batch planning ability.' );
toolbox_article_media_batch_smoke_assert( 1 === (int) ( $created['proposal_count'] ?? 0 ), 'Core creates one batch proposal from the high-risk plan.' );
toolbox_article_media_batch_smoke_assert( 8 === (int) ( $created['action_count'] ?? 0 ), 'Core response preserves the batch action count.' );
toolbox_article_media_batch_smoke_assert( false === (bool) ( $created['commit_execution'] ?? true ), 'Core from-plan intake remains non-commit.' );

$proposals = is_array( $created['proposals'] ?? null ) ? $created['proposals'] : array();
$proposal  = is_array( $proposals[0] ?? null ) ? $proposals[0] : array();
$preview   = is_array( $proposal['preview'] ?? null ) ? $proposal['preview'] : array();
$input     = is_array( $proposal['input'] ?? null ) ? $proposal['input'] : array();

toolbox_article_media_batch_smoke_assert( 'pending' === (string) ( $proposal['status'] ?? '' ), 'Core batch proposal starts pending approval.' );
toolbox_article_media_batch_smoke_assert( 'npcink-abilities-toolkit/create-draft' === (string) ( $proposal['ability_id'] ?? '' ), 'Core batch proposal stores the first target ability for availability checks.' );
toolbox_article_media_batch_smoke_assert( 'plan_to_proposal_batch' === (string) ( $preview['source']['type'] ?? '' ), 'Core preview records a plan_to_proposal_batch source.' );
toolbox_article_media_batch_smoke_assert( is_array( $preview['article_media_batch_workflow'] ?? null ), 'Core preview preserves article media batch workflow evidence.' );
toolbox_article_media_batch_smoke_assert( 8 === count( (array) ( $input['write_actions'] ?? array() ) ), 'Core proposal input stores all batch write actions.' );
toolbox_article_media_batch_smoke_assert( true === (bool) ( $input['dry_run'] ?? false ) && false === (bool) ( $input['commit'] ?? true ), 'Core batch proposal input remains dry-run and non-commit.' );
toolbox_article_media_batch_smoke_assert( false === (bool) ( $preview['commit_execution'] ?? true ), 'Core batch proposal preview disables Core execution.' );

toolbox_article_media_batch_smoke_assert( $before_title_count === toolbox_article_media_batch_smoke_post_title_count( $titles ), 'High-risk batch proof does not create WordPress posts.' );
toolbox_article_media_batch_smoke_assert( $before_attachment_count === toolbox_article_media_batch_smoke_attachment_count(), 'High-risk batch proof does not upload media attachments.' );
toolbox_article_media_batch_smoke_assert( $before_local_consent_count === toolbox_article_media_batch_smoke_local_consent_audit_count(), 'High-risk batch proof does not use Local Admin Consent audit events.' );

toolbox_article_media_batch_smoke_purge_governance_records();
echo "Toolbox article/media batch Core proposal smoke passed.\n";
