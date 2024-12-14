import { mount } from '@vue/test-utils'
import { getInstalledLanguages } from '../../service/ocrBackendInfoService.js'
import WorkflowOcr from '../../components/WorkflowOcr.vue'
import SettingsItem from '../../components/SettingsItem.vue'
import { NcSelect, NcSelectTags } from '@nextcloud/vue'
import Vue from 'vue'

let installedLanguages = []

const systemTagsXml = `<?xml version="1.0"?>
<d:multistatus xmlns:d="DAV:" xmlns:s="http://sabredav.org/ns" xmlns:oc="http://owncloud.org/ns"
    xmlns:nc="http://nextcloud.org/ns">
    <d:response>
        <d:href>/remote.php/dav/systemtags/</d:href>
        <d:propstat>
            <d:prop>
                <oc:id />
                <oc:display-name />
                <oc:user-visible />
                <oc:user-assignable />
                <oc:can-assign />
            </d:prop>
            <d:status>HTTP/1.1 404 Not Found</d:status>
        </d:propstat>
    </d:response>
    <d:response>
        <d:href>/remote.php/dav/systemtags/1/</d:href>
        <d:propstat>
            <d:prop>
                <oc:id>1</oc:id>
                <oc:display-name>testTag</oc:display-name>
                <oc:user-visible>true</oc:user-visible>
                <oc:user-assignable>true</oc:user-assignable>
                <oc:can-assign>true</oc:can-assign>
            </d:prop>
            <d:status>HTTP/1.1 200 OK</d:status>
        </d:propstat>
    </d:response>
    <d:response>
        <d:href>/remote.php/dav/systemtags/2/</d:href>
        <d:propstat>
            <d:prop>
                <oc:id>2</oc:id>
                <oc:display-name>testTag2</oc:display-name>
                <oc:user-visible>true</oc:user-visible>
                <oc:user-assignable>true</oc:user-assignable>
                <oc:can-assign>true</oc:can-assign>
            </d:prop>
            <d:status>HTTP/1.1 200 OK</d:status>
        </d:propstat>
    </d:response>
</d:multistatus>
`

jest.mock('../../service/ocrBackendInfoService')

Vue.mixin({
	methods: {
		t: (key) => key, // Translation function
	},
})

beforeEach(() => {
	global.NextcloudVueDocs = {
		tags: systemTagsXml,
	}
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
		await multiselect.vm.$emit('input', [
			{ label: 'German', langCode: 'de' },
			{ label: 'English', langCode: 'en' },
		])

		const inputEvent = wrapper.emitted().input
		expect(inputEvent).toBeTruthy()
		expect(inputEvent[0][0]).toBe('{"languages":["de","en"],"tagsToAddAfterOcr":[],"tagsToRemoveAfterOcr":[],"removeBackground":true,"keepOriginalFileVersion":false,"keepOriginalFileDate":false,"ocrMode":0,"customCliArgs":""}')
		
	})
})

describe('Add/remove tags tests', () => {
	test('Values assignTagsAfterOcr/removeTagsAfterOcr tags are set to empty array if no value was choosen', () => {
		const wrapper = mount(WorkflowOcr)
		expect(wrapper.vm.model.tagsToAddAfterOcr).toEqual([])
		expect(wrapper.vm.model.tagsToRemoveAfterOcr).toEqual([])
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
		expect(inputEvent[0][0]).toBe('{"languages":["de"],"tagsToAddAfterOcr":[1,2],"tagsToRemoveAfterOcr":[],"removeBackground":true,"keepOriginalFileVersion":false,"keepOriginalFileDate":false,"ocrMode":0,"customCliArgs":""}')
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
		expect(inputEvent[0][0]).toBe('{"languages":["de"],"tagsToAddAfterOcr":[],"tagsToRemoveAfterOcr":[1,2],"removeBackground":true,"keepOriginalFileVersion":false,"keepOriginalFileDate":false,"ocrMode":0,"customCliArgs":""}')
	})
})

describe('Remove background tests', () => {
	test('RemoveBackground default is false if value is not set', () => {
		const wrapper = mount(WorkflowOcr)
		expect(wrapper.vm.model.removeBackground).toBe(false)
	})

	test('RemoveBackground default is false if property not set', () => {
		const wrapper = mount(WorkflowOcr, {
			propsData: {
				value: '{ "languages": [ "de" ] }',
			},
		})
		expect(wrapper.vm.model.removeBackground).toBe(false)
	})

	test('Should set removeBackground to false', async () => {
		const wrapper = mount(WorkflowOcr, {
			propsData: {
				value: '{ "languages": [ "de" ], "removeBackground": true }',
			},
		})

		const radioSwitch = wrapper.findComponent({ ref: 'removeBackgroundSwitch' })

		expect(radioSwitch.vm.checked).toBe(true)

		// Simulate user input
		radioSwitch.vm.$emit('update:checked', false)

		await wrapper.vm.$nextTick()

		const inputEvent = wrapper.emitted().input
		expect(inputEvent).toBeTruthy()
		expect(inputEvent[0][0]).toBe('{"languages":["de"],"tagsToAddAfterOcr":[],"tagsToRemoveAfterOcr":[],"removeBackground":false,"keepOriginalFileVersion":false,"keepOriginalFileDate":false,"ocrMode":0,"customCliArgs":""}')
	})
})

describe('OCR mode tests', () => {
	test('Default OCR mode is 0 (skip-text)', () => {
		const wrapper = mount(WorkflowOcr)
		expect(wrapper.vm.ocrMode).toBe('0')
	})

	test.each([0, 1, 2])('Should set OCR mode to %i', async (mode) => {
		const wrapper = mount(WorkflowOcr, {
			propsData: {
				// simulate that ocr mode is currently set to something diffferent
				value: `{ "ocrMode": ${mode + 1 % 3}}`,
			},
		})
		const radioButton = wrapper.findComponent({ ref: `ocrMode${mode}` })

		// Simulate user click on radiobutton
		radioButton.vm.$emit('update:checked', mode)

		await wrapper.vm.$nextTick()

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

describe('Custom CLI args test', () => {
	test('Default value for customCliArgs is empty string', () => {
		const wrapper = mount(WorkflowOcr)
		expect(wrapper.vm.model.customCliArgs).toBe('')
	})

	test('Should set input element value to customCliArgs', async () => {
		const wrapper = mount(WorkflowOcr, {
			propsData: {
				value: '{}',
			},
		})

		const textInput = wrapper.findComponent({ ref: 'customCliArgs' })

		// Simulate user input
		textInput.vm.$emit('update:value', '--dpi 300')

		await wrapper.vm.$nextTick()

		const inputEvent = wrapper.emitted().input
		expect(inputEvent).toBeTruthy()
		expect(inputEvent[0][0]).toBe('{"languages":[],"tagsToAddAfterOcr":[],"tagsToRemoveAfterOcr":[],"removeBackground":false,"keepOriginalFileVersion":false,"keepOriginalFileDate":false,"ocrMode":0,"customCliArgs":"--dpi 300"}')
	})
})

describe('Original file switches test', () => {
	test.each(['keepOriginalFileDate', 'keepOriginalFileVersion'])('Should set %s to true', async (ref) => {
		const wrapper = mount(WorkflowOcr, {
			propsData: {
				value: '{}',
			},
		})

		const switchComponent = wrapper.findComponent({ ref })
		expect(switchComponent.vm.checked).toBe(false)

		// Simulate user input
		switchComponent.vm.$emit('update:checked', true)

		await wrapper.vm.$nextTick()

		const inputEvent = wrapper.emitted().input
		expect(inputEvent).toBeTruthy()
		expect(inputEvent[0][0]).toContain(`"${ref}":true`)
	})
})
