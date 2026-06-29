# AI Plugin Overlap Closeout - 2026-06-29

Status: accepted and merged through PR #33.

## Context

This stage started from a product-surface review against the local WordPress AI
plugin settings page at `ai-wp-admin`. That page already covers generic AI
administration and broad generation experiments: provider/model setup, request
logging, connector approval, generic Abilities Explorer behavior, title and
excerpt generation, content classification, ALT text generation, meta
description support, editorial notes, and comment moderation.

The risk was not that Toolbox had useless code. The risk was that Toolbox could
look like a second generic AI admin product if those abilities stayed visible as
default buttons or settings surfaces. That would weaken the Npcink boundary:
Toolbox should be the operator-facing workflow surface, not provider control
plane, request log owner, connector approval UI, or direct-write feature owner.

## Decision

Keep the useful capabilities, but remove or demote duplicate entry points.

Default Toolbox UI should focus on Npcink workflows:

- Publish Preflight;
- Internal Link Candidates;
- Image Candidates and governed image adoption planning;
- Article audio candidates and governed audio adoption planning;
- Site Profile;
- Full-site Insights;
- Site Knowledge setup, search, and bridge health;
- Cloud web search and evidence checks;
- Batch Image Optimization Review;
- Batch ALT Review Handoff;
- Core/Adapter handoff receipts and failure feedback.

Generic AI-plugin-style capabilities remain compatible only where they are still
useful to existing workflows:

- title suggestions;
- summary suggestions;
- category and tag suggestions;
- outline support;
- article checkup;
- selected-text polish;
- current-article ALT suggestions;
- discoverability support;
- comment-reply suggestions.

Those paths may remain as REST, rendering, or route-only compatibility, but they
must not be restored as default Toolbox buttons unless a later boundary decision
reclassifies them as Npcink workflow entries.

## Lessons Adopted From `ai-wp-admin`

Toolbox adopted engineering discipline, not product ownership:

- make capability metadata explicit;
- distinguish visible workflow entries from hidden compatibility entries;
- keep runtime owner, handoff path, overlap policy, and write posture visible;
- summarize Npcink-owned workflow readiness for operators;
- keep result copy honest about suggestion-only behavior and Core-governed
  handoff;
- preserve provider/model/source identity in results without becoming the
  provider control plane.

Toolbox explicitly did not adopt the generic provider picker, AI request log,
connector approval surface, generic Abilities Explorer, or direct-write AI
feature ownership.

## What Changed

PR #33 implemented the decision in this repository:

- the editor default flow list now shows only the focused Npcink review and
  handoff buttons: `publish_preflight`, `internal_links`,
  `image_candidates`, `article_narration`, and `article_audio_summary`;
- generic overlap intents remain supported outside the default visible button
  list for compatibility and result rendering;
- the admin Image Handling entry is framed as **Batch ALT Review Handoff**
  rather than direct ALT completion;
- `Ability_Surface_Metadata` records a read-only local projection of
  Toolbox-owned defaults and route-only compatibility entries;
- the Overview page renders **Npcink capability health** from that projection;
- docs, translations, and static contracts were updated to keep this boundary
  explicit.

`Ability_Surface_Metadata` is a projection, not a registry. It must not become
a second ability registry, workflow registry, approval store, provider picker,
request log, connector approval UI, queue, provider runtime, or write path.

## Admin Surface Follow-Up

The follow-up admin cleanup closed the remaining operator-facing duplication:

- Overview keeps one primary site-check action, one media-library image entry,
  one site-profile entry, and one **Open advanced tools** link.
- Overview no longer repeats secondary diagnostics or setup links in a folded
  advanced directory.
- Advanced is the single low-frequency directory and groups entries by operator
  job: Setup, Diagnostics, Review, and Planning/Handoff.
- **Connection Diagnostics** is the ordinary label for the old Cloud Checks
  surface, while the stable `toolbox_tab=cloud-checks` deep link remains
  compatible.
- Content Library Setup, Full-site Insights detail, and Morning Brief preview
  remain secondary/read-only entry points rather than default generic AI admin
  buttons.
- Media Library single-image and bulk image entries remain the productized
  image workflow entry points, so Toolbox does not need a duplicate backend
  one-image picker.

This follow-up keeps the same overlap rule: demote duplicate generic AI
administration surfaces, keep Npcink workflow buttons visible, and preserve
route or deep-link compatibility where existing callers rely on it.

## Verification And Publish Closeout

The work was published through:

- branch: `codex/ai-plugin-overlap-boundary`;
- PR: <https://github.com/muze-page/npcink-workflow-toolbox/pull/33>;
- merge commit: `f7fd8c798ea9329b1883ad3101bc4e2c0b8c7055`.

The PR was moved from Draft to Ready, the PR body contract was corrected to use
the required `Scope`, `Boundary`, `Verification`, and `Risk` sections, and the
PR was merged into `master`.

Verified gates included:

- `composer test:all`;
- `composer test:editor-progressive-js`;
- `composer smoke:editor-progressive-recommendations`;
- `composer smoke:editor-progressive-local-matrix`;
- `NODE_PATH=/Users/muze/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules composer smoke:core-handoff-receipt-ui`;
- WP-CLI checks for the status route, admin render, ability metadata defaults
  and hidden entries, and hidden overlap REST compatibility.

After merge, local `master` was synchronized with `origin/master`, the branch
was `0 behind / 0 ahead`, the worktree was clean, and `composer test:all`
passed again.

The admin surface follow-up was verified locally with:

- `composer test:all`;
- WP-CLI plugin status on `https://magick-ai.local`;
- authenticated WordPress admin render checks for Overview,
  `toolbox_tab=advanced`, and `toolbox_tab=cloud-checks`.

## Known Environment Notes

The current symlinked local WordPress site used during validation did not have
Core, Adapter, and Cloud active together, so Core/Adapter handoff portions of
some smoke tests stopped at missing downstream routes after Toolbox assertions
passed.

`https://npcink.local` browser smoke hit a Local Router 502 before reaching
WordPress during that session. `https://magick-ai.local` had the fuller
Core/Adapter/Cloud stack. During the admin surface follow-up, its Toolbox plugin
copy was synchronized to this worktree before current-code render checks were
used as proof.

## Future Rule

When a future feature overlaps with a generic AI admin product, classify it
before adding UI:

1. If it is generic provider/admin ownership, keep it outside Toolbox.
2. If it is useful to a Npcink workflow, keep the capability but make the
   visible entry Npcink-specific.
3. If existing callers depend on it, preserve route or rendering compatibility
   without promoting it to a default button.
4. If the outcome is write-like, prepare a Core/Adapter/Abilities handoff
   rather than writing from Toolbox.
5. If the feature needs queues, schedulers, provider control, request logs, or
   approval truth, write a boundary note instead of implementing it locally.
