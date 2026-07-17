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
$GLOBALS['toolbox_article_core_smoke_post_id']      = 0;
$GLOBALS['toolbox_article_core_smoke_unexpected_http_urls'] = array();
$GLOBALS['toolbox_article_core_smoke_addon_state_before'] = array();

function toolbox_article_core_smoke_pass( string $message ): void {
	echo "PASS: {$message}\n";
}

function toolbox_article_core_smoke_info( string $message ): void {
	echo "INFO: {$message}\n";
}

function toolbox_article_core_smoke_fail( string $message ): void {
	fwrite( STDERR, "FAIL: {$message}\n" );
	toolbox_article_core_smoke_purge_post_fixture();
	toolbox_article_core_smoke_purge_adapter_records();
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
	$post_id = absint( $GLOBALS['toolbox_article_core_smoke_post_id'] ?? 0 );
	$title   = (string) ( $GLOBALS['toolbox_article_core_smoke_post_title'] ?? '' );
	if ( $post_id <= 0 ) {
		return;
	}

	$post = get_post( $post_id );
	if ( ! $post instanceof WP_Post ) {
		$GLOBALS['toolbox_article_core_smoke_post_id'] = 0;
		return;
	}

	if ( 'post' !== $post->post_type || 'draft' !== $post->post_status || $title !== $post->post_title ) {
		fwrite( STDERR, "FAIL: Refusing to delete a post whose id, type, status, or title no longer matches the smoke fixture.\n" );
		return;
	}

	$deleted = wp_delete_post( $post_id, true );
	if ( $deleted instanceof WP_Post ) {
		$GLOBALS['toolbox_article_core_smoke_post_id'] = 0;
		return;
	}

	fwrite( STDERR, "FAIL: WordPress did not delete the tracked smoke draft.\n" );
}

function toolbox_article_core_smoke_should_purge_governance_records(): bool {
	$value = getenv( 'NPCINK_TOOLBOX_ARTICLE_CORE_SMOKE_PURGE' );
	if ( ! is_string( $value ) || '' === trim( $value ) ) {
		return true;
	}

	return in_array( strtolower( trim( $value ) ), array( '1', 'true', 'yes' ), true );
}

function toolbox_article_core_smoke_purge_adapter_records(): void {
	if ( ! toolbox_article_core_smoke_should_purge_governance_records() ) {
		return;
	}

	$proposal_ids = array_keys( (array) ( $GLOBALS['toolbox_article_core_smoke_proposal_ids'] ?? array() ) );
	if ( empty( $proposal_ids ) ) {
		return;
	}

	foreach ( array( 'npcink_openclaw_adapter_execution_records', 'npcink_openclaw_adapter_preflight_handoffs' ) as $option_name ) {
		$records = get_option( $option_name, array() );
		if ( ! is_array( $records ) ) {
			continue;
		}

		foreach ( $proposal_ids as $proposal_id ) {
			unset( $records[ md5( $proposal_id ) ] );
			foreach ( $records as $record_key => $record ) {
				if ( is_array( $record ) && $proposal_id === (string) ( $record['proposal_id'] ?? '' ) ) {
					unset( $records[ $record_key ] );
				}
			}
			delete_option( 'npcink_openclaw_adapter_exec_lock_' . md5( $proposal_id ) );
		}

		update_option( $option_name, $records, false );
	}

	toolbox_article_core_smoke_info( 'Purged Adapter execution fixtures: ' . count( $proposal_ids ) );
}

function toolbox_article_core_smoke_assert_fixture_cleanup(): void {
	global $wpdb;

	$proposal_ids = array_keys( (array) ( $GLOBALS['toolbox_article_core_smoke_proposal_ids'] ?? array() ) );
	foreach ( $proposal_ids as $proposal_id ) {
		if ( ! toolbox_article_core_smoke_should_purge_governance_records() ) {
			toolbox_article_core_smoke_info( "Retained Adapter and Core records for inspection: {$proposal_id}" );
			continue;
		}

		foreach ( array( 'npcink_openclaw_adapter_execution_records', 'npcink_openclaw_adapter_preflight_handoffs' ) as $option_name ) {
			$records = get_option( $option_name, array() );
			$records = is_array( $records ) ? $records : array();
			$contains_proposal = isset( $records[ md5( $proposal_id ) ] );
			foreach ( $records as $record ) {
				if ( is_array( $record ) && $proposal_id === (string) ( $record['proposal_id'] ?? '' ) ) {
					$contains_proposal = true;
				}
			}
			toolbox_article_core_smoke_assert( ! $contains_proposal, "{$option_name} no longer contains the smoke proposal." );
		}

		$proposal_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}npcink_governance_core_proposals WHERE proposal_id = %s",
				$proposal_id
			)
		);
		$audit_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}npcink_governance_core_audit_log WHERE proposal_id = %s",
				$proposal_id
			)
		);
		toolbox_article_core_smoke_assert( 0 === $proposal_count && 0 === $audit_count, 'Core proposal and audit fixtures are removed.' );
	}
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

/**
 * Installs process-local guards that keep the acceptance lane offline and
 * prevent Cloud Addon buffers from observing the temporary draft lifecycle.
 */
function toolbox_article_core_smoke_install_local_guards(): void {
	$option_name = class_exists( 'Npcink_Cloud_Addon_Settings' )
		? Npcink_Cloud_Addon_Settings::option_name()
		: 'npcink_cloud_addon_settings';
	$stored_settings = get_option( $option_name, false );
	$safe_settings   = is_array( $stored_settings ) ? $stored_settings : $stored_settings;
	if ( is_array( $safe_settings ) ) {
		$safe_settings['monitoring_enabled']              = false;
		$safe_settings['site_knowledge_delivery_enabled'] = false;
	}

	add_filter(
		'pre_option_' . $option_name,
		static function () use ( $safe_settings ) {
			return $safe_settings;
		},
		PHP_INT_MAX,
		3
	);

	add_filter(
		'pre_http_request',
		static function ( $preempt, $parsed_args, $url ) {
			$GLOBALS['toolbox_article_core_smoke_unexpected_http_urls'][] = (string) $url;

			return new WP_Error(
				'toolbox_article_core_smoke_outbound_http_blocked',
				'Outbound HTTP is blocked for the five-plugin no-credit acceptance lane.'
			);
		},
		PHP_INT_MAX,
		3
	);

	$GLOBALS['toolbox_article_core_smoke_addon_state_before'] = array(
		'site_knowledge_buffer' => get_option( 'npcink_cloud_addon_site_knowledge_change_buffer', null ),
		'observability_buffer'  => get_option( 'npcink_cloud_addon_observability_buffer', null ),
		'site_knowledge_flush'  => wp_next_scheduled( 'npcink_cloud_addon_flush_site_knowledge_changes' ),
		'site_knowledge_sync'   => wp_next_scheduled( 'npcink_cloud_addon_reconcile_site_knowledge_changes' ),
		'observability_flush'   => wp_next_scheduled( 'npcink_cloud_addon_flush_observability' ),
	);

	register_shutdown_function(
		static function (): void {
			$urls = (array) ( $GLOBALS['toolbox_article_core_smoke_unexpected_http_urls'] ?? array() );
			if ( empty( $urls ) ) {
				return;
			}

			fwrite( STDERR, 'FAIL: Outbound HTTP was attempted during the governed draft lane: ' . implode( ', ', array_unique( $urls ) ) . "\n" );
			exit( 1 );
		}
	);
}

/**
 * Confirms the temporary draft did not mutate Cloud Addon delivery state.
 */
function toolbox_article_core_smoke_assert_addon_state_unchanged(): void {
	$after = array(
		'site_knowledge_buffer' => get_option( 'npcink_cloud_addon_site_knowledge_change_buffer', null ),
		'observability_buffer'  => get_option( 'npcink_cloud_addon_observability_buffer', null ),
		'site_knowledge_flush'  => wp_next_scheduled( 'npcink_cloud_addon_flush_site_knowledge_changes' ),
		'site_knowledge_sync'   => wp_next_scheduled( 'npcink_cloud_addon_reconcile_site_knowledge_changes' ),
		'observability_flush'   => wp_next_scheduled( 'npcink_cloud_addon_flush_observability' ),
	);

	toolbox_article_core_smoke_assert(
		(array) ( $GLOBALS['toolbox_article_core_smoke_addon_state_before'] ?? array() ) === $after,
		'Cloud Addon buffers and scheduled delivery hooks remain unchanged.'
	);
}

/**
 * Counts one Core event in a proposal detail audit timeline.
 */
function toolbox_article_core_smoke_audit_event_count( array $timeline, string $event_name ): int {
	return count(
		array_filter(
			$timeline,
			static function ( $event ) use ( $event_name ): bool {
				return is_array( $event ) && $event_name === (string) ( $event['event_name'] ?? '' );
			}
		)
	);
}

toolbox_article_core_smoke_install_local_guards();

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
	$created_post_id = absint( $execution_record['post_id'] ?? ( $executed['post_id'] ?? 0 ) );
	$GLOBALS['toolbox_article_core_smoke_post_id'] = $created_post_id;
	toolbox_article_core_smoke_assert( 'succeeded' === (string) ( $execution_record['status'] ?? '' ), 'Adapter approve-and-execute succeeds after Core approval and preflight.' );
	$core_preflight_evidence = is_array( $executed['core_preflight_evidence'] ?? null ) ? $executed['core_preflight_evidence'] : array();
	$core_execution_record   = is_array( $execution_record['core_execution_record'] ?? null ) ? $execution_record['core_execution_record'] : array();
	toolbox_article_core_smoke_assert( 'pending' === (string) ( $executed['status_before'] ?? '' ) && true === (bool) ( $executed['approved_by_adapter'] ?? false ), 'Adapter records the explicit pending-to-approved transition.' );
	toolbox_article_core_smoke_assert( 'core_commit_preflight' === (string) ( $executed['preflight_source'] ?? '' ), 'Adapter execution uses fresh Core commit preflight.' );
	toolbox_article_core_smoke_assert( false === (bool) ( $executed['core_commit_execution'] ?? true ), 'Core remains authorization-only and does not execute the write.' );
	toolbox_article_core_smoke_assert( true === (bool) ( $core_preflight_evidence['authorized'] ?? false ) && 'core-preflight-v1' === (string) ( $core_preflight_evidence['policy_version'] ?? '' ), 'Adapter preserves authorized Core preflight policy evidence.' );
	toolbox_article_core_smoke_assert( true === (bool) ( $core_execution_record['recorded'] ?? false ) && 'executed' === (string) ( $core_execution_record['status'] ?? '' ), 'Adapter records the completed execution back in Core.' );

	toolbox_article_core_smoke_assert( $created_post_id > 0, 'Adapter returns the exact created WordPress post id.' );
	toolbox_article_core_smoke_assert( 1 === toolbox_article_core_smoke_post_title_count( $title ), 'Approved governed execution creates exactly one WordPress post.' );
	$created_post = get_post( $created_post_id );
	toolbox_article_core_smoke_assert( $created_post instanceof WP_Post && $title === $created_post->post_title && 'draft' === $created_post->post_status, 'Governed execution creates the expected status-draft post, never publish.' );
	toolbox_article_core_smoke_assert( false !== strpos( (string) $created_post->post_content, '<h2>Governed draft smoke</h2>' ), 'Markdown sections are converted to WordPress-safe HTML.' );

	$duplicate = toolbox_article_core_smoke_rest_result(
		'POST',
		'/npcink-openclaw-adapter/v1/proposals/' . rawurlencode( $proposal_id ) . '/approve-and-execute',
		array(
			'intent' => 'commit',
			'note'   => 'Duplicate local Toolbox article draft smoke approval.',
		)
	);
	$duplicate_data = is_array( $duplicate['data'] ?? null ) ? $duplicate['data'] : array();
	$duplicate_error_data = is_array( $duplicate_data['data'] ?? null ) ? $duplicate_data['data'] : array();
	$duplicate_record = is_array( $duplicate_error_data['execution_record'] ?? null ) ? $duplicate_error_data['execution_record'] : array();
	toolbox_article_core_smoke_assert( 409 === (int) $duplicate['status'], 'Duplicate governed execution is rejected with HTTP 409.' );
	toolbox_article_core_smoke_assert( 'npcink_openclaw_adapter_execution_already_completed' === (string) ( $duplicate_data['code'] ?? '' ), 'Duplicate governed execution returns the Adapter completed-execution code.' );
	toolbox_article_core_smoke_assert( $created_post_id === absint( $duplicate_record['post_id'] ?? 0 ), 'Duplicate rejection returns the original execution record.' );
	toolbox_article_core_smoke_assert( 1 === toolbox_article_core_smoke_post_title_count( $title ), 'Duplicate execution does not create a second WordPress post.' );

	$core_readback = toolbox_article_core_smoke_rest( 'GET', '/npcink-governance-core/v1/proposals/' . rawurlencode( $proposal_id ) );
	toolbox_article_core_smoke_assert( 'executed' === (string) ( $core_readback['status'] ?? '' ), 'Core truth records the proposal as executed.' );
	$core_timeline = is_array( $core_readback['audit_timeline'] ?? null ) ? $core_readback['audit_timeline'] : array();
	foreach ( array( 'proposal.approved', 'commit.preflighted', 'proposal.executed' ) as $event_name ) {
		toolbox_article_core_smoke_assert( 1 === toolbox_article_core_smoke_audit_event_count( $core_timeline, $event_name ), "Core audit timeline contains exactly one {$event_name} event." );
	}

	$expected_hash        = sanitize_text_field( (string) ( $core_preflight_evidence['approved_input_hash'] ?? '' ) );
	$expected_correlation = sanitize_text_field( (string) ( $core_preflight_evidence['correlation_id'] ?? '' ) );
	toolbox_article_core_smoke_assert( '' !== $expected_hash && '' !== $expected_correlation, 'Adapter exposes the Core preflight input hash and correlation id.' );
	foreach ( $core_timeline as $event ) {
		if ( ! is_array( $event ) || ! in_array( (string) ( $event['event_name'] ?? '' ), array( 'commit.preflighted', 'proposal.executed' ), true ) ) {
			continue;
		}
		$metadata = is_array( $event['metadata'] ?? null ) ? $event['metadata'] : array();
		toolbox_article_core_smoke_assert( $expected_hash === sanitize_text_field( (string) ( $metadata['approved_input_hash'] ?? '' ) ), 'Core audit event remains bound to the approved input hash.' );
		toolbox_article_core_smoke_assert( $expected_correlation === sanitize_text_field( (string) ( $metadata['correlation_id'] ?? '' ) ), 'Core audit event remains bound to the preflight correlation id.' );
	}

	$deleted_post_id = $created_post_id;
	toolbox_article_core_smoke_purge_post_fixture();
	toolbox_article_core_smoke_assert( null === get_post( $deleted_post_id ), 'Only the tracked draft post id is permanently removed.' );
	toolbox_article_core_smoke_assert( $before_count === toolbox_article_core_smoke_post_title_count( $title ), 'Created draft fixture is removed after readback.' );
} else {
	toolbox_article_core_smoke_info( 'Set NPCINK_TOOLBOX_ARTICLE_CORE_SMOKE_EXECUTE=1 to approve, execute, verify draft status, and clean up the post fixture.' );
}

toolbox_article_core_smoke_purge_adapter_records();
toolbox_article_core_smoke_purge_governance_records();
toolbox_article_core_smoke_assert_fixture_cleanup();
toolbox_article_core_smoke_assert_addon_state_unchanged();
toolbox_article_core_smoke_assert( array() === (array) $GLOBALS['toolbox_article_core_smoke_unexpected_http_urls'], 'No outbound HTTP request is attempted during the governed draft lane.' );
echo "Toolbox article draft Core handoff smoke passed.\n";
