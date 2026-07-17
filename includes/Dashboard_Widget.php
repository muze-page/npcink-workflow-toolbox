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

	private Hot_Topic_Pool $hot_topic_pool;

	public function __construct( Provider_Client $client ) {
		$this->hot_topic_pool = new Hot_Topic_Pool( $client );
	}

	public function register_hooks(): void {
		add_action( 'wp_dashboard_setup', array( $this, 'register' ) );
		add_action( 'admin_post_npcink_toolbox_refresh_hot_topic_pool', array( $this, 'refresh_hot_topic_pool' ) );
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
			__( '知乎热榜', 'npcink-workflow-toolbox' ),
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

		$pool  = $this->hot_topic_pool->read_cached();
		$items = is_array( $pool['items'] ?? null ) ? $pool['items'] : array();

		echo '<p class="description">' . esc_html__( '今日热榜标题速览。这里不做选题处理，只帮助快速判断外部热点。', 'npcink-workflow-toolbox' ) . '</p>';

		if ( array() === $items ) {
			$message = sanitize_text_field( (string) ( $pool['message'] ?? __( '暂时没有可用的热点选题。', 'npcink-workflow-toolbox' ) ) );
			echo '<p>' . esc_html( $message ) . '</p>';
		} else {
			$this->render_hot_topic_titles( $items );
		}

		$this->render_hot_topic_meta( $pool );
		$this->render_refresh_form();
	}

	public function refresh_hot_topic_pool(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'You are not allowed to refresh Toolbox hot topics.', 'npcink-workflow-toolbox' ),
				'',
				array( 'response' => 403 )
			);
		}

		check_admin_referer( 'npcink_toolbox_refresh_hot_topic_pool' );
		$pool   = $this->hot_topic_pool->refresh();
		$status = sanitize_key( (string) ( $pool['status'] ?? 'unknown' ) );
		$url    = add_query_arg( 'npcink_toolbox_hot_topic_refresh', $status, admin_url( 'index.php' ) );

		wp_safe_redirect( $url );
		exit;
	}

	private function render_hot_topic_titles( array $items ): void {
		echo '<div style="display:grid;gap:8px;margin-top:10px;">';
		foreach ( $items as $index => $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$rank  = absint( $item['rank'] ?? ( $index + 1 ) );
			$title = $this->hot_topic_pool->item_title( $item, $rank );
			$url   = esc_url_raw( (string) ( $item['url'] ?? '' ) );

			echo '<div style="display:grid;grid-template-columns:34px minmax(0,1fr)64px;gap:10px;align-items:center;padding:10px;border:1px solid #dcdcde;border-radius:6px;background:#fff;">';
			echo '<span class="description" style="font-weight:600;">#' . esc_html( (string) $rank ) . '</span>';
			echo '<span style="color:#1d2327;font-weight:600;line-height:1.45;word-break:break-word;">' . esc_html( $title ) . '</span>';
			if ( '' !== $url ) {
				echo '<a class="button button-small" target="_blank" rel="noopener noreferrer" href="' . esc_url( $url ) . '" style="justify-self:end;">' . esc_html__( '查看', 'npcink-workflow-toolbox' ) . '</a>';
			} else {
				echo '<span class="description" style="justify-self:end;">' . esc_html__( '无来源', 'npcink-workflow-toolbox' ) . '</span>';
			}
			echo '</div>';
		}
		echo '</div>';
	}

	private function render_hot_topic_meta( array $pool ): void {
		$parts = array();
		if ( isset( $pool['fetched_at'] ) ) {
			/* translators: %s: hot-topic pool fetch time. */
			$parts[] = sprintf( __( '更新时间：%s', 'npcink-workflow-toolbox' ), sanitize_text_field( (string) $pool['fetched_at'] ) );
		}
		if ( isset( $pool['cache_status'] ) ) {
			$cache_status = 'stale' === sanitize_key( (string) $pool['cache_status'] )
				? __( '本地备份', 'npcink-workflow-toolbox' )
				: __( '本地', 'npcink-workflow-toolbox' );
			/* translators: %s: user-facing cache status. */
			$parts[] = sprintf( __( '缓存：%s', 'npcink-workflow-toolbox' ), $cache_status );
		}
		if ( isset( $pool['result_count'] ) ) {
			/* translators: %d: number of hot-topic candidates. */
			$parts[] = sprintf( __( '数量：%d', 'npcink-workflow-toolbox' ), absint( $pool['result_count'] ) );
		}

		if ( array() !== $parts ) {
			echo '<p class="description" style="margin:8px 0 0;">' . esc_html( implode( ' · ', $parts ) ) . '</p>';
		}
	}

	private function render_refresh_form(): void {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:10px;">';
		echo '<input type="hidden" name="action" value="npcink_toolbox_refresh_hot_topic_pool">';
		wp_nonce_field( 'npcink_toolbox_refresh_hot_topic_pool' );
		submit_button( __( 'Refresh hot topics', 'npcink-workflow-toolbox' ), 'secondary', 'submit', false );
		echo '</form>';
	}
}
