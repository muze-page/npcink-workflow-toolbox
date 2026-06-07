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
		$this->post( '/flows/site-knowledge-review-plan', 'site_knowledge_review_plan' );
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

		return rest_ensure_response(
			$this->client->test_cloud_web_search(
				array(
					'query'               => $query,
					'intent'              => sanitize_key( (string) ( $request->get_param( 'intent' ) ?: 'news' ) ),
					'max_results'         => max( 1, min( 5, (int) ( $request->get_param( 'max_results' ) ?: 3 ) ) ),
					'recency_days'        => max( 0, min( 30, (int) ( $request->get_param( 'recency_days' ) ?: 7 ) ) ),
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

	public function site_knowledge_review_plan( WP_REST_Request $request ) {
		$params = method_exists( $request, 'get_params' ) ? $request->get_params() : array();
		return rest_ensure_response( $this->client->build_site_knowledge_review_plan( is_array( $params ) ? $params : array() ) );
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
		if ( ! in_array( $intent, array( 'writing_support', 'summary_terms_optimization', 'taxonomy_tags', 'internal_links', 'image_candidates', 'publish_preflight', 'discoverability' ), true ) ) {
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

		if ( 'summary_terms_optimization' === $intent ) {
			$result['sections']['summary_terms_optimization'] = $this->editor_summary_terms_optimization( $context, $query );
		}

		if ( 'internal_links' === $intent ) {
			$result['sections']['site_knowledge'] = $this->editor_support_section(
				$this->client->search_site_knowledge(
					array(
						'query'           => $query,
						'intent'          => 'internal_links',
						'current_post_id' => absint( $context['post_id'] ?? 0 ),
						'max_results'     => 8,
					)
				)
			);
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
		}

		return rest_ensure_response( $result );
	}

	public function media_derivative_handoff( WP_REST_Request $request ) {
		$params = method_exists( $request, 'get_params' ) ? $request->get_params() : array();
		return rest_ensure_response( $this->client->build_media_derivative_handoff( is_array( $params ) ? $params : array() ) );
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

	private function required_text( WP_REST_Request $request, string $key ) {
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

		return $value;
	}

	private function editor_post_context( WP_REST_Request $request ): array {
		$content             = trim( wp_strip_all_tags( (string) $request->get_param( 'content' ) ) );
		$selected_text       = trim( wp_strip_all_tags( (string) $request->get_param( 'selected_text' ) ) );
		$selected_block_text = trim( wp_strip_all_tags( (string) $request->get_param( 'selected_block_text' ) ) );
		return array(
			'post_id'             => absint( $request->get_param( 'post_id' ) ),
			'post_type'           => sanitize_key( (string) ( $request->get_param( 'post_type' ) ?: 'post' ) ),
			'post_status'         => sanitize_key( (string) $request->get_param( 'post_status' ) ),
			'title'               => sanitize_text_field( (string) $request->get_param( 'title' ) ),
			'excerpt'             => sanitize_textarea_field( (string) $request->get_param( 'excerpt' ) ),
			'content_text'        => wp_trim_words( $content, 220, '' ),
			'selected_text'       => wp_trim_words( sanitize_textarea_field( $selected_text ), 110, '' ),
			'selected_block_text' => wp_trim_words( sanitize_textarea_field( $selected_block_text ), 110, '' ),
			'selected_block_name' => sanitize_text_field( (string) $request->get_param( 'selected_block_name' ) ),
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
		$query = trim(
			implode(
				' ',
				array_filter(
					array(
						(string) ( $context['title'] ?? '' ),
						(string) ( $context['excerpt'] ?? '' ),
						(string) ( $context['content_text'] ?? '' ),
					)
				)
			)
		);

		return wp_trim_words( $query, 80, '' );
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
		$taxonomy_terms = $this->editor_taxonomy_term_candidates( $context, $query );
		$summary_ai     = $this->editor_support_section(
			$this->client->run_hosted_ai_content_support(
				array(
					'intent'  => 'summary_terms_optimization',
					'post_id' => absint( $context['post_id'] ?? 0 ),
					'title'   => (string) ( $context['title'] ?? '' ),
					'excerpt' => (string) ( $context['excerpt'] ?? '' ),
					'content' => (string) ( $context['content_text'] ?? '' ),
				)
			)
		);
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

		return array(
			'artifact_type'          => 'article_discoverability_optimization.v1',
			'composition_role'       => 'summary_taxonomy_tag_candidates',
			'candidate_type'         => 'summary_terms_optimization',
			'write_posture'          => 'suggestion_only',
			'final_write_path'       => 'core_proposal_required',
			'direct_wordpress_write' => false,
			'summary_candidates'     => $summary_ai,
			'summary_layers'         => $this->editor_summary_layer_candidates( $context ),
			'category_candidates'    => array_slice( $categories, 0, 5 ),
			'tag_candidates'         => array_slice( $tags, 0, 8 ),
			'taxonomy_terms'         => $taxonomy_terms,
			'related_content'        => $related_content,
			'discoverability'        => $discoverability,
			'optimization_strategy'  => $this->editor_summary_terms_strategy(),
			'review_metrics'         => $this->editor_summary_terms_review_metrics(),
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

	private function editor_summary_layer_candidates( array $context ): array {
		$title   = trim( sanitize_text_field( (string) ( $context['title'] ?? '' ) ) );
		$excerpt = trim( sanitize_textarea_field( (string) ( $context['excerpt'] ?? '' ) ) );
		$content = trim( wp_strip_all_tags( (string) ( $context['content_text'] ?? '' ) ) );
		$base    = '' !== $excerpt ? $excerpt : ( '' !== $content ? $content : $title );

		return array(
			'candidate_type'         => 'summary_layer_candidates',
			'write_posture'          => 'suggestion_only',
			'direct_wordpress_write' => false,
			'items'                  => array(
				array(
					'id'     => 'short_summary',
					'label'  => __( 'Short summary', 'npcink-toolbox' ),
					'limit'  => '160_chars',
					'value'  => sanitize_text_field( wp_html_excerpt( $base, 160, '' ) ),
					'reason' => __( 'Use as an excerpt-style candidate after confirming it adds no unsupported facts.', 'npcink-toolbox' ),
				),
				array(
					'id'     => 'standard_summary',
					'label'  => __( 'Standard summary', 'npcink-toolbox' ),
					'limit'  => '2_3_sentences',
					'value'  => sanitize_text_field( wp_trim_words( $base, 45, '' ) ),
					'reason' => __( 'Use for editor review where a slightly fuller article summary is useful.', 'npcink-toolbox' ),
				),
				array(
					'id'     => 'seo_meta_description',
					'label'  => __( 'SEO meta description', 'npcink-toolbox' ),
					'limit'  => '155_chars',
					'value'  => sanitize_text_field( wp_html_excerpt( $base, 155, '' ) ),
					'reason' => __( 'Use only as a Core-governed SEO/meta proposal candidate.', 'npcink-toolbox' ),
				),
			),
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

	private function editor_taxonomy_term_candidates( array $context, string $query ): array {
		$post_type  = sanitize_key( (string) ( $context['post_type'] ?? 'post' ) );
		$taxonomies = array_values(
			array_intersect(
				get_object_taxonomies( $post_type ),
				array( 'category', 'post_tag' )
			)
		);

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
				$score = $this->term_match_score( $term->name . ' ' . $term->slug . ' ' . $term->description, $query );
				if ( $score <= 0 ) {
					continue;
				}
				$matched_tokens = $this->term_match_tokens( $term->name . ' ' . $term->slug . ' ' . $term->description, $query );

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
					'match_signals'                => array(
						'draft_query_overlap',
						'existing_taxonomy_vocabulary',
					),
					'reason'                       => sprintf(
						/* translators: %s: comma-separated matched words. */
						__( 'Existing term matched against the current title, excerpt, or draft body. Matched tokens: %s.', 'npcink-toolbox' ),
						implode( ', ', array_slice( $matched_tokens, 0, 6 ) )
					),
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
			'items'                  => array_slice( $candidates, 0, 10 ),
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
