# Cross-Repo Boundary Matrix

Status: active
Date: 2026-06-12

This matrix records the current ownership split across the local WordPress
control plane, fixed Toolbox surfaces, Cloud Addon transport, and hosted Cloud
runtime. It is a boundary reference for implementation reviews, not a new
runtime contract.

| Repo | Owns | Must Not Own | Allowed Handoff |
| --- | --- | --- | --- |
| `npcink-governance-core` | Proposal intake, approval state, preflight authorization, audit records, app-key scope policy, and governance decision status. | Final WordPress write execution, reusable first-party ability definitions, hosted model routing, workflow runtime, queues, schedulers, provider keys, or Cloud runtime state. | Receives proposal-ready payloads, issues commit preflight context, and records execution outcomes from the adapter path. |
| `npcink-abilities-toolkit` | Reusable WordPress Ability definitions, schemas, callbacks, dry-run previews, and host-approved commit callbacks. | Approval truth, audit truth, provider/model routing, workflow runtime, provider credentials, or deciding whether a final write is authorized. | Runs dry-run by default; commits only when the host supplies approved commit context. |
| `npcink-ai-client-adapter` | AI client channel, signed REST entrypoint, Core proposal/status proxy, read ability execution, and allowlisted post-Core execution profiles. | First-party ability definitions, approval truth, generic approval proxy, workflow runtime, Cloud credentials, provider truth, or direct writes outside Core preflight. | Sends proposal/from-plan payloads to Core, consumes Core preflight, and passes host approval context to approved ability callbacks. |
| `npcink-workflow-toolbox` | WordPress operator UI, fixed workflow buttons, suggestion artifacts, reviewable plans, content context, and Cloud-backed tool UX. Runtime REST routes, ability ids, options, and hook names keep the first-version `npcink-toolbox` contract for compatibility. | Core governance truth, final WordPress authorization, reusable ability definitions owned by Toolkit, workflow runtime, queue/scheduler ownership, vector collection lifecycle, provider billing, or direct metadata/media/SEO writes. | Produces Core-ready plans and suggestions for Adapter/Core review. The only current direct local write exception is Local Admin Consent for one existing image attachment as the current post featured image, with Core audit and rollback on completion-audit failure. |
| `npcink-cloud-addon` | WordPress-side Cloud Base URL/API key settings, request signing, bounded Cloud runtime transport, entitlements/health reads, artifact download transport, and observability forwarding. | Proposal, approval, audit, WordPress writes, billing truth, provider routing truth, prompt/preset truth, workflow runtime, or local ability registry state. | Sends signed bounded requests to Cloud and returns Cloud runtime results to local WordPress surfaces. |
| `npcink-ai-cloud` | Hosted runtime execution, provider adapters, usage/billing/entitlement service evidence, health diagnostics, Site Knowledge runtime/detail, artifacts, and read-only runtime metadata projections. | WordPress control-plane truth, local ability registry, local workflow registry, final approval/preflight/audit truth, prompt/router/preset local truth, or WordPress writes. | Returns `suggestion_only` or proposal-input-ready runtime results to the local WordPress control plane. |

## Write Paths

Final WordPress writes must flow through Toolkit ability callbacks after Core
approval, Core preflight, and Adapter handoff of host approval context.

The only current Toolbox Local Admin Consent write path is
`/local-admin-consent/featured-image`: one existing WordPress image attachment
may be set as the current post featured image by a present administrator, with
Core audit before and after the write. The required guard documents are
`docs/boundary-exceptions.md` and `docs/adversarial-boundary-review.md`; static
contracts must fail if a second Local Admin Consent route or direct write path
appears without a new ADR. These operations remain Core proposal paths unless a
separate boundary decision defines a specific new local contract:

- metadata apply
- SEO mutation
- media import
- settings mutation
- batch operation
- any external/delegated write

## Review Rule

If a change makes Cloud, Cloud Addon, Adapter, or Toolbox look like any of these
owners, stop the implementation and add or update a boundary note before adding
code:

- second ability registry, workflow registry, approval store
- prompt/router/preset truth
- WordPress write control plane

## Remaining Migration Audit

No broad migration should continue by default. The current Content Support
shape is intentionally split: Toolkit owns reusable WordPress ability-shaped
artifacts, Toolbox owns the operator/editor surface, Cloud owns hosted provider
runtime and Site Knowledge evidence, and Core/Adapter own proposal, approval,
preflight, audit, and governed execution.

Move to Toolkit only when all of these are true:

- the logic is reusable across Toolbox, OpenClaw, and third-party WordPress
  hosts;
- the output is a stable WordPress ability artifact, plan, dry-run, or
  host-approved callback contract;
- the logic has no provider/model routing, hosted runtime, billing, quota,
  request-log, vector-index, queue, scheduler, lease, or retry ownership;
- the logic has no Toolbox editor/admin UI state and no OpenClaw projection
  state;
- the result remains review-only, dry-run, or Core-proposal-ready unless Core
  and Adapter supply an approved host commit context.

Do not migrate provider runtime, Cloud indexing, editor UI state, proposal
approval, audit, preflight, or final WordPress writes into Toolkit.

## Migration Candidate Boundary Table

Use this table before opening a new cross-repo migration branch. A migration is
worth doing only when it reduces long-term ownership confusion without turning
another project into a second control plane.

| Candidate | Target | Decision | Gate |
| --- | --- | --- | --- |
| Media ALT/caption review-set builder | `npcink-abilities-toolkit` | Done for the reusable builder; keep the Toolbox local fallback through the first release window. | Post-merge smoke must show `source_ability_id=npcink-abilities-toolkit/build-media-alt-caption-review-set`, `runtime_owner=npcink-abilities-toolkit`, unchanged sampled attachment metadata, no proposal, no execution, no media derivative run, and no direct write. |
| Taxonomy/tag review-set builder | `npcink-abilities-toolkit` | Migrated for the reusable builder; Toolbox prefers `npcink-abilities-toolkit/build-taxonomy-tag-review-set` and keeps a compatibility fallback built from Toolkit taxonomy suggestions for older Toolkit installs. | Must not create terms, assign terms, mutate SEO fields, persist feedback truth, call Cloud runtime, or create proposals. Toolbox keeps editor/admin review state and Core handoff presentation. |
| Internal-link review-set builder | `npcink-abilities-toolkit` | Candidate after taxonomy/tag review sets. Build only reusable grouping, blocked reasons, and review artifact shape from supplied candidate evidence. | Must not insert links, patch post content, crawl/index content, own Site Knowledge, or create proposals. Toolbox keeps selection, copy/open actions, and operator review UI. |
| Content metadata apply-plan envelope | `npcink-abilities-toolkit` | Already Toolkit-owned; extend only if Core/Adapter contract changes require a stable reusable envelope. | Must remain Core-proposal-ready or dry-run until Core approval and Adapter preflight provide approved host context. |
| Media metadata apply path | `npcink-abilities-toolkit` plus Core/Adapter | Do not start from Toolbox. Design separately only after a governed write path is accepted. | Requires Toolkit write ability schema, Core proposal/preflight/audit, Adapter execution profile, and final WordPress callback. It must not expand Local Admin Consent to ALT/caption metadata. |
| Site Knowledge bridge health and delivery status | `npcink-cloud-addon` | Belongs in Cloud Addon, not Toolbox. Toolbox may consume a shallow readiness projection. | Addon may show verified connection, buffered public changes, last delivery, last error, next flush, and Cloud Site Knowledge links. It must not expose rebuild/delete, collection lifecycle, provider settings, vector DB endpoints, or stale-index controls. |
| Cloud runtime runs recent/status/result/retry detail | `npcink-cloud-addon` | Belongs in Cloud Addon Runtime Runs. Toolbox may keep compatibility routes or links only. | Addon must stay a runtime/detail surface. It must not become proposal approval, local scheduler truth, run-store truth for WordPress, or a second workflow console. |
| Image-source, audio, and generated-image transport detail | `npcink-cloud-addon` | Transport/status/detail belongs in Cloud Addon or Cloud service plane. Toolbox keeps fixed buttons and candidate review UX. | Addon owns signed transport and shallow diagnostics only. Toolbox must not store provider keys, billing/quota truth, request logs, media imports, or featured-image writes. |
| Hosted AI title, summary, outline, polish, and image-context generation | `npcink-ai-cloud` runtime, surfaced by Toolbox | Do not migrate to Toolkit. | These are provider/model runtime outputs. Toolkit can consume reviewed evidence only after the output is normalized into a stable WordPress artifact. |
| Progressive local recommendations | Keep in Toolbox for now | Defer extraction. | Extract only if a stable host-reusable artifact emerges. While it depends on editor fingerprinting, loading state, and UI fallback behavior, it remains Toolbox product glue. |
| Operator feedback and quality loop | Keep outside Toolkit | Do not migrate to Toolkit now. | Feedback and eval signals are product/runtime evidence. Move only bounded runtime detail to Cloud/Add-on surfaces, not WordPress ability definitions. |
| OpenClaw projection | Adapter/OpenClaw path | Do not migrate to Toolbox or Toolkit. | OpenClaw should call the same Toolkit artifacts and Core proposal paths. Toolbox remains the fixed-button projection, not the natural-language projection owner. |

Recommended order:

1. Finish and verify the media ALT/caption merge on `master`.
2. Add only the taxonomy/tag review-set builder candidate to Toolkit.
3. Re-run the cross-repo boundary matrix and real WordPress smoke.
4. Reassess before moving internal-link review sets or any Cloud Addon detail.

| Candidate | Decision | Boundary |
| --- | --- | --- |
| Internal-link candidate assembly | Migrated; no further migration. | `npcink-abilities-toolkit/resolve-internal-link-targets` owns `internal_link_candidates.v1`. Toolbox passes bounded editor context and optional Cloud related-content evidence, then renders copy/open review actions. |
| Taxonomy/tag candidate ranking and review set | Migrated; keep split. | `npcink-abilities-toolkit/suggest-post-taxonomy-terms` owns candidate ranking and `npcink-abilities-toolkit/build-taxonomy-tag-review-set` owns the reusable `taxonomy_tag_review_set.v1` builder. Toolbox supplies editor context, related-term evidence, UI review state, and Core handoff presentation; it must not create new terms or assign taxonomy outside a governed handoff. |
| Comment reply suggestion artifact | Migrated; no further migration. | `npcink-abilities-toolkit/build-comment-mention-reply-suggest` owns review-only reply candidates. Toolbox owns comment selection and editor presentation; it must not publish comments or change status. |
| Image candidate review projection | Migrated; no further migration. | `npcink-abilities-toolkit/build-image-candidate-review-artifact` owns `image_candidate_review.v1` projections. Toolbox and Cloud still own image-source search, hosted image candidate requests, and provider UX. |
| Image candidate adoption plan | Already Toolkit-owned. | `npcink-abilities-toolkit/build-image-candidate-adoption-plan` owns adoption planning for reviewed candidates. Core/Adapter govern any final media action. |
| Content metadata apply plan | Already Toolkit-owned. | `npcink-abilities-toolkit/build-content-metadata-apply-plan` owns the reusable apply-plan artifact. Toolbox only packages accepted operator selections for Core proposal intake. |
| Media ALT/caption review set | Migrated; keep split. | `npcink-abilities-toolkit/build-media-alt-caption-review-set` owns the reusable `media_alt_caption_review_set.v1` builder for supplied metadata and optional reviewed evidence. Toolbox still owns UI, media selection, review state, Cloud evidence requests, local fallback, and Core handoff presentation. |
| SEO meta handoff preview | Keep split; defer only if a reusable preview artifact stabilizes. | Toolkit owns `npcink-abilities-toolkit/set-post-seo-meta` for approved writes. Toolbox may present a single-post Core handoff preview but must not own SEO mutation. |
| Title, summary, outline, polish, and other hosted AI text outputs | Do not migrate now. | These are provider/model runtime results surfaced as Toolbox suggestions. Moving prompt/runtime generation into Toolkit would make Toolkit too heavy. |
| Image-source search and hosted image candidate requests | Do not migrate. | Toolbox owns source UX and Cloud/provider candidate requests; Toolkit may consume already retrieved candidate evidence for review or adoption artifacts only. |
| Site Knowledge / vector related content | Do not migrate. | Cloud owns vector/index/rerank runtime and collection lifecycle. Toolbox may pass optional evidence into Toolkit; Toolkit must not become a RAG, indexing, or collection owner. |
| Media derivative preview and batch runtime | Do not migrate. | Cloud/Adapter/Core own runtime, proposal, approval, and execution boundaries. Toolkit may own reusable planning and write abilities; Toolbox must not become a queue, scheduler, or batch writer. |
| Progressive local recommendations | Defer. | Keep aggregation in Toolbox while it is editor UX glue. Extract only a stable, host-reusable artifact that satisfies the Move-to-Toolkit rule above. |
| Operator feedback and quality loop | Do not migrate to Toolkit. | Feedback capture, evaluation, and quality signals are runtime/product evidence. They must not become WordPress ability definitions unless a concrete reusable ability contract appears. |
| OpenClaw projection | Do not migrate to Toolbox or Toolkit. | OpenClaw/Adapter own natural-language projection. They should call the same Toolkit artifacts and Core proposal paths, not create a parallel Toolbox button contract. |

## Next Work Rule

Future Content Support work should start from this audit before adding new
abilities. Add a Toolkit ability only for a repeated, host-reusable WordPress
artifact or callback contract. Otherwise keep the work in Toolbox UI, Cloud
runtime, Core governance, or Adapter/OpenClaw projection according to the matrix
above.
