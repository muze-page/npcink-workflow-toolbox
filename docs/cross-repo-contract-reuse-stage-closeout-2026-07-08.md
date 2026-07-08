# Cross-Repo Contract Reuse Stage Closeout - 2026-07-08

Status: active stage closeout

This closeout records the current answer to the original question: whether the
current projects already have similar plugin patterns, contracts, or reusable
capabilities to learn from before building more code.

## Decision

Stop feature expansion for this stage.

The five local/plugin-side projects now have enough contract reuse evidence to
continue without inventing a second registry, runtime, approval store, queue, or
write executor:

| Project | Role | Current readiness commit |
| --- | --- | --- |
| `npcink-governance-core` | `proposal_handoff` | `c9ed904 Record Core contract reuse readiness` |
| `npcink-abilities-toolkit` | `ability_contracts` | `2330f0a Record ability contract reuse readiness` |
| `npcink-ai-client-adapter` | `execution_profiles` | `256fcad Record Adapter contract reuse readiness` |
| `npcink-workflow-toolbox` | `product_surface` | `3a48ea2 Record Toolbox contract reuse readiness` |
| `npcink-cloud-addon` | `signed_transport` | `ea2fa21 Record Cloud Addon contract reuse readiness` |

The runtime/detail side already has a Cloud source commit:

| Project | Role | Current source commit |
| --- | --- | --- |
| `npcink-ai-cloud` | `runtime_detail` | `231a8338 Expose Cloud runtime contract reuse` |

## What We Learned From Existing Plugins

The useful lesson is not to copy mature plugins wholesale. The useful lesson is
to borrow their proven shapes while keeping Npcink ownership boundaries intact.

- Cloud Addon should borrow connector setup, verification, status, and
  troubleshooting patterns from Jetpack, Site Kit by Google, WP Mail SMTP,
  Health Check & Troubleshooting, and WordPress Application Passwords.
- Toolbox should borrow fixed-button, checklist, readiness-row, and operator
  review patterns from mature WordPress admin plugins, but keep buttons
  suggestion-only or Core-handoff only.
- Core should learn from approval/review/audit products, but stay the
  governance kernel rather than a product workflow console.
- Abilities Toolkit should learn from WordPress Abilities API and reusable
  callback packaging, but not absorb provider runtime, Cloud indexing, UI
  state, approval, audit, preflight, or final WordPress writes.
- Adapter should learn from channel adapters and recipe runners, but keep
  execution profiles as bounded handoff evidence rather than a second
  governance truth.

This is the practical "stand on giants' shoulders" rule for the next stage:
reuse connector/status/review patterns and existing Npcink contracts first;
write new product code only when a gap is proven against those contracts.

## Verification

Status matrix:

```bash
composer quality:matrix
```

Result on 2026-07-08:

- all configured repos were present;
- the five local/plugin-side readiness repos had no dirty worktree files;
- `npcink-ai-cloud` had seven dirty frontend files, so it should not be treated
  as a clean release state without a separate Cloud pass;
- gates were not run by this status-only command.

Gate matrix:

```bash
composer quality:matrix:run
```

Result on 2026-07-08:

| Repo | Gate | Result |
| --- | --- | --- |
| `npcink-abilities-toolkit` | `composer test:all` | passed |
| `npcink-governance-core` | `composer test:all` | passed |
| `npcink-ai-client-adapter` | `composer test:all` | passed |
| `npcink-workflow-toolbox` | `composer test:all` | passed |
| `npcink-cloud-addon` | `composer test:all` | passed |
| `npcink-ai-cloud` | `npm run check:fast` | passed |
| `magick-ai-toolbox` | `composer test` | passed |

The gate result proves the current workspace can pass the configured contract
checks. It does not mean every local commit has been published, merged, or
reviewed.

## Current Git State To Keep Visible

At closeout time:

- `npcink-governance-core` was ahead of its upstream by 5 commits.
- `npcink-ai-client-adapter` was ahead of its upstream by 5 commits.
- `npcink-workflow-toolbox` was ahead of its upstream by 1 commit.
- `npcink-cloud-addon` was ahead of its upstream by 1 commit.
- `npcink-ai-cloud` was not ahead, but had seven dirty frontend files:
  - `frontend/src/app/portal/login/page.tsx`
  - `frontend/src/app/portal/register/page.tsx`
  - `frontend/src/hooks/usePortalSiteSelection.ts`
  - `frontend/tests/e2e/portal-login.spec.ts`
  - `frontend/tests/unit/portal-login-remember-me-contract.mjs`
  - `frontend/tests/unit/portal-registration-ui-contract.mjs`
  - `frontend/tests/unit/portal-cookie-route-refresh-contract.mjs`

Do not call this phase fully published until the ahead commits are pushed or
intentionally kept local, and the Cloud dirty files are separately reviewed.

## Fifth-Project Closeout

For the originally planned local/plugin-side optimization pass, this stage is
closed.

The five projects now have a clear role split:

```text
Toolkit ability contracts
-> Toolbox product surface
-> Adapter execution profiles
-> Core proposal handoff and audit
-> Cloud Addon signed transport
-> Cloud runtime/detail
```

Future work should keep the expansion flags false:

- `adds_registry=false`
- `adds_scheduler_truth=false`
- `adds_approval_store=false`
- `adds_queue=false`
- `adds_write_executor=false`

## Sixth-Project Recommendation

Do one separate `npcink-ai-cloud` observation pass next, but treat it as a
Cloud runtime/detail audit, not a continuation of local plugin feature
development.

Goal:

- verify Cloud owns `runtime_detail` and hosted execution;
- confirm current dirty frontend work does not turn Cloud into a second local
  product control plane;
- keep Cloud from becoming a second ability registry, workflow registry,
  prompt/router control plane, MCP/OpenClaw truth source, or WordPress write
  authority;
- preserve `suggestion_only` and read/write separation for runtime results.

Stop condition:

- if the Cloud pass confirms the current runtime/detail boundaries and the
  dirty frontend changes are either committed or explicitly left out of scope,
  stop this contract-reuse phase and move to publishing/PR cleanup rather than
  new feature development.

## Next Work Rule

The next stage should be one of these, in order:

1. publish or PR the local readiness commits that are intentionally complete;
2. run the separate `npcink-ai-cloud` runtime/detail observation pass;
3. only after those are clear, choose a narrow product improvement based on
   real operator usage, not another broad architecture expansion.
