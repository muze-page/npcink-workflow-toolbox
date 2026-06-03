<?php
/**
 * Abilities API registrations for Toolbox actions.
 *
 * @package Magick_AI_Toolbox
 */

namespace Magick_AI_Toolbox;

defined( 'ABSPATH' ) || exit;

final class Abilities {
	private Settings $settings;
	private Provider_Client $client;
	private bool $registered_with_helpers = false;

	public function __construct( Settings $settings, Provider_Client $client ) {
		$this->settings = $settings;
		$this->client   = $client;
	}

	public function register_with_magick_ai_abilities(): void {
		if ( $this->registered_with_helpers || ! function_exists( 'magick_ai_abilities_register_readonly' ) ) {
			return;
		}

		if ( function_exists( 'magick_ai_abilities_register_category' ) ) {
			magick_ai_abilities_register_category(
				'magick-ai-toolbox',
				array(
					'label'       => __( 'Magick AI Toolbox', 'magick-ai-toolbox' ),
					'description' => __( 'External research, image, knowledge, and fixed-flow tools.', 'magick-ai-toolbox' ),
				)
			);
		}

		foreach ( $this->definitions() as $ability_id => $definition ) {
			magick_ai_abilities_register_readonly( $ability_id, $definition );
		}

		$this->registered_with_helpers = true;
	}

	public function register_native_category(): void {
		if ( $this->registered_with_helpers || ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		if ( function_exists( 'wp_has_ability_category' ) && wp_has_ability_category( 'magick-ai-toolbox' ) ) {
			return;
		}

		wp_register_ability_category(
			'magick-ai-toolbox',
			array(
				'label'       => __( 'Magick AI Toolbox', 'magick-ai-toolbox' ),
				'description' => __( 'External research, image, knowledge, and fixed-flow tools.', 'magick-ai-toolbox' ),
			)
		);
	}

	public function register_native_abilities(): void {
		if ( $this->registered_with_helpers || ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		foreach ( $this->definitions() as $ability_id => $definition ) {
			wp_register_ability(
				$ability_id,
				array(
					'label'               => $definition['label'],
					'description'         => $definition['description'],
					'category'            => 'magick-ai-toolbox',
					'input_schema'        => $definition['input_schema'],
					'output_schema'       => $definition['output_schema'],
					'execute_callback'    => $definition['execute_callback'],
					'permission_callback' => fn(): bool => $this->can_execute_ability( $ability_id ),
					'meta'                => array_merge(
						array(
							'show_in_rest'   => true,
							'readonly'       => true,
							'required_scope' => $definition['required_scope'],
						),
						$definition['meta']
					),
				)
			);
		}
	}

	private function definitions(): array {
		return array(
			'magick-ai-toolbox/web-research'                       => $this->definition(
				__( 'Web Research', 'magick-ai-toolbox' ),
				__( 'Research a topic using the configured external search provider.', 'magick-ai-toolbox' ),
				array( 'query' ),
				array( $this, 'web_research' ),
				'cap.toolbox.search',
				array(
					'composition_role' => 'research_evidence',
				)
			),
			'magick-ai-toolbox/search-image-source'                => $this->definition(
				__( 'Search Image Source', 'magick-ai-toolbox' ),
				__( 'Search configured image source candidates without importing media.', 'magick-ai-toolbox' ),
				array( 'query' ),
				array( $this, 'search_image_source' ),
				'cap.toolbox.image_source',
				array(
					'composition_role' => 'image_source_candidates',
				)
			),
			'magick-ai-toolbox/vector-search'                      => $this->definition(
				__( 'Vector Search', 'magick-ai-toolbox' ),
				__( 'Query the configured vector database with text query embedding or vector JSON.', 'magick-ai-toolbox' ),
				array( 'query' ),
				array( $this, 'vector_search' ),
				'cap.toolbox.vector_search',
				array(
					'composition_role' => 'local_style_context',
				)
			),
			'magick-ai-toolbox/build-article-brief'                => $this->definition(
				__( 'Build Article Brief', 'magick-ai-toolbox' ),
				__( 'Build a research-backed article planning brief without writing WordPress content.', 'magick-ai-toolbox' ),
				array( 'topic' ),
				array( $this, 'build_article_brief' ),
				'cap.toolbox.workflow_suggest',
				array(
					'composition_role' => 'article_planning_bundle',
				)
			),
			'magick-ai-toolbox/build-article-write-plan'           => $this->definition(
				__( 'Build Article Write Plan', 'magick-ai-toolbox' ),
				__( 'Build a Core-ready article_write_plan for a reviewed draft without writing WordPress content.', 'magick-ai-toolbox' ),
				array( 'title', 'content_markdown' ),
				array( $this, 'build_article_write_plan' ),
				'cap.toolbox.workflow_suggest',
				array(
					'data_classification' => 'planning_artifact',
					'composition_role'    => 'core_article_write_plan',
					'provider_execution'  => 'none',
					'write_posture'       => 'core_proposal_handoff',
				)
			),
			'magick-ai-toolbox/build-media-brief'                  => $this->definition(
				__( 'Build Media Brief', 'magick-ai-toolbox' ),
				__( 'Build image prompt and media SEO suggestions from supplied post context.', 'magick-ai-toolbox' ),
				array( 'post_context' ),
				array( $this, 'build_media_brief' ),
				'cap.toolbox.workflow_suggest',
				array(
					'composition_role' => 'media_planning_bundle',
				)
			),
			'magick-ai-toolbox/get-content-discoverability-context' => $this->definition(
				__( 'Get Content Discoverability Context', 'magick-ai-toolbox' ),
				__( 'Return the operator-maintained SEO, AEO, and GEO context for third-party AI callers without exposing provider secrets or writing WordPress content.', 'magick-ai-toolbox' ),
				array(),
				array( $this, 'get_content_discoverability_context' ),
				'cap.toolbox.context.read',
				array(
					'data_classification' => 'public_context',
					'composition_role'    => 'site_context',
					'provider_execution'  => 'none',
					'write_posture'       => 'suggestion_only',
				)
			),
			'magick-ai-toolbox/validate-content-discoverability-context' => $this->definition(
				__( 'Validate Content Discoverability Context', 'magick-ai-toolbox' ),
				__( 'Check whether the operator-maintained SEO, AEO, and GEO context has enough fields for third-party AI suggestion workflows.', 'magick-ai-toolbox' ),
				array(),
				array( $this, 'validate_content_discoverability_context' ),
				'cap.toolbox.context.read',
				array(
					'data_classification' => 'public_context',
					'composition_role'    => 'context_preflight',
					'provider_execution'  => 'none',
					'write_posture'       => 'suggestion_only',
				)
			),
			'magick-ai-toolbox/build-content-discoverability-brief' => $this->definition(
				__( 'Build Content Discoverability Brief', 'magick-ai-toolbox' ),
				__( 'Build a suggestion-only SEO, AEO, and GEO brief from operator context and supplied post or topic input without writing WordPress content.', 'magick-ai-toolbox' ),
				array(),
				array( $this, 'build_content_discoverability_brief' ),
				'cap.toolbox.workflow_suggest',
				array(
					'data_classification' => 'planning_artifact',
					'composition_role'    => 'seo_aeo_geo_brief',
					'provider_execution'  => 'none',
					'write_posture'       => 'suggestion_only',
				)
			),
		);
	}

	private function definition( string $label, string $description, array $required, callable $callback, string $required_scope, array $meta = array() ): array {
		$default_meta = array(
			'show_in_rest'             => true,
			'readonly'                 => true,
			'data_classification'      => 'provider_suggestion',
			'composition_role'         => 'toolbox_suggestion',
			'write_posture'            => 'suggestion_only',
			'final_write_path'         => 'core_proposal_required',
			'direct_wordpress_write'   => false,
			'provider_execution'       => 'server_side_toolbox',
			'provider_secret_exposure' => 'none',
		);
		$properties = array();
		foreach ( $required as $key ) {
			$properties[ $key ] = array(
				'type' => 'string',
			);
		}

		return array(
			'label'               => $label,
			'description'         => $description,
			'category'            => 'magick-ai-toolbox',
			'capability'          => 'manage_options',
			'required_scope'      => $required_scope,
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => $properties,
				'required'             => $required,
				'additionalProperties' => true,
			),
			'output_schema'       => array(
				'type'                 => 'object',
				'additionalProperties' => true,
			),
			'execute_callback'    => $callback,
			'meta'                => array_merge( $default_meta, $meta ),
			'project_to_magick_catalog' => true,
		);
	}

	public function web_research( $input = array() ) {
		$input = is_array( $input ) ? $input : array();
		return $this->client->web_research( sanitize_textarea_field( (string) ( $input['query'] ?? '' ) ) );
	}

	public function search_image_source( $input = array() ) {
		$input = is_array( $input ) ? $input : array();
		return $this->client->image_candidates(
			sanitize_textarea_field( (string) ( $input['query'] ?? '' ) ),
			array(
				'orientation' => sanitize_key( (string) ( $input['orientation'] ?? '' ) ),
				'color'       => sanitize_key( (string) ( $input['color'] ?? '' ) ),
				'per_page'    => (int) ( $input['per_page'] ?? 8 ),
			)
		);
	}

	public function vector_search( $input = array() ) {
		$input = is_array( $input ) ? $input : array();
		$query = sanitize_textarea_field( (string) ( $input['query'] ?? '' ) );
		$vector = sanitize_textarea_field( (string) ( $input['vector'] ?? '' ) );
		$payload = '' !== trim( $query ) ? $query : $vector;
		$input_type = sanitize_key( (string) ( $input['input_type'] ?? 'auto' ) );
		return $this->client->vector_search( $payload, (int) ( $input['max_results'] ?? 4 ), $input_type );
	}

	public function build_article_brief( $input = array() ) {
		$input = is_array( $input ) ? $input : array();
		return $this->client->build_article_brief( sanitize_textarea_field( (string) ( $input['topic'] ?? '' ) ) );
	}

	public function build_article_write_plan( $input = array() ) {
		return $this->client->build_article_write_plan( is_array( $input ) ? $input : array() );
	}

	public function build_media_brief( $input = array() ) {
		$input = is_array( $input ) ? $input : array();
		return $this->client->build_media_brief( sanitize_textarea_field( (string) ( $input['post_context'] ?? '' ) ) );
	}

	public function get_content_discoverability_context( $input = array() ) {
		return $this->settings->get_content_context_for_ability();
	}

	public function validate_content_discoverability_context( $input = array() ) {
		return $this->settings->validate_content_context_for_ability();
	}

	public function build_content_discoverability_brief( $input = array() ) {
		return $this->client->build_content_discoverability_brief( is_array( $input ) ? $input : array() );
	}

	private function can_execute_ability( string $ability_id ): bool {
		return (bool) apply_filters( 'magick_ai_toolbox_ability_permission', current_user_can( 'manage_options' ), $ability_id );
	}
}
