# Cross-Repo Third-Party AI Review Brief - 2026-07-09

Status: review brief.

Purpose: give a third-party AI reviewer a compact current-state view of the six
Npcink projects, what has already landed, the ownership boundaries, and the
next goals. This is an audit handoff, not a new runtime contract.

## 2026-07-11 Media ALT Closure Update

The missing-media-ALT proof is now implemented across the four local WordPress
repos:

| Project | Landed evidence |
| --- | --- |
| `npcink-abilities-toolkit` | `e41b4cf` adds `build-media-alt-apply-plan`, guarded `expected_current_alt`, visual confirmation, missing-only validation, and the final reusable `update-media-details` write. |
| `npcink-governance-core` | `3742490` validates `media_alt_apply_plan.v1`, preserves review/audit evidence, defaults to manual review, and returns a live-value preflight guard without reading or writing attachment state. |
| `npcink-ai-client-adapter` | `17fa4ef` exposes the shared plan, validates the narrow execution profile, runs Toolkit dry-run immediately before commit, blocks drift, and records live-preflight evidence. |
| `npcink-workflow-toolbox` | The current change displays missing ALT only, requires explicit visual confirmation, builds the same Toolkit plan per image, submits it through Adapter `/proposals/from-plan`, renders Core receipts, and stops. |

The original execution-boundary questions are also closed:

- Batch media optimization stops after selected Core proposal submission.
- Editor SEO, external-image adoption, and article-audio adoption remain
  intentionally preserved: the author action queues a reviewed proposal id and
  execution occurs only after the next successful native Publish or Update.
- Product positioning and the media-specific contract now agree on those two
  distinct rules.
- Toolbox media ALT does not call `approve-and-execute`, poll execution, use
  `/wp/v2/media`, overwrite existing ALT, or include caption/title/description.

This update supersedes the older local-head snapshot below for the media ALT
scope. The older table remains as dated historical context for the broader
six-repo review.

## Review Scope

Target projects:

- `npcink-abilities-toolkit`
- `npcink-governance-core`
- `npcink-ai-client-adapter`
- `npcink-workflow-toolbox`
- `npcink-ai-cloud`
- `npcink-cloud-addon`

The current platform split is:

```text
Toolkit ability contracts
-> Toolbox product surface
-> Adapter execution profiles
-> Core proposal handoff and audit
-> Cloud Addon signed transport
-> Cloud runtime/detail
```

The key review question is whether the project family still preserves one
authority per concern, or whether any repo is quietly becoming a second ability
registry, workflow registry, approval store, runtime queue, provider control
plane, or WordPress write executor.

## Current Local Snapshot

Observed from `/Users/muze/gitee/npcink-workflow-toolbox` on 2026-07-09 using
`composer quality:observe` and plain `git status --short --branch`.

| Project | Current local state | Head | Notes for reviewer |
| --- | --- | --- | --- |
| `npcink-abilities-toolkit` | `master...origin/master`, clean | `072effc` | Last observed head points Toolkit docs to the platform index. |
| `npcink-governance-core` | `master...origin/master`, clean | `853e0db` | Last observed head is the reference-plugin evaluation summary merge. |
| `npcink-ai-client-adapter` | `master...origin/master`, clean | `90a816e` | Last observed head is the Adapter master consolidation merge. |
| `npcink-workflow-toolbox` | `master...origin/master`, clean before this doc change | `07a77d4` | Toolbox is the coordination index and the only repo changed by this brief. |
| `npcink-ai-cloud` | `master...origin/master [ahead 2]`, dirty | `c2531d62` | Dirty files exist in backend, frontend API, and a new migration. Treat Cloud as requiring a separate runtime/detail pass before release claims. |
| `npcink-cloud-addon` | `codex/post-smtp-diagnostic-taxonomy`, dirty, same head as `origin/master` at observation time | `48e2ba7` | Dirty files exist in README, runtime client docs/code, and tests. Treat Addon changes as unrelated local work unless explicitly scoped. |

The most recent full gate record before this brief was the 2026-07-08 cross-repo
release closeout: `composer quality:matrix:run` passed for Toolkit, Core,
Adapter, Toolbox, Cloud Addon, and Cloud. This brief did not rerun every repo
gate; it only records the current observation and updates documentation.

## Shared Goal

The six projects together should provide governed AI-assisted WordPress
operations:

- operators get fixed, understandable UI entry points;
- AI and Cloud outputs stay suggestions, candidates, evidence packs, or
  proposal-input-ready artifacts;
- final WordPress writes remain governed through Core approval, preflight, and
  audit;
- reusable WordPress write/read callbacks stay in Toolkit;
- OpenClaw or other AI clients use Adapter as a thin channel into the same
  contracts;
- Cloud and Cloud Addon provide hosted processing, signed transport, runtime
  detail, and status without becoming the local WordPress control plane.

The near-term target is not broad feature expansion. The next useful stage is
to improve trust and review clarity on existing surfaces: owner labels, evidence
labels, blocked-state guidance, handoff paths, and no-direct-write posture.

## Project Cards

### `npcink-abilities-toolkit`

Role: reusable WordPress ability contracts.

Current implementation posture:

- owns stable `namespace/name` ability ids, schemas, labels, permission
  metadata, dry-run previews, reusable review artifacts, and host-approved
  callback contracts;
- already owns or is the preferred home for reusable artifacts such as content
  metadata apply plans, taxonomy/tag review sets, internal-link candidates,
  image candidate review/adoption artifacts, media ALT/caption review sets, and
  approved WordPress write callbacks;
- current local branch is clean and synced with `origin/master`.

Boundary:

- must not own proposal approval, audit truth, provider/model routing,
  provider credentials, workflow runtime, MCP gateway policy, Cloud indexing, or
  the decision that a write is authorized;
- commits should happen only when Core/Adapter provide approved host context.

Goal:

- keep ability metadata and dry-run behavior inspectable enough that Toolbox,
  Adapter, OpenClaw, and third-party callers can reuse the same contracts
  without inventing another registry.

### `npcink-governance-core`

Role: governance truth.

Current implementation posture:

- owns proposal intake, approval state, policy/preflight evidence, operation
  classification, app-key governance, sensitive-read governance, and audit
  evidence;
- recent stage work recorded Core contract reuse readiness and reference-plugin
  evaluation guidance;
- current local branch is clean and synced with `origin/master`.

Boundary:

- must not become the product workflow console, provider gateway, reusable
  ability package, Cloud runtime owner, queue/scheduler owner, or final
  WordPress write executor;
- must not absorb Toolbox or Adapter responsibilities simply because governance
  information is useful across projects.

Goal:

- make proposal detail, approval/preflight status, audit events, rejection
  reasons, and operation classification clear enough that write authority stays
  centralized without becoming a broad product control plane.

### `npcink-ai-client-adapter`

Role: thin AI client and OpenClaw channel.

Current implementation posture:

- owns signed/client-facing channel entry, connection posture, Core proposal
  relay, read ability execution, output-reference validation, correlation
  evidence, and allowlisted post-Core execution profiles;
- recent stage work consolidated Adapter master state and preserved thin-channel
  ownership;
- current local branch is clean and synced with `origin/master`.

Boundary:

- must not own first-party ability definitions, approval truth, arbitrary
  approve/reject proxies, workflow runtime, Cloud credentials, provider truth,
  generic recipe marketplace, or direct writes outside Core preflight.

Goal:

- expose OpenClaw and other AI client workflows through the same Toolkit/Core
  contracts with clear payload discipline, authentication posture, execution
  profiles, and reviewable correlation evidence.

### `npcink-workflow-toolbox`

Role: WordPress operator-facing AI workflow surface and platform coordination
index.

Current implementation posture:

- owns fixed buttons, admin/editor UI, content discoverability context,
  suggestion artifacts, image-source UX, Site Check read-only decision routing,
  Core-ready planning artifacts, and cross-repo quality/observation guidance;
- acts as the central documentation index for project-family boundaries because
  repeatable operator workflows converge here;
- current local branch was clean and synced with `origin/master` before this
  review brief was added.

Boundary:

- must not own Core governance truth, final WordPress authorization, reusable
  Toolkit ability definitions, workflow runtime, queue/scheduler truth, vector
  collection lifecycle, provider billing/logs, or direct media/metadata/SEO
  writes;
- the only current direct local write exception is Local Admin Consent for one
  existing attachment as the current post featured image, with Core audit and
  rollback on completion-audit failure.

Goal:

- keep the default product surface useful and review-only: source labels,
  evidence labels, owner/runtime labels, blocked-state guidance, copy/open/review
  actions, and explicit Core handoff paths before adding new buttons.

### `npcink-ai-cloud`

Role: hosted runtime and runtime/detail service.

Current implementation posture:

- owns hosted processing, provider adapters, usage/billing/entitlement service
  evidence, health diagnostics, Site Knowledge runtime/detail, artifact
  handling, and read-only runtime metadata projections;
- recent release work enforced GitHub-only policy markers and promoted the
  remote deploy stdin fix to production;
- current local state is ahead of upstream by two commits and has dirty files,
  including backend repository/routes/models, a support mixin, frontend admin
  API route work, and a new migration.

Boundary:

- must not become WordPress approval/preflight/audit truth, a local ability
  registry, a local workflow registry, prompt/router/preset truth for local
  WordPress governance, or a WordPress write authority;
- Cloud results returned to WordPress should remain `suggestion_only` or
  proposal-input-ready runtime results.

Goal:

- make runtime/detail more inspectable with timelines, run/correlation ids,
  low-cardinality metrics, cause categories, usage/audit separation, retention
  labels, and links back to the local owner surfaces.

### `npcink-cloud-addon`

Role: WordPress-side signed Cloud transport and shallow status bridge.

Current implementation posture:

- owns Cloud Base URL/API key settings, request signing, bounded runtime
  transport, entitlement/status reads, verified artifact receive/ACK transport, Site
  Knowledge delivery/status bridge, and observability forwarding;
- recent merged work preserved signed-transport ownership and GitHub-only
  repository management;
- current local checkout is on `codex/post-smtp-diagnostic-taxonomy` with dirty
  README, runtime-client contract/code, and test files.

Boundary:

- must not own proposals, approval, audit, final WordPress writes, billing
  truth, provider routing truth, prompt/preset truth, workflow runtime, local
  ability registry state, durable run history, or Cloud service-plane logs;
- local buffers may support transport durability, but durable queues, retry
  history, run recovery, Site Knowledge lifecycle, usage, billing, entitlement,
  and diagnostics detail belong in Cloud service storage.

Goal:

- provide a narrow, trustworthy connector experience: connection summary, setup
  and recovery path, test action, grouped status rows, shallow Site Knowledge
  bridge health, and links to Cloud runtime/detail.

## Landed Stage Summary

The 2026-07-08 closeout recorded a complete cross-repo publication and
verification pass:

- Toolkit, Core, Adapter, Toolbox, Cloud Addon, and Cloud all had GitHub PRs
  merged for contract reuse, platform-index, GitHub-only, or runtime policy
  work;
- full configured gates passed across the six target repos;
- Gitee is no longer a current source-control target for this project family;
- ordinary repo work should use shell `git`; use `gh` only for GitHub-specific
  PR/check/API operations;
- the merged stage did not give any project a second registry, approval store,
  runtime queue, provider billing/log owner, or final WordPress write authority.

## Boundary Stop Rules

Stop and write a boundary note before implementing if a proposal would add any
of these outside its accepted owner:

- second ability registry;
- second workflow registry;
- second proposal/approval/audit store;
- second WordPress write executor;
- `confirm_token` or `write_confirmed` behavior in Toolbox;
- direct publish, media import, featured-image, SEO, metadata, or content writes
  that bypass Toolkit/Core/Adapter governance;
- provider key leakage into logs, docs, proposals, REST responses, or AI-visible
  payloads;
- local queues, schedulers, leases, retry ownership, or durable run history in
  Toolbox, Adapter, or Cloud Addon;
- local vector database, indexing lifecycle, stale-index policy, embedding
  provider ownership, rerank ownership, or collection controls in Toolbox or
  Cloud Addon;
- Cloud-side WordPress publish/write authority.

## Suggested Third-Party AI Review Questions

Ask the reviewer to check these points first:

1. Does each public surface name the actual owner for evidence, runtime,
   proposal, approval, preflight, execution, and audit?
2. Are any suggestion-only outputs worded like final truth or automatic action?
3. Do any Toolbox or Addon screens imply provider control-plane ownership,
   workflow runtime ownership, Site Knowledge lifecycle ownership, or final
   WordPress write authority?
4. Do any Toolkit contracts contain provider runtime, prompt/model routing,
   billing/quota, queue, scheduler, approval, or audit responsibilities?
5. Does Adapter remain a thin channel, or has it become a generic workflow
   runner or approval proxy?
6. Does Core stay governance-only, or is product workflow coordination drifting
   back into Core?
7. Do Cloud runtime results remain `suggestion_only` or proposal-input-ready
   rather than authoritative WordPress changes?
8. Are dirty local states in Cloud and Cloud Addon explicitly treated as
   separate review scope before release or PR claims?
9. Are GitHub-only and shell-`git` workflow expectations preserved?
10. Is the next work narrow enough to improve trust labels, blocked states, and
    handoff clarity before adding new product surface area?

## Recommended Next Action

Run a no-code third-party review against this brief, the
`cross-repo-boundary-matrix.md`, and each repository's authoritative owner docs.
The next implementation should be selected only after the review identifies one
high-confidence gap. Prefer a small existing-surface clarity pass over a new
feature, new registry, new runtime, or new write path.
