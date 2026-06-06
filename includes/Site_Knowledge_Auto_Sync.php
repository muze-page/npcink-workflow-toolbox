<?php
/**
 * Lightweight WordPress content-change bridge for Cloud Site Knowledge.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

use WP_Comment;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Site_Knowledge_Auto_Sync {
	private const QUEUE_OPTION     = 'npcink_toolbox_site_knowledge_auto_sync_queue';
	private const CRON_HOOK        = 'npcink_toolbox_process_site_knowledge_auto_sync';
	private const RECONCILE_HOOK   = 'npcink_toolbox_reconcile_site_knowledge_auto_sync';
	private const DEFAULT_POST_TYPES = array( 'post', 'page' );
	private const BATCH_SIZE       = 25;
	private const DEBOUNCE_SECONDS = 180;
	private const RETRY_SECONDS    = 300;
	private const MAX_RETRY_ATTEMPTS = 3;
	private const MAX_QUEUE_POST_IDS = 500;
	private const RECONCILE_POSTS  = 50;

	private Provider_Client $client;

	public function __construct( Provider_Client $client ) {
		$this->client = $client;
	}

	public function register_hooks(): void {
		if ( ! self::cloud_runtime_available() ) {
			return;
		}

		add_action( 'transition_post_status', array( $this, 'handle_post_status_transition' ), 10, 3 );
		add_action( 'save_post', array( $this, 'handle_post_saved' ), 10, 3 );
		add_action( 'trashed_post', array( $this, 'handle_post_removed' ) );
		add_action( 'before_delete_post', array( $this, 'handle_post_deleted' ), 10, 2 );

		add_action( 'transition_comment_status', array( $this, 'handle_comment_status_transition' ), 10, 3 );
		add_action( 'comment_post', array( $this, 'handle_comment_created' ), 10, 3 );
		add_action( 'edit_comment', array( $this, 'handle_comment_edited' ), 10, 2 );
		add_action( 'trashed_comment', array( $this, 'handle_comment_removed' ), 10, 2 );
		add_action( 'deleted_comment', array( $this, 'handle_comment_removed' ), 10, 2 );

		add_action( self::CRON_HOOK, array( $this, 'process_queue' ) );
		add_action( self::RECONCILE_HOOK, array( $this, 'queue_recent_public_content' ) );
		$this->ensure_reconcile_scheduled();
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
		wp_clear_scheduled_hook( self::RECONCILE_HOOK );
	}

	public static function health_snapshot(): array {
		if ( ! self::cloud_runtime_available() ) {
			return array(
				'status'                  => 'disabled',
				'queue_count'             => 0,
				'attempts'                => 0,
				'max_attempts'            => self::MAX_RETRY_ATTEMPTS,
				'batch_size'              => self::BATCH_SIZE,
				'debounce_seconds'        => self::DEBOUNCE_SECONDS,
				'max_queue_post_ids'      => self::MAX_QUEUE_POST_IDS,
				'wp_cron_disabled'        => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
				'server_cron_recommended' => false,
				'next_queue_run_at'       => '',
				'next_reconcile_at'       => '',
				'queued_updated_at'       => '',
				'cron_command'            => '',
				'wp_cli_command'          => '',
				'message'                 => __( 'Site Knowledge auto-sync is disabled until Cloud Addon transport is available.', 'npcink-toolbox' ),
			);
		}

		$value    = get_option( self::QUEUE_OPTION, array() );
		$post_ids = is_array( $value ) ? ( $value['post_ids'] ?? $value ) : array();
		$post_ids = array_values(
			array_unique(
				array_filter(
					array_map( 'absint', is_array( $post_ids ) ? $post_ids : array() ),
					static fn( int $post_id ): bool => 0 < $post_id
				)
			)
		);

		$now            = time();
		$next_queue     = wp_next_scheduled( self::CRON_HOOK );
		$next_reconcile = wp_next_scheduled( self::RECONCILE_HOOK );
		$queue_count    = count( $post_ids );
		$is_overdue     = $queue_count > 0 && $next_queue && $next_queue < ( $now - self::RETRY_SECONDS );
		$wp_cron_url    = home_url( '/wp-cron.php?doing_wp_cron' );
		$wp_cli_command = '*/5 * * * * cd /path/to/wordpress && wp cron event run --due-now --quiet';
		$curl_command   = '*/5 * * * * curl -fsS --max-time 20 ' . esc_url_raw( $wp_cron_url ) . ' >/dev/null 2>&1';

		return array(
			'status'                  => $is_overdue ? 'delayed' : ( $queue_count > 0 ? 'queued' : 'idle' ),
			'queue_count'             => $queue_count,
			'attempts'                => is_array( $value ) ? absint( $value['attempts'] ?? 0 ) : 0,
			'max_attempts'            => self::MAX_RETRY_ATTEMPTS,
			'batch_size'              => self::BATCH_SIZE,
			'debounce_seconds'        => self::DEBOUNCE_SECONDS,
			'max_queue_post_ids'      => self::MAX_QUEUE_POST_IDS,
			'wp_cron_disabled'        => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
			'server_cron_recommended' => true,
			'next_queue_run_at'       => $next_queue ? gmdate( 'c', (int) $next_queue ) : '',
			'next_reconcile_at'       => $next_reconcile ? gmdate( 'c', (int) $next_reconcile ) : '',
			'queued_updated_at'       => is_array( $value ) && ! empty( $value['updated_at'] ) ? gmdate( 'c', absint( $value['updated_at'] ) ) : '',
			'cron_command'            => $curl_command,
			'wp_cli_command'          => $wp_cli_command,
			'message'                 => $is_overdue
				? __( 'Site Knowledge auto-sync is queued but WP-Cron appears delayed. Configure a server cron for low-traffic sites.', 'npcink-toolbox' )
				: __( 'Site Knowledge auto-sync is debounced and processed by WP-Cron. Use a server cron in production for low-traffic sites.', 'npcink-toolbox' ),
		);
	}

	/**
	 * Queue a refresh when posts/pages enter or leave public status.
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param WP_Post $post       Post object.
	 */
	public function handle_post_status_transition( string $new_status, string $old_status, $post ): void {
		if ( ! $post instanceof WP_Post || ! $this->is_site_knowledge_post_type( (string) $post->post_type ) ) {
			return;
		}

		if ( 'publish' === $new_status || 'publish' === $old_status ) {
			$this->enqueue_post_ids( array( (int) $post->ID ) );
		}
	}

	public function handle_post_saved( int $post_id, $post, bool $update ): void {
		unset( $update );

		if ( ! $post instanceof WP_Post || 'publish' !== (string) $post->post_status ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( $this->is_site_knowledge_post_type( (string) $post->post_type ) ) {
			$this->enqueue_post_ids( array( $post_id ) );
		}
	}

	public function handle_post_removed( int $post_id ): void {
		$post = get_post( $post_id );
		if ( $post instanceof WP_Post && $this->is_site_knowledge_post_type( (string) $post->post_type ) ) {
			$this->enqueue_post_ids( array( $post_id ) );
		}
	}

	public function handle_post_deleted( int $post_id, $post = null ): void {
		if ( ! $post instanceof WP_Post ) {
			$post = get_post( $post_id );
		}

		if ( $post instanceof WP_Post && $this->is_site_knowledge_post_type( (string) $post->post_type ) ) {
			$this->enqueue_post_ids( array( $post_id ) );
		}
	}

	/**
	 * Queue a parent-post refresh when approved comments stop being public.
	 *
	 * @param string     $new_status New comment status.
	 * @param string     $old_status Old comment status.
	 * @param WP_Comment $comment    Comment object.
	 */
	public function handle_comment_status_transition( string $new_status, string $old_status, $comment ): void {
		if ( ! $comment instanceof WP_Comment ) {
			return;
		}

		if ( $this->is_public_comment_status( $new_status ) || $this->is_public_comment_status( $old_status ) ) {
			$this->enqueue_comment_parent( $comment );
		}
	}

	public function handle_comment_created( int $comment_id, $comment_approved = null, array $commentdata = array() ): void {
		unset( $commentdata );

		if ( ! $this->is_public_comment_status( $comment_approved ) ) {
			return;
		}

		$comment = get_comment( $comment_id );
		if ( $comment instanceof WP_Comment ) {
			$this->enqueue_comment_parent( $comment );
		}
	}

	public function handle_comment_edited( int $comment_id, array $data = array() ): void {
		unset( $data );

		$comment = get_comment( $comment_id );
		if ( $comment instanceof WP_Comment && $this->is_public_comment_status( $comment->comment_approved ) ) {
			$this->enqueue_comment_parent( $comment );
		}
	}

	public function handle_comment_removed( int $comment_id, $comment = null ): void {
		if ( ! $comment instanceof WP_Comment ) {
			$comment = get_comment( $comment_id );
		}

		if ( $comment instanceof WP_Comment ) {
			$this->enqueue_comment_parent( $comment );
		}
	}

	public function process_queue(): void {
		$post_ids = $this->queued_post_ids();
		if ( array() === $post_ids ) {
			delete_option( self::QUEUE_OPTION );
			return;
		}

		$batch  = array_slice( $post_ids, 0, self::BATCH_SIZE );
		$result = $this->client->request_site_knowledge_sync(
			array(
				'sync_mode' => 'refresh',
				'post_ids'  => $batch,
				'max_posts' => count( $batch ),
			)
		);

		if ( $this->is_active_cloud_run_response( $result ) ) {
			$this->schedule_queue( self::RETRY_SECONDS );
			return;
		}

		if ( is_wp_error( $result ) ) {
			$this->retry_or_drop_queue( $post_ids );
			return;
		}

		$remaining = array_values( array_diff( $post_ids, $batch ) );
		if ( array() === $remaining ) {
			delete_option( self::QUEUE_OPTION );
			return;
		}

		$this->store_queue( $remaining );
		$this->schedule_queue( self::DEBOUNCE_SECONDS );
	}

	public function queue_recent_public_content(): void {
		if ( ! function_exists( 'get_posts' ) ) {
			return;
		}

		$posts = get_posts(
			array(
				'post_type'      => $this->site_knowledge_post_types(),
				'post_status'    => 'publish',
				'posts_per_page' => self::RECONCILE_POSTS,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'fields'         => 'ids',
			)
		);

		if ( is_array( $posts ) ) {
			$this->enqueue_post_ids( $posts );
		}
	}

	private function enqueue_comment_parent( WP_Comment $comment ): void {
		$post_id = absint( $comment->comment_post_ID ?? 0 );
		if ( 0 >= $post_id ) {
			return;
		}

		$post_type = get_post_type( $post_id );
		if ( is_string( $post_type ) && $this->is_site_knowledge_post_type( $post_type ) ) {
			$this->enqueue_post_ids( array( $post_id ) );
		}
	}

	private function enqueue_post_ids( array $post_ids ): void {
		if ( ! self::cloud_runtime_available() ) {
			return;
		}

		if ( ! apply_filters( 'npcink_toolbox_site_knowledge_auto_sync_enabled', true, $post_ids ) ) {
			return;
		}

		$next_ids = array_values(
			array_slice(
				array_unique(
					array_filter(
						array_map( 'absint', array_merge( $this->queued_post_ids(), $post_ids ) ),
						static fn( int $post_id ): bool => 0 < $post_id
					)
				),
				0,
				self::MAX_QUEUE_POST_IDS
			)
		);

		if ( array() === $next_ids ) {
			return;
		}

		$this->store_queue( $next_ids, 0 );
		$this->schedule_queue( self::DEBOUNCE_SECONDS );
	}

	private function site_knowledge_post_types(): array {
		$post_types = apply_filters( 'npcink_toolbox_site_knowledge_post_types', self::DEFAULT_POST_TYPES );
		if ( ! is_array( $post_types ) ) {
			$post_types = self::DEFAULT_POST_TYPES;
		}

		$post_types = array_values(
			array_unique(
				array_filter(
					array_map( 'sanitize_key', $post_types ),
					static fn( string $post_type ): bool => '' !== $post_type && 'attachment' !== $post_type
				)
			)
		);

		return array() === $post_types ? self::DEFAULT_POST_TYPES : $post_types;
	}

	private function queued_post_ids(): array {
		$value = get_option( self::QUEUE_OPTION, array() );
		$ids   = is_array( $value ) ? ( $value['post_ids'] ?? $value ) : array();

		return array_values(
			array_unique(
				array_filter(
					array_map( 'absint', is_array( $ids ) ? $ids : array() ),
					static fn( int $post_id ): bool => 0 < $post_id
				)
			)
		);
	}

	private function queued_attempts(): int {
		$value = get_option( self::QUEUE_OPTION, array() );
		return is_array( $value ) ? absint( $value['attempts'] ?? 0 ) : 0;
	}

	private function store_queue( array $post_ids, int $attempts = 0 ): void {
		$post_ids = array_slice(
			array_values( array_unique( array_map( 'absint', $post_ids ) ) ),
			0,
			self::MAX_QUEUE_POST_IDS
		);

		update_option(
			self::QUEUE_OPTION,
			array(
				'post_ids'   => $post_ids,
				'attempts'   => max( 0, $attempts ),
				'updated_at' => time(),
			),
			false
		);
	}

	private function retry_or_drop_queue( array $post_ids ): void {
		$attempts = $this->queued_attempts() + 1;
		if ( self::MAX_RETRY_ATTEMPTS <= $attempts ) {
			delete_option( self::QUEUE_OPTION );
			return;
		}

		$this->store_queue( $post_ids, $attempts );
		$this->schedule_queue( self::RETRY_SECONDS );
	}

	private function schedule_queue( int $delay_seconds ): void {
		if ( wp_next_scheduled( self::CRON_HOOK ) ) {
			return;
		}

		wp_schedule_single_event( time() + max( 1, $delay_seconds ), self::CRON_HOOK );
	}

	private function ensure_reconcile_scheduled(): void {
		if ( wp_next_scheduled( self::RECONCILE_HOOK ) ) {
			return;
		}

		wp_schedule_event( time() + 3600, 'daily', self::RECONCILE_HOOK );
	}

	private function is_active_cloud_run_response( $result ): bool {
		if ( ! is_array( $result ) ) {
			return false;
		}

		return 'syncing' === sanitize_key( (string) ( $result['status'] ?? '' ) )
			&& ! empty( $result['progress'] );
	}

	private function is_site_knowledge_post_type( string $post_type ): bool {
		return in_array( $post_type, $this->site_knowledge_post_types(), true );
	}

	private function is_public_comment_status( $status ): bool {
		return in_array( (string) $status, array( '1', 'approve', 'approved' ), true );
	}

	private static function cloud_runtime_available(): bool {
		if ( has_filter( 'npcink_toolbox_site_knowledge_cloud_request' ) ) {
			return true;
		}

		$client = self::cloud_runtime_client();
		return is_object( $client ) && method_exists( $client, 'execute_runtime' );
	}

	private static function cloud_runtime_client() {
		if ( function_exists( 'npcink_cloud_addon_runtime_client' ) ) {
			return npcink_cloud_addon_runtime_client();
		}

		if ( function_exists( 'magick_ai_cloud_addon_runtime_client' ) ) {
			return magick_ai_cloud_addon_runtime_client();
		}

		return null;
	}
}
