# Cloud Addon Transport Release Gate

Status: active release gate for Toolbox to Cloud Addon transport paths.

Use this gate before treating the current Cloud-backed image, audio, web-search,
or image-source slice as release-ready. It verifies the transport contract and
keeps quota/provider readiness separate from Toolbox code health.

## Scope

This gate covers four Toolbox paths:

- AI image candidates through `toolbox_image_generation`;
- article audio candidates through `toolbox_audio_generation`;
- Cloud-managed web search through `toolbox_web_search`;
- Cloud-managed image-source candidates through `toolbox_image_source`.

The expected posture is unchanged:

- Toolbox returns candidates, evidence, or planning artifacts only;
- Cloud Addon signs and dispatches verified Cloud runtime requests;
- Cloud owns provider execution, entitlement, quota, and provider readiness;
- Core/Abilities remain the final WordPress write path.

This gate must not add a local provider picker, quota dashboard, request log,
media import, featured-image write, Core proposal creation, queue, local run
table, or retry owner inside Toolbox.

## No-Credit Contract Gate

Run these on every release candidate because they do not consume real Cloud
provider credits:

```bash
composer test:all
composer smoke:ai-image-cloud-addon-transport
composer smoke:audio-cloud-addon-transport
```

Also run the matching dependency gates in the sibling repositories when they
are part of the release candidate:

```bash
cd /Users/muze/gitee/npcink-cloud-addon
composer run test:all

cd /Users/muze/gitee/npcink-ai-cloud
.venv/bin/python -m pytest tests/api/test_runtime_execute.py tests/api/test_web_search_runtime.py tests/api/test_image_source_runtime.py tests/api/test_entitlement_routes.py
.venv/bin/python -m ruff check .
```

The two Toolbox transport smokes intercept or stub the Cloud runtime response.
They must prove:

- the named Cloud Addon helper exists;
- `/v1/runtime/execute` receives the expected `channel` and `ability_name`;
- `storage_mode` remains `result_only`;
- Toolbox does not enable provider fallback control;
- output remains candidate-only or evidence-only;
- `direct_wordpress_write=false`.

## Credit-Consuming Provider Gate

Run these only when the release owner intentionally wants real provider
coverage and the Cloud entitlement has enough remaining credits:

```bash
composer smoke:web-search-cloud-addon-transport
composer smoke:image-source-cloud-addon-transport
```

These calls use the real Cloud Addon helper and real Cloud-managed provider
configuration. They are outside `composer test:all` because they depend on:

- a running local WordPress site;
- active Toolbox and Cloud Addon plugins;
- verified Cloud Addon credentials;
- a running Cloud API and worker path;
- Cloud provider configuration;
- enough remaining Cloud credits or quota.

Before running them, check Cloud Addon entitlement or Cloud portal usage. If the
site is `near_limit`, `quota_exhausted`, or has too few remaining credits for
the requested run, skip the provider gate and record the quota state in the
release note. Do not spend the last test credits just to re-prove a transport
shape already covered by the no-credit gate.

## Failure Classification

Use this table during triage:

| Symptom | Classification | Next action |
| --- | --- | --- |
| Missing Cloud Addon helper | Plugin wiring failure | Check Cloud Addon activation, version, and helper export. |
| `cloud_web_search_zhihu_access_secret_missing` | Cloud provider config missing | Fix Cloud/provider configuration outside Toolbox. |
| `cloud_image_source_provider_not_configured` | Cloud provider config missing | Fix Cloud/provider configuration outside Toolbox. |
| entitlement or credit exhaustion | Cloud entitlement/quota state | Replenish credits or record an intentional skip. |
| candidate artifact sets `direct_wordpress_write=true` | Boundary failure | Stop release and fix the contract. |
| provider result tries to import media, set featured image, mutate SEO, or publish | Boundary failure | Stop release and route the write through Core/Abilities. |
| raw provider credentials appear in output, logs, or docs | Secret exposure failure | Stop release and redact/fail closed before retrying. |

Quota or credit exhaustion is not a Toolbox plugin defect by itself. It becomes
a release blocker only when the release requires fresh real-provider evidence
and the release owner cannot replenish credits or choose an intentional skip.

## Release Record

Record each run with:

- repository commits;
- local WordPress site and active plugin versions;
- no-credit contract gate results;
- provider gate result or explicit quota/config skip reason;
- any Cloud run ids or trace ids that are safe to store;
- confirmation that final WordPress writes still require Core/Abilities.

Do not record Cloud secrets, signed headers, raw provider payloads, cookies,
nonces, or full request logs.
