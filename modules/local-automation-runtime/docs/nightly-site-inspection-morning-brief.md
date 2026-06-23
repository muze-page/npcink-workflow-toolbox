# Nightly Site Inspection / Morning Brief

Status: Phase 1 contract-only task profile.

This profile records how the future local automation runtime may host the
Nightly Site Inspection / Morning Brief job without making Toolbox fixed-flow
buttons into a scheduler, queue, approval path, or WordPress write executor.

## Product Positioning

Use:

- Nightly Site Inspection;
- Morning Brief;
- content quality scoring;
- writing preparation;
- reviewable recommendations.
- Cloud-first entitlement-backed scoring for Pro users;
- WordPress-side fallback preview when Cloud is unavailable or not connected.

Do not use:

- auto-writing;
- hands-free article generation;
- unattended SEO publishing;
- fully automatic WordPress optimization.

## Phase 1 Scope

Phase 1 is contract-only:

- document the task profile;
- validate dry-run replay fixtures;
- validate deterministic rule scoring against fixture snapshots;
- collect bounded local snapshots only when an administrator requests a manual
  preview;
- build manual dry-run replay plans from caller-provided snapshots;
- keep runtime execution disabled;
- keep scheduling disabled;
- keep all WordPress writes disabled.

Phase 1 must not:

- register WP-Cron hooks;
- create Action Scheduler jobs;
- create custom runtime tables;
- scan live posts in the background;
- call Cloud runtime;
- create Core proposals;
- approve Core proposals;
- update post content, metadata, media, taxonomy, comments, or settings.

The Phase 1 snapshot collector may read a bounded set of local published posts,
pages, and image attachments after an administrator requests a manual preview.
It must not register hooks, schedule work, call Cloud, persist results, create
Core proposals, or mutate WordPress.

This administrator preview is classified as Phase 1A Manual Read-Only Preview.
It is a Toolbox-hosted operator UX for producing a dry-run replay sample, not a
runtime execution phase. Scheduled or supervised execution belongs to the
`npcink-local-automation-runtime` implementation boundary.

The Phase 1 scoring core may accept a caller-provided snapshot and return a
`nightly_site_inspection_result.v1` preview. It must not query WordPress, mutate
WordPress, schedule work, call Cloud, or persist results.

The Phase 1 manual planner may wrap that preview in a
`npcink_local_automation_runtime.v1` `dry_run_replay` envelope for operator
review. It may produce preview-only actions with the
`manual_dry_run_preview_only` execution profile. It must not register hooks,
enqueue jobs, persist job state, call Cloud, create Core proposals, or execute
WordPress writes.

## Phase 2 Local Fallback Preview Shape

The Basic/local fallback edition starts with a constrained WP-Cron dry-run
preview:

- `WP-Cron` as the default trigger, disabled by default;
- enable/disable, local run time, post/page scan limit, and media scan limit;
- single latest Morning Brief preview storage in
  `npcink_local_automation_runtime_nightly_inspection_latest_preview`;
- deterministic checks before AI;
- WordPress admin review surface.

It still excludes:

- Cloud calls;
- Cloud scheduler truth;
- Core proposal creation;
- WordPress content writes;
- `Action Scheduler`;
- custom tables, leases, retries, and dead-letter processing.

Future Basic execution beyond this dry-run preview may add:

- server cron guidance for reliable production triggering;
- capped batches;
- manual run;
- richer local Morning Brief storage.

Action Scheduler is not part of the current Basic or Pro plan. It remains a
future local fallback/substrate candidate only if a confirmed local-batch
requirement justifies the added plugin complexity.

The local runtime owner remains `npcink-local-automation-runtime`. Toolbox may
host the UI, but Toolbox buttons must not become the queue or scheduler truth.

## Pro Cloud Runtime Shape

The Pro edition offloads batch analysis to Npcink AI Cloud through the Cloud
Addon and the existing hosted runtime contract. The preferred shape is Cloud
Batch Runtime: Cloud owns run/action state, queue-backed worker execution,
retry, dead-letter, entitlement, usage metering, quota enforcement, observability,
result retention, and concurrency detail. The WordPress plugin only bridges
batch intent, status/result sync, and reviewed Core proposal handoff.

This is Cloud-first, not cloud-only. The local dry-run preview remains available
as a fallback and onboarding aid, but it is not the commercial reliability,
multi-site, quota, retry, or retention path.

Cloud may return:

- content quality explanations;
- content gap classifications;
- refresh opportunities;
- compliance/risk labels;
- internal-link follow-up;
- media follow-up;
- writing preparation metadata based on existing site evidence.

Cloud must not return unattended article drafts, final SEO copy, final FAQ copy,
direct apply payloads, approval tokens, nonces, cookies, credentials, or
WordPress write instructions.

## Dry-Run Replay Shape

The dry-run fixture for this profile uses the generic
`npcink_local_automation_runtime.v1` replay contract and adds a
`task_profile` of `nightly_site_inspection_morning_brief`.

Required posture:

- `core_runtime_execution`: false;
- `background_execution`: false;
- `job.source`: `operator_started`;
- `job.status`: `planned`;
- `acceptance.scheduler_created`: false;
- `acceptance.worker_created`: false;
- `acceptance.core_tables_created`: false;
- `acceptance.lease_store_created`: false;
- `acceptance.dead_letter_processor_created`: false;
- preview actions use `manual_dry_run_preview_only`;
- all candidate actions are `ready`, `waiting`, `skipped`, or `blocked`;
- all final WordPress writes are absent.

## Future Handoff

When execution phases are approved, the sequence must remain:

```text
WP-Cron or manual trigger
-> local dry-run preview or Cloud Batch Runtime
-> deterministic eligibility and scoring
-> optional Cloud hosted analysis
-> local Morning Brief
-> human review
-> Core proposal
-> Core approval
-> Core commit preflight
-> Adapter allowlisted execution
-> WordPress Abilities API callback
```

The future implementation must keep every write behind Core governance.
