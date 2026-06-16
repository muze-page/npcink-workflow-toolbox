<?php
/**
 * Basic WP-Cron dry-run preview for Nightly Site Inspection.
 *
 * @package Npcink_Local_Automation_Runtime
 */

namespace Npcink\LocalAutomationRuntime\NightlyInspection;

use Npcink_Toolbox\Settings;

final class Basic_WP_Cron_Dry_Run {
	public const HOOK                    = 'npcink_local_automation_runtime_nightly_inspection_dry_run';
	public const LATEST_PREVIEW_OPTION   = 'npcink_local_automation_runtime_nightly_inspection_latest_preview';
	public const SCHEDULE_SIGNATURE_OPTION = 'npcink_local_automation_runtime_nightly_inspection_schedule_signature';

	private Settings $settings;
	private Snapshot_Collector $collector;
	private Morning_Brief_Builder $builder;

	public function __construct( Settings $settings, ?Snapshot_Collector $collector = null, ?Morning_Brief_Builder $builder = null ) {
		$this->settings  = $settings;
		$this->collector = $collector ?: new Snapshot_Collector();
		$this->builder   = $builder ?: new Morning_Brief_Builder();
	}

	public function register_hooks(): void {
		add_action( self::HOOK, array( $this, 'run' ) );
		add_action( 'admin_init', array( $this, 'sync_schedule' ) );
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( self::HOOK );
		delete_option( self::SCHEDULE_SIGNATURE_OPTION );
	}

	/**
	 * Returns the latest bounded cron dry-run preview stored for operator review.
	 *
	 * @return array<string,mixed>
	 */
	public static function latest_preview(): array {
		$value = get_option( self::LATEST_PREVIEW_OPTION, array() );

		return is_array( $value ) ? $value : array();
	}

	public function sync_schedule(): void {
		$config = $this->settings->get_nightly_inspection_settings();
		$next   = wp_next_scheduled( self::HOOK );

		if ( empty( $config['enabled'] ) ) {
			if ( false !== $next ) {
				wp_clear_scheduled_hook( self::HOOK );
			}
			delete_option( self::SCHEDULE_SIGNATURE_OPTION );
			return;
		}

		$signature = $this->schedule_signature( $config );
		if ( false !== $next && $signature === (string) get_option( self::SCHEDULE_SIGNATURE_OPTION, '' ) ) {
			return;
		}

		if ( false !== $next ) {
			wp_clear_scheduled_hook( self::HOOK );
		}

		$scheduled = wp_schedule_event( $this->next_timestamp( (string) $config['time'] ), 'daily', self::HOOK );
		if ( false !== $scheduled && ! is_wp_error( $scheduled ) ) {
			update_option( self::SCHEDULE_SIGNATURE_OPTION, $signature, false );
		}
	}

	public function run(): void {
		$config = $this->settings->get_nightly_inspection_settings();
		if ( empty( $config['enabled'] ) ) {
			return;
		}

		$preview = $this->build_preview(
			(int) $config['post_limit'],
			(int) $config['media_limit'],
			(string) $config['time']
		);

		update_option( self::LATEST_PREVIEW_OPTION, $preview, false );
	}

	/**
	 * Builds a dry-run preview artifact without Cloud, Core proposals, or content writes.
	 *
	 * @return array<string,mixed>
	 */
	public function build_preview( int $post_limit, int $media_limit, string $scheduled_time ): array {
		$snapshot = $this->collector->collect( $post_limit, $media_limit );
		$brief    = $this->builder->build( $snapshot );
		if ( isset( $brief['safety'] ) && is_array( $brief['safety'] ) ) {
			$brief['safety']['cron_registered'] = true;
		}

		return array(
			'contract_version'       => 'nightly_site_inspection_basic_wp_cron_preview.v1',
			'runtime_owner'          => 'npcink-local-automation-runtime',
			'task_profile'           => 'nightly_site_inspection_morning_brief',
			'mode'                   => 'wp_cron_dry_run_preview',
			'trigger'                => 'wp_cron',
			'generated_at'           => $this->current_gmt_time(),
			'scheduled_time'         => $scheduled_time,
			'core_runtime_execution' => false,
			'background_execution'   => true,
			'snapshot_summary'       => array(
				'site_id'     => (string) ( $snapshot['site_id'] ?? '' ),
				'run_id'      => (string) ( $snapshot['run_id'] ?? '' ),
				'post_count'  => count( is_array( $snapshot['posts'] ?? null ) ? $snapshot['posts'] : array() ),
				'media_count' => count( is_array( $snapshot['media'] ?? null ) ? $snapshot['media'] : array() ),
			),
			'preview'                => array(
				'morning_brief' => $brief,
			),
			'safety'                 => array(
				'dry_run'                            => true,
				'latest_preview_option_only'         => true,
				'direct_wordpress_content_write'     => false,
				'cloud_called'                       => false,
				'core_proposal_created'              => false,
				'action_scheduler_used'              => false,
				'custom_tables_created'              => false,
				'lease_store_created'                => false,
				'retry_processor_created'            => false,
				'dead_letter_processor_created'      => false,
				'cloud_scheduler_truth'              => false,
			),
		);
	}

	public function next_timestamp( string $time ): int {
		$parts = explode( ':', $time );
		$hour  = isset( $parts[0] ) ? max( 0, min( 23, (int) $parts[0] ) ) : 3;
		$min   = isset( $parts[1] ) ? max( 0, min( 59, (int) $parts[1] ) ) : 0;
		$zone  = wp_timezone();
		$now   = new \DateTimeImmutable( 'now', $zone );
		$next  = $now->setTime( $hour, $min );

		if ( $next <= $now ) {
			$next = $next->modify( '+1 day' );
		}

		return $next->getTimestamp();
	}

	/**
	 * @param array<string,bool|int|string> $config Settings.
	 */
	private function schedule_signature( array $config ): string {
		return hash(
			'sha256',
			(string) wp_json_encode(
				array(
					'time'        => (string) $config['time'],
					'post_limit'  => (int) $config['post_limit'],
					'media_limit' => (int) $config['media_limit'],
				)
			)
		);
	}

	private function current_gmt_time(): string {
		$timestamp = current_time( 'timestamp', true );
		if ( ! is_numeric( $timestamp ) ) {
			$timestamp = time();
		}

		return gmdate( 'c', (int) $timestamp );
	}
}
