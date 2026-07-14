#!/bin/sh
set -eu

ROOT="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
NPCINK_REPO_FAMILY_ROOT="${NPCINK_REPO_FAMILY_ROOT:-$(dirname "$ROOT")}"
WP_PATH="${WP_PATH:-/Users/muze/Local Sites/npcink/app/public}"
WP_CLI_BIN="${WP_CLI_BIN:-/opt/homebrew/bin/wp}"
WP_CLI_PHP="${WP_CLI_PHP:-$HOME/Library/Application Support/Local/lightning-services/php-8.5.3+1/bin/darwin-arm64/bin/php}"
SOCKET="${WP_DB_SOCKET:-$HOME/Library/Application Support/Local/run/NPb24Zg9g/mysql/mysqld.sock}"

fail() {
	printf 'FAIL: %s\n' "$1" >&2
	exit 1
}

wp_cli() {
	if [ -n "$WP_CLI_PHP" ]; then
		if "$WP_CLI_PHP" \
				-d display_errors=0 \
				-d error_reporting=8191 \
				-d mysqli.default_socket="$SOCKET" \
				-d pdo_mysql.default_socket="$SOCKET" \
				"$WP_CLI_BIN" \
				--path="$WP_PATH" \
				--no-color \
				--require="$ROOT/tests/bootstrap-no-outbound-http.php" \
				"$@"; then
			status=0
		else
			status=$?
		fi
	else
		if "$WP_CLI_BIN" \
				--path="$WP_PATH" \
				--no-color \
				--require="$ROOT/tests/bootstrap-no-outbound-http.php" \
				"$@"; then
			status=0
		else
			status=$?
		fi
	fi

	if [ -s "$HTTP_GUARD_LOG" ]; then
		printf '%s\n' 'FAIL: Unexpected outbound HTTP was attempted by a WordPress acceptance process:' >&2
		sed 's/^/  /' "$HTTP_GUARD_LOG" >&2
		return 86
	fi

	return "$status"
}

[ -d "$WP_PATH" ] || fail "WordPress path does not exist: $WP_PATH"
if [ -n "$WP_CLI_PHP" ]; then
	[ -x "$WP_CLI_PHP" ] || fail "Local PHP is not executable: $WP_CLI_PHP"
	[ -r "$WP_CLI_BIN" ] || fail "WP-CLI is not readable: $WP_CLI_BIN"
else
	[ -x "$WP_CLI_BIN" ] || fail "WP-CLI is not executable: $WP_CLI_BIN"
fi

LOCK_DIR="${TMPDIR:-/tmp}/npcink-five-plugin-acceptance.lock"
mkdir "$LOCK_DIR" 2>/dev/null || fail "Another five-plugin acceptance is already running: $LOCK_DIR"
HTTP_GUARD_LOG="$LOCK_DIR/unexpected-http.log"
export NPCINK_TOOLBOX_HTTP_GUARD_LOG="$HTTP_GUARD_LOG"
trap 'rm -f "$HTTP_GUARD_LOG"; rmdir "$LOCK_DIR" 2>/dev/null || true' 0 1 2 3 15

WP_PREFLIGHT="$(
	wp_cli --skip-plugins --skip-themes eval '
		$active = (array) get_option( "active_plugins", array() );
		if ( is_multisite() ) {
			$active = array_merge( $active, array_keys( (array) get_site_option( "active_sitewide_plugins", array() ) ) );
		}
		$slugs = array();
		foreach ( $active as $plugin_file ) {
			$parts = explode( "/", (string) $plugin_file, 2 );
			$slugs[] = $parts[0];
		}
		$slugs = array_values( array_unique( array_filter( $slugs ) ) );
		sort( $slugs );
		echo WP_PLUGIN_DIR . "\n" . implode( "\n", $slugs );
	'
)"
WP_PLUGIN_DIR_PATH="$(printf '%s\n' "$WP_PREFLIGHT" | sed -n '1p')"
ACTIVE_PLUGIN_SLUGS="$(printf '%s\n' "$WP_PREFLIGHT" | sed -n '2,$p')"
[ -d "$WP_PLUGIN_DIR_PATH" ] || fail "WordPress plugin directory does not exist: $WP_PLUGIN_DIR_PATH"

printf '%s\n' '[five-plugin] checking active plugin set and mounted worktrees'
for plugin in \
	npcink-workflow-toolbox \
	npcink-abilities-toolkit \
	npcink-governance-core \
	npcink-ai-client-adapter \
	npcink-cloud-addon
do
	case "$plugin" in
		npcink-workflow-toolbox) expected_path="$ROOT" ;;
		*) expected_path="$NPCINK_REPO_FAMILY_ROOT/$plugin" ;;
	esac
	actual_path="$WP_PLUGIN_DIR_PATH/$plugin"
	[ -d "$expected_path" ] || fail "Expected sibling repository does not exist: $expected_path"
	[ -d "$actual_path" ] || fail "Mounted WordPress plugin path does not exist: $actual_path"
	expected_real="$(CDPATH= cd -- "$expected_path" && pwd -P)"
	actual_real="$(CDPATH= cd -- "$actual_path" && pwd -P)"
	[ "$actual_real" = "$expected_real" ] || fail "Mounted plugin is not the current repository: $plugin ($actual_real != $expected_real)"
	printf '%s\n' "$ACTIVE_PLUGIN_SLUGS" | grep -Fx "$plugin" >/dev/null 2>&1 || fail "Required plugin is not active: $plugin"
	printf 'PASS: active current worktree %s\n' "$plugin"
done

printf '%s\n' '[five-plugin] lane 1/2: governed draft execution and replay rejection'
NPCINK_TOOLBOX_ARTICLE_CORE_SMOKE_EXECUTE=1 \
NPCINK_TOOLBOX_ARTICLE_CORE_SMOKE_PURGE=1 \
	wp_cli eval-file "$ROOT/tests/smoke-article-draft-core-proof.php"

printf '%s\n' '[five-plugin] lane 2/2: Cloud Addon no-credit suggestion transport'
NPCINK_TOOLBOX_HTTP_GUARD_ALLOWED_URL='http://127.0.0.1:8010/v1/runtime/execute' \
	wp_cli eval-file "$ROOT/tests/smoke-ai-image-cloud-addon-transport.php"

printf '%s\n' 'PASS: five-plugin local acceptance completed without a real Cloud request.'
