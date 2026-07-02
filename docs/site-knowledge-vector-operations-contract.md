# Site Knowledge Vector Operations Contract

Status: active.

This contract keeps Site Knowledge vector operations split across Toolbox,
Cloud Addon, and Cloud service surfaces.

## Ownership

Toolbox owns fixed workflow buttons that consume Cloud-managed Site Knowledge
results. It may search, read status, and build review-only planning artifacts.
It must not own vector indexing, embedding providers, collection lifecycle,
stale-index policy, rerank configuration, or deep index diagnostics.

Cloud Addon owns the WordPress-side connector, signed runtime transport,
bounded public-content refresh requests, shallow delivery status, and the
operator link into Cloud Site Knowledge. It must not expose collection
management, re-index jobs, stale-index policy controls, embedding-provider
settings, Qdrant endpoints, or local vector database settings.

Cloud Site Knowledge owns embedding, vector storage, collection lifecycle,
freshness policy, rerank, re-index, quota, and deep diagnostics.

## Local Permissions

Default Toolbox and Cloud Addon routes remain administrator-only with
`manage_options` unless a narrower host authorization model is intentionally
designed.

Toolbox fixed editor workflows may use Site Knowledge search results as
suggestion-only evidence. They must not trigger public refresh, rebuild,
delete, collection migration, or final WordPress writes from editor roles.

Cloud Addon manual public refresh also requires `manage_options` and a verified
Cloud connection. The action sends a bounded refresh request; it does not
approve proposals, write WordPress content, or manage Cloud collections.

## Allowed WordPress-Side Operations

Allowed from Toolbox:

- `site_knowledge_search.v1` for semantic search, related content, internal
  link evidence, writing context, image context, FAQ candidates, content gap
  checks, and duplicate-risk checks.
- `site_knowledge_status.v1` for read-only Cloud coverage, freshness, progress,
  quota, and bridge status projection.
- `site_knowledge_sync.v1` only with `sync_mode=refresh` for bounded public
  content refresh transport.

Allowed from Cloud Addon:

- Listen for public `post` and `page` changes, and approved comment changes.
- Buffer affected public content ids for delivery durability.
- Send `sync_mode=refresh` through `POST /v1/runtime/execute`.
- Show connector state, buffered public changes, last delivery, last error,
  next flush, and an explicit link to Cloud Site Knowledge.

## Forbidden WordPress-Side Operations

Toolbox and Cloud Addon must reject or omit:

- `sync_mode=rebuild`
- `sync_mode=delete`
- manual re-index jobs
- collection create, update, migrate, or delete controls
- embedding provider settings
- vector database endpoints, dimensions, and collection names
- stale-index policy controls
- local vector stores
- local indexing queues or scheduler truth
- direct WordPress writes from Site Knowledge results

## Jina And Rerank Boundary

Jina Reader, Jina Reranker, embedding-provider selection, rerank policy, and
vector scoring strategy are not active Toolbox runtime features. Toolbox may
display candidate ranking, freshness, coverage, or rerank status returned by
Cloud-managed Site Knowledge, but it must not expose Jina toggles, Reader
enhancement controls, local rerank provider settings, embedding model fields, or
collection lifecycle operations.

If a future workflow needs Jina Reader or Jina Reranker, the accepted contract
must live in Cloud or Cloud Addon first. Toolbox may then consume the returned
evidence as suggestion-only result detail, not as provider configuration,
runtime ownership, or indexing lifecycle control.

## Content Admission

Public refresh payloads may include only:

- published posts and pages;
- bounded excerpts or manifests from those public entries;
- approved comments attached to those public entries;
- stable source identifiers such as post id, post type, permalink, modified
  time, and content hash;
- payload limits and truncation metadata.

Public refresh payloads must not include:

- drafts, private posts, password-protected posts, or trashed content;
- user emails, orders, memberships, form submissions, support tickets, or
  private CRM data;
- provider credentials, signing fields, nonces, API keys, cookies, or tokens;
- Core approval records, audit logs, or proposal truth;
- final WordPress write instructions.

Every WordPress-side Site Knowledge runtime payload must keep:

```json
{
  "data_classification": "public_site_content",
  "storage_mode": "result_only",
  "input": {
    "write_posture": "suggestion_only",
    "direct_wordpress_write": false
  }
}
```

## Product Surface Rule

If an operator asks to search, compare, find related content, prepare internal
links, or build a review plan, the entry can stay in Toolbox as a fixed
suggestion workflow.

If an operator asks to connect Cloud, refresh public content, inspect delivery,
or confirm whether the bridge is healthy, the entry belongs in Cloud Addon.

If an operator asks to rebuild an index, change vector providers, change
collection settings, diagnose stale-index policy, or inspect vector operations
deeply, the entry belongs in Cloud Site Knowledge.
