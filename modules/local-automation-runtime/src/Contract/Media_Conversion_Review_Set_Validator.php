<?php
/**
 * Media conversion review-set fixture validator.
 *
 * @package Npcink_Local_Automation_Runtime
 */

namespace Npcink\LocalAutomationRuntime\Contract;

final class Media_Conversion_Review_Set_Validator {
	private const CONTRACT_VERSION = 'npcink_local_automation_media_conversion_review_set.v1';
	private const RUNTIME_OWNER    = 'npcink-local-automation-runtime';
	private const TARGET_ABILITY   = 'npcink-abilities-toolkit/build-media-derivative-cloud-request';

	/**
	 * Validates a decoded media conversion review-set fixture.
	 *
	 * @param mixed $review_set Decoded JSON value.
	 * @return array{valid:bool,errors:array<int,string>}
	 */
	public function validate( $review_set ): array {
		$errors = array();

		if ( ! is_array( $review_set ) ) {
			return array(
				'valid'  => false,
				'errors' => array( 'review_set_not_object' ),
			);
		}

		$this->require_value( $review_set, 'contract_version', self::CONTRACT_VERSION, $errors );
		$this->require_value( $review_set, 'runtime_owner', self::RUNTIME_OWNER, $errors );
		$this->require_value( $review_set, 'operation_family', 'media_conversion', $errors );
		$this->require_value( $review_set, 'mode', 'governed_review_set', $errors );
		$this->require_value( $review_set, 'trigger', 'operator_manual_review', $errors );
		$this->require_bool( $review_set, 'retryable', $errors );
		$this->require_non_empty_string( $review_set, 'operator_next_action', $errors );

		$scope = $this->object_at( $review_set, 'scope', $errors );
		if ( array() !== $scope ) {
			$this->require_value( $scope, 'object_type', 'attachment', $errors, 'scope.' );
			$this->require_non_empty_string( $scope, 'target_format', $errors, 'scope.' );
		}

		$selected_items = $this->array_at( $review_set, 'selected_items', $errors );
		$blocked_items  = $this->array_at( $review_set, 'blocked_items', $errors );

		$summary = $this->object_at( $review_set, 'eligibility_summary', $errors );
		if ( array() !== $summary ) {
			$this->require_positive_int( $summary, 'items_total', $errors, 'eligibility_summary.' );
			$this->require_value( $summary, 'selected_count', count( $selected_items ), $errors, 'eligibility_summary.' );
			$this->require_value( $summary, 'blocked_count', count( $blocked_items ), $errors, 'eligibility_summary.' );
			$target_ability_ids = $this->array_at( $summary, 'target_ability_ids', $errors, 'eligibility_summary.' );
			if ( ! in_array( self::TARGET_ABILITY, $target_ability_ids, true ) ) {
				$errors[] = 'eligibility_summary.target_ability_ids_missing_media_derivative_ability';
			}
		}

		foreach ( $selected_items as $index => $item ) {
			if ( is_array( $item ) ) {
				$this->validate_selected_item( $item, (int) $index, $errors );
			} else {
				$errors[] = 'selected_items.' . (int) $index . '_not_object';
			}
		}

		foreach ( $blocked_items as $index => $item ) {
			if ( is_array( $item ) ) {
				$this->validate_blocked_item( $item, (int) $index, $errors );
			} else {
				$errors[] = 'blocked_items.' . (int) $index . '_not_object';
			}
		}

		$retry_guidance = $this->object_at( $review_set, 'retry_guidance', $errors );
		if ( array() !== $retry_guidance ) {
			$this->require_bool( $retry_guidance, 'retryable', $errors, 'retry_guidance.' );
			if ( isset( $review_set['retryable'], $retry_guidance['retryable'] ) && (bool) $review_set['retryable'] !== (bool) $retry_guidance['retryable'] ) {
				$errors[] = 'retry_guidance.retryable_mismatch';
			}
			$this->require_non_empty_string( $retry_guidance, 'operator_next_action', $errors, 'retry_guidance.' );
		}

		$safety = $this->object_at( $review_set, 'safety', $errors );
		if ( array() !== $safety ) {
			$this->require_true( $safety, 'dry_run', $errors, 'safety.' );
			foreach ( $this->forbidden_safety_flags() as $flag ) {
				$this->require_false( $safety, $flag, $errors, 'safety.' );
			}
		}

		return array(
			'valid'  => array() === $errors,
			'errors' => $errors,
		);
	}

	/**
	 * @param array<string,mixed> $item Selected item.
	 * @param int                 $index Item index.
	 * @param array<int,string>   $errors Errors.
	 */
	private function validate_selected_item( array $item, int $index, array &$errors ): void {
		$prefix = 'selected_items.' . $index . '.';
		$this->require_positive_int( $item, 'attachment_id', $errors, $prefix );
		$this->require_non_empty_string( $item, 'source_mime_type', $errors, $prefix );
		$this->require_non_empty_string( $item, 'target_format', $errors, $prefix );
		$this->require_true( $item, 'preview_required', $errors, $prefix );
		$this->require_value( $item, 'target_ability_id', self::TARGET_ABILITY, $errors, $prefix );
		$this->require_value( $item, 'proposal_path', 'core_proposal_required', $errors, $prefix );
		$this->require_false( $item, 'direct_wordpress_write', $errors, $prefix );
		$this->require_non_empty_string( $item, 'result_ref', $errors, $prefix );
	}

	/**
	 * @param array<string,mixed> $item Blocked item.
	 * @param int                 $index Item index.
	 * @param array<int,string>   $errors Errors.
	 */
	private function validate_blocked_item( array $item, int $index, array &$errors ): void {
		$prefix = 'blocked_items.' . $index . '.';
		$this->require_positive_int( $item, 'attachment_id', $errors, $prefix );
		$this->require_non_empty_string( $item, 'blocked_reason', $errors, $prefix );
		$this->require_non_empty_string( $item, 'operator_next_action', $errors, $prefix );
		$this->require_false( $item, 'retryable', $errors, $prefix );
	}

	/**
	 * @return array<int,string>
	 */
	private function forbidden_safety_flags(): array {
		return array(
			'direct_wordpress_write',
			'core_proposal_created',
			'approval_performed',
			'preflight_performed',
			'execution_performed',
			'action_scheduler_used',
			'custom_tables_created',
			'local_queue_created',
			'lease_store_created',
			'retry_worker_created',
			'dead_letter_created',
			'cloud_scheduler_truth',
		);
	}

	/**
	 * @param array<string,mixed> $value Source.
	 * @param string              $key Key.
	 * @param mixed               $expected Expected value.
	 * @param array<int,string>   $errors Errors.
	 * @param string              $prefix Error prefix.
	 */
	private function require_value( array $value, string $key, $expected, array &$errors, string $prefix = '' ): void {
		if ( ! array_key_exists( $key, $value ) || $expected !== $value[ $key ] ) {
			$errors[] = $prefix . $key . '_invalid';
		}
	}

	/**
	 * @param array<string,mixed> $value Source.
	 * @param string              $key Key.
	 * @param array<int,string>   $errors Errors.
	 * @param string              $prefix Error prefix.
	 */
	private function require_true( array $value, string $key, array &$errors, string $prefix = '' ): void {
		if ( true !== ( $value[ $key ] ?? null ) ) {
			$errors[] = $prefix . $key . '_not_true';
		}
	}

	/**
	 * @param array<string,mixed> $value Source.
	 * @param string              $key Key.
	 * @param array<int,string>   $errors Errors.
	 * @param string              $prefix Error prefix.
	 */
	private function require_false( array $value, string $key, array &$errors, string $prefix = '' ): void {
		if ( false !== ( $value[ $key ] ?? null ) ) {
			$errors[] = $prefix . $key . '_not_false';
		}
	}

	/**
	 * @param array<string,mixed> $value Source.
	 * @param string              $key Key.
	 * @param array<int,string>   $errors Errors.
	 * @param string              $prefix Error prefix.
	 */
	private function require_bool( array $value, string $key, array &$errors, string $prefix = '' ): void {
		if ( ! is_bool( $value[ $key ] ?? null ) ) {
			$errors[] = $prefix . $key . '_not_bool';
		}
	}

	/**
	 * @param array<string,mixed> $value Source.
	 * @param string              $key Key.
	 * @param array<int,string>   $errors Errors.
	 * @param string              $prefix Error prefix.
	 */
	private function require_non_empty_string( array $value, string $key, array &$errors, string $prefix = '' ): void {
		if ( ! isset( $value[ $key ] ) || '' === trim( (string) $value[ $key ] ) ) {
			$errors[] = $prefix . $key . '_missing';
		}
	}

	/**
	 * @param array<string,mixed> $value Source.
	 * @param string              $key Key.
	 * @param array<int,string>   $errors Errors.
	 * @param string              $prefix Error prefix.
	 */
	private function require_positive_int( array $value, string $key, array &$errors, string $prefix = '' ): void {
		if ( ! isset( $value[ $key ] ) || (int) $value[ $key ] <= 0 ) {
			$errors[] = $prefix . $key . '_not_positive';
		}
	}

	/**
	 * @param array<string,mixed> $value Source.
	 * @param string              $key Key.
	 * @param array<int,string>   $errors Errors.
	 * @param string              $prefix Error prefix.
	 * @return array<string,mixed>
	 */
	private function object_at( array $value, string $key, array &$errors, string $prefix = '' ): array {
		if ( ! isset( $value[ $key ] ) || ! is_array( $value[ $key ] ) ) {
			$errors[] = $prefix . $key . '_missing';
			return array();
		}

		return $value[ $key ];
	}

	/**
	 * @param array<string,mixed> $value Source.
	 * @param string              $key Key.
	 * @param array<int,string>   $errors Errors.
	 * @param string              $prefix Error prefix.
	 * @return array<int,mixed>
	 */
	private function array_at( array $value, string $key, array &$errors, string $prefix = '' ): array {
		if ( ! isset( $value[ $key ] ) || ! is_array( $value[ $key ] ) ) {
			$errors[] = $prefix . $key . '_missing';
			return array();
		}

		return array_values( $value[ $key ] );
	}
}
