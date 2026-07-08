# Cross-Repo Contract Reuse Acceptance

Status: active release guardrail
Date: 2026-07-08

This is the shared acceptance rule for the current reference-learning pass. It
turns the repeated "stand on existing contracts" decision into a checkable
cross-repo contract. It is not a new runtime, registry, queue, approval path, or
release process.

## Contract Reuse Map

| Project | Reused role | Must stay out of |
| --- | --- | --- |
| `npcink-abilities-toolkit` | `ability_contracts`: reusable WordPress ability ids, schemas, dry-run plans, and host-approved callbacks. | Provider/model routing, Cloud runtime, approval truth, audit truth, queue/scheduler truth, and final write authorization. |
| `npcink-governance-core` | `proposal_handoff`: proposal intake, approval/rejection state, preflight, policy checks, and audit records. | Ability definitions, provider runtime, Cloud detail, workflow queues, and final WordPress callback implementation. |
| `npcink-ai-client-adapter` | `execution_profiles`: signed client channel, Core proposal/status proxy, and allowlisted post-Core execution profiles. | First-party ability definitions, approval truth, generic approval proxy, Cloud credentials, workflow runtime, and direct writes outside Core preflight. |
| `npcink-workflow-toolbox` | `product_surface`: fixed buttons, operator UI, reviewable candidates, Core-ready plans, and shallow readiness projection. | Registry truth, runtime ownership, queue/scheduler ownership, approval store, provider billing, Site Knowledge lifecycle, and direct metadata/media/SEO writes. |
| `npcink-cloud-addon` | `signed_transport`: Cloud URL/API key settings, HMAC signing, bounded Cloud runtime transport, entitlement/detail reads, and read-only status surfaces. | Proposal/approval/audit truth, billing truth, provider routing truth, prompt/preset truth, workflow runtime, local ability registry, scheduler truth, and WordPress writes. |
| `npcink-ai-cloud` | `runtime_detail`: hosted runtime execution, provider adapters, usage/entitlement evidence, health diagnostics, Site Knowledge runtime/detail, artifacts, and read-only runtime metadata. | WordPress control-plane truth, local ability registry, local workflow registry, final approval/preflight/audit truth, prompt/router/preset local truth, and WordPress writes. |

## Frozen Projection Fields

The current acceptance path is the `pro_cloud_runtime.contract_reuse` and
Toolbox `contract_reuse` readiness projection:

- Cloud source:
  `/Users/muze/gitee/npcink-ai-cloud/app/api/routes/entitlements.py`
- Cloud Addon source:
  `/Users/muze/gitee/npcink-cloud-addon/includes/class-cloud-entitlement-summary.php`
- Cloud Addon display:
  `/Users/muze/gitee/npcink-cloud-addon/includes/class-cloud-settings-page.php`
- Toolbox display:
  `includes/Ability_Surface_Metadata.php`

The fields must keep these values:

```text
cloud_role=runtime_detail
toolbox_role=product_surface
core_role=proposal_handoff
adapter_role=execution_profiles
toolkit_role=ability_contracts
addon_role=signed_transport
adds_registry=false
adds_scheduler_truth=false
adds_approval_store=false
adds_queue=false
adds_write_executor=false
```

Toolbox wording may use `adds no registry, runtime, approval store, queue, or
write executor`; Cloud and Cloud Addon wording may distinguish scheduler truth
from runtime/detail. The meaning must stay the same: no project may use this
projection to claim new control-plane ownership.

## End-To-End Acceptance Path

Use this path for the next representative validation before new feature work:

1. Cloud returns `entitlement.pro_cloud_runtime.contract_reuse` from
   `GET /v1/entitlements/current`.
2. Cloud Addon preserves that contract in its normalized entitlement projection
   and renders the Pro Cloud Runtime `Contract reuse` row as read-only detail.
3. Toolbox shows Workflow readiness `contract_reuse` as a support/readiness
   projection, not as a generic Abilities Explorer or runtime console.
4. Any write-like follow-up still goes through Toolkit ability contracts,
   Core proposal/preflight/audit, and Adapter execution profiles.
5. Cross-repo gates pass without adding a REST route, queue, scheduler truth,
   approval store, registry, provider picker, or WordPress write executor.

Minimum evidence for this acceptance path:

```bash
cd /Users/muze/gitee/npcink-ai-cloud
.venv/bin/python -m pytest tests/api/test_entitlement_routes.py -q
pnpm run check:fast
pnpm run check:anti-drift

cd /Users/muze/gitee/npcink-cloud-addon
composer run test:all

cd /Users/muze/gitee/npcink-workflow-toolbox
composer test:all
composer quality:matrix:run
```

`composer quality:matrix:run` is the closeout gate for this phase. Use
`php scripts/cross-repo-quality-matrix.php --run-gates --fail-on-dirty` only
when preparing a release or PR stack that must prove every repo has no hidden
local edits.

## Publish And PR Rule

Do not start another implementation slice until the current local commits are
reviewed as one contract-reuse stack.

Recommended publish order when the operator asks to push:

1. `npcink-ai-cloud` - source of the Cloud runtime/detail projection.
2. `npcink-cloud-addon` - signed transport and read-only display consumer.
3. `npcink-workflow-toolbox` - product-surface readiness and central
   acceptance docs.

Core, Adapter, and Toolkit do not need new code for this acceptance pass because
their roles are reused, not changed. If future acceptance uncovers a missing
write-profile or ability contract, open that as a separate narrow change after
this stack is reviewed.

## Stop Rule

Stop and write a boundary note instead of implementing if a proposed follow-up
requires any of these:

- a second ability registry, workflow registry, approval store, or WordPress
  control plane;
- a new local or Cloud queue/scheduler truth for WordPress writes;
- provider keys, prompt/router/preset truth, or billing truth in Toolbox or
  Cloud Addon;
- Cloud-generated final WordPress writes, direct publishing, media import,
  SEO mutation, or metadata mutation;
- a new heavy infrastructure dependency such as Celery, Kafka, NATS, Temporal,
  RabbitMQ, Kubernetes-first deployment, or a second workflow engine.
