# Site Knowledge UI Migration Closeout - 2026-06-30

## Status

Closed locally.

This record summarizes the 2026-06-30 product-boundary cleanup that moved
operator-facing Site Knowledge indexing controls out of Npcink Workflow Toolbox
and left Toolbox focused on fixed best-practice buttons.

## Starting Question

The Toolbox Site Knowledge page had grown into a content-library/index
operations surface. It exposed index status, index refresh actions, and setup
links from Advanced. That made the admin UI feel too complex for operators and
blurred the product boundary:

- Toolbox is the fixed-button workflow surface.
- Cloud Addon is the WordPress-side Cloud connector and transport/detail
  surface.
- Cloud owns Site Knowledge indexing, freshness policy, vector collection
  lifecycle, rerank, quota, and deep diagnostics.

The agreed direction was to migrate content-index related capability and UI to
`npcink-cloud-addon`, while keeping `npcink-workflow-toolbox` focused on
repeatable review-only workflow buttons.

## Decision

Toolbox no longer presents Site Knowledge as an operator index-management
surface.

Accepted boundary:

- Toolbox may keep Site Knowledge REST routes and WordPress Ability ids for
  compatibility with existing callers.
- Toolbox may keep a hidden secondary Content Library Usage panel for read-only
  status, search checks, and evidence-backed review handoff context.
- Toolbox must not show daily index start, refresh, rebuild, stale-index, or
  collection lifecycle controls.
- Toolbox Advanced must not link to Cloud Addon content-library setup. Advanced
  should contain only low-frequency Toolbox-owned review and handoff entries.
- Cloud Addon owns connector state, public content refresh transport, buffered
  change delivery, and shallow Site Knowledge status/detail.
- Cloud service remains the owner for indexing, freshness policy, vector
  collection lifecycle, rerank, quota, and deep troubleshooting.

## What Changed

### Toolbox Admin UI

The Advanced page no longer contains the Content library connection card.

The removed card previously pointed operators from Toolbox to Cloud Addon for
Site Knowledge connector status and public refresh. It was removed because even
a cross-link kept content-library setup inside the Toolbox mental model.

The whole empty Setup group was also removed from Advanced. Advanced now keeps:

- Site check details for read-only Full-site Insights detail.
- Morning Brief preview for scheduled-review planning and governed handoff
  preview.

### Content Library Usage Panel

The old Site Content Index panel was renamed and reframed as Content Library
Usage.

It no longer exposes:

- Start indexing.
- Refresh index.
- Sync form submission.
- Toolbox-owned index action state.
- Local polling for index refresh completion.

It still preserves:

- read-only status loading;
- search checks;
- Site Knowledge review handoff context;
- compatibility for stable deep links and existing REST/Ability callers.

### Admin JavaScript

Toolbox admin JavaScript no longer owns Site Knowledge sync form behavior:

- removed sync busy handling;
- removed index action state switching;
- removed sync polling;
- removed mapping from a Toolbox button to Cloud rebuild.

Status rendering, search checks, bridge status guidance, and Core review
handoff rendering remain.

### Documentation

Docs now describe Site Knowledge this way:

- Toolbox consumes Cloud-managed Site Knowledge results in fixed workflow
  surfaces.
- Cloud Addon owns the WordPress-side connector/status/refresh transport.
- Cloud owns indexing and vector lifecycle.
- Toolbox Advanced is not a setup directory for Cloud Addon content-library
  management.

## What Was Intentionally Not Removed

The following compatibility contracts remain in Toolbox:

- `GET /wp-json/npcink-toolbox/v1/site-knowledge/status`
- `POST /wp-json/npcink-toolbox/v1/site-knowledge/search`
- `POST /wp-json/npcink-toolbox/v1/site-knowledge/sync`
- `npcink-toolbox/search-site-knowledge`
- `npcink-toolbox/get-site-knowledge-status`
- `npcink-toolbox/request-site-knowledge-sync`
- `npcink-toolbox/build-site-knowledge-review-plan`

These are retained because external callers, OpenClaw-style composition, and
existing host integrations may still depend on the first-version `npcink-toolbox`
namespace and ability ids.

Removing those contracts requires a separate compatibility review and migration
plan. This cleanup only removes operator-facing index-management UI from
Toolbox.

## Verification

Toolbox:

```bash
composer test:all
git diff --check
composer quality:matrix
```

Cloud Addon was also checked during the same migration slice:

```bash
composer run test:all
git diff --check
rg "/v1/runtime/workflows/runs|wp_insert_post|wp_update_post" --glob '*.php' --glob '!build/**' .
```

The Cloud Addon forbidden-pattern scan returned no matches.

## Boundary Guardrails For Future Work

Do not reintroduce these into Toolbox:

- visible Site Knowledge index start or refresh controls;
- local index/re-index/stale-index management;
- vector collection lifecycle settings;
- local content-index jobs or queue ownership;
- Cloud Addon content-library setup links from Toolbox Advanced;
- a second Site Knowledge operations console inside Toolbox.

If operators need more Site Knowledge operations detail, improve Cloud Addon or
the Cloud service-plane UI. Toolbox should only use the resulting context in
fixed suggestion and review workflows.

## Next Recommendation

Keep this slice closed.

The next useful work is to validate the simplified Advanced page with operators:

- can they find Full-site Insights detail and Morning Brief preview;
- do they stop looking for content-index controls in Toolbox;
- does Cloud Addon clearly explain Site Knowledge connector state and refresh
  transport.

Only reopen Toolbox if a fixed best-practice button needs Site Knowledge result
context. Do not reopen Toolbox as an index operations surface.
