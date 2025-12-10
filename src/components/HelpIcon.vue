<!--
  - @copyright Copyright (c) 2025 Robin Windey <ro.windey@gmail.com>
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
	<NcPopover :triggers="['hover']" no-focus-trap>
		<template #trigger>
			<HelpCircleIcon
				class="info"
				:size="20"
				decorative
				title="" />
		</template>
		<p>{{ infoText }}</p>
	</NcPopover>
</template>

<script>

import { NcPopover } from '@nextcloud/vue'
import HelpCircleIcon from 'vue-material-design-icons/HelpCircle.vue'

export default {
	name: 'HelpIcon',
	components: {
		NcPopover,
		HelpCircleIcon,
	},

	props: {
		infoText: {
			type: String,
			required: false,
			default: '',
		},
	},
}
</script>
