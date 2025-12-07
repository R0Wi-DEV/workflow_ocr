import { mount } from '@vue/test-utils'
import { getGlobalSettings, setGlobalSettings } from '../../service/globalSettingsService.js'
import GlobalSettings from '../../components/GlobalSettings.vue'

jest.mock('../../service/globalSettingsService')

const mountOptions = {
	global: {
		mocks: {
			t: (app, key) => key,
		},
	},
}

beforeEach(() => {
	jest.resetAllMocks()
})

describe('Init tests', () => {
	test('Component values shall be empty if server returns null values', async () => {
		const mockSettings = { processorCount: null }
		getGlobalSettings.mockResolvedValueOnce(mockSettings)

		const wrapper = mount(GlobalSettings, mountOptions)
		await new Promise(process.nextTick)

		expect(getGlobalSettings).toHaveBeenCalledTimes(1)
		expect(wrapper.vm.settings).toEqual(mockSettings)
		const processorCount = wrapper.find('input[name="processorCount"]')
		expect(processorCount.element.value).toBe('')
	})

	test('Component values shall reflect values given by server', async () => {
		const mockSettings = { processorCount: 42 }
		getGlobalSettings.mockResolvedValueOnce(mockSettings)

		const wrapper = mount(GlobalSettings, mountOptions)
		await new Promise(process.nextTick)

		expect(getGlobalSettings).toHaveBeenCalledTimes(1)
		expect(wrapper.vm.settings).toEqual(mockSettings)
		const processorCount = wrapper.find('input[name="processorCount"]')
		expect(processorCount.element.value).toBe('42')
	})
})

describe('Interaction tests', () => {
	test('Should update settings when processorCount is changed', async () => {
		const initialMockSettings = { processorCount: '2' }
		getGlobalSettings.mockResolvedValueOnce(initialMockSettings)

		const afterSaveMockSettings = { processorCount: 42 }
		setGlobalSettings.mockResolvedValueOnce(afterSaveMockSettings)

		const wrapper = mount(GlobalSettings, mountOptions)
		await new Promise(process.nextTick)

		const processorCount = wrapper.find('input[name="processorCount"]')
		processorCount.element.value = '42'
		await processorCount.trigger('input')

		// v-model on type="number" input converts to number
		expect(wrapper.vm.settings.processorCount).toBe(42)
		expect(setGlobalSettings).toHaveBeenCalledTimes(1)
		expect(setGlobalSettings).toHaveBeenCalledWith(expect.objectContaining({
			processorCount: 42,
		}))
	})
})
