#!/bin/sh
set -u

WP_PATH="${WP_PATH:-/Users/muze/Local Sites/npcink/app/public}"
WP_CLI_BIN="${WP_CLI_BIN:-/opt/homebrew/bin/wp}"
WP_CLI_PHP="${WP_CLI_PHP:-}"
SOCKET="${WP_DB_SOCKET:-$HOME/Library/Application Support/Local/run/NPb24Zg9g/mysql/mysqld.sock}"

CLOUD_PLUGIN="${NPCINK_CLOUD_ADDON_PLUGIN:-npcink-cloud-addon}"
TOOLBOX_PLUGIN="${NPCINK_TOOLBOX_PLUGIN:-npcink-toolbox}"
CLOUD_WAS_ACTIVE=0
TOOLBOX_WAS_ACTIVE=0

wp_cli() {
	if [ -n "$WP_CLI_PHP" ]; then
		"$WP_CLI_PHP" \
			-d display_errors=0 \
			-d error_reporting=8191 \
			-d mysqli.default_socket="$SOCKET" \
			"$WP_CLI_BIN" \
			--path="$WP_PATH" \
			--no-color \
			"$@"
		return
	fi

	"$WP_CLI_BIN" \
		--path="$WP_PATH" \
		--no-color \
		"$@"
}

restore_plugins() {
	status=$?
	if [ "$TOOLBOX_WAS_ACTIVE" != "1" ]; then
		wp_cli plugin deactivate "$TOOLBOX_PLUGIN" >/dev/null 2>&1 || true
	fi
	if [ "$CLOUD_WAS_ACTIVE" != "1" ]; then
		wp_cli plugin deactivate "$CLOUD_PLUGIN" >/dev/null 2>&1 || true
	fi
	exit "$status"
}

trap restore_plugins EXIT INT TERM

if wp_cli plugin is-active "$CLOUD_PLUGIN" >/dev/null 2>&1; then
	CLOUD_WAS_ACTIVE=1
fi
if wp_cli plugin is-active "$TOOLBOX_PLUGIN" >/dev/null 2>&1; then
	TOOLBOX_WAS_ACTIVE=1
fi

if [ "$CLOUD_WAS_ACTIVE" != "1" ]; then
	wp_cli plugin activate "$CLOUD_PLUGIN" >/dev/null
fi
if [ "$TOOLBOX_WAS_ACTIVE" != "1" ]; then
	wp_cli plugin activate "$TOOLBOX_PLUGIN" >/dev/null
fi
wp_cli eval-file tests/smoke-site-knowledge-cloud-addon-bridge.php
