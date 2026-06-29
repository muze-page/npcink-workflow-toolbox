# Batch Automation Governance Plan

Status: planning contract.

This plan captures what Toolbox should learn from legacy WordPress AI
automation systems without importing their local queue runtime or direct write
path.

## Decision

Toolbox should productize batch work as governed review sets, not as an
automation executor.

The useful pattern is:

```text
rule and scope selection
-> eligibility summary
-> blocked reasons
-> selected preview or dry-run artifacts
-> Core proposal handoff
-> Core approval and preflight
-> Adapter allowlisted execution profile
-> Abilities final write callback
```

Toolbox may own the operator-facing batch plan, candidate list, skipped reason
display, selected preview controls, and proposal submission affordance. It must
not own a custom task table, background worker, retry lease, approval store,
workflow runtime, or final WordPress write execution.

## Adopted Lessons

### Rule First

Batch flows must start from an explicit rule and scope model before any proposal
or preview action runs. The first version should record:

- selected object type and object ids or bounded scope preset;
- task intent;
- eligibility checks;
- blocked reasons;
- operator-selected candidates;
- expected write ability ids;
- Core proposal route.

This keeps batch actions from becoming "run this across everything" shortcuts.

### Visible Recovery

Batch responses should be operationally readable. Any batch plan, preview run,
or proposal submission result should include:

- `eligibility_summary`;
- `blocked_items[]`;
- `operator_next_action`;
- `retryable`;
- `retry_guidance`;
- `selected_count`;
- `submitted_count`;
- per-item `status`, `reason`, and result reference.

Adapter execution responses should continue to expose `executed_count`,
`failed_count`, `results[]`, blocked items, and fail-closed reasons. Toolbox
renders those results, but does not persist them as workflow truth.

### Explicit Dependencies

Dependent steps should be modeled as plan dependencies, not as frozen local
queue rows. For write batches, use Adapter/Core `write_actions[]` and exact
`$outputs.<prior_action_id>.<field>` references when a later action depends on
an earlier action result. Toolbox may display the dependency chain, but Adapter
validates it before forwarding or executing.

### Thin Local Event Bridges

Site Knowledge auto-sync has moved to the Cloud Addon change bridge as the
required owner. Toolbox no longer retains a legacy standalone fallback with
debounced post ids, bounded batch size, capped retries, or Cloud status
handoff. It may show Cloud Addon bridge health and clear retired local state,
but it must not act as a general workflow runtime. New batch workflows should
not copy that bridge unless the task is a narrow content-change notification
with no WordPress write and a separate boundary owner has been named.

### Cloud-Fit Decision Rule

Batch work should stay local only when it is an operator-facing review, eligibility,
preview, or Core handoff surface around WordPress-owned objects. Move the difficult
runtime parts to Cloud when the workflow needs queue-backed execution, long-running
analysis, retry and dead-letter recovery, quota or entitlement enforcement, result
retention, observability, or cross-site diagnostics.

Cloud remains runtime and detail only. It may process accepted runs and expose status
or entitlement detail, but it must not become WordPress schedule truth, Core approval
truth, a second ability registry, or a WordPress write owner. Local controls must
decide whether a site submits a bounded batch intent, and reviewed write outcomes
must still land through Core, Adapter, and Abilities.

Automatic approval for low-risk batch writes, if a product path needs it, must
be a Core policy decision. Toolbox may expose whether a prepared plan is
eligible for a future Core auto-approval policy, but it must not approve its own
proposal, bypass Core preflight, or write WordPress objects directly. For media
ALT, the first safe candidate is "fill missing ALT only" after provenance text,
source attribution, generic placeholders, and weak candidates are rejected.

## Rejected Imports

Do not import these legacy automation shapes:

- public unauthenticated queue execution endpoints;
- nopriv AJAX queue triggers;
- `wp_set_current_user()` to impersonate an administrator in a worker;
- custom Toolbox queue tables for general workflow state;
- automatic post publishing;
- automatic term creation or assignment;
- automatic comment insertion;
- automatic media import, file replacement, metadata write, or featured-image
  setting outside Core governance;
- local retry workers, schedulers, leases, or priority queues for governed
  writes.

If a workflow needs durable asynchronous execution, cancellation, retry leases,
or long-running status, write a new runtime boundary decision before
implementing it. Do not add it inside Toolbox or Adapter by default.

## Cross-Repo Landing Plan

| Repo | Near-term responsibility |
| --- | --- |
| `npcink-toolbox` | Batch review-set UI, eligibility summaries, blocked reasons, selected previews, and selected Core proposal submission. |
| `npcink-abilities-toolkit` | Ability schemas, dry-run preview callbacks, read-only plan builders, workflow definition guidance, and host-approved final write callbacks. |
| `npcink-governance-core` | Proposal intake, approval, preflight, audit, policy evaluation, and review status. |
| `npcink-ai-client-adapter` | Authenticated channel, Core proposal relay, `write_actions[]` validation, output-reference validation, execution profile allowlist, and approved execution. |
| `npcink-cloud-addon` | Signed Cloud transport, run/result/status reads, entitlement and diagnostics detail. |
| `npcink-ai-cloud` | Hosted processing for allowed non-writing runtime tasks, previews, diagnostics, and service detail. |

## Implementation Order

1. Prove the OpenClaw/Adapter selected-batch execution contract.
   Batch media replacement should first work as an OpenClaw/Adapter governed
   path with selected actions, execution profile allowlist evidence, Core
   approval, commit preflight, per-action result payloads, retry guidance, and
   final Abilities callbacks. For media conversion, reuse the existing
   `adopt-cloud-media-derivative` / `replace-media-file` / `restore-media-backup`
   ability path already proven by the media optimization smoke.
   Do not build a Toolbox-specific replacement writer.
2. Stabilize `media_optimization_v1` batch review sets.
   The product surface should build a bounded review plan, show eligible and
   blocked candidates, generate selected previews, and submit only selected Core
   reviews. The local automation runtime contract anchor for this first target
   is
   `npcink_local_automation_media_conversion_review_set.v1`, which validates the
   review-set shape without adding a queue, scheduler, proposal creator, or
   WordPress write path.
3. Productize the accepted OpenClaw path as a Toolbox fixed button.
   After the OpenClaw/Adapter selected-batch execution proof is accepted,
   Toolbox may add a visible "replace original image" or equivalent action that
   calls the accepted path and renders Adapter/Core/Abilities results. The UI
   must still show eligibility, selected previews, Core approval/preflight
   posture, per-item execution status, rollback evidence, and retry guidance.
4. Normalize batch response contracts.
   Add the same eligibility, blocked-item, retryability, and operator-next-action
   fields to new batch planning artifacts before adding more batch surfaces.
5. Extend content metadata only as governed handoff.
   Existing excerpt, category, and tag choices may become Core proposal plans;
   missing term creation and direct local apply remain out of scope.
6. Add more fixed batch candidates as lower-risk review sets first.
   The first follow-up is `media_alt_caption_review_set.v1`, a review-only
   artifact inside the existing AI Site Helpers media ALT suggestions response.
   It may expose selected media items, blocked reasons, retry guidance, and
   operator next actions, but it must not write media metadata, create Core
   proposals, or reuse media derivative replacement execution. Future follow-up
   order remains taxonomy/tag review set, then internal-link review set. Do not
   add old-article source coverage as a separate Toolbox local batch candidate.
   Nightly Inspection Cloud Batch already owns site-wide article/data analysis;
   keep source coverage local only for current-post publish preflight or a
   single operator-triggered review artifact.

Before starting item 6, complete the
[Media Optimization Operator Trial](archive/2026-06/media-optimization-operator-trial.md). The
trial must prove that real operators understand preview, Core review,
Adapter/Core/Abilities execution, partial failure recovery, and governed
restore before Toolbox adds another batch surface.

## Acceptance Gate

A batch workflow is acceptable only when:

- every item has an eligibility decision or blocked reason;
- every write-like item maps to a real ability id;
- the plan can be submitted to Core without Toolbox creating approval truth;
- Adapter can reject malformed or non-allowlisted actions before execution;
- final writes stay behind Core approval, Core preflight, Adapter execution
  profiles, and Abilities callbacks;
- the UI avoids "run all", "replace all", "auto-publish", and "whole site
  automation" language.
