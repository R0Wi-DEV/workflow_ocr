// Ensure global bare identifiers `appName` and `appVersion` exist
// Some bundles reference `appName`/`appVersion` as bare globals; declare them here
if (typeof window !== 'undefined') {
    window.appName = window.appName || 'workflow_ocr'
    window.appVersion = window.appVersion || '1.0.0'
}
