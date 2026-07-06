# Media ALT/Caption Review Set

Status: P0 reviewed ALT candidate quality and handoff-preview path.

## Purpose

The media ALT/caption review set turns the existing **AI Site Helpers -> Media
ALT suggestions** flow into a bounded operator artifact. It helps an operator
review images already used by one article and inspect possible ALT or caption
text before any governed write path exists. Recent media-library sampling stays
available only as an explicit advanced fallback.

This stage is intentionally not a Toolbox-owned media metadata writer. The
admin UI may prepare a local dry-run handoff preview for reviewed ALT-only
rows, but it must not submit that preview to Adapter, create a Core proposal,
ask Core to approve or execute, or write media metadata in the current P0.
Core policy remains the only place that can later approve a low-risk ALT
update after a separately accepted write path exists.

## Contract

The local response artifact is `media_alt_caption_review_set.v1`.

It is returned inside the existing `/ai/site-helpers` response when the intent
is `media_alt_suggestions`.

Required posture:

- `write_posture`: `suggestion_only`;
- `final_write_path`: `core_proposal_required`;
- `direct_wordpress_write`: `false`;
- `proposal_created`: `false`;
- `execution_created`: `false`;
- `source_policy`: defaults to
  `current_article_media_metadata_only_no_pixel_vision`.

Other allowed source policies:

- `operator_supplied_media_metadata_only_no_pixel_vision` for editor or eval
  supplied bounded snapshots;
- `media_library_metadata_only_no_pixel_vision` only for the explicit recent
  media-library sample fallback.

Optional weak-metadata follow-up:

- `image_context_evidence_request`: `image_context_evidence_request.v1`;
- `runtime_owner`: `cloud_or_host_runtime`;
- `expected_response_contract`: `image_context_evidence.v1`;
- `no_local_model`: `true`;
- `no_media_write`: `true`;
- `direct_wordpress_write`: `false`.

Required operational fields:

- `eligibility_summary`;
- `selected_items[]`;
- `blocked_items[]`;
- `operator_next_action`;
- `retryable`;
- `retry_guidance`;
- per-item `status`, `review_reasons`, and `result_ref`.

Candidate quality fields are machine-readable review hints, not write
authorization. Each selected or quality-blocked row may include:

- `candidate_quality.score`;
- `candidate_quality.tier`;
- `candidate_quality.basis_summary`;
- `candidate_quality.primary_alt_candidate`;
- `candidate_quality.automation_recommendation`;
- `candidate_quality.visual_evidence_required`;
- flat compatibility aliases such as `candidate_quality_score`,
  `candidate_quality_tier`, and `automation_recommendation`.

`candidate_quality.automation_recommendation` is a legacy machine-readable
review hint, not an executable automation command. Values must remain in the
triage-only family, such as `visually_review_alt_caption`,
`request_visual_evidence_or_skip`,
`review_caption_manually_or_skip_alt_handoff`,
`confirm_context_terms_or_edit_alt`, and
`eligible_for_local_preview_after_visual_check`. Values that imply automatic
apply, auto-approval, Core submission readiness, write readiness, or proposal
readiness are not allowed in this contract. Flat aliases are read-only mirrors
of nested `candidate_quality.*` values; they
must not be treated as authorization inputs or stronger signals than the nested
values. `candidate_quality.*`, `candidate_quality_score`,
`candidate_quality_tier`, `automation_recommendation`, and any legacy
ready-for-handoff aliases are forbidden as Adapter/Core proposal inputs,
ability execution inputs, or WordPress write inputs.
`tests/run.php` statically guards the allowed triage-only recommendation family
and fails if the old Core-ready recommendation value returns to the provider
client.

The `eligibility_summary` may also include
`local_preview_candidate_count`, `context_confirmation_count`,
`caption_review_only_count`, `visual_evidence_request_count`, and
`insufficient_quality_count`. These counts help the UI and eval tooling route
operator attention, but every row still requires human visual confirmation and
any accepted ALT write still uses a future Core-governed handoff path. New P0
responses must not emit deprecated ready-for-handoff aliases. If an old runtime
still returns such an alias, consumers must ignore it for button enablement,
Adapter/Core submission, proposal creation, auto-approval, and WordPress writes.
`local_preview_candidate_count` is also a UI triage hint only and is forbidden
as an input to any Core proposal, Adapter execution profile, ability execution
schema, or write decision.

The follow-up `/flows/media-alt-caption-review-plan` response is
`media_alt_caption_core_handoff_plan.v1`. In the current P0 it is preview-only
and may include per-row `future_contract_preview` objects that point at the
future `npcink-abilities-toolkit/update-media-details` contract with only:

- `attachment_id`;
- `alt`;
- `dry_run=true`;
- `commit=false`;
- an idempotency key.

Every selected action and future contract preview must also carry
`submission_status: preview_only_not_submitted`,
`target_contract_status: future_or_unavailable`, `not_submittable: true`,
`proposal_created: false`, `execution_created: false`, and
`direct_wordpress_write: false`. Current P0 consumers must not send
`future_contract_preview` objects as Adapter/Core request bodies.

Caption, title, description, source, and attribution fields are not part of
the ALT handoff preview. If a later governed write path accepts those fields,
Core must keep the proposal in manual review or reject the candidate. Current
P0 previews must keep `proposal_created=false`, `core_submission:
preview_only_not_submitted`, and `direct_wordpress_write=false`.

## Source Boundary

The default review set uses only images already attached to, featured by, or
embedded in one current article. The admin form requires a Post ID for this
default path and records `media_scope: current_article_used_images` plus
`post_context`.

Each review item is still metadata-first:

- attachment id;
- source within the article, such as featured media or content image;
- title;
- caption;
- description excerpt;
- current ALT;
- filename;
- MIME type;
- thumbnail URL;
- attachment URL.

The advanced `media_scope: media_library_sample` fallback may inspect a bounded
recent media-library metadata sample when the operator intentionally chooses
that scope. It is not a site-wide batch runner, media-library indexing job, or
write path.

It does not inspect image pixels. Every selected item has
`needs_human_visual_check: true`, and operators must visually confirm ALT and
caption suggestions before any later handoff.

When metadata is too weak to produce useful candidates, Toolbox may include an
`image_context_evidence_request.v1` packet for up to 10 blocked items. This is
a bounded request artifact for a Cloud-owned or host-owned visual recognition
runtime. Toolbox does not run a local vision model, persist provider keys,
create a queue, create a Core proposal, or write media metadata from this
request. In short: no local vision model and no local write path.

If the Cloud Addon exposes `request_image_context_evidence()`, Toolbox may call
that named helper once for the bounded request and rebuild the local review set
with the returned `image_context_evidence.v1`. If the helper is missing or
Cloud returns an error, Toolbox keeps the request packet visible and the local
metadata-only flow still succeeds. The returned visual summary, scene, objects,
or visible text may become additional candidate basis, but the resulting
suggestions still remain review-only. The evidence is not treated as final
truth; every selected item still requires human visual confirmation against the
real image.

## Current P0 Behavior

P0 selects image attachments when:

- ALT is missing;
- ALT appears weak or filename-like;
- caption is missing;
- title appears filename-like.

Before an item becomes selected, Toolbox now applies a local candidate-quality
rendering filter. The filter removes ALT/caption candidates that only duplicate
existing title, ALT, or caption text; look like URLs, source attribution,
camera-default strings, or filenames; are generic placeholders; or conflict
with supplied metadata such as horizontal/vertical wording. This is local UI
triage, not Core policy ownership or final write validation. Items that need
review but have no usable metadata-only candidate are blocked as
`candidate_quality_insufficient` instead of showing low-value text to the
operator.

The same gate classifies candidate basis so operators can focus on the rows
that need real judgment:

- `visual_fact`: legacy compatibility value for unconfirmed external visual
  evidence produced from optional `image_context_evidence.v1` visual summaries,
  scenes, objects, or visible text. UI must render it as "external visual
  evidence, unconfirmed" or equivalent, not as established visual fact;
- `metadata_fact`: produced from existing ALT, caption, or description fields;
- `context_only`: produced from title, filename, or other context that may
  describe the asset but may not be visible in the image.

Location phrases and proper-name context from non-visual metadata are marked
with `needs_context_confirmation`, `candidate_review_status:
needs_context_confirmation`, and `candidate_confidence: context_required`.
The admin UI does not select those rows by default. An operator must either
confirm the location/proper-name context or edit it out before the row can be
counted for handoff. The follow-up handoff planner repeats the same gate and
blocks unconfirmed rows as `context_confirmation_required`, so the rule does
not depend on front-end behavior.

Rows that only have a caption suggestion and no usable ALT candidate are kept
as `candidate_review_status: caption_review_only`. They can help an operator
inspect the media record, but this ALT batch action does not count them as
ready handoff rows. Their next action is
`review_caption_manually_or_skip_alt_handoff`.

The quality gate also rejects runtime provenance text such as "Generated by",
model/provider names, prompt labels, dates, or Cloud execution descriptions.
Those strings describe how an image was produced, not what the image shows, and
must never become default ALT or caption text. The same rejection is applied
again when a selected item is turned into a local handoff preview so an edited
review value cannot reintroduce provenance copy. Toolbox never produces a
Core-submittable draft in this P0; future Core/Abilities write paths must
repeat the gate before accepting any proposal input.

The response defaults to a small review set and caps local selection at 10
items. Items outside the current selection, items with missing attachment ids,
items already complete for this P0, or items filtered by the candidate-quality
gate are reported as blocked items with a reason.

The admin UI renders the review set as:

- eligibility and blocked counts;
- ready/context/caption-only/visual-evidence quality counts;
- source policy and contract version;
- selected item rows with ALT candidates and caption candidate;
- candidate quality flags and filtered-candidate notes for audit/debug review;
- candidate score, tier, and review hint for triage;
- candidate fact type, confidence, and context-confirmation status;
- blocked item details;
- optional image context evidence request details for weak metadata;
- an explicit "No media metadata was changed" notice.

## Stage Closeout Decision

This stage should stop at the review-set layer for now.

The goal is not to make Toolbox automatically batch-edit the media library. The
goal is to turn media ALT/caption suggestions into a reviewable, governable
batch candidate surface:

1. Toolbox finds candidates, displays eligibility, selected items, blocked
   reasons, and review guidance.
2. Operators visually confirm suggestions against the real image.
3. Future accepted changes move through Abilities, Core, Adapter, and final
   WordPress ability callbacks.

The current review set is worth keeping because it is lightweight and useful:

- it gives operators a quick inventory of weak or incomplete media metadata;
- it proves the batch UX shape for eligibility, blocked reasons, selected
  items, retry guidance, and operator next action;
- it keeps AI output suggestion-only and makes sample limitations explicit;
- it creates a reusable pattern for later taxonomy/tag and internal-link review
  sets without adding write risk.

Full batch apply is intentionally deferred. Building it now would require a
cross-repo write path for media metadata updates, including Abilities schemas
and dry-run previews, Core proposal and preflight handling, Adapter execution
profile allowlisting, per-action results, and final WordPress callbacks. That
cost is not justified until real operator usage shows that media ALT/caption
review sets repeatedly produce enough accepted changes to need batch apply.

The current stage is considered complete when:

- `/ai/site-helpers` returns `media_alt_caption_review_set.v1`;
- the admin UI renders selected and blocked items;
- weak metadata can produce a bounded `image_context_evidence_request.v1`
  without local image recognition;
- every selected item requires visual review;
- the result states that no media metadata was changed;
- admin UI and translation strings use review/preview wording and avoid
  apply, approve, submit, update, ready-to-write, legacy ready-for-handoff,
  dry-run-preview-payload, or similar write-authorization wording for quality
  scores, review hints, counts, and preview rows;
- responses state that quality scores, review hints, and preview payloads do
  not authorize proposals, approvals, execution, or media writes;
- direct writes, proposal creation, queues, and derivative replacement runs
  remain disabled.

Do not add an "apply", "bulk update", "replace", "submit selected", or
"submit and update" button for ALT/caption metadata in Toolbox until the Future
Apply Path below exists. A P0 button may only prepare a local handoff preview
and must not call Adapter, Core proposal, approval, execution, or media write
routes.

## Restart Conditions

Restart the write path only when all of these are true:

- real usage shows frequent batches with meaningful selected counts, for
  example 20 or more reviewable items across ordinary media libraries;
- operators are accepting or lightly editing a material share of suggestions;
- `npcink-abilities-toolkit` has a media metadata update ability contract with
  dry-run preview;
- `npcink-governance-core` can intake, approve, preflight, and audit the media
  metadata proposal;
- `npcink-ai-client-adapter` has an explicit execution profile for the approved media
  metadata ability;
- the UI can still present partial failure, retry guidance, and no-write
  fallback states without becoming a queue or workflow runtime.

Until then, the useful next action is observation: use the review set against
real media libraries, inspect selected counts, suggestion quality, blocked
reasons, and manual review cost.

## Future Apply Path

Applying accepted ALT/caption changes requires a separate governed path:

1. `npcink-abilities-toolkit` defines the media metadata update ability schema
   and dry-run preview.
2. `npcink-governance-core` accepts the proposal, approval, preflight, and audit
   truth.
3. `npcink-ai-client-adapter` relays the approved action through an allowlisted
   execution profile.
4. WordPress Abilities perform the final write callback.
5. Toolbox productizes that accepted path as a fixed operator button.

Low-risk automatic approval, if introduced later, belongs to
`npcink-governance-core` policy, not Toolbox. The first eligible shape should be
limited to filling missing ALT text only, after the candidate-quality gate has
passed, runtime provenance/source text has been rejected, the batch is bounded,
and Core can record old values, actor/source evidence, final values, execution
results, and rollback evidence. Overwriting existing ALT, changing captions, or
handling people/sensitive imagery should remain manual review by default.

Until that path exists, Toolbox must not directly write media ALT, caption,
description, replacement URLs, or attachment file data from this review set.

## Non-Goals

- no custom queue table;
- no background worker;
- no automatic media metadata update;
- no automatic proposal creation;
- no final WordPress write;
- no Adapter/Core proposal submission from the preview button;
- no claim that Toolbox itself has viewed image pixels;
- no local image recognition model or bundled vision dataset;
- no reuse of media derivative replacement execution for metadata writes.
