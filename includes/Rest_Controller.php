<?php
/**
 * REST endpoints for Toolbox admin actions and future clients.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

final class Rest_Controller {
	private const REQUIRED_TEXT_MAX_CHARS = 500;

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
		$this->post( '/flows/content-metadata-apply-plan', 'content_metadata_apply_plan' );
		$this->post( '/flows/media-brief', 'media_brief' );
		$this->post( '/editor/content-support', 'editor_content_support' );
		$this->post( '/media-derivative-handoff', 'media_derivative_handoff' );

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
					'include_ai_generated' => ! empty( $request->get_param( 'include_ai_generated' ) ),
					'generation_prompt'     => sanitize_textarea_field( (string) $request->get_param( 'generation_prompt' ) ),
					'generated_image_url'   => esc_url_raw( (string) $request->get_param( 'generated_image_url' ) ),
					'model'                 => sanitize_text_field( (string) $request->get_param( 'model' ) ),
					'manual_query'          => $query,
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
		if ( ! in_array( $intent, array( 'writing_support', 'summary_suggestions', 'category_suggestions', 'tag_suggestions', 'summary_terms_optimization', 'taxonomy_tags', 'internal_links', 'image_candidates', 'publish_preflight', 'discoverability' ), true ) ) {
			return new WP_Error(
				'npcink_toolbox_invalid_editor_support_intent',
				__( 'A supported editor content-support intent is required.', 'npcink-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$context = $this->editor_post_context( $request );
		$query   = $this->editor_support_query( $context );
		if ( 'image_candidates' === $intent ) {
			$query = $this->editor_image_support_query( $context );
		}
		if ( '' === $query ) {
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
			'handoff'                => array(
				'surface'                => 'post_editor_sidebar',
				'final_writes'           => 'core_proposal_required',
				'direct_wordpress_write' => false,
			),
		);

		if ( 'writing_support' === $intent ) {
			$result['sections']['writing_support'] = $this->editor_support_section(
				$this->client->search_site_knowledge(
					array(
						'query'           => $query,
						'intent'          => 'writing_support_plan',
						'current_post_id' => absint( $context['post_id'] ?? 0 ),
						'max_results'     => 6,
					)
				)
			);
		}

		if ( 'taxonomy_tags' === $intent ) {
			$result['sections']['taxonomy_terms'] = $this->editor_taxonomy_term_candidates( $context, $query );
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
			$result['sections']['image_candidates'] = $this->editor_support_section(
				$this->client->image_candidates(
					$query,
					array(
						'provider'       => 'auto',
						'per_page'       => 6,
						'image_mode'     => 'paragraph' === sanitize_key( (string) ( $context['image_mode'] ?? '' ) ) ? 'paragraph_image' : 'featured_image',
						'visual_context' => $this->editor_image_visual_context( $context, $query ),
					)
				)
			);
		}

		if ( 'discoverability' === $intent || 'publish_preflight' === $intent ) {
			$result['sections']['discoverability'] = $this->editor_support_section(
				$this->client->build_content_discoverability_brief(
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

		if ( 'publish_preflight' === $intent ) {
			$result['sections']['checks'] = $this->editor_publish_preflight_checks( $context );
			$result['sections']['duplicate_check'] = $this->editor_support_section(
				$this->client->search_site_knowledge(
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
		$context_scope       = sanitize_key( (string) ( $request->get_param( 'context_scope' ) ?: 'auto' ) );
		if ( ! in_array( $context_scope, array( 'auto', 'full_article', 'selected_text', 'topic_only' ), true ) ) {
			$context_scope = 'auto';
		}

		return array(
			'post_id'             => absint( $request->get_param( 'post_id' ) ),
			'post_type'           => sanitize_key( (string) ( $request->get_param( 'post_type' ) ?: 'post' ) ),
			'post_status'         => sanitize_key( (string) $request->get_param( 'post_status' ) ),
			'context_scope'       => $context_scope,
			'title'               => sanitize_text_field( (string) $request->get_param( 'title' ) ),
			'excerpt'             => sanitize_textarea_field( (string) $request->get_param( 'excerpt' ) ),
			'content_text'        => wp_trim_words( $content, 220, '' ),
			'selected_text'       => wp_trim_words( sanitize_textarea_field( $selected_text ), 110, '' ),
			'selected_block_text' => wp_trim_words( sanitize_textarea_field( $selected_block_text ), 110, '' ),
			'selected_block_name' => sanitize_text_field( (string) $request->get_param( 'selected_block_name' ) ),
			'generation_variant'  => sanitize_text_field( (string) $request->get_param( 'generation_variant' ) ),
			'image_mode'          => sanitize_key( (string) $request->get_param( 'image_mode' ) ),
			'category_ids'        => $this->csv_absint_list( (string) $request->get_param( 'category_ids' ) ),
			'tag_ids'             => $this->csv_absint_list( (string) $request->get_param( 'tag_ids' ) ),
			'featured_media'      => absint( $request->get_param( 'featured_media' ) ),
		);
	}

	private function image_visual_context_from_request( WP_REST_Request $request, string $query ): array {
		$context = $request->get_param( 'visual_context' );
		if ( is_array( $context ) ) {
			$context['manual_query'] = $context['manual_query'] ?? $query;
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
			'query_intent'        => array(
				'rewrite_abstract_terms'       => ! empty( $context['query_intent']['rewrite_abstract_terms'] ),
				'prefer_concrete_visual_scene' => ! empty( $context['query_intent']['prefer_concrete_visual_scene'] ),
				'return_alternate_queries'     => ! empty( $context['query_intent']['return_alternate_queries'] ),
			),
		);
	}

	private function editor_support_query( array $context ): string {
		$scope     = sanitize_key( (string) ( $context['context_scope'] ?? 'auto' ) );
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

	private function editor_summary_terms_optimization( array $context, string $query ): array {
		$related_content = $this->editor_support_section(
			$this->client->search_site_knowledge(
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
			$this->client->run_hosted_ai_content_support(
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
			$this->client->build_content_discoverability_brief(
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
		$proposed_new_terms = $this->editor_proposed_new_terms_review( $summary_ai );
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
				__( 'Prefer existing categories and tags; treat proposed new tags as operator-review candidates only.', 'npcink-toolbox' ),
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
					__( 'Existing WordPress terms should be reused before proposing new vocabulary.', 'npcink-toolbox' ),
					__( 'Related Site Knowledge evidence can reveal duplicate coverage and proven term patterns.', 'npcink-toolbox' ),
				),
				'warnings'          => array(
					__( 'Do not accept summaries that add unsupported claims.', 'npcink-toolbox' ),
					__( 'Treat proposed new terms as taxonomy-sprawl risks until a human confirms a real vocabulary gap.', 'npcink-toolbox' ),
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
								'core_proposal_required_for_external_batch_new_term_or_incomplete_preview',
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
					'new_term_candidates_keep_review_required_true',
					'no_toolbox_direct_wordpress_write',
					'accepted_write_like_changes_route_through_core_or_future_classified_local_consent',
				),
			),
			'learning_candidates'    => array(
				'accepted_excerpt_style',
				'accepted_existing_category_or_tag_patterns',
				'rejected_or_merged_new_term_candidates',
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

	private function editor_internal_link_candidates( array $context, string $query ): array {
		$knowledge = $this->editor_support_section(
			$this->client->search_site_knowledge(
				array(
					'query'           => $query,
					'intent'          => 'internal_links',
					'current_post_id' => absint( $context['post_id'] ?? 0 ),
					'max_results'     => 8,
				)
			)
		);

		return array(
			'artifact_type'          => 'internal_link_candidates.v1',
			'candidate_type'         => 'internal_link_candidates',
			'write_posture'          => 'suggestion_only',
			'final_write_path'       => 'operator_review_only_no_insert',
			'direct_wordpress_write' => false,
			'input_scope'            => $this->editor_input_scope( $context ),
			'items'                  => $this->editor_internal_link_candidate_items( $context, $knowledge ),
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
		$summary_ai = $this->editor_support_section(
			$this->client->run_hosted_ai_content_support(
				array(
					'intent'             => 'summary_suggestions',
					'post_id'            => absint( $context['post_id'] ?? 0 ),
					'title'              => (string) ( $context['title'] ?? '' ),
					'excerpt'            => (string) ( $context['excerpt'] ?? '' ),
					'content'            => (string) ( $context['content_text'] ?? '' ),
					'generation_variant' => (string) ( $context['generation_variant'] ?? '' ),
				)
			)
		);
		$summary_layers     = $this->editor_ai_summary_layer_candidates( $summary_ai );
		$proposed_new_terms = $this->empty_proposed_new_terms_review();
		$handoff_preview    = $this->editor_summary_terms_handoff_preview( $summary_layers, array(), array(), $proposed_new_terms );
		$metadata_delta     = $this->editor_content_metadata_delta( $context, $query, $summary_layers, array(), array(), $proposed_new_terms, array(), array(), $handoff_preview );
		$section            = $this->editor_metadata_suggestion_section(
			'summary_suggestions',
			$context,
			$summary_layers,
			array(),
			array(),
			$proposed_new_terms,
			array(),
			array(),
			$handoff_preview,
			$metadata_delta
		);
		$section['summary_candidates']  = $summary_ai;
		$section['provider_execution']  = 'hosted_ai';
		$section['generation_mode']     = 'ai_summary';
		$section['generation_variant']  = sanitize_text_field( (string) ( $context['generation_variant'] ?? '' ) );
		$section['quality_contract']    = is_array( $summary_ai['quality_contract'] ?? null ) ? $summary_ai['quality_contract'] : array();
		$section['review_checklist']    = is_array( $summary_ai['review_checklist'] ?? null ) ? $summary_ai['review_checklist'] : array();

		return $section;
	}

	private function editor_fast_category_suggestions( array $context, string $query ): array {
		$taxonomy_terms     = $this->editor_taxonomy_term_candidates( $context, $query );
		$items              = is_array( $taxonomy_terms['items'] ?? null ) ? $taxonomy_terms['items'] : array();
		$categories         = array_values(
			array_filter(
				$items,
				static fn( array $item ): bool => 'category' === (string) ( $item['taxonomy'] ?? '' )
			)
		);
		$summary_layers     = $this->empty_summary_layer_candidates();
		$proposed_new_terms = $this->empty_proposed_new_terms_review();
		$handoff_preview    = $this->editor_summary_terms_handoff_preview( $summary_layers, $categories, array(), $proposed_new_terms );
		$metadata_delta     = $this->editor_content_metadata_delta( $context, $query, $summary_layers, $categories, array(), $proposed_new_terms, array(), array(), $handoff_preview );

		return $this->editor_metadata_suggestion_section(
			'category_suggestions',
			$context,
			$summary_layers,
			$categories,
			array(),
			$proposed_new_terms,
			$taxonomy_terms,
			array(),
			$handoff_preview,
			$metadata_delta
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
		$summary_layers     = $this->empty_summary_layer_candidates();
		$proposed_new_terms = $this->editor_proposed_new_terms_from_query( $context, $query, $tags );
		$handoff_preview    = $this->editor_summary_terms_handoff_preview( $summary_layers, array(), $tags, $proposed_new_terms );
		$metadata_delta     = $this->editor_content_metadata_delta( $context, $query, $summary_layers, array(), $tags, $proposed_new_terms, array(), array(), $handoff_preview );

		return $this->editor_metadata_suggestion_section(
			'tag_suggestions',
			$context,
			$summary_layers,
			array(),
			$tags,
			$proposed_new_terms,
			$taxonomy_terms,
			array(),
			$handoff_preview,
			$metadata_delta
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
						'authorization_path'     => 'core_policy_gated_strong_review',
						'status'                 => 'review_only_vocabulary_gap',
					);
				},
				$items
			)
		);
	}

	private function editor_proposed_new_terms_review( array $summary_ai ): array {
		$items = array();
		if ( '' !== trim( (string) ( $summary_ai['output_text'] ?? '' ) ) ) {
			$items[] = array(
				'name'                         => __( 'AI-proposed new terms in hosted output', 'npcink-toolbox' ),
				'status'                       => 'review_only',
				'controlled_vocabulary_status' => 'not_existing_term',
				'source'                       => 'hosted_ai_output',
				'reason'                       => __( 'Review any new category or tag names mentioned by hosted AI only after checking that no existing WordPress term is close enough.', 'npcink-toolbox' ),
				'strong_review_required'       => true,
				'authorization_path'           => 'core_policy_gated_strong_review',
			);
		}

		return array(
			'candidate_type'         => 'proposed_new_terms_review',
			'write_posture'          => 'suggestion_only',
			'direct_wordpress_write' => false,
			'creation_policy'        => 'core_policy_gated_strong_review',
			'strong_review_required' => true,
			'duplicate_review_required' => true,
			'blocked_actions'        => array(
				'no_direct_term_creation_in_toolbox',
				'no_auto_approval_request_for_new_terms',
				'no_term_assignment_without_core_policy_review',
			),
			'items'                  => $items,
			'empty_message'          => __( 'No new term is recommended by default. Prefer existing terms unless an editor confirms a real vocabulary gap.', 'npcink-toolbox' ),
		);
	}

	private function empty_proposed_new_terms_review(): array {
		return array(
			'candidate_type'         => 'proposed_new_terms_review',
			'write_posture'          => 'suggestion_only',
			'direct_wordpress_write' => false,
			'creation_policy'        => 'core_policy_gated_strong_review',
			'strong_review_required' => true,
			'duplicate_review_required' => true,
			'blocked_actions'        => array(
				'no_direct_term_creation_in_toolbox',
				'no_auto_approval_request_for_new_terms',
				'no_term_assignment_without_core_policy_review',
			),
			'items'                  => array(),
			'empty_message'          => __( 'No new term is recommended by default. Prefer existing terms unless an editor confirms a real vocabulary gap.', 'npcink-toolbox' ),
		);
	}

	private function editor_proposed_new_terms_from_query( array $context, string $query, array $existing_tag_candidates ): array {
		$existing_keys = array();
		foreach ( $existing_tag_candidates as $item ) {
			$name = sanitize_text_field( (string) ( $item['name'] ?? '' ) );
			if ( '' !== $name ) {
				$existing_keys[ sanitize_title( $name ) ] = true;
			}
		}

		$items = array();
		foreach ( $this->support_tokens( $query ) as $token ) {
			$label = trim( sanitize_text_field( $token ) );
			if ( '' === $label || strlen( $label ) < 2 ) {
				continue;
			}
			$key = sanitize_title( $label );
			if ( '' === $key || isset( $existing_keys[ $key ] ) ) {
				continue;
			}
			$items[] = array(
				'taxonomy'                     => 'post_tag',
				'name'                         => $label,
				'status'                       => 'review_only_vocabulary_gap',
				'controlled_vocabulary_status' => 'not_existing_term',
				'source'                       => 'draft_token_gap',
				'reason'                       => __( 'Review as a possible new tag only if no existing WordPress tag is close enough.', 'npcink-toolbox' ),
				'strong_review_required'       => true,
				'authorization_path'           => 'core_policy_gated_strong_review',
			);
			$existing_keys[ $key ] = true;
			if ( 5 <= count( $items ) ) {
				break;
			}
		}

		return array(
			'candidate_type'         => 'proposed_new_terms_review',
			'write_posture'          => 'suggestion_only',
			'direct_wordpress_write' => false,
			'creation_policy'        => 'core_policy_gated_strong_review',
			'strong_review_required' => true,
			'duplicate_review_required' => true,
			'blocked_actions'        => array(
				'no_direct_term_creation_in_toolbox',
				'no_auto_approval_request_for_new_terms',
				'no_term_assignment_without_core_policy_review',
			),
			'items'                  => $items,
			'empty_message'          => __( 'No new tag gap is obvious from the draft tokens. Prefer existing tags unless an editor confirms a real vocabulary gap.', 'npcink-toolbox' ),
		);
	}

	private function editor_summary_terms_auto_apply_actions( array $summary_layers, array $categories, array $tags, array $proposed_new_terms ): array {
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
		$new_term_count    = count( is_array( $proposed_new_terms['items'] ?? null ) ? $proposed_new_terms['items'] : array() );

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
			array(
				'id'                    => 'create_new_tags_assign',
				'name'                  => __( 'Create new tags and assign', 'npcink-toolbox' ),
				'status'                => 0 < $new_term_count ? 'core_policy_gated' : 'no_new_tag_candidate',
				'target_operation'      => 'create_post_tags_and_assign',
				'available_fields'      => array(
					'proposed_new_terms' => $new_term_count,
				),
				'auto_approval_request' => false,
				'toolbox_direct_apply'  => false,
				'strong_review_required' => true,
				'duplicate_review_required' => true,
				'authorization_path'    => 'core_policy_gated_strong_review',
				'proposal_policy'       => array(
					'core_proposal_required' => true,
					'default_mode'           => 'strong_review_required',
					'eligible_if'            => array(
						'taxonomy_is_post_tag',
						'normalized_term_has_no_close_existing_match',
						'new_tag_count_is_within_core_policy_limit',
						'current_user_can_edit_target_post',
					),
				),
				'reason'                => __( 'New tag creation is allowed only as a Core-governed proposal after duplicate-term review; Toolbox never creates or assigns the tag directly.', 'npcink-toolbox' ),
			),
		);
	}

	private function editor_summary_terms_handoff_preview( array $summary_layers, array $categories, array $tags, array $proposed_new_terms ): array {
		$auto_apply_actions = $this->editor_summary_terms_auto_apply_actions( $summary_layers, $categories, $tags, $proposed_new_terms );

		return array(
			'artifact_type'             => 'summary_terms_handoff_preview.v1',
			'status'                    => 'operator_selection_required',
			'write_posture'             => 'suggestion_only',
			'final_write_path'          => 'core_proposal_required',
			'direct_wordpress_write'    => false,
			'preview_only'              => true,
			'auto_apply_actions'        => $auto_apply_actions,
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
					'create_new_tags_assign',
				),
			),
			'available_fields'          => array(
				'summary_layers'      => $auto_apply_actions[0]['available_fields'],
				'existing_categories' => $auto_apply_actions[2]['available_fields'],
				'existing_tags'       => $auto_apply_actions[1]['available_fields'],
				'proposed_new_terms'  => count( is_array( $proposed_new_terms['items'] ?? null ) ? $proposed_new_terms['items'] : array() ),
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
				__( 'Use Create new tags and assign only through Core proposal review after duplicate-term checks.', 'npcink-toolbox' ),
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

	private function editor_ai_summary_layer_candidates( array $summary_ai ): array {
		$result      = is_array( $summary_ai['result'] ?? null ) ? $summary_ai['result'] : array();
		$output_text = trim( sanitize_textarea_field( (string) ( $summary_ai['output_text'] ?? '' ) ) );
		$recommended = trim( sanitize_textarea_field( (string) ( $result['recommended_excerpt'] ?? $result['short_summary'] ?? $result['excerpt'] ?? '' ) ) );
		$alternate   = trim( sanitize_textarea_field( (string) ( $result['alternate_excerpt'] ?? $result['standard_summary'] ?? '' ) ) );
		$reason      = trim( sanitize_textarea_field( (string) ( $result['why_this_works'] ?? $result['reason'] ?? '' ) ) );

		if ( '' === $recommended && '' !== $output_text ) {
			$decoded = json_decode( $output_text, true );
			if ( is_array( $decoded ) ) {
				$recommended = trim( sanitize_textarea_field( (string) ( $decoded['recommended_excerpt'] ?? $decoded['short_summary'] ?? $decoded['excerpt'] ?? '' ) ) );
				$alternate   = trim( sanitize_textarea_field( (string) ( $decoded['alternate_excerpt'] ?? $decoded['standard_summary'] ?? '' ) ) );
				$reason      = trim( sanitize_textarea_field( (string) ( $decoded['why_this_works'] ?? $decoded['reason'] ?? '' ) ) );
			}
		}
		if ( '' === $recommended && '' !== $output_text ) {
			$recommended = sanitize_text_field( wp_html_excerpt( preg_replace( '/^[#*\-\s:[:alnum:]_]+/u', '', $output_text ), 180, '' ) );
		}

		$items = array();
		if ( '' !== $recommended ) {
			$items[] = array(
				'id'            => 'ai_recommended_excerpt',
				'label'         => __( 'AI recommended excerpt', 'npcink-toolbox' ),
				'limit'         => '80_160_zh_chars',
				'value'         => sanitize_text_field( $recommended ),
				'reason'        => '' !== $reason ? $reason : __( 'Generated by hosted AI from the current title, excerpt, and draft body. Review before applying.', 'npcink-toolbox' ),
				'context_use'   => 'draft_grounded_ai_summary',
				'evidence_refs' => array(),
			);
		}
		if ( '' !== $alternate && $alternate !== $recommended ) {
			$items[] = array(
				'id'            => 'ai_alternate_excerpt',
				'label'         => __( 'AI alternate excerpt', 'npcink-toolbox' ),
				'limit'         => '80_160_zh_chars',
				'value'         => sanitize_text_field( $alternate ),
				'reason'        => __( 'Alternate AI wording with the same factual scope for editor comparison.', 'npcink-toolbox' ),
				'context_use'   => 'draft_grounded_ai_summary',
				'evidence_refs' => array(),
			);
		}

		return array(
			'candidate_type'         => 'ai_summary_layer_candidates',
			'write_posture'          => 'suggestion_only',
			'direct_wordpress_write' => false,
			'related_context_summary' => array(),
			'items'                  => $items,
		);
	}

	private function editor_summary_terms_strategy(): array {
		return array(
			'candidate_type'         => 'summary_terms_precision_strategy',
			'write_posture'          => 'suggestion_only',
			'direct_wordpress_write' => false,
			'existing_terms_first'   => true,
			'proposed_new_terms'     => 'operator_review_only',
			'ranking_signals'        => array(
				array(
					'name'   => __( 'Draft query overlap', 'npcink-toolbox' ),
					'weight' => 'high',
					'detail' => __( 'Match against title, excerpt, selected text, and draft body tokens.', 'npcink-toolbox' ),
				),
				array(
					'name'   => __( 'Existing taxonomy vocabulary', 'npcink-toolbox' ),
					'weight' => 'high',
					'detail' => __( 'Prefer existing WordPress categories and tags before suggesting new terms.', 'npcink-toolbox' ),
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
					'name'   => 'new_term_rate',
					'detail' => __( 'Keep proposed new terms visible as a taxonomy-sprawl signal instead of silently adding them.', 'npcink-toolbox' ),
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
				$related_evidence = is_array( $related_term_evidence[ $term_key ] ?? null ) ? $related_term_evidence[ $term_key ] : array();
				$draft_score      = $this->term_match_score( $term->name . ' ' . $term->slug . ' ' . $term->description, $query );
				$related_score    = $this->editor_related_term_score( $related_evidence );
				$score            = $draft_score + $related_score;
				if ( $score <= 0 ) {
					continue;
				}
				$matched_tokens = $this->term_match_tokens( $term->name . ' ' . $term->slug . ' ' . $term->description, $query );
				$match_signals  = array( 'existing_taxonomy_vocabulary' );
				if ( $draft_score > 0 ) {
					$match_signals[] = 'draft_query_overlap';
				}
				if ( $related_score > 0 ) {
					$match_signals[] = 'related_site_knowledge_term';
				}

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

		return array_values(
			array_unique(
				array_filter(
					$tokens,
					static function ( string $token ): bool {
						return strlen( $token ) >= 2;
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
