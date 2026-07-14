<?php
/**
 * WP-CLI bootstrap guard for the five-plugin no-credit acceptance.
 *
 * This file is loaded through WP-CLI --require for every preflight and smoke
 * process. It blocks outbound HTTP before command execution and records any
 * attempted URL for the parent shell gate. One exact loopback URL may be
 * allowed for the Cloud Addon transport lane, where a later filter returns the
 * deterministic mock response before a socket is opened.
 *
 * @package Npcink_Toolbox
 */

$toolbox_http_guard_allowed_url = trim( (string) getenv( 'NPCINK_TOOLBOX_HTTP_GUARD_ALLOWED_URL' ) );
$toolbox_http_guard_log         = trim( (string) getenv( 'NPCINK_TOOLBOX_HTTP_GUARD_LOG' ) );

if ( ! defined( 'WP_HTTP_BLOCK_EXTERNAL' ) ) {
	define( 'WP_HTTP_BLOCK_EXTERNAL', true );
}

$toolbox_install_http_guard = static function () use ( $toolbox_http_guard_allowed_url, $toolbox_http_guard_log ): void {
	add_filter(
		'pre_http_request',
		static function ( $preempt, $parsed_args, $url ) use ( $toolbox_http_guard_allowed_url, $toolbox_http_guard_log ) {
			$url = (string) $url;
			if ( '' !== $toolbox_http_guard_allowed_url && hash_equals( $toolbox_http_guard_allowed_url, $url ) ) {
				return $preempt;
			}

			if ( '' !== $toolbox_http_guard_log ) {
				file_put_contents(
					$toolbox_http_guard_log,
					str_replace( array( "\r", "\n" ), '', $url ) . PHP_EOL,
					FILE_APPEND | LOCK_EX
				);
			}

			return new WP_Error(
				'toolbox_five_plugin_acceptance_http_blocked',
				'Outbound HTTP is blocked by the five-plugin no-credit acceptance bootstrap.'
			);
		},
		PHP_INT_MIN,
		3
	);
};

if ( function_exists( 'add_filter' ) ) {
	$toolbox_install_http_guard();
} elseif ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::add_hook( 'after_wp_load', $toolbox_install_http_guard );
}
