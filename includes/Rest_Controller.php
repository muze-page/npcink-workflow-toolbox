<?php
/**
 * REST endpoints for Toolbox admin actions and future clients.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

use Npcink\LocalAutomationRuntime\NightlyInspection\Snapshot_Collector;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

final class Rest_Controller {
	private const REQUIRED_TEXT_MAX_CHARS = 500;
	private const EDITOR_SUMMARY_FULL_CONTENT_MAX_CHARS = 30000;
	private const EDITOR_FLOW_CACHE_TTL = 300;
	private const EDITOR_PROGRESSIVE_TARGET_MS = 2500;
	private const EDITOR_PROGRESSIVE_CANDIDATE_LIMIT = 8;

	private Settings $settings;
	private Provider_Client $client;

	public function __construct( Settings $settings, Provider_Client $client ) {
		$this->settings = $settings;
		$this->client   = $client;
	}

	public function register_routes(): void {
		$this->post( '/image-candidates', 'image_candidates' );
		$this->post( '/vector-search', 'knowledge_search' );
		$this->post( '/knowledge-search', 'knowledge_search' );
		$this->post( '/web-search/test', 'web_search_test' );
		$this->post( '/web-search/diagnostics', 'web_search_diagnostics' );
		$this->post( '/site-knowledge/search', 'site_knowledge_search' );
		$this->post( '/site-knowledge/sync', 'site_knowledge_sync' );
		$this->post( '/agent-feedback', 'agent_feedback' );
		$this->post( '/agent-feedback/summary', 'agent_feedback_summary' );
		$this->post( '/ai/content-support', 'hosted_ai_content_support' );
		$this->post( '/ai/site-helpers', 'hosted_ai_site_helper' );
		$this->post( '/ai/image-generation', 'ai_image_generation' );
		$this->post( '/flows/article-brief', 'article_brief' );
		$this->post( '/flows/article-assistant', 'article_assistant' );
		$this->post( '/flows/article-plan', 'article_plan' );
		$this->post( '/flows/image-candidate-adoption-plan', 'image_candidate_adoption_plan' );
		$this->post( '/local-admin-consent/featured-image', 'local_admin_consent_featured_image' );
		$this->post( '/flows/site-knowledge-review-plan', 'site_knowledge_review_plan' );
		$this->post( '/flows/nightly-inspection-review-plan', 'nightly_inspection_review_plan' );
		$this->post( '/flows/content-metadata-apply-plan', 'content_metadata_apply_plan' );
		$this->post( '/flows/media-brief', 'media_brief' );
		$this->post( '/editor/content-support', 'editor_content_support' );
		$this->post( '/media-derivative-handoff', 'media_derivative_handoff' );
		$this->post( '/nightly-inspection/cloud-batch', 'nightly_inspection_cloud_batch' );
		$this->get( '/nightly-inspection/cloud-runtime-entitlement', 'nightly_inspection_cloud_runtime_entitlement' );
		$this->get( '/nightly-inspection/cloud-batch/recent', 'nightly_inspection_cloud_batch_recent' );
		$this->get( '/nightly-inspection/cloud-batch/(?P<run_id>[A-Za-z0-9._:-]+)', 'nightly_inspection_cloud_batch_status' );
		$this->get( '/nightly-inspection/cloud-batch/(?P<run_id>[A-Za-z0-9._:-]+)/result', 'nightly_inspection_cloud_batch_result' );
		$this->post( '/nightly-inspection/cloud-batch/(?P<run_id>[A-Za-z0-9._:-]+)/result', 'nightly_inspection_cloud_batch_result' );
		$this->post( '/nightly-inspection/cloud-batch/(?P<run_id>[A-Za-z0-9._:-]+)/retry', 'nightly_inspection_cloud_batch_retry' );

		register_rest_route(
			Plugin::REST_NAMESPACE,
			'/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'status' ),
				'permission_callback' => array( $this, 'permission' ),
			)
		);

		register_rest_route(
			Plugin::REST_NAMESPACE,
			'/site-knowledge/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'site_knowledge_status' ),
				'permission_callback' => array( $this, 'permission' ),
			)
		);
	}

	public function permission( $request = null ): bool {
		return (bool) apply_filters( 'npcink_toolbox_rest_permission', current_user_can( 'manage_options' ), $request );
	}

	public function status(): WP_REST_Response {
		$cloud_runtime = $this->settings->cloud_runtime_status();
		$cloud_ready   = (bool) $cloud_runtime['available'];

		return rest_ensure_response(
			array(
				'image_provider'           => 'cloud_image_sources',
				'image_source_providers'   => $this->settings->configured_image_source_providers(),
				'vector_provider'          => 'cloud_site_knowledge',
				'web_search_owner'         => 'cloud_runtime',
				'cloud_image_sources_configured' => $this->settings->has_image_source_provider(),
				'raw_responses_enabled'    => (bool) $this->settings->get( 'include_raw_responses' ),
				'image_source_enabled'     => (bool) $this->settings->get( 'enable_image_source' ),
				'image_source_available'   => $cloud_ready && (bool) $this->settings->get( 'enable_image_source' ),
				'vector_search_registered' => true,
				'vector_search_enabled'    => $cloud_ready,
				'web_search_registered'    => true,
				'web_search_enabled'       => $cloud_ready,
				'image_source_owner'       => 'cloud_runtime',
				'ai_image_generation'      => array(
					'registered'              => true,
					'available'               => $cloud_ready,
					'hosted_profile'          => 'grok-imagine-image-quality',
					'entry_surface'           => 'image_source_ai_generation_handoff',
					'posture'                 => 'candidate_only_core_approval_required',
					'direct_wordpress_write'  => false,
				),
				'vector_owner'             => 'cloud_runtime',
				'cloud_runtime'            => $cloud_runtime,
				'hosted_ai'               => array(
					'entry_surface'           => 'toolbox_content_support',
					'hosted_profile'          => 'text.ai',
					'registered'              => true,
					'site_helpers_registered' => true,
					'available'               => $cloud_ready,
					'posture'                 => 'suggestion_only_core_approval_required',
				),
				'pro_nightly_inspection'  => array(
					'registered'             => true,
					'available'              => $cloud_ready,
					'entry_surface'          => 'nightly_inspection_cloud_batch',
					'runtime_owner'          => 'npcink-local-automation-runtime',
					'cloud_role'             => 'runtime_detail',
					'posture'                => 'review_only_core_proposal_required',
					'direct_wordpress_write' => false,
					'polling_registered'     => true,
					'entitlement_route'      => '/nightly-inspection/cloud-runtime-entitlement',
					'recent_route'           => '/nightly-inspection/cloud-batch/recent',
					'retry_registered'       => true,
				),
				'boundary'                 => 'Toolbox returns Cloud-managed image-source and Cloud-managed site-knowledge suggestions only. Cloud owns web search execution and provider configuration. WordPress writes should be handed to Abilities/Core governance.',
			)
		);
	}

	public function image_candidates( WP_REST_Request $request ) {
		if ( ! $this->settings->get( 'enable_image_source' ) ) {
			return $this->disabled_error( 'image source search' );
		}

		$query = $this->required_text( $request, 'query' );
		if ( is_wp_error( $query ) ) {
			return $query;
		}

		return rest_ensure_response(
			$this->client->image_candidates(
				$query,
				array(
					'orientation' => sanitize_key( (string) $request->get_param( 'orientation' ) ),
					'color'       => sanitize_key( (string) $request->get_param( 'color' ) ),
					'provider'    => sanitize_key( (string) $request->get_param( 'provider' ) ),
					'per_page'    => (int) ( $request->get_param( 'per_page' ) ?: 8 ),
					'latency_mode' => sanitize_key( (string) $request->get_param( 'latency_mode' ) ),
					'include_ai_generated' => ! empty( $request->get_param( 'include_ai_generated' ) ),
					'generation_prompt'     => sanitize_textarea_field( (string) $request->get_param( 'generation_prompt' ) ),
					'generated_image_url'   => esc_url_raw( (string) $request->get_param( 'generated_image_url' ) ),
					'model'                 => sanitize_text_field( (string) $request->get_param( 'model' ) ),
					'manual_query'          => $query,
					'refresh_variant'       => sanitize_text_field( (string) $request->get_param( 'refresh_variant' ) ),
					'visual_context'        => $this->image_visual_context_from_request( $request, $query ),
				)
			)
		);
	}

	public function knowledge_search( WP_REST_Request $request ) {
		$query = trim( sanitize_textarea_field( (string) $request->get_param( 'query' ) ) );
		$vector = trim( sanitize_textarea_field( (string) $request->get_param( 'vector' ) ) );
		if ( '' === $query && '' === $vector ) {
			return new WP_Error(
				'npcink_toolbox_missing_vector_input',
				__( 'A query or vector field is required for vector search.', 'npcink-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$input_type = sanitize_key( (string) ( $request->get_param( 'input_type' ) ?: 'auto' ) );
		$input = '' !== $query ? $query : $vector;
		$max_results = max( 1, min( 10, (int) ( $request->get_param( 'max_results' ) ?: 4 ) ) );
		return rest_ensure_response( $this->client->vector_search( $input, $max_results, $input_type ) );
	}

	public function site_knowledge_status( WP_REST_Request $request ) {
		$status = $this->client->get_site_knowledge_status(
			array(
				'include_coverage' => true,
			)
		);

		if ( is_array( $status ) ) {
			$status['auto_sync'] = Site_Knowledge_Auto_Sync::health_snapshot();
		}

		return rest_ensure_response( $status );
	}

	public function web_search_test( WP_REST_Request $request ) {
		$query = $this->required_text( $request, 'query' );
		if ( is_wp_error( $query ) ) {
			return $query;
		}
		$intent            = sanitize_key( (string) ( $request->get_param( 'intent' ) ?: 'news' ) );
		$recency_param     = $request->get_param( 'recency_days' );
		$default_recency   = in_array( $intent, array( 'pricing_snapshot', 'product_comparison' ), true ) ? 0 : ( 'news' === $intent ? 7 : 30 );
		$recency_days      = null === $recency_param || '' === $recency_param ? $default_recency : (int) $recency_param;

		return rest_ensure_response(
			$this->client->test_cloud_web_search(
				array(
					'query'               => $query,
					'intent'              => $intent,
					'max_results'         => max( 1, min( 5, (int) ( $request->get_param( 'max_results' ) ?: 3 ) ) ),
					'recency_days'        => max( 0, min( 30, $recency_days ) ),
				)
			)
		);
	}

	public function web_search_diagnostics( WP_REST_Request $request ) {
		$topic = $this->required_text( $request, 'topic' );
		if ( is_wp_error( $topic ) ) {
			return $topic;
		}

		return rest_ensure_response(
			$this->client->diagnose_automatic_web_search(
				array(
					'topic'    => $topic,
					'title'    => sanitize_text_field( (string) ( $request->get_param( 'title' ) ?: $topic ) ),
					'scenario' => sanitize_key( (string) ( $request->get_param( 'scenario' ) ?: 'article_assistant' ) ),
				)
			)
		);
	}

	public function site_knowledge_sync( WP_REST_Request $request ) {
		$sync_mode = sanitize_key( (string) ( $request->get_param( 'sync_mode' ) ?: 'refresh' ) );
		if ( ! in_array( $sync_mode, array( 'refresh', 'rebuild', 'delete' ), true ) ) {
			$sync_mode = 'refresh';
		}

		return rest_ensure_response(
			$this->client->request_site_knowledge_sync(
				array(
					'sync_mode' => $sync_mode,
					'post_ids'  => $this->csv_absint_list( (string) $request->get_param( 'post_ids' ) ),
					'max_posts' => max( 1, min( 50, (int) ( $request->get_param( 'max_posts' ) ?: 20 ) ) ),
				)
			)
		);
	}

	public function site_knowledge_search( WP_REST_Request $request ) {
		$query = $this->required_text( $request, 'query' );
		if ( is_wp_error( $query ) ) {
			return $query;
		}

		return rest_ensure_response(
			$this->client->search_site_knowledge(
				array(
					'query'           => $query,
					'intent'          => sanitize_key( (string) ( $request->get_param( 'intent' ) ?: 'site_search' ) ),
					'current_post_id' => absint( $request->get_param( 'current_post_id' ) ),
					'max_results'     => max( 1, min( 20, (int) ( $request->get_param( 'max_results' ) ?: 8 ) ) ),
					'filters'         => array(
						'source_types' => $this->csv_list( (string) $request->get_param( 'source_types' ) ),
					),
				)
			)
		);
	}

	public function article_brief( WP_REST_Request $request ) {
		$topic = $this->required_text( $request, 'topic' );
		if ( is_wp_error( $topic ) ) {
			return $topic;
		}

		return rest_ensure_response( $this->client->build_article_brief( $topic, ! empty( $request->get_param( 'include_knowledge' ) ) ) );
	}

	public function hosted_ai_content_support( WP_REST_Request $request ) {
		$params = method_exists( $request, 'get_params' ) ? $request->get_params() : array();
		return rest_ensure_response( $this->client->run_hosted_ai_content_support( is_array( $params ) ? $params : array() ) );
	}

	public function hosted_ai_site_helper( WP_REST_Request $request ) {
		$params = method_exists( $request, 'get_params' ) ? $request->get_params() : array();
		return rest_ensure_response( $this->client->run_hosted_ai_site_helper( is_array( $params ) ? $params : array() ) );
	}

	public function ai_image_generation( WP_REST_Request $request ) {
		$params = method_exists( $request, 'get_params' ) ? $request->get_params() : array();
		return rest_ensure_response( $this->client->run_ai_image_generation( is_array( $params ) ? $params : array() ) );
	}

	public function nightly_inspection_cloud_batch( WP_REST_Request $request ) {
		if ( ! $this->settings->cloud_runtime_available() ) {
			return new WP_Error(
				'npcink_toolbox_nightly_inspection_cloud_batch_unavailable',
				__( 'Connect Npcink Cloud before submitting Pro Nightly Inspection batches.', 'npcink-toolbox' ),
				array( 'status' => 503 )
			);
		}

		$post_limit      = max( 1, min( 50, (int) ( $request->get_param( 'post_limit' ) ?: 12 ) ) );
		$media_limit     = max( 1, min( 50, (int) ( $request->get_param( 'media_limit' ) ?: 8 ) ) );
		$idempotency_key = sanitize_text_field( (string) $request->get_param( 'idempotency_key' ) );
		$config          = $this->settings->get_nightly_inspection_settings();
		$snapshot        = ( new Snapshot_Collector() )->collect( $post_limit, $media_limit );

		return rest_ensure_response(
			$this->client->submit_nightly_inspection_cloud_batch(
				$snapshot,
				array(
					'idempotency_key' => $idempotency_key,
					'payload_mode'    => (string) ( $request->get_param( 'payload_mode' ) ?: $config['cloud_payload_mode'] ),
					'retention_ttl'   => (int) ( $request->get_param( 'retention_ttl' ) ?: $config['cloud_retention_ttl'] ),
					'source'          => 'toolbox_rest',
				)
			)
		);
	}

	public function nightly_inspection_cloud_batch_status( WP_REST_Request $request ) {
		if ( ! $this->settings->cloud_runtime_available() ) {
			return new WP_Error(
				'npcink_toolbox_nightly_inspection_cloud_batch_unavailable',
				__( 'Connect Npcink Cloud before reading Pro Nightly Inspection batches.', 'npcink-toolbox' ),
				array( 'status' => 503 )
			);
		}

		return rest_ensure_response(
			$this->client->get_nightly_inspection_cloud_batch_status(
				sanitize_text_field( (string) $request->get_param( 'run_id' ) )
			)
		);
	}

	public function nightly_inspection_cloud_batch_recent( WP_REST_Request $request ) {
		if ( ! $this->settings->cloud_runtime_available() ) {
			return new WP_Error(
				'npcink_toolbox_nightly_inspection_cloud_batch_unavailable',
				__( 'Connect Npcink Cloud before reading recent Pro Nightly Inspection runs.', 'npcink-toolbox' ),
				array( 'status' => 503 )
			);
		}

		return rest_ensure_response(
			$this->client->get_nightly_inspection_cloud_recent_runs(
				max( 1, min( 50, (int) ( $request->get_param( 'limit' ) ?: 5 ) ) )
			)
		);
	}

	public function nightly_inspection_cloud_runtime_entitlement() {
		if ( ! $this->settings->cloud_runtime_available() ) {
			return new WP_Error(
				'npcink_toolbox_nightly_inspection_entitlement_unavailable',
				__( 'Connect Npcink Cloud before reading Pro Cloud Runtime entitlement.', 'npcink-toolbox' ),
				array( 'status' => 503 )
			);
		}

		return rest_ensure_response( $this->client->get_nightly_inspection_cloud_runtime_entitlement() );
	}

	public function nightly_inspection_cloud_batch_result( WP_REST_Request $request ) {
		if ( ! $this->settings->cloud_runtime_available() ) {
			return new WP_Error(
				'npcink_toolbox_nightly_inspection_cloud_batch_unavailable',
				__( 'Connect Npcink Cloud before reading Pro Nightly Inspection batches.', 'npcink-toolbox' ),
				array( 'status' => 503 )
			);
		}

		$params        = method_exists( $request, 'get_json_params' ) ? $request->get_json_params() : array();
		$morning_brief = is_array( $params ) && is_array( $params['morning_brief'] ?? null ) ? $params['morning_brief'] : array();

		return rest_ensure_response(
			$this->client->get_nightly_inspection_cloud_batch_result(
				sanitize_text_field( (string) $request->get_param( 'run_id' ) ),
				$morning_brief
			)
		);
	}

	public function nightly_inspection_cloud_batch_retry( WP_REST_Request $request ) {
		if ( ! $this->settings->cloud_runtime_available() ) {
			return new WP_Error(
				'npcink_toolbox_nightly_inspection_cloud_batch_unavailable',
				__( 'Connect Npcink Cloud before retrying Pro Nightly Inspection runs.', 'npcink-toolbox' ),
				array( 'status' => 503 )
			);
		}

		$params          = method_exists( $request, 'get_json_params' ) ? $request->get_json_params() : array();
		$params          = is_array( $params ) ? $params : array();
		$config          = $this->settings->get_nightly_inspection_settings();
		$post_limit_raw  = $params['post_limit'] ?? $request->get_param( 'post_limit' );
		$media_limit_raw = $params['media_limit'] ?? $request->get_param( 'media_limit' );
		$post_limit      = max( 1, min( 50, (int) ( $post_limit_raw ?: 12 ) ) );
		$media_limit     = max( 1, min( 50, (int) ( $media_limit_raw ?: 8 ) ) );
		$idempotency_key = sanitize_text_field( (string) ( $params['idempotency_key'] ?? $request->get_param( 'idempotency_key' ) ) );
		$snapshot        = ( new Snapshot_Collector() )->collect( $post_limit, $media_limit );
		$payload_mode    = (string) ( $params['payload_mode'] ?? $request->get_param( 'payload_mode' ) );
		$retention_ttl   = $params['retention_ttl'] ?? $request->get_param( 'retention_ttl' );

		return rest_ensure_response(
			$this->client->retry_nightly_inspection_cloud_batch(
				sanitize_text_field( (string) $request->get_param( 'run_id' ) ),
				$snapshot,
				array(
					'idempotency_key' => $idempotency_key,
					'payload_mode'    => '' !== $payload_mode ? $payload_mode : (string) $config['cloud_payload_mode'],
					'retention_ttl'   => (int) ( $retention_ttl ?: $config['cloud_retention_ttl'] ),
					'source'          => 'toolbox_rest_retry',
				)
			)
		);
	}

	public function agent_feedback( WP_REST_Request $request ) {
		$params = method_exists( $request, 'get_json_params' ) ? $request->get_json_params() : array();
		if ( ! is_array( $params ) ) {
			$params = method_exists( $request, 'get_params' ) ? $request->get_params() : array();
		}

		return rest_ensure_response( $this->client->submit_agent_feedback( is_array( $params ) ? $params : array() ) );
	}

	public function agent_feedback_summary( WP_REST_Request $request ) {
		$params = method_exists( $request, 'get_json_params' ) ? $request->get_json_params() : array();
		if ( ! is_array( $params ) ) {
			$params = method_exists( $request, 'get_params' ) ? $request->get_params() : array();
		}

		return rest_ensure_response( $this->client->get_agent_feedback_summary( is_array( $params ) ? $params : array() ) );
	}

	public function article_assistant( WP_REST_Request $request ) {
		$params = method_exists( $request, 'get_params' ) ? $request->get_params() : array();
		return rest_ensure_response( $this->client->build_article_assistant( is_array( $params ) ? $params : array() ) );
	}

	public function article_plan( WP_REST_Request $request ) {
		$params = method_exists( $request, 'get_params' ) ? $request->get_params() : array();
		return rest_ensure_response( $this->client->build_article_write_plan( is_array( $params ) ? $params : array() ) );
	}

	public function image_candidate_adoption_plan( WP_REST_Request $request ) {
		$params = method_exists( $request, 'get_params' ) ? $request->get_params() : array();
		return rest_ensure_response( $this->client->build_image_candidate_adoption_plan( is_array( $params ) ? $params : array() ) );
	}

	public function local_admin_consent_featured_image( WP_REST_Request $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'npcink_toolbox_local_featured_image_admin_required',
				__( 'Local admin consent requires an administrator session.', 'npcink-toolbox' ),
				array( 'status' => 403 )
			);
		}

		$post_id       = absint( $request->get_param( 'post_id' ) );
		$attachment_id = absint( $request->get_param( 'attachment_id' ) );
		if ( $post_id <= 0 || $attachment_id <= 0 ) {
			return new WP_Error(
				'npcink_toolbox_local_featured_image_target_required',
				__( 'A post_id and existing attachment_id are required.', 'npcink-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$post = get_post( $post_id );
		$attachment = get_post( $attachment_id );
		if ( ! $post || ! $attachment || 'attachment' !== get_post_type( $attachment ) ) {
			return new WP_Error(
				'npcink_toolbox_local_featured_image_target_not_found',
				__( 'The target post or media attachment was not found.', 'npcink-toolbox' ),
				array( 'status' => 404 )
			);
		}

		if ( ! current_user_can( 'edit_post', $post_id ) || ! current_user_can( 'edit_post', $attachment_id ) ) {
			return new WP_Error(
				'npcink_toolbox_local_featured_image_permission_denied',
				__( 'You do not have permission to update this featured image.', 'npcink-toolbox' ),
				array( 'status' => 403 )
			);
		}

		if ( function_exists( 'wp_attachment_is_image' ) && ! wp_attachment_is_image( $attachment_id ) ) {
			return new WP_Error(
				'npcink_toolbox_local_featured_image_attachment_not_image',
				__( 'Local admin consent can set only existing image attachments as featured images.', 'npcink-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$classification = ( new Operation_Classifier() )->classify(
			array(
				'request_source'          => Operation_Classifier::SOURCE_WP_ADMIN_UI,
				'actor_presence'         => Operation_Classifier::ACTOR_PRESENT_CLICK,
				'preview_completeness'    => Operation_Classifier::PREVIEW_EXACT_FINAL,
				'scope'                   => Operation_Classifier::SCOPE_ONE_OBJECT,
				'reversibility'           => Operation_Classifier::REVERSIBILITY_EASY_UNDO,
				'operation_kind'          => Operation_Classifier::KIND_SET_FEATURED_IMAGE,
				'writes_wordpress_state'  => true,
			)
		);
		if ( Operation_Classifier::LOCAL_ADMIN_CONSENT !== (string) ( $classification['classification'] ?? '' ) ) {
			return new WP_Error(
				'npcink_toolbox_local_featured_image_classification_rejected',
				__( 'This featured image action is not eligible for local admin consent.', 'npcink-toolbox' ),
				array(
					'status'         => 422,
					'classification' => $classification,
				)
			);
		}

		$before_attachment_id = absint( get_post_thumbnail_id( $post_id ) );
		$audit_base           = $this->local_featured_image_audit_metadata( $request, $post_id, $attachment_id, $before_attachment_id, $classification );
		$requested_audit      = $this->record_core_local_admin_consent_audit( 'local_admin_consent.requested', $audit_base );
		if ( is_wp_error( $requested_audit ) ) {
			return $requested_audit;
		}

		$set_result = set_post_thumbnail( $post_id, $attachment_id );
		$after_attachment_id = absint( get_post_thumbnail_id( $post_id ) );
		if ( $after_attachment_id !== $attachment_id || ( false === $set_result && $before_attachment_id !== $attachment_id ) ) {
			$this->record_core_local_admin_consent_audit(
				'local_admin_consent.failed',
				array_merge(
					$audit_base,
					array(
						'failure_code'        => 'set_post_thumbnail_failed',
						'after_attachment_id' => $after_attachment_id,
					)
				)
			);

			return new WP_Error(
				'npcink_toolbox_local_featured_image_write_failed',
				__( 'WordPress did not accept the featured image update.', 'npcink-toolbox' ),
				array( 'status' => 500 )
			);
		}

		$completed_audit = $this->record_core_local_admin_consent_audit(
			'local_admin_consent.completed',
			array_merge(
				$audit_base,
				array(
					'after_attachment_id' => $after_attachment_id,
				)
			)
		);
		if ( is_wp_error( $completed_audit ) ) {
			if ( $before_attachment_id > 0 ) {
				set_post_thumbnail( $post_id, $before_attachment_id );
			} else {
				delete_post_thumbnail( $post_id );
			}

			return new WP_Error(
				'npcink_toolbox_local_featured_image_completion_audit_failed',
				__( 'The featured image update could not be fully audited and was rolled back.', 'npcink-toolbox' ),
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response(
			array(
				'artifact_type'          => 'local_admin_consent_featured_image_result',
				'status'                 => 'completed',
				'operation_kind'         => Operation_Classifier::KIND_SET_FEATURED_IMAGE,
				'classification'         => $classification,
				'post_id'                => $post_id,
				'attachment_id'          => $attachment_id,
				'featured_media'         => $attachment_id,
				'previous_attachment_id' => $before_attachment_id,
				'proposal_created'       => false,
				'core_proposal_required' => false,
				'direct_wordpress_write' => true,
				'write_owner'            => 'toolbox_local_admin_consent',
				'audit_owner'            => 'npcink-governance-core',
				'audit'                  => array(
					'requested' => $requested_audit,
					'completed' => $completed_audit,
				),
			)
		);
	}

	public function site_knowledge_review_plan( WP_REST_Request $request ) {
		$params = method_exists( $request, 'get_params' ) ? $request->get_params() : array();
		return rest_ensure_response( $this->client->build_site_knowledge_review_plan( is_array( $params ) ? $params : array() ) );
	}

	public function nightly_inspection_review_plan( WP_REST_Request $request ) {
		$params = method_exists( $request, 'get_params' ) ? $request->get_params() : array();
		return rest_ensure_response( $this->client->build_nightly_inspection_review_plan( is_array( $params ) ? $params : array() ) );
	}

	public function content_metadata_apply_plan( WP_REST_Request $request ) {
		$params = method_exists( $request, 'get_params' ) ? $request->get_params() : array();
		return rest_ensure_response( $this->client->build_content_metadata_apply_plan( is_array( $params ) ? $params : array() ) );
	}

	public function media_brief( WP_REST_Request $request ) {
		$post_id = absint( $request->get_param( 'post_id' ) );
		if ( 0 === $post_id ) {
			return new WP_Error(
				'npcink_toolbox_missing_post_id',
				__( 'A post_id is required for the media brief flow.', 'npcink-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error(
				'npcink_toolbox_post_not_found',
				__( 'The requested post was not found.', 'npcink-toolbox' ),
				array( 'status' => 404 )
			);
		}

		$context = wp_json_encode(
			array(
				'id'      => $post_id,
				'title'   => get_the_title( $post ),
				'type'    => get_post_type( $post ),
				'status'  => get_post_status( $post ),
				'excerpt' => wp_strip_all_tags( get_the_excerpt( $post ) ),
				'content' => wp_trim_words( wp_strip_all_tags( (string) $post->post_content ), 350 ),
			),
			JSON_PRETTY_PRINT
		);

		return rest_ensure_response( $this->client->build_media_brief( (string) $context ) );
	}

	public function editor_content_support( WP_REST_Request $request ) {
		$intent = sanitize_key( (string) ( $request->get_param( 'intent' ) ?: '' ) );
		if ( ! in_array( $intent, array( 'progressive_recommendations', 'writing_support', 'article_checkup', 'title_suggestions', 'article_outline', 'polish_notes', 'summary_suggestions', 'category_suggestions', 'tag_suggestions', 'summary_terms_optimization', 'taxonomy_tags', 'internal_links', 'image_candidates', 'image_alt_suggestions', 'publish_preflight', 'discoverability' ), true ) ) {
			return new WP_Error(
				'npcink_toolbox_invalid_editor_support_intent',
				__( 'A supported editor content-support intent is required.', 'npcink-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$context = $this->editor_post_context( $request );
			if ( 'title_suggestions' === $intent ) {
				$context['context_scope']        = 'full_article';
				$context['selected_text']        = '';
				$context['selected_block_text']  = '';
				$context['selected_block_name']  = '';
			}
			if ( 'polish_notes' === $intent ) {
				$selected_review_text = trim(
					implode(
						' ',
						array_filter(
							array(
								(string) ( $context['selected_text'] ?? '' ),
								(string) ( $context['selected_block_text'] ?? '' ),
							)
						)
					)
				);
				if ( '' === $selected_review_text ) {
					return new WP_Error(
						'npcink_toolbox_missing_editor_selection',
						__( 'Select paragraph text before running a paragraph check.', 'npcink-toolbox' ),
						array( 'status' => 400 )
					);
				}
				$context['context_scope'] = 'selected_text';
			}
			$query   = $this->editor_support_query( $context );
		if ( 'image_candidates' === $intent ) {
			$query = $this->editor_image_support_query( $context );
		}
		if ( '' === $query && 'progressive_recommendations' !== $intent ) {
			return new WP_Error(
				'npcink_toolbox_missing_editor_context',
				__( 'A title, excerpt, or post content is required for editor content support.', 'npcink-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$result = array(
			'artifact_type'          => 'editor_content_support_flow',
			'composition_role'       => 'editor_content_support',
			'intent'                 => $intent,
			'write_posture'          => 'suggestion_only',
			'final_write_path'       => 'core_proposal_required',
			'direct_wordpress_write' => false,
			'post_context'           => $context,
			'query'                  => $query,
			'sections'               => array(),
			'remote_execution_policy' => array(
				'cache_ttl_seconds'     => self::EDITOR_FLOW_CACHE_TTL,
				'cache_scope'           => 'site_transient_by_intent_query_and_post_context',
				'workflow_runtime'      => false,
				'direct_async_queue'    => false,
				'progressive_target_ms' => self::EDITOR_PROGRESSIVE_TARGET_MS,
			),
			'handoff'                => array(
				'surface'                => 'post_editor_sidebar',
				'final_writes'           => 'core_proposal_required',
				'direct_wordpress_write' => false,
			),
		);

		if ( 'progressive_recommendations' === $intent ) {
			$result['sections']['progressive_recommendations'] = $this->editor_progressive_recommendations( $context, $query );
			$result['recommendation_set']                     = $this->editor_recommendation_set( $context, $intent, $result['sections'] );
			$result['content_fingerprint']                    = $result['recommendation_set']['content_fingerprint'];
			return rest_ensure_response( $result );
		}

			if ( 'writing_support' === $intent ) {
				$result['sections']['writing_support'] = $this->editor_support_section(
					$this->editor_cached_site_knowledge(
						array(
						'query'           => $query,
						'intent'          => 'writing_support_plan',
						'current_post_id' => absint( $context['post_id'] ?? 0 ),
						'max_results'     => 6,
					)
				)
				);
			}

			if ( 'article_checkup' === $intent ) {
				$result['sections']['article_checkup'] = $this->editor_article_checkup_section( $context );
			}

			if ( 'taxonomy_tags' === $intent ) {
				$result['sections']['taxonomy_terms'] = $this->editor_taxonomy_term_candidates( $context, $query );
		}

		if ( 'title_suggestions' === $intent ) {
			$result['sections']['title_suggestions'] = $this->editor_hosted_draft_support( $context, 'title_summary' );
		}

		if ( 'article_outline' === $intent ) {
			$result['sections']['article_outline'] = $this->editor_hosted_draft_support( $context, 'article_outline' );
		}

		if ( 'polish_notes' === $intent ) {
			$result['sections']['polish_notes'] = $this->editor_hosted_draft_support( $context, 'polish_notes' );
		}

		if ( 'summary_suggestions' === $intent ) {
			$result['sections']['summary_terms_optimization'] = $this->editor_ai_summary_suggestions( $context, $query );
		}

		if ( 'category_suggestions' === $intent ) {
			$result['sections']['summary_terms_optimization'] = $this->editor_fast_category_suggestions( $context, $query );
		}

		if ( 'tag_suggestions' === $intent ) {
			$result['sections']['summary_terms_optimization'] = $this->editor_fast_tag_suggestions( $context, $query );
		}

		if ( 'summary_terms_optimization' === $intent ) {
			$result['sections']['summary_terms_optimization'] = $this->editor_summary_terms_optimization( $context, $query );
		}

		if ( 'internal_links' === $intent ) {
			$result['sections']['internal_links'] = $this->editor_internal_link_candidates( $context, $query );
		}

		if ( 'image_candidates' === $intent ) {
			$result['sections']['image_candidates'] = $this->editor_image_recommendation_section(
				$this->editor_support_section(
					$this->client->image_candidates(
						$query,
						array(
							'provider'       => 'auto',
							'per_page'       => 6,
							'image_mode'     => 'paragraph' === sanitize_key( (string) ( $context['image_mode'] ?? '' ) ) ? 'paragraph_image' : 'featured_image',
							'latency_mode'   => sanitize_key( (string) ( $context['latency_mode'] ?? '' ) ),
							'visual_context' => $this->editor_image_visual_context( $context, $query ),
						)
					)
				)
			);
		}

		if ( 'image_alt_suggestions' === $intent ) {
			$result['sections']['image_alt_suggestions'] = $this->editor_article_image_alt_suggestions( $context );
		}

		if ( 'discoverability' === $intent || 'publish_preflight' === $intent ) {
			$result['sections']['discoverability'] = $this->editor_support_section(
				$this->editor_cached_content_discoverability(
					array(
						'post_id' => absint( $context['post_id'] ?? 0 ),
						'title'   => (string) ( $context['title'] ?? '' ),
						'topic'   => $query,
						'excerpt' => (string) ( $context['excerpt'] ?? '' ),
						'content' => (string) ( $context['content_text'] ?? '' ),
						'external_search_intent' => 'publish_preflight' === $intent ? 'fact_check' : 'writing_context',
						'include_external_search' => true,
					)
				)
			);
		}

		if ( 'discoverability' === $intent ) {
			$result['sections']['seo_handoff'] = $this->editor_seo_meta_handoff_preview( $context, $result['sections']['discoverability'] );
		}

		if ( 'publish_preflight' === $intent ) {
			$result['sections']['checks'] = $this->editor_publish_preflight_checks( $context );
			$result['sections']['duplicate_check'] = $this->editor_support_section(
				$this->editor_cached_site_knowledge(
					array(
						'query'           => $query,
						'intent'          => 'duplicate_check',
						'current_post_id' => absint( $context['post_id'] ?? 0 ),
						'max_results'     => 5,
					)
				)
			);
			$result['sections']['seo_handoff'] = $this->editor_seo_meta_handoff_preview( $context, $result['sections']['discoverability'] );
			$result['sections']['pre_publish_review'] = $this->editor_pre_publish_review( $context, $result['sections'] );
		}

		$result['recommendation_set']  = $this->editor_recommendation_set( $context, $intent, $result['sections'] );
		$result['content_fingerprint'] = $result['recommendation_set']['content_fingerprint'];

		return rest_ensure_response( $result );
	}

	public function media_derivative_handoff( WP_REST_Request $request ) {
		$params = method_exists( $request, 'get_params' ) ? $request->get_params() : array();
		return rest_ensure_response( $this->client->build_media_derivative_handoff( is_array( $params ) ? $params : array() ) );
	}

	/**
	 * Builds Core-owned audit metadata for the local featured image consent path.
	 *
	 * @param WP_REST_Request     $request Request.
	 * @param int                 $post_id Post id.
	 * @param int                 $attachment_id Attachment id.
	 * @param int                 $before_attachment_id Previous thumbnail id.
	 * @param array<string,mixed> $classification Classification result.
	 * @return array<string,mixed>
	 */
	private function local_featured_image_audit_metadata( WP_REST_Request $request, int $post_id, int $attachment_id, int $before_attachment_id, array $classification ): array {
		$candidate = $request->get_param( 'candidate' );
		$candidate = is_array( $candidate ) ? $candidate : array();
		$title     = sanitize_text_field( (string) ( $candidate['title'] ?? ( $candidate['name'] ?? get_the_title( $attachment_id ) ) ) );
		$source    = sanitize_text_field( (string) ( $candidate['source'] ?? ( $candidate['provider'] ?? 'media_library' ) ) );
		$image_url = esc_url_raw( (string) ( $candidate['url'] ?? ( $candidate['image_url'] ?? wp_get_attachment_url( $attachment_id ) ) ) );

		return array(
			'source_module'          => 'npcink-toolbox',
			'surface'                => 'editor_image_source_modal',
			'operation_kind'         => Operation_Classifier::KIND_SET_FEATURED_IMAGE,
			'classification'         => sanitize_key( (string) ( $classification['classification'] ?? '' ) ),
			'policy_version'         => sanitize_text_field( (string) ( $classification['policy_version'] ?? 'operation-classification-v1' ) ),
			'reasons'                => array_values( array_map( 'sanitize_key', (array) ( $classification['reasons'] ?? array() ) ) ),
			'required_evidence'      => array_values( array_map( 'sanitize_key', (array) ( $classification['required_evidence'] ?? array() ) ) ),
			'actor_user_id'          => get_current_user_id(),
			'target_object_type'     => 'post',
			'target_object_id'       => $post_id,
			'post_id'                => $post_id,
			'attachment_id'          => $attachment_id,
			'before_attachment_id'   => $before_attachment_id,
			'ai_suggestion_summary'  => '' !== $title ? $title : __( 'Set one reviewed existing media image as the featured image.', 'npcink-toolbox' ),
			'image_source'           => $source,
			'image_url'              => $image_url,
			'preview_completeness'   => Operation_Classifier::PREVIEW_EXACT_FINAL,
			'actor_presence'         => Operation_Classifier::ACTOR_PRESENT_CLICK,
			'reversibility'          => Operation_Classifier::REVERSIBILITY_EASY_UNDO,
			'core_proposal_created'  => false,
			'request_or_correlation_id' => sanitize_text_field( (string) ( $request->get_header( 'x-request-id' ) ?: wp_generate_uuid4() ) ),
		);
	}

	/**
	 * Records a local-admin-consent event through Governance Core.
	 *
	 * @param string              $event_name Event name.
	 * @param array<string,mixed> $metadata Event metadata.
	 * @return array<string,mixed>|WP_Error
	 */
	private function record_core_local_admin_consent_audit( string $event_name, array $metadata ) {
		$result = apply_filters( 'npcink_governance_core_record_local_admin_consent', null, $event_name, $metadata );
		if ( null === $result ) {
			return new WP_Error(
				'npcink_toolbox_local_consent_core_audit_unavailable',
				__( 'Governance Core local consent audit is unavailable.', 'npcink-toolbox' ),
				array( 'status' => 503 )
			);
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return is_array( $result ) ? $result : array( 'event_id' => sanitize_text_field( (string) $result ) );
	}

	private function post( string $route, string $method ): void {
		register_rest_route(
			Plugin::REST_NAMESPACE,
			$route,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, $method ),
				'permission_callback' => array( $this, 'permission' ),
			)
		);
	}

	private function get( string $route, string $method ): void {
		register_rest_route(
			Plugin::REST_NAMESPACE,
			$route,
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, $method ),
				'permission_callback' => array( $this, 'permission' ),
			)
		);
	}

	private function required_text( WP_REST_Request $request, string $key, int $max_chars = self::REQUIRED_TEXT_MAX_CHARS ) {
		$value = trim( sanitize_textarea_field( (string) $request->get_param( $key ) ) );
		if ( '' === $value ) {
			return new WP_Error(
				'npcink_toolbox_missing_' . sanitize_key( $key ),
				sprintf(
					/* translators: %s: field name. */
					__( '%s is required.', 'npcink-toolbox' ),
					$key
				),
				array( 'status' => 400 )
			);
		}

		$max_chars = max( 1, $max_chars );
		if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
			if ( mb_strlen( $value ) > $max_chars ) {
				return mb_substr( $value, 0, $max_chars );
			}
			return $value;
		}

		if ( strlen( $value ) > $max_chars ) {
			return substr( $value, 0, $max_chars );
		}

		return $value;
	}

	private function editor_post_context( WP_REST_Request $request ): array {
		$content             = trim( wp_strip_all_tags( (string) $request->get_param( 'content' ) ) );
		$selected_text       = trim( wp_strip_all_tags( (string) $request->get_param( 'selected_text' ) ) );
		$selected_block_text = trim( wp_strip_all_tags( (string) $request->get_param( 'selected_block_text' ) ) );
		$user_instruction    = trim( wp_strip_all_tags( (string) $request->get_param( 'user_instruction' ) ) );
		$context_scope       = sanitize_key( (string) ( $request->get_param( 'context_scope' ) ?: 'auto' ) );
		$summary_mode        = sanitize_key( (string) ( $request->get_param( 'summary_generation_mode' ) ?: 'fast_brief' ) );
		if ( ! in_array( $context_scope, array( 'auto', 'full_article', 'selected_text', 'topic_only' ), true ) ) {
			$context_scope = 'auto';
		}
		if ( ! in_array( $summary_mode, array( 'fast_brief', 'full_context' ), true ) ) {
			$summary_mode = 'fast_brief';
		}

		return array(
			'post_id'             => absint( $request->get_param( 'post_id' ) ),
			'post_type'           => sanitize_key( (string) ( $request->get_param( 'post_type' ) ?: 'post' ) ),
			'post_status'         => sanitize_key( (string) $request->get_param( 'post_status' ) ),
			'context_scope'       => $context_scope,
			'title'               => sanitize_text_field( (string) $request->get_param( 'title' ) ),
			'excerpt'             => sanitize_textarea_field( (string) $request->get_param( 'excerpt' ) ),
			'content_text'        => wp_trim_words( $content, 220, '' ),
			'content_full_text'   => sanitize_textarea_field( $this->editor_trim_chars( $content, self::EDITOR_SUMMARY_FULL_CONTENT_MAX_CHARS ) ),
			'selected_text'       => wp_trim_words( sanitize_textarea_field( $selected_text ), 110, '' ),
			'selected_block_text' => wp_trim_words( sanitize_textarea_field( $selected_block_text ), 110, '' ),
			'selected_block_name' => sanitize_text_field( (string) $request->get_param( 'selected_block_name' ) ),
			'user_instruction'    => wp_trim_words( sanitize_textarea_field( $user_instruction ), 60, '' ),
			'generation_variant'  => sanitize_text_field( (string) $request->get_param( 'generation_variant' ) ),
			'force_regenerate'    => (bool) $request->get_param( 'force_regenerate' ),
			'summary_generation_mode' => $summary_mode,
			'image_mode'          => sanitize_key( (string) $request->get_param( 'image_mode' ) ),
			'category_ids'        => $this->csv_absint_list( (string) $request->get_param( 'category_ids' ) ),
			'tag_ids'             => $this->csv_absint_list( (string) $request->get_param( 'tag_ids' ) ),
			'featured_media'      => absint( $request->get_param( 'featured_media' ) ),
			'media_items'         => $this->editor_media_items_from_request( $request ),
		);
	}

	private function editor_trim_chars( string $value, int $max_chars ): string {
		$value     = trim( $value );
		$max_chars = max( 1, $max_chars );
		if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
			return mb_strlen( $value, 'UTF-8' ) > $max_chars ? mb_substr( $value, 0, $max_chars, 'UTF-8' ) : $value;
		}

		return strlen( $value ) > $max_chars ? substr( $value, 0, $max_chars ) : $value;
	}

	private function editor_media_items_from_request( WP_REST_Request $request ): array {
		$items = array();
		$seen  = array();

		$featured_media = absint( $request->get_param( 'featured_media' ) );
		if ( $featured_media > 0 ) {
			$featured = $this->editor_attachment_media_item( $featured_media, 'featured_media' );
			if ( ! empty( $featured ) ) {
				$items[] = $featured;
				$seen[]  = 'id:' . $featured_media;
			}
		}

		$request_items = $request->get_param( 'media_items' );
		if ( is_string( $request_items ) && '' !== trim( $request_items ) ) {
			$decoded = json_decode( $request_items, true );
			$request_items = is_array( $decoded ) ? $decoded : array();
		}

		foreach ( is_array( $request_items ) ? $request_items : array() as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$media_item = $this->sanitize_editor_media_item( $item );
			if ( empty( $media_item ) ) {
				continue;
			}
			$key = ! empty( $media_item['attachment_id'] )
				? 'id:' . (string) $media_item['attachment_id']
				: 'url:' . (string) ( $media_item['url'] ?? '' );
			if ( '' === $key || in_array( $key, $seen, true ) ) {
				continue;
			}
			$items[] = $media_item;
			$seen[]  = $key;
			if ( count( $items ) >= 12 ) {
				break;
			}
		}

		return $items;
	}

	private function editor_attachment_media_item( int $attachment_id, string $source ): array {
		if ( $attachment_id <= 0 || ( function_exists( 'wp_attachment_is_image' ) && ! wp_attachment_is_image( $attachment_id ) ) ) {
			return array();
		}

		$attachment = function_exists( 'get_post' ) ? get_post( $attachment_id ) : null;
		if ( ! $attachment || 'attachment' !== get_post_type( $attachment ) ) {
			return array();
		}

		$image_src = function_exists( 'wp_get_attachment_image_src' ) ? wp_get_attachment_image_src( $attachment_id, 'thumbnail' ) : false;
		return array(
			'source'        => sanitize_key( $source ),
			'attachment_id' => $attachment_id,
			'title'         => sanitize_text_field( (string) ( $attachment->post_title ?? '' ) ),
			'caption'       => sanitize_textarea_field( (string) ( $attachment->post_excerpt ?? '' ) ),
			'description'   => sanitize_textarea_field( wp_trim_words( wp_strip_all_tags( (string) ( $attachment->post_content ?? '' ) ), 80, '' ) ),
			'alt'           => function_exists( 'get_post_meta' ) ? sanitize_text_field( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ) : '',
			'missing_alt'   => function_exists( 'get_post_meta' ) ? '' === trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ) : true,
			'thumbnail_url' => is_array( $image_src ) ? esc_url_raw( (string) ( $image_src[0] ?? '' ) ) : '',
			'url'           => function_exists( 'wp_get_attachment_url' ) ? esc_url_raw( (string) wp_get_attachment_url( $attachment_id ) ) : '',
		);
	}

	private function sanitize_editor_media_item( array $item ): array {
		$attachment_id = absint( $item['attachment_id'] ?? ( $item['id'] ?? 0 ) );
		if ( $attachment_id > 0 ) {
			$attachment_item = $this->editor_attachment_media_item( $attachment_id, sanitize_key( (string) ( $item['source'] ?? 'content_image' ) ) );
			if ( ! empty( $attachment_item ) ) {
				$attachment_item['url']     = '' !== (string) ( $attachment_item['url'] ?? '' ) ? $attachment_item['url'] : esc_url_raw( (string) ( $item['url'] ?? '' ) );
				$attachment_item['alt']     = '' !== (string) ( $attachment_item['alt'] ?? '' ) ? $attachment_item['alt'] : sanitize_text_field( (string) ( $item['alt'] ?? '' ) );
				$attachment_item['caption'] = '' !== (string) ( $attachment_item['caption'] ?? '' ) ? $attachment_item['caption'] : sanitize_textarea_field( (string) ( $item['caption'] ?? '' ) );
				return $attachment_item;
			}
		}

		$url = esc_url_raw( (string) ( $item['url'] ?? '' ) );
		if ( '' === $url ) {
			return array();
		}

		$alt = sanitize_text_field( (string) ( $item['alt'] ?? '' ) );
		return array(
			'source'        => sanitize_key( (string) ( $item['source'] ?? 'content_image' ) ),
			'attachment_id' => 0,
			'title'         => sanitize_text_field( (string) ( $item['title'] ?? '' ) ),
			'caption'       => sanitize_textarea_field( (string) ( $item['caption'] ?? '' ) ),
			'description'   => sanitize_textarea_field( (string) ( $item['description'] ?? '' ) ),
			'alt'           => $alt,
			'missing_alt'   => '' === trim( $alt ),
			'thumbnail_url' => $url,
			'url'           => $url,
		);
	}

	private function editor_article_image_alt_suggestions( array $context ): array {
		$items = is_array( $context['media_items'] ?? null ) ? $context['media_items'] : array();
		if ( empty( $items ) ) {
			return array(
				'artifact_type'          => 'current_article_image_alt_suggestions.v1',
				'status'                 => 'empty',
				'message'                => __( 'No current article images were found. Add a featured image or image block before requesting ALT suggestions.', 'npcink-toolbox' ),
				'write_posture'          => 'suggestion_only',
				'final_write_path'       => 'core_proposal_required',
				'direct_wordpress_write' => false,
				'items'                  => array(),
			);
		}

		$media_snapshot = array(
			'sample_size'       => count( $items ),
			'missing_alt_count' => count( array_filter( $items, static fn( array $item ): bool => ! empty( $item['missing_alt'] ) ) ),
			'items'             => array_slice( $items, 0, 12 ),
			'snapshot_policy'   => 'current_article_media_metadata_only',
			'post_context'      => array(
				'post_id' => absint( $context['post_id'] ?? 0 ),
				'title'   => sanitize_text_field( (string) ( $context['title'] ?? '' ) ),
				'excerpt' => sanitize_textarea_field( (string) ( $context['excerpt'] ?? '' ) ),
			),
		);

		return $this->editor_support_section(
			$this->client->run_hosted_ai_site_helper(
				array(
					'intent'         => 'media_alt_suggestions',
					'focus'          => __( 'Suggest ALT and caption notes only for images already used by the current article.', 'npcink-toolbox' ),
					'media_snapshot' => $media_snapshot,
					'source_policy'  => 'current_article_media_metadata_only',
				)
			)
		);
	}

	private function image_visual_context_from_request( WP_REST_Request $request, string $query ): array {
		$context = $request->get_param( 'visual_context' );
		if ( is_array( $context ) ) {
			$context['manual_query'] = $context['manual_query'] ?? $query;
			$context['latency_mode'] = $context['latency_mode'] ?? (string) $request->get_param( 'latency_mode' );
			$context['refresh_variant'] = $context['refresh_variant'] ?? (string) $request->get_param( 'refresh_variant' );
			return $this->sanitize_image_visual_context( $context );
		}

		$content = trim( wp_strip_all_tags( (string) $request->get_param( 'content' ) ) );
		return $this->sanitize_image_visual_context(
			array(
				'manual_query'        => $query,
				'title'               => (string) $request->get_param( 'title' ),
				'excerpt'             => (string) $request->get_param( 'excerpt' ),
				'content_summary'     => wp_trim_words( $content, 80, '' ),
				'selected_text'       => (string) $request->get_param( 'selected_text' ),
				'selected_block_text' => (string) $request->get_param( 'selected_block_text' ),
				'selected_block_name' => (string) $request->get_param( 'selected_block_name' ),
				'image_mode'          => (string) $request->get_param( 'image_mode' ),
				'latency_mode'        => (string) $request->get_param( 'latency_mode' ),
				'refresh_variant'     => (string) $request->get_param( 'refresh_variant' ),
			)
		);
	}

	private function editor_image_visual_context( array $context, string $query ): array {
		return $this->sanitize_image_visual_context(
			array(
				'manual_query'        => '',
				'fallback_query'      => $query,
				'title'               => (string) ( $context['title'] ?? '' ),
				'excerpt'             => (string) ( $context['excerpt'] ?? '' ),
				'content_summary'     => (string) ( $context['content_text'] ?? '' ),
				'selected_text'       => (string) ( $context['selected_text'] ?? '' ),
				'selected_block_text' => (string) ( $context['selected_block_text'] ?? '' ),
				'selected_block_name' => (string) ( $context['selected_block_name'] ?? '' ),
				'image_mode'          => (string) ( $context['image_mode'] ?? '' ),
				'latency_mode'        => (string) ( $context['latency_mode'] ?? '' ),
			)
		);
	}

	private function sanitize_image_visual_context( array $context ): array {
		$mode = sanitize_key( (string) ( $context['image_mode'] ?? $context['image_use'] ?? '' ) );
		if ( ! in_array( $mode, array( 'featured', 'featured_image', 'paragraph', 'paragraph_image', 'inline', 'inline_image', 'setting', 'setting_image' ), true ) ) {
			$mode = 'featured_image';
		}
		if ( 'featured' === $mode ) {
			$mode = 'featured_image';
		}
		if ( 'paragraph' === $mode ) {
			$mode = 'paragraph_image';
		}
		if ( 'inline' === $mode ) {
			$mode = 'inline_image';
		}
		if ( 'setting' === $mode ) {
			$mode = 'setting_image';
		}

		return array(
			'image_mode'          => $mode,
			'manual_query'        => sanitize_text_field( (string) ( $context['manual_query'] ?? '' ) ),
			'fallback_query'      => sanitize_text_field( (string) ( $context['fallback_query'] ?? '' ) ),
			'post_id'             => max( 0, absint( $context['post_id'] ?? 0 ) ),
			'title'               => wp_trim_words( sanitize_text_field( (string) ( $context['title'] ?? '' ) ), 18, '' ),
			'excerpt'             => wp_trim_words( sanitize_textarea_field( (string) ( $context['excerpt'] ?? '' ) ), 36, '' ),
			'content_summary'     => wp_trim_words( sanitize_textarea_field( (string) ( $context['content_summary'] ?? $context['content_text'] ?? $context['content'] ?? '' ) ), 80, '' ),
			'selected_text'       => wp_trim_words( sanitize_textarea_field( (string) ( $context['selected_text'] ?? '' ) ), 80, '' ),
			'selected_block_text' => wp_trim_words( sanitize_textarea_field( (string) ( $context['selected_block_text'] ?? '' ) ), 80, '' ),
			'selected_block_name' => sanitize_key( (string) ( $context['selected_block_name'] ?? '' ) ),
			'avoid_brand_logos'   => ! empty( $context['avoid_brand_logos'] ),
			'latency_mode'        => sanitize_key( (string) ( $context['latency_mode'] ?? '' ) ),
			'refresh_variant'     => sanitize_text_field( (string) ( $context['refresh_variant'] ?? '' ) ),
			'query_intent'        => array(
				'rewrite_abstract_terms'       => ! empty( $context['query_intent']['rewrite_abstract_terms'] ),
				'prefer_concrete_visual_scene' => ! empty( $context['query_intent']['prefer_concrete_visual_scene'] ),
				'return_alternate_queries'     => ! empty( $context['query_intent']['return_alternate_queries'] ),
			),
		);
	}

	private function editor_support_query( array $context ): string {
		$scope     = sanitize_key( (string) ( $context['context_scope'] ?? 'auto' ) );
		$instruction = trim( (string) ( $context['user_instruction'] ?? '' ) );
		$selection = trim(
			implode(
				' ',
				array_filter(
					array(
						(string) ( $context['selected_text'] ?? '' ),
						(string) ( $context['selected_block_text'] ?? '' ),
					)
				)
			)
		);
		$selected_scope_text = '' !== $selection ? $selection : trim( (string) ( $context['content_text'] ?? '' ) );

		if ( 'selected_text' === $scope && '' !== $selected_scope_text ) {
			return wp_trim_words(
				trim(
					implode(
						' ',
						array_filter(
							array(
								$selected_scope_text,
								(string) ( $context['title'] ?? '' ),
								$instruction,
							)
						)
					)
				),
				80,
				''
			);
		}

		if ( 'topic_only' === $scope ) {
			return wp_trim_words(
				trim(
					implode(
						' ',
						array_filter(
							array(
								(string) ( $context['title'] ?? '' ),
								(string) ( $context['excerpt'] ?? '' ),
								$instruction,
							)
						)
					)
				),
				80,
				''
			);
		}

		$query = trim(
			implode(
				' ',
				array_filter(
					array(
						'auto' === $scope ? $selection : '',
						(string) ( $context['title'] ?? '' ),
						(string) ( $context['excerpt'] ?? '' ),
						(string) ( $context['content_text'] ?? '' ),
						$instruction,
					)
				)
			)
		);

		return wp_trim_words( $query, 80, '' );
	}

	private function editor_input_scope( array $context ): array {
		$scope     = sanitize_key( (string) ( $context['context_scope'] ?? 'auto' ) );
		$selection = trim( (string) ( $context['selected_text'] ?? '' ) . ' ' . (string) ( $context['selected_block_text'] ?? '' ) );
		if ( 'auto' === $scope ) {
			$scope = '' !== $selection ? 'selected_text' : ( '' !== trim( (string) ( $context['content_text'] ?? '' ) ) ? 'full_article' : 'topic_only' );
		}

		$labels = array(
			'selected_text' => __( 'Selected text or supplied snippet', 'npcink-toolbox' ),
			'full_article'  => __( 'Full article context', 'npcink-toolbox' ),
			'topic_only'    => __( 'Topic or short brief', 'npcink-toolbox' ),
		);

		$fields = array();
		foreach ( array( 'title', 'excerpt', 'content_text', 'selected_text', 'selected_block_text', 'post_id' ) as $field ) {
			if ( ! empty( $context[ $field ] ) ) {
				$fields[] = $field;
			}
		}

		return array(
			'id'                     => $scope,
			'label'                  => $labels[ $scope ] ?? __( 'Current context', 'npcink-toolbox' ),
			'source_fields'          => $fields,
			'operator_selected_mode' => sanitize_key( (string) ( $context['context_scope'] ?? 'auto' ) ),
			'detail'                 => __( 'This scope controls ranking context only. Toolbox still returns suggestions and does not write WordPress data.', 'npcink-toolbox' ),
		);
	}

	private function editor_image_support_query( array $context ): string {
		$instruction = trim( sanitize_textarea_field( (string) ( $context['user_instruction'] ?? '' ) ) );
		$selection = trim(
			implode(
				' ',
				array_filter(
					array(
						(string) ( $context['selected_text'] ?? '' ),
						(string) ( $context['selected_block_text'] ?? '' ),
					)
				)
			)
		);
		$seed = trim(
			implode(
				' ',
				array_filter(
					array(
						$selection,
						$instruction,
						(string) ( $context['title'] ?? '' ),
						(string) ( $context['excerpt'] ?? '' ),
						'' === $selection ? (string) ( $context['content_text'] ?? '' ) : '',
					)
				)
			)
		);

		if ( '' === $seed ) {
			return '';
		}

		$visual_terms = array();
		$lower_seed   = strtolower( $seed );
		$term_map     = array(
			'seo'       => 'search engine optimization',
			'aeo'       => 'answer engine optimization',
			'geo'       => 'generative engine optimization',
			'ai'        => 'artificial intelligence',
			'wordpress' => 'wordpress publishing',
			'content'   => 'content strategy',
			'上下文'        => 'editorial context',
			'文章'         => 'editorial content',
			'段落'         => 'editorial paragraph',
			'事实'         => 'evidence documentation',
			'表达'         => 'clear communication',
			'创作'         => 'creative writing workspace',
			'读者'         => 'reader research',
		);
		foreach ( $term_map as $needle => $visual_term ) {
			$pattern = preg_match( '/^[a-z0-9]+$/', $needle ) ? '/(?<![a-z0-9])' . preg_quote( $needle, '/' ) . '(?![a-z0-9])/' : '/' . preg_quote( $needle, '/' ) . '/u';
			if ( preg_match( $pattern, $lower_seed ) ) {
				$visual_terms[] = $visual_term;
			}
		}

		if ( ! empty( $visual_terms ) ) {
			return wp_trim_words( implode( ' ', array_unique( $visual_terms ) ) . ' digital marketing workspace analytics', 16, '' );
		}

		if ( '' !== $selection ) {
			return wp_trim_words( $selection, 12, '' );
		}

		$title = trim( sanitize_text_field( (string) ( $context['title'] ?? '' ) ) );
		if ( '' !== $title ) {
			return wp_trim_words( $title, 12, '' );
		}

		$excerpt = trim( sanitize_textarea_field( (string) ( $context['excerpt'] ?? '' ) ) );
		if ( '' !== $excerpt ) {
			return wp_trim_words( $excerpt, 12, '' );
		}

		return wp_trim_words( wp_strip_all_tags( (string) ( $context['content_text'] ?? '' ) ), 12, '' );
	}

	private function editor_support_section( $value ): array {
		if ( is_wp_error( $value ) ) {
			return array(
				'status'                 => 'error',
				'code'                   => sanitize_key( (string) $value->get_error_code() ),
				'message'                => sanitize_text_field( $value->get_error_message() ),
				'write_posture'          => 'suggestion_only',
				'direct_wordpress_write' => false,
			);
		}

		return is_array( $value ) ? $value : array();
	}

	private function editor_progressive_recommendations( array $context, string $query ): array {
		$taxonomy_terms     = '' !== $query ? $this->editor_taxonomy_term_candidates( $context, $query ) : $this->editor_local_taxonomy_profile( $context );
		$taxonomy_items     = is_array( $taxonomy_terms['items'] ?? null ) ? $taxonomy_terms['items'] : array();
		$category_items     = array_values(
			array_filter(
				$taxonomy_items,
				static fn( array $item ): bool => 'category' === (string) ( $item['taxonomy'] ?? '' )
			)
		);
		$tag_items          = array_values(
			array_filter(
				$taxonomy_items,
				static fn( array $item ): bool => 'post_tag' === (string) ( $item['taxonomy'] ?? '' )
			)
		);
		$media_items        = $this->editor_media_library_candidates( $context, $query );
		$preflight_checks   = $this->editor_publish_preflight_checks( $context );
		$preflight_candidates = $this->editor_preflight_recommendation_candidates( $preflight_checks );
		$taxonomy_recommendations = '' !== trim( $query )
			? array_merge(
				$this->editor_taxonomy_recommendation_candidates( 'category_suggestions', $category_items, array(), $this->empty_proposed_new_terms_review() ),
				$this->editor_taxonomy_recommendation_candidates( 'tag_suggestions', array(), $tag_items, $this->empty_proposed_new_terms_review() )
			)
			: array();
		$recommendations    = array_merge(
			$this->editor_filter_high_confidence_taxonomy_recommendations( $taxonomy_recommendations ),
			$this->editor_media_library_recommendation_candidates( $media_items ),
			$preflight_candidates
		);

		return array(
			'artifact_type'              => 'editor_progressive_recommendations.v1',
			'composition_role'           => 'local_progressive_prefetch',
			'candidate_type'             => 'progressive_recommendations',
			'candidate_contract'         => 'recommendation_candidate.v1',
			'latency_profile'            => 'local_300ms',
			'target_latency_ms'          => 300,
			'write_posture'              => 'suggestion_only',
			'final_write_path'           => 'core_proposal_required',
			'direct_wordpress_write'     => false,
			'content_fingerprint'        => $this->editor_content_fingerprint( $context ),
			'available_context'          => array(
				'post_id'                 => absint( $context['post_id'] ?? 0 ),
				'post_type'               => sanitize_key( (string) ( $context['post_type'] ?? 'post' ) ),
				'has_title'               => '' !== trim( (string) ( $context['title'] ?? '' ) ),
				'has_excerpt'             => '' !== trim( (string) ( $context['excerpt'] ?? '' ) ),
				'content_words'           => str_word_count( (string) ( $context['content_text'] ?? '' ) ),
				'category_count'          => count( $category_items ),
				'tag_count'               => count( $tag_items ),
				'media_library_count'     => count( $media_items ),
				'context_source'          => '' !== $query ? 'current_draft_and_local_wordpress' : 'local_wordpress_prefetch',
			),
			'category_candidates'        => array_slice( $category_items, 0, 2 ),
			'tag_candidates'             => array_slice( $tag_items, 0, 8 ),
			'media_library_candidates'   => $media_items,
			'preflight_candidates'       => $preflight_candidates,
			'recommendation_candidates'  => array_slice( $recommendations, 0, self::EDITOR_PROGRESSIVE_CANDIDATE_LIMIT ),
			'preflight_checks'           => $preflight_checks,
			'next_fast_intents'          => array_values(
				array_filter(
					array(
						'' !== trim( (string) ( $context['title'] ?? '' ) ) ? 'title_suggestions' : '',
						str_word_count( (string) ( $context['content_text'] ?? '' ) ) >= 80 ? 'summary_suggestions' : '',
						! empty( $category_items ) ? 'category_suggestions' : '',
						! empty( $tag_items ) ? 'tag_suggestions' : '',
						empty( $context['featured_media'] ) && ! empty( $media_items ) ? 'image_candidates' : '',
					)
				)
			),
			'deferred_enhancements'      => array(
				'cloud_title_generation',
				'hosted_summary_generation',
				'cloud_image_source_search',
				'site_knowledge_internal_links',
				'publish_preflight_duplicate_check',
			),
			'remote_execution_policy'    => array(
				'cloud_calls'             => false,
				'workflow_runtime'        => false,
				'direct_async_queue'      => false,
				'fallback_for_timeout_ms' => self::EDITOR_PROGRESSIVE_TARGET_MS,
			),
		);
	}

	private function editor_recommendation_set( array $context, string $intent, array $sections ): array {
		$content_fingerprint = $this->editor_content_fingerprint( $context );
		$artifact_counts     = array(
			'titles'         => $this->editor_recommendation_count_by_kind( $sections, 'title' ),
			'excerpts'       => $this->editor_recommendation_count_by_kind( $sections, 'excerpt' ),
			'categories'     => $this->editor_recommendation_count_by_kind( $sections, 'category' ),
			'tags'           => $this->editor_recommendation_count_by_kind( $sections, 'tag' ),
			'featured_image' => $this->editor_recommendation_count_by_kind( $sections, 'image' ),
			'internal_links' => $this->editor_recommendation_count_by_kind( $sections, 'internal_link' ),
			'preflight'      => $this->editor_recommendation_count_by_kind( $sections, 'preflight' ),
		);
		$retrieval_sources   = $this->editor_recommendation_set_sources( $sections );
		$proposal_targets    = $this->editor_recommendation_set_proposal_targets( $sections );

		return array(
			'recommendation_set_id' => 'rec_' . substr( hash( 'sha256', $content_fingerprint . '|' . $intent . '|' . wp_json_encode( $artifact_counts ) ), 0, 20 ),
			'contract_version'      => 'editor_recommendation_set.v1',
			'generated_at'          => gmdate( 'c' ),
			'source_layer'          => $this->editor_recommendation_set_source_layer( $sections ),
			'latency_profile'       => 'progressive_recommendations' === $intent ? 'local_300ms' : 'focused_intent',
			'content_fingerprint'   => $content_fingerprint,
			'intent'                => sanitize_key( $intent ),
			'artifacts'             => $artifact_counts,
			'artifact_counts'       => $artifact_counts,
			'candidates'            => $this->editor_recommendation_set_candidate_refs( $sections ),
			'retrieval_sources'     => $retrieval_sources,
			'proposal_targets'      => $proposal_targets,
			'no_write'              => true,
			'governance'            => array(
				'dry_run_available'     => true,
				'requires_proposal'     => $this->editor_recommendation_set_required_proposals( $sections ),
				'write_posture'         => 'suggestion_only',
				'direct_wordpress_write' => false,
			),
			'debug'                 => array(
				'retrieval_sources'     => $retrieval_sources,
				'cache_ttl_seconds'     => self::EDITOR_FLOW_CACHE_TTL,
				'progressive_target_ms' => self::EDITOR_PROGRESSIVE_TARGET_MS,
			),
		);
	}

	private function editor_content_fingerprint( array $context ): string {
		$payload = array(
			'post_id'             => absint( $context['post_id'] ?? 0 ),
			'post_type'           => sanitize_key( (string) ( $context['post_type'] ?? 'post' ) ),
			'title'               => (string) ( $context['title'] ?? '' ),
			'excerpt'             => (string) ( $context['excerpt'] ?? '' ),
			'content_text'        => (string) ( $context['content_text'] ?? '' ),
			'selected_text'       => (string) ( $context['selected_text'] ?? '' ),
			'selected_block_text' => (string) ( $context['selected_block_text'] ?? '' ),
			'category_ids'        => array_map( 'absint', is_array( $context['category_ids'] ?? null ) ? $context['category_ids'] : array() ),
			'tag_ids'             => array_map( 'absint', is_array( $context['tag_ids'] ?? null ) ? $context['tag_ids'] : array() ),
			'featured_media'      => absint( $context['featured_media'] ?? 0 ),
		);
		return 'sha256:' . hash( 'sha256', (string) wp_json_encode( $payload ) );
	}

	private function editor_recommendation_count_by_kind( array $sections, string $kind ): int {
		$count = 0;
		foreach ( $sections as $section ) {
			if ( ! is_array( $section ) || ! is_array( $section['recommendation_candidates'] ?? null ) ) {
				continue;
			}
			foreach ( $section['recommendation_candidates'] as $candidate ) {
				if ( is_array( $candidate ) && $kind === (string) ( $candidate['kind'] ?? '' ) ) {
					++$count;
				}
			}
		}
		return $count;
	}

	private function editor_recommendation_set_required_proposals( array $sections ): array {
		$required = array();
		foreach ( $sections as $section ) {
			if ( ! is_array( $section ) || ! is_array( $section['recommendation_candidates'] ?? null ) ) {
				continue;
			}
			foreach ( $section['recommendation_candidates'] as $candidate ) {
				if ( is_array( $candidate ) && 'core_proposal_required' === (string) ( $candidate['action_policy'] ?? '' ) ) {
					$target = sanitize_key( (string) ( $candidate['target_field'] ?? $candidate['kind'] ?? 'candidate' ) );
					if ( '' !== $target && ! in_array( $target, $required, true ) ) {
						$required[] = $target;
					}
				}
			}
		}
		return $required;
	}

	private function editor_recommendation_set_candidate_refs( array $sections ): array {
		$refs = array();
		foreach ( $sections as $section ) {
			if ( ! is_array( $section ) || ! is_array( $section['recommendation_candidates'] ?? null ) ) {
				continue;
			}
			foreach ( $section['recommendation_candidates'] as $candidate ) {
				if ( ! is_array( $candidate ) ) {
					continue;
				}
				$id = sanitize_key( (string) ( $candidate['id'] ?? '' ) );
				if ( '' === $id ) {
					continue;
				}
				$refs[] = array(
					'candidate_id'  => $id,
					'kind'          => sanitize_key( (string) ( $candidate['kind'] ?? 'generic' ) ),
					'target_field'  => sanitize_key( (string) ( $candidate['target_field'] ?? '' ) ),
					'action_policy' => sanitize_key( (string) ( $candidate['action_policy'] ?? 'suggestion_only' ) ),
				);
			}
		}
		return $refs;
	}

	private function editor_recommendation_set_proposal_targets( array $sections ): array {
		$targets = array();
		$seen    = array();
		foreach ( $sections as $section ) {
			if ( ! is_array( $section ) || ! is_array( $section['recommendation_candidates'] ?? null ) ) {
				continue;
			}
			foreach ( $section['recommendation_candidates'] as $candidate ) {
				if ( ! is_array( $candidate ) || 'core_proposal_required' !== (string) ( $candidate['action_policy'] ?? '' ) ) {
					continue;
				}
				$candidate_id = sanitize_key( (string) ( $candidate['id'] ?? '' ) );
				if ( '' === $candidate_id ) {
					continue;
				}
				$kind          = sanitize_key( (string) ( $candidate['kind'] ?? 'generic' ) );
				$target_field  = sanitize_key( (string) ( $candidate['target_field'] ?? $kind ) );
				$ability_id    = $this->editor_recommendation_target_ability_id( $target_field, $kind );
				$dedupe_key    = $candidate_id . '|' . $target_field . '|' . $ability_id;
				if ( isset( $seen[ $dedupe_key ] ) ) {
					continue;
				}
				$seen[ $dedupe_key ] = true;
				$targets[]           = array(
					'candidate_id'             => $candidate_id,
					'candidate_kind'           => $kind,
					'target_field'             => $target_field,
					'proposal_target'          => 'core_ability_handoff',
					'required_ability_id'      => $ability_id,
					'proposed_payload_preview' => $this->editor_recommendation_payload_preview( $candidate, $target_field, $ability_id ),
					'handoff_status'           => 'definition_only_user_trigger_required',
					'direct_wordpress_write'   => false,
				);
			}
		}
		return $targets;
	}

	private function editor_recommendation_set_source_layer( array $sections ): string {
		foreach ( $sections as $section ) {
			if ( is_array( $section ) && ! empty( $section['provider_execution'] ) ) {
				return 'cloud';
			}
		}
		return 'local';
	}

	private function editor_recommendation_target_ability_id( string $target_field, string $kind ): string {
		$field = sanitize_key( $target_field );
		$type  = sanitize_key( $kind );
		if ( in_array( $field, array( 'category', 'post_tag', 'taxonomy_terms' ), true ) || in_array( $type, array( 'category', 'tag' ), true ) ) {
			return 'npcink-abilities-toolkit/set-post-terms';
		}
		if ( in_array( $field, array( 'featured_media', 'featured_image' ), true ) || 'image' === $type ) {
			return 'npcink-abilities-toolkit/set-post-featured-image';
		}
		if ( in_array( $field, array( 'seo_meta', 'seo_title', 'seo_description' ), true ) ) {
			return 'npcink-abilities-toolkit/set-post-seo-meta';
		}
		return 'npcink-abilities-toolkit/update-post';
	}

	private function editor_recommendation_payload_preview( array $candidate, string $target_field, string $ability_id ): array {
		$value = sanitize_text_field( (string) ( $candidate['value'] ?? '' ) );
		return array(
			'candidate_id'       => sanitize_key( (string) ( $candidate['id'] ?? '' ) ),
			'target_field'       => sanitize_key( $target_field ),
			'ability_id'         => sanitize_text_field( $ability_id ),
			'value_preview'      => substr( $value, 0, 160 ),
			'source_contract'    => 'editor_recommendation_set.v1',
			'dry_run'            => true,
			'commit'             => false,
			'operator_triggered' => true,
		);
	}

	private function editor_recommendation_set_sources( array $sections ): array {
		$sources = array( 'current_editor_context' );
		foreach ( $sections as $section ) {
			if ( ! is_array( $section ) ) {
				continue;
			}
			if ( ! empty( $section['available_context'] ) || ! empty( $section['taxonomy_terms'] ) || ! empty( $section['category_candidates'] ) || ! empty( $section['tag_candidates'] ) ) {
				$sources[] = 'site_taxonomy';
			}
			if ( ! empty( $section['media_library_candidates'] ) ) {
				$sources[] = 'media_library';
			}
			if ( ! empty( $section['provider_execution'] ) ) {
				$sources[] = sanitize_key( (string) $section['provider_execution'] );
			}
			if ( ! empty( $section['ranking_context']['related_content_terms'] ) || ! empty( $section['related_context_summary'] ) ) {
				$sources[] = 'site_knowledge';
			}
		}
		return array_values( array_unique( array_filter( $sources ) ) );
	}

	private function editor_local_taxonomy_profile( array $context ): array {
		$post_type  = sanitize_key( (string) ( $context['post_type'] ?? 'post' ) );
		$taxonomies = array_values(
			array_intersect(
				get_object_taxonomies( $post_type ),
				array( 'category', 'post_tag' )
			)
		);
		$items      = array();
		foreach ( $taxonomies as $taxonomy ) {
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
					'number'     => 'category' === $taxonomy ? 12 : 24,
					'orderby'    => 'count',
					'order'      => 'DESC',
				)
			);
			if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
				continue;
			}
			foreach ( $terms as $term ) {
				$items[] = array(
					'term_id'                      => (int) $term->term_id,
					'taxonomy'                     => sanitize_key( $taxonomy ),
					'name'                         => sanitize_text_field( $term->name ),
					'slug'                         => sanitize_title( $term->slug ),
					'score'                        => min( 10, max( 1, absint( $term->count ?? 0 ) ) ),
					'status'                       => 'existing_term',
					'controlled_vocabulary_status' => 'existing_wordpress_term',
					'normalization_key'            => sanitize_title( $term->name ),
					'matched_tokens'               => array(),
					'match_signals'                => array( 'existing_taxonomy_vocabulary', 'local_taxonomy_profile' ),
					'related_context'              => array(),
					'evidence_refs'                => array(),
					'reason'                       => __( 'Existing WordPress term from the local site taxonomy profile. Review against the current draft before applying.', 'npcink-toolbox' ),
				);
			}
		}
		return array(
			'candidate_type'         => 'taxonomy_tag_candidates',
			'write_posture'          => 'suggestion_only',
			'direct_wordpress_write' => false,
			'ranking_context'        => array(
				'draft_query_overlap'   => false,
				'local_prefetch_only'   => true,
				'related_content_terms' => false,
			),
			'items'                  => $items,
		);
	}

	private function editor_media_library_candidates( array $context, string $query ): array {
		if ( ! function_exists( 'get_posts' ) ) {
			return array();
		}
		$attachments = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => 'image',
				'posts_per_page' => 12,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		$items       = array();
		foreach ( is_array( $attachments ) ? $attachments : array() as $attachment ) {
			if ( ! is_object( $attachment ) ) {
				continue;
			}
			$item = $this->editor_attachment_media_item( absint( $attachment->ID ?? 0 ), 'media_library_prefetch' );
			if ( empty( $item ) ) {
				continue;
			}
			$score         = $this->editor_contextual_match_score( implode( ' ', array( $item['title'] ?? '', $item['alt'] ?? '', $item['caption'] ?? '', $item['description'] ?? '' ) ), $context, $query );
			$item['score'] = $score;
			$item['recency_rank'] = count( $items );
			$item['reason'] = $score > 0
				? __( 'Existing media matched weighted title, excerpt, selected text, or draft terms.', 'npcink-toolbox' )
				: __( 'Recent existing media candidate from the local library. Review visual fit before adoption.', 'npcink-toolbox' );
			$items[]       = $item;
		}
		usort(
			$items,
			static function ( array $left, array $right ): int {
				$score_compare = (int) ( $right['score'] ?? 0 ) <=> (int) ( $left['score'] ?? 0 );
				if ( 0 !== $score_compare ) {
					return $score_compare;
				}
				return (int) ( $left['recency_rank'] ?? 0 ) <=> (int) ( $right['recency_rank'] ?? 0 );
			}
		);
		return array_slice( $items, 0, 8 );
	}

	private function editor_media_library_recommendation_candidates( array $media_items ): array {
		$candidates = array();
		foreach ( array_slice( $media_items, 0, 4 ) as $index => $item ) {
			$attachment_id = absint( $item['attachment_id'] ?? 0 );
			if ( $attachment_id <= 0 ) {
				continue;
			}
			$match_score   = (int) ( $item['score'] ?? 0 );
			$quality_score = min( 95, 55 + $match_score * 8 );
			$candidates[]  = $this->editor_recommendation_candidate(
				array(
					'id'                   => 'media_library_' . $attachment_id,
					'kind'                 => 'image',
					'label'                => $match_score > 0 ? ( 0 === $index ? __( 'Existing media candidate', 'npcink-toolbox' ) : __( 'Media library option', 'npcink-toolbox' ) ) : __( 'Recent media review item', 'npcink-toolbox' ),
					'value'                => (string) ( $item['title'] ?? ( $item['url'] ?? '' ) ),
					'reason'               => (string) ( $item['reason'] ?? '' ),
					'confidence'           => $quality_score / 100,
					'target_field'         => 'featured_media',
					'action_policy'        => $match_score > 0 ? 'core_proposal_required' : 'operator_review_only_no_write',
					'quality_status'       => $match_score > 0 && $quality_score >= 70 ? 'good' : 'review',
					'quality_score'        => $quality_score,
					'quality_issues'       => array(
						$match_score > 0
							? __( 'Existing media still requires operator visual review before use.', 'npcink-toolbox' )
							: __( 'Recent media has no strong text match; treat it as a review-only local reference.', 'npcink-toolbox' ),
					),
					'evidence_refs'        => array( 'attachment:' . $attachment_id ),
					'source_candidate_ref' => 'attachment:' . $attachment_id,
				)
			);
		}
		return $candidates;
	}

	private function editor_filter_high_confidence_taxonomy_recommendations( array $candidates ): array {
		return array_values(
			array_filter(
				$candidates,
				static function ( array $candidate ): bool {
					$issues = is_array( $candidate['quality_issues'] ?? null ) ? implode( ' ', $candidate['quality_issues'] ) : '';
					$reason = (string) ( $candidate['reason'] ?? '' );
					if (
						'weak' === (string) ( $candidate['quality_status'] ?? '' )
						|| false !== strpos( $issues, '仅描述字段匹配' )
						|| false !== strpos( $issues, '只有一个较弱 token 匹配' )
					) {
						return false;
					}
					return false !== strpos( $issues, '匹配当前草稿' )
						|| false !== strpos( $issues, 'Matched tokens:' )
						|| false !== strpos( $issues, '历史相关' )
						|| false !== strpos( $issues, 'Site Knowledge' )
						|| false !== strpos( $reason, 'Matched tokens:' )
						|| false !== strpos( $reason, 'matched the draft' )
						|| false !== strpos( $reason, 'matched against the current' )
						|| false !== strpos( $reason, 'Site Knowledge' );
				}
			)
		);
	}

	private function editor_preflight_recommendation_candidates( array $preflight_checks ): array {
		$items      = is_array( $preflight_checks['items'] ?? null ) ? $preflight_checks['items'] : array();
		$candidates = array();
		$targets    = array(
			'title'          => 'post_title',
			'excerpt'        => 'post_excerpt',
			'terms'          => 'taxonomy_terms',
			'featured_media' => 'featured_media',
		);
		$priority   = array(
			'title'          => 10,
			'excerpt'        => 20,
			'terms'          => 30,
			'featured_media' => 40,
		);
		usort(
			$items,
			static function ( array $left, array $right ) use ( $priority ): int {
				$left_id  = sanitize_key( (string) ( $left['id'] ?? 'preflight' ) );
				$right_id = sanitize_key( (string) ( $right['id'] ?? 'preflight' ) );
				return (int) ( $priority[ $left_id ] ?? 99 ) <=> (int) ( $priority[ $right_id ] ?? 99 );
			}
		);

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) || 'ok' === (string) ( $item['status'] ?? '' ) ) {
				continue;
			}
			$id     = sanitize_key( (string) ( $item['id'] ?? 'preflight' ) );
			$status = sanitize_key( (string) ( $item['status'] ?? 'warning' ) );
			$score  = 'error' === $status ? 35 : 55;
			$candidates[] = $this->editor_recommendation_candidate(
				array(
					'id'             => 'preflight_' . $id,
					'kind'           => 'preflight',
					'label'          => sanitize_text_field( (string) ( $item['label'] ?? __( 'Preflight review', 'npcink-toolbox' ) ) ),
					'value'          => sanitize_text_field( (string) ( $item['detail'] ?? '' ) ),
					'reason'         => __( 'Local pre-publish check found a review item before any Cloud enhancement or Core proposal handoff.', 'npcink-toolbox' ),
					'confidence'     => 0.9,
					'target_field'   => $targets[ $id ] ?? $id,
					'action_policy'  => 'operator_review_only_no_write',
					'quality_status' => 'error' === $status ? 'weak' : 'review',
					'quality_score'  => $score,
					'quality_issues' => array( sanitize_text_field( (string) ( $item['detail'] ?? '' ) ) ),
					'evidence_refs'  => array( 'local_preflight:' . $id ),
				)
			);
		}

		return $candidates;
	}

	private function editor_cached_site_knowledge( array $input ) {
		return $this->editor_cached_client_result(
			'site_knowledge',
			$input,
			function () use ( $input ) {
				return $this->client->search_site_knowledge( $input );
			}
		);
	}

	private function editor_cached_site_knowledge_hit( array $input ): array {
		$cached = $this->editor_cached_client_hit( 'site_knowledge', $input );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		return array(
			'status'                 => 'skipped',
			'cache_status'           => 'miss',
			'skip_reason'            => 'nonblocking_summary_fast_brief_uses_site_knowledge_cache_hit_only',
			'write_posture'          => 'suggestion_only',
			'direct_wordpress_write' => false,
		);
	}

	private function editor_cached_content_discoverability( array $input ) {
		return $this->editor_cached_client_result(
			'content_discoverability',
			$input,
			function () use ( $input ) {
				return $this->client->build_content_discoverability_brief( $input );
			}
		);
	}

	private function editor_cached_hosted_ai_content_support( array $input, bool $force_refresh = false ) {
		return $this->editor_cached_client_result(
			'hosted_ai_content_support',
			$input,
			function () use ( $input ) {
				return $this->client->run_hosted_ai_content_support( $input );
			},
			$force_refresh
		);
	}

	private function editor_cached_client_result( string $namespace, array $input, callable $callback, bool $force_refresh = false ) {
		$cache_key = $this->editor_flow_cache_key( $namespace, $input );
		$cached    = $force_refresh ? false : get_transient( $cache_key );
		if ( false !== $cached && is_array( $cached ) ) {
			$cached['cache_status'] = 'hit';
			return $cached;
		}

		$result = $callback();
		if ( ! is_wp_error( $result ) && is_array( $result ) ) {
			$result['cache_status'] = $force_refresh ? 'bypass' : 'miss';
			if ( ! $force_refresh ) {
				set_transient( $cache_key, $result, self::EDITOR_FLOW_CACHE_TTL );
			}
		}

		return $result;
	}

	private function editor_cached_client_hit( string $namespace, array $input ): ?array {
		$cached = get_transient( $this->editor_flow_cache_key( $namespace, $input ) );
		if ( false !== $cached && is_array( $cached ) ) {
			$cached['cache_status'] = 'hit';
			return $cached;
		}

		return null;
	}

	private function editor_flow_cache_key( string $namespace, array $input ): string {
		$json = wp_json_encode( $input );
		if ( ! is_string( $json ) ) {
			$json = serialize( $input );
		}

		return 'npcink_toolbox_editor_' . sanitize_key( $namespace ) . '_' . md5( $json );
	}

	private function editor_image_recommendation_section( array $section ): array {
		$section['candidate_contract']        = 'recommendation_candidate.v1';
		$section['recommendation_candidates'] = $this->editor_image_recommendation_candidates( $section );

		return $section;
	}

	private function editor_image_recommendation_candidates( array $section ): array {
		$candidates = array();
		foreach ( array_slice( $this->editor_image_candidate_items( $section ), 0, 8 ) as $index => $item ) {
			$title = sanitize_text_field(
				(string) (
					$item['title']
					?? $item['alt_description']
					?? $item['description']
					?? $item['prompt']
					?? ''
				)
			);
			$url = esc_url_raw(
				(string) (
					$item['download_url']
					?? $item['regular_url']
					?? $item['small_url']
					?? $item['url']
					?? ''
				)
			);
			if ( '' === $title && '' === $url ) {
				continue;
			}

			$warnings = array();
			foreach ( array( 'warnings', 'risk_flags', 'quality_tags' ) as $key ) {
				if ( is_array( $item[ $key ] ?? null ) ) {
					$warnings = array_merge( $warnings, array_map( 'sanitize_text_field', $item[ $key ] ) );
				}
			}
			$license_status = sanitize_key( (string) ( $item['license_review_status'] ?? '' ) );
			if ( '' !== $license_status && 'not_required' !== $license_status && 'clear' !== $license_status ) {
				$warnings[] = __( '图片授权或来源需要人工确认。', 'npcink-toolbox' );
			}

			$match_score = is_numeric( $item['match_score'] ?? null ) ? (float) $item['match_score'] : 0.0;
			$quality_score = $match_score > 0
				? ( $match_score <= 1 ? 55 + (int) round( max( 0, min( 1, $match_score ) ) * 35 ) : max( 0, min( 90, (int) round( $match_score ) ) ) )
				: 65;
			if ( ! empty( $warnings ) ) {
				$quality_score = min( $quality_score, 65 );
			}
			if ( '' === $url ) {
				$quality_score = min( $quality_score, 45 );
				$warnings[]    = __( '缺少可采用的图片 URL。', 'npcink-toolbox' );
			}
			if ( empty( $warnings ) ) {
				$warnings[] = __( '保留完整图片候选用于授权、归因和采用计划审查。', 'npcink-toolbox' );
			}
			$source_ref = sanitize_text_field( (string) ( $item['id'] ?? '' ) );
			if ( '' === $source_ref ) {
				$source_ref = '' !== $url ? $url : 'image_candidate_' . ( $index + 1 );
			}

			$candidates[] = $this->editor_recommendation_candidate(
				array(
					'id'                   => 'image_candidate_' . ( $index + 1 ),
					'kind'                 => 'image',
					'label'                => '' !== $title ? $title : __( 'Image candidate', 'npcink-toolbox' ),
					'value'                => '' !== $url ? $url : $title,
					'reason'               => sanitize_text_field( (string) ( $item['match_reason'] ?? $item['reason'] ?? '' ) ),
					'confidence'           => $match_score > 0 && $match_score <= 1 ? $match_score : null,
					'target_field'         => 'featured_image',
					'action_policy'        => 'core_proposal_required',
					'quality_status'       => $quality_score >= 70 ? 'review' : ( $quality_score >= 50 ? 'review' : 'weak' ),
					'quality_score'        => $quality_score,
					'quality_issues'       => array_values( array_unique( $warnings ) ),
					'evidence_refs'        => array_filter(
						array(
							'' !== (string) ( $item['provider'] ?? '' ) ? 'image_provider:' . sanitize_key( (string) $item['provider'] ) : '',
							'' !== (string) ( $item['source_type'] ?? '' ) ? 'image_source_type:' . sanitize_key( (string) $item['source_type'] ) : '',
						)
					),
					'source_candidate_ref' => $source_ref,
				)
			);
		}

		return $candidates;
	}

	private function editor_image_candidate_items( array $section ): array {
		foreach ( array( 'image_candidates', 'images', 'image_source_candidates', 'source_candidates', 'media_candidates', 'assets', 'candidates' ) as $key ) {
			if ( is_array( $section[ $key ] ?? null ) ) {
				return array_values( array_filter( $section[ $key ], 'is_array' ) );
			}
		}

		return array();
	}

	private function editor_summary_terms_optimization( array $context, string $query ): array {
		$related_content = $this->editor_support_section(
			$this->editor_cached_site_knowledge(
				array(
					'query'           => $query,
					'intent'          => 'related_content',
					'current_post_id' => absint( $context['post_id'] ?? 0 ),
					'max_results'     => 6,
				)
			)
		);
		$taxonomy_terms  = $this->editor_taxonomy_term_candidates( $context, $query, $related_content );
		$summary_ai     = $this->editor_support_section(
			$this->editor_cached_hosted_ai_content_support(
				array(
					'intent'                  => 'summary_terms_optimization',
					'post_id'                 => absint( $context['post_id'] ?? 0 ),
					'title'                   => (string) ( $context['title'] ?? '' ),
					'excerpt'                 => (string) ( $context['excerpt'] ?? '' ),
					'content'                 => (string) ( $context['content_text'] ?? '' ),
					'related_content_context' => $this->editor_related_content_context_for_ai( $related_content ),
				)
			)
		);
		$discoverability = $this->editor_support_section(
			$this->editor_cached_content_discoverability(
				array(
					'post_id'                 => absint( $context['post_id'] ?? 0 ),
					'title'                   => (string) ( $context['title'] ?? '' ),
					'topic'                   => $query,
					'excerpt'                 => (string) ( $context['excerpt'] ?? '' ),
					'content'                 => (string) ( $context['content_text'] ?? '' ),
					'external_search_intent'  => 'writing_context',
					'include_external_search' => true,
				)
			)
		);

		$items      = is_array( $taxonomy_terms['items'] ?? null ) ? $taxonomy_terms['items'] : array();
		$categories = array_values(
			array_filter(
				$items,
				static fn( array $item ): bool => 'category' === (string) ( $item['taxonomy'] ?? '' )
			)
		);
		$tags       = array_values(
			array_filter(
				$items,
				static fn( array $item ): bool => 'post_tag' === (string) ( $item['taxonomy'] ?? '' )
			)
		);
		$summary_layers     = $this->editor_summary_layer_candidates( $context, $related_content );
		$proposed_new_terms = $this->empty_proposed_new_terms_review();
		$handoff_preview    = $this->editor_summary_terms_handoff_preview( $summary_layers, $categories, $tags, $proposed_new_terms );
		$metadata_delta     = $this->editor_content_metadata_delta( $context, $query, $summary_layers, $categories, $tags, $proposed_new_terms, $related_content, $discoverability, $handoff_preview );

		return array(
			'artifact_type'          => 'article_discoverability_optimization.v1',
			'composition_role'       => 'summary_taxonomy_tag_candidates',
			'candidate_type'         => 'summary_terms_optimization',
			'write_posture'          => 'suggestion_only',
			'final_write_path'       => 'core_proposal_required',
			'direct_wordpress_write' => false,
			'input_scope'            => $this->editor_input_scope( $context ),
			'summary_candidates'     => $summary_ai,
			'summary_layers'         => $summary_layers,
			'category_candidates'    => array_slice( $categories, 0, 5 ),
			'tag_candidates'         => array_slice( $tags, 0, 8 ),
			'proposed_new_terms'     => $proposed_new_terms,
			'taxonomy_terms'         => $taxonomy_terms,
			'related_content'        => $related_content,
			'discoverability'        => $discoverability,
			'optimization_strategy'  => $this->editor_summary_terms_strategy(),
			'review_metrics'         => $this->editor_summary_terms_review_metrics(),
			'handoff_preview'        => $handoff_preview,
			'content_metadata_delta' => $metadata_delta,
			'risk_notes'             => array(
				__( 'Reject summaries that add facts not present in the draft, site context, or cited evidence.', 'npcink-toolbox' ),
				__( 'Prefer existing categories and tags; defer new vocabulary to a later taxonomy governance workflow.', 'npcink-toolbox' ),
				__( 'Use related Site Knowledge results to avoid duplicate coverage and taxonomy drift.', 'npcink-toolbox' ),
			),
			'handoff'                => array(
				'final_writes'           => 'core_proposal_required',
				'direct_wordpress_write' => false,
				'next_steps'             => array(
					__( 'Review the summary, category, and tag candidates in the editor.', 'npcink-toolbox' ),
					__( 'Prepare a Core proposal only after an operator chooses accepted metadata changes.', 'npcink-toolbox' ),
				),
			),
		);
	}

	private function editor_content_metadata_delta( array $context, string $query, array $summary_layers, array $categories, array $tags, array $proposed_new_terms, array $related_content, array $discoverability, array $handoff_preview ): array {
		$evidence_refs = $this->editor_content_metadata_evidence_refs( $context, $related_content, $discoverability );
		$summary_items = is_array( $summary_layers['items'] ?? null ) ? $summary_layers['items'] : array();
		$excerpt_item  = $summary_items[0] ?? array();
		$authorization = ( new Operation_Classifier() )->classify(
			array(
				'request_source'          => Operation_Classifier::SOURCE_WP_ADMIN_UI,
				'actor_presence'         => Operation_Classifier::ACTOR_PRESENT_CLICK,
				'preview_completeness'    => Operation_Classifier::PREVIEW_SUFFICIENT,
				'scope'                   => Operation_Classifier::SCOPE_ONE_OBJECT,
				'reversibility'           => Operation_Classifier::REVERSIBILITY_EASY_UNDO,
				'operation_kind'          => Operation_Classifier::KIND_SUGGEST,
				'writes_wordpress_state'  => false,
			)
		);

		return array(
			'artifact_type'          => 'content_metadata_delta',
			'version'                => 1,
			'target_post_id'         => absint( $context['post_id'] ?? 0 ),
			'write_posture'          => 'suggestion_only',
			'final_write_path'       => 'core_proposal_required',
			'direct_wordpress_write' => false,
			'issue_record'           => array(
				'user_expression'    => '' !== trim( $query ) ? $query : __( 'Improve summary, category, and tag discoverability for the current draft.', 'npcink-toolbox' ),
				'target_post'        => array(
					'id'             => absint( $context['post_id'] ?? 0 ),
					'type'           => sanitize_key( (string) ( $context['post_type'] ?? 'post' ) ),
					'status'         => sanitize_key( (string) ( $context['post_status'] ?? '' ) ),
					'title'          => sanitize_text_field( (string) ( $context['title'] ?? '' ) ),
					'category_ids'   => array_values( array_map( 'absint', is_array( $context['category_ids'] ?? null ) ? $context['category_ids'] : array() ) ),
					'tag_ids'        => array_values( array_map( 'absint', is_array( $context['tag_ids'] ?? null ) ? $context['tag_ids'] : array() ) ),
				),
				'observed_signals'  => $this->editor_content_metadata_observed_signals( $context, $categories, $tags, $proposed_new_terms ),
				'context_refs'      => $evidence_refs,
			),
			'diagnosis'              => array(
				'summary_quality'   => $this->editor_summary_quality( $context ),
				'taxonomy_quality'  => $this->editor_taxonomy_quality( $context, $categories, $tags, $proposed_new_terms ),
				'hypotheses'        => array(
					__( 'A clearer excerpt can improve archive, social, and answer-summary presentation without rewriting the article body.', 'npcink-toolbox' ),
					__( 'Existing WordPress terms should be reused; new vocabulary belongs in a later taxonomy governance workflow.', 'npcink-toolbox' ),
					__( 'Related Site Knowledge evidence can reveal duplicate coverage and proven term patterns.', 'npcink-toolbox' ),
				),
				'warnings'          => array(
					__( 'Do not accept summaries that add unsupported claims.', 'npcink-toolbox' ),
					__( 'Do not use the editor recommendation loop to create categories or tags.', 'npcink-toolbox' ),
					__( 'Do not treat related-content evidence as indexing or RAG lifecycle ownership inside Toolbox.', 'npcink-toolbox' ),
				),
				'evidence_strength' => empty( $evidence_refs ) ? 'draft_only' : 'draft_plus_tool_context',
			),
			'delta'                  => array(
				'excerpt'             => array(
					'recommended'   => sanitize_text_field( (string) ( $excerpt_item['value'] ?? '' ) ),
					'reason'        => sanitize_text_field( (string) ( $excerpt_item['reason'] ?? __( 'Use the short summary candidate only after operator review.', 'npcink-toolbox' ) ) ),
					'evidence_refs' => $this->editor_content_metadata_evidence_ids( $evidence_refs ),
				),
				'categories'          => $this->editor_content_metadata_term_delta_items( array_slice( $categories, 0, 5 ), $evidence_refs ),
				'tags'                => $this->editor_content_metadata_term_delta_items( array_slice( $tags, 0, 8 ), $evidence_refs ),
				'new_term_candidates' => $this->editor_content_metadata_new_term_delta_items( $proposed_new_terms ),
			),
			'authorization'          => array(
				'classification'          => sanitize_key( (string) ( $authorization['classification'] ?? Operation_Classifier::SUGGESTION_ONLY ) ),
				'reason'                  => __( 'This Content Metadata Delta only recommends excerpt and existing-term changes. Accepted writes must use the Core handoff preview and reusable WordPress abilities.', 'npcink-toolbox' ),
				'reasons'                 => array_values( array_map( 'sanitize_key', (array) ( $authorization['reasons'] ?? array() ) ) ),
				'required_evidence'       => array_values(
					array_unique(
						array_merge(
							array_map( 'sanitize_key', (array) ( $authorization['required_evidence'] ?? array() ) ),
							array(
								'operator_selected_final_excerpt_or_existing_terms',
								'exact_or_sufficient_preview_before_any_apply_action',
								'core_proposal_required_for_incomplete_preview_or_future_taxonomy_governance',
							)
						)
					)
				),
				'policy_version'          => sanitize_text_field( (string) ( $authorization['policy_version'] ?? 'operation-classification-v1' ) ),
				'local_admin_consent_note' => __( 'A later local-admin-consent path requires a present administrator, one post, exact preview, and activity evidence before any direct local apply can be considered.', 'npcink-toolbox' ),
				'handoff_preview_ref'      => sanitize_text_field( (string) ( $handoff_preview['artifact_type'] ?? 'summary_terms_handoff_preview.v1' ) ),
			),
			'outcome_contract'       => array(
				'checks' => array(
					'excerpt_reviewed_with_no_unsupported_claims',
					'existing_categories_or_tags_reused_when_possible',
					'related_content_terms_used_for_ranking_only',
					'new_term_candidates_deferred_to_taxonomy_governance',
					'no_toolbox_direct_wordpress_write',
					'accepted_write_like_changes_route_through_core_or_future_classified_local_consent',
				),
			),
			'learning_candidates'    => array(
				'accepted_excerpt_style',
				'accepted_existing_category_or_tag_patterns',
				'future_taxonomy_gap_feedback',
				'duplicate_topic_or_taxonomy_noise_feedback',
			),
		);
	}

	private function editor_content_metadata_observed_signals( array $context, array $categories, array $tags, array $proposed_new_terms ): array {
		$signals = array();
		if ( '' === trim( (string) ( $context['excerpt'] ?? '' ) ) ) {
			$signals[] = 'missing_excerpt';
		}
		if ( empty( $context['category_ids'] ) ) {
			$signals[] = 'no_current_categories_supplied';
		}
		if ( empty( $context['tag_ids'] ) ) {
			$signals[] = 'no_current_tags_supplied';
		}
		if ( ! empty( $categories ) ) {
			$signals[] = 'existing_category_candidates_available';
		}
		if ( ! empty( $tags ) ) {
			$signals[] = 'existing_tag_candidates_available';
		}
		if ( ! empty( $proposed_new_terms['items'] ) ) {
			$signals[] = 'proposed_new_terms_require_review';
		}

		return array_values( array_unique( $signals ) );
	}

	private function editor_summary_quality( array $context ): string {
		$excerpt = trim( sanitize_textarea_field( (string) ( $context['excerpt'] ?? '' ) ) );
		if ( '' === $excerpt ) {
			return 'missing';
		}

		return strlen( $excerpt ) < 80 ? 'weak' : 'acceptable';
	}

	private function editor_taxonomy_quality( array $context, array $categories, array $tags, array $proposed_new_terms ): string {
		$current_categories = is_array( $context['category_ids'] ?? null ) ? array_filter( array_map( 'absint', $context['category_ids'] ) ) : array();
		$current_tags       = is_array( $context['tag_ids'] ?? null ) ? array_filter( array_map( 'absint', $context['tag_ids'] ) ) : array();
		if ( empty( $current_categories ) && empty( $current_tags ) ) {
			return 'missing';
		}
		if ( ! empty( $proposed_new_terms['items'] ) || 12 < count( $current_tags ) ) {
			return 'noisy';
		}
		if ( empty( $categories ) && empty( $tags ) ) {
			return 'acceptable';
		}

		return 'weak';
	}

	private function editor_related_content_items( array $related_content ): array {
		$items = is_array( $related_content['results'] ?? null )
			? $related_content['results']
			: ( is_array( $related_content['items'] ?? null ) ? $related_content['items'] : array() );

		return array_values(
			array_filter(
				$items,
				static fn( $item ): bool => is_array( $item )
			)
		);
	}

	private function editor_related_content_summary( array $related_content ): array {
		$items        = $this->editor_related_content_items( $related_content );
		$evidence_ids = array();
		$titles       = array();

		foreach ( array_slice( $items, 0, 6 ) as $index => $item ) {
			$ref_id = (string) ( $item['post_id'] ?? ( $item['id'] ?? $index ) );
			$evidence_ids[] = 'site_knowledge:' . sanitize_key( $ref_id );
			$title = sanitize_text_field( (string) ( $item['title'] ?? $item['name'] ?? '' ) );
			if ( '' !== $title ) {
				$titles[] = $title;
			}
		}

		return array(
			'available'     => array() !== $items,
			'result_count'  => count( $items ),
			'evidence_refs' => array_values( array_unique( $evidence_ids ) ),
			'top_titles'    => array_slice( array_values( array_unique( $titles ) ), 0, 5 ),
			'policy'        => 'related_context_checks_duplicate_coverage_and_term_fit_without_adding_new_facts',
		);
	}

	private function editor_related_content_context_for_ai( array $related_content ): array {
		$items = $this->editor_related_content_items( $related_content );
		$context_items = array();

		foreach ( array_slice( $items, 0, 6 ) as $item ) {
			$post_id = absint( $item['post_id'] ?? 0 );
			$context_items[] = array(
				'post_id'         => $post_id,
				'title'           => sanitize_text_field( (string) ( $item['title'] ?? $item['name'] ?? '' ) ),
				'score'           => is_numeric( $item['score'] ?? null ) ? (float) $item['score'] : null,
				'excerpt'         => sanitize_textarea_field( wp_trim_words( wp_strip_all_tags( (string) ( $item['excerpt'] ?? $item['snippet'] ?? $item['content_excerpt'] ?? '' ) ), 55, '' ) ),
				'existing_terms'  => $this->editor_related_post_terms_for_context( $post_id ),
			);
		}

		return array(
			'policy' => 'related_context_for_summary_and_term_review_only_no_new_facts_no_writes',
			'items'  => array_values( array_filter( $context_items, static fn( array $item ): bool => '' !== (string) ( $item['title'] ?? '' ) || 0 < absint( $item['post_id'] ?? 0 ) ) ),
		);
	}

	private function editor_summary_vector_context_for_ai( array $summary_context ): array {
		$items = $this->editor_related_content_items( $summary_context );
		$context_items = array();

		foreach ( array_slice( $items, 0, 5 ) as $index => $item ) {
			$post_id = absint( $item['post_id'] ?? 0 );
			$ref_id  = sanitize_key( (string) ( $item['post_id'] ?? ( $item['id'] ?? $index ) ) );
			$context_items[] = array(
				'evidence_ref' => 'site_knowledge:' . $ref_id,
				'post_id'      => $post_id,
				'title'        => sanitize_text_field( (string) ( $item['title'] ?? $item['name'] ?? '' ) ),
				'score'        => is_numeric( $item['score'] ?? null ) ? (float) $item['score'] : null,
				'excerpt'      => sanitize_textarea_field( wp_trim_words( wp_strip_all_tags( (string) ( $item['excerpt'] ?? $item['snippet'] ?? $item['content_excerpt'] ?? '' ) ), 42, '' ) ),
			);
		}

		return array(
			'policy' => 'cloud_vector_context_for_fast_summary_brief_only_current_draft_remains_primary_source',
			'items'  => array_values( array_filter( $context_items, static fn( array $item ): bool => '' !== (string) ( $item['title'] ?? '' ) || '' !== (string) ( $item['excerpt'] ?? '' ) ) ),
		);
	}

	private function editor_internal_link_candidates( array $context, string $query ): array {
		$knowledge = $this->editor_support_section(
			$this->editor_cached_site_knowledge(
				array(
					'query'           => $query,
					'intent'          => 'internal_links',
					'current_post_id' => absint( $context['post_id'] ?? 0 ),
					'max_results'     => 8,
				)
			)
		);
		$items     = $this->editor_internal_link_candidate_items( $context, $knowledge );

		return array(
			'artifact_type'          => 'internal_link_candidates.v1',
			'candidate_type'         => 'internal_link_candidates',
			'candidate_contract'     => 'recommendation_candidate.v1',
			'write_posture'          => 'suggestion_only',
			'final_write_path'       => 'operator_review_only_no_insert',
			'direct_wordpress_write' => false,
			'input_scope'            => $this->editor_input_scope( $context ),
			'items'                  => $items,
			'recommendation_candidates' => $this->editor_internal_link_recommendation_candidates( $items ),
			'source_knowledge'       => $knowledge,
			'review_policy'          => array(
				'link_insertion_owner'      => 'human_editor',
				'automatic_anchor_insert'   => false,
				'post_content_patch_handoff' => false,
				'current_post_excluded'     => true,
			),
			'handoff'                => array(
				'final_writes'           => 'operator_review_only_no_insert',
				'direct_wordpress_write' => false,
				'blocked_actions'        => array(
					'no_link_insertion_in_toolbox',
					'no_patch_post_content_handoff_yet',
					'no_automatic_anchor_insertion',
				),
				'next_steps'             => array(
					__( 'Review whether the target article genuinely helps the reader before inserting a link manually.', 'npcink-toolbox' ),
					__( 'Choose anchor text from the article wording; do not let Toolbox rewrite the paragraph.', 'npcink-toolbox' ),
				),
			),
		);
	}

	private function editor_internal_link_candidate_items( array $context, array $knowledge ): array {
		$current_post_id = absint( $context['post_id'] ?? 0 );
		$items           = array();

		foreach ( array_slice( $this->editor_related_content_items( $knowledge ), 0, 8 ) as $index => $item ) {
			$target_post_id = absint( $item['post_id'] ?? ( $item['id'] ?? 0 ) );
			if ( 0 < $target_post_id && $target_post_id === $current_post_id ) {
				continue;
			}

			$title = sanitize_text_field( (string) ( $item['title'] ?? $item['name'] ?? '' ) );
			$url   = $this->editor_internal_link_target_url( $item, $target_post_id );
			if ( '' === $title && '' === $url ) {
				continue;
			}

			$items[] = array(
				'title'                 => '' !== $title ? $title : __( 'Related internal target', 'npcink-toolbox' ),
				'target_post_id'        => $target_post_id,
				'target_url'            => $url,
				'suggested_anchor_text' => $this->editor_internal_link_anchor_text( $title ),
				'placement_hint'        => __( 'Review near the paragraph where this topic is mentioned; Toolbox does not insert the link.', 'npcink-toolbox' ),
				'reason'                => $this->editor_internal_link_reason( $item ),
				'evidence_refs'         => array( 'site_knowledge:' . sanitize_key( (string) ( $target_post_id ?: $index ) ) ),
				'score'                 => is_numeric( $item['score'] ?? null ) ? (float) $item['score'] : null,
				'status'                => 'review_only_candidate',
			);
		}

		return $items;
	}

	private function editor_internal_link_recommendation_candidates( array $items ): array {
		$candidates = array();
		foreach ( array_slice( $items, 0, 8 ) as $index => $item ) {
			$title  = sanitize_text_field( (string) ( $item['title'] ?? '' ) );
			$anchor = sanitize_text_field( (string) ( $item['suggested_anchor_text'] ?? '' ) );
			$url    = esc_url_raw( (string) ( $item['target_url'] ?? '' ) );
			if ( '' === $title && '' === $anchor && '' === $url ) {
				continue;
			}

			$has_score = is_numeric( $item['score'] ?? null );
			$score     = $has_score ? (float) $item['score'] : 0.0;
			if ( ! $has_score ) {
				$quality_score = 65;
			} elseif ( $score <= 1 ) {
				$quality_score = 55 + (int) round( max( 0, min( 1, $score ) ) * 35 );
			} else {
				$quality_score = max( 0, min( 90, (int) round( $score ) ) );
			}
			$quality_issues = array( __( '人工确认目标文章与当前段落或全文语义相关后再插入。', 'npcink-toolbox' ) );
			if ( '' === $url ) {
				$quality_score   = min( $quality_score, 55 );
				$quality_issues[] = __( '缺少目标 URL，插入前需要人工补充或确认。', 'npcink-toolbox' );
			}

			$candidates[] = $this->editor_recommendation_candidate(
				array(
					'id'                   => 'internal_link_' . ( $index + 1 ),
					'kind'                 => 'internal_link',
					'label'                => '' !== $title ? $title : __( 'Internal link candidate', 'npcink-toolbox' ),
					'value'                => '' !== $anchor ? $anchor : $url,
					'reason'               => sanitize_text_field( (string) ( $item['reason'] ?? '' ) ),
					'confidence'           => $has_score && $score > 0 && $score <= 1 ? $score : null,
					'target_field'         => 'post_content',
					'action_policy'        => 'operator_review_only_no_insert',
					'quality_status'       => $quality_score >= 60 && '' !== $url ? 'review' : 'weak',
					'quality_score'        => $quality_score,
					'quality_issues'       => $quality_issues,
					'evidence_refs'        => is_array( $item['evidence_refs'] ?? null ) ? $item['evidence_refs'] : array(),
					'source_candidate_ref' => '' !== $url ? $url : 'internal_link_item_' . ( $index + 1 ),
				)
			);
		}

		return $candidates;
	}

	private function editor_internal_link_target_url( array $item, int $target_post_id ): string {
		foreach ( array( 'url', 'permalink', 'link', 'source_url' ) as $key ) {
			$value = esc_url_raw( (string) ( $item[ $key ] ?? '' ) );
			if ( '' !== $value ) {
				return $value;
			}
		}

		if ( 0 < $target_post_id ) {
			$permalink = get_permalink( $target_post_id );
			return is_string( $permalink ) ? esc_url_raw( $permalink ) : '';
		}

		return '';
	}

	private function editor_internal_link_anchor_text( string $title ): string {
		$anchor = trim( sanitize_text_field( wp_trim_words( $title, 8, '' ) ) );
		return '' !== $anchor ? $anchor : __( 'Related article', 'npcink-toolbox' );
	}

	private function editor_internal_link_reason( array $item ): string {
		$reason = trim( sanitize_text_field( (string) ( $item['reason'] ?? $item['summary'] ?? '' ) ) );
		if ( '' !== $reason ) {
			return $reason;
		}

		if ( is_numeric( $item['score'] ?? null ) ) {
			return sprintf(
				/* translators: %s: similarity score. */
				__( 'Site Knowledge returned this internal target with similarity score %s. Review relevance before inserting manually.', 'npcink-toolbox' ),
				(string) $item['score']
			);
		}

		return __( 'Site Knowledge returned this as related public content. Review relevance before inserting manually.', 'npcink-toolbox' );
	}

	private function editor_ai_summary_suggestions( array $context, string $query ): array {
		$summary_mode           = in_array( (string) ( $context['summary_generation_mode'] ?? 'fast_brief' ), array( 'fast_brief', 'full_context' ), true ) ? (string) $context['summary_generation_mode'] : 'fast_brief';
		$summary_vector_context = array();
		$timing                 = array(
			'summary_generation_mode'        => $summary_mode,
			'site_knowledge_ms'              => 0,
			'site_knowledge_cache_status'    => 'skipped',
			'site_knowledge_blocking'        => false,
			'hosted_ai_ms'                   => 0,
			'hosted_ai_cache_status'         => 'unknown',
			'fast_brief_target_seconds'      => '3_5',
			'vector_context_included'        => false,
		);
		if ( 'fast_brief' === $summary_mode ) {
			$site_knowledge_started = microtime( true );
			$summary_vector_context = $this->editor_support_section(
				$this->editor_cached_site_knowledge_hit(
					array(
						'query'           => $query,
						'intent'          => 'summary_context',
						'current_post_id' => absint( $context['post_id'] ?? 0 ),
						'max_results'     => 5,
					)
				)
			);
			$timing['site_knowledge_ms']           = (int) round( ( microtime( true ) - $site_knowledge_started ) * 1000 );
			$timing['site_knowledge_cache_status'] = sanitize_key( (string) ( $summary_vector_context['cache_status'] ?? 'miss' ) );
		}

		$summary_vector_context_for_ai       = $this->editor_summary_vector_context_for_ai( $summary_vector_context );
		$timing['vector_context_included']   = ! empty( $summary_vector_context_for_ai['items'] );
		$force_regenerate                    = ! empty( $context['force_regenerate'] );
		$hosted_ai_started                   = microtime( true );
		$summary_ai = $this->editor_support_section(
			$this->editor_cached_hosted_ai_content_support(
				array(
					'intent'             => 'summary_suggestions',
					'post_id'            => absint( $context['post_id'] ?? 0 ),
					'title'              => (string) ( $context['title'] ?? '' ),
					'excerpt'            => (string) ( $context['excerpt'] ?? '' ),
					'content'            => (string) ( $context['content_full_text'] ?? $context['content_text'] ?? '' ),
					'user_instruction'   => (string) ( $context['user_instruction'] ?? '' ),
					'generation_variant' => (string) ( $context['generation_variant'] ?? '' ),
					'summary_generation_mode' => $summary_mode,
					'summary_vector_context'  => $summary_vector_context_for_ai,
				),
				$force_regenerate
			)
		);
		$timing['hosted_ai_ms']           = (int) round( ( microtime( true ) - $hosted_ai_started ) * 1000 );
		$timing['hosted_ai_cache_status'] = sanitize_key( (string) ( $summary_ai['cache_status'] ?? 'unknown' ) );

		return $this->editor_summary_only_suggestion_section( $summary_ai, $context, $timing );
	}

	private function editor_summary_only_suggestion_section( array $summary_ai, array $context, array $timing = array() ): array {
		$summary_layers = $this->editor_ai_summary_layer_candidates( $summary_ai, $context );

		return array(
			'artifact_type'          => 'article_summary_suggestions.v1',
			'composition_role'       => 'summary_candidates_only',
			'candidate_type'         => 'summary_suggestions',
			'candidate_contract'     => 'recommendation_candidate.v1',
			'write_posture'          => 'suggestion_only',
			'final_write_path'       => 'editor_apply_preview_save_required',
			'direct_wordpress_write' => false,
			'summary_layers'         => $summary_layers,
			'summary_candidates'     => $summary_ai,
			'provider_execution'     => 'hosted_ai',
			'generation_mode'        => 'ai_summary',
			'summary_generation_mode' => in_array( (string) ( $context['summary_generation_mode'] ?? 'fast_brief' ), array( 'fast_brief', 'full_context' ), true ) ? (string) $context['summary_generation_mode'] : 'fast_brief',
			'generation_variant'     => sanitize_text_field( (string) ( $context['generation_variant'] ?? '' ) ),
			'timing'                 => $timing,
			'quality_contract'       => is_array( $summary_ai['quality_contract'] ?? null ) ? $summary_ai['quality_contract'] : array(),
			'review_checklist'       => is_array( $summary_ai['review_checklist'] ?? null ) ? $summary_ai['review_checklist'] : array(),
		);
	}

	private function editor_article_checkup_section( array $context ): array {
		$text       = trim( (string) ( $context['content_full_text'] ?? $context['content_text'] ?? '' ) );
		$title      = trim( (string) ( $context['title'] ?? '' ) );
		$excerpt    = trim( (string) ( $context['excerpt'] ?? '' ) );
		$paragraphs = $this->editor_article_checkup_paragraphs( $text );
		$items      = array();

		foreach ( $paragraphs as $index => $paragraph ) {
			$length          = $this->editor_text_length( $paragraph );
			$sentence_parts  = preg_split( '/[。！？!?；;]+/u', $paragraph );
			$longest_sentence = 0;
			foreach ( is_array( $sentence_parts ) ? $sentence_parts : array() as $sentence ) {
				$longest_sentence = max( $longest_sentence, $this->editor_text_length( trim( (string) $sentence ) ) );
			}
			$location = sprintf(
				/* translators: %d: paragraph number. */
				__( 'Paragraph %d', 'npcink-toolbox' ),
				$index + 1
			);

			if ( $length >= 220 ) {
				$items[] = $this->editor_article_checkup_issue(
					'paragraph_too_long_' . ( $index + 1 ),
					'format',
					'warning',
					$location,
					$paragraph,
					__( 'Paragraph is dense and may be hard to scan.', 'npcink-toolbox' ),
					__( 'Consider splitting the paragraph by claim, condition, or conclusion. Keep the editor responsible for the final wording.', 'npcink-toolbox' )
				);
			}

			if ( $longest_sentence >= 95 ) {
				$items[] = $this->editor_article_checkup_issue(
					'long_sentence_' . ( $index + 1 ),
					'clarity',
					'warning',
					$location,
					$paragraph,
					__( 'One sentence carries too many clauses.', 'npcink-toolbox' ),
					__( 'Review whether the sentence should be broken into shorter factual steps before publishing.', 'npcink-toolbox' )
				);
			}

			if ( 1 === preg_match( '/(\d|万|倍|%|百分|快|慢|耗时|性能|测试|经测试|同等|相当|无明显|明显|适合)/u', $paragraph ) ) {
				$items[] = $this->editor_article_checkup_issue(
					'fact_claim_' . ( $index + 1 ),
					'fact_gap',
					'warning',
					$location,
					$paragraph,
					__( 'The paragraph contains a metric, comparison, performance, or scope claim.', 'npcink-toolbox' ),
					__( 'Verify the source, test condition, comparison object, and applicable scope. Do not let Toolbox turn one observed result into a universal fact.', 'npcink-toolbox' )
				);
			}

			if ( 1 === preg_match( '/(最|绝对|完全|一键|显著|极大|领先|革命性|无敌|完美|保证)/u', $paragraph ) ) {
				$items[] = $this->editor_article_checkup_issue(
					'tone_risk_' . ( $index + 1 ),
					'tone',
					'info',
					$location,
					$paragraph,
					__( 'Tone may read stronger than the supporting evidence.', 'npcink-toolbox' ),
					__( 'Review whether the claim should be softened or tied to a specific condition.', 'npcink-toolbox' )
				);
			}
		}

		$word_count = str_word_count( $text );
		$text_length = $this->editor_text_length( $text );
		if ( '' === $title ) {
			$items[] = $this->editor_article_checkup_issue(
				'missing_title',
				'structure',
				'error',
				__( 'Title', 'npcink-toolbox' ),
				'',
				__( 'The article title is missing.', 'npcink-toolbox' ),
				__( 'Add a human-reviewed title before running title or metadata handoff actions.', 'npcink-toolbox' )
			);
		}
		if ( '' === $excerpt && ( $word_count >= 120 || $text_length >= 360 ) ) {
			$items[] = $this->editor_article_checkup_issue(
				'missing_excerpt',
				'structure',
				'warning',
				__( 'Excerpt', 'npcink-toolbox' ),
				'',
				__( 'The article has enough body content but no excerpt.', 'npcink-toolbox' ),
				__( 'Review whether a summary suggestion should be generated, then accept it manually before saving.', 'npcink-toolbox' )
			);
		}
		if ( count( $paragraphs ) >= 5 && ! $this->editor_article_checkup_has_heading_signal( $text ) ) {
			$items[] = $this->editor_article_checkup_issue(
				'missing_heading_structure',
				'structure',
				'info',
				__( 'Full article', 'npcink-toolbox' ),
				'',
				__( 'The draft is long enough to need scan-friendly structure, but no obvious heading signal was found.', 'npcink-toolbox' ),
				__( 'Review whether section headings, lists, or clearer paragraph grouping would help readers scan the article.', 'npcink-toolbox' )
			);
		}
		if ( empty( $items ) ) {
			$items[] = $this->editor_article_checkup_issue(
				'no_blocking_local_issue',
				'clarity',
				'info',
				__( 'Full article', 'npcink-toolbox' ),
				'',
				__( 'No high-confidence local article issues were found.', 'npcink-toolbox' ),
				__( 'This is a local heuristic check only. Run focused title, summary, taxonomy, internal-link, image, or publish preflight actions when needed.', 'npcink-toolbox' )
			);
		}

		return array(
			'artifact_type'          => 'article_checkup.v1',
			'composition_role'       => 'full_draft_review',
			'candidate_contract'     => 'article_checkup_issue.v1',
			'status'                 => 'ready',
			'provider_execution'     => 'local_article_checkup',
			'write_posture'          => 'suggestion_only',
			'final_write_path'       => 'operator_review_only',
			'direct_wordpress_write' => false,
			'items'                  => array_slice( $items, 0, 12 ),
			'summary'                => array(
				'paragraph_count' => count( $paragraphs ),
				'issue_count'     => count( $items ),
				'cloud_calls'     => false,
				'no_rewrite'      => true,
			),
		);
	}

	private function editor_article_checkup_paragraphs( string $text ): array {
		$normalized = preg_replace( "/\r\n?/", "\n", $text );
		$parts      = preg_split( "/\n{2,}|(?<=[。！？!?])\s+(?=\\S)/u", is_string( $normalized ) ? $normalized : $text );
		$paragraphs = array();
		foreach ( is_array( $parts ) ? $parts : array( $text ) as $part ) {
			$paragraph = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $part ) ) ?: '' );
			if ( '' === $paragraph ) {
				continue;
			}
			$paragraphs[] = $paragraph;
			if ( count( $paragraphs ) >= 24 ) {
				break;
			}
		}
		return $paragraphs;
	}

	private function editor_article_checkup_has_heading_signal( string $text ): bool {
		return 1 === preg_match( '/(^|\n)\s*(#{2,6}\s+|[一二三四五六七八九十]+[、.．]|\\d+[.．、]|[（(][一二三四五六七八九十\\d]+[）)])|<h[1-6][^>]*>/iu', $text );
	}

	private function editor_article_checkup_issue( string $id, string $type, string $severity, string $location, string $evidence, string $issue, string $edit_direction ): array {
		return array(
			'id'             => sanitize_key( $id ),
			'type'           => sanitize_key( $type ),
			'severity'       => sanitize_key( $severity ),
			'location'       => sanitize_text_field( $location ),
			'evidence'       => sanitize_text_field( $this->editor_trim_chars( $evidence, 120 ) ),
			'issue'          => sanitize_text_field( $issue ),
			'edit_direction' => sanitize_textarea_field( $edit_direction ),
			'action_policy'  => 'operator_review_only_no_insert',
			'evidence_refs'  => array( 'current_draft:' . sanitize_key( $id ) ),
		);
	}

	private function editor_text_length( string $text ): int {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );
	}

	private function editor_hosted_draft_support( array $context, string $provider_intent ): array {
		$content = (string) ( $context['content_text'] ?? '' );
		if ( 'polish_notes' === $provider_intent ) {
			$selected = trim(
				implode(
					"\n\n",
					array_filter(
						array(
							(string) ( $context['selected_text'] ?? '' ),
							(string) ( $context['selected_block_text'] ?? '' ),
						)
					)
				)
			);
			if ( '' !== $selected ) {
				$content = $selected;
			}
		}

		$section = $this->editor_support_section(
				$this->editor_cached_hosted_ai_content_support(
					array(
						'intent'             => $provider_intent,
						'post_id'            => absint( $context['post_id'] ?? 0 ),
					'title'              => (string) ( $context['title'] ?? '' ),
					'excerpt'            => (string) ( $context['excerpt'] ?? '' ),
					'content'            => $content,
						'user_instruction'   => (string) ( $context['user_instruction'] ?? '' ),
						'generation_variant' => (string) ( $context['generation_variant'] ?? '' ),
					),
					! empty( $context['force_regenerate'] )
				)
			);
		$section['provider_execution'] = 'hosted_ai';
		$section['provider_intent']    = $provider_intent;
		$section['write_posture']      = 'suggestion_only';
		if ( 'title_summary' === $provider_intent ) {
			$section = $this->editor_title_recommendation_section( $section, $context );
		}
		if ( 'polish_notes' === $provider_intent && ! $this->editor_paragraph_check_has_output( $section ) ) {
			$section = $this->editor_paragraph_check_local_fallback_section( $section, $content );
		}

		return $section;
	}

	private function editor_paragraph_check_has_output( array $section ): bool {
		if ( ! empty( $section['items'] ) && is_array( $section['items'] ) ) {
			return true;
		}
		if ( '' !== trim( sanitize_textarea_field( (string) ( $section['output_text'] ?? '' ) ) ) ) {
			return true;
		}
		$output_json = is_array( $section['output_json'] ?? null ) ? $section['output_json'] : array();
		foreach ( array( 'clarity_check', 'fact_gaps', 'tone_consistency', 'editing_suggestions', 'assumptions_to_verify' ) as $key ) {
			if ( ! empty( $output_json[ $key ] ) ) {
				return true;
			}
		}
		$result = is_array( $section['result'] ?? null ) ? $section['result'] : array();
		foreach ( array( 'clarity_check', 'fact_gaps', 'tone_consistency', 'editing_suggestions', 'assumptions_to_verify' ) as $key ) {
			if ( ! empty( $result[ $key ] ) ) {
				return true;
			}
		}
		return false;
	}

	private function editor_paragraph_check_local_fallback_section( array $section, string $selected_text ): array {
		$output = $this->editor_paragraph_check_local_output( $selected_text );
		$status = sanitize_key( (string) ( $section['status'] ?? 'unknown' ) );

		$section['provider_execution']     = 'hosted_ai_with_local_empty_fallback';
		$section['hosted_ai_status']       = $status;
		$section['fallback_reason']        = 'local_paragraph_check_after_hosted_ai_empty';
		$section['fallback_source']        = 'current_selected_paragraph_only';
		$section['fallback_write_posture'] = 'suggestion_only_no_replacement_text';
		$section['output_json']            = $output;
		$section['items']                  = array(
			array(
				'name'          => __( 'Clarity check', 'npcink-toolbox' ),
				'detail'        => $output['clarity_check'],
				'action_policy' => 'operator_review_only_no_insert',
				'evidence_refs' => array( 'current_selection:paragraph' ),
			),
			array(
				'name'          => __( 'Fact gaps', 'npcink-toolbox' ),
				'detail'        => $output['fact_gaps'],
				'action_policy' => 'operator_review_only_no_insert',
				'evidence_refs' => array( 'current_selection:paragraph' ),
			),
			array(
				'name'          => __( 'Tone consistency', 'npcink-toolbox' ),
				'detail'        => $output['tone_consistency'],
				'action_policy' => 'operator_review_only_no_insert',
				'evidence_refs' => array( 'current_selection:paragraph' ),
			),
			array(
				'name'          => __( 'Editing suggestions', 'npcink-toolbox' ),
				'detail'        => $output['editing_suggestions'],
				'action_policy' => 'operator_review_only_no_insert',
				'evidence_refs' => array( 'current_selection:paragraph' ),
			),
		);

		return $section;
	}

	private function editor_paragraph_check_local_output( string $selected_text ): array {
		$text     = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $selected_text ) ) ?: '' );
		$length   = function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );
		$punctuation_count = preg_match_all( '/[。！？!?；;]/u', $text );
		$has_metric_claim  = 1 === preg_match( '/(\d|万|倍|%|百分|快|慢|耗时|性能|测试|经测试|同等|相当|无明显|明显|适合)/u', $text );
		$has_scope_claim   = 1 === preg_match( '/(可用于|适合|场景|条件|因此|所以|由于|因为)/u', $text );

		$clarity = $length > 150 || $punctuation_count >= 3
			? __( '段落信息量偏高，建议人工检查性能结论、保存耗时和适用场景是否需要拆开呈现，避免读者把多个结论混在一起。', 'npcink-toolbox' )
			: __( '段落结构基本清楚；重点检查比较结论和适用边界是否已经在上下文中交代。', 'npcink-toolbox' );

		$fact_gaps = $has_metric_claim
			? __( '包含测试、数量、速度或耗时类结论；发布前需要确认测试条件、数据规模、对比对象和结论来源，避免把单次测试写成通用事实。', 'npcink-toolbox' )
			: __( '未发现明显数字或性能结论；仍需人工确认段落中的判断是否有上下文依据。', 'npcink-toolbox' );

		$tone = $has_scope_claim
			? __( '语气整体偏说明性；涉及“适合/因此/场景”等判断时，建议保持审慎，不要超过已验证范围。', 'npcink-toolbox' )
			: __( '语气整体中性；保持事实说明即可。', 'npcink-toolbox' );

		$editing = __( '不要直接替换正文。优先核对依据，再决定是否补充测试条件、缩小适用范围，或把性能表现和适用场景分开审阅。', 'npcink-toolbox' );

		return array(
			'clarity_check'       => $clarity,
			'fact_gaps'           => $fact_gaps,
			'tone_consistency'    => $tone,
			'editing_suggestions' => $editing,
			'assumptions_to_verify' => __( '托管 AI 本次未返回建议，以上为本地兜底检查；仍以人工编辑和原始测试记录为准。', 'npcink-toolbox' ),
		);
	}

	private function editor_title_recommendation_section( array $section, array $context ): array {
		$candidates = $this->editor_title_recommendation_candidates( $section, $context );

		$section['candidate_contract']        = 'recommendation_candidate.v1';
		$section['recommendation_candidates'] = $candidates;
		$section['quality_gate']              = array(
			'name'           => 'runtime_title_candidate_rerank',
			'policy'         => 'length_meta_phrase_title_repetition_rerank_and_flag',
			'minimum_score'  => 0,
			'candidate_sort' => 'quality_score_desc_then_model_order',
		);
		$section['quality_notes']             = $this->editor_recommendation_quality_notes( $candidates );

		return $section;
	}

	private function editor_title_recommendation_candidates( array $section, array $context ): array {
		$result      = is_array( $section['result'] ?? null ) ? $section['result'] : array();
		$output_json = is_array( $section['output_json'] ?? null ) ? $section['output_json'] : array();
		$output_text = trim( sanitize_textarea_field( (string) ( $section['output_text'] ?? '' ) ) );
		$decoded     = '' !== $output_text ? $this->editor_decode_ai_summary_output( $output_text ) : array();
		$raw_items   = array();

		foreach ( array( $result, $output_json, is_array( $decoded ) ? $decoded : array() ) as $source ) {
			foreach ( $this->editor_title_candidate_values( $source ) as $item ) {
				$raw_items[] = $item;
			}
		}

		if ( empty( $raw_items ) && '' !== $output_text && empty( $decoded ) ) {
			$raw_items[] = array(
				'value'  => $output_text,
				'reason' => '',
				'order'  => 0,
			);
		}

		$candidates = array();
		$seen       = array();
		foreach ( $raw_items as $raw_item ) {
			$value = $this->editor_clean_title_candidate( (string) ( $raw_item['value'] ?? '' ) );
			if ( '' === $value ) {
				continue;
			}
			$key = strtolower( $value );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$quality      = $this->editor_title_candidate_quality( $value, $context );
			$candidates[] = array(
				'value'   => $value,
				'reason'  => sanitize_text_field( (string) ( $raw_item['reason'] ?? '' ) ),
				'order'   => absint( $raw_item['order'] ?? 0 ),
				'quality' => $quality,
			);
		}

		usort(
			$candidates,
			static function ( array $a, array $b ): int {
				$score_delta = (int) ( $b['quality']['score'] ?? 0 ) <=> (int) ( $a['quality']['score'] ?? 0 );
				return 0 !== $score_delta ? $score_delta : ( absint( $a['order'] ?? 0 ) <=> absint( $b['order'] ?? 0 ) );
			}
		);

		$items = array();
		foreach ( array_slice( $candidates, 0, 5 ) as $index => $candidate ) {
			$items[] = $this->editor_recommendation_candidate(
				array(
					'id'             => 0 === $index ? 'ai_recommended_title' : 'ai_title_option_' . ( $index + 1 ),
					'kind'           => 'title',
					'label'          => 0 === $index ? __( 'AI recommended title', 'npcink-toolbox' ) : __( 'AI title option', 'npcink-toolbox' ),
					'value'          => (string) ( $candidate['value'] ?? '' ),
					'reason'         => '' !== (string) ( $candidate['reason'] ?? '' ) ? (string) $candidate['reason'] : __( 'Generated by hosted AI from the current title, excerpt, and draft context. Review before applying.', 'npcink-toolbox' ),
					'target_field'   => 'post_title',
					'action_policy'  => 'editor_apply_preview_save_required',
					'quality_status' => (string) ( $candidate['quality']['status'] ?? 'review' ),
					'quality_score'  => absint( $candidate['quality']['score'] ?? 0 ),
					'quality_issues' => is_array( $candidate['quality']['issues'] ?? null ) ? $candidate['quality']['issues'] : array(),
					'evidence_refs'  => array(),
				)
			);
		}

		return $items;
	}

	private function editor_title_candidate_values( array $source ): array {
		$items = array();
		foreach ( array( 'title_options', 'titles', 'suggestions', 'candidates' ) as $key ) {
			if ( ! is_array( $source[ $key ] ?? null ) ) {
				continue;
			}
			foreach ( $source[ $key ] as $item ) {
				$value = is_array( $item )
					? $this->editor_ai_summary_field( $item, array( 'title', 'value', 'text', 'name', 'label' ) )
					: trim( sanitize_text_field( (string) $item ) );
				if ( '' === $value ) {
					continue;
				}
				$items[] = array(
					'value'  => $value,
					'reason' => is_array( $item ) ? $this->editor_ai_summary_field( $item, array( 'reason', 'rationale', 'detail' ) ) : '',
					'order'  => count( $items ),
				);
			}
		}

		foreach ( array( 'title', 'recommended_title', 'seo_title', 'working_title' ) as $key ) {
			if ( isset( $source[ $key ] ) && ! is_array( $source[ $key ] ) ) {
				$value = trim( sanitize_text_field( (string) $source[ $key ] ) );
				if ( '' !== $value ) {
					$items[] = array(
						'value'  => $value,
						'reason' => '',
						'order'  => count( $items ),
					);
				}
			}
		}

		foreach ( array( 'result', 'data', 'output' ) as $nested_key ) {
			if ( is_array( $source[ $nested_key ] ?? null ) ) {
				foreach ( $this->editor_title_candidate_values( $source[ $nested_key ] ) as $item ) {
					$items[] = array(
						'value'  => (string) ( $item['value'] ?? '' ),
						'reason' => (string) ( $item['reason'] ?? '' ),
						'order'  => count( $items ),
					);
				}
			}
		}

		return $items;
	}

	private function editor_clean_title_candidate( string $title ): string {
		$value = trim( sanitize_text_field( $title ) );
		$value = preg_replace( '/^\s*(?:#+|\*+|-+|\d+[\.、)]\s*)\s*/u', '', $value );
		$value = is_string( $value ) ? trim( $value ) : trim( sanitize_text_field( $title ) );
		$value = trim( $value, " \t\n\r\0\x0B\"'“”‘’" );

		return sanitize_text_field( $value );
	}

	private function editor_title_candidate_quality( string $title, array $context ): array {
		$score  = 100;
		$issues = array();
		$value  = trim( $title );
		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
		$current_title = trim( sanitize_text_field( (string) ( $context['title'] ?? '' ) ) );

		if ( $length < 6 ) {
			$score   -= 18;
			$issues[] = __( '标题过短，可能缺少具体对象。', 'npcink-toolbox' );
		}
		if ( $length > 80 ) {
			$score   -= 28;
			$issues[] = __( '标题超过 80 个字符，可能不适合编辑器标题字段。', 'npcink-toolbox' );
		}
		if ( '' !== $current_title && strtolower( $value ) === strtolower( $current_title ) ) {
			$score   -= 14;
			$issues[] = __( '标题与当前标题完全相同。', 'npcink-toolbox' );
		}
		if ( 1 === preg_match( '/(?:草稿|本文|这篇文章|该文章|this\s+(?:article|post|draft)|标题建议|title suggestion)/iu', $value ) ) {
			$score   -= 35;
			$issues[] = __( '包含文章自指或编辑提示词。', 'npcink-toolbox' );
		}
		if ( false !== strpos( $value, '```' ) || false !== strpos( $value, '{' ) || false !== strpos( $value, '}' ) ) {
			$score   -= 40;
			$issues[] = __( '包含格式或 JSON 泄漏。', 'npcink-toolbox' );
		}
		if ( 1 === preg_match( '/(?:必看|震惊|最强|最好|终极|保证|100%|排名第一)/u', $value ) ) {
			$score   -= 18;
			$issues[] = __( '标题可能过度营销或包含高风险承诺。', 'npcink-toolbox' );
		}

		$status = 'good';
		if ( $score < 70 ) {
			$status = 'review';
		}
		if ( $score < 55 ) {
			$status = 'weak';
		}

		if ( empty( $issues ) ) {
			$issues[] = __( '通过长度、自指套话和基础标题质量检查。', 'npcink-toolbox' );
		}

		return array(
			'score'  => max( 0, min( 100, $score ) ),
			'status' => $status,
			'issues' => array_values( array_unique( $issues ) ),
		);
	}

	private function editor_recommendation_candidate( array $args ): array {
		$candidate = array(
			'contract'               => 'recommendation_candidate.v1',
			'id'                     => sanitize_key( (string) ( $args['id'] ?? 'candidate' ) ),
			'kind'                   => sanitize_key( (string) ( $args['kind'] ?? 'generic' ) ),
			'label'                  => sanitize_text_field( (string) ( $args['label'] ?? __( 'Recommendation candidate', 'npcink-toolbox' ) ) ),
			'value'                  => sanitize_text_field( (string) ( $args['value'] ?? '' ) ),
			'reason'                 => sanitize_text_field( (string) ( $args['reason'] ?? '' ) ),
			'confidence'             => is_numeric( $args['confidence'] ?? null ) ? max( 0, min( 1, (float) $args['confidence'] ) ) : null,
			'quality_status'         => sanitize_key( (string) ( $args['quality_status'] ?? 'review' ) ),
			'quality_score'          => absint( $args['quality_score'] ?? 0 ),
			'quality_issues'         => is_array( $args['quality_issues'] ?? null ) ? array_values( array_map( 'sanitize_text_field', $args['quality_issues'] ) ) : array(),
			'action_policy'          => sanitize_key( (string) ( $args['action_policy'] ?? 'suggestion_only' ) ),
			'target_field'           => sanitize_key( (string) ( $args['target_field'] ?? '' ) ),
			'write_posture'          => 'suggestion_only',
			'direct_wordpress_write' => false,
			'evidence_refs'          => is_array( $args['evidence_refs'] ?? null ) ? array_values( array_map( 'sanitize_text_field', $args['evidence_refs'] ) ) : array(),
		);

		if ( '' !== (string) ( $args['source_candidate_ref'] ?? '' ) ) {
			$candidate['source_candidate_ref'] = sanitize_text_field( (string) $args['source_candidate_ref'] );
		}

		return $candidate;
	}

	private function editor_recommendation_quality_notes( array $items ): array {
		$notes = array();
		foreach ( $items as $item ) {
			$issues = is_array( $item['quality_issues'] ?? null ) ? $item['quality_issues'] : array();
			$notes[] = array(
				'name'   => (string) ( $item['label'] ?? __( 'Recommendation candidate', 'npcink-toolbox' ) ),
				'status' => sanitize_key( (string) ( $item['quality_status'] ?? 'review' ) ),
				'detail' => sprintf(
					/* translators: 1: quality score, 2: quality notes. */
					__( 'Quality score %1$d. %2$s', 'npcink-toolbox' ),
					absint( $item['quality_score'] ?? 0 ),
					implode( ' ', array_map( 'sanitize_text_field', $issues ) )
				),
			);
		}

		return $notes;
	}

	private function editor_fast_category_suggestions( array $context, string $query ): array {
		$taxonomy_terms = $this->editor_taxonomy_term_candidates( $context, $query );
		$items          = is_array( $taxonomy_terms['items'] ?? null ) ? $taxonomy_terms['items'] : array();
		$categories     = array_values(
			array_filter(
				$items,
				static fn( array $item ): bool => 'category' === (string) ( $item['taxonomy'] ?? '' )
			)
		);

		return $this->editor_taxonomy_only_suggestion_section(
			'category_suggestions',
			$categories,
			array(),
			$this->empty_proposed_new_terms_review(),
			$taxonomy_terms,
			$context
		);
	}

	private function editor_fast_tag_suggestions( array $context, string $query ): array {
		$taxonomy_terms = $this->editor_taxonomy_term_candidates( $context, $query );
		$items          = is_array( $taxonomy_terms['items'] ?? null ) ? $taxonomy_terms['items'] : array();
		$tags           = array_values(
			array_filter(
				$items,
				static fn( array $item ): bool => 'post_tag' === (string) ( $item['taxonomy'] ?? '' )
			)
		);
		$proposed_new_terms = $this->empty_proposed_new_terms_review();

		return $this->editor_taxonomy_only_suggestion_section(
			'tag_suggestions',
			array(),
			$tags,
			$proposed_new_terms,
			$taxonomy_terms,
			$context
		);
	}

	private function editor_taxonomy_only_suggestion_section( string $candidate_type, array $categories, array $tags, array $proposed_new_terms, array $taxonomy_terms, array $context ): array {
		return array(
			'artifact_type'              => 'article_taxonomy_suggestions.v1',
			'composition_role'           => 'taxonomy_candidates_only',
			'candidate_type'             => sanitize_key( $candidate_type ),
			'candidate_contract'         => 'recommendation_candidate.v1',
			'write_posture'              => 'suggestion_only',
			'final_write_path'           => 'core_proposal_required',
			'direct_wordpress_write'     => false,
			'input_scope'                => $this->editor_input_scope( $context ),
			'category_candidates'        => array_slice( $categories, 0, 5 ),
			'tag_candidates'             => array_slice( $tags, 0, 8 ),
			'proposed_new_terms'         => $proposed_new_terms,
			'taxonomy_terms'             => $taxonomy_terms,
			'recommendation_candidates'  => $this->editor_taxonomy_recommendation_candidates( $candidate_type, $categories, $tags, $proposed_new_terms ),
			'quality_gate'               => array(
				'name'           => 'runtime_taxonomy_candidate_rerank',
				'policy'         => 'existing_terms_first_current_draft_match_then_related_history',
				'candidate_sort' => 'score_desc_then_existing_term_order',
			),
			'selection_policy'           => array(
				'prefer_existing_terms'      => true,
				'new_terms_deferred'         => true,
				'no_toolbox_term_creation'   => true,
				'accepted_write_path'        => 'core_proposal_required',
			),
		);
	}

	private function editor_taxonomy_recommendation_candidates( string $candidate_type, array $categories, array $tags, array $proposed_new_terms ): array {
		$items  = 'category_suggestions' === $candidate_type ? array_slice( $categories, 0, 5 ) : array_slice( $tags, 0, 8 );
		$result = array();
		foreach ( $items as $index => $item ) {
			$taxonomy = sanitize_key( (string) ( $item['taxonomy'] ?? '' ) );
			$term_id  = absint( $item['term_id'] ?? 0 );
			$name     = sanitize_text_field( (string) ( $item['name'] ?? '' ) );
			if ( '' === $name || 0 >= $term_id ) {
				continue;
			}
			$quality  = $this->editor_taxonomy_candidate_quality( $item );
			$result[] = $this->editor_recommendation_candidate(
				array(
					'id'             => ( 'category' === $taxonomy ? 'category_' : 'tag_' ) . $term_id,
					'kind'           => 'category' === $taxonomy ? 'category' : 'tag',
					'label'          => 'category' === $taxonomy ? __( 'Existing category', 'npcink-toolbox' ) : __( 'Existing tag', 'npcink-toolbox' ),
					'value'          => $name,
					'reason'         => sanitize_text_field( (string) ( $item['reason'] ?? '' ) ),
					'confidence'     => $quality['confidence'],
					'target_field'   => 'category' === $taxonomy ? 'category' : 'post_tag',
					'action_policy'  => 'core_proposal_required',
					'quality_status' => $quality['status'],
					'quality_score'  => $quality['score'],
					'quality_issues' => $quality['issues'],
					'evidence_refs'  => is_array( $item['evidence_refs'] ?? null ) ? $item['evidence_refs'] : array(),
				)
			);
		}

		return $result;
	}

	private function editor_taxonomy_candidate_quality( array $item ): array {
		$score            = is_numeric( $item['score'] ?? null ) ? (float) $item['score'] : 0.0;
		$match_signals    = is_array( $item['match_signals'] ?? null ) ? $item['match_signals'] : array();
		$related_context  = is_array( $item['related_context'] ?? null ) ? $item['related_context'] : array();
		$quality_score    = max( 0, min( 100, 45 + (int) round( $score * 10 ) ) );
		$quality_issues   = array();
		if ( in_array( 'current_draft_match', $match_signals, true ) ) {
			$quality_issues[] = __( '匹配当前草稿中的标题、摘要或正文词。', 'npcink-toolbox' );
		}
		if ( in_array( 'title_term_name_match', $match_signals, true ) ) {
			$quality_issues[] = __( '词条名称在标题中完整出现，优先级更高。', 'npcink-toolbox' );
		}
		if ( in_array( 'slug_alias_match', $match_signals, true ) ) {
			$quality_issues[] = __( '词条 slug 或别名与当前编辑上下文匹配。', 'npcink-toolbox' );
		}
		if ( in_array( 'related_site_knowledge_term', $match_signals, true ) ) {
			$quality_issues[] = __( '历史相关文章使用过该词汇，可作为站内词库证据。', 'npcink-toolbox' );
		}
		if ( in_array( 'description_only_match', $match_signals, true ) ) {
			$quality_score -= 20;
			$quality_issues[] = __( '仅描述字段匹配，避免把弱说明文字当作强分类依据。', 'npcink-toolbox' );
		}
		if ( in_array( 'low_specificity_match', $match_signals, true ) ) {
			$quality_score -= 15;
			$quality_issues[] = __( '只有一个较弱 token 匹配，需人工确认是否为标题党或泛化词。', 'npcink-toolbox' );
		}
		if ( empty( $quality_issues ) ) {
			$quality_issues[] = __( '仅作为现有 WordPress 词条候选，采用人工审查。', 'npcink-toolbox' );
		}
		if ( 0 === absint( $related_context['source_count'] ?? 0 ) && ! in_array( 'current_draft_match', $match_signals, true ) ) {
			$quality_score -= 15;
			$quality_issues[] = __( '缺少当前草稿或历史文章的强匹配证据。', 'npcink-toolbox' );
		}

		$status = 'good';
		if ( $quality_score < 70 ) {
			$status = 'review';
		}
		if ( $quality_score < 55 ) {
			$status = 'weak';
		}

		return array(
			'score'      => max( 0, min( 100, $quality_score ) ),
			'status'     => $status,
			'confidence' => max( 0.0, min( 1.0, $score / 5 ) ),
			'issues'     => array_values( array_unique( $quality_issues ) ),
		);
	}

	private function editor_metadata_suggestion_section( string $candidate_type, array $context, array $summary_layers, array $categories, array $tags, array $proposed_new_terms, array $taxonomy_terms, array $related_content, array $handoff_preview, array $metadata_delta ): array {
		return array(
			'artifact_type'          => 'article_discoverability_optimization.v1',
			'composition_role'       => 'summary_taxonomy_tag_candidates',
			'candidate_type'         => sanitize_key( $candidate_type ),
			'write_posture'          => 'suggestion_only',
			'final_write_path'       => 'core_proposal_required',
			'direct_wordpress_write' => false,
			'input_scope'            => $this->editor_input_scope( $context ),
			'summary_layers'         => $summary_layers,
			'category_candidates'    => array_slice( $categories, 0, 5 ),
			'tag_candidates'         => array_slice( $tags, 0, 8 ),
			'proposed_new_terms'     => $proposed_new_terms,
			'taxonomy_terms'         => $taxonomy_terms,
			'related_content'        => $related_content,
			'optimization_strategy'  => $this->editor_summary_terms_strategy(),
			'review_metrics'         => $this->editor_summary_terms_review_metrics(),
			'handoff_preview'        => $handoff_preview,
			'content_metadata_delta' => $metadata_delta,
			'handoff'                => array(
				'final_writes'           => 'core_proposal_required',
				'direct_wordpress_write' => false,
				'core_route'             => '/wp-json/npcink-openclaw-adapter/v1/proposals/from-plan',
			),
		);
	}

	private function editor_related_post_terms_for_context( int $post_id ): array {
		if ( 0 >= $post_id || ! function_exists( 'get_the_terms' ) ) {
			return array();
		}

		$terms = array();
		foreach ( array( 'category', 'post_tag' ) as $taxonomy ) {
			$items = get_the_terms( $post_id, $taxonomy );
			if ( is_wp_error( $items ) || ! is_array( $items ) ) {
				continue;
			}

			foreach ( $items as $term ) {
				$terms[] = array(
					'term_id'  => absint( $term->term_id ?? 0 ),
					'taxonomy' => sanitize_key( $taxonomy ),
					'name'     => sanitize_text_field( (string) ( $term->name ?? '' ) ),
				);
			}
		}

		return array_values( $terms );
	}

	private function editor_related_content_term_evidence( array $related_content ): array {
		$evidence = array();
		foreach ( array_slice( $this->editor_related_content_items( $related_content ), 0, 20 ) as $index => $item ) {
			$post_id = absint( $item['post_id'] ?? 0 );
			if ( 0 >= $post_id ) {
				continue;
			}

			$score      = is_numeric( $item['score'] ?? null ) ? (float) $item['score'] : 0.0;
			$source_ref = 'site_knowledge:' . sanitize_key( (string) ( $item['post_id'] ?? ( $item['id'] ?? $index ) ) );
			$title      = sanitize_text_field( (string) ( $item['title'] ?? $item['name'] ?? '' ) );

			foreach ( $this->editor_related_post_terms_for_context( $post_id ) as $term ) {
				$term_id  = absint( $term['term_id'] ?? 0 );
				$taxonomy = sanitize_key( (string) ( $term['taxonomy'] ?? '' ) );
				if ( 0 >= $term_id || ! in_array( $taxonomy, array( 'category', 'post_tag' ), true ) ) {
					continue;
				}

				$key = $taxonomy . ':' . $term_id;
				if ( ! isset( $evidence[ $key ] ) ) {
					$evidence[ $key ] = array(
						'term_id'          => $term_id,
						'taxonomy'         => $taxonomy,
						'name'             => sanitize_text_field( (string) ( $term['name'] ?? '' ) ),
						'source_count'     => 0,
						'source_post_ids'  => array(),
						'source_titles'    => array(),
						'source_refs'      => array(),
						'max_similarity'   => 0.0,
					);
				}

				$evidence[ $key ]['source_count']++;
				$evidence[ $key ]['source_post_ids'][] = $post_id;
				$evidence[ $key ]['source_refs'][]     = $source_ref;
				if ( '' !== $title ) {
					$evidence[ $key ]['source_titles'][] = $title;
				}
				$evidence[ $key ]['max_similarity'] = max( (float) $evidence[ $key ]['max_similarity'], $score );
			}
		}

		foreach ( $evidence as $key => $item ) {
			$evidence[ $key ]['source_post_ids'] = array_values( array_unique( array_map( 'absint', $item['source_post_ids'] ) ) );
			$evidence[ $key ]['source_titles']   = array_slice( array_values( array_unique( array_map( 'sanitize_text_field', $item['source_titles'] ) ) ), 0, 5 );
			$evidence[ $key ]['source_refs']     = array_values( array_unique( array_map( 'sanitize_text_field', $item['source_refs'] ) ) );
			$evidence[ $key ]['source_count']    = count( $evidence[ $key ]['source_post_ids'] );
		}

		return $evidence;
	}

	private function editor_content_metadata_evidence_refs( array $context, array $related_content, array $discoverability ): array {
		$refs    = array();
		$post_id = absint( $context['post_id'] ?? 0 );
		if ( 0 < $post_id ) {
			$refs[] = array(
				'id'    => 'target_post:' . $post_id,
				'type'  => 'target_post',
				'label' => sanitize_text_field( (string) ( $context['title'] ?? '' ) ),
			);
		}

		$related = is_array( $related_content['results'] ?? null ) ? $related_content['results'] : ( is_array( $related_content['items'] ?? null ) ? $related_content['items'] : array() );
		foreach ( array_slice( $related, 0, 6 ) as $index => $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$ref_id = (string) ( $item['post_id'] ?? ( $item['id'] ?? $index ) );
			$refs[] = array(
				'id'    => 'site_knowledge:' . sanitize_key( $ref_id ),
				'type'  => 'related_content',
				'label' => sanitize_text_field( (string) ( $item['title'] ?? $item['name'] ?? $item['url'] ?? '' ) ),
				'score' => is_numeric( $item['score'] ?? null ) ? (float) $item['score'] : null,
			);
		}

		$sources = is_array( $discoverability['external_search'] ?? null ) && is_array( $discoverability['external_search']['sources'] ?? null )
			? $discoverability['external_search']['sources']
			: ( is_array( $discoverability['sources'] ?? null ) ? $discoverability['sources'] : array() );
		foreach ( array_slice( $sources, 0, 5 ) as $index => $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$refs[] = array(
				'id'    => 'source:' . ( $index + 1 ),
				'type'  => 'external_source_candidate',
				'label' => sanitize_text_field( (string) ( $item['title'] ?? $item['url'] ?? $item['source_url'] ?? '' ) ),
			);
		}

		return array_values( array_filter( $refs, static fn( array $ref ): bool => '' !== (string) ( $ref['label'] ?? '' ) || 'target_post' === (string) ( $ref['type'] ?? '' ) ) );
	}

	private function editor_content_metadata_evidence_ids( array $evidence_refs ): array {
		return array_values(
			array_filter(
				array_map(
					static fn( array $ref ): string => sanitize_text_field( (string) ( $ref['id'] ?? '' ) ),
					$evidence_refs
				)
			)
		);
	}

	private function editor_content_metadata_term_delta_items( array $items, array $evidence_refs ): array {
		$evidence_ids = $this->editor_content_metadata_evidence_ids( $evidence_refs );
		return array_values(
			array_map(
				static function ( array $item ) use ( $evidence_ids ): array {
					$score              = is_numeric( $item['score'] ?? null ) ? (float) $item['score'] : 0.0;
					$item_evidence_refs = is_array( $item['evidence_refs'] ?? null ) ? array_values(
						array_filter(
							array_map( 'sanitize_text_field', $item['evidence_refs'] )
						)
					) : array();
					return array(
						'term_id'       => absint( $item['term_id'] ?? 0 ),
						'name'          => sanitize_text_field( (string) ( $item['name'] ?? '' ) ),
						'taxonomy'      => sanitize_key( (string) ( $item['taxonomy'] ?? '' ) ),
						'confidence'    => max( 0.0, min( 1.0, $score / 5 ) ),
						'reason'        => sanitize_text_field( (string) ( $item['reason'] ?? '' ) ),
						'evidence_refs' => array() !== $item_evidence_refs ? $item_evidence_refs : $evidence_ids,
						'match_signals' => is_array( $item['match_signals'] ?? null ) ? array_values( array_map( 'sanitize_key', $item['match_signals'] ) ) : array(),
						'related_context' => is_array( $item['related_context'] ?? null ) ? $item['related_context'] : array(),
						'status'        => 'existing_wordpress_term',
					);
				},
				$items
			)
		);
	}

	private function editor_content_metadata_new_term_delta_items( array $proposed_new_terms ): array {
		$items = is_array( $proposed_new_terms['items'] ?? null ) ? $proposed_new_terms['items'] : array();
		return array_values(
			array_map(
				static function ( array $item ): array {
					return array(
						'taxonomy'               => sanitize_key( (string) ( $item['taxonomy'] ?? 'post_tag' ) ),
						'name'                   => sanitize_text_field( (string) ( $item['name'] ?? '' ) ),
						'reason'                 => sanitize_text_field( (string) ( $item['reason'] ?? '' ) ),
						'review_required'        => true,
						'strong_review_required' => true,
						'authorization_path'     => 'deferred_taxonomy_governance',
						'status'                 => 'deferred_taxonomy_gap',
					);
				},
				$items
			)
		);
	}

	private function empty_proposed_new_terms_review(): array {
		return array(
			'candidate_type'         => 'proposed_new_terms_review',
			'write_posture'          => 'suggestion_only',
			'direct_wordpress_write' => false,
			'creation_policy'        => 'deferred_taxonomy_governance',
			'strong_review_required' => true,
			'duplicate_review_required' => true,
			'blocked_actions'        => array(
				'no_direct_term_creation_in_toolbox',
				'no_auto_approval_request_for_new_terms',
				'no_term_assignment_without_core_policy_review',
			),
			'items'                  => array(),
			'empty_message'          => __( 'New taxonomy creation is deferred. Use existing categories and tags in this stage.', 'npcink-toolbox' ),
		);
	}

	private function editor_summary_terms_core_handoff_candidates( array $summary_layers, array $categories, array $tags, array $proposed_new_terms ): array {
		$summary_layer_ids = array_values(
			array_filter(
				array_map(
					static fn( array $item ): string => sanitize_key( (string) ( $item['id'] ?? '' ) ),
					is_array( $summary_layers['items'] ?? null ) ? $summary_layers['items'] : array()
				)
			)
		);
		$category_ids      = array_values(
			array_filter(
				array_map(
					static fn( array $item ): int => (int) ( $item['term_id'] ?? 0 ),
					array_slice( $categories, 0, 5 )
				)
			)
		);
		$tag_ids           = array_values(
			array_filter(
				array_map(
					static fn( array $item ): int => (int) ( $item['term_id'] ?? 0 ),
					array_slice( $tags, 0, 8 )
				)
			)
		);
		return array(
			array(
				'id'                    => 'generate_apply_summary',
				'name'                  => __( 'Generate and apply summary', 'npcink-toolbox' ),
				'status'                => 'core_auto_approval_eligible',
				'target_operation'      => 'update_post_excerpt',
				'available_fields'      => $summary_layer_ids,
				'auto_approval_request' => true,
				'toolbox_direct_apply'  => false,
				'proposal_policy'       => array(
					'core_proposal_required' => true,
					'eligible_if'            => array(
						'selected_summary_layer_is_returned_by_toolbox',
						'summary_adds_no_unsupported_facts',
						'current_user_can_edit_target_post',
					),
				),
				'reason'                => __( 'Core may auto-approve a selected summary when it is derived from the supplied draft context and the editor can edit the target post.', 'npcink-toolbox' ),
			),
			array(
				'id'                    => 'recommend_apply_tags',
				'name'                  => __( 'Recommend and apply tags', 'npcink-toolbox' ),
				'status'                => empty( $tag_ids ) ? 'no_existing_tag_candidate' : 'core_auto_approval_eligible',
				'target_operation'      => 'assign_existing_post_tags',
				'available_fields'      => $tag_ids,
				'auto_approval_request' => ! empty( $tag_ids ),
				'toolbox_direct_apply'  => false,
				'proposal_policy'       => array(
					'core_proposal_required' => true,
					'eligible_if'            => array(
						'selected_terms_are_existing_post_tags',
						'term_assignment_is_additive_or_operator_selected',
						'current_user_can_edit_target_post',
					),
				),
				'reason'                => __( 'Existing tag assignments can be proposed for Core auto-approval because Toolbox returns WordPress term ids and does not create taxonomy state.', 'npcink-toolbox' ),
			),
			array(
				'id'                    => 'recommend_categories',
				'name'                  => __( 'Recommend categories', 'npcink-toolbox' ),
				'status'                => empty( $category_ids ) ? 'no_existing_category_candidate' : 'recommendation_only',
				'target_operation'      => 'recommend_existing_categories',
				'available_fields'      => $category_ids,
				'auto_approval_request' => false,
				'toolbox_direct_apply'  => false,
				'proposal_policy'       => array(
					'core_proposal_required' => true,
					'default_mode'           => 'operator_review_required',
					'eligible_if'            => array(
						'selected_terms_are_existing_categories',
						'category_change_policy_allows_auto_assignment',
					),
				),
				'reason'                => __( 'Categories affect site structure, so Toolbox recommends existing categories by default and leaves any assignment policy to Core.', 'npcink-toolbox' ),
			),
		);
	}

	private function editor_summary_terms_handoff_preview( array $summary_layers, array $categories, array $tags, array $proposed_new_terms ): array {
		$core_handoff_candidates = $this->editor_summary_terms_core_handoff_candidates( $summary_layers, $categories, $tags, $proposed_new_terms );

		return array(
			'artifact_type'             => 'summary_terms_handoff_preview.v1',
			'status'                    => 'operator_selection_required',
			'write_posture'             => 'suggestion_only',
			'final_write_path'          => 'core_proposal_required',
			'direct_wordpress_write'    => false,
			'preview_only'              => true,
			'core_handoff_candidates'   => $core_handoff_candidates,
			'core_auto_approval_policy' => array(
				'request_supported'          => true,
				'toolbox_direct_apply'       => false,
				'approval_owner'             => 'npcink-governance-core',
				'execution_owner'            => 'wordpress_abilities',
				'default_safe_actions'       => array(
					'generate_apply_summary',
					'recommend_apply_tags',
				),
				'operator_review_by_default' => array(
					'recommend_categories',
				),
			),
			'available_fields'          => array(
				'summary_layers'      => $core_handoff_candidates[0]['available_fields'],
				'existing_categories' => $core_handoff_candidates[2]['available_fields'],
				'existing_tags'       => $core_handoff_candidates[1]['available_fields'],
			),
			'blocked_actions'        => array(
				'no_excerpt_update_in_toolbox',
				'no_term_assignment_in_toolbox',
				'no_new_term_creation_in_toolbox',
				'no_seo_meta_write_in_toolbox',
			),
			'next_steps'             => array(
				__( 'Use Generate and apply summary when Core policy can auto-approve the selected summary layer.', 'npcink-toolbox' ),
				__( 'Use Recommend and apply tags for existing tag ids returned by Toolbox.', 'npcink-toolbox' ),
				__( 'Use Recommend categories as review-first guidance unless Core explicitly allows category auto-assignment.', 'npcink-toolbox' ),
			),
		);
	}

	private function editor_summary_layer_candidates( array $context, array $related_content = array() ): array {
		$title   = trim( sanitize_text_field( (string) ( $context['title'] ?? '' ) ) );
		$excerpt = trim( sanitize_textarea_field( (string) ( $context['excerpt'] ?? '' ) ) );
		$content = trim( wp_strip_all_tags( (string) ( $context['content_text'] ?? '' ) ) );
		$base                  = '' !== $excerpt ? $excerpt : ( '' !== $content ? $content : $title );
		$related_summary       = $this->editor_related_content_summary( $related_content );
		$summary_evidence_refs = is_array( $related_summary['evidence_refs'] ?? null ) ? $related_summary['evidence_refs'] : array();

		return array(
			'candidate_type'         => 'summary_layer_candidates',
			'write_posture'          => 'suggestion_only',
			'direct_wordpress_write' => false,
			'related_context_summary' => $related_summary,
			'items'                   => array(
				array(
					'id'            => 'short_summary',
					'label'         => __( 'Short summary', 'npcink-toolbox' ),
					'limit'         => '160_chars',
					'value'         => sanitize_text_field( wp_html_excerpt( $base, 160, '' ) ),
					'reason'        => __( 'Use as an excerpt-style candidate after checking related Site Knowledge evidence for duplicate coverage and term fit.', 'npcink-toolbox' ),
					'context_use'   => 'draft_grounded_related_context_checked',
					'evidence_refs' => $summary_evidence_refs,
				),
				array(
					'id'            => 'standard_summary',
					'label'         => __( 'Standard summary', 'npcink-toolbox' ),
					'limit'         => '2_3_sentences',
					'value'         => sanitize_text_field( wp_trim_words( $base, 45, '' ) ),
					'reason'        => __( 'Use for editor review where a slightly fuller article summary is useful, while keeping related content as context evidence rather than new factual material.', 'npcink-toolbox' ),
					'context_use'   => 'draft_grounded_related_context_checked',
					'evidence_refs' => $summary_evidence_refs,
				),
				array(
					'id'            => 'seo_meta_description',
					'label'         => __( 'SEO meta description', 'npcink-toolbox' ),
					'limit'         => '155_chars',
					'value'         => sanitize_text_field( wp_html_excerpt( $base, 155, '' ) ),
					'reason'        => __( 'Use only as a Core-governed SEO/meta proposal candidate after comparing the article with related public content.', 'npcink-toolbox' ),
					'context_use'   => 'draft_grounded_related_context_checked',
					'evidence_refs' => $summary_evidence_refs,
				),
			),
		);
	}

	private function editor_ai_summary_layer_candidates( array $summary_ai, array $context = array() ): array {
		$result      = is_array( $summary_ai['result'] ?? null ) ? $summary_ai['result'] : array();
		$output_text = trim( sanitize_textarea_field( (string) ( $summary_ai['output_text'] ?? '' ) ) );
		$decoded     = '' !== $output_text ? $this->editor_decode_ai_summary_output( $output_text ) : array();
		$reason      = $this->editor_ai_summary_field( $result, array( 'why_this_works', 'reason', 'rationale' ) );
		$coverage    = $this->editor_ai_summary_array_field( $result, array( 'coverage_check', 'coverage' ) );
		$raw_items   = array();

		if ( is_array( $decoded ) && '' === $reason ) {
			$reason = $this->editor_ai_summary_field( $decoded, array( 'why_this_works', 'reason', 'rationale' ) );
		}
		if ( is_array( $decoded ) && empty( $coverage ) ) {
			$coverage = $this->editor_ai_summary_array_field( $decoded, array( 'coverage_check', 'coverage' ) );
		}

		$push_candidate = static function ( array &$items, string $value, string $label_key, int $order ) use ( $reason ): void {
			$value = trim( $value );
			if ( '' === $value ) {
				return;
			}
			$items[] = array(
				'value'     => $value,
				'label_key' => $label_key,
				'order'     => $order,
				'reason'    => $reason,
			);
		};

		foreach ( array( $result, is_array( $decoded ) ? $decoded : array() ) as $source ) {
			$push_candidate( $raw_items, $this->editor_ai_summary_field( $source, array( 'recommended_excerpt', 'short_summary', 'excerpt', 'summary' ) ), 'recommended', count( $raw_items ) );
			$push_candidate( $raw_items, $this->editor_ai_summary_field( $source, array( 'alternate_excerpt', 'standard_summary', 'alternate_summary' ) ), 'alternate', count( $raw_items ) );
			$push_candidate( $raw_items, $this->editor_ai_summary_field( $source, array( 'third_excerpt', 'second_alternate_excerpt', 'alternate_excerpt_2', 'variant_excerpt' ) ), 'alternate', count( $raw_items ) );
			foreach ( $this->editor_ai_summary_list_fields( $source ) as $listed_excerpt ) {
				$push_candidate( $raw_items, $listed_excerpt, 'alternate', count( $raw_items ) );
			}
		}

		if ( empty( $raw_items ) && '' !== $output_text ) {
			$fallback = preg_replace( '/^\s*(?:#+|\*+|-+)?\s*(?:recommended[_ ]excerpt|short[_ ]summary|summary|excerpt|推荐摘要|摘要)\s*[:：-]?\s*/iu', '', $output_text );
			$push_candidate( $raw_items, sanitize_text_field( wp_html_excerpt( is_string( $fallback ) ? $fallback : $output_text, 180, '' ) ), 'recommended', 0 );
		}

		$candidates = array();
		$seen       = array();
		foreach ( $raw_items as $raw_item ) {
			$value = $this->editor_clean_ai_summary_excerpt( (string) ( $raw_item['value'] ?? '' ) );
			if ( ! $this->editor_ai_summary_excerpt_is_reviewable( $value ) ) {
				continue;
			}
			$key = strtolower( $value );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$quality      = $this->editor_ai_summary_candidate_quality( $value, $coverage, $context );
			$candidates[] = array(
				'value'     => $value,
				'order'     => absint( $raw_item['order'] ?? 0 ),
				'reason'    => sanitize_text_field( (string) ( $raw_item['reason'] ?? '' ) ),
				'quality'   => $quality,
			);
		}

		usort(
			$candidates,
			static function ( array $a, array $b ): int {
				$score_delta = (int) ( $b['quality']['score'] ?? 0 ) <=> (int) ( $a['quality']['score'] ?? 0 );
				return 0 !== $score_delta ? $score_delta : ( absint( $a['order'] ?? 0 ) <=> absint( $b['order'] ?? 0 ) );
			}
		);

		$items = array();
		foreach ( array_slice( $candidates, 0, 3 ) as $index => $candidate ) {
			$is_first = 0 === $index;
			$items[]  = array(
				'contract'       => 'recommendation_candidate.v1',
				'id'             => $is_first ? 'ai_recommended_excerpt' : ( 1 === $index ? 'ai_alternate_excerpt' : 'ai_third_excerpt' ),
				'kind'           => 'excerpt',
				'label'          => $is_first ? __( 'AI recommended excerpt', 'npcink-toolbox' ) : __( 'AI alternate excerpt', 'npcink-toolbox' ),
				'limit'         => '50_160_zh_chars',
				'value'          => sanitize_text_field( (string) ( $candidate['value'] ?? '' ) ),
				'reason'         => '' !== (string) ( $candidate['reason'] ?? '' ) ? (string) $candidate['reason'] : __( 'Generated by hosted AI from the current title, excerpt, and draft body. Review before applying.', 'npcink-toolbox' ),
				'context_use'    => 'draft_grounded_ai_summary',
				'quality_status' => sanitize_key( (string) ( $candidate['quality']['status'] ?? 'review' ) ),
				'quality_score'  => absint( $candidate['quality']['score'] ?? 0 ),
				'quality_issues' => is_array( $candidate['quality']['issues'] ?? null ) ? array_values( array_map( 'sanitize_text_field', $candidate['quality']['issues'] ) ) : array(),
				'action_policy'  => 'editor_apply_preview_save_required',
				'target_field'   => 'post_excerpt',
				'evidence_refs'  => array(),
			);
		}

		return array(
			'candidate_contract'     => 'recommendation_candidate.v1',
			'candidate_type'          => 'ai_summary_layer_candidates',
			'write_posture'           => 'suggestion_only',
			'direct_wordpress_write'  => false,
			'related_context_summary' => array(),
			'coverage_check'          => $this->editor_ai_summary_sanitize_array( $coverage ),
			'quality_gate'            => array(
				'name'           => 'runtime_summary_candidate_rerank',
				'policy'         => 'length_meta_phrase_coverage_check_rerank_and_flag',
				'minimum_score'  => 0,
				'candidate_sort' => 'quality_score_desc_then_model_order',
			),
			'quality_notes'           => $this->editor_ai_summary_quality_notes( $items ),
			'items'                   => $items,
		);
	}

	private function editor_ai_summary_candidate_quality( string $excerpt, array $coverage, array $context ): array {
		$score  = 100;
		$issues = array();
		$value  = trim( $excerpt );
		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
		$source = trim(
			sanitize_textarea_field(
				(string) ( $context['title'] ?? '' ) . "\n" .
				(string) ( $context['excerpt'] ?? '' ) . "\n" .
				wp_strip_all_tags( (string) ( $context['content_full_text'] ?? $context['content_text'] ?? '' ) )
			)
		);

		if ( $length < 70 ) {
			$score   -= 6;
			$issues[] = __( '摘要偏短，可能没有覆盖足够信息。', 'npcink-toolbox' );
		}
		if ( 1 === preg_match( '/(?:草稿|本文|这篇文章|该文章|本文说明|本文介绍|这篇草稿|this\s+(?:article|post|draft))/iu', $value ) ) {
			$score   -= 35;
			$issues[] = __( '包含草稿或文章自指套话。', 'npcink-toolbox' );
		}
		if ( 1 === preg_match( '/^(?:面向|适合|需要|想要|对于)/u', $value ) ) {
			$score   -= 4;
			$issues[] = __( '开头较模板化。', 'npcink-toolbox' );
		}

		$core_subject = $this->editor_ai_summary_coverage_text( $coverage['core_subject'] ?? '' );
		if ( $this->editor_ai_summary_coverage_group_missing( $source, $value, $core_subject ) ) {
			$score   -= 18;
			$issues[] = __( '可能缺少核心对象。', 'npcink-toolbox' );
		}

		$title_positioning = $this->editor_ai_summary_coverage_text( $coverage['title_positioning'] ?? '' );
		if ( $this->editor_ai_summary_coverage_group_missing( $source, $value, $title_positioning ) ) {
			$score   -= 10;
			$issues[] = __( '可能遗漏标题中的关键定位。', 'npcink-toolbox' );
		}

		$missing_groups = 0;
		foreach ( $this->editor_ai_summary_flatten_strings( $coverage['must_cover_points'] ?? array() ) as $point ) {
			$terms = $this->editor_ai_summary_keyword_candidates( $point );
			if ( empty( $terms ) ) {
				continue;
			}
			$source_mentions = false;
			$excerpt_mentions = false;
			foreach ( $terms as $term ) {
				if ( $this->editor_ai_summary_text_contains( $source, $term ) ) {
					$source_mentions = true;
				}
				if ( $this->editor_ai_summary_text_contains( $value, $term ) ) {
					$excerpt_mentions = true;
				}
			}
			if ( $source_mentions && ! $excerpt_mentions ) {
				++$missing_groups;
			}
		}
		if ( $missing_groups > 0 ) {
			$score   -= min( 24, $missing_groups * 8 );
			$issues[] = __( '可能遗漏一个或多个必须覆盖点。', 'npcink-toolbox' );
		}

		$term_segments          = $this->editor_ai_summary_source_named_term_segments( $source );
		$available_term_segments = 0;
		$covered_term_segments   = 0;
		$all_named_terms         = array();
		foreach ( $term_segments as $terms ) {
			if ( empty( $terms ) ) {
				continue;
			}
			$all_named_terms = array_merge( $all_named_terms, $terms );
			++$available_term_segments;
			foreach ( $terms as $term ) {
				if ( $this->editor_ai_summary_text_contains( $value, $term ) ) {
					++$covered_term_segments;
					break;
				}
			}
		}
		if ( $available_term_segments >= 2 && $covered_term_segments < 2 ) {
			$score   -= 32;
			$issues[] = __( '可能只覆盖了正文局部工具、方法或流程分支。', 'npcink-toolbox' );
		}
		$all_named_terms = array_values( array_unique( $all_named_terms ) );
		if ( count( $all_named_terms ) >= 3 && count( $all_named_terms ) <= 5 ) {
			$missing_named_terms = array();
			foreach ( $all_named_terms as $term ) {
				if ( ! $this->editor_ai_summary_text_contains( $value, $term ) ) {
					$missing_named_terms[] = $term;
				}
			}
			if ( ! empty( $missing_named_terms ) ) {
				$score   -= min( 36, count( $missing_named_terms ) * 18 );
				$issues[] = sprintf(
					/* translators: %s: comma-separated missing named terms. */
					__( '可能遗漏关键工具或方法：%s。', 'npcink-toolbox' ),
					implode( ', ', array_slice( $missing_named_terms, 0, 5 ) )
				);
			}
		}

		$status = 'good';
		if ( $score < 70 ) {
			$status = 'review';
		}
		if ( $score < 55 ) {
			$status = 'weak';
		}

		if ( empty( $issues ) ) {
			$issues[] = __( '通过长度、自指套话和覆盖检查。', 'npcink-toolbox' );
		}

		return array(
			'score'  => max( 0, min( 100, $score ) ),
			'status' => $status,
			'issues' => array_values( array_unique( $issues ) ),
		);
	}

	private function editor_ai_summary_quality_notes( array $items ): array {
		$notes = array();
		foreach ( $items as $item ) {
			$issues = is_array( $item['quality_issues'] ?? null ) ? $item['quality_issues'] : array();
			$notes[] = array(
				'name'   => (string) ( $item['label'] ?? __( 'Summary candidate', 'npcink-toolbox' ) ),
				'status' => sanitize_key( (string) ( $item['quality_status'] ?? 'review' ) ),
				'detail' => sprintf(
					/* translators: 1: quality score, 2: quality notes. */
					__( 'Quality score %1$d. %2$s', 'npcink-toolbox' ),
					absint( $item['quality_score'] ?? 0 ),
					implode( ' ', array_map( 'sanitize_text_field', $issues ) )
				),
			);
		}

		return $notes;
	}

	private function editor_ai_summary_array_field( array $source, array $keys ): array {
		foreach ( $keys as $key ) {
			if ( is_array( $source[ $key ] ?? null ) ) {
				return $source[ $key ];
			}
		}

		foreach ( array( 'result', 'data', 'summary', 'summary_candidates', 'output' ) as $nested_key ) {
			if ( is_array( $source[ $nested_key ] ?? null ) ) {
				$value = $this->editor_ai_summary_array_field( $source[ $nested_key ], $keys );
				if ( ! empty( $value ) ) {
					return $value;
				}
			}
		}

		return array();
	}

	private function editor_ai_summary_sanitize_array( array $value ): array {
		$clean = array();
		foreach ( $value as $key => $item ) {
			$clean_key = is_string( $key ) ? sanitize_key( $key ) : absint( $key );
			if ( is_array( $item ) ) {
				$clean[ $clean_key ] = $this->editor_ai_summary_sanitize_array( $item );
				continue;
			}
			$clean[ $clean_key ] = sanitize_text_field( (string) $item );
		}

		return $clean;
	}

	private function editor_ai_summary_coverage_text( $value ): string {
		$parts = $this->editor_ai_summary_flatten_strings( $value );
		return trim( sanitize_text_field( implode( ' ', array_slice( $parts, 0, 3 ) ) ) );
	}

	private function editor_ai_summary_flatten_strings( $value ): array {
		if ( is_scalar( $value ) ) {
			$text = trim( sanitize_text_field( (string) $value ) );
			return '' !== $text ? array( $text ) : array();
		}
		if ( ! is_array( $value ) ) {
			return array();
		}

		$parts = array();
		foreach ( $value as $item ) {
			foreach ( $this->editor_ai_summary_flatten_strings( $item ) as $part ) {
				if ( '' !== $part ) {
					$parts[] = $part;
				}
			}
		}

		return $parts;
	}

	private function editor_ai_summary_keyword_candidates( string $value ): array {
		$text  = preg_replace( '/[，,、；;。.!！？?（）()\[\]【】"“”\'‘’：:]+/u', ' ', $value );
		$parts = preg_split( '/\s+/u', is_string( $text ) ? $text : $value );
		$terms = array();
		foreach ( is_array( $parts ) ? $parts : array() as $part ) {
			$term = trim( sanitize_text_field( $part ) );
			if ( '' === $term ) {
				continue;
			}
			$length = function_exists( 'mb_strlen' ) ? mb_strlen( $term, 'UTF-8' ) : strlen( $term );
			if ( $length < 2 || in_array( $term, array( '以及', '或者', '并且', '主要', '核心', '覆盖', '说明', '介绍', '场景', '步骤', '能力' ), true ) ) {
				continue;
			}
			$terms[] = $term;
			if ( count( $terms ) >= 4 ) {
				break;
			}
		}

		return array_values( array_unique( $terms ) );
	}

	private function editor_ai_summary_coverage_group_missing( string $source, string $excerpt, string $coverage_text ): bool {
		$terms = $this->editor_ai_summary_keyword_candidates( $coverage_text );
		if ( empty( $terms ) ) {
			return false;
		}

		$source_mentions = false;
		$excerpt_mentions = false;
		foreach ( $terms as $term ) {
			if ( $this->editor_ai_summary_text_contains( $source, $term ) ) {
				$source_mentions = true;
			}
			if ( $this->editor_ai_summary_text_contains( $excerpt, $term ) ) {
				$excerpt_mentions = true;
			}
		}

		return $source_mentions && ! $excerpt_mentions;
	}

	private function editor_ai_summary_text_contains( string $haystack, string $needle ): bool {
		$needle = trim( $needle );
		if ( '' === $needle ) {
			return false;
		}
		if ( function_exists( 'mb_stripos' ) ) {
			return false !== mb_stripos( $haystack, $needle, 0, 'UTF-8' );
		}

		return false !== stripos( $haystack, $needle );
	}

	private function editor_ai_summary_source_named_term_segments( string $source ): array {
		$plain = trim( wp_strip_all_tags( $source ) );
		$segments = array(
			'lead'   => array(),
			'middle' => array(),
			'end'    => array(),
		);
		if ( '' === $plain ) {
			return $segments;
		}

		$length = strlen( $plain );
		if ( 1 !== preg_match_all( '/(?<![A-Za-z0-9._+-])([A-Za-z][A-Za-z0-9._+-]{1,})(?![A-Za-z0-9._+-])/u', $plain, $matches, PREG_OFFSET_CAPTURE ) ) {
			return $segments;
		}

		foreach ( $matches[1] as $match ) {
			$term = trim( sanitize_text_field( (string) ( $match[0] ?? '' ) ) );
			$key  = strtolower( $term );
			if ( '' === $term || in_array( $key, array( 'http', 'https', 'www', 'com', 'html', 'php', 'js', 'css', 'question', 'answer' ), true ) ) {
				continue;
			}
			if ( 1 === preg_match( '/^[A-Z0-9]{2,5}$/', $term ) ) {
				continue;
			}
			if ( 0 === strpos( $key, 'www.' ) || 1 === preg_match( '/\.(?:com|cn|net|org)$/', $key ) ) {
				continue;
			}

			$offset     = max( 0, (int) ( $match[1] ?? 0 ) );
			$segment_id = 'lead';
			if ( $offset >= (int) floor( $length * 2 / 3 ) ) {
				$segment_id = 'end';
			} elseif ( $offset >= (int) floor( $length / 3 ) ) {
				$segment_id = 'middle';
			}
			if ( ! in_array( $term, $segments[ $segment_id ], true ) ) {
				$segments[ $segment_id ][] = $term;
			}
		}

		return $segments;
	}

	private function editor_decode_ai_summary_output( string $output_text ): array {
		$trimmed = trim( $output_text );
		if ( '' === $trimmed ) {
			return array();
		}

		$direct = json_decode( $trimmed, true );
		if ( is_array( $direct ) ) {
			return $direct;
		}

		if ( 1 === preg_match( '/```(?:json)?\s*(\{.*?\})\s*```/is', $trimmed, $matches ) ) {
			$fenced = json_decode( $matches[1], true );
			if ( is_array( $fenced ) ) {
				return $fenced;
			}
		}

		$first_brace = strpos( $trimmed, '{' );
		$last_brace  = strrpos( $trimmed, '}' );
		if ( false !== $first_brace && false !== $last_brace && $last_brace > $first_brace ) {
			$embedded = json_decode( substr( $trimmed, $first_brace, $last_brace - $first_brace + 1 ), true );
			if ( is_array( $embedded ) ) {
				return $embedded;
			}
		}

		return array();
	}

	private function editor_ai_summary_list_fields( array $source ): array {
		$values = array();
		foreach ( array( 'excerpt_candidates', 'summary_candidates', 'candidates', 'alternates' ) as $key ) {
			if ( ! is_array( $source[ $key ] ?? null ) ) {
				continue;
			}
			foreach ( $source[ $key ] as $item ) {
				if ( is_array( $item ) ) {
					$value = $this->editor_ai_summary_field( $item, array( 'recommended_excerpt', 'alternate_excerpt', 'third_excerpt', 'excerpt', 'summary', 'value', 'text' ) );
				} else {
					$value = trim( sanitize_textarea_field( (string) $item ) );
				}
				if ( '' !== $value && ! in_array( $value, $values, true ) ) {
					$values[] = $value;
				}
			}
		}

		foreach ( array( 'result', 'data', 'summary', 'output' ) as $nested_key ) {
			if ( is_array( $source[ $nested_key ] ?? null ) ) {
				foreach ( $this->editor_ai_summary_list_fields( $source[ $nested_key ] ) as $value ) {
					if ( '' !== $value && ! in_array( $value, $values, true ) ) {
						$values[] = $value;
					}
				}
			}
		}

		return $values;
	}

	private function editor_ai_summary_field( array $source, array $keys ): string {
		foreach ( $keys as $key ) {
			if ( isset( $source[ $key ] ) && ! is_array( $source[ $key ] ) ) {
				$value = trim( sanitize_textarea_field( (string) $source[ $key ] ) );
				if ( '' !== $value ) {
					return $value;
				}
			}
		}

		foreach ( array( 'result', 'data', 'summary', 'summary_candidates', 'output' ) as $nested_key ) {
			if ( is_array( $source[ $nested_key ] ?? null ) ) {
				$value = $this->editor_ai_summary_field( $source[ $nested_key ], $keys );
				if ( '' !== $value ) {
					return $value;
				}
			}
		}

		return '';
	}

	private function editor_clean_ai_summary_excerpt( string $excerpt ): string {
		$value = trim( sanitize_textarea_field( $excerpt ) );
		if ( '' === $value ) {
			return '';
		}

		$cleaned = preg_replace(
			'/^\s*(?:(?:这篇|该|当前)?草稿|(?:这篇|该)?文章|本文|post|article|draft|this\s+(?:post|article|draft))\s*(?:主张|说明|介绍|讲述|阐述|探讨|分析|指出|强调|聚焦(?:于)?|围绕|旨在|认为|argues|explains|introduces|describes|covers|focuses\s+on)?\s*[:：,，。-]?\s*/iu',
			'',
			$value
		);
		$value   = is_string( $cleaned ) ? trim( $cleaned ) : $value;
		$value   = trim( $value, " \t\n\r\0\x0B\"'“”‘’" );

		return sanitize_text_field( $value );
	}

	private function editor_ai_summary_excerpt_is_reviewable( string $excerpt ): bool {
		$value = trim( $excerpt );
		if ( '' === $value ) {
			return false;
		}

		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );

		return $length >= 50 && $length <= 160;
	}

	private function editor_summary_terms_strategy(): array {
		return array(
			'candidate_type'         => 'summary_terms_precision_strategy',
			'write_posture'          => 'suggestion_only',
			'direct_wordpress_write' => false,
			'existing_terms_first'   => true,
			'proposed_new_terms'     => 'deferred_taxonomy_governance',
			'ranking_signals'        => array(
				array(
					'name'   => __( 'Draft query overlap', 'npcink-toolbox' ),
					'weight' => 'high',
					'detail' => __( 'Match against title, excerpt, selected text, and draft body tokens.', 'npcink-toolbox' ),
				),
				array(
					'name'   => __( 'Existing taxonomy vocabulary', 'npcink-toolbox' ),
					'weight' => 'high',
					'detail' => __( 'Prefer existing WordPress categories and tags; defer new vocabulary creation to taxonomy governance.', 'npcink-toolbox' ),
				),
				array(
					'name'   => __( 'Site Knowledge similarity', 'npcink-toolbox' ),
					'weight' => 'medium',
					'detail' => __( 'Use related public content to avoid duplicate coverage and borrow proven term patterns.', 'npcink-toolbox' ),
				),
				array(
					'name'   => __( 'Discoverability context', 'npcink-toolbox' ),
					'weight' => 'medium',
					'detail' => __( 'Check saved SEO/AEO/GEO guidance and Cloud web-search evidence before recommending metadata.', 'npcink-toolbox' ),
				),
			),
			'dedupe_policy'          => array(
				__( 'Normalize candidate labels by case, punctuation, and whitespace before review.', 'npcink-toolbox' ),
				__( 'Treat near-synonyms, plural/singular variants, and translated duplicates as taxonomy-drift risks.', 'npcink-toolbox' ),
				__( 'Keep broad categories stable and use tags for narrower topic facets.', 'npcink-toolbox' ),
			),
			'evidence_requirements'  => array(
				__( 'Each accepted category or tag should have a reason tied to draft text, existing taxonomy, Site Knowledge, or search evidence.', 'npcink-toolbox' ),
				__( 'Fresh external search is useful for factual or current topics, but it should not override the supplied article draft.', 'npcink-toolbox' ),
			),
		);
	}

	private function editor_summary_terms_review_metrics(): array {
		return array(
			'candidate_type'         => 'summary_terms_review_metrics',
			'write_posture'          => 'suggestion_only',
			'direct_wordpress_write' => false,
			'items'                  => array(
				array(
					'name'   => 'accepted_suggestion_rate',
					'detail' => __( 'Track how many summary, category, and tag suggestions an editor accepts after review.', 'npcink-toolbox' ),
				),
				array(
					'name'   => 'summary_edit_distance',
					'detail' => __( 'Compare AI/fallback summaries with the final reviewed summary to detect overbroad or weak suggestions.', 'npcink-toolbox' ),
				),
				array(
					'name'   => 'taxonomy_gap_deferral_rate',
					'detail' => __( 'Track cases where no existing term fits so a later taxonomy governance workflow can review them.', 'npcink-toolbox' ),
				),
				array(
					'name'   => 'duplicate_topic_review',
					'detail' => __( 'Use related Site Knowledge results to flag whether the article overlaps existing public content.', 'npcink-toolbox' ),
				),
				array(
					'name'   => 'evidence_coverage',
					'detail' => __( 'Check whether accepted suggestions cite draft, taxonomy, Site Knowledge, or search evidence.', 'npcink-toolbox' ),
				),
			),
		);
	}

	private function editor_taxonomy_term_candidates( array $context, string $query, array $related_content = array() ): array {
		$post_type  = sanitize_key( (string) ( $context['post_type'] ?? 'post' ) );
		$taxonomies = array_values(
			array_intersect(
				get_object_taxonomies( $post_type ),
				array( 'category', 'post_tag' )
			)
		);
		$related_term_evidence = $this->editor_related_content_term_evidence( $related_content );

		$candidates = array();
		foreach ( $taxonomies as $taxonomy ) {
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
					'number'     => 60,
				)
			);
			if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
				continue;
			}

			foreach ( $terms as $term ) {
				$term_key         = sanitize_key( $taxonomy ) . ':' . absint( $term->term_id );
				$term_text        = $term->name . ' ' . $term->slug . ' ' . $term->description;
				$related_evidence = is_array( $related_term_evidence[ $term_key ] ?? null ) ? $related_term_evidence[ $term_key ] : array();
				$draft_score      = $this->editor_contextual_match_score( $term_text, $context, $query );
				$matched_tokens   = $this->editor_taxonomy_evidence_tokens( $this->editor_contextual_match_tokens( $term_text, $context, $query ) );
				if ( $draft_score > 0 && array() === $matched_tokens ) {
					$draft_score = 0;
				}
				$match_profile    = $this->editor_taxonomy_match_profile( $term, $context, $query, $draft_score, $matched_tokens );
				$draft_score      = (int) $match_profile['score'];
				$matched_tokens   = is_array( $match_profile['matched_tokens'] ?? null ) ? $match_profile['matched_tokens'] : $matched_tokens;
				$related_score    = $this->editor_related_term_score( $related_evidence );
				$score            = $draft_score + $related_score;
				if ( $score <= 0 ) {
					continue;
				}
				$match_signals  = array( 'existing_taxonomy_vocabulary' );
				if ( $draft_score > 0 ) {
					$match_signals[] = 'draft_query_overlap';
					$match_signals[] = 'current_draft_match';
				}
				if ( $related_score > 0 ) {
					$match_signals[] = 'related_site_knowledge_term';
				}
				$match_signals = array_merge( $match_signals, is_array( $match_profile['signals'] ?? null ) ? $match_profile['signals'] : array() );

				$candidates[] = array(
					'term_id'                      => (int) $term->term_id,
					'taxonomy'                     => sanitize_key( $taxonomy ),
					'name'                         => sanitize_text_field( $term->name ),
					'slug'                         => sanitize_title( $term->slug ),
					'score'                        => $score,
					'status'                       => 'existing_term',
					'controlled_vocabulary_status' => 'existing_wordpress_term',
					'normalization_key'            => sanitize_title( $term->name ),
					'matched_tokens'               => $matched_tokens,
					'match_signals'                => array_values( array_unique( $match_signals ) ),
					'related_context'              => $this->editor_related_term_context_summary( $related_evidence ),
					'evidence_refs'                => is_array( $related_evidence['source_refs'] ?? null ) ? array_values( array_unique( $related_evidence['source_refs'] ) ) : array(),
					'reason'                       => $this->editor_taxonomy_candidate_reason( $matched_tokens, $related_evidence ),
				);
			}
		}

		usort(
			$candidates,
			static function ( array $left, array $right ): int {
				return (int) $right['score'] <=> (int) $left['score'];
			}
		);

		return array(
			'candidate_type'         => 'taxonomy_tag_candidates',
			'write_posture'          => 'suggestion_only',
			'direct_wordpress_write' => false,
			'ranking_context'        => array(
				'draft_query_overlap'          => true,
				'related_content_terms'        => array() !== $related_term_evidence,
				'related_term_evidence_count' => count( $related_term_evidence ),
				'related_term_policy'         => 'ranking_evidence_only_no_term_creation_or_assignment',
			),
			'items'                  => array_slice( $candidates, 0, 10 ),
		);
	}

	private function editor_taxonomy_match_profile( object $term, array $context, string $query, int $draft_score, array $matched_tokens ): array {
		$name        = sanitize_text_field( (string) ( $term->name ?? '' ) );
		$slug        = sanitize_title( (string) ( $term->slug ?? '' ) );
		$description = sanitize_text_field( (string) ( $term->description ?? '' ) );
		$name_tokens = $this->support_tokens( $name );
		$slug_tokens = $this->support_tokens( str_replace( '-', ' ', $slug ) );
		$description_tokens = $this->support_tokens( $description );
		$name_slug_tokens   = array_values( array_unique( array_merge( $name_tokens, $slug_tokens ) ) );
		$signals            = array();
		$score              = max( 0, $draft_score );

		$title = trim( (string) ( $context['title'] ?? '' ) );
		if ( array() !== $name_tokens && '' !== $title && $this->editor_text_contains_phrase( $title, $name ) ) {
			$score    += 6;
			$signals[] = 'title_term_name_match';
		}

		foreach ( array( 'excerpt', 'selected_text', 'selected_block_text' ) as $field ) {
			$value = trim( (string) ( $context[ $field ] ?? '' ) );
			if ( array() !== $name_tokens && '' !== $value && $this->editor_text_contains_phrase( $value, $name ) ) {
				$score    += 4;
				$signals[] = $field . '_term_name_match';
				break;
			}
		}

		$content = trim( (string) ( $context['content_text'] ?? '' ) );
		if ( array() !== $name_tokens && '' !== $content && $this->editor_text_contains_phrase( $content, $name ) ) {
			$score    += 2;
			$signals[] = 'body_term_name_match';
		}

		$context_tokens = $this->support_tokens(
			implode(
				' ',
				array(
					(string) ( $context['title'] ?? '' ),
					(string) ( $context['excerpt'] ?? '' ),
					(string) ( $context['selected_text'] ?? '' ),
					(string) ( $context['selected_block_text'] ?? '' ),
					'' !== trim( $query ) ? $query : '',
				)
			)
		);
		$slug_overlap = array_intersect( $slug_tokens, $context_tokens );
		$required_slug_overlap = count( $slug_tokens ) > 1 ? 2 : 1;
		if ( count( $slug_overlap ) >= $required_slug_overlap ) {
			$score    += 2;
			$signals[] = 'slug_alias_match';
		}

		if ( $score > 0 && array() !== $matched_tokens && array() === array_intersect( $matched_tokens, $name_slug_tokens ) && array() !== array_intersect( $matched_tokens, $description_tokens ) ) {
			$score    = min( $score, 2 );
			$signals[] = 'description_only_match';
		}

		$has_exact_name_signal = (bool) array_filter(
			$signals,
			static fn( string $signal ): bool => false !== strpos( $signal, '_term_name_match' )
		);
		if ( $score > 0 && 1 === count( $matched_tokens ) && ! $has_exact_name_signal && ! in_array( 'slug_alias_match', $signals, true ) ) {
			$score    = min( $score, 2 );
			$signals[] = 'low_specificity_match';
		}

		return array(
			'score'          => max( 0, min( 40, $score ) ),
			'matched_tokens' => array_values( array_unique( $matched_tokens ) ),
			'signals'        => array_values( array_unique( $signals ) ),
		);
	}

	private function editor_text_contains_phrase( string $haystack, string $needle ): bool {
		$needle = trim( $needle );
		if ( '' === $needle ) {
			return false;
		}
		return false !== strpos( strtolower( $haystack ), strtolower( $needle ) );
	}

	private function editor_related_term_score( array $related_evidence ): int {
		if ( array() === $related_evidence ) {
			return 0;
		}

		$source_count   = absint( $related_evidence['source_count'] ?? 0 );
		$max_similarity = is_numeric( $related_evidence['max_similarity'] ?? null ) ? (float) $related_evidence['max_similarity'] : 0.0;

		return max( 1, min( 5, $source_count + (int) ceil( max( 0.0, $max_similarity ) * 2 ) ) );
	}

	private function empty_summary_layer_candidates(): array {
		return array(
			'candidate_type'          => 'summary_layer_candidates',
			'write_posture'           => 'suggestion_only',
			'direct_wordpress_write'  => false,
			'related_context_summary' => array(),
			'items'                   => array(),
		);
	}

	private function editor_taxonomy_candidate_reason( array $matched_tokens, array $related_evidence ): string {
		$has_related = array() !== $related_evidence;
		if ( $has_related && array() !== $matched_tokens ) {
			return sprintf(
				/* translators: %s: comma-separated matched words. */
				__( 'Existing term matched the draft and appears on related Site Knowledge posts. Matched tokens: %s.', 'npcink-toolbox' ),
				implode( ', ', array_slice( $matched_tokens, 0, 6 ) )
			);
		}

		if ( $has_related ) {
			return __( 'Existing term appears on related Site Knowledge posts and should be reviewed as a proven site taxonomy pattern.', 'npcink-toolbox' );
		}

		if ( array() === $matched_tokens ) {
			return __( 'Existing term has local taxonomy evidence but no concise matched token could be displayed. Review it against the current draft before applying.', 'npcink-toolbox' );
		}

		return sprintf(
			/* translators: %s: comma-separated matched words. */
			__( 'Existing term matched against the current title, excerpt, or draft body. Matched tokens: %s.', 'npcink-toolbox' ),
			implode( ', ', array_slice( $matched_tokens, 0, 6 ) )
		);
	}

	private function editor_related_term_context_summary( array $related_evidence ): array {
		if ( array() === $related_evidence ) {
			return array();
		}

		return array(
			'source_count'    => absint( $related_evidence['source_count'] ?? 0 ),
			'source_post_ids' => is_array( $related_evidence['source_post_ids'] ?? null ) ? array_values( array_map( 'absint', $related_evidence['source_post_ids'] ) ) : array(),
			'source_titles'   => is_array( $related_evidence['source_titles'] ?? null ) ? array_slice( array_values( array_map( 'sanitize_text_field', $related_evidence['source_titles'] ) ), 0, 5 ) : array(),
			'max_similarity'  => is_numeric( $related_evidence['max_similarity'] ?? null ) ? (float) $related_evidence['max_similarity'] : null,
			'policy'          => 'related_content_ranking_evidence_only',
		);
	}

	private function term_match_score( string $term_text, string $query ): int {
		return count( $this->term_match_tokens( $term_text, $query ) );
	}

	private function editor_contextual_match_score( string $candidate_text, array $context, string $query ): int {
		$weighted_score = 0;
		$weighted_fields = array(
			'title'               => 4,
			'excerpt'             => 3,
			'selected_text'       => 3,
			'selected_block_text' => 3,
			'content_text'        => 1,
			'user_instruction'    => 2,
		);

		foreach ( $weighted_fields as $field => $weight ) {
			$value = trim( (string) ( $context[ $field ] ?? '' ) );
			if ( '' === $value ) {
				continue;
			}
			$weighted_score += count( $this->term_match_tokens( $candidate_text, $value ) ) * $weight;
		}

		if ( $weighted_score <= 0 && '' !== trim( $query ) ) {
			$weighted_score = $this->term_match_score( $candidate_text, $query );
		}

		return max( 0, min( 30, $weighted_score ) );
	}

	private function editor_contextual_match_tokens( string $candidate_text, array $context, string $query ): array {
		$tokens = array();
		foreach ( array( 'title', 'excerpt', 'selected_text', 'selected_block_text', 'content_text', 'user_instruction' ) as $field ) {
			$value = trim( (string) ( $context[ $field ] ?? '' ) );
			if ( '' === $value ) {
				continue;
			}
			$tokens = array_merge( $tokens, $this->term_match_tokens( $candidate_text, $value ) );
		}

		if ( array() === $tokens && '' !== trim( $query ) ) {
			$tokens = $this->term_match_tokens( $candidate_text, $query );
		}

		return array_values( array_unique( array_filter( $tokens ) ) );
	}

	private function editor_taxonomy_evidence_tokens( array $tokens ): array {
		return array_values(
			array_filter(
				array_unique( array_map( 'strtolower', array_map( 'sanitize_text_field', $tokens ) ) ),
				function ( string $token ): bool {
					return ! $this->editor_is_generic_taxonomy_match_token( $token );
				}
			)
		);
	}

	private function editor_is_generic_taxonomy_match_token( string $token ): bool {
		$generic_tokens = array(
			'post'    => true,
			'posts'   => true,
			'page'    => true,
			'pages'   => true,
			'format'  => true,
			'formats' => true,
			'type'    => true,
			'types'   => true,
		);

		return ! empty( $generic_tokens[ strtolower( trim( $token ) ) ] );
	}

	private function term_match_tokens( string $term_text, string $query ): array {
		$term_tokens  = $this->support_tokens( $term_text );
		$query_tokens = $this->support_tokens( $query );
		if ( array() === $term_tokens || array() === $query_tokens ) {
			return array();
		}

		return array_values( array_intersect( $term_tokens, $query_tokens ) );
	}

	private function support_tokens( string $text ): array {
		$tokens = preg_split( '/[^\p{L}\p{N}]+/u', strtolower( $text ) );
		if ( ! is_array( $tokens ) ) {
			return array();
		}
		$stopwords = array(
			'a' => true,
			'an' => true,
			'and' => true,
			'are' => true,
			'as' => true,
			'at' => true,
			'be' => true,
			'by' => true,
			'for' => true,
			'from' => true,
			'has' => true,
			'have' => true,
			'in' => true,
			'into' => true,
			'is' => true,
			'it' => true,
			'of' => true,
			'on' => true,
			'or' => true,
			'that' => true,
			'the' => true,
			'this' => true,
			'to' => true,
			'with' => true,
		);

		return array_values(
			array_unique(
				array_filter(
					$tokens,
					static function ( string $token ) use ( $stopwords ): bool {
						return strlen( $token ) >= 2 && empty( $stopwords[ $token ] );
					}
				)
			)
		);
	}

	private function editor_seo_meta_handoff_preview( array $context, array $discoverability ): array {
		$suggestions     = is_array( $discoverability['candidate_suggestions'] ?? null ) ? $discoverability['candidate_suggestions'] : array();
		$fallback_title  = sanitize_text_field( (string) ( $context['title'] ?? '' ) );
		$fallback_desc   = trim( sanitize_textarea_field( (string) ( $context['excerpt'] ?? '' ) ) );
		if ( '' === $fallback_desc ) {
			$fallback_desc = sanitize_text_field( wp_trim_words( wp_strip_all_tags( (string) ( $context['content_text'] ?? '' ) ), 28, '' ) );
		}
		$seo_title       = sanitize_text_field( wp_html_excerpt( (string) ( $suggestions['seo_title'] ?? $suggestions['title'] ?? $fallback_title ), 65, '' ) );
		$seo_description = sanitize_text_field( wp_html_excerpt( (string) ( $suggestions['seo_description'] ?? $suggestions['meta_description'] ?? $fallback_desc ), 155, '' ) );
		$post_id         = absint( $context['post_id'] ?? 0 );

		return array(
			'artifact_type'          => 'seo_meta_handoff_preview.v1',
			'candidate_type'         => 'seo_meta_single_post_handoff',
			'write_posture'          => 'suggestion_only',
			'final_write_path'       => 'core_proposal_required',
			'direct_wordpress_write' => false,
			'proposal_ready'         => 0 < $post_id && '' !== $seo_title && '' !== $seo_description,
			'target_ability_id'      => 'npcink-abilities-toolkit/set-post-seo-meta',
			'core_route'             => '/wp-json/npcink-governance-core/v1/proposals',
			'adapter_route'          => '/wp-json/npcink-openclaw-adapter/v1/proposals',
			'proposal_payload_template' => array(
				'ability_id' => 'npcink-abilities-toolkit/set-post-seo-meta',
				'title'      => __( 'Review SEO meta for the current post', 'npcink-toolbox' ),
				'summary'    => __( 'Single-post SEO title and description candidate prepared by Toolbox for Core-governed review.', 'npcink-toolbox' ),
				'input'      => array(
					'post_id'         => $post_id,
					'seo_title'       => $seo_title,
					'seo_description' => $seo_description,
					'dry_run'         => true,
					'commit'          => false,
				),
				'preview'    => array(
					'field_patch'      => array(
						array(
							'field'    => 'seo_title',
							'current'  => null,
							'proposed' => $seo_title,
						),
						array(
							'field'    => 'seo_description',
							'current'  => null,
							'proposed' => $seo_description,
						),
					),
					'post_id'          => $post_id,
					'dry_run'          => true,
					'commit_execution' => false,
				),
			),
			'items'                  => array(
				array(
					'name'   => __( 'SEO title candidate', 'npcink-toolbox' ),
					'value'  => $seo_title,
					'status' => '' !== $seo_title ? 'review_required' : 'missing',
				),
				array(
					'name'   => __( 'SEO description candidate', 'npcink-toolbox' ),
					'value'  => $seo_description,
					'status' => '' !== $seo_description ? 'review_required' : 'missing',
				),
				array(
					'name'   => __( 'Core handoff', 'npcink-toolbox' ),
					'detail' => __( 'Submit only after the editor confirms the title and description do not add unsupported claims.', 'npcink-toolbox' ),
					'status' => 'core_proposal_required',
				),
			),
			'required_review'        => array(
				'editor_confirms_single_post_scope',
				'editor_confirms_no_unsupported_claims',
				'editor_confirms_plugin_field_mapping_before_commit',
			),
			'blocked_actions'        => array(
				'no_seo_meta_write_in_toolbox',
				'no_batch_seo_apply',
				'no_geo_schema_write_from_toolbox',
			),
		);
	}

	private function editor_pre_publish_review( array $context, array $sections ): array {
		$duplicate_items = $this->editor_related_content_items( is_array( $sections['duplicate_check'] ?? null ) ? $sections['duplicate_check'] : array() );
		$seo_handoff     = is_array( $sections['seo_handoff'] ?? null ) ? $sections['seo_handoff'] : array();
		$items           = array(
			$this->editor_pre_publish_review_item(
				'summary',
				'' !== trim( (string) ( $context['excerpt'] ?? '' ) ) ? 'ok' : 'warning',
				'' !== trim( (string) ( $context['excerpt'] ?? '' ) ) ? __( 'Excerpt is present for archives and sharing contexts.', 'npcink-toolbox' ) : __( 'Run summary suggestions before publishing if the excerpt is empty.', 'npcink-toolbox' ),
				'summary_suggestions'
			),
			$this->editor_pre_publish_review_item(
				'categories',
				! empty( $context['category_ids'] ) ? 'ok' : 'warning',
				! empty( $context['category_ids'] ) ? __( 'At least one category is selected.', 'npcink-toolbox' ) : __( 'Review category suggestions before publishing.', 'npcink-toolbox' ),
				'category_suggestions'
			),
			$this->editor_pre_publish_review_item(
				'tags',
				! empty( $context['tag_ids'] ) ? 'ok' : 'warning',
				! empty( $context['tag_ids'] ) ? __( 'At least one tag is selected.', 'npcink-toolbox' ) : __( 'Review existing tag suggestions before creating any new vocabulary.', 'npcink-toolbox' ),
				'tag_suggestions'
			),
			$this->editor_pre_publish_review_item(
				'featured_image',
				! empty( $context['featured_media'] ) ? 'ok' : 'warning',
				! empty( $context['featured_media'] ) ? __( 'Featured image is selected.', 'npcink-toolbox' ) : __( 'Review image candidates or select an existing media attachment.', 'npcink-toolbox' ),
				'image_candidates'
			),
			$this->editor_pre_publish_review_item(
				'internal_links',
				'review',
				__( 'Run internal link candidates and insert only the links a human editor accepts.', 'npcink-toolbox' ),
				'internal_links'
			),
			$this->editor_pre_publish_review_item(
				'seo_meta',
				! empty( $seo_handoff['proposal_ready'] ) ? 'review' : 'warning',
				! empty( $seo_handoff['proposal_ready'] ) ? __( 'SEO title and description candidates are ready for Core-governed review.', 'npcink-toolbox' ) : __( 'SEO handoff needs a post id, title, and description candidate.', 'npcink-toolbox' ),
				'seo_meta_single_post_handoff'
			),
			$this->editor_pre_publish_review_item(
				'duplicate_risk',
				empty( $duplicate_items ) ? 'ok' : 'review',
				empty( $duplicate_items ) ? __( 'No duplicate-risk candidates were returned by Site Knowledge.', 'npcink-toolbox' ) : __( 'Related public content was found; compare overlap before publishing.', 'npcink-toolbox' ),
				'duplicate_check'
			),
		);

		return array(
			'artifact_type'          => 'pre_publish_review.v1',
			'candidate_type'         => 'pre_publish_review',
			'write_posture'          => 'suggestion_only',
			'final_write_path'       => 'core_proposal_required',
			'direct_wordpress_write' => false,
			'items'                  => $items,
			'next_actions'           => array(
				'summary_suggestions',
				'category_suggestions',
				'tag_suggestions',
				'internal_links',
				'image_candidates',
				'seo_meta_single_post_handoff',
			),
			'handoff'                => array(
				'metadata'               => 'core_proposal_required',
				'seo'                    => 'core_proposal_required',
				'internal_links'         => 'operator_review_only_no_insert',
				'direct_wordpress_write' => false,
			),
		);
	}

	private function editor_pre_publish_review_item( string $name, string $status, string $detail, string $next_action ): array {
		return array(
			'name'        => sanitize_key( $name ),
			'status'      => sanitize_key( $status ),
			'detail'      => sanitize_text_field( $detail ),
			'next_action' => sanitize_key( $next_action ),
		);
	}

	private function editor_publish_preflight_checks( array $context ): array {
		$checks = array(
			array(
				'id'     => 'title',
				'status' => '' !== trim( (string) ( $context['title'] ?? '' ) ) ? 'ok' : 'warning',
				'label'  => __( 'Title', 'npcink-toolbox' ),
				'detail' => '' !== trim( (string) ( $context['title'] ?? '' ) ) ? __( 'Title is present.', 'npcink-toolbox' ) : __( 'Add a specific title before publishing.', 'npcink-toolbox' ),
			),
			array(
				'id'     => 'excerpt',
				'status' => '' !== trim( (string) ( $context['excerpt'] ?? '' ) ) ? 'ok' : 'warning',
				'label'  => __( 'Excerpt', 'npcink-toolbox' ),
				'detail' => '' !== trim( (string) ( $context['excerpt'] ?? '' ) ) ? __( 'Excerpt is present.', 'npcink-toolbox' ) : __( 'Add an excerpt or meta description candidate.', 'npcink-toolbox' ),
			),
			array(
				'id'     => 'terms',
				'status' => ! empty( $context['category_ids'] ) || ! empty( $context['tag_ids'] ) ? 'ok' : 'warning',
				'label'  => __( 'Terms', 'npcink-toolbox' ),
				'detail' => ! empty( $context['category_ids'] ) || ! empty( $context['tag_ids'] ) ? __( 'At least one category or tag is selected.', 'npcink-toolbox' ) : __( 'Review category and tag candidates before publishing.', 'npcink-toolbox' ),
			),
			array(
				'id'     => 'featured_media',
				'status' => ! empty( $context['featured_media'] ) ? 'ok' : 'warning',
				'label'  => __( 'Featured image', 'npcink-toolbox' ),
				'detail' => ! empty( $context['featured_media'] ) ? __( 'Featured image is selected.', 'npcink-toolbox' ) : __( 'Review image candidates or select a featured image.', 'npcink-toolbox' ),
			),
		);

		return array(
			'candidate_type'         => 'publish_preflight_checks',
			'write_posture'          => 'suggestion_only',
			'direct_wordpress_write' => false,
			'items'                  => $checks,
		);
	}

	private function csv_list( string $value ): array {
		$items = array_filter( array_map( 'trim', explode( ',', $value ) ) );
		return array_values(
			array_filter(
				array_map( 'sanitize_text_field', $items ),
				static fn( string $item ): bool => '' !== $item
			)
		);
	}

	private function csv_absint_list( string $value ): array {
		$items = array_filter( array_map( 'trim', explode( ',', $value ) ) );
		return array_values(
			array_filter(
				array_map( 'absint', $items ),
				static fn( int $item ): bool => 0 < $item
			)
		);
	}

	private function disabled_error( string $label ): WP_Error {
		return new WP_Error(
			'npcink_toolbox_disabled',
			sprintf(
				/* translators: %s: feature label. */
				__( 'Enable %s in Npcink Toolbox settings before running this tool.', 'npcink-toolbox' ),
				$label
			),
			array( 'status' => 403 )
		);
	}
}
