<?php
/**
 * Main plugin coordinator.
 *
 * @package Magick_AI_Toolbox
 */

namespace Magick_AI_Toolbox;

defined( 'ABSPATH' ) || exit;

final class Plugin {
	public const OPTION_NAME         = 'magick_ai_toolbox_settings';
	public const CONTEXT_OPTION_NAME = 'magick_ai_toolbox_content_context';
	public const REST_NAMESPACE      = 'magick-ai-toolbox/v1';

	private static ?Plugin $instance = null;

	private Settings $settings;
	private Provider_Client $client;
	private Rest_Controller $rest_controller;
	private Admin_Page $admin_page;
	private Editor_Content_Support $editor_content_support;
	private Abilities $abilities;

	private function __construct() {
		$this->settings        = new Settings();
		$this->client          = new Provider_Client( $this->settings );
		$this->rest_controller = new Rest_Controller( $this->settings, $this->client );
		$this->admin_page      = new Admin_Page( $this->settings );
		$this->editor_content_support = new Editor_Content_Support();
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
		add_action( 'admin_menu', array( $this->admin_page, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this->admin_page, 'enqueue' ) );
		add_action( 'enqueue_block_editor_assets', array( $this->editor_content_support, 'enqueue' ) );
		add_action( 'rest_api_init', array( $this->rest_controller, 'register_routes' ) );
		add_action( 'wp_abilities_api_categories_init', array( $this->abilities, 'register_with_magick_ai_abilities' ), 1 );
		add_action( 'wp_abilities_api_categories_init', array( $this->abilities, 'register_native_category' ) );
		add_action( 'wp_abilities_api_init', array( $this->abilities, 'register_native_abilities' ) );
	}
}
