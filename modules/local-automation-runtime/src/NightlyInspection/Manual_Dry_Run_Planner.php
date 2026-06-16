<?php
/**
 * Manual dry-run planner for Nightly Site Inspection.
 *
 * @package Npcink_Local_Automation_Runtime
 */

namespace Npcink\LocalAutomationRuntime\NightlyInspection;

final class Manual_Dry_Run_Planner {
	private const CONTRACT_VERSION = 'npcink_local_automation_runtime.v1';
	private const RUNTIME_OWNER    = 'npcink-local-automation-runtime';
	private const TASK_PROFILE     = 'nightly_site_inspection_morning_brief';

	private Morning_Brief_Builder $builder;

	public function __construct( ?Morning_Brief_Builder $builder = null ) {
		$this->builder = $builder ?: new Morning_Brief_Builder();
	}

	/**
	 * Plans a manual dry-run from a caller-provided snapshot.
	 *
	 * @param array<string,mixed> $snapshot Site snapshot.
	 * @return array<string,mixed>
	 */
	public function plan( array $snapshot ): array {
		$brief          = $this->builder->build( $snapshot );
		$run_id         = $this->string_value( $brief, 'run_id', 'local-preview-run' );
		$generated_at   = $this->string_value( $brief, 'generated_at', '2026-06-15T00:00:00Z' );
		$priorities     = $this->array_value( $brief, 'priorities' );
		$preparations   = $this->array_value( $brief, 'writing_preparation' );
		$score_actions  = $this->build_score_actions( $priorities, $run_id );
		$brief_actions  = $this->build_preparation_actions( $preparations, $run_id );
		$actions        = array_merge( $score_actions, $brief_actions );
		$action_count   = count( $actions );
		$target_ability = array_values(
			array_unique(
				array_map(
					static fn( array $action ): string => (string) $action['target_ability_id'],
					$actions
				)
			)
		);

		return array(
			'contract_version'       => self::CONTRACT_VERSION,
			'fixture_id'             => '',
			'mode'                   => 'dry_run_replay',
			'runtime_owner'          => self::RUNTIME_OWNER,
			'task_profile'           => self::TASK_PROFILE,
			'core_runtime_execution' => false,
			'background_execution'   => false,
			'job'                    => array(
				'job_id'              => 'manual-nightly-inspection-' . $run_id,
				'runtime_id'          => 'manual-nightly-inspection',
				'source'              => 'operator_started',
				'actor'               => array(
					'type'    => 'wp_user',
					'display' => 'Administrator',
				),
				'correlation_id'      => 'corr-' . $run_id,
				'idempotency_key'     => $run_id,
				'title'               => 'Preview Nightly Site Inspection Morning Brief',
				'summary'             => 'Manual dry-run preview only. No cron, scheduler, Cloud call, Core proposal, or WordPress write occurs.',
				'status'              => 'planned',
				'eligibility_summary' => array(
					'scope'                  => $this->scope_from_brief( $brief ),
					'candidate_count'        => $action_count,
					'eligible_count'         => $action_count,
					'blocked_count'          => 0,
					'needs_input_count'      => 0,
					'allowed_ability_ids'    => $target_ability,
					'disallowed_ability_ids' => array(
						'npcink-toolbox/apply-approved-fix',
					),
					'risk_level'             => $this->risk_level( $brief ),
					'operator_next_action'   => 'review_morning_brief_preview',
					'retryable'              => false,
				),
				'blocked_items'       => array(),
				'actions'             => $actions,
				'limits'              => array(
					'max_actions'             => $action_count,
					'max_concurrency'         => 1,
					'max_attempts_per_action' => 1,
					'lease_timeout_seconds'   => 0,
					'schedule_window'         => 'none',
				),
			),
			'preview'                => array(
				'morning_brief' => $brief,
			),
			'core_handoff'           => array(
				'proposal_id'          => '',
				'proposal_status'      => 'not_created',
				'preflight_status'     => 'not_requested',
				'correlation_id'       => 'corr-' . $run_id,
				'approved_input_hash'  => '',
				'batch_review_summary' => array(
					'summary_version'       => 'core-batch-review-summary-v1',
					'action_count'          => $action_count,
					'blocked_count'         => 0,
					'needs_input_count'     => 0,
					'target_ability_ids'    => $target_ability,
					'operator_next_action'  => 'review_morning_brief_preview',
					'retryable'             => false,
					'final_execution_owner' => 'none_in_manual_dry_run',
					'core_execution'        => false,
					'commit_execution'      => false,
				),
				'core_execution'       => false,
				'commit_execution'     => false,
			),
			'operator_controls'      => array(
				'pause'       => true,
				'resume'      => false,
				'cancel'      => true,
				'retry'       => false,
				'kill_switch' => true,
			),
			'runtime_events'         => array(
				array(
					'event'            => 'runtime.job.profile_validated',
					'job_id'           => 'manual-nightly-inspection-' . $run_id,
					'correlation_id'   => 'corr-' . $run_id,
					'contract_version' => self::CONTRACT_VERSION,
					'created_at'       => $generated_at,
					'metadata'         => array(
						'task_profile'           => self::TASK_PROFILE,
						'core_runtime_execution' => false,
						'background_execution'   => false,
					),
				),
			),
			'acceptance'             => array(
				'phase'                         => 'phase_1_contract_only',
				'schema_only'                   => true,
				'dry_run_replay_only'           => true,
				'requires_future_runtime_repo'  => true,
				'core_tables_created'           => false,
				'core_routes_created'           => false,
				'worker_created'                => false,
				'scheduler_created'             => false,
				'lease_store_created'           => false,
				'dead_letter_processor_created' => false,
			),
		);
	}

	/**
	 * @param array<int,mixed> $priorities Priorities.
	 * @return array<int,array<string,mixed>>
	 */
	private function build_score_actions( array $priorities, string $run_id ): array {
		$actions = array();
		foreach ( $priorities as $priority ) {
			if ( ! is_array( $priority ) ) {
				continue;
			}
			$object_id = max( 0, (int) ( $priority['object_id'] ?? 0 ) );
			if ( $object_id <= 0 ) {
				continue;
			}
			$actions[] = array(
				'action_id'         => 'score-content-item-' . $object_id,
				'target_ability_id' => 'npcink-toolbox/score-content-item',
				'execution_profile' => 'manual_dry_run_preview_only',
				'input_hash'        => 'sha256:' . hash( 'sha256', 'score:' . $run_id . ':' . $object_id ),
				'preview_ref'       => 'preview.morning_brief.priorities.' . ( count( $actions ) ),
				'depends_on'        => array(),
				'status'            => 'ready',
				'attempt_count'     => 0,
				'max_attempts'      => 1,
				'retryable'         => false,
				'blocked_reason'    => '',
				'output_refs'       => array(
					'preview.morning_brief.priorities.' . ( count( $actions ) ),
				),
				'idempotency_key'   => $run_id . ':score-content-item-' . $object_id,
			);
		}

		return $actions;
	}

	/**
	 * @param array<int,mixed> $preparations Writing preparation entries.
	 * @return array<int,array<string,mixed>>
	 */
	private function build_preparation_actions( array $preparations, string $run_id ): array {
		$actions = array();
		foreach ( $preparations as $index => $preparation ) {
			if ( ! is_array( $preparation ) ) {
				continue;
			}
			$source_ids = $preparation['source_object_ids'] ?? array();
			$source_id  = is_array( $source_ids ) ? max( 0, (int) ( $source_ids[0] ?? 0 ) ) : 0;
			if ( $source_id <= 0 ) {
				continue;
			}
			$actions[] = array(
				'action_id'         => 'prepare-writing-evidence-' . $source_id,
				'target_ability_id' => 'npcink-toolbox/prepare-writing-evidence',
				'execution_profile' => 'manual_dry_run_preview_only',
				'input_hash'        => 'sha256:' . hash( 'sha256', 'evidence:' . $run_id . ':' . $source_id ),
				'preview_ref'       => 'preview.morning_brief.writing_preparation.' . (int) $index,
				'depends_on'        => array(),
				'status'            => 'ready',
				'attempt_count'     => 0,
				'max_attempts'      => 1,
				'retryable'         => false,
				'blocked_reason'    => '',
				'output_refs'       => array(
					'preview.morning_brief.writing_preparation.' . (int) $index,
				),
				'idempotency_key'   => $run_id . ':prepare-writing-evidence-' . $source_id,
			);
		}

		return $actions;
	}

	/**
	 * @param array<string,mixed> $brief Morning Brief.
	 * @return array<string,mixed>
	 */
	private function scope_from_brief( array $brief ): array {
		$post_ids       = array();
		$attachment_ids = array();
		foreach ( $this->array_value( $brief, 'priorities' ) as $priority ) {
			if ( ! is_array( $priority ) ) {
				continue;
			}
			$object_id = max( 0, (int) ( $priority['object_id'] ?? 0 ) );
			if ( $object_id <= 0 ) {
				continue;
			}
			if ( 'attachment' === ( $priority['object_type'] ?? '' ) ) {
				$attachment_ids[] = $object_id;
			} else {
				$post_ids[] = $object_id;
			}
		}

		return array(
			'post_ids'       => array_values( array_unique( $post_ids ) ),
			'attachment_ids' => array_values( array_unique( $attachment_ids ) ),
		);
	}

	/**
	 * @param array<string,mixed> $brief Morning Brief.
	 */
	private function risk_level( array $brief ): string {
		$summary = $brief['summary'] ?? array();
		if ( is_array( $summary ) && (int) ( $summary['risk_total'] ?? 0 ) > 0 ) {
			return 'medium';
		}

		return 'low';
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
