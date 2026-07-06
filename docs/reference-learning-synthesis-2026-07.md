# Reference Learning Synthesis - 2026-07

Status: active planning guardrail for cross-repo reference learning.

Question: across the current five-project Npcink/Magick split, which mature
plugin patterns should we learn from, and which patterns would push the local
projects into the wrong ownership boundary?

Short answer: yes, there are useful mature patterns to borrow. The next stage
should borrow clarity, evidence, status, onboarding, diagnostics, and review
affordances from established plugins. It should not borrow their product
ownership, workflow runtime, approval truth, provider control plane, or direct
WordPress write authority.

## Input Notes

This synthesis consolidates these reference notes:

| Project | Reference note |
| --- | --- |
| `npcink-governance-core` | `docs/reference-plugin-benchmark.md`, `docs/reference-plugin-deep-dive-2026-07-06.md`, `docs/core-admin-reference-notes-2026-07.md` |
| `npcink-abilities-toolkit` | `docs/ability-metadata-reference-notes-2026-07.md` |
| `npcink-ai-client-adapter` | `docs/adapter-onboarding-reference-notes-2026-07.md` |
| `npcink-workflow-toolbox` | `docs/toolbox-fixed-button-reference-notes-2026-07.md` |
| `npcink-cloud-addon` | `docs/cloud-addon-reference-notes-2026-07.md` |
| `npcink-ai-cloud` | `docs/cloud-runtime-reference-notes-2026-07.md` |

## Shared Lessons To Borrow

Borrow these patterns because they make existing Npcink surfaces easier to
trust without changing product ownership:

- visible source, evidence, and confidence labels before asking an operator to
  trust a recommendation;
- explicit owner labels such as Toolbox, Toolkit, Core, Adapter, Cloud Addon,
  and Cloud;
- status groups that distinguish ready, needs review, blocked, unavailable,
  handoffable, and failed;
- first-class blocked states that name the missing dependency and the next
  owner, rather than showing generic errors;
- checklist-style summaries where the underlying checks are inspectable;
- narrow test actions tied to a named contract, not generic "test anything"
  diagnostics;
- correlation ids, run ids, and timeline rows for runtime/detail surfaces;
- separation between summary, detail, audit, usage, logs, and operator notes;
- stable schema/category/permission metadata for abilities;
- manual copy, open, review, and handoff actions that leave final writes under
  the existing governance path.

## Shared Patterns Not To Borrow

Do not import these mature-plugin patterns into the local project family:

- workflow builders, trigger/action marketplaces, queues, retries, leases, or
  scheduler truth;
- second proposal, approval, audit, or final-write stores outside Core;
- generic AI chat, prompt playgrounds, model pickers, provider keys, request
  logs, quota management, or billing administration;
- SEO suite ownership for titles, descriptions, schema, redirects, sitemaps,
  keyword systems, or direct SEO metadata writes;
- Cloud-side WordPress publish authority or local plugin-side Cloud control
  planes;
- generic connector approval UIs that bypass Adapter/Core contracts;
- raw payload search, permanent prompt/result retention, or broad observability
  platforms;
- Site Knowledge lifecycle ownership in Toolbox or Cloud Addon when the current
  surface only needs suggestion/detail or status display.

## Cross-Repo Application Matrix

| Project | Borrow from mature plugins | Do not borrow | Next useful audit |
| --- | --- | --- | --- |
| `npcink-governance-core` | Review queue clarity, proposal detail evidence, audit event shape, bounded status transitions. | Workflow runtime, ability definitions, model/provider settings, connector approval, direct write execution. | Proposal detail and admin queue should show source, status, preflight, audit, and rejection reasons clearly. |
| `npcink-abilities-toolkit` | Stable `namespace/name`, categories, labels, descriptions, JSON schemas, permission callbacks, dry-run metadata. | Approval truth, audit truth, prompt/model routing, provider credentials, workflow runtime, MCP gateway policy. | Ability metadata should make risk, write posture, required context, and preview behavior inspectable. |
| `npcink-ai-client-adapter` | Connection manifest clarity, authentication posture, payload discipline, operator feedback, correlation ids. | Recipe builder ownership, trigger/action marketplace, run queues, generic approve/reject proxy, arbitrary final execution. | OpenClaw onboarding should expose connection status and payload contract without becoming a workflow engine. |
| `npcink-workflow-toolbox` | Checklist readiness, source/evidence labels, issue grouping, blocked states, owner/runtime labels, copy/open/review actions. | Generic SEO suite, generic AI suite, provider settings, local queues, automatic mutations, Site Knowledge lifecycle controls. | Existing default buttons should show the trust labels and blocked-state guidance before any new button is added. |
| `npcink-cloud-addon` | Single connection summary, setup/recovery path, narrow transport test action, grouped status rows, low-frequency troubleshooting detail. | Local workflow stores, scheduler truth, broad module marketplace, billing/key lifecycle operations, raw provider logs. | Local permissions, Status, Site Knowledge, Troubleshooting, and Connection Management tabs should stay status/control-light. |
| `npcink-ai-cloud` | Runtime detail timeline, low-cardinality metrics, cause categories, usage/audit separation, retention labels, links back to local owners. | Prompt/router/workflow/skill/MCP registry ownership, customer-facing alert suites, WordPress approval/publish/write authority. | Runtime detail should become more inspectable while staying suggestion-only and non-authoritative for WordPress writes. |

## Unified Acceptance Checklist

For the next optimization pass on any project, a mature-plugin lesson is worth
implementing only if the resulting surface can answer these questions:

1. What is the source of the recommendation, status, or runtime detail?
2. What evidence can the operator inspect?
3. Which project owns the current step?
4. Is the result informational, copyable, handoffable, blocked, unavailable, or
   final?
5. What is the write posture: suggestion-only, local-admin-consent exception,
   Core-proposal-required, or no write path?
6. If blocked, what exact dependency is missing and who owns it?
7. If runtime-backed, what correlation id, run id, or detail link allows a
   support trail?
8. What data is stored, retained, redacted, or intentionally not stored?
9. Which existing public contract is being clarified?
10. Which forbidden ownership boundary remains untouched?

If an implementation cannot answer these questions without adding new runtime
ownership, it should stay as a boundary note or planning artifact.

## Complexity Brake

This reference-learning effort is valuable only if it keeps the local project
family simpler to operate. The next stage should not add:

- new REST routes, abilities, tables, options, queues, schedulers, provider
  settings, prompt routers, approval stores, or direct write paths only for
  reference-learning work;
- duplicate status dashboards when an existing surface can be clarified;
- new buttons before the current default buttons meet the trust-label checklist;
- Cloud or Addon controls that make the browser plugin feel like a second
  WordPress control plane.

The default move is to improve labels, grouping, blocked states, detail links,
and acceptance criteria on existing surfaces first.

## Recommended Next Sequence

1. Freeze this synthesis as the shared cross-repo reference-learning guardrail.
2. Run a no-code gap audit for each existing surface against the unified
   acceptance checklist.
3. Start the first implementation pilot in Toolbox fixed buttons because the
   surface is visible, bounded, and already has a reference note.
4. Apply the same acceptance checklist to Cloud Addon connection/status screens.
5. Then tune Core proposal/admin detail and Cloud runtime detail where the
   evidence and timeline patterns have the highest governance value.

## Decision

The next code-bearing phase should be a small Toolbox fixed-button acceptance
pass, not a broad cross-repo feature expansion.

That pilot should preserve the existing button list and only improve trust
signals: source/evidence labels, action class, owner/runtime label,
blocked-state guidance, governed handoff path, and visible no-direct-write
posture.
