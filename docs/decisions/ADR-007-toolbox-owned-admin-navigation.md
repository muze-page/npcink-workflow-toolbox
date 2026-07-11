# ADR-007: Let Toolbox Own Optional Suite Navigation

## Status

Accepted

## Date

2026-07-11

## Context

Npcink WordPress plugins are separate products with separate authority:

- `npcink-governance-core` owns proposal, approval, preflight, and audit truth;
- `npcink-abilities-toolkit` owns reusable ability and static workflow definitions;
- `npcink-ai-client-adapter` owns external AI-client channel projection;
- `npcink-workflow-toolbox` owns the operator-facing product surface and fixed
  workflow buttons;
- `npcink-cloud-addon` owns Cloud settings and signed transport.

Historically, several of these plugins could create the shared top-level
`Npcink AI` WordPress admin menu and its installed-plugin Overview. This made a
standalone connector, such as Cloud Addon, look like a suite installer: merely
activating it could expose rows for Core, Adapter, Abilities, and Toolbox even
when those plugins were absent.

That behavior blurred two different concepts:

1. **Independent product availability.** Every plugin must remain installable
   and usable without Toolbox.
2. **Optional suite navigation.** A shared Overview is useful only when the
   operator-facing Toolbox product is installed to compose that experience.

The shared slug itself was not the problem. Multiple plugins may target the
same parent slug when it exists. The problem was allowing multiple independent
plugins to create the parent and thereby imply suite ownership.

## Decision

`npcink-workflow-toolbox` is the sole owner of the optional top-level
`Npcink AI` menu with slug `npcink-ai` and the installed-surface Overview.

This is navigation composition only. It does not transfer governance, ability,
channel, transport, runtime, or persistence authority to Toolbox.

Each other plugin follows a conditional registration rule:

- if the Toolbox-owned `npcink-ai` parent exists, register the plugin's own
  page beneath it;
- otherwise register the same page under the most appropriate native
  WordPress menu;
- never create a replacement suite parent or show an installed-surface
  Overview while Toolbox is absent.

The resulting behavior is:

| Plugin | Toolbox active | Toolbox absent |
| --- | --- | --- |
| Workflow Toolbox | Creates `Npcink AI`, Overview, and its own product submenu | Same; it is the navigation composer |
| Governance Core | `Npcink AI -> Core` | `Tools -> Core` |
| Abilities Toolkit | `Npcink AI -> Abilities` | `Tools -> Abilities` |
| AI Client Adapter | `Npcink AI -> Adapter` | `Settings -> Adapter` |
| Cloud Addon | `Npcink AI -> Cloud Addon` | `Settings -> Cloud Addon` |

Toolbox registers the navigation shell on `admin_menu` priority `5`, before
consumer plugins inspect the parent. Its product submenu registers at priority
`45`, after the independent plugin entries, so the visible order remains
stable:

1. Overview
2. Core
3. Adapter
4. Abilities, when its operator/test surface is enabled
5. Workflow Toolbox
6. Cloud Addon

Plugin-row Settings links must resolve to the page that WordPress actually
registered. They use the shared parent URL when Toolbox is active and the
native Tools or Settings fallback when it is not.

## Why This Boundary Fits The Platform

The top-level menu answers “where does the operator enter the composed Npcink
product experience?” That is a product-surface question, so Toolbox is the
natural owner.

It does not answer any of these questions:

- who may approve a write;
- who owns an ability or workflow definition;
- who connects an external AI client;
- who stores Cloud credentials or performs signed transport;
- who owns hosted runtime, billing, scheduling, or index lifecycle.

Those truths remain in their existing repositories. The design therefore
centralizes navigation without centralizing authority.

## Alternatives Considered

### Let Every Plugin Create The Shared Parent

Pros:

- every plugin can always attach to the same slug;
- activation order appears less important.

Cons:

- installing any single plugin can expose a misleading suite Overview;
- no repository is clearly accountable for Overview content and ordering;
- connector or governance plugins appear to own product composition;
- independent installation is visually confused with suite installation.

Rejected.

### Move The Shared Parent To Core

Pros:

- Core is a common dependency for governed write flows.

Cons:

- Core must remain a governance kernel, not a suite control plane;
- read-only, connector, or standalone product surfaces should not require Core
  merely to obtain navigation;
- it would make product composition look like governance authority.

Rejected.

### Add A Separate Suite Shell Plugin

Pros:

- navigation composition would have a dedicated package.

Cons:

- adds another plugin and release dependency for a small amount of UI;
- Toolbox already owns the operator-facing composed product surface;
- increases installation and support complexity without adding a new truth
  boundary.

Rejected for the current platform stage. Reconsider only if multiple distinct
operator products need to compose the same suite independently of Toolbox.

### Remove Shared Navigation Entirely

Pros:

- maximum plugin independence;
- no cross-plugin menu coordination.

Cons:

- operators lose a useful installed-surface Overview when using the suite;
- related surfaces become unnecessarily scattered across WordPress admin.

Rejected.

## Implementation History

The accepted change was delivered across the four affected repositories:

| Repository | Commit | Responsibility |
| --- | --- | --- |
| `npcink-workflow-toolbox` | `c9bc937` | Own the shared parent, Overview, ordering, and canonical links |
| `npcink-governance-core` | `f4bfae9` | Attach under Toolbox or fall back to Tools |
| `npcink-ai-client-adapter` | `b3995c6` | Attach under Toolbox or fall back to Settings |
| `npcink-cloud-addon` | `edd3981` | Attach under Toolbox or fall back to Settings |

The closeout branch is `codex/platform-admin-navigation-closeout`. The related
Draft pull requests are Toolbox `#87`, Core `#60`, Adapter `#32`, and Cloud
Addon `#37`.

During GitHub verification, Toolbox's platform convergence script initially
assumed every sibling repository existed beside the checkout. That is true in
the local platform workspace but false in a normal single-repository GitHub
Actions checkout. Commit `0b80a99` changed the gate to:

- continue enforcing all Toolbox-local contract assertions;
- validate sibling evidence when sibling checkouts are available;
- report unavailable sibling evidence as explicit skips instead of throwing a
  filesystem exception;
- allow `NPCINK_CONTRACT_FAMILY_ROOT` to point the gate at an alternate family
  workspace.

This preserves a useful local cross-repository gate without making ordinary
single-repository CI depend on an undeclared checkout layout.

## Verification Evidence

The navigation contract was verified in a real local WordPress installation:

- Cloud Addon alone exposed only its Settings entry and no `Npcink AI` parent;
- Core alone exposed only its Tools entry;
- Adapter with its required dependencies but without Toolbox exposed its
  Settings entry;
- Toolbox alone created the top-level `Npcink AI` entry;
- the full active set produced the expected Overview, Core, Adapter, Toolbox,
  and Cloud ordering.

After the smoke test, the local plugin activation state was restored.

Repository gates passed for Toolbox, Core, Adapter, and Cloud Addon. The
central `composer quality:matrix:run` gate also passed across the six-project
matrix: Abilities Toolkit, Core, Adapter, Toolbox, Cloud Addon, and hosted
Npcink Cloud.

For Toolbox's standalone-CI behavior, the convergence gate passed both with
the local sibling repositories and with a deliberately unavailable family
root. The latter produced explicit skips while retaining all local checks.

## Development Rules Going Forward

1. Treat menu composition as a product-surface concern, not authority.
2. Do not infer that sharing `npcink-ai` grants permission to create it.
3. Preserve every plugin's native WordPress fallback and plugin-row link.
4. Register the Toolbox parent before consumers; preserve deterministic submenu
   priorities when adding a new surface.
5. Test at least these activation shapes when navigation changes: plugin alone,
   Toolbox alone, plugin plus Toolbox, and the full suite.
6. Keep single-repository CI self-contained. Cross-repository evidence may be
   additional when sibling checkouts exist, but absence must not cause an
   unrelated filesystem failure.
7. Add a new suite submenu only when the plugin owns a real operator surface.
   An Overview link must not imply installation, readiness, entitlement, or
   authority that does not exist.
8. If Toolbox ever stops being the product-surface composer, supersede this ADR
   explicitly; do not silently let another plugin recreate the parent.

## Consequences

- Installing Cloud Addon, Core, Adapter, or Abilities alone no longer exposes a
  misleading suite Overview.
- All plugins remain independently reachable through native WordPress menus.
- Operators who install Toolbox receive one coherent Npcink navigation shell.
- Toolbox gains responsibility for parent timing, Overview content, and menu
  ordering, but no new platform truth or runtime ownership.
- Cross-repository navigation changes require both isolated-plugin and composed
  suite smoke tests.
- Future CI checks must distinguish unavailable optional sibling evidence from
  a failed contract in evidence that is actually present.
