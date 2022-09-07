import { mount } from '@vue/test-utils'
import WorkflowOcr from '../../components/WorkflowOcr.vue'
import SettingsItem from '../../components/SettingsItem.vue'
import Multiselect from '@nextcloud/vue/dist/Components/Multiselect'
import MultiselectTags from '@nextcloud/vue/dist/Components/MultiselectTags'
import CheckboxRadioSwitch from '@nextcloud/vue/dist/Components/CheckboxRadioSwitch'

beforeEach(() => {
	global.t = jest.fn()
})

describe('Init tests', () => {
	test('Component value shall be empty if user does not make any settings', () => {
		const wrapper = mount(WorkflowOcr)
		expect(wrapper.vm.value).toEqual('')
	})
})

describe('Language settings tests', () => {
	test('Should have 10 languages available', () => {
		const wrapper = mount(WorkflowOcr)
		expect(wrapper.vm.availableLanguages.length).toBe(10)
	})

	test('Should select one language', () => {
		const wrapper = mount(WorkflowOcr, {
			propsData: {
				value: '{ "languages": [ "de" ], "removeBackground": true }',
			},
		})
		expect(wrapper.vm.selectedLanguages.length).toBe(1)
	})

	test('Should select no language when value not set', () => {
		const wrapper = mount(WorkflowOcr)
		expect(wrapper.vm.selectedLanguages.length).toBe(0)
	})

	test('Should select no language when value set to null', () => {
		const wrapper = mount(WorkflowOcr, {
			propsData: {
				value: null,
			},
		})
		expect(wrapper.vm.selectedLanguages.length).toBe(0)
	})

	test('Should not select any language if language code not found', () => {
		const wrapper = mount(WorkflowOcr, {
			propsData: {
				value: '{ "languages": [ "nonExistend" ], "removeBackground": true }',
			},
		})
		expect(wrapper.vm.selectedLanguages.length).toBe(0)
	})

	test('Should return empty array if value is null', () => {
		const wrapper = mount(WorkflowOcr, {
			propsData: {
				value: '{ "languages": null }',
			},
		})
		expect(wrapper.vm.selectedLanguages).toEqual([])
	})

	test('Should add new language if user selects additional language', () => {
		const wrapper = mount(WorkflowOcr, {
			propsData: {
				value: '{ "languages": [ "de" ], "removeBackground": true }',
			},
		})

		// Simulate user input
		const multiselect = wrapper.findAllComponents(SettingsItem).at(0).findComponent(Multiselect)
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
		const assignTagsItem = settingsItems.at(1).findComponent(MultiselectTags)

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
		const removeTagsItem = settingsItems.at(2).findComponent(MultiselectTags)

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

		const radioSwitch = wrapper.findComponent(CheckboxRadioSwitch)

		expect(radioSwitch.vm.checked).toBe(true)

		// Simulate user input
		radioSwitch.vm.$emit('update:checked', false)

		const inputEvent = wrapper.emitted().input
		expect(inputEvent).toBeTruthy()
		expect(inputEvent[0][0]).toBe('{"languages":["de"],"removeBackground":false}')
	})
})
