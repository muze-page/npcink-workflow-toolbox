# WordPress.org Release Gate

Status: active release gate.

Before uploading this plugin to WordPress.org, run:

```sh
composer release:verify
```

This release gate exists because functional tests and local smoke tests can pass
while WordPress.org rejects the package for review-policy issues.

The local `check:wporg` guard blocks recurring review problems:

- direct `wp-admin/includes/*` path construction, except the common
  `upgrade.php` activation helper for `dbDelta()`;
- admin request parameters read directly from `$_GET`;
- unsanitized request reads such as `FILTER_UNSAFE_RAW`;
- inline admin CSS or JS emitted from PHP;
- raw `<script>` or `<style>` tags in PHP admin views.
- a WordPress.org-facing `readme.txt` Contributors line that omits the
  submitting account `muze233`.

The Plugin Check gate must run with the workstation WP-CLI path that actually
exists:

```sh
WP_CLI_BIN=/opt/homebrew/bin/wp composer plugin-check:release
```

Do not rely on a passing `composer test:all` or `composer check:wporg` alone
for WordPress.org review readiness. `composer test:all` protects the internal
Toolbox contracts; `composer check:wporg` protects local recurring static
patterns; Plugin Check is the external review-policy mirror.

## Brand And Submitter Gate

The public plugin name intentionally remains `Npcink Workflow Toolbox` because
this repository is the Npcink product surface, and the first-version runtime
contracts keep `npcink-toolbox` route and ability ids for compatibility.

Before replying to a WordPress.org trademark/name review question, confirm one
of these non-code facts and state it briefly in the reviewer reply:

- the submitting WordPress.org account is an official Npcink owner account; or
- the plugin should be transferred to an official Npcink owner account; or
- the display name and requested slug must be changed before resubmission.

Do not treat a code-only edit as sufficient for a brand ownership question.
Adding the submitter to `readme.txt` resolves the contributor mismatch only; it
does not prove brand ownership.

## 2026-07-02 Review Feedback Retrospective

WordPress.org pre-review feedback for the 0.1.1 submission identified three
classes of issues:

1. potential confusion around the `Npcink` public name when submitted from
   `muze233`;
2. `filter_input( INPUT_GET, ..., FILTER_UNSAFE_RAW )` request reads, including
   nonce reads before `wp_verify_nonce()`;
3. `readme.txt` listing `npcink` but not the submitting account `muze233`.

The immediate cause was that the release closeout treated
`composer test:all` and the earlier `check:wporg` guard as sufficient
evidence. They were not. The local guard did not include the exact review-email
pattern classes, and the brand ownership question is not statically decidable
from source code.

The correction is:

- fix the current code pattern, not only the cited line numbers;
- add every statically checkable review pattern to `scripts/check-wordpress-org-review-rules.php`;
- run Plugin Check with the real local WP-CLI path before every upload;
- keep brand ownership and submitter identity as an explicit manual release
  checklist item.

When WordPress.org sends a review email, decode the current top-level message,
extract every cited file and line, fix the whole pattern class, and add a local
guard when the pattern is statically checkable.
