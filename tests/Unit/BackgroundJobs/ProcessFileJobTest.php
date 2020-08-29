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
use OCA\WorkflowOcr\BackgroundJobs\ProcessFileJob;
use OCA\WorkflowOcr\Exception\OcrNotPossibleException;
use OCA\WorkflowOcr\Exception\OcrProcessorNotFoundException;
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
use OCP\ILogger;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class ProcessFileJobTest extends TestCase {

	/** @var ILogger|MockObject */
	private $logger;
	/** @var IRootFolder|MockObject */
	private $rootFolder;
	/** @var IOcrService|MockObject */
	private $ocrService;
	/** @var IViewFactory|MockObject */
	private $viewFactory;
	/** @var IFilesystem|MockObject */
	private $filesystem;
	/** @var JobList */
	private $jobList;
	/** @var ProcessFileJob */
	private $processFileJob;

	public function setUp() : void {
		parent::setUp();

		/** @var ILogger */
		$this->logger = $this->createMock(ILogger::class);
		/** @var IRootFolder */
		$this->rootFolder = $this->createMock(IRootFolder::class);
		/** @var IOcrService */
		$this->ocrService = $this->createMock(IOcrService::class);
		/** @var IViewFactory */
		$this->viewFactory = $this->createMock(IViewFactory::class);
		/** @var IFilesystem */
		$this->filesystem = $this->createMock(IFilesystem::class);

		$this->processFileJob = new ProcessFileJob(
			$this->logger,
			$this->rootFolder,
			$this->ocrService,
			$this->viewFactory,
			$this->filesystem
		);

		/** @var IConfig */
		$configMock = $this->createMock(IConfig::class);
		/** @var ITimeFactory */
		$timeFactoryMock = $this->createMock(ITimeFactory::class);
		/** @var MockObject|IDbConnection */
		$connectionMock = $this->createMock(IDBConnection::class);
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
			$timeFactoryMock
		);
	}
    
    public function testCatchesException() {
        $this->processFileJob->setArgument(['filePath' => '/admin/files/somefile.pdf']);
        $exception = new Exception();
        $this->filesystem->method('init')
            ->willThrowException($exception);
        
        $this->logger->expects($this->once())
            ->method('logException')
            ->with($exception);

        $this->processFileJob->execute($this->jobList);
    }
    
	/**
	 * @dataProvider dataProvider_InvalidArguments
	 */
	public function testDoesNothingOnInvalidArguments($argument) {
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
		$this->logger->expects($this->once())
			->method('warning');

		$this->processFileJob->execute($this->jobList);
	}

	/**
	 * @dataProvider dataProvider_ValidArguments
	 */
	public function testCallsInitFilesystem(array $arguments, string $user, string $rootFolderPath) {
		$this->processFileJob->setArgument($arguments);
		$this->filesystem->expects($this->once())
			->method('init')
			->with($user, $rootFolderPath);
		
		$this->processFileJob->execute($this->jobList);
	}

	/**
	 * @dataProvider dataProvider_ValidArguments
	 */
	public function testCallsGetOnRootFolder(array $arguments, string $user, string $rootFolderPath) {
		$this->processFileJob->setArgument($arguments);
		$this->rootFolder->expects($this->once())
			->method('get')
			->with($arguments['filePath']);
		
		$this->processFileJob->execute($this->jobList);
	}

	/**
	 * @dataProvider dataProvider_ValidArguments
	 */
	public function testCallsOcr_IfIsFile(array $arguments, string $user, string $rootFolderPath) {
		$this->processFileJob->setArgument($arguments);
	   
		$mimeType = 'application/pdf';
		$content = 'someFileContent';
		$fileMock = $this->createValidFileMock($mimeType, $content);
		$this->rootFolder->method('get')
			->with($arguments['filePath'])
			->willReturn($fileMock);

		$this->ocrService->expects($this->once())
			->method('ocrFile')
			->with($mimeType, $content);

		$this->processFileJob->execute($this->jobList);
	}

	/**
	 * @dataProvider dataProvider_ValidArguments
	 */
	public function testCreatesNewFileVersion(array $arguments, string $user, string $rootFolderPath) {
		$this->processFileJob->setArgument($arguments);
		$mimeType = 'application/pdf';
		$content = 'someFileContent';
		$ocrContent = 'someOcrProcessedFile';
		$filePath = $arguments['filePath'];
		$dirPath = dirname($filePath);
		$filename = basename($filePath);

		$fileMock = $this->createValidFileMock($mimeType, $content);
		$this->rootFolder->method('get')
			->with($arguments['filePath'])
			->willReturn($fileMock);

		$this->ocrService->expects($this->once())
			->method('ocrFile')
			->willReturn($ocrContent);

		$viewMock = $this->createMock(IView::class);
		$viewMock->expects($this->once())
			->method('file_put_contents')
			->with($filename, $ocrContent);
		$this->viewFactory->expects($this->once())
			->method('create')
			->with($dirPath)
			->willReturn($viewMock);

		$this->processFileJob->execute($this->jobList);
	}

	public function testNotFoundLogsWarning_AndDoesNothingAfterwards() {
		$this->processFileJob->setArgument(['filePath' => '/admin/files/somefile.pdf']);

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
		$arguments = ['filePath' => '/admin/files/someInvalidStuff'];
		$this->processFileJob->setArgument($arguments);

		$this->rootFolder->method('get')
			->with($arguments['filePath'])
			->willReturn($invalidNode);

		$this->ocrService->expects($this->never())
			->method('ocrFile');

		$this->processFileJob->execute($this->jobList);
	}

	/**
	 * @dataProvider dataProvider_OcrExceptions
	 */
	public function testLogsInfo_OnOcrException(Exception $exception) {
		$arguments = ['filePath' => '/admin/files/someInvalidStuff'];
		$this->processFileJob->setArgument($arguments);

		$fileMock = $this->createValidFileMock();
		$this->rootFolder->method('get')
			->with($arguments['filePath'])
			->willReturn($fileMock);

		$this->ocrService->method('ocrFile')
			->willThrowException($exception);
		
		$this->logger->expects($this->once())
			->method('info');

		$this->viewFactory->expects($this->never())
			->method('create');

		$this->processFileJob->execute($this->jobList);
	}

	public function dataProvider_InvalidArguments() {
		$arr = [
			[['mykey' => 'myvalue']],
			[['someotherkey' => 'someothervalue', 'k2' => 'v2']]
		];
		return $arr;
	}

	public function dataProvider_ValidArguments() {
		$arr = [
			[['filePath' => '/admin/files/somefile.pdf'], 'admin', '/admin/files'],
			[['filePath' => '/myuser/files/subfolder/someotherfile.docx'], 'myuser', '/myuser/files']
		];
		return $arr;
	}

	public function dataProvider_InvalidNodes() {
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
	private function createValidFileMock(string $mimeType = 'application/pdf', string $content = 'someFileContent') {
		$fileMock = $this->createMock(File::class);
		$fileMock->method('getType')
			->willReturn(FileInfo::TYPE_FILE);
		$fileMock->method('getMimeType')
			->willReturn($mimeType);
		$fileMock->method('getContent')
			->willReturn($content);
		return $fileMock;
	}
}
