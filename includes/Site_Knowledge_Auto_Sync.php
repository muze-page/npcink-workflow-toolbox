<?php
/**
 * Cloud Addon Site Knowledge change bridge status projection.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

defined( 'ABSPATH' ) || exit;

final class Site_Knowledge_Auto_Sync {
	private const LEGACY_QUEUE_OPTION   = 'npcink_toolbox_site_knowledge_auto_sync_queue';
	private const LEGACY_CRON_HOOK      = 'npcink_toolbox_process_site_knowledge_auto_sync';
	private const LEGACY_RECONCILE_HOOK = 'npcink_toolbox_reconcile_site_knowledge_auto_sync';

	public function __construct( Provider_Client $client ) {
		unset( $client );
	}

	/**
	 * Retires the legacy Toolbox fallback by clearing its local scheduled work.
	 */
	public function register_hooks(): void {
		self::clear_legacy_state();
	}

	/**
	 * Clears legacy Toolbox fallback hooks and buffered delivery state.
	 */
	public static function deactivate(): void {
		self::clear_legacy_state();
	}

	/**
	 * Returns Cloud Addon-owned Site Knowledge change bridge health.
	 *
	 * @return array<string,mixed>
	 */
	public static function health_snapshot(): array {
		self::clear_legacy_state();

		if ( self::cloud_addon_bridge_available() ) {
			return self::cloud_addon_bridge_health_snapshot();
		}

		$ownership        = self::default_cloud_boundary_ownership( 'cloud_addon_required' );
		$truth_boundaries = self::default_cloud_truth_boundaries();

		return array(
			'owner'                   => 'cloud_addon_required',
			'mode'                    => 'site_knowledge_change_bridge_required',
			'legacy_toolbox_fallback' => false,
			'status'                  => 'disabled',
			'enabled'                 => false,
			'configured'              => false,
			'verified'                => false,
			'buffer_count'            => 0,
			'queue_count'             => 0,
			'next_flush_at'           => '',
			'next_queue_run_at'       => '',
			'next_reconcile_at'       => '',
			'last_delivery_at'        => '',
			'last_success_at'         => '',
			'last_error_code'         => '',
			'wp_cron_disabled'        => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
			'server_cron_recommended' => false,
			'cron_command'            => '',
			'wp_cli_command'          => '',
			'write_posture'           => 'suggestion_only',
			'index_lifecycle_owner'   => 'cloud_service',
			'scheduler_truth'         => false,
			'workflow_truth'          => false,
			'wordpress_write_included' => false,
			'ownership'               => $ownership,
			'truth_boundaries'        => $truth_boundaries,
			'site_knowledge_cloud_boundary' => self::cloud_boundary_projection(
				array(
					'ownership'        => $ownership,
					'truth_boundaries' => $truth_boundaries,
				)
			),
			'compatibility_aliases'    => array( 'auto_sync', 'queue_count', 'next_queue_run_at' ),
			'message'                 => __( 'Install and verify Cloud Addon to enable automatic Site Knowledge public-change delivery. Manual Site Knowledge sync remains available from Toolbox.', 'npcink-workflow-toolbox' ),
		);
	}

	public static function cloud_addon_bridge_available(): bool {
		return function_exists( 'npcink_cloud_addon_site_knowledge_change_bridge_health' );
	}

	/**
	 * Returns the read-only Site Knowledge owner/truth projection from bridge health.
	 *
	 * @param array<string,mixed> $snapshot Bridge health or Cloud status payload.
	 * @return array<string,mixed>
	 */
	public static function cloud_boundary_projection( array $snapshot ): array {
		$source = is_array( $snapshot['site_knowledge_cloud_boundary'] ?? null )
			? $snapshot['site_knowledge_cloud_boundary']
			: $snapshot;

		$ownership        = self::normalize_ownership_map( is_array( $source['ownership'] ?? null ) ? $source['ownership'] : array() );
		$truth_boundaries = self::normalize_truth_boundaries( is_array( $source['truth_boundaries'] ?? null ) ? $source['truth_boundaries'] : array() );

		if ( array() === $ownership && array() === $truth_boundaries ) {
			return array();
		}

		return array(
			'contract_version' => sanitize_text_field( (string) ( $source['contract_version'] ?? 'site_knowledge_status.v1' ) ),
			'ownership'        => $ownership,
			'truth_boundaries' => $truth_boundaries,
			'projection_owner' => 'toolbox_read_only_consumer',
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function cloud_addon_bridge_health_snapshot(): array {
		$snapshot = npcink_cloud_addon_site_knowledge_change_bridge_health();
		if ( ! is_array( $snapshot ) ) {
			$snapshot = array();
		}

		$buffer_count = absint( $snapshot['buffer_count'] ?? ( $snapshot['queue_count'] ?? 0 ) );
		$enabled      = ! empty( $snapshot['enabled'] );
		$configured   = ! empty( $snapshot['configured'] );
		$verified     = ! empty( $snapshot['verified'] );
		$status       = sanitize_key( (string) ( $snapshot['status'] ?? '' ) );
		if ( '' === $status ) {
			$status = ! $configured ? 'not_configured' : ( ! $verified ? 'unverified' : ( $buffer_count > 0 ? 'queued' : 'idle' ) );
		}
		if ( ! $enabled && in_array( $status, array( 'not_configured', 'unverified' ), true ) ) {
			$status = 'disabled';
		}

		$message = (string) ( $snapshot['message'] ?? '' );
		if ( '' === trim( $message ) ) {
			$message = $enabled
				? __( 'Site Knowledge public-change delivery is owned by Cloud Addon. Toolbox is showing bridge health only.', 'npcink-workflow-toolbox' )
				: __( 'Verify Cloud Addon to enable automatic Site Knowledge public-change delivery. Toolbox legacy auto-sync is retired.', 'npcink-workflow-toolbox' );
		}

		$ownership = self::normalize_ownership_map( is_array( $snapshot['ownership'] ?? null ) ? $snapshot['ownership'] : array() );
		if ( array() === $ownership ) {
			$ownership = self::default_cloud_boundary_ownership( 'cloud_addon' );
		}
		$truth_boundaries = self::normalize_truth_boundaries( is_array( $snapshot['truth_boundaries'] ?? null ) ? $snapshot['truth_boundaries'] : array() );
		if ( array() === $truth_boundaries ) {
			$truth_boundaries = self::default_cloud_truth_boundaries();
		}

		$normalized = array_merge(
			$snapshot,
			array(
				'owner'                   => 'cloud_addon',
				'mode'                    => 'site_knowledge_change_bridge',
				'legacy_toolbox_fallback' => false,
				'status'                  => $status,
				'enabled'                 => $enabled,
				'configured'              => $configured,
				'verified'                => $verified,
				'buffer_count'            => $buffer_count,
				'queue_count'             => $buffer_count,
				'next_flush_at'           => sanitize_text_field( (string) ( $snapshot['next_flush_at'] ?? '' ) ),
				'next_queue_run_at'       => sanitize_text_field( (string) ( $snapshot['next_flush_at'] ?? '' ) ),
				'next_reconcile_at'       => sanitize_text_field( (string) ( $snapshot['next_reconcile_at'] ?? '' ) ),
				'last_delivery_at'        => sanitize_text_field( (string) ( $snapshot['last_delivery_at'] ?? ( $snapshot['last_delivered_at'] ?? '' ) ) ),
				'last_success_at'         => sanitize_text_field( (string) ( $snapshot['last_success_at'] ?? '' ) ),
				'last_error_code'         => sanitize_key( (string) ( $snapshot['last_error_code'] ?? '' ) ),
				'server_cron_recommended' => $enabled,
				'write_posture'           => 'suggestion_only',
				'index_lifecycle_owner'   => 'cloud_service',
				'scheduler_truth'         => false,
				'workflow_truth'          => false,
				'wordpress_write_included' => false,
				'ownership'               => $ownership,
				'truth_boundaries'        => $truth_boundaries,
				'compatibility_aliases'    => array( 'auto_sync', 'queue_count', 'next_queue_run_at' ),
				'message'                 => $message,
			)
		);

		$normalized['site_knowledge_cloud_boundary'] = self::cloud_boundary_projection( $normalized );

		return $normalized;
	}

	/**
	 * @return array<string,string>
	 */
	private static function default_cloud_boundary_ownership( string $delivery_bridge_owner ): array {
		return array(
			'source_content_owner'     => 'local_wordpress_host',
			'delivery_bridge_owner'    => $delivery_bridge_owner,
			'index_execution_owner'    => 'cloud_service',
			'index_lifecycle_owner'    => 'cloud_service',
			'freshness_policy_owner'   => 'cloud_service',
			'diagnostics_detail_owner' => 'cloud_service',
			'vector_storage_owner'     => 'cloud_service',
			'embedding_execution_owner' => 'cloud_service',
			'approval_owner'           => 'local_wordpress_host',
			'final_write_owner'        => 'local_wordpress_host',
			'wordpress_write_owner'    => 'local_wordpress_host',
		);
	}

	/**
	 * @return array<string,bool>
	 */
	private static function default_cloud_truth_boundaries(): array {
		return array(
			'cloud_is_index_truth'              => true,
			'cloud_is_freshness_truth'          => true,
			'cloud_is_diagnostics_truth'        => true,
			'cloud_is_wordpress_control_plane'  => false,
			'cloud_creates_wordpress_writes'    => false,
			'cloud_owns_local_approval'         => false,
			'cloud_owns_ability_registry'       => false,
			'cloud_owns_workflow_registry'      => false,
		);
	}

	/**
	 * @param array<string,mixed> $ownership Raw ownership map.
	 * @return array<string,string>
	 */
	private static function normalize_ownership_map( array $ownership ): array {
		$allowed_keys = array(
			'source_content_owner',
			'delivery_bridge_owner',
			'index_execution_owner',
			'index_lifecycle_owner',
			'freshness_policy_owner',
			'diagnostics_detail_owner',
			'vector_storage_owner',
			'embedding_execution_owner',
			'approval_owner',
			'final_write_owner',
			'wordpress_write_owner',
		);
		$normalized = array();

		foreach ( $allowed_keys as $key ) {
			$value = sanitize_key( (string) ( $ownership[ $key ] ?? '' ) );
			if ( '' !== $value ) {
				$normalized[ $key ] = $value;
			}
		}

		return $normalized;
	}

	/**
	 * @param array<string,mixed> $truth_boundaries Raw truth boundary map.
	 * @return array<string,bool>
	 */
	private static function normalize_truth_boundaries( array $truth_boundaries ): array {
		$allowed_keys = array(
			'cloud_is_index_truth',
			'cloud_is_freshness_truth',
			'cloud_is_diagnostics_truth',
			'cloud_is_wordpress_control_plane',
			'cloud_creates_wordpress_writes',
			'cloud_owns_local_approval',
			'cloud_owns_ability_registry',
			'cloud_owns_workflow_registry',
		);
		$normalized = array();

		foreach ( $allowed_keys as $key ) {
			if ( array_key_exists( $key, $truth_boundaries ) ) {
				$normalized[ $key ] = self::normalize_bool( $truth_boundaries[ $key ] );
			}
		}

		return $normalized;
	}

	private static function normalize_bool( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_string( $value ) ) {
			return in_array( strtolower( trim( $value ) ), array( '1', 'true', 'yes', 'on' ), true );
		}

		return (bool) $value;
	}

	private static function clear_legacy_state(): void {
		if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
			wp_clear_scheduled_hook( self::LEGACY_CRON_HOOK );
			wp_clear_scheduled_hook( self::LEGACY_RECONCILE_HOOK );
		}

		if ( function_exists( 'delete_option' ) ) {
			delete_option( self::LEGACY_QUEUE_OPTION );
		}
	}
}
