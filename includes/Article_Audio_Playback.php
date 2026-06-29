<?php
/**
 * Frontend playback surface for adopted article audio.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

defined( 'ABSPATH' ) || exit;

final class Article_Audio_Playback {
	public const META_URL              = '_npcink_toolbox_article_audio_url';
	public const META_ATTACHMENT_ID    = '_npcink_toolbox_article_audio_attachment_id';
	public const META_TITLE            = '_npcink_toolbox_article_audio_title';
	public const META_KIND             = '_npcink_toolbox_article_audio_kind';
	public const META_DURATION_SECONDS = '_npcink_toolbox_article_audio_duration_seconds';
	public const META_MIME_TYPE        = '_npcink_toolbox_article_audio_mime_type';
	public const META_SOURCE_HASH      = '_npcink_toolbox_article_audio_source_content_hash';
	public const META_SOURCE_WORD_COUNT = '_npcink_toolbox_article_audio_source_word_count';
	public const META_SOURCE_GENERATED_AT = '_npcink_toolbox_article_audio_source_generated_at';

	public function register_hooks(): void {
		add_action( 'init', array( $this, 'register_meta' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'the_content', array( $this, 'prepend_player' ), 12 );
	}

	public function register_meta(): void {
		foreach ( $this->meta_definitions() as $key => $definition ) {
			register_post_meta(
				'post',
				$key,
				array_merge(
					array(
						'single'       => true,
						'show_in_rest' => false,
					),
					$definition
				)
			);
		}
	}

	public function enqueue_assets(): void {
		if ( is_admin() || is_feed() || ! is_singular( 'post' ) ) {
			return;
		}

		$post_id = get_queried_object_id();
		if ( $post_id <= 0 || null === $this->audio_for_post( $post_id ) ) {
			return;
		}

		wp_enqueue_style(
			'npcink-toolbox-article-audio',
			NPCINK_TOOLBOX_URL . 'assets/article-audio-playback.css',
			array(),
			$this->asset_version( 'assets/article-audio-playback.css' )
		);
	}

	public function prepend_player( string $content ): string {
		if ( is_admin() || is_feed() || ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$post_id = get_the_ID();
		if ( ! is_int( $post_id ) || $post_id <= 0 ) {
			return $content;
		}

		$audio = $this->audio_for_post( $post_id );
		if ( null === $audio ) {
			return $content;
		}

		return $this->render_player( $audio ) . $content;
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	private function meta_definitions(): array {
		return array(
			self::META_URL              => array(
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
				'auth_callback'     => static fn(): bool => current_user_can( 'edit_posts' ),
			),
			self::META_ATTACHMENT_ID    => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'auth_callback'     => static fn(): bool => current_user_can( 'edit_posts' ),
			),
			self::META_TITLE            => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => static fn(): bool => current_user_can( 'edit_posts' ),
			),
			self::META_KIND             => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_key',
				'auth_callback'     => static fn(): bool => current_user_can( 'edit_posts' ),
			),
			self::META_DURATION_SECONDS => array(
				'type'              => 'number',
				'sanitize_callback' => static fn( $value ): float => max( 0.0, (float) $value ),
				'auth_callback'     => static fn(): bool => current_user_can( 'edit_posts' ),
			),
			self::META_MIME_TYPE        => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_mime_type',
				'auth_callback'     => static fn(): bool => current_user_can( 'edit_posts' ),
			),
			self::META_SOURCE_HASH      => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => static fn(): bool => current_user_can( 'edit_posts' ),
			),
			self::META_SOURCE_WORD_COUNT => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'auth_callback'     => static fn(): bool => current_user_can( 'edit_posts' ),
			),
			self::META_SOURCE_GENERATED_AT => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => static fn(): bool => current_user_can( 'edit_posts' ),
			),
		);
	}

	/**
	 * @return array<string,mixed>|null
	 */
	private function audio_for_post( int $post_id ): ?array {
		$attachment_id = absint( get_post_meta( $post_id, self::META_ATTACHMENT_ID, true ) );
		$url           = esc_url_raw( (string) get_post_meta( $post_id, self::META_URL, true ) );
		$mime_type     = sanitize_mime_type( (string) get_post_meta( $post_id, self::META_MIME_TYPE, true ) );

		if ( '' === $url && $attachment_id > 0 ) {
			$attachment_url = wp_get_attachment_url( $attachment_id );
			$url            = is_string( $attachment_url ) ? esc_url_raw( $attachment_url ) : '';
		}

		if ( '' === $mime_type && $attachment_id > 0 ) {
			$attachment_mime = get_post_mime_type( $attachment_id );
			$mime_type       = is_string( $attachment_mime ) ? sanitize_mime_type( $attachment_mime ) : '';
		}

		$audio = array(
			'url'              => $url,
			'attachment_id'    => $attachment_id,
			'title'            => sanitize_text_field( (string) get_post_meta( $post_id, self::META_TITLE, true ) ),
			'kind'             => sanitize_key( (string) get_post_meta( $post_id, self::META_KIND, true ) ),
			'duration_seconds' => max( 0.0, (float) get_post_meta( $post_id, self::META_DURATION_SECONDS, true ) ),
			'mime_type'        => $mime_type,
			'source_content_hash' => sanitize_text_field( (string) get_post_meta( $post_id, self::META_SOURCE_HASH, true ) ),
			'source_word_count' => absint( get_post_meta( $post_id, self::META_SOURCE_WORD_COUNT, true ) ),
			'source_generated_at' => sanitize_text_field( (string) get_post_meta( $post_id, self::META_SOURCE_GENERATED_AT, true ) ),
			'write_posture'    => 'adopted_wordpress_meta_read_only',
		);

		/**
		 * Allows a host/Core adoption path to project already approved audio
		 * without making Toolbox own generation, proposal, or write authority.
		 *
		 * @param array<string,mixed> $audio   Audio playback metadata.
		 * @param int                 $post_id Current post ID.
		 */
		$audio = apply_filters( 'npcink_toolbox_article_audio_playback_meta', $audio, $post_id );
		if ( ! is_array( $audio ) ) {
			return null;
		}

		$audio['url'] = esc_url_raw( (string) ( $audio['url'] ?? '' ) );
		if ( '' === $audio['url'] ) {
			return null;
		}

		$audio['title']            = sanitize_text_field( (string) ( $audio['title'] ?? '' ) );
		$audio['kind']             = sanitize_key( (string) ( $audio['kind'] ?? '' ) );
		$audio['duration_seconds'] = max( 0.0, (float) ( $audio['duration_seconds'] ?? 0 ) );
		$audio['mime_type']        = sanitize_mime_type( (string) ( $audio['mime_type'] ?? '' ) );
		$audio['source_content_hash'] = sanitize_text_field( (string) ( $audio['source_content_hash'] ?? '' ) );
		$audio['source_word_count'] = absint( $audio['source_word_count'] ?? 0 );
		$audio['source_generated_at'] = sanitize_text_field( (string) ( $audio['source_generated_at'] ?? '' ) );
		$audio['freshness']        = $this->freshness_for_post( $post_id, $audio );

		return $audio;
	}

	/**
	 * @param array<string,mixed> $audio Audio playback metadata.
	 */
	private function render_player( array $audio ): string {
		$label    = $this->label_for_kind( (string) $audio['kind'] );
		$title    = '' !== $audio['title'] ? (string) $audio['title'] : $label;
		$duration = $this->format_duration( (float) $audio['duration_seconds'] );
		$freshness = is_array( $audio['freshness'] ?? null ) ? $audio['freshness'] : array();
		$freshness_status = sanitize_key( (string) ( $freshness['status'] ?? 'unknown' ) );
		$show_freshness_notice = current_user_can( 'edit_post', get_the_ID() ) && in_array( $freshness_status, array( 'minor_drift', 'review_recommended', 'stale' ), true );

		ob_start();
		?>
		<section class="npcink-toolbox-article-audio" aria-label="<?php echo esc_attr( $label ); ?>" data-npcink-audio-freshness="<?php echo esc_attr( $freshness_status ); ?>">
			<div class="npcink-toolbox-article-audio__summary">
				<span class="npcink-toolbox-article-audio__eyebrow"><?php echo esc_html__( 'Audio', 'npcink-workflow-toolbox' ); ?></span>
				<strong class="npcink-toolbox-article-audio__title"><?php echo esc_html( $title ); ?></strong>
				<?php if ( '' !== $duration ) : ?>
					<span class="npcink-toolbox-article-audio__duration"><?php echo esc_html( $duration ); ?></span>
				<?php endif; ?>
			</div>
			<audio class="npcink-toolbox-article-audio__player" controls preload="none">
				<source src="<?php echo esc_url( (string) $audio['url'] ); ?>"<?php echo '' !== $audio['mime_type'] ? ' type="' . esc_attr( (string) $audio['mime_type'] ) . '"' : ''; ?> />
				<?php esc_html_e( 'Your browser does not support audio playback.', 'npcink-workflow-toolbox' ); ?>
			</audio>
			<?php if ( $show_freshness_notice ) : ?>
				<p class="npcink-toolbox-article-audio__freshness">
					<?php echo esc_html( $this->freshness_label( $freshness_status ) ); ?>
				</p>
			<?php endif; ?>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * @param array<string,mixed> $audio Audio playback metadata.
	 *
	 * @return array<string,mixed>
	 */
	private function freshness_for_post( int $post_id, array $audio ): array {
		$source_hash = sanitize_text_field( (string) ( $audio['source_content_hash'] ?? '' ) );
		$source_word_count = absint( $audio['source_word_count'] ?? 0 );
		if ( '' === $source_hash || $source_word_count <= 0 ) {
			return array(
				'status'            => 'unknown',
				'content_change_ratio' => null,
				'policy'            => 'missing_source_fingerprint',
			);
		}

		$post = get_post( $post_id );
		$current_text = $post ? $this->normalized_content_text( (string) $post->post_content ) : '';
		$current_hash = $this->content_hash( $current_text );
		$current_word_count = $this->content_word_count( $current_text );
		if ( '' !== $current_hash && hash_equals( $source_hash, $current_hash ) ) {
			return array(
				'status'            => 'current',
				'content_change_ratio' => 0.0,
				'current_word_count' => $current_word_count,
				'source_word_count' => $source_word_count,
			);
		}

		if ( $current_word_count <= 0 ) {
			return array(
				'status'            => 'review_recommended',
				'content_change_ratio' => null,
				'current_word_count' => $current_word_count,
				'source_word_count' => $source_word_count,
			);
		}

		$ratio = abs( $current_word_count - $source_word_count ) / max( 1, $source_word_count );
		if ( $ratio < 0.03 ) {
			$status = 'minor_drift';
		} elseif ( $ratio <= 0.15 ) {
			$status = 'review_recommended';
		} else {
			$status = 'stale';
		}

		return array(
			'status'            => $status,
			'content_change_ratio' => $ratio,
			'current_word_count' => $current_word_count,
			'source_word_count' => $source_word_count,
		);
	}

	private function freshness_label( string $status ): string {
		if ( 'minor_drift' === $status ) {
			return __( 'Article audio may be slightly out of date after minor edits. Review before regenerating.', 'npcink-workflow-toolbox' );
		}
		if ( 'review_recommended' === $status ) {
			return __( 'Article audio needs review because the article changed after generation.', 'npcink-workflow-toolbox' );
		}
		if ( 'stale' === $status ) {
			return __( 'Article audio is likely stale. Regenerate after reviewing the article changes.', 'npcink-workflow-toolbox' );
		}

		return __( 'Article audio freshness is unknown.', 'npcink-workflow-toolbox' );
	}

	private function normalized_content_text( string $content ): string {
		$text = trim( wp_strip_all_tags( $content ) );
		$text = preg_replace( '/\s+/u', ' ', $text );
		return is_string( $text ) ? trim( $text ) : '';
	}

	private function content_hash( string $content ): string {
		$content = $this->normalized_content_text( $content );
		return '' === $content ? '' : hash( 'sha256', $content );
	}

	private function content_word_count( string $content ): int {
		$content = $this->normalized_content_text( $content );
		if ( '' === $content ) {
			return 0;
		}

		$word_count = str_word_count( $content );
		if ( $word_count > 0 ) {
			return $word_count;
		}

		return function_exists( 'mb_strlen' ) ? mb_strlen( $content, 'UTF-8' ) : strlen( $content );
	}

	private function label_for_kind( string $kind ): string {
		if ( 'summary' === $kind || 'audio_summary' === $kind ) {
			return __( 'Audio summary', 'npcink-workflow-toolbox' );
		}

		if ( 'narration' === $kind || 'article_narration' === $kind ) {
			return __( 'Article narration', 'npcink-workflow-toolbox' );
		}

		return __( 'Article audio', 'npcink-workflow-toolbox' );
	}

	private function format_duration( float $seconds ): string {
		$total_seconds = (int) round( $seconds );
		if ( $total_seconds <= 0 ) {
			return '';
		}

		$minutes = intdiv( $total_seconds, 60 );
		$seconds = $total_seconds % 60;

		return sprintf( '%d:%02d', $minutes, $seconds );
	}

	private function asset_version( string $relative_path ): string {
		$path     = NPCINK_TOOLBOX_DIR . ltrim( $relative_path, '/' );
		$modified = file_exists( $path ) ? filemtime( $path ) : false;
		return NPCINK_TOOLBOX_VERSION . ( $modified ? '-' . (string) $modified : '' );
	}
}
