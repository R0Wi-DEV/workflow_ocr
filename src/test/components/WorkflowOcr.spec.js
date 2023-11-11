import { mount } from '@vue/test-utils'
import { getInstalledLanguages } from '../../service/ocrBackendInfoService.js'
import WorkflowOcr from '../../components/WorkflowOcr.vue'
import SettingsItem from '../../components/SettingsItem.vue'
import { NcSelect, NcSelectTags } from '@nextcloud/vue'

let installedLanguages = []

jest.mock('../../service/ocrBackendInfoService')

beforeEach(() => {
	const mockT = jest.fn()
	mockT.mockReturnValue('translated')
	global.t = mockT
	jest.resetAllMocks()
	getInstalledLanguages.mockImplementation(() => installedLanguages)
})

describe('Init tests', () => {
	test('Component value shall be empty if user does not make any settings', () => {
		const wrapper = mount(WorkflowOcr)
		expect(wrapper.vm.value).toEqual('')
		expect(getInstalledLanguages).toHaveBeenCalledTimes(1)
	})
})

describe('Language settings tests', () => {
	test('Should have 3 languages available', async () => {
		installedLanguages = ['deu', 'eng', 'fra']
		const wrapper = mount(WorkflowOcr)
		await new Promise(process.nextTick)
		expect(wrapper.vm.availableLanguages.length).toBe(3)
	})

	test('Should select one language', async () => {
		installedLanguages = ['deu', 'eng', 'fra']
		const wrapper = mount(WorkflowOcr, {
			propsData: {
				value: '{ "languages": [ "deu" ], "removeBackground": true }',
			},
		})
		await new Promise(process.nextTick)
		expect(wrapper.vm.selectedLanguages.length).toBe(1)
	})

	test('Should select no language when value not set', async () => {
		installedLanguages = ['deu', 'eng', 'fra']
		const wrapper = mount(WorkflowOcr)
		await new Promise(process.nextTick)
		expect(wrapper.vm.selectedLanguages.length).toBe(0)
	})

	test('Should select no language when value set to null', async () => {
		installedLanguages = ['deu', 'eng', 'fra']
		const wrapper = mount(WorkflowOcr, {
			propsData: {
				value: null,
			},
		})
		await new Promise(process.nextTick)
		expect(wrapper.vm.selectedLanguages.length).toBe(0)
	})

	test('Should not select any language if language code not found', async () => {
		installedLanguages = ['deu', 'eng', 'fra']
		const wrapper = mount(WorkflowOcr, {
			propsData: {
				value: '{ "languages": [ "nonExistend" ], "removeBackground": true }',
			},
		})
		await new Promise(process.nextTick)
		expect(wrapper.vm.selectedLanguages.length).toBe(0)
	})

	test('Should return empty array if value is null', async () => {
		installedLanguages = ['deu', 'eng', 'fra']
		const wrapper = mount(WorkflowOcr, {
			propsData: {
				value: '{ "languages": null }',
			},
		})
		await new Promise(process.nextTick)
		expect(wrapper.vm.selectedLanguages).toEqual([])
	})

	test('Should add new language if user selects additional language', async () => {
		installedLanguages = ['deu', 'eng', 'fra']
		const wrapper = mount(WorkflowOcr, {
			propsData: {
				value: '{ "languages": [ "de" ], "removeBackground": true }',
			},
		})
		await new Promise(process.nextTick)

		// Simulate user input
		const multiselect = wrapper.findAllComponents(SettingsItem).at(0).findComponent(NcSelect)
		multiselect.vm.$emit('input', [
			{ label: 'German', langCode: 'de' },
			{ label: 'English', langCode: 'en' },
		])

		const inputEvent = wrapper.emitted().input
		expect(inputEvent).toBeTruthy()
		expect(inputEvent[0][0]).toBe('{"languages":["de","en"],"removeBackground":true}')
	})
})

describe('Add/remove tags tests', () => {
	test('Values assignTagsAfterOcr/removeTagsAfterOcr tags are set to empty array if no value was choosen', () => {
		const wrapper = mount(WorkflowOcr)
		expect(wrapper.vm.tagsToAddAfterOcr).toEqual([])
		expect(wrapper.vm.tagsToRemoveAfterOcr).toEqual([])
	})

	test('User input for assignTagsAfterOcr is applied correctly on empty component', async () => {
		const wrapper = mount(WorkflowOcr, {
			propsData: {
				value: '{ "languages": [ "de" ], "removeBackground": true }',
			},
		})

		const settingsItems = wrapper.findAllComponents(SettingsItem)
		const assignTagsItem = settingsItems.at(1).findComponent(NcSelectTags)

		// Simulate user input
		assignTagsItem.vm.$emit('input', [1, 2])

		await wrapper.vm.$nextTick()

		const inputEvent = wrapper.emitted().input
		expect(inputEvent).toBeTruthy()
		expect(inputEvent[0][0]).toBe('{"languages":["de"],"removeBackground":true,"tagsToAddAfterOcr":[1,2]}')
	})

	test('User input for removeTagsAfterOcr is applied correctly on empty component', async () => {
		const wrapper = mount(WorkflowOcr, {
			propsData: {
				value: '{ "languages": [ "de" ], "removeBackground": true }',
			},
		})

		const settingsItems = wrapper.findAllComponents(SettingsItem)
		const removeTagsItem = settingsItems.at(2).findComponent(NcSelectTags)

		// Simulate user input
		removeTagsItem.vm.$emit('input', [1, 2])

		await wrapper.vm.$nextTick()

		const inputEvent = wrapper.emitted().input
		expect(inputEvent).toBeTruthy()
		expect(inputEvent[0][0]).toBe('{"languages":["de"],"removeBackground":true,"tagsToRemoveAfterOcr":[1,2]}')
	})
})

describe('Remove background tests', () => {
	test('RemoveBackground default is false if value is not set', () => {
		const wrapper = mount(WorkflowOcr)
		expect(wrapper.vm.removeBackground).toBe(false)
	})

	test('RemoveBackground default is false if property not set', () => {
		const wrapper = mount(WorkflowOcr, {
			propsData: {
				value: '{ "languages": [ "de" ] }',
			},
		})
		expect(wrapper.vm.removeBackground).toBe(false)
	})

	test('Should set removeBackground to false', () => {
		const wrapper = mount(WorkflowOcr, {
			propsData: {
				value: '{ "languages": [ "de" ], "removeBackground": true }',
			},
		})

		const radioSwitch = wrapper.findComponent({ ref: 'removeBackgroundSwitch' })

		expect(radioSwitch.vm.checked).toBe(true)

		// Simulate user input
		radioSwitch.vm.$emit('update:checked', false)

		const inputEvent = wrapper.emitted().input
		expect(inputEvent).toBeTruthy()
		expect(inputEvent[0][0]).toBe('{"languages":["de"],"removeBackground":false}')
	})
})

describe('OCR mode tests', () => {
	test('Default OCR mode is 0 (skip-text)', () => {
		const wrapper = mount(WorkflowOcr)
		expect(wrapper.vm.ocrMode).toBe('0')
	})

	test.each([0, 1, 2])('Should set OCR mode to %i', (mode) => {
		const wrapper = mount(WorkflowOcr)
		const radioButton = wrapper.findComponent({ ref: `ocrMode${mode}` })

		// Simulate user click on radiobutton
		radioButton.vm.$emit('update:checked', mode)

		const inputEvent = wrapper.emitted().input
		expect(inputEvent).toBeTruthy()
		expect(inputEvent[0][0]).toContain(`"ocrMode":${mode}`)
	})

	test('Setting OCR mode to --redo-ocr (1) should set removeBackground to false and disable the control', async () => {
		const wrapper = mount(WorkflowOcr, {
			propsData: {
				value: '{ "languages": [ "de" ], "removeBackground": true, "ocrMode": 0 }',
			},
		})

		const radioButton = wrapper.findComponent({ ref: 'ocrMode1' })

		// Simulate user click on radiobutton 'Redo OCR'
		radioButton.vm.$emit('update:checked', 1)

		await wrapper.vm.$nextTick()

		const inputEvent = wrapper.emitted().input
		expect(inputEvent).toBeTruthy()
		expect(inputEvent[0][0]).toContain('"ocrMode":1')
		expect(inputEvent[0][0]).toContain('"removeBackground":false')

		const removeBackgroundSwitch = wrapper.findComponent({ ref: 'removeBackgroundSwitch' })
		expect(removeBackgroundSwitch.vm.disabled).toBe(true)
	})

	test.each([0, 2, 3])('Should enable remove background switch when setting OCR mode from 1 (--redo-ocr) to %i', async (mode) => {
		const wrapper = mount(WorkflowOcr, {
			propsData: {
				value: '{ "removeBackground": false, "ocrMode": 1 }',
			},
		})

		await wrapper.vm.$nextTick()
		const removeBackgroundSwitchPre = wrapper.findComponent({ ref: 'removeBackgroundSwitch' })
		expect(removeBackgroundSwitchPre.vm.disabled).toBe(true)

		const radioButton = wrapper.findComponent({ ref: `ocrMode${mode}` })

		// Simulate user click on radiobutton
		radioButton.vm.$emit('update:checked', mode)

		await wrapper.vm.$nextTick()

		const inputEvent = wrapper.emitted().input
		expect(inputEvent).toBeTruthy()
		expect(inputEvent[0][0]).toContain(`"ocrMode":${mode}`)

		const removeBackgroundSwitchPost = wrapper.findComponent({ ref: 'removeBackgroundSwitch' })
		expect(removeBackgroundSwitchPost.vm.disabled).toBe(false)
	})
})
