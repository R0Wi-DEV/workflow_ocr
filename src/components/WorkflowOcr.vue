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
		<SettingsItem label="OCR language"
			info-text="The language(s) to be used for OCR processing">
			<Multiselect v-model="selectedLanguages"
				track-by="langCode"
				label="label"
				:tag-width="80"
				:placeholder="selectedLanguagesPlaceholder"
				:multiple="true"
				:options="availableLanguages" />
		</SettingsItem>
		<SettingsItem label="Assign tags after OCR"
			info-text="These tags will be assigned to the file after OCR processing has finished">
			<MultiselectTags v-model="tagsToAddAfterOcr"
				:multiple="true">
				{{ tagsToAddAfterOcr }}
			</MultiselectTags>
		</SettingsItem>
		<SettingsItem label="Remove tags after OCR"
			info-text="These tags will be removed from the file after OCR processing has finished">
			<MultiselectTags v-model="tagsToRemoveAfterOcr"
				:multiple="true">
				{{ tagsToRemoveAfterOcr }}
			</MultiselectTags>
		</SettingsItem>
		<CheckboxRadioSwitch :checked.sync="removeBackground"
			type="switch">
			{{ translate('Remove background') }}
		</CheckboxRadioSwitch>
	</div>
</template>

<script>

import { appId } from '../constants'
import SettingsItem from './SettingsItem'
import Multiselect from '@nextcloud/vue/dist/Components/Multiselect'
import MultiselectTags from '@nextcloud/vue/dist/Components/MultiselectTags'
import CheckboxRadioSwitch from '@nextcloud/vue/dist/Components/CheckboxRadioSwitch'

const availableLanguages = [
	{ label: 'German', langCode: 'de' },
	{ label: 'English', langCode: 'en' },
	{ label: 'French', langCode: 'fr' },
	{ label: 'Italian', langCode: 'it' },
	{ label: 'Spanish', langCode: 'es' },
	{ label: 'Portuguese', langCode: 'pt' },
	{ label: 'Russian', langCode: 'ru' },
	{ label: 'Chinese - Simplified', langCode: 'chi' },
	{ label: 'Estonian', langCode: 'est' },
	{ label: 'Slovak', langCode: 'slk ' },
]

export default {
	name: 'WorkflowOcr',
	components: {
		Multiselect: Multiselect,
		MultiselectTags: MultiselectTags,
		CheckboxRadioSwitch: CheckboxRadioSwitch,
		SettingsItem: SettingsItem,
	},
	props: {
		// Will be set by the parent (serialized JSON value)
		value: {
			type: String,
			default: '',
		},
	},
	data: () => ({
		availableLanguages: availableLanguages,
	}),
	computed: {
		selectedLanguages: {
			get: function() {
				const model = this.getModel()
				return model.languages
					? model.languages.map(langCode => {
						const entry = availableLanguages.find(lang => lang.langCode === langCode)
						if (!entry) {
							return null
						}
						entry.label = this.translate(entry.label)
						return entry
					}).filter(entry => entry !== null)
					: []
			},
			set: function(langArray) {
				const model = this.getModel()
				model.languages = langArray.map(lang => lang.langCode).filter(lang => lang !== null)
				this.$emit('input', JSON.stringify(model))
			},
		},
		tagsToAddAfterOcr: {
			get: function() {
				const model = this.getModel()
				return model.tagsToAddAfterOcr ?? []
			},
			set: function(tagIdArray) {
				const model = this.getModel()
				model.tagsToAddAfterOcr = tagIdArray
				this.$emit('input', JSON.stringify(model))
			},
		},
		tagsToRemoveAfterOcr: {
			get: function() {
				const model = this.getModel()
				return model.tagsToRemoveAfterOcr ?? []
			},
			set: function(tagIdArray) {
				const model = this.getModel()
				model.tagsToRemoveAfterOcr = tagIdArray
				this.$emit('input', JSON.stringify(model))
			},
		},
		removeBackground: {
			get: function() {
				const model = this.getModel()
				return !!model.removeBackground
			},
			set: function(checked) {
				const model = this.getModel()
				model.removeBackground = !!checked
				this.$emit('input', JSON.stringify(model))
			},
		},
		selectedLanguagesPlaceholder: function() {
			return this.translate('Select language(s)')
		},
	},
	methods: {
		getModel: function() {
			/*
			 * Model structure which is captured by NC parent as JSON string:
			 * {
			 *   languages: [ 'de', 'en' ],
			 *   assignTagsAfterOcr: [1, 2, 3],
			 *   removeTagsAfterOcr: [42, 43],
			 *   removeBackground: true,
			 * }
			 */
			return this.value ? JSON.parse(this.value) : {}
		},
		translate: function(str) {
			return t(appId, str)
		},
	},
}
</script>

<style scoped>
	.multiselect {
		width: 100%;
		max-width: 300px;
		margin: auto;
		text-align: center;
	}
</style>
