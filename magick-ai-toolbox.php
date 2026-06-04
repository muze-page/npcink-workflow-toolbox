<?php
/**
 * Plugin Name: Magick AI Toolbox
 * Description: Operator-facing AI tools for Cloud-managed web search, Cloud-managed image-source candidates, Qdrant vector search, and repeatable content workflows.
 * Version: 0.1.0
 * Requires at least: 6.9
 * Requires PHP: 8.0
 * Author: Magick AI
 * Text Domain: magick-ai-toolbox
 *
 * @package Magick_AI_Toolbox
 */

defined( 'ABSPATH' ) || exit;

define( 'MAGICK_AI_TOOLBOX_VERSION', '0.1.0' );
define( 'MAGICK_AI_TOOLBOX_FILE', __FILE__ );
define( 'MAGICK_AI_TOOLBOX_DIR', plugin_dir_path( __FILE__ ) );
define( 'MAGICK_AI_TOOLBOX_URL', plugin_dir_url( __FILE__ ) );

require_once MAGICK_AI_TOOLBOX_DIR . 'includes/Settings.php';
require_once MAGICK_AI_TOOLBOX_DIR . 'includes/Provider_Client.php';
require_once MAGICK_AI_TOOLBOX_DIR . 'includes/Rest_Controller.php';
require_once MAGICK_AI_TOOLBOX_DIR . 'includes/Admin_Page.php';
require_once MAGICK_AI_TOOLBOX_DIR . 'includes/Editor_Content_Support.php';
require_once MAGICK_AI_TOOLBOX_DIR . 'includes/Abilities.php';
require_once MAGICK_AI_TOOLBOX_DIR . 'includes/Plugin.php';

add_action(
	'plugins_loaded',
	static function () {
		\Magick_AI_Toolbox\Plugin::instance()->register_hooks();
	}
);
