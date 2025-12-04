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
			propsData: {
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
			propsData: {
				infoText: infoText,
			},
			attachTo: document.body,
		})

		const icon = wrapper.find('.info')
		expect(icon.exists()).toBe(true)

        const popoverContentBefore = document.body.textContent
        expect(popoverContentBefore).not.toContain(infoText)

		// Simulate user hovering the icon
		await icon.trigger('mouseenter')
		await wrapper.vm.$nextTick()

		// Wait a bit for the popover to open
		await new Promise(resolve => setTimeout(resolve, 50))

		// Check that the popover content is now visible in the document
		const popoverContentAfter = document.body.textContent
		expect(popoverContentAfter).toContain(infoText)

		// Cleanup
		wrapper.destroy()
	})
})
