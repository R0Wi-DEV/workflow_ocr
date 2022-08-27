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

namespace OCA\WorkflowOcr\Events;

use OCP\EventDispatcher\Event;
use OCA\WorkflowOcr\OcrProcessors\OcrProcessorResult;
use OCP\Files\File;

/**
 * Class TextRecognizedEvent
 *
 * @package OCA\WorkflowOcr\Events
 */
class TextRecognizedEvent extends Event {


	/** @var OcrProcessorResult */
	protected $result;

	/** @var File */
	protected $file;


	/**
	 * TextRecognizedEvent constructor.
	 *
	 * @param OcrProcessorResult $result
	 */
	public function __construct(OcrProcessorResult $result, File $file) {
		parent::__construct();
		
		$this->result = $result;
		$this->file = $file;
	}

	/**
	 * @return OcrProcessorResult $result
	 */
	public function getResult(): OcrProcessorResult {
		return $this->result;
	}

	/**
	 * @return File $file
	 */
	public function getFile(): File {
		return $this->file;
	}
}
