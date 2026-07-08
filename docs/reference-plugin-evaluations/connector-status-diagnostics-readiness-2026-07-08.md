# Connector Status Diagnostics Readiness Evaluation - 2026-07-08

Status: accepted as second checklist trial.

## Scope

Reference capability: mature connector, analytics, mail, and health-check
plugins often show compact connection status, setup readiness, diagnostic
detail, and next-action guidance.

Reference source: existing local reference-learning notes for connector
status, service health, setup completeness, and troubleshooting patterns. This
record evaluates the capability pattern only; it does not copy the reference
plugins' control-plane, account, billing, log, or provider ownership model.

Evaluation date: 2026-07-08.

Evaluator: AI development session.

Current Npcink question: should Toolbox borrow connector/status/diagnostics
readiness patterns, and if yes, how should Toolbox, Cloud Addon, Cloud, and Core
split ownership without creating a second control plane?

## Observed Useful Pattern

The useful pattern is not a full connector console. The useful pattern is a
small readiness surface that tells the operator whether a capability is usable,
why it is blocked, where the owner surface lives, and what safe next action is
available.

This is valuable for Toolbox because fixed workflow buttons need clear blocked
states: Cloud disconnected, Cloud runtime unavailable, Site Knowledge not ready,
connector helper missing, or Core handoff unavailable. Operators should see the
state and next action without Toolbox becoming the settings authority, provider
account page, billing page, request log, runtime run store, or approval store.

## Capability Breakdown

| Question | Answer |
| --- | --- |
| Input | Existing local capability status, Cloud Addon helper availability, Cloud-returned entitlement or runtime status summaries, feature flags, current operator context, and bounded diagnostic codes. Inputs must be non-secret and may not include stored provider keys, raw prompts, raw outputs, or unredacted request logs. |
| Output | Readiness labels, blocked reasons, owner labels, next-action links, diagnostic notes, and optional copyable support facts. Output is status/detail only, not a write packet or account console. |
| User action | Inspect status, open the owning settings surface, retry an explicitly supported status refresh, copy bounded diagnostics, or continue to an existing suggestion-only workflow when ready. |
| Write action | No WordPress content, media, SEO, taxonomy, approval, billing, provider log, run history, or Cloud control-plane write is performed by Toolbox. |
| Runtime dependency | Status may read existing local projections or Cloud/Cloud Addon summaries. It must not add local workers, queues, leases, schedulers, runtime registries, or run recovery workspaces. |
| Data storage | No new Toolbox storage. Existing settings/status projections may be read. Cloud Addon may keep its bounded connector status/cache under its own signed-connector boundary. Cloud owns hosted runtime/detail, usage, entitlement, and diagnostics storage. |
| Permission model | Toolbox display remains operator/admin gated. Cloud Addon owns signed connector verification and transport. Cloud owns hosted runtime/detail permissions. Core owns proposal, approval, preflight, and audit truth for write-like handoffs. |

## Repository Ownership

Primary owner: `npcink-workflow-toolbox` owns operator-facing readiness rows,
blocked-state language, owner labels, and safe next-action hints for fixed
workflow surfaces.

Supporting owners:

- `npcink-cloud-addon` owns the signed WordPress-to-Cloud connector, local
  Cloud connection settings, bounded connector verification, and read-only
  Cloud status/detail projection.
- `npcink-ai-cloud` owns hosted runtime/detail, entitlement, usage, provider
  execution, health diagnostics, and retained service-plane evidence.
- `npcink-governance-core` owns proposal, approval, preflight, and audit truth
  when a status result leads to a write-like handoff.
- `npcink-abilities-toolkit` owns reusable WordPress ability contracts that a
  ready workflow may later call.
- `npcink-ai-client-adapter` owns thin channel/execution profiles for approved
  Core paths.

Rejected owners:

- Toolbox must not own Cloud credentials, billing, quota, request logs,
  provider routing, model routing, or hosted diagnostic storage.
- Toolbox must not own a connector approval store, runtime run table, retry
  queue, scheduler, or recovery workspace.
- Cloud Addon must not become a second workflow registry, approval store, or
  WordPress write authority.
- Cloud must not become a WordPress write owner or second local control plane.

## Boundary Result

| Boundary check | Result |
| --- | --- |
| Adds second ability registry | no |
| Adds second workflow registry | no |
| Adds approval store | no |
| Adds local runtime queue | no |
| Adds provider billing/log owner | no |
| Bypasses Core governed write path | no |
| Adds direct WordPress write | no |

Additional red lines:

- Do not add a generic diagnostics route that proxies arbitrary Cloud requests.
- Do not expose stored secrets, split credentials, raw prompts, raw outputs, or
  provider request logs in Toolbox.
- Do not add a local run history or retry workspace for Cloud runtime failures.
- Do not turn Toolbox status rows into a billing, quota, key-rotation, provider
  picker, or model-router surface.
- Do not create proposals automatically from a blocked or failed diagnostic
  state.

## Decision

Decision: Borrow as suggestion-only Toolbox surface.

Also accepted as a documentation/static-contract pattern: future Toolbox
readiness rows may borrow the compact status, blocked reason, owner label, and
safe next-action shape, but only as a projection of existing owner surfaces.

The safe split is:

```text
Toolbox readiness row / blocked state / next-action hint
-> Cloud Addon signed connector status when the fact is local-to-Cloud transport
-> Cloud hosted runtime/detail when the fact is service-plane runtime evidence
-> Core proposal/preflight/audit only when an operator later chooses a governed handoff
```

Reject any implementation that makes Toolbox the source of truth for provider
accounts, billing, request logs, runtime runs, retries, connector approvals, or
WordPress writes.

## Verification Gate

Minimum gate for this record:

```bash
php tests/run.php --quiet --filter='Reference plugin evaluation'
```

Required gate for this documentation/static-contract slice:

```bash
composer test:all
```

Broader gate only if this becomes a multi-repo implementation:

```bash
composer quality:matrix:run
```

No browser smoke, WordPress activation smoke, or Cloud smoke is needed for this
record because it adds no product UI, route, ability, runtime, connector
setting, diagnostic endpoint, or write behavior.

## Next Step

Next action: use this record when evaluating future connector/status
improvements. If implementation is proposed, start from existing Toolbox
readiness rows and Cloud Addon status projections rather than creating a new
diagnostics console.

Stop condition: stop and write a boundary note if the proposed next step
requires local runtime state, arbitrary Cloud proxying, provider account
management, billing/quota truth, request-log ownership, automatic proposals, or
direct WordPress writes.

Rollback: revert this record and its static-test/index references; no runtime
state exists.
