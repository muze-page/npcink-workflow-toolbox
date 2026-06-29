# Media Optimization V1

Status: validated fixed workflow contract; V1 frozen for defect fixes only.

`media_optimization_v1` names the fixed governed image optimization workflow.
It is exposed through media-library single-image actions and the Toolbox Batch
Optimize Images workbench.
This is a product contract over the current media derivative, metadata review,
Adapter recipe, and Core proposal handoff. It is not a new workflow runtime.

The current V1 has passed release smokes and one real-attachment operator trial:
one single-image execution, one selected batch review set, governed execution,
and governed restore. See
[Media Optimization Operator Trial Results - 2026-06-18](archive/2026-06/media-optimization-operator-trial-results-2026-06-18.md).

## Position

Toolbox already owns the operator-facing media optimization surface. This
contract makes that ownership explicit so future work improves the existing
flow instead of adding a duplicate runner, moving the flow into Core, or
creating a generic workflow builder.

The workflow is deterministic:

1. The operator selects one existing media attachment or resolves one local
   uploads URL to an attachment candidate.
2. Toolbox reads its stored media optimization defaults and applies one-run
   operator overrides.
3. Adapter runs the bounded media derivative recipe through Cloud Addon and
   Cloud, returning a short-lived derivative preview artifact.
4. Toolbox renders the same-origin signed preview proxy and collects reviewed
   media metadata.
5. Toolbox submits the Adapter `from_plan_request` to
   `/proposals/from-plan`, so Core creates one media optimization proposal.
6. Core owns proposal review, approval, preflight, and audit.
7. Adapter and Abilities own approved final execution when policy permits.

## Ownership

| Project | Owns |
| --- | --- |
| `npcink-toolbox` | Fixed operator UI, media selection, media optimization defaults, one-run overrides, preview rendering, reviewed metadata capture, operator feedback display, and Core proposal handoff. |
| `npcink-governance-core` | Plan intake, proposal records, approval, preflight, and audit truth. |
| `npcink-openclaw-adapter` | Bounded media derivative recipe dispatch, same-origin preview proxy, from-plan relay, and approved allowlisted execution. |
| `npcink-cloud-addon` | Verified local-to-Cloud signing and transport. |
| `npcink-cloud` | Hosted derivative processing, run/result state, entitlement, quota, provider routing, and runtime diagnostics. |
| `npcink-abilities-toolkit` | Reusable media read, derivative request, optimization plan, metadata, and derivative adoption abilities. |

## Non-Goals

`media_optimization_v1` must not add:

- a Toolbox workflow runtime;
- a persistent Toolbox run table;
- a Toolbox media artifact registry;
- Toolbox-owned approval or audit truth;
- Toolbox provider routing, quota, billing, or request-log control planes;
- direct WordPress media writes from Toolbox;
- automatic proposal approval;
- automatic retry workers, queues, schedulers, leases, or background jobs.

## Product Surface

Single-image optimization starts from the WordPress media-library attachment
details panel or image row actions, where the operator is already inspecting or
selecting an image. Those actions carry the attachment ID into the same
selected-image workbench used for small batches. Deprecated `tool=optimize` and
legacy `toolbox_tool=media-derivative` URLs fall back to Batch Optimize Images
rather than exposing a standalone one-image picker in Toolbox.

Batch media optimization lives in the Toolbox **Image Handling -> Batch
Optimize Images** workbench. Media library bulk actions may send selected
attachment IDs into that workbench, but eligibility review, selected previews,
and selected review submission stay inside the default admin workbench. Explicit
replacement execution stays in the accepted Adapter/Core/Abilities path and is
covered by dedicated execution smoke tests, not by a default visible admin
button.
Cloud Checks may keep a preview-only media derivative reachability check, but
Core proposal submission, batch proposal submission, URL repair, and settings
repair stay in governed media optimization surfaces.

The single-image UI should present the existing flow as a fixed sequence:

1. Select media.
2. Generate Cloud preview.
3. Review media metadata.
4. Submit optimization review.
5. Continue in Core or Adapter for approval and execution.

These steps may be displayed in local browser state or in existing result
artifacts. They must not require a new REST route such as `/workflow-runs` or a
new durable Toolbox run store in the current stage.

The batch UI should present a separate fixed sequence:

1. Select images in the media library or use a bounded media sample.
2. Build an eligibility plan.
3. Generate previews only for selected rows.
4. Submit selected items for review.
5. Complete approval and execution only through the accepted Adapter/Core/Abilities path outside the default admin workbench.

## Operator Flow

The user-facing flow is deliberately small:

1. Select or resolve an existing media attachment.
2. Generate a Cloud preview and inspect the short-lived derivative image.
3. Review the adoption preflight summary, including content URL and settings
   reference signals.
4. Submit the Core optimization review only after the preview is acceptable.
5. Approve and execute only through the explicit Adapter
   `approve-and-execute` action, whether the operator starts that action from
   OpenClaw, Core/Adapter, or another governed review surface.
6. Audit and roll back from Core/Adapter evidence, not from Toolbox state.

Toolbox copy should make clear that preview generation is not a WordPress write.
The first visible success state is a derivative preview plus evidence. The
write decision starts only after the operator submits the Core review.

## Replacement Boundaries

Media adoption replaces the approved attachment file through the governed Core
proposal path. It does not automatically repair every old URL string that may
exist elsewhere in WordPress.

The first-version boundary is:

- attachment adoption: governed by one Core media optimization proposal;
- post content URLs: handled by the separate content URL repair action when the
  preflight finds hard-coded references;
- settings, theme, and plugin option URLs: handled by the separate settings URL
  repair action with excluded-format and minimum-dimension filters;
- external caches, CDN rules, custom database tables, and arbitrary third-party
  storage: outside Toolbox automatic replacement.

This keeps the fixed button understandable without letting Toolbox become a
second WordPress write owner or a site-wide search-replace tool.

## Batch Guardrails

Batch optimization is a review-set workflow, not a one-click whole-site
replacement. The default surface should:

1. build a bounded review plan;
2. show `eligibility_summary`, skipped candidates, and blocked reasons before
   any preview or proposal action;
3. let the operator generate previews only for selected candidates;
4. submit only selected items for review;
5. leave Adapter `approve-and-execute` to the governed execution path after review;
6. render review results without storing workflow truth in Toolbox.

Batch "direct replacement" is allowed only as a productized result of the
OpenClaw/Adapter batch contract. The implementation order is:

1. prove selected-batch execution in OpenClaw/Adapter with Core
   approval/preflight, execution profile allowlist evidence, per-action results,
   and Abilities media replacement callbacks;
2. keep Toolbox at review-set and selected proposal submission until that proof
   exists;
3. expose the accepted path in Toolbox as a fixed best-practice action that
   renders Adapter/Core/Abilities outcomes, not as a Toolbox writer.

The single-image smoke already proves the desired governed replacement shape:
Toolbox handoff, Adapter Cloud derivative run, Core proposal intake,
Adapter `approve-and-execute`, Abilities replacement, backup history, and
governed restore. The batch proof should reuse that path for selected items
instead of adding a new replacement mechanism.

Avoid labels such as "replace all" or "whole site optimization" in the current
stage. Broad scopes may exist as bounded candidate searches, but the visible
language should keep the operator focused on sampled review sets and selected
proposal submission.

Batch response and execution payloads should follow
[Batch Automation Governance Plan](batch-automation-governance-plan.md): include
`blocked_items[]`, `retryable`, `retry_guidance`, `operator_next_action`,
selected/submitted/executed/failed/blocked counts, `partial_success`, Core
preflight evidence, per-action `execution_profile`, per-action
`idempotency_key`, and per-item status or result references. Those fields are
display and handoff evidence, not a Toolbox workflow store.

## Proposal Shape

The single-image optimization path must keep one user intent as one Core proposal.
In product copy and tests, this should remain visible as one Core proposal
rather than a set of unrelated writes:

- plan ability: `npcink-abilities-toolkit/build-media-optimization-plan`;
- proposal mode: `plan_to_proposal_batch`;
- target write actions include reviewed media metadata and derivative adoption;
- inline content reference repair evidence belongs in the derivative adoption
  preview/commit contract, not as a separate post-content write action inside
  the same optimization intent.

If Core or Abilities lacks the required media optimization plan contract,
Toolbox should stop and report operator feedback instead of splitting the
optimization into multiple unrelated proposals.

## Expansion Rule

This V1 should not grow additional replacement mechanics after validation.
Defect fixes and copy clarifications are acceptable; new media write surfaces,
unattended execution, video transcoding, global search-replace, or whole-library
replacement require a separate boundary decision.

The next batch pattern should be copied to a lower-risk surface first: media
ALT/caption review sets. That follow-up should remain suggestion/planning or
Core handoff evidence, not a direct Toolbox media metadata writer. Do not
generalize this workflow into a workflow builder until a separate runtime
decision defines storage, retries, leases, quotas, cancellation, and ownership.
