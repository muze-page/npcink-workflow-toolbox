# Content Metadata Apply Plan Decision Envelope Closeout - 2026-06-21

## Status

Closed locally and pushed to `origin/master`.

Commit:

- `3224e73 Normalize metadata apply plan decision evidence`

## Context

The failing gate was:

```bash
composer smoke:metadata-delta
```

The failing assertion was:

```text
Content metadata apply plan carries the operation-classification decision envelope.
```

This failure happened after the receipt-loop UI smoke and failure-feedback polish
work. That round did not change the PHP apply-plan generation path, so the
failure pointed to a local cross-repo contract mismatch between Toolbox,
Toolkit, and Core rather than a regression in the receipt UI work.

## Root Cause

The Toolbox route:

```text
/wp-json/npcink-toolbox/v1/flows/content-metadata-apply-plan
```

delegates the apply-plan artifact to:

```text
npcink-abilities-toolkit/build-content-metadata-apply-plan
```

The local Toolkit apply plan already returned a Core-ready
`content_metadata_apply_plan` with:

- `authorization.classification=core_proposal_required`
- `authorization.requires_proposal=true`
- `authorization.requires_approval=true`
- a nested `authorization.decision_envelope`

However, that nested decision envelope contained plan-specific details but did
not carry the stable operation-classification fields expected by the current
Core and Toolbox smoke contract:

- `decision_version=operation-classification-v1`
- `classification=core_proposal_required`
- `reasons`
- `required_evidence`

Core's from-plan preview path copies `plan.authorization` into
`preview.content_metadata_apply.classification_evidence`, so the correct fix is
to normalize `authorization` before Toolbox returns the delegated plan. Toolbox
does not need to create a second preview contract.

## Boundary

This fix stays inside Toolbox's adapter surface:

- Toolbox remains an operator-facing planning and handoff surface.
- Toolkit remains the owner of the reusable content metadata apply-plan ability.
- Core remains the owner of proposal records, approval, preview evidence, and
  audit truth.
- The metadata apply path remains Core-proposal-required.
- No direct WordPress write path was introduced.
- No second ability registry, workflow registry, queue, approval store, or
  preview truth was introduced.

The repaired posture is still:

```text
Toolbox editor suggestion
-> reviewed metadata apply choices
-> Toolkit apply-plan artifact
-> Toolbox contract normalization
-> Core from-plan proposal review
-> approval and governed ability execution outside Toolbox
```

## Implementation

Changed file:

- `includes/Provider_Client.php`

Change:

- `Provider_Client::build_content_metadata_apply_plan()` now returns the
  delegated Toolkit artifact through
  `normalize_content_metadata_apply_plan_contract()`.
- The normalization keeps the Toolkit plan intact and fills the Core-facing
  decision evidence fields when absent:
  - `authorization.policy_version`
  - `authorization.decision_version`
  - `authorization.reasons`
  - `authorization.required_evidence`
  - `authorization.decision_envelope.decision_version`
  - `authorization.decision_envelope.classification`
  - `authorization.decision_envelope.reasons`
  - `authorization.decision_envelope.required_evidence`
  - `authorization.decision_envelope.final_write_path`
  - `authorization.decision_envelope.direct_wordpress_write=false`
- It also preserves the no-write flags:
  - `direct_wordpress_write=false`
  - `requires_approval=true`
  - `dry_run=true`
  - `commit_execution=false`

Changed file:

- `tests/run.php`

Change:

- Added a static contract assertion that the Provider client keeps the metadata
  apply-plan decision-envelope normalization path and `classification_evidence`
  contract.

## Verification

Commands run:

```bash
php -l includes/Provider_Client.php
php -l tests/run.php
php tests/run.php
composer smoke:metadata-delta
composer test:all
git diff --check
```

Results:

- PHP syntax passed.
- Static contract checks passed with `2039 passed`.
- `composer smoke:metadata-delta` passed.
- The original failing assertion passed.
- Core from-plan proposal creation passed.
- Core proposal preview preserved
  `preview.content_metadata_apply.classification_evidence.decision_envelope`.
- The sampled post was not mutated.
- `composer test:all` passed.
- `git diff --check` passed.

## Git Closeout

Final repository state after the fix:

- branch: `master`
- upstream: `origin/master`
- local branch state: clean and synced
- local branches: only `master`
- worktrees: only `/Users/muze/gitee/npcink-toolbox`
- stash: empty

Push notes:

- The first push attempts hit transient GitHub HTTPS network failures:
  - `Error in the HTTP2 framing layer`
  - `Failed to connect to github.com port 443 after 75001 ms`
- A later `git -c http.version=HTTP/1.1 push --porcelain origin master`
  confirmed:

```text
refs/heads/master:refs/heads/master [up to date]
```

## Follow-Up

No more work is needed for this failure point.

Only revisit this area if Core or Toolkit intentionally changes the
operation-classification decision-envelope contract. If Toolkit later emits the
full envelope itself, Toolbox can keep this normalization as a defensive
compatibility layer or remove it in a coordinated cross-repo contract cleanup.
