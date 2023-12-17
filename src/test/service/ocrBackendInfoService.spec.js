import axios from '@nextcloud/axios'
import { getInstalledLanguages } from '../../service/ocrBackendInfoService.js'

jest.mock('@nextcloud/axios')
jest.mock('@nextcloud/router', () => ({
	generateUrl: url => url,
}))

afterEach(() => {
	jest.resetAllMocks()
})

test('getInstalledLanguages returns correct data from server', async () => {
	const mockedResponse = { data: ['deu', 'eng'] }

	axios.get.mockResolvedValueOnce(mockedResponse)

	const result = await getInstalledLanguages()

	expect(result.length).toBe(2)
	expect(axios.get).toHaveBeenCalledTimes(1)
	expect(axios.get).toHaveBeenCalledWith('/apps/workflow_ocr/ocrBackendInfo/installedLangs')
})
