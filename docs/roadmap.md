# Roadmap

Status: planning baseline.

## Stage 0 - Project Contract

Goal: make the standalone plugin understandable to future sessions.

Done:

- WordPress plugin scaffold.
- Admin toolbox screen.
- Tavily, Unsplash, SiliconFlow, Jina, and Qdrant connector settings.
- Content discoverability context setting and read-only Abilities exposure.
- Content discoverability context validation and one-item brief abilities.
- REST routes.
- Abilities registrations.
- Static contract tests.
- Boundary and architecture docs.

Gate:

```bash
composer test:all
composer validate --no-check-publish
```

## Stage 1 - Local Operator MVP

Goal: prove the Toolbox is useful as a manual admin tool.

Target features:

- Tavily research action with source-aware response display.
- Unsplash image-source action with browser preview instead of raw JSON only.
- SiliconFlow or Jina text-to-vector query plus Qdrant vector query against a
  configured collection.
- Article brief button.
- Media brief button.
- Content Context form for SEO, AEO, and GEO guidance.
- Clear empty/error/loading states.
- Local WordPress activation smoke.

Non-goals:

- background jobs;
- final WordPress writes;
- multi-provider routing;
- quota and billing UI.
- WordPress content indexing.

## Stage 2 - Governed Handoffs

Goal: connect useful suggestions to governed WordPress changes.

Target features:

- create draft proposal handoff;
- article write plan artifact for one reviewed draft;
- set featured image proposal handoff;
- update media metadata proposal handoff;
- set SEO meta proposal handoff;
- use content discoverability context when preparing SEO/AEO/GEO proposal
  payloads;
- validate content discoverability context before third-party AI usage;
- handoff status display that points operators to Core review.

Rules:

- every write-like action creates or prepares a Core proposal;
- Toolbox does not bypass Core approval;
- proposal payloads use real WordPress ability ids.

## Stage 3 - Knowledge Base Operations

Goal: make vector search practical for site content.

Target features:

- site-content indexing plan;
- embedding provider expansion beyond SiliconFlow and Jina;
- manual re-index button;
- index status display;
- document/source coverage report;
- stale index warnings;
- internal-link and old-article refresh suggestions.

Open decision:

- whether vector indexing lives in Toolbox, a provider connector, or a future
  `magick-ai-knowledge` plugin.
- which embedding provider owns text-to-vector conversion.

## Stage 4 - Productized Workflow Buttons

Goal: add repeatable operator flows without creating a workflow runtime.

Candidate buttons:

- generate article outline with references;
- find Unsplash featured image candidates;
- optimize old article;
- complete media alt and caption suggestions;
- find internal-link opportunities;
- build FAQ suggestions;
- build content discoverability suggestions from the operator-filled context;
- check source coverage.
- rerank source, image, and vector candidates with Jina Reranker.
- extract cleaner source content with Jina Reader.

Rule:

Buttons may run bounded synchronous planning actions. Long-running orchestration,
queues, retries, and scheduling require a separate runtime decision.

## Deferred Decisions

- provider connector split;
- vector store ownership;
- request log ownership;
- cost and quota display;
- editor-side UI;
- multisite behavior;
- scoped non-admin permissions.
