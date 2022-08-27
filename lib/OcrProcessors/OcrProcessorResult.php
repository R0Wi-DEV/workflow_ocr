<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020 Robin Windey <ro.windey@gmail.com>
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

/**
 * Represents a file which was processed via OCR.
 */
class OcrProcessorResult {
	/** @var string */
	private $fileContent;
	/** @var string */
	private $fileExtension;
	/** @var string */
	private $recognizedText;


	public function __construct(string $fileContent, string $fileExtension, string $recognizedText) {
		$this->fileContent = $fileContent;
		$this->fileExtension = $fileExtension;
		$this->recognizedText = $recognizedText;
	}

	public function getFileContent(): string {
		return $this->fileContent;
	}

	public function getFileExtension(): string {
		return $this->fileExtension;
	}

	public function getRecognizedText(): string {
		return $this->recognizedText;
	}
}
