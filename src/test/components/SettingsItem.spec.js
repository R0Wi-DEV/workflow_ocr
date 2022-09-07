import { mount } from '@vue/test-utils'
import SettingsItem from '../../components/SettingsItem.vue'
import Popover from '@nextcloud/vue/dist/Components/Popover'

beforeEach(() => {
	global.t = jest.fn()
	jest.resetAllMocks()
})

describe('SettingsItem', () => {
	test('Should render label, infoText and child-content', () => {
		const wrapper = mount(SettingsItem, {
			propsData: {
				label: 'label',
				infoText: 'infoText',
			},
			slots: {
				default: '<div class="child-content" />',
			},
		})
		expect(wrapper.find('span.label').text()).toBe('label')
		expect(wrapper.find('p').text()).toBe('infoText')
		expect(wrapper.find('.child-content').exists()).toBe(true)
	})

	test('Should not render info icon if infoText is not set', () => {
		const wrapper = mount(SettingsItem, {
			propsData: {
				label: 'label',
			},
			slots: {
				default: '<div class="child-content" />',
			},
		})

		expect(wrapper.find('span.label').text()).toBe('label')
		expect(wrapper.find('.child-content').exists()).toBe(true)
		expect(wrapper.findComponent(Popover).exists()).toBe(false)
	})
})
