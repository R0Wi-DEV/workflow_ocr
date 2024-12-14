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

use OCA\Files_Versions\Versions\IVersionManager;
use OCA\WorkflowOcr\Exception\OcrNotPossibleException;
use OCA\WorkflowOcr\Helper\IProcessingFileAccessor;
use OCA\WorkflowOcr\OcrProcessors\IOcrProcessorFactory;
use OCA\WorkflowOcr\Service\IEventService;
use OCA\WorkflowOcr\Service\IGlobalSettingsService;
use OCA\WorkflowOcr\Service\INotificationService;
use OCA\WorkflowOcr\Service\IOcrService;
use OCA\WorkflowOcr\Service\NotificationService;
use OCA\WorkflowOcr\Service\OcrService;
use OCA\WorkflowOcr\Wrapper\IFilesystem;
use OCA\WorkflowOcr\Wrapper\IViewFactory;
use OCP\Files\IRootFolder;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Notification\INotification;
use OCP\SystemTag\ISystemTagObjectMapper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class NotificationTest extends TestCase {
	/** @var LoggerInterface|MockObject */
	private $logger;
	/** @var IOcrService|MockObject */
	//private $ocrService;
	/** @var INotificationService */
	private $notificationService;

	protected function setUp() : void {
		parent::setUp();
		// We use a faked notification receiver app to keep track of any notifications created
		\OC::$server->get(\OCP\Notification\IManager::class)->registerApp(AppFake::class);
		
		// Use real Notification service to be able to check if notifications get created
		$this->notificationService = new NotificationService(\OC::$server->get(\OCP\Notification\IManager::class));

		$this->logger = $this->createMock(LoggerInterface::class);
	}

	public function testOcrServiceCreatesErrorNotificationIfOcrFailed() {
		// Simulate an early exception
		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('get')
			->with('someuser')
			->willThrowException(new OcrNotPossibleException('Some error'));
		$ocrService = new OcrService(
			$this->createMock(IOcrProcessorFactory::class),
			$this->createMock(IGlobalSettingsService::class),
			$this->createMock(IVersionManager::class),
			$this->createMock(ISystemTagObjectMapper::class),
			$userManager,
			$this->createMock(IFilesystem::class),
			$this->createMock(IUserSession::class),
			$this->createMock(IRootFolder::class),
			$this->createMock(IEventService::class),
			$this->createMock(IViewFactory::class),
			$this->createMock(IProcessingFileAccessor::class),
			$this->notificationService,
			$this->logger
		);
		// Notification recorder ...
		$appFake = \OC::$server->get(AppFake::class);

		$ocrService->runOcrProcessWithJobArgument([
			'fileId' => 42,
			'uid' => 'someuser',
			'settings' => '{}'
		]);

		$notifications = $appFake->getNotifications();
		$this->assertCount(1, $notifications);

		$notification = $notifications[0];
		$this->assertEquals('workflow_ocr', $notification->getApp());
		$this->assertEquals('ocr_error', $notification->getSubject());
		$this->assertEquals('An error occured while executing the OCR process (Some error). Please have a look at your servers logfile for more details.', $notification->getSubjectParameters()['message']);
	}

	public function testCreateSuccessNotification() {
		// Notification recorder ...
		$appFake = \OC::$server->get(AppFake::class);

		$this->notificationService->createSuccessNotification('someuser', 42);

		$notifications = $appFake->getNotifications();
		$this->assertCount(1, $notifications);

		/** @var INotification */
		$notification = $notifications[0];
		$this->assertEquals('workflow_ocr', $notification->getApp());
		$this->assertEquals('ocr_success', $notification->getSubject());
		$this->assertEquals('42', $notification->getObjectId());
		$this->assertEquals('someuser', $notification->getUser());
		$this->assertEquals('file', $notification->getObjectType());
		$this->assertEquals([], $notification->getSubjectParameters());
	}
}
