<?php
/**
 * Plugin Name: Npcink Workflow Toolbox
 * Description: Fixed AI workflow buttons for WordPress operators, with review-only suggestions and governed handoff plans.
 * Version: 0.1.1
 * Requires at least: 6.9
 * Requires PHP: 8.0
 * Author: Npcink
 * License: GPL-2.0-or-later
 * Text Domain: npcink-workflow-toolbox
 * Domain Path: /languages
 *
 * @package Npcink_Toolbox
 */

defined( 'ABSPATH' ) || exit;

define( 'NPCINK_TOOLBOX_VERSION', '0.1.1' );
define( 'NPCINK_TOOLBOX_FILE', __FILE__ );
define( 'NPCINK_TOOLBOX_DIR', plugin_dir_path( __FILE__ ) );
define( 'NPCINK_TOOLBOX_URL', plugin_dir_url( __FILE__ ) );

require_once NPCINK_TOOLBOX_DIR . 'includes/Settings.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Operation_Classifier.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Provider_Client.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Hot_Topic_Pool.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Site_Knowledge_Auto_Sync.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Site_Ops_Snapshot_Collector.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Site_Ops_Insight_Builder.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Site_Ops_Cloud_Request_Builder.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Ability_Surface_Metadata.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Rest_Controller.php';
require_once NPCINK_TOOLBOX_DIR . 'modules/local-automation-runtime/src/Contract/Replay_Validator.php';
require_once NPCINK_TOOLBOX_DIR . 'modules/local-automation-runtime/src/NightlyInspection/Rule_Scorer.php';
require_once NPCINK_TOOLBOX_DIR . 'modules/local-automation-runtime/src/NightlyInspection/Morning_Brief_Builder.php';
require_once NPCINK_TOOLBOX_DIR . 'modules/local-automation-runtime/src/NightlyInspection/Cloud_Batch_Result_Merger.php';
require_once NPCINK_TOOLBOX_DIR . 'modules/local-automation-runtime/src/NightlyInspection/Manual_Dry_Run_Planner.php';
require_once NPCINK_TOOLBOX_DIR . 'modules/local-automation-runtime/src/NightlyInspection/Snapshot_Collector.php';
require_once NPCINK_TOOLBOX_DIR . 'modules/local-automation-runtime/src/NightlyInspection/Basic_WP_Cron_Dry_Run.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Admin_Page.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Editor_Content_Support.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Article_Audio_Playback.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Dashboard_Widget.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Abilities.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Plugin.php';

register_deactivation_hook( NPCINK_TOOLBOX_FILE, array( \Npcink_Toolbox\Site_Knowledge_Auto_Sync::class, 'deactivate' ) );
register_deactivation_hook( NPCINK_TOOLBOX_FILE, array( \Npcink\LocalAutomationRuntime\NightlyInspection\Basic_WP_Cron_Dry_Run::class, 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		\Npcink_Toolbox\Plugin::instance()->register_hooks();
	}
);
