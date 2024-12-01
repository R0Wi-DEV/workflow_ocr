<!--
  - @copyright Copyright (c) 2021 Robin Windey <ro.windey@gmail.com>
  -
  - @author Robin Windey <ro.windey@gmail.com>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<div>
		<SettingsItem :label="t('workflow_ocr', 'OCR language')"
			:info-text="t('workflow_ocr', 'The language(s) to be used for OCR processing')">
			<NcSelect v-model="selectedLanguages"
				track-by="langCode"
				:labelOutside="true"
				:tag-width="80"
				:placeholder="selectedLanguagesPlaceholder"
				:multiple="true"
				:options="availableLanguages" />
		</SettingsItem>
		<SettingsItem :label="t('workflow_ocr', 'Assign tags after OCR')"
			:info-text="t('workflow_ocr', 'These tags will be assigned to the file after OCR processing has finished')">
			<NcSelectTags v-model="tagsToAddAfterOcr"
				:labelOutside="true"
				:multiple="true">
				{{ tagsToAddAfterOcr }}
			</NcSelectTags>
		</SettingsItem>
		<SettingsItem :label="t('workflow_ocr', 'Remove tags after OCR')"
			:info-text="t('workflow_ocr', 'These tags will be removed from the file after OCR processing has finished')">
			<NcSelectTags v-model="tagsToRemoveAfterOcr"
				:labelOutside="true"
				:multiple="true">
				{{ tagsToRemoveAfterOcr }}
			</NcSelectTags>
		</SettingsItem>
		<SettingsItem :label="t('workflow_ocr', 'OCR mode')"
			:info-text="t('workflow_ocr', 'Apply this mode if file already has OCR content')">
			<div>
				<NcCheckboxRadioSwitch ref="ocrMode0"
					:checked.sync="ocrMode"
					type="radio"
					name="ocr_mode_radio"
					value="0">
					{{ t('workflow_ocr', 'Skip text') }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch ref="ocrMode1"
					:checked.sync="ocrMode"
					type="radio"
					name="ocr_mode_radio"
					value="1">
					{{ t('workflow_ocr', 'Redo OCR') }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch ref="ocrMode2"
					:checked.sync="ocrMode"
					type="radio"
					name="ocr_mode_radio"
					value="2">
					{{ t('workflow_ocr', 'Force OCR') }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch ref="ocrMode3"
					:checked.sync="ocrMode"
					type="radio"
					name="ocr_mode_radio"
					value="3">
					{{ t('workflow_ocr', 'Skip file completely') }}
				</NcCheckboxRadioSwitch>
			</div>
		</SettingsItem>
		<SettingsItem :label="t('workflow_ocr', 'Other settings')">
			<div>
				<NcCheckboxRadioSwitch ref="removeBackgroundSwitch"
					:disabled="removeBackgroundDisabled"
					:checked.sync="removeBackground"
					type="switch">
					{{ t('workflow_ocr', 'Remove background') }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch ref="keepOriginalFileVersionSwitch"
					:checked.sync="keepOriginalFileVersion"
					type="switch">
					{{ t('workflow_ocr', 'Keep original file version') }}
				</NcCheckboxRadioSwitch>
			</div>
		</SettingsItem>
	</div>
</template>

<script>

import { tesseractLanguageMapping } from '../constants.js'
import { getInstalledLanguages } from '../service/ocrBackendInfoService.js'
import SettingsItem from './SettingsItem.vue'
import { NcSelect, NcSelectTags, NcCheckboxRadioSwitch } from '@nextcloud/vue'

export default {
	name: 'WorkflowOcr',
	components: {
		NcSelect: NcSelect,
		NcSelectTags: NcSelectTags,
		NcCheckboxRadioSwitch: NcCheckboxRadioSwitch,
		SettingsItem: SettingsItem,
	},
	props: {
		// Will be set by the parent (serialized JSON value)
		value: {
			type: String,
			default: '',
		},
	},
	data: function() {
		return {
			availableLanguages: [],
			/*
			 * This is our JS data object model as single source of truth.
			 * Model structure which is captured by NC parent as JSON string:
			 * {
			 *   languages: [ 'de', 'en' ],
			 *   assignTagsAfterOcr: [1, 2, 3],
			 *   removeTagsAfterOcr: [42, 43],
			 *   removeBackground: true,
			 *	 keepOriginalFileVersion: true,
			 *   ocrMode: 0,
			 * }
			 * It's initially set after component creation by 'created'-hook.
			 */
			model: {},
		}
	},
	computed: {
		selectedLanguages: {
			get: function() {
				return this.model.languages
					? this.model.languages
						.map(langCode => tesseractLanguageMapping.find(lang => lang.langCode === langCode))
						.filter(entry => !!entry)
					: []
			},
			set: function(langArray) {
				this.$set(this.model, 'languages', langArray.map(lang => lang.langCode).filter(lang => lang !== null))
				this.modelChanged()
			},
		},
		tagsToAddAfterOcr: {
			get: function() {
				return this.model.tagsToAddAfterOcr ?? []
			},
			set: function(tagIdArray) {
				this.$set(this.model, 'tagsToAddAfterOcr', tagIdArray)
				this.modelChanged()
			},
		},
		tagsToRemoveAfterOcr: {
			get: function() {
				return this.model.tagsToRemoveAfterOcr ?? []
			},
			set: function(tagIdArray) {
				this.$set(this.model, 'tagsToRemoveAfterOcr', tagIdArray)
				this.modelChanged()
			},
		},
		removeBackground: {
			get: function() {
				return !!this.model.removeBackground
			},
			set: function(checked) {
				this.$set(this.model, 'removeBackground', !!checked)
				this.modelChanged()
			},
		},
		keepOriginalFileVersion: {
			get: function() {
				return !!this.model.keepOriginalFileVersion
			},
			set: function(checked) {
				this.$set(this.model, 'keepOriginalFileVersion', !!checked)
				this.modelChanged()
			},
		},
		ocrMode: {
			get: function() {
				return '' + (this.model.ocrMode ?? 0)
			},
			set: function(mode) {
				this.$set(this.model, 'ocrMode', parseInt(mode))
				// --redo-ocr is incompatible with --remove-background
				if (this.model.ocrMode === 1) {
					this.$set(this.model, 'removeBackground', false)
				}
				this.modelChanged()
			},
		},
		selectedLanguagesPlaceholder: function() {
			return this.t('workflow_ocr', 'Select language(s)')
		},
		removeBackgroundDisabled: function() {
			return this.model.ocrMode === 1
		},
	},
	beforeMount: async function() {
		const installedLanguagesCodes = await getInstalledLanguages()
		this.availableLanguages = tesseractLanguageMapping.filter(lang => installedLanguagesCodes.includes(lang.langCode))
	},
	created: function() {
		// Set the initial model by applying the JSON value set by parent after initial mount
		this.model = this.value ? JSON.parse(this.value) : {}
	},
	methods: {
		modelChanged: function() {
			this.$emit('input', JSON.stringify(this.model))
		},
	},
}
</script>

<style scoped>
	.NcMultiselect {
		width: 100%;
		max-width: 300px;
		margin: auto;
		text-align: center;
	}
</style>
