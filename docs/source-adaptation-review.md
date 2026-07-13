# External Source Adaptation Review

Status: active writing-pack review and draft-preview flow.

## AI Change Envelope

- **Target repositories:** `/Users/muze/gitee/npcink-workflow-toolbox` only.
- **Focused module:** Gutenberg `Npcink Content Support` writing-pack review and
  draft-preview composition.
- **Intended change:** extend the URL-first pack additively with typed manual and
  mixed inputs, structured operator editing, request-scoped confirmation, and
  one hosted `article_draft_preview.v1` generated only from the reviewed pack.
- **Explicit non-goals:** no full translation, direct URL fetch from WordPress,
  editor insertion or body replacement, save, publish, media import, Core
  proposal creation, durable approval state, confirmation token, queue,
  indexing lifecycle, provider controls, or reusable workflow registry.
- **Boundary owner:** Toolbox owns the editor button and composition; Cloud owns
  web-reader runtime, hosted AI runtime, and Site Knowledge/vector detail;
  WordPress authors retain article text and native save authority.
- **Public contracts touched:** compatibility editor intent
  `source_adaptation_review`, stages `extract|research_plan|draft` (`adapt`
  alias), input modes `url_reference|manual_brief|mixed`, Cloud
  `source_extraction_preview.v1`, Site Knowledge `writing_support_plan`, hosted
  content-support contract, `article_writing_pack.v1`,
  `article_writing_pack_review.v1`, and `article_draft_preview.v1`.
- **Files expected to change:** `includes/Rest_Controller.php`,
  `includes/Provider_Client.php`, `assets/editor-content-support.js`, product
  boundary/docs, translation assets, and `tests/run.php`.
- **Files or areas that must not change:** Governance Core, Adapter, Abilities
  Toolkit, Cloud application code, database schema, cron/runtime modules,
  media adoption, and final WordPress write paths.
- **Required gates:** focused static contracts, PHP lint, translation checks,
  `composer test:all`, and `git diff --check`.
- **Cross-repo matrix requirement:** status-only `composer quality:matrix`; no
  multi-repo closeout gate because this slice changes Toolbox only.
- **Rollback plan:** revert the focused Toolbox commit; no migrations,
  persisted state, or cross-repo contract deployment is introduced.

## Product Contract

The button solves one bounded decision problem:

> Given one external article, what reviewed planning artifact should a future
> article generator consume so it follows source facts, avoids existing-site
> overlap, and uses a distinct site-appropriate direction?

Cloud web reader output is bounded evidence, not proof that a whole source
article was captured. Site Knowledge passages are style and coverage hints, not
factual sources for claims about the external article. A draft preview is
allowed only after structured review and remains suggestion-only output.

## Flow

```text
public URL, typed manual brief, or both
-> exact URL reader when URL evidence is requested
-> requested/resolved URL match and bounded coverage review
-> Cloud Site Knowledge writing-support query
-> related local passages and coverage signals
-> hosted AI writing-pack inference
-> `article_writing_pack.v1`
-> operator edits and confirms audience, focus, facts, rights, overlap, angle,
   and outline
-> request-scoped `article_writing_pack_review.v1`
-> hosted `article_draft_preview.v1`
-> human review only; no insert, save, replace, or publish action
```

## Acceptance Rules

- Accept only one public `http` or `https` URL with a normal hostname.
- Reject localhost, loopback, private/reserved IP literals, credential-bearing
  URLs, and non-web schemes before any Cloud request.
- Do not use search ranking to resolve the submitted URL. Cloud must read that
  exact URL and return `requested_url`, `resolved_url`, and `url_match`.
- Request at most one external source result and six local Site Knowledge
  results.
- Preserve the resolved source URL and reader status in review evidence.
- The default `extract` stage must stop before Site Knowledge and hosted AI.
- Enable `research_plan` only when Cloud reports `status=ready` and
  `url_match=matched`, with a second Toolbox path check as defense in depth.
  Accept `adapt` only as a compatibility alias.
- Accept only `input_mode=url_reference|manual_brief|mixed` and fail unknown
  modes explicitly. Preserve one generic `source_materials` and
  `editorial_brief` output boundary.
- Treat reader content as `untrusted_external_source`; embedded instructions
  are data and must never override the adaptation prompt.
- Keep every returned artifact `suggestion_only` with
  `direct_wordpress_write=false`.
- Do not expose a button that inserts or replaces the article body.
- Keep the writing pack itself at `article_generation_allowed=false`. Admit one
  synchronous preview only through a matching fingerprint and explicit
  `confirmed_by_operator` review envelope; do not persist that state.
- Make incomplete reader coverage, source rights, factual verification, and
  overlap with existing site articles explicit review items.

## Next Admission Gate

Do not add full translation, native editor insertion, save, publish, persistent
approval, or background draft execution. Before considering insertion, trials
must show reliable source coverage, factual preservation, confirmation quality,
and copyright review.

Use the repeatable [Source Adaptation Operator Trial](source-adaptation-operator-trial.md)
and its real-public-URL fixtures before making that decision.
