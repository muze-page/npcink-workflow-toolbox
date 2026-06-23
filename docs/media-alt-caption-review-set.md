# Media ALT/Caption Review Set

Status: P0 review-only contract.

## Purpose

The media ALT/caption review set turns the existing **AI Site Helpers -> Media
ALT suggestions** flow into a bounded operator artifact. It helps an operator
review images already used by one article and inspect possible ALT or caption
text before any governed write path exists. Recent media-library sampling stays
available only as an explicit advanced fallback.

This stage is intentionally not a media metadata writer.

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
gate. The gate removes ALT/caption candidates that only duplicate existing
title, ALT, or caption text; look like URLs, source attribution, camera-default
strings, or filenames; are generic placeholders; or conflict with supplied
metadata such as horizontal/vertical wording. Items that need review but have
no usable metadata-only candidate are blocked as
`candidate_quality_insufficient` instead of showing low-value text to the
operator.

The response defaults to a small review set and caps local selection at 10
items. Items outside the current selection, items with missing attachment ids,
items already complete for this P0, or items filtered by the candidate-quality
gate are reported as blocked items with a reason.

The admin UI renders the review set as:

- eligibility and blocked counts;
- source policy and contract version;
- selected item rows with ALT candidates and caption candidate;
- candidate quality flags and filtered-candidate notes for audit/debug review;
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
- direct writes, proposal creation, queues, and derivative replacement runs
  remain disabled.

Do not add an "apply", "bulk update", "replace", or "submit selected" button
for ALT/caption metadata in Toolbox until the Future Apply Path below exists.

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

Until that path exists, Toolbox must not directly write media ALT, caption,
description, replacement URLs, or attachment file data from this review set.

## Non-Goals

- no custom queue table;
- no background worker;
- no automatic media metadata update;
- no automatic proposal creation;
- no final WordPress write;
- no claim that Toolbox itself has viewed image pixels;
- no local image recognition model or bundled vision dataset;
- no reuse of media derivative replacement execution for metadata writes.
