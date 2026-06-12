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
| `magick-ai-adapter` | AI client channel, signed REST entrypoint, Core proposal/status proxy, read ability execution, and allowlisted post-Core execution profiles. | First-party ability definitions, approval truth, generic approval proxy, workflow runtime, Cloud credentials, provider truth, or direct writes outside Core preflight. | Sends proposal/from-plan payloads to Core, consumes Core preflight, and passes host approval context to approved ability callbacks. |
| `magick-ai-toolbox` | WordPress operator UI, fixed workflow buttons, suggestion artifacts, reviewable plans, content context, and Cloud-backed tool UX. | Core governance truth, final WordPress authorization, reusable ability definitions owned by Toolkit, workflow runtime, queue/scheduler ownership, vector collection lifecycle, provider billing, or direct metadata/media/SEO writes. | Produces Core-ready plans and suggestions for Adapter/Core review. The only current direct local write exception is Local Admin Consent for one existing image attachment as the current post featured image, with Core audit and rollback on completion-audit failure. |
| `magick-ai-cloud-addon` | WordPress-side Cloud Base URL/API key settings, request signing, bounded Cloud runtime transport, entitlements/health reads, artifact download transport, and observability forwarding. | Proposal, approval, audit, WordPress writes, billing truth, provider routing truth, prompt/preset truth, workflow runtime, or local ability registry state. | Sends signed bounded requests to Cloud and returns Cloud runtime results to local WordPress surfaces. |
| `magick-ai-cloud` | Hosted runtime execution, provider adapters, usage/billing/entitlement service evidence, health diagnostics, Site Knowledge runtime/detail, artifacts, and read-only runtime metadata projections. | WordPress control-plane truth, local ability registry, local workflow registry, final approval/preflight/audit truth, prompt/router/preset local truth, or WordPress writes. | Returns `suggestion_only` or proposal-input-ready runtime results to the local WordPress control plane. |

## Write Paths

Final WordPress writes must flow through Toolkit ability callbacks after Core
approval, Core preflight, and Adapter handoff of host approval context.

The only current Toolbox Local Admin Consent write path is
`/local-admin-consent/featured-image`: one existing WordPress image attachment
may be set as the current post featured image by a present administrator, with
Core audit before and after the write. These operations remain Core proposal
paths unless a separate boundary decision defines a specific new local contract:

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
