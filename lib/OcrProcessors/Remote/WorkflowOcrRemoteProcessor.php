<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2025 Robin Windey <ro.windey@gmail.com>
 *
 *  @license GNU AGPL version 3 or any later version
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

namespace OCA\WorkflowOcr\OcrProcessors\Remote;

use OCA\WorkflowOcr\Exception\OcrNotPossibleException;
use OCA\WorkflowOcr\Model\GlobalSettings;
use OCA\WorkflowOcr\Model\WorkflowSettings;
use OCA\WorkflowOcr\OcrProcessors\ICommandLineUtils;
use OCA\WorkflowOcr\OcrProcessors\IOcrProcessor;
use OCA\WorkflowOcr\OcrProcessors\OcrProcessorResult;
use OCA\WorkflowOcr\OcrProcessors\Remote\Client\IApiClient;
use OCA\WorkflowOcr\OcrProcessors\Remote\Client\Model\ErrorResult;
use OCP\Files\File;
use Psr\Log\LoggerInterface;

/**
 * OCR Processor which utilizes the Workflow OCR Backend remote service to perform OCR.
 */
class WorkflowOcrRemoteProcessor implements IOcrProcessor {
	public function __construct(
		private IApiClient $apiClient,
		private ICommandLineUtils $commandLineUtils,
		private LoggerInterface $logger,
	) {

	}
	 
	public function ocrFile(File $file, WorkflowSettings $settings, GlobalSettings $globalSettings): OcrProcessorResult {
		$ocrMyPdfParameters = $this->commandLineUtils->getCommandlineArgs($settings, $globalSettings);
		$fileResource = $file->fopen('rb');
		$fileName = $file->getName();
		
		$this->logger->debug('Sending OCR request to remote backend');
		$apiResult = $this->apiClient->processOcr($fileResource, $fileName, $ocrMyPdfParameters);
		$this->logger->debug('OCR result received', ['apiResult' => $apiResult]);

		if ($apiResult instanceof ErrorResult) {
			throw new OcrNotPossibleException($apiResult->getMessage());
		}

		return new OcrProcessorResult(
			base64_decode($apiResult->getFileContent()),
			pathinfo($apiResult->getFilename(), PATHINFO_EXTENSION),
			$apiResult->getRecognizedText()
		);
	}
}
