import axios from '@nextcloud/axios'
import { vi } from 'vitest'
import { getInstalledLanguages } from '../../service/ocrBackendInfoService.js'

afterEach(() => {
	vi.resetAllMocks()
})

test('getInstalledLanguages returns correct data from server', async () => {
	const mockedResponse = { data: ['deu', 'eng'] }

	axios.get.mockResolvedValueOnce(mockedResponse)

	const result = await getInstalledLanguages()

	expect(result.length).toBe(2)
	expect(axios.get).toHaveBeenCalledTimes(1)
	expect(axios.get).toHaveBeenCalledWith('/apps/workflow_ocr/ocrBackendInfo/installedLangs')
})
