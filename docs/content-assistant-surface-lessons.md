# Content Assistant Surface Lessons

Status: active guidance for Toolbox product-surface work.

This note records what `npcink-toolbox` may absorb from
`npcink-content-assistant` without crossing the Toolbox boundary.

## Absorb

Toolbox may absorb these product-surface rules:

1. Keep each default tool panel focused on one operator task.
2. Show the minimum required input first; put low-frequency tuning and debug
   output behind explicit disclosures.
3. Render results as `summary -> detail`: show the result summary, candidates,
   risks, and next step before execution metadata or raw payloads.
4. Use utility copy that tells the operator what happened and what they can do
   next.
5. Preserve source metadata in the default result view when it affects a later
   governed handoff, especially image-source attribution and Unsplash
   `download_location`.
6. Keep static contracts for UI structure, provider metadata preservation,
   secret non-exposure, and suggestion-only write posture.

## Do Not Absorb

Toolbox must not absorb these Content Assistant responsibilities:

- article, comment, or media content lanes;
- local post, term, comment, attachment, SEO, excerpt, slug, schema, or media
  writes;
- `preview -> confirm apply` write flows;
- `post.write`, `media.write`, `comments.manage`, or taxonomy write scopes;
- workflow runtime, queues, retries, leases, router/model control, or preset
  truth;
- batch governance, whole-site scanning, long-running task centers, or content
  indexing lifecycle ownership.

## Toolbox Version

For Toolbox, the equivalent rule is:

`Toolbox surfaces. Core governs. WordPress writes through abilities.`

Toolbox may run configured external research, optional result reading,
Cloud-managed image-source search, Cloud-managed site knowledge, and bounded
fixed-flow planning actions. The result must
remain a suggestion, candidate set, or handoff note until Npcink Governance Core and the
appropriate WordPress ability approve and execute any final write.

## Result Surface Contract

Every operator-facing tool result should keep this order:

1. Summary: what was returned and whether it is usable.
2. Candidates: sources, images, vector matches, or planning sections.
3. Handoff: write posture and next governed action when relevant.
4. Detail: provider metadata, raw payloads, and debugging material in collapsed
   disclosures.

Raw provider responses may be available for debugging when enabled, but they
must not become the default result surface and must not include provider keys.
