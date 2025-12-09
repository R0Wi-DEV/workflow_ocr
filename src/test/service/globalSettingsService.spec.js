import axios from '@nextcloud/axios'
import { vi } from 'vitest'
import { getGlobalSettings, setGlobalSettings } from '../../service/globalSettingsService.js'

afterEach(() => {
	vi.resetAllMocks()
})

test('getGlobalSettings returns correct data from server', async () => {
	const mockedResponse = { data: { processorCount: 42 } }

	axios.get.mockResolvedValueOnce(mockedResponse)

	const result = await getGlobalSettings()

	expect(result.processorCount).toBe(42)
	expect(axios.get).toHaveBeenCalledTimes(1)
	expect(axios.get).toHaveBeenCalledWith('/apps/workflow_ocr/globalSettings')
})

test('setGlobalSettings sends correct data to server', async () => {
	const request = { data: { processorCount: 42 } }
	const mockedResponse = { data: { processorCount: '42' } }

	axios.put.mockResolvedValueOnce(mockedResponse)

	const result = await setGlobalSettings(request)

	expect(result.processorCount).toBe('42')
	expect(axios.put).toHaveBeenCalledTimes(1)
	expect(axios.put).toHaveBeenCalledWith('/apps/workflow_ocr/globalSettings', { globalSettings: request })
})
