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

use OCA\WorkflowEngine\Entity\File;
use OCA\WorkflowOcr\BackgroundJobs\ProcessFileJob;
use OCA\WorkflowOcr\Helper\IProcessingFileAccessor;
use OCA\WorkflowOcr\Operation;
use OCP\BackgroundJob\IJobList;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\GenericEvent;
use OCP\Files\FileInfo;
use OCP\Files\Node;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\WorkflowEngine\IManager;
use OCP\WorkflowEngine\IRuleMatcher;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class OperationTest extends TestCase {

	/** @var IJobList|MockObject */
	private $jobList;
	/** @var IL10N|MockObject */
	private $l;
	/** @var LoggerInterface|MockObject */
	private $logger;
	/** @var IURLGenerator|MockObject */
	private $urlGenerator;
	/** @var IProcessingFileAccessor|MockObject */
	private $processingFileAccessor;
	/** @var IRuleMatcher|MockObject */
	private $ruleMatcher;

	protected function setUp(): void {
		parent::setUp();
		$this->jobList = $this->createMock(IJobList::class);
		$this->l = $this->createMock(IL10N::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->processingFileAccessor = $this->createMock(IProcessingFileAccessor::class);
		$this->ruleMatcher = $this->createMock(IRuleMatcher::class);
		$this->ruleMatcher->method('getFlows')
			->willReturn([$this->createMock(Operation::class)]); // simulate single matching operation
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

		$operation = new Operation($this->jobList, $this->l, $this->logger, $this->urlGenerator, $this->processingFileAccessor);
		$operation->onEvent($eventName, $event, $this->ruleMatcher);
	}

	/**
	 * @dataProvider dataProvider_InvalidRuleMatcherResults
	 */
	public function testDoesNothingIfRuleMatcherDoesNotMatch($ruleMatcherResult) {
		$this->jobList->expects($this->never())
			->method('add')
			->withAnyParameters();
		$this->logger->expects($this->once())
			->method('debug')
			->withAnyParameters();

		$operation = new Operation($this->jobList, $this->l, $this->logger, $this->urlGenerator, $this->processingFileAccessor);

		/** @var IRuleMatcher|MockObject */
		$ruleMatcher = $this->createMock(IRuleMatcher::class);
		$ruleMatcher->expects($this->once())
			->method('getFlows')
			->with(true)
			->willReturn($ruleMatcherResult);

		$operation->onEvent("\OCP\Files::postCreate", new GenericEvent(), $ruleMatcher);
	}

	public function testDoesNothingOnFolderEvent() {
		$this->jobList->expects($this->never())
			->method('add')
			->withAnyParameters();
		$this->logger->expects($this->once())
			->method('debug')
			->withAnyParameters();

		$operation = new Operation($this->jobList, $this->l, $this->logger, $this->urlGenerator, $this->processingFileAccessor);

		$fileMock = $this->createMock(Node::class);
		$fileMock->method('getType')
			->willReturn(FileInfo::TYPE_FOLDER);
		$event = new GenericEvent($fileMock);
		$eventName = '\OCP\Files::postCreate';

		$operation->onEvent($eventName, $event, $this->ruleMatcher);
	}

	public function testDoesNothingOnPostWriteTriggeredByCurrentOcrProcess() {
		$this->jobList->expects($this->never())
			->method('add')
			->withAnyParameters();
		$this->logger->expects($this->once())
			->method('debug')
			->withAnyParameters();

		/** @var IProcessingFileAccessor|MockObject */
		$processingFileAccessorMock = $this->createMock(IProcessingFileAccessor::class);
		$processingFileAccessorMock->expects($this->once())
			->method('getCurrentlyProcessedFileId')
			->willReturn(42);

		$operation = new Operation($this->jobList, $this->l, $this->logger, $this->urlGenerator, $processingFileAccessorMock);

		$userMock = $this->createMock(IUser::class);
		$userMock->expects($this->never())
			->method('getUID');
		$fileMock = $this->createMock(Node::class);
		$fileMock->method('getType')
			->willReturn(FileInfo::TYPE_FILE);
		$fileMock->method('getPath')
			->willReturn('/someuser/files/somefile.pdf');
		$fileMock->method('getOwner')
			->willReturn($userMock);
		$fileMock->method('getId')
			->willReturn(42);
		$event = new GenericEvent($fileMock);
		$eventName = '\OCP\Files::postCreate';

		$operation->onEvent($eventName, $event, $this->ruleMatcher);
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

		$operation = new Operation($this->jobList, $this->l, $this->logger, $this->urlGenerator, $this->processingFileAccessor);

		$fileMock = $this->createMock(Node::class);
		$fileMock->method('getType')
			->willReturn(FileInfo::TYPE_FILE);
		$fileMock->method('getPath')
			->willReturn($filePath);
		$event = new GenericEvent($fileMock);
		$eventName = '\OCP\Files::postCreate';

		$operation->onEvent($eventName, $event, $this->ruleMatcher);
	}

	public function testDoesNothingOnFileWithoutOwner() {
		$this->jobList->expects($this->never())
			->method('add')
			->withAnyParameters();
		$this->logger->expects($this->once())
			->method('debug')
			->withAnyParameters();

		$operation = new Operation($this->jobList, $this->l, $this->logger, $this->urlGenerator, $this->processingFileAccessor);

		$fileMock = $this->createMock(Node::class);
		$fileMock->method('getType')
			->willReturn(FileInfo::TYPE_FILE);
		$fileMock->method('getPath')
			->willReturn('/admin/files/path/to/file.pdf');
		$fileMock->method('getOwner')
			->willReturn(null);

		$event = new GenericEvent($fileMock);
		$eventName = '\OCP\Files::postCreate';

		$operation->onEvent($eventName, $event, $this->ruleMatcher);
	}

	public function testAddWithCorrectFilePathAndUser() {
		$filePath = "/admin/files/path/to/file.pdf";
		$uid = 'admin';
		$this->jobList->expects($this->once())
			->method('add')
			->with(ProcessFileJob::class, ['filePath' => $filePath, 'uid' => $uid]);

		$operation = new Operation($this->jobList, $this->l, $this->logger, $this->urlGenerator, $this->processingFileAccessor);

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
		$fileMock->method('getId')
			->willReturn(42);
		$event = new GenericEvent($fileMock);
		$eventName = '\OCP\Files::postCreate';

		$operation->onEvent($eventName, $event, $this->ruleMatcher);
	}

	/**
	 * @dataProvider dataProvider_ValidScopes
	 */
	public function testIsAvailableForScope(int $scope) {
		$operation = new Operation($this->jobList, $this->l, $this->logger, $this->urlGenerator, $this->processingFileAccessor);
		$result = $operation->isAvailableForScope($scope);

		$this->assertTrue($result);
	}

	public function testDoesNothing_OnValidateOperation() {
		$this->jobList->expects($this->never())
			->method($this->anything());
		$this->l->expects($this->never())
			->method($this->anything());
		$this->logger->expects($this->never())
			->method($this->anything());
		$this->urlGenerator->expects($this->never())
			->method($this->anything());

		$operation = new Operation($this->jobList, $this->l, $this->logger, $this->urlGenerator, $this->processingFileAccessor);

		$operation->validateOperation('aName', [], 'aOp');
	}

	public function testCallsLang_OnGetDisplayName() {
		$this->l->expects($this->once())
			->method('t');

		$operation = new Operation($this->jobList, $this->l, $this->logger, $this->urlGenerator, $this->processingFileAccessor);

		$operation->getDisplayName();
	}


	public function testCallsLang_OnGetDescription() {
		$this->l->expects($this->once())
			->method('t');

		$operation = new Operation($this->jobList, $this->l, $this->logger, $this->urlGenerator, $this->processingFileAccessor);

		$operation->getDescription();
	}

	public function testCallsUrlGenerator_OnGetIcon() {
		$this->urlGenerator->expects($this->once())
			->method('imagePath');

		$operation = new Operation($this->jobList, $this->l, $this->logger, $this->urlGenerator, $this->processingFileAccessor);

		$operation->getIcon();
	}

	public function testEntityIdIsFile() {
		$operation = new Operation($this->jobList, $this->l, $this->logger, $this->urlGenerator, $this->processingFileAccessor);

		$this->assertEquals(File::class, $operation->getEntityId());
	}

	public function dataProvider_InvalidEvents() {
		$arr = [
			["\OCP\Files::preWrite", new GenericEvent()],
			["\OCP\Files::preCreate", new GenericEvent()],
			["\OCP\Files::preDelete", new GenericEvent()],
			["\OCP\Files::postDelete", new GenericEvent()],
			["\OCP\Files::postTouch", new GenericEvent()],
			["\OCP\Files::preTouch", new GenericEvent()],
			["\OCP\Files::preCopy", new GenericEvent()],
			["\OCP\Files::postCopy", new GenericEvent()],
			["\OCP\Files::preRename", new GenericEvent()],
			["\OCP\Files::postRename", new GenericEvent()],
			["\OCP\Files::postWrite", new Event()],
			["\OCP\Files::postCreate", new Event()],
		];
		return $arr;
	}

	public function dataProvider_InvalidRuleMatcherResults() {
		return [
			[ [] ],
			[ null ]
		];
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
