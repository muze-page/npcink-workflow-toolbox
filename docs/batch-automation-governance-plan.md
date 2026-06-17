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

The existing Site Knowledge auto-sync bridge is the exception pattern:
debounced post ids, bounded batch size, capped retries, and Cloud status
handoff. It is not a general workflow runtime. New batch workflows should not
copy that bridge unless the task is a narrow content-change notification with
no WordPress write.

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
| `magick-ai-toolbox` | Batch review-set UI, eligibility summaries, blocked reasons, selected previews, and selected Core proposal submission. |
| `npcink-abilities-toolkit` | Ability schemas, dry-run preview callbacks, read-only plan builders, workflow definition guidance, and host-approved final write callbacks. |
| `npcink-governance-core` | Proposal intake, approval, preflight, audit, policy evaluation, and review status. |
| `magick-ai-adapter` | Authenticated channel, Core proposal relay, `write_actions[]` validation, output-reference validation, execution profile allowlist, and approved execution. |
| `magick-ai-cloud-addon` | Signed Cloud transport, run/result/status reads, entitlement and diagnostics detail. |
| `magick-ai-cloud` | Hosted processing for allowed non-writing runtime tasks, previews, diagnostics, and service detail. |

## Implementation Order

1. Stabilize `media_optimization_v1` batch review sets.
   The product surface should build a bounded review plan, show eligible and
   blocked candidates, generate selected previews, and submit only selected Core
   reviews. The local automation runtime contract anchor for this first target
   is
   `npcink_local_automation_media_conversion_review_set.v1`, which validates the
   review-set shape without adding a queue, scheduler, proposal creator, or
   WordPress write path.
2. Normalize batch response contracts.
   Add the same eligibility, blocked-item, retryability, and operator-next-action
   fields to new batch planning artifacts before adding more batch surfaces.
3. Extend content metadata only as governed handoff.
   Existing excerpt, category, and tag choices may become Core proposal plans;
   missing term creation and direct local apply remain out of scope.
4. Add more fixed batch candidates only after the first surface is accepted.
   Possible follow-ups are old article refresh, media ALT review, source
   coverage checks, and internal-link review sets.

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
