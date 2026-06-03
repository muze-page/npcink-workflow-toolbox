# AI Content Composition Abilities

Status: active first-version contract.

This document defines how other AI callers should compose Toolbox abilities
when drafting articles, selecting image candidates, and using vector context.

## Product Rule

Toolbox provides a bounded tool layer for content composition. It exposes
search, image-source, vector, content-context, and planning abilities that other
AI systems can call.

Toolbox does not become the article-writing brain, workflow runtime, knowledge
indexer, media importer, SEO writer, publisher, approval store, or audit truth.

## Recommended Call Sequence

For one article draft or article-planning run, an AI caller should use this
sequence:

1. `magick-ai-toolbox/get-content-discoverability-context`
   - Read site positioning, target audience, brand voice, keywords, forbidden
     claims, and proposal fields.
2. `magick-ai-toolbox/validate-content-discoverability-context`
   - Stop for operator input if required context is missing.
3. `magick-ai-toolbox/web-research`
   - Gather external source candidates for the topic.
4. `magick-ai-toolbox/vector-search`
   - Retrieve local style, historical content, internal-link, or image-context
     references from an already configured collection.
5. `magick-ai-toolbox/search-image-source`
   - Retrieve external image-source candidates and preserve attribution and
     `download_location`.
6. `magick-ai-toolbox/build-content-discoverability-brief`
   - Build suggestion-only SEO, AEO, and GEO instructions and proposal fields
     for one supplied topic or post.
7. `magick-ai-toolbox/build-article-brief`
   - Build a research/image/vector planning bundle when a compact operator
     brief is useful.
8. `magick-ai-toolbox/build-article-write-plan`
   - Convert a reviewed draft into a Core-ready `article_write_plan` handoff.

The AI caller may skip unavailable optional steps, but it must preserve the
write posture from the abilities it calls.

## Ability Roles

| Ability | Composition role | Output use |
| --- | --- | --- |
| `magick-ai-toolbox/get-content-discoverability-context` | `site_context` | Site-level content rules. |
| `magick-ai-toolbox/validate-content-discoverability-context` | `context_preflight` | Readiness checks before drafting. |
| `magick-ai-toolbox/web-research` | `research_evidence` | External source candidates and research notes. |
| `magick-ai-toolbox/vector-search` | `local_style_context` | Local style, previous article, internal-link, or image-context references from an existing collection. |
| `magick-ai-toolbox/search-image-source` | `image_source_candidates` | External image-source candidates with attribution metadata. |
| `magick-ai-toolbox/build-content-discoverability-brief` | `seo_aeo_geo_brief` | Suggestion-only SEO/AEO/GEO instructions and proposal template. |
| `magick-ai-toolbox/build-article-brief` | `article_planning_bundle` | Compact research, image, vector, and handoff notes. |
| `magick-ai-toolbox/build-article-write-plan` | `core_article_write_plan` | Reviewed draft plan for Core proposal intake. |
| `magick-ai-toolbox/build-media-brief` | `media_planning_bundle` | Image-source planning for existing post context. |

## Output Contract

Provider-backed Toolbox payloads should include enough contract metadata for AI
callers to reason about them without reading private settings:

```json
{
  "artifact_type": "research_evidence|image_source_candidates|local_style_context",
  "composition_role": "research_evidence|image_source_candidates|local_style_context",
  "write_posture": "suggestion_only",
  "direct_wordpress_write": false
}
```

Planning payloads such as `article_write_plan` or
`content_discoverability_brief` keep their existing artifact type, but must keep
the same direct-write-disabled posture unless a new governed contract is
accepted. Ability metadata carries the fuller boundary details such as provider
secret non-exposure and Core proposal write path.

## Search Usage

`web-research` provides source candidates for the draft. A writing AI should:

- cite or preserve source URLs in its evidence pack;
- treat Tavily output as research material, not verified truth;
- avoid inventing facts beyond the source candidates and supplied context;
- send write-like outcomes to Core proposal flows.

## Image Usage

`search-image-source` provides external image-source candidates. A writing AI
may recommend a featured or inline image candidate, but must:

- preserve `download_location`;
- preserve photographer attribution and source URL;
- avoid describing Unsplash, Pixabay, or Pexels as AI image generation;
- avoid importing media or setting featured images directly from Toolbox.

AI-generated image abilities, if added later, should use a separate ability id
and contract from image-source search.

## Vector Usage

`vector-search` can help with:

- article writing style references;
- old article refresh context;
- internal-link candidates;
- image recommendation context;
- reusable wording or topic patterns from an already indexed collection.

In the current stage, Toolbox may query Qdrant and create a synchronous query
embedding through SiliconFlow or Jina. Toolbox must not own content indexing,
re-indexing, stale-index detection, collection lifecycle, or full RAG behavior.

## Handoff Rule

The final AI output should be one of:

- a draft candidate for human review;
- source/image/vector evidence packs;
- SEO/AEO/GEO suggestion payloads;
- an `article_write_plan` for Core proposal intake.

Final WordPress writes still require Core proposal approval and reusable
WordPress abilities. Do not add direct publish, direct media import,
featured-image mutation, direct SEO mutation, `confirm_token`, or
`write_confirmed` behavior to Toolbox.
