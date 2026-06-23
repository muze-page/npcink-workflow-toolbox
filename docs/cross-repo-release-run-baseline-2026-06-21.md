# Cross-Repo Release And Run Baseline - 2026-06-21

Status: closed baseline

This note records the clean post-release baseline for the Npcink
WordPress stack, the small local regression run, and the next focused work
area. It is a handoff record, not a new product requirement.

## Scope

Repositories checked:

| Repository | Baseline commit | State |
| --- | --- | --- |
| `npcink-abilities-toolkit` | `948b226c113a` | `master...origin/master`, clean |
| `npcink-governance-core` | `405cf3e08060` | `master...origin/master`, clean |
| `npcink-ai-client-adapter` | `103b5de74d9a` | `master...origin/master`, clean |
| `npcink-toolbox` | `61fac0ce07f3` | `master...origin/master`, clean before this handoff doc |
| `npcink-cloud-addon` | `a819bccc08ea` | `master...origin/master`, clean |
| `npcink-ai-cloud` | `dce3d4744340` | `master...origin/master`, clean |

All six repositories had only local `master`, only `origin/master` remotely,
one registered worktree, no ahead/behind state, no uncommitted files, and no
stash entries after cleanup.

## Verification Already Completed

Release cleanup gates completed before this handoff:

| Repository | Gate | Result |
| --- | --- | --- |
| `npcink-toolbox` | `composer test:all` | Passed; static contracts reported `2028 passed`. |
| `npcink-abilities-toolkit` | `composer test:all` | Passed; static contracts reported `5069 assertions`. |
| `npcink-ai-client-adapter` | `composer test:all` | Passed. |
| `npcink-ai-cloud` | `git diff --check` for the closeout doc | Passed. |

## Local Regression - 2026-06-21

Local environment:

- WordPress path:
  `/Users/muze/Local Sites/npcink/app/public`
- WP-CLI:
  `/opt/homebrew/bin/wp`, version `2.12.0`
- Local MySQL socket:
  `/Users/muze/Library/Application Support/Local/run/NPb24Zg9g/mysql/mysqld.sock`
- Cloud runtime containers were running, including API, worker, callback
  worker, ops worker, proxy, frontend, Postgres, and Redis.

Active Npcink plugins on the local WordPress site:

| Plugin | Version | Status |
| --- | --- | --- |
| `npcink-toolbox` | `0.1.0` | Active |
| `npcink-governance-core` | `0.1.1` | Active |
| `npcink-abilities-toolkit` | `0.5.2` | Active |
| `npcink-ai-client-adapter` | `0.3.2` | Active |

Regression commands and results:

| Command | Result | Boundary verified |
| --- | --- | --- |
| `composer smoke:article-core` | Passed | Toolbox built an `article_write_plan`, Core created one pending dry-run `npcink-abilities-toolkit/create-draft` proposal, and no WordPress post was created. Fixture proposal was purged. |
| `composer smoke:editor-review-artifacts` | Passed | Editor Content Support produced review artifacts, Adapter created one pending Core SEO metadata proposal, Core detail preserved reviewable field patches, and the sampled post was not mutated. Fixture proposal was purged. |
| `composer smoke:nightly-inspection-cloud-e2e` | Passed | Cloud ran Nightly Inspection to `succeeded`; Toolbox produced a merged Morning Brief preview; result stayed review-only with `direct_wordpress_write=false` and `cloud_scheduler_truth=false`. Run id: `run_2c230f19c2644a35a1c6e025a98b8648`. |

The regression intentionally did not approve, preflight, execute, publish,
mutate SEO fields directly, create posts, import media, or write WordPress
content outside Core-governed paths.

## Current Boundary

The verified operating split is:

- Cloud owns hosted runtime execution, run evidence, entitlement/quota detail,
  provider-call evidence, and retry/status/result detail.
- Cloud does not own WordPress schedule truth, ability registry truth, proposal
  truth, approval truth, or final WordPress writes.
- Toolbox owns the operator-facing fixed-button surface, local review display,
  Morning Brief merge, selected review items, and Core handoff UX.
- Toolbox does not own workflow runtime, queues, local run tables, approval,
  preflight, audit truth, or final writes.
- Core owns proposal intake, approval state, preflight, audit, and governance
  records.
- Adapter bridges reviewed plans and explicit execution profiles. It does not
  own provider/model runtime or generic final-write execution.
- Abilities Toolkit owns reusable WordPress ability definitions, schemas,
  callbacks, dry-run previews, and final write callbacks after governance.

## Next Focus

Do not expand Nightly Intelligence itself yet. The current bottleneck is the
operator and governance receipt loop after a Toolbox handoff.

Recommended next slice:

1. Add a clear receipt after Toolbox submits or prepares a Core handoff.
2. Show the Core proposal id, status, target ability id, and source review item.
3. Link from Toolbox back to Core proposal detail.
4. Preserve failure detail from Adapter/Core as operator feedback.
5. Keep all writes behind Core proposal approval, Core preflight, Adapter
   execution profile allowlists, and Abilities callbacks.

Acceptance for that slice:

- a handoff from Morning Brief or Content Support produces a visible receipt;
- the receipt can be traced to one Core proposal;
- failed handoff attempts show actionable operator feedback;
- no new Toolbox write executor, queue, scheduler, approval store, workflow
  registry, media registry, or Cloud control plane is introduced;
- existing smoke gates above continue to pass.

## Receipt Loop Slice - 2026-06-21

Implemented in Toolbox as an operator-facing local receipt, not a governance
record:

- receipt contract: `toolbox_core_handoff_receipt.v1`;
- receipt owner: `wordpress_toolbox_local`;
- storage posture: `ephemeral_response_only`;
- canonical truth: Core governance proposal, approval, preflight, execution,
  and audit records;
- visible fields: Core proposal id, status, target ability id, source review
  item, next operator action, and Core review link when available.

Covered surfaces:

- Morning Brief selected review-item Core handoff;
- Morning Brief completed draft proposal handoff;
- Site Knowledge review proposal handoff;
- Editor Content Support metadata Core review handoff;
- Editor Content Support SEO handoff and Core execution fallback.

This slice intentionally does not introduce a Toolbox queue, proposal store,
approval store, workflow runtime, scheduler, or direct WordPress write path.

## Stop Conditions

Stop and write a boundary note before implementing any of these:

- automatic Core proposal creation from passive Cloud status/result reads;
- automatic proposal approval or execution;
- direct WordPress writes from Toolbox outside the existing featured-image
  Local Admin Consent proof;
- plugin-side Action Scheduler or local queue/runtime ownership;
- Cloud scheduler truth or Cloud workflow registry truth;
- new batch write surface beyond existing governed proofs;
- Site Knowledge indexing, re-indexing, or collection lifecycle ownership in
  Toolbox.
