# Product Positioning

Status: active for the first Toolbox build.

Magick AI Toolbox is the WordPress operator-facing AI tool surface: it gives
site owners and editors buttons for Tavily and Bocha research, Jina Reader
result enhancement, Unsplash, Pixabay, and Pexels image-source candidates,
Cloud-managed site knowledge abilities, SiliconFlow or Jina query embeddings,
Qdrant vector search, and repeatable AI-assisted planning workflows.

## One-Sentence Positioning

Magick AI Toolbox turns third-party research, image-source, and vector-search
connectors into safe, click-driven WordPress operator tools.

## Primary Users

- WordPress administrators who want controlled AI tools without touching raw
  provider APIs.
- Editors who need research, image-source, vector-search, and article planning
  support.
- Magick AI operators who need fixed workflow buttons that produce reviewable
  handoffs.

## Core Jobs

1. Provide a visible admin product surface for external AI tools.
2. Run configured external search, optional result reading, configured
   image-source search, configured query embeddings, and Qdrant vector queries
   from a controlled WordPress UI.
3. Convert repeated operator workflows into fixed buttons.
4. Return planning artifacts, candidates, and handoff notes.
5. Let operators fill non-secret SEO, AEO, and GEO content context for
   suggestion workflows and third-party AI callers.
6. Preserve Core and Abilities boundaries for final WordPress writes.

## Non-Goals

Magick AI Toolbox does not own:

- Core proposal records, approvals, audit logs, or app-key governance;
- reusable WordPress ability packages owned by `magick-ai-abilities`;
- final WordPress write execution;
- provider marketplace, billing, long-term quota, request-log products, or
  multi-provider routing;
- workflow runtime, queues, retry leases, or background schedulers;
- MCP, Agent Gateway, Open API, or OpenClaw projection truth.

## Product Split

| Project | Owns |
| --- | --- |
| `magick-ai-core` | Governance, proposal records, approval boundaries, audit logs, and host policy. |
| `magick-ai-abilities` | Reusable WordPress Abilities API definitions, schemas, callbacks, and dry-run previews. |
| `magick-ai-toolbox` | Operator tool UI, fixed workflow buttons, content discoverability context, configured external research, optional result reading, configured image-source candidates, configured query embeddings, and Qdrant vector search actions. |
| Provider connector plugins | Durable provider configuration, key rotation, quotas, billing, and request logs when those surfaces mature. |

## Design Rule

If a feature is a button or screen that helps an operator generate a suggestion,
candidate, or planning artifact, it may belong in Toolbox.

If a feature lets an operator fill non-secret site guidance that third-party AI
can consume as suggestion-only context, it may belong in Toolbox.

If a feature authorizes, commits, audits, schedules, or owns final WordPress
writes, it belongs outside Toolbox.

Unsplash, Pixabay, and Pexels are image-source connectors, not AI
image-generation connectors. Toolbox must preserve attribution and source
metadata in its candidate payloads; Unsplash candidates must also preserve
download tracking metadata. AI-generated image candidates are a separate
explicit candidate mode: callers may provide reviewed generated image URLs, or
a host may provide a bounded generation runtime seam. Toolbox must not own
model routing, prompt management, provider billing, or media import.

Qdrant is a vector database connector, not a complete knowledge system by
itself. Toolbox may create a single query embedding through SiliconFlow or Jina
so other AI clients can call vector search with text. WordPress content
indexing, re-index jobs, stale index detection, and vector collection lifecycle
still require a separate stage decision.

Cloud-managed site knowledge is the preferred high-level surface for semantic
site search, related content, writing context, internal links, refresh
suggestions, and image context. Toolbox may expose these as local WordPress
Abilities while Cloud owns embeddings, vector storage, indexing, reranking, and
status detail. Toolbox must not store Cloud credentials or become the content
index lifecycle owner.
