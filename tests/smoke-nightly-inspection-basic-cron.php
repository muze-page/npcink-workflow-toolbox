<?php
/**
 * Validates the Basic WP-Cron dry-run preview path.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox {
	final class Settings {
		/** @var array<string,bool|int|string> */
		private array $settings;

		/**
		 * @param array<string,bool|int|string> $settings Settings.
		 */
		public function __construct( array $settings ) {
			$this->settings = $settings;
		}

		/**
		 * @return array<string,bool|int|string>
		 */
		public function get_nightly_inspection_settings(): array {
			return $this->settings;
		}
	}
}

namespace {
	$root = dirname( __DIR__ );

	if ( ! class_exists( 'WP_Post' ) ) {
		class WP_Post {
			public int $ID;
			public string $post_type;
			public string $post_status;
			public string $post_title;
			public string $post_content;
			public string $post_modified_gmt;

			public function __construct( int $id, string $post_type, string $title, string $content, string $modified_gmt = '2026-06-16 00:00:00' ) {
				$this->ID                = $id;
				$this->post_type         = $post_type;
				$this->post_status       = 'publish';
				$this->post_title        = $title;
				$this->post_content      = $content;
				$this->post_modified_gmt = $modified_gmt;
			}
		}
	}

	$GLOBALS['npcink_basic_cron_actions'] = array();
	$GLOBALS['npcink_basic_cron_events']  = array();
	$GLOBALS['npcink_basic_cron_options'] = array();

	function add_action( string $hook, callable $callback ): void {
		$GLOBALS['npcink_basic_cron_actions'][ $hook ] = $callback;
	}

	function wp_next_scheduled( string $hook ) {
		return $GLOBALS['npcink_basic_cron_events'][ $hook ]['timestamp'] ?? false;
	}

	function wp_schedule_event( int $timestamp, string $recurrence, string $hook ): bool {
		$GLOBALS['npcink_basic_cron_events'][ $hook ] = array(
			'timestamp'  => $timestamp,
			'recurrence' => $recurrence,
		);

		return true;
	}

	function wp_clear_scheduled_hook( string $hook ): void {
		unset( $GLOBALS['npcink_basic_cron_events'][ $hook ] );
		$GLOBALS['npcink_basic_cron_cleared'][] = $hook;
	}

	function get_option( string $key, $default = false ) {
		return array_key_exists( $key, $GLOBALS['npcink_basic_cron_options'] ) ? $GLOBALS['npcink_basic_cron_options'][ $key ] : $default;
	}

	function update_option( string $key, $value, $autoload = null ): bool {
		$GLOBALS['npcink_basic_cron_options'][ $key ] = $value;

		return true;
	}

	function delete_option( string $key ): void {
		unset( $GLOBALS['npcink_basic_cron_options'][ $key ] );
	}

	function is_wp_error( $value ): bool {
		return false;
	}

	function wp_json_encode( $data, int $flags = 0, int $depth = 512 ): string {
		return (string) json_encode( $data, $flags, $depth );
	}

	function wp_timezone(): \DateTimeZone {
		return new \DateTimeZone( 'UTC' );
	}

	function current_time( string $type, bool $gmt = false ): int {
		return 1781575200;
	}

	function home_url( string $path = '/' ): string {
		return 'https://example.test' . $path;
	}

	function wp_parse_url( string $url, int $component = -1 ) {
		return parse_url( $url, $component );
	}

	function get_posts( array $args ): array {
		if ( 'attachment' === ( $args['post_type'] ?? '' ) ) {
			return array(
				new WP_Post( 201, 'attachment', 'Hero image', '', '2026-06-15 00:00:00' ),
			);
		}

		return array(
			new WP_Post( 101, 'post', '2024 WordPress Optimization Guide', '<p>Short intro with <a href="/internal">internal</a>.</p><img src="/missing.jpg" />', '2024-01-10 08:00:00' ),
			new WP_Post( 102, 'page', 'Useful Editorial Maintenance Checklist', '<h2>Overview</h2><p>Structured content with <a href="https://example.test/related">related</a>.</p>', '2026-05-10 08:00:00' ),
		);
	}

	function get_post_meta( int $post_id, string $key, bool $single = false ): string {
		$values = array(
			'102:_yoast_wpseo_metadesc'   => 'A practical maintenance checklist for reviewing content quality, structure, internal links, media evidence, and editorial readiness.',
			'201:_wp_attachment_image_alt' => '',
		);

		return $values[ $post_id . ':' . $key ] ?? '';
	}

	function wp_get_post_terms( int $post_id, string $taxonomy, array $args = array() ): array {
		if ( 102 === $post_id && 'category' === $taxonomy ) {
			return array( 'Editorial' );
		}
		if ( 102 === $post_id && 'post_tag' === $taxonomy ) {
			return array( 'maintenance' );
		}

		return array();
	}

	function get_post_thumbnail_id( int $post_id ): int {
		return 0;
	}

	function get_attached_file( int $attachment_id ): string {
		return '/tmp/hero-image.jpg';
	}

	require_once $root . '/modules/local-automation-runtime/src/NightlyInspection/Rule_Scorer.php';
	require_once $root . '/modules/local-automation-runtime/src/NightlyInspection/Morning_Brief_Builder.php';
	require_once $root . '/modules/local-automation-runtime/src/NightlyInspection/Snapshot_Collector.php';
	require_once $root . '/modules/local-automation-runtime/src/NightlyInspection/Basic_WP_Cron_Dry_Run.php';

	$fail = static function ( string $message ): void {
		fwrite( STDERR, $message . "\n" );
		exit( 1 );
	};

	$hook = \Npcink\LocalAutomationRuntime\NightlyInspection\Basic_WP_Cron_Dry_Run::HOOK;

	$disabled = new \Npcink\LocalAutomationRuntime\NightlyInspection\Basic_WP_Cron_Dry_Run(
		new \Npcink_Toolbox\Settings(
			array(
				'enabled'     => false,
				'time'        => '03:00',
				'post_limit'  => 12,
				'media_limit' => 8,
			)
		)
	);
	$disabled->register_hooks();
	if ( ! isset( $GLOBALS['npcink_basic_cron_actions'][ $hook ] ) || ! isset( $GLOBALS['npcink_basic_cron_actions']['admin_init'] ) ) {
		$fail( 'Basic WP-Cron dry-run should register only the cron hook and admin schedule sync.' );
	}

	$GLOBALS['npcink_basic_cron_events'][ $hook ] = array(
		'timestamp'  => 1781580000,
		'recurrence' => 'daily',
	);
	update_option( \Npcink\LocalAutomationRuntime\NightlyInspection\Basic_WP_Cron_Dry_Run::SCHEDULE_SIGNATURE_OPTION, 'stale', false );
	$disabled->sync_schedule();
	if ( false !== wp_next_scheduled( $hook ) ) {
		$fail( 'Disabled Basic WP-Cron dry-run should clear the scheduled hook.' );
	}
	if ( '' !== (string) get_option( \Npcink\LocalAutomationRuntime\NightlyInspection\Basic_WP_Cron_Dry_Run::SCHEDULE_SIGNATURE_OPTION, '' ) ) {
		$fail( 'Disabled Basic WP-Cron dry-run should delete the schedule signature.' );
	}

	$enabled = new \Npcink\LocalAutomationRuntime\NightlyInspection\Basic_WP_Cron_Dry_Run(
		new \Npcink_Toolbox\Settings(
			array(
				'enabled'     => true,
				'time'        => '03:00',
				'post_limit'  => 2,
				'media_limit' => 1,
			)
		)
	);
	$enabled->sync_schedule();
	if ( false === wp_next_scheduled( $hook ) || 'daily' !== ( $GLOBALS['npcink_basic_cron_events'][ $hook ]['recurrence'] ?? '' ) ) {
		$fail( 'Enabled Basic WP-Cron dry-run should schedule a daily event.' );
	}

	$enabled->run();
	$preview = get_option( \Npcink\LocalAutomationRuntime\NightlyInspection\Basic_WP_Cron_Dry_Run::LATEST_PREVIEW_OPTION, array() );
	if ( ! is_array( $preview ) || 'wp_cron_dry_run_preview' !== ( $preview['mode'] ?? '' ) ) {
		$fail( 'Basic WP-Cron dry-run should store a latest dry-run preview artifact.' );
	}
	if ( 'npcink-local-automation-runtime' !== ( $preview['runtime_owner'] ?? '' ) ) {
		$fail( 'Basic WP-Cron dry-run should keep local automation runtime ownership.' );
	}
	foreach ( array( 'cloud_called', 'core_proposal_created', 'direct_wordpress_content_write', 'action_scheduler_used', 'custom_tables_created', 'lease_store_created', 'retry_processor_created', 'dead_letter_processor_created' ) as $false_flag ) {
		if ( false !== ( $preview['safety'][ $false_flag ] ?? true ) ) {
			$fail( 'Basic WP-Cron dry-run safety flag must be false: ' . $false_flag );
		}
	}
	if ( true !== ( $preview['safety']['latest_preview_option_only'] ?? false ) ) {
		$fail( 'Basic WP-Cron dry-run should persist only the latest-preview option.' );
	}
	if ( false !== ( $preview['core_runtime_execution'] ?? true ) ) {
		$fail( 'Basic WP-Cron dry-run must not execute Core runtime.' );
	}
	if ( 2 !== ( $preview['snapshot_summary']['post_count'] ?? 0 ) || 1 !== ( $preview['snapshot_summary']['media_count'] ?? 0 ) ) {
		$fail( 'Basic WP-Cron dry-run should honor bounded scan limits.' );
	}

	echo "Nightly inspection Basic WP-Cron dry-run: ok\n";
}
