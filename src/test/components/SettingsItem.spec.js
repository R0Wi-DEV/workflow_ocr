import { mount } from '@vue/test-utils'
import SettingsItem from '../../components/SettingsItem.vue'
import { NcPopover } from '@nextcloud/vue'

beforeEach(() => {
	global.t = jest.fn()
	jest.resetAllMocks()
})

describe('SettingsItem', () => {
	test('Should render label, infoText and child-content', async () => {
		const wrapper = mount(SettingsItem, {
			propsData: {
				label: 'label',
				infoText: 'infoText',
			},
			slots: {
				default: '<div class="child-content" />',
			},
		})
		expect(wrapper.find('.help-circle-icon').exists()).toBe(true)
		// Simulate click on help icon
		await wrapper.find('.help-circle-icon').trigger('click')
		expect(wrapper.findComponent(NcPopover).exists()).toBe(true)

		// TODO :: check popover content
		// expect(wrapper.find('span.label').text()).toBe('label')
		// expect(wrapper.find('p').text()).toBe('infoText')
		// expect(wrapper.find('.child-content').exists()).toBe(true)
	})

	test('Should not render info icon if infoText is not set', async () => {
		const wrapper = mount(SettingsItem, {
			propsData: {
				label: 'label',
			},
			slots: {
				default: '<div class="child-content" />',
			},
		})

		expect(wrapper.find('.help-circle-icon').exists()).toBe(false)

		// TODO :: check popover content
		// expect(wrapper.find('span.label').text()).toBe('label')
		// expect(wrapper.find('.child-content').exists()).toBe(true)
		// expect(wrapper.findComponent(NcPopover).exists()).toBe(false)
	})
})
