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
use OCA\WorkflowOcr\Exception\OcrNotPossibleException;
use OCA\WorkflowOcr\Exception\OcrProcessorNotFoundException;
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
		
		[$success, $filePath, $uid, $settings] = $this->tryParseArguments($argument);
		if (!$success) {
			$this->notificationService->createErrorNotification($uid, 'Failed to parse arguments inside the OCR process. Please have a look at your servers logfile for more details.');
			return;
		}

		try {
			$this->initUserEnvironment($uid);
			$this->processFile($filePath, $settings, $uid);
		} catch (\Throwable $ex) {
			$this->logger->error($ex->getMessage(), ['exception' => $ex]);
			$this->notificationService->createErrorNotification($uid, 'An error occured while executing the OCR process. Please have a look at your servers logfile for more details.');
		} finally {
			$this->shutdownUserEnvironment();
		}

		$this->logger->debug('ENDED -- Run ' . self::class . ' job. Argument: {argument}.', ['argument' => $argument]);
	}

	/**
	 * @param mixed $argument
	 */
	private function tryParseArguments($argument) : array {
		if (!is_array($argument)) {
			$this->logger->warning('Argument is no array in ' . self::class . ' method \'tryParseArguments\'.');
			return [
				false
			];
		}

		$filePath = null;
		$uid = null;
		$filePathKey = 'filePath';
		if (array_key_exists($filePathKey, $argument)) {
			$filePath = $argument[$filePathKey];
			// '', admin, 'files', 'path/to/file.pdf'
			$splitted = explode('/', $filePath, 4);
			if (count($splitted) < 4) {
				$this->logger->warning('File path "' . $filePath . '" is not valid in ' . self::class . ' method \'tryParseArguments\'.');
				return [
					false
				];
			}
			$uid = $splitted[1];
		} else {
			$this->logVariableKeyNotSet($filePathKey, 'tryParseArguments');
		}

		$settings = null;
		$settingsKey = 'settings';
		if (array_key_exists($settingsKey, $argument)) {
			$jsonSettings = $argument[$settingsKey];
			$settings = new WorkflowSettings($jsonSettings);
		} else {
			$this->logVariableKeyNotSet($settingsKey, 'tryParseArguments');
		}

		return [
			$filePath !== null && $uid !== null && $settings !== null,
			$filePath,
			$uid,
			$settings
		];
	}

	/**
	 * @param string $filePath  The file to be processed
	 * @param WorkflowSettings $settings The settings to be used for processing
	 * @param string $userId The user who triggered the processing
	 */
	private function processFile(string $filePath, WorkflowSettings $settings, string $userId) : void {
		$node = $this->getNode($filePath, $userId);

		if ($node === null) {
			return;
		}

		$nodeId = $node->getId();

		try {
			$ocrFile = $this->ocrService->ocrFile($node, $settings);
		} catch(\Throwable $throwable) {
			if ($throwable instanceof(OcrNotPossibleException::class)) {
				$msg = 'OCR for file ' . $node->getPath() . ' not possible. Message: ' . $throwable->getMessage();
			} elseif ($throwable instanceof(OcrProcessorNotFoundException::class)) {
				$msg = 'OCR processor not found for mimetype ' . $node->getMimeType();
			} else {
				throw $throwable;
			}

			$this->logger->error($msg);
			$this->notificationService->createErrorNotification($userId, $msg, $nodeId);
			
			return;
		}

		$fileContent = $ocrFile->getFileContent();
		$originalFileExtension = $node->getExtension();
		$newFileExtension = $ocrFile->getFileExtension();

		// Only create a new file version if the file OCR result was not empty #130
		if ($ocrFile->getRecognizedText() !== '') {
			$newFilePath = $originalFileExtension === $newFileExtension ?
				$filePath :
				$filePath . ".pdf";

			$this->createNewFileVersion($newFilePath, $fileContent, $nodeId);
		}

		$this->eventService->textRecognized($ocrFile, $node);
	}

	private function getNode(string $filePath, string $userId) : ?Node {
		try {
			/** @var File */
			$node = $this->rootFolder->get($filePath);
		} catch (NotFoundException $nfEx) {
			$msg = 'Could not process file \'' . $filePath . '\'. File was not found';
			$this->logger->warning($msg);
			$this->notificationService->createErrorNotification($userId, $msg);
			return null;
		}

		if (!$node instanceof Node || $node->getType() !== FileInfo::TYPE_FILE) {
			$msg = 'Skipping process for \'' . $filePath . '\'. It is not a file';
			$this->logger->warning($msg);
			$this->notificationService->createErrorNotification($userId, $msg);
			return null;
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

	private function logVariableKeyNotSet(string $key, string $method) : void {
		$this->logger->warning("Variable '" . $key . "' not set in " . self::class . " method '" . $method . "'.");
	}
}
