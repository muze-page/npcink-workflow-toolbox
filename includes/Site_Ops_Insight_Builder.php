<?php
/**
 * Builds review-only Site Ops Insights from a bounded local snapshot.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

defined( 'ABSPATH' ) || exit;

final class Site_Ops_Insight_Builder {
	public const CONTRACT_VERSION = 'site_ops_insight_pack.v1';

	/**
	 * @param array<string,mixed> $snapshot Site ops snapshot.
	 * @param array<string,mixed> $context Site context readiness and runtime hints.
	 * @return array<string,mixed>
	 */
	public function build( array $snapshot, array $context = array() ): array {
		$posts      = $this->array_value( $snapshot, 'posts' );
		$media      = $this->array_value( $snapshot, 'media' );
		$comments   = is_array( $snapshot['comments'] ?? null ) ? $snapshot['comments'] : array();
		$taxonomies = is_array( $snapshot['taxonomies'] ?? null ) ? $snapshot['taxonomies'] : array();
		$findings   = array();

		$this->add_content_freshness_finding( $posts, $snapshot, $findings );
		$this->add_content_quality_finding( $posts, $findings );
		$this->add_metadata_finding( $posts, $findings );
		$this->add_comment_finding( $comments, $findings );
		$this->add_media_finding( $media, $posts, $findings );
		$this->add_taxonomy_finding( $taxonomies, $findings );
		$this->add_context_finding( $context, $findings );
		$this->add_site_knowledge_finding( $context, $findings );

		usort(
			$findings,
			static function ( array $left, array $right ): int {
				$left_score  = (int) ( $left['priority_score'] ?? 0 );
				$right_score = (int) ( $right['priority_score'] ?? 0 );
				if ( $left_score === $right_score ) {
					return strcmp( (string) ( $left['id'] ?? '' ), (string) ( $right['id'] ?? '' ) );
				}

				return $right_score <=> $left_score;
			}
		);

		$findings = array_slice( $findings, 0, 8 );

		return array(
			'artifact_type'          => 'site_ops_insight_pack',
			'contract_version'       => self::CONTRACT_VERSION,
			'version'                => 1,
			'run_id'                 => $this->string_value( $snapshot, 'run_id', 'site-ops-insights-preview' ),
			'site_id'                => $this->string_value( $snapshot, 'site_id', 'local-site' ),
			'generated_at'           => $this->string_value( $snapshot, 'generated_at' ),
			'write_posture'          => 'suggestion_only',
			'direct_wordpress_write' => false,
			'cloud_required'         => false,
			'core_proposal_created'  => false,
			'summary'                => $this->summary( $posts, $media, $comments, $taxonomies, $findings ),
			'data_sources'           => $this->data_sources( $posts, $media, $comments, $taxonomies, $context ),
			'top_findings'           => $findings,
			'opportunities'          => $this->opportunities( $findings ),
			'blocked_items'          => $this->blocked_items( $context ),
			'safety'                 => array(
				'operator_review_required'      => true,
				'direct_wordpress_write'        => false,
				'automatic_core_proposal'       => false,
				'comment_text_returned'         => false,
				'comment_author_email_returned' => false,
				'comment_ip_returned'           => false,
				'cloud_called'                  => false,
				'local_scheduler_created'       => false,
				'custom_tables_created'         => false,
			),
		);
	}

	/**
	 * @param array<int,mixed>               $posts Posts.
	 * @param array<string,mixed>            $snapshot Snapshot.
	 * @param array<int,array<string,mixed>> $findings Findings.
	 */
	private function add_content_freshness_finding( array $posts, array $snapshot, array &$findings ): void {
		$generated_at = $this->string_value( $snapshot, 'generated_at' );
		$stale_posts  = array();
		foreach ( $posts as $post ) {
			if ( ! is_array( $post ) ) {
				continue;
			}
			if ( $this->age_days( $this->string_value( $post, 'modified_at' ), $generated_at ) >= 180 ) {
				$stale_posts[] = $post;
			}
		}
		if ( empty( $stale_posts ) ) {
			return;
		}

		$with_comments = 0;
		foreach ( $stale_posts as $post ) {
			if ( (int) ( $post['approved_comment_count'] ?? 0 ) > 0 ) {
				++$with_comments;
			}
		}

		$findings[] = $this->finding(
			'stale_content_backlog',
			__( 'Old content needs a refresh queue', 'npcink-workflow-toolbox' ),
			'content_freshness',
			min( 95, 55 + count( $stale_posts ) * 5 + $with_comments * 5 ),
			$with_comments > 0 ? 'high' : 'medium',
			sprintf(
				/* translators: 1: stale item count, 2: stale item count with comments. */
				__( '%1$d sampled public posts or pages have not been modified for 180+ days; %2$d still have approved comments.', 'npcink-workflow-toolbox' ),
				count( $stale_posts ),
				$with_comments
			),
			__( 'Older but still active content can reduce reader trust and search freshness.', 'npcink-workflow-toolbox' ),
			__( 'Review the oldest active items first, then prepare refresh notes or a Core-governed update plan.', 'npcink-workflow-toolbox' ),
			'core_handoff_candidate',
			array_slice( $this->object_refs( $stale_posts ), 0, 5 )
		);
	}

	/**
	 * @param array<int,mixed>               $posts Posts.
	 * @param array<int,array<string,mixed>> $findings Findings.
	 */
	private function add_content_quality_finding( array $posts, array &$findings ): void {
		$thin_posts        = array();
		$no_internal_links = array();
		foreach ( $posts as $post ) {
			if ( ! is_array( $post ) ) {
				continue;
			}
			if ( (int) ( $post['word_count'] ?? 0 ) > 0 && (int) ( $post['word_count'] ?? 0 ) < 300 ) {
				$thin_posts[] = $post;
			}
			if ( (int) ( $post['internal_link_count'] ?? 0 ) <= 0 ) {
				$no_internal_links[] = $post;
			}
		}
		if ( empty( $thin_posts ) && empty( $no_internal_links ) ) {
			return;
		}

		$findings[] = $this->finding(
			'content_depth_and_linking_gap',
			__( 'Some content lacks depth or internal paths', 'npcink-workflow-toolbox' ),
			'content_quality',
			min( 90, 45 + count( $thin_posts ) * 6 + count( $no_internal_links ) * 4 ),
			count( $thin_posts ) + count( $no_internal_links ) >= 5 ? 'high' : 'medium',
			sprintf(
				/* translators: 1: thin item count, 2: missing internal link count. */
				__( '%1$d sampled items are short; %2$d have no recorded internal links.', 'npcink-workflow-toolbox' ),
				count( $thin_posts ),
				count( $no_internal_links )
			),
			__( 'Thin pages and missing internal paths make it harder for readers and AI systems to understand the site map.', 'npcink-workflow-toolbox' ),
			__( 'Prioritize internal-link review and content-depth review before creating new articles on the same topics.', 'npcink-workflow-toolbox' ),
			'manual_review_only',
			array_slice( $this->object_refs( array_merge( $thin_posts, $no_internal_links ) ), 0, 5 )
		);
	}

	/**
	 * @param array<int,mixed>               $posts Posts.
	 * @param array<int,array<string,mixed>> $findings Findings.
	 */
	private function add_metadata_finding( array $posts, array &$findings ): void {
		$missing_meta  = array();
		$missing_terms = array();
		foreach ( $posts as $post ) {
			if ( ! is_array( $post ) ) {
				continue;
			}
			if ( empty( $post['meta_description_present'] ) || empty( $post['excerpt_present'] ) ) {
				$missing_meta[] = $post;
			}
			if ( empty( $post['categories'] ) || empty( $post['tags'] ) ) {
				$missing_terms[] = $post;
			}
		}
		if ( empty( $missing_meta ) && empty( $missing_terms ) ) {
			return;
		}

		$findings[] = $this->finding(
			'metadata_review_backlog',
			__( 'Metadata review backlog is visible', 'npcink-workflow-toolbox' ),
			'metadata',
			min( 88, 44 + count( $missing_meta ) * 5 + count( $missing_terms ) * 4 ),
			count( $missing_meta ) + count( $missing_terms ) >= 5 ? 'high' : 'medium',
			sprintf(
				/* translators: 1: missing meta count, 2: missing taxonomy count. */
				__( '%1$d sampled items need excerpt or meta-description review; %2$d need category or tag review.', 'npcink-workflow-toolbox' ),
				count( $missing_meta ),
				count( $missing_terms )
			),
			__( 'Weak metadata reduces snippet quality and makes suggestion workflows less grounded.', 'npcink-workflow-toolbox' ),
			__( 'Use editor metadata suggestions for individual posts, then hand accepted values to Core proposals.', 'npcink-workflow-toolbox' ),
			'core_handoff_candidate',
			array_slice( $this->object_refs( array_merge( $missing_meta, $missing_terms ) ), 0, 5 )
		);
	}

	/**
	 * @param array<string,mixed>            $comments Comments summary.
	 * @param array<int,array<string,mixed>> $findings Findings.
	 */
	private function add_comment_finding( array $comments, array &$findings ): void {
		$questions = (int) ( $comments['question_like_count'] ?? 0 );
		$long      = (int) ( $comments['long_comment_count'] ?? 0 );
		$pending   = (int) ( $comments['pending_total'] ?? 0 );
		if ( $questions <= 0 && $long <= 0 && $pending <= 0 ) {
			return;
		}

		$findings[] = $this->finding(
			'comment_signal_review',
			__( 'Comments contain support and follow-up signals', 'npcink-workflow-toolbox' ),
			'comments',
			min( 86, 40 + $questions * 7 + $long * 4 + min( 20, $pending ) ),
			$questions >= 3 || $pending >= 10 ? 'high' : 'medium',
			sprintf(
				/* translators: 1: question-like comments, 2: long comments, 3: pending comments. */
				__( 'The approved comment sample includes %1$d question-like comments and %2$d longer comments; %3$d comments are pending moderation.', 'npcink-workflow-toolbox' ),
				$questions,
				$long,
				$pending
			),
			__( 'Comment patterns can reveal missing FAQ, troubleshooting, or follow-up content needs.', 'npcink-workflow-toolbox' ),
			__( 'Review high-signal public comments manually; convert repeated needs into FAQ or article-refresh notes.', 'npcink-workflow-toolbox' ),
			'manual_review_only',
			array()
		);
	}

	/**
	 * @param array<int,mixed>               $media Media.
	 * @param array<int,mixed>               $posts Posts.
	 * @param array<int,array<string,mixed>> $findings Findings.
	 */
	private function add_media_finding( array $media, array $posts, array &$findings ): void {
		$missing_alt      = array();
		$missing_captions = array();
		foreach ( $media as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			if ( empty( $item['alt_present'] ) ) {
				$missing_alt[] = $item;
			}
			if ( empty( $item['caption_present'] ) ) {
				$missing_captions[] = $item;
			}
		}
		$post_missing_alt = 0;
		foreach ( $posts as $post ) {
			if ( is_array( $post ) ) {
				$post_missing_alt += (int) ( $post['missing_alt_count'] ?? 0 );
			}
		}
		if ( empty( $missing_alt ) && empty( $missing_captions ) && $post_missing_alt <= 0 ) {
			return;
		}

		$findings[] = $this->finding(
			'media_metadata_debt',
			__( 'Media metadata needs review', 'npcink-workflow-toolbox' ),
			'media',
			min( 90, 42 + count( $missing_alt ) * 6 + count( $missing_captions ) * 3 + $post_missing_alt * 3 ),
			count( $missing_alt ) + $post_missing_alt >= 5 ? 'high' : 'medium',
			sprintf(
				/* translators: 1: missing attachment alt count, 2: missing caption count, 3: referenced image alt gaps. */
				__( '%1$d sampled attachments lack ALT text, %2$d lack captions, and sampled posts reference %3$d image ALT gaps.', 'npcink-workflow-toolbox' ),
				count( $missing_alt ),
				count( $missing_captions ),
				$post_missing_alt
			),
			__( 'Image metadata affects accessibility, editorial reuse, and media search quality.', 'npcink-workflow-toolbox' ),
			__( 'Start with a media ALT/caption review set; do not update media metadata until a governed path is selected.', 'npcink-workflow-toolbox' ),
			'core_handoff_candidate',
			array_slice( $this->object_refs( $missing_alt ), 0, 5 )
		);
	}

	/**
	 * @param array<string,mixed>            $taxonomies Taxonomy summary.
	 * @param array<int,array<string,mixed>> $findings Findings.
	 */
	private function add_taxonomy_finding( array $taxonomies, array &$findings ): void {
		$category = is_array( $taxonomies['category'] ?? null ) ? $taxonomies['category'] : array();
		$tag      = is_array( $taxonomies['post_tag'] ?? null ) ? $taxonomies['post_tag'] : array();
		$empty    = (int) ( $category['empty_count'] ?? 0 ) + (int) ( $tag['empty_count'] ?? 0 );
		$low      = (int) ( $category['low_count'] ?? 0 ) + (int) ( $tag['low_count'] ?? 0 );
		if ( $empty <= 0 && $low <= 0 ) {
			return;
		}

		$findings[] = $this->finding(
			'taxonomy_structure_drift',
			__( 'Taxonomy structure may need cleanup', 'npcink-workflow-toolbox' ),
			'taxonomy',
			min( 82, 38 + $empty * 5 + $low * 3 ),
			$empty + $low >= 8 ? 'high' : 'medium',
			sprintf(
				/* translators: 1: empty term count, 2: low-use term count. */
				__( '%1$d category/tag terms are empty and %2$d are used once in the sampled taxonomy summary.', 'npcink-workflow-toolbox' ),
				$empty,
				$low
			),
			__( 'Sparse vocabulary can fragment content discovery and weaken recommendation quality.', 'npcink-workflow-toolbox' ),
			__( 'Review taxonomy consolidation separately; do not create, merge, or assign terms from this panel.', 'npcink-workflow-toolbox' ),
			'manual_review_only',
			array()
		);
	}

	/**
	 * @param array<string,mixed>            $context Context.
	 * @param array<int,array<string,mixed>> $findings Findings.
	 */
	private function add_context_finding( array $context, array &$findings ): void {
		if ( ! empty( $context['content_context_ready'] ) ) {
			return;
		}

		$findings[] = $this->finding(
			'site_context_incomplete',
			__( 'Site Context needs a stronger brief', 'npcink-workflow-toolbox' ),
			'site_context',
			72,
			'medium',
			__( 'The compact site positioning, audience, voice, or keyword context is incomplete.', 'npcink-workflow-toolbox' ),
			__( 'Weak site context makes downstream SEO/AEO/GEO and content support suggestions less consistent.', 'npcink-workflow-toolbox' ),
			__( 'Fill the Site Context brief before relying on repeated AI recommendations.', 'npcink-workflow-toolbox' ),
			'manual_review_only',
			array()
		);
	}

	/**
	 * @param array<string,mixed>            $context Context.
	 * @param array<int,array<string,mixed>> $findings Findings.
	 */
	private function add_site_knowledge_finding( array $context, array &$findings ): void {
		if ( ! empty( $context['cloud_ready'] ) ) {
			return;
		}

		$findings[] = $this->finding(
			'site_knowledge_cloud_unavailable',
			__( 'Cloud Site Knowledge is not available', 'npcink-workflow-toolbox' ),
			'site_knowledge',
			68,
			'medium',
			__( 'The local insight pack can run, but semantic Site Knowledge and Cloud analysis are unavailable.', 'npcink-workflow-toolbox' ),
			__( 'Without Cloud, recommendations stay local and cannot use semantic related-content evidence.', 'npcink-workflow-toolbox' ),
			__( 'Connect or verify Cloud Addon before expecting deeper semantic analysis.', 'npcink-workflow-toolbox' ),
			'blocked_until_cloud_ready',
			array()
		);
	}

	/**
	 * @param array<int,mixed>               $posts Posts.
	 * @param array<int,mixed>               $media Media.
	 * @param array<string,mixed>            $comments Comments.
	 * @param array<string,mixed>            $taxonomies Taxonomies.
	 * @param array<int,array<string,mixed>> $findings Findings.
	 * @return array<string,mixed>
	 */
	private function summary( array $posts, array $media, array $comments, array $taxonomies, array $findings ): array {
		$high = 0;
		foreach ( $findings as $finding ) {
			if ( 'high' === (string) ( $finding['severity'] ?? '' ) ) {
				++$high;
			}
		}

		return array(
			'scanned_posts'          => count( $posts ),
			'scanned_media'          => count( $media ),
			'approved_comments'      => (int) ( $comments['approved_total'] ?? 0 ),
			'recent_comment_sample'  => (int) ( $comments['recent_sample_count'] ?? 0 ),
			'category_terms'         => (int) ( $taxonomies['category']['total'] ?? 0 ),
			'tag_terms'              => (int) ( $taxonomies['post_tag']['total'] ?? 0 ),
			'top_finding_count'      => count( $findings ),
			'high_priority_findings' => $high,
		);
	}

	/**
	 * @param array<int,mixed>    $posts Posts.
	 * @param array<int,mixed>    $media Media.
	 * @param array<string,mixed> $comments Comments.
	 * @param array<string,mixed> $taxonomies Taxonomies.
	 * @param array<string,mixed> $context Context.
	 * @return array<string,mixed>
	 */
	private function data_sources( array $posts, array $media, array $comments, array $taxonomies, array $context ): array {
		return array(
			'posts'          => array( 'available' => count( $posts ) > 0, 'count' => count( $posts ), 'source' => 'local_public_wordpress' ),
			'comments'       => array( 'available' => (int) ( $comments['recent_sample_count'] ?? 0 ) > 0, 'count' => (int) ( $comments['recent_sample_count'] ?? 0 ), 'privacy' => is_array( $comments['privacy'] ?? null ) ? $comments['privacy'] : array() ),
			'media'          => array( 'available' => count( $media ) > 0, 'count' => count( $media ), 'source' => 'local_media_metadata' ),
			'taxonomies'     => array( 'available' => ! empty( $taxonomies ), 'source' => 'local_taxonomy_summary' ),
			'site_context'   => array( 'available' => ! empty( $context['content_context_ready'] ), 'source' => 'npcink_toolbox_content_context' ),
			'site_knowledge' => array( 'available' => ! empty( $context['cloud_ready'] ), 'source' => 'cloud_managed_site_knowledge' ),
			'cloud_runtime'  => array( 'available' => ! empty( $context['cloud_ready'] ), 'used_in_p0' => false ),
		);
	}

	/**
	 * @param array<int,array<string,mixed>> $findings Findings.
	 * @return array<int,array<string,mixed>>
	 */
	private function opportunities( array $findings ): array {
		$items = array();
		foreach ( $findings as $finding ) {
			$items[] = array(
				'finding_id'      => (string) ( $finding['id'] ?? '' ),
				'opportunity'     => (string) ( $finding['recommended_action'] ?? '' ),
				'write_boundary'  => (string) ( $finding['write_boundary'] ?? 'suggestion_only' ),
				'operator_action' => 'review_finding',
			);
		}

		return array_slice( $items, 0, 5 );
	}

	/**
	 * @param array<string,mixed> $context Context.
	 * @return array<int,array<string,mixed>>
	 */
	private function blocked_items( array $context ): array {
		$items = array();
		if ( empty( $context['cloud_ready'] ) ) {
			$items[] = array(
				'id'     => 'cloud_semantic_analysis',
				'reason' => 'cloud_runtime_unavailable',
				'next'   => 'connect_or_verify_cloud_addon',
			);
		}

		return $items;
	}

	/**
	 * @param array<int,mixed> $items Items.
	 * @return array<int,array<string,mixed>>
	 */
	private function object_refs( array $items ): array {
		$refs = array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$refs[] = array(
				'object_type' => sanitize_key( (string) ( $item['object_type'] ?? 'post' ) ),
				'object_id'   => (int) ( $item['object_id'] ?? 0 ),
				'title'       => sanitize_text_field( (string) ( $item['title'] ?? '' ) ),
			);
		}

		return $refs;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function finding( string $id, string $title, string $issue_type, int $priority_score, string $severity, string $evidence_summary, string $impact, string $recommended_action, string $write_boundary, array $source_refs ): array {
		return array(
			'id'                     => $id,
			'title'                  => $title,
			'issue_type'             => $issue_type,
			'category'               => $issue_type,
			'severity'               => $severity,
			'priority_score'         => max( 0, min( 100, $priority_score ) ),
			'evidence_summary'       => $evidence_summary,
			'impact'                 => $impact,
			'recommended_action'     => $recommended_action,
			'write_boundary'         => $write_boundary,
			'core_handoff_candidate' => 'core_handoff_candidate' === $write_boundary,
			'direct_wordpress_write' => false,
			'source_refs'            => $source_refs,
		);
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
}
