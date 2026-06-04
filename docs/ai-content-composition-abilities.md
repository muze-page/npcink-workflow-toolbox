# AI Content Composition Abilities

Status: active first-version contract.

This document defines how other AI callers should compose Toolbox abilities
when they need external research, image candidates, vector context, content
guidance, or article planning.

## Product Rule

Toolbox provides a bounded tool layer for AI composition. It exposes
image-source, vector, content-context, and planning abilities that other AI
systems can call. Cloud-managed web search, image-source, and vector surfaces
are general tool inputs; article drafting is only one consumer.

Toolbox does not become the article-writing brain, workflow runtime, knowledge
indexer, media importer, SEO writer, publisher, approval store, or audit truth.

## General Tool Usage

Use the low-level provider abilities directly whenever the workflow only needs
one kind of evidence:

- Cloud-managed web search for external source candidates, comparison material,
  current public references, support context, source coverage, or article
  preparation. Magick AI Cloud owns web search provider configuration and
  execution;
- `magick-ai-toolbox/search-image-source` for featured, inline, layout,
  presentation, reference, or media-planning image candidates;
- `magick-ai-toolbox/search-site-knowledge` for semantic site search, related
  content, writing context, internal-link candidates, refresh suggestions,
  image-context lookup, FAQ candidates, content gap analysis, or publish
  preflight duplicate checks from Cloud-managed site knowledge;
- `magick-ai-toolbox/vector-search` only as a Cloud-managed site knowledge
  compatibility pointer for older clients.

Do not call article-specific planning abilities unless the workflow is actually
building or handing off an article.

## Article Call Sequence

For SEO, AEO, and GEO guidance, the primary contract is the lightweight brief:

```text
magick-ai-toolbox/build-content-discoverability-brief
```

It returns per-section `seo`, `aeo`, and `geo` blocks plus
`exceptions`/`special_cases`, proposal fields, and conservative candidates.

For OpenClaw or another external caller that only receives a broad
natural-language request such as "write an article about AI", use the high-level
fallback entrypoint:

```text
magick-ai-toolbox/build-ai-article-writing-pack
```

That ability internally composes the local content context, context validation,
and content discoverability brief into one writing pack. The caller can then
draft from the returned instructions without manually chaining each lower-level
ability. It is not the primary SEO/AEO/GEO contract.

For one article draft or article-planning run, an AI caller should use this
sequence:

1. `magick-ai-toolbox/get-content-discoverability-context`
   - Read site positioning, target audience, brand voice, keywords, forbidden
     claims, and proposal fields.
2. `magick-ai-toolbox/validate-content-discoverability-context`
   - Stop for operator input if required context is missing.
3. Cloud-managed web search
   - Gather external source candidates for the topic through Magick AI Cloud.
4. `magick-ai-toolbox/vector-search`
   - Retrieve local style, historical content, internal-link, or image-context
     references from an already configured collection.
5. `magick-ai-toolbox/search-image-source`
   - Retrieve external image-source candidates and preserve attribution and
     `download_location`.
6. `magick-ai-toolbox/build-content-discoverability-brief`
   - Build suggestion-only SEO, AEO, and GEO instructions and proposal fields
     for one supplied topic or post.
7. `magick-ai-toolbox/build-ai-article-writing-pack`
   - Build a single OpenClaw-friendly writing context pack for natural-language
     article requests.
8. `magick-ai-toolbox/build-article-brief`
   - Build a research/image/vector planning bundle when a compact operator
     brief is useful.
9. `magick-ai-toolbox/build-article-write-plan`
   - Convert a reviewed draft into a Core-ready `article_write_plan` handoff.
10. `magick-ai-toolbox/build-article-media-batch-write-plan`
   - Convert reviewed drafts and selected image-source candidates into a
     Core-ready `article_media_batch_write_plan` handoff. Reviewed media
     imports may include an optional `file_name` value supplied by the article
     row or selected image candidate.

The AI caller may skip unavailable optional steps, but it must preserve the
write posture from the abilities it calls.

## Fixed Button Mapping

OpenClaw natural-language recipes and Toolbox fixed buttons should compose the
same ability contracts. The channel changes, but the write boundary does not.

| Operator intent | OpenClaw route | Toolbox button flow | Final write path |
| --- | --- | --- | --- |
| Draft one reviewed article | Adapter `article_draft_plan` recipe | Article Write Plan | Core proposal for `magick-ai/create-draft` |
| Build article plus featured images | Adapter `article_media_batch_plan` recipe | Article/media batch plan | Core proposal for draft, media upload, metadata, and featured-image abilities |
| Adopt one reviewed image candidate | Adapter `image_candidate_adoption_plan` recipe | Adopt New Image | Core proposal for media upload, metadata, and optional featured image |
| Optimize existing media | Adapter media derivative recipe | Optimize Existing Image | Core proposal for `magick-ai/adopt-cloud-media-derivative` |
| Repair hard-coded media URLs | Adapter read ability plus Core from-plan | URL repair proposal button | Core proposal for exact-match patch actions |
| Suggest SEO/AEO/GEO fields | Adapter content discoverability recipe | Content Discoverability brief | Core proposal for allowed fields only |

Toolbox may make these flows easier to click through, but it must not create a
separate workflow runtime, direct write path, or approval store for them.

## Ability Roles

| Ability | Composition role | Output use |
| --- | --- | --- |
| `magick-ai-toolbox/get-content-discoverability-context` | `site_context` | Site-level content rules. |
| `magick-ai-toolbox/validate-content-discoverability-context` | `context_preflight` | Readiness checks before drafting. |
| `magick-ai-toolbox/vector-search` | `site_knowledge_context` | Cloud-managed site knowledge compatibility pointer. New callers should use `search-site-knowledge`. |
| `magick-ai-toolbox/search-site-knowledge` | `site_knowledge_context` | Cloud-managed site search, related content, writing context, internal links, refresh suggestions, or image context. |
| `magick-ai-toolbox/get-site-knowledge-status` | `site_knowledge_status` | Cloud-managed site knowledge coverage and freshness status. |
| `magick-ai-toolbox/request-site-knowledge-sync` | `site_knowledge_sync_request` | Bounded public-content sync or rebuild request for Cloud-managed site knowledge. |
| `magick-ai-toolbox/search-image-source` | `image_source_candidates` | External image-source candidates with attribution metadata. |
| `magick-ai-toolbox/build-content-discoverability-brief` | `seo_aeo_geo_brief` | Suggestion-only SEO/AEO/GEO instructions and proposal template. |
| `magick-ai-toolbox/build-ai-article-writing-pack` | `ai_article_writing_pack` | Convenience fallback for OpenClaw-style natural-language article requests. |
| `magick-ai-toolbox/build-article-brief` | `article_planning_bundle` | Compact research, image, vector, and handoff notes. |
| `magick-ai-toolbox/build-article-write-plan` | `core_article_write_plan` | Reviewed draft plan for Core proposal intake. |
| `magick-ai-toolbox/build-article-batch-write-plan` | `core_article_batch_write_plan` | Reviewed draft batch plan for one Core batch proposal. |
| `magick-ai-toolbox/build-article-media-batch-write-plan` | `core_article_media_batch_write_plan` | Reviewed article plus image-source plan for Core-governed draft, media upload, metadata, and featured-image actions. |
| `magick-ai-toolbox/build-image-candidate-adoption-plan` | `core_image_candidate_adoption_plan` | Reviewed image candidate plan for Core-governed media upload, metadata, and optional featured-image actions. |
| `magick-ai-toolbox/build-media-brief` | `media_planning_bundle` | Image-source planning for existing post context. |

## Output Contract

Provider-backed Toolbox payloads should include enough contract metadata for AI
callers to reason about them without reading private settings:

```json
{
  "artifact_type": "research_evidence|image_source_candidates|site_knowledge_context",
  "composition_role": "research_evidence|image_source_candidates|site_knowledge_context",
  "write_posture": "suggestion_only",
  "direct_wordpress_write": false
}
```

Planning payloads such as `article_write_plan` keep their existing artifact
type. The `content_discoverability_brief` payload is the primary lightweight
SEO/AEO/GEO contract and includes `primary_contract=true`, `seo`, `aeo`, `geo`,
`exceptions`, `special_cases`, `proposal_allowed_fields`,
`proposal_template`, and `candidate_suggestions`. All planning payloads must
keep the same direct-write-disabled posture unless a new governed contract is
accepted. Ability metadata carries the fuller boundary details such as provider
secret non-exposure and Core proposal write path.

## Search Usage

Cloud-managed web search provides external source candidates. This Cloud runtime
is general-purpose: support answers, competitive comparisons, source coverage,
briefing, product research, content planning, and article writing should call
the same Cloud search capability instead of integrating search provider APIs
directly or configuring provider keys in Toolbox.

Every AI caller should:

- preserve Cloud-returned provider/source names when present;
- cite or preserve source URLs in its evidence pack;
- treat Cloud-managed web search output as research material, not verified
  truth;
- avoid inventing facts beyond the source candidates and supplied context;
- send write-like outcomes to Core proposal flows.

## Image Usage

`search-image-source` provides image candidates. For public photo/source
providers it returns external image-source candidates. In explicit
`ai_generated` mode it may normalize a reviewed generated image URL supplied by
the caller, or call a host-provided `magick_ai_toolbox_ai_image_generation_request`
runtime seam and return generated-image candidates. This ability is
general-purpose: article writing, media planning, page layout, product
presentation, reference selection, and any other image-dependent AI workflow
should call the same ability instead of integrating provider APIs directly.

Every image candidate should be treated as `image_candidate.v1`. The contract
keeps `source_type`, `provider`, `provider_origin`, `download_url`,
`thumbnail_url`, `prompt`, `model`, `license_review_status`, attribution,
provenance, and warnings. Public image providers use `source_type=stock`;
generated-image candidates use `source_type=ai_generated`.

A writing AI may recommend a featured or inline image candidate, but every AI
caller must:

- preserve provider name and source URL;
- preserve `download_location`;
- preserve photographer attribution and source URL;
- avoid describing Unsplash, Pixabay, or Pexels as AI image generation;
- mark generated-image candidates as `source_type=ai_generated` and keep the
  prompt/model evidence plus human license review status;
- preserve any reviewed `file_name` so the governed media upload can use a
  customer-approved filename;
- avoid importing media or setting featured images directly from Toolbox.

AI-generated candidates are not public image-source search results. They remain
suggestion-only candidates until Core approval and a local WordPress media
write ability handles import. To adopt one reviewed candidate, callers should
build `magick-ai-toolbox/build-image-candidate-adoption-plan` and send the
returned `image_candidate_adoption_plan` to Core from-plan intake.

## Vector Usage

Prefer `search-site-knowledge` when the workflow needs current site context:

- semantic site search;
- related content recommendations;
- writing reference snippets;
- internal-link candidates;
- old article refresh context;
- image recommendation context.

`vector-search` remains only as a Cloud-managed site knowledge compatibility
pointer for older clients. New workflows should call `search-site-knowledge`
directly. Toolbox must not own embedding provider configuration, vector
database settings, content indexing, re-indexing, stale-index detection,
collection lifecycle, or full RAG behavior.

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
