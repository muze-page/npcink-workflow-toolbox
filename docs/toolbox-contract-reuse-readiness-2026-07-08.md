# Toolbox Contract Reuse Readiness - 2026-07-08

Status: active observation record

This record closes the `npcink-workflow-toolbox` observation pass after Core
confirmed `proposal_handoff` readiness, Abilities Toolkit confirmed
`ability_contracts` readiness, and Adapter confirmed `execution_profiles`
readiness. The purpose is to decide whether Toolbox needs new implementation
work before the next project optimization pass.

## Scope

Toolbox's role in the current reuse stack is `product_surface`:

- expose fixed operator buttons and WordPress admin/editor surfaces;
- collect non-secret operator intent, review choices, and content context;
- call Cloud-owned search, image-source, Site Knowledge, or hosted suggestion
  seams only through existing bounded contracts;
- build reviewable candidates, suggestion artifacts, or Core-ready plans;
- reuse real Toolkit or Toolbox ability ids rather than local aliases;
- show shallow Workflow readiness and contract reuse evidence;
- hand write-like outcomes to Core proposal or local-consent audit paths
  instead of writing silently.

The adjacent roles stay outside Toolbox:

| Role | Owner |
| --- | --- |
| `ability_contracts` | `npcink-abilities-toolkit` or another WordPress Abilities API provider |
| `proposal_handoff` | `npcink-governance-core` |
| `execution_profiles` | `npcink-ai-client-adapter` or another approved channel adapter |
| `signed_transport` | `npcink-cloud-addon` |
| `runtime_detail` | `npcink-ai-cloud` |

## Current Evidence

The current Toolbox already has the product-surface hooks needed for contract
reuse:

- Product Positioning defines Toolbox as the fixed-button projection of the
  same ability ids, plan artifact shapes, and Core proposal handoff used by
  OpenClaw flows;
- Boundary defines the safe pattern from Toolbox fixed button to reviewed plan,
  Core proposal, approval/preflight, and WordPress ability write;
- Architecture records Workflow readiness contract reuse without turning the
  readiness row into a second registry, runtime, approval store, queue, or
  write executor;
- Cross-Repo Contract Reuse Acceptance freezes `toolbox_role=product_surface`
  with `adds_registry=false`, `adds_scheduler_truth=false`,
  `adds_approval_store=false`, `adds_queue=false`, and
  `adds_write_executor=false`;
- `Ability_Surface_Metadata` exposes the `contract_reuse` readiness row as
  support detail only;
- static contracts already protect the cross-repo role map, route boundary
  table, ability boundary table, Cloud bridge table, Local Admin Consent
  exception, and fixed-button/OpenClaw split;
- high-risk write-like flows are already framed as Core-ready plans,
  local-admin-consent exception paths, or Adapter/Core/Abilities handoffs
  rather than silent Toolbox writes.

## Active Observation Result

No new Toolbox route, workflow runtime, queue, approval store, or write executor is needed for this pass.

The current product surface is sufficient for operators and clients to reuse
the existing Core, Toolkit, Adapter, Cloud Addon, and Cloud contracts. The
important follow-up is not to broaden Toolbox ownership, but to keep future
fixed-button work inside the existing review and handoff discipline:

```text
operator or editor intent
-> Toolbox fixed button or editor panel
-> reviewable candidate, suggestion, or plan artifact
-> real Toolkit or Toolbox ability id
-> Core proposal or local_admin_consent audit classification when required
-> Adapter/Core/Abilities execution outside Toolbox when approval permits
-> Core audit or product-visible operator feedback
```

## Representative Ready Contracts

These existing Toolbox contracts are enough to continue the reuse pass:

- `contract_reuse` Workflow readiness projection;
- `fixed-button` product surface;
- `site_ops_insight_pack.v1`;
- `site_ops_cloud_analysis_request.v1`;
- `content_metadata_delta`;
- `content_metadata_apply_plan`;
- `image_candidate_adoption_plan`;
- `article_audio_adoption_plan.v1`;
- `media_optimization_v1`;
- `media_alt_caption_review_set.v1`;
- `operation-classification-v1`;
- `local_admin_consent`;
- `core_proposal_required`;
- `plan_to_proposal_batch`.

Treat these as reference product-surface contracts. Add a new Toolbox feature
only when a real operator workflow cannot be expressed as a bounded suggestion,
review artifact, Core-ready plan, Local Admin Consent exception, or accepted
Adapter/Core/Abilities handoff.

## Stop Rule

Stop and write a boundary note or ADR before implementing if a follow-up
requires Toolbox to own any of these:

- reusable WordPress ability definitions, schemas, callbacks, or dry-run
  previews;
- Core proposal records, approval lifecycle, commit-preflight truth, read-grant
  truth, or audit truth;
- Adapter execution profiles, generic final write authority, arbitrary ability
  execution, or approval proxying;
- workflow runtime, task queues, retry workers, leases, schedulers, run
  recovery workspaces, or batch execution consoles;
- direct publishing, media import, media metadata mutation, SEO mutation,
  post-content mutation, term creation, or featured-image writes outside the
  existing Local Admin Consent exception;
- provider credential storage, model routing, prompt/preset truth, quota,
  billing, or request log ownership;
- Cloud signed transport, Cloud runtime/detail, Site Knowledge lifecycle,
  vector indexing, reranking, collection ownership, or Cloud run truth;
- MCP runtime, Agent Gateway catalogs, Open API control-plane state, or
  OpenClaw projection truth.

## Next Development Recommendation

End this Toolbox observation pass here.

The next useful development slice should move to the Cloud transport/runtime
side only if the operator wants to continue the same pass. A good next slice is
`npcink-cloud-addon`: verify that signed transport and read-only Cloud runtime
status surfaces stay bounded and do not become proposal, approval, provider
routing, prompt, scheduler, local registry, or WordPress write owners.

## Verification

Required Toolbox gate for this record:

```bash
composer test:all
```

Run WordPress smoke gates only if a future change touches activation, REST
routes, admin/editor UI behavior, Core proposal handoff, Local Admin Consent,
Adapter execution, Abilities registration, Cloud transport, or local runtime
exceptions.
