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

namespace OCA\WorkflowOcr\Events;

use OCP\EventDispatcher\Event;
use OCP\Files\File;

/**
 * Class TextRecognizedEvent
 *
 * @package OCA\WorkflowOcr\Events
 */
class TextRecognizedEvent extends Event {
	/** @var string */
	private $recognizedText;

	/** @var File */
	private $file;


	/**
	 * TextRecognizedEvent constructor.
	 */
	public function __construct(string $recognizedText, File $file) {
		parent::__construct();
		
		$this->recognizedText = $recognizedText;
		$this->file = $file;
	}

	/**
	 * @return string $recognizedText
	 */
	public function getRecognizedText(): string {
		return $this->recognizedText;
	}

	/**
	 * @return File $file
	 */
	public function getFile(): File {
		return $this->file;
	}
}
