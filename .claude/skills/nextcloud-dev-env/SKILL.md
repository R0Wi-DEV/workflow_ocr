---
name: nextcloud-dev-env
description: Bring up or repair a Nextcloud environment for workflow_ocr so PHP unit and integration tests can run, and drive the app manually (cron, occ, remote backend). Use when tests fail to bootstrap, when no Nextcloud instance exists, or when asked how to run the app locally.
---

The app cannot run or be tested outside a Nextcloud installation. Three ways to get one.

## Option 1 — devcontainer (preferred)

`.devcontainer/` is a full setup: Apache + PHP on one service, Postgres 16 on `db`, this
repo bind-mounted at `/var/www/nextcloud/apps/workflow_ocr`.

`postStartCommand` runs `.devcontainer/setup.sh`, which is idempotent and:

1. reads `<nextcloud max-version>` from `appinfo/info.xml`,
2. shallow-clones `nextcloud/server` at `stable<max-version>`, falling back to `master`
   when that branch does not exist upstream yet,
3. installs Nextcloud against Postgres (`admin`/`admin`), runs `composer install --no-dev`,
   and enables the app.

Re-run it by hand after a wipe: `.devcontainer/setup.sh`. Use
`.devcontainer/unshallow.sh` if you need the server's full git history.

Note it installs with `--no-dev`, so before running tests:

```bash
composer install     # with dev dependencies, in the app directory
```

## Option 2 — existing Nextcloud checkout

```bash
cd /var/www/<nextcloud>/apps
git clone https://github.com/R0Wi/workflow_ocr.git workflow_ocr
cd workflow_ocr && make build && composer install
php ../../occ app:enable workflow_ocr
php ../../occ maintenance:mimetype:update-db      # integration tests need this
sudo apt-get install -y ocrmypdf                  # local backend only
```

## Option 3 — mirror CI

`.github/workflows/phpunit.yml` and `phpunit-integration.yml` are the executable spec for a
working environment (server checkout, PHP extensions incl. `imagick`, MariaDB service,
`occ` steps, the HaRP/nginx setup for the remote backend under
`tests/nginx-harp-test.conf.template`). When an environment question is not answered here,
read those files rather than guessing.

## Driving the app

```bash
php occ app:enable workflow_ocr
sudo -u www-data php cron.php        # executes queued ProcessFileJob runs
php occ config:system:set loglevel --value 0 --type integer
```

OCR is asynchronous: a workflow only enqueues `ProcessFileJob`; nothing happens until cron
runs. When "nothing happened", check the job queue and the log before suspecting the
processor.

The UI can be driven headlessly — `.mcp.json` provides a Playwright MCP server. Admin
settings live under Administration settings → Workflow OCR; workflows under
Administration/Personal settings → Flow.

## Switching to the remote backend

`isRemoteBackend()` is true only when `app_api` is enabled **and** the
`workflow_ocr_backend` ExApp is registered and enabled:

```bash
php occ app:enable app_api
php occ app_api:app:register workflow_ocr_backend --force-scopes \
    --info-xml <url-or-path-to-the-backend-repo's-appinfo/info.xml>
php occ app_api:app:list
```

The backend repo's `examples/register-ex-app.sh` holds the same command, and its
`.claude/skills/run-local/` covers getting the container up. Without this, every processor
resolves to the local CLI whatever the backend is doing.
