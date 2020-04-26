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

namespace OCA\WorkflowOcr;

use OCA\WorkflowEngine\Entity\File;
use OCA\WorkflowOcr\AppInfo\Application;
use OCP\BackgroundJob\IJobList;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\GenericEvent;
use OCP\Files\IRootFolder;
use OCP\IL10N;
use OCP\IUserSession;
use OCP\WorkflowEngine\IManager;
use OCP\WorkflowEngine\IRuleMatcher;
use OCP\WorkflowEngine\ISpecificOperation;
use OCP\ILogger;
use OCA\WorkflowOcr\BackgroundJobs\ProcessFileJob;

class Operation implements ISpecificOperation {

	/** @var IJobList */
	private $jobList;
	/** @var IL10N */
	private $l;
	/** @var ILogger */
	private $logger;

	public function __construct(IManager $workflowEngineManager, IJobList $jobList, IL10N $l, IUserSession $session, IRootFolder $rootFolder, ILogger $logger) {
		$this->jobList = $jobList;
		$this->l = $l;
		$this->logger = $logger;
	}

	/**
	 * @throws \UnexpectedValueException
	 * @since 9.1
	 */
	public function validateOperation(string $name, array $checks, string $operation): void {
		// nothing to do
	}

	public function getDisplayName(): string {
		return $this->l->t('OCR file'); // TODO
	}

	public function getDescription(): string {
		return $this->l->t('OCR processing via workflow'); // TODO
	}

	public function getIcon(): string {
		return \OC::$server->getURLGenerator()->imagePath(Application::APP_NAME, 'app.svg');
	}

	public function isAvailableForScope(int $scope): bool {
		return $scope === IManager::SCOPE_ADMIN;
	}

	public function onEvent(string $eventName, Event $event, IRuleMatcher $ruleMatcher): void {
		if ($eventName !== '\OCP\Files::postCreate' && $eventName !== '\OCP\Files::postWrite' || 
			!$event instanceof GenericEvent){
				$this->logger->debug('Not processing event {eventname} with argument {event}.', 
					['eventname' => $eventName, 'event' => $event]);
				return;
			}

		$node = $event->getSubject();

		if (!$node instanceof \OC\Files\Node\File){
			$this->logger->debug('Not processing event {eventname} because node is not a file.', 
					['eventname' => $eventName]);
			return;
		}

		$args = ['filePath' => $node->getPath()];
		$this->jobList->add(ProcessFileJob::class, $args);
	}

	public function getEntityId(): string {
		return File::class;
	}
}
