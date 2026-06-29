<?php
/**
 * Cloud-cached Zhihu hot topic pool reader.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

defined( 'ABSPATH' ) || exit;

final class Hot_Topic_Pool {
	private const CACHE_KEY = 'npcink_toolbox_zhihu_hot_topic_pool_v2';
	private const BACKUP_OPTION = 'npcink_toolbox_zhihu_hot_topic_pool_backup_v1';
	private const CACHE_TTL = 1800;
	private const MAX_ITEMS = 20;

	private Provider_Client $client;

	public function __construct( Provider_Client $client ) {
		$this->client = $client;
	}

	public function get( bool $force_refresh = false ): array {
		$cached = $force_refresh ? false : get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			$cached['cache_status'] = 'hit';
			return $cached;
		}

		$result = $this->client->test_cloud_web_search(
			array(
				'query'          => '知乎热榜',
				'intent'         => 'zhihu_hot_topics',
				'managed_source' => 'zhihu_hot_topics',
				'max_results'    => self::MAX_ITEMS,
				'recency_days'   => 1,
			)
		);

		if ( is_wp_error( $result ) ) {
			$backup = get_option( self::BACKUP_OPTION, array() );
			if ( is_array( $backup ) && ! empty( $backup['items'] ) && is_array( $backup['items'] ) ) {
				$backup['status']       = 'stale';
				$backup['cache_status'] = 'stale';
				$backup['message']      = $result->get_error_message();
				return $backup;
			}

			return array(
				'status'       => 'failed',
				'cache_status' => 'error',
				'message'      => $result->get_error_message(),
				'items'        => array(),
			);
		}

		$pool = $this->normalize( is_array( $result ) ? $result : array() );
		if ( 'ready' === (string) ( $pool['status'] ?? '' ) ) {
			set_transient( self::CACHE_KEY, $pool, self::CACHE_TTL );
			update_option( self::BACKUP_OPTION, $pool, false );
		}

		return $pool;
	}

	public function refresh(): array {
		delete_transient( self::CACHE_KEY );
		return $this->get( true );
	}

	public function display_signal( array $item ): string {
		foreach ( array( 'signal', 'snippet', 'selection_reason' ) as $key ) {
			$value = sanitize_textarea_field( (string) ( $item[ $key ] ?? '' ) );
			if ( '' === $value || $this->is_machine_or_url_value( $value ) ) {
				continue;
			}
			return $value;
		}

		return __( '热榜趋势信号，需人工判断是否适合本站受众。', 'npcink-workflow-toolbox' );
	}

	public function item_title( array $item, int $fallback_rank ): string {
		$title = sanitize_text_field( (string) ( $item['title'] ?? '' ) );
		if ( '' !== $title ) {
			return $title;
		}

		return sprintf(
			/* translators: %d: hot topic rank number. */
			__( '热榜选题 %d', 'npcink-workflow-toolbox' ),
			$fallback_rank
		);
	}

	public function trim_display_text( string $value, int $limit ): string {
		if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
			return mb_strlen( $value ) > $limit ? mb_substr( $value, 0, $limit - 1 ) . '…' : $value;
		}

		return strlen( $value ) > $limit ? substr( $value, 0, $limit - 1 ) . '...' : $value;
	}

	public static function cache_ttl_seconds(): int {
		return self::CACHE_TTL;
	}

	private function normalize( array $result ): array {
		$pool       = is_array( $result['hot_topic_pool'] ?? null ) ? $result['hot_topic_pool'] : array();
		$items      = is_array( $pool['items'] ?? null ) ? $pool['items'] : array();
		$source_set = 'hot_topic_pool';

		if ( array() === $items ) {
			$atomic     = is_array( $result['atomic_outputs']['topic_candidates'] ?? null ) ? $result['atomic_outputs']['topic_candidates'] : array();
			$items      = is_array( $atomic['items'] ?? null ) ? $atomic['items'] : array();
			$source_set = 'topic_candidate.v1';
		}

		if ( array() === $items ) {
			$items      = is_array( $result['results'] ?? null ) ? $result['results'] : array();
			$source_set = 'results';
		}

		return array(
			'status'        => array() === $items ? 'empty' : 'ready',
			'cache_status'  => 'miss',
			'fetched_at'    => current_time( 'mysql' ),
			'source_set'    => $source_set,
			'result_count'  => count( $items ),
			'provider_mode' => sanitize_key( (string) ( $result['provider_mode'] ?? '' ) ),
			'run_id'        => sanitize_text_field( (string) ( $result['run_id'] ?? '' ) ),
			'items'         => array_slice( $items, 0, self::MAX_ITEMS ),
		);
	}

	private function is_machine_or_url_value( string $value ): bool {
		$normalized = strtolower( trim( $value ) );
		if ( '' === $normalized ) {
			return true;
		}
		if ( preg_match( '/^https?:\\/\\//', $normalized ) ) {
			return true;
		}

		return in_array(
			$normalized,
			array(
				'zhihu_hot_topic_candidate',
				'zhihu_hot_topic_pool',
				'topic_candidate.v1',
				'manual_topic_selection_then_focused_research',
			),
			true
		);
	}
}
