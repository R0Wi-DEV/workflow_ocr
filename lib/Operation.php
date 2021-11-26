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

use OCA\WorkflowOcr\AppInfo\Application;
use OCA\WorkflowEngine\Entity\File;
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
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\IURLGenerator;
use OCP\SystemTag\MapperEvent;
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
	/** @var IRootFolder */
	private $rootFolder;

	public function __construct(
		IJobList $jobList,
		IL10N $l,
		LoggerInterface $logger,
		IURLGenerator $urlGenerator,
		IProcessingFileAccessor $processingFileAccessor,
		IRootFolder $rootFolder) {
		$this->jobList = $jobList;
		$this->l = $l;
		$this->logger = $logger;
		$this->urlGenerator = $urlGenerator;
		$this->processingFileAccessor = $processingFileAccessor;
		$this->rootFolder = $rootFolder;
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
		$this->logger->debug('onEvent: ' . $eventName);

		if (!$this->checkRuleMatcher($ruleMatcher)) {
			return;
		}

		// $node will be passed by reference
		if (!$this->tryGetFile($eventName, $event, $node)) {
			return;
		}

		if (!$this->pathIsValid($node) ||
			!$this->ownerExists($node) ||
			$this->eventTriggeredByOcrProcess($node)) {
			return;
		}

		$args = [
			'filePath' => $node->getPath(),
			'uid' => $node->getOwner()->getUID()
		];

		$this->logger->debug('Adding file to jobqueue: ' . json_encode($args));

		$this->jobList->add(ProcessFileJob::class, $args);
	}

	public function getEntityId(): string {
		return File::class;
	}

	private function tryGetFile(string $eventName, Event $event, ?Node & $node) : bool {
		// Handle file creation/ file change events
		if ($event instanceof GenericEvent) {
			return $this->tryGetFileFromGenericEvent($eventName, $event, $node);
		}

		// Handle file tag assigned events
		if ($event instanceof MapperEvent) {
			return $this->tryGetFileFromMapperEvent($eventName, $event, $node);
		}

		$this->logger->warning('Not processing event {eventname} because the event type {eventtype} is not supported.',
				['eventname' => $eventName],
				['eventtype' => get_class($event)]);

		return false;
	}

	private function tryGetFileFromGenericEvent(string $eventName, GenericEvent $event, ?Node & $node) : bool {
		$node = $event->getSubject();

		if (!$node instanceof Node || $node->getType() !== FileInfo::TYPE_FILE) {
			$this->logger->debug(
				'Not processing event {eventname} because node is not a file.',
				['eventname' => $eventName]
			);
			return false;
		}

		return true;
	}

	private function tryGetFileFromMapperEvent(string $eventName, MapperEvent $event, ?Node & $node) : bool {
		if ($event->getObjectType() !== 'files') {
			$this->logger->warning('Do not process MapperEvent of type {type}',
			['type' => $event->getObjectType()]);
			return false;
		}

		$fileId = intval($event->getObjectId());
		if ($fileId === 0) {
			$this->logger->warning(
				'Not processing event {eventname} because file id  \'{fileid}\' could not be casted to integer.',
				['eventname' => $eventName],
				['fileid' => $event->getObjectId()]
			);
			return false;
		}

		$files = $this->rootFolder->getById($fileId);
		if (count($files) <= 0 || !($files[0] instanceof \OCP\Files\File)) {
			$this->logger->warning(
				'Not processing event {eventname} because node with id  \'{fileid}\' could not be found or is not a file.',
				['eventname' => $eventName],
				['fileid' => $fileId]);
			return false;
		}

		$node = $files[0];
		return true;
	}

	private function checkRuleMatcher(IRuleMatcher $ruleMatcher) : bool {
		$match = $ruleMatcher->getFlows(true);
		if (!$match) {
			$this->logger->debug('Not processing event because IRuleMatcher->getFlows did not return anything');
			return false;
		}
		return true;
	}

	private function pathIsValid(Node $node) : bool {
		// Check path has valid structure
		$filePath = $node->getPath();
		// '', admin, 'files', 'path/to/file.pdf'
		[,, $folder,] = explode('/', $filePath, 4);
		if ($folder !== 'files') {
			$this->logger->debug('Not processing event because path \'{path}\' seems to be invalid.',
					['path' => $filePath]);
			return false;
		}

		return true;
	}

	private function ownerExists(Node $node) : bool {
		// Check owner of file exists
		$owner = $node->getOwner();
		if ($owner === null) {
			$this->logger->debug('Not processing event because file with path \'{path}\' has no owner.',
					['path' => $node->getPath()]);
			return false;
		}

		return true;
	}

	private function eventTriggeredByOcrProcess(Node $node) : bool {
		// Check if the event was triggered by OCR rewrite of the file
		if ($node->getId() === $this->processingFileAccessor->getCurrentlyProcessedFileId()) {
			$this->logger->debug('Not processing event because file with path \'{path}\' was written by OCR process.',
			['path' => $node->getPath()]);
			return true;
		}

		return false;
	}
}
