<?php
/**
 * Builds the future Cloud analysis request for Site Ops Insights.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

defined( 'ABSPATH' ) || exit;

final class Site_Ops_Cloud_Request_Builder {
	public const CONTRACT_VERSION        = 'site_ops_cloud_analysis_request.v1';
	public const RESULT_CONTRACT_VERSION = 'site_ops_cloud_analysis_result.v1';

	/**
	 * Builds a bounded, privacy-minimized request packet for future Cloud analysis.
	 *
	 * @param array<string,mixed> $snapshot Local Site Ops snapshot.
	 * @param array<string,mixed> $insight_pack Local Site Ops insight pack.
	 * @param array<string,mixed> $context Runtime and operator context.
	 * @return array<string,mixed>
	 */
	public function build( array $snapshot, array $insight_pack, array $context = array() ): array {
		return array(
			'artifact_type'              => 'site_ops_cloud_analysis_request',
			'contract_version'           => self::CONTRACT_VERSION,
			'version'                    => 1,
			'request_id'                 => $this->request_id( $snapshot ),
			'site_id'                    => $this->string_value( $snapshot, 'site_id', 'local-site' ),
			'generated_at'               => $this->string_value( $snapshot, 'generated_at' ),
			'source_pack_contract'       => $this->string_value( $insight_pack, 'contract_version', Site_Ops_Insight_Builder::CONTRACT_VERSION ),
			'expected_result_contract'   => self::RESULT_CONTRACT_VERSION,
			'cloud_role'                => 'runtime_detail',
			'execution_pattern'          => 'whole_run_offload',
			'data_classification'        => 'public_site_aggregate',
			'storage_mode'               => 'cloud_runtime_policy',
			'write_posture'              => 'suggestion_only',
			'direct_wordpress_write'     => false,
			'core_proposal_created'      => false,
			'local_runtime_created'      => false,
			'local_scheduler_created'    => false,
			'input'                      => array(
				'site'              => $this->site_summary( $snapshot ),
				'local_summary'     => is_array( $insight_pack['summary'] ?? null ) ? $insight_pack['summary'] : array(),
				'sample_summaries'  => $this->sample_summaries( $snapshot ),
				'local_findings'    => $this->local_findings( $insight_pack ),
				'blocked_items'     => $this->blocked_items( $insight_pack ),
				'analysis_tasks'    => $this->analysis_tasks(),
				'operator_context'  => $this->operator_context( $context ),
			),
			'expected_result_shape'      => $this->expected_result_shape(),
			'safety'                    => array(
				'cloud_request_prepared'         => true,
				'cloud_called'                   => false,
				'cloud_is_runtime_detail_only'   => true,
				'operator_review_required'       => true,
				'direct_wordpress_write'         => false,
				'automatic_core_proposal'        => false,
				'local_queue_created'            => false,
				'local_scheduler_created'        => false,
				'custom_tables_created'          => false,
				'comment_text_returned'          => false,
				'comment_author_email_returned'  => false,
				'comment_ip_returned'            => false,
				'comment_user_agent_returned'    => false,
			),
		);
	}

	/**
	 * @param array<string,mixed> $snapshot Source snapshot.
	 * @return array<string,mixed>
	 */
	private function site_summary( array $snapshot ): array {
		$site = is_array( $snapshot['site'] ?? null ) ? $snapshot['site'] : array();

		return array(
			'name'        => $this->text_value( $site, 'name' ),
			'description' => $this->text_value( $site, 'description' ),
			'url_host'    => $this->host_value( $site, 'url_host' ),
		);
	}

	/**
	 * @param array<string,mixed> $snapshot Source snapshot.
	 * @return array<string,mixed>
	 */
	private function sample_summaries( array $snapshot ): array {
		$posts      = $this->array_value( $snapshot, 'posts' );
		$media      = $this->array_value( $snapshot, 'media' );
		$comments   = is_array( $snapshot['comments'] ?? null ) ? $snapshot['comments'] : array();
		$taxonomies = is_array( $snapshot['taxonomies'] ?? null ) ? $snapshot['taxonomies'] : array();

		return array(
			'posts'      => $this->post_sample_summary( $posts, $this->string_value( $snapshot, 'generated_at' ) ),
			'media'      => $this->media_sample_summary( $media, $posts ),
			'comments'   => $this->comment_signal_summary( $comments ),
			'taxonomies' => $this->taxonomy_summary( $taxonomies ),
		);
	}

	/**
	 * @param array<int,mixed> $posts Local post summary rows.
	 * @return array<string,int>
	 */
	private function post_sample_summary( array $posts, string $generated_at ): array {
		$stale            = 0;
		$short            = 0;
		$no_links         = 0;
		$missing_meta     = 0;
		$missing_terms    = 0;
		$commented        = 0;
		$missing_alt_refs = 0;

		foreach ( $posts as $post ) {
			if ( ! is_array( $post ) ) {
				continue;
			}
			if ( $this->age_days( $this->string_value( $post, 'modified_at' ), $generated_at ) >= 180 ) {
				++$stale;
			}
			if ( (int) ( $post['word_count'] ?? 0 ) > 0 && (int) ( $post['word_count'] ?? 0 ) < 300 ) {
				++$short;
			}
			if ( (int) ( $post['internal_link_count'] ?? 0 ) <= 0 ) {
				++$no_links;
			}
			if ( empty( $post['meta_description_present'] ) || empty( $post['excerpt_present'] ) ) {
				++$missing_meta;
			}
			if ( empty( $post['categories'] ) || empty( $post['tags'] ) ) {
				++$missing_terms;
			}
			if ( (int) ( $post['approved_comment_count'] ?? 0 ) > 0 ) {
				++$commented;
			}
			$missing_alt_refs += (int) ( $post['missing_alt_count'] ?? 0 );
		}

		return array(
			'sampled_count'           => count( $posts ),
			'stale_180d_count'        => $stale,
			'short_content_count'     => $short,
			'no_internal_link_count'  => $no_links,
			'missing_meta_count'      => $missing_meta,
			'missing_terms_count'     => $missing_terms,
			'commented_item_count'    => $commented,
			'missing_alt_ref_count'   => $missing_alt_refs,
		);
	}

	/**
	 * @param array<int,mixed> $media Local media rows.
	 * @param array<int,mixed> $posts Local post rows.
	 * @return array<string,int>
	 */
	private function media_sample_summary( array $media, array $posts ): array {
		$missing_alt      = 0;
		$missing_caption  = 0;
		$referenced_gaps  = 0;

		foreach ( $media as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			if ( empty( $item['alt_present'] ) ) {
				++$missing_alt;
			}
			if ( empty( $item['caption_present'] ) ) {
				++$missing_caption;
			}
		}
		foreach ( $posts as $post ) {
			if ( is_array( $post ) ) {
				$referenced_gaps += (int) ( $post['missing_alt_count'] ?? 0 );
			}
		}

		return array(
			'sampled_count'            => count( $media ),
			'missing_alt_count'        => $missing_alt,
			'missing_caption_count'    => $missing_caption,
			'referenced_alt_gap_count' => $referenced_gaps,
		);
	}

	/**
	 * @param array<string,mixed> $comments Comment summary.
	 * @return array<string,mixed>
	 */
	private function comment_signal_summary( array $comments ): array {
		return array(
			'approved_total'      => (int) ( $comments['approved_total'] ?? 0 ),
			'pending_total'       => (int) ( $comments['pending_total'] ?? 0 ),
			'recent_sample_count' => (int) ( $comments['recent_sample_count'] ?? 0 ),
			'question_like_count' => (int) ( $comments['question_like_count'] ?? 0 ),
			'long_comment_count'  => (int) ( $comments['long_comment_count'] ?? 0 ),
			'active_post_count'   => (int) ( $comments['active_post_count'] ?? 0 ),
			'privacy'             => array(
				'approved_public_comments_only' => true,
				'comment_text_returned'         => false,
				'author_email_returned'         => false,
				'ip_address_returned'           => false,
				'user_agent_returned'           => false,
			),
		);
	}

	/**
	 * @param array<string,mixed> $taxonomies Taxonomy summary.
	 * @return array<string,array<string,int>>
	 */
	private function taxonomy_summary( array $taxonomies ): array {
		$summary = array();
		foreach ( array( 'category', 'post_tag' ) as $taxonomy ) {
			$source = is_array( $taxonomies[ $taxonomy ] ?? null ) ? $taxonomies[ $taxonomy ] : array();
			$summary[ $taxonomy ] = array(
				'total'       => (int) ( $source['total'] ?? 0 ),
				'empty_count' => (int) ( $source['empty_count'] ?? 0 ),
				'low_count'   => (int) ( $source['low_count'] ?? 0 ),
			);
		}

		return $summary;
	}

	/**
	 * @param array<string,mixed> $insight_pack Local insight pack.
	 * @return array<int,array<string,mixed>>
	 */
	private function local_findings( array $insight_pack ): array {
		$findings = is_array( $insight_pack['top_findings'] ?? null ) ? array_slice( $insight_pack['top_findings'], 0, 8 ) : array();
		$items    = array();
		foreach ( $findings as $finding ) {
			if ( ! is_array( $finding ) ) {
				continue;
			}
			$items[] = array(
				'id'                 => sanitize_key( (string) ( $finding['id'] ?? '' ) ),
				'issue_type'         => sanitize_key( (string) ( $finding['issue_type'] ?? '' ) ),
				'severity'           => sanitize_key( (string) ( $finding['severity'] ?? '' ) ),
				'priority_score'     => max( 0, min( 100, (int) ( $finding['priority_score'] ?? 0 ) ) ),
				'evidence_summary'   => $this->clean_text( (string) ( $finding['evidence_summary'] ?? '' ) ),
				'recommended_action' => $this->clean_text( (string) ( $finding['recommended_action'] ?? '' ) ),
				'write_boundary'     => sanitize_key( (string) ( $finding['write_boundary'] ?? 'suggestion_only' ) ),
				'source_refs'        => $this->source_refs( is_array( $finding['source_refs'] ?? null ) ? $finding['source_refs'] : array() ),
			);
		}

		return $items;
	}

	/**
	 * @param array<int,mixed> $refs Source refs.
	 * @return array<int,array<string,mixed>>
	 */
	private function source_refs( array $refs ): array {
		$items = array();
		foreach ( array_slice( $refs, 0, 5 ) as $ref ) {
			if ( ! is_array( $ref ) ) {
				continue;
			}
			$items[] = array(
				'object_type' => sanitize_key( (string) ( $ref['object_type'] ?? 'post' ) ),
				'object_id'   => max( 0, (int) ( $ref['object_id'] ?? 0 ) ),
				'title'       => $this->clean_text( (string) ( $ref['title'] ?? '' ) ),
			);
		}

		return $items;
	}

	/**
	 * @param array<string,mixed> $insight_pack Local insight pack.
	 * @return array<int,array<string,mixed>>
	 */
	private function blocked_items( array $insight_pack ): array {
		$blocked = is_array( $insight_pack['blocked_items'] ?? null ) ? array_slice( $insight_pack['blocked_items'], 0, 8 ) : array();
		$items   = array();
		foreach ( $blocked as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$items[] = array(
				'id'     => sanitize_key( (string) ( $item['id'] ?? '' ) ),
				'reason' => sanitize_key( (string) ( $item['reason'] ?? '' ) ),
				'next'   => sanitize_key( (string) ( $item['next'] ?? '' ) ),
			);
		}

		return $items;
	}

	/**
	 * @return array<int,string>
	 */
	private function analysis_tasks(): array {
		return array(
			'prioritize_operator_review_queue',
			'explain_metric_drivers',
			'identify_repeat_comment_needs',
			'rank_content_refresh_candidates',
			'rank_media_metadata_review_candidates',
			'prepare_core_handoff_candidates_without_creating_proposals',
		);
	}

	/**
	 * @param array<string,mixed> $context Runtime context.
	 * @return array<string,mixed>
	 */
	private function operator_context( array $context ): array {
		return array(
			'content_context_ready' => ! empty( $context['content_context_ready'] ),
			'cloud_ready'           => ! empty( $context['cloud_ready'] ),
			'requested_by'          => 'wordpress_administrator',
			'next_local_action'     => ! empty( $context['cloud_ready'] ) ? 'send_to_cloud_runtime_when_available' : 'connect_or_verify_cloud_addon',
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function expected_result_shape(): array {
		return array(
			'contract_version'      => self::RESULT_CONTRACT_VERSION,
			'allowed_outputs'       => array(
				'priority_queue',
				'trend_notes',
				'confidence',
				'evidence_refs',
				'blocked_items',
				'core_handoff_candidates',
				'operator_next_actions',
			),
			'required_safety'       => array(
				'write_posture'          => 'suggestion_only',
				'direct_wordpress_write' => false,
				'core_proposal_created'  => false,
				'cloud_scheduler_truth'  => false,
			),
			'disallowed_outputs'    => array(
				'full_comment_text',
				'private_comment_author_contact',
				'private_comment_network_metadata',
				'wordpress_write_action',
				'automatic_proposal_creation',
				'local_runtime_instruction',
			),
		);
	}

	/**
	 * @param array<string,mixed> $snapshot Source snapshot.
	 */
	private function request_id( array $snapshot ): string {
		$run_id = sanitize_key( $this->string_value( $snapshot, 'run_id', 'site-ops-insights-preview' ) );
		return '' === $run_id ? 'site-ops-cloud-analysis-preview' : $run_id . '-cloud-analysis';
	}

	private function age_days( string $date, string $generated_at ): int {
		$date_ts = '' !== $date ? strtotime( $date ) : false;
		$now_ts  = '' !== $generated_at ? strtotime( $generated_at ) : false;
		if ( false === $date_ts || false === $now_ts || $now_ts <= $date_ts ) {
			return 0;
		}

		return (int) floor( ( $now_ts - $date_ts ) / 86400 );
	}

	/**
	 * @param array<string,mixed> $item Source.
	 * @return array<int,mixed>
	 */
	private function array_value( array $item, string $key ): array {
		$value = $item[ $key ] ?? array();
		return is_array( $value ) ? array_values( $value ) : array();
	}

	/**
	 * @param array<string,mixed> $item Source.
	 */
	private function string_value( array $item, string $key, string $default = '' ): string {
		$value = $item[ $key ] ?? $default;
		return is_scalar( $value ) ? (string) $value : $default;
	}

	/**
	 * @param array<string,mixed> $item Source.
	 */
	private function text_value( array $item, string $key ): string {
		return $this->clean_text( $this->string_value( $item, $key ) );
	}

	/**
	 * @param array<string,mixed> $item Source.
	 */
	private function host_value( array $item, string $key ): string {
		return strtolower( preg_replace( '/[^a-zA-Z0-9.\-]/', '', $this->string_value( $item, $key ) ) ?: '' );
	}

	private function clean_text( string $value ): string {
		return trim( sanitize_text_field( wp_strip_all_tags( $value ) ) );
	}
}
