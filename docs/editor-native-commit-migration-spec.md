# Editor Native Commit Migration Specification

Status: implemented for the current development line.

Authority: ADR-006.

## Objective

Remove the editor's hidden proposal-intent and post-save execution bridge.
Eligible author-reviewed values must enter normal WordPress editor state and be
persisted only by the author's native Publish or Update transaction.

This migration does not weaken governance for media adoption, audio adoption,
cross-object writes, batch work, or external clients.

## Completion Record

The hard cut removed both editor control routes, private proposal-intent meta,
the Publish listener, post-save Adapter execution, and contextual ALT Core
audit. Native ALT changes now update Gutenberg state only. SEO, external image,
and article-audio adoption stop after Core proposal creation and show the
governance path. No compatibility alias or production data migration was added.

## Eligibility Test

A field is eligible only when every answer is yes:

1. Does it belong to the one article currently open?
2. Is the exact final value visible and editable before commit?
3. Is the value held by the normal WordPress editor entity/block state?
4. Does native Publish or Update persist it without a Toolbox write route,
   Core proposal, Adapter call, or post-save hook?
5. Do native WordPress capabilities and revision/history behavior remain in
   force?
6. Does the action avoid media-library mutation, import, another object,
   global settings, taxonomy creation, background work, and batch execution?

If any answer is no, the operation is not `native_editor_commit`.

## Initial Field Policy

| Field shape | Target path | Notes |
| --- | --- | --- |
| Current post title, excerpt, and body blocks | Native editor commit | Use the standard editor data store; do not call a Toolbox write endpoint. |
| Current block attributes, including inline image ALT stored in the article block | Native editor commit | The value must remain visible in the block and must not update attachment metadata. |
| Existing categories or tags selected in the current article | Native editor commit candidate | Admit only after a focused proof confirms the exact values remain editable and native save performs the only persistence. No term creation. |
| SEO title/description | Native editor commit candidate | Admit only if the fields are registered into the current post's normal editor entity state and native save is the sole writer. Otherwise use Core governance. |
| Featured image relationship | Not in the initial migration | Keep the existing ADR-003 Local Admin Consent proof until a later decision changes it. |
| Image import, attachment ALT/caption, image replacement, or generated-image adoption | Core proposal | Media/cross-object operation; navigate to governance after proposal creation. |
| Article audio generation or adoption | Core proposal | Media adoption and post metadata are not eligible for hidden post-save execution. |
| Batch metadata, batch media, publishing, settings, taxonomy creation | Core proposal | Toolbox admin stops after proposal creation. |

## Removed Legacy Components

The implementation slice must remove, not rename:

- `Editor_Content_Support::PUBLISH_EXECUTION_META_KEY` and registration of
  `_npcink_toolbox_publish_execution_intents`;
- `Rest_Controller` route `/editor/reviewed-action-intents`, its arguments,
  handler, capability map, and route-boundary row;
- JavaScript `PUBLISH_EXECUTION_OPERATIONS`;
- `queuePublishExecutionIntent()`;
- `executeReviewedProposalOnPublish()`;
- `executePendingPublishIntents()` and the Publish-completion effect that
  triggers it;
- SEO, image-adoption, and article-audio calls that queue proposal ids for
  publish-time execution;
- tests and smoke language that treats post-save `approve-and-execute` as an
  accepted editor outcome.

Existing private intent meta should be deleted by an idempotent, versioned
cleanup routine or explicitly left unread and documented for a bounded release
window. It must never be executed after the migration. The implementation task
must choose and test one cleanup policy before release.

## Replacement Flows

### Eligible value

```text
AI suggestion
-> author reviews exact value
-> Toolbox updates normal editor state
-> author edits further if desired
-> native Publish or Update
-> WordPress persists and records normal history
```

There is no Core proposal, Core audit, Adapter request, Toolbox write REST
request, or delayed executor.

### Ineligible value

```text
AI suggestion or reviewed plan
-> operator reviews exact proposal payload
-> Toolbox submits Core proposal
-> Toolbox displays receipt and governance URL
-> operator continues in Core governance
```

Toolbox must not call approve, preflight, execute, or poll-to-execute after
submission.

## Acceptance Cases

1. Review a title or excerpt, modify it again manually, publish, and verify the
   final manual value wins through native editor state.
2. Apply current-block ALT, save, and verify block markup changes while media
   attachment metadata remains unchanged.
3. Verify eligible actions create no Core proposal or Core audit events and
   make no Adapter request.
4. Refresh before saving and verify the reviewed unsaved value is not silently
   committed.
5. Fail a native save and verify no secondary write occurs.
6. Submit image or audio adoption and verify a Core proposal receipt plus
   governance link, with no publish-triggered execution.
7. Submit an admin batch and verify one or more Core proposals are created,
   Toolbox stops, and WordPress state remains unchanged.
8. Verify no production source contains `/editor/reviewed-action-intents`,
   `queuePublishExecutionIntent`, `executePendingPublishIntents`, or editor-side
   `approve-and-execute`.

## Non-Goals

- no new Core classification;
- no Core audit for qualifying native editor commits;
- no generic direct-write endpoint;
- no automatic migration of media or audio adoption into editor state;
- no new workflow runtime, retry mechanism, or post-save hook;
- no change to Adapter's approved external-client execution profiles.

## Executed Rollout Order

1. Add native editor-state application for one low-risk field and its tests.
2. Remove that field's proposal-intent path.
3. Move image/audio adoption to proposal-receipt-and-governance-link behavior.
4. Remove the route, meta, executor, and obsolete smokes as one bounded slice.
5. Run editor, static, local WordPress, and cross-repository gates.

Do not run old and new commit mechanisms for the same field in parallel.
