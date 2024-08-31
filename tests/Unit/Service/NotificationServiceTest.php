<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2023 Robin Windey <ro.windey@gmail.com>
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

namespace OCA\WorkflowOcr\Tests\Unit\Service;

use OC\Notification\Notification;
use OCA\WorkflowOcr\Service\NotificationService;
use OCP\Notification\IManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class NotificationServiceTest extends TestCase {
	/** @var IManager|MockObject */
	private $notificationManager;

	/** @var Notification|MockObject */
	private $notification;

	/** @var NotificationService */
	private $service;

	public function setUp() : void {
		$this->notificationManager = $this->createMock(IManager::class);
		$this->notification = $this->createMock(Notification::class);
		$this->service = new NotificationService($this->notificationManager);
		parent::setUp();
	}

	public function testCreateErrorNotificationWithFileId() {
		$this->notificationManager->expects($this->once())
			->method('createNotification')
			->willReturn($this->notification);
		$this->notification->expects($this->once())
			->method('setApp')
			->with('workflow_ocr')
			->willReturn($this->notification);
		$this->notification->expects($this->once())
			->method('setUser')
			->with('user1')
			->willReturn($this->notification);
		$this->notification->expects($this->once())
			->method('setDateTime')
			->willReturn($this->notification);
		$this->notification->expects($this->once())
			->method('setSubject')
			->with('ocr_error', ['message' => 'testnotification'])
			->willReturn($this->notification);
		$this->notification->expects($this->once())
			->method('setObject')
			->with('file', '123')
			->willReturn($this->notification);
		$this->notificationManager->expects($this->once())
			->method('notify')
			->with($this->notification);

		$this->service->createErrorNotification('user1', 'testnotification', 123);
	}

	public function testCreateErrorNotificationWithoutFileId() {
		$this->notificationManager->expects($this->once())
			->method('createNotification')
			->willReturn($this->notification);
		$this->notification->expects($this->once())
			->method('setApp')
			->with('workflow_ocr')
			->willReturn($this->notification);
		$this->notification->expects($this->once())
			->method('setUser')
			->with('user1')
			->willReturn($this->notification);
		$this->notification->expects($this->once())
			->method('setDateTime')
			->willReturn($this->notification);
		$this->notification->expects($this->once())
			->method('setSubject')
			->with('ocr_error', ['message' => 'testnotification'])
			->willReturn($this->notification);
		$this->notification->expects($this->once())
			->method('setObject')
			->with('ocr', 'ocr');
		$this->notificationManager->expects($this->once())
			->method('notify')
			->with($this->notification);

		$this->service->createErrorNotification('user1', 'testnotification');
	}

	public function testCreateErrorNotificationDoesNothingIfUserIdIsNotSet() {
		$this->notificationManager->expects($this->never())
			->method('createNotification');
		$this->notificationManager->expects($this->never())
			->method('notify');
		$this->notification->expects($this->never())
			->method('setApp');
		$this->notification->expects($this->never())
			->method('setUser');
		$this->notification->expects($this->never())
			->method('setDateTime');
		$this->notification->expects($this->never())
			->method('setSubject');
		$this->notification->expects($this->never())
			->method('setObject');

		$this->service->createErrorNotification(null, 'testnotification', 123);
	}
}
