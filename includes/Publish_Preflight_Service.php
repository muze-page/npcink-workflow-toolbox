<?php
/**
 * Builds publish-preflight review artifacts from editor and evidence context.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

defined( 'ABSPATH' ) || exit;

final class Publish_Preflight_Service {
	/**
	 * Builds all deterministic sections for the publish-preflight flow.
	 *
	 * Remote evidence is resolved by the controller before it reaches this
	 * service so artifact assembly stays deterministic and directly testable.
	 *
	 * @param array<string,mixed> $context Editor post context.
	 * @param array<string,mixed> $discoverability Discoverability evidence.
	 * @param array<string,mixed> $duplicate_check Site Knowledge evidence.
	 * @return array<string,mixed>
	 */
	public function build_sections( array $context, array $discoverability, array $duplicate_check ): array {
		$sections = array(
			'checks'          => $this->local_checks( $context ),
			'duplicate_check' => $duplicate_check,
			'seo_handoff'     => $this->seo_handoff_preview( $context, $discoverability ),
		);
		$sections['pre_publish_review'] = $this->pre_publish_review( $context, $sections );

		return $sections;
	}

	/**
	 * Builds local checks shared by progressive recommendations and preflight.
	 *
	 * @param array<string,mixed> $context Editor post context.
	 * @return array<string,mixed>
	 */
	public function local_checks( array $context ): array {
		$checks = array(
			array(
				'id'     => 'title',
				'status' => '' !== trim( (string) ( $context['title'] ?? '' ) ) ? 'ok' : 'warning',
				'label'  => __( 'Title', 'npcink-workflow-toolbox' ),
				'detail' => '' !== trim( (string) ( $context['title'] ?? '' ) ) ? __( 'Title is present.', 'npcink-workflow-toolbox' ) : __( 'Add a specific title before publishing.', 'npcink-workflow-toolbox' ),
			),
			array(
				'id'     => 'excerpt',
				'status' => '' !== trim( (string) ( $context['excerpt'] ?? '' ) ) ? 'ok' : 'warning',
				'label'  => __( 'Excerpt', 'npcink-workflow-toolbox' ),
				'detail' => '' !== trim( (string) ( $context['excerpt'] ?? '' ) ) ? __( 'Excerpt is present.', 'npcink-workflow-toolbox' ) : __( 'Add an excerpt or meta description candidate.', 'npcink-workflow-toolbox' ),
			),
			array(
				'id'     => 'terms',
				'status' => ! empty( $context['category_ids'] ) || ! empty( $context['tag_ids'] ) ? 'ok' : 'warning',
				'label'  => __( 'Terms', 'npcink-workflow-toolbox' ),
				'detail' => ! empty( $context['category_ids'] ) || ! empty( $context['tag_ids'] ) ? __( 'At least one category or tag is selected.', 'npcink-workflow-toolbox' ) : __( 'Review category and tag candidates before publishing.', 'npcink-workflow-toolbox' ),
			),
			array(
				'id'     => 'featured_media',
				'status' => ! empty( $context['featured_media'] ) ? 'ok' : 'warning',
				'label'  => __( 'Featured image', 'npcink-workflow-toolbox' ),
				'detail' => ! empty( $context['featured_media'] ) ? __( 'Featured image is selected.', 'npcink-workflow-toolbox' ) : __( 'Review image candidates or select a featured image.', 'npcink-workflow-toolbox' ),
			),
		);

		return array(
			'candidate_type'         => 'publish_preflight_checks',
			'write_posture'          => 'suggestion_only',
			'direct_wordpress_write' => false,
			'items'                  => $checks,
		);
	}

	/**
	 * Builds the governed, single-post SEO metadata handoff preview.
	 *
	 * @param array<string,mixed> $context Editor post context.
	 * @param array<string,mixed> $discoverability Discoverability evidence.
	 * @return array<string,mixed>
	 */
	public function seo_handoff_preview( array $context, array $discoverability ): array {
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
				'title'      => __( 'Review SEO meta for the current post', 'npcink-workflow-toolbox' ),
				'summary'    => __( 'Single-post SEO title and description candidate prepared by Toolbox for Core-governed review.', 'npcink-workflow-toolbox' ),
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
					'name'   => __( 'SEO title candidate', 'npcink-workflow-toolbox' ),
					'value'  => $seo_title,
					'status' => '' !== $seo_title ? 'review_required' : 'missing',
				),
				array(
					'name'   => __( 'SEO description candidate', 'npcink-workflow-toolbox' ),
					'value'  => $seo_description,
					'status' => '' !== $seo_description ? 'review_required' : 'missing',
				),
				array(
					'name'   => __( 'Core handoff', 'npcink-workflow-toolbox' ),
					'detail' => __( 'Submit only after the editor confirms the title and description do not add unsupported claims.', 'npcink-workflow-toolbox' ),
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

	/**
	 * Builds the unified pre-publish review from deterministic section data.
	 *
	 * @param array<string,mixed> $context Editor post context.
	 * @param array<string,mixed> $sections Preflight sections.
	 * @return array<string,mixed>
	 */
	private function pre_publish_review( array $context, array $sections ): array {
		$duplicate_items = $this->related_content_items( is_array( $sections['duplicate_check'] ?? null ) ? $sections['duplicate_check'] : array() );
		$seo_handoff     = is_array( $sections['seo_handoff'] ?? null ) ? $sections['seo_handoff'] : array();
		$items           = array(
			$this->review_item(
				'summary',
				'' !== trim( (string) ( $context['excerpt'] ?? '' ) ) ? 'ok' : 'warning',
				'' !== trim( (string) ( $context['excerpt'] ?? '' ) ) ? __( 'Excerpt is present for archives and sharing contexts.', 'npcink-workflow-toolbox' ) : __( 'Run summary suggestions before publishing if the excerpt is empty.', 'npcink-workflow-toolbox' ),
				'summary_suggestions'
			),
			$this->review_item(
				'categories',
				! empty( $context['category_ids'] ) ? 'ok' : 'warning',
				! empty( $context['category_ids'] ) ? __( 'At least one category is selected.', 'npcink-workflow-toolbox' ) : __( 'Review category suggestions before publishing.', 'npcink-workflow-toolbox' ),
				'category_suggestions'
			),
			$this->review_item(
				'tags',
				! empty( $context['tag_ids'] ) ? 'ok' : 'warning',
				! empty( $context['tag_ids'] ) ? __( 'At least one tag is selected.', 'npcink-workflow-toolbox' ) : __( 'Review existing tag suggestions before creating any new vocabulary.', 'npcink-workflow-toolbox' ),
				'tag_suggestions'
			),
			$this->review_item(
				'featured_image',
				! empty( $context['featured_media'] ) ? 'ok' : 'warning',
				! empty( $context['featured_media'] ) ? __( 'Featured image is selected.', 'npcink-workflow-toolbox' ) : __( 'Review image candidates or select an existing media attachment.', 'npcink-workflow-toolbox' ),
				'image_candidates'
			),
			$this->review_item(
				'internal_links',
				'review',
				__( 'Run internal link candidates and insert only the links a human editor accepts.', 'npcink-workflow-toolbox' ),
				'internal_links'
			),
			$this->review_item(
				'seo_meta',
				! empty( $seo_handoff['proposal_ready'] ) ? 'review' : 'warning',
				! empty( $seo_handoff['proposal_ready'] ) ? __( 'SEO title and description candidates are ready for Core-governed review.', 'npcink-workflow-toolbox' ) : __( 'SEO handoff needs a post id, title, and description candidate.', 'npcink-workflow-toolbox' ),
				'seo_meta_single_post_handoff'
			),
			$this->review_item(
				'duplicate_risk',
				empty( $duplicate_items ) ? 'ok' : 'review',
				empty( $duplicate_items ) ? __( 'No duplicate-risk candidates were returned by Site Knowledge.', 'npcink-workflow-toolbox' ) : __( 'Related public content was found; compare overlap before publishing.', 'npcink-workflow-toolbox' ),
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

	/**
	 * Normalizes one review row.
	 *
	 * @return array<string,string>
	 */
	private function review_item( string $name, string $status, string $detail, string $next_action ): array {
		return array(
			'name'        => sanitize_key( $name ),
			'status'      => sanitize_key( $status ),
			'detail'      => sanitize_text_field( $detail ),
			'next_action' => sanitize_key( $next_action ),
		);
	}

	/**
	 * Extracts related-content rows from current and compatibility shapes.
	 *
	 * @param array<string,mixed> $related_content Site Knowledge section.
	 * @return array<int,array<string,mixed>>
	 */
	private function related_content_items( array $related_content ): array {
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
}
