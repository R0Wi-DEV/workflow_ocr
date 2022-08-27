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

namespace OCA\WorkflowOcr\Listener;

use OCA\WorkflowOcr\Events\TextRecognizedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class TextRecognizedListener implements IEventListener {

	/** @var ContainerInterface */
	private $container;
	/** @var LoggerInterface */
	private $logger;

	public function __construct(ContainerInterface $container, LoggerInterface $logger) {
		$this->container = $container;
		$this->logger = $logger;
	}

	public function handle(Event $event): void {
		if (!$event instanceof TextRecognizedEvent) {
			return;
		}
		file_put_contents('/var/www/html/ocr.log', print_r(['step' => 'handling TextRecognizedEvent','filePath' => $event->getFile()->getPath()], true), FILE_APPEND);
	}
}
