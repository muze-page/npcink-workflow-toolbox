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
		$operational    = $this->operational_detail( $cloud_result, count( $actions_by_key ) );
		$priority_queue = $this->priority_queue( $cloud_result, $actions_by_key );
		$core_intake_package = $this->core_intake_package( $cloud_result );

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
			'provider'               => 'npcink_cloud',
			'composition_role'       => 'morning_brief_cloud_runtime_detail',
			'source_run_id'          => $this->text_value( $cloud_result, array( 'run_id', 'cloud_run_id', 'source_run_id' ) ),
			'status'                 => $this->text_value( $cloud_result, array( 'status' ), 'merged' ),
			'worker_phase'           => $operational['worker_phase'],
			'execution_kind'         => $operational['execution_kind'],
			'eligibility_summary'    => $operational['eligibility_summary'],
			'blocked_items'          => $operational['blocked_items'],
			'operator_next_action'   => $operational['operator_next_action'],
			'retryable'              => $operational['retryable'],
			'retry_guidance'         => $operational['retry_guidance'],
			'action_count'           => count( $actions_by_key ),
			'merged_priority_count'  => $merged_count,
			'priority_queue_count'   => count( $priority_queue ),
			'core_intake_package_available' => array() !== $core_intake_package,
			'core_intake_package_contract'  => (string) ( $core_intake_package['contract_version'] ?? '' ),
			'core_intake_target_route'      => (string) ( $core_intake_package['target_route'] ?? '' ),
			'core_intake_receipt_owner'     => (string) ( $core_intake_package['receipt_expectation']['receipt_owner'] ?? '' ),
			'direct_wordpress_write' => false,
			'final_write_path'       => 'core_proposal_required',
			'requires_local_review'  => true,
		);

		if ( array() !== $core_intake_package ) {
			$merged['cloud_runtime']['core_intake_package'] = $core_intake_package;
		}

		if ( array() !== $priority_queue ) {
			$merged['priority_queue'] = $priority_queue;
		}

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
		$actions_by_key   = $this->actions_by_key( $cloud_result );
		$actions          = array_values( $actions_by_key );
		$operational      = $this->operational_detail( $cloud_result, count( $actions ) );
		$action_summaries = array_map( array( $this, 'cloud_action_summary' ), $actions );
		$priority_queue   = $this->priority_queue( $cloud_result, $actions_by_key );
		$core_intake_package = $this->core_intake_package( $cloud_result );

		$patch = array(
			'contract_version'       => self::CONTRACT_VERSION,
			'provider'               => 'npcink_cloud',
			'composition_role'       => 'morning_brief_cloud_runtime_patch',
			'source_run_id'          => $this->text_value( $cloud_result, array( 'run_id', 'cloud_run_id', 'source_run_id' ) ),
			'status'                 => $this->text_value( $cloud_result, array( 'status' ), 'available' ),
			'worker_phase'           => $operational['worker_phase'],
			'execution_kind'         => $operational['execution_kind'],
			'eligibility_summary'    => $operational['eligibility_summary'],
			'blocked_items'          => $operational['blocked_items'],
			'review_items'           => $action_summaries,
			'operator_next_action'   => $operational['operator_next_action'],
			'retryable'              => $operational['retryable'],
			'retry_guidance'         => $operational['retry_guidance'],
			'actions'                => $action_summaries,
			'action_count'           => count( $actions ),
			'priority_queue'         => $priority_queue,
			'priority_queue_count'   => count( $priority_queue ),
			'core_intake_package_available' => array() !== $core_intake_package,
			'core_intake_package_contract'  => (string) ( $core_intake_package['contract_version'] ?? '' ),
			'core_intake_target_route'      => (string) ( $core_intake_package['target_route'] ?? '' ),
			'core_intake_receipt_owner'     => (string) ( $core_intake_package['receipt_expectation']['receipt_owner'] ?? '' ),
			'direct_wordpress_write' => false,
			'final_write_path'       => 'core_proposal_required',
			'requires_local_review'  => true,
		);

		if ( array() !== $core_intake_package ) {
			$patch['core_intake_package'] = $core_intake_package;
		}

		return $patch;
	}

	/**
	 * @param array<string,mixed> $cloud_result Cloud result.
	 * @return array<string,mixed>
	 */
	private function operational_detail( array $cloud_result, int $review_item_count ): array {
		return array(
			'worker_phase'         => $this->text_value( $cloud_result, array( 'worker_phase' ), '' ),
			'execution_kind'       => $this->sanitize_key( $this->text_value( $cloud_result, array( 'execution_kind' ), 'nightly_site_inspection' ) ),
			'eligibility_summary'  => $this->eligibility_summary( $cloud_result['eligibility_summary'] ?? array(), $review_item_count ),
			'blocked_items'        => $this->blocked_items( $cloud_result['blocked_items'] ?? array() ),
			'operator_next_action' => $this->sanitize_key( $this->text_value( $cloud_result, array( 'operator_next_action' ), 'review_cloud_batch_result' ) ),
			'retryable'            => true === ( $cloud_result['retryable'] ?? false ),
			'retry_guidance'       => $this->retry_guidance( $cloud_result['retry_guidance'] ?? array() ),
		);
	}

	/**
	 * @param mixed $summary Cloud eligibility summary.
	 * @return array<string,int>
	 */
	private function eligibility_summary( $summary, int $review_item_count ): array {
		$summary = is_array( $summary ) ? $summary : array();
		return array(
			'items_total'      => max( 0, (int) ( $summary['items_total'] ?? $summary['total_count'] ?? 0 ) ),
			'eligible_count'  => max( 0, (int) ( $summary['eligible_count'] ?? 0 ) ),
			'blocked_count'   => max( 0, (int) ( $summary['blocked_count'] ?? 0 ) ),
			'reviewable_count' => max( 0, (int) ( $summary['reviewable_count'] ?? $review_item_count ) ),
			'selected_count'  => max( 0, (int) ( $summary['selected_count'] ?? $review_item_count ) ),
		);
	}

	/**
	 * @param mixed $items Cloud blocked items.
	 * @return array<int,array<string,mixed>>
	 */
	private function blocked_items( $items ): array {
		$items  = is_array( $items ) ? $items : array();
		$result = array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$result[] = array(
				'object_type'          => $this->sanitize_key( (string) ( $item['object_type'] ?? $item['type'] ?? '' ) ),
				'object_id'            => max( 0, (int) ( $item['object_id'] ?? $item['post_id'] ?? $item['attachment_id'] ?? 0 ) ),
				'blocked_reason'       => $this->sanitize_key( (string) ( $item['blocked_reason'] ?? $item['reason'] ?? '' ) ),
				'operator_next_action' => $this->sanitize_key( (string) ( $item['operator_next_action'] ?? '' ) ),
			);
			if ( count( $result ) >= 20 ) {
				break;
			}
		}
		return $result;
	}

	/**
	 * @param mixed $guidance Cloud retry guidance.
	 * @return array<string,mixed>
	 */
	private function retry_guidance( $guidance ): array {
		$guidance = is_array( $guidance ) ? $guidance : array();
		return array(
			'retryable'            => true === ( $guidance['retryable'] ?? false ),
			'reason'               => $this->sanitize_key( (string) ( $guidance['reason'] ?? '' ) ),
			'operator_next_action' => $this->sanitize_key( (string) ( $guidance['operator_next_action'] ?? '' ) ),
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
	 * @param array<string,mixed>                $cloud_result Cloud result.
	 * @param array<string,array<string,mixed>> $actions_by_key Cloud actions indexed by object.
	 * @return array<int,array<string,mixed>>
	 */
	private function priority_queue( array $cloud_result, array $actions_by_key ): array {
		$queue = $this->extract_priority_queue( $cloud_result );
		$items = array();

		foreach ( $queue as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$summary = $this->priority_queue_item( $item, $actions_by_key );
			if ( 0 === (int) $summary['object_id'] || '' === (string) $summary['object_type'] ) {
				continue;
			}
			$items[] = $summary;
			if ( count( $items ) >= 10 ) {
				break;
			}
		}

		if ( array() === $items ) {
			foreach ( $actions_by_key as $action ) {
				$summary = $this->priority_queue_item( $action, $actions_by_key );
				if ( 0 === (int) $summary['object_id'] || '' === (string) $summary['object_type'] ) {
					continue;
				}
				$items[] = $summary;
				if ( count( $items ) >= 10 ) {
					break;
				}
			}
		}

		return $items;
	}

	/**
	 * @param array<string,mixed> $cloud_result Cloud result.
	 * @return array<int,mixed>
	 */
	private function extract_priority_queue( array $cloud_result ): array {
		if ( isset( $cloud_result['morning_brief'] ) && is_array( $cloud_result['morning_brief'] ) ) {
			$brief = $cloud_result['morning_brief'];
			if ( isset( $brief['priority_queue'] ) && is_array( $brief['priority_queue'] ) ) {
				return $brief['priority_queue'];
			}
		}
		if ( isset( $cloud_result['priority_queue'] ) && is_array( $cloud_result['priority_queue'] ) ) {
			return $cloud_result['priority_queue'];
		}
		if ( isset( $cloud_result['result'] ) && is_array( $cloud_result['result'] ) ) {
			return $this->extract_priority_queue( $cloud_result['result'] );
		}

		return array();
	}

	/**
	 * @param array<string,mixed>                $item Queue item.
	 * @param array<string,array<string,mixed>> $actions_by_key Cloud actions indexed by object.
	 * @return array<string,mixed>
	 */
	private function priority_queue_item( array $item, array $actions_by_key ): array {
		$key = $this->object_key( $item );
		$action = '' !== $key && isset( $actions_by_key[ $key ] ) ? $actions_by_key[ $key ] : array();
		$reason_codes = $this->string_list( $item['reason_codes'] ?? ( $action['reason_codes'] ?? array() ), 12 );

		return array(
			'action_id'              => $this->bounded_text( (string) ( $item['action_id'] ?? $action['action_id'] ?? '' ), 120 ),
			'object_type'            => $this->sanitize_key( (string) ( $item['object_type'] ?? $action['object_type'] ?? $item['type'] ?? $action['type'] ?? '' ) ),
			'object_id'              => max( 0, (int) ( $item['object_id'] ?? $action['object_id'] ?? $item['post_id'] ?? $action['post_id'] ?? $item['attachment_id'] ?? $action['attachment_id'] ?? 0 ) ),
			'quality_score'          => max( 0, min( 100, (int) ( $item['score'] ?? $item['quality_score'] ?? $action['score'] ?? $action['quality_score'] ?? 0 ) ) ),
			'severity'               => $this->sanitize_key( (string) ( $item['severity'] ?? $action['severity'] ?? 'notice' ) ),
			'priority_reason'        => $this->bounded_text( (string) ( $item['priority_reason'] ?? $action['priority_reason'] ?? '' ), 500 ),
			'reason_codes'           => $reason_codes,
			'group_ids'              => $this->string_list( $item['group_ids'] ?? ( $action['group_ids'] ?? array() ), 8 ),
			'evidence_summary'       => $this->bounded_text( (string) ( $item['evidence_summary'] ?? $action['evidence_summary'] ?? '' ), 500 ),
			'recommended_next_action' => $this->sanitize_key( (string) ( $item['recommended_next_action'] ?? $action['recommended_next_action'] ?? 'review_item' ) ),
			'direct_wordpress_write' => false,
			'final_write_path'       => 'core_proposal_required',
			'requires_local_review'  => true,
		);
	}

	/**
	 * @param array<string,mixed> $cloud_result Cloud result.
	 * @return array<string,mixed>
	 */
	private function core_intake_package( array $cloud_result ): array {
		$package = $this->extract_core_intake_package( $cloud_result );
		if ( ! is_array( $package ) || array() === $package ) {
			return array();
		}

		$receipt = is_array( $package['receipt_expectation'] ?? null ) ? $package['receipt_expectation'] : array();

		return array(
			'contract_version'                    => $this->bounded_text( (string) ( $package['contract_version'] ?? '' ), 120 ),
			'selected_review_item_ids'            => $this->text_list( $package['selected_review_item_ids'] ?? array(), 10, 160 ),
			'selected_review_items'               => $this->core_intake_selected_items( $package['selected_review_items'] ?? array() ),
			'target_route'                        => $this->bounded_text( (string) ( $package['target_route'] ?? '' ), 160 ),
			'target_plan_ability_id'              => $this->bounded_text( (string) ( $package['target_plan_ability_id'] ?? '' ), 160 ),
			'target_plan_contract'                => $this->bounded_text( (string) ( $package['target_plan_contract'] ?? '' ), 160 ),
			'core_review_plan_idempotency_key'    => $this->bounded_text( (string) ( $package['core_review_plan_idempotency_key'] ?? '' ), 191 ),
			'proposal_created'                    => false,
			'proposal_state_owner'                => $this->sanitize_key( (string) ( $package['proposal_state_owner'] ?? 'npcink-governance-core' ) ),
			'approval_truth'                      => $this->sanitize_key( (string) ( $package['approval_truth'] ?? 'wordpress_local' ) ),
			'final_write_truth'                   => $this->sanitize_key( (string) ( $package['final_write_truth'] ?? 'wordpress_local' ) ),
			'cloud_role'                          => $this->sanitize_key( (string) ( $package['cloud_role'] ?? 'runtime_detail' ) ),
			'cloud_scheduler_truth'               => false,
			'direct_wordpress_write'              => false,
			'receipt_expectation'                 => array(
				'expected_local_receipt' => $this->bounded_text( (string) ( $receipt['expected_local_receipt'] ?? 'core_proposal_id' ), 120 ),
				'receipt_owner'          => $this->sanitize_key( (string) ( $receipt['receipt_owner'] ?? 'wordpress_toolbox_local' ) ),
				'cloud_receipt_storage'  => $this->sanitize_key( (string) ( $receipt['cloud_receipt_storage'] ?? 'not_canonical' ) ),
			),
		);
	}

	/**
	 * @param array<string,mixed> $cloud_result Cloud result.
	 * @return mixed
	 */
	private function extract_core_intake_package( array $cloud_result ) {
		if ( isset( $cloud_result['core_intake_package'] ) && is_array( $cloud_result['core_intake_package'] ) ) {
			return $cloud_result['core_intake_package'];
		}
		if ( isset( $cloud_result['result'] ) && is_array( $cloud_result['result'] ) ) {
			return $this->extract_core_intake_package( $cloud_result['result'] );
		}

		return null;
	}

	/**
	 * @param mixed $items Selected Core intake items.
	 * @return array<int,array<string,mixed>>
	 */
	private function core_intake_selected_items( $items ): array {
		$items  = is_array( $items ) ? $items : array();
		$result = array();

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$result[] = array(
				'action_id'               => $this->bounded_text( (string) ( $item['action_id'] ?? '' ), 120 ),
				'title'                   => $this->bounded_text( (string) ( $item['title'] ?? '' ), 160 ),
				'object_type'             => $this->sanitize_key( (string) ( $item['object_type'] ?? $item['type'] ?? '' ) ),
				'object_id'               => max( 0, (int) ( $item['object_id'] ?? $item['post_id'] ?? $item['attachment_id'] ?? 0 ) ),
				'score'                   => max( 0, min( 100, (int) ( $item['score'] ?? $item['quality_score'] ?? 0 ) ) ),
				'severity'                => $this->sanitize_key( (string) ( $item['severity'] ?? '' ) ),
				'reason_codes'            => $this->string_list( $item['reason_codes'] ?? array(), 12 ),
				'evidence_summary'        => $this->bounded_text( (string) ( $item['evidence_summary'] ?? '' ), 500 ),
				'recommended_next_action' => $this->sanitize_key( (string) ( $item['recommended_next_action'] ?? 'operator_review' ) ),
				'direct_wordpress_write'  => false,
				'final_write_path'        => 'core_proposal_required',
				'requires_local_review'   => true,
			);
			if ( count( $result ) >= 5 ) {
				break;
			}
		}

		return $result;
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
	 * @param mixed $items Items.
	 * @return array<int,string>
	 */
	private function text_list( $items, int $limit, int $max_chars ): array {
		$values = is_array( $items ) ? $items : array();
		$list   = array();
		foreach ( $values as $item ) {
			$value = $this->bounded_text( (string) $item, $max_chars );
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
		$value = trim( $this->plain_text( $value ) );
		if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) && mb_strlen( $value ) > $max_chars ) {
			return mb_substr( $value, 0, $max_chars );
		}

		return strlen( $value ) > $max_chars ? substr( $value, 0, $max_chars ) : $value;
	}

	private function plain_text( string $value ): string {
		if ( function_exists( 'wp_strip_all_tags' ) ) {
			return (string) wp_strip_all_tags( $value );
		}

		return (string) preg_replace( '/<[^>]*>/', '', $value );
	}
}
