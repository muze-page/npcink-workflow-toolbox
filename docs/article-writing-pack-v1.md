# Article Writing Pack V1

Status: active contract for the URL-reference first slice.

## Purpose

`article_writing_pack.v1` is the reviewed planning artifact that must exist
before any future source-grounded article draft generation. It is not an
article body, translation, workflow run, Core proposal, or WordPress write.

The first implemented input mode is `url_reference`: one operator-supplied
public URL is read through Cloud exact-source extraction and compared with
Cloud Site Knowledge. The contract is deliberately not URL-shaped. Future
manual and mixed inputs will populate the same `source_materials` and
`editorial_brief` fields additively instead of creating another writing-pack
format.

## Contract

```json
{
  "artifact_type": "article_writing_pack.v1",
  "contract_version": "article_writing_pack.v1",
  "input_mode": "url_reference",
  "inputs": {
    "source_materials": [],
    "editorial_brief": {
      "audience": {},
      "article_goal": {},
      "reader_problem": {},
      "focus_points": [],
      "operator_instruction": {}
    },
    "site_context_policy": {}
  },
  "research_basis": {
    "source_summary": [],
    "fact_ledger": [],
    "source_coverage": {},
    "verification_items": []
  },
  "site_adaptation": {
    "related_articles": [],
    "overlap_map": [],
    "site_style_signals": [],
    "unique_angle": {}
  },
  "writing_plan": {
    "title_directions": [],
    "reader_promise": {},
    "content_type": {},
    "outline": [],
    "cta_direction": {}
  },
  "risk_review": {
    "fact_risks": [],
    "rights_risks": [],
    "similarity_risks": []
  },
  "generation_admission": {},
  "provenance": {},
  "content_fingerprint": "sha256:...",
  "write_posture": "suggestion_only",
  "direct_wordpress_write": false
}
```

Every inferred editorial field carries:

- `value`;
- `source`;
- `operator_confirmed=false`.

An explicit future operator value may replace an inferred preference, but it
must not turn an unsupported claim into a verified fact. Source facts remain
grounded only in exact-source evidence; Site Knowledge remains overlap,
terminology, tone, and internal-reference context rather than proof about the
external source.

## Current Input Boundary

Accepted now:

- `input_mode=url_reference`;
- one validated public `source_url`.

Not accepted yet:

- `manual_brief`;
- `mixed` URL plus manual field editing;
- multiple source URLs;
- pasted source bodies or uploads.

The current editor surface does not expose a free-form brief field. Manual
audience, priorities, and related information will enter only through the
explicit future input modes, so URL-first behavior cannot become an accidental
untyped manual contract.

Unsupported input modes fail with a validation error instead of silently
falling back to URL mode. Future support must be additive to this contract.

The legacy `source_stage=adapt` input remains accepted for compatibility and
keeps the outer `source_adaptation_review.v1` wrapper while including the same
`sections.article_writing_pack` artifact. New clients use
`source_stage=research_plan` and receive `article_writing_pack.v1` as the
primary outer artifact.

## Article Generation Boundary

V1 always returns `article_generation_allowed=false`. A future article
generator must consume the reviewed writing pack and its fingerprint rather
than independently rereading the URL and choosing a new direction. V1 exposes
no article-generation, body-insertion, translation, media-import, proposal, or
publish action.

## Ownership

- Toolbox owns the editor composition, normalization, display, and feedback.
- Cloud owns exact-source reading, hosted text execution, and Site Knowledge.
- Cloud Addon remains signed transport only.
- Core, Adapter, and Toolkit are unchanged because this artifact performs no
  WordPress write.
