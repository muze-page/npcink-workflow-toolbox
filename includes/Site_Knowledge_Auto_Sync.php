<?php
/**
 * Cloud Addon Site Knowledge change bridge status projection.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

defined( 'ABSPATH' ) || exit;

final class Site_Knowledge_Auto_Sync {
	private const QUEUE_OPTION   = 'npcink_toolbox_site_knowledge_auto_sync_queue';
	private const CRON_HOOK      = 'npcink_toolbox_process_site_knowledge_auto_sync';
	private const RECONCILE_HOOK = 'npcink_toolbox_reconcile_site_knowledge_auto_sync';

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

		return array(
			'owner'                   => 'cloud_addon_required',
			'mode'                    => 'site_knowledge_change_bridge_required',
			'legacy_toolbox_fallback' => false,
			'status'                  => 'disabled',
			'enabled'                 => false,
			'configured'              => false,
			'verified'                => false,
			'queue_count'             => 0,
			'buffer_count'            => 0,
			'next_queue_run_at'       => '',
			'next_flush_at'           => '',
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
			'message'                 => __( 'Install and verify Cloud Addon to enable automatic Site Knowledge public-change delivery. Manual Site Knowledge sync remains available from Toolbox.', 'npcink-workflow-toolbox' ),
		);
	}

	public static function cloud_addon_bridge_available(): bool {
		return function_exists( 'npcink_cloud_addon_site_knowledge_change_bridge_health' );
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

		return array_merge(
			$snapshot,
			array(
				'owner'                   => 'cloud_addon',
				'mode'                    => 'site_knowledge_change_bridge',
				'legacy_toolbox_fallback' => false,
				'status'                  => $status,
				'enabled'                 => $enabled,
				'configured'              => $configured,
				'verified'                => $verified,
				'queue_count'             => $buffer_count,
				'buffer_count'            => $buffer_count,
				'next_queue_run_at'       => sanitize_text_field( (string) ( $snapshot['next_flush_at'] ?? '' ) ),
				'next_flush_at'           => sanitize_text_field( (string) ( $snapshot['next_flush_at'] ?? '' ) ),
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
				'message'                 => $message,
			)
		);
	}

	private static function clear_legacy_state(): void {
		if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
			wp_clear_scheduled_hook( self::CRON_HOOK );
			wp_clear_scheduled_hook( self::RECONCILE_HOOK );
		}

		if ( function_exists( 'delete_option' ) ) {
			delete_option( self::QUEUE_OPTION );
		}
	}
}
