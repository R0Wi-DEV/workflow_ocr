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
use OCA\WorkflowOcr\Service\INotificationService;
use OCA\WorkflowOcr\Service\IOcrService;
use OCA\WorkflowOcr\Service\NotificationService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\QueryBuilder\IExpressionBuilder;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IConfig;
use OCP\IDBConnection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class NotificationTest extends TestCase {
	/** @var LoggerInterface|MockObject */
	private $logger;
	/** @var IOcrService|MockObject */
	private $ocrService;
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
		$this->ocrService = $this->createMock(IOcrService::class);
		
		$this->processFileJob = new ProcessFileJob(
			$this->logger,
			$this->ocrService,
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
		$this->ocrService->expects($this->once())
			->method('runOcrProcess')
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
}
