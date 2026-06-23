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
| Approved execution relay | `npcink-ai-client-adapter` |
| Final WordPress media metadata callback | WordPress Abilities callback |

## Extraction Acceptance Criteria

Move the reusable part to Toolkit only when all of these are true:

1. The artifact shape is stable across Toolbox, OpenClaw, and at least one
   third-party WordPress host.
2. The input is bounded WordPress media metadata and optional reviewed evidence;
   it does not require Toolbox admin state.
3. The output remains `review-only`, deterministic enough to test, and usable
   without a Cloud runtime.
4. The contract preserves a metadata-only no-pixel source policy:
   `current_article_media_metadata_only_no_pixel_vision` by default,
   `operator_supplied_media_metadata_only_no_pixel_vision` for supplied
   snapshots, or `media_library_metadata_only_no_pixel_vision` only when the
   operator explicitly chooses the media-library sample fallback.
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

## Open Public Sample Accelerator

When the local media library is too small, use eval-lab open public samples to
calibrate judge prompts and candidate filters:

```bash
composer eval:media-alt-caption:open-samples
```

This calls the development-only `npcink-eval-lab` task
`media_alt_caption_open_samples`. It samples public Wikimedia Commons metadata
and writes a local `media_alt_caption_operator_trial.v1` artifact that can be
passed to `media_alt_caption_judge_cross`.

This accelerator is intentionally lightweight:

- it stores URLs and public metadata only;
- it does not download images or bundle datasets in the plugin;
- it does not import WIT, LAION, or other large image-text corpora;
- it does not call WordPress, Core, Adapter, Cloud, or Abilities;
- it does not create proposals, queues, executions, or media metadata writes.

Use W3C WAI and WebAIM guidance as reviewer-rule references. Treat Wikimedia
open samples as prompt/filter calibration only, not as proof that the Toolbox
production review set works on a real WordPress media library. Extraction still
requires real local WordPress exports and human visual confirmation.

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
