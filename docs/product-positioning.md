# Product Positioning

Status: active for the first Toolbox build.

Npcink Toolbox is the WordPress operator-facing AI tool surface: it gives
site owners and editors buttons for Cloud-managed web search, Cloud-managed
image-source candidates, Cloud-managed site knowledge abilities, and repeatable
AI-assisted content-support workflows.

## One-Sentence Positioning

Npcink Toolbox turns WordPress content-support abilities into safe,
click-driven operator tools around human-written articles.

## Relationship To OpenClaw

Toolbox and OpenClaw should expose the same governed workflows through
different operator entry points:

- OpenClaw is the natural-language channel for broad requests.
- Toolbox is the fixed-button product surface for the same repeatable flows.
- Adapter publishes OpenClaw recipe guidance and forwards reviewed plans.
- Core owns proposal, approval, preflight, and final WordPress write truth.
- Abilities execute the reusable WordPress read/write callbacks.
- Cloud may provide hosted runtime processing, but not write authority.

When a workflow can be used both by OpenClaw and by a Toolbox button, the
Toolbox button should reuse the same ability ids, plan artifact shapes, and
Core proposal handoff as the OpenClaw recipe. Toolbox must not fork the flow
into a separate approval path, media registry, prompt/model control plane, or
WordPress write executor.

For batch workflows, the order is stricter: prove the OpenClaw/Adapter batch
contract first, including execution profiles, Core approval/preflight evidence,
per-action results, retry guidance, and Abilities write callbacks; only then
freeze the accepted path into a Toolbox button. Toolbox is the fixed-button
best-practice projection of OpenClaw, not the place to invent batch execution
semantics.

## Primary Users

- WordPress administrators who want controlled AI tools without touching raw
  provider APIs.
- Editors who need taxonomy/tag, internal-link, image-source, SEO/AEO/GEO, and
  publish-readiness support around human-written articles.
- Npcink operators who need fixed workflow buttons that produce reviewable
  handoffs.

## Core Jobs

1. Provide a visible admin product surface for external AI tools.
2. Run configured external search, optional result reading, Cloud-managed
   image-source requests, and Cloud-managed site knowledge operations from a
   controlled WordPress UI.
3. Convert repeated operator workflows into fixed buttons.
4. Return planning artifacts, candidates, and handoff notes.
5. Let operators fill non-secret SEO, AEO, and GEO content context for
   suggestion workflows and third-party AI callers.
6. Prioritize work around the article body: taxonomy/tag candidates,
   internal-link candidates, image candidates, SEO/AEO/GEO briefs, media
   metadata plans, and publish/readiness checks.
7. Keep article text creation with human editors; expose Article Assistant only
   as a fallback workbench for reviewed local draft artifacts.
8. Treat `media_optimization_v1` as the fixed governed Optimize Existing Image
   workflow, improving the existing Toolbox surface rather than creating a
   duplicate runner.
9. Preserve Core and Abilities boundaries for final WordPress writes.

## Non-Goals

Npcink Toolbox does not own:

- Core proposal records, approvals, audit logs, or app-key governance;
- reusable WordPress ability packages owned by `npcink-abilities-toolkit`;
- final WordPress write execution;
- provider marketplace, billing, long-term quota, request-log products, or
  multi-provider routing;
- workflow runtime, queues, retry leases, or background schedulers;
- MCP, Agent Gateway, Open API, or OpenClaw projection truth.

## Product Split

| Project | Owns |
| --- | --- |
| `npcink-governance-core` | Governance, proposal records, approval boundaries, audit logs, and host policy. |
| `npcink-abilities-toolkit` | Reusable WordPress Abilities API definitions, schemas, callbacks, and dry-run previews. |
| `npcink-toolbox` | Operator tool UI, fixed workflow buttons, content discoverability context, configured external research, optional result reading, Cloud-managed image-source candidates, and Cloud-managed site knowledge actions. |
| Provider connector plugins | Durable provider configuration, key rotation, quotas, billing, and request logs when those surfaces mature. |

## Design Rule

If a feature is a button or screen that helps an operator generate a suggestion,
candidate, or planning artifact, it may belong in Toolbox.

If a feature lets an operator fill non-secret site guidance that third-party AI
can consume as suggestion-only context, it may belong in Toolbox.

If a feature authorizes, commits, audits, schedules, or owns final WordPress
writes, it belongs outside Toolbox.

Default buttons should solve around-the-body work before offering article draft
handoffs. Draft handoffs are acceptable only after a reviewed human draft exists
and the final write goes through Core proposal governance.

Media optimization is the first fixed governed media workflow. Toolbox may
present **Optimize Existing Image** as `media_optimization_v1`, with visible
steps from media selection through Cloud preview and Core proposal handoff, but
it must not add a workflow runtime, persistent run store, media registry,
approval path, provider routing UI, or direct WordPress write executor.
Batch media conversion and direct replacement should reuse the same
OpenClaw/Adapter/Core/Abilities replacement path once the OpenClaw batch
contract is accepted; Toolbox should not duplicate attachment replacement or URL
repair logic locally.

High-frequency article support belongs in the WordPress post editor as a
Toolbox-owned panel, not only on the standalone Toolbox admin page. The editor
panel may run fixed flows for writing preparation, publish preflight,
summary suggestions, category suggestions, tag suggestions, internal-link
candidates, and image candidates, but it must keep the same suggestion-only and
Core-governed write posture as the admin surface. Internal-link candidates are
manual review aids, publish preflight is a unified advisory review panel, SEO
metadata is only a single-post Core handoff preview, and new vocabulary remains
Core policy-gated strong review.

Unsplash, Pixabay, and Pexels are image-source connectors, not AI
image-generation connectors. Toolbox must preserve attribution and source
metadata in its candidate payloads; Unsplash candidates must also preserve
download tracking metadata. AI-generated image candidates are a separate
explicit candidate mode: callers may provide reviewed generated image URLs, or
a host may provide a bounded generation runtime seam. Toolbox must not own
model routing, prompt management, provider billing, or media import.

Cloud-managed Site Knowledge is the vector surface. Toolbox may collect bounded
public WordPress manifests, trigger Cloud sync, show returned status, and call
semantic site search. Embedding providers, vector database endpoints,
collection names, dimensions, rerank, stale detection, and index lifecycle are
Cloud operator responsibilities.

Cloud-managed site knowledge is the preferred high-level surface for semantic
site search, related content, writing context, internal links, refresh
suggestions, and image context. Toolbox may expose these as local WordPress
Abilities while Cloud owns embeddings, vector storage, indexing, reranking, and
status detail. Toolbox must not store Cloud credentials or become the content
index lifecycle owner.
