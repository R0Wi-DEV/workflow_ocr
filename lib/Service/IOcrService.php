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

interface IOcrService {
	/**
	 * Processes OCR on the given file. Creates a new file version and emits appropriate events.
	 *
	 * @param mixed $argument The argument from the \OCP\BackgroundJob\Job->run which executes this method
	 *
	 * @throws \OCA\WorkflowOcr\Exception\OcrNotPossibleException
	 * @throws \OCA\WorkflowOcr\Exception\OcrProcessorNotFoundException
	 * @throws \OCA\WorkflowOcr\Exception\OcrResultEmptyException
	 * @throws \InvalidArgumentException
	 */
	public function runOcrProcessWithJobArgument($argument) : void;

	/**
	 * Processes OCR on the given file. Creates a new file version and emits appropriate events.
	 *
	 * @param int $fileId The id if the file to be processed
	 * @param string $uid The id of the user who has access to this file
	 * @param WorkflowSettings $settings The settings to be used for processing
	 *
	 * @throws \OCA\WorkflowOcr\Exception\OcrNotPossibleException
	 * @throws \OCA\WorkflowOcr\Exception\OcrProcessorNotFoundException
	 * @throws \OCA\WorkflowOcr\Exception\OcrResultEmptyException
	 * @throws \InvalidArgumentException
	 */
	public function runOcrProcess(int $fileId, string $uid, WorkflowSettings $settings) : void;
}
