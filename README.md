# Npcink Workflow Toolbox

Npcink Workflow Toolbox turns proven AI-assisted WordPress operations into
fixed, review-only buttons for site operators, including Cloud-managed web
search, Cloud-managed image-source candidates, Cloud-managed site knowledge,
and governed handoff flows. The WordPress.org release slug is
`npcink-workflow-toolbox`. The first-version runtime contracts keep the
existing `npcink-toolbox` REST namespace, ability ids, option names, and hook
names for compatibility with Core, Adapter, Cloud, and existing site
integrations.

It is intentionally separate from:

- `npcink-governance-core`, which owns governance, proposal records, approval, and audit;
- `npcink-abilities-toolkit`, which owns reusable WordPress Abilities API contracts;
- provider connector plugins, which can later own richer key management,
  provider selection, quota, and request logs.

## First Version

The first version provides:

- a Npcink admin page at **Npcink -> Workflow Toolbox** when a Npcink host menu
  exists, with a **Tools -> Npcink Workflow Toolbox** fallback for standalone
  installs. Its Overview includes a read-only **Npcink capability health**
  summary for local workflow readiness and route-only compatibility; it is not
  a generic Abilities Explorer, provider picker, request log, or connector
  approval surface;
- a post editor **Npcink Content Support** sidebar whose default buttons focus
  on Npcink review and handoff flows: publish preflight, internal-link
  candidates, image candidates, and article audio candidates. Generic title,
  summary, taxonomy/tag, outline, article-checkup, and current-article ALT
  support remains available through compatible route/rendering paths, plus
  selected-paragraph toolbar checks that do not replace body text;
- a frontend article audio playback entry that renders only already adopted
  WordPress audio metadata near single posts; Cloud generation remains a
  candidate artifact path, and Toolbox may prepare a Core-governed article
  audio adoption plan with lightweight source-content freshness evidence, but
  does not adopt, import, regenerate, or write audio itself;
- a route-only content opportunity helper intent that samples recent, older,
  missing-image, and taxonomy-backed public content for bounded update,
  linking, expansion, or image opportunities; operator-facing site opportunity
  review lives in **Full-site Insights**; media
  ALT/caption helper contracts stay available for editor-sidebar use and future
  batch review sets, but the standalone admin tool is not exposed;
- a **Full-site Insights** tab that builds a local
  `site_ops_insight_pack.v1` from bounded public content, approved comment
  signals, media metadata, taxonomy summaries, Site Context readiness, and
  Cloud availability into a read-only site analysis report with coverage
  metrics, charts, local deterministic summary, dimension views, findings, and
  evidence, without Cloud calls, Core proposals, persistence, or WordPress writes, and prepares a copyable
  `site_ops_cloud_analysis_request.v1` contract. When Cloud is connected and
  the administrator explicitly clicks **Run Cloud analysis**, Toolbox may send
  that request to Cloud runtime for a suggestion-only
  `site_ops_cloud_analysis_result.v1`, without local queues, local run tables,
  Core proposal creation, or WordPress writes;
- a Start panel **Preview Morning Brief** entry that reads bounded local
  public-content evidence and renders a dry-run Nightly Site Inspection preview
  without cron, Cloud calls, Core proposals, persistence, or WordPress writes;
- a disabled-by-default **Local Fallback Preview** WP-Cron setting that can
  overwrite one latest dry-run Morning Brief preview for operator review when
  Cloud is unavailable or not yet connected, without Cloud calls, Core
  proposals, Action Scheduler, custom tables, leases, retries, dead letters, or
  WordPress content writes;
- Cloud-managed web search status, plus read-only Cloud-managed image-source
  and vector availability;
- an operator-filled content discoverability context for SEO, AEO, and GEO
  guidance that can be exposed to third-party AI callers;
- REST endpoints for image-source candidates, site knowledge/search, content
  discoverability, article-support fallback flows, media briefs, and media
  derivative handoffs;
- WordPress Abilities API registrations for the same tool actions;
- static tests and PHP syntax linting.

## Boundary

Toolbox returns suggestions and planning artifacts. It does not directly update
posts, upload media, publish content, or bypass governance. WordPress writes
should continue through WordPress abilities and Core proposal approval.

The default product posture is content support outside the article body:
taxonomy/tag candidates, internal-link candidates, image candidates,
SEO/AEO/GEO suggestions, media metadata plans, duplicate-risk checks, and
publish-readiness checks. Human editors own the article text. Article
Assistant exists only as a fallback workbench for reviewed local draft
artifacts.

Project goals, ownership, and future-session instructions are documented in:

- [Product Positioning](docs/product-positioning.md)
- [Boundary](docs/boundary.md)
- [AI Plugin Overlap Boundary](docs/ai-plugin-overlap-boundary.md)
- [Cross-Repo Boundary Matrix](docs/cross-repo-boundary-matrix.md)
- [Architecture](docs/architecture.md)
- [Feature Ownership And Plugin Boundary](docs/feature-ownership-and-plugin-boundary.md)
- [Roadmap](docs/roadmap.md)
- [Admin Surface Consolidation Summary](docs/admin-surface-consolidation-summary.md)
- [Content Support Product Readiness](docs/content-support-product-readiness.md)
- [Content Support Release And Trial Closeout](docs/content-support-release-trial-closeout.md)
- [Content Support Toolkit Migration History](docs/content-support-toolkit-migration-history-2026-06-21.md)
- [Content Metadata Apply Plan Decision Envelope Closeout](docs/content-metadata-apply-plan-decision-envelope-closeout-2026-06-21.md)
- [Site Knowledge Cloud Addon Bridge Closeout](docs/site-knowledge-cloud-addon-bridge-closeout-2026-06-21.md)
- [Cross-Repo Release And Run Baseline](docs/cross-repo-release-run-baseline-2026-06-21.md)
- [AI Development Quality Workflow](docs/ai-development-quality-workflow.md)
- [GitHub Quality Guardrails Closeout](docs/github-quality-guardrails-closeout-2026-06-26.md)
- [AI Change Envelope Template](docs/ai-change-envelope-template.md)
- [Toolbox 0.1.1 Stage Closeout](docs/toolbox-0.1.1-stage-closeout-2026-06-22.md)
- [WordPress.org Release Readiness Closeout](docs/wordpress-org-release-readiness-closeout-2026-06-29.md)
- [Admin Operator UX Cleanup Summary](docs/admin-operator-ux-cleanup-summary-2026-06-29.md)
- [Content Support Writing Checkup History](docs/content-support-writing-checkup-history.md)
- [Editor Progressive Recommendations Trial](docs/editor-progressive-recommendations-trial.md)
- [Editor AI Image Recommendation Summary](docs/editor-ai-image-recommendation-summary.md)
- [AI Content Composition Abilities](docs/ai-content-composition-abilities.md)
- [Local Automation Runtime Module](modules/local-automation-runtime/README.md)
- [Connector Ability Exposure](docs/connector-ability-exposure.md)
- [Content Discoverability Context](docs/content-discoverability-context.md)
- [Scoped Permissions First Version](docs/scoped-permissions-first-version.md)
- [Security And Performance Release Gate](docs/security-performance-release-gate.md)
- [Security And Performance Closeout](docs/security-performance-closeout-2026-06-21.md)
- [OpenClaw Content Discoverability Handoff](docs/openclaw-content-discoverability-handoff.md)
- [OpenClaw SEO/GEO/AEO Acceptance Summary](docs/openclaw-seo-geo-aeo-acceptance-summary.md)
- [OpenClaw Batch Media Optimization Handoff](docs/openclaw-batch-media-optimization-handoff.md)
- [Content Assistant Surface Lessons](docs/content-assistant-surface-lessons.md)
- [Retired Article Assistant Workbench](docs/article-assistant-workbench.md)
- [Media Optimization V1](docs/media-optimization-v1.md)
- [Batch Automation Governance Plan](docs/batch-automation-governance-plan.md)
- [Media ALT/Caption Review Set](docs/media-alt-caption-review-set.md)
- [Media ALT/Caption Toolkit Validation Plan](docs/media-alt-caption-toolkit-validation-plan.md)
- [Media ALT/Caption Operator Trial](docs/media-alt-caption-operator-trial-2026-06-21.md)
- [Media Optimization Stage Summary](docs/media-optimization-stage-summary.md)
- [Media Optimization Release Checklist](docs/media-optimization-release-checklist.md)
- [Media Optimization Operator Trial](docs/media-optimization-operator-trial.md)
- [Site Ops Cloud Analysis Contract](docs/site-ops-cloud-analysis-contract.md)
- [Full-site Insights Operator Loop](docs/full-site-insights-operator-loop.md)
- [Full-site Insights Stage Closeout](docs/full-site-insights-stage-closeout-2026-06-24.md)
- [Development Workflow](docs/development-workflow.md)
- [ADR-001: Build Toolbox As A Product Surface](docs/decisions/ADR-001-toolbox-as-product-surface.md)
- [ADR-002: Expose Content Context Through Abilities](docs/decisions/ADR-002-content-context-via-abilities.md)
- [ADR-003: Local Admin Consent Requires A Separate Write Boundary](docs/decisions/ADR-003-local-admin-consent-boundary.md)
- [ADR-004: Bundle Local Automation Runtime As An Isolated Module](docs/decisions/ADR-004-bundle-local-automation-runtime-as-isolated-module.md)
- [ADR-005: Use WP-Cron Local Preview And Cloud Batch Runtime For Nightly Automation](docs/decisions/ADR-005-wp-cron-cloud-batch-orchestration.md)

## REST Routes

All routes require a logged-in user with `manage_options`.

- `GET /wp-json/npcink-toolbox/v1/status`
- `POST /wp-json/npcink-toolbox/v1/image-candidates`
- `POST /wp-json/npcink-toolbox/v1/vector-search`
- `POST /wp-json/npcink-toolbox/v1/knowledge-search`
- `POST /wp-json/npcink-toolbox/v1/web-search/test`
- `POST /wp-json/npcink-toolbox/v1/web-search/diagnostics`
- `GET /wp-json/npcink-toolbox/v1/site-knowledge/status`
- `POST /wp-json/npcink-toolbox/v1/site-knowledge/search`
- `POST /wp-json/npcink-toolbox/v1/site-knowledge/sync`
- `POST /wp-json/npcink-toolbox/v1/agent-feedback`
- `POST /wp-json/npcink-toolbox/v1/agent-feedback/summary`
- `POST /wp-json/npcink-toolbox/v1/ai/content-support`
- `POST /wp-json/npcink-toolbox/v1/ai/site-helpers`
- `POST /wp-json/npcink-toolbox/v1/ai/image-generation`
- `POST /wp-json/npcink-toolbox/v1/flows/article-brief`
- `POST /wp-json/npcink-toolbox/v1/flows/article-assistant`
- `POST /wp-json/npcink-toolbox/v1/flows/article-plan`
- `POST /wp-json/npcink-toolbox/v1/flows/image-candidate-adoption-plan`
- `POST /wp-json/npcink-toolbox/v1/flows/article-audio-adoption-plan`
- `POST /wp-json/npcink-toolbox/v1/local-admin-consent/featured-image`
- `POST /wp-json/npcink-toolbox/v1/flows/site-knowledge-review-plan`
- `POST /wp-json/npcink-toolbox/v1/flows/nightly-inspection-review-plan`
- `POST /wp-json/npcink-toolbox/v1/flows/content-metadata-apply-plan`
- `POST /wp-json/npcink-toolbox/v1/flows/media-alt-caption-review-plan`
- `POST /wp-json/npcink-toolbox/v1/flows/media-brief`
- `POST /wp-json/npcink-toolbox/v1/editor/content-support`
- `POST /wp-json/npcink-toolbox/v1/media-derivative-handoff`
- `GET /wp-json/npcink-toolbox/v1/nightly-inspection/cloud-runtime-entitlement`
- `POST /wp-json/npcink-toolbox/v1/nightly-inspection/cloud-batch`
- `GET /wp-json/npcink-toolbox/v1/nightly-inspection/cloud-batch/recent`
- `GET /wp-json/npcink-toolbox/v1/nightly-inspection/cloud-batch/{run_id}`
- `GET|POST /wp-json/npcink-toolbox/v1/nightly-inspection/cloud-batch/{run_id}/result`
- `POST /wp-json/npcink-toolbox/v1/nightly-inspection/cloud-batch/{run_id}/retry`

`/flows/article-brief` remains an API composition primitive for OpenClaw and
external AI callers; it is not exposed as a current operator-facing admin tool.

The status route distinguishes registered Toolbox surfaces from currently
available Cloud execution. Cloud-backed actions report `registered`,
`cloud_required`, `available`, and `unavailable_reason` fields so standalone
Toolbox installs do not imply that Cloud-managed search, image-source, Site
Knowledge, or hosted GPT actions can run without a connected Cloud Addon or
host-provided runtime filter.

Toolbox admin result panels can render governed `operator_feedback` payloads
from Adapter/Core handoff failures. The feedback is for operator revision only;
Toolbox may submit one Core media optimization proposal from the Adapter media
derivative recipe after reviewed metadata and derivative artifact evidence are
present, but it does not approve proposals, execute proposals, or perform
WordPress writes.
The fixed media optimization flow is: select or resolve an existing attachment,
generate a short-lived Cloud preview, review the derivative and adoption
preflight, then submit one Core optimization review. Attachment adoption,
content URL repair, and settings URL repair remain separate governed actions so
hard-coded post URLs and URLs stored in theme or plugin options are not silently
rewritten by preview generation.
Batch media optimization uses bounded review sets: build a review plan,
generate previews for selected candidates, and submit only selected Core
reviews. It is intentionally not presented as one-click whole-site replacement.
Toolbox also bundles `modules/local-automation-runtime/` for the future
`npcink-local-automation-runtime` owner. That module supports Phase 1A Manual Read-Only Preview for Morning Brief evidence, validates dry-run replay fixtures,
and now owns Phase 2 Basic WP-Cron Dry-Run. Phase 1A is a Toolbox-hosted
operator preview, not a runtime execution phase; that manual path does not register hooks,
create runtime job tables, schedule workers, acquire leases, retry actions,
dead-letter failures, persist preview results, approve proposals, execute
Adapter actions, or write WordPress data. Phase 2 Basic WP-Cron Dry-Run is the
Local Fallback Preview; it may register one disabled-by-default WP-Cron hook
that overwrites a single latest-preview option;
it must not call Cloud, create Core proposals, use Action Scheduler, create
custom tables, acquire leases, retry actions, process dead letters, or write
WordPress content. Current Pro planning does not introduce plugin-side Action
Scheduler; Pro batch processing should use Cloud Batch Runtime, with the plugin
limited to read-only entitlement detail, batch intent, status/result sync, and
reviewed Core proposal handoff.
The runtime module remains bundled with Toolbox for release. It should become a
separate plugin only after a new ADR proves independent lifecycle needs, real
runtime state, a stable cross-plugin API, explicit data migration/uninstall
ownership, and graceful Toolbox degradation when the runtime plugin is inactive.
The product posture is Cloud-first, not cloud-only: Pro Cloud Batch Runtime is
the primary commercial path for reliable scoring, entitlement, usage metering,
queue-backed execution, retry, observability, and result retention, while the
local WP-Cron path remains a WordPress-side fallback preview and onboarding aid,
not a second Pro scheduler.
The Pro Cloud Runtime panel can refresh `pro_cloud_runtime` quota detail from
Cloud and disable new submissions when Cloud reports exhausted
`nightly_site_inspection_runs`; this local display is not billing truth.
Action Scheduler is reserved as a future local fallback/substrate candidate only
if a confirmed local-batch requirement justifies the added plugin complexity.
ADR-005 freezes this current split: WP-Cron is the local fallback preview or
future bounded local submit trigger, Cloud Batch Runtime is the Pro execution
path, and neither Toolbox nor Cloud becomes a second WordPress scheduler or
write owner.
The Site Knowledge review plan route builds a blocked Core handoff plan from
Cloud evidence only; it does not approve, preflight, or execute that plan.

## Abilities

Toolbox abilities are server-side tool wrappers. External AI callers provide
task input and receive normalized suggestion payloads; they do not receive
provider API keys or direct provider credentials.

General AI composition guidance is kept in
[AI Content Composition Abilities](docs/ai-content-composition-abilities.md).

When the WordPress Abilities API is available, Toolbox registers:

- `npcink-toolbox/search-image-source`
- `npcink-toolbox/generate-image`
- `npcink-toolbox/search-site-knowledge`
- `npcink-toolbox/cloud-web-search`
- `npcink-toolbox/get-site-knowledge-status`
- `npcink-toolbox/request-site-knowledge-sync`
- `npcink-toolbox/build-article-write-plan`
- `npcink-toolbox/build-article-batch-write-plan`
- `npcink-toolbox/build-article-media-batch-write-plan`
- `npcink-toolbox/build-site-knowledge-review-plan`
- `npcink-toolbox/build-nightly-inspection-review-plan`
- `npcink-toolbox/build-media-derivative-handoff`
- `npcink-toolbox/get-content-discoverability-context`
- `npcink-toolbox/validate-content-discoverability-context`
- `npcink-toolbox/build-content-discoverability-brief`
- `npcink-toolbox/build-ai-article-writing-pack`

The legacy `/vector-search`, `/flows/article-brief`, `/flows/article-assistant`,
and `/flows/media-brief` REST routes remain compatibility surfaces, but they are
no longer registered as public Toolbox abilities. New AI callers should use
`npcink-toolbox/search-site-knowledge`, `npcink-toolbox/build-article-write-plan`,
content-support routes, or editor/media-specific routes instead of those legacy
ability ids.

When `npcink-abilities-toolkit` is active, Toolbox uses its public registration
helpers so the tools can be discovered by existing Npcink consumers.
Toolbox ability ids stay under `npcink-toolbox/*` so they do not collide with
Core governance abilities or first-party WordPress abilities.

Ability metadata includes Toolbox scopes such as `cap.toolbox.image_source`,
`cap.toolbox.vector_search`, and `cap.toolbox.workflow_suggest`. Content context uses
`cap.toolbox.context.read`. The first admin REST surface remains
`manage_options` gated; external AI/app-key authorization should be enforced by
Core or the host that consumes the ability scope metadata. First-version host
integration hooks are `npcink_toolbox_rest_permission` and
`npcink_toolbox_ability_permission`.

## Content Discoverability Context

The admin page defaults to a Start surface that summarizes Cloud runtime,
Site Context, Site Knowledge, and final-write posture before sending operators
to the right work surface. Its Site Context form stores operator-maintained SEO, AEO,
and GEO guidance: site positioning, target audience, brand voice, keywords,
allowed and forbidden claims, exception rules, SEO/AEO/GEO rules, and proposal
fields AI may suggest. It is stored in `npcink_toolbox_content_context`,
separate from connector settings that may contain provider keys.

The context is exposed only as read-only, suggestion-only guidance through
`npcink-toolbox/get-content-discoverability-context`. Third-party AI callers
may also call `npcink-toolbox/validate-content-discoverability-context` to
check filling quality and `npcink-toolbox/build-content-discoverability-brief`
to get the primary lightweight content-support contract: SEO/AEO/GEO guidance,
taxonomy/tag candidates, internal-link hints, proposal fields, and conservative
candidates from supplied post or topic input. Final WordPress writes still
require Core proposal approval.

For natural-language article requests from OpenClaw or another external AI,
`npcink-toolbox/build-ai-article-writing-pack` composes the context,
validation result, discoverability brief, writing instructions, and guardrails
into one suggestion-only pack. It is a convenience fallback for broad prompts,
not the default SEO/AEO/GEO, taxonomy, link, image, or publish-readiness
surface.

The legacy Article Assistant REST flow can still compose one local
`article_draft_v1` workbench artifact from topic, evidence candidates,
image-source candidates, site context, operator notes, and an optional reviewed
draft. It is route-only compatibility, not an operator-facing tool and not a
public Toolbox ability. Reviewed draft write-plan handoffs should use
`npcink-toolbox/build-article-write-plan` through REST or Abilities; normal
editorial work should stay in the editor content-support sidebar.

Toolbox fixed buttons are the operator-click surface for repeatable OpenClaw
flows. They should reuse the same ability ids, plan artifact shapes, Adapter
recipe guidance, and Core proposal handoff as OpenClaw natural-language flows.
Toolbox must not turn those buttons into a separate approval store, media
registry, workflow runtime, prompt/model control plane, or WordPress write
executor.
Batch replacement must be developed and accepted first as an OpenClaw/Adapter
contract with Core approval, commit preflight, execution profile evidence,
per-action results, and Abilities media replacement callbacks. Toolbox then
exposes that accepted path as the fixed `media_optimization_v1` best-practice
button; it must not create a separate batch writer.

The article plan flow and `npcink-toolbox/build-article-write-plan` ability
assemble a Core-ready `article_write_plan` for a reviewed draft. They do not
call Core, approve proposals, publish content, or write WordPress data.
The **Full-site Insights** surface is the operator-facing path for site content
opportunities. The bounded content snapshot helper remains available through
`/ai/site-helpers` for route/internal composition, while the reviewed-draft
write-plan route and Ability stay
available for machine clients, future Cloud bulk import, and explicit API
composition, but they are not exposed as a backend operator tool while there is
no active external-draft import workflow.

The post editor also exposes **Npcink Content Support** as a plugin sidebar
opened from the editor top toolbar. Its visible buttons run fixed Npcink flows
for publish preflight, internal-link candidates, image-source candidates, and
article audio candidates from the current draft context. Generic title,
summary, category, tag, outline, article-checkup, discoverability,
current-article ALT, and comment-reply support remains available through
compatible REST/result-rendering paths, but those entries are not default
buttons when a generic AI plugin already owns similar experiments. Related
existing-post review is handled through publish preflight duplicate-risk checks
and Toolkit-backed internal-link candidates rather than a separate visible
button. The route-only article checkup remains a local full-draft diagnostic
surface for sentence density, fact-gap, tone, and structure review; it points to
paragraphs and editing direction, but does not rewrite, insert, or replace
article text. The sidebar
also prefetches a local-only progressive recommendation
set after the editor opens or the draft stabilizes: existing taxonomy matches,
recent media-library candidates, and local preflight checks are shown quickly,
while Cloud title/summary generation, image-source search, image generation,
deep search, and proposal handoffs remain explicit follow-up actions. The metadata buttons use
lighter draft/taxonomy fast paths and merge their results into one
`article_discoverability_optimization.v1` review surface. The full
`summary_terms_optimization` intent remains available as a compatibility and
diagnostic path when hosted AI, related Site Knowledge, discoverability
evidence, ranking, dedupe, and review metrics are needed together. The same
result shape includes a `content_metadata_delta` P0 artifact with an issue
record, diagnosis, excerpt and existing-term delta, authorization
classification, outcome checks, and learning candidates for later review.
Operators can scope the run to the full article, selected text or block, or a
topic-only brief. Existing WordPress term recommendations remain suggestion
only. Proposed new terms are shown as review-only vocabulary gaps and include a
preview-only Core handoff packet for accepted summary and term choices.
Internal-link results are returned as `internal_link_candidates.v1` candidates
with suggested anchor text, placement hints, and source evidence, but link
insertion stays a human editor action. Publish preflight now returns a unified
`pre_publish_review.v1` panel that points the operator back to summary,
category, tag, internal-link, image, duplicate-risk, and SEO handoff checks.
SEO metadata is prepared as a single-post `seo_meta_handoff_preview.v1`
payload and can be submitted through Adapter as one pending Core review
proposal; Toolbox does not approve, execute, or mutate SEO fields.
See [Editor Progressive Recommendations Closeout](docs/editor-progressive-recommendations-closeout.md)
for the local prefetch contract, quality rules, and verification record.
Current-article image ALT/caption review belongs in the editor sidebar, where
the post context and used images are available. The backend Image Handling tab
only exposes a selected media-library review set for recent images with weak
ALT or caption metadata. Accepted items can be converted into a
`media_alt_caption_core_handoff_plan.v1` through
`/wp-json/npcink-toolbox/v1/flows/media-alt-caption-review-plan`; that route
returns proposal-ready ALT-only payloads but creates no proposal by itself.
After an operator selects reviewed rows, the admin UI can submit those payloads
through Adapter and request Core `approve-and-execute`. Core policy decides
whether a narrow missing-or-weak ALT update is auto-approved; blocked items
remain normal Core review proposals. Toolbox still performs no media upload,
media metadata write, approval, execution, or audit ownership.
ALT/caption candidates reject runtime provenance text such as model/provider
names, prompt labels, and "Generated by" descriptions before review and again
before Core handoff. Caption edits are excluded from the quick batch execution
path and must remain manual review work until Core exposes a separate policy.
Accepted excerpt, existing category, and existing tag choices can be
converted into a dry-run `content_metadata_apply_plan` through
`/wp-json/npcink-toolbox/v1/flows/content-metadata-apply-plan`; the plan uses
`npcink-abilities-toolkit/build-content-metadata-apply-plan` as its Core
handoff ability so third-party plugins do not need to depend on Toolbox. Core still owns proposal
approval and final execution remains outside Toolbox. Existing category/tag
candidates are ranked by `npcink-abilities-toolkit/suggest-post-taxonomy-terms`
and presented by Toolbox for review. That packet exposes proposal-ready action
labels: Generate and apply summary, Recommend and apply tags, Recommend
categories. Summary application and existing
tag assignment can request Core auto-approval when policy allows; category
changes stay recommendation-first by default, and new tag creation remains
deferred to a later taxonomy governance workflow. The panel returns suggestions
only; it does not
insert links, assign terms, create terms, update excerpts or SEO fields, import
media, publish content, store acceptance/audit truth, or write WordPress data.
Do not expand Local Admin Consent to this metadata flow. A future single-post
metadata direct-apply proof would first need a separate
`strong_local_confirmation` UX and audit contract covering exact final values,
old/new metadata, actor and source evidence, confirmation copy, recovery
evidence, and fail-closed audit behavior. Until then, accepted metadata choices
remain Core proposal handoffs.
The image-source button opens a Cloud image recommendation modal: it
automatically searches from the selected paragraph or selected block when
available, combines that with the current draft context, and also lets the
editor enter a manual query. The same modal includes a secondary image-plan
action that runs the existing media brief flow from the saved current post, so
the plan feeds later source search, AI generation, and media SEO review without
becoming a separate sidebar entry. Toolbox sends a bounded visual context
request so Cloud may build a visual brief, use site context vectors managed by
Cloud for reranking, and return media SEO suggestions; these are runtime
details, not local vector/index or provider ownership. Returned images remain
`image_candidate.v1` suggestions with provider, attribution, source,
license-review, and Unsplash download tracking metadata preserved. Editor review
projection is delegated to
`npcink-abilities-toolkit/build-image-candidate-review-artifact`; media import
still flows through a governed adoption plan. When the selected candidate is an
existing WordPress image attachment, the editor may set that one attachment as
the current post's featured image through Local Admin Consent: the logged-in
administrator sees the selected image, clicks one button, Toolbox records
Core-owned audit evidence before and after the write, and no Core proposal is
created. External image URLs, media import, media metadata writes, and
multi-action adoption still use the Adapter/Core/Abilities path.
When Cloud includes an `ai_generation_handoff`, the Toolbox result can show a
reviewed-prompt AI image generation action. The action calls the Cloud Addon
runtime seam with `grok-imagine-image-quality`, returns AI-generated
`image_candidate.v1` candidates, and still requires the local adoption/Core
review path before any media import or featured-image write.
Toolbox builds the adoption plan with proposed media title, alt text,
description, attribution, filename, and featured-image step, submits it through
Adapter's plan-to-proposal bridge, then calls Adapter's unified
`approve-and-execute` action for the created Core proposal. Adapter calls Core
approval and preflight, then executes the allowlisted WordPress ability writes
when policy permits. Toolbox does not import media, mutate SEO/meta fields,
approve, execute, or set the featured image directly.
The only direct featured-image write exception is
`/local-admin-consent/featured-image`, and it accepts existing image
attachments only. If Core audit is unavailable or completion audit fails,
Toolbox fails closed and rolls back the featured-image change.
The high-risk contrast remains governed: reviewed article/media batch plans
that include draft creation, media upload, media metadata, and featured-image
actions are submitted to Core as one `plan_to_proposal_batch` through
`npcink-toolbox/build-article-media-batch-write-plan`; they do not use Local
Admin Consent and do not write posts or media during proposal intake.
The selected-block toolbar also exposes compact paragraph actions: a paragraph
check button that returns clarity, fact-gap, and tone notes without replacement
copy, plus an image-icon paragraph image suggestion button. The image entry uses
the selected paragraph or block as the primary context and defaults to a
media-import plan for later placement, while the sidebar image-source entry
remains the article-level featured-image recommendation path.
The same image-source picker contract can be reused by future image fields,
including settings screens. Those callers may pass a manual query and optional
context, receive the selected `image_candidate.v1` plus media SEO suggestions,
and then hand off any setting or media write through the appropriate governed
ability path. Toolbox does not write setting values directly.
Image-source picker optimization stays lightweight in Toolbox and heavier in
Cloud. The reusable picker should provide one search box, concise candidate
cards, a selected-image inspector, media SEO fields, and selection/adoption
buttons that change by usage context. Cloud should optimize abstract article
topics into concrete visual queries, use site-context vectors for reranking,
dedupe near-identical candidates, filter low-quality or watermarked images,
return license/source/attribution evidence, and provide short query rewrites
when no images are found. Toolbox may cache recent modal results for a few
minutes and show those Cloud signals, but it must not own provider routing,
image indexing, media-library writes, settings writes, or long-term adoption
history.

The post editor **Npcink Content Support** sidebar owns high-frequency Npcink
review and handoff actions because those actions need the current article
context: publish preflight, internal-link candidates, image candidates, and
article audio candidates. Generic writing, metadata, article-checkup,
discoverability, comment, and current-article ALT capabilities stay available
as compatible route-only or result-rendering paths rather than default visible
buttons. The admin
**Workflows** tab stays focused on site helpers, fallback
bundles, governed handoffs, and media planning rather than draft-side writing
buttons.
Full-site Insights owns site-level content opportunity review. The bounded
`content_snapshot_suggestions` helper remains route-only for hosted AI
composition, and reviewed draft write-plan contracts remain available only
through REST and Abilities for future bulk import or machine-client composition.
Media ALT/caption helper contracts remain available to editor-sidebar flows and
future batch review sets, where the operator has either current article context
or an explicit selected review set. In all cases, Toolbox samples only the
supplied public-site or media metadata, Cloud produces reviewable suggestions,
and no media library, post, SEO, proposal, crawler, or queue state is changed
locally.
Publish preflight, internal-link candidates, image candidates, and article
audio candidates are the default editor sidebar buttons. Summary suggestions,
category suggestions, tag suggestions, article checkup, discoverability, and
current-article ALT checks stay route-compatible but are demoted from the
default button list to avoid duplicating generic AI plugin features.
Internal-link candidate assembly is delegated to
`npcink-abilities-toolkit/resolve-internal-link-targets`; Toolbox only passes
editor context plus optional Cloud Site Knowledge evidence and renders the
review/copy/open surface.
The admin **Image Handling** tab defaults to media work, with **Batch Optimize
Images** as the first visible workbench. Single-image ALT and optimization
actions start from the media-library attachment details panel or media list row
actions, then carry that attachment into the same selected-image workbenches
used for batches. The old `tab=image&tool=optimize` and
`toolbox_tab=tools&toolbox_tool=media-derivative` URLs are deprecated and fall
back to `tab=image&tool=batch-optimize`; Toolbox no longer exposes a standalone
one-image picker page. Media library bulk actions can send selected images to
`tab=image&tool=bulk-alt` or `tab=image&tool=batch-optimize`. Site helpers
remain secondary low-frequency checks, while content preparation and reviewed
handoffs live in their own admin surface.

`media_optimization_v1` names the fixed governed image optimization workflow,
not a new workflow runtime or persistent run store. Toolbox stores media
optimization defaults for the preview and handoff flow, accepts one-run
operator overrides, lets an operator start one image from the media library, and
lets selected media-library images enter a bounded batch workbench. The batch
workbench may ask Adapter/Core to approve and execute selected proposals after
previews exist; Core policy decides whether execution is allowed or the proposal
remains pending for review. Operators can request a bounded one-run
aspect-ratio crop before resize/watermark processing. Operators can keep the Toolbox default
watermark, disable it for the run, use a text watermark, or use the configured
Toolbox image/logo watermark source with one-run placement settings.
Text watermark overrides pass text, font, color, background, margin, position,
and opacity directly to the same Cloud request shape used by OpenClaw handoffs.
If an operator starts from a hard-coded
local uploads URL, the same surface can call the local read-only
`npcink-abilities-toolkit/resolve-media-attachment-by-url` ability through Adapter
`run-read-ability`, show bounded match evidence, and fill the attachment ID for
the same preview/proposal flow. The single-image action surface dispatches the
bounded Adapter media-derivative recipe, polls the short-lived Cloud artifact
result, renders the same-origin signed Adapter preview proxy when available,
and can submit a Core replacement proposal with the artifact evidence. The
	Batch Optimize Images workbench can build a bounded batch conversion plan, show
	candidates and skipped reasons, generate selected previews, and submit selected
	items for review; final replacement execution stays in the governed
	Adapter/Core path outside the default admin workbench. The admin batch surface is intentionally
	a fixed operator flow: operators choose selected media or a media range and
	processing goal first, while exact ID, date, exclusion, and dimension filters remain in an
	advanced disclosure for exceptions. Toolbox
can also build a local media reference repair plan for exact hard-coded URL
matches and submit that plan to Core from-plan intake as `patch-post-content`
actions. For theme/plugin settings that store hard-coded media URLs, Toolbox can
build a filtered settings reference repair plan with excluded formats and minimum
image dimensions, then submit exact `patch-setting-value` actions to Core.
Toolbox does not store the site media policy, own Cloud credentials, create an
artifact registry, approve proposals, execute proposals, replace files, write
attachment metadata, patch post content, or update options/theme mods directly.
See [Media Optimization V1](docs/media-optimization-v1.md) for the fixed
workflow contract and expansion rule, and
[OpenClaw Batch Media Optimization Handoff](docs/openclaw-batch-media-optimization-handoff.md)
for the two-stage batch implementation order.

## Connector Configuration

Toolbox no longer stores provider keys for web search, public image-source
providers, or vector infrastructure. Configure Cloud connectivity in the Cloud
Addon and configure provider keys, routing, quotas, and health in the Cloud
operator surface.

Image-source search supports `auto`, `cloud`, and provider hints such as
`unsplash`, `pixabay`, or `pexels`, but the public provider keys and provider
selection live in Cloud. Toolbox sends one Cloud runtime request and returns a
normalized `image_source_candidates` payload for any AI caller that needs
images, whether the use case is article drafting, media planning, layout
suggestions, reference selection, or another image-dependent workflow.
When editor context is available, Toolbox includes only a truncated visual brief
input: image use, title/excerpt snippets, selected paragraph text, manual query,
and bounded candidate limits. Cloud may optimize the visual query and rerank
candidates with site-context vectors, but Toolbox only consumes the normalized
candidate list, match reasons, and optional media SEO suggestions.
Supported image-use labels include featured, paragraph, inline, and setting
image contexts. The label informs Cloud ranking and UI copy; it does not grant
write authority.
`ai_generated` remains explicit: callers may provide a reviewed generated image
URL, or a host may handle `npcink_toolbox_ai_image_generation_request` and
return generated-image candidates. Toolbox still does not own model routing,
provider billing, media import, or final WordPress writes.

Every returned image candidate is normalized to `image_candidate.v1` while
preserving legacy URL fields for existing callers. The normalized fields include
`source_type`, `provider`, `provider_origin`, `download_url`,
`thumbnail_url`, `prompt`, `model`, `license_review_status`, attribution,
provenance, and warnings. Stock providers return `source_type=stock`;
generated candidates return `source_type=ai_generated`.

Editor review surfaces can pass those already retrieved candidates to
`npcink-abilities-toolkit/build-image-candidate-review-artifact` to get a shared
`image_candidate_review.v1` artifact and `recommendation_candidate.v1`
projection. That Toolkit ability does not search providers, generate images,
download files, import media, or write WordPress state.

After an operator reviews one candidate, `npcink-abilities-toolkit/build-image-candidate-adoption-plan`
or `POST /wp-json/npcink-toolbox/v1/flows/image-candidate-adoption-plan`
can build an `image_candidate_adoption_plan`. That plan targets only
`npcink-abilities-toolkit/upload-media-from-url`, `npcink-abilities-toolkit/update-media-details`, and
optional `npcink-abilities-toolkit/set-post-featured-image` through Core proposal intake. It
does not import media, update metadata, set a featured image, or write
WordPress directly. The editor one-click adoption flow uses Adapter
`/proposals/from-plan` followed by `/proposals/{proposal_id}/approve-and-execute`
so the visible action can complete only through Core approval, preflight, audit,
and allowlisted Abilities execution.
The standalone admin `tool=image-candidate-adoption` surface is deprecated and
is not exposed as a Toolbox button; single-article image adoption belongs in the
post editor image recommendation sidebar, while batch adoption of new image
candidates requires a separate reviewed batch design.
For a selected image that is already a WordPress attachment, the editor may use
`POST /wp-json/npcink-toolbox/v1/local-admin-consent/featured-image` instead of
building an import proposal. That route is limited to one current post and one
existing image attachment, uses the shared operation classifier as
`local_admin_consent`, records Core audit events, and does not create a Core
proposal.

Cloud-managed web search is provided by Npcink Cloud. Toolbox no longer
stores local web search provider keys, registers a local web search ability,
or exposes a local web search REST route. AI workflows that need current
external evidence, comparison material, Chinese source lookup, source coverage,
product research, support context, or article preparation should call the Cloud
runtime and preserve returned source URLs in their evidence packs. Toolbox does
not verify truth, write WordPress content, or expose provider keys.

The legacy `vector-search` route is a compatibility pointer only.
Toolbox no longer stores vector provider keys, embedding models, dimensions,
provider endpoints, collection names, or local vector database settings. New
Ability callers should use Cloud-managed Site Knowledge for semantic site
context through `npcink-toolbox/search-site-knowledge`.

Cloud-managed site knowledge is the preferred high-level ability surface for
semantic site search, related content, writing context, internal-link
candidates, refresh suggestions, image-context lookup, FAQ candidates, content
gap analysis, and publish preflight duplicate checks. Toolbox registers
`search-site-knowledge`, `get-site-knowledge-status`, and
`request-site-knowledge-sync` as WordPress Abilities. These abilities call
Npcink Cloud through the Cloud Addon runtime seam or the
`npcink_toolbox_site_knowledge_cloud_request` host filter; Toolbox does not
store Cloud credentials, own vector collection lifecycle, or write WordPress
content.

Sync requests send bounded public WordPress manifests: published posts and
pages, plus recent approved comments attached to those indexed public entries.
Comment payloads include only public comment text and source IDs needed for
Cloud indexing; moderation, edits, deletion, and final writes remain local
WordPress responsibilities.

When Cloud Addon exposes `npcink_cloud_addon_site_knowledge_change_bridge_health()`,
it owns the public content-change bridge for Site Knowledge. Toolbox then shows
that bridge health in the Site Knowledge status response and does not register
its legacy local auto-sync hooks. Standalone installs without the Cloud Addon
bridge show an install-and-verify requirement instead of running a Toolbox-owned
fallback queue. Manual Site Knowledge sync remains available from Toolbox, but
automatic public-change delivery belongs to Cloud Addon. Toolbox does not store
provider credentials, run embeddings locally, own the index lifecycle, or write
WordPress content.

The admin **Site Knowledge** tab lets operators start or refresh the
Cloud-managed index and inspect coverage without configuring vector provider
keys in Toolbox. Cloud owns embedding, vector storage, and detailed run health;
Toolbox only starts explicit sync requests, displays returned status, and
surfaces Cloud Addon bridge health when automatic public-change delivery is
available.
The **Cloud Checks -> Site Knowledge** panel is a read-only verification surface
for Cloud-managed site knowledge search; status and refresh operations stay in
Site Knowledge. It does not expose provider keys, embedding settings,
collection names, or vector database configuration.
The **Cloud Checks -> Search** panel uses Cloud auto execution for a bounded
Toolbox reachability check; provider selection, Jina Reader toggles, routing
diagnostics, entitlement, quota, billing, and request logs belong in Cloud
Addon or Cloud service-plane surfaces.
The **Cloud Checks -> Image** panel checks Cloud image-source candidates and can
generate a short-lived derivative preview for one existing media-library image,
while Content Operations coverage and Agent quality summaries live in Cloud
Addon Monitoring.
including text or image/logo watermark overrides for that run. Single-image
Core proposal submission and URL repair handoffs remain in the one-image review
flow; batch proposal submission remains in **Image Handling -> Batch Optimize
Images**.

Provider responses return normalized fields by default. Set **Include provider
raw responses** to include redacted raw provider payloads for debugging.
Production hosts can define `NPCINK_TOOLBOX_DISABLE_RAW_RESPONSES` to force raw
payloads off even if the local option is enabled.

For hardening release checks, run `composer smoke:security-permission-debug`
with the default gate. To capture an authenticated local or staging latency
baseline, set `NPCINK_TOOLBOX_BASE_URL` plus admin REST authentication headers
and run `composer perf:baseline`. The release checklist is documented in
[Security And Performance Release Gate](docs/security-performance-release-gate.md).

## Development

```bash
composer test:all
```

The current gate runs PHP syntax linting and static contract checks.
