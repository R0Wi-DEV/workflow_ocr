import { mount } from '@vue/test-utils'
import WorkflowOcr from '../../components/WorkflowOcr.vue'
import Multiselect from '@nextcloud/vue/dist/Components/Multiselect'
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
		const multiselect = wrapper.findComponent(Multiselect)
		multiselect.vm.$emit('input', [
			{ label: 'German', langCode: 'de' },
			{ label: 'English', langCode: 'en' },
		])

		const inputEvent = wrapper.emitted().input
		expect(inputEvent).toBeTruthy()
		expect(inputEvent[0][0]).toBe('{"languages":["de","en"],"removeBackground":true}')
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
