<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2022 Robin Windey <ro.windey@gmail.com>
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

use OCA\WorkflowOcr\Service\IOcrBackendInfoService;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * This is the backend API controller which provides informations about the OCR backend system.
 */
class OcrBackendInfoController extends ControllerBase {
	/** @var IOcrBackendInfoService */
	private $ocrBackendInfoService;

	public function __construct($AppName, IRequest $request, IOcrBackendInfoService $ocrBackendInfoService) {
		parent::__construct($AppName, $request);
		$this->ocrBackendInfoService = $ocrBackendInfoService;
	}

	/**
	 * @return JSONResponse
	 */
	public function getInstalledLanguages() : JSONResponse {
		return $this->tryExecute(function () {
			return $this->ocrBackendInfoService->getInstalledLanguages();
		});
	}
}
