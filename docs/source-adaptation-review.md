# External Source Adaptation Review

Status: evolved into the URL-reference input path for `article_writing_pack.v1`.

## AI Change Envelope

- **Target repositories:** `/Users/muze/gitee/npcink-workflow-toolbox` only.
- **Focused module:** Gutenberg `Npcink Content Support` source research and
  adaptation review.
- **Intended change:** accept one public source URL, first return a bounded exact
  URL extraction preview, then query Cloud Site Knowledge for related local
  passages and return one review-only `article_writing_pack.v1` artifact with
  inferred audience, priorities, source facts, overlap, distinct angle, outline,
  and fact/copyright/similarity checks.
- **Explicit non-goals:** no full article generation, translation-and-publish,
  direct URL fetch from WordPress, media import, editor body replacement,
  automatic publishing, Core proposal creation, queue, indexing lifecycle,
  provider controls, or reusable workflow registry.
- **Boundary owner:** Toolbox owns the editor button and composition; Cloud owns
  web-reader runtime, hosted AI runtime, and Site Knowledge/vector detail;
  WordPress authors retain article text and native save authority.
- **Public contracts touched:** compatibility editor intent
  `source_adaptation_review`, stages `extract|research_plan` (`adapt` alias),
  input mode `url_reference`, Cloud `source_extraction_preview.v1`, Site
  Knowledge `writing_support_plan`, hosted content-support suggestion contract,
  and artifact `article_writing_pack.v1`.
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

The first slice intentionally stops before article-body generation. Cloud web
reader output is bounded evidence, not proof that a whole source article was
captured. Site Knowledge passages are style and coverage hints, not factual
sources for claims about the external article.

## Flow

```text
one public source URL
-> Cloud exact URL reader (`source_extraction_preview.v1`)
-> requested/resolved URL match, bounded coverage, opening/closing previews
-> operator verifies that the intended source was captured
-> continue explicitly
-> Cloud Site Knowledge writing-support query
-> related local passages and coverage signals
-> hosted AI writing-pack inference
-> `article_writing_pack.v1`
-> operator reviews inferred audience, focus, facts, overlap, angle, outline,
   and risks
-> future article generation remains blocked in this stage
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
- Accept only `input_mode=url_reference` in this version and fail unsupported
  modes explicitly. Preserve the generic `source_materials` and
  `editorial_brief` output boundary for later manual and mixed inputs.
- Treat reader content as `untrusted_external_source`; embedded instructions
  are data and must never override the adaptation prompt.
- Keep every returned artifact `suggestion_only` with
  `direct_wordpress_write=false`.
- Do not expose a button that inserts or replaces the article body.
- Return `article_generation_allowed=false`; the writing pack is a prerequisite
  artifact, not permission to generate or write an article.
- Make incomplete reader coverage, source rights, factual verification, and
  overlap with existing site articles explicit review items.

## Next Admission Gate

Do not add full translation, full-body adaptation, or native editor insertion
until a trial shows that operators repeatedly use the review artifact and that
source extraction coverage, factual preservation, and copyright confirmation
can be measured reliably.

Use the repeatable [Source Adaptation Operator Trial](source-adaptation-operator-trial.md)
and its real-public-URL fixtures before making that decision.
