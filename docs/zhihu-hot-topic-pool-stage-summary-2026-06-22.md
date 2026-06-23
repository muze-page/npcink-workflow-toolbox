# Zhihu Hot Topic Pool Stage Summary

Status: implemented as a lightweight Dashboard signal.

Last updated: 2026-06-23.

## Background

This stage explored whether Zhihu Open Platform should be connected to Toolbox
as writing-support infrastructure. The original scope considered four atomic
Cloud capabilities:

- Web-wide search: high-trust external evidence for AI callers.
- Zhihu search: community questions, human viewpoints, objections, and
  discussion angles.
- Zhihu hot list: daily trend signals for topic selection.
- Zhihu direct answer: simple, deep, and deepsearch answer-preview atoms.

The product decision is to keep all four as atomic capabilities, but only
productize the hot-list lane in Toolbox for now. The other three lanes remain
available for tests, OpenClaw-style callers, and future composed workflows, but
they should not become default Toolbox UI until there is a concrete writing
scenario.

## Product Positioning

The current feature is `知乎热榜`, not an article-writing tool.

It solves one narrow problem:

> Editors often do not know what is worth paying attention to today. The hot
> list gives a daily trend signal so the editor can decide whether a topic is
> worth manual research.

Primary use cases:

- glance at today's external trend signals from the WordPress Dashboard;
- decide whether a trend may fit the site's audience or columns;
- choose whether a topic deserves deeper Zhihu search, web-wide evidence, or
  direct-answer preview;
- feed later topic-selection or writing-preparation workflows as an input atom.

Non-goals:

- no automatic article generation;
- no copying, rewriting, or publishing Zhihu content;
- no claim that hot-list text is factual evidence by itself;
- no WordPress write path;
- no second workflow, approval, or scheduler surface;
- no local quota/billing control plane.

## Current Product Surface

The only default operator-facing surface is the WordPress Dashboard widget on
`wp-admin/index.php`.

Current UI shape:

- widget title: `知乎热榜`;
- short copy: hot-list title preview only, for judging external trends;
- each hot item is rendered as a compact separated card;
- title text uses normal reading color, not blue link styling;
- each row has a `查看` button for opening the source;
- no manual `刷新缓存` button in the Dashboard;
- cache status is shown in user-facing wording such as `本地` or `本地备份`.

This is intentionally a glance surface. It should not become a workbench. The
Dashboard answers: "what are today's visible signals?" It does not answer:
"which topic has been selected, researched, approved, or drafted?"

## Removed Or Deferred Surfaces

Several earlier entry points were removed or kept non-default after trial:

- The editor-side `热点选题` default button was removed. Topic selection happens
  before article editing, so it does not belong as a default editor action.
- The Toolbox admin `topic-pool` tab was removed. It duplicated the Dashboard
  signal without enough topic-processing workflow to justify a full page.
- The dedicated `admin.php?page=npcink-hot-topics` page was removed. It can be
  reconsidered only if it becomes a real selection workbench.

A future dedicated page would need additional workflow value, for example:

- mark topics as `可写`, `不适合`, or `待观察`;
- save topic decisions and reviewer notes;
- hand one topic into writing preparation;
- run focused Zhihu search or web-wide evidence collection from the selected
  topic;
- show topic history or site-audience fit.

Without those actions, the dedicated page is local UI noise.

## Implementation Boundary

Cloud remains the runtime provider:

- Cloud calls the Zhihu Open Platform hot-list lane.
- Cloud owns hosted credentials, service-side quota, entitlement, and usage
  metering.
- Cloud can cache hot-list data server-side to reduce calls to the 100/day
  hot-list API.
- Cloud returns structured topic candidates and usage metadata.

Toolbox remains the local projection and review surface:

- Toolbox calls Cloud through the existing Cloud Addon runtime seam.
- Toolbox uses `Hot_Topic_Pool` as the shared local reader.
- Toolbox stores a local transient cache for short-lived Dashboard reuse.
- Toolbox stores a local backup option so stale hot-list data can still display
  if Cloud is temporarily unavailable.
- Toolbox renders suggestion-only UI and does not write WordPress content.

Quota and credit restrictions belong in Cloud. Toolbox test surfaces may show
Cloud-returned `result.usage_summary` for debugging, but the user-facing credit
ledger should live on the Cloud user/account page rather than creating pressure
inside the WordPress plugin.

## Atomic Capability Model

The long-term model is four atoms, composed by higher-level workflows:

| Atom | Role | Default UI now |
| --- | --- | --- |
| Web-wide search | Credible external evidence and citations | Test/atomic only |
| Zhihu search | Human community angles and objections | Test/atomic only |
| Zhihu hot list | Daily trend signal | Dashboard widget |
| Zhihu direct answer | Quick or deep answer preview | Test/atomic only |

OpenClaw or other local AI callers should call these through existing Toolbox
or adapter seams instead of introducing a second Zhihu registry. For hot-list
use, the stable request shape remains:

```json
{
  "query": "知乎热榜",
  "intent": "zhihu_hot_topics",
  "managed_source": "zhihu_hot_topics",
  "max_results": 20,
  "recency_days": 1
}
```

Expected posture:

- `write_posture=suggestion_only`;
- `direct_wordpress_write=false`;
- `problem_solved=daily_topic_selection`;
- `operator_next_action=select_topic_then_manual_research`.

## Testing And Validation

The current local acceptance checks should cover:

- Dashboard widget registers `知乎热榜`;
- hot-list rows are card-separated and use per-row `查看` buttons;
- Dashboard has no manual refresh form;
- shared `Hot_Topic_Pool` calls `managed_source=zhihu_hot_topics`;
- shared reader uses local transient cache and stale backup fallback;
- Toolbox no longer hosts a hot-topic tab;
- `Hot_Topics_Page` and `admin.php?page=npcink-hot-topics` are not bootstrapped.

Recent verification commands:

```bash
composer lint:php
composer test:quiet
git diff --check
```

Browser verification may require a logged-in local WordPress admin session at
`https://npcink.local/wp-admin/index.php`.

## Next Stage

Do not productize the other Zhihu lanes by default yet.

The next useful product stage is a real topic-selection trial:

1. Watch whether the Dashboard `知乎热榜` signal is useful during several real
   publishing days.
2. Record whether editors can identify usable topics from the glance widget.
3. For selected topics, mark which follow-up atom was actually needed:
   Zhihu search, web-wide search, or direct answer.
4. If repeated topic-processing work appears, then add a dedicated workbench.
5. If no clear workflow appears, keep the feature as a Dashboard signal and an
   atomic input for OpenClaw.

The current stopping point is intentional: one lightweight local signal, no
duplicated admin page, no article-writing claims, and no second control plane.
