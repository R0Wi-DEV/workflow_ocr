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
use OCP\IL10N;
use OCP\WorkflowEngine\IManager;
use OCP\WorkflowEngine\IRuleMatcher;
use OCP\WorkflowEngine\ISpecificOperation;
use OCA\WorkflowOcr\BackgroundJobs\ProcessFileJob;
use OCA\WorkflowOcr\Helper\IProcessingFileAccessor;
use OCA\WorkflowOcr\Helper\SynchronizationHelper;
use OCP\Files\FileInfo;
use OCP\Files\Node;
use OCP\IURLGenerator;
use Psr\Log\LoggerInterface;

class Operation implements ISpecificOperation {

	/** @var IJobList */
	private $jobList;
	/** @var IL10N */
	private $l;
	/** @var LoggerInterface */
	private $logger;
	/** @var IURLGenerator */
	private $urlGenerator;
	/** @var SynchronizationHelper */
	private $processingFileAccessor;

	public function __construct(IJobList $jobList, IL10N $l, LoggerInterface $logger, IURLGenerator $urlGenerator, IProcessingFileAccessor $processingFileAccessor) {
		$this->jobList = $jobList;
		$this->l = $l;
		$this->logger = $logger;
		$this->urlGenerator = $urlGenerator;
		$this->processingFileAccessor = $processingFileAccessor;
	}

	/**
	 * @throws \UnexpectedValueException
	 * @since 9.1
	 */
	public function validateOperation(string $name, array $checks, string $operation): void {
		// nothing to do
	}

	public function getDisplayName(): string {
		return $this->l->t('OCR file');
	}

	public function getDescription(): string {
		return $this->l->t('OCR processing via workflow');
	}

	public function getIcon(): string {
		return $this->urlGenerator->imagePath(Application::APP_NAME, 'app.svg');
	}

	public function isAvailableForScope(int $scope): bool {
		return $scope === IManager::SCOPE_ADMIN || $scope === IManager::SCOPE_USER;
	}

	public function onEvent(string $eventName, Event $event, IRuleMatcher $ruleMatcher): void {
		if ($eventName !== '\OCP\Files::postCreate' && $eventName !== '\OCP\Files::postWrite' ||
			!$event instanceof GenericEvent) {
			$this->logger->debug('Not processing event {eventname} with argument {event}.',
					['eventname' => $eventName, 'event' => $event]);
			return;
		}

		/** @var Node*/
		$node = $event->getSubject();

		if (!$node instanceof Node || $node->getType() !== FileInfo::TYPE_FILE) {
			$this->logger->debug('Not processing event {eventname} because node is not a file.',
					['eventname' => $eventName]);
			return;
		}

		if (!$this->checkNode($node)) {
			return;
		}

		$args = [
			'filePath' => $node->getPath(),
			'uid' => $node->getOwner()->getUID()
		];
		$this->jobList->add(ProcessFileJob::class, $args);
	}

	public function getEntityId(): string {
		return File::class;
	}

	private function checkNode(Node $node) : bool {
		// Check path has valid structure
		$filePath = $node->getPath();
		// '', admin, 'files', 'path/to/file.pdf'
		list(,, $folder,) = explode('/', $filePath, 4);
		if ($folder !== 'files') {
			$this->logger->debug('Not processing event because path \'{path}\' seems to be invalid.',
					['path' => $filePath]);
			return false;
		}

		// Check owner exists
		$owner = $node->getOwner();
		if ($owner === null) {
			$this->logger->debug('Not processing event because file with path \'{path}\' has no owner.',
					['path' => $filePath]);
			return false;
		}

		// Check if the event was triggered by OCR rewrite of the file
		if ($node->getId() === $this->processingFileAccessor->getCurrentlyProcessedFileId()) {
			$this->logger->debug('Not processing event because file with path \'{path}\' was written by OCR process.',
			['path' => $filePath]);
			return false;
		}

		return true;
	}
}
