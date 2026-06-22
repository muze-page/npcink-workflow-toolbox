# Zhihu Hot Topic Pool Stage Summary - 2026-06-22

Status: implemented as the first productized Zhihu atom.

## Background

This stage evaluated four Zhihu Open Platform lanes:

- Web-wide search: high-trust external evidence for AI callers.
- Zhihu search: community question, viewpoint, and objection signals.
- Zhihu hot list: daily trend signals for topic selection.
- Zhihu direct answer: answer-preview atoms in simple, deep, and deepsearch modes.

The product decision is to productize only the hot-topic lane now. The other
lanes remain callable atoms, but they should not become default Toolbox product
surfaces until a concrete usage scenario exists.

## Product Positioning

The feature is `热点选题`, not article writing.

It solves one narrow problem:

> Editors often do not know what is worth writing today. The hot-topic pool gives
> a daily list of trend signals so the editor can choose a topic before doing
> manual research and drafting.

Primary use cases:

- choose today's topic;
- screen whether a trend fits the site's audience and columns;
- build a manual research queue;
- decide whether a topic deserves deeper Zhihu search or external evidence.

Non-goals:

- no automatic article generation;
- no copying, rewriting, or publishing Zhihu content;
- no factual-source claim from the hot list alone;
- no WordPress write path;
- no second workflow, approval, or scheduler surface.

## Current Implementation

The operator-facing entry point is a WordPress Dashboard widget on
`wp-admin/index.php`:

```text
intent=zhihu_hot_topics
widget=知乎热榜选题
```

The previous editor-side `热点选题` and `知乎选题研究` buttons are not default
visible flows. Both lanes remain callable through REST intent and low-level
ability contracts, but the default product surface is the Dashboard topic pool.
This keeps topic selection in the pre-writing workspace instead of the article
editing sidebar.

Cloud remains the runtime provider:

- Cloud calls the Zhihu hot-list lane.
- Cloud caches the hot list server-side.
- Cloud returns `topic_candidate.v1` inside `atomic_outputs`.

Toolbox remains the local product and projection surface:

- Toolbox renders the `知乎热榜选题` Dashboard widget.
- Toolbox calls Cloud through the existing Cloud Addon runtime seam.
- Toolbox normalizes the Cloud topic candidates into `hot_topic_pool`.
- Toolbox keeps all outputs `suggestion_only` and `direct_wordpress_write=false`.

## OpenClaw Usage

OpenClaw and other local AI callers should not use a separate Zhihu ability id
for this first version. They should call the existing local Toolbox ability:

```text
npcink-toolbox/cloud-web-search
```

Recommended input:

```json
{
  "query": "知乎热榜",
  "intent": "zhihu_hot_topics",
  "managed_source": "zhihu_hot_topics",
  "max_results": 5,
  "recency_days": 1
}
```

Expected local output:

- `artifact_type=web_search_results`
- `composition_role=external_web_evidence`
- `atomic_outputs.topic_candidates.contract_version=topic_candidate.v1`
- `hot_topic_pool.contract_version=zhihu_hot_topic_pool.v1`
- `hot_topic_pool.problem_solved=daily_topic_selection`
- `hot_topic_pool.operator_next_action=select_topic_then_manual_research`
- `write_posture=suggestion_only`
- `direct_wordpress_write=false`

## User Experience

On the WordPress Dashboard, the user sees:

- `知乎热榜选题`
- a short explanation that it solves "今天写什么";
- a compact table with rank, topic, signal, suggested use, and source action;
- a refresh button that clears the local Dashboard transient and reuses the
  existing Cloud hot-list runtime.

The result should be read as trend signal, not final evidence. If the editor
chooses one topic, the next human step is manual research and drafting.

## Verification

Static checks:

```bash
php -l includes/Provider_Client.php
php -l includes/Abilities.php
composer test
```

Targeted contract checks passed:

```bash
php tests/run.php --filter='Zhihu'
php tests/run.php --filter='hot_topic_pool'
```

Local WordPress REST dispatch passed with:

```text
intent=zhihu_hot_topics
status=200
direct_wordpress_write=false
has_hot_topics=true
has_hot_topic_pool=true
result_count=5
section_status=ready
```

## Next Stage

Do not productize the other Zhihu lanes by default yet.

The next useful step is an editor trial:

1. Run `热点选题` for several real site days.
2. Record whether editors can select useful topics from the pool.
3. Mark which selected topics require deeper Zhihu research, web-wide evidence,
   or direct-answer preview.
4. Only then decide whether to productize one of the remaining atoms.
