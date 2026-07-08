# GitHub Publishing Runbook

Status: active local publishing reference.

Use this when local commits need to reach GitHub. The preferred path is normal
Git CLI publication. Use `gh` only for GitHub-specific PR metadata, check
inspection, or PR operations that plain `git` cannot perform after a successful
push.

## Standard Path

Before publishing, confirm scope and upstream state:

```bash
git status --short --branch
git diff --stat
git rev-list --left-right --count origin/master...HEAD
```

Verify non-interactive remote access through Git:

```bash
composer git:remote-check
```

Push a dedicated branch, then create the PR:

```bash
git push origin HEAD:refs/heads/codex/<topic>
gh pr create --base master --head codex/<topic> --draft --fill
```

For a local `master` cleanup branch that should publish as a review branch,
prefer pushing `HEAD` to `codex/<topic>` instead of pushing directly to
`master`.

## HTTPS Timeout Diagnostics

`composer git:remote-check` wraps `git ls-remote origin HEAD` with
`GIT_TERMINAL_PROMPT=0` and a timeout. If it exits with code `142` or hangs,
inspect the Git HTTP path before trying alternate publication methods:

```bash
git config --show-origin --get-regexp 'http\.curloptresolve|http\.version|credential\.https://github\.com'
GIT_TRACE=1 GIT_CURL_VERBOSE=1 GIT_TERMINAL_PROMPT=0 perl -e 'alarm 20; exec @ARGV' git ls-remote origin HEAD
curl -I --connect-timeout 20 https://github.com
curl -I --connect-timeout 20 https://api.github.com
```

If a stale repo-local fixed resolve is present, remove it and retry the remote
gate:

```bash
git config --unset http.curloptresolve
composer git:remote-check
```

If `https://github.com` times out but `https://api.github.com` works, GitHub's
REST API may still be reachable while normal Git HTTPS remains blocked. Do not
treat API reachability as proof that `git push` can work.

## China Mainland VPN / Proxy Baseline

On workstations inside mainland China, a browser VPN may not automatically
cover terminal Git traffic. If direct `git ls-remote origin HEAD`,
`git push`, `curl https://github.com`, or `nc github.com 443` times out, first
check whether the local proxy can establish a GitHub HTTPS tunnel:

```bash
curl -x http://127.0.0.1:7890 -I --connect-timeout 10 --max-time 20 https://github.com
```

Expected evidence is `HTTP/1.1 200 Connection established` followed by a
GitHub HTTP response such as `HTTP/2 200`. If that works, configure GitHub-only
Git proxy settings:

```bash
git config --global http.https://github.com.proxy http://127.0.0.1:7890
git config --global https.https://github.com.proxy http://127.0.0.1:7890
git config --global --get-regexp '^(http|https)\.https://github\.com\.proxy'
```

Then verify the Git path before pushing:

```bash
GIT_TERMINAL_PROMPT=0 git ls-remote origin HEAD
git push -u origin codex/<topic>
```

If the local proxy port changes, update both Git config entries. If this
workstation no longer needs the proxy, remove both entries:

```bash
git config --global --unset http.https://github.com.proxy
git config --global --unset https.https://github.com.proxy
```

This keeps ordinary publishing on the Git CLI path. `gh auth setup-git` can
provide credentials for HTTPS Git, but it does not route Git traffic through a
VPN or proxy by itself.

## SSH Fallback

When Git HTTPS is blocked, test SSH authentication:

```bash
ssh -o BatchMode=yes -o ConnectTimeout=20 -T git@github.com
ssh -o BatchMode=yes -o ConnectTimeout=20 -T -p 443 git@ssh.github.com
```

Then push with an explicit SSH target:

```bash
git push git@github.com:muze-page/npcink-workflow-toolbox.git HEAD:refs/heads/codex/<topic>
git push ssh://git@ssh.github.com:443/muze-page/npcink-workflow-toolbox.git HEAD:refs/heads/codex/<topic>
```

If GitHub says permission is denied to a deploy key, the active SSH key is not a
writable key for this repository. Install or select a user key or deploy key
with write access for `muze-page/npcink-workflow-toolbox`; do not push with an
unrelated repository deploy key.

## Current Workstation Notes

The 2026-06-30 publishing attempt proved:

- `gh auth status` succeeded for account `muze-page`;
- `gh auth setup-git` completed during historical credential repair, but
  current ordinary Git work should still use command-line `git`;
- `gh repo view muze-page/npcink-workflow-toolbox` confirmed default branch
  `master`;
- `composer git:remote-check` timed out while `git ls-remote origin HEAD` tried
  to reach `github.com:443`;
- `curl https://github.com` timed out, while `curl https://api.github.com`
  succeeded;
- a stale repo-local `http.curloptresolve` was removed from `.git/config`;
- SSH authenticated, but the available key identified as a deploy key for a
  different repository and `git push` was rejected with deploy-key permission
  denial.

The 2026-07-08 connector-diagnostics publishing attempt added one more
workstation fact:

- direct HTTPS Git and `nc github.com 443` timed out;
- `curl -x http://127.0.0.1:7890 https://github.com` returned a valid GitHub
  response through the local proxy;
- setting GitHub-only Git proxy config for `http://127.0.0.1:7890` restored
  `git ls-remote origin HEAD` and `git push -u origin
  codex/connector-status-diagnostics-evaluation`;
- SSH remained readable but not writable because the active key was a deploy
  key without write permission for this repository.

Until either Git HTTPS connectivity is restored or a writable SSH key is
selected for this repository, leave commits local and report:

- ahead/behind counts;
- the exact blocking command and error;
- the local commit subjects that still need to be published.

## Fallback Boundary

Do not use GitHub's Git Data API for normal publishing. It can create remote
commit objects that do not match the local commit SHA. Use it only as an
explicitly approved emergency fallback, and record that the remote branch will
not be a direct push of the local object.
