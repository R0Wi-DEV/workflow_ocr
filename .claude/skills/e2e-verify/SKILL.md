---
name: e2e-verify
description: Drive a real Nextcloud instance through the browser via the Playwright MCP server to confirm a workflow_ocr change actually works end-to-end - bring up the dev environment, log in, exercise the Workflow OCR admin settings and/or a Flow rule. Use when asked to verify a UI/UX change in the browser, or before claiming a change "works" beyond unit/integration tests.
allowed-tools: Bash, Read, Grep, Glob, mcp__playwright__browser_navigate, mcp__playwright__browser_snapshot, mcp__playwright__browser_click, mcp__playwright__browser_type, mcp__playwright__browser_fill_form, mcp__playwright__browser_select_option, mcp__playwright__browser_file_upload, mcp__playwright__browser_wait_for, mcp__playwright__browser_console_messages, mcp__playwright__browser_take_screenshot, mcp__playwright__browser_close
---

Unit and integration tests (`/preflight`) prove the code is correct in isolation. This
skill proves the browser-facing result — an admin settings page, a Flow rule, an OCR
run's visible outcome — actually renders and works, by driving a real Nextcloud instance
with the `.mcp.json` Playwright MCP server (`--headless --browser chromium`).

## 0. Precondition: a running Nextcloud instance

This needs an actual Nextcloud reachable over HTTP — it is not something a bare CLI
session can fake. Check first:

```bash
curl -sf http://localhost/status.php && echo REACHABLE || echo DOWN
```

**If REACHABLE**, skip to step 2.

**If DOWN**, bring one up. See `/nextcloud-dev-env` for the full picture; short version:

```bash
docker info >/dev/null 2>&1 && echo "docker available" || echo "no docker daemon"
```

- **Docker available** (local machine, a devcontainer host): start the devcontainer's
  compose stack and run its setup:
  ```bash
  docker compose -f .devcontainer/docker-compose.yml up -d
  docker compose -f .devcontainer/docker-compose.yml exec nextcloud \
    /var/www/nextcloud/apps/workflow_ocr/.devcontainer/setup.sh
  ```
  `setup.sh` is idempotent — clones `nextcloud/server`, installs against Postgres with
  `admin`/`admin`, and enables `workflow_ocr`. It also runs `composer install --no-dev`;
  for anything test-related run `composer install` (with dev deps) again inside the
  container afterwards.
- **No docker daemon** (this is common in a plain Claude Code web/CLI session): this
  skill cannot bring up an instance by itself. Say so plainly — "no docker daemon, e2e
  verification not run" — rather than skipping the check silently or guessing at the
  result. Do not attempt to fake success against unit/integration tests instead; that is
  `/preflight`'s job, not this skill's.

If the change needs the **remote backend** instead of local CLI OCR, follow
`/nextcloud-dev-env`'s "Switching to the remote backend" section before driving the UI.

## 1. Make sure the app under test is current

If you changed PHP: `make build` (or at least `composer install`) and re-run
`php occ app:enable workflow_ocr` inside the container. If you changed Vue/JS sources
without an npm build step already run, `make npm-build` first — the UI serves `js/`, not
`src/`.

## 2. Log in

Navigate and log in as the devcontainer's seeded admin (`admin`/`admin`, or
`NEXTCLOUD_ADMIN_USER`/`NEXTCLOUD_ADMIN_PASSWORD` if the instance overrides them):

1. `browser_navigate` to `http://localhost/index.php/login`
2. `browser_snapshot` to get element refs, `browser_type` username/password,
   `browser_click` the submit button
3. `browser_wait_for` the Files app or dashboard to confirm the login succeeded — a
   login form still on screen means the credentials or instance state is wrong, not that
   the app under test is broken. Stop and report that distinctly.

## 3. Drive the scenario the change actually touches

Pick the shortest path that exercises the change:

- **Global settings change** (`GlobalSettings.vue` / `GlobalSettingsController`) →
  Administration settings → Workflow OCR (`/settings/admin/workflow_ocr` fragment via the
  admin settings nav). Snapshot the rendered form, exercise the changed control, save,
  reload, confirm the value persisted.
- **Per-workflow setting** (`WorkflowOcr.vue` / `Operation.php`) → Administration or
  Personal settings → Flow. Add a new flow rule, pick "Convert to searchable PDF via OCR"
  (or the current operation label), configure it, save. Confirm the custom element
  (`oca-workflow-ocr-settings`) renders without console errors
  (`browser_console_messages`).
- **A full OCR run** (processor, post-processing, notifications) → after wiring a Flow
  rule, upload a file from `tests/Integration/testdata/` via `browser_file_upload` (or
  the Files app's own upload), then run cron so the queued `ProcessFileJob` executes:
  ```bash
  docker compose -f .devcontainer/docker-compose.yml exec nextcloud \
    sudo -u www-data php /var/www/nextcloud/cron.php
  ```
  Reload the Files app and confirm the expected result (new file version, tag, sidecar
  `.txt`, notification) — whichever the change claims to produce.

Use `browser_snapshot` (accessibility tree, cheap) for locating elements and asserting
text/state; reach for `browser_take_screenshot` only when a visual artifact is worth
keeping (e.g. to attach to a PR description).

## 4. Check for silent breakage

`browser_console_messages` after each significant navigation — a page that "looks right"
but logged a Vue warning or a failed XHR is not a pass. `browser_network_requests` if a
save/submit seems to hang, to see whether the request actually round-tripped.

## Reporting

State plainly: which scenario was driven, what was observed (with the console/network
check explicitly called out, not just "looked fine"), and — if step 0 came back DOWN
with no docker — that e2e verification did not run and why. Never report a change as
browser-verified without having actually driven the browser this session.
