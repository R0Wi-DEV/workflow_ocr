
/**
 * @copyright Copyright (c) 2021 Robin Windey <ro.windey@gmail.com>
 *
 * @author Robin Windey <ro.windey@gmail.com>
 *
 * @license GNU AGPL version 3 or any later version
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
 *
 */

/** @class */
export default class GlobalSettings {

	/** @member {?number} */
	processorCount

	/**
	 * Create a new GlobalSettings from the given plain object data
	 *
	 * @param {object} data GlobalSettings config data to construct an instance from
	 * @param {?number} data.processorCount Processor count setting
	 */
	constructor(data) {
		data ??= {}
		this.processorCount = data.processorCount ? parseInt(data.processorCount) : null
	}

}
