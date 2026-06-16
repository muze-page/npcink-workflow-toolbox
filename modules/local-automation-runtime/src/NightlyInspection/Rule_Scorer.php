<?php
/**
 * Deterministic scoring rules for Nightly Site Inspection.
 *
 * @package Npcink_Local_Automation_Runtime
 */

namespace Npcink\LocalAutomationRuntime\NightlyInspection;

final class Rule_Scorer {
	/**
	 * Scores one content item without reading WordPress state.
	 *
	 * @param array<string,mixed> $item Content snapshot.
	 * @param string              $generated_at ISO timestamp used for freshness checks.
	 * @return array<string,mixed>
	 */
	public function score_content_item( array $item, string $generated_at = '' ): array {
		$object_type = $this->string_value( $item, 'object_type', 'post' );
		$object_id   = $this->int_value( $item, 'object_id' );
		$title       = $this->string_value( $item, 'title' );
		$content     = $this->string_value( $item, 'content' );
		$findings    = array();

		$this->maybe_add_title_findings( $title, $findings );
		$this->maybe_add_meta_findings( $item, $findings );
		$this->maybe_add_structure_findings( $content, $findings );
		$this->maybe_add_freshness_findings( $item, $title, $generated_at, $findings );
		$this->maybe_add_link_findings( $item, $findings );
		$this->maybe_add_taxonomy_findings( $item, $findings );
		$this->maybe_add_media_findings( $item, $findings );

		$score = 100;
		foreach ( $findings as $finding ) {
			$score -= (int) ( $finding['deduction'] ?? 0 );
		}
		$score = max( 0, min( 100, $score ) );

		return array(
			'object_type'             => $object_type,
			'object_id'               => $object_id,
			'title'                   => $title,
			'score'                   => $score,
			'severity'                => $this->severity_for_score( $score ),
			'reason_codes'            => array_values(
				array_map(
					static function ( array $finding ): string {
						return (string) ( $finding['code'] ?? '' );
					},
					$findings
				)
			),
			'findings'                => $findings,
			'recommended_next_action' => $this->recommended_next_action( $findings, $score ),
		);
	}

	/**
	 * @param array<int,array<string,mixed>> $findings Findings.
	 */
	private function maybe_add_title_findings( string $title, array &$findings ): void {
		$length = strlen( trim( $title ) );
		if ( 0 === $length ) {
			$findings[] = $this->finding( 'missing_title', 'error', 20, 'Add a clear title before review.', 'review_title' );
			return;
		}
		if ( $length < 18 ) {
			$findings[] = $this->finding( 'short_title', 'warning', 5, 'Title is short enough to need editorial review.', 'review_title' );
		}
		if ( $length > 90 ) {
			$findings[] = $this->finding( 'long_title', 'warning', 5, 'Title is long enough to need editorial review.', 'review_title' );
		}
	}

	/**
	 * @param array<string,mixed>            $item Content snapshot.
	 * @param array<int,array<string,mixed>> $findings Findings.
	 */
	private function maybe_add_meta_findings( array $item, array &$findings ): void {
		$description = trim( $this->string_value( $item, 'meta_description' ) );
		if ( '' === $description ) {
			$findings[] = $this->finding( 'missing_meta_description', 'warning', 12, 'Meta description is absent.', 'prepare_seo_review' );
			return;
		}
		$length = strlen( $description );
		if ( $length < 70 ) {
			$findings[] = $this->finding( 'short_meta_description', 'warning', 6, 'Meta description is short and may need review.', 'prepare_seo_review' );
		}
		if ( $length > 180 ) {
			$findings[] = $this->finding( 'long_meta_description', 'warning', 6, 'Meta description is long and may need review.', 'prepare_seo_review' );
		}
	}

	/**
	 * @param array<int,array<string,mixed>> $findings Findings.
	 */
	private function maybe_add_structure_findings( string $content, array &$findings ): void {
		$plain_text  = trim( strip_tags( $content ) );
		$word_count  = $this->token_count( $plain_text );
		$h2_count    = preg_match_all( '/<h2\b/i', $content );
		$h3_count    = preg_match_all( '/<h3\b/i', $content );
		$headings    = (int) $h2_count + (int) $h3_count;
		$paragraphs  = preg_match_all( '/<p\b/i', $content );
		$plain_lines = substr_count( $plain_text, "\n" ) + 1;

		if ( $word_count < 250 ) {
			$findings[] = $this->finding( 'thin_content', 'warning', 15, 'Content is short enough to need quality review.', 'review_content_depth' );
		}
		if ( $headings <= 0 ) {
			$findings[] = $this->finding( 'missing_heading_structure', 'warning', 8, 'No H2/H3 structure was found.', 'review_structure' );
		}
		if ( $paragraphs <= 1 && $plain_lines <= 2 ) {
			$findings[] = $this->finding( 'weak_paragraph_structure', 'warning', 5, 'Paragraph structure appears sparse.', 'review_structure' );
		}
	}

	/**
	 * @param array<string,mixed>            $item Content snapshot.
	 * @param array<int,array<string,mixed>> $findings Findings.
	 */
	private function maybe_add_freshness_findings( array $item, string $title, string $generated_at, array &$findings ): void {
		$current_year = $this->year_from_timestamp( $generated_at );
		if ( preg_match( '/\b(20\d{2})\b/', $title, $matches ) ) {
			$title_year = (int) $matches[1];
			if ( $current_year > 0 && $title_year < $current_year ) {
				$findings[] = $this->finding( 'stale_year_marker', 'warning', 8, 'Title contains an older year marker.', 'review_update_brief' );
			}
		}

		$modified_year = $this->year_from_timestamp( $this->string_value( $item, 'modified_at' ) );
		if ( $current_year > 0 && $modified_year > 0 && $modified_year <= $current_year - 2 ) {
			$findings[] = $this->finding( 'stale_content', 'warning', 10, 'Content has not been updated recently.', 'review_update_brief' );
		}
	}

	/**
	 * @param array<string,mixed>            $item Content snapshot.
	 * @param array<int,array<string,mixed>> $findings Findings.
	 */
	private function maybe_add_link_findings( array $item, array &$findings ): void {
		if ( $this->int_value( $item, 'internal_link_count' ) <= 0 ) {
			$findings[] = $this->finding( 'missing_internal_links', 'warning', 8, 'No internal links were recorded.', 'review_internal_links' );
		}
	}

	/**
	 * @param array<string,mixed>            $item Content snapshot.
	 * @param array<int,array<string,mixed>> $findings Findings.
	 */
	private function maybe_add_taxonomy_findings( array $item, array &$findings ): void {
		if ( 0 === count( $this->array_value( $item, 'categories' ) ) ) {
			$findings[] = $this->finding( 'missing_category', 'warning', 6, 'No category was recorded.', 'review_taxonomy' );
		}
		if ( 0 === count( $this->array_value( $item, 'tags' ) ) ) {
			$findings[] = $this->finding( 'missing_tags', 'notice', 4, 'No tags were recorded.', 'review_taxonomy' );
		}
	}

	/**
	 * @param array<string,mixed>            $item Content snapshot.
	 * @param array<int,array<string,mixed>> $findings Findings.
	 */
	private function maybe_add_media_findings( array $item, array &$findings ): void {
		if ( false === (bool) ( $item['featured_image_present'] ?? false ) ) {
			$findings[] = $this->finding( 'missing_featured_image', 'warning', 6, 'Featured image is absent.', 'review_media' );
		}
		$missing_alt = $this->int_value( $item, 'missing_alt_count' );
		if ( $missing_alt > 0 ) {
			$findings[] = $this->finding( 'missing_image_alt', 'warning', min( 10, 4 + $missing_alt ), 'One or more referenced images are missing alt text.', 'review_media_alt' );
		}
	}

	/**
	 * @param array<int,array<string,mixed>> $findings Findings.
	 */
	private function recommended_next_action( array $findings, int $score ): string {
		if ( $score < 60 ) {
			return 'review_quality_blockers';
		}
		foreach ( $findings as $finding ) {
			$action = (string) ( $finding['recommended_next_action'] ?? '' );
			if ( '' !== $action ) {
				return $action;
			}
		}

		return 'monitor';
	}

	private function severity_for_score( int $score ): string {
		if ( $score < 60 ) {
			return 'error';
		}
		if ( $score < 85 ) {
			return 'warning';
		}

		return 'ok';
	}

	/**
	 * @return array<string,mixed>
	 */
	private function finding( string $code, string $severity, int $deduction, string $detail, string $action ): array {
		return array(
			'code'                    => $code,
			'severity'                => $severity,
			'deduction'               => $deduction,
			'detail'                  => $detail,
			'recommended_next_action' => $action,
		);
	}

	private function token_count( string $text ): int {
		if ( '' === $text ) {
			return 0;
		}
		if ( preg_match_all( '/[\p{L}\p{N}]+/u', $text, $matches ) ) {
			return count( $matches[0] );
		}

		return str_word_count( $text );
	}

	private function year_from_timestamp( string $value ): int {
		if ( preg_match( '/^(20\d{2})/', $value, $matches ) ) {
			return (int) $matches[1];
		}

		return 0;
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
	 */
	private function int_value( array $item, string $key ): int {
		return max( 0, (int) ( $item[ $key ] ?? 0 ) );
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
