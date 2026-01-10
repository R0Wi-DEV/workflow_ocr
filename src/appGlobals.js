// Ensure global bare identifiers `appName` and `appVersion` exist
// Some bundles reference `appName`/`appVersion` as bare globals; declare them here
if (typeof globalThis !== 'undefined') {
	globalThis.appName = globalThis.appName || 'workflow_ocr'
	globalThis.appVersion = globalThis.appVersion || '1.0.0'
}
