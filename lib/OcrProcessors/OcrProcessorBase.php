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

namespace OCA\WorkflowOcr\OcrProcessors;

use OCA\WorkflowOcr\Exception\OcrAlreadyDoneException;
use OCA\WorkflowOcr\Exception\OcrNotPossibleException;
use OCA\WorkflowOcr\Exception\OcrResultEmptyException;
use OCA\WorkflowOcr\Model\GlobalSettings;
use OCA\WorkflowOcr\Model\WorkflowSettings;
use OCP\Files\File;
use Psr\Log\LoggerInterface;

/**
 * Base class for all OCR processors.
 */
abstract class OcrProcessorBase implements IOcrProcessor {
	public function __construct(
		protected LoggerInterface $logger,
	) {
	}

	public function ocrFile(File $file, WorkflowSettings $settings, GlobalSettings $globalSettings): OcrProcessorResult {
		$fileName = $file->getName();
		$fileResource = $this->doFilePreprocessing($file);
		try {
			[$success, $fileContent, $recognizedText, $exitCode, $errorMessage] = $this->doOcrProcessing($fileResource, $fileName, $settings, $globalSettings);
			if (!$success) {
				$this->throwException($errorMessage, $exitCode);
			}
			if (!$recognizedText) {
				$this->logger->info('Recognized text was empty');
			}
			return $fileContent ? new OcrProcessorResult($fileContent, $recognizedText) : throw new OcrResultEmptyException('OCRmyPDF did not produce any output for file ' . $fileName);
		} finally {
			if (is_resource($fileResource)) {
				fclose($fileResource);
			}
		}
	}

	/**
	 * Perform the actual OCR processing. Implementation is specific to the OCR processor. Might me local or remote.
	 * Should return [$success, $fileContent, $recognizedText, $exitCode, $errorMessage]
	 * @param resource $fileResource
	 * @param string $fileName
	 * @param WorkflowSettings $settings
	 * @param GlobalSettings $globalSettings
	 * @return array
	 */
	abstract protected function doOcrProcessing($fileResource, string $fileName, WorkflowSettings $settings, GlobalSettings $globalSettings): array;

	/**
	 * @return resource|false
	 */
	private function doFilePreprocessing(File $file) {
		return $file->getMimeType() != 'image/png' ? $file->fopen('rb') : $this->removeAlphaChannelFromImage($file);
	}

	/**
	 * @return resource|false
	 */
	private function removeAlphaChannelFromImage(File $file) {
		// Remove any alpha channel from the PNG image (if any)
		$imageResource = null;
		try {
			$this->logger->debug('Checking if PNG has alpha channel');

			$imageResource = $file->fopen('rb');
			$image = new \Imagick();
			$image->readImageFile($imageResource, $file->getName());
			$alphaChannel = $image->getImageAlphaChannel();

			if (!$alphaChannel) {
				$this->logger->debug('PNG does not have alpha channel, no need to remove it');
				return $imageResource;
			}

			$this->logger->debug('PNG has alpha channel, removing it');
			$image->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
			$image->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
			$imageBlob = $image->getImageBlob();
			$stream = fopen('php://temp', 'r+');
			fwrite($stream, $imageBlob);
			rewind($stream);
			return $stream;
		} finally {
			if (is_resource($imageResource)) {
				fclose($imageResource);
			}
			$image->clear();
			$image->destroy();
		}
	}

	/**
	 * Throws an appropriate exception based on the error message and exit code.
	 */
	private function throwException($errorMessage, $exitCode) {
		if ($exitCode === 6) {
			throw new OcrAlreadyDoneException('File appears to contain text so it may not need OCR. Message: ' . $errorMessage);
		}
		throw new OcrNotPossibleException($errorMessage);
	}
}
