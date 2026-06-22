<?php
/**
 * WordPress dashboard widgets for Toolbox operator signals.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

defined( 'ABSPATH' ) || exit;

final class Dashboard_Widget {
	private const HOT_TOPIC_WIDGET_ID = 'npcink_toolbox_zhihu_hot_topic_pool';
	private const HOT_TOPIC_CACHE_KEY = 'npcink_toolbox_zhihu_hot_topic_pool_v1';
	private const HOT_TOPIC_CACHE_TTL = 1800;

	private Provider_Client $client;

	public function __construct( Provider_Client $client ) {
		$this->client = $client;
	}

	public function register_hooks(): void {
		add_action( 'wp_dashboard_setup', array( $this, 'register' ) );
	}

	public function register(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! function_exists( 'wp_add_dashboard_widget' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			self::HOT_TOPIC_WIDGET_ID,
			__( '知乎热榜选题', 'npcink-toolbox' ),
			array( $this, 'render_hot_topic_pool' ),
			null,
			null,
			'normal',
			'high'
		);
	}

	public function render_hot_topic_pool(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$force_refresh = $this->is_refresh_request();
		if ( $force_refresh ) {
			delete_transient( self::HOT_TOPIC_CACHE_KEY );
		}

		$pool  = $this->hot_topic_pool( $force_refresh );
		$items = is_array( $pool['items'] ?? null ) ? $pool['items'] : array();

		echo '<p class="description">' . esc_html__( '来自服务器缓存的知乎热榜，用来先判断今天写什么；这里只做选题，不生成、不改写、不发布文章。', 'npcink-toolbox' ) . '</p>';

		if ( array() === $items ) {
			$message = sanitize_text_field( (string) ( $pool['message'] ?? __( '暂时没有可用的热点选题。', 'npcink-toolbox' ) ) );
			echo '<p>' . esc_html( $message ) . '</p>';
		} else {
			$this->render_hot_topic_table( $items );
		}

		echo '<form method="post" style="margin-top:12px;">';
		wp_nonce_field( 'npcink_toolbox_refresh_hot_topic_pool', 'npcink_toolbox_hot_topic_nonce' );
		echo '<input type="hidden" name="npcink_toolbox_hot_topic_refresh" value="1" />';
		submit_button( __( '刷新选题池', 'npcink-toolbox' ), 'secondary', 'submit', false );
		$this->render_hot_topic_meta( $pool );
		echo '</form>';
	}

	private function is_refresh_request(): bool {
		$refresh = filter_input( INPUT_POST, 'npcink_toolbox_hot_topic_refresh', FILTER_UNSAFE_RAW );
		if ( '1' !== ( is_scalar( $refresh ) ? (string) $refresh : '' ) ) {
			return false;
		}

		$nonce = filter_input( INPUT_POST, 'npcink_toolbox_hot_topic_nonce', FILTER_UNSAFE_RAW );
		$nonce = is_scalar( $nonce ) ? sanitize_text_field( wp_unslash( (string) $nonce ) ) : '';
		return '' !== $nonce && wp_verify_nonce( $nonce, 'npcink_toolbox_refresh_hot_topic_pool' );
	}

	private function hot_topic_pool( bool $force_refresh ): array {
		$cached = $force_refresh ? false : get_transient( self::HOT_TOPIC_CACHE_KEY );
		if ( is_array( $cached ) ) {
			$cached['cache_status'] = 'hit';
			return $cached;
		}

		$result = $this->client->test_cloud_web_search(
			array(
				'query'          => '知乎热榜',
				'intent'         => 'zhihu_hot_topics',
				'managed_source' => 'zhihu_hot_topics',
				'max_results'    => 5,
				'recency_days'   => 1,
			)
		);

		if ( is_wp_error( $result ) ) {
			return array(
				'status'       => 'failed',
				'cache_status' => 'error',
				'message'      => $result->get_error_message(),
				'items'        => array(),
			);
		}

		$pool = $this->normalize_hot_topic_pool( is_array( $result ) ? $result : array() );
		if ( 'ready' === (string) ( $pool['status'] ?? '' ) ) {
			set_transient( self::HOT_TOPIC_CACHE_KEY, $pool, self::HOT_TOPIC_CACHE_TTL );
		}

		return $pool;
	}

	private function normalize_hot_topic_pool( array $result ): array {
		$pool       = is_array( $result['hot_topic_pool'] ?? null ) ? $result['hot_topic_pool'] : array();
		$items      = is_array( $pool['items'] ?? null ) ? $pool['items'] : array();
		$source_set = 'hot_topic_pool';

		if ( array() === $items ) {
			$atomic = is_array( $result['atomic_outputs']['topic_candidates'] ?? null ) ? $result['atomic_outputs']['topic_candidates'] : array();
			$items  = is_array( $atomic['items'] ?? null ) ? $atomic['items'] : array();
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
			'items'         => array_slice( $items, 0, 5 ),
		);
	}

	private function render_hot_topic_table( array $items ): void {
		echo '<table class="widefat striped" style="margin-top:10px;table-layout:fixed;width:100%;">';
		echo '<thead><tr>';
		echo '<th scope="col" style="width:52px;">' . esc_html__( '排名', 'npcink-toolbox' ) . '</th>';
		echo '<th scope="col" style="width:34%;">' . esc_html__( '热榜标题', 'npcink-toolbox' ) . '</th>';
		echo '<th scope="col">' . esc_html__( '热点信号', 'npcink-toolbox' ) . '</th>';
		echo '<th scope="col" style="width:76px;">' . esc_html__( '操作', 'npcink-toolbox' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $items as $index => $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$rank   = absint( $item['rank'] ?? ( $index + 1 ) );
			$title  = sanitize_text_field( (string) ( $item['title'] ?? '' ) );
			$signal = $this->display_hot_topic_signal( $item );
			$url    = esc_url_raw( (string) ( $item['url'] ?? '' ) );
			if ( '' === $title ) {
				/* translators: %d: hot topic rank number. */
				$title = sprintf( __( '热榜选题 %d', 'npcink-toolbox' ), $rank );
			}

			echo '<tr>';
			echo '<td style="vertical-align:top;">' . esc_html( '#' . (string) $rank ) . '</td>';
			echo '<td style="vertical-align:top;word-break:break-word;"><strong>' . esc_html( $title ) . '</strong></td>';
			echo '<td style="vertical-align:top;word-break:break-word;">' . esc_html( $this->trim_display_text( $signal, 110 ) ) . '</td>';
			echo '<td style="vertical-align:top;">';
			if ( '' !== $url ) {
				echo '<a class="button button-small" target="_blank" rel="noopener noreferrer" href="' . esc_url( $url ) . '">' . esc_html__( '打开', 'npcink-toolbox' ) . '</a>';
			} else {
				echo '<span class="description">' . esc_html__( '无来源', 'npcink-toolbox' ) . '</span>';
			}
			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	private function display_hot_topic_signal( array $item ): string {
		foreach ( array( 'signal', 'snippet', 'selection_reason' ) as $key ) {
			$value = sanitize_textarea_field( (string) ( $item[ $key ] ?? '' ) );
			if ( '' === $value || $this->is_machine_or_url_value( $value ) ) {
				continue;
			}
			return $value;
		}

		return __( '热榜趋势信号，需人工判断是否适合本站受众。', 'npcink-toolbox' );
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

	private function render_hot_topic_meta( array $pool ): void {
		$parts = array();
		if ( isset( $pool['fetched_at'] ) ) {
			/* translators: %s: hot-topic pool fetch time. */
			$parts[] = sprintf( __( '更新时间：%s', 'npcink-toolbox' ), sanitize_text_field( (string) $pool['fetched_at'] ) );
		}
		if ( isset( $pool['cache_status'] ) ) {
			/* translators: %s: cache status. */
			$parts[] = sprintf( __( '缓存：%s', 'npcink-toolbox' ), sanitize_key( (string) $pool['cache_status'] ) );
		}
		if ( isset( $pool['result_count'] ) ) {
			/* translators: %d: number of hot-topic candidates. */
			$parts[] = sprintf( __( '数量：%d', 'npcink-toolbox' ), absint( $pool['result_count'] ) );
		}

		if ( array() !== $parts ) {
			echo '<p class="description" style="margin:8px 0 0;">' . esc_html( implode( ' · ', $parts ) ) . '</p>';
		}
	}

	private function trim_display_text( string $value, int $limit ): string {
		if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
			return mb_strlen( $value ) > $limit ? mb_substr( $value, 0, $limit - 1 ) . '…' : $value;
		}

		return strlen( $value ) > $limit ? substr( $value, 0, $limit - 1 ) . '...' : $value;
	}
}
