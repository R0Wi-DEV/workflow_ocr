<?php

declare(strict_types=1);

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

namespace OCA\WorkflowOcr\Controller;

use OCA\WorkflowOcr\Model\GlobalSettings;
use OCA\WorkflowOcr\Service\IGlobalSettingsService;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * This is the backend API controller for the Admin.vue component.
 */
class GlobalSettingsController extends ControllerBase {
	/** @var IGlobalSettingsService */
	private $globalSettingsService;

	public function __construct($appName, IRequest $request, IGlobalSettingsService $globalSettingsService) {
		parent::__construct($appName, $request);
		$this->globalSettingsService = $globalSettingsService;
	}

	/**
	 * @return JSONResponse
	 */
	public function getGlobalSettings() : JSONResponse {
		return $this->tryExecute(function () {
			return $this->globalSettingsService->getGlobalSettings();
		});
	}

	/**
	 * @param array $globalSettings to be parsed into a GlobalSettings object
	 * @return JSONResponse
	 */
	public function setGlobalSettings(array $globalSettings) : JSONResponse {
		return $this->tryExecute(function () use ($globalSettings) {
			$globalSettingsObject = new GlobalSettings();
			$globalSettingsObject->processorCount = $globalSettings['processorCount'];

			$this->globalSettingsService->setGlobalSettings($globalSettingsObject);
			return $this->globalSettingsService->getGlobalSettings();
		});
	}
}
