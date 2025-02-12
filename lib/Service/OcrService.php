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

namespace OCA\WorkflowOcr\Service;

use OC\User\NoUserException;
use OCA\Files_Versions\Versions\IMetadataVersion;
use OCA\Files_Versions\Versions\IMetadataVersionBackend;
use OCA\Files_Versions\Versions\IVersionManager;
use OCA\WorkflowOcr\Exception\OcrResultEmptyException;
use OCA\WorkflowOcr\Helper\IProcessingFileAccessor;
use OCA\WorkflowOcr\Model\WorkflowSettings;
use OCA\WorkflowOcr\OcrProcessors\IOcrProcessorFactory;
use OCA\WorkflowOcr\OcrProcessors\OcrProcessorResult;
use OCA\WorkflowOcr\Wrapper\IFilesystem;
use OCA\WorkflowOcr\Wrapper\IViewFactory;
use OCP\Files\File;
use OCP\Files\FileInfo;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\SystemTag\ISystemTagObjectMapper;
use OCP\SystemTag\TagNotFoundException;
use Psr\Log\LoggerInterface;

class OcrService implements IOcrService {
	private const FILE_VERSION_LABEL_KEY = 'label';
	private const FILE_VERSION_LABEL_VALUE = 'Before OCR';

	/** @var IOcrProcessorFactory */
	private $ocrProcessorFactory;

	/** @var IGlobalSettingsService */
	private $globalSettingsService;

	/** @var IVersionManager */
	private $versionManager;

	/** @var ISystemTagObjectMapper */
	private $systemTagObjectMapper;

	/** @var IUserManager */
	private $userManager;

	/** @var IFilesystem */
	private $filesystem;

	/** @var IUserSession */
	private $userSession;

	/** @var IRootFolder */
	private $rootFolder;

	/** @var IEventService */
	private $eventService;

	/** @var IViewFactory */
	private $viewFactory;

	/** @var IProcessingFileAccessor */
	private $processingFileAccessor;

	/** @var INotificationService */
	private $notificationService;

	/** @var LoggerInterface */
	private $logger;

	public function __construct(
		IOcrProcessorFactory $ocrProcessorFactory,
		IGlobalSettingsService $globalSettingsService,
		IVersionManager $versionManager,
		ISystemTagObjectMapper $systemTagObjectMapper,
		IUserManager $userManager,
		IFilesystem $filesystem,
		IUserSession $userSession,
		IRootFolder $rootFolder,
		IEventService $eventService,
		IViewFactory $viewFactory,
		IProcessingFileAccessor $processingFileAccessor,
		INotificationService $notificationService,
		LoggerInterface $logger) {
		$this->ocrProcessorFactory = $ocrProcessorFactory;
		$this->globalSettingsService = $globalSettingsService;
		$this->versionManager = $versionManager;
		$this->systemTagObjectMapper = $systemTagObjectMapper;
		$this->userManager = $userManager;
		$this->filesystem = $filesystem;
		$this->userSession = $userSession;
		$this->rootFolder = $rootFolder;
		$this->eventService = $eventService;
		$this->viewFactory = $viewFactory;
		$this->processingFileAccessor = $processingFileAccessor;
		$this->notificationService = $notificationService;
		$this->logger = $logger;
	}

	/** @inheritdoc */
	public function runOcrProcessWithJobArgument($argument): void {
		try {
			[$fileId, $uid, $settings] = $this->parseArguments($argument);
			$this->runOcrProcess($fileId, $uid, $settings);
		} catch (\Throwable $ex) {
			$this->logger->error($ex->getMessage(), ['exception' => $ex]);
			$this->notificationService->createErrorNotification($uid ?? null, 'An error occured while executing the OCR process (' . $ex->getMessage() . '). Please have a look at your servers logfile for more details.');
		}
	}

	/** @inheritdoc */
	public function runOcrProcess(int $fileId, string $uid, WorkflowSettings $settings) : void { // TODO :: make private
		try {
			$this->initUserEnvironment($uid);

			$file = $this->getNode($fileId);

			$this->logger->debug('Begin processing file', [
				'fileId' => $fileId,
				'file' => $file,
				'path' => $file->getPath(),
				'permissions' => $file->getPermissions(),
				'mimeType' => $file->getMimeType()
			]);

			$fileMtime = null;
			if ($settings->getKeepOriginalFileDate()) {
				// Add one ms to the original file modification time to prevent the new original version from being overwritten
				$fileMtime = $file->getMTime() + 1;
			}

			$ocrProcessor = $this->ocrProcessorFactory->create($file->getMimeType());
			$globalSettings = $this->globalSettingsService->getGlobalSettings();
			
			try {
				$result = $ocrProcessor->ocrFile($file, $settings, $globalSettings);
			} catch (OcrResultEmptyException $ex) {
				// #232: it's okay to have an empty result if the file was skipped due to OCR mode
				if ($settings->getOcrMode() === WorkflowSettings::OCR_MODE_SKIP_FILE) {
					$this->logger->debug('Skipping empty OCR result for file with id {fileId} because OCR mode is set to \'skip file\'', ['fileId' => $fileId]);
					return;
				}
				throw $ex;
			}

			$this->doPostProcessing($file, $uid, $settings, $result, $fileMtime);
		} finally {
			$this->shutdownUserEnvironment();
		}
	}

	/**
	 * @param mixed $argument
	 */
	private function parseArguments($argument) : array {
		if (!is_array($argument)) {
			throw new \InvalidArgumentException('Argument is not an array in ' . self::class . ' method \'tryParseArguments\'.');
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
	 * * @param string $uid 	The owners userId of the file to be processed
	 */
	private function initUserEnvironment(string $uid) : void {
		/** @var IUser */
		$user = $this->userManager->get($uid);
		if (!$user) {
			throw new NoUserException("User with uid '$uid' was not found");
		}

		$this->logger->debug('Initializing user environment for user {uid}', ['uid' => $uid]);
		$this->userSession->setUser($user);
		$this->filesystem->init($uid, '/' . $uid . '/files');
	}

	private function shutdownUserEnvironment() : void {
		$this->userSession->setUser(null);
	}

	private function getNode(int $fileId) : Node {
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

	private function processTagsAfterSuccessfulOcr(File $file, WorkflowSettings $settings) : void {
		$objectType = 'files';
		$fileId = strval($file->getId());
		$tagsToRemove = $settings->getTagsToRemoveAfterOcr();
		$tagsToAdd = $settings->getTagsToAddAfterOcr();

		foreach ($tagsToRemove as $tagToRemove) {
			try {
				$this->systemTagObjectMapper->unassignTags($fileId, $objectType, $tagToRemove);
			} catch (TagNotFoundException $ex) {
				$this->logger->warning("Cannot remove tag with id '$tagToRemove' because it was not found. Skipping.");
			}
		}

		foreach ($tagsToAdd as $tagToAdd) {
			try {
				$this->systemTagObjectMapper->assignTags($fileId, $objectType, $tagToAdd);
			} catch (TagNotFoundException $ex) {
				$this->logger->warning("Cannot add tag with id '$tagToAdd' because it was not found. Skipping.");
			}
		}
	}

	/**
	 * @param string $filePath The filepath of the file to write
	 * @param string $ocrContent The new filecontent (which was OCR processed)
	 * @param int $fileMtime The mtime of the new file. Can be used to restore the original modification time of the non-OCR file.
	 */
	private function createNewFileVersion(string $filePath, string $ocrContent, ?int $fileMtime = null) : void {
		$dirPath = dirname($filePath);
		$filename = basename($filePath);
		
		$this->processingFileAccessor->setCurrentlyProcessedFilePath($filePath);

		try {
			$view = $this->viewFactory->create($dirPath);
			// Create new file or file-version with OCR-file
			// This will trigger 'postWrite' event which would normally
			// add the file to the queue again but this is tackled
			// by the processingFileAccessor.
			$view->file_put_contents($filename, $ocrContent);

			// Restore the original modification time of the non-OCR file
			if ($fileMtime !== null) {
				$view->touch($filename, $fileMtime);
			}
		} finally {
			$this->processingFileAccessor->setCurrentlyProcessedFilePath(null);
		}
	}

	/**
	 * @param File $file The file to set the label for
	 * @param string $uid The userId of the file owner
	 * @param string $label The label to set
	 */
	private function setFileVersionsLabel(File $file, string $uid, string $label): void {
		$fileMTime = $file->getMTime();
		$user = $this->userManager->get($uid);
		$versions = $this->versionManager->getVersionsForFile($user, $file);

		foreach ($versions as $version) {
			if (!$version instanceof IMetadataVersion) {
				$this->logger->debug('Skipping version with revision id {versionId} because "{versionClass}" is not an IMetadataVersion', ['versionClass' => get_class($version)]);
				continue;
			}

			$versionBackend = $version->getBackend();

			if (!$versionBackend instanceof IMetadataVersionBackend) {
				$this->logger->debug('Skipping version with revision id {versionId} because its backend "{versionBackendClass}" does not implement IMetadataVersionBackend', ['versionBackendClass' => get_class($versionBackend)]);
				continue;
			}

			$revisionId = $version->getRevisionId();
			$versionTimestamp = $version->getTimestamp();
			$versionLabel = $version->getMetadataValue(self::FILE_VERSION_LABEL_KEY);

			if ($fileMTime === $versionTimestamp && empty($versionLabel)) {
				$this->logger->debug('Setting pre OCR label for version with revision id {versionId} on file {fileId}', ['versionId' => $revisionId, 'fileId' => $file->getId()]);
				$versionBackend->setMetadataValue($file, $revisionId, self::FILE_VERSION_LABEL_KEY, $label);
			}
		}
	}

	private function doPostProcessing(Node $file, string $uid, WorkflowSettings $settings, OcrProcessorResult $result, ?int $fileMtime = null): void {
		$this->processTagsAfterSuccessfulOcr($file, $settings);

		$fileId = $file->getId();
		$fileContent = $result->getFileContent();
		$originalFileExtension = $file->getExtension();
		$newFileExtension = $result->getFileExtension();

		// Only create a new file version if the file OCR result was not empty #130
		if ($result->getRecognizedText() !== '') {
			if ($settings->getKeepOriginalFileVersion() && $file->isUpdateable()) {
				// Add label to original file to prevent its expiry
				$this->setFileVersionsLabel($file, $uid, self::FILE_VERSION_LABEL_VALUE);
			}

			$newFilePath = $this->determineNewFilePath($file, $originalFileExtension, $newFileExtension);
			$this->createNewFileVersion($newFilePath, $fileContent, $fileMtime);
		}

		$this->eventService->textRecognized($result, $file);

		if ($settings->getSendSuccessNotification()) {
			$this->notificationService->createSuccessNotification($uid, $fileId);
		}
	}

	/**
	 * Determines the new file path for a given file by analyzing the original- and new file extension.
	 * Also takes into consideration, if the file can be updated by the current user.
	 *
	 * @param Node $file The original file node for which the OCR processing has been succeeded.
	 * @param string $originalFileExtension The original file extension.
	 * @param string $newFileExtension The new file extension to be applied.
	 * @return string The new file path with the updated extension.
	 */
	private function determineNewFilePath(Node $file, string $originalFileExtension, string $newFileExtension): string {
		$filePath = $file->getPath();
		if ($originalFileExtension !== $newFileExtension) {
			// If the extension changed, will create a new file with the new extension
			return $filePath . '.' . $newFileExtension;
		}
		if (!$file->isUpdateable()) {
			// Add suffix '_OCR' if original file cannot be updated
			$fileInfo = pathinfo($filePath);
			return $fileInfo['dirname'] . '/' . $fileInfo['filename'] . '_OCR.' . $newFileExtension;
		}
		// By returning the original file path, we will create a new file version of the original file
		return $filePath;
	}
}
