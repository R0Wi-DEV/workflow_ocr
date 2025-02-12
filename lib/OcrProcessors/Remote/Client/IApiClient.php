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

namespace OCA\WorkflowOcr\OcrProcessors\Remote\Client;

use OCA\WorkflowOcr\OcrProcessors\Remote\Client\Model\ErrorResult;
use OCA\WorkflowOcr\OcrProcessors\Remote\Client\Model\OcrResult;

/**
 * Remote API supported by Workflow OCR Backend.
 */
interface IApiClient {
	/**
	 * Process OCR on the given file.
	 * @param resource $file The file to process OCR on.
	 * @param string $fileName The name of the file.
	 * @param string $ocrMyPdfParameters The parameters to pass to ocrmypdf.
	 * @return OcrResult|ErrorResult The result of the OCR operation.
	 */
	public function processOcr($file, string $fileName, string $ocrMyPdfParameters): OcrResult|ErrorResult;

	/**
	 * Get the list of installed Tesseract languages.
	 * @return string[]
	 */
	public function getLanguages(): array;

	/**
	 * Send a heartbeat to the remote backend.
	 */
	public function heartbeat(): bool;
}
