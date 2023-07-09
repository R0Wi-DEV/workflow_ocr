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

namespace OCA\WorkflowOcr\Tests\Unit\BackgroundJobs;

use Exception;
use OC\BackgroundJob\JobList;
use OC\User\NoUserException;
use OCA\WorkflowOcr\BackgroundJobs\ProcessFileJob;
use OCA\WorkflowOcr\Exception\OcrNotPossibleException;
use OCA\WorkflowOcr\Exception\OcrProcessorNotFoundException;
use OCA\WorkflowOcr\Helper\IProcessingFileAccessor;
use OCA\WorkflowOcr\OcrProcessors\OcrProcessorResult;
use OCA\WorkflowOcr\Service\IEventService;
use OCA\WorkflowOcr\Service\IOcrService;
use OCA\WorkflowOcr\Wrapper\IFilesystem;
use OCA\WorkflowOcr\Wrapper\IView;
use OCA\WorkflowOcr\Wrapper\IViewFactory;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\QueryBuilder\IExpressionBuilder;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\File;
use OCP\Files\FileInfo;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ProcessFileJobTest extends TestCase {
	/** @var LoggerInterface|MockObject */
	private $logger;
	/** @var IRootFolder|MockObject */
	private $rootFolder;
	/** @var IOcrService|MockObject */
	private $ocrService;
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
	/** @var JobList */
	private $jobList;
	/** @var ProcessFileJob */
	private $processFileJob;

	public function setUp() : void {
		parent::setUp();

		$this->logger = $this->createMock(LoggerInterface::class);
		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->ocrService = $this->createMock(IOcrService::class);
		$this->eventService = $this->createMock(IEventService::class);
		$this->viewFactory = $this->createMock(IViewFactory::class);
		$this->filesystem = $this->createMock(IFilesystem::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->processingFileAccessor = $this->createMock(IProcessingFileAccessor::class);
		
		/** @var MockObject|IUserManager */
		$userManager = $this->createMock(IUserManager::class);
		$user = $this->createMock(IUser::class);
		$userManager->method('get')
			->withAnyParameters()
			->willReturn($user);

		$this->userManager = $userManager;
		$this->user = $user;

		$this->processFileJob = new ProcessFileJob(
			$this->logger,
			$this->rootFolder,
			$this->ocrService,
			$this->eventService,
			$this->viewFactory,
			$this->filesystem,
			$this->userManager,
			$this->userSession,
			$this->processingFileAccessor,
			$this->createMock(ITimeFactory::class)
		);
		$this->processFileJob->setId(111);

		/** @var IConfig */
		$configMock = $this->createMock(IConfig::class);
		/** @var ITimeFactory */
		$timeFactoryMock = $this->createMock(ITimeFactory::class);
		/** @var MockObject|IDbConnection */
		$connectionMock = $this->createMock(IDBConnection::class);
		/** @var MockObject|IQueryBuilder */
		$queryBuilderMock = $this->createMock(IQueryBuilder::class);
		$expressionBuilderMock = $this->createMock(IExpressionBuilder::class);

		$queryBuilderMock->method('delete')
			->withAnyParameters()
			->willReturn($queryBuilderMock);
		$queryBuilderMock->method('set')
			->withAnyParameters()
			->willReturn($queryBuilderMock);
		$queryBuilderMock->method('update')
			->withAnyParameters()
			->willReturn($queryBuilderMock);
		$queryBuilderMock->method('expr')
			->withAnyParameters()
			->willReturn($expressionBuilderMock);
		$connectionMock->method('getQueryBuilder')
			->withAnyParameters()
			->willReturn($queryBuilderMock);

		$this->jobList = new JobList(
			$connectionMock,
			$configMock,
			$timeFactoryMock,
			$this->logger
		);

		$this->processFileJob->setArgument([
			'filePath' => '/admin/files/somefile.pdf',
			'uid' => 'someuser',
			'settings' => '{}'
		]);
	}
	
	public function testCatchesExceptionAndResetsUserEnvironment() {
		$exception = new Exception('someEx');
		$this->filesystem->method('init')
			->willThrowException($exception);
		
		$this->logger->expects($this->once())
			->method('error')
			->with($exception->getMessage(), ['exception' => $exception]);

		// Make sure user-environment is reset after any exception
		// so the user should be set on beginning but should also
		// be reset to null after any run.
		$this->userSession->expects($this->exactly(2))
			->method('setUser')
			->withConsecutive([$this->user], [null]);

		$this->processFileJob->execute($this->jobList);
	}
	
	/**
	 * @dataProvider dataProvider_InvalidArguments
	 */
	public function testDoesNothingOnInvalidArguments($argument, $invalidCount) {
		$this->processFileJob->setArgument($argument);
		$this->filesystem->expects($this->never())
			->method('init')
			->withAnyParameters();
		$this->ocrService->expects($this->never())
			->method('ocrFile')
			->withAnyParameters();
		$this->viewFactory->expects($this->never())
			->method('create')
			->withAnyParameters();
		$this->logger->expects($this->exactly($invalidCount))
			->method('warning');

		$this->processFileJob->execute($this->jobList);
	}

	/**
	 * @dataProvider dataProvider_ValidArguments
	 */
	public function testCallsInitFilesystem(array $arguments, string $user, string $rootFolderPath, string $originalFileExtension, string $expectedOcrFilename) {
		$this->processFileJob->setArgument($arguments);
		$this->filesystem->expects($this->once())
			->method('init')
			->with($user, $rootFolderPath);
		
		$this->processFileJob->execute($this->jobList);
	}

	/**
	 * @dataProvider dataProvider_ValidArguments
	 */
	public function testCallsGetOnRootFolder(array $arguments, string $user, string $rootFolderPath, string $originalFileExtension, string $expectedOcrFilename) {
		$this->processFileJob->setArgument($arguments);
		$this->rootFolder->expects($this->once())
			->method('get')
			->with($arguments['filePath']);
		
		$this->processFileJob->execute($this->jobList);
	}

	/**
	 * @dataProvider dataProvider_ValidArguments
	 */
	public function testCallsOcr_IfIsFile(array $arguments, string $user, string $rootFolderPath, string $originalFileExtension, string $expectedOcrFilename) {
		$this->processFileJob->setArgument($arguments);
	   
		$mimeType = 'application/pdf';
		$content = 'someFileContent';
		$fileMock = $this->createValidFileMock($mimeType, $content);
		$this->rootFolder->method('get')
			->with($arguments['filePath'])
			->willReturn($fileMock);

		$this->ocrService->expects($this->once())
			->method('ocrFile')
			->with($fileMock);

		$this->processFileJob->execute($this->jobList);
	}

	/**
	 * @dataProvider dataProvider_ValidArguments
	 */
	public function testCreatesNewFileVersionAndEmitsTextRecognizedEvent(array $arguments, string $user, string $rootFolderPath, string $originalFileExtension, string $expectedOcrFilename) {
		$this->processFileJob->setArgument($arguments);
		$mimeType = 'application/pdf';
		$content = 'someFileContent';
		$ocrContent = 'someOcrProcessedFile';
		$ocrResult = new OcrProcessorResult($ocrContent, "pdf", $ocrContent); // Extend this cases if we add new OCR processors
		$filePath = $arguments['filePath'];
		$dirPath = dirname($filePath);

		$fileMock = $this->createValidFileMock($mimeType, $content, $originalFileExtension);
		$this->rootFolder->method('get')
			->with($arguments['filePath'])
			->willReturn($fileMock);

		$this->ocrService->expects($this->once())
			->method('ocrFile')
			->willReturn($ocrResult);

		/** @var MockObject|IView */
		$viewMock = $this->createMock(IView::class);
		$viewMock->expects($this->once())
			->method('file_put_contents')
			->with($expectedOcrFilename, $ocrContent);
		$this->viewFactory->expects($this->once())
			->method('create')
			->with($dirPath)
			->willReturn($viewMock);

		$this->eventService->expects($this->once())
			->method('textRecognized')
			->with($ocrResult, $fileMock);

		$this->processFileJob->execute($this->jobList);
	}

	public function testNotFoundLogsWarning_AndDoesNothingAfterwards() {
		$this->rootFolder->expects($this->once())
			->method('get')
			->willThrowException(new NotFoundException());
		$this->logger->expects($this->once())
			->method('warning')
			->with($this->stringContains('not found'));
		$this->ocrService->expects($this->never())
			->method('ocrFile');

		$this->processFileJob->execute($this->jobList);
	}

	/**
	 * @dataProvider dataProvider_InvalidNodes
	 */
	public function testDoesNotCallOcr_OnNonFile($invalidNode) {
		$this->rootFolder->method('get')
			->with('/admin/files/somefile.pdf')
			->willReturn($invalidNode);

		$this->ocrService->expects($this->never())
			->method('ocrFile');

		$this->processFileJob->execute($this->jobList);
	}

	/**
	 * @dataProvider dataProvider_OcrExceptions
	 */
	public function testLogsError_OnOcrException(Exception $exception) {
		$fileMock = $this->createValidFileMock();
		$this->rootFolder->method('get')
			->with('/admin/files/somefile.pdf')
			->willReturn($fileMock);

		$this->ocrService->method('ocrFile')
			->willThrowException($exception);
		
		$this->logger->expects($this->once())
			->method('error');

		$this->viewFactory->expects($this->never())
			->method('create');

		$this->processFileJob->execute($this->jobList);
	}

	public function testThrowsNoUserException_OnNonExistingUser() {
		// Unfortunately method definitions can't yet be overwritten in
		// PHPUnit, see https://github.com/sebastianbergmann/phpunit-documentation-english/issues/169
		/** @var IUserManager|MockObject */
		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('get')
			->with('nonexistinguser')
			->willReturn(null);

		$this->logger->expects($this->once())
			->method('error')
			->with($this->stringContains('nonexistinguser'), $this->callback(function ($subject) {
				return is_array($subject) && ($subject['exception'] instanceof NoUserException);
			}));
		
		$processFileJob = new ProcessFileJob(
			$this->logger,
			$this->rootFolder,
			$this->ocrService,
			$this->eventService,
			$this->viewFactory,
			$this->filesystem,
			$userManager,
			$this->userSession,
			$this->processingFileAccessor,
			$this->createMock(ITimeFactory::class)
		);
		$processFileJob->setId(111);
		$arguments = ['filePath' => '/nonexistinguser/files/someInvalidStuff', 'settings' => '{}'];
		$processFileJob->setArgument($arguments);

		$processFileJob->execute($this->jobList);
	}

	/**
	 * @dataProvider dataProvider_ValidArguments
	 */
	public function testCallsProcessingFileAccessor(array $arguments, string $user, string $rootFolderPath, string $originalFileExtension, string $expectedOcrFilename) {
		$this->processFileJob->setArgument($arguments);
		$mimeType = 'application/pdf';
		$content = 'someFileContent';
		$ocrContent = 'someOcrProcessedFile';
		$ocrResult = new OcrProcessorResult($ocrContent, "pdf", $ocrContent); // Extend this cases if we add new OCR processors

		$fileMock = $this->createValidFileMock($mimeType, $content);
		$this->rootFolder->method('get')
			->with($arguments['filePath'])
			->willReturn($fileMock);

		$this->ocrService->expects($this->once())
			->method('ocrFile')
			->willReturn($ocrResult);

		$viewMock = $this->createMock(IView::class);
		$this->viewFactory->expects($this->once())
			->method('create')
			->willReturn($viewMock);

		$calledWith42 = 0;
		$calledWithNull = 0;

		$this->processingFileAccessor->expects($this->exactly(2))
			->method('setCurrentlyProcessedFileId')
			->with($this->callback(function ($id) use (&$calledWith42, &$calledWithNull) {
				if ($id === 42) {
					$calledWith42++;
				} elseif ($id === null) {
					$calledWithNull++;
				}

				return true;
			}));

		$this->processFileJob->execute($this->jobList);

		$this->assertEquals(1, $calledWith42);
		$this->assertEquals(1, $calledWithNull);
	}

	/**
	 * @dataProvider dataProvider_ValidArguments
	 */
	public function testDoesNotCreateNewFileVersionIfOcrContentWasEmpty(array $arguments, string $user, string $rootFolderPath, string $originalFileExtension, string $expectedOcrFilename) {
		$this->processFileJob->setArgument($arguments);
		$mimeType = 'application/pdf';
		$content = 'someFileContent';
		$ocrContent = '';
		$ocrResult = new OcrProcessorResult($ocrContent, "pdf", $ocrContent);

		$fileMock = $this->createValidFileMock($mimeType, $content);
		$this->rootFolder->method('get')
			->willReturn($fileMock);

		$this->ocrService->expects($this->once())
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

		$this->processFileJob->execute($this->jobList);
	}

	public function dataProvider_InvalidArguments() {
		$arr = [
			[null, 1],
			[['mykey' => 'myvalue'], 2],
			[['someotherkey' => 'someothervalue', 'k2' => 'v2'], 2],
			[['filePath' => 'someInvalidPath'], 1]
		];
		return $arr;
	}

	public function dataProvider_ValidArguments() {
		$arr = [
			[['filePath' => '/admin/files/somefile.pdf', 'uid' => 'admin', 'settings' => '{}'], 'admin', '/admin/files', 'pdf', 'somefile.pdf'],
			[['filePath' => '/myuser/files/subfolder/someotherfile.jpg', 'uid' => 'myuser', 'settings' => '{}'], 'myuser', '/myuser/files', 'jpg', 'someotherfile.jpg.pdf']
		];
		return $arr;
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

	public function dataProvider_OcrExceptions() {
		return [
			[new OcrNotPossibleException('Ocr not possible')],
			[new OcrProcessorNotFoundException()]
		];
	}

	/**
	 * @return File|MockObject
	 */
	private function createValidFileMock(string $mimeType = 'application/pdf', string $content = 'someFileContent', string $fileExtension = "pdf") {
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
		$fileMock->method('getExtension')
			->willReturn($fileExtension);
		return $fileMock;
	}
}
