---
name: preflight
description: Run the full workflow_ocr quality gate (lint, psalm, PHP unit + integration tests, JS tests) and fix what it reports. Use before committing, before opening a PR, or when asked whether the change is ready.
allowed-tools: Bash, Read, Edit, Grep, Glob
---

The repo's own gate, in the cheapest-first order. Stop at the first failure, fix it, then
restart from that step.

## 1. Lint and static analysis (no Nextcloud needed)

```bash
make lint
composer run psalm
```

- `make lint` runs `php -l`, `php-cs-fixer --dry-run --diff`, `eslint`, `stylelint`.
- Formatting-only failures: `make lint-fix`. Do not hand-edit what the fixer owns.
- Psalm errors: fix the code. **Never** run `composer run psalm:update-baseline` to hide an
  error you just introduced — the baseline is only for pre-existing debt.

## 2. JS tests (no Nextcloud needed)

```bash
make js-test
```

## 3. PHP tests (Nextcloud required)

```bash
make php-unittest
make php-integrationtest
```

These boot Nextcloud through `tests/bootstrap.php`. If they fail with a bootstrap or
autoload error rather than an assertion, the environment is wrong, not the code — check:

- the repo is at `<nextcloud>/apps/workflow_ocr`,
- `php occ app:enable workflow_ocr` has been run,
- `composer install` (with dev deps) has been run in the app directory.

See `/nextcloud-dev-env` if no environment exists yet.

## 4. Cross-cutting checks the tools do not make

- Did the change touch OCR settings, `CommandLineUtils`, or a processor? Then confirm both
  the **local** and **remote** backend paths still behave correctly — CI runs the
  integration suite twice over `backend: [remote, local]`.
- Did it change anything the ExApp also implements (endpoints, field names, the
  `ocrmypdf_parameters` string)? Run `/sync-backend-contract`.
- New user-facing string? It must go through `IL10N::t` / `@nextcloud/l10n`, and `l10n/`
  stays untouched.

## Reporting

State which steps ran, which passed, and — explicitly — which could not run and why
(a missing Nextcloud instance is a legitimate "not run", never a silent pass).
