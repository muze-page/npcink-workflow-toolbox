# Five-Plugin Hardening And Cloud-Offline Closeout - 2026-07-15

Status: completed local-development checkpoint.

Date: 2026-07-15.

## Purpose And Authority

This document records the five-plugin hardening stage that followed the
WordPress 7.0 product and boundary review. It covers:

- `npcink-abilities-toolkit`;
- `npcink-governance-core`;
- `npcink-ai-client-adapter`;
- `npcink-workflow-toolbox`;
- `npcink-cloud-addon`.

It is a point-in-time execution and lessons-learned record, not a new
architecture decision and not a replacement for active contracts. If this
closeout conflicts with current source, repository-local boundary documents,
ADRs, or executable gates, those live sources are authoritative.

`npcink-ai-cloud` was explicitly outside the implementation scope because it
was being refactored separately and was not available for reliable end-to-end
execution. The local no-credit acceptance described here must not be presented
as real Cloud or provider verification.

## Executive Conclusion

The five local WordPress plugins have reached a useful stopping point for this
stage:

- their ownership boundaries remain intact;
- the internal extractions and security hardening selected for this stage are
  complete;
- performance guards or observation baselines match the evidence currently
  available for each plugin;
- every plugin's default source gate passes;
- a real WordPress 7.0 site proves the five current worktrees can compose
  without `npcink-ai-cloud`;
- governed draft execution, replay rejection, cleanup, and the Cloud Addon
  transport seam are covered by one repeatable no-credit acceptance command.

The correct next action is not another broad or destructive plugin refactor.
Keep the five plugin contracts stable while Cloud is changing. Resume real
integration work only when Cloud API, runtime workers, credentials,
entitlement, and provider configuration are stable enough to produce honest
end-to-end evidence.

## How The Stage Was Chosen

The initial review used the supplied WordPress 7.0 and AI research as reference
material, then cross-checked its conclusions against repository source,
project boundary documents, executable gates, and official platform evidence.
The durable product conclusion was that generic title, summary, ALT, and model
wrapper features are increasingly baseline capabilities. The differentiated
value of this project family is the governed operation:

- one reusable ability or workflow contract;
- one explicit runtime and transport owner;
- reviewable artifacts and predictable failure behavior;
- Core-owned approval, preflight, and audit truth;
- replay protection, rollback or cleanup evidence, and no hidden write path.

This led to a staged implementation decision:

1. stop broad feature expansion;
2. inspect performance, security, and ownership risks while change is still
   inexpensive;
3. use the absence of users and compatibility obligations to remove internal
   debt early;
4. preserve cross-plugin contracts even while internal classes are split;
5. prove the local five-plugin composition without waiting for Cloud;
6. defer real Cloud and provider claims until the Cloud refactor stabilizes.

## Completed Repository Work

The commit lists below are the local hardening branches relative to their
point-in-time `origin/master` references. They are implementation evidence,
not proof that the branches were pushed, reviewed, or merged.

### Npcink Abilities Toolkit

Branch: `codex/refactor-toolkit-media-boundaries`.

Commits:

- `c0fc505` `refactor: extract media governance definitions`;
- `6ed9adc` `refactor: extract media alt caption methods`;
- `c5caf09` `security: bind media adoption to artifact integrity`.

Result:

- media governance definitions and ALT/caption methods are split by cohesive
  responsibility;
- media adoption is bound to exact artifact integrity evidence rather than a
  weak runtime reference;
- Toolkit remains the reusable ability and workflow-definition owner, not an
  approval, provider, or runtime owner.

Reusable lesson: class or file size is only a diagnostic signal. Split code
when a stable responsibility boundary exists, and require exact artifact
identity, digest, byte size, MIME, dimensions, and expiry evidence for a
governed media handoff.

### Npcink Governance Core

Branch: `codex/refactor-core-plan-handlers`.

Commits:

- `e1cddc9` `core: extract plan contract validator`;
- `9365062` `security: fail closed on ambiguous ability intake`.

Result:

- pure plan validation is separated from proposal lifecycle handling;
- ambiguous, conflicting, incomplete, or REST-invisible ability input remains
  diagnosable but cannot enter proposal, read, or preflight execution paths;
- Core remains governance truth only.

Reusable lesson: diagnostic visibility and execution eligibility are different
decisions. A malformed ability may remain visible for investigation while every
write-capable route fails closed.

### Npcink AI Client Adapter

Branch: `codex/refactor-adapter-validation-execution`.

Commits:

- `89b9ebc` `refactor: extract adapter input validation`;
- `32cb20c` `refactor: extract adapter action runner`;
- `9514276` `security: harden adapter trust boundaries`.

Result:

- external input validation and approved action execution are independent
  components;
- ability identity, governance source, channel, and signature evidence are
  derived or confirmed server-side instead of trusting client annotations;
- nonce and replay protection is kept on the verified, atomic path.

Reusable lesson: a thin adapter still needs strong trust boundaries. Replay
protection must verify the signature first and then claim the nonce atomically;
"check then write" is not sufficient.

### Npcink Workflow Toolbox

Branch: `codex/refactor-toolbox-publish-preflight`.

Commits:

- `9d47baa` `refactor: extract publish preflight service`;
- `84f99f2` `security: harden toolbox privacy and request boundaries`;
- `93b46e9` `test: add five-plugin no-credit acceptance`;
- `431ff80` `test: add repeatable Toolbox performance baseline`;
- `c8edbe0` `Fix five-plugin acceptance cron isolation`.

Result:

- publish-preflight artifact construction is separated from the REST
  controller;
- permission scopes, debug payload suppression, secret classification, raw
  response redaction, and external source URL restrictions have executable
  guards;
- one command verifies the real local five-plugin governance and transport
  composition without Cloud or provider credit;
- authenticated REST observations can record comparable median and P95
  measurements without prematurely enforcing unstable latency thresholds.

Reusable lesson: source contracts, local integration, and real Cloud E2E are
three different evidence levels. A deterministic transport mock is valuable
only when paired with a default-deny HTTP guard and honest no-Cloud wording.

### Npcink Cloud Addon

Branch: `codex/p2-wordpress-text-contract`.

Security, performance, and contract commits include:

- `6d7b497` `security: encrypt cloud signing credentials`;
- `4bb54b4` `security: centralize cloud outbound policy`;
- `a271ef2` `Adopt Cloud connector runtime contract`;
- `f2064a8` `test: harden addon delivery performance guards`;
- `75c3d14` `perf: defer site knowledge full-index delivery`;
- `26553dd` `fix: preserve authorization callback state`;
- `71fc5c1` `Artifactize WordPress alt-text sources`;
- `b2da647` `fix: harden addon runtime handoffs`.

The branch also extracts Site Knowledge admin projection and actions, runtime
run presentation, endpoint policy, and artifact URL normalization, while
removing duplicate or unused surfaces.

Result:

- signing credentials are encrypted at rest;
- all outbound Cloud calls share one policy boundary;
- plugin bootstrap and recurring schedule synchronization remain network-free;
- full-index delivery is bounded and deferred instead of monopolizing a normal
  request;
- runtime and artifact inputs are normalized and fail closed before transport;
- Addon remains signed transport and local status/detail projection, not a
  queue, provider control plane, approval store, or WordPress write authority.

Reusable lesson: keep security complexity where it protects a transport seam,
but keep product-control complexity in Cloud. Pure policy, normalization,
projection, and presentation components are safer extraction boundaries than
one large settings or runtime client class.

## Was Early Performance And Security Work Worth Doing?

Yes, with a narrow definition. It was worth establishing repeatable guards
before users arrived because the cost of removing unsafe or expensive bootstrap
behavior was low. It would not have been worth inventing one universal numeric
latency threshold for five plugins with different responsibilities.

The implemented model is layered:

| Component | Current performance evidence | Interpretation |
| --- | --- | --- |
| Toolkit | Cold-bootstrap and bounded composition budgets under its normal gates. | Protect the contract package from eager loading and uncontrolled scan growth. |
| Core | No new numeric latency target in this stage; proposal, validation, and fail-closed behavior remain source-tested. | Correct authorization and deterministic failure are the first budget for the governance kernel. |
| Adapter | No separate latency baseline in this stage; validation, action execution, nonce storage, and trust boundaries are behavior-tested. | Keep the channel thin and prevent security state from becoming an autoloaded or durable runtime store. |
| Toolbox | Authenticated REST baseline tool with warmups, ten measured samples, median, P95, identity-matched comparisons, and hard HTTP/JSON invariants. | Observe first. Timing enforcement waits for three comparable stable batches. |
| Cloud Addon | Zero-HTTP bootstrap, idempotent schedule synchronization, bounded delivery, and deferred full-index guards. | Prevent WordPress request-path amplification; hosted runtime latency remains Cloud-owned. |

The Toolbox comparison reports a candidate regression only when both median
increase exceeds 30 percent and the absolute increase exceeds 20 milliseconds.
It becomes a failing timing gate only after an explicit enforcement switch.
No three-batch authenticated reference or real Cloud latency baseline was
claimed as complete in this stage.

Cloud-backed performance probes remain opt-in because they can consume quota,
measure an unstable refactor target, and mix WordPress latency with provider or
worker behavior. They should resume only after Cloud availability is real.

## Local WordPress 7.0 Acceptance

### Fixture State

The final fixture is the exclusive local single-site installation at:

`/Users/muze/Local Sites/npcink-trial/app/public`

It runs WordPress 7.0 and keeps these current worktrees mounted and active:

- `npcink-abilities-toolkit`;
- `npcink-governance-core`;
- `npcink-ai-client-adapter`;
- `npcink-workflow-toolbox`;
- `npcink-cloud-addon`.

The existing WordPress Importer remains active. A broken legacy
`npcink-toolbox` mount and its stale `active_plugins` entry were removed after
the original state was recorded.

This site is locally verified for the five-plugin acceptance only. Cloud Addon
was `configured=false` and `verified=false`; therefore this is not a
Cloud-verified site.

### Acceptance Command And Evidence

The repeatable command is:

```bash
composer accept:local-five-plugin
```

Its preflight verifies that all five plugin slugs are active and that every
mounted directory resolves to the expected current worktree.

Lane 1 proves the governed local write path:

1. Toolbox builds one reviewed article plan.
2. Adapter submits it to Core.
3. Core creates, approves, and preflights the exact Toolkit `create-draft`
   ability.
4. Adapter executes exactly one WordPress draft write.
5. A replay is rejected with HTTP 409 and returns the original execution
   record.
6. The draft, Adapter execution/preflight state, Core proposal, and Core audit
   fixtures are removed.

Lane 2 proves only the Addon transport contract:

1. temporary credentials live in an authenticated in-memory envelope;
2. Toolbox calls the real Cloud Addon helper;
3. one exact loopback runtime URL is intercepted by an in-process deterministic
   response before a socket opens;
4. all other HTTP is rejected;
5. the stored Addon option remains byte-for-byte unchanged.

The final run passed both lanes with no real Cloud request, no provider credit,
no published post, no residual proposal/audit fixture, and no unexpected
outbound HTTP.

## Failures Preserved As Useful Evidence

### WordPress 6.9+ Shutdown Cron

The first real acceptance run failed because WordPress attempted a loopback
`wp-cron.php` request during process shutdown. This was not a Cloud call and
not a product workflow request. WordPress 6.9+ moved the normal `_wp_cron()`
check to `shutdown`, so a due event could contaminate an otherwise offline
WP-CLI process.

The correct fix was to define `DISABLE_WP_CRON=true` inside the acceptance
bootstrap process. This prevents only that WP-CLI process from spawning cron;
it does not modify `wp-config.php`, delete scheduled events, or weaken the HTTP
guard. Allowlisting `wp-cron.php` would have hidden an unrelated network path
and was rejected.

### WP-CLI Plugin Listing Is Not A Network-Free Preflight

A separate diagnostic `wp plugin list` attempted the WordPress.org plugin
update-check endpoint over HTTP and HTTPS. Both requests were blocked, but the
observation showed that a convenient status command is not necessarily an
offline command.

The acceptance therefore reads the `active_plugins` option in one guarded
`--skip-plugins --skip-themes` process and validates mount realpaths itself.

### Generic Repository Detection Can Be A False Negative

The generic WordPress triage and plugin detectors reported these repo-root
plugin checkouts as unknown or found zero plugins. The plugin headers,
repository-local `AGENTS.md`, Composer gates, and actual WordPress mounts were
the reliable evidence. A generic detector result should inform triage, not
override direct repository facts.

## Destructive Refactor Decision

The project condition was explicit: local development, no real users, and no
external compatibility burden. Under that condition, early destructive
refactoring was justified where it removed duplicated code, ambiguous intake,
weak trust boundaries, eager work, or obsolete internal surfaces.

The rule was not "rewrite everything":

- preserve one owner for each ability, workflow, approval, transport, and
  runtime truth;
- split internal responsibilities behind the existing cross-plugin contract;
- remove dead or duplicate surfaces rather than supporting two mechanisms;
- add or strengthen tests before moving to the next repository;
- run the real five-plugin composition after local refactors converge.

No-user compatibility freedom does not remove cross-repository contract
discipline. The five current plugins already consume each other's contracts;
an intentional public contract reset must still be coordinated and verified
across all affected consumers.

## Evidence Levels To Keep Separate

| Level | What it proves | What it does not prove |
| --- | --- | --- |
| Source and static contracts | Public ids, schemas, boundaries, forbidden behavior, and deterministic helpers. | WordPress activation or database behavior. |
| Repository default gates | Each checkout is internally consistent under its configured tests. | Five plugins compose in one real site. |
| Local WordPress five-plugin acceptance | Current worktrees activate together; governed draft, replay, cleanup, and mocked Addon transport work. | Real Cloud credentials, provider output, quota, worker execution, or production load. |
| Real Cloud/provider E2E | Signed connection, entitlement, runtime, provider, polling, result, and artifact behavior. | Production readiness without operator trials, monitoring, and release review. |

This distinction prevents a deterministic mock, a passing source gate, or a
locally verified WordPress site from being reported as Cloud readiness.

## Verification Snapshot

The final 2026-07-15 checkpoint recorded:

- all five repositories passed `composer test:all` in the focused gate matrix;
- Toolkit and Core passed their configured PHPStan analysis;
- Adapter had no separate PHPStan Composer command, so no nonexistent gate was
  claimed;
- Toolbox's focused bootstrap contract and full default gate passed;
- final `composer accept:local-five-plugin` passed on the normalized WordPress
  7.0 fixture;
- an independent review found no blocking correctness, security, architecture,
  or performance issue in the cron-isolation fix;
- all five Git worktrees were clean after the implementation commit.

At that point the local branches had no configured upstream and were ahead of
their local `origin/master` references by `3 / 2 / 3 / 5 / 16` commits in
Toolkit, Core, Adapter, Toolbox, and Addon order. This closeout documentation
commit adds one further Toolbox commit. These counts are publication debt, not
test failures, and local remote references are not a substitute for a fresh
fetch or a merged PR.

## Explicitly Deferred Work

The following claims remain unverified while `npcink-ai-cloud` is being
refactored:

- Cloud Addon Save and Verify;
- signed Cloud health and entitlement reads;
- real `/v1/runtime/execute` behavior;
- real provider-backed text, image, audio, or ALT generation;
- run polling, result reads, artifact download, and recovery;
- Site Knowledge full-index, rebuild, and delete completion;
- real Cloud latency or quota behavior;
- production load, real-user value, and China-facing generated-content
  labeling/release obligations.

Do not work around these blockers by moving provider keys, billing, quota,
runtime state, or fallback provider execution into a WordPress plugin.

## Reentry And Stop Rule

Stop broad five-plugin refactoring at this checkpoint.

Reenter real integration only when all of these are true:

1. Cloud API and runtime workers start reliably from a clean known revision.
2. Cloud Addon can save and verify a signed connection on the exclusive test
   site.
3. Entitlement and required provider configuration are available and quota use
   is intentional.
4. The real gate names and expected result contracts are stable.
5. The five-plugin no-credit acceptance still passes before Cloud is enabled.

Then run the smallest real Cloud sequence first: signed health, entitlement,
one bounded runtime execution, status/result read, and cleanup. Add provider or
credit-consuming checks only after that path is stable. Do not add new plugin
features merely to compensate for an unavailable Cloud implementation.

## Reusable Working Method

For future cross-repository work, use this order:

1. Read active owner and boundary documents before judging code shape.
2. Preserve the original worktree and local WordPress state.
3. Run source gates before editing and record missing gates honestly.
4. Refactor one repository and one responsibility at a time.
5. Add security and performance guards at the boundary being changed.
6. Keep network-dependent and credit-consuming checks explicit and opt-in.
7. Compose the real local plugins with a default-deny HTTP guard.
8. Preserve exact failure modes; distinguish product defects from WordPress,
   WP-CLI, orchestration, or environment noise.
9. Clean exact fixtures and prove replay/idempotency behavior.
10. Stop when the local contract is proven and the next dependency is external
    or unstable.

## Authoritative Follow-Up Reading

- [Platform Governance Index](platform/README.md)
- [Platform Boundary And Development Summary](platform-boundary-and-development-summary-2026-07-12.md)
- [Project History And Development Thinking](project-history-and-development-thinking-2026-07-12.md)
- [Cross-Repo Boundary Matrix](cross-repo-boundary-matrix.md)
- [Security And Performance Release Gate](security-performance-release-gate.md)
- [Cloud Addon Transport Release Gate](cloud-addon-transport-release-gate.md)
- [Development Workflow](development-workflow.md)
- [AI Development Quality Workflow](ai-development-quality-workflow.md)
- [ADR-008: Freeze Fixed-Button And Generic AI-Client Boundaries](decisions/ADR-008-freeze-fixed-button-and-generic-client-boundary.md)
