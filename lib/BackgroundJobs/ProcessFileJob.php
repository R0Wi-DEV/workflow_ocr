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

use OC\User\NoUserException;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use \OCP\Files\File;
use OCA\WorkflowOcr\Exception\OcrNotPossibleException;
use OCA\WorkflowOcr\Exception\OcrProcessorNotFoundException;
use OCA\WorkflowOcr\Helper\IProcessingFileAccessor;
use OCA\WorkflowOcr\Service\IOcrService;
use OCA\WorkflowOcr\Wrapper\IFilesystem;
use OCA\WorkflowOcr\Wrapper\IViewFactory;
use OCP\Files\FileInfo;
use OCP\Files\Node;
use OCP\IUserManager;
use OCP\IUser;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * Represents a QuedJob which processes
 * a OCR on a single file.
 */
class ProcessFileJob extends \OC\BackgroundJob\QueuedJob {

	/** @var LoggerInterface */
	protected $logger;
	/** @var IRootFolder */
	private $rootFolder;
	/** @var IOcrService */
	private $ocrService;
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
	
	public function __construct(
		LoggerInterface $logger,
		IRootFolder $rootFolder,
		IOcrService $ocrService,
		IViewFactory $viewFactory,
		IFilesystem $filesystem,
		IUserManager $userManager,
		IUserSession $userSession,
		IProcessingFileAccessor $processingFileAccessor) {
		$this->logger = $logger;
		$this->rootFolder = $rootFolder;
		$this->ocrService = $ocrService;
		$this->viewFactory = $viewFactory;
		$this->filesystem = $filesystem;
		$this->userManager = $userManager;
		$this->userSession = $userSession;
		$this->processingFileAccessor = $processingFileAccessor;
	}
	
	/**
	 * @param mixed $argument
	 */
	protected function run($argument) : void {
		$this->logger->debug('STARTED -- Run ' . self::class . ' job. Argument: {argument}.', ['argument' => $argument]);
		
		[$success, $filePath, $uid] = $this->tryParseArguments($argument);
		if (!$success) {
			return;
		}

		try {
			$this->initUserEnvironment($uid);
			$this->processFile($filePath);
		} catch (\Throwable $ex) {
			$this->logger->error($ex->getMessage(), ['exception' => $ex]);
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
		$filePathKey = 'filePath';
		if (array_key_exists($filePathKey , $argument)) {
			$filePath = $argument[$filePathKey];
		} else {
			$this->logger->warning('Variable \''. $filePathKey .'\' not set in ' . self::class . ' method \'tryParseArguments\'.');
		}

		$uid = null;
		$uidKey = 'uid';
		if (array_key_exists($uidKey , $argument)) {
			$uid = $argument[$uidKey];
		} else {
			$this->logger->warning('Variable \''. $uidKey .'\' not set in ' . self::class . ' method \'tryParseArguments\'.');
		}

		return [
			$filePath !== null && $uid !== null,
			$filePath,
			$uid
		];
	}

	/**
	 * @param string $filePath  The file to be processed
	 */
	private function processFile(string $filePath) : void {
		try {
			/** @var File */
			$node = $this->rootFolder->get($filePath);
		} catch (NotFoundException $ex) {
			$this->logger->warning('Could not process file \'' . $filePath . '\'. File was not found');
			return;
		}

		if (!$node instanceof Node || $node->getType() !== FileInfo::TYPE_FILE) {
			$this->logger->warning('Skipping process for \'' . $filePath . '\'. It is not a file');
			return;
		}

		try {
			$ocrFile = $this->ocrFile($node);
		} catch (OcrNotPossibleException $ocrNpEx) {
			$this->logger->error('OCR for file ' . $node->getPath() . ' not possible. Message: ' . $ocrNpEx->getMessage());
			return;
		} catch (OcrProcessorNotFoundException $ocrProcNfEx) {
			$this->logger->error('OCR processor not found for mimetype ' . $node->getMimeType());
			return;
		}

		$this->createNewFileVersion($filePath, $ocrFile, $node->getId());
	}

	/**
	 * * @param string $uid 	The owners userId of the file to be processed
	 */
	private function initUserEnvironment(string $uid) : void {
		/** @var IUser */
		$user = $this->userManager->get($uid);
		if (!$user) {
			throw new NoUserException("User with uid '$uid' was not found");
		}

		$this->userSession->setUser($user);
		$this->filesystem->init($uid, '/' . $uid . '/files');
	}

	/**
	 * @param File $file
	 */
	private function ocrFile(File $file) : string {
		return $this->ocrService->ocrFile($file->getMimeType(), $file->getContent());
	}

	private function shutdownUserEnvironment() : void {
		$this->userSession->setUser(null);
	}

	/**
	 * @param string $filePath		The filepath of the file to write
	 * @param string $ocrContent	The new filecontent (which was OCR processed)
	 * @param string $fileId		The id of the file to write. Used for locking.
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
