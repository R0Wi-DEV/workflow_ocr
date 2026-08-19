# workflow_ocr DevContainer

Development container for this app, blueprinted on the
[official Nextcloud server devcontainer](https://github.com/nextcloud/server/tree/master/.devcontainer).

## Usage

Make sure you have the [VSCode DevContainer](https://code.visualstudio.com/docs/devcontainers/containers)
extension installed. Open this repository and VSCode will ask you if you want to reopen it inside the
container (or use <kbd>F1</kbd> &rarr; *Dev Containers: Reopen in Container*).

The default workspace folder is `/var/www/nextcloud/apps/workflow_ocr`, i.e. this repository, bind mounted
into a Nextcloud installation's `apps` folder.

## What happens on startup

Nextcloud core itself is **not** part of this repository. Instead, on every container start the
[`setup.sh`](./setup.sh) script (wired up as the devcontainer `postStartCommand`) checks whether
`/var/www/nextcloud` already contains a cloned and installed Nextcloud instance:

* **If not**, it:
  1. Reads the `<dependencies><nextcloud max-version="..."/>` entry from this app's
     [`appinfo/info.xml`](../appinfo/info.xml) to determine the highest Nextcloud version this app declares
     compatibility with (currently `35`).
  2. Checks whether the [nextcloud/server](https://github.com/nextcloud/server) repository has a matching
     `stable<VERSION>` branch (e.g. `stable35`). If it does, that branch is used; otherwise it falls back to
     `master`.
  3. Clones that branch as a **shallow, single-branch, single-commit** clone into `/var/www/nextcloud`
     (merged with the already bind-mounted `apps/workflow_ocr` folder).
  4. Installs Nextcloud (`occ maintenance:install`) against the `db` Postgres service and enables this app
     (`occ app:enable workflow_ocr`).
* **If it's already installed**, this is a fast no-op and Apache is simply (re)started.

Since the checkout is shallow, run [`unshallow.sh`](./unshallow.sh) inside the container whenever you need
the full git history of the Nextcloud core checkout (e.g. for `git blame`/`bisect` or to switch branches):

```bash
.devcontainer/unshallow.sh
```

## Credentials

**Nextcloud Admin Login**

Username: `admin` <br>
Password: `admin`

**Postgres credentials**

Host: `db` <br>
Username: `nextcloud` <br>
Password: `nextcloud` <br>
Database: `nextcloud`

## Services

Only two services are used, connected via Docker Compose's normal (default) bridge network - no shared
network namespace tricks:

| Service     | Local port | Description                                    |
|-------------|------------|-------------------------------------------------|
| `nextcloud` | `80`       | Apache + PHP serving the cloned Nextcloud instance |
| `db`        | `5432`     | Postgres database                                |

## Building this app

The devcontainer only takes care of Nextcloud core. To build/install this app's own dependencies, run
(inside the container, from the workspace folder):

```bash
make build
```

Don't forget to enable/refresh the app afterwards via `occ app:enable workflow_ocr` or the Nextcloud web UI.

## Debugging

XDebug is preinstalled and configured to connect to port `9003`. This repository's own
[`.vscode/launch.json`](../.vscode/launch.json) already ships matching debug profiles (works out of the box
since the workspace folder is `apps/workflow_ocr`, mirroring a real Nextcloud `apps` installation).
