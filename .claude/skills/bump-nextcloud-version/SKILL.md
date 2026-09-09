---
name: bump-nextcloud-version
description: Create the next stableNN branch and bump master to the next Nextcloud dev version, once nextcloud/server has cut its own stableNN branch. Use only when explicitly asked to prepare workflow_ocr for a new Nextcloud major version.
disable-model-invocation: true
---

Only run this when Nextcloud has actually cut a new `stableNN` branch on
`nextcloud/server` for the version this app's `master` currently targets. Verify with
`git ls-remote https://github.com/nextcloud/server stableNN` before doing anything —
if it doesn't exist yet, there is nothing to do.

This is **two separate changes**, each its own commit (each has historically also been its
own PR): first branch and pin `stableNN`, then bump `master` to `NN+1`. Reference commits
for the most recent cycle: `7f81860` (pin `stable35`) and `923b351` (`master` → NC36).
Earlier cycles: `38f3894` (`stable34`), `9a93299` (`master` → NC34), `971c0f0`/`cc8180e`
(`master` → NC33). Read one of these with `git show` before starting if anything below is
unclear — they are the ground truth this skill was written from.

## 0. Preconditions

- `appinfo/info.xml`'s `<nextcloud min-version max-version="NN"/>` — min and max are
  always the same single value — is the version `master` currently develops against. Call
  it `NN`; the new branch is `stableNN`, the new master target is `NN+1`.
- `stableNN` does not already exist in this repo:
  `git ls-remote origin stableNN` returns nothing.

## 1. Create and pin `stableNN`

```bash
git fetch origin master
git checkout -b stableNN origin/master
```

On `stableNN` only, change every `server-versions: ['master']` / `'dev-master'` reference
to the new branch:

| File | Change |
| --- | --- |
| `.github/workflows/phpunit.yml` | all three `server-versions: ['master']` matrix entries (the sqlite/php-matrix job, the mysql job, the pgsql job) → `['stableNN']` |
| `.github/workflows/phpunit-integration.yml` | `server-versions: ['master']` → `['stableNN']` |
| `.github/workflows/sonarqube.yml` | `server-versions: ['master']` → `['stableNN']` |
| `.github/workflows/psalm.yml` | `ocp-version: [ 'dev-master' ]` → `[ 'dev-stableNN' ]` |
| `composer.json` | `"nextcloud/ocp": "dev-master"` → `"nextcloud/ocp": "dev-stableNN"` |
| `.github/copilot-instructions.md` | the worked example and the "Current target: Nextcloud NN" line → NN |

Then regenerate the lockfile against the newly pinned OCP stub package:

```bash
composer update nextcloud/ocp
```

Expect `composer.lock`'s `nextcloud/ocp` entry to change `reference`/`time`/`source` and
lose `"default-branch": true`. `composer update` may also reformat unrelated
vendor-adjacent generated files (e.g. `lib/OcrProcessors/Remote/Client/ObjectSerializer.php`)
via whatever `nextcloud/coding-standard` version comes along — that is an expected side
effect, not something to hand-edit or revert.

`appinfo/info.xml` normally needs **no change** on `stableNN`: master's `<nextcloud>`
version already reads `NN` (it was set by the *previous* bump cycle in step 2 below), so the
new branch inherits the correct value at cut time. Only touch it if that assumption doesn't
hold — e.g. the branch is being cut before the previous cycle's master bump ever landed.

Commit message pattern: `NC{NN} compat: pin CI workflows and composer to stable{NN}`.

### Also check, opportunistically — not every cycle

Nextcloud's supported PHP range shifts with each major version. If the new NC target drops
or adds a supported PHP version (check `nextcloud/server`'s own `composer.json` on
`stableNN`), update together, in the **same** commit as this section:

- `.github/workflows/lint.yml` and `phpunit.yml`'s `php-versions` matrices
- `composer.json`'s `config.platform.php`
- `appinfo/info.xml`'s `<php min-version max-version>`

These moved together for the NC33→34 cycle (see `9a93299`) but not for NC35→36 — only touch
them when the target PHP range actually changed, and do it as part of step 2
(`master is now NC{NN+1}`) since that is where it happened in `9a93299`.

## 2. Bump `master` to `NN+1`

Switch back to `master` (not `stableNN`) for this part:

- `appinfo/info.xml` — bump `<version>` (`1.NN.0` → `1.(NN+1).0`) and
  `<nextcloud min-version max-version="NN+1"/>`.
- `README.md` — the Nextcloud version badge
  (`img.shields.io/badge/Nextcloud-NN-orange`) → `NN+1`.
- Leave CI matrices and `composer.json`'s `nextcloud/ocp` alone here — `master` keeps
  tracking `dev-master` / `server-versions: ['master']`, since `NN+1` isn't released yet.

Commit message pattern: `master is now NC{NN+1}`.

## 3. Verify

- Run `/preflight` on both branches. `stableNN` needs a Nextcloud checkout actually on
  `stableNN` (not `master`) — see `/nextcloud-dev-env`.
- Push `stableNN` and check its first CI run actually resolves the pinned matrices (read
  the Actions run, not just the YAML — a typo'd branch name in the matrix fails silently
  as "branch not found" deep in a checkout step).
- `.devcontainer/setup.sh` needs **no change** — it already derives which `nextcloud/server`
  branch to clone from `<nextcloud max-version>` in `appinfo/info.xml` at container start,
  so it picks up both the `stableNN` value (unchanged) and the new `master` value (`NN+1`,
  falling back to `nextcloud/server`'s own `master` if `stable{NN+1}` doesn't exist there
  yet) automatically.
- `backport.yml`, `dependabot-approve.yml`, `build.yml`, `build_release.yml`,
  `lint-fix.yml`, `composer-update.yml` need **no branch-specific edits** — they are generic
  and start working against `stableNN` (e.g. for backports) as soon as it exists.

## 4. Companion repo

`workflow_ocr_backend` goes through an equivalent two-step cycle, on its own schedule —
often the same day, but always as its own separate operation with a different shape (see
its `bump-nextcloud-version` skill). Check whether it has already been done:

```bash
git ls-remote https://github.com/R0Wi-DEV/workflow_ocr_backend stableNN
```

If not, do it there too (or tell the user it still needs doing — it is a different repo and
this skill does not reach into it).
