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

namespace OCA\WorkflowOcr\Service;

use OCA\WorkflowOcr\Exception\CommandException;

interface IOcrBackendInfoService {

	/**
	 * Returns all languages that are supported by the OCR backend.
	 * Languages will be returned as an array of language-code-strings,
	 * currently defined at https://github.com/tesseract-ocr/tesseract/blob/main/doc/tesseract.1.asc#languages.
	 * @return array string[]
	 * @throws CommandException
	 */
	public function getInstalledLanguages() : array;
}
