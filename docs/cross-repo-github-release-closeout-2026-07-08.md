# Cross-Repo GitHub Release Closeout - 2026-07-08

Status: accepted.

Scope: the current `npcink-*`, Cloud, Cloud Addon, Workflow Toolbox, and
legacy/current Magick Toolbox project family.

This record closes the stage that started with reference-plugin learning and
contract reuse checks, then moved through GitHub-only migration, PR creation,
CI, merge, and post-merge matrix verification.

## Purpose

The stage solved three operational problems:

1. Multi-repo work no longer depends on manual status guessing. The central
   observation brief and quality matrix now show dirty worktrees, ahead/behind
   branches, default gates, and the next queue before new scope is added.
2. Local work is now reviewable and merged through GitHub. Completed contract
   reuse and GitHub-only changes were turned into PRs, passed CI, merged, and
   synced back to local main branches.
3. Gitee is no longer a current source-control target for this project family.
   Current remotes point to GitHub, and ordinary GitHub repository operations
   use shell `git` first.

## Merged PRs

| Repo | PR | Merge commit | Purpose |
| --- | --- | --- | --- |
| `npcink-abilities-toolkit` | [#85](https://github.com/muze-page/npcink-abilities-toolkit/pull/85) | `13490a3` | Record reusable ability contract readiness and implementation posture metadata. |
| `npcink-governance-core` | [#56](https://github.com/muze-page/npcink-governance-core/pull/56) | `c60eb0b` | Record Core contract reuse readiness and proposal/admin posture evidence. |
| `npcink-ai-client-adapter` | [#28](https://github.com/muze-page/npcink-ai-client-adapter/pull/28) | `2dbf148` | Record Adapter contract reuse readiness and checked posture consumption. |
| `npcink-workflow-toolbox` | [#71](https://github.com/muze-page/npcink-workflow-toolbox/pull/71) | `b000648` | Add the cross-repo observation brief and stage closeout records. |
| `npcink-cloud-addon` | [#29](https://github.com/muze-page/npcink-cloud-addon/pull/29) | `6b0ddfc` | Record Cloud Addon contract reuse readiness and GitHub-only repository management. |
| `npcink-ai-cloud` | [#119](https://github.com/muze-page/npcink-ai-cloud/pull/119) | `0304d64a` | Enforce GitHub-only release policy markers. |
| `npcink-ai-cloud` production | [#118](https://github.com/muze-page/npcink-ai-cloud/pull/118) | `74f4b85` | Promote the remote deploy stdin fix to `production`. |

## Mainline Snapshot

After merge and local sync:

| Repo | Branch | Commit | Remote |
| --- | --- | --- | --- |
| `npcink-abilities-toolkit` | `master...origin/master` | `13490a3` | `git@github.com:muze-page/npcink-abilities-toolkit.git` |
| `npcink-governance-core` | `master...origin/master` | `c60eb0b` | `git@github.com:muze-page/npcink-governance-core.git` |
| `npcink-ai-client-adapter` | `master...origin/master` | `2dbf148` | `https://github.com/muze-page/npcink-ai-client-adapter.git` |
| `npcink-workflow-toolbox` | `master...origin/master` | `b000648` | `https://github.com/muze-page/npcink-workflow-toolbox.git` |
| `npcink-cloud-addon` | `master...origin/master` | `6b0ddfc` | `https://github.com/muze-page/npcink-cloud-addon.git` |
| `npcink-ai-cloud` | `master...origin/master` | `0304d64a` | `git@github-magick-ai-cloud:muze-page/npcink-ai-cloud.git` |
| `wp-magick-toolbox` | `main...origin/main` | `b42f681` | `https://github.com/muze-page/wp-magick-toolbox.git` |

## Verification

Final gate:

```bash
composer quality:matrix:run
```

Observed result at `2026-07-08T06:21:39+00:00`:

| Repo | Gate | Result |
| --- | --- | --- |
| `npcink-abilities-toolkit` | `composer test:all` | `passed` |
| `npcink-governance-core` | `composer test:all` | `passed` |
| `npcink-ai-client-adapter` | `composer test:all` | `passed` |
| `npcink-workflow-toolbox` | `composer test:all` | `passed` |
| `npcink-cloud-addon` | `composer test:all` | `passed` |
| `npcink-ai-cloud` | `npm run check:fast` | `passed` |
| `wp-magick-toolbox` | `composer test` | `passed` |

Fast observation after merge:

```bash
composer quality:observe
```

Result: all seven repositories were clean, with no dirty, ahead, or behind
queue.

GitHub remote smoke:

```bash
composer git:remote-check
GIT_TERMINAL_PROMPT=0 git -c http.version=HTTP/1.1 ls-remote origin HEAD
```

Observed result: both remote probes hit transient GitHub HTTPS/network errors
from this machine (`alarm` timeout and `Empty reply from server`). This is a
network-path caution, not a repository-state failure: PR creation, merge, local
sync, and the final matrix all completed. For future publish work, retry with
plain `git` first and use `-c http.version=HTTP/1.1` when HTTP/2 framing or
empty-reply errors appear.

## Boundary Result

The merged stage did not change the ownership split:

- Toolkit owns reusable WordPress ability contracts.
- Core owns proposal, approval, preflight, and audit truth.
- Adapter remains the thin OpenClaw/channel layer.
- Workflow Toolbox remains the operator-facing suggestion and handoff surface.
- Cloud Addon remains the thin signed Cloud connector.
- Cloud remains hosted runtime/detail and GitHub-only release policy owner.
- `wp-magick-toolbox` is now under GitHub source control for this workflow.

No repo in this stage gained a second ability registry, workflow registry,
approval store, local runtime queue, provider billing/log owner, or final
WordPress write authority.

## Next Stage Recommendation

Do not add another broad multi-repo feature immediately.

The next productive stage should be one narrow product goal selected from the
clean baseline. Recommended first candidate:

```text
Reference-plugin evaluation checklist
```

Goal: turn the reference-plugin learning process into a reusable intake and
decision checklist, so future external plugins can be assessed quickly:

- what capability is worth borrowing;
- which repo owns the idea;
- whether it should become a doc, static contract, suggestion-only surface, or
  governed handoff;
- which boundaries would block implementation;
- which gate proves the idea is safe enough to keep.

This next stage should start with `composer quality:observe`, keep the existing
role split, and avoid adding runtime ownership while the checklist is still
being validated.
