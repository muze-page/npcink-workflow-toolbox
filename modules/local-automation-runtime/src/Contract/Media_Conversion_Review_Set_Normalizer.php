<?php
/**
 * Normalizes media derivative batch plans into local automation review sets.
 *
 * @package Npcink_Local_Automation_Runtime
 */

namespace Npcink\LocalAutomationRuntime\Contract;

final class Media_Conversion_Review_Set_Normalizer {
	private const CONTRACT_VERSION = 'npcink_local_automation_media_conversion_review_set.v1';
	private const RUNTIME_OWNER    = 'npcink-local-automation-runtime';
	private const TARGET_ABILITY   = 'npcink-abilities-toolkit/build-media-derivative-cloud-request';

	/**
	 * Builds a media conversion review set from an existing media derivative batch plan.
	 *
	 * @param array<string,mixed> $plan Existing plan data or success envelope.
	 * @return array<string,mixed>
	 */
	public function from_media_derivative_batch_plan( array $plan ): array {
		$data = is_array( $plan['data'] ?? null ) ? (array) $plan['data'] : $plan;

		$filters       = is_array( $data['filters'] ?? null ) ? (array) $data['filters'] : array();
		$summary       = is_array( $data['eligibility_summary'] ?? null ) ? (array) $data['eligibility_summary'] : array();
		$candidates    = is_array( $data['candidates'] ?? null ) ? array_values( (array) $data['candidates'] ) : array();
		$blocked_items = is_array( $data['blocked_items'] ?? null ) ? array_values( (array) $data['blocked_items'] ) : array();

		$target_format = $this->first_non_empty_string(
			array(
				$filters['target_format'] ?? '',
				$candidates[0]['target_format'] ?? '',
				$candidates[0]['cloud_request_input']['preferred_format'] ?? '',
			),
			'webp'
		);

		$selected_items = array();
		foreach ( $candidates as $candidate ) {
			if ( is_array( $candidate ) ) {
				$selected_items[] = $this->normalize_candidate( $candidate, $target_format );
			}
		}

		$normalized_blocked_items = array();
		foreach ( $blocked_items as $blocked_item ) {
			if ( is_array( $blocked_item ) ) {
				$normalized_blocked_items[] = $this->normalize_blocked_item( $blocked_item );
			}
		}

		$retryable = is_bool( $data['retryable'] ?? null ) ? (bool) $data['retryable'] : false;

		return array(
			'contract_version'    => self::CONTRACT_VERSION,
			'runtime_owner'       => self::RUNTIME_OWNER,
			'operation_family'    => 'media_conversion',
			'mode'                => 'governed_review_set',
			'trigger'             => 'operator_manual_review',
			'scope'               => array(
				'object_type'             => 'attachment',
				'source'                  => 'media_library',
				'target_format'           => $target_format,
				'max_items'               => (int) ( $filters['max_items'] ?? count( $candidates ) ),
				'selected_attachment_ids' => $this->attachment_ids_from_items( array_merge( $selected_items, $normalized_blocked_items ) ),
			),
			'eligibility_summary' => array(
				'items_total'        => (int) ( $summary['total_count'] ?? $summary['items_total'] ?? count( $selected_items ) + count( $normalized_blocked_items ) ),
				'eligible_count'     => (int) ( $summary['eligible_count'] ?? count( $selected_items ) ),
				'selected_count'     => count( $selected_items ),
				'blocked_count'      => count( $normalized_blocked_items ),
				'needs_input_count'  => (int) ( $summary['needs_input_count'] ?? 0 ),
				'risk_level'         => 'medium',
				'target_ability_ids' => array( self::TARGET_ABILITY ),
			),
			'selected_items'      => $selected_items,
			'blocked_items'       => $normalized_blocked_items,
			'operator_next_action' => $this->first_non_empty_string(
				array(
					$data['operator_next_action'] ?? '',
					! empty( $selected_items ) ? 'generate_selected_previews' : 'adjust_selection_or_filters',
				),
				'adjust_selection_or_filters'
			),
			'retryable'           => $retryable,
			'retry_guidance'      => array(
				'retryable'            => $retryable,
				'reason'               => $retryable ? 'review_set_can_be_rebuilt' : 'review_set_not_execution_state',
				'operator_next_action' => $this->first_non_empty_string(
					array(
						$data['retry_guidance'] ?? '',
						$retryable ? 'adjust_filters_or_selection_then_rebuild' : 'adjust_selection_or_generate_selected_previews',
					),
					'adjust_selection_or_generate_selected_previews'
				),
			),
			'safety'              => $this->safety_flags(),
		);
	}

	/**
	 * @param array<string,mixed> $candidate Source candidate.
	 * @return array<string,mixed>
	 */
	private function normalize_candidate( array $candidate, string $target_format ): array {
		$attachment_id = (int) ( $candidate['attachment_id'] ?? $candidate['id'] ?? 0 );

		return array(
			'attachment_id'           => $attachment_id,
			'source_mime_type'        => $this->first_non_empty_string( array( $candidate['mime_type'] ?? '', $candidate['source_mime_type'] ?? '' ), 'image/unknown' ),
			'target_format'           => $this->first_non_empty_string( array( $candidate['target_format'] ?? '', $candidate['cloud_request_input']['preferred_format'] ?? '' ), $target_format ),
			'preview_required'        => true,
			'target_ability_id'       => self::TARGET_ABILITY,
			'proposal_path'           => 'core_proposal_required',
			'result_ref'              => $this->first_non_empty_string( array( $candidate['result_ref'] ?? '', 'attachment:' . (string) $attachment_id ), 'attachment:' . (string) $attachment_id ),
			'direct_wordpress_write'  => false,
			'cloud_request_input_ref' => 'selected_items.attachment:' . (string) $attachment_id . '.cloud_request_input',
		);
	}

	/**
	 * @param array<string,mixed> $blocked_item Source blocked item.
	 * @return array<string,mixed>
	 */
	private function normalize_blocked_item( array $blocked_item ): array {
		return array(
			'attachment_id'         => (int) ( $blocked_item['attachment_id'] ?? $blocked_item['id'] ?? 0 ),
			'source_mime_type'      => $this->first_non_empty_string( array( $blocked_item['mime_type'] ?? '', $blocked_item['source_mime_type'] ?? '' ), 'image/unknown' ),
			'blocked_reason'        => $this->first_non_empty_string( array( $blocked_item['blocked_reason'] ?? '', $blocked_item['reason'] ?? '' ), 'blocked' ),
			'operator_next_action'  => $this->first_non_empty_string( array( $blocked_item['operator_next_action'] ?? '' ), 'adjust_filters_or_skip' ),
			'retryable'             => false,
		);
	}

	/**
	 * @param array<int,array<string,mixed>> $items Items.
	 * @return array<int,int>
	 */
	private function attachment_ids_from_items( array $items ): array {
		$ids = array();
		foreach ( $items as $item ) {
			$attachment_id = (int) ( $item['attachment_id'] ?? 0 );
			if ( $attachment_id > 0 ) {
				$ids[] = $attachment_id;
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * @param array<int,mixed> $values Candidate values.
	 */
	private function first_non_empty_string( array $values, string $fallback ): string {
		foreach ( $values as $value ) {
			if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
				return trim( (string) $value );
			}
		}

		return $fallback;
	}

	/**
	 * @return array<string,bool>
	 */
	private function safety_flags(): array {
		return array(
			'dry_run'                => true,
			'direct_wordpress_write' => false,
			'core_proposal_created'  => false,
			'approval_performed'     => false,
			'preflight_performed'    => false,
			'execution_performed'    => false,
			'action_scheduler_used'  => false,
			'custom_tables_created'  => false,
			'local_queue_created'    => false,
			'lease_store_created'    => false,
			'retry_worker_created'   => false,
			'dead_letter_created'    => false,
			'cloud_scheduler_truth'  => false,
		);
	}
}
