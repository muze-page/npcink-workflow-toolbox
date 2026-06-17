<?php
/**
 * Abilities API registrations for Toolbox actions.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

defined( 'ABSPATH' ) || exit;

final class Abilities {
	private Settings $settings;
	private Provider_Client $client;
	private bool $registered_with_helpers = false;

	public function __construct( Settings $settings, Provider_Client $client ) {
		$this->settings = $settings;
		$this->client   = $client;
	}

	public function register_with_npcink_abilities_toolkit(): void {
		if ( $this->registered_with_helpers || ! function_exists( 'npcink_abilities_toolkit_register_readonly' ) ) {
			return;
		}

		if ( function_exists( 'npcink_abilities_toolkit_register_category' ) ) {
			npcink_abilities_toolkit_register_category(
				'npcink-toolbox',
				array(
					'label'       => __( 'Npcink Toolbox', 'npcink-toolbox' ),
					'description' => __( 'External research, image, knowledge, and fixed-flow tools.', 'npcink-toolbox' ),
				)
			);
		}

		foreach ( $this->definitions() as $ability_id => $definition ) {
			npcink_abilities_toolkit_register_readonly( $ability_id, $definition );
		}

		$this->registered_with_helpers = true;
	}

	public function register_native_category(): void {
		if ( $this->registered_with_helpers || ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		if ( function_exists( 'wp_has_ability_category' ) && wp_has_ability_category( 'npcink-toolbox' ) ) {
			return;
		}

		wp_register_ability_category(
			'npcink-toolbox',
			array(
				'label'       => __( 'Npcink Toolbox', 'npcink-toolbox' ),
				'description' => __( 'External research, image, knowledge, and fixed-flow tools.', 'npcink-toolbox' ),
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
					'category'            => 'npcink-toolbox',
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
			'npcink-toolbox/search-image-source'                => $this->definition(
				__( 'Search Image Source', 'npcink-toolbox' ),
				__( 'Search configured image source candidates without importing media.', 'npcink-toolbox' ),
				array( 'query' ),
				array( $this, 'search_image_source' ),
				'cap.toolbox.image_source',
				array(
					'composition_role' => 'image_source_candidates',
				)
			),
			'npcink-toolbox/generate-image'                     => $this->definition(
				__( 'Generate Image Candidate', 'npcink-toolbox' ),
				__( 'Generate a Cloud-hosted AI image candidate after operator prompt review, without importing media.', 'npcink-toolbox' ),
				array( 'prompt' ),
				array( $this, 'generate_image_candidate' ),
				'cap.toolbox.image_source',
				array(
					'composition_role'  => 'image_source_candidates',
					'provider_execution' => 'cloud_runtime_via_addon',
					'write_posture'     => 'candidate_only_core_approval_required',
				)
			),
			'npcink-toolbox/vector-search'                      => $this->definition(
				__( 'Vector Search', 'npcink-toolbox' ),
				__( 'Compatibility pointer for Cloud-managed site knowledge. Vector provider configuration is managed in Npcink Cloud.', 'npcink-toolbox' ),
				array( 'query' ),
				array( $this, 'vector_search' ),
				'cap.toolbox.vector_search',
				array(
					'data_classification' => 'public_site_content',
					'composition_role' => 'site_knowledge_context',
					'provider_execution' => 'cloud_runtime_via_addon',
					'knowledge_layer' => 'cloud_managed_site_knowledge',
				)
			),
			'npcink-toolbox/search-site-knowledge'              => $this->definition(
				__( 'Search Site Knowledge', 'npcink-toolbox' ),
				__( 'Search Cloud-managed site knowledge for semantic search, related content, writing context, internal links, or refresh suggestions without writing WordPress content.', 'npcink-toolbox' ),
				array( 'query' ),
				array( $this, 'search_site_knowledge' ),
				'cap.toolbox.knowledge.search',
				array(
					'data_classification' => 'public_site_content',
					'composition_role'    => 'site_knowledge_context',
					'provider_execution'  => 'cloud_runtime_via_addon',
					'cloud_contract'      => 'site_knowledge_search.v1',
				)
			),
			'npcink-toolbox/cloud-web-search'                  => $this->definition(
				__( 'Cloud Web Search', 'npcink-toolbox' ),
				__( 'Run Cloud-managed web search for current external evidence without exposing local provider keys or writing WordPress content.', 'npcink-toolbox' ),
				array( 'query' ),
				array( $this, 'cloud_web_search' ),
				'cap.toolbox.web_search',
				array(
					'data_classification'    => 'public_external_evidence',
					'composition_role'       => 'external_web_evidence',
					'provider_execution'     => 'cloud_runtime_via_addon',
					'cloud_contract'         => 'web_search.v1',
					'cloud_ability'          => 'npcink-cloud/web-search',
					'provider_secret_source' => 'cloud_managed',
				),
				array(
					'query'        => array(
						'type'        => 'string',
						'description' => __( 'Search query or research question.', 'npcink-toolbox' ),
					),
					'intent'       => array(
						'type'        => 'string',
						'description' => __( 'Search intent hint such as article_background, fact_check, competitor_research, pricing_snapshot, product_comparison, or general_research.', 'npcink-toolbox' ),
					),
					'max_results'  => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'maximum'     => 8,
						'description' => __( 'Maximum result count requested from Cloud.', 'npcink-toolbox' ),
					),
					'recency_days' => array(
						'type'        => 'integer',
						'minimum'     => 0,
						'maximum'     => 3650,
						'description' => __( 'Optional freshness window in days.', 'npcink-toolbox' ),
					),
				)
			),
			'npcink-toolbox/get-site-knowledge-status'          => $this->definition(
				__( 'Get Site Knowledge Status', 'npcink-toolbox' ),
				__( 'Read Cloud-managed site knowledge coverage and sync status without writing WordPress content.', 'npcink-toolbox' ),
				array(),
				array( $this, 'get_site_knowledge_status' ),
				'cap.toolbox.knowledge.read',
				array(
					'data_classification' => 'public_site_content',
					'composition_role'    => 'site_knowledge_status',
					'provider_execution'  => 'cloud_runtime_via_addon',
					'cloud_contract'      => 'site_knowledge_status.v1',
				)
			),
			'npcink-toolbox/request-site-knowledge-sync'        => $this->definition(
				__( 'Request Site Knowledge Sync', 'npcink-toolbox' ),
				__( 'Request a Cloud-managed site knowledge sync or rebuild from bounded public WordPress content without writing WordPress content.', 'npcink-toolbox' ),
				array(),
				array( $this, 'request_site_knowledge_sync' ),
				'cap.toolbox.knowledge.sync',
				array(
					'data_classification' => 'public_site_content',
					'composition_role'    => 'site_knowledge_sync_request',
					'provider_execution'  => 'cloud_runtime_via_addon',
					'cloud_contract'      => 'site_knowledge_sync.v1',
				)
			),
			'npcink-toolbox/build-article-brief'                => $this->definition(
				__( 'Build Article Brief', 'npcink-toolbox' ),
				__( 'Build a research-backed article planning brief without writing WordPress content.', 'npcink-toolbox' ),
				array( 'topic' ),
				array( $this, 'build_article_brief' ),
				'cap.toolbox.workflow_suggest',
				array(
					'composition_role' => 'article_planning_bundle',
				)
			),
			'npcink-toolbox/build-article-assistant'            => $this->definition(
				__( 'Build Article Assistant Workbench', 'npcink-toolbox' ),
				__( 'Build one local article_draft_v1 workbench artifact from topic, evidence, site context, draft notes, and reviewed draft input without writing WordPress content.', 'npcink-toolbox' ),
				array( 'topic' ),
				array( $this, 'build_article_assistant' ),
				'cap.toolbox.workflow_suggest',
				array(
					'data_classification' => 'planning_artifact',
					'composition_role'    => 'article_assistant_workbench',
					'local_recipe_id'     => 'article_draft_v1',
					'ability_recipe_ref'  => 'workflow/wordpress_article_draft',
					'provider_execution'  => 'server_side_toolbox',
					'write_posture'       => 'core_proposal_handoff',
				)
			),
			'npcink-toolbox/build-article-write-plan'           => $this->definition(
				__( 'Build Article Write Plan', 'npcink-toolbox' ),
				__( 'Build a Core-ready article_write_plan for a reviewed draft without writing WordPress content.', 'npcink-toolbox' ),
				array( 'title', 'content_markdown' ),
				array( $this, 'build_article_write_plan' ),
				'cap.toolbox.workflow_suggest',
				array(
					'data_classification' => 'planning_artifact',
					'composition_role'    => 'core_article_write_plan',
					'local_recipe_id'     => 'article_draft_v1',
					'ability_recipe_ref'  => 'workflow/wordpress_article_draft',
					'provider_execution'  => 'none',
					'write_posture'       => 'core_proposal_handoff',
				)
			),
			'npcink-toolbox/build-article-batch-write-plan'     => $this->definition(
				__( 'Build Article Batch Write Plan', 'npcink-toolbox' ),
				__( 'Build a Core-ready article_batch_write_plan for 2 to 5 reviewed drafts without writing WordPress content.', 'npcink-toolbox' ),
				array( 'articles' ),
				array( $this, 'build_article_batch_write_plan' ),
				'cap.toolbox.workflow_suggest',
				array(
					'data_classification' => 'planning_artifact',
					'composition_role'    => 'core_article_batch_write_plan',
					'local_recipe_id'     => 'article_batch_draft_v1',
					'ability_recipe_ref'  => 'workflow/wordpress_article_batch_draft',
					'provider_execution'  => 'none',
					'write_posture'       => 'core_proposal_handoff',
				)
			),
			'npcink-toolbox/build-article-media-batch-write-plan' => $this->definition(
				__( 'Build Article Media Batch Write Plan', 'npcink-toolbox' ),
				__( 'Build a Core-ready article_media_batch_write_plan for reviewed drafts plus selected image-source candidates without writing WordPress content.', 'npcink-toolbox' ),
				array( 'articles' ),
				array( $this, 'build_article_media_batch_write_plan' ),
				'cap.toolbox.workflow_suggest',
				array(
					'data_classification' => 'planning_artifact',
					'composition_role'    => 'core_article_media_batch_write_plan',
					'local_recipe_id'     => 'article_media_batch_draft_v1',
					'ability_recipe_ref'  => 'workflow/wordpress_article_media_batch_draft',
					'provider_execution'  => 'optional_image_source_lookup',
					'write_posture'       => 'core_proposal_handoff',
				)
			),
			'npcink-toolbox/build-image-candidate-adoption-plan' => $this->definition(
				__( 'Build Image Candidate Adoption Plan', 'npcink-toolbox' ),
				__( 'Build a Core-ready image_candidate_adoption_plan for one reviewed image candidate without importing media or writing WordPress content.', 'npcink-toolbox' ),
				array( 'image_candidate' ),
				array( $this, 'build_image_candidate_adoption_plan' ),
				'cap.toolbox.workflow_suggest',
				array(
					'data_classification' => 'planning_artifact',
					'composition_role'    => 'core_image_candidate_adoption_plan',
					'local_recipe_id'     => 'image_candidate_adoption_v1',
					'ability_recipe_ref'  => 'workflow/image_candidate_adoption',
					'provider_execution'  => 'none',
					'write_posture'       => 'core_proposal_handoff',
					'candidate_contract'  => 'image_candidate.v1',
				)
			),
			'npcink-toolbox/build-site-knowledge-review-plan' => $this->definition(
				__( 'Build Site Knowledge Review Plan', 'npcink-toolbox' ),
				__( 'Build a Core review proposal plan from a Cloud Site Knowledge agent handoff without writing WordPress content.', 'npcink-toolbox' ),
				array( 'proposal_input' ),
				array( $this, 'build_site_knowledge_review_plan' ),
				'cap.toolbox.workflow_suggest',
				array(
					'data_classification' => 'planning_artifact',
					'composition_role'    => 'core_site_knowledge_review_plan',
					'local_recipe_id'     => 'site_knowledge_review_v1',
					'ability_recipe_ref'  => 'workflow/site_knowledge_review',
					'provider_execution'  => 'none',
					'write_posture'       => 'core_proposal_handoff',
				)
			),
			'npcink-toolbox/build-nightly-inspection-review-plan' => $this->definition(
				__( 'Build Nightly Inspection Review Plan', 'npcink-toolbox' ),
				__( 'Build a blocked Core review proposal plan from selected Nightly Morning Brief items without writing WordPress content.', 'npcink-toolbox' ),
				array( 'selected_items' ),
				array( $this, 'build_nightly_inspection_review_plan' ),
				'cap.toolbox.workflow_suggest',
				array(
					'data_classification' => 'planning_artifact',
					'composition_role'    => 'core_nightly_inspection_review_plan',
					'local_recipe_id'     => 'nightly_inspection_review_v1',
					'ability_recipe_ref'  => 'workflow/nightly_site_inspection_review',
					'provider_execution'  => 'none',
					'write_posture'       => 'core_proposal_handoff',
				)
			),
			'npcink-toolbox/build-content-metadata-apply-plan' => $this->definition(
				__( 'Build Content Metadata Apply Plan', 'npcink-toolbox' ),
				__( 'Build a Core-ready content_metadata_apply_plan from reviewed excerpt, category, and tag choices without writing WordPress content.', 'npcink-toolbox' ),
				array( 'post_id' ),
				array( $this, 'build_content_metadata_apply_plan' ),
				'cap.toolbox.workflow_suggest',
				array(
					'data_classification' => 'planning_artifact',
					'composition_role'    => 'core_content_metadata_apply_plan',
					'local_recipe_id'     => 'content_metadata_delta_v1',
					'ability_recipe_ref'  => 'workflow/content_metadata_delta',
					'provider_execution'  => 'none',
					'write_posture'            => 'core_proposal_handoff',
					'accepted_input_contract'  => 'reviewed_excerpt_existing_terms_only_no_create_missing',
				),
				array(
					'post_id'                => array(
						'type'        => 'integer',
						'description' => __( 'Current WordPress post ID receiving reviewed metadata suggestions.', 'npcink-toolbox' ),
					),
					'excerpt'                => array(
						'type'        => 'string',
						'description' => __( 'Reviewed excerpt text to package into a dry-run Core proposal action.', 'npcink-toolbox' ),
					),
					'category_ids'           => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'integer' ),
						'description' => __( 'Reviewed existing category term IDs. Missing terms are never created by this plan.', 'npcink-toolbox' ),
					),
					'tag_ids'                => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'integer' ),
						'description' => __( 'Reviewed existing tag term IDs. Missing terms are never created by this plan.', 'npcink-toolbox' ),
					),
					'category_mode'          => array(
						'type'        => 'string',
						'enum'        => array( 'append', 'replace' ),
						'description' => __( 'Whether Core should append or replace reviewed existing categories during later approved execution.', 'npcink-toolbox' ),
					),
					'tag_mode'               => array(
						'type'        => 'string',
						'enum'        => array( 'append', 'replace' ),
						'description' => __( 'Whether Core should append or replace reviewed existing tags during later approved execution.', 'npcink-toolbox' ),
					),
					'content_metadata_delta' => array(
						'type'        => 'object',
						'description' => __( 'Source content_metadata_delta artifact used as review evidence for the Core proposal.', 'npcink-toolbox' ),
					),
					'evidence_refs'          => array(
						'type'        => 'array',
						'description' => __( 'Evidence references preserved from the metadata delta review.', 'npcink-toolbox' ),
					),
					'new_term_candidates'    => array(
						'type'        => 'array',
						'description' => __( 'Review-only vocabulary-gap notes. This plan keeps create_missing disabled.', 'npcink-toolbox' ),
					),
				)
			),
			'npcink-toolbox/build-media-brief'                  => $this->definition(
				__( 'Build Media Brief', 'npcink-toolbox' ),
				__( 'Build image prompt and media SEO suggestions from supplied post context.', 'npcink-toolbox' ),
				array( 'post_context' ),
				array( $this, 'build_media_brief' ),
				'cap.toolbox.workflow_suggest',
				array(
					'composition_role' => 'media_planning_bundle',
				)
			),
			'npcink-toolbox/build-media-derivative-handoff'     => $this->definition(
				__( 'Build Media Derivative Handoff', 'npcink-toolbox' ),
				__( 'Build a one-run Core/Abilities media derivative handoff from Toolbox defaults without writing WordPress media.', 'npcink-toolbox' ),
				array( 'attachment_id' ),
				array( $this, 'build_media_derivative_handoff' ),
				'cap.toolbox.workflow_suggest',
				array(
					'data_classification' => 'planning_artifact',
					'composition_role'    => 'media_derivative_operator_handoff',
					'provider_execution'  => 'none',
					'write_posture'       => 'core_proposal_handoff',
				)
			),
			'npcink-toolbox/get-content-discoverability-context' => $this->definition(
				__( 'Get Content Discoverability Context', 'npcink-toolbox' ),
				__( 'Return the operator-maintained SEO, AEO, and GEO context for third-party AI callers without exposing provider secrets or writing WordPress content.', 'npcink-toolbox' ),
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
			'npcink-toolbox/validate-content-discoverability-context' => $this->definition(
				__( 'Validate Content Discoverability Context', 'npcink-toolbox' ),
				__( 'Check whether the operator-maintained SEO, AEO, and GEO context has enough fields for third-party AI suggestion workflows.', 'npcink-toolbox' ),
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
			'npcink-toolbox/build-content-discoverability-brief' => $this->definition(
				__( 'Build Discoverability Suggestions', 'npcink-toolbox' ),
				__( 'Build suggestion-only SEO, AEO, and GEO guidance from operator context and supplied post or topic input without writing WordPress content.', 'npcink-toolbox' ),
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
			'npcink-toolbox/build-ai-article-writing-pack' => $this->definition(
				__( 'Build AI Article Writing Pack', 'npcink-toolbox' ),
				__( 'Build one suggestion-only article writing context pack from operator SEO, AEO, and GEO context without writing WordPress content.', 'npcink-toolbox' ),
				array(),
				array( $this, 'build_ai_article_writing_pack' ),
				'cap.toolbox.workflow_suggest',
				array(
					'data_classification' => 'planning_artifact',
					'composition_role'    => 'ai_article_writing_pack',
					'provider_execution'  => 'none',
					'write_posture'       => 'suggestion_only',
				)
			),
		);
	}

	private function definition( string $label, string $description, array $required, callable $callback, string $required_scope, array $meta = array(), array $input_properties = array() ): array {
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
		$properties = array_merge( $properties, $input_properties );

		return array(
			'label'               => $label,
			'description'         => $description,
			'category'            => 'npcink-toolbox',
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
			'project_to_npcink_catalog' => true,
		);
	}

	public function search_image_source( $input = array() ) {
		$input = is_array( $input ) ? $input : array();
		return $this->client->image_candidates(
			sanitize_textarea_field( (string) ( $input['query'] ?? '' ) ),
			array(
				'orientation' => sanitize_key( (string) ( $input['orientation'] ?? '' ) ),
				'color'       => sanitize_key( (string) ( $input['color'] ?? '' ) ),
				'provider'    => sanitize_key( (string) ( $input['provider'] ?? '' ) ),
				'per_page'    => (int) ( $input['per_page'] ?? 8 ),
				'include_ai_generated' => ! empty( $input['include_ai_generated'] ),
				'generation_prompt'     => sanitize_textarea_field( (string) ( $input['generation_prompt'] ?? '' ) ),
				'generated_image_url'   => esc_url_raw( (string) ( $input['generated_image_url'] ?? '' ) ),
				'model'                 => sanitize_text_field( (string) ( $input['model'] ?? '' ) ),
			)
		);
	}

	public function generate_image_candidate( $input = array() ) {
		return $this->client->run_ai_image_generation( is_array( $input ) ? $input : array() );
	}

	public function vector_search( $input = array() ) {
		$input = is_array( $input ) ? $input : array();
		$query = sanitize_textarea_field( (string) ( $input['query'] ?? '' ) );
		$vector = sanitize_textarea_field( (string) ( $input['vector'] ?? '' ) );
		$payload = '' !== trim( $query ) ? $query : $vector;
		$input_type = sanitize_key( (string) ( $input['input_type'] ?? 'auto' ) );
		return $this->client->vector_search( $payload, (int) ( $input['max_results'] ?? 4 ), $input_type );
	}

	public function search_site_knowledge( $input = array() ) {
		return $this->client->search_site_knowledge( is_array( $input ) ? $input : array() );
	}

	public function cloud_web_search( $input = array() ) {
		$input = is_array( $input ) ? $input : array();
		return $this->client->test_cloud_web_search(
			array(
				'query'        => sanitize_textarea_field( (string) ( $input['query'] ?? '' ) ),
				'intent'       => sanitize_key( (string) ( $input['intent'] ?? 'general_research' ) ),
				'max_results'  => (int) ( $input['max_results'] ?? 3 ),
				'recency_days' => (int) ( $input['recency_days'] ?? 7 ),
			)
		);
	}

	public function get_site_knowledge_status( $input = array() ) {
		return $this->client->get_site_knowledge_status( is_array( $input ) ? $input : array() );
	}

	public function request_site_knowledge_sync( $input = array() ) {
		return $this->client->request_site_knowledge_sync( is_array( $input ) ? $input : array() );
	}

	public function build_article_brief( $input = array() ) {
		$input = is_array( $input ) ? $input : array();
		return $this->client->build_article_brief( sanitize_textarea_field( (string) ( $input['topic'] ?? '' ) ) );
	}

	public function build_article_assistant( $input = array() ) {
		return $this->client->build_article_assistant( is_array( $input ) ? $input : array() );
	}

	public function build_article_write_plan( $input = array() ) {
		return $this->client->build_article_write_plan( is_array( $input ) ? $input : array() );
	}

	public function build_article_batch_write_plan( $input = array() ) {
		return $this->client->build_article_batch_write_plan( is_array( $input ) ? $input : array() );
	}

	public function build_article_media_batch_write_plan( $input = array() ) {
		return $this->client->build_article_media_batch_write_plan( is_array( $input ) ? $input : array() );
	}

	public function build_image_candidate_adoption_plan( $input = array() ) {
		return $this->client->build_image_candidate_adoption_plan( is_array( $input ) ? $input : array() );
	}

	public function build_site_knowledge_review_plan( $input = array() ) {
		return $this->client->build_site_knowledge_review_plan( is_array( $input ) ? $input : array() );
	}

	public function build_nightly_inspection_review_plan( $input = array() ) {
		return $this->client->build_nightly_inspection_review_plan( is_array( $input ) ? $input : array() );
	}

	public function build_content_metadata_apply_plan( $input = array() ) {
		return $this->client->build_content_metadata_apply_plan( is_array( $input ) ? $input : array() );
	}

	public function build_media_brief( $input = array() ) {
		$input = is_array( $input ) ? $input : array();
		return $this->client->build_media_brief( sanitize_textarea_field( (string) ( $input['post_context'] ?? '' ) ) );
	}

	public function build_media_derivative_handoff( $input = array() ) {
		return $this->client->build_media_derivative_handoff( is_array( $input ) ? $input : array() );
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

	public function build_ai_article_writing_pack( $input = array() ) {
		return $this->client->build_ai_article_writing_pack( is_array( $input ) ? $input : array() );
	}

	private function can_execute_ability( string $ability_id ): bool {
		return (bool) apply_filters( 'npcink_toolbox_ability_permission', current_user_can( 'manage_options' ), $ability_id );
	}

	private function sanitize_string_list( $value ): array {
		if ( is_array( $value ) ) {
			$items = $value;
		} else {
			$items = preg_split( '/[\r\n,]+/', (string) $value );
		}

		$items = array_map(
			static fn( $item ): string => sanitize_text_field( trim( (string) $item ) ),
			is_array( $items ) ? $items : array()
		);

		return array_values( array_filter( array_unique( $items ) ) );
	}
}
