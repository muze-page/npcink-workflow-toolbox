<?php
/**
 * Operation classification policy helper.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

defined( 'ABSPATH' ) || exit;

/**
 * Mirrors the Core operation-classification contract for Toolbox planning.
 */
final class Operation_Classifier {
	public const SUGGESTION_ONLY           = 'suggestion_only';
	public const LOCAL_ADMIN_CONSENT       = 'local_admin_consent';
	public const STRONG_LOCAL_CONFIRMATION = 'strong_local_confirmation';
	public const CORE_PROPOSAL_REQUIRED    = 'core_proposal_required';

	public const SOURCE_WP_ADMIN_UI      = 'wp_admin_ui';
	public const SOURCE_EXTERNAL_ADAPTER = 'external_adapter';
	public const SOURCE_SCHEDULED_TASK   = 'scheduled_task';
	public const SOURCE_CLI              = 'cli';
	public const SOURCE_CLOUD_CALLBACK   = 'cloud_callback';

	public const ACTOR_PRESENT_CLICK = 'present_click';
	public const ACTOR_BACKGROUND    = 'background';
	public const ACTOR_DELEGATED     = 'delegated';

	public const PREVIEW_EXACT_FINAL = 'exact_final';
	public const PREVIEW_SUFFICIENT  = 'sufficient';
	public const PREVIEW_PARTIAL     = 'partial';
	public const PREVIEW_NONE        = 'none';

	public const SCOPE_ONE_FIELD        = 'one_field';
	public const SCOPE_ONE_OBJECT       = 'one_object';
	public const SCOPE_MULTIPLE_OBJECTS = 'multiple_objects';
	public const SCOPE_SITE_WIDE        = 'site_wide';
	public const SCOPE_EXTERNAL_ACCOUNT = 'external_account';

	public const REVERSIBILITY_EASY_UNDO      = 'easy_undo';
	public const REVERSIBILITY_BACKUP_RESTORE = 'backup_restore';
	public const REVERSIBILITY_HARD_RESTORE   = 'hard_restore';
	public const REVERSIBILITY_IRREVERSIBLE   = 'irreversible';

	public const KIND_SUGGEST                 = 'suggest';
	public const KIND_CREATE_DRAFT            = 'create_draft';
	public const KIND_UPDATE_METADATA         = 'update_metadata';
	public const KIND_UPDATE_EXISTING_TERMS   = 'update_existing_terms';
	public const KIND_SET_FEATURED_IMAGE      = 'set_featured_image';
	public const KIND_PUBLISH                 = 'publish';
	public const KIND_UNPUBLISH               = 'unpublish';
	public const KIND_DELETE                  = 'delete';
	public const KIND_REPLACE_FILE            = 'replace_file';
	public const KIND_OVERWRITE_CONTENT       = 'overwrite_content';
	public const KIND_SETTINGS_CHANGE         = 'settings_change';
	public const KIND_PERMISSION_CHANGE       = 'permission_change';
	public const KIND_EXTERNAL_ACCOUNT_CHANGE = 'external_account_change';
	public const KIND_BATCH_PLAN              = 'batch_plan';

	/**
	 * Classifies a Toolbox operation plan before it is executed or handed off.
	 *
	 * @param array<string,mixed> $operation Operation context.
	 * @return array<string,mixed>
	 */
	public function classify( array $operation ): array {
		$source        = $this->sanitize_enum( (string) ( $operation['request_source'] ?? '' ), $this->allowed_request_sources(), self::SOURCE_EXTERNAL_ADAPTER );
		$actor         = $this->sanitize_enum( (string) ( $operation['actor_presence'] ?? '' ), $this->allowed_actor_presence(), self::ACTOR_BACKGROUND );
		$preview       = $this->sanitize_enum( (string) ( $operation['preview_completeness'] ?? '' ), $this->allowed_preview_completeness(), self::PREVIEW_NONE );
		$scope         = $this->sanitize_enum( (string) ( $operation['scope'] ?? '' ), $this->allowed_scopes(), self::SCOPE_MULTIPLE_OBJECTS );
		$reversibility = $this->sanitize_enum( (string) ( $operation['reversibility'] ?? '' ), $this->allowed_reversibility(), self::REVERSIBILITY_HARD_RESTORE );
		$kind          = $this->sanitize_enum( (string) ( $operation['operation_kind'] ?? '' ), $this->allowed_operation_kinds(), self::KIND_BATCH_PLAN );
		$writes_state  = array_key_exists( 'writes_wordpress_state', $operation ) ? (bool) $operation['writes_wordpress_state'] : self::KIND_SUGGEST !== $kind;

		if ( ! $writes_state || self::KIND_SUGGEST === $kind ) {
			return $this->result( self::SUGGESTION_ONLY, array( 'no_wordpress_write' ), array() );
		}

		$core_reasons = $this->core_required_reasons( $source, $actor, $preview, $scope, $reversibility, $kind );
		if ( ! empty( $core_reasons ) ) {
			return $this->result( self::CORE_PROPOSAL_REQUIRED, $core_reasons, $this->core_proposal_evidence() );
		}

		if ( $this->is_high_impact_single_object_kind( $kind ) ) {
			return $this->result(
				self::STRONG_LOCAL_CONFIRMATION,
				array( 'single_object_high_impact_write', 'present_admin_preview_required' ),
				$this->strong_confirmation_evidence()
			);
		}

		if (
			self::SOURCE_WP_ADMIN_UI === $source
			&& self::ACTOR_PRESENT_CLICK === $actor
			&& $this->preview_is_sufficient( $preview )
			&& $this->scope_is_local( $scope )
			&& $this->reversibility_is_low_cost( $reversibility )
		) {
			return $this->result(
				self::LOCAL_ADMIN_CONSENT,
				array( 'present_admin_single_visible_low_risk_write' ),
				$this->local_admin_consent_evidence()
			);
		}

		return $this->result(
			self::CORE_PROPOSAL_REQUIRED,
			array( 'local_admin_consent_requirements_not_met' ),
			$this->core_proposal_evidence()
		);
	}

	/**
	 * @return array<int,string>
	 */
	private function allowed_request_sources(): array {
		return array( self::SOURCE_WP_ADMIN_UI, self::SOURCE_EXTERNAL_ADAPTER, self::SOURCE_SCHEDULED_TASK, self::SOURCE_CLI, self::SOURCE_CLOUD_CALLBACK );
	}

	/**
	 * @return array<int,string>
	 */
	private function allowed_actor_presence(): array {
		return array( self::ACTOR_PRESENT_CLICK, self::ACTOR_BACKGROUND, self::ACTOR_DELEGATED );
	}

	/**
	 * @return array<int,string>
	 */
	private function allowed_preview_completeness(): array {
		return array( self::PREVIEW_EXACT_FINAL, self::PREVIEW_SUFFICIENT, self::PREVIEW_PARTIAL, self::PREVIEW_NONE );
	}

	/**
	 * @return array<int,string>
	 */
	private function allowed_scopes(): array {
		return array( self::SCOPE_ONE_FIELD, self::SCOPE_ONE_OBJECT, self::SCOPE_MULTIPLE_OBJECTS, self::SCOPE_SITE_WIDE, self::SCOPE_EXTERNAL_ACCOUNT );
	}

	/**
	 * @return array<int,string>
	 */
	private function allowed_reversibility(): array {
		return array( self::REVERSIBILITY_EASY_UNDO, self::REVERSIBILITY_BACKUP_RESTORE, self::REVERSIBILITY_HARD_RESTORE, self::REVERSIBILITY_IRREVERSIBLE );
	}

	/**
	 * @return array<int,string>
	 */
	private function allowed_operation_kinds(): array {
		return array(
			self::KIND_SUGGEST,
			self::KIND_CREATE_DRAFT,
			self::KIND_UPDATE_METADATA,
			self::KIND_UPDATE_EXISTING_TERMS,
			self::KIND_SET_FEATURED_IMAGE,
			self::KIND_PUBLISH,
			self::KIND_UNPUBLISH,
			self::KIND_DELETE,
			self::KIND_REPLACE_FILE,
			self::KIND_OVERWRITE_CONTENT,
			self::KIND_SETTINGS_CHANGE,
			self::KIND_PERMISSION_CHANGE,
			self::KIND_EXTERNAL_ACCOUNT_CHANGE,
			self::KIND_BATCH_PLAN,
		);
	}

	/**
	 * @param string $source Request source.
	 * @param string $actor Actor presence.
	 * @param string $preview Preview completeness.
	 * @param string $scope Scope.
	 * @param string $reversibility Reversibility.
	 * @param string $kind Operation kind.
	 * @return array<int,string>
	 */
	private function core_required_reasons( string $source, string $actor, string $preview, string $scope, string $reversibility, string $kind ): array {
		$reasons = array();
		if ( self::SOURCE_WP_ADMIN_UI !== $source ) {
			$reasons[] = 'non_admin_ui_source';
		}
		if ( self::ACTOR_PRESENT_CLICK !== $actor ) {
			$reasons[] = 'actor_not_present_click';
		}
		if ( ! $this->preview_is_sufficient( $preview ) ) {
			$reasons[] = 'preview_not_sufficient';
		}
		if ( ! $this->scope_is_local( $scope ) ) {
			$reasons[] = 'scope_not_single_object';
		}
		if ( self::REVERSIBILITY_IRREVERSIBLE === $reversibility ) {
			$reasons[] = 'irreversible_operation';
		}
		if ( $this->is_always_core_kind( $kind ) ) {
			$reasons[] = 'operation_kind_requires_core_proposal';
		}

		return array_values( array_unique( $reasons ) );
	}

	private function preview_is_sufficient( string $preview ): bool {
		return in_array( $preview, array( self::PREVIEW_EXACT_FINAL, self::PREVIEW_SUFFICIENT ), true );
	}

	private function scope_is_local( string $scope ): bool {
		return in_array( $scope, array( self::SCOPE_ONE_FIELD, self::SCOPE_ONE_OBJECT ), true );
	}

	private function reversibility_is_low_cost( string $reversibility ): bool {
		return in_array( $reversibility, array( self::REVERSIBILITY_EASY_UNDO, self::REVERSIBILITY_BACKUP_RESTORE ), true );
	}

	private function is_always_core_kind( string $kind ): bool {
		return in_array( $kind, array( self::KIND_DELETE, self::KIND_SETTINGS_CHANGE, self::KIND_PERMISSION_CHANGE, self::KIND_EXTERNAL_ACCOUNT_CHANGE, self::KIND_BATCH_PLAN ), true );
	}

	private function is_high_impact_single_object_kind( string $kind ): bool {
		return in_array( $kind, array( self::KIND_PUBLISH, self::KIND_UNPUBLISH, self::KIND_REPLACE_FILE, self::KIND_OVERWRITE_CONTENT ), true );
	}

	/**
	 * @param string            $classification Classification.
	 * @param array<int,string> $reasons Reasons.
	 * @param array<int,string> $required_evidence Required evidence.
	 * @return array<string,mixed>
	 */
	private function result( string $classification, array $reasons, array $required_evidence ): array {
		return array(
			'classification'    => $classification,
			'reasons'           => array_values( array_unique( array_map( array( $this, 'sanitize_token' ), $reasons ) ) ),
			'required_evidence' => array_values( array_unique( array_map( array( $this, 'sanitize_token' ), $required_evidence ) ) ),
			'policy_version'    => 'operation-classification-v1',
		);
	}

	/**
	 * @return array<int,string>
	 */
	private function local_admin_consent_evidence(): array {
		return array( 'actor_user_id', 'source_module', 'target_object_id', 'target_object_type', 'classification', 'ai_suggestion_summary', 'timestamp', 'request_or_correlation_id' );
	}

	/**
	 * @return array<int,string>
	 */
	private function strong_confirmation_evidence(): array {
		return array_merge( $this->local_admin_consent_evidence(), array( 'strong_confirmation', 'reversibility_evidence' ) );
	}

	/**
	 * @return array<int,string>
	 */
	private function core_proposal_evidence(): array {
		return array( 'target_ability_id', 'target_input_or_safe_summary', 'before_after_or_dry_run_evidence', 'reason_risk_required_scopes', 'caller_source_metadata', 'batch_item_details_when_applicable' );
	}

	/**
	 * @param string            $value Raw value.
	 * @param array<int,string> $allowed Allowed values.
	 * @param string            $fallback Fallback value.
	 * @return string
	 */
	private function sanitize_enum( string $value, array $allowed, string $fallback ): string {
		$value = $this->sanitize_token( $value );
		return in_array( $value, $allowed, true ) ? $value : $fallback;
	}

	private function sanitize_token( string $value ): string {
		if ( function_exists( 'sanitize_key' ) ) {
			return sanitize_key( $value );
		}

		$value = strtolower( $value );
		return preg_replace( '/[^a-z0-9_\-]/', '', $value ) ?: '';
	}
}
