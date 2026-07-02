# Cloud Bridge And Site Check Validation Closeout - 2026-07-02

## Status

Accepted as a local validation and merge closeout record.

This document summarizes the July 2, 2026 work that started from an
adversarial boundary review of `npcink-workflow-toolbox`, continued through
Cloud bridge contract hardening and real local UI validation, and ended with a
repeatable browser smoke for Site Check Cloud detail and Scheduled Review.

## Purpose

The goal was not to add new product runtime ownership to Toolbox. The goal was
to prove that the current Toolbox surface can safely:

- expose Cloud bridge contracts as bounded, review-only product surfaces;
- run local Site Check without hidden Cloud calls, Core proposals, local run
  storage, queues, scheduler truth, or WordPress writes;
- let an administrator explicitly request Cloud runtime/detail for Site Check;
- show Scheduled Review as local preview plus Cloud Addon recovery, not as a
  local Cloud run workspace;
- keep all final WordPress writes behind Core, Adapter, and Abilities
  governance.

## Boundary Summary

The work stayed inside Toolbox's operator-facing product surface. Toolbox owns
the fixed-button UI, local review artifacts, compatibility routes, and
suggestion-only handoff display.

Toolbox still does not own:

- Core proposal truth, approval, preflight, or audit logs;
- final WordPress write authorization;
- reusable first-party WordPress ability definitions owned by
  `npcink-abilities-toolkit`;
- Cloud runtime queues, retries, retention, run recovery, or scheduler truth;
- Site Knowledge indexing lifecycle, embeddings, vector collection ownership,
  or reranking policy;
- provider key rotation, billing, quota, or durable request logs.

The only accepted direct-write exception remains the separately documented
Local Admin Consent featured-image proof. This closeout does not expand that
exception.

## What Changed

### Boundary Contract Tables

The branch added and validated machine-readable contract tables for the
Toolbox public surface:

- route boundary table;
- ability boundary table;
- Cloud bridge contract table.

These records make ownership, write posture, runtime owner, and forbidden
local-control-plane drift easier to test and audit.

### Site Check Cloud Detail

Site Check remains a local current-site report first. The local report builds a
bounded `site_ops_insight_pack.v1` and a copyable
`site_ops_cloud_analysis_request.v1` without calling Cloud.

When Cloud is ready and the administrator explicitly clicks the Cloud detail
action, Toolbox sends the bounded request through the Cloud Addon/runtime seam
and renders `site_ops_cloud_analysis_result.v1` as suggestion-only detail. The
result includes Cloud runtime/detail ranking, run id, trend notes, semantic
ranking, and local-governed Core/WordPress boundary copy. It does not create
Core proposals, run local queues, store local run history, claim scheduler
truth, or write WordPress data.

### Scheduled Review

Scheduled Review stayed a low-frequency local preview and Cloud Addon recovery
handoff. The Toolbox page does not expose local Nightly Cloud Batch submit,
recent-run, retry, or recovery controls as the primary product surface. Cloud
Addon Runtime Runs remains the run-history and recovery location.

### Real Browser Smoke

The hand-run browser validation was converted into:

```bash
NODE_PATH="${NODE_PATH:-/Users/muze/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules}" composer smoke:site-ops-cloud-detail-browser
```

The smoke:

- logs into the local WordPress admin through a short-lived local helper and
  cleans that helper afterward;
- opens Site Check, generates the local current-check report, and verifies the
  current-check UI renders;
- clicks the explicit Cloud detail action;
- waits for the server-rendered Cloud result, switches back to the Cloud tab
  after reload, and verifies the rendered Cloud result card, run id, boundary
  copy, and non-error state;
- verifies the browser flow did not request Core proposal, approve-and-execute,
  or media execution routes;
- switches to Scheduled Review, verifies the Cloud Addon runtime-runs recovery
  link, and confirms local Nightly Cloud Batch controls are absent;
- clicks the Scheduled Review preview and verifies the preview keeps no Cloud
  call, no Core proposal, and no WordPress write posture;
- captures local screenshots under `build/smoke/`.

The smoke is intentionally outside `composer test:all` because it requires a
running local WordPress site, writable local WordPress root, Playwright, a
local browser, a verified Cloud Addon connection, and a running Cloud runtime.

## Verification Evidence

The following gates passed during the closeout:

- `composer smoke:site-ops-insights-browser`
- `composer smoke:site-ops-cloud-e2e`
  - Cloud run id: `run_79fbdc6e6eec47ce956ee5728ee4d0b1`
- `composer smoke:nightly-inspection-cloud-e2e`
  - Cloud run id: `run_9e1efb14083f481bac8224e5907764ff`
- `NODE_PATH=".../node_modules" composer smoke:site-ops-cloud-detail-browser`
- `php tests/run.php --quiet --filter='Site Check Cloud detail browser'`
- `composer validate --no-check-publish`
- `composer test:all`
  - final static contract count after the browser-smoke addition:
    `3457 passed`

Earlier Cloud runtime repair and validation also confirmed that the local Cloud
Addon connection could decrypt the active key in the current API environment
and that Nightly Cloud Batch E2E could complete successfully. Secret key values
were not recorded in this repository document.

## Local Screenshots

Successful local browser-smoke runs wrote screenshots to:

- `build/smoke/site-ops-insights.png`
- `build/smoke/site-ops-cloud-detail-browser.png`
- `build/smoke/site-ops-scheduled-review-browser.png`

These are local evidence artifacts and should not be treated as release
source files.

## Commits In This Closeout

Key local commits before merge:

- `ee26e83` - `Add Cloud bridge contract table`
- `1c4fba3` - `Add Site Check Cloud detail browser smoke`

This closeout record is intended to be committed on the same branch before the
branch is merged into `master`.

## Decision

Stop expanding product scope in this phase.

The useful product-quality gain is that an operator-critical path which was
previously validated manually is now repeatable as a named, optional browser
smoke. The next stage should not add more Toolbox runtime ownership. If more
production readiness is needed, it should focus on publishing/PR verification
and Cloud Addon recovery UX, while preserving the same boundary:

- Toolbox displays suggestions and compatibility detail;
- Cloud owns runtime/detail and recovery;
- Core/Adapter/Abilities own governed WordPress writes.
