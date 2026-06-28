# Retired Article Assistant Workbench

Article Assistant was the local, single-article workbench for composing
existing Toolbox and Abilities outputs into one reviewable `article_draft_v1`
artifact. It is now retired from the operator-facing Toolbox admin surface and
from the public Toolbox Ability catalog.

The legacy REST route remains only as a compatibility path for older callers.
New workflows should use the smaller surfaces around a human-written article:
taxonomy/tag choices, internal links, image candidates, SEO/AEO/GEO
suggestions, media metadata, publish/readiness checks, or the reviewed draft
handoff route when a human-reviewed draft already exists.

## Surface Budget

This workbench is intentionally small. Its job is to arrange existing Toolbox
and Abilities outputs into a reviewable local artifact, then optionally produce
one Core-ready draft proposal when the operator supplies reviewed draft text.

Do not present this legacy workbench as an article generator, autonomous writer,
Cloud writing feature, bulk publishing tool, default Toolbox product surface,
operator-facing admin tool, or public Toolbox Ability.

The current budget is:

- one article per run;
- optional reviewed draft body supplied or approved locally by the operator;
- one optional `article_write_plan` for `npcink-abilities-toolkit/create-draft`;
- no prompt library, authoring runtime, scheduler, background writing job, or
  batch article console;
- no default button that promises to write the article body;
- no Cloud article generation, Cloud article import, or Cloud-produced writing
  plan.

## Contract

- REST route: `POST /wp-json/npcink-toolbox/v1/flows/article-assistant`
- Ability id: none. The former `npcink-toolbox/build-article-assistant`
  Ability is retired.
- Artifact type: `article_assistant_workbench`
- Composition role: `article_assistant_workbench`
- Recipe id: `article_draft_v1`
- Recipe ref: `npcink-abilities-toolkit/recipes/article-draft`
- Execution posture: `local_operator_orchestration`
- Final write path: `core_proposal_required`
- Direct WordPress writes: `false`
- Workflow runtime: `false`
- Batch execution: `false`

## Inputs

The workbench accepts one topic plus optional operator details: working title,
goal, target audience, angle, tone, target word count, reference URLs,
must-include points, must-avoid points, draft notes, and reviewed draft body.

The reviewed draft body is intentionally optional. Without it, Toolbox returns
research, image, outline, discoverability, and risk artifacts, but does not
claim the result is ready for Core proposal intake.

## Output Shape

The workbench returns:

- `article_goal_brief`: topic, title, goal, audience, angle, source policy, and
  operator constraints.
- `research_evidence_pack`: web/source candidates, operator reference URLs, and
  optional local knowledge matches.
- `image_candidates`: image-source candidates with attribution metadata when a
  configured image provider is available.
- `article_outline`: deterministic section guidance for a human editor or an
  external AI caller, not a finished article body.
- `article_draft_candidate`: reviewed draft content when supplied, otherwise
  notes plus a not-ready marker.
- `discoverability_pack`: SEO/AEO/GEO suggestion contract from the existing
  content context ability path.
- `article_risk_report`: blocked claims, source/context review needs, legal
  posture, and `ready_for_proposal`.
- `article_write_plan`: included only when the reviewed draft exists and risk
  checks pass.

## Boundaries

The legacy route may call provider-backed Toolbox methods for evidence,
image-source candidates, and vector context. It must not call or provide a
cloud writer. It must not submit Core proposals, approve proposals, execute
proposals, publish content, update SEO metadata, import media, or enqueue
background writing jobs.

Cloud Addon can still provide hosted runtime for separately governed provider
or knowledge tasks, but it does not own this local control plane and must not
become the article writing owner.

## Operator Flow

1. Fill topic and constraints.
2. Review returned evidence, context, outline, and image candidates.
3. Add or revise the reviewed draft body locally.
4. Rebuild the workbench until `article_risk_report.ready_for_proposal` is
   true.
5. Submit only the returned `article_write_plan` to Core from-plan intake.
6. Let Core handle proposal review, preflight, approval, audit, and final
   authorized ability execution.

## Core Handoff Smoke

Run the local Core handoff proof with:

```bash
composer smoke:article-core
```

The smoke builds one reviewed `article_write_plan` through Toolbox REST, submits
that plan to Core `/proposals/from-plan`, and verifies Core creates one pending
`npcink-abilities-toolkit/create-draft` proposal. It also verifies the handoff
stays draft-only, dry-run, `commit=false`, `commit_execution=false`, and that no
WordPress post is created during proposal intake. By default the script purges
the Core proposal/audit rows it created; set
`NPCINK_TOOLBOX_ARTICLE_CORE_SMOKE_PURGE=0` to inspect them after a run.

For normal editorial operations, use the smaller support buttons:
taxonomy/tag recommendations, internal-link candidates, image candidates,
content discoverability suggestions, media metadata, and publish/readiness
preflight. For reviewed draft creation handoff, use the reviewed draft handoff
route and `npcink-toolbox/build-article-write-plan` instead of restoring Article
Assistant as a product entry.
