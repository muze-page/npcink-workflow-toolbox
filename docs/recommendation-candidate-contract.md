# Recommendation Candidate Contract

Status: active internal contract for editor recommendations.

`recommendation_candidate.v1` is the shared shape for editor-facing candidates
that may later be exported to review worksheets, batch dry-runs, or Core
handoff artifacts.

It is additive. Existing response fields such as `summary_layers.items`,
`title_options`, `category_candidates`, `tag_candidates`, `image_candidates`,
and `internal_link_candidates` may remain during migration, but new
recommendation surfaces should also expose `recommendation_candidates` or mark
their items with `contract=recommendation_candidate.v1`.

The shared shape must not replace richer domain contracts. Image candidates
remain `image_candidate.v1` for media review and adoption. Internal-link
candidates remain `internal_link_candidates.v1` for related-content evidence and
manual insertion review. Those surfaces may expose `recommendation_candidate.v1`
as a projection for consistent editor rendering, batch dry-runs, exports, and
review queues, but the original candidate object remains the source of truth.

## Required Fields

- `contract`: `recommendation_candidate.v1`.
- `id`: stable candidate id within the section.
- `kind`: candidate family, such as `title`, `excerpt`, `category`, `tag`,
  `image`, or `internal_link`.
- `label`: short editor-facing label.
- `value`: the proposed visible value or primary candidate text.
- `reason`: concise reason grounded in the current article or evidence.
- `quality_status`: `good`, `review`, or `weak`.
- `quality_score`: integer score from 0 to 100.
- `quality_issues`: list of review notes or gate failures.
- `action_policy`: what the UI may offer for this candidate.
- `write_posture`: always `suggestion_only` in Toolbox responses.
- `direct_wordpress_write`: always `false`.

## Optional Fields

- `confidence`: model or ranking confidence from 0 to 1.
- `target_field`: destination field hint, such as `post_title`,
  `post_excerpt`, `category`, or `post_tag`.
- `evidence_refs`: ids for related Site Knowledge, media, taxonomy, or source
  evidence used for review.

## Action Policies

- `editor_apply_preview_save_required`: the editor may copy the value into the
  current unsaved editor state; the normal WordPress draft save persists it.
- `core_proposal_required`: accepted changes must be packaged for Core review.
- `operator_review_only_no_insert`: the candidate is informational and must be
  applied manually by a human editor.
- `suggestion_only`: no apply action is exposed.

## Current Adoption

- Editor Content Support returns an additive `editor_recommendation_set.v1`
  wrapper on focused results and the local `progressive_recommendations` prefetch
  result. It includes `recommendation_set_id`, `content_fingerprint`,
  `generated_at`, `source_layer`, `latency_profile`, `artifact_counts`,
  `retrieval_sources`, `proposal_targets`, `candidates`, and `no_write=true`.
  The legacy `artifacts`, `governance`, and debug retrieval-source fields remain
  additive compatibility metadata. The wrapper is metadata around candidate
  sections, not the source of write, approval, audit, or learning truth.
- `proposal_targets` are definition-only Core handoff envelopes. Each target
  points from `candidate_id` to a stable `required_ability_id` and bounded
  `proposed_payload_preview`. Targets must not contain raw REST routes,
  submitted proposal ids, execution status, approval status, run logs, retry
  queues, or workflow-runtime state. The only valid status in the current stage
  is `definition_only_user_trigger_required`.
- AI summary suggestions mark excerpt items with the contract while preserving
  the existing `summary_layers.items` shape. The single-summary editor action
  returns `article_summary_suggestions.v1` and does not include taxonomy,
  metadata-delta, or Core handoff payloads.
- AI title suggestions expose `recommendation_candidates` and keep legacy
  `title_options` parsing as a fallback. Title recommendation is a first-class
  single-intent action, separate from summary generation.
- Category and tag shortcuts return `article_taxonomy_suggestions.v1` focused
  artifacts. They rank existing WordPress terms only; new category or tag
  creation is deferred to a later taxonomy governance workflow.
- Internal-link recommendations expose `recommendation_candidates` with
  `kind=internal_link`, but `internal_link_candidates.v1` remains authoritative
  for target post id, target URL, suggested anchor text, placement hints, Site
  Knowledge evidence, and the no-background-patch review policy.
- Image recommendations expose `recommendation_candidates` with `kind=image`,
  but `image_candidate.v1` remains authoritative for provider identity, source
  URL, thumbnail/download URLs, license review, attribution, download tracking,
  AI-generated image metadata, media SEO suggestions, and adoption planning.

## Boundary

This contract is not a write API, approval record, or audit store. It is a
reviewable candidate shape. Batch generation should first export these
candidates as dry-run JSON or XLSX rows. Accepted WordPress writes still need
the existing editor save, Core proposal, or explicitly classified future local
confirmation path.
