# Media ALT/Caption Operator Trial - 2026-06-21

Status: local real-media read-only trial.

## Purpose

This trial validates the current `media_alt_caption_review_set.v1` surface
before any Toolkit extraction work. It checks whether the review set can scan a
real local WordPress media library, return selected and blocked items, and keep
the whole flow review-only.

The trial is not a migration approval. It is one evidence point for the
[Media ALT/Caption Toolkit Validation Plan](../../media-alt-caption-toolkit-validation-plan.md).

## Trial Command

```bash
composer smoke:media-alt-caption-trial
composer eval:media-alt-caption:export
composer eval:media-alt-caption:judge-cross
composer eval:media-alt-caption:export-batch
composer eval:media-alt-caption:judge-cross-batch
composer eval:media-alt-caption:open-samples
```

The command runs through WP-CLI against the configured local WordPress site. It
uses a local host filter for the hosted site-helper response so the trial can
exercise the existing `/ai/site-helpers` route without requiring Cloud runtime
availability.

`composer eval:media-alt-caption:export` writes the selected
`media_alt_caption_operator_trial.v1` cases to local `build/eval/`.
`composer eval:media-alt-caption:judge-cross` passes that local artifact to
the development-only `npcink-eval-lab` task
`media_alt_caption_judge_cross`. The eval-lab output is AI-assisted review
evidence only; it is not final acceptance truth or write authorization.

Use the `*-batch` commands to accelerate sample collection before making an
extraction decision. The batch exporter pages real local media metadata through
the same `/ai/site-helpers` route, keeps each request capped at 10 items, and
aggregates a local `media_alt_caption_operator_trial.v1` artifact for
eval-lab. This is not a product batch workflow and does not create a queue,
Cloud run, Core proposal, execution, or media metadata write.

For long provider-backed evals, keep `MEDIA_ALT_CAPTION_JUDGE_RESUME=1` and
`MEDIA_ALT_CAPTION_CHECKPOINT_EVERY=1`. The batch judge command reuses an
existing `build/eval/media-alt-caption-batch-cases.json` file by default so
the input fingerprint remains stable across retries; set
`MEDIA_ALT_CAPTION_FORCE_EXPORT=1` only when the sampled cases should be
refreshed. If a 36+ case run is interrupted, rerun the same command and
eval-lab will skip completed matching cases from the same input fingerprint.
Use `MEDIA_ALT_CAPTION_JUDGE_OFFSET` only when you intentionally split a run
into smaller windows. Use `MEDIA_ALT_CAPTION_JUDGE_OUTPUT_JSON`,
`MEDIA_ALT_CAPTION_JUDGE_OUTPUT_MD`, and `MEDIA_ALT_CAPTION_JUDGE_OUTPUT_CSV`
when a full run should write to dedicated report paths.

If local media volume is too small for reviewer calibration, use
`composer eval:media-alt-caption:open-samples`. That command proxies to
eval-lab and exports bounded Wikimedia Commons public metadata into
`media_alt_caption_operator_trial.v1`. It is an eval-only accelerator: no
images are downloaded, no large third-party dataset is bundled, no WordPress
data is touched, and no write or proposal path is authorized. Open samples can
stress judge prompts and filter assumptions, but they do not replace this
trial's real WordPress media evidence or human visual confirmation.

## Boundary Checked

- real image attachments only; no generated fixture media;
- `media_library_metadata_only_no_pixel_vision`;
- `suggestion_only`;
- no direct media metadata writes;
- no proposal creation;
- no execution creation;
- no media derivative run;
- every selected item requires human visual review;
- batch eval keeps `MEDIA_ALT_CAPTION_PAGE_SIZE` capped at 10 per request;
- attachment title, caption, description, ALT, attached file, metadata hash,
  URL, and modified timestamp remain unchanged.

## Local Trial Result

Command result: pass.

Local WordPress sample:

| Metric | Result |
| --- | --- |
| Scanned image attachments | 10 |
| Eligible items | 2 |
| Selected items | 2 |
| Blocked items | 8 |
| Max items | 10 |
| Selected reason counts | `missing_caption=2` |
| Blocked reason counts | `metadata_complete_for_p0=8` |

Attachment ids checked:

- `283886`
- `283887`
- `283888`
- `283934`
- `283869`
- `284060`
- `283844`
- `8053`
- `7786`
- `7774`

Evidence:

- `/ai/site-helpers` returned `media_alt_caption_review_set.v1`.
- The trial used a local host filter instead of requiring Cloud runtime
  availability.
- `direct_wordpress_write=false`.
- `proposal_created=false`.
- `execution_created=false`.
- `media_derivative_run_created=false`.
- Every selected item required human visual review.
- All attachment metadata snapshots were unchanged before and after the REST
  call.

## AI-Assisted Review Result

Command result: pass.

Eval-lab task: `media_alt_caption_judge_cross`.

Provider-backed profiles:

- `gpt55`
- `grok43`
- `deepseek`

Cross-judge result:

| Metric | Result |
| --- | --- |
| Input contract | `media_alt_caption_operator_trial.v1` |
| Output contract | `media_alt_caption_ai_judge_cross.v1` |
| Cases reviewed | 36 |
| Requested/completed | `36/36` |
| Source fingerprint | `1215c016ca060e7f4fc6b75965d7806e2623eef1` |
| Accepted | 5 |
| Edited | 3 |
| Rejected | 27 |
| Misleading | 1 |
| Partial | `false` |

Profile reliability:

| Profile | Successful judgments | Provider failures |
| --- | ---: | ---: |
| `gpt55` | 36 | 0 |
| `grok43` | 24 | 12 |
| `deepseek` | 36 | 0 |

Top flags:

| Flag | Cases |
| --- | ---: |
| `needs_human_visual_check` | 36 |
| `caption_redundant` | 32 |
| `too_generic` | 32 |
| `metadata_insufficient` | 29 |
| `filename_like` | 24 |
| `provider_error` | 12 |
| `unsupported_visual_claim` | 2 |

Attachment-level result:

| Attachment | Outcome | Average score | Passes | Flags |
| ---: | --- | ---: | ---: | --- |
| `8053` | `accepted` | `0.640` | `1/3` | `caption_redundant`, `too_generic`, `needs_human_visual_check`, `metadata_insufficient` |
| `7774` | `accepted` | `0.577` | `2/3` | `needs_human_visual_check`, `provider_error` |
| `7598` | `edited` | `0.440` | `0/3` | `too_generic`, `caption_redundant`, `needs_human_visual_check`, `provider_error`, `unsupported_visual_claim` |
| `6242` | `rejected` | `0.117` | `0/3` | `too_generic`, `filename_like`, `caption_redundant`, `needs_human_visual_check`, `provider_error` |
| `6241` | `rejected` | `0.117` | `0/3` | `filename_like`, `too_generic`, `caption_redundant`, `metadata_insufficient`, `needs_human_visual_check`, `provider_error` |
| `6240` | `rejected` | `0.133` | `0/3` | `metadata_insufficient`, `too_generic`, `filename_like`, `caption_redundant`, `needs_human_visual_check`, `provider_error` |
| `6239` | `rejected` | `0.100` | `0/3` | `too_generic`, `filename_like`, `caption_redundant`, `metadata_insufficient`, `needs_human_visual_check`, `provider_error` |
| `1377` | `rejected` | `0.150` | `0/3` | `too_generic`, `caption_redundant`, `metadata_insufficient`, `needs_human_visual_check`, `provider_error` |
| `6238` | `rejected` | `0.100` | `0/3` | `metadata_insufficient`, `filename_like`, `too_generic`, `caption_redundant`, `needs_human_visual_check`, `provider_error` |
| `6237` | `rejected` | `0.100` | `0/3` | `filename_like`, `too_generic`, `caption_redundant`, `metadata_insufficient`, `needs_human_visual_check`, `provider_error` |
| `6236` | `rejected` | `0.117` | `0/3` | `metadata_insufficient`, `too_generic`, `filename_like`, `caption_redundant`, `needs_human_visual_check`, `provider_error` |
| `5175` | `rejected` | `0.117` | `0/3` | `metadata_insufficient`, `too_generic`, `filename_like`, `caption_redundant`, `needs_human_visual_check`, `provider_error` |
| `4874` | `accepted` | `0.807` | `2/3` | `metadata_insufficient`, `caption_redundant`, `needs_human_visual_check` |
| `1493` | `rejected` | `0.423` | `0/3` | `too_generic`, `caption_redundant`, `needs_human_visual_check`, `metadata_insufficient` |
| `1692` | `rejected` | `0.267` | `0/3` | `metadata_insufficient`, `too_generic`, `filename_like`, `needs_human_visual_check` |
| `1691` | `rejected` | `0.133` | `0/3` | `filename_like`, `too_generic`, `metadata_insufficient`, `needs_human_visual_check` |
| `1687` | `rejected` | `0.150` | `0/3` | `filename_like`, `too_generic`, `caption_redundant`, `needs_human_visual_check`, `metadata_insufficient` |
| `1686` | `rejected` | `0.117` | `0/3` | `filename_like`, `too_generic`, `caption_redundant`, `metadata_insufficient`, `needs_human_visual_check` |
| `1628` | `accepted` | `0.533` | `1/3` | `filename_like`, `caption_redundant`, `too_generic`, `needs_human_visual_check`, `metadata_insufficient` |
| `1027` | `misleading` | `0.250` | `0/3` | `unsupported_visual_claim`, `caption_redundant`, `too_generic`, `filename_like`, `needs_human_visual_check`, `metadata_insufficient` |
| `1022` | `rejected` | `0.200` | `0/3` | `too_generic`, `filename_like`, `caption_redundant`, `metadata_insufficient`, `needs_human_visual_check` |
| `1045` | `rejected` | `0.167` | `0/3` | `too_generic`, `metadata_insufficient`, `needs_human_visual_check` |
| `1029` | `rejected` | `0.200` | `0/3` | `too_generic`, `filename_like`, `caption_redundant`, `metadata_insufficient`, `needs_human_visual_check` |
| `967` | `rejected` | `0.200` | `0/3` | `too_generic`, `filename_like`, `caption_redundant`, `needs_human_visual_check`, `metadata_insufficient` |
| `1025` | `rejected` | `0.217` | `0/3` | `too_generic`, `filename_like`, `caption_redundant`, `needs_human_visual_check` |
| `968` | `rejected` | `0.200` | `0/3` | `too_generic`, `filename_like`, `caption_redundant`, `needs_human_visual_check`, `metadata_insufficient` |
| `1023` | `rejected` | `0.250` | `0/3` | `caption_redundant`, `filename_like`, `too_generic`, `needs_human_visual_check` |
| `827` | `rejected` | `0.133` | `0/3` | `too_generic`, `caption_redundant`, `metadata_insufficient`, `needs_human_visual_check`, `filename_like` |
| `811` | `rejected` | `0.133` | `0/3` | `metadata_insufficient`, `filename_like`, `too_generic`, `caption_redundant`, `needs_human_visual_check` |
| `807` | `rejected` | `0.133` | `0/3` | `filename_like`, `too_generic`, `caption_redundant`, `needs_human_visual_check`, `metadata_insufficient` |
| `769` | `rejected` | `0.467` | `0/3` | `too_generic`, `caption_redundant`, `needs_human_visual_check`, `metadata_insufficient` |
| `767` | `edited` | `0.827` | `2/3` | `caption_redundant`, `needs_human_visual_check` |
| `766` | `edited` | `0.507` | `0/3` | `too_generic`, `caption_redundant`, `needs_human_visual_check`, `metadata_insufficient` |
| `765` | `rejected` | `0.417` | `0/3` | `too_generic`, `caption_redundant`, `needs_human_visual_check`, `filename_like` |
| `764` | `rejected` | `0.483` | `0/3` | `too_generic`, `caption_redundant`, `needs_human_visual_check`, `metadata_insufficient` |

Interpretation:

- The selected candidates still did not create direct writes, proposals,
  executions, or media derivative runs.
- The 36-case run found one misleading case where generated text contradicted
  existing title/file evidence (`Horizontal` versus `Vertical`), so the review
  set must keep visual confirmation and conflict checks before any handoff.
- Most rejected cases were not provider-quality failures alone: common issues
  were duplicate captions, title/file-name reuse, metadata insufficiency, and
  generic text that did not describe actual visual content.
- `grok43` had 12 provider failures during the run; the result is still useful
  because `gpt55` and `deepseek` completed all 36 cases, but this profile
  should not be treated as a stable release gate until provider reliability is
  improved.
- The useful product direction is not direct application. It is a guarded
  review queue that filters duplicate/file-name-like candidates, marks
  conflict risk, and keeps every suggested ALT/caption behind human visual
  confirmation.

## Follow-Up Decision

Do not move the implementation to `npcink-abilities-toolkit` yet. The sample
size is now sufficient to expose the shape of the problem, and the dominant
problem is candidate quality rather than lack of volume: 28 of 36 cases were
`rejected` or `misleading`.

Before any extraction approval, the Toolbox candidate filter must stay tighter
than the original 36-case run:

- block caption candidates that only duplicate existing title, ALT, or caption;
- block ALT candidates that are URLs, source attribution, camera defaults, or
  file-name-like strings;
- mark metadata conflicts before they reach the operator;
- keep all accepted/edited candidates as `human_review_required=true`;
- keep the artifact `suggestion_only` with Core handoff only after operator
  review, never direct media metadata writes.

This filter is implemented as a local deterministic review-set gate. It emits
`candidate_quality_flags` and `filtered_candidate_notes` and blocks items as
`candidate_quality_insufficient` when metadata-only sources cannot produce a
usable candidate. Rerun the batch export with
`MEDIA_ALT_CAPTION_FORCE_EXPORT=1` before comparing follow-up eval results so
the new filter is reflected in the input artifact.

The current implementation should stay in Toolbox as a product-surface review
gate until a follow-up 36-case run shows materially lower rejected/misleading
rates and a real operator review confirms accepted/edited candidates are useful
outside the Toolbox screen.

## Candidate Filter Follow-Up

Command result: pass.

After tightening the local candidate-quality filter, the same local media
library no longer had 36 usable metadata-only candidates. The batch exporter
scanned 63 real image attachments, selected 11, and blocked 52. This is an
expected result of the stricter gate: low-value candidates are removed before
operator review rather than passed to eval-lab as weak suggestions.

Filtered batch summary:

| Metric | Result |
| --- | --- |
| Scanned image attachments | 63 |
| Eligible items | 11 |
| Selected items | 11 |
| Blocked items | 52 |
| Source fingerprint | `6f8c578d69d2cd3110773bba1a44cf2149b95150` |
| Blocked as candidate-quality insufficient | 34 |
| Blocked as metadata-complete for P0 | 18 |

Filtered cross-judge result:

| Metric | Result |
| --- | --- |
| Cases reviewed | 11 |
| Accepted | 11 |
| Edited | 0 |
| Rejected | 0 |
| Misleading | 0 |
| Partial | `false` |
| Provider failures | 0 |

Filtered attachment-level result:

| Attachment | Outcome | Average score | Passes | Flags |
| ---: | --- | ---: | ---: | --- |
| `7774` | `accepted` | `0.867` | `3/3` | `needs_human_visual_check` |
| `769` | `accepted` | `0.640` | `1/3` | `metadata_insufficient`, `too_generic`, `caption_redundant`, `needs_human_visual_check`, `metadata_duplicate` |
| `767` | `accepted` | `0.850` | `3/3` | `needs_human_visual_check`, `caption_redundant` |
| `766` | `accepted` | `0.703` | `2/3` | `needs_human_visual_check`, `caption_redundant`, `metadata_duplicate` |
| `765` | `accepted` | `0.810` | `2/3` | `needs_human_visual_check`, `caption_redundant`, `metadata_duplicate` |
| `764` | `accepted` | `0.687` | `2/3` | `needs_human_visual_check`, `caption_redundant`, `metadata_duplicate`, `filename_like`, `metadata_insufficient` |
| `761` | `accepted` | `0.727` | `2/3` | `needs_human_visual_check`, `caption_redundant`, `metadata_duplicate` |
| `759` | `accepted` | `0.720` | `2/3` | `needs_human_visual_check`, `caption_redundant`, `metadata_duplicate` |
| `758` | `accepted` | `0.787` | `2/3` | `needs_human_visual_check`, `caption_redundant`, `metadata_insufficient` |
| `757` | `accepted` | `0.637` | `1/3` | `needs_human_visual_check`, `caption_redundant`, `metadata_duplicate`, `filename_like`, `metadata_insufficient` |
| `754` | `accepted` | `0.787` | `2/3` | `needs_human_visual_check`, `caption_redundant`, `metadata_duplicate` |

Interpretation:

- The local filter eliminated the previous rejected/misleading eval outcomes in
  this media library.
- The cost is lower recall: the stricter metadata-only gate selected 11 useful
  candidates instead of forcing 36 weaker candidates into review.
- `caption_redundant` still appears because several accepted ALT candidates are
  derived from existing captions. That is acceptable only as an ALT improvement
  prompt and still requires human visual confirmation.
- This improves the case for keeping the feature as a Toolbox review surface,
  but it still does not justify migration or apply behavior. The next evidence
  should be real operator accept/edit/reject outcomes on these filtered
  candidates.

## Human Visual Confirmation

Command result: pass.

The operator reviewed the 11 filtered candidates against the actual image URLs
from `build/eval/media-alt-caption-human-review-filtered-11.csv`.

Human review result:

| Metric | Result |
| --- | --- |
| Candidates reviewed | 11 |
| Correct | 11 |
| Needs edit | 0 |
| Rejected | 0 |
| Misleading | 0 |

Interpretation:

- The filtered review set is useful as a Toolbox operator-facing review
  surface for this local media library.
- The current evidence supports keeping the feature in Toolbox with
  `suggestion_only`, `needs_human_visual_check`, and no direct media metadata
  writes.
- This still does not approve Toolkit migration or apply behavior. Those remain
  blocked until the same quality holds across more real sites and a governed
  media metadata write path exists through Abilities, Core, and Adapter.

## Cross-Site Validation - npcink.local - 2026-06-22

Command result: pass with zero selected candidates.

The second real-site check mounted the current Toolbox checkout temporarily on
the local `npcink.local` WordPress site and ran the same batch exporter through
the existing `/ai/site-helpers` route. The check used real image attachments,
the same metadata-only source policy, and the same per-request page cap of 10.
It did not create proposals, executions, media derivative runs, or media
metadata writes.

Site media-library shape:

| Metric | Result |
| --- | --- |
| Image attachments counted by direct SQL | 4,981 |
| Missing ALT | 4,935 |
| Missing caption | 4,847 |
| Weak titles (`image`, blank, or hash-like) | 340 |

Batch export result:

| Metric | Result |
| --- | --- |
| Attachments sampled | 500 |
| Page size | 10 |
| Selected candidates | 0 |
| Blocked candidates | 500 |
| Dominant blocked reason | `candidate_quality_insufficient` |

A targeted follow-up sampled 50 attachments that had either an existing ALT or
caption. It also selected 0 candidates; all 50 were blocked as
`candidate_quality_insufficient`.

Representative blocked evidence:

| Attachment | Existing metadata | Filter evidence |
| ---: | --- | --- |
| `279007` | caption=`构建页 - 构建` | `too_generic`, `metadata_duplicate`, `filename_like`, `caption_redundant`, `metadata_insufficient` |
| `21223` | caption=`友情链接提交表单` | `too_generic`, `filename_like`, `caption_redundant`, `metadata_insufficient` |
| `20056` | ALT/caption/description=`背景图片` | `metadata_duplicate`, `filename_like`, `caption_redundant`, `metadata_insufficient` |
| `15807` | title=`safar-safarov-MSN8TFhJ0is-unsplash`, caption=`背景图片` | `too_generic`, `source_attribution_or_url`, `caption_redundant`, `metadata_insufficient` |

Interpretation:

- This is a useful negative cross-site result. The filter correctly refused to
  turn generic labels, source attribution, hashes, screenshots, and UI-purpose
  words into ALT/caption suggestions.
- The feature remains valuable only when media-library metadata already
  contains enough descriptive signal. On weak metadata libraries, the correct
  output is a blocked review set and an operator next action such as
  `skip_until_better_metadata_or_visual_evidence`.
- Do not loosen the deterministic filter just to create volume.
- Do not move this implementation to Toolkit yet.
- Do not add direct apply behavior.
- If this workflow needs to cover weak-metadata libraries, the next design
  should add optional Cloud-owned visual/image-context evidence or an operator
  enrichment workflow. That evidence path must stay outside local Toolbox
  runtime ownership and still require human visual confirmation before any
  governed media metadata write path.

## AI Vision Evidence POC - 2026-06-22

The next POC keeps the product path simple:

1. Toolbox first runs the local metadata-only review-set builder.
2. If candidates are blocked as `candidate_quality_insufficient`, Toolbox emits
   a bounded `image_context_evidence_request.v1` packet for up to 10 items.
3. When the Cloud Addon exposes `request_image_context_evidence()`, Toolbox may
   call that named helper once. A Cloud-owned or host-owned runtime may return
   `image_context_evidence.v1` with visual summary, scene, objects, visible
   text, and confidence.
4. Toolbox uses that evidence only as candidate basis and still requires human
   visual confirmation before any future governed apply path.

This is intended to reduce operator work on weak local media libraries without
making local installs heavier. It does not add a local vision model, bundled
third-party image dataset, queue, Core proposal creation, or media metadata
write behavior. If Cloud evidence is unavailable, the review set remains a
metadata-only no-write artifact with the request packet visible for diagnosis.
