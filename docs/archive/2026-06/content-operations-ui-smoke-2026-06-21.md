# Content Operations UI Smoke - 2026-06-21

This note records the local WordPress smoke test for the Toolbox Content
Operations and Agent Feedback Quality surfaces.

## Scope

- Local site: `https://npcink.local`
- Admin route: `wp-admin/admin.php?page=npcink-toolbox&toolbox_tab=cloud-checks`
- Plugin: `npcink-toolbox`
- Mode: local smoke only, no comment publication, no WordPress content write, no
  real Agent feedback submission to Cloud eval.

## Checks

- WordPress admin page loaded with an authenticated administrator session.
- Cloud Checks exposed these sub-tabs:
  - `content-operations`
  - `agent-quality`
- Content Operations refresh completed in the browser UI.
- Agent Feedback Quality refresh completed in the browser UI.
- Frontend console produced no error or warning messages during the smoke run.

## Content Operations Result

The `Content Operations` panel rendered:

- `Content operations projection loaded.`
- `seo_metadata`
- `media_alt_caption`
- `comment_reply`
- `Core approval and final WordPress writes remain local.`

The panel remained a read-only status projection. Approval truth and final write
truth stayed local.

## Agent Feedback Quality Result

The `Agent Feedback Quality` panel rendered:

- `Agent feedback summary: 0 events`
- `Tracked source runtimes`
- `Comment Reply`
- `Quality signal only; local proposal and write truth stay unchanged.`

The smoke did not submit a new Agent feedback event.

## REST Cross-Checks

`GET /npcink-toolbox/v1/status` returned:

- `content_operations` present
- `write_posture=suggestion_only`
- `direct_wordpress_write=false`
- source runtimes include `comment_reply`

`POST /npcink-toolbox/v1/agent-feedback/summary` returned:

- `status=200`
- `events_total=0`
- quality summary keys including `source_runtimes` and `quality_trend`

`POST /npcink-toolbox/v1/editor/content-support` with
`intent=comment_reply_suggestion` returned:

- `artifact_type=comment_reply_suggestion.v1`
- `status=ready`
- 3 review-only reply items
- 3 recommendation candidates
- `write_posture=suggestion_only`
- `final_write_path=core_proposal_required`
- `direct_wordpress_write=false`
- `comment_publication_policy=operator_review_only_no_comment_publish`
- `comment_status_unchanged=true`

## Notes

The in-app browser did not have a WordPress login session, so the click-level
smoke used a short-lived local WordPress auth cookie generated through WP-CLI
and injected into an isolated Playwright browser context. The temporary cookie
file was deleted after the run.
