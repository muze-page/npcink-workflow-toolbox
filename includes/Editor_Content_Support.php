<?php
/**
 * Post editor entrypoint for fixed content-support flows.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

defined( 'ABSPATH' ) || exit;

final class Editor_Content_Support {
	public function enqueue(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_enqueue_style(
			'npcink-toolbox-editor-content-support',
			NPCINK_TOOLBOX_URL . 'assets/editor-content-support.css',
			array(),
			NPCINK_TOOLBOX_VERSION
		);

		wp_enqueue_script(
			'npcink-toolbox-editor-content-support',
			NPCINK_TOOLBOX_URL . 'assets/editor-content-support.js',
			array( 'wp-api-fetch', 'wp-components', 'wp-core-data', 'wp-data', 'wp-edit-post', 'wp-editor', 'wp-element', 'wp-i18n', 'wp-plugins' ),
			NPCINK_TOOLBOX_VERSION,
			true
		);
		wp_set_script_translations(
			'npcink-toolbox-editor-content-support',
			'npcink-toolbox',
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
			)
		);
	}
}
