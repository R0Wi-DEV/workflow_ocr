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
		<NcSettingsSection name="Workflow OCR"
			:description="description"
			doc-url="https://github.com/R0Wi/workflow_ocr/blob/master/README.md#global-settings">
			<div class="div-table-row">
				<div class="div-table-col div-table-col-left">
					<span class="leftcol">{{ translate('Processor cores:') }}</span>
					<br>
					<em>{{ translate('Number of CPU cores to be used for OCR processing. If not set all cores on the system will be used.') }}</em>
				</div>
				<div class="div-table-col">
					<input v-model="settings.processorCount"
						name="processorCount"
						type="number"
						@input="save">
				</div>
			</div>
		</NcSettingsSection>
	</div>
</template>

<script>

import { appId } from '../constants.js'
import { NcSettingsSection } from '@nextcloud/vue'
import { getGlobalSettings, setGlobalSettings } from '../service/globalSettingsService.js'

export default {
	name: 'GlobalSettings',
	components: {
		NcSettingsSection: NcSettingsSection,
	},
	data: () => ({
		settings: {},
	}),
	computed: {
		description: function() {
			return this.translate('Global settings applied to all OCR workflows.')
		},
	},
	mounted: function() {
		this.loadSettings()
	},
	methods: {
		save: async function() {
			this.settings = await setGlobalSettings(this.settings)
		},
		loadSettings: async function() {
			this.settings = await getGlobalSettings()
		},
		translate: function(str) {
			return t(appId, str)
		},
	},
}
</script>

<style scoped>
	.div-table-row {
		margin-bottom: 1em;
	}

	input {
		background-color: rgba(255, 255, 255, 0.18);
	}
</style>
