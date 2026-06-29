# GitHub Quality Guardrails Closeout - 2026-06-26

Status: closed observation baseline.

This note records the GitHub quality-guardrail work completed for the related
Npcink repositories after deciding that AI-assisted development needs durable
repository gates, not only chat instructions.

## Why This Work Was Done

The maintainer currently uses AI heavily for implementation and usually checks
the visible result rather than reviewing every line of code. That makes the
main risk different from a normal multi-person team: the risk is not lack of
speed, but silent boundary drift, unreviewed broad changes, missing tests, and
AI claiming a change is safe without evidence.

The chosen approach was intentionally lightweight:

- use GitHub pull requests as the reviewable evidence trail;
- make CI and branch protection block obvious unsafe changes;
- keep repository-specific boundaries in `AGENTS.md`, docs, tests, and PR
  body contracts;
- avoid heavyweight bureaucracy while the projects are still led by one
  maintainer.

## Repositories Covered

The quality baseline covers these repositories:

| Repository | Role |
| --- | --- |
| `npcink-abilities-toolkit` | Reusable WordPress Abilities API definitions, schemas, callbacks, and dry-run previews. |
| `npcink-governance-core` | Governance truth: proposals, approvals, preflight, audit, app keys, and policy boundaries. |
| `npcink-ai-client-adapter` | Thin OpenClaw/client channel adapter over Core and WordPress abilities. |
| `npcink-toolbox` | WordPress operator-facing fixed-button product surface and suggestion/handoff UX. |
| `npcink-cloud-addon` | Thin WordPress connector to Npcink Cloud runtime, entitlement, Site Knowledge bridge, and observability surfaces. |
| `npcink-ai-cloud` | Hosted commercial backend and runtime/detail surface. |

`magick-ai-toolbox` may still appear in the cross-repo quality matrix as a
legacy/current checkout when present, but GitHub-first publishing for the
current `npcink-*` repositories is the active default.

## Completed GitHub Baseline

The repositories were moved toward a GitHub-first quality workflow:

- public repository posture was accepted for the current pre-release phase;
- branch protection was enabled around the default branch;
- required PR checks were configured;
- PR body contracts require scope, boundary, verification, and risk sections;
- Dependabot security updates, secret scanning, and push protection were
  enabled where available;
- local cross-repo status and gate matrices were added through Toolbox;
- repository-specific `AGENTS.md` instructions were used as durable AI agent
  constraints.

This is not a promise that AI will always obey instructions. The practical
control is that future AI output must pass repository gates and leave a
reviewable PR trail.

## Completed PRs In This Stage

| Repository | PR | Result | Purpose |
| --- | --- | --- | --- |
| `npcink-ai-client-adapter` | `#23` | Merged | Added the existing Adapter CLI Node syntax check to GitHub CI. |
| `npcink-cloud-addon` | `#7` | Merged | Added the existing WordPress.org review guard to CI and moved a Connector page inline style into the existing admin stylesheet so the guard stays green. |
| `npcink-governance-core` | `#41` | Merged | Added a conservative PHPStan gate, WordPress stubs, a narrow Toolkit public-helper stub, Composer dependency installation in CI, and static contracts/docs to keep the gate durable. |

The final Core merge commit recorded by GitHub was:

```text
efc19be6e34a9da0bb233a59614aced8927d1382
```

## Local Git Transport And Branch Cleanup

During the final publication cleanup, GitHub HTTPS operations intermittently
timed out or failed with an HTTP/2 framing error. The failure was transport
level, not a repository or commit problem.

The accepted local default for this repository family is:

1. Use local Git CLI for fetch, branch, merge, commit, and push operations.
2. Prefer repository-local `http.version=HTTP/1.1` for the six current Npcink
   repositories to avoid the observed HTTP/2 transport failure mode.
3. Use GitHub pull requests for protected `master` updates. Do not bypass
   branch protection or direct-push protected branches.
4. Use `gh` for PR creation, required-check inspection, and PR merge when the
   branch is protected.
5. Do not use GitHub Git Data API for normal branch publication.

The local HTTP setting was applied only to the six active repositories:

```bash
git -C /Users/muze/gitee/npcink-abilities-toolkit config http.version HTTP/1.1
git -C /Users/muze/gitee/npcink-governance-core config http.version HTTP/1.1
git -C /Users/muze/gitee/npcink-ai-client-adapter config http.version HTTP/1.1
git -C /Users/muze/gitee/npcink-workflow-toolbox config http.version HTTP/1.1
git -C /Users/muze/gitee/npcink-ai-cloud config http.version HTTP/1.1
git -C /Users/muze/gitee/npcink-cloud-addon config http.version HTTP/1.1
```

No global Git HTTP setting was changed. Each value should resolve from the
repository's own `.git/config`.

The normal guarded publication sequence is:

```bash
GIT_TERMINAL_PROMPT=0 git fetch --prune origin
git status --short --branch
git push origin <local-branch>:<remote-branch>
gh pr create --base master --head <remote-branch>
gh pr checks <pr-number> --watch
gh pr merge <pr-number> --merge --delete-branch
GIT_TERMINAL_PROMPT=0 git fetch --prune origin
git merge --ff-only origin/master
```

After PRs are merged, local cleanup should leave each target repository on
`master...origin/master` with a clean worktree, no ahead or behind commits, no
extra local `codex/*` branches, and no extra worktrees. Remote Dependabot
branches may remain. In `npcink-ai-cloud`, the remote `production` branch is a
normal production branch and must be preserved.

## Current Gate Shape

The current guardrail shape is intentionally uneven by repository. It follows
each repository's actual risk profile instead of forcing one identical process
everywhere.

| Repository | Current guardrail emphasis |
| --- | --- |
| `npcink-abilities-toolkit` | Strong existing Composer/static/PHPStan-style contracts and PHP-version matrix. |
| `npcink-governance-core` | Governance-focused contracts plus PHPStan as a first-pass static analysis gate. |
| `npcink-ai-client-adapter` | PHP contracts, PR body contract, WordPress.org guard, and Adapter CLI syntax check. |
| `npcink-toolbox` | Local static contracts, smoke-oriented Composer gates, and cross-repo quality matrix ownership. |
| `npcink-cloud-addon` | PHP contracts, boundary grep, WordPress.org guard, PR body contract, and no-control-plane boundary checks. |
| `npcink-ai-cloud` | Hosted runtime fast checks and Cloud boundary/perimeter tests. |

## What This Should Prevent

The baseline should catch or slow down:

- AI changes that skip tests;
- PRs with no stated boundary or verification;
- accidental provider key exposure;
- direct WordPress writes that bypass Core/Abilities governance;
- Adapter or Toolbox drifting into Core approval truth;
- Cloud or Cloud Addon drifting into a second WordPress control plane;
- basic syntax/static mistakes in PHP or Node CLI code;
- WordPress.org review regressions that are already locally checkable.

## What This Does Not Solve

These gates are useful, but they are not a full human review replacement.

They do not guarantee:

- product quality or UX quality;
- that every AI-generated line is logically correct;
- that public repositories are free of all commercial or security exposure;
- that future agents will understand business intent without good issue or PR
  context;
- that advanced security issues will be caught without deeper review.

For `npcink-ai-cloud`, the current decision was to keep it public for the
pre-release phase and revisit privacy before formal commercial release. Because
it is the core commercial backend, that repository still deserves a separate
security and public-exposure review before launch.

## Complexity Decision

This stage deliberately stops before adding more process.

The current workflow is worth keeping because the cost is small:

1. create a branch and PR;
2. fill a short change envelope;
3. run local gates;
4. let branch protection and CI verify the change;
5. use the cross-repo matrix only for multi-repo work or milestone closeout.

Adding CodeQL or stronger static analysis everywhere immediately would likely
be premature. The better path is to observe whether the current gates catch
real AI mistakes without slowing daily development too much.

## Stop Decision

This phase is closed after the merge of:

- Adapter CLI CI check;
- Cloud Addon WordPress.org CI guard;
- Core PHPStan CI gate.

Do not add new GitHub quality rules by default for the next 1-2 weeks. Use the
current gates and watch the actual failure pattern.

## Observation Checklist

During the observation period, record:

- which checks fail most often;
- whether failures are useful or noisy;
- whether AI agents can fix failures without broad rewrites;
- whether CI time is acceptable;
- whether PR body contracts improve review clarity;
- whether branch protection blocks unsafe direct-to-master changes;
- whether public repository exposure still fits the business stage.

## Recommended Next Step After Observation

Only after the current gates prove stable, consider one narrow next step:

1. raise `npcink-governance-core` PHPStan strictness gradually; or
2. add a lightweight JS/MJS syntax gate to `npcink-toolbox`; or
3. add CodeQL as non-required observation-only checks before making it required.

Do not do all three at once.

## Standing Boundary

The quality workflow must not become a product architecture change.

- Core remains governance truth only.
- Toolkit remains reusable ability definitions and callbacks.
- Adapter remains a thin channel layer.
- Toolbox remains suggestion, review, and handoff UX.
- Cloud Addon remains a thin connector.
- Cloud remains hosted runtime/detail and commercial backend.

Quality gates protect those boundaries; they do not move ownership between
repositories.
