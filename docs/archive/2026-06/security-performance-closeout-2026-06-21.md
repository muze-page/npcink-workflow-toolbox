# Security And Performance Closeout - 2026-06-21

Status: closed local milestone.

This note summarizes the security and performance hardening milestone that
ended with commit `30b16b6 Add security performance release gates`, published
to `origin/master`.

## Boundary

This work stayed inside the Toolbox product-surface boundary:

- no direct WordPress publishing, media import, SEO mutation, or batch writes;
- no `confirm_token` or `write_confirmed` behavior;
- no second ability registry, workflow registry, approval store, queue, or
  runtime owner;
- no provider key exposure in logs, REST responses, proposals, or docs;
- all provider and hosted-runtime outputs remain suggestions, diagnostics, or
  Core handoff artifacts.

Toolbox remains the WordPress operator-facing surface. Core, Adapter,
Abilities, Cloud, and connector owners keep their existing responsibilities.

## Completed Work

The milestone converted the earlier security and performance review into
repeatable gates instead of one-off advice.

### Permission And Debug Hardening

- Added `tests/smoke-security-permission-debug.php`.
- Verified REST route scopes reach `npcink_toolbox_rest_permission`.
- Verified ability scopes reach `npcink_toolbox_ability_permission`.
- Verified unknown REST routes fail closed to `cap.toolbox.admin`.
- Verified `NPCINK_TOOLBOX_DISABLE_RAW_RESPONSES` suppresses raw payloads even
  when local debug output is enabled.
- Verified debug payloads redact sensitive keys and token-shaped strings.

### Performance Baseline

- Added `scripts/performance-baseline.php`.
- Added the `composer perf:baseline` command.
- Captures authenticated REST JSONL timings for status, Site Knowledge status,
  and optional Cloud-backed probes.
- Fails on missing HTTP status, unexpected error status, or requests over
  2500ms.
- Supports explicit local self-signed TLS testing through
  `NPCINK_TOOLBOX_PERF_INSECURE_TLS=1`.
- Supports explicit known-failure measurement through
  `NPCINK_TOOLBOX_PERF_ALLOW_ERROR_STATUS=1`.

### Release Gate Documentation

- Added `docs/security-performance-release-gate.md`.
- Added `docs/scoped-permissions-first-version.md`.
- Updated `README.md` and `docs/development-workflow.md` with the new commands
  and release checklist.
- Updated `tests/run.php` static contracts so public documentation and command
  drift are caught by the default gate.

## Verification Results

The final milestone passed:

```bash
composer smoke:security-permission-debug
php -l scripts/performance-baseline.php
php -l tests/smoke-security-permission-debug.php
composer validate --no-check-publish
php tests/run.php --quiet
composer test:all
composer package:release
git diff --check
composer smoke:site-knowledge-review-ui
composer smoke:site-knowledge-cloud-addon-bridge
composer smoke:nightly-inspection-cloud-e2e
composer perf:baseline
```

The Nightly Cloud E2E proof completed with run id
`run_02c30b429d9d454ca55098397c0186ef`.

The authenticated performance baseline recorded:

| Probe | Status | Duration | Notes |
| --- | ---: | ---: | --- |
| `status` | 200 | 177.1ms | Local status route. |
| `site_knowledge_status` | 200 | 551.3ms | Cloud Addon bridge status. |
| `site_knowledge_search` | 200 | 670.3ms | Cloud-backed semantic search. |
| `ai_content_support_summary` | 400 | 191ms | Known Cloud `text.ai` no-candidate failure path, measured with explicit allow flag. |
| `image_candidates_fast_first` | 200 | 400ms | Fast image-source candidate path. |

`composer package:release` produced a build artifact during verification, and
the generated `build/` directory was removed after the package gate passed.

## Remaining Risks

### Cloud `text.ai` No-Candidate Path

The only measured non-green runtime path is `ai_content_support_summary`.
Current behavior returns HTTP 400 because the Cloud `text.ai` profile reports
`routing.no_candidates`. The baseline can measure this path only when
`NPCINK_TOOLBOX_PERF_ALLOW_ERROR_STATUS=1` is set.

Next target: fix Cloud profile or routing availability so this probe can run
under the strict default `composer perf:baseline` gate without the allow flag.

### Scoped Permissions Are Still Host-Integration Only

The smoke proves Toolbox passes normalized scopes to host filters. It does not
grant non-admin permissions by default. This is intentional: `manage_options`
remains the default gate until a host model deliberately grants narrower
Toolbox scopes.

Next target: run one controlled host-integration trial before exposing scoped
Toolbox access to non-admin callers.

### Performance Baseline Is A Probe, Not Observability

`composer perf:baseline` catches route regressions and local staging latency,
but it is not request-log ownership, billing telemetry, quota reporting, or
production observability.

Next target: keep production runtime metrics in Cloud or the relevant connector
owner, not inside Toolbox.

## Next-Stage Recommendation

Stop this stage here. The security and performance gates are now repeatable,
documented, and published.

The next useful stage is narrow:

1. Fix Cloud `text.ai` profile or routing availability.
2. Re-run `composer perf:baseline` without
   `NPCINK_TOOLBOX_PERF_ALLOW_ERROR_STATUS=1`.
3. Record a strict all-200 baseline in this document or a follow-up trial note.
4. Only after that, consider one scoped-permission host trial using
   `docs/scoped-permissions-first-version.md`.

