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
- inline admin CSS or JS emitted from PHP;
- raw `<script>` or `<style>` tags in PHP admin views.

When WordPress.org sends a review email, decode the current top-level message,
extract every cited file and line, fix the whole pattern class, and add a local
guard when the pattern is statically checkable.
