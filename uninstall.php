<?php
/**
 * Uninstall cleanup for Npcink Toolbox.
 *
 * @package Npcink_Toolbox
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'npcink_toolbox_settings' );
delete_option( 'npcink_toolbox_content_context' );
