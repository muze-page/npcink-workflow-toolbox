# Media ALT/Caption Operator Trial - 2026-06-21

Status: local real-media read-only trial.

## Purpose

This trial validates the current `media_alt_caption_review_set.v1` surface
before any Toolkit extraction work. It checks whether the review set can scan a
real local WordPress media library, return selected and blocked items, and keep
the whole flow review-only.

The trial is not a migration approval. It is one evidence point for the
[Media ALT/Caption Toolkit Validation Plan](media-alt-caption-toolkit-validation-plan.md).

## Trial Command

```bash
composer smoke:media-alt-caption-trial
composer eval:media-alt-caption:export
composer eval:media-alt-caption:judge-cross
```

The command runs through WP-CLI against the configured local WordPress site. It
uses a local host filter for the hosted site-helper response so the trial can
exercise the existing `/ai/site-helpers` route without requiring Cloud runtime
availability.

`composer eval:media-alt-caption:export` writes the selected
`media_alt_caption_operator_trial.v1` cases to local `build/eval/`.
`composer eval:media-alt-caption:judge-cross` passes that local artifact to
the development-only `magick-ai-eval-lab` task
`media_alt_caption_judge_cross`. The eval-lab output is AI-assisted review
evidence only; it is not final acceptance truth or write authorization.

## Boundary Checked

- real image attachments only; no generated fixture media;
- `media_library_metadata_only_no_pixel_vision`;
- `suggestion_only`;
- no direct media metadata writes;
- no proposal creation;
- no execution creation;
- no media derivative run;
- every selected item requires human visual review;
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
| Cases reviewed | 2 |
| Accepted | 1 |
| Edited | 1 |
| Rejected | 0 |
| Misleading | 0 |

Attachment-level result:

| Attachment | Outcome | Average score | Passes | Flags |
| ---: | --- | ---: | ---: | --- |
| `8053` | `edited` | `0.567` | `0/3` | `too_generic`, `caption_redundant`, `needs_human_visual_check` |
| `7774` | `accepted` | `0.867` | `3/3` | `needs_human_visual_check` |

Interpretation:

- The selected candidates did not trip direct write, proposal, execution, or
  misleading-content boundaries.
- Description-backed candidates can be specific enough to enter human visual
  confirmation.
- Metadata-thin candidates still need editing when they only restate title/ALT
  language.
- The next product step should keep every item on the human visual review path
  and collect real operator accept/edit/reject outcomes.

## Follow-Up Decision

Do not move code to `npcink-abilities-toolkit` from this single trial alone.
The latest AI-assisted review improved to one `accepted` and one `edited`
outcome after metadata-source selection was tightened. That is useful quality
evidence, but it is still too small for extraction approval and one
metadata-thin item still required editing. The next useful evidence is real
operator review quality: accepted, edited, rejected, and misleading suggestion
counts for selected items.

Keep the current implementation in Toolbox until at least one real operator
review records those outcome counts and confirms the artifact is useful outside
the Toolbox screen.
