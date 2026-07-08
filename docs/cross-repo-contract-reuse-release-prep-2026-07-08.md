# Cross-Repo Contract Reuse Release Prep - 2026-07-08

Status: local release-readiness record

This records the release-prep decision for the current contract-reuse stack.
The next step is validation and publication review, not another feature slice.

## Scope

The stack proves that the current projects reuse existing contracts instead of
creating a new control plane:

| Repo | Local commit | Role in this stack |
| --- | --- | --- |
| `npcink-ai-cloud` | `231a8338 Expose Cloud runtime contract reuse` | Source of the Cloud `pro_cloud_runtime.contract_reuse` runtime/detail projection. |
| `npcink-cloud-addon` | `d5938f8 Surface Cloud Addon contract reuse` | Signed transport consumer and read-only `Contract reuse` display. |
| `npcink-workflow-toolbox` | `a08f177 Add fixed button trust labels` | Product-surface trust labels for fixed buttons. |
| `npcink-workflow-toolbox` | `62e6e76 Surface contract reuse readiness` | Workflow readiness `contract_reuse` display. |
| `npcink-workflow-toolbox` | `b6a264f Document contract reuse acceptance` | Central cross-repo acceptance contract and static checks. |

Core, Adapter, and Toolkit are reused by role only in this pass. They do not
need new code unless a future acceptance run exposes a missing ability contract
or execution profile.

## Contract Evidence

Current implementation evidence:

- Cloud returns `entitlement.pro_cloud_runtime.contract_reuse` from
  `/Users/muze/gitee/npcink-ai-cloud/app/api/routes/entitlements.py`.
- Cloud Addon normalizes the projection in
  `/Users/muze/gitee/npcink-cloud-addon/includes/class-cloud-entitlement-summary.php`.
- Cloud Addon renders read-only `Contract reuse` detail in
  `/Users/muze/gitee/npcink-cloud-addon/includes/class-cloud-settings-page.php`.
- Toolbox exposes Workflow readiness `contract_reuse` from
  `/Users/muze/gitee/npcink-workflow-toolbox/includes/Ability_Surface_Metadata.php`.
- The central acceptance rule is
  `/Users/muze/gitee/npcink-workflow-toolbox/docs/cross-repo-contract-reuse-acceptance.md`.

Required frozen roles remain:

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

## Verification Evidence

Commands run for this release-prep pass:

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

Observed results before writing this record:

- Cloud entitlement route tests: `5 passed, 1 warning`.
- Cloud fast gate: contract tests `60 passed, 1 skipped`; domain tests
  `152 passed, 3 skipped`.
- Cloud anti-drift gate: `cloud anti-drift passed` and
  `provider env retirement passed`.
- Cloud Addon `composer run test:all`: passed, including the forbidden route and
  write-token scan.
- Toolbox `composer test:all`: passed.

`composer quality:matrix:run` remains the final closeout gate after this record
is committed because this document intentionally changes the Toolbox worktree.

## Boundary Decision

Continue only as a release-prep stack:

1. Review the three local stacks together.
2. Publish or open PRs in this order when the operator approves:
   `npcink-ai-cloud` -> `npcink-cloud-addon` -> `npcink-workflow-toolbox`.
3. Do not start another implementation slice until this contract-reuse stack is
   reviewed or intentionally parked.

This stack must not grow into any of these:

- a second ability registry, workflow registry, approval store, queue, or
  WordPress control plane;
- new scheduler truth in Cloud, Addon, or Toolbox;
- Toolbox or Addon provider keys, prompt/router/preset truth, or billing truth;
- Cloud-generated final WordPress writes, direct media import, direct SEO
  mutation, metadata mutation, or publishing;
- new heavy infrastructure such as Celery, Kafka, NATS, Temporal, RabbitMQ,
  Kubernetes-first deployment, or a second workflow engine.

## Current Recommendation

Stop feature development here. The next meaningful action is publication
review: inspect the three local commit stacks, decide whether they should be
published as PRs, and only then move to the next project optimization pass.
