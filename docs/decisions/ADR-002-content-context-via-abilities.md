# ADR-002: Expose Content Context Through Abilities

## Status

Accepted

## Date

2026-06-02

## Context

Operators need a place to fill in SEO, AEO, and GEO guidance so OpenClaw,
Agent Gateway, Open API, and other third-party AI callers do not have to guess
site positioning, target audience, keywords, brand voice, or forbidden claims.

Toolbox already owns the operator-facing tool surface and suggestion workflows.
Core owns proposal records, approval, audit, and final WordPress write
governance. `npcink-abilities-toolkit` owns reusable first-party WordPress atomic
abilities. OpenClaw and Agent Gateway consume projected local truth and must
not become second control planes or second registry truth.

## Decision

Store a non-secret content-discoverability context in Toolbox and expose it
through a read-only Toolbox ability:

```text
npcink-toolbox/get-content-discoverability-context
```

Use a dedicated option:

```text
npcink_toolbox_content_context
```

The ability returns suggestion-only SEO, AEO, and GEO guidance for third-party
AI callers. It does not accept updates and does not authorize final WordPress
writes. Write-like outputs must continue through WordPress abilities and Core
proposal approval.

## Alternatives Considered

### Put The Context In Core

Pros:

- Core already owns governance and proposal approval.

Cons:

- This is an operator-facing product form, not proposal truth.
- It would pressure Core to become a content-planning UX.

Rejected for first version.

### Put The Context In `npcink-abilities-toolkit`

Pros:

- Abilities are already discoverable by AI callers.

Cons:

- The filled site guidance is product configuration, not a reusable first-party
  WordPress atomic ability definition.
- It would turn Abilities into a product settings surface.

Rejected. Toolbox can register the ability through the Abilities API.

### Let OpenClaw Own The Context

Pros:

- Directly near one intended consumer.

Cons:

- OpenClaw and Agent Gateway must consume projected local truth.
- This would create a second context/control truth outside WordPress.

Rejected.

## Consequences

- Operators can maintain one local WordPress-side content context.
- Third-party AI callers can read a stable ability instead of guessing site
  facts or scraping private settings.
- Provider keys stay in connector settings and are not exposed in the context.
- The public ability surface grows by one read-only ability and a stable scope:
  `cap.toolbox.context.read`.
- Future AI sessions must keep the context `suggestion_only` unless a separate
  governed update/write contract is accepted.
