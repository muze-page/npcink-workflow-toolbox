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
	private const EDITOR_AUDIO_TEXT_MAX_CHARS = 5000;
	private const EDITOR_SELECTED_TEXT_MAX_CHARS = 2000;
	private const EDITOR_COMMENT_TEXT_MAX_CHARS = 1200;
	private const EDITOR_FLOW_CACHE_TTL = 300;
	private const EDITOR_PROGRESSIVE_TARGET_MS = 2500;
	private const EDITOR_PROGRESSIVE_CANDIDATE_LIMIT = 8;

	private Settings $settings;
	private Provider_Client $client;
	private Publish_Preflight_Service $publish_preflight;

	public function __construct( Settings $settings, Provider_Client $client, Publish_Preflight_Service $publish_preflight ) {
		$this->settings          = $settings;
		$this->client            = $client;
		$this->publish_preflight = $publish_preflight;
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
		$this->post( '/flows/article-audio-adoption-plan', 'article_audio_adoption_plan' );
		$this->post( '/local-admin-consent/featured-image', 'local_admin_consent_featured_image' );
		$this->post( '/flows/site-knowledge-review-plan', 'site_knowledge_review_plan' );
		$this->post( '/flows/nightly-inspection-review-plan', 'nightly_inspection_review_plan' );
		$this->post( '/flows/content-metadata-apply-plan', 'content_metadata_apply_plan' );
		$this->post( '/flows/media-alt-caption-review-plan', 'media_alt_caption_review_plan' );
		$this->post( '/flows/media-brief', 'media_brief' );
		$this->post( '/editor/content-support', 'editor_content_support' );
		$this->post( '/media-derivative-handoff', 'media_derivative_handoff' );
		$this->post( '/media-derivative-preview', 'create_media_derivative_preview' );
		$this->get( '/media-derivative-preview/(?P<run_id>[A-Za-z0-9._:-]+)', 'get_media_derivative_preview' );
		$this->get( '/media-derivative-preview/(?P<run_id>[A-Za-z0-9._:-]+)/result', 'get_media_derivative_preview_result' );
		$this->post( '/media-derivative-optimization-payload', 'build_media_derivative_optimization_payload' );
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

		register_rest_route(
			Plugin::REST_NAMESPACE,
			'/media-derivative-local-review/(?P<artifact_id>art_[0-9a-f]{32})',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'serve_media_derivative_local_review' ),
				'permission_callback' => array( $this, 'permission_media_derivative_local_review' ),
				'args'                => $this->media_derivative_local_review_route_args(),
			)
		);
	}

	public function permission( $request = null ): bool {
		$route          = $request instanceof WP_REST_Request ? $this->normalize_route_for_scope( (string) $request->get_route() ) : '';
		$required_scope = $this->rest_route_scope( $route );

		return (bool) apply_filters( 'npcink_toolbox_rest_permission', current_user_can( 'manage_options' ), $request, $required_scope, $route );
	}

	private function normalize_route_for_scope( string $route ): string {
		$prefix = '/' . Plugin::REST_NAMESPACE;
		if ( str_starts_with( $route, $prefix ) ) {
			$route = substr( $route, strlen( $prefix ) );
		}

		return '' === $route ? '/' : $route;
	}

	private function rest_route_scope( string $route ): string {
		if ( preg_match( '#^/media-derivative-preview/[A-Za-z0-9._:-]+(?:/result)?$#', $route ) ) {
			return 'cap.toolbox.workflow_suggest';
		}
		if ( preg_match( '#^/nightly-inspection/cloud-batch/[A-Za-z0-9._:-]+(?:/result|/retry)?$#', $route ) ) {
			return 'cap.toolbox.nightly_inspection';
		}

		$scopes = array(
			'/status'                                      => 'cap.toolbox.status.read',
			'/image-candidates'                            => 'cap.toolbox.image_source',
			'/vector-search'                               => 'cap.toolbox.vector_search',
			'/knowledge-search'                            => 'cap.toolbox.knowledge.search',
			'/web-search/test'                             => 'cap.toolbox.web_search',
			'/web-search/diagnostics'                      => 'cap.toolbox.web_search',
			'/site-knowledge/status'                       => 'cap.toolbox.knowledge.read',
			'/site-knowledge/search'                       => 'cap.toolbox.knowledge.search',
			'/site-knowledge/sync'                         => 'cap.toolbox.knowledge.sync',
			'/agent-feedback'                              => 'cap.toolbox.feedback.write',
			'/agent-feedback/summary'                      => 'cap.toolbox.feedback.read',
			'/ai/content-support'                          => 'cap.toolbox.workflow_suggest',
			'/ai/site-helpers'                             => 'cap.toolbox.workflow_suggest',
			'/ai/image-generation'                         => 'cap.toolbox.image_source',
			'/flows/article-brief'                         => 'cap.toolbox.workflow_suggest',
			'/flows/article-assistant'                     => 'cap.toolbox.workflow_suggest',
			'/flows/article-plan'                          => 'cap.toolbox.workflow_suggest',
			'/flows/image-candidate-adoption-plan'         => 'cap.toolbox.workflow_suggest',
			'/flows/article-audio-adoption-plan'           => 'cap.toolbox.workflow_suggest',
			'/local-admin-consent' . '/featured-image'     => 'cap.toolbox.local_admin_consent',
			'/flows/site-knowledge-review-plan'            => 'cap.toolbox.workflow_suggest',
			'/flows/nightly-inspection-review-plan'        => 'cap.toolbox.workflow_suggest',
			'/flows/content-metadata-apply-plan'           => 'cap.toolbox.workflow_suggest',
			'/flows/media-alt-caption-review-plan'         => 'cap.toolbox.workflow_suggest',
			'/flows/media-brief'                           => 'cap.toolbox.workflow_suggest',
			'/editor/content-support'                      => 'cap.toolbox.workflow_suggest',
			'/media-derivative-handoff'                    => 'cap.toolbox.workflow_suggest',
			'/media-derivative-preview'                    => 'cap.toolbox.workflow_suggest',
			'/media-derivative-optimization-payload'       => 'cap.toolbox.workflow_suggest',
			'/media-derivative-local-review/(?P<artifact_id>art_[0-9a-f]{32})' => 'cap.toolbox.workflow_suggest',
			'/nightly-inspection/cloud-runtime-entitlement' => 'cap.toolbox.nightly_inspection',
			'/nightly-inspection/cloud-batch'              => 'cap.toolbox.nightly_inspection',
			'/nightly-inspection/cloud-batch/recent'       => 'cap.toolbox.nightly_inspection',
		);

		return $scopes[ $route ] ?? 'cap.toolbox.admin';
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
				'raw_responses_enabled'    => $this->settings->raw_responses_enabled(),
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
				'content_operations'      => $this->content_operations_projection( $cloud_ready ),
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

	private function content_operations_projection( bool $cloud_ready ): array {
		return array(
			'contract_version'      => 'toolbox_content_operations_projection.v1',
			'registered'            => true,
			'available'             => $cloud_ready,
			'write_posture'         => 'suggestion_only',
			'final_write_path'      => 'core_proposal_required',
			'approval_truth'        => 'wordpress_local',
			'final_write_truth'     => 'wordpress_local',
			'direct_wordpress_write' => false,
			'projection_role'       => 'single_toolbox_status_projection',
			'surfaces'              => array(
				'editor_content_support' => array(
					'route'          => '/editor/content-support',
					'artifact_type'  => 'editor_content_support_flow',
					'source_layers'  => array( 'local_editor_context', 'cloud_site_knowledge', 'cloud_web_search', 'hosted_ai' ),
					'intents'        => array( 'progressive_recommendations', 'writing_support', 'zhihu_research', 'zhihu_hot_topics', 'article_checkup', 'title_suggestions', 'article_outline', 'polish_notes', 'summary_suggestions', 'category_suggestions', 'tag_suggestions', 'summary_terms_optimization', 'taxonomy_tags', 'internal_links', 'image_candidates', 'image_alt_suggestions', 'comment_reply_suggestion', 'publish_preflight', 'discoverability' ),
					'feedback_scope' => 'editor_content_support',
				),
				'nightly_inspection'     => array(
					'route'          => '/nightly-inspection/cloud-batch',
					'contracts'      => array( 'nightly_site_inspection_morning_brief.v2', 'nightly_site_inspection_core_intake_package.v1' ),
					'source_layers'  => array( 'local_site_snapshot', 'cloud_batch_runtime' ),
					'feedback_scope' => 'nightly_site_inspection',
				),
				'site_knowledge'         => array(
					'route'          => '/site-knowledge/search',
					'intents'        => array( 'site_search', 'related_content', 'writing_context', 'internal_links', 'refresh_suggestions', 'image_context', 'faq_candidates', 'content_gap_analysis', 'duplicate_check', 'writing_support_plan' ),
					'source_layers'  => array( 'cloud_site_knowledge' ),
					'feedback_scope' => 'site_knowledge',
				),
				'media_site_helpers'     => array(
					'route'          => '/ai/site-helpers',
					'contracts'      => array( 'media_alt_caption_review_set.v1', 'current_article_image_alt_suggestions.v1' ),
					'source_layers'  => array( 'media_library_metadata_only_no_pixel_vision', 'hosted_ai' ),
					'feedback_scope' => 'media_alt_caption',
				),
			),
			'gap_contracts'         => array(
				'seo_metadata_suggestion.v1'       => array(
					'state'                => 'covered_by_existing_projection',
					'current_artifacts'    => array( 'seo_meta_handoff_preview.v1', 'content_metadata_delta' ),
					'route'                => '/editor/content-support',
					'final_write_path'     => 'core_proposal_required',
					'target_ability_id'    => 'npcink-abilities-toolkit/set-post-seo-meta',
					'direct_wordpress_write' => false,
					'feedback_scope'       => 'seo_metadata',
				),
				'media_alt_caption_suggestion.v1'  => array(
					'state'                => 'covered_by_existing_projection',
					'current_artifacts'    => array( 'media_alt_caption_review_set.v1', 'current_article_image_alt_suggestions.v1' ),
					'route'                => '/ai/site-helpers',
					'evidence_policy'      => 'media_library_metadata_only_no_pixel_vision',
					'direct_wordpress_write' => false,
					'feedback_scope'       => 'media_alt_caption',
				),
				'comment_reply_suggestion.v1'      => array(
					'state'                => 'covered_by_existing_projection',
					'current_artifacts'    => array( 'comment_reply_suggestion.v1' ),
					'route'                => '/editor/content-support',
					'final_write_path'     => 'core_proposal_required',
					'direct_wordpress_write' => false,
					'feedback_scope'       => 'comment_reply',
				),
			),
			'feedback'              => array(
				'route'              => '/agent-feedback',
				'summary_route'      => '/agent-feedback/summary',
				'contract_version'   => 'cloud_agent_feedback.v1',
				'quality_owner'      => 'cloud_eval_only',
				'mutation_scope'     => 'none',
				'source_runtimes'    => array( 'editor_content_support', 'image_candidates', 'nightly_site_inspection', 'site_knowledge', 'seo_metadata', 'media_alt_caption', 'comment_reply' ),
				'direct_wordpress_write' => false,
			),
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
				__( 'A query or vector field is required for vector search.', 'npcink-workflow-toolbox' ),
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
			$change_bridge = Site_Knowledge_Auto_Sync::health_snapshot();
			$status['change_bridge'] = $change_bridge;
			$status['auto_sync']     = $change_bridge;
			if ( ! is_array( $status['site_knowledge_cloud_boundary'] ?? null ) ) {
				$boundary = Site_Knowledge_Auto_Sync::cloud_boundary_projection( $change_bridge );
				if ( array() !== $boundary ) {
					$status['site_knowledge_cloud_boundary'] = $boundary;
				}
			}
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
					'managed_source'      => sanitize_key( (string) $request->get_param( 'managed_source' ) ),
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
					'scenario' => sanitize_key( (string) ( $request->get_param( 'scenario' ) ?: 'discoverability' ) ),
				)
			)
		);
	}

	public function site_knowledge_sync( WP_REST_Request $request ) {
		$sync_mode = sanitize_key( (string) ( $request->get_param( 'sync_mode' ) ?: 'refresh' ) );
		if ( 'refresh' !== $sync_mode ) {
			return new WP_Error(
				'npcink_toolbox_site_knowledge_sync_mode_not_allowed',
				__( 'Toolbox only forwards public Site Knowledge refresh requests. Rebuild, delete, and collection lifecycle operations belong in Cloud Site Knowledge.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}

		return rest_ensure_response(
			$this->client->request_site_knowledge_sync(
				array(
					'sync_mode' => 'refresh',
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
				__( 'Connect Npcink Cloud before submitting Pro Nightly Inspection batches.', 'npcink-workflow-toolbox' ),
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
				__( 'Connect Npcink Cloud before reading Pro Nightly Inspection batches.', 'npcink-workflow-toolbox' ),
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
				__( 'Connect Npcink Cloud before reading recent Pro Nightly Inspection runs.', 'npcink-workflow-toolbox' ),
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
				__( 'Connect Npcink Cloud before reading Pro Cloud Runtime entitlement.', 'npcink-workflow-toolbox' ),
				array( 'status' => 503 )
			);
		}

		return rest_ensure_response( $this->client->get_nightly_inspection_cloud_runtime_entitlement() );
	}

	public function nightly_inspection_cloud_batch_result( WP_REST_Request $request ) {
		if ( ! $this->settings->cloud_runtime_available() ) {
			return new WP_Error(
				'npcink_toolbox_nightly_inspection_cloud_batch_unavailable',
				__( 'Connect Npcink Cloud before reading Pro Nightly Inspection batches.', 'npcink-workflow-toolbox' ),
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
				__( 'Connect Npcink Cloud before retrying Pro Nightly Inspection runs.', 'npcink-workflow-toolbox' ),
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

	public function article_audio_adoption_plan( WP_REST_Request $request ) {
		$params = method_exists( $request, 'get_params' ) ? $request->get_params() : array();
		return rest_ensure_response( $this->client->build_article_audio_adoption_plan( is_array( $params ) ? $params : array() ) );
	}

	public function local_admin_consent_featured_image( WP_REST_Request $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'npcink_toolbox_local_featured_image_admin_required',
				__( 'Local admin consent requires an administrator session.', 'npcink-workflow-toolbox' ),
				array( 'status' => 403 )
			);
		}

		$post_id       = absint( $request->get_param( 'post_id' ) );
		$attachment_id = absint( $request->get_param( 'attachment_id' ) );
		if ( $post_id <= 0 || $attachment_id <= 0 ) {
			return new WP_Error(
				'npcink_toolbox_local_featured_image_target_required',
				__( 'A post_id and existing attachment_id are required.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$post = get_post( $post_id );
		$attachment = get_post( $attachment_id );
		if ( ! $post || ! $attachment || 'attachment' !== get_post_type( $attachment ) ) {
			return new WP_Error(
				'npcink_toolbox_local_featured_image_target_not_found',
				__( 'The target post or media attachment was not found.', 'npcink-workflow-toolbox' ),
				array( 'status' => 404 )
			);
		}

		if ( ! current_user_can( 'edit_post', $post_id ) || ! current_user_can( 'edit_post', $attachment_id ) ) {
			return new WP_Error(
				'npcink_toolbox_local_featured_image_permission_denied',
				__( 'You do not have permission to update this featured image.', 'npcink-workflow-toolbox' ),
				array( 'status' => 403 )
			);
		}

		if ( function_exists( 'wp_attachment_is_image' ) && ! wp_attachment_is_image( $attachment_id ) ) {
			return new WP_Error(
				'npcink_toolbox_local_featured_image_attachment_not_image',
				__( 'Local admin consent can set only existing image attachments as featured images.', 'npcink-workflow-toolbox' ),
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
				__( 'This featured image action is not eligible for local admin consent.', 'npcink-workflow-toolbox' ),
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
				__( 'WordPress did not accept the featured image update.', 'npcink-workflow-toolbox' ),
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
				__( 'The featured image update could not be fully audited and was rolled back.', 'npcink-workflow-toolbox' ),
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

		public function media_alt_caption_review_plan( WP_REST_Request $request ) {
			$params = method_exists( $request, 'get_params' ) ? $request->get_params() : array();
			return rest_ensure_response( $this->client->build_media_alt_caption_review_plan( is_array( $params ) ? $params : array() ) );
		}

		public function media_brief( WP_REST_Request $request ) {
		$post_id = absint( $request->get_param( 'post_id' ) );
		if ( 0 === $post_id ) {
			return new WP_Error(
				'npcink_toolbox_missing_post_id',
				__( 'A post_id is required for the media brief flow.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error(
				'npcink_toolbox_post_not_found',
				__( 'The requested post was not found.', 'npcink-workflow-toolbox' ),
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

		return rest_ensure_response(
			$this->client->build_media_brief(
				(string) $context,
				array(
					'refresh_variant' => sanitize_text_field( (string) ( $request->get_param( 'refresh_variant' ) ?: '' ) ),
					'image_mode'      => sanitize_key( (string) ( $request->get_param( 'image_mode' ) ?: 'featured_image' ) ),
				)
			)
		);
	}

	public function editor_content_support( WP_REST_Request $request ) {
		$intent = sanitize_key( (string) ( $request->get_param( 'intent' ) ?: '' ) );
		if ( ! in_array( $intent, array( 'progressive_recommendations', 'source_adaptation_review', 'writing_support', 'zhihu_research', 'zhihu_hot_topics', 'article_checkup', 'title_suggestions', 'article_outline', 'polish_notes', 'summary_suggestions', 'article_narration', 'article_audio_summary', 'category_suggestions', 'tag_suggestions', 'summary_terms_optimization', 'taxonomy_tags', 'internal_links', 'image_candidates', 'image_alt_suggestions', 'comment_reply_suggestion', 'publish_preflight', 'discoverability' ), true ) ) {
			return new WP_Error(
				'npcink_toolbox_invalid_editor_support_intent',
				__( 'A supported editor content-support intent is required.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$context = $this->editor_post_context( $request );
		if ( 'source_adaptation_review' === $intent ) {
			$input_mode = sanitize_key( (string) ( $request->get_param( 'input_mode' ) ?: 'url_reference' ) );
			if ( ! in_array( $input_mode, array( 'url_reference', 'manual_brief', 'mixed' ), true ) ) {
				return new WP_Error(
					'npcink_toolbox_writing_pack_input_mode_not_supported',
					__( 'Choose URL reference, manual brief, or mixed input for the article writing pack.', 'npcink-workflow-toolbox' ),
					array( 'status' => 400 )
				);
			}
			$default_stage = 'manual_brief' === $input_mode ? 'research_plan' : 'extract';
			$source_stage = sanitize_key( (string) ( $request->get_param( 'source_stage' ) ?: $default_stage ) );
			$source_url = '';
			if ( 'draft' !== $source_stage && in_array( $input_mode, array( 'url_reference', 'mixed' ), true ) ) {
				$source_url = $this->editor_source_adaptation_url( (string) $request->get_param( 'source_url' ) );
				if ( is_wp_error( $source_url ) ) {
					return $source_url;
				}
			}
			$context['source_url'] = $source_url;
			$context['source_stage'] = in_array( $source_stage, array( 'extract', 'adapt', 'research_plan', 'draft' ), true ) ? $source_stage : $default_stage;
			$context['source_stage_requested'] = $context['source_stage'];
			$context['input_mode']   = $input_mode;
			$context['editorial_brief'] = $this->editor_writing_pack_request_brief( $request->get_param( 'editorial_brief' ) );
			$context['reviewed_writing_pack'] = $request->get_param( 'reviewed_writing_pack' );
			$context['writing_pack_confirmation'] = $request->get_param( 'writing_pack_confirmation' );
			$context['draft_review_feedback'] = 'draft' === $context['source_stage']
				? $this->editor_draft_review_feedback_request( $request->get_param( 'draft_review_feedback' ) )
				: array();
			$context['user_instruction'] = (string) ( $context['editorial_brief']['operator_instruction'] ?? '' );
		}
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
						__( 'Select paragraph text before running paragraph review.', 'npcink-workflow-toolbox' ),
						array( 'status' => 400 )
					);
				}
				$context['context_scope'] = 'selected_text';
			}
			$query   = 'source_adaptation_review' === $intent
				? $this->editor_writing_pack_query( $context )
				: $this->editor_support_query( $context );
		if ( 'image_candidates' === $intent ) {
			$query = $this->editor_image_support_query( $context );
		}
		if ( '' === $query && ! in_array( $intent, array( 'progressive_recommendations', 'zhihu_hot_topics' ), true ) ) {
			return new WP_Error(
				'npcink_toolbox_missing_editor_context',
				__( 'A title, excerpt, or post content is required for editor content support.', 'npcink-workflow-toolbox' ),
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

		if ( 'source_adaptation_review' === $intent ) {
			$source_url   = (string) ( $context['source_url'] ?? '' );
			$source_stage = (string) ( $context['source_stage'] ?? 'extract' );
			if ( 'adapt' === $source_stage ) {
				$source_stage = 'research_plan';
			}
			if ( 'draft' === $source_stage ) {
				return $this->editor_article_draft_response( $context, $result );
			}
			if ( 'manual_brief' === (string) ( $context['input_mode'] ?? '' ) ) {
				return $this->editor_manual_writing_pack_response( $context, $result );
			}
			$external_raw  = $this->editor_cached_cloud_web_search(
				array(
					'query'        => $source_url,
					'source_url'   => $source_url,
					'intent'       => 'source_extraction_preview',
					'max_results'  => 1,
					'recency_days' => 0,
				)
			);
			$external       = $this->editor_support_section( $external_raw );
			$source_item    = ! is_wp_error( $external_raw ) && is_array( $external_raw['results'][0] ?? null ) ? $external_raw['results'][0] : array();
			$source_text    = trim( (string) ( $source_item['reader_excerpt'] ?? $source_item['snippet'] ?? '' ) );
			$source_title   = sanitize_text_field( (string) ( $external['title'] ?? $source_item['title'] ?? '' ) );
			$source_body_text = $this->editor_source_article_body_text( $source_title, $source_text );
			$source_resolved_url = esc_url_raw( (string) ( $external['resolved_url'] ?? $source_item['url'] ?? '' ) );
			$cloud_url_match = sanitize_key( (string) ( $external['url_match'] ?? '' ) );
			$source_url_matches = 'matched' === $cloud_url_match && $this->editor_source_adaptation_url_matches( $source_url, $source_resolved_url );
			$result['sections']['source_article'] = $external;
			$result['sections']['source_article']['requested_url'] = esc_url_raw( (string) ( $external['requested_url'] ?? $source_url ) );
			$result['sections']['source_article']['resolved_url']  = $source_resolved_url;
			$result['sections']['source_article']['url_match']     = $source_url_matches ? 'matched' : ( $cloud_url_match ?: 'unavailable' );
			$source_body_ready = $source_url_matches && $this->editor_source_body_is_draftable( $source_body_text );
			$result['sections']['source_article']['body_ready'] = $source_body_ready;
			$result['sections']['source_article']['body_readiness'] = $source_body_ready ? 'ready' : 'insufficient';
			$result['artifact_type']              = 'source_extraction_preview.v1';
			$result['contract_version']           = 'source_extraction_preview.v1';
			$result['input_mode']                 = (string) ( $context['input_mode'] ?? 'url_reference' );
			$result['composition_role']           = 'external_source_extraction_review';
			$result['final_write_path']            = 'operator_review_only_no_insert';
			$result['handoff']['final_writes']     = 'operator_review_only_no_insert';
			$result['handoff']['source_runtime']   = 'cloud_exact_url_reader';
			$result['handoff']['body_generation']  = false;
			$result['handoff']['body_replacement'] = false;

			if ( 'extract' === $source_stage ) {
				$result['recommendation_set']  = $this->editor_recommendation_set( $context, $intent, $result['sections'] );
				$result['content_fingerprint'] = $result['recommendation_set']['content_fingerprint'];
				return rest_ensure_response( $result );
			}

			if ( ! $source_url_matches ) {
				$result['sections']['source_adaptation_review'] = array(
					'status'                 => 'blocked',
					'message'                => __( 'Cloud search returned a different article URL on the same site. Verify the source URL before continuing.', 'npcink-workflow-toolbox' ),
					'write_posture'          => 'suggestion_only',
					'direct_wordpress_write' => false,
				);
			} elseif ( $source_body_ready ) {
				$site_query = trim( $source_title . ' ' . wp_trim_words( wp_strip_all_tags( $source_body_text ), 80, '' ) );
				$knowledge_raw = $this->editor_cached_site_knowledge(
					array(
						'query'           => $site_query,
						'intent'          => 'writing_support_plan',
						'result_granularity' => 'document',
						'current_post_id' => absint( $context['post_id'] ?? 0 ),
						'max_results'     => 6,
					)
				);
				$result['sections']['source_site_context'] = $this->editor_support_section( $knowledge_raw );
				$result['sections']['source_adaptation_review'] = $this->editor_hosted_source_adaptation_review(
					$context,
					array(
						'title'         => $source_title,
						'url'           => $source_resolved_url,
						'content'       => $source_body_text,
						'reader_status' => sanitize_key( (string) ( $source_item['reader_status'] ?? 'snippet_only' ) ),
					),
					is_wp_error( $knowledge_raw ) ? array() : $knowledge_raw
				);
			} else {
				$result['sections']['source_adaptation_review'] = array(
					'status'                 => 'blocked',
					'message'                => __( 'The source reader did not return enough article body text. Try another public article URL; no draft will be generated from navigation or metadata alone.', 'npcink-workflow-toolbox' ),
					'write_posture'          => 'suggestion_only',
					'direct_wordpress_write' => false,
				);
			}

			$result['sections']['article_writing_pack'] = $this->editor_article_writing_pack(
				$context,
				$result['sections']['source_article'],
				$result['sections']['source_site_context'] ?? array(),
				$result['sections']['source_adaptation_review'] ?? array(),
				$source_body_text
			);
			$legacy_adapt_stage = 'adapt' === (string) ( $context['source_stage_requested'] ?? '' );
			$result['artifact_type']              = $legacy_adapt_stage ? 'source_adaptation_review.v1' : 'article_writing_pack.v1';
			$result['contract_version']           = $legacy_adapt_stage ? 'source_adaptation_review.v1' : 'article_writing_pack.v1';
			$result['primary_artifact_type']      = 'article_writing_pack.v1';
			$result['input_mode']                 = (string) ( $context['input_mode'] ?? 'url_reference' );
			$result['composition_role']           = 'source_grounded_article_planning';
			$result['final_write_path']            = 'operator_review_only_no_insert';
			$result['handoff']['final_writes']     = 'operator_review_only_no_insert';
			$result['handoff']['source_runtime']   = 'cloud_exact_url_reader';
			$result['handoff']['style_runtime']    = 'cloud_site_knowledge';
			$result['handoff']['required_input_contract'] = 'article_writing_pack.v1';
			$result['handoff']['article_generation_status'] = 'not_admitted_current_stage';
			$result['handoff']['body_generation']  = false;
			$result['handoff']['body_replacement'] = false;
			$result['recommendation_set']          = $this->editor_recommendation_set( $context, $intent, $result['sections'] );
			$result['content_fingerprint']         = $result['recommendation_set']['content_fingerprint'];
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

			if ( 'zhihu_research' === $intent ) {
				$result['sections']['zhihu_research'] = $this->editor_support_section(
					$this->editor_cached_cloud_web_search(
						array(
							'query'          => $query,
							'intent'         => 'zhihu_research',
							'managed_source' => 'zhihu_research',
							'max_results'    => 5,
							'recency_days'   => 30,
						)
					)
				);
			}

			if ( 'zhihu_hot_topics' === $intent ) {
				$result['sections']['zhihu_hot_topics'] = $this->editor_support_section(
					$this->editor_cached_cloud_web_search(
						array(
							'query'          => '知乎热榜',
							'intent'         => 'zhihu_hot_topics',
							'managed_source' => 'zhihu_hot_topics',
							'max_results'    => 5,
							'recency_days'   => 1,
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

		if ( 'article_narration' === $intent ) {
			$result['sections']['audio_generation'] = $this->editor_article_audio_generation( $context, 'article_narration' );
		}

		if ( 'article_audio_summary' === $intent ) {
			$result['sections']['audio_generation'] = $this->editor_article_audio_generation( $context, 'article_audio_summary' );
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

		if ( 'comment_reply_suggestion' === $intent ) {
			$result['sections']['comment_reply_suggestion'] = $this->editor_comment_reply_suggestions( $context );
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
			$result['sections']['seo_handoff'] = $this->publish_preflight->seo_handoff_preview( $context, $result['sections']['discoverability'] );
		}

		if ( 'publish_preflight' === $intent ) {
			$duplicate_check = $this->editor_support_section(
				$this->editor_cached_site_knowledge(
					array(
						'query'           => $query,
						'intent'          => 'duplicate_check',
						'current_post_id' => absint( $context['post_id'] ?? 0 ),
						'max_results'     => 5,
					)
				)
			);
			$result['sections'] = array_merge(
				$result['sections'],
				$this->publish_preflight->build_sections( $context, $result['sections']['discoverability'], $duplicate_check )
			);
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
	 * Starts one preview-only Cloud derivative run through the Cloud Addon seam.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_media_derivative_preview( WP_REST_Request $request ) {
		$preview_input = $this->media_derivative_preview_input( $request );
		if ( is_wp_error( $preview_input ) ) {
			return $preview_input;
		}

		if ( ! function_exists( 'npcink_cloud_addon_dispatch_media_derivative_cloud_request' ) ) {
			return $this->media_derivative_cloud_addon_unavailable();
		}

		$watermark_id = absint( $preview_input['watermark_attachment_id'] ?? 0 );
		$ability_input = $preview_input;
		unset( $ability_input['watermark_attachment_id'] );

		$ability_response = $this->run_toolkit_ability( 'npcink-abilities-toolkit/build-media-derivative-cloud-request', $ability_input );
		if ( is_wp_error( $ability_response ) ) {
			return $ability_response;
		}

		$source_artifact = $this->media_derivative_attachment_descriptor( absint( $ability_input['attachment_id'] ?? 0 ) );
		if ( is_wp_error( $source_artifact ) ) {
			return $source_artifact;
		}

		$watermark_artifact = array();
		if ( $watermark_id > 0 ) {
			$watermark_artifact = $this->media_derivative_attachment_descriptor( $watermark_id );
			if ( is_wp_error( $watermark_artifact ) ) {
				return $watermark_artifact;
			}
		}

		$trace_id = sanitize_text_field( (string) ( $request->get_param( 'trace_id' ) ?: wp_generate_uuid4() ) );
		$dispatch = npcink_cloud_addon_dispatch_media_derivative_cloud_request(
			$ability_response,
			$source_artifact,
			$trace_id,
			sanitize_text_field( (string) $request->get_param( 'idempotency_key' ) ),
			$watermark_artifact
		);
		if ( is_wp_error( $dispatch ) ) {
			return $dispatch;
		}

		$cloud_run = is_array( $dispatch ) ? $dispatch : array();
		$run_id    = sanitize_text_field( (string) ( $cloud_run['run_id'] ?? '' ) );

		return new WP_REST_Response(
			array(
				'contract_version' => 'toolbox_media_derivative_preview.v2',
				'status'           => 'submitted',
				'run_id'           => sanitize_text_field( (string) $run_id ),
				'cloud_run'        => $cloud_run,
				'ability_response' => $ability_response,
				'write_posture'    => 'preview_only',
				'direct_wordpress_write' => false,
				'core_proposal_created'   => false,
			),
			202
		);
	}

	public function get_media_derivative_preview( WP_REST_Request $request ) {
		if ( ! function_exists( 'npcink_cloud_addon_get_media_derivative_run' ) ) {
			return $this->media_derivative_cloud_addon_unavailable();
		}

		$result = npcink_cloud_addon_get_media_derivative_run(
			sanitize_text_field( (string) $request->get_param( 'run_id' ) ),
			sanitize_text_field( (string) $request->get_param( 'trace_id' ) )
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'contract_version' => 'toolbox_media_derivative_preview_status.v2',
				'cloud_run'        => is_array( $result ) ? $result : array(),
				'local_review'     => $this->media_derivative_local_review_projection( is_array( $result ) ? $result : array() ),
				'direct_wordpress_write' => false,
			)
		);
	}

	public function get_media_derivative_preview_result( WP_REST_Request $request ) {
		if ( ! function_exists( 'npcink_cloud_addon_get_media_derivative_run_result' ) ) {
			return $this->media_derivative_cloud_addon_unavailable();
		}

		$result = npcink_cloud_addon_get_media_derivative_run_result(
			sanitize_text_field( (string) $request->get_param( 'run_id' ) ),
			sanitize_text_field( (string) $request->get_param( 'trace_id' ) )
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$cloud_result = is_array( $result ) ? $result : array();
		$local_review = $this->media_derivative_local_review_projection( $cloud_result );
		if ( empty( $local_review ) ) {
			return new WP_Error(
				'npcink_toolbox_media_derivative_local_review_unavailable',
				__( 'Cloud media derivative result did not include a valid local review artifact.', 'npcink-workflow-toolbox' ),
				array( 'status' => 502 )
			);
		}

		return rest_ensure_response(
			array(
				'contract_version' => 'toolbox_media_derivative_preview_result.v2',
				'cloud_result'     => $cloud_result,
				'local_review'     => $local_review,
				'direct_wordpress_write' => false,
			)
		);
	}

	public function build_media_derivative_optimization_payload( WP_REST_Request $request ) {
		if ( ! function_exists( 'npcink_cloud_addon_build_media_derivative_optimization_payload' ) ) {
			return $this->media_derivative_cloud_addon_unavailable();
		}

		$payload = npcink_cloud_addon_build_media_derivative_optimization_payload(
			$this->media_derivative_object_param( $request, 'ability_response' ),
			$this->media_derivative_object_param( $request, 'cloud_result' ),
			$this->media_derivative_object_param( $request, 'derivative_artifact' ),
			$this->media_derivative_object_param( $request, 'media_details_input' )
		);

		return is_wp_error( $payload ) ? $payload : rest_ensure_response( $payload );
	}

	public function permission_media_derivative_local_review(): bool {
		return current_user_can( 'manage_options' );
	}

	public function serve_media_derivative_local_review( WP_REST_Request $request ) {
		if ( ! function_exists( 'npcink_cloud_addon_receive_media_derivative_artifact' ) ) {
			return $this->media_derivative_cloud_addon_unavailable();
		}
		$allowed_params = array( 'artifact_id', 'artifact' );
		$unknown_params = array_diff( array_keys( $request->get_params() ), $allowed_params );
		$query_params   = method_exists( $request, 'get_query_params' ) ? $request->get_query_params() : array();
		$json_params    = method_exists( $request, 'get_json_params' ) ? $request->get_json_params() : array();
		if (
			! empty( $unknown_params )
			|| ! empty( $query_params )
			|| ! is_array( $json_params )
			|| array( 'artifact' ) !== array_keys( $json_params )
		) {
			return new WP_Error(
				'npcink_toolbox_media_derivative_local_review_args_invalid',
				__( 'Media derivative local review requires one exact JSON artifact body and no query parameters.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400, 'unsupported_fields' => array_values( $unknown_params ) )
			);
		}

		$artifact = $this->media_derivative_local_review_artifact_from_request( $request );
		if ( is_wp_error( $artifact ) ) {
			return $artifact;
		}
		$artifact_id = (string) $artifact['artifact_id'];
		$expected_local_artifact_keys = array(
			'artifact_id',
			'expires_at',
			'mime_type',
			'format',
			'width',
			'height',
			'filesize_bytes',
			'sha256',
			'suggested_filename',
			'filename_basis',
			'processing_warnings',
		);
		if ( $expected_local_artifact_keys !== array_keys( $artifact ) ) {
			return new WP_Error(
				'npcink_toolbox_media_derivative_local_review_contract_invalid',
				__( 'Media derivative local review could not build the exact Addon receive contract.', 'npcink-workflow-toolbox' ),
				array( 'status' => 500 )
			);
		}
		$received = npcink_cloud_addon_receive_media_derivative_artifact( $artifact );
		if ( is_wp_error( $received ) ) {
			return $received;
		}

		$contents  = is_string( $received['contents'] ?? null ) ? $received['contents'] : '';
		$mime_type = sanitize_text_field( (string) ( $received['mime_type'] ?? '' ) );
		if ( '' === $contents || ! in_array( $mime_type, array( 'image/avif', 'image/jpeg', 'image/png', 'image/webp' ), true ) ) {
			return new WP_Error(
				'npcink_toolbox_media_derivative_local_review_invalid',
				__( 'Cloud Addon did not return verified media derivative bytes.', 'npcink-workflow-toolbox' ),
				array( 'status' => 502 )
			);
		}
		if ( function_exists( 'status_header' ) ) {
			status_header( 200 );
		}
		nocache_headers();
		header( 'Content-Type: ' . $mime_type );
		header( 'Content-Length: ' . strlen( $contents ) );
		header( 'Content-Disposition: inline; filename="' . (string) $artifact['suggested_filename'] . '"' );
		header( 'Cache-Control: private, no-store, max-age=0' );
		header( 'Pragma: no-cache' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Npcink-Artifact-Id: ' . $artifact_id );
		echo $contents; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
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
			'policy_version'         => sanitize_text_field( (string) ( $classification['policy_version'] ?? Operation_Classifier::POLICY_VERSION ) ),
			'reasons'                => array_values( array_map( 'sanitize_key', (array) ( $classification['reasons'] ?? array() ) ) ),
			'required_evidence'      => array_values( array_map( 'sanitize_key', (array) ( $classification['required_evidence'] ?? array() ) ) ),
			'operation_classification' => $classification,
			'actor_user_id'          => get_current_user_id(),
			'target_object_type'     => 'post',
			'target_object_id'       => $post_id,
			'post_id'                => $post_id,
			'attachment_id'          => $attachment_id,
			'before_attachment_id'   => $before_attachment_id,
			'ai_suggestion_summary'  => '' !== $title ? $title : __( 'Set one reviewed existing media image as the featured image.', 'npcink-workflow-toolbox' ),
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
				__( 'Governance Core local consent audit is unavailable.', 'npcink-workflow-toolbox' ),
				array( 'status' => 503 )
			);
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return is_array( $result ) ? $result : array( 'event_id' => sanitize_text_field( (string) $result ) );
	}

	private function post( string $route, string $method, array $args = array() ): void {
		register_rest_route(
			Plugin::REST_NAMESPACE,
			$route,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, $method ),
				'permission_callback' => array( $this, 'permission' ),
				'args'                => $args,
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
					__( '%s is required.', 'npcink-workflow-toolbox' ),
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
		$content_raw         = (string) $request->get_param( 'content' );
		$audio_preferences   = $this->editor_audio_preferences_from_request( $request );
		$content             = trim( wp_strip_all_tags( $content_raw ) );
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
			'content_audio_text'  => sanitize_textarea_field( $this->editor_trim_chars( $this->editor_audio_text_from_raw_content( $content_raw, $audio_preferences ), self::EDITOR_AUDIO_TEXT_MAX_CHARS ) ),
			'selected_text'       => wp_trim_words( sanitize_textarea_field( $selected_text ), 110, '' ),
			'selected_block_text' => wp_trim_words( sanitize_textarea_field( $selected_block_text ), 110, '' ),
			'selected_text_full'       => sanitize_textarea_field( $this->editor_trim_chars( $selected_text, self::EDITOR_SELECTED_TEXT_MAX_CHARS ) ),
			'selected_block_text_full' => sanitize_textarea_field( $this->editor_trim_chars( $selected_block_text, self::EDITOR_SELECTED_TEXT_MAX_CHARS ) ),
			'selected_block_name' => sanitize_text_field( (string) $request->get_param( 'selected_block_name' ) ),
			'user_instruction'    => wp_trim_words( sanitize_textarea_field( $user_instruction ), 60, '' ),
			'audio_preferences'   => $audio_preferences,
			'generation_variant'  => sanitize_text_field( (string) $request->get_param( 'generation_variant' ) ),
			'force_regenerate'    => (bool) $request->get_param( 'force_regenerate' ),
			'summary_generation_mode' => $summary_mode,
			'image_mode'          => sanitize_key( (string) $request->get_param( 'image_mode' ) ),
			'category_ids'        => $this->csv_absint_list( (string) $request->get_param( 'category_ids' ) ),
			'tag_ids'             => $this->csv_absint_list( (string) $request->get_param( 'tag_ids' ) ),
			'featured_media'      => absint( $request->get_param( 'featured_media' ) ),
			'media_items'         => $this->editor_media_items_from_request( $request ),
			'comment_id'          => absint( $request->get_param( 'comment_id' ) ),
			'comment_author'      => sanitize_text_field( (string) $request->get_param( 'comment_author' ) ),
			'comment_text'        => sanitize_textarea_field( $this->editor_trim_chars( trim( wp_strip_all_tags( (string) $request->get_param( 'comment_text' ) ) ), self::EDITOR_COMMENT_TEXT_MAX_CHARS ) ),
		);
	}

	private function editor_audio_preferences_from_request( WP_REST_Request $request ): array {
		$raw = $request->get_param( 'audio_preferences' );
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}
		$defaults = array(
			'tone'     => 'calm',
			'pace'     => 'normal',
			'handling' => 'skip_code',
			'focus'    => 'product_names',
		);
		$allowed = array(
			'tone'     => array( 'calm', 'formal', 'casual', 'expressive' ),
			'pace'     => array( 'normal', 'slow', 'fast' ),
			'handling' => array( 'skip_code', 'read_code', 'skip_tables' ),
			'focus'    => array( 'product_names', 'numbers', 'headings' ),
		);
		$preferences = array();
		foreach ( $defaults as $key => $default ) {
			$value = sanitize_key( (string) ( $raw[ $key ] ?? $default ) );
			$preferences[ $key ] = in_array( $value, $allowed[ $key ], true ) ? $value : $default;
		}
		return $preferences;
	}

	private function editor_audio_text_from_raw_content( string $content, array $audio_preferences ): string {
		$source   = $content;
		$handling = sanitize_key( (string) ( $audio_preferences['handling'] ?? 'skip_code' ) );
		if ( 'skip_code' === $handling ) {
			$source = preg_replace( '/<!--\s*wp:code\b.*?<!--\s*\/wp:code\s*-->/is', ' ', $source );
			$source = preg_replace( '/<pre\b[^>]*>.*?<\/pre>/is', ' ', $source );
			$source = preg_replace( '/<code\b[^>]*>.*?<\/code>/is', ' ', $source );
		}
		if ( 'skip_tables' === $handling ) {
			$source = preg_replace( '/<!--\s*wp:table\b.*?<!--\s*\/wp:table\s*-->/is', ' ', $source );
			$source = preg_replace( '/<table\b[^>]*>.*?<\/table>/is', ' ', $source );
		}
		return trim( wp_strip_all_tags( (string) $source ) );
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
		$intent = sanitize_key( (string) $request->get_param( 'intent' ) );

		$featured_media = absint( $request->get_param( 'featured_media' ) );
		if ( 'image_alt_suggestions' !== $intent && $featured_media > 0 ) {
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
			$key = ! empty( $media_item['occurrence_id'] )
				? 'occurrence:' . (string) $media_item['occurrence_id']
				: ( ! empty( $media_item['attachment_id'] )
					? 'id:' . (string) $media_item['attachment_id']
					: 'url:' . (string) ( $media_item['url'] ?? '' ) );
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
			'mime_type'     => sanitize_text_field( (string) ( $attachment->post_mime_type ?? '' ) ),
			'alt'           => function_exists( 'get_post_meta' ) ? sanitize_text_field( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ) : '',
			'missing_alt'   => function_exists( 'get_post_meta' ) ? '' === trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ) : true,
			'thumbnail_url' => is_array( $image_src ) ? esc_url_raw( (string) ( $image_src[0] ?? '' ) ) : '',
			'url'           => function_exists( 'wp_get_attachment_url' ) ? esc_url_raw( (string) wp_get_attachment_url( $attachment_id ) ) : '',
		);
	}

	private function sanitize_editor_media_item( array $item ): array {
		$attachment_id = absint( $item['attachment_id'] ?? ( $item['id'] ?? 0 ) );
		$occurrence_id = sanitize_text_field( (string) ( $item['occurrence_id'] ?? '' ) );
		$occurrence_fields = array(
			'occurrence_id'   => $occurrence_id,
			'block_client_id' => sanitize_text_field( (string) ( $item['block_client_id'] ?? '' ) ),
			'block_name'      => sanitize_text_field( (string) ( $item['block_name'] ?? '' ) ),
			'occurrence_index' => absint( $item['occurrence_index'] ?? 0 ),
			'context_heading' => sanitize_text_field( (string) ( $item['context_heading'] ?? '' ) ),
			'context_before'  => sanitize_textarea_field( $this->editor_trim_chars( (string) ( $item['context_before'] ?? '' ), 240 ) ),
			'context_after'   => sanitize_textarea_field( $this->editor_trim_chars( (string) ( $item['context_after'] ?? '' ), 240 ) ),
			'context_caption' => sanitize_textarea_field( $this->editor_trim_chars( (string) ( $item['context_caption'] ?? '' ), 220 ) ),
			'context_summary' => sanitize_textarea_field( $this->editor_trim_chars( (string) ( $item['context_summary'] ?? '' ), 360 ) ),
			'target_scope'    => 'post_block_alt',
		);
		if ( $attachment_id > 0 ) {
			$attachment_item = $this->editor_attachment_media_item( $attachment_id, sanitize_key( (string) ( $item['source'] ?? 'content_image' ) ) );
			if ( ! empty( $attachment_item ) ) {
				$attachment_item['url']     = '' !== (string) ( $attachment_item['url'] ?? '' ) ? $attachment_item['url'] : esc_url_raw( (string) ( $item['url'] ?? '' ) );
				$attachment_item['alt']     = '' !== $occurrence_id ? sanitize_text_field( (string) ( $item['alt'] ?? '' ) ) : ( '' !== (string) ( $attachment_item['alt'] ?? '' ) ? $attachment_item['alt'] : sanitize_text_field( (string) ( $item['alt'] ?? '' ) ) );
				$attachment_item['caption'] = '' !== (string) ( $attachment_item['caption'] ?? '' ) ? $attachment_item['caption'] : sanitize_textarea_field( (string) ( $item['caption'] ?? '' ) );
				$attachment_item['missing_alt'] = '' === trim( (string) $attachment_item['alt'] );
				return array_merge( $attachment_item, $occurrence_fields );
			}
		}

		$url = esc_url_raw( (string) ( $item['url'] ?? '' ) );
		if ( '' === $url ) {
			return array();
		}

		$alt = sanitize_text_field( (string) ( $item['alt'] ?? '' ) );
		return array_merge(
			array(
			'source'        => sanitize_key( (string) ( $item['source'] ?? 'content_image' ) ),
			'attachment_id' => 0,
			'title'         => sanitize_text_field( (string) ( $item['title'] ?? '' ) ),
			'caption'       => sanitize_textarea_field( (string) ( $item['caption'] ?? '' ) ),
			'description'   => sanitize_textarea_field( (string) ( $item['description'] ?? '' ) ),
			'mime_type'     => sanitize_text_field( (string) ( $item['mime_type'] ?? '' ) ),
			'alt'           => $alt,
			'missing_alt'   => '' === trim( $alt ),
			'thumbnail_url' => $url,
			'url'           => $url,
			),
			$occurrence_fields
		);
	}

	private function editor_article_image_alt_suggestions( array $context ): array {
		$items = is_array( $context['media_items'] ?? null ) ? $context['media_items'] : array();
		$items = array_values(
			array_filter(
				$items,
				static fn( $item ): bool => is_array( $item ) && '' !== trim( (string) ( $item['occurrence_id'] ?? '' ) )
			)
		);
		if ( empty( $items ) ) {
			return array(
				'artifact_type'          => 'current_article_image_alt_suggestions.v1',
				'status'                 => 'empty',
				'message'                => __( 'No image blocks were found in the current article. Add an image block before previewing contextual ALT.', 'npcink-workflow-toolbox' ),
				'write_posture'          => 'suggestion_only',
				'final_write_path'       => 'core_proposal_required',
				'direct_wordpress_write' => false,
				'items'                  => array(),
			);
		}

		$visual_fallback = $this->editor_article_image_visual_fallback( $items );
		$review_items    = array();
		$visual_fallback_used_count = 0;
		foreach ( array_slice( $items, 0, 12 ) as $index => $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$current_alt    = sanitize_text_field( (string) ( $item['alt'] ?? '' ) );
			$attachment_id  = absint( $item['attachment_id'] ?? 0 );
			$visual_evidence = $visual_fallback[ $attachment_id ] ?? array();
			$suggested_alt  = $this->editor_contextual_image_alt_draft( $item, $context, $visual_evidence );
			$visual_fallback_used = ! empty( $visual_evidence ) && $this->editor_article_image_needs_visual_fallback( $item );
			if ( $visual_fallback_used ) {
				++$visual_fallback_used_count;
			}
			$context_data = array(
				'heading' => sanitize_text_field( (string) ( $item['context_heading'] ?? '' ) ),
				'before'  => sanitize_textarea_field( (string) ( $item['context_before'] ?? '' ) ),
				'after'   => sanitize_textarea_field( (string) ( $item['context_after'] ?? '' ) ),
				'caption' => sanitize_textarea_field( (string) ( $item['context_caption'] ?? '' ) ),
				'visual_fallback' => $visual_fallback_used ? $this->editor_article_image_visual_summary( $visual_evidence ) : '',
			);
			$review_items[] = array(
				'occurrence_id'            => sanitize_text_field( (string) ( $item['occurrence_id'] ?? 'article-image-' . ( $index + 1 ) ) ),
				'occurrence_index'         => absint( $item['occurrence_index'] ?? ( $index + 1 ) ),
				'block_client_id'          => sanitize_text_field( (string) ( $item['block_client_id'] ?? '' ) ),
				'block_name'               => sanitize_text_field( (string) ( $item['block_name'] ?? '' ) ),
				'apply_supported'           => 'core/image' === (string) ( $item['block_name'] ?? '' ),
				'attachment_id'            => $attachment_id,
				'thumbnail_url'            => esc_url_raw( (string) ( $item['thumbnail_url'] ?? ( $item['url'] ?? '' ) ) ),
				'current_alt'              => $current_alt,
				'suggested_alt'            => $suggested_alt,
				'candidate_review_status'  => '' === $current_alt ? 'contextual_draft' : 'existing_alt_review',
				'candidate_basis'          => array_values( array_filter( array( 'article_context', '' !== $context_data['caption'] ? 'figure_caption' : '', '' !== $context_data['heading'] ? 'nearest_heading' : '', '' !== $context_data['before'] || '' !== $context_data['after'] ? 'adjacent_text' : '', $visual_fallback_used ? 'silent_ai_vision_fallback' : '' ) ) ),
				'context'                  => $context_data,
				'context_fingerprint'      => hash( 'sha256', wp_json_encode( $context_data ) ?: '' ),
				'target_scope'             => 'post_block_alt',
				'needs_human_visual_check' => false,
				'visual_fallback_used'      => $visual_fallback_used,
				'visual_fallback_source'    => $visual_fallback_used ? sanitize_key( (string) ( $visual_evidence['source'] ?? 'cloud_or_host_runtime' ) ) : '',
				'visual_confirmation_basis' => $visual_fallback_used ? 'author_present_in_post_editor' : 'article_context_only',
				'decorative_option'        => 'operator_may_choose_empty_alt',
				'write_status'             => 'preview_only_not_submitted',
			);
		}

		return array(
			'artifact_type'          => 'current_article_image_alt_suggestions.v1',
			'contract_version'      => 'current_article_image_alt_context_review.v1',
			'composition_role'      => 'current_article_contextual_alt_review',
			'status'                => 'ready',
			'runtime_owner'         => 'npcink-workflow-toolbox',
			'provider_execution'    => $visual_fallback_used_count > 0 ? 'optional_cloud_visual_fallback' : 'none',
			'source_policy'         => 'current_article_context_with_silent_visual_fallback',
			'target_scope'          => 'post_block_alt',
			'post_id'               => absint( $context['post_id'] ?? 0 ),
			'post_title'            => sanitize_text_field( (string) ( $context['title'] ?? '' ) ),
			'image_occurrence_count' => count( $review_items ),
			'missing_alt_count'     => count( array_filter( $review_items, static fn( array $item ): bool => '' === (string) ( $item['current_alt'] ?? '' ) ) ),
			'visual_fallback_used_count' => $visual_fallback_used_count,
			'visual_confirmation_policy' => 'no_extra_step_author_present_and_native_save',
			'items'                 => $review_items,
			'write_posture'         => 'suggestion_only',
			'editor_apply_path'      => 'local_admin_consent_audit_then_editor_state',
			'final_write_path'      => 'wordpress_editor_save_after_confirmation',
			'proposal_ready'        => false,
			'proposal_created'      => false,
			'direct_wordpress_write' => false,
			'guardrails'            => array(
				'context_decides_image_purpose',
				'visual_facts_are_used_only_when_article_context_is_insufficient',
				'visual_runtime_failure_is_silent_and_non_blocking',
				'no_keyword_stuffing',
				'decorative_images_may_use_empty_alt',
				'no_attachment_global_alt_write',
				'no_post_block_write_in_preview',
			),
		);
	}

	private function editor_contextual_image_alt_draft( array $item, array $context, array $visual_evidence = array() ): string {
		$current_alt = sanitize_text_field( (string) ( $item['alt'] ?? '' ) );
		$caption = sanitize_text_field( (string) ( $item['context_caption'] ?? ( $item['caption'] ?? '' ) ) );
		$heading = sanitize_text_field( (string) ( $item['context_heading'] ?? '' ) );
		$nearby  = sanitize_text_field( (string) ( $item['context_before'] ?? '' ) );
		if ( '' === $nearby ) {
			$nearby = sanitize_text_field( (string) ( $item['context_after'] ?? '' ) );
		}
		$article_title = sanitize_text_field( (string) ( $context['title'] ?? '' ) );
		$candidates    = array();
		if ( '' !== $caption ) {
			$candidates[] = $caption;
		}
		if ( '' !== $heading && '' !== $nearby ) {
			$candidates[] = false === stripos( $nearby, $heading ) ? $heading . '：' . $nearby : $nearby;
		}
		$candidates[] = $nearby;
		$candidates[] = $heading;
		$candidates[] = $current_alt;
		$candidates[] = $this->editor_article_image_visual_summary( $visual_evidence );
		$candidates[] = $article_title;

		foreach ( $candidates as $candidate ) {
			$candidate = trim( preg_replace( '/\s+/u', ' ', (string) $candidate ) ?: '' );
			if ( '' !== $candidate ) {
				return $this->editor_trim_chars( $candidate, 120 );
			}
		}

		return '';
	}

	private function editor_article_image_needs_visual_fallback( array $item ): bool {
		if ( '' !== trim( sanitize_text_field( (string) ( $item['alt'] ?? '' ) ) ) ) {
			return false;
		}

		foreach ( array( 'context_caption', 'context_heading', 'context_before', 'context_after' ) as $key ) {
			if ( '' !== trim( sanitize_textarea_field( (string) ( $item[ $key ] ?? '' ) ) ) ) {
				return false;
			}
		}

		return absint( $item['attachment_id'] ?? 0 ) > 0
			&& '' !== esc_url_raw( (string) ( $item['url'] ?? ( $item['thumbnail_url'] ?? '' ) ) );
	}

	/**
	 * @param array<int,array<string,mixed>> $items Article image occurrences.
	 * @return array<int,array<string,mixed>> Evidence indexed by attachment id.
	 */
	private function editor_article_image_visual_fallback( array $items ): array {
		$request_items = array();
		$requested_ids = array();
		foreach ( array_slice( $items, 0, 12 ) as $item ) {
			if ( ! is_array( $item ) || ! $this->editor_article_image_needs_visual_fallback( $item ) ) {
				continue;
			}
			$attachment_id = absint( $item['attachment_id'] ?? 0 );
			if ( isset( $requested_ids[ $attachment_id ] ) ) {
				continue;
			}
			$requested_ids[ $attachment_id ] = true;
			$request_items[] = array(
				'attachment_id'          => $attachment_id,
				'title'                  => sanitize_text_field( (string) ( $item['title'] ?? '' ) ),
				'filename'               => sanitize_file_name( wp_basename( (string) wp_parse_url( (string) ( $item['url'] ?? '' ), PHP_URL_PATH ) ) ),
				'thumbnail_url'          => esc_url_raw( (string) ( $item['thumbnail_url'] ?? '' ) ),
				'url'                    => esc_url_raw( (string) ( $item['url'] ?? '' ) ),
				'mime_type'              => sanitize_text_field( (string) ( $item['mime_type'] ?? '' ) ),
				'current_alt_status'     => 'missing',
				'current_caption_status' => '' === trim( (string) ( $item['caption'] ?? '' ) ) ? 'missing' : 'present',
				'candidate_quality_flags' => array( 'article_context_insufficient' ),
				'filtered_candidate_notes' => array(),
			);
			if ( count( $request_items ) >= 10 ) {
				break;
			}
		}

		if ( empty( $request_items ) ) {
			return array();
		}

		$evidence = $this->client->request_image_context_evidence(
			array(
				'contract_version'          => 'image_context_evidence_request.v1',
				'artifact_type'             => 'image_context_evidence_request',
				'runtime_owner'             => 'cloud_or_host_runtime',
				'write_posture'             => 'suggestion_only',
				'direct_wordpress_write'    => false,
				'proposal_created'          => false,
				'execution_created'         => false,
				'no_local_model'            => true,
				'no_media_write'            => true,
				'source_policy'             => 'bounded_article_image_urls_for_silent_visual_fallback',
				'expected_response_contract' => 'image_context_evidence.v1',
				'requested_count'           => count( $request_items ),
				'max_items'                 => count( $request_items ),
				'items'                     => $request_items,
				'operator_next_action'      => 'silent_runtime_fallback_no_extra_ui',
			)
		);
		if ( empty( $evidence ) ) {
			return array();
		}

		$indexed = array();
		foreach ( (array) ( $evidence['items'] ?? array() ) as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$attachment_id = absint( $item['attachment_id'] ?? 0 );
			if ( empty( $requested_ids[ $attachment_id ] ) ) {
				continue;
			}
			$summary = $this->editor_article_image_visual_summary( $item );
			if ( '' === $summary ) {
				continue;
			}
			$indexed[ $attachment_id ] = array(
				'source'         => sanitize_key( (string) ( $item['source'] ?? 'cloud_or_host_runtime' ) ),
				'visual_summary' => $summary,
			);
		}

		return $indexed;
	}

	private function editor_article_image_visual_summary( array $evidence ): string {
		$summary = sanitize_text_field( (string) ( $evidence['visual_summary'] ?? ( $evidence['alt_text_basis'] ?? '' ) ) );
		if ( '' === $summary ) {
			$summary = sanitize_text_field( (string) ( $evidence['scene'] ?? '' ) );
		}
		if ( preg_match( '/(?:https?:\/\/|generated\s+by|system\s+prompt|model[_\s-]?id|provider[_\s-]?id|ignore\s+previous)/i', $summary ) ) {
			return '';
		}

		$trimmed = $this->editor_trim_chars( $summary, 120 );
		if ( $trimmed !== $summary && preg_match( '/\s/u', $trimmed ) ) {
			$trimmed = preg_replace( '/\s+\S*$/u', '', $trimmed ) ?: $trimmed;
		}

		return rtrim( $trimmed, " \t\n\r\0\x0B,;:-" );
	}

	private function editor_comment_reply_suggestions( array $context ): array {
		$comment_context = $this->editor_comment_reply_context( $context );
		$comment_text    = (string) ( $comment_context['comment_text'] ?? '' );
		$post_title      = sanitize_text_field( (string) ( $context['title'] ?? '' ) );
		$post_excerpt    = sanitize_textarea_field( (string) ( $context['excerpt'] ?? '' ) );
		$post_summary    = '' !== $post_excerpt ? $post_excerpt : sanitize_textarea_field( wp_trim_words( (string) ( $context['content_text'] ?? '' ), 36, '' ) );

		$base = array(
			'artifact_type'             => 'comment_reply_suggestion.v1',
			'candidate_type'            => 'comment_reply_candidates',
			'write_posture'             => 'suggestion_only',
			'final_write_path'          => 'core_proposal_required',
			'direct_wordpress_write'    => false,
			'comment_publication_policy' => 'operator_review_only_no_comment_publish',
			'comment_status_unchanged'  => true,
			'provider_execution'        => 'toolkit_comment_reply_suggestion',
			'source_policy'             => 'current_article_and_operator_supplied_comment_only',
			'post_context'              => array(
				'post_id' => absint( $context['post_id'] ?? 0 ),
				'title'   => $post_title,
				'excerpt' => $post_summary,
			),
			'comment_context'           => $comment_context,
		);

		if ( '' === $comment_text ) {
			return array_merge(
				$base,
				array(
					'status'  => 'needs_comment_context',
					'message' => __( 'Select or provide a comment before requesting reply suggestions.', 'npcink-workflow-toolbox' ),
					'items'   => array(),
				)
			);
		}

		$result = $this->editor_toolkit_comment_reply_suggestions(
			array(
				'comment_id'     => absint( $comment_context['comment_id'] ?? 0 ),
				'post_id'        => absint( $context['post_id'] ?? 0 ),
				'post_title'     => $post_title,
				'comment_text'   => $comment_text,
				'comment_author' => sanitize_text_field( (string) ( $comment_context['comment_author'] ?? '' ) ),
				'comment_status' => sanitize_key( (string) ( $comment_context['comment_status'] ?? '' ) ),
				'trigger_type'   => 'support_request',
				'always_suggest' => true,
			)
		);
		if ( is_wp_error( $result ) ) {
			return array_merge(
				$base,
				array(
					'status'            => 'toolkit_required',
					'source_ability_id' => 'npcink-abilities-toolkit/build-comment-mention-reply-suggest',
					'toolkit_required'  => true,
					'error_code'        => sanitize_key( $result->get_error_code() ),
					'message'           => sanitize_text_field( $result->get_error_message() ),
					'items'             => array(),
				)
			);
		}

		$data  = is_array( $result['data'] ?? null ) ? $result['data'] : $result;
		$items = $this->editor_comment_reply_items_from_toolkit( $data );

		return array_merge(
			$base,
			array(
				'status'                   => 'ready',
				'source_ability_id'        => 'npcink-abilities-toolkit/build-comment-mention-reply-suggest',
				'toolkit_artifact'         => $data,
				'items'                    => $items,
				'recommendation_candidates' => $this->editor_comment_reply_recommendation_candidates( $items ),
			)
		);
	}

	private function editor_toolkit_comment_reply_suggestions( array $input ) {
		$ability_id = 'npcink-abilities-toolkit/build-comment-mention-reply-suggest';
		if ( ! function_exists( 'npcink_abilities_toolkit_get_registered' ) ) {
			return new WP_Error(
				'npcink_toolbox_comment_reply_toolkit_unavailable',
				__( 'Npcink Abilities Toolkit is required to build comment reply suggestions.', 'npcink-workflow-toolbox' ),
				array( 'status' => 503 )
			);
		}

		$registered = npcink_abilities_toolkit_get_registered();
		$definition = is_array( $registered[ $ability_id ] ?? null ) ? $registered[ $ability_id ] : array();
		$callback   = $definition['execute_callback'] ?? null;
		if ( ! is_callable( $callback ) ) {
			return new WP_Error(
				'npcink_toolbox_comment_reply_toolkit_unavailable',
				__( 'The Toolkit comment reply suggestion ability is not currently callable.', 'npcink-workflow-toolbox' ),
				array( 'status' => 503 )
			);
		}

		$result = call_user_func( $callback, $input );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( ! is_array( $result ) ) {
			return new WP_Error(
				'npcink_toolbox_comment_reply_toolkit_invalid_response',
				__( 'The Toolkit comment reply suggestion ability returned an invalid response.', 'npcink-workflow-toolbox' ),
				array( 'status' => 500 )
			);
		}

		return $result;
	}

	private function editor_comment_reply_items_from_toolkit( array $data ): array {
		$options = is_array( $data['reply_options'] ?? null ) ? $data['reply_options'] : array();
		if ( empty( $options ) && '' !== trim( (string) ( $data['reply_suggestion'] ?? '' ) ) ) {
			$options[] = array(
				'id'         => 'toolkit_reply_suggestion',
				'label'      => __( 'Reply suggestion', 'npcink-workflow-toolbox' ),
				'reply_text' => (string) $data['reply_suggestion'],
				'reason'     => __( 'Generated by the Toolkit comment reply suggestion ability.', 'npcink-workflow-toolbox' ),
			);
		}

		$items = array();
		foreach ( $options as $index => $option ) {
			if ( ! is_array( $option ) ) {
				continue;
			}
			$reply_text = sanitize_textarea_field( (string) ( $option['reply_text'] ?? $option['value'] ?? '' ) );
			if ( '' === $reply_text ) {
				continue;
			}
			$items[] = array(
				'id'            => sanitize_key( (string) ( $option['id'] ?? 'toolkit_reply_' . ( $index + 1 ) ) ),
				'label'         => sanitize_text_field( (string) ( $option['label'] ?? __( 'Reply suggestion', 'npcink-workflow-toolbox' ) ) ),
				'reply_text'    => $reply_text,
				'reason'        => sanitize_textarea_field( (string) ( $option['reason'] ?? '' ) ),
				'status'        => sanitize_key( (string) ( $option['status'] ?? 'review_required' ) ),
				'action_policy' => 'operator_review_only_no_comment_publish',
				'target_field'  => 'comment_reply',
			);
		}

		return $items;
	}

	private function editor_comment_reply_context( array $context ): array {
		$comment_id = absint( $context['comment_id'] ?? 0 );
		$text       = sanitize_textarea_field( (string) ( $context['comment_text'] ?? '' ) );
		$author     = sanitize_text_field( (string) ( $context['comment_author'] ?? '' ) );
		$status     = '';

		if ( $comment_id > 0 && function_exists( 'get_comment' ) ) {
			$comment = get_comment( $comment_id );
			if ( $comment ) {
				$text   = '' !== $text ? $text : sanitize_textarea_field( $this->editor_trim_chars( wp_strip_all_tags( (string) ( $comment->comment_content ?? '' ) ), self::EDITOR_COMMENT_TEXT_MAX_CHARS ) );
				$author = '' !== $author ? $author : sanitize_text_field( (string) ( $comment->comment_author ?? '' ) );
				$status = sanitize_key( (string) ( $comment->comment_approved ?? '' ) );
			}
		}

		if ( '' === $text ) {
			$text = sanitize_textarea_field(
				$this->editor_trim_chars(
					trim(
						implode(
							' ',
							array_filter(
								array(
									(string) ( $context['selected_text_full'] ?? '' ),
									(string) ( $context['selected_block_text_full'] ?? '' ),
									(string) ( $context['user_instruction'] ?? '' ),
								)
							)
						)
					),
					self::EDITOR_COMMENT_TEXT_MAX_CHARS
				)
			);
		}

		return array(
			'comment_id'     => $comment_id,
			'comment_author' => $author,
			'comment_status' => $status,
			'comment_text'   => $text,
			'redaction'      => 'operator_supplied_or_local_comment_text',
		);
	}

	private function editor_comment_reply_recommendation_candidates( array $items ): array {
		$candidates = array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$candidates[] = array(
				'id'            => sanitize_key( (string) ( $item['id'] ?? '' ) ),
				'kind'          => 'comment_reply',
				'target_field'  => 'comment_reply',
				'value'         => sanitize_textarea_field( (string) ( $item['reply_text'] ?? '' ) ),
				'reason'        => sanitize_textarea_field( (string) ( $item['reason'] ?? '' ) ),
				'action_policy' => 'operator_review_only_no_comment_publish',
			);
		}
		return $candidates;
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
			'selected_text' => __( 'Selected text or supplied snippet', 'npcink-workflow-toolbox' ),
			'full_article'  => __( 'Full article context', 'npcink-workflow-toolbox' ),
			'topic_only'    => __( 'Topic or short brief', 'npcink-workflow-toolbox' ),
		);

		$fields = array();
		foreach ( array( 'title', 'excerpt', 'content_text', 'selected_text', 'selected_block_text', 'post_id' ) as $field ) {
			if ( ! empty( $context[ $field ] ) ) {
				$fields[] = $field;
			}
		}

		return array(
			'id'                     => $scope,
			'label'                  => $labels[ $scope ] ?? __( 'Current context', 'npcink-workflow-toolbox' ),
			'source_fields'          => $fields,
			'operator_selected_mode' => sanitize_key( (string) ( $context['context_scope'] ?? 'auto' ) ),
			'detail'                 => __( 'This scope controls ranking context only. Toolbox still returns suggestions and does not write WordPress data.', 'npcink-workflow-toolbox' ),
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
		$preflight_checks   = $this->publish_preflight->local_checks( $context );
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
					'reason'                       => __( 'Existing WordPress term from the local site taxonomy profile. Review against the current draft before applying.', 'npcink-workflow-toolbox' ),
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
				? __( 'Existing media matched weighted title, excerpt, selected text, or draft terms.', 'npcink-workflow-toolbox' )
				: __( 'Recent existing media candidate from the local library. Review visual fit before adoption.', 'npcink-workflow-toolbox' );
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
					'label'                => $match_score > 0 ? ( 0 === $index ? __( 'Existing media candidate', 'npcink-workflow-toolbox' ) : __( 'Media library option', 'npcink-workflow-toolbox' ) ) : __( 'Recent media review item', 'npcink-workflow-toolbox' ),
					'value'                => (string) ( $item['title'] ?? ( $item['url'] ?? '' ) ),
					'reason'               => (string) ( $item['reason'] ?? '' ),
					'confidence'           => $quality_score / 100,
					'target_field'         => 'featured_media',
					'action_policy'        => $match_score > 0 ? 'core_proposal_required' : 'operator_review_only_no_write',
					'quality_status'       => $match_score > 0 && $quality_score >= 70 ? 'good' : 'review',
					'quality_score'        => $quality_score,
					'quality_issues'       => array(
						$match_score > 0
							? __( 'Existing media still requires operator visual review before use.', 'npcink-workflow-toolbox' )
							: __( 'Recent media has no strong text match; treat it as a review-only local reference.', 'npcink-workflow-toolbox' ),
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
					'label'          => sanitize_text_field( (string) ( $item['label'] ?? __( 'Preflight review', 'npcink-workflow-toolbox' ) ) ),
					'value'          => sanitize_text_field( (string) ( $item['detail'] ?? '' ) ),
					'reason'         => __( 'Local pre-publish check found a review item before any Cloud enhancement or Core proposal handoff.', 'npcink-workflow-toolbox' ),
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

	private function editor_cached_audio_generation( array $input, bool $force_refresh = false ) {
		return $this->editor_cached_client_result(
			'audio_generation',
			$input,
			function () use ( $input ) {
				return $this->client->run_audio_generation( $input );
			},
			$force_refresh
		);
	}

	private function editor_cached_cloud_web_search( array $input ) {
		return $this->editor_cached_client_result(
			'cloud_web_search',
			$input,
			function () use ( $input ) {
				return $this->client->test_cloud_web_search( $input );
			}
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

	/**
	 * Validates one public source URL before Cloud research is requested.
	 *
	 * @param string $value Raw URL.
	 * @return string|WP_Error
	 */
	private function editor_source_adaptation_url( string $value ) {
		$value = trim( $value );
		$url   = esc_url_raw( $value, array( 'http', 'https' ) );
		$parts = '' !== $url ? wp_parse_url( $url ) : false;
		if ( ! is_array( $parts ) ) {
			return new WP_Error(
				'npcink_toolbox_source_url_invalid',
				__( 'Enter one valid public article URL.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$scheme = strtolower( (string) ( $parts['scheme'] ?? '' ) );
		$host   = strtolower( trim( (string) ( $parts['host'] ?? '' ), '[]' ) );
		$port   = isset( $parts['port'] ) ? (int) $parts['port'] : ( 'https' === $scheme ? 443 : 80 );
		$has_credentials = '' !== (string) ( $parts['user'] ?? '' ) || '' !== (string) ( $parts['pass'] ?? '' );
		$blocked_host = '' === $host
			|| 'localhost' === $host
			|| str_ends_with( $host, '.localhost' )
			|| str_ends_with( $host, '.local' )
			|| str_ends_with( $host, '.test' )
			|| str_ends_with( $host, '.invalid' )
			|| str_ends_with( $host, '.example' )
			|| str_ends_with( $host, '.internal' )
			|| str_ends_with( $host, '.home.arpa' );
		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			$blocked_host = false === filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
		}

		$safe_port = ( 'http' === $scheme && 80 === $port ) || ( 'https' === $scheme && 443 === $port );
		$wordpress_safe_url = function_exists( 'wp_http_validate_url' ) ? wp_http_validate_url( $url ) : $url;
		$public_host        = $this->editor_source_adaptation_host_is_public( $host );

		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) || $has_credentials || $blocked_host || ! $safe_port || false === $wordpress_safe_url || ! $public_host ) {
			return new WP_Error(
				'npcink_toolbox_source_url_not_public',
				__( 'Use a public HTTP or HTTPS article URL on a standard port without credentials, localhost, or private network addresses.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}

		return is_string( $wordpress_safe_url ) ? $wordpress_safe_url : $url;
	}

	private function editor_source_adaptation_host_is_public( string $host ): bool {
		$host = strtolower( trim( $host, '[]' ) );
		if ( '' === $host ) {
			return false;
		}
		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return $this->editor_source_adaptation_ip_is_public( $host );
		}

		// Cloud Addon owns fetch-time DNS and redirect validation. Resolving a
		// hostname here would duplicate transport policy and break hosts that use
		// a local outbound proxy address for public destinations.
		return true;
	}

	private function editor_source_adaptation_ip_is_public( string $address ): bool {
		$packed = @inet_pton( $address );
		if ( false === $packed ) {
			return false;
		}

		if ( 16 === strlen( $packed ) && substr( $packed, 0, 12 ) === str_repeat( "\0", 10 ) . "\xff\xff" ) {
			$mapped_ipv4 = @inet_ntop( substr( $packed, 12, 4 ) );
			return is_string( $mapped_ipv4 ) && $this->editor_source_adaptation_ip_is_public( $mapped_ipv4 );
		}

		$blocked_cidrs = 4 === strlen( $packed )
			? array(
				'0.0.0.0/8',
				'10.0.0.0/8',
				'100.64.0.0/10',
				'127.0.0.0/8',
				'169.254.0.0/16',
				'172.16.0.0/12',
				'192.0.0.0/24',
				'192.0.2.0/24',
				'192.88.99.0/24',
				'192.168.0.0/16',
				'198.18.0.0/15',
				'198.51.100.0/24',
				'203.0.113.0/24',
				'224.0.0.0/4',
				'240.0.0.0/4',
			)
			: array(
				'::/96',
				'::1/128',
				'64:ff9b::/96',
				'64:ff9b:1::/48',
				'100::/64',
				'2001:2::/48',
				'2001:10::/28',
				'2001:20::/28',
				'2001:db8::/32',
				'2002::/16',
				'fc00::/7',
				'fe80::/10',
				'ff00::/8',
			);

		foreach ( $blocked_cidrs as $blocked_cidr ) {
			if ( $this->editor_source_adaptation_ip_is_in_cidr( $address, $blocked_cidr ) ) {
				return false;
			}
		}

		return true;
	}

	private function editor_source_adaptation_ip_is_in_cidr( string $address, string $cidr ): bool {
		$parts = explode( '/', $cidr, 2 );
		if ( 2 !== count( $parts ) || ! ctype_digit( $parts[1] ) ) {
			return false;
		}

		$address_bytes = @inet_pton( $address );
		$network_bytes = @inet_pton( $parts[0] );
		if ( false === $address_bytes || false === $network_bytes || strlen( $address_bytes ) !== strlen( $network_bytes ) ) {
			return false;
		}

		$prefix_bits = (int) $parts[1];
		$total_bits  = strlen( $address_bytes ) * 8;
		if ( 0 > $prefix_bits || $prefix_bits > $total_bits ) {
			return false;
		}

		$full_bytes = intdiv( $prefix_bits, 8 );
		if ( 0 < $full_bytes && substr( $address_bytes, 0, $full_bytes ) !== substr( $network_bytes, 0, $full_bytes ) ) {
			return false;
		}

		$remaining_bits = $prefix_bits % 8;
		if ( 0 === $remaining_bits ) {
			return true;
		}

		$mask = ( 0xff << ( 8 - $remaining_bits ) ) & 0xff;
		return ( ord( $address_bytes[ $full_bytes ] ) & $mask ) === ( ord( $network_bytes[ $full_bytes ] ) & $mask );
	}

	private function editor_source_adaptation_url_matches( string $requested_url, string $resolved_url ): bool {
		$requested = wp_parse_url( $requested_url );
		$resolved  = wp_parse_url( $resolved_url );
		if ( ! is_array( $requested ) || ! is_array( $resolved ) ) {
			return false;
		}

		$requested_host = strtolower( trim( (string) ( $requested['host'] ?? '' ), '[]' ) );
		$resolved_host  = strtolower( trim( (string) ( $resolved['host'] ?? '' ), '[]' ) );
		if ( '' === $requested_host || $requested_host !== $resolved_host ) {
			return false;
		}

		$normalize_path = static function ( array $parts ): string {
			$path = rawurldecode( (string) ( $parts['path'] ?? '/' ) );
			$path = preg_replace( '#/+#', '/', '/' . ltrim( $path, '/' ) );
			return '/' === $path ? '/' : rtrim( $path, '/' );
		};

		return $normalize_path( $requested ) === $normalize_path( $resolved );
	}

	private function editor_source_article_body_text( string $source_title, string $source_text ): string {
		$text = trim( $source_text );
		$title = trim( $source_title );
		if ( '' === $text || '' === $title ) {
			return '';
		}
		$title_candidates = array( $title );
		$short_title      = preg_split( '/\s+[–—|]\s+/u', $title, 2 );
		if ( is_array( $short_title ) && '' !== trim( (string) ( $short_title[0] ?? '' ) ) ) {
			$title_candidates[] = trim( (string) $short_title[0] );
		}

		foreach ( array_unique( $title_candidates ) as $title_candidate ) {
			$pattern = '/^#{1,3}\s+' . preg_quote( $title_candidate, '/' ) . '\s*$/miu';
			if ( 1 !== preg_match( $pattern, $text, $match, PREG_OFFSET_CAPTURE ) ) {
				continue;
			}
			$heading = (string) ( $match[0][0] ?? '' );
			$offset  = (int) ( $match[0][1] ?? 0 );

			return trim( substr( $text, $offset + strlen( $heading ) ) );
		}

		return '';
	}

	private function editor_source_body_is_draftable( string $source_text ): bool {
		$text = trim( wp_strip_all_tags( $source_text ) );
		if ( '' === $text ) {
			return false;
		}
		$char_count = function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
		$prose = preg_replace( '#https?://\S+#iu', '', $text );
		$sentence_count = preg_match_all( '/[.!?。！？]/u', is_string( $prose ) ? $prose : '' );

		return $char_count >= 600 && is_int( $sentence_count ) && $sentence_count >= 3;
	}

	private function editor_writing_pack_request_brief( $raw ): array {
		if ( is_string( $raw ) && '' !== trim( $raw ) ) {
			$decoded = json_decode( $raw, true );
			$raw = is_array( $decoded ) ? $decoded : array();
		}
		$raw = is_array( $raw ) ? $raw : array();
		$result = array();
		foreach ( array( 'audience', 'article_goal', 'reader_problem', 'unique_angle', 'reader_promise', 'content_type', 'operator_instruction' ) as $field ) {
			$result[ $field ] = wp_trim_words( sanitize_textarea_field( wp_strip_all_tags( (string) ( $raw[ $field ] ?? '' ) ) ), 120, '' );
		}
		foreach ( array( 'focus_points', 'title_directions', 'outline' ) as $field ) {
			$value = $raw[ $field ] ?? array();
			if ( is_string( $value ) ) {
				$value = preg_split( '/\r\n|\r|\n/', $value );
			}
			$result[ $field ] = $this->editor_writing_pack_list( is_array( $value ) ? $value : array(), 12 );
		}

		return $result;
	}

	private function editor_draft_review_feedback_request( $raw ): array {
		if ( is_string( $raw ) && '' !== trim( $raw ) ) {
			$decoded = json_decode( $raw, true );
			$raw = is_array( $decoded ) ? $decoded : array();
		}
		$raw = is_array( $raw ) ? $raw : array();
		$status = sanitize_key( (string) ( $raw['status'] ?? '' ) );
		if ( ! in_array( $status, array( 'usable', 'usable_after_changes', 'not_usable' ), true ) ) {
			$status = '';
		}
		$allowed_issues = array( 'fact_accuracy', 'site_tone', 'structure', 'source_similarity', 'rights_attribution' );
		$issue_codes = array();
		foreach ( is_array( $raw['issue_codes'] ?? null ) ? $raw['issue_codes'] : array() as $issue_code ) {
			$issue_code = sanitize_key( (string) $issue_code );
			if ( in_array( $issue_code, $allowed_issues, true ) && ! in_array( $issue_code, $issue_codes, true ) ) {
				$issue_codes[] = $issue_code;
			}
		}
		$notes = wp_trim_words( sanitize_textarea_field( wp_strip_all_tags( (string) ( $raw['notes'] ?? '' ) ) ), 120, '' );
		if ( '' === $status ) {
			return array();
		}

		return array(
			'artifact_type'              => 'article_draft_review_feedback.v1',
			'contract_version'           => 'article_draft_review_feedback.v1',
			'status'                     => $status,
			'issue_codes'                => $issue_codes,
			'notes'                      => $notes,
			'authorization_scope'        => 'single_draft_regeneration_request',
			'durable_review_state'       => false,
			'direct_wordpress_write'     => false,
		);
	}

	private function editor_writing_pack_query( array $context ): string {
		$source_url = trim( (string) ( $context['source_url'] ?? '' ) );
		if ( '' !== $source_url ) {
			return $source_url;
		}
		$reviewed_pack = $context['reviewed_writing_pack'] ?? array();
		if ( is_string( $reviewed_pack ) ) {
			$decoded = json_decode( $reviewed_pack, true );
			$reviewed_pack = is_array( $decoded ) ? $decoded : array();
		}
		if ( is_array( $reviewed_pack ) ) {
			$reviewed_ref = sanitize_text_field( (string) ( $reviewed_pack['content_fingerprint'] ?? $reviewed_pack['writing_pack_id'] ?? '' ) );
			if ( '' !== $reviewed_ref ) {
				return $reviewed_ref;
			}
		}
		$brief = is_array( $context['editorial_brief'] ?? null ) ? $context['editorial_brief'] : array();
		$parts = array(
			(string) ( $brief['audience'] ?? '' ),
			(string) ( $brief['article_goal'] ?? '' ),
			(string) ( $brief['reader_problem'] ?? '' ),
			implode( ' ', is_array( $brief['focus_points'] ?? null ) ? $brief['focus_points'] : array() ),
			(string) ( $brief['operator_instruction'] ?? '' ),
		);

		return wp_trim_words( trim( implode( ' ', array_filter( $parts ) ) ), 120, '' );
	}

	private function editor_manual_writing_pack_response( array $context, array $result ) {
		$brief = is_array( $context['editorial_brief'] ?? null ) ? $context['editorial_brief'] : array();
		$missing = array();
		foreach ( array( 'audience', 'article_goal' ) as $field ) {
			if ( '' === trim( (string) ( $brief[ $field ] ?? '' ) ) ) {
				$missing[] = 'editorial_brief.' . $field;
			}
		}
		if ( empty( $brief['focus_points'] ) ) {
			$missing[] = 'editorial_brief.focus_points';
		}
		if ( ! empty( $missing ) ) {
			return new WP_Error(
				'npcink_toolbox_writing_pack_manual_brief_incomplete',
				__( 'Manual writing packs require an audience, article goal, and at least one focus point.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400, 'missing_required_fields' => $missing )
			);
		}

		$query = $this->editor_writing_pack_query( $context );
		$knowledge_raw = $this->editor_cached_site_knowledge(
			array(
				'query'           => $query,
				'intent'          => 'writing_support_plan',
				'result_granularity' => 'document',
				'current_post_id' => absint( $context['post_id'] ?? 0 ),
				'max_results'     => 6,
			)
		);
		$source = array(
			'status'        => 'not_required',
			'url_match'     => 'not_applicable',
			'content_trust' => 'operator_supplied_brief',
			'coverage'      => array( 'mode' => 'manual_brief' ),
		);
		$review = $this->editor_hosted_source_adaptation_review(
			$context,
			array( 'reader_status' => 'not_applicable' ),
			is_wp_error( $knowledge_raw ) ? array() : $knowledge_raw
		);
		$result['sections']['source_article'] = $source;
		$result['sections']['source_site_context'] = $this->editor_support_section( $knowledge_raw );
		$result['sections']['source_adaptation_review'] = $review;
		$result['sections']['article_writing_pack'] = $this->editor_article_writing_pack(
			$context,
			$source,
			$result['sections']['source_site_context'],
			$review,
			''
		);
		$result['artifact_type'] = 'article_writing_pack.v1';
		$result['contract_version'] = 'article_writing_pack.v1';
		$result['primary_artifact_type'] = 'article_writing_pack.v1';
		$result['input_mode'] = 'manual_brief';
		$result['composition_role'] = 'operator_brief_article_planning';
		$result['final_write_path'] = 'operator_review_only_no_insert';
		$result['handoff']['final_writes'] = 'operator_review_only_no_insert';
		$result['handoff']['style_runtime'] = 'cloud_site_knowledge';
		$result['handoff']['required_input_contract'] = 'article_writing_pack.v1';
		$result['handoff']['article_generation_status'] = 'awaiting_operator_confirmation';
		$result['handoff']['body_generation'] = false;
		$result['handoff']['body_replacement'] = false;
		$result['recommendation_set'] = $this->editor_recommendation_set( $context, 'source_adaptation_review', $result['sections'] );
		$result['content_fingerprint'] = $result['recommendation_set']['content_fingerprint'];

		return rest_ensure_response( $result );
	}

	private function editor_article_draft_response( array $context, array $result ) {
		$review = $this->editor_confirmed_writing_pack_review( $context );
		if ( is_wp_error( $review ) ) {
			return $review;
		}
		$pack                  = $review['reviewed_writing_pack'];
		$draft_review_feedback = is_array( $context['draft_review_feedback'] ?? null ) ? $context['draft_review_feedback'] : array();
		$public_review = $review;
		unset( $public_review['reviewed_writing_pack'] );
		$raw = $this->editor_support_section(
			$this->editor_cached_hosted_ai_content_support(
				array(
					'intent'              => 'article_draft_from_writing_pack',
					'post_id'             => absint( $context['post_id'] ?? 0 ),
					'input_mode'          => (string) ( $pack['input_mode'] ?? 'manual_brief' ),
					'writing_pack'        => $pack,
					'writing_pack_review' => $public_review,
					'draft_review_feedback' => $draft_review_feedback,
					'generation_variant'  => (string) ( $context['generation_variant'] ?? '' ),
				),
				! empty( $context['force_regenerate'] )
			)
		);
		$draft = $this->editor_article_draft_preview( $pack, $public_review, $raw );
		unset( $context['reviewed_writing_pack'], $context['writing_pack_confirmation'], $context['draft_review_feedback'] );
		$result['post_context'] = $context;
		$result['artifact_type'] = 'article_draft_preview.v1';
		$result['contract_version'] = 'article_draft_preview.v1';
		$result['primary_artifact_type'] = 'article_draft_preview.v1';
		$result['input_mode'] = (string) ( $pack['input_mode'] ?? 'manual_brief' );
		$result['composition_role'] = 'confirmed_writing_pack_draft_preview';
		$result['sections']['article_writing_pack'] = $pack;
		$result['sections']['writing_pack_review'] = $public_review;
		$result['sections']['article_draft_preview'] = $draft;
		if ( ! empty( $draft_review_feedback ) ) {
			$result['sections']['draft_review_feedback'] = $draft_review_feedback;
		}
		$result['write_posture'] = 'suggestion_only';
		$result['final_write_path'] = 'operator_review_only_no_insert';
		$result['direct_wordpress_write'] = false;
		$result['handoff']['final_writes'] = 'operator_review_only_no_insert';
		$result['handoff']['body_generation'] = true;
		$result['handoff']['body_insertion'] = false;
		$result['handoff']['body_replacement'] = false;
		$result['handoff']['article_generation_status'] = 'draft_preview_generated_from_confirmed_pack';
		$result['content_fingerprint'] = (string) $review['review_fingerprint'];

		return rest_ensure_response( $result );
	}

	private function editor_confirmed_writing_pack_review( array $context ) {
		$raw_pack = $context['reviewed_writing_pack'] ?? array();
		$raw_confirmation = $context['writing_pack_confirmation'] ?? array();
		if ( is_string( $raw_pack ) ) {
			$decoded = json_decode( $raw_pack, true );
			$raw_pack = is_array( $decoded ) ? $decoded : array();
		}
		if ( is_string( $raw_confirmation ) ) {
			$decoded = json_decode( $raw_confirmation, true );
			$raw_confirmation = is_array( $decoded ) ? $decoded : array();
		}
		$pack = $this->editor_writing_pack_payload_value( is_array( $raw_pack ) ? $raw_pack : array() );
		$confirmation = $this->editor_writing_pack_payload_value( is_array( $raw_confirmation ) ? $raw_confirmation : array() );
		if ( ! is_array( $pack ) || 'article_writing_pack.v1' !== (string) ( $pack['artifact_type'] ?? '' ) ) {
			return new WP_Error(
				'npcink_toolbox_writing_pack_review_invalid_artifact',
				__( 'A reviewed article_writing_pack.v1 artifact is required before draft generation.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}
		$input_mode = sanitize_key( (string) ( $pack['input_mode'] ?? '' ) );
		if ( ! in_array( $input_mode, array( 'url_reference', 'manual_brief', 'mixed' ), true ) ) {
			return new WP_Error(
				'npcink_toolbox_writing_pack_review_invalid_input_mode',
				__( 'The reviewed writing pack has an unsupported input mode.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}
		if ( $input_mode !== sanitize_key( (string) ( $context['input_mode'] ?? '' ) ) ) {
			return new WP_Error(
				'npcink_toolbox_writing_pack_review_input_mode_mismatch',
				__( 'The reviewed writing pack input mode does not match the draft request.', 'npcink-workflow-toolbox' ),
				array( 'status' => 409 )
			);
		}
		$base_fingerprint = sanitize_text_field( (string) ( $pack['content_fingerprint'] ?? '' ) );
		$confirmed_fingerprint = sanitize_text_field( (string) ( $confirmation['base_content_fingerprint'] ?? '' ) );
		if ( empty( $confirmation['confirmed'] ) || 'confirmed_by_operator' !== sanitize_key( (string) ( $confirmation['status'] ?? '' ) ) ) {
			return new WP_Error(
				'npcink_toolbox_writing_pack_review_confirmation_required',
				__( 'Review and confirm the writing pack before generating a draft.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}
		if ( '' === $base_fingerprint || ! hash_equals( $base_fingerprint, $confirmed_fingerprint ) ) {
			return new WP_Error(
				'npcink_toolbox_writing_pack_review_fingerprint_mismatch',
				__( 'The writing pack changed after confirmation. Review and confirm the current version again.', 'npcink-workflow-toolbox' ),
				array( 'status' => 409 )
			);
		}
		$admission = is_array( $pack['generation_admission'] ?? null ) ? $pack['generation_admission'] : array();
		if ( 'blocked' === sanitize_key( (string) ( $admission['status'] ?? '' ) ) ) {
			return new WP_Error(
				'npcink_toolbox_writing_pack_generation_blocked',
				__( 'This writing pack cannot generate a draft because the source body or required writing evidence is insufficient.', 'npcink-workflow-toolbox' ),
				array(
					'status'           => 400,
					'blocking_reasons' => $this->editor_writing_pack_list( $admission['blocking_reasons'] ?? array(), 12 ),
				)
			);
		}
		$missing = $this->editor_writing_pack_required_fields( $pack );
		if ( ! empty( $missing ) ) {
			return new WP_Error(
				'npcink_toolbox_writing_pack_review_incomplete',
				__( 'The reviewed writing pack is missing fields required for draft generation.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400, 'missing_required_fields' => $missing )
			);
		}
		$fingerprint_basis = $pack;
		unset( $fingerprint_basis['generated_at'], $fingerprint_basis['generation_admission'] );
		$review_fingerprint = 'sha256:' . hash( 'sha256', (string) wp_json_encode( $fingerprint_basis ) );

		return array(
			'artifact_type'              => 'article_writing_pack_review.v1',
			'contract_version'           => 'article_writing_pack_review.v1',
			'status'                     => 'confirmed_by_operator',
			'base_content_fingerprint'   => $base_fingerprint,
			'review_fingerprint'         => $review_fingerprint,
			'reviewed_writing_pack'      => $pack,
			'article_generation_allowed' => true,
			'authorization_scope'        => 'single_synchronous_draft_preview_request',
			'durable_approval_state'     => false,
			'direct_wordpress_write'     => false,
		);
	}

	private function editor_writing_pack_required_fields( array $pack ): array {
		$required = array(
			'inputs.editorial_brief.audience' => $pack['inputs']['editorial_brief']['audience']['value'] ?? '',
			'inputs.editorial_brief.article_goal' => $pack['inputs']['editorial_brief']['article_goal']['value'] ?? '',
			'inputs.editorial_brief.focus_points' => $pack['inputs']['editorial_brief']['focus_points']['value'] ?? array(),
			'site_adaptation.unique_angle' => $pack['site_adaptation']['unique_angle']['value'] ?? '',
			'writing_plan.outline' => $pack['writing_plan']['outline'] ?? array(),
		);
		if ( in_array( (string) ( $pack['input_mode'] ?? '' ), array( 'url_reference', 'mixed' ), true ) ) {
			$required['research_basis.fact_ledger'] = $pack['research_basis']['fact_ledger'] ?? array();
			$required['inputs.source_materials'] = $pack['inputs']['source_materials'] ?? array();
		}
		$missing = array();
		foreach ( $required as $path => $value ) {
			if ( ! $this->editor_writing_pack_has_value( $value ) ) {
				$missing[] = $path;
			}
		}

		return $missing;
	}

	private function editor_writing_pack_has_value( $value ): bool {
		if ( is_scalar( $value ) ) {
			return '' !== trim( (string) $value );
		}
		if ( ! is_array( $value ) ) {
			return false;
		}
		foreach ( $value as $item ) {
			if ( $this->editor_writing_pack_has_value( $item ) ) {
				return true;
			}
		}

		return false;
	}

	private function editor_article_draft_preview( array $pack, array $review, array $raw ): array {
		$output = $this->editor_writing_pack_hosted_output( $raw );
		$sections = array();
		foreach ( array_slice( is_array( $output['sections'] ?? null ) ? $output['sections'] : array(), 0, 20 ) as $index => $section ) {
			$section = is_array( $section ) ? $section : array( 'body' => $section );
			$heading = sanitize_text_field( (string) ( $section['heading'] ?? $section['title'] ?? sprintf( __( 'Section %d', 'npcink-workflow-toolbox' ), $index + 1 ) ) );
			$body = wp_trim_words( sanitize_textarea_field( wp_strip_all_tags( (string) ( $section['body'] ?? $section['content'] ?? '' ) ) ), 700, '' );
			if ( '' !== $heading || '' !== $body ) {
				$sections[] = array(
					'heading'              => $heading,
					'body'                 => $body,
					'supporting_fact_refs' => $this->editor_writing_pack_list( $section['supporting_fact_refs'] ?? array(), 12 ),
				);
			}
		}
		$status = ! empty( $sections ) ? 'ready' : 'blocked';

		return array(
			'artifact_type'              => 'article_draft_preview.v1',
			'contract_version'           => 'article_draft_preview.v1',
			'status'                     => $status,
			'title'                      => sanitize_text_field( (string) ( $output['title'] ?? '' ) ),
			'excerpt'                    => wp_trim_words( sanitize_textarea_field( wp_strip_all_tags( (string) ( $output['excerpt'] ?? '' ) ) ), 120, '' ),
			'sections'                   => $sections,
			'verification_notes'         => $this->editor_writing_pack_list( $output['verification_notes'] ?? array(), 20 ),
			'source_attribution_notes'   => $this->editor_writing_pack_list( $output['source_attribution_notes'] ?? array(), 12 ),
			'writing_pack_id'            => sanitize_text_field( (string) ( $pack['writing_pack_id'] ?? '' ) ),
			'writing_pack_review_fingerprint' => sanitize_text_field( (string) ( $review['review_fingerprint'] ?? '' ) ),
			'write_posture'              => 'suggestion_only',
			'operator_review_required'   => true,
			'direct_wordpress_write'     => false,
			'body_insertion'             => false,
			'body_replacement'           => false,
			'message'                    => 'ready' === $status ? '' : __( 'The hosted runtime did not return a structured draft preview.', 'npcink-workflow-toolbox' ),
		);
	}

	private function editor_hosted_source_adaptation_review( array $context, array $source, array $knowledge ): array {
		$section = $this->editor_support_section(
			$this->editor_cached_hosted_ai_content_support(
				array(
					'intent'                  => 'source_adaptation_review',
					'input_mode'              => (string) ( $context['input_mode'] ?? 'url_reference' ),
					'post_id'                 => absint( $context['post_id'] ?? 0 ),
					'title'                   => (string) ( $source['title'] ?? '' ),
					'content'                 => (string) ( $source['content'] ?? '' ),
					'user_instruction'        => (string) ( $context['user_instruction'] ?? '' ),
					'generation_variant'      => (string) ( $context['generation_variant'] ?? '' ),
					'source_url'              => (string) ( $source['url'] ?? '' ),
					'source_reader_status'    => (string) ( $source['reader_status'] ?? '' ),
					'editorial_brief'         => is_array( $context['editorial_brief'] ?? null ) ? $context['editorial_brief'] : array(),
					'related_content_context' => $knowledge,
				),
				! empty( $context['force_regenerate'] )
			)
		);
		$section['provider_execution']     = 'hosted_ai_source_adaptation_review';
		$section['provider_intent']        = 'source_adaptation_review';
		$section['artifact_type']          = 'source_adaptation_review.v1';
		$section['write_posture']          = 'suggestion_only';
		$section['direct_wordpress_write'] = false;
		$section['source_url']             = esc_url_raw( (string) ( $source['url'] ?? '' ) );
		$section['source_reader_status']   = sanitize_key( (string) ( $source['reader_status'] ?? '' ) );
		$section['body_generation']        = false;
		$section['body_replacement']       = false;

		return $section;
	}

	private function editor_article_writing_pack( array $context, array $source, array $knowledge, array $review, string $source_text ): array {
		$output = $this->editor_writing_pack_hosted_output( $review );
		$editorial = is_array( $output['editorial_direction'] ?? null ) ? $output['editorial_direction'] : $output;
		$research  = is_array( $output['research_basis'] ?? null ) ? $output['research_basis'] : $output;
		$adaptation = is_array( $output['site_adaptation'] ?? null ) ? $output['site_adaptation'] : $output;
		$plan      = is_array( $output['writing_plan'] ?? null ) ? $output['writing_plan'] : $output;
		$risk      = is_array( $output['risk_review'] ?? null ) ? $output['risk_review'] : $output;
		$input_mode = sanitize_key( (string) ( $context['input_mode'] ?? 'url_reference' ) );
		$manual_only = 'manual_brief' === $input_mode;
		$source_ready = $manual_only || ( 'ready' === sanitize_key( (string) ( $source['status'] ?? '' ) )
			&& 'matched' === sanitize_key( (string) ( $source['url_match'] ?? '' ) )
			&& $this->editor_source_body_is_draftable( $source_text ) );
		$review_ready = ! empty( $output ) && ! in_array( sanitize_key( (string) ( $review['status'] ?? '' ) ), array( 'blocked', 'error', 'failed' ), true );
		$blocking_reasons = array();
		if ( ! $source_ready ) {
			$blocking_reasons[] = 'source_body_evidence_insufficient';
		}
		if ( ! $review_ready ) {
			$blocking_reasons[] = 'writing_pack_output_not_ready';
		}

		$brief = is_array( $context['editorial_brief'] ?? null ) ? $context['editorial_brief'] : array();
		$operator_instruction = trim( sanitize_textarea_field( (string) ( $brief['operator_instruction'] ?? $context['user_instruction'] ?? '' ) ) );
		$source_materials = $manual_only ? array() : array(
			array(
				'material_type'       => 'public_url',
				'requested_url'       => esc_url_raw( (string) ( $source['requested_url'] ?? $context['source_url'] ?? '' ) ),
				'resolved_url'        => esc_url_raw( (string) ( $source['resolved_url'] ?? '' ) ),
				'title'               => sanitize_text_field( (string) ( $source['title'] ?? '' ) ),
				'content_hash'        => sanitize_text_field( (string) ( $source['content_hash'] ?? '' ) ),
				'coverage'            => $this->editor_writing_pack_payload_value( $source['coverage'] ?? array() ),
				'content_trust'       => sanitize_key( (string) ( $source['content_trust'] ?? 'untrusted_external_source' ) ),
				'url_match'           => sanitize_key( (string) ( $source['url_match'] ?? '' ) ),
				'continuation_requested' => true,
				'operator_confirmed'     => false,
			),
		);
		$pack = array(
			'artifact_type'          => 'article_writing_pack.v1',
			'contract_version'       => 'article_writing_pack.v1',
			'composition_role'       => 'source_grounded_article_planning',
			'input_mode'             => $input_mode,
			'inputs'                 => array(
				'source_materials'   => $source_materials,
				'editorial_brief'    => array(
					'audience'             => $this->editor_writing_pack_resolved_field( $brief['audience'] ?? '', $editorial['audience'] ?? $editorial['inferred_audience'] ?? '' ),
					'article_goal'          => $this->editor_writing_pack_resolved_field( $brief['article_goal'] ?? '', $editorial['article_goal'] ?? '' ),
					'reader_problem'        => $this->editor_writing_pack_resolved_field( $brief['reader_problem'] ?? '', $editorial['reader_problem'] ?? '' ),
					'focus_points'          => $this->editor_writing_pack_resolved_field( $brief['focus_points'] ?? array(), $this->editor_writing_pack_list( $editorial['focus_points'] ?? $output['adaptation_directions'] ?? array(), 8 ) ),
					'operator_instruction'  => array(
						'value'              => $operator_instruction,
						'source'             => '' !== $operator_instruction ? 'operator' : 'not_supplied',
						'operator_confirmed' => '' !== $operator_instruction,
					),
				),
				'site_context_policy' => array(
					'role'                   => 'overlap_tone_terminology_and_internal_reference_only',
					'factual_source_for_external_claims' => false,
					'index_lifecycle_owner'  => 'npcink_ai_cloud',
				),
			),
			'research_basis'        => array(
				'source_summary'     => $manual_only ? array() : $this->editor_writing_pack_list( $research['source_summary'] ?? $research['source_summary_zh'] ?? $output['source_summary_zh'] ?? array(), 6 ),
				'fact_ledger'        => $manual_only ? array() : $this->editor_writing_pack_list( $research['fact_ledger'] ?? array(), 16 ),
				'source_coverage'    => $this->editor_writing_pack_payload_value( $source['coverage'] ?? array() ),
				'verification_items' => $this->editor_writing_pack_list( $research['verification_items'] ?? $output['facts_to_verify'] ?? array(), 12 ),
			),
			'site_adaptation'       => array(
				'related_articles'   => $this->editor_writing_pack_related_articles( $knowledge ),
				'overlap_map'        => $this->editor_writing_pack_list( $adaptation['overlap_map'] ?? array(), 10 ),
				'site_style_signals' => $this->editor_writing_pack_list( $adaptation['site_style_signals'] ?? $output['site_style_signals'] ?? array(), 8 ),
				'unique_angle'       => $this->editor_writing_pack_resolved_field( $brief['unique_angle'] ?? '', $adaptation['unique_angle'] ?? $output['unique_angle'] ?? '' ),
			),
			'writing_plan'          => array(
				'title_directions' => $this->editor_writing_pack_list( ! empty( $brief['title_directions'] ) ? $brief['title_directions'] : ( $plan['title_directions'] ?? array() ), 6 ),
				'reader_promise'   => $this->editor_writing_pack_resolved_field( $brief['reader_promise'] ?? '', $plan['reader_promise'] ?? '' ),
				'content_type'     => $this->editor_writing_pack_resolved_field( $brief['content_type'] ?? '', $plan['content_type'] ?? '' ),
				'outline'          => $this->editor_writing_pack_list( ! empty( $brief['outline'] ) ? $brief['outline'] : ( $plan['outline'] ?? $output['suggested_outline'] ?? array() ), 12 ),
				'cta_direction'    => $this->editor_writing_pack_inferred_field( $plan['cta_direction'] ?? '' ),
			),
			'risk_review'           => array(
				'fact_risks'       => $this->editor_writing_pack_list( $risk['fact_risks'] ?? $output['facts_to_verify'] ?? array(), 12 ),
				'rights_risks'     => $this->editor_writing_pack_list( $risk['rights_risks'] ?? $output['copyright_and_attribution'] ?? array(), 12 ),
				'similarity_risks' => $this->editor_writing_pack_list( $risk['similarity_risks'] ?? array(), 10 ),
			),
			'generation_admission' => array(
				'status'                     => empty( $blocking_reasons ) ? 'needs_review' : 'blocked',
				'blocking_reasons'           => $blocking_reasons,
				'review_requirements'        => array(
					'operator_editorial_review',
					'fact_traceability_review',
					'source_rights_confirmation',
					'similarity_and_overlap_review',
				),
				'article_generation_allowed' => false,
				'next_gate'                  => 'review_and_confirm_article_writing_pack_before_future_draft_generation',
			),
			'provenance'            => array(
				'source_facts'        => $manual_only ? 'operator_brief_no_external_fact_source' : 'cloud_exact_source_extraction',
				'site_context'        => 'cloud_site_knowledge',
				'editorial_direction' => 'hosted_ai_inference_not_operator_confirmed',
				'operator_input'      => '' !== $operator_instruction ? 'optional_instruction_only' : 'none',
			),
			'write_posture'          => 'suggestion_only',
			'final_write_path'       => 'operator_review_only_no_insert',
			'direct_wordpress_write' => false,
			'body_generation'        => false,
			'body_replacement'       => false,
		);
		$missing_required_fields = array();
		if ( empty( $pack['inputs']['editorial_brief']['audience']['value'] ) ) {
			$missing_required_fields[] = 'editorial_brief.audience';
		}
		if ( empty( $pack['inputs']['editorial_brief']['article_goal']['value'] ) ) {
			$missing_required_fields[] = 'editorial_brief.article_goal';
		}
		if ( empty( $pack['inputs']['editorial_brief']['focus_points']['value'] ) ) {
			$missing_required_fields[] = 'editorial_brief.focus_points';
		}
		if ( ! $manual_only && empty( $pack['research_basis']['fact_ledger'] ) ) {
			$missing_required_fields[] = 'research_basis.fact_ledger';
		}
		if ( empty( $pack['site_adaptation']['unique_angle']['value'] ) ) {
			$missing_required_fields[] = 'site_adaptation.unique_angle';
		}
		if ( empty( $pack['writing_plan']['outline'] ) ) {
			$missing_required_fields[] = 'writing_plan.outline';
		}
		if ( ! empty( $missing_required_fields ) ) {
			$pack['generation_admission']['status'] = 'blocked';
			$pack['generation_admission']['blocking_reasons'][] = 'writing_pack_required_fields_missing';
			$pack['generation_admission']['missing_required_fields'] = $missing_required_fields;
		}
		$fingerprint_basis = $pack;
		$fingerprint = 'sha256:' . hash( 'sha256', (string) wp_json_encode( $fingerprint_basis ) );
		$pack['writing_pack_id']     = 'awp_' . substr( hash( 'sha256', $fingerprint ), 0, 20 );
		$pack['content_fingerprint'] = $fingerprint;
		$pack['generated_at']        = gmdate( 'c' );

		return $pack;
	}

	private function editor_writing_pack_hosted_output( array $review ): array {
		if ( is_array( $review['output_json'] ?? null ) ) {
			return $review['output_json'];
		}
		$result = is_array( $review['result'] ?? null ) ? $review['result'] : array();
		if ( is_array( $result['output_json'] ?? null ) ) {
			return $result['output_json'];
		}

		return array();
	}

	private function editor_writing_pack_inferred_field( $value ): array {
		if ( is_array( $value ) && array_key_exists( 'value', $value ) ) {
			$value = $value['value'];
		}
		return array(
			'value'              => $this->editor_writing_pack_payload_value( $value ),
			'source'             => 'ai_inferred_from_source_and_site_context',
			'operator_confirmed' => false,
		);
	}

	private function editor_writing_pack_resolved_field( $operator_value, $inferred_value ): array {
		$has_operator_value = is_array( $operator_value ) ? ! empty( $operator_value ) : '' !== trim( (string) $operator_value );
		if ( $has_operator_value ) {
			return array(
				'value'              => $this->editor_writing_pack_payload_value( $operator_value ),
				'source'             => 'operator_supplied_brief',
				'operator_confirmed' => true,
			);
		}

		return $this->editor_writing_pack_inferred_field( $inferred_value );
	}

	private function editor_writing_pack_list( $value, int $limit ): array {
		$is_list = is_array( $value ) && ( array() === $value || array_keys( $value ) === range( 0, count( $value ) - 1 ) );
		$items = $is_list ? $value : ( null === $value || '' === $value ? array() : array( $value ) );
		$result = array();
		foreach ( array_slice( $items, 0, max( 1, min( 20, $limit ) ) ) as $item ) {
			$sanitized = $this->editor_writing_pack_payload_value( $item );
			if ( null !== $sanitized && '' !== $sanitized && array() !== $sanitized ) {
				$result[] = $sanitized;
			}
		}

		return $result;
	}

	private function editor_writing_pack_payload_value( $value, int $depth = 0 ) {
		if ( $depth > 6 || null === $value ) {
			return null;
		}
		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
			return $value;
		}
		if ( is_string( $value ) ) {
			return wp_trim_words( sanitize_textarea_field( wp_strip_all_tags( $value ) ), 180, '' );
		}
		if ( ! is_array( $value ) ) {
			return null;
		}

		$result = array();
		foreach ( array_slice( $value, 0, 20, true ) as $key => $item ) {
			$clean_key = is_int( $key ) ? $key : sanitize_key( (string) $key );
			if ( '' === (string) $clean_key && ! is_int( $clean_key ) ) {
				continue;
			}
			$clean_value = $this->editor_writing_pack_payload_value( $item, $depth + 1 );
			if ( null !== $clean_value ) {
				$result[ $clean_key ] = $clean_value;
			}
		}

		return $result;
	}

	private function editor_writing_pack_related_articles( array $knowledge ): array {
		$items = $this->editor_related_content_items( $knowledge );
		$result = array();
		foreach ( array_slice( $items, 0, 6 ) as $index => $item ) {
			$result[] = array(
				'post_id'      => absint( $item['post_id'] ?? $item['id'] ?? 0 ),
				'title'        => sanitize_text_field( (string) ( $item['title'] ?? $item['name'] ?? '' ) ),
				'url'          => esc_url_raw( (string) ( $item['url'] ?? $item['permalink'] ?? '' ) ),
				'score'        => is_numeric( $item['score'] ?? null ) ? (float) $item['score'] : null,
				'evidence_ref' => 'site_knowledge:' . sanitize_key( (string) ( $item['post_id'] ?? $item['id'] ?? $index ) ),
			);
		}

		return $result;
	}

	private function editor_image_recommendation_section( array $section ): array {
		$items = $this->editor_image_candidate_items( $section );
		$target_field = 'paragraph_image' === sanitize_key( (string) ( $section['image_mode'] ?? $section['recommended_use'] ?? '' ) ) ? 'paragraph_image' : 'featured_image';
		$result = $this->editor_toolkit_image_candidate_review_artifact(
			array(
				'image_candidates' => array_slice( $items, 0, 12 ),
				'target_field'     => $target_field,
				'candidate_limit'  => 8,
			)
		);
		if ( is_wp_error( $result ) ) {
			$review_artifact = $this->empty_toolkit_image_candidate_review_artifact( $result );
		} else {
			$data = is_array( $result['data'] ?? null ) ? $result['data'] : $result;
			$review_artifact = is_array( $data ) ? $data : $this->empty_toolkit_image_candidate_review_artifact(
				new WP_Error(
					'npcink_toolbox_image_candidate_review_toolkit_invalid_artifact',
					__( 'The Toolkit image candidate review ability returned an invalid artifact.', 'npcink-workflow-toolbox' ),
					array( 'status' => 500 )
				)
			);
		}

		$section['image_candidate_review']     = $review_artifact;
		$section['image_candidate_contract']   = 'image_candidate.v1';
		$section['candidate_contract']         = 'recommendation_candidate.v1';
		$section['source_ability_id']          = 'npcink-abilities-toolkit/build-image-candidate-review-artifact';
		$section['recommendation_candidates']  = is_array( $review_artifact['recommendation_candidates'] ?? null ) ? $review_artifact['recommendation_candidates'] : array();

		return $section;
	}

	private function editor_image_candidate_items( array $section ): array {
		foreach ( array( 'image_candidates', 'images', 'image_source_candidates', 'source_candidates', 'media_candidates', 'assets', 'candidates' ) as $key ) {
			if ( is_array( $section[ $key ] ?? null ) ) {
				return array_values( array_filter( $section[ $key ], 'is_array' ) );
			}
		}

		return array();
	}

	private function editor_toolkit_image_candidate_review_artifact( array $input ) {
		$ability_id = 'npcink-abilities-toolkit/build-image-candidate-review-artifact';
		if ( ! function_exists( 'npcink_abilities_toolkit_get_registered' ) ) {
			return new WP_Error(
				'npcink_toolbox_image_candidate_review_toolkit_unavailable',
				__( 'Npcink Abilities Toolkit is required to build image candidate review artifacts.', 'npcink-workflow-toolbox' ),
				array( 'status' => 503 )
			);
		}

		$registered = npcink_abilities_toolkit_get_registered();
		$definition = is_array( $registered[ $ability_id ] ?? null ) ? $registered[ $ability_id ] : array();
		$callback   = $definition['execute_callback'] ?? null;
		if ( ! is_callable( $callback ) ) {
			return new WP_Error(
				'npcink_toolbox_image_candidate_review_toolkit_unavailable',
				__( 'The Toolkit image candidate review ability is not currently callable.', 'npcink-workflow-toolbox' ),
				array( 'status' => 503 )
			);
		}

		$result = call_user_func( $callback, $input );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( ! is_array( $result ) ) {
			return new WP_Error(
				'npcink_toolbox_image_candidate_review_toolkit_invalid_response',
				__( 'The Toolkit image candidate review ability returned an invalid response.', 'npcink-workflow-toolbox' ),
				array( 'status' => 500 )
			);
		}

		return $result;
	}

	private function empty_toolkit_image_candidate_review_artifact( WP_Error $error ): array {
		return array(
			'artifact_type'             => 'image_candidate_review.v1',
			'candidate_type'            => 'image_candidates',
			'candidate_contract'        => 'image_candidate.v1',
			'projection_contract'       => 'recommendation_candidate.v1',
			'write_posture'             => 'suggestion_only',
			'final_write_path'          => 'core_proposal_required',
			'direct_wordpress_write'    => false,
			'source_ability_id'         => 'npcink-abilities-toolkit/build-image-candidate-review-artifact',
			'adoption_plan_ability_id'  => 'npcink-abilities-toolkit/build-image-candidate-adoption-plan',
			'toolkit_required'          => true,
			'error_code'                => sanitize_key( $error->get_error_code() ),
			'error_message'             => sanitize_text_field( $error->get_error_message() ),
			'items'                     => array(),
			'recommendation_candidates' => array(),
		);
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
				__( 'Reject summaries that add facts not present in the draft, site context, or cited evidence.', 'npcink-workflow-toolbox' ),
				__( 'Prefer existing categories and tags; defer new vocabulary to a later taxonomy governance workflow.', 'npcink-workflow-toolbox' ),
				__( 'Use related Site Knowledge results to avoid duplicate coverage and taxonomy drift.', 'npcink-workflow-toolbox' ),
			),
			'handoff'                => array(
				'final_writes'           => 'core_proposal_required',
				'direct_wordpress_write' => false,
				'next_steps'             => array(
					__( 'Review the summary, category, and tag candidates in the editor.', 'npcink-workflow-toolbox' ),
					__( 'Prepare a Core proposal only after an operator chooses accepted metadata changes.', 'npcink-workflow-toolbox' ),
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
				'user_expression'    => '' !== trim( $query ) ? $query : __( 'Improve summary, category, and tag discoverability for the current draft.', 'npcink-workflow-toolbox' ),
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
					__( 'A clearer excerpt can improve archive, social, and answer-summary presentation without rewriting the article body.', 'npcink-workflow-toolbox' ),
					__( 'Existing WordPress terms should be reused; new vocabulary belongs in a later taxonomy governance workflow.', 'npcink-workflow-toolbox' ),
					__( 'Related Site Knowledge evidence can reveal duplicate coverage and proven term patterns.', 'npcink-workflow-toolbox' ),
				),
				'warnings'          => array(
					__( 'Do not accept summaries that add unsupported claims.', 'npcink-workflow-toolbox' ),
					__( 'Do not use the editor recommendation loop to create categories or tags.', 'npcink-workflow-toolbox' ),
					__( 'Do not treat related-content evidence as indexing or RAG lifecycle ownership inside Toolbox.', 'npcink-workflow-toolbox' ),
				),
				'evidence_strength' => empty( $evidence_refs ) ? 'draft_only' : 'draft_plus_tool_context',
			),
			'delta'                  => array(
				'excerpt'             => array(
					'recommended'   => sanitize_text_field( (string) ( $excerpt_item['value'] ?? '' ) ),
					'reason'        => sanitize_text_field( (string) ( $excerpt_item['reason'] ?? __( 'Use the short summary candidate only after operator review.', 'npcink-workflow-toolbox' ) ) ),
					'evidence_refs' => $this->editor_content_metadata_evidence_ids( $evidence_refs ),
				),
				'categories'          => $this->editor_content_metadata_term_delta_items( array_slice( $categories, 0, 5 ), $evidence_refs ),
				'tags'                => $this->editor_content_metadata_term_delta_items( array_slice( $tags, 0, 8 ), $evidence_refs ),
				'new_term_candidates' => $this->editor_content_metadata_new_term_delta_items( $proposed_new_terms ),
			),
			'authorization'          => array(
				'classification'          => sanitize_key( (string) ( $authorization['classification'] ?? Operation_Classifier::SUGGESTION_ONLY ) ),
				'reason'                  => __( 'This Content Metadata Delta only recommends excerpt and existing-term changes. Accepted writes must use the Core handoff preview and reusable WordPress abilities.', 'npcink-workflow-toolbox' ),
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
				'local_admin_consent_note' => __( 'A later local-admin-consent path requires a present administrator, one post, exact preview, and activity evidence before any direct local apply can be considered.', 'npcink-workflow-toolbox' ),
				'handoff_preview_ref'      => sanitize_text_field( (string) ( $handoff_preview['artifact_type'] ?? 'summary_terms_handoff_preview.v1' ) ),
			),
			'outcome_contract'       => array(
				'checks' => array(
					'excerpt_reviewed_with_no_unsupported_claims',
					'existing_categories_or_tags_reused_when_possible',
					'related_content_terms_used_for_ranking_only',
					'related_content_ranking_evidence_only',
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
		$source_knowledge = $this->editor_support_section(
			$this->editor_cached_site_knowledge(
				array(
					'query'           => $query,
					'intent'          => 'internal_links',
					'current_post_id' => absint( $context['post_id'] ?? 0 ),
					'max_results'     => 8,
				)
			)
		);
		$related_content_evidence = $this->editor_internal_link_related_content_evidence( $source_knowledge );
		$input = array(
			'current_post_id'          => absint( $context['post_id'] ?? 0 ),
			'post_type'                => sanitize_key( (string) ( $context['post_type'] ?? 'post' ) ),
			'query'                    => sanitize_textarea_field( $query ),
			'title'                    => sanitize_text_field( (string) ( $context['title'] ?? '' ) ),
			'excerpt'                  => sanitize_textarea_field( (string) ( $context['excerpt'] ?? '' ) ),
			'content_text'             => sanitize_textarea_field( (string) ( $context['content_text'] ?? '' ) ),
			'selected_text'            => sanitize_textarea_field( (string) ( $context['selected_text'] ?? '' ) ),
			'selected_block_text'      => sanitize_textarea_field( (string) ( $context['selected_block_text'] ?? '' ) ),
			'user_instruction'         => sanitize_textarea_field( (string) ( $context['user_instruction'] ?? '' ) ),
			'candidate_limit'          => 8,
			'max_targets'              => 6,
			'related_content_evidence' => $related_content_evidence,
		);
		if ( 0 >= (int) $input['current_post_id'] ) {
			unset( $input['current_post_id'] );
		}
		if ( array() === $related_content_evidence ) {
			unset( $input['related_content_evidence'] );
		}

		$result = $this->editor_toolkit_internal_link_candidates( $input );
		if ( is_wp_error( $result ) ) {
			return $this->empty_toolkit_internal_link_candidates( $result, $source_knowledge );
		}

		$data = is_array( $result['data'] ?? null ) ? $result['data'] : $result;
		$artifact = is_array( $data['internal_link_candidates'] ?? null ) ? $data['internal_link_candidates'] : array();
		if ( 'internal_link_candidates' !== (string) ( $artifact['candidate_type'] ?? '' ) ) {
			return $this->empty_toolkit_internal_link_candidates(
				new WP_Error(
					'npcink_toolbox_internal_link_toolkit_invalid_artifact',
					__( 'The Toolkit internal-link ability returned an invalid artifact.', 'npcink-workflow-toolbox' ),
					array( 'status' => 500 )
				),
				$source_knowledge
			);
		}

		$items = is_array( $artifact['items'] ?? null ) ? $artifact['items'] : array();
		$artifact['input_scope'] = $this->editor_input_scope( $context );
		$artifact['source_ability_id'] = 'npcink-abilities-toolkit/resolve-internal-link-targets';
		$artifact['source_knowledge'] = $source_knowledge;
		$artifact['toolkit_artifact'] = $data;
		$artifact['recommendation_candidates'] = $this->editor_internal_link_recommendation_candidates( $items );
		$artifact['final_write_path'] = 'operator_review_only_no_insert';
		$artifact['direct_wordpress_write'] = false;
		$artifact['owner_label'] = 'human_editor';
		$artifact['next_safe_action'] = 'copy_or_open_then_place_manually';
		$artifact['action_policy'] = 'operator_review_only_no_insert';
		$artifact['review_policy'] = array_merge(
			is_array( $artifact['review_policy'] ?? null ) ? $artifact['review_policy'] : array(),
			array(
				'link_insertion_owner'       => 'human_editor',
				'automatic_anchor_insert'    => false,
				'post_content_patch_handoff' => false,
				'current_post_excluded'      => true,
			)
		);
		$handoff = is_array( $artifact['handoff'] ?? null ) ? $artifact['handoff'] : array();
		$blocked_actions = is_array( $handoff['blocked_actions'] ?? null ) ? $handoff['blocked_actions'] : array();
		$handoff['final_writes'] = 'operator_review_only_no_insert';
		$handoff['direct_wordpress_write'] = false;
		$handoff['blocked_actions'] = array_values(
			array_unique(
				array_merge(
					$blocked_actions,
					array(
						'no_link_insertion_in_toolbox',
						'no_patch_post_content_handoff_yet',
						'no_automatic_anchor_insertion',
					)
				)
			)
		);
		$artifact['handoff'] = $handoff;

		return $artifact;
	}

	private function editor_internal_link_related_content_evidence( array $source_knowledge ): array {
		$evidence = array();
		foreach ( array_slice( $this->editor_related_content_items( $source_knowledge ), 0, 8 ) as $index => $item ) {
			$post_id = absint( $item['post_id'] ?? ( $item['id'] ?? 0 ) );
			$evidence[] = array(
				'post_id'      => $post_id,
				'title'        => sanitize_text_field( (string) ( $item['title'] ?? $item['name'] ?? '' ) ),
				'url'          => esc_url_raw( (string) ( $item['url'] ?? ( $item['permalink'] ?? ( $item['link'] ?? ( $item['source_url'] ?? '' ) ) ) ) ),
				'excerpt'      => sanitize_textarea_field( wp_trim_words( wp_strip_all_tags( (string) ( $item['excerpt'] ?? $item['snippet'] ?? $item['content_excerpt'] ?? '' ) ), 55, '' ) ),
				'score'        => is_numeric( $item['score'] ?? null ) ? (float) $item['score'] : null,
				'evidence_ref' => 'site_knowledge:' . sanitize_key( (string) ( $post_id ?: $index ) ),
			);
		}

		return array_values(
			array_filter(
				$evidence,
				static fn( array $item ): bool => '' !== (string) ( $item['title'] ?? '' ) || '' !== (string) ( $item['url'] ?? '' ) || 0 < absint( $item['post_id'] ?? 0 )
			)
		);
	}

	private function editor_toolkit_internal_link_candidates( array $input ) {
		$ability_id = 'npcink-abilities-toolkit/resolve-internal-link-targets';
		if ( ! function_exists( 'npcink_abilities_toolkit_get_registered' ) ) {
			return new WP_Error(
				'npcink_toolbox_internal_link_toolkit_unavailable',
				__( 'Npcink Abilities Toolkit is required to build internal-link candidates.', 'npcink-workflow-toolbox' ),
				array( 'status' => 503 )
			);
		}

		$registered = npcink_abilities_toolkit_get_registered();
		$definition = is_array( $registered[ $ability_id ] ?? null ) ? $registered[ $ability_id ] : array();
		$callback   = $definition['execute_callback'] ?? null;
		if ( ! is_callable( $callback ) ) {
			return new WP_Error(
				'npcink_toolbox_internal_link_toolkit_unavailable',
				__( 'The Toolkit internal-link ability is not currently callable.', 'npcink-workflow-toolbox' ),
				array( 'status' => 503 )
			);
		}

		$result = call_user_func( $callback, $input );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( ! is_array( $result ) ) {
			return new WP_Error(
				'npcink_toolbox_internal_link_toolkit_invalid_response',
				__( 'The Toolkit internal-link ability returned an invalid response.', 'npcink-workflow-toolbox' ),
				array( 'status' => 500 )
			);
		}

		return $result;
	}

	private function empty_toolkit_internal_link_candidates( WP_Error $error, array $source_knowledge ): array {
		return array(
			'artifact_type'          => 'internal_link_candidates.v1',
			'candidate_type'         => 'internal_link_candidates',
			'candidate_contract'     => 'recommendation_candidate.v1',
			'write_posture'          => 'suggestion_only',
			'final_write_path'       => 'operator_review_only_no_insert',
			'direct_wordpress_write' => false,
			'source_ability_id'      => 'npcink-abilities-toolkit/resolve-internal-link-targets',
			'toolkit_required'       => true,
			'error_code'             => sanitize_key( $error->get_error_code() ),
			'error_message'          => sanitize_text_field( $error->get_error_message() ),
			'items'                  => array(),
			'recommendation_candidates' => array(),
			'owner_label'            => 'human_editor',
			'next_safe_action'       => 'fix_toolkit_or_retry_later',
			'action_policy'          => 'operator_review_only_no_insert',
			'source_knowledge'       => $source_knowledge,
			'review_policy'          => array(
				'link_insertion_owner'       => 'human_editor',
				'automatic_anchor_insert'    => false,
				'post_content_patch_handoff' => false,
				'current_post_excluded'      => true,
			),
			'handoff'                => array(
				'final_writes'           => 'operator_review_only_no_insert',
				'direct_wordpress_write' => false,
				'blocked_actions'        => array(
					'no_link_insertion_in_toolbox',
					'no_patch_post_content_handoff_yet',
					'no_automatic_anchor_insertion',
				),
			),
		);
	}

	private function editor_internal_link_recommendation_candidates( array $items ): array {
		$candidates = array();
		foreach ( array_slice( $items, 0, 8 ) as $index => $item ) {
			$title  = sanitize_text_field( (string) ( $item['title'] ?? '' ) );
			$anchor = sanitize_text_field( (string) ( $item['suggested_anchor_text'] ?? '' ) );
			$url    = esc_url_raw( (string) ( $item['target_url'] ?? '' ) );
			$target_post_id = absint( $item['target_post_id'] ?? 0 );
			$placement_hint = sanitize_text_field( (string) ( $item['placement_hint'] ?? '' ) );
			$reason = sanitize_text_field( (string) ( $item['reason'] ?? '' ) );
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
			$quality_issues = array( __( '人工确认目标文章与当前段落或全文语义相关后再插入。', 'npcink-workflow-toolbox' ) );
			if ( '' === $url ) {
				$quality_score   = min( $quality_score, 55 );
				$quality_issues[] = __( '缺少目标 URL，插入前需要人工补充或确认。', 'npcink-workflow-toolbox' );
			}

			$candidates[] = $this->editor_recommendation_candidate(
				array(
					'id'                   => 'internal_link_' . ( $index + 1 ),
					'kind'                 => 'internal_link',
					'label'                => '' !== $title ? $title : __( 'Internal link candidate', 'npcink-workflow-toolbox' ),
					'value'                => '' !== $anchor ? $anchor : $url,
					'reason'               => $reason,
					'confidence'           => $has_score && $score > 0 && $score <= 1 ? $score : null,
					'target_field'         => 'post_content',
					'action_policy'        => 'operator_review_only_no_insert',
					'target_ref'           => array(
						'post_id' => $target_post_id,
						'title'   => $title,
						'url'     => $url,
					),
					'anchor_or_context'    => '' !== $anchor ? $anchor : $placement_hint,
					'evidence_note'        => '' !== $reason ? $reason : __( 'Related content candidate for manual internal-link review.', 'npcink-workflow-toolbox' ),
					'owner_label'          => 'human_editor',
					'next_safe_action'     => 'copy_or_open_then_place_manually',
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

	private function editor_article_audio_generation( array $context, string $intent ): array {
		$source_text       = trim( (string) ( $context['content_audio_text'] ?? $context['content_full_text'] ?? $context['content_text'] ?? '' ) );
		$force_regenerate = ! empty( $context['force_regenerate'] );
		$audio_preferences = is_array( $context['audio_preferences'] ?? null ) ? $context['audio_preferences'] : array();
		$script_source    = array(
			'artifact_type'          => 'article_audio_script_source.v1',
			'intent'                 => $intent,
			'audio_preferences'      => $audio_preferences,
			'write_posture'          => 'suggestion_only',
			'direct_wordpress_write' => false,
		);

		if ( 'article_audio_summary' === $intent ) {
			$summary_ai = $this->editor_support_section(
				$this->editor_cached_hosted_ai_content_support(
					array(
						'intent'             => 'audio_summary_script',
						'post_id'            => absint( $context['post_id'] ?? 0 ),
						'title'              => (string) ( $context['title'] ?? '' ),
						'excerpt'            => (string) ( $context['excerpt'] ?? '' ),
						'content'            => (string) ( $context['content_full_text'] ?? $context['content_text'] ?? '' ),
						'user_instruction'   => (string) ( $context['user_instruction'] ?? '' ),
						'generation_variant' => (string) ( $context['generation_variant'] ?? '' ),
						'summary_generation_mode' => 'fast_brief',
					),
					$force_regenerate
				)
			);
			$script = $this->editor_audio_summary_script_text( $summary_ai );
			$script_source['summary_script'] = $summary_ai;
		} else {
			$script = $this->editor_audio_source_text( $source_text );
			$script_source['source_mode'] = 'article_text';
		}

		$audio = $this->editor_support_section(
			$this->editor_cached_audio_generation(
				array(
					'intent'    => $intent,
					'text'      => $script,
					'script'    => $script,
					'user_instruction' => (string) ( $context['user_instruction'] ?? '' ),
					'audio_preferences' => $audio_preferences,
					'format'    => 'mp3',
					'context'   => array(
						'post_id'          => absint( $context['post_id'] ?? 0 ),
						'post_type'        => sanitize_key( (string) ( $context['post_type'] ?? 'post' ) ),
						'title'            => sanitize_text_field( (string) ( $context['title'] ?? '' ) ),
						'excerpt'          => sanitize_textarea_field( (string) ( $context['excerpt'] ?? '' ) ),
						'source_text_hash' => md5( $source_text ),
						'user_instruction' => sanitize_textarea_field( (string) ( $context['user_instruction'] ?? '' ) ),
						'audio_preferences' => $audio_preferences,
						'surface'          => 'editor_content_support',
					),
				),
				$force_regenerate
			)
		);

		return array(
			'artifact_type'          => 'article_audio_support.v1',
			'composition_role'       => 'article_audio_support',
			'candidate_type'         => $intent,
			'write_posture'          => 'suggestion_only',
			'final_write_path'       => 'core_proposal_required',
			'adoption_plan_route'    => '/wp-json/npcink-toolbox/v1/flows/article-audio-adoption-plan',
			'direct_wordpress_write' => false,
			'script'                 => $script,
			'script_source'          => $script_source,
			'audio_preferences'      => $audio_preferences,
			'audio'                  => $audio,
			'items'                  => is_array( $audio['items'] ?? null ) ? $audio['items'] : array(),
			'audio_generation'       => $audio,
			'use_case'               => 'article_audio_summary' === $intent ? 'longform_listening_summary' : 'full_article_narration',
			'review_policy'          => array(
				'script_review_required' => true,
				'audio_meta_owner'       => 'core_governed_handoff',
				'media_import_owner'     => 'core_governed_handoff',
				'no_post_content_patch'  => true,
			),
			'handoff'                => array(
				'final_writes'           => 'core_proposal_required',
				'adoption_plan_route'    => '/wp-json/npcink-toolbox/v1/flows/article-audio-adoption-plan',
				'direct_wordpress_write' => false,
				'blocked_actions'        => array(
					'no_media_import_in_toolbox',
					'no_post_content_patch',
					'no_direct_wordpress_write',
				),
			),
		);
	}

	private function editor_audio_source_text( string $text ): string {
		$plain = trim( wp_strip_all_tags( $text ) );
		if ( '' === $plain ) {
			return '';
		}
		if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
			return mb_strlen( $plain, 'UTF-8' ) > self::EDITOR_AUDIO_TEXT_MAX_CHARS ? mb_substr( $plain, 0, self::EDITOR_AUDIO_TEXT_MAX_CHARS, 'UTF-8' ) : $plain;
		}
		return strlen( $plain ) > self::EDITOR_AUDIO_TEXT_MAX_CHARS ? substr( $plain, 0, self::EDITOR_AUDIO_TEXT_MAX_CHARS ) : $plain;
	}

	private function editor_audio_summary_script_text( array $summary_ai ): string {
		$output_json = is_array( $summary_ai['output_json'] ?? null ) ? $summary_ai['output_json'] : array();
		$parts       = array();
		foreach ( array( 'opening', 'script', 'closing' ) as $key ) {
			$value = trim( sanitize_textarea_field( (string) ( $output_json[ $key ] ?? '' ) ) );
			if ( '' !== $value ) {
				$parts[] = $value;
			}
		}
		if ( is_array( $output_json['key_points'] ?? null ) ) {
			foreach ( array_slice( $output_json['key_points'], 0, 5 ) as $point ) {
				$value = trim( sanitize_textarea_field( (string) $point ) );
				if ( '' !== $value ) {
					$parts[] = $value;
				}
			}
		}
		$script = trim( implode( "\n\n", array_values( array_unique( $parts ) ) ) );
		if ( '' === $script ) {
			$script = trim( sanitize_textarea_field( (string) ( $summary_ai['output_text'] ?? '' ) ) );
		}
		if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
			return mb_strlen( $script, 'UTF-8' ) > self::EDITOR_AUDIO_TEXT_MAX_CHARS ? mb_substr( $script, 0, self::EDITOR_AUDIO_TEXT_MAX_CHARS, 'UTF-8' ) : $script;
		}
		return strlen( $script ) > self::EDITOR_AUDIO_TEXT_MAX_CHARS ? substr( $script, 0, self::EDITOR_AUDIO_TEXT_MAX_CHARS ) : $script;
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
				__( 'Paragraph %d', 'npcink-workflow-toolbox' ),
				$index + 1
			);

			if ( $length >= 220 ) {
				$items[] = $this->editor_article_checkup_issue(
					'paragraph_too_long_' . ( $index + 1 ),
					'format',
					'warning',
					$location,
					$paragraph,
					__( 'Paragraph is dense and may be hard to scan.', 'npcink-workflow-toolbox' ),
					__( 'Consider splitting the paragraph by claim, condition, or conclusion. Keep the editor responsible for the final wording.', 'npcink-workflow-toolbox' )
				);
			}

			if ( $longest_sentence >= 95 ) {
				$items[] = $this->editor_article_checkup_issue(
					'long_sentence_' . ( $index + 1 ),
					'clarity',
					'warning',
					$location,
					$paragraph,
					__( 'One sentence carries too many clauses.', 'npcink-workflow-toolbox' ),
					__( 'Review whether the sentence should be broken into shorter factual steps before publishing.', 'npcink-workflow-toolbox' )
				);
			}

			if ( 1 === preg_match( '/(\d|万|倍|%|百分|快|慢|耗时|性能|测试|经测试|同等|相当|无明显|明显|适合)/u', $paragraph ) ) {
				$items[] = $this->editor_article_checkup_issue(
					'fact_claim_' . ( $index + 1 ),
					'fact_gap',
					'warning',
					$location,
					$paragraph,
					__( 'The paragraph contains a metric, comparison, performance, or scope claim.', 'npcink-workflow-toolbox' ),
					__( 'Verify the source, test condition, comparison object, and applicable scope. Do not let Toolbox turn one observed result into a universal fact.', 'npcink-workflow-toolbox' )
				);
			}

			if ( 1 === preg_match( '/(绝对|完全|一键|显著|极大|领先|革命性|无敌|完美|保证)/u', $paragraph ) ) {
				$items[] = $this->editor_article_checkup_issue(
					'tone_risk_' . ( $index + 1 ),
					'tone',
					'info',
					$location,
					$paragraph,
					__( 'Tone may read stronger than the supporting evidence.', 'npcink-workflow-toolbox' ),
					__( 'Review whether the claim should be softened or tied to a specific condition.', 'npcink-workflow-toolbox' )
				);
			}

			foreach ( $this->editor_article_checkup_structure_glue_issues( $paragraph, $index + 1, $location ) as $structure_issue ) {
				$items[] = $structure_issue;
			}
		}

		$word_count = str_word_count( $text );
		$text_length = $this->editor_text_length( $text );
		if ( '' === $title ) {
			$items[] = $this->editor_article_checkup_issue(
				'missing_title',
				'structure',
				'error',
				__( 'Title', 'npcink-workflow-toolbox' ),
				'',
				__( 'The article title is missing.', 'npcink-workflow-toolbox' ),
				__( 'Add a human-reviewed title before running title or metadata handoff actions.', 'npcink-workflow-toolbox' )
			);
		}
		if ( '' === $excerpt && ( $word_count >= 120 || $text_length >= 360 ) ) {
			$items[] = $this->editor_article_checkup_issue(
				'missing_excerpt',
				'structure',
				'warning',
				__( 'Excerpt', 'npcink-workflow-toolbox' ),
				'',
				__( 'The article has enough body content but no excerpt.', 'npcink-workflow-toolbox' ),
				__( 'Review whether a summary suggestion should be generated, then accept it manually before saving.', 'npcink-workflow-toolbox' )
			);
		}
		if ( count( $paragraphs ) >= 5 && ! $this->editor_article_checkup_has_heading_signal( $text ) ) {
			$items[] = $this->editor_article_checkup_issue(
				'missing_heading_structure',
				'structure',
				'info',
				__( 'Full article', 'npcink-workflow-toolbox' ),
				'',
				__( 'The draft is long enough to need scan-friendly structure, but no obvious heading signal was found.', 'npcink-workflow-toolbox' ),
				__( 'Review whether section headings, lists, or clearer paragraph grouping would help readers scan the article.', 'npcink-workflow-toolbox' )
			);
		}

		$format_consistency   = $this->editor_article_checkup_format_consistency( $paragraphs );
		$format_items         = is_array( $format_consistency['items'] ?? null ) ? $format_consistency['items'] : array();
		foreach ( $format_items as $format_item ) {
			$items[] = $format_item;
		}

		$semantic_consistency = $this->editor_article_checkup_semantic_consistency( $text );
		$semantic_items       = is_array( $semantic_consistency['items'] ?? null ) ? $semantic_consistency['items'] : array();
		foreach ( $semantic_items as $semantic_item ) {
			$items[] = $semantic_item;
		}

		if ( empty( $items ) ) {
			$items[] = $this->editor_article_checkup_issue(
				'no_blocking_local_issue',
				'clarity',
				'info',
				__( 'Full article', 'npcink-workflow-toolbox' ),
				'',
				__( 'No high-confidence local article issues were found.', 'npcink-workflow-toolbox' ),
				__( 'This is a local heuristic check only. Run focused title, summary, taxonomy, internal-link, image, or publish preflight actions when needed.', 'npcink-workflow-toolbox' )
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
			'format_consistency'     => $format_consistency,
			'semantic_consistency'   => $semantic_consistency,
			'items'                  => array_slice( $items, 0, 12 ),
			'summary'                => array(
				'paragraph_count'          => count( $paragraphs ),
				'issue_count'              => count( $items ),
				'format_consistency_count' => count( $format_items ),
				'semantic_consistency_count' => count( $semantic_items ),
				'cloud_calls'              => false,
				'no_rewrite'               => true,
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

	private function editor_article_checkup_structure_glue_issues( string $paragraph, int $paragraph_number, string $location ): array {
		$signals = array();

		if ( $this->editor_text_has_heading_label_glue( $paragraph ) ) {
			$signals[] = __( '标题式标签直接黏在正文前', 'npcink-workflow-toolbox' );
		}
		if ( $this->editor_text_has_phrase_cluster_glue( $paragraph ) ) {
			$signals[] = __( '短语组之间缺少分隔', 'npcink-workflow-toolbox' );
		}
		if ( $this->editor_text_has_alnum_cjk_glue( $paragraph ) ) {
			$signals[] = __( '字母、数字或方案标签与中文黏连', 'npcink-workflow-toolbox' );
		}

		if ( empty( $signals ) ) {
			return array();
		}

		return array(
			$this->editor_article_checkup_issue(
				'structural_glue_' . $paragraph_number,
				'format',
				'warning',
				$location,
				$paragraph,
				__( '标题标签、短语组或方案标签可能与正文黏连。', 'npcink-workflow-toolbox' ),
				sprintf(
					/* translators: %s: comma-separated structural glue signals. */
					__( '请检查这些分隔问题：%s。发布前可改为小标题、标点、项目符号或表格行。', 'npcink-workflow-toolbox' ),
					implode( ', ', $signals )
				)
			),
		);
	}

	private function editor_article_checkup_format_consistency( array $paragraphs ): array {
		$items = array();

		foreach ( $paragraphs as $index => $paragraph ) {
			if ( ! is_string( $paragraph ) || '' === trim( $paragraph ) ) {
				continue;
			}
			$inline_marker_count = preg_match_all( '/(?:^|[。；;：:\\s])(?:\\d+[.．、]|[（(]?\\d+[）)]|[A-Za-z][.．、])(?=\\s*[^\\s])/u', $paragraph );
			if ( (int) $inline_marker_count < 2 ) {
				continue;
			}
			$location = sprintf(
				/* translators: %d: paragraph number. */
				__( 'Paragraph %d', 'npcink-workflow-toolbox' ),
				$index + 1
			);
			$items[] = $this->editor_article_checkup_issue(
				'format_inline_list_' . ( $index + 1 ),
				'format',
				'info',
				$location,
				$paragraph,
				__( 'The paragraph looks like an inline numbered or option list.', 'npcink-workflow-toolbox' ),
				__( 'Review whether these points should become bullets, table rows, or separate paragraphs. Keep this as layout guidance only; do not auto-rewrite the article.', 'npcink-workflow-toolbox' )
			);
			if ( count( $items ) >= 3 ) {
				break;
			}
		}

		return array(
			'artifact_type'          => 'format_consistency.v1',
			'source'                 => 'current_full_draft_local_heuristic',
			'status'                 => 'ready',
			'write_posture'          => 'suggestion_only',
			'direct_wordpress_write' => false,
			'no_rewrite'             => true,
			'items'                  => $items,
		);
	}

	private function editor_article_checkup_semantic_consistency( string $text ): array {
		$sentences = $this->editor_article_checkup_sentences( $text );
		$items     = array();

		foreach ( $this->editor_article_checkup_semantic_aeo_answer_order_issues( $sentences ) as $item ) {
			$items[] = $item;
			if ( count( $items ) >= 4 ) {
				break;
			}
		}

		if ( count( $items ) < 4 ) {
			foreach ( $this->editor_article_checkup_semantic_term_tensions( $sentences ) as $item ) {
				$items[] = $item;
				if ( count( $items ) >= 4 ) {
					break;
				}
			}
		}

		if ( count( $items ) < 4 ) {
			foreach ( $this->editor_article_checkup_semantic_boundary_tensions( $sentences ) as $item ) {
				$items[] = $item;
				if ( count( $items ) >= 4 ) {
					break;
				}
			}
		}

		return array(
			'artifact_type'          => 'semantic_consistency.v1',
			'status'                 => empty( $items ) ? 'clear' : 'review',
			'source'                 => 'current_full_draft_local_heuristic',
			'write_posture'          => 'suggestion_only_no_replacement_text',
			'action_policy'          => 'operator_review_only_no_insert',
			'direct_wordpress_write' => false,
			'no_rewrite'             => true,
			'items'                  => array_slice( $items, 0, 4 ),
		);
	}

	private function editor_article_checkup_sentences( string $text ): array {
		$normalized = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $text ) ) ?: '' );
		if ( '' === $normalized ) {
			return array();
		}

		$parts     = preg_split( '/(?<=[。！？!?；;])\s*/u', $normalized );
		$sentences = array();
		foreach ( is_array( $parts ) ? $parts : array( $normalized ) as $part ) {
			$sentence = trim( (string) $part );
			if ( '' === $sentence ) {
				continue;
			}
			$sentences[] = $sentence;
			if ( count( $sentences ) >= 80 ) {
				break;
			}
		}

		return $sentences;
	}

	private function editor_article_checkup_semantic_aeo_answer_order_issues( array $sentences ): array {
		$items = array();
		foreach ( $sentences as $index => $sentence ) {
			$next_sentence = isset( $sentences[ $index + 1 ] ) ? (string) $sentences[ $index + 1 ] : '';
			$window        = trim( $sentence . ' ' . $next_sentence );
			if ( 1 !== preg_match( '/(AEO|回答型体验|回答型|直接答案)/iu', $window ) ) {
				continue;
			}
			if ( 1 !== preg_match( '/(不能|不应|不要|避免)先给(出)?直接答案/u', $window ) ) {
				continue;
			}
			$items[] = $this->editor_article_checkup_issue(
				'semantic_aeo_answer_order',
				'semantic_consistency',
				'warning',
				__( 'Full article', 'npcink-workflow-toolbox' ),
				$window,
				__( 'The AEO section may reverse answer-first guidance.', 'npcink-workflow-toolbox' ),
				__( 'Confirm whether the cannot-answer-first wording is intentional. For answer-oriented content, manually verify the direct answer, conditions, steps, and limits before publishing.', 'npcink-workflow-toolbox' )
			);
			return $items;
		}

		return $items;
	}

	private function editor_article_checkup_semantic_term_tensions( array $sentences ): array {
		$terms = array( 'SEO', 'AEO', 'GEO', 'AI', 'Toolbox', 'Core', 'Cloud', 'Adapter', 'WordPress', 'OpenClaw' );
		$items = array();

		foreach ( $terms as $term ) {
			$negative = '';
			$positive = '';
			foreach ( $sentences as $sentence ) {
				if ( false === stripos( $sentence, $term ) ) {
					continue;
				}
				if ( '' === $negative && $this->editor_article_checkup_has_negative_semantic_marker( $sentence ) ) {
					$negative = $sentence;
				}
				if ( '' === $positive && $this->editor_article_checkup_has_positive_semantic_marker( $sentence ) ) {
					$positive = $sentence;
				}
				if ( '' !== $negative && '' !== $positive && $negative !== $positive ) {
					$items[] = $this->editor_article_checkup_issue(
						'semantic_term_tension_' . strtolower( $term ),
						'semantic_consistency',
						'warning',
						__( 'Full article', 'npcink-workflow-toolbox' ),
						$negative . ' ' . $positive,
						sprintf(
							/* translators: %s: term. */
							__( 'The draft uses both limiting and enabling language around %s.', 'npcink-workflow-toolbox' ),
							$term
						),
						__( 'Confirm whether the contrast is intentional, stage-specific, or a real contradiction before publishing. Keep any final wording change manual.', 'npcink-workflow-toolbox' )
					);
					break;
				}
			}
		}

		return $items;
	}

	private function editor_article_checkup_semantic_boundary_tensions( array $sentences ): array {
		$items = array();
		foreach ( $sentences as $index => $sentence ) {
			if ( ! $this->editor_article_checkup_has_free_generation_marker( $sentence ) ) {
				continue;
			}
			foreach ( $sentences as $other_index => $other_sentence ) {
				if ( $index === $other_index || ! $this->editor_article_checkup_has_review_boundary_marker( $other_sentence ) ) {
					continue;
				}
				$items[] = $this->editor_article_checkup_issue(
					'semantic_generation_boundary_' . ( $index + 1 ),
					'semantic_consistency',
					'warning',
					__( 'Full article', 'npcink-workflow-toolbox' ),
					$other_sentence . ' ' . $sentence,
					__( 'The draft mixes review-boundary wording with free-generation or replacement wording.', 'npcink-workflow-toolbox' ),
					__( 'Check whether the free-generation phrase is a counterexample or the actual recommendation. Do not turn this into an automatic rewrite.', 'npcink-workflow-toolbox' )
				);
				return $items;
			}
		}

		return $items;
	}

	private function editor_article_checkup_has_negative_semantic_marker( string $sentence ): bool {
		return 1 === preg_match( '/(不是|不能|不要|不应|不得|避免|禁止|不等于|不要让|不替换|不生成|不写入)/u', $sentence );
	}

	private function editor_article_checkup_has_positive_semantic_marker( string $sentence ): bool {
		return 1 === preg_match( '/(应该|需要|可以|用于|负责|依赖|直接|自动|一键|生成|替换|写入|发布)/u', $sentence );
	}

	private function editor_article_checkup_has_free_generation_marker( string $sentence ): bool {
		return 1 === preg_match( '/(自由发挥|生成全文|替换正文|一键优化|自动改写|自动发布|直接写入)/u', $sentence );
	}

	private function editor_article_checkup_has_review_boundary_marker( string $sentence ): bool {
		return 1 === preg_match( '/(人工|审阅|审核|建议|只读|不生成|不替换|不写入|约束|治理|Core|proposal)/iu', $sentence );
	}

	private function editor_article_checkup_has_heading_signal( string $text ): bool {
		return 1 === preg_match( '/(^|\n)\s*(#{2,6}\s+|[一二三四五六七八九十]+[、.．]|\\d+[.．、]|[（(][一二三四五六七八九十\\d]+[）)])|<h[1-6][^>]*>/iu', $text );
	}

	private function editor_text_has_heading_label_glue( string $text ): bool {
		return 1 === preg_match( '/(核心要点|评估维度|主要差异|适用建议|常见问题|方案\s*[A-Za-zＡ-Ｚ])(?=[\p{Han}A-Za-z0-9])/u', $text );
	}

	private function editor_text_has_phrase_cluster_glue( string $text ): bool {
		return 1 === preg_match( '/可维护性编辑体验响应式表现治理边界/u', $text )
			|| 1 === preg_match( '/(可维护性|编辑体验|响应式表现|治理边界)(可维护性|编辑体验|响应式表现|治理边界)(可维护性|编辑体验|响应式表现|治理边界)/u', $text );
	}

	private function editor_text_has_alnum_cjk_glue( string $text ): bool {
		return 1 === preg_match( '/(?:[A-Za-z0-9][\x{4e00}-\x{9fff}]|[\x{4e00}-\x{9fff}][A-Za-z0-9]{2,})/u', $text );
	}

	private function editor_text_has_structural_glue( string $text ): bool {
		return $this->editor_text_has_heading_label_glue( $text )
			|| $this->editor_text_has_phrase_cluster_glue( $text )
			|| $this->editor_text_has_alnum_cjk_glue( $text );
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
					array_values(
						array_unique(
							array_filter(
								array_map(
									'trim',
									array(
										(string) ( $context['selected_text_full'] ?? $context['selected_text'] ?? '' ),
										(string) ( $context['selected_block_text_full'] ?? $context['selected_block_text'] ?? '' ),
									)
								)
							)
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
		if ( 'polish_notes' === $provider_intent ) {
			if ( ! $this->editor_paragraph_check_has_output( $section ) ) {
				$section = $this->editor_paragraph_check_local_fallback_section( $section, $content );
			} else {
				$section = $this->editor_paragraph_check_local_overlay_section( $section, $content );
			}
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
		$section['fallback_signal_profile'] = is_array( $output['signal_profile'] ?? null )
			? array_map( 'boolval', $output['signal_profile'] )
			: array();
		$section['output_json']            = $output;
		$section['items']                  = array(
			array(
				'name'          => __( 'Clarity check', 'npcink-workflow-toolbox' ),
				'detail'        => $output['clarity_check'],
				'action_policy' => 'operator_review_only_no_insert',
				'evidence_refs' => array( 'current_selection:paragraph' ),
			),
			array(
				'name'          => __( 'Fact gaps', 'npcink-workflow-toolbox' ),
				'detail'        => $output['fact_gaps'],
				'action_policy' => 'operator_review_only_no_insert',
				'evidence_refs' => array( 'current_selection:paragraph' ),
			),
			array(
				'name'          => __( 'Tone consistency', 'npcink-workflow-toolbox' ),
				'detail'        => $output['tone_consistency'],
				'action_policy' => 'operator_review_only_no_insert',
				'evidence_refs' => array( 'current_selection:paragraph' ),
			),
			array(
				'name'          => __( 'Editing suggestions', 'npcink-workflow-toolbox' ),
				'detail'        => $output['editing_suggestions'],
				'action_policy' => 'operator_review_only_no_insert',
				'evidence_refs' => array( 'current_selection:paragraph' ),
			),
		);

		return $section;
	}

	private function editor_paragraph_check_local_overlay_section( array $section, string $selected_text ): array {
		$output = $this->editor_paragraph_check_local_output( $selected_text, false );
		$overlay = array(
			'artifact_type'  => 'paragraph_local_review_overlay.v1',
			'source'         => 'current_selected_paragraph_only',
			'write_posture'  => 'suggestion_only_no_replacement_text',
			'action_policy'  => 'operator_review_only_no_insert',
			'status'         => 'ready',
			'provider_execution' => 'local_signal_overlay_after_hosted_ai_output',
			'signal_profile' => is_array( $output['signal_profile'] ?? null )
				? array_map( 'boolval', $output['signal_profile'] )
				: array(),
			'output_json'    => $output,
			'items'          => $this->editor_paragraph_check_local_overlay_items( $output ),
		);

		$section['local_review_overlay'] = $overlay;
		$section['local_signal_profile'] = $overlay['signal_profile'];

		$output_json = is_array( $section['output_json'] ?? null ) ? $section['output_json'] : array();
		$output_json['local_review_overlay'] = $overlay;
		$section['output_json'] = $output_json;

		return $section;
	}

	private function editor_paragraph_check_local_overlay_items( array $output ): array {
		$signals = is_array( $output['signal_profile'] ?? null ) ? $output['signal_profile'] : array();
		$items   = array();

		if ( ! empty( $signals['has_structural_glue'] ) ) {
			$items[] = array(
				'name'          => __( 'Local structure cross-check', 'npcink-workflow-toolbox' ),
				'detail'        => (string) ( $output['clarity_check'] ?? '' ),
				'action_policy' => 'operator_review_only_no_insert',
				'evidence_refs' => array( 'current_selection:local_overlay' ),
			);
			$items[] = array(
				'name'          => __( 'Local fact-boundary check', 'npcink-workflow-toolbox' ),
				'detail'        => (string) ( $output['fact_gaps'] ?? '' ),
				'action_policy' => 'operator_review_only_no_insert',
				'evidence_refs' => array( 'current_selection:local_overlay' ),
			);
		} elseif ( ! empty( $signals['has_metric_claim'] ) || ! empty( $signals['has_comparison_claim'] ) || ! empty( $signals['long_or_dense'] ) ) {
			$items[] = array(
				'name'          => __( 'Local fact-boundary check', 'npcink-workflow-toolbox' ),
				'detail'        => (string) ( $output['fact_gaps'] ?? '' ),
				'action_policy' => 'operator_review_only_no_insert',
				'evidence_refs' => array( 'current_selection:local_overlay' ),
			);
		} elseif ( ! empty( $signals['has_scope_claim'] ) || ! empty( $signals['has_causal_transition'] ) ) {
			$items[] = array(
				'name'          => __( 'Local scope check', 'npcink-workflow-toolbox' ),
				'detail'        => (string) ( $output['tone_consistency'] ?? '' ),
				'action_policy' => 'operator_review_only_no_insert',
				'evidence_refs' => array( 'current_selection:local_overlay' ),
			);
		}

		if ( ! empty( $items ) ) {
			$items[] = array(
				'name'          => __( 'Local editing guardrail', 'npcink-workflow-toolbox' ),
				'detail'        => (string) ( $output['editing_suggestions'] ?? '' ),
				'action_policy' => 'operator_review_only_no_insert',
				'evidence_refs' => array( 'current_selection:local_overlay' ),
			);
		}

		return array_values(
			array_filter(
				$items,
				static function ( $item ): bool {
					return is_array( $item ) && '' !== trim( (string) ( $item['detail'] ?? '' ) );
				}
			)
		);
	}

	private function editor_paragraph_check_signal_profile( string $text, int $length, int $punctuation_count ): array {
		$has_performance_claim = 1 === preg_match( '/(快|慢|耗时|性能|经测试|同等服务器|相当|无明显|明显性能|读取|保存)/u', $text );
		$has_metric_claim      = 1 === preg_match( '/(\d|万|倍|%|百分|测试|经测试|数量|规模|id|ID|attachment)/u', $text );
		$has_scope_claim       = 1 === preg_match( '/(可用于|适合|场景|条件|范围|限制|边界|因此|所以|由于|因为)/u', $text );
		$has_comparison_claim  = 1 === preg_match( '/(比|相比|对比|相较|优于|弱于|快于|慢于|高于|低于)/u', $text );
		$has_causal_transition = 1 === preg_match( '/(因此|所以|由于|因为|从而|导致)/u', $text );

		return array(
			'long_or_dense'          => $length > 150 || $punctuation_count >= 3,
			'has_performance_claim' => $has_performance_claim,
			'has_metric_claim'      => $has_metric_claim,
			'has_scope_claim'       => $has_scope_claim,
			'has_comparison_claim'  => $has_comparison_claim,
			'has_causal_transition' => $has_causal_transition,
			'has_structural_glue'   => $this->editor_text_has_structural_glue( $text ),
		);
	}

	private function editor_paragraph_check_local_output( string $selected_text, bool $hosted_ai_empty = true ): array {
		$text     = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $selected_text ) ) ?: '' );
		$length   = function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );
		$punctuation_count = preg_match_all( '/[。！？!?；;]/u', $text );
		$signals = $this->editor_paragraph_check_signal_profile( $text, $length, (int) $punctuation_count );

		if ( $signals['has_structural_glue'] ) {
			$clarity = __( '选中段落疑似存在标题词、选项标签或短语串与正文黏连的问题，建议先检查分隔、标点、列表或小标题结构。', 'npcink-workflow-toolbox' );
		} elseif ( $signals['long_or_dense'] && $signals['has_metric_claim'] ) {
			$clarity = __( '段落信息量偏高，建议人工检查测试条件、对比结论和适用边界是否需要拆开呈现，避免读者把多个结论混在一起。', 'npcink-workflow-toolbox' );
		} elseif ( $signals['long_or_dense'] ) {
			$clarity = __( '段落信息量偏高，建议人工检查主语、原因、结论和适用边界是否需要拆开呈现，避免读者把多个判断混在一起。', 'npcink-workflow-toolbox' );
		} else {
			$clarity = __( '段落结构基本清楚；重点检查判断对象、原因和适用边界是否已经在上下文中交代。', 'npcink-workflow-toolbox' );
		}

		$fact_gaps = $signals['has_structural_glue']
			? __( '结构黏连会影响读者判断事实边界；发布前需要确认每个标签、维度、方案、ID 或问答对应的正文是否清楚分开。', 'npcink-workflow-toolbox' )
			: ( $signals['has_performance_claim']
				? __( '包含测试、数量、速度或耗时类结论；发布前需要确认测试条件、数据规模、对比对象和结论来源，避免把单次测试写成通用事实。', 'npcink-workflow-toolbox' )
				: ( $signals['has_metric_claim']
					? __( '包含数字、ID、数量或范围类表述；发布前需要确认这些数字对应的对象、条件和来源，避免把局部证据写成通用事实。', 'npcink-workflow-toolbox' )
					: ( $signals['has_comparison_claim']
						? __( '包含比较性判断；发布前需要确认比较对象、比较条件和依据是否在上下文中明确。', 'npcink-workflow-toolbox' )
						: __( '未发现明显数字或比较结论；仍需人工确认段落中的判断是否有上下文依据。', 'npcink-workflow-toolbox' ) ) ) );

		$tone = $signals['has_scope_claim']
			? __( '语气整体偏说明性；涉及“适合/因此/场景”等判断时，建议保持审慎，不要超过已验证范围。', 'npcink-workflow-toolbox' )
			: ( $signals['has_causal_transition']
				? __( '语气整体偏推论式；建议确认原因和结论之间的关系是否足够明确。', 'npcink-workflow-toolbox' )
				: __( '语气整体中性；保持事实说明，并避免加入选中段落没有承载的新判断。', 'npcink-workflow-toolbox' ) );

		$editing_parts = array( __( '不要直接替换正文。', 'npcink-workflow-toolbox' ) );
		if ( $signals['has_structural_glue'] ) {
			$editing_parts[] = __( '优先拆开标题词、选项标签、短语串和正文说明。', 'npcink-workflow-toolbox' );
		} elseif ( $signals['has_performance_claim'] ) {
			$editing_parts[] = __( '优先核对测试条件、性能口径和对比对象。', 'npcink-workflow-toolbox' );
		} elseif ( $signals['has_metric_claim'] ) {
			$editing_parts[] = __( '优先核对数字、ID、数量口径和对应对象。', 'npcink-workflow-toolbox' );
		} elseif ( $signals['has_comparison_claim'] ) {
			$editing_parts[] = __( '优先核对比较对象和比较条件。', 'npcink-workflow-toolbox' );
		} else {
			$editing_parts[] = __( '优先核对该段判断是否有上下文依据。', 'npcink-workflow-toolbox' );
		}
		if ( $signals['has_scope_claim'] ) {
			$editing_parts[] = __( '必要时缩小适用范围，或把原因和边界分开审阅。', 'npcink-workflow-toolbox' );
		} else {
			$editing_parts[] = __( '必要时补充限定条件，或把原因和结论分开审阅。', 'npcink-workflow-toolbox' );
		}
		$editing = implode( '', $editing_parts );

		return array(
			'clarity_check'       => $clarity,
			'fact_gaps'           => $fact_gaps,
			'tone_consistency'    => $tone,
			'editing_suggestions' => $editing,
			'assumptions_to_verify' => $hosted_ai_empty
				? __( '托管 AI 本次未返回建议，以上为本地兜底检查；仍以人工编辑和原始测试记录为准。', 'npcink-workflow-toolbox' )
				: __( '本地复核只检查结构、事实口径和语气风险；托管 AI 建议仍需人工审阅。', 'npcink-workflow-toolbox' ),
			'signal_profile'      => $signals,
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
					'label'          => 0 === $index ? __( 'AI recommended title', 'npcink-workflow-toolbox' ) : __( 'AI title option', 'npcink-workflow-toolbox' ),
					'value'          => (string) ( $candidate['value'] ?? '' ),
					'reason'         => '' !== (string) ( $candidate['reason'] ?? '' ) ? (string) $candidate['reason'] : __( 'Generated by hosted AI from the current title, excerpt, and draft context. Review before applying.', 'npcink-workflow-toolbox' ),
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
			$issues[] = __( '标题过短，可能缺少具体对象。', 'npcink-workflow-toolbox' );
		}
		if ( $length > 80 ) {
			$score   -= 28;
			$issues[] = __( '标题超过 80 个字符，可能不适合编辑器标题字段。', 'npcink-workflow-toolbox' );
		}
		if ( '' !== $current_title && strtolower( $value ) === strtolower( $current_title ) ) {
			$score   -= 14;
			$issues[] = __( '标题与当前标题完全相同。', 'npcink-workflow-toolbox' );
		}
		if ( 1 === preg_match( '/(?:草稿|本文|这篇文章|该文章|this\s+(?:article|post|draft)|标题建议|title suggestion)/iu', $value ) ) {
			$score   -= 35;
			$issues[] = __( '包含文章自指或编辑提示词。', 'npcink-workflow-toolbox' );
		}
		if ( false !== strpos( $value, '```' ) || false !== strpos( $value, '{' ) || false !== strpos( $value, '}' ) ) {
			$score   -= 40;
			$issues[] = __( '包含格式或 JSON 泄漏。', 'npcink-workflow-toolbox' );
		}
		if ( 1 === preg_match( '/(?:必看|震惊|最强|最好|终极|保证|100%|排名第一)/u', $value ) ) {
			$score   -= 18;
			$issues[] = __( '标题可能过度营销或包含高风险承诺。', 'npcink-workflow-toolbox' );
		}

		$status = 'good';
		if ( $score < 70 ) {
			$status = 'review';
		}
		if ( $score < 55 ) {
			$status = 'weak';
		}

		if ( empty( $issues ) ) {
			$issues[] = __( '通过长度、自指套话和基础标题质量检查。', 'npcink-workflow-toolbox' );
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
			'label'                  => sanitize_text_field( (string) ( $args['label'] ?? __( 'Recommendation candidate', 'npcink-workflow-toolbox' ) ) ),
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
		if ( is_array( $args['target_ref'] ?? null ) ) {
			$target_ref = $args['target_ref'];
			$candidate['target_ref'] = array(
				'post_id' => absint( $target_ref['post_id'] ?? 0 ),
				'title'   => sanitize_text_field( (string) ( $target_ref['title'] ?? '' ) ),
				'url'     => esc_url_raw( (string) ( $target_ref['url'] ?? '' ) ),
			);
		}
		if ( '' !== (string) ( $args['anchor_or_context'] ?? '' ) ) {
			$candidate['anchor_or_context'] = sanitize_text_field( (string) $args['anchor_or_context'] );
		}
		if ( '' !== (string) ( $args['evidence_note'] ?? '' ) ) {
			$candidate['evidence_note'] = sanitize_text_field( (string) $args['evidence_note'] );
		}
		if ( '' !== (string) ( $args['owner_label'] ?? '' ) ) {
			$candidate['owner_label'] = sanitize_key( (string) $args['owner_label'] );
		}
		if ( '' !== (string) ( $args['next_safe_action'] ?? '' ) ) {
			$candidate['next_safe_action'] = sanitize_key( (string) $args['next_safe_action'] );
		}

		return $candidate;
	}

	private function editor_recommendation_quality_notes( array $items ): array {
		$notes = array();
		foreach ( $items as $item ) {
			$issues = is_array( $item['quality_issues'] ?? null ) ? $item['quality_issues'] : array();
			$notes[] = array(
				'name'   => (string) ( $item['label'] ?? __( 'Recommendation candidate', 'npcink-workflow-toolbox' ) ),
				'status' => sanitize_key( (string) ( $item['quality_status'] ?? 'review' ) ),
				'detail' => sprintf(
					/* translators: 1: quality score, 2: quality notes. */
					__( 'Quality score %1$d. %2$s', 'npcink-workflow-toolbox' ),
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
					'label'          => 'category' === $taxonomy ? __( 'Existing category', 'npcink-workflow-toolbox' ) : __( 'Existing tag', 'npcink-workflow-toolbox' ),
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
			$quality_issues[] = __( '匹配当前草稿中的标题、摘要或正文词。', 'npcink-workflow-toolbox' );
		}
		if ( in_array( 'title_term_name_match', $match_signals, true ) ) {
			$quality_issues[] = __( '词条名称在标题中完整出现，优先级更高。', 'npcink-workflow-toolbox' );
		}
		if ( in_array( 'slug_alias_match', $match_signals, true ) ) {
			$quality_issues[] = __( '词条 slug 或别名与当前编辑上下文匹配。', 'npcink-workflow-toolbox' );
		}
		if ( in_array( 'related_site_knowledge_term', $match_signals, true ) ) {
			$quality_issues[] = __( '历史相关文章使用过该词汇，可作为站内词库证据。', 'npcink-workflow-toolbox' );
		}
		if ( in_array( 'description_only_match', $match_signals, true ) ) {
			$quality_score -= 20;
			$quality_issues[] = __( '仅描述字段匹配，避免把弱说明文字当作强分类依据。', 'npcink-workflow-toolbox' );
		}
		if ( in_array( 'low_specificity_match', $match_signals, true ) ) {
			$quality_score -= 15;
			$quality_issues[] = __( '只有一个较弱 token 匹配，需人工确认是否为标题党或泛化词。', 'npcink-workflow-toolbox' );
		}
		if ( empty( $quality_issues ) ) {
			$quality_issues[] = __( '仅作为现有 WordPress 词条候选，采用人工审查。', 'npcink-workflow-toolbox' );
		}
		if ( 0 === absint( $related_context['source_count'] ?? 0 ) && ! in_array( 'current_draft_match', $match_signals, true ) ) {
			$quality_score -= 15;
			$quality_issues[] = __( '缺少当前草稿或历史文章的强匹配证据。', 'npcink-workflow-toolbox' );
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
			'empty_message'          => __( 'New taxonomy creation is deferred. Use existing categories and tags in this stage.', 'npcink-workflow-toolbox' ),
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
				'name'                  => __( 'Generate and apply summary', 'npcink-workflow-toolbox' ),
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
				'reason'                => __( 'Core may auto-approve a selected summary when it is derived from the supplied draft context and the editor can edit the target post.', 'npcink-workflow-toolbox' ),
			),
			array(
				'id'                    => 'recommend_apply_tags',
				'name'                  => __( 'Recommend and apply tags', 'npcink-workflow-toolbox' ),
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
				'reason'                => __( 'Existing tag assignments can be proposed for Core auto-approval because Toolbox returns WordPress term ids and does not create taxonomy state.', 'npcink-workflow-toolbox' ),
			),
			array(
				'id'                    => 'recommend_categories',
				'name'                  => __( 'Recommend categories', 'npcink-workflow-toolbox' ),
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
				'reason'                => __( 'Categories affect site structure, so Toolbox recommends existing categories by default and leaves any assignment policy to Core.', 'npcink-workflow-toolbox' ),
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
				__( 'Use Generate and apply summary when Core policy can auto-approve the selected summary layer.', 'npcink-workflow-toolbox' ),
				__( 'Use Recommend and apply tags for existing tag ids returned by Toolbox.', 'npcink-workflow-toolbox' ),
				__( 'Use Recommend categories as review-first guidance unless Core explicitly allows category auto-assignment.', 'npcink-workflow-toolbox' ),
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
					'label'         => __( 'Short summary', 'npcink-workflow-toolbox' ),
					'limit'         => '160_chars',
					'value'         => sanitize_text_field( wp_html_excerpt( $base, 160, '' ) ),
					'reason'        => __( 'Use as an excerpt-style candidate after checking related Site Knowledge evidence for duplicate coverage and term fit.', 'npcink-workflow-toolbox' ),
					'context_use'   => 'draft_grounded_related_context_checked',
					'evidence_refs' => $summary_evidence_refs,
				),
				array(
					'id'            => 'standard_summary',
					'label'         => __( 'Standard summary', 'npcink-workflow-toolbox' ),
					'limit'         => '2_3_sentences',
					'value'         => sanitize_text_field( wp_trim_words( $base, 45, '' ) ),
					'reason'        => __( 'Use for editor review where a slightly fuller article summary is useful, while keeping related content as context evidence rather than new factual material.', 'npcink-workflow-toolbox' ),
					'context_use'   => 'draft_grounded_related_context_checked',
					'evidence_refs' => $summary_evidence_refs,
				),
				array(
					'id'            => 'seo_meta_description',
					'label'         => __( 'SEO meta description', 'npcink-workflow-toolbox' ),
					'limit'         => '155_chars',
					'value'         => sanitize_text_field( wp_html_excerpt( $base, 155, '' ) ),
					'reason'        => __( 'Use only as a Core-governed SEO/meta proposal candidate after comparing the article with related public content.', 'npcink-workflow-toolbox' ),
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
				'label'          => $is_first ? __( 'AI recommended excerpt', 'npcink-workflow-toolbox' ) : __( 'AI alternate excerpt', 'npcink-workflow-toolbox' ),
				'limit'         => '50_160_zh_chars',
				'value'          => sanitize_text_field( (string) ( $candidate['value'] ?? '' ) ),
				'reason'         => '' !== (string) ( $candidate['reason'] ?? '' ) ? (string) $candidate['reason'] : __( 'Generated by hosted AI from the current title, excerpt, and draft body. Review before applying.', 'npcink-workflow-toolbox' ),
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
			$issues[] = __( '摘要偏短，可能没有覆盖足够信息。', 'npcink-workflow-toolbox' );
		}
		if ( 1 === preg_match( '/(?:草稿|本文|这篇文章|该文章|本文说明|本文介绍|这篇草稿|this\s+(?:article|post|draft))/iu', $value ) ) {
			$score   -= 35;
			$issues[] = __( '包含草稿或文章自指套话。', 'npcink-workflow-toolbox' );
		}
		if ( 1 === preg_match( '/^(?:面向|适合|需要|想要|对于)/u', $value ) ) {
			$score   -= 4;
			$issues[] = __( '开头较模板化。', 'npcink-workflow-toolbox' );
		}

		$core_subject = $this->editor_ai_summary_coverage_text( $coverage['core_subject'] ?? '' );
		if ( $this->editor_ai_summary_coverage_group_missing( $source, $value, $core_subject ) ) {
			$score   -= 18;
			$issues[] = __( '可能缺少核心对象。', 'npcink-workflow-toolbox' );
		}

		$title_positioning = $this->editor_ai_summary_coverage_text( $coverage['title_positioning'] ?? '' );
		if ( $this->editor_ai_summary_coverage_group_missing( $source, $value, $title_positioning ) ) {
			$score   -= 10;
			$issues[] = __( '可能遗漏标题中的关键定位。', 'npcink-workflow-toolbox' );
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
			$issues[] = __( '可能遗漏一个或多个必须覆盖点。', 'npcink-workflow-toolbox' );
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
			$issues[] = __( '可能只覆盖了正文局部工具、方法或流程分支。', 'npcink-workflow-toolbox' );
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
					__( '可能遗漏关键工具或方法：%s。', 'npcink-workflow-toolbox' ),
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
			$issues[] = __( '通过长度、自指套话和覆盖检查。', 'npcink-workflow-toolbox' );
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
				'name'   => (string) ( $item['label'] ?? __( 'Summary candidate', 'npcink-workflow-toolbox' ) ),
				'status' => sanitize_key( (string) ( $item['quality_status'] ?? 'review' ) ),
				'detail' => sprintf(
					/* translators: 1: quality score, 2: quality notes. */
					__( 'Quality score %1$d. %2$s', 'npcink-workflow-toolbox' ),
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
					'name'   => __( 'Draft query overlap', 'npcink-workflow-toolbox' ),
					'weight' => 'high',
					'detail' => __( 'Match against title, excerpt, selected text, and draft body tokens.', 'npcink-workflow-toolbox' ),
				),
				array(
					'name'   => __( 'Existing taxonomy vocabulary', 'npcink-workflow-toolbox' ),
					'weight' => 'high',
					'detail' => __( 'Prefer existing WordPress categories and tags; defer new vocabulary creation to taxonomy governance.', 'npcink-workflow-toolbox' ),
				),
				array(
					'name'   => __( 'Site Knowledge similarity', 'npcink-workflow-toolbox' ),
					'weight' => 'medium',
					'detail' => __( 'Use related public content to avoid duplicate coverage and borrow proven term patterns.', 'npcink-workflow-toolbox' ),
				),
				array(
					'name'   => __( 'Discoverability context', 'npcink-workflow-toolbox' ),
					'weight' => 'medium',
					'detail' => __( 'Check saved SEO/AEO/GEO guidance and Cloud web-search evidence before recommending metadata.', 'npcink-workflow-toolbox' ),
				),
			),
			'dedupe_policy'          => array(
				__( 'Normalize candidate labels by case, punctuation, and whitespace before review.', 'npcink-workflow-toolbox' ),
				__( 'Treat near-synonyms, plural/singular variants, and translated duplicates as taxonomy-drift risks.', 'npcink-workflow-toolbox' ),
				__( 'Keep broad categories stable and use tags for narrower topic facets.', 'npcink-workflow-toolbox' ),
			),
			'evidence_requirements'  => array(
				__( 'Each accepted category or tag should have a reason tied to draft text, existing taxonomy, Site Knowledge, or search evidence.', 'npcink-workflow-toolbox' ),
				__( 'Fresh external search is useful for factual or current topics, but it should not override the supplied article draft.', 'npcink-workflow-toolbox' ),
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
					'detail' => __( 'Track how many summary, category, and tag suggestions an editor accepts after review.', 'npcink-workflow-toolbox' ),
				),
				array(
					'name'   => 'summary_edit_distance',
					'detail' => __( 'Compare AI/fallback summaries with the final reviewed summary to detect overbroad or weak suggestions.', 'npcink-workflow-toolbox' ),
				),
				array(
					'name'   => 'taxonomy_gap_deferral_rate',
					'detail' => __( 'Track cases where no existing term fits so a later taxonomy governance workflow can review them.', 'npcink-workflow-toolbox' ),
				),
				array(
					'name'   => 'duplicate_topic_review',
					'detail' => __( 'Use related Site Knowledge results to flag whether the article overlaps existing public content.', 'npcink-workflow-toolbox' ),
				),
				array(
					'name'   => 'evidence_coverage',
					'detail' => __( 'Check whether accepted suggestions cite draft, taxonomy, Site Knowledge, or search evidence.', 'npcink-workflow-toolbox' ),
				),
			),
		);
	}

	private function editor_taxonomy_term_candidates( array $context, string $query, array $related_content = array() ): array {
		$input = array(
			'post_id'         => absint( $context['post_id'] ?? 0 ),
			'post_type'       => sanitize_key( (string) ( $context['post_type'] ?? 'post' ) ),
			'taxonomy'        => 'both',
			'query'           => sanitize_textarea_field( $query ),
			'title'           => sanitize_text_field( (string) ( $context['title'] ?? '' ) ),
			'excerpt'         => sanitize_textarea_field( (string) ( $context['excerpt'] ?? '' ) ),
			'content_text'    => sanitize_textarea_field( (string) ( $context['content_text'] ?? '' ) ),
			'selected_text'   => sanitize_textarea_field( (string) ( $context['selected_text'] ?? '' ) ),
			'selected_block_text' => sanitize_textarea_field( (string) ( $context['selected_block_text'] ?? '' ) ),
			'user_instruction' => sanitize_textarea_field( (string) ( $context['user_instruction'] ?? '' ) ),
			'category_limit'  => 5,
			'tag_limit'       => 8,
			'candidate_limit' => 10,
			'review_set_limit' => 8,
		);
		if ( 0 >= (int) $input['post_id'] ) {
			unset( $input['post_id'] );
		}

		$related_term_evidence = $this->editor_related_content_term_evidence( $related_content );
		if ( array() !== $related_term_evidence ) {
			$input['related_term_evidence'] = array_values( $related_term_evidence );
		}

		$result = $this->editor_toolkit_taxonomy_suggestions( $input );
		if ( is_wp_error( $result ) ) {
			return $this->empty_toolkit_taxonomy_term_candidates( $result, $related_term_evidence );
		}

		$data = is_array( $result['data'] ?? null ) ? $result['data'] : $result;
		$taxonomy_terms = is_array( $data['taxonomy_terms'] ?? null ) ? $data['taxonomy_terms'] : array();
		if ( empty( $taxonomy_terms['candidate_type'] ) || 'taxonomy_tag_candidates' !== (string) $taxonomy_terms['candidate_type'] ) {
			return $this->empty_toolkit_taxonomy_term_candidates(
				new WP_Error(
					'npcink_toolbox_taxonomy_toolkit_invalid_artifact',
					__( 'The Toolkit taxonomy suggestion ability returned an invalid artifact.', 'npcink-workflow-toolbox' ),
					array( 'status' => 500 )
				),
				$related_term_evidence
			);
		}

		$taxonomy_terms['source_ability_id'] = 'npcink-abilities-toolkit/suggest-post-taxonomy-terms';
		$taxonomy_terms['ranking_context']['related_term_policy'] = 'ranking_evidence_only_no_term_creation_or_assignment';
		$review_set_result = $this->editor_toolkit_taxonomy_review_set( $input );
		if ( is_wp_error( $review_set_result ) ) {
			$taxonomy_terms['taxonomy_tag_review_set'] = $this->editor_taxonomy_review_set_from_suggestions( $taxonomy_terms, $review_set_result );
		} else {
			$review_set_data = is_array( $review_set_result['data'] ?? null ) ? $review_set_result['data'] : $review_set_result;
			$taxonomy_terms['taxonomy_tag_review_set'] = is_array( $review_set_data ) && 'taxonomy_tag_review_set' === (string) ( $review_set_data['artifact_type'] ?? '' )
				? $review_set_data
				: $this->editor_taxonomy_review_set_from_suggestions(
					$taxonomy_terms,
					new WP_Error(
						'npcink_toolbox_taxonomy_review_set_invalid_artifact',
						__( 'The Toolkit taxonomy review-set ability returned an invalid artifact.', 'npcink-workflow-toolbox' ),
						array( 'status' => 500 )
					)
				);
		}

		return $taxonomy_terms;
	}

	private function editor_toolkit_taxonomy_review_set( array $input ) {
		$ability_id = 'npcink-abilities-toolkit/build-taxonomy-tag-review-set';
		if ( ! function_exists( 'npcink_abilities_toolkit_get_registered' ) ) {
			return new WP_Error(
				'npcink_toolbox_taxonomy_review_set_toolkit_unavailable',
				__( 'Npcink Abilities Toolkit is required to build taxonomy review sets.', 'npcink-workflow-toolbox' ),
				array( 'status' => 503 )
			);
		}

		$registered = npcink_abilities_toolkit_get_registered();
		$definition = is_array( $registered[ $ability_id ] ?? null ) ? $registered[ $ability_id ] : array();
		$callback   = $definition['execute_callback'] ?? null;
		if ( ! is_callable( $callback ) ) {
			return new WP_Error(
				'npcink_toolbox_taxonomy_review_set_toolkit_unavailable',
				__( 'The Toolkit taxonomy review-set ability is not currently callable.', 'npcink-workflow-toolbox' ),
				array( 'status' => 503 )
			);
		}

		$result = call_user_func( $callback, $input );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( ! is_array( $result ) ) {
			return new WP_Error(
				'npcink_toolbox_taxonomy_review_set_invalid_response',
				__( 'The Toolkit taxonomy review-set ability returned an invalid response.', 'npcink-workflow-toolbox' ),
				array( 'status' => 500 )
			);
		}

		return $result;
	}

	private function editor_toolkit_taxonomy_suggestions( array $input ) {
		$ability_id = 'npcink-abilities-toolkit/suggest-post-taxonomy-terms';
		if ( ! function_exists( 'npcink_abilities_toolkit_get_registered' ) ) {
			return new WP_Error(
				'npcink_toolbox_taxonomy_toolkit_unavailable',
				__( 'Npcink Abilities Toolkit is required to build taxonomy suggestions.', 'npcink-workflow-toolbox' ),
				array( 'status' => 503 )
			);
		}

		$registered = npcink_abilities_toolkit_get_registered();
		$definition = is_array( $registered[ $ability_id ] ?? null ) ? $registered[ $ability_id ] : array();
		$callback   = $definition['execute_callback'] ?? null;
		if ( ! is_callable( $callback ) ) {
			return new WP_Error(
				'npcink_toolbox_taxonomy_toolkit_unavailable',
				__( 'The Toolkit taxonomy suggestion ability is not currently callable.', 'npcink-workflow-toolbox' ),
				array( 'status' => 503 )
			);
		}

		$result = call_user_func( $callback, $input );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( ! is_array( $result ) ) {
			return new WP_Error(
				'npcink_toolbox_taxonomy_toolkit_invalid_response',
				__( 'The Toolkit taxonomy suggestion ability returned an invalid response.', 'npcink-workflow-toolbox' ),
				array( 'status' => 500 )
			);
		}

		return $result;
	}

	private function editor_taxonomy_review_set_from_suggestions( array $taxonomy_terms, WP_Error $fallback_reason ): array {
		$items            = is_array( $taxonomy_terms['items'] ?? null ) ? array_values( array_filter( $taxonomy_terms['items'], 'is_array' ) ) : array();
		$review_set_limit = 8;
		$selected         = array();
		$blocked          = array();

		foreach ( $items as $item ) {
			$quality = $this->editor_taxonomy_candidate_quality( $item );
			$row     = array(
				'candidate_id'               => ( 'category' === (string) ( $item['taxonomy'] ?? '' ) ? 'category_' : 'tag_' ) . absint( $item['term_id'] ?? 0 ),
				'candidate_contract'         => 'taxonomy_tag_review_candidate.v1',
				'taxonomy'                   => sanitize_key( (string) ( $item['taxonomy'] ?? '' ) ),
				'term_id'                    => absint( $item['term_id'] ?? 0 ),
				'name'                       => sanitize_text_field( (string) ( $item['name'] ?? '' ) ),
				'slug'                       => sanitize_title( (string) ( $item['slug'] ?? '' ) ),
				'score'                      => is_numeric( $item['score'] ?? null ) ? (float) $item['score'] : 0.0,
				'quality'                    => $quality,
				'reason'                     => sanitize_text_field( (string) ( $item['reason'] ?? '' ) ),
				'evidence_refs'              => is_array( $item['evidence_refs'] ?? null ) ? array_values( array_map( 'sanitize_text_field', $item['evidence_refs'] ) ) : array(),
				'proposed_action'            => 'append_existing_term',
				'needs_operator_review'      => true,
				'direct_wordpress_write'     => false,
				'term_creation_allowed'      => false,
				'term_assignment_authorized' => false,
			);
			if ( '' === $row['name'] || 0 >= $row['term_id'] ) {
				$row['blocked_reason'] = 'invalid_existing_term_candidate';
				$blocked[] = $row;
				continue;
			}
			if ( 'weak' === (string) $quality['status'] ) {
				$row['blocked_reason'] = 'weak_taxonomy_evidence';
				$blocked[] = $row;
				continue;
			}
			if ( count( $selected ) >= $review_set_limit ) {
				$row['blocked_reason'] = 'review_set_limit_reached';
				$blocked[] = $row;
				continue;
			}
			$row['review_status'] = 'good' === (string) $quality['status'] ? 'ready_for_review' : 'review_recommended';
			$selected[] = $row;
		}

		return array(
			'contract_version'            => 'taxonomy_tag_review_set.v1',
			'artifact_type'               => 'taxonomy_tag_review_set',
			'mode'                        => 'governed_review_set',
			'write_posture'               => 'suggestion_only',
			'final_write_path'            => 'core_proposal_required',
			'direct_wordpress_write'      => false,
			'proposal_created'            => false,
			'execution_created'           => false,
			'commit_execution'            => false,
			'source_ability_id'           => 'npcink-abilities-toolkit/suggest-post-taxonomy-terms',
			'preferred_source_ability_id' => 'npcink-abilities-toolkit/build-taxonomy-tag-review-set',
			'runtime_owner'               => 'npcink-workflow-toolbox',
			'fallback_reason'             => sanitize_key( $fallback_reason->get_error_code() ),
			'fallback_message'            => sanitize_text_field( $fallback_reason->get_error_message() ),
			'review_set_limit'            => $review_set_limit,
			'eligibility_summary'         => array(
				'scanned'  => count( $items ),
				'selected' => count( $selected ),
				'blocked'  => count( $blocked ),
			),
			'selected_items'              => $selected,
			'blocked_items'               => $blocked,
			'safety'                      => array(
				'term_creation_allowed'    => false,
				'term_assignment_allowed'  => false,
				'proposal_created'         => false,
				'direct_wordpress_write'   => false,
				'provider_runtime_used'    => false,
				'cloud_runtime_dependency' => false,
			),
			'handoff'                     => array(
				'accepted_selection_target' => 'npcink-abilities-toolkit/build-content-metadata-apply-plan',
				'term_assignment_target'    => 'npcink-abilities-toolkit/set-post-terms',
				'final_write_path'          => 'core_proposal_required',
				'operator_review_required'  => true,
			),
		);
	}

	private function empty_toolkit_taxonomy_term_candidates( WP_Error $error, array $related_term_evidence ): array {
		return array(
			'candidate_type'         => 'taxonomy_tag_candidates',
			'write_posture'          => 'suggestion_only',
			'direct_wordpress_write' => false,
			'source_ability_id'      => 'npcink-abilities-toolkit/suggest-post-taxonomy-terms',
			'toolkit_required'       => true,
			'error_code'             => sanitize_key( $error->get_error_code() ),
			'error_message'          => sanitize_text_field( $error->get_error_message() ),
			'ranking_context'        => array(
				'draft_query_overlap'          => true,
				'related_content_terms'        => array() !== $related_term_evidence,
				'related_term_evidence_count' => count( $related_term_evidence ),
				'related_term_policy'         => 'ranking_evidence_only_no_term_creation_or_assignment',
			),
			'items'                  => array(),
		);
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

	/**
	 * Returns the exact preview input contract or rejects legacy/unknown fields.
	 *
	 * watermark_attachment_id is a WordPress-local upload selector. It is removed
	 * before the canonical Toolkit ability input is dispatched.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	private function media_derivative_preview_input( WP_REST_Request $request ) {
		$input = $request->get_param( 'input' );
		$input = is_array( $input ) ? $input : array();

		$legacy_fields = array( 'target_format', 'max_width', 'watermark_enabled' );
		foreach ( $legacy_fields as $legacy_field ) {
			if ( array_key_exists( $legacy_field, $input ) ) {
				return new WP_Error(
					'npcink_toolbox_media_derivative_preview_legacy_field',
					__( 'The media derivative preview input contains a removed legacy field.', 'npcink-workflow-toolbox' ),
					array( 'status' => 400, 'field' => $legacy_field )
				);
			}
		}

		$allowed_fields = array(
			'attachment_id',
			'target_max_width',
			'large_file_threshold_bytes',
			'preferred_format',
			'quality',
			'crop',
			'watermark',
			'watermark_attachment_id',
		);
		foreach ( array_keys( $input ) as $field ) {
			if ( ! is_string( $field ) || ! in_array( $field, $allowed_fields, true ) ) {
				return new WP_Error(
					'npcink_toolbox_media_derivative_preview_unknown_field',
					__( 'The media derivative preview input contains an unknown field.', 'npcink-workflow-toolbox' ),
					array( 'status' => 400, 'field' => sanitize_key( (string) $field ) )
				);
			}
		}

		$nested_fields = array(
			'crop'      => array( 'type', 'aspect_ratio', 'position' ),
			'watermark' => array( 'type', 'artifact_id', 'text', 'position', 'opacity', 'scale_percent', 'font_size', 'color', 'background', 'margin_px' ),
		);
		foreach ( $nested_fields as $parent => $allowed_nested_fields ) {
			if ( ! array_key_exists( $parent, $input ) ) {
				continue;
			}
			if ( ! is_array( $input[ $parent ] ) ) {
				return new WP_Error(
					'npcink_toolbox_media_derivative_preview_invalid_field',
					__( 'The media derivative preview input contains an invalid field value.', 'npcink-workflow-toolbox' ),
					array( 'status' => 400, 'field' => $parent )
				);
			}
			foreach ( array_keys( $input[ $parent ] ) as $nested_field ) {
				if ( ! is_string( $nested_field ) || ! in_array( $nested_field, $allowed_nested_fields, true ) ) {
					return new WP_Error(
						'npcink_toolbox_media_derivative_preview_unknown_field',
						__( 'The media derivative preview input contains an unknown field.', 'npcink-workflow-toolbox' ),
						array( 'status' => 400, 'field' => $parent . '.' . sanitize_key( (string) $nested_field ) )
					);
				}
			}
		}

		$watermark               = is_array( $input['watermark'] ?? null ) ? $input['watermark'] : array();
		$watermark_type          = ! empty( $watermark ) ? sanitize_key( (string) ( $watermark['type'] ?? 'image' ) ) : '';
		$watermark_attachment_id = absint( $input['watermark_attachment_id'] ?? 0 );
		if ( 'image' === $watermark_type && $watermark_attachment_id <= 0 ) {
			return new WP_Error(
				'npcink_toolbox_media_derivative_preview_watermark_attachment_required',
				__( 'Image watermark previews require a configured local watermark attachment.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400, 'field' => 'watermark_attachment_id' )
			);
		}
		if ( $watermark_attachment_id > 0 && 'image' !== $watermark_type ) {
			return new WP_Error(
				'npcink_toolbox_media_derivative_preview_watermark_attachment_unexpected',
				__( 'A local watermark attachment is allowed only for an image watermark preview.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400, 'field' => 'watermark_attachment_id' )
			);
		}

		$input = map_deep( $input, 'sanitize_text_field' );
		$input['attachment_id'] = absint( $input['attachment_id'] ?? 0 );
		if ( isset( $input['watermark_attachment_id'] ) ) {
			$input['watermark_attachment_id'] = absint( $input['watermark_attachment_id'] );
		}
		if ( isset( $input['target_max_width'] ) ) {
			$input['target_max_width'] = absint( $input['target_max_width'] );
		}
		if ( isset( $input['large_file_threshold_bytes'] ) ) {
			$input['large_file_threshold_bytes'] = absint( $input['large_file_threshold_bytes'] );
		}
		if ( isset( $input['preferred_format'] ) ) {
			$input['preferred_format'] = sanitize_key( (string) $input['preferred_format'] );
		}
		if ( isset( $input['quality'] ) ) {
			$input['quality'] = max( 1, min( 100, absint( $input['quality'] ) ) );
		}

		return $input;
	}

	private function run_toolkit_ability( string $ability_id, array $input ) {
		if ( ! function_exists( 'npcink_abilities_toolkit_get_registered' ) ) {
			return new WP_Error(
				'npcink_toolbox_media_derivative_toolkit_unavailable',
				__( 'Npcink Abilities Toolkit is required to build the media derivative request.', 'npcink-workflow-toolbox' ),
				array( 'status' => 503 )
			);
		}

		$registered = npcink_abilities_toolkit_get_registered();
		$definition = is_array( $registered[ $ability_id ] ?? null ) ? $registered[ $ability_id ] : array();
		$callback   = $definition['execute_callback'] ?? null;
		if ( ! is_callable( $callback ) ) {
			return new WP_Error(
				'npcink_toolbox_media_derivative_toolkit_unavailable',
				__( 'The Toolkit media derivative request ability is not callable.', 'npcink-workflow-toolbox' ),
				array( 'status' => 503 )
			);
		}

		$result = call_user_func( $callback, $input );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return is_array( $result ) ? $result : new WP_Error(
			'npcink_toolbox_media_derivative_toolkit_invalid_response',
			__( 'The Toolkit media derivative request ability returned an invalid response.', 'npcink-workflow-toolbox' ),
			array( 'status' => 502 )
		);
	}

	private function media_derivative_attachment_descriptor( int $attachment_id ) {
		$path = $attachment_id > 0 ? get_attached_file( $attachment_id ) : '';
		if ( ! is_string( $path ) || '' === $path || ! is_readable( $path ) ) {
			return new WP_Error(
				'npcink_toolbox_media_derivative_file_unreadable',
				__( 'The selected attachment file is not readable for the preview upload.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400, 'attachment_id' => $attachment_id )
			);
		}

		return array(
			'path'      => $path,
			'filename'  => sanitize_file_name( basename( $path ) ),
			'mime_type' => sanitize_text_field( (string) get_post_mime_type( $attachment_id ) ),
		);
	}

	private function media_derivative_cloud_addon_unavailable(): WP_Error {
		return new WP_Error(
			'npcink_toolbox_media_derivative_cloud_addon_unavailable',
			__( 'Npcink Cloud Addon is required for media derivative preview transport.', 'npcink-workflow-toolbox' ),
			array( 'status' => 503, 'required_plugin' => 'npcink-cloud-addon' )
		);
	}

	private function media_derivative_object_param( WP_REST_Request $request, string $key ): array {
		$value = $request->get_param( $key );
		return is_array( $value ) ? $value : array();
	}

	private function media_derivative_local_review_projection( array $cloud_projection ): array {
		$artifact = is_array( $cloud_projection['artifact'] ?? null ) ? $cloud_projection['artifact'] : array();
		$expected_keys = array(
			'artifact_id',
			'artifact_reference',
			'expires_at',
			'suggested_filename',
			'filename_basis',
			'mime_type',
			'format',
			'width',
			'height',
			'filesize_bytes',
			'checksum',
			'processing_warnings',
		);
		if ( count( $artifact ) !== count( $expected_keys ) || array() !== array_diff( $expected_keys, array_keys( $artifact ) ) || array() !== array_diff( array_keys( $artifact ), $expected_keys ) ) {
			return array();
		}

		$artifact_id = (string) $artifact['artifact_id'];
		$expires_at  = (string) $artifact['expires_at'];
		$expires_ts  = self::media_derivative_strict_timestamp( $expires_at );
		$format      = (string) $artifact['format'];
		$mime_type   = (string) $artifact['mime_type'];
		$mime_by_format = array(
			'avif' => 'image/avif',
			'jpeg' => 'image/jpeg',
			'png'  => 'image/png',
			'webp' => 'image/webp',
		);
		$artifact_reference = $artifact['artifact_reference'];
		$filename_basis     = $artifact['filename_basis'];
		if (
			! is_string( $artifact['artifact_id'] )
			|| 1 !== preg_match( '/^art_[0-9a-f]{32}$/', $artifact_id )
			|| ! is_string( $artifact['expires_at'] )
			|| ! is_string( $artifact['mime_type'] )
			|| ! is_string( $artifact['format'] )
			|| ! is_string( $artifact['checksum'] )
			|| ! is_string( $artifact['suggested_filename'] )
			|| ! is_array( $artifact_reference )
			|| array( 'artifact_id' ) !== array_keys( $artifact_reference )
			|| $artifact_id !== (string) ( $artifact_reference['artifact_id'] ?? '' )
			|| ! is_array( $filename_basis )
			|| 3 !== count( $filename_basis )
			|| array() !== array_diff( array( 'owner', 'strategy', 'final_sanitize_unique_required' ), array_keys( $filename_basis ) )
			|| array() !== array_diff( array_keys( $filename_basis ), array( 'owner', 'strategy', 'final_sanitize_unique_required' ) )
			|| 'wordpress_write_ability_final' !== ( $filename_basis['owner'] ?? null )
			|| 'format_checksum' !== ( $filename_basis['strategy'] ?? null )
			|| true !== ( $filename_basis['final_sanitize_unique_required'] ?? null )
			|| false === $expires_ts
			|| $expires_ts <= time()
			|| ! isset( $mime_by_format[ $format ] )
			|| $mime_by_format[ $format ] !== $mime_type
			|| ! is_int( $artifact['width'] )
			|| (int) $artifact['width'] <= 0
			|| (int) $artifact['width'] > 8192
			|| ! is_int( $artifact['height'] )
			|| (int) $artifact['height'] <= 0
			|| (int) $artifact['height'] > 8192
			|| (int) $artifact['width'] * (int) $artifact['height'] > 16777216
			|| ! is_int( $artifact['filesize_bytes'] )
			|| (int) $artifact['filesize_bytes'] <= 0
			|| (int) $artifact['filesize_bytes'] > 26214400
			|| 1 !== preg_match( '/^sha256:[0-9a-f]{64}$/', (string) $artifact['checksum'] )
			|| ! is_array( $artifact['processing_warnings'] )
			|| ( ! empty( $artifact['processing_warnings'] ) && array_keys( $artifact['processing_warnings'] ) !== range( 0, count( $artifact['processing_warnings'] ) - 1 ) )
			|| count( $artifact['processing_warnings'] ) > 20
			|| '' === (string) $artifact['suggested_filename']
			|| strlen( (string) $artifact['suggested_filename'] ) > 120
			|| sanitize_file_name( (string) $artifact['suggested_filename'] ) !== (string) $artifact['suggested_filename']
		) {
			return array();
		}
		foreach ( $artifact['processing_warnings'] as $warning ) {
			if ( ! is_string( $warning ) || strlen( $warning ) > 200 || sanitize_text_field( $warning ) !== $warning ) {
				return array();
			}
		}

		$local_artifact = array(
			'artifact_id'         => $artifact_id,
			'expires_at'          => $expires_at,
			'mime_type'           => $mime_type,
			'format'              => $format,
			'width'               => (int) $artifact['width'],
			'height'              => (int) $artifact['height'],
			'filesize_bytes'      => (int) $artifact['filesize_bytes'],
			'sha256'              => substr( (string) $artifact['checksum'], 7 ),
			'suggested_filename'  => (string) $artifact['suggested_filename'],
			'filename_basis'      => $filename_basis,
			'processing_warnings' => array_values( $artifact['processing_warnings'] ),
		);

		return array(
			'endpoint' => rest_url( Plugin::REST_NAMESPACE . '/media-derivative-local-review/' . rawurlencode( $artifact_id ) ),
			'method'   => 'POST',
			'artifact' => $local_artifact,
		);
	}

	/**
	 * Parses the exact UTC RFC3339 forms emitted by Cloud without calendar normalization.
	 *
	 * @param string $value Timestamp.
	 * @return int|false
	 */
	private static function media_derivative_strict_timestamp( string $value ) {
		$utc = new \DateTimeZone( 'UTC' );
		$formats = array(
			'!Y-m-d\TH:i:s\Z'   => 'Y-m-d\TH:i:s\Z',
			'!Y-m-d\TH:i:sP'    => 'Y-m-d\TH:i:sP',
			'!Y-m-d\TH:i:s.u\Z' => 'Y-m-d\TH:i:s.u\Z',
			'!Y-m-d\TH:i:s.uP'  => 'Y-m-d\TH:i:s.uP',
		);
		foreach ( $formats as $parse_format => $roundtrip_format ) {
			$timestamp = \DateTimeImmutable::createFromFormat( $parse_format, $value, $utc );
			$errors    = \DateTimeImmutable::getLastErrors();
			if (
				false !== $timestamp
				&& ( ! is_array( $errors ) || ( 0 === (int) $errors['warning_count'] && 0 === (int) $errors['error_count'] ) )
				&& 0 === $timestamp->getOffset()
				&& $value === $timestamp->format( $roundtrip_format )
			) {
				return $timestamp->getTimestamp();
			}
		}

		return false;
	}

	/**
	 * Builds the exact local11 artifact only after fail-closed cross-field validation.
	 *
	 * WordPress REST args validate individual body fields, but cannot validate
	 * MIME/format, width/height area, or the complete descriptor together.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string,mixed>|WP_Error
	 */
	private function media_derivative_local_review_artifact_from_request( WP_REST_Request $request ) {
		$path_artifact_id   = $request->get_param( 'artifact_id' );
		$json_params        = method_exists( $request, 'get_json_params' ) ? $request->get_json_params() : array();
		$artifact           = is_array( $json_params['artifact'] ?? null ) ? $json_params['artifact'] : array();
		$expected_keys      = array(
			'artifact_id',
			'expires_at',
			'mime_type',
			'format',
			'width',
			'height',
			'filesize_bytes',
			'sha256',
			'suggested_filename',
			'filename_basis',
			'processing_warnings',
		);
		if (
			count( $artifact ) !== count( $expected_keys )
			|| array() !== array_diff( $expected_keys, array_keys( $artifact ) )
			|| array() !== array_diff( array_keys( $artifact ), $expected_keys )
		) {
			return $this->media_derivative_local_review_descriptor_invalid();
		}

		$artifact_id        = $artifact['artifact_id'];
		$expires_at         = $artifact['expires_at'];
		$mime_type          = $artifact['mime_type'];
		$format             = $artifact['format'];
		$width              = self::media_derivative_local_review_positive_integer( $artifact['width'] );
		$height             = self::media_derivative_local_review_positive_integer( $artifact['height'] );
		$filesize_bytes     = self::media_derivative_local_review_positive_integer( $artifact['filesize_bytes'] );
		$sha256             = $artifact['sha256'];
		$suggested_filename = $artifact['suggested_filename'];
		$filename_basis     = $artifact['filename_basis'];
		$processing_warnings = $artifact['processing_warnings'];
		$expires_timestamp  = is_string( $expires_at ) ? self::media_derivative_strict_timestamp( $expires_at ) : false;
		$mime_by_format     = array(
			'avif' => 'image/avif',
			'jpeg' => 'image/jpeg',
			'png'  => 'image/png',
			'webp' => 'image/webp',
		);
		if (
			! is_string( $path_artifact_id )
			|| ! is_string( $artifact_id )
			|| 1 !== preg_match( '/^art_[0-9a-f]{32}$/', $artifact_id )
			|| $path_artifact_id !== $artifact_id
			|| false === $expires_timestamp
			|| $expires_timestamp <= time()
			|| ! is_string( $mime_type )
			|| ! is_string( $format )
			|| ! isset( $mime_by_format[ $format ] )
			|| $mime_by_format[ $format ] !== $mime_type
			|| false === $width
			|| $width > 8192
			|| false === $height
			|| $height > 8192
			|| $width * $height > 16777216
			|| false === $filesize_bytes
			|| $filesize_bytes > 26214400
			|| ! is_string( $sha256 )
			|| 1 !== preg_match( '/^[0-9a-f]{64}$/', $sha256 )
			|| ! is_string( $suggested_filename )
			|| '' === $suggested_filename
			|| strlen( $suggested_filename ) > 120
			|| sanitize_file_name( $suggested_filename ) !== $suggested_filename
			|| ! is_array( $filename_basis )
			|| 3 !== count( $filename_basis )
			|| array() !== array_diff( array( 'owner', 'strategy', 'final_sanitize_unique_required' ), array_keys( $filename_basis ) )
			|| array() !== array_diff( array_keys( $filename_basis ), array( 'owner', 'strategy', 'final_sanitize_unique_required' ) )
			|| 'wordpress_write_ability_final' !== ( $filename_basis['owner'] ?? null )
			|| 'format_checksum' !== ( $filename_basis['strategy'] ?? null )
			|| true !== ( $filename_basis['final_sanitize_unique_required'] ?? null )
		) {
			return $this->media_derivative_local_review_descriptor_invalid();
		}

		if (
			! is_array( $processing_warnings )
			|| ( ! empty( $processing_warnings ) && array_keys( $processing_warnings ) !== range( 0, count( $processing_warnings ) - 1 ) )
			|| count( $processing_warnings ) > 20
		) {
			return $this->media_derivative_local_review_descriptor_invalid();
		}
		foreach ( $processing_warnings as $warning ) {
			if ( ! is_string( $warning ) || strlen( $warning ) > 200 || sanitize_text_field( $warning ) !== $warning ) {
				return $this->media_derivative_local_review_descriptor_invalid();
			}
		}

		return array(
			'artifact_id'         => $artifact_id,
			'expires_at'          => $expires_at,
			'mime_type'           => $mime_type,
			'format'              => $format,
			'width'               => $width,
			'height'              => $height,
			'filesize_bytes'      => $filesize_bytes,
			'sha256'              => $sha256,
			'suggested_filename'  => $suggested_filename,
			'filename_basis'      => array(
				'owner'                          => 'wordpress_write_ability_final',
				'strategy'                       => 'format_checksum',
				'final_sanitize_unique_required' => true,
			),
			'processing_warnings' => $processing_warnings,
		);
	}

	/**
	 * Accepts one exact positive JSON integer without coercion.
	 *
	 * @param mixed $value Raw value.
	 * @return int|false
	 */
	private static function media_derivative_local_review_positive_integer( $value ) {
		return is_int( $value ) && $value > 0 ? $value : false;
	}

	private function media_derivative_local_review_descriptor_invalid(): WP_Error {
		return new WP_Error(
			'npcink_toolbox_media_derivative_local_review_descriptor_invalid',
			__( 'Media derivative local review descriptor facts are invalid.', 'npcink-workflow-toolbox' ),
			array( 'status' => 400 )
		);
	}

	private function media_derivative_local_review_route_args(): array {
		return array(
			'artifact_id'         => array(
				'required'          => true,
				'type'              => 'string',
				'validate_callback' => static fn( $value ): bool => is_string( $value ) && 1 === preg_match( '/^art_[0-9a-f]{32}$/', $value ),
			),
			'artifact'            => array(
				'required'             => true,
				'type'                 => 'object',
				'additionalProperties' => false,
				'validate_callback'    => 'rest_validate_request_arg',
				'properties'           => array(
					'artifact_id'         => array( 'required' => true, 'type' => 'string', 'pattern' => '^art_[0-9a-f]{32}$' ),
					'expires_at'          => array( 'required' => true, 'type' => 'string' ),
					'mime_type'           => array( 'required' => true, 'type' => 'string', 'enum' => array( 'image/avif', 'image/jpeg', 'image/png', 'image/webp' ) ),
					'format'              => array( 'required' => true, 'type' => 'string', 'enum' => array( 'avif', 'jpeg', 'png', 'webp' ) ),
					'width'               => array( 'required' => true, 'type' => 'integer', 'minimum' => 1, 'maximum' => 8192 ),
					'height'              => array( 'required' => true, 'type' => 'integer', 'minimum' => 1, 'maximum' => 8192 ),
					'filesize_bytes'      => array( 'required' => true, 'type' => 'integer', 'minimum' => 1, 'maximum' => 26214400 ),
					'sha256'              => array( 'required' => true, 'type' => 'string', 'pattern' => '^[0-9a-f]{64}$' ),
					'suggested_filename'  => array( 'required' => true, 'type' => 'string', 'minLength' => 1, 'maxLength' => 120 ),
					'filename_basis'      => array(
						'required' => true,
						'type'     => 'object',
						'anyOf'    => array(
							array(
								'type'                 => 'object',
								'additionalProperties' => false,
								'required'             => array( 'owner', 'strategy', 'final_sanitize_unique_required' ),
								'properties'           => array(
									'owner'                          => array( 'type' => 'string', 'enum' => array( 'wordpress_write_ability_final' ) ),
									'strategy'                       => array( 'type' => 'string', 'enum' => array( 'format_checksum' ) ),
									'final_sanitize_unique_required' => array( 'type' => 'boolean', 'enum' => array( true ) ),
								),
							),
						),
					),
					'processing_warnings' => array(
						'required' => true,
						'type'     => 'array',
						'maxItems' => 20,
						'items'    => array( 'type' => 'string', 'maxLength' => 200 ),
					),
				),
			),
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
				__( 'Enable %s in Npcink Workflow Toolbox settings before running this tool.', 'npcink-workflow-toolbox' ),
				$label
			),
			array( 'status' => 403 )
		);
	}
}
