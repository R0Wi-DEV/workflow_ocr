import { vi } from 'vitest'
import axios from '@nextcloud/axios'

beforeEach(() => {
	global.t = vi.fn()

	// Create the element that Vue will mount to
	const element = document.createElement('div')
	element.id = 'workflow_ocr_globalsettings'
	document.body.appendChild(element)
})

// TODO :: extend this testcases by mocking Vue
test('globalSettings.js can be imported', async () => {
	// Mock axios to prevent actual HTTP calls during component mounting
	axios.get.mockResolvedValue({ data: {} })

	// In Vue 3, we don't use Vue.prototype, instead use app.config.globalProperties
	// Just test that the file can be imported without errors
	await import('../globalSettings.js')
	expect(global.t).toBeDefined()
})
