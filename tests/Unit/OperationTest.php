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

namespace OCA\WorkflowOcr\Tests\Unit;

use OCA\WorkflowOcr\BackgroundJobs\ProcessFileJob;
use OCA\WorkflowOcr\Operation;
use OCP\BackgroundJob\IJobList;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\GenericEvent;
use OCP\Files\FileInfo;
use OCP\Files\Node;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IUser;
use OCP\WorkflowEngine\IManager;
use OCP\WorkflowEngine\IRuleMatcher;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OperationTest extends TestCase {
	
	/** @var IJobList|MockObject */
	private $jobList;
	/** @var IL10N|MockObject */
	private $l;
	/** @var ILogger|MockObject */
	private $logger;
	
	protected function setUp(): void {
		parent::setUp();
		$this->jobList = $this->createMock(IJobList::class);
		$this->l = $this->createMock(IL10N::class);
		$this->logger = $this->createMock(ILogger::class);
	}

	/**
	 * @dataProvider dataProvider_InvalidEvents
	 */
	public function testDoesNothingOnInvalidEvent(string $eventName, Event $event) {
		$this->jobList->expects($this->never())
			->method('add')
			->withAnyParameters();
		$this->logger->expects($this->once())
			->method('debug')
			->withAnyParameters();

		$operation = new Operation($this->jobList, $this->l, $this->logger);
		/** @var IRuleMatcher */
		$ruleMatcher = $this->createMock(IRuleMatcher::class);
		$operation->onEvent($eventName, $event, $ruleMatcher);
	}

	public function testDoesNothingOnFolderEvent() {
		$this->jobList->expects($this->never())
			->method('add')
			->withAnyParameters();
		$this->logger->expects($this->once())
			->method('debug')
			->withAnyParameters();
		
		$operation = new Operation($this->jobList, $this->l, $this->logger);

		$fileMock = $this->createMock(Node::class);
		$fileMock->method('getType')
			->willReturn(FileInfo::TYPE_FOLDER);
		$event = new GenericEvent($fileMock);
		/** @var IRuleMatcher */
		$ruleMatcher = $this->createMock(IRuleMatcher::class);
		$eventName = '\OCP\Files::postCreate';

		$operation->onEvent($eventName, $event, $ruleMatcher);
	}

	/**
	 * @dataProvider dataProvider_InvalidFilePaths
	 */
	public function testDoesNothingOnInvalidFilePath(string $filePath) {
		$this->jobList->expects($this->never())
			->method('add')
			->withAnyParameters();
		$this->logger->expects($this->once())
			->method('debug')
			->withAnyParameters();
		
		$operation = new Operation($this->jobList, $this->l, $this->logger);

		$fileMock = $this->createMock(Node::class);
		$fileMock->method('getType')
			->willReturn(FileInfo::TYPE_FILE);
		$fileMock->method('getPath')
			->willReturn($filePath);
		$event = new GenericEvent($fileMock);
		/** @var IRuleMatcher */
		$ruleMatcher = $this->createMock(IRuleMatcher::class);
		$eventName = '\OCP\Files::postCreate';

		$operation->onEvent($eventName, $event, $ruleMatcher);
	}

	public function testAddWithCorrectFilePathAndUser() {
		$filePath = "/admin/files/path/to/file.pdf";
		$uid = 'admin';
		$this->jobList->expects($this->once())
			->method('add')
			->with(ProcessFileJob::class, ['filePath' => $filePath, 'uid' => $uid]);
		
		$operation = new Operation($this->jobList, $this->l, $this->logger);

		$userMock = $this->createMock(IUser::class);
		$userMock->expects($this->once())
			->method('getUID')
			->willReturn($uid);
		$fileMock = $this->createMock(Node::class);
		$fileMock->method('getType')
			->willReturn(FileInfo::TYPE_FILE);
		$fileMock->method('getPath')
			->willReturn($filePath);
		$fileMock->method('getOwner')
			->willReturn($userMock);
		$event = new GenericEvent($fileMock);
		/** @var IRuleMatcher */
		$ruleMatcher = $this->createMock(IRuleMatcher::class);
		$eventName = '\OCP\Files::postCreate';

		$operation->onEvent($eventName, $event, $ruleMatcher);
	}

	/**
	 * @dataProvider dataProvider_ValidScopes
	 */
	public function testIsAvailableForScope(int $scope) {
		$operation = new Operation($this->jobList, $this->l, $this->logger);
		$result = $operation->isAvailableForScope($scope);

		$this->assertTrue($result);
	}

	public function dataProvider_InvalidEvents() {
		$arr = [
			["\OCP\Files\preWrite", new GenericEvent()],
			["\OCP\Files\preCreate", new GenericEvent()],
			["\OCP\Files\preDelete", new GenericEvent()],
			["\OCP\Files\postDelete", new GenericEvent()],
			["\OCP\Files\postTouch", new GenericEvent()],
			["\OCP\Files\preTouch", new GenericEvent()],
			["\OCP\Files\preCopy", new GenericEvent()],
			["\OCP\Files\postCopy", new GenericEvent()],
			["\OCP\Files\preRename", new GenericEvent()],
			["\OCP\Files\postRename", new GenericEvent()],
			["\OCP\Files\postWrite", new Event()],
			["\OCP\Files\postCreate", new Event()],
		];
		return $arr;
	}

	public function dataProvider_InvalidFilePaths() {
		$arr = [
			["/user/nofiles/somefile.pdf"],
			["/invalidmount/data/somefile.pdf"],
			["/some/somefile.txt"]
		];
		return $arr;
	}

	public function dataProvider_ValidScopes() {
		return [
			[IManager::SCOPE_ADMIN],
			[IManager::SCOPE_USER]
		];
	}
}
