# OpenClaw Content Discoverability Handoff

Status: active handoff prompt source.

This document gives OpenClaw and `magick-ai-adapter` the fixed SEO, AEO, and
GEO suggestion flow for Toolbox content context. It is prompt guidance and
channel handoff documentation only. It does not create OpenClaw projection
truth, Core governance records, or final WordPress writes.

## Boundary

Layer ownership stays fixed:

- Toolbox stores the operator-filled content context and builds
  suggestion-only discoverability briefs.
- Adapter is the OpenClaw channel and runs direct-read abilities only when Core
  reports `governance_mode=direct_read` and
  `execution_surface=wp_abilities_rest`.
- Core owns proposal, approval, commit-preflight, and audit truth.
- WordPress Abilities API executes the registered ability callback.
- OpenClaw follows this prompt and visible Adapter tools; it must not infer
  write permission from a suggestion payload.

## Required OpenClaw Flow

OpenClaw must start with Adapter:

```text
GET /wp-json/magick-ai-adapter/v1/health
GET /wp-json/magick-ai-adapter/v1/help
GET /wp-json/magick-ai-adapter/v1/capabilities
```

Require the three Toolbox ability ids to appear in Core capabilities as
`direct_read` abilities before using them:

```text
magick-ai-toolbox/validate-content-discoverability-context
magick-ai-toolbox/get-content-discoverability-context
magick-ai-toolbox/build-content-discoverability-brief
```

Then run the suggestion flow:

1. Call `magick-ai-toolbox/validate-content-discoverability-context`.
2. Stop for operator input if the validation status is `needs_attention`.
3. Call `magick-ai-toolbox/get-content-discoverability-context`.
4. Call `magick-ai-toolbox/build-content-discoverability-brief` for one
   `post_id` or supplied topic/title/content.
5. Return proposal-ready suggestions only for fields listed in
   `proposal_allowed_fields`.
6. Send final write-like changes through Core proposals and preflight.

Adapter callers may use either `POST /run-read-ability` with the real
`ability_id`, or Adapter read shortcuts when available:

```text
GET /content-discoverability-validation
GET /content-discoverability-context
GET /content-discoverability-brief?post_id=POST_ID
```

## Prompt Block

Use this block when giving OpenClaw task instructions:

```text
You are a third-party AI caller using Magick AI Adapter.
Connect only to Adapter, not directly to Core or Toolbox REST.

Before SEO/AEO/GEO suggestions:
1. GET /health, GET /help, and GET /capabilities.
2. Verify these ability ids are direct_read on wp_abilities_rest:
   - magick-ai-toolbox/validate-content-discoverability-context
   - magick-ai-toolbox/get-content-discoverability-context
   - magick-ai-toolbox/build-content-discoverability-brief
3. Run validate-content-discoverability-context first.
4. If status is needs_attention, stop and ask the operator to update Toolbox
   Content Context.
5. Run get-content-discoverability-context.
6. Run build-content-discoverability-brief for exactly one post_id or supplied
   topic/title/content.

Return suggestions only. Do not write WordPress data.
Never directly mutate SEO meta, slug, excerpt, FAQ, schema, post content,
media, featured image, terms, or publishing status.
Do not invent product facts, customer stories, citations, rankings, guarantees,
or unsupported features.
Respect forbidden claims and brand voice from the content context.
Final writes must go through Core proposal, approval, and commit-preflight.
```

## Output Shape

OpenClaw should return a compact suggestion package:

```json
{
  "post_id": 0,
  "source": "adapter_run_read_ability",
  "context_status": "ready",
  "write_posture": "suggestion_only",
  "direct_wordpress_write": false,
  "suggestions": {
    "seo_title": "",
    "seo_description": "",
    "slug": "",
    "excerpt": "",
    "faq": [],
    "answer_summary": "",
    "geo_summary": ""
  },
  "proposal_required": true,
  "final_write_path": "core_proposal_required"
}
```

Only include fields allowed by the Toolbox brief. If the brief does not allow a
field, omit it instead of inventing permission.
