<?php
/**
 * Read-only Toolbox ability surface metadata.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

defined( 'ABSPATH' ) || exit;

final class Ability_Surface_Metadata {
	public static function definitions(): array {
		return array(
			'publish_preflight'        => self::definition( __( 'Publish Preflight', 'npcink-workflow-toolbox' ), 'editor_default_button', true, 'toolbox_local_optional_cloud', 'seo_meta_core_handoff_preview', 'npcink_owned_default' ),
			'internal_link_candidates' => self::definition( __( 'Internal Link Candidates', 'npcink-workflow-toolbox' ), 'editor_default_button', true, 'toolkit_with_optional_site_knowledge', 'manual_editor_review', 'npcink_owned_default' ),
			'image_candidates'         => self::definition( __( 'Image Candidates', 'npcink-workflow-toolbox' ), 'editor_default_button', true, 'cloud_runtime_via_addon', 'image_candidate_adoption_plan', 'npcink_owned_default' ),
			'article_audio_candidates' => self::definition( __( 'Article Audio Candidates', 'npcink-workflow-toolbox' ), 'editor_default_button', true, 'cloud_runtime_via_addon', 'article_audio_adoption_plan', 'npcink_owned_default' ),
			'site_profile'             => self::definition( __( 'Site Profile', 'npcink-workflow-toolbox' ), 'admin_profile', true, 'local_wordpress_option', 'read_only_context', 'npcink_owned_default' ),
			'full_site_insights'       => self::definition( __( 'Full-site Insights', 'npcink-workflow-toolbox' ), 'admin_overview', true, 'local_snapshot_optional_cloud_analysis', 'site_ops_cloud_analysis_request', 'npcink_owned_default' ),
			'site_knowledge'           => self::definition( __( 'Site Knowledge', 'npcink-workflow-toolbox' ), 'advanced_setup', true, 'cloud_runtime_via_addon', 'search_or_sync_request', 'npcink_owned_default' ),
			'cloud_web_search'         => self::definition( __( 'Cloud Web Search', 'npcink-workflow-toolbox' ), 'advanced_check', true, 'cloud_runtime_via_addon', 'evidence_only', 'npcink_owned_default' ),
			'batch_alt_review_handoff' => self::definition( __( 'Batch ALT Review Handoff', 'npcink-workflow-toolbox' ), 'admin_image_handling', true, 'cloud_runtime_via_addon', 'media_alt_caption_review_plan', 'npcink_owned_default' ),
			'title_suggestions'        => self::definition( __( 'Title suggestions', 'npcink-workflow-toolbox' ), 'editor_route_only_compatibility', false, 'hosted_ai_runtime', 'editor_state_only', 'generic_ai_plugin_overlap_route_only' ),
			'summary_suggestions'      => self::definition( __( 'Summary suggestions', 'npcink-workflow-toolbox' ), 'editor_route_only_compatibility', false, 'hosted_ai_runtime', 'content_metadata_apply_plan', 'generic_ai_plugin_overlap_route_only' ),
			'category_tag_suggestions' => self::definition( __( 'Category and tag suggestions', 'npcink-workflow-toolbox' ), 'editor_route_only_compatibility', false, 'toolkit_and_hosted_ai_runtime', 'content_metadata_apply_plan', 'generic_ai_plugin_overlap_route_only' ),
			'article_checkup'          => self::definition( __( 'Article checkup', 'npcink-workflow-toolbox' ), 'editor_route_only_compatibility', false, 'local_and_hosted_ai_runtime', 'operator_review_only_no_insert', 'generic_ai_plugin_overlap_route_only' ),
			'image_alt_suggestions'    => self::definition( __( 'Current-article ALT suggestions', 'npcink-workflow-toolbox' ), 'editor_route_only_compatibility', false, 'hosted_ai_runtime', 'media_alt_caption_review_plan', 'generic_ai_plugin_overlap_route_only' ),
			'comment_reply_suggestion' => self::definition( __( 'Comment reply suggestions', 'npcink-workflow-toolbox' ), 'editor_route_only_compatibility', false, 'toolkit_runtime', 'operator_review_only_no_comment_write', 'generic_ai_plugin_overlap_route_only' ),
		);
	}

	public static function health_summary( array $state ): array {
		$cloud_ready   = ! empty( $state['cloud_ready'] );
		$profile_ready = ! empty( $state['site_profile_ready'] );
		$route_only    = array_filter(
			self::definitions(),
			static fn( array $definition ): bool => empty( $definition['default_visible'] )
		);

		return array(
			array(
				'id'          => 'site_profile',
				'label'       => __( 'Site Profile', 'npcink-workflow-toolbox' ),
				'status'      => $profile_ready ? 'ok' : 'neutral',
				'status_text' => $profile_ready ? __( 'Ready', 'npcink-workflow-toolbox' ) : __( 'Needs brief', 'npcink-workflow-toolbox' ),
				'description' => __( 'Local suggestion context for SEO, AEO, GEO, claims, audience, and tone.', 'npcink-workflow-toolbox' ),
			),
			array(
				'id'          => 'cloud_runtime',
				'label'       => __( 'Cloud runtime', 'npcink-workflow-toolbox' ),
				'status'      => $cloud_ready ? 'ok' : 'warning',
				'status_text' => $cloud_ready ? __( 'Connected', 'npcink-workflow-toolbox' ) : __( 'Needs connection', 'npcink-workflow-toolbox' ),
				'description' => __( 'Search, image, Site Knowledge, hosted AI, and AI image checks use Cloud or a host runtime seam.', 'npcink-workflow-toolbox' ),
			),
			array(
				'id'          => 'default_entries',
				'label'       => __( 'Default entries', 'npcink-workflow-toolbox' ),
				'status'      => 'ok',
				'status_text' => __( 'Npcink workflows', 'npcink-workflow-toolbox' ),
				'description' => __( 'Editor defaults stay on preflight, internal links, image candidates, article audio, and governed handoff work.', 'npcink-workflow-toolbox' ),
			),
			array(
				'id'          => 'route_only_compatibility',
				'label'       => __( 'Route-only compatibility', 'npcink-workflow-toolbox' ),
				'status'      => 'neutral',
				'status_text' => sprintf(
					/* translators: %d: number of route-only compatibility capabilities. */
					__( '%d hidden entries', 'npcink-workflow-toolbox' ),
					count( $route_only )
				),
				'description' => __( 'Generic AI overlap stays callable for compatibility but is not restored as default Toolbox UI.', 'npcink-workflow-toolbox' ),
			),
			array(
				'id'          => 'handoff_boundary',
				'label'       => __( 'Core handoff boundary', 'npcink-workflow-toolbox' ),
				'status'      => 'neutral',
				'status_text' => __( 'Review required', 'npcink-workflow-toolbox' ),
				'description' => __( 'Write-like outcomes remain Core/Adapter/Abilities handoffs; this summary creates no proposals and writes nothing.', 'npcink-workflow-toolbox' ),
			),
		);
	}

	private static function definition( string $label, string $surface, bool $default_visible, string $runtime_owner, string $handoff_path, string $overlap_policy ): array {
		return array(
			'label'                  => $label,
			'owner'                  => 'npcink-workflow-toolbox',
			'surface'                => $surface,
			'default_visible'        => $default_visible,
			'write_posture'          => 'suggestion_only',
			'direct_wordpress_write' => false,
			'runtime_owner'          => $runtime_owner,
			'handoff_path'           => $handoff_path,
			'overlap_policy'         => $overlap_policy,
		);
	}
}
