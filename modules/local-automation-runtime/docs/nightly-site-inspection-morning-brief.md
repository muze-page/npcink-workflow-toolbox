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

Do not use:

- auto-writing;
- hands-free article generation;
- unattended SEO publishing;
- fully automatic WordPress optimization.

## Phase 1 Scope

Phase 1 is contract-only:

- document the task profile;
- validate dry-run replay fixtures;
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

## Future Basic Edition Shape

The Basic edition should later use:

- `WP-Cron` as the default trigger;
- server cron guidance for reliable production triggering;
- `Action Scheduler` as the local batch execution substrate;
- deterministic checks before AI;
- capped batches;
- manual run;
- local Morning Brief storage;
- WordPress admin review surface.

The local runtime owner remains `npcink-local-automation-runtime`. Toolbox may
host the UI, but Toolbox buttons must not become the queue or scheduler truth.

## Future Pro Edition Shape

The Pro edition may later offload heavy analysis to Magick AI Cloud through the
Cloud Addon and the existing hosted runtime contract.

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
- `acceptance.scheduler_created`: false;
- `acceptance.worker_created`: false;
- `acceptance.core_tables_created`: false;
- `acceptance.dead_letter_processor_created`: false;
- all candidate actions are `ready`, `waiting`, `skipped`, or `blocked`;
- all final WordPress writes are absent.

## Future Handoff

When execution phases are approved, the sequence must remain:

```text
WP-Cron or manual trigger
-> Action Scheduler local batches
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
