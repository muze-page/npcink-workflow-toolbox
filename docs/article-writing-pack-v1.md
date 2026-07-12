# Article Writing Pack V1

Status: active contract for URL, manual, mixed, review, and draft-preview stages.

## Purpose

`article_writing_pack.v1` is the planning artifact that must exist before an
article draft preview can be requested. It is not an article body, translation,
workflow run, Core proposal, durable approval record, or WordPress write.

Three input modes populate the same contract:

- `url_reference`: one public URL plus inferred editorial direction;
- `manual_brief`: operator audience, goal, focus, and related fields without a
  claimed external fact source;
- `mixed`: exact URL evidence plus operator editorial fields, with operator
  preferences taking precedence over inference.

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

An explicit operator value may replace an inferred preference, but it must not
turn an unsupported claim into a verified fact. Source facts remain
grounded only in exact-source evidence; Site Knowledge remains overlap,
terminology, tone, and internal-reference context rather than proof about the
external source.

## Input Boundary

Accepted:

- `input_mode=url_reference` with one validated public `source_url`;
- `input_mode=manual_brief` with audience, article goal, and at least one focus
  point;
- `input_mode=mixed` with the URL plus typed editorial fields.

Not accepted:

- multiple source URLs;
- pasted source bodies or uploads.

The editor exposes typed audience, goal, reader problem, focus, angle, title,
promise, content type, outline, and additional-guidance fields. Unsupported
input modes fail explicitly instead of silently falling back.

The legacy `source_stage=adapt` input remains accepted for compatibility and
keeps the outer `source_adaptation_review.v1` wrapper while including the same
`sections.article_writing_pack` artifact. New clients use
`source_stage=research_plan` and receive `article_writing_pack.v1` as the
primary outer artifact.

## Review Contract

Draft generation requires one request-scoped `article_writing_pack_review.v1`:

```json
{
  "artifact_type": "article_writing_pack_review.v1",
  "status": "confirmed_by_operator",
  "base_content_fingerprint": "sha256:...",
  "review_fingerprint": "sha256:...",
  "article_generation_allowed": true,
  "authorization_scope": "single_synchronous_draft_preview_request",
  "durable_approval_state": false,
  "direct_wordpress_write": false
}
```

The server rejects absent confirmation, fingerprint mismatch, unsupported input
mode, or missing required fields. This is not `confirm_token`, Core approval,
or a local approval store. Editing any structured field in the UI clears the
confirmation checkbox.

## Draft Preview Boundary

The confirmed pack may produce `article_draft_preview.v1` with a title, excerpt,
ordered plain-text sections, fact references, verification notes, and source
attribution notes. The draft runtime must consume the reviewed pack and review
fingerprint; it must not reread a URL and silently choose a different direction.

The draft preview always remains:

- `suggestion_only`;
- `operator_review_required=true`;
- `direct_wordpress_write=false`;
- `body_insertion=false`;
- `body_replacement=false`.

The editor exposes no insert, replace, save, publish, media-import, proposal, or
background action for this preview.

## Request-Scoped Draft Review

After a draft preview is returned, the editor may collect one lightweight
`article_draft_review_feedback.v1` envelope:

- `status`: `usable`, `usable_after_changes`, or `not_usable`;
- `issue_codes`: a bounded subset of `fact_accuracy`, `site_tone`, `structure`,
  `source_similarity`, and `rights_attribution`;
- `notes`: a short operator revision instruction.

The feedback exists only in the current editor session. It is sent to the
hosted text runtime only when the operator explicitly requests another draft,
and it may guide editorial revision but may never become factual evidence. The
response may echo the sanitized envelope so the operator can see what informed
the latest regeneration.

The contract always keeps:

- `authorization_scope=single_draft_regeneration_request`;
- `durable_review_state=false`;
- `direct_wordpress_write=false`.

The editor may copy the plain-text preview to the clipboard. Copying does not
insert content into Gutenberg, save the post, create a Core proposal, or
publish anything. No review database, acceptance history, learning profile, or
automatic regeneration loop is introduced in this version.

## Ownership

- Toolbox owns the editor composition, normalization, display, and feedback.
- Cloud owns exact-source reading, hosted text execution, and Site Knowledge.
- Cloud Addon remains signed transport only.
- Core, Adapter, and Toolkit are unchanged because this artifact performs no
  WordPress write.
