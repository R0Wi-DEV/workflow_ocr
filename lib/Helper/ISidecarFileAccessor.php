<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2022 Robin Windey <ro.windey@gmail.com>
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

namespace OCA\WorkflowOcr\Helper;

interface ISidecarFileAccessor {
	/**
	 * 	Creates a new temporary sidecar file for OCR text content.
	 *  If a file was already created, the path to the existing file is returned.
	 *
	 * @return string|bool Path to the sidecar file or false if the file could not be created
	 */
	public function getOrCreateSidecarFile();

	/**
	 * 	Gets the content of the created sidecar file. File has to be created
	 * 	before calling this method.
	 */
	public function getSidecarFileContent(): string;
}
