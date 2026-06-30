# Cloud Diagnostics Transition Summary

Status: historical decision record for the Toolbox Cloud Checks removal.

Date: 2026-06-30

## Context

During the admin surface review, the Cloud Checks preview URL and the normal
Cloud Checks URL were found to expose overlapping troubleshooting behavior.
Several entries looked like product workflow checks in one place and Cloud
connection diagnostics in another. The project is still in active development,
has no user compatibility burden, and does not need to preserve duplicate
legacy admin surfaces.

The key product question was whether Toolbox should keep a standalone Cloud
diagnostics workspace or whether those checks belong in `npcink-cloud-addon`
and the Cloud service plane.

## Decision

Toolbox no longer owns a standalone Cloud Checks or Troubleshooting Checks
surface.

Cloud connection checks, hosted runtime health, search/image-source diagnostics,
AI image generation readiness, Site Knowledge bridge checks, entitlement, quota,
billing, request logs, and service health detail belong in `npcink-cloud-addon`
or Cloud service-plane surfaces.

Toolbox keeps only task-owned product surfaces:

- Content Library Usage for read-only Site Knowledge status, result
  consumption, search checks, and governed review handoff context. Connection,
  refresh, indexing, and detailed delivery diagnostics live in Cloud Addon.
- Site Check for manual read-only site checks and explicit Cloud detail
  requests.
- Morning Brief for scheduled-review preview and local fallback settings; Cloud
  run status, result reads, and recovery now live in Cloud Addon Runtime Runs.
- Image Handling for selected-media review, derivative preview, and governed
  handoff flows.

Cloud runtime routes may remain as bounded call sites for those product
workflows. Standalone diagnostics do not live in Toolbox.

## Rationale

The removed Cloud Checks panel was not a primary operator workflow. It was a
mixed troubleshooting surface that duplicated Cloud Addon responsibilities and
blurred the boundary between product workflows and infrastructure diagnostics.

Keeping both surfaces would create several problems:

- duplicate UI for the same Cloud readiness questions;
- unclear ownership for basic AI connection checks;
- pressure for Toolbox to grow into provider diagnostics, quota, request-log,
  and support-console ownership;
- weaker separation between local WordPress product actions and Cloud runtime
  detail.

The cleaner split is:

- Toolbox answers: what task is the operator trying to run, what suggestion or
  handoff can be produced, and what reviewed next step exists.
- Cloud Addon answers: whether Cloud is connected, verified, entitled, healthy,
  and able to run hosted capabilities.
- Cloud service plane answers: deeper provider, runtime, usage, billing,
  entitlement, request-log, and service-health detail.

## Implemented Toolbox Changes

The Toolbox admin page was simplified so the Cloud Checks secondary panel is no
longer rendered. Advanced navigation now points to setup, review, and governed
handoff previews instead of a local diagnostics group.

The JavaScript URL state and switchers for `toolbox_cloud_check` and
`toolbox_cloud_check_group` were removed. Article planning links that previously
pointed to Toolbox Cloud Checks now point toward Cloud Addon diagnostics.

Docs and tests were updated to make the ownership explicit:

- `README.md` states there is no local Cloud Checks or Troubleshooting Checks
  panel.
- `docs/boundary.md` now has a Cloud Diagnostics Ownership section.
- `docs/architecture.md` records that Toolbox no longer renders Cloud Checks.
- `docs/first-version-reference.md` states that standalone diagnostics do not
  live in Toolbox.
- `tests/run.php` asserts that Cloud Checks UI, URL state, and advanced
  diagnostics group entries are absent.

Chinese translations were updated for the remaining advanced setup copy.

## What Was Deliberately Kept

The following were not removed because they are still product workflow seams,
not standalone diagnostics:

- Site Knowledge product panel and manual sync/search/status routes.
- Morning Brief panel and Nightly Inspection Cloud Batch routes.
- Site Check and explicit Cloud detail request flow.
- Image Handling and media derivative handoff controls.
- REST routes and Abilities used by actual product workflows.

This keeps existing task flows available while removing the duplicate
troubleshooting console.

## Cloud Addon Follow-Up

The next implementation belongs in `npcink-cloud-addon`.

Recommended scope:

- organize a bounded Diagnostics or Status area in the existing Cloud Addon
  settings/overview structure;
- cover base URL and Cloud API Key verification;
- show Cloud service status and hosted runtime readiness;
- expose read-only entitlement, quota, usage, and billing detail or links;
- show Platform Models, provider readiness, and hosted capability metadata only
  as status/detail, not as router truth;
- check Cloud web search, image-source, AI image generation, and Site Knowledge
  bridge capability only when a real Cloud API/contract exists;
- keep raw payloads, request IDs, and deeper traces behind advanced disclosure;
- avoid provider secret exposure and split-key UI fields;
- avoid any second ability registry, workflow registry, approval store,
  WordPress write owner, router/prompt/preset control plane, or local runtime
  queue.

The Cloud Addon implementation should not recreate the old Toolbox Cloud
Checks page one-to-one. It should consolidate Cloud connection and runtime
detail in the owner plugin while leaving Toolbox as a fixed-button product
surface.

## Verification Baseline

After the Toolbox-side removal, the following local checks passed:

```bash
php -l includes/Admin_Page.php
node --check assets/admin.js
msgfmt languages/npcink-workflow-toolbox-zh_CN.po -o languages/npcink-workflow-toolbox-zh_CN.mo
git diff --check
composer test:all
```

The final `composer test:all` run reported the static contract suite as green.

## Future Rule

If a future contributor wants to add a Cloud diagnostic to Toolbox, first
classify it:

- task readiness inside a specific Toolbox workflow may stay as a read-only
  local summary;
- connection, credential, quota, entitlement, runtime, provider, request-log,
  or service-health diagnostics must go to `npcink-cloud-addon` or Cloud
  service-plane surfaces;
- anything that would create a second runtime, queue, approval path, ability
  registry, workflow registry, or WordPress write owner is blocked.
