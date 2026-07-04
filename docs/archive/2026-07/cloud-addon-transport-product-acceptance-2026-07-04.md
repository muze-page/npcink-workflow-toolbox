# Cloud Addon Transport Product Acceptance - 2026-07-04

Status: accepted for the current migration slice, with a credit-risk follow-up.

## Scope

This record closes the post-merge product acceptance pass for the Toolbox to
Cloud Addon transport migration covering:

- AI image candidate generation;
- article audio candidate generation;
- Cloud-managed web search;
- Cloud-managed image-source candidates.

The acceptance target is the cross-repo contract, not a new feature surface:
Toolbox remains the operator-facing fixed-button and suggestion surface, Cloud
Addon remains the verified WordPress-side Cloud connector and transport/detail
surface, and Npcink Cloud remains the hosted runtime/provider execution owner.

## Merged Baseline

The acceptance pass was run from merged `origin/master` snapshots:

| Repository | Commit | Source PR |
| --- | --- | --- |
| `npcink-ai-cloud` | `26b4f46` | PR #89, Cloud provider readiness checks |
| `npcink-cloud-addon` | `30f97cf` | PR #21, Cloud Addon Toolbox image transport |
| `npcink-workflow-toolbox` | `e39edfa` | PR #54, Toolbox image Cloud Addon migration |

## Local WordPress State

Local site: `https://magick-ai.local`.

The local WordPress smoke environment had these active plugins:

- `npcink-cloud-addon` 0.1.1;
- `npcink-workflow-toolbox` 0.1.1;
- `npcink-governance-core` 0.1.1;
- `npcink-abilities-toolkit` 0.5.2;
- `npcink-ai-client-adapter` 0.3.2.

Cloud Addon was configured and verified against the local Cloud service at
`http://127.0.0.1:8010`. The following Toolbox transport helpers were present:

- `npcink_cloud_addon_execute_toolbox_image_generation_runtime`;
- `npcink_cloud_addon_execute_toolbox_audio_generation_runtime`;
- `npcink_cloud_addon_execute_toolbox_web_search_runtime`;
- `npcink_cloud_addon_execute_toolbox_image_source_runtime`.

The Cloud connectivity probe returned:

- liveness: ok;
- signed entitlement verification: ok;
- package: Free;
- package tier: free;
- site status: active;
- Cloud role: `runtime_detail`;
- final write path: `core_proposal_required`;
- direct WordPress write: false.

The same probe also returned AI credit usage at 298 of 300 credits, with only 2
credits remaining and status `near_limit`. That is a release-risk signal for
additional real provider E2E runs, not a transport contract failure.

## Verification

Passed gates:

| Repository | Gate | Result |
| --- | --- | --- |
| `npcink-workflow-toolbox` | `composer test:all` | passed |
| `npcink-cloud-addon` | `composer run test:all` | passed |
| `npcink-ai-cloud` | `.venv/bin/python -m pytest tests/api/test_runtime_execute.py tests/api/test_web_search_runtime.py tests/api/test_image_source_runtime.py tests/api/test_entitlement_routes.py` | 115 passed, 1 Starlette deprecation warning |
| `npcink-ai-cloud` | `.venv/bin/python -m ruff check .` | passed |
| `npcink-workflow-toolbox` | `composer smoke:ai-image-cloud-addon-transport` | passed |
| `npcink-workflow-toolbox` | `composer smoke:audio-cloud-addon-transport` | passed |

The AI image and article audio smokes use local WordPress plus HTTP stubs. They
prove the Cloud Addon transport helper path, payload shape, `result_only`
storage mode, no provider fallback control, candidate normalization, and
`direct_wordpress_write=false` without consuming Cloud provider credits.

The real Cloud-managed web search and image-source smokes were previously run
successfully after the merge. They were not repeated in this pass because the
current local entitlement is already near the credit limit. Repeating them at
this point would primarily test account quota headroom, not the migrated
transport contract.

Browser UI verification was attempted with the in-app browser against
`https://magick-ai.local/wp-admin/admin.php?page=npcink-cloud-addon`, but the
browser runtime timed out and then failed DOM snapshot capture at `about:blank`.
This was treated as a browser tooling blocker. The acceptance pass therefore
used WP-CLI, REST dispatch smoke scripts, static contracts, and service tests.

## Boundary Result

Accepted boundary posture:

- Toolbox returns image, audio, web-search, and image-source candidate artifacts
  only.
- Toolbox does not own provider credentials, model routing, prompt/runtime
  management, billing, quota, request logs, or durable provider execution.
- Cloud Addon signs and dispatches verified Cloud runtime requests, but does
  not become a second ability registry, workflow registry, approval store, or
  WordPress write authority.
- Cloud remains runtime/detail/provider execution owner.
- Final WordPress writes continue to require Core proposal governance and
  WordPress ability execution.

No additional migration should expand this slice into media import, featured
image setting, SEO mutation, direct publishing, runtime queues, scheduler truth,
or local request-log ownership.

## Current Completion

The current transport migration is complete enough for a release candidate:

- AI image candidates: migrated through Cloud Addon transport and locally
  smoke-tested.
- Article audio candidates: migrated through Cloud Addon transport and locally
  smoke-tested.
- Web search: migrated through Cloud Addon transport, Cloud runtime test
  covered, and local helper/config state verified.
- Image-source candidates: migrated through Cloud Addon transport, Cloud
  runtime test covered, and local helper/config state verified.

The main open item is operational rather than architectural: replenish or
increase test Cloud credits before repeating real provider E2E for web search
and image-source on the local site.

## Next Phase Recommendation

The next phase should not start with another broad migration. It should first
turn the completed transport migration into a repeatable release gate:

1. Add a documented release checklist row for the four Cloud Addon transport
   paths, including which tests are no-credit contract gates and which are
   credit-consuming real provider gates.
2. Add or update a small status surface in Toolbox or docs that explains when
   Web Search and Image Source are blocked by Cloud credit/quota state, so
   operators do not confuse quota exhaustion with a broken plugin.
3. Run the real provider E2E only after credits are replenished, then record the
   run IDs and result artifacts without storing secrets or raw provider logs.

After that release gate is stable, the best next migration candidate is not more
provider execution. It is a bounded review-set surface that reuses the same
suggestion-only posture:

- media ALT/caption review set, if the existing validation plan still passes;
- taxonomy/tag review set, only as Core handoff preparation;
- internal-link review set, only as operator-reviewed candidates.

These are worth migrating only if they reduce repeated Toolbox-local planning
logic while preserving the same Core/Abilities final-write boundary.
