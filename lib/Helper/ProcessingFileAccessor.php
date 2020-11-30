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

namespace OCA\WorkflowOcr\Helper;

/**
 * This class is a singleton which holds the id
 * of the currently OCR processed file. This ensures
 * that a files is not added to the processing queue
 * if the 'postWrite' hook was triggered by a new
 * version created by the OCR process.
 */
class ProcessingFileAccessor implements IProcessingFileAccessor {
	/** @var ?int */
	private $currentlyProcessedFileId;

	/** @var ProcessingFileAccessor */
	private static $instance;
	public static function getInstance() : ProcessingFileAccessor {
		if (self::$instance === null) {
			self::$instance = new ProcessingFileAccessor();
		}
		return self::$instance;
	}

	private function __construct() {
		// Just ensuring singleton instance ...
	}

	/**
	 * @inheritdoc
	 */
	public function getCurrentlyProcessedFileId() : ?int {
		return $this->currentlyProcessedFileId;
	}

	/**
	 * @inheritdoc
	 */
	public function setCurrentlyProcessedFileId(?int $fileId) : void {
		$this->currentlyProcessedFileId = $fileId;
	}
}
