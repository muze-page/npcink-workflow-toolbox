# Content Discoverability Context

Status: active first-version contract.

This document records the SEO, AEO, and GEO context surface for future AI
sessions. The feature exists so a WordPress operator can fill in site facts and
content-governance rules in Toolbox, then expose that read-only context through
the WordPress Abilities API for OpenClaw, Agent Gateway, Open API, or other
third-party AI callers.

## Product Shape

Toolbox owns:

- the operator-facing form for site content context;
- storage for non-secret SEO, AEO, and GEO guidance;
- a read-only Abilities API action that returns the context;
- suggestion-only workflow inputs for third-party AI callers.

Toolbox does not own:

- final SEO meta, slug, excerpt, schema, or post writes;
- Core proposal records, approvals, or audit logs;
- OpenClaw, Agent Gateway, Open API, or MCP projection truth;
- a second settings, ability, workflow, approval, or write registry;
- third-party provider secrets in this context payload.

## Storage

Content context is stored separately from provider settings:

```text
magick_ai_toolbox_content_context
```

Do not merge this into:

```text
magick_ai_toolbox_settings
```

The provider settings option may contain API keys and connector endpoints.
Third-party AI context must remain safe to expose through Abilities and must not
include provider keys, request logs, billing details, quotas, or private
credentials.

## Abilities Surface

Current read-only/context abilities:

```text
magick-ai-toolbox/get-content-discoverability-context
magick-ai-toolbox/validate-content-discoverability-context
magick-ai-toolbox/build-content-discoverability-brief
magick-ai-toolbox/build-ai-article-writing-pack
```

Scopes:

```text
cap.toolbox.context.read
cap.toolbox.workflow_suggest
```

Native ability metadata should include:

```text
readonly: true
show_in_rest: true
required_scope: cap.toolbox.context.read
data_classification: public_context
write_posture: suggestion_only
```

The ability returns guidance only. Third-party AI must treat it as context for
suggestions, briefs, and proposal-ready payloads, not as permission to commit
WordPress writes.

`validate-content-discoverability-context` returns `ready`,
`ready_with_warnings`, or `needs_attention` plus required/recommended field
checks. It is intended as a preflight before third-party AI uses the context.

`build-content-discoverability-brief` accepts supplied topic/title/content or a
local `post_id` and returns a suggestion-only SEO, AEO, and GEO instruction
pack, exception/special-case rules, proposal template, and conservative
candidate values grounded in the source. This is the primary lightweight
SEO/AEO/GEO contract for third-party AI. It does not call a model and does not
write WordPress data.
In short, this is the primary lightweight SEO/AEO/GEO contract.
It does not call a model and does not write WordPress data.

`build-ai-article-writing-pack` is the high-level OpenClaw-friendly entrypoint
for natural-language article requests. It composes validation, context, the
discoverability brief, writing instructions, and guardrails into one
suggestion-only pack so the caller does not need to manually chain the
lower-level context abilities. It is a convenience fallback, not the primary
SEO/AEO/GEO context contract.

## Payload Shape

The ability returns a JSON object shaped like:

```json
{
  "context_type": "content_discoverability",
  "version": 1,
  "write_posture": "suggestion_only",
  "final_write_path": "core_proposal_required",
  "direct_wordpress_write": false,
  "site_positioning": "",
  "target_audience": [],
  "brand_voice": "",
  "keywords": {
    "primary": [],
    "long_tail": [],
    "entities": []
  },
  "claims": {
    "allowed": [],
    "forbidden": []
  },
  "exceptions": {
    "disallowed_topics": [],
    "cautious_topics": [],
    "no_structured_output_topics": [],
    "human_confirmation_required": []
  },
  "rules": {
    "seo": "",
    "aeo": "",
    "geo": "",
    "allow_faq_generation": true,
    "allow_aeo_summary": true,
    "allow_geo_summary": true,
    "allow_structured_data_suggestions": true
  },
  "proposal_allowed_fields": [
    "seo_title",
    "seo_description",
    "slug",
    "excerpt",
    "faq",
    "answer_summary",
    "geo_summary"
  ],
  "handoff": {
    "consumer": "abilities_or_agent_gateway",
    "final_writes": "core_proposal_required",
    "direct_wordpress_write": false
  }
}
```

## Operator Fields

The first version exposes these admin fields:

- site positioning;
- target audience;
- brand voice;
- primary keywords;
- long-tail keywords;
- entity keywords;
- allowed claims;
- forbidden claims;
- disallowed topics;
- cautious topics;
- topics where FAQ/HowTo/schema suggestions must not be generated;
- claims that require human confirmation;
- SEO rules;
- AEO rules;
- GEO rules;
- FAQ suggestion toggle;
- AEO answer summary toggle;
- GEO summary toggle;
- structured data suggestion toggle;
- proposal fields AI may suggest.

The admin page also shows an ability-preview JSON block so operators and future
AI sessions can see exactly what third-party callers will receive.

## Third-Party AI Usage

Third-party AI should:

1. read `magick-ai-toolbox/get-content-discoverability-context`;
2. call `magick-ai-toolbox/validate-content-discoverability-context` and stop
   for operator input if required fields are missing;
3. call `magick-ai-toolbox/build-content-discoverability-brief` for one post or
   topic;
4. consume the brief's `seo`, `aeo`, `geo`, `exceptions`, and `special_cases`
   blocks as the primary SEO/AEO/GEO contract;
5. use `magick-ai-toolbox/build-ai-article-writing-pack` only as a convenience
   fallback for broad natural-language article requests;
6. combine the brief or writing pack with read-only site/post abilities when needed;
7. produce suggestions for the fields listed in `proposal_allowed_fields`;
8. preserve `forbidden_claims`, `exceptions`, `special_cases`, and the site
   `brand_voice`;
9. hand write-like outcomes to Core proposal flows.

Third-party AI must not:

- mutate this context;
- treat OpenClaw or Agent Gateway as a second context truth source;
- directly write SEO fields, slugs, excerpts, FAQs, schema, media, or posts;
- invent product facts, customer examples, rankings, citations, or guarantees;
- leak connector keys or private credentials into prompts, outputs, proposals,
  logs, REST responses, or docs.

## Local Readiness Smoke

After the operator fills the content context, run the local smoke through
WP-CLI:

```bash
wp eval-file tests/smoke-content-discoverability.php -- [post_id]
```

The optional `post_id` lets the operator test a specific draft or post. Without
it, the smoke uses the most recently modified local post it can read. The script
does not print the context body, post title, or generated suggestions; it only
prints pass/fail status and the sampled post id.

The smoke verifies:

- the four content discoverability and writing-pack abilities are registered through
  `magick_ai_abilities_get_registered()`;
- each ability is read-only, REST-discoverable, projected into the Magick
  compatibility catalog, exposes no provider secrets, and declares no direct
  WordPress write path;
- the saved content context validates as `ready` or `ready_with_warnings`;
- `build-content-discoverability-brief` can build one suggestion-only brief for
  a real local post and exposes `seo`, `aeo`, `geo`, `exceptions`, and
  `special_cases`;
- `build-ai-article-writing-pack` can build one suggestion-only writing pack for
  the same local post;
- the optional Agent Gateway projection matrix is inspected when available.

Agent Gateway/OpenClaw direct tool exposure is intentionally reported as a
status, not forced by Toolbox. Core/Agent Gateway still owns the `wp_*` tool
name, `allowed_channels`, scope, quota, audit, and execution policy. If the
smoke reports that the Agent Gateway direct tool map is missing these abilities,
the next task belongs in the host projection/admission contract, not in Toolbox.
Missing `wp_*` Agent Gateway exposure is a host-side admission task.

## Future Work

Possible later abilities:

- `magick-ai-toolbox/build-content-discoverability-batch-brief`

Do not add an update-context ability for third-party AI in the first version.
If an external update path becomes necessary, it must be governed by Core and
must not bypass local administrator review.
