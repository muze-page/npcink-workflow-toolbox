# Connector Ability Exposure

Status: active first-version contract.

This document summarizes how Toolbox exposes Cloud-managed web search status,
image-source, embedding, and vector APIs to other AI callers.

For AI composition examples, including article drafting and content planning, read
`docs/ai-content-composition-abilities.md`.

## Product Rule

Toolbox is:

- a WordPress admin configuration surface for bounded non-search external tools;
- a server-side executor for configured non-search provider calls;
- a WordPress Abilities API exposure layer for AI callers.

Toolbox is not:

- a provider-secret distribution channel;
- a second ability registry, workflow registry, approval store, or control
  plane;
- a final WordPress write owner;
- a content indexing, re-indexing, stale-index, or vector collection lifecycle
  owner.

## Call Model

External AI callers should discover and call Toolbox abilities such as:

- `npcink-toolbox/search-image-source`
- `npcink-toolbox/generate-image`
- `npcink-toolbox/search-site-knowledge`
- `npcink-toolbox/cloud-web-search`
- `npcink-toolbox/get-site-knowledge-status`
- `npcink-toolbox/request-site-knowledge-sync`
- `npcink-toolbox/build-article-write-plan`
- `npcink-toolbox/build-article-batch-write-plan`
- `npcink-toolbox/build-article-media-batch-write-plan`
- `npcink-abilities-toolkit/build-image-candidate-adoption-plan`
- `npcink-toolbox/build-site-knowledge-review-plan`
- `npcink-toolbox/build-nightly-inspection-review-plan`
- `npcink-toolbox/build-media-derivative-handoff`
- `npcink-toolbox/get-content-discoverability-context`
- `npcink-toolbox/validate-content-discoverability-context`
- `npcink-toolbox/build-content-discoverability-brief`
- `npcink-toolbox/build-ai-article-writing-pack`

The caller provides task input. Toolbox reads local configuration or delegates
to Cloud runtime ownership, normalizes the response, and returns a
suggestion-oriented payload. Cloud-managed web search is executed by Npcink
Cloud, not by a local Toolbox provider route.

For SEO, AEO, and GEO context readiness, operators can run:

```bash
wp eval-file tests/smoke-content-discoverability.php -- [post_id]
```

This smoke verifies the content discoverability ability registrations, local
Npcink catalog projection, context validation, and one suggestion-only brief.
It may also report Agent Gateway direct tool-map status when the host projection
matrix is present. Missing `wp_*` Agent Gateway exposure is a host-side
admission task, not a Toolbox route or registry task.

For SEO, AEO, and GEO usage, human operators use the editor sidebar
**Discoverability suggestions** button. External AI callers should treat
`npcink-toolbox/build-content-discoverability-brief` as the machine contract
behind that editor-facing flow. It exposes `seo`, `aeo`, `geo`, `exceptions`,
`special_cases`, and proposal fields. `build-ai-article-writing-pack` is only a
convenience fallback for broad natural-language article requests.

The caller must not receive provider API keys. Provider secrets remain in:

- PHP constants;
- environment variables;
- stored WordPress connector settings for non-search providers, when the
  operator chooses that path.

## Provider Boundaries

Cloud-managed web search is owned by Npcink Cloud. Toolbox does not store web
search provider keys, expose a local web search ability, or register a local
web search REST route. This capability is general-purpose for any AI workflow
that needs external source candidates, not only article drafting. Results are
source candidates, not verified truth. Payloads must preserve provider/source
names when Cloud returns them, source URLs, and enough summary/snippet material
for the caller to build an evidence pack without receiving provider API keys.

Unsplash, Pixabay, and Pexels are exposed through the single
`search-image-source` ability as public image-source candidate search. This
ability is general-purpose for any AI workflow that needs image candidates, not
only article drafting. These providers are not AI image generation. Payloads
must preserve provider name, source URL, photographer attribution, and Unsplash
`download_location` when present.

The same ability also accepts an explicit `ai_generated` candidate mode. In
that mode a caller may provide a reviewed generated image URL, or a host may
handle `npcink_toolbox_ai_image_generation_request` and return generated
image candidates. Toolbox normalizes those candidates with
`source_type=ai_generated`, prompt/model evidence, and human license review
status. Toolbox does not own model routing, prompt management, provider
credentials, billing, media import, or featured-image writes.

All image-source and generated-image candidates are normalized to
`image_candidate.v1`. Public source providers return `source_type=stock`;
generated providers return `source_type=ai_generated`; external or owned
callers may pass the explicit source type. The contract includes
`provider_origin`, `download_url`, `thumbnail_url`, attribution, provenance,
and warnings so OpenClaw, Toolbox buttons, and Core proposal intake can consume
one candidate shape.

`npcink-abilities-toolkit/build-image-candidate-adoption-plan` converts one reviewed
`image_candidate.v1` into an `image_candidate_adoption_plan` with governed
write actions for media upload, metadata update, and optional featured-image
setting. It is read-only and suggestion-only; final adoption still goes through
Core proposals and WordPress abilities.

`npcink-toolbox/build-site-knowledge-review-plan` converts one reviewed Cloud
Site Knowledge agent handoff into a blocked `site_knowledge_review_plan`.
It preserves evidence refs and may create a Core review proposal, but it
requires human title/content input before any later approval or preflight path.

Vector provider configuration is not exposed locally. Embedding providers,
embedding dimensions, vector database endpoints, collection names, rerank, and
detailed index health are managed in Npcink Cloud.

Cloud-managed site knowledge is exposed through three high-level abilities:

- `search-site-knowledge` for semantic site search, related content, writing
  context, internal links, refresh suggestions, image context, FAQ candidates,
  content gap analysis, and publish preflight duplicate checks;
- `get-site-knowledge-status` for Cloud index coverage and freshness status;
- `request-site-knowledge-sync` for bounded Cloud refresh requests from
  public WordPress content, including published posts/pages and bounded
  approved comments attached to those public entries.

Toolbox and Cloud Addon must reject `rebuild`, `delete`, collection lifecycle,
embedding-provider, and stale-index policy operations. Those operations belong
only in Cloud Site Knowledge operator surfaces.

These abilities are general-purpose. Article drafting, admin search,
recommendations, internal-link tooling, and refresh workflows should call the
same site knowledge abilities instead of integrating vector databases or
embedding providers directly. Toolbox calls Cloud through the Cloud Addon
runtime client or the `npcink_toolbox_site_knowledge_cloud_request` host
filter. It does
not store Cloud credentials, create a second ability registry, write WordPress
content, or own Cloud vector collection lifecycle.

## Ability Metadata

Toolbox ability metadata should make the boundary machine-readable:

```text
readonly: true
show_in_rest: true
composition_role: research_evidence|image_source_candidates|site_knowledge_context|...
write_posture: suggestion_only
final_write_path: core_proposal_required
direct_wordpress_write: false
provider_execution: server_side_toolbox
provider_secret_exposure: none
```

Each ability also declares a stable Toolbox scope such as
`cap.toolbox.image_source`, `cap.toolbox.vector_search`,
`cap.toolbox.knowledge.search`,
`cap.toolbox.knowledge.read`, `cap.toolbox.knowledge.sync`,
`cap.toolbox.workflow_suggest`, or `cap.toolbox.context.read`.

Core or the host that consumes these scopes is responsible for external
AI/app-key authorization.

## Write Posture

Toolbox results are suggestions, candidates, briefs, context, or handoff notes.

If an AI wants to publish a post, update SEO meta, set a slug, import media,
set a featured image, or mutate any WordPress record, the write-like outcome
must go through reusable WordPress abilities and Core proposal governance.

`build-article-batch-write-plan` and `build-article-media-batch-write-plan`
return Core handoff artifacts only. They may describe write action candidates
such as `npcink-abilities-toolkit/create-draft`, `npcink-abilities-toolkit/upload-media-from-url`,
`npcink-abilities-toolkit/update-media-details`, or `npcink-abilities-toolkit/set-post-featured-image`, but
Toolbox does not approve, execute, upload media, or set featured images.

Do not add `confirm_token`, `write_confirmed`, direct publish, direct media
mutation, or direct SEO mutation behavior to Toolbox.

## Raw Provider Payloads

Default outputs should be normalized. Raw provider responses are for local
admin debugging only and must never contain provider keys. Do not return
provider request headers, provider secrets, billing data, quotas, request logs,
or private credentials through REST responses, ability payloads, proposal
payloads, docs, or logs.
