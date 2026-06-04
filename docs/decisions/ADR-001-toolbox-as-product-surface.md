# ADR-001: Build Toolbox As A Product Surface

## Status

Accepted

## Date

2026-06-02

## Context

Magick AI already has separate projects for governance and reusable WordPress
abilities:

- `magick-ai-core` governs proposal records, approvals, preflight, and audit.
- `magick-ai-abilities` registers reusable WordPress Abilities API definitions.

The desired new functionality includes external web research, image-source
search, vector search, and fixed-flow operator buttons. These features are
product-facing and provider-facing. They need admin UX and workflow affordances,
but they should not turn Core into a provider gateway or turn Abilities into a
semantic runtime.

## Decision

Create `magick-ai-toolbox` as a standalone WordPress plugin.

Toolbox owns the operator-facing product surface for external tools and fixed
workflow buttons. It may call Tavily, Bocha, Jina Reader as a bounded
post-search enhancement, configured image-source providers such as Unsplash,
Pixabay, and Pexels, SiliconFlow, Jina, Qdrant, and future connector APIs for
bounded suggestion tasks and may register its actions through the WordPress
Abilities API.

Toolbox does not own final WordPress write approval, Core audit truth, reusable
first-party WordPress ability packs, queues, MCP, Agent Gateway, or OpenClaw
control-plane state.

## Alternatives Considered

### Add These Features To Core

Pros:

- Core already has governance context.
- Fewer plugins in the short term.

Cons:

- Would mix governance truth with provider execution.
- Would pressure Core to store provider keys and runtime state.
- Conflicts with Core's governance-only boundary.

Rejected.

### Add These Features To Abilities

Pros:

- Abilities already exposes callable operations.
- Ability ids and schemas would be easy to discover.

Cons:

- External search, image-source lookup, embeddings, and vector search depend on
  provider/runtime ownership.
- Abilities is meant to remain a reusable WordPress ability package, not a
  semantic/model runtime.

Rejected for the main plugin. Toolbox can still register its actions through
the Abilities API.

### Keep Everything In A Host Runtime

Pros:

- Host can manage provider keys, quotas, logs, and runtime policy centrally.

Cons:

- Operators still need a visible WordPress product surface.
- Fixed workflow buttons would be harder to iterate independently.

Rejected as the only surface. Host/runtime integration remains a future option.

## Consequences

- Toolbox can evolve as a product plugin without weakening Core or Abilities.
- Provider configuration is acceptable for MVP, but durable billing, quotas,
  request logs, and key rotation may later move to connector plugins.
- Write-like actions must hand off to WordPress abilities and Core governance.
- Long-running workflows require a future runtime decision before implementation.
- Tavily and Bocha must be treated as source-candidate search connectors, not
  verified truth providers. Jina Reader may enhance selected result URLs but
  must not become a crawler or search provider.
- Unsplash, Pixabay, and Pexels must be treated as image-source connectors with
  attribution/source metadata, not as AI image-generation providers. Unsplash
  candidates must preserve download tracking. AI-generated image candidates are
  a separate explicit mode that may normalize a reviewed generated-image URL or
  use a host runtime seam; Toolbox must not own model routing, prompt
  management, provider billing, media import, or featured-image writes.
- Vector search may create a synchronous query embedding through SiliconFlow or
  Jina. WordPress content indexing, re-index jobs, and vector collection
  lifecycle remain separate decisions.
- Jina Reranker is a reserved workflow-level enhancement, not a first-version
  runtime feature.
