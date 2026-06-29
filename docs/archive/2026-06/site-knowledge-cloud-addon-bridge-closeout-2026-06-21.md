# Site Knowledge Cloud Addon Bridge Closeout - 2026-06-21

## Status

Closed and pushed.

This closeout records the decision to retire the Toolbox legacy Site Knowledge
auto-sync fallback and move automatic public content-change delivery to the
Cloud Addon bridge.

## Decision

Toolbox must not own automatic Site Knowledge public-change delivery.

The accepted boundary is:

- Cloud Addon owns the Site Knowledge change bridge after Cloud settings are
  installed and verified.
- Toolbox may show Cloud Addon bridge health, clear retired local state, and
  keep explicit manual Site Knowledge sync/search surfaces.
- Toolbox must not keep a standalone fallback queue, public post/comment hooks,
  local retry loop, reconciliation loop, scheduler truth, workflow truth, or
  automatic refresh-hint delivery.
- Cloud and Cloud Addon remain runtime/detail/transport surfaces only. They must
  not become a second ability registry, second workflow registry, second
  WordPress control plane, or WordPress write owner.

Manual Site Knowledge sync remains available from Toolbox. Automatic public
content-change delivery belongs to Cloud Addon.

## What Changed

### Toolbox

Commit: `2c64ac5 Retire Site Knowledge legacy auto sync`

Changed `includes/Site_Knowledge_Auto_Sync.php` from a local fallback worker
into a compatibility status projection:

- no public `post` / `page` hooks;
- no comment hooks;
- no local queue processing;
- no local retry or reconciliation loop;
- no background `request_site_knowledge_sync` calls;
- clears retired legacy cron hooks and queue option;
- projects `npcink_cloud_addon_site_knowledge_change_bridge_health()` when
  Cloud Addon is present;
- reports `cloud_addon_required` when the bridge is absent.

Admin Site Knowledge copy now says `Change bridge`, `Bridge owner`, `Bridge
state`, and `Buffered changes` instead of `Auto-sync`, `Queue meaning`, and
`Queued changes`.

Toolbox docs were updated in:

- `README.md`
- `docs/product-positioning.md`
- `docs/boundary.md`
- `docs/architecture.md`
- `docs/admin-surface-consolidation-summary.md`
- `docs/batch-automation-governance-plan.md`
- `docs/development-workflow.md`

Static tests now assert that the legacy fallback has exited Toolbox and that the
remaining surface is Cloud Addon bridge health plus cleanup.

### Cloud Addon

Commit: `a819bcc Gate Site Knowledge bridge on verified Cloud`

Changed the Site Knowledge bridge to require verified Cloud settings before it
buffers and delivers public content-change hints:

- `is_enabled()` now uses `Npcink_Cloud_Addon_Settings::is_verified()`;
- unverified settings report `status=unverified`;
- health includes stable fields for Toolbox:
  - `status`
  - `last_delivery_at`
  - `last_success_at`
  - `last_error_code`
  - `legacy_toolbox_fallback=false`
- failed delivery attempts record explicit error codes;
- docs now say the bridge starts after verified Cloud settings, not merely
  configured settings.

Behavior and static contracts cover the verified gate and stable health fields.

## Verification

Toolbox:

```bash
composer test:all
composer smoke:site-knowledge-cloud-addon-bridge
git diff --check
```

Cloud Addon:

```bash
composer test:all
git diff --check
```

The local bridge smoke confirmed:

- Cloud Addon exposes the bridge health seam;
- Toolbox returns a Site Knowledge auto-sync health array;
- Toolbox reports `owner=cloud_addon` when Cloud Addon is active;
- Toolbox reports Cloud Addon bridge mode;
- Toolbox legacy fallback is disabled while Cloud Addon bridge is present;
- Cloud Addon owns the public post status hook;
- Toolbox legacy auto-sync cron hook is not registered.

## Push State

Push order:

1. Cloud Addon first, because Toolbox now depends on the verified bridge health
   semantics.
2. Toolbox second, including the already-ahead documentation commit
   `b93a8b6 Document content support toolkit migration history`.

Final pushed heads:

- `/Users/muze/gitee/npcink-cloud-addon`: `master...origin/master`
- `/Users/muze/gitee/npcink-workflow-toolbox`: `master...origin/master`

Both working trees were clean after push.

Follow-up Toolbox hardening commit `b9e476a` does not reopen the retired legacy
auto-sync path. It keeps the same boundary while adding disabled-state runtime
loading guards, scope-aware permission context, bounded Cloud request metadata,
Site Knowledge sync payload limits, and raw-debug redaction.

## Boundary Guardrails For Future Work

Do not reintroduce any of the following into Toolbox:

- public content-change hooks for automatic Site Knowledge delivery;
- local Site Knowledge auto-sync queue;
- local retry or dead-letter behavior;
- local reconciliation worker;
- automatic background Cloud sync from content hooks;
- vector collection lifecycle ownership;
- direct WordPress writes or write confirmation contracts.

If future work needs stronger automatic freshness, extend the Cloud Addon bridge
or the Cloud Site Knowledge service. Do not rebuild the retired Toolbox fallback.

## Next Recommendation

Stop this slice here.

The boundary is now clear and verified. The next useful step is release
tracking, not more implementation:

- watch the first real Cloud Addon verified-site run for bridge health values;
- confirm no operator needs the retired Toolbox queue vocabulary;
- only reopen implementation if bridge health shows a real delivery failure or
  missing Cloud-side diagnostic.
