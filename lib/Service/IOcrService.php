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

interface IOcrService {
	/**
	 * Processes OCR on the given file
	 * @param string $mimeType        The mimetype of the file to be processed
	 * @param string $fileContent     The file to be processed
	 * @return string                 The processed pdf as byte string
	 * @throws \OCA\WorkflowOcr\Exception\OcrNotPossibleException
	 * @throws \OCA\WorkflowOcr\Exception\OcrProcessorNotFoundException
	 */
	public function ocrFile(string $mimeType, string $fileContent) : string;
}
