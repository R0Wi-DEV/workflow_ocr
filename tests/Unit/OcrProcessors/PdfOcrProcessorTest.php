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

namespace OCA\WorkflowOcr\Tests\Unit\OcrProcessors;

use OCA\WorkflowOcr\Exception\OcrNotPossibleException;
use OCA\WorkflowOcr\Helper\ISidecarFileAccessor;
use OCA\WorkflowOcr\Model\GlobalSettings;
use OCA\WorkflowOcr\Model\WorkflowSettings;
use OCA\WorkflowOcr\OcrProcessors\PdfOcrProcessor;
use OCA\WorkflowOcr\Wrapper\ICommand;
use OCP\Files\File;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class PdfOcrProcessorTest extends TestCase {
	private const FILE_CONTENT_BEFORE = "someFileContentBefore";
	private const FILE_CONTENT_AFTER = "somePDFFileContentAfter";

	private $fileBeforeMimeType;
	private $ocrMyPdfOutput;

	/** @var File|MockObject */
	private $fileBefore;

	/** @var ICommand|MockObject */
	private $command;
	/** @var LoggerInterface|MockObject */
	private $logger;
	/** @var ISidecarFileAccessor|MockObject */
	private $sidecarFileAccessor;
	/** @var WorkflowSettings */
	private $defaultSettings;
	/** @var GlobalSettings */
	private $defaultGlobalSettings;

	protected function setUp(): void {
		parent::setUp();

		$this->command = $this->createMock(ICommand::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->sidecarFileAccessor = $this->createMock(ISidecarFileAccessor::class);
		$this->defaultSettings = new WorkflowSettings();
		$this->defaultGlobalSettings = new GlobalSettings();
		$this->fileBefore = $this->createMock(File::class);

		$this->fileBefore->method('getContent')
			->willReturn(self::FILE_CONTENT_BEFORE);
		$this->fileBefore->method('getMimeType')
			->will($this->returnCallback(function () {
				return $this->fileBeforeMimeType;
			}));

		$this->fileBeforeMimeType = 'application/pdf';
		$this->ocrMyPdfOutput = self::FILE_CONTENT_AFTER;
		$this->command->method('getOutput')
			->will($this->returnCallback(function () {
				return $this->ocrMyPdfOutput !== self::FILE_CONTENT_AFTER ? $this->ocrMyPdfOutput : self::FILE_CONTENT_AFTER;
			}));
	}

	public function testCallsCommandInterface() {
		$this->command->expects($this->once())
			->method('setCommand')
			->willReturn($this->command);
		$this->command->expects($this->once())
			->method('setStdIn')
			->with(self::FILE_CONTENT_BEFORE);
		$this->command->expects($this->once())
			->method('execute')
			->willReturn(true);

		$processor = new PdfOcrProcessor($this->command, $this->logger, $this->sidecarFileAccessor);
		$result = $processor->ocrFile($this->fileBefore, $this->defaultSettings, $this->defaultGlobalSettings);

		$this->assertEquals(self::FILE_CONTENT_AFTER, $result->getFileContent());
	}

	public function testThrowsOcrNotPossibleException() {
		$this->command->expects($this->once())
			->method('setCommand')
			->willReturn($this->command);
		$this->command->expects($this->once())
			->method('execute')
			->willReturn(false);
		$this->command->expects($this->never())
			->method('getOutput');
		$this->command->expects($this->once())
			->method('getError');
		$this->command->expects($this->once())
			->method('getExitCode');

		$processor = new PdfOcrProcessor($this->command, $this->logger, $this->sidecarFileAccessor);
		$thrown = false;

		try {
			$processor->ocrFile($this->fileBefore, $this->defaultSettings, $this->defaultGlobalSettings);
		} catch (\Throwable $t) {
			$thrown = true;
			$this->assertInstanceOf(OcrNotPossibleException::class, $t);
		}

		$this->assertTrue($thrown);
	}

	public function testLogsWarningIfOcrMyPdfSucceedsWithWarningOutput() {
		$this->command->expects($this->once())
			->method('execute')
			->willReturn(true);
		$this->command->expects($this->once())
			->method('getError')
			->willReturn('getErrorOutput');
		$this->command->expects($this->once())
			->method('getStdErr')
			->willReturn('stdErrOutput');
		$this->command->expects($this->once())
			->method('setCommand')
			->willReturn($this->command);
		$this->command->expects($this->once())
			->method('setStdIn');
		$this->logger->expects($this->once())
			->method('warning')
			->with(
				'OCRmyPDF succeeded with warning(s): {stdErr}, {errorOutput}',
				$this->callback(function ($paramsArray) {
					return is_array($paramsArray) &&
							count($paramsArray) === 2 &&
							$paramsArray['stdErr'] === 'stdErrOutput' &&
							$paramsArray['errorOutput'] === 'getErrorOutput';
				}));

		$processor = new PdfOcrProcessor($this->command, $this->logger, $this->sidecarFileAccessor);
		$processor->ocrFile($this->fileBefore, $this->defaultSettings, $this->defaultGlobalSettings);
	}

	public function testThrowsErrorIfOcrFileWasEmpty() {
		$this->command->expects($this->once())
			->method('execute')
			->willReturn(true);
		$this->command->expects($this->once())
			->method('getError')
			->willReturn('error');
		$this->command->expects($this->once())
			->method('getStdErr')
			->willReturn('stdErr');
		$this->ocrMyPdfOutput = "";

		$thrown = false;
		$processor = new PdfOcrProcessor($this->command, $this->logger, $this->sidecarFileAccessor);

		try {
			$processor->ocrFile($this->fileBefore, $this->defaultSettings, $this->defaultGlobalSettings);
		} catch (\Throwable $t) {
			$thrown = true;
			$this->assertInstanceOf(OcrNotPossibleException::class, $t);
			$this->assertEquals('OCRmyPDF did not produce any output', $t->getMessage());
		}

		$this->assertTrue($thrown);
	}

	public function testLanguageSettingsAreSetCorrectly() {
		$this->command->expects($this->once())
			->method('setCommand')
			->with('ocrmypdf -q --skip-text -l deu+eng - - | cat');
		$this->command->expects($this->once())
			->method('execute')
			->willReturn(true);
		$this->command->expects($this->once())
			->method('getOutput')
			->willReturn('someOcrContent');

		$processor = new PdfOcrProcessor($this->command, $this->logger, $this->sidecarFileAccessor);
		$processor->ocrFile($this->fileBefore, new WorkflowSettings('{"languages": ["deu", "eng"] }'), $this->defaultGlobalSettings);
	}

	public function testRemoveBackgroundFlagIsSetCorrectly() {
		$this->command->expects($this->once())
			->method('setCommand')
			->with('ocrmypdf -q --skip-text --remove-background - - | cat');
		$this->command->expects($this->once())
			->method('execute')
			->willReturn(true);
		$this->command->expects($this->once())
			->method('getOutput')
			->willReturn('someOcrContent');

		$processor = new PdfOcrProcessor($this->command, $this->logger, $this->sidecarFileAccessor);
		$processor->ocrFile($this->fileBefore, new WorkflowSettings('{"removeBackground": true }'), $this->defaultGlobalSettings);
	}

	public function testProcessorCountIsNotSetIfGlobalSettingsDoesNotContainProcessorCount() {
		$this->command->expects($this->once())
			->method('setCommand')
			->with('ocrmypdf -q --skip-text - - | cat');
		$this->command->expects($this->once())
			->method('execute')
			->willReturn(true);
		$this->command->expects($this->once())
			->method('getOutput')
			->willReturn('someOcrContent');

		$processor = new PdfOcrProcessor($this->command, $this->logger, $this->sidecarFileAccessor);
		$processor->ocrFile($this->fileBefore, $this->defaultSettings, $this->defaultGlobalSettings);
	}

	public function testProcessorCountIsSetCorrectlyFromGobalSettings() {
		$this->command->expects($this->once())
			->method('setCommand')
			->with('ocrmypdf -q --skip-text -j 42 - - | cat');
		$this->command->expects($this->once())
			->method('execute')
			->willReturn(true);
		$this->command->expects($this->once())
			->method('getOutput')
			->willReturn('someOcrContent');

		$this->defaultGlobalSettings->processorCount = 42;

		$processor = new PdfOcrProcessor($this->command, $this->logger, $this->sidecarFileAccessor);
		$processor->ocrFile($this->fileBefore, $this->defaultSettings, $this->defaultGlobalSettings);
	}

	public function testLogsInfoIfSidecarFileContentWasEmpty() {
		$this->command->expects($this->once())
			->method('execute')
			->willReturn(true);
		$this->command->expects($this->once())
			->method('getOutput')
			->willReturn('someOcrContent');
		$this->sidecarFileAccessor->expects($this->once())
			->method('getSidecarFileContent')
			->willReturn('');

		$this->logger->expects($this->once())
			->method('info')
			->with($this->callback(function ($message) {
				return strpos($message, 'Temporary sidecar file at') !== false && strpos($message, 'was empty') !== false;
			}));

		$processor = new PdfOcrProcessor($this->command, $this->logger, $this->sidecarFileAccessor);
		$processor->ocrFile($this->fileBefore, $this->defaultSettings, $this->defaultGlobalSettings);
	}

	public function testDoesNotLogInfoIfSidecarFileContentWasNotEmpty() {
		$this->command->expects($this->once())
			->method('execute')
			->willReturn(true);
		$this->command->expects($this->once())
			->method('getOutput')
			->willReturn('someOcrContent');
		$this->sidecarFileAccessor->expects($this->once())
			->method('getSidecarFileContent')
			->willReturn('someOcrContent');

		$this->logger->expects($this->never())
			->method('info');

		$processor = new PdfOcrProcessor($this->command, $this->logger, $this->sidecarFileAccessor);
		$processor->ocrFile($this->fileBefore, $this->defaultSettings, $this->defaultGlobalSettings);
	}

	public function testAppliesSidecarParameterIfSidecarFileCanBeCreated() {
		$this->command->expects($this->once())
			->method('setCommand')
			->with('ocrmypdf -q --skip-text --sidecar /tmp/sidecar.txt - - | cat');
		$this->command->expects($this->once())
			->method('execute')
			->willReturn(true);
		$this->command->expects($this->once())
			->method('getOutput')
			->willReturn('someOcrContent');
		$this->sidecarFileAccessor->expects($this->once())
			->method('getSidecarFileContent')
			->willReturn('someOcrContent');
		$this->sidecarFileAccessor->expects($this->once())
			->method('getOrCreateSidecarFile')
			->willReturn('/tmp/sidecar.txt');

		$processor = new PdfOcrProcessor($this->command, $this->logger, $this->sidecarFileAccessor);
		$processor->ocrFile($this->fileBefore, $this->defaultSettings, $this->defaultGlobalSettings);
	}
}
