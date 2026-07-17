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
- The same effective raw-response policy drives REST status, editor diagnostics,
  and every Cloud response normalizer; production code must not read the raw
  option directly.
- Secret-shaped editor text is classified as `secret` with `no_store` before a
  Cloud handoff.
- Exact external source URLs pass WordPress validation, reject literal
  special-purpose IPv4/IPv6 addresses, and use only the standard port for their
  HTTP or HTTPS scheme. Cloud Addon owns fetch-time DNS, redirect, and response
  validation; Toolbox must not duplicate that transport policy.

## 3. Site Knowledge Cloud Addon Gate

When local WordPress has Toolbox and Cloud Addon installed, run:

```bash
composer smoke:site-knowledge-review-ui
composer smoke:site-knowledge-cloud-addon-bridge
```

The bridge smoke must prove Cloud Addon owns public content-change delivery,
Toolbox reports `owner=cloud_addon` through the `change_bridge`/`buffer_count`
status projection, Toolbox exposes the Cloud/Add-on returned
`site_knowledge_cloud_boundary` owner/truth map as read-only status detail, and
retired Toolbox legacy auto-sync hooks are not registered. The old `auto_sync`
and `queue_count` fields are compatibility aliases only.

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
NPCINK_TOOLBOX_PERF_OUTPUT="build/perf/toolbox-observation-1.jsonl" \
composer perf:baseline
```

The default is a local-only `/status` probe with one warmup and ten measured
requests. It records median and P95 timing. Missing or mixed HTTP status,
redirects, non-JSON responses, and non-2xx local responses fail immediately;
timing remains observation-only while three comparable baseline batches are
collected. After selecting a stable reference, set
`NPCINK_TOOLBOX_PERF_BASELINE` to compare later medians. A candidate regression
requires both greater than 30 percent and greater than 20 milliseconds. Add
`NPCINK_TOOLBOX_PERF_ENFORCE_REGRESSION=1` only after the three-batch baseline
is stable.

Add `NPCINK_TOOLBOX_PERF_INCLUDE_CLOUD=1` only for an intentional,
quota-aware Cloud proof. Site Knowledge status is Cloud-backed, and the full
set contains four Cloud routes. Start with
`NPCINK_TOOLBOX_PERF_SAMPLES=3 NPCINK_TOOLBOX_PERF_WARMUPS=0`; that still makes
twelve Cloud-backed requests. A stable known Cloud 4xx/5xx may be measured with
`NPCINK_TOOLBOX_PERF_ALLOW_ERROR_STATUS=1`, but this never relaxes local status.
For local self-signed HTTPS only, add
`NPCINK_TOOLBOX_PERF_INSECURE_TLS=1`. Keep output under ignored and
package-excluded `build/perf/`.

The WordPress Dashboard hot-topic widget is a local cache reader. Rendering the
widget must make zero Cloud calls. Its capability- and nonce-protected refresh
action may make one synchronous Cloud request after an explicit administrator
click; failure must preserve the last local backup.

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
