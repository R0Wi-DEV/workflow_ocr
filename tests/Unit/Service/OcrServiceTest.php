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

namespace OCA\WorkflowOcr\Tests\Unit\Service;

use Exception;
use InvalidArgumentException;
use OC\User\NoUserException;
use OCA\Files_Versions\Versions\IVersionManager;
use OCA\WorkflowOcr\Exception\OcrNotPossibleException;
use OCA\WorkflowOcr\Exception\OcrProcessorNotFoundException;
use OCA\WorkflowOcr\Exception\OcrResultEmptyException;
use OCA\WorkflowOcr\Helper\IProcessingFileAccessor;
use OCA\WorkflowOcr\Model\GlobalSettings;
use OCA\WorkflowOcr\Model\WorkflowSettings;
use OCA\WorkflowOcr\OcrProcessors\IOcrProcessor;
use OCA\WorkflowOcr\OcrProcessors\IOcrProcessorFactory;
use OCA\WorkflowOcr\OcrProcessors\OcrProcessorResult;
use OCA\WorkflowOcr\Service\IEventService;
use OCA\WorkflowOcr\Service\IGlobalSettingsService;
use OCA\WorkflowOcr\Service\INotificationService;
use OCA\WorkflowOcr\Service\OcrService;
use OCA\WorkflowOcr\Wrapper\IFilesystem;
use OCA\WorkflowOcr\Wrapper\IView;
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
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class OcrServiceTest extends TestCase {
	/** @var IOcrProcessorFactory|MockObject */
	private $ocrProcessorFactory;
	/** @var IOcrProcessor|MockObject */
	private $ocrProcessor;
	/** @var IGlobalSettingsService|MockObject */
	private $globalSettingsService;
	/** @var IVersionManager|MockObject */
	private $versionManager;
	/** @var ISystemTagObjectMapper|MockObject */
	private $systemTagObjectMapper;
	/** @var LoggerInterface|MockObject */
	private $logger;
	/** @var IRootFolder|MockObject */
	private $rootFolder;
	/** @var IEventService|MockObject */
	private $eventService;
	/** @var IViewFactory|MockObject */
	private $viewFactory;
	/** @var IFilesystem|MockObject */
	private $filesystem;
	/** @var IUserSession|MockObject */
	private $userSession;
	/** @var IUserManager|MockObject */
	private $userManager;
	/** @var IUser|MockObject */
	private $user;
	/** @var IProcessingFileAccessor|MockObject */
	private $processingFileAccessor;
	/** @var INotificationService|MockObject */
	private $notificationService;
	/** @var File[] */
	private $rootFolderGetById42ReturnValue;
	/** @var OcrService */
	private $ocrService;
	/** @var File|MockObject */
	private $fileInput;

	private $defaultArgument = [
		'fileId' => 42,
		'uid' => 'admin',
		'settings' => '{}'
	];

	public function setUp() : void {
		parent::setUp();

		$this->globalSettingsService = $this->createMock(IGlobalSettingsService::class);
		$this->versionManager = $this->createMock(IVersionManager::class);
		$this->ocrProcessor = $this->createMock(IOcrProcessor::class);
		$this->systemTagObjectMapper = $this->createMock(ISystemTagObjectMapper::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->fileInput = $this->createMock(File::class);
		$this->eventService = $this->createMock(IEventService::class);
		$this->viewFactory = $this->createMock(IViewFactory::class);
		$this->filesystem = $this->createMock(IFilesystem::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->processingFileAccessor = $this->createMock(IProcessingFileAccessor::class);
		$this->notificationService = $this->createMock(INotificationService::class);

		/** @var MockObject|IRootFolder */
		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->rootFolderGetById42ReturnValue = [$this->createValidFileMock()];
		$this->rootFolder->expects($this->any())
			->method('getById')
			->with(42)
			->willReturnCallback(function () {
				return $this->rootFolderGetById42ReturnValue;
			});

		/** @var MockObject|IUserManager */
		$userManager = $this->createMock(IUserManager::class);
		$user = $this->createMock(IUser::class);
		$userManager->method('get')
			->withAnyParameters()
			->willReturn($user);

		$this->userManager = $userManager;
		$this->user = $user;

		/** @var MockObject|IOcrProcessorFactory */
		$this->ocrProcessorFactory = $this->createMock(IOcrProcessorFactory::class);
		$this->ocrProcessorFactory->method('create')
			->withAnyParameters()
			->willReturn($this->ocrProcessor);

		$this->ocrService = new OcrService(
			$this->ocrProcessorFactory,
			$this->globalSettingsService,
			$this->versionManager,
			$this->systemTagObjectMapper,
			$this->userManager,
			$this->filesystem,
			$this->userSession,
			$this->rootFolder,
			$this->eventService,
			$this->viewFactory,
			$this->processingFileAccessor,
			$this->notificationService,
			$this->logger);
	}

	public function testCallsOcrProcessor_WithCorrectArguments() {
		$mime = 'application/pdf';
		$content = 'someFileContent';
		$settings = new WorkflowSettings();
		$globalSettings = new GlobalSettings();

		$this->fileInput->method('getMimeType')
			->willReturn($mime);
		$this->fileInput->method('getContent')
			->willReturn($content);

		$this->globalSettingsService->expects($this->once())
			->method('getGlobalSettings')
			->willReturn($globalSettings);

		$this->ocrProcessorFactory->expects($this->once())
			->method('create')
			->with($mime)
			->willReturn($this->ocrProcessor);

		$this->ocrProcessor->expects($this->once())
			->method('ocrFile')
			->with($this->fileInput, $settings, $globalSettings);

		$this->ocrService->runOcrProcess(42, 'usr', $settings);
	}

	public function testCallsSystemTagObjectManager_WithCorrectArguments() {
		$mime = 'application/pdf';
		$content = 'someFileContent';
		$settings = new WorkflowSettings('{"tagsToRemoveAfterOcr": [1,2], "tagsToAddAfterOcr": [3,4]}');
		$globalSettings = new GlobalSettings();

		$this->fileInput->method('getMimeType')
			->willReturn($mime);
		$this->fileInput->method('getContent')
			->willReturn($content);
		$this->fileInput->method('getId')
			->willReturn(42);

		$this->globalSettingsService->expects($this->once())
			->method('getGlobalSettings')
			->willReturn($globalSettings);

		$this->ocrProcessorFactory->expects($this->once())
			->method('create')
			->with($mime)
			->willReturn($this->ocrProcessor);

		// Check call for function:
		// unassignTags(string $objId, string $objectType, $tagIds);
		$this->systemTagObjectMapper->expects($this->exactly(2))
			->method('unassignTags')
			->withConsecutive(['42', 'files', 1], ['42', 'files', 2]);

		// Check call for function:
		// assignTags(string $objId, string $objectType, $tagIds);
		$this->systemTagObjectMapper->expects($this->exactly(2))
			->method('assignTags')
			->withConsecutive(['42', 'files', 3], ['42', 'files', 4]);

		$this->ocrService->runOcrProcess(42, 'usr', $settings);
	}

	public function testSendsSuccessNotificationIfConfigured() {
		$mime = 'application/pdf';
		$content = 'someFileContent';
		$settings = new WorkflowSettings('{"sendSuccessNotification": true}');
		$globalSettings = new GlobalSettings();

		$this->fileInput->method('getMimeType')
			->willReturn($mime);
		$this->fileInput->method('getContent')
			->willReturn($content);

		$this->globalSettingsService->expects($this->once())
			->method('getGlobalSettings')
			->willReturn($globalSettings);

		$this->ocrProcessorFactory->expects($this->once())
			->method('create')
			->with($mime)
			->willReturn($this->ocrProcessor);

		$this->ocrProcessor->expects($this->once())
			->method('ocrFile')
			->with($this->fileInput, $settings, $globalSettings);

		$this->notificationService->expects($this->once())
			->method('createSuccessNotification')
			->with('usr', 42);

		$this->ocrService->runOcrProcess(42, 'usr', $settings);
	}

	public function testCatchesTagNotFoundException() {
		$mime = 'application/pdf';
		$content = 'someFileContent';
		$settings = new WorkflowSettings('{"tagsToRemoveAfterOcr": [1], "tagsToAddAfterOcr": [2]}');
		$globalSettings = new GlobalSettings();

		$this->fileInput->method('getMimeType')
			->willReturn($mime);
		$this->fileInput->method('getContent')
			->willReturn($content);
		$this->fileInput->method('getId')
			->willReturn(42);

		$this->globalSettingsService->expects($this->once())
			->method('getGlobalSettings')
			->willReturn($globalSettings);

		$this->ocrProcessorFactory->expects($this->once())
			->method('create')
			->with($mime)
			->willReturn($this->ocrProcessor);

		$this->systemTagObjectMapper->expects($this->once())
			->method('unassignTags')
			->willThrowException(new \OCP\SystemTag\TagNotFoundException());

		$this->systemTagObjectMapper->expects($this->once())
			->method('assignTags')
			->willThrowException(new \OCP\SystemTag\TagNotFoundException());

		$this->logger->expects($this->exactly(2))
			->method('warning');
		
		$this->ocrService->runOcrProcess(42, 'usr', $settings);
	}

	public function testResetsUserEnvironmentOnException() {
		$settings = new WorkflowSettings();
		$exception = new Exception('someEx');
		$this->filesystem->method('init')
			->willThrowException($exception);
		
		// Make sure user-environment is reset after any exception
		// so the user should be set on beginning but should also
		// be reset to null after any run.
		$this->userSession->expects($this->exactly(2))
			->method('setUser')
			->withConsecutive([$this->user], [null]);

		$thrown = false;
		try {
			$this->ocrService->runOcrProcess(42, 'usr', $settings);
		} catch (Exception $e) {
			$this->assertEquals($exception, $e);
			$thrown = true;
		}
		$this->assertTrue($thrown);
	}

	public function testCallsInitFilesystem() {
		$settings = new WorkflowSettings();
		$this->filesystem->expects($this->once())
			->method('init')
			->with('usr', '/usr/files');
		
		$this->ocrService->runOcrProcess(42, 'usr', $settings);
	}

	public function testCallsGetOnRootFolder() {
		$settings = new WorkflowSettings();
		$this->rootFolder->expects($this->once())
			->method('getById')
			->with(42);
		
		$this->ocrService->runOcrProcess(42, 'usr', $settings);
	}

	public function testCallsOcrProcessorWithFile() {
		$globalSettings = new GlobalSettings();
		$settings = new WorkflowSettings();
		
		$mimeType = 'application/pdf';
		$content = 'someFileContent';
		$fileMock = $this->createValidFileMock($mimeType, $content);
		$this->rootFolderGetById42ReturnValue = [$fileMock];

		$this->globalSettingsService->expects($this->once())
			->method('getGlobalSettings')
			->willReturn($globalSettings);

		$this->ocrProcessor->expects($this->once())
			->method('ocrFile')
			->with($fileMock, $settings, $globalSettings);

		$this->ocrService->runOcrProcess(42, 'usr', $settings);
	}

	/**
	 * @dataProvider dataProvider_OriginalAndNewFilesnames
	 */
	public function testCreatesNewFileVersionAndEmitsTextRecognizedEvent(string $originalFilename, string $expectedOcrFilename) {
		$settings = new WorkflowSettings();

		$rootFolderPath = '/usr/files';
		$mimeType = 'application/pdf';
		$content = 'someFileContent';
		$ocrContent = 'someOcrProcessedFile';
		$ocrResult = new OcrProcessorResult($ocrContent, 'pdf', $ocrContent); // Extend this cases if we add new OCR processors
		$originalFileMock = $this->createValidFileMock($mimeType, $content, $rootFolderPath, $originalFilename);
		
		$this->rootFolderGetById42ReturnValue = [$originalFileMock];

		$this->ocrProcessor->expects($this->once())
			->method('ocrFile')
			->willReturn($ocrResult);

		/** @var MockObject|IView */
		$viewMock = $this->createMock(IView::class);
		$viewMock->expects($this->once())
			->method('file_put_contents')
			->with($expectedOcrFilename, $ocrContent);
		$this->viewFactory->expects($this->once())
			->method('create')
			->with($rootFolderPath)
			->willReturn($viewMock);

		$this->eventService->expects($this->once())
			->method('textRecognized')
			->with($ocrResult, $originalFileMock);

		$this->ocrService->runOcrProcess(42, 'usr', $settings);
	}

	public function testThrowsNotFoundExceptionWhenFileNotFound() {
		$settings = new WorkflowSettings();
		$this->rootFolderGetById42ReturnValue = [];

		$this->expectException(NotFoundException::class);
		$this->ocrService->runOcrProcess(42, 'usr', $settings);
	}

	/**
	 * @dataProvider dataProvider_InvalidNodes
	 */
	public function testDoesNotCallOcr_OnNonFile($invalidNode) {
		$settings = new WorkflowSettings();
		$this->rootFolderGetById42ReturnValue = [$invalidNode];

		$this->ocrProcessor->expects($this->never())
			->method('ocrFile');

		$this->expectException(InvalidArgumentException::class);
		$this->ocrService->runOcrProcess(42, 'usr', $settings);
	}

	public function testThrowsNoUserException_OnNonExistingUser() {
		$settings = new WorkflowSettings();

		// Unfortunately method definitions can't yet be overwritten in
		// PHPUnit, see https://github.com/sebastianbergmann/phpunit-documentation-english/issues/169
		/** @var IUserManager|MockObject */
		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('get')
			->with('nonexistinguser')
			->willReturn(null);

		$this->ocrService = new OcrService(
			$this->ocrProcessorFactory,
			$this->globalSettingsService,
			$this->versionManager,
			$this->systemTagObjectMapper,
			$userManager,
			$this->filesystem,
			$this->userSession,
			$this->rootFolder,
			$this->eventService,
			$this->viewFactory,
			$this->processingFileAccessor,
			$this->notificationService,
			$this->logger);

		$thrown = false;
		try {
			$this->ocrService->runOcrProcess(42, 'nonexistinguser', $settings);
		} catch (NoUserException $e) {
			$this->assertTrue(str_contains($e->getMessage(), 'nonexistinguser'));
			$thrown = true;
		}
		$this->assertTrue($thrown);
	}

	public function testCallsProcessingFileAccessor() {
		$settings = new WorkflowSettings();
		$mimeType = 'application/pdf';
		$content = 'someFileContent';
		$ocrContent = 'someOcrProcessedFile';
		$ocrResult = new OcrProcessorResult($ocrContent, 'pdf', $ocrContent); // Extend this cases if we add new OCR processors

		$this->rootFolderGetById42ReturnValue = [$this->createValidFileMock($mimeType, $content)];

		$this->ocrProcessor->expects($this->once())
			->method('ocrFile')
			->willReturn($ocrResult);

		$viewMock = $this->createMock(IView::class);
		$this->viewFactory->expects($this->once())
			->method('create')
			->willReturn($viewMock);

		$calledWithFileId42 = 0;
		$calledWithNull = 0;
		$withIdCalledFirst = false;

		$this->processingFileAccessor->expects($this->exactly(2))
			->method('setCurrentlyProcessedFileId')
			->with($this->callback(function ($id) use (&$calledWithFileId42, &$calledWithNull, &$withIdCalledFirst) {
				if ($id === 42) {
					$calledWithFileId42++;
					$withIdCalledFirst = $calledWithNull === 0;
				} elseif ($id === null) {
					$calledWithNull++;
				}

				return true;
			}));

		$this->ocrService->runOcrProcess(42, 'usr', $settings);

		$this->assertEquals(1, $calledWithFileId42);
		$this->assertEquals(1, $calledWithNull);
		$this->assertTrue($withIdCalledFirst);
	}

	public function testDoesNotCreateNewFileVersionIfOcrContentWasEmpty() {
		$settings = new WorkflowSettings();
		$user = 'usr';
		$mimeType = 'application/pdf';
		$content = 'someFileContent';
		$ocrContent = '';
		$ocrResult = new OcrProcessorResult($ocrContent, 'pdf', $ocrContent);
		$fileId = 42;

		$this->rootFolder->expects($this->once())
			->method('getById')
			->with($fileId)
			->willReturn([$this->createValidFileMock($mimeType, $content)]);

		$this->ocrProcessor->expects($this->once())
			->method('ocrFile')
			->willReturn($ocrResult);

		$viewMock = $this->createMock(IView::class);
		$this->viewFactory->expects($this->never())
			->method('create')
			->willReturn($viewMock);

		$this->processingFileAccessor->expects($this->never())
			->method('setCurrentlyProcessedFileId');

		$this->eventService->expects($this->once())
			->method('textRecognized');

		$this->ocrService->runOcrProcess($fileId, $user, $settings);
	}

	public function testOcrSkippedIfOcrModeIsSkipFileAndResultIsEmpty() {
		$fileId = 42;
		$settings = new WorkflowSettings('{"ocrMode": ' . WorkflowSettings::OCR_MODE_SKIP_FILE . '}');

		$this->ocrProcessor->expects($this->once())
			->method('ocrFile')
			->willThrowException(new OcrResultEmptyException('oops'));
		$this->logger->expects($this->once())
			->method('debug')
			->with($this->stringStartsWith('Skipping empty OCR result for file with id'), ['fileId' => $fileId]);
		$this->viewFactory->expects($this->never())
			->method('create');
		$this->eventService->expects($this->never())
			->method('textRecognized');
			
		$this->ocrService->runOcrProcess($fileId, 'usr', $settings);
	}

	/**
	 * @dataProvider dataProvider_OcrModesThrowOnEmptyResult
	 */
	public function testOcrEmptyExceptionIsThrown(int $ocrMode) {
		$fileId = 42;
		$settings = new WorkflowSettings('{"ocrMode": ' . $ocrMode . '}');
		$ex = new OcrResultEmptyException('oops');

		$this->ocrProcessor->expects($this->once())
			->method('ocrFile')
			->willThrowException($ex);
		$this->logger->expects($this->never())
			->method('debug');
		$this->viewFactory->expects($this->never())
			->method('create');
		$this->eventService->expects($this->never())
			->method('textRecognized');
		
		$thrown = false;
		try {
			$this->ocrService->runOcrProcess($fileId, 'usr', $settings);
		} catch (OcrResultEmptyException $e) {
			$this->assertEquals($ex, $e);
			$thrown = true;
		}
		$this->assertTrue($thrown);
	}

	public function testRestoreOriginalFileModificationDate() {
		$settings = new WorkflowSettings('{"keepOriginalFileDate": true}');
		$mimeType = 'application/pdf';
		$content = 'someFileContent';
		$ocrContent = 'someOcrProcessedFile';
		$ocrResult = new OcrProcessorResult($ocrContent, 'pdf', $ocrContent); // Extend this cases if we add new OCR processors

		$fileMock = $this->createValidFileMock($mimeType, $content);
		$this->rootFolderGetById42ReturnValue = [$fileMock];

		$this->ocrProcessor->expects($this->once())
			->method('ocrFile')
			->willReturn($ocrResult);

		$viewMock = $this->createMock(IView::class);
		$this->viewFactory->expects($this->once())
			->method('create')
			->willReturn($viewMock);

		$fileMock->expects($this->once())
			->method('getMTime')
			->willReturn(1234);
		$viewMock->expects($this->once())
			->method('touch')
			->with('somefile.pdf', 1235);

		$this->ocrService->runOcrProcess(42, 'usr', $settings);
	}
	
	public function testRunOcrProcessWithJobArgumentCatchedException() {
		$exception = new Exception('someEx');
		$this->userManager->method('get')
			->willThrowException($exception);
		
		$this->logger->expects($this->once())
			->method('error')
			->with($exception->getMessage(), ['exception' => $exception]);

		$this->ocrService->runOcrProcessWithJobArgument($this->defaultArgument);
	}

	/**
	 * @dataProvider dataProvider_InvalidArguments
	 */
	public function testRunOcrProcessWithJobArgumentLogsErrorAndDoesNothingOnInvalidArguments($argument, $errorMessagePart) {
		$this->userManager->expects($this->never())
			->method('get')
			->withAnyParameters();
		$this->logger->expects($this->once())
			->method('error')
			->with($this->stringContains($errorMessagePart), $this->callback(function ($loggerArgs) {
				return is_array($loggerArgs) && ($loggerArgs['exception'] instanceof \Exception);
			}));

		$this->ocrService->runOcrProcessWithJobArgument($argument);
	}

	public function testRunOcrProcessWithJobArgumentLogsErrorAndSendsNotificationOnNotFound() {
		$this->rootFolder->method('getById')
			->willThrowException(new NotFoundException('File was not found'));
		$this->logger->expects($this->once())
			->method('error')
			->with($this->stringContains('File was not found'), $this->callback(function ($subject) {
				return is_array($subject) && ($subject['exception'] instanceof NotFoundException);
			}));
		$this->notificationService->expects($this->once())
			->method('createErrorNotification')
			->with($this->stringContains('An error occured while executing the OCR process (') && $this->stringContains('File was not found'));

		$this->ocrService->runOcrProcessWithJobArgument($this->defaultArgument);
	}

	/**
	 * @dataProvider dataProvider_ExceptionsToBeCaught
	 */
	public function testRunOcrProcessWithJobArgumentLogsErrorOnException(Exception $exception) {
		$this->ocrProcessor->method('ocrFile')
			->willThrowException($exception);
		
		$this->logger->expects($this->once())
			->method('error');

		$this->ocrService->runOcrProcessWithJobArgument($this->defaultArgument);
	}

	public function dataProvider_InvalidNodes() {
		/** @var MockObject|Node */
		$folderMock = $this->createMock(Node::class);
		$folderMock->method('getType')
			->willReturn(FileInfo::TYPE_FOLDER);
		$fileInfoMock = $this->createMock(FileInfo::class);
		$arr = [
			[$folderMock],
			[$fileInfoMock],
			[null],
			[(object)['someField' => 'someValue']]
		];
		return $arr;
	}

	public function dataProvider_OcrModesThrowOnEmptyResult() {
		return [
			[WorkflowSettings::OCR_MODE_FORCE_OCR],
			[WorkflowSettings::OCR_MODE_SKIP_TEXT],
			[WorkflowSettings::OCR_MODE_REDO_OCR]
		];
	}

	public function dataProvider_OriginalAndNewFilesnames() {
		return [
			['somefile.pdf', 'somefile.pdf'],
			['somefile.jpg', 'somefile.jpg.pdf']
		];
	}

	public function dataProvider_InvalidArguments() {
		$arr = [
			[null, 'Argument is not an array'],
			[['mykey' => 'myvalue'], 'Undefined array key']
		];
		return $arr;
	}

	public function dataProvider_ExceptionsToBeCaught() {
		return [
			[new OcrNotPossibleException('Ocr not possible')],
			[new OcrProcessorNotFoundException('audio/mpeg')],
			[new OcrResultEmptyException('Ocr result was empty')],
			[new Exception('Some exception')]
		];
	}

	/**
	 * @return File|MockObject
	 */
	private function createValidFileMock(string $mimeType = 'application/pdf', string $content = 'someFileContent', string $rootFolderPath = '/admin/files', string $fileName = 'somefile.pdf'): File {
		/** @var MockObject|File */
		$fileMock = $this->createMock(File::class);
		$fileMock->method('getType')
			->willReturn(FileInfo::TYPE_FILE);
		$fileMock->method('getMimeType')
			->willReturn($mimeType);
		$fileMock->method('getContent')
			->willReturn($content);
		$fileMock->method('getId')
			->willReturn(42);
		$fileMock->method('getPath')
			->willReturn("$rootFolderPath/$fileName");
		#get extension from filename
		$fileMock->method('getExtension')
			->willReturn(pathinfo($fileName, PATHINFO_EXTENSION));
		return $fileMock;
	}
}
