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

namespace OCA\WorkflowOcr\Tests\Unit\Service;

use OCA\WorkflowOcr\Model\GlobalSettings;
use OCA\WorkflowOcr\Model\WorkflowSettings;
use OCA\WorkflowOcr\OcrProcessors\IOcrProcessor;
use OCA\WorkflowOcr\OcrProcessors\IOcrProcessorFactory;
use OCA\WorkflowOcr\Service\IGlobalSettingsService;
use OCA\WorkflowOcr\Service\OcrService;
use OCP\Files\File;
use OCP\SystemTag\ISystemTagObjectMapper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class OcrServiceTest extends TestCase {
	/** @var IOcrProcessorFactory|MockObject */
	private $ocrProcessorFactory;
	/** @var IOcrProcessor|MockObject */
	private $ocrProcessor;
	/** @var IGlobalSettingsService|MockObject*/
	private $globalSettingsService;
	/** @var ISystemTagObjectMapper|MockObject */
	private $systemTagObjectMapper;
	/** @var LoggerInterface|MockObject */
	private $logger;
	/** @var OcrService */
	private $ocrService;
	/** @var File|MockObject */
	private $fileInput;

	public function setUp() : void {
		parent::setUp();

		$this->globalSettingsService = $this->createMock(IGlobalSettingsService::class);
		$this->ocrProcessorFactory = $this->createMock(IOcrProcessorFactory::class);
		$this->ocrProcessor = $this->createMock(IOcrProcessor::class);
		$this->systemTagObjectMapper = $this->createMock(ISystemTagObjectMapper::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->fileInput = $this->createMock(File::class);
		$this->ocrService = new OcrService($this->ocrProcessorFactory, $this->globalSettingsService, $this->systemTagObjectMapper, $this->logger);
	}

	public function testCallsOcrProcessor_WithCorrectArguments() {
		$mime = 'application/pdf';
		$content = 'someFileContent';
		$settings = new WorkflowSettings();
		$globalSettings = new GlobalSettings();

		$this->fileInput->method('getMimeType')
			->willReturn($mime);
		$this->fileInput->method('getContent')
			->willReturn($content);

		$this->globalSettingsService->expects($this->once())
			->method('getGlobalSettings')
			->willReturn($globalSettings);

		$this->ocrProcessorFactory->expects($this->once())
			->method('create')
			->with($mime)
			->willReturn($this->ocrProcessor);

		$this->ocrProcessor->expects($this->once())
			->method('ocrFile')
			->with($this->fileInput, $settings, $globalSettings);

		$this->ocrService->ocrFile($this->fileInput, $settings);
	}

	public function testCallsSystemTagObjectManager_WithCorrectArguments() {
		$mime = 'application/pdf';
		$content = 'someFileContent';
		$settings = new WorkflowSettings("{\"tagsToRemoveAfterOcr\": [1,2], \"tagsToAddAfterOcr\": [3,4]}");
		$globalSettings = new GlobalSettings();

		$this->fileInput->method('getMimeType')
			->willReturn($mime);
		$this->fileInput->method('getContent')
			->willReturn($content);
		$this->fileInput->method('getId')
			->willReturn(42);

		$this->globalSettingsService->expects($this->once())
			->method('getGlobalSettings')
			->willReturn($globalSettings);

		$this->ocrProcessorFactory->expects($this->once())
			->method('create')
			->with($mime)
			->willReturn($this->ocrProcessor);

		// Check call for function:
		// unassignTags(string $objId, string $objectType, $tagIds);
		$this->systemTagObjectMapper->expects($this->exactly(2))
			->method('unassignTags')
			->withConsecutive(["42", "files", 1], ["42", "files", 2]);

		// Check call for function:
		// assignTags(string $objId, string $objectType, $tagIds);
		$this->systemTagObjectMapper->expects($this->exactly(2))
			->method('assignTags')
			->withConsecutive(["42", "files", 3], ["42", "files", 4]);

		$this->ocrService->ocrFile($this->fileInput, $settings);
	}

	public function testCatchesTagNotFoundException() {
		$mime = 'application/pdf';
		$content = 'someFileContent';
		$settings = new WorkflowSettings("{\"tagsToRemoveAfterOcr\": [1], \"tagsToAddAfterOcr\": [2]}");
		$globalSettings = new GlobalSettings();

		$this->fileInput->method('getMimeType')
			->willReturn($mime);
		$this->fileInput->method('getContent')
			->willReturn($content);
		$this->fileInput->method('getId')
			->willReturn(42);

		$this->globalSettingsService->expects($this->once())
			->method('getGlobalSettings')
			->willReturn($globalSettings);

		$this->ocrProcessorFactory->expects($this->once())
			->method('create')
			->with($mime)
			->willReturn($this->ocrProcessor);

		$this->systemTagObjectMapper->expects($this->once())
			->method('unassignTags')
			->willThrowException(new \OCP\SystemTag\TagNotFoundException());

		$this->systemTagObjectMapper->expects($this->once())
			->method('assignTags')
			->willThrowException(new \OCP\SystemTag\TagNotFoundException());

		$this->logger->expects($this->exactly(2))
			->method('warning');
		
		$this->ocrService->ocrFile($this->fileInput, $settings);
	}
}
