---
name: sync-backend-contract
description: Verify or update the REST contract between the workflow_ocr PHP app and the workflow_ocr_backend Python ExApp - endpoints, multipart fields, JSON field names, error shape, ocrmypdf parameter string and versions. Use whenever a change touches the remote backend path or either side's API.
---

The two repos are separately deployed but tightly coupled: the PHP app is a hand-maintained
client of the ExApp. Nothing checks the contract at build time — the integration suite with
`backend: remote` is the only end-to-end guard. Walk all five points below whenever either
side changes.

Backend repo: `R0Wi-DEV/workflow_ocr_backend`. If it is not checked out next to this one,
read it via the GitHub MCP tools rather than assuming.

## 1. Endpoints

| Endpoint | PHP caller | Python handler |
| --- | --- | --- |
| `POST /process_ocr` | `ApiClient::processOcr` | `app.py::process_ocr` |
| `GET /installed_languages` | `ApiClient::getLanguages` | `app.py::installed_languages` |
| `GET /heartbeat` | `ApiClient::heartbeat` | provided by `nc_py_api`'s `set_handlers` |

All calls go through `IAppApiWrapper::exAppRequest` (AppAPI routes them to the ExApp) —
never a raw HTTP client.

## 2. Multipart fields for `/process_ocr`

PHP sends exactly two parts, and FastAPI's parameter names must match byte for byte:

| Part | PHP (`ApiClient::processOcr`) | Python (`process_ocr`) |
| --- | --- | --- |
| file | `name => 'file'`, `filename => $fileName` | `file: UploadFile = File(...)` |
| params | `name => 'ocrmypdf_parameters'` | `ocrmypdf_parameters: str = Form(None)` |

## 3. JSON field names

Python `pydantic` models use `serialization_alias`; the PHP client models use
`$openAPITypes` / `attributeMap`. These are two hand-written halves of one schema.

| Python (`model/ocrresult.py`) | Wire | PHP (`lib/OcrProcessors/Remote/Client/Model/`) |
| --- | --- | --- |
| `OcrResult.filename` | `filename` | `OcrResult` |
| `OcrResult.content_type` | `contentType` | `OcrResult` |
| `OcrResult.recognized_text` | `recognizedText` | `OcrResult` |
| `OcrResult.file_content` (base64) | `fileContent` | `OcrResult` |
| `ErrorResult.message` | `message` | `ErrorResult` |
| `ErrorResult.ocr_my_pdf_exit_code` | `ocrMyPdfExitCode` | `ErrorResult` |

Adding a field means editing **four** places: the pydantic model, the PHP model's
`$openAPITypes`, `$openAPIFormats`/`$attributeMap`, and its getter/setter maps.

## 4. Status codes and error shape

`ApiClient::processOcr` handles exactly `200` (→ `OcrResult`) and `500` (→ `ErrorResult`)
and throws `RuntimeException` on anything else. The backend's two exception handlers in
`app.py` return 500 with `{"message": ...}`, plus `ocrMyPdfExitCode` for
`ocrmypdf.ExitCodeException`. A new status code on the Python side needs a matching branch
in PHP or it becomes an opaque failure.

`fileContent` is base64 on the wire; the PHP side calls `base64_decode` in
`WorkflowOcrRemoteProcessor`.

## 5. The `ocrmypdf_parameters` string

`CommandLineUtils::getCommandlineArgs` emits a CLI-style string
(`--skip-text --language eng+deu --jobs 4`); the backend's
`OcrService._split_parameters` parses it back into `ocrmypdf.ocr()` kwargs (`--` split,
`-`→`_` in keys, `+` → list, numeric/bool coercion, bare flag → `True`).

Consequences to check when adding a flag:

- Local-only arguments must not be sent remotely. `CommandLineUtils` already gates `-q` and
  `--sidecar` on `$isLocalExecution` — a new local-only flag needs the same gate.
- A flag whose value can contain a space or `--` will not survive the parser.
- The flag must exist as an `ocrmypdf.ocr()` keyword, not only as a CLI option.

## 6. Versions

`appinfo/info.xml` in both repos carries the same version (backend uses a `-dev` suffix
between releases). The backend image is published to
`ghcr.io/r0wi-dev/workflow_ocr_backend` and pinned in the backend's `<docker-install>`.
Bump both together.

## Verifying

```bash
make php-integrationtest    # covers the remote path via OcrBackendServiceTest
```

and in the backend repo `make test` (plus `make harp-integrationtest` if the deployment
path changed). If you cannot run the remote integration suite, say so explicitly rather
than reporting the contract as verified.
