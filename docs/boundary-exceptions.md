# Boundary Exceptions Registry

Npcink Workflow Toolbox is a fixed-button, review-only operator surface. This
registry records the two current exceptions that are intentionally allowed to
exist inside the plugin without changing that product posture.

An entry here is not a precedent for new direct writes, schedulers, queues,
provider control panels, indexing lifecycle controls, or approval stores. New
exceptions require a separate ADR and matching static contracts before any code
path is added.

## Exception 1 - Local Admin Consent Featured Image

Status: accepted narrow proof.

Owner: Toolbox route and editor UI; Core owns audit truth.

Allowed scope:

- one present WordPress administrator action;
- one current post;
- one existing WordPress image attachment;
- set that attachment as the current post featured image;
- record Core-owned `local_admin_consent.requested` and
  `local_admin_consent.completed` audit events;
- roll back the featured-image change if completion audit fails.

Required static contracts:

- only one `/local-admin-consent/*` REST route is registered;
- the route remains `/local-admin-consent/featured-image`;
- the route requires an image attachment and
  `Operation_Classifier::LOCAL_ADMIN_CONSENT`;
- completion-audit failure triggers rollback;
- article/media batch handoffs never use Local Admin Consent audit events.

Hard stop:

- no media import;
- no media metadata write;
- no SEO meta write;
- no taxonomy or excerpt write;
- no post creation or publishing;
- no proposal approval or execution;
- no batch action;
- no new Local Admin Consent route without a new ADR.

Primary decision record:

- [ADR-003: Local Admin Consent Requires A Separate Write Boundary](decisions/ADR-003-local-admin-consent-boundary.md)

## Exception 2 - Local Fallback WP-Cron Preview

Status: accepted bounded fallback preview.

Owner: `npcink-local-automation-runtime` module bundled in Toolbox until a
separate extraction ADR accepts a stable cross-plugin API and graceful Toolbox
degradation.

Allowed scope:

- disabled-by-default WP-Cron hook;
- local public-content dry-run evidence collection;
- one latest scheduled-review preview option;
- operator-visible preview inside Site Check Scheduled Review;
- JSON download of the dry-run preview for review/debugging.

Required static contracts:

- the clean disabled state does not register a schedule;
- the class keeps `latest_preview_option_only` safety metadata;
- the route surface does not add admin-post or Ajax execution endpoints beyond
  read-only JSON download;
- scheduled-review preview stays separate from Cloud run recovery;
- Cloud runtime runs, status, result, and retry ownership remain in Cloud Addon.

Hard stop:

- no Cloud call from the Basic WP-Cron dry-run;
- no Core proposal creation;
- no WordPress content write;
- no Action Scheduler path;
- no custom runtime tables;
- no lease store;
- no retry processor;
- no dead-letter processor;
- no local Pro scheduler truth.

Primary decision records:

- [ADR-004: Bundle Local Automation Runtime As An Isolated Module](decisions/ADR-004-bundle-local-automation-runtime-as-isolated-module.md)
- [ADR-005: Use WP-Cron Local Preview And Cloud Batch Runtime For Nightly Automation](decisions/ADR-005-wp-cron-cloud-batch-orchestration.md)

## Current Non-Exceptions

These are not exceptions and must remain outside Toolbox ownership:

- Cloud Checks or Troubleshooting Checks product surfaces;
- Site Knowledge indexing, rebuild, delete, or vector collection lifecycle;
- AI image generation as a provider playground;
- final publish, media upload, featured-image batch replacement, or SEO meta
  mutation without governed handoff;
- workflow runtime queues, schedulers, leases, retries, run tables, or approval
  stores.
