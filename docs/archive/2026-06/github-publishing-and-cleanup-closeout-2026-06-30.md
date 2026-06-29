# GitHub Publishing And Cleanup Closeout - 2026-06-30

Status: historical closeout record.

## Scope

This closeout records the repository cleanup, GitHub publication, PR merge, and
local Git history reconciliation work completed after the admin advanced
closeout. It is evidence for future sessions; current workflow instructions
remain in [Development Workflow](../../development-workflow.md) and
[GitHub Publishing Runbook](../../github-publishing-runbook.md).

## Boundary

The work stayed inside documentation, release packaging, editor copy, and local
Git hygiene. It did not add Toolbox runtime ownership, WordPress write
authority, proposal approval state, provider key handling, or a second
workflow/ability registry.

## What Changed

PR #35, [Clean up docs and publishing runbook](https://github.com/muze-page/npcink-workflow-toolbox/pull/35),
merged at `55798018f6917f6bf23ad64f0dd566688e1ec233`.

It included:

- documented the admin advanced closeout from PR #34;
- archived historical June 2026 closeout and trial documents under
  `docs/archive/2026-06/`;
- moved WordPress.org source assets from `sj/` to `wporg-assets/source/`;
- updated packaging/docs so source assets stay outside release zips;
- added [GitHub Publishing Runbook](../../github-publishing-runbook.md).

PR #36, [Rename paragraph check copy to review](https://github.com/muze-page/npcink-workflow-toolbox/pull/36),
merged at `1fba89d1d192415aed063efec3342889db1ffa5c`.

It included:

- renamed selected-paragraph UI copy from "paragraph check" to
  "paragraph review";
- aligned REST missing-selection copy, active docs, zh_CN translation JSON,
  PO, MO, and static contract wording;
- preserved the existing `polish_notes` path without adding routes or changing
  write posture.

## Publishing Notes

Normal Git publication initially failed:

- Git HTTPS to `github.com` timed out;
- SSH authenticated with a deploy key for another repository and could not
  write to `muze-page/npcink-workflow-toolbox`.

The two PR branches were therefore created through the GitHub REST Git API as
an emergency fallback. Remote commit SHAs differ from the original local SHAs,
but the final tree content was verified:

- #35 remote tree matched local `HEAD` for the four cleanup commits;
- #36 branch was rebuilt on the updated remote `master`, and its tree matched
  local `72d699d`.

After PR merge, Git HTTPS recovered. `git fetch origin --prune` succeeded and
local `master` was aligned to `origin/master`.

## Local History Reconciliation

Before alignment, local `master` contained the original five local commits:

- `f88cd47` - Document admin advanced closeout;
- `d60fda3` - Organize documentation archive;
- `8f84915` - Clarify WordPress.org asset sources;
- `61dac5e` - Document GitHub publishing runbook;
- `72d699d` - Rename paragraph check copy to review.

Remote `master` contained equivalent API-created commits and PR merge commits.
Before resetting local `master`, a temporary backup branch was created:

```bash
git branch codex/local-master-before-github-sync-20260630 master
git reset --hard origin/master
```

The backup branch was later checked and removed. Verification showed:

- backup branch tree equaled `origin/master` tree:
  `254a52025b2a6adeef0229bcf27993efc2ec427a`;
- stable patch-id matches:
  - `f88cd47` matched `e6fe88f`;
  - `d60fda3` matched `2867986`;
  - `8f84915` matched `81bb54d`;
  - `61dac5e` matched `668a654`;
  - `72d699d` matched `a25a029`.

Then the temporary backup branch was deleted:

```bash
git branch -D codex/local-master-before-github-sync-20260630
```

## Verification

Local gates run during the sequence:

- `git diff --check`;
- `composer test:all`;
- `composer validate --no-check-publish`;
- `composer package:release`;
- release zip checked to exclude `wporg-assets`, `source/`, and `sj/`.

GitHub status after merge:

- PR #35: merged, `Toolbox CI` success;
- PR #36: merged, `Toolbox CI` success.

Final local state after cleanup:

```bash
git status --short --branch
# ## master...origin/master

git rev-list --left-right --count origin/master...HEAD
# 0 0
```

## Follow-Up

Use normal Git CLI for future publication now that HTTPS has recovered:

```bash
gh auth status
gh auth setup-git
composer git:remote-check
git fetch origin --prune
```

If Git HTTPS regresses, use the documented diagnostics in
[GitHub Publishing Runbook](../../github-publishing-runbook.md) before falling
back to the GitHub REST Git API again.
