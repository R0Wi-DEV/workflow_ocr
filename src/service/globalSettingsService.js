/**
 * @copyright Copyright (c) 2021 Robin Windey <ro.windey@gmail.com>
 *
 * @author Robin Windey <ro.windey@gmail.com>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const relativeUrl = '/apps/workflow_ocr/globalSettings'

/**
 * Get global settings for workflow OCR app from the backend
 *
 * @return {Promise} Global settings for workflow OCR app
 */
export async function getGlobalSettings() {
	const url = generateUrl(relativeUrl)
	const axiosResponse = await axios.get(url)
	return axiosResponse.data
}

/**
 * Saves the given settings
 *
 * @param {object} globalSettings Settings to be saved
 * @return {Promise} Global settings for workflow OCR app
 */
export async function setGlobalSettings(globalSettings) {
	const url = generateUrl(relativeUrl)
	const axiosResponse = await axios.put(url, { globalSettings })
	return axiosResponse.data
}
