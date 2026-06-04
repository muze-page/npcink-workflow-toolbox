=== Magick AI Toolbox ===
Contributors: magick-ai
Requires at least: 6.9
Requires PHP: 8.0
Tested up to: 7.0
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Operator-facing AI tools for Tavily/Bocha research, Jina Reader result
enhancement, Unsplash/Pixabay/Pexels image-source candidates, Cloud-managed
site knowledge, SiliconFlow or Jina query embeddings, Qdrant vector search, and
repeatable content workflows, with read-only SEO/AEO/GEO context for
third-party AI.

== Description ==

Magick AI Toolbox provides a WordPress admin toolbox for external research,
image-source candidate search, Cloud-managed site knowledge abilities,
text-to-vector search, and fixed-flow planning actions. Operators can also fill
non-secret content discoverability context so third-party AI callers can read
site positioning, keywords, brand voice, and SEO/AEO/GEO rules through the
WordPress Abilities API. They can also validate the filled context and request
a suggestion-only content discoverability brief or AI article writing pack for
one post or topic.

It returns suggestions and planning artifacts. WordPress writes should still go
through host-governed WordPress abilities and Magick AI Core approval.

== Changelog ==

= 0.1.0 =

* Initial plugin scaffold.
* Added Toolbox settings page.
* Added REST routes for web research, image-source candidates, vector search,
  article briefs, and media briefs.
* Added Bocha search configuration and optional Jina Reader result enhancement
  for the shared web research ability.
* Added configurable Unsplash, Pixabay, and Pexels image-source provider
  options for the shared image candidate ability.
* Added WordPress Abilities API registrations for Toolbox actions.
* Added Cloud-managed site knowledge abilities for semantic search, related
  content, status, and sync requests through the Cloud Addon runtime seam.
* Added SiliconFlow and Jina embedding configuration for text-based vector
  search.
* Added read-only content discoverability context for third-party AI callers.
* Added content context validation and content discoverability brief abilities.
* Added a high-level AI article writing pack ability for OpenClaw-style natural
  language article requests.
