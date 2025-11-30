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
			<NcSelect v-model="selectedLanguages" track-by="langCode" :labelOutside="true" :tag-width="80"
				:placeholder="selectedLanguagesPlaceholder" :multiple="true" :options="availableLanguages" />
		</SettingsItem>
		<SettingsItem :label="t('workflow_ocr', 'Assign tags after OCR')"
			:info-text="t('workflow_ocr', 'These tags will be assigned to the file after OCR processing has finished')">
			<NcSelectTags v-model="model.tagsToAddAfterOcr" :labelOutside="true" :multiple="true">
				{{ model.tagsToAddAfterOcr }}
			</NcSelectTags>
		</SettingsItem>
		<SettingsItem :label="t('workflow_ocr', 'Remove tags after OCR')"
			:info-text="t('workflow_ocr', 'These tags will be removed from the file after OCR processing has finished')">
			<NcSelectTags v-model="model.tagsToRemoveAfterOcr" :labelOutside="true" :multiple="true">
				{{ model.tagsToRemoveAfterOcr }}
			</NcSelectTags>
		</SettingsItem>
		<SettingsItem :label="t('workflow_ocr', 'OCR mode')"
			:info-text="t('workflow_ocr', 'Apply this mode if file already has OCR content')">
			<div>
				<NcCheckboxRadioSwitch ref="ocrMode0" :checked.sync="ocrMode" type="radio" name="ocr_mode_radio"
					value="0">
					{{ t('workflow_ocr', 'Skip text') }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch ref="ocrMode1" :checked.sync="ocrMode" type="radio" name="ocr_mode_radio"
					value="1">
					{{ t('workflow_ocr', 'Redo OCR') }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch ref="ocrMode2" :checked.sync="ocrMode" type="radio" name="ocr_mode_radio"
					value="2">
					{{ t('workflow_ocr', 'Force OCR') }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch ref="ocrMode3" :checked.sync="ocrMode" type="radio" name="ocr_mode_radio"
					value="3">
					{{ t('workflow_ocr', 'Skip file completely') }}
				</NcCheckboxRadioSwitch>
			</div>
		</SettingsItem>
		<SettingsItem :label="t('workflow_ocr', 'Notifications')"
			:info-text="t('workflow_ocr', 'The asynchronous OCR process will send Nextcloud notifications. Use these settings to control them.')">
			<div>
				<HelpTextWrapper class="nccb-info-wrapper"
					:help-text="t('workflow_ocr', 'Skip notifications for ocrmypdf error code 2 (for example digitally signed PDFs)')">
					<NcCheckboxRadioSwitch ref="skipNotificationsOnInvalidPdf"
						:checked.sync="model.skipNotificationsOnInvalidPdf" type="switch">
						{{ t('workflow_ocr', 'Skip for invalid PDFs') }}
					</NcCheckboxRadioSwitch>
				</HelpTextWrapper>
				<HelpTextWrapper class="nccb-info-wrapper"
					:help-text="t('workflow_ocr', 'Skip notifications for ocrmypdf error code 8 (for example password protected PDFs)')">
					<NcCheckboxRadioSwitch ref="skipNotificationsOnEncryptedPdf"
						:checked.sync="model.skipNotificationsOnEncryptedPdf" type="switch">
						{{ t('workflow_ocr', 'Skip for encrypted PDFs') }}
					</NcCheckboxRadioSwitch>
				</HelpTextWrapper>
				<HelpTextWrapper class="nccb-info-wrapper"
					:help-text="t('workflow_ocr', 'Skip notifications for ocrmypdf error code 8 (for example password protected PDFs)')">
					<NcCheckboxRadioSwitch ref="skipNotificationsOnEncryptedPdf"
						:checked.sync="model.skipNotificationsOnEncryptedPdf" type="switch">
						{{ t('workflow_ocr', 'Send success notification') }}
					</NcCheckboxRadioSwitch>
				</HelpTextWrapper>
			</div>
		</SettingsItem>
		<SettingsItem :label="t('workflow_ocr', 'Other settings')">
			<div>
				<NcCheckboxRadioSwitch ref="removeBackgroundSwitch" :disabled="removeBackgroundDisabled"
					:checked.sync="model.removeBackground" type="switch">
					{{ t('workflow_ocr', 'Remove background') }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch ref="keepOriginalFileVersion" :checked.sync="model.keepOriginalFileVersion"
					type="switch">
					{{ t('workflow_ocr', 'Keep original file version') }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch ref="keepOriginalFileDate" :checked.sync="model.keepOriginalFileDate"
					type="switch">
					{{ t('workflow_ocr', 'Keep original file modification date') }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch ref="createSidecarFile" :checked.sync="model.createSidecarFile" type="switch">
					{{ t('workflow_ocr', 'Create sidecar text file') }}
				</NcCheckboxRadioSwitch>
			</div>
		</SettingsItem>
		<div>
			<NcTextField :value.sync="model.customCliArgs" :label="t('workflow_ocr', 'Custom ocrMyPdf CLI arguments')"
				ref="customCliArgs">
			</NcTextField>
		</div>
	</div>
</template>

<script>

import { tesseractLanguageMapping } from '../constants.js'
import { getInstalledLanguages } from '../service/ocrBackendInfoService.js'
import SettingsItem from './SettingsItem.vue'
import HelpTextWrapper from './HelpTextWrapper.vue'
import { NcSelect, NcSelectTags, NcCheckboxRadioSwitch, NcTextField, NcPopover } from '@nextcloud/vue'
import HelpCircleIcon from 'vue-material-design-icons/HelpCircle.vue'

export default {
	name: 'WorkflowOcr',
	components: {
		NcSelect: NcSelect,
		NcSelectTags: NcSelectTags,
		NcCheckboxRadioSwitch: NcCheckboxRadioSwitch,
		NcTextField: NcTextField,
		SettingsItem: SettingsItem,
		NcPopover: NcPopover,
		HelpCircleIcon: HelpCircleIcon,
		HelpTextWrapper: HelpTextWrapper,
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
			 *   tagsToAddAfterOcr: [1, 2, 3],
			 *   tagsToRemoveAfterOcr: [42, 43],
			 *   removeBackground: true,
			 *   keepOriginalFileVersion: true,
			 *   keepOriginalFileDate: true,
			 *   sendSuccessNotification: true,
			 *   ocrMode: 0,
			 *   customCliArgs: '--rotate-pages-threshold 8',
			 *   createSidecarFile: false,
			 *   skipNotificationsOnInvalidPdf: false,
			 *   skipNotificationsOnEncryptedPdf: false,
			 * }
			 * It's initially set after component creation by 'created'-hook.
			 */
			model: {
				languages: [],
				tagsToAddAfterOcr: [],
				tagsToRemoveAfterOcr: [],
				removeBackground: false,
				keepOriginalFileVersion: false,
				keepOriginalFileDate: false,
				sendSuccessNotification: false,
				ocrMode: 0,
				customCliArgs: '',
				createSidecarFile: false,
				skipNotificationsOnInvalidPdf: false,
				skipNotificationsOnEncryptedPdf: false,
			},
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
	watch: {
		value: {
			immediate: true,
			handler(newValue) {
				if (newValue) {
					// Merge with defaults
					this.model = { ...this.model, ...JSON.parse(newValue) }
				}
			},
		},
		model: {
			deep: true,
			handler(newValue) {
				// Publish serialized model to parent
				this.$emit('input', JSON.stringify(this.model))
			},
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

	.nccb-info-wrapper {
		max-width: 300px;
	}
</style>
