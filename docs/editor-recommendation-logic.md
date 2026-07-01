# Editor Recommendation Logic

Status: active design note for editor content-support recommendations.

The editor sidebar exposes focused recommendation intents. Each intent should
do one job, return reviewable candidates, and avoid direct WordPress writes.

## Discussion Summary

The editor panel is narrow and task-oriented. A user who clicks one button, such
as summary generation, expects one focused result, not a full metadata workflow
with diagnostics, taxonomy, evidence, and Core handoff controls. The current
direction is therefore:

- keep one button per focused job;
- keep the rich metadata workflow as a separate advanced action;
- return compact, reviewable candidates in the focused result view;
- show evidence and diagnostics only when they help review, not as the primary
  content;
- never let Toolbox perform direct WordPress metadata writes.

This applies especially to title, summary, category, and tag suggestions. These
features are part of a batch-ready recommendation foundation, but the editor UX
must still behave like a single-action tool.

## Product Decisions

1. `title_suggestions` is independent from summary generation. It should not
   read selected text, because selected text can bias a post-level title toward
   a local paragraph. It uses the current title, excerpt, and draft context.
2. `summary_suggestions` is AI-generated excerpt copy. It reads the full draft
   context, returns a small number of candidates, and lets the editor fill the
   current excerpt field before saving the draft.
3. `category_suggestions` is focused on existing WordPress categories. The
   shortcut should not create categories and should not show a Core handoff
   packet.
4. `tag_suggestions` is focused on existing WordPress tags. It should not show
   proposed new tags or vocabulary-gap candidates in the first-stage editor
   shortcut, because vocabulary management is a separate governance task.
5. `summary_terms_optimization` remains the richer workflow for combined
   summary, taxonomy, Site Knowledge evidence, diagnostics, and Core proposal
   preparation.
6. Cloud vectors and historical article learning are useful, but they are
   evidence and ranking inputs. They do not become a second taxonomy registry,
   write authority, prompt registry, or audit store.
7. Image and internal-link recommendations may expose a
   `recommendation_candidate.v1` review projection, but their domain contracts
   remain authoritative. Image adoption uses `image_candidate.v1`; internal-link
   review uses `internal_link_candidates.v1`.

## First-Stage Closed Loops

The first editor stage keeps five default fixed buttons and supporting route
paths:

- the editor prefetches `progressive_recommendations` from local WordPress
  context after the editor opens or the draft stabilizes. This is a Layer 0
  helper: existing taxonomy, recent local media candidates, and local preflight
  checks only. It does not call Cloud, generate images, run deep search, or
  create a workflow run. The sidebar keeps it hidden unless it needs operator
  attention;
- title, summary, category, and tag suggestions accept one operator instruction,
  regenerate candidates, and may feed editor fields or Core review handoff
  previews, but they are route-compatible support flows rather than default
  visible buttons;
- image recommendations use the current article or selected paragraph plus the
  operator image preference text, then continue through `image_candidate.v1`
  review and the existing media adoption path;
- internal-link recommendations pass editor context and optional Cloud Site
  Knowledge evidence to `npcink-abilities-toolkit/resolve-internal-link-targets`,
  show target, anchor, and placement hints, then offer copy-link and open-target
  actions; they do not create backend post-content patches or mutate the current
  draft;
- article narration and audio summary generate review-only listening candidates
  whose adoption stays on the Core-governed article-audio handoff path;
- publish preflight aggregates readiness issues and routes operators back to
  the focused tools. It is a closing checklist, not a replacement editing
  workspace.

The second editor stage keeps the same backend contracts and improves the
author loop:

- title and summary candidates show explicit use buttons that fill the current
  unsaved editor field;
- category and tag candidates show existing-term choices, then submit selected
  terms through the Core review handoff;
- image candidates stay in the image-source modal and continue through the
  existing media adoption flow;
- internal-link candidates show target article, anchor text, and placement
  evidence with explicit actions to copy the link or open the target. Toolbox
  must not insert links or patch post content in the background;
- publish preflight renders a suggested handling list that routes operators
  back to the focused tools. It must not create a parallel apply surface or
  bypass Core proposals.

## Focused Intents

- `title_suggestions`: hosted AI reads the current title, excerpt, and draft
  text, then returns title candidates. Toolbox parses,
  deduplicates, reranks, and flags weak candidates.
- `summary_suggestions`: hosted AI reads a local `fast_brief` source package by
  default and returns excerpt candidates. The default fast path does not block
  on Cloud Site Knowledge; it only includes bounded vector context when a prior
  short cache entry is already available, and reports timing for the cached
  vector lookup and hosted AI call. Any vector context is used only for coverage
  and site-style hints while the current draft brief remains the factual source.
  The default hosted prompt uses `fast_summary_v2`: a short JSON-only request
  for three excerpt fields, with length, meta-wording, coverage, and reranking
  handled by local PHP post-processing. The result view also offers an advanced
  full-context rerun that keeps the richer quality contract as a slower fallback.
  Toolbox strips meta wording, enforces length limits, reranks by coverage, and
  lets the editor copy one candidate into the current unsaved excerpt field.
- `category_suggestions`: Toolbox presents existing WordPress category
  candidates ranked by `npcink-abilities-toolkit/suggest-post-taxonomy-terms`.
  Current draft text and, when supplied by the richer flow, related Site
  Knowledge term evidence are passed to Toolkit as ranking context. The focused
  shortcut does not use selected text and does not create categories.
- `tag_suggestions`: Toolbox presents existing WordPress tag candidates from
  the same Toolkit ability. Proposed new tag gaps are deferred to a later
  taxonomy governance workflow; Toolbox does not create terms from this panel.
- `summary_terms_optimization`: the full workflow that may combine summary,
  taxonomy, Site Knowledge, discoverability evidence, diagnostics, and Core
  handoff preparation.

## Candidate Contract

Focused intents should expose `recommendation_candidate.v1` where practical.
The contract lets batch dry-runs, spreadsheets, and later review queues consume
one common candidate shape while each intent remains independently runnable.

For image and internal-link recommendations, the shared contract is only a
review, export, and batch dry-run projection:

- image recommendations keep the full `image_candidate.v1` object as the
  adoption source of truth, including provider, source URL, license review,
  attribution, download tracking, media SEO, and generated-image metadata;
  editor review projections come from Toolkit's
  `image_candidate_review.v1` artifact after Toolbox has retrieved candidates;
- internal-link recommendations keep Toolkit's `internal_link_candidates.v1` as
  the source of truth, including target post, URL, suggested anchor, placement
  hint, supporting evidence, and the no-background-patch review policy;
- projection fields such as `label`, `value`, `reason`, `quality_status`, and
  `action_policy` should point back to the source candidate rather than replace
  it.

Editor responses also expose an additive `editor_recommendation_set.v1` wrapper
with a content fingerprint, source layer, latency profile, artifact counts,
candidate refs, retrieval-source hints, `no_write=true`, and definition-only
Core handoff envelopes. A handoff envelope points to a stable ability id and a
bounded payload preview; it does not include raw REST routes, submitted proposal
ids, execution status, approval status, run logs, retry queues, or workflow
runtime state. The wrapper lets the UI show high-confidence local candidates
quickly and then route the operator into a focused tool. It is not a workflow
run record, approval store, feedback store, or write API.

## Progressive Timing

The progressive path is intentionally split by latency:

- Layer 0: `progressive_recommendations`, local-only, target 0-300 ms. It may
  use current editor state, existing WordPress categories/tags, current article
  media metadata, recent image attachments, and local preflight checks.
  Ranking is weighted toward the title first, then excerpt and selected text,
  then body text, so a short but specific draft can still produce useful local
  candidates. The reviewable default list is capped at eight candidates.
- Focused fast tools: image and internal-link buttons remain explicit default
  operator actions; title, summary, category, tag, and outline helpers stay
  callable support paths. Summary defaults to `fast_brief`; image-source lookup
  uses `fast_first`; neither path performs final writes.
- Slow/enhanced work: full-context summary reruns, image generation, deep search,
  duplicate checks, and proposal handoffs stay explicit second-stage actions.

The editor applies a 2.5 second timeout to progressive prefetch. If that misses,
the UI keeps the last local recommendation set or shows a local unavailable
state instead of blocking the editor. This timeout is a UX fallback, not a queue
or background runtime.

## Ranking Inputs

Local-only ranking can use:

- current draft title, excerpt, and body text;
- selected text only for intents that explicitly operate on a selected
  paragraph, such as paragraph review or paragraph image workflows;
- existing WordPress categories and tags;
- exact or token overlap between draft text and term name, slug, or
  description;
- exact term-name matches in the title are stronger than body-only token
  overlap. Slug or alias overlap is useful evidence only when enough tokens
  match the current editor context. Description-only and single weak-token
  matches are downgraded and do not enter the high-confidence recommendation
  set;
- local preflight warnings projected as `recommendation_candidate.v1` review
  items with `operator_review_only_no_write`, so the operator sees missing
  title, excerpt, terms, or featured-image work without creating a write path;
  progressive preflight candidates keep a stable review order of title,
  excerpt, terms, then featured image;
- English stopword-only overlaps are ignored. Existing taxonomy terms enter the
  high-confidence recommendation list only when the current draft or related
  evidence gives a meaningful signal; otherwise they remain local profile
  context.
- Recent media-library items without a text match are downgraded to
  `operator_review_only_no_write` review references instead of immediate Core
  proposal candidates. When local media scores tie, newer attachments stay
  ahead so the operator reviews likely-current assets first.
- runtime quality gates for length, meta wording, duplication, and unsupported
  claims.

Cloud-assisted ranking can additionally use:

- vector search over historical published articles;
- historical taxonomy usage on semantically related posts;
- similarity scores and source references returned as evidence;
- site-level style and vocabulary patterns.

Cloud vectors should remain evidence and ranking input. They must not become a
second WordPress write authority, taxonomy registry, prompt registry, or audit
store. Accepted writes still go through editor save, Core proposal, or an
explicitly classified local confirmation path.

## AI Generation Boundaries

AI is used where generative language is the actual product:

- titles need wording options, so hosted AI generates candidates and Toolbox
  applies local quality gates;
- summaries need public-facing preview copy, so hosted AI generates excerpt
  candidates and Toolbox rejects meta framing, unsupported claims, weak length,
  and poor coverage.

AI should be more constrained for taxonomy:

- categories represent site structure and should be chosen from existing
  categories by default;
- tags are lighter, but first-stage tag recommendations still stay within
  existing WordPress vocabulary;
- new taxonomy terms are deferred to a later taxonomy governance workflow, not
  produced by focused editor shortcuts.

This keeps AI useful without allowing it to reshape the site's information
architecture by accident.

## New Category Policy

AI may help identify a possible taxonomy gap in a future governance workflow,
but new categories are structural site changes and new tags can still create
vocabulary sprawl. They should not appear as focused shortcut output. A future
implementation may surface taxonomy-gap rows only in a separate vocabulary
governance workflow, with duplicate checks against existing terms, historical
usage evidence, and Core strong review before any term is created or assigned.

## Batch-Ready Foundation

The same candidate contract should support future batch generation. Batch mode
should first produce dry-run rows, such as JSON or XLSX, with candidate value,
target field, reason, quality status, quality score, and evidence refs. Batch
mode should not skip review just because results are generated in bulk.

Expected first batch targets:

- summary candidates for multiple posts;
- title candidates for multiple posts;
- existing category and tag candidates with evidence.

Taxonomy-gap review is a later workflow and should use separate batch rows from
the focused editor recommendation loop.

Accepted writes still need the normal editor save path, a Core proposal, or a
future explicitly classified local confirmation path.
