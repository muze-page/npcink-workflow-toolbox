# OpenClaw Batch Media Optimization Handoff

Status: active planning contract.

This document records the implementation order for batch media optimization:
build and verify the OpenClaw/Adapter batch contract first, then expose the
accepted path as a fixed Toolbox best-practice button.

## Decision

Toolbox is the fixed-button product surface for repeatable OpenClaw best
practices. It must not invent a second batch media replacement implementation.

The canonical order is:

1. OpenClaw and Adapter prove the governed batch execution contract.
2. Core keeps proposal, approval, preflight, and audit truth.
3. Abilities Toolkit performs the approved media replacement callbacks.
4. Toolbox productizes the accepted flow as `media_optimization_v1`.

For image format conversion or "replace original image" work, Toolbox should
reuse the same OpenClaw path that already powers the single-image Optimize
Existing Image flow. It must not write attachment files, update
`_wp_attached_file`, patch post content, update options, or maintain a local
execution queue.

## Stage 1: OpenClaw Batch Contract

OpenClaw/Adapter must define the batch execution semantics before Toolbox turns
the flow into a fixed button.

Required contract fields:

- explicit operator intent and bounded scope;
- selected attachment ids and selected action ids;
- real target ability ids from `npcink-abilities-toolkit`;
- `execution_profile` allowlist evidence;
- per-action idempotency keys;
- Core proposal ids or Core review refs for each selected item;
- Core approval and commit-preflight result evidence;
- per-action `status`, `result`, `blocked_reason`, and `retryable`;
- aggregate `selected_count`, `submitted_count`, `executed_count`,
  `failed_count`, and `blocked_count`;
- `operator_next_action` and `retry_guidance`.

For media derivative replacement, approved execution must reuse:

- `npcink-abilities-toolkit/build-media-derivative-cloud-request` for bounded
  Cloud derivative request input;
- the Adapter media derivative run/result and proposal-payload bridge;
- `npcink-abilities-toolkit/build-media-optimization-plan` for the reviewed
  Core proposal shape;
- `npcink-abilities-toolkit/adopt-cloud-media-derivative` or
  `npcink-abilities-toolkit/replace-media-file` for final approved media file
  replacement;
- `npcink-abilities-toolkit/restore-media-backup` for rollback proof.

Acceptance proof should extend the existing single-image media derivative smoke
into a selected-batch execution smoke:

```text
create temporary JPEG attachments
-> build media derivative batch plan
-> generate selected Cloud derivative artifacts
-> submit selected Core media optimization proposals
-> approve-and-execute selected proposals through Adapter
-> assert attachment file pointer and MIME changes
-> assert replacement history and backup evidence
-> assert post-content URL repair evidence when fixture content references exist
-> restore originals through governed restore proposals
```

This proof belongs to the OpenClaw/Adapter plus Core/Abilities integration
layer. Toolbox may keep review-only and proposal-only smokes, but it should not
claim batch replacement is product-ready until this execution proof exists.

## Stage 2: Toolbox Fixed Best Practice

After Stage 1 is accepted, Toolbox may expose the path as a fixed operator
workflow under **Optimize Existing Image**.

Toolbox owns:

- media range and processing-goal controls;
- eligibility summary and blocked item display;
- selected candidate controls;
- selected preview generation;
- selected Core review submission;
- approved-execution result rendering returned by Adapter;
- recovery guidance and rollback links or refs.

Toolbox does not own:

- OpenClaw recipe truth;
- execution profile policy;
- Core approval, preflight, proposal, or audit truth;
- Abilities final write callbacks;
- media replacement history;
- post-content or settings patch execution;
- queues, leases, retries, schedulers, or background workers.

User-facing copy may say "replace original image" only when the visible flow
makes the governance path clear:

```text
Review selected previews.
Submit selected Core replacement reviews.
Execute approved replacements through Adapter/Core/Abilities.
Restore from recorded backups if needed.
```

Avoid copy such as "replace all", "optimize whole site", or "run unattended" in
the current Toolbox surface. Broad scopes can produce bounded review sets, but
the operator must still review selected candidates and see skipped reasons
before any proposal or execution step.

The fixed Toolbox action may call Adapter `approve-and-execute` for selected
Core media replacement proposals only after selected previews and proposal ids
exist. The UI must render Adapter response fields such as
`selected_count`, `submitted_count`, `executed_count`, `failed_count`,
`blocked_count`, `partial_success`, `retryable`, `operator_next_action`,
Core preflight evidence, per-action `execution_profile`, and per-action
`idempotency_key`. These are operator feedback and audit pointers, not Toolbox
runtime state.

## Non-Goals

Do not implement batch media replacement by adding any of the following to
Toolbox:

- direct calls to `wp_update_attachment_metadata`, `_wp_attached_file`, or raw
  filesystem replacement;
- a custom queue or run table;
- a retry worker, lease store, or dead-letter processor;
- a hidden `approve-and-execute` loop not backed by Core approval and
  preflight evidence;
- automatic post-content search-replace;
- automatic settings/theme-mod URL replacement;
- administrator impersonation in background execution.

If the OpenClaw batch execution contract is not available, Toolbox should stay
at the review-set and selected Core proposal stage.
