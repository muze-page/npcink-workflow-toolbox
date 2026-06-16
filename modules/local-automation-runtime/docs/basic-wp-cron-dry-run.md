# Phase 2 Basic WP-Cron Dry-Run

## Status

Phase 2 Basic WP-Cron Dry-Run is the first scheduled implementation step for
Nightly Site Inspection. It belongs to the `npcink-local-automation-runtime`
owner while the code is bundled in Toolbox for release.

## Scope

The Basic edition uses WP-Cron only to generate a bounded Morning Brief preview
for operator review. It is disabled by default. When enabled, the schedule can
use only:

- enable/disable;
- a daily local-site run time;
- a post/page scan limit;
- a media scan limit.

The cron handler may collect bounded local evidence, build deterministic quality
signals, and overwrite the single latest-preview option
`npcink_local_automation_runtime_nightly_inspection_latest_preview`.

## Non-Goals

Part 3 is not an unattended article writer and not a production runtime job
system. It is no Cloud call, no Core proposal, no WordPress writes, no custom
tables, no custom tables-backed queue, and no Action Scheduler. It must not:

- call Cloud;
- treat Cloud as scheduler truth;
- create a Core proposal;
- approve or execute a Core proposal;
- write WordPress content, metadata, taxonomy, media, or SEO fields;
- use Action Scheduler;
- create custom tables;
- persist a job queue, lease store, retry processor, or dead-letter processor;
- expose REST, admin-post, or Ajax execution routes.

The latest-preview option is a bounded operator review artifact, not runtime job state.

## Contract

The stored preview artifact uses
`nightly_site_inspection_basic_wp_cron_preview.v1`, declares
`runtime_owner: npcink-local-automation-runtime`, and keeps:

- `task_profile: nightly_site_inspection_morning_brief`;
- `mode: wp_cron_dry_run_preview`;
- `trigger: wp_cron`;
- `core_runtime_execution: false`;
- `safety.dry_run: true`;
- `safety.latest_preview_option_only: true`;
- `safety.direct_wordpress_content_write: false`;
- `safety.cloud_called: false`;
- `safety.core_proposal_created: false`;
- `safety.action_scheduler_used: false`;
- `safety.custom_tables_created: false`.

## Advanced Boundary

Advanced/Pro orchestration can be designed later as Cloud-assisted analysis or
Cloud-managed orchestration detail. Cloud must not become a second scheduler
truth, write path, approval store, or runtime state machine. Any supervised or
unattended execution beyond this dry-run preview must remain in the
`npcink-local-automation-runtime` runtime boundary and pass a new ADR/test gate.

Current Pro planning should not introduce plugin-side Action Scheduler. If Pro
needs batch processing, the preferred shape is Cloud Batch Runtime: Cloud owns
run/action state, queue-backed worker execution, retry, dead-letter, entitlement,
and concurrency detail. The WordPress plugin remains a bridge for batch intent,
status display, bounded result sync, and Core proposal handoff. Action Scheduler
is reserved as a future local fallback/substrate candidate only when a confirmed
local-batch requirement justifies the added plugin complexity.
