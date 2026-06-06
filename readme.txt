=== Npcink Toolbox ===
Contributors: npcink
Requires at least: 6.9
Requires PHP: 8.0
Tested up to: 7.0
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Operator-facing AI tools for Cloud-managed search, site knowledge, image candidates, and repeatable SEO/AEO/GEO content workflows.

== Description ==

Npcink Toolbox provides a WordPress admin toolbox for external research,
image-source candidate search, Cloud-managed site knowledge abilities,
text-to-vector search, and fixed-flow planning actions. Operators can also fill
non-secret content discoverability context so third-party AI callers can read
site positioning, keywords, brand voice, and SEO/AEO/GEO rules through the
WordPress Abilities API. They can also validate the filled context and request
a suggestion-only content discoverability brief or AI article writing pack for
one post or topic.

It returns suggestions and planning artifacts. WordPress writes should still go
through host-governed WordPress abilities and Npcink Governance Core approval.

== Changelog ==

= 0.1.0 =

* Initial plugin scaffold.
* Added Toolbox settings page.
* Added REST routes for image-source candidates, vector search, article briefs,
  and media briefs.
* Moved web search provider configuration and execution to Npcink Cloud.
* Added Cloud-managed image-source provider support for the shared image
  candidate ability.
* Added WordPress Abilities API registrations for Toolbox actions.
* Added Cloud-managed site knowledge abilities for semantic search, related
  content, status, and sync requests through the Cloud Addon runtime seam.
* Added SiliconFlow and Jina embedding configuration for text-based vector
  search.
* Added read-only content discoverability context for third-party AI callers.
* Added content context validation and content discoverability brief abilities.
* Added a high-level AI article writing pack ability for OpenClaw-style natural
  language article requests.
* Added an AI Draft Support entry group for lightweight title/summary,
  outline, polish, and summary/category/tag optimization suggestions through
  the hosted runtime.
* Added a summary/category/tag optimization fixed flow that returns
  suggestion-only article metadata candidates without updating excerpts,
  assigning terms, mutating SEO fields, or owning indexing.
* Added reviewed-prompt AI image candidate generation from image-source
  handoffs without media import or featured-image writes.
* Added a Site Knowledge review handoff plan that keeps Core submission,
  approval, preflight, and execution outside Toolbox.
* Added AI Site Helpers for review-only media ALT and bounded content snapshot
  suggestions without local scanning or writes.
* Added review checklist and reject-if guardrails for AI draft-support
  suggestions.
