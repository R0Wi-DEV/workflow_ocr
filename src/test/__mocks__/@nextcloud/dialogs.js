import { vi } from 'vitest'

// Mock for @nextcloud/dialogs
export const showError = vi.fn()
export const showWarning = vi.fn()
export const showInfo = vi.fn()
export const showSuccess = vi.fn()
export const showMessage = vi.fn()
