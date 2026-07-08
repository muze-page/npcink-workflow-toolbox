# AI Development Quality Workflow

Status: active workflow guardrail.

This workflow is for AI-assisted changes across the related Npcink and Magick
AI repositories:

- `npcink-abilities-toolkit`
- `npcink-governance-core`
- `npcink-ai-client-adapter`
- `npcink-toolbox`
- `npcink-cloud-addon`
- `npcink-ai-cloud`
- `magick-ai-toolbox` when that checkout is present

It adds an operational layer on top of each repository's own `AGENTS.md`,
boundary documents, and release gates. It does not replace repository-specific
startup instructions.

## Purpose

AI agents are useful for implementation speed, but they also increase three
risks:

- accidental boundary expansion;
- unrelated file changes, broad rewrites, or hidden rollback;
- claiming quality or performance without running the relevant gate.

The control pattern is:

```text
change envelope -> narrow implementation -> local repo gate -> cross-repo
quality matrix -> scoped staging/commit review
```

## Cross-Repo Quality Matrix

Use the matrix before multi-repo work, after multi-repo work, and before any
milestone closeout that depends on more than one repository.

Fast status-only matrix:

```bash
composer quality:matrix
```

Fast next-action brief:

```bash
composer quality:observe
```

Run the configured default gates:

```bash
composer quality:matrix:run
```

Gate-backed next-action brief:

```bash
composer quality:observe:run
```

Write a report artifact:

```bash
php scripts/cross-repo-quality-matrix.php --run-gates \
  --output=var/reports/cross-repo-quality-matrix.md
```

The matrix script reports branch state, dirty file counts, ahead/behind counts,
configured gate commands, gate results, and bounded gate output tails. The
observation brief turns that same read-only JSON into a decision queue for
failed gates, dirty worktrees, behind branches, ahead branches, and clean repos.
They are read-only except for the optional report file; they must not fetch,
stage, reset, or mutate WordPress.

If a repository is missing or not a Git checkout, treat the matrix as
incomplete. Do not silently substitute a different repository path without
stating it in the closeout.

## Change Envelope

Before editing, write a short change envelope in the task thread or PR body.
For long-running work, keep it near the top of the thread so later agents can
reuse it.

Required fields:

- Target repositories:
- Focused module:
- Intended change:
- Explicit non-goals:
- Boundary owner:
- Public contracts touched:
- Files expected to change:
- Files or areas that must not change:
- Required gates:
- Cross-repo matrix requirement:
- Rollback plan:

Minimum example:

```markdown
Target repositories: npcink-toolbox only
Focused module: development workflow and quality gate tooling
Intended change: add a cross-repo quality matrix and AI change envelope docs
Explicit non-goals: no product runtime changes, no REST route changes, no write
path changes
Boundary owner: Toolbox documentation and dev scripts only
Public contracts touched: composer scripts and development workflow docs
Files expected to change: composer.json, scripts/*, docs/*, tests/run.php
Files or areas that must not change: includes/*, assets/*, runtime modules
Required gates: php -l scripts/cross-repo-quality-matrix.php, composer
quality:matrix, composer test:all
Cross-repo matrix requirement: status matrix required; gate matrix before
multi-repo release
Rollback plan: revert this commit or remove the new composer scripts and docs
without touching product runtime files
```

## Anti-Rollback Discipline

Agents must keep rollback and cleanup explicit:

- Do not run `git reset --hard`, `git checkout -- .`, or equivalent destructive
  cleanup unless the user explicitly asks for that exact action.
- Use command-line `git` for ordinary Git operations, including status, diff,
  branch, fetch, merge/rebase, staging, commit, push, pruning, and local sync.
  Reserve `gh` for GitHub-specific PR metadata, check inspection, or PR
  operations that plain `git` cannot perform.
- Do not use `git add -A` in mixed worktrees.
- Before edits, capture `git status --short --branch`.
- Before staging, inspect `git status --short --branch` and `git diff --stat`.
- If unrelated files are present, name them and leave them unstaged.
- If a file mixes task hunks and unrelated hunks, stage only the intended hunk.
- Before commit, run `git diff --cached --stat` and
  `git diff --cached --name-only`.
- After commit, run `git show --name-status --stat HEAD`.
- If unexpected files entered the commit, use `git reset --mixed HEAD~1` and
  recommit only the intended scope. This preserves the working tree.

## Gate Selection

Use the narrowest gate that can prove the change, then run the default gate
before closeout when the change touches public contracts, docs enforced by
static tests, REST routes, ability ids, write posture, security, or performance.

Default local gate for Toolbox:

```bash
composer test:all
```

Cross-repo status gate:

```bash
composer quality:matrix
```

Cross-repo release or multi-repo gate:

```bash
composer quality:matrix:run
```

Performance release gate remains separate:

```bash
composer perf:baseline
```

Run performance only when authenticated local or staging REST credentials are
available and the task changes latency-sensitive runtime behavior.

## Closeout

Every AI development closeout should state:

- changed files;
- gate commands and pass/fail results;
- branch ahead/behind state;
- remaining modified or untracked files;
- whether the cross-repo matrix was status-only or gate-running;
- why any expected gate was skipped.
