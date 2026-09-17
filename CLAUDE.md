# CLAUDE.md — workflow_ocr

Nextcloud app that runs OCR on files via the Nextcloud workflow engine. Companion
repo: [`workflow_ocr_backend`](https://github.com/R0Wi-DEV/workflow_ocr_backend) (Python
ExApp). Both repos are versioned in lock-step and share a REST contract — see
`.claude/skills/sync-backend-contract/`.

## Critical constraints

- **Most of `tests/Unit/` runs standalone.** `make php-unittest-standalone`
  (bootstrap `tests/bootstrap.standalone.php`, config `phpunit.standalone.xml`) needs
  only `composer install` — no Nextcloud checkout — because it resolves `OCP\*`/`NCU\*`
  via the `nextcloud/ocp` composer dependency instead of a real NC core, with a handful
  of small polyfills in `tests/Unit/TestUtils/` for legacy/other-app classes
  `nextcloud/ocp` doesn't ship (`OC\User\NoUserException`, `OC\Notification\Notification`,
  `OCA\Files_Versions\Versions\*`, `OCA\AppAPI\PublicFunctions`). Seven files that touch a
  real, non-trivial Nextcloud implementation (not just an interface) are excluded via
  `#[Group('nextcloud-full')]` and still run only under `phpunit.xml`:
  `AppInfo/ApplicationTest.php`, `OcrProcessors/OcrProcessorFactoryTest.php`,
  `composer/AutoloadTest.php` (all construct a real `OCP\AppFramework\App`, needing
  `\OC::$server` and Nextcloud's internal DI container), `Wrapper/ViewFactoryTest.php`
  (real `OC\Files\View`), `BackgroundJobs/ProcessFileJobTest.php` and
  `Listener/RegisterFlowOperationsListenerTest.php` (call real OCP methods that
  internally use `OCP\Server::get()`/`\OC::$server`), and `Notification/NotifierTest.php`
  (real `OC\Notification\Notification`, whose validation logic is too much to safely
  polyfill). In CI, only the coverage workflow (`.github/workflows/sonarqube.yml`) still
  runs the full, NC-bootstrapped unit suite; `phpunit.yml`'s other jobs use the
  standalone suite instead.
- **`tests/Integration/` and the full `tests/Unit/` suite (`phpunit.xml`) still need a
  full Nextcloud checkout.** Both boot Nextcloud via `tests/bootstrap.php` and need a
  Nextcloud checkout with this app installed under `apps/workflow_ocr` and enabled
  (`php occ app:enable workflow_ocr`). Use the devcontainer (`.devcontainer/`) or mirror
  `.github/workflows/phpunit*.yml`. JS tests (`vitest`) also run without Nextcloud.
- **Every change must be considered against both backend modes** (local CLI and remote
  ExApp). A change to settings, CLI arguments, or processors usually touches both paths.
- **Target Nextcloud version comes from `appinfo/info.xml`** (`<nextcloud min/max>`),
  currently **NC 36**. Verify Nextcloud API usage against `stable36` of
  `nextcloud/server`, or `master` when that branch does not exist yet.
- `.github/copilot-instructions.md` covers the same ground for GitHub Copilot. If you
  change conventions here, keep that file in sync; where the two disagree, this file and
  the actual repo state win.

## Stack

PHP 8.2–8.5 (composer platform pin 8.4, psalm `phpVersion` 8.2) · Vue 3 · Node ^24 /
npm ^11.6 · rsbuild + vitest · phpunit 12 · psalm 6.4 · php-cs-fixer via
`nextcloud/coding-standard` · `nextcloud/ocp` for OCP stubs and (as of the standalone
unit suite) real `OCP\*`/`NCU\*` symbols at test-run time — an unpinned `dev-master`
dependency, see "Critical constraints".

Namespace `OCA\WorkflowOcr\`, PSR-4 from `lib/`. Frontend sources in `src/`, built into
`js/` by rsbuild (`make npm-build`).

## Architecture

### Processing flow

```
workflowengine event
  └─ lib/Operation.php (ISpecificOperation)      matches rule, resolves the file
      └─ IJobList->add(ProcessFileJob, [fileId, uid, settings])
          └─ lib/BackgroundJobs/ProcessFileJob.php  (QueuedJob, runs in cron)
              └─ lib/Service/OcrService.php         orchestration
                  ├─ IOcrProcessorFactory->create($mimeType)
                  │   └─ IOcrProcessor->ocrFile(File, WorkflowSettings, GlobalSettings)
                  └─ post-processing: new file version, tags add/remove, sidecar .txt,
                     notification, TextRecognizedEvent
```

- `IProcessingFileAccessor` (`ProcessingFileAccessor`, a process-wide singleton) marks the
  file currently being written so `Operation::onEvent` ignores the write the OCR itself
  causes. Without it the app would re-trigger on its own output.
- `OcrProcessorBase::ocrFile` handles file pre-processing (PNG alpha-channel removal via
  Imagick) and resource cleanup; subclasses implement only `doOcrProcessing()`.

### Backend mode selection

`OcrProcessorFactory` holds two mime→processor maps and picks one per call from
`IOcrBackendInfoService::isRemoteBackend()`, which is true only when the `app_api` app is
enabled **and** the `workflow_ocr_backend` ExApp is registered and enabled.

| MIME | local | remote |
| --- | --- | --- |
| `application/pdf` | `Local\PdfOcrProcessor` | `Remote\WorkflowOcrRemoteProcessor` |
| `image/jpeg`, `image/png` | `Local\ImageOcrProcessor` | `Remote\WorkflowOcrRemoteProcessor` |

Local processors shell out to `ocrmypdf` through `ICommand`; the remote processor posts
to the ExApp through `IApiClient` → `IAppApiWrapper::exAppRequest`.

### Settings

- `Model\WorkflowSettings` — per-workflow, serialized as JSON by the Vue component
  (`src/components/WorkflowOcr.vue`) and validated in `Operation::validateOperation`
  via `WorkflowSettings::canConstruct`.
- `Model\GlobalSettings` — admin-wide (`processorCount`, `timeout`), stored by
  `GlobalSettingsService`, edited by `src/components/GlobalSettings.vue`.
- `OcrProcessors\CommandLineUtils` turns both into the `ocrmypdf` argument string. It is
  the **single** place where arguments are built, for local CLI and remote alike (the
  remote backend re-parses that string into `ocrmypdf` kwargs).

### Security-sensitive code

The `ocrmypdf` argument string is executed as a shell string locally. Two guards exist and
must both be kept:

- `LANGUAGE_CODE_REGEX` allow-list, duplicated in `Model\WorkflowSettings` (input
  validation) and `OcrProcessors\CommandLineUtils` (defense in depth for workflows stored
  before validation existed).
- `CommandLineUtils::escapeCustomCliArgs` strips `&&` and `;` from user-supplied
  `customCliArgs`.

Never widen these without a test in `tests/Unit/OcrProcessors/CommandLineUtilsTest.php`
and `tests/Unit/Model/WorkflowSettingsTest.php`.

### Wrappers

`lib/Wrapper/` exists purely so untestable globals can be mocked: `ICommand`
(shell), `IFilesystem` / `IViewFactory` / `IView` (NC filesystem), `IPhpNativeFunctions`
(`fopen` etc.), `IAppApiWrapper` (AppAPI). **Do not call PHP globals or AppAPI directly
from services or processors — go through a wrapper**, and add one if a new global is
needed.

`ICommand` and the local processors are registered with `shared = false` in
`Application::register` / `OcrProcessorFactory::registerOcrProcessors` (bug #43): a shared
`Command` object is reused across OCR runs and breaks. Keep `false`.

### HTTP routes (`appinfo/routes.php`)

| Verb | URL | Controller |
| --- | --- | --- |
| GET / PUT | `/globalSettings` | `GlobalSettingsController` |
| GET | `/ocrBackendInfo/installedLangs` | `OcrBackendInfoController` |

### Events

`Events\TextRecognizedEvent` is emitted by `EventService` after every successful OCR run,
including when the recognized text is empty. It is public API — changing its shape is a
breaking change for consumer apps (documented in README).

## Commands

Run from the repo root. The Makefile is authoritative — there is no `make unittest` or
`make integrationtest`; the PHP suites are `php-` prefixed.

```bash
make build                 # composer (no-dev) + npm install + rsbuild build
make php-unittest          # phpunit -c phpunit.xml        (tests/Unit)
make php-unittest-standalone # phpunit -c phpunit.standalone.xml (tests/Unit, no NC checkout)
make php-integrationtest   # phpunit -c phpunit.integration.xml (tests/Integration)
make php-test              # both PHP suites
make js-test               # vitest unit + integration
make test                  # php-test + js-test
make lint                  # php -l, php-cs-fixer --dry-run, eslint + stylelint
make lint-fix              # php-cs-fixer fix, eslint/stylelint --fix
make coverage-all          # merged PHP + JS coverage
make appstore              # release tarball (build/artifacts/appstore)

composer run psalm         # static analysis, baseline at tests/psalm-baseline.xml
composer run psalm:update-baseline
npm run watch              # rsbuild dev server for frontend work
```

Before committing, `make lint` **and** `composer run psalm` **and** `make test` must be
clean. Never add to the psalm baseline to silence a new error you introduced.

## Testing conventions

- `tests/Unit/` mirrors `lib/` one-to-one. Collaborators are mocked through their
  interfaces; that is what the `lib/Wrapper/` indirection is for.
- `tests/Integration/` runs against a live Nextcloud. `TestUtils/BackendTestBase.php`
  holds the cases that must pass on **both** backends; `LocalBackendTest` and
  `OcrBackendServiceTest` derive from it. CI runs the suite twice via a
  `backend: [remote, local]` matrix (`.github/workflows/phpunit-integration.yml`) — the
  matrix value names are load-bearing, do not rename them.
- Test PDFs/images live in `tests/Integration/testdata/`.
- JS tests are `src/test/**/*.spec.js` with `@nextcloud/*` mocks in `src/test/__mocks__/`.
- `tests/Unit/TestUtils/` holds standalone-suite-only scaffolding (an `invokePrivate`
  replacement trait, plus polyfills for classes `nextcloud/ocp` doesn't ship) needed
  only because `phpunit.standalone.xml` doesn't have a full Nextcloud core to draw on.
  Mirrors the `tests/Integration/TestUtils/` convention. `nextcloud/ocp` itself ships no
  composer `autoload` section, so `composer.json`'s `autoload-dev` adds a `classmap` over
  `vendor/nextcloud/ocp/{OCP,NCU}/` to make its classes loadable at all, plus a PSR-4
  mapping for `tests/Unit/` (so test-only support classes like
  `tests/Unit/Service/IMetadataVersionWithBackend.php` autoload without a manual require).

## Conventions

- Constructor property promotion for new classes; older classes still use assigned
  properties — match the file you are editing rather than converting it.
- Register every service alias in `lib/AppInfo/Application.php::register`.
- All user-facing strings go through `IL10N::t` (PHP) or `translate` from
  `@nextcloud/l10n` (JS). Do not edit `l10n/` by hand — it is Transifex-managed.
- Vue components use `@nextcloud/vue`; `src/main.js` registers the workflow operator as a
  custom element (`oca-workflow-ocr-settings`).
- Branching follows Nextcloud server: `master` targets the next unreleased NC version,
  `stableNN` for released ones. Backports are automated (`.github/workflows/backport.yml`).
- Bumping the app version means bumping `appinfo/info.xml` **and** the backend repo's
  `appinfo/info.xml` together.

## Tooling in this repo

- `.mcp.json` provides a headless Playwright MCP server (pinned to the `chromium` browser)
  for driving the Nextcloud UI. See `/e2e-verify` for how to use it against a running
  instance.
- `.claude/skills/` — task procedures (`/add-ocr-processor`, `/add-workflow-setting`,
  `/preflight`, `/sync-backend-contract`, `/nextcloud-dev-env`,
  `/bump-nextcloud-version`, `/e2e-verify`).
- `.claude/agents/` — subagents for review, pipeline tracing, test authoring, and
  checking Nextcloud upstream APIs.
