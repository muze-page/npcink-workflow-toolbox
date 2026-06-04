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
		$this->post( '/web-research', 'web_research' );
		$this->post( '/image-candidates', 'image_candidates' );
		$this->post( '/vector-search', 'knowledge_search' );
		$this->post( '/knowledge-search', 'knowledge_search' );
		$this->post( '/site-knowledge/search', 'site_knowledge_search' );
		$this->post( '/site-knowledge/sync', 'site_knowledge_sync' );
		$this->post( '/flows/article-brief', 'article_brief' );
		$this->post( '/flows/article-assistant', 'article_assistant' );
		$this->post( '/flows/article-plan', 'article_plan' );
		$this->post( '/flows/media-brief', 'media_brief' );
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
				'search_provider'          => sanitize_key( (string) $this->settings->get( 'search_provider' ) ),
				'search_providers'         => $this->settings->configured_search_providers(),
				'image_provider'           => sanitize_key( (string) $this->settings->get( 'image_provider' ) ),
				'image_source_providers'   => $this->settings->configured_image_source_providers(),
				'vector_provider'          => 'qdrant',
				'embedding_provider'       => sanitize_key( (string) $this->settings->get( 'embedding_provider' ) ),
				'embedding_dimensions'     => (int) $this->settings->get( 'embedding_dimensions' ),
				'tavily_configured'        => $this->settings->has_tavily_api_key(),
				'bocha_configured'         => $this->settings->has_bocha_api_key(),
				'unsplash_configured'      => $this->settings->has_unsplash_access_key(),
				'pixabay_configured'       => $this->settings->has_pixabay_api_key(),
				'pexels_configured'        => $this->settings->has_pexels_api_key(),
				'qdrant_configured'        => $this->settings->has_qdrant_connection(),
				'siliconflow_configured'   => $this->settings->has_siliconflow_api_key(),
				'jina_configured'          => $this->settings->has_jina_api_key(),
				'jina_reader_enabled'      => (bool) $this->settings->get( 'enable_jina_reader' ),
				'raw_responses_enabled'    => (bool) $this->settings->get( 'include_raw_responses' ),
				'web_research_enabled'     => (bool) $this->settings->get( 'enable_web_research' ),
				'image_source_enabled'     => (bool) $this->settings->get( 'enable_image_source' ),
				'vector_search_enabled'    => (bool) $this->settings->get( 'enable_vector_search' ),
				'boundary'                 => 'Toolbox returns research, image-source, and vector-search suggestions only. WordPress writes should be handed to Abilities/Core governance.',
			)
		);
	}

	public function web_research( WP_REST_Request $request ) {
		if ( ! $this->settings->get( 'enable_web_research' ) ) {
			return $this->disabled_error( 'web research' );
		}

		$query = $this->required_text( $request, 'query' );
		if ( is_wp_error( $query ) ) {
			return $query;
		}

		return rest_ensure_response(
			$this->client->web_research(
				$query,
				array(
					'include_domains' => $this->csv_list( (string) $request->get_param( 'include_domains' ) ),
					'exclude_domains' => $this->csv_list( (string) $request->get_param( 'exclude_domains' ) ),
					'time_range'      => sanitize_key( (string) $request->get_param( 'time_range' ) ),
					'provider'        => sanitize_key( (string) $request->get_param( 'provider' ) ),
					'enhance_with_reader' => ! empty( $request->get_param( 'enhance_with_reader' ) ),
					'max_results'     => (int) ( $request->get_param( 'max_results' ) ?: 5 ),
				)
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
		if ( ! $this->settings->get( 'enable_vector_search' ) ) {
			return $this->disabled_error( 'vector search' );
		}

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
