# OpenClaw SEO/GEO/AEO Acceptance Summary

Status: accepted local operating summary, 2026-06-04.

This document summarizes the current SEO, GEO, and AEO content-discoverability
contract for OpenClaw, Adapter, Toolbox, and other third-party AI callers. It is
an operating handoff, not a new control-plane decision.

## Scope

The next-stage content work is:

```text
Content discoverability and answerability governance
= SEO + GEO + AEO
```

Toolbox owns the non-secret WordPress-side content context and exposes it as
read-only, suggestion-only abilities. Toolbox does not own OpenClaw projection
truth, Agent Gateway admission, Core approval, final WordPress writes, prompt
runtime, provider billing, or provider credentials.

## Main Entrypoints

Use these abilities for third-party AI consumption:

| Intent | Adapter shortcut | Toolbox ability | Role |
| --- | --- | --- | --- |
| Validate filled site context | `GET /content-discoverability-validation` | `magick-ai-toolbox/validate-content-discoverability-context` | Context readiness check |
| Read site context | `GET /content-discoverability-context` | `magick-ai-toolbox/get-content-discoverability-context` | Read-only site rule source |
| SEO/GEO/AEO suggestions | `GET /content-discoverability-brief?post_id=POST_ID` | `magick-ai-toolbox/build-content-discoverability-brief` | Primary contract |
| Broad article request | `GET /article-writing-pack?topic=AI_TOPIC` | `magick-ai-toolbox/build-ai-article-writing-pack` | Fallback article pack |

The primary SEO/GEO/AEO entrypoint is `content-discoverability-brief`.
`article-writing-pack` is only for broad natural-language article requests such
as:

```text
按 AI 主题给我写篇文章
```

For explicit content governance requests such as:

```text
给这篇文章做 SEO/GEO/AEO 建议
```

OpenClaw should use `content-discoverability-brief`, not
`article-writing-pack`.

## Required OpenClaw Flow

For SEO/GEO/AEO suggestions:

1. Call `content-discoverability-validation`.
2. If required fields are missing, stop and ask the operator to complete the
   Toolbox content context.
3. Call `content-discoverability-context`.
4. Call `content-discoverability-brief` for exactly one `post_id` or supplied
   topic/content input.
5. Return suggestions only. Do not write SEO meta, slug, excerpt, FAQ, schema,
   media, terms, posts, or settings.
6. If the operator accepts a write-like suggestion, create or route through a
   Core-governed proposal and WordPress ability write path.

For broad article-writing requests:

1. Call `article-writing-pack`.
2. Use the returned context, prompt guidance, discoverability brief, exceptions,
   and guardrails to draft a reviewable article candidate.
3. Treat the draft as suggestion-only.
4. Any reviewed final write must still go through Core proposal, approval, and
   commit-preflight governance.

## Contract Markers

The accepted outputs must preserve these markers:

```text
artifact_type=content_discoverability_brief
primary_contract=true
write_posture=suggestion_only
direct_wordpress_write=false
final_write_path=core_proposal_required
```

The brief must expose:

- `seo`
- `aeo`
- `geo`
- `exceptions`
- `special_cases`
- `proposal_template`
- governed `handoff` metadata

For broad article requests, the accepted fallback output is:

```text
artifact_type=ai_article_writing_pack
primary_contract=false
write_posture=suggestion_only
direct_wordpress_write=false
final_write_path=core_proposal_required
```

## Filled Context State

The local WordPress content context has been filled with real site guidance for:

- site positioning;
- target audience;
- brand voice;
- primary, long-tail, and entity keywords;
- allowed and forbidden claims;
- SEO, AEO, and GEO rules;
- proposal fields AI may suggest;
- exception and special-case rules.

Exception groups now cover:

- `disallowed_topics`;
- `cautious_topics`;
- `no_structured_output_topics`;
- `human_confirmation_required`.

These rules are stored in the WordPress option
`magick_ai_toolbox_content_context`. They are runtime site configuration, not a
source-controlled code artifact.

## Local Acceptance Evidence

Local Adapter/Toolbox acceptance was run against the local WordPress environment
on 2026-06-04.

Validation result:

```text
validation_status=ready
score=1
missing_required=0
missing_recommended=0
```

SEO/GEO/AEO brief result:

```text
ability=magick-ai-toolbox/build-content-discoverability-brief
artifact=content_discoverability_brief
status=ready
direct_write=false
```

Broad article request result:

```text
ability=magick-ai-toolbox/build-ai-article-writing-pack
artifact=ai_article_writing_pack
direct_write=false
```

Toolbox content-discoverability smoke also passed:

```text
Content discoverability smoke passed.
```

## Boundary Notes

- Do not add a Toolbox update-context ability for third-party AI in the first
  version. Operators maintain the context in the WordPress admin UI.
- Do not add direct SEO, schema, media, slug, excerpt, or post writes in
  Toolbox.
- Do not treat OpenClaw as precisely controllable application logic. OpenClaw is an external natural-language channel that follows visible guidance and available tools.
- If OpenClaw picks the wrong entrypoint, fix Adapter recipe guidance or
  host-side tool admission/prompting before adding heavier Toolbox abilities.
- Missing Agent Gateway direct tool projection is a host/Core admission task,
  not a reason to create a second registry in Toolbox.

## Next Manual Check

Use two real OpenClaw prompts:

```text
按 AI 主题给我写篇文章
```

Expected route: `article-writing-pack`.

```text
给这篇文章做 SEO/GEO/AEO 建议
```

Expected route: `content-discoverability-brief`.

The pass condition is that OpenClaw selects the right entrypoint, returns
suggestions only, and does not attempt direct WordPress writes.
