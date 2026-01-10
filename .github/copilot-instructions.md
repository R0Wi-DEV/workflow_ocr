# Copilot Instructions for workflow_ocr

## Repository Overview

This repository contains the `workflow_ocr` Nextcloud app, which enables flexible OCR (Optical Character Recognition) processing through Nextcloud's workflow engine. The app processes PDF files and images using [OCRmyPDF](https://github.com/ocrmypdf/OCRmyPDF) to add searchable text layers.

## Architecture

### Core Concepts

The app integrates with Nextcloud's [workflowengine](https://github.com/nextcloud/server/tree/master/apps/workflowengine) to allow administrators and users to configure OCR tasks that run as scheduled background jobs.

### Backend Modes

The app supports two OCR processing modes:

1. **Local Mode**: `ocrmypdf` runs on the same server as Nextcloud. The app calls the CLI and interacts via stdin/stdout.

2. **External Backend Mode**: Uses the [`workflow_ocr_backend`](https://github.com/R0Wi-DEV/workflow_ocr_backend) ExApp (External App) running in a separate Docker container connected to the Nextcloud instance. Files and parameters are sent via REST API.

### Key Components

- **OcrProcessors** (`lib/OcrProcessors/`): Handle different file types (PDF, JPEG, PNG)
- **Background Jobs** (`lib/BackgroundJobs/`): Process OCR tasks asynchronously
- **Workflow Operations** (`lib/Operation.php`): Integration with Nextcloud workflow engine
- **Settings** (`lib/Settings/`): Per-workflow and global configuration
- **Notifications**: User feedback for OCR processing results

## Development Requirements

### Critical Context

**IMPORTANT**: This app cannot run standalone. For development, you MUST:

1. Set up a full [Nextcloud Server](https://github.com/nextcloud/server) instance
2. Install this app into the Nextcloud installation
3. Tests must run within a working Nextcloud environment (see CI/CD workflows for examples)

### Target Version Compatibility

Always check `appinfo/info.xml` for the Nextcloud target version:
- If the version exists (e.g., NC 32), check against that stable branch in the Nextcloud Server repo
- If the version doesn't exist yet (e.g., NC 33 not released), check against the `master` branch of the Nextcloud Server repo

Current target: Nextcloud 33 (check `appinfo/info.xml` for updates)

### Technology Stack

- **Backend**: PHP 8.1-8.4
- **Frontend**: Vue 3, Node 24, npm 11.6
- **Build Tools**: rspack, vitest, eslint, stylelint
- **PHP Tools**: composer, phpunit, psalm, php-cs-fixer
- **External Dependencies**: OCRmyPDF CLI, tesseract-ocr

### Required Tools

- `make`
- `node` and `npm` (versions specified in package.json)
- `composer`
- PHP 8.1+ environment
- Web server (Apache recommended)
- XDebug (for debugging)

## Building and Testing

### Build Commands

```bash
# Full build (installs dependencies and compiles)
make build

# Install composer dependencies only
make composer-build

# Install and build frontend
make npm-install && make npm-build
```

### Testing

**IMPORTANT**: Activate the app before running tests: `php occ app:enable workflow_ocr`

```bash
# Run all tests
make test

# PHP unit tests only
make php-unittest

# PHP integration tests only
make php-integrationtest

# JavaScript tests
make js-test

# Generate coverage
make coverage-all
```

### Linting

```bash
# Check all linting (PHP and JS)
make lint

# Auto-fix linting issues
make lint-fix

# PHP only
composer run cs:check
composer run cs:fix
composer run psalm

# JS only
npm run lint
npm run lint:fix
```

## Coding Conventions

### General Principles

1. **Minimal Changes**: Make the smallest possible modifications to achieve the goal
2. **Existing Patterns**: Follow established patterns in the codebase
3. **Nextcloud Compatibility**: Always verify changes against the target Nextcloud Server version code
4. **No Standalone Assumptions**: Remember the app must work within Nextcloud ecosystem

### PHP Conventions

- Follow Nextcloud coding standards (enforced by `php-cs-fixer`)
- Use type hints and return types
- Namespace: `OCA\WorkflowOcr\`
- PSR-4 autoloading from `lib/` directory
- Use dependency injection via Nextcloud's container

### JavaScript/Vue Conventions

- Vue 3 Composition API
- Use Nextcloud Vue components from `@nextcloud/vue`
- Follow ESLint configuration (`@nextcloud/eslint-config`)
- Use l10n for all user-facing strings (`@nextcloud/l10n`)

### File Organization

- **Controllers**: `lib/Controller/` - Handle HTTP requests
- **Services**: `lib/Service/` - Business logic
- **Models**: `lib/Model/` - Data structures
- **Events**: `lib/Events/` - Event definitions
- **Listeners**: `lib/Listener/` - Event handlers
- **Background Jobs**: `lib/BackgroundJobs/` - Async processing
- **Tests**: `tests/` - Unit and integration tests

### Adding New OcrProcessors

To support a new MIME type:

1. Create new class in `lib/OcrProcessors/` implementing `IOcrProcessor`
2. Register in `lib/OcrProcessors/OcrProcessorFactory.php` mapping
3. Add factory method in `registerOcrProcessors()`

## Branching Strategy

The repository follows the same branching strategy as Nextcloud Server:

- **`master`**: Development branch targeting the next unreleased Nextcloud version
- **`stable##`**: Released stable versions (e.g., `stable32` for Nextcloud 32)

When working on code:
- Changes for unreleased NC versions → `master` branch
- Changes for released NC versions → appropriate `stable##` branch
- Check [Nextcloud releases](https://github.com/nextcloud/server/releases) to determine which versions are released

## Nextcloud Integration

### Workflow Engine Integration

The app extends Nextcloud's workflow engine:
- Left side (triggers/conditions): Provided by Nextcloud core
- Right side (OCR settings): App-specific implementation

### Key Nextcloud APIs

- File system operations: `OCP\Files\File`, `OCP\Files\Folder`
- Background jobs: `OCP\BackgroundJob\QueuedJob`
- Events: `OCP\EventDispatcher\IEventDispatcher`
- Settings: `OCP\Settings\ISettings`
- Notifications: Via Nextcloud Notifications app

### Setup Checks

The app implements Nextcloud's Setup Check API to validate:
- OCRmyPDF availability and version
- Backend configuration
- Required dependencies

## Common Tasks

### Running OCR Processing Manually

```bash
cd /var/www/<NEXTCLOUD_INSTALL>
sudo -u www-data php cron.php
```

### Debugging

Use the provided VSCode configuration (`.vscode/launch.json`):
- **Listen for XDebug**: Web server debugging
- **Listen for XDebug (CLI)**: CLI debugging
- **Run cron.php**: Debug background jobs
- **Debug Unittests/Integrationtests**: Debug tests

Configure XDebug to connect on port 9003.

### Checking Logs

Set Nextcloud log level to 0 for detailed debugging:
- Check `nextcloud.log` or use the logreader app
- OCR process output is logged
- Background job execution is logged

## Known Limitations

- Only PDF (`application/pdf`) and images (`image/jpeg`, `image/png`) are supported
- All outputs are PDF files
- PDF metadata may not be preserved (OCRmyPDF limitation)
- No batch processing mechanism (use "tag assigned" trigger for existing files)
- File versions can restore original files if needed

## External Resources

- [OCRmyPDF Documentation](https://ocrmypdf.readthedocs.io/)
- [Nextcloud Server Repository](https://github.com/nextcloud/server)
- [Nextcloud Workflow Engine](https://github.com/nextcloud/server/tree/master/apps/workflowengine)
- [Nextcloud AppAPI](https://docs.nextcloud.com/server/latest/admin_manual/exapps_management/AppAPIAndExternalApps.html)
- [workflow_ocr_backend](https://github.com/R0Wi-DEV/workflow_ocr_backend)

## Important Notes for AI Assistants

1. **Never assume standalone operation** - All development and testing requires a Nextcloud instance
2. **Check Nextcloud Server code** - Always verify implementations against the target Nextcloud version
3. **Respect branching strategy** - Ensure changes are compatible with the target Nextcloud version
4. **Follow existing patterns** - The codebase has established patterns for controllers, services, and processors
5. **Test within Nextcloud** - Tests cannot run in isolation; they need the Nextcloud environment
6. **Consider both backends** - Changes may affect both local CLI and external backend processing modes
