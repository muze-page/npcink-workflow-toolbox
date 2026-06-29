<?php
/**
 * Main plugin coordinator.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

use Npcink\LocalAutomationRuntime\NightlyInspection\Basic_WP_Cron_Dry_Run;

defined( 'ABSPATH' ) || exit;

final class Plugin {
	public const OPTION_NAME         = 'npcink_toolbox_settings';
	public const CONTEXT_OPTION_NAME = 'npcink_toolbox_content_context';
	public const MEDIA_OPTION_NAME   = 'npcink_toolbox_media_optimization_settings';
	public const REST_NAMESPACE      = 'npcink-toolbox/v1';

	private static ?Plugin $instance = null;

	private Settings $settings;
	private Provider_Client $client;
	private Rest_Controller $rest_controller;
	private Admin_Page $admin_page;
	private Dashboard_Widget $dashboard_widget;
	private Editor_Content_Support $editor_content_support;
	private Article_Audio_Playback $article_audio_playback;
	private Site_Knowledge_Auto_Sync $site_knowledge_auto_sync;
	private Basic_WP_Cron_Dry_Run $nightly_inspection_cron;
	private Abilities $abilities;

	private function __construct() {
		$this->settings        = new Settings();
		$this->client          = new Provider_Client( $this->settings );
		$this->rest_controller = new Rest_Controller( $this->settings, $this->client );
		$this->admin_page      = new Admin_Page( $this->settings );
		$this->dashboard_widget = new Dashboard_Widget( $this->client );
		$this->editor_content_support = new Editor_Content_Support();
		$this->article_audio_playback = new Article_Audio_Playback();
		$this->site_knowledge_auto_sync = new Site_Knowledge_Auto_Sync( $this->client );
		$this->nightly_inspection_cron = new Basic_WP_Cron_Dry_Run( $this->settings );
		$this->abilities       = new Abilities( $this->settings, $this->client );
	}

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function register_hooks(): void {
		add_action( 'admin_init', array( $this->settings, 'register' ) );
		add_action( 'admin_menu', array( $this->admin_page, 'register_menu' ), 45 );
		add_action( 'admin_enqueue_scripts', array( $this->admin_page, 'enqueue' ) );
		add_filter( 'attachment_fields_to_edit', array( $this->admin_page, 'add_media_library_attachment_actions' ), 10, 2 );
		add_filter( 'media_row_actions', array( $this->admin_page, 'filter_media_library_row_actions' ), 10, 3 );
		add_filter( 'bulk_actions-upload', array( $this->admin_page, 'filter_media_library_bulk_actions' ) );
		add_filter( 'handle_bulk_actions-upload', array( $this->admin_page, 'handle_media_library_bulk_action' ), 10, 3 );
		add_action( 'enqueue_block_editor_assets', array( $this->editor_content_support, 'enqueue' ) );
		$this->article_audio_playback->register_hooks();
		add_filter( 'plugin_action_links_' . plugin_basename( NPCINK_TOOLBOX_FILE ), array( $this, 'filter_plugin_action_links' ) );
		$this->dashboard_widget->register_hooks();
		$this->site_knowledge_auto_sync->register_hooks();
		$this->nightly_inspection_cron->register_hooks();
		add_action( 'rest_api_init', array( $this->rest_controller, 'register_routes' ) );
		add_action( 'wp_abilities_api_categories_init', array( $this->abilities, 'register_with_npcink_abilities_toolkit' ), 1 );
		add_action( 'wp_abilities_api_categories_init', array( $this->abilities, 'register_native_category' ) );
		add_action( 'wp_abilities_api_init', array( $this->abilities, 'register_native_abilities' ) );
	}

	/**
	 * Adds a settings shortcut on the WordPress plugins screen.
	 *
	 * @param array<int|string,string> $links Existing plugin action links.
	 * @return array<int|string,string>
	 */
	public function filter_plugin_action_links( array $links ): array {
		array_unshift(
			$links,
			sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( $this->plugin_settings_url() ),
				esc_html__( 'Settings', 'npcink-workflow-toolbox' )
			)
		);

		return $links;
	}

	private function plugin_settings_url(): string {
		if ( function_exists( 'menu_page_url' ) ) {
			$url = menu_page_url( 'npcink-toolbox', false );
			if ( is_string( $url ) && '' !== $url ) {
				return $url;
			}
		}

		return admin_url( $this->has_npcink_parent_menu() ? 'admin.php?page=npcink-toolbox' : 'tools.php?page=npcink-toolbox' );
	}

	private function has_npcink_parent_menu(): bool {
		global $menu;

		foreach ( (array) $menu as $item ) {
			if ( isset( $item[2] ) && 'npcink-ai' === $item[2] ) {
				return true;
			}
		}

		return false;
	}

}
