# Operator Path Validation Closeout - 2026-07-02

## Context

This closeout records the July 2026 tightening pass after the Toolbox admin
surface was narrowed around fixed, review-only buttons for site operators. The
goal was to confirm that `npcink-workflow-toolbox` still behaves as the
operator-facing product surface for repeatable Npcink workflows without turning
into a generic AI console, second registry, workflow runtime, provider control
plane, or WordPress write executor.

The work followed the current boundary:

- Toolbox owns fixed operator UI, read-only Site Check, suggestion/candidate
  displays, and governed handoff preparation.
- Core owns proposal, approval, preflight, final write truth, and audit truth.
- Abilities owns reusable WordPress write/read callbacks.
- Cloud may provide hosted runtime/detail, image-source, and Site Knowledge
  processing, but not WordPress write authority.

## Completed Changes

The stage closed through small PRs instead of one broad redesign:

| PR | Commit | Result |
| --- | --- | --- |
| #40 | `f705dbd` | froze fixed-button boundaries and aligned the default UI around the intended product posture |
| #41 | `f050a52` | trimmed editor default support so generic AI-plugin-style entries remain compatibility paths rather than default buttons |
| #42 | `fe561bb` | aligned top-level admin tabs with the shared Npcink AI visual standard |
| #43 | `b373bb9` | used Gutenberg blue for Toolbox admin tab hover and active states |
| #44 | `6bb8435` | documented that `Ability_Surface_Metadata` is only the read-only Workflow readiness projection, not a second registry |

After #44, local `master` was synchronized with `origin/master` using normal
Git CLI commands. The preferred local workflow for this repository is command
line `git` for status, branch, fetch, merge/rebase, staging, committing, push,
pruning, and local sync. Use `gh` only for GitHub-specific PR/check/API tasks.

## Product Decisions Preserved

- **Overview** stays simple: it recommends `Check my site` as the primary
  operator action and folds workflow readiness behind **System status**.
- **Site Check** is the default site-maintenance entry. It produces a bounded,
  read-only `site_ops_insight_pack.v1` and may prepare optional Cloud detail,
  but it does not create Core proposals, queues, local run tables, schedules, or
  WordPress writes.
- **Site Profile** and **Image Handling** remain supporting surfaces, not
  generic tool directories.
- **Site Knowledge** remains a secondary content-library usage panel and Cloud
  Addon-owned lifecycle detail; Toolbox does not own indexing, rebuild/delete,
  stale detection, or vector collection lifecycle.
- **Editor Content Support** keeps Npcink review and handoff flows as defaults.
  Generic writing/checkup/taxonomy helper paths remain route-compatible support
  paths instead of new default buttons.
- `Ability_Surface_Metadata` remains a read-only projection for admin workflow
  readiness. It must not become an exhaustive button catalog, ability registry,
  workflow registry, provider picker, request log, approval store, or
  route-compatibility registry.

## Verification Run

Static and CI-style gates used during the stage:

```bash
php tests/run.php --filter="top-level tabs"
php tests/run.php --filter="Admin page separates"
php tests/run.php --filter="shared Npcink AI tab visual standard"
php tests/run.php --filter="Fixed button surface"
composer test:all
```

GitHub checks passed on the merged PRs:

- `PR body contract`
- `PHP contracts`

Local operator-path validation then ran against the local WordPress environment
at `https://magick-ai.local` with `npcink-workflow-toolbox` active:

```bash
NODE_PATH="/Users/muze/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules" \
WP_PATH="/Users/muze/Local Sites/magick-ai/app/public" \
WP_BASE_URL="https://magick-ai.local" \
composer smoke:site-ops-insights-browser

NODE_PATH="/Users/muze/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules" \
WP_PATH="/Users/muze/Local Sites/magick-ai/app/public" \
WP_BASE_URL="https://magick-ai.local" \
composer smoke:editor-progressive-browser
```

Observed results:

- Site Check browser smoke opened the local admin surface, generated the local
  read-only report, validated the decision queue, treatment paths, Cloud tab,
  advanced JSON panel, and captured `build/smoke/site-ops-insights.png`.
- The Site Check browser smoke confirmed no automatic Cloud detail, Core
  proposal, governance-core, approve/execute, or media-derivative run requests.
- Editor progressive browser smoke confirmed automatic prefetch uses
  `POST /wp-json/npcink-toolbox/v1/editor/content-support` with
  `progressive_recommendations`.
- Editor progressive browser smoke confirmed no Cloud, Adapter, Core proposal,
  writing-support, or proposal-handoff requests were sent.
- A failed first editor-browser attempt was environmental: `WP_PATH` pointed to
  `/Users/muze/Local Sites/npcink/app/public` while the served site root was
  `https://magick-ai.local`. Re-running with
  `/Users/muze/Local Sites/magick-ai/app/public` passed.
- Temporary smoke login helper files and failure artifacts were removed after
  validation.

## Current State

At closeout:

- `master` and `origin/master` were aligned at `6bb8435`.
- The working tree was clean.
- There were no open PRs.
- No product defect was found that required a follow-up fix.

## Follow-Up Rule

Do not add new Toolbox buttons, metadata entries, workflow runtime behavior, or
registry-like abstractions only to make the surface look more complete. The next
useful product work should start from real operator feedback or a failed smoke
path. If a new default button is proposed, update `docs/fixed-button-surface.md`,
the product boundary, and static contracts in the same PR.
