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
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\SystemTag\MapperEvent;
use OCP\WorkflowEngine\IManager;
use OCP\WorkflowEngine\IRuleMatcher;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use UnexpectedValueException;

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
	/** @var IRootFolder|MockObject */
	private $rootFolder;

	private const SETTINGS = "{\"languages\":[\"de\"],\"removeBackground\":true}";

	protected function setUp(): void {
		parent::setUp();
		
		$this->jobList = $this->createMock(IJobList::class);
		$this->l = $this->createMock(IL10N::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->processingFileAccessor = $this->createMock(IProcessingFileAccessor::class);
		$this->ruleMatcher = $this->createMock(IRuleMatcher::class);
		$this->rootFolder = $this->createMock(IRootFolder::class);

		$match = ['operation' => self::SETTINGS];
		$this->ruleMatcher->method('getFlows')
			->willReturn($match); // simulate single matching operation
	}

	public function testDoesNothingIfRuleMatcherDoesNotMatch() {
		$this->jobList->expects($this->never())
			->method('add')
			->withAnyParameters();
		$this->logger->expects($this->atLeastOnce())
			->method('debug')
			->withAnyParameters();

		$operation = new Operation($this->jobList, $this->l, $this->logger, $this->urlGenerator, $this->processingFileAccessor, $this->rootFolder);

		/** @var IRuleMatcher|MockObject */
		$ruleMatcher = $this->createMock(IRuleMatcher::class); // test only works with local mock
		$ruleMatcherResult = [];
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
		$this->logger->expects($this->atLeastOnce())
			->method('debug')
			->withAnyParameters();

		$operation = new Operation($this->jobList, $this->l, $this->logger, $this->urlGenerator, $this->processingFileAccessor, $this->rootFolder);

		$fileMock = $this->createMock(Node::class);
		$fileMock->method('getType')
			->willReturn(FileInfo::TYPE_FOLDER);
		$event = new GenericEvent($fileMock);
		$eventName = '\OCP\Files::postCreate';

		$operation->onEvent($eventName, $event, $this->ruleMatcher);
	}

	public function testDoesNothingOnPostWriteTriggeredByCurrentOcrProcess() {
		$fileId = 42;

		$this->jobList->expects($this->never())
			->method('add')
			->withAnyParameters();
		$this->logger->expects($this->atLeastOnce())
			->method('debug')
			->withAnyParameters();

		$this->processingFileAccessor->expects($this->once())
			->method('getCurrentlyProcessedFileId')
			->willReturn($fileId);

		$operation = new Operation($this->jobList, $this->l, $this->logger, $this->urlGenerator, $this->processingFileAccessor, $this->rootFolder);

		/** @var MockObject|IUser */
		$userMock = $this->createMock(IUser::class);
		$userMock->expects($this->never())
			->method('getUID');
		/** @var MockObject|Node */
		$fileMock = $this->createMock(Node::class);
		$fileMock->method('getType')
			->willReturn(FileInfo::TYPE_FILE);
		$fileMock->method('getPath')
			->willReturn('/someuser/files/somefile.pdf');
		$fileMock->method('getOwner')
			->willReturn($userMock);
		$fileMock->method('getId')
			->willReturn($fileId);
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
		$this->logger->expects($this->exactly(2))
			->method('debug')
			->withAnyParameters();

		$operation = new Operation($this->jobList, $this->l, $this->logger, $this->urlGenerator, $this->processingFileAccessor, $this->rootFolder);

		/** @var MockObject|Node */
		$fileMock = $this->createMock(Node::class);
		$fileMock->method('getType')
			->willReturn(FileInfo::TYPE_FILE);
		$fileMock->method('getPath')
			->willReturn($filePath);
		$fileMock->method('getId')
			->willReturn(42);
		$event = new GenericEvent($fileMock);
		$eventName = '\OCP\Files::postCreate';

		$operation->onEvent($eventName, $event, $this->ruleMatcher);
	}

	public function testDoesNothingOnFileWithoutOwner() {
		$this->jobList->expects($this->never())
			->method('add')
			->withAnyParameters();
		$this->logger->expects($this->atLeastOnce())
			->method('debug')
			->withAnyParameters();

		$operation = new Operation($this->jobList, $this->l, $this->logger, $this->urlGenerator, $this->processingFileAccessor, $this->rootFolder);

		/** @var MockObject|Node */
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
		$fileId = 42;
		$uid = 'admin';
		$this->jobList->expects($this->once())
			->method('add')
			->with(ProcessFileJob::class, ['fileId' => $fileId, 'uid' => $uid, 'settings' => self::SETTINGS]);

		$operation = new Operation($this->jobList, $this->l, $this->logger, $this->urlGenerator, $this->processingFileAccessor, $this->rootFolder);

		/** @var MockObject|IUser */
		$userMock = $this->createMock(IUser::class);
		$userMock->expects($this->never())
			->method('getUID')
			->willReturn($uid);
		/** @var MockObject|Node */
		$fileMock = $this->createMock(Node::class);
		$fileMock->method('getType')
			->willReturn(FileInfo::TYPE_FILE);
		$fileMock->method('getPath')
			->willReturn($filePath);
		$fileMock->method('getOwner')
			->willReturn($userMock);
		$fileMock->method('getId')
			->willReturn($fileId);
		$event = new GenericEvent($fileMock);
		$eventName = '\OCP\Files::postCreate';

		$operation->onEvent($eventName, $event, $this->ruleMatcher);
	}

	/**
	 * @dataProvider dataProvider_ValidScopes
	 */
	public function testIsAvailableForScope(int $scope) {
		$operation = new Operation($this->jobList, $this->l, $this->logger, $this->urlGenerator, $this->processingFileAccessor, $this->rootFolder);
		$result = $operation->isAvailableForScope($scope);

		$this->assertTrue($result);
	}

	/**
	 * @dataProvider dataProvider_EmptyOperationSettings
	 */
	public function testValidateOperationAcceptsEmptyOperationSettings($settings) {
		$this->jobList->expects($this->never())
			->method($this->anything());
		$this->l->expects($this->never())
			->method($this->anything());
		$this->logger->expects($this->never())
			->method($this->anything());
		$this->urlGenerator->expects($this->never())
			->method($this->anything());

		$operation = new Operation($this->jobList, $this->l, $this->logger, $this->urlGenerator, $this->processingFileAccessor, $this->rootFolder);

		$operation->validateOperation('', [], $settings);
	}

	public function testOnValidateOperationThrowsUnexpectedValueExceptionIfJsonSettingsAreInvalid() {
		$operation = new Operation($this->jobList, $this->l, $this->logger, $this->urlGenerator, $this->processingFileAccessor, $this->rootFolder);

		$this->expectException(UnexpectedValueException::class);
		$operation->validateOperation('aName', [], '{ "invalid; "json" }');
	}

	public function testCallsLang_OnGetDisplayName() {
		$this->l->expects($this->once())
			->method('t');

		$operation = new Operation($this->jobList, $this->l, $this->logger, $this->urlGenerator, $this->processingFileAccessor, $this->rootFolder);

		$operation->getDisplayName();
	}


	public function testCallsLang_OnGetDescription() {
		$this->l->expects($this->once())
			->method('t');

		$operation = new Operation($this->jobList, $this->l, $this->logger, $this->urlGenerator, $this->processingFileAccessor, $this->rootFolder);

		$operation->getDescription();
	}

	public function testCallsUrlGenerator_OnGetIcon() {
		$this->urlGenerator->expects($this->once())
			->method('imagePath');

		$operation = new Operation($this->jobList, $this->l, $this->logger, $this->urlGenerator, $this->processingFileAccessor, $this->rootFolder);

		$operation->getIcon();
	}

	public function testEntityIdIsFile() {
		$operation = new Operation($this->jobList, $this->l, $this->logger, $this->urlGenerator, $this->processingFileAccessor, $this->rootFolder);

		$this->assertEquals(File::class, $operation->getEntityId());
	}

	public function testDoesNothingOnUnsupportedEvent() {
		$event = new Event();
		$eventName = '\OCP\Files::someOtherEvent';

		$this->jobList->expects($this->never())
			->method('add');
		$this->logger->expects($this->once())
			->method('warning')
			->withAnyParameters();
		
		$operation = new Operation($this->jobList, $this->l, $this->logger, $this->urlGenerator, $this->processingFileAccessor, $this->rootFolder);
		
		$operation->onEvent($eventName, $event, $this->ruleMatcher);
	}

	public function testDoesNothingOnMapperTypeEventWithObjectTypeFolder() {
		$eventName = '\OCP\SystemTag\ISystemTagObjectMapper::assignTags';
		$event = new MapperEvent($eventName, 'folder', '42', ['ocr']);

		$this->jobList->expects($this->never())
			->method('add');
		$this->logger->expects($this->once())
			->method('warning')
			->withAnyParameters();
		
		$operation = new Operation($this->jobList, $this->l, $this->logger, $this->urlGenerator, $this->processingFileAccessor, $this->rootFolder);
		
		$operation->onEvent($eventName, $event, $this->ruleMatcher);
	}

	public function testDoesNothingOnMapperEventHavingInvalidFileId() {
		$eventName = '\OCP\SystemTag\ISystemTagObjectMapper::assignTags';
		$event = new MapperEvent($eventName, 'files', 'notAInteger', ['ocr']);

		$this->jobList->expects($this->never())
			->method('add');
		$this->logger->expects($this->once())
			->method('warning')
			->withAnyParameters();
		
		$operation = new Operation($this->jobList, $this->l, $this->logger, $this->urlGenerator, $this->processingFileAccessor, $this->rootFolder);
		
		$operation->onEvent($eventName, $event, $this->ruleMatcher);
	}

	public function testDoesNothingOnMapperEventIfFileNotFound() {
		$eventName = '\OCP\SystemTag\ISystemTagObjectMapper::assignTags';
		$event = new MapperEvent($eventName, 'files', '42', ['ocr']);

		$this->jobList->expects($this->never())
			->method('add');
		$this->logger->expects($this->once())
			->method('warning')
			->withAnyParameters();
		/** @var MockObject|IRootFolder */
		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->rootFolder->expects($this->once())
			->method('getById')
			->with(42)
			->willReturn([]);
		
		$operation = new Operation($this->jobList, $this->l, $this->logger, $this->urlGenerator, $this->processingFileAccessor, $this->rootFolder);
		
		$operation->onEvent($eventName, $event, $this->ruleMatcher);
	}

	public function testDoesNothingOnMapperEventIfObjectIdIsFolder() {
		$eventName = '\OCP\SystemTag\ISystemTagObjectMapper::assignTags';
		$event = new MapperEvent($eventName, 'files', '42', ['ocr']);

		$this->jobList->expects($this->never())
			->method('add');
		$this->logger->expects($this->once())
			->method('warning')
			->withAnyParameters();
		/** @var MockObject|IRootFolder */
		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->rootFolder->expects($this->once())
			->method('getById')
			->with(42)
			->willReturn([$this->createMock(Folder::class)]);
		
		$operation = new Operation($this->jobList, $this->l, $this->logger, $this->urlGenerator, $this->processingFileAccessor, $this->rootFolder);
		
		$operation->onEvent($eventName, $event, $this->ruleMatcher);
	}

	public function testFileAddedToQueueOnTagAssignedEvent() {
		$eventName = '\OCP\SystemTag\ISystemTagObjectMapper::assignTags';
		$fileId = 1234;
		$event = new MapperEvent($eventName, 'files', strval($fileId), ['ocr']);
		$filePath = '/someUser/files/someFile.pdf';
		$uid = 'someUser';

		$this->jobList->expects($this->once())
			->method('add')
			->with(ProcessFileJob::class, ['fileId' => $fileId, 'uid' => $uid, 'settings' => self::SETTINGS]);

		/** @var MockObject|IUser */
		$userMock = $this->createMock(IUser::class);
		$userMock->expects($this->never())
			->method('getUID')
			->willReturn($uid);
		/** @var MockObject|\OCP\Files\File */
		$fileMock = $this->createMock(\OCP\Files\File::class);
		$fileMock->method('getType')
			->willReturn(FileInfo::TYPE_FILE);
		$fileMock->method('getPath')
			->willReturn($filePath);
		$fileMock->method('getOwner')
			->willReturn($userMock);
		$fileMock->method('getId')
			->willReturn($fileId);
		/** @var MockObject|IRootFolder */
		$rootFolder = $this->createMock(IRootFolder::class);
		$rootFolder->expects($this->once())
			->method('getById')
			->with($fileId)
			->willReturn([$fileMock]);

		$operation = new Operation($this->jobList, $this->l, $this->logger, $this->urlGenerator, $this->processingFileAccessor, $rootFolder);
		
		$operation->onEvent($eventName, $event, $this->ruleMatcher);
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

	public function dataProvider_EmptyOperationSettings() {
		return [
			[''],
			['{}']
		];
	}
}
