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

namespace OCA\WorkflowOcr\Tests\Unit\Notification;

use OC\Notification\Notification;
use OCA\WorkflowOcr\Notification\Notifier;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\AlreadyProcessedException;
use OCP\Notification\INotification;
use OCP\RichObjectStrings\IValidator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class NotifierTest extends TestCase {
	/** @var IFactory|MockObject */
	private $l10nFactory;

	/** @var IRootFolder|MockObject */
	private $rootFolder;

	/** @var LoggerInterface|MockObject */
	private $logger;

	/** @var IURLGenerator|MockObject */
	private $urlGenerator;

	/** @var Notifier */
	private $notifier;

	public function setUp() : void {
		$this->l10nFactory = $this->createMock(IFactory::class);
		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);

		$this->notifier = new Notifier(
			$this->l10nFactory,
			$this->urlGenerator,
			$this->rootFolder,
			$this->logger
		);
	}

	public function testGetIdReturnsWorkflowOcrName() {
		$this->assertEquals('workflow_ocr', $this->notifier->getId());
	}

	public function testGetDisplayNameReturnsWorkflowOcrName() {
		/** @var IL10N|MockObject */
		$l10n = $this->createMock(IL10N::class);
		$l10n->expects($this->once())
			->method('t')
			->with('Workflow OCR')
			->willReturn('Workflow OCR');
		$this->l10nFactory->expects($this->once())
			->method('get')
			->with('workflow_ocr')
			->willReturn($l10n);
		$this->assertEquals('Workflow OCR', $this->notifier->getName());
	}

	public function testPrepareThrowsInvalidArgumentExceptionOnAppNotWorkflowOcr() {
		/** @var INotification|MockObject */
		$notification = $this->createMock(INotification::class);
		$notification->expects($this->once())
			->method('getApp')
			->willReturn('not_workflow_ocr');
		$this->expectException(\InvalidArgumentException::class);
		$this->notifier->prepare($notification, 'en');
	}

	public function testPrepareThrowsInvalidArgumentExceptionIfNotificationSubjectNotOcrError() {
		/** @var INotification|MockObject */
		$notification = $this->createMock(INotification::class);
		$notification->expects($this->once())
			->method('getApp')
			->willReturn('workflow_ocr');
		$notification->expects($this->once())
			->method('getSubject')
			->willReturn('not_ocr_error');
		$this->expectException(AlreadyProcessedException::class);
		$this->notifier->prepare($notification, 'en');
	}

	public function testPrepareConstructsOcrErrorCorrectlyWithFileId() {
		/** @var IValidator|MockObject */
		$validator = $this->createMock(IValidator::class);
		/** @var IL10N|MockObject */
		$l10n = $this->createMock(IL10N::class);
		$l10n->expects($this->once())
			->method('t')
			->with('Workflow OCR error for file {file}')
			->willReturn('<translated> Workflow OCR error for file {file}');
		$this->l10nFactory->expects($this->once())
			->method('get')
			->with('workflow_ocr')
			->willReturn($l10n);

		$notification = new Notification($validator);
		$notification->setUser('user');
		$notification->setApp('workflow_ocr');
		$notification->setSubject('ocr_error', ['message' => 'mymessage']);
		$notification->setObject('file', '123');

		/** @var File|MockObject */
		$file = $this->createMock(File::class);
		$file->expects($this->once())
			->method('getPath')
			->willReturn('admin/files/file.txt');
		$file->expects($this->once())
			->method('getName')
			->willReturn('file.txt');
		$file->expects($this->once())
			->method('getId')
			->willReturn('123');
		/** @var Folder|MockObject */
		$userFolder = $this->createMock(Folder::class);
		$userFolder->expects($this->once())
			->method('getById')
			->with('123')
			->willReturn(['file' => $file]);
		$userFolder->expects($this->once())
			->method('getRelativePath')
			->with('admin/files/file.txt')
			->willReturn('files/file.txt');
		$this->rootFolder->expects($this->once())
			->method('getUserFolder')
			->with('user')
			->willReturn($userFolder);
		$this->urlGenerator->expects($this->once())
			->method('imagePath')
			->with('workflow_ocr', 'app-dark.svg')
			->willReturn('apps/workflow_ocr/app-dark.svg');
		$this->urlGenerator->expects($this->once())
			->method('getAbsoluteURL')
			->with('apps/workflow_ocr/app-dark.svg')
			->willReturn('http://localhost/index.php/apps/workflow_ocr/app-dark.svg');
		$this->urlGenerator->expects($this->once())
			->method('linkToRouteAbsolute')
			->with('files.viewcontroller.showFile', ['fileid' => '123'])
			->willReturn('http://localhost/index.php/apps/files/?file=123');

		$preparedNotification = $this->notifier->prepare($notification, 'en');
		
		$richSubject = $preparedNotification->getRichSubject();
		$richSubjectParams = $preparedNotification->getRichSubjectParameters();

		$this->assertEquals('<translated> Workflow OCR error for file {file}', $richSubject);
		$this->assertEquals(['file' => [
			'type' => 'file',
			'id' => '123',
			'name' => 'file.txt',
			'path' => 'files/file.txt',
			'link' => 'http://localhost/index.php/apps/files/?file=123'
		]], $richSubjectParams);
	}

	public function testPrepareConstructsOcrErrorCorrectlyWithoutFile() {
		/** @var IValidator|MockObject */
		$validator = $this->createMock(IValidator::class);
		/** @var IL10N|MockObject */
		$l10n = $this->createMock(IL10N::class);
		$l10n->expects($this->once())
			->method('t')
			->with('Workflow OCR error')
			->willReturn('<translated> Workflow OCR error');
		$this->l10nFactory->expects($this->once())
			->method('get')
			->with('workflow_ocr')
			->willReturn($l10n);

		$notification = new Notification($validator);
		$notification->setUser('user');
		$notification->setApp('workflow_ocr');
		$notification->setSubject('ocr_error', ['message' => 'mymessage']);
		$notification->setObject('ocr', 'ocr');

		$this->urlGenerator->expects($this->once())
			->method('imagePath')
			->with('workflow_ocr', 'app-dark.svg')
			->willReturn('apps/workflow_ocr/app-dark.svg');
		$this->urlGenerator->expects($this->once())
			->method('getAbsoluteURL')
			->with('apps/workflow_ocr/app-dark.svg')
			->willReturn('http://localhost/index.php/apps/workflow_ocr/app-dark.svg');
		$this->urlGenerator->expects($this->never())
			->method('linkToRouteAbsolute');

		$preparedNotification = $this->notifier->prepare($notification, 'en');
		
		$this->assertEmpty($preparedNotification->getRichSubject());
		$this->assertEmpty($preparedNotification->getRichSubjectParameters());
		$this->assertEquals('<translated> Workflow OCR error', $preparedNotification->getParsedSubject());
	}

	public function testSendsFallbackNotificationWithoutFileInfoIfFileNotFoundWasThrown() {
		/** @var IValidator|MockObject */
		$validator = $this->createMock(IValidator::class);
		/** @var IL10N|MockObject */
		$l10n = $this->createMock(IL10N::class);
		$l10n->expects($this->once())
			->method('t')
			->with('Workflow OCR error')
			->willReturn('<translated> Workflow OCR error');
		$this->l10nFactory->expects($this->once())
			->method('get')
			->with('workflow_ocr')
			->willReturn($l10n);

		$notification = new Notification($validator);
		$notification->setUser('user');
		$notification->setApp('workflow_ocr');
		$notification->setSubject('ocr_error', ['message' => 'mymessage']);
		$notification->setObject('file', '123');

		/** @var Folder|MockObject */
		$userFolder = $this->createMock(Folder::class);
		$ex = new \OCP\Files\NotFoundException('nope ... sorry');
		$userFolder->expects($this->once())
			->method('getById')
			->with('123')
			->willThrowException($ex); // This is what we want to test ...
		$userFolder->expects($this->never())
			->method('getRelativePath');
		$this->rootFolder->expects($this->once())
			->method('getUserFolder')
			->with('user')
			->willReturn($userFolder);
		$this->urlGenerator->expects($this->once())
			->method('imagePath')
			->with('workflow_ocr', 'app-dark.svg')
			->willReturn('apps/workflow_ocr/app-dark.svg');
		$this->urlGenerator->expects($this->once())
			->method('getAbsoluteURL')
			->with('apps/workflow_ocr/app-dark.svg')
			->willReturn('http://localhost/index.php/apps/workflow_ocr/app-dark.svg');
		$this->logger->expects($this->once())
			->method('error')
			->with('nope ... sorry', ['exception' => $ex]);

		$notification = $this->notifier->prepare($notification, 'en');

		$this->assertEmpty($notification->getRichSubject());
		$this->assertEquals('<translated> Workflow OCR error', $notification->getParsedSubject());
	}

	public function testSendsFallbackNotificationWithoutFileInfoIfReturnedFileArrayWasEmpty() {
		/** @var IValidator|MockObject */
		$validator = $this->createMock(IValidator::class);
		/** @var IL10N|MockObject */
		$l10n = $this->createMock(IL10N::class);
		$l10n->expects($this->once())
			->method('t')
			->with('Workflow OCR error')
			->willReturn('<translated> Workflow OCR error');
		$this->l10nFactory->expects($this->once())
			->method('get')
			->with('workflow_ocr')
			->willReturn($l10n);

		$notification = new Notification($validator);
		$notification->setUser('user');
		$notification->setApp('workflow_ocr');
		$notification->setSubject('ocr_error', ['message' => 'mymessage']);
		$notification->setObject('file', '123');

		/** @var Folder|MockObject */
		$userFolder = $this->createMock(Folder::class);
		$userFolder->expects($this->once())
			->method('getById')
			->with('123')
			->willReturn([]); // This is what we want to test ...
		$userFolder->expects($this->never())
			->method('getRelativePath');
		$this->rootFolder->expects($this->once())
			->method('getUserFolder')
			->with('user')
			->willReturn($userFolder);
		$this->urlGenerator->expects($this->once())
			->method('imagePath')
			->with('workflow_ocr', 'app-dark.svg')
			->willReturn('apps/workflow_ocr/app-dark.svg');
		$this->urlGenerator->expects($this->once())
			->method('getAbsoluteURL')
			->with('apps/workflow_ocr/app-dark.svg')
			->willReturn('http://localhost/index.php/apps/workflow_ocr/app-dark.svg');
		$this->logger->expects($this->once())
			->method('warning')
			->with('Could not find file with id {fileId} for user {uid}', ['fileId' => '123', 'uid' => 'user']);

		$notification = $this->notifier->prepare($notification, 'en');

		$this->assertEmpty($notification->getRichSubject());
		$this->assertEquals('<translated> Workflow OCR error', $notification->getParsedSubject());
	}
}
