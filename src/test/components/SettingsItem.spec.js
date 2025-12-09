import { vi } from 'vitest'
import { mount } from '@vue/test-utils'
import SettingsItem from '../../components/SettingsItem.vue'

beforeEach(() => {
	vi.resetAllMocks()
})

describe('SettingsItem', () => {
	test('Should render label, infoText and child-content', async () => {
		const wrapper = mount(SettingsItem, {
			props: {
				label: 'label',
				infoText: 'infoText',
			},
			slots: {
				default: '<div class="child-content" />',
			},
		})
		// Check that the HelpIcon component is rendered when infoText is provided
		const helpIcon = wrapper.findComponent({ name: 'HelpIcon' })
		expect(helpIcon.exists()).toBe(true)
		// Check that the label is rendered
		expect(wrapper.find('.label').text()).toContain('label')
		// Check that the slot content is rendered
		expect(wrapper.find('.child-content').exists()).toBe(true)
	})

	test('Should not render info icon if infoText is not set', async () => {
		const wrapper = mount(SettingsItem, {
			props: {
				label: 'label',
			},
			slots: {
				default: '<div class="child-content" />',
			},
		})

		// Check that the info icon is not rendered
		expect(wrapper.find('.info').exists()).toBe(false)

		// TODO :: check popover content
		// expect(wrapper.find('span.label').text()).toBe('label')
		// expect(wrapper.find('.child-content').exists()).toBe(true)
		// expect(wrapper.findComponent(NcPopover).exists()).toBe(false)
	})
})
