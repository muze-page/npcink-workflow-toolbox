# Scoped Permissions First Version

Status: implementation guide for host integrations.

Toolbox keeps `manage_options` as the default WordPress admin gate. Scoped
permissions are a host integration contract only: Core, an app-key host, or a
trusted site integration may use the permission filters to grant narrower
access to selected routes or abilities.

The filters are:

- `npcink_toolbox_rest_permission( $allowed, $request, $required_scope, $route )`
- `npcink_toolbox_ability_permission( $allowed, $ability_id, $required_scope )`

The first version must not create public routes, anonymous access, direct
WordPress writes, or a second approval store.

## Scope Matrix

| Scope | REST routes | Ability examples | Notes |
| --- | --- | --- | --- |
| `cap.toolbox.status.read` | `/status` | none | Readiness only; no provider secrets or execution. |
| `cap.toolbox.image_source` | `/image-candidates`, `/ai/image-generation` | `npcink-toolbox/search-image-source`, `npcink-toolbox/generate-image` | Candidate generation only; no media import or featured-image write. |
| `cap.toolbox.vector_search` | `/vector-search` | `npcink-toolbox/vector-search` | Compatibility pointer for Cloud-managed Site Knowledge. |
| `cap.toolbox.knowledge.read` | `/site-knowledge/status` | `npcink-toolbox/get-site-knowledge-status` | Read-only Cloud status projection. |
| `cap.toolbox.knowledge.search` | `/knowledge-search`, `/site-knowledge/search` | `npcink-toolbox/search-site-knowledge` | Semantic context candidates only. |
| `cap.toolbox.knowledge.sync` | `/site-knowledge/sync` | `npcink-toolbox/request-site-knowledge-sync` | Bounded public manifest submission; no indexing lifecycle ownership. |
| `cap.toolbox.web_search` | `/web-search/test`, `/web-search/diagnostics` | `npcink-toolbox/cloud-web-search` | Cloud-owned search execution; source candidates only. |
| `cap.toolbox.feedback.write` | `/agent-feedback` | none | Cloud eval metadata only; no approval or audit truth. |
| `cap.toolbox.feedback.read` | `/agent-feedback/summary` | none | Feedback summary display only. |
| `cap.toolbox.workflow_suggest` | `/ai/content-support`, `/ai/site-helpers`, `/flows/*`, `/editor/content-support`, `/media-derivative-handoff` | article, media, content context, and review-plan abilities | Suggestion or Core handoff artifacts only. |
| `cap.toolbox.local_admin_consent` | `/local-admin-consent/featured-image` | none | Narrow current-post existing-attachment featured-image proof only. |
| `cap.toolbox.nightly_inspection` | `/nightly-inspection/*` | none | Cloud detail bridge and local preview metadata only. |
| `cap.toolbox.admin` | fallback | fallback | Fail closed for unknown future routes until explicitly mapped. |

## Host Rules

1. Grant the smallest scope needed for the caller.
2. Treat `cap.toolbox.workflow_suggest` as proposal preparation, not write
   authorization.
3. Keep `cap.toolbox.local_admin_consent` restricted to present local
   administrators unless a later ADR narrows another write proof.
4. Do not use any Toolbox scope as Core approval, Adapter execution, media
   import, indexing lifecycle, quota, billing, or request-log authority.
5. When in doubt, leave `manage_options` as the effective gate.

## Verification

Run:

```bash
composer smoke:security-permission-debug
composer test:all
```

The smoke proves that route scopes and ability scopes reach host filters, that
unknown routes fall back to `cap.toolbox.admin`, and that raw debug payloads can
be force-disabled and redacted.
