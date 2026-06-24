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

		return $audio;
	}

	/**
	 * @param array<string,mixed> $audio Audio playback metadata.
	 */
	private function render_player( array $audio ): string {
		$label    = $this->label_for_kind( (string) $audio['kind'] );
		$title    = '' !== $audio['title'] ? (string) $audio['title'] : $label;
		$duration = $this->format_duration( (float) $audio['duration_seconds'] );

		ob_start();
		?>
		<section class="npcink-toolbox-article-audio" aria-label="<?php echo esc_attr( $label ); ?>">
			<div class="npcink-toolbox-article-audio__summary">
				<span class="npcink-toolbox-article-audio__eyebrow"><?php echo esc_html__( 'Audio', 'npcink-toolbox' ); ?></span>
				<strong class="npcink-toolbox-article-audio__title"><?php echo esc_html( $title ); ?></strong>
				<?php if ( '' !== $duration ) : ?>
					<span class="npcink-toolbox-article-audio__duration"><?php echo esc_html( $duration ); ?></span>
				<?php endif; ?>
			</div>
			<audio class="npcink-toolbox-article-audio__player" controls preload="none">
				<source src="<?php echo esc_url( (string) $audio['url'] ); ?>"<?php echo '' !== $audio['mime_type'] ? ' type="' . esc_attr( (string) $audio['mime_type'] ) . '"' : ''; ?> />
				<?php esc_html_e( 'Your browser does not support audio playback.', 'npcink-toolbox' ); ?>
			</audio>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	private function label_for_kind( string $kind ): string {
		if ( 'summary' === $kind || 'audio_summary' === $kind ) {
			return __( 'Audio summary', 'npcink-toolbox' );
		}

		if ( 'narration' === $kind || 'article_narration' === $kind ) {
			return __( 'Article narration', 'npcink-toolbox' );
		}

		return __( 'Article audio', 'npcink-toolbox' );
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
