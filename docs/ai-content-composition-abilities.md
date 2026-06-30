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
Default composition should support the editorial work around the article body:
taxonomy/tag choices, internal-link candidates, image candidates, SEO/AEO/GEO
guidance, media metadata, and publish/readiness checks. Article writing packs
are fallback packaging for broad writing-support prompts, not the main product
contract.

Toolbox does not become the article-writing brain, workflow runtime, knowledge
indexer, media importer, SEO writer, publisher, approval store, or audit truth.

## General Tool Usage

Use the low-level provider abilities directly whenever the workflow only needs
one kind of evidence:

- Cloud-managed web search for external source candidates, comparison material,
  current public references, support context, source coverage, or article
  preparation. Npcink Cloud owns web search provider configuration and
  execution;
- the fixed editor `zhihu_research` flow for Zhihu topic, question, viewpoint,
  and objection signals before drafting. It is still Cloud-managed web search
  evidence for the current query, not a local Zhihu provider integration,
  article rewrite tool, or publish path;
- the Dashboard `zhihu_hot_topics` topic pool for a Cloud-cached Zhihu hot-list.
  It is a trend and selection surface, not a factual source,
  article generator, scheduler, publisher, or WordPress write path. OpenClaw
  callers should use `npcink-toolbox/cloud-web-search` with
  `managed_source=zhihu_hot_topics`, `intent=zhihu_hot_topics`, and a neutral
  query such as `知乎热榜`; Toolbox promotes the Cloud `topic_candidate.v1`
  atom into `hot_topic_pool` for direct topic selection;
- `npcink-toolbox/search-image-source` for featured, inline, layout,
  presentation, reference, or media-planning image candidates;
- `npcink-toolbox/search-site-knowledge` for semantic site search, related
  content, writing context, internal-link candidates, refresh suggestions,
  image-context lookup, FAQ candidates, content gap analysis, or publish
  preflight duplicate checks from Cloud-managed site knowledge;
- the legacy `/vector-search` REST route only as a Cloud-managed site knowledge
  compatibility pointer for older clients. It is not a public Ability catalog
  entry.

Do not call article-specific planning abilities unless the workflow is actually
building or handing off an article.

## Content Support First

For SEO, AEO, and GEO guidance, the primary contract is the lightweight brief:

```text
npcink-toolbox/build-content-discoverability-brief
```

It returns per-section `seo`, `aeo`, and `geo` blocks plus
`exceptions`/`special_cases`, proposal fields, and conservative candidates.

For normal WordPress editorial support, compose abilities in this order:

1. `npcink-toolbox/build-content-discoverability-brief`
   - Build suggestion-only SEO/AEO/GEO guidance, taxonomy/tag candidates,
     internal-link hints, and proposal fields for one topic or post.
2. `npcink-toolbox/search-site-knowledge`
   - Retrieve related content, internal-link candidates, duplicate-risk
     signals, refresh suggestions, or image context from Cloud-managed site
     knowledge.
3. `npcink-toolbox/search-image-source`
   - Retrieve featured, inline, layout, reference, or media-planning image
     candidates and preserve attribution.
4. `npcink-abilities-toolkit/build-image-candidate-adoption-plan`
   - After operator review, build a Core-ready media adoption plan when the
     operator wants to import a selected image candidate.
5. `npcink-toolbox/build-site-knowledge-review-plan`
   - After operator review, build a blocked Core review proposal plan from
     Cloud Site Knowledge evidence without generating or writing content.
6. `/flows/media-brief`
   - Compatibility REST route for saved-post image planning when the editor
     sidebar is not available. It is not a public Ability catalog entry.

For OpenClaw or another external caller that only receives a broad
natural-language request such as "write an article about AI", use the high-level
fallback entrypoint:

```text
npcink-toolbox/build-ai-article-writing-pack
```

That ability internally composes the local content context, context validation,
and content discoverability brief into one writing pack. The caller can then
draft from the returned instructions without manually chaining each lower-level
ability. It is not the primary SEO/AEO/GEO contract.

For one reviewed article draft or article-planning fallback run, an AI caller
should use this sequence:

1. `npcink-toolbox/get-content-discoverability-context`
   - Read site positioning, target audience, brand voice, keywords, forbidden
     claims, and proposal fields.
2. `npcink-toolbox/validate-content-discoverability-context`
   - Stop for operator input if required context is missing.
3. Cloud-managed web search
   - Gather external source candidates for the topic through Npcink Cloud.
4. `npcink-toolbox/search-site-knowledge`
   - Retrieve local style, historical content, internal-link, or image-context
     references from Cloud-managed site knowledge.
5. `npcink-toolbox/search-image-source`
   - Retrieve external image-source candidates and preserve attribution and
     `download_location`.
6. `npcink-toolbox/build-content-discoverability-brief`
   - Build suggestion-only SEO, AEO, and GEO instructions and proposal fields
     for one supplied topic or post.
7. `npcink-toolbox/build-ai-article-writing-pack`
   - Build a single OpenClaw-friendly writing context pack for natural-language
     article requests.
8. `/flows/article-brief`
   - Compatibility REST route for a compact planning bundle. It is not a
     public Ability catalog entry.
9. `npcink-toolbox/build-article-write-plan`
   - Convert a reviewed draft into a Core-ready `article_write_plan` handoff.
10. `npcink-toolbox/build-article-media-batch-write-plan`
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
| Suggest taxonomy/tags | Adapter taxonomy terms recipe | Taxonomy/tag recommendations | Core proposal for `npcink-abilities-toolkit/set-post-terms` after review |
| Find internal-link opportunities | Adapter/site-knowledge support recipe | Internal-link candidates | No direct write; future reviewed content patch must go through Core |
| Research Zhihu audience questions and viewpoints | Cloud web search evidence recipe | Zhihu research | No direct write; source candidates only, with manual citation and human drafting |
| Review daily Zhihu hot topics | Cloud cached hot-list evidence recipe | Zhihu hot topics | No direct write; trend signals only, then manual topic selection and focused research |
| Find image candidates | Adapter image-source support recipe | Image candidates | No direct write until a candidate adoption plan is reviewed |
| Suggest SEO/AEO/GEO fields | Adapter content discoverability recipe | Content Discoverability brief | Core proposal for allowed fields only |
| Run publish/readiness preflight | Adapter site-knowledge/support recipe | Publish preflight | No direct write; returns warnings and operator tasks |
| Adopt one reviewed image candidate | Adapter `image_candidate_adoption_plan` recipe | Editor image adoption | Core proposal for media upload, metadata, and optional featured image |
| Optimize existing media | Adapter media derivative recipe | Media Library image action / Batch Optimize Images | Core media optimization proposal for reviewed metadata plus `npcink-abilities-toolkit/adopt-cloud-media-derivative` |
| Repair hard-coded media URLs | Adapter read ability plus Core from-plan | URL repair proposal button | Core proposal for exact-match patch actions |
| Draft one reviewed article | Adapter `article_draft_plan` recipe | Article Write Plan fallback | Core proposal for `npcink-abilities-toolkit/create-draft` |
| Build article plus featured images | Adapter `article_media_batch_plan` recipe | Article/media batch fallback | Core proposal for draft, media upload, metadata, and featured-image abilities |

Toolbox may make these flows easier to click through, but it must not create a
separate workflow runtime, direct write path, or approval store for them.

The high-frequency WordPress entrypoint for these fixed flows is the Toolbox
post editor **Npcink Content Support** panel. The admin **Content Support**
tab remains the management, testing, and cross-article surface.

## Ability Roles

| Ability | Composition role | Output use |
| --- | --- | --- |
| `npcink-toolbox/get-content-discoverability-context` | `site_context` | Site-level content rules. |
| `npcink-toolbox/validate-content-discoverability-context` | `context_preflight` | Readiness checks before drafting. |
| `npcink-toolbox/search-site-knowledge` | `site_knowledge_context` | Cloud-managed site search, related content, writing context, internal links, refresh suggestions, or image context. |
| `npcink-toolbox/get-site-knowledge-status` | `site_knowledge_status` | Cloud-managed site knowledge coverage and freshness status. |
| `npcink-toolbox/request-site-knowledge-sync` | `site_knowledge_sync_request` | Bounded public-content refresh request for Cloud-managed site knowledge. |
| `npcink-toolbox/search-image-source` | `image_source_candidates` | External image-source candidates with attribution metadata. |
| `npcink-toolbox/generate-image` | `image_source_candidates` | Reviewed-prompt Cloud AI image candidates from an image-source handoff. |
| `npcink-toolbox/build-content-discoverability-brief` | `seo_aeo_geo_brief` | Suggestion-only SEO/AEO/GEO instructions and proposal template. |
| `npcink-toolbox/build-ai-article-writing-pack` | `ai_article_writing_pack` | Convenience fallback for OpenClaw-style natural-language article requests. |
| `npcink-toolbox/build-article-write-plan` | `core_article_write_plan` | Reviewed draft plan for Core proposal intake. |
| `npcink-toolbox/build-article-batch-write-plan` | `core_article_batch_write_plan` | Reviewed draft batch plan for one Core batch proposal. |
| `npcink-toolbox/build-article-media-batch-write-plan` | `core_article_media_batch_write_plan` | Reviewed article plus image-source plan for Core-governed draft, media upload, metadata, and featured-image actions. |
| `npcink-abilities-toolkit/build-image-candidate-adoption-plan` | `core_image_candidate_adoption_plan` | Reviewed image candidate plan for Core-governed media upload, metadata, and optional featured-image actions. |
| `npcink-toolbox/build-site-knowledge-review-plan` | `core_site_knowledge_review_plan` | Blocked Site Knowledge review plan with preserved evidence refs and human title/content input still required. |

## Atomic Knowledge Outputs

Search-like surfaces should consume Cloud atomic output contracts rather than
inventing one-off result shapes:

| Atomic capability | Expected contract | Toolbox use |
| --- | --- | --- |
| Global web search | `source_evidence.v1` | External facts, citation candidates, comparison material, and support evidence. Zhihu full-web search is available through `managed_source=zhihu_global_search` when Cloud config enables it. |
| Zhihu search | `source_evidence.v1` plus optional `topic_candidate.v1` | Audience questions, viewpoints, objections, and writing angles |
| Hot list | `topic_candidate.v1` plus supporting `source_evidence.v1` | Daily topic pool before a draft exists |
| Direct answer | `grounded_answer.v1` | Short answer preview or FAQ/AEO draft from `managed_source=zhida_simple`, `managed_source=zhida_deep`, or `managed_source=zhida_deepsearch`; never final article text |

Toolbox should treat these as composable atoms. A higher-level flow may combine
hot-list candidates, Zhihu research, web evidence, and a grounded answer preview,
but the combination remains `suggestion_only`. The direct-answer atom must not
be accepted as final article text, and any write-like outcome must still route
through the existing local/Core review path.

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

The editor `zhihu_research` button is a fixed productized use of this same
runtime and should read Cloud `source_evidence.v1` / `topic_candidate.v1`
projections when present. Toolbox sends `managed_source=zhihu_research`, which
maps to Cloud runtime input `provider=zhihu` and `source_type=zhihu_research`.
The UI may show returned title, URL, source, author, content type, engagement
counts, and authority metadata as review signals. It must not expose a generic
provider picker, collect Zhihu credentials locally, mix global hot-list items
into the current query by default, copy source text into the draft, rewrite
Zhihu content as an article, or publish anything. It is useful before writing
when the editor needs real audience questions, common objections, angle
discovery, or citation candidates.

The Dashboard `zhihu_hot_topics` widget is a separate topic-pool surface.
Toolbox sends `managed_source=zhihu_hot_topics`, which maps to Cloud runtime
input `provider=zhihu` and `source_type=zhihu_hot_list`. Cloud may return cached
hot-list items and `topic_candidate.v1` projections so multiple WordPress
clients do not spend provider quota on each dashboard view. The UI should
present these as "what might be worth researching today", not as facts, drafts,
schedules, or publication instructions.

For broader source discovery, a feature can call the same Cloud web-search seam
with `managed_source=zhihu_global_search`, `intent=zhihu_global_search`, and a
review query. Toolbox maps that to Cloud runtime input `provider=zhihu` and
`source_type=zhihu_global_search`. The returned `source_evidence.v1` atom can
feed fact checking, citation collection, product comparisons, FAQ/AEO research,
or article background packs. It should not replace official-source review for
claims that will be published.

For short answers, a feature can call `managed_source=zhida_simple`,
`managed_source=zhida_deep`, or `managed_source=zhida_deepsearch` with the same
Cloud web-search seam. Cloud maps these to the Zhihu direct-answer lanes and
returns `grounded_answer.v1` when configured. Toolbox may show that answer as a
preview, FAQ/AEO candidate, or research conclusion candidate, but it must remain
`suggestion_only`; inserting it into a post still requires the local/Core review
path.

## Image Usage

`search-image-source` provides image candidates. For public photo/source
providers it returns external image-source candidates. In explicit
`ai_generated` mode it may normalize a reviewed generated image URL supplied by
the caller, or call a host-provided `npcink_toolbox_ai_image_generation_request`
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
build `npcink-abilities-toolkit/build-image-candidate-adoption-plan` and send the
returned `image_candidate_adoption_plan` to Core from-plan intake.

Cloud Site Knowledge agent handoffs are narrower. To review an evidence-backed
content gap or refresh suggestion, callers may build
`npcink-toolbox/build-site-knowledge-review-plan` and send the returned
`site_knowledge_review_plan` to Core from-plan intake. The resulting proposal
must remain blocked until a human supplies draft `title` and `content`; Toolbox
and Cloud must not approve, preflight, execute, or write WordPress content.

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

If Cloud returns `agent_handoff` for Site Knowledge, Toolbox may surface it as
a governed local handoff candidate. `handoff_type=proposal_input` means "review
this evidence before creating a Core proposal". Toolbox may prepare a local
candidate packet that keeps evidence refs, blocked outputs, and the next local
review action visible, but that packet is still not a Core submission, approval,
preflight, or WordPress write plan.

If Cloud returns `ai_generation_handoff` for image-source candidates, Toolbox
may show a reviewed prompt action for `npcink-toolbox/generate-image`. The
operator must review or edit the prompt before dispatch. The result remains
`image_candidate.v1` evidence for local adoption planning; it is not media
import, featured-image mutation, approval, preflight, or a WordPress write.

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
