<?php
/**
 * Merges Cloud Batch Runtime detail back into a local Morning Brief.
 *
 * @package Npcink_Local_Automation_Runtime
 */

namespace Npcink\LocalAutomationRuntime\NightlyInspection;

final class Cloud_Batch_Result_Merger {
	public const CONTRACT_VERSION = 'nightly_site_inspection_cloud_batch_merge.v1';

	/**
	 * @param array<string,mixed> $morning_brief Local Morning Brief.
	 * @param array<string,mixed> $cloud_result Cloud Batch Runtime result.
	 * @return array<string,mixed>
	 */
	public function merge( array $morning_brief, array $cloud_result ): array {
		$actions_by_key = $this->actions_by_key( $cloud_result );
		$merged         = $morning_brief;
		$merged_count   = 0;

		if ( isset( $merged['priorities'] ) && is_array( $merged['priorities'] ) ) {
			foreach ( $merged['priorities'] as $index => $priority ) {
				if ( ! is_array( $priority ) ) {
					continue;
				}
				$key = $this->object_key( $priority );
				if ( '' === $key || ! isset( $actions_by_key[ $key ] ) ) {
					continue;
				}

				$merged['priorities'][ $index ]['cloud_runtime'] = $this->cloud_action_summary( $actions_by_key[ $key ] );
				++$merged_count;
			}
		}

		$merged['cloud_runtime'] = array(
			'contract_version'       => self::CONTRACT_VERSION,
			'provider'               => 'magick_ai_cloud',
			'composition_role'       => 'morning_brief_cloud_runtime_detail',
			'source_run_id'          => $this->text_value( $cloud_result, array( 'run_id', 'cloud_run_id', 'source_run_id' ) ),
			'status'                 => $this->text_value( $cloud_result, array( 'status' ), 'merged' ),
			'action_count'           => count( $actions_by_key ),
			'merged_priority_count'  => $merged_count,
			'direct_wordpress_write' => false,
			'final_write_path'       => 'core_proposal_required',
			'requires_local_review'  => true,
		);

		$merged['writing_preparation'] = $this->merge_writing_preparation(
			isset( $merged['writing_preparation'] ) && is_array( $merged['writing_preparation'] ) ? $merged['writing_preparation'] : array(),
			$actions_by_key
		);

		if ( isset( $merged['safety'] ) && is_array( $merged['safety'] ) ) {
			$merged['safety']['cloud_called']             = true;
			$merged['safety']['direct_wordpress_write']   = false;
			$merged['safety']['cloud_scheduler_truth']    = false;
			$merged['safety']['requires_local_review']    = true;
			$merged['safety']['action_scheduler_used']    = false;
			$merged['safety']['custom_tables_created']    = false;
			$merged['safety']['core_proposal_created']    = false;
		}

		return $merged;
	}

	/**
	 * @param array<string,mixed> $cloud_result Cloud result.
	 * @return array<string,mixed>
	 */
	public function patch( array $cloud_result ): array {
		$actions = array_values( $this->actions_by_key( $cloud_result ) );

		return array(
			'contract_version'       => self::CONTRACT_VERSION,
			'provider'               => 'magick_ai_cloud',
			'composition_role'       => 'morning_brief_cloud_runtime_patch',
			'source_run_id'          => $this->text_value( $cloud_result, array( 'run_id', 'cloud_run_id', 'source_run_id' ) ),
			'status'                 => $this->text_value( $cloud_result, array( 'status' ), 'available' ),
			'actions'                => array_map( array( $this, 'cloud_action_summary' ), $actions ),
			'action_count'           => count( $actions ),
			'direct_wordpress_write' => false,
			'final_write_path'       => 'core_proposal_required',
			'requires_local_review'  => true,
		);
	}

	/**
	 * @param array<string,mixed> $cloud_result Cloud result.
	 * @return array<string,array<string,mixed>>
	 */
	private function actions_by_key( array $cloud_result ): array {
		$actions = $this->extract_actions( $cloud_result );
		$indexed = array();

		foreach ( $actions as $action ) {
			if ( ! is_array( $action ) ) {
				continue;
			}
			$key = $this->object_key( $action );
			if ( '' === $key ) {
				continue;
			}
			$indexed[ $key ] = $action;
		}

		return $indexed;
	}

	/**
	 * @param array<string,mixed> $cloud_result Cloud result.
	 * @return array<int,mixed>
	 */
	private function extract_actions( array $cloud_result ): array {
		foreach ( array( 'actions', 'review_items', 'items', 'priorities' ) as $key ) {
			if ( isset( $cloud_result[ $key ] ) && is_array( $cloud_result[ $key ] ) ) {
				return $cloud_result[ $key ];
			}
		}

		if ( isset( $cloud_result['result'] ) && is_array( $cloud_result['result'] ) ) {
			return $this->extract_actions( $cloud_result['result'] );
		}

		return array();
	}

	/**
	 * @param array<string,mixed> $item Item.
	 */
	private function object_key( array $item ): string {
		$type = $this->sanitize_key( (string) ( $item['object_type'] ?? $item['type'] ?? '' ) );
		$id   = max( 0, (int) ( $item['object_id'] ?? $item['post_id'] ?? $item['attachment_id'] ?? 0 ) );

		if ( '' === $type || 0 === $id ) {
			return '';
		}

		return $type . ':' . $id;
	}

	/**
	 * @param array<string,mixed> $action Cloud action.
	 * @return array<string,mixed>
	 */
	private function cloud_action_summary( array $action ): array {
		return array(
			'object_type'           => $this->sanitize_key( (string) ( $action['object_type'] ?? $action['type'] ?? '' ) ),
			'object_id'             => max( 0, (int) ( $action['object_id'] ?? $action['post_id'] ?? $action['attachment_id'] ?? 0 ) ),
			'quality_score'         => max( 0, min( 100, (int) ( $action['quality_score'] ?? $action['score'] ?? 0 ) ) ),
			'severity'              => $this->sanitize_key( (string) ( $action['severity'] ?? 'notice' ) ),
			'recommendation'        => $this->bounded_text( (string) ( $action['recommendation'] ?? $action['recommended_next_action'] ?? $action['summary'] ?? '' ), 500 ),
			'reason_codes'          => $this->string_list( $action['reason_codes'] ?? $action['codes'] ?? array(), 12 ),
			'evidence_refs'         => $this->sanitize_evidence_refs( $action['evidence_refs'] ?? array() ),
			'direct_wordpress_write' => false,
			'final_write_path'      => 'core_proposal_required',
			'requires_local_review' => true,
		);
	}

	/**
	 * @param array<int,mixed>                $local_items Local preparation.
	 * @param array<string,array<string,mixed>> $actions_by_key Cloud actions.
	 * @return array<int,mixed>
	 */
	private function merge_writing_preparation( array $local_items, array $actions_by_key ): array {
		$items = $local_items;

		foreach ( $actions_by_key as $action ) {
			$summary = $this->cloud_action_summary( $action );
			if ( '' === $summary['recommendation'] && array() === $summary['reason_codes'] ) {
				continue;
			}

			$items[] = array(
				'source'                  => 'cloud_batch_runtime',
				'source_object_ids'       => array( (int) $summary['object_id'] ),
				'evidence_summary'        => $summary['recommendation'],
				'reason_codes'            => $summary['reason_codes'],
				'forbidden_output_absent' => true,
				'direct_wordpress_write'  => false,
				'final_write_path'        => 'core_proposal_required',
			);
		}

		return array_slice( $items, 0, 30 );
	}

	/**
	 * @param mixed $refs Evidence refs.
	 * @return array<int,array<string,string>>
	 */
	private function sanitize_evidence_refs( $refs ): array {
		$items  = is_array( $refs ) ? $refs : array();
		$result = array();

		foreach ( $items as $ref ) {
			if ( ! is_array( $ref ) ) {
				continue;
			}
			$id = $this->bounded_text( (string) ( $ref['id'] ?? $ref['ref_id'] ?? '' ), 120 );
			if ( '' === $id ) {
				continue;
			}
			$result[] = array(
				'id'      => $id,
				'label'   => $this->bounded_text( (string) ( $ref['label'] ?? $ref['title'] ?? '' ), 200 ),
				'source'  => $this->sanitize_key( (string) ( $ref['source'] ?? $ref['source_type'] ?? 'cloud_runtime' ) ),
			);
			if ( count( $result ) >= 12 ) {
				break;
			}
		}

		return $result;
	}

	/**
	 * @param mixed $items Items.
	 * @return array<int,string>
	 */
	private function string_list( $items, int $limit ): array {
		$values = is_array( $items ) ? $items : array();
		$list   = array();
		foreach ( $values as $item ) {
			$value = $this->sanitize_key( (string) $item );
			if ( '' !== $value && ! in_array( $value, $list, true ) ) {
				$list[] = $value;
			}
			if ( count( $list ) >= $limit ) {
				break;
			}
		}

		return $list;
	}

	/**
	 * @param array<string,mixed> $source Source.
	 * @param array<int,string>   $keys Candidate keys.
	 */
	private function text_value( array $source, array $keys, string $default = '' ): string {
		foreach ( $keys as $key ) {
			if ( isset( $source[ $key ] ) && '' !== trim( (string) $source[ $key ] ) ) {
				return $this->bounded_text( (string) $source[ $key ], 191 );
			}
		}

		return $default;
	}

	private function sanitize_key( string $value ): string {
		return preg_replace( '/[^a-z0-9_\\-]/', '', strtolower( $value ) ) ?? '';
	}

	private function bounded_text( string $value, int $max_chars ): string {
		$value = trim( strip_tags( $value ) );
		if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) && mb_strlen( $value ) > $max_chars ) {
			return mb_substr( $value, 0, $max_chars );
		}

		return strlen( $value ) > $max_chars ? substr( $value, 0, $max_chars ) : $value;
	}
}
