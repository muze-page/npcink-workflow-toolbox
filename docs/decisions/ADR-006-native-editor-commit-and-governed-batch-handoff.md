# ADR-006: Separate Native Editor Commit From Governed Batch Handoff

## Status

Accepted

## Date

2026-07-11

## Context

Toolbox exposes two operator surfaces that can both begin with AI output but do
not have the same commit mechanism:

- an article-editor sidebar, where the author reviews values inside the current
  article and later uses WordPress Publish or Update; and
- plugin-admin batch surfaces, where one action may affect multiple objects or
  perform media, metadata, taxonomy, or publishing writes.

Treating every reviewed editor value as a Core proposal adds governance state
to the author's ordinary editing transaction. Treating every Toolbox button as
locally authorized would instead let batch and hidden writes bypass Core. The
boundary therefore cannot be inferred from UI location or button wording.

The platform also needs one explicit owner for reusable workflow definitions
and one stable channel-adapter rule. Without those decisions, Toolbox and AI
clients could drift into parallel workflow registries.

## Decision

### Native editor commit

An editor-side action is a `native_editor_commit`, outside the Core proposal
path, only when all of these conditions hold:

- it targets the one article currently open in the editor;
- every final value is visible and editable in normal editor state before the
  author commits it;
- the present author performs the normal WordPress Publish or Update action;
- persistence happens only through that native WordPress save transaction and
  its normal capability checks;
- Toolbox does not call Core, Adapter, a direct write REST route, or a hidden
  post-save executor to persist the value;
- there is no delayed `save_post` or `transition_post_status` follow-up write;
- it does not import or mutate media, write another object, change global
  settings, create taxonomy, or execute a batch.

WordPress revisions, author identity, modified time, and ordinary post history
remain the record. Core proposal or audit records are not required. This is not
a fifth Core operation classification: the action never enters the governed AI
write path.

An editor sidebar is not automatically exempt. If any condition above is
missing, classify the operation normally. Cross-object, hidden, external,
background, destructive, incomplete-preview, or batch writes remain
`core_proposal_required`.

### Governed batch handoff

Plugin-admin batch execution surfaces may collect inputs, show previews, and
build proposal-ready plans. When the operator submits selected writes, Toolbox
creates Core proposals and then stops. It links or navigates the operator to the
Core governance surface; Toolbox does not approve, execute, poll-to-execute, or
perform the WordPress writes.

### Adapter contract

`npcink-ai-client-adapter` owns a generic external AI-client contract, with
OpenClaw as the first and priority implementation. The existing OpenClaw REST
namespace may remain as a compatibility surface, but it does not narrow the
product to OpenClaw. A materially different channel should become a separate
adapter plugin only when its authentication, transport, lifecycle, or durable
state cannot conform cleanly to the shared contract.

### Workflow definition ownership

`npcink-abilities-toolkit` is the canonical owner of reusable, versioned,
static workflow definitions. Toolbox projects those definitions as fixed
buttons. Adapter projects them into external AI-client channels. Core may store
definition references and governance evidence, but none of these consumers may
become a second workflow registry or workflow runtime.

### Independent plugin boundary

`wp-magick-toolbox` and `npcink-workflow-toolbox` are independent products.
Neither is a legacy name, migration source, compatibility alias, or release
gate for the other. Npcink platform maps and cross-repository quality matrices
must not include `wp-magick-toolbox` as a member of the Npcink project family.

## Consequences

- The commit mechanism and target scope, not the visual surface, determine the
  governance path.
- Normal author-reviewed editor changes remain as simple as ordinary WordPress
  editing and do not create redundant Core records.
- Batch and external writes remain reviewable, approvable, and auditable in
  Core.
- Toolkit definitions can be reused by buttons and AI clients without parallel
  registries.
- The former editor proposal-intent and post-save execution bridge was removed
  rather than reinterpreted as `native_editor_commit`. Native editor state and
  Core proposal handoff are now separate paths.
- Existing OpenClaw-specific route names are compatibility details, not the
  Adapter's long-term product boundary.

## Alternatives Considered

### Route every editor save through Core

Rejected. The author already reviews and commits the current article through
WordPress's native transaction, so a second approval and audit path duplicates
the editor's source of truth.

### Exempt every editor-sidebar action

Rejected. UI location does not prevent hidden, cross-object, media, or batch
writes.

### Let each channel own workflow definitions

Rejected. It creates divergent versions and makes parity between fixed buttons
and external AI clients unverifiable.

### Treat wp-magick-toolbox as a predecessor or sibling package

Rejected. The two plugins have no product, migration, compatibility, or release
relationship.
