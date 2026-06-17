# Local Automation Runtime Boundary

Status: Phase 1 contract-only boundary plus Phase 2 Basic WP-Cron dry-run.

The local automation runtime is independently owned as
`npcink-local-automation-runtime` and may be bundled in Toolbox for release as
`modules/local-automation-runtime/`.

Toolbox may host the module and later expose an operator console, but Toolbox
fixed-flow buttons must not become the runtime state machine, scheduler, lease
manager, retry processor, dead-letter processor, approval path, or final write
executor.

## Phase 1 Allowed

- dry-run replay fixture validation;
- contract docs;
- no-write smoke tests;
- static contract checks.

Phase 1 may also define governed batch review-set contracts, including
`npcink_local_automation_media_conversion_review_set.v1`, when the contract is
review-only and proves that media conversion candidates are scoped, eligible or
blocked, and routed toward Core-governed proposal handoff without local
execution.

## Phase 1A Manual Read-Only Preview

The Toolbox Start panel may expose a manual read-only Morning Brief preview.
This is not a runtime execution phase. It is an operator UX that may collect a
bounded local snapshot only after a present administrator clicks the preview
entry, then render a `manual_dry_run_preview_only` replay sample.

Phase 1A must stay outside runtime ownership:

- no scheduler or worker;
- no runtime job table, lease store, retry state, or dead-letter recovery;
- no WP-Cron hook or Action Scheduler job;
- no REST execution route, admin-post execution route, or Ajax execution
  endpoint;
- no Cloud call, Core proposal, Adapter execution, persistence, or WordPress
  write.

The first implementation that adds scheduled or supervised execution belongs
to the `npcink-local-automation-runtime` runtime implementation boundary, not
to Toolbox fixed buttons.

## Phase 2 Basic WP-Cron Dry-Run

Product posture is Cloud-first, not cloud-only. Pro Cloud Batch Runtime is the
primary commercial execution path for reliable scoring, entitlement, usage
metering, queue-backed execution, retry, observability, and result retention.
The Basic WP-Cron path is a WordPress-side Local Fallback Preview and onboarding
aid, not a second Pro scheduler.

The Basic/local fallback edition may register one WP-Cron hook:
`npcink_local_automation_runtime_nightly_inspection_dry_run`.

This is `npcink-local-automation-runtime` runtime implementation work while the
code remains bundled in Toolbox. It must stay disabled by default and may only
generate a dry-run Morning Brief preview for operator review. The handler may
overwrite the single latest-preview option
`npcink_local_automation_runtime_nightly_inspection_latest_preview`; that option
is a bounded review artifact, not runtime job state.

Phase 2 Basic remains blocked from:

- Cloud calls or Cloud scheduler truth;
- Core proposal creation, approval, preflight, or execution;
- WordPress content, metadata, taxonomy, media, SEO, or settings writes beyond
  the schedule settings and latest-preview option;
- Action Scheduler;
- custom runtime tables;
- lease stores;
- retry processors;
- dead-letter processors;
- REST, admin-post, or Ajax execution routes.

## Pro Cloud Batch Boundary

Current Pro planning should use Cloud Batch Runtime instead of plugin-side
Action Scheduler. Cloud may own run/action state, queue-backed worker execution,
retry, dead-letter, entitlement, usage, quota enforcement, result retention, and
concurrency detail. The WordPress plugin may only bridge batch intent, poll or
receive bounded status/results, and hand reviewed outputs to Core proposal governance.

Action Scheduler is deferred as a future local fallback/substrate candidate. It
should not be introduced unless a confirmed local-batch requirement exists, such
as offline local execution, long-running WordPress-local work, or cloud-unavailable
fallback. Even then, it must not become a second scheduler truth beside Cloud
Batch Runtime.

## Phase 1 Blocked

- registering a cron schedule or Action Scheduler job;
- creating runtime custom tables;
- acquiring leases;
- retrying action execution;
- dead-letter processing;
- approving Core proposals;
- calling Adapter approve-and-execute;
- calling WordPress write abilities;
- publishing, importing media, mutating SEO, or changing settings.
- creating media conversion queues, retry loops, approval shortcuts, or direct
  media replacement paths.

## Handoff Rule

Future execution phases must use this sequence:

```text
runtime job
-> Core proposal
-> Core approval
-> Core commit preflight
-> Adapter allowlisted execution profile
-> WordPress Abilities API callback
-> Core execution-result record
```
