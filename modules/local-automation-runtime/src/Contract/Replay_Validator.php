<?php
/**
 * Dry-run replay fixture validator.
 *
 * @package Npcink_Local_Automation_Runtime
 */

namespace Npcink\LocalAutomationRuntime\Contract;

final class Replay_Validator {
	private const CONTRACT_VERSION = 'npcink_local_automation_runtime.v1';
	private const RUNTIME_OWNER    = 'npcink-local-automation-runtime';

	/**
	 * Validates a decoded dry-run replay fixture.
	 *
	 * @param mixed $replay Decoded JSON value.
	 * @return array{valid:bool,errors:array<int,string>}
	 */
	public function validate( $replay ): array {
		$errors = array();

		if ( ! is_array( $replay ) ) {
			return array(
				'valid'  => false,
				'errors' => array( 'replay_not_object' ),
			);
		}

		$this->require_value( $replay, 'contract_version', self::CONTRACT_VERSION, $errors );
		$this->require_value( $replay, 'mode', 'dry_run_replay', $errors );
		$this->require_value( $replay, 'runtime_owner', self::RUNTIME_OWNER, $errors );
		$this->require_false( $replay, 'core_runtime_execution', $errors );
		$this->require_false( $replay, 'background_execution', $errors );

		$job = $this->object_at( $replay, 'job', $errors );
		if ( array() !== $job ) {
			$this->validate_job( $job, $errors );
		}

		$core_handoff = $this->object_at( $replay, 'core_handoff', $errors );
		if ( array() !== $core_handoff ) {
			$this->require_false( $core_handoff, 'core_execution', $errors );
			$this->require_false( $core_handoff, 'commit_execution', $errors );
		}

		$operator_controls = $this->object_at( $replay, 'operator_controls', $errors );
		if ( array() !== $operator_controls ) {
			$this->require_true( $operator_controls, 'kill_switch', $errors );
		}

		$acceptance = $this->object_at( $replay, 'acceptance', $errors );
		if ( array() !== $acceptance ) {
			$this->require_value( $acceptance, 'phase', 'phase_1_contract_only', $errors );
			$this->require_true( $acceptance, 'schema_only', $errors );
			$this->require_true( $acceptance, 'dry_run_replay_only', $errors );
			foreach ( array( 'core_tables_created', 'core_routes_created', 'worker_created', 'scheduler_created', 'lease_store_created', 'dead_letter_processor_created' ) as $field ) {
				$this->require_false( $acceptance, $field, $errors );
			}
		}

		return array(
			'valid'  => array() === $errors,
			'errors' => $errors,
		);
	}

	/**
	 * @param array<string,mixed> $job Job object.
	 * @param array<int,string>  $errors Errors.
	 */
	private function validate_job( array $job, array &$errors ): void {
		foreach ( array( 'job_id', 'runtime_id', 'source', 'correlation_id', 'idempotency_key', 'status' ) as $field ) {
			if ( ! isset( $job[ $field ] ) || '' === (string) $job[ $field ] ) {
				$errors[] = 'job_' . $field . '_missing';
			}
		}

		if ( ! in_array( $job['status'] ?? '', array( 'planned', 'blocked', 'awaiting_core_proposal', 'awaiting_core_approval', 'awaiting_core_preflight', 'ready' ), true ) ) {
			$errors[] = 'job_status_not_phase_1';
		}

		$blocked_items = $this->array_at( $job, 'blocked_items', $errors );
		$summary       = $this->object_at( $job, 'eligibility_summary', $errors );
		if ( array() !== $summary && isset( $summary['blocked_count'] ) && (int) $summary['blocked_count'] !== count( $blocked_items ) ) {
			$errors[] = 'blocked_count_mismatch';
		}

		$actions = $this->array_at( $job, 'actions', $errors );
		foreach ( $actions as $index => $action ) {
			if ( is_array( $action ) ) {
				$this->validate_action( $action, (int) $index, $errors );
			} else {
				$errors[] = 'action_' . (int) $index . '_not_object';
			}
		}
	}

	/**
	 * @param array<string,mixed> $action Action object.
	 * @param int                 $index Action index.
	 * @param array<int,string>   $errors Errors.
	 */
	private function validate_action( array $action, int $index, array &$errors ): void {
		foreach ( array( 'action_id', 'target_ability_id', 'execution_profile', 'input_hash', 'preview_ref', 'status', 'idempotency_key' ) as $field ) {
			if ( ! isset( $action[ $field ] ) || '' === (string) $action[ $field ] ) {
				$errors[] = 'action_' . $index . '_' . $field . '_missing';
			}
		}

		if ( ! in_array( $action['status'] ?? '', array( 'blocked', 'waiting', 'ready', 'skipped' ), true ) ) {
			$errors[] = 'action_' . $index . '_status_not_phase_1';
		}

		foreach ( array( 'leased', 'running', 'succeeded', 'failed', 'retry_wait', 'dead_lettered' ) as $forbidden_status ) {
			if ( $forbidden_status === ( $action['status'] ?? '' ) ) {
				$errors[] = 'action_' . $index . '_execution_status_forbidden';
			}
		}
	}

	/**
	 * @param array<string,mixed> $value Source.
	 * @param string              $key Key.
	 * @param mixed               $expected Expected value.
	 * @param array<int,string>   $errors Errors.
	 */
	private function require_value( array $value, string $key, $expected, array &$errors ): void {
		if ( ! array_key_exists( $key, $value ) || $expected !== $value[ $key ] ) {
			$errors[] = $key . '_invalid';
		}
	}

	/**
	 * @param array<string,mixed> $value Source.
	 * @param string              $key Key.
	 * @param array<int,string>   $errors Errors.
	 */
	private function require_true( array $value, string $key, array &$errors ): void {
		if ( true !== ( $value[ $key ] ?? null ) ) {
			$errors[] = $key . '_not_true';
		}
	}

	/**
	 * @param array<string,mixed> $value Source.
	 * @param string              $key Key.
	 * @param array<int,string>   $errors Errors.
	 */
	private function require_false( array $value, string $key, array &$errors ): void {
		if ( false !== ( $value[ $key ] ?? null ) ) {
			$errors[] = $key . '_not_false';
		}
	}

	/**
	 * @param array<string,mixed> $value Source.
	 * @param string              $key Key.
	 * @param array<int,string>   $errors Errors.
	 * @return array<string,mixed>
	 */
	private function object_at( array $value, string $key, array &$errors ): array {
		if ( ! isset( $value[ $key ] ) || ! is_array( $value[ $key ] ) ) {
			$errors[] = $key . '_missing';
			return array();
		}

		return $value[ $key ];
	}

	/**
	 * @param array<string,mixed> $value Source.
	 * @param string              $key Key.
	 * @param array<int,string>   $errors Errors.
	 * @return array<int,mixed>
	 */
	private function array_at( array $value, string $key, array &$errors ): array {
		if ( ! isset( $value[ $key ] ) || ! is_array( $value[ $key ] ) ) {
			$errors[] = $key . '_missing';
			return array();
		}

		return array_values( $value[ $key ] );
	}
}

