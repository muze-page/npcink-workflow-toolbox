# AGENTS.md - Npcink Toolbox

## Session Startup Protocol

Every new AI development session should start with:

1. Run `git status --short --branch`.
2. Read `README.md`.
3. Read these docs before editing:
   - `docs/product-positioning.md`
   - `docs/boundary.md`
   - `docs/architecture.md`
   - `docs/roadmap.md`
   - `docs/development-workflow.md`
   - `docs/decisions/ADR-001-toolbox-as-product-surface.md`
4. Briefly report the current module, relevant boundary, and intended focused
   gate before editing.

## Product Boundary

Npcink Toolbox is the WordPress operator-facing AI tool surface.

Toolbox owns:

- Tavily external research tool UX;
- Unsplash image-source candidate tool UX;
- SiliconFlow or Jina query embedding calls for vector search;
- Qdrant vector search tool UX;
- fixed workflow buttons for repeatable operator actions;
- planning artifacts and handoff suggestions;
- lightweight provider action calls for current MVP.

Toolbox does not own:

- Core governance truth, approval records, or audit logs;
- final WordPress write authorization;
- reusable first-party WordPress ability definitions already owned by
  `npcink-abilities-toolkit`;
- workflow runtime, queues, schedulers, retries, or leases;
- MCP, Agent Gateway, Open API, or OpenClaw control-plane state;
- long-term provider billing, quota, key rotation, or request log ownership;
- content indexing jobs, re-index jobs, stale index detection, or vector
  collection lifecycle in the current stage;
- direct publish, direct media mutation, or direct SEO meta mutation without a
  governed WordPress ability handoff.

## Hard Blocks

Do not introduce:

- `confirm_token` or `write_confirmed` behavior;
- final WordPress writes from fixed-flow buttons;
- direct post publishing, media upload, featured-image setting, or SEO writes
  that bypass WordPress abilities and Core governance;
- a second ability registry, second workflow registry, or second approval store;
- provider key leakage into logs, proposals, REST responses, or docs;
- queue/runtime ownership inside this plugin for the current stage.
- treating Unsplash image-source search as AI image generation;
- treating query embedding as content indexing ownership;
- treating Qdrant vector query as full RAG, indexing, or collection lifecycle
  ownership.
- treating Jina Reader or Jina Reranker as active runtime features before a
  workflow-level contract exists.

If a feature needs any of the above, stop and write a boundary note instead of
implementing it inside Toolbox.

## Development Rules

- Check `git status --short --branch` before edits.
- Keep changes scoped to one module per session.
- Prefer server-rendered WordPress admin UI and vanilla JS unless a real build
  requirement appears.
- Keep REST routes capability-gated with `manage_options` until a scoped host
  auth model is intentionally designed.
- Treat provider outputs as suggestions, not committed WordPress changes.
- Preserve Unsplash attribution and `download_location` metadata.
- Keep vector search limited to synchronous text query embeddings, supplied
  vector JSON, or Qdrant query objects. Do not add indexing/re-indexing without
  a separate contract.
- Keep `cap.toolbox.*` as the stable first-version scope naming unless Core
  explicitly changes the contract.
- Update docs when public REST, ability ids, workflow shape, lifecycle, or
  product boundary changes.
- Add or update `tests/run.php` static contracts for public behavior.
- Stage only files changed for the current task. Do not use `git add -A`.
- Before staging, inspect `git status --short` and `git diff --stat`. If
  unrelated edits already exist, name them and leave them unstaged.
- When one file contains unrelated hunks, stage with `git add -p` or
  `git apply --cached` so the commit contains only the intended scope.
- Before committing, run `git diff --cached --stat` and
  `git diff --cached --name-only`; after committing, check
  `git show --name-status --stat HEAD`. If unexpected files entered the commit,
  reset that commit with `git reset --mixed HEAD~1` and recommit the correct
  scope while preserving the working tree.

## Verification Gates

Default gate:

```bash
composer test:all
```

Composer metadata:

```bash
composer validate --no-check-publish
```

WordPress activation smoke, when a local site and WP-CLI are available:

```bash
wp --path="/path/to/wordpress" plugin activate npcink-toolbox
```

Before finishing a code session, run the narrowest useful gate and report
exactly what passed or failed.

## Session Closeout

Before final response:

- run the relevant verification gate;
- commit only if explicitly requested or if the session is clearly a complete
  local milestone and the user expects commits;
- report whether the branch is ahead/behind its upstream and whether any
  worktree files remain modified or untracked;
- if the branch is ahead after a completed milestone, either push/open the
  expected PR or explicitly state why the commits are intentionally local-only;
- report changed files and verification results.
