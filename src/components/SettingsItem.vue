<!--
  - @copyright Copyright (c) 2022 Robin Windey <ro.windey@gmail.com>
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
	<div class="div-table-row outer">
		<div class="div-table-col div-table-col-left">
			<span class="leftcol label">{{ label }}
				<NcPopover v-if="hasInfoText"
					trigger="hover focus">
					<template #trigger>
						<HelpCircleIcon class="info"
							:size="20"
							decorative
							title="" />
					</template>
					<p>{{ infoText }}</p>
				</NcPopover>
			</span>
			<br>
		</div>
		<div class="div-table-col">
			<slot />
		</div>
	</div>
</template>

<script>

import { NcPopover } from '@nextcloud/vue'
import HelpCircleIcon from 'vue-material-design-icons/HelpCircle.vue'

export default {
	name: 'SettingsItem',
	components: {
		NcPopover: NcPopover,
		HelpCircleIcon: HelpCircleIcon,
	},
	props: {
		label: {
			type: String,
			required: true,
		},
		infoText: {
			type: String,
			required: false,
			default: null,
		},
	},
	computed: {
		hasInfoText: function() {
			return this.infoText !== null
		},
	},
}
</script>

<style lang="scss" scoped>
	.label{
		display: inline-flex;
		align-items: center;
		justify-content: center;
	}

	.info {
		display: flex;
		align-items: center;
		justify-content: center;
		width: 44px;
		height: 44px;
		margin: -14px;
		margin-left: -10px;
		opacity: .7;

		&:hover, &:focus, &:active {
			opacity: 1;
		}
	}

	.outer {
		margin-bottom: 10px;
	}
</style>
