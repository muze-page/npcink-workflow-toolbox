<?php
/**
 * Post editor entrypoint for fixed content-support flows.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

defined( 'ABSPATH' ) || exit;

final class Editor_Content_Support {
	public const PUBLISH_EXECUTION_META_KEY = '_npcink_toolbox_publish_execution_intents';

	public function register_hooks(): void {
		add_action( 'init', array( $this, 'register_publish_execution_meta' ), 20 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue' ) );
	}

	public function register_publish_execution_meta(): void {
		$post_types = get_post_types( array( 'show_in_rest' => true ), 'names' );

		foreach ( $post_types as $post_type ) {
			if ( 'attachment' === $post_type || ! post_type_supports( $post_type, 'editor' ) ) {
				continue;
			}

			register_post_meta(
				$post_type,
				self::PUBLISH_EXECUTION_META_KEY,
				array(
					'type'              => 'array',
					'single'            => true,
					'default'           => array(),
					'sanitize_callback' => array( self::class, 'sanitize_publish_execution_intents' ),
					'auth_callback'     => static function ( bool $allowed, string $meta_key, int $object_id ): bool {
						return current_user_can( 'manage_options' ) && current_user_can( 'edit_post', $object_id );
					},
					'show_in_rest'      => false,
				)
			);
		}
	}

	/**
	 * Keeps the bounded editor-to-publish handoff free of arbitrary payloads.
	 *
	 * @param mixed $value REST-provided post meta value.
	 * @return array<int,array{proposal_id:string,operation:string,author_approved_at:string}>
	 */
	public static function sanitize_publish_execution_intents( $value ): array {
		$allowed_operations = array( 'seo_meta', 'image_adoption', 'article_audio' );
		$sanitized          = array();

		foreach ( array_slice( is_array( $value ) ? $value : array(), 0, 20 ) as $intent ) {
			if ( ! is_array( $intent ) ) {
				continue;
			}

			$proposal_id = substr( (string) preg_replace( '/[^A-Za-z0-9_-]/', '', (string) ( $intent['proposal_id'] ?? '' ) ), 0, 191 );
			$operation   = sanitize_key( (string) ( $intent['operation'] ?? '' ) );
			$approved_at = substr( sanitize_text_field( (string) ( $intent['author_approved_at'] ?? '' ) ), 0, 40 );
			if ( '' === $proposal_id || isset( $sanitized[ $proposal_id ] ) || ! in_array( $operation, $allowed_operations, true ) || '' === $approved_at ) {
				continue;
			}

			$sanitized[ $proposal_id ] = array(
				'proposal_id'       => $proposal_id,
				'operation'         => $operation,
				'author_approved_at' => $approved_at,
			);
		}

		return array_values( $sanitized );
	}

	public function enqueue(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$style_version  = $this->asset_version( 'assets/editor-content-support.css' );
		$script_version = $this->asset_version( 'assets/editor-content-support.js' );

		wp_enqueue_style(
			'npcink-toolbox-editor-content-support',
			NPCINK_TOOLBOX_URL . 'assets/editor-content-support.css',
			array(),
			$style_version
		);

		wp_enqueue_script(
			'npcink-toolbox-editor-content-support',
			NPCINK_TOOLBOX_URL . 'assets/editor-content-support.js',
			array( 'wp-api-fetch', 'wp-block-editor', 'wp-components', 'wp-core-data', 'wp-data', 'wp-edit-post', 'wp-editor', 'wp-element', 'wp-hooks', 'wp-i18n', 'wp-plugins' ),
			$script_version,
			true
		);
		wp_set_script_translations(
			'npcink-toolbox-editor-content-support',
			'npcink-workflow-toolbox',
			NPCINK_TOOLBOX_DIR . 'languages'
		);

		wp_localize_script(
			'npcink-toolbox-editor-content-support',
			'NpcinkToolboxEditorSupport',
			array(
				'restUrl'        => esc_url_raw( rest_url( Plugin::REST_NAMESPACE ) ),
				'coreRestUrl'    => esc_url_raw( rest_url( 'npcink-governance-core/v1' ) ),
				'adapterRestUrl' => esc_url_raw( rest_url( 'npcink-openclaw-adapter/v1' ) ),
				'nonce'          => wp_create_nonce( 'wp_rest' ),
				'adminUrl'       => esc_url_raw( admin_url( 'admin.php?page=npcink-toolbox&toolbox_tab=tools' ) ),
				'coreAdminUrl'   => esc_url_raw( admin_url( 'admin.php?page=npcink-governance-core' ) ),
				'showRuntimeDiagnostics' => $this->show_runtime_diagnostics(),
			)
		);
	}

	private function show_runtime_diagnostics(): bool {
		$settings = get_option( Plugin::OPTION_NAME, array() );
		return is_array( $settings ) && ! empty( $settings['include_raw_responses'] );
	}

	private function asset_version( string $relative_path ): string {
		$path     = NPCINK_TOOLBOX_DIR . ltrim( $relative_path, '/' );
		$modified = file_exists( $path ) ? filemtime( $path ) : false;
		return NPCINK_TOOLBOX_VERSION . ( $modified ? '-' . (string) $modified : '' );
	}
}
