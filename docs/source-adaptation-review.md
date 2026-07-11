# External Source Adaptation Review

Status: first bounded editor slice.

## AI Change Envelope

- **Target repositories:** `/Users/muze/gitee/npcink-workflow-toolbox` only.
- **Focused module:** Gutenberg `Npcink Content Support` source research and
  adaptation review.
- **Intended change:** accept one public source URL, request bounded Cloud web
  evidence with URL-reader enhancement, query Cloud Site Knowledge for related
  local passages, and return one review-only `source_adaptation_review.v1`
  artifact with Chinese source summary, site-style signals, adaptation
  directions, outline guidance, and fact/copyright checks.
- **Explicit non-goals:** no full article generation, translation-and-publish,
  direct URL fetch from WordPress, media import, editor body replacement,
  automatic publishing, Core proposal creation, queue, indexing lifecycle,
  provider controls, or reusable workflow registry.
- **Boundary owner:** Toolbox owns the editor button and composition; Cloud owns
  web-reader runtime, hosted AI runtime, and Site Knowledge/vector detail;
  WordPress authors retain article text and native save authority.
- **Public contracts touched:** editor intent `source_adaptation_review`, Cloud
  `web_search.v1` reader inputs, Site Knowledge `writing_support_plan`, hosted
  content-support suggestion contract, and artifact
  `source_adaptation_review.v1`.
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

> Given one external article, what should an editor preserve, verify, and
> change so a future human-written article fits this site's existing coverage
> and tone without copying or automatically publishing the source?

The first slice intentionally stops before article-body generation. Cloud web
reader output is bounded evidence, not proof that a whole source article was
captured. Site Knowledge passages are style and coverage hints, not factual
sources for claims about the external article.

## Flow

```text
one public source URL
-> Cloud web search with bounded URL-reader enhancement
-> source evidence and extraction status
-> Cloud Site Knowledge writing-support query
-> related local passages and coverage signals
-> hosted AI source-adaptation review
-> operator reviews summary, style signals, directions, outline, and risks
-> human editor writes or revises the current article
```

## Acceptance Rules

- Accept only one public `http` or `https` URL with a normal hostname.
- Reject localhost, loopback, private/reserved IP literals, credential-bearing
  URLs, and non-web schemes before any Cloud request.
- Request at most one external source result and six local Site Knowledge
  results.
- Preserve the resolved source URL and reader status in review evidence.
- Block before Site Knowledge or hosted AI calls when Cloud search returns a
  different article path on the same allowed domain.
- Keep every returned artifact `suggestion_only` with
  `direct_wordpress_write=false`.
- Do not expose a button that inserts or replaces the article body.
- Make incomplete reader coverage, source rights, factual verification, and
  overlap with existing site articles explicit review items.

## Next Admission Gate

Do not add full translation, full-body adaptation, or native editor insertion
until a trial shows that operators repeatedly use the review artifact and that
source extraction coverage, factual preservation, and copyright confirmation
can be measured reliably.
