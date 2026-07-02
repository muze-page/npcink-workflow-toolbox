=== Npcink Workflow Toolbox ===
Contributors: npcink
Tags: ai, seo, editorial-workflow, media, content
Requires at least: 6.9
Requires PHP: 8.0
Tested up to: 7.0
Stable tag: 0.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Fixed AI workflow buttons for WordPress operators, with review-only suggestions and governed handoff plans.

== Description ==

Npcink Workflow Toolbox helps WordPress operators turn proven AI-assisted
content and site-operations practices into fixed, review-only buttons.

The plugin provides a WordPress admin surface and post-editor panel for:

* a Dashboard Zhihu hot-topic pool for deciding what may be worth researching
  today before any draft generation or publishing decision;
* read-only Zhihu capability checks for connected Cloud search, hot-list, and
  direct-answer lanes;
* writing preparation, title ideas, outline support, summary suggestions, and
  publish-readiness checks;
* SEO, AEO, and GEO guidance from operator-maintained site context;
* existing-category and existing-tag recommendations for review;
* internal-link candidates and source-coverage notes;
* image-source candidates, reviewed hosted image candidates, current-article
  media ALT/caption review sets, and media planning handoff packets;
* Cloud-managed site knowledge search, status, and sync requests when a
  compatible host runtime is connected;
* review-only Scheduled Review previews, with Cloud runtime inspection and recovery routed to Cloud Addon.

Toolbox returns suggestions, candidates, previews, and planning artifacts. It
does not publish posts, approve proposals, import media, create terms, update SEO
metadata, mutate media metadata, or run a local workflow queue as part of the
default content-support flow.

When a write-like action is needed, Toolbox prepares a governed handoff plan for
the host's Npcink Governance Core, OpenClaw Adapter, and WordPress Abilities
stack. Final approval, preflight, audit, and WordPress writes remain outside
Toolbox.

= Good Fit =

Npcink Workflow Toolbox is useful when:

* editors want AI-assisted content checks without one-click publishing;
* administrators need fixed buttons for repeatable review workflows;
* editors want a low-friction "what should we research today?" surface backed
  by a connected Cloud hot-list runtime;
* a site uses Npcink Cloud or a host-provided runtime for web search, image
  candidates, site knowledge, or hosted AI suggestions;
* accepted changes must stay reviewable and auditable before any WordPress write.

= Not A Good Fit =

Npcink Workflow Toolbox is not a standalone article generator, SEO autopilot,
media import bot, indexing system, or workflow scheduler. Cloud-backed features
require a connected Npcink Cloud Addon or compatible host runtime. Without that
runtime, the plugin still shows local settings, context forms, and review-only
local planning surfaces, but Cloud-managed searches and AI runtime actions fail
closed.

== Installation ==

1. Upload the `npcink-workflow-toolbox` folder to `/wp-content/plugins/`, or install it
   through the WordPress plugin installer.
2. Activate **Npcink Workflow Toolbox** in WordPress.
3. Open **Npcink -> Workflow Toolbox** when a Npcink menu is available, or
   **Tools -> Npcink Workflow Toolbox** for standalone installs.
4. Fill the Site Context fields with non-secret positioning, audience, brand
   voice, keyword, and SEO/AEO/GEO guidance.
5. Connect the required Npcink Cloud Addon or host runtime before using
   Cloud-managed search, image-source, site knowledge, hosted AI, or Pro Cloud
   Runtime features.

== External Services ==

Npcink Workflow Toolbox can contact external services only after an
administrator uses a feature that requires the connected service or configures
the related runtime. The plugin does not load third-party JavaScript or CSS from
those services.

= Npcink Cloud runtime =

Used for Cloud-managed web search, image-source candidates, site knowledge
search/sync/status, hosted AI content-support suggestions, reviewed hosted
image candidates, and Cloud Addon Runtime Runs inspection details.

Npcink Workflow Toolbox is designed to work with the official Npcink hosted
runtime service, Npcink Cloud. The plugin does not include or hard-code a Cloud
service endpoint. A site administrator connects to Npcink Cloud through a
companion connector such as Npcink Cloud Addon, or a host may provide an
equivalent runtime through documented filters.

Npcink Cloud is responsible for account/key issuance, service terms, privacy
policy, data retention, hosted runtime execution, and any provider subprocessors
used behind the Cloud runtime. Site administrators should review these service
documents before connecting a WordPress site to Npcink Cloud:

Terms of Service: https://cloud.npc.ink/terms/en/terms.html
Privacy Policy: https://cloud.npc.ink/terms/en/privacy.html
Data Retention: https://cloud.npc.ink/terms/en/data-retention.html

If a site host configures a non-Npcink runtime through Toolbox filters, that host
runtime is responsible for its own terms of service, privacy policy, data
retention, account/key issuance, and provider subprocessors. Site administrators
should review the configured host runtime's policies before connecting it to
Toolbox.

Data that may be sent depends on the feature used and may include the submitted
query, selected post identifiers, public post/page titles, public excerpts,
public URLs, approved public comment excerpts for selected public entries, image
metadata already visible in WordPress, operator-entered content context, and
operator-entered prompts or review notes. Provider API keys are not exposed to AI
callers through Toolbox responses.

= Unsplash =

When a connected runtime uses Unsplash for image-source candidates, image search
queries and candidate selection metadata may be sent to Unsplash. Unsplash
candidate payloads preserve attribution and download tracking metadata.

Terms: https://unsplash.com/terms
Privacy: https://unsplash.com/privacy

= Pixabay =

When a connected runtime uses Pixabay for image-source candidates, image search
queries may be sent to Pixabay and returned candidates may be shown for operator
review.

Terms: https://pixabay.com/service/terms/
Privacy: https://pixabay.com/service/privacy/

= Pexels =

When a connected runtime uses Pexels for image-source candidates, image search
queries may be sent to Pexels and returned candidates may be shown for operator
review.

Terms: https://www.pexels.com/terms-of-service/
Privacy: https://www.pexels.com/privacy-policy/

== Privacy ==

Npcink Workflow Toolbox stores two local WordPress options:

* `npcink_toolbox_settings` for feature flags and local settings;
* `npcink_toolbox_content_context` for non-secret site guidance.

Do not store provider secrets, private customer data, billing details, or write
authorization in the content context fields.

Cloud-backed features may send bounded review data to the connected Npcink Cloud
or host runtime. The plugin is designed to send suggestions and evidence for
operator review, not to grant direct WordPress write authority to an external
service.

== Frequently Asked Questions ==

= Does this plugin write or publish my content automatically? =

No. The default content-support flows return suggestions, candidates, previews,
and handoff plans. Final WordPress writes require the separate governed host
workflow.

= Can I use it without Npcink Cloud? =

Partially. Local settings, Site Context, editor surfaces, and review-only local
planning remain available. Cloud-managed web search, image-source candidates,
site knowledge, hosted AI suggestions, reviewed hosted image candidate requests,
and Pro Cloud Runtime checks require a connected runtime.

= Does the Zhihu hot-topic pool generate posts? =

No. The Dashboard topic pool is a research-selection surface. It can show
Cloud-provided hot-list candidates for operator review, but it does not create
drafts, rewrite content, or publish posts.

= Does Toolbox create new categories or tags? =

No. The first version recommends existing categories and tags for review. New
vocabulary remains a separate governance decision outside Toolbox shortcuts.

= Is Unsplash image search the same as hosted image candidate requests? =

No. Unsplash, Pixabay, and Pexels are image-source providers. Reviewed
host-generated image candidates are a separate explicit mode and still do not
import media or set featured images by themselves.

= What happens when I accept a suggestion? =

Accepted choices can be packaged into a governed handoff plan. Core proposal
approval, preflight, audit, and the final WordPress ability execution remain
outside Toolbox.

== Changelog ==

= 0.1.1 =

* Added the review-only Dashboard `知乎热榜选题` topic-pool surface for daily
  research selection.
* Added read-only Zhihu capability checks for connected Cloud search, hot-list,
  and direct-answer lanes.
* Tightened the Zhihu hot-topic table so machine identifiers and URL-like
  values are hidden from the operator-facing signal column.
* Clarified current-article media ALT/caption review-set positioning in the
  WordPress.org-facing readme.
* Kept hot topics, media ALT/caption suggestions, and accepted choices
  suggestion-only with no direct WordPress or media metadata writes.

= 0.1.0 =

* Initial WordPress admin toolbox and post-editor content-support surface.
* Added review-only writing preparation, summary, taxonomy/tag, internal-link,
  image candidate, current-article image ALT, and publish-readiness support.
* Added Site Context fields for non-secret SEO/AEO/GEO guidance.
* Added Cloud-managed search, image-source, site knowledge, hosted AI, and
  Cloud Addon runtime-detail links that fail closed without a connected runtime.
* Added governed handoff plans for article, media, image candidate, site
  knowledge, and metadata review workflows.
* Added WordPress Abilities API registrations for suggestion and planning
  actions.
* Added static release and boundary tests.
