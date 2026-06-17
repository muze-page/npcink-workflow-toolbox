# Local Automation Runtime Module

Status: Phase 1 bundled skeleton plus Phase 2 Basic WP-Cron local fallback preview.

This module carries the future `npcink-local-automation-runtime` contract inside
the Toolbox release package without making Toolbox a runtime owner.

Current scope:

- validate `npcink_local_automation_runtime.v1` dry-run replay fixtures;
- validate deterministic Nightly Site Inspection scoring against fixture
  snapshots;
- collect a bounded read-only WordPress snapshot for administrator-started
  previews;
- build a manual Nightly Site Inspection dry-run replay from a caller-provided
  snapshot;
- optionally register the disabled-by-default Phase 2 Basic WP-Cron dry-run
  preview hook;
- define the first governed local batch operation review-set contract for media
  format conversion;
- keep runtime execution disabled;
- provide a stable module path for future isolated development:
  `modules/local-automation-runtime/`.

Current non-scope:

- no WordPress hooks outside the named Basic WP-Cron dry-run hook;
- no REST routes;
- no admin execution buttons;
- no scheduler outside the named Basic WP-Cron dry-run hook;
- no worker;
- no job table;
- no lease store;
- no retry or dead-letter processor;
- no unattended approval;
- no final WordPress writes.

The first non-nightly local batch target is
`npcink_local_automation_media_conversion_review_set.v1`. It is a bounded
operator review-set contract for image format conversion candidates. It may
reference the existing media derivative preview and Core proposal handoff, but
it does not create a queue, schedule work, create Core proposals, approve,
preflight, execute, replace media, or write metadata.

The module namespace is `Npcink\LocalAutomationRuntime`. If later phases add a
runtime console or worker, they must keep this module isolated from Toolbox
fixed-flow buttons and must continue to use Core proposal approval and commit
preflight before any WordPress write.

Nightly Site Inspection Phase 1A is a read-only collector, builder, and
manual-planner preview. It is not a runtime execution phase. The administrator
preview collects a bounded snapshot of local public content on demand, returns a
`nightly_site_inspection_result.v1` preview, and can wrap that preview in a
`npcink_local_automation_runtime.v1` dry-run replay for operator review. It does
not call Cloud, register cron, enqueue Action Scheduler jobs, persist results,
create Core proposals, write WordPress data, or generate article/SEO copy.
Scheduled or supervised execution belongs to the future
`npcink-local-automation-runtime` runtime implementation boundary, not Toolbox
fixed buttons.

Phase 2 Basic WP-Cron Dry-Run is the first scheduled WordPress-side fallback
preview step. It remains disabled by default, lets the operator configure
enable/disable, local run time, and scan limits, and may only overwrite the
single latest-preview option
`npcink_local_automation_runtime_nightly_inspection_latest_preview`. It must not
call Cloud, create Core proposals, use Action Scheduler, create custom tables,
acquire leases, retry actions, process dead letters, or write WordPress content.

Product positioning is Cloud-first, not cloud-only. Pro Cloud Batch Runtime is
the commercial execution path for reliable scoring, entitlement, usage metering,
queue-backed execution, retry, observability, and result retention. This module
keeps the local WP-Cron path as fallback preview and onboarding support, not a
parallel Pro scheduler.
Toolbox may read Cloud `pro_cloud_runtime` entitlement as a display snapshot
for the Pro controls and may disable new Cloud submissions when Cloud reports
exhausted Nightly Site Inspection quota. That does not make this module a
local entitlement ledger, billing owner, queue, retry processor, or scheduler
truth.
