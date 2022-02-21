<?php
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
	 *
	 * This file represents the frontend snippet HTML code for global settings page.
	 */
	use OCA\WorkflowOcr\AppInfo\Application;

	// Add GlobalSettings.vue component
	script(Application::APP_NAME, Application::APP_NAME . '-globalSettings');
?>

<!-- Vue mountpoint -->
<div id="workflow_ocr_globalsettings"></div>