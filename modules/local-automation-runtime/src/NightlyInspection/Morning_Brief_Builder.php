<?php
/**
 * Builds a read-only Morning Brief payload from deterministic scores.
 *
 * @package Npcink_Local_Automation_Runtime
 */

namespace Npcink\LocalAutomationRuntime\NightlyInspection;

final class Morning_Brief_Builder {
	public const CONTRACT_VERSION = 'nightly_site_inspection_result.v1';

	private Rule_Scorer $scorer;

	public function __construct( ?Rule_Scorer $scorer = null ) {
		$this->scorer = $scorer ?: new Rule_Scorer();
	}

	/**
	 * Builds a review-only payload from a caller-provided site snapshot.
	 *
	 * @param array<string,mixed> $snapshot Site snapshot.
	 * @return array<string,mixed>
	 */
	public function build( array $snapshot ): array {
		$generated_at = $this->string_value( $snapshot, 'generated_at', '2026-06-15T00:00:00Z' );
		$site_id      = $this->string_value( $snapshot, 'site_id', 'local-site' );
		$posts        = $this->array_value( $snapshot, 'posts' );
		$media        = $this->array_value( $snapshot, 'media' );
		$priorities   = array();

		foreach ( $posts as $post ) {
			if ( ! is_array( $post ) ) {
				continue;
			}
			$score = $this->scorer->score_content_item( $post, $generated_at );
			if ( $score['score'] < 90 || ! empty( $score['findings'] ) ) {
				$priorities[] = $score;
			}
		}

		foreach ( $media as $media_item ) {
			if ( ! is_array( $media_item ) ) {
				continue;
			}
			$media_priority = $this->media_priority( $media_item );
			if ( array() !== $media_priority ) {
				$priorities[] = $media_priority;
			}
		}

		usort(
			$priorities,
			static function ( array $left, array $right ): int {
				$left_score  = (int) ( $left['score'] ?? 100 );
				$right_score = (int) ( $right['score'] ?? 100 );
				if ( $left_score === $right_score ) {
					return (int) ( $left['object_id'] ?? 0 ) <=> (int) ( $right['object_id'] ?? 0 );
				}

				return $left_score <=> $right_score;
			}
		);

		return array(
			'contract_version'    => self::CONTRACT_VERSION,
			'run_id'              => $this->string_value( $snapshot, 'run_id', 'local-preview-run' ),
			'site_id'             => $site_id,
			'generated_at'        => $generated_at,
			'summary'             => $this->summary( $posts, $media, $priorities ),
			'priorities'          => array_slice( $priorities, 0, 20 ),
			'writing_preparation' => $this->writing_preparation( $priorities ),
			'safety'              => array(
				'direct_wordpress_write' => false,
				'requires_local_review'  => true,
				'cloud_scheduler_truth'  => false,
				'cloud_called'           => false,
				'cron_registered'        => false,
				'action_scheduler_used'  => false,
				'custom_tables_created'  => false,
			),
		);
	}

	/**
	 * @param array<string,mixed> $media_item Media snapshot.
	 * @return array<string,mixed>
	 */
	private function media_priority( array $media_item ): array {
		$object_id = max( 0, (int) ( $media_item['object_id'] ?? $media_item['attachment_id'] ?? 0 ) );
		$alt       = trim( $this->string_value( $media_item, 'alt' ) );
		$findings  = array();

		if ( '' === $alt ) {
			$findings[] = array(
				'code'                    => 'missing_image_alt',
				'severity'                => 'warning',
				'deduction'               => 15,
				'detail'                  => 'Attachment alt text is absent.',
				'recommended_next_action' => 'review_media_alt',
			);
		}
		if ( '' === trim( $this->string_value( $media_item, 'filename' ) ) ) {
			$findings[] = array(
				'code'                    => 'missing_media_filename',
				'severity'                => 'notice',
				'deduction'               => 5,
				'detail'                  => 'Attachment filename was not present in the snapshot.',
				'recommended_next_action' => 'review_media',
			);
		}
		if ( array() === $findings ) {
			return array();
		}

		$score = max( 0, 100 - array_sum( array_map( static fn( array $finding ): int => (int) $finding['deduction'], $findings ) ) );

		return array(
			'object_type'             => 'attachment',
			'object_id'               => $object_id,
			'title'                   => $this->string_value( $media_item, 'title', $this->string_value( $media_item, 'filename' ) ),
			'score'                   => $score,
			'severity'                => $score < 85 ? 'warning' : 'ok',
			'reason_codes'            => array_map(
				static fn( array $finding ): string => (string) $finding['code'],
				$findings
			),
			'findings'                => $findings,
			'recommended_next_action' => 'review_media_alt',
		);
	}

	/**
	 * @param array<int,mixed>               $posts Posts.
	 * @param array<int,mixed>               $media Media.
	 * @param array<int,array<string,mixed>> $priorities Priorities.
	 * @return array<string,mixed>
	 */
	private function summary( array $posts, array $media, array $priorities ): array {
		$risk_total = 0;
		foreach ( $priorities as $priority ) {
			if ( 'error' === ( $priority['severity'] ?? '' ) ) {
				++$risk_total;
			}
		}

		return array(
			'scanned_posts' => count( $posts ),
			'scanned_media' => count( $media ),
			'actions_total' => count( $priorities ),
			'risk_total'    => $risk_total,
		);
	}

	/**
	 * @param array<int,array<string,mixed>> $priorities Priorities.
	 * @return array<int,array<string,mixed>>
	 */
	private function writing_preparation( array $priorities ): array {
		$items = array();
		foreach ( $priorities as $priority ) {
			$codes = $priority['reason_codes'] ?? array();
			if ( ! is_array( $codes ) ) {
				continue;
			}
			if ( ! array_intersect( $codes, array( 'stale_content', 'stale_year_marker', 'thin_content', 'missing_heading_structure' ) ) ) {
				continue;
			}
			$items[] = array(
				'source_object_ids'       => array( (int) ( $priority['object_id'] ?? 0 ) ),
				'opportunity_kind'        => in_array( 'stale_content', $codes, true ) || in_array( 'stale_year_marker', $codes, true ) ? 'refresh_existing_content' : 'improve_existing_content',
				'evidence_summary'        => 'Source evidence only. No article title, body, FAQ copy, or SEO copy is generated.',
				'forbidden_output_absent' => true,
			);
		}

		return array_slice( $items, 0, 10 );
	}

	/**
	 * @param array<string,mixed> $item Source.
	 */
	private function string_value( array $item, string $key, string $default = '' ): string {
		$value = $item[ $key ] ?? $default;
		if ( is_scalar( $value ) ) {
			return (string) $value;
		}

		return $default;
	}

	/**
	 * @param array<string,mixed> $item Source.
	 * @return array<int,mixed>
	 */
	private function array_value( array $item, string $key ): array {
		$value = $item[ $key ] ?? array();
		if ( ! is_array( $value ) ) {
			return array();
		}

		return array_values( $value );
	}
}
