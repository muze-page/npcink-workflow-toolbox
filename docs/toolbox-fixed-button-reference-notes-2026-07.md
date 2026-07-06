# Toolbox Fixed Button Reference Notes - 2026-07

Status: reference notes for next-stage planning.

Question: for the current fixed buttons, editor recommendations, and
review-only content-support surface, do mature WordPress plugins already solve
similar interaction or capability problems we can learn from?

Short answer: yes. Toolbox should learn their operator affordances, evidence
presentation, and editor-side feedback patterns. It should not copy their
product ownership, write authority, provider administration, workflow runtime,
or broad SEO/AI suite scope.

## Current Toolbox Baseline

Toolbox already owns:

- fixed review-only operator buttons;
- Site Check as a ranked local review list plus optional Cloud detail request;
- editor Content Support default buttons for publish preflight, internal-link
  candidates, image candidates, and article audio candidates;
- local progressive recommendations for taxonomy, media, and preflight signals;
- Core-ready handoff artifacts for accepted write-like outcomes.

Toolbox must continue to avoid:

- final WordPress write authorization;
- direct SEO, media metadata, taxonomy, publish, or post-content mutation
  outside accepted boundary exceptions;
- provider/model/prompt settings and request logs;
- local workflow queues, schedulers, retries, leases, or runtime consoles;
- a generic AI writing suite that duplicates connector or generic AI plugin
  surfaces.

## Reference Sources

| Reference | Similar capability | Useful lesson | Boundary note |
| --- | --- | --- | --- |
| [PublishPress Checklists](https://wordpress.org/plugins/publishpress-checklists/) | Pre-publish requirements and visible completion state. | A checklist works because it makes readiness, missing items, and next actions easy to scan before publishing. | Borrow the readiness pattern, not publish enforcement ownership. Toolbox preflight remains advisory or Core-governed. |
| [Yoast SEO](https://wordpress.org/plugins/wordpress-seo/) | Editor-side SEO and readability analysis. | Small, specific checks with status colors and explanations are easier to trust than one broad AI answer. | Do not become an SEO plugin or mutate SEO metadata directly. SEO handoff remains Core/Adapter/Abilities governed. |
| [Rank Math SEO](https://wordpress.org/plugins/seo-by-rank-math/) | SEO analysis, content suggestions, and AI-assisted writing support. | Operators expect inline guidance, score-like summaries, and clear issue grouping near the editing workflow. | Do not copy full SEO suite ownership, schema management, keyword systems, or Content AI provider surface. |
| [AI Engine](https://wordpress.org/plugins/ai-engine/) | Generic AI content tools, chatbot, assistants, image generation, and model/provider settings. | The strongest learning is what to demote: generic AI experiments should not become default Toolbox buttons. | Provider keys, model routing, prompt management, logs, and chatbot/admin AI suite ownership stay outside Toolbox. |
| [WordPress AI plugin](https://github.com/WordPress/wordpress-ai) | WordPress AI building blocks, AI request handling, and Abilities-oriented integrations. | Keep AI capability metadata explicit and discoverable, with clear owners and labels. | Toolbox remains a workflow surface, not a generic abilities explorer, model router, or connector approval UI. |

## What To Borrow

Borrow these patterns because they improve operator trust without changing
Toolbox's ownership boundary:

- checklist-style readiness rows for publish preflight, Site Check, and default
  button health;
- explicit source labels, evidence refs, and action classes on every candidate;
- compact issue grouping: ready, needs review, blocked, unavailable, and
  handoffable;
- blocked-state copy that says exactly what is missing and where the operator
  should go next;
- stable visible owner labels such as local, Cloud, Toolkit, Core, or Adapter;
- editor-near recommendations that stay lightweight and do not replace the
  article body;
- one-click copy/open/review actions for manual editor work;
- score-like summaries only when the underlying checks are inspectable.

## What Not To Borrow

Do not import these patterns into Toolbox:

- generic SEO plugin ownership for titles, descriptions, schema, redirects,
  sitemaps, or keyword systems;
- generic AI chat, assistant, prompt playground, model picker, provider key
  storage, request log, or connector approval UI;
- automatic publish blockers that imply Toolbox owns publication policy;
- automatic insertion, rewrite, taxonomy assignment, SEO mutation, or media
  metadata updates;
- workflow builder, trigger/action marketplace, local queue, retry console, or
  run history;
- local Site Knowledge indexing, vector database, stale-index policy, rebuild,
  delete, or rerank controls;
- AI image generation as a provider product surface. Toolbox may show reviewed
  generated-image candidates only through the existing candidate seam.

## Candidate Improvements

### P1 - Preserve Existing Boundary

Keep the current default button list:

- Site Check;
- Publish Preflight;
- Internal Link Candidates;
- Image Candidates;
- Article Audio Candidates.

Generic title, summary, category, tag, article-checkup, discoverability,
current-article ALT, and comment support should remain route-compatible or
result-rendering paths unless a new product review proves they are Npcink
workflow buttons rather than generic AI plugin overlap.

### P1 - Improve Trust Cues

Add or keep visible labels in future UI work:

- source: local preflight, current draft, recent media, Toolkit, Cloud Site
  Knowledge, Cloud web search, Cloud image-source, or operator selection;
- action class: informational, copyable, handoffable, blocked, or unavailable;
- write posture: suggestion-only, local-admin-consent exception,
  Core-proposal-required, or route-only compatibility;
- runtime owner: Toolbox local, Toolkit, Cloud, Cloud Addon, Core, or Adapter.

This follows the strongest shared lesson from SEO, checklist, and AI plugins:
the operator should know why a recommendation exists before trusting it.

### P1 - Clarify Blocked States

Blocked states should be first-class rows, not generic errors. Examples:

- Cloud unavailable: keep local Site Check usable and show that Cloud detail is
  optional;
- Core unavailable: keep suggestions visible, but mark write-like handoffs as
  blocked;
- Toolkit ability unavailable: show the missing reusable artifact owner instead
  of silently falling back to a weaker action;
- insufficient post context: ask for a title, draft content, featured image, or
  saved post before running the relevant recommendation.

### P2 - Borrow Checklist Shape For Site Check

Site Check should continue to render a ranked review list, but its summary can
learn from checklist products:

- readiness rows by dimension: content, media, taxonomy, comments, context,
  Cloud readiness;
- each row shows status, evidence count, recommended next action, and owner;
- optional Cloud detail stays an explicit operator action and returns
  suggestion-only detail.

### P2 - Tighten Editor Recommendation Review

Future editor-side tuning should focus on the current local recommendation
contract:

- better media fit labels before Cloud rerank;
- clearer weak-evidence labels when a recommendation comes from recent media
  fallback;
- term-match confidence that rejects generic tokens and explains accepted
  evidence;
- preserving fast local prefetch without Cloud calls, proposals, or mutations.

## Decision Gate For New Work

Before adding a new visible Toolbox button, answer:

1. Is this a repeated Npcink workflow button, or generic AI/SEO plugin overlap?
2. What bounded input does the button collect?
3. What artifact contract does it return?
4. Who owns runtime/detail work?
5. Who owns any accepted WordPress write?
6. Which explicit non-goals prevent drift into writes, queues, indexing,
   provider control, approval, or audit ownership?

If any answer points to SEO suite ownership, AI provider administration,
workflow runtime, or direct WordPress writes, the work should not enter Toolbox
as a default button.

## Suggested Next Artifact

The next implementation planning artifact should be a UI acceptance checklist
for the existing default buttons only. It should not add capabilities. It
should verify that each existing button shows:

- source/evidence labels;
- action class;
- owner/runtime label;
- blocked-state guidance;
- governed handoff path when relevant;
- no direct WordPress write unless covered by the existing Local Admin Consent
  featured-image exception.
