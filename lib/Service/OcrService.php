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
use OCP\SystemTag\ISystemTagObjectMapper;
use OCP\SystemTag\TagNotFoundException;
use Psr\Log\LoggerInterface;

class OcrService implements IOcrService {
	/** @var IOcrProcessorFactory */
	private $ocrProcessorFactory;

	/** @var IGlobalSettingsService */
	private $globalSettingsService;

	/** @var ISystemTagObjectMapper */
	private $systemTagObjectMapper;

	/** @var LoggerInterface */
	private $logger;

	public function __construct(IOcrProcessorFactory $ocrProcessorFactory, IGlobalSettingsService $globalSettingsService, ISystemTagObjectMapper $systemTagObjectMapper, LoggerInterface $logger) {
		$this->ocrProcessorFactory = $ocrProcessorFactory;
		$this->globalSettingsService = $globalSettingsService;
		$this->systemTagObjectMapper = $systemTagObjectMapper;
		$this->logger = $logger;
	}

	/** @inheritdoc */
	public function ocrFile(File $file, WorkflowSettings $settings) : OcrProcessorResult {
		$ocrProcessor = $this->ocrProcessorFactory->create($file->getMimeType());
		$result = $ocrProcessor->ocrFile($file, $settings, $this->globalSettingsService->getGlobalSettings());
		$this->processTagsAfterSuccessfulOcr($file, $settings);
		return  $result;
	}

	private function processTagsAfterSuccessfulOcr(File $file, WorkflowSettings $settings) : void {
		$objectType = 'files';
		$fileId = strval($file->getId());
		$tagsToRemove = $settings->getTagsToRemoveAfterOcr();
		$tagsToAdd = $settings->getTagsToAddAfterOcr();

		foreach ($tagsToRemove as $tagToRemove) {
			try {
				$this->systemTagObjectMapper->unassignTags($fileId, $objectType, $tagToRemove);
			} catch (TagNotFoundException $ex) {
				$this->logger->warning("Cannot remove tag with id '$tagToRemove' because it was not found. Skipping.");
			}
		}

		foreach ($tagsToAdd as $tagToAdd) {
			try {
				$this->systemTagObjectMapper->assignTags($fileId, $objectType, $tagToAdd);
			} catch (TagNotFoundException $ex) {
				$this->logger->warning("Cannot add tag with id '$tagToAdd' because it was not found. Skipping.");
			}
		}
	}
}
