<?php
/**
 * REST endpoints for Toolbox admin actions and future clients.
 *
 * @package Magick_AI_Toolbox
 */

namespace Magick_AI_Toolbox;

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
		$this->post( '/site-knowledge/search', 'site_knowledge_search' );
		$this->post( '/site-knowledge/sync', 'site_knowledge_sync' );
		$this->post( '/flows/article-brief', 'article_brief' );
		$this->post( '/flows/article-assistant', 'article_assistant' );
		$this->post( '/flows/article-plan', 'article_plan' );
		$this->post( '/flows/image-candidate-adoption-plan', 'image_candidate_adoption_plan' );
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
		return (bool) apply_filters( 'magick_ai_toolbox_rest_permission', current_user_can( 'manage_options' ), $request );
	}

	public function status(): WP_REST_Response {
		return rest_ensure_response(
			array(
				'image_provider'           => 'cloud_image_sources',
				'image_source_providers'   => $this->settings->configured_image_source_providers(),
				'vector_provider'          => 'cloud_site_knowledge',
				'web_search_owner'         => 'cloud_runtime',
				'cloud_image_sources_configured' => $this->settings->has_image_source_provider(),
				'raw_responses_enabled'    => (bool) $this->settings->get( 'include_raw_responses' ),
				'image_source_enabled'     => (bool) $this->settings->get( 'enable_image_source' ),
				'vector_search_enabled'    => false,
				'web_search_enabled'       => true,
				'image_source_owner'       => 'cloud_runtime',
				'vector_owner'             => 'cloud_runtime',
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
				)
			)
		);
	}

	public function knowledge_search( WP_REST_Request $request ) {
		$query = trim( sanitize_textarea_field( (string) $request->get_param( 'query' ) ) );
		$vector = trim( sanitize_textarea_field( (string) $request->get_param( 'vector' ) ) );
		if ( '' === $query && '' === $vector ) {
			return new WP_Error(
				'magick_ai_toolbox_missing_vector_input',
				__( 'A query or vector field is required for vector search.', 'magick-ai-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$input_type = sanitize_key( (string) ( $request->get_param( 'input_type' ) ?: 'auto' ) );
		$input = '' !== $query ? $query : $vector;
		$max_results = max( 1, min( 10, (int) ( $request->get_param( 'max_results' ) ?: 4 ) ) );
		return rest_ensure_response( $this->client->vector_search( $input, $max_results, $input_type ) );
	}

	public function site_knowledge_status( WP_REST_Request $request ) {
		return rest_ensure_response(
			$this->client->get_site_knowledge_status(
				array(
					'include_coverage' => true,
				)
			)
		);
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
					'provider'            => sanitize_key( (string) ( $request->get_param( 'provider' ) ?: 'auto' ) ),
					'max_results'         => max( 1, min( 5, (int) ( $request->get_param( 'max_results' ) ?: 3 ) ) ),
					'recency_days'        => max( 0, min( 30, (int) ( $request->get_param( 'recency_days' ) ?: 7 ) ) ),
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

	public function media_brief( WP_REST_Request $request ) {
		$post_id = absint( $request->get_param( 'post_id' ) );
		if ( 0 === $post_id ) {
			return new WP_Error(
				'magick_ai_toolbox_missing_post_id',
				__( 'A post_id is required for the media brief flow.', 'magick-ai-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error(
				'magick_ai_toolbox_post_not_found',
				__( 'The requested post was not found.', 'magick-ai-toolbox' ),
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
		if ( ! in_array( $intent, array( 'taxonomy_tags', 'internal_links', 'image_candidates', 'publish_preflight', 'discoverability' ), true ) ) {
			return new WP_Error(
				'magick_ai_toolbox_invalid_editor_support_intent',
				__( 'A supported editor content-support intent is required.', 'magick-ai-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$context = $this->editor_post_context( $request );
		$query   = $this->editor_support_query( $context );
		if ( '' === $query ) {
			return new WP_Error(
				'magick_ai_toolbox_missing_editor_context',
				__( 'A title, excerpt, or post content is required for editor content support.', 'magick-ai-toolbox' ),
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

		if ( 'taxonomy_tags' === $intent ) {
			$result['sections']['taxonomy_terms'] = $this->editor_taxonomy_term_candidates( $context, $query );
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
						'provider' => 'auto',
						'per_page' => 6,
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
				'magick_ai_toolbox_missing_' . sanitize_key( $key ),
				sprintf(
					/* translators: %s: field name. */
					__( '%s is required.', 'magick-ai-toolbox' ),
					$key
				),
				array( 'status' => 400 )
			);
		}

		return $value;
	}

	private function editor_post_context( WP_REST_Request $request ): array {
		$content = trim( wp_strip_all_tags( (string) $request->get_param( 'content' ) ) );
		return array(
			'post_id'        => absint( $request->get_param( 'post_id' ) ),
			'post_type'      => sanitize_key( (string) ( $request->get_param( 'post_type' ) ?: 'post' ) ),
			'post_status'    => sanitize_key( (string) $request->get_param( 'post_status' ) ),
			'title'          => sanitize_text_field( (string) $request->get_param( 'title' ) ),
			'excerpt'        => sanitize_textarea_field( (string) $request->get_param( 'excerpt' ) ),
			'content_text'   => wp_trim_words( $content, 220, '' ),
			'category_ids'   => $this->csv_absint_list( (string) $request->get_param( 'category_ids' ) ),
			'tag_ids'        => $this->csv_absint_list( (string) $request->get_param( 'tag_ids' ) ),
			'featured_media' => absint( $request->get_param( 'featured_media' ) ),
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

				$candidates[] = array(
					'term_id'  => (int) $term->term_id,
					'taxonomy' => sanitize_key( $taxonomy ),
					'name'     => sanitize_text_field( $term->name ),
					'slug'     => sanitize_title( $term->slug ),
					'score'    => $score,
					'reason'   => __( 'Matched against the current title, excerpt, or draft body.', 'magick-ai-toolbox' ),
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
		$term_tokens  = $this->support_tokens( $term_text );
		$query_tokens = $this->support_tokens( $query );
		if ( array() === $term_tokens || array() === $query_tokens ) {
			return 0;
		}

		return count( array_intersect( $term_tokens, $query_tokens ) );
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
				'label'  => __( 'Title', 'magick-ai-toolbox' ),
				'detail' => '' !== trim( (string) ( $context['title'] ?? '' ) ) ? __( 'Title is present.', 'magick-ai-toolbox' ) : __( 'Add a specific title before publishing.', 'magick-ai-toolbox' ),
			),
			array(
				'id'     => 'excerpt',
				'status' => '' !== trim( (string) ( $context['excerpt'] ?? '' ) ) ? 'ok' : 'warning',
				'label'  => __( 'Excerpt', 'magick-ai-toolbox' ),
				'detail' => '' !== trim( (string) ( $context['excerpt'] ?? '' ) ) ? __( 'Excerpt is present.', 'magick-ai-toolbox' ) : __( 'Add an excerpt or meta description candidate.', 'magick-ai-toolbox' ),
			),
			array(
				'id'     => 'terms',
				'status' => ! empty( $context['category_ids'] ) || ! empty( $context['tag_ids'] ) ? 'ok' : 'warning',
				'label'  => __( 'Terms', 'magick-ai-toolbox' ),
				'detail' => ! empty( $context['category_ids'] ) || ! empty( $context['tag_ids'] ) ? __( 'At least one category or tag is selected.', 'magick-ai-toolbox' ) : __( 'Review category and tag candidates before publishing.', 'magick-ai-toolbox' ),
			),
			array(
				'id'     => 'featured_media',
				'status' => ! empty( $context['featured_media'] ) ? 'ok' : 'warning',
				'label'  => __( 'Featured image', 'magick-ai-toolbox' ),
				'detail' => ! empty( $context['featured_media'] ) ? __( 'Featured image is selected.', 'magick-ai-toolbox' ) : __( 'Review image candidates or select a featured image.', 'magick-ai-toolbox' ),
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
			'magick_ai_toolbox_disabled',
			sprintf(
				/* translators: %s: feature label. */
				__( 'Enable %s in Magick AI Toolbox settings before running this tool.', 'magick-ai-toolbox' ),
				$label
			),
			array( 'status' => 403 )
		);
	}
}
