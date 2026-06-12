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
4. `tag_suggestions` prefers existing WordPress tags. New tag gaps may appear
   as review-only candidates, but Toolbox must not create terms directly.
5. `summary_terms_optimization` remains the richer workflow for combined
   summary, taxonomy, Site Knowledge evidence, diagnostics, and Core proposal
   preparation.
6. Cloud vectors and historical article learning are useful, but they are
   evidence and ranking inputs. They do not become a second taxonomy registry,
   write authority, prompt registry, or audit store.

## Focused Intents

- `title_suggestions`: hosted AI reads the current title, excerpt, and draft
  text, then returns title candidates. Toolbox parses,
  deduplicates, reranks, and flags weak candidates.
- `summary_suggestions`: hosted AI reads the full draft context and returns
  excerpt candidates. Toolbox strips meta wording, enforces length limits,
  reranks by coverage, and lets the editor copy one candidate into the current
  unsaved excerpt field.
- `category_suggestions`: Toolbox ranks existing WordPress categories by
  current draft token matches and, when supplied by the richer flow, related
  Site Knowledge term evidence. The focused shortcut does not use selected text
  and does not create categories.
- `tag_suggestions`: Toolbox ranks existing WordPress tags by the same rules.
  Review-only new tag gaps may be shown, but Toolbox does not create terms.
- `summary_terms_optimization`: the full workflow that may combine summary,
  taxonomy, Site Knowledge, discoverability evidence, diagnostics, and Core
  handoff preparation.

## Candidate Contract

Focused intents should expose `recommendation_candidate.v1` where practical.
The contract lets batch dry-runs, spreadsheets, and later review queues consume
one common candidate shape while each intent remains independently runnable.

## Ranking Inputs

Local-only ranking can use:

- current draft title, excerpt, and body text;
- selected text only for intents that explicitly operate on a selected
  paragraph, such as polish or paragraph image workflows;
- existing WordPress categories and tags;
- exact or token overlap between draft text and term name, slug, or
  description;
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
- tags are lighter, but new tags still need duplicate and vocabulary review;
- new taxonomy terms are candidates for review, not write operations.

This keeps AI useful without allowing it to reshape the site's information
architecture by accident.

## New Category Policy

AI may help identify a possible category gap, but new categories are structural
site changes. They should not appear as one-click focused shortcut output. A
future implementation may surface AI-proposed category gaps only in the richer
metadata optimization flow, with duplicate checks against the existing category
tree, historical usage evidence, and Core strong review before any category is
created or assigned.

## Batch-Ready Foundation

The same candidate contract should support future batch generation. Batch mode
should first produce dry-run rows, such as JSON or XLSX, with candidate value,
target field, reason, quality status, quality score, and evidence refs. Batch
mode should not skip review just because results are generated in bulk.

Expected first batch targets:

- summary candidates for multiple posts;
- title candidates for multiple posts;
- existing category and tag candidates with evidence;
- review-only taxonomy gap candidates.

Accepted writes still need the normal editor save path, a Core proposal, or a
future explicitly classified local confirmation path.
