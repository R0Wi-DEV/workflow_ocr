---
name: add-workflow-setting
description: Add or change a per-workflow or global setting in workflow_ocr end to end - PHP model, validation, ocrmypdf argument mapping, Vue UI, l10n and tests. Use when asked to add an OCR option, checkbox, or admin setting.
---

Two kinds of setting, different paths. Pick the right one first.

---

## A. Per-workflow setting (`Model\WorkflowSettings`)

Stored as the workflow operation's JSON string, produced by the Vue component.

1. **Model** — `lib/Model/WorkflowSettings.php`: add the private property with its default,
   a getter, and a line in `setJson()`:

   ```php
   $this->setProperty($this->myOption, $data, 'myOption', fn ($value) => is_bool($value));
   ```

   The validator callback is the security boundary. Anything that ends up on the `ocrmypdf`
   command line must be allow-listed, not just type-checked — see `LANGUAGE_CODE_REGEX`.
   `Operation::validateOperation` rejects the whole workflow when `setJson` throws.

2. **Argument mapping** — if the setting changes the OCR run, map it in
   `lib/OcrProcessors/CommandLineUtils::getCommandlineArgs`. This one method feeds both the
   local CLI and the `ocrmypdf_parameters` form field sent to the ExApp, so the setting
   works in both backend modes for free. Watch for incompatible `ocrmypdf` flag
   combinations and log a warning rather than emitting a broken command line (see how
   `--remove-background` is suppressed under `--redo-ocr`).

3. **Post-processing** — if it instead changes what happens *after* OCR (tags, versions,
   sidecar, notifications), it belongs in `lib/Service/OcrService.php`, not in
   `CommandLineUtils`.

4. **UI** — `src/components/WorkflowOcr.vue`. Add the control, include the property in the
   emitted JSON, and keep the key identical to the model's. Wrap the label in
   `t('workflow_ocr', '…')`. Use `SettingsItem.vue` / `HelpTextWrapper.vue` for
   consistency with the existing rows.

5. **Tests** — `tests/Unit/Model/WorkflowSettingsTest.php` (parse, default, rejection of
   invalid input), `tests/Unit/OcrProcessors/CommandLineUtilsTest.php` (emitted arguments),
   `src/test/components/WorkflowOcr.spec.js` (control renders, JSON round-trips).

---

## B. Global admin setting (`Model\GlobalSettings`)

1. `lib/Model/GlobalSettings.php` — add the public typed property.
2. `lib/Service/GlobalSettingsService.php` — persist and read it.
3. `lib/Controller/GlobalSettingsController::setGlobalSettings` — parse and cast it from the
   request array (the controller casts explicitly; do not pass raw input through).
4. `src/components/GlobalSettings.vue` + `src/service/globalSettingsService.js` — the UI and
   its API call against `PUT /globalSettings`.
5. Consume it where relevant — `CommandLineUtils` (e.g. `processorCount` → `--jobs`) or
   `ApiClient` (e.g. `timeout`).
6. Tests: `tests/Unit/Service/GlobalSettingsServiceTest.php`,
   `tests/Unit/Controller/GlobalSettingsControllerTest.php`,
   `src/test/components/GlobalSettings.spec.js`.

---

## Both

Document the new option in the README "Settings" section, then run `/preflight`.
