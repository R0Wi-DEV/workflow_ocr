// Mock for @nextcloud/vue components
import { defineComponent, h } from 'vue'

// Create simple mock components that render a div with the component name
const createMockComponent = (name, additionalProps = {}) => defineComponent({
	name,
	props: Object.assign({
		modelValue: {},
		value: {},
	}, additionalProps),
	emits: ['update:modelValue', 'update:value', 'input'],
	setup(props, { slots, attrs, emit }) {
		return () => h('div', { 
			class: name, 
			...attrs,
			'data-test-id': name 
		}, slots.default ? slots.default() : null)
	}
})

export const NcSettingsSection = createMockComponent('NcSettingsSection')
export const NcSelect = createMockComponent('NcSelect')
export const NcSelectTags = createMockComponent('NcSelectTags')
export const NcCheckboxRadioSwitch = defineComponent({
	name: 'NcCheckboxRadioSwitch',
	props: {
		modelValue: {},
		type: { type: String },
		value: {},
		disabled: { type: Boolean, default: false },
	},
	emits: ['update:modelValue'],
	computed: {
		checked() {
			return this.modelValue
		}
	},
	render() {
		return h('div', {
			class: 'NcCheckboxRadioSwitch',
			'data-test-id': 'NcCheckboxRadioSwitch'
		}, this.$slots.default ? this.$slots.default() : null)
	}
})
export const NcTextField = defineComponent({
	name: 'NcTextField',
	props: {
		value: {},
		label: { type: String },
	},
	emits: ['update:value'],
	render() {
		return h('div', {
			class: 'NcTextField',
			'data-test-id': 'NcTextField'
		}, this.$slots.default ? this.$slots.default() : null)
	}
})
export const NcPopover = createMockComponent('NcPopover')
