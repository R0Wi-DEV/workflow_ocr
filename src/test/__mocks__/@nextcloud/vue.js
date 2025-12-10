import { h } from 'vue'

function make(name) {
	return {
		name,
		render() {
			return h('div', this.$slots.default ? this.$slots.default() : [])
		},
	}
}

export const NcPopover = make('NcPopover')
export const NcSettingsSection = make('NcSettingsSection')
export const NcSelect = make('NcSelect')
export const NcSelectTags = make('NcSelectTags')
export const NcCheckboxRadioSwitch = make('NcCheckboxRadioSwitch')
export const NcTextField = make('NcTextField')
export const NcColorPicker = make('NcColorPicker')
export const NcDateTimePicker = make('NcDateTimePicker')

export default {
	NcPopover,
	NcSettingsSection,
	NcSelect,
	NcSelectTags,
	NcCheckboxRadioSwitch,
	NcTextField,
	NcColorPicker,
	NcDateTimePicker,
}
