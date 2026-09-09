---
name: add-ocr-processor
description: Add OCR support for a new MIME type to workflow_ocr by writing an IOcrProcessor, wiring it into OcrProcessorFactory for the local and remote backends, and adding tests. Use when asked to support a new file type or add/modify an OCR processor.
---

Support for a MIME type is per backend mode. `OcrProcessorFactory` keeps two maps and
picks one from `IOcrBackendInfoService::isRemoteBackend()`, so decide up front whether the
new type is local-only, remote-only, or both.

## 1. Write the processor

Create `lib/OcrProcessors/Local/<Name>OcrProcessor.php` (or `Remote/`) extending
`OcrProcessorBase`. Implement only:

```php
protected function doOcrProcessing($fileResource, string $fileName, WorkflowSettings $settings, GlobalSettings $globalSettings): OcrProcessorResult
```

`OcrProcessorBase::ocrFile` already opens the file, strips the PNG alpha channel, and
closes the resource — do not reimplement that. Return
`new OcrProcessorResult($success, $fileContent, $recognizedText, $exitCode, $errorMessage)`.

For a local processor, look at `Local\OcrMyPdfBasedProcessor` first: it likely already does
what you need. Build the command line **only** through `ICommandLineUtils::getCommandlineArgs`
— never concatenate user input into a shell string yourself. Run the command through
`ICommand`, never `exec`/`shell_exec`.

## 2. Register the mapping

In `lib/OcrProcessors/OcrProcessorFactory.php`, add the MIME type to `$localMapping`
and/or `$remoteMapping`:

```php
private static $localMapping = [
    'application/pdf' => PdfOcrProcessor::class,
    'image/jpeg' => ImageOcrProcessor::class,
    'image/png' => ImageOcrProcessor::class,
    'image/tiff' => TiffOcrProcessor::class,   // new
];
```

## 3. Register the service (local processors only)

Add a factory in `OcrProcessorFactory::registerOcrProcessors`, with `shared = false` —
bug #43: a shared `Command` instance is reused across runs and breaks.

```php
/** @psalm-suppress InvalidArgument */
$context->registerService(TiffOcrProcessor::class, fn (ContainerInterface $c)
    => new TiffOcrProcessor(
        $c->get(ICommand::class),
        $c->get(LoggerInterface::class),
        $c->get(ISidecarFileAccessor::class),
        $c->get(ICommandLineUtils::class),
        $c->get(IPhpNativeFunctions::class)), false);
```

Remote processors need no registration — `WorkflowOcrRemoteProcessor` is already an alias
target and handles every MIME type the ExApp accepts.

## 4. Frontend

If the type should be selectable in the workflow UI, the MIME condition comes from
Nextcloud's workflowengine, not from this app — usually nothing to do. Check
`src/components/WorkflowOcr.vue` only if the new type needs its own setting.

## 5. Tests

- `tests/Unit/OcrProcessors/OcrProcessorFactoryTest.php` — the new mapping resolves for
  both backend modes.
- `tests/Unit/OcrProcessors/Local/<Name>OcrProcessorTest.php` — mirror
  `PdfOcrProcessorTest` / `ImageOcrProcessorTest`; mock `ICommand`.
- Add a sample file to `tests/Integration/testdata/` and a case in
  `TestUtils/BackendTestBase.php` if it must pass on both backends.

## 6. Remote backend

A new type only works remotely if `workflow_ocr_backend` can process it — `ocrmypdf`
accepts what it accepts. If the ExApp needs a change, run `/sync-backend-contract`.

## 7. Finish

Update the README's supported-types list and the Limitations section, then run
`/preflight`.
