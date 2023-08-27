<?php
/**
 * @copyright Copyright (c) 2023 Robin Windey <ro.windey@gmail.com>
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

namespace OCA\WorkflowOcr\Tests\Integration\Notification;

use OC\BackgroundJob\JobList;
use OCA\WorkflowOcr\BackgroundJobs\ProcessFileJob;
use OCA\WorkflowOcr\Exception\OcrNotPossibleException;
use OCA\WorkflowOcr\Helper\IProcessingFileAccessor;
use OCA\WorkflowOcr\Service\IEventService;
use OCA\WorkflowOcr\Service\INotificationService;
use OCA\WorkflowOcr\Service\IOcrService;
use OCA\WorkflowOcr\Service\NotificationService;
use OCA\WorkflowOcr\Wrapper\IFilesystem;
use OCA\WorkflowOcr\Wrapper\IViewFactory;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\QueryBuilder\IExpressionBuilder;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\File;
use OCP\Files\FileInfo;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class NotificationTest extends TestCase {
	/** @var AppFake */
	private $appFake;
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
	/** @var INotificationService|MockObject */
	private $notificationService;
	/** @var JobList */
	private $jobList;
	/** @var ProcessFileJob */
	private $processFileJob;

	protected function setUp() : void {
		parent::setUp();
		// We use a faked notification receiver app to keep track of any notifications created
		\OC::$server->get(\OCP\Notification\IManager::class)->registerApp(AppFake::class);
		
		// Use real Notification service to be able to check if notifications get created
		$this->notificationService = new NotificationService(\OC::$server->get(\OCP\Notification\IManager::class));

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
			$this->notificationService,
			$this->createMock(ITimeFactory::class)
		);
		
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

		$this->processFileJob->setId(111);
		$this->processFileJob->setArgument([
			'fileId' => 42,
			'uid' => 'someuser',
			'settings' => '{}'
		]);
	}

	public function testBackgroundJobCreatesErrorNotificationIfOcrFailed() {
		$fileMock = $this->createValidFileMock();
		$this->rootFolder->method('getById')
			->with(42)
			->willReturn([$fileMock]);

		$this->ocrService->expects($this->once())
			->method('ocrFile')
			->withAnyParameters()
			->willThrowException(new OcrNotPossibleException('Some error'));
		$appFake = \OC::$server->get(AppFake::class);

		$this->processFileJob->start($this->jobList);

		$notifications = $appFake->getNotifications();
		$this->assertCount(1, $notifications);

		$notification = $notifications[0];
		$this->assertEquals('workflow_ocr', $notification->getApp());
		$this->assertEquals('ocr_error', $notification->getSubject());
		$this->assertEquals('An error occured while executing the OCR process (Some error). Please have a look at your servers logfile for more details.', $notification->getSubjectParameters()['message']);
	}

	/**
	 * @return File|MockObject
	 */
	private function createValidFileMock(string $mimeType = 'application/pdf', string $content = 'someFileContent', string $fileExtension = "pdf", string $path = "/admin/files/somefile.pdf") {
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
		$fileMock->method('getPath')
			->willReturn($path);
		return $fileMock;
	}
}
