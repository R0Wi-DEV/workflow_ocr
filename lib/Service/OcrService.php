<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020 Robin Windey <ro.windey@gmail.com>
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

namespace OCA\WorkflowOcr\Service;

use OCA\WorkflowOcr\Model\WorkflowSettings;
use OCA\WorkflowOcr\OcrProcessors\IOcrProcessorFactory;
use OCA\WorkflowOcr\OcrProcessors\OcrProcessorResult;
use OCP\Files\File;

class OcrService implements IOcrService {
	/** @var IOcrProcessorFactory */
	private $ocrProcessorFactory;

	/** @var IGlobalSettingsService */
	private $globalSettingsService;

	public function __construct(IOcrProcessorFactory $ocrProcessorFactory, IGlobalSettingsService $globalSettingsService) {
		$this->ocrProcessorFactory = $ocrProcessorFactory;
		$this->globalSettingsService = $globalSettingsService;
	}

	/** @inheritdoc */
	public function ocrFile(File $file, WorkflowSettings $settings) : OcrProcessorResult {
		$ocrProcessor = $this->ocrProcessorFactory->create($file->getMimeType());
		return $ocrProcessor->ocrFile($file, $settings, $this->globalSettingsService->getGlobalSettings());
	}
}
