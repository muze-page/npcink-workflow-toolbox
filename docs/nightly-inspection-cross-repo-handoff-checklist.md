# Nightly Inspection Cross-Repo Handoff Checklist

Status: active coordination checklist.
Date: 2026-06-17

## Purpose

This checklist coordinates Nightly Site Inspection work across Toolbox, Cloud,
Core, Adapter, Cloud Addon, and Abilities while other implementation sessions
are active.

The goal is to finish the Cloud-first batch execution path without letting any
single repo become the whole automation system. Cloud may execute accepted
runtime work. Toolbox may submit bounded intent and show read-only results.
Core remains the governance intake. Abilities remain the final WordPress
callback layer.

## Current Split

| Repo | Owns | Must not own |
| --- | --- | --- |
| `magick-ai-toolbox` | Operator UI, bounded snapshot submission, quota/status/result display, review-only Morning Brief merge, and operator-triggered Core handoff payloads. | Cloud worker state, scheduler truth, local job tables, retry leases, dead letters, automatic Core proposals, approval, preflight, or WordPress writes. |
| `magick-ai-cloud` | Cloud Batch Runtime execution, run/action state, worker execution, retry/dead-letter detail, entitlement, usage, quota, retention, and diagnostics. | WordPress schedule truth, Core approval truth, WordPress write authority, or a second ability/workflow registry. |
| `magick-ai-cloud-addon` | Signed transport, Cloud credentials, runtime/run/result/stats/entitlement reads. | Local control plane, proposal store, write governance, or Toolbox product UI. |
| `npcink-governance-core` | Proposal intake, approval/rejection, commit preflight, audit, policy checks, and review status. | Cloud runtime execution or WordPress ability callbacks. |
| `npcink-abilities-toolkit` | WordPress ability schemas, metadata, dry-run previews, and final callback implementations. | Approval truth, audit truth, workflow runtime, or Cloud queue state. |
| `magick-ai-adapter` | Authenticated channel behavior, Core proposal relay, capability guidance, execution profile allowlist, and approved execution proxy. | Local workflow runtime, scheduler truth, or unapproved writes. |

## Integration Shape

The intended Pro path is:

```text
Toolbox bounded local snapshot
-> Cloud Addon signed transport
-> Cloud Batch Runtime accepted run
-> Cloud status/result detail
-> Toolbox review-only Morning Brief merge
-> operator clicks Review in Core
-> Core creates a pending review proposal
-> later Core approval/preflight
-> Adapter allowlisted execution
-> Abilities final WordPress callback
```

The Basic/local fallback path stays separate:

```text
disabled-by-default WP-Cron
-> bounded local dry-run preview
-> latest-preview option only
-> operator review
```

The Basic path must not call Cloud, create Core proposals, use Action
Scheduler, create custom tables, or write WordPress content.

## Cloud Result Contract Inputs For Toolbox

Cloud results consumed by Toolbox should provide enough data to render a
reviewable operator summary without raw-payload inspection:

- `run_id`;
- terminal or current `status`;
- `worker_phase` or equivalent runtime phase;
- `execution_kind: nightly_site_inspection`;
- `contract_version`;
- bounded item counts and skipped counts;
- `eligibility_summary`;
- `blocked_items[]`;
- `review_items[]` or equivalent prioritized recommendations;
- `operator_next_action`;
- `retryable`;
- `retry_guidance`;
- quota or usage detail when available;
- `trace_id` or correlation evidence.

Toolbox may merge these fields into the Morning Brief as review context. The
merge must keep `direct_wordpress_content_write=false` and
`core_proposal_created=false`.

## Toolbox Handoff Payload To Core

When an operator explicitly chooses `Review in Core`, Toolbox should build a
bounded handoff packet from the merged Cloud result:

- source `run_id` and Cloud correlation evidence;
- source artifact type and contract version;
- selected review items, not the whole raw Cloud payload;
- local object references needed for human review;
- evidence refs and blocked reasons;
- proposed next actions as review-plan items;
- explicit statement that the packet is pending review and not executable
  until Core accepts it;
- no provider secrets, no raw credentials, and no hidden write authorization.

The handoff should create at most a pending Core review proposal. Toolbox must
not approve, preflight, execute, or auto-create proposals from background
status/result reads.

## Coordination Order

1. Cloud stabilizes the Nightly Site Inspection run/result contract and worker
   behavior in its own branch.
2. Toolbox verifies it can read status/result and render the result as
   review-only.
3. Toolbox and Core agree on the `Review in Core` handoff payload shape.
4. Core accepts the handoff as a pending proposal and keeps not-ready or
   evidence-poor packets fail-closed.
5. Adapter verifies any later execution path requires Core approval,
   commit-preflight, and an explicit execution profile allowlist.
6. Abilities verifies final write callbacks still enforce their schemas and
   dry-run/preview contracts.
7. A real local WordPress plus Cloud worker smoke proves the whole path without
   adding Action Scheduler, local runtime tables, automatic proposal creation,
   or direct WordPress writes.

## Acceptance Gates

Toolbox local gates:

```bash
composer test:all
composer smoke:nightly-inspection-cloud-ui
composer smoke:nightly-inspection-orchestration-boundary
```

Real integration gate, only when Cloud and local WordPress are available:

```bash
cd /Users/muze/gitee/magick-ai-cloud
docker compose -f docker-compose.dev.yml --profile runtime up -d worker

cd /Users/muze/gitee/magick-ai-toolbox
composer smoke:nightly-inspection-cloud-e2e
```

Cross-repo acceptance requires:

- Cloud run reaches a terminal status or exposes clear retry/recovery guidance;
- Toolbox shows Cloud run detail, review summary, and Core handoff direction;
- no server-side Toolbox run table is created;
- no Action Scheduler path is introduced;
- no automatic Core proposal is created by submit/status/result reads;
- any Core handoff is operator-triggered and creates only a pending proposal;
- final writes remain behind Core approval, Core preflight, Adapter execution
  profile allowlist, and Abilities callbacks.

## Stop Conditions

Stop and update a boundary decision before implementing any of these:

- Cloud becomes independent schedule truth for a WordPress site;
- Toolbox creates a local job table, lease store, retry worker, or
  dead-letter processor;
- Toolbox uses Action Scheduler for the current Basic or Pro path;
- Cloud creates Core proposals automatically;
- Cloud or Toolbox approves, preflights, executes, or writes WordPress content;
- any repo adds a second ability registry, workflow registry, approval store,
  or WordPress write owner.

## Current Recommended Next Step

While other sessions are actively changing Cloud/Core/Abilities, keep Toolbox
work limited to this checklist, source-only contract checks, and read-only UI
readiness. Do not modify Cloud worker code, Core proposal endpoints, or
Abilities callbacks from the Toolbox session until the parallel branches are
stable enough for a real integration smoke.
