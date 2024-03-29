<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2022 Robin Windey <ro.windey@gmail.com>
 *
 * @author g-schmitz <gschmitz@email.com>
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

use OCA\WorkflowOcr\Events\TextRecognizedEvent;
use OCA\WorkflowOcr\OcrProcessors\OcrProcessorResult;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\File;

class EventService implements IEventService {
	/** @var IEventDispatcher */
	private $eventDispatcher;

	public function __construct(IEventDispatcher $eventDispatcher) {
		$this->eventDispatcher = $eventDispatcher;
	}

	/**
	 * @return void
	 */
	public function textRecognized(OcrProcessorResult $result, File $node) {
		$event = new TextRecognizedEvent($result->getRecognizedText(), $node);
		$this->eventDispatcher->dispatchTyped($event);
	}
}
