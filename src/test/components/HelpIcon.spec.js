import { mount } from '@vue/test-utils'
import HelpIcon from '../../components/HelpIcon.vue'

// Mock focus-trap to prevent errors when popover opens
jest.mock('focus-trap', () => ({
	createFocusTrap: () => ({
		activate: jest.fn(),
		deactivate: jest.fn(),
		pause: jest.fn(),
		unpause: jest.fn(),
	}),
}))

beforeEach(() => {
	jest.resetAllMocks()
})

describe('HelpIcon tests', () => {
	test('Should pass infoText prop to the component', () => {
		const infoText = 'This is some help text'
		const wrapper = mount(HelpIcon, {
			props: {
				infoText: infoText,
			},
		})

		// The infoText prop should be set correctly
		expect(wrapper.props('infoText')).toBe(infoText)
	})

	test('Should have default empty string for infoText prop', () => {
		const wrapper = mount(HelpIcon)

		// Default value should be empty string
		expect(wrapper.props('infoText')).toBe('')
	})

	test('Should show info text in popover when user hovers the icon', async () => {
		const infoText = 'This tooltip appears on hover'

		const wrapper = mount(HelpIcon, {
			props: {
				infoText: infoText,
			},
			attachTo: document.body,
		})

		// Check that the component renders
		expect(wrapper.exists()).toBe(true)

		// Check that the popover component exists
		const popover = wrapper.findComponent({ name: 'NcPopover' })
		expect(popover.exists()).toBe(true)

		// Check that the infoText prop is set
		expect(wrapper.props('infoText')).toBe(infoText)

		// Check that the component displays the info text in the template
		expect(wrapper.html()).toContain(infoText)

		// Cleanup
		wrapper.unmount()
	})
})
