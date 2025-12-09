import { vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { getInstalledLanguages } from '../../service/ocrBackendInfoService.js'
import WorkflowOcr from '../../components/WorkflowOcr.vue'
import SettingsItem from '../../components/SettingsItem.vue'
import { NcSelect, NcSelectTags } from '@nextcloud/vue'

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

vi.mock('../../service/ocrBackendInfoService')

const mountOptions = {
	global: {
		mocks: {
			t: (key) => key,
		},
	},
}

beforeEach(() => {
	global.NextcloudVueDocs = {
		tags: systemTagsXml,
	}
	vi.resetAllMocks()
	getInstalledLanguages.mockImplementation(() => installedLanguages)
})

describe('Init tests', () => {
	test('Component value shall be empty if user does not make any settings', () => {
		const wrapper = mount(WorkflowOcr, {
			...mountOptions,
			global: {
				mocks: {
					t: (key) => key,
				},
			},
		})
		expect(wrapper.props().modelValue).toEqual('')
		expect(getInstalledLanguages).toHaveBeenCalledTimes(1)
	})
})

describe('Language settings tests', () => {
	test('Should have 3 languages available', async () => {
		installedLanguages = ['deu', 'eng', 'fra']
		const wrapper = mount(WorkflowOcr, mountOptions)
		await new Promise(process.nextTick)
		expect(wrapper.vm.availableLanguages.length).toBe(3)
	})

	test('Should select one language', async () => {
		installedLanguages = ['deu', 'eng', 'fra']
		const wrapper = mount(WorkflowOcr, {
			...mountOptions,
			...mountOptions,
			props: {
				modelValue: '{ "languages": [ "deu" ], "removeBackground": true }',
			},
		})
		await new Promise(process.nextTick)
		expect(wrapper.vm.selectedLanguages.length).toBe(1)
	})

	test('Should select no language when value not set', async () => {
		installedLanguages = ['deu', 'eng', 'fra']
		const wrapper = mount(WorkflowOcr, mountOptions)
		await new Promise(process.nextTick)
		expect(wrapper.vm.selectedLanguages.length).toBe(0)
	})

	test('Should select no language when value set to null', async () => {
		installedLanguages = ['deu', 'eng', 'fra']
		const wrapper = mount(WorkflowOcr, {
			...mountOptions,
			...mountOptions,
			props: {
				modelValue: null,
			},
		})
		await new Promise(process.nextTick)
		expect(wrapper.vm.selectedLanguages.length).toBe(0)
	})

	test('Should not select any language if language code not found', async () => {
		installedLanguages = ['deu', 'eng', 'fra']
		const wrapper = mount(WorkflowOcr, {
			...mountOptions,
			...mountOptions,
			props: {
				modelValue: '{ "languages": [ "nonExistend" ], "removeBackground": true }',
			},
		})
		await new Promise(process.nextTick)
		expect(wrapper.vm.selectedLanguages.length).toBe(0)
	})

	test('Should return empty array if value is null', async () => {
		installedLanguages = ['deu', 'eng', 'fra']
		const wrapper = mount(WorkflowOcr, {
			...mountOptions,
			...mountOptions,
			props: {
				modelValue: '{ "languages": null }',
			},
		})
		await new Promise(process.nextTick)
		expect(wrapper.vm.selectedLanguages).toEqual([])
	})

	test('Should add new language if user selects additional language', async () => {
		installedLanguages = ['deu', 'eng', 'fra']
		const wrapper = mount(WorkflowOcr, {
			...mountOptions,
			...mountOptions,
			props: {
				modelValue: '{ "languages": [ "de" ], "removeBackground": true }',
			},
		})
		await new Promise(process.nextTick)

		// Simulate user input
		const multiselect = wrapper.findAllComponents(SettingsItem).at(0).findComponent(NcSelect)
		await multiselect.vm.$emit('update:modelValue', [
			{ label: 'German', langCode: 'de' },
			{ label: 'English', langCode: 'en' },
		])

		const inputEvent = wrapper.emitted()['update:modelValue']
		expect(inputEvent).toBeTruthy()
		expect(inputEvent[0][0]).toBe('{"languages":["de","en"],"tagsToAddAfterOcr":[],"tagsToRemoveAfterOcr":[],"removeBackground":true,"keepOriginalFileVersion":false,"keepOriginalFileDate":false,"sendSuccessNotification":false,"ocrMode":0,"customCliArgs":"","createSidecarFile":false,"skipNotificationsOnInvalidPdf":false,"skipNotificationsOnEncryptedPdf":false}')

	})
})

describe('Add/remove tags tests', () => {
	test('Values assignTagsAfterOcr/removeTagsAfterOcr tags are set to empty array if no value was choosen', () => {
		const wrapper = mount(WorkflowOcr, mountOptions)
		expect(wrapper.vm.model.tagsToAddAfterOcr).toEqual([])
		expect(wrapper.vm.model.tagsToRemoveAfterOcr).toEqual([])
	})

	test('User input for assignTagsAfterOcr is applied correctly on empty component', async () => {
		const wrapper = mount(WorkflowOcr, {
			...mountOptions,
			...mountOptions,
			props: {
				modelValue: '{ "languages": [ "de" ], "removeBackground": true }',
			},
		})

		const settingsItems = wrapper.findAllComponents(SettingsItem)
		const assignTagsItem = settingsItems.at(1).findComponent(NcSelectTags)

		// Simulate user input
		assignTagsItem.vm.$emit('update:modelValue', [1, 2])

		await wrapper.vm.$nextTick()

		const inputEvent = wrapper.emitted()['update:modelValue']
		expect(inputEvent).toBeTruthy()
		expect(inputEvent[0][0]).toBe('{"languages":["de"],"tagsToAddAfterOcr":[1,2],"tagsToRemoveAfterOcr":[],"removeBackground":true,"keepOriginalFileVersion":false,"keepOriginalFileDate":false,"sendSuccessNotification":false,"ocrMode":0,"customCliArgs":"","createSidecarFile":false,"skipNotificationsOnInvalidPdf":false,"skipNotificationsOnEncryptedPdf":false}')
	})

	test('User input for removeTagsAfterOcr is applied correctly on empty component', async () => {
		const wrapper = mount(WorkflowOcr, {
			...mountOptions,
			...mountOptions,
			props: {
				modelValue: '{ "languages": [ "de" ], "removeBackground": true }',
			},
		})

		const settingsItems = wrapper.findAllComponents(SettingsItem)
		const removeTagsItem = settingsItems.at(2).findComponent(NcSelectTags)

		// Simulate user input
		removeTagsItem.vm.$emit('update:modelValue', [1, 2])

		await wrapper.vm.$nextTick()

		const inputEvent = wrapper.emitted()['update:modelValue']
		expect(inputEvent).toBeTruthy()
		expect(inputEvent[0][0]).toBe('{"languages":["de"],"tagsToAddAfterOcr":[],"tagsToRemoveAfterOcr":[1,2],"removeBackground":true,"keepOriginalFileVersion":false,"keepOriginalFileDate":false,"sendSuccessNotification":false,"ocrMode":0,"customCliArgs":"","createSidecarFile":false,"skipNotificationsOnInvalidPdf":false,"skipNotificationsOnEncryptedPdf":false}')
	})
})

describe('Remove background tests', () => {
	test('RemoveBackground default is false if value is not set', () => {
		const wrapper = mount(WorkflowOcr, mountOptions)
		expect(wrapper.vm.model.removeBackground).toBe(false)
	})

	test('RemoveBackground default is false if property not set', () => {
		const wrapper = mount(WorkflowOcr, {
			...mountOptions,
			...mountOptions,
			props: {
				modelValue: '{ "languages": [ "de" ] }',
			},
		})
		expect(wrapper.vm.model.removeBackground).toBe(false)
	})

	test('Should set removeBackground to false', async () => {
		const wrapper = mount(WorkflowOcr, {
			...mountOptions,
			...mountOptions,
			props: {
				modelValue: '{ "languages": [ "de" ], "removeBackground": true }',
			},
		})

		expect(wrapper.vm.model.removeBackground).toBe(true)

		// Simulate user input by toggling the reactive model directly
		wrapper.vm.model.removeBackground = false

		await wrapper.vm.$nextTick()

		const inputEvent = wrapper.emitted()['update:modelValue']
		expect(inputEvent).toBeTruthy()
		expect(inputEvent[0][0]).toBe('{"languages":["de"],"tagsToAddAfterOcr":[],"tagsToRemoveAfterOcr":[],"removeBackground":false,"keepOriginalFileVersion":false,"keepOriginalFileDate":false,"sendSuccessNotification":false,"ocrMode":0,"customCliArgs":"","createSidecarFile":false,"skipNotificationsOnInvalidPdf":false,"skipNotificationsOnEncryptedPdf":false}')
	})
})

describe('OCR mode tests', () => {
	test('Default OCR mode is 0 (skip-text)', () => {
		const wrapper = mount(WorkflowOcr, mountOptions)
		expect(wrapper.vm.ocrMode).toBe('0')
	})

	test.each([0, 1, 2])('Should set OCR mode to %i', async (mode) => {
		const wrapper = mount(WorkflowOcr, {
			...mountOptions,
			props: {
				// simulate that ocr mode is currently set to something diffferent
				modelValue: `{ "ocrMode": ${mode + 1 % 3}}`,
			},
		})
		const radioButton = wrapper.findComponent({ ref: `ocrMode${mode}` })

		// Simulate user click on radiobutton
		radioButton.vm.$emit('update:modelValue', mode)

		await wrapper.vm.$nextTick()

		const inputEvent = wrapper.emitted()['update:modelValue']
		expect(inputEvent).toBeTruthy()
		expect(inputEvent[0][0]).toContain(`"ocrMode":${mode}`)
	})

	test('Setting OCR mode to --redo-ocr (1) should set removeBackground to false and disable the control', async () => {
		const wrapper = mount(WorkflowOcr, {
			...mountOptions,
			props: {
				modelValue: '{ "languages": [ "de" ], "removeBackground": true, "ocrMode": 0 }',
			},
		})

		const radioButton = wrapper.findComponent({ ref: 'ocrMode1' })

		// Simulate user click on radiobutton 'Redo OCR'
		radioButton.vm.$emit('update:modelValue', 1)

		await wrapper.vm.$nextTick()

		const inputEvent = wrapper.emitted()['update:modelValue']
		expect(inputEvent).toBeTruthy()
		expect(inputEvent[0][0]).toContain('"ocrMode":1')
		expect(inputEvent[0][0]).toContain('"removeBackground":false')

		expect(wrapper.vm.removeBackgroundDisabled).toBe(true)
	})

	test.each([0, 2, 3])('Should enable remove background switch when setting OCR mode from 1 (--redo-ocr) to %i', async (mode) => {
		const wrapper = mount(WorkflowOcr, {
			...mountOptions,
			props: {
				modelValue: '{ "removeBackground": false, "ocrMode": 1 }',
			},
		})

		await wrapper.vm.$nextTick()
		expect(wrapper.vm.removeBackgroundDisabled).toBe(true)

		const radioButton = wrapper.findComponent({ ref: `ocrMode${mode}` })

		// Simulate user click on radiobutton
		radioButton.vm.$emit('update:modelValue', mode)

		await wrapper.vm.$nextTick()

		const inputEvent = wrapper.emitted()['update:modelValue']
		expect(inputEvent).toBeTruthy()
		expect(inputEvent[0][0]).toContain(`"ocrMode":${mode}`)

		expect(wrapper.vm.removeBackgroundDisabled).toBe(false)
	})
})

describe('Custom CLI args test', () => {
	test('Default value for customCliArgs is empty string', () => {
		const wrapper = mount(WorkflowOcr, mountOptions)
		expect(wrapper.vm.model.customCliArgs).toBe('')
	})

	test('Should set input element value to customCliArgs', async () => {
		const wrapper = mount(WorkflowOcr, {
			...mountOptions,
			props: {
				modelValue: '{}',
			},
		})

		expect(wrapper.vm.model.customCliArgs).toBe('')

		// Simulate user input by writing to the reactive model
		wrapper.vm.model.customCliArgs = '--dpi 300'

		await wrapper.vm.$nextTick()

		const inputEvent = wrapper.emitted()['update:modelValue']
		expect(inputEvent).toBeTruthy()
		expect(inputEvent[0][0]).toBe('{"languages":[],"tagsToAddAfterOcr":[],"tagsToRemoveAfterOcr":[],"removeBackground":false,"keepOriginalFileVersion":false,"keepOriginalFileDate":false,"sendSuccessNotification":false,"ocrMode":0,"customCliArgs":"--dpi 300","createSidecarFile":false,"skipNotificationsOnInvalidPdf":false,"skipNotificationsOnEncryptedPdf":false}')
	})
})

describe('Original file switches test', () => {
	test.each(['keepOriginalFileDate', 'keepOriginalFileVersion'])('Should set %s to true', async (ref) => {
		const wrapper = mount(WorkflowOcr, {
			...mountOptions,
			props: {
				modelValue: '{}',
			},
		})

		expect(wrapper.vm.model[ref]).toBe(false)

		// Simulate user input by toggling the reactive model
		wrapper.vm.model[ref] = true

		await wrapper.vm.$nextTick()

		const inputEvent = wrapper.emitted()['update:modelValue']
		expect(inputEvent).toBeTruthy()
		expect(inputEvent[0][0]).toContain(`"${ref}":true`)
	})
})

describe('Sidecar file switch test', () => {
	test('Should set createSidecarFile to false by default', () => {
		const wrapper = mount(WorkflowOcr, {
			...mountOptions,
			props: {
				modelValue: '{}',
			},
		})

		expect(wrapper.vm.model.createSidecarFile).toBe(false)
	})

	test('Should set createSidecarFile to true when toggled', async () => {
		const wrapper = mount(WorkflowOcr, {
			...mountOptions,
			props: {
				value: '{}',
			},
		})

		expect(wrapper.vm.model.createSidecarFile).toBe(false)

		// Simulate user input by toggling the reactive model
		wrapper.vm.model.createSidecarFile = true

		await wrapper.vm.$nextTick()

		const inputEvent = wrapper.emitted()['update:modelValue']
		expect(inputEvent).toBeTruthy()
		expect(inputEvent[0][0]).toContain('"createSidecarFile":true')
	})
})

describe('Notifications switches tests', () => {
	test('Notification switches default to false', () => {
		const wrapper = mount(WorkflowOcr, {
			...mountOptions,
			props: { modelValue: '{}' },
		})

		// Test the model values directly instead of trying to find components inside HelpTextWrapper slots
		// In Vue 3, components inside slots are not easily accessible via findComponent from the parent
		expect(wrapper.vm.model.skipNotificationsOnInvalidPdf).toBe(false)
		expect(wrapper.vm.model.skipNotificationsOnEncryptedPdf).toBe(false)
		expect(wrapper.vm.model.sendSuccessNotification).toBe(false)
	})

	test('Should set skipNotificationsOnInvalidPdf to true when toggled', async () => {
		const wrapper = mount(WorkflowOcr, { ...mountOptions, props: { modelValue: '{}' } })

		// Test by directly manipulating the model value (simulates user interaction)
		expect(wrapper.vm.model.skipNotificationsOnInvalidPdf).toBe(false)

		// Simulate user toggle by setting the model property
		wrapper.vm.model.skipNotificationsOnInvalidPdf = true
		await wrapper.vm.$nextTick()

		const inputEvent = wrapper.emitted()['update:modelValue']
		expect(inputEvent).toBeTruthy()
		expect(inputEvent[0][0]).toContain('"skipNotificationsOnInvalidPdf":true')
	})

	test('Should set skipNotificationsOnEncryptedPdf to true when toggled', async () => {
		const wrapper = mount(WorkflowOcr, { ...mountOptions, props: { modelValue: '{}' } })

		// Test by directly manipulating the model value (simulates user interaction)
		expect(wrapper.vm.model.skipNotificationsOnEncryptedPdf).toBe(false)

		// Simulate user toggle by setting the model property
		wrapper.vm.model.skipNotificationsOnEncryptedPdf = true
		await wrapper.vm.$nextTick()

		const inputEvent = wrapper.emitted()['update:modelValue']
		expect(inputEvent).toBeTruthy()
		expect(inputEvent[0][0]).toContain('"skipNotificationsOnEncryptedPdf":true')
	})

	test('Should set sendSuccessNotification to true when toggled', async () => {

		const wrapper = mount(WorkflowOcr, { ...mountOptions, props: { modelValue: '{}' } })

		// Test by directly manipulating the model value (simulates user interaction)
		expect(wrapper.vm.model.sendSuccessNotification).toBe(false)

		// Simulate user toggle by setting the model property
		wrapper.vm.model.sendSuccessNotification = true
		await wrapper.vm.$nextTick()

		const inputEvent = wrapper.emitted()['update:modelValue']
		expect(inputEvent).toBeTruthy()
		expect(inputEvent[0][0]).toContain('"sendSuccessNotification":true')
	})
})
