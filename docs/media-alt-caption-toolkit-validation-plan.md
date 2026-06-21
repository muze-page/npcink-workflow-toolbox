# Media ALT/Caption Toolkit Validation Plan

Status: validation gate; do not migrate code yet.

## Decision

`media_alt_caption_review_set.v1` is a Toolkit extraction candidate, but the
current implementation should stay in Toolbox until an operator trial proves a
stable, reusable, review-only artifact.

The likely future owner is `npcink-abilities-toolkit`, but only for the
artifact normalizer or planner contract. Toolbox remains the operator/editor UI
for media selection, review state, Cloud/provider evidence display, and Core
handoff presentation.

This validation gate exists to prevent two wrong moves:

- moving UI glue or hosted runtime behavior into Toolkit;
- turning a review set into direct media metadata writes before Core, Adapter,
  and Abilities have an accepted apply path.

## Current Owner Split

| Area | Owner |
| --- | --- |
| Media selection, admin/editor display, operator review state | Toolbox |
| Optional hosted AI or image-context runtime | Cloud or host runtime |
| Reusable review artifact, if proven stable | `npcink-abilities-toolkit` |
| Proposal, approval, preflight, and audit | `npcink-governance-core` |
| Approved execution relay | `magick-ai-adapter` |
| Final WordPress media metadata callback | WordPress Abilities callback |

## Extraction Acceptance Criteria

Move the reusable part to Toolkit only when all of these are true:

1. The artifact shape is stable across Toolbox, OpenClaw, and at least one
   third-party WordPress host.
2. The input is bounded WordPress media metadata and optional reviewed evidence;
   it does not require Toolbox admin state.
3. The output remains `review-only`, deterministic enough to test, and usable
   without a Cloud runtime.
4. The contract preserves `media_library_metadata_only_no_pixel_vision` unless
   a separate Cloud evidence field is supplied by a runtime owner.
5. It creates no proposal, execution, queue, scheduler, media derivative run,
   or direct WordPress write.
6. It does not update ALT, caption, description, attachment files, featured
   images, SEO metadata, post content, or media URLs.
7. Operator trial data shows repeated value: selected counts, accepted or
   lightly edited suggestions, blocked reasons, and manageable visual review
   effort.
8. Static tests in both repos can prove no direct media metadata writes, no
   Cloud runtime dependency, and no proposal creation.

## Operator Trial Protocol

Run the current Toolbox review set against ordinary media libraries before any
extraction branch:

1. Record total scanned items, selected items, blocked items, and blocked
   reasons.
2. For each selected item, record whether the operator accepted, edited, or
   rejected the ALT and caption suggestions.
3. Record manual visual review cost and whether the metadata-only source policy
   produced misleading suggestions.
4. Record whether the same artifact would be useful from OpenClaw without the
   Toolbox screen.
5. Stop if operators mostly need image-pixel analysis, provider/model choice,
   UI-only filtering, or immediate apply buttons.

## Stop Rules

Keep the work in Toolbox if it depends on editor/admin UI state, current-screen
selection, or display-only recovery guidance.

Keep the work in Cloud if it needs image-pixel analysis, model routing,
provider credentials, usage metering, request logs, retry runtime, or hosted
quality evaluation.

Keep writes out of this artifact. If operators need apply behavior, the apply
path must be designed separately through Toolkit write ability schemas, Core
proposal governance, Adapter execution profiles, and final WordPress ability
callbacks.

Do not expand Local Admin Consent to media ALT/caption metadata from this
validation plan.

## First Extraction Shape

If the trial passes, extract only a small Toolkit helper that accepts supplied
media metadata and returns a `media_alt_caption_review_set.v1` artifact with:

- eligibility summary;
- selected items;
- blocked items;
- per-item review reasons;
- source policy;
- `needs_human_visual_check`;
- no-write posture fields.

Toolbox would continue to call the helper, render the result, collect operator
review, and hand off any later governed apply path.
