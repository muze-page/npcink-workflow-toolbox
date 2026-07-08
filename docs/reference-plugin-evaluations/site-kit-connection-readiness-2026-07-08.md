# Site Kit Connection Readiness Evaluation - 2026-07-08

Status: accepted as third checklist trial.

## Scope

Reference capability: Site Kit by Google shows a WordPress operator whether
Google-backed services are connected, which modules still need setup, and where
the next setup or troubleshooting action belongs.

Reference sources:

- [Site Kit documentation: Install Site Kit](https://sitekit.withgoogle.com/documentation/getting-started/install/)
- [Site Kit documentation: Troubleshooting setup](https://sitekit.withgoogle.com/documentation/troubleshooting/setup/)
- [WordPress.org plugin page: Site Kit by Google](https://wordpress.org/plugins/google-site-kit/)
- [Site Kit GitHub issue 12016: Settings > Connected Services module states](https://github.com/google/site-kit-wp/issues/12016)

This record evaluates the connection/readiness pattern only. It does not copy
Site Kit's Google account connection, OAuth, analytics product surface, module
ownership, API ownership, or troubleshooting control-plane model.

Evaluation date: 2026-07-08.

Evaluator: AI development session.

Current Npcink question: should Toolbox borrow Site Kit's connected-service
readiness pattern, and if yes, how should Toolbox, Cloud Addon, Cloud, and Core
split ownership without making Toolbox a second Cloud/provider control plane?

## Observed Useful Pattern

The useful pattern is not the Google service suite. The useful pattern is a
service-readiness row that separates three things:

- the human-readable service label;
- the current connection/setup state;
- the next safe action, such as connect, complete setup, troubleshoot, or open
  the owning settings surface.

The Site Kit issue records a compact Settings -> Connected Services direction:
connected modules show `Connected`, while modules that still need work show
`Complete setup`. That distinction is useful for Toolbox readiness rows because
operators need to understand whether a fixed workflow is ready, blocked by a
missing connector, blocked by Cloud runtime/detail, or waiting on a separate
owner surface.

## Capability Breakdown

| Question | Answer |
| --- | --- |
| Input | Existing service/module registration, current account or connector state, setup progress, service availability, and bounded troubleshooting facts. For Npcink, the equivalent input is Cloud Addon connection status, Cloud entitlement/runtime status, existing Toolbox readiness metadata, and Core handoff availability. |
| Output | A compact readiness row: service label, status label, owner label, blocked reason, and next-action target. Output is status/detail only, not a provider account console or write authorization packet. |
| User action | Inspect whether a capability is connected, complete a missing setup step in the owner surface, open troubleshooting docs/settings, or continue to a suggestion-only Toolbox workflow when ready. |
| Write action | Site Kit may perform account/module setup for its own services. Npcink Toolbox must not borrow that write/control-plane behavior; it may only display readiness and route the operator to the owner surface. |
| Runtime dependency | No new Toolbox runtime is required. Readiness should use existing local metadata, Cloud Addon signed connector projection, Cloud service detail, or Core handoff availability. |
| Data storage | No new Toolbox storage. Site Kit's own account/module state is not a model for Toolbox. Npcink Cloud Addon owns bounded connector settings/status; Cloud owns hosted service/runtime/detail; Core owns proposal/audit truth. |
| Permission model | Toolbox display remains operator/admin gated. Cloud Addon gates Cloud connection and signed transport. Cloud gates hosted service/runtime detail. Core gates any governed write-like handoff. |

## Repository Ownership

Primary owner: `npcink-workflow-toolbox` may own the operator-facing readiness
row, blocked-state language, owner label, and safe next-action hint for fixed
workflow surfaces.

Supporting owners:

- `npcink-cloud-addon` owns Cloud connection setup, signed transport,
  verification state, and local read-only Cloud status projection.
- `npcink-ai-cloud` owns hosted service/runtime/detail, entitlement, service
  health, usage evidence, and provider execution.
- `npcink-governance-core` owns proposal, approval, preflight, and audit truth
  for any write-like outcome that starts after the operator leaves the readiness
  row.
- `npcink-abilities-toolkit` owns reusable WordPress ability contracts that a
  ready workflow may later call.
- `npcink-ai-client-adapter` owns thin channel/execution profiles for approved
  Core paths.

Rejected owners:

- Toolbox must not own Google-style account connection, Cloud account linking,
  OAuth, provider setup, key rotation, billing, quota, request logs, service
  health storage, or module control-plane truth.
- Toolbox must not own a generic connected-services registry, second workflow
  registry, approval store, runtime queue, retry workspace, or diagnostic
  control plane.
- Cloud Addon must not become a WordPress write authority or approval store.
- Cloud must not become a second WordPress control plane.

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

- Do not add Site Kit-style Google OAuth, account linking, or product analytics
  surfaces to Toolbox.
- Do not create a generic connected-services registry in Toolbox.
- Do not turn readiness rows into a Cloud Addon settings clone or Cloud service
  console.
- Do not expose provider credentials, raw diagnostics, raw prompts, raw outputs,
  billing/quota records, or request logs in Toolbox.
- Do not automatically create Core proposals from a disconnected or incomplete
  service state.

## Decision

Decision: Borrow as suggestion-only Toolbox surface.

The borrowable shape is:

```text
Capability label
-> status label such as ready / disconnected / complete setup / unavailable
-> owner label such as Toolbox / Cloud Addon / Cloud / Core
-> blocked reason
-> next safe action
```

The safe Npcink split is:

```text
Toolbox readiness row and next-action hint
-> Cloud Addon connection/setup/status when the fact is local-to-Cloud transport
-> Cloud hosted runtime/detail when the fact is service-plane readiness
-> Core proposal/preflight/audit only after an operator chooses a governed handoff
```

Reject any implementation that turns Toolbox into a Site Kit-style account,
service, analytics, billing, request-log, runtime, approval, or WordPress write
control plane.

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

No browser smoke, WordPress activation smoke, Site Kit install, Google API
setup, OAuth test, or Cloud smoke is needed for this record because it adds no
product UI, route, ability, runtime, connector setting, provider integration,
diagnostic endpoint, or write behavior.

## Next Step

Next action: keep this as a learning record. If a future implementation is
proposed, start with one existing Toolbox readiness row and make it clearer
about status, owner, blocked reason, and next action. Do not build a new
connected-services screen first.

Stop condition: stop and write a boundary note if the proposed next step
requires account linking, OAuth, provider setup ownership, billing/quota truth,
request-log ownership, service health storage, local runtime state, automatic
proposal creation, or direct WordPress writes in Toolbox.

Rollback: revert this record and its static-test/index references; no runtime
state exists.
