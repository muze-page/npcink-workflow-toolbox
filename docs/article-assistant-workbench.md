# Article Assistant Workbench

Article Assistant is the local, single-article workbench for composing existing
Toolbox and Abilities outputs into one reviewable `article_draft_v1` artifact.
It is not a cloud writing product, batch publishing surface, workflow runtime,
or second approval plane.

## Contract

- REST route: `POST /wp-json/magick-ai-toolbox/v1/flows/article-assistant`
- Ability id: `magick-ai-toolbox/build-article-assistant`
- Artifact type: `article_assistant_workbench`
- Composition role: `article_assistant_workbench`
- Recipe id: `article_draft_v1`
- Recipe ref: `workflow/wordpress_article_draft`
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
- `article_outline`: deterministic section guidance for a human or external AI
  caller.
- `article_draft_candidate`: reviewed draft content when supplied, otherwise
  notes plus a not-ready marker.
- `discoverability_pack`: SEO/AEO/GEO suggestion contract from the existing
  content context ability path.
- `article_risk_report`: blocked claims, source/context review needs, legal
  posture, and `ready_for_proposal`.
- `article_write_plan`: included only when the reviewed draft exists and risk
  checks pass.

## Boundaries

Article Assistant may call provider-backed Toolbox abilities for evidence,
image-source candidates, and vector context. It must not call or provide a cloud
writer. It must not submit Core proposals, approve proposals, execute proposals,
publish content, update SEO metadata, import media, or enqueue background
writing jobs.

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
