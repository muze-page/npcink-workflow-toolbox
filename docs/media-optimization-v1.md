# Media Optimization V1

Status: active fixed workflow contract.

`media_optimization_v1` names the existing **Optimize Existing Image** surface
as a fixed, governed Toolbox workflow. This is a product contract over the
current media derivative, metadata review, Adapter recipe, and Core proposal
handoff. It is not a new workflow runtime.

## Position

Toolbox already owns the operator-facing media optimization surface. This
contract makes that ownership explicit so future work improves the existing
flow instead of adding a duplicate runner, moving the flow into Core, or
creating a generic workflow builder.

The workflow is deterministic:

1. The operator selects one existing media attachment or resolves one local
   uploads URL to an attachment candidate.
2. Toolbox reads Core media optimization defaults when available and applies
   one-run operator overrides.
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
| `npcink-toolbox` | Fixed operator UI, media selection, one-run overrides, preview rendering, reviewed metadata capture, operator feedback display, and Core proposal handoff. |
| `npcink-governance-core` | Media policy defaults, plan intake, proposal records, approval, preflight, and audit truth. |
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

The first product surface remains **Content Support -> Optimize Existing
Image**. Cloud Checks may keep a preview-only media derivative reachability
check, but Core proposal submission, batch proposal submission, URL repair, and
settings repair stay in the Optimize Existing Image surface.

The UI should present the existing flow as a fixed sequence:

1. Select media.
2. Generate Cloud preview.
3. Review media metadata.
4. Submit optimization review.
5. Continue in Core or Adapter for approval and execution.

These steps may be displayed in local browser state or in existing result
artifacts. They must not require a new REST route such as `/workflow-runs` or a
new durable Toolbox run store in the current stage.

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

Only after this fixed surface is clear and tested should the same pattern be
copied to another fixed workflow such as publish preflight or old-article
refresh. Do not generalize it into a workflow builder until a separate runtime
decision defines storage, retries, leases, quotas, cancellation, and ownership.
