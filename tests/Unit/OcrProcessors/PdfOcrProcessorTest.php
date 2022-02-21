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
use OCA\WorkflowOcr\Model\GlobalSettings;
use OCA\WorkflowOcr\Model\WorkflowSettings;
use OCA\WorkflowOcr\OcrProcessors\PdfOcrProcessor;
use OCA\WorkflowOcr\Wrapper\ICommand;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class PdfOcrProcessorTest extends TestCase {

	/** @var ICommand|MockObject */
	private $command;
	/** @var LoggerInterface|MockObject */
	private $logger;
	/** @var WorkflowSettings */
	private $defaultSettings;
	/** @var GlobalSettings */
	private $defaultGlobalSettings;

	protected function setUp(): void {
		parent::setUp();

		$this->command = $this->createMock(ICommand::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->defaultSettings = new WorkflowSettings();
		$this->defaultGlobalSettings = new GlobalSettings();
	}

	public function testCallsCommandInterface() {
		$pdfBefore = 'someFileContent';
		$pdfAfter = 'someOcrFileContent';

		$this->command->expects($this->once())
			->method('setCommand')
			->willReturn($this->command);
		$this->command->expects($this->once())
			->method('setStdIn')
			->with($pdfBefore)
			->willReturn($this->command);
		$this->command->expects($this->once())
			->method('execute')
			->willReturn(true);
		$this->command->expects($this->once())
			->method('getOutput')
			->willReturn($pdfAfter);
		
		$processor = new PdfOcrProcessor($this->command, $this->logger);
		$result = $processor->ocrFile($pdfBefore, $this->defaultSettings, $this->defaultGlobalSettings);
		
		$this->assertEquals($pdfAfter, $result);
	}

	public function testThrowsOcrNotPossibleException() {
		$pdfBefore = 'someFileContent';

		$this->command->expects($this->once())
			->method('setCommand')
			->willReturn($this->command);
		$this->command->expects($this->once())
			->method('setStdIn')
			->with($pdfBefore)
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

		$processor = new PdfOcrProcessor($this->command, $this->logger);
		$thrown = false;

		try {
			$processor->ocrFile($pdfBefore, $this->defaultSettings, $this->defaultGlobalSettings);
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
			->method('getOutput')
			->willReturn('someOcrFileContent');
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

		$processor = new PdfOcrProcessor($this->command, $this->logger);
		$processor->ocrFile('someContent', $this->defaultSettings, $this->defaultGlobalSettings);
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
		$this->command->expects($this->once())
			->method('getOutput')
			->willReturn('');

	
		$thrown = false;
		$processor = new PdfOcrProcessor($this->command, $this->logger);

		try {
			$processor->ocrFile('someContent', $this->defaultSettings, $this->defaultGlobalSettings);
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
			->with('ocrmypdf -q -l deu+eng --redo-ocr - - | cat');
		$this->command->expects($this->once())
			->method('execute')
			->willReturn(true);
		$this->command->expects($this->once())
			->method('getOutput')
			->willReturn('someOcrContent');

		$processor = new PdfOcrProcessor($this->command, $this->logger);
		$processor->ocrFile('someContent', new WorkflowSettings('{"languages": ["de", "en"] }'), $this->defaultGlobalSettings);
	}

	public function testInvalidLanguagesAreFiltered() {
		$this->command->expects($this->once())
			->method('setCommand')
			->with('ocrmypdf -q -l deu+eng --redo-ocr - - | cat');
		$this->command->expects($this->once())
			->method('execute')
			->willReturn(true);
		$this->command->expects($this->once())
			->method('getOutput')
			->willReturn('someOcrContent');

		$processor = new PdfOcrProcessor($this->command, $this->logger);
		$processor->ocrFile('someContent', new WorkflowSettings('{"languages": ["de", "invalid", "en"] }'), $this->defaultGlobalSettings);
	}

	public function testRemoveBackgroundFlagIsSetCorrectly() {
		$this->command->expects($this->once())
			->method('setCommand')
			->with('ocrmypdf -q --remove-background - - | cat');
		$this->command->expects($this->once())
			->method('execute')
			->willReturn(true);
		$this->command->expects($this->once())
			->method('getOutput')
			->willReturn('someOcrContent');

		$processor = new PdfOcrProcessor($this->command, $this->logger);
		$processor->ocrFile('someContent', new WorkflowSettings('{"removeBackground": true }'), $this->defaultGlobalSettings);
	}

	public function testProcessorCountIsNotSetIfGlobalSettingsDoesNotContainProcessorCount() {
		$this->command->expects($this->once())
		->method('setCommand')
		->with('ocrmypdf -q --redo-ocr - - | cat');
		$this->command->expects($this->once())
			->method('execute')
			->willReturn(true);
		$this->command->expects($this->once())
			->method('getOutput')
			->willReturn('someOcrContent');

		$processor = new PdfOcrProcessor($this->command, $this->logger);
		$processor->ocrFile('someContent', $this->defaultSettings, $this->defaultGlobalSettings);
	}

	public function testProcessorCountIsSetCorrectlyFromGobalSettings() {
		$this->command->expects($this->once())
		->method('setCommand')
		->with('ocrmypdf -q --redo-ocr -j 42 - - | cat');
		$this->command->expects($this->once())
			->method('execute')
			->willReturn(true);
		$this->command->expects($this->once())
			->method('getOutput')
			->willReturn('someOcrContent');

		$this->defaultGlobalSettings->processorCount = 42;

		$processor = new PdfOcrProcessor($this->command, $this->logger);
		$processor->ocrFile('someContent', $this->defaultSettings, $this->defaultGlobalSettings);
	}
}
