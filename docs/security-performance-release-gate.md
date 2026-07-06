# Security And Performance Release Gate

Status: next-stage verification checklist.

Use this checklist before treating a Toolbox hardening milestone as ready for a
release branch or PR.

## 1. Package Baseline

Run:

```bash
composer validate --no-check-publish
composer test:all
composer smoke:security-permission-debug
```

If WordPress.org packaging is in scope for the milestone, also run:

```bash
composer package:release
```

`composer release:verify` remains the broader local release gate when WP-CLI,
Plugin Check, and the configured local WordPress site are available.

## 2. Permission And Debug Payload Gate

Confirm:

- REST routes pass `$request`, `$required_scope`, and normalized `$route` to
  `npcink_toolbox_rest_permission`.
- Abilities pass `$ability_id` and `$required_scope` to
  `npcink_toolbox_ability_permission`.
- Unknown routes fall back to `cap.toolbox.admin`.
- `NPCINK_TOOLBOX_DISABLE_RAW_RESPONSES` suppresses raw payloads even when the
  local debug option is enabled.
- Debug payloads redact sensitive keys and token-shaped strings before display.

## 3. Site Knowledge Cloud Addon Gate

When local WordPress has Toolbox and Cloud Addon installed, run:

```bash
composer smoke:site-knowledge-review-ui
composer smoke:site-knowledge-cloud-addon-bridge
```

The bridge smoke must prove Cloud Addon owns public content-change delivery,
Toolbox reports `owner=cloud_addon` through the `change_bridge`/`buffer_count`
status projection, and retired Toolbox legacy auto-sync hooks are not
registered. The old `auto_sync` and `queue_count` fields are compatibility
aliases only.

## 4. Cloud Addon Transport Gate

Before treating Cloud-backed image, audio, web-search, or image-source paths as
release-ready, follow
[`Cloud Addon Transport Release Gate`](cloud-addon-transport-release-gate.md).
That gate separates no-credit contract checks from credit-consuming real
provider checks, and requires quota or provider-configuration blockers to be
recorded as Cloud readiness state instead of Toolbox-owned provider logic.

## 5. Performance Baseline

Capture a small authenticated local or staging REST baseline:

```bash
NPCINK_TOOLBOX_BASE_URL="https://example.local" \
NPCINK_TOOLBOX_AUTH_COOKIE="wordpress_logged_in_..." \
NPCINK_TOOLBOX_NONCE="..." \
NPCINK_TOOLBOX_PERF_OUTPUT="var/perf/toolbox-baseline.jsonl" \
composer perf:baseline
```

Add `NPCINK_TOOLBOX_PERF_INCLUDE_CLOUD=1` only when Cloud Addon/runtime
availability is part of the release proof. For local self-signed HTTPS only,
add `NPCINK_TOOLBOX_PERF_INSECURE_TLS=1`. To measure a known Cloud unavailable
or no-candidate failure path, add `NPCINK_TOOLBOX_PERF_ALLOW_ERROR_STATUS=1`
and preserve the status in the trial note. The baseline should include status,
Site Knowledge status, and, when enabled, Cloud-backed Site Knowledge search,
content support, and fast-first image candidates. Any probe without an HTTP
status, unexpected 4xx/5xx status, or over 2500ms should be investigated before
release.

## 6. Nightly Cloud E2E Gate

When Cloud API, Redis/Postgres, Cloud runtime worker, Cloud Addon, and local
WordPress are all available, run:

```bash
composer smoke:nightly-inspection-cloud-e2e
```

The smoke must still prove that Cloud returns runtime/detail only: no local
Toolbox scheduler truth, no automatic Core proposal creation, and no direct
WordPress write authority.

## 7. Scoped Permission Design Gate

Before granting non-admin access, review
[`Scoped Permissions First Version`](scoped-permissions-first-version.md). A
host may grant narrower Toolbox scopes, but Toolbox itself must keep the default
`manage_options` gate and must not treat a Toolbox scope as Core approval,
Adapter execution, indexing lifecycle, quota, billing, or request-log authority.
