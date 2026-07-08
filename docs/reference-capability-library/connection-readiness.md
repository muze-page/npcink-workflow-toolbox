# Connection Readiness

Status: seed capability pattern.

## Problem

Connection-oriented workflows fail badly when the operator cannot tell whether
a service is ready, disconnected, partially set up, owned by another surface, or
temporarily unavailable.

Npcink needs a reusable pattern for status rows that explain:

- what capability is being checked;
- whether it is usable now;
- who owns the missing setup or runtime fact;
- why it is blocked;
- what the safe next action is.

The pattern must improve operator clarity without making Toolbox a second
Cloud/provider control plane.

## Mature Reference Patterns

- Site Kit by Google: Settings -> Connected Services distinguishes connected
  modules from modules that need setup, using status language such as
  `Connected` and `Complete setup`.
- Connector/status diagnostics plugins: compact rows combine owner labels,
  blocked reasons, diagnostic notes, and next-action links.
- Health/setup plugins: setup and troubleshooting states are most useful when
  they point to the owning surface rather than hiding the problem behind a
  generic error.

Source evaluation records:

- [Site Kit connection readiness evaluation](../reference-plugin-evaluations/site-kit-connection-readiness-2026-07-08.md)
- [Connector status diagnostics readiness evaluation](../reference-plugin-evaluations/connector-status-diagnostics-readiness-2026-07-08.md)

## Borrowable Patterns

- `status_label`: short operator-facing state such as `ready`,
  `complete_setup`, `disconnected`, `unavailable`, or `blocked_by_owner`.
- `owner_label`: the surface that owns the missing fact or action, such as
  Toolbox, Cloud Addon, Cloud, Core, Toolkit, or Adapter.
- `blocked_reason`: one sentence that explains why the capability cannot
  proceed.
- `next_action`: one safe action, such as open Cloud Addon settings, retry a
  supported status refresh, open Core review, complete setup, or continue.
- `support_facts`: bounded non-secret diagnostics that can be copied or shown
  without exposing provider credentials, raw prompts, raw outputs, request
  logs, or billing detail.

## Npcink Mapping

| Repository | Role |
| --- | --- |
| `npcink-workflow-toolbox` | Shows suggestion-only readiness rows, blocked states, owner labels, and next-action hints for fixed workflow surfaces. It may prepare a Core-ready handoff preview only after the capability is ready. |
| `npcink-cloud-addon` | Owns Cloud connection setup, signed transport verification, connector availability, and bounded local status projection. |
| `npcink-ai-cloud` | Owns hosted runtime/detail, entitlement, usage evidence, provider execution, service health, artifacts, and durable diagnostics evidence. |
| `npcink-governance-core` | Owns proposal, approval, preflight, operation classification, policy, and audit truth for write-like outcomes. |
| `npcink-abilities-toolkit` | Owns reusable ability contracts and dry-run/write callback definitions that ready workflows may later call. |
| `npcink-ai-client-adapter` | Owns channel/execution profiles and status forwarding for approved Core paths. |
| `wp-magick-toolbox` | Provides historical wording and compatibility reference only. |

## Recommended Implementation Shape

Start with a static vocabulary and projection shape before changing UI:

```text
capability_label
status_label
owner_label
blocked_reason
next_action
support_facts
write_posture=suggestion_only
```

Recommended first product slice:

```text
existing Toolbox readiness/status row
-> clearer status label
-> explicit owner label
-> one blocked reason
-> one next action
```

Do not create a new screen, connected-services registry, setup wizard, Cloud
settings clone, or diagnostics console until a separate product decision proves
that the existing surfaces cannot carry the pattern.

## Selection Rubric Result

```text
boundary_fit: pass
repo_owner: npcink-workflow-toolbox
borrow_shape: suggestion_only_surface
user_value: high
complexity: low
risk: low
recommended_result: candidate
required_gate: composer_test_all
review_required: shortlist_only
```

Rationale: the pattern improves operator clarity on existing readiness/status
surfaces without adding provider setup ownership, runtime queues, approval
storage, or a second WordPress write path. It is a candidate rather than an
automatic implementation because each touched readiness row still needs a
specific owner and UI surface check.

## Do Not Borrow

Do not borrow or recreate:

- Google OAuth or third-party account linking inside Toolbox;
- provider setup ownership in Toolbox;
- generic connected-services registry;
- provider billing, quota, request-log, key-rotation, prompt-router, or
  model-router ownership;
- local runtime queues, retry workers, scheduler truth, run history, or recovery
  workspaces;
- generic Cloud proxy or arbitrary diagnostics route;
- automatic Core proposal creation from disconnected, incomplete, or failed
  readiness states;
- direct WordPress writes.

## Security And Performance Notes

- Readiness output must be non-secret by default.
- Do not expose provider credentials, split credentials, bearer tokens, API
  keys, raw prompts, raw outputs, raw diagnostics payloads, request logs,
  billing records, quota internals, or private site data in Toolbox rows.
- Keep status reads bounded and synchronous when they use local projections.
- Hosted runtime/detail checks belong to Cloud or Cloud Addon; Toolbox should
  consume projections instead of polling provider services directly.
- Avoid adding persistent local status stores. Existing options/projections may
  be read when already owned by the target repository.
- A failing readiness row should block the action cleanly; it should not retry
  indefinitely, enqueue background work, create proposals, or trigger writes.

## Verification Gates

| Change type | Gate |
| --- | --- |
| Documentation/library update only | `php tests/run.php --quiet --filter='Reference capability library'` and `composer test:all` |
| Toolbox readiness/status copy or projection shape | `composer test:all` plus the narrow UI/static smoke that owns the touched surface |
| Cloud Addon connection/status projection | Cloud Addon `composer run test:all` |
| Cloud runtime/detail contract | Cloud service contract tests and the relevant transport smoke |
| Cross-repo implementation | `composer quality:matrix:run` from the orchestration repo after each repo passes its local gate |

Passing a gate does not expand ownership. It only proves the selected boundary
was preserved.

## Sources

- [Site Kit connection readiness evaluation](../reference-plugin-evaluations/site-kit-connection-readiness-2026-07-08.md)
- [Connector status diagnostics readiness evaluation](../reference-plugin-evaluations/connector-status-diagnostics-readiness-2026-07-08.md)
- [Reference Plugin Evaluation Checklist](../reference-plugin-evaluation-checklist.md)
