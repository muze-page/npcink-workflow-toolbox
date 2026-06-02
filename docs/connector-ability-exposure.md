# Connector Ability Exposure

Status: active first-version contract.

This document summarizes how Toolbox exposes external search, image-source,
embedding, and vector APIs to other AI callers.

## Product Rule

Toolbox is:

- a WordPress admin configuration surface for bounded external tools;
- a server-side executor for configured provider calls;
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

- `magick-ai-toolbox/web-research`
- `magick-ai-toolbox/search-image-source`
- `magick-ai-toolbox/vector-search`
- `magick-ai-toolbox/build-article-brief`
- `magick-ai-toolbox/build-article-write-plan`
- `magick-ai-toolbox/build-media-brief`
- `magick-ai-toolbox/get-content-discoverability-context`

The caller provides task input. Toolbox reads local configuration, performs the
provider request on the server, normalizes the response, and returns a
suggestion-oriented payload.

The caller must not receive provider API keys. Provider secrets remain in:

- PHP constants;
- environment variables;
- stored WordPress connector settings, when the operator chooses that path.

## Provider Boundaries

Tavily is exposed as external web research. Results are source candidates, not
verified truth.

Unsplash is exposed as image-source candidate search. It is not AI image
generation. Payloads must preserve photographer attribution and
`download_location`.

SiliconFlow and Jina are exposed only as synchronous query embedding providers
for vector search in the first version. They do not imply content indexing
ownership.

Qdrant is exposed as configured vector query execution. It does not imply full
RAG, indexing jobs, re-index buttons, stale-index detection, or collection
lifecycle ownership.

## Ability Metadata

Toolbox ability metadata should make the boundary machine-readable:

```text
readonly: true
show_in_rest: true
write_posture: suggestion_only
final_write_path: core_proposal_required
direct_wordpress_write: false
provider_execution: server_side_toolbox
provider_secret_exposure: none
```

Each ability also declares a stable Toolbox scope such as
`cap.toolbox.search`, `cap.toolbox.image_source`,
`cap.toolbox.vector_search`, `cap.toolbox.workflow_suggest`, or
`cap.toolbox.context.read`.

Core or the host that consumes these scopes is responsible for external
AI/app-key authorization.

## Write Posture

Toolbox results are suggestions, candidates, briefs, context, or handoff notes.

If an AI wants to publish a post, update SEO meta, set a slug, import media,
set a featured image, or mutate any WordPress record, the write-like outcome
must go through reusable WordPress abilities and Core proposal governance.

Do not add `confirm_token`, `write_confirmed`, direct publish, direct media
mutation, or direct SEO mutation behavior to Toolbox.

## Raw Provider Payloads

Default outputs should be normalized. Raw provider responses are for local
admin debugging only and must never contain provider keys. Do not return
provider request headers, provider secrets, billing data, quotas, request logs,
or private credentials through REST responses, ability payloads, proposal
payloads, docs, or logs.
