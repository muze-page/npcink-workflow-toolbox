# ADR-004: Bundle Local Automation Runtime As An Isolated Module

## Status

Accepted

## Date

2026-06-15

## Context

Core ADR-007 names `npcink-local-automation-runtime` as the dedicated owner for
future unattended batch automation. The owner should be independently developed
and independently testable, but operators should not necessarily need to
install a separate plugin when Toolbox can provide the release shell.

Toolbox already owns operator-facing AI tools and fixed buttons. It must not
quietly become a workflow runtime, queue owner, scheduler, retry system, or
final WordPress write executor.

## Decision

Toolbox may bundle the future local automation runtime as
`modules/local-automation-runtime/` when the module keeps an isolated identity:

- namespace: `Npcink\LocalAutomationRuntime`;
- owner id: `npcink-local-automation-runtime`;
- contract version: `npcink_local_automation_runtime.v1`;
- independent boundary docs;
- independent tests;
- independent kill switch and health status in later phases.

Phase 1 is contract-only. The bundled module may include a dry-run replay
fixture and a validator, but it must not register hooks, create custom runtime
tables, add REST routes, schedule workers, acquire leases, retry actions,
process dead letters, approve Core proposals, call Adapter execution routes, or
write WordPress data.
The Phase 1 validator and smoke tests must fail closed when replay data claims
runtime execution, scheduler creation, worker creation, leases, direct
WordPress writes, execution-status actions, or inconsistent blocked counts.

### Phase 1A Manual Read-Only Preview Classification

Toolbox may host a manual read-only preview entry for Nightly Site Inspection
while preserving the Core ADR-007 runtime owner decision. This preview is not a
runtime execution phase. It is a Toolbox operator UX that reads bounded local
public-content evidence only after a present administrator clicks the preview
entry, then produces a Morning Brief preview and dry-run replay sample.

This classification is allowed only while all of the following remain true:

- no scheduler, worker, lease, retry, dead-letter, or runtime job state is
  created;
- no WP-Cron hook, Action Scheduler job, REST execution route, admin-post
  execution route, or Ajax execution endpoint is registered;
- no Cloud call, Core proposal, Adapter execution, persistence, or WordPress
  write occurs;
- preview actions remain `manual_dry_run_preview_only`;
- the owner id and contract remain `npcink-local-automation-runtime` and
  `npcink_local_automation_runtime.v1`.

The first implementation that adds WP-Cron, Action Scheduler, job storage,
leases, retry state, dead-letter recovery, or unattended policy must be treated
as `npcink-local-automation-runtime` runtime implementation work, not Toolbox
fixed-button work.

Phase 2 Basic WP-Cron Dry-Run is therefore allowed only inside the
`modules/local-automation-runtime/` boundary. The allowed first step is one
disabled-by-default WP-Cron hook that overwrites a single latest-preview option
for operator review. It must not call Cloud, create Core proposals, use Action
Scheduler, create custom tables, acquire leases, retry actions, process dead
letters, expose execution routes, or write WordPress content.

Current Pro planning will not introduce plugin-side Action Scheduler. Pro batch
processing should be modeled as Cloud Batch Runtime: Cloud owns run/action
state, queue-backed worker execution, retry, dead-letter, entitlement, usage,
and concurrency detail. The WordPress plugin remains a bridge for batch intent,
status/result sync, and reviewed Core proposal handoff. Action Scheduler is
reserved as a future local fallback/substrate candidate only if a confirmed
local-batch requirement justifies the added plugin complexity.

Toolbox may later host the operator console for this runtime, but Toolbox
fixed-flow buttons must not become the runtime state machine, scheduler, lease
manager, retry processor, dead-letter processor, approval path, or final write
executor.

## Alternatives Considered

### Publish only as a separate plugin

Pros:

- Clear plugin boundary.
- Lower risk of Toolbox/runtime coupling.

Cons:

- More install and support overhead for operators.
- Product UX can feel fragmented.

Rejected as a product requirement. Separate development remains useful, but
release bundling is acceptable when module boundaries are enforced.

### Put runtime logic directly into existing Toolbox flows

Pros:

- Fewer directories and fewer concepts.

Cons:

- Fixed buttons would become hidden workflow state.
- Batch review sets could turn into execution queues.
- Toolbox would own failure recovery and unattended authority by accident.

Rejected.

### Put runtime in Abilities

Rejected. Abilities owns ability definitions, schemas, callbacks, metadata, and
dry-run previews. It must not own jobs, scheduling, leases, retries, or
approval truth.

## Consequences

Toolbox can ship the Phase 1 skeleton now without enabling background
automation. Toolbox fixed-flow buttons must stay outside the runtime state
machine. Later phases must keep runtime code isolated in
`modules/local-automation-runtime/` and must pass explicit boundary tests before
adding supervised or scheduled execution.
