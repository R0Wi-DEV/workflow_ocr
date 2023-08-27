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

namespace OCA\WorkflowOcr\BackgroundJobs;

use \OCP\Files\File;
use OC\User\NoUserException;
use OCA\WorkflowOcr\Helper\IProcessingFileAccessor;
use OCA\WorkflowOcr\Model\WorkflowSettings;
use OCA\WorkflowOcr\Service\IEventService;
use OCA\WorkflowOcr\Service\INotificationService;
use OCA\WorkflowOcr\Service\IOcrService;
use OCA\WorkflowOcr\Wrapper\IFilesystem;
use OCA\WorkflowOcr\Wrapper\IViewFactory;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\FileInfo;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * Represents a QuedJob which processes
 * a OCR on a single file.
 */
class ProcessFileJob extends \OCP\BackgroundJob\QueuedJob {
	/** @var LoggerInterface */
	protected $logger;
	/** @var IRootFolder */
	private $rootFolder;
	/** @var IOcrService */
	private $ocrService;
	/** @var IEventService */
	private $eventService;
	/** @var IViewFactory */
	private $viewFactory;
	/** @var IFilesystem */
	private $filesystem;
	/** @var IUserManager */
	private $userManager;
	/** @var IUserSession */
	private $userSession;
	/** @var IProcessingFileAccessor */
	private $processingFileAccessor;
	/** @var INotificationService */
	private $notificationService;
	
	public function __construct(
		LoggerInterface $logger,
		IRootFolder $rootFolder,
		IOcrService $ocrService,
		IEventService $eventService,
		IViewFactory $viewFactory,
		IFilesystem $filesystem,
		IUserManager $userManager,
		IUserSession $userSession,
		IProcessingFileAccessor $processingFileAccessor,
		INotificationService $notificationService,
		ITimeFactory $timeFactory) {
		parent::__construct($timeFactory);
		$this->logger = $logger;
		$this->rootFolder = $rootFolder;
		$this->ocrService = $ocrService;
		$this->eventService = $eventService;
		$this->viewFactory = $viewFactory;
		$this->filesystem = $filesystem;
		$this->userManager = $userManager;
		$this->userSession = $userSession;
		$this->processingFileAccessor = $processingFileAccessor;
		$this->notificationService = $notificationService;
	}
	
	/**
	 * @param mixed $argument
	 */
	protected function run($argument) : void {
		$this->logger->debug('STARTED -- Run ' . self::class . ' job. Argument: {argument}.', ['argument' => $argument]);

		try {
			[$fileId, $uid, $settings] = $this->parseArguments($argument);
			$this->initUserEnvironment($uid);
			$this->processFile($fileId, $settings);
		} catch (\Throwable $ex) {
			$this->logger->error($ex->getMessage(), ['exception' => $ex]);
			$this->notificationService->createErrorNotification($uid, 'An error occured while executing the OCR process ('.$ex->getMessage().'). Please have a look at your servers logfile for more details.');
		} finally {
			$this->shutdownUserEnvironment();
		}

		$this->logger->debug('ENDED -- Run ' . self::class . ' job. Argument: {argument}.', ['argument' => $argument]);
	}

	/**
	 * @param mixed $argument
	 */
	private function parseArguments($argument) : array {
		if (!is_array($argument)) {
			throw new \InvalidArgumentException('Argument is no array in ' . self::class . ' method \'tryParseArguments\'.');
		}

		$jsonSettings = $argument['settings'];
		$settings = new WorkflowSettings($jsonSettings);
		$uid = $argument['uid'];
		$fileId = intval($argument['fileId']);

		return [
			$fileId,
			$uid,
			$settings
		];
	}

	/**
	 * @param int $fileId  The id of the file to be processed
	 * @param WorkflowSettings $settings The settings to be used for processing
	 */
	private function processFile(int $fileId, WorkflowSettings $settings) : void {
		$node = $this->getNode($fileId);

		$ocrFile = $this->ocrService->ocrFile($node, $settings);

		$filePath = $node->getPath();
		$fileContent = $ocrFile->getFileContent();
		$originalFileExtension = $node->getExtension();
		$newFileExtension = $ocrFile->getFileExtension();

		// Only create a new file version if the file OCR result was not empty #130
		if ($ocrFile->getRecognizedText() !== '') {
			$newFilePath = $originalFileExtension === $newFileExtension ?
				$filePath :
				$filePath . ".pdf";

			$this->createNewFileVersion($newFilePath, $fileContent, $fileId);
		}

		$this->eventService->textRecognized($ocrFile, $node);
	}

	private function getNode(int $fileId) : ?Node {
		/** @var File[] */
		$nodeArr = $this->rootFolder->getById($fileId);
		if (count($nodeArr) === 0) {
			throw new NotFoundException('Could not process file with id \'' . $fileId . '\'. File was not found');
		}
		
		$node = array_shift($nodeArr);

		if (!$node instanceof Node || $node->getType() !== FileInfo::TYPE_FILE) {
			throw new \InvalidArgumentException('Skipping process for file with id \'' . $fileId . '\'. It is not a file');
		}

		return $node;
	}

	/**
	 * * @param string $userId 	The owners userId of the file to be processed
	 */
	private function initUserEnvironment(string $userId) : void {
		/** @var IUser */
		$user = $this->userManager->get($userId);
		if (!$user) {
			throw new NoUserException("User with uid '$userId' was not found");
		}

		$this->userSession->setUser($user);
		$this->filesystem->init($userId, '/' . $userId . '/files');
	}

	private function shutdownUserEnvironment() : void {
		$this->userSession->setUser(null);
	}

	/**
	 * @param string $filePath		The filepath of the file to write
	 * @param string $ocrContent	The new filecontent (which was OCR processed)
	 * @param int $fileId		The id of the file to write. Used for locking.
	 */
	private function createNewFileVersion(string $filePath, string $ocrContent, int $fileId) : void {
		$dirPath = dirname($filePath);
		$filename = basename($filePath);
		
		$this->processingFileAccessor->setCurrentlyProcessedFileId($fileId);

		try {
			$view = $this->viewFactory->create($dirPath);
			// Create new file or file-version with OCR-file
			// This will trigger 'postWrite' event which would normally
			// add the file to the queue again but this is tackled
			// by the processingFileAccessor.
			$view->file_put_contents($filename, $ocrContent);
		} finally {
			$this->processingFileAccessor->setCurrentlyProcessedFileId(null);
		}
	}
}
